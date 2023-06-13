<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VendorSettingController;
use Illuminate\Http\Request;
use App\Http\Traits\VendorFactoryTrait;
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

	public function syncItemList(Request $request)
	{

		return $this->callMethod($request, __CLASS__, __METHOD__ );
	}

	public function getSettings(Request $request)
	{
		return $this->callMethod($request, __CLASS__, __METHOD__ );
	}

	public function switchToOnline(Request $request)
	{
		return $this->callMethod($request, __CLASS__, __METHOD__ );
	}

	public function latestInvoiceId(Request $request)
	{
		return $this->callMethod($request, __CLASS__, __METHOD__ );
	}
}