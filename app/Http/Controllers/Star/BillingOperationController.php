<?php

namespace App\Http\Controllers\Star;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VendorSettingController;
use Illuminate\Http\Request;
use DB;

use App\Cart;
use App\Order;

class BillingOperationController extends Controller
{

    public function __construct()
	{
		//$this->middleware('auth');
	}

	public function getItem(Request $request){

		$v_id = $request->v_id;
        $store_id = $request->store_id; 
        $c_id = $request->c_id;

        $stores =  DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();
        $store_db_name = $stores->store_db_name;

		$item_master = DB::table($store_db_name.'.item_master as im')
						->join($store_db_name.'.price_master as pm', 'pm.ITEM' ,'im.EAN')
						->where('pm.TYPE','DELIVERY_ITEM')->get();

        Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status','process')->delete();

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
            if(!empty($product->IMAGE) && $product->IMAGE != null){
                $image_name = explode('.' , $product->IMAGE);
                $selected_images = $image_name[0].'_selected.'.$image_name[1];
            }else{
                //$image_name = explode('.' , $product->IMAGE);
                $selected_images = '';
            }
            
			$data[$product->CATEGORY][] = [ 'item_name' => $product->ITEM_DESC ,
						'images' => $product->IMAGE,
                        'selected_images' => $selected_images,
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

		return response()->json(['status' => 'success' , 'item_data' => $response , 'product_image_link' => product_image_link().$v_id.'/', 'total' => format_number($total) , 'cart_qty_total' => (string)$cart_qty_total ],200);

	}

	public function saveItem(Request $request)
    {
        //echo 'inside this';exit;
        $v_id = $request->v_id;
        $store_id = $request->store_id; 
        $c_id = $request->c_id; 
        //$order_id = $request->order_id; 
        $items = $request->items; 
	    $items = json_decode($items, true);
        //dd($bags);
        $store_db_name = get_store_db_name(['store_id' => $store_id]);

        $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

        $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();
        $cart_barcode = $carts->pluck('item_id')->all(); 
        $cartC = new CartController;
        foreach ($items as $key => $value) {
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
            return response()->json(['status' => 'save_items', 'message' => 'Items Added' , 'total' => format_number($total) ],200);
        } else {
            return response()->json(['status' => 'save_items', 'message' => 'items  Updated' ,'total' => format_number($total) ],200);
        }
        //print_r($
        // $carry_bags = DB::table('vendor_carry_bags')->select('BAG_ID','Name','Price')->where('V_ID', $v_id)->where('Store_ID', $store_id)->get();
        // return response()->json(['status' => 'get_carry_bags_by_store', 'data' => print_r(expression)$bags ],200);
    }

    public function saveOtherDetails(Request $request){
        
        $v_id = $request->v_id;
        $store_id = $request->store_id; 
        $c_id = $request->c_id;

        $company_name = $request->company_name;
        $truck_from = $request->truck_from;
        $truck_number = $request->truck_number;

        $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

        DB::table('cart_other_details')->where( [ 'v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id , 'order_id' => $order_id])->delete();

        DB::table('cart_other_details')->insert([
            [ 'v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id , 'order_id' => $order_id , 'name' => 'company_name', 'value' => $company_name],
            [ 'v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id , 'order_id' => $order_id , 'name' => 'truck_from', 'value' => $truck_from],
            [ 'v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id , 'order_id' => $order_id , 'name' => 'truck_number', 'value' => $truck_number]
        ]);

        $total = DB::table('cart')->where([ 'v_id' => $v_id , 'store_id' => $store_id , 'user_id' => $c_id , 'order_id' => $order_id])->sum('total');

        return response()->json(['status' => 'success' , 'total' => $total , 'message' => 'Data save successfully'],200);

    }

    public function printReceipt(Request $request){
        
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
        $cart_other_details = DB::table('cart_other_details')->where('c_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order->o_id)->get();

        //dd($cart_other_details);
        //$user = User::select('first_name','last_name', 'mobile')->where('c_id',$c_id)->first();

        $store_db_name = $stores->store_db_name;

        $print_data_array['title'] = 'HANDLING CHARGES';
        $print_data_array['header'] = 'Receipt No : '. $order->order_id. '\nDate :'.$order->date ;
        $print_data_array['company_name'] = $cart_other_details->where('name' ,'company_name' )->first()->value;
        $print_data_array['truck_number'] = $cart_other_details->where('name' ,'truck_number' )->first()->value;
        $print_data_array['type'] = $carts->first()->item_name;
        $print_data_array['truck_from'] = $cart_other_details->where('name' ,'truck_from' )->first()->value;
        $print_data_array['amount'] = $order->total;
        $print_data_array['amount_in_words'] = numberTowords($order->total);

        return response()->json(['status' => 'success' , 'data' => $print_data_array ],200);


    }


}