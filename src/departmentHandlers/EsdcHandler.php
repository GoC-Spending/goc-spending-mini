<?php


namespace GoCSpending\DepartmentHandlers;

use GoCSpending\Interfaces\DepartmentHandler;
use GoCSpending\Helpers;

class EsdcHandler implements DepartmentHandler {
    private $acronym = 'esdc';

    public static function scrape() {

    }

    public static function parse($html) {

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

}
