<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;

use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\VendorSettingController;
use Illuminate\Http\Request;
use App\Http\Traits\V1\VendorFactoryTrait;
use DB;

use App\Cart;

class TableSyncController extends Controller
{
    use VendorFactoryTrait;

    public function __construct()
	{
		//$this->middleware('auth');
	}

	public function sync(Request $request){
        
        return $this->callMethod($request, __CLASS__, __METHOD__ );

	}

	public function success(Request $request){
        
        return $this->callMethod($request, __CLASS__, __METHOD__ );

	}

}