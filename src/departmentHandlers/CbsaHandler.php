<?php


namespace GoCSpending\DepartmentHandlers;

use \XPathSelector\Selector;
use GoCSpending\Interfaces\DepartmentHandler;
use GoCSpending\Helpers;

class CbsaHandler implements DepartmentHandler {
    private $acronym = 'cbsa';

    public static function scrape() {

    }

    public static function parse($html) {

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

}
