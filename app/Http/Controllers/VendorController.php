<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Vendor;
use App\VendorDetails;
use App\Country;
use App\DeviceStorage;
use App\DeviceseVendorUser;
use DB;
use Illuminate\Support\Facades\Hash;
use Auth;
use App\Order;
use App\VendorAuth;
use App\Cart;
use App\User;
use App\Address;
use App\Payment;
use App\LoginSession;
use App\VendorImage;
use App\CustomerGroup;
use App\CustomerGroupMapping;
use Event;
use App\Events\Authlog;
use App\Http\Controllers\LoyaltyController;
use App\Events\Loyalty;
use App\LoyaltyCustomer;
use App\Carry;
use App\EventLog;
use App\SettlementSession;
use App\Vendor\VendorRole;
use App\Vendor\VendorRoleUserMapping;
use App\CartDiscount;
use App\Organisation;
use App\Store;
use App\PineLabDevice;
use App\StoreSettings;
use App\CashRegister;
use App\DepRfdTrans;
use App\Model\GiftVoucher\GiftVoucherCartDetails;



class VendorController extends Controller
{
    public function __construct()
    {
     $this->defaultCountry  = 'IN';   

     $this->middleware('auth', ['except' => ['logout','register_mobile','login']]);  
    }

    public function get_vendor_details(Request $request)
    {
        $vendor_random = $request->vendor_random;
        $vendor_check = DB::table('vendor')->where('vendor_code', $vendor_random)->first();
        if (empty(@$vendor_check)) {
            return response()->json(['status' => 'fail', 'message' => 'Vendor ID Not Found'], 200);
        }
        $store_check = DB::table('stores')->where('v_id', $vendor_check->id)->first();
        if (empty(@$store_check)) {
            return response()->json(['status' => 'fail', 'message' => 'Vendor ID / Strore ID Mismatch'], 200);
        }

        // return response()->json([ 'status' => 'verify_details', 'message' => 'Vendor details', 'v_id' =>$vendor_check->id  , 'vendor_name' => $vendor_check->company_name , 'store_name' => $store_check->name, 'store_icon' => $store_check->store_icon, 'store_logo' => $store_check->store_logo, 'store_location' => $store_check->location, 'store_logo_link' => store_logo_link(), 'store_list_bg' => $store_check->store_list_bg ], 200);
        return response()->json(['status' => 'verify_details', 'message' => 'Vendor details', 'v_id' => $vendor_check->id, 'vendor_name' => $vendor_check->name, 'store_name' => $store_check->name, 'store_icon' => $store_check->store_icon, 'store_logo' => $store_check->store_logo, 'store_location' => $store_check->location, 'store_logo_link' => store_logo_link(), 'store_list_bg' => $store_check->store_list_bg], 200);
    }

    public function get_store_data(Request $request)
    {

        $v_id = $request->v_id;
        $store_random = $request->store_random;


        $store_check = DB::table('stores')->where('v_id', $v_id)->where('store_random', $store_random)->first();

        if (empty($store_check)) {
            return response()->json(['status' => 'vendor_store_mismatch', 'message' => 'Vendor ID / Strore ID Mismatch'], 404);
        }

        return response()->json(['status' => 'store_details', 'message' => 'Store details', 'v_id' => $v_id, 'store_id' => $store_check->store_id, 'store_name' => $store_check->name, 'store_icon' => $store_check->store_icon, 'store_logo' => $store_check->store_logo, 'store_location' => $store_check->location, 'store_logo_link' => store_logo_link()], 200);
    }

    public function register_mobile(Request $request)
    {
        // SMS API
        //dd($request->all());
        $username = "roxfortgroup@gmail.com";
        $hash = "bb7a4a8a91634f50df712fdf5c11ef6745ea735b85afc9443d23ec9ae3e8b711";
        $test = "0";
        $sender = "MZWING";
        $mobile = $request->mobile;
        $trans_from = $request->trans_from;
        $udidtoken   = $request->udidtoken;
        $check_mobile_exists = Vendor::where('mobile', $mobile)->count();

        if (!empty($check_mobile_exists)) {

            $existMobile = Vendor::where('mobile', $mobile)->first();
            if($existMobile){
             $organisation = Organisation::find($existMobile->v_id);
                if($organisation->db_type == 'MULTITON'){
                    //dynamicConnection($organisation->db_name);
                $connPrm    =array('host'=> $organisation->connection->host,'port'=>$organisation->connection->port,'username'=>$organisation->connection->username,'password' =>  $organisation->connection->password,'db_name'=>$organisation->db_name);
                dynamicConnectionNew($connPrm);
                }
            }

            // Only Cashier can login
            $check_user = Vendor::where('mobile', $mobile)->first();
            $checkCashier = false;
            $check_user->roles->filter(function ($item) use (&$checkCashier) {
                // dd($item->role);
                if ($item->role->code == 'cashier') {
                    $checkCashier = true;
                }
            });

            if ($checkCashier === false) {
                return response()->json(['status' => 'fail', 'message' => 'User not Found'], 200);
            }

            $check_mobile_active = Vendor::where('mobile', $mobile)->where('mobile_active', 0)->count();
            if (!empty($check_mobile_active)) {
                $check_user = Vendor::where('mobile', $mobile)->first();

                if ($check_user->mobile_active == '1') {
                    if ($check_user->status == 0 || $check_user->approved_by_store == 0) {
                        return response()->json(['status' => 'account_not_active', 'message' => 'Your account is not activated'], 200);
                    }
                }
                $otp = rand(1111, 9999);
                $vendor_otp_update = Vendor::where('mobile', $mobile)->where('mobile_active', 0)->first();
                //$vendor_otp_update = Vendor::where('mobile', $mobile)->first();
                if ($vendor_otp_update->type == 'guard' || $vendor_otp_update->type == 'supervisor') {
                    return response()->json(['status' => 'account_not_active', 'message' => 'You are not authorized to login'], 200);
                }

                // Check Mobile Not Exists
                if ($trans_from == 'CLOUD_TAB_WEB' || $trans_from == 'ANDROID_VENDOR') {
                    return response()->json(['status' => 'm_not_found', 'message' => 'User not Found'], 200);
                }

                $vendor_otp_update->otp = $otp;
                $vendor_otp_update->save();
                $numbers = "91" . $mobile;
                $message = "Welcome to ZWING your otp is " . $otp;
                $message = urlencode($message);
                $data = "username=" . $username . "&hash=" . $hash . "&message=" . $message . "&sender=" . $sender . "&numbers=" . $numbers . "&test=" . $test;
                $ch = curl_init('http://api.textlocal.in/send/?');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $result = curl_exec($ch);
                curl_close($ch);

                $vendor['id'] = $vendor_otp_update->id;
                $vendor['mobile'] = $vendor_otp_update->mobile;

                return response()->json(['status' => 'otp_sent', 'message' => 'OTP Send Successfully', 'data' => $vendor], 200);
            } else {
                $account_active = Vendor::where('mobile', $mobile)->where('status', 1)->first();

                if (!$account_active) {
                    return response()->json(['status' => 'account_not_active', 'message' => 'Your account is not activated'], 200);
                }

                if ($account_active->type == 'guard' || $account_active->type == 'supervisor') {
                    return response()->json(['status' => 'account_not_active', 'message' => 'You are not authorized to login'], 200);
                }

                $vendor_api_token = Vendor::
                    // ->join('user_store_mapping', 'user_store_mapping.user_id', '=','vendor_auth.id')
                where('mobile', $mobile)->where('mobile_active', 1)->first();

                $role = VendorRoleUserMapping::select('role_id')->where('user_id', $vendor_api_token->vu_id)->first();
                $sParams = ['v_id' => $vendor_api_token->v_id, 'store_id' => $vendor_api_token->store_id, 'user_id' => $vendor_api_token->vu_id, 'role_id' => $role->role_id, 'trans_from' => $trans_from,'udidtoken'=>$udidtoken];
                if ($vendor_api_token->v_id > 0 && $vendor_api_token->store_id > 0) {
                    $vendor_api_token = Vendor::
                        // ->join('user_store_mapping', 'user_store_mapping.user_id', '=','vendor_auth.id')
                    where('mobile', $mobile)->where('mobile_active', 1)->first();
                    // dd($vendor_api_token->vendor_id);
                    $errorStatus = '';
                    if ($vendor_api_token->vendor_id > 0 && $vendor_api_token->store_id > 0) {
                        $vendorS = new VendorSettingController;
                        $userLogin = $vendorS->getVendorUserLogin($sParams);
                        if ($userLogin->device_specific) {
                            if($vendor_api_token->udid){
                            if ($request->udid != $vendor_api_token->udid) {
                                return response()->json(['status' => 'fail', 'message' => 'You are Not login with Correct device'], 200);
                            }
                        }
                        }
                    }
                } else {
                    $errorStatus = 'STORE_NOT_SET';
                }

                // Check already user login or not

                $checkUserLogin = EventLog::where('staff_id', $vendor_api_token->vu_id)->where('api_token', $vendor_api_token->api_token)->latest()->first();
                // DB::connection()
                // dd($checkUserLogin);
                if (!empty($checkUserLogin)) {
                    // if ($checkUserLogin->type == 'Login') {
                    //     if ($checkUserLogin->trans_from != $request->trans_from) {
                    //         // return response()->json([ 'status' => 'fail', 'message' => 'User already login from other device' ], 422);
                    //     } else {
                    //         // $new_api_token = str_random(50);
                    //         // DB::connection('mongodb')->collection('event_log')->where('oid', $checkUserLogin->oid)
                    //         //                         ->update(['api_token' => $new_api_token]);
                    //         // $vendor_api_token->api_token = $new_api_token;
                    //         // $vendor_api_token->save();
                    //     }
                    // }
                } else {
                    $vendor_api_token->api_token = str_random(50);
                    $vendor_api_token->save();
                }



                // $id = $vendor_api_token->id;
                // return response()->json(['status' => 'login_redirect', 'message' => 'Login Successfully', 'data' => [ 'mobile' => $vendor_api_token->mobile, 'db_name' => DB::connection()->getDatabaseName(), 'vu_id' => $vendor_api_token->id, 'v_id' => $vendor_api_token->v_id ] ], 200);
                $id = $vendor_api_token->vu_id;
                return response()->json(['status' => 'login_redirect', 'error_status' => $errorStatus, 'message' => 'Login Successfully', 'data' => ['mobile' => $vendor_api_token->mobile, 'db_name' => DB::connection()->getDatabaseName(), 'vu_id' => $vendor_api_token->vu_id, 'v_id' => $vendor_api_token->vendor_id]], 200);
            }
            return response()->json(['status' => 'mobile_already', 'message' => 'Mobile Already Exists'], 200);
        }

        // Check Mobile Not Exists
        if ($trans_from == 'CLOUD_TAB_WEB' || $trans_from == 'ANDROID_VENDOR') {
            return response()->json(['status' => 'm_not_found', 'message' => 'User not Found'], 200);
        }

        $otp = rand(1111, 9999);
        $vendor = Vendor::create(['mobile' => $mobile, 'otp' => $otp]);
        $numbers = "91" . $mobile;
        $message = "Welcome to ZWING your otp is " . $otp;
        $message = urlencode($message);
        $data = "username=" . $username . "&hash=" . $hash . "&message=" . $message . "&sender=" . $sender . "&numbers=" . $numbers . "&test=" . $test;
        $ch = curl_init('http://api.textlocal.in/send/?');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        //$vendor = Vendor::where('mobile',$mobile)->first();
        //dd($vendor);
        $new_data = [];
        // $data .="&id=".$hash."&mobile=".$message;
        $new_data['id'] = $vendor->id;
        $new_data['mobile'] = $vendor->mobile;

        return response()->json(['status' => 'otp_sent', 'message' => 'OTP Send Successfully', 'data' => $new_data], 200);
    }

    public function verify_mobile(Request $request)
    {
        $otp = $request->otp;
        $mobile = $request->mobile;
        $id = $request->vu_id;

        $opt_verify = Vendor::where('id', $id)->where('mobile', $mobile)->where('otp', $otp)->count();

        if (empty($opt_verify)) {
            return response()->json(['status' => 'incorrect_otp', 'message' => 'Incorrect OTP, Please Check or Resend'], 200);
        }
        $opt_verify = Vendor::where('id', $id)->where('mobile', $mobile)->where('otp', $otp)->update(['mobile_active' => 1]);

        return response()->json(['status' => 'otp_verified', 'message' => 'OTP Verified Successfully', 'mobile' => $mobile, 'id' => $id], 200);
    }

    public function verify_password(Request $request)
    {
        $pin = $request->pin;
        $mobile = $request->mobile;
        $vu_id = $request->vu_id;

        $vendor = Vendor::where('id', $vu_id)->where('mobile', $mobile)->first();
        if ($vendor) {
            if (Hash::check($pin, $vendor->password)) {
                return response()->json(['status' => 'pin_verified', 'message' => 'Pin Verified Successfully'], 200);
            } else {
                return response()->json(['status' => 'incorrect_pin', 'message' => 'Pin is not correct'], 200);
            }
        } else {
            return response()->json(['status' => 'invalid_credentials', 'message' => 'Credentials does not match'], 200);
        }
    }

    public function setup_pin(Request $request)
    {
        $id = $request->vu_id;
        $mobile = $request->mobile;
        $pin = $request->pin;
        $pin_update = Vendor::where('id', $id)->where('mobile', $mobile)->update(['password' => app('hash')->make($pin), 'vendor_user_random' => rand(100000, 999999)]);
        return response()->json(['status' => 'success', 'message' => 'PIN Set Successfully', 'mobile' => $mobile, 'id' => $id], 200);
    }

    public function get_store_details(Request $request)
    {
        $id = $request->vu_id;
        $mobile = $request->mobile;
        $v_id = $request->v_id;
        $store_id = $request->store_id;

        $vendor_check = DB::table('vendor_auth')->where('id', $v_id)->count();
        $store_check = DB::table('stores')->where('store_id', $store_id)->where('v_id', $v_id)->first();

        if (empty($vendor_check)) {
            return response()->json(['status' => 'vendor_not_found', 'message' => 'Vendor ID Not Found'], 404);
        } else if (empty($store_check)) {
            return response()->json(['status' => 'vendor_store_mismatch', 'message' => 'Vendor ID / Strore ID Mismatch'], 404);
        }

        return response()->json(['status' => 'verify_vendor', 'message' => 'Vendor ID / Strore ID Match', 'mobile'  => $mobile, 'id' => $id, 'store_name' => $store_check->name, 'store_icon' => $store_check->store_icon, 'store_logo' => $store_check->store_logo, 'store_location' => $store_check->location, 'store_logo_link' => store_logo_link()], 200);
    }

    public function verify_vendor(Request $request)
    {
        $id = $request->vu_id;
        $mobile = $request->mobile;
        $v_id = $request->v_id;
        $store_id = $request->store_id;

        $vendor_check = DB::table('vendor_auth')->where('id', $v_id)->count();
        $store_check = DB::table('stores')->where('store_id', $store_id)->where('v_id', $v_id)->first();

        if (empty($vendor_check)) {
            return response()->json(['status' => 'vendor_not_found', 'message' => 'Vendor ID Not Found'], 404);
        } else if (empty($store_check)) {
            return response()->json(['status' => 'vendor_store_mismatch', 'message' => 'Vendor ID / Strore ID Mismatch'], 404);
        }

        Vendor::where('id', $id)->where('mobile', $mobile)->update(['v_id' => $v_id, 'store_id' => $store_id]);

        return response()->json(['status' => 'vendor_store_match', 'message' => 'Vendor ID / Strore ID Match', 'mobile'  => $mobile, 'id' => $id, 'store_name' => $store_check->name, 'store_icon' => $store_check->store_icon, 'store_logo' => $store_check->store_logo, 'store_location' => $store_check->location, 'store_logo_link' => store_logo_link()], 200);
    }

    public function register_vendor_details(Request $request)
    {
        $id = $request->vu_id;
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

        $vendor_user = Vendor::where('id', $id)->where('mobile', $mobile)->first();

        $check_email_exists = $vendor_user->where('email', $email)->count();
        if (!empty($check_email_exists)) {
            return response()->json(['status' => 'email_already', 'message' => 'Email Already Exits'], 200);
        }

        if ($vendor_user->v_id > 0) {
            $vendorS = new VendorSettingController;
            $userLogin = $vendorS->getVendorUserLogin(['v_id' => $vendor_user->v_id, 'trans_from' => $trans_from]);

            if ($userLogin->device_specific) {
                $udid = Vendor::where('v_id', $vendor_user->v_id)->where('udid', $request->udid)->first();
                if ($udid) {
                    return response()->json(['status' => 'fail', 'message' => 'Another user is already registered with this device'], 200);
                }
            }
        }


        $vendor_details = Vendor::where('id', $id)->where('mobile', $mobile)->update(['first_name' => $first_name, 'last_name' => $last_name, 'gender' => $gender, 'dob' => $dob, 'email' => $email, 'device_name' => $device_name, 'os_name' => $os_name, 'os_version' => $os_version, 'udid' => $udid, 'imei' => $imei, 'latitude' => $latitude, 'longitude' => $longitude, 'device_model_number' => $device_model_number, 'status' => '1', 'api_token' => $api_token]);


        return response()->json(['status' => 'registed', 'message' => 'Registed Successfully', 'mobile' => $mobile, 'id' => $id, 'api_token' => $api_token, 'active_status' => 1,   'approved_by_store' =>  $vendor_user->approved_by_store], 200);
    }

    public function login(Request $request)
    {
        //dd($request->all());
        $mobile = $request->mobile;
        $pin = $request->pin;
        $trans_from = $request->trans_from;
        $udidtoken  = $request->udidtoken;
        $user_id = '';
        
        
        //$store_id = $request->store_id;
        $forcelogin = 0;

        $vendor = Vendor::where('mobile', $mobile)->first();
         if($vendor){
         $organisation = Organisation::find($vendor->v_id);
            if($organisation->db_type == 'MULTITON'){
                dynamicConnection($organisation->db_name);
                $vendor = Vendor::where('mobile', $mobile)->first();
            }
        }


        if ($request->has('vu_id')) {
            $user_id = $request->vu_id;
        }else{
            $user_id = $vendor->vu_id;
        }

        if ($request->has('forcelogin')) {
            $forcelogin = $request->forcelogin;
        }
        $store_id = $vendor->store_id;
        
        $old_api_token = $vendor->api_token;
        

        if($vendor){
           $check =  Store::find($vendor->store_id);
           if($check->api_status != 1){
                return response()->json(['status' => 'fail', 'message' => 'Pos is not active.First activate the pos status to continue billing'], 200);
           }
        }

        if (!$vendor) {
            return response()->json(['status' => 'm_not_found', 'message' => 'Mobile not Found'], 404);
        }

        $account_active = Vendor::where('mobile', $mobile)->where('status', 1)->first();
        if (!$account_active) {
            return response()->json(['status' => 'account_not_active', 'message' => 'Your account is not activated'], 200);
        }

        $approveby_store = Vendor::where('mobile', $mobile)->where('approved_by_store', 1)->first();
        if(!$approveby_store){
            return response()->json(['status' => 'account_not_active', 'message' => 'Your account is not approved by store'], 200);
        }

        if ($account_active->type == 'guard' || $account_active->type == 'supervisor') {
            return response()->json(['status' => 'account_not_active', 'message' => 'You are not authorized to login'], 200);
        }

        // echo 
        $licence =CashRegister::where('udidtoken',$udidtoken)
                       ->where('store_id',$store_id)->first();


      if(!$licence){  
         return response()->json(['status' => 'fail', 'message' => 'This device is already registered with another store license key.'], 200);

      }
        if (Hash::check($pin, $vendor->password)) {

            $api_token = str_random(50);

            // Check user already login or not

            $checkUserLogin = EventLog::where('staff_id', $vendor->vu_id)->where('api_token', $old_api_token)->latest()->first();

            if ($forcelogin == 0) {

                // dd($checkUserLogin);
                if (!empty($checkUserLogin)) {
                    if ($checkUserLogin->type == 'Login') {
                        if ($checkUserLogin->trans_from == $request->trans_from) {
                            DB::connection('mongodb')->collection('event_log')->where('oid', $checkUserLogin->oid)
                            ->update(['api_token' => $api_token]);
                        } else {
                            return response()->json(['status' => 'warning', 'message' => 'The user you are trying to log-in with\nis already logged-in from another device.\n\nBy tapping ‘Continue’ you’ll be logged-out from the other device and the ongoing session will be closed.', 'action' => ['text' => 'Continue', 'key' => ['forcelogin' => 1]]], 200);
                        }
                    }
                } else {
                    // $result = event(new Authlog($userdata));  // Event capture for vendor login
                }
            } else if ($forcelogin == 1) {

                $lgoRequest = new Request();
                $lgoRequest->merge(['api_token' => $old_api_token, 'vu_id' => $vendor->vu_id, 'mobile' => $vendor->mobile, 'forcelogout' => $forcelogin, 'trans_from' => $checkUserLogin->trans_from]);
                $this->logout($lgoRequest);
            }


            $vendor->update(['api_token' => $api_token, 'latitude' => $request->latitude, 'longitude' => $request->longitude]);

            $vSetting['settings'] = ['status' => 'fail'];
            if ($vendor->v_id > 0 && $vendor->store_id > 0) {
                $store_check = DB::table('stores')->where('store_id', $vendor->store_id, 'store_id')->first();
                if (strpos($store_check->display_status, ':' . $trans_from . ':') === false) {
                    return response()->json(['status' => 'fail', 'message' => 'This store is not enabled for this application '], 200);
                }
                $vendorS = new VendorSettingController;
                // Getting user id from specific settings
                $role = VendorRoleUserMapping::select('role_id')->where('user_id', $vendor->vu_id)->first();

                $userLogin = $vendorS->getVendorUserLogin(['v_id' => $vendor->v_id, 'trans_from' => $trans_from, 'user_id' => $user_id, 'store_id' => $store_id, 'role_id' => $role->role_id,'udidtoken'=>$udidtoken]);
                if ($userLogin->device_specific) {
                    if ($request->udid != $vendor->udid) {
                        return response()->json(['status' => 'fail', 'message' => 'You are login with Correct device'], 200);
                    }
                }
                
                $sessionCompulsory = $vendorS->getSessionCompulsorySettingFunction(['v_id' => $vendor->v_id, 'trans_from' => $trans_from, 'user_id' => $user_id, 'store_id' => $store_id, 'role_id' => $role->role_id,'udidtoken'=>$udidtoken]);
                $sessionCompulsoryStatus = '';
                if(isset($sessionCompulsory->status)){
                    $sessionCompulsoryStatus = $sessionCompulsory->status;
                }else{
                    $sessionCompulsoryStatus = '';
                }
                if(isset($sessionCompulsory) && $sessionCompulsoryStatus=='1'){
                  
                 $previousterminal =SettlementSession::where(['v_id' => $vendor->v_id, 'vu_id' => $user_id, 'store_id' => $store_id])->orderBy('id','DESC')->first();
                 if(isset($previousterminal) && $previousterminal->closing_time==Null){
                  if($licence->id!=$previousterminal->cash_register_id){
                     
                     // return response()->json(['status' => 'fail', 'message' => 'You have an active session on another terminal. Please close that session first to proceed.'], 200);

                      $request->request->add(['session_force_close'=>1]);
                  }

                 }
                  //dd("ok");

                }

                $request->request->add(['v_id' => $vendor->v_id, 'store_id' => $vendor->store_id, 'api_token' => $vendor->api_token, 'vu_id' => $vendor->vu_id, 'response_format' => 'ARRAY','udidtoken'=>$udidtoken, 'manufacturer_name' => 'SUNMI%7CT1-G']);
                $vSetting =  $this->get_settings($request);
    
            }
            if ($request->has('udid')) {
                $device_id = $request->get('udid');
            } else {
                $device_id = '';
            }


            $loginSession = new LoginSession;
            $loginSession->v_id = $vendor->v_id;
            $loginSession->store_id = $vendor->store_id;
            $loginSession->vu_id = $vendor->vu_id;
            $loginSession->api_token = $vendor->api_token;
            $loginSession->longitude = $vendor->longitude;
            $loginSession->latitude = $vendor->latitude;

            if ($request->has('latitude') && $request->has('longitude')) {
                // $mapLoc = new MapLocationController;
                // $response = $mapLoc->addressBylatLongArray($vendor->longitude , $vendor->latitude);
                // if(!empty($response) && $response['status'] !='fail'){
                //     $loginSession->locality  = $response['data']['locality'];
                //     $loginSession->address   = $response['data']['address'];
                // }
            }

            $loginSession->device_id = $device_id;
            $loginSession->save();

            /*Event log data array*/
            $store_name = Store::find($vendor->store_id)->name;
            $staff_name = $vendor->first_name.' '.$vendor->last_name;
            $userdata = array('store_id' => $vendor->store_id,'store_name'=>$store_name, 'v_id' => $vendor->v_id, 'staff_id' => $vendor->id, 'staff_name'=>$staff_name,'type' => 'Login', 'ip_address' => $_SERVER['REMOTE_ADDR'], 'latitude' => $vendor->latitude, 'longitude' => $vendor->longitude, 'udid' => $device_id, 'api_token' => $vendor->api_token, 'trans_from' => $request->trans_from);
            $vendor->vu_id = (string) $vendor->vu_id;
            $vendor->vendor_id  = (isset($vendor->v_id)) ? $vendor->v_id : '';
            $result = event(new Authlog($userdata));  // Event capture for vendor login

            //Event::dispatch(new Authlog($eventLogData));
            //$vSetting['settings'] = [ 'db_name' => DB::connection()->getDatabaseName() ];
            return response()->json([
                'status' => 'login_redirect', 'message' => 'Login successfully', 'data' => $vendor,
                'full_name' => $vendor->first_name . ' ' . $vendor->last_name,
                'email' => (isset($vendor->email)) ? $vendor->email : '',
                'v_id' => (isset($vendor->v_id)) ? $vendor->v_id : '',
                'store_id' => (isset($vendor->store_id)) ? $vendor->store_id : '',
                'store_name' => (isset($store_check->name)) ? $store_check->name : '',
                'store_icon' => (isset($store_check->store_icon)) ? $store_check->store_icon : '',
                'store_logo' => (isset($store_check->store_logo)) ? $store_check->store_logo : '',
                'store_location' => (isset($store_check->location)) ? $store_check->location : '',
                'store_logo_link' => store_logo_link(),
                'store_list_bg' => (isset($store_check->store_list_bg)) ? $store_check->store_list_bg : '',
                // 'db_name'   => DB::connection()->getDatabaseName(),
                'settings' => $vSetting['settings']
            ], 200);
        }

        return response()->json(['status' => 'invalid_credentials', 'message' => 'Invalid Credentials'], 200);
    }

    public function logout(Request $request)
    {
        // dd($request->all());
        $api_token = $request->api_token;
        $id = $request->vu_id;
        $mobile = $request->mobile;
        $trans_from = $request->trans_from;
        $forcelogout = 0;
        if ($request->has('forcelogout')) {
            $forcelogout = $request->forcelogout;
        }
        $vendor = Vendor::where('api_token', $api_token)->where('id', $id)->where('mobile', $mobile)->first();
        // dd($vendor);
        if (!$vendor) {
            return response()->json(['status' => 'not_logged_in', 'message' => 'Not Logged in'], 200);
        }
        $vendor->api_token = null;

        $vendor->save();

        if ($request->has('udid')) {
            $device_id = $request->get('udid');
        } else {
            $device_id = '';
        }
        
        /*Event log data array*/
        $store_name = Store::find($vendor->store_id)->name;
        $staff_name = $vendor->first_name.' '.$vendor->last_name;
        
        $userdata = array('store_id' => $vendor->store_id,'store_name'=>$store_name, 'v_id' => $vendor->v_id, 'staff_id' => $vendor->id, 'staff_name'=>$staff_name,'type' => 'Login', 'ip_address' => $_SERVER['REMOTE_ADDR'], 'latitude' => $vendor->latitude, 'longitude' => $vendor->longitude, 'udid' => $device_id, 'api_token' => $vendor->api_token, 'trans_from' => $request->trans_from);

        $result = event(new Authlog($userdata));  //Event capture for vendor Logout

        $loginSession = LoginSession::where('vu_id', (int) $id)->orderBy('_id', 'desc')->first();
        $LoginSess = new LoginSession;
        $getCollectionCon = $LoginSess->getConnectionName();
        $loginSession->setConnection($getCollectionCon);
        $loginSession->updated_at = date('Y-m-d H:i:s');
        $loginSession->save();
        if ($forcelogout == 0) {
            return response()->json(['status' => 'logout_redirect', 'message' => 'Logged Out Successfully'], 200);
        }
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

        if (empty($check_mobile_exists)) {
            return response()->json(['status' => 'mobile_not_found', 'message' => 'Mobile Number Not Found'], 200);
        }

        $otp = rand(1111, 9999);
        $vendor_otp_update = Vendor::where('mobile', $mobile)->where('mobile_active', 1)->update(['otp' => $otp]);
        $vendor = Vendor::where('mobile', $mobile)->where('mobile_active', 1)->first();
        //dd($vendor);
        $numbers = "91" . $mobile;
        $message = "Welcome to ZWING your otp is " . $otp;
        $message = urlencode($message);
        $data = "username=" . $username . "&hash=" . $hash . "&message=" . $message . "&sender=" . $sender . "&numbers=" . $numbers . "&test=" . $test;
        $ch = curl_init('http://api.textlocal.in/send/?');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        //$api_null = Vendor::where('mobile', $mobile)->update(['api_token' => null,'status' => 0]);
        return response()->json(['status' => 'otp_sent', 'message' => 'OTP Send Successfully', 'mobile' => $mobile, 'id' => $vendor->id], 200);
    }

    public function forgot_pin_verify(Request $request)
    {
        $id = $request->vu_id;
        $mobile = $request->mobile;
        $otp = $request->otp;

        $opt_verify = Vendor::where('id', $id)->where('mobile', $mobile)->where('otp', $otp)->count();

        if (empty($opt_verify)) {
            return response()->json(['status' => 'incorrect_otp', 'message' => 'Incorrect OTP, Please Check or Resend'], 200);
        } else { }

        return response()->json(['status' => 'otp_verified', 'message' => 'OTP Verified Successfully', 'mobile' => $mobile, 'id' => $id], 200);
    }

    public function change_pin(Request $request)
    {
        //echo "test";exit();
        $id = $request->vu_id;
        $mobile = $request->mobile;
        $pin = $request->pin;
        $v_id = $request->v_id;

       // echo config('database.default');die;
        //JobdynamicConnection($v_id);


        $change_pin = Vendor::where('mobile', $mobile)->where('id', $id)->where('mobile_active', 1)->update(['password' => app('hash')->make($pin)]);

        if(config('database.default') == 'dynamic'){
        $change_pin_mysql = DB::connection('mysql')->table('vendor_auth')->where('mobile', $mobile)->where('id', $id)->where('mobile_active', 1)->update(['password' => app('hash')->make($pin)]);
        }

        // dd($change_pin);
        $vendor = Vendor::select('id', 'first_name', 'last_name', 'email', 'mobile', 'api_token', 'password', 'v_id', 'store_id', 'status', 'approved_by_store', 'first_name', 'last_name', 'gender')->where('mobile', $mobile)->where('id', $id)->first();

        $vSetting['settings'] = ['status' => 'fail'];
        // if($vendor->v_id > 0 && $vendor->store_id > 0 ){

        //     $request->request->add(['v_id' => $vendor->v_id, 'api_token' =>$vendor->api_token , 'id' =>$vendor->id ,'response_format' => 'ARRAY' ,'store_id'=>$vendor->store_id ]);

        if ($vendor->vendor_id > 0 && $vendor->store_id > 0) {
            $store_check = DB::table('stores')->where('store_id', $vendor->store_id, 'store_id')->first();

            $request->request->add(['v_id' => $vendor->vendor_id, 'api_token' => $vendor->api_token, 'vu_id' => $vendor->vu_id, 'response_format' => 'ARRAY', 'store_id' => $vendor->store_id]);

            $vSetting =  $this->get_settings($request);
            // dd($vSetting);
        }
        return response()->json([
            'status' => 'pin_change', 'message' => 'Pin changed successfully',
            'connection'=>  config('database.default'),
            'data' => $vendor,
            'full_name' => $vendor->first_name . ' ' . $vendor->last_name,
            'email' => (isset($vendor->email)) ? $vendor->email : '',
            'gender' => (isset($vendor->gender)) ? $vendor->gender : '',
            'v_id' => (isset($vendor->v_id)) ? $vendor->v_id : '',
            'store_id' => (isset($vendor->store_id)) ? $vendor->store_id : '',
            'store_name' => (isset($store_check->name)) ? $store_check->name : '',
            'store_icon' => (isset($store_check->store_icon)) ? $store_check->store_icon : '',
            'store_logo' => (isset($store_check->store_logo)) ? $store_check->store_logo : '',
            'store_location' => (isset($store_check->location)) ? $store_check->location : '',
            'store_logo_link' => store_logo_link(),
            'store_list_bg' => (isset($store_check->store_list_bg)) ? $store_check->store_list_bg : '',
            'approved_by_store' => (isset($vendor->approved_by_store)) ? $vendor->approved_by_store : '',
            'mobile' => $mobile,
            'id' => $id,
            'vu_id' => $id,
            'settings' => $vSetting['settings']
        ], 200);
    }

    public function profile(Request $request)
    {

        $vu_id = $request->vu_id;
        $mobile = $request->mobile;

        $vendor = Vendor::select('mobile', 'first_name', 'last_name', 'gender', 'dob', 'email', 'email_active')->where('id', $vu_id)->where('mobile', $mobile)->first();
        return response()->json(['status' => 'profile_data', 'message' => 'Profile Data', 'data' => $vendor], 200);
    }

    public function profile_update(Request $request)
    {
        $id = $request->vu_id;
        $mobile = $request->mobile;
        $first_name = $request->first_name;
        $last_name = $request->last_name;
        $gender = $request->gender;
        $email = $request->email;
        $dob = date('Y-m-d', strtotime($request->dob));
        $user_details = Vendor::where('id', $id)->where('mobile', $mobile)->update(['first_name' => $first_name, 'last_name' => $last_name, 'gender' => $gender, 'dob' => $dob, 'email' => $email]);
        return response()->json(['status' => 'profile_update', 'message' => 'Profile Updated Successfully', 'mobile' => $mobile, 'vu_id' => $id, 'api_token' => Vendor::find($id)->api_token], 200);
    }

    public function change_store(Request $request)
    {
        $id = $request->vu_id;
        $mobile = $request->mobile;
        $v_id = null;
        $store_random = $request->store_random;
        $trans_from = $request->trans_from;


        // $store_check = DB::table('stores')->where('v_id', $v_id)->where('store_code', $store_random)->first();


        $store_check = DB::table('stores')->where('store_code', $store_random);
        if ($request->has('v_id')) {
            $store_check = $store_check->where('v_id', $request->v_id)->first();
            $v_id = $request->v_id;
        } else {
            $store_check = $store_check->first();
            $v_id = $store_check->v_id;
        }


        if (empty($store_check)) {
            return response()->json(['status' => 'vendor_store_mismatch', 'message' => 'Vendor ID / Strore ID Mismatch'], 404);
        }

        $user_details = Vendor::where('id', $id)->where('mobile', $mobile)->update(['v_id' => $v_id, 'store_id' => $store_check->store_id, 'approved_by_store' => '0']);


        // $request->request->add(['v_id' => $v_id, 'api_token' => $request->api_token , 'id' =>$id ,'response_format' => 'ARRAY'  ]);

        $request->request->add(['v_id' => $v_id, 'api_token' => $request->api_token, 'vu_id' => $request->vu_id, 'response_format' => 'ARRAY', 'store_id' => $store_check->store_id]);

        if ($request->has('udid')) {
            $device_detail  = DeviceStorage::create(['udid' => $request->udid]);
            $device_user_map = DeviceVendorUser::create(['device_id' => $device_detail->id, 'id' => $id, 'v_id' => $v_id, 'store_id' => $store_check->store_id]);
        }

        $vSetting =  $this->get_settings($request);

        return response()->json(['status' => 'profile_update', 'message' => 'Store Changed Successfully', 'mobile' => $mobile, 'id' => $id, 'v_id' => $v_id, 'store_id' => $store_check->store_id, 'store_location' => (isset($store_check->location)) ? $store_check->location : '',  'api_token' => Vendor::find($id)->api_token, 'approved_by_store' => '0', 'settings' => $vSetting['settings']], 200);
    }

    public function order_details(Request $request)
    {
        $vendor = Auth::user();
        //dd($vendor);

        $v_id = $vendor->v_id;
        //$c_id = $request->c_id;
        $store_id = $vendor->store_id;
        $order_id = $request->order_id;

        $stores =  DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();
        $o_id = Order::where('order_id', $order_id)->where('v_id', $v_id)->where('store_id', $store_id)->first();
        if (!$o_id) {
            return response()->json(['status' => 'fail', 'message' => 'Unable to get the orders'], 200);
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


            $res = DB::table('cart_offers')->where('cart_id', $cart->cart_id)->first();
            $offer_data = json_decode($res->offers, true);

            foreach ($offer_data['pdata'] as $key => $value) {
                foreach ($value['tax'] as $nkey => $tax) {
                    if (isset($tax_details[$tax['tax_code']])) {
                        $tax_details[$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'];
                        $tax_details[$tax['tax_code']]['tax'] += $tax['tax'];
                    } else {
                        $tax_details[$tax['tax_code']] = $tax;
                    }
                }
            }

            $available_offer = [];
            foreach ($offer_data['available_offer'] as $key => $value) {

                $available_offer[] =  ['message' => $value];
            }
            $offer_data['available_offer'] = $available_offer;
            $applied_offer = [];
            foreach ($offer_data['applied_offer'] as $key => $value) {

                $applied_offer[] =  ['message' => $value];
            }
            $offer_data['applied_offer'] = $applied_offer;
            //dd($offer_data);

            //Counting the duplicate offers
            $tempOffers = $offer_data['applied_offer'];
            for ($i = 0; $i < count($offer_data['applied_offer']); $i++) {
                $apply_times = 1;
                $apply_key = 0;
                for ($j = $i + 1; $j < count($tempOffers); $j++) {

                    if (isset($offer_data['applied_offer'][$j]['message']) && $tempOffers[$i]['message'] == $offer_data['applied_offer'][$j]['message']) {
                        unset($offer_data['applied_offer'][$j]);
                        $apply_times++;
                        $apply_key = $j;
                    }
                }
                if ($apply_times > 1) {
                    $offer_data['applied_offer'][$i]['message'] = $offer_data['applied_offer'][$i]['message'] . ' - ' . $apply_times . ' times';
                }
            }
            $offer_data['available_offer'] = array_values($offer_data['available_offer']);
            $offer_data['applied_offer'] = array_values($offer_data['applied_offer']);

            $carr_bag_arr =  ['114903443', '114952448', '114974444'];
            $carry_bag_flag = in_array($cart->item_id, $carr_bag_arr);

            $product_data['carry_bag_flag'] = $carry_bag_flag;
            $product_data['p_id'] = (int) $cart->item_id;
            $product_data['category'] = '';
            $product_data['brand_name'] = '';
            $product_data['sub_categroy'] = '';
            $product_data['whishlist'] = 'No';
            $product_data['weight_flag'] = ($cart->weight_flag == 1) ? true : false;
            $product_data['p_name'] = $cart->item_name;
            $product_data['offer'] = (count($offer_data['applied_offer']) > 0) ? 'Yes' : 'No';
            $product_data['offer_data'] = ['applied_offers' => $offer_data['applied_offer'], 'available_offers' => $offer_data['available_offer']];
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
                'amount'        => $cart->total,
                'qty'           => $cart->qty,
                'tax_amount'    => $tax_amount,
                'delivery'      => $cart->delivery
            );
            //$tax_total = $tax_total +  $tax_amount ;
        }

        $bill_buster_discount = $o_id->bill_buster_discount;
        $saving = $discount + $bill_buster_discount;

        $bags = DB::table('user_carry_bags')->select('vendor_carry_bags.Name', 'user_carry_bags.Qty', 'vendor_carry_bags.BAG_ID')->selectRaw('user_carry_bags.Qty * vendor_carry_bags.Price as Price')->leftJoin('vendor_carry_bags', 'user_carry_bags.Bag_ID', '=', 'vendor_carry_bags.BAG_ID')->where('user_carry_bags.V_ID', $v_id)->where('user_carry_bags.Store_ID', $store_id)->where('user_carry_bags.User_ID', $c_id)->where('user_carry_bags.Order_ID', $order_num_id['o_id'])->get();
        $bprice = DB::table('user_carry_bags')->selectRaw('SUM(user_carry_bags.Qty * vendor_carry_bags.Price) as Price')->leftJoin('vendor_carry_bags', 'user_carry_bags.Bag_ID', '=', 'vendor_carry_bags.BAG_ID')->where('user_carry_bags.V_ID', $v_id)->where('user_carry_bags.Store_ID', $store_id)->where('user_carry_bags.User_ID', $c_id)->where('user_carry_bags.Order_ID', $order_num_id['o_id'])->first();
        // $cart_data['bags'] = $bags;

        if (empty($bprice->Price)) {
            $carry_bag_total = 0;
        } else {
            $carry_bag_total = $bprice->Price;
        }
        $store = DB::table('stores')->select('delivery')->where('store_id', $store_id)->where('v_id', $v_id)->first();
        //$total = (int)$sub_total + (int)$carry_bag_total;
        //$less = array_sum($saving) - (int)$sub_total;
        $address = (object) array();
        if ($o_id->address_id > 0) {
            $address = Address::where('c_id', $c_id)->where('deleted_status', 0)->where('id', $o_id->address_id)->first();
        }

        $paymentMethod = Payment::where('v_id', $o_id->v_id)->where('store_id', $o_id->store_id)->where('order_id', $o_id->order_id)->get()->pluck('method')->all();

        // return response()->json(['status' => 'order_details', 'message' => 'Order Details Details', 'data' => $cart_data, 'product_image_link' => product_image_link(), 'sub_total' => $sub_total, 'tax_total' => $tax_total, 'grand_total' => $sub_total + $carry_bag_total + $tax_total, 'date' => $o_id->date, 'time' => $o_id->time, 'bags' => $bags, 'carry_bag_total' => $carry_bag_total, 'delivered' => $store->delivery , 'address'=> $address  ],200);
        return response()->json([
            'status' => 'order_details', 'message' => 'Order Details Details',
            'mobile' => $o_id->user->mobile,
            'payment_method' =>  implode(',', $paymentMethod),
            'data' => $cart_data, 'product_image_link' => product_image_link(),
            //'offer_data' => $global_offer_data,
            'bags' => $bags,
            'carry_bag_total' => (format_number($carry_bag_total)) ? format_number($carry_bag_total) : '0.00',
            'sub_total' => (format_number($sub_total)) ? format_number($sub_total) : '0.00',
            'tax_total' => (format_number($tax_total)) ? format_number($tax_total) : '0.00',
            'tax_details' => $tax_details,
            'bill_buster_discount' => (format_number($bill_buster_discount)) ? format_number($bill_buster_discount) : '0.00',
            'discount' => (format_number($discount)) ? format_number($discount) : '0.00',
            //'grand_total' => (format_number($grand_total))?format_number($grand_total):'0.00', 
            'date' => $o_id->date,
            'time' => $o_id->time,
            'order_id' => $order_id,
            'total' => format_number($total),
            'cart_qty_total' => (string) $cart_qty_total,
            'saving' => (format_number($saving)) ? format_number($saving) : '0.00',
            'store_address' => $stores->address1 . ' ' . $stores->address2 . ' ' . $stores->state . ' - ' . $stores->pincode,
            'store_timings' => $stores->opening_time . ' ' . $stores->closing_time,
            'delivered' => $store->delivery,
            'address' => $address,
            'verify_status' => $o_id->verify_status
        ], 200);
    }

    public function verify_order(Request $request)
    {

        $vendor = Auth::user();

        $id = $request->vu_id;
        $v_id = $vendor->v_id;
        $store_id = $vendor->store_id;
        $order_id = $request->order_id;
        $order  = Order::where('order_id', $order_id)
        ->where('v_id', $v_id)
        ->where('store_id', $store_id)->first();
        if ($order) {
            if ($order->verify_status == 1) {
                return response()->json(['status' => 'order_verified', 'message' => 'Order has been already verified by Staff'], 200);
            } else {


                $order->verify_status = '1';
                $order->verified_by = $id;
                $order->save();
                //$order  = $order->update(['verify_status' => '1' , 'verified_by' => $id]);

                return  $this->order_details($request);
                // return response()->json(['status' => 'order_verified', 'message' => 'Order has been verified' ],200);
            }
        } else {

            return response()->json(['status' => 'fail', 'message' => 'Unable to verified this order'], 200);
        }
    }

    public function verify_order_by_guard(Request $request)
    {

        //$vendor = Auth::user();
        //$id = $request->vu_id ;
        $v_id = $request->v_id;
        $store_id = $request->store_id;

        $order_id = $request->order_id;
        $vendor_user_random = $request->guard_code;

        $order  = Order::where('order_id', $order_id)
        ->where('v_id', $v_id)
        ->where('store_id', $store_id)->first();
        if ($order) {
            if ($order->verify_status == 1) {

                if ($order->verify_status_guard == 1) {

                    return response()->json(['status' => 'fail', 'message' => 'Order has been already verified by Guard'], 200);
                } else {

                    $vendor = Vendor::where('vendor_user_random', $vendor_user_random)->where('type', 'guard')->where('v_id', $v_id)->where('store_id', $store_id)->first();

                    if ($vendor) {
                        if ($vendor->status == '1' && $vendor->approved_by_store == '1') {

                            $order->verify_status_guard = '1';
                            $order->verified_by_guard = $vendor->id;
                            $order->save();
                            //return  $this->order_details($request);
                            return response()->json(['status' => 'success', 'message' => 'Your order is verified , Thank You'], 200);
                        } else {
                            return response()->json(['status' => 'fail', 'message' => 'Guard is not active Or not approved by store Manager'], 200);
                        }
                    } else {

                        return response()->json(['status' => 'fail', 'message' => 'Incorrect Code'], 200);
                    }
                }
            } else {


                return response()->json(['status' => 'fail', 'message' => 'This order is not verified by Staff , Ask staff to verified first'], 200);
            }
        } else {

            return response()->json(['status' => 'fail', 'message' => 'Unable to verified this order'], 200);
        }
    }

    public function updateCustomerToCart($params)
    {

        $order_id = Order::where('user_id', $params['old_c_id'])->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
        $order_id = $order_id + 1;
        $new_customer_id = $params['c_id'];

        $new_order_id = Order::where('user_id', $new_customer_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
        $new_order_id = $new_order_id + 1;

        Cart::where('v_id', $params['v_id'])->where('store_id', $params['store_id'])->where('user_id', $params['old_c_id'])->where('order_id', $order_id)->where('status', 'process')->update(['user_id' => $params['c_id'], 'order_id' => $new_order_id]);

        CartDiscount::where('v_id', $params['v_id'])->where('store_id', $params['store_id'])->where('user_id', $params['old_c_id'])->where('vu_id', $params['vu_id'])->update(['user_id' => $params['c_id']]);
        //update gift voucher cart
        GiftVoucherCartDetails::where('v_id', $params['v_id'])->where('store_id', $params['store_id'])->where('customer_id',$params['old_c_id'])->update(['customer_id' => $params['c_id']] );

        if(isset($params['cust_gstin']) && $params['cust_gstin'] !='' ){
            $request = $params['request'];
            $request->request->add([
                'c_id'  => $params['c_id'],
                'cust_gstin' => $params['cust_gstin']
            ]);

            $cartC = new CartController;
            $cartC->calculatePromotions($request);
        }
    }

    public function unTagCustomer(Request $request)
    {
        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $c_id = $request->dummy_c_id;
        $id = $request->vu_id;
        $old_c_id = $request->customer_c_id;

        $this->updateCustomerToCart(['c_id' => $c_id, 'v_id' => $v_id, 'store_id' => $store_id, 'old_c_id' => $old_c_id, 'vu_id' => $id]);

        if ($c_id == '') {

            $ven = Vendor::select('mobile')->where('id', $id)->first();

            $exists_user = User::select('c_id', 'mobile', 'api_token', 'password', 'first_name', 'last_name', 'vendor_user_id', 'email', 'gender', 'anniversary_date', 'gstin')->where('mobile', '3' . $ven->mobile)->first();
        } else {

            $exists_user = User::select('c_id', 'mobile', 'api_token', 'password', 'first_name', 'last_name', 'vendor_user_id', 'email', 'gender', 'anniversary_date', 'gstin')->where('c_id', $c_id)->first();
        }

        return $response = ['status' => 'login_redirect', 'message' => 'Login Successfully', 'data' => $exists_user, 'customer_group_code' => 'DUMMY',  'id' => $id, 'vu_id' => $id];
    }

    public function login_for_customer(Request $request)
    {   //dd($request->all());
        $customer_mobile = $request->customer_mobile;
        $id = $request->vu_id;
        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $c_id = null;
        if ($request->has('store_id')) {
            $country = Store::select('country')->where('v_id', $v_id)->where('store_id', $store_id)->first();
            $countryId=$country->country;
            $dialCode = Country::select('dial_code')->where('id', $countryId)->first();
            $customerPhoneCode=$dialCode->dial_code;
        }
        $customerPhoneCode=empty($customerPhoneCode)?'':$customerPhoneCode;
        $address1 = '';
        $address2 = '';
        if ($request->has('address1')) {
            $address1 = $request->address1;
        }
        if ($request->has('address2')) {
            $address2 = $request->address2;
        }

        $update_flag = 1;
        if ($request->has('update_flag')) {
            $update_flag = $request->update_flag;
        }


        $fname = '';
        $lname = 'Customer';
        $dob = null;
        $email = '';
        $gender = '';
        $gstin = '';
        $anniversary_date = '';
        $address_nickname = 'Home';
        $city_id = null;
        $state_id = null;
        $landmark=null;
        $country_id = null;
        $pincode = '';
        $seat_no = '';
        $hall_no = '';
        $landmark = '';

        if ($request->trans_from == 'ANDROID_KIOSK') {
            $fname  = 'Kiosk';
        } else {
            $fname  = 'mPos';
        }

        if ($request->has('first_name')) {
            $fname  = $request->first_name;
        }

        if ($request->has('last_name')) {
            $lname  = $request->last_name;
        }

        if ($request->has('dob')) {
            $dob  = $request->dob;
        }

        if ($request->has('email')) {
            $email  = $request->email;
        }

        if ($request->has('gender')) {
            $gender  = $request->gender;
        }

        if ($request->has('anniversary_date')) {
            $anniversary_date  = $request->anniversary_date;
        }

        if ($request->has('gstin')) {
            $gstin  = $request->gstin;
        }


        if ($request->has('address_nickname')) {
            $address_nickname  = $request->address_nickname;
        }

        if ($request->has('city_id')) {
            $city_id  = $request->city_id;
        }
        if ($request->has('landmark')) {
            $landmark  = $request->landmark;
        }
        if ($request->has('state_id')) {
            $state_id  = $request->state_id;
        }

        if ($request->has('country_id')) {
            $country_id  = $request->country_id;
        }

        if ($request->has('pincode')) {
            $pincode  = $request->pincode;
        }

        if ($request->has('seat_no')) {
            $seat_no  = $request->seat_no;
        }

        if ($request->has('hall_no')) {
            $hall_no  = $request->hall_no;
        }
        if($request->has('customer_phone_code') && $request->get('customer_phone_code')){
            $customer_phone_code = $request->get('customer_phone_code');
        }else{
            $customer_phone_code = Country::join('addresses','addresses.country_id','countries.id')
                                            ->join('customer_auth','customer_auth.c_id','addresses.c_id')
                                            ->where('customer_auth.mobile',$customer_mobile)->select('countries.dial_code')->first();
        }
        $address = null;
        $group_id = 2; //For Regular Customer
        $group_code = 'REGULAR'; //For Regular Customer
        if ($request->has('customer_group')) {
            $fname = 'Dummy';
            $group_code = 'DUMMY'; //For Regular Customer
        }
        $group_id = CustomerGroup::select('id')->where('code', $group_code)->first()->id;

        $exists_user = User::select('c_id', 'dob', 'mobile', 'api_token', 'password', 'first_name', 'last_name', 'vendor_user_id', 'email', 'gender', 'anniversary_date', 'gstin', 'v_id')->where('mobile', $customer_mobile)->where('v_id', $request->v_id)->first();
        $previousGstin = !empty($exists_user->gstin)?$exists_user->gstin:'';

        if (!$exists_user) {
            if ($request->has('loyalty')) {
                $loyaltyPrams = ['type' => $request->loyaltyType, 'event' => 'searchCustomer', 'mobile' => $customer_mobile, 'id' => $request->vu_id, 'settings' => $request->loyaltySettings, 'zw_event' => 'checkAccount', 'v_id' => $v_id, 'store_id' => $store_id];
                event(new Loyalty($loyaltyPrams));
            }
            
            $user = new User;
            $user->mobile    = $customer_mobile;
            $user->customer_phone_code   = $customer_phone_code;
            $user->first_name = $fname;
            $user->v_id = $request->v_id;
            $user->last_name  = $lname;
            $user->email      = $email;
            $user->dob        = empty($dob)?$dob:date('Y-m-d',strtotime($dob));
            $user->gender     = $gender;
            $user->status     = 1;
            $user->anniversary_date  = $anniversary_date;
            $user->gstin  = $gstin;
            $user->api_token  =  str_random(50);
            $user->vendor_user_id = $id;
            if ($request->has('seat_no')) {
                $user->seat_no = $seat_no;
            }
            if ($request->has('hall_no')) {
                $user->hall_no = $hall_no;
            }
            $user->customer_phone_code = $customerPhoneCode;
            $user->save();

            $c_id = $user->c_id;
            $cgm = new CustomerGroupMapping;
            $cgm->c_id = $c_id;
            $cgm->group_id = $group_id;
            $cgm->save();

            if ($address1 != '' || $address2 != '') {
                $address = new Address;
                $address->c_id = $c_id;
                $address->address_nickname = $address_nickname;
                $address->address1 = $address1;
                $address->address2 = $address2;
                $address->city_id = $city_id;
                $address->state_id = $state_id;
                $address->country_id = $country_id;
                $address->pincode = $pincode;
                $address->save();
            }

            if ($address) {

                $custC = new CustomerController;
                $address = $custC->getAddressArr($address);
            }

            // if ($request->has('loyalty')) {
            //     $loyaltyPrams = [ 'type' => 'easeMyRetail', 'event' => 'createCustomer', 'mobile' => $customer_mobile, 'id' => $request->vu_id, 'user_id' => $c_id ];
            //     $loyaltyCon = new LoyaltyController;
            //     $loyaltyResponse = $loyaltyCon->index($loyaltyPrams);
            // }
            $summary = [];
            //get all customer group and then get all setting from group on the basis of max value
            $groupIdList = CustomerGroupMapping::select('group_id')->where('c_id',$c_id)->get();
            $groupIds = collect($groupIdList)->pluck('group_id');
            $group_settings = [];
            $maximum_limit_perbill = CustomerGroup::where('items_limit_perbill','1')->whereIn('id',$groupIds)->max('maximum_limit_perbill');
            $maximum_limit_perday = CustomerGroup::where('items_limit_perday','1')->whereIn('id',$groupIds)->max('maximum_limit_perday');
            $maximum_value_perbill = CustomerGroup::where('value_limit_perbill','1')->whereIn('id',$groupIds)->max('maximum_value_perbill');
            $maximum_value_perday = CustomerGroup::where('value_limit_perday','1')->whereIn('id',$groupIds)->max('maximum_value_perday');
            $allow_manual_discount = CustomerGroup::where('allow_manual_discount','1')->whereIn('id',$groupIds)->exists();
            $allow_manual_discount_bill_level = CustomerGroup::where('allow_manual_discount_bill_level','1')->whereIn('id',$groupIds)->exists();
            $group_settings['items_limit_perbill']=$maximum_limit_perbill>0?true:false;
            $group_settings['maximum_limit_perbill']=empty($maximum_limit_perbill)?0:$maximum_limit_perbill;
            $group_settings['items_limit_perday']=$maximum_limit_perday>0?true:false;
            $group_settings['maximum_limit_perday']=empty($maximum_limit_perday)?0:$maximum_limit_perday;
            $group_settings['value_limit_perbill']=$maximum_value_perbill>0?true:false;
            $group_settings['maximum_value_perbill']=empty($maximum_value_perbill)?0:$maximum_value_perbill;
            $group_settings['value_limit_perday']=$maximum_value_perday>0?true:false;
            $group_settings['maximum_value_perday']=empty($maximum_value_perday)?0:$maximum_value_perday;
            $group_settings['allow_manual_discount_item_level']=empty($allow_manual_discount)?false:true;
            $group_settings['allow_manual_discount_bill_level']=empty($allow_manual_discount_bill_level)?false:true;
            $response = ['status' => 'login_redirect', 'message' => 'Login Successfully', 'data' => $user, 'customer_group_code' => $group_code, 'vu_id' => $id, 'address' => $address, 'summary' => $summary,'group_settings'=>$group_settings];
        } else {
            //if( $exists_user->api_token == null ){
            $exists_user->api_token  =  str_random(50);
            if ($update_flag) {
                $exists_user->first_name = $fname;
                $exists_user->last_name  = $lname;
                $exists_user->dob  = empty($dob)?$dob:date('Y-m-d',strtotime($dob));
                $exists_user->email = $email;
                $exists_user->gender  = $gender;
                $exists_user->status     = 1;
                $exists_user->anniversary_date  = $anniversary_date;
                $exists_user->gstin  = $gstin;
            }
            if ($request->has('seat_no')) {
                $exists_user->seat_no  = $seat_no;
            }
            if ($request->has('hall_no')) {
                $exists_user->hall_no  = $hall_no;
            }
            $exists_user->save();
            //add phone code
            $userPhoneCode = User::select('customer_phone_code')->where('c_id', $exists_user->c_id)->first();
            if(empty($userPhoneCode->customer_phone_code) ){
                User::find($exists_user->c_id)->update(['customer_phone_code' => $customerPhoneCode ]);
            }
            //}

            if ($request->has('loyalty')) {
                $checkLoyaltyAccount = LoyaltyCustomer::where('mobile', $customer_mobile)->where('vendor_id', $v_id)->where('type', $request->loyaltyType)->where('is_created', '1')->first();
                if (empty($checkLoyaltyAccount)) {
                    $loyaltyPrams = ['type' => $request->loyaltyType, 'event' => 'searchCustomer', 'mobile' => $customer_mobile, 'id' => $request->vu_id, 'settings' => $request->loyaltySettings, 'zw_event' => 'checkAccountOrCreate', 'v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $exists_user->c_id];
                    event(new loyalty($loyaltyPrams));
                }
            }
            $c_id = $exists_user->c_id;
            $address = null;
            $address = Address::where('c_id', $exists_user->c_id)->first();
            if ($address1 != '' || $address2 != '') {

                if ($update_flag) {
                    if ($address) {
                        $address->address1 = $address1;
                        $address->address2 = $address2;
                        $address->landmark = $landmark;
                        $address->address_nickname = $address_nickname;
                        $address->city_id = $city_id;
                        $address->state_id = $state_id;
                        $address->country_id = $country_id;
                        $address->pincode = $pincode;
                        $address->save();
                    } else {
                        $address = new Address;
                        $address->c_id = $exists_user->c_id;
                        $address->address_nickname = $address_nickname;
                        $address->address1 = $address1;
                        $address->address2 = $address2;
                        $address->landmark = $landmark;
                        $address->city_id = $city_id;
                        $address->state_id = $state_id;
                        $address->country_id = $country_id;
                        $address->pincode = $pincode;
                        $address->save();
                    }
                }
            }

            //IF Any Group is not mapped Then mapping Group
            $groupExists = CustomerGroupMapping::where('c_id', $c_id)->first();
            if(!$groupExists){
                $cgm = new CustomerGroupMapping;
                $cgm->c_id = $c_id;
                $cgm->group_id = $group_id;
                $cgm->save();    
            }

            $order = Order::select('order_id', 'v_id', 'store_id', 'date', 'time', 'total', 'verify_status', 'verify_status_guard')->where('user_id', $exists_user->c_id)->where('status', 'success')->where('transaction_type', 'sales')->orderBy('od_id', 'desc')->first();

            $cart_items = Cart::where('user_id', $exists_user->c_id)->where('transaction_type', 'sales')->select('cart_id','barcode','qty','item_name','created_at')->get()->toArray();
            
            if ($address) {

                $custC = new CustomerController;
                $address = $custC->getAddressArr($address);
            }

            $summary = [];
            if($exists_user) {
                $maxCreditLimit=0;
                foreach ($exists_user->groups as $group => $code) {
                    $maxCreditLimit = $code->maximum_credit_limit;
                }
                $on_account_bal = DepRfdTrans::where(['v_id'=>$request->v_id,'src_store_id'=>$request->store_id,'user_id'=>$exists_user->c_id])->select(DB::raw("SUM(dep_rfd_trans.amount) as amount"))->where('status','Success')->first()->amount;

                // $maxCreditLimit = $maxCreditLimit+$on_account_bal;

                // $previousDebitAmount = DepRfdTrans::where(['v_id'=>$request->v_id,'user_id'=>$request->c_id])->select(DB::raw("SUM(dep_rfd_trans.amount) as amount"))->where('status','Success')->first();
                // $customer_bal    = $previousDebitAmount->amount;
                // $maxCreditLimit  = $maxCreditLimit;

                $summary['total_spent'] = format_number($exists_user->invoices()->sum('total'));
                $summary['no_of_bills'] = $exists_user->invoices()->count();
                $summary['compleleted_sales'] = format_number($exists_user->invoices()->where('transaction_type', 'sales')->sum('total'));
                $summary['compleleted_sales_total'] = $exists_user->invoices()->where('transaction_type', 'sales')->count();
                $summary['no_of_returns'] = $exists_user->invoices()->where('transaction_type', 'return')->count();
                $summary['layby'] = format_number($exists_user->invoices()->where('transaction_type', 'layby')->sum('total'));
                $summary['layby_count'] = $exists_user->invoices()->where('transaction_type', 'layby')->count();
                $summary['loyalty'] = '0.00';
                $summary['store_credit_unused'] = format_number($exists_user->vouchers->where('status','unused')->sum('amount'));
                $summary['store_credit_used'] = format_number($exists_user->vouchers->where('status','used')->sum('amount'));
                $summary['total_store_credit'] = format_number($exists_user->vouchers->sum('amount'));
                $summary['on_account'] = $on_account_bal == null ? '0.00' : $on_account_bal;
                // $summary['on_account'] = format_number($user->invoices()->sum('total'));
                $summary['max_limit'] = format_number($maxCreditLimit - $on_account_bal);
                $summary['no_of_on_account'] = DepRfdTrans::where(['v_id'=>$request->v_id,'user_id'=>$request->c_id])->select(DB::raw("SUM(dep_rfd_trans.amount) as amount"))->where('status','Success')->count();

                $exists_user->groups = $exists_user->groups->pluck('code');
                $exists_user->unsetRelation('groups')->unsetRelation('invoices')->unsetRelation('vouchers');
            }else{
                $summary['on_account'] = 0;
            }
            //get all customer group and then get all setting from group on the basis of max value
            $groupIdList = CustomerGroupMapping::select('group_id')->where('c_id',$c_id)->get();
            $groupIds = collect($groupIdList)->pluck('group_id');
            $group_settings = [];
            $maximum_limit_perbill = CustomerGroup::where('items_limit_perbill','1')->whereIn('id',$groupIds)->max('maximum_limit_perbill');
            $maximum_limit_perday = CustomerGroup::where('items_limit_perday','1')->whereIn('id',$groupIds)->max('maximum_limit_perday');
            $maximum_value_perbill = CustomerGroup::where('value_limit_perbill','1')->whereIn('id',$groupIds)->max('maximum_value_perbill');
            $maximum_value_perday = CustomerGroup::where('value_limit_perday','1')->whereIn('id',$groupIds)->max('maximum_value_perday');
            $allow_manual_discount = CustomerGroup::where('allow_manual_discount','1')->whereIn('id',$groupIds)->exists();
            $allow_manual_discount_bill_level = CustomerGroup::where('allow_manual_discount_bill_level','1')->whereIn('id',$groupIds)->exists();
            $perDayQty=$exists_user->invoices()->where('transaction_type', 'sales')->where('date',date('Y-m-d'))->sum('qty');
            $perDayValue=$exists_user->invoices()->where('transaction_type', 'sales')->where('date',date('Y-m-d'))->sum('total');
            $afterBillQty=($maximum_limit_perday-$perDayQty)>0?($maximum_limit_perday-$perDayQty):0;
            $afterBillValue=($maximum_value_perday-$perDayValue)>0?($maximum_value_perday-$perDayValue):0;
            $group_settings['items_limit_perbill']=$maximum_limit_perbill>0?true:false;
            $group_settings['maximum_limit_perbill']=empty($maximum_limit_perbill)?0:$maximum_limit_perbill;
            $group_settings['items_limit_perday']=$maximum_limit_perday>0?true:false;
            $group_settings['maximum_limit_perday']=empty($maximum_limit_perday)?0:$afterBillQty;
            $group_settings['actual_maximum_limit_perday']=empty($maximum_limit_perday)?0:$maximum_limit_perday;
            $group_settings['value_limit_perbill']=$maximum_value_perbill>0?true:false;
            $group_settings['maximum_value_perbill']=empty($maximum_value_perbill)?0:$maximum_value_perbill;
            $group_settings['value_limit_perday']=$maximum_value_perday>0?true:false;
            $group_settings['maximum_value_perday']=empty($maximum_value_perday)?0:$afterBillValue;
            $group_settings['actual_maximum_value_perday']=empty($maximum_value_perday)?0:$maximum_value_perday;
            $group_settings['allow_manual_discount_item_level']=empty($allow_manual_discount)?false:true;
            $group_settings['allow_manual_discount_bill_level']=empty($allow_manual_discount_bill_level)?false:true;
            $response = ['status' => 'login_redirect','cart_items'=>$cart_items, 'message' => 'Login Successfully', 'data' => $exists_user, 'customer_group_code' => $group_code,  'vu_id' => $id, 'address' => $address, 'summary' => $summary,'group_settings'=>$group_settings];

            if ($request->v_id == 16) {
                if ($order) {

                    if ($order->verify_status != '1') {
                        $response = ['status' => 'fail', 'message' => 'Previous Order Verification is pending from Cashier'];
                    } else if ($order->verify_status_guard != '1') {
                        $response = ['status' => 'fail', 'message' => 'Previous Order Verification is pending from Guard'];
                    }
                }
            }
        }

    if (($request->has('tag_customer') && $request->tag_customer == 1)  || @$exists_user->gstin != $previousGstin) {

        //echo 'dd';die;
        //if($exists_user->gstin != $gstin){
            $old_c_id = $request->old_c_id;
            $v_id = $request->v_id;
            $store_id = $request->store_id;

            if(empty($gstin)){
                $gstin = '0';
            }
            $this->updateCustomerToCart(['old_c_id' => $old_c_id, 'c_id' => $c_id, 'v_id' => $v_id, 'store_id' => $store_id, 'vu_id' => $request->vu_id, 'cust_gstin' => $gstin , 'request' => $request ]);
        }

        if ($request->has('response_format') && $request->response_format == 'ARRAY') {
            return $response;
        } else {
            return response()->json($response, 200);
        }
    }

    public function scan_for_customer(Request $request)
    {
        return $this->login_for_customer($request);
    }


    public function operation_verification(Request $request)
    {

        $id = $request->vu_id;
        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $operation = $request->operation;
        $security_code = $request->security_code;
        if ($operation ==  'BILL_REPRINT') {
            $vendor = VendorAuth::where('vendor_user_random', $security_code)->where('v_id', $v_id)->first();
            if ($vendor) {
                $vendor_auth = VendorRole::where('v_id', $vendor->v_id)->where('code', 'super_visor')->first();
                if ($vendor_auth) {
                    if ($vendor->status == '1' && $vendor->approved_by_store == '1') {
                        return response()->json(['status' => 'success', 'message' => 'Authorized successfully', 'data' => ['security_code_id' => $vendor_auth->id]], 200);
                    } else {
                        return response()->json(['status' => 'fail', 'message' => 'Your account is not active'], 200);
                    }
                } else {
                    return response()->json(['status' => 'fail', 'message' => 'You are not supervisor'], 200);
                }
            } else {
                return response()->json(['status' => 'fail', 'message' => 'Incorrect Code'], 200);
            }
        } else {

            return response()->json(['status' => 'fail', 'message' => 'Operation not specified'], 200);
        }
    }


    public function get_settings(Request $request)
    {
        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $user_id = $request->vu_id;
        $udidtoken=$request->udidtoken;
        JobdynamicConnection($v_id);
        
        $organisation = Organisation::where('id',$v_id)->first();
        $role = VendorRoleUserMapping::select('role_id')->where('user_id', $user_id)->first();
        $role_id = $role->role_id;
        $trans_from = $request->trans_from;
        $sParams = ['v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $user_id, 'role_id' => $role_id, 'trans_from' => $trans_from,'udidtoken'=>$udidtoken];
        $response_format = 'JSON';
        $merchantPosCodeDetails=$this->getmerchantStoreCode($sParams);
        $pineLabDetails=$this->getPineLabDetails($sParams);
        $vendorS = new VendorSettingController;
        $product_max_qty =  $vendorS->getProductMaxQty($sParams);
        $cart_max_item = $vendorS->getMaxItemInCart($sParams);
        
        $colorSettings = $vendorS->getColorSetting($sParams);
        $vendorApp = $vendorS->getVendorAppSetting($sParams);
        $toolbar = $vendorS->getToolbarSetting($sParams);
        $paymentTypeSettings = $vendorS->getPaymentTypeSetting($sParams);
        $paymentMultipleMopSettings = $vendorS->getPaymentMultipleMopSetting($sParams);
        $feedback = $vendorS->getFeedbackSetting($sParams);
        $invoice = $vendorS->getInvoiceSetting($sParams);
        $print = $vendorS->getPrintSetting($sParams);
        $barcode = $vendorS->getBarcodeSetting($sParams);
        $optimize_flow = $vendorS->getOptimizeFlowSetting($sParams);
        $return_authorization = $vendorS->getReturnAuthorizationSetting($sParams);
        $vendor_customer_login = $vendorApp->customer_login;
        $customer_logins = $vendorS->getVendorCustomerLogins($sParams);
        $stock = $vendorS->getStockSetting($sParams);
        $numberOfItemInOneInvoice = $vendorS->getMaxItemInCart($sParams);

        $featureSettings = $vendorS->getFeatureSetting($sParams);//dd($featureSettings);
        $getPaymentTypeSetting = $vendorS->getPaymentMultipleMopSetting($sParams);

        ///allow adhoc return ///
        $allow_returns = $vendorS->getSettlementSetting($sParams);
        if($allow_returns->good_return->DEFAULT->options[0]->cash->value == false)
        {
            $allow_returns->good_return->DEFAULT->options[0]->cash->status = 0;
        }else{
            $allow_returns->good_return->DEFAULT->options[0]->cash->status = 1;
        }
        if($allow_returns->good_return->DEFAULT->options[0]->store_credit_note->value == false)
        {
            $allow_returns->good_return->DEFAULT->options[0]->store_credit_note->status = 0;
        }else{
            $allow_returns->good_return->DEFAULT->options[0]->store_credit_note->status = 1;
        }

        $getPromotionSetting = $vendorS->getPromotionSetting($sParams);
        $session_alive = $vendorS->getSettlementSetting($sParams);
        $cashmanagement = $vendorS->getStoreSetting($sParams);
        $storeSettings = $vendorS->getStoreSetting($sParams);
        $settlementSettings = $vendorS->getSettlementSetting($sParams);
        $scanSettings = $vendorS->getScanScreenSetting($sParams);
        $offerSettings = $vendorS->getOfferSetting($sParams);
        $orderSettings = $vendorS->getOrderSetting($sParams);
        $stockSettings = $vendorS->getStockSetting($sParams);
        $paymentSettings = $vendorS->getPaymentSetting($sParams);
        $vendorAppSettings = $vendorS->getVendorAppSetting($sParams);
        $toolbarSettings = $vendorS->getToolbarSetting($sParams);
        //STORE DATA FOR QRCODE
        $orgAccountDetails = DB::table('org_accounts_store_mapping as oasm')
                            ->rightJoin('org_account_details as oad', 'oad.id', 'oasm.account_id')
                            ->select('oad.vpa', 'oad.account_type', 'oad.bank_name', 'oad.branch_name', 'oad.account_number', 'oad.ifsc_code', 'oad.status')
                            ->where('v_id', $request->v_id)
                            ->where('store_id', $request->store_id)
                            ->where('status', '1')
                            ->first();

        $orgDetails = [];
        
        if($orgAccountDetails){
            foreach ($orgAccountDetails as $key => $value) {
                if($key == 'status')
                    $orgDetails[$key] = true;
                else
                    $orgDetails[$key] = $value == null ? '' : $value;
            }
        }else{
            $orgDetails['account_type'] = '';
            $orgDetails['vpa'] = '';
            $orgDetails['bank_name'] = '';
            $orgDetails['branch_name'] = '';
            $orgDetails['account_number'] = '';
            $orgDetails['ifsc_code'] = '';
            $orgDetails['status'] = false;
        }

        if (!$vendor_customer_login || $customer_logins->status == 0) {
            $skip_login = 0;
            if (isset($customer_logins->skip_login)) {
                $skip_login = $customer_logins->skip_login;
            }

            $request->request->add(['customer_mobile' => '3' . $request->mobile, 'customer_group' => 'DUMMY', 'response_format' => 'ARRAY']);
            $login = $this->login_for_customer($request);

            // if($login['data']->c_id == 6518) {
            //     dd($login['data']->c_id);
            // }
            $vendor_login_details = ['vendor_customer_login' => $vendor_customer_login, 'skip_login' => $skip_login, 'api_token' => $login['data']->api_token, 'c_id' => $login['data']->c_id, 'mobile' => (string) $login['data']->mobile, 'email' => $login['data']->email, 'first_name' => $login['data']->first_name, 'last_name' => $login['data']->last_name, 'customer_group_code' => $login['customer_group_code']];
        } else {
            if (isset($customer_logins->skip_login) && ($customer_logins->skip_login == 1)) {
                $request->request->add(['customer_mobile' => '3' . $request->mobile, 'customer_group' => 'DUMMY', 'response_format' => 'ARRAY']);
                $login = $this->login_for_customer($request);

                //dd($login['data']);
                $vendor_login_details = ['vendor_customer_login' => $vendor_customer_login, 'skip_login' => $customer_logins->skip_login, 'api_token' => $login['data']->api_token, 'c_id' => $login['data']->c_id, 'mobile' => (string) $login['data']->mobile, 'email' => $login['data']->email, 'first_name' => $login['data']->first_name, 'last_name' => $login['data']->last_name, 'customer_group_code' => $login['customer_group_code']];
            } else {

                if ($trans_from == 'CLOUD_TAB_WEB' || $trans_from == 'ANDROID_VENDOR') {

                    $request->request->add(['customer_mobile' => '3' . $request->mobile, 'customer_group' => 'DUMMY', 'response_format' => 'ARRAY']);
                    $login = $this->login_for_customer($request);

                    $skip_login = 0;
                    if(isset($customer_logins->DEFAULT->options[0]->pos_billing->value)){
                        if($customer_logins->DEFAULT->options[0]->pos_billing->value == 'optional'){
                            $skip_login = 1;   
                        }

                    }
                    //dd($login['data']);
                    $vendor_login_details = ['vendor_customer_login' => $vendor_customer_login, 'skip_login' => $skip_login, 'api_token' => $login['data']->api_token, 'c_id' => $login['data']->c_id, 'mobile' => (string) $login['data']->mobile, 'email' => $login['data']->email, 'first_name' => $login['data']->first_name, 'last_name' => $login['data']->last_name, 'customer_group_code' => $login['customer_group_code']];
                } else {

                    $vendor_login_details = ['vendor_customer_login' => $vendor_customer_login, 'skip_login' => 0];
                }
            }
        }

        /*User currency */
            $crparams = array('v_id'=>$v_id,'store_id'=>$store_id,'vu_id'=>$user_id,'info_type'=>'CURRENCY');
            $vendor_login_details['currency'] = $this->getCurrencyDetail($crparams);
            $crparams['info_type'] = 'COUNTRY';
            $vendor_login_details['country_details'] = $this->getCurrencyDetail($crparams);
        /*User currency end*/

        if (@$request->has('store_id')) {
            $store =  DB::table('stores')->select(DB::raw('name,store_logo,store_icon,store_list_bg,location,store_id'))->where('api_status', 1)->where('status', 1)->where('v_id', $v_id)->where('store_id', $request->store_id)->first();
        } else {
            $store =  DB::table('stores')->select(DB::raw('name,store_logo,store_icon,store_list_bg,location,store_id'))->where('api_status', 1)->where('status', 1)->where('v_id', $v_id)->first();
        }

        if(!$store){
            return response()->json(['message' => 'Store is not active', 'status' => 'fail'], 200);
        }
        /*Get Store Setting start*/
            $storeSetting = StoreSettings::where('store_id',$store->store_id)->first();
            $coupon_type  = '';
            $loyalty_type = '';
            if($storeSetting){
                $strSet       = json_decode($storeSetting->settings);
                $coupon_type  = isset($strSet->easeMyRetail->COUPON_TYPE)?$strSet->easeMyRetail->COUPON_TYPE:'emr';
                $loyalty_type = isset($strSet->easeMyRetail->LOYALITY_TYPE)?$strSet->easeMyRetail->LOYALITY_TYPE:'emr';
            }
        /*Store setting end*/

        $store_logo   = '';
        $store_bg_logo = '';
        $vendorImage  = VendorImage::where('v_id', $v_id);
        if ($vendorImage) {
            //$bilLogo = env('ADMIN_URL').$vendorImage->path;
            $store_logo = $vendorImage->where('type', 1)->where('status', 1)->where('deleted', 0)->first();
            $store_bg_logo = VendorImage::where('v_id', $v_id)->where('type', 2)->where('status', 1)->where('deleted', 0)->first();
        }

        $mgPath=env('ADMIN_URL');
        if ($store_logo!=null) {
            if ($store_logo->path!=null) {
            $mgPath .= $store_logo->path;
            }
        }
        
        $store_logo_flag = false;
        if (@getimagesize($mgPath)) {
            $store_logo_flag = true;
        }

        $vendorSett = new VendorSettlementController;
        if ($request->has('response_format')) {
            $response_format =  $request->response_format;
        }

        if($request->has('session_force_close')){

          $foreclosedStatus = $vendorSett->getSessionDetails($request);
            
        }else{
         
         $foreclosedStatus= ['status'=>0,
                             'details'=>(object)[]
                            ];
        }
        
        $appV = DB::connection('mysql')->table('app_versions')->where('trans_from', $trans_from)->orderBy('id', 'desc')->first();

        $latest_version = "1.0.0";
        $fore_fully_update = '0';
        $app_url = '';
        $app_update_msg = '';
        $store_url_flag = 0;

        if ($appV) {

            $latest_version = $appV->version;
            $fore_fully_update = $appV->forcefully_update;
            $app_update_msg = $appV->message;
            $store_url_flag =  $appV->store_url_flag;
            $app_url = $appV->app_url;
        }

        //$vendorSett = new VendorSettlementController;
 

        $offline_scan = $vendorS->getScanScreenOffilneSetting($sParams);
        if ($offline_scan->status == 1) {
            $vendorDevice = new DeviceController;
            $checkDevice  = $vendorDevice->getdevice($request);

            if ($checkDevice == 0) {
                if ($response_format == 'ARRAY') {
                    $deviceMsg  = ['status' => 'fail', 'message' => 'Device Not Found In Our Database'];
                    return ['settings' => $deviceMsg];
                } else {
                    return response()->json(['status' => 'fail', 'message' => 'Device Not Found In Our Database'], 200);
                }
            }
            $vendorD      = $vendorDevice->devicesyncstatus($request);
            $offline_scan->sync_status = $vendorD;
        } else {
            $offline_scan->sync_status = 0;
        }

        $session_started_at = '';

        $opening_balance_flag = $vendorSett->opening_balance_flag($request);
        $opening_balance_status = 0;
        if (is_array($opening_balance_flag)) {
            $session_started_at =  $opening_balance_flag['opening_time'];
            $opening_balance_status = $opening_balance_flag['opening_flag'];
        } else {
            $opening_balance_status = 0;
        }


        $settlement_session_id = 0;
        $closing_previous_balance_status = $vendorSett->closing_balance_flag($request);
        //dd($closing_previous_balance_status);
        if (is_array($closing_previous_balance_status)) {
            $settlement_session_id = $closing_previous_balance_status['settlement_session_id'];
            $closing_previous_balance_status = $closing_previous_balance_status['closing_flag'];
        }
        //This is Checking current Closing Balance
        if (isset($opening_balance_flag['trans_from']) && ($trans_from != $opening_balance_flag['trans_from'])) {
            $request->request->add(['current' => 1]);
            $closing_previous_balance_status = $vendorSett->closing_balance_flag($request);
            if (is_array($closing_previous_balance_status)) {
                $settlement_session_id = $closing_previous_balance_status['settlement_session_id'];
                $closing_previous_balance_status = $closing_previous_balance_status['closing_flag'];
            }
        }

        $open_sesssion_compulsory = $vendorS->getSessionCompulsorySettingFunction($sParams);
        // if(isset($open_sesssion_compulsory->status) ){
        //     if((int)$open_sesssion_compulsory->status == 1 && (int)$closing_previous_balance_status ==1 && (int)$open_sesssion_compulsory->status == 1){
        //         $opening_balance_status = 0; 
        //     }
        // }
        //dd($opening_balance_status);
        // Get opening time
        if (!empty($settlement_session_id) || $settlement_session_id != 0) {
            $sessionDat = SettlementSession::find($settlement_session_id);
            $session_started_at = $sessionDat->opening_time;
        }

        // Check closing
        // if($closing_previous_balance_status == 0) {
        //     $session_started_at = '';
        // }


        $bilLogo = [];
        $bill_logo_id = 5;
        $bilLogoImage = '';
        $billLogoHeight = 30;
        $billLogoWidth = 150;
        $units = 'mm';
        $base_img = '';

        if($trans_from == 'CLOUD_TAB_WEB') {
            $billLogoHeight = 1.5;
            $billLogoWidth = 1.25;
            $units = 'in';
        }

        $vendorImage = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status', 1)->first();
        if ($vendorImage) {
            if (!empty($vendorImage->path) && $vendorImage->path != '') {
                $bilLogoImage = env('ADMIN_URL') . $vendorImage->path;
            }else{
                $bilLogoImage = env('ADMIN_URL').'/default/default.png';
                //storage/images
            }
            //$bilLogoImage = env('ADMIN_URL') . $vendorImage->path;
            if ($vendorImage->height != '' && $vendorImage->height != null) {
                $billLogoHeight = $vendorImage->height;
                $billLogoWidth = $vendorImage->width;
            }
        
        }else{
            $bilLogoImage = env('ADMIN_URL').'/default/default.png';
        }
            $type = pathinfo($bilLogoImage, PATHINFO_EXTENSION);
            
            $base_img = '';
            if($bilLogoImage != env('ADMIN_URL').'/default/default.png'){
            //    $img_header = substr(get_headers($bilLogoImage)[0], 9, 3);
                 if(file_exists($bilLogoImage)) {
                    $base_img = 'data:image/' . $type . ';base64,' . base64_encode(file_get_contents($bilLogoImage));
                }
            }

        $billHeaderLogo = ['image' => $bilLogoImage, 'height' => (string) $billLogoHeight, 'width' => (string) $billLogoWidth, 'units' => $units, 'base64' => $base_img];

        $billFooterLogo = ['image' => image_path() . 'temp_bill_zwing_bottom_logo.png', 'height' => (string) 21, 'width' => (string) 102];

        $cartSettings = $vendorS->getCartSetting($sParams);

        
        $cart_menu = [];$settingtype="";
        $menu_list = ['avail_offer', 'carry_bag', 'assign_salesman', 'bill_remark', 'hold_bill', 'recall_bill', 'cancel_bill', 'clear_cart', 'item_remark'];
        foreach ($cartSettings as $key => $setting) {
            if ($key == 'avail_offers') {
                $key = 'avail_offer';
            }
            $status = 0;
            $display_text = '';
            $display_name='';
            $salesperson_tagging='';  
            if($key != "template"){
                if (isset($setting->$trans_from)) {
                    $settingtype = $setting->$trans_from;
                    $status = $settingtype->status;
                } else {
                    $settingtype = $setting->DEFAULT;
                    $status = $settingtype->status;
                }
            }
            $status = $settingtype->status;
            // dd($key,$settingtype->status,$status,$cartSettings);
            if (in_array($key, $menu_list)) {
                if (isset($settingtype->display_text)) {
                    $display_text =  $settingtype->display_text;
                    
                }
                if (isset($settingtype->display_name)) {
                $display_name =  $settingtype->display_name;
                }
                if ($settingtype->status == 1) {

                    // Check Carry bag exists in store

                    if ($key == 'carry_bag') {
                        $carryBagExists = Carry::where('v_id', $v_id)->where('store_id', $request->store_id)->where('deleted_status', 0)->where('status', 1)->count();
                        if (empty($carryBagExists)) {
                            $status = 0;
                        }
                    }

                    // Check Assign saleman exists in store

                    if ($key == 'assign_salesman'){
                        $carrySalesManExists = Vendor::join('vendor_role_user_mapping', 'vendor_auth.id', '=', 'vendor_role_user_mapping.user_id')
                        ->join('vendor_roles', 'vendor_roles.id', '=', 'vendor_role_user_mapping.role_id')
                        ->where('vendor_roles.code', 'sales_man')
                        ->where('vendor_auth.store_id', $request->store_id)
                        ->where('vendor_auth.v_id', $v_id)
                        ->where('vendor_auth.status', '1')
                        ->get()
                        ->count();
                        $salesperson_tagging=$setting->DEFAULT->options[0]->salesperson->value;
                        if (empty($carrySalesManExists)) {
                            $status = 0;
                        }
                    }

                    $cart_menu[] = ['name' =>  $key, 'status' => $status, 'display_text' =>  $display_text,'display_name'=>$display_name,'salesperson_tagging'=>$salesperson_tagging];
                } else {
                    $cart_menu[] = ['name' =>  $key, 'status' => $settingtype->status, 'display_text' =>  $display_text,'display_name'=>$display_name,'salesperson_tagging'=>$salesperson_tagging];
                }
            }
        }

        // if ($v_id == 1) {
        //     # code...
        // } else {
        //     dd($cart_menu);
        // }


        $other_payment_method = [];
        $newpaymentTypeSettings = [];
        if ($trans_from == 'CLOUD_TAB' || $trans_from == 'CLOUD_TAB_ANDROID') {
            foreach ($paymentTypeSettings as $key => $pType) {
                if ($pType->name == 'razor_pay_online') {
                    $other_payment_method[] = $pType;
                } else {
                    $newpaymentTypeSettings[] = $pType;
                }
            }

            $paymentTypeSettings = $newpaymentTypeSettings;
        }

        $store_type = 'GROCERY';
        // if ($v_id == 27) {
        //     $store_type = 'CINEMA';
        // }

        // Stock Settings

        // $negative_stock_billing = (object)[
        //     'status'        => $stock->negative_stock_allow,
        //     'is_warning'    => $stock->negative_stock_warning_status,
        //     'warning_msg'   => $stock->negative_warning_message
        // ];

        $einvoiceVerify = StoreSettings::where('name','Einvoice')->where('status','1')->count();
        //->where('store_id',$store_id)
        if($einvoiceVerify > 0){
            $einvoicFlag = 'true';
        }else{
            $einvoicFlag = 'false';
        }

        $exchange = $vendorS->getItemExchangeFunction($sParams);

        $is_promotion_enable = $getPromotionSetting;
        $is_promotion_enable->status = $is_promotion_enable->status == 1 ? true : false;
        
        $responseData = [
            'store_type' => $store_type,
            'setting_date_time'=>['time_zone'=>'Asia/Kolkata','date_time'=>date('d-m-Y h:i:s')],
            'terminal_id'=> get_terminal_id($store_id,$v_id,$udidtoken),
            'bill_logo' => ['header_logo' => $billHeaderLogo, 'footer_logo' => $billFooterLogo],
            'db_name'   => DB::connection()->getDatabaseName(),
            'image_path' => image_path(),
            'status' => 'success',
            'latest_version' => $latest_version,
            'force_fully_update' => $fore_fully_update,
            'app_update_msg' => $app_update_msg,
            'app_url' => $app_url,
            'store_url_flag' => $store_url_flag,
            'v_id' => $v_id,
            'color' =>  $colorSettings,
            'payment_type' => $paymentTypeSettings,
            'multiple_mop' => $paymentMultipleMopSettings,
            'other_payment_method' => $other_payment_method,
            'vendor_app_menu' => $vendorApp,
            'customer_login' => $customer_logins->status,
            'store' => [
                'name' => $store->name,
                'store_logo_flag' => @$store_logo_flag,
                'store_logo' => @$store_logo->path,
                'store_icon' => @$store->store_icon,
                'store_list_bg' => @$store_bg_logo->path,
                'location' => @$store->location,
                'store_logo_link' => env('ADMIN_URL'),
                'organisation_name' => $organisation->name,
                'coupon_type'      => $coupon_type,
                'loyalty_type'     => $loyalty_type,
                'einvoice_enable'  => $einvoicFlag,
            ],
            'organisation' => [
             'name' => $organisation->name,
         ],
         'toolbar' => $toolbar,
         'feedback' => $feedback,
         'print' => $print,
         'invoice' => $invoice,
         'exchange_invoice' => [
            'status' => $exchange->status,
            'display_text' => $exchange->display_text,
            'display_name' => $exchange->display_name,
            'allow_adhoc_exchange' =>$vendorS->getAdhocExchange($sParams),
            'allow_inter_store_exchange' => $vendorS->getInterStoreExchange($sParams),
            'allow_price_override_exchange' => $vendorS->getPriceOverrideExchange($sParams),
            'against_invoice_exchange' =>  $vendorS->getAgainstInvoiceExchange($sParams),
         ],
         'barcode' => $barcode,
         'opening_balance_status' => $opening_balance_status,
         'settlement' => [
            'force_session_close'=>$foreclosedStatus,
             'sessionExpire'=>$vendorSett->getExpireDetails($request),
             'active_session_day_for'=>$vendorS->getActiveSessionday($sParams),
             'store_day_settlement'=>$vendorS->getStoreDaySettlement($sParams),
            'denomination' => [
                'status' => $vendorS->getSettlementDenominationStatus($sParams),
                'currency' => [
                    'notes' => ['2', '5',  '10', '20', '50', '100', '200', '500', '2000'],
                    'coins' => ['1', '2', '5', '10']
                ]
            ],
            //'previous_session_detail'=>$vendorSett->getPreviousSessionStatus($request),
            'opening_balance_status' => $opening_balance_status,
            'open_sesssion_compulsory' => $open_sesssion_compulsory,
            'closing_previous_session_compulsory' => $vendorS->getSessionCompulsorySettingFunction($sParams),
            'closing_previous_balance_status' => $closing_previous_balance_status,
            'session_started_at' => $session_started_at,
            'settlement_session_id' => $settlement_session_id,
            'allow_item_exchange' => $vendorS->getAllowItemExchangeSettings($sParams),
        ],
        'optimize_flow' => $optimize_flow,
        'return_authorization' => $return_authorization,
        'cart_avail_offer' => $vendorS->getCartAvailSetting($sParams),
        'scan_screen' => [
            'cart_qty_total_flag' =>  $vendorS->getScanScreenCartQtyTotalSetting($sParams),
            'total_flag' => $vendorS->getScanScreenTotalSetting($sParams),
            'offline_scan' => $vendorS->getScanScreenOffilneSetting($sParams),
            'offline_scan' => $offline_scan,
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
            'voucher_total_flag' => $vendorS->getCartVoucherTotalSetting($sParams),
            'enable_promotion' => $is_promotion_enable,
            'allow_adhoc_return' => $allow_returns->good_return->DEFAULT,
            'product_max_qty' => $product_max_qty,
            'cart_max_item' => $cart_max_item,
        ],
        'tax' => [
            'manual_apply' => $vendorS->getTaxManualSetting($sParams),
        ],
        'cart_menu' => $cart_menu,
        'order' => [
            'history' => [
                'total_flag' => $vendorS->getOrderHistoryTotalSetting($sParams)
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
                'invoice_button_flag' => $vendorS->getInvoiceButton($sParams)
            ],
            'lay_by' => $vendorS->getOrderLayBySetting($sParams),
            //'on_account' => $vendorS->getOrderOnAccountSetting($sParams),
            'on_account' => $vendorS->getAccountSaleActiveInactive($sParams),
            'oms' => $vendorS->getOMSSetting($sParams),
        ],
        'offer' => [
            'manual_discount' => $vendorS->getOfferManualDiscountSetting($sParams),
        ],
        'vendor_login_details' => $vendor_login_details,
        'stock' => $stock,
        'pine_lab'=>[
                    'MerchantID'           => $pineLabDetails['MerchantID'],
                    //'SecurityToken'        => env('LOGIN_SECURITY_TOKEN'),
                    'SecurityToken'        => $pineLabDetails['SecurityToken'],
                    'IMEI'                 => $merchantPosCodeDetails['imei'],
                    'MerchantStorePosCode' => $merchantPosCodeDetails['merchant_store_pos_code'] ,
                    'HardwareId'           => $merchantPosCodeDetails['hardware_id'],
            ],

        'numberOfItemInOneInvoice'=>$numberOfItemInOneInvoice,
        'sessionForceClosure'=>$vendorS->getSessionForceClosure($sParams),
        'invoiceCopiesSetting'=>$vendorS->getInvoiceCopiesSetting($sParams),
        'creditNoteCopiesSetting'=>$vendorS->getCreditNoteCopiesSetting($sParams),  

        'share_invoice_via' => [
            'allow_via_sms' => $featureSettings->invoice->sms,
            'allow_via_email' => $featureSettings->invoice->email,
        ],
        'store_account_details' => $orgDetails,//csdfsd
        'reprint_invoice'     => $featureSettings->bill_reprint->DEFAULT,
        'split_Payment' => $getPaymentTypeSetting,
        'negative_stock_billing' => $stock->negative_stock_billing,
        'session_alive' => $session_alive->session_compulsory->DEFAULT->options[0]->session_alive,
        'cashmanagement_limit' => $cashmanagement->cashmanagement->DEFAULT,
        'template' => [
            'cashmanagement_limit' => json_decode($storeSettings->template),
            'session_alive' => json_decode($settlementSettings->template),
            'negative_stock_billing' => json_decode($stockSettings->template),
            'split_Payment' => json_decode($paymentSettings->template),
            'reprint_invoice' => json_decode($featureSettings->template),
            'creditNoteCopiesSetting' => json_decode($featureSettings->template),
            'invoiceCopiesSetting' => json_decode($featureSettings->template),
            'sessionForceClosure' => json_decode($settlementSettings->template),
            'stock' => json_decode($stockSettings->template),
            'vendor_login_details' => json_decode($vendorAppSettings->template),
            'cart_avail_offer' => json_decode($cartSettings->template),
            'return_authorization' => json_decode($featureSettings->template),
            'optimize_flow' => json_decode($featureSettings->template),
            'barcode' => json_decode($featureSettings->template),
            'invoice' => json_decode($featureSettings->template),
            'feedback' => json_decode($featureSettings->template),
            'print' => json_decode($featureSettings->template),
            'toolbar' => json_decode($toolbarSettings->template),
            'store' => json_decode($storeSettings->template),
            'exchange_invoice' => json_decode($settlementSettings->template), 
            'share_invoice_via' => json_decode($featureSettings->template),
            'pine_lab' => ['template_id' => 1, 'settings_level' => 'store' , 'settings_id' => 12],
            'offer' => json_decode($offerSettings->template),
            'order' => json_decode($orderSettings->template),
            'tax' => json_decode($offerSettings->template), 
            'cart' => json_decode($cartSettings->template), 
            'scan_screen' => json_decode($scanSettings->template),
            'settlement' => json_decode($settlementSettings->template),  
            // 'customer_login' => json_decode($vendorAppSettings->template),
            // 'other_payment_method' => ['template_id' => 1, 'settings_level' => 'store' , 'settings_id' => 12],
            'vendor_app_menu' => json_decode($vendorAppSettings->template)
            // 'multiple_mop' => ['template_id' => 1, 'settings_level' => 'store' , 'settings_id' => 12],
            // 'payment_type' => ['template_id' => 1, 'settings_level' => 'store' , 'settings_id' => 12],
            // 'store_url_flag' => ['template_id' => 1, 'settings_level' => 'store' , 'settings_id' => 12],
            // 'app_update_msg' => ['template_id' => 1, 'settings_level' => 'store' , 'settings_id' => 12],
            // 'app_url' => ['template_id' => 1, 'settings_level' => 'store' , 'settings_id' => 12],
            // 'force_fully_update' => ['template_id' => 1, 'settings_level' => 'store' , 'settings_id' => 12],
            // 'latest_version' => ['template_id' => 1, 'settings_level' => 'store' , 'settings_id' => 12]
        ]
        
    ];

    if ($response_format == 'ARRAY') { 

        return ['settings' => $responseData, 'status' => 'success'];
    } else {

        return response()->json(['settings' => $responseData, 'status' => 'success'], 200);
    }
}


    public function getCurrencyDetail($params){

        $v_id     = $params['v_id'];
        $store_id = $params['store_id'];
        $vu_id    = $params['vu_id'];
        $infoType = isset($params['info_type'])?$params['info_type']:'CURRENCY';

        $currency = [];
        $phoneinfo= [];
        $getVendorAuthDetail  = VendorAuth::find($vu_id);
        
        //Store Level
        $getStoreDetail   = Store::find($store_id);
        //echo $getStoreDetail->country ;die;
        if(isset($getStoreDetail->country) && $getStoreDetail->country !=0){
            $country  = $getStoreDetail->countryDetail;
            $currency['name']       = $country->currency_name;
            $currency['symbol']     =  html_entity_decode(trim($country->currency_html_code));
            $currency['html_code']  = $country->currency_html_code;
            $currency['code']       = $country->currency_code;
            $currency['country_id']   = $country->id;

            $phoneinfo['country']     = $country->name;
            $phoneinfo['country_code']= $country->sortname;
            $phoneinfo['phone_digit'] = $country->phone_digit;
            $phoneinfo['dial_code']   = $country->dial_code;
            $phoneinfo['country_id']   = $country->id;
        }else{
            //Vendor Level
            $getVendorDetail  = VendorDetails::where('v_id',$v_id)->first();
            if($getVendorDetail->country !=0){
            $country                = $getVendorDetail->countryDetail;
            $currency['name']       = $country->currency_name;
            $currency['symbol']     = html_entity_decode($country->currency_html_code);
            $currency['html_code']  = $country->currency_html_code;
            $currency['code']       = $country->currency_code;
            $currency['country_id']   = $country->id;
            
            $phoneinfo['country']     = $country->name;
            $phoneinfo['country_code']= $country->sortname;
            $phoneinfo['phone_digit'] = $country->phone_digit;
            $phoneinfo['dial_code']   = $country->dial_code;
            $phoneinfo['country_id']   = $country->id;

            }else{
                //Default Currency
                $country               = Country::where('sortname',$this->defaultCountry)->first();
                $currency['name']      = $country->currency_name;
                $currency['symbol']    = html_entity_decode($country->currency_html_code);
                $currency['html_code'] = $country->currency_html_code;
                $currency['code']       = $country->currency_code;
                $currency['country_id']   = $country->id;
                 
                $phoneinfo['country']     = $country->name;
                $phoneinfo['country_code']= $country->sortname;
                $phoneinfo['phone_digit'] = $country->phone_digit;
                $phoneinfo['dial_code']   = $country->dial_code;
                $phoneinfo['country_id']   = $country->id;
            }
        }
        if($infoType =='CURRENCY'){
            return $currency;
        }else{
            return $phoneinfo;
        }
        
    }//End of getCurrencyDetail

    public function getTaxLabel($params){
        $currency   = $this->getCurrencyDetail($params);
        if($currency['country_code'] == 'CA'){
          $data['tax_label_1'] = 'GST';
          $data['tax_label_2'] = 'PGST';
          $data['tax_label_3'] = '';
          $data['tax_label_4'] = '';
          $print_var = 'GST,';

        }else{
          $data['tax_label_1'] = 'CGST';
          $data['tax_label_2'] = 'PGST';
          $data['tax_label_3'] = 'IGST';
          $data['tax_label_4'] = 'CESS';
        }


    }

    private function getmerchantStoreCode($parms)
    {
       // $vendor = VendorAuth::select('mobile')->where('id', $parms['user_id'])->first();
       // $pineLabDevice = PineLabDevice::where('imei', $vendor['mobile'])->first();
        $pineLab = DB::table('pinelab')->where('v_id', $parms['v_id'])->first();
        if(!empty($pineLab)){
            $mapping_by=$pineLab->mapping_by;
            if($mapping_by=='cashier'){
                $pineLabDevice = PineLabDevice::where('cashier_id', $parms['user_id'])->first();
            }elseif($mapping_by=='terminal'){
                $terminal_data = DB::table('cash_registers')->where('v_id', $parms['v_id'])->where('udidtoken', $parms['udidtoken'])->first();
                if(!empty($terminal_data->id)){
                    $pineLabDevice = PineLabDevice::select('imei','merchant_store_pos_code','hardware_id')->where('terminal_id', $terminal_data->id)->first();
                }else{
                    $pineLabDevice=array('imei'=>'','merchant_store_pos_code'=>'','hardware_id'=>'');
                }
            }
        }else{
                $pineLabDevice=array('imei'=>'','merchant_store_pos_code'=>'','hardware_id'=>'');
                
        }
         
        return $pineLabDevice;
        
    }

    private function getPineLabDetails($parms)
    {
        $pineLab = DB::table('pinelab')->where('v_id', $parms['v_id'])->first();
        if(empty($pineLab)){
            $dataReturn=array('MerchantID'=>'','SecurityToken'=>'');
        }else{
            $MerchantID=isset($pineLab->merchant_id)?$pineLab->merchant_id:'';
            $SecurityToken=isset($pineLab->security_token)?$pineLab->security_token:'';
            $dataReturn=array('MerchantID'=>$MerchantID,'SecurityToken'=>$SecurityToken);
        }

        return $dataReturn;
    }

    public function get_settings_console(Request $request)
    {
        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $user_id = $request->vu_id;
        $role = VendorRoleUserMapping::select('role_id')->where('user_id', $user_id)->first();
        $role_id = $role->role_id;
        $sParams = ['v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $user_id, 'role_id' => $role_id];
        $vendorS = new VendorSettingController;
        $stock = $vendorS->getInventorySetting($sParams);
        
        $responseData = [
            'inventory_settings' => $stock,

        ];
        return response()->json(['settings' => $responseData, 'status' => 'success'], 200);

    }




}
