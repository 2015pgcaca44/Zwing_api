<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use DB;
use App\Rating;
use App\PopUp;
use App\Order;
use App\PopUpCustomer;
use Auth;
use App\Cart;

class AuthController extends Controller
{
	
	public function send_verification_email(Request $request){
        $c_id = $request->c_id;
       
        $url = env('APP_URL')."/customer/send-verification-email";
        //$headers = array("api-key: $api_key",'Accept: application/json','Content-Type: application/json') ; 
        $data = "c_id=".$c_id;
        $ch = curl_init();
        //curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
		//echo $output; exit;
        if ($output === FALSE) {
          echo "cURL Error: " . curl_error($ch);
        }
        
        $response = (array)(json_decode($output));
		
		//dd($response);

        if($response['status'] == 'success'){
             return response()->json(['status' => 'send_verification_email', 'message' => 'Verification Email Send'], 200);
        }else{
             return response()->json(['status' => 'fail', 'message' => $response['message'] ], 200);
        }


    }

    public function register_mobile(Request $request)
    {
        // SMS API
        $username = "roxfortgroup@gmail.com";
        $hash = "bb7a4a8a91634f50df712fdf5c11ef6745ea735b85afc9443d23ec9ae3e8b711";
        $test = "0";
        $sender = "MZWING";

        $mobile = $request->mobile;
        $check_mobile_exists = User::where('mobile', $mobile)->count();
        if(!empty($check_mobile_exists)) {
            $check_mobile_active = User::where('mobile', $mobile)->where('mobile_active', 0)->count();
            if(!empty($check_mobile_active)) {
                $otp = rand(1111,9999);
                $user_otp_update = User::where('mobile', $mobile)->where('mobile_active', 0)->first();
                $user_otp_update->otp = $otp;
                $user_otp_update->save();
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
                return response()->json(['status' => 'otp_sent', 'message' => 'OTP Send Successfully', 'mobile' => $mobile, 'c_id' => $user_otp_update->c_id], 200);
            } else {
                    $account_active = User::where('mobile', $mobile)->where('status', 1)->first();
                    if(!$account_active) {
                        return response()->json(['status' => 'account_not_active', 'message' => 'Your account is not activated'], 404);
                    }
                    $user_api_token = User::select('c_id','mobile','first_name','email')->where('mobile', $mobile)->where('mobile_active', 1)->first();
                    $user_api_token->api_token = str_random(50);
                    $user_api_token->save();
                    $id = $user_api_token->c_id;
                    return response()->json(['status' => 'login_redirect', 'message' => 'Login Successfully', 'data' => $user_api_token], 200);
            }
            return response()->json(['status' => 'mobile_already', 'message' => 'Mobile Already Exists'], 401);
        }
        
        $otp = rand(1111,9999);
        $user = User::create([ 'mobile' => $mobile, 'otp' => $otp ]);
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
        // return response()->json(['status' => 'success', 'message' => $user,'sms' => json_decode($result, true)], 200);
        return response()->json(['status' => 'otp_sent', 'message' => 'OTP Send Successfully', 'mobile' => $mobile, 'c_id' => $user->c_id], 200);
    }

    public function verify_mobile(Request $request)
    {
        $otp = $request->otp;
        $mobile = $request->mobile;
        $c_id = $request->c_id;

        $opt_verify = User::where('c_id', $c_id)->where('mobile', $mobile)->where('otp', $otp)->count();

        if(empty($opt_verify)) {
            return response()->json(['status' => 'incorrect_otp', 'message' => 'Incorrect OTP, Please Check or Resend'], 200);
        }
        $opt_verify = User::where('c_id', $c_id)->where('mobile', $mobile)->where('otp', $otp)->update(['mobile_active' => 1, 'status' => 1]);
        return response()->json(['status' => 'otp_verified', 'message' => 'OTP Verified Successfully', 'mobile' => $mobile, 'c_id' => $c_id], 200);
    }

    public function setup_pin(Request $request)
    {
        $c_id = $request->c_id;
        $mobile = $request->mobile;
        $pin = $request->pin;
        $pin_update = User::where('c_id', $c_id)->where('mobile', $mobile)->update(['password' => app('hash')->make($pin)]);
        return response()->json(['status' => 'success', 'message' => 'PIN Set Successfully', 'mobile' => $mobile, 'c_id' => $c_id], 200);
     }

    public function register_user_details(Request $request)
    {
        $c_id = $request->c_id;
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
        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $device_model_number = $request->device_model_number;
        $api_token = str_random(50);
        $check_email_exists = User::where('c_id', $c_id)->where('mobile', $mobile)->where('email', $email)->count();
        if(!empty($check_email_exists)) {
            return response()->json(['status' => 'email_already', 'message' => 'Email Already Exits'], 404);
        }
        $user_details = User::where('c_id', $c_id)->where('mobile', $mobile)->update(['first_name' => $first_name, 'last_name' => $last_name, 'gender' => $gender, 'dob' => $dob, 'email' => $email, 'device_name' => $device_name, 'os_name' => $os_name, 'os_version' => $os_version, 'udid' => $udid, 'imei' => $imei, 'latitude' => $latitude, 'longitude' => $longitude, 'device_model_number' => $device_model_number, 'api_token' => $api_token ]);
        return response()->json(['status' => 'registed', 'message' => 'Registed Successfully', 'mobile' => $mobile, 'c_id' => $c_id, 'api_token' => $api_token], 200);
    }

    public function login(Request $request)
    {
        $mobile = $request->mobile;
        $pin = $request->pin;

        $user = User::select('c_id','mobile','api_token','password','first_name','email','gender')->where('mobile', $mobile)->first();

        if(!$user) {
            return response()->json(['status' => 'm_not_found', 'message' => 'Mobile not Found'], 404);
        }

        if(Hash::check($pin, $user->password)) {
			
			if($request->has('latitude') && $request->has('longitude')){
                $latitude = $request->latitude;
                $longitude = $request->longitude;
                $customer =  new CustomerController;
                $customer->log(['latitude' =>  $latitude, 'longitude' => $longitude, 'c_id' => $user->c_id ]);
            }

            $order = Order::select('order_id', 'v_id' , 'store_id','date','time','total','verify_status','verify_status_guard')->where('user_id',$user->c_id)->where('status','success')->where('transaction_type','sales')->orderBy('od_id','desc')->first();

            if($order){

                $vendorS = new VendorSettingController;
                $settings = $vendorS->getSetting($order->v_id , 'color');
                if($settings){
                    
                    $settings = $settings->first()->settings;
                    $colorSettings = json_decode($settings);

                     $colorSettings;
                }else{

                    $colorSettings = json_decode('{"color_top":{"r":6,"g":80,"b":133,"hex":"#065085"},"color_bottom":{"r":28,"g":116,"b":180, "hex":"#1C74B4"}}');

                }
            }else{

                $colorSettings = json_decode('{"color_top":{"r":6,"g":80,"b":133,"hex":"#065085"},"color_bottom":{"r":28,"g":116,"b":180, "hex":"#1C74B4"}}');

            }

            if($order){
                    $verification_data = [
                'cashier_verify_status' => ($order->verify_status == '1')?true:false ,
             'guard_verify_status' => ($order->verify_status_guard == '1')?true:false,
              'order_id' => $order->order_id,
             'amount' => $order->total,
             'v_id' => $order->v_id,
             'store_id'=> $order->store_id,
             'date' => $order->date,
             'time' => $order->time,
             'color' => $colorSettings ];
            }else{

                $verification_data = [
             'cashier_verify_status' => true,
             'guard_verify_status' => true,
             'order_id' => '' ,
             'amount' => '',
             'v_id' => 0,
             'store_id' => 0,
             'date' => '',
             'time' =>'',
             'color' => (object)[]
              ];
            }

			
            $user->update(['api_token' => str_random(50)]);
            return response()->json(['status' => 'login_redirect', 'message' => 'Login Successfully', 'data' => $user ,'verification' => $verification_data ], 200);
        }

        return response()->json(['status' => 'invalid_credentials', 'message' => 'Invalid Credentials'], 401);
    }

    public function logout(Request $request)
    {
        $api_token = $request->api_token;
        $c_id = $request->c_id;
        $mobile = $request->mobile;
        $user = User::where('api_token', $api_token)->where('c_id',$c_id)->where('mobile',$mobile)->first();

        if(!$user) {
            return response()->json(['status' => 'not_logged_in', 'message' => 'Not Logged in'], 401);
        }
        $user->api_token = null;

        $user->save();

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
        $check_mobile_exists = User::where('mobile', $mobile)->where('mobile_active', 1)->count();

        if(empty($check_mobile_exists)) {
            return response()->json([ 'status' => 'mobile_not_found', 'message' => 'Mobile Number Not Found' ], 401);
        }

        $otp = rand(1111,9999);
        $user_otp_update = User::where('mobile', $mobile)->where('mobile_active', 1)->update(['otp' => $otp]);
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
        $api_null = User::where('mobile', $mobile)->update(['api_token' => null]);
        return response()->json(['status' => 'otp_sent', 'message' => 'OTP Send Successfully', 'mobile' => $mobile, 'c_id' => $user_otp_update], 200);
    }

    public function forgot_pin_verify(Request $request)
    {
        $c_id = $request->c_id;
        $mobile = $request->mobile;
        $otp = $request->otp;

        $opt_verify = User::where('c_id', $c_id)->where('mobile', $mobile)->where('otp', $otp)->count();

        if(empty($opt_verify)) {
            return response()->json(['status' => 'incorrect_otp', 'message' => 'Incorrect OTP, Please Check or Resend'], 200);
        }

        return response()->json(['status' => 'otp_verified', 'message' => 'OTP Verified Successfully', 'mobile' => $mobile, 'c_id' => $c_id], 200);
    }

    public function change_pin(Request $request)
    {
        $c_id = $request->c_id;
        $mobile = $request->mobile;
        $pin = $request->pin;

        $change_pin = User::where('mobile', $mobile)->where('c_id', $c_id)->where('mobile_active', 1)->update(['password' => app('hash')->make($pin)]);
        $api = str_random(50);
        $api_token = User::where('mobile', $mobile)->where('c_id', $c_id)->where('mobile_active', 1)->update(['api_token' => $api]);
        return response()->json(['status' => 'pin_change', 'message' => 'Pin Change Successfully', 'mobile' => $mobile, 'c_id' => $c_id, 'api_token' => $api ], 200);
    }

    public function qr_store_details(Request $request)
    {
        $qr_code = base64_decode($request->qr_code);
        $store = DB::table('stores')->where('store_random', $qr_code)->where('api_status', 1)->first();
		
		$v_id = $store->v_id;
        $store_id = $store->store_id;
		$c_id = Auth::user()->c_id;
        //$store = DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();
        $data = array();
        $opening_time = date('h A', strtotime($store->opening_time));
        $closing_time = date('h A', strtotime($store->closing_time));
        date_default_timezone_set("Asia/Kolkata");
        $nowDate = date("Y-m-d h:i:sa");
        $start = date('H:i:s', strtotime($store->opening_time));
        $end   = date('H:i:s', strtotime($store->closing_time));
        $time = date("H:i:s");
        $flag = isWithInTime($start, $end, $time);
        $rating_exits = Rating::where('Store_ID', $store->store_id)->where('V_ID', $store->v_id)->where('User_ID', $c_id)->count();
        if (empty($rating_exits)) {
            $rating = 'No';
        } else {
            $rating = 'Yes';
        }
        $mgPath = store_logo_link().$store->store_logo ;
	    $store_logo_flag = false;
	    if (@getimagesize($mgPath)) {
	        $store_logo_flag = true;
	    }
		
		
		$vendorS = new VendorSettingController;
		$settings = $vendorS->getSetting($store->v_id , 'color');
		if($settings){
			
			$settings = $settings->first()->settings;
			$colorSettings = json_decode($settings);

			 $colorSettings;
		}else{

			$colorSettings = json_decode('{"color_top":{"r":6,"g":80,"b":133,"hex":"#065085"},"color_bottom":{"r":28,"g":116,"b":180, "hex":"#1C74B4"}}');

		}
		
		
		$vendorS = new VendorSettingController;
        $settings = $vendorS->getSetting($store->v_id , 'others');
        if($settings){
            $settings = $settings->first()->settings;
            $otherSettings = json_decode($settings);

             $otherSettings;
        }else{

            $otherSettings = json_decode('{"offer_detail_flag":"YES","locate_product_flag":"YES"}');

        }
            
        $data['store_id'] = $store->store_id;
		$data['store_random'] = $store->store_random;
        $data['v_id'] = $store->v_id;
        $data['type'] = $store->type;
        $data['name'] = $store->name;
		$data['email'] = $store->email;
		$data['pincode'] = $store->pincode;
        $data['address1'] = $store->address1;
        $data['address2'] = $store->address2;
		$data['state'] = $store->state;
        $data['city'] = $store->city;
        $data['latitude'] = $store->latitude;
        $data['longitude'] = $store->longitude;
        $data['opening_time'] = $opening_time;
        $data['closing_time'] = $closing_time;
		$data['weekly_off'] = $store->weekly_off;
        $data['description'] = $store->description;
        $data['tagline'] = $store->tagline;
		$data['contact_person'] = $store->contact_person;
        $data['contact_number'] = $store->contact_number;
		$data['contact_designation'] = $store->contact_designation;
        $data['store_logo_flag'] = $store_logo_flag;
        $data['store_details_img'] = $store->store_details_img;
        $data['store_logo'] = $store->store_logo;
        $data['store_icon'] = $store->store_icon;
        $data['location'] = $store->location;  
		$data['delivery'] = $store->delivery;
        $data['store_status'] = $flag;
        $data['day'] = date('D');
        $data['max_qty'] = $store->max_qty;
        $data['user_rating'] = $rating;
        $data['rating'] = store_rating($store->store_id,$store->v_id);
		$data['color'] = $colorSettings;
		$data['offer_detail_flag'] = $otherSettings->offer_detail_flag; 
        $data['locate_product_flag'] = $otherSettings->locate_product_flag;  

		$popUp = PopUp::where('v_id', $v_id)->where('store_id', $store_id)->where('status' ,'1')->first();
		if($popUp){
			
			$popUpCustomer = PopUpCustomer::where('v_id', $v_id)->where('store_id', $store_id)->where('pop_up_id', $popUp->id)->where('c_id', Auth::user()->c_id )->first();

			if($popUpCustomer){
				 $pop =['flag' => 'NO'];
			}else{
				 $pop = [ 'flag' => 'YES' , 'offer_title' => $popUp->offer_title,  'offer_description' => $popUp->offer_description, 'pop_up_id' =>  $popUp->id , 'store_icon' =>  store_logo_link().$store->store_icon, 'tc' => 'https://zwing.in/faq/' ]  ;   
			}
			$data['pop_up'] =  $pop ;	
				
		}else{
			$data['pop_up'] =  ['flag' => 'NO'];;	
			
		}

		if($v_id == 3){
            $data['store_navigation_img'] = 'vmart_store_nav_img.png';
            $data['store_scango_img'] = 'vmart_scango_bg_img.png';
            $data['offer_font_color'] = '#555555';

             $offers_grid[] = [ 'image' => 'http://zwing.in/vendor/vendorstuff/store/offers/vmart_grid_1' ];
             $offers_grid[] = [ 'image' => 'http://zwing.in/vendor/vendorstuff/store/offers/vmart_grid_2' ];
			 
			
			$template2[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/vmart_grid_1.png', 'offer_id' => 1 ];
			$template2[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/vmart_grid_2.png', 'offer_id' => 2 ];
			
			$template4[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/vmart_offer_1.png', 'offer_id' => 3 ];
			
			$offers[] = [ 'offer_template_id'=> 2, 'images' => $template2 ];
			$offers[] = [ 'offer_template_id'=> 4, 'images' => $template4 ];
			
			
			$template1[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/decath_grid_1.png', 'offer_id' => 1 ];
			$template1[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/decath_grid_2.png', 'offer_id' => 2 ];
			
			$template3[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/offers_1.png', 'offer_id' => 3 ];
			
			$offers[] = [ 'offer_template_id'=> 1, 'images' => $template1 ];
			$offers[] = [ 'offer_template_id'=> 3, 'images' => $template3 ];

        }else{

            $data['store_navigation_img'] = 'decath_store_nav_img.png';
            $data['store_scango_img'] = 'decath_scango_bg_img.png';
            $data['offer_font_color'] = '#065086';

            $offers_grid[] = [ 'image' => 'http://zwing.in/vendor/vendorstuff/store/offers/decath_grid_1' ];
            $offers_grid[] = [ 'image' => 'http://zwing.in/vendor/vendorstuff/store/offers/decath_grid_2' ];
			
			$template1[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/decath_grid_1.png', 'offer_id' => 1 ];
			$template1[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/decath_grid_2.png', 'offer_id' => 2 ];
			
			$template3[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/offers_1.png', 'offer_id' => 3 ];
			
			$offers[] = [ 'offer_template_id'=> 1, 'images' => $template1 ];
			$offers[] = [ 'offer_template_id'=> 3, 'images' => $template3 ];
			
			
			$template2[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/vmart_grid_1.png', 'offer_id' => 1 ];
			$template2[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/vmart_grid_2.png', 'offer_id' => 2 ];
			
			$template4[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/vmart_offer_1.png', 'offer_id' => 3 ];
			
			$offers[] = [ 'offer_template_id'=> 2, 'images' => $template2 ];
			$offers[] = [ 'offer_template_id'=> 4, 'images' => $template4 ];

        }


		$data['offers'] = $offers;
		$data['offer_grid'] = $offers_grid;
        
        return response()->json(['status' => 'store_details', 'message' => 'Store Profile Details', 'data' => $data, 'store_logo_link' => store_logo_link() ],200);
    }

    public function profile_update(Request $request)
    {
        $c_id = $request->c_id;
        $mobile = $request->mobile;
        $first_name = $request->first_name;
        $last_name = $request->last_name;
        $gender = $request->gender;
        $email = $request->email;
        $dob = date('Y-m-d', strtotime($request->dob));
        $user_details = User::where('c_id', $c_id)->where('mobile', $mobile)->update(['first_name' => $first_name, 'last_name' => $last_name, 'gender' => $gender, 'dob' => $dob, 'email' => $email ]);
        return response()->json(['status' => 'profile_update', 'message' => 'Profile Update Successfully', 'mobile' => $mobile, 'c_id' => $c_id, 'api_token' => User::find($c_id)->api_token], 200);
    }
    
    public function no_auth_stote_list(Request $request)
    {
        $latitude = $request->latitude;
        $longitude = $request->longitude;
		$c_id = $request->c_id;
        $stores  = DB::table('stores')
            ->select(DB::raw('store_id, v_id, type, name, address1, address2, latitude, longitude, opening_time, closing_time, description, tagline, store_logo,store_icon, store_list_bg, location,  ( 6371 * acos ( cos ( radians('.$latitude.') ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians('.$longitude.') ) + sin ( radians('.$latitude.') ) * sin( radians( latitude ) ) ) ) AS customer_distance'))
            ->having('customer_distance', '<', 300)
            ->orderBy('customer_distance', 'asc')
            ->where('api_status', 1);
        if($request->has('store_id')){
             $stores  = $stores->where('store_id', $request->store_id);
        }
	
		if($request->has('search_term')){
             $stores  = $stores->where('name','like','%'.$request->search_term.'%');
        }
        
        $stores  = $stores->limit(50)->get()->toArray();
        $data = array();
		$storeLanding = false;
        foreach ($stores as $key => $store) {
			if($store->customer_distance <= 0.8){
               $storeLanding = true;  
            }
            $opening_time = date('h A', strtotime($store->opening_time));
            $closing_time = date('h A', strtotime($store->closing_time));
            date_default_timezone_set("Asia/Kolkata");
            $nowDate = date("Y-m-d h:i:sa");
            $start = date('H:i:s', strtotime($store->opening_time));
            $end   = date('H:i:s', strtotime($store->closing_time));
            $time = date("H:i:s");
            $flag = isWithInTime($start, $end, $time);
            $rating_exits = Rating::where('Store_ID', $store->store_id)->where('V_ID', $store->v_id)->where('User_ID', $c_id)->count();
            if (empty($rating_exits)) {
                $rating = 'No';
            } else {
                $rating = 'Yes';
            }
            
            $mgPath = store_logo_link().$store->store_logo ;
            $store_logo_flag = false;
            if (@getimagesize($mgPath)) {
                $store_logo_flag = true;
            }


            $data[] = array(
                'store_id' => $store->store_id,
                'v_id' => $store->v_id,
                'type' => $store->type,
                'name' => $store->name,
                'address1' => $store->address1,
                'address2' => $store->address2,
                'latitude' => $store->latitude,
                'longitude' => $store->longitude,
                'opening_time' => $opening_time,
                'closing_time' => $closing_time,
                'description' => $store->description,
                'tagline' => $store->tagline,
                'store_logo_flag' => $store_logo_flag,
                'store_logo' => $store->store_logo,
                'store_icon' => $store->store_icon,
				'store_list_bg' => $store->store_list_bg,
                'location' => $store->location,
                'customer_distance' => $store->customer_distance,
                'store_status' => $flag,
                'user_rating' => $rating,
                'rating' => store_rating($store->store_id,$store->v_id)
            );
        }

        return response()->json(['store_landing' => $storeLanding ,'status' => 'store_list', 'message' => 'Store List Data', 'data' => $data, 'store_logo_link' => store_logo_link() ],200);
    }

    public function store_details(Request $request)
    {
        $v_id = $request->v_id;
        $store_id = $request->store_id;
		$c_id = $request->c_id;
        $store = DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();
        $data = array();
        $opening_time = date('h A', strtotime($store->opening_time));
        $closing_time = date('h A', strtotime($store->closing_time));
        date_default_timezone_set("Asia/Kolkata");
        $nowDate = date("Y-m-d h:i:sa");
        $start = date('H:i:s', strtotime($store->opening_time));
        $end   = date('H:i:s', strtotime($store->closing_time));
        $time = date("H:i:s");
        $flag = isWithInTime($start, $end, $time);
        $rating_exits = Rating::where('Store_ID', $store->store_id)->where('V_ID', $store->v_id)->where('User_ID', $c_id)->count();
        if (empty($rating_exits)) {
            $rating = 'No';
        } else {
            $rating = 'Yes';
        }
        $mgPath = store_logo_link().$store->store_logo ;
	    $store_logo_flag = false;
	    if (@getimagesize($mgPath)) {
	        $store_logo_flag = true;
	    }
		
		
		$vendorS = new VendorSettingController;
		$settings = $vendorS->getSetting($store->v_id , 'color');
		if($settings){
			
			$settings = $settings->first()->settings;
			$colorSettings = json_decode($settings);

			 $colorSettings;
		}else{

			$colorSettings = json_decode('{"color_top":{"r":6,"g":80,"b":133,"hex":"#065085"},"color_bottom":{"r":28,"g":116,"b":180, "hex":"#1C74B4"}}');

		}
		
		
		$vendorS = new VendorSettingController;
        $settings = $vendorS->getSetting($store->v_id , 'others');
        if($settings){
            $settings = $settings->first()->settings;
            $otherSettings = json_decode($settings);

             $otherSettings;
        }else{

            $otherSettings = json_decode('{"offer_detail_flag":"YES","locate_product_flag":"YES"}');

        }
            
        $data['store_id'] = $store->store_id;
		$data['store_random'] = $store->store_random;
        $data['v_id'] = $store->v_id;
        $data['type'] = $store->type;
        $data['name'] = $store->name;
		$data['email'] = $store->email;
		$data['pincode'] = $store->pincode;
        $data['address1'] = $store->address1;
        $data['address2'] = $store->address2;
		$data['state'] = $store->state;
        $data['city'] = $store->city;
        $data['latitude'] = $store->latitude;
        $data['longitude'] = $store->longitude;
        $data['opening_time'] = $opening_time;
        $data['closing_time'] = $closing_time;
		$data['weekly_off'] = $store->weekly_off;
        $data['description'] = $store->description;
        $data['tagline'] = $store->tagline;
		$data['contact_person'] = $store->contact_person;
        $data['contact_number'] = $store->contact_number;
		$data['contact_designation'] = $store->contact_designation;
        $data['store_logo_flag'] = $store_logo_flag;
        $data['store_details_img'] = $store->store_details_img;
        $data['store_logo'] = $store->store_logo;
        $data['store_icon'] = $store->store_icon;
        $data['location'] = $store->location;  
		$data['delivery'] = $store->delivery;
        $data['store_status'] = $flag;
        $data['day'] = date('D');
        $data['max_qty'] = $store->max_qty;
        $data['user_rating'] = $rating;
        $data['rating'] = store_rating($store->store_id,$store->v_id);
		$data['color'] = $colorSettings;
		$data['offer_detail_flag'] = $otherSettings->offer_detail_flag; 
        $data['locate_product_flag'] = $otherSettings->locate_product_flag;  
		$data['pop_up'] =  ['flag' => 'NO'];
		

		if($v_id == 3){
            $data['store_navigation_img'] = 'vmart_store_nav_img.png';
            $data['store_scango_img'] = 'vmart_scango_bg_img.png';
            $data['offer_font_color'] = '#555555';

             $offers_grid[] = [ 'image' => 'http://zwing.in/vendor/vendorstuff/store/offers/vmart_grid_1' ];
             $offers_grid[] = [ 'image' => 'http://zwing.in/vendor/vendorstuff/store/offers/vmart_grid_2' ];
			 
			
			$template2[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/vmart_grid_1.png', 'offer_id' => 1 ];
			$template2[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/vmart_grid_2.png', 'offer_id' => 2 ];
			
			$template4[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/vmart_offer_1.png', 'offer_id' => 3 ];
			
			$offers[] = [ 'offer_template_id'=> 2, 'images' => $template2 ];
			$offers[] = [ 'offer_template_id'=> 4, 'images' => $template4 ];
			
			
			$template1[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/decath_grid_1.png', 'offer_id' => 1 ];
			$template1[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/decath_grid_2.png', 'offer_id' => 2 ];
			
			$template3[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/offers_1.png', 'offer_id' => 3 ];
			
			
			$offers[] = [ 'offer_template_id'=> 1, 'images' => $template1 ];
			$offers[] = [ 'offer_template_id'=> 3, 'images' => $template3 ];

        }else{

            $data['store_navigation_img'] = 'decath_store_nav_img.png';
            $data['store_scango_img'] = 'decath_scango_bg_img.png';
            $data['offer_font_color'] = '#065086';

            $offers_grid[] = [ 'image' => 'http://zwing.in/vendor/vendorstuff/store/offers/decath_grid_1' ];
            $offers_grid[] = [ 'image' => 'http://zwing.in/vendor/vendorstuff/store/offers/decath_grid_2' ];
			
			$template1[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/decath_grid_1.png', 'offer_id' => 1 ];
			$template1[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/decath_grid_2.png', 'offer_id' => 2 ];
			
			$template3[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/offers_1.png', 'offer_id' => 3 ];
			
			
			$offers[] = [ 'offer_template_id'=> 1, 'images' => $template1 ];
			$offers[] = [ 'offer_template_id'=> 3, 'images' => $template3 ];
			
			
			$template2[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/vmart_grid_1.png', 'offer_id' => 1 ];
			$template2[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/vmart_grid_2.png', 'offer_id' => 2 ];
			
			$template4[] = [ 'image'=> 'http://zwing.in/vendor/vendorstuff/store/offers/vmart_offer_1.png', 'offer_id' => 3 ];
			
			$offers[] = [ 'offer_template_id'=> 2, 'images' => $template2 ];
			$offers[] = [ 'offer_template_id'=> 4, 'images' => $template4 ];

        }

		$data['offers'] = $offers;
		$data['offer_grid'] = $offers_grid;
        
        return response()->json(['status' => 'store_details', 'message' => 'Store Profile Details', 'data' => $data, 'store_logo_link' => store_logo_link() ],200);
    }
	
	
	public function delete_user(Request $request){
        $c_id =  $request->c_id;
        User::where('c_id' , $c_id)->delete();

         return response()->json(['status' => 'store_details', 'message' => 'User detleted' ],200);

    }

    public function order_receipt($c_id,$v_id , $store_id, $order_id){
        
        $stores =  DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();
        $order = Order::where('order_id', $order_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->first();
        $carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order->o_id)->get();
        $user = User::select('first_name','last_name', 'mobile')->where('c_id',$c_id)->first();

        $store_db_name = $stores->store_db_name;

        $total = 0.00;
        $total_qty =0;
        $item_discount = 0.00;
        $counter =0;
        $tax_details = [];
        $tax_details_data = [];
        $cart_item_text ='';
        $tax_item_text = '';
        $param = [];
        $params = [];
        $tax_category_arr = [ 'A','B', 'C','D' ,'E','F' ,'G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V'];
        $tax_code_inc = 0;
        $cart_tax_code = [];

        foreach ($carts as $key => $cart) {

            $counter++;
            $total += $cart->total;
            $item_discount += $cart->discount;
            $total_qty += $cart->qty;
            $tax_category = '';
           
            $cart_tax_code_msg = '';

            $loopQty = $cart->qty;
            while($loopQty > 0){
               $param[] = $cart->total / $cart->qty; 
               $params[] = ['item_id' => $cart->item_id , 'price' => $cart->total / $cart->qty ];
               $loopQty--;
            }
            

            $res = DB::table('cart_offers')->where('cart_id',$cart->cart_id)->first();
            $offer_data = json_decode($res->offers, true);
            $item_master = DB::table($store_db_name.'.item_master')->where('ITEM',$cart->item_id)->first();
            $res = DB::table('cart_offers')->where('cart_id',$cart->cart_id)->first();
            $offer_data = json_decode($res->offers, true);
            $hsn_code = '';
            /*if(isset($offer_data['hsn_code'])){
                $hsn_code = $offer_data['hsn_code'];
            }*/
            if(isset($item_master->HSN) && $item_master->HSN != ''){
                $hsn_code = $item_master->HSN;
            }
            foreach ($offer_data['pdata'] as $key => $value) {
                $tax_details_data[$cart->item_id] = ['tax' =>  $value['tax'] , 'total' => $value['ex_price'] ];

                /*foreach($value['tax'] as $nkey => $tax){
                    if(isset($tax_details[$tax['tax_code']])){
                        $tax_details[$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
                        $tax_details[$tax['tax_code']]['tax'] += $tax['tax'] ;
                    }else{
                        $tax_details[$tax['tax_code']] = $tax;
                        
                    }
                    
                }*/

                if(empty($value['tax']) ){

                    if(isset($tax_details[00][00])){
                        $cart_tax_code_msg .= $cart_tax_code[00][00];
                        $cart_tax_code_msg .= $cart_tax_code[00][01];
                    }else{

                        $tax_details[00][00] = [ "tax_category" => "0",
                          "tax_desc" => "CGST_00_RC",
                          "tax_code" => "0",
                          "tax_rate" => "0",
                          "taxable_factor" => "0",
                          "taxable_amount" => $cart->total,
                          "tax" => 0.00 ] ;

                        $cart_tax_code_msg .= $tax_category_arr[$tax_code_inc];
                        $cart_tax_code[00][00] = $tax_category_arr[$tax_code_inc];
                        $tax_code_inc++;

                        $tax_details[00][01] = [ "tax_category" => "0",
                          "tax_desc" => "SGST_00_RC",
                          "tax_code" => "0",
                          "tax_rate" => "0",
                          "taxable_factor" => "0",
                          "taxable_amount" => $cart->total,
                          "tax" => 0.00 ] ;
                        $cart_tax_code_msg .= $tax_category_arr[$tax_code_inc];
                        $cart_tax_code[00][01] = $tax_category_arr[$tax_code_inc];
                        $tax_code_inc++;
                    }

                }else{
                    
                    foreach($value['tax'] as $nkey => $tax){
                        $tax_category = $tax['tax_category'];
                        if(isset($tax_details[$tax_category][$tax['tax_code']])){
                            $tax_details[$tax_category][$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
                            $tax_details[$tax_category][$tax['tax_code']]['tax'] += $tax['tax'] ;
                            $cart_tax_code_msg .= $cart_tax_code[$tax_category][$tax['tax_code']];
                        }else{
                            $tax_details[$tax_category][$tax['tax_code']] = $tax;
                            $cart_tax_code_msg .= $tax_category_arr[$tax_code_inc];
                            $cart_tax_code[$tax_category][$tax['tax_code']] = $tax_category_arr[$tax_code_inc];
                            $tax_code_inc++;
                            
                        }
                        
                    }
                }
                break;
            }

            //$cart_item_arr[] = ['hsn_code' => $hsn_code , 'item_name' => $cart->item_name , 'unit_mrp' => $cart->unit_mrp, 'qty' => $cart->qty , 'discount' => $cart->discount , 'total' => $cart->total , 'tax_category' => $tax_category ]; 
            
           $cart_item_text .=
             '<tr class="td-center">
                <td colspan="3" style="text-align:left">'.$counter."&nbsp;&nbsp;&nbsp;".$hsn_code.'   '.substr($cart->item_name, 0,20).'</td>
                <td>'.$cart_tax_code_msg.'</td>
    
            </tr>
            <tr class="td-center">
                <td style="padding-left:20px;text-align:left">'.$cart->qty.'</td>
                <td> '.format_number($cart->unit_mrp).'</td>
                <td>'.format_number($cart->discount / $cart->qty).'</td>
                <td>'.$cart->total.'</td>
            </tr>';

            if( $order->transaction_type == 'return'){
               $cart_item_text .=
                 '<tr class="td-center">
                    <td colspan="3" style="text-align:left">&nbsp;&nbsp;&nbsp;&nbsp; Orig. Receipt: '.$order->ref_order_id.'</td>
                    <td></td>
        
                </tr>';   
            }
                      


        }
        //dd($tax_details);
        $transaction_type = $order->transaction_type;
        $employee_discount_text = '';
        $employee_details = '';
        if($order->employee_discount > 0.00){
            $total = $total - $order->employee_discount;
            $employee_discount_text .=
            '<tr>
                <td colspan="3">Employee Discount</td> 
                <td> -'.format_number($order->employee_discount).'</td>
            </tr>';

            $emp_d = DB::table($store_db_name.'.employee_details')->where('employee_id', $order->employee_id)->first();
            $employee_details .=
            '<div style="text-align:left;line-height: 0.4;padding-top:10px">
                <p>EMPLOYEE NAME : '.$emp_d->first_name.' '.$emp_d->last_name.'</p>
                <p>COMPANY NAME : '.$emp_d->company_name.'</p>
                <p>ID : '.$order->employee_id.'</p>
                <p>AVAILABLE AMOUNT : '.$order->employee_available_discount.' </p>
            </div>';
        }

        $bill_buster_discount_text = '';
        if($order->bill_buster_discount > 0){
            $total = $total - $order->bill_buster_discount;
            $bill_buster_discount_text .=
            '<tr>
                <td colspan="3">Bill Buster</td> 
                <td> -'.format_number($order->bill_buster_discount).'</td>
            </tr>';

            //Recalcualting taxes when bill buster is applied
            $promo_c = new PromotionController(['store_db_name' => $store_db_name]);
            $tax_details =[];
            $ratio_val = $promo_c->get_offer_amount_by_ratio($param, $order->bill_buster_discount);
            $ratio_total = array_sum($ratio_val);

            $discount = 0;
            $total_discount = 0;
            //dd($param);
            foreach($params as $key => $par){
                $discount = round( ($ratio_val[$key]/$ratio_total) * $order->bill_buster_discount , 2);
                $params[$key]['discount'] =  $discount;
                $total_discount += $discount;
            }
            //dd($params);
            //echo $total_discount;exit;
            //Thid code is added because facing issue when rounding of discount value
            if($total_discount > $order->bill_buster_discount){
                $total_diff = $total_discount - $order->bill_buster_discount;
                foreach($params as $key => $par){
                    if($total_diff > 0.00){
                        $params[$key]['discount'] -= 0.01;
                        $total_diff -= 0.01;
                    }else{
                        break;
                    }
                }
            }else if($total_discount < $order->bill_buster_discount){
                $total_diff =  $order->bill_buster_discount - $total_discount;
                foreach($params as $key => $par){
                    if($total_diff > 0.00){
                        $params[$key]['discount'] += 0.01;
                        $total_diff -= 0.01;
                    }else{
                        break;
                    }
                }
            }
            //dd($params);
            foreach($params as $key => $para){
                $discount = $para['discount'];  
                $item_id = $para['item_id'] ;
                // $tax_details_data[$key]
                foreach($tax_details_data[$item_id]['tax'] as $nkey => $tax){
                    $tax_category = $tax['tax_category'];
                    $taxable_total = $para['price'] - $discount;
                    $tax['taxable_amount'] = round( $taxable_total * $tax['taxable_factor'] , 2 );
                    $tax['tax'] =  round( ($tax['taxable_amount'] * $tax['tax_rate']) /100 , 2 );
                    //$tax_total += $tax['tax'];
                    if(isset($tax_details[$tax_category][$tax['tax_code']])){
                        $tax_details[$tax_category][$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
                        $tax_details[$tax_category][$tax['tax_code']]['tax'] += $tax['tax'] ;
                    }else{
                        
                        $tax_details[$tax_category][$tax['tax_code']] = $tax;
                    }

                }
            }

        }

        //dd($tax_details_data);

        $discount_text = '';
        if(($item_discount + $order->bill_buster_discount) > 0){
           $discount_text = '<p>***TOTAL SAVING : Rs. '.format_number($item_discount+ $order->bill_buster_discount).' *** </p>';
        }

        $tax_counter =0;
        $total_tax = 0;
        //dd($tax_details);
        foreach($tax_details as $tax_category){
            foreach($tax_category as $tax){
                
                $total_tax += $tax['tax'];
                $tax_item_text .=
                 '<tr >
                    <td>'.$tax_category_arr[$tax_counter].'  '.substr($tax['tax_desc'],0,-2).' ('.$tax['tax_rate'].'%) '.'</td>
                    <td>'.format_number($tax['taxable_amount']).'</td>
                    <td>'.format_number($tax['tax']).'</td>
                </tr>';
                $tax_counter++;
            }
        }

        //$rounded =  round($total);
        $rounded =  $total;
        $rounded_off =  $rounded - $total;
        $transaction_type_msg = '';
        if($order->transaction_type == 'sales')
        {

            $payments = Payment::where('v_id',$v_id)->where('store_id',$store_id)->where('user_id',$c_id)->where('order_id', $order_id)->get();
            if($payments){

                foreach($payments as $payment){
                    if($payment->method != 'spar_credit'){
                        $transaction_type_msg .= '<tr>
                        <td colspan="3">&nbsp;&nbsp; Zwing Online</td> 
                        <td>'.format_number($payment->amount).'</td>
                        </tr>';
                    }else{

                        $transaction_type_msg .= '<tr>
                        <td colspan="3">&nbsp;&nbsp; Credit Note </td> 
                        <td>'.format_number($payment->amount).'</td>
                        </tr>';
                    }
                }
                

            }else{
                return response()->json([ 'status'=>'fail', 'message'=> 'Payment is not processed' ], 200);
            }

        }else{
            $voucher = DB::table('voucher')->where('ref_id', $order->ref_order_id)->where('user_id',$order->user_id)->first();
            if($voucher){

            
                $transaction_type_msg .= '<tr>
                        <td colspan="3">&nbsp;&nbsp; Store credit</td> 
                        <td> -'.format_number($rounded).'</td>
                    </tr>
                    <tr>
                    <td></td>
                    <td colspan="3">Store Credit #: '.$voucher->voucher_no.'<td>
                    </tr>';
            }

        }
                    
        
        //dd($tax_details);
        $html = 
        '<!DOCTYPE html>
        <html>
            <head>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            </head>
            <title></title>
            <style type="text/css">
            .container {
                max-width : 400px;
                margin:auto;
                margin : auto;
               #font-family: Arial, Helvetica, sans-serif;
                font-family: courier, sans-serif;
                font-size: 14px;
            }

            body {
                background-color:#ffff;

            }

            table {
                width: 100%;
                font-size: 14px;
            }
            .td-center td 
            {
                text-align: center;
            }
            .invoice-address p {
                line-height: 0.6;
            }
            hr {
                border-top:1px dashed #000;
                border-bottom: none;
                
            }
            </style>
            <body>
                <div class="container">
                <center>
                    <img src="http://zwing.in/vendor/vendorstuff/store/logo/spar-logo.png" > 
                    <p>MAX HYPERMARKET INDIA PVT LTD</P>
                    <hr/>
                    <div class="invoice-address">
                        <p>'.$stores->address1.'</P>
                        <p>'.$stores->address2.'</P>
                        <p>'.$stores->city.' - '.$stores->pincode.'</P>
                        <p>GSTIN - '.$stores->gst.'</P>
                        <p>TIN - '.$stores->tin.'</P>
                        <p>Helpline - 044-66622267</P>
                        <p>Store Timing - '.$stores->opening_time.' To '.$stores->closing_time.'</P>
                        <p>EMAIL - customer@sparindia.com</P>

                        <div style="text-align:left;margin-top:20px">
                        <p>Name : '.$user->first_name.' '.$user->last_name.'</p>
                        <p>Mobile : '.$user->mobile.'</p>
                        </div>
                    </div>
                    <hr/>
                    <p></p>

                    <hr/>
                    <table>
                    
                    <tr class="td-center">
                        <td>HSN/ITEM</td>
                        <td>Rate</td>
                        <td>Disc</td>
                        <td>Amount TC</td>
                    </tr>
                    <tr>
                        <td>/QTY</td>
                        <td>(Rs./UNIT)</td>
                        <td>(Rs./UNIT)</td>
                        <td>(Inc.VAT)</td>
                    </tr>
                    </table>
                    <hr>
                    <table>
                    <tr class="td-center" style="line-height: 0;">
                        <td height="2">&nbsp;&nbsp;&nbsp;&nbsp;</td>
                        <td height="2">&nbsp;</td>
                        <td height="2">&nbsp;&nbsp;&nbsp;&nbsp;</td>
                        <td height="2">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                    </tr>

                   '.$cart_item_text.'
                    <tr>
                        <td colspan="4">&nbsp;</td>
                        
                    </tr>
                    '.$employee_discount_text.'
                    '.$bill_buster_discount_text.'
                    <tr>
                        <td colspan="3">Total Amount</td> 
                        <td>'.format_number($total).'</td>
                    </tr>
                    <tr><td>&nbsp;<td></tr>

                    <tr>
                        <td colspan="3">&nbsp;&nbsp; Total Rounded</td> 
                        <td>'.format_number($rounded).'</td>
                    </tr>
                    <tr><td>&nbsp;<td></tr>

                    <tr>
                        <td colspan="3">&nbsp;&nbsp; Rounded Off Amt</td> 
                        <td>'.format_number($rounded_off).'</td>
                    </tr>
                    <tr><td>&nbsp;<td></tr>
                    '.$transaction_type_msg.'
                    <tr><td>&nbsp;<td></tr>

                    <tr>
                        <td colspan="3">Total Tender</td> 
                        <td>'.format_number($rounded).'</td>
                    </tr>
                    <tr><td>&nbsp;<td></tr>

                    <tr>
                        <td colspan="3">&nbsp;&nbsp; Change Due</td> 
                        <td>0.00</td>
                    </tr>
                    <tr><td>&nbsp;<td></tr>
                    
                    <tr>
                        <td colspan="3">Total number of items/Qty</td> 
                        <td>'.$counter.'/'.$total_qty.'</td>
                    </tr>
                    </table>
                    '.$employee_details.'
                    '.$discount_text.'
                    <p>Tax Details</p>
                    
                    <table>
                    <tr>
                        
                        <td>Tax Desc</td>
                        <td>TAXABLE</td>
                        <td>Tax</td>
                    </tr>
                    '.$tax_item_text.'
                    <tr>
                        <td colspan="6">&nbsp;</td>
                        
                    </tr>
                    <tr>
                        <td colspan="2">Total tax value</td> 
                        <td>'.format_number($total_tax).'</td>
                    </tr>
                </table>
                <hr>
                <div class="invoice-address">
                    <p>THANK YOU !!! DO VISIT AGAIN<p>
                    <p>E&OE<p>
                    <p>FOR EXCHANGE POLICY<p>
                    <p>PLEASE REFER END OF THE BILL<p>
                    <p>&nbsp;</p>
                </div>
                <hr/>
                <p>Tax Invoice/Bill Of Supply - '.strtoupper($transaction_type).'<p>
                <p>'.$order->order_id.'</p>
                <p></p>
                <hr/>
                <p>'.date('H:i:s d-M-Y', strtotime($order->created_at)).'</p>
                <p>&nbsp;</p>
                <p>&nbsp;</p>
                <div style="text-align:left">
                <h3>Exchange Policy</h3>
                <p>At SPAR, our endeavor is to bring you Superior Quality
                Products at all times. If, for some reason you wish to
                exchange, we would be pleased to do so within 14 days from
                the date of purchase against submission of Original
                invoice to the same store.</p>
                
                <p>All Electric, Electronic, Luggage & Information
                Technology products shall be subject to manufacturer\'s
                warranty only and is not covered under this exchange
                policy. After sales service, wherever is applicable, will
                be provided by the authorized service centers of the
                respective manufacturers, based on their terms and
                conditions of warranty.</p>

                <p>For reasons of health & hygiene undergarments, personal
                care products, swimwear, socks, cosmetics, crockery,
                jewellery, frozen foods, dairy and bakery products, loose
                staples & dry fruits, fruits and vegetables, baby food,
                liquor, tobacco, over the counter medication (OTC) &
                Products of similar nature will not be exchanged.
                Exchange/refund will not be entertained on altered,
                damaged, used, discounted products and merchandise
                purchased on promotional sale.</p>

                <p>All products returned should be unused, undamaged and in
                saleable condition.
                Refund will be through a credit note for onetime use valid
                for 30 days from the date of issue to be redeemed in the
                same store. No duplicate credit note will be issued in
                lieu of damaged/lost/defaced/mutilated Credit Note/s.
                While our endeavor is to be flexible, in case of any
                dispute, the same shall be subject to Bengaluru
                jurisdiction only.</p>


                <div>
                </center>
                </div>
            </body>
        </html>';

        return $html;

    }

    public function returnMail($c_id, $v_id, $store_id, $order_id)
    {
        // $user_id = $request->c_id;
        // $v_id = $request->v_id;
        // $store_id = $request->store_id;
        // $order_id = $request->order_id;
        try {
            $html = $this->order_receipt($c_id , $v_id, $store_id, $order_id);
            return $html;
            $pdf = PDF::loadHTML($html);
            $path = storage_path();
            $complete_path = $path."/app/invoices/".$current_invoice_name;
            $pdf->setWarnings(false)->save($complete_path);

            $payment_method = (isset($payment->method) )?$payment->method:'';

            $user = Auth::user();
            if($user->email != null && $user->email != ''){
               
                    Mail::to($user->email)->bcc('spar.zwing@maxhypermarkets.com')->send(new OrderCreated($user,$ord,$carts,$payment_method,  $complete_path));
                
            }
            
        }catch(Exception $e){
                    //Nothing doing after catching email fail
        }
    }
}
