<?php

namespace App\Http\Controllers\Search;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SearchController
{
	private $config;
	
	public function __construct()
    {

		$this->config = config('search');
	}

	public function getConfig(){
        return $this->config;
    }


}