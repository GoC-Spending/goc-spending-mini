<?php


namespace GoCSpending\DepartmentHandlers;

use GoCSpending\Interfaces\DepartmentHandler;
use GoCSpending\Helpers;

class InacHandler implements DepartmentHandler {
    private $acronym = 'inac';

    public static function scrape() {

    }

    public static function parse($html) {

        return Helpers::genericXpathParser($html, "//table[@class='widthFull TableBorderBasic']//th", "//table[@class='widthFull TableBorderBasic']//td", ' - ');

    }

}