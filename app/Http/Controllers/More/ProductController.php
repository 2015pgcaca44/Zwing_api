<?php

namespace App\Http\Controllers\More;

use App\Http\Controllers\Controller;
use App\Order;
use App\Scan;
use DB;
use Illuminate\Http\Request;

class ProductController extends Controller {

	public function __construct() {
		$this->middleware('auth');
	}

	public function product_details(Request $request) 
	{
		// dd($request->all());
		$v_id = $request->v_id;
		$trans_from = $request->trans_from;
		$store_id = $request->store_id;
		$barcode = $request->barcode;
		$c_id = $request->c_id;
		$scanFlag = $request->scan;
		$product_data = array();
		$stores = DB::table('stores')->select('name', 'mapping_store_id' ,'store_db_name')->where('store_id', $store_id)->first();
		$store_name = $stores->name;
		$store_db_name = $stores->store_db_name;
		//Getting barcode without strore tagging
		$item = DB::table($store_db_name.'.invitem')->select('GRPCODE', 'INVARTICLE_CODE','BARCODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE', 'LISTED_MRP', 'DESC1', 'DESC2', 'DESC3', 'DESC4', 'DESC5')->where('BARCODE', $barcode)->first();
		if (!$item) {

			$item = DB::table($store_db_name.'.invitem')->select('GRPCODE', 'INVARTICLE_CODE','BARCODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE', 'LISTED_MRP', 'DESC1', 'DESC2', 'DESC3', 'DESC4', 'DESC5', 'DESC6')->where('ICODE', $barcode)->first();
			if (!$item) {
				return response()->json(['status' => 'product_not_found', 'message' => 'Product Not Found'], 404);
			} else {
				$barcodefrom = $item->ICODE;
			}
		} else {
			$barcodefrom = $item->ICODE;
		}

		// dd($barcode);

		$order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
		$order_id = $order_id + 1;

		$carts = DB::table('cart')->where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

		$check_product_in_cart_exists = $carts->where('barcode', $barcodefrom)->first();
		//dd($check_product_in_cart_exists);
		// $response = $this->check_product_exist_in_cart($request);

		if (empty($check_product_in_cart_exists)) {
			$qty = 1;
		} else {
			$qty = $check_product_in_cart_exists->qty + 1;
		}

		$promoC = new PromotionController;

		//(array) $push_data = ['v_id' => $v_id, 'trans_from' => $trans_from, 'barcode' => $barcodefrom, 'qty' => (string) $qty, 'scode' => $stores->mapping_store_id];

		//$offer_data = $promoC->final_check_promo_sitewise($push_data, 0);
		
		$params = ['v_id' => $v_id, 'trans_from' => $trans_from, 'barcode' => $barcodefrom, 'qty' =>  $qty, 'mapping_store_id' => $stores->mapping_store_id , 'item' => $item, 'carts' => $carts , 'store_db_name' => $store_db_name, 'is_cart' => 0, 'is_update' => 0 ];
		
		$offer_data = $promoC->index($params);

		$data = $offer_data;
		// dd($offer_data);

		if ($trans_from == 'ANDROID_VENDOR' || $trans_from == 'IOS_VENDOR' || $trans_from == 'CLOUD_TAB') {

			$request->request->add(['qty' => $offer_data['qty'],
				'unit_mrp' => $offer_data['unit_mrp'],
				'unit_rsp' => $offer_data['unit_rsp'],
				'r_price' => $offer_data['r_price'],
				's_price' => $offer_data['s_price'],
				'discount' => $offer_data['discount'],
				'pdata' => $offer_data['pdata'],
				'data' => $data,
				'ogbarcode'	=> $barcodefrom

			]);

			$cartC = new CartController;
			if ($offer_data['qty'] == 1) {
				return $cartC->add_to_cart($request);
			} else {
				return $cartC->add_to_cart_by_qty($request);
			}

		} else if ($trans_from == 'ANDROID_KIOSK' || $trans_from == 'IOS_KIOSK') {

			$request->request->add(['qty' => $offer_data['qty'],
				'unit_mrp' 		=> $offer_data['unit_mrp'],
				'r_price' 		=> $offer_data['r_price'],
				's_price' 		=> $offer_data['s_price'],
				'discount' 		=> $offer_data['discount'],
				'pdata' 		=> $offer_data['pdata'],
				'get_data_of' 	=> 'CART_DETAILS',
				'ogbarcode'		=> $barcodefrom
			]);

			$cartC = new CartController;
			if ($offer_data['qty'] == 1) {
				return $cartC->add_to_cart($request);
			} else {
				return $cartC->product_qty_update($request);
			}

		} else {

			return response()->json(['status' => 'get_product_details', 'message' => 'Get Product Details', 'data' => $offer_data, 'product_image_link' => product_image_link(), 'store_name' => $store_name], 200);
		}
	}

	public function product_search(Request $request) {
		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$search_term = $request->search_term;

		$product = [];
		$prices = DB::table('spar_uat.price_master')->where('ITEM_DESC', 'LIKE', '%' . $search_term . '%')->get();

		foreach ($prices as $price) {
			$item = DB::table('spar_uat.item_master')->where('ITEM', $price->ITEM)->first();
			$product_data['product_id'] = $item->ITEM;
			$product_data['product_name'] = $price->ITEM_DESC;
			$product_data['r_price'] = $price->MRP1;
			$product_data['s_price'] = $price->CSP1;
			$product_data['image'] = '';
			$product_data['barcode'] = $item->EAN;
			$product[] = $product_data;
		}

		return response()->json(['status' => 'get_product_search', 'message' => 'Get Product Search', 'data' => $product, 'product_image_link' => product_image_link()], 200);
	}

	public function product_details_by_cart($value) 
	{
		$v_id = $value->v_id;
		$trans_from = $value->trans_from;
		$store_id = $value->store_id;
		$barcode = $value->barcode;
		$c_id = $value->c_id;
		$scanFlag = $value->scan;
		$qty = $value->qty;
		$product_data = array();

		$stores = DB::table('stores')->select('name', 'mapping_store_id')->where('store_id', $store_id)->first();
		$store_name = $stores->name;
		$item = DB::table('vmart.invitem')->where('ICODE', $barcode)->first();
		if (!$item) {
			return response()->json(['status' => 'product_not_found', 'message' => 'Product Not Found'], 404);
		}

		(array) $push_data = ['v_id' => $v_id, 'trans_from' => $trans_from, 'barcode' => $value->barcode, 'qty' => $qty, 'scode' => $stores->mapping_store_id];

		$promoC = new PromotionController;
		$offer_data = $promoC->final_check_promo_sitewise($push_data, 1);
		// $data = $offer_data;

		return $offer_data;
	}

	public function product_details_by_qty(Request $request) 
	{
		// dd($value);
		$v_id = $request->v_id;
		$trans_from = $request->trans_from;
		$store_id = $request->store_id;
		$barcode = $request->barcode;
		$c_id = $request->c_id;
		$scanFlag = $request->scan;
		$qty = $request->qty;
		$product_data = array();

		$stores = DB::table('stores')->select('name', 'mapping_store_id')->where('store_id', $store_id)->first();
		$store_name = $stores->name;
		// $item = DB::table('vmart.invitem')->where('ICODE', $barcode)->first();
		// if (!$item) {
		// 	return response()->json(['status' => 'product_not_found', 'message' => 'Product Not Found'], 404);
		// }

		(array) $push_data = ['v_id' => $v_id, 'trans_from' => $trans_from, 'barcode' => $value->barcode, 'qty' => $qty, 'scode' => $stores->mapping_store_id];

		$promoC = new PromotionController;
		$offer_data = $promoC->final_check_promo_sitewise($push_data, 0);
		// $data = $offer_data;
		// dd($offer_data);
		return $offer_data;
	}

}
