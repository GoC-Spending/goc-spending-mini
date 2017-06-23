<?php


namespace GoCSpending\DepartmentHandlers;

use GoCSpending\Interfaces\DepartmentHandler;
use GoCSpending\Helpers;

class CicHandler implements DepartmentHandler {
    private $acronym = 'cic';

    public static function scrape() {

    }

    public static function parse($html) {

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

}
