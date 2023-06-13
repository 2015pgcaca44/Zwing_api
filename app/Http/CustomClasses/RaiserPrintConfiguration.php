<?php


namespace App\Http\CustomClasses;


class RaiserPrintConfiguration {


    private static $noOfWordBySize= [];
    private static $fontSizeBySize= [];
    private static $endLines= [];


    function __construct($params) {

    

        self::$noOfWordBySize = [  
            '20' => 48, 
            '22' => 48, 
            '24' => 48, 
            '27' => 48, 
            '29' => 48, 
            '30' => 48, 
            '32' => 48, 
            '36' => 48, 
            '48' => 48, 
            ] ;

        self::$fontSizeBySize = [  
            '20' => 24 , 
            '22' => 24 , 
            '24' => 24 , 
            '27' => 24 , 
            '29' => 24 , 
            '30' => 24 , 
            '32' => 24, 
            '36' => 24, 
            '48' => 24, 
            ] ;

        self::$endLines = 4;

       

    }


    //          Font    Words
    //            20 - 38
    //            22 - 34
    //            24 - 32
    //            27 - - 29
    //            30 - 25
    //            36 - 21
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

        return self::$endLines;
    }
}