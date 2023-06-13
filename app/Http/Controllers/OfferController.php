<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Traits\VendorFactoryTrait;
use Illuminate\Http\File;
use DB;
use Illuminate\Support\Facades\Crypt;
use App\Rating;
use App\PopUpCustomer;
use Auth;
use App\Order;
use App\Cart;
use App\Payment;
use App\User;
use App\VendorSetting;
use App\CartDiscount;
use App\Organisation;
use App\ManualDiscount;
use App\MdAllocation;
use App\CustomerGroupMapping;    
use App\DepRfdTrans;    
use App\Store;    
use App\Voucher;
use App\Model\Items\VendorSkuAssortmentMapping;
use App\CustomerGroup; 
use App\Model\Items\VendorSku;
use App\Model\Items\VendorItem;
use App\Model\Items\VendorSkuDetailBarcode;
use App\Vendor\VendorRoleUserMapping;
use App\Carry;
use App\CrDrSettlementLog;
use App\Http\Controllers\CloudPos\AccountsaleController;
// use App\DepRfdTrans;

class OfferController extends Controller
{
    use VendorFactoryTrait;

    public function __construct()
    {
        $this->middleware('auth',['except' => ['account_sale']]);
    }
    
    
    public function popup_offer_viewed(Request $request){

        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $pop_up_id = $request->pop_up_id;

        $c_id = Auth::user()->c_id;

        $viewed = new PopUpCustomer;

        $viewed->v_id = $v_id;
        $viewed->store_id = $store_id;
        $viewed->pop_up_id = $pop_up_id;
        $viewed->c_id = $c_id;
        $viewed->viewed = '1';
        $viewed->save();

     return response()->json(['status' => 'success' , 'message' => 'Offer is viewed' ],200);

    }


    public function list(Request $request)
    {
        $offers[] = [ 'image'=> 'https://zwing.in/vendor/vendorstuff/store/offers/offers_1.png', 'offer_id' => 1 ];
        $offers[] = [ 'image'=> 'https://zwing.in/vendor/vendorstuff/store/offers/offers_1.png', 'offer_id' => 2 ];
        $offers[] = [ 'image'=> 'https://zwing.in/vendor/vendorstuff/store/offers/offers_1.png', 'offer_id' => 3 ];

        $data['slider'] = $offers;


        $data['offers'][] =  [ 'title' => 'Festive Special Offer' ,
                             'list' => [
                                            [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/cycling.png',
                                             'product_name' => 'Cycling',
                                             'offer' => 'Upto 30% off'
                                            ],
                                            [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/bag.png',
                                             'product_name' => 'Quechua',
                                             'offer' => 'Clearance Sale'
                                            ],
                                            [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/shoe.png',
                                             'product_name' => 'Running Shoes',
                                             'offer' => 'Upto 10% off'
                                            ]
                                        ]
                           ] ;

        $data['offers'][] =  [ 'title' => 'Top Deals' ,
                             'list' =>  [
                                            [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/softRing.png',
                                             'product_name' => 'Triboard Soft Ring',
                                             'offer' => 'Buy 1 Get 1'
                                            ],
                                            [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/bodybuilding.png',
                                             'product_name' => 'Dumbbell Set 20kg',
                                             'offer' => 'Flat 20% off'
                                            ],
                                             [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/jersey.jpg',
                                             'product_name' => 'Football Jerseys',
                                             'offer' => 'Now for RS 299'
                                            ]

                                        ]
                           ] ;
                           
                           
        
        $data['offers'][] =  [ 'title' => 'Festive Special Offer' ,
                             'list' => [
                                            [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/cycling.png',
                                             'product_name' => 'Cycling',
                                             'offer' => 'Upto 30% off'
                                            ],
                                            [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/bag.png',
                                             'product_name' => 'Quechua',
                                             'offer' => 'Clearance Sale'
                                            ],
                                            [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/shoe.png',
                                             'product_name' => 'Running Shoes',
                                             'offer' => 'Upto 10% off'
                                            ]
                                        ]
                           ] ;

        $data['offers'][] =  [ 'title' => 'Top Deals' ,
                             'list' =>  [
                                            [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/softRing.png',
                                             'product_name' => 'Triboard Soft Ring',
                                             'offer' => 'Buy 1 Get 1'
                                            ],
                                            [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/bodybuilding.png',
                                             'product_name' => 'Dumbbell Set 20kg',
                                             'offer' => 'Flat 20% off'
                                            ],
                                             [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/jersey.jpg',
                                             'product_name' => 'Football Jerseys',
                                             'offer' => 'Now for RS 299'
                                            ]

                                        ]
                           ] ;




        return response()->json(['status' => 'offer_list', 'message' => 'Offer List', 'data' => $data ],200);

    }


    public function get_offers(Request $request)
    {
        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $c_id = $request->c_id;

        $get_by = '';
        if($request->has('get_by') && $request->get('get_by')) {
            $get_by = $request->get_by; //VOUCHER_NO , MOBILE_NO
        }
        
        $trans_from = 'ANDROID';
        if($request->has('trans_from') && $request->get('trans_from')) {
            $trans_from =  $request->trans_from;
        }

        // $store_db_name = get_store_db_name([ 'store_id' => $store_id ]);
        if($request->has('mobile') && $request->get('mobile')) {
            $user = User::select('c_id')->where([ 'mobile' => $request->mobile, 'v_id' => $v_id, 'status' => 1 ])->first();
            if(!empty($user)) {
                $c_id = $user->c_id;
            } else {
                $response = ['status' => 'fail' , 'message' => 'Customer Not found'];
                return $response;
            }
        }
        $order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
        $order_id = $order_id + 1;
        
        $carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status','process')->get();

        //$employee_discount = $carts->sum('employee_discount');
        $coupon_voucher = [];
        //$company_discount = DB::table($store_db_name.'.company_discount')->get();
        //$company_list = $company_discount->pluck('company_name')->all();
        
        //$employee_discount = [ 'type' => 'employee_discount' , 'name' => 'SPAR' , 'title' => 'Employee Discount' , 'desc' => '',  'applied_status' => ($employee_discount >0.00)?true:false , 'company_list' => $company_list ];

        $today_date = date('Y-m-d H:i:s');
        //array_push($coupon_voucher, $employee_discount);
        $vouchers = Voucher::select('id','stores.name as store_name','cr_dr_voucher.voucher_no','cr_dr_voucher.amount','cr_dr_voucher.type','cr_dr_voucher.expired_at','cr_dr_voucher.dep_ref_trans_ref')->leftjoin('stores','stores.store_id','cr_dr_voucher.store_id')->where('cr_dr_voucher.store_id', $store_id)->where('cr_dr_voucher.v_id', $v_id)->where('cr_dr_voucher.user_id', $c_id)->where('cr_dr_voucher.status','!=','used')->where('cr_dr_voucher.type','voucher_credit')->where('cr_dr_voucher.effective_at' ,'<=' ,$today_date )->where('cr_dr_voucher.expired_at','>=' , $today_date)->get();
        // dd($vouchers);

        //This condition is added to get the applied voucher which is alread used 
        if($request->has('order_id') && $request->get('order_id')) {
            $payments = Payment::where('order_id', $request->order_id)->where('payment_gateway_type', 'VOUCHER')->get();
            $o_id = [];
            foreach($payments as $payment){
                $pay_id = explode('_',$payment->pay_id);
                array_push($o_id, end($pay_id));
            }

            $vouchers_applied = DB::table('cr_dr_settlement_log')->select('voucher_id')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->whereIn('order_id',$o_id)->get();

            $newVouchers = DB::table('cr_dr_voucher')->select('id','voucher_no','amount','type','expired_at')->where('status','used')->whereIn('id', $vouchers_applied->pluck('voucher_id')->all() )->get();

            //$vouchers = $vouchers->merge($newVouchers);
             $vouchers = $newVouchers->merge($vouchers);

        }

        // $vouchers_applied = CrDrSettlementLog::select('voucher_id')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->get();
        $vouchers_applied = CrDrSettlementLog::select('voucher_id')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('order_id',$order_id)->get();

        $vouchers_ids = $vouchers_applied->pluck('voucher_id')->all();

        foreach($vouchers as $voucher) {
            $applied_status = (in_array($voucher->id, $vouchers_ids)?true:false);
            if($get_by == 'VOUCHER_NO') {
                if(!$applied_status) {
                    continue;
                }
            }

            $voucherAmount = $voucher->amount;
            $previous_applied = CrDrSettlementLog::select('applied_amount')->where('voucher_id' , $voucher->id)->where('status','APPLIED');
            
            if($request->has('order_id') && $request->order_id != '') {
               $previous_applied = $previous_applied->where('order_id', '!=', $order_id); 
            }
            $previous_applied = $previous_applied->get();

            $voucherAmount -= $previous_applied->sum('applied_amount');
            if(empty($voucher->dep_ref_trans_ref)) {
                $voucherAmount -= $previous_applied->sum('applied_amount');
            } else {
                // $checkDep = DepRfdTrans::where([ 'v_id' => $request->v_id, 'id' => $voucher->dep_ref_trans_ref ])->first();
                // if($checkDep->trans_src == 'order') {
                //     $voucherAmount -= $previous_applied->sum('applied_amount');  
                // } else {
                    $voucherAmount = $previous_applied->sum('applied_amount');      
                // }
            }
            

            $expired_at = date('d-M-Y', strtotime($voucher->expired_at));
            $store_credit = [ 'type' => 'voucher' , 'name' => @$voucher->store_name , 'title' => 'store credit' , 'desc' => 'Voucher Credit',  'amount' => (string)$voucherAmount , 'voucher_no' => $voucher->voucher_no , 'voucher_id' => $voucher->id , 'applied_status' => $applied_status , 'expired_at' => $expired_at ];

            array_push($coupon_voucher, $store_credit);
        }
        
        if(count($coupon_voucher) > 0 ) {
            $data = [ 'coupon_voucher' => $coupon_voucher ];
            $response = ['status' => 'offers', 'message' => 'Available Offers' , 'data' => $data ];
        } else {
            if($request->get_by == "VOUCHER_NO") {
                $data = [ 'coupon_voucher' => $coupon_voucher ];
                $response = ['status' => 'offers', 'message' => 'Available Offers' , 'data' => $data ];
            } else {
                $response = ['status' => 'fail', 'message' => 'No Voucher Available'  ];
            }
        }

        if($request->has('response_format') &&  $request->response_format == 'ARRAY' && $request->has('response_format')) {
            return $response;
        } else {
            return response()->json( $response,200);    
        }
    }


    public function apply_voucher(Request $request){
        
        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $trans_from = $request->trans_from;
        $c_id = $request->c_id;
        $type = $request->type;
        $newVocherData = '';

        $total_payable = 0;
        if($request->has('total_payable') && $request->get('total_payable')){
            $total_payable = $request->total_payable;
        }

        if($request->has('mobile') && $request->get('mobile')){
            $user = User::select('c_id')->where('mobile', $request->mobile)->where('v_id', $v_id)->first();
            if($user){
                $c_id = $user->c_id;
            }else{
                $response = ['status' => 'fail' , 'message' => 'Customer Not found'];
                return $response;
            }
        }
        
        if($request->has('voucher_code') && $request->get('voucher_code')){
            $voucher_code = $request->voucher_code;
            $firstChar = substr($voucher_code, 0, 3);
            
            //Offer get Created and applied here dynamically
            if($firstChar == 'JUD'){// This condition is added only for JustDelicious
                $amount = substr($voucher_code, 3);
                //dd($amount);
                $today_date = date('Y-m-d H:i:s');
                $next_date =  date('Y-m-d H:i:s' ,strtotime('+30 days', strtotime($today_date)) );

                $voucher = DB::table('voucher')->select('id','voucher_no','amount','type','status','expired_at')->where('voucher_no', $voucher_code)->where('v_id', $v_id)->where('user_id', $c_id)->first();
                if($voucher){

                     $voucher = DB::table('cr_dr_voucher')->select('id','voucher_no','amount','type','status','expired_at')->where('voucher_no', $voucher_code)->where('v_id', $v_id)->where('user_id', $c_id)->update(['voucher_no' => $voucher_code , 'amount' => $amount , 'status' => 'unused', 'effective_at' => $today_date   , 'expired_at' => $next_date ]);

                }else{

                    $voucher = DB::table('cr_dr_voucher')->insert(['v_id' => $v_id, 'store_id' => $store_id , 'user_id' => $c_id , 'voucher_no' => $voucher_code , 'amount' => $amount , 'type' => 'voucher_credit' ,'status' => 'unused', 'effective_at' => $today_date   , 'expired_at' => $next_date ]);
                }


            }
            $voucher = DB::table('cr_dr_voucher')->select('id','voucher_no','amount','type','status','expired_at')->where('voucher_no', $voucher_code)->where('v_id', $v_id)->where('user_id', $c_id)->first();

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
            $voucher_ids[] = [ "voucher_id" => $voucher->id ];
        }else{
            //dd($bags);
            $voucher_ids = json_decode(urldecode($request->voucher_id), true);
            if(is_array($voucher_ids) ){
               $voucher_ids;
            }else{
                $voucher_ids = [] ;
                $voucher_ids[] = [ "voucher_id" => $request->voucher_id ];
            }
        }
        //dd($voucher_ids);
        if($type == 'voucher'){

            $response = ['status' => 'fail' , 'message' => 'Unable of applied any voucher'];
            $voucher_applied_flag = false;
            if($request->has('order_id') && $request->get('order_id')){
              $request->request->add(['order_id' => $request->order_id]);  
            }
            $request->request->add(['all' => 1]);
            $this->remove_voucher($request);

            foreach ($voucher_ids as $key => $vou) {

                $voucher_id = $vou['voucher_id'];
                $voucher_amount = 0;
                $voucher = DB::table('cr_dr_voucher')->select('id','voucher_no','amount','type','dep_ref_trans_ref')->where('id', $voucher_id)->first();

                $voucherAmount = $voucher->amount;
                $voucher_applied = DB::table('cr_dr_settlement_log')->select('applied_amount')->where('voucher_id', $voucher_id)->get();

                if(empty($voucher->dep_ref_trans_ref) ||  $voucher->dep_ref_trans_ref==''){
                   $voucher_remain_amount = $voucherAmount - $voucher_applied->sum('applied_amount');
                }else{
                    $voucher_remain_amount =  $voucher_applied->sum('applied_amount');    
                }

                
                //$voucher_remain_amount = $voucherAmount - $voucher_applied->sum('applied_amount'); //comment when new scenerio start
                
                if($voucher){

                    if($voucher->amount > 0.00){
                       
                        if( $total_payable > 0 ){
                            $applied_amount = 0;
                            if($total_payable >= $voucher_remain_amount ){
                                $applied_amount = $voucher_remain_amount;
                                $total_payable = $total_payable - $voucher_remain_amount;
                            }else{
                                $applied_amount = $total_payable;
                                $total_payable = 0;
                            }

                            $order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
                            $order_id = $order_id + 1;

                            $paramsVr = array('status'=> 'Process','tran_sub_type'=>'Redeem-CN','amount'=> $applied_amount);
                            $request->merge([
                            //'order_id' => $invoice->invoice_id
                            'tr_type'       => 'Debit',
                            'user_id'  => $c_id,
                            ]);
                            $actSaleCtr  = new AccountsaleController;
                            $crDrDep     = $actSaleCtr->createDepRfdRrans($request,$paramsVr);
                            $crDDRr = $crDrDep->id;     

                            $newVocherID = DB::table('cr_dr_settlement_log')->insertGetId(['v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $c_id ,'order_id' => $order_id , 'voucher_id' => $voucher_id, 'status' => 'PROCESS', 'applied_amount' => $applied_amount,'trans_src'=>'Redeem-CN' ,'trans_src_ref_id'=>$crDDRr]);
                            
                            if($newVocherID){

                                DB::table('dep_rfd_trans')->where('id', $crDrDep->id)->update(['status' => 'Success']);
                            }


                            $vocherData = DB::table('cr_dr_voucher')->where('id', $voucher_id)->first();
                            $voucherApplied = DB::table('cr_dr_settlement_log')->where('voucher_id', $voucher_id)->where('status', 'APPLIED')->where('v_id', $v_id)->sum('applied_amount');
                            // $vocherData->amount = $vocherData->amount - $voucherApplied;
                            //$vocherData->amount = $voucherApplied;
                            if(empty($vocherData->dep_ref_trans_ref) ||  $vocherData->dep_ref_trans_ref==''){
                                $vocherData->amount = $vocherData->amount - $voucherApplied;
                            } else{
                                $vocherData->amount = $voucherApplied;
                            }



                            $newVocherData = [ 'credit_note' => $vocherData->ref_id, 'issue_on' => date('d M Y', strtotime($vocherData->effective_at)), 'expire_at' => date('d M Y', strtotime($vocherData->expired_at)), 'amount' => $vocherData->amount, 'id' => $vocherData->id ];
                            
                            $voucher_applied_flag = true;
                        }

                        
                    }else{
                        $response = ['status' => 'fail' , 'message' => 'Unable to  Applied voucher'];
                    }
                }else{

                    $response = ['status' => 'fail' , 'message' => 'Unable to  Find voucher'];

                }

            }

            if($voucher_applied_flag){
              
                $orders = '';
              if($request->has('order_id') && $request->order_id != ''){
                $orders = Order::where('order_id', $request->order_id)->first();
                }
                $orderC = new OrderController;
                $order_arr = $orderC->getOrderResponse(['order' => $orders ,'v_id' => $v_id , 'trans_from' => $trans_from ]) ;

                return response()->json(['status' => 'success' ,'voucher_total'=> $vocherData->amount, 'message' => 'Voucher Applied succcessfully' , 'data' => $newVocherData,
                     'order_summary' => $order_arr
                 ]);
            }else{
                return response()->json($response);
            }
            

        }

    }

    public function remove_voucher(Request $request){
        
        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $c_id = $request->c_id;
        $trans_from = $request->trans_from;

        if($request->has('mobile')){
            $user = User::select('c_id')->where('mobile', $request->mobile)->where('v_id', $v_id)->first();
            if($user){
                $c_id = $user->c_id;
            }else{
                $response = ['status' => 'fail' , 'message' => 'Customer Not found'];
                return $response;
            }
        }

        $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;
        if($request->has('all') && $request->all == 1){
            if($request->has('order_id') && $request->order_id != ''){
                $vApplied = DB::table('cr_dr_settlement_log')->where('order_id', $order_id)->where('user_id', $c_id)->get();
                foreach($vApplied as $vou){
                        $voucher_id = $vou->voucher_id;
                        $pay_id = 'user_order_id_'.$voucher_id;
                        DB::table('payments')->where('order_id', $request->order_id)->where('pay_id' ,'like', $pay_id.'%')->delete();
                        DB::table('cr_dr_voucher')->where('id',$voucher_id)->update(['status' => 'unused']);
                }
            }
            DB::table('cr_dr_settlement_log')->where('order_id', $order_id)->where('user_id', $c_id)->delete();
            DB::table('dep_rfd_trans')->where('trans_src_ref', $request->order_id)->where('user_id', $c_id)->delete();
        }else{

            $voucher_ids = json_decode(urldecode($request->voucher_id), true);
            if(is_array($voucher_ids) ){
               $voucher_ids;
            }else{
                $voucher_ids = [];
                $voucher_ids[] = [ "voucher_id" => $request->voucher_id ];
            }

            foreach ($voucher_ids as $key => $vou) {
                $voucher_id = $vou['voucher_id'];
                DB::table('cr_dr_settlement_log')->where('voucher_id',$voucher_id)->where('order_id', $order_id)->where('user_id', $c_id)->delete();
                
                DB::table('dep_rfd_trans')->where('trans_src_ref', $request->order_id)->where('user_id', $c_id)->delete();


                if($request->has('order_id') && $request->order_id != ''){
                    $pay_id = 'user_order_id_'.$voucher_id;
                    DB::table('payments')->where('order_id', $request->order_id)->where('pay_id' ,'like', $pay_id.'%')->delete();
                    DB::table('cr_dr_voucher')->where('id',$voucher_id)->update(['status' => 'unused']);
                }
                
            }
        }

        $order_arr = [];
        if($request->has('order_id') && $request->order_id != ''){
            DB::table('payments')->where('order_id', $request->order_id)->where('payment_gateway_type' , 'VOUCHER')->delete();
            $orderC = new OrderController;
            $orders = Order::where('order_id', $request->order_id)->first();
            $getOrderResponse = ['order' => $orders , 'v_id' => $v_id , 'trans_from' => $trans_from ];
            if($request->has('transaction_sub_type')){
                $getOrderResponse['transaction_sub_type'] = $request->transaction_sub_type;
            }
            $order_arr = $orderC->getOrderResponse($getOrderResponse) ;
        }

        $request->request->add(['response_format' => 'ARRAY']);
        $offers = $this->get_offers($request);
        return response()->json(['status' => 'success', 'message' => 'Removed Successfully' , 'data' => isset($offers['data'])?$offers['data']:[]  , 'order_summary' => $order_arr ]);

    }

    public function manualDiscount(Request $request)
    {
        // dd($request->all());
        $v_id      = $request->v_id;
        $store_id  = $request->store_id;
        $user_id   = $request->c_id;
        $vu_id     = $request->vu_id;
        $trans_from= $request->trans_from;
        $type      = $request->type;
        $applicable_level = $request->applicable_level;
        $is_applicable = 0;
        $md_id = null;
        $name = null;

        JobdynamicConnection($v_id);
       
        if($request->has('is_applicable') && $request->is_applicable==1) {
         $is_applicable = $request->is_applicable;
        }
        if($applicable_level == 'ITEM_LEVEL' && $is_applicable!=1) {
          return $this->itemLevelManualDiscount($request);
        }
        if($applicable_level == 'BILL_LEVEL'){
            $groupIdList = CustomerGroupMapping::select('group_id')->where('c_id',$user_id)->get();
            $groupIds = collect($groupIdList)->pluck('group_id');
            $allow_manual_discount_bill = CustomerGroup::where('allow_manual_discount_bill_level','1')->whereIn('id',$groupIds)->exists();
            // dd(CustomerGroup::whereIn('id',$groupIds)->get());
            $allow_manual_discount_bill_level=empty($allow_manual_discount_bill)?false:true;
            if($allow_manual_discount_bill_level == false){
                return response()->json(['status' => 'fail' , 'message' => 'Bill Level Manual discount setting has not enabled ', 'other_info' => 'Bill level manual discount status is disabled from Customer Group' ],200);
            } 
        }
        if($type=='predefined'){
            $md_id         = $request->md_id;   
            $manual_discount=ManualDiscount::find($md_id);
            $name = $manual_discount->name;
            $factor   =  $manual_discount->discount_factor;
            $basis    =  $manual_discount->discount_type;
        }else{
            $factor    = $request->manual_discount_factor;  // Value of discount
            $basis     = $request->manual_discount_basis;    // Discount type (0=> Percentage, 1 => Amount)
        }

        $remove_discount = $request->remove_discount;   
        $total     = 0;
        $discount  = 0;
        $where     = array('v_id'=>$v_id,'store_id'=>$store_id,'user_id'=>$user_id,'vu_id'=>$vu_id);
        $data = Carry::select('barcode')->where(['store_id' => $store_id, 'v_id' => $v_id, 'status' => '1'])->get();
        $cart = Cart::where($where)->whereNotIn('barcode', $data)->where('qty','>',0)->get();
        $cart = $cart->filter(function($item) {
            $tdata = json_decode($item->tdata);
            // print_r($tdata);
            if($tdata->tax_type == 'EXC') {
                $item->total = $item->total - $item->tax;
            }
            return $item;
        });

        if($cart){
            $vendor = Organisation::find($v_id);
            if($vendor->db_structure == 2){
                $cartConfig  = new CloudPos\CartController;
            }else{
                $cartConfig  = new CartController;
            }
            if($remove_discount == 1){
                CartDiscount::where($where)->delete();
            }else{
                $total   = 0;  //$cart->sum('total');
                $cart_ids= $cart->pluck('cart_id');
                foreach ($cart as $item) {
                    $tdata = json_decode($item->tdata);
                    //$total += $tdata->netamt;
                    // Get Item details
                    // $item_master = VendorSku::select('item_id','name','hsn_code','has_batch','barcode','variant_combi','tax_type')->where([ 'barcode' => $item->barcode, 'v_id' => $v_id, 'deleted_at' => null ])->first();

                    // if(!$item_master){
                    //     $item_master = VendorSku::select('item_id','name','hsn_code','has_batch','barcode','variant_combi','tax_type')->where([ 'sku' => $item->barcode, 'v_id' => $v_id, 'deleted_at' => null ])->first();
                    // }
                    // if($item_master->tax_type == 'EXC') {
                    //     $netTotal = $item->total - $item->tax;
                    // } else {
                    //     $netTotal = $item->total;
                    // }
                    $total += $item->total;
                }
       
                if($basis =='A' &&  (int)$factor>$total){

                    return response()->json(['status' => 'fail' , 'message' => 'Total amount is lesser than discount amount' ],200);
                }

                // $setting = VendorSetting::where(['v_id'=>$v_id,'name'=>'offer','store_id'=>$store_id])
                //                 ->orderBy('id','desc')
                //                  ->first();
                // if(!$setting) {
                // $setting = VendorSetting::where(['v_id'=>$v_id,'name'=>'offer'])
                //                 ->orderBy('id','desc')
                //                  ->first();
                // }


                $vSetting = new VendorSettingController;
                $role_id  = VendorRoleUserMapping::select('role_id')->where('user_id',$vu_id)->first()->role_id;

                $settingsArray = ['v_id'=> $v_id,'store_id'=>$store_id,'user_id'=>$vu_id,'role_id'=>$role_id,'name'=>'offer' , 'trans_from' => $trans_from];
                $offer_setting = json_decode($vSetting->getSetting($settingsArray)->pluck('settings')->toArray()[0],true);

                // dd($offer_setting);
                //$setting = VendorSetting::where(['v_id'=>$v_id,'name'=>'offer'])->first();

                if($offer_setting){

                    // $offer_setting = json_decode($setting->settings,true);
                    //print_r($offer_setting);
                    if(isset( $offer_setting['manual_discount'][$trans_from]) ){

                    }else{
                        $trans_from = 'DEFAULT';
                    }


                    //print_r($offer_setting['manual_discount']['DEFAULT']['options'][0]['bill_level_percentage_amount']);die;

                    $PolicyManualDis=isset($offer_setting['manual_discount']['DEFAULT']['options'][0]['bill_level_percentage_amount'])?$offer_setting['manual_discount']['DEFAULT']['options'][0]['bill_level_percentage_amount']:'';

                    if(!empty($PolicyManualDis) && $PolicyManualDis['status']  == true){
                        $max_percentage = $PolicyManualDis['internal_options']['max_item_level_percentage']['value'];
                        $max_amount = $PolicyManualDis['internal_options']['max_item_level_amount']['value'];
                    }else{

                        $max_percentage =  $offer_setting['manual_discount'][$trans_from]['max_percentage']? $offer_setting['manual_discount'][$trans_from]['max_percentage']:$offer_setting['manual_discount']['DEFAULT']['max_percentage'];
                        //dd($max_percentage);
                        $max_amount     =  $offer_setting['manual_discount'][$trans_from]['max_amount']?$offer_setting['manual_discount'][$trans_from]['max_amount']:$offer_setting['manual_discount']['DEFAULT']['max_amount'];

                    }
                }
                // dd($max_percentage);
                if( $basis == '0' || $basis == 'P'){

                    if($factor>$max_percentage && $type !='predefined'){
                        return response()->json(['status' => 'fail' , 'message' => 'Applied manual discount more than limit' ],200);
                    }


                    // if(!isset($max_percentage)){
                    //     return response()->json(['status' => 'fail' , 'message' => 'Max percentage not define' ],200);
                    // }
                    $val_basis = 'P';
                    $discount  = getDiscountOfPercentage($total,$factor);


                }else if( $basis == '1' || $basis == 'A'){


                    if($factor>$max_amount && $type !='predefined'){
                        return response()->json(['status' => 'fail' , 'message' => 'Applied manual discount more than limit' ],200);
                    }
                    // if(!isset($max_amount)){
                    //     return response()->json(['status' => 'fail' , 'message' => 'Max amount not define' ],200);
                    // }
                    $val_basis = 'A';
                    $discount  = $factor;
                }else{
                    return response()->json(['status' => 'fail' , 'message' => 'No Discount Found' ],200);
                }

                if($discount > $total){
                    return response()->json(['status' => 'fail' , 'message' => 'Bad Discount Not Applied' ],200);
                }

                // echo $total;
                // echo '<br>';
                // echo $factor;
                // die;

                //echo 'helo'.$discount;die;

                $custom = (object)[ 'item_list' => $cart->pluck('cart_id'), 'amount' => $discount ];




                $discountApportionOnItems = discountApportionOnItems($cart, $custom, 'manual_discount');

                //dd($discountApportionOnItems);
                //  foreach ($discountApportionOnItems as $key => $value) {
                //   echo $value->total;
                //  }

                // die;

                $cart_data = array();
              
                foreach ($discountApportionOnItems as $key => $value) {
                    $tdata   = json_decode($value['tdata'],true);
                    //$value['qty']
                    $tax_total = $value['total'];
                    // if($tdata['tax_type'] == 'EXC'){
                    //     $tax_total = $value['total']-$value['tax'];
                    // }

                    // if($tax_total == 0){
                    //     $tax_total = $value['netamt'];
                    // }

                    $params  = array('barcode'=>$value['item_id'],'qty'=>$value['qty'],'s_price'=>$tax_total,'hsn_code'=>$tdata['hsn'],'store_id'=>$store_id,'v_id'=>$v_id);

                    $tax_details = $cartConfig->taxCal($params);
                    if($tdata['tax_type'] == 'EXC'){
                        $taxAmt = $tax_details['total'];
                    }else{
                        $taxAmt = $value['total'];
                    }

                    // if($taxAmt == 0){
                    //     $taxAmt = $tax_details['netamt'];
                    //     $tax_details['total'] = $tax_details['netamt'];
                    // }

                    $cart_data[] = array('item_id'=>$value['item_id'],'batch_id'=>$value['batch_id'],'serial_id'=>$value['serial_id'],'unit_mrp'=>$value['unit_mrp'],'discount'=>$value['manual_discount'],'qty'=>$value['qty'],'total'=>$taxAmt,'tdata'=> $tax_details);
                      
                }

                //print_r($cart_data);
                // dd($md_id);
                //dd($discountApportionOnItems);

                /*Add Discount Data in cart_discount*/
                CartDiscount::where($where)->delete();
                $amount   = $total-$discount;
                $disData  = array('md_id' => $md_id,'type' => $type,'name' => $name,'total_cart_amt'=>$total,'discount_amt'=>$discount,'amount'=>$amount,'cart_data'=>$cart_data);
                $cartDis  = array('v_id'=>$v_id,'store_id'=>$store_id,'user_id'=>$user_id,'vu_id'=>$vu_id,
                                  'type'=>'manual_discount','basis'=>$val_basis,'factor'=>$factor,
                                  'total'=>$total,'discount'=>$discount,'dis_data'=>json_encode($disData));
                CartDiscount::create($cartDis);
                /*End cart_discount*/
            }   

            if($request->has('return')){

            }else{
                return $cartConfig->cart_details($request);
            }

        }else{
            return response()->json(['status' => 'fail' , 'message' => 'No Cart Found' ],200);
        }

    }//End of manualDiscount


    public function manualDiscountList(Request $request){
// dd($request->all());

           $v_id              = $request->v_id;
           $applicable_level  = $request->applicable_level;
           $store_id          = $request->store_id;
           $barcode           = $request->barcode;
           $current_date      =  date('Y-m-d');
           $manual_discountList = [];
           if($request->has('c_id') && $request->c_id!=null){
             $c_id = $request->c_id;
             $cg   = CustomerGroupMapping::select('group_id')->where('c_id',$c_id)->get();


            }

           if($applicable_level==='BILL_LEVEL'){
           //DB::enableQueryLog();
            $manual_discountList=ManualDiscount::join('md_allocations','md_allocations.md_id','manual_discounts.id')
                                                  ->select('manual_discounts.id','manual_discounts.name','manual_discounts.description');

                            $manual_discountList->where(function ($query) use($store_id,$v_id,$current_date) {
                                $query->where('store_id',$store_id)
                                      ->where('manual_discounts.v_id',$v_id)
                                     ->where('manual_discounts.applicable_level','BILL_LEVEL')
                                     ->where('manual_discounts.status','1')
                                     ->whereDate('effective_date', '<=' ,$current_date)
                                     ->whereDate('valid_upto', '>=' ,$current_date) 
                                     ->whereNull('manual_discounts.deleted_at');
                         });
                        if($cg->isNotEmpty()){    
                            $manual_discountList->orWhere(function($query) use($cg,$v_id,$current_date) {
                                $query->whereIn('cg_id',$cg)
                                      ->where('manual_discounts.v_id',$v_id)
                                     ->where('manual_discounts.applicable_level','BILL_LEVEL')
                                     ->where('manual_discounts.status','1')
                                     ->whereDate('effective_date', '<=' ,$current_date)
                                     ->whereDate('valid_upto', '>=' ,$current_date);
                                    
                            });
                        }
            $manual_discountList=$manual_discountList->groupBy('manual_discounts.id')
                                 ->get();
                  
            }elseif($applicable_level=="ITEM_LEVEL"){


            $manualDiscountList=ManualDiscount::join('md_allocations','md_allocations.md_id','manual_discounts.id')
                                               ->select('manual_discounts.id','manual_discounts.name','manual_discounts.description','manual_discounts.assortment_id');

                                $manualDiscountList->where(function ($query) use($store_id,$v_id,$current_date) {
                                    $query->where('store_id',$store_id)
                                          ->where('manual_discounts.v_id',$v_id)
                                         ->where('manual_discounts.applicable_level','ITEM_LEVEL')
                                         ->where('manual_discounts.status','1')
                                         ->whereDate('effective_date', '<=' ,$current_date)
                                         ->whereDate('valid_upto', '>=' ,$current_date);
                                    });
                                if($cg->isNotEmpty()){    
                                     $manualDiscountList->orWhere(function($query) use($cg,$v_id,$current_date) {
                                        $query->whereIn('cg_id',$cg)
                                              ->where('manual_discounts.v_id',$v_id)
                                             ->where('manual_discounts.applicable_level','ITEM_LEVEL')
                                             ->where('manual_discounts.status','1')
                                             ->whereDate('effective_date', '<=' ,$current_date)
                                             ->whereDate('valid_upto', '>=' ,$current_date);
                                            
                                    });
                                }
           $manualDiscountList=$manualDiscountList->groupBy('manual_discounts.id')
                                 ->get();
            $manual_discountList=[];

            if($manualDiscountList->isNotEmpty()){
               
               foreach ($manualDiscountList as $key => $value) {

                $assortment = VendorSkuAssortmentMapping::where('v_id',$v_id)
                                              ->where('assortment_code',$value->assortment_id)
                                              ->where('barcode',$barcode)
                                              ->first();
                if($assortment){

                $manual_discountList [] = ['id'=>$value->id,
                                            'name'=>$value->name,
                                            'description'=>$value->description
                                            ];
                }                             

               }

            }
                                 

            }  

            return response()->json(['status' => 'sucesss' , 'manual_discount_list' =>$manual_discountList],200);                                   

     }


    public function itemLevelManualDiscount(Request $request)
    {    
        // dd($request->all()); 
        $v_id      = $request->v_id;
        $store_id  = $request->store_id;
        $user_id   = $request->c_id;
        $vu_id     = $request->vu_id;
        $trans_from= $request->trans_from;
        $type      = $request->type;
        $item_id   = $request->item_id;
        $barcode   = $request->barcode;
        $unit_mrp  = $request->unit_mrp;
        $unit_csp  = $request->unit_csp;
        $remove_discount    = $request->remove_discount;
        $isCustomer    = 0;
        $max_percentage=0;
        $max_amount    =0; 

                
        // $setting = VendorSetting::where(['v_id'=>$v_id,'name'=>'offer','store_id'=>$store_id])
        //                         ->orderBy('id','desc')
        //                          ->first();
        // if(!$setting) {
        //     $setting = VendorSetting::where(['v_id'=>$v_id,'name'=>'offer'])
        //                         ->orderBy('id','desc')
        //                          ->first();
        // }


        $vSetting = new VendorSettingController;
        $role_id = VendorRoleUserMapping::select('role_id')->where('user_id',$vu_id)->first()->role_id;

        $settingsArray = ['v_id'=> $v_id,'store_id'=>$store_id,'user_id'=>$vu_id,'role_id'=>$role_id,'name'=>'offer' , 'trans_from' => $trans_from];
        $offer_setting = json_decode($vSetting->getSetting($settingsArray)->pluck('settings')->toArray()[0]);


        $groupIdList = CustomerGroupMapping::select('group_id')->where('c_id',$user_id)->get();
            $groupIds = collect($groupIdList)->pluck('group_id');
        $allow_manual_discount = CustomerGroup::where('allow_manual_discount','1')->whereIn('id',$groupIds)->exists();
        $allow_manual_discount_item_level=empty($allow_manual_discount)?false:true;


        $cart = null;
        if($remove_discount == 1) {

            if($request->has('cart_id') && $cart_id=!null){
                $cart = Cart::where('cart_id',$request->cart_id)->first();
            }else{
                $where= array('v_id'=>$v_id,'store_id'=>$store_id,'user_id'=>$user_id,'vu_id'=>$vu_id,'item_id'=>$item_id,'barcode'=>$barcode,'unit_mrp'=>$unit_mrp,'unit_csp'=>$unit_csp);
                $cart  = Cart::where($where)->where('status','process')->where('qty','>',0);
                if($request->has('batch_id') && $request->batch_id!=0 ){
                    $cart    =$cart->where('batch_id',$request->batch_id);
                }
                if($request->has('serial_id') && $request->batch_id!=0 ){
                    $cart  =$cart->where('serial_id',$request->serial_id);
                }
                $cart =$cart->first();  

            }

        }else{

            if($request->has('cart_id') && $cart_id=!null){
                $cart = Cart::where('cart_id',$request->cart_id)->first();
            }else{
                $where= array('v_id'=>$v_id,'store_id'=>$store_id,'user_id'=>$user_id,'vu_id'=>$vu_id,'item_id'=>$item_id,'barcode'=>$barcode,'unit_mrp'=>$unit_mrp,'unit_csp'=>$unit_csp);
                $cart  = Cart::where($where)->where('status','process');
                if($request->has('batch_id') && $request->batch_id!=0 ){
                  $cart    =$cart->where('batch_id',$request->batch_id);
                }
                if($request->has('serial_id') && $request->batch_id!=0 ){
                   $cart  =$cart->where('serial_id',$request->serial_id);
                }
                $cart =$cart->first();

            }

        }


        $bar = VendorSkuDetailBarcode::select('item_id','vendor_sku_detail_id','barcode')->where('is_active','1')->where('v_id', $v_id)->where('barcode', $cart->barcode)->first();

        $item_master = null;
        if($bar){
            $item_master = VendorSku::select('item_id','name','hsn_code','has_batch','barcode','variant_combi','tax_type')->where([ 'vendor_sku_detail_id' => $bar->vendor_sku_detail_id, 'v_id' => $v_id, 'deleted_at' => null ])->first();
    
        }
    
        if(!$item_master){
            $item_master = VendorSku::select('item_id','name','hsn_code','has_batch','barcode','variant_combi','tax_type')->where([ 'sku' => $cart->barcode, 'v_id' => $v_id, 'deleted_at' => null ])->first();
        }

        $item  =  VendorItem::select('vendor_items.allow_manual_discount','vendor_items.manual_discount_percent','vendor_items.manual_discount_override_by_store_policy')
            ->where(['vendor_items.v_id' => $v_id , 'vendor_items.item_id' => $bar->item_id])
            ->first()
            ;
        // dd($item->toSql(), $item->getBindings());
        // dd($item);

        if($allow_manual_discount_item_level != false){
            if($offer_setting) {

                // $offer_setting = json_decode($setting->settings);

                // dd($offer_setting);

                if($offer_setting->item_level_manual_discount->DEFAULT->status==1){


                    if($offer_setting->item_level_manual_discount->DEFAULT->max_percentage!='' && $offer_setting->item_level_manual_discount->DEFAULT->max_percentage!=null){     
                            $max_percentage = $offer_setting->item_level_manual_discount->DEFAULT->max_percentage;

                    }

                    if($offer_setting->item_level_manual_discount->DEFAULT->max_amount!='' && $offer_setting->item_level_manual_discount->DEFAULT->max_amount!=null){     
                        $max_amount = $offer_setting->item_level_manual_discount->DEFAULT->max_amount;
                    }
                    


                    $cashierwiese=$offer_setting->item_level_manual_discount->DEFAULT->options[0]->item_level_percentage_amount;
                    if($cashierwiese->status == true){
                        $percentageValue=$cashierwiese->internal_options->max_item_level_percentage->value;
                        $amountValue=$cashierwiese->internal_options->max_item_level_amount->value;
                        if($percentageValue!=null && $percentageValue!=''){
                            $max_percentage   = (float)$percentageValue;
                        }
                        if($amountValue!=null && $amountValue!=''){
                            $max_amount  =  (float)$amountValue;
                        }
                    } 


                    //Override manual Discount
                    if($item->manual_discount_override_by_store_policy == '1' ){

                        if($item->allow_manual_discount == '1'){
                            $max_percentage = $item->manual_discount_percent;
                        }else{
                            return response()->json(['status' => 'fail' , 'message' => 'Item Level Manual discount setting has not enabled (By Product Policy) ' ],200);
                        }

                    }         

                 } else {
                    return response()->json(['status' => 'fail' , 'message' => 'Item Level Manual discount setting has not enabled ' , 'other_info' => 'Item level manual discount status is disabled'],200);
                }

            } else {
                return response()->json(['status' => 'fail' , 'message' => 'Item Level Manual discount setting has not enabled ' , 'other_info' => 'Unable to find Item level manual discount Settings'],200);
            } 
        }else {
            return response()->json(['status' => 'fail' , 'message' => 'Item Level Manual discount setting has not enabled ' , 'other_info' => 'Allow manual discount is not enable in Customer Group'],200);
        }        

        // dd($max_percentage);    

        if($request->has('c_id') && $request->c_id != null){
             $c_id = $request->c_id;
             $cg   = CustomerGroupMapping::select('group_id')
                                           ->where('c_id',$c_id)
                                           ->get();
            //dd($cg);                               
            // if($cg->isNotEmpty()){
            //  //DB::enableQueryLog(); 
            //  $isCustomer=1;  
            //  $customerMaxDiscountLimit = CustomerGroup::where('allow_manual_discount','1')->whereIn('id',$cg)->max('maximum_discount_perbill');
            //  //dd(DB::getQueryLog());
            //  //dd($customerMaxDiscountLimit);
            // }else{
              
            //   return response()->json(['status' => 'fail' , 'message' => 'Customer is not tag any groups' ],200);

            // }                               
        }

        //dd($cg);
        
        if($remove_discount == 1) {

            
            if($cart){

                //print_r($item_master);

                if($cart->item_level_manual_discount!=null){
              
                    $ilmDiscount                =  json_decode($cart->item_level_manual_discount);
                    // $total                      =  round($cart->total+$ilmDiscount->discount,2);
                    // $total                      =  $cart->subtotal;
                    if($item_master->tax_type == 'EXC') {
                        $total = $cart->total - $cart->tax + $ilmDiscount->discount;
                    } else {
                        $total = $cart->total + $ilmDiscount->discount;
                    }
                    $item_level_manual_discount = null;


                }else{
               
                    return response()->json(['status' => 'success' , 'message' => 'Discount Not Applied in this item' ],200);
                
                }

           }else{
             return response()->json(['status' => 'fail' , 'message' => ' Discount Not Applied in this item' ],200);

           }


        }else{

            if($type=='predefined'){
                $md_id         = $request->md_id;   
                $manual_discount=ManualDiscount::find($md_id);
                $factor   =  $manual_discount->discount_factor;
                $basis    =  $manual_discount->discount_type;
            }else{
                $md_id    = null;    
                $factor    = $request->manual_discount_factor;  // Value of discount
                $basis     = $request->manual_discount_basis;    // Discount type (0=> Percentage, 1 => Amount)
            }
            // $where     = array('v_id'=>$v_id,'store_id'=>$store_id,'user_id'=>$user_id,'vu_id'=>$vu_id,'cart_id'=>$cart_id);
          
              
            $total = 0;
            
               // dd($basis);
            if($cart) {
                

                if($item_master->tax_type == 'EXC') {
                    $total = $cart->total - $cart->tax;
                } else {
                    $total = $cart->total;
                }

                if($cart->discount){
                    $total += $cart->discount;
                }

                if($cart->bill_buster_discount){
                    $total += $cart->bill_buster_discount;
                }
            }
               // dd($total);

            if( $basis == '0' || $basis == 'P'){
                //dd($max_percentage);
                if($factor>$max_percentage && $type !='predefined'){
                    return response()->json(['status' => 'fail' , 'message' => 'Applied manual discount more than limit' ],200);
                }
                $val_basis = 'P';
                $discount  = getDiscountOfPercentage($total,$factor);

                //Considering Max Amount as capping
                if($discount > $max_amount){
                    return response()->json(['status' => 'fail' , 'message' => 'Applied manual discount more than limit (Capping : '.$max_amount.')' ],200);
                }
            }else if( $basis == '1' || $basis == 'A'){
                if($factor>$max_amount  && $type !='predefined'){
                    return response()->json(['status' => 'fail' , 'message' => 'Applied manual discount more than limit' ],200);
                }
                $val_basis = 'A';
                $discount  = $factor;

                //Considering Max Amount as capping
                $per = ( $discount / $total ) * 100 ; 
                if($per > $max_percentage){
                    return response()->json(['status' => 'fail' , 'message' => 'Applied manual discount is greater the given percentage ('.$max_percentage.')' ],200);
                }
                
            }else{
                return response()->json(['status' => 'fail' , 'message' => 'No Discount Found' ],200);
            }
              // if($isCustomer==1 && $customerMaxDiscountLimit==null || $isCustomer==1 && $customerMaxDiscountLimit==0){
              //   return response()->json(['status' => 'fail' , 'message' => 'Customer groups have  not enable manual  discount settings  or manual discount zero' ],200);

              // }elseif($isCustomer==1 && $customerMaxDiscountLimit>0){
                
              //   $allowmaxdiscount =getDiscountOfPercentage($total,$customerMaxDiscountLimit);

              //   if($discount>$allowmaxdiscount){
                 
              //    return response()->json(['status' => 'fail' , 'message' => 'Applied manual discount more than customer groups limts' ],200);

              //   }

              // } 
            if($discount > $total){
                return response()->json(['status' => 'fail' , 'message' => 'Item amount is lesser than discount amount' ],200);
            }
            $disData  = [ 'discount' => format_number($discount), 'factor' => $factor, 'basis' => $basis, 'md_id' => $md_id, 'type' => $type];
            $total = round($total- format_number($discount),2);
            $item_level_manual_discount = json_encode($disData);

        }

        $vendor = Organisation::find($v_id);
        if($vendor->db_structure == 2){
            $cartConfig  = new CloudPos\CartController;
        }else{
            $cartConfig  = new CartController;
        }


        // Tax Re-Calculate
        $bar = VendorSkuDetailBarcode::select('item_id','vendor_sku_detail_id','barcode')->where('is_active','1')->where('v_id', $v_id)->where('barcode', $cart->barcode)->first();

        $item_master = null;
        if($bar){
            $item_master = VendorSku::select('item_id','name','hsn_code','has_batch','barcode','variant_combi','tax_type')->where([ 'vendor_sku_detail_id' => $bar->vendor_sku_detail_id, 'v_id' => $v_id, 'deleted_at' => null ])->first();
    
        }
        
        if(!$item_master){
            $item_master = VendorSku::select('item_id','name','hsn_code','has_batch','barcode','variant_combi','tax_type')->where([ 'sku' => $cart->barcode, 'v_id' => $v_id, 'deleted_at' => null ])->first();
        }

        $from_gstin = Store::select('gst')->where('store_id', $store_id)->first()->gst;
        $to_gstin = null;
        $invoice_type = 'B2C';
        if($request->has('cust_gstin') && $request->cust_gstin != ''){
            $invoice_type= 'B2B';
            $to_gstin = $request->cust_gstin;
        }
        $taxParams = ['barcode' => $cart->barcode, 'qty' => $cart->qty, 's_price' => $total, 'hsn_code' => $item_master->hsn_code, 'store_id' => $store_id, 'v_id' => $v_id, 'from_gstin' => $from_gstin, 'to_gstin' => $to_gstin, 'invoice_type' => $invoice_type];
        $tax_details = $cartConfig->taxCal($taxParams);
        
        if($item_master->tax_type == 'EXC') {
            $total = $total + format_number($tax_details['tax']);
        }
        // dd($total);

           $cart     = Cart::where('cart_id',$cart->cart_id)
                            ->update([ 'total' => format_number($total), 'item_level_manual_discount' => $item_level_manual_discount, 'tax' => format_number($tax_details['tax'], 2), 'tdata' => json_encode($tax_details) ]);
        return $cartConfig->cart_details($request);

     }
   
}
