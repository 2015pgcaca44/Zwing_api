<?php
/**
 * Created by PhpStorm.
 * User: Chandramani
 * Date: 27/03/19
 * Time: 1:12 PM
 */

namespace App\Http\CustomClasses;


class PinelabPrintConfiguration {


    private static $noOfWordBySize= [];
    private static $fontSizeBySize= [];

    function __construct($params) {
        self::$noOfWordBySize = [  
            '20' => 40 , 
            '22' => 40 , 
            '24' => 32 , 
            '27' => 32 , 
            '29' => 32 , 
            '30' => 32 , 
            '32' => 32, 
            '36' => 24, 
            '48' => 24, 
            '40' => 24, 
            ] ;

        self::$fontSizeBySize = [  
            '20' => 40 , 
            '22' => 40 , 
            '24' => 32 , 
            '27' => 32 , 
            '29' => 32 , 
            '30' => 32 , 
            '32' => 32, 
            '36' => 24, 
            '48' => 24, 
            '40' => 24, 
            ] ;

    }

    //Pinelab     Font  no of char
    //            24 -  24
    //            40 -  40
    //            48 -  48

                    
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

    //          Font    Manufacturere Font
    //            20 - 40
    //            22 - 40
    //            24 - 32
    //            27 - - 32
    //            30 - 32
    //            36 - 32
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