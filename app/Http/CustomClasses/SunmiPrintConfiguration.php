<?php
/**
 * Created by PhpStorm.
 * User: sudhanshuigi
 * Date: 27/03/19
 * Time: 1:12 PM
 */

namespace App\Http\CustomClasses;


class SunmiPrintConfiguration {

    private static $noOfWordBySize= [];
    private static $fontSizeBySize= [];

    function __construct($params) {
        
        if(isset($params['model_no']) && ( $params['model_no'] == 'T1-G' || $params['model_no'] == 'T2' ) ){ // This model has print width 80 mm
            
            self::$noOfWordBySize = [  
                '20' => 44 , 
                '22' => 44 , 
                '24' => 44 , 
                '27' => 44 , 
                '29' => 44 , 
                '30' => 44 , 
                '36' => 44 
                ] ;

            self::$fontSizeBySize = [  
                '20' => 24 , 
                '22' => 24 , 
                '24' => 24 , 
                '27' => 24 , 
                '29' => 24 , 
                '30' => 24 , 
                '36' => 24 
                ] ;
        }else{// This model has print width 58 mm

            self::$noOfWordBySize = [  
                '20' => 38 , 
                '22' => 34 , 
                '24' => 32 , 
                '27' => 29 , 
                '29' => 29 , 
                '30' => 25 , 
                '36' => 21 
                ] ;

            self::$fontSizeBySize = [  
                '20' => 20 , 
                '22' => 22 , 
                '24' => 24 , 
                '27' => 27 , 
                '29' => 27 , 
                '30' => 30 , 
                '36' => 36 
                ] ;
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
        //dd(self::$fontSizeBySize);
        return self::$fontSizeBySize[$fontSize];
    }

    static function getDefaultSize() {
        return 24;
    }

    static function endLines() {
        return 2;
    }
}