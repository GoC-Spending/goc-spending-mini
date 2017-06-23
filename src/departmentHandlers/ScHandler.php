<?php


namespace GoCSpending\DepartmentHandlers;

use GoCSpending\Interfaces\DepartmentHandler;
use GoCSpending\Helpers;

class ScHandler implements DepartmentHandler {
    private $acronym = 'sc';

    public static function scrape() {

    }

    public static function parse($html) {

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

}
