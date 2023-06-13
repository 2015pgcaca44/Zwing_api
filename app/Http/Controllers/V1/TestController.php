<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use DB;
use App\Cart;
use App\TempCart;
use App\VendorSetting;

class TestController extends Controller
{

	public function phpinfo(Request $request){
		//phpinfo();
		//
		$carts = TempCart::where('store_id', '5')->where('v_id', '4')->where('order_id', '2')->where('user_id', '27')->get();

		foreach($carts as $cart){
			$tempCartId = $cart->cart_id;
            unset($cart->cart_id);
            //dd($cart->toArray());
            
            $cartId = Cart::insertGetId($cart->toArray());
            //dd($cartId);
            $tempCartDetails = DB::table('temp_cart_details')
            //$text = " $cartId as cart_id";
            ->select(DB::raw(" $cartId as cart_id"),'barcode','qty','mrp','price','discount','ext_price','tax','taxes','message','ru_prdv','type','type_id','promo_id','is_promo')
            ->where('cart_id',$tempCartId)->get();
            
            $tempCartOffers = DB::table('temp_cart_offers')
            ->select(DB::raw(" $cartId as cart_id"),'item_id','mrp','qty','offers')
            ->where('cart_id',$tempCartId)->get();

            foreach($tempCartDetails as $details){
            	DB::table('cart_details')->insert((array)$details);
            }

            foreach($tempCartOffers as $details){
            	DB::table('cart_offers')->insert((array)$details);
            }
            //dd($tempCartDetails->toArray());
           
            DB::table('temp_cart')->where('cart_id', $tempCartId)->delete();
            DB::table('temp_cart_details')->where('cart_id',  $tempCartId)->delete();
            DB::table('temp_cart_offers')->where('cart_id',  $tempCartId)->delete();
        }

	}

	public function verify_order_for_test(Request $request){

	
		DB::table('orders')->where('order_id', $request->order_id)->update(['verify_status' => $request->verify_status,  'verify_status_guard' => $request->verify_status_guard ]);
		return response()->json(['status' => 'success', 'message' => 'Data updated successfully'],200);
	}

	public function get_vendor_settings(Request $request){
		$v_id = $request->v_id;
		$name = $request->setting_name;

		$settings = VendorSetting::select('name','settings')->where('v_id',$v_id)->where('name',$name)->first();
		$settings = json_decode($settings->settings);

		return response()->json(['status' => 'success' , 'data' => $settings],200);


	}

	public function update_vendor_settings(Request $request){
		$v_id = $request->v_id;
		$name = $request->setting_name;
		$settings_data = json_decode($request->settings);

		//dd(json_decode($request->settings));

		$settings = VendorSetting::where('v_id',$v_id)->where('name',$name)->first();
		$settings->settings = json_encode($settings_data);
		$settings->save();


		return response()->json(['status' => 'success' , 'data' => "Data updated successfully"],200);

	}

}