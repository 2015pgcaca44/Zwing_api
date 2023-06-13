<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VendorSettingController;
use Illuminate\Http\Request;
use App\Http\Traits\VendorFactoryTrait;
use DB;

use App\Cart;

class BillingOperationController extends Controller
{

    use VendorFactoryTrait;

    public function __construct()
	{
		//$this->middleware('auth');
	}

	public function getItem(Request $request){
		
        return $this->callMethod($request, __CLASS__, __METHOD__ );

	}

    public function saveItem(Request $request){
        
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    }

    public function saveOtherDetails(Request $request){
        
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    }

    public function printReceipt(Request $request){
        
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    }

}