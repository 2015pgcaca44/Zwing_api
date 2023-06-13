<?php
/**
 * Created by PhpStorm.
 * User: Chandramani
 * Date: 27/03/19
 * Time: 1:12 PM
 */

namespace App\Http\CustomClasses;


class MswipePrintConfiguration {

    private static $noOfWordBySize= [];
    private static $fontSizeBySize= [];

    function __construct($params) {

        self::$noOfWordBySize = [ 
            '19' => 44 ,   
            '20' => 32 , 
            '22' => 32 , 
            '24' => 32 , 
            '27' => 32 , 
            '29' => 32 , 
            '30' => 24 , 
            '32' => 24, 
            '36' => 24, 
            '48' => 16, 
            ] ;

        self::$fontSizeBySize = [ 
            '19' => 19,   
            '20' => 24 , 
            '22' => 24 , 
            '24' => 24 , 
            '27' => 24 , 
            '29' => 24 , 
            '30' => 32 , 
            '32' => 32, 
            '36' => 32, 
            '48' => 48, 
            ] ;

    }

// Mswipe     Font  no of char
//            8 -   --
//            16 -  --
//            24 -  32
//            32 -  24
//            48 -  16


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