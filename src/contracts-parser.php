<?php
// A simple script to retrieve all proactive disclosure contract pages
// from the PWGSC website, and store them in an "output" folder.
// Depending on your folder permissions, you may have to create the
// "output" folder as a subdirectory before running this script.
// Estimated runtime is at least an hour on a home internet connection.

// This script retrieves the contracts downloaded by contract-scraper.php
// And parses the data values contained in their HTML tables.
// A future update should merge these together, to do both operations in one pass.

// toobs2017@gmail.com and the GoC-Spending team!

require('contracts-helpers.php');

// Note that the vendor directory is one level up
require_once dirname(__FILE__) . '/../vendor/autoload.php';
use XPathSelector\Selector;

// Go crazy!
ini_set('memory_limit', '3200M');

class Configuration {

	public static $rawHtmlFolder = 'contracts';
	
	public static $jsonOutputFolder = 'generated-data';
	
	public static $departmentsToSkip = [
		'agr',
		'csa',
		'fin',
		'ic',
		'infra',
		'pwgsc',
		'sc',
		'tbs',
		'acoa',
		// 'pch',
		'dnd',
	];

	public static $limitDepartments = 0;
	public static $limitFiles = 2;

}

class DepartmentParser {

	public $acronym;
	public $contracts;

	public static $rowParams = [
			'uuid' => '',
			'vendorName' => '',
			'referenceNumber' => '',
			'contractDate' => '',
			'description' => '',
			'contractPeriodStart' => '',
			'contractPeriodEnd' => '',
			'startYear' => '',
			'endYear' => '',
			'deliveryDate' => '',
			'originalValue' => '',
			'contractValue' => '',
			'comments' => '',
			'ownerAcronym' => '',
			'sourceYear' => '',
			'sourceQuarter' => '',
			'sourceFilename' => '',
			'sourceURL' => '',
			'amendedValues' => [],
		];

	function __construct($acronym) {

		$this->acronym = $acronym;

	}

	public static function getSourceDirectory($acronym = false) {

		if($acronym) {
			return dirname(__FILE__) . '/' . Configuration::$rawHtmlFolder . '/' . $acronym;
		}
		else {
			return dirname(__FILE__) . '/' . Configuration::$rawHtmlFolder;
		}

	}

	

	public static function cleanParsedArray(&$values) {

		$values['startYear'] = Helpers::yearFromDate($values['contractPeriodStart']);
		$values['endYear'] = Helpers::yearFromDate($values['contractPeriodEnd']);

		$values['originalValue'] = Helpers::cleanupContractValue($values['originalValue']);
		$values['contractValue'] = Helpers::cleanupContractValue($values['contractValue']);

		if(! $values['contractValue']) {
			$values['contractValue'] = $values['originalValue'];
		}

		// Check for error-y non-unicode characters
		$values['referenceNumber'] = Helpers::cleanText($values['referenceNumber']);
		$values['vendorName'] = Helpers::cleanText($values['vendorName']);
		$values['comments'] = Helpers::cleanText($values['comments']);
		$values['description'] = Helpers::cleanText($values['description']);


	}

	public function parseDepartment() {

		$sourceDirectory = self::getSourceDirectory($this->acronym);

		$validFiles = [];
		$files = array_diff(scandir($sourceDirectory), ['..', '.']);

		foreach($files as $file) {
			// Check if it ends with .html
			$suffix = '.html';
			if(substr_compare( $file, $suffix, -strlen( $suffix )) === 0) {
				$validFiles[] = $file;
			}
		}

		$filesParsed = 0;
		foreach($validFiles as $file) {
			if(Configuration::$limitFiles && $filesParsed >= Configuration::$limitFiles) {
				break;
			}

			// Retrieve the values from the department-specific file parser
			// And merge these with the default values
			// Just to guarantee that all the array keys are around:
			$fileValues = array_merge(self::$rowParams, $this->parseFile($file));

			if($fileValues) {

				self::cleanParsedArray($fileValues);
				// var_dump($fileValues);

				$fileValues['ownerAcronym'] = $this->acronym;

				// Useful for troubleshooting:
				$fileValues['sourceFilename'] = $this->acronym . '/' . $file;

				// A lot of DND's entries are missing reference numbers:
				if(! $fileValues['referenceNumber']) {
					echo "Warning: no reference number.\n";
					$filehash = explode('.', $file)[0];

					$fileValues['referenceNumber'] = $filehash;

				}
				
				// TODO - update this to match the schema discussed at 2017-03-28's Civic Tech!
				$fileValues['uuid'] = $this->acronym . '-' . $fileValues['referenceNumber'];

				$referenceNumber = $fileValues['referenceNumber'];

				// If the row already exists, update it
				// Otherwise, add it
				if(isset($this->contracts[$referenceNumber])) {
					echo "Updating $referenceNumber\n";

					// Because we don't have a year/quarter for all organizations, let's use the largest contractValue for now:
					$existingContract = $this->contracts[$referenceNumber];
					if($fileValues['contractValue'] > $existingContract['contractValue']) {
						$this->contracts[$referenceNumber] = $fileValues;
					}

					// Add entries to the amendedValues array
					// If it's the first time, add the original too
					if($existingContract['amendedValues']) {
						$this->contracts[$referenceNumber]['amendedValues'] = array_merge($existingContract['amendedValues'], [$fileValues['contractValue']]);
					}
					else {
						$this->contracts[$referenceNumber]['amendedValues'] = [
							$existingContract['contractValue'],
							$fileValues['contractValue'],
						];
					}

				} else {
					// Add to the contracts array:
					$this->contracts[$referenceNumber] = $fileValues;
				}

			}
			else {
				echo "Error: could not parse data for $file\n";
			}

			

			$filesParsed++;

		}
		// var_dump($validFiles);

	}

	public function parseFile($filename) {

		$acronym = $this->acronym;

		if(! method_exists('FileParser', $acronym)) {
			echo 'Cannot find matching FileParser for ' . $acronym . "\n";
			return false;
		}

		$source = file_get_contents(self::getSourceDirectory($this->acronym) . '/' . $filename);

		$source = Helpers::initialSourceTransform($source, $acronym);

		return FileParser::$acronym($source);


	}

	public static function getDepartments() {

		$output = [];
		$sourceDirectory = self::getSourceDirectory();


		$departments = array_diff(scandir($sourceDirectory), ['..', '.']);

		// Make sure that these are really directories
		// This could probably done with some more elegant array map function
		foreach($departments as $department) {
			if(is_dir(dirname(__FILE__) . '/' . Configuration::$rawHtmlFolder . '/' . $department)) {
				$output[] = $department;
			}
		}

		return $output;

	}

	public static function parseAllDepartments() {

		// Run the operation!
		$startTime = microtime(true);

		// Question of the day is... how big can PHP arrays get?
		$output = [];

		$departments = DepartmentParser::getDepartments();

		$departmentsParsed = 0;
		foreach($departments as $acronym) {

			if(in_array($acronym, Configuration::$departmentsToSkip)) {
				echo "Skipping " . $acronym . "\n";
				continue;
			}

			if(Configuration::$limitDepartments && $departmentsParsed >= Configuration::$limitDepartments) {
				break;
			}

			$startDate = date('Y-m-d H:i:s');
			echo "Starting " . $acronym . " at ". $startDate . " \n";

			$department = new DepartmentParser($acronym);

			$department->parseDepartment();

			// Rather than storing the whole works in memory, 
			// let's just save one department at a time in individual
			// JSON files:

			$directoryPath = dirname(__FILE__) . '/' . Configuration::$jsonOutputFolder . '/' . $acronym;

			// If the folder doesn't exist yet, create it:
			// Thanks to http://stackoverflow.com/a/15075269/756641
			if(! is_dir($directoryPath)) {
				mkdir($directoryPath, 0755, true);
			}

			
			// Iterative check of json_encode
			// Trying to catch encoding issues
			if(file_put_contents($directoryPath . '/contracts.json', json_encode($department->contracts, JSON_PRETTY_PRINT))) {
				echo "...saved.\n";
			}
			else {
				// echo "STARTHERE: \n";
				// var_export($departmentArray);

				// echo "ENDHERE. \n";
				echo "...failed.\n";

				$newOutput = [];

				$index = 0;
				$limit = 1000000;

				foreach($department->contracts as $key => $data) {
					$index++;
					if($index > $limit) {
						break;
					}
					$newOutput[$key] = $data;

					echo $index;

					if(json_encode($data, JSON_PRETTY_PRINT)) {
						echo " P\n";
					}
					else {
						echo " F\n";
						var_dump($key);
						var_dump($data);
						exit();
					}

				}

			}


			// var_dump($department->contracts);
			// var_dump(json_encode($department->contracts, JSON_PRETTY_PRINT));
			// $output[$acronym] = $department->contracts;

			echo "Started " . $acronym . " at " . $startDate . "\n";
			echo "Finished at ". date('Y-m-d H:i:s') . " \n\n";

			$departmentsParsed++;

		}

		

	}




}

class FileParser {

	// Fun with Regular Expressions
	// ([A-Z])\w+
	public static function agr($html) {

		$values = [];
		$keyToLabel = [
			'vendorName' => 'Vendor Name:',
			'referenceNumber' => 'Reference Number:',
			'contractDate' => 'Contract Date:',
			'description' => 'Description Of Work:',
			'contractPeriodStart' => '',
			'contractPeriodEnd' => '',
			'contractPeriodRange' => 'Contract Period:',
			'deliveryDate' => '',
			'originalValue' => 'Original Contract Value:',
			'contractValue' => 'Contract Value:',
			'comments' => 'Comments:',
		];
		$labelToKey = array_flip($keyToLabel);

		$matches = [];
		$pattern = '/<th scope="row">([\wÀ-ÿ@$#%^&+\*\-.\'(),;:<\/>\s]*)<\/th><td>([\wÀ-ÿ@$#%^&+\*\-.\'(),;:<\/>\s]*)<\/td>/';

		preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

		// var_dump($matches);

		foreach($matches as $match) {

			$label = $match[1];
			$value = $match[2];

			if(array_key_exists($label, $labelToKey)) {

				$values[$labelToKey[$label]] = Helpers::cleanHtmlValue($value);

			}

		}

		// Change the "to" range into start and end values:
		if(isset($values['contractPeriodRange']) && $values['contractPeriodRange']) {
			$split = explode(' to ', $values['contractPeriodRange']);
			$values['contractPeriodStart'] = $split[0];
			$values['contractPeriodEnd'] = $split[1];
		}

		return $values;

	}

	public static function csa($html) {

		// Just get the table in the middle:
		$html = Helpers::stringBetween('DEBUT DU CONTENU', 'FIN DU CONTENU', $html);

		$values = [];

		$keys = [
			'vendorName',
			'referenceNumber',
			'description',
			'deliveryDate',
			'contractValue',
			'comments',
		];

		$keysWithModifications = [
			'vendorName',
			'referenceNumber',
			'description',
			'deliveryDate',
			'originalValue',
			'modificationValue',
			'contractValue',
			'comments',
		];

		$matches = [];
		$pattern = '/<td class="align-middle">([\wÀ-ÿ@$#%^&+\*\-.\'(),;:<\/>\s]*)<\/td>/';

		preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

		// var_dump($matches);

		if(count($matches) == 8) {
			$keys = $keysWithModifications;
		}

		foreach($matches as $index => $match) {

			$value = $match[1];

			if(array_key_exists($index, $keys)) {

				$value = Helpers::cleanHtmlValue($value);

				$values[$keys[$index]] = $value;

			}

		}

		// Interestingly, the decimal points for CSA are commas rather than periods (probably coded in French originally).
		if(isset($values['originalValue'])) {
			$values['originalValue'] = str_replace([',', ' '], ['.', ''], $values['originalValue']);
		}
		if(isset($values['contractValue'])) {
			$values['contractValue'] = str_replace([',', ' '], ['.', ''], $values['contractValue']);
		}
		if(isset($values['modificationValue'])) {
			$values['modificationValue'] = str_replace([',', ' ', '$'], ['.', '', ''], $values['modificationValue']);
		}

		


		// If there isn't an originalValue, use the contractValue
		if(! (isset($values['originalValue']) && $values['originalValue'])) {
			$values['originalValue'] = $values['contractValue'];
		}

		// Do a separate regular expression to retrieve the time values
		// The first one is the contract date, and the second two are the start and end dates
		// For the contract period (the second two values), these are inexplicably in YYYY-DD-MMM format (eg. 2011-31-003)
		$matches = [];
		$pattern = '/<time datetime="([\w-@$#%^&+.,;:<\/>\s]*)">/';

		preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

		// var_dump($matches);

		$values['contractDate'] = $matches[0][1];

		// Fix the date issue while we're at it:
		if(isset($matches[1][1])) {
			$values['contractPeriodStart'] = Helpers::switchMonthsAndDays(str_replace('-00', '-0', $matches[1][1]));
		}
		if(isset($matches[2][1])) {
			$values['contractPeriodEnd'] = Helpers::switchMonthsAndDays(str_replace('-00', '-0', $matches[2][1]));
		}
		
		

		// var_dump($values);

		return $values;

	}

	public static function fin($html) {

		$html = Helpers::stringBetween('MainContentStart', 'MainContentEnd', $html);

		$values = [];
		$keyToLabel = [
			'vendorName' => 'Vendor Name:',
			'referenceNumber' => 'Reference Number:',
			'contractDate' => 'Contract Date:',
			'description' => 'Description of work:',
			'contractPeriodStart' => '',
			'contractPeriodEnd' => '',
			'contractPeriodRange' => 'Contract Period:',
			'deliveryDate' => 'Delivery Date:',
			'originalValue' => 'Original Contract Value:',
			'contractValue' => 'Contract Value:',
			'comments' => 'Comments:',
		];
		$labelToKey = array_flip($keyToLabel);

		$matches = [];
		$pattern = '/<div class="span-2"><strong>([\wÀ-ÿ@$#%^&+\*\-.\'(),;:<\/>\s]*)<\/strong><\/div>[\s]*<div class="span-3">([\wÀ-ÿ@$#%^&+\*\-.\'(),;:<\/>\s]*)<\/div>/';

		preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

		// var_dump($matches);
		// exit();

		foreach($matches as $match) {

			$label = trim(str_replace('&nbsp;', '', $match[1]));
			$value = trim(str_replace('&nbsp;', '', $match[2]));

			if(array_key_exists($label, $labelToKey)) {

				$values[$labelToKey[$label]] = Helpers::cleanHtmlValue($value);

			}

		}

		// Change the "to" range into start and end values:
		if(isset($values['contractPeriodRange']) && $values['contractPeriodRange']) {
			$split = explode(' to ', $values['contractPeriodRange']);
			$values['contractPeriodStart'] = $split[0];
			$values['contractPeriodEnd'] = $split[1];


		}

		return $values;

	}

	public static function infra($html) {

		$html = Helpers::stringBetween('MainContentStart', 'MainContentEnd', $html);

		$values = [];
		$keyToLabel = [
			'vendorName' => 'Vendor Name',
			'referenceNumber' => 'Reference Number',
			'contractDate' => 'Contract Date',
			'description' => 'Description of Work',
			'contractPeriodStart' => '',
			'contractPeriodEnd' => '',
			'contractPeriodRange' => 'Contract Period',
			'deliveryDate' => 'Delivery Date',
			'originalValue' => 'Original Contract Value',
			'contractValue' => 'Contract Value',
			'comments' => 'Comments',
		];
		$labelToKey = array_flip($keyToLabel);

		$matches = [];
		$pattern = '/<th>([\wÀ-ÿ@$#%^&+\*\-.\'(),;:\/\s]*)<\/th>[\s]*<td>([\wÀ-ÿ@$#%^&+\*\-.\'(),\/;:\s]*)<\/td>/';

		preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

		// var_dump($matches);
		// exit();

		foreach($matches as $match) {

			$label = trim(str_replace('&nbsp;', '', $match[1]));
			$value = trim(str_replace('&nbsp;', '', $match[2]));

			if(array_key_exists($label, $labelToKey)) {

				$values[$labelToKey[$label]] = Helpers::cleanHtmlValue($value);

			}

		}

		// Change the "to" range into start and end values:
		if(isset($values['contractPeriodRange']) && $values['contractPeriodRange']) {
			$split = explode(' to ', $values['contractPeriodRange']);
			$values['contractPeriodStart'] = $split[0];
			$values['contractPeriodEnd'] = $split[1];


		}

		return $values;

	}

	public static function ic($html) {

		$html = Helpers::stringBetween('<div typeof="Action">', '<p class="notPrintable">', $html);

		// Remove spans that are kind of un-helpful
		$html = str_replace([
			'<span property="agent">',
			'<span property="startTime">',
			'<span property="description">',
			'<span property="object">',
			'</span>',
			], '', $html);

		// var_dump($html);
		// exit();

		$values = [];
		$keyToLabel = [
			'vendorName' => 'Vendor name:',
			'referenceNumber' => 'Reference number:',
			'contractDate' => 'Contract date:',
			'description' => 'Description of work:',
			'contractPeriodStart' => '',
			'contractPeriodEnd' => '',
			'contractPeriodRange' => 'Contract period / delivery date:',
			'deliveryDate' => 'Delivery Date:',
			'originalValue' => 'Original contract value:',
			'contractValue' => 'Contract value:',
			'comments' => 'Comments:',
		];
		$labelToKey = array_flip($keyToLabel);

		$matches = [];
		$pattern = '/<div class="ic2col1 formLeftCol">([\wÀ-ÿ@$#%^&+\*\-.\'(),;:<\/>\s]*)<\/div>[\s]*<div class="ic2col2 formRightCol">([\wÀ-ÿ@$#%^&+\*\-.\'(),;:<\/>\s]*)<\/div>/';

		preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

		// var_dump($matches);
		// exit();

		foreach($matches as $match) {

			$label = trim(str_replace('&nbsp;', '', $match[1]));
			$value = trim(str_replace('&nbsp;', '', $match[2]));

			if(array_key_exists($label, $labelToKey)) {

				$values[$labelToKey[$label]] = Helpers::cleanHtmlValue($value);

			}

		}

		// Change the "to" range into start and end values:
		if(isset($values['contractPeriodRange']) && $values['contractPeriodRange']) {
			$split = explode(' - ', $values['contractPeriodRange']);
			$values['contractPeriodStart'] = trim($split[0]);
			$values['contractPeriodEnd'] = trim($split[1]);


		}

		// var_dump($values);
		// exit();

		return $values;

	}

	public static function pwgsc($html) {

		$html = Helpers::stringBetween('MainContentStart', 'MainContentEnd', $html);

		$values = [];
		$keyToLabel = [
			'vendorName' => 'Vendor Name',
			'referenceNumber' => 'Reference Number',
			'contractDate' => 'Contract Date',
			'description' => 'Description of Work',
			'contractPeriodStart' => 'Contract Period - From',
			'contractPeriodEnd' => 'Contract Period - To',
			'contractPeriodRange' => 'Contract Period',
			'deliveryDate' => 'Delivery Date',
			'originalValue' => 'Contract Value',
			'contractValue' => 'Total Amended Contract Value',
			'comments' => 'Comments',
		];
		$labelToKey = array_flip($keyToLabel);

		$matches = [];
		$pattern = '/<th scope="row" class="row">([\wÀ-ÿ@$#%^&+\*\-.\'(),;:\/\s]*)<\/th>[\s]*<td>([\wÀ-ÿ@$#%^&+\*\-.\'(),\/;:\s]*)<\/td>/';

		preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

		// var_dump($matches);
		// exit();

		foreach($matches as $match) {

			$label = trim(str_replace('&nbsp;', '', $match[1]));
			$value = trim(str_replace('&nbsp;', '', $match[2]));

			if(array_key_exists($label, $labelToKey)) {

				$values[$labelToKey[$label]] = Helpers::cleanHtmlValue($value);

			}

		}

		// Change the "to" range into start and end values:
		if(isset($values['contractPeriodRange']) && $values['contractPeriodRange']) {
			$split = explode(' to ', $values['contractPeriodRange']);
			$values['contractPeriodStart'] = $split[0];
			$values['contractPeriodEnd'] = $split[1];


		}

		return $values;

	}

	public static function sc($html) {

		$html = Helpers::stringBetween('the main content', 'end main content', $html);

		$values = [];
		$keyToLabel = [
			'vendorName' => 'Vendor Name :',
			'referenceNumber' => 'Reference Number :',
			'contractDate' => 'Contract Date :',
			'description' => 'Description of Work :',
			'contractPeriodStart' => '',
			'contractPeriodEnd' => '',
			'contractPeriodRange' => 'Contract Period :',
			'deliveryDate' => 'Delivery Date :',
			'originalValue' => '',
			'contractValue' => 'Current Contract Value :',
			'comments' => 'Comments :',
		];
		$labelToKey = array_flip($keyToLabel);

		$matches = [];
		$pattern = '/<th class="tbpercent33" scope="row">([\wÀ-ÿ@$#%^&+\*\-.\'(),;:\/\s]*)<\/th>[\s]*<td class="tbpercent66">([\wÀ-ÿ@$#%^&+\*\-.\'()\/,;:\s]*)<\/td>/';

		preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

		// var_dump($matches);
		// exit();

		foreach($matches as $match) {

			$label = trim(str_replace('&nbsp;', '', $match[1]));
			$value = trim(str_replace(['&nbsp;', 'N/A'], '', $match[2]));

			if(array_key_exists($label, $labelToKey)) {

				$values[$labelToKey[$label]] = Helpers::cleanHtmlValue($value);

			}

		}

		// Change the "to" range into start and end values:
		if(isset($values['contractPeriodRange']) && $values['contractPeriodRange']) {
			$split = explode(' to ', $values['contractPeriodRange']);
			$values['contractPeriodStart'] = trim($split[0]);
			$values['contractPeriodEnd'] = trim($split[1]);


		}

		return $values;

	}

	public static function tbs($html) {

		$html = Helpers::stringBetween('mainContentOfPage', 'Report a problem', $html);

		$values = [];
		$keyToLabel = [
			'vendorName' => 'Vendor Name:',
			'referenceNumber' => 'Reference Number:',
			'contractDate' => 'Contract Date:',
			'description' => 'Description of work:',
			'contractPeriodStart' => '',
			'contractPeriodEnd' => '',
			'contractPeriodRange' => 'Contract Period:',
			'deliveryDate' => 'Delivery Date:',
			'originalValue' => '',
			'contractValue' => 'Contract Value:',
			'comments' => 'Comments:',
		];
		$labelToKey = array_flip($keyToLabel);

		$matches = [];
		$pattern = '/<strong>([\wÀ-ÿ@$#%^&+\*\-.\'(),;:\/\s]*)<\/strong><\/th><td>([\wÀ-ÿ@$#%^&+\*\-.\'()\/,;:\s]*)<\/td>/';

		preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

		// var_dump($matches);
		// exit();

		foreach($matches as $match) {

			$label = trim(str_replace('&nbsp;', '', $match[1]));
			$value = trim(str_replace(['&nbsp;', 'N/A'], '', $match[2]));

			if(array_key_exists($label, $labelToKey)) {

				$values[$labelToKey[$label]] = Helpers::cleanHtmlValue($value);

			}

		}

		// Change the "to" range into start and end values:
		if(isset($values['contractPeriodRange']) && $values['contractPeriodRange']) {
			$split = explode(' to ', $values['contractPeriodRange']);
			$values['contractPeriodStart'] = trim($split[0]);
			$values['contractPeriodEnd'] = trim($split[1]);


		}

		return $values;

	}

	public static function dnd($html) {

		// DND and departments after are pre-trimmed to just the content table, -ish, to save hard drive space.

		$values = [];
		$keyToLabel = [
			'vendorName' => 'Vendor Name',
			'referenceNumber' => 'Reference Number',
			'contractDate' => 'Contract Date',
			'description' => 'Description of Work',
			'contractPeriodStart' => '',
			'contractPeriodEnd' => '',
			'contractPeriodRange' => 'Contract Period',
			'deliveryDate' => 'Delivery Date',
			'originalValue' => '',
			'contractValue' => 'Contract Value',
			'comments' => 'Comments',
		];
		$labelToKey = array_flip($keyToLabel);

		$matches = [];
		$pattern = '/<tr><th>([\wÀ-ÿ@$#%^&+\*\-.\'(),;:\/\s]*)<\/th><td>([\wÀ-ÿ@$#%^&+\*\-.\'()\/,;:\s]*)<\/td>/';

		preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

		// var_dump($matches);
		// exit();

		foreach($matches as $match) {

			$label = trim(str_replace('&nbsp;', '', $match[1]));
			$value = trim(str_replace(['&nbsp;', 'N/A'], '', $match[2]));

			if(array_key_exists($label, $labelToKey)) {

				$values[$labelToKey[$label]] = Helpers::cleanHtmlValue($value);

			}

		}

		// Change the "to" range into start and end values:
		if(isset($values['contractPeriodRange']) && $values['contractPeriodRange']) {
			$split = explode(' to ', $values['contractPeriodRange']);
			$values['contractPeriodStart'] = trim($split[0]);
			$values['contractPeriodEnd'] = trim($split[1]);


		}

		// Fix dates that are in d-m-yyyy format
		foreach(['contractDate', 'deliveryDate', 'contractPeriodStart', 'contractPeriodEnd'] as $dateField) {
			if(isset($values[$dateField]) && $values[$dateField]) {
				$values[$dateField] = Helpers::fixDndDate($values[$dateField]);
			}
		}



		return $values;

	}

	// Parser for CBSA, now powered by *drumroll* XPath! 
	// It's ...way easier!
	// This might be abstractable to some generic function in the future, since if we're lucky, a lot of departments will be able to re-use this by just changing the XPath lookups.
	public static function cbsa($html) {

		$values = [];
		$keyToLabel = [
			'vendorName' => 'Vendor Name:',
			'referenceNumber' => 'Reference Number:',
			'contractDate' => 'Contract Date:',
			'description' => 'Description of work:',
			'contractPeriodStart' => '',
			'contractPeriodEnd' => '',
			'contractPeriodRange' => 'Contract Period:',
			'deliveryDate' => 'Delivery Date:',
			'originalValue' => '',
			'contractValue' => 'Contract Value:',
			'comments' => 'Comments:',
		];

		$cleanKeys = [];
		foreach($keyToLabel as $key => $label) {
			$cleanKeys[$key] = Helpers::cleanLabelText($label);
		}

		$labelToKey = array_flip($cleanKeys);

		$xs = Selector::loadHTML($html);

		// Extracts the keys (from the <th> tags) in order
		$keyXpath = "//table[@class='contractDetail span-6']//th";
		$keyNodes = $xs->findAll($keyXpath)->map(function ($node, $index) {
			return (string)$node;
		});

		// Extracts the values (from the <td> tags) in hopefully the same order:
		$valueXpath = "//table[@class='contractDetail span-6']//td";
		$valueNodes = $xs->findAll($valueXpath)->map(function ($node, $index) {
			return (string)$node;
		});

		$keys = [];

		// var_dump($keyNodes);
		// var_dump($valueNodes);

		foreach($keyNodes as $index => $keyNode) {

			$keyNode = Helpers::cleanLabelText($keyNode);

			if($labelToKey[$keyNode]) {
				$values[$labelToKey[$keyNode]] = Helpers::cleanHtmlValue($valueNodes[$index]);
			}

		}

		// var_dump($values);
		// exit();

		// Change the "to" range into start and end values:
		if(isset($values['contractPeriodRange']) && $values['contractPeriodRange']) {
			$split = explode(' to ', $values['contractPeriodRange']);
			$values['contractPeriodStart'] = trim($split[0]);
			$values['contractPeriodEnd'] = trim($split[1]);
		}

		// var_dump($values);
		return $values;

	}

	// Parser for INAC, based on the XPath parser (originally for CBSA above)
	public static function inac($html) {

		return Helpers::genericXpathParser($html, "//table[@class='widthFull TableBorderBasic']//th", "//table[@class='widthFull TableBorderBasic']//td", ' - ');

	}

	// Parser for CIC
	public static function cic($html) {

		$keyArray = [
			'vendorName' => 'Vendor Name',
			'referenceNumber' => 'Reference Number',
			'contractDate' => 'Contract Date',
			'description' => 'Description of work',
			'contractPeriodStart' => '',
			'contractPeriodEnd' => '',
			'contractPeriodRange' => 'Contract Period',
			'deliveryDate' => '',
			'originalValue' => '',
			'contractValue' => 'Contract Value',
			'comments' => 'Comments',
			'additionalComments' => 'Additional Comments',
		];

		return Helpers::genericXpathParser($html, "//table//th", "//table//td", ' to ', $keyArray);

	}

	// Parser for HC
	public static function hc($html) {

		$keyArray = [
			'vendorName' => 'Vendor Name:',
			'referenceNumber' => 'Reference Number:',
			'contractDate' => 'Contract Date:',
			'description' => 'Description of work:',
			'contractPeriodStart' => '',
			'contractPeriodEnd' => '',
			'contractPeriodRange' => 'Contract Period:',
			'deliveryDate' => 'Delivery Date:',
			'originalValue' => 'Original Contract Value',
			'contractValue' => 'Overall Contract Value',
			'comments' => 'Comments:',
		];

		return Helpers::genericXpathParser($html, "//h2", "//p", ' to ', $keyArray);

	}

	// Parser for EC
	public static function ec($html) {

		return Helpers::genericXpathParser($html, "//table//td[@scope='row']", "//table//td[@class='alignTopLeft']", ' to ');

	}

	// Parser for ESDC
	public static function esdc($html) {

		$keyArray = [
			'vendorName' => 'Vendor Name:',
			'referenceNumber' => 'Reference Number:',
			'contractDate' => 'Contract Date:',
			'description' => 'Description of work:',
			'detailedDescription' => 'Description (more details):',
			'contractPeriodStart' => '',
			'contractPeriodEnd' => '',
			'contractPeriodRange' => 'Contract Period:',
			'deliveryDate' => 'Delivery Date:',
			'originalValue' => '',
			'contractValue' => 'Current Contract Value',
			'comments' => 'Comments:',
		];

		return Helpers::genericXpathParser($html, "//table//th", "//table//td", ' to ', $keyArray);

	}

	// Parser for PCH
	public static function pch($html) {

		// To easily take care of the 
		// Description&nbsp;of&nbsp;Work
		// label for the description key:
		$html = str_replace('&nbsp;', ' ', $html);

		return Helpers::genericXpathParser($html, "//table//th", "//table//td", ' to ');

	}

	// Parser for RCMP
	public static function rcmp($html) {

		$values = Helpers::genericXpathParser($html, "//table//th", "//table//td", 'to');

		// The RCMP includes both original and amended values in the same cell, but only displays the amended value when there is one (seemingly, most of the time). This tries to re-split these into separate fields.

		// Amended value is first, then original.
		// For example,
		// Amended contract value:$45,375.00Original contract value:$32,450.00

		$contractValueSplit = str_replace('Amended contract value:', '', $values['contractValue']);
		$contractValueSplit = explode('Original contract value:', $contractValueSplit);

		$amendedValue = $contractValueSplit[0];
		$originalValue = $contractValueSplit[1];

		if($originalValue) {
			$values['originalValue'] = $originalValue;
			$values['contractValue'] = $originalValue;
		}
		if($amendedValue) {
			$values['contractValue'] = $amendedValue;
		}

		return $values;

	}

}

DepartmentParser::parseAllDepartments();