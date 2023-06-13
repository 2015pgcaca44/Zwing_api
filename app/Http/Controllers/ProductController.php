<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Traits\VendorFactoryTrait;
use DB;
use App\Offer;
use App\Wishlist;
use App\Scan;
use Auth;

class ProductController extends Controller
{

    use VendorFactoryTrait;

	public function __construct()
	{
		$this->middleware('auth', ['except' => ['product_search'] ]);
	}

    public function product_details(Request $request)
    {
        return $this->callMethod($request, __CLASS__, __METHOD__ );
 
    }
	
	public function product_search(Request $request){
        
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    }

    public function product_b2b_details(Request $request){
        
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    }

    public function checkOverrideLimit(Request $request){
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    }

    public function create_flat_table($params){
        
        DB::statement('call SkuAssortmentMapping(?,?,?)',array($v_id, $assortment_code, $item_id));
    }

    public function checkProductInventory(Request $request){
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    }

}
