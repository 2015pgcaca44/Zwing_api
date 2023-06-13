<?php

namespace App\Http\Sheet;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class File
{

	private $ext;
	private $name;
	private $storagePath;
	private $filePath;

	public function __construct($filePath = null, $disk = null){
		$this->ext = $this->getExtension($filePath);
		$this->setLocalPath($filePath);
	}

	public function getExtension($filePath){
		if (!$filePath instanceof UploadedFile) {
            $pathInfo  = pathinfo($filePath);
            $ext = $pathInfo['extension'] ?? '';
        } else if($filePath->getClientOriginalExtension() ) {
            $ext = $filePath->getClientOriginalExtension();
        }
        return $ext;
	}
	
	public function detectType($filePath , $readerType){
		$ext = $this->getExtension($filePath);
		$type = '';

		return $this->getType($ext);
	}

	public function getType($ext =null){
		$extension = null;
		if($ext == null){
			$extension = $this->ext;
		}else{
			$extension = $ext;
		}
		$type = '';

		switch ( strtolower($extension) ) {
		    case "csv":
		        $type = 'CSV';
		        break;
		    case "xls":
		        $type = 'XLS';
		        break;
		    default:
		        $type = '';
		}
		return $type;
	}

	/**
     * @param string|null $fileExtension
     *
     * @return string
     */
    private function generateFilename(string $fileExtension = null): string
    {
        return 'laravel-excel-' . Str::random(32) . ($fileExtension ? '.' . $fileExtension : '');
    }

    public function setName($filePath){
    	$name = time().'-';

    	if (!$filePath instanceof UploadedFile) {
            $pathInfo  = pathinfo($filePath);	
            $this->name = $pathInfo['tmp_name'] ?? '';
        } else if($filePath->getClientOriginalName() ) {
            $this->name = $name. $filePath->getClientOriginalName();
            //.'/'.$filePath->getClientOriginalName();
        }
    }

    public function getName(){
    	return $this->name;
    }

    public function setLocalPath($filePath){
    	if (!$filePath instanceof UploadedFile) {
            $pathInfo  = pathinfo($filePath);	
            $this->localPath = $pathInfo['tmp_name'] ?? '';
        } else if($filePath->getRealPath() ) {
			// ini_set('post_max_size', '4M');
			// ini_set('upload_max_filesize', '10M');
        	$this->setName($filePath);
            // $this->localPath =  $filePath->storeAs('file', $this->name);
            $localPath =  $filePath->move(storage_path('file'), $this->name);
            $this->storagePath = storage_path('file');
            $this->filePath = $localPath->getPathName();
        }
    }

    public function getStoragePath(){
        return $this->storagePath;
    }

    public function getLocalPath($filePath =null){
    	if($filePath != null){
	    	$this->setLocalPath($filePath);
    	}
        return $this->filePath;
    }


}