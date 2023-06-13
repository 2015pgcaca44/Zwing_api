<?php

namespace App\Http\Controllers;
use App\Http\Controllers\VendorSettingController;
use Illuminate\Http\Request;
use App\Http\Traits\VendorFactoryTrait;
use DB;

class OutBoundTableSyncController extends Controller
{
    use VendorFactoryTrait;

    public function __construct()
	{
	
	}

	public function outBoundSync(Request $request){
		
    return $this->callMethod($request, __CLASS__, __METHOD__ );
	} 
}
