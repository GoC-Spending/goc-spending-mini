<?php
// A simple script to retrieve all proactive disclosure contract pages
// from the PWGSC website, and store them in an "output" folder.
// Depending on your folder permissions, you may have to create the
// "output" folder as a subdirectory before running this script.
// Estimated runtime is at least an hour on a home internet connection.

// This script only retrieves and stores the PWGSC contract pages,
// and doesn't do any actual scraping and analysis.

// toobs2017@gmail.com and the GoC-Spending team!

// Sample background usage:
// php scrapers/contracts-xpath-scraper.php > results.log 2>&1 &

// Require Guzzle, via composer package
// as well as the XpathSelector package 
// from https://github.com/stil/xpath-selector

// Note that the vendor directory is one level up
require dirname(__FILE__) . '/../vendor/autoload.php';
use GuzzleHttp\Client;
use XPathSelector\Selector;

// These aren't required in PHP 7+
if(function_exists('mb_language')) {
	mb_language('uni'); mb_internal_encoding('UTF-8');
}


// General fetcher configuration (across all departments)
// See below for department-specific URLs and text splitting
class Configuration {
	
	// Note that these should be changed before bulk-downloading all contracts
	// Set the limitQuarters and limitContractsPerQuarter values to 0 to ignore the limit and retrieve all contracts:
	public static $limitQuarters = 2;
	public static $limitContractsPerQuarter = 2;

	public static $departmentsToSkip = [
	];

	// Optionally sleep for a certain number (or fraction) of seconds in-between contract page downloads:
	public static $sleepBetweenDownloads = 0;

	// Optionally force downloading all files, including ones that have been already been downloaded:
	public static $redownloadExistingFiles = 1;

	// Output director for the contract pages (sub-categorized by owner department acronym)
	public static $outputFolder = 'contracts';

}


class DepartmentFetcher2 {

	public $guzzleClient;

	public $ownerAcronym;
	public $indexUrl;

	public $activeQuarterPage;
	public $totalContractsFetched = 0;


	public $contractContentSubsetXpath;
	public $contentSplitParameters = [];

	public $multiPage = 0;
	public $sleepBetweenDownloads = 0;
	public $quarterToContractPrefix = '';

	public function __construct($detailsArray = []) {

		if($this->baseUrl) {
			$this->guzzleClient = new Client(['base_uri' => $this->baseUrl]);
		}
		else {
			$this->guzzleClient = new Client;
		}
		



	}

	// By default, just return the same
	// Child classes can change this, to eg. add a parent URL
	public function quarterToContractUrlTransform($contractUrl) {
		return $contractUrl;
	}

	// In case we want to filter specific URLs out of the list of quarter URLs
	// Useful for departments (like CBSA) that change their schema halfway through :P 
	public function filterQuarterUrls($quarterUrls) {
		return $quarterUrls;
	}

	public function run() {

		// Run the operation!
		$startDate = date('Y-m-d H:i:s');
		echo "Starting " . $this->ownerAcronym . " at ". $startDate . " \n\n";
		$startTime = microtime(true);


		$indexPage = $this->getPage($this->indexUrl);

		$quarterUrls = $this->getQuarterUrls($indexPage);

		$quarterUrls = $this->filterQuarterUrls($quarterUrls);

		$quartersFetched = 0;
		foreach ($quarterUrls as $url) {

			if(Configuration::$limitQuarters && $quartersFetched >= Configuration::$limitQuarters) {
				break;
			}

			echo $url . "\n";

			// If the quarter pages have server-side pagination, then we need to get the multiple pages that represent that quarter. If there's only one page, then we'll put that as a single item in an array below, to simplify any later steps:
			$quarterMultiPages = [];
			if($this->multiPage == 1) {

				$quarterPage = $this->getPage($url);

				// If there aren't multipages, this just returns the original quarter URL back as a single item array:
				$quarterMultiPages = $this->getMultiPageQuarterUrls($quarterPage);

			}
			else {
				$quarterMultiPages = [ $url ];
			}


			$contractsFetched = 0;
			// Retrive all the (potentially multiple) pages from the given quarter:
			foreach($quarterMultiPages as $url) {
				echo "D: " . $url . "\n";

				$this->activeQuarterPage = $url;

				$quarterPage = $this->getPage($url);

				$contractUrls = $this->getContractUrls($quarterPage);

				foreach($contractUrls as $contractUrl) {

					if(Configuration::$limitContractsPerQuarter && $contractsFetched >= Configuration::$limitContractsPerQuarter) {
						break;
					}

					$contractUrl = $this->quarterToContractUrlTransform($contractUrl);

					echo "   " . $contractUrl . "\n";

					$this->downloadPage($contractUrl, $this->ownerAcronym);

					$this->totalContractsFetched++;
					$contractsFetched++;
				}

			}

			echo "$contractsFetched pages downloaded for this quarter.\n\n";

			$quartersFetched++;
		}
		// echo $indexPage;

	}

	public static function arrayFromHtml($htmlSource, $xpath) {

		$xs = Selector::loadHTML($htmlSource);

		$urls = $xs->findAll($xpath)->map(function ($node, $index) {
			return (string)$node;
		});

		return $urls;

	}

	public function getQuarterUrls($indexPage) {

		$urls = self::arrayFromHtml($indexPage, $this->indexToQuarterXpath);

		// var_dump($urls);

		$urls = array_unique($urls);

		return $urls;

	}

	public function getMultiPageQuarterUrls($quarterPage) {

		$urls = self::arrayFromHtml($quarterPage, $this->quarterMultiPageXpath);

		$urls = array_unique($urls);

		return $urls;

	}

	public function getContractUrls($quarterPage) {

		$urls = self::arrayFromHtml($quarterPage, $this->quarterToContractXpath);

		$urls = array_unique($urls);

		return $urls;

	}


	// Get a page using the Guzzle library
	// No longer a static function since we're reusing the client object between requests.
	// Ignores SSL verification per http://stackoverflow.com/a/32707976/756641
	public function getPage($url) {
		$response = $this->guzzleClient->request('GET', $url,
			[
				'verify' => false,
			]);
		return $response->getBody();
	}



	// Generic page download function
	// Downloads the requested URL and saves it to the specified directory
	// If the same URL has already been downloaded, it avoids re-downloading it again.
	// This makes it easier to stop and re-start the script without having to go from the very beginning again.
	public function downloadPage($url, $subdirectory = '') {

		$url = self::cleanupIncomingUrl($url);

		$filename = md5($url) . '.html';
		$directoryPath = dirname(__FILE__) . '/' . Configuration::$outputFolder;

		if($subdirectory) {
			$directoryPath .= '/' . $subdirectory;
		}

		// If the folder doesn't exist yet, create it:
		// Thanks to http://stackoverflow.com/a/15075269/756641
		if(! is_dir($directoryPath)) {
			mkdir($directoryPath, 0755, true);
		}

		// If that particular page has already been downloaded,
		// don't download it again.
		// That lets us re-start the script without starting from the very beginning again.
		if(file_exists($directoryPath . '/' . $filename) == false || Configuration::$redownloadExistingFiles) {

			// Download the page in question:
			$pageSource = $this->getPage($url);

			if($pageSource) {

				if($this->contentSplitParameters) {

					$split = explode($this->contentSplitParameters['startSplit'], $pageSource);
					$pageSource = explode($this->contentSplitParameters['endSplit'], $split[1])[0];

				}

				if($this->contractContentSubsetXpath) {

					$xs = Selector::loadHTML($pageSource);
					$pageSource = $xs->find($this->contractContentSubsetXpath)->innerHTML(); 

				}

				// Store it to a local location:
				file_put_contents($directoryPath . '/' . $filename, $pageSource);

				// Optionally sleep for a certain amount of time (eg. 0.1 seconds) in between fetches to avoid angry sysadmins:
				if(Configuration::$sleepBetweenDownloads) {
					sleep(Configuration::$sleepBetweenDownloads);
				}

				// This can now be configured per-department
				// The two are cumulative (eg. you could have a system-wide sleep configuration, and a department-specific, and it'll sleep for both durations.)
				if($this->sleepBetweenDownloads) {
					sleep($this->sleepBetweenDownloads);
				}

			}

			
			
			return true;

		}
		else {
			$this->totalAlreadyDownloaded += 1;
			return false;
		}


	}

	// For departments that use ampersands in link URLs, this seems to be necessary before retrieving the pages:
	public static function cleanupIncomingUrl($url) {

		$url = str_replace('&amp;', '&', $url);
		return $url;

	}

}



class InacFetcher extends DepartmentFetcher2 {

	public $indexUrl = 'http://www.aadnc-aandc.gc.ca/prodis/cntrcts/rprts-eng.asp';
	public $baseUrl = 'http://www.aadnc-aandc.gc.ca/';
	public $ownerAcronym = 'inac';

	// From the index page, list all the "quarter" URLs
	public $indexToQuarterXpath = "//div[@class='center']//ul/li/a/@href";

	public $multiPage = 1;
	public $quarterMultiPageXpath = "//div[@class='align-right size-small']/a/@href";

	public $quarterToContractXpath = "//table[@class='widthFull TableBorderBasic']//td//a/@href";

	public $contractContentSubsetXpath = "//div[@class='center']";

}


class CbsaFetcher extends DepartmentFetcher2 {

	public $indexUrl = 'http://www.cbsa-asfc.gc.ca/pd-dp/contracts-contrats/reports-rapports-eng.html';
	public $baseUrl = 'http://www.cbsa-asfc.gc.ca/';
	public $ownerAcronym = 'cbsa';

	// From the index page, list all the "quarter" URLs
	public $indexToQuarterXpath = "//main[@class='container']//ul/li/a/@href";

	public $multiPage = 0;

	public $quarterToContractXpath = "//table[@id='pdcon-table']//td//a/@href";
	public $quarterToContractPrefix = "";


	public $contractContentSubsetXpath = "//div[@id='wb-main-in']";

	// Since the a href tags on the quarter pages just return a path-relative URL, use this to prepend the rest of the URL path
	public function quarterToContractUrlTransform($contractUrl) {
		echo "Q: " . $this->activeQuarterPage . "\n";

		$urlArray = explode('/', $this->activeQuarterPage);
		array_pop($urlArray);

		$urlString = implode('/',$urlArray).'/';

		return $urlString.$contractUrl;

	}

	// Ignore the latest quarter that uses "open.canada.ca" as a link instead.
	// We'll need to retrieve those from the actual dataset.
	public function filterQuarterUrls($quarterUrls) {

		// Remove the new entries with "open.canada.ca"
		$quarterUrls = array_filter($quarterUrls, function($url) {
			if(strpos($url, 'open.canada.ca') !== false) {
				return false;
			}
			return true;
		});

		return $quarterUrls;
	}

}



// Run the Indigenous and Northern Affairs scraper:
// $inacFetcher = new InacFetcher;
// $inacFetcher->run();

// Run the CBSA fetcher
$cbsaFetcher = new CbsaFetcher;
$cbsaFetcher->run();

exit();




// Sample Xpath testing of locally-saved files

function testIndex($filename) {

	$xs = Selector::loadHTMLFile($filename);

	$urls = $xs->findAll("//main[@class='container']//ul/li/a/@href"); 

	foreach ($urls as $url) {
		echo $url . "\n";
	}

}

function testQuarter($filename) {

	$xs = Selector::loadHTMLFile($filename);

	$urls = $xs->findAll("//table[@id='pdcon-table']//td//a/@href"); 

	foreach ($urls as $url) {
		echo $url . "\n";
	}

}


// testIndex(dirname(__FILE__) . '/' . 'test/test.html');
testQuarter(dirname(__FILE__) . '/' . 'test/test2.html');


