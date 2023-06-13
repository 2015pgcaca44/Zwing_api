<?php
/**
 * Created by PhpStorm.
 * User: sudhanshuigi
 * Date: 27/03/19
 * Time: 1:12 PM
 */

namespace App\Http\CustomClasses;


class BasewinPrintConfiguration {


    private static $noOfWordBySize= [];
    private static $fontSizeBySize= [];
    private static $endLines= [];


    function __construct($params) {

        if(isset($params['model_no']) && $params['model_no'] == 'P1000S'){ // This model has print width 80 mm

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

        }else{

            self::$noOfWordBySize = [ 
                '19' => 44 ,  
                '20' => 38 , 
                '22' => 34 , 
                '24' => 32 , 
                '27' => 29 , 
                  '29' => 32 , 
                '30' => 25 , 
                '32' => 25, 
                '36' => 21, 
                '48' => 21, 
                ] ;

             self::$fontSizeBySize = [
                '19' => 19,  
                '20' => 20 , 
                '22' => 22 , 
                '24' => 24 , 
                '27' => 27 , 
                '29' => 29 , 
                '30' => 30 , 
                '32' => 32, 
                '36' => 36, 
                '48' => 48, 
                ] ;

            self::$endLines = 16;
        }

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