<?php


namespace GoCSpending\DepartmentHandlers;

use GoCSpending\Interfaces\DepartmentHandler;
use GoCSpending\Helpers;

class InfraHandler implements DepartmentHandler {
    private $acronym = 'infra';

    public static function scrape() {

    }

    public static function parse($html) {

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

}