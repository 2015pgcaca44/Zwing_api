<?php

namespace App\Http\Controllers\Spar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Order;
use App\Cart;
use App\Address;
use App\PartnerOffer;
use App\PartnerOfferUsed;
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
		$this->middleware('auth');
	}


    public function process_each_item_for_offer_in_cart(Request $request){

        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;

        $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

        $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

        if(!$carts->isEmpty()){

            $productC = new OfferController;
            //$productC->
            $target_offer=[];
            $higest_lowest=[];
            $global_offer=[];
            foreach ($carts as $key => $cart) {
                $item_master = DB::table('spar_uat.item_master')->where('EAN', $cart->barcode)->first();
                $price_master = DB::table('spar_uat.price_master')->where('ITEM', $item_master->ITEM)->first();
                
                $response = $productC->fetch_individual_offers( [ 
                    'item_master'=>$item_master ,
                    'price_master' => $price_master,
                    'cart' => $carts,
                    'mrp' => $cart['per_unit_mrp'],
                    'product_barcode' => $cart['barcode'],
                    'product_qty' => $cart['qty'],
                    'global_offer' => $global_offer,
                    'target_offer' => $target_offer,
                    'higest_lowest' => $higest_lowest,
                    'without_cart' => false
                    
                    ] 
                );

                //dd($response);
                $target_offer = $response['target_offer'];
                $higest_lowest = $response['higest_lowest'];
                $global_offer = $response['global_offer'];


                $res = DB::table('cart_offers')->where('cart_id',$cart->cart_id)->first();

                if($res){
                    
                    DB::table('cart_offers')->where('cart_id',$cart->cart_id)->update(
                        [  'mrp' => $cart['per_unit_mrp'], 'qty' => $cart['qty'] , 'offers' => json_encode($response)]
                    );

                    $cart->r_price = $response['r_price'];
                    $cart->amount = $response['s_price'];
                    //$cart->
                    $cart->save();

                }else{
                    //dd($response);
                    DB::table('cart_offers')->insert(
                        ['cart_id' => $cart->cart_id, 'item_id' => $item_master->ITEM, 'mrp' => $cart['per_unit_mrp'], 'qty' => $cart['qty'] , 'offers' => json_encode($response)]
                    );

                }
               // $global_offer = $response[$cart->barcode]['global_offer'];
            }

            //dd($response);

            $global_available_offer = [] ;
            $global_applied_offer = [];
            $global_offer = $response['global_offer'];
           foreach ($global_offer as $section) {
                foreach ($section as $data) {
                    $g_total_amount = $data['total_amount'];
                    foreach($data['offer'] as $offer){
                        $global_available_offer[] = [ 'message' => $offer['message'] ];
                        //dd($offer);
                        if($g_total_amount >= $offer['mo_th']){
                            if($offer['ty_ru_prdv'] == 'MM' && isset($offer['target_product_id']) ){

                                $cart_ean_list = $carts->pluck('barcode')->all();
                                //$cart_ean_list[] = $offer_params['product_barcode'];
                                $cart_ean_list = array_unique($cart_ean_list);
                                //dd($cart_ean_list);
                                $arr = [$offer['target_product_id']];
                                $item = DB::table('spar_uat.item_master')->select('EAN')->where('ITEM', $offer['target_product_id'])->first();
                                    //dd($item);
                                $single_cart = DB::table('cart')->where('barcode',$item->EAN)->first();  
                                //dd($single_cart);
                                if(!empty(array_intersect( $arr, $cart_ean_list))){
                                    //$global_applied_offer[] = $offer;
                                    $cd_mth_prdv = $offer['cd_mth_prdv'];
                                    $mrp = (float)$single_cart->per_unit_mrp;

                                    if($cd_mth_prdv == 0 ) { //NOt in Use
                                        $second = $mrp;
                                        $final_price = $second;
                                        $saving_price = 0;
                                    } else if($cd_mth_prdv == 1) {//By Percentage Off
                                        $second = $offer['offer_price'];
                                        $per_discount = $mrp * $second / 100;
                                        $final_price = $mrp - $per_discount;
                                        $saving_price = $per_discount;
                                    }   else if($cd_mth_prdv == 2) {//By Amount OFf
                                        $second = $offer['offer_price'];
                                        $final_price = $mrp - $second;
                                        $saving_price = $second;
                                    } else if($cd_mth_prdv == 3) {//By Fixed Price
                                        $second = (float)$offer['offer_price'];
                                        $saving_price = $mrp - $second;
                                        $final_price = $second;
                                    }

                                    $choose = array(
                                        '0' => '0',
                                        '1' => '% OFF',
                                        '2' => ' Rs. OFF',
                                        '3' => ' Rs.'
                                    );

                                   /*$final_offer = [ 'saving_price' =>  $saving_price ,
                                        'strike_price' =>  format_number($mrp) ,
                                        'selling_price' => format_number($final_price)  ,
                                        'message' => $offer['message'],
                                        'qu_th' => $offer['target_qty'] ,
                                        'cd_mth_prdv' => $offer['cd_mth_prdv'],
                                        'offer_price' => $second,
                                        //'weight_flag' => ,
                                        'ty_ru_prdv' => $offer['ty_ru_prdv'],
                                        'max_all_sources' => $offer['max_all_sources'],
                                        'product_list' => $offer['product_list'],
                                        'product_item_list' => $offer['product_item_list'] 
                                    ];*/

                                    //echo $offer['target_product_id'];exit;
                                                   
                                    $res = DB::table('cart_offers')->where('cart_id',$single_cart->cart_id)->first();
                                    $response = json_decode($res->offers, true);

                                    if($res){

                                        DB::table('cart')->where('cart_id',$single_cart->cart_id)->update(['amount' => $final_price, 'r_price' => $mrp]);  
                                    
                                        $response['r_price'] = $saving_price;
                                        $response['s_price'] = format_number($mrp);
                                        $response['applied_offers'] = [ 'message' => $offer['message'] , 'product_list' => $offer['product_list']  ];

                                        DB::table('cart_offers')->where('cart_id',$single_cart->cart_id)->update(
                                            [  'mrp' => $single_cart->per_unit_mrp, 'qty' => $single_cart->qty , 'offers' => json_encode($response)]
                                        );

                                    }

                                    
                                }
                                
                            }
                            
                        }

                    }
                }
                
            }

        }
        //dd($global_available_offer);

    }

	public function add_to_cart(Request $request)
    {
        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;
        $product_id = $request->product_id;
        $barcode = $request->barcode;
        $qty = $request->qty;
        $amount = $request->amount;
        $r_price = $request->r_price;
        $per_unit_mrp = $request->per_unit_mrp;

        $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

        $check_product_exists = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('product_id', $product_id)->where('barcode', $barcode)->where('status', 'process')->count();

        if(!empty($check_product_exists)) {
        	return response()->json(['status' => 'product_already_exists', 'message' => 'Product Already Exists' ], 409);
        }

        $cart = new Cart;

        $cart->store_id = $store_id;
        $cart->v_id = $v_id;
        $cart->order_id = $order_id;
        $cart->user_id = $c_id;
        $cart->product_id = $product_id;
        $cart->barcode = $barcode;
        $cart->qty = $qty;
        $cart->amount = $amount;
        $cart->r_price = $r_price;
        $cart->per_unit_mrp = $per_unit_mrp;
        $cart->status = 'process';
        $cart->date = date('Y-m-d');
        $cart->time = date('h:i:s');
        $cart->month = date('m');
        $cart->year = date('Y');

        $cart->save();

        $this->process_each_item_for_offer_in_cart($request);  


        return response()->json(['status' => 'add_to_cart', 'message' => 'Product was successfully added to your cart.', 'data' => $cart ],200);
    }

    public function product_qty_update(Request $request)
    {
    	$v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;
        $product_id = $request->product_id;
        $barcode = $request->barcode;
        $qty = $request->qty;

        $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

        $check_product_exists = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('barcode', $barcode)->where('status', 'process')->first();

        $per_unit_mrp = ($request->has('per_unit_mrp'))?$request->per_unit_mrp:$check_product_exists->per_unit_mrp;
        $amount = ($request->has('amount'))?$request->amount:$check_product_exists->amount * $qty;
        $r_price = ($request->has('r_price'))?$request->r_price:$check_product_exists->r_price * $qty;
        


        //$check_product_exists->qty = $check_product_exists->qty + $qty;
        //$check_product_exists->amount = $check_product_exists->amount + $amount;
        $check_product_exists->qty =  $qty;
        $check_product_exists->amount =  $amount;
        $check_product_exists->r_price = $r_price;
        $check_product_exists->per_unit_mrp = $per_unit_mrp;
        $check_product_exists->save();

        $this->process_each_item_for_offer_in_cart($request);

        return response()->json(['status' => 'product_qty_update', 'message' => 'Product quantity successfully Updated'], 200);
    }

    public function remove_product(Request $request)
    {
       // echo 'inside spart contr';
    	$c_id = $request->c_id;
    	$store_id = $request->store_id;
    	$v_id = $request->v_id;
    	$cart_id = $request->cart_id;
    	$product_id = $request->product_id;

    	$cart = Cart::where('cart_id', $cart_id)->delete();

        $this->process_each_item_for_offer_in_cart($request);
       

    	return response()->json(['status' => 'remove_product', 'message' => 'Remove Product' ],200);
    }

    public function cart_details(Request $request)
    {
        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id; 

        $cart_data = array();
        $product_data = [];
        $tax_total = 0;
		$cart_qty_total = 0;
        $retail_total =0;
        //$order_id = Order::where('user_id', $c_id)->where('status', 'success')->orWhere('status' ,'error')->count();
		$order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;
        
        $carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status','process')->get();
        $r_price_total = $carts->sum('r_price');
        $sub_total = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status','process')->sum('amount');
        $price = array();
        $rprice = array();
        $qty = array();
        $merge = array();
        
        $target_offer=[];
        $higest_lowest=[];
        $global_offer=[];

        $productC = new OfferController;
        foreach ($carts as $key => $cart) {
            $item_master = DB::table('spar_uat.item_master')->where('EAN', $cart->barcode)->first();
            $price_master = DB::table('spar_uat.price_master')->where('ITEM', $item_master->ITEM)->first();

            $res = DB::table('cart_offers')->where('cart_id',$cart->cart_id)->first();
            /*if($cart->cart_id == 1536){
                dd($res);
            }*/

            if($res){
                $response[$cart->barcode] = json_decode($res->offers , true);
            }else{

                $response[$cart->barcode] = $productC->fetch_individual_offers( [ 
                    'item_master'=>$item_master ,
                    'price_master' => $price_master,
                    'cart' => $carts,
                    'mrp' => $cart['mrp'],
                    'product_barcode' => $cart['barcode'],
                    'product_qty' => $cart['qty'],
                    'global_offer' => $global_offer,
                    'target_offer' => $target_offer,
                    'higest_lowest' => $higest_lowest
                    
                    ] 
                );

                

            }

            $target_offer = $response[$cart->barcode]['target_offer'];
            $higest_lowest = $response[$cart->barcode]['higest_lowest'];
            $global_offer = $response[$cart->barcode]['global_offer'];

            //dd($response);

            $global_offer = $response[$cart->barcode]['global_offer'];
            
            $offer_data = $response[$cart->barcode];
            //dd($offer_data);
            $product_data['p_id'] = $item_master->id;
            $product_data['category'] = '';
            $product_data['brand_name'] = '';
            $product_data['sub_categroy'] = '';
            $product_data['whishlist'] = 'No';
            $product_data['p_name'] = $price_master->ITEM_DESC;
            $product_data['offer'] = (count($offer_data['available_offers']) > 0)?'Yes':'No';
            $product_data['offer_data'] = [ 'applied_offers' => $offer_data['applied_offers'] , 'available_offers' =>$offer_data['available_offers']  ];
            //$product_data['qty'] = '';
            $product_data['multiple_price_flag'] = $offer_data['multiple_price_flag'];
            $product_data['multiple_mrp'] = $offer_data['multiple_mrp'];
            $product_data['r_price'] = format_number($offer_data['r_price']);
            $product_data['s_price'] = format_number($offer_data['s_price']);
            if(!empty($offer_data['applied_offers']) ){
                $product_data['r_price'] = format_number($offer_data['r_price']);
                $product_data['s_price'] = format_number($offer_data['s_price']);
            }

            $product_data['varient'] = '';
            $product_data['images'] = '';
            $product_data['description'] = '';
            $product_data['deparment'] = '';
            $product_data['barcode'] = $cart->barcode;

		    //$tax_total = $tax_total +  $tax_amount ;
            $tax_amount =0 ;
			$cart_qty_total =  $cart_qty_total + $cart->qty;
            $cart_data[] = array(
                    'cart_id'       => $cart->cart_id,
                    'product_data'  => $product_data,
                    'amount'        => $cart->amount,
                    'qty'           => $cart->qty,
                    'tax_amount'    => (format_number($tax_amount))?format_number($tax_amount):'0.00',
                    'delivery'      => $cart->delivery
                    // 'ptotal'        => $cart->amount * $cart->qty,
            );

            
            $qty[] = $cart->qty;
            //$merge = array_combine($rprice,$qty);

            $last_product_ean = $cart->barcode;

        }


        $global_offer_data['applied_offers']= [];
        $global_offer_data['available_offers']= [];


        $global_available_offer = [] ;
        $global_applied_offer = [];
        if(isset($last_product_ean)){
            $global_offer = $response[$last_product_ean]['global_offer'];
        }else{
           $global_offer = []; 
        }
        
        foreach ($global_offer as $section) {
            foreach ($section as $data) {
                $g_total_amount = $data['total_amount'];
                foreach($data['offer'] as $offer){
                    $global_available_offer[] = [ 'message' => $offer['message'] ];
                    //dd($offer);
                    if($g_total_amount >= $offer['mo_th']){
                        if($offer['ty_ru_prdv'] == 'MM' && isset($offer['target_product_id']) ){

                            $cart_ean_list = $carts->pluck('barcode')->all();
                            //$cart_ean_list[] = $offer_params['product_barcode'];
                            $cart_ean_list = array_unique($cart_ean_list);
                            //dd($cart_ean_list);
                            $arr = [$offer['target_product_id']];
                            $item = DB::table('spar_uat.item_master')->select('EAN')->where('ITEM', $offer['target_product_id'])->first();
                                //dd($item);
                            $single_cart = DB::table('cart')->where('barcode',$item->EAN)->first();  
                            //dd($single_cart);
                            if(!empty(array_intersect( $arr, $cart_ean_list))){
                                //$global_applied_offer[] = $offer;
                                $cd_mth_prdv = $offer['cd_mth_prdv'];
                                $mrp = (float)$single_cart->per_unit_mrp;

                                if($cd_mth_prdv == 0 ) { //NOt in Use
                                    $second = $mrp;
                                    $final_price = $second;
                                    $saving_price = 0;
                                } else if($cd_mth_prdv == 1) {//By Percentage Off
                                    $second = $offer['offer_price'];
                                    $per_discount = $mrp * $second / 100;
                                    $final_price = $mrp - $per_discount;
                                    $saving_price = $per_discount;
                                }   else if($cd_mth_prdv == 2) {//By Amount OFf
                                    $second = $offer['offer_price'];
                                    $final_price = $mrp - $second;
                                    $saving_price = $second;
                                } else if($cd_mth_prdv == 3) {//By Fixed Price
                                    $second = (float)$offer['offer_price'];
                                    $saving_price = $mrp - $second;
                                    $final_price = $second;
                                }

                                $choose = array(
                                    '0' => '0',
                                    '1' => '% OFF',
                                    '2' => ' Rs. OFF',
                                    '3' => ' Rs.'
                                );

                              
                                //echo $offer['target_product_id'];exit;
                                               
                                $res = DB::table('cart_offers')->where('cart_id',$single_cart->cart_id)->first();
                                $response = json_decode($res->offers, true);

                                if($res){

                                    DB::table('cart')->where('cart_id',$single_cart->cart_id)->update(['amount' => $final_price, 'r_price' => $mrp]);  
                                
                                    $response['r_price'] = $saving_price;
                                    $response['s_price'] = format_number($mrp);
                                    $response['applied_offers'] = [ 'message' => $offer['message'] , 'product_list' => $offer['product_list']  ];

                                    DB::table('cart_offers')->where('cart_id',$single_cart->cart_id)->update(
                                        [  'mrp' => $single_cart->per_unit_mrp, 'qty' => $single_cart->qty , 'offers' => json_encode($response)]
                                    );

                                }

                                
                            }
                            
                        }
                        
                    }

                }
            }
            
        }

    

        //BILL BUSTER OFERS
        //$total_r_price = $r_price_total;
        $total_s_price = $sub_total;
        $bill_applied_offer=[];
        $bill_available_offer=[];
        $bill_available_offer_for_applying=[];
        $bill_buster_offers= $productC->get_bill_buster_offers();
        //dd($bill_buster_offers);
        foreach($bill_buster_offers as $offer){
            $bill_available_offer[] = $offer;
            if($total_s_price >= $offer['mo_th_src'] ){
               
                if(isset($offer['target_product_id'])){

                    $cart_ean_list = $carts->pluck('barcode')->all();
                   // $cart_ean_list[] = $offer_params['product_barcode']; 
                    $cart_ean_list  = array_unique($cart_ean_list);
                    //dd($offer);
                    //Need to get ean code from item master
                    $res = [ $offer['target_product_id'] ];
                    
                    if(!empty(array_intersect($res, $cart_ean_list))){
                        $prices= DB::table('spar_uat.price_master')->where('ITEM', $offer['target_product_id'])->first();
                        
                        $mrp = $total_s_price;
                        $final_price = $total_s_price -  (float)$prices->MRP1;
                        $saving_price = $price_master->MRP1;

                        $offer = [ 'saving_price' =>  $saving_price   , 'strike_price' => format_number($mrp) , 'selling_price' => format_number($final_price) , 'message' => $offer['message']  , 'cd_mth_prdv' => 2, 'offer_price' => $prices->MRP1 ];

                        $bill_available_offer_for_applying[] = $offer;

                    }
                    

                }else{

                    $cd_mth_prdv = $offer['cd_mth_prdv'];
                    $mrp =  $total_s_price;
                    if($cd_mth_prdv == 0 ) { //NOt in Use
                        $second = $mrp;
                        $final_price = $second;
                        $saving_price = 0;
                    } else if($cd_mth_prdv == 1) {//By Percentage Off
                        $second = $offer['offer_price'];
                        $per_discount = $mrp * $second / 100;
                        $final_price = $mrp - $per_discount;
                        $saving_price = $per_discount;
                    }   else if($cd_mth_prdv == 2) {//By Amount OFf
                        $second = $offer['offer_price'];
                        $final_price = $mrp - $second;
                        $saving_price = $second;
                    } else if($cd_mth_prdv == 3) {//By Fixed Price
                        $second = $offer['offer_price'];
                        $saving_price = $mrp - $second;
                        $final_price = $second;
                    }


                    $offer = [ 'saving_price' =>  $saving_price , 'strike_price' => format_number($mrp) , 'selling_price' => format_number($final_price) , 'message' => $offer['message']  , 'cd_mth_prdv' => $offer['cd_mth_prdv'], 'offer_price' => $second ];

                    $bill_available_offer_for_applying[] = $offer;
                }
            }
            //dd($offer);
            
        }

        if(!empty($bill_available_offer_for_applying)){
            foreach($bill_available_offer_for_applying as $key => $offer ){

                if(empty($bill_applied_offer)){

                    $bill_applied_offer = $offer;
                }else{

                    if($bill_applied_offer['saving_price'] < $offer['saving_price']){
                        $bill_applied_offer = $offer;
                    }

                }
                
            }

        }
        //dd($bill_applied_offer);
        $bill_applied_offers = [];
        if(!empty($bill_applied_offer)){
            $sub_total = $bill_applied_offer['selling_price'];  
            $bill_applied_offers[] = [ 'message' =>  $bill_applied_offer['message'] ];
        }
        

        $saving = $r_price_total - $sub_total;  

        //dd($bill_available_offer);

        $global_offer_data['applied_offers'] = array_merge($global_offer_data['applied_offers'] , $global_applied_offer);
        $global_offer_data['available_offers'] = array_merge($global_offer_data['available_offers'] , $global_available_offer);
        $global_offer_data['applied_offers'] = array_merge($global_offer_data['applied_offers'] , $bill_applied_offers);
        $global_offer_data['available_offers'] = array_merge($global_offer_data['available_offers'] , $bill_available_offer);
       
		/*
		echo '<pre>';print_r($merge);exit;

        foreach ($merge as $keys => $val) {
            $saving[] = round($keys * $val);
        }*/
        
        $bags = DB::table('user_carry_bags')->select('vendor_carry_bags.Name','user_carry_bags.Qty','vendor_carry_bags.BAG_ID')->selectRaw('user_carry_bags.Qty * vendor_carry_bags.Price as Price')->leftJoin('vendor_carry_bags', 'user_carry_bags.Bag_ID', '=', 'vendor_carry_bags.BAG_ID')->where('user_carry_bags.V_ID', $v_id)->where('user_carry_bags.Store_ID', $store_id)->where('user_carry_bags.User_ID', $c_id)->where('user_carry_bags.Order_ID', $order_id)->get();
        $bprice = DB::table('user_carry_bags')->selectRaw('SUM(user_carry_bags.Qty * vendor_carry_bags.Price) as Price')->leftJoin('vendor_carry_bags', 'user_carry_bags.Bag_ID', '=', 'vendor_carry_bags.BAG_ID')->where('user_carry_bags.V_ID', $v_id)->where('user_carry_bags.Store_ID', $store_id)->where('user_carry_bags.User_ID', $c_id)->where('user_carry_bags.Order_ID', $order_id)->first();
        // $cart_data['bags'] = $bags;
		
		        
        if(empty($bprice->Price)) {
            $carry_bag_total = 0;
        } else {
            $carry_bag_total = $bprice->Price;
        }
        $total = (int)$sub_total + (int)$carry_bag_total;
        if($saving == 0){
           $less =0; 
        }else{
            $less = $saving ;
        }
		
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
        return response()->json(['status' => 'cart_details', 'message' => 'Your Cart Details', 
            'data' => $cart_data, 'product_image_link' => product_image_link(),
            'offer_data' => $global_offer_data,
            'bags' => $bags, 
            'sub_total' => (format_number($sub_total))?format_number($sub_total):'0.00', 
            'tax_total' => (format_number($tax_total))?format_number($tax_total):'0.00',
            'grand_total' => (format_number($grand_total))?format_number($grand_total):'0.00', 
            'order_id' => $order_id, 
            'carry_bag_total' => (format_number($carry_bag_total))?format_number($carry_bag_total):'0.00',
            'total' => format_number($total), 
            'cart_qty_total' => $cart_qty_total,
            'saving' => (format_number($less))?format_number($less):'0.00',
            'delivered' => $store->delivery , 
            'offered_mount' => (format_number($offeredAmount))?format_number($offeredAmount):'0.00' ],200);
        // echo array_sum($saving);
    }

    public function process_to_payment(Request $request)
    {
    	$v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;
        $amount = $request->amount;

        $t_order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $t_order_id = $t_order_id + 1;
        $order_id = order_id_generate($store_id, $c_id);

        $order = new Order;

        $order->order_id = $order_id;
        $order->o_id = $t_order_id;
        $order->v_id = $v_id;
        $order->store_id = $store_id;
        $order->user_id = $c_id;
        $order->amount = $amount;
        $order->status = 'process';
        $order->date = date('Y-m-d');
        $order->time = date('h:i:s');
        $order->month = date('m');
        $order->year = date('Y');

        $order->save();

        return response()->json(['status' => 'proceed_to_payment', 'message' => 'Proceed to Payment', 'data' => $order ],200);
    }

    public function payment_details(Request $request)
    {
    	$store_id = $request->store_id;
    	$v_id = $request->v_id;
    	$t_order_id = $request->t_order_id;
    	$order_id = $request->order_id;
    	$user_id = $request->c_id;
    	$pay_id = $request->pay_id;
    	$amount = $request->amount;
    	$method = $request->method;
    	$invoice_id = $request->invoice_id;
    	$bank = $request->bank;
    	$wallet = $request->wallet;
    	$vpa = $request->vpa;
    	$error_description = $request->error_description;
    	$status = $request->status;
		
		$api_key = env('RAZORPAY_API_KEY');
        $api_secret = env('RAZORPAY_API_SECERET');

        $api = new Api($api_key, $api_secret);
        $razorAmount = $amount * 100;
        $razorpay_payment  = $api->payment->fetch($pay_id)->capture(array('amount'=>$razorAmount)); // Captures a payment
       
        if($razorpay_payment){

            if($razorpay_payment->status == 'captured'){

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
				$payment->date = date('Y-m-d');
				$payment->time = date('h:i:s');
				$payment->month = date('m');
				$payment->year = date('Y');

				$payment->save();
				
				//Order::where('order_id', $order_id)->update(['status' => $status]);
				$ord = Order::where('order_id', $order_id)->first();
				if($request->has('address_id')){
				   $ord->address_id = $request->address_id;
				}

				$ord->status = $status;

				$ord->save();

				$cartss = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $ord->o_id)->where('user_id', $user_id)->update(['status' => $status]);


				return response()->json(['status' => 'payment_save', 'message' => 'Save Payment', 'data' => $payment ],200);
		
			}
		
		}
    }

    public function order_qr_code(Request $request)
    {
        $order_id = $request->order_id;
        $qrCode = new QrCode($order_id);
        header('Content-Type: image/png');
        echo $qrCode->writeString();
    }

    public function order_details(Request $request)
    {
        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id; 
        $order_id = $request->order_id; 

        $o_id = Order::where('order_id', $order_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->first();
        $order_num_id = Order::where('order_id', $order_id)->first();

        $cart_data = array();
        $product_data = [];
        $tax_total = 0;
        $cart_qty_total = 0;
        
        $carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $o_id->o_id)->get();
        $r_price_total = $carts->sum('r_price');
        $sub_total = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $o_id->o_id)->sum('amount');
        $saving = $sub_total - $r_price_total;
        foreach ($carts as $key => $cart) {
            $item_master = DB::table('spar_uat.item_master')->where('EAN', $cart->barcode)->first();
            $price_master = DB::table('spar_uat.price_master')->where('ITEM', $item_master->ITEM)->first();

            $res = DB::table('cart_offers')->where('cart_id',$cart->cart_id)->first();

            if($res){
                $response[$cart->barcode] = json_decode($res->offers , true);
            }else{

                $response[$cart->barcode] = $this->fetch_individual_offers( [ 
                    'item_master'=>$item_master ,
                    'price_master' => $price_master,
                    'cart' => $carts,
                    'mrp' => $cart['mrp'],
                    'product_barcode' => $cart['barcode'],
                    'product_qty' => $cart['qty'],
                    'global_offer' => $global_offer
                    
                    ] 
                );
            }

            //dd($response);

            $global_offer = $response[$cart->barcode]['global_offer'];
            
            $offer_data = $response[$cart->barcode];
            $product_data['p_id'] = $item_master->id;
            $product_data['category'] = '';
            $product_data['brand_name'] = '';
            $product_data['sub_categroy'] = '';
            $product_data['whishlist'] = 'No';
            $product_data['p_name'] = $price_master->ITEM_DESC;
            $product_data['offer'] = count($offer_data['available_offers'] > 0)?'Yes':'No';
            $product_data['offer_data'] = [ 'applied_offers' => $offer_data['applied_offers'] , 'available_offers' =>$offer_data['available_offers']  ];
            //$product_data['qty'] = '';
            $product_data['multiple_price_flag'] = $offer_data['multiple_price_flag'];
            $product_data['multiple_mrp'] = $offer_data['multiple_mrp'];
            $product_data['r_price'] = format_number($price_master->MRP1);
            $product_data['s_price'] = format_number($price_master->CSP1);
            if(!empty($offer_data['applied_offers']) ){
                $product_data['r_price'] = format_number($offer_data['r_price']);
                $product_data['s_price'] = format_number($offer_data['s_price']);
            }

            $product_data['varient'] = '';
            $product_data['images'] = '';
            $product_data['description'] = '';
            $product_data['deparment'] = '';
            $product_data['barcode'] = $cart->barcode;

            //$tax_total = $tax_total +  $tax_amount ;
            $tax_amount =0 ;
            $cart_qty_total =  $cart_qty_total + $cart->qty;
            $cart_data[] = array(
                    'cart_id'       => $cart->cart_id,
                    'product_data'  => $product_data,
                    'amount'        => $cart->amount,
                    'qty'           => $cart->qty,
                    'tax_amount'    => (format_number($tax_amount))?format_number($tax_amount):'0.00',
                    'delivery'      => $cart->delivery
                    // 'ptotal'        => $cart->amount * $cart->qty,
            );

            
            $qty[] = $cart->qty;
            //$merge = array_combine($rprice,$qty);
        }

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

        $grand_total = $sub_total + $carry_bag_total + $tax_total ;
        return response()->json(['status' => 'order_details', 'message' => 'Order Details Details', 'data' => $cart_data, 
            //'product_image_link' => product_image_link(),
            'bags' => $bags, 
            'sub_total' => (format_number($sub_total))?format_number($sub_total):'0.00', 
            'tax_total' => (format_number($tax_total))?format_number($tax_total):'0.00',
            'grand_total' => (format_number($grand_total))?format_number($grand_total):'0.00', 
            'date' => $o_id->date,
            'time' => $o_id->time,
            //'order_id' => $order_id, 
            'carry_bag_total' => (format_number($carry_bag_total))?format_number($carry_bag_total):'0.00',
            //'total' => format_number($total), 
            'cart_qty_total' => $cart_qty_total,
            //'saving' => (format_number($less))?format_number($less):'0.00',
            'delivered' => $store->delivery , 
            //'offered_mount' => (format_number($offeredAmount))?format_number($offeredAmount):'0.00',
            'address'=> $address 
        ],200);
    }

    public function get_carry_bags(Request $request)
    {
        $v_id = $request->v_id;
        $store_id = $request->store_id; 
        $order_id = Order::where('user_id', Auth::user()->c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

        $carry_bags = DB::table('vendor_carry_bags')->select('BAG_ID','Name','Price')->where('V_ID', $v_id)->where('Store_ID', $store_id)->get();
        $data = array();
        foreach ($carry_bags as $key => $value) {
            $bags = DB::table('user_carry_bags')->select('Qty','Bag_ID')->where('V_ID', $v_id)->where('Store_ID', $store_id)->where('User_ID', Auth::user()->c_id)->where('Order_ID', $order_id)->where('Bag_ID', $value->BAG_ID)->first();
            if(empty($bags)) {
                $data[] = array(
                        'BAG_ID' => $value->BAG_ID,
                        'Name' => $value->Name,
                        'Price' => $value->Price,
                        'Qty' => 0,
                );
            } else {
                if($value->BAG_ID == $bags->Bag_ID) {
                    $data[] = array(
                            'BAG_ID' => $value->BAG_ID,
                            'Name' => $value->Name,
                            'Price' => $value->Price,
                            'Qty' => $bags->Qty,
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
        return response()->json(['status' => 'get_carry_bags_by_store', 'data' => $data ],200);
    }

    public function save_carry_bags(Request $request)
    {
        $v_id = $request->v_id;
        $store_id = $request->store_id; 
        $c_id = $request->c_id; 
        $order_id = $request->order_id; 
        $bags = $request->bags; 
	    $bags = json_decode($bags, true);
        foreach ($bags as $key => $value) {
            $exists = DB::table('user_carry_bags')->where('V_ID', $v_id)->where('Store_ID', $store_id)->where('User_ID', $c_id)->where('Order_ID', $order_id)->where('Bag_ID', $value[0])->first();
            if(empty($exists)) {
                $add = DB::table('user_carry_bags')->insert(
                    ['V_ID' => $v_id, 'Store_ID' => $store_id, 'User_ID' => $c_id, 'Order_ID' => $order_id, 'Bag_ID' => $value[0], 'Qty' => $value[1]]
                );
                $status = '1';
            } else {
                if(empty($value[1])) {
                    $update = DB::table('user_carry_bags')->where('V_ID', $v_id)->where('Store_ID', $store_id)->where('User_ID', $c_id)->where('Order_ID', $order_id)->where('Bag_ID', $value[0])->update(['Qty' => $value[1]]);
                    $delete = DB::table('user_carry_bags')->where('V_ID', $v_id)->where('Store_ID', $store_id)->where('User_ID', $c_id)->where('Order_ID', $order_id)->where('Bag_ID', $value[0])->delete();
                } else {
                    $delete = DB::table('user_carry_bags')->where('V_ID', $v_id)->where('Store_ID', $store_id)->where('User_ID', $c_id)->where('Order_ID', $order_id)->where('Bag_ID', $value[0])->update(['Qty' => $value[1]]);  
                }
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

}