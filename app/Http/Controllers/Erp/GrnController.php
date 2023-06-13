<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Http\Traits\ErpFactoryTrait;
use Illuminate\Http\Request;
use App\Organisation;
use App\InvoicePush;
use App\Invoice;
use App\Store;



class GrnController extends Controller
{
	use ErpFactoryTrait;

	public function __construct()
    {

    }

    public function grnPush($params){
    
    	return $this->callMethod($params, __CLASS__, __METHOD__);
	}

	public function grnRetrunPush($params){
		
		return $this->callMethod($params, __CLASS__, __METHOD__);
	}

}