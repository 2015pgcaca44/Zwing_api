<?php 
namespace App\Http\Sheet;

use App\Http\Sheet\Factories\ReaderFactory;
use App\Http\Sheet\File;

class Reader {

	/**
     * @var Spreadsheet
     */
    protected $spreadsheet;

	/**
     * @var FileReader
     */
    protected $reader;

    /**
     * @var TemporaryFile
     */
    protected $currentFile;


	public function read($importer, $filePath, $readerType, $disk){
		$this->reader = $this->getReader($importer, $filePath, $readerType, $disk);		
		$this->reader->load(
            $this->currentFile->getLocalPath(), $importer
        );
	}

	public function getReader($importer, $filePath, $readerType, $disk){

		/*$shouldQueue = $importer instanceof ShouldQueue;

		$fileExtension     = pathinfo($filePath, PATHINFO_EXTENSION);
        $temporaryFile     = $shouldQueue ? $this->temporaryFileFactory->make($fileExtension) : $this->temporaryFileFactory->makeLocal(null, $fileExtension);
        $this->currentFile = $temporaryFile->copyFrom(
            $filePath,
            $disk
        );*/

        // dd($filePath);
        $this->currentFile = new File($filePath, $disk);
        // dd($this->currentFile);
		return ReaderFactory::make($importer, $this->currentFile , $readerType);
	}


	/**
     * Garbage collect.
     */
    private function garbageCollect()
    {
        // $this->setDefaultValueBinder();

        // Force garbage collecting
        // unset($this->sheetImports, $this->spreadsheet);

        $this->currentFile->delete();
    }
}