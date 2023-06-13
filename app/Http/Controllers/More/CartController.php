<?php

namespace App\Http\Controllers\More;

use App\Address;
use App\Cart;
use App\Http\Controllers\Controller;
use App\Http\Controllers\VendorSettingController;
use App\Order;
use App\Payment;
use App\Store;
use App\User;
use App\VendorImage;
use Auth;
use DB;
use Endroid\QrCode\QrCode;
use Illuminate\Http\Request;
use Razorpay\Api\Api;
use App\Invoice;
use App\OrderDetails;
use App\OrderItemDetails;
use App\CartDetails;
use App\InvoiceDetails;
use App\InvoiceItemDetails;

class CartController extends Controller {

	public function __construct() {
		$this->middleware('auth', ['except' => ['order_receipt', 'rt_log']]);
	}

	private function store_db_name($store_id)
	{	 
		if($store_id){
			$store     = Store::find($store_id);
		    $store_name= $store->store_db_name;
		    return $store_name;
		}else{
			return false;
		}
	} 

	public function add_to_cart(Request $request) 
	{
		// dd($request->all());
		$v_id = $request->v_id;
		$c_id = $request->c_id;
		$store_id = $request->store_id;
		//$product_id = $request->product_id;
		//$barcode = $request->barcode;
		$barcode = $request->ogbarcode;
		$qty = $request->qty;
		$unit_mrp = $request->unit_mrp;
		$unit_rsp = $request->unit_rsp;
		$r_price = $request->r_price;
		$s_price = $request->s_price;
		$discount = $request->discount;
		$user_remarks = $request->remarks;
		$pdata = urldecode($request->pdata);
		$spdata = urldecode($request->pdata);



		$all_data = json_encode($request->data);
		$product_response = urldecode($request->data['item_det']);
		$product_response = json_decode($product_response);
		$single_cart_data = [];
		// dd($product_response);
		// $product_response = json_decode($product_response);
		$pdata = json_decode($pdata);
		//dd($pdata);

		$taxs = [];

		$params = array('barcode'=>$barcode,'qty'=>$qty,'s_price'=>$s_price,'tax_code'=>$product_response->INVHSNSACMAIN_CODE,'store_id'=>$store_id);
		$tax_details = $this->taxCal($params);

		// dd($tax_details);

		$order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
		$order_id = $order_id + 1;

		$cart_list = Cart::where('item_id', '!=', $barcode)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('order_id', $order_id)->where('status', 'process')->get();

		// dd($cart_list);

		$cart_id = Cart::create([
			'store_id' => $store_id,
			'transaction_type' => 'sales',
			'v_id' => $v_id,
			'order_id' => $order_id,
			'user_id' => $c_id,
			'barcode' => $barcode,
			'item_name' => $barcode . ' ' . $product_response->DEPARTMENT_NAME,
			'item_id' => $barcode,
			'qty' => $qty,
			'unit_mrp' => $unit_mrp,
			'unit_csp' => $unit_rsp,
			'subtotal' => $r_price,
			'total' => $s_price,
			'trans_from'	=> $request->trans_from,
			'vu_id'	=> $request->vu_id,
			'discount' => $discount,
			'status' => 'process',
			'date' => date('Y-m-d'),
			'time' => date('H:i:s'),
			'month' => date('m'),
			'year' => date('Y'),
			'tax' => number_format($tax_details['tax'], 2),
			'department_id' => $product_response->DEPARTMENT_CODE,
			'group_id' => $product_response->SECTION_CODE,
			'division_id' => $product_response->DIVISION_CODE,
			'subclass_id' => $product_response->ARTICLE_CODE,
			'pdata' => $spdata,
			'tdata' => json_encode($tax_details),
			'section_target_offers' => $all_data,
			'remarks' => trim($user_remarks)
		]);

// dd($cart_list);

		    // dd('coming');
		foreach ($cart_list as $key => $cart) {
		    $single_cart_data['v_id'] = $v_id;
		    $single_cart_data['is_cart'] = 1;
		    $single_cart_data['is_update'] = 0;
		    $single_cart_data['store_id'] = $store_id;
		    $single_cart_data['c_id'] = $c_id;
		    $single_cart_data['trans_from'] = $cart->trans_from;
		    $single_cart_data['barcode'] = $cart->item_id;
		    $single_cart_data['qty'] = $cart->qty;
		    $single_cart_data['vu_id'] = $cart->vu_id;
		    $single_cart_data['mapping_store_id'] = $cart->store->mapping_store_id;
		    $item = DB::table($cart->store->store_db_name.'.invitem')->select('GRPCODE', 'INVARTICLE_CODE','BARCODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE', 'LISTED_MRP', 'DESC1', 'DESC2', 'DESC3', 'DESC4', 'DESC5', 'DESC6')->where('ICODE', $cart->item_id)->first();
		    $single_cart_data['item'] = $item;
		    $single_cart_data['store_db_name'] = $cart->store->store_db_name;
		    $carts = Cart::where('store_id', $cart->store_id)->where('v_id', $cart->v_id)->where('order_id', $cart->order_id)->where('user_id', $cart->user_id)->where('status', 'process')->get();
		    $single_cart_data['carts'] = $carts;
		    $promoC = new PromotionController;
		    $offer_data = $promoC->index($single_cart_data);
		    // dd($offer_data);
		    $data = (object)[ 'v_id' => $single_cart_data['v_id'], 'store_id' => $single_cart_data['store_id'], 'c_id' => $single_cart_data['c_id'], 'barcode' => $offer_data['barcode'], 'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'data' => $offer_data, 'trans_from' => $single_cart_data['trans_from'], 'vu_id' => $single_cart_data['vu_id'] ];
		    // dd($data);
		    // $cart = new CartController;
		    $this->update_to_cart($data);
		    // $this->process_each_item_in_cart($single_cart_data);
		}

		$cartD  = array('barcode'=>$barcode,'cart_id'=>$cart_id->cart_id,'pdata'=>$pdata);
		$this->addCartDetail($cartD);


		$carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();

		// $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id ];
		// $this->process_each_item_in_cart($params);
		$total_amount = format_number($carts->sum('total'));
		if ($request->has('get_data_of')) {
			if ($request->get_data_of == 'CART_DETAILS') {
				return $this->cart_details($request);
			} else if ($request->get_data_of == 'CATALOG_DETAILS') {
				$catalogC = new CatalogController;
				return $catalogC->getCatalog($request);
			}
		}

		return response()->json(['status' => 'add_to_cart', 'message' => 'Product was successfully added to your cart.', 'total_qty' => $carts->sum('qty'), 'total_amount' => $total_amount,
		], 200);
	}

	private function addCartDetail($params)
	{
		$cart_id  = $params['cart_id'];
		$barcode  = $params['barcode'];
		$pdata    = $params['pdata'];
		//echo count($pdata);
		//die;
		CartDetails::where('cart_id',$cart_id)->delete();
		if(count($pdata) > 0) {
			foreach ($pdata as $item) {

				if (empty($item->promo_code)) { $is_promo = 0;}
				else {$is_promo = 1;}
				$cartdetail  = new CartDetails();
				$cartdetail->cart_id = $cart_id;
				$cartdetail->barcode = $barcode;
				$cartdetail->qty     = $item->qty;
				$cartdetail->mrp     = $item->unit_mrp;
				$cartdetail->discount= $item->discount;
				$cartdetail->ext_price = $item->total;
				$cartdetail->price   = $item->unit_rsp;
				$cartdetail->ru_prdv = (isset($item->slab_code)) ? $item->slab_code : '';
				$cartdetail->promo_id= (isset($item->promo_code)) ? $item->promo_code : '';
				$cartdetail->is_promo= (isset($is_promo)) ? $is_promo : '';
				$cartdetail->message = $item->message;
				$cartdetail->save();
			}
		} else {
			$cart = Cart::find($cart_id);
			CartDetails::create([ 'cart_id' => $cart_id, 'barcode' => $barcode, 'qty' => $cart->qty, 'mrp' => $cart->unit_mrp, 'price' => $cart->unit_csp, 'discount' => $cart->discount, 'ext_price' => $cart->total, 'is_promo' => 0 ]);
		}
	}

	public function add_to_cart_by_qty($request)
	{
		// dd($request->all());
		$v_id = $request->v_id;
		$c_id = $request->c_id;
		$store_id = $request->store_id;
		//$product_id = $request->product_id;
		$barcode = $request->ogbarcode;
		$qty = $request->qty;
		$unit_mrp = $request->unit_mrp;
		$unit_rsp = $request->unit_rsp;
		$r_price = $request->r_price;
		$s_price = $request->s_price;
		$discount = $request->discount;
		$pdata = urldecode($request->pdata);
		$spdata = urldecode($request->pdata);
		$all_data = json_encode($request->data);
		$product_response = urldecode($request->data['item_det']);
		$product_response = json_decode($product_response);
		$single_cart_data = [];
		// dd($product_response);
		// $product_response = json_decode($product_response);
		$pdata = json_decode($pdata);
		$taxs = [];
		$params = array('barcode'=>$barcode,'qty'=>$qty,'s_price'=>$s_price,'tax_code'=>$product_response->INVHSNSACMAIN_CODE,'store_id'=>$store_id);
		$tax_details = $this->taxCal($params);

		// dd($barcode);

		$order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
		$order_id = $order_id + 1;

		$cart_list = Cart::where('item_id', '!=', $barcode)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('order_id', $order_id)->where('status', 'process')->get();

		// dd($cart_list);

		$cart_id = Cart::where('transaction_type', 'sales')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('status', 'process')->where('item_id', $barcode);
		$cart_id->update([
			'store_id' => $store_id,
			'transaction_type' => 'sales',
			'v_id' => $v_id,
			'order_id' => $order_id,
			'user_id' => $c_id,
			'barcode' => $barcode,
			'item_name' => $barcode . ' ' . $product_response->DEPARTMENT_NAME,
			'item_id' => $barcode,
			'qty' => $qty,
			'unit_mrp' => $unit_mrp,
			'unit_csp' => $unit_rsp,
			'subtotal' => $r_price,
			'total' => $s_price,
			'trans_from' => $request->trans_from,
			'vu_id'	=> $request->vu_id,
			'discount' => $discount,
			'status' => 'process',
			'date' => date('Y-m-d'),
			'time' => date('H:i:s'),
			'month' => date('m'),
			'year' => date('Y'),
			'tax' => number_format($tax_details['tax'], 2),
			'department_id' => $product_response->DEPARTMENT_CODE,
			'group_id' => $product_response->SECTION_CODE,
			'division_id' => $product_response->DIVISION_CODE,
			'subclass_id' => $product_response->ARTICLE_CODE,
			'pdata' => $spdata,
			'tdata' => json_encode($tax_details),
			'section_target_offers' => $all_data,
		]);
		$cartuse  = $cart_id->first();
		//dd($cartuse->cart_id);

		foreach ($cart_list as $key => $cart) {
		    $single_cart_data['v_id'] = $v_id;
		    $single_cart_data['is_cart'] = 1;
		    $single_cart_data['is_update'] = 0;
		    $single_cart_data['store_id'] = $store_id;
		    $single_cart_data['c_id'] = $c_id;
		    $single_cart_data['trans_from'] = $cart->trans_from;
		    $single_cart_data['barcode'] = $cart->item_id;
		    $single_cart_data['qty'] = $cart->qty;
		    $single_cart_data['vu_id'] = $cart->vu_id;
		    $single_cart_data['mapping_store_id'] = $cart->store->mapping_store_id;
		    $item = DB::table($cart->store->store_db_name.'.invitem')->select('GRPCODE', 'INVARTICLE_CODE','BARCODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE', 'LISTED_MRP', 'DESC1', 'DESC2', 'DESC3', 'DESC4', 'DESC5', 'DESC6')->where('ICODE', $cart->item_id)->first();
		    $single_cart_data['item'] = $item;
		    $single_cart_data['store_db_name'] = $cart->store->store_db_name;
		    $carts = Cart::where('store_id', $cart->store_id)->where('v_id', $cart->v_id)->where('order_id', $cart->order_id)->where('user_id', $cart->user_id)->where('status', 'process')->get();
		    $single_cart_data['carts'] = $carts;
		    $promoC = new PromotionController;
		    $offer_data = $promoC->index($single_cart_data);
		    // dd($offer_data);
		    $data = (object)[ 'v_id' => $single_cart_data['v_id'], 'store_id' => $single_cart_data['store_id'], 'c_id' => $single_cart_data['c_id'], 'barcode' => $offer_data['barcode'], 'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'data' => $offer_data, 'trans_from' => $single_cart_data['trans_from'], 'vu_id' => $single_cart_data['vu_id'] ];
		    // dd($data);
		    // $cart = new CartController;
		    $this->update_to_cart($data);
		    // $this->process_each_item_in_cart($single_cart_data);
		}


		$cartD  = array('barcode'=>$barcode,'cart_id'=>$cartuse->cart_id,'pdata'=>$pdata);
		$this->addCartDetail($cartD);

		// if (empty($pdata)) {

		// 	DB::table('cart_details')->insert([
		// 		'cart_id' => $cart_id,
		// 		'qty' => $qty,
		// 		'mrp' => $unit_mrp,
		// 		'price' => $r_price,
		// 		'is_promo' => 0,
		// 		'barcode' => $barcode,
		// 		// 'product_details'   => $all_data
		// 	]);

		// } else {

		// 	if (count($pdata) == 1) {

		// 		foreach ($pdata as $key => $value) {
		// 			if (empty($value->promo_code)) {
		// 				$is_promo = 0;
		// 			} else {
		// 				$is_promo = 1;
		// 			}
		// 			DB::table('cart_details')->insert([
		// 				'cart_id' => $cart_id,
		// 				'qty' => $value->qty,
		// 				'mrp' => $value->basic_price,
		// 				'discount' => $value->promotion,
		// 				'ext_price' => $value->sale_price,
		// 				'price' => $value->gross,
		// 				'ru_prdv' => (isset($value->slab_code)) ? $value->slab_code : '',
		// 				'promo_id' => (isset($value->promo_code)) ? $value->promo_code : '',
		// 				'is_promo' => (isset($is_promo)) ? $is_promo : '',
		// 				'barcode' => $barcode,
		// 				// 'product_details'   => $all_data
		// 			]);

		// 		}

		// 	} else {

		// 		foreach ($pdata as $key => $value) {
		// 			if (empty($value->promo_code)) {
		// 				$is_promo = 0;
		// 			} else {
		// 				$is_promo = 1;
		// 			}
		// 			DB::table('cart_details')->insert([
		// 				'cart_id' => $cart_id,
		// 				'qty' => $value->qty,
		// 				'mrp' => $value->basic_price,
		// 				'discount' => $value->promotion,
		// 				'ext_price' => $value->sale_price,
		// 				'price' => $value->gross,
		// 				'ru_prdv' => $value->slab_code,
		// 				'promo_id' => $value->promo_code,
		// 				'barcode' => $barcode,
		// 				// 'product_details'   => $all_data
		// 			]);

		// 		}

		// 	}

		// }

		//$cart = DB::table('cart')->where('cart_id', $cart_id)->first();

		$carts = DB::table('cart')->where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();

		$total_amount = format_number($carts->sum('total'));
		// $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id ];
		// $this->process_each_item_in_cart($params);
		if ($request->has('get_data_of')) {
			if ($request->get_data_of == 'CART_DETAILS') {
				return $this->cart_details($request);
			} else if ($request->get_data_of == 'CATALOG_DETAILS') {
				$catalogC = new CatalogController;
				return $catalogC->getCatalog($request);
			}
		}

		return response()->json(['status' => 'add_to_cart', 'message' => 'Product was successfully added to your cart.', 'total_qty' => $carts->sum('qty'), 'total_amount' => $total_amount,
		], 200);
	}

	public function update_to_cart($values) 
	{
		// dd($values->all());
		$v_id = $values->v_id;
		$c_id = $values->c_id;
		$store_id = $values->store_id;
		//$product_id = $values->product_id;
		$barcode = $values->barcode;
		$qty = $values->qty;
		$unit_mrp = $values->unit_mrp;
		$unit_rsp = $values->unit_rsp;
		$r_price = $values->r_price;
		$s_price = $values->s_price;
		$discount = $values->discount;
		$pdata = urldecode($values->pdata);
		$spdata = urldecode($values->pdata);
		$all_data = json_encode($values->data);
		$product_response = urldecode($values->data['item_det']);
		$product_response = json_decode($product_response);
		// $product_response = json_decode($product_response);
		$pdata = json_decode($pdata);
		$taxs = [];
		$params = array('barcode'=>$barcode,'qty'=>$qty,'s_price'=>$s_price,'tax_code'=>$product_response->INVHSNSACMAIN_CODE,'store_id'=>$store_id);


		$tax_details = $this->taxCal($params);
		// dd($pdata);

		$order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
		$order_id = $order_id + 1;

		$cart_id = Cart::where('transaction_type', 'sales')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('status', 'process')->where('item_id', $barcode)->update([
			'store_id' => $store_id,
			'transaction_type' => 'sales',
			'v_id' => $v_id,
			'order_id' => $order_id,
			'user_id' => $c_id,
			'barcode' => $barcode,
			'item_name' => $barcode . ' ' . $product_response->DEPARTMENT_NAME,
			'item_id' => $barcode,
			'qty' => $qty,
			'unit_mrp' => $unit_mrp,
			'unit_csp' => $unit_rsp,
			'subtotal' => $r_price,
			'total' => $s_price,
			'trans_from' => $values->trans_from,
			'vu_id'	=> $values->vu_id,
			'discount' => $discount,
			'status' => 'process',
			'date' => date('Y-m-d'),
			'time' => date('H:i:s'),
			'month' => date('m'),
			'year' => date('Y'),
			'tax' => number_format($tax_details['tax'], 2),
			'department_id' => $product_response->DEPARTMENT_CODE,
			'group_id' => $product_response->SECTION_CODE,
			'division_id' => $product_response->DIVISION_CODE,
			'subclass_id' => $product_response->ARTICLE_CODE,
			'pdata' => $spdata,
			'tdata' => json_encode($tax_details),
			'section_target_offers' => $all_data,
		]);
		// dd($cart_id);
		$cartD  = array( 'barcode' => $barcode , 'cart_id' => $cart_id, 'pdata' => $pdata);
		$this->addCartDetail($cartD);

		// dd($cart_id);

		// if (empty($pdata)) {

		// 	DB::table('cart_details')->insert([
		// 		'cart_id' => $cart_id,
		// 		'qty' => $qty,
		// 		'mrp' => $unit_mrp,
		// 		'price' => $r_price,
		// 		'is_promo' => 0,
		// 		'barcode' => $barcode,
		// 		// 'product_details'   => $all_data
		// 	]);

		// } else {

		// 	if (count($pdata) == 1) {

		// 		foreach ($pdata as $key => $value) {
		// 			if (empty($value->promo_code)) {
		// 				$is_promo = 0;
		// 			} else {
		// 				$is_promo = 1;
		// 			}
		// 			DB::table('cart_details')->insert([
		// 				'cart_id' => $cart_id,
		// 				'qty' => $value->qty,
		// 				'mrp' => $value->basic_price,
		// 				'discount' => $value->promotion,
		// 				'ext_price' => $value->sale_price,
		// 				'price' => $value->gross,
		// 				'ru_prdv' => (isset($value->slab_code)) ? $value->slab_code : '',
		// 				'promo_id' => (isset($value->promo_code)) ? $value->promo_code : '',
		// 				'is_promo' => (isset($is_promo)) ? $is_promo : '',
		// 				'barcode' => $barcode,
		// 				// 'product_details'   => $all_data
		// 			]);

		// 		}

		// 	} else {

		// 		foreach ($pdata as $key => $value) {
		// 			if (empty($value->promo_code)) {
		// 				$is_promo = 0;
		// 			} else {
		// 				$is_promo = 1;
		// 			}
		// 			DB::table('cart_details')->insert([
		// 				'cart_id' => $cart_id,
		// 				'qty' => $value->qty,
		// 				'mrp' => $value->basic_price,
		// 				'discount' => $value->promotion,
		// 				'ext_price' => $value->sale_price,
		// 				'price' => $value->gross,
		// 				'ru_prdv' => $value->slab_code,
		// 				'promo_id' => $value->promo_code,
		// 				'barcode' => $barcode,
		// 				// 'product_details'   => $all_data
		// 			]);

		// 		}

		// 	}

		// }

		//$cart = DB::table('cart')->where('cart_id', $cart_id)->first();

		$carts = DB::table('cart')->where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();

		return response()->json(['status' => 'add_to_cart', 'message' => 'Product quantity successfully Updated',
			//, 'data' => $cart
			'total_qty' => $carts->sum('qty'), 'total_amount' => $carts->sum('total'),
		], 200);
	}

	public function product_qty_update(Request $request)
	{
		dd($request->all());
		$v_id = $request->v_id;
		$c_id = $request->c_id;
		$store_id = $request->store_id;
		$trans_from = $request->trans_from;
		$vu_id = $request->vu_id;
		if($request->has('ogbarcode')){
			$barcode = $request->ogbarcode;
		}else{
			$barcode = $request->barcode;
		}
		

		$qty = $request->qty;
		$unit_mrp = $request->unit_mrp;
		$r_price = $request->r_price;
		$s_price = $request->s_price;
		$discount = $request->discount;

		$stores = Store::select('name', 'mapping_store_id' ,'store_db_name')->where('store_id', $store_id)->first();
		$store_name = $stores->name;
		$store_db_name = $stores->store_db_name;
		//Getting barcode without strore tagging
		$item = DB::table($store_db_name.'.invitem')->select('GRPCODE', 'INVARTICLE_CODE','BARCODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE', 'LISTED_MRP', 'DESC1', 'DESC2', 'DESC3', 'DESC4', 'DESC5', 'DESC6')->where('ICODE', $barcode)->first();

		$order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
		$order_id = $order_id + 1;

		$carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

		$promoC = new PromotionController;
		
		$params = ['v_id' => $v_id, 'trans_from' => $trans_from, 'barcode' => $barcode, 'qty' =>  $qty, 'mapping_store_id' => $stores->mapping_store_id , 'item' => $item, 'carts' => $carts , 'store_db_name' => $store_db_name, 'is_cart' => 1, 'is_update' => 1 ];
		
		$offer_data = $promoC->index($params);

		// $data = $offer_data;

		$data = (object)[ 'v_id' => $v_id, 'store_id' => $store_id, 'c_id' => $c_id, 'barcode' => $offer_data['barcode'], 'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'data' => $offer_data, 'trans_from' => $trans_from, 'vu_id' => $vu_id ];
		    // dd($data);
		    // $cart = new CartController;
		    $this->update_to_cart($data);

		$cart_list = Cart::where('item_id', '!=', $barcode)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('order_id', $order_id)->where('status', 'process')->get();

		foreach ($cart_list as $key => $cart) {
		    $single_cart_data['v_id'] = $v_id;
		    $single_cart_data['is_cart'] = 1;
		    $single_cart_data['is_update'] = 0;
		    $single_cart_data['store_id'] = $store_id;
		    $single_cart_data['c_id'] = $c_id;
		    $single_cart_data['trans_from'] = $cart->trans_from;
		    $single_cart_data['barcode'] = $cart->item_id;
		    $single_cart_data['qty'] = $cart->qty;
		    $single_cart_data['vu_id'] = $cart->vu_id;
		    $single_cart_data['mapping_store_id'] = $cart->store->mapping_store_id;
		    $item = DB::table($cart->store->store_db_name.'.invitem')->select('GRPCODE', 'INVARTICLE_CODE','BARCODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE', 'LISTED_MRP', 'DESC1', 'DESC2', 'DESC3', 'DESC4', 'DESC5', 'DESC6')->where('ICODE', $cart->item_id)->first();
		    $single_cart_data['item'] = $item;
		    $single_cart_data['store_db_name'] = $cart->store->store_db_name;
		    $carts = Cart::where('store_id', $cart->store_id)->where('v_id', $cart->v_id)->where('order_id', $cart->order_id)->where('user_id', $cart->user_id)->where('status', 'process')->get();
		    $single_cart_data['carts'] = $carts;
		    $promoC = new PromotionController;
		    $offer_data = $promoC->index($single_cart_data);
		    // dd($offer_data);
		    $data = (object)[ 'v_id' => $single_cart_data['v_id'], 'store_id' => $single_cart_data['store_id'], 'c_id' => $single_cart_data['c_id'], 'barcode' => $offer_data['barcode'], 'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'data' => $offer_data, 'trans_from' => $single_cart_data['trans_from'], 'vu_id' => $single_cart_data['vu_id'] ];
		    // dd($data);
		    // $cart = new CartController;
		    $this->update_to_cart($data);
		    // $this->process_each_item_in_cart($single_cart_data);
		}

		// dd($data);

		$carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status', 'process')->where('order_id', $order_id)->get();

		if ($request->has('get_data_of')) {
			if ($request->get_data_of == 'CART_DETAILS') {
				return $this->cart_details($request);
			} else if ($request->get_data_of == 'CATALOG_DETAILS') {
				$catalogC = new CatalogController;
				return $catalogC->getCatalog($request);
			}
		}

		return response()->json(['status' => 'product_qty_update', 'message' => 'Product quantity successfully Updated', 'total_qty' => $carts->sum('qty'), 'total_amount' =>(string) $carts->sum('total')], 200);
	}

	public function remove_product(Request $request) 
	{
		// dd($request->all());
		$v_id = $request->v_id;
		$c_id = $request->c_id;
		$store_id = $request->store_id;
		$v_id = $request->v_id;

		//$barcode = $request->barcode;
		if ($request->has('all')) {
			if ($request->all == 1) {
				$order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
				$order_id = $order_id + 1;
				// dd($order_id);

				$carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

				foreach ($carts as $key => $cart) {
					Cart::where('cart_id', $cart->cart_id)->delete();
					CartDetails::where('cart_id', $cart->cart_id)->delete();
					// DB::table('cart_offers')->where('cart_id' , $cart->cart_id)->delete();
				}

				DB::table('voucher_applied')->where('order_id', $order_id)->where('user_id', $c_id)->delete();


			}

		} else {

			if ($request->has('cart_id')) {
				$cart_id = $request->cart_id;
				Cart::where('cart_id', $cart_id)->delete();
				CartDetails::where('cart_id', $cart_id)->delete();

				

				$params = ['v_id' => $v_id, 'store_id' => $store_id, 'c_id' => $c_id, 'trans_from' => $request->trans_from, 'vu_id' => $request->vu_id];
				$cart_list = Cart::where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('vu_id', $request->vu_id)->where('trans_from', $request->trans_from)->where('status', 'process')->get();
				

				if($cart_list->isEmpty()){
					$order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
					$order_id = $order_id + 1;
					DB::table('voucher_applied')->where('order_id', $order_id)->where('user_id', $c_id)->delete();
				}

				foreach ($cart_list as $key => $cart) {
				    $single_cart_data['v_id'] = $v_id;
				    $single_cart_data['is_cart'] = 1;
				    $single_cart_data['is_update'] = 0;
				    $single_cart_data['store_id'] = $store_id;
				    $single_cart_data['c_id'] = $c_id;
				    $single_cart_data['trans_from'] = $cart->trans_from;
				    $single_cart_data['barcode'] = $cart->item_id;
				    $single_cart_data['qty'] = $cart->qty;
				    $single_cart_data['vu_id'] = $cart->vu_id;
				    $single_cart_data['mapping_store_id'] = $cart->store->mapping_store_id;
				    $item = DB::table($cart->store->store_db_name.'.invitem')->select('GRPCODE', 'INVARTICLE_CODE','BARCODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE', 'LISTED_MRP', 'DESC1', 'DESC2', 'DESC3', 'DESC4', 'DESC5', 'DESC6')->where('ICODE', $cart->item_id)->first();
				    $single_cart_data['item'] = $item;
				    $single_cart_data['store_db_name'] = $cart->store->store_db_name;
				    $carts = Cart::where('store_id', $cart->store_id)->where('v_id', $cart->v_id)->where('order_id', $cart->order_id)->where('user_id', $cart->user_id)->where('status', 'process')->get();
				    $single_cart_data['carts'] = $carts;
				    $promoC = new PromotionController;
				    $offer_data = $promoC->index($single_cart_data);
				    // dd($offer_data);
				    $data = (object)[ 'v_id' => $single_cart_data['v_id'], 'store_id' => $single_cart_data['store_id'], 'c_id' => $single_cart_data['c_id'], 'barcode' => $offer_data['barcode'], 'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'data' => $offer_data, 'trans_from' => $single_cart_data['trans_from'], 'vu_id' => $single_cart_data['vu_id'] ];
				    // dd($data);
				    // $cart = new CartController;
				    $this->update_to_cart($data);
				    // $this->process_each_item_in_cart($single_cart_data);
				}
				// $data = (object)[ 'v_id' => $v_id, 'store_id' => $store_id, 'c_id' => $c_id, 'barcode' => $offer_data['barcode'], 'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'data' => $offer_data, 'trans_from' => $single_cart_data['trans_from'], 'vu_id' => $single_cart_data['vu_id'] ];
				// $this->process_each_item_in_cart($params);
				// DB::table('cart_offers')->where('cart_id' , $cart_id)->delete();
			}

		}

		return response()->json(['status' => 'remove_product', 'message' => 'Product Removed'], 200);
	}

	public function cart_details(Request $request) {
		$v_id = $request->v_id;
		$c_id = $request->c_id;
		$store_id = $request->store_id;
		$trans_from = $request->trans_from;
		$carry_bag_added = false;
		$data = [];
		$total_subtotal = 0;
		$total_tax = 0;
		$total_discount = 0;
		$total_amount = 0;

		$order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
		$order_id = $order_id + 1;

		$cart = DB::table('cart')->where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status', 'process')->get();

		// dd($cart);

		$total_qty = 0;

		foreach ($cart as $key => $value) {
			$total_qty += $value->qty;

			//dd($value->item_id);

			//$carr_bag_arr = ['VR132797', 'VR132799', 'VR132807'];
			$carr_bag_arr = [];
			$carry_bags = DB::table('carry_bags')->where('v_id', $v_id)->where('store_id', $store_id)->where('status','1')->get();
			if($carry_bags->isEmpty()){
				//echo 'insdie this';exit;
				$carry_bags = DB::table('carry_bags')->where('v_id', $v_id)->where('store_id', '0')->where('status','1')->get();
			}
			//dd($carry_bags);
			if($carry_bags){
				
				$carr_bag_arr = $carry_bags->pluck('barcode')->all();
			}
			

			//$carry_bag_flag = in_array($value->item_id, $carr_bag_arr);
			$carry_bag_flag = in_array($value->item_id, $carr_bag_arr);

			if ($carry_bag_flag) {
				$carry_bag_added = true;
			}
			// dd(json_decode($value->section_target_offers));
			$product_details = json_decode($value->section_target_offers);
			// $productC = new  ProductController;
			// $fetchRequest = (object)[ 'v_id' => $value->v_id, 'trans_from' => $trans_from, 'store_id' => $value->store_id, 'barcode' => $value->barcode, 'c_id' => $value->user_id, 'scan' => 'TRUE', 'qty' => $value->qty ];
			// $response = $productC->product_details_by_cart($fetchRequest);
			// dd($response);
			
			$vendorS = new VendorSettingController;
            $product_default_image = $vendorS->getProductDefaultImage(['v_id' => $v_id , 'trans_from' => $trans_from]);

			$response['carry_bag_flag'] = $carry_bag_flag;
			$total_subtotal += $value->total;
			$total_tax += $value->tax;
			$total_discount += $value->discount;
			$total_amount += $value->subtotal;
			$data[] = array(
				'cart_id' => $value->cart_id,
				'product_data' => [
					'p_id' => $product_details->p_id,
					'category' => $product_details->category,
					'brand_name' => $product_details->brand_name,
					'sub_categroy' => $product_details->sub_categroy,
					'p_name' => $product_details->p_name,
					'offer' => $product_details->offer,
					'offer_data' => $product_details->offer_data,
					'multiple_price_flag' => $product_details->multiple_price_flag,
					'multiple_mrp' => $product_details->multiple_mrp,
					'unit_mrp' => $product_details->unit_mrp,
					'r_price' => $product_details->r_price,
					's_price' => $product_details->s_price,
					'discount' => $product_details->discount,
					'varient' => $product_details->varient,
					'images' => $product_default_image,
					'description' => $product_details->description,
					'deparment' => $product_details->deparment,
					'barcode' => $product_details->barcode,
					'whishlist' => 'No',
					'weight_flag' => false,
					'quantity_change_flag' => true,
					'carry_bag_flag' => $carry_bag_flag,
				],
				'amount' => (string) $product_details->s_price,
				'qty' => $value->qty,
				'tax_amount' => $value->tax,
				'delivery' => 'No',
				'tdata' => json_decode($value->tdata),
				'salesman_id' => $value->salesman_id
			);
		}
		// dd($data);

		$total_saving = $total_discount;
		$roundoff_total = round($total_subtotal);
		$total_qty = (int) $total_qty;
		$total_qty = (string) $total_qty;
		$bags = $this->get_carry_bags($request);
		$bags = $bags['data'];

		$voucher_array = [];
		$pay_by_voucher = 0;
		/*$vouchers = DB::table('voucher_applied as va')
			->join('voucher as v', 'v.id', 'va.voucher_id')
			->select('v.*','va.applied_amount')
			->where('va.user_id', $c_id)->where('va.v_id', $v_id)
			->where('va.store_id', $store_id)
			->where('va.order_id', $order_id)
			->where('va.status','PROCESS')->get();
		*/
		$vouchers = DB::table('voucher_applied')->select('id','voucher_id','applied_amount')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('order_id', $order_id)->get();

		$voucher_total = 0;
		$pay_by_voucher = 0;
		foreach ($vouchers as $key => $voucher) {
			$voucher_applied = DB::table('voucher_applied')->where('voucher_id', $voucher->voucher_id)->where('status', 'APPLIED')->get();
			$totalVoucher = DB::table('voucher')->where('id', $voucher->voucher_id)->first()->amount;
			$voucher_remain_amount = $totalVoucher - $voucher_applied->sum('applied_amount');

			array_push($voucher_array, ['name' => 'Voucher Credit', 'amount' => $voucher_remain_amount]);
			
			$voucher_total += $voucher_remain_amount;
			if ($roundoff_total >= $voucher_remain_amount) {
				$voucher_applied_amount = $voucher_remain_amount;
				$pay_by_voucher += $voucher_remain_amount;
				$roundoff_total = $roundoff_total - $voucher_remain_amount;
			} else {
				$voucher_applied_amount = $roundoff_total;
				$pay_by_voucher += $roundoff_total;
				$roundoff_total = 0;
			}

			DB::table('voucher_applied')->where('id', $voucher->id)->update(['status' => 'PROCESS' , 'applied_amount' => $voucher_applied_amount ]);
		}
		$voucher_total = $pay_by_voucher;
		// dd($data);

		// $carr_bag_arr =  [ 'VR132797', 'VR132799' ,'VR132807'];
		// $carry_bag_flag = in_array($cart->barcode, $carr_bag_arr);

		// if($carry_bag_flag){
		// $carry_bag_added = true;
		// }
		$vendorS = new VendorSettingController;
		$product_max_qty = $vendorS->getProductMaxQty(['v_id' => $v_id, 'trans_from' => $trans_from]);
		$cart_max_item = $vendorS->getMaxItemInCart(['v_id' => $v_id, 'trans_from' => $trans_from]);

		$paymentTypeSettings = $vendorS->getPaymentTypeSetting(['v_id' => $v_id, 'trans_from' => $trans_from]);

		return response()->json(['status' => 'cart_details', 'message' => 'Your Cart Details',
			'order_id' => $order_id,
			'payment_type' => $paymentTypeSettings,
			'cart_max_item' => (string) $cart_max_item,
			'product_max_qty' => (string) $product_max_qty,
			'data' => $data,
			'product_image_link' => product_image_link(),
			//'offer_data' => $global_offer_data,
			'carry_bag_added' => $carry_bag_added,
			'bags' => $bags,
			'sub_total' => (string) format_number($total_amount),
			'tax_total' =>  (string) format_number($total_tax),
			'bill_buster_discount' => '0.00',
			'discount' => (string) format_number($total_discount),
			//'grand_total' => (format_number($grand_total))?format_number($grand_total):'0.00',
			'order_id' => $order_id,
			'carry_bag_total' => '0.00',
			'voucher_total' => $voucher_total,
			'vouchers' => $voucher_array,
			'pay_by_voucher' => (string) format_number($pay_by_voucher),
			'total' => (string) format_number($roundoff_total),
			'cart_qty_total' => $total_qty,
			'saving' => (string) format_number($total_saving),
			'delivered' => 'No',
			'offered_mount' => '0.00'], 200);
	}

	public function process_to_payment(Request $request) {
		$v_id = $request->v_id;
		$c_id = $request->c_id;
		$store_id = $request->store_id;
		$subtotal = $request->sub_total;
		$discount = $request->discount;
		$pay_by_voucher = $request->pay_by_voucher;
		$trans_from = $request->trans_from;

		if ($request->has('payment_gateway_type')) {
			$payment_gateway_type = $request->payment_gateway_type; //'EZETAP'
		} else {
			$payment_gateway_type = 'RAZOR_PAY';
		}

		$vu_id = 0;
		if ($request->has('vu_id')) {
			$vu_id = $request->vu_id;
		}

		//Checking Opening balance has entered or not if payment is through cash
		if ($vu_id > 0 && $payment_gateway_type == 'CASH') {
			$vendorSett = new \App\Http\Controllers\VendorSettlementController;
			$response = $vendorSett->opening_balance_status($request);
			if ($response) {
				return $response;
			}
		}

		$bill_buster_discount = $request->bill_buster_discount;
		$tax = $request->tax_total;
		$total = $request->total;
		$trans_from = $request->trans_from;

		$t_order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
		$t_order_id = $t_order_id + 1;
		$order_id = order_id_generate($store_id, $c_id, $trans_from);
		$custom_order_id = custom_order_id_generate(['store_id' => $store_id, 'user_id' => $c_id, 'trans_from' => $trans_from]);

		$order = new Order;

		$order->order_id = $order_id;
		$order->custom_order_id = $custom_order_id;
		$order->o_id = $t_order_id;
		$order->v_id = $v_id;
		$order->store_id = $store_id;
		$order->user_id = $c_id;
		$order->trans_from = $trans_from;
		$order->subtotal = $subtotal;
		$order->discount = $discount;
		$order->bill_buster_discount = $bill_buster_discount;
		$order->tax = $tax;
		$order->total = $total + $pay_by_voucher;

		$order->status = 'process';
		$order->date = date('Y-m-d');
		$order->time = date('h:i:s');
		$order->month = date('m');
		$order->year = date('Y');
		$order->payment_type = 'full';
		$order->payment_via = $payment_gateway_type;
		$order->is_invoice = '0';
		$order->vu_id = $vu_id;

		$order->save();

		$cart_data = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $t_order_id)->where('user_id', $c_id)->get()->toArray();

		$porder_id = $order->od_id;

		foreach ($cart_data as $value) {
			$cart_details_data = CartDetails::where('cart_id', $value['cart_id'])->get()->toArray();
			$save_order_details = array_except($value, ['cart_id']);
			$save_order_details = array_add($value, 't_order_id', $porder_id);
			$order_details = OrderDetails::create($save_order_details);
			foreach ($cart_details_data as $cdvalue) {
				$save_order_item_details = array_add($cdvalue, 'porder_id', $order_details->id);
				OrderItemDetails::create($save_order_item_details);
			}
		}

		$vSetting = new VendorSettingController;
		$voucherSetting = $vSetting->getVoucherSetting(['v_id' => $v_id , 'trans_from' => $trans_from]);
		$voucherUsedType = null;
		if(isset($voucherSetting->status) &&  $voucherSetting->status ==1){

			$vouchers = DB::table('voucher_applied')->select('id','voucher_id','applied_amount')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('order_id', $t_order_id)->get();
			$voucherUsedType = $voucherSetting->used_type;
			foreach($vouchers as $voucher) {
				$totalVoucher = 0;
				$vou = DB::table('voucher')->select('amount')->where('id', $voucher->voucher_id)->first();
				$totalVoucher = $vou->amount;
				$previous_applied = DB::table('voucher_applied')->select('applied_amount')->where('voucher_id' , $voucher->voucher_id)->get();
				$totalAppliedAmount = $previous_applied->sum('applied_amount');

				if( $voucherUsedType == 'PARTIAL' ){
					if( $vou->amount ==  $totalAppliedAmount ){
						DB::table('voucher')->where('id', $voucher->voucher_id)->update(['status' => 'used']);
					}else if($totalAppliedAmount > $vou->amount){
						DB::table('voucher')->where('id', $voucher->voucher_id)->update(['status' => 'used']);
					}else{
						DB::table('voucher')->where('id', $voucher->voucher_id)->update(['status' => 'partial']);
					}
				}else{

					DB::table('voucher')->where('id', $voucher->voucher_id)->update(['status' => 'used']);
				}

				DB::table('voucher_applied')->where('id', $voucher->id)->update(['status' => 'APPLIED' ]);
			}
		}

		$payment = null;
		if ($pay_by_voucher > 0.00 && $total == 0.00) {

			$request->request->add(['t_order_id' => $t_order_id, 'order_id' => $order_id, 'pay_id' => 'user_order_id_' . $t_order_id, 'method' => 'vmart_credit', 'invoice_id' => '', 'bank' => '', 'wallet' => '', 'vpa' => '', 'error_description' => '', 'status' => 'success', 'payment_gateway_type' => 'Voucher', 'cash_collected' => '', 'cash_return' => '', 'amount' => $pay_by_voucher]);

			return $this->payment_details($request);

		} else if ($pay_by_voucher > 0.00 && $total > 0.00) {

			$payment = new Payment;
			$payment->store_id = $store_id;
			$payment->v_id = $v_id;
			//$payment->t_order_id = 0;
			$payment->order_id = $order_id;
			$payment->user_id = $c_id;
			$payment->pay_id = 'user_order_id_' . $t_order_id;
			$payment->amount = $pay_by_voucher;
			$payment->method = 'voucher_credit';
			//$payment->invoice_id = '';
			$payment->payment_invoice_id = '';
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

		$order = array_add($order, 'order_id', $porder_id);

		return response()->json(['status' => 'proceed_to_payment', 'message' => 'Proceed to Payment', 'data' => $order], 200);
	}

	public function payment_details(Request $request) {
		$v_id = $request->v_id;
		$store_id = $request->store_id;
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
		$trans_from = $request->trans_from;
		$payment_type = 'full';
		$cash_collected = null;
		$cash_return = null;
		$gateway_response = null;
		$payment_invoice_id = null;

		$orders = Order::where('order_id', $order_id)->first();

		if ($orders->payment_type != 'full') {
			$payment_type = 'partial';
		}

		$vu_id = 0;
		if ($request->has('vu_id')) {
			$vu_id = $request->vu_id;
		}

		$payment_save_status = false;
		if ($request->has('payment_gateway_type')) {
			$payment_gateway_type = $request->payment_gateway_type; //'EZETAP'
		} else {
			$payment_gateway_type = 'RAZOR_PAY';
		}

		if ($payment_gateway_type == 'RAZOR_PAY') {

			$api_key = env('RAZORPAY_API_KEY');
			$api_secret = env('RAZORPAY_API_SECERET');

			$api = new Api($api_key, $api_secret);
			$razorAmount = $amount * 100;
			$razorpay_payment = $api->payment->fetch($pay_id)->capture(array('amount' => $razorAmount)); // Captures a payment

			if ($razorpay_payment) {

				if ($razorpay_payment->status == 'captured') {

					// $date = date('Y-m-d');
					// $time = date('h:i:s');
					

					// $payment->store_id = $store_id;
					// $payment->v_id = $v_id;
					// $payment->t_order_id = $t_order_id;
					// $payment->order_id = $order_id;
					// $payment->user_id = $user_id;
					// $payment->pay_id = $pay_id;
					// $payment->amount = $amount;
					$method = $razorpay_payment->method;
					$payment_invoice_id = $razorpay_payment->invoice_id;
					$bank = $razorpay_payment->bank;
					$wallet = $razorpay_payment->wallet;
					$vpa = $razorpay_payment->vpa;
					// $payment->error_description = $error_description;
					// $payment->status = $status;
					// $payment->date = date('Y-m-d');
					// $payment->time = date('h:i:s');
					// $payment->month = date('m');
					// $payment->year = date('Y');

					// $payment->save();

					$payment_save_status = true;

				}

			}

		} else if ($payment_gateway_type == 'EZETAP') {

			//$t_order_id = $request->t_order_id;
			// $pay_id = $request->pay_id; //tnx->txnId
			// $amount = $request->amount; //tnx->amount
			// $method = $request->method; //tnx->paymentMode
			// $invoice_id = $request->invoice_id; //tnx->invoiceNumber
			// $status = $request->status; // $gateway_response->status

			// $date = date('Y-m-d');
			// $time = date('h:i:s');
			// $payment = new Payment;

			$gateway_response = $request->gateway_response;

			$gateway_response = json_decode($gateway_response);

			//dd($gateway_response->result);
			//var_dump($gateway_response->result->txn);
			if (!empty($gateway_response)) {
				$status = $gateway_response->status;
				$tnx = $gateway_response->result->txn;

				$pay_id = $tnx->txnId; //tnx->txnId
				$amount = $tnx->amount; //tnx->amount
				$method = $tnx->paymentMode; //tnx->paymentMode
				$invoice_id = $tnx->invoiceNumber; //tnx->invoiceNumber
			}

			// $payment->store_id = $store_id;
			// $payment->v_id = $v_id;
			// //$payment->t_order_id = $t_order_id;
			// $payment->order_id = $order_id;
			// $payment->user_id = $user_id;
			// $payment->pay_id = $pay_id;
			// $payment->amount = $amount;
			// $payment->method = $method;
			// $payment->invoice_id = $invoice_id;
			// $payment->status = $status;
			// $payment->payment_gateway_type = $payment_gateway_type;
			// $payment->gateway_response = json_encode($gateway_response);
			// $payment->date = date('Y-m-d');
			// $payment->time = date('h:i:s');
			// $payment->month = date('m');
			// $payment->year = date('Y');

			// $payment->save();

			$payment_save_status = true;

		} else if ($payment_gateway_type == 'EZSWYPE') {

			//$t_order_id = $request->t_order_id;
			// $pay_id = $request->pay_id; //tnx->txnId
			// $amount = $request->amount; //tnx->amount
			// $method = $request->method; //tnx->paymentMode
			// $invoice_id = $request->invoice_id; //tnx->invoiceNumber
			// $status = $request->status; // $gateway_response->status

			if ($method != 'card' && $method != 'cash') {
				$method = 'wallet';
			}

			// $date = date('Y-m-d');
			// $time = date('h:i:s');
			// $payment = new Payment;

			$gateway_response = $request->gateway_response;

			$gateway_response = json_decode($gateway_response);

			//dd($gateway_response->result);
			//var_dump($gateway_response->result->txn);

			// $payment->store_id = $store_id;
			// $payment->v_id = $v_id;
			// //$payment->t_order_id = $t_order_id;
			// $payment->order_id = $order_id;
			// $payment->user_id = $user_id;
			// $payment->pay_id = $pay_id;
			// $payment->amount = $amount;
			// $payment->method = $method;
			// $payment->invoice_id = $invoice_id;
			// $payment->status = $status;
			// $payment->payment_gateway_type = $payment_gateway_type;
			// $payment->gateway_response = json_encode($gateway_response);
			// $payment->date = date('Y-m-d');
			// $payment->time = date('h:i:s');
			// $payment->month = date('m');
			// $payment->year = date('Y');

			// $payment->save();

			$payment_save_status = true;

		} else {

			//$t_order_id = $request->t_order_id;
			// $pay_id = $request->pay_id; //tnx->txnId
			// $amount = $request->amount; //tnx->amount
			$cash_collected = $request->cash_collected;
			$cash_return = $request->cash_return;
			// $method = $request->method; //tnx->paymentMode
			// $invoice_id = $request->invoice_id; //tnx->invoiceNumber
			// $status = $request->status; // $gateway_response->status

			// $date = date('Y-m-d');
			// $time = date('h:i:s');
			// $payment = new Payment;

			// $payment->store_id = $store_id;
			// $payment->v_id = $v_id;
			// //$payment->t_order_id = $t_order_id;
			// $payment->order_id = $order_id;
			// $payment->user_id = $user_id;
			// $payment->pay_id = $pay_id;
			// $payment->amount = $amount;
			// $payment->method = $method;
			// $payment->cash_collected = $cash_collected;
			// $payment->cash_return = $cash_return;
			// $payment->invoice_id = $invoice_id;
			// $payment->status = $status;
			// $payment->payment_gateway_type = $payment_gateway_type;
			// //$payment->gateway_response = json_encode($gateway_response);
			// $payment->date = date('Y-m-d');
			// $payment->time = date('h:i:s');
			// $payment->month = date('m');
			// $payment->year = date('Y');

			// $payment->save();

			$payment_save_status = true;

		}

		// dd($razorpay_payment);
		//$razorpay_payment = (object)$razorpay_payment = ['status' => 'captured', 'method'=>'cart','invoice_id' => '', 'wallet'=> '' , 'vpa' =>''];

		$payment = new Payment;

		$payment->store_id = $store_id;
		$payment->v_id = $v_id;
		$payment->order_id = $order_id;
		$payment->user_id = $user_id;
		$payment->pay_id = $pay_id;
		$payment->amount = $amount;
		$payment->method = $method;
		$payment->cash_collected = $cash_collected;
		$payment->cash_return = $cash_return;
		$payment->payment_invoice_id = $invoice_id;
		$payment->bank = $bank;
		$payment->wallet = $wallet;
		$payment->vpa = $vpa;
		$payment->error_description = $error_description;
		$payment->status = $status;
		$payment->payment_type = $payment_type;
		$payment->payment_gateway_type = $payment_gateway_type;
		$payment->gateway_response = json_encode($gateway_response);
		$payment->date = date('Y-m-d');
		$payment->time = date('H:i:s');
		$payment->month = date('m');
		$payment->year = date('Y');

		$payment->save();

		if ($status == 'success') {

			// if($razorpay_payment->status == 'captured'){

			// $order_id = order_id_generate($store_id, $c_id, $trans_from);
			// $custom_order_id = custom_order_id_generate(['store_id' => $store_id, 'user_id' => $c_id, 'trans_from' => $trans_from]);

			// ----- Generate Order ID & Update Order status on orders and orders details -----

			// $new_order_id = order_id_generate($store_id, $user_id, $trans_from);
			// $custom_order_id = custom_order_id_generate(['store_id' => $store_id, 'user_id' => $user_id, 'trans_from' => $trans_from]);

			// $orders->update([ 'order_id' => $new_order_id, 'custom_order_id' => $custom_order_id, 'status' => 'success' ]);
			$orders->update([ 'status' => 'success', 'verify_status' => '1', 'verify_status_guard' => '1' ]);

			OrderDetails::where('t_order_id', $orders->od_id)->update([ 'status' => 'success' ]);	

			// ----- Generate Invoice -----

			$zwing_invoice_id = invoice_id_generate($store_id, $user_id, $trans_from);
			$custom_invoice_id = custom_invoice_id_generate(['store_id' => $store_id, 'user_id' => $user_id, 'trans_from' => $trans_from]);
			// dd($zwing_invoice_id);

			if ($payment_type == 'full') {
				
				$invoice = new Invoice;

				$invoice->invoice_id = $zwing_invoice_id;
				$invoice->custom_order_id = $custom_invoice_id;
				$invoice->ref_order_id = $orders->order_id;
				$invoice->transaction_type = $orders->transaction_type;
				$invoice->v_id = $v_id;
				$invoice->store_id = $store_id;
				$invoice->user_id = $user_id;
				$invoice->subtotal = $orders->subtotal;
				$invoice->discount = $orders->discount;
				$invoice->tax = $orders->tax;
				$invoice->total = $orders->total;
				$invoice->trans_from = $trans_from;
				$invoice->vu_id = $vu_id;
				$invoice->date = date('Y-m-d');
				$invoice->time = date('H:i:s');
				$invoice->month = date('m');
				$invoice->year = date('Y');

				$invoice->save();

				$payment->update([ 'invoice_id' => $zwing_invoice_id ]);



			} elseif ($payment_type == 'partial') {
				// For the partial 
			}

			// ------ Copy Order Details & Order Item Details to Invoice Details & Invoice Item Details ------

			$pinvoice_id = $invoice->id;

			$order_data = OrderDetails::where('t_order_id', $orders->od_id)->get()->toArray();

			foreach ($order_data as $value) {

				$value['t_order_id']  = $invoice->id;
				$save_invoice_details = $value;

				//$save_invoice_details = array_except($value, ['t_order_id']);
				//$save_invoice_details = array_add($value, 't_order_id', $pinvoice_id);
				$invoice_details_data = InvoiceDetails::create($save_invoice_details);
				$order_details_data = OrderItemDetails::where('porder_id', $value['id'])->get()->toArray();
				foreach ($order_details_data as $indvalue) {
					$save_invoice_item_details = array_add($indvalue, 'pinvoice_id', $invoice_details_data->id);
					InvoiceItemDetails::create($save_invoice_item_details);
				}
			}

			// Delete Date From Cart

			$cart_id_list = Cart::where('order_id', $orders->o_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $user_id)->get(['cart_id']);

			CartDetails::whereIn('cart_id', $cart_id_list)->delete();

			Cart::whereIn('cart_id', $cart_id_list)->delete();

			// $porder_id = $order->od_id;

			// foreach ($cart_data as $value) {
			// 	$cart_details_data = CartDetails::where('cart_id', $value['cart_id'])->get()->toArray();
			// 	$save_order_details = array_except($value, ['cart_id']);
			// 	$save_order_details = array_add($value, 't_order_id', $porder_id);
			// 	$order_details = OrderDetails::create($save_order_details);
			// 	foreach ($cart_details_data as $cdvalue) {
			// 		$save_order_item_details = array_add($cdvalue, 'porder_id', $order_details->id);
			// 		OrderItemDetails::create($save_order_item_details);
			// 	}
			// }



			



			// $last_transaction_no = 0;
			// $store = Store::where('v_id', $v_id)->where('store_id', $store_id)->first();
			// $order = Order::where('v_id', $v_id)->where('store_id', $store_id)->where('status', 'success')->orderBy('od_id', 'desc')->first();
			// if (empty($order->transaction_no)) {
			// 	# code...
			// } else {
			// 	$last_transaction_no = $order->transaction_no;
			// }

			// $current_invoice_name = '';
			// if (empty($order->invoice_name)) {
			// 	# code...
			// } else {
			// 	$last_invoice_name = $order->invoice_name;
			// 	if ($last_invoice_name) {
			// 		$arr = explode('_', $last_invoice_name);
			// 		$id = $arr[2] + 1;
			// 		$current_invoice_name = $date . $time . '_' . $store->mapping_store_id . '_' . $store_id . '_' . $id . '.pdf';
			// 	} else {
			// 		$current_invoice_name = $date . $time . '_' . $store->mapping_store_id . '_' . $store_id . '_1.pdf';
			// 	}
			// }

			//Order::where('order_id', $order_id)->update(['status' => $status]);
			// $ord = Order::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->first();
			// if ($request->has('address_id')) {
			// 	$orders->address_id = $request->address_id;
			// }
			// $orders->invoice_name = $current_invoice_name;
			// $orders->transaction_no = $last_transaction_no + 1;

			// if ($vu_id > 0) {
			// 	$orders->vu_id = $vu_id;
			// 	$orders->verify_status = '1';
			// 	$orders->verify_status_guard = '1';
			// }

			// $orders->status = 'success';
			// $orders->save();



			// Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $ord->o_id)->where('user_id', $user_id)->update(['status' => $status]);
			// DB::table('cart')->where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $ord->o_id)->where('user_id', $user_id)->update(['status' => 'success']);

			// $carts = DB::table('cart')->where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $ord->o_id)->where('user_id', $user_id)->get();



			// $invoice_id = invoice_id_generate($store_id, $user_id, $trans_from);
			// $custom_invoice_id = custom_invoice_id_generate(['store_id' => $store_id, 'user_id' => $user_id, 'trans_from' => $trans_from]);
			// array_set($orders, 'order_id', $invoice_id);
			// array_set($orders, 'custom_order_id', $custom_invoice_id);
			// Invoice::create($orders);
			//dd($ord);

			// $html = $this->order_receipt($user_id , $v_id, $store_id, $order_id);
			// $pdf = PDF::loadHTML($html);
			// $path =  storage_path();
			// $complete_path = $path."/app/invoices/".$current_invoice_name;
			// $pdf->setWarnings(false)->save($complete_path);

			$payment_method = (isset($payment->method)) ? $payment->method : '';

			$user = Auth::user();
			// Mail::to($user->email)->send(new OrderCreated($user,$ord,$carts,$payment_method,  $complete_path));

			return response()->json(['status' => 'payment_save', 'redirect_to_qr' => true, 'message' => 'Save Payment', 'data' => $payment], 200);

			// }

		} else if($status == 'failed' || $status == 'error') {

			// ----- Generate Order ID & Update Order status on orders and orders details -----

			// $new_order_id = order_id_generate($store_id, $user_id, $trans_from);
			// $custom_order_id = custom_order_id_generate(['store_id' => $store_id, 'user_id' => $user_id, 'trans_from' => $trans_from]);

			// $orders->update([ 'order_id' => $new_order_id, 'custom_order_id' => $custom_order_id, 'status' => $status ]);
			$orders->update([ 'status' => $status ]);

			OrderDetails::where('t_order_id', $orders->od_id)->update([ 'status' => $status ]);

		}
	}

	public function order_qr_code(Request $request) {
		$order_id = $request->order_id;
		$qrCode = new QrCode($order_id);
		header('Content-Type: image/png');
		echo $qrCode->writeString();
	}

	public function order_pre_verify_guide(Request $request) {
		$v_id = $request->v_id;
		$c_id = $request->c_id;
		$store_id = $request->store_id;
		$order_id = $request->order_id;

		$o_id = Order::where('order_id', $order_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->first();

		$message_data = [];

		$message_data['title'][] = ['message' => 'Thank You for Shopping!'];
		$message_data['body'][] = ['message' => 'Please proceed with your purchase to'];
		if ($o_id->qty <= 5) {
			$message_data['body'][] = ['message' => 'The exit and show your'];
			$message_data['body'][] = ['message' => 'QR Receipt to the guard'];
		} else if ($o_id->qty > 5 && $o_id->qty <= 15) {
			$message_data['body'][] = ['message' => 'ZWING Packing Zone 5', 'bold_flag' => true];
			$message_data['body'][] = ['message' => 'near Aisle 5'];
		} else {
			$message_data['body'][] = ['message' => 'ZWING Express Counter', 'bold_flag' => true, 'italic_flag' => true];
			$message_data['body'][] = ['message' => 'for packing'];
		}

		return response()->json(['status' => 'pre_verify_screen', 'message' => 'Order Details Details', 'data' => $message_data]);
	}

	public function order_details(Request $request) {
		// dd($request->all());
		$v_id 			= $request->v_id;
		$c_id 			= $request->c_id;
		$store_id 		= $request->store_id;
		$order_id 		= $request->order_id;
		$store_db_name 	= $this->store_db_name($store_id);
		$trans_from 	= $request->trans_from;
		$cart_qty_total = 0;

		$vu_id = 0;
		if ($request->has('vu_id')) {
			$vu_id = $request->vu_id;
		} else if ($request->has('c_id')) {
			$c_id = $request->c_id;
		}

		$item_qty = 0;
		// dd($request->all());
		$stores = DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();

		$o_id = Order::where('order_id', $order_id)->where('v_id', $v_id)->where('store_id', $store_id)->get();

		if ($vu_id > 0) {
			$o_id = $o_id->where('vu_id', $vu_id)->first();
		} else {
			$o_id = $o_id->where('user_id', $c_id)->first();
		}

		$c_id = $o_id->user_id;
		$user_api_token = $o_id->user->api_token;
		$customer_number = $o_id->user->mobile;
		$payment_via = $o_id->payment->method;

		$order = Order::where('order_id', $order_id)->first();

		$carts = OrderDetails::where('t_order_id', $order->od_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->get();

		$cart_qty_total = $carts->sum('qty');
		$data = [];



		//For Return operation only
		$return_items = [];
		if($order->transaction_type == 'sales'){
			
			$return_order = DB::table('orders as o')

							->join('order_details as od', 'od.t_order_id', 'o.od_id')
							->where('o.ref_order_id' , $order->order_id)
							->where('o.transaction_type','return')
							//->select('od.*')
							 ->selectRaw('sum(qty) as sum, od.*')
							->get();

			$return_items = $return_order->pluck('sum','item_id')->all();
		}

		//dd($return_items);

		foreach ($carts as $key => $value) {
			$applied_offer = [];
			$available_offer = [];
			//$item_details = vmartCategory($value->barcode);
			
			//$carr_bag_arr = ['VR132797', 'VR132799', 'VR132807'];
			$carr_bag_arr = [];
			$carry_bags = DB::table('carry_bags')->where('v_id', $v_id)->where('store_id', $store_id)->where('status','1')->get();
			if($carry_bags->isEmpty()){
				//echo 'insdie this';exit;
				$carry_bags = DB::table('carry_bags')->where('v_id', $v_id)->where('store_id', '0')->where('status','1')->get();
			}
			//dd($carry_bags);
			if($carry_bags){
				
				$carr_bag_arr = $carry_bags->pluck('barcode')->all();
			}

			$carry_bag_flag = in_array($value->item_id, $carr_bag_arr);

			$product_details = json_decode($value->section_target_offers);

			$vendorS = new VendorSettingController;
            $product_default_image = $vendorS->getProductDefaultImage(['v_id' => $v_id , 'trans_from' => $trans_from]);

			// $data['cart_id'] = $value->cart_id;
			$product_data = array(
				'return_flag' => false,
				'return_qty' => 0,
				'carry_bag_flag' => $carry_bag_flag,
				'isProductReturn' => false,
				'p_id' => $value->barcode,
				'category' => $product_details->category,
				'brand_name' => $product_details->brand_name,
				'sub_categroy' => $product_details->sub_categroy,
				'whishlist' => 'No',
				'weight_flag' => false,
				'p_name' => $value->item_name,
				'offer' => $product_details->offer,
				'offer_data' => $product_details->offer_data,
				'multiple_price_flag' => $product_details->multiple_price_flag,
				'multiple_mrp' => $product_details->multiple_mrp,
				'unit_mrp' => $product_details->unit_mrp,
				'r_price' => $product_details->r_price,
				's_price' => $product_details->s_price,
				'discount' => $product_details->discount,
				'varient' => $product_details->varient,
				'images' => $product_default_image,
				'description' => $product_details->description,
				'deparment' => $product_details->deparment,
				'barcode' => $product_details->barcode,
			);
			// $data['amount'] = vformat_and_string($value->total);
			// $data['qty'] = (string)$value->qty;
			// $data['return_product_qty'] = '';
			// $data['tax_amount'] = '';
			// $data['delivery'] = 'No';
			// $data['item_flag'] = 'NORMAL';
			 
			$return_product_qty = $value->qty;
			if (isset($return_items[$value->item_id]) ) {
				$return_product_qty = $value->qty - $return_items[$value->item_id];
			}
			$data[] = [
				'cart_id' => $value->cart_id,
				'product_data' => $product_data,
				'amount' => vformat_and_string($value->total),
				'qty' => (string) $value->qty,
				'return_product_qty' => (string)$return_product_qty,
				'tax_amount' => '',
				'delivery' => 'No',
				'item_flag' => 'NORMAL',
				'salesman_id' => $value->salesman_id
			];
			$item_qty = $value->qty;
		}

		$paymentMethod = Payment::where('v_id', $o_id->v_id)->where('store_id', $o_id->store_id)->where('order_id', $o_id->order_id)->get()->pluck('method')->all();

		return response()->json(['status' => 'order_details', 'message' => 'Order Details Details',
			'payment_method' => implode(',', $paymentMethod),
			'mobile' => $o_id->user->mobile,
			'data' => $data, 'return_req_process' => [], 'return_req_approved' => [], 'product_image_link' => product_image_link(), 'return_request_flag' => false, 'bags' => [], 'carry_bag_total' => '0.00', 'sub_total' => $order->subtotal, 'tax_total' => '0.00', 'tax_details' => '', 'discount' => $order->discount, 'date' => $order->date, 'time' => $order->time, 'order_id' => $order->order_id, 'total' => $order->total, 'cart_qty_total' => (string) $cart_qty_total, 'saving' => vformat_and_string($order->subtotal - $order->total), 'store_address' => $stores->address1, 'store_timings' => '', 'delivered' => 'No', 'address' => (object) [], 'c_id' => $c_id, 'user_api_token' => $user_api_token, 'customer' => $customer_number, 'payment_via' => $payment_via], 200);

		//dd($return_request);

		$return_item_ids = [];
		if (!$return_request->isEmpty()) {
			$return_item_ids = $return_request->pluck('item_id')->all();
		}

		$cart_data = array();
		$return_req_process = array();
		$return_req_approved = array();
		$cart_data = array();
		$product_data = [];
		$tax_total = 0;
		$cart_qty_total = 0;

		$carts = Order::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $o_id->o_id)->get();

		$sub_total = $carts->sum('subtotal');
		$discount = $carts->sum('discount');
		$total = $carts->sum('total');
		$tax_total = $carts->sum('tax');
		$bill_buster_discount = 0;
		$tax_details = [];

		foreach ($carts as $key => $cart) {

			$res = OrderDetails::where('t_order_id', $cart->id)->first();
			$offer_data = json_decode($res->pdata, true);

			foreach ($offer_data['pdata'] as $key => $value) {
				foreach ($value['tax'] as $nkey => $tax) {
					if (isset($tax_details[$tax['tax_code']])) {
						$tax_details[$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'];
						$tax_details[$tax['tax_code']]['tax'] += $tax['tax'];
					} else {
						$tax_details[$tax['tax_code']] = $tax;
					}

				}

			}

			$available_offer = [];
			foreach ($offer_data['available_offer'] as $key => $value) {

				$available_offer[] = ['message' => $value];
			}
			$offer_data['available_offer'] = $available_offer;
			$applied_offer = [];
			foreach ($offer_data['applied_offer'] as $key => $value) {

				$applied_offer[] = ['message' => $value];
			}
			$offer_data['applied_offer'] = $applied_offer;
			//dd($offer_data);

			//Counting the duplicate offers
			$tempOffers = $offer_data['applied_offer'];
			for ($i = 0; $i < count($offer_data['applied_offer']); $i++) {
				$apply_times = 1;
				$apply_key = 0;
				for ($j = $i + 1; $j < count($tempOffers); $j++) {

					if (isset($offer_data['applied_offer'][$j]['message']) && $tempOffers[$i]['message'] == $offer_data['applied_offer'][$j]['message']) {
						unset($offer_data['applied_offer'][$j]);
						$apply_times++;
						$apply_key = $j;
					}

				}
				if ($apply_times > 1) {
					$offer_data['applied_offer'][$i]['message'] = $offer_data['applied_offer'][$i]['message'] . ' - ' . $apply_times . ' times';
				}

			}
			$offer_data['available_offer'] = array_values($offer_data['available_offer']);
			$offer_data['applied_offer'] = array_values($offer_data['applied_offer']);

			//$carr_bag_arr = ['114903443', '114952448', '114974444'];
			$carr_bag_arr = [];
			$carry_bags = DB::table('carry_bags')->where('v_id', $v_id)->where('store_id', $store_id)->where('status','1')->get();
			if($carry_bags->isEmpty()){
				//echo 'insdie this';exit;
				$carry_bags = DB::table('carry_bags')->where('v_id', $v_id)->where('store_id', '0')->where('status','1')->get();
			}
			//dd($carry_bags);
			if($carry_bags){
				
				$carr_bag_arr = $carry_bags->pluck('barcode')->all();
			}
			
			$carry_bag_flag = in_array($cart->item_id, $carr_bag_arr);

			$request = [];
			$return_flag = false;
			$return_qty = 0;
			if (in_array($cart->item_id, $return_item_ids)) {
				$request = $return_request->where('item_id', $cart->item_id);

				foreach ($request as $req) {
					if ($req->status == 'approved') {
						$return_qty += $req->qty;
					}

					if ($req->status == 'process') {
						$return_flag = true;

					}
				}

			}

			$product_data['return_flag'] = $return_flag;
			$product_data['return_qty'] = (string) $return_qty;
			$product_data['carry_bag_flag'] = $carry_bag_flag;
			$product_data['isProductReturn'] = ($cart->transaction_type == 'return') ? true : false;
			$product_data['p_id'] = (int) $cart->item_id;
			$product_data['category'] = '';
			$product_data['brand_name'] = '';
			$product_data['sub_categroy'] = '';
			$product_data['whishlist'] = 'No';
			$product_data['weight_flag'] = ($cart->weight_flag == 1) ? true : false;
			$product_data['p_name'] = $cart->item_name;
			$product_data['offer'] = (count($offer_data['applied_offer']) > 0) ? 'Yes' : 'No';
			$product_data['offer_data'] = ['applied_offers' => $offer_data['applied_offer'], 'available_offers' => $offer_data['available_offer']];
			//$product_data['qty'] = '';
			$product_data['multiple_price_flag'] = $offer_data['multiple_price_flag'];
			$product_data['multiple_mrp'] = $offer_data['multiple_mrp'];
			$product_data['r_price'] = format_number($offer_data['r_price']);
			$product_data['s_price'] = format_number($offer_data['s_price']);
			$product_data['unit_mrp'] = format_number($cart->unit_mrp);
			/*if(!empty($offer_data['applied_offers']) ){
				                $product_data['r_price'] = format_number($offer_data['r_price']);
				                $product_data['s_price'] = format_number($offer_data['s_price']);
			*/

			$product_data['varient'] = '';
			$product_data['images'] = '';
			$product_data['description'] = '';
			$product_data['deparment'] = '';
			$product_data['barcode'] = $cart->barcode;

			//$tax_total = $tax_total +  $tax_amount ;
			$tax_amount = $cart->tax;
			$cart_qty_total = $cart_qty_total + $cart->qty;

			$cart_data[] = array(
				'cart_id' => $cart->cart_id,
				'product_data' => $product_data,
				'amount' => $cart->total,
				'qty' => $cart->qty,
				'return_product_qty' => $cart->qty,
				'tax_amount' => $tax_amount,
				'delivery' => $cart->delivery,
				'item_flag' => 'NORMAL',
			);
			//$tax_total = $tax_total +  $tax_amount ;

			//This code is added for displayin andy return items
			if (in_array($cart->item_id, $return_item_ids)) {

				//dd($request);
				foreach ($request as $req) {
					$product_data['r_price'] = format_number($req->subtotal);
					$product_data['s_price'] = format_number($req->total);

					if ($req->status == 'process') {

						$return_req_process[] = array(
							'cart_id' => $cart->cart_id,
							'product_data' => $product_data,
							'amount' => $req->total,
							'qty' => $req->qty,
							//'return_product_qty' => $cart->qty,
							'tax_amount' => $req->tax,
							'delivery' => $cart->delivery,
							'item_flag' => 'RETURN_PROCESS',
						);
					}

					if ($req->status == 'approved') {

						$return_req_approved[] = array(
							'cart_id' => $cart->cart_id,
							'product_data' => $product_data,
							'amount' => $req->total,
							'qty' => $req->qty,
							//'return_product_qty' => $cart->qty,
							'tax_amount' => $req->tax,
							'delivery' => $cart->delivery,
							'item_flag' => 'RETURN_APPROVED',
						);
					}

				}
			}
		}

		$bill_buster_discount = $o_id->bill_buster_discount;
		$saving = $discount + $bill_buster_discount;

		$bags = DB::table('user_carry_bags')->select('vendor_carry_bags.Name', 'user_carry_bags.Qty', 'vendor_carry_bags.BAG_ID')->selectRaw('user_carry_bags.Qty * vendor_carry_bags.Price as Price')->leftJoin('vendor_carry_bags', 'user_carry_bags.Bag_ID', '=', 'vendor_carry_bags.BAG_ID')->where('user_carry_bags.V_ID', $v_id)->where('user_carry_bags.Store_ID', $store_id)->where('user_carry_bags.User_ID', $c_id)->where('user_carry_bags.Order_ID', $order_num_id['o_id'])->get();
		$bprice = DB::table('user_carry_bags')->selectRaw('SUM(user_carry_bags.Qty * vendor_carry_bags.Price) as Price')->leftJoin('vendor_carry_bags', 'user_carry_bags.Bag_ID', '=', 'vendor_carry_bags.BAG_ID')->where('user_carry_bags.V_ID', $v_id)->where('user_carry_bags.Store_ID', $store_id)->where('user_carry_bags.User_ID', $c_id)->where('user_carry_bags.Order_ID', $order_num_id['o_id'])->first();
		// $cart_data['bags'] = $bags;

		if (empty($bprice->Price)) {
			$carry_bag_total = 0;
		} else {
			$carry_bag_total = $bprice->Price;
		}
		$store = DB::table('stores')->select('delivery')->where('store_id', $store_id)->where('v_id', $v_id)->first();
		//$total = (int)$sub_total + (int)$carry_bag_total;
		//$less = array_sum($saving) - (int)$sub_total;
		$address = (object) array();
		if ($o_id->address_id > 0) {
			$address = Address::where('c_id', $c_id)->where('deleted_status', 0)->where('id', $o_id->address_id)->first();
		}
		$paymentMethod = Payment::where('v_id', $o_id->v_id)->where('store_id', $o_id->store_id)->where('order_id', $o_id->order_id)->get()->pluck('method')->all();

		return response()->json(['status' => 'order_details', 'message' => 'Order Details Details',
			'mobile' => $o_id->user->mobile,
			'payment_method' => implode(',', $paymentMethod),
			'data' => $cart_data,
			'return_req_process' => $return_req_process,
			'return_req_approved' => $return_req_approved,
			'product_image_link' => product_image_link(),
			//'offer_data' => $global_offer_data,
			'return_request_flag' => ($return_request) ? true : false,
			'bags' => $bags,
			'carry_bag_total' => (format_number($carry_bag_total)) ? format_number($carry_bag_total) : '0.00',
			'sub_total' => (format_number($sub_total)) ? format_number($sub_total) : '0.00',
			'tax_total' => (format_number($tax_total)) ? format_number($tax_total) : '0.00',
			'tax_details' => $tax_details,
			'bill_buster_discount' => (format_number($bill_buster_discount)) ? format_number($bill_buster_discount) : '0.00',
			'discount' => (format_number($discount)) ? format_number($discount) : '0.00',
			//'grand_total' => (format_number($grand_total))?format_number($grand_total):'0.00',
			'date' => $o_id->date,
			'time' => $o_id->time,
			'order_id' => $order_id,
			'total' => format_number($total),
			'cart_qty_total' => (string) $cart_qty_total,
			'saving' => (format_number($saving)) ? format_number($saving) : '0.00',
			'store_address' => $stores->address1 . ' ' . $stores->address2 . ' ' . $stores->state . ' - ' . $stores->pincode,
			'store_timings' => $stores->opening_time . ' ' . $stores->closing_time,
			'delivered' => $store->delivery,
			'address' => $address], 200);
	}

	public function order_receipt($c_id, $v_id, $store_id, $order_id) {


		$stores =  DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();
        $order = Invoice::where('ref_order_id', $order_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->first();
 		$carts = InvoiceDetails::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('t_order_id', $order->id)->get();
        $user = User::select('first_name','last_name', 'mobile')->where('c_id',$c_id)->first();
        $payment  = Payment::where('order_id', $order_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->first();

		$total = 0.00;
		$total_qty = 0;
		$item_discount = 0.00;
		$counter = 0;
		$tax_details = [];
		$tax_details_data = [];
		$cart_item_text = '';
		$tax_item_text = '';
		$param = [];
		$params = [];
		$tax_category_arr = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];
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
			while ($loopQty > 0) {
				$param[] = $cart->total / $cart->qty;
				$params[] = ['item_id' => $cart->item_id, 'price' => $cart->total / $cart->qty];
				$loopQty--;
			}

			 
			// $offer_data = json_decode($cart->pdata, true);

			// //dd($cart);

			// $hsn_code = '';
			// if (isset($offer_data['hsn_code'])) {
			// 	$hsn_code = $offer_data['hsn_code'];
			// }
			// foreach ($offer_data['pdata'] as $key => $value) {
			// 	$tax_details_data[$cart->item_id] = ['tax' => $value['tax'], 'total' => $value['ex_price']];

			// 	/*foreach($value['tax'] as $nkey => $tax){
			// 		                    if(isset($tax_details[$tax['tax_code']])){
			// 		                        $tax_details[$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
			// 		                        $tax_details[$tax['tax_code']]['tax'] += $tax['tax'] ;
			// 		                    }else{
			// 		                        $tax_details[$tax['tax_code']] = $tax;

			// 		                    }

			// 	*/

			// 	if (empty($value['tax'])) {

			// 		if (isset($tax_details[00][00])) {
			// 			$cart_tax_code_msg .= $cart_tax_code[00][00];
			// 			$cart_tax_code_msg .= $cart_tax_code[00][01];
			// 		} else {

			// 			$tax_details[00][00] = ["tax_category" => "0",
			// 				"tax_desc" => "CGST_00_RC",
			// 				"tax_code" => "0",
			// 				"tax_rate" => "0",
			// 				"taxable_factor" => "0",
			// 				"taxable_amount" => $cart->total,
			// 				"tax" => 0.00];

			// 			$cart_tax_code_msg .= $tax_category_arr[$tax_code_inc];
			// 			$cart_tax_code[00][00] = $tax_category_arr[$tax_code_inc];
			// 			$tax_code_inc++;

			// 			$tax_details[00][01] = ["tax_category" => "0",
			// 				"tax_desc" => "SGST_00_RC",
			// 				"tax_code" => "0",
			// 				"tax_rate" => "0",
			// 				"taxable_factor" => "0",
			// 				"taxable_amount" => $cart->total,
			// 				"tax" => 0.00];
			// 			$cart_tax_code_msg .= $tax_category_arr[$tax_code_inc];
			// 			$cart_tax_code[00][01] = $tax_category_arr[$tax_code_inc];
			// 			$tax_code_inc++;
			// 		}

			// 	} else {

			// 		foreach ($value['tax'] as $nkey => $tax) {
			// 			$tax_category = $tax['tax_category'];
			// 			if (isset($tax_details[$tax_category][$tax['tax_code']])) {
			// 				$tax_details[$tax_category][$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'];
			// 				$tax_details[$tax_category][$tax['tax_code']]['tax'] += $tax['tax'];
			// 				$cart_tax_code_msg .= $cart_tax_code[$tax_category][$tax['tax_code']];
			// 			} else {
			// 				$tax_details[$tax_category][$tax['tax_code']] = $tax;
			// 				$cart_tax_code_msg .= $tax_category_arr[$tax_code_inc];
			// 				$cart_tax_code[$tax_category][$tax['tax_code']] = $tax_category_arr[$tax_code_inc];
			// 				$tax_code_inc++;

			// 			}

			// 		}
			// 	}

			// }

			//$cart_item_arr[] = ['hsn_code' => $hsn_code , 'item_name' => $cart->item_name , 'unit_mrp' => $cart->unit_mrp, 'qty' => $cart->qty , 'discount' => $cart->discount , 'total' => $cart->total , 'tax_category' => $tax_category ];
			$tdata = json_decode($cart->tdata);

			$tax_cal_text = '';
			$tt    = $tdata->cgst + $tdata->sgst;
			$total_tax = 0;
			$sub_total = 0;
			$cgstamt   = 0;
			$sgstamt   = 0;
			$igstamt   = 0;
			$cessamt   = 0;
			$tax_cal   = array();
			$gst       = array();
           
            $total_tax += @$tdata->tax_amount;
                   
            $cgstamt += @$tdata->cgstamt;
            $sgstamt += @$tdata->sgstamt;
            $igstamt += @$tdata->igstamt;
            $cessamt += @$tdata->cessamt;
            $sub_total += $tdata->taxable;
            
            if($tdata->cgstamt != 0){
                $totalgst   = $tdata->cgstamt+$tdata->sgstamt;
                @$gst[$tdata->cgst+$tdata->sgst]  +=$totalgst;  
            }
            $totaltax = $tdata->cgst+$tdata->sgst;

			$tax_cal['cgstamt']= $cgstamt;
			$tax_cal['sgstamt']= $sgstamt;
			$tax_cal['igstamt']= $igstamt;
			$tax_cal['gst']    = $gst;
			if($totaltax > 0){
				$tax_cal_text .= '<tr>
									<td>GST '.$totaltax.' %</td>
									<td>'.$sub_total.'</td>
									<td>'.$cgstamt.'</td>
									<td>'.$sgstamt.'</td>
									<td>'.$cessamt.'</td>
								</tr>';
        	}



			$cart_item_text .=
			'<tr class="td-center">
                <td>' . $counter . '</td>
                <td> '.$cart->item_id.'</td>
                <td colspan=2>' . $cart->item_name . '</td>
                <td colspan=2>' . $tdata->hsn . '</td>

            </tr>
            <tr class="td-center">
                <td style="padding-left:5px">' . $cart->qty . '</td>
                <td> &nbsp;&nbsp;' . format_number($cart->unit_mrp) . '</td>
                <td>' . format_number($cart->discount/$cart->qty) . '</td>
                 <td>' . $cart->tax . '</td>
                  <td>' . $tt. '</td>
                <td>' . $cart->total . '</td>
            </tr>';

 

		}
		//dd($total_tax);

		$total_tax_cal_text = '';
			if($tax_cal['gst']){
				foreach($tax_cal['gst'] as $key => $tx){
					$total_tax_cal_text  .= '<tr>
									<td>Total </td>
									<td>'.$sub_total.'</td>
									<td>'.$cgstamt.'</td>
									<td>'.$sgstamt.'</td>
									<td>'.$cessamt.'</td>
								</tr>'; 

				}
			}

		$bill_buster_discount_text = '';
		if ($order->bill_buster_discount > 0) {
			$total = $total - $order->bill_buster_discount;
			$bill_buster_discount_text .=
			'<tr>
                <td colspan="3">Bill Buster</td>
                <td> -' . format_number($order->bill_buster_discount) . '</td>
            </tr>';

			//Recalcualting taxes when bill buster is applied
			$promo_c = new PromotionController;
			$tax_details = [];
			$ratio_val = $promo_c->get_offer_amount_by_ratio($param, $order->bill_buster_discount);
			$ratio_total = array_sum($ratio_val);

			$discount = 0;
			$total_discount = 0;
			//dd($param);
			foreach ($params as $key => $par) {
				$discount = round(($ratio_val[$key] / $ratio_total) * $order->bill_buster_discount, 2);
				$params[$key]['discount'] = $discount;
				$total_discount += $discount;
			}
			//dd($params);
			//echo $total_discount;exit;
			//Thid code is added because facing issue when rounding of discount value
			if ($total_discount > $order->bill_buster_discount) {
				$total_diff = $total_discount - $order->bill_buster_discount;
				foreach ($params as $key => $par) {
					if ($total_diff > 0.00) {
						$params[$key]['discount'] -= 0.01;
						$total_diff -= 0.01;
					} else {
						break;
					}
				}
			} else if ($total_discount < $order->bill_buster_discount) {
				$total_diff = $order->bill_buster_discount - $total_discount;
				foreach ($params as $key => $par) {
					if ($total_diff > 0.00) {
						$params[$key]['discount'] += 0.01;
						$total_diff -= 0.01;
					} else {
						break;
					}
				}
			}
			//dd($params);
			foreach ($params as $key => $para) {
				$discount = $para['discount'];
				$item_id = $para['item_id'];
				// $tax_details_data[$key]
				foreach ($tax_details_data[$item_id]['tax'] as $nkey => $tax) {
					$tax_category = $tax['tax_category'];
					$taxable_total = $para['price'] - $discount;
					$tax['taxable_amount'] = round($taxable_total * $tax['taxable_factor'], 2);
					$tax['tax'] = round(($tax['taxable_amount'] * $tax['tax_rate']) / 100, 2);
					//$tax_total += $tax['tax'];
					if (isset($tax_details[$tax_category][$tax['tax_code']])) {
						$tax_details[$tax_category][$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'];
						$tax_details[$tax_category][$tax['tax_code']]['tax'] += $tax['tax'];
					} else {

						$tax_details[$tax_category][$tax['tax_code']] = $tax;
					}

				}
			}

		}

		//dd($tax_details_data);

		$discount_text = '';
		if (($item_discount + $order->bill_buster_discount) > 0) {
			$discount_text = '<p>***TOTAL SAVING : Rs. ' . format_number($item_discount + $order->bill_buster_discount) . ' *** </p>';
		}

		$tax_counter = 0;
		$total_tax = 0;
		//dd($tax_details);
		foreach ($tax_details as $tax_category) {
			foreach ($tax_category as $tax) {

				$total_tax += $tax['tax'];
				$tax_item_text .=
				'<tr >
                    <td>' . $tax_category_arr[$tax_counter] . '  ' . substr($tax['tax_desc'], 0, -2) . ' (' . $tax['tax_rate'] . '%) ' . '</td>
                    <td>' . $tax['taxable_amount'] . '</td>
                    <td>' . $tax['tax'] . '</td>
                </tr>';
				$tax_counter++;
			}
		}

		$rounded = round($total);
		$rounded_off = $rounded - $total;

		$bill_logo_id = 5;
		$vendorImage = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status', 1)->first();
		if ($vendorImage) {
			$bilLogo = env('ADMIN_URL') . $vendorImage->path;
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
                <div class="logo">
                  <center><img   src="'.$bilLogo.'" ></center>
                </div>
                <center>
                	 <h2>'.$stores->name.'</h2>
                	 <div class="invoice-contact">
                	 	<p>Contact No: '.$stores->contact_number.'</p>
                	 	<p>Email: '.$stores->email.'</p>
                	 </div>
                 	<h2>**** Invoice ****</h2>
                    <div class="invoice-contact">
						<p>GSTIN - ' . $stores->gst . '</P>
						<p>CIN - ' . $stores->cin . '</P>
                	 </div>
					<table>
						<tr>
							<td>GST Doc No: '.$order->custom_order_id.'<td>
					   </tr>
					   <tr>
					   	<td>'.$order->invoice_id.'<td>
					   </tr>
					    <tr>
							<td>'.date('h:i A d-M-Y', strtotime($order->created_at)).'</td>
					   </tr>
					   <tr>
						 <td> Cashier: '.$order->vuser->first_name.' '.$order->vuser->last_name.'</td>
					   </tr>
					</table>
                    <hr/>
					<table>
						<tr>
							<td>GST Doc No: '.$order->custom_order_id.'<td>
					   </tr>
					   <tr>
					   	<td>'.$order->invoice_id.'<td>
					   </tr>
					    <tr>
							<td>'.date('h:i A d-M-Y', strtotime($order->created_at)).'</td>
					   </tr>
					   <tr>
						 <td> Cashier: '.$order->vuser->first_name.' '.$order->vuser->last_name.'</td>
					   </tr>
					</table>

                    
                    

                    <hr/>
                    <table>

                    <tr>
                        <td>Sr. No </td>
                        <td>Product</td>
                        <td colspan=2>Description</td>
                        <td colspan=2>Hsn Code</td>
                    </tr>
                    <tr>
                        <td>Qty</td>
                        <td>Rate</td>
                        <td>DISC</td>
                        <td>Tax Amt</td>
                        <td>%Amt</td>
                        <td>Amount</td>
                    </tr>
                    </table>
                    <hr>
                    <table>

                   ' . $cart_item_text . '
                    <tr>
                        <td colspan="6"><hr></td>

                    </tr>
                    ' . $bill_buster_discount_text . '
                    <tr>
                        <td colspan="5">Total Amount</td>

                        <td>' . format_number($total) . '</td>
                    </tr>
           
                    <tr>
                        <td colspan="5">GST</td>
                        <td>' . format_number($order->tax) . '</td>
                    </tr>
                
                    <tr>
                        <td colspan="5">Rounded Off</td>
                        <td>' . format_number($rounded_off) . '</td>
                    </tr>
                   
                    <tr>
                        <td colspan="5">Due:-</td>
                        <td>' . format_number($total) . '</td>
                    </tr>
                    <tr>
                    	<td></td><td colspan=5>'.numberTowords(round($order->total)).'</td>
                    </tr>
                    <tr><td colspan=6><hr><td></tr>
                    <tr>
                        <td colspan="5">'.ucfirst($payment->method).'</td>
                        <td>' . format_number($payment->amount) . '</td>
                    </tr>
                    <tr><td colspan=6><hr><td></tr>
                    <tr>
                        <td colspan=2></td>
                        <td colspan=4 ><b>Customer Paid : '.format_number($payment->cash_collected).'</b></td>
                    </tr>
                      <tr>
                        <td colspan=2></td>
                        <td  colspan=4><b>Balance Refund: '.format_number($payment->cash_return).'</b></td>
                    </tr>
						 <tr><td colspan=6><hr></td></tr>
                    
					 <tr>
                        <td colspan="5">Total Sale</td>
                        <td>' . format_number($order->total). '</td>
                    </tr>
                    <tr>
                        <td colspan="5">Total Return</td>
                        <td>0.00</td>
                    </tr>
                    <tr>
                        <td colspan="5">Saving On The Bill</td>
                        <td>' . $order->discount . '</td>
                    </tr>
                    <tr>
                        <td colspan="5">Net Sale</td>
                        <td>' . format_number($order->total). '</td>
                    </tr>
                    <tr>
                        <td colspan="5">Round Off</td>
                        <td>' . $this->format_and_string($rounded_off). '</td>
                    </tr>
                     
                     <tr>
                        <td colspan="5">Net Payable</td>
                        <td>' . format_number($payment->amount). '</td>
                    </tr>
					 <tr><td colspan=6  ><hr></td></tr>
					 <tr>
                        <td colspan="6" text-align=center>'.$order->invoice_id.'</td>
                    </tr>
 				, 
                    
                    </table>
                   
                <div style="text-align:left">
                <h3>Terms And Conditions</h3>
                <hr>
	               
	                <p>1. All Items inclusive of GST \nExcept Discounted Item.</p>
					<p>2. Extra GST Will be Charged on\n Discounted Item.</p>
					<p>3. No exchange on discounted and\n offer items.</p>
					<p>4. No Refund.</p>
					<p>5. We recommended dry clean for\n all fancy items.</p>
					<p>6. No guarantee for colors and all hand work item.</p>
                <div>
				<table>
					<tr>
						<td colspan="5">Net Payable</td>
						<td>'.format_number($payment->amount). '</td>
					</tr>
					 <tr><td colspan=6  ><hr></td></tr>
					 <tr>
                        <td  >Summary </td>
                        <td>Taxable</td>
                        <td>UT/CGST</td>
                        <td>UT/SGST</td>
                        <td>Cess</td>
                    </tr>
                    <tr>
                    	<td></td>
                        <td>Amount</td>
                        <td>Amount</td>
                        <td>Amount</td>
                        <td>Amount</td>
                    </tr>
                    <tr><td colspan=6  ><hr></td></tr>
                    '.$tax_cal_text.'
                    <tr><td colspan=6  ><hr></td></tr>
					'.$total_tax_cal_text.'
					
					 <tr>
                        <td colspan="5">Gate Pass No</td>
                        <td></td>
                    </tr>
                     <tr>
                        <td colspan="5">Memo No</td>
                        <td>' .$order->invoice_id. '</td>
                    </tr>
                     <tr>
                        <td colspan="5">Customer Name</td>
                        <td>' .$order->user->first_name.' '.$order->user->last_name. '</td>
                    </tr>
                    <tr>
                        <td colspan="5">Customer Mobile</td>
                        <td>' .$order->user->mobile. '</td>
                    </tr>
                    <tr>
                        <td colspan="5">Total Qty</td>
                        <td>' .$total_qty. '</td>
                    </tr>
                     <tr>
                        <td colspan="5">Total Amount</td>
                        <td>' . format_number($payment->amount). '</td>
                    </tr>
                     <tr>
                        <td colspan="5">Cashier Name</td>
                        <td>' . $order->vuser->first_name.''.$order->vuser->last_name.'</td>
                    </tr>
                    <tr>
                        <td colspan="6"><center>GENERATED BY</center></td>
                    </tr>
                    <tr>
                        <td colspan="6"><center><img style="height:30px" src="'.env('APP_URL').'/images/zwing_header.png" ></center></td>
                    </tr>

				</table>
                </center>
                </div>
            </body>
        </html>';

		return $html;
	}

	public function rt_log(Request $request) {
		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$date = $request->date;

		$stores = DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();
		$orders = Order::where('v_id', $v_id)->where('store_id', $store_id)->where('date', $date)->get();
		//$carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order->o_id)->get();

		$columns = ['STORE', 'BUSINESS_DATE', 'TRANSACTION_DATETIME', 'CASHIER', 'TRAN_TYPE', 'CUSTOMER_ORDER_NO', 'CUSTOMER_ORDER_DATE', 'TIC_NO', 'ORIG_TRAN_NO', 'ORIG_BUSINESS_DATE', 'TOTAL_REF', 'VALUE', 'ITEM_SEQ_NO', 'ITEM', 'QTY', 'UNIT_RETAIL', 'MRP', 'SELLING_UOM', 'RETURN_REASON_CODE', 'PROMO_TYPE', 'DISCOUNT_TYPE', 'DISCOUNT_AMOUNT', 'TAX_CODE_1', 'TAX_RATE_1', 'TAX_VALUE_1', 'TAX_CODE_2', 'TAX_RATE_2', 'TAX_VALUE_2', 'TAX_CODE_3', 'TAX_RATE_3', 'TAX_VALUE_3', 'TAX_CODE_4', 'TAX_RATE_4', 'TAX_VALUE_4', 'TAX_CODE_5', 'TAX_RATE_5', 'TAX_VALUE_5', 'TENDER_TYPE_GROUP', 'TENDER_TYPE_ID', 'AMOUNT', 'CREDIT_CARD_NUMBER', 'CREDIT_CARD_EXPIRY_DATE', 'COUPON_NO', 'COUPON_DESC', 'VOUCHER_NO'];

		$path = storage_path();
		$file_name = 'Zwing_' . $stores->mapping_store_id . '_' . $date . '.csv';
		$file = fopen($path . "/app/" . $file_name, "w");

		fputcsv($file, $columns);
		//$cart_items = [];
		$total = 0;
		foreach ($orders as $key => $order) {
			$carts = Cart::where('user_id', $order->user_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order->o_id)->get();
			$cart_counter = 0;
			foreach ($carts as $key => $cart) {
				$total += $cart->total;
				$res = DB::table('cart_offers')->where('cart_id', $cart->cart_id)->first();
				$offer_data = json_decode($res->offers, true);

				$tax_details = [];
				foreach ($offer_data['pdata'] as $key => $value) {
					foreach ($value['tax'] as $nkey => $tax) {
						if (isset($tax_details[$tax['tax_code']])) {
							$tax_details[$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'];
							$tax_details[$tax['tax_code']]['tax'] += $tax['tax'];
						} else {
							$tax_details[$tax['tax_code']] = $tax;
						}

					}

				}

				$tax_details = array_values($tax_details);
				//dd($tax_details);

				$cart_counter++;
				$items = [
					$stores->mapping_store_id,
					date('d-m-Y', strtotime($date)),
					date('d-m-Y H:i', strtotime($order->created_at)),
					'M013303',
					'SALE',
					$order->order_id,
					date('d-m-Y H:i', strtotime($order->created_at)),
					'6718324189', //TIC_NO,
					'', //ORIG_TRAN_NO use when return happen
					'', //ORIG_BUSINESS_DATE USe when return happen
					'', //TOTAL_REF use when calculting total
					'', //VALUE
					$cart_counter, //ITEM_SEQ_NO
					$cart->item_id,
					$cart->qty,
					$cart->total / $cart->qty,
					$cart->unit_mrp,
					'EA', //EA and KG
					'', //REturn REAson cdoe
					'1004', //Promo Type
					'ORRCAP', //Discount Type
					$cart->discount,
				];

				$items_index = count($items);
				foreach ($tax_details as $tax) {
					//$items_index++;
					$items[$items_index++] = $tax['tax_code'];
					$items[$items_index++] = $tax['tax_rate'];
					$items[$items_index++] = $tax['taxable_factor'];

				}

				if ($items_index == 37) {

				} else {
					while ($items_index < 37) {
						$items[$items_index++] = '';
					}
				}

				$items[$items_index++] = 'ZWING'; //Tender group type
				$items[$items_index++] = '9999'; // Tender group id
				$items[$items_index++] = $cart->total;
				// $items[$items_index++] = 'ZWING';

				fputcsv($file, $items);
			}

		}

		$line = [
			$stores->mapping_store_id,
			date('d-m-Y', strtotime($date)),
			date('d-m-Y H:i', strtotime($order->created_at)),
			'M013303',
			'TOTAL',
			'',
			'',
			'', //TIC_NO,
			'', //ORIG_TRAN_NO use when return happen
			'', //ORIG_BUSINESS_DATE USe when return happen
			'CASH', //TOTAL_REF use when calculting total
			'0', //VALUE
		];

		fputcsv($file, $line);

		$line = [
			$stores->mapping_store_id,
			date('d-m-Y', strtotime($date)),
			date('d-m-Y H:i', strtotime($order->created_at)),
			'M013303',
			'TOTAL',
			'',
			'',
			'', //TIC_NO,
			'', //ORIG_TRAN_NO use when return happen
			'', //ORIG_BUSINESS_DATE USe when return happen
			'ZWING', //TOTAL_REF use when calculting total
			$total, //VALUE
		];

		fputcsv($file, $line);

		/* foreach ($list as $line)
			          {
			          fputcsv($file,explode(',',$line));
		*/

		fclose($file);

		return ['status' => 'success', 'message' => 'RT Log has been generated successfully'];
	}

	public function get_carry_bags(Request $request) {

		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$c_id = $request->c_id;
		$order_id = Order::where('user_id', Auth::user()->c_id)->where('status', 'success')->count();
		$order_id = $order_id + 1;
		$store_db_name 	= $this->store_db_name($store_id);

		//$carry_bags = DB::table('vendor_carry_bags')->select('BAG_ID','Name','Price')->where('V_ID', $v_id)->where('Store_ID', $store_id)->get();

		//$carr_bag_arr = ['VR132797', 'VR132799', 'VR132807'];
		$carr_bag_arr = [];
		$carry_bags = DB::table('carry_bags')->where('v_id', $v_id)->where('store_id', $store_id)->where('status','1')->get();
		if($carry_bags->isEmpty()){
			//echo 'insdie this';exit;
			$carry_bags = DB::table('carry_bags')->where('v_id', $v_id)->where('store_id', '0')->where('status','1')->get();
		}
		//dd($carry_bags);
		if($carry_bags){
			
			$carr_bag_arr = $carry_bags->pluck('barcode')->all();
		}

		$carry_bags = DB::table($store_db_name.'.invitem')->select('ICODE as BAG_ID', 'CNAME1 as Name', 'MRP as Price')->whereIn('ICODE', $carr_bag_arr)->get();

		$carts = DB::table('cart')->where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

		$data = array();
		foreach ($carry_bags as $key => $value) {
			//$bags = DB::table('user_carry_bags')->select('Qty','Bag_ID')->where('V_ID', $v_id)->where('Store_ID', $store_id)->where('User_ID', Auth::user()->c_id)->where('Order_ID', $order_id)->where('Bag_ID', $value->BAG_ID)->first();
			$cart = $carts->where('item_id', $value->BAG_ID)->first();
			//$bags =

			if (empty($cart)) {
				$data[] = array(
					'BAG_ID' => $value->BAG_ID,
					'Name' => $value->Name,
					'Price' => $this->format_and_string($value->Price),
					'Qty' => 0,
				);
			} else {
				if ($value->BAG_ID == $cart->item_id) {
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
		return ['status' => 'get_carry_bags_by_store', 'data' => $data];
	}

	public function save_carry_bags(Request $request) {

		//echo 'inside this';exit;
		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$c_id = $request->c_id;
		$store_db_name 	= $this->store_db_name($store_id);
		//$order_id = $request->order_id;
		$bags = $request->bags;
		//dd($bags);
		$bags = json_decode(urldecode($bags), true);
		//dd($bags);
		$stores = DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();
		$order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
		$order_id = $order_id + 1;
		$carts = DB::table('cart')->where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();
		 
		foreach ($bags as $key => $value) {

			$exists = $carts->where('barcode', $value[0])->first();

			//dd($exists);
			$price_master = DB::table($store_db_name.'.invitem')->select('ICODE as BAG_ID', 'CNAME1 as Name', 'MRP as Price')->where('ICODE', $value[0])->first();
			//dd($price_master);

			(array) $push_data = ['v_id' => $v_id, 'trans_from' => $request->trans_from, 'barcode' => $price_master->BAG_ID, 'qty' => $value[1], 'scode' => $stores->mapping_store_id];

			//dd($push_data);




			$single_cart_data['v_id'] = $v_id;
			$single_cart_data['is_cart'] = 1;
			$single_cart_data['is_update'] = 0;
			$single_cart_data['store_id'] = $store_id;
			$single_cart_data['c_id'] = $c_id;
			$single_cart_data['trans_from'] = $request->trans_from;
			$single_cart_data['barcode'] = $price_master->BAG_ID;
			$single_cart_data['qty'] = $value[1];
			$single_cart_data['vu_id'] = $request->vu_id;
			$single_cart_data['mapping_store_id'] = $stores->mapping_store_id;
			$item = DB::table($stores->store_db_name.'.invitem')->select('GRPCODE', 'INVARTICLE_CODE','BARCODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE', 'LISTED_MRP', 'DESC1', 'DESC2', 'DESC3', 'DESC4', 'DESC5', 'DESC6')->where('ICODE', $price_master->BAG_ID)->first();
			$single_cart_data['item'] = $item;
			$single_cart_data['store_db_name'] = $stores->store_db_name;
			$carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $request->order_id)->where('user_id', $request->c_id)->where('status', 'process')->get();
			$single_cart_data['carts'] = $carts;

			//print_r($single_cart_data['section_target_offers']);die;

			$promoC = new PromotionController;
			$offer_data = $promoC->index($single_cart_data);
			$data = $offer_data;


			/*echo $data['r_price'];
			dd($data);*/

			if ($exists) {

				if ($value[1] < 1) {
					$request->request->add(['cart_id' => $exists->cart_id]);
					$this->remove_product($request);
				} else {
					$request->request->add(['barcode' => $value[0], 'qty' =>   $value[1], 'unit_mrp' =>  $data['unit_mrp'], 'unit_rsp' => $data['unit_rsp'] , 'r_price' =>  $data['r_price'], 's_price' => $data['s_price'], 'discount' =>$data['discount'], 'pdata' => '', 'data' => $data,'ogbarcode' => $value[0]]);
					$this->product_qty_update($request);
				}

				$status = '1';
			} else {

				if ($value[1] > 0) {

					$request->request->add(['barcode' => $value[0], 'qty' => $value[1], 'unit_mrp' => $data['unit_mrp'], 'unit_rsp' =>  $data['unit_rsp'], 'r_price' => $data['r_price'], 's_price' => $data['s_price'], 'discount' =>$data['discount'], 'pdata' => '', 'data' => $data,'ogbarcode' => $value[0]]);
					
					$this->add_to_cart($request);
				}
				/*
					                if(empty($value[1])) {
					                    $update = DB::table('user_carry_bags')->where('V_ID', $v_id)->where('Store_ID', $store_id)->where('User_ID', $c_id)->where('Order_ID', $order_id)->where('Bag_ID', $value[0])->update(['Qty' => $value[1]]);
					                    $delete = DB::table('user_carry_bags')->where('V_ID', $v_id)->where('Store_ID', $store_id)->where('User_ID', $c_id)->where('Order_ID', $order_id)->where('Bag_ID', $value[0])->delete();
					                } else {
					                    $delete = DB::table('user_carry_bags')->where('V_ID', $v_id)->where('Store_ID', $store_id)->where('User_ID', $c_id)->where('Order_ID', $order_id)->where('Bag_ID', $value[0])->update(['Qty' => $value[1]]);
				*/

				$status = '2';
			}
		}
		if ($status == 1) {
			return response()->json(['status' => 'add_carry_bags', 'message' => 'Carry Bags Added'], 200);
		} else {
			return response()->json(['status' => 'add_carry_bags', 'message' => 'Carry Bags Updated'], 200);
		}
		//print_r($
		// $carry_bags = DB::table('vendor_carry_bags')->select('BAG_ID','Name','Price')->where('V_ID', $v_id)->where('Store_ID', $store_id)->get();
		// return response()->json(['status' => 'get_carry_bags_by_store', 'data' => print_r(expression)$bags ],200);
	
	}

	public function deliveryStatus(Request $request) {
		$c_id = $request->c_id;
		// $v_id = $request->v_id;
		// $store_id = $request->store_id;
		$cart_id = $request->cart_id;
		$status = $request->status;

		$cart = Cart::find($cart_id)->update(['delivery' => $status]);

		return response()->json(['status' => 'delivery_status_update'], 200);
	}

	public function process_each_item_in_cart($param)
	{
		// dd($param);
		$promoC = new PromotionController;
		$offer_data = $promoC->indexByCart($param);
		// dd($offer_data);
		$data = (object)[ 'v_id' => $param['v_id'], 'store_id' => $param['store_id'], 'c_id' => $param['c_id'], 'barcode' => $offer_data['barcode'], 'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'data' => $offer_data, 'trans_from' => $param['trans_from'], 'vu_id' => $param['vu_id'] ];
		// dd($data);
		$this->update_to_cart($data);
		// dd($data);
		// $check_product_exists = DB::table('cart')->where('v_id', $param['v_id'])->where('store_id', $param['store_id'])->where('user_id', $param['c_id'])->where('status', 'process')->get(['barcode', 'qty', 'cart_id']);
		// foreach ($check_product_exists as $key => $delete_update) {
		// 	DB::table('cart')->where('cart_id', $delete_update->cart_id)->delete();
		// 	DB::table('cart_details')->where('cart_id', $delete_update->cart_id)->delete();
		// 	$product = new ProductController;
		// 	$request_data = (object) ['v_id' => $param['v_id'], 'trans_from' => 'ANDROID_VENDOR', 'store_id' => $param['store_id'], 'barcode' => $delete_update->barcode, 'c_id' => $param['c_id'], 'scan' => 'TRUE', 'qty' => $delete_update->qty];
		// 	$response_data = $product->product_details_by_qty($request_data);
		// 	// dd($response_data);
		// 	$add_to_cart_reponse = (object) ['v_id' => $param['v_id'], 'c_id' => $param['c_id'], 'store_id' => $param['store_id'], 'barcode' => $response_data['p_id'], 'qty' => $response_data['qty'], 'unit_mrp' => $response_data['unit_mrp'], 'r_price' => $response_data['r_price'], 's_price' => $response_data['s_price'], 'discount' => $response_data['discount'], 'pdata' => $response_data['pdata'], 'data' => $response_data, 'trans_from' => $param['trans_from'], 'vu_id' => $param['vu_id']];
		// 	$this->auto_add_to_cart($add_to_cart_reponse);
		// 	// dd($add_to_cart_reponse);
		// }
		// dd($check_product_exists);
	}

	public function get_print_receipt(Request $request) 
	{
		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$c_id = $request->c_id;
		$order_id = $request->order_id;
		$product_data = [];
		$gst_list = [];
		$final_gst = [];
		$detatch_gst = [];
		$store_db_name = $this->store_db_name($store_id);
		$store = Store::find($store_id);

		$site_details = DB::table($store_db_name.'.admsite')->where('CODE', $store->mapping_store_id)->first();
		$order_details = Order::where('order_id', $order_id)->first();

		$cart_qty = OrderDetails::where('t_order_id', $order_details->od_id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('qty');

        $total_amount = OrderDetails::where('t_order_id', $order_details->od_id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
        // dd($total_amount);

        $cart_product = OrderDetails::where('t_order_id', $order_details->od_id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->get();
		$count = 1;
		$gst_tax = 0;
		$gst_listing = [];

		foreach ($cart_product as $key => $value) {
			$tdata = json_decode($value->tdata);
			// dd($tdata);
			// if (is_array($tdata) || array_key_exists('apply_tax', $tdata)) {

				$gst_tax += $value->tax;
				array_push($product_data, [
					'row' => 1,
					'sr_no' => $count++,
					'name' => $value->item_name,
					'total' => $value->total,
					'hsn' => $tdata->hsn,
				]);

				array_push($product_data, [
					'row' => 2,
					'rate' => round($value->unit_mrp),
					'qty' => $value->qty,
					'discount' => $value->discount,
					'rsp' => $value->unit_mrp,
					'tax_amt' => $value->tax,
					'tax_per' => $tdata->cgst + $tdata->sgst,
					'total' => $value->total,
				]);

				$gst_list[] = [
					'name' => $tdata->tax_name,
					'wihout_tax_price' => $tdata->taxable,
					'tax_amount' => $tdata->tax,
				];
			// } else {

			// 	// $gst_tax += $value->tax;
			// 	array_push($product_data, [
			// 		'row' => 1,
			// 		'sr_no' => $count++,
			// 		'name' => $value->item_name,
			// 		'total' => $value->total,
			// 		'hsn' => '',
			// 	]);

			// 	array_push($product_data, [
			// 		'row' => 2,
			// 		'rate' => round($value->unit_mrp),
			// 		'qty' => $value->qty,
			// 		'discount' => $value->discount,
			// 		'rsp' => $value->unit_mrp,
			// 		'tax_amt' => $value->tax,
			// 		'tax_per' => '',
			// 		'total' => $value->total,
			// 	]);

			// }
		}

		// dd(array_unique($gst_list));

		$gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
		// dd($gst_list);
		$total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = 0;
		foreach ($gst_listing as $key => $value) {
			$buffer_total_gst = $buffer_taxable_amount = $buffer_total_taxable = $buffer_total_csgt = $buffer_total_sgst = 0;
			foreach ($gst_list as $val) {
				if ($val['name'] == $value) {
					$buffer_total_gst += $val['tax_amount'];
					$buffer_taxable_amount += str_replace(",", '', $val['wihout_tax_price']);
					$total_gst += $val['tax_amount'];
					$taxable_amount += str_replace(",", '', $val['wihout_tax_price']);
					$final_gst[$value] = (object) [
						'name' => $value,
						'taxable' => $this->format_and_string($buffer_taxable_amount),
						'cgst' => number_format($buffer_total_gst / 2, 2),
						'sgst' => number_format($buffer_total_gst / 2, 2),
						'cess' => '0.00',
					];
					// $total_taxable += $taxable_amount;
					$total_csgt = $total_gst / 2;
					$total_sgst = $total_gst / 2;
				}
			}
		}
		// dd($final_gst);

		foreach ($final_gst as $key => $value) {
			$detatch_gst[] = $value;
		}

		// dd($detatch_gst);

		$roundoff = explode(".", $total_amount);
		$roundoffamt = 0;
		// dd($roundoff);
		if (!isset($roundoff[1])) {
			$roundoff[1] = 0;
		}
		if ($roundoff[1] >= 50) {
			$roundoffamt = $order_details->total - $total_amount;
		} else if ($roundoff[1] <= 49) {
			$roundoffamt = $total_amount - $order_details->total;
		}
		// dd($roundoffamt);

		//Voucher Conditions started Here
		$store_credit = '';
		$voucher_no = '';
		$voucher_total = 0;
		$voucher_applied_list = [];
		$lapse_voucher_amount = 0;
		$bill_voucher_amount = 0;
		$cash_collected = 0;
		$cash_return = 0;
		if ($order_details->transaction_type == 'sales') {
			$payments = Payment::where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('order_id', $order_id)->get();
			if ($payments) {

				foreach ($payments as $payment) {
					$cash_collected += (float) $payment->cash_collected;
					$cash_return += (float) $payment->cash_return;
					if ($payment->method == 'vmart_credit') {
						$vouchers = DB::table('voucher_applied as va')
							->join('voucher as v', 'v.id', 'va.voucher_id')
							->select('v.voucher_no', 'v.amount')
							->where('va.v_id', $v_id)->where('va.store_id', $store_id)
							->where('va.user_id', $c_id)->where('va.order_id', $order_details->o_id)->get();
						$voucher_total = 0;
						foreach ($vouchers as $voucher) {
							$voucher_total += $voucher->amount;
							$voucher_applied_list[] = ['voucher_code' => $voucher->voucher_no, 'voucher_amount' => format_number($voucher->amount)];
						}

						if ($voucher_total > $total_amount) {

							$lapse_voucher_amount = $voucher_total - $total_amount;
							$bill_voucher_amount = $total_amount;

						} else {

							$bill_voucher_amount = $voucher_total;
						}

					} else {
						$zwing_online = format_number($payment->amount);
					}
				}

			} else {
				return response()->json(['status' => 'fail', 'message' => 'Payment is not processed'], 200);
			}

		} else {
			$voucher = DB::table('voucher')->where('ref_id', $order_details->ref_order_id)->where('user_id', $order_details->user_id)->first();
			if ($voucher) {

				$store_credit = format_number($rounded);
				$voucher_no = $voucher->voucher_no;

			}

		}

		if ($cash_collected > 0.00) {

		} else {
			$cash_collected = $total_amount;
			$cash_return = 0.00;
		}
		$bilLogo = '';
		$bill_logo_id = 5;
		$vendorImage = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status', 1)->first();
		if ($vendorImage) {
			$bilLogo = env('ADMIN_URL') . $vendorImage->path;
		}

		$data = [
			'header' => $site_details->NAME,
			'address' => $site_details->ADDRESS,
			'contact' => $store->helpline,
			'email' => $store->email,
			'gstin' => $store->gst,
			'cin' => 'L51909DL2002PLC163727',
			'gst_doc_no' => $order_details->custom_order_id,
			'memo_no' => $order_details->order_id,
			'time' => date('h:i A', strtotime($order_details->created_at)),
			'date' => date('d-M-Y', strtotime($order_details->created_at)),
			'cashier' => $order_details->vuser->first_name . ' ' . $order_details->vuser->last_name,
			'customer_name' => 'NA',
			'mobile' => (string) $order_details->user->mobile,
			'product_data' => $product_data,
			'total_qty' => $cart_qty,
			'total_amount' => $total_amount,
			'voucher_total ' => $voucher_total,
			'voucher_applied_list ' => $voucher_applied_list,
			'lapse_voucher_amount ' => $lapse_voucher_amount,
			'bill_voucher_amount ' => $bill_voucher_amount,
			'gst' => $this->format_and_string('0.00'),
			'round_off' => $this->format_and_string($roundoffamt),
			'due' => $total_amount,
			'in_words' => numberTowords(round($order_details->total)),
			'payment_type' => ucfirst($order_details->payment->method),
			'payment_type_amount' => $total_amount,
			'customer_paid' => format_number($cash_collected),
			'balance_refund' => format_number($cash_return),
			'total_sale' => $order_details->total,
			'total_return' => '0.00',
			'saving_on_the_bill' => $order_details->discount,
			'net_sale' => $order_details->total,
			'round_off_2' => $this->format_and_string($roundoffamt),
			'net_payable' => $order_details->total,
			't_and_s_1' => '1. All Items inclusive of GST \nExcept Discounted Item.',
			't_and_s_2' => '2. Extra GST Will be Charged on\n Discounted Item.',
			't_and_s_3' => '3. No exchange on discounted and\n offer items.',
			't_and_s_4' => '4. No Refund.',
			't_and_s_5' => '5. We recommended dry clean for\n all fancy items.',
			't_and_s_6' => '6. No guarantee for colors and all hand work item.',
			'total_savings' => $order_details->discount,
			'gst_list' => $detatch_gst,
			'total_gst' => ['taxable' => $this->format_and_string($taxable_amount), 'cgst' => $this->format_and_string($total_csgt), 'sgst' => $this->format_and_string($total_sgst), 'cess' => '0.00'],
			'gate_pass_no' => '',
			'bill_logo' => $bilLogo,
		];

		return response()->json(['status' => 'success', 'data' => $data], 200);
	}

	public function format_and_string($value) 
	{
		return (string) sprintf('%0.2f', $value);
	}

	public function taxCal($params)
	{
		$data    = array();
		$barcode = $params['barcode'];
		$qty     = $params['qty'];
		$mrp     = $params['s_price'];
		$tax_code= $params['tax_code'];
		$store_id= $params['store_id'];


		$cgst_amount = 0;
		$sgst_amount = 0;
		$igst_amount = 0;
		$cess_amount = 0;
		$cgst = 0;
		$sgst = 0;
		$igst = 0;
		$cess = 0;
		$slab_amount = 0;
		$slab_cgst_amount = 0;
		$slab_sgst_amount = 0;
		$slab_cess_amount = 0;
		$tax_amount = 0;
		$taxable_amount = 0;
		$total = 0;
		$to_amount = 0;
		$tax_name = '';
		$store_db_name = $this->store_db_name($store_id);
		 
		$main = DB::table($store_db_name.'.invhsnsacmain')->select('HSN_SAC_CODE')->where('CODE', $tax_code)->first();
		$det = DB::table($store_db_name.'.invhsnsacdet')->select('CODE','INVGSTRATE_CODE','SLAB_APPL','SLAB_BASIS')->where('INVHSNSACMAIN_CODE', $tax_code)->orderBy('CODE', 'desc')->first();

		if (!empty($det)) {
			if ($det->SLAB_APPL == 'Y') {
				if ($det->SLAB_BASIS == 'N') {
					$mrp = round($mrp / $qty, 2);
				} elseif ($det->SLAB_BASIS == 'R') {
					$mrp = $mrp;
				}

				$slabs = DB::table($store_db_name.'.invhsnsacslab')->select('INVGSTRATE_CODE', 'AMOUNT_FROM')->where('INVHSNSACMAIN_CODE', $tax_code)->where('INVHSNSACDET_CODE', $det->CODE)->orderBy('AMOUNT_FROM','ASC')->get()->toArray();

				//dd($slabs);

				$numbers = array_column($slabs, 'AMOUNT_FROM');
				$min = min($numbers);
				$max = max($numbers);
				$range = [];
				$rangeSatisfy = false;

				//dd($slabs);
				$lowest_invgst_rate_code = null;
				foreach ($slabs as $key => $value) {
					if($key == 0){
						$lowest_invgst_rate_code = $value->INVGSTRATE_CODE;
					}
					
					if(isset($slabs[$key+1])){

						$range[] = [ 'from' => $value->AMOUNT_FROM ,'to' => $slabs[$key+1]->AMOUNT_FROM ];
					}
					//dd($cgst);				
				}

				$gst = DB::table($store_db_name.'.invgstrate')->where('CODE', $lowest_invgst_rate_code)->first();

				$slab_cgst_amount = $mrp / ( 100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE ) * $gst->CGST_RATE;
				$slab_sgst_amount = $mrp / ( 100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE ) * $gst->SGST_RATE;
				$slab_igst_amount = $mrp / ( 100 + $gst->IGST_RATE + $gst->CESS_RATE ) * $gst->IGST_RATE;
				$slab_cess_amount = $mrp / ( 100 + $gst->IGST_RATE + $gst->CESS_RATE ) * $gst->CESS_RATE;

				$total_tax_amount = $this->formatValue($slab_cgst_amount) + $this->formatValue($slab_sgst_amount) + $this->formatValue($slab_cess_amount);

				$total_taxable_amount = $mrp - $total_tax_amount;
				//print_r($range);
				//echo $total_taxable_amount;
				foreach($range as $keyR => $value){
					if($total_taxable_amount >= $value['from'] && $total_taxable_amount < $value['to']){
						
						$rangeSatisfy = true;

						foreach ($slabs as $key => $svalue) {
							if($svalue->AMOUNT_FROM == $value['from']){

								$invGstRateCode = $svalue->INVGSTRATE_CODE;
							}
						}
						

						$gst = DB::table($store_db_name.'.invgstrate')->where('CODE', $invGstRateCode)->first();
						//dd($gst);

						$slab_cgst_amount = $mrp / ( 100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE ) * $gst->CGST_RATE;
						$slab_sgst_amount = $mrp / ( 100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE ) * $gst->SGST_RATE;
						$slab_igst_amount = $mrp / ( 100 + $gst->IGST_RATE + $gst->CESS_RATE ) * $gst->IGST_RATE;
						$slab_cess_amount = $mrp / ( 100 + $gst->IGST_RATE + $gst->CESS_RATE ) * $gst->CESS_RATE;

						$total_tax_amount = $this->formatValue($slab_cgst_amount) + $this->formatValue($slab_sgst_amount) + $this->formatValue($slab_igst_amount) + $this->formatValue($slab_cess_amount);

						$cgst = $gst->CGST_RATE;
						$sgst = $gst->SGST_RATE;
						$igst = $gst->IGST_RATE;
						$cess = $gst->CESS_RATE;
						$cgst_amount = round($slab_cgst_amount, 2);
						$sgst_amount = round($slab_sgst_amount, 2);
						$igst_amount = 0;
						$cess_amount = $this->formatValue($slab_cess_amount);
						$tax_amount = $cgst_amount +$igst_amount + $sgst_amount + $cess_amount;
						$tax_amount = $this->formatValue($tax_amount);
						$taxable_amount = floatval($mrp) - floatval($tax_amount);
						$taxable_amount = $this->formatValue($taxable_amount);
						$total = $taxable_amount + $tax_amount;
						$tax_name = $gst->TAX_NAME;

						
					}
				}

					if(!$rangeSatisfy){
						//echo 'inside range';
						$slab_len = count($slabs);
						$temp = $slabs[$slab_len -1]->AMOUNT_FROM;
						if($total_taxable_amount > $temp){
							
							$invGstRateCode = $slabs[$slab_len -1]->INVGSTRATE_CODE;
							$gst = DB::table($store_db_name.'.invgstrate')->where('CODE', $invGstRateCode)->first();
							//dd($gst);

							$slab_cgst_amount = $mrp / ( 100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE ) * $gst->CGST_RATE;
							$slab_sgst_amount = $mrp / ( 100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE ) * $gst->SGST_RATE;
							$slab_igst_amount = $mrp / ( 100 + $gst->IGST_RATE + $gst->CESS_RATE ) * $gst->IGST_RATE;
							$slab_cess_amount = $mrp / ( 100 + $gst->IGST_RATE + $gst->CESS_RATE ) * $gst->CESS_RATE;

							$total_tax_amount = $this->formatValue($slab_cgst_amount) + $this->formatValue($slab_sgst_amount) + $this->formatValue($slab_igst_amount) + $this->formatValue($slab_cess_amount);

							$cgst = $gst->CGST_RATE;
							$sgst = $gst->SGST_RATE;
							$igst = $gst->IGST_RATE;
							$cess = $gst->CESS_RATE;
							$cgst_amount = round($slab_cgst_amount, 2);
							$sgst_amount = round($slab_sgst_amount, 2);
							$igst_amount = 0;
							$cess_amount = $this->formatValue($slab_cess_amount);
							$tax_amount = $cgst_amount +$igst_amount + $sgst_amount + $cess_amount;
							$tax_amount = $this->formatValue($tax_amount);
							$taxable_amount = floatval($mrp) - floatval($tax_amount);
							$taxable_amount = $this->formatValue($taxable_amount);
							$total = $taxable_amount + $tax_amount;
							$tax_name = $gst->TAX_NAME;
							
						}
						
					}

				//exit;

				$gst = DB::table($store_db_name.'.invgstrate')->where('CODE', $invGstRateCode)->first();
				//dd($gst);
				$slab_cgst_amount = $mrp / ( 100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE ) * $gst->CGST_RATE;
				$slab_sgst_amount = $mrp / ( 100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE ) * $gst->SGST_RATE;
				$slab_cess_amount = $mrp / ( 100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE ) * $gst->CESS_RATE;
				$slab_amount = $mrp - $this->formatValue($slab_sgst_amount) - $this->formatValue($slab_cgst_amount) - $this->formatValue($cess_amount);
				// $to_amount = $mrp - $this->formatValue($slab_sgst_amount) - $this->formatValue($slab_cgst_amount) - $this->formatValue($cess_amount);
				if ($slab_amount >= $max) {
					$cgst = $gst->CGST_RATE;
					$sgst = $gst->SGST_RATE;
					$igst = $gst->IGST_RATE;
					$cess = $gst->CESS_RATE;
					$cgst_amount = round($slab_cgst_amount, 2);
					$sgst_amount = round($slab_sgst_amount, 2);
					$igst_amount = 0;
					$cess_amount = $this->formatValue($slab_cess_amount);
					$tax_amount = $cgst_amount + $sgst_amount + $cess_amount;
					$tax_amount = $this->formatValue($tax_amount);
					$taxable_amount = floatval($mrp) - floatval($tax_amount);
					$taxable_amount = $this->formatValue($taxable_amount);
					$total = $taxable_amount + $tax_amount;
					$tax_name = $gst->TAX_NAME;
				}

				



			} elseif ($det->SLAB_APPL == 'N') {
				if($qty > 0){
					$mrp = round($mrp / $qty, 2);
					$gst = DB::table($store_db_name.'.invgstrate')->where('CODE', $det->INVGSTRATE_CODE)->first();
					$slab_cgst_amount = $mrp / ( 100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE ) * $gst->CGST_RATE;
					$slab_sgst_amount = $mrp / ( 100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE ) * $gst->SGST_RATE;
					$slab_cess_amount = $mrp / ( 100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE ) * $gst->CESS_RATE;
					$cgst = $gst->CGST_RATE;
					$sgst = $gst->SGST_RATE;
					$igst = $gst->IGST_RATE;
					$cess = $gst->CESS_RATE;
					$cgst_amount = round($slab_cgst_amount, 2);
					$sgst_amount = round($slab_sgst_amount, 2);
					$igst_amount = 0;
					$cess_amount = $this->formatValue($slab_cess_amount);
					$tax_amount = $cgst_amount + $sgst_amount + $cess_amount;
					$tax_amount = $this->formatValue($tax_amount);
					$taxable_amount = floatval($mrp) - floatval($tax_amount);
					$taxable_amount = $this->formatValue($taxable_amount);
					$total = $taxable_amount + $tax_amount;
					$tax_name = $gst->TAX_NAME;
				}
				 
				// dd($taxable_amount);
			}
		}

		$taxable_amount = $taxable_amount * $qty;
		$cgst_amount = $cgst_amount * $qty;
		$sgst_amount = $sgst_amount * $qty;
		$igst_amount = $igst_amount * $qty;
		$slab_cess_amount = $slab_cess_amount * $qty;
		$tax_amount = $tax_amount * $qty;
		$data = [
			'barcode'	=> $barcode,
			'hsn'		=> $main->HSN_SAC_CODE,
			'cgst'		=> $cgst,
			'sgst'		=> $sgst,
			'igst'		=> $igst,
			'cess'		=> $cess,
			'cgstamt'	=> (string)$cgst_amount,
			'sgstamt'	=> (string)$sgst_amount,
			'igstamt'	=> (string)$igst_amount,
			'cessamt'	=> (string)$slab_cess_amount,
			'netamt'	=> $mrp * $qty,
			'taxable'	=> (string)$taxable_amount,
			'tax'		=> (string)$tax_amount,
			'total'		=> $total * $qty,
			'tax_name'	=> $tax_name
		];	
		return $data;
	}

	public function formatValue($value)
	{
		if (is_float($value) && $value != '0.00') {
			$tax = explode(".", $value);
			if (count($tax) == 1) {
				$strlen = 1;
			} else {
				$strlen = strlen($tax[1]);
			}
			if ($strlen == 2 || $strlen == 1) {
				return (float)$value;
			} else {
				$strlen = $strlen - 2;
				return (float)substr($value, 0, -$strlen);
			}
		} else {
			return $value;
		}
	}

}