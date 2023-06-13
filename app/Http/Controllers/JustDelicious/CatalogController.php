<?php

namespace App\Http\Controllers\JustDelicious;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VendorSettingController;
use Illuminate\Http\Request;
use DB;

use App\Cart;
use App\Order;

class CatalogController extends Controller
{

    public function __construct()
	{
		//$this->middleware('auth');
	}

	public function getCatalog(Request $request){

		$v_id = $request->v_id;
        $store_id = $request->store_id; 
        $c_id = $request->c_id;

        $stores =  DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();
        $store_db_name = $stores->store_db_name;

		$item_master = DB::table($store_db_name.'.item_master as im')
						->join($store_db_name.'.price_master as pm', 'pm.ITEM' ,'im.EAN')
						->join($store_db_name.'.price_master_details as pmd', 'pmd.ITEM', 'pm.ITEM')
						->select('pmd.ITEM','pmd.ITEM_DESC','pmd.IMAGE','pm.MRP1','pmd.CATEGORY','pmd.DESCRIPTION','pmd.IS_IMAGE','pmd.IS_NONVEG')
						->where('pmd.IS_CATALOG','1')
                        //->where('pmd.CATEGORY','!=', 'Carry Bag')
                        ->get();
						
        $carry_bags = DB::table('carry_bags')->select('barcode')->where('v_id', $v_id)->where('store_id', $store_id)->where('status','1')->where('deleted_status', '0')->get();
        $carry_bag_arr = $carry_bags->pluck('barcode')->all();  

		$carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status','process')->orderBy('updated_at','desc')->get();

		$total = $carts->sum('total');
		$cart_qty_total = $carts->sum('qty');
		$cartItems = $carts->pluck('qty','item_id')->all();;

		$items = $item_master->pluck('');
		$data = [];
		foreach($item_master as $product){
			

            //if (strpos($product->CATEGORY, 'Carry Bag') !== false) {
            if (preg_match('/Carry Bag/', $product->CATEGORY) ){
                
                if( in_array($product->ITEM, $carry_bag_arr) ){
                    //echo $product->ITEM;
                }else{
                    continue;
                }
            }

			$qty = 0;
			$cart_id = '';
			if(isset($cartItems[$product->ITEM])){
				$qty = $cartItems[$product->ITEM];
				$cart = $carts->where('item_id', $product->ITEM)->first();
				$cart_id = $cart->cart_id;
			}
            $itemDesc   = $product->DESCRIPTION;
            if($product->DESCRIPTION == NULL){
                $itemDesc = "";
            }
			$data[$product->CATEGORY][] = [ 'item_name' => $product->ITEM_DESC ,
                        'item_desc' => $itemDesc,
                        'is_nonveg' => $product->IS_NONVEG,
						'images' => $product->IMAGE,
						'unit_mrp' => $product->MRP1,
						'category' => $product->CATEGORY,
						'qty' => (string)$qty,
						'barcode' => $product->ITEM,
						'cart_id' => $cart_id
					 ];
            $isimage[$product->CATEGORY]  =  $product->IS_IMAGE;
		}

        //ONly for carry BAg
        $item_master = DB::table($store_db_name.'.item_master as im')
                        ->join($store_db_name.'.price_master as pm', 'pm.ITEM' ,'im.EAN')
                        ->join($store_db_name.'.price_master_details as pmd', 'pmd.ITEM', 'pm.ITEM')
                        ->join('carry_bags as cb', 'cb.barcode' ,'im.EAN')
                        ->select('pmd.ITEM','pmd.ITEM_DESC','pmd.IMAGE','pm.MRP1','pmd.CATEGORY','pmd.DESCRIPTION','pmd.IS_IMAGE','pmd.IS_NONVEG')
                        //->where('pmd.IS_CATALOG','1')
                        ->where('pmd.CATEGORY','!=', 'Carry Bag')
                        ->get();

        foreach($item_master as $product){
            
            $qty = 0;
            $cart_id = '';
            if(isset($cartItems[$product->ITEM])){
                $qty = $cartItems[$product->ITEM];
                $cart = $carts->where('item_id', $product->ITEM)->first();
                $cart_id = $cart->cart_id;
            }
            $itemDesc   = $product->DESCRIPTION;
            if($product->DESCRIPTION == NULL){
                $itemDesc = "";
            }
            $data[$product->CATEGORY][] = [ 'item_name' => $product->ITEM_DESC ,
                        'item_desc' => $itemDesc,
                        'is_nonveg' => $product->IS_NONVEG,
                        'images' => $product->IMAGE,
                        'unit_mrp' => $product->MRP1,
                        'category' => $product->CATEGORY,
                        'qty' => (string)$qty,
                        'barcode' => $product->ITEM,
                        'cart_id' => $cart_id
                     ];
            $isimage[$product->CATEGORY]  =  $product->IS_IMAGE;
        }

         
		$response = [];
        $sr = 1;
		foreach($data as $key =>  $d){
			$response[] = [ 'title' => $key,'cat_id'=>$sr,'is_image'=> $isimage[$key] , 'data' => $d ]; 
            $sr++;
		}

		return response()->json(['status' => 'success' , 'catalog_data' => $response , 'product_image_link' => product_image_link().$v_id.'/', 'total' => format_number($total) , 'cart_qty_total' => (string)$cart_qty_total ],200);

	}

	public function saveCatalog(Request $request)
    {
    	
        //echo 'inside this';exit;
        $v_id = $request->v_id;
        $store_id = $request->store_id; 
        $c_id = $request->c_id; 
        //$order_id = $request->order_id; 
        $catalogs = $request->catalogs; 
	    $catalogs = json_decode($catalogs, true);
        //dd($catalogs);
        $store_db_name = get_store_db_name(['store_id' => $store_id]);

        $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

        $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();
        //dd($carts);
        $cart_barcode = $carts->pluck('item_id')->all(); 
        $cartC = new CartController;
        //dd($catalogs);
        $status = -1;
        foreach ($catalogs as $key => $value) {
            $exists = $carts->where('barcode', $value[0])->first();
            $price_master = DB::table($store_db_name.'.price_master')->where('ITEM', $value[0])->first();
            if($exists) {
                
                if($value[1] < 1 ){
                    $request->request->add(['cart_id' => $exists->cart_id]);
                    $cartC->remove_product($request);
                }else{
                    unset($cart_barcode[ array_search($value[0], $cart_barcode) ] );
                    $request->request->add(['barcode' => $value[0] , 'qty' =>$value[1] , 'unit_mrp' => $price_master->MRP1  , 'r_price' => $price_master->MRP1 * $value[1] , 's_price' => $price_master->MRP1 * $value[1] , 'discount' => 0 , 'pdata' => '' ]);
                    $cartC->product_qty_update($request);
                }

                $status = '1';
            } else {

                if($value[1] > 0 ){

                    //dd($value);
        
                    $request->request->add(['barcode' => $value[0] , 'qty' =>$value[1] , 'unit_mrp' => $price_master->MRP1  , 'r_price' => $price_master->MRP1 * $value[1] , 's_price' => $price_master->MRP1 * $value[1] , 'discount' => 0 , 'pdata' => ''  ,'is_catalog' =>'1']);
                    $cartC->add_to_cart($request);
                }

                $status = '2';
            }
        }
        //dd($request);
        //dd($cart_barcode);
        foreach($cart_barcode as $bar){
            $remove_cart = $carts->where('item_id', $bar)->where('is_catalog','1')->first();
            //dd($remove_cart);
            if($remove_cart){
                $request->request->add(['cart_id' => $remove_cart->cart_id]);
                $cartC->remove_product($request);
            }
        }

        $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

        $total = $carts->sum('total');

        if($status == 1) {
            return response()->json(['status' => 'save_catalogs', 'message' => 'Catalogs Added' , 'total' => format_number($total) ],200);
        } elseif($status == 2) {
            return response()->json(['status' => 'save_catalogs', 'message' => 'Catalogs  Updated' ,'total' => format_number($total) ],200);
        }else{
            return response()->json(['status' => 'save_catalogs', 'message' => 'Catalogs  Cleared' ,'total' => format_number(0) ],200);

        }
        //print_r($
        // $carry_bags = DB::table('vendor_carry_bags')->select('BAG_ID','Name','Price')->where('V_ID', $v_id)->where('Store_ID', $store_id)->get();
        // return response()->json(['status' => 'get_carry_bags_by_store', 'data' => print_r(expression)$bags ],200);
    }

}