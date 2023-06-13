<?php

namespace App\Http\Controllers\Spar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Mail\OrderCreated;
use Illuminate\Support\Facades\Mail;
use PDF;
use App\Store;
use App\Order;
use App\Cart;
use App\User;
use DB;
use Auth;
use App\Http\Controllers\ProfileController;
use App\Vendor;
use App\Payment;
use App\Invoice;
use App\InvoiceDetails;
use App\OrderDetails;
use App\OrderItemDetails;

class ReturnController extends Controller
{


    public function __construct()
	{
		$this->middleware('auth');
	}

	public function get_return_item(Request $request){
		  
		$v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;
        $order_id = $request->order_id;

        $data = [] ;
        $order = Order::where('store_id', $store_id)->where('v_id' , $v_id)->where('user_id', $c_id)->where('order_id', $order_id)->first();
        $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order->o_id)->where('user_id', $c_id)->get();

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

    public function get_order(Request $request){
        $v_id = $request->v_id;
        //$c_id = $request->c_id;
        $store_id = $request->store_id;
        $cust_order = $request->cust_order;
        $trans_from = $request->trans_from;

        if(is_numeric($cust_order)){
            
            $profileC = new ProfileController;
            $cust = User::where('mobile' ,$cust_order)->first();
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

            $order = Order::where('order_id', $cust_order)->where('status' , 'success')->first();
            if($order){

                $request->request->add();
                //return $cartC->order_details($request);
                $cust = User::where('c_id' ,$order->user_id)->first();
                return response()->json(['status' => 'success' , 'go_to' => 'order_details' , 'data' => [ 'v_id' => $order->v_id , 'store_id' => $order->store_id , 'c_id' => $order->user_id, 'api_token' => $cust->api_token, 'order_id' => $cust_order, 'trans_from' => $trans_from ]
                 ]);
                
            }else{

                return response()->json(['status' => 'fail', 'message' => 'Unable to find the Order' ]);
            }

        }

    }

	public function get_return_request(Request $request){
        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;
        $order_id = $request->order_id;
        $confirm = $request->confirm;
        $return_item = json_decode($request->return_item);

        //dd($return_item);
        //dd($request->return_item);
        $return_items = [];
        foreach($return_item as $item){
            $item_id = $item->item_id;
            $return_items[$item_id]['request_qty'] = $item->qty;
            $return_items[$item_id]['qty'] = 0;
            $return_items[$item_id]['discount'] = 0;
            $return_items[$item_id]['tax'] = 0;
            $return_items[$item_id]['bill_discount'] = 0;
            $return_items[$item_id]['s_price'] = 0;
            $return_items[$item_id]['r_price'] = 0;
            $return_items[$item_id]['p_name'] = 0;
        }
        $return_item_data = [];
        $order = Order::select('order_id', 'v_id' , 'store_id','date','time','total','verify_status','verify_status_guard')->where('user_id',$c_id)->where('v_id' , $v_id)->where('status','success')->where('transaction_type','sales')->orderBy('od_id','desc')->first();
        if($order){
            if($order->verify_status != '1'){
               return  ['status' => 'fail', 'message' => 'Customer Previous Order Verification is pending from Cashier and Guard']; 
            }else if($order->verify_status_guard != '1'){
                return ['status' => 'fail', 'message' => 'Customer Previous Order Verification is pending from Guard' ] ; 
            }
        }

        $order = Order::where('store_id', $store_id)->where('v_id' , $v_id)->where('user_id', $c_id)->where('order_id', $order_id)->first();
        //$carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order->o_id)->where('user_id', $c_id)->get();

        $carts = DB::table('order_details')->where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order->o_id)->where('user_id', $c_id)->get();
        $cart_ids = $carts->pluck('id')->all();
        //dd($cart_ids);

        $ret_req = DB::table('return_request')->where('order_id', $order_id)->where('confirm','1')->get();

        $cnt = $ret_req->where('status','process')->count();

        if($cnt > 0){
            return ['status' => 'fail', 'message' => 'Your previous return is in progress' ];    
        }

        $param =[];
        $params= [];
        foreach($carts as $cart){
            $loopQty = $cart->qty;
            while($loopQty > 0){
               $param[] = $cart->total / $cart->qty; 
               $params[] = ['item_id' => $cart->barcode , 'price' => $cart->total / $cart->qty, 'r_price'=> $cart->subtotal / $cart->qty , 'discount' => $cart->discount / $cart->qty , 'tax' => $cart->tax / $cart->qty  , 'p_name' =>$cart->item_name , 'unit_mrp' => $cart->unit_mrp  , 'cart_id' => $cart->t_order_id  , 'bill_discount' => 0];

               /*if()
               $return_item_data[] = ['p_id' => $cart->item_id, 'p_name' => $cart->item_name , 'qty' => $cart->qty, 'r_price' => $cart->subtotal  / $cart->qty , 's_price' => $cart->total / $cart->qty ];*/

               $loopQty--;
            }
            

        }

        // dd($params);
   
        ######################################
        ##### --- BILL BUSTER  START --- #####
        $bill_buster_discount = 0;
        $discount = 0;
        $total_discount = 0;
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
            $return_items[$key]['tax'] = (string)$return_items[$key]['tax'];
            $return_items[$key]['bill_discount'] = (string)$return_items[$key]['bill_discount'];
            $return_items[$key]['unit_mrp'] = (string)@$return_items[$key]['unit_mrp'];
            $return_items[$key]['qty'] = (string)$return_items[$key]['qty'];
        }
        $return_items = array_values($return_items);
        return [ 'status' => 'success', 'returns' => $return_items , 'order' => $order ,'return_item' => $return_item , 'ret_req' => $ret_req];
    }

    public function return_request(Request $request){
        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;
        $order_id = $request->order_id;
        $confirm = $request->confirm;
        $response = $this->get_return_request($request);
        if($response['status'] == 'fail'){
            return response()->json( $response, 200);
        }
        $return_items = $response['returns'];
        //dd($response);
        $ret_req = $response['ret_req'];
        $order = $response['order'];
        $sub_total = 0;;
        $discount = 0;
        $total = 0;
        $tax_total = 0;
        $cart_qty_total = 0;
        Cart::where('user_id', $c_id)->delete();
        foreach($return_items as $items ){
            $cart = DB::table('order_details')->where('t_order_id', $response['order']['od_id'])->first();
            DB::table('return_request')->insert(
                [ 
                'confirm' => $confirm,
                'qty' => $items['qty'] , 
                'subtotal' => $items['s_price'] , 
                'discount' => $items['discount'], 
                'bill_buster_discount' => $items['bill_discount'], 
                'tax' => $items['tax'], 
                'total' => $items['r_price'],
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
                
                'transaction_type' => 'return'
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

            //$tax_total = $tax_total +  $tax_amount ;
            $tax_amount = $items['tax'];
            //$cart_qty_total =  $cart_qty_total + $items['qty'];
            $approved_qty = $ret_req->where('status','approved')->where('item_id',$cart->item_id)->sum('qty');
            // dd($approved_qty);
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
            $discount += $items['discount'];
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
            'payment_method' => 'Voucher',
            //'saving' => (format_number($saving)) ? format_number($saving) : '0.00'
             ]);        

    }


    public function approve(Request $request){
        $vu_id = $request->vu_id;
        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;
        $order_id = $request->order_id;
        $admin_id = $request->admin_id;
        $status = 'approved';
        $trans_from = $request->trans_from;

        $t_order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $t_order_id = $t_order_id + 1;

        $subtotal = 0;
        $discount = 0;
        $bill_buster_discount = 0;
        $tax = 0;
        $total = 0;

        $employee_id = 0;
        $employee_discount =0;
        $employee_available_discount = 0;

        $request->request->add(['confirm' => 0]);
        $response = $this->get_return_request($request);
        if($response['status'] == 'fail'){
            return response()->json( $response, 200);
        }
        $return_items = $response['returns'];
        foreach($return_items as $item ){
            $subtotal += $item['r_price'];
            $discount += $item['discount'];
            $bill_buster_discount += $item['bill_discount'];
            $total += $item['s_price'];
            $tax += $item['tax'];

            $ex_cart = DB::table('order_details')->where('t_order_id', $item['cart_id'])->first();
            DB::table('order_details')->insert(
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
                'subtotal' => $item['r_price'],
                'total' => $item['s_price'],
                'discount' => $item['discount'],
                'bill_buster_discount' => $item['bill_discount'],
                'tax' => $item['tax'],
                'status' => 'success',
                //'date' = date('Y-m-d');
                //'time' = date('h:i:s');
                //'month' = date('m');
                //'year' = date('Y');

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
                'tdata'   => $ex_cart->tdata
                   ]
            );


            InvoiceDetails::insert(
                [ 
                'transaction_type' => 'return',
                'store_id' => $ex_cart->store_id,
                'v_id' => $ex_cart->v_id,
                'order_id' => $t_order_id,
                'user_id' => $ex_cart->user_id,
                'item_name' => $ex_cart->item_name,
                'weight_flag' => $ex_cart->weight_flag,
                'barcode' => $ex_cart->barcode,
                'qty' => $item['qty'],
                'unit_mrp' => $ex_cart->unit_mrp,
                'subtotal' => $item['r_price'],
                'total' => $item['s_price'],
                'discount' => $item['discount'],
                'bill_buster_discount' => $item['bill_discount'],
                'tax' => $item['tax'],
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
                'tdata'   => $ex_cart->tdata,
                'pdata'   => $ex_cart->pdata
                   ]
            );

            //DB::table('return_request')->where('id', $item->id)->update(['status' => 'approved']);

        }

        $last_transaction_no = 0;
        $last_order = Order::where('v_id',$v_id)->where('store_id', $store_id)->where('status','success')->orderBy('od_id','desc')->first();
        $last_transaction_no = $last_order->transaction_no;

        $r_order_id = order_id_generate($store_id, $c_id , $trans_from);

        $order = new Order;

        $order->transaction_type = 'return';
        $order->order_id = $r_order_id;
        $order->ref_order_id = $order_id;
        $order->o_id = $t_order_id;
        $order->v_id = $v_id;
        $order->vu_id = $vu_id;
        $order->store_id = $store_id;
        $order->user_id = $c_id;
        $order->subtotal = $subtotal;
        $order->discount = $discount;
        if ($v_id == 4) {
            $order->employee_id = $employee_id;
            $order->employee_discount = $employee_discount;
            $order->employee_available_discount = $employee_available_discount;
            $order->bill_buster_discount = $bill_buster_discount;
        }
        if ($trans_from == 'ANDROID_VENDOR') {
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
       // $order->time = date('h:i:s');
        //$order->month = date('m');
       // $order->year = date('Y');

        $order->save();

        DB::table('order_details')->where('store_id',$store_id)->where('v_id',$v_id)->where('user_id',$c_id)->where('order_id', $t_order_id)->where('transaction_type','return')->update( ['t_order_id' => $order->od_id]);

        $zwing_invoice_id = invoice_id_generate($store_id, $c_id, $trans_from);
        $custom_invoice_id = custom_invoice_id_generate(['store_id' => $store_id, 'user_id' => $c_id, 'trans_from' => $trans_from]);
        $invoice = new Invoice;
        $invoice->invoice_id = $zwing_invoice_id;
        $invoice->custom_order_id = $custom_invoice_id;
        $invoice->ref_order_id = $order->order_id;
        $invoice->transaction_type = $order->transaction_type;
        $invoice->v_id = $v_id;
        $invoice->store_id = $store_id;
        $invoice->user_id = $c_id;
        $invoice->subtotal = $order->subtotal;
        $invoice->discount = $order->discount;
        $invoice->tax = $order->tax;
        $invoice->total = $order->total;
        $invoice->trans_from = $trans_from;
        $invoice->vu_id = $vu_id;
        $invoice->date = date('Y-m-d');
        $invoice->time = date('H:i:s');
        $invoice->month = date('m');
        $invoice->year = date('Y');
        $invoice->save();


        DB::table('invoice_details')->where('store_id',$store_id)->where('v_id',$v_id)->where('user_id',$c_id)->where('order_id', $t_order_id)->where('transaction_type','return')->update( ['t_order_id' => $invoice->id]);

        $voucher_no = generateRandomString(6);

        $today_date = date('Y-m-d H:i:s');
        $next_date =  date('Y-m-d H:i:s' ,strtotime('+30 days', strtotime($today_date)) );

        DB::table('voucher')->insert(['v_id'=> $v_id , 'store_id' => $store_id ,  'user_id'=> $c_id , 'amount' => $total , 'ref_id' => $r_order_id , 'status' => 'unused' ,'type' => 'voucher_credit' , 'voucher_no' => $voucher_no, 'effective_at' => $today_date   , 'expired_at' => $next_date ]);

        $payment = new Payment;
        $payment->v_id = $v_id;
        $payment->store_id = $store_id;
        $payment->user_id = $c_id;
        $payment->order_id = $r_order_id;
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

        $cust = DB::table('customer_auth')->select('mobile')->where('c_id', $c_id)->first();
        $username = "roxfortgroup@gmail.com";
        $hash = "bb7a4a8a91634f50df712fdf5c11ef6745ea735b85afc9443d23ec9ae3e8b711";
        $test = "0";
        $sender = "MZWING";
        $mobile = $cust->mobile;
       
        //$otp = rand(1111,9999);
        //$user_otp_update = User::where('mobile', $mobile)->where('mobile_active', 1)->update(['otp' => $otp]);
        $numbers = "91".$mobile; 
        //$message = "You have received a Credit Note of Rs ".$total.". Your code is ".$voucher_no." Expire at ".$next_date." . Please note this is one time use only";
        $dates = explode(' ',$next_date);
        $message = "You have received a voucher of Rs ".format_number($total).". Your code is ".$voucher_no." Expire at ".$dates[0].". one time use only";
        
        //$message = "You have received a Voucher of Rs 2000. Your code is 54ogpg Expire at 2018-12-12. Please note this is one time use only";
        $message = urlencode($message);
        $data = "username=".$username."&hash=".$hash."&message=".$message."&sender=".$sender."&numbers=".$numbers."&test=".$test;
        $ch = curl_init('http://api.textlocal.in/send/?');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch); 
        //dd($result);
        curl_close($ch);


        return response()->json(['status' => 'success', 'message' => 'Retrun of items has been approved successfully' , 'data' => [ 'order_id' => $r_order_id ], 'order_id' => $r_order_id ]);

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

}
