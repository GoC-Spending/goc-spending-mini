<?php

namespace GoCSpending;

use \XPathSelector\Selector;

class FileParser {

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
