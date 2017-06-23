<?php


namespace GoCSpending\DepartmentHandlers;

use GoCSpending\Interfaces\DepartmentHandler;
use GoCSpending\Helpers;

class IcHandler implements DepartmentHandler {
    private $acronym = 'ic';

    public static function scrape() {

    }

    public static function parse($html) {

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

}