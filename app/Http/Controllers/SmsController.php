<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Auth;
use App\User;
use App\Order;
use App\Otp;
use App\SmsLog;
use App\Store;

class SmsController extends Controller
{
    private $username = "roxfortgroup@gmail.com";
    private $hash = "bb7a4a8a91634f50df712fdf5c11ef6745ea735b85afc9443d23ec9ae3e8b711";
    private $test = "0";
    private $sender = "MZWING";

    public function __construct()
    {
        $this->middleware('auth' , ['except' => ['send_otp'] ]);
    }
    public function testsms($params){

            // SMS API
            $username = "roxfortgroup@gmail.com";
            $hash = "bb7a4a8a91634f50df712fdf5c11ef6745ea735b85afc9443d23ec9ae3e8b711";
            $test = "0";
            $sender = "MZWING";

            $mobile = 9930387351;
           

            $otp = rand(1111,9999);
            //$user_otp_update = User::where('mobile', $mobile)->where('mobile_active', 1)->update(['otp' => $otp]);
            $numbers = "91".$mobile; 
            $message = "http://localhost/zwing/laraapi/public/receipt/MTc4LzE2LzE4L09EOTAxNTAxODM3MTUxMjk1MDQx";
            $message = urlencode($message);
            $data = "username=".$username."&hash=".$hash."&message=".$message."&sender=".$sender."&numbers=".$numbers."&test=".$test;
            $ch = curl_init('http://api.textlocal.in/send/?');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch); 
            curl_close($ch);
            dd($result);
            //$api_null = User::where('mobile', $mobile)->update(['api_token' => null]);
            return response()->json(['status' => 'otp_sent', 'message' => 'OTP Send Successfully', 'mobile' => $mobile, 'c_id' => $user_otp_update], 200);

    }
        
    public function send_sms($params){

        $username = $this->username;
        $hash = $this->hash;
        $test = $this->test;
        $sender = $this->sender;
        $mobile = $params['mobile'];
   
        $numbers = "91".$mobile; 
        $message = $params['message'];

        $message = urlencode($message);

        $data = "username=".$username."&hash=".$hash."&message=".$message."&sender=".$sender."&numbers=".$numbers."&test=".$test;
        $url = 'http://api.textlocal.in/send/?';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch); 

        curl_close($ch);

        $response = json_decode($result);
        if(isset($params['v_id'])  &&  $params['v_id']!= ''){

            $sms = new SmsLog;

            $sms->v_id = $params['v_id'];
            $sms->store_id = $params['store_id'];
            $sms->request = $url.$data;
            $sms->response = $result;
            if(isset($params['for'])  &&  $params['for']!= ''){
                $sms->for = $params['for'];
            }
            $sms->save();
        }
        // dd('k',$response,$response->status);
        if($response->status == 'success'){
             return ['status' => 'success', 'message' => 'message sent Successfully' ];   
        }else{
                return ['status' => 'failure', 'message' => 'Unable to send a message' , 'detail_message' => json_decode($result) ];  
        }
                
            
    }


    public function get_templates(){
        $hash = $this->hash;
            // Account details
            $apiKey = urlencode('Your apiKey');

            // Prepare data for POST request
            $data = array('apikey' => $hash);

            // Send the POST request with cURL
            $ch = curl_init('https://api.textlocal.in/get_templates/');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            // Process your response here
            echo $response;
    }

    public function send_otp(Request $request){
// dd($vendor);\\
        // echo "avc";
// dd($request);
        // dd('hi');
        // dd('hi');
        $for = $request->for;//REGISTRATION,FORGOT_PASSWORD, GET_OFFERS
        $user_type = $request->user_type; //CUSTOMER,VENDOR_USER,VENDOR_ADMIN
        $mobile = $request->mobile;
        $store_id  = $request->store_id;
        $store          = Store::find($store_id);
        // dd($for);
        $store_name = '';
        if(isset($store->name)){
            $store_name = $store->name;
        }else{
            $store_name = '';
        }
        // dd($store_name);
        $StoreName      = substr($store_name, 0,20);
        // dd($StoreName);
        $v_id = '';
        if($request->has('v_id')){
            $v_id = $request->v_id;
        }

        $otps = rand(111111,999999);
// dd($otps);
        if($for == 'GET_OFFERS'){
         $TransactionName  = 'Offers';
        }else if($for == 'REGISTRATION'){
         $TransactionName  = 'Customer Registraion';
        }else if($for == 'PASSWORD'){
         $TransactionName  = 'Password';
        }else{
         $TransactionName = '';
        }
// dd($TransactionName);
        //$message = "Welcome to ZWING your otp is ".$otps;
        $message   = "OTP for $TransactionName at $StoreName is $otps";
        // dd($message);
        $expired_at = date('Y-m-d H:i:s' , strtotime("+15 minutes", strtotime(Date('Y-m-d H:i:s')) ));
        // dd($expired_at);
        if($for == 'GET_OFFERS'){
            $user = User::where('mobile', $mobile)->where('status', '1')->first();
            if(!$user){
                return response()->json(['status' => 'success', 'message' => 'User not exists' ], 200); 
            }
            $otp = new Otp;
            $otp->otp = $otps;
            $otp->for = $for;
            $otp->expired_at = $expired_at;
            $otp->mobile = $mobile;
            $otp->user_type = $user_type;
            $otp->status = 'PENDING';
            $otp->save();
        }

        $params= ['mobile' => $mobile , 'message' => $message , 'for' => $for, 'v_id' => $v_id ,'store_id' => $store_id ];
        // dd($params);
        return $this->send_sms($params);

    }

    public function verify_otp(Request $request){
        
        $for = $request->for;//REGISTRATION,FORGOT_PASSWORD, GET_OFFERS
        $user_type = $request->user_type; //CUSTOMER,VENDOR_USER,VENDOR_ADMIN
        $mobile = $request->mobile;
        $otp = $request->otp;

        $otp = Otp::where('mobile',$mobile)->where('user_type',$user_type)->where('for',$for)->where('otp', $otp)->first();

        if($otp){

            if($otp->status=='VERIFIED'){
                $response =  ['status' => 'fail' , 'message' => 'This otp is already verified'];
            }else{

                if(strtotime($otp->expired_at) >= strtotime(Date('Y-m-d H:i:s')) ){

                    $otp->status = 'VERIFIED';
                    $otp->save();

                    $response =  ['status' => 'success' , 'message' => 'Your otp has been verified successfully'];
                }else{
                    $response =  ['status' => 'fail' , 'message' => 'Your otp has been Expired'];
                }
            }

        }else{
            $response =  ['status' => 'fail' , 'message' => 'You have entered wrong otp'];
        }

    
        return response()->json( $response,200);    
    }




    public function send_bill_receipt(Request $request){

        $v_id = $request->v_id;
        $order_id = $request->order_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;
        $trans_from = $request->trans_from;
        if(!empty($request->type)){
            $type  = $request->type;
        }

        $user = User::select('mobile')->where('c_id',$c_id)->first();
        
        if(isset($params['store_name'])){
            $StoreName      = substr($params['store_name'], 0,20);
        }else{
            $store          = Store::find($store_id);
            $StoreName      = substr($store->name, 0,20);
        }

        //$message = 'Click To Open Your Receipt ';

        $message   = "Dear Customer, Thank your for shopping at $StoreName. Please tap on the link to get your e-invoice. ";

        $para = base64_encode($c_id.'/'.$v_id.'/'.$store_id.'/'.$order_id);
        //$message .= env('API_URL').'/receipt/'.$para;

        //{c_id}/{v_id}/{store_id}/{order_id}
        if(empty($type)){
            $message  .= env('API_URL').'/order-receipt/'.$c_id.'/'.$v_id.'/'.$store_id.'/'.$order_id;
        }else{
            $message  .= env('API_URL').'/order-receipt/'.$c_id.'/'.$v_id.'/'.$store_id.'/'.$order_id.'?type='.$type;
        }
        
        $mobile = $user->mobile;
        if($request->has('mobile') && $request->mobile != ''){
            $mobile = $request->mobile;
        }
        //echo $message;exit;
        $sms_params = ['mobile' => $mobile , 'message' => $message ];

        $response = $this->send_sms($sms_params);
        if($response['status'] == 'success'){

                if($trans_from == 'ANDROID_KIOSK'){
                        $order = Order::where('user_id',$c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->update(['verify_status' => '0' , 'verify_status_guard' => '0']);
                }else if($trans_from == 'ANDROID_VENDOR' || $trans_from == 'CLOUD_TAB' || $trans_from == 'CLOUD_TAB_ANDROID' || $trans_from == 'CLOUD_TAB_WEB' ){
                        $order = Order::where('user_id',$c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->update(['verify_status' => '1' , 'verify_status_guard' => '1']);
                }

                 return response()->json(['status' => 'success', 'message' => $response['message'] ], 200);

        }else{

                return response()->json(['status' => 'fail', 'message' => $response['message'] ], 200);

        }

    }

    public function send_voucher($params){

        $mobile         = $params['mobile'];
        $voucher_amount = $params['voucher_amount'];
        $voucher_no     = $params['voucher_no'];
        $expiry_date    = $params['expiry_date'];
        $v_id           = $params['v_id'];
        $store_id       = $params['store_id'];
        $store          = Store::find($store_id);
        if(isset($params['store_name'])){
            $StoreName      = substr($params['store_name'], 0,20);
        }else{
            $StoreName      = substr($store->name, 0,20);
        }




        // $message = "You have received a voucher of Rs ".format_number($voucher_amount).". Your code is ".$voucher_no." Expire at ".$expiry_date.".";
        $message  = "Dear Customer, Your return was successfully placed at $StoreName. Your store credit voucher worth ".format_number($voucher_amount)." is $voucher_no, valid till $expiry_date";
 
        // $message .= ' one time use only';
         $param= ['mobile' => $mobile , 'message' => $message , 'for' => 'VOUCHER' ,'v_id' => $v_id, 'store_id' => $store_id ];

         return $this->send_sms($param);
    }


}