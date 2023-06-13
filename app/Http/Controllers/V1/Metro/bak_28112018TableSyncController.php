<?php

namespace App\Http\Controllers\V1\Metro;

use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\VendorSettingController;
use Illuminate\Http\Request;
use DB;

use App\Cart;
use App\Order;

class TableSyncController extends Controller
{

    public function __construct()
	{
		//$this->middleware('auth');
	}

	public function sync(Request $request){

		$v_id = $request->v_id;
        $store_id = $request->store_id; 
        //$c_id = $request->c_id;

        $stores =  DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();
        $store_db_name = $stores->store_db_name;

        $price_master = DB::table($store_db_name.'.price_master as pm')
                        ->select('pm.ITEM as barcode', 'pm.ITEM_DESC as name', 'pm.IMAGE as image', 'pm.MRP1 as mrp')
                        ->limit(90000)
                        ->get();

		//$carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status','process')->orderBy('updated_at','desc')->get();

        return response()->json( ['status' => 'success', 'data' => $price_master ] ,200);
	
	}

}