<?php

class Stock_Csv {
    function __construct () {
    }

    static function get_data ($csv_url, $contains_header) {
        $csv_file = fopen($csv_url, 'r');

        if ($csv_file) {
            $rows = [];

            if ($contains_header) {
                $h = fgetcsv($csv_file);
            }

            while(!feof($csv_file)){
                $row = fgetcsv ($csv_file);
                
                array_push($rows, $row);
            }

            fclose($csv_file);

            return $rows;
        }
    }
}