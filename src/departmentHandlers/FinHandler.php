<?php


namespace GoCSpending\DepartmentHandlers;

use GoCSpending\Interfaces\DepartmentHandler;
use GoCSpending\Helpers;

class FinHandler implements DepartmentHandler {
    private $acronym = 'fin';

    public static function scrape() {

    }

    public static function parse($html) {

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

}
