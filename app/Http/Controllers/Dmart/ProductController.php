<?php

namespace App\Http\Controllers\Dmart;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductRatingController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\VendorSettingController;

use Illuminate\Http\Request;
use DB;
use App\Offer;
use App\Wishlist;
use App\Scan;
use App\Order;
use App\Cart;
use Auth;

class ProductController extends Controller
{

	public function __construct()
	{
		$this->middleware('auth');
	}

    public function check_product_exist_in_cart(Request $request){

        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $barcode = $request->barcode;
        $c_id = $request->c_id;

        $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

        $cart = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

        $product_qty = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('barcode', $barcode)->where('status', 'process')->sum('qty');

        //if($product_qty) {
            //return response()->json(['status' => 'product_already_exists', 'message' => 'Product Already Exists' , 'qty' => $product_qty ]    );
            return ['qty' => $product_qty, 'cart' => $cart];  
        //}
    }

    public function product_details(Request $request)
    {
        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $barcode = $request->barcode;
		$c_id = $request->c_id;
		$scanFlag = $request->scan;
        $product_data = array();
        $change_mrp = '';
        $plu_qty = 0.00;
        $plu_flag = false;
        $plu_barcode = null;
        $trans_from = $request->trans_from;
		
		$carr_bag_arr =[];

        if($barcode[0] == 2){
            $plu_flag = true;
            $plu_barcode = $barcode;
            $plu_qty = substr($barcode,7, 5);
            $barcode = substr($barcode,1, 6);    
        }

        $stores =  DB::table('stores')->select('name','mapping_store_id','store_db_name')->where('store_id', $store_id)->first();
        $store_name = $stores->name;
        $store_db_name =  $stores->store_db_name ;
        $item = DB::table($store_db_name.'.item_master')->where('EAN', $barcode)->where('STORE', $stores->mapping_store_id)->first();
        if(!$item) {
            return response()->json(['status' => 'product_not_found', 'message' => 'Product Not Found' ], 404);
        }
        $price = DB::table($store_db_name.'.price_master')->where('ITEM', $item->ITEM)->where('LOC', $stores->mapping_store_id)->first();
        if(!$price) {
            return response()->json(['status' => 'product_not_found', 'message' => 'Product Not Found' ], 404);
        }

        if($request->has('change_mrp')){
            $change_mrp = $request->change_mrp;
        }else{
            $change_mrp = $price->MRP1;
        }

        $response = $this->check_product_exist_in_cart($request);
		$cart = $response['cart'];

        $carr_bag_arr =  [ '114903443', '114952448' ,'114974444'];
        $no_of_cart_item  = $cart->whereNotIn('item_id', $carr_bag_arr)->count();
        $no_of_qty_for_item = 0;
        if($response['qty'] > 0){//Prdudct exists
            $no_of_qty_for_item  = $response['qty'] + 1;
        }else{
            $no_of_qty_for_item =1 ;
            $no_of_cart_item += 1;
        }

        $vendorS = new VendorSettingController;
        $product_max_qty = 999 ;
        $settings = $vendorS->getSetting($v_id , 'product');
        if($settings){
            
            $settings = $settings->first()->settings;
            $productSettings = json_decode($settings);

            if(isset($productSettings->max_qty)){
                if($request->has('trans_from') ){
                    $for = $request->trans_from;
                    if(isset($productSettings->max_qty->$for)){
                        $product_max_qty = $productSettings->max_qty->$for;
                    }
                }
            }
            
        }
        if($no_of_qty_for_item > $product_max_qty ){
            return response()->json(['status' => 'fail', 'message' => 'Quantity cannot be greater than '.$product_max_qty   ],200 );
        }

        $cart_max_item = 999 ;
        $settings = $vendorS->getSetting($v_id , 'store');
        if($settings){
            $settings = $settings->first()->settings;
            $productSettings = json_decode($settings);

            if(isset($productSettings->cart_max_item)){
                if($request->has('trans_from') ){
                    $for = $request->trans_from;
                    if(isset($productSettings->cart_max_item->$for)){
                        $cart_max_item = $productSettings->cart_max_item->$for;
                    }
                }
            }
        }

        
        if($no_of_cart_item > $cart_max_item){
            return response()->json(['status' => 'fail', 'message' => 'Cart item cannot be greater than '.$cart_max_item   ],200 );
        }
		
        if($plu_flag){

            if($plu_qty > 0){
                $product_qty = $plu_qty / 1000;
        
            }else{
              $product_qty =1;  
            }

        }else{
			
			$product_qty = 1;
		/*
            if(isset($response['qty'])){
                $product_qty = $response['qty'] + 1;
            }else{
                $product_qty =1;
            }
			*/
        }
        
        
        //dd( $product_qty);
        /*$product_qty = 0;
        if(isset($response['qty'])){
            $product_qty = $response['qty'] + 1;
        }else{
            $product_qty =1;
        }*/

        
        $offer_params = [ 'item_master' => $item, 'price_master' => $price  , 'product_qty' => $product_qty , 'product_barcode' => $barcode,   'mrp' => $change_mrp , 'cart' => $response['cart'] , 'c_id' => $c_id , 'v_id' => $v_id , 'store_id' => $store_id  , 'mapping_store_id' =>  $stores->mapping_store_id ];

        //dd($offer_params);
        
        $promoC = new PromotionController(['store_db_name' => $store_db_name ]);
        $offer_data = $promoC->index($offer_params);

        //dd($offer_data); 
        //$url = urlencode(json_encode($offer_data));
		$pdata_id = DB::table('temp_table')->insertGetId(['pdata' => json_encode($offer_data) ]);

        //dd($offer_data);
        
       
        $type = 0;
        $offer_arr = array();

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
        $product_data['p_id'] = (int)$item->ITEM;
        $product_data['category'] = '';
        $product_data['brand_name'] = '';
        $product_data['sub_categroy'] = '';
        $product_data['style_code'] = '';
        $product_data['weight_flag'] = ($price->WEIGHT_FLAG == 'YES')?true:false ;
        $product_data['p_name'] = $price->ITEM_DESC;
        $product_data['offer'] = (count($offer_data['available_offer']) > 0)?'Yes':'No';
        $product_data['offer_data'] = [ 'applied_offers' => $offer_data['applied_offer'] , 'available_offers' => $offer_data['available_offer'] ];
        /*if($price->WEIGHT_FLAG == 'YES'){
            $product_data['qty'] = (string)1;
            $product_data['weight'] = (string)$offer_data['total_qty']
        }else{*/
            $product_data['qty'] = (string)$offer_data['total_qty'];  
        //}
        
        $product_data['multiple_price_flag'] = $offer_data['multiple_price_flag'];
        $product_data['multiple_mrp'] = $offer_data['multiple_mrp'];
        $product_data['unit_mrp'] = format_number($change_mrp);
        //$product_data['r_price'] = format_number($price->MRP1);
        //$csp = ($price->CSP1 <=0.00 || $price->CSP1=='' || $price->CSP1 === NULL || $price->CSP1 == null)?$price->MRP1:$price->CSP1;
        //$product_data['s_price'] = format_number($csp) ;
        //if(count($offer_data['available_offers'])>0 ){
            
            $product_data['r_price'] = format_number($offer_data['r_price']);
            $product_data['s_price'] = format_number($offer_data['s_price']);
            
        //}
       $product_data['discount'] = format_number($offer_data['total_discount']);

        $product_data['varient'] = '';
        $product_data['images'] = '';
        $product_data['description'] = '';
        $product_data['deparment'] = '';
        if($plu_flag){
            $product_data['barcode'] = $plu_barcode;
        }else{
          $product_data['barcode'] = $barcode;  
        }
        
        $product_data['pdata'] = (string)$pdata_id;
		
		$rating = new ProductRatingController;
        $rating = $rating->get_rating($request);
        if($rating['status'] != 'fail'){
            if($rating['data']['count'] >= 1){
                $product_data['rating'] = $rating['data']['star'];
                $product_data['rating_no'] =  $rating['data']['count'];
            }
        }

        $review = new ProductReviewController;
        $review = $review->get_review($request);

        if($review['status'] != 'fail'){
            $product_data['review'] = $review['data'];
        }
		
        $whishlist = Wishlist::where('v_id', $v_id)->where('store_id', $store_id)->where('barcode', $barcode)->where('user_id', Auth::user()->c_id)->count();
        if(empty($whishlist)) {
            $product_data['whishlist'] = 'No';
        } else {
            $product_data['whishlist'] = 'Yes';
        }


        $vendorS = new VendorSettingController;
		$product_max_qty = 50 ;
		$settings = $vendorS->getSetting($v_id , 'product');
		if($settings){
			
			$settings = $settings->first()->settings;
			$productSettings = json_decode($settings);

			if(isset($productSettings->max_qty)){
				if($request->has('trans_from') ){
					$for = $request->trans_from;
					//dd($for);
					if(isset($productSettings->max_qty->$for)){
						$product_max_qty = $productSettings->max_qty->$for;
					}
					 
				}
			}
			
		}
		
		$cart_max_item = 50 ;
		$settings = $vendorS->getSetting($v_id , 'store');
		if($settings){
			
			$settings = $settings->first()->settings;
			$productSettings = json_decode($settings);

			if(isset($productSettings->cart_max_qty)){
				if($request->has('trans_from') ){
					$for = $request->trans_from;
					if(isset($productSettings->cart_max_item->$for)){
						$cart_max_item = $productSettings->cart_max_item->$for;
					}
				}
			}
			
		}

		$product_data['cart_max_item'] = (string)$cart_max_item;
        $product_data['product_max_qty'] = (string)$product_max_qty;
		
		
		//Adding Entry when Product scan is done
        if(!empty($product_data)){
            if($scanFlag == 'TRUE'){
                //echo 'indie this';exit;
                $scan = new Scan;
                $scan->store_id = $store_id;
                $scan->v_id = $v_id;
                $scan->user_id = $c_id;
                $scan->item_id = $product_data['p_id'];
                $scan->barcode = $barcode;
                $scan->trans_from = $trans_from;
                $scan->date = date('Y-m-d');
                $scan->time = date('h:i:s');
                $scan->month = date('m');
                $scan->year = date('Y');

                $scan->save();
            }
        }




        return response()->json(['status' => 'get_product_details', 'message' => 'Get Product Details', 'data' => $product_data, 'product_image_link' => product_image_link().$product_data['images'], 'store_name' => $store_name   ],200 );
    }
	
	public function product_search(Request $request){
        
        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $search_term = $request->search_term;

        $store_db_name = get_store_db_name(['store_id' => $store_id]);
        $product= [];
        $prices = DB::table($store_db_name.'.price_master')->where('ITEM_DESC', 'LIKE','%'.$search_term.'%')->get();

        foreach($prices as $price){
             $item = DB::table($store_db_name.'.item_master')->where('ITEM', $price->ITEM)->first();

            $product_data['product_id'] = $item->ITEM;
            $product_data['product_name'] = $price->ITEM_DESC;
            $product_data['r_price'] = $price->MRP1;
            $product_data['s_price'] = $price->CSP1;
            $product_data['image'] = '';
            $product_data['barcode'] = $item->EAN;

            $product[] = $product_data;

        }
       
        return response()->json(['status' => 'get_product_search', 'message' => 'Get Product Search', 'data' => $product ,'product_image_link' => product_image_link() ],200);


    }


    
}
