<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Auth;
use App\User;
use App\Order;

class SmsController extends Controller
{
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

		$username = "roxfortgroup@gmail.com";
                $hash = "bb7a4a8a91634f50df712fdf5c11ef6745ea735b85afc9443d23ec9ae3e8b711";
                $test = "0";
                $sender = "MZWING";

                $mobile = $params['mobile'];
           
                $numbers = "91".$mobile; 
                $message = $params['message'];
				//dd($message);
                $message = urlencode($message);
                $data = "username=".$username."&hash=".$hash."&message=".$message."&sender=".$sender."&numbers=".$numbers."&test=".$test;
                //dd($data);
                $ch = curl_init('http://api.textlocal.in/send/?');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $result = curl_exec($ch); 
                curl_close($ch);

                $response = json_decode($result);
                //dd(json_decode($result));
                if($response->status == 'success'){
                     return ['status' => 'success', 'message' => 'message sent Successfully' ];   
                }else{
                        return ['status' => 'fail', 'message' => 'Unable to send a message' ];  
                }
        			
                
	}


        public function send_bill_receipt(Request $request){

                $v_id = $request->v_id;
                $order_id = $request->order_id;
                $c_id = $request->c_id;
                $store_id = $request->store_id;
                $trans_from = $request->trans_from;

                $user = User::select('mobile')->where('c_id',$c_id)->first();

                $message = 'Click To Open Your Receipt ';
                $para = base64_encode($c_id.'/'.$v_id.'/'.$store_id.'/'.$order_id);
                $message .= env('API_URL').'/receipt/'.$para;
                //echo $message;exit;
                $sms_params = ['mobile' => $user->mobile , 'message' => $message ];

                $response = $this->send_sms($sms_params);
                if($response['status'] == 'success'){

                        if($trans_from == 'ANDROID_KIOSK'){
                                $order = Order::where('user_id',$c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->update(['verify_status' => '0' , 'verify_status_guard' => '0']);
                        }else if($trans_from == 'ANDROID_VENDOR'){
                                $order = Order::where('user_id',$c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->update(['verify_status' => '1' , 'verify_status_guard' => '0']);
                        }

                         return response()->json(['status' => 'success', 'message' => $response['message'] ], 200);

                }else{

                        return response()->json(['status' => 'fail', 'message' => $response['message'] ], 200);

                }

        }

}