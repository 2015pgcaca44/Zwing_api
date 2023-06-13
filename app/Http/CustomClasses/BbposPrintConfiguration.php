<?php

namespace App\Http\CustomClasses;


class BbposPrintConfiguration{

    private static $noOfWordBySize= [];
    private static $fontSizeBySize= [];

    function __construct($params) {
        self::$noOfWordBySize = [  
            '20' => 31, 
            '22' => 31, 
            '24' => 29, 
            '27' => 29, 
            '29' => 29, 
            '30' => 27, 
            '32' => 27, 
            '36' => 27, 
            '48' => 27, 
            ];

        self::$fontSizeBySize = [  
            '20' => 20, 
            '22' => 20, 
            '24' => 22, 
            '27' => 22, 
            '29' => 22, 
            '30' => 24, 
            '32' => 24, 
            '36' => 24, 
            '48' => 24, 
            ] ;

    }

    static function getLength($fontSize) {
        return self::$noOfWordBySize[$fontSize];
    }
    static function getFontSize($fontSize) {
        return self::$fontSizeBySize[$fontSize];
    }

    static function getDefaultSize() {
        return 24;
    }

    static function endLines() {
        return 2;
    }
}