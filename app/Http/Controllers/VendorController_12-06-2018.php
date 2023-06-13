<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Vendor;
use DB;
use Illuminate\Support\Facades\Hash;
use Auth;
use App\Order;
use App\Cart;

class VendorController extends Controller
{
    public function register_mobile(Request $request)
    {
        // SMS API
        $username = "roxfortgroup@gmail.com";
        $hash = "bb7a4a8a91634f50df712fdf5c11ef6745ea735b85afc9443d23ec9ae3e8b711";
        $test = "0";
        $sender = "MZWING";

        $mobile = $request->mobile;
        $check_mobile_exists = Vendor::where('mobile', $mobile)->count();
        if(!empty($check_mobile_exists)) {
            $check_mobile_active = Vendor::where('mobile', $mobile)->where('mobile_active', 0)->count();
            if(!empty($check_mobile_active)) {
                $otp = rand(1111,9999);
                $vendor_otp_update = Vendor::where('mobile', $mobile)->where('mobile_active', 0)->first();
                $vendor_otp_update->otp = $otp;
                $vendor_otp_update->save();
                $numbers = "91".$mobile; 
                $message = "Welcome to ZWING your otp is ".$otp;
                $message = urlencode($message);
                $data = "username=".$username."&hash=".$hash."&message=".$message."&sender=".$sender."&numbers=".$numbers."&test=".$test;
                $ch = curl_init('http://api.textlocal.in/send/?');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $result = curl_exec($ch); 
                curl_close($ch);
				
				$vendor['vu_id'] = $vendor_otp_update->vu_id;
                $vendor['mobile'] = $vendor_otp_update->mobile;
				
                return response()->json(['status' => 'otp_sent', 'message' => 'OTP Send Successfully', 'data' => $vendor ], 200);
            } else {
            		$account_active = Vendor::where('mobile', $mobile)->where('status', 1)->first();

			        if(!$account_active) {
			        	return response()->json(['status' => 'account_not_active', 'message' => 'Your account is not activated'], 200);
			        }
                    $vendor_api_token = Vendor::select('vu_id','mobile')->where('mobile', $mobile)->where('mobile_active', 1)->first();
                    $vendor_api_token->api_token = str_random(50);
                    $vendor_api_token->save();
                    $id = $vendor_api_token->vu_id;
                    return response()->json(['status' => 'login_redirect', 'message' => 'Login Successfully', 'data' => $vendor_api_token], 200);
            }
            return response()->json(['status' => 'mobile_already', 'message' => 'Mobile Already Exists'], 200);
        }
        
        $otp = rand(1111,9999);
        $vendor = new Vendor;
        $vendor->mobile = $mobile;
        $vendor->otp = $otp;
        $vendor->save();
        // dd($vendor->vu_id);
        $response['vu_id'] = $vendor->vu_id;
        $response['mobile'] = $vendor->mobile;
        $numbers = "91".$mobile; 
        $message = "Welcome to ZWING your otp is ".$otp;
        $message = urlencode($message);
        $data = "username=".$username."&hash=".$hash."&message=".$message."&sender=".$sender."&numbers=".$numbers."&test=".$test;
        $ch = curl_init('http://api.textlocal.in/send/?');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch); 
        curl_close($ch);
		
		
		
        return response()->json(['status' => 'otp_sent', 'message' => 'OTP Send Successfully', 'data' => $response ], 200);
    }

    public function verify_mobile(Request $request)
    {
        $otp = $request->otp;
        $mobile = $request->mobile;
        $vu_id = $request->vu_id;

        $opt_verify = Vendor::where('vu_id', $vu_id)->where('mobile', $mobile)->where('otp', $otp)->count();

        if(empty($opt_verify)) {
            return response()->json(['status' => 'incorrect_otp', 'message' => 'Incorrect OTP, Please Check or Resend'], 200);
        }
        $opt_verify = Vendor::where('vu_id', $vu_id)->where('mobile', $mobile)->where('otp', $otp)->update(['mobile_active' => 1]);
        return response()->json(['status' => 'otp_verified', 'message' => 'OTP Verified Successfully', 'mobile' => $mobile, 'vu_id' => $vu_id], 200);
    }

    public function setup_pin(Request $request)
    {
        $vu_id = $request->vu_id;
        $mobile = $request->mobile;
        $pin = $request->pin;
        $pin_update = Vendor::where('vu_id', $vu_id)->where('mobile', $mobile)->update(['password' => app('hash')->make($pin)]);
        return response()->json(['status' => 'success', 'message' => 'PIN Set Successfully', 'mobile' => $mobile, 'vu_id' => $vu_id], 200);
    }
	
	
	public function get_store_details(Request $request)
    {

        $vu_id = $request->vu_id;
        $mobile = $request->mobile;
        $vendor_id = $request->vendor_id;
        $store_id = $request->store_id;

        $vendor_check = DB::table('vendor_auth')->where('id', $vendor_id)->count();
        $store_check = DB::table('stores')->where('store_id', $store_id)->where('v_id', $vendor_id)->first();

        if(empty($vendor_check)) {
            return response()->json([ 'status' => 'vendor_not_found', 'message' => 'Vendor ID Not Found' ], 404);
        } else if(empty($store_check)) {
            return response()->json([ 'status' => 'vendor_store_mismatch', 'message' => 'Vendor ID / Strore ID Mismatch' ], 404);
        }

        return response()->json([ 'status' => 'verify_vendor', 'message' => 'Vendor ID / Strore ID Match', 'mobile'  => $mobile, 'vu_id' => $vu_id, 'store_name' => $store_check->name, 'store_icon' => $store_check->store_icon, 'store_logo' => $store_check->store_logo, 'store_location' => $store_check->location, 'store_logo_link' => store_logo_link() ], 200);
    }


    public function verify_vendor(Request $request)
    {
    	$vu_id = $request->vu_id;
    	$mobile = $request->mobile;
    	$vendor_id = $request->vendor_id;
    	$store_id = $request->store_id;

    	$vendor_check = DB::table('vendor_auth')->where('id', $vendor_id)->count();
    	$store_check = DB::table('stores')->where('store_id', $store_id)->where('v_id', $vendor_id)->first();

    	if(empty($vendor_check)) {
    		return response()->json([ 'status' => 'vendor_not_found', 'message' => 'Vendor ID Not Found' ], 404);
    	} else if(empty($store_check)) {
    		return response()->json([ 'status' => 'vendor_store_mismatch', 'message' => 'Vendor ID / Strore ID Mismatch' ], 404);
    	}

    	Vendor::where('vu_id', $vu_id)->where('mobile', $mobile)->update(['vendor_id' => $vendor_id, 'store_id' => $store_id ]);

    	return response()->json([ 'status' => 'vendor_store_match', 'message' => 'Vendor ID / Strore ID Match', 'mobile'  => $mobile, 'vu_id' => $vu_id, 'store_name' => $store_check->name, 'store_icon' => $store_check->store_icon, 'store_logo' => $store_check->store_logo, 'store_location' => $store_check->location, 'store_logo_link' => store_logo_link() ], 200);
    }

    public function register_vendor_details(Request $request)
    {
        $vu_id = $request->vu_id;
        $mobile = $request->mobile;
        $first_name = $request->first_name;
        $last_name = $request->last_name;
        $gender = $request->gender;
        $dob = date('Y-m-d', strtotime($request->dob));
        $email = $request->email;
        $device_name = $request->device_name;
        $os_name = $request->os_name;
        $os_version = $request->os_version;
        $udid = $request->udid;
        $imei = $request->imei;
        $type = $request->type;
        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $device_model_number = $request->device_model_number;
        $check_email_exists = Vendor::where('vu_id', $vu_id)->where('mobile', $mobile)->where('email', $email)->count();
        if(!empty($check_email_exists)) {
            return response()->json(['status' => 'email_already', 'message' => 'Email Already Exits'], 404);
        }
        $vendor_details = Vendor::where('vu_id', $vu_id)->where('mobile', $mobile)->update(['first_name' => $first_name, 'last_name' => $last_name, 'gender' => $gender, 'dob' => $dob, 'email' => $email, 'device_name' => $device_name, 'os_name' => $os_name, 'os_version' => $os_version, 'udid' => $udid, 'imei' => $imei, 'latitude' => $latitude, 'longitude' => $longitude, 'device_model_number' => $device_model_number]);
        return response()->json(['status' => 'registed', 'message' => 'Registed Successfully', 'mobile' => $mobile, 'vu_id' => $vu_id], 200);
    }

    public function login(Request $request)
    {
        $mobile = $request->mobile;
        $pin = $request->pin;

        $vendor = Vendor::select('vu_id','mobile','api_token','password')->where('mobile', $mobile)->first();

        if(!$vendor) {
            return response()->json(['status' => 'm_not_found', 'message' => 'Mobile not Found'], 404);
        }

        $account_active = Vendor::where('mobile', $mobile)->where('status', 1)->first();

        if(!$account_active) {
        	return response()->json(['status' => 'account_not_active', 'message' => 'Your account is not activated'], 200);
        }

        if(Hash::check($pin, $vendor->password)) {
        	$api_token = str_random(50);
            $vendor->update(['api_token' => $api_token]);
            return response()->json(['status' => 'login_redirect', 'message' => 'Login successfully', 'mobile' => $mobile, 'api_token' => $api_token, 'vu_id' => $vendor->vu_id], 200);
        }

        return response()->json(['status' => 'invalid_credentials', 'message' => 'Invalid Credentials'], 200);
    }

    public function logout(Request $request)
    {
        $api_token = $request->api_token;
        $vu_id = $request->vu_id;
        $mobile = $request->mobile;
        $vendor = Vendor::where('api_token', $api_token)->where('vu_id',$vu_id)->where('mobile',$mobile)->first();

        if(!$vendor) {
            return response()->json(['status' => 'not_logged_in', 'message' => 'Not Logged in'], 200);
        }
        $vendor->api_token = null;

        $vendor->save();

        return response()->json(['status' => 'logout_redirect', 'message' => 'Logged Out Successfully'], 200);
    }

    public function forgot_pin(Request $request)
    {
        // SMS API
        $username = "roxfortgroup@gmail.com";
        $hash = "bb7a4a8a91634f50df712fdf5c11ef6745ea735b85afc9443d23ec9ae3e8b711";
        $test = "0";
        $sender = "MZWING";

        $mobile = $request->mobile;
        $check_mobile_exists = Vendor::where('mobile', $mobile)->where('mobile_active', 1)->count();

        if(empty($check_mobile_exists)) {
            return response()->json([ 'status' => 'mobile_not_found', 'message' => 'Mobile Number Not Found' ], 200);
        }

        $otp = rand(1111,9999);
        $vendor_otp_update = Vendor::where('mobile', $mobile)->where('mobile_active', 1)->update(['otp' => $otp]);
        $vendor = Vendor::where('mobile', $mobile)->where('mobile_active', 1)->first();
		//dd($vendor);
        $numbers = "91".$mobile; 
        $message = "Welcome to ZWING your otp is ".$otp;
        $message = urlencode($message);
        $data = "username=".$username."&hash=".$hash."&message=".$message."&sender=".$sender."&numbers=".$numbers."&test=".$test;
        $ch = curl_init('http://api.textlocal.in/send/?');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch); 
        curl_close($ch);
        //$api_null = Vendor::where('mobile', $mobile)->update(['api_token' => null,'status' => 0]);
        return response()->json(['status' => 'otp_sent', 'message' => 'OTP Send Successfully', 'mobile' => $mobile, 'vu_id' => $vendor->vu_id], 200);
    }

    public function forgot_pin_verify(Request $request)
    {
        $vu_id = $request->vu_id;
        $mobile = $request->mobile;
        $otp = $request->otp;

        $opt_verify = Vendor::where('vu_id', $vu_id)->where('mobile', $mobile)->where('otp', $otp)->count();

        if(empty($opt_verify)) {
            return response()->json(['status' => 'incorrect_otp', 'message' => 'Incorrect OTP, Please Check or Resend'], 200);
        }else{
	
		}

        return response()->json(['status' => 'otp_verified', 'message' => 'OTP Verified Successfully', 'mobile' => $mobile, 'vu_id' => $vu_id], 200);
    }

    public function change_pin(Request $request)
    {
        $vu_id = $request->vu_id;
        $mobile = $request->mobile;
        $pin = $request->pin;

        $change_pin = Vendor::where('mobile', $mobile)->where('vu_id', $vu_id)->where('mobile_active', 1)->update(['password' => app('hash')->make($pin)]);
        return response()->json(['status' => 'pin_change', 'message' => 'Pin Change Successfully', 'mobile' => $mobile, 'vu_id' => $vu_id ], 200);
    }

    public function profile(Request $request)
    {
        $vu_id = $request->vu_id;
        $mobile = $request->mobile;

        $vendor = Vendor::select('mobile','first_name','last_name','gender','dob','email','email_active')->where('vu_id', $vu_id)->where('mobile', $mobile)->first();

        return response()->json(['status' => 'profile_data', 'message' => 'Profile Data', 'data' => $vendor],200);
    }

   public function order_details(Request $request)
    {
		$vendor = Auth::user();
		//dd($vendor);
		
        $v_id = $vendor->vendor_id;
        //$c_id = $request->c_id;
        $store_id = $vendor->store_id; 
        $order_id = $request->order_id; 

        $stores =  DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();
        $o_id = Order::where('order_id', $order_id)->where('v_id', $v_id)->where('store_id', $store_id)->first();
		if(!$o_id){
			return response()->json(['status' => 'fail', 'message' => 'Unable to get the orders'],200);
		}
		
		//dd($o_id);
		
        $order_num_id = Order::where('order_id', $order_id)->first();
		$c_id = $o_id->user_id;

        $cart_data = array();
        $product_data = [];
        $tax_total = 0;
        $cart_qty_total =  0;
        
        $carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $o_id->o_id)->get();
        $sub_total = $carts->sum('amount');
        $discount  = $carts->sum('discount');
        $total     = $carts->sum('total');
        $tax_total = $carts->sum('tax');
        $bill_buster_discount = 0;
        $tax_details = [];

        foreach ($carts as $key => $cart) {


            $res = DB::table('cart_offers')->where('cart_id',$cart->cart_id)->first();
            $offer_data = json_decode($res->offers, true);
            
            foreach ($offer_data['pdata'] as $key => $value) {
                foreach($value['tax'] as $nkey => $tax){
                    if(isset($tax_details[$tax['tax_code']])){
                        $tax_details[$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
                        $tax_details[$tax['tax_code']]['tax'] += $tax['tax'] ;
                    }else{
                        $tax_details[$tax['tax_code']] = $tax;
                    }
                    
                }
                
            }

            $available_offer = [];
            foreach($offer_data['available_offer'] as $key => $value){

                $available_offer[] =  ['message' => $value ];
            }
            $offer_data['available_offer'] = $available_offer;
            $applied_offer = [];
            foreach($offer_data['applied_offer'] as $key => $value){

                $applied_offer[] =  ['message' => $value ];
            }
            $offer_data['applied_offer'] = $applied_offer;
            //dd($offer_data);

            //Counting the duplicate offers
            $tempOffers = $offer_data['applied_offer'];
            for($i=0; $i<count($offer_data['applied_offer']); $i++){
                $apply_times = 1 ;
                $apply_key = 0;
                for($j=$i+1; $j<count($tempOffers); $j++){
                    
                    if(isset($offer_data['applied_offer'][$j]['message']) && $tempOffers[$i]['message'] == $offer_data['applied_offer'][$j]['message']){
                        unset($offer_data['applied_offer'][$j]);
                        $apply_times++;
                        $apply_key = $j;
                    }

                }
                if($apply_times > 1 ){
                    $offer_data['applied_offer'][$i]['message'] = $offer_data['applied_offer'][$i]['message'].' - ' .$apply_times.' times';
                }

            }
            $offer_data['available_offer'] = array_values($offer_data['available_offer']);
            $offer_data['applied_offer'] = array_values($offer_data['applied_offer']);

            $carr_bag_arr =  [ '114903443', '114952448' ,'114974444'];
            $carry_bag_flag = in_array($cart->item_id, $carr_bag_arr);

            $product_data['carry_bag_flag'] = $carry_bag_flag;
            $product_data['p_id'] = (int)$cart->item_id;
            $product_data['category'] = '';
            $product_data['brand_name'] = '';
            $product_data['sub_categroy'] = '';
            $product_data['whishlist'] = 'No';
            $product_data['weight_flag'] = ($cart->weight_flag == 1)?true:false;
            $product_data['p_name'] = $cart->item_name;
            $product_data['offer'] = (count($offer_data['applied_offer']) > 0)?'Yes':'No';
            $product_data['offer_data'] = [ 'applied_offers' => $offer_data['applied_offer'] , 'available_offers' =>$offer_data['available_offer']  ];
            //$product_data['qty'] = '';
            $product_data['multiple_price_flag'] = $offer_data['multiple_price_flag'];
            $product_data['multiple_mrp'] = $offer_data['multiple_mrp'];
            $product_data['r_price'] = format_number($offer_data['r_price']);
            $product_data['s_price'] = format_number($offer_data['s_price']);
            $product_data['unit_mrp'] = format_number($cart->unit_mrp);
            /*if(!empty($offer_data['applied_offers']) ){
                $product_data['r_price'] = format_number($offer_data['r_price']);
                $product_data['s_price'] = format_number($offer_data['s_price']);
            }*/

            $product_data['varient'] = '';
            $product_data['images'] = '';
            $product_data['description'] = '';
            $product_data['deparment'] = '';
            $product_data['barcode'] = $cart->barcode;

            //$tax_total = $tax_total +  $tax_amount ;
            $tax_amount = $cart->tax;
            $cart_qty_total =  $cart_qty_total + $cart->qty;
           
            $cart_data[] = array(
                    'cart_id'       => $cart->cart_id,
                    'product_data'  => $product_data,
                    'amount'        => $cart->total ,
                    'qty'           => $cart->qty,
                    'tax_amount'    => $tax_amount,
                    'delivery'      => $cart->delivery
            );
            //$tax_total = $tax_total +  $tax_amount ;
        }

        $bill_buster_discount = $o_id->bill_buster_discount;
        $saving = $discount + $bill_buster_discount;

        $bags = DB::table('user_carry_bags')->select('vendor_carry_bags.Name','user_carry_bags.Qty','vendor_carry_bags.BAG_ID')->selectRaw('user_carry_bags.Qty * vendor_carry_bags.Price as Price')->leftJoin('vendor_carry_bags', 'user_carry_bags.Bag_ID', '=', 'vendor_carry_bags.BAG_ID')->where('user_carry_bags.V_ID', $v_id)->where('user_carry_bags.Store_ID', $store_id)->where('user_carry_bags.User_ID', $c_id)->where('user_carry_bags.Order_ID', $order_num_id['o_id'])->get();
        $bprice = DB::table('user_carry_bags')->selectRaw('SUM(user_carry_bags.Qty * vendor_carry_bags.Price) as Price')->leftJoin('vendor_carry_bags', 'user_carry_bags.Bag_ID', '=', 'vendor_carry_bags.BAG_ID')->where('user_carry_bags.V_ID', $v_id)->where('user_carry_bags.Store_ID', $store_id)->where('user_carry_bags.User_ID', $c_id)->where('user_carry_bags.Order_ID', $order_num_id['o_id'])->first();
        // $cart_data['bags'] = $bags;
        
        if(empty($bprice->Price)) {
            $carry_bag_total = 0;
        } else {
            $carry_bag_total = $bprice->Price;
        }
        $store = DB::table('stores')->select('delivery')->where('store_id', $store_id)->where('v_id', $v_id)->first();
        //$total = (int)$sub_total + (int)$carry_bag_total;
        //$less = array_sum($saving) - (int)$sub_total;
        $address = (object)array();
        if($o_id->address_id > 0){
            $address = Address::where('c_id', $c_id)->where('deleted_status', 0)->where('id',$o_id->address_id)->first();
        }

        // return response()->json(['status' => 'order_details', 'message' => 'Order Details Details', 'data' => $cart_data, 'product_image_link' => product_image_link(), 'sub_total' => $sub_total, 'tax_total' => $tax_total, 'grand_total' => $sub_total + $carry_bag_total + $tax_total, 'date' => $o_id->date, 'time' => $o_id->time, 'bags' => $bags, 'carry_bag_total' => $carry_bag_total, 'delivered' => $store->delivery , 'address'=> $address  ],200);
        return response()->json(['status' => 'order_details', 'message' => 'Order Details Details', 
            'data' => $cart_data, 'product_image_link' => product_image_link(),
            //'offer_data' => $global_offer_data,
            'bags' => $bags, 
            'carry_bag_total' => (format_number($carry_bag_total))?format_number($carry_bag_total):'0.00',
            'sub_total' => (format_number($sub_total))?format_number($sub_total):'0.00', 
            'tax_total' => (format_number($tax_total))?format_number($tax_total):'0.00',
            'tax_details' => $tax_details,
            'bill_buster_discount' => (format_number($bill_buster_discount))?format_number($bill_buster_discount):'0.00',
            'discount' => (format_number($discount))?format_number($discount):'0.00', 
            //'grand_total' => (format_number($grand_total))?format_number($grand_total):'0.00', 
            'date' => $o_id->date, 
            'time' => $o_id->time,
            'order_id' => $order_id, 
            'total' => format_number($total), 
            'cart_qty_total' => (string)$cart_qty_total,
            'saving' => (format_number($saving))?format_number($saving):'0.00',
            'store_address' => $stores->address1.' '.$stores->address2.' '.$stores->state.' - '.$stores->pincode,
            'store_timings' => $stores->opening_time.' '.$stores->closing_time,
            'delivered' => $store->delivery , 
            'address'=> $address,
            'verify_status' => $o_id->verify_status ],200);
    }
	
	public function verify_order(Request $request){

        $vendor = Auth::user();

        $vu_id = $request->vu_id ;
        $v_id = $vendor->vendor_id ;
        $store_id = $vendor->store_id ;
        $order_id = $request->order_id ;
        $order  = Order::where('order_id' , $order_id)
                        ->where('v_id' , $v_id)
                        ->where('store_id' , $store_id)->first();
        if($order){
            $order->verify_status = '1';
            $order->verified_by = $vu_id;
            $order->save();
            //$order  = $order->update(['verify_status' => '1' , 'verified_by' => $vu_id]);
			
			 return  $this->order_details($request);
            // return response()->json(['status' => 'order_verified', 'message' => 'Order has been verified' ],200);

        }else{

             return response()->json(['status' => 'fail', 'message' => 'Unable to verified this order' ],200);
        }
                        
    }


}
