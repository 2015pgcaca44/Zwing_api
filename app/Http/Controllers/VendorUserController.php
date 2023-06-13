<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Vendor;
use App\VendorUserAuth;
use App\Order;
use App\Cart;

use DB;



class VendorUserController extends Controller
{

	public function getUser(Request $request)
	{

		$v_id = $request->v_id;
		$store_id = $request->store_id;

		$id_alias = 'vu_id';
		if ($request->has('user_type') && $request->user_type == 'salesman') {
			$id_alias = 'salesman_id';
		}
		$vendorUser = Vendor::join('vendor_role_user_mapping', 'vendor_auth.id', '=', 'vendor_role_user_mapping.user_id')
			->join('vendor_roles', 'vendor_roles.id', '=', 'vendor_role_user_mapping.role_id')
			->select('vendor_auth.id as ' . $id_alias . '', 'vendor_auth.first_name', 'vendor_auth.last_name', 'vendor_auth.employee_code', 'vendor_auth.vendor_user_random')
			->where('vendor_roles.code', 'sales_man')
			->where('vendor_auth.store_id', $store_id)
			->where('vendor_auth.v_id', $v_id)
			->where('vendor_auth.status', '1');


		// $vendorUser = VendorUserAuth::select('vu_id as ' . $id_alias . '', 'first_name', 'last_name', 'employee_code', 'vendor_user_random')->where('vendor_id', $v_id)->where('store_id', $store_id)->where('status', '1');
		// if ($request->has('user_type')) {
		// 	$vendorUser = $vendorUser->where('type', $request->user_type);
		// }

		if ($request->has('salesman_id')) {
			$vendorUser = $vendorUser->where('vendor_auth.id', $request->salesman_id);
		}


		$vendorUser = $vendorUser->groupBy('vendor_auth.id')->get();

		return $vendorUser;
	}


	public function getSalesMan(Request $request)
	{

		$param = ['user_type' => 'salesman'];
		if ($request->has('salesman_id')) {
			$param = array_merge($param, ['salesman_id' => $request->salesman_id]);
		}

		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$c_id = $request->c_id;

		$request->request->add($param);
		//dd($request->all());
		$vendorUser =  $this->getUser($request);
		$vendorUser = $vendorUser->toArray();

		// $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
		$order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
		$order_id = $order_id + 1;

		$cart = DB::table('cart')->select(DB::raw('count("salesman_id") as count, salesman_id'))->where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status', 'process')->groupBy('salesman_id')->get();

		$salesmans = $cart->pluck('count', 'salesman_id')->all();

		$newUser = [];
		foreach ($vendorUser as $key => $user) {
			$salesmans_count = (isset($salesmans[$user['salesman_id']])) ? $salesmans[$user['salesman_id']] : 0;
			$newUser[] = array_merge($user, ['is_assign' => '0', 'count' => (string) $salesmans_count]);
		}

		$response = ['status' => 'success', 'data' => $newUser];
		if ($request->has('response') && $request->response == 'ARRAY') {
			return $response;
		} else {
			return response()->json($response, 200);
		}
	}

	public function tagSalesMan(Request $request)
	{
		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$c_id = $request->c_id;

		$salesman_id = $request->salesman_id;
		// $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
		$order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
		$order_id = $order_id + 1;

		$remoColl = [];

		$cart = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id);

		if ($request->has('all') && $request->all == 1) {
			$cart = $cart->where('order_id', $order_id);
		} else {


			/*if($request->has('barcode')){
				$barcode = json_decode($request->barcode);
				if(is_array($barcode)){
					$coll = collect($barcode)->pluck('barcode')->all();
					$cart = $cart->whereIn('barcode', $coll);
				}else{
					$cart = $cart->where('barcode', $request->barcode);	
				}
				
			}*/

			if ($request->has('cart_id')) {

				$cartId = json_decode($request->cart_id,true);
				if (is_array($cartId)) {
					$cartId = $cartId[0]['cart_id'];
				}else{
					$cartId = $request->cart_id ;
				}


			 
				//This condition is added for excluding exchange item from salesman tagging
				$item = cart::select('qty')->where('cart_id', $cartId)->first();


				if(!$item || $item->qty < 0){//Need to change this condition
					return response()->json(['status' => 'fail', 'message' => 'Unable to tag salesman for this item'], 200);
				}

				$cart_id = json_decode($request->cart_id);
				if (is_array($cart_id)) {
					$coll = collect($cart_id);
					$addColl = $coll->where('check', '1')->pluck('cart_id')->all();
					$remoColl = $coll->where('check', '0')->pluck('cart_id')->all();
					$cart = $cart->whereIn('cart_id', $addColl);
				} else {
					$cart = $cart->where('cart_id', $request->cart_id);
					if ($request->has('check') && $request->check == 0) {
						$cart = $cart->where('cart_id', 0); //Cart should not get updated here
						$remoColl = [$request->cart_id];
					} else {
						$cart = $cart->where('cart_id', $request->cart_id);
					}
				}
			}
		}

		//This condition is added for excluding exchange item from salesman tagging neet to change qty condition
		$cart = $cart->where('qty', '>', 0);

		$cart->update(['salesman_id' => $salesman_id]);
		if (count($remoColl) > 0) {
			Cart::whereIn('cart_id', $remoColl)->update(['salesman_id' => '']);
		}

		$request->request->add(['response' => 'ARRAY', 'user_type' => 'salesman']);
		$allRequest = $request->all();
		unset($allRequest['salesman_id']);
		$myRequest = new \Illuminate\Http\Request();
		$myRequest->replace($allRequest);

		$res = $this->getSalesMan($myRequest);

		return response()->json(['status' => 'success', 'message' => 'Data has been updated successfully', 'salesman' => $res['data']], 200);
	}

	public function untagSalesMan(Request $request)
	{
		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$c_id = $request->c_id;

		// $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
		$order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
		$order_id = $order_id + 1;

		$cart = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id);

		if ($request->has('salesman_id') && $request->salesman_id > 0) {
			$cart = $cart->where('salesman_id', $request->salesman_id);
		}

		$cart->update(['salesman_id' => '']);


		$request->request->add(['response' => 'ARRAY', 'user_type' => 'salesman']);
		$allRequest = $request->all();
		unset($allRequest['salesman_id']);
		$myRequest = new \Illuminate\Http\Request();
		$myRequest->replace($allRequest);

		$res = $this->getSalesMan($myRequest);

		return response()->json(['status' => 'success', 'message' => 'Untag successfully', 'salesman' => $res['data']], 200);
	}
}
