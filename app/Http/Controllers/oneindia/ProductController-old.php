<?php

namespace App\Http\Controllers\oneindia;

use App\Http\Controllers\Controller;
use App\Order;
use App\Scan;
use DB;
use Illuminate\Http\Request;

class ProductController extends Controller {

	public function __construct() {
		$this->middleware('auth');
	}

	public function product_details(Request $request) {
		// dd($request->all());
		$v_id = $request->v_id;
		$trans_from = $request->trans_from;
		$store_id = $request->store_id;
		$barcode = $request->barcode;
		$c_id = $request->c_id;
		$scanFlag = $request->scan;
		$product_data = array();

		$stores = DB::table('stores')->select('name', 'mapping_store_id')->where('store_id', $store_id)->first();
		$store_name = $stores->name;
		$item = DB::table('vmart.invitem')->where('BARCODE', $barcode)->first();
		// dd($item);
		if (!$item) {
			$itema = DB::table('vmart.invitem')->where('ICODE', $barcode)->first();
			if (!$itema) {
				return response()->json(['status' => 'product_not_found', 'message' => 'Product Not Found'], 404);
			} else {
				$barcodefrom = $itema->ICODE;
			}
		} else {
			$barcodefrom = $item->ICODE;
		}
		// dd($barcode);

		$order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
		$order_id = $order_id + 1;

		$check_product_in_cart_exists = DB::table('cart')->where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('barcode', $barcodefrom)->where('status', 'process')->first();

		// $response = $this->check_product_exist_in_cart($request);

		if (empty($check_product_in_cart_exists)) {
			$qty = 1;
		} else {
			$qty = $check_product_in_cart_exists->qty + 1;
		}

		(array) $push_data = ['v_id' => $v_id, 'trans_from' => $trans_from, 'barcode' => $barcodefrom, 'qty' => (string) $qty, 'scode' => $stores->mapping_store_id];

		$promoC = new PromotionController;
		$offer_data = $promoC->final_check_promo_sitewise($push_data, 0);
		$data = $offer_data;
		// dd($offer_data);

		if ($trans_from == 'ANDROID_VENDOR' || $trans_from == 'IOS_VENDOR') {

			$request->request->add(['qty' => $offer_data['qty'],
				'unit_mrp' => $offer_data['unit_mrp'],
				'r_price' => $offer_data['r_price'],
				's_price' => $offer_data['s_price'],
				'discount' => $offer_data['discount'],
				'pdata' => $offer_data['pdata'],
				'data' => $data,

			]);

			$cartC = new CartController;
			if ($offer_data['qty'] == 1) {
				return $cartC->add_to_cart($request);
			} else {
				return $cartC->product_qty_update($request);
			}

		} else if ($trans_from == 'ANDROID_KIOSK' || $trans_from == 'IOS_KIOSK') {

			$request->request->add(['qty' => $offer_data['qty'],
				'unit_mrp' => $offer_data['unit_mrp'],
				'r_price' => $offer_data['r_price'],
				's_price' => $offer_data['s_price'],
				'discount' => $offer_data['discount'],
				'pdata' => $offer_data['pdata'],
				'get_data_of' => 'CART_DETAILS',

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

	public function product_details_by_cart($value) {
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

	public function product_details_by_qty($value) {
		// dd($value);
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
