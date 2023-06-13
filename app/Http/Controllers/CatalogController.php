<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VendorSettingController;
use Illuminate\Http\Request;
use App\Http\Traits\VendorFactoryTrait;
use DB;

use App\Cart;

class CatalogController extends Controller
{

    use VendorFactoryTrait;

    public function __construct()
	{
		//$this->middleware('auth');
	}

	public function getCatalog(Request $request){
		
        return $this->callMethod($request, __CLASS__, __METHOD__ );

	}

    public function saveCatalog(Request $request){
        
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    }

}