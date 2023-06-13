<?php
/**
 * Created by PhpStorm.
 * User: sudhanshuigi
 * Date: 27/03/19
 * Time: 1:12 PM
 */

namespace App\Http\CustomClasses;


class ThermalPrintConfiguration {


    private static $noOfWordBySize= [];
    private static $fontSizeBySize= [];
    private static $endLines= [];


    function __construct($params) {

    

        self::$noOfWordBySize = [  
            '20' => 32 , 
            '22' => 32 , 
            '24' => 32 , 
            '27' => 32 , 
            '29' => 32 , 
            '30' => 32 , 
            '32' => 32, 
            '36' => 32, 
            '48' => 32, 
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