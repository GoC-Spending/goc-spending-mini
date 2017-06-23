<?php


namespace GoCSpending\DepartmentHandlers;

use GoCSpending\Interfaces\DepartmentHandler;
use GoCSpending\Helpers;

class PchHandler implements DepartmentHandler {
    private $acronym = 'pch';

    public static function scrape() {

    }

    public static function parse($html) {

        // To easily take care of the
        // Description&nbsp;of&nbsp;Work
        // label for the description key:
        $html = str_replace('&nbsp;', ' ', $html);

        return Helpers::genericXpathParser($html, "//table//th", "//table//td", ' to ');

    }

}
