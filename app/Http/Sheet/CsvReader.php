<?php 
namespace App\Http\Sheet;

class CsvReader {

	/**
     * Input encoding.
     *
     * @var string
     */
    private $inputEncoding = 'UTF-8';

	/**
     * Delimiter.
     *
     * @var string
     */
    private $delimiter = ',';
    
    /**
     * Enclosure.
     *
     * @var string
     */
    private $enclosure = '"';
    
    /**
     * Sheet index to read.
     *
     * @var int
     */
    private $sheetIndex = 0;
    
    /**
     * Load rows contiguously.
     *
     * @var bool
     */
    private $contiguous = false;
    
    /**
     * Row counter for loading rows contiguously.
     *
     * @var int
     */
    private $contiguousRow = -1;
    
    /**
     * The character that can escape the enclosure.
     *
     * @var string
     */
    private $escapeCharacter = '\\';


    protected $fileHandle;
    protected $importer;
    
    protected $hasHeader = false;  //Boolean
    protected $batchSize; // Int
    protected $chunkSize;  // Int


	public function read($importer, $filePath, $readerType, $disk){

	}

	public function processImporter($importer){
		if(method_exists($importer, 'hasHeader') ){
			$this->hasHeader = $importer->hasHeader();
		}

		if(method_exists($importer, 'batchSize') ){
			$this->batchSize = $importer->batchSize();
		}

		if(method_exists($importer, 'chunkSize') ){
			$this->chunkSize = $importer->chunkSize();
		}
	}


	public function load($fileName, $importer){


		// Open file
        if (!$this->canRead($fileName)) {
            throw new Exception($fileName . ' is an Invalid Spreadsheet file.');
        }

        $this->processImporter($importer);
        $this->openFile($fileName);
        $fileHandle = $this->fileHandle;

		// Loop through each line of the file in turn
        $header = [];
        $line =0;
        while (($rowData = fgetcsv($fileHandle, 0, $this->delimiter, $this->enclosure, $this->escapeCharacter)) !== false) {

        	// $rowData = array_map("utf8_encode", $rowData); //added
        	foreach ($rowData as $key => $row) {
        		//Converting to Utf -8
        		$rowData[$key] = mb_convert_encoding($row, "UTF-8");
        	}
            // $columnLetter = 'A';
            // dd($rowData);
            // foreach ($rowData as $rowDatum) {
            //     if ($rowDatum != '' && $this->readFilter->readCell($columnLetter, $currentRow)) {
            //         // Convert encoding if necessary
            //         if ($this->inputEncoding !== 'UTF-8') {
            //             $rowDatum = StringHelper::convertEncoding($rowDatum, 'UTF-8', $this->inputEncoding);
            //         }
            //         // Set cell value
            //         $sheet->getCell($columnLetter . $currentRow)->setValue($rowDatum);
            //     }
            //     ++$columnLetter;
            // }
            // ++$currentRow;
            
            
            if($this->hasHeader){
            	if($line == 0){
	            	$header = $rowData;
	            	$line++;
	            	continue;
	            }
	            $rowData = array_combine($header, $rowData);
            }
	       
            $importer->array($rowData);

            $line++;
        }

        // Close file
        fclose($fileHandle);
	}


	/**
     * Open file for reading.
     *
     * @param string $pFilename
     *
     * @throws Exception
     */
    protected function openFile($pFilename)
    {
        // Open file
        $this->fileHandle = fopen($pFilename, 'r');
        if ($this->fileHandle === false) {
            throw new Exception('Could not open file ' . $pFilename . ' for reading.');
        }
    }



	public function canRead($pFilename)
    {
        // Check if file exists
        try {
            $this->openFile($pFilename);
        } catch (Exception $e) {
            return false;
        }
        fclose($this->fileHandle);
        // Trust file extension if any
        $extension = strtolower(pathinfo($pFilename, PATHINFO_EXTENSION));
        if (in_array($extension, ['csv', 'tsv'])) {
            return true;
        }
        // Attempt to guess mimetype
        $type = mime_content_type($pFilename);
        $supportedTypes = [
            'text/csv',
            'text/plain',
            'inode/x-empty',
        ];
        return in_array($type, $supportedTypes, true);
    }

	public function import($importer, $filePath, $readerType, $disk){

	}


	public function getNextLine($line= ''){

		// Get the next line in the file
        $newLine = fgets($this->fileHandle);
        // Return false if there is no next line
        if ($newLine === false) {
            return false;
        }

        // Add the new line to the line passed in
        $line = $line . $newLine;


        // Drop everything that is enclosed to avoid counting false positives in enclosures
        $enclosure = '(?<!' . preg_quote($this->escapeCharacter, '/') . ')'
            . preg_quote($this->enclosure, '/');
        $line = preg_replace('/(' . $enclosure . '.*' . $enclosure . ')/Us', '', $line);
        // See if we have any enclosures left in the line
        // if we still have an enclosure then we need to read the next line as well
        if (preg_match('/(' . $enclosure . ')/', $line) > 0) {
            $line = $this->getNextLine($line);
        }

        return $line;

	}

	public function setDelimiter($delimiter){
		$this->delimiter = $delimiter;
	}

	public function setEnclosure($enclosure){
		$this->enclosure = $enclosure;
	}

	public function setEscapeCharacter($escapeCharacter){
		$this->escapeCharacter = $escapeCharacter;
	}

	/**
     * Set Contiguous.
     *
     * @param bool $contiguous
     *
     * @return Csv
     */
    public function setContiguous($contiguous)
    {
        $this->contiguous = (bool) $contiguous;
        if (!$contiguous) {
            $this->contiguousRow = -1;
        }
        return $this;
    }

	public function setInputEncoding($inputEncoding){
		$this->inputEncoding = $inputEncoding;
	}


}