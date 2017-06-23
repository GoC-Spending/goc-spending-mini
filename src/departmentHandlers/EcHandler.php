<?php


namespace GoCSpending\DepartmentHandlers;

use GoCSpending\Interfaces\DepartmentHandler;
use GoCSpending\Helpers;

class EcHandler implements DepartmentHandler {
    private $acronym = 'ec';

    public static function scrape() {

    }

    public static function parse($html) {

        return Helpers::genericXpathParser($html, "//table//td[@scope='row']", "//table//td[@class='alignTopLeft']", ' to ');

    }

}