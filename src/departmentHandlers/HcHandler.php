<?php


namespace GoCSpending\DepartmentHandlers;

use GoCSpending\Interfaces\DepartmentHandler;
use GoCSpending\Helpers;

class HcHandler implements DepartmentHandler {
    private $acronym = 'hc';

    public static function scrape() {

    }

    public static function parse($html) {

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

}