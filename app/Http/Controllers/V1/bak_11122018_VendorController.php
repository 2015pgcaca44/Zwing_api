<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Vendor;
use App\DeviceStorage;
use App\DeviceVendorUser;
use DB;
use Illuminate\Support\Facades\Hash;
use Auth;
use App\Order;
use App\Cart;
use App\User;
use App\Payment;
use App\LoginSession;
use App\VendorImage;
use Event;
use App\Events\Authlog;


class VendorController extends Controller
{
    public function get_vendor_details(Request $request){

    
        $vendor_random = $request->vendor_random;

        $vendor_check = DB::table('vendor')->where('vendor_code', $vendor_random)->first();
        if(empty(@$vendor_check)) {
            return response()->json([ 'status' => 'fail', 'message' => 'Vendor ID Not Found' ], 200);
        }
        $store_check = DB::table('stores')->where('v_id', $vendor_check->id)->first();
        if(empty(@$store_check)) {
            return response()->json([ 'status' => 'fail', 'message' => 'Vendor ID / Strore ID Mismatch' ], 200);
        }

         // return response()->json([ 'status' => 'verify_details', 'message' => 'Vendor details', 'v_id' =>$vendor_check->id  , 'vendor_name' => $vendor_check->company_name , 'store_name' => $store_check->name, 'store_icon' => $store_check->store_icon, 'store_logo' => $store_check->store_logo, 'store_location' => $store_check->location, 'store_logo_link' => store_logo_link(), 'store_list_bg' => $store_check->store_list_bg ], 200);
         return response()->json([ 'status' => 'verify_details', 'message' => 'Vendor details', 'v_id' =>$vendor_check->id  , 'vendor_name' => $vendor_check->name , 'store_name' => $store_check->name, 'store_icon' => $store_check->store_icon, 'store_logo' => $store_check->store_logo, 'store_location' => $store_check->location, 'store_logo_link' => store_logo_link(), 'store_list_bg' => $store_check->store_list_bg ], 200);
    }

    public function get_store_data(Request $request){

    
        $v_id = $request->v_id;
        $store_random = $request->store_random;


        $store_check = DB::table('stores')->where('v_id', $v_id)->where('store_random', $store_random)->first();

        if(empty($store_check)) {
            return response()->json([ 'status' => 'vendor_store_mismatch', 'message' => 'Vendor ID / Strore ID Mismatch' ], 404);
        }

        return response()->json([ 'status' => 'store_details', 'message' => 'Store details', 'v_id' =>$v_id  , 'store_id' =>$store_check->store_id, 'store_name' => $store_check->name, 'store_icon' => $store_check->store_icon, 'store_logo' => $store_check->store_logo, 'store_location' => $store_check->location, 'store_logo_link' => store_logo_link() ], 200);
    }

    public function register_mobile(Request $request)
    {
        // SMS API
        $username = "roxfortgroup@gmail.com";
        $hash = "bb7a4a8a91634f50df712fdf5c11ef6745ea735b85afc9443d23ec9ae3e8b711";
        $test = "0";
        $sender = "MZWING";

        $mobile = $request->mobile;
        $trans_from = $request->trans_from;
        $check_mobile_exists = Vendor::where('mobile', $mobile)->count();
        if(!empty($check_mobile_exists)) {
            $check_mobile_active = Vendor::where('mobile', $mobile)->where('mobile_active', 0)->count();
            if(!empty($check_mobile_active)) {
                $otp = rand(1111,9999);
                $vendor_otp_update = Vendor::where('mobile', $mobile)->where('mobile_active', 0)->first();
                if($vendor_otp_update->type == 'guard' || $vendor_otp_update->type =='supervisor'){
                    return response()->json(['status' => 'account_not_active', 'message' => 'You are not authorized to login'], 200);
                }
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

                    if($account_active->type == 'guard' || $account_active->type == 'supervisor'){
                        return response()->json(['status' => 'account_not_active', 'message' => 'You are not authorized to login'], 200);
                    }

                    $vendor_api_token = Vendor::select('vu_id','mobile', 'vendor_id','store_id','udid')->where('mobile', $mobile)->where('mobile_active', 1)->first();

                    $vendorS = new VendorSettingController;
                    $userLogin = $vendorS->getVendorUserLogin(['v_id' => $vendor_api_token->vendor_id , 'trans_from' => $trans_from ]);

                    if($userLogin->device_specific){
                        if($request->udid != $vendor_api_token->udid){
                            return response()->json(['status' => 'fail', 'message' => 'You are Not login with Correct device' ],200);
                        }
                    }
                    
                    $vendor_api_token->api_token = str_random(50);
                    $vendor_api_token->save();
                    $id = $vendor_api_token->vu_id;
                    return response()->json(['status' => 'login_redirect', 'message' => 'Login Successfully', 'data' => $vendor_api_token], 200);
            }
            return response()->json(['status' => 'mobile_already', 'message' => 'Mobile Already Exists'], 200);
        }
        
        $otp = rand(1111,9999);
        $vendor = Vendor::create([ 'mobile' => $mobile, 'otp' => $otp ]);
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
        
        //$vendor = Vendor::where('mobile',$mobile)->first();
        //dd($vendor);
        $new_data = [];
        // $data .="&vu_id=".$hash."&mobile=".$message;
        $new_data['vu_id'] = $vendor->vu_id;
        $new_data['mobile'] = $vendor->mobile;
        
        return response()->json(['status' => 'otp_sent', 'message' => 'OTP Send Successfully', 'data' => $new_data ], 200);
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
        $pin_update = Vendor::where('vu_id', $vu_id)->where('mobile', $mobile)->update(['password' => app('hash')->make($pin), 'vendor_user_random' => rand(100000,999999)]);
        return response()->json(['status' => 'success', 'message' => 'PIN Set Successfully', 'mobile' => $mobile, 'vu_id' => $vu_id], 200);
    }
    
    
    public function get_store_details(Request $request){

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
        //$device_token = $request->device_token;
        $udid = $request->udid;
        $imei = $request->imei;
        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $device_model_number = $request->device_model_number;
        $api_token = str_random(50);
        $trans_from = $request->trans_from;

        $vendor_user = Vendor::where('vu_id', $vu_id)->where('mobile', $mobile)->first();

        $check_email_exists = $vendor_user->where('email',$email)->count();
        if(!empty($check_email_exists)) {
            return response()->json(['status' => 'email_already', 'message' => 'Email Already Exits'], 200);
        }

        if($vendor_user->vendor_id > 0 ){
            $vendorS = new VendorSettingController;
            $userLogin = $vendorS->getVendorUserLogin(['v_id' => $vendor_user->vendor_id , 'trans_from' => $trans_from ]);

            if($userLogin->device_specific){
                $udid = Vendor::where('v_id' , $vendor_user->vendor_id)->where('udid', $request->udid)->first();
                if($udid){
                    return response()->json(['status' => 'fail', 'message' => 'Another user is already registered with this device'], 200);
                }
                
            }
        }


        $vendor_details = Vendor::where('vu_id', $vu_id)->where('mobile', $mobile)->update(['first_name' => $first_name, 'last_name' => $last_name, 'gender' => $gender, 'dob' => $dob, 'email' => $email, 'device_name' => $device_name, 'os_name' => $os_name, 'os_version' => $os_version, 'udid' => $udid, 'imei' => $imei, 'latitude' => $latitude, 'longitude' => $longitude, 'device_model_number' => $device_model_number, 'status' => '1' , 'api_token' => $api_token ]);
        
        
        return response()->json(['status' => 'registed', 'message' => 'Registed Successfully', 'mobile' => $mobile, 'vu_id' => $vu_id , 'api_token' => $api_token ,'active_status' => 1,   'approved_by_store' =>  $vendor_user->approved_by_store ], 200);
    }

    public function login(Request $request)
    {
        $mobile = $request->mobile;
        $pin = $request->pin;
        $trans_from = $request->trans_from;

        $vendor = Vendor::select('vu_id','first_name','last_name','email','mobile','api_token','password', 'vendor_id' , 'store_id','status' , 'udid','approved_by_store' , 'first_name' , 'last_name' , 'gender')->where('mobile', $mobile)->first();

        if(!$vendor) {
            return response()->json(['status' => 'm_not_found', 'message' => 'Mobile not Found'], 404);
        }

        $account_active = Vendor::where('mobile', $mobile)->where('status', 1)->first();

        if(!$account_active) {
            return response()->json(['status' => 'account_not_active', 'message' => 'Your account is not activated'], 200);
        }

        if($account_active->type == 'guard' || $account_active->type == 'supervisor'){
            return response()->json(['status' => 'account_not_active', 'message' => 'You are not authorized to login'], 200);
        }

        if(Hash::check($pin, $vendor->password)) {
            $api_token = str_random(50);
            $vendor->update(['api_token' => $api_token,'latitude'=>$request->latitude,'longitude'=>$request->longitude]);
            
            $store_check = DB::table('stores')->where('store_id', $vendor->store_id, 'store_id')->first();
            //dd($store_check);

            if(strpos($store_check->display_status, ':'.$trans_from.':') === false ){
                return response()->json(['status' => 'fail', 'message' => 'This store is not enabled for this application '],200); 
            }

            $vendorS = new VendorSettingController;
            $userLogin = $vendorS->getVendorUserLogin(['v_id' => $vendor->vendor_id , 'trans_from' => $trans_from ]);

            if($userLogin->device_specific){
                if($request->udid != $vendor->udid){
                    return response()->json(['status' => 'fail', 'message' => 'You are login with Correct device' ],200);
                }
            }
            
            $request->request->add(['v_id' => $vendor->vendor_id, 'api_token' =>$vendor->api_token , 'vu_id' =>$vendor->vu_id ,'response_format' => 'ARRAY'  ]);
            $vSetting =  $this->get_settings($request);


            
            $loginSession = new LoginSession;
            $loginSession->v_id = $vendor->vendor_id;
            $loginSession->store_id = $vendor->store_id;
            $loginSession->vu_id = $vendor->vu_id;
            $loginSession->api_token = $vendor->api_token;
            $loginSession->save();

            $userdata = array('store_id' => $vendor->store_id,'vendor_id'=>$vendor->vendor_id,'staff_id'=>$vendor->vu_id,'type'=>'Login','ip_address'=> $_SERVER['REMOTE_ADDR'] );

            //Event::dispatch(new Authlog($eventLogData));

            $result = event(new Authlog($userdata));  //Event capture for vendor login

            return response()->json(['status' => 'login_redirect', 'message' => 'Login successfully', 'data' => $vendor ,
            'full_name' => $vendor->first_name.' '.$vendor->last_name,
            'email' =>  (isset($vendor->email))?$vendor->email:'',
            'v_id' => (isset($vendor->vendor_id))?$vendor->vendor_id:'',
            'store_id' =>  (isset($vendor->store_id))?$vendor->store_id:'',
            'store_name' => (isset($store_check->name))?$store_check->name:'',
            'store_icon' => (isset($store_check->store_icon))?$store_check->store_icon:'', 
            'store_logo' => (isset($store_check->store_logo))?$store_check->store_logo:'', 
            'store_location' => (isset($store_check->location))?$store_check->location:'',
            'store_logo_link' => store_logo_link(),
            'store_list_bg' => (isset($store_check->store_list_bg))?$store_check->store_list_bg:'',
            'settings' => $vSetting['settings']
            ], 200);
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

        $userdata = array('store_id' => $vendor->store_id,'vendor_id'=>$vendor->vendor_id,'staff_id'=>$vendor->vu_id,'type'=>'Logout','ip_address'=> $_SERVER['REMOTE_ADDR'] );

        $result = event(new Authlog($userdata));  //Event capture for vendor Logout
        
        $loginSession = LoginSession::where('vu_id', $vu_id)->orderBy('id','desc')->first();
        $loginSession->updated_at = date('Y-m-d H:i:s');
        $loginSession->save();

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

        $vendor = Vendor::select('vu_id','first_name','last_name','email','mobile','api_token','password', 'vendor_id' , 'store_id','status' , 'approved_by_store' , 'first_name' , 'last_name' , 'gender')->where('mobile', $mobile)->where('vu_id',$vu_id)->first();

        $request->request->add(['v_id' => $vendor->vendor_id, 'api_token' =>$vendor->api_token , 'vu_id' =>$vendor->vu_id ,'response_format' => 'ARRAY'  ]);
        $vSetting =  $this->get_settings($request);

        return response()->json(['status' => 'pin_change', 'message' => 'Pin Change Successfully',
        'full_name' => $vendor->first_name.' '.$vendor->last_name,
        'email' =>  (isset($vendor->email))?$vendor->email:'',
        'gender' => (isset($vendor->gender))?$vendor->gender:'',
        'v_id' => (isset($vendor->vendor_id))?$vendor->vendor_id:'',
        'store_id' => (isset($vendor->store_id))?$vendor->store_id:'',
        'approved_by_store' => (isset($vendor->approved_by_store))?$vendor->approved_by_store:'',
        'mobile' => $mobile,
        'vu_id' => $vu_id ,
        'settings' => $vSetting['settings'] ], 200);
    }

    public function profile(Request $request)
    {
        $vu_id = $request->vu_id;
        $mobile = $request->mobile;

        $vendor = Vendor::select('mobile','first_name','last_name','gender','dob','email','email_active')->where('vu_id', $vu_id)->where('mobile', $mobile)->first();

        return response()->json(['status' => 'profile_data', 'message' => 'Profile Data', 'data' => $vendor],200);
    }

    public function profile_update(Request $request)
    {
        $vu_id = $request->vu_id;
        $mobile = $request->mobile;
        $first_name = $request->first_name;
        $last_name = $request->last_name;
        $gender = $request->gender;
        $email = $request->email;
        $dob = date('Y-m-d', strtotime($request->dob));
        $user_details = Vendor::where('vu_id', $vu_id)->where('mobile', $mobile)->update(['first_name' => $first_name, 'last_name' => $last_name, 'gender' => $gender, 'dob' => $dob, 'email' => $email ]);
        return response()->json(['status' => 'profile_update', 'message' => 'Profile Update Successfully', 'mobile' => $mobile, 'vu_id' => $vu_id, 'api_token' => Vendor::find($vu_id)->api_token], 200);
    }


    public function change_store(Request $request)
    {
        $vu_id = $request->vu_id;
        $mobile = $request->mobile;
        $v_id = $request->v_id;
        $store_random = $request->store_random;
        $trans_from = $request->trans_from;


        $store_check = DB::table('stores')->where('v_id', $v_id)->where('store_code', $store_random)->first();

        if(empty($store_check)) {
            return response()->json([ 'status' => 'vendor_store_mismatch', 'message' => 'Vendor ID / Strore ID Mismatch' ], 404);
        }

        $user_details = Vendor::where('vu_id', $vu_id)->where('mobile', $mobile)->update(['vendor_id' => $v_id, 'store_id' => $store_check->store_id , 'approved_by_store' => '0']);
        
        $request->request->add(['v_id' => $v_id, 'api_token' => $request->api_token , 'vu_id' =>$vu_id ,'response_format' => 'ARRAY'  ]);
        
        if($request->has('udid')){
            $device_detail  = DeviceStorage::create(['udid' => $request->udid]);
            $device_user_map = DeviceVendorUser::create(['device_id'=>$device_detail->id,'vu_id' => $vu_id,'v_id' => $v_id , 'store_id' => $store_check->store_id ]);
        }

        $vSetting =  $this->get_settings($request);

        return response()->json(['status' => 'profile_update', 'message' => 'Store Changed Successfully', 'mobile' => $mobile, 'vu_id' => $vu_id, 'v_id' => $v_id , 'store_id' => $store_check->store_id , 'store_location' => (isset($store_check->location))?$store_check->location:'',  'api_token' => Vendor::find($vu_id)->api_token , 'approved_by_store' => '0', 'settings' => $vSetting['settings'] ], 200);
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
        $sub_total = $carts->sum('subtotal');
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

        $paymentMethod = Payment::where('v_id', $o_id->v_id)->where('store_id',$o_id->store_id)->where('order_id',$o_id->order_id)->get()->pluck('method')->all() ;
        
        // return response()->json(['status' => 'order_details', 'message' => 'Order Details Details', 'data' => $cart_data, 'product_image_link' => product_image_link(), 'sub_total' => $sub_total, 'tax_total' => $tax_total, 'grand_total' => $sub_total + $carry_bag_total + $tax_total, 'date' => $o_id->date, 'time' => $o_id->time, 'bags' => $bags, 'carry_bag_total' => $carry_bag_total, 'delivered' => $store->delivery , 'address'=> $address  ],200);
        return response()->json(['status' => 'order_details', 'message' => 'Order Details Details', 
            'mobile' => $o_id->user->mobile,
            'payment_method'=>  implode(',',$paymentMethod),
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
            if($order->verify_status == 1){
                return response()->json(['status' => 'order_verified', 'message' => 'Order has been already verified by Staff' ],200);
            }else{
                
                
                $order->verify_status = '1';
                $order->verified_by = $vu_id;
                $order->save();
                //$order  = $order->update(['verify_status' => '1' , 'verified_by' => $vu_id]);
                
                 return  $this->order_details($request);
                // return response()->json(['status' => 'order_verified', 'message' => 'Order has been verified' ],200);
            }

        }else{

             return response()->json(['status' => 'fail', 'message' => 'Unable to verified this order' ],200);
        }
                        
    }


    public function verify_order_by_guard(Request $request){

        //$vendor = Auth::user();
        //$vu_id = $request->vu_id ;
        $v_id = $request->v_id ;
        $store_id = $request->store_id ;
        
        $order_id = $request->order_id ;
        $vendor_user_random = $request->guard_code;

        $order  = Order::where('order_id' , $order_id)
                        ->where('v_id' , $v_id)
                        ->where('store_id' , $store_id)->first();
        if($order){
            if($order->verify_status == 1){

                if($order->verify_status_guard == 1){

                    return response()->json(['status' => 'fail', 'message' => 'Order has been already verified by Guard' ],200);
                    
                }else{

                    $vendor = Vendor::where('vendor_user_random',$vendor_user_random)->where('type','guard')->where('vendor_id',$v_id)->where('store_id',$store_id)->first();

                    if($vendor){
                        if($vendor->status == '1' && $vendor->approved_by_store == '1'){
                            
                            $order->verify_status_guard = '1';
                            $order->verified_by_guard = $vendor->vu_id;
                            $order->save();
                            //return  $this->order_details($request);
                            return response()->json(['status' => 'success', 'message' => 'Your order is verified , Thank You' ],200);
                        }else{
                             return response()->json(['status' => 'fail', 'message' => 'Guard is not active Or not approved by store Manager' ],200);
                        }

                    }else{

                        return response()->json(['status' => 'fail', 'message' => 'Incorrect Code' ],200);

                    }
                   
                }
            
               
            }else{
               

                  return response()->json(['status' => 'fail', 'message' => 'This order is not verified by Staff , Ask staff to verified first' ],200);
            
            }

        }else{

             return response()->json(['status' => 'fail', 'message' => 'Unable to verified this order' ],200);
        }
                        
    }


    public function login_for_customer(Request $request){
        $customer_mobile = $request->customer_mobile;
        $vu_id = $request->vu_id;

        $exists_user = User::select('c_id','mobile','api_token','password','first_name','last_name','vendor_user_id','email','gender')->where('mobile', $customer_mobile)->first();

        if(!$exists_user) {
            if($request->trans_from == 'ANDROID_KIOSK'){
                $fname  = 'Kiosk';
            }else{
                $fname  = 'mPos';
            }
            $user = new User;
            $user->mobile     = $customer_mobile;
            $user->first_name = $fname;
            $user->email      = '';
            $user->gender     = '';
            $user->last_name  = 'Customer';
            $user->api_token  =  str_random(50);
            $user->vendor_user_id = $vu_id;
            $user->save();
            
            $response = ['status' => 'login_redirect', 'message' => 'Login Successfully', 'data' => $user , 'vu_id' => $vu_id];

        }else{
             
            $order = Order::select('order_id', 'v_id' , 'store_id','date','time','total','verify_status','verify_status_guard')->where('user_id',$exists_user->c_id)->where('status','success')->where('transaction_type','sales')->orderBy('od_id','desc')->first();

            $response = ['status' => 'login_redirect', 'message' => 'Login Successfully', 'data' => $exists_user , 'vu_id' => $vu_id];

            if (Auth::user()->vendor_id == 16) {
                if($order){
                
                    if($order->verify_status != '1'){
                        $response = ['status' => 'fail', 'message' => 'Previous Order Verification is pending from Cashier'];
                       
                    }else if($order->verify_status_guard != '1'){
                        $response = ['status' => 'fail', 'message' => 'Previous Order Verification is pending from Guard' ];
                    }
                    
                }
            }

            
            
        }

        if($request->has('response_format') && $request->response_format == 'ARRAY'){
            return $response;
        }else{
           return response()->json( $response, 200); 
        }

        

    }

    public function scan_for_customer(Request $request){
       return $this->login_for_customer($request);

    }


    public function operation_verification(Request $request){
        
        $vu_id = $request->vu_id ;
        $v_id = $request->v_id ;
        $store_id = $request->store_id ;
        $operation = $request->operation ;
        $security_code = $request->security_code ;

        if($operation ==  'BILL_REPRINT'){
            //$order_id = $request->order_id ;
            $vendor_auth = DB::table('vender_users_auth')->where('vendor_user_random', $security_code )->where('vendor_id', $v_id)->where('store_id', $store_id)->whereIn('type', ['supervisor'])->first();
            if($vendor_auth){
                if($vendor_auth->status =='1' && $vendor_auth->approved_by_store == ''){

                    return response()->json(['status' => 'success', 'message' => 'Authorized successfully' , 'data' => ['security_code_vu_id' => $vendor_auth->vu_id ] ], 200); 
                }else{
                    return response()->json(['status' => 'fail', 'message' => 'Your account is not active'], 200); 
                }
            }else{
                return response()->json(['status' => 'fail', 'message' => 'Incorrect Code'], 200); 
            }

        }else{

            return response()->json(['status' => 'fail', 'message' => 'Operation not specified'], 200); 
        }


    }

    public function get_settings(Request $request){

        //dd($request);
        $v_id = $request->v_id;
        $trans_from = $request->trans_from;

        $response_format = 'JSON';
        if($request->has('response_format')){
            $response_format =  $request->response_format;
        }
        

        $vendorS = new VendorSettingController;
        $colorSettings = $vendorS->getColorSetting(['v_id' => $v_id]);
        $vendorApp = $vendorS->getVendorAppSetting(['v_id' => $v_id]);
        $toolbar = $vendorS->getToolbarSetting(['v_id' => $v_id]);
        
        $paymentTypeSettings = $vendorS->getPaymentTypeSetting(['v_id' => $v_id, 'trans_from' => $trans_from]);

        $feedback = $vendorS->getFeedbackSetting(['v_id' => $v_id, 'trans_from' => $trans_from]);
        $invoice = $vendorS->getInvoiceSetting(['v_id' => $v_id, 'trans_from' => $trans_from]);
        $print = $vendorS->getPrintSetting(['v_id' => $v_id, 'trans_from' => $trans_from]);
        $barcode = $vendorS->getBarcodeSetting(['v_id' => $v_id, 'trans_from' => $trans_from]);
        $optimize_flow = $vendorS->getOptimizeFlowSetting(['v_id' => $v_id, 'trans_from' => $trans_from]);



        $vendor_customer_login = $vendorApp->customer_login;
        if(!$vendor_customer_login){ 

            $request->request->add(['customer_mobile' => $request->mobile , 'response_format' => 'ARRAY' ]);
            $login = $this->login_for_customer($request);

            //dd($login['data']);

            $vendor_login_details = [ 'vendor_customer_login' => $vendor_customer_login , 'api_token' => $login['data']->api_token, 'c_id' => $login['data']->c_id, 'mobile' => (string)$login['data']->mobile , 'email' => $login['data']->email , 'first_name' => $login['data']->first_name , 'last_name' => $login['data']->last_name ] ;
            
        }else{
            $vendor_login_details = [ 'vendor_customer_login' => $vendor_customer_login  ] ;
        }

        $store =  DB::table('stores')->select(DB::raw('name,store_logo,store_icon,store_list_bg,location'))->where('api_status', 1)->where('status', 1)->where('v_id', $v_id)->first();

        $store_logo   = '';
        $store_bg_logo = '';
        $vendorImage  = VendorImage::where('v_id', $v_id);
        if($vendorImage)
        {
            //$bilLogo = env('ADMIN_URL').$vendorImage->path;
            $store_logo = $vendorImage->where('type',1)->where('status',1)->where('deleted',0)->first();
            $store_bg_logo = VendorImage::where('v_id', $v_id)->where('type',2)->where('status',1)->where('deleted',0)->first();
             
        }

        $mgPath = env('ADMIN_URL').$store_logo->path;
        $store_logo_flag = false;
        if (@getimagesize($mgPath)) {
            $store_logo_flag = true;
        }

        
        
        $vendorSett = new VendorSettlementController;
        $sParams = ['v_id' => $v_id, 'trans_from' => $trans_from];

        $appV = DB::table('app_versions')->where('trans_from', $trans_from)->orderBy('id','desc')->first();

        $latest_version = "1.0.0";
        $fore_fully_update = '0';
        $app_update_msg = '';

        if($appV){

            $latest_version = $appV->version;
            $fore_fully_update = $appV->forcefully_update;
            $app_update_msg = $appV->message;
        }

        $vendorSett = new VendorSettlementController;
        $sParams = ['v_id' => $v_id, 'trans_from' => $trans_from];

        $offline_scan = $vendorS->getScanScreenOffilneSetting($sParams);
        if($offline_scan->status == 1){
            $vendorDevice = new DeviceController;
            $checkDevice  = $vendorDevice->getdevice($request);

            if($checkDevice == 0){
                if($response_format == 'ARRAY' ){
                    $deviceMsg  = ['status' => 'fail' , 'message' => 'Device Not Found In Our Database' ];
                    return ['settings' => $deviceMsg ];
                }else{
                    return response()->json(['status' => 'fail' , 'message' => 'Device Not Found In Our Database' ], 200);
                }
            }
            $vendorD      = $vendorDevice->devicesyncstatus($request);
            $offline_scan->sync_status = $vendorD; 
        }else{
            $offline_scan->sync_status = 0; 
        }

        $responseData = [
            'status' => 'success',
            'latest_version' => $latest_version,
            'force_fully_update' => $fore_fully_update ,
            'app_update_msg' => $app_update_msg ,
            'v_id' => $v_id,
            'color' =>  $colorSettings,
            'payment_type' => $paymentTypeSettings,
            'vendor_app_menu' => $vendorApp,
            'store' => [
                'name' => $store->name,
                'store_logo_flag' => $store_logo_flag,
                'store_logo' => $store_logo->path,
                'store_icon' => $store->store_icon,
                'store_list_bg' => $store_bg_logo->path,
                'location' => $store->location,
                'store_logo_link' => env('ADMIN_URL')
            ],
            'toolbar' => $toolbar,
            'feedback' => $feedback,
            'print' => $print,
            'invoice' => $invoice,
            'barcode' => $barcode,
            'opening_balance_status' => $vendorSett->opening_balance_flag($request),
            'optimize_flow' => $optimize_flow,
            'cart_avail_offer' => $vendorS->getCartAvailSetting($sParams),
            'scan_screen' => [
                'cart_qty_total_flag' =>  $vendorS->getScanScreenCartQtyTotalSetting($sParams),
                'total_flag' => $vendorS->getScanScreenTotalSetting($sParams),
                'offline_scan' => $vendorS->getScanScreenOffilneSetting($sParams),
                'offline_scan' => $offline_scan
            ],
            'cart' => [
                'price_override' => $vendorS->getCartPriceOverrideSetting($sParams),
                'avail_offer' => $vendorS->getCartAvailSetting($sParams),
                'r_price_flag' => $vendorS->getCartRPriceSetting($sParams),
                's_price_flag' => $vendorS->getCartsPriceSetting($sParams),
                'total_flag' => $vendorS->getCartTotalSetting($sParams),
                'saving_flag' => $vendorS->getCartSavingSetting($sParams),
                'sub_total_flag' => $vendorS->getCartSubTotalSetting($sParams),
                'tax_total_flag' => $vendorS->getCartTaxTotalSetting($sParams),
                'carry_bag_total_flag' => $vendorS->getCartCarrayBagTotalSetting($sParams),
                'carry_bag'          => $vendorS->getCartCarrayBagSetting($sParams),
                'voucher_total_flag' => $vendorS->getCartVoucherTotalSetting($sParams)
            ],
            'order' => [
                'history' => [
                    'total_flag' =>$vendorS->getOrderHistoryTotalSetting($sParams)
                ],
                'details' => [
                    'r_price_flag'      => $vendorS->getOrderDetailRPriceSetting($sParams),
                    's_price_flag'      => $vendorS->getOrderDetailSPriceSetting($sParams),
                    'total_flag'        => $vendorS->getOrderDetailTotalSetting($sParams),
                    'saving_flag'       => $vendorS->getOrderDetailSavingSetting($sParams),
                    'sub_total_flag'    => $vendorS->getOrderDetailSubTotalSetting($sParams),
                    'tax_total_flag'    => $vendorS->getOrderDetailTaxTotalSetting($sParams),
                    'carry_bag_total_flag' => $vendorS->getOrderDetailCarrayBagTotalSetting($sParams),
                    'voucher_total_flag' => $vendorS->getOrderDetailVoucherTotalSetting($sParams),
                    'invoice_button_flag'=> $vendorS->getInvoiceButton($sParams)
                ]
            ],

            'vendor_login_details' => $vendor_login_details
        ];

        if($response_format == 'ARRAY' ){

            return ['settings' => $responseData ];
        }else{

            return response()->json(['settings' => $responseData , 'status' => 'success' ], 200);

        }
    
    }
}
