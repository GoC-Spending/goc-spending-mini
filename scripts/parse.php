<?php
// A simple script to retrieve all proactive disclosure contract pages
// from the PWGSC website, and store them in an "output" folder.
// Depending on your folder permissions, you may have to create the
// "output" folder as a subdirectory before running this script.
// Estimated runtime is at least an hour on a home internet connection.

// This script retrieves the contracts downloaded by contract-scraper.php
// And parses the data values contained in their HTML tables.
// A future update should merge these together, to do both operations in one pass.

// toobs2017@gmail.com and the GoC-Spending team!

// Note that the vendor directory is one level up
require_once dirname(__FILE__) . '/../vendor/autoload.php';

// Go crazy!
ini_set('memory_limit', '3200M');

$configuration = [
    'rawHtmlFolder' => dirname(__FILE__) . '/contracts/',

    'jsonOutputFolder' => dirname(__FILE__) . '/generated-data/',

    'departmentsToSkip' => [
        'agr',
        'csa',
        'fin',
        'ic',
        'infra',
        'pwgsc',
        'sc',
        'tbs',
        'acoa',
        'pch',
//        'dnd',
        'cic',
        'ec',
        'esdc',
        'hc',
    ],

    'limitDepartments' => 0,
    'limitFiles' => 2,
];

GoCSpending\DepartmentParser::parseAllDepartments($configuration);
