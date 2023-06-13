<?php

namespace App\Http\Sheet;

class Sheet
{

	const XLSX     = 'Xlsx';
    const CSV      = 'Csv';

    /**
     * @var Writer
     */
    protected $writer;
 
    /**
     * @var Reader
     */
    private $reader;



    public function __construct(){
    	$this->writer = null;
    	$this->reader = new Reader;
    }

    public function import($importer, $filePath, string $disk = null, string $readerType = null){
    	
    	//detecting File type
    	$readerType = (new File)->detectType($filePath, $readerType);
    	$response = $this->reader->read($importer, $filePath, $readerType, $disk);

    	return $response;

    }

    public function export($exporter, $fileName , string $writerType = null){

    	$writerType = (new File)->getType($fileName, $writerType);

    	return $this->writer->export($export, $writerType);
    }


}