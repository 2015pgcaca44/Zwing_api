<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Traits\VendorFactoryTrait;
use App\Mail\OrderCreated;
use Illuminate\Support\Facades\Mail;
use PDF;
use App\Store;
use App\Order;
use App\Cart;
use App\User;
use App\Invoice;
use App\Vendor;
use DB;
use Auth;
use App\Vendor\VendorGroup;
use App\Vendor\VendorRole;

class ReturnController extends Controller
{
    use VendorFactoryTrait;

    public function __construct()
    {
        //$this->middleware('auth',   ['except' => ['order_receipt']]);
    }

    public function authorized(Request $request){
        //$vu_id = $request->vu_id;
        $v_id = $request->v_id;
        //$c_id = $request->c_id;
        $store_id = $request->store_id; 
        $trans_from = $request->trans_from;
        $security_code = $request->security_code;

        //$order_id = $order_id; 
        $operation = 'return_authorization';

        //$vendor = Vendor::where('vendor_user_random', $security_code)->where('v_id',$v_id)->first();

        $vendor   = Vendor::join('vendor_role_user_mapping as vrum','vrum.user_id','vendor_auth.id')
                            ->join('vendor_roles as vr','vr.id','vrum.role_id')
                            ->where('vr.code','store_manager')
                            ->where('vendor_auth.v_id',$v_id)
                            ->where('vendor_auth.store_id',$store_id)
                            ->where('vendor_auth.status','1')
                            ->where('vendor_auth.vendor_user_random',$security_code)
                            ->first();


        if(!$vendor){
            return response()->json(['status' => 'fail' , 'message' => 'Code is incorrect']);
        }
        $vRole = VendorRole::select('v_id','code')->where('v_id',$vendor->v_id)->where('code','store_manager')->first();
        // $where     = array('vendor_id'=>$v_id,'store_id'=>$store_id,'type'=>'manager');
        // $vendor    = Vendor::where('vendor_user_random', $security_code)->where($where)->first();
        
        $vu_id = $request->vu_id;
        if($vendor){
            DB::table('operation_verification_log')->insert([ 'v_id' => $v_id, 'store_id' => $store_id, 
                //'c_id' =>$user->c_id,
                 'trans_from' => $trans_from, 'vu_id' => $vu_id ,'operation' => $operation ,
                 // 'order_id' => $order_id ,
                  'verify_by' =>  $vendor->id , 'created_at' => date('Y-m-d H:i:s') ]);

            return response()->json(['status' => 'success', 'message' => 'You are Authorized' ]);
        }else{

            return response()->json(['status' => 'fail', 'message' => 'You are not  Authorized User' ]);

        }

    }
    
    public function get_order(Request $request){
        
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    // public function get_order(Request $request)
    // {
    //     $v_id = $request->v_id;
    //     //$c_id = $request->c_id;
    //     $store_id = $request->store_id;
    //     $cust_order = $request->cust_order;
    //     $trans_from = $request->trans_from;

    //     if(is_numeric($cust_order)){
            
    //         //$profileC = new ProfileController;
    //         $cust = User::where('mobile' ,$cust_order)->first();

    //         if($cust){

    //             return response()->json(['status' => 'success' , 'go_to' => 'my_order' , 'data' => [ 'c_id' => $cust->c_id , 'api_token' => $cust->api_token ] ]);
                
    //         }else{
    //             return response()->json(['status' => 'fail', 'message' => 'Unable to find the customer' ]);
    //         }

    //     }else{
    //         //$cartC = new CartController;

    //         $order = Invoice::where('invoice_id', $cust_order)->where('v_id', $v_id)->first();
    //         if($order){

    //             $request->request->add();
    //             //return $cartC->order_details($request);
    //             $cust = User::where('c_id' ,$order->user_id)->first();
    //             return response()->json(['status' => 'success' , 'go_to' => 'order_details' , 'data' => [ 'v_id' => $order->v_id , 'store_id' => $order->store_id , 'c_id' => $order->user_id, 'api_token' => $cust->api_token, 'order_id' => $cust_order, 'trans_from' => $trans_from ]
    //              ]);
                
    //         }else{

    //             return response()->json(['status' => 'fail', 'message' => 'Unable to find the Order' ]);
    //         }

    //     }
    }

    public function get_return_item(Request $request){
        
        return $this->callMethod($request, __CLASS__, __METHOD__ );

    }

    public function return_request(Request $request){
     
        
        return $this->callMethod($request, __CLASS__, __METHOD__ );    

    }


    public function approve(Request $request){

        return $this->callMethod($request, __CLASS__, __METHOD__ );

    }

    public function update(Request $request){
        
         return $this->callMethod($request, __CLASS__, __METHOD__ );

    }

    public function delete(Request $request){
        
         return $this->callMethod($request, __CLASS__, __METHOD__ );
    }

}