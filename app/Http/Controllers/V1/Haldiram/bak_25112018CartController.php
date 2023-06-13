<?php

namespace App\Http\Controllers\V1\Haldiram;

use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\VendorSettingController;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Mail\OrderCreated;
use Illuminate\Support\Facades\Mail;
use PDF;
use App\Store;
use App\Order;
use App\Cart;
use App\Address;
use App\PartnerOffer;
use App\PartnerOfferUsed;
use App\User;
use DB;
use App\Payment;
use Endroid\QrCode\QrCode;
use App\Wishlist;
use Auth;
use Razorpay\Api\Api;

class CartController extends Controller
{

    public function __construct()
	{
		$this->middleware('auth' , ['except' => ['order_receipt','rt_log'] ]);
	}

	public function add_to_cart(Request $request)
    {

		//dd($request);
        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;
        //$product_id = $request->product_id;
        $barcode = $request->barcode;
        $qty = $request->qty;
        $unit_mrp = $request->unit_mrp;
        $r_price = $request->r_price;
        $s_price = $request->s_price;
        $discount = $request->discount;
        $pdata = $request->pdata;
		
        $temp = DB::table('temp_table')->where('id', $pdata)->first();
		if($temp){
			$data = json_decode($temp->pdata);
			
		}
        
		//dd($data);
        $tax = isset($data->total_tax)?$data->total_tax:0;
        $trans_from = $request->trans_from;
        $weight_flag = 0;
        if($request->has('weight_flag')){
            $weight_flag = $request->weight_flag;
        }

        $vu_id = 0;
        if($request->has('vu_id')){
            $vu_id = $request->vu_id;
        }
        
        $store_db_name = get_store_db_name(['store_id' => $store_id]);
        if(empty($pdata)){
            //echo 'indisde this ';exit;
            $total = $unit_mrp * $qty;
            $final_data['pdata'][] = [ 'qty' => $qty, 'mrp' => $unit_mrp, 'discount' => 0, 'ex_price' => $total, 'total_price' => $total, 'message' => '' , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0];
            $final_data['available_offer'] = [];
            $final_data['applied_offer'] = [];
            $final_data['item_id'] = $barcode;

            $pdata = json_encode($final_data);
            $data = json_decode($pdata);
        }
        
        $plu_flag = false;
        $plu_barcode = 0;
        if($barcode[0] == 2){
            $plu_flag = true;
            $plu_barcode = $barcode;
           // $plu_qty = substr($barcode,7, 5);
            $barcode = substr($plu_barcode,1, 6);   
            $item_master = DB::table($store_db_name.'.item_master')->where('EAN', $barcode)->first();
            $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
            $order_id = $order_id + 1;
            if($weight_flag){
                $check_product_exists = 0;
            }else{
				
				$cart_plu_qty = $qty;
                $cart_plu_qty = explode('.',$cart_plu_qty);
                if(count($cart_plu_qty) > 1 ){
                     $check_product_exists = 0;
                }else{
                    
                    $check_product_exists = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('plu_barcode', $plu_barcode)->where('status', 'process')->count();    
                }
                //$check_product_exists = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('plu_barcode', $plu_barcode)->where('status', 'process')->count();    
            }
            

        }else{

            $item_master = DB::table($store_db_name.'.item_master')->where('EAN', $barcode)->first();
            $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
            $order_id = $order_id + 1;

            $check_product_exists = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('barcode', $barcode)->where('status', 'process')->count();


        }

        

        if(!empty($check_product_exists)) {
        	return response()->json(['status' => 'product_already_exists', 'message' => 'Product Already Exists' ], 409);
        }

        $cart = new Cart;

        $cart->store_id = $store_id;
        $cart->v_id = $v_id;
        $cart->order_id = $order_id;
        $cart->user_id = $c_id;
        //$cart->product_id = $product_id;
        //$cart->weight_flag = 
        if($plu_flag){
          $cart->plu_barcode = $plu_barcode;  
        }
        
        $cart->barcode = $barcode;
        $cart->qty = $qty;
        $cart->unit_mrp = $unit_mrp;
        $cart->subtotal = $r_price;
        $cart->total = $s_price;
        $cart->discount = $discount;
        $cart->tax = $tax;
        $cart->status = 'process';
        $cart->trans_from = $trans_from;
        $cart->vu_id = $vu_id;
        $cart->date = date('Y-m-d');
        $cart->time = date('h:i:s');
        $cart->month = date('m');
        $cart->year = date('Y');

        if($request->has('is_catalog')){
            $cart->is_catalog = $request->is_catalog;
        }

        $cart->target_offer = (isset($data->target))?json_encode($data->target):'';
        $cart->section_target_offers = (isset($data->section_target))?json_encode($data->section_target):'';
        $cart->section_offers = (isset($data->section_offer))?json_encode($data->section_offer):'';
        $cart->item_id = $item_master->ITEM;
        $cart->department_id = $item_master->ID_MRHRC_GP_PRNT_DEPT;
        $cart->subclass_id = $item_master->ID_MRHRC_GP_SUBCLASS;
        $cart->printclass_id = $item_master->ID_MRHRC_GP_PRNT_CLASS;
        $cart->group_id = $item_master->ID_MRHRC_GP_PRNT_GROUP;
        $cart->division_id = $item_master->ID_MRHRC_GP_PRNT_DIVISION;

        $cart->save();

        
        foreach ($data as $key => $val) {
                if ($key == 'pdata') {
                    foreach ($val as $key => $value) {
                        $cart_details = DB::table('cart_details')->insert([
                            'cart_id' => $cart->cart_id,
                            'qty' => $value->qty,
                            'mrp' => $value->mrp,
                            'price' => $value->total_price,
                            'discount' => $value->discount,
                            'ext_price' => $value->ex_price,
                            'tax' => '',
                            'message' => $value->message,
                            'ru_prdv' => $value->ru_prdv,
                            'type' => $value->type,
                            'type_id' => $value->type_id,
                            'promo_id' => $value->promo_id,
                            'is_promo' => $value->is_promo,
                            'taxes' => isset($value->tax)?json_encode($value->tax):''
                        ]);
                    }
                }
            }


        $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id ];
        $this->process_each_item_in_cart($params);

        return response()->json(['status' => 'add_to_cart', 'message' => 'Product was successfully added to your cart.', 'data' => $cart ],200);
        
    }

    public function apply_employee_discount(Request $request){
        
        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;

        $employee_code = $request->employee_code;
        $company_name = $request->company_name;

        $params = [ 'employee_code' => $employee_code , 'company_name'=> $company_name ] ;

        $employDis = new EmployeeDiscountController;
        $employee_details = $employDis->get_details($params);

        $store_db_name = get_store_db_name(['store_id' => $store_id]);
        //dd($employee_details);
        if($employee_details){
            $employee = DB::table($v_id.'_employee_details')->where('employee_id', $employee_details->Employee_ID)->first();
            
            if($employee){

                 DB::table($v_id.'_employee_details')->update(['available_discount' => $employee_details->Available_Discount_Amount]);
                
            }else{
                DB::table($v_id.'_employee_details')->insert([
                    'employee_id' => $employee_details->Employee_ID,
                    'first_name'  => $employee_details->First_Name,
                    'last_name'  => $employee_details->Last_Name,
                    'designation'  => $employee_details->Designation,
                    'location'  => $employee_details->Location,
                    'company_name'  => $employee_details->Comp_Name,
                    'available_discount' => $employee_details->Available_Discount_Amount
                ]);
            }

            $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id , 'employee_available_discount' => $employee_details->Available_Discount_Amount , 'employee_id' => $employee_details->Employee_ID , 'company_name' => $company_name ];

            return $this->process_each_item_in_cart($params);

            

        }else{
            return response()->json(['status' => 'fail', 'message' => 'Unable to find the employee'], 200);
        }

    }
	
	
	public function remove_employee_discount(Request $request){
        
        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;

        $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

        $cart = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->update(['employee_id' => '' , 'employee_discount' => 0.00]);


        $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id   ];

        $this->process_each_item_in_cart($params);

        return response()->json(['status' => 'success', 'message' => 'Removed Successfully' ]);

    }

    public function product_qty_update(Request $request)
    {
    	$v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;
        //$product_id = $request->product_id;
        $barcode = $request->barcode;
        $qty = $request->qty;
        $unit_mrp = $request->unit_mrp;
        $r_price = $request->r_price;
        $s_price = $request->s_price;
        $discount = $request->discount;



        $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

        $check_product_exists = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('barcode', $barcode)->where('status', 'process')->first();

        $check_product_exists->qty =  $qty;
        $check_product_exists->unit_mrp =  $unit_mrp;
        $check_product_exists->subtotal =  $r_price;
        $check_product_exists->total = $s_price;
        $check_product_exists->discount = $discount;
        $check_product_exists->save();

        $check_product_exists->save();

        $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id ];
        $this->process_each_item_in_cart($params);

        return response()->json(['status' => 'product_qty_update', 'message' => 'Product quantity successfully Updated'], 200);
        
    }

    public function remove_product(Request $request)
    {

       

        $v_id = $request->v_id;

    	$c_id = $request->c_id;
    	$store_id = $request->store_id;
    	$v_id = $request->v_id;
    	
    	//$barcode = $request->barcode;
        if($request->has('all')){
            if($request->all == 1){
                $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
                $order_id = $order_id + 1;

                $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

                foreach ($carts as $key => $cart) {
                    Cart::where('cart_id', $cart->cart_id)->delete();
                    DB::table('cart_details')->where('cart_id' , $cart->cart_id)->delete();
                    DB::table('cart_offers')->where('cart_id' , $cart->cart_id)->delete();
                }
            }

        }else{

            if($request->has('cart_id')){
                $cart_id = $request->cart_id;
                Cart::where('cart_id', $cart_id)->delete();
                DB::table('cart_details')->where('cart_id' , $cart_id)->delete();
                DB::table('cart_offers')->where('cart_id' , $cart_id)->delete();
            }

        }


    	

        $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id ];
        $this->process_each_item_in_cart($params);

    	return response()->json(['status' => 'remove_product', 'message' => 'Item Removed successfully' ],200);
        
    }

    public function cart_details(Request $request)
    {
            $v_id = $request->v_id;
            $c_id = $request->c_id;
            $store_id = $request->store_id; 
			$trans_from = $request->trans_from;

            
            //html = $this->order_receipt($c_id , $v_id, $store_id, 'OD526301835648336284');
            //$pdf = PDF::loadHTML($html);
            /*$path =  storage_path();
            $complete_path = $path."/app/invoices/new_invoice.pdf";
            
            $order_id = 'OD646901850605694154';
            $order = Order::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status','success')->where('order_id', $order_id)->first();
            $payment = Payment::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->first();
            $payment_method = (isset($payment->method) )?$payment->method:'';

            $carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status','success')->where('order_id', $order->o_id)->get();
            $user = Auth::user();
            Mail::to('chandramani212@gmail.com')->send(new OrderCreated($user,$order,$carts,$payment_method, $complete_path));

            //return view('emails.orders.created', ['user' => $user, 'order' => $order, 'carts' => $carts, 'payment_method' => $payment_method, 'complete_path' =>  $complete_path]);
            exit;*/
            

            $cart_data = array();
            $product_data = [];
            $tax_total = 0;
    		$cart_qty_total = 0;
            //$order_id = Order::where('user_id', $c_id)->where('status', 'success')->orWhere('status' ,'error')->count();
    		$order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
            $order_id = $order_id + 1;
            
            $carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status','process')->orderBy('updated_at','desc')->get();
            $sub_total = $carts->sum('subtotal');
            $item_discount  = $carts->sum('discount');
            $employee_discount = $carts->sum('employee_discount');
            $employee_id = 0;
            $total     = $carts->sum('total');
            $tax_total = $carts->sum('tax');
            $bill_buster_discount = 0;
            $price = array();
            $rprice = array();
            $qty = array();
            $merge = array();
            $saving = 0;
            $carry_bag_added = false;
            $tax_details = [];
            $tax_details_data=[];
            $param =[];
            $params= [];
            foreach ($carts as $key => $cart) {
                $employee_id = $cart->employee_id;
                
                $loopQty = $cart->qty;
                while($loopQty > 0){
                   $param[] = $cart->total / $cart->qty; 
                   $params[] = ['item_id' => $cart->item_id , 'price' => $cart->total / $cart->qty ];
                   $loopQty--;
                }

                $res = DB::table('cart_offers')->where('cart_id',$cart->cart_id)->first();

                $offer_data = json_decode($res->offers, true);

                foreach ($offer_data['pdata'] as $key => $value) {
                    $tax_details_data[$cart->item_id] = ['tax' =>  $value['tax'] , 'total' => $value['ex_price'] ];
                    
                }
                $available_offer =[];
                foreach($offer_data['available_offer'] as $key => $value){

                    $available_offer[] =  ['message' => $value ];
                }
                $applied_offer = [];
                $offer_data['available_offer'] = $available_offer;

                foreach($offer_data['applied_offer'] as $key => $value){

                    $applied_offer[] =  ['message' => $value ];
                }
                $offer_data['applied_offer'] = $applied_offer;

                //Counting the duplicate offers
                $tempOffers = $offer_data['applied_offer'];
                for($i=0; $i<count($offer_data['applied_offer']); $i++){
                    $apply_times = 1 ;
                    $apply_key = 0;
                    for($j=$i+1; $j<count($tempOffers); $j++){
                        
                        if( isset($offer_data['applied_offer'][$j]['message']) && $tempOffers[$i]['message'] == $offer_data['applied_offer'][$j]['message']){
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
                //dd($offer_data);
                $carr_bag_arr =  [ '114903443', '114952448' ,'114974444'];
                $carry_bag_flag = in_array($cart->item_id, $carr_bag_arr);
                
                if($carry_bag_flag){
                    $carry_bag_added = true;
                }

                $product_data['carry_bag_flag'] = $carry_bag_flag;
                $product_data['p_id'] = (int)$cart->item_id;
                $product_data['category'] = '';
                $product_data['brand_name'] = '';
                $product_data['sub_categroy'] = '';
                $product_data['whishlist'] = 'No';
                $product_data['weight_flag'] = ($cart->weight_flag == 1)?true:false;
                $product_data['quantity_change_flag'] = (strlen($cart->plu_barcode) == 13)?false:true;
                $product_data['p_name'] = $cart->item_name;
                $product_data['offer'] = (count($offer_data['applied_offer']) > 0)?'Yes':'No';
                $product_data['offer_data'] = [ 'applied_offers' => $offer_data['applied_offer'] , 'available_offers' =>$offer_data['available_offer']  ];
                //$product_data['qty'] = '';
                $product_data['multiple_price_flag'] = $offer_data['multiple_price_flag'];
                $product_data['multiple_mrp'] = $offer_data['multiple_mrp'];
                $product_data['r_price'] = format_number($offer_data['r_price']);
                $product_data['s_price'] = format_number($offer_data['s_price']);
                /*if(!empty($offer_data['applied_offers']) ){
                    $product_data['r_price'] = format_number($offer_data['r_price']);
                    $product_data['s_price'] = format_number($offer_data['s_price']);
                }*/

                $product_data['varient'] = '';
                $product_data['images'] = 'zwing_default.png';
                $product_data['description'] = '';
                $product_data['deparment'] = '';
                $product_data['barcode'] = $cart->barcode;

    		    //$tax_total = $tax_total +  $tax_amount ;
                $tax_amount = $cart->tax;
                
				if($cart->weight_flag == '1'){
                    $cart_qty_total =  $cart_qty_total + 1;
                }else{

                    if($cart->plu_barcode){
                        $cart_plu_qty = $cart->qty;
                        $cart_plu_qty = explode('.',$cart_plu_qty);
                        //dd($cart_plu_qty);
                        if(count($cart_plu_qty) > 1 ){
                            $cart_qty_total =  $cart_qty_total + 1;
                        }else{
                            $cart_qty_total =  $cart_qty_total + $cart->qty;    
                        }
                    }else{
                        $cart_qty_total =  $cart_qty_total + $cart->qty; 
                    }   
                }
    
                $cart_data[] = array(
                        'cart_id'       => $cart->cart_id,
                        'product_data'  => $product_data,
                        'amount'        => $cart->total,
                        'qty'           => $cart->qty,
                        'tax_amount'    => format_number($tax_amount),
                        'delivery'      => $cart->delivery
                        // 'ptotal'        => $cart->amount * $cart->qty,
                );
                //$tax_total = $tax_total +  $tax_amount ;
                
                $qty[] = $cart->qty;
                //$merge = array_combine($rprice,$qty);

                
            }
    		/*
    		echo '<pre>';print_r($merge);exit;

            foreach ($merge as $keys => $val) {
                $saving[] = round($keys * $val);
            }*/

            $store_db_name = get_store_db_name(['store_id' => $store_id]);

            $promo_c = new PromotionController(['store_db_name' => $store_db_name ]);
            ######################################
            ##### --- BILL BUSTER  START --- #####

            //Bill Buster Calculation
            $ru_prdv_data_bill_buster = $promo_c->get_rule_id('', 'billbuster');
            $filter_data_bill_buster = $promo_c->filterPromotionID($ru_prdv_data_bill_buster);
            //dd($filter_data_bill_buster);
            $bill_buster_dis =[];
			$push_data_bill=[];
            foreach ($filter_data_bill_buster as $key => $value) {
                $spilt = explode("-", $key);

                if ($spilt[0] == 'Buy$NorMoreGetZ$offTiered') {
                    // echo 'BuyNOrMoreOfXGetatUnitPriceTiered<br>';
                    $push_data_bill[$spilt[0]][$spilt[1]] = $value;
                }elseif ($spilt[0] == 'Buy$NorMoreGetZ%offTiered') {
                    $push_data_bill[$spilt[0]][$spilt[1]] = $value;
                }elseif ($spilt[0] == 'BuyRsNOrMoreGetPrintedItemFreeTiered') {
                    $push_data_bill[$spilt[0]][$spilt[1]] = $value;
                }


            }
            //dd($push_data_bill);
            //echo $total;exit;
            $barcode = '1000000000000';
            foreach ($push_data_bill as $key => $value) {
                if ($key == 'Buy$NorMoreGetZ$offTiered') {
                    $response = $promo_c->shop_bill_get_amount_tiered($total, $cart_qty_total, $value, $barcode, $store_id, $c_id);
                    if(!empty($response)){
                        $bill_buster_dis[$response['discount']] = $response;
                    }
                }elseif($key == 'Buy$NorMoreGetZ%offTiered'){
                    $response = $promo_c->shop_bill_get_percentage_tiered($total, $cart_qty_total, $value, $barcode, $store_id, $c_id);
                    if(!empty($response)){
                        $bill_buster_dis[$response['discount']] = $response;
                    }
                }elseif($key == 'BuyRsNOrMoreGetPrintedItemFreeTiered'){
                    /*$response = $this->shop_bill_get_printed_tiered($total_amount, $qty, $value, $barcode, $store, $user_id);
                    if(!empty($response)){
                        $bill_buster_dis[$response['discount']] = $response;
                    }*/
                }
            }
            
            if(!empty($bill_buster_dis)){
                $max = max( array_keys($bill_buster_dis) );
                $bill_buster_dis = $bill_buster_dis[$max];

                $final_data['applied_offer'][] = $bill_buster_dis['message'];
            }

            if($employee_discount > 0.00){
                $total = $total - $employee_discount;
            }
            ##### --- BILL BUSTER  END --- #####
            ####################################
            //dd($tax_details_data);
            //dd($param);
            $bill_buster_discount = 0;
            if(isset($bill_buster_dis['discount']) && $bill_buster_dis['discount'] > 0 ){
                $bill_buster_discount = $bill_buster_dis['discount'];
                $tax_total = 0;
                
                //Recalculating tax after bill buster applied
                $ratio_val = $promo_c->get_offer_amount_by_ratio($param, $bill_buster_discount);
                $ratio_total = array_sum($ratio_val);

                $discount = 0;
                $total_discount = 0;
                //dd($param);
    
                foreach($params as $key => $par){
                    $discount = round( ($ratio_val[$key]/$ratio_total) * $bill_buster_discount , 2);
                    $params[$key]['discount'] =  $discount;
                    $total_discount += $discount;
                }
                //echo $total_discount;exit;
                //Thid code is added because facing issue when rounding of discount value
                if($total_discount > $bill_buster_discount){
                    $total_diff = $total_discount - $bill_buster_discount;
                    foreach($params as $key => $par){
                        if($total_diff > 0.00){
                            $params[$key]['discount'] -= 0.01;
                            $total_diff -= 0.01;
                        }else{
                            break;
                        }
                    }
                }else if($total_discount < $bill_buster_discount){
                    $total_diff =  $bill_buster_discount - $total_discount;
                    foreach($params as $key => $par){
                        if($total_diff > 0.00){
                            $params[$key]['discount'] += 0.01;
                            $total_diff -= 0.01;
                        }else{
                            break;
                        }
                    }
                }

                //dd($param);
                foreach($params as $key => $para){
                    $discount = $para['discount'];   
                    $item_id = $para['item_id'];
                    //$tax_details_data[$key]
                    foreach($tax_details_data[$item_id]['tax'] as $nkey => $tax){

                        $taxable_total = $tax_details_data[$item_id]['total'] -$discount;
                        $tax['taxable_amount'] = round( $taxable_total * $tax['taxable_factor'] , 2 );
                        $tax['tax'] =  round( ($tax['taxable_amount'] * $tax['tax_rate']) /100 , 2 );

                        $tax_total += $tax['tax'];

                        if(isset($tax_details[$tax['tax_code']])){
                            $tax_details[$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
                            $tax_details[$tax['tax_code']]['tax'] += $tax['tax'] ;
                        }else{
                            
                            $tax_details[$tax['tax_code']] = $tax;
                        }

                    }
                }
        
            
            }

            $total = $total - $bill_buster_discount;
            $saving = $item_discount + $bill_buster_discount;

            //dd($carry_bags);
            
            $bags = $this->get_carry_bags($request);
            $bags = $bags['data'];
            // $cart_data['bags'] = $bags;
    		
    		        
            if(empty($bprice->Price)) {
                $carry_bag_total = 0;
            } else {
                $carry_bag_total = $bprice->Price;
            }

            //$total = $sub_total + $carry_bag_total;
            //$less = 
    		
    		$offeredAmount = 0;
    		$grand_total = $sub_total + $carry_bag_total + $tax_total;
    		$offerUsed = PartnerOfferUsed::where('user_id',$c_id)->where('order_id',$order_id)->first();
            if( $offerUsed){
                

                $offers =  PartnerOffer::where('id',$offerUsed->partner_offer_id)->first();
    			if($offers){
                 if($offers->type == 'PRICE'){

                        $offerMsg = "Get Cash Back Upto $offers->value ";
                        $offeredAmount = $offers->value;
                    }else if($offers->type == 'PERCENTAGE'){
                        $offerMsg = "Get Upto $offers->value % Discount max upto $offers->max";
                        
                        $offeredAmount = ($grand_total  * $offers->value) / 100;
                        if( $offers->max != 0  && $offeredAmount >= $offers->max){
                            $offeredAmount = $offers->max;
                        }
                        
                    }

                   $grand_total = $grand_total - $offeredAmount;
    			}

            }
    		
            $store = DB::table('stores')->select('delivery')->where('store_id', $store_id)->where('v_id', $v_id)->first();

            // if ($store->delivery == 'Yes') {
            //     $product_data['delivery'] = $wflag;
            // }
            // $sub_total = (int)$sub_total + $bprice->Price;
            $voucher_array = [];
            $pay_by_voucher = 0;
            $vouchers = DB::table('voucher_applied as va')
                    ->join('voucher as v','v.id','va.voucher_id')
                    ->select('v.*')
                    ->where('va.user_id', $c_id)->where('va.v_id', $v_id)->where('va.store_id', $store_id)->where('va.order_id', $order_id)->get();
            
			$voucher_total = 0 ;
            $pay_by_voucher = 0;
            foreach ($vouchers as $key => $voucher) {
                array_push($voucher_array ,['name' => 'Zwing Credit' , 'amount' => $voucher->amount ] );
                $voucher_total += $voucher->amount;
                if($total >= $voucher->amount ){
                    $pay_by_voucher += $voucher->amount;
                    $total  = $total - $voucher->amount;
                }else{
                    $pay_by_voucher += $total;
                    $total  = 0;
                     
                }

            }
			$voucher_total = $pay_by_voucher;
            
            $vendorS = new VendorSettingController;
            $product_max_qty =  $vendorS->getProductMaxQty(['v_id' => $v_id, 'trans_from' => $trans_from]) ;
            $cart_max_item = $vendorS->getMaxItemInCart(['v_id' => $v_id, 'trans_from' => $trans_from]);

            $paymentTypeSettings = $vendorS->getPaymentTypeSetting(['v_id' => $v_id, 'trans_from' => $trans_from]);
    
            
            return response()->json(['status' => 'cart_details', 'message' => 'Your Cart Details', 
			'payment_type' =>  $paymentTypeSettings,
            'data' => $cart_data, 'product_image_link' => product_image_link(),
            //'offer_data' => $global_offer_data,
			'current_date' => date('d F Y'),
			'cart_max_item' => (string)$cart_max_item,
            'product_max_qty' => (string)$product_max_qty,
            'carry_bag_added' => $carry_bag_added,
            'bags' => $bags, 
            'sub_total' => (format_number($sub_total))?format_number($sub_total):'0.00', 
            'tax_total' => (format_number($tax_total))?format_number($tax_total):'0.00',
            'employee_id' => $employee_id,
            'employee_discount' => (format_number($employee_discount))?format_number($employee_discount):'0.00',
            'bill_buster_discount' => (format_number($bill_buster_discount))?format_number($bill_buster_discount):'0.00',
            'discount' => (format_number($item_discount))?format_number($item_discount):'0.00', 
            //'grand_total' => (format_number($grand_total))?format_number($grand_total):'0.00', 
            'order_id' => $order_id, 
            'carry_bag_total' => (format_number($carry_bag_total))?format_number($carry_bag_total):'0.00',
			'voucher_total' => $voucher_total,
            'vouchers' => $voucher_array,
            'pay_by_voucher' => $pay_by_voucher,
            'total' => format_number($total), 
            'cart_qty_total' => (string)$cart_qty_total,
            'saving' => (format_number($saving))?format_number($saving):'0.00',
            'delivered' => $store->delivery , 
            'offered_mount' => (format_number($offeredAmount))?format_number($offeredAmount):'0.00' ],200);
            // echo array_sum($saving);
        
    }

    public function process_to_payment(Request $request)
    {
        //dd($request);
    	$v_id = $request->v_id;
        
        $c_id = $request->c_id;
        $store_id = $request->store_id;
        $subtotal = $request->sub_total;
        $discount = $request->discount;
        $bill_buster_discount = $request->bill_buster_discount;
        $pay_by_voucher = $request->pay_by_voucher;
        $trans_from = $request->trans_from;
		
		if($request->has('payment_gateway_type')){
            $payment_gateway_type = $request->payment_gateway_type; //'EZETAP'
        }else{
            $payment_gateway_type = 'RAZOR_PAY';
        }

        $vu_id = 0;
        if($request->has('vu_id')){
            $vu_id = $request->vu_id;
        }
		
		//Checking Opening balance has entered or not if payment is through cash
        if($vu_id > 0 && $payment_gateway_type == 'CASH'){
            $vendorSett = new \App\Http\Controllers\VendorSettlementController;
            $response = $vendorSett->opening_balance_status($request);
            if($response){
                return $response;
            }
        }

        $store_db_name = get_store_db_name(['store_id' => $store_id]);
        $employee_id = '';
        $employee_discount = 0.0;
        if($request->has('employee_id')){
            $employee_id = $request->employee_id;
            $employee_discount = $request->employee_discount;
        }
        
        $tax = $request->tax_total;
        $total = $request->total;
        $trans_from = $request->trans_from;

        $t_order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $t_order_id = $t_order_id + 1;
        $order_id = order_id_generate($store_id, $c_id, $trans_from);

        $order = new Order;

        $order->order_id = $order_id;
        $order->o_id = $t_order_id;
        $order->v_id = $v_id;
        $order->store_id = $store_id;
        $order->user_id = $c_id;
        $order->subtotal = $subtotal;
        $order->discount = $discount;
        $order->employee_id = $employee_id;
        $order->employee_discount = $employee_discount;
        //$order->employee_available_discount = $employee_available_discount;
        $order->bill_buster_discount = $bill_buster_discount;
        $order->tax = $tax;
        $order->total = $total + $pay_by_voucher ;

        $order->status = 'process';
        $order->trans_from = $trans_from;
        $order->vu_id = $vu_id;
        $order->date = date('Y-m-d');
        $order->time = date('h:i:s');
        $order->month = date('m');
        $order->year = date('Y');

        $order->save();
        $vouchers = DB::table('voucher_applied')->select('voucher_id')->where('store_id' ,$store_id)->where('v_id',$v_id)->where('user_id', $c_id)->where('order_id', $t_order_id)->get();

        foreach($vouchers as $voucher){
            DB::table('voucher')->where('id', $voucher->voucher_id)->update(['status'=> 'used']);
        }

        $payment = null;
        if($pay_by_voucher > 0.00){
                
                $payment = new Payment;
                $payment->store_id = $store_id;
                $payment->v_id = $v_id;
                $payment->t_order_id = 0;
                $payment->order_id = $order_id;
                $payment->user_id = $c_id;
                $payment->pay_id = 'user_order_id_'.$t_order_id;
                $payment->amount = $pay_by_voucher;
                $payment->method = 'zwing_credit';
                $payment->invoice_id = '';
                $payment->bank = '';
                $payment->wallet = '';
                $payment->vpa = '';
                $payment->error_description = '';
                $payment->status = 'success';
                $payment->date = date('Y-m-d');
                $payment->time = date('h:i:s');
                $payment->month = date('m');
                $payment->year = date('Y');

                $payment->save();

        }

        if($total == 0.00){
            $date = date('Y-m-d');
            $time = date('h:i:s');

            $last_transaction_no = 0;
            $store = Store::where('v_id',$v_id)->where('store_id', $store_id)->first();
            $order = Order::where('v_id',$v_id)->where('store_id', $store_id)->where('status','success')->orderBy('od_id','desc')->first();
            $last_invoice_name = $order->invoice_name;
            $last_transaction_no = $order->transaction_no;
            $current_invoice_name = '';
            if($last_invoice_name){
               $arr =  explode('_',$last_invoice_name);
               $id = $arr[2] + 1;
                $current_invoice_name = $date.$time.'_'.$store->mapping_store_id.'_'.$store_id.'_'.$id.'.pdf';
            }else{
                $current_invoice_name = $date.$time.'_'.$store->mapping_store_id.'_'.$store_id.'_1.pdf';
            }
            //Order::where('order_id', $order_id)->update(['status' => $status]);
            $ord = Order::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->first();
            if($request->has('address_id')){
               $ord->address_id = $request->address_id;
            }
            $ord->invoice_name = $current_invoice_name;
            $ord->transaction_no = $last_transaction_no + 1;
            $ord->status = 'success';
            $ord->save();

            Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $ord->o_id)->where('user_id', $c_id)->update(['status' => 'success']);
            
            $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $ord->o_id)->where('user_id', $c_id)->get();
            //dd($ord);

            if($ord->employee_discount > 0.00){
                $emp_d = new EmployeeDiscountController;
                $emp_det = DB::table($v_id.'_employee_details')->where('employee_id', $ord->employee_id)->first();

                $dis = $carts->where('employee_discount' ,'>' , 0.00);
                $discounted_amount = $dis->sum('total');

                $params = ['employee_code' => $ord->employee_id, 'company_name' => $emp_det->company_name, 'discounted_amount' => $discounted_amount ] ;
                $res = $emp_d->update_discount($params);

                $details = $emp_d->get_details($params);

                //updating orders
                $ord->employee_available_discount = $details->Available_Discount_Amount;
                $ord->save();
                //DB::table('spar_uat.employee_details')->where('employee_id', $ord->employee_id)->update(['available_discount' => ]);
            }

            $html = $this->order_receipt($c_id , $v_id, $store_id, $order_id);
            $pdf = PDF::loadHTML($html);
            $path =  storage_path();
            $complete_path = $path."/app/invoices/".$current_invoice_name;
            $pdf->setWarnings(false)->save($complete_path);

            $payment_method = (isset($payment->method) )?$payment->method:'';

            $user = Auth::user();
            Mail::to($user->email)->bcc(env('BCC_MAIL'))->send(new OrderCreated($user,$ord,$carts,$payment_method,  $complete_path));

            return response()->json(['status' => 'payment_save', 'message' => 'Save Payment', 'data' => $payment , 'redirect_to_qr' => true ],200);
            exit;

        }

        return response()->json(['status' => 'proceed_to_payment', 'message' => 'Proceed to Payment', 'data' => $order , 'redirect_to_qr' => false ],200);
    
    }

    public function payment_details(Request $request)
    {

        $v_id = $request->v_id;
        $order_id = $request->order_id;
        $user_id = $request->c_id;
        $store_id = $request->store_id;
		
		$vu_id =0;
        if($request->has('vu_id')){
            $vu_id = $request->vu_id;
        }

        $payment_save_status = false;
        if($request->has('payment_gateway_type')){
            $payment_gateway_type = $request->payment_gateway_type; //'EZETAP'
        }else{
            $payment_gateway_type = 'RAZOR_PAY';
        }
		
		//$razorpay_payment = (object)$razorpay_payment = ['status' => 'captured', 'method'=>'cart','invoice_id' => '', 'wallet'=> '' , 'vpa' =>''];
        if($payment_gateway_type =='RAZOR_PAY'){

            $t_order_id = $request->t_order_id;
            $pay_id = $request->pay_id;
            $amount = $request->amount;
            $method = $request->method;
            $invoice_id = $request->invoice_id;
            $bank = $request->bank;
            $wallet = $request->wallet;
            $vpa = $request->vpa;
            $error_description = $request->error_description;
            $status = $request->status;
            
            $store_db_name = get_store_db_name(['store_id' => $store_id]);
            $api_key = env('RAZORPAY_API_KEY');
            $api_secret = env('RAZORPAY_API_SECERET');

            $api = new Api($api_key, $api_secret);
            $razorAmount = $amount * 100;
            $razorpay_payment  = $api->payment->fetch($pay_id)->capture(array('amount'=>$razorAmount)); // Captures a payment

            if($razorpay_payment){

                if($razorpay_payment->status == 'captured'){

                    $date = date('Y-m-d');
                    $time = date('h:i:s');
    				$payment = new Payment;

    				$payment->store_id = $store_id;
    				$payment->v_id = $v_id;
    				$payment->t_order_id = $t_order_id;
    				$payment->order_id = $order_id;
    				$payment->user_id = $user_id;
    				$payment->pay_id = $pay_id;
    				$payment->amount = $amount;
    				$payment->method = $razorpay_payment->method;
                    $payment->invoice_id = $razorpay_payment->invoice_id;;
                    $payment->bank = $razorpay_payment->bank;
                    $payment->wallet = $razorpay_payment->wallet;
                    $payment->vpa = $razorpay_payment->vpa;
    				$payment->error_description = $error_description;
    				$payment->status = $status;
                    $payment->payment_gateway_type = $payment_gateway_type;
    				$payment->date = date('Y-m-d');
    				$payment->time = date('h:i:s');
    				$payment->month = date('m');
    				$payment->year = date('Y');

    				$payment->save();

                    $payment_save_status = true;
    				
    		
    			}
    		
    		}

        }else if($payment_gateway_type =='EZETAP'){

            //$t_order_id = $request->t_order_id;
            $pay_id = $request->pay_id; //tnx->txnId
            $amount = $request->amount; //tnx->amount
            $method = $request->method; //tnx->paymentMode
            $invoice_id = $request->invoice_id; //tnx->invoiceNumber
            $status = $request->status; // $gateway_response->status


            $date = date('Y-m-d');
            $time = date('h:i:s');
            $payment = new Payment;

            $gateway_response = $request->gateway_response;

            $gateway_response = json_decode($gateway_response);

            //dd($gateway_response->result);
            //var_dump($gateway_response->result->txn);
            if(!empty($gateway_response)){
                $status = $gateway_response->status;
                $tnx = $gateway_response->result->txn;

                $pay_id = $tnx->txnId; //tnx->txnId
                $amount = $tnx->amount; //tnx->amount
                $method = $tnx->paymentMode; //tnx->paymentMode
                $invoice_id = $tnx->invoiceNumber; //tnx->invoiceNumber
            }
            
            $payment->store_id = $store_id;
            $payment->v_id = $v_id;
            //$payment->t_order_id = $t_order_id;
            $payment->order_id = $order_id;
            $payment->user_id = $user_id;
            $payment->pay_id = $pay_id;
            $payment->amount = $amount;
            $payment->method = $method;
            $payment->invoice_id = $invoice_id;
            $payment->status = $status;
            $payment->payment_gateway_type = $payment_gateway_type;
            $payment->gateway_response = json_encode($gateway_response);
            $payment->date = date('Y-m-d');
            $payment->time = date('h:i:s');
            $payment->month = date('m');
            $payment->year = date('Y');

            $payment->save();

            $payment_save_status = true;

        }else{

            //$t_order_id = $request->t_order_id;
            $pay_id = $request->pay_id; //tnx->txnId
            $amount = $request->amount; //tnx->amount
            $cash_collected = $request->cash_collected;
            $cash_return = $request->cash_return;
            $method = $request->method; //tnx->paymentMode
            $invoice_id = $request->invoice_id; //tnx->invoiceNumber
            $status = $request->status; // $gateway_response->status

            $date = date('Y-m-d');
            $time = date('h:i:s');
            $payment = new Payment;

            $payment->store_id = $store_id;
            $payment->v_id = $v_id;
            //$payment->t_order_id = $t_order_id;
            $payment->order_id = $order_id;
            $payment->user_id = $user_id;
            $payment->pay_id = $pay_id;
            $payment->amount = $amount;
            $payment->method = $method;
            $payment->cash_collected = $cash_collected;
            $payment->cash_return = $cash_return;
            $payment->invoice_id = $invoice_id;
            $payment->status = $status;
            $payment->payment_gateway_type = $payment_gateway_type;
            //$payment->gateway_response = json_encode($gateway_response);
            $payment->date = date('Y-m-d');
            $payment->time = date('h:i:s');
            $payment->month = date('m');
            $payment->year = date('Y');

            $payment->save();

            $payment_save_status = true;

        }

        if($payment_save_status){

            $last_transaction_no = 0;
            $store = Store::where('v_id',$v_id)->where('store_id', $store_id)->first();
            $order = Order::where('v_id',$v_id)->where('store_id', $store_id)->where('status','success')->orderBy('od_id','desc')->first();
            if($order){
                $last_invoice_name = $order->invoice_name;
                $last_transaction_no = $order->transaction_no;
                $current_invoice_name = '';
                if($last_invoice_name){
                   $arr =  explode('_',$last_invoice_name);
                   $id = $arr[2] + 1;
                    $current_invoice_name = $date.$time.'_'.$store->mapping_store_id.'_'.$store_id.'_'.$id.'.pdf';
                }else{
                    $current_invoice_name = $date.$time.'_'.$store->mapping_store_id.'_'.$store_id.'_1.pdf';
                }
            }else{
                $current_invoice_name = $date.$time.'_'.$store->mapping_store_id.'_'.$store_id.'_1.pdf';
            }
            //Order::where('order_id', $order_id)->update(['status' => $status]);
            $ord = Order::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->first();
            if($request->has('address_id')){
               $ord->address_id = $request->address_id;
            }
            $ord->invoice_name = $current_invoice_name;
            $ord->transaction_no = $last_transaction_no + 1;
			if($vu_id > 0){
                $ord->vu_id = $vu_id;
                $ord->verify_status = '1';
                $ord->verify_status_guard = '1';    
            }
            $ord->status = $status;
            $ord->save();
			
            Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $ord->o_id)->where('user_id', $user_id)->update(['status' => $status]);
            /*
            $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $ord->o_id)->where('user_id', $user_id)->get();
            //dd($ord);

            if($ord->employee_discount > 0.00){
                $emp_d = new EmployeeDiscountController;
                $emp_det = DB::table($v_id.'_employee_details')->where('employee_id', $ord->employee_id)->first();

                $dis = $carts->where('employee_discount' ,'>' , 0.00);
                $discounted_amount = $dis->sum('total');

                $params = ['employee_code' => $ord->employee_id, 'company_name' => $emp_det->company_name, 'discounted_amount' => $discounted_amount ] ;
                $res = $emp_d->update_discount($params);

                $details = $emp_d->get_details($params);

                //updating orders
                $ord->employee_available_discount = $details->Available_Discount_Amount;
                $ord->save();
                //DB::table('spar_uat.employee_details')->where('employee_id', $ord->employee_id)->update(['available_discount' => ]);
            }

			try{
				$html = $this->order_receipt($user_id , $v_id, $store_id, $order_id);
				$pdf = PDF::loadHTML($html);
				$path =  storage_path();
				$complete_path = $path."/app/invoices/".$current_invoice_name;
				$pdf->setWarnings(false)->save($complete_path);

				$payment_method = (isset($payment->method) )?$payment->method:'';

				$user = Auth::user();
				if($user->email != null && $user->email != ''){
				   
						Mail::to($user->email)->bcc(env('BCC_MAIL'))->send(new OrderCreated($user,$ord,$carts,$payment_method,  $complete_path));
					
				}
				
			}catch(Exception $e){
						//Nothing doing after catching email fail
			}*/
        
            return response()->json(['status' => 'payment_save', 'message' => 'Save Payment', 'data' => $payment ],200);
        }
    }
	
    public function order_qr_code(Request $request)
    {
        $order_id = $request->order_id;
        $qrCode = new QrCode($order_id);
        header('Content-Type: image/png');
        echo $qrCode->writeString();
    }

    public function order_pre_verify_guide(Request $request){
        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id; 
        $order_id = $request->order_id; 

        $o_id = Order::where('order_id', $order_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->first();


        $message_data = [];
        
        $message_data['title'][] = ['message' => 'Thank You for Shopping!'];
        $message_data['body'][] = [ 'message' => 'Please proceed with your purchase to'];
        if($o_id->qty <= 5 ){
            $message_data['body'][] = [ 'message' => 'The exit and show your'];
            $message_data['body'][] = [ 'message' => 'QR Receipt to the staff'];
        }else if($o_id->qty > 5 && $o_id->qty <=15 ){
            $message_data['body'][] = [ 'message' => 'ZWING Packing Zone 5' , 'bold_flag' => true ];
            $message_data['body'][] = [ 'message' => 'near Aisle 5'];
        }else{
            $message_data['body'][] = [ 'message' => 'ZWING Express Counter' , 'bold_flag' => true , 'italic_flag' => true ];
            $message_data['body'][] = [ 'message' => 'for packing'];
        }



        return response()->json(['status' => 'pre_verify_screen', 'message' => 'Order Details Details', 'data' => $message_data]);


    }

    public function order_details(Request $request)
    {
        $v_id = $request->v_id;
        $store_id = $request->store_id; 
        $order_id = $request->order_id; 

		$vu_id = 0;
        if($request->has('vu_id')){
            $vu_id = $request->vu_id;
        }else if($request->has('c_id')){
            $c_id = $request->c_id;
        }

        $stores =  DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();

        $o_id = Order::where('order_id', $order_id)->where('v_id', $v_id)->where('store_id', $store_id)->get();
        
        if($vu_id > 0){
            $o_id = $o_id->where('vu_id', $vu_id)->first();
        }else{
            $o_id = $o_id->where('user_id', $c_id)->first();
        }
		
		$c_id = $o_id->user_id;
		
        $order_num_id = Order::where('order_id', $order_id)->first();

        $return_request = DB::table('return_request')->where('order_id', $order_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('confirm','1')->get();

        //dd($return_request);

        $return_item_ids = [];
        if(!$return_request->isEmpty()){
            $return_item_ids = $return_request->pluck('item_id')->all();
        }

        $cart_data = array();
        $return_req_process = array();
        $return_req_approved = array();
        $cart_data = array();
        $product_data = [];
        $tax_total = 0;
		$cart_qty_total =  0;
        
        $carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $o_id->o_id)->get();
        $sub_total = $carts->sum('subtotal');
        $discount  = $carts->sum('discount');
        $employee_discount = $carts->sum('employee_discount');
        $total     = $carts->sum('total');
        $tax_total = $carts->sum('tax');
        $bill_buster_discount = 0;
        $tax_details = [];

        foreach ($carts as $key => $cart) {


            $res = DB::table('cart_offers')->where('cart_id',$cart->cart_id)->first();
            $offer_data = json_decode($res->offers, true);
            
            foreach ($offer_data['pdata'] as $key => $value) {
                foreach($value['tax'] as $nkey => $tax){
                    if(isset($tax_details[$tax['tax_code']])){
                        $tax_details[$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
                        $tax_details[$tax['tax_code']]['tax'] += $tax['tax'] ;
                    }else{
                        $tax_details[$tax['tax_code']] = $tax;
                    }
                    
                }
                
            }

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

            $request = [];
            $return_flag = false;
            $return_qty = 0;
            if(in_array($cart->item_id, $return_item_ids)){
                $request = $return_request->where('item_id', $cart->item_id);   

                foreach($request as $req){
                    if($req->status == 'approved'){
                        $return_qty += $req->qty;
                    }

                    if($req->status == 'process'){
                        $return_flag = true;
                       
                    }
                }

            }

            $product_data['return_flag'] = $return_flag;
            $product_data['return_qty'] = (string)$return_qty;
            $product_data['carry_bag_flag'] = $carry_bag_flag;
            $product_data['isProductReturn'] = ($cart->transaction_type == 'return')?true:false;
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
            $product_data['r_price'] = format_number($offer_data['r_price']);
            $product_data['s_price'] = format_number($offer_data['s_price']);
            $product_data['unit_mrp'] = format_number($cart->unit_mrp);
            /*if(!empty($offer_data['applied_offers']) ){
                $product_data['r_price'] = format_number($offer_data['r_price']);
                $product_data['s_price'] = format_number($offer_data['s_price']);
            }*/

            $product_data['varient'] = '';
            $product_data['images'] = 'zwing_default.png';
            $product_data['description'] = '';
            $product_data['deparment'] = '';
            $product_data['barcode'] = $cart->barcode;

            //$tax_total = $tax_total +  $tax_amount ;
            $tax_amount = $cart->tax;
            if($cart->weight_flag == '1'){
                $cart_qty_total =  $cart_qty_total + 1;
            }else{

                if($cart->plu_barcode){
                    $cart_plu_qty = $cart->qty;
                    $cart_plu_qty = explode('.',$cart_plu_qty);
                    //dd($cart_plu_qty);
                    if(count($cart_plu_qty) > 1 ){
                        $cart_qty_total =  $cart_qty_total + 1;
                    }else{
                        $cart_qty_total =  $cart_qty_total + $cart->qty;    
                    }
                }else{
                    $cart_qty_total =  $cart_qty_total + $cart->qty; 
                }   
            }
            
           
            $cart_data[] = array(
                    'cart_id'       => $cart->cart_id,
                    'product_data'  => $product_data,
                    'amount'        => $cart->total ,
                    'qty'           => $cart->qty,
                    'return_product_qty' => $cart->qty,
                    'tax_amount'    => $tax_amount,
                    'delivery'      => $cart->delivery,
                    'item_flag'     => 'NORMAL'
            );
            //$tax_total = $tax_total +  $tax_amount ;

            //This code is added for displayin andy return items
            if(in_array($cart->item_id, $return_item_ids)){
                
                //dd($request);
                foreach($request as $req){
                    $product_data['r_price'] = format_number($req->subtotal);
                    $product_data['s_price'] = format_number($req->total);

                    if($req->status == 'process'){

                        $return_req_process[] = array(
                            'cart_id'       => $cart->cart_id,
                            'product_data'  => $product_data,
                            'amount'        => $req->total ,
                            'qty'           => $req->qty,
                            //'return_product_qty' => $cart->qty,
                            'tax_amount'    => $req->tax,
                            'delivery'      => $cart->delivery,
                            'item_flag'     => 'RETURN_PROCESS'
                        );
                    }

                    if($req->status == 'approved'){

                        $return_req_approved[] = array(
                            'cart_id'       => $cart->cart_id,
                            'product_data'  => $product_data,
                            'amount'        => $req->total ,
                            'qty'           => $req->qty,
                            //'return_product_qty' => $cart->qty,
                            'tax_amount'    => $req->tax,
                            'delivery'      => $cart->delivery,
                            'item_flag'     => 'RETURN_APPROVED'
                        );
                    }

                }
            }
        }

        if($employee_discount > 0.00){
                $total = $total - $employee_discount;
            }
        $bill_buster_discount = $o_id->bill_buster_discount;
        if($bill_buster_discount > 0.00){
            $total = $total - $bill_buster_discount;
        }
        $saving = $discount + $bill_buster_discount;

        $bags = DB::table('user_carry_bags')->select('vendor_carry_bags.Name','user_carry_bags.Qty','vendor_carry_bags.BAG_ID')->selectRaw('user_carry_bags.Qty * vendor_carry_bags.Price as Price')->leftJoin('vendor_carry_bags', 'user_carry_bags.Bag_ID', '=', 'vendor_carry_bags.BAG_ID')->where('user_carry_bags.V_ID', $v_id)->where('user_carry_bags.Store_ID', $store_id)->where('user_carry_bags.User_ID', $c_id)->where('user_carry_bags.Order_ID', $order_num_id['o_id'])->get();
        $bprice = DB::table('user_carry_bags')->selectRaw('SUM(user_carry_bags.Qty * vendor_carry_bags.Price) as Price')->leftJoin('vendor_carry_bags', 'user_carry_bags.Bag_ID', '=', 'vendor_carry_bags.BAG_ID')->where('user_carry_bags.V_ID', $v_id)->where('user_carry_bags.Store_ID', $store_id)->where('user_carry_bags.User_ID', $c_id)->where('user_carry_bags.Order_ID', $order_num_id['o_id'])->first();
        // $cart_data['bags'] = $bags;
        
        if(empty($bprice->Price)) {
            $carry_bag_total = 0;
        } else {
            $carry_bag_total = $bprice->Price;
        }
        $store = DB::table('stores')->select('delivery')->where('store_id', $store_id)->where('v_id', $v_id)->first();
        //$total = (int)$sub_total + (int)$carry_bag_total;
        //$less = array_sum($saving) - (int)$sub_total;
		$address = (object)array();
		if($o_id->address_id > 0){
			$address = Address::where('c_id', $c_id)->where('deleted_status', 0)->where('id',$o_id->address_id)->first();
		}

		$paymentMethod = Payment::where('v_id', $o_id->v_id)->where('store_id',$o_id->store_id)->where('order_id',$o_id->order_id)->get()->pluck('method')->all() ;
		
        return response()->json(['status' => 'order_details', 'message' => 'Order Details Details', 
            'mobile' => $o_id->user->mobile,
            'payment_method'=>  implode(',',$paymentMethod),
			'data' => $cart_data,
            'return_req_process' => $return_req_process,
            'return_req_approved' => $return_req_approved,
            'product_image_link' => product_image_link(),
            'store_header_logo' => store_logo_link().'spar_logo_round.png',
            //'offer_data' => $global_offer_data,
            'return_request_flag' => ($return_request)?true:false,
            'bags' => $bags, 
            'carry_bag_total' => (format_number($carry_bag_total))?format_number($carry_bag_total):'0.00',
            'sub_total' => (format_number($sub_total))?format_number($sub_total):'0.00', 
            'tax_total' => (format_number($tax_total))?format_number($tax_total):'0.00',
            'tax_details' => $tax_details,
            'bill_buster_discount' => (format_number($bill_buster_discount))?format_number($bill_buster_discount):'0.00',
            'discount' => (format_number($discount))?format_number($discount):'0.00', 
            //'grand_total' => (format_number($grand_total))?format_number($grand_total):'0.00', 
            'date' => $o_id->date, 
            'time' => $o_id->time,
            'order_id' => $order_id, 
            'total' => format_number($total), 
            'cart_qty_total' => (string)$cart_qty_total,
            'saving' => (format_number($saving))?format_number($saving):'0.00',
            'store_address' => $stores->address1.' '.$stores->address2.' '.$stores->state.' - '.$stores->pincode,
            'store_timings' => $stores->opening_time.' '.$stores->closing_time,
            'delivered' => $store->delivery , 
            'address'=> $address,
            'c_id' => $c_id ],200);

    }
    
    public function order_verify_status(Request $request){
        
        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id; 
        $order_id = $request->order_id; 
		
		$vu_id = 0;
        if($request->has('vu_id')){
            $vu_id = $request->vu_id;
        }

        $order = Order::select('order_id', 'v_id' , 'store_id', 'date','time','total','verify_status','verify_status_guard')->where('user_id',$c_id)->where('order_id', $order_id)->where('v_id',$v_id)->where('store_id',$store_id)->first();

        if($vu_id > 0){
            $order->verify_status = '1';
            $order->verify_status_guard = '1';    
            $order->save();
        }
		
		$message ='';
        if($order->verify_status !='1'){    
            $message = 'Verification is pending, Please visit to nearest staff for verification.';
        }else{
            $message = 'verification successfully';
        }

        $verification_data = [
            'cashier_verify_status' => ($order->verify_status == '1')?true:false ,
            'guard_verify_status' => ($order->verify_status_guard == '1')?true:false,
             'order_id' => $order->order_id,
            'amount' => $order->total,
             'v_id' => $order->v_id,
             'store_id' =>  $order->store_id,
             'date' => $order->date,
             'time' => $order->time ];

        return response()->json(['status' => 'verification', 'message' => $message ,'verification' => $verification_data ], 200);
    }

    public function get_print_receipt(Request $request){
        
        $v_id = $request->v_id;
        $store_id = $request->store_id; 
        $c_id = $request->c_id;
        $order_id = $request->order_id;
		
		$trans_from = '';
        if($request->has('trans_from')){
           $trans_from = $request->trans_from; 
        }

        $stores =  DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();
        $order = Order::where('order_id', $order_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->first();
        $carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order->o_id)->get();
        $user = User::select('first_name','last_name', 'mobile')->where('c_id',$c_id)->first();

        $store_db_name = $stores->store_db_name;

        $subtotal = 0.00;
        $total = 0.00;
        $total_qty =0;
        $item_discount = 0.00;
        $counter =0;
        $tax_details = [];
        $tax_details_data = [];
        $cart_item_text ='';
        $tax_item_text = '';
        $param = [];
        $params = [];
        $tax_category_arr = [ 'A','B', 'C','D' ,'E','F' ,'G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V'];
        $tax_code_inc = 0;
        $cart_tax_code = [];

        $print_data_array = [];


        foreach ($carts as $key => $cart) {

            $counter++;
            $total += $cart->total;
            $subtotal += $cart->subtotal;
            $item_discount += $cart->discount;
            $total_qty += $cart->qty;
            $tax_category = '';
           
            $cart_tax_code_msg = '';

            $loopQty = $cart->qty;
            while($loopQty > 0){
               $param[] = $cart->total / $cart->qty; 
               $params[] = ['item_id' => $cart->item_id , 'price' => $cart->total / $cart->qty ];
               $loopQty--;
            }
            

            $res = DB::table('cart_offers')->where('cart_id',$cart->cart_id)->first();
            $offer_data = json_decode($res->offers, true);
            $item_master = DB::table($store_db_name.'.item_master')->where('ITEM',$cart->item_id)->first();
            $res = DB::table('cart_offers')->where('cart_id',$cart->cart_id)->first();
            $offer_data = json_decode($res->offers, true);
            $hsn_code = '';
            /*if(isset($offer_data['hsn_code'])){
                $hsn_code = $offer_data['hsn_code'];
            }*/
            if(isset($item_master->HSN) && $item_master->HSN != ''){
                $hsn_code = $item_master->HSN;
            }
            foreach ($offer_data['pdata'] as $key => $value) {
                $tax_details_data[$cart->item_id] = ['tax' =>  $value['tax'] , 'total' => $value['ex_price'] ];

                /*foreach($value['tax'] as $nkey => $tax){
                    if(isset($tax_details[$tax['tax_code']])){
                        $tax_details[$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
                        $tax_details[$tax['tax_code']]['tax'] += $tax['tax'] ;
                    }else{
                        $tax_details[$tax['tax_code']] = $tax;
                        
                    }
                    
                }*/

                if(empty($value['tax']) ){

                    if(isset($tax_details[00][00])){
                        $cart_tax_code_msg .= $cart_tax_code[00][00];
                        $cart_tax_code_msg .= $cart_tax_code[00][01];
                    }else{

                        $tax_details[00][00] = [ "tax_category" => "0",
                          "tax_desc" => "CGST_00_RC",
                          "tax_code" => "0",
                          "tax_rate" => "0",
                          "taxable_factor" => "0",
                          "taxable_amount" => $cart->total,
                          "tax" => 0.00 ] ;

                        $cart_tax_code_msg .= $tax_category_arr[$tax_code_inc];
                        $cart_tax_code[00][00] = $tax_category_arr[$tax_code_inc];
                        $tax_code_inc++;

                        $tax_details[00][01] = [ "tax_category" => "0",
                          "tax_desc" => "SGST_00_RC",
                          "tax_code" => "0",
                          "tax_rate" => "0",
                          "taxable_factor" => "0",
                          "taxable_amount" => $cart->total,
                          "tax" => 0.00 ] ;
                        $cart_tax_code_msg .= $tax_category_arr[$tax_code_inc];
                        $cart_tax_code[00][01] = $tax_category_arr[$tax_code_inc];
                        $tax_code_inc++;
                    }

                }else{
                    
                    foreach($value['tax'] as $nkey => $tax){
                        $tax_category = $tax['tax_category'];
                        if(isset($tax_details[$tax_category][$tax['tax_code']])){
                            $tax_details[$tax_category][$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
                            $tax_details[$tax_category][$tax['tax_code']]['tax'] += $tax['tax'] ;
                            $cart_tax_code_msg .= $cart_tax_code[$tax_category][$tax['tax_code']];
                        }else{
                            $tax_details[$tax_category][$tax['tax_code']] = $tax;
                            $cart_tax_code_msg .= $tax_category_arr[$tax_code_inc];
                            $cart_tax_code[$tax_category][$tax['tax_code']] = $tax_category_arr[$tax_code_inc];
                            $tax_code_inc++;
                            
                        }
                        
                    }
                }
               break; 
            }

            
            $cart_item_arr[] = ['row' => '1' , 'counter' => $counter , 'hsn_code' => $hsn_code , 'cart_item_name' => substr($cart->item_name,0, 20 ) , 'tax_category_name' => $cart_tax_code_msg ];
            $cart_item_arr[] = ['row' => '2' , 'qty' =>$cart->qty , 'unit_mrp' => format_number($cart->unit_mrp) , 'discount' => format_number($cart->discount / $cart->qty) , 'total' => $cart->total ];
            

           
            $ref_order_id = '';
            if( $order->transaction_type == 'return'){
               $ref_order_id = $order->ref_order_id;   
            }
                      


        }

       

        $transaction_type = $order->transaction_type;
        $employee_discount = '';
        $employee_details = [];
        if($order->employee_discount > 0.00){
            $total = $total - $order->employee_discount;

            $employee_discount = format_number($order->employee_discount);
            $emp_d = DB::table($v_id.'_employee_details')->where('employee_id', $order->employee_id)->first();
            $employee_details = [ 
                'employee_name' => $emp_d->first_name.' '.$emp_d->last_name,
                'company_name' => $emp_d->company_name,
                'employee_id' => (string)$order->employee_id,
                'available_discount' => (string)$order->employee_available_discount

                ];
        }

        


        $bill_buster_discount = '';
        if($order->bill_buster_discount > 0){
            $total = $total - $order->bill_buster_discount;

            $bill_buster_discount = format_number($order->bill_buster_discount);
            
            //Recalcualting taxes when bill buster is applied
            $promo_c = new PromotionController(['store_db_name' => $store_db_name]);
            $tax_details =[];
            $ratio_val = $promo_c->get_offer_amount_by_ratio($param, $order->bill_buster_discount);
            $ratio_total = array_sum($ratio_val);

            $discount = 0;
            $total_discount = 0;
            //dd($param);
            foreach($params as $key => $par){
                $discount = round( ($ratio_val[$key]/$ratio_total) * $order->bill_buster_discount , 2);
                $params[$key]['discount'] =  $discount;
                $total_discount += $discount;
            }
            //dd($params);
            //echo $total_discount;exit;
            //Thid code is added because facing issue when rounding of discount value
            if($total_discount > $order->bill_buster_discount){
                $total_diff = $total_discount - $order->bill_buster_discount;
                foreach($params as $key => $par){
                    if($total_diff > 0.00){
                        $params[$key]['discount'] -= 0.01;
                        $total_diff -= 0.01;
                    }else{
                        break;
                    }
                }
            }else if($total_discount < $order->bill_buster_discount){
                $total_diff =  $order->bill_buster_discount - $total_discount;
                foreach($params as $key => $par){
                    if($total_diff > 0.00){
                        $params[$key]['discount'] += 0.01;
                        $total_diff -= 0.01;
                    }else{
                        break;
                    }
                }
            }
            //dd($params);
            foreach($params as $key => $para){
                $discount = $para['discount'];  
                $item_id = $para['item_id'] ;
                // $tax_details_data[$key]
                foreach($tax_details_data[$item_id]['tax'] as $nkey => $tax){
                    $tax_category = $tax['tax_category'];
                    $taxable_total = $para['price'] - $discount;
                    $tax['taxable_amount'] = round( $taxable_total * $tax['taxable_factor'] , 2 );
                    $tax['tax'] =  round( ($tax['taxable_amount'] * $tax['tax_rate']) /100 , 2 );
                    //$tax_total += $tax['tax'];
                    if(isset($tax_details[$tax_category][$tax['tax_code']])){
                        $tax_details[$tax_category][$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
                        $tax_details[$tax_category][$tax['tax_code']]['tax'] += $tax['tax'] ;
                    }else{
                        
                        $tax_details[$tax_category][$tax['tax_code']] = $tax;
                    }

                }
            }

        }

        

        //dd($tax_details_data);

        $total_saving = '';
        if(($item_discount + $order->bill_buster_discount) > 0){
           $total_saving = format_number($item_discount+ $order->bill_buster_discount);

        }

        

        $tax_counter =0;
        $total_tax = 0;
        $tax_item_arr = [];
        //dd($tax_details);
        foreach($tax_details as $tax_category){
            foreach($tax_category as $tax){
                
                $total_tax += $tax['tax'];
                $tax_item_arr[] = [ 
                    'tax_desc' => $tax_category_arr[$tax_counter].'  '.substr($tax['tax_desc'],0,-2).' ('.$tax['tax_rate'].'%) ',
                    'taxable_amount' => format_number($tax['taxable_amount']),
                    'tax' => format_number($tax['tax'])
                     ];
                
                $tax_counter++;
            }
        }


        //$rounded =  round($total);
        $rounded =  $total;
        $rounded_off =  $rounded - $total;

        

        $zwing_online = '';
        $store_credit = '';
        $voucher_no = '';
		$voucher_total =0;
        $voucher_applied_list = [];
        $lapse_voucher_amount = 0;
        $bill_voucher_amount = 0;
        if($order->transaction_type == 'sales')
        {
            $payments = Payment::where('v_id',$v_id)->where('store_id',$store_id)->where('user_id',$c_id)->where('order_id', $order_id)->get();
            if($payments){

                foreach($payments as $payment){
                    if($payment->method == 'zwing_credit'){
                        $vouchers = DB::table('voucher_applied as va')
                                        ->join('voucher as v', 'v.id' , 'va.voucher_id')
                                        ->select('v.voucher_no', 'v.amount')
                                        ->where('va.v_id' , $v_id)->where('va.store_id' ,$store_id)
                                        ->where('va.user_id' , $c_id)->where('va.order_id' , $order->o_id)->get();
                        $voucher_total = 0;
                        foreach($vouchers as $voucher){
                            $voucher_total += $voucher->amount;
                            $voucher_applied_list[] = [ 'voucher_code' =>$voucher->voucher_no , 'voucher_amount' => format_number($voucher->amount) ] ;
                        }

                        if($voucher_total > $total){
                            
                            $lapse_voucher_amount = $voucher_total - $total;
                            $bill_voucher_amount =  $total ;
                            

                        }else{

                            $bill_voucher_amount =  $voucher_total ;
                        }

                    }else{
                        $zwing_online = format_number($payment->amount);
                    }
                }
                

            }else{
                return response()->json([ 'status'=>'fail', 'message'=> 'Payment is not processed' ], 200);
            }

        }else{
            $voucher = DB::table('voucher')->where('ref_id', $order->ref_order_id)->where('user_id',$order->user_id)->first();
            if($voucher){

                $store_credit = format_number($rounded);
                $voucher_no  = $voucher->voucher_no;

            }

        }
		
		$print_bill_attempt = 0;
        $print_bill_attempt =  DB::table('operation_verification_log')->where('v_id',$v_id)->where('store_id',$store_id)->where('c_id',$c_id)->where('order_id', $order_id)->where('operation','BILL_REPRINT')->where('trans_from', $trans_from)->whereRaw('date(created_at)', date('Y-m-d'))->count();

        $print_data_array['reprint_bill_attempt'] = $print_bill_attempt;

        $print_data_array['store_name'] = 'ZWING HYPERMARKET INDIA PVT LTD';
        $print_data_array['header'] = $stores->address1.' \n'.$stores->address2.' \n'.$stores->city.' - '.$stores->pincode.'\n'.'GSTIN - '.$stores->gst.'\n'.'TIN - '.$stores->tin.''.'\n'.'Helpline - '.$stores->helpline.''.'\n'.'Store Timing - '.$stores->opening_time.' To '.$stores->closing_time.'\n'.'EMAIL - customer@gozwing.com';
        $print_data_array['name'] = $user->first_name.' '.$user->last_name;
        $print_data_array['mobile'] = $user->mobile;

        $print_data_array['cart_item'] = $cart_item_arr;
        $print_data_array['ref_order_id'] = $ref_order_id;
        $print_data_array['subtotal'] = format_number($subtotal);
        $print_data_array['discount'] = format_number($item_discount);
        $print_data_array['employee_discount'] = $employee_discount;
        $print_data_array['bill_buster_discount'] = $bill_buster_discount;
        $print_data_array['total_amount'] = format_number($total);
        $print_data_array['total_rounded'] = format_number($rounded);
        $print_data_array['total_rounded_off'] = format_number($rounded_off);

		$print_data_array['voucher_total'] = format_number($voucher_total);

        $print_data_array['voucher_applied_list'] = $voucher_applied_list;
        $print_data_array['lapse_voucher_amount'] = format_number($lapse_voucher_amount);
        $print_data_array['bill_voucher_amount'] = format_number($bill_voucher_amount);
        $print_data_array['zwing_online'] = $zwing_online;
        $print_data_array['store_credit'] = $store_credit;
        $print_data_array['voucher_no'] = $voucher_no;

        $print_data_array['total_tender'] = format_number($rounded);
        $print_data_array['change_due'] = '0.00';
        $print_data_array['total_number_item_qty'] = $counter.'/'.$total_qty;

        $print_data_array['employee_details'] = $employee_details;
        

        $print_data_array['total_saving'] = $total_saving;
        $print_data_array['total_qty'] = (string)$total_qty;
        $print_data_array['tax_item_arr'] = $tax_item_arr;
        $print_data_array['total_tax'] = format_number($total_tax);

        $print_data_array['thank_you_msg'] = 'THANK YOU !!! DO VISIT AGAIN'.'\n'.'E&OE'.'\n'.'FOR EXCHANGE POLICY'.'\n'.'PLEASE SEE THE REVERSE OF THE BILL'.'\n'.'GENERATED BY ZWING';

        $print_data_array['tax_invoice_msg'] = 'Tax Invoice/Bill Of Supply - '.strtoupper($transaction_type).'\n'.$order->order_id;

        $print_data_array['date_time'] = date('H:i:s d-M-Y', strtotime($order->created_at));

    

        return response()->json(['status' => 'success' , 'data' => $print_data_array ],200);


        //dd($print_data_array);
    }
	
	public function get_duplicate_receipt(Request $request){
        
        $vu_id = $request->vu_id;
        $v_id = $request->v_id;
        $store_id = $request->store_id; 
        $security_code_vu_id = $request->security_code_vu_id;
        //$c_id = $request->c_id;
        //$order_id = $request->order_id;
        $cust_mobile_no = $request->cust_mobile_no;
        $trans_from = $request->trans_from;
        $operation = $request->operation;

        $user = User::select('c_id', 'mobile')->where('mobile',$cust_mobile_no)->first();
        if($user){

            $today_date = date('Y-m-d');
            $order = Order::where('user_id', $user->c_id)->where('status','success')->orderBy('od_id' , 'desc')->where('date', $today_date)->where('trans_from', $trans_from)->first();

            if($order){

               // dd(date('Y-m-d H:i:s'));

                DB::table('operation_verification_log')->insert([ 'v_id' => $v_id, 'store_id' => $store_id, 'c_id' =>$user->c_id, 'trans_from' => $trans_from, 'vu_id' =>$vu_id ,'operation' => $operation , 'order_id' => $order->order_id , 'verify_by' =>  $security_code_vu_id , 'created_at' => date('Y-m-d H:i:s') ]);

                $request->request->add(['c_id' => $user->c_id , 'order_id' => $order->order_id]);
                return $this->get_print_receipt($request);

            }else{
                return response()->json(['status'=> 'fail' , 'message' => 'Unbale to found any order which has been placed today'] , 200);
            }
        }else{
            return response()->json(['status'=> 'fail' , 'message' => 'Customer not exists'] , 200);
        }

    }
	
	public function order_receipt($c_id,$v_id , $store_id, $order_id){
        
        $stores =  DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();
        $order = Order::where('order_id', $order_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->first();
		
		$return_sign = '';
        if($order->transaction_type == 'return'){
            $return_sign = '-';
        }
		
        $carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order->o_id)->get();
        $user = User::select('first_name','last_name', 'mobile')->where('c_id',$c_id)->first();

        $store_db_name = $stores->store_db_name;

        $total = 0.00;
        $total_qty =0;
        $item_discount = 0.00;
        $counter =0;
        $tax_details = [];
        $tax_details_data = [];
        $cart_item_text ='';
        $tax_item_text = '';
        $param = [];
        $params = [];
        $tax_category_arr = [ 'A','B', 'C','D' ,'E','F' ,'G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V'];
        $tax_code_inc = 0;
        $cart_tax_code = [];

        foreach ($carts as $key => $cart) {

            $counter++;
            $total += $cart->total;
            $item_discount += $cart->discount;
            $total_qty += $cart->qty;
            $tax_category = '';
           
            $cart_tax_code_msg = '';

            $loopQty = $cart->qty;
            while($loopQty > 0){
               $param[] = $cart->total / $cart->qty; 
               $params[] = ['item_id' => $cart->item_id , 'price' => $cart->total / $cart->qty ];
               $loopQty--;
            }
            
			if($order->transaction_type == 'sales'){
                $res = DB::table('cart_offers')->where('cart_id',$cart->cart_id)->first();
                $offer_data = json_decode($res->offers, true);
            }else if($order->transaction_type == 'return'){

                $ref_order = Order::select('o_id')->where('order_id', $order->ref_order_id)->where('v_id',$v_id)->where('store_id',$store_id)->where('user_id',$c_id)->first();
                $ref_cart = Cart::select('cart_id')->where('v_id',$v_id)->where('store_id',$store_id)->where('user_id',$c_id)->where('order_id' , $ref_order->o_id)->where('item_id' , $cart->item_id)->first();
                $res = DB::table('cart_offers')->where('cart_id',$ref_cart->cart_id)->first();
                $offer_data = json_decode($res->offers, true);
            }
			
			$item_master = DB::table($store_db_name.'.item_master')->where('ITEM',$cart->item_id)->first();
           
            $hsn_code = '';
            /*if(isset($offer_data['hsn_code'])){
                $hsn_code = $offer_data['hsn_code'];
            }*/
            if(isset($item_master->HSN) && $item_master->HSN != ''){
                $hsn_code = $item_master->HSN;
            }
            foreach ($offer_data['pdata'] as $key => $value) {
                $tax_details_data[$cart->item_id] = ['tax' =>  $value['tax'] , 'total' => $value['ex_price'] ];

                /*foreach($value['tax'] as $nkey => $tax){
                    if(isset($tax_details[$tax['tax_code']])){
                        $tax_details[$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
                        $tax_details[$tax['tax_code']]['tax'] += $tax['tax'] ;
                    }else{
                        $tax_details[$tax['tax_code']] = $tax;
                        
                    }
                    
                }*/

                if(empty($value['tax']) ){

                    if(isset($tax_details[00][00])){
                        $cart_tax_code_msg .= $cart_tax_code[00][00];
                        $cart_tax_code_msg .= $cart_tax_code[00][01];
                    }else{

                        $tax_details[00][00] = [ "tax_category" => "0",
                          "tax_desc" => "CGST_00_RC",
                          "tax_code" => "0",
                          "tax_rate" => "0",
                          "taxable_factor" => "0",
                          "taxable_amount" => $cart->total,
                          "tax" => 0.00 ] ;

                        $cart_tax_code_msg .= $tax_category_arr[$tax_code_inc];
                        $cart_tax_code[00][00] = $tax_category_arr[$tax_code_inc];
                        $tax_code_inc++;

                        $tax_details[00][01] = [ "tax_category" => "0",
                          "tax_desc" => "SGST_00_RC",
                          "tax_code" => "0",
                          "tax_rate" => "0",
                          "taxable_factor" => "0",
                          "taxable_amount" => $cart->total,
                          "tax" => 0.00 ] ;
                        $cart_tax_code_msg .= $tax_category_arr[$tax_code_inc];
                        $cart_tax_code[00][01] = $tax_category_arr[$tax_code_inc];
                        $tax_code_inc++;
                    }

                }else{
                    
                    foreach($value['tax'] as $nkey => $tax){
                        $tax_category = $tax['tax_category'];
                        if(isset($tax_details[$tax_category][$tax['tax_code']])){
                            $tax_details[$tax_category][$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
                            $tax_details[$tax_category][$tax['tax_code']]['tax'] += $tax['tax'] ;
                            $cart_tax_code_msg .= $cart_tax_code[$tax_category][$tax['tax_code']];
                        }else{
                            $tax_details[$tax_category][$tax['tax_code']] = $tax;
                            $cart_tax_code_msg .= $tax_category_arr[$tax_code_inc];
                            $cart_tax_code[$tax_category][$tax['tax_code']] = $tax_category_arr[$tax_code_inc];
                            $tax_code_inc++;
                            
                        }
                        
                    }
                }
                break;
            }

            //$cart_item_arr[] = ['hsn_code' => $hsn_code , 'item_name' => $cart->item_name , 'unit_mrp' => $cart->unit_mrp, 'qty' => $cart->qty , 'discount' => $cart->discount , 'total' => $cart->total , 'tax_category' => $tax_category ]; 
            
           $cart_item_text .=
             '<tr class="td-center">
                <td colspan="3" style="text-align:left">'.$counter."&nbsp;&nbsp;&nbsp;".$hsn_code.'   '.substr($cart->item_name, 0,20).'</td>
                <td>'.$cart_tax_code_msg.'</td>
    
            </tr>
            <tr class="td-center">
                <td style="padding-left:20px;text-align:left">'.$cart->qty.'</td>
                <td> '.format_number($cart->unit_mrp).'</td>
                <td>'.format_number($cart->discount / $cart->qty).'</td>
                <td>'.$return_sign.$cart->total.'</td>
            </tr>';

        }
		
		if( $order->transaction_type == 'return'){
		   $cart_item_text .=
			 '<tr class="td-center">
				<td colspan="3" style="text-align:left">&nbsp;&nbsp;&nbsp; Orig. Receipt: '.$order->ref_order_id.'</td>
				<td></td>
	
			</tr>';   
		}
        //dd($tax_details);
        $transaction_type = $order->transaction_type;
        $employee_discount_text = '';
        $employee_details = '';
        if($order->employee_discount > 0.00){
            $total = $total - $order->employee_discount;
            $employee_discount_text .=
            '<tr>
                <td colspan="3">Employee Discount</td> 
                <td> -'.format_number($order->employee_discount).'</td>
            </tr>';

            $emp_d = DB::table($v_id.'_employee_details')->where('employee_id', $order->employee_id)->first();
            $employee_details .=
            '<div style="text-align:left;line-height: 0.4;padding-top:10px">
                <p>EMPLOYEE NAME : '.$emp_d->first_name.' '.$emp_d->last_name.'</p>
                <p>COMPANY NAME : '.$emp_d->company_name.'</p>
                <p>ID : '.$order->employee_id.'</p>
                <p>AVAILABLE AMOUNT : '.$order->employee_available_discount.' </p>
            </div>';
        }

        $bill_buster_discount_text = '';
        if($order->bill_buster_discount > 0){
            $total = $total - $order->bill_buster_discount;
            $bill_buster_discount_text .=
            '<tr>
                <td colspan="3">Bill Buster</td> 
                <td> -'.format_number($order->bill_buster_discount).'</td>
            </tr>';

            //Recalcualting taxes when bill buster is applied
            $promo_c = new PromotionController(['store_db_name' => $store_db_name]);
            $tax_details =[];
            $ratio_val = $promo_c->get_offer_amount_by_ratio($param, $order->bill_buster_discount);
            $ratio_total = array_sum($ratio_val);

            $discount = 0;
            $total_discount = 0;
            //dd($param);
            foreach($params as $key => $par){
                $discount = round( ($ratio_val[$key]/$ratio_total) * $order->bill_buster_discount , 2);
                $params[$key]['discount'] =  $discount;
                $total_discount += $discount;
            }
            //dd($params);
            //echo $total_discount;exit;
            //Thid code is added because facing issue when rounding of discount value
            if($total_discount > $order->bill_buster_discount){
                $total_diff = $total_discount - $order->bill_buster_discount;
                foreach($params as $key => $par){
                    if($total_diff > 0.00){
                        $params[$key]['discount'] -= 0.01;
                        $total_diff -= 0.01;
                    }else{
                        break;
                    }
                }
            }else if($total_discount < $order->bill_buster_discount){
                $total_diff =  $order->bill_buster_discount - $total_discount;
                foreach($params as $key => $par){
                    if($total_diff > 0.00){
                        $params[$key]['discount'] += 0.01;
                        $total_diff -= 0.01;
                    }else{
                        break;
                    }
                }
            }
            //dd($params);
            foreach($params as $key => $para){
                $discount = $para['discount'];  
                $item_id = $para['item_id'] ;
                // $tax_details_data[$key]
                foreach($tax_details_data[$item_id]['tax'] as $nkey => $tax){
                    $tax_category = $tax['tax_category'];
                    $taxable_total = $para['price'] - $discount;
                    $tax['taxable_amount'] = round( $taxable_total * $tax['taxable_factor'] , 2 );
                    $tax['tax'] =  round( ($tax['taxable_amount'] * $tax['tax_rate']) /100 , 2 );
                    //$tax_total += $tax['tax'];
                    if(isset($tax_details[$tax_category][$tax['tax_code']])){
                        $tax_details[$tax_category][$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
                        $tax_details[$tax_category][$tax['tax_code']]['tax'] += $tax['tax'] ;
                    }else{
                        
                        $tax_details[$tax_category][$tax['tax_code']] = $tax;
                    }

                }
            }

        }

        //dd($tax_details_data);

        $discount_text = '';
        if(($item_discount + $order->bill_buster_discount) > 0){
           $discount_text = '<p>***TOTAL SAVING : Rs. '.format_number($item_discount+ $order->bill_buster_discount).' *** </p>';
        }

        $tax_counter =0;
        $total_tax = 0;
        //dd($tax_details);
        foreach($tax_details as $tax_category){
            foreach($tax_category as $tax){
                
                $total_tax += $tax['tax'];
                $tax_item_text .=
                 '<tr >
                    <td>'.$tax_category_arr[$tax_counter].'  '.substr($tax['tax_desc'],0,-2).' ('.$tax['tax_rate'].'%) '.'</td>
                    <td>'.format_number($tax['taxable_amount']).'</td>
                    <td>'.format_number($tax['tax']).'</td>
                </tr>';
                $tax_counter++;
            }
        }

        //$rounded =  round($total);
        $rounded =  $total;
        $rounded_off =  $rounded - $total;
        $transaction_type_msg = '';
        if($order->transaction_type == 'sales')
        {

            $payments = Payment::where('v_id',$v_id)->where('store_id',$store_id)->where('user_id',$c_id)->where('order_id', $order_id)->get();
            if($payments){

                foreach($payments as $payment){
                    if($payment->method != 'zwing_credit'){
                        $transaction_type_msg .= '<tr>
                        <td colspan="3">&nbsp;&nbsp; Zwing Online</td> 
                        <td>'.format_number($payment->amount).'</td>
                        </tr>';
                    }else{

                        $transaction_type_msg .= '<tr>
                        <td colspan="3">&nbsp;&nbsp; Credit Note </td> 
                        <td>'.format_number($payment->amount).'</td>
                        </tr>';
                    }
                }
                

            }else{
                return response()->json([ 'status'=>'fail', 'message'=> 'Payment is not processed' ], 200);
            }

        }else{
            $voucher = DB::table('voucher')->where('ref_id', $order->order_id)->where('user_id',$order->user_id)->first();
            if($voucher){

            
                $transaction_type_msg .= '<tr>
                        <td colspan="3">&nbsp;&nbsp; Store credit</td> 
                        <td> '.$return_sign.format_number($rounded).'</td>
                    </tr>
                    <tr>
                    <td></td>
                    <td colspan="3">Store Credit #: '.$voucher->voucher_no.'<td>
                    </tr>';
            }

        }
                    
        
        //dd($tax_details);
        $html = 
        '<!DOCTYPE html>
        <html>
            <head>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            </head>
            <title></title>
            <style type="text/css">
            .container {
                max-width : 400px;
                margin:auto;
                margin : auto;
               #font-family: Arial, Helvetica, sans-serif;
                font-family: courier, sans-serif;
                font-size: 14px;
            }
			.clearfix {
				clear: both;
			}

            body {
                background-color:#ffff;

            }

            table {
                width: 100%;
                font-size: 14px;
            }
            .td-center td 
            {
                text-align: center;
            }
            .invoice-address p {
                line-height: 0.6;
            }
            hr {
                border-top:1px dashed #000;
                border-bottom: none;
                
            }
            </style>
            <body>
                <div class="container">
                <center>
                    <div> 
                    <img style="float:left" src="'.store_logo_link().'Zwing-logo.png" >
					<img style="float:right;height:30px" src="'.env('APP_URL').'/images/zwing_header.png" >

					</div>
					<div class="clearfix"></div>
                    <p>ZWING HYPERMARKET INDIA PVT LTD</P>
                    <hr/>
                    <div class="invoice-address">
                        <p>'.$stores->address1.'</P>
                        <p>'.$stores->address2.'</P>
                        <p>'.$stores->city.' - '.$stores->pincode.'</P>
                        <p>GSTIN - '.$stores->gst.'</P>
                        <p>TIN - '.$stores->tin.'</P>
                        <p>Helpline - '.$stores->helpline.'</P>
                        <p>Store Timing - '.$stores->opening_time.' To '.$stores->closing_time.'</P>
                        <p>EMAIL - customer@gozwing.com</P>

                        
                    </div>
                    <hr/>
                    <div style="text-align:left;margin-top:10px">
                        <p>Name : '.$user->first_name.' '.$user->last_name.'</p>
                        <p>Mobile : '.$user->mobile.'</p>
                    </div>

                    <hr/>
                    <table>
                    
                    <tr class="td-center">
                        <td>HSN/ITEM</td>
                        <td>Rate</td>
                        <td>Disc</td>
                        <td>Amount TC</td>
                    </tr>
                    <tr>
                        <td>/QTY</td>
                        <td>(Rs./UNIT)</td>
                        <td>(Rs./UNIT)</td>
                        <td>(Inc.TAX)</td>
                    </tr>
                    </table>
                    <hr>
                    <table>
                    <tr class="td-center" style="line-height: 0;">
                        <td height="2">&nbsp;&nbsp;&nbsp;&nbsp;</td>
                        <td height="2">&nbsp;</td>
                        <td height="2">&nbsp;&nbsp;&nbsp;&nbsp;</td>
                        <td height="2">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                    </tr>

                   '.$cart_item_text.'
                    <tr>
                        <td colspan="4">&nbsp;</td>
                        
                    </tr>
                    '.$employee_discount_text.'
                    '.$bill_buster_discount_text.'
                    <tr>
                        <td colspan="3">Total Amount</td> 
                        <td>'.format_number($total).'</td>
                    </tr>
                    <tr><td>&nbsp;<td></tr>

                    <tr>
                        <td colspan="3">&nbsp;&nbsp; Total Rounded</td> 
                        <td>'.format_number($rounded).'</td>
                    </tr>
                    <tr><td>&nbsp;<td></tr>

                    <tr>
                        <td colspan="3">&nbsp;&nbsp; Rounded Off Amt</td> 
                        <td>'.format_number($rounded_off).'</td>
                    </tr>
                    <tr><td>&nbsp;<td></tr>
                    '.$transaction_type_msg.'
                    <tr><td>&nbsp;<td></tr>

                    <tr>
                        <td colspan="3">Total Tender</td> 
                        <td>'.$return_sign.format_number($rounded).'</td>
                    </tr>
                    <tr><td>&nbsp;<td></tr>

                    <tr>
                        <td colspan="3">&nbsp;&nbsp; Change Due</td> 
                        <td>0.00</td>
                    </tr>
                    <tr><td>&nbsp;<td></tr>
                    
                    <tr>
                        <td colspan="3">Total number of items/Qty</td> 
                        <td>'.$counter.'/'.$return_sign.$total_qty.'</td>
                    </tr>
                    </table>
                    '.$employee_details.'
                    '.$discount_text.'
                    <p>Tax Details</p>
                    
                    <table>
                    <tr>
                        
                        <td>Tax Desc</td>
                        <td>TAXABLE</td>
                        <td>Tax</td>
                    </tr>
                    '.$tax_item_text.'
                    <tr>
                        <td colspan="6">&nbsp;</td>
                        
                    </tr>
                    <tr>
                        <td colspan="2">Total tax value</td> 
                        <td>'.format_number($total_tax).'</td>
                    </tr>
                </table>
                <hr>
                <div class="invoice-address">
                    <p>THANK YOU !!! DO VISIT AGAIN<p>
                    <p>E&OE<p>
                    <p>FOR EXCHANGE POLICY<p>
                    <p>PLEASE REFER END OF THE BILL<p>
                    <p>&nbsp;</p>
                </div>
                <hr/>
                <p>Tax Invoice/Bill Of Supply - '.strtoupper($transaction_type).'<p>
                <p>'.$order->order_id.'</p>
                <p></p>
                <hr/>
                <p>'.date('H:i:s d-M-Y', strtotime($order->created_at)).'</p>
                <p>&nbsp;</p>
                <p>&nbsp;</p>
                <div style="text-align:left">
                <h3>Exchange Policy</h3>
                <p>At Zwing, our endeavor is to bring you Superior Quality
                Products at all times. If, for some reason you wish to
                exchange, we would be pleased to do so within 14 days from
                the date of purchase against submission of Original
                invoice to the same store.</p>
                
                <p>All Electric, Electronic, Luggage & Information
                Technology products shall be subject to manufacturer\'s
                warranty only and is not covered under this exchange
                policy. After sales service, wherever is applicable, will
                be provided by the authorized service centers of the
                respective manufacturers, based on their terms and
                conditions of warranty.</p>

                <p>For reasons of health & hygiene undergarments, personal
                care products, swimwear, socks, cosmetics, crockery,
                jewellery, frozen foods, dairy and bakery products, loose
                staples & dry fruits, fruits and vegetables, baby food,
                liquor, tobacco, over the counter medication (OTC) &
                Products of similar nature will not be exchanged.
                Exchange/refund will not be entertained on altered,
                damaged, used, discounted products and merchandise
                purchased on promotional sale.</p>

                <p>All products returned should be unused, undamaged and in
                saleable condition.
                Refund will be through a credit note for onetime use valid
                for 30 days from the date of issue to be redeemed in the
                same store. No duplicate credit note will be issued in
                lieu of damaged/lost/defaced/mutilated Credit Note/s.
                While our endeavor is to be flexible, in case of any
                dispute, the same shall be subject to Bengaluru
                jurisdiction only.</p>


                <div>
                </center>
                </div>
            </body>
        </html>';

        return $html;

    }

    public function rt_log(Request $request){
		try {
			
			$v_id = $request->v_id;
			$store_id = $request->store_id;
			$date = $request->date;
			$time = date('m/d/Y H:i:s');

			$stores =  DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();
			$orders = Order::where('v_id', $v_id)->where('store_id', $store_id)->where('date', $date)->where('transaction_type' ,'sales')->where('status','success')->get();
			//$carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order->o_id)->get();

			$store_db_name = $stores->store_db_name;

			$columns= ['STORE','BUSINESS_DATE','TRANSACTION_DATETIME','CASHIER','TRAN_TYPE','CUSTOMER_ORDER_NO','CUSTOMER_ORDER_DATE','TIC_NO','ORIG_TRAN_NO','ORIG_BUSINESS_DATE','TOTAL_REF','VALUE','ITEM_SEQ_NO','ITEM','QTY','UNIT_RETAIL','MRP','SELLING_UOM','RETURN_REASON_CODE','PROMO_TYPE','DISCOUNT_TYPE','DISCOUNT_AMOUNT','TAX_CODE_1','TAX_RATE_1','TAX_VALUE_1','TAX_CODE_2','TAX_RATE_2','TAX_VALUE_2','TAX_CODE_3','TAX_RATE_3','TAX_VALUE_3','TAX_CODE_4','TAX_RATE_4','TAX_VALUE_4','TAX_CODE_5','TAX_RATE_5','TAX_VALUE_5','TENDER_TYPE_GROUP','TENDER_TYPE_ID','AMOUNT','CREDIT_CARD_NUMBER','CREDIT_CARD_EXPIRY_DATE','COUPON_NO','COUPON_DESC','VOUCHER_NO'];




			$path =  storage_path();
			$file_name = 'Zwing-'.$stores->mapping_store_id.'-'.$date.'.csv';
			//$path_with_file_name = $path."/app/".$file_name;
			$path_with_file_name = '/home/sparut/ftp/'.env('RT_LOG_FOLDER').'/'.$stores->mapping_store_id
			.'/'.$file_name;
			$file = fopen($path_with_file_name,"w");

			 fputcsv($file,$columns);
			 //$cart_items = [];
			 $total = 0;
			 foreach ($orders as $key => $order) {
				$carts = Cart::where('user_id', $order->user_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order->o_id)->where('transaction_type', 'sales')->where('status','success')->get();
				$cart_counter = 0;
				foreach ($carts as $key => $cart) {
					$total += $cart->total;
					$res = DB::table('cart_offers')->where('cart_id',$cart->cart_id)->first();
					$offer_data = json_decode($res->offers, true);
					
					$tax_details = [];
					foreach ($offer_data['pdata'] as $key => $value) {
						foreach($value['tax'] as $nkey => $tax){
							if(isset($tax_details[$tax['tax_code']])){
								$tax_details[$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
								$tax_details[$tax['tax_code']]['tax'] += $tax['tax'] ;
							}else{
								$tax_details[$tax['tax_code']] = $tax;
							}
							
						}
						
					}

					$tax_details = array_values($tax_details);
					//dd($tax_details);

					$cart_counter++;
					$promo_type = '1004';
					$discount_type = 'ORRCAP';
					$discount = $cart->discount;
					if($cart->employee_discount > 0.00){
						$promo_type = '10044';
						$discount_type = 'ORRCAPP';
						$discount = $cart->employee_discount;
					}
					
					$cust = DB::table('customer_auth')->select('mobile')->where('c_id', $cart->user_id)->first();
					$tic_no = '';
					if($cust){
						$cust_d = DB::table($v_id.'_customer_details')->select('tic_no')->where('mobile', $cust->mobile)->first();
						
						if($cust_d){
							$tic_no = $cust_d->tic_no;
						}

					}


					$items = [
						$stores->mapping_store_id, 
						date('m/d/Y', strtotime($date) ),
						date('m/d/Y H:i:s', strtotime($order->created_at)),
						'M013303' ,
						'SALE',
						$order->order_id,
						date( 'm/d/Y H:i:s', strtotime($order->created_at)),
						$tic_no, //TIC_NO,
						'', //ORIG_TRAN_NO use when return happen
						'', //ORIG_BUSINESS_DATE USe when return happen
						'', //TOTAL_REF use when calculting total
						'', //VALUE
						$cart_counter, //ITEM_SEQ_NO
						$cart->item_id,
						$cart->qty,
						$cart->total / $cart->qty,
						$cart->unit_mrp,
						($cart->weight_flag)?'KG':'EA', //EA and KG
						'', //REturn REAson cdoe
						$promo_type , //Promo Type
						$discount_type, //Discount Type
						$discount
					];

					$items_index = count($items);
					foreach($tax_details as $tax){
						//$items_index++;
						$items[$items_index++] = $tax['tax_code'];
						$items[$items_index++] = $tax['tax_rate'];
						$items[$items_index++] = $tax['taxable_factor'];
						
					}

					if($items_index ==37){

					}else{
						while($items_index < 37){
							$items[$items_index++] = '';
						}
					}

					$items[$items_index++] = 'ZWING';//Tender group type
					$items[$items_index++] = '8888'; // Tender group id
					$items[$items_index++] = $cart->total;//Amount
					$items[$items_index++] = '';//Credit card no
					$items[$items_index++] = '';//Credit cart expiry date
					$items[$items_index++] = '';//coupon no
					$items[$items_index++] = '';//Coupon discount
					$items[$items_index++] = '';//Voucher no

					/*if($cart->employee_discount > 0.00){
						$emp_d = DB::table('spar_uat.employee_details')->where('employee_id',$cart->employee_id)->first();
						$items[$items_index++] = $cart->employee_id;//Employye_id
						$items[$items_index++] = $emp_d->company_name;//Employee company name
					}else{
						$items[$items_index++] = '';//Employye_id
						$items[$items_index++] = '';//Employee company name

					}*/
				
				   // $items[$items_index++] = 'ZWING';

					fputcsv($file,$items);
				}

			 }


			#### This entrry  for Return Items START #########
			$orders = Order::where('v_id', $v_id)->where('store_id', $store_id)->where('date', $date)->where('transaction_type' ,'return')->where('status','success')->get();
			foreach ($orders as $key => $order) {
				$carts = Cart::where('user_id', $order->user_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order->o_id)->where('transaction_type', 'return')->where('status','success')->get();
				$cart_counter = 0;
				foreach ($carts as $key => $cart) {
					$total += $cart->total;
					
					$ref_order = Order::where('v_id', $v_id)->where('store_id', $store_id)->where('date', $date)->where('transaction_type' ,'sales')->where('status','success')->where('order_id', $order->ref_order_id )->first();

					$cart_id = Cart::where('order_id', $ref_order->o_id)->where('item_id', $cart->item_id)->where('user_id', $cart->user_id)->where('store_id',$cart->store_id)->where('v_id', $cart->v_id)->where('transaction_type','sales')->first();
					if($cart_id){
					   $cart_id = $cart_id->cart_id; 
				   }else{
					echo $order->o_id.' '.$cart->item_id.' '.$cart->user_id;
					exit;
				   }
					
					$res = DB::table('cart_offers')->where('cart_id',$cart_id)->first();
					$offer_data = json_decode($res->offers, true);
					
					$tax_details = [];
					foreach ($offer_data['pdata'] as $key => $value) {
						foreach($value['tax'] as $nkey => $tax){
							if(isset($tax_details[$tax['tax_code']])){
								$tax_details[$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
								$tax_details[$tax['tax_code']]['tax'] += $tax['tax'] ;
							}else{
								$tax_details[$tax['tax_code']] = $tax;
							}
							
						}
						
					}

					$tax_details = array_values($tax_details);
					//dd($tax_details);
					$origin_order = Order::where('order_id', $order->order_id)->first();
					$cart_counter++;
					$promo_type = '1004';
					$discount_type = 'ORRCAP';
					$discount = $cart->discount;
					if($cart->employee_discount > 0.00){
						$promo_type = '10044';
						$discount_type = 'ORRCAPP';
						$discount = $cart->employee_discount;
					}

					$cust = DB::table('customer_auth')->select('mobile')->where('c_id', $cart->user_id)->first();
					$tic_no = '';
					if($cust){
						$cust_d = DB::table($v_id.'_customer_details')->select('tic_no')->where('mobile', $cust->mobile)->first();
						
						if($cust_d){
							$tic_no = $cust_d->tic_no;
						}

					}

					$items = [
						$stores->mapping_store_id, 
						date('m/d/Y', strtotime($date) ),
						date('m/d/Y H:i:s', strtotime($order->created_at)),
						'M013303' ,
						'RETURN',
						$order->order_id,
						date( 'm/d/Y H:i:s', strtotime($order->created_at)),
						$tic_no, //TIC_NO,
						$order->ref_order_id, //ORIG_TRAN_NO use when return happen
						date( 'm/d/Y', strtotime($origin_order->created_at)), //ORIG_BUSINESS_DATE USe when return happen
						'', //TOTAL_REF use when calculting total
						'', //VALUE
						$cart_counter, //ITEM_SEQ_NO
						$cart->item_id,
						$cart->qty,
						$cart->total / $cart->qty,
						$cart->unit_mrp,
						($cart->weight_flag)?'KG':'EA', //EA and KG
						$cart->return_code, //REturn REAson cdoe
						$promo_type , //Promo Type
						$discount_type, //Discount Type
						$discount
					];

					$items_index = count($items);
					foreach($tax_details as $tax){
						//$items_index++;
						$items[$items_index++] = $tax['tax_code'];
						$items[$items_index++] = $tax['tax_rate'];
						$items[$items_index++] = $tax['taxable_factor'];
						
					}

					if($items_index ==37){

					}else{
						while($items_index < 37){
							$items[$items_index++] = '';
						}
					}

					$items[$items_index++] = 'ZWING';//Tender group type
					$items[$items_index++] = '8888'; // Tender group id
					$items[$items_index++] = $cart->total;
					$items[$items_index++] = '';//Credit card no
					$items[$items_index++] = '';//Credit cart expiry date
					$items[$items_index++] = '';//coupon no
					$items[$items_index++] = '';//Coupon discount
					$items[$items_index++] = '';//Voucher no
				   // $items[$items_index++] = 'ZWING';



					fputcsv($file,$items);
				}

			}
			#### This Entry  for Return Items END #########


			$line = [
				$stores->mapping_store_id, 
				date('m/d/Y', strtotime($date) ),
				date('m/d/Y H:i:s', strtotime($time)),
				'M013303' ,
				'TOTAL',
				'',
				'',
				'', //TIC_NO,
				'', //ORIG_TRAN_NO use when return happen
				'', //ORIG_BUSINESS_DATE USe when return happen
				'CASH', //TOTAL_REF use when calculting total
				'0', //VALUE
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				''

			];

			fputcsv($file,$line);

			$line = [
				$stores->mapping_store_id, 
				date('m/d/Y', strtotime($date) ),
				date('m/d/Y H:i:s', strtotime($time)),
				'M013303' ,
				'TOTAL',
				'',
				'',
				'', //TIC_NO,
				'', //ORIG_TRAN_NO use when return happen
				'', //ORIG_BUSINESS_DATE USe when return happen
				'ZWING', //TOTAL_REF use when calculting total
				$total, //VALUE
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				''
			];

			 fputcsv($file,$line);

		   /* foreach ($list as $line)
			  {
			  fputcsv($file,explode(',',$line));
			  }*/

			fclose($file);
			
			$string = file_get_contents($path_with_file_name, FILE_USE_INCLUDE_PATH);
			$string2 = str_replace('"', "", $string);
			file_put_contents($path_with_file_name, $string2); 
		
		} catch(\Exception $e){

            Mail::raw( $e->getMessage(), function ($message) {
               $message->to('chandramani@roxfort.in')
                        ->subject('Rt-log Error')
                        ->cc('rishabh@roxfort.in')
                        ;
            });

            return ['status' => 'fail', 'message' => 'Error has Occured'];
        }
		
        return ['status' => 'success', 'message' => 'RT Log has been generated successfully'];

    }

    public function get_carry_bags(Request $request)
    {
        $v_id = $request->v_id;
        $store_id = $request->store_id; 
        $c_id = $request->c_id;
        $order_id = Order::where('user_id', Auth::user()->c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

        $store_db_name = get_store_db_name(['store_id' => $store_id]);
        //$carry_bags = DB::table('vendor_carry_bags')->select('BAG_ID','Name','Price')->where('V_ID', $v_id)->where('Store_ID', $store_id)->get();

        $carr_bag_arr =  [ '114903443', '114952448' ,'114974444'];
        $carry_bags = DB::table($store_db_name.'.price_master')->select('ITEM as BAG_ID','ITEM_DESC as Name', 'MRP1 as Price')->whereIn('ITEM', $carr_bag_arr)->get();

        $carts  = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

        $data = array();
        foreach ($carry_bags as $key => $value) {
            //$bags = DB::table('user_carry_bags')->select('Qty','Bag_ID')->where('V_ID', $v_id)->where('Store_ID', $store_id)->where('User_ID', Auth::user()->c_id)->where('Order_ID', $order_id)->where('Bag_ID', $value->BAG_ID)->first();
            $cart = $carts->where('item_id',$value->BAG_ID)->first();
            //$bags = 

            if(empty($cart)) {
                $data[] = array(
                        'BAG_ID' => $value->BAG_ID,
                        'Name' => $value->Name,
                        'Price' => $value->Price,
                        'Qty' => 0,
                );
            } else {
                if($value->BAG_ID == $cart->item_id) {
                    $data[] = array(
                            'BAG_ID' => $value->BAG_ID,
                            'Name' => $value->Name,
                            'Price' => $value->Price,
                            'Qty' => $cart->qty,
                    );
                } else {
                    $data[] = array(
                            'BAG_ID' => $value->BAG_ID,
                            'Name' => $value->Name,
                            'Price' => $value->Price,
                            'Qty' => 0,
                    );
                }
            }
            
            
        }
        //return response()->json(['status' => 'get_carry_bags_by_store', 'data' => $data ],200);
        return ['status' => 'get_carry_bags_by_store', 'data' => $data ];
    }

    public function save_carry_bags(Request $request)
    {
        //echo 'inside this';exit;
        $v_id = $request->v_id;
        $store_id = $request->store_id; 
        $c_id = $request->c_id; 
        //$order_id = $request->order_id; 
        $bags = $request->bags; 
	    $bags = json_decode($bags, true);
        //dd($bags);
        $store_db_name = get_store_db_name(['store_id' => $store_id]);

        $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

        $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

        foreach ($bags as $key => $value) {
            $exists = $carts->where('barcode', $value[0])->first();
            $price_master = DB::table($store_db_name.'.price_master')->where('ITEM', $value[0])->first();
            if($exists) {

                if($value[1] < 1 ){
                    $request->request->add(['cart_id' => $exists->cart_id]);
                    $this->remove_product($request);
                }else{
                    $request->request->add(['barcode' => $value[0] , 'qty' =>$value[1] , 'unit_mrp' => $price_master->MRP1  , 'r_price' => $price_master->MRP1 * $value[1] , 's_price' => $price_master->MRP1 * $value[1] , 'discount' => 0 , 'pdata' => '' ]);
                    $this->product_qty_update($request);
                }

                $status = '1';
            } else {

                if($value[1] > 0 ){
        

                    $request->request->add(['barcode' => $value[0] , 'qty' =>$value[1] , 'unit_mrp' => $price_master->MRP1  , 'r_price' => $price_master->MRP1 * $value[1] , 's_price' => $price_master->MRP1 * $value[1] , 'discount' => 0 , 'pdata' => '' ]);
                    $this->add_to_cart($request);
                }
                /*
                if(empty($value[1])) {
                    $update = DB::table('user_carry_bags')->where('V_ID', $v_id)->where('Store_ID', $store_id)->where('User_ID', $c_id)->where('Order_ID', $order_id)->where('Bag_ID', $value[0])->update(['Qty' => $value[1]]);
                    $delete = DB::table('user_carry_bags')->where('V_ID', $v_id)->where('Store_ID', $store_id)->where('User_ID', $c_id)->where('Order_ID', $order_id)->where('Bag_ID', $value[0])->delete();
                } else {
                    $delete = DB::table('user_carry_bags')->where('V_ID', $v_id)->where('Store_ID', $store_id)->where('User_ID', $c_id)->where('Order_ID', $order_id)->where('Bag_ID', $value[0])->update(['Qty' => $value[1]]);  
                }*/

                $status = '2';
            }
        }
        if($status == 1) {
            return response()->json(['status' => 'add_carry_bags', 'message' => 'Carry Bags Added'],200);
        } else {
            return response()->json(['status' => 'add_carry_bags', 'message' => 'Carry Bags Updated'],200);
        }
        //print_r($
        // $carry_bags = DB::table('vendor_carry_bags')->select('BAG_ID','Name','Price')->where('V_ID', $v_id)->where('Store_ID', $store_id)->get();
        // return response()->json(['status' => 'get_carry_bags_by_store', 'data' => print_r(expression)$bags ],200);
    }

    public function deliveryStatus(Request $request)
    {
        $c_id = $request->c_id;
        // $v_id = $request->v_id;
        // $store_id = $request->store_id;
        $cart_id = $request->cart_id;
        $status = $request->status;

        $cart = Cart::find($cart_id)->update([ 'delivery' => $status ]);

        return response()->json(['status' => 'delivery_status_update'],200);
    }

    public function process_each_item_in_cart($param){

        

        $v_id = $param['v_id'];
        $store_id = $param['store_id'];
        $c_id = $param['c_id'];
        $final_data = [];

        $store_db_name = get_store_db_name(['store_id' => $store_id]);
        $promo_c = new PromotionController(['store_db_name' => $store_db_name]);

        $employee_id = 0;
        $employee_available_discount = 0;
		$employee_company_name ='';

        $mapping_store_id =  DB::table('stores')->select('mapping_store_id')->where('store_id', $store_id)->first()->mapping_store_id;

        $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

        $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();
        
        $section_target_offer = [];
        $section_offers = [];
        $section_total =[];
        $cart_item = true;
        foreach ($carts as $key => $cart) {
            //dd($cart);
            $employee_id = $cart->employee_id;
            $item_master = DB::table($store_db_name.'.item_master')->where('EAN', $cart->barcode)->first();
            $price_master = DB::table($store_db_name.'.price_master')->where('ITEM', $item_master->ITEM)->first();

            $csp_arr = [];
            $mrp_arr = [];
            $mrp_arrs = array_filter( [ $price_master->MRP1, $price_master->MRP2 , $price_master->MRP3 ]  );
            $csp_arrs = array_filter( [ $price_master->CSP1, $price_master->CSP2 , $price_master->CSP3 ]  );

            foreach ($mrp_arrs as $key => $value) {
                if($value == 0 || $value ===null){

                }else{
                    $mrp_arr[] = format_number($value);
                }
            }

            foreach ($csp_arrs as $key => $value) {
                if($value == 0 || $value ===null){

                }else{
                    $csp_arr[] = format_number($value);
                }
            }

            $params = [ 'barcode' => $cart->barcode, 'qty' => $cart->qty, 'mrp' => $cart->unit_mrp, 'v_id' => $v_id , 'store_id' => $store_id, 'item_master' => $item_master , 'price_master' => $price_master , 'user_id' => $c_id  , 'cart_item' => $cart_item ,'carts' => $carts , 'mrp_arr' => $mrp_arr, 'csp_arr' => $csp_arr  ,'mapping_store_id' => $mapping_store_id];

            $final_data = $promo_c->process_individual_item($params);

            //dd($final_data);
            //If Offer not found
            if(!isset($final_data['pdata'])){
               
                $total = $cart->unit_mrp * $cart->qty;
                $ex_price = $total;
                foreach($mrp_arr as $key => $mr){
                    if($mr == $cart->unit_mrp){
                        if(isset($csp_arr[$key]) && $csp_arr[$key] > 0 ){
                           // echo 'finall inside';exit;
                           $ex_price = $csp_arr[$key] * $cart->qty; 
                        }else{
                            $ex_price = $total;
                        }
                        
                    }
                }
                $discount = $total - $ex_price ; 

                $final_data['pdata'][] = [ 'qty' => $cart->qty, 'mrp' => $cart->unit_mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total, 'message' => '' , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0];
                $final_data['available_offer'] = [];
                $final_data['applied_offer'] = [];
                $final_data['item_id'] = $price_master->ITEM;

            }else{

                if(empty($final_data['available_offer']) && empty($final_data['applied_offer'])){
                    //echo 'inside this';exit;
                    foreach($final_data['pdata'] as $key => $pdata){

                        $total = $pdata['mrp'] * $pdata['qty'];
                        $ex_price = $total;
                        foreach($mrp_arr as $key => $mr){
                            if($mr == $cart->unit_mrp){
                                if(isset($csp_arr[$key]) && $csp_arr[$key] > 0 ){
                                   // echo 'finall inside';exit;
                                   $ex_price = $csp_arr[$key] * $pdata['qty']; 
                                }else{
                                    $ex_price = $total;
                                }
                                
                            }
                        }

                        $discount = $total - $ex_price ; 

                        $final_data['pdata'][$key]['ex_price'] = $ex_price;
                        $final_data['pdata'][$key]['total_price'] =  $total;
                        $final_data['pdata'][$key]['discount'] = $discount;
                    }

                    
                }

            }

            $total_mrp = 0;
            $total_amount = 0;
            $total_discount = 0;
            $total_price = 0;
            $total_qty = 0;
            $is_slab = 0;
            $total_csp = 0;
            $total_tax= 0;
            $tax_region = DB::table($store_db_name.'.tax_regions')->where('store_id',$mapping_store_id)->first();
            $tax_rates = DB::table($store_db_name.'.tax_rate')->where('ID_CTGY_TX', $item_master->TAX_CATEGORY)->where('ID_RN_FM_TX',$tax_region->region_from)->where('ID_RN_TO_TX', $tax_region->region_to)->get();
            foreach ($final_data['pdata'] as $key => $value) {
                
                $total_mrp += $value['mrp'];
                $total_price += $value['total_price'];
                $total_amount += $value['ex_price'];
                $total_discount += (float)$value['discount'];
                $total_qty += $value['qty'];
                $is_slab += $value['is_slab'];


                $taxes =[];
                foreach ($tax_rates as $tkey => $tax_rate) {

                    $taxable_amount = round ( ($value['ex_price'] / $value['qty']) * $tax_rate->TXBL_FCT , 2 );
                    $tax = round( ( $taxable_amount / 100  ) * $tax_rate->TX_RT , 2 );
                    $taxable_amount = $taxable_amount * $value['qty'];
                    $tax = $tax * $value['qty'];
                    $taxes[] = [ 'tax_category' => $tax_rate->ID_CTGY_TX , 'tax_desc' => $tax_rate->TX_CD_DSCR , 'tax_code' => $tax_rate->TX_CD , 'tax_rate' => $tax_rate->TX_RT , 'taxable_factor' => $tax_rate->TXBL_FCT , 'taxable_amount' => $taxable_amount , 'tax' => $tax ];

                    $total_tax += $tax;
                }

                $final_data['pdata'][$key]['tax'] = $taxes;

                //This condition is added for if offer not available
                /*foreach($mrp_arr as $key => $mr){
                    if($mr == $cart->unit_mrp){
                        if(isset($csp_arr[$key]) && $csp_arr[$key] > 0 ){
                           $total_csp += $csp_arr[$key] * $value['qty']; 
                        }else{
                            $total_csp = $total_price;
                        }
                        
                    }
                }*/
            }

           //if(!empty($final_data['available_offer']) ){
                $final_data['r_price'] = $total_price ;
                $final_data['s_price'] = $total_amount ;
            /*}else{
                $final_data['r_price'] = $total_price ;
                $final_data['s_price'] = $total_csp ;
            }*/

            $final_data['total_qty']= $total_qty;
            $final_data['total_discount'] = $total_discount;
            $final_data['total_tax'] = $total_tax;

            //dd($final_data);

            $url = json_encode($final_data);
            $data = json_decode($url);
            $cart->weight_flag = ($price_master->WEIGHT_FLAG == 'YES')?'1':'0';
            $cart->item_name= $price_master->ITEM_DESC;
            $cart->unit_mrp = $cart->unit_mrp;
            $cart->qty = $cart->qty;
            $cart->subtotal = $final_data['r_price'];
            $cart->discount = $total_discount;
            $cart->total    = $final_data['s_price'];
            $cart->tax      = $total_tax;
            $cart->slab     = ($is_slab == 0 ? 'No' : 'Yes');
            $cart->save();
            //$cart_update = DB::table('cart')->where('cart_id', $cart->cart_id)->update(['item_name' => $price_master->ITEM_DESC,  'unit_mrp' =>$cart->unit_mrp, 'qty' => $cart->qty, 'subtotal' => $final_data['r_price'] , 'discount' => $total_discount, 'total' => $final_data['s_price'], 'tax' => $total_tax,   'slab' => ($is_slab == 0 ? 'No' : 'Yes') ]);

            DB::table('cart_details')->where('cart_id', $cart->cart_id)->delete();
            DB::table('cart_offers')->where('cart_id', $cart->cart_id)->delete();

            DB::table('cart_offers')->insert([
                'cart_id' => $cart->cart_id,
                'offers' => $url
            ]);

        
            foreach ($data as $key => $val) {
                if ($key == 'pdata') {
                    foreach ($val as $key => $value) {
                        $cart_details = DB::table('cart_details')->insert([
                            'cart_id' => $cart->cart_id,
                            'qty' => $value->qty,
                            'mrp' => $value->mrp,
                            'price' => $value->total_price,
                            'discount' => $value->discount,
                            'ext_price' => $value->ex_price,
                            'tax' => '',
                            'message' => $value->message,
                            'ru_prdv' => $value->ru_prdv,
                            'type' => $value->type,
                            'type_id' => $value->type_id,
                            'promo_id' => $value->promo_id,
                            'is_promo' => $value->is_promo,
                            'taxes' => json_encode($value->tax)
                        ]);
                    }
                }
            }

            //dd($cart);

            //Section Offers Starts
            $sOffers = $cart->section_target_offers;
            if($sOffers !='' && !empty($sOffers) ){
                $off = json_decode($sOffers);
                //echo '<pre>';print_r($off->department);exit;
                if(!empty($off->department) ){
                    if(isset($section_total['department'][$cart->department_id])){
                        $section_total['department'][$cart->department_id]['total'] += $cart->total;
                    }else{
                        $section_total['department'][$cart->department_id]['total'] = $cart->total;
                        $section_target_offer['department'][$cart->department_id] = $off->department;
                    }
                    
                }
               
                if(!empty($off->subclass) ){
                    if(isset($section_total['subclass'][$cart->subclass_id])){
                        $section_total['subclass'][$cart->subclass_id]['total'] += $cart->total;
                    }else{
                        $section_total['subclass'][$cart->subclass_id]['total'] = $cart->total;
                        $section_target_offer['subclass'][$cart->subclass_id] = $off->subclass;
                        
                    }
                    
                }

                if(!empty($off->printclass) ){
                    if(isset($section_total['printclass'][$cart->printclass_id])){
                        $section_total['printclass'][$cart->printclass_id]['total'] += $cart->total;
                    }else{
                        $section_total['printclass'][$cart->printclass_id]['total'] = $cart->total;
                        $section_target_offer['printclass'][$cart->printclass_id] = $off->printclass;
                        
                    }
                    
                }


                if(!empty($off->group) ){
                    if(isset($section_total['group'][$cart->group_id])){
                        $section_total['group'][$cart->group_id]['total'] += $cart->total;
                    }else{
                        $section_total['group'][$cart->group_id]['total'] = $cart->total;
                        $section_target_offer['group'][$cart->group_id] = $off->group;
                        
                    }
                    
                }


                if(!empty($off->division) ){
                    if(isset($section_total['division'][$cart->division_id])){
                        $section_total['division'][$cart->division_id]['total'] += $cart->total;
                    }else{
                        $section_total['division'][$cart->division_id]['total'] = $cart->total;
                        $section_target_offer['division'][$cart->division_id] = $off->division;
                    }
                    
                }
            }



            //Section Offers Without Target Starts
            
            $sOffers = $cart->section_offers;
            if($sOffers !='' && !empty($sOffers) ){
                $off = json_decode($sOffers);

                if(!empty($off->department) ){
                    //dd($off->printclass->first());
                    if(isset($section_total['department'][$cart->department_id])){
                    }else{
                        foreach($off->department as  $key => $val){
                            $section_offers['department'][$cart->department_id] = $val;
                        }
                        
                    }
                    
                }


                if(!empty($off->subclass) ){
                    //dd($off->printclass->first());
                    if(isset($section_total['subclass'][$cart->subclass_id])){
                    }else{
                        foreach($off->subclass as  $key => $val){
                            $section_offers['subclass'][$cart->subclass_id] = $val;
                        }
                        
                    }
                    
                }

                if(!empty($off->printclass) ){
                    //dd($off->printclass->first());
                    if(isset($section_total['printclass'][$cart->printclass_id])){
                    }else{
                        foreach($off->printclass as  $key => $val){
                            $section_offers['printclass'][$cart->printclass_id] = $val;
                        }
                        
                    }
                    
                }


                if(!empty($off->group) ){
                    //dd($off->printclass->first());
                    if(isset($section_total['group'][$cart->group_id])){
                    }else{
                        foreach($off->group as  $key => $val){
                            $section_offers['group'][$cart->group_id] = $val;
                        }
                        
                    }
                    
                }

                if(!empty($off->division) ){
                    //dd($off->printclass->first());
                    if(isset($section_total['division'][$cart->division_id])){
                    }else{
                        foreach($off->division as  $key => $val){
                            $section_offers['division'][$cart->division_id] = $val;
                        }
                        
                    }
                    
                }

            }

        }
        //dd($final_data);
        //Called again because need updated data of cart
        $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

        $target_item_list   = $carts->pluck('item_id')->all();
        //dd( $section_target_offer );
        $s_final_data = [];
        foreach($section_target_offer as $level => $target_offers){
            //$target_offer = (arr$target_offer;
            //dd($target_offers);
            foreach ($target_offers as $section_id => $section_offer) {
                    //dd($target_offer);
                foreach($section_offer as $key => $target_offer){
                    $offer_type = key($target_offer);
                    //dd($offer_type);
                    if(in_array($key , $target_item_list)){
                       // dd($target_offer[$source_item->ITEM]);
                        

                            $cart_single_item = $carts->where('item_id', $key)->first();

                            //dd($cart_single_item);
                            $param = ['carts' => $carts, 'source_item' => $cart_single_item->item_id , 'offer' => $target_offer->$offer_type,
                                    'qty' =>  $cart_single_item->qty, 'mrp' =>  $cart_single_item->unit_mrp ,  'store_id' => $store_id , 'user_id' => $c_id , 'item_desc' => $cart_single_item->item_name , 'section_total' => $section_total , 'cart_item' => true  ] ;

                        
                        if($offer_type == 'BuyRsNOrMoreOfXGetYatZ%OffTiered'){
                            $s_final_data[$level][$section_id][] = $promo_c->calculate_shop_target_offer_of_percentage_tiered($param);
                        }elseif($offer_type == 'Buy$NorMoreOfXgetYatZ$'){
                            $s_final_data[$level][$section_id][] = $promo_c->calculate_shop_target_offer_of_fixed_price($param);
                        }elseif($offer_type == 'Buy$NorMoreOfXgetYatZ%off'){
                            $s_final_data[$level][$section_id][] = $promo_c->calculate_shop_target_offer_of_percentage($param);
                        }elseif($offer_type == 'BuyRsNOrMoreOfXGetYatZRsOffTiered'){
                            $s_final_data[$level][$section_id][] = $promo_c->calculate_shop_target_offer_of_amount_tiered($param);
                        }elseif($offer_type == 'BuyRsNOrMoreOfXGetYatZRsTiered'){
                            $s_final_data[$level][$section_id][] = $promo_c->calculate_shop_target_offer_of_fixed_price_tiered($param);
                        }elseif($offer_type == 'Buy$NorMoreOfXgetYatZ$off'){
                            $s_final_data[$level][$section_id][] = $promo_c->calculate_shop_target_offer_of_amount($param);
                        }

                        
                    }

                }
            }
        }

        //dd($section_total);
        //dd($s_final_data);
        $final_datas = [];
        if(count($s_final_data) > 0 ){
            //Finding the best Section Offers
            foreach ($s_final_data as $level => $levels) {
                foreach ($levels as $section_id => $section) {
                    $best_dis = 0;
                    foreach($section as $key => $final_d){
                        if( $final_d['pdata'][0]['discount'] > $best_dis ){
                            $final_datas[$level][$section_id] = $section[$key];
                            $best_dis = $final_d['pdata'][0]['discount'];
                        }
                    }
                }
            }  
            //dd($s_final_data);
        }


        foreach ($final_datas as $level => $levels) {
            foreach ($levels as $section_id => $section) {
                //if($level == 'department' && $section_id == $item_master->ID_MRHRC_GP_PRNT_DEPT){
                    $final_data = $section;

                //}
                $item_master = DB::table($store_db_name.'.item_master')->where('ITEM', $final_data['item_id'])->first();
                $price_master = DB::table($store_db_name.'.price_master')->where('ITEM', $final_data['item_id'])->first();
                $cart = $carts->where('item_id', $final_data['item_id'])->first();

                $csp_arr = [];
                $mrp_arr = [];
                $mrp_arrs = array_filter( [ $price_master->MRP1, $price_master->MRP2 , $price_master->MRP3 ]  );
                $csp_arrs = array_filter( [ $price_master->CSP1, $price_master->CSP2 , $price_master->CSP3 ]  );

                foreach ($mrp_arrs as $key => $value) {
                    if($value == 0 || $value ===null){

                    }else{
                        $mrp_arr[] = format_number($value);
                    }
                }

                foreach ($csp_arrs as $key => $value) {
                    if($value == 0 || $value ===null){

                    }else{
                        $csp_arr[] = format_number($value);
                    }
                }

                if(empty($final_data['available_offer']) && empty($final_data['applied_offer'])){
                    //echo 'inside this';exit;
                    foreach($final_data['pdata'] as $key => $pdata){

                        $total = $pdata['mrp'] * $pdata['qty'];
                        $ex_price = $total;
                        foreach($mrp_arr as $key => $mr){
                            if($mr == $cart->unit_mrp){
                                if(isset($csp_arr[$key]) && $csp_arr[$key] > 0 ){
                                   // echo 'finall inside';exit;
                                   $ex_price = $csp_arr[$key] * $pdata['qty']; 
                                }else{
                                    $ex_price = $total;
                                }
                                
                            }
                        }

                        $discount = $total - $ex_price ; 

                        $final_data['pdata'][$key]['ex_price'] = $ex_price;
                        $final_data['pdata'][$key]['total_price'] =  $total;
                        $final_data['pdata'][$key]['discount'] = $discount;
                    }
   
                }


                $total_mrp = 0;
                $total_amount = 0;
                $total_price =0;
                $total_discount = 0;
                $total_qty = 0;
                $is_slab = 0;
                $total_csp = 0;
                $total_tax = 0;
                $tax_region = DB::table($store_db_name.'.tax_regions')->where('store_id',$mapping_store_id)->first();
                $tax_rates = DB::table($store_db_name.'.tax_rate')->where('ID_CTGY_TX', $item_master->TAX_CATEGORY)->where('ID_RN_FM_TX',$tax_region->region_from)->where('ID_RN_TO_TX',$tax_region->region_from)->get();
                foreach ($final_data['pdata'] as $key => $value) {
                    
                    $total_mrp += $value['mrp'];
                    $total_amount += $value['ex_price'];
                    $total_price += $value['total_price'];
                    $total_discount += (float)$value['discount'];
                    $total_qty += $value['qty'];
                    $is_slab += $value['is_slab'];

                    $taxes =[];
                    foreach ($tax_rates as $tkey => $tax_rate) {

                        $taxable_amount = round ( ($value['ex_price'] / $value['qty']) * $tax_rate->TXBL_FCT , 2 );
                        $tax = round( ( $taxable_amount / 100  ) * $tax_rate->TX_RT , 2 );
                        $taxable_amount = $taxable_amount * $value['qty'];
                        $tax = $tax * $value['qty'];
                        $taxes[] = [ 'tax_category' => $tax_rate->ID_CTGY_TX , 'tax_desc' => $tax_rate->TX_CD_DSCR , 'tax_code' => $tax_rate->TX_CD , 'tax_rate' => $tax_rate->TX_RT , 'taxable_factor' => $tax_rate->TXBL_FCT , 'taxable_amount' => $taxable_amount , 'tax' => $tax ];
                        $total_tax += $tax;
                    }

                    $final_data['pdata'][$key]['tax'] = $taxes;

                    /*foreach($mrp_arr as $key => $mr){
                        if($mr == $cart->unit_mrp){
                            if(isset($csp_arr[$key]) && $csp_arr[$key] > 0 ){
                               $total_csp += $csp_arr[$key] * $value['qty']; 
                            }else{
                                $total_csp = $total_price;
                            }
                            
                        }
                    }*/
                }

                //if(!empty($final_data['available_offer']) ){
                    $final_data['r_price'] = $total_price ;
                    $final_data['s_price'] = $total_amount ;

                /*}else{

                    $final_data['r_price'] = $total_price ;
                    $final_data['s_price'] = $total_csp ;

                }*/

                $final_data['total_qty']= $total_qty;
                $final_data['total_discount'] = $total_discount;
                $final_data['total_tax'] = $total_tax;

                $final_data['multiple_price_flag'] =  ( count( $mrp_arrs) > 1 )? true:false;
                $final_data['multiple_mrp'] = $mrp_arrs;

                $url = json_encode($final_data);
                $data = json_decode($url);

                $cart_update = DB::table('cart')->where('cart_id', $cart->cart_id)->update(['unit_mrp' =>$cart->unit_mrp, 'qty' => $cart->qty, 'subtotal' => $final_data['r_price'] , 'discount' => $total_discount, 'total' => $final_data['s_price'], 'tax' => $total_tax,  'slab' => ($is_slab == 0 ? 'No' : 'Yes') ]);

                DB::table('cart_details')->where('cart_id', $cart->cart_id)->delete();
                DB::table('cart_offers')->where('cart_id', $cart->cart_id)->delete();

                DB::table('cart_offers')->insert([
                    'cart_id' => $cart->cart_id,
                    'offers' => $url
                ]);

                foreach ($data as $key => $val) {
                    if ($key == 'pdata') {
                        foreach ($val as $key => $value) {
                            $cart_details = DB::table('cart_details')->insert([
                                'cart_id' => $cart->cart_id,
                                'qty' => $value->qty,
                                'mrp' => $value->mrp,
                                'price' => $value->total_price,
                                'discount' => $value->discount,
                                'ext_price' => $value->ex_price,
                                'tax' => '',
                                'message' => $value->message,
                                'ru_prdv' => $value->ru_prdv,
                                'type' => $value->type,
                                'type_id' => $value->type_id,
                                'promo_id' => $value->promo_id,
                                'is_promo' => $value->is_promo,
                                'taxes' => json_encode($value->tax)
                            ]);
                        }
                    }
                }


            }
        }
        //dd($final_data);

        //dd($carts);
         //Section offer without Target
        //dd($section_offers);
        foreach($section_offers as $level => $section_offer){
            //$target_offer = (arr$target_offer;
            //dd($target_offers);
            foreach ($section_offer as $section_id => $offers) {
                    //dd($target_offer);
                foreach($offers as $key => $offer){
                    //dd($offer);
                   // $offer = json_decode(json_encode($offer));
                    $section_carts= $carts->where($level.'_id',$section_id);
                    foreach($section_carts as $cart){

                        $param = ['carts' => $carts, 'source_item' => $cart->item_id, 'offer' => $offer,
                                    'qty' => $cart->qty, 'mrp' =>$cart->unit_mrp ,  'store_id' => $store_id , 'user_id' => $c_id , 'item_desc' => '' , 'section_total' => [] , 'cart_item' => true] ;
                        
                        if($key == 'Buy$NofXatZ%offTiered'){
                            $final_data = $promo_c->calculate_shop_offer_of_percentage_tiered($param);
                        }

                        //dd($final_data);
                        $item_master = DB::table($store_db_name.'.item_master')->where('ITEM', $final_data['item_id'])->first();
                        $price_master = DB::table($store_db_name.'.price_master')->where('ITEM', $final_data['item_id'])->first();
                        

                        $csp_arr = [];
                        $mrp_arr = [];
                        $mrp_arrs = array_filter( [ $price_master->MRP1, $price_master->MRP2 , $price_master->MRP3 ]  );
                        $csp_arrs = array_filter( [ $price_master->CSP1, $price_master->CSP2 , $price_master->CSP3 ]  );

                        foreach ($mrp_arrs as $key => $value) {
                            if($value == 0 || $value ===null){

                            }else{
                                $mrp_arr[] = format_number($value);
                            }
                        }

                        foreach ($csp_arrs as $key => $value) {
                            if($value == 0 || $value ===null){

                            }else{
                                $csp_arr[] = format_number($value);
                            }
                        }


                        if(empty($final_data['available_offer']) && empty($final_data['applied_offer'])){
                        //echo 'inside this';exit;
                            foreach($final_data['pdata'] as $key => $pdata){

                                $total = $pdata['mrp'] * $pdata['qty'];
                                $ex_price = $total;
                                foreach($mrp_arr as $key => $mr){
                                    if($mr == $cart->unit_mrp){
                                        if(isset($csp_arr[$key]) && $csp_arr[$key] > 0 ){
                                           // echo 'finall inside';exit;
                                           $ex_price = $csp_arr[$key] * $pdata['qty']; 
                                        }else{
                                            $ex_price = $total;
                                        }
                                        
                                    }
                                }

                                $discount = $total - $ex_price ; 

                                $final_data['pdata'][$key]['ex_price'] = $ex_price;
                                $final_data['pdata'][$key]['total_price'] =  $total;
                                $final_data['pdata'][$key]['discount'] = $discount;
                            }
           
                        }


                        $total_mrp = 0;
                        $total_amount = 0;
                        $total_price =0;
                        $total_discount = 0;
                        $total_qty = 0;
                        $is_slab = 0;
                        $total_csp = 0;
                        $total_tax = 0;
                        $tax_region = DB::table($store_db_name.'.tax_regions')->where('store_id',$mapping_store_id)->first();
                        $tax_rates = DB::table($store_db_name.'.tax_rate')->where('ID_CTGY_TX', $item_master->TAX_CATEGORY)->where('ID_RN_FM_TX', $tax_region->region_from)->where('ID_RN_TO_TX',$tax_region->region_to)->get();
                        foreach ($final_data['pdata'] as $key => $value) {
                            
                            $total_mrp += $value['mrp'];
                            $total_amount += $value['ex_price'];
                            $total_price += $value['total_price'];
                            $total_discount += (float)$value['discount'];
                            $total_qty += $value['qty'];
                            $is_slab += $value['is_slab'];

                            $taxes =[];
                            foreach ($tax_rates as $tkey => $tax_rate) {

                                $taxable_amount = round ( ($value['ex_price'] / $value['qty']) * $tax_rate->TXBL_FCT , 2 );
                                $tax = round( ( $taxable_amount / 100  ) * $tax_rate->TX_RT , 2 );
                                $taxable_amount = $taxable_amount * $value['qty'];
                                $tax = $tax * $value['qty'];
                                $taxes[] = [ 'tax_category' => $tax_rate->ID_CTGY_TX , 'tax_desc' => $tax_rate->TX_CD_DSCR , 'tax_code' => $tax_rate->TX_CD , 'tax_rate' => $tax_rate->TX_RT , 'taxable_factor' => $tax_rate->TXBL_FCT , 'taxable_amount' => $taxable_amount , 'tax' => $tax ];
                                $total_tax += $tax;
                            }

                            $final_data['pdata'][$key]['tax'] = $taxes;

                            /*foreach($mrp_arr as $key => $mr){
                                if($mr == $cart->unit_mrp){
                                    if(isset($csp_arr[$key]) && $csp_arr[$key] > 0 ){
                                       $total_csp += $csp_arr[$key] * $value['qty']; 
                                    }else{
                                        $total_csp = $total_price;
                                    }
                                    
                                }
                            }*/
                        }

                        //if(!empty($final_data['available_offer']) ){
                            $final_data['r_price'] = $total_price ;
                            $final_data['s_price'] = $total_amount ;

                        /*}else{

                            $final_data['r_price'] = $total_price ;
                            $final_data['s_price'] = $total_csp ;

                        }*/

                        $final_data['total_qty']= $total_qty;
                        $final_data['total_discount'] = $total_discount;
                        $final_data['total_tax'] = $total_tax;

                        $final_data['multiple_price_flag'] =  ( count( $mrp_arrs) > 1 )? true:false;
                        $final_data['multiple_mrp'] = $mrp_arrs;

                        $url = json_encode($final_data);
                        $data = json_decode($url);

                        $cart_update = DB::table('cart')->where('cart_id', $cart->cart_id)->update(['unit_mrp' =>$cart->unit_mrp, 'qty' => $cart->qty, 'subtotal' => $final_data['r_price'] , 'discount' => $total_discount, 'total' => $final_data['s_price'], 'tax' => $total_tax,  'slab' => ($is_slab == 0 ? 'No' : 'Yes') ]);

                        DB::table('cart_details')->where('cart_id', $cart->cart_id)->delete();
                        DB::table('cart_offers')->where('cart_id', $cart->cart_id)->delete();

                        DB::table('cart_offers')->insert([
                            'cart_id' => $cart->cart_id,
                            'offers' => $url
                        ]);

                        foreach ($data as $key => $val) {
                            if ($key == 'pdata') {
                                foreach ($val as $key => $value) {
                                    $cart_details = DB::table('cart_details')->insert([
                                        'cart_id' => $cart->cart_id,
                                        'qty' => $value->qty,
                                        'mrp' => $value->mrp,
                                        'price' => $value->total_price,
                                        'discount' => $value->discount,
                                        'ext_price' => $value->ex_price,
                                        'tax' => '',
                                        'message' => $value->message,
                                        'ru_prdv' => $value->ru_prdv,
                                        'type' => $value->type,
                                        'type_id' => $value->type_id,
                                        'promo_id' => $value->promo_id,
                                        'is_promo' => $value->is_promo,
                                        'taxes' => json_encode($value->tax)
                                    ]);
                                }
                            }
                        }


                    }

                }

            }

        }
  
        ###### Employee Discount START ######
        if(isset($param['employee_available_discount'])){
            $employee_id = $param['employee_id'];
            $employee_available_discount = $param['employee_available_discount'];
			$employee_company_name = $param['company_name'];
        }elseif ($employee_id!=0) {
            $emp_details = DB::table($v_id.'_employee_details')->where('employee_id', $employee_id)->first();

            $employee_available_discount = $emp_details->available_discount;
			$employee_company_name = $emp_details->company_name;
        }

    
    
        $cartss = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();
        if($employee_available_discount > 0.00 ){

            //Called again because need updated data of cart
            
            $carts = $cartss->where('discount','=','0.00');
            //dd($carts);
            if(count($carts)>0){
                $cart_total = 0;
                $param = [];
                $params = [];
                foreach($carts as $cart){
                    
                    $cart_total += $cart->total;
                    $loopQty = $cart->qty;
                    
                    while($loopQty > 0){
                        $params[] = [ 'item_id' => $cart->item_id, 'unit_mrp' => $cart->total / $cart->qty ];
                        $param[] = $cart->total / $cart->qty;

                        $loopQty--;
                    }
                }

                if($cart_total < $employee_available_discount){
                    //echo 'insdie this';exit;
                    $percentage = 0;
                    $company_discount = DB::select('percentage')->table($store_db_name.'.company_discount')->where('company_name', $employee_company_name)->first();
                    if($company_discount){
                        $percentage = $company_discount->percentage; 
                    }
                    if($percentage ==0){
                        $offer_amount = ($cart_total * $percentage )/100;
                    }else{
                        $offer_amount = 0;
                    }
                    //dd($param);

                    $ratio_val = $promo_c->get_offer_amount_by_ratio($param, $offer_amount);
                    //dd($ratio_val);
                    $ratio_total = array_sum($ratio_val);
                    //dd($ratio_val);
                    
                    $discount = 0;
                    $total_discount = 0;
                    foreach($params as $key => $par){
                        $discount = round( ($ratio_val[$key]/$ratio_total) * $offer_amount , 2);
                        $params[$key]['discount'] =  $discount;
                        $total_discount += $discount;
                    }

                    //Thid code is added because facing issue when rounding of discount value
                    if($total_discount > $offer_amount){
                        $total_diff = $total_discount - $offer_amount;
                        foreach($params as $key => $par){
                            if($total_diff > 0.00){
                                $params[$key]['discount'] -= 0.01;
                                $total_diff -= 0.01;
                            }else{
                                break;
                            }
                        }
                    }else if($total_discount < $offer_amount){
                        $total_diff =  $offer_amount - $total_discount;
                        foreach($params as $key => $par){
                            if($total_diff > 0.00){
                                $params[$key]['discount'] += 0.01;
                                $total_diff -= 0.01;
                            }else{
                                break;
                            }
                        }

                    }

                    $new_params = [];
                    $total_qty = 0;
                    $total_price = 0;
                    $discount =0;
                    foreach($params as $key => $par){

                        if(isset($new_params[$par['item_id']])){
                            $new_params[$par['item_id']]['discount'] +=  $par['discount'];
                        }else{
                            $new_params[$par['item_id']] = $par;
                        }
                        
                    }
                    //dd($new_params);
                    foreach($new_params as $key =>  $param){
                        $cart = $carts->where('item_id', $key)->first();
                        $cart_update = DB::table('cart')->where('cart_id', $cart->cart_id)->update(['employee_discount' => $param['discount'] , 'employee_id' => $employee_id ]);
                    }

                    return response()->json(['status' => 'success', 'message' => 'Employee discount applied successfully'], 200);

                }else{
                    foreach($cartss as $cart){
                        $cart->employee_discount = 0.00;
                        $cart->save();
                    }
                    return response()->json(['status' => 'fail', 'message' => 'Cannot apply Employee discount because available discount is less than the item purchase'], 200);
                }

            }else{

                foreach($cartss as $cart){
                    $cart->employee_discount = 0.00;
                    $cart->save();
                }
            }

            //dd($new_params);

        }else{
            //dd($cartss);
            //$cartss->update(['employee_discount' => 0.00 ]);
            foreach($cartss as $cart){
                $cart->employee_discount = 0.00;
                $cart->save();
            }
        }
        //dd($carts);
        ###### Employee Discount END ######
        
        //dd($s_o_final_data);


    }

}