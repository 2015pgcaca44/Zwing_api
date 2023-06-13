<?php

namespace App\Http\Controllers\Erp;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Traits\ErpFactoryTrait;
use App\Organisation;
use App\Store;

class DaySettlementController extends Controller
{


   use ErpFactoryTrait;

	public function __construct()
    {

    }    

 public function  settlementPush($params){
    
    	return $this->callMethod($params, __CLASS__, __METHOD__);
	}

}
