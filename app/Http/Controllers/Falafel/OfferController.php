<?php

namespace App\Http\Controllers\Falafel;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use DB;
use App\Cart;
use App\Order;
use Auth;

class OfferController extends Controller
{

	public function __construct(){

    	$this->middleware('auth');

    }

	public function get_offers(Request $request){

		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$c_id = $request->c_id;
		
		$trans_from = 'ANDROID';

		if($request->has('trans_from')){
			$trans_from =  $request->trans_from;
		}

		$store_db_name = get_store_db_name( [ 'store_id' => $store_id ]);
		
		$order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;
		
		$carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status','process')->get();

		$employee_discount = $carts->sum('employee_discount');
		$coupon_voucher = [];
		//$company_discount = DB::table($store_db_name.'.company_discount')->get();
		$company_discount = DB::table($v_id.'_company_discount')->get();
		$company_list = $company_discount->pluck('company_name')->all();
		
		$employee_discount = [ 'type' => 'employee_discount' , 'name' => 'SPAR' , 'title' => 'Employee Discount' , 'desc' => '',  'applied_status' => ($employee_discount >0.00)?true:false , 'company_list' => $company_list ];

		$today_date = date('Y-m-d H:i:s');
		array_push($coupon_voucher, $employee_discount);
		$vouchers = DB::table('voucher')->select('id','voucher_no','amount','type','expired_at')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('status','unused')->where('effective_at' ,'<=' ,$today_date )->where('expired_at','>=' , $today_date)->get();

		$vouchers_applied = DB::table('voucher_applied')->select('voucher_id')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('order_id',$order_id)->get();

		$vouchers_ids = $vouchers_applied->pluck('voucher_id')->all();

		foreach($vouchers as $voucher){
			$expired_at = date('d-M-Y' , strtotime($voucher->expired_at) );
			$store_credit = [ 'type' => 'voucher' , 'name' => 'SPAR' , 'title' => 'store credit' , 'desc' => 'On Time use',  'amount' => $voucher->amount , 'voucher_no' => $voucher->voucher_no , 'voucher_id' => $voucher->id , 'applied_status' => (in_array($voucher->id, $vouchers_ids)?true:false) , 'expired_at' => $expired_at ];

			array_push($coupon_voucher, $store_credit);
		}
		
		/*
		if($trans_from == 'ANDROID_KIOSK'){

			foreach ($coupon_voucher as $key => $value) {
				if(!$value['applied_status']){
					unset($coupon_voucher[$key]);
				}
			}
		}*/
		
		

		$data = [ 'coupon_voucher' => $coupon_voucher ];

		$response = ['status' => 'offers', 'message' => 'Available Offers' , 'data' => $data ];

		if($request->has('response_format') &&  $request->response_format == 'ARRAY'){
			return $response;
		}else{

			return response()->json( $response,200);	
		}


	}


	public function apply_voucher(Request $request){
		
		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$c_id = $request->c_id;

		$type = $request->type;
		
		if($request->has('voucher_code')){
			$voucher_code = $request->voucher_code;
			$voucher = DB::table('voucher')->select('id','voucher_no','amount','type','status','expired_at')->where('voucher_no', $voucher_code)->where('v_id', $v_id)->where('user_id', $c_id)->first();
			if(!$voucher){
				return response()->json(['status' => 'fail' , 'message' => 'Your Entered code is not correct']);
			}else{
				if($voucher->status == 'used'){
					return response()->json(['status' => 'fail' , 'message' => 'You have already used this voucher' ]);	
				}

				if($voucher->expired_at < date('Y-m-d H:i:s') ){
					return response()->json(['status' => 'fail' , 'message' => 'Your voucher has been Expired' ]);	
				}
			}
			$voucher_id = $voucher->id;
		}else{
			$voucher_id = $request->voucher_id;
		}

		if($type == 'voucher'){

			$voucher = DB::table('voucher')->select('id','voucher_no','amount','type')->where('id', $voucher_id)->first();

			if($voucher){

				if($voucher->amount > 0.00){

					$order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
           			$order_id = $order_id + 1;

					DB::table('voucher_applied')->insert(['v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $c_id ,'order_id' => $order_id , 'voucher_id' => $voucher_id]);

					return response()->json(['status' => 'success' , 'message' => 'Voucher Applied succcessfully']);
				}else{

					return response()->json(['status' => 'fail' , 'message' => 'Unable to  Applied voucher']);

				}
			}else{

				return response()->json(['status' => 'fail' , 'message' => 'Unable to  Find voucher']);

			}

		}

	}

	public function remove_voucher(Request $request){
		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$c_id = $request->c_id;

		$voucher_id = $request->voucher_id;


		$order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

		DB::table('voucher_applied')->where('voucher_id',$voucher_id)->where('order_id', $order_id)->where('user_id', $c_id)->delete();

		$request->request->add(['response_format' => 'ARRAY']);
		$offers = $this->get_offers($request);

		return response()->json(['status' => 'success', 'message' => 'Removed Successfully' , 'data' => $offers['data'] ]);

	}
}