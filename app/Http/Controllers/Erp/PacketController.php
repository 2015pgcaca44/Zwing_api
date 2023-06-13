<?php

namespace App\Http\Controllers\Erp;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Traits\ErpFactoryTrait;
use App\Organisation;
use App\Store;

class PacketController extends Controller
{
    
    use ErpFactoryTrait;
	public function __construct()
    {

    }

    public function packetPush($params){
    
    	return $this->callMethod($params, __CLASS__, __METHOD__);
	}

    public function packetvoid($params){
        return $this->callMethod($params, __CLASS__, __METHOD__);   
    }

}
