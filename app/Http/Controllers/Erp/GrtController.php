<?php

namespace App\Http\Controllers\Erp;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Traits\ErpFactoryTrait;
use App\Organisation;
use App\Store;

class GrtController extends Controller
{
   use ErpFactoryTrait;

	public function __construct()
    {

    }

  	public function grtPush($params){
    
    	return $this->callMethod($params, __CLASS__, __METHOD__);
	}


	public function storeTransferPush($params){
    
    	return $this->callMethod($params, __CLASS__, __METHOD__);
	}

	



}
