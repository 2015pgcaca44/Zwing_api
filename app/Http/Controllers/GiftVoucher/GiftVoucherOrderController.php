<?php

namespace App\Http\Controllers\GiftVoucher;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use Auth;
use Event;
use App\Model\GiftVoucher\GiftVoucher;
use App\Model\GiftVoucher\GiftVoucherCategory;
use App\Model\GiftVoucher\GiftVoucherGroup;
use App\Model\GiftVoucher\GiftVoucherPacks;
use App\Model\GiftVoucher\GiftVoucherConfiguration;
use App\Model\GiftVoucher\GiftVoucherConfigPresetMapping;
use App\Model\GiftVoucher\GiftVoucherConfigPreset;
use App\Model\GiftVoucher\GiftVoucherAllocation;
use App\Model\GiftVoucher\GiftVoucherCartDetails;
use App\Model\GiftVoucher\GiftVoucherOrder;
use App\Model\GiftVoucher\GiftVoucherOrderDetails;
use App\Http\Controllers\CloudPos\AccountsaleController;
use App\Model\GiftVoucher\GiftVoucherPayments;
use App\Store;
use Carbon\Carbon;



class GiftVoucherOrderController extends Controller
{
    public function __construct()
    {
       // $this->middleware('auth',['except' => ['printGrn']]);
    }
    
    //all cart action per
    public function processToCheckout(Request $request)
    {
        $this->validate($request, [
            'v_id'               => 'required',
            'store_id'           => 'required',
            'vu_id'              => 'required',
            'c_id'               => 'required',
            'trans_from'         => 'required',
            'subtotal'           => 'required',
            //'total_tax'          => 'required',
            'total_amount'       => 'required',
            'total_item_in_cart' => 'required',
        ]);
        $v_id         = $request->v_id;
        $store_id     = $request->store_id;
        $c_id         = $request->c_id;
        $trans_from   = $request->trans_from;
        $vu_id        = $request->vu_id;
        $subtotal     = $request->subtotal;
        $total_tax    = $request->total_tax;
        $total_amount = (float)$request->total_amount;
        $voucher_qty  = $request->total_item_in_cart;

        if($request->has('cust_gstin') && $request->cust_gstin != ''){

            $cust_gstin    = DB::table('customer_gstin')->select('state_id')->where('v_id',$v_id)->where('c_id', $c_id)->where('gstin', $request->cust_gstin)->first();
            if(!$cust_gstin){
                return response()->json(['status' => 'fail' , 'message' => 'Unable to find Customer Gstin'], 200);
            }
            $comm_trans = 'B2B';
            $customer_gstin        = $request->cust_gstin;
            $customer_gst_state_id = $cust_gstin->state_id;
        }else{
            $comm_trans = 'B2C';
            $customer_gstin='';
            $customer_gst_state_id='';
        }

        $accountSale    = new AccountsaleController;
        $cParams        = ['user_id'=>$c_id,'v_id'=>$v_id,'store_id'=>$store_id];
        $customerInfo   = $accountSale->customerInfo($cParams);

        $store_details  = DB::table('stores')->select('state_id','gst')->where('v_id',$v_id)->where('store_id',$store_id)->first();
        $store_state_id = empty($store_details)?'':$store_details->state_id;
        $store_gstin    = empty($store_details)?'':$store_details->gst;
        $order_id       = gv_order_id_generate($store_id, $c_id, $trans_from);
        
        $order                         = new GiftVoucherOrder;
        $order->gv_order_doc_no        = $order_id;
        $order->transaction_type       = 'sales';
        $order->trans_from             = $trans_from;
        $order->voucher_qty            = $voucher_qty;
        $order->subtotal               = $subtotal;
        $order->total                  = trim($total_amount);
        $order->tax_amount             = $total_tax;
        $order->status                 = 'process';
        $order->v_id                   = $v_id;
        $order->vu_id                  = $vu_id;
        $order->customer_id            = $c_id;
        $order->store_id               = $store_id;
        $order->date                   = date('Y-m-d');
        $order->time                   = date('h:i:s');
        $order->month                  = date('m');
        $order->financial_year         = date('Y');
        $order->payment_type           = '';
        $order->payment_via            = '';
        $order->customer_gstin         = $customer_gstin;
        $order->customer_gst_state_id  = $customer_gst_state_id;
        $order->store_gstin            = $store_gstin;
        $order->store_state_id         = $store_state_id;
        $order->comm_trans             = $comm_trans;
        $order->save();
        $gv_order_id=$order->gv_order_id;

        $cart_data = GiftVoucherCartDetails::where('store_id', $store_id)->where('v_id', $v_id)->where('vu_id', $vu_id)->where('customer_id', $c_id)->get()->toArray();
        foreach ($cart_data as $key => $value) {

                $save_order_details = array_except($value, ['gv_cart_id']);
                //$save_order_details = array_add($value, 'status','process');
                $array_merge        = array('status' =>'process','transaction_type'=>'sales','gv_order_id'=>$gv_order_id,
                                            'subtotal'=>$value['subtotal'],'total'=>$value['total'],'tax_amount'=>$value['tax_amount']
                                            ,'date'=>date('Y-m-d'),'time'=>date('H:i:s'),'month'=>date('m'),'year'=>date('y'));
                $save_order_details = array_merge($value,$array_merge);
                $order_details = GiftVoucherOrderDetails::create($save_order_details);
        }
        
        
        $order_arr = $this->getGvOrderResponse(['order' => $order, 'v_id' => $v_id, 'trans_from' => $trans_from]);   
                             
        if($gv_order_id){

            $where_get = array('v_id'=>$v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'customer_id'=>$c_id,'gv_order_id'=>$gv_order_id);
            $order_data = GiftVoucherOrder::where($where_get)
                                          ->select('gv_order_id as order_id','gv_order_doc_no','transaction_type as trans_type','trans_from','subtotal','total','status','tax_amount','customer_gstin as cust_gstin','customer_gst_state_id as cust_gstin_state_id')
                                          ->first();

            return response()->json([ 'status' => 'proceed_to_payment', 'message' => 'Proceed to Payment','data' => $order_data,'customer_info'=>$customerInfo,'order_summary'=>$order_arr ], 200);  
        }else{                 
            return response()->json([ 'status' => 'fail', 'message' => 'Fail checkout process','data' => $responseData ], 200);
        }

    }

    public function getGvOrderResponse($params)
    {
        $v_id = $params['v_id'];
        $response = [];
        $summary = [];
        $order = null;
        $items_qty = 0;
        $amount_due = 0;
        $tax_total=0;

        if($params['trans_from'] == 'ANDROID_VENDOR' || $params['trans_from'] == 'CLOUD_TAB' || $params['trans_from'] == 'CLOUD_TAB_ANDROID' || $params['trans_from'] == 'CLOUD_TAB_WEB' || $params['trans_from'] == 'ANDROID_KIOSK' ) {


            if(isset($params['order'])){
                $order = $params['order'];
            }else if(isset($params['gv_order_id'])){
                $order = GiftVoucherOrder::where('v_id',$v_id)->where('gv_order_id' , $params['gv_order_id'])
                                         ->first();
            }else{
                return $response;
            }
            
            //Start order details
            $sub_total = 0;
            $where_get = array('v_id'=>$order->v_id,'vu_id'=>$order->vu_id,'store_id'=>$order->store_id,'customer_id'=>$order->customer_id,'gv_order_id'=>$order->gv_order_id);
            $order_summary = GiftVoucherOrderDetails::where($where_get)
                                                ->select('voucher_code','voucher_sequence','mobile','gift_value','sale_value','tdata'
                                                        ,'subtotal','total','tax_amount')
                                                ->get();    
            $sub_total  = $order_summary->sum('subtotal');
            $total_tax  = $order_summary->sum('tax_amount');  
            $total_od   = $order_summary->sum('total');  
            //$sub_total  = $total_od - $total_tax;
            $sb_total   = $sub_total;
            $total_payable = (float)$order->total;

            $summary[] = [ 'name' => 'sub_total' , 'display_text' => 'Sub Total' , 'display_name' => 'Sub Total' , 'value' => format_number($sb_total),'sign'=>'' ];
            $summary[] = [ 'name' => 'tax' , 'display_text' => 'Taxes' , 'display_name' => 'Taxes' , 'value' => format_number($order->tax_amount),'sign'=>'' ];
            $summary[] = [ 'name' => 'total' , 'display_text' => 'Total' ,'display_name' => 'Total' , 'value' => format_number($order->total),'sign'=>'' ];
            

            //End order details
            //start payments 

            $payments = GiftVoucherPayments::where('v_id', $order->v_id)->where('store_id', $order->store_id)->where('gv_order_id', $order->gv_order_id)->where('status','success')->get();

            

            foreach ($payments as $key => $payment) {
                if($payment->payment_gateway_type == 'CASH'){
                    $summary[] = [ 'name' => 'cash' , 'display_text' => 'Cash' ,'display_name' => 'Cash' , 'value' =>  format_number($payment->amount) , 'color_flag' => '1' , 'mop_flag' => '1'];

                }else if($payment->payment_gateway_type == 'VOUCHER' || $payment->payment_gateway_type == ''){
                    $summary[] = [ 'name' => 'voucher_credit' , 'display_text' => 'Voucher ' ,'display_name' => 'Voucher ' , 'value' =>  format_number($payment->amount) , 'color_flag' => '1' , 'mop_flag' => '1' ];

                }else if($payment->payment_gateway_type == 'RAZOR_PAY'){
                    
                    if($payment->method == 'wallet'){

                        $summary[] = [ 'name' => 'online' , 'display_text' => 'Wallet' ,'display_name' => 'Wallet' , 'value' =>  format_number($payment->amount) , 'color_flag' => '1' , 'mop_flag' => '1'];
                    }else if($payment->method == 'netbanking'){

                        $summary[] = [ 'name' => 'netbanking' , 'display_text' => 'Net Banking' ,'display_name' => 'Net Banking' , 'value' => format_number($payment->amount) , 'color_flag' => '1' , 'mop_flag' => '1'];

                    }else if($payment->method == 'card'){

                        $summary[] = [ 'name' => 'card' , 'display_text' => 'Card' , 'display_name' => 'Card' , 'value' =>  format_number($payment->amount) , 'color_flag' => '1' , 'mop_flag' => '1'];
                    }

                }else if($payment->payment_gateway_type == 'EZETAP' || $payment->payment_gateway_type == 'EZSWYPE'){

                    $summary[] = [ 'name' => 'card' , 'display_text' => 'Card' , 'display_name' => 'Card' , 'value' =>  format_number($payment->amount) , 'color_flag' => '1' , 'mop_flag' => '1' ];
                }else{

                    $paymentName  = str_replace('_', ' ', $payment->method);

                    $summary[] = [ 'name' => $payment->method , 'display_text' => ucwords($paymentName) ,'display_name' => ucwords($paymentName) , 'value' =>  format_number($payment->amount) , 'color_flag' => '1' , 'mop_flag' => '1' ];

                }
                
                
            }

            $amount_paid = (float)$payments->sum('amount');
            $total_payable -= $amount_paid;
            $amount_due = (float)$order->total - $amount_paid;
            
            if($order->transaction_type == 'return'){
                $summary[] = [ 'name' => 'total_refund ' , 'display_text' => 'Total Refund' ,'display_name' => 'Total Refund' , 'value' => format_number($amount_paid), 'color_flag' => '1' ];
            }else{
                $summary[] = [ 'name' => 'total_payable' , 'display_text' => 'Total Payable' ,'display_name' => 'Total Payable' , 'value' => format_number($total_payable), 'color_flag' => '1' ];
            }
            //end payments
            

            $response['items'] = $order_summary;
            $response['item_qty'] = (string)$order->voucher_qty;
            $response['summary'] = $summary;
            $response['total_payable'] = (float)format_number($total_payable);
            $response['amount_due'] = $amount_due;
            $response['amount_paid'] = $amount_paid;
            if($order->transaction_type == 'return'){
                $response['total_refund'] = (string)$order->total;
            }
            $response['order_total'] = (float)$order->total;
        }

        return $response;

    }
 

}
