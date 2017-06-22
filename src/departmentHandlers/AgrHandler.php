<?php


namespace GoCSpending\DepartmentHandlers;

use GoCSpending\Interfaces\DepartmentHandler;
use GoCSpending\Helpers;

class AgrHandler implements DepartmentHandler {
    private $acronym = 'agr';

    public static function scrape() {

    }

    public static function parse($html) {

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

}