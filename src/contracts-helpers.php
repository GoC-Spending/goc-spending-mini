<?php
// Helper-functions class for the contracts parser and related scripts.

// toobs2017@gmail.com and the GoC-Spending team!

// These aren't required in PHP 7+

namespace GoCSpending;

if(function_exists('mb_language')) {
	mb_language('uni'); mb_internal_encoding('UTF-8');
}

// Note that the vendor directory is one level up
require_once dirname(__FILE__) . '/../vendor/autoload.php';
use XPathSelector\Selector;

class Helpers {

	public static function cleanupDate($dateInput, $printErrors = 1) {

		// 11/1/2013
		$time = strtotime($dateInput);
		if($time) {
			return $time;
		}
		else {
			// Try doing the month switch and see if that helps:
			if($dateInput) {
				$time = strtotime(self::switchMonthsAndDays($dateInput, $printErrors));
				if($time) {

					if($printErrors) {
						echo "Switched months and days for: '$dateInput'\n";
					}
					return $time;
				}
				else {
					if($printErrors) {
						echo "Date cleanup error: '$dateInput'\n";
					}
					
				}
			}
			// If there's no $dateInput at all, don't print an error:
			return false;
		}
		


	}

	// Should transition to yearFromDate (below) which is more reliable:
	public static function dateToYear($dateInput) {

		$time = self::cleanupDate($dateInput);
		if($time) {
			return date('Y', $time);
		}
		else {
			return false;
		}

	}

	// Use a regex for the same thing as above, but more reliably:
	public static function yearFromDate($dateInput) {

		// ([1-2][0-9][0-9][0-9])

		$matches = [];
		$pattern = '/([1-2][0-9][0-9][0-9])/';

		$year = preg_match($pattern, $dateInput, $matches);
		if($matches) {
			return $matches[1];
		}

		return false;

	}

	public static function fixDndDate($dateInput) {

		$year = self::yearFromDate($dateInput);

		// Default backup values
		$month = '01';
		$day = '01';

		$matches = [];
		$pattern = '/([0-9]+)-/';

		preg_match_all($pattern, $dateInput, $matches, PREG_SET_ORDER);

		if($matches) {
			if(isset($matches[0][1])) {
				$day = str_pad($matches[0][1], 2, '0', STR_PAD_LEFT);
			}
			if(isset($matches[1][1])) {
				$month = str_pad($matches[1][1], 2, '0', STR_PAD_LEFT);
			}
		}

		return $year . '-' . $month . '-' . $day;

	}

	// Uses a series of regular expressions to cleanup bad date data
	// This often probably gets months and days mixed-up, but that's okay.
	// We're going for nearest year in this case.
	public static function regDateCleanup($dateInput) {

		if(! $dateInput) {
			return false;
		}

		$year = null;
		$month = null;
		$day = null;

		$yearMatches = [];
		$pattern = '/([1-2][0-9][0-9][0-9])/';
		preg_match($pattern, $dateInput, $yearMatches);

		if($yearMatches) {
			$year = $yearMatches[1];
		}

		$monthMatches = [];
		$pattern = '/([0-1][0-2])/';
		preg_match($pattern, str_replace($year, '', $dateInput), $monthMatches);

		if($monthMatches) {
			$month = $monthMatches[1];
		}

		$dayMatches = [];
		$pattern = '/([0-3][0-9])/';
		preg_match($pattern, str_replace([$year, $month], '', $dateInput), $dayMatches);

		if($dayMatches) {
			$day = $dayMatches[1];
		}

		if(! $month) {
			$month = '01';
		}
		if(! $day) {
			$day = '01';
		}

		if($year) {
			return $year . '-' . $month . '-' . $day;
		}
		else {
			echo "Could not parse '$dateInput'\n";
			return false;
		}
		
	}

	// Thanks to
	// https://stackoverflow.com/a/6059008/756641
	// Usage
	// $str = unicodeString("\u1000");
	public static function unicodeString($str, $encoding=null) {
	    if (is_null($encoding)) $encoding = ini_get('mbstring.internal_encoding');
	    return preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/u', function($match) use ($encoding) {
	        return mb_convert_encoding(pack('H*', $match[1]), $encoding, 'UTF-16BE');
	    }, $str);
	}

	public static function cleanupContractValue($input) {

		$output = str_replace(['$', ','], '', $input);
		return floatval($output);

	}

	public static function cleanHtmlValue($value) {

		$value = str_replace(['&nbsp;', '&amp;', '&AMP;'], [' ', '&', '&'], $value);
		
		// Clean up any pesky unicode characters
		// In the case of \u00A0, it's a non-breaking space:
		// Not exactly sure why the Â shows up though...
		$value = str_replace([self::unicodeString("\u00A0"), ' ', 'Â'], '', $value);

		$value = trim(strip_tags($value));
		return $value;

	}

	public static function cleanLabelText($label) {

		$label = str_replace([':', ' '], '', strtolower($label));
		return trim($label);

	}

	public static function removeLinebreaks($input) {

		$output = str_replace(["\n", "\r", "\t"], ' ', $input);
		return trim($output);

	}

	public static function switchMonthsAndDays($dateString, $printErrors = 1) {
		// Takes a YYYY-DD-MM (whyyyy, CSA?)
		// and changes it to YYYY-MM-DD

		$split = explode('-', $dateString);
		if(count($split) == 3) {
			return $split[0] . '-' . $split[2] . '-' . $split[1];
		}
		else {
			if($printErrors) {
				echo "Error: could not switchMonthsAndDays for '$dateString'\n";
			}
			
			return false;
		}
		

	}

	public static function stringBetween($start, $end, $string) {

		if(! $string) {
			return '';
		}

		$split = explode($start, $string);
		return explode($end, $split[1])[0];

	}

	public static function cleanText($inputText) {

		// return self::cleanNonAsciiCharactersInString($inputText);
		return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $inputText);

	}

	// Thanks to,
	// http://stackoverflow.com/a/24925209/756641
	public static function cleanNonAsciiCharactersInString($orig_text) {

	    $text = $orig_text;

	    // Single letters
	    $text = preg_replace("/[∂άαáàâãªä]/u",      "a", $text);
	    $text = preg_replace("/[∆лДΛдАÁÀÂÃÄ]/u",     "A", $text);
	    $text = preg_replace("/[ЂЪЬБъь]/u",           "b", $text);
	    $text = preg_replace("/[βвВ]/u",            "B", $text);
	    $text = preg_replace("/[çς©с]/u",            "c", $text);
	    $text = preg_replace("/[ÇС]/u",              "C", $text);        
	    $text = preg_replace("/[δ]/u",             "d", $text);
	    $text = preg_replace("/[éèêëέëèεе℮ёєэЭ]/u", "e", $text);
	    $text = preg_replace("/[ÉÈÊË€ξЄ€Е∑]/u",     "E", $text);
	    $text = preg_replace("/[₣]/u",               "F", $text);
	    $text = preg_replace("/[НнЊњ]/u",           "H", $text);
	    $text = preg_replace("/[ђћЋ]/u",            "h", $text);
	    $text = preg_replace("/[ÍÌÎÏ]/u",           "I", $text);
	    $text = preg_replace("/[íìîïιίϊі]/u",       "i", $text);
	    $text = preg_replace("/[Јј]/u",             "j", $text);
	    $text = preg_replace("/[ΚЌК]/u",            'K', $text);
	    $text = preg_replace("/[ќк]/u",             'k', $text);
	    $text = preg_replace("/[ℓ∟]/u",             'l', $text);
	    $text = preg_replace("/[Мм]/u",             "M", $text);
	    $text = preg_replace("/[ñηήηπⁿ]/u",            "n", $text);
	    $text = preg_replace("/[Ñ∏пПИЙийΝЛ]/u",       "N", $text);
	    $text = preg_replace("/[óòôõºöοФσόо]/u", "o", $text);
	    $text = preg_replace("/[ÓÒÔÕÖθΩθОΩ]/u",     "O", $text);
	    $text = preg_replace("/[ρφрРф]/u",          "p", $text);
	    $text = preg_replace("/[®яЯ]/u",              "R", $text); 
	    $text = preg_replace("/[ГЃгѓ]/u",              "r", $text); 
	    $text = preg_replace("/[Ѕ]/u",              "S", $text);
	    $text = preg_replace("/[ѕ]/u",              "s", $text);
	    $text = preg_replace("/[Тт]/u",              "T", $text);
	    $text = preg_replace("/[τ†‡]/u",              "t", $text);
	    $text = preg_replace("/[úùûüџμΰµυϋύ]/u",     "u", $text);
	    $text = preg_replace("/[√]/u",               "v", $text);
	    $text = preg_replace("/[ÚÙÛÜЏЦц]/u",         "U", $text);
	    $text = preg_replace("/[Ψψωώẅẃẁщш]/u",      "w", $text);
	    $text = preg_replace("/[ẀẄẂШЩ]/u",          "W", $text);
	    $text = preg_replace("/[ΧχЖХж]/u",          "x", $text);
	    $text = preg_replace("/[ỲΫ¥]/u",           "Y", $text);
	    $text = preg_replace("/[ỳγўЎУуч]/u",       "y", $text);
	    $text = preg_replace("/[ζ]/u",              "Z", $text);

	    // Punctuation
	    $text = preg_replace("/[‚‚]/u", ",", $text);        
	    $text = preg_replace("/[`‛′’‘]/u", "'", $text);
	    $text = preg_replace("/[″“”«»„]/u", '"', $text);
	    $text = preg_replace("/[—–―−–‾⌐─↔→←]/u", '-', $text);
	    $text = preg_replace("/[  ]/u", ' ', $text);

	    $text = str_replace("…", "...", $text);
	    $text = str_replace("≠", "!=", $text);
	    $text = str_replace("≤", "<=", $text);
	    $text = str_replace("≥", ">=", $text);
	    $text = preg_replace("/[‗≈≡]/u", "=", $text);


	    // Exciting combinations    
	    $text = str_replace("ыЫ", "bl", $text);
	    $text = str_replace("℅", "c/o", $text);
	    $text = str_replace("₧", "Pts", $text);
	    $text = str_replace("™", "tm", $text);
	    $text = str_replace("№", "No", $text);        
	    $text = str_replace("Ч", "4", $text);                
	    $text = str_replace("‰", "%", $text);
	    $text = preg_replace("/[∙•]/u", "*", $text);
	    $text = str_replace("‹", "<", $text);
	    $text = str_replace("›", ">", $text);
	    $text = str_replace("‼", "!!", $text);
	    $text = str_replace("⁄", "/", $text);
	    $text = str_replace("∕", "/", $text);
	    $text = str_replace("⅞", "7/8", $text);
	    $text = str_replace("⅝", "5/8", $text);
	    $text = str_replace("⅜", "3/8", $text);
	    $text = str_replace("⅛", "1/8", $text);        
	    $text = preg_replace("/[‰]/u", "%", $text);
	    $text = preg_replace("/[Љљ]/u", "Ab", $text);
	    $text = preg_replace("/[Юю]/u", "IO", $text);
	    $text = preg_replace("/[ﬁﬂ]/u", "fi", $text);
	    $text = preg_replace("/[зЗ]/u", "3", $text); 
	    $text = str_replace("£", "(pounds)", $text);
	    $text = str_replace("₤", "(lira)", $text);
	    $text = preg_replace("/[‰]/u", "%", $text);
	    $text = preg_replace("/[↨↕↓↑│]/u", "|", $text);
	    $text = preg_replace("/[∞∩∫⌂⌠⌡]/u", "", $text);


	    //2) Translation CP1252.
	    $trans = get_html_translation_table(HTML_ENTITIES);
	    $trans['f'] = '&fnof;';    // Latin Small Letter F With Hook
	    $trans['-'] = array(
	        '&hellip;',     // Horizontal Ellipsis
	        '&tilde;',      // Small Tilde
	        '&ndash;'       // Dash
	        );
	    $trans["+"] = '&dagger;';    // Dagger
	    $trans['#'] = '&Dagger;';    // Double Dagger         
	    $trans['M'] = '&permil;';    // Per Mille Sign
	    $trans['S'] = '&Scaron;';    // Latin Capital Letter S With Caron        
	    $trans['OE'] = '&OElig;';    // Latin Capital Ligature OE
	    $trans["'"] = array(
	        '&lsquo;',  // Left Single Quotation Mark
	        '&rsquo;',  // Right Single Quotation Mark
	        '&rsaquo;', // Single Right-Pointing Angle Quotation Mark
	        '&sbquo;',  // Single Low-9 Quotation Mark
	        '&circ;',   // Modifier Letter Circumflex Accent
	        '&lsaquo;'  // Single Left-Pointing Angle Quotation Mark
	        );

	    $trans['"'] = array(
	        '&ldquo;',  // Left Double Quotation Mark
	        '&rdquo;',  // Right Double Quotation Mark
	        '&bdquo;',  // Double Low-9 Quotation Mark
	        );

	    $trans['*'] = '&bull;';    // Bullet
	    $trans['n'] = '&ndash;';    // En Dash
	    $trans['m'] = '&mdash;';    // Em Dash        
	    $trans['tm'] = '&trade;';    // Trade Mark Sign
	    $trans['s'] = '&scaron;';    // Latin Small Letter S With Caron
	    $trans['oe'] = '&oelig;';    // Latin Small Ligature OE
	    $trans['Y'] = '&Yuml;';    // Latin Capital Letter Y With Diaeresis
	    $trans['euro'] = '&euro;';    // euro currency symbol
	    ksort($trans);

	    foreach ($trans as $k => $v) {
	        $text = str_replace($v, $k, $text);
	    }

	    // 3) remove <p>, <br/> ...
	    $text = strip_tags($text);

	    // 4) &amp; => & &quot; => '
	    $text = html_entity_decode($text);


	    // transliterate
	    // if (function_exists('iconv')) {
	    // $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
	    // }

	    // remove non ascii characters
	    // $text =  preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $text);      

	    return $text;
	}


	public static function dateIsWithinYearRange($startYear, $endYear, $targetYear) {

		if($startYear <= $targetYear && $targetYear <= $endYear) {
			return true;
		}
		else {
			return false;
		}

	}


	// $periodSplitString = " to "
	public static function genericXpathParser($html, $keyXpath, $valueXpath, $periodSplitString, $keyArray = []) {

		$values = [];
		$defaultKeyArray = [
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

		if($keyArray == []) {
			$keyArray = $defaultKeyArray;
		}

		$cleanKeys = [];
		foreach($keyArray as $key => $label) {
			$cleanKeys[$key] = Helpers::cleanLabelText($label);
		}

		$labelToKey = array_flip($cleanKeys);

		$xs = Selector::loadHTML($html);

		// Extracts the keys (from the <th> tags) in order
		$keyNodes = $xs->findAll($keyXpath)->map(function ($node, $index) {
			return (string)$node;
		});

		// Extracts the values (from the <td> tags) in hopefully the same order:
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
			$split = explode($periodSplitString, $values['contractPeriodRange']);
			$values['contractPeriodStart'] = trim($split[0]);
			$values['contractPeriodEnd'] = trim($split[1]);
		}

		var_dump($values);
		return $values;

	}

	// Just in case there are any changes we want to make to all contract HTML files before they're run through the parser script:
	public static function initialSourceTransform($html, $acronym) {
		
		// Since <br>'s don't seem to be used in any of the actual table structures, this way we can avoid text being stuck together when tags are stripped from text content:
		$html = str_replace(['<br>'], [' '], $html);

		return $html;
	}

}