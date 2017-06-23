<?php


namespace GoCSpending\DepartmentHandlers;

use GoCSpending\Interfaces\DepartmentHandler;
use GoCSpending\Helpers;

class CsaHandler implements DepartmentHandler {
    private $acronym = 'csa';

    public static function scrape() {

    }

    public static function parse($html) {

        // Just get the table in the middle:
        $html = Helpers::stringBetween('DEBUT DU CONTENU', 'FIN DU CONTENU', $html);

        $values = [];

        $keys = [
            'vendorName',
            'referenceNumber',
            'description',
            'deliveryDate',
            'contractValue',
            'comments',
        ];

        $keysWithModifications = [
            'vendorName',
            'referenceNumber',
            'description',
            'deliveryDate',
            'originalValue',
            'modificationValue',
            'contractValue',
            'comments',
        ];

        $matches = [];
        $pattern = '/<td class="align-middle">([\wÀ-ÿ@$#%^&+\*\-.\'(),;:<\/>\s]*)<\/td>/';

        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

        // var_dump($matches);

        if(count($matches) == 8) {
            $keys = $keysWithModifications;
        }

        foreach($matches as $index => $match) {

            $value = $match[1];

            if(array_key_exists($index, $keys)) {

                $value = Helpers::cleanHtmlValue($value);

                $values[$keys[$index]] = $value;

            }

        }

        // Interestingly, the decimal points for CSA are commas rather than periods (probably coded in French originally).
        if(isset($values['originalValue'])) {
            $values['originalValue'] = str_replace([',', ' '], ['.', ''], $values['originalValue']);
        }
        if(isset($values['contractValue'])) {
            $values['contractValue'] = str_replace([',', ' '], ['.', ''], $values['contractValue']);
        }
        if(isset($values['modificationValue'])) {
            $values['modificationValue'] = str_replace([',', ' ', '$'], ['.', '', ''], $values['modificationValue']);
        }




        // If there isn't an originalValue, use the contractValue
        if(! (isset($values['originalValue']) && $values['originalValue'])) {
            $values['originalValue'] = $values['contractValue'];
        }

        // Do a separate regular expression to retrieve the time values
        // The first one is the contract date, and the second two are the start and end dates
        // For the contract period (the second two values), these are inexplicably in YYYY-DD-MMM format (eg. 2011-31-003)
        $matches = [];
        $pattern = '/<time datetime="([\w-@$#%^&+.,;:<\/>\s]*)">/';

        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

        // var_dump($matches);

        $values['contractDate'] = $matches[0][1];

        // Fix the date issue while we're at it:
        if(isset($matches[1][1])) {
            $values['contractPeriodStart'] = Helpers::switchMonthsAndDays(str_replace('-00', '-0', $matches[1][1]));
        }
        if(isset($matches[2][1])) {
            $values['contractPeriodEnd'] = Helpers::switchMonthsAndDays(str_replace('-00', '-0', $matches[2][1]));
        }



        // var_dump($values);

        return $values;

    }

}
