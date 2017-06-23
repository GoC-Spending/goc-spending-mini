<?php


namespace GoCSpending\DepartmentHandlers;

use GoCSpending\Interfaces\DepartmentHandler;
use GoCSpending\Helpers;

class RcmpHandler implements DepartmentHandler {
    private $acronym = 'rcmp';

    public static function scrape() {

    }

    public static function parse($html) {

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
