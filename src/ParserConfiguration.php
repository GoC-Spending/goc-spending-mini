<?php

namespace GoCSpending;

class ParserConfiguration {

    public static $rawHtmlFolder = 'contracts';

    public static $jsonOutputFolder = 'generated-data';

    public static $departmentsToSkip = [
//		'agr',
        'csa',
        'fin',
        'ic',
        'infra',
        'pwgsc',
        'sc',
        'tbs',
        'acoa',
        // 'pch',
        'dnd',
    ];

    public static $limitDepartments = 0;
    public static $limitFiles = 2;

}
