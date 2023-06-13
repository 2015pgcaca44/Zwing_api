<?php

namespace App\Http\Controllers\Erp;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Traits\ErpFactoryTrait;
use App\Organisation;
use App\Store;

class StockAduitController extends Controller
{
   

   use ErpFactoryTrait;

	public function __construct()
    {

    } 

    public function StockAduitPush($params){
    
    	return $this->callMethod($params, __CLASS__, __METHOD__);
	}   
}
