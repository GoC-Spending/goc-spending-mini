<?php

namespace GoCSpending;

class DepartmentParser {

    public $acronym;
    public $contracts;

    public static $rowParams = [
        'uuid' => '',
        'vendorName' => '',
        'referenceNumber' => '',
        'contractDate' => '',
        'description' => '',
        'contractPeriodStart' => '',
        'contractPeriodEnd' => '',
        'startYear' => '',
        'endYear' => '',
        'deliveryDate' => '',
        'originalValue' => '',
        'contractValue' => '',
        'comments' => '',
        'ownerAcronym' => '',
        'sourceYear' => '',
        'sourceQuarter' => '',
        'sourceFilename' => '',
        'sourceURL' => '',
        'amendedValues' => [],
    ];

    function __construct($acronym) {

        $this->acronym = $acronym;

    }

    public static function getSourceDirectory($acronym = false) {

        if($acronym) {
            return dirname(__FILE__) . '/' . ParserConfiguration::$rawHtmlFolder . '/' . $acronym;
        }
        else {
            return dirname(__FILE__) . '/' . ParserConfiguration::$rawHtmlFolder;
        }

    }



    public static function cleanParsedArray(&$values) {

        $values['startYear'] = Helpers::yearFromDate($values['contractPeriodStart']);
        $values['endYear'] = Helpers::yearFromDate($values['contractPeriodEnd']);

        $values['originalValue'] = Helpers::cleanupContractValue($values['originalValue']);
        $values['contractValue'] = Helpers::cleanupContractValue($values['contractValue']);

        if(! $values['contractValue']) {
            $values['contractValue'] = $values['originalValue'];
        }

        // Check for error-y non-unicode characters
        $values['referenceNumber'] = Helpers::cleanText($values['referenceNumber']);
        $values['vendorName'] = Helpers::cleanText($values['vendorName']);
        $values['comments'] = Helpers::cleanText($values['comments']);
        $values['description'] = Helpers::cleanText($values['description']);


    }

    public function parseDepartment() {

        $sourceDirectory = self::getSourceDirectory($this->acronym);

        $validFiles = [];
        $files = array_diff(scandir($sourceDirectory), ['..', '.']);

        foreach($files as $file) {
            // Check if it ends with .html
            $suffix = '.html';
            if(substr_compare( $file, $suffix, -strlen( $suffix )) === 0) {
                $validFiles[] = $file;
            }
        }

        $filesParsed = 0;
        foreach($validFiles as $file) {
            if(ParserConfiguration::$limitFiles && $filesParsed >= ParserConfiguration::$limitFiles) {
                break;
            }

            // Retrieve the values from the department-specific file parser
            // And merge these with the default values
            // Just to guarantee that all the array keys are around:
            $fileValues = array_merge(self::$rowParams, $this->parseFile($file));

            if($fileValues) {

                self::cleanParsedArray($fileValues);
                // var_dump($fileValues);

                $fileValues['ownerAcronym'] = $this->acronym;

                // Useful for troubleshooting:
                $fileValues['sourceFilename'] = $this->acronym . '/' . $file;

                // A lot of DND's entries are missing reference numbers:
                if(! $fileValues['referenceNumber']) {
                    echo "Warning: no reference number.\n";
                    $filehash = explode('.', $file)[0];

                    $fileValues['referenceNumber'] = $filehash;

                }

                // TODO - update this to match the schema discussed at 2017-03-28's Civic Tech!
                $fileValues['uuid'] = $this->acronym . '-' . $fileValues['referenceNumber'];

                $referenceNumber = $fileValues['referenceNumber'];

                // If the row already exists, update it
                // Otherwise, add it
                if(isset($this->contracts[$referenceNumber])) {
                    echo "Updating $referenceNumber\n";

                    // Because we don't have a year/quarter for all organizations, let's use the largest contractValue for now:
                    $existingContract = $this->contracts[$referenceNumber];
                    if($fileValues['contractValue'] > $existingContract['contractValue']) {
                        $this->contracts[$referenceNumber] = $fileValues;
                    }

                    // Add entries to the amendedValues array
                    // If it's the first time, add the original too
                    if($existingContract['amendedValues']) {
                        $this->contracts[$referenceNumber]['amendedValues'] = array_merge($existingContract['amendedValues'], [$fileValues['contractValue']]);
                    }
                    else {
                        $this->contracts[$referenceNumber]['amendedValues'] = [
                            $existingContract['contractValue'],
                            $fileValues['contractValue'],
                        ];
                    }

                } else {
                    // Add to the contracts array:
                    $this->contracts[$referenceNumber] = $fileValues;
                }

            }
            else {
                echo "Error: could not parse data for $file\n";
            }



            $filesParsed++;

        }
        // var_dump($validFiles);

    }

    public function parseFile($filename) {

        $acronym = $this->acronym;

        $handlerExists = class_exists('GoCSpending\\DepartmentHandlers\\' . ucfirst($acronym) . 'Handler' );
        $fileParserExists = method_exists('FileParser', $acronym);

        if ( ! $handlerExists ) {
            echo 'Cannot find matching DepartmentHandler for ' . $acronym . "\n";
        }

        if ( ! $fileParserExists ) {
            echo 'Cannot find matching FileParser for ' . $acronym . "\n";
        }

        $source = file_get_contents(self::getSourceDirectory($this->acronym) . '/' . $filename);

        $source = Helpers::initialSourceTransform($source, $acronym);

        if ( $handlerExists ) {
            return call_user_func( array( 'GoCSpending\\DepartmentHandlers\\' . ucfirst($acronym) . 'Handler', 'parse' ), $source );
        } else {
            return FileParser::$acronym($source);
        }

    }

    public static function getDepartments() {

        $output = [];
        $sourceDirectory = self::getSourceDirectory();


        $departments = array_diff(scandir($sourceDirectory), ['..', '.']);

        // Make sure that these are really directories
        // This could probably done with some more elegant array map function
        foreach($departments as $department) {
            if(is_dir(dirname(__FILE__) . '/' . ParserConfiguration::$rawHtmlFolder . '/' . $department)) {
                $output[] = $department;
            }
        }

        return $output;

    }

    public static function parseAllDepartments() {

        // Run the operation!
        $startTime = microtime(true);

        // Question of the day is... how big can PHP arrays get?
        $output = [];

        $departments = DepartmentParser::getDepartments();

        $departmentsParsed = 0;
        foreach($departments as $acronym) {

            if(in_array($acronym, ParserConfiguration::$departmentsToSkip)) {
                echo "Skipping " . $acronym . "\n";
                continue;
            }

            if(ParserConfiguration::$limitDepartments && $departmentsParsed >= ParserConfiguration::$limitDepartments) {
                break;
            }

            $startDate = date('Y-m-d H:i:s');
            echo "Starting " . $acronym . " at ". $startDate . " \n";

            $department = new DepartmentParser($acronym);

            $department->parseDepartment();

            // Rather than storing the whole works in memory,
            // let's just save one department at a time in individual
            // JSON files:

            $directoryPath = dirname(__FILE__) . '/' . ParserConfiguration::$jsonOutputFolder . '/' . $acronym;

            // If the folder doesn't exist yet, create it:
            // Thanks to http://stackoverflow.com/a/15075269/756641
            if(! is_dir($directoryPath)) {
                mkdir($directoryPath, 0755, true);
            }


            // Iterative check of json_encode
            // Trying to catch encoding issues
            if(file_put_contents($directoryPath . '/contracts.json', json_encode($department->contracts, JSON_PRETTY_PRINT))) {
                echo "...saved.\n";
            }
            else {
                // echo "STARTHERE: \n";
                // var_export($departmentArray);

                // echo "ENDHERE. \n";
                echo "...failed.\n";

                $newOutput = [];

                $index = 0;
                $limit = 1000000;

                foreach($department->contracts as $key => $data) {
                    $index++;
                    if($index > $limit) {
                        break;
                    }
                    $newOutput[$key] = $data;

                    echo $index;

                    if(json_encode($data, JSON_PRETTY_PRINT)) {
                        echo " P\n";
                    }
                    else {
                        echo " F\n";
                        var_dump($key);
                        var_dump($data);
                        exit();
                    }

                }

            }


            // var_dump($department->contracts);
            // var_dump(json_encode($department->contracts, JSON_PRETTY_PRINT));
            // $output[$acronym] = $department->contracts;

            echo "Started " . $acronym . " at " . $startDate . "\n";
            echo "Finished at ". date('Y-m-d H:i:s') . " \n\n";

            $departmentsParsed++;

        }

    }


}
