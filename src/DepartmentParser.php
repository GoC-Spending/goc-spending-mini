<?php

namespace GoCSpending;

class DepartmentParser {

    private $configuration;
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

    function __construct($configuration, $acronym) {

        $this->configuration = $configuration;
        $this->acronym = $acronym;

    }

    public static function getSourceDirectory($configuration, $acronym = false) {

        if($acronym) {
            return $configuration['rawHtmlFolder']. '/' . $acronym;
        }
        else {
            return $configuration['rawHtmlFolder'];
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

    public function parseDepartment($configuration) {

        $sourceDirectory = self::getSourceDirectory($configuration, $this->acronym);

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
            if($configuration['limitFiles'] && $filesParsed >= $configuration['limitFiles']) {
                break;
            }

            // Retrieve the values from the department-specific file parser
            // And merge these with the default values
            // Just to guarantee that all the array keys are around:
            $fileValues = array_merge(self::$rowParams, $this->parseFile($configuration, $file));

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

    public function parseFile($configuration, $filename) {

        $acronym = $this->acronym;

        if ( ! class_exists('GoCSpending\\DepartmentHandlers\\' . ucfirst($acronym) . 'Handler' ) ) {
            echo 'Cannot find matching DepartmentHandler for ' . $acronym . "; skipping parsing it.\n";
            return false;
        }

        $source = file_get_contents(self::getSourceDirectory($configuration, $this->acronym) . '/' . $filename);

        $source = Helpers::initialSourceTransform($source, $acronym);

        return call_user_func( array( 'GoCSpending\\DepartmentHandlers\\' . ucfirst($acronym) . 'Handler', 'parse' ), $source );

    }

    public static function getDepartments($configuration) {

        $output = [];
        $sourceDirectory = self::getSourceDirectory($configuration);


        $departments = array_diff(scandir($sourceDirectory), ['..', '.']);

        // Make sure that these are really directories
        // This could probably done with some more elegant array map function
        foreach($departments as $department) {
            if(is_dir($sourceDirectory . $department)) {
                $output[] = $department;
            }
        }

        return $output;

    }

    public static function parseAllDepartments($configuration) {

        // Run the operation!
        $startTime = microtime(true);

        // Question of the day is... how big can PHP arrays get?
        $output = [];

        $departments = DepartmentParser::getDepartments($configuration);

        $departmentsParsed = 0;
        foreach($departments as $acronym) {

            if(in_array($acronym, $configuration['departmentsToSkip'])) {
                echo "Skipping " . $acronym . "\n";
                continue;
            }

            if($configuration['limitDepartments'] && $departmentsParsed >= $configuration['limitDepartments']) {
                break;
            }

            $startDate = date('Y-m-d H:i:s');
            echo "Starting " . $acronym . " at ". $startDate . " \n";

            $department = new DepartmentParser($configuration, $acronym);

            $department->parseDepartment($configuration);

            // Rather than storing the whole works in memory,
            // let's just save one department at a time in individual
            // JSON files:

            $directoryPath = $configuration['jsonOutputFolder'] . $acronym;

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
