<?php

namespace App\Http\Controllers\V1\Star;

use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\VendorSettingController;
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
						->where('pm.IS_CATALOG','1')->get();

		$carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status','process')->orderBy('updated_at','desc')->get();

		$total = $carts->sum('total');
		$cart_qty_total = $carts->sum('qty');
		$cartItems = $carts->pluck('qty','item_id')->all();;

		$items = $item_master->pluck('');
		$data = [];
		foreach($item_master as $product){
			
			$qty = 0;
			$cart_id = '';
			if(isset($cartItems[$product->ITEM])){
				$qty = $cartItems[$product->ITEM];
				$cart = $carts->where('item_id', $product->ITEM)->first();
				$cart_id = $cart->cart_id;
			}
			$data[$product->CATEGORY][] = [ 'item_name' => $product->ITEM_DESC ,
						'images' => $product->IMAGE,
						'unit_mrp' => $product->MRP1,
						'category' => $product->CATEGORY,
						'qty' => (string)$qty,
						'barcode' => $product->ITEM,
						'cart_id' => $cart_id
					 ];
		}

		$response = [];
		foreach($data as $key =>  $d){
			$response[] = [ 'title' => $key , 'data' => $d ]; 
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
        //dd($bags);
        $store_db_name = get_store_db_name(['store_id' => $store_id]);

        $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

        $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();
        $cart_barcode = $carts->pluck('item_id')->all(); 
        $cartC = new CartController;
        foreach ($catalogs as $key => $value) {
            $exists = $carts->where('barcode', $value[0])->first();
            $price_master = DB::table($store_db_name.'.price_master')->where('ITEM', $value[0])->first();
            if($exists) {
                unset($cart_barcode[$key]);
                if($value[1] < 1 ){
                    $request->request->add(['cart_id' => $exists->cart_id]);
                    $cartC->remove_product($request);
                }else{
                    $request->request->add(['barcode' => $value[0] , 'qty' =>$value[1] , 'unit_mrp' => $price_master->MRP1  , 'r_price' => $price_master->MRP1 * $value[1] , 's_price' => $price_master->MRP1 * $value[1] , 'discount' => 0 , 'pdata' => '' ]);
                    $cartC->product_qty_update($request);
                }

                $status = '1';
            } else {

                if($value[1] > 0 ){
        
                    $request->request->add(['barcode' => $value[0] , 'qty' =>$value[1] , 'unit_mrp' => $price_master->MRP1  , 'r_price' => $price_master->MRP1 * $value[1] , 's_price' => $price_master->MRP1 * $value[1] , 'discount' => 0 , 'pdata' => '' ]);
                    $cartC->add_to_cart($request);
                }

                $status = '2';
            }
        }
        //dd($cart_barcode);
        foreach($cart_barcode as $bar){
            $remove_cart = $carts->where('item_id', $bar)->first();
            //dd($remove_cart);
            $request->request->add(['cart_id' => $remove_cart->cart_id]);
            $cartC->remove_product($request);
        }

        $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

        $total = $carts->sum('total');

        if($status == 1) {
            return response()->json(['status' => 'save_catalogs', 'message' => 'Catalogs Added' , 'total' => format_number($total) ],200);
        } else {
            return response()->json(['status' => 'save_catalogs', 'message' => 'Catalogs  Updated' ,'total' => format_number($total) ],200);
        }
        //print_r($
        // $carry_bags = DB::table('vendor_carry_bags')->select('BAG_ID','Name','Price')->where('V_ID', $v_id)->where('Store_ID', $store_id)->get();
        // return response()->json(['status' => 'get_carry_bags_by_store', 'data' => print_r(expression)$bags ],200);
    }


}