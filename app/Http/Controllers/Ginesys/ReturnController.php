<?php

namespace App\Http\Controllers\Ginesys;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\CartController as OldCartController;
use Illuminate\Http\Request;
use App\Mail\OrderCreated;
use Illuminate\Support\Facades\Mail;
use PDF;
use App\Store;
use App\Order;
use App\Cart;
use App\User;

use App\CashRegister;
use App\SettlementSession;

use App\Vendor;
use App\Payment;
use App\Invoice;
use App\InvoiceDetails;
use App\Reason;
use DB;
use Auth;
use App\SmsLog;
use Event;
use App\Events\Loyalty;
use App\LoyaltyBill;
use App\Http\Controllers\LoyaltyController;
use App\OrderDetails;

class ReturnController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function authorized(Request $request)
    {
        //$vu_id = $request->vu_id;
        $v_id = $request->v_id;
        //$c_id = $request->c_id;
        $store_id = $request->store_id; 
        $trans_from = $request->trans_from;
        $security_code = $request->security_code;

        //$order_id = $order_id; 
        $operation = 'return_authorization';

        $vendor = Vendor::where('vendor_user_random', $security_code)->where('type','manager')->first();

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

    public function get_order(Request $request)
    {
        $v_id = $request->v_id;
        //$c_id = $request->c_id;
        $store_id = $request->store_id;
        $cust_order = $request->cust_order;
        $trans_from = $request->trans_from;

        if(is_numeric($cust_order)){
            
            $profileC = new ProfileController;
            $cust = User::where('mobile' ,$cust_order)->where('v_id', $v_id)->first();

            if($cust){

                return response()->json(['status' => 'success' , 'go_to' => 'my_order' , 'data' => [ 'c_id' => $cust->c_id , 'api_token' => $cust->api_token ] ]);
                
            }else{
                return response()->json(['status' => 'fail', 'message' => 'Unable to find the customer' ]);
            }

        }else{
            $cartC = new CartController;

            $order = Invoice::where('invoice_id', $cust_order)->where('status' , 'success')->first();
            if($order){

                $request->request->add();
                //return $cartC->order_details($request);
                $cust = User::where('c_id' ,$order->user_id)->where('v_id', $v_id)->first();
                return response()->json(['status' => 'success' , 'go_to' => 'order_details' , 'data' => [ 'v_id' => $order->v_id , 'store_id' => $order->store_id , 'c_id' => $order->user_id, 'api_token' => $cust->api_token, 'order_id' => $cust_order, 'trans_from' => $trans_from ]
                 ]);
                
            }else{

                return response()->json(['status' => 'fail', 'message' => 'Unable to find the Order' ]);
            }

        }
    }


    public function get_return_item(Request $request)
    {    
        $v_id = $request->v_id;
        $c_id = $request->customer_id;
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

    public function get_return_request(Request $request)
    {
        $v_id = $request->v_id;
        $c_id = $request->customer_id;
        $store_id = $request->store_id;
        $invoice_id = $request->order_id;
        $confirm = $request->confirm;
        $return_item = json_decode($request->return_item);

        $invoice = Invoice::where('invoice_id', $invoice_id)->first();

        $return_items = [];
        foreach($return_item as $item){
            $item_id = $item->item_id;
            $item_return = 0;
            if(isset($item->reason) && $item->reason!=''){
                $item_return = $item->reason;  
            }
            if(isset($item->reason_id)){
             $return_items[$item_id]['reason_id'] = $item->reason_id;
            }
          
            $return_items[$item_id]['request_qty'] = $item->qty;
            $return_items[$item_id]['qty'] = 0;
            $return_items[$item_id]['discount'] = 0;
            $return_items[$item_id]['tax'] = 0;
            $return_items[$item_id]['bill_discount'] = 0;
            $return_items[$item_id]['s_price'] = 0;
            $return_items[$item_id]['r_price'] = 0;
            $return_items[$item_id]['coupon_discount'] = 0;
            $return_items[$item_id]['lpdiscount'] = 0;
            $return_items[$item_id]['manual_discount'] = 0;
            $return_items[$item_id]['p_name'] = 0;
        }

        $return_item_data = [];
        
        // Before return any Items check any previous order is pending or not
        $order = Order::select('order_id', 'v_id' , 'store_id','date','time','total','verify_status','verify_status_guard')->where('user_id',$c_id)->where('v_id' , $v_id)->where('status','success')->where('transaction_type','sales')->orderBy('od_id','desc')->first();
        if($order){
            if($order->verify_status != '1'){
               return  ['status' => 'fail', 'message' => 'Customer Previous Order Verification is pending from Cashier and Guard']; 
            }else if($order->verify_status_guard != '1'){
                return ['status' => 'fail', 'message' => 'Customer Previous Order Verification is pending from Guard' ] ; 
            }
        }


        // Checking any Previous return is in process or not
        // $order = Invoice::where('store_id', $store_id)->where('v_id' , $v_id)->where('user_id', $c_id)->where('invoice', $invoice_id)->first();

        // $carts = OrderDetails::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order->o_id)->where('user_id', $c_id)->get();

        $cart_ids = $invoice->details->pluck('id')->all();

        $ret_req = DB::table('return_request')->where('order_id', $invoice->ref_order_id)->where('confirm','1')->get();

        $cnt = $ret_req->where('status','process')->count();

        if($cnt > 0){
            return ['status' => 'fail', 'message' => 'Your previous return is in progress' ];    
        }

        $param =[];
        $params= [];
        foreach($invoice->details as $cart){
    
            $loopQty = $cart->qty;
            while($loopQty > 0){
               $param[] = $cart->total / $cart->qty; 
               $params[] = ['item_id' => $cart->item_id , 'price' => $cart->total / $cart->qty, 'r_price'=> $cart->subtotal / $cart->qty , 'discount' => $cart->discount / $cart->qty , 'tax' => $cart->tax / $cart->qty  , 'p_name' =>$cart->item_name , 'unit_mrp' => $cart->unit_mrp  , 'cart_id' => $cart->id  , 'bill_discount' => 0, 'manual_discount' => $cart->manual_discount / $cart->qty, 'lpdiscount' => $cart->lpdiscount / $cart->qty, 'coupon_discount' => $cart->coupon_discount / $cart->qty ];

               $loopQty--;
            }

        }

        // dd($params);
   
        ######################################
        ##### --- BILL BUSTER  START --- #####
        $bill_buster_discount = 0;
        $discount = 0;
        $total_discount = 0;
        if($order && $order->bill_buster_discount > 0){
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
        
        foreach($params as $key => $val){
            $params[$key]['price'] -= $params[$key]['bill_discount'];
            $item_id = $val['item_id'];
            if( isset($return_items[$item_id]) ){
                if($return_items[$item_id]['request_qty'] > $return_items[$item_id]['qty']){
                    $return_items[$item_id]['s_price'] += $params[$key]['price'];
                    $return_items[$item_id]['r_price'] += $params[$key]['r_price'];
                    $return_items[$item_id]['discount'] += $params[$key]['discount'];
                    $return_items[$item_id]['manual_discount'] += $params[$key]['manual_discount'];
                    $return_items[$item_id]['coupon_discount'] += $params[$key]['coupon_discount'];
                    $return_items[$item_id]['lpdiscount'] += $params[$key]['lpdiscount'];
                    $return_items[$item_id]['tax'] += $params[$key]['tax'];
                    $return_items[$item_id]['bill_discount'] += $params[$key]['bill_discount'];
                    $return_items[$item_id]['unit_mrp'] = $params[$key]['unit_mrp'];
                    $return_items[$item_id]['p_name'] = $params[$key]['p_name'];
                    $return_items[$item_id]['cart_id'] = $params[$key]['cart_id'];
                    $return_items[$item_id]['p_id'] = (int) $params[$key]['item_id'];
                    $return_items[$item_id]['qty'] += 1;
                }
            }
        }
        
        foreach($return_items as $key => $items) {

            $return_items[$key]['s_price'] = (string)$return_items[$key]['s_price'];
            $return_items[$key]['r_price'] = (string)$return_items[$key]['r_price'];
            $return_items[$key]['discount'] = (string)$return_items[$key]['discount'];
            $return_items[$key]['manual_discount'] = (string)$return_items[$key]['manual_discount'];
            $return_items[$key]['coupon_discount'] = (string)$return_items[$key]['coupon_discount'];
            $return_items[$key]['lpdiscount'] = (string)$return_items[$key]['lpdiscount'];
            $return_items[$key]['tax'] = (string)$return_items[$key]['tax'];
            $return_items[$key]['bill_discount'] = (string)$return_items[$key]['bill_discount'];
            $return_items[$key]['unit_mrp'] = (string)@$return_items[$key]['unit_mrp'];
            $return_items[$key]['qty'] = (string)$return_items[$key]['qty'];
        }
       
        $return_items = array_values($return_items);

        return [ 'status' => 'success', 'returns' => $return_items , 'order' => $invoice ,'return_item' => $return_item , 'ret_req' => $ret_req ];
    }

    public function return_request(Request $request)
    {
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
        // dd($order->user->mobile);
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
            //dd($cart);
            $reson_id = 0;
            if(isset($items['reason_id'])){
                $reson_id   = $items['reason_id'];
            }

            DB::table('return_request')->insert(
                [ 
                'confirm' => $confirm,
                'qty' => $items['qty'] , 
                'reason_id' => $reson_id,
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
                'year' => date('Y')
            ]
            );

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
            $product_data['r_price'] = format_number($items['r_price']);
            $product_data['s_price'] = format_number($items['s_price']);
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
                    'amount'        => $items['s_price'] ,
                    'qty'           => $items['qty'],
                    'return_product_qty' => (string) $return_product_qty, //MAx quantity available fo retrun
                    'tax_amount'    => $tax_amount,
                    'delivery'      => $cart->delivery,
                    'item_flag'     => 'NORMAL'
            );

            $sub_total += $items['s_price'];
            $discount += $items['discount'] + $items['lpdiscount'] + $items['manual_discount'] + $items['coupon_discount']+ $items['bill_discount'];
            $total += $items['s_price'];
            $tax_total += $items['tax'];
            $cart_qty_total += $items['qty'];
            ######## This code is to Generate Same response as order Details  END  ######
            #############################################################################

        }
        
        //dd($params);

        return response()->json(['status' => 'order_details', 'message' => 'Your Cart Details', 
            'data' => $cart_data,
            
            //'sub_total' => (format_number($sub_total)) ? format_number($sub_total) : '0.00',
            //'tax_total' => (format_number($tax_total)) ? format_number($tax_total) : '0.00',
            //'tax_details' => $tax_details,
            //'bill_buster_discount' => (format_number($bill_buster_discount)) ? format_number($bill_buster_discount) : '0.00',
            'discount' => (format_number($discount)) ? format_number($discount) : '0.00',
            //'grand_total' => (format_number($grand_total))?format_number($grand_total):'0.00',
            'order_id' => $order_id,
            'total' => format_number($total),
            'cart_qty_total' => (string) $cart_qty_total,
            'mobile' => $order->user->mobile,
            'customer_name' => $order->user->first_name.' '.$order->user->last_name, 
            'payment_method' => 'Voucher',
            //'saving' => (format_number($saving)) ? format_number($saving) : '0.00'
             ]);        
    }


    public function approve(Request $request)
    {
        $vu_id = $request->vu_id;
        $v_id = $request->v_id;
        $c_id = $request->customer_id;
        $store_id = $request->store_id;
        $order_id = $request->order_id;
        $remark='';
        
        if($request->has('remark')){
            $remark = $request->remark;
        }
        
        $admin_id = $request->admin_id;
        $status = 'approved';
        $trans_from = $request->trans_from;

        $t_order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $t_order_id = $t_order_id + 1;

        $subtotal = 0;
        $discount = 0;
        $lpdiscount = 0;
        $coupon_discount = 0;
        $manual_discount = 0;
        $bill_buster_discount = 0;
        $tax = 0;
        $total = 0;
        $qty = 0;

        $employee_id = 0;
        $employee_discount =0;
        $employee_available_discount = 0;

        $request->request->add(['confirm' => 0]);
        $response = $this->get_return_request($request);
        if($response['status'] == 'fail'){
            return response()->json( $response, 200);
        }
        $return_items = $response['returns'];
        $invoice_seq = null;
        if($request->has('invoice_seq')){
         $invoice_seq =   $request->invoice_seq;
        }
        $udidtoken = '';
        if ($request->has('udidtoken')) {
            $udidtoken    = $request->udidtoken;
            $terminalInfo = CashRegister::where('udidtoken',$udidtoken)->first(); 
        }
        $current_date = date('Y-m-d'); 
        $settlementSession = SettlementSession::select('id')->where(['v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id ,'trans_from' => $trans_from, 'settlement_date' => $current_date ])->orderBy('opening_time','desc')->first();
        if($settlementSession){
            $session_id = $settlementSession->id;
        }
        $userDetail   = User::find($c_id);


        //dd($return_items);
        DB::beginTransaction();
      
        try {

            foreach($return_items as $item ) {

                $subtotal += $item['r_price'];
                $discount += $item['discount'];
                $lpdiscount += $item['lpdiscount'];
                $manual_discount += $item['manual_discount'];
                $coupon_discount += $item['coupon_discount'];
                $bill_buster_discount += $item['bill_discount'];
                $total += $item['s_price'];
                $tax += $item['tax'];
                $qty += $item['qty'];
               
                // Tax Calculation for item tData

                $ex_cart = InvoiceDetails::where('id', $item['cart_id'])->first();
                // $tax_data = json_decode($ex_cart->tdata,true);
                 //$sprice = $item['s_price']/$item['qty'];
                $cartC = new CartController;
                $data = json_decode($ex_cart->section_target_offers);
              // dd(urldecode($data->pdata));
                $invhsn = urldecode($data->item_det);
                $inv = json_decode($invhsn);
                $pdata   =json_decode($ex_cart->pdata);
                $rpdata =(array_slice($pdata, 0, $item['qty'], true));
                //dd($qty);
                if(empty($inv->BARCODE)){
                  $barcode = $inv->ICODE; 
                }else{
                   $barcode = $inv->BARCODE;   
                }

                $taxParams = array(
                    'barcode' => $barcode,
                    'qty'  => $item['qty'],
                    's_price' => $item['s_price'],
                    'tax_code'=> $inv->INVHSNSACMAIN_CODE,
                    'store_id'=> $request->store_id
                );
                $taXcals = $cartC->taxCal($taxParams);
                $section_target_offers = array(
                 'p_id'=>$data->p_id,
                 'category'=>$data->category,
                 'brand_name'=>$data->brand_name,
                 'sub_categroy'=>$data->sub_categroy,
                 'p_name'=>$data->p_name,
                 'offer'=> $data->offer,
                'offer_data'=>$data->offer_data,
                 'qty'=>$item['qty'], 
                  'multiple_price_flag'=>$data->multiple_price_flag ,
                  'multiple_mrp'=>$data->multiple_mrp,
                  'unit_mrp'=> $data->unit_mrp,
                  'unit_rsp'=> $data->unit_rsp,
                  'r_price'=> $item['r_price'],
                  's_price'=> $item['s_price'],
                  'discount'=> $item['discount'],
                  'varient'=> $data->varient,
                  'images'=> $data->images,
                  'description'=> $data->description,
                  'deparment'=> $data->deparment,
                  'barcode'=>  $data->barcode,
                  'pdata' =>urlencode(json_encode($rpdata)),
                  'tdata' =>json_encode($taXcals),
                  'review'=>$data->review,
                  'item_det'=>$data->item_det,
                  'whishlist'=>$data->whishlist,

                );

                  

                   
           //dd($section_target_offers);
                  
            //exit();    // Insert Order Details data
                 
                $createOrderDetails = OrderDetails::create(
                    [ 
                    'transaction_type' => 'return',
                    'store_id' => $ex_cart->store_id,
                    'v_id' => $ex_cart->v_id,
                    'order_id' => $t_order_id,
                    'user_id' => $ex_cart->user_id,
                    'plu_barcode' => $ex_cart->plu_barcode,
                    'item_name' => $ex_cart->item_name,
                    'weight_flag' => $ex_cart->weight_flag,
                    'barcode' => $ex_cart->barcode,
                    'qty' => $item['qty'],
                    'unit_mrp' => $ex_cart->unit_mrp,
                    'unit_csp' => $ex_cart->unit_csp,
                    'subtotal' => $item['r_price'],
                    'total' => $item['s_price'],
                    'discount' => $item['discount'],
                    'lpdiscount' => $item['lpdiscount'],
                    'manual_discount' => $item['manual_discount'],
                    'coupon_discount' => $item['coupon_discount'],
                    'bill_buster_discount' => $item['bill_discount'],
                    'tax' => $taXcals['tax'],
                    'status' => 'success',
                    'trans_from' => $request->trans_from,
                    'vu_id' => $request->vu_id,
                    'date' => date('Y-m-d'),
                    'time' => date('h:i:s'),
                    'month' => date('m'),
                    'year' => date('Y'),
                    'target_offer' => $ex_cart->target_offer,
                    'slab' => $ex_cart->slab,
                    'section_target_offers' =>json_encode($section_target_offers),
                    'section_offers' => $ex_cart->section_offers,
                    'item_id' => $ex_cart->item_id,
                    'department_id' => $ex_cart->department_id,
                    'subclass_id' => $ex_cart->subclass_id,
                    'printclass_id' => $ex_cart->printclass_id,
                    'group_id' => $ex_cart->group_id,
                    'division_id' => $ex_cart->division_id,
                    'pdata'   => json_encode($rpdata),
                    'tdata'   => json_encode($taXcals),
                    'reason_id' => isset($item['reason_id'])?$item['reason_id']:0
                       ]
                );

                $orderDetails = OrderDetails::find($createOrderDetails->id)->toArray();

                // Insert Order Details data in Invoice Details

                InvoiceDetails::create($orderDetails);

                // Tax Calculation for item tData

                $ex_cart = InvoiceDetails::where('id', $item['cart_id'])->first();
                 //$sprice = $item['s_price']/$item['qty'];
                $cartC = new CartController;
                $data = json_decode($ex_cart->section_target_offers);
                $invhsn = urldecode($data->item_det);
                $inv = json_decode($invhsn);
                   

                if(empty($inv->BARCODE)){
                  $barcode = $inv->ICODE; 
                }else{
                   $barcode = $inv->BARCODE;   
                }
                $taxParams = array(
                    'barcode' => $barcode,
                    'qty'  => $item['qty'],
                    's_price' => $item['s_price'],
                    'tax_code'=> $inv->INVHSNSACMAIN_CODE,
                    'store_id'=> $request->store_id
                );
                $taXcals = $cartC->taxCal($taxParams);
                // dd($taXcals);


                //DB::table('return_request')->where('id', $item->id)->update(['status' => 'approved']);

            }

            $last_transaction_no = 0;
            $last_order = Order::where('v_id',$v_id)->where('store_id', $store_id)->where('status','success')->orderBy('od_id','desc')->first();
            $last_transaction_no = $last_order->transaction_no;

            $r_order_id = order_id_generate($store_id, $c_id , $trans_from);
            $custom_order_id = custom_order_id_generate(['store_id' => $store_id, 'user_id' => $c_id, 'trans_from' => $trans_from]);

            //This condition is added when return was done by Invoice ID $order_id variable get updated here

            $invoice = Invoice::where('invoice_id', $order_id)->first();
            $order_id = $invoice->ref_order_id;
            $store_data = Store::find($store_id);

            $order = new Order;
            $order->transaction_type = 'return';
            $order->transaction_sub_type = 'return';
            $order->store_gstin   =     $store_data->gst;
            $order->store_gstin_state_id = $store_data->state_id;
            $order->order_id = $r_order_id;
            $order->custom_order_id = $custom_order_id;
            $order->ref_order_id = $order_id;
            $order->o_id = $t_order_id;
            $order->v_id = $v_id;
            $order->vu_id = $vu_id;
            $order->store_id = $store_id;
            $order->qty = $qty;
            $order->user_id = $c_id;
            $order->subtotal = $subtotal;
            $order->discount = $discount;
            $order->lpdiscount = $lpdiscount;
            $order->manual_discount = $manual_discount;
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
            $order->tax = $tax;
            $order->total = $total;
            $order->transaction_no = $last_transaction_no;
            $order->return_by = 'voucher';
            $order->status = 'success';
            $order->date = date('Y-m-d');
            $order->trans_from = $trans_from;
            $order->remark = $remark;
            $order->time = date('h:i:s');
            $order->month = date('m');
            $order->year = date('Y');

            $order->save();

            DB::table('order_details')->where('store_id',$store_id)->where('v_id',$v_id)->where('user_id',$c_id)->where('order_id', $t_order_id)->where('transaction_type','return')->update( ['t_order_id' => $order->od_id]);

            $zwing_invoice_id = invoice_id_generate($store_id, $c_id, $trans_from, $invoice_seq, $udidtoken);
            $inc_id  = invoice_id_generate($store_id, $c_id, $trans_from,$invoice_seq,$udidtoken,'seq_id');
            
            $custom_invoice_id = custom_invoice_id_generate(['store_id' => $store_id, 'user_id' => $c_id, 'trans_from' => $trans_from]);
            $invoice = new Invoice;
            $invoice->invoice_id = $zwing_invoice_id;
            $invoice->custom_order_id = $custom_invoice_id;
            $invoice->ref_order_id = $order->order_id;
            $invoice->transaction_type = $order->transaction_type;
            $invoice->transaction_sub_type = $order->transaction_sub_type;
            $invoice->store_gstin   =     $order->store_gstin;
            $invoice->store_gstin_state_id  = $order->store_gstin_state_id;
            $invoice->v_id = $v_id;
            $invoice->store_id = $store_id;
            $invoice->user_id = $c_id;
            $invoice->subtotal = $order->subtotal;
            $invoice->discount = $order->discount;
            $invoice->lpdiscount = $order->lpdiscount;
            $invoice->manual_discount = $order->manual_discount;
            $invoice->coupon_discount = $order->coupon_discount;
            $invoice->store_short_code  = $store_data->short_code;
            $invoice->session_id        = $session_id;
            $invoice->qty = $order->qty;
            $invoice->invoice_sequence  = $inc_id;
            $invoice->tax = $order->tax;
            $invoice->total = $order->total;
            $invoice->trans_from = $trans_from;
            $invoice->vu_id = $vu_id;
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
            $invoice->save();


            DB::table('invoice_details')->where('store_id',$store_id)->where('v_id',$v_id)->where('user_id',$c_id)->where('order_id', $t_order_id)->where('transaction_type','return')->update( ['t_order_id' => $invoice->id]);

            $voucher_no = generateRandomString(6);

            $today_date = date('Y-m-d H:i:s');
            $next_date =  date('Y-m-d H:i:s' ,strtotime('+30 days', strtotime($today_date)) );

            DB::table('cr_dr_voucher')->insert(['v_id'=> $v_id , 'store_id' => $store_id ,  'user_id'=> $c_id , 'amount' => $total , 'ref_id' => $r_order_id , 'status' => 'unused' ,'type' => 'voucher_credit' , 'voucher_no' => $voucher_no, 'effective_at' => $today_date   , 'expired_at' => $next_date ]);

            $payment = new Payment;
            $payment->v_id = $v_id;
            $payment->store_id = $store_id;
            $payment->user_id = $c_id;
            $payment->order_id = $r_order_id;
            $payment->invoice_id = $invoice->invoice_id;
            //$payment->t_order_id = 0;
            $payment->pay_id = $voucher_no;
            $payment->amount = $total;
            $payment->method = 'voucher_credit';
            
            $payment->status = 'success';
            $payment->date = date('Y-m-d');
            $payment->time = date('h:i:s');
            $payment->month = date('m');
            $payment->year = date('Y');
            $payment->save();

        DB::commit();
        } catch(Exception $e) {
          DB::rollback();
          exit;
        }

        $cust = DB::table('customer_auth')->select('mobile','first_name','last_name','email')->where('c_id', $c_id)->first();
        /*sending sms via common sms controller*/
        $mobile = $cust->mobile;
        $customer_name = $cust->first_name.' '.$cust->last_name;
        $customer_email= $cust->email;
        $dates = explode(' ',$next_date);

        $smsC = new SmsController;
        $smsC->send_voucher(['mobile' => $mobile , 'voucher_amount' => $total, 'voucher_no' => $voucher_no , 'expiry_date' => $dates[0] , 'v_id' => $v_id, 'store_id' =>  $store_id , 'store_name' => $store_data->name ]);

        $orderC = new OrderController;
        $order_arr = $orderC->getOrderResponse(['order' => $order , 'v_id' => $v_id , 'trans_from' => $trans_from ]) ;

        // Loyality

        if($request->has('loyalty')) {
            $checkLoyaltyBillSubmit = LoyaltyBill::where('vendor_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('invoice_no', $zwing_invoice_id)->where('type', 'easeMyRetail')->where('is_submitted', '1')->first();
            if (empty($checkLoyaltyBillSubmit)) {
                $userInformation = User::find($c_id);
                // $invoice_id = Invoice::where('ref_order_id', $order_id)->first()->invoice_id;
                $loyaltyPrams = [ 'type' => $request->loyaltyType, 'event' => 'billPush', 'mobile' => $userInformation->mobile, 'vu_id' => $request->vu_id, 'settings' => $request->loyaltySettings, 'zw_event' => 'checkBill', 'v_id' => $v_id, 'store_id' => $store_id, 'invoice_id' => $zwing_invoice_id, 'user_id' => $c_id ];
                // Event::fire(new Loyalty($loyaltyPrams));
                event(new Loyalty($loyaltyPrams));
            }
            // dd($loyaltyPrams);
        }

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
            $html = $htmlData->getContent();
            $html_obj_data = json_decode($html);
            if($html_obj_data->status == 'success')
            {
                $invoice->html_data =  $gcartC->get_html_structure($html_obj_data->print_data);
            }
        }
       
      // return response()->json(['status' => 'success', 'message' => 'Retrun of items has been approved successfully' , 'data' => [ 'order_id' => $invoice->invoice_id ], 'order_id' => $invoice->invoice_id ]);
        return response()->json(['status' => 'success', 'message' => 'Retrun of items has been approved successfully' , 'data' => $invoice, 'order_id' => $invoice->invoice_id,'invoice_id' => $invoice->invoice_id,'refund_mode'=> 'Store Credit','return_remark'=>'','customer_name'=>$customer_name,'customer_email'=>$customer_email,'customer_mobile'=>$mobile,'account_balance'=>'','store_credit'=>'','loyalty_points'=>'','order_summary' => $order_arr,'' ]);

    }

    public function update(Request $request){
        $v_id = $request->v_id;
        $c_id = $request->customer_id;
        $store_id = $request->store_id;
        $order_id = $request->order_id;
        //$return_item = json_decode($request->return_item);

        DB::table('return_request')->where('order_id', $order_id)->where('status','process')->delete();

        return $this->return_request($request);


    }

    public function delete(Request $request){
        $v_id = $request->v_id;
        $c_id = $request->customer_id;
        $store_id = $request->store_id;
        $order_id = $request->order_id;

        DB::table('return_request')->where('order_id', $order_id)->where('status','process')->delete();

        return response()->json(['status' => 'success', 'message' => 'Data deleted successfully' ]);

    }

}
