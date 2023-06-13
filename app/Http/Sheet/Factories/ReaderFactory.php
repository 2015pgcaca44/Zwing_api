<?php 
namespace App\Http\Sheet\Factories;
use App\Http\Sheet\CsvReader;

class ReaderFactory {

	public static function make($import, $file, string $readerType = null)
    {
    	$reader = null;
    	$readerType = $file->getType();
    	if($readerType == 'CSV'){
    		
    		$reader =  new CsvReader;
    
    	}

    	return $reader;

    }
}