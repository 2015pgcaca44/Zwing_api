<?php

namespace App\Http\Controllers\CloudPos;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CartController as OldCartController;
use Illuminate\Http\Request;
use App\Mail\OrderCreated;
use Illuminate\Support\Facades\Mail;
use PDF;
use App\Store;
use App\Order;
use App\Cart;
use App\User;
use App\Vendor;
use App\Payment;
use App\Invoice;
use App\InvoiceDetails;
use App\Reason;
use App\CashRegister;
use App\SettlementSession;
use DB;
use Auth;
use App\OrderDetails;
use App\Model\Items\VendorSkuDetails;
use App\Model\Items\VendorSku;
use App\Model\Items\VendorSkuDetailBarcode;
use App\Http\Controllers\CloudPos\CartconfigController;
use App\Vendor\VendorRoleUserMapping;
use App\Http\Controllers\VendorSettingController;
use App\Http\Controllers\CloudPos\AccountsaleController;
use App\Http\Controllers\SmsController;
use Event;
use App\Events\InvoiceCreated;
use App\Events\SaleItemReport;


class ReturnController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->cartconfig  = new CartconfigController;
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
        $where     = array('vendor_id'=>$v_id,'store_id'=>$store_id,'type'=>'manager');
        $vendor    = Vendor::where('vendor_user_random', $security_code)->where($where)->first();

        $vu_id = $request->vu_id;

        if($vendor){
            DB::table('operation_verification_log')->insert([ 'v_id' => $v_id, 'store_id' => $store_id, 
                //'c_id' =>$user->c_id,
                 'trans_from' => $trans_from, 'vu_id' => $vu_id ,'operation' => $operation ,
                 // 'order_id' => $order_id ,
                  'verify_by' =>  $vendor->vendor_id , 'created_at' => date('Y-m-d H:i:s') ]);

            return response()->json(['status' => 'success', 'message' => 'You are Authorized' ]);
        }else{

            return response()->json(['status' => 'fail', 'message' => 'You are not  Authorized User' ]);

        }

    }

    public function get_order(Request $request){
        $v_id = $request->v_id;
        //$c_id = $request->c_id;
        $store_id = $request->store_id;
        $cust_order = $request->cust_order;
        $trans_from = $request->trans_from;

        if($request->type == 'mobile_number'){
            
            $profileC = new ProfileController;
            $cust = User::where('mobile' ,$cust_order)->where('v_id',$v_id)->first();

            if($cust){

                // $myRequest = new \Illuminate\Http\Request();
                // $myRequest->setMethod('POST');
                // $myRequest->request->add([ 'c_id' => $cust->c_id , 'api_token' => $cust->api_token ]);

               // $Nrequest->request->add([ 'c_id' => $cust->c_id ]);


                //$request->request->remove('v_id');
                //$request->request->remove('store_id');
                //dd($request);
                //return $profileC->my_order($myRequest); 
                return response()->json(['status' => 'success' , 'go_to' => 'my_order' , 'data' => [ 'c_id' => $cust->c_id , 'api_token' => $cust->api_token ] ]);
                
            }else{
                return response()->json(['status' => 'fail', 'message' => 'Unable to find the customer' ]);
            }

        }else{

            $cartC = new CartController;

            //$order = Order::where('order_id', $cust_order)->where('status' , 'success')->first();
            $order = Invoice::where('invoice_id','like', '%'.$cust_order.'%')->where(['v_id'=>$v_id])->where('transaction_type','sales')->first();


            if($order){

                $request->request->add();
                //return $cartC->order_details($request);
                $cust = User::where('c_id' ,$order->user_id)->where('v_id', $v_id)->first();
                return response()->json(['status' => 'success' , 'go_to' => 'order_details' , 'data' => [ 'v_id' => $order->v_id , 'store_id' => $order->store_id , 'c_id' => $order->user_id, 'api_token' => $cust->api_token, 'order_id' => $order->invoice_id, 'trans_from' => $trans_from ]
                 ]);
                
            }else{

                return response()->json(['status' => 'fail', 'message' => 'Unable to find the Order' ]);
            }

        
        }

    }


    public function get_return_item(Request $request){
        
        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;
        $order_id = $request->order_id;

        $data = [] ;
        $order = Order::where('store_id', $store_id)->where('v_id' , $v_id)->where('user_id', $c_id)->where('order_id', $order_id)->first();
        $carts = DB::table('order_details')->where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order->o_id)->where('user_id', $c_id)->get();

        $param =[];
        $params= [];
        foreach($carts as $cart){

            $loopQty = $cart->qty;
            while($loopQty > 0){
               $param[] = $cart->total / $cart->qty; 
               $params[] = ['item_id' => $cart->item_id , 'price' => $cart->total / $cart->qty ];
               $loopQty--;
            }

            $data = ['p_name' => $cart->item_name , 'qty' => $cart->qty, 's_price' => $cart->total ];
        }

        return response()->json(['status' => 'cart_details', 'message' => 'Your Cart Details', 
            'data' => $data ]);

    }

    public function get_return_request(Request $request){

        $v_id = $request->v_id;
        $c_id = $request->customer_id;
        $store_id = $request->store_id;
        $order_id = $request->order_id;
        $confirm = $request->confirm;
        $return_item = json_decode($request->return_item);
        $returnItemCollect = collect($return_item);
        $invoice = Invoice::where('invoice_id', $order_id)->first();
        $order_id = $invoice->ref_order_id;

        //dd($return_item);
        //dd($request->return_item);
        $return_items = [];

        $nreturn_items = [];

        foreach($return_item as $key => $item){

            $r_item = [];// Extra

            $item_id = $item->item_id;
            $item_return = 0;
            if(isset($item->reason) && $item->reason!=''){
                $item_return = $item->reason;  
            }
            if(isset($item->reason_id)){
             $return_items[$key]['reason_id'] = $item->reason_id;
            }
          
            $return_items[$key]['request_qty'] = $item->qty;
            $return_items[$key]['unit_mrp'] = isset($item->unit_mrp)?$item->unit_mrp:'';
            $return_items[$key]['qty'] = 0;
            $return_items[$key]['discount'] = 0;
            $return_items[$key]['lpdiscount'] = 0;
            $return_items[$key]['manual_discount'] = 0;
            $return_items[$key]['coupon_discount'] = 0;
            $return_items[$key]['tax'] = 0;
            $return_items[$key]['bill_discount'] = 0;
            $return_items[$key]['s_price'] = 0;
            $return_items[$key]['r_price'] = 0;
            $return_items[$key]['p_name'] = 0;
            $return_items[$key]['batch_id'] = isset($item->batch_id)?$item->batch_id:'';
            $return_items[$key]['serial_id'] = isset($item->serial_id)?$item->serial_id:'';
            $return_items[$key]['item_id'] = $item->item_id;

            // $r_item[$item_id] = $return_items[$item_id];
          
            // $nreturn_items[] = $r_item;




        }

        $return_item_data = [];
        
        //Before return any Items check any previous order is pending or not
        $order = Order::select('order_id', 'v_id' , 'store_id','date','time','total','verify_status','verify_status_guard','bill_buster_discount')->where('user_id',$c_id)->where('v_id' , $v_id)->where('status','success')->where('transaction_type','sales')->orderBy('od_id','desc')->first();
        if($order){
            if($order->verify_status != '1'){
               return  ['status' => 'fail', 'message' => 'Customer Previous Order Verification is pending from Cashier and Guard']; 
            }else if($order->verify_status_guard != '1'){
                return ['status' => 'fail', 'message' => 'Customer Previous Order Verification is pending from Guard' ] ; 
            }
        }


        //Checking any Previous Retrn is in process or not
        // $order = Order::where('store_id', $store_id)->where('v_id' , $v_id)->where('user_id', $c_id)->where('order_id', $order_id)->first();

        //$carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order->o_id)->where('user_id', $c_id)->get();

        // $carts = DB::table('order_details')->where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order->o_id)->where('user_id', $c_id)->get();

        $cart_ids = $invoice->details->pluck('id')->all();
        //dd($cart_ids);

        $ret_req = DB::table('return_request')->where('order_id', $invoice->ref_order_id)->where('confirm','1')->get();

        $cnt = $ret_req->where('status','process')->count();

        if($cnt > 0){
            return ['status' => 'fail', 'message' => 'Your previous return is in progress' ];    
        }

        $param =[];
        $params= [];
        foreach($invoice->details->whereIn('barcode', $returnItemCollect->pluck('item_id')) as $cart){
            if ($cart->weight_flag == 1) {
                $param[] = $cart->total / $cart->qty; 
                $params[] = ['item_id' => $cart->item_id , 'price' => $cart->total, 'r_price'=> $cart->subtotal , 'discount' => $cart->discount , 'tax' => $cart->tax  , 'p_name' =>$cart->item_name , 'unit_mrp' => $cart->unit_mrp  , 'cart_id' => $cart->id  , 'bill_discount' => 0, 'manual_discount' => $cart->manual_discount, 'lpdiscount' => $cart->lpdiscount, 'coupon_discount' => $cart->coupon_discount, 'batch_id' => $cart->batch_id, 'serial_id' => $cart->serial_id ];
            } else {
                $loopQty = $cart->qty;
                while($loopQty > 0){
                    $param[] = $cart->total / $cart->qty; 
                    $params[] = ['item_id' => $cart->item_id , 'price' => $cart->total / $cart->qty, 'r_price'=> $cart->subtotal / $cart->qty , 'discount' => $cart->discount / $cart->qty , 'tax' => $cart->tax / $cart->qty  , 'p_name' =>$cart->item_name , 'unit_mrp' => $cart->unit_mrp  , 'cart_id' => $cart->id  , 'bill_discount' => 0, 'manual_discount' => $cart->manual_discount / $cart->qty, 'lpdiscount' => $cart->lpdiscount / $cart->qty, 'coupon_discount' => $cart->coupon_discount / $cart->qty, 'batch_id' => $cart->batch_id, 'serial_id' => $cart->serial_id ];

                 $loopQty--;
                }

            }
        }

   
        ######################################
        ##### --- BILL BUSTER  START --- #####
        $bill_buster_discount = 0;
        $discount = 0;
        $total_discount = 0;

        //dd($order->bill_buster_discount);

        if($order->bill_buster_discount > 0){
            $bill_buster_discount =  $order->bill_buster_discount;
       
            //Recalcualting taxes when bill buster is applied
            $promo_c = new PromotionController;
            $tax_details =[];
            $ratio_val = $promo_c->get_offer_amount_by_ratio($param, $bill_buster_discount);
            $ratio_total = array_sum($ratio_val);

            $discount = 0;
            $total_discount = 0;
            //dd($param);
            foreach($params as $key => $par){
                $discount = round( ($ratio_val[$key]/$ratio_total) * $bill_buster_discount , 2);
                $params[$key]['bill_discount'] =  $discount;
               // $params[$key]['price'] -= $discount;
                $total_discount += $discount;
            }
        }

        //Thid code is added because facing issue when rounding of discount value
        if($total_discount > $bill_buster_discount){
            $total_diff = $total_discount - $bill_buster_discount;
            foreach($params as $key => $par){
                if($total_diff > 0.00){
                    $params[$key]['bill_discount'] -= 0.01;
                    $total_diff -= 0.01;
                }else{
                    break;
                }
            }
        }else if($total_discount < $bill_buster_discount){
            $total_diff =  $bill_buster_discount - $total_discount;
            foreach($params as $key => $par){
                if($total_diff > 0.00){
                    $params[$key]['bill_discount'] += 0.01;
                    $total_diff -= 0.01;
                }else{
                    break;
                }
            }
        }
        //dd($params);
        //echo $total_discount;

        $data = [];


        $finalReturnItems = $this->filterReturnItem($params, $return_items, $v_id);
    
        return [ 'status' => 'success', 'returns' => $finalReturnItems , 'order' => $invoice ,'return_item' => $return_item , 'ret_req' => $ret_req ];
        

        //print_r($nreturn_items);die;

        foreach($params as $key => $val) {
            $item_id = $val['item_id'];
            $unit_mrp = (int)$val['unit_mrp'];

            $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $item_id)->first();
            if($bar){
                $item  = VendorSku::select('selling_uom_type')->where(['vendor_sku_detail_id' => $bar->vendor_sku_detail_id,'v_id'=> $v_id])->first();
            }

           // if( isset($return_items[$item_id])  &&  $return_items[$item_id]['unit_mrp'] == $unit_mrp){

            //if(array_key_exists($item_id,$nreturn_items)){


            if($this->findKey($return_items,$val) == true) {
                foreach ($return_items as $key => $value) {

                  $getunitmrp  = !empty($value[$item_id]['unit_mrp'])?$value[$item_id]['unit_mrp']:$unit_mrp;

                if(isset($value[$item_id])){
                if($value[$item_id]['request_qty'] > $value[$item_id]['qty'] && $getunitmrp == $unit_mrp ){

                    $getkey  = $key;
                    $return_items[$item_id]['s_price'] += $params[$key]['price'];
                    $return_items[$item_id]['r_price'] += $params[$key]['r_price'];
                    $return_items[$item_id]['discount'] += $params[$key]['discount'];
                    $return_items[$item_id]['lpdiscount'] += $params[$key]['lpdiscount'];
                    $return_items[$item_id]['manual_discount'] += $params[$key]['manual_discount'];
                    $return_items[$item_id]['coupon_discount'] += $params[$key]['coupon_discount'];
                    $return_items[$item_id]['tax'] += $params[$key]['tax'];
                    $return_items[$item_id]['bill_discount'] += $params[$key]['bill_discount'];
                    $return_items[$item_id]['unit_mrp'] = $params[$key]['unit_mrp'];
                    $return_items[$item_id]['p_name'] = $params[$key]['p_name'];
                    $return_items[$item_id]['cart_id'] = $params[$key]['cart_id'];
                    $return_items[$item_id]['p_id'] = (int) $params[$key]['item_id'];
                    
                    if ($item->uom->selling->type == 'WEIGHT') {
                         $return_items[$item_id]['qty'] = $return_items[$item_id]['request_qty'];
                    } else {
                         //$qty = $check_product_in_cart_exists->qty + 1;
                        $return_items[$item_id]['qty'] += 1;
                    }
                }
                
                }
            }

            // unset($nreturn_items[$getkey]);

                   
                }
            }
        
        
        //dd($return_items);
        foreach($return_items as $key => $items){

        // foreach($tax_details_data[$key]['tax'] as $nkey => $tax){
         //        $tax_category = $tax['tax_category'];
         //        $taxable_total = $items['s_price'] - $discount;
         //        $tax['taxable_amount'] = round( $taxable_total * $tax['taxable_factor'] , 2 );
         //        $tax['tax'] =  round( ($tax['taxable_amount'] * $tax['tax_rate']) /100 , 2 );
         //        //$tax_total += $tax['tax'];
         //        $return_items[$key]['tax'] = $tax['tax'];
         //        /*if(isset($tax_details[$tax_category][$tax['tax_code']])){
         //            $tax_details[$tax_category][$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
         //            $tax_details[$tax_category][$tax['tax_code']]['tax'] += $tax['tax'] ;
         //        }else{
         //            $tax_details[$tax_category][$tax['tax_code']] = $tax;
         //        }*/

         //    }

            $return_items[$key]['s_price'] = (string)$return_items[$key]['s_price'];
            $return_items[$key]['r_price'] = (string)$return_items[$key]['r_price'];
            $return_items[$key]['discount'] = (string)$return_items[$key]['discount'];
            $return_items[$key]['lpdiscount'] = (string)$return_items[$key]['lpdiscount'];
            $return_items[$key]['manual_discount'] = (string)$return_items[$key]['manual_discount'];
            $return_items[$key]['coupon_discount'] = (string)$return_items[$key]['coupon_discount'];
            $return_items[$key]['tax'] = (string)$return_items[$key]['tax'];
            $return_items[$key]['bill_discount'] = (string)$return_items[$key]['bill_discount'];
            $return_items[$key]['unit_mrp'] = (string)@$return_items[$key]['unit_mrp'];
            $return_items[$key]['qty'] = (string)$return_items[$key]['qty'];
        }
       
        $return_items = array_values($return_items);
        //dd($return_items);
        return [ 'status' => 'success', 'returns' => $return_items , 'order' => $invoice ,'return_item' => $return_item , 'ret_req' => $ret_req ];
    }


    private function findKey($array, $keySearch)
    {
        foreach ($array as $key => $item) {
            if ($key == $keySearch) {
                return true;
            } elseif (is_array($item) && $this->findKey($item, $keySearch)) {
                return true;
            }
        }
        return false;
    }

    private function findValue($array,$key ,$keySearch)
    {
        foreach ($array as $key => $item) {

            //if($item)

            if ($item['unit_mrp'] == $keySearch) {
                return $key;
            } elseif (is_array($item) && $this->findValue($item, $keySearch)) {
                return $key;
            }
        }
        return false;
    }


    public function return_request(Request $request){

        $v_id = $request->v_id;
        $c_id = $request->customer_id;
        $store_id = $request->store_id;
        $order_id = $request->order_id;
        $confirm = $request->confirm;

        $response = $this->get_return_request($request);
        if($response['status'] == 'fail'){
            return response()->json( $response, 200);
        }
        //dd($response);
        $return_items = $response['returns'];
        $ret_req = $response['ret_req'];
        $order = $response['order'];

        $sub_total = 0;;
        $discount = 0;
        $total = 0;
        $tax_total = 0;
        $cart_qty_total = 0;

        Cart::where('user_id', $c_id)->delete();

        $return_reasons = [];
        //if($return_request){
            $return_reasons = Reason::select('id','description')->where('type','RETURN')->where('v_id', $v_id )->get();
        //}

        foreach($return_items as $items ){
            $cart     = InvoiceDetails::where('id', $items['cart_id'])->first();
            $reson_id = 0;
            if(isset($items['reason_id'])){
                $reson_id   = $items['reason_id'];
            }

            $product_details = json_decode($cart->section_target_offers);

            DB::table('return_request')->insert(
                [ 
                'confirm'       => $confirm,
                'qty'           => $items['request_qty'] , 
                'reason_id'     => $reson_id,
                'subtotal' => $items['r_price'] , 
                'discount' => $items['discount'], 
                'lpdiscount' => $items['lpdiscount'], 
                'manual_discount' => $items['manual_discount'], 
                'coupon_discount' => $items['coupon_discount'], 
                'bill_buster_discount' => $items['bill_discount'], 
                'tax' => $items['tax'], 
                'total' => $items['s_price'],
                'status' => 'process',
                'order_id' => $order_id,
                'store_id' => $cart->store_id,
                'v_id' => $cart->v_id,
                'unit_mrp' => $cart->unit_mrp,
                'cart_id' => $cart->id,
                'user_id' => $cart->user_id,
                'weight_flag' => $cart->weight_flag,
                'plu_barcode' => $cart->plu_barcode,
                'barcode' => $cart->barcode,
                'item_name' => $cart->item_name,
                'item_id' => $cart->item_id ,
                'delivery' => $cart->delivery,
                'slab' => $cart->slab,
                'transaction_type' => 'return',
                'date' => date('Y-m-d'),
                'time' => date('h:i:s'),
                'month' => date('m'),
                'year' => date('Y'),
                'batch_id' => $items['batch_id'],
                'serial_id' => $items['serial_id']
             ]);

            ##############################################################################
            ######## This code is to Generate Same response as order Details  START ######
            //$res = DB::table('cart_offers')->where('cart_id',$cart->cart_id)->first();
            //$offer_data = json_decode($res->offers, true);

            $available_offer = [];
            // foreach($offer_data['available_offer'] as $key => $value){

            //     $available_offer[] =  ['message' => $value ];
            // }
            $offer_data['available_offer'] = $available_offer;
            $applied_offer = [];
            // foreach($offer_data['applied_offer'] as $key => $value){

            //     $applied_offer[] =  ['message' => $value ];
            // }
            $offer_data['applied_offer'] = $applied_offer;
            //dd($offer_data);

            //Counting the duplicate offers
            // $tempOffers = $offer_data['applied_offer'];
            // for($i=0; $i<count($offer_data['applied_offer']); $i++){
            //     $apply_times = 1 ;
            //     $apply_key = 0;
            //     for($j=$i+1; $j<count($tempOffers); $j++){
                    
            //         if(isset($offer_data['applied_offer'][$j]['message']) && $tempOffers[$i]['message'] == $offer_data['applied_offer'][$j]['message']){
            //             unset($offer_data['applied_offer'][$j]);
            //             $apply_times++;
            //             $apply_key = $j;
            //         }

            //     }
            //     if($apply_times > 1 ){
            //         $offer_data['applied_offer'][$i]['message'] = $offer_data['applied_offer'][$i]['message'].' - ' .$apply_times.' times';
            //     }

            // }
            $total += $items['s_price'];
            $offer_data['available_offer'] = array_values($offer_data['available_offer']);
            $offer_data['applied_offer'] = array_values($offer_data['applied_offer']);

            $carr_bag_arr =  [ '114903443', '114952448' ,'114974444'];
            $carry_bag_flag = in_array($cart->item_id, $carr_bag_arr);

            $product_data['return_flag'] = 'true';
            $product_data['return_qty'] = (string)$items['qty'];
            $product_data['carry_bag_flag'] = $carry_bag_flag ;
            $product_data['isProductReturn'] = true;
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
            $product_data['multiple_price_flag'] = false;
            $product_data['multiple_mrp'] = [$items['r_price']];
            $product_data['r_price'] = $items['s_price'];
            $product_data['s_price'] = $items['s_price'];
            $product_data['unit_mrp'] = format_number($cart->unit_mrp);
            $product_data['uom'] = $product_details->uom;
            /*if(!empty($offer_data['applied_offers']) ){
                $product_data['r_price'] = format_number($offer_data['r_price']);
                $product_data['s_price'] = format_number($offer_data['s_price']);
            }*/

            $product_data['varient'] = '';
            $product_data['images'] = '';
            $product_data['description'] = '';
            $product_data['deparment'] = '';
            $product_data['barcode'] = $cart->barcode;
            $product_data['discount'] = $items['discount'] + $items['lpdiscount'] + $items['manual_discount'] + $items['coupon_discount']+ $items['bill_discount'];

            $return_reason = Reason::select('id','description')->where('v_id', $v_id )->where('id', $reson_id)->first();
            if($return_reason){
                $product_data['reason'] = $return_reason->description;
                $product_data['reason_added'] = '1';

            }else{
                $product_data['reason'] = '';
                $product_data['reason_added'] = '0';

            }
            

            //$tax_total = $tax_total +  $tax_amount ;
            $tax_amount = $items['tax'];
            //$cart_qty_total =  $cart_qty_total + $items['qty'];
            $approved_qty = $ret_req->where('status','approved')->where('item_id',$cart->item_id)->sum('qty');

            $return_product_qty = $cart->qty - $approved_qty;
            $cart_data[] = array(
                    'cart_id'       => $cart->id,
                    'product_data'  => $product_data,
                    'amount'        => $cart->total,
                    'qty'           => $items['qty'],
                    'return_product_qty' => (string) $return_product_qty, //MAx quantity available fo retrun
                    'tax_amount'    => $tax_amount,
                    'delivery'      => $cart->delivery,
                    'item_flag'     => 'NORMAL'
            );

            $sub_total += $items['s_price'];
            $discount += $items['discount'] + $items['lpdiscount'] + $items['manual_discount'] + $items['coupon_discount']+ $items['bill_discount'];
            $tax_total += $items['tax'];
            if($cart->weight_flag == 1){
                $cart_qty_total = $cart_qty_total+1;
            }else{
                $cart_qty_total += $items['qty'];
            }
            ######## This code is to Generate Same response as order Details  END  ######
            #############################################################################

        }
        
        //dd($params);

        $summary['summary'][] = [ 'name' => 'sub_total' , 'display_text' => 'Sub Total' , 'value' => format_number($sub_total),'sign'=>'' ];
        if($discount != 0) {
            $summary['summary'][] = [ 'name' => 'discount' , 'display_text' => 'Bill Discount' , 'value' => format_number($discount),'sign'=>'-' ];
        }
        // Round Off Calculation
        if (!empty(getRoundValue($total))) {

             

            $summary['summary'][] = [ 'name' => 'roundoff' , 'display_text' => 'Round Off' ,'display_name' => 'Round Off' ,'item_type'=>"", 'value' => abs(getRoundValue($total)), 'sign' => getRoundValue($total) < 0 ? '-' : '+' ];
        }
        $total  = round($total);
        $summary['summary'][] = [ 'name' => 'total' , 'display_text' => 'Total' , 'value' => format_number($total),'sign'=>'' ];
        $summary['summary'][] = [ 'name' => 'total_refund' , 'display_text' => 'Total Refund' , 'value' => format_number($total),'sign'=>'' ];
        $summary['item_qty'] = $cart_qty_total;
        $returnItems = [];
        foreach ($return_items as $prod) {
            $returnItems[] = ['p_name' => utf8_encode($prod['p_name'])  , 'qty' => $prod['qty'] , 'total' => format_number($prod['s_price'])];
        }
        $summary['items'] = $returnItems;

        return response()->json(['status' => 'order_details', 'message' => 'Your Cart Details', 
            'data' => $cart_data,
            
            //'sub_total' => (format_number($sub_total)) ? format_number($sub_total) : '0.00',
            //'tax_total' => (format_number($tax_total)) ? format_number($tax_total) : '0.00',
            //'tax_details' => $tax_details,
            //'bill_buster_discount' => (format_number($bill_buster_discount)) ? format_number($bill_buster_discount) : '0.00',
            'discount' => (format_number($discount)) ? format_number($discount) : '0.00',
            //'grand_total' => (format_number($grand_total))?format_number($grand_total):'0.00',
            'order_id' => $order_id,
            'order_summary' => $summary,
            'total' => format_number($total),
            'cart_qty_total' => (string) $cart_qty_total,
            'mobile' => $order->user->mobile,
            'customer_name' => $order->user->first_name.' '.$order->user->last_name, 
            'payment_method' => 'Voucher',
            //'saving' => (format_number($saving)) ? format_number($saving) : '0.00'
             ]);        

    }


    public function approve(Request $request){
        $vu_id    = $request->vu_id;
        $v_id     = $request->v_id;
        $c_id     = $request->customer_id;
        $store_id = $request->store_id;
        $order_id = $request->order_id;


       /* $smsC = new SmsController;
        $smsParams = ['mobile' => '8800886321', 'voucher_amount' => '200', 'voucher_no' => 'DDFDL', 'expiry_date' => '02-02-2020', 'v_id' => '127', 'store_id' => '228'];
        $smsResponse = $smsC->send_voucher($smsParams);*/

        

        $checkInvoice  = Invoice::where('invoice_id',$order_id)->first();
        $to_gstin   = empty($checkInvoice->cust_gstin)?'':$checkInvoice->cust_gstin;
        $cust_gstin_state_id = @$checkInvoice->cust_gstin_state_id;
        $invoice_type= 'B2C';
        if(!empty($to_gstin) && $to_gstin != ''){
            $invoice_type= 'B2B';
        }


        //return response()->json(['order_id'=>$order_id,'to_gstin'=>$to_gstin]);

        if(empty($order_id)){
             return response()->json(['status' => 'fail', 'message' => 'Order not found. Please try again' ]);
        }
        $trans_from = $request->trans_from;
        $session_id =0;
        if($request->get('credit_issue') == 'cash'){
            $credit_issue = $request->get('credit_issue');
            $refund_mode = 'Cash Refund';
        }else if($request->get('credit_issue') == 'voucher'){
            $credit_issue = $request->get('credit_issue');
            $refund_mode = 'Store Credit';
        }
        else{
            $credit_issue = 'voucher';
            $refund_mode = 'Store Credit';
        }
        $remark   = '';
        $udidtoken = '';
        $invoice_seq =null;
        if ($request->has('udidtoken')) {
            $udidtoken    = $request->udidtoken;
            $terminalInfo = CashRegister::where('udidtoken',$udidtoken)->first(); 
        }
        if($request->has('invoice_seq')){
         $invoice_seq =   $request->invoice_seq;
        }
        $print_url = null;
        if($request->has('remark')){
            $remark = $request->remark;
        }
        /* retun information */
        
        $current_date = date('Y-m-d'); 

        $settlementSession = SettlementSession::select('id')->where(['v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id ,'trans_from' => $trans_from, 'settlement_date' => $current_date ])->orderBy('opening_time','desc')->first();
        if($settlementSession){
            $session_id = $settlementSession->id;
        }
        $stores       = Store::find($store_id);
        $short_code   = $stores->short_code;
        $userDetail   = User::find($c_id);
            
        
        $admin_id   = $request->admin_id;
        $status     = 'approved';
        $trans_from = $request->trans_from;

        // $t_order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $t_order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
        $t_order_id = $t_order_id + 1;

        $subtotal = 0;
        $discount = 0;
        $lpdiscount = 0;
        $coupon_discount = 0;
        $manual_discount = 0;
        $ilm_discount_total = 0;
        $bill_buster_discount = 0;
        $tax        = 0;
        $total      = 0;
        $employee_id = 0;
        $employee_discount =0;
        $employee_available_discount = 0;
        $total_qty = 0; 

        $request->request->add(['confirm' => 0]);
        $response = $this->get_return_request($request);
        if($response['status'] == 'fail'){
            return response()->json( $response, 200);
        }
        $return_items = $response['returns'];

        // DB::beginTransaction();

        // try {

            foreach($return_items as $item ) {

                $subtotal += $item['r_price'];
                $discount += $item['discount'];
                $bill_buster_discount += $item['bill_discount'];
                $manual_discount += $item['manual_discount'];
                $total += $item['s_price'];
                $tax += $item['tax'];
                $total_qty +=  $item['request_qty'];

                $ex_cart = InvoiceDetails::where('id', $item['cart_id'])->first();

                $tax_data = json_decode($ex_cart->tdata,true);

                //Item Level Discount
                if( $ex_cart->item_level_manual_discount != null){
                    $disc = json_decode($ex_cart->item_level_manual_discount);
                    $ilm_discount_total += (float)$disc->discount;
                }

                /*Tax Calculation start for return  value*/
                $sprice = $item['s_price'];   // -- /$item['qty']
                $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode','sku_code')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $ex_cart->barcode)->first();
                $item_master = null;
                if($bar){
                    $item_master = VendorSku::select('v_id','item_id','hsn_code','tax_type')->where(['vendor_sku_detail_id'=> $bar->vendor_sku_detail_id,'v_id'=>$v_id])->first();
                }
                if(!$item_master){
                    $item_master = VendorSku::select('v_id','item_id','hsn_code','tax_type')->where(['sku'=> $ex_cart->barcode,'v_id' => $v_id])->first();
                }

                if($item_master->vendorItem->tax_type == 'EXC'){
                    $sprice    = $sprice-$item['tax'];
                }

                $from_gstin = $stores->gst;
                //echo $checkInvoice->cust_gstin
                

                $params = array('barcode'=>$ex_cart->barcode,'qty'=>$item['qty'],'s_price'=> $sprice,'hsn_code'=>$tax_data['hsn'],'store_id'=>$ex_cart->store_id,'v_id'=>$ex_cart->v_id,'from_gstin' => $from_gstin , 'to_gstin' => $to_gstin,'invoice_type'=>$invoice_type);



                //print_r($params);


     
                $cController = new CartController;
                $tax_detail = $cController->taxCal($params);

               // return response()->json(['order_id'=>$order_id,'to_gstin'=>$to_gstin,'params'=>$params,$tax_detail]);

                //dd($tax_detail);

                /*Tax Calculation start for return  value*/
             

               // Insert Order Details data
                     
                $createOrderDetails = OrderDetails::create(
                    [ 
                    'transaction_type' => 'return',
                    'store_id' => $store_id == $ex_cart->store_id ? $ex_cart->store_id : $store_id,
                    'v_id' => $ex_cart->v_id,
                    'order_id' => $t_order_id,
                    'user_id' => $ex_cart->user_id,
                    'plu_barcode' => $ex_cart->plu_barcode,
                    'item_name' => $ex_cart->item_name,
                    'weight_flag' => $ex_cart->weight_flag,
                    'barcode' => $ex_cart->barcode,
                    'sku_code' => $ex_cart->sku_code,
                    'qty' => (string)$item['request_qty'],
                    'unit_mrp' => $ex_cart->unit_mrp,
                    'unit_csp' => $ex_cart->unit_csp,
                    'subtotal' => (string)$item['r_price'],
                    'total' => (string)$item['s_price'],
                    'discount' => (string)$item['discount'],
                    'lpdiscount' => (string)$item['lpdiscount'],
                    'manual_discount' => (string)$item['manual_discount'],
                    'item_level_manual_discount' => $ex_cart->item_level_manual_discount,
                    'coupon_discount' => (string)$item['coupon_discount'],
                    'bill_buster_discount' => (string)$item['bill_discount'],
                    'tax' => $tax_detail['tax'],
                    'status' => 'success',
                    'trans_from' => $request->trans_from,
                    'vu_id' => $request->vu_id,
                    'date' => date('Y-m-d'),
                    'time' => date('h:i:s'),
                    'month' => date('m'),
                    'year' => date('Y'),
                    'target_offer' => $ex_cart->target_offer,
                    'slab' => $ex_cart->slab,
                    'section_target_offers' => $ex_cart->section_target_offers,
                    'section_offers' => $ex_cart->section_offers,
                    'item_id' => $ex_cart->item_id,
                    'department_id' => $ex_cart->department_id,
                    'subclass_id' => $ex_cart->subclass_id,
                    'printclass_id' => $ex_cart->printclass_id,
                    'group_id' => $ex_cart->group_id,
                    'division_id' => $ex_cart->division_id,
                    'pdata'   => $ex_cart->pdata,
                    'tdata'   => json_encode($tax_detail),
                    'reason_id' => isset($item['reason_id'])?$item['reason_id']:0,
                    'batch_id' => $item['batch_id'],
                    'serial_id' => $item['serial_id']
                    ],
                );

                $orderDetails = OrderDetails::find($createOrderDetails->id)->toArray();

                // Insert Order Details data in Invoice Details

                InvoiceDetails::create($orderDetails);

                //DB::table('return_request')->where('id', $item->id)->update(['status' => 'approved']);

            }


            $last_transaction_no = 0;
            $last_order = Order::where('v_id',$v_id)->where('store_id', $store_id)->where('status','success')->orderBy('od_id','desc')->first();
            $last_transaction_no = $last_order->transaction_no;

            $r_order_id = order_id_generate($store_id, $c_id , $trans_from);
            $store_data = Store::find($store_id);

            $round_off = 0;
            if (!empty(getRoundValue($total))) {
                $round_off = getRoundValue($total);
            }
            $total  = round($total);

            $order = new Order;
            $order->transaction_type = 'return';
            $order->transaction_sub_type = 'return';
            $order->comm_trans = $invoice_type;
            $order->cust_gstin = $to_gstin;
            $order->cust_gstin_state_id = $cust_gstin_state_id;
            $order->order_id = $r_order_id;
            $order->ref_order_id = $order_id;
            $order->o_id = $t_order_id;
            $order->qty  = (string)$total_qty;
            $order->v_id = $v_id;
            $order->vu_id = $vu_id;
            $order->store_id = $store_id;
            $order->user_id = $c_id;
            $order->subtotal = (string)$subtotal;
            $order->store_gstin = $store_data->gst;
            $order->store_gstin_state_id = $store_data->state_id;
            $order->discount = $discount;
            $order->lpdiscount = $lpdiscount;
            $order->manual_discount = $manual_discount;
            $order->ilm_discount_total = $ilm_discount_total;
            $order->coupon_discount = $coupon_discount;
            if ($v_id == 4) {
                $order->employee_id = $employee_id;
                $order->employee_discount = $employee_discount;
                $order->employee_available_discount = $employee_available_discount;
                $order->bill_buster_discount = $bill_buster_discount;
            }
            if ($trans_from == 'ANDROID_VENDOR' || $trans_from == 'CLOUD_TAB_WEB') {
                $order->verify_status       = '1';
                $order->verify_status_guard = '1';
            }
            $order->bill_buster_discount = $bill_buster_discount;
            $order->tax = (string)$tax;
            $order->round_off = (string)$round_off;
            $order->total = (string)$total;
            $order->transaction_no = $last_transaction_no;
            $order->return_by = $credit_issue;
            $order->status = 'success';
            $order->date = date('Y-m-d');
            $order->trans_from = $trans_from;
            $order->remark = $remark;
            $order->time = date('h:i:s');
            $order->month = date('m');
            $order->year = date('Y');

            $order->save();

            DB::table('order_details')->where('store_id',$store_id)->where('v_id',$v_id)->where('user_id',$c_id)->where('order_id', $t_order_id)->where('transaction_type','return')->update( ['t_order_id' => $order->od_id]);

            //$zwing_invoice_id = invoice_id_generate($store_id, $c_id, $trans_from,$request->udidtoken);
           // offlien and online invoice generate 
                 $role_id = getRoleId($vu_id);
                    $params  = array('v_id'=>$v_id,'store_id'=>$store_id,'name' =>'vendor_app', 'user_id'=>$vu_id,'role_id'=>$role_id);
                    $setting  = new VendorSettingController;
                    $vendorAppSetting = $setting->getSetting($params)->pluck('settings')->toArray();
                    $vendorAppSettings = json_decode($vendorAppSetting[0]);
                    if(isset($vendorAppSettings->offline) && $vendorAppSettings->offline->status =='1'){
                    $inc_id  = $invoice_seq;        
                    $zwing_invoice_id  = invoice_id_generate($store_id, $c_id, $trans_from,$invoice_seq,$udidtoken);
                    //dd($zwing_invoice_id);    

                  }else{
                    //dd("abc");
                 $zwing_invoice_id  = invoice_id_generate($store_id, $c_id, $trans_from,$invoice_seq,$udidtoken);

                // Getting incrementing id for invoice sequence
                 $inc_id  = invoice_id_generate($store_id, $c_id, $trans_from,$invoice_seq,$udidtoken,'seq_id');
                }

            $custom_invoice_id = custom_invoice_id_generate(['store_id' => $store_id, 'user_id' => $c_id, 'trans_from' => $trans_from]);
                $invoice = new Invoice;
                $invoice->invoice_id = $zwing_invoice_id;
                $invoice->custom_order_id = $custom_invoice_id;
                $invoice->ref_order_id = $order->order_id;
                $invoice->transaction_type = $order->transaction_type;
                $invoice->transaction_sub_type = $order->transaction_sub_type;
                $invoice->comm_trans = $invoice_type;
                $invoice->cust_gstin = $to_gstin;
                $invoice->cust_gstin_state_id = $order->cust_gstin_state_id;
                $invoice->store_gstin   =     $order->store_gstin;
                $invoice->store_gstin_state_id  = $order->store_gstin_state_id;
                $invoice->v_id = $v_id;
                $invoice->store_id = $store_id;
                $invoice->user_id = $c_id;
                $invoice->qty = $order->qty;
                $invoice->invoice_sequence  = $inc_id;
                $invoice->subtotal = $order->subtotal;
                $invoice->discount = $order->discount;
                $invoice->invoice_sequence  = $inc_id;
                $invoice->lpdiscount = $order->lpdiscount;
                $invoice->manual_discount = (string)$order->manual_discount;
                $invoice->coupon_discount = $order->coupon_discount;
                $invoice->bill_buster_discount = $bill_buster_discount;
                $invoice->tax = $order->tax;
                $invoice->round_off = $order->round_off;
                $invoice->total = $order->total;
                $invoice->trans_from = $trans_from;
                $invoice->vu_id = $vu_id;
                $invoice->remark = $remark;
                $invoice->session_id        = $session_id;
                $invoice->store_short_code  = $short_code;
                $invoice->terminal_name     = isset($terminalInfo)?$terminalInfo->name:'';
                $invoice->terminal_id       = isset($terminalInfo)?$terminalInfo->id:'';
                $invoice->customer_first_name     = isset($userDetail->first_name)?$userDetail->first_name:'';
                $invoice->customer_last_name     = isset($userDetail->last_name)?$userDetail->last_name:'';
                $invoice->customer_number     = isset($userDetail->mobile)?$userDetail->mobile:'';
                $invoice->customer_email     = isset($userDetail->email)?$userDetail->email:'';
                $invoice->customer_gender     = isset($userDetail->gender)?$userDetail->gender:'';
                $invoice->customer_address  = isset($userDetail->address)?$userDetail->address->address1:'';
                $invoice->customer_pincode  = isset($userDetail->address)?$userDetail->address->pincode:'';
                //phone code update in
                $invoice->customer_phone_code  = isset($userDetail->customer_phone_code)?$userDetail->customer_phone_code:'+91';
                $invoice->date = date('Y-m-d');
                $invoice->time = date('H:i:s');
                $invoice->month = date('m');
                $invoice->year = date('Y');
                $invoice->financial_year = getFinancialYear();
                $invoice->save();

            DB::table('invoice_details')->where('store_id',$store_id)->where('v_id',$v_id)->where('user_id',$c_id)->where('order_id', $t_order_id)->where('transaction_type','return')->update( ['t_order_id' => $invoice->id]);

            $role = VendorRoleUserMapping::select('role_id')->where('user_id', $vu_id)->first();
            $role_id  = $role->role_id;

            $sParams = ['v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $vu_id, 'role_id' => $role_id, 'trans_from' => $trans_from];

            $vendorS = new VendorSettingController;
            $getFeatureSetting = $vendorS->getFeatureSetting($sParams);
            $expiry_credit_note_status = $getFeatureSetting->credit_note_expiry->DEFAULT->status;
            $expiry_credit_note_date = $getFeatureSetting->credit_note_expiry->DEFAULT->options[0]->minimun_no_days->value;
            $voucher_no = generateRandomString(6);
            $today_date = date('Y-m-d H:i:s');
            if($expiry_credit_note_status == '1')
            {
                if($expiry_credit_note_date != '')
                    {
                        $next_date =  date('Y-m-d H:i:s' ,strtotime($expiry_credit_note_date.'days', strtotime($today_date)));
                    }else{
                        $next_date = date('Y-m-d H:i:s' ,strtotime('+16 years', strtotime($today_date)));
                    }
            }else{
                $next_date = date('Y-m-d H:i:s' ,strtotime('+16 years', strtotime($today_date)));
            }
            // dd($role_id,$c_id,$vu_id,$sParams,$expiry_credit_note_date,$expiry_credit_note_status,$next_date,$today_date);
            // $voucher_no = generateRandomString(6);
            // $today_date = date('Y-m-d H:i:s');
            // $next_date =  date('Y-m-d H:i:s' ,strtotime('+30 days', strtotime($today_date)) );

            if($credit_issue == 'voucher'){
                $paramsCr = array('status'=> 'Process');
                $request->merge([
                    //'order_id' => $invoice->invoice_id
                    'tr_type'     => 'Credit',
                    'user_id'  => $c_id,
                    'invoice_no' => $invoice->invoice_id,
                    'amount'   =>  $invoice->total
                ]);
                $actSaleCtr  = new AccountsaleController;
                $crDrDep     = $actSaleCtr->createDepRfdRrans($request,$paramsCr);
                if($crDrDep){

                    $vcher = DB::table('cr_dr_voucher')->insertGetId(['v_id'=> $v_id , 'store_id' => $store_id ,  'user_id'=> $c_id ,'dep_ref_trans_ref'=> $crDrDep->id ,'amount' => $total , 'ref_id' => $r_order_id , 'status' => 'unused' ,'type' => 'voucher_credit' , 'voucher_no' => $voucher_no, 'effective_at' => $today_date   , 'expired_at' => $next_date ]);
                    if($vcher){
                        $paramsLg    = array('trans_src_ref_id' => $crDrDep->id,'trans_src' =>'Credit-Note','applied_amount'=>$total,'voucher_id'=>$vcher,'status'=>'APPLIED');
                        $crDrLog     = $actSaleCtr->createVocherSettLog($request,$paramsLg);

                        if($crDrLog){

                            DB::table('dep_rfd_trans')->where('id',$crDrDep->id)->update(['status' => 'Success']);
                        }else{
                            DB::table('cr_dr_voucher')->where('id',$vcher)->update(['status' => 'Failed']);
                            DB::table('dep_rfd_trans')->where('id',$crDrDep->id)->update(['status' => 'Failed']);
                        }
                    }else{
                        DB::table('dep_rfd_trans')->where('id',$crDrDep->id)->update(['status' => 'Failed']);
                    }
                }
            }

            $payment = new Payment;
            $payment->v_id      = $v_id;
            $payment->store_id  = $store_id;
            $payment->user_id   = $c_id;
            $payment->order_id  = $r_order_id;
            $payment->invoice_id= $invoice->invoice_id;
            $payment->pay_id = $voucher_no;
            $payment->amount = $total;
            $payment->method = $request->credit_issue == 'cash' ? 'cash' : 'credit_note_issued';

            $payment->payment_gateway_type = strtoupper($credit_issue);
            // $payment->method = 'credit_note_issued';
            // $payment->payment_gateway_type = 'VOUCHER';
            
            $payment->status = 'success';
            $payment->date = date('Y-m-d');
            $payment->time = date('h:i:s');
            $payment->month = date('m');
            $payment->year = date('Y');
            $payment->save();
            if($payment){

                $order_data = InvoiceDetails::where('t_order_id', $invoice->id)->get()->toArray();
                foreach ($order_data as $value) {
                    $params = array('v_id' => $value['v_id'], 'store_id' => $value['store_id'], 'barcode' => $value['barcode'], 'sku_code' => $value['sku_code'], 'qty' => $value['qty'], 'invoice_id' => $invoice->invoice_id, 'batch_id' => $value['batch_id'], 'serial_id' => $value['serial_id'], 'order_id' => $invoice->ref_order_id,'vu_id'=>$vu_id,'trans_from'=>$trans_from,'transaction_scr_id'=>$invoice->id,'transaction_type'=>'RETURN');
                    $this->cartconfig->updateStockQty($params);
                     
                }
                         
            }

            if($credit_issue == "cash"){
                $transaction_type = 'return';
                $setting  = new VendorSettingController;
                $CartCon = new OldCartController;
                $params  = array('v_id'=>$v_id,
                                              'store_id'=>$store_id,
                                              'name' =>'store',
                                              'user_id'=>$vu_id,
                                              'role_id'=>$role_id
                                            );
                     $storeSetting = $setting->getSetting($params)->pluck('settings')->toArray();
                     $storeSettings = json_decode($storeSetting[0]);
                     if(isset($storeSettings->cashmanagement) && $storeSettings->cashmanagement->DEFAULT->status=='1'){
                            $terminal_id = CashRegister::select('id')->where('udidtoken',$udidtoken)->first();
                            $CartCon->cashPointTranscationUpdate($store_id,$v_id,$payment->invoice_id,$vu_id,$terminal_id->id,$transaction_type);
                     }
                                                //end cashmangement
            }
            // event(new SaleItemReport($invoice->invoice_id));
            // DB::commit();
        // } catch(Exception $e) {
          // DB::rollback();
          // exit;
        // }



        $cust = DB::table('customer_auth')->select(['mobile','first_name','last_name','email'])->where('c_id', $c_id)->first();
        $mobile = $cust->mobile;
        $customer_name = $cust->first_name.' '.$cust->last_name;
        $customer_email= $cust->email;
        if($request->get('credit_issue') == 'voucher'){

            $numbers = "91".$mobile;      
            $dates = explode(' ',$next_date);
            /*sending sms via common sms controller*/
            $smsC = new SmsController;
            $smsC->send_voucher(['mobile' => $mobile , 'voucher_amount' => $total, 'voucher_no' => $voucher_no , 'expiry_date' => $dates[0] , 'v_id' => $v_id, 'store_id' =>  $store_id , 'store_name' => $store_data->name ]);
        }

        $orderC = new OrderController;
        $order_arr = $orderC->getOrderResponse(['order' => $order , 'v_id' => $v_id , 'trans_from' => $trans_from ]) ;
        if($request->trans_from == 'CLOUD_TAB_WEB') {
            
            $request->merge([
                'v_id' => $v_id,
                'c_id' => $c_id,
                'store_id' => $store_id,
                'order_id' => $invoice->invoice_id
            ]);
            $gcartC = new CartController;
            $oldCart = new OldCartController;
            $htmlData = $gcartC->get_print_receipt($request);
            /*Get Setting start*/
            $role        = VendorRoleUserMapping::select('role_id')->where('user_id', $vu_id)->first();
            $sParams     = ['v_id' => $v_id,'store_id'=>$store_id,'user_id'=>$c_id,'role_id'=>@$role->role_id,'trans_from' => $trans_from];
            $vendorS     = new VendorSettingController;
            $printSetting= $vendorS->getPrintSetting($sParams);
            if(count($printSetting) > 0){
                foreach($printSetting as $psetting){
                  if($psetting->name == 'bill_print'){
                    $bill_print_type = $psetting->width;
                  }
                }
            }
            /*Get Setting end*/
            if($bill_print_type == 'A4' && $trans_from == 'CLOUD_TAB_WEB'){
                $invoice->html_data = $htmlData;
            }else{
                $html = $htmlData->getContent(); 
                $html_obj_data = json_decode($html);   
                if($html_obj_data->status == 'success')
                {
                $invoice->html_data =  $oldCart->get_html_structure($html_obj_data->print_data);
                }
            }
        
        }
         
        $db_structure = DB::table('vendor')->select('db_structure')->where('id', $v_id)->first()->db_structure;
        //[ 'order_id' => $invoice->invoice_id ]

        if(isset($invoice)){
                        $zwingTagVId = '<ZWINGV>'.$v_id.'<EZWINGV>';
                        $zwingTagStoreId = '<ZWINGSO>'.$store_id.'<EZWINGSO>';
                        $zwingTagTranId = '<ZWINGTRAN>'.$invoice->id.'<EZWINGTRAN>';
                        event(new InvoiceCreated([
                            'invoice_id' => $invoice->id,
                            'v_id' => $v_id,
                            'store_id' => $store_id,
                            'db_structure' => $db_structure,
                            'type'=>'RETURN',
                            'zv_id' => $zwingTagVId,
                            'zs_id' => $zwingTagStoreId,
                            'zt_id' => $zwingTagTranId
                            ] 
                            )
                        );
        }
        
      $print_url  =  env('API_URL').'/order-receipt/'.$c_id.'/'.$v_id.'/'.$store_id.'/'.$invoice->invoice_id;
      return response()->json(['status' => 'success', 'message' => 'Retrun of items has been approved successfully' , 'data' => $invoice, 'order_id' => $invoice->invoice_id,'invoice_id' => $invoice->invoice_id,'refund_mode'=>$refund_mode,'return_remark'=>'','customer_name'=>$customer_name,'customer_email'=>$customer_email,'customer_mobile'=>$mobile,'account_balance'=>'','store_credit'=>'','loyalty_points'=>'','order_summary' => $order_arr,'print_url'=>$print_url,'' ]);

    }

    public function update(Request $request){
        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;
        $order_id = $request->order_id;
        //$return_item = json_decode($request->return_item);

        DB::table('return_request')->where('order_id', $order_id)->where('status','process')->delete();

        return $this->return_request($request);


    }

    public function delete(Request $request){
        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;
        $order_id = $request->order_id;

        DB::table('return_request')->where('order_id', $order_id)->where('status','process')->delete();

        return response()->json(['status' => 'success', 'message' => 'Data deleted successfully' ]);

    }

    public function filterReturnItem($arrayItem, $items, $v_id)
    {
        $arrayItem = collect($arrayItem);
        $items = collect($items)->map(function($value) use(&$arrayItem, $v_id) {
            $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $value['item_id'])->first();
            if($bar){
                $item  = VendorSku::select('selling_uom_type')->where(['vendor_sku_detail_id' => $bar->vendor_sku_detail_id,'v_id'=> $v_id])->first();
            }
            $filterItem = $arrayItem->where('item_id', $value['item_id'])
                                    ->where('unit_mrp', $value['unit_mrp'])
                                    ->filter(function($item) use ($value) {
                                        return $item['batch_id'] == $value['batch_id'] && $item['serial_id'] == $value['serial_id'];
                                    })
                                    ->take($item->selling_uom_type == 'WEIGHT' ? 1 : $value['request_qty']);
            
            $qty = 1;
            if( $item->selling_uom_type == 'WEIGHT' ){
                $qty = $value['request_qty'];
            }else{
                $qty = $filterItem->count();
            }

            $value['qty'] = $qty;
            $value['discount'] = $filterItem->sum('discount');
            $value['lpdiscount'] = $filterItem->sum('lpdiscount');
            $value['manual_discount'] = $filterItem->sum('manual_discount');
            $value['coupon_discount'] = $filterItem->sum('coupon_discount');
            $value['tax'] = $filterItem->sum('tax');
            $value['bill_discount'] = $filterItem->sum('bill_discount');
            $value['s_price'] = $filterItem->sum('price');
            $value['r_price'] = $filterItem->sum('r_price');
            $value['p_name'] = $filterItem->first()['p_name'];
            $value['cart_id'] = $filterItem->first()['cart_id'];
            return $value;
        });
        return $items->values()->toArray();
    }

}
