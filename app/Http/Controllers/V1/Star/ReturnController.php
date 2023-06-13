<?php

namespace App\Http\Controllers\V1\Star;

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

	public function return_request(Request $request){
		
		$v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;
        $order_id = $request->order_id;
        $confirm = $request->confirm;
        $return_item = json_decode($request->return_item);

        //dd($return_item);
        $return_items = [];
        foreach($return_item as $item){
        	$item_id = $item[0];
        	$return_items[$item_id]['request_qty'] = $item[1];
        	$return_items[$item_id]['qty'] = 0;
        	$return_items[$item_id]['discount'] = 0;
        	$return_items[$item_id]['tax'] = 0;
        	$return_items[$item_id]['bill_discount'] = 0;
        	$return_items[$item_id]['s_price'] = 0;
        	$return_items[$item_id]['r_price'] = 0;
        	$return_items[$item_id]['p_name'] = 0;
        }
        $return_item_data = [];

		$order = Order::where('store_id', $store_id)->where('v_id' , $v_id)->where('user_id', $c_id)->where('order_id', $order_id)->first();
        $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order->o_id)->where('user_id', $c_id)->get();
        $cart_ids = $carts->pluck('cart_id')->all();
        //dd($cart_ids);

        $ret_req = DB::table('return_request')->where('order_id', $order_id)->where('confirm','1')->get();

        $cnt = $ret_req->where('status','process')->count();

        if($cnt > 0){
        	return response()->json(['status' => 'fail', 'message' => 'Your previous return is in progress' ]);    
        }

        $param =[];
        $params= [];
        foreach($carts as $cart){
    
        	$loopQty = $cart->qty;
            while($loopQty > 0){
               $param[] = $cart->total / $cart->qty; 
               $params[] = ['item_id' => $cart->item_id , 'price' => $cart->total / $cart->qty, 'r_price'=> $cart->subtotal / $cart->qty , 'discount' => $cart->discount / $cart->qty , 'tax' => $cart->tax / $cart->qty  , 'p_name' =>$cart->item_name , 'unit_mrp' => $cart->unit_mrp  , 'cart_id' => $cart->cart_id  , 'bill_discount' => 0];

               /*if()
               $return_item_data[] = ['p_id' => $cart->item_id, 'p_name' => $cart->item_name , 'qty' => $cart->qty, 'r_price' => $cart->subtotal  / $cart->qty , 's_price' => $cart->total / $cart->qty ];*/

               $loopQty--;
            }


            $res = DB::table('cart_offers')->where('cart_id',$cart->cart_id)->first();
            $offer_data = json_decode($res->offers, true);
           
            foreach ($offer_data['pdata'] as $key => $value) {
                $tax_details_data[$cart->item_id] = ['tax' =>  $value['tax'] , 'total' => $value['ex_price'] ];
            }

        }

		//dd($params);
   
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
        //dd($return_items);
        foreach($return_items as $key => $items){

        	foreach($tax_details_data[$key]['tax'] as $nkey => $tax){
                $tax_category = $tax['tax_category'];
                $taxable_total = $items['s_price'] - $discount;
                $tax['taxable_amount'] = round( $taxable_total * $tax['taxable_factor'] , 2 );
                $tax['tax'] =  round( ($tax['taxable_amount'] * $tax['tax_rate']) /100 , 2 );
                //$tax_total += $tax['tax'];
                $return_items[$key]['tax'] = $tax['tax'];
                /*if(isset($tax_details[$tax_category][$tax['tax_code']])){
                    $tax_details[$tax_category][$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
                    $tax_details[$tax_category][$tax['tax_code']]['tax'] += $tax['tax'] ;
                }else{
                    
                    $tax_details[$tax_category][$tax['tax_code']] = $tax;
                }*/

            }

        	$return_items[$key]['s_price'] = (string)$return_items[$key]['s_price'];
        	$return_items[$key]['r_price'] = (string)$return_items[$key]['r_price'];
        	$return_items[$key]['discount'] = (string)$return_items[$key]['discount'];
        	$return_items[$key]['tax'] = (string)$return_items[$key]['tax'];
        	$return_items[$key]['bill_discount'] = (string)$return_items[$key]['bill_discount'];
        	$return_items[$key]['unit_mrp'] = (string)$return_items[$key]['unit_mrp'];
        	$return_items[$key]['qty'] = (string)$return_items[$key]['qty'];
        }
       
        $return_items = array_values($return_items);

        foreach($return_items as $items ){
        	$cart = Cart::where('cart_id', $items['cart_id'])->first();
        	DB::table('return_request')->insert(
			    [ 
                'confirm' => $confirm,
                'qty' => $items['qty'] , 
			    'subtotal' => $items['s_price'] , 
			    'discount' => $items['discount'], 
			    'bill_buster_discount' => $items['bill_discount'], 
			    'tax' => $items['tax'], 
			    'total' => $items['s_price'],
			    'status' => 'process',
			    'order_id' => $order_id,
			    'store_id' => $cart->store_id,
			    'v_id' => $cart->v_id,
			    'unit_mrp' => $cart->unit_mrp,
			    'cart_id' => $cart->cart_id,
			    'user_id' => $cart->user_id,
			    'weight_flag' => $cart->weight_flag,
			    'plu_barcode' => $cart->plu_barcode,
			    'barcode' => $cart->barcode,
			    'item_name' => $cart->item_name,
			    'item_id' => $cart->item_id	,
			    'delivery' => $cart->delivery,
			    'slab' => $cart->slab,
			    'transaction_type' => 'return'
			       ]
			);

            ##############################################################################
            ######## This code is to Generate Same response as order Details  START ######
            $res = DB::table('cart_offers')->where('cart_id',$cart->cart_id)->first();
            $offer_data = json_decode($res->offers, true);

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
            $product_data['multiple_price_flag'] = $offer_data['multiple_price_flag'];
            $product_data['multiple_mrp'] = $offer_data['multiple_mrp'];
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

            $return_product_qty = $cart->qty - $approved_qty;
            $cart_data[] = array(
                    'cart_id'       => $cart->cart_id,
                    'product_data'  => $product_data,
                    'amount'        => $items['s_price'] ,
                    'qty'           => $cart->qty,
                    'return_product_qty' => (string) $return_product_qty, //MAx quantity available fo retrun
                    'tax_amount'    => $tax_amount,
                    'delivery'      => $cart->delivery,
                    'item_flag'     => 'NORMAL'
            );


            ######## This code is to Generate Same response as order Details  END  ######
            #############################################################################

        }
        
        //dd($params);

        return response()->json(['status' => 'cart_details', 'message' => 'Your Cart Details', 
            'data' => $cart_data ]);        

	}


	public function approve(Request $request){

		$v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;
        $order_id = $request->order_id;
        $trans_from = $request->trans_from;

        $admin_id = $request->admin_id;
        $status = 'approved';

        $return_items = DB::table('return_request')->where('order_id', $order_id)->where('status','process')->where('confirm','1')->get();

        $t_order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $t_order_id = $t_order_id + 1;

        $subtotal = 0;
        $discount = 0;
        $bill_buster_discount = 0;
        $tax = 0;
        $total = 0;

        //dd($return_items);

        foreach($return_items as $item){

        	$subtotal += $item->subtotal;
        	$discount += $item->discount;
        	$bill_buster_discount += $item->bill_buster_discount;
        	$total += $item->total;
        	$tax += $item->tax;

	       	$ex_cart = Cart::where('cart_id', $item->cart_id)->first();

	        $cart = new Cart;
	        $cart->transaction_type = 'return';
	        $cart->store_id = $item->store_id;
	        $cart->v_id = $item->v_id;
	        $cart->order_id = $t_order_id;
	        $cart->user_id = $item->user_id;
	        $cart->plu_barcode = $ex_cart->plu_barcode;  
	        $cart->item_name = $ex_cart->item_name;  
	        $cart->weight_flag = $ex_cart->weight_flag;  
	        $cart->barcode = $ex_cart->barcode;
	        $cart->qty = $item->qty;
	        $cart->unit_mrp = $ex_cart->unit_mrp;
	        $cart->subtotal = $item->subtotal;
	        $cart->total = $item->total;
	        $cart->discount = $item->discount;
	        $cart->bill_buster_discount = $item->bill_buster_discount;
	        $cart->tax = $item->tax;
	        $cart->status = 'success';
	        $cart->date = date('Y-m-d');
	        $cart->time = date('h:i:s');
	        $cart->month = date('m');
	        $cart->year = date('Y');

	        $cart->target_offer = $ex_cart->target_offer;
	        $cart->slab = $ex_cart->slab;
	        $cart->section_target_offers = $ex_cart->section_target_offers;
	        $cart->section_offers = $ex_cart->section_offers;
	        $cart->item_id = $ex_cart->item_id;
	        $cart->department_id = $ex_cart->department_id;
	        $cart->subclass_id = $ex_cart->subclass_id;
	        $cart->printclass_id = $ex_cart->printclass_id;
	        $cart->group_id = $ex_cart->group_id;
	        $cart->division_id = $ex_cart->division_id;

	        $cart->save();

	        DB::table('return_request')->where('id', $item->id)->update(['status' => 'approved']);

        }

        $last_transaction_no = 0;
        $last_order = Order::where('v_id',$v_id)->where('store_id', $store_id)->where('status','success')->orderBy('od_id','desc')->first();
        $last_transaction_no = $last_order->transaction_no;

        $r_order_id = order_id_generate($store_id, $c_id, $trans_from);

        $order = new Order;

        $order->transaction_type = 'return';
        $order->order_id = $r_order_id;
        $order->ref_order_id = $order_id;
        $order->o_id = $t_order_id;
        $order->v_id = $v_id;
        $order->store_id = $store_id;
        $order->user_id = $c_id;
        $order->subtotal = $subtotal;
        $order->discount = $discount;
        $order->bill_buster_discount = $bill_buster_discount;
        $order->tax = $tax;
        $order->total = $total;
        $order->transaction_no = $last_transaction_no;
        $order->return_by = 'voucher';

        $order->status = 'success';
        $order->date = date('Y-m-d');
        $order->time = date('h:i:s');
        $order->month = date('m');
        $order->year = date('Y');

        $order->save();

        $voucher_no = generateRandomString(6);

        DB::table('voucher')->insert(['v_id'=> $v_id , 'store_id' => $store_id ,  'user_id'=> $c_id , 'amount' => $total , 'ref_id' => $r_order_id , 'status' => 'unused' , 'voucher_no' => $voucher_no]);


        return response()->json(['status' => 'success', 'message' => 'Retrun of items has been approved successfully' ]);

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
