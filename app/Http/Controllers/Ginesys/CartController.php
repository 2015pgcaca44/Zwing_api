<?php

namespace App\Http\Controllers\Ginesys;

use App\Address;
use App\Cart;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\VendorSettingController;
use App\Http\CustomClasses\PrintInvoice;

use App\Order;
use App\Payment;
use App\Store;
use App\User;
use App\Reason;
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
use App\Vendor;

use App\Model\Stock\StockCurrentStatus;
use App\Model\Stock\StockTransactions;
use App\Model\Stock\StockLogs;
use App\Model\Stock\StockPoints;
use App\Model\Items\VendorSkuDetails;
use Event;
use App\Events\Loyalty;
use App\LoyaltyBill;
use App\Http\Controllers\LoyaltyController;
use App\Organisation;
use App\SyncReports;
use App\B2bCart;
use App\B2bOrder;
use App\B2bOrderDetails;
use App\B2bOrderExtra;

use App\Vendor\VendorRole;
use App\Vendor\VendorRoleUserMapping;

class CartController extends Controller
{

	public function __construct()
	{
		$this->middleware('auth', ['except' => ['order_receipt', 'rt_log']]);
	}

	public function store_db_name($store_id)
	{
		if ($store_id) {
			$store     = Store::find($store_id);
			$store_name = $store->store_db_name;
			return $store_name;
		} else {
			return false;
		}
	}

	public function get_carry_bags_offline(Request $request)
    {
        $v_id           = $request->v_id;
        $store_id       = $request->store_id; 
        // $c_id           = $request->c_id;
        // $order_id       = Order::where('user_id', Auth::user()->c_id)->where('status', 'success')->count();
        // $order_id       = $order_id + 1;
        // $store_db_name  = get_store_db_name(['store_id' => $store_id]);
        // $carry_bag     = Carry::select('barcode')->where('v_id', $v_id)->where('store_id', $store_id)->where('status','1')->where('deleted_status', '0')->get();
        // $carr_bag_arr   = $carry_bag->pluck('barcode')->all();
        // $carry_bags     = VendorSkuDetails::select('vendor_sku_details.barcode')->whereIn('vendor_sku_details.barcode', $carr_bag_arr)
        // ->where('vendor_sku_details.v_id', $v_id)
        // ->where('vendor_sku_details.deleted_at', null)
        // ->join('stock_current_status','stock_current_status.item_id','vendor_sku_details.item_id')
        // ->groupBy('vendor_sku_details.barcode')
        // ->get();
        $data = array();
        // if(count($carry_bags)> 0){

        //     foreach ($carry_bags as $key => $value) {
        //         $data[] = array(
        //             'BAG_ID' => $value->barcode,
        //         );
        //     }


        // }
        //return response()->json(['status' => 'get_carry_bags_by_store', 'data' => $data ],200);
        return ['status' => 'get_carry_bags_by_store', 'data' => $data ];
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
		$pdata = urldecode($request->pdata);
		$spdata = urldecode($request->pdata);
		$all_data = json_encode($request->data);
		$product_response = urldecode($request->data['item_det']);
		$product_response = json_decode($product_response);
		$single_cart_data = [];
		$pdata = json_decode($pdata);
		$totalTax = 0;
		$tax_details = [];
		$taxs = [];

		// Calculate Tax on each item
		$cgstamt = $sgstamt = $igstamt = $cessamt = $taxable = $netamt = $total =  0;

		foreach ($pdata as $key => $item) {
			$itemTax = $this->taxCal([
				'barcode'	=> $barcode,
				'qty'		=> $item->qty,
				's_price'	=> $item->total,
				'tax_code'	=> $product_response->INVHSNSACMAIN_CODE,
				'store_id'	=> $store_id
			]);

			$item->tax = $itemTax['tax'];
			$item->tax_details = json_encode($itemTax);

			$totalTax += $itemTax['tax'];
			$cgstamt += $itemTax['cgstamt'];
			$sgstamt += $itemTax['sgstamt'];
			$igstamt += $itemTax['igstamt'];
			$cessamt += $itemTax['cessamt'];
			$taxable += $itemTax['taxable'];
			$netamt += $itemTax['netamt'];
			$total += $item->total;
			$tax_details['barcode'] = $barcode;
			$tax_details['hsn'] = $itemTax['hsn'];
			$tax_details['cgst'] = $itemTax['cgst'];
			$tax_details['sgst'] = $itemTax['sgst'];
			$tax_details['igst'] = $itemTax['igst'];
			$tax_details['cess'] = $itemTax['cess'];
			$tax_details['tax_name'] = $itemTax['tax_name'];
			// $tax_details['each_qty'][] = $itemTax;

		}

		$tax_details['cgstamt'] = format_number($cgstamt);
		$tax_details['sgstamt'] = format_number($sgstamt);
		$tax_details['igstamt'] = format_number($igstamt);
		$tax_details['cessamt'] = format_number($cessamt);
		$tax_details['taxable'] = format_number($taxable);
		$tax_details['netamt'] = format_number($netamt);
		$tax_details['tax'] = format_number($totalTax);
		$tax_details['total'] = format_number($total);

		$order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
		$order_id = $order_id + 1;

		Cart::where('item_id', '!=', $barcode)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('order_id', '!=',  $order_id)->where('status', 'process')->delete();


		$cart_list = Cart::where('item_id', '!=', $barcode)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('order_id', $order_id)->where('status', 'process')->get();


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
			'tax' => format_number($totalTax),
			'department_id' => $product_response->DEPARTMENT_CODE,
			'group_id' => $product_response->SECTION_CODE,
			'division_id' => $product_response->DIVISION_CODE,
			'subclass_id' => $product_response->ARTICLE_CODE,
			'pdata' => $spdata,
			'tdata' => json_encode($tax_details),
			'section_target_offers' => $all_data,
		]);



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
			$item = DB::table($cart->store->store_db_name . '.invitem')->select('GRPCODE', 'INVARTICLE_CODE', 'BARCODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE', 'LISTED_MRP', 'DESC1', 'DESC2', 'DESC3', 'DESC4', 'DESC5', 'DESC6')->where('ICODE', $cart->item_id)->first();
			$single_cart_data['item'] = $item;
			$single_cart_data['store_db_name'] = $cart->store->store_db_name;
			$carts = Cart::where('store_id', $cart->store_id)->where('v_id', $cart->v_id)->where('order_id', $cart->order_id)->where('user_id', $cart->user_id)->where('status', 'process')->get();
			$single_cart_data['carts'] = $carts;
			$promoC = new PromotionController;
			$offer_data = $promoC->index($single_cart_data);
			// dd($offer_data);
			$data = (object) ['v_id' => $single_cart_data['v_id'], 'store_id' => $single_cart_data['store_id'], 'c_id' => $single_cart_data['c_id'], 'barcode' => $offer_data['barcode'], 'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'data' => $offer_data, 'trans_from' => $single_cart_data['trans_from'], 'vu_id' => $single_cart_data['vu_id']];
			// dd($data);
			// $cart = new CartController;
			$this->update_to_cart($data);
			// $this->process_each_item_in_cart($single_cart_data);
		}


		$cartD  = array('barcode' => $barcode, 'cart_id' => $cart_id->cart_id, 'pdata' => $pdata);
		$this->addCartDetail($cartD);


		$carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();
		$memoPromotions = null;
		$offerParams['carts'] = $carts;
		$offerParams['store_id'] = $store_id;
		$memoPromo = new PromotionController;
		$memoPromotions = $memoPromo->memoIndex($offerParams);

		if (!empty($memoPromotions)) {
			$mParams['store_id'] = $store_id;
			$mParams['items'] = $memoPromotions;
			$this->reCalculateTax($mParams);
			$carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();
		}

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

		return response()->json([
			'status' => 'add_to_cart', 'message' => 'Product was successfully added to your cart.', 'total_qty' => $carts->sum('qty'), 'total_amount' => $total_amount,
		], 200);
	}

	private function reCalculateTax($params)
	{
		// dd($params['items']);
		foreach ($params['items'] as $key => $value) {
			$itemTax = $this->taxCal([
				'barcode'	=> $value->item_id,
				'qty'		=> $value->qty,
				's_price'	=> $value->total,
				'tax_code'	=> $value->hsn,
				'store_id'	=> $params['store_id']
			]);

			Cart::find($value->cart_id)->update(['bill_buster_discount' => format_number($value->discount), 'total' => format_number($value->total), 'tax' => $itemTax['tax'], 'tdata' => json_encode($itemTax)]);
		}
	}

	private function addCartDetail($params)
	{
		$cart_id  = $params['cart_id'];
		$barcode  = $params['barcode'];
		$pdata    = $params['pdata'];
		CartDetails::where('cart_id', $cart_id)->delete();
		if ($pdata) {
			foreach ($pdata as $item) {
				if (empty($item->promo_code)) {
					$is_promo = 0;
				} else {
					$is_promo = 1;
				}
				$cartdetail  = new CartDetails();
				$cartdetail->cart_id = $cart_id;
				$cartdetail->barcode = $barcode;
				$cartdetail->qty     = $item->qty;
				$cartdetail->mrp     = $item->unit_mrp;
				$cartdetail->discount = $item->discount;
				$cartdetail->ext_price = $item->total;
				$cartdetail->price   = $item->unit_rsp;
				$cartdetail->ru_prdv = (isset($item->slab_code)) ? $item->slab_code : '';
				$cartdetail->promo_id = (isset($item->promo_code)) ? $item->promo_code : '';
				$cartdetail->is_promo = (isset($is_promo)) ? $is_promo : '';
				$cartdetail->message = $item->message;
				$cartdetail->tax = $item->tax;
				$cartdetail->taxes = $item->tax_details;
				$cartdetail->save();
			}
		} else {
			$cart = Cart::find($cart_id);
			CartDetails::create(['cart_id' => $cart_id, 'barcode' => $barcode, 'qty' => $cart->qty, 'mrp' => $cart->unit_mrp, 'price' => $cart->unit_csp, 'discount' => $cart->discount, 'ext_price' => $cart->total, 'is_promo' => 0, 'tax' => $cart->tax, 'taxes' => $cart->tdata]);
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
		$totalTax = 0;
		$tax_details = [];
		$taxs = [];
		// Calculate Tax on each item
		$cgstamt = $sgstamt = $igstamt = $cessamt = $taxable = $netamt = $total =  0;

		foreach ($pdata as $key => $item) {
			$itemTax = $this->taxCal([
				'barcode'	=> $barcode,
				'qty'		=> $item->qty,
				's_price'	=> $item->total,
				'tax_code'	=> $product_response->INVHSNSACMAIN_CODE,
				'store_id'	=> $store_id
			]);

			$item->tax = $itemTax['tax'];
			$item->tax_details = json_encode($itemTax);

			$totalTax += $itemTax['tax'];
			$cgstamt += $itemTax['cgstamt'];
			$sgstamt += $itemTax['sgstamt'];
			$igstamt += $itemTax['igstamt'];
			$cessamt += $itemTax['cessamt'];
			$taxable += $itemTax['taxable'];
			$netamt += $itemTax['netamt'];
			$total += $item->total;
			$tax_details['barcode'] = $barcode;
			$tax_details['hsn'] = $itemTax['hsn'];
			$tax_details['cgst'] = $itemTax['cgst'];
			$tax_details['sgst'] = $itemTax['sgst'];
			$tax_details['igst'] = $itemTax['igst'];
			$tax_details['cess'] = $itemTax['cess'];
			$tax_details['tax_name'] = $itemTax['tax_name'];
			// $tax_details['each_qty'][] = $itemTax;

		}

		$tax_details['cgstamt'] = format_number($cgstamt);
		$tax_details['sgstamt'] = format_number($sgstamt);
		$tax_details['igstamt'] = format_number($igstamt);
		$tax_details['cessamt'] = format_number($cessamt);
		$tax_details['taxable'] = format_number($taxable);
		$tax_details['netamt'] = format_number($netamt);
		$tax_details['tax'] = format_number($totalTax);
		$tax_details['total'] = format_number($total);

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
			'bill_buster_discount' => 0,
			'status' => 'process',
			'date' => date('Y-m-d'),
			'time' => date('H:i:s'),
			'month' => date('m'),
			'year' => date('Y'),
			'tax' => format_number($totalTax),
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
			$item = DB::table($cart->store->store_db_name . '.invitem')->select('GRPCODE', 'INVARTICLE_CODE', 'BARCODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE', 'LISTED_MRP', 'DESC1', 'DESC2', 'DESC3', 'DESC4', 'DESC5', 'DESC6')->where('ICODE', $cart->item_id)->first();
			$single_cart_data['item'] = $item;
			$single_cart_data['store_db_name'] = $cart->store->store_db_name;
			$carts = Cart::where('store_id', $cart->store_id)->where('v_id', $cart->v_id)->where('order_id', $cart->order_id)->where('user_id', $cart->user_id)->where('status', 'process')->get();
			$single_cart_data['carts'] = $carts;
			$promoC = new PromotionController;
			$offer_data = $promoC->index($single_cart_data);
			// dd($offer_data);
			$data = (object) ['v_id' => $single_cart_data['v_id'], 'store_id' => $single_cart_data['store_id'], 'c_id' => $single_cart_data['c_id'], 'barcode' => $offer_data['barcode'], 'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'data' => $offer_data, 'trans_from' => $single_cart_data['trans_from'], 'vu_id' => $single_cart_data['vu_id']];
			// dd($data);
			// $cart = new CartController;
			$this->update_to_cart($data);
			// $this->process_each_item_in_cart($single_cart_data);
		}
		//dd('this is updated');

		$cartD  = array('barcode' => $barcode, 'cart_id' => $cartuse->cart_id, 'pdata' => $pdata);
		$this->addCartDetail($cartD);

		$carts = DB::table('cart')->where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();

		$memoPromotions = null;
		$offerParams['carts'] = $carts;
		$offerParams['store_id'] = $store_id;
		$memoPromo = new PromotionController;
		$memoPromotions = $memoPromo->memoIndex($offerParams);

		if (!empty($memoPromotions)) {
			$mParams['store_id'] = $store_id;
			$mParams['items'] = $memoPromotions;
			$this->reCalculateTax($mParams);
			$carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();
		}

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

		return response()->json([
			'status' => 'add_to_cart', 'message' => 'Product was successfully added to your cart.', 'total_qty' => $carts->sum('qty'), 'total_amount' => $total_amount,
		], 200);
	}

	public function itemGroupByTotal($data)
	{
		$pdata = [];
		$collection = collect($data);

		$grouped = $collection->groupBy(function ($item, $key) {
			$item->total = (string) $item->total;
			return $item->total;
		});

		foreach ($grouped as $key => $value) {
			$item = collect($value);

			if (array_key_exists('promo_code', $value[0])) {
				$promo_code = $value[0]->promo_code;
			} else {
				$promo_code = '';
			}

			if (array_key_exists('no', $value[0])) {
				$no = $value[0]->no;
			} else {
				$no = '';
			}

			if (array_key_exists('start_date', $value[0])) {
				$start_date = $value[0]->start_date;
			} else {
				$start_date = '';
			}

			if (array_key_exists('end_date', $value[0])) {
				$end_date = $value[0]->end_date;
			} else {
				$end_date = '';
			}

			$pdata[] = (object) [
				'item_id'					=>	$value[0]->item_id,
				'qty'						=>	$item->sum('qty'),
				'unit_mrp'					=>	$value[0]->unit_mrp,
				'unit_rsp'					=>	$value[0]->unit_rsp,
				'total' 					=>	$item->sum('total'),
				'discount' 					=>	$item->sum('discount'),
				'message'					=>	$value[0]->message,
				'discount_price_basis'		=>	$value[0]->discount_price_basis,
				'promo_code'				=>	$promo_code,
				'no'						=>	$no,
				'start_date'				=>	$start_date,
				'end_date'					=>	$end_date
			];
		}

		return $pdata;
	}

	public function update_to_cart($values)
	{
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
		$pdata = json_decode($pdata);
		$totalTax = 0;
		$tax_details = [];

		// Merge quantity if total is same
		$pdata = $this->itemGroupByTotal($pdata);

		// dd($pdata);

		// Calculate Tax on each item
		$cgstamt = $sgstamt = $igstamt = $cessamt = $taxable = $netamt = $total =  0;

		foreach ($pdata as $key => $item) {
			$itemTax = $this->taxCal([
				'barcode'	=> $barcode,
				'qty'		=> $item->qty,
				's_price'	=> $item->total,
				'tax_code'	=> $product_response->INVHSNSACMAIN_CODE,
				'store_id'	=> $store_id
			]);

			$item->tax = $itemTax['tax'];
			$item->tax_details = json_encode($itemTax);

			$totalTax += $itemTax['tax'];
			$cgstamt += $itemTax['cgstamt'];
			$sgstamt += $itemTax['sgstamt'];
			$igstamt += $itemTax['igstamt'];
			$cessamt += $itemTax['cessamt'];
			$taxable += $itemTax['taxable'];
			$netamt += $itemTax['netamt'];
			$total += $item->total;
			$tax_details['barcode'] = $barcode;
			$tax_details['hsn'] = $itemTax['hsn'];
			$tax_details['cgst'] = $itemTax['cgst'];
			$tax_details['sgst'] = $itemTax['sgst'];
			$tax_details['igst'] = $itemTax['igst'];
			$tax_details['cess'] = $itemTax['cess'];
			$tax_details['tax_name'] = $itemTax['tax_name'];
			// $tax_details['each_qty'][] = $itemTax;

		}

		$tax_details['cgstamt'] = format_number($cgstamt);
		$tax_details['sgstamt'] = format_number($sgstamt);
		$tax_details['igstamt'] = format_number($igstamt);
		$tax_details['cessamt'] = format_number($cessamt);
		$tax_details['taxable'] = format_number($taxable);
		$tax_details['netamt'] = format_number($netamt);
		$tax_details['tax'] = format_number($totalTax);
		$tax_details['total'] = format_number($total);

		// dd($tax_details);

		$taxs = [];

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
			'bill_buster_discount' => 0,
			'status' => 'process',
			'date' => date('Y-m-d'),
			'time' => date('H:i:s'),
			'month' => date('m'),
			'year' => date('Y'),
			'tax' => format_number($totalTax),
			'department_id' => $product_response->DEPARTMENT_CODE,
			'group_id' => $product_response->SECTION_CODE,
			'division_id' => $product_response->DIVISION_CODE,
			'subclass_id' => $product_response->ARTICLE_CODE,
			'pdata' => $spdata,
			'tdata' => json_encode($tax_details),
			'section_target_offers' => $all_data,
		]);
		// dd($cart_id);
		$cartD  = array('barcode' => $barcode, 'cart_id' => $cart_id, 'pdata' => $pdata);
		$this->addCartDetail($cartD);

		$carts = DB::table('cart')->where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();
		$memoPromotions = null;
		$offerParams['carts'] = $carts;
		$offerParams['store_id'] = $store_id;
		$memoPromo = new PromotionController;
		$memoPromotions = $memoPromo->memoIndex($offerParams);

		if (!empty($memoPromotions)) {
			$mParams['store_id'] = $store_id;
			$mParams['items'] = $memoPromotions;
			$this->reCalculateTax($mParams);
			$carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();
		}

		return response()->json([
			'status' => 'add_to_cart', 'message' => 'Product quantity successfully Updated',
			//, 'data' => $cart
			'total_qty' => $carts->sum('qty'), 'total_amount' => $carts->sum('total'),
		], 200);
	}


	public function product_qty_update(Request $request)
	{
		// dd($request->all());
		$v_id = $request->v_id;
		$c_id = $request->c_id;
		$store_id = $request->store_id;
		$trans_from = $request->trans_from;
		$vu_id = $request->vu_id;
		if ($request->has('ogbarcode')) {
			$barcode = $request->ogbarcode;
		} else {
			$barcode = $request->barcode;
		}


		$qty = $request->qty;
		$unit_mrp = $request->unit_mrp;
		$r_price = $request->r_price;
		$s_price = $request->s_price;
		$discount = $request->discount;

		$stores = Store::select('name', 'mapping_store_id', 'store_db_name')->where('store_id', $store_id)->first();
		$store_name = $stores->name;
		$store_db_name = $stores->store_db_name;

		//Getting barcode without strore tagging
		$item = DB::table($store_db_name . '.invitem')->select('GRPCODE', 'INVARTICLE_CODE', 'BARCODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE', 'LISTED_MRP', 'DESC1', 'DESC2', 'DESC3', 'DESC4', 'DESC5', 'DESC6')->where('ICODE', $barcode)->first();
		$order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
		$order_id = $order_id + 1;

		$carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

		$promoC = new PromotionController;

		$item = removeSpecialChar($item);
		$params = ['v_id' => $v_id, 'trans_from' => $trans_from, 'barcode' => $barcode, 'qty' =>  $qty, 'mapping_store_id' => $stores->mapping_store_id, 'item' => $item, 'carts' => $carts, 'store_db_name' => $store_db_name, 'is_cart' => 1, 'is_update' => 1];
		$offer_data = $promoC->index($params);
		// $data = $offer_data;

		$data = (object) ['v_id' => $v_id, 'store_id' => $store_id, 'c_id' => $c_id, 'barcode' => $offer_data['barcode'], 'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'tdata' => $offer_data['tdata'], 'data' => $offer_data, 'trans_from' => $trans_from, 'vu_id' => $vu_id];
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
			$item = DB::table($cart->store->store_db_name . '.invitem')->select('GRPCODE', 'INVARTICLE_CODE', 'BARCODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE', 'LISTED_MRP', 'DESC1', 'DESC2', 'DESC3', 'DESC4', 'DESC5', 'DESC6')->where('ICODE', $cart->item_id)->first();
			$item = removeSpecialChar($item);
			$single_cart_data['item'] = $item;
			$single_cart_data['store_db_name'] = $cart->store->store_db_name;
			$carts = Cart::where('store_id', $cart->store_id)->where('v_id', $cart->v_id)->where('order_id', $cart->order_id)->where('user_id', $cart->user_id)->where('status', 'process')->get();
			$single_cart_data['carts'] = $carts;
			$promoC = new PromotionController;
			$offer_data = $promoC->index($single_cart_data);
			// dd($offer_data);
			$data = (object) ['v_id' => $single_cart_data['v_id'], 'store_id' => $single_cart_data['store_id'], 'c_id' => $single_cart_data['c_id'], 'barcode' => $offer_data['barcode'], 'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'data' => $offer_data, 'trans_from' => $single_cart_data['trans_from'], 'vu_id' => $single_cart_data['vu_id']];
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

		return response()->json(['status' => 'product_qty_update', 'message' => 'Product quantity successfully Updated', 'total_qty' => $carts->sum('qty'), 'total_amount' => (string) $carts->sum('total')], 200);
	}

	public function remove_product(Request $request)
	{
		// dd($request->all());
		$v_id = $request->v_id;
		$c_id = $request->c_id;
		$store_id = $request->store_id;
		$v_id = $request->v_id;

		$carts = null;

		//$barcode = $request->barcode;
		if ($request->has('all')&& !empty($request->all) ) {
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

				DB::table('cr_dr_settlement_log')->where('order_id', $order_id)->where('user_id', $c_id)->delete();
			}
		} else {

			if ($request->has('cart_id')) {
				$cart_id = $request->cart_id;
				Cart::where('cart_id', $cart_id)->delete();
				CartDetails::where('cart_id', $cart_id)->delete();



				$params = ['v_id' => $v_id, 'store_id' => $store_id, 'c_id' => $c_id, 'trans_from' => $request->trans_from, 'vu_id' => $request->vu_id];
				$cart_list = Cart::where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('vu_id', $request->vu_id)->where('trans_from', $request->trans_from)->where('status', 'process')->get();


				if ($cart_list->isEmpty()) {
					$order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
					$order_id = $order_id + 1;
					DB::table('cr_dr_settlement_log')->where('order_id', $order_id)->where('user_id', $c_id)->delete();
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
					$item = DB::table($cart->store->store_db_name . '.invitem')->select('GRPCODE', 'INVARTICLE_CODE', 'BARCODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE', 'LISTED_MRP', 'DESC1', 'DESC2', 'DESC3', 'DESC4', 'DESC5', 'DESC6')->where('ICODE', $cart->item_id)->first();
					$item = removeSpecialChar($item);
					$single_cart_data['item'] = $item;
					$single_cart_data['store_db_name'] = $cart->store->store_db_name;
					$carts = Cart::where('store_id', $cart->store_id)->where('v_id', $cart->v_id)->where('order_id', $cart->order_id)->where('user_id', $cart->user_id)->where('status', 'process')->get();
					$single_cart_data['carts'] = $carts;
					$promoC = new PromotionController;
					$offer_data = $promoC->index($single_cart_data);
					// dd($offer_data);
					$data = (object) ['v_id' => $single_cart_data['v_id'], 'store_id' => $single_cart_data['store_id'], 'c_id' => $single_cart_data['c_id'], 'barcode' => $offer_data['barcode'], 'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'data' => $offer_data, 'trans_from' => $single_cart_data['trans_from'], 'vu_id' => $single_cart_data['vu_id']];
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


		$roundoff_total = 0;
        $cart_qty_total = 0;

        if($carts){
            $roundoff_total = $carts->sum('total');
            $cart_qty_total = $carts->where('weight_flag','!=',1)->sum('qty') + $carts->where('weight_flag',1)->count();
        }

		return response()->json(['status' => 'remove_product', 'message' => 'Product Removed',
			'total'  => format_number($roundoff_total), 
            'cart_qty_total'    => (string)round($cart_qty_total)
			], 200);
	}

	public function cart_details(Request $request)
	{
		$v_id = $request->v_id;
		$c_id = $request->c_id;
		$store_id = $request->store_id;
		$trans_from = $request->trans_from;
		if ($request->has('vu_id')) {
			$user_id = $request->vu_id;
		}
		$role = VendorRoleUserMapping::select('role_id')->where('user_id', $user_id)->first();
		$role_id = $role->role_id;
		$carry_bag_added = false;
		$data = [];
		$total_subtotal = 0;
		$total_tax = 0;
		$total_discount = $bill_buster_discount = 0;
		$total_amount = 0;

		$order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
		$order_id = $order_id + 1;

		$cart = DB::table('cart')->where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status', 'process')->get();


		$total_qty = 0;

		foreach ($cart as $key => $value) {
			$total_qty += $value->qty;

			//dd($value->item_id);

			//$carr_bag_arr = ['VR132797', 'VR132799', 'VR132807'];
			$carr_bag_arr = [];
			$carry_bags = DB::table('carry_bags')->where('v_id', $v_id)->where('store_id', $store_id)->where('status', '1')->get();
			if ($carry_bags->isEmpty()) {
				//echo 'insdie this';exit;
				$carry_bags = DB::table('carry_bags')->where('v_id', $v_id)->where('store_id', '0')->where('status', '1')->get();
			}
			//dd($carry_bags);
			if ($carry_bags) {
				$carr_bag_arr = $carry_bags->pluck('barcode')->all();
			}
			// dd($carr_bag_arr);
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
			$sParam = ['v_id' => $v_id,'store_id'=>$store_id,'role_id'=>$role_id,'user_id'=>$user_id, 'trans_from' => $trans_from];
			$product_default_image = $vendorS->getProductDefaultImage($sParam);

			$response['carry_bag_flag'] = $carry_bag_flag;
			$total_subtotal += $value->total;
			$total_tax += $value->tax;
			$total_discount += $value->discount;
			$bill_buster_discount += $value->bill_buster_discount;
			$total_amount += $value->subtotal;
			$remark = '';
			if (isset($value->remark)) {
				$remark = $value->remark;
			}

			$salesman_name ='';
            $salesmans = Vendor::where('id', $value->salesman_id)->first();
            //dd($salesmans);
            if($salesmans){
                $salesman_name = $salesmans->first_name.' '.$salesmans->last_name;
            }

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
					'remark' => $remark,
					'tdata' => $value->tdata
				],
				'amount' => (string) $product_details->s_price,
				'qty' => $value->qty,
				'tax_amount' => $value->tax,
				'delivery' => 'No',
				'tdata' => json_decode($value->tdata),
				'salesman_id' => $value->salesman_id,
				'salesman_name' => $salesman_name
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
		$vouchers = DB::table('cr_dr_settlement_log')->select('id', 'voucher_id', 'applied_amount')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('order_id', $order_id)->get();

		$voucher_total = 0;
		$pay_by_voucher = 0;
		foreach ($vouchers as $key => $voucher) {
			$voucher_applied = DB::table('cr_dr_settlement_log')->where('voucher_id', $voucher->voucher_id)->where('status', 'APPLIED')->get();
			$totalVoucher = DB::table('cr_dr_voucher')->where('id', $voucher->voucher_id)->first()->amount;
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

			DB::table('cr_dr_settlement_log')->where('id', $voucher->id)->update(['status' => 'PROCESS', 'applied_amount' => $voucher_applied_amount]);
		}
		$voucher_total = $pay_by_voucher;
		// dd($data);

		// $carr_bag_arr =  [ 'VR132797', 'VR132799' ,'VR132807'];
		// $carry_bag_flag = in_array($cart->barcode, $carr_bag_arr);

		// if($carry_bag_flag){
		// $carry_bag_added = true;
		// }
		$vendorS = new VendorSettingController;
		$sParams = ['v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $user_id, 'role_id' => $role_id, 'trans_from' => $trans_from];
		// $sParams = ['v_id' => $v_id, 'trans_from' => $trans_from];
		$product_max_qty = $vendorS->getProductMaxQty($sParams);
		$cart_max_item = $vendorS->getMaxItemInCart($sParams);

		$paymentTypeSettings = $vendorS->getPaymentTypeSetting($sParams);

		$bill_summary = [];

		$bill_summary[] = ['name' => 'sub_total',  'display_text' => 'Sub Total' ,'display_name' => 'Sub Total' , 'item_type'=>"", 'value' => (string) format_number($total_amount)];
		$bill_summary[] = ['name' => 'discount', 'display_text' => 'Discount','display_name' => 'Discount','item_type'=>"",'value' => (string) format_number($total_discount)];
		$bill_summary[] = ['name' => 'bill_discount', 'display_text' => 'Bill Discount', 'display_name' => 'Bill Buster Discount','item_type'=>"2" ,'value' => (string) format_number($bill_buster_discount)];
		$bill_summary[] = ['name' => 'tax_total', 'display_text' => 'Tax Total', 'display_name' => 'Tax Total (Included)','type' => 'INCLUSIVE', 'value' => (string) format_number($total_tax)];
		$bill_summary[] = ['name' => 'total','display_name' => 'Total' ,'item_type'=>"", 'display_text' => 'Total', 'value' => (string) format_number($roundoff_total)];

		if ($voucher_total > 0) {

			$bill_summary[] = ['name' => 'voucher', 'display_text' => 'Voucher Total', 'display_name' => 'Voucher Total' ,'item_type'=>"",'value' => (string) format_number($voucher_total)];
		}

		$hold_bill_count = 0;
		$hold_bill_count_flag = false;
		$cartSettings = $vendorS->getCartSetting($sParams);
		if (isset($cartSettings->recall_bill)) {
			if (isset($cartSettings->recall_bill->$trans_from)) {
				$status = $cartSettings->recall_bill->$trans_from;
				if ($status->status == 1) {
					$hold_bill_count_flag = true;
				}
			} else {
				$status = $cartSettings->recall_bill->DEFAULT;
				if ($status->status == 1) {
					$hold_bill_count_flag = true;
				}
			}
		}

		if ($hold_bill_count_flag) {
			$hold_bill_count = Order::where('transaction_sub_type', 'hold');
			if ($request->has('vu_id')) {
				$hold_bill_count = $hold_bill_count->where('vu_id', $request->vu_id)->count();
			} else {
				$hold_bill_count = $hold_bill_count->where('user_id', $c_id)->count();
			}
		}

		$bill_summary = collect($bill_summary);
		$bill_summary = $bill_summary->whereNotIn('value', ['0.00'])->values();


		return response()->json([
			'status' => 'cart_details', 'message' => 'Your Cart Details',
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
			'bill_buster_discount' => (string) format_number($bill_buster_discount),
			'discount' => (string) format_number($total_discount),
			//'grand_total' => (format_number($grand_total))?format_number($grand_total):'0.00',
			'order_id' => $order_id,
			'carry_bag_total' => '0.00',
			'carry_bag_qty_total' => (string) collect($bags)->sum('Qty'),
			'voucher_total' => $voucher_total,
			'vouchers' => $voucher_array,
			'pay_by_voucher' => (string) format_number($pay_by_voucher),
			'total' => (string) format_number($roundoff_total),
			'cart_qty_total' => $total_qty,
			'saving' => (string) format_number($total_saving),
			'delivered' => 'No',
			'offered_mount' => '0.00',
			'hold_bill_count' => (string) $hold_bill_count,
			'bill_summary' => $bill_summary
		], 200);
	}

	public function process_to_payment(Request $request)
	{
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

		//Hold Bill
		$hold_bill = 0;
		$transaction_sub_type = 'sales';
		if ($request->has('hold_bill')) {
			$hold_bill = $request->hold_bill;
			$transaction_sub_type = 'hold';
			$hold_bill = 1;
		}
		if ($request->has('transaction_sub_type')) {
			$transaction_sub_type = $request->transaction_sub_type;
			$hold_bill = 1;
		}


		//Checking Opening balance has entered or not if payment is through cash
		$vendorSetting = new \App\Http\Controllers\VendorSettingController;
		$paymentTypeSettings = $vendorSetting->getPaymentTypeSetting(['v_id' => $v_id, 'trans_from' => $trans_from]);
		$cash_flag = false;
		foreach ($paymentTypeSettings as $type) {
			if ($type->name == 'cash') {
				if ($type->status == 1) {
					$cash_flag = true;
				}
			}
		}

		if (($vu_id > 0 && $payment_gateway_type == 'CASH') || $cash_flag) {
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

		if ($request->payment_gateway_type == 'LOYALTY') {

			// if ($request->has('loyalty')) {
			// 	$userInformation = User::find($c_id);
			// 	$loyaltyPrams = [ 'type' => $request->loyaltyType, 'event' => 'getUrl', 'mobile' => $userInformation->mobile, 'vu_id' => $request->vu_id, 'settings' => $request->loyaltySettings, 'zw_event' => 'generateUrl', 'v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $c_id, 'order_id' => $request->temp_order_id, 'billAmount' => $request->total ];
			// 	$loyaltyCon = new LoyaltyController;
			// 	$loyaltyUrl = $loyaltyCon->index($loyaltyPrams);

			// 	$orderDetails = Order::where('order_id', $request->temp_order_id)->first();

			// 	$orderC = new OrderController;
			// 	$order_arr = $orderC->getOrderResponse(['order' => $orderDetails , 'v_id' => $v_id , 'trans_from' => $trans_from ]) ;

			// 	$res =  ['status' => 'proceed_to_payment', 'message' => 'Proceed to Payment', 'data' => $orderDetails , 'order_summary' => $order_arr, 'loyalty_url' => $loyaltyUrl->response['url'] ];

			// 	if($request->has('response') && $request->response == 'ARRAY') {	
			// 		return $res;
			// 	} else {
			// 		return response()->json($res, 200);
			// 	}

			// }

			if ($request->has('loyalty')) {
				$userInformation = User::find($c_id);
				$loyaltyPrams = ['type' => $request->loyaltyType, 'event' => 'getCouponUrl', 'mobile' => $userInformation->mobile, 'vu_id' => $request->vu_id, 'settings' => $request->loyaltySettings, 'zw_event' => 'generateCouponUrl', 'v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $c_id, 'order_id' => $request->temp_order_id, 'billAmount' => $request->total];
				$loyaltyCon = new LoyaltyController;
				$loyaltyUrl = $loyaltyCon->index($loyaltyPrams);

				$orderDetails = Order::where('order_id', $request->temp_order_id)->first();

				$orderC = new OrderController;
				$order_arr = $orderC->getOrderResponse(['order' => $orderDetails, 'v_id' => $v_id, 'trans_from' => $trans_from]);

				$res =  ['status' => 'proceed_to_payment', 'message' => 'Proceed to Payment', 'data' => $orderDetails, 'order_summary' => $order_arr, 'loyalty_url' => $loyaltyUrl->response['url']];

				if ($request->has('response') && $request->response == 'ARRAY') {
					return $res;
				} else {
					return response()->json($res, 200);
				}
			}
		}

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
		$order->transaction_sub_type = $transaction_sub_type;
		if ($request->has('manual_discount')) {
			$order->manual_discount = $request->manual_discount;
			$order->md_added_by = $request->vu_id;
		}

		$order->bill_buster_discount = $bill_buster_discount;
		$order->tax = $tax;
		$order->total = (float) $total + (float) $pay_by_voucher;
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

			//Deleting cart if hold bill is true
			if ($transaction_sub_type == 'hold') {

				CartDetails::where('cart_id', $value['cart_id'])->delete();
				CartOffers::where('cart_id', $value['cart_id'])->delete();
				Cart::where('cart_id', $value['cart_id'])->delete();
			}
		}




		$payment = null;
		if ($pay_by_voucher > 0.00 && $total == 0.00) {

			$request->request->add(['t_order_id' => $t_order_id, 'order_id' => $order_id, 'pay_id' => 'user_order_id_' . $t_order_id, 'method' => 'voucher_credit', 'invoice_id' => '', 'bank' => '', 'wallet' => '', 'vpa' => '', 'error_description' => '', 'status' => 'success', 'payment_gateway_type' => 'Voucher', 'cash_collected' => '', 'cash_return' => '', 'amount' => $pay_by_voucher]);

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

		$orderC = new OrderController;
		$order_arr = $orderC->getOrderResponse(['order' => $order, 'v_id' => $v_id, 'trans_from' => $trans_from]);

		$order = array_add($order, 'order_id', $porder_id);

		// Loyality

		// if($request->has('loyalty')) {
		// $checkLoyaltyBillSubmit = LoyaltyBill::where('vendor_id', $v_id)->where('store_id', $store_id)->where('user_id', $user_id)->where('invoice_no', $zwing_invoice_id)->where('type', 'easeMyRetail')->where('is_submitted', '1')->first();
		// if (empty($checkLoyaltyBillSubmit)) {
		// $userInformation = User::find($c_id);
		// $invoice_id = Invoice::where('ref_order_id', $order_id)->first()->invoice_id;
		// $loyaltyPrams = [ 'type' => $request->loyaltyType, 'event' => 'getUrl', 'mobile' => $order->user->mobile, 'vu_id' => $request->vu_id, 'settings' => $request->loyaltySettings, 'zw_event' => 'generateUrl', 'v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $c_id, 'order_id' => $order_id, 'billAmount' => $order->total ];
		// $loyaltyCon = new LoyaltyController;
		// $loyaltyUrl = $loyaltyCon->index($loyaltyPrams);

		// Event::fire(new loyalty($loyaltyPrams));
		// }
		// dd($loyaltyPrams);
		// $res =  ['status' => 'proceed_to_payment', 'message' => 'Proceed to Payment', 'data' => $order , 'order_summary' => $order_arr, 'loyalty_url' => $loyaltyUrl->response['url'] ];
		// } else {
		$res =  ['status' => 'proceed_to_payment', 'message' => 'Proceed to Payment', 'data' => $order, 'order_summary' => $order_arr];
		// }


		if ($request->has('response') && $request->response == 'ARRAY') {
			return $res;
		} else {
			return response()->json($res, 200);
		}
	}

	public function payment_details(Request $request)
	{
		// dd($request->all());
		$v_id = $request->v_id;
		$c_id = $request->c_id;
		$store_id = $request->store_id;
		$t_order_id = $request->t_order_id;
		$order_id = $request->order_id;
		$user_id = $request->c_id;
		$pay_id = $request->pay_id;
		$amount = $request->amount;
		$method = $request->get('method');
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
		$paymenDetails = null;
		$totalLPDiscount = null;


		$orders = Order::where('order_id', $order_id)->first();


		if ($orders->total == $amount) {
			$payment_type = 'full';
		} else {
			$totalPaymentAmount = $orders->total - $orders->payments->sum('amount');
			if ($totalPaymentAmount == $amount) {
				$payment_type = 'full';
			} else {
				$payment_type = 'partial';
			}
		}

		// dd($orders->payments->count());

		$remark = '';
		if ($orders && $request->has('remark')) {
			$orders->remark = $request->remark;
			$orders->save();
		}

		if ($orders->payment_type != 'full') {
			$payment_type = 'partial';
		}

		$vu_id = 0;
		if ($request->has('vu_id')) {
			$vu_id = $request->vu_id;
		}

		$udidtoken = '';
		if ($request->has('udidtoken')) {
			$udidtoken = $request->udidtoken;
		}

		$payment_save_status = false;
		if ($request->has('payment_gateway_type')) {
			$payment_gateway_type = $request->payment_gateway_type; //'EZETAP'
		} else {
			$payment_gateway_type = 'RAZOR_PAY';
		}

		//Checking Opening balance has entered or not if payment is through cash
		if ($vu_id > 0 && $payment_gateway_type == 'CASH') {
			$vendorSett = new \App\Http\Controllers\VendorSettlementController;
			$response = $vendorSett->opening_balance_status($request);
			if ($response) {
				return $response;
			}
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
		} else if ($payment_gateway_type == 'EZSWYPE_INTERNAL') {

			$gateway_response = $request->gateway_response;

			$gateway_response = json_decode($gateway_response);

			$payment_save_status = true;
		} else if ($payment_gateway_type == 'PINELAB_INTERNAL') {

			$gateway_response = $request->gateway_response;
			$gateway_response = json_decode($gateway_response);
			$payment_save_status = true;
		} elseif ($payment_gateway_type == 'LOYALTY') {

			$gateway_response = $request->gateway_response;
			$gateway_response = json_decode($gateway_response);
			$payment_save_status = true;
		} elseif ($payment_gateway_type == 'PAYTM_OFFLINE' || $payment_gateway_type == 'PAYTM') {

			$method = 'paytm';
			if ($payment_gateway_type == 'PAYTM') {
				$gateway_response = $request->gateway_response;
				$gateway_response = json_decode($gateway_response);
			}
			$payment_save_status = true;
		} elseif ($payment_gateway_type == 'GOOGLE_TEZ_OFFLINE' || $payment_gateway_type == 'GOOGLE_TEZ') {

			$method = 'google_tez';
			if ($payment_gateway_type == 'GOOGLE_TEZ') {
				$gateway_response = $request->gateway_response;
				$gateway_response = json_decode($gateway_response);
			}
			$payment_save_status = true;
		} elseif ($payment_gateway_type == 'CARD_OFFLINE') {

			$method = 'card';
			$gateway_response = '';
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


		if (!$t_order_id) {
			$t_order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
			$t_order_id = $t_order_id + 1;
		}

		$vSetting = new VendorSettingController;
		$voucherSetting = $vSetting->getVoucherSetting(['v_id' => $v_id, 'trans_from' => $trans_from]);
		$voucherUsedType = null;
		if (isset($voucherSetting->status) &&  $voucherSetting->status == 1) {

			$vouchers = DB::table('cr_dr_settlement_log')->select('id', 'voucher_id', 'applied_amount')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('order_id', $t_order_id)->get();

			$voucherUsedType = $voucherSetting->used_type;
			foreach ($vouchers as $voucher) {
				$totalVoucher = 0;
				$vou = DB::table('cr_dr_voucher')->select('amount', 'voucher_no', 'expired_at')->where('id', $voucher->voucher_id)->first();
				$totalVoucher = $vou->amount;
				$previous_applied = DB::table('cr_dr_settlement_log')->select('applied_amount')->where('voucher_id', $voucher->voucher_id)->get();
				$totalAppliedAmount = $previous_applied->sum('applied_amount');

				if ($voucherUsedType == 'PARTIAL') {
					if ($vou->amount ==  $totalAppliedAmount) {
						DB::table('cr_dr_voucher')->where('id', $voucher->voucher_id)->update(['status' => 'used']);
					} else if ($totalAppliedAmount > $vou->amount) {
						DB::table('cr_dr_voucher')->where('id', $voucher->voucher_id)->update(['status' => 'used']);
					} else {
						DB::table('cr_dr_voucher')->where('id', $voucher->voucher_id)->update(['status' => 'partial']);

						//This is added for sending sms for partial voucher
						$cust = User::select('mobile')->where('c_id', $c_id)->first();
						$smsC = new SmsController;
						$expired_at = explode(' ', $vou->expired_at);
						$smsParams = ['mobile' => $cust->mobile, 'voucher_amount' => ($vou->amount - $totalAppliedAmount), 'voucher_no' => $vou->voucher_no, 'expiry_date' => $expired_at[0], 'v_id' => $v_id, 'store_id' => $store_id];
						$smsResponse = $smsC->send_voucher($smsParams);
					}
				} else {

					DB::table('cr_dr_voucher')->where('id', $voucher->voucher_id)->update(['status' => 'used']);
				}

				DB::table('cr_dr_settlement_log')->where('id', $voucher->id)->update(['status' => 'APPLIED']);
			}
		} else {

			$vouchers = DB::table('cr_dr_settlement_log')->select('voucher_id')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('order_id', $t_order_id)->get();

			foreach ($vouchers as $voucher) {
				DB::table('cr_dr_voucher')->where('id', $voucher->voucher_id)->update(['status' => 'used']);
			}
		}

		if ($status == 'success') {

			// ----- Generate Order ID & Update Order status on orders and orders details -----

			$payments = Payment::where('v_id', $orders->v_id)->where('store_id', $orders->store_id)->where('order_id', $orders->order_id)->get();

			if ($orders->total == $payments->sum('amount')) {

				$orders->update(['status' => 'success', 'verify_status' => '1', 'verify_status_guard' => '1']);
			}

			OrderDetails::where('t_order_id', $orders->od_id)->update(['status' => 'success']);

			// ----- Generate Invoice -----

			$zwing_invoice_id  = invoice_id_generate($store_id, $user_id, $trans_from, $udidtoken);
			//dd($zwing_invoice_id);
			$custom_invoice_id = custom_invoice_id_generate(['store_id' => $store_id, 'user_id' => $user_id, 'trans_from' => $trans_from]);
			// dd($zwing_invoice_id);

			if ($payment_type == 'full') {

				// Apportion if loyalty is there on MOP

				$paymentLists = Payment::where('order_id', $orders->order_id)->where('payment_gateway_type', 'LOYALTY')->where('status', 'success')->get();
				// $order = Order::where('order_id', 'O2001002J4900004')->first();
				$orderData = [];
				$totalTax = $totalAmount = 0;

				if (!$paymentLists->isEmpty()) {

					$totalLPDiscount = $paymentLists->sum('amount');
					$discountPer = getPercentageOfDiscount($orders->total, $totalLPDiscount);

					// Apply discount to each item

					foreach ($orders->details as $item) {

						$total = $tax = $lpdiscount = 0;
						$lpdiscount = round($item->total * $discountPer / 100, 2);
						$total = $item->total - $lpdiscount;
						$tax_code = json_decode($item->section_target_offers);
						$tax_code = json_decode(urldecode($tax_code->item_det));
						// dd(json_decode($tax_code)->INVHSNSACMAIN_CODE);
						$orderData[] = ['id' => $item->id, 'barcode' => $item->item_id, 'total' => $total, 'lpdiscount' => $lpdiscount, 'qty' => $item->qty, 'tax_code' => $tax_code->INVHSNSACMAIN_CODE, 'store_id' => $item->store_id];
					}

					// Calculate all item LP Discount & match to Bill level LP Discount

					$orderData = collect($orderData);
					$totalItemLPdiscount = $orderData->sum('lpdiscount');
					if ($totalLPDiscount == $totalItemLPdiscount) {
						// echo 'Cool : -'.$totalItemLPdiscount;
					} elseif ($totalItemLPdiscount > $totalLPDiscount) {
						$highestLPDAmt = $orderData->sortByDesc('lpdiscount')->first();
						$diffAmt = round($totalItemLPdiscount - $totalLPDiscount, 2);
						$orderData = $orderData->map(function ($item, $key) use ($highestLPDAmt, $diffAmt) {
							if ($item['id'] == $highestLPDAmt['id']) {
								$item['lpdiscount'] = $item['lpdiscount'] - $diffAmt;
								$item['total'] = $item['total'] + $diffAmt;
							}
							return $item;
						});
						// echo 'Grater Then : -'.$orderData->sum('lpdiscount');
					} elseif ($totalItemLPdiscount < $totalLPDiscount) {
						$lowestLPDAmt = $orderData->sortBy('lpdiscount')->first();
						$diffAmt = round($totalLPDiscount - $totalItemLPdiscount, 2);
						$orderData = $orderData->map(function ($item, $key) use ($lowestLPDAmt, $diffAmt) {
							if ($item['id'] == $lowestLPDAmt['id']) {
								$item['lpdiscount'] = $item['lpdiscount'] + $diffAmt;
								$item['total'] = $item['total'] - $diffAmt;
							}
							return $item;
						});
						// echo 'Less Then : -'.$orderData->sum('lpdiscount');
					}

					// Re-calculate Tax of all items

					foreach ($orderData as $taxData) {

						// $CartController = new CartController();
						$itemTaxData = $this->taxCal([
							'barcode' 	=> $taxData['barcode'],
							'qty'		=> $taxData['qty'],
							's_price'	=> $taxData['total'],
							'tax_code'	=> $taxData['tax_code'],
							'store_id'	=> $taxData['store_id']
						]);

						$totalAmount += $taxData['total'];
						$totalTax += $itemTaxData['tax'];

						OrderDetails::find($taxData['id'])->update([
							'lpdiscount'	=> $taxData['lpdiscount'],
							'tax'			=> format_number($itemTaxData['tax'], 2),
							'total'			=> $taxData['total'],
							'tdata'			=> json_encode($itemTaxData)
						]);
					}

					// Update Order Data

					Order::where('order_id', $orders->order_id)->update([
						'lpdiscount'	=> $totalLPDiscount,
						'tax'			=> format_number($totalTax, 2),
						'total'			=> $totalAmount
					]);
				}

				$invoice = new Invoice;
				$invoice->invoice_id 		= $zwing_invoice_id;
				$invoice->custom_order_id 	= $custom_invoice_id;
				$invoice->ref_order_id 		= $orders->order_id;
				$invoice->transaction_type 	= $orders->transaction_type;
				$invoice->v_id 				= $v_id;
				$invoice->store_id 			= $store_id;
				$invoice->user_id 			= $user_id;
				$invoice->subtotal 			= $orders->subtotal;
				$invoice->discount 			= $orders->discount;
				$invoice->lpdiscount 		= $totalLPDiscount;
				if (isset($orders->manual_discount)) {
					$invoice->manual_discount	= $orders->manual_discount;
				}
				if (!empty($totalLPDiscount)) {
					$invoice->tax 				= $totalTax;
					$invoice->total 			= $totalAmount;
				} else {
					$invoice->tax 				= $orders->tax;
					$invoice->total 			= $orders->total;
				}
				$invoice->trans_from 		= $trans_from;
				$invoice->vu_id 			= $vu_id;
				$invoice->date 				= date('Y-m-d');
				$invoice->time 				= date('H:i:s');
				$invoice->month 			= date('m');
				$invoice->year 				= date('Y');
				$invoice->save();


				// $paymentCount = $orders->payments->count();

				// // dd($paymentCount);

				// if ($paymentCount == 1) {
				// 	$payment->update([ 'invoice_id' => $zwing_invoice_id ]);
				// } else {
				Payment::where('order_id', $order_id)->update(['invoice_id' => $zwing_invoice_id]);
				$paymenDetails = Payment::where('order_id', $order_id)->first();
				// $paymenDetails->invoice_id = $zwing_invoice_id;
				// $paymenDetails->save();
				// }

				// ------ Copy Order Details & Order Item Details to Invoice Details & Invoice Item Details ------

				$pinvoice_id = $invoice->id;

				$order_data = OrderDetails::where('t_order_id', $orders->od_id)->get()->toArray();

				foreach ($order_data as $value) {

					if ($invoice->id) {

						$value['t_order_id']  = $invoice->id;
						$save_invoice_details = $value;
						$invoice_details_data = InvoiceDetails::create($save_invoice_details);
						$order_details_data  = OrderItemDetails::where('porder_id', $value['id'])->get()->toArray();

						foreach ($order_details_data as $indvalue) {
							$save_invoice_item_details = array_add($indvalue, 'pinvoice_id', $invoice_details_data->id);
							InvoiceItemDetails::create($save_invoice_item_details);
						}

						/*Update Stock start*/
						$store_db_name = $this->store_db_name($value['store_id']);
						$barcode  =  $this->getBarcode($value['barcode'], $store_db_name);

						if ($barcode) {
							$barcode  = $barcode;
						} else {
							$barcode  = $value['barcode'];
						}
						//echo $barcode;die;
						$where    = array('v_id' => $value['v_id'], 'barcode' => $barcode);
						$Item     = VendorSkuDetails::where($where)->first();
						//dd($Item);

						if ($Item) {


							$whereStockCurrentStatus = array('variant_sku' => $Item->sku, 'item_id' => $Item->item_id, 'store_id' => $value['store_id'], 'v_id' => $value['v_id']);
							$stockCurrentStatus = StockCurrentStatus::where($whereStockCurrentStatus)->orderBy('id', 'desc')->first();
							if ($stockCurrentStatus) {

								$this->updateStockCurrentStatus($Item->sku, $Item->item_id, $value['qty'], $value['v_id'], $value['store_id']);

								/*$stockCurrentStatus->out_qty = $stockCurrentStatus->out_qty+$value['qty'];
								$stockCurrentStatus->save();*/

								$stockpointwhere  = array('v_id' => $value['v_id'], 'store_id' => $value['store_id'], 'name' => 'SALE');
								$stockpoint = StockPoints::where($stockpointwhere)->first();
								if (!$stockpoint) {
									$stockpoint = new StockPoints;
									$stockpoint->v_id 		= $value['v_id'];
									$stockpoint->store_id 	= $value['store_id'];
									$stockpoint->name 		= 'SALE';
									$stockpoint->code 		= 'SL001';
									$stockpoint->save();
								}

								$whereRefPoint   = array('item_id' => $Item->item_id, 'v_id' => $value['v_id'], 'store_id' => $value['store_id']);


								$ref_stock_point = StockLogs::select('stock_point_id')->where($whereRefPoint)->orderBy('id', 'desc')->first();

								$stockdata 	= array(
									'variant_sku' => $Item->sku,
									'item_id'    => $Item->item_id,
									'store_id'	 => $value['store_id'],
									'stock_type' => 'OUT',
									'stock_point_id' => $stockpoint->id,
									'qty'		 => $value['qty'],
									'ref_stock_point_id' => $ref_stock_point->stock_point_id,
									'v_id' 		=>  $value['v_id']
								);
								StockLogs::create($stockdata);
								$stocktransdata 	= array(
									'variant_sku' => $Item->sku,
									'item_id'    => $Item->item_id,
									'store_id'	 => $value['store_id'],
									'stock_type' => 'OUT',
									'stock_point_id' => $stockpoint->id,
									'qty'		 => $value['qty'],
									'v_id' 		=>  $value['v_id'],
									'order_id'  =>  $orders->od_id,
									'invoice_no' =>  $invoice->invoice_id
								);
								StockTransactions::create($stocktransdata);
								/*$stockCurrentdata = array('variant_sku'=> $Item->sku,
															'item_id'    => $Item->item_id,
															'store_id'	 => $value['store_id'],
															'stock_type' => 'OUT',
															'stock_point_id' => $stockpoint->id,
															'qty'		 => $value['qty'],
															'v_id' 		=>  $value['v_id'],
															'order_id'  =>  $orders->od_id,
															'invoice_no'=>  $invoice->invoice_id);*/
							}
						}

						/*Update Stock end*/
					}
				}
			} elseif ($payment_type == 'partial') {
				// For the partial 
			}

			// Delete Data From Cart & Cart Details Table

			$cart_id_list = Cart::where('order_id', $orders->o_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $user_id)->get(['cart_id']);

			CartDetails::whereIn('cart_id', $cart_id_list)->delete();

			Cart::whereIn('cart_id', $cart_id_list)->delete();

			$payment_method = (isset($payment->method)) ? $payment->method : '';

			$user = Auth::user();
			// Mail::to($user->email)->send(new OrderCreated($user,$ord,$carts,$payment_method,  $complete_path));

			$orderC = new OrderController;
			$order_arr = $orderC->getOrderResponse(['order' => $orders, 'v_id' => $v_id, 'trans_from' => $trans_from]);

			if (empty($order_arr['total_payable'])) {

				// Loyality

				if ($request->has('loyalty')) {
					$checkLoyaltyBillSubmit = LoyaltyBill::where('vendor_id', $v_id)->where('store_id', $store_id)->where('user_id', $user_id)->where('invoice_no', $zwing_invoice_id)->where('type', 'easeMyRetail')->where('is_submitted', '1')->first();
					if (empty($checkLoyaltyBillSubmit)) {
						$userInformation = User::find($c_id);
						// $invoice_id = Invoice::where('ref_order_id', $order_id)->first()->invoice_id;
						$loyaltyPrams = ['type' => $request->loyaltyType, 'event' => 'billPush', 'mobile' => $userInformation->mobile, 'vu_id' => $request->vu_id, 'settings' => $request->loyaltySettings, 'zw_event' => 'checkBill', 'v_id' => $v_id, 'store_id' => $store_id, 'invoice_id' => $zwing_invoice_id, 'user_id' => $user_id];
						Event::fire(new Loyalty($loyaltyPrams));
					}
					// dd($loyaltyPrams);
				}
			}

			$print_url  =  env('API_URL') . '/order-receipt/' . $c_id . '/' . $v_id . '/' . $store_id . '/' . $zwing_invoice_id;

			return response()->json(['status' => 'payment_save', 'redirect_to_qr' => true, 'message' => 'Save Payment', 'data' => $paymenDetails, 'order_summary' => $order_arr, 'print_url' => $print_url], 200);

			// }

		} else if ($status == 'failed' || $status == 'error') {

			// ----- Generate Order ID & Update Order status on orders and orders details -----

			// $new_order_id = order_id_generate($store_id, $user_id, $trans_from);
			// $custom_order_id = custom_order_id_generate(['store_id' => $store_id, 'user_id' => $user_id, 'trans_from' => $trans_from]);

			// $orders->update([ 'order_id' => $new_order_id, 'custom_order_id' => $custom_order_id, 'status' => $status ]);
			if ($trans_from == 'CLOUD_TAB' || $trans_from == 'CLOUD_TAB_ANDROID') { } else {
				$orders->update(['status' => $status]);
				OrderDetails::where('t_order_id', $orders->od_id)->update(['status' => $status]);
			}
		}
	}

	// public function 

	private function getBarcode($code, $store_db_name)
	{
		if ($code) {
			//using icode
			$barcode = DB::table($store_db_name . '.invitem')->select('BARCODE')->where('ICODE', $code)->first();
			if ($barcode->BARCODE) {
				return $barcode->BARCODE;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	private function updateStockCurrentStatus($variant_sku, $item_id, $quantity, $v_id, $store_id)
	{

		$date = date('Y-m-d');
		$todayStatus = StockCurrentStatus::select('id', 'out_qty')
			->where('item_id', $item_id)
			->where('variant_sku', $variant_sku)
			->where('store_id', $store_id)
			->where('v_id', $v_id)
			->where('for_date', $date)
			->first();

		if ($todayStatus) {
			$todayStatus->out_qty += $quantity;
			$todayStatus->save();
			//  print($todayStatus); die;
		} else {
			$stockPastStatus = StockCurrentStatus::select('opening_qty', 'out_qty', 'int_qty')
				->where('item_id', $item_id)
				->where('variant_sku', $variant_sku)
				->where('store_id', $store_id)
				->where('v_id', $v_id)
				->orderBy('for_date', 'DESC')
				->first();

			if ($stockPastStatus) {
				$openingStock = $stockPastStatus->opening_qty + $stockPastStatus->int_qty - $stockPastStatus->out_qty;
			} else {
				$openingStock = 0;
			}

			StockCurrentStatus::create([
				'item_id' => $item_id,
				'variant_sku' => $variant_sku,
				'store_id' => $store_id,
				'v_id' => $v_id,
				'for_date' => $date,
				'opening_qty' => $openingStock,
				'out_qty' => $quantity,
				'int_qty' => 0
			]);
		}
	} //End of updateStockCurrentStatusOnImport

	public function order_qr_code(Request $request)
	{
		$order_id = $request->order_id;
		$qrCode = new QrCode($order_id);
		header('Content-Type: image/png');
		echo $qrCode->writeString();
	}

	public function order_pre_verify_guide(Request $request)
	{
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

	public function order_details(Request $request)
	{
		// dd($request->all());
		$v_id 			= $request->v_id;
		$c_id 			= $request->c_id;
		$store_id 		= $request->store_id;
		$invoice_id 	= $request->order_id;
		$store_db_name 	= $this->store_db_name($store_id);
		$trans_from 	= $request->trans_from;

		$cart_qty_total = 0;
		// dd($invoice_id);

		$return_request = 0;
		if ($request->has('return_request')) {
			if ($request->return_request == 1) {
				$return_request = 1;
			}
		}

		$vu_id = 0;
		if ($request->has('vu_id')) {
			$vu_id = $request->vu_id;
			$user_id = $request->vu_id;
		} else if ($request->has('c_id')) {
			$c_id = $request->c_id;
		}
		$role = VendorRoleUserMapping::select('role_id')->where('user_id', $vu_id)->first();
		$role_id = $role->role_id;
		$item_qty = 0;
		$where  = array(
			'invoice_id'	=> $invoice_id,
			'v_id'      	=> $v_id,
			'store_id'  	=> $store_id
		);

		if ($request->has('return_request') && $request->return_request == 1) {
			$where['user_id'] = $c_id;
		} else {
			if ($vu_id > 0) {
				$where['vu_id'] = $vu_id;
			} else {
				$where['user_id'] = $c_id;
			}
		}


		$invoice = Invoice::where($where)->first();

		$c_id = $invoice->user_id;
		$user_api_token = $invoice->user->api_token;
		$customer_number = $invoice->user->mobile;
		$payment_via = $invoice->payment->method;

		$total_qty = $invoice->details->sum('qty');
		$data = [];

		//For Return operation only
		$return_items = [];
		if ($invoice->transaction_type == 'sales') {

			$return_order = DB::table('orders as o')

				->join('order_details as od', 'od.t_order_id', 'o.od_id')
				->where('o.ref_order_id', $invoice->ref_order_id)
				->where('o.transaction_type', 'return')
				->groupBy('od.item_id')
				->selectRaw('sum(od.qty) as sum, od.item_id')
				->get();

			$return_items = $return_order->pluck('sum', 'item_id')->all();
		}

		foreach ($invoice->details as $key => $value) {

			$applied_offer = [];
			$available_offer = [];
			$carr_bag_arr = [];
			$carry_bags = DB::table('carry_bags')->where('v_id', $v_id)->where('store_id', $store_id)->where('status', '1')->get();
			if ($carry_bags->isEmpty()) {
				$carry_bags = DB::table('carry_bags')->where('v_id', $v_id)->where('store_id', '0')->where('status', '1')->get();
			}
			if ($carry_bags) {
				$carr_bag_arr = $carry_bags->pluck('barcode')->all();
			}

			$carry_bag_flag = in_array($value->item_id, $carr_bag_arr);
			$product_details = json_decode($value->section_target_offers);
			//dd($product_details);
			$vendorS = new VendorSettingController;
			$sParam = ['v_id' => $v_id,'store_id'=>$store_id,'role_id'=>$role_id,'user_id'=>$user_id, 'trans_from' => $trans_from];
			$product_default_image = $vendorS->getProductDefaultImage($sParam);

			$return_product_qty = $value->qty;
			$retured_qty = 0;
			if (isset($return_items[$value->item_id])) {
				$return_product_qty = $value->qty - $return_items[$value->item_id];
				$retured_qty = $return_items[$value->item_id];
			}

			$product_data = array(
				'return_flag' => false,
				'return_qty' => $retured_qty,
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
				'r_price' => (string) ($value->subtotal),
				's_price' => (string) ($value->total),
				'discount' => $product_details->discount,
				'varient' => $product_details->varient,
				'images' => $product_default_image,
				'description' => $product_details->description,
				'deparment' => $product_details->deparment,
				'barcode' => $product_details->barcode,
				''
			);

			$data[] = [
				'cart_id' => $value->id,
				'product_data' => $product_data,
				'amount' => vformat_and_string($value->total),
				'qty' => (string) $value->qty,
				'return_product_qty' => (string) $return_product_qty,
				'tax_amount' => '',
				'delivery' => 'No',
				'item_flag' => 'NORMAL',
				'salesman_id' => $value->salesman_id
			];
			$item_qty = $value->qty;
		}

		// $paymentMethod = Payment::where('v_id', $o_id->v_id)->where('store_id', $o_id->store_id)->where('invoice_id', $o_id->invoice_id)->get()->pluck('method')->all();
		$payments = $invoice->payments;
		$paymentMethod = $payments->pluck('method')->all();
		$tempMethod = [];
		foreach ($paymentMethod as $key => $value) {
			if ($value == 'voucher_credit') {
				$tempMethod[] = 'Store Credit';
			} else {

				$tempMethod[] = ucfirst(strtolower(str_replace('_', ' ', $value)));
			}
		}
		$paymentMethod = $tempMethod;

		$return_reasons = [];
		if ($return_request) {
			$return_reasons = Reason::select('id', 'description')->where('type', 'RETURN')->where('v_id', $v_id)->get();
		}

		$bill_summary = [];

		$bill_summary[] = ['name' => 'sub_total', 'display_text' => 'Sub Total','display_name' => 'Sub Total', 'value' => (string) format_number($invoice->subtotal)];
		$bill_summary[] = ['name' => 'discount', 'display_text' => 'Discount','display_name' => 'Discount', 'value' => (string) format_number($invoice->total_discount)];
		if($invoice->manual_discount > 0){
			$bill_summary[] = [ 'name' => 'manual_discount' , 'display_text' => 'Manual Discount', 'display_name' => 'Manual Discount' , 'value' => format_number($invoice->manual_discount) ];
		}

		$bill_summary[] = ['name' => 'total', 'display_text' => 'Total','display_name' => 'Total', 'value' => (string) format_number($invoice->total)];
		$bill_summary[] = ['name' => 'tax_total', 'display_text' => 'Tax Total','display_name' => 'Tax Total', 'value' => (string) format_number($invoice->tax)];


		foreach ($payments as $key => $payment) {
			if ($payment->method == 'voucher_credit') {
				$bill_summary[] = ['name' => 'payment_' . $payment->method, 'display_text' => 'Store Credit','display_name' => 'Store Credit', 'value' => (string) format_number($payment->amount), 'mop_flag' => '1'];
			} else {
				$bill_summary[] = ['name' => 'payment_' . $payment->method, 'display_text' => ucfirst($payment->method),'display_name' => ucfirst($payment->method), 'value' => (string) format_number($payment->amount), 'mop_flag' => '1'];
			}
		}

		$customer = User::select('first_name', 'last_name')->where('c_id', $invoice->user_id)->first();
		$group = DB::table('customer_group_mappings')->where('c_id', $invoice->user_id)->first();
		$group_code = 'REGULAR';
		if ($group) {
			$group_code = DB::table('customer_groups')->where('id', $group->group_id)->first()->code;
		}
		// if($voucher_total > 0){

		// 	$bill_summary[] = [ 'name' => 'voucher' , 'display_text' => 'Voucher Total' , 'value' => (string)format_number($voucher_total) ];
		// }

		$print_url  =  env('API_URL') . '/order-receipt/' . $c_id . '/' . $v_id . '/' . $store_id . '/' . $invoice_id;

		return response()->json([
			'status' => 'order_details', 'message' => 'Order Details Details',
			'payment_method' => implode(',', $paymentMethod),
			'transaction_type'  =>  $invoice->transaction_type,
			'mobile' => $invoice->user->mobile,
			'data' => $data, 'return_req_process' => [], 'return_req_approved' => [], 'product_image_link' => product_image_link(), 'return_request_flag' => false, 'bags' => [], 'carry_bag_total' => '0.00', 'sub_total' => $invoice->subtotal, 'tax_total' => '0.00', 'tax_details' => '', 'discount' => $invoice->discount, 'date' => $invoice->date, 'time' => $invoice->time, 'order_id' => $invoice->invoice_id, 'total' => $invoice->total, 'cart_qty_total' => (string) $total_qty, 'saving' => vformat_and_string($invoice->subtotal - $invoice->total), 'store_address' => $invoice->store->address1, 'store_timings' => '', 'delivered' => 'No', 'address' => (object) [], 'c_id' => $c_id, 'user_api_token' => $user_api_token, 'customer' => $customer_number, 'customer_name' => $customer->first_name . ' ' . $customer->last_name,  'payment_via' => $payment_via, 'return_reasons' => $return_reasons, 'bill_summary' => $bill_summary, 'bill_remark' => $invoice->remark, 'customer_group_code' => $group_code, 'print_url' => $print_url
		], 200);

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
			$carry_bags = DB::table('carry_bags')->where('v_id', $v_id)->where('store_id', $store_id)->where('status', '1')->get();
			if ($carry_bags->isEmpty()) {
				//echo 'insdie this';exit;
				$carry_bags = DB::table('carry_bags')->where('v_id', $v_id)->where('store_id', '0')->where('status', '1')->get();
			}
			//dd($carry_bags);
			if ($carry_bags) {

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

		$tempMethod = [];
		foreach ($paymentMethod as $key => $value) {
			if ($value == 'voucher_credit') {
				$tempMethod[] = 'Store Credit';
			} else {
				$tempMethod[] = ucfirst(strtolower($value));
			}
		}
		$paymentMethod = $tempMethod;

		return response()->json([
			'status' => 'order_details', 'message' => 'Order Details Details',
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
			'address' => $address
		], 200);
	}

	public function order_receipt($c_id, $v_id, $store_id, $order_id)
	{

		// $organisation = Organisation::find($v_id);
		//dynamicConnection($organisation->db_name);
		$request = new \Illuminate\Http\Request();
		$request->merge([
			'v_id' => $v_id,
			'c_id' => $c_id,
			'store_id' => $store_id,
			'order_id' => $order_id
		]);
		$htmlData = $this->get_print_receipt($request);
		$html = $htmlData->getContent();
		$html_obj_data = json_decode($html);
		if ($html_obj_data->status == 'success') {
			return $this->get_html_structure($html_obj_data->print_data);
		}
	}

	public function get_html_structure($str)
	{
		$string = string($str)->replace('<center>', '<tbodyclass="center">');
		$string = string($string)->replace('<left>', '<tbodyclass="left">');
		$string = string($string)->replace('<right>', '<tbodyclass="right">');
		$string = string($string)->replace('</center>', '</tbody>');
		$string = string($string)->replace('</left>', '</tbody>');
		$string = string($string)->replace('</right>', '</tbody>');
		$string = string($string)->replace('normal>', 'span>');
		$string = string($string)->replace('bold>', 'b>');
		$string = string($string)->replace('<size', '<tr><td');
		$string = string($string)->replace('size>', 'td></tr>');
		$string = string($string)->replace('text', 'pre');
		$string = string($string)->replace('td=30', 'tdstyle="font-size:90px"');
		$string = string($string)->replace('td=24', 'tdstyle="font-size:16px"');
		$string = string($string)->replace('td=22', 'tdstyle="font-size:15px"');
		$string = string($string)->replace('td=20', 'tdstyle="font-size:14px"');
		$string = string($string)->replace('\n', '&nbsp;');
		// $DOM = new \DOMDocument;
		// $DOM->loadHtml($string);

		$string = urlencode($string);
		// $string = string($string)->replace('+','&nbsp;&nbsp;');
		$string = string($string)->replace('tds', 'td s');
		$string = string($string)->replace('tbodyc', 'tbody c');

		$renderPrintPreview = '<!DOCTYPE html><html><head>
		 						<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                                <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
                            	<title>Cool</title>
                            	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
	                            <style type="text/css">
	                            * {  font-family: Lato; }
								div { margin: 30px 0; border: 1px solid #f5f5f5; }
								table {  width: 350px;  }
								.center { text-align: center;  }
								.left { text-align: left; }
								.left pre { padding:0 30px !important; }
								.right { text-align: right;  }
								.right pre { padding:0 30px !important; }
								td { padding: 0 5px; }
								tbody { display: table !important; width: inherit; word-wrap: break-word; }
								pre {
								    white-space: pre-wrap;       /* Since CSS 2.1 */
								    white-space: -moz-pre-wrap;  /* Mozilla, since 1999 */
								    white-space: -pre-wrap;      /* Opera 4-6 */
								    white-space: -o-pre-wrap;    /* Opera 7 */
								    word-wrap: break-word;       /* Internet Explorer 5.5+ */
								    overflow: hidden;
								    background-color: #fff;
								    padding: 0;
								    border: none;
								    font-size: 12.5px !important;
								}
	                            </style>
                        </head>
                            
                        <body>
                            <center>
                            
                                <div style="width: 350px;">
                                <table>
                            '
			. urldecode($string) .
			'</table>
                            </div>
                            
                                </center>
                        </body>
                            </html>';

		return $renderPrintPreview;
	}

	public function rt_log(Request $request)
	{
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

				if ($items_index == 37) { } else {
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

	public function get_carry_bags(Request $request)
	{
		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$c_id = $request->c_id;
		$order_id = Order::where('user_id', Auth::user()->c_id)->where('status', 'success')->count();
		$order_id = $order_id + 1;
		$store_db_name 	= $this->store_db_name($store_id);

		//$carry_bags = DB::table('vendor_carry_bags')->select('BAG_ID','Name','Price')->where('V_ID', $v_id)->where('Store_ID', $store_id)->get();

		//$carr_bag_arr = ['VR132797', 'VR132799', 'VR132807'];
		$carr_bag_arr = [];
		$carry_bags = DB::table('carry_bags')->where('v_id', $v_id)->where('store_id', $store_id)->where('status', 1)->where('deleted_status', 0)->get();
		if ($carry_bags->isEmpty()) {
			//echo 'insdie this';exit;
			$carry_bags = DB::table('carry_bags')->where('v_id', $v_id)->where('store_id', '0')->where('status', 1)->where('deleted_status', 0)->get();
		}
		if ($carry_bags) {

			$carr_bag_arr = $carry_bags->pluck('barcode')->all();
		}

		$carry_bags = DB::table($store_db_name . '.invitem')->select('ICODE as BAG_ID', 'CNAME1 as Name', 'MRP as Price')->whereIn('ICODE', $carr_bag_arr)->get();
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

	public function save_carry_bags(Request $request)
	{
		//echo 'inside this';exit;
		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$c_id = $request->c_id;
		$store_db_name 	= $this->store_db_name($store_id);
		//$order_id = $request->order_id;
		$bags = $request->bags;
		//dd($bags);
		$bags = json_decode(urldecode($bags), true);
		$stores = DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();
		$order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
		$order_id = $order_id + 1;
		$carts = DB::table('cart')->where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

		foreach ($bags as $key => $value) {

			$exists = $carts->where('barcode', $value[0])->first();
			$price_master = DB::table($store_db_name . '.invitem')->select('ICODE as BAG_ID', 'CNAME1 as Name', 'MRP as Price')->where('ICODE', $value[0])->first();
			//dd($price_master);

			(array) $push_data = ['v_id' => $v_id, 'trans_from' => $request->trans_from, 'barcode' => $price_master->BAG_ID, 'qty' => $value[1], 'scode' => $stores->mapping_store_id];

			//dd($push_data);




			$single_cart_data['v_id'] = $v_id;
			$single_cart_data['is_cart'] = 0;
			$single_cart_data['is_update'] = 0;
			$single_cart_data['store_id'] = $store_id;
			$single_cart_data['c_id'] = $c_id;
			$single_cart_data['trans_from'] = $request->trans_from;
			$single_cart_data['barcode'] = $price_master->BAG_ID;
			$single_cart_data['qty'] = $value[1];
			$single_cart_data['vu_id'] = $request->vu_id;
			$single_cart_data['mapping_store_id'] = $stores->mapping_store_id;
			$item = DB::table($stores->store_db_name . '.invitem')->select('GRPCODE', 'INVARTICLE_CODE', 'BARCODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE', 'LISTED_MRP', 'DESC1', 'DESC2', 'DESC3', 'DESC4', 'DESC5', 'DESC6')->where('ICODE', $price_master->BAG_ID)->first();
			$item = removeSpecialChar($item);
			$single_cart_data['item'] = $item;
			$single_cart_data['store_db_name'] = $stores->store_db_name;
			$carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $request->order_id)->where('user_id', $request->c_id)->where('status', 'process')->get();
			$single_cart_data['carts'] = $carts;
			//print_r($single_cart_data['carts']);die;
			$promoC = new PromotionController;
			$offer_data = $promoC->index($single_cart_data);
			$data = $offer_data;
			/*echo $data['r_price'];
			dd($data);*/
			// dd($exists);
			if ($exists) {
				if ($value[1] < 1) {
					$request->request->add(['cart_id' => $exists->cart_id]);
					$this->remove_product($request);
				} else {
					$request->request->add(['barcode' => $value[0], 'qty' =>   $value[1], 'unit_mrp' =>  $data['unit_mrp'], 'unit_rsp' => $data['unit_rsp'], 'r_price' =>  $data['r_price'], 's_price' => $data['s_price'], 'discount' => $data['discount'], 'pdata' => $data['pdata'], 'data' => $data, 'ogbarcode' => $value[0]]);
					$this->product_qty_update($request);
				}

				$status = '1';
			} else {
				if ($value[1] > 0) {
					// dd($data);

					$request->request->add(['barcode' => $value[0], 'scan' => false, 'qty' => $value[1], 'unit_mrp' => $data['unit_mrp'], 'unit_rsp' =>  $data['unit_rsp'], 'r_price' => $data['r_price'], 's_price' => $data['s_price'], 'discount' => $data['discount'], 'pdata' => $data['pdata'], 'data' => $data, 'ogbarcode' => $value[0]]);
					//$product = new ProductController;
					$this->add_to_cart($request);
					//$product->product_details($request);
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

	public function deliveryStatus(Request $request)
	{
		$c_id = $request->c_id;
		// $v_id = $request->v_id;
		// $store_id = $request->store_id;
		$cart_id = $request->cart_id;
		$status = $request->status;

		$cart = Cart::find($cart_id)->update(['delivery' => $status]);

		return response()->json(['status' => 'delivery_status_update'], 200);
	}

	public function calculatePromotions(Request $request){

        // $params = $request->all();
        
        // $this->process_each_item_in_cart($params);

        return $this->cart_details($request);
    }

	public function process_each_item_in_cart($param)
	{
		// dd($param);
		$promoC = new PromotionController;
		$offer_data = $promoC->indexByCart($param);
		// dd($offer_data);
		$data = (object) ['v_id' => $param['v_id'], 'store_id' => $param['store_id'], 'c_id' => $param['c_id'], 'barcode' => $offer_data['barcode'], 'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'data' => $offer_data, 'trans_from' => $param['trans_from'], 'vu_id' => $param['vu_id']];
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
		$mop_list = [];
		$store_db_name = $this->store_db_name($store_id);
		$store = Store::find($store_id);

		$site_details = DB::table($store_db_name . '.admsite')->where('CODE', $store->mapping_store_id)->first();
		$order_details = Invoice::where('invoice_id', $order_id)->first();

		$customer = User::find($order_details->user_id);

		$cart_qty = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('qty');

		$total_amount = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
		// dd($total_amount);

		$cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->get();
		$count = 1;
		$gst_tax = 0;
		$gst_listing = [];

		foreach ($cart_product as $key => $value) {

			$tdata = json_decode($value->tdata);

			$gst_tax += $value->tax;
			array_push($product_data, [
				'row' => 1,
				'sr_no' => $count++,
				'name' => $value->item_name,
				'total' => $value->total,
				'hsn' => $tdata->hsn,
			]);

			if ($order_details->transaction_type == 'sales') {
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
			} else if ($order_details->transaction_type == 'return') {
				array_push($product_data, [
					'row' => 2,
					'rate' => round($value->unit_mrp),
					'qty' => -$value->qty,
					'discount' => $value->discount,
					'rsp' => $value->unit_mrp,
					'tax_amt' => format_number(-$value->tax),
					'tax_per' => $tdata->cgst + $tdata->sgst,
					'total' => format_number(-$value->total),
				]);
			}

			$gst_list[] = [
				'name' => $tdata->tax_name,
				'wihout_tax_price' => $tdata->taxable,
				'tax_amount' => $tdata->tax,
			];
		}

		// dd(array_unique($gst_list));

		$gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
		// dd($gst_list);
		$total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = 0;
		foreach ($gst_listing as $key => $value) {
			$buffer_total_gst = $buffer_taxable_amount = $buffer_total_taxable = $buffer_total_csgt = $buffer_total_sgst = 0;
			foreach ($gst_list as $val) {
				if ($val['name'] == $value) {
					if ($order_details->transaction_type == 'sales') {
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
					} elseif ($order_details->transaction_type == 'return') {
						$buffer_total_gst += $val['tax_amount'];
						$buffer_taxable_amount += str_replace(",", '', $val['wihout_tax_price']);
						$total_gst += $val['tax_amount'];
						$taxable_amount += str_replace(",", '', $val['wihout_tax_price']);
						$final_gst[$value] = (object) [
							'name' => $value,
							'taxable' => format_number(-$buffer_taxable_amount),
							'cgst' => format_number(-$buffer_total_gst / 2),
							'sgst' => format_number(-$buffer_total_gst / 2),
							'cess' => '0.00',
						];
						// $total_taxable += $taxable_amount;
						$total_csgt = -$total_gst / 2;
						$total_sgst = -$total_gst / 2;
					}
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
			$roundoffamt = -$roundoffamt;
		} else if ($roundoff[1] <= 49) {
			$roundoffamt = $total_amount - $order_details->total;
			$roundoffamt = -$roundoffamt;
		}
		// dd($roundoffamt);

		//Voucher Conditions started Here
		$store_credit = '';
		$voucher_no = '';
		$rounded = 0;
		$voucher_total = 0;
		$voucher_applied_list = [];
		$lapse_voucher_amount = 0;
		$bill_voucher_amount = 0;
		$cash_collected = 0;
		$cash_return = 0;
		if ($order_details->transaction_type == 'sales') {
			$invoice_title = '*** Invoice ***';
			$payments = Payment::where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('invoice_id', $order_id)->get();
			// dd($payments);
			if ($payments) {

				foreach ($payments as $payment) {
					if ($payment->method == 'cash') {
						$mop_list[] = ['mode' => $payment->method, 'amount' => $payment->cash_collected];
					} else {
						$mop_list[] = ['mode' => $payment->method, 'amount' => $payment->amount];
					}

					$cash_collected += (float) $payment->cash_collected;
					$cash_return += (float) $payment->cash_return;
					if ($payment->method == 'vmart_credit') {
						$vouchers = DB::table('cr_dr_settlement_log as va')
							->join('voucher as v', 'v.id', 'va.voucher_id')
							->select('v.voucher_no', 'v.amount', 'va.applied_amount')
							->where('va.v_id', $v_id)->where('va.store_id', $store_id)
							->where('va.user_id', $c_id)->where('va.order_id', $order_details->o_id)->get();
						$voucher_total = 0;
						foreach ($vouchers as $voucher) {
							$voucher_total += $voucher->applied_amount;
							$voucher_applied_list[] = ['voucher_code' => $voucher->voucher_no, 'voucher_amount' => format_number($voucher->applied_amount)];
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
		} elseif ($order_details->transaction_type == 'return') {
			$invoice_title = '** Credit Note **';
			$voucher = DB::table('cr_dr_voucher')->where('ref_id', $order_details->ref_order_id)->where('user_id', $order_details->user_id)->first();
			if ($voucher) {

				$store_credit = format_number($rounded);
				$voucher_no = $voucher->voucher_no;
			}
		}

		if ($cash_collected > 0.00) { } else {
			$cash_collected = $total_amount;
			$cash_return = 0.00;
		}
		$bilLogo = '';
		$bill_logo_id = 5;
		$vendorImage = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status', 1)->first();
		if ($vendorImage) {
			$bilLogo = env('ADMIN_URL') . $vendorImage->path;
		}



		if ($order_details->transaction_type == 'sales') {
			$cart_qty = $cart_qty;
			$total_amount = $total_amount;
			$due = $total_amount;
			if (empty($order_details->total) || $order_details->total == 0.00 || $order_details->total == '0.00') {
				$in_words = numberTowords(round($order_details->total));
			} else {
				$in_words = numberTowords(round($order_details->total)) . ' only';
			}
			$cash_collected = $cash_collected;
			$customer_paid = $cash_collected;
			$balance_refund = $cash_return;
			$total_sale = $total_amount;
			$saving_on_the_bill = $order_details->discount;
			if (isset($order_details->manual_discount)) {
				$saving_on_the_bill += $order_details->manual_discount;
			}
			$net_sale = $order_details->total;
			$net_payable = $order_details->total;
			$taxable_amount = $taxable_amount;
		} elseif ($order_details->transaction_type == 'return') {
			$cart_qty = -$cart_qty;
			$total_amount = format_number(-$total_amount);
			$due = '0.00';
			$in_words = numberTowords(round($due)) . ' only';
			$cash_collected = format_number(-$cash_collected);
			$customer_paid = '0.00';
			$balance_refund = '0.00';
			$total_sale = '0.00';
			$saving_on_the_bill = '0.00';
			$net_sale = '0.00';
			$net_payable = '0.00';
			$taxable_amount = format_number(-$taxable_amount);
		}

		// $mop_list[] = [ 'mode' => 'Credit Card', 'amount' => '1000' ];


		if ($order_details->tax > 0) { } else {
			$detatch_gst = [];
		}

		/*$data = [
			'header' => $site_details->NAME,
			'address' => $site_details->ADDRESS.','.$site_details->CTNAME.','.$site_details->PIN,
			'contact' => $store->contact_number,
			'email' => $store->email,
			'title'	=> $invoice_title,
			'gstin' => $store->gst,
			'cin' => 'L51909DL2002PLC163727',
			'gst_doc_no' => $order_details->custom_order_id,
			'memo_no' => $order_details->invoice_id,
			'time' => date('h:i A', strtotime($order_details->created_at)),
			'date' => date('d-M-Y', strtotime($order_details->created_at)),
			'cashier' => $order_details->vuser->first_name . ' ' . $order_details->vuser->last_name,
			'customer_name' => $customer->first_name,
			'mobile' => (string) $order_details->user->mobile,
			'product_data' => $product_data,
			'total_qty' => $cart_qty,
			'total_amount' => $total_amount,
			'voucher_total ' => format_number( $voucher_total ),
			'voucher_applied_list ' => $voucher_applied_list,
			'lapse_voucher_amount ' => $lapse_voucher_amount,
			'bill_voucher_amount ' => $bill_voucher_amount,
			'gst' => $this->format_and_string('0.00'),
			'round_off' => $this->format_and_string($roundoffamt),
			'due' => $due,
			'in_words' => $in_words,
			'mop_list' => $mop_list,
			'payment_type' => ucfirst($order_details->payment->method),
			'payment_type_amount' => format_number($cash_collected),
			'customer_paid' => format_number($customer_paid),
			'balance_refund' => format_number($balance_refund),
			'total_sale' => $total_sale,
			'total_return' => '0.00',
			'saving_on_the_bill' => $saving_on_the_bill,
			'net_sale' => $net_sale,
			'round_off_2' => $this->format_and_string($roundoffamt),
			'net_payable' => $net_payable,
			't_and_s_1' => '1. All Items inclusive of GST \nExcept Discounted Item.',
			't_and_s_2' => '2. Extra GST Will be Charged on\n Discounted Item.',
			't_and_s_3' => '3. No exchange on discounted and\n offer items.',
			't_and_s_4' => '4. No Refund.',
			't_and_s_5' => '5. We recommended dry clean for\n all fancy items.',
			't_and_s_6' => '6. No guarantee for colors and all hand work item.',
			'total_savings' => $order_details->discount,
			'round_off_3' => $this->format_and_string($roundoffamt),
			'gst_list' => $detatch_gst,
			'total_gst' => ['taxable' => $this->format_and_string($taxable_amount), 'cgst' => $this->format_and_string($total_csgt), 'sgst' => $this->format_and_string($total_sgst), 'cess' => '0.00'],
			'gate_pass_no' => '',
			'bill_logo' => $bilLogo,
		];

		return response()->json(['status' => 'success', 'data' => $data], 200);*/


		//$terms_conditions =  array('1. All Items inclusive of GST. \nExcept Discounted Item.','2. Extra GST Will be Charged on\n Discounted Item.','3. No exchange on discounted and\n offer items.','4. No Refund.','5. We recommended dry clean for\n all fancy items.','6. No guarantee for colors and all hand work item.');
		$terms_conditions = [];

		$manufacturer_name = 'basewin';
		if ($request->has('manufacturer_name')) {
			$manufacturer_name = $request->manufacturer_name;
		}

		$manufacturer_name =  explode('|', $manufacturer_name);

		$printParams = [];
		if (isset($manufacturer_name[1])) {
			$printParams['model_no'] = $manufacturer_name[1];
		}


		$printInvioce = new PrintInvoice($manufacturer_name[0], $printParams);
		// Start center
		//$printInvioce->addLineCenter($site_details->NAME, 24, true);

		$printInvioce->addLineCenter($store->name, 24, true);
		//$printInvioce->addLine('A unit of\nV mart Retail Limited', 22);
		$printInvioce->addLine($site_details->ADDRESS . ',' . $site_details->CTNAME . ',' . $site_details->PIN, 22);
		$printInvioce->addLine('Contact No.: ' . $store->contact_number, 22);
		$printInvioce->addLine('E-mail: ' . $store->email . '\n', 22);
		$printInvioce->addLine($invoice_title, 29, true);
		if ($store->gst) {
			$printInvioce->addLine('GSTIN: ' . $store->gst, 22);
		}
		$printInvioce->addLine('CIN: ' . 'L51909DL2002PLC163727' . '\n', 22);

		// Closes Center & Start left
		$printInvioce->addLineLeft('GST Doc No.:' . $order_details->custom_order_id, 22);
		$printInvioce->addLine($order_details->invoice_id, 22);
		$printInvioce->addLine(date('h:i A', strtotime($order_details->created_at)) . ' ' . date('d-M-Y', strtotime($order_details->created_at)), 22);
		$printInvioce->addLine('Cashier: ' . $order_details->vuser->first_name . ' ' . $order_details->vuser->last_name, 22);

		// Closes Left & Start center
		$printInvioce->addDivider('-', 20);

		// Closes Center & Start left
		$printInvioce->addLineLeft('Customer Name: ' . $customer->first_name, 22);
		$printInvioce->addLine('Customer Mobile: ' . (string) $order_details->user->mobile, 22);

		// Closes Left & Start center
		$printInvioce->addDivider('-', 20);
		$printInvioce->tableStructure(['Sr.No', 'Product Desc', 'HSN Code'], [6, 20, 8], 22);
		$printInvioce->tableStructure(['Qty', 'Rate', 'DISC', 'Tax_Amt', '%Amt', 'Amount'], [4, 5, 5, 9, 5, 6], 22);
		$printInvioce->addDivider('-', 20);
		for ($i = 0; $i < count($product_data); $i++) {
			if ($i % 2 == 0) {
				$printInvioce->tableStructure(
					[
						$product_data[$i]['sr_no'],
						$product_data[$i]['name'],
						$product_data[$i]['hsn']
					],
					[6, 20, 8],
					22
				);
			} else {
				$printInvioce->tableStructure(
					[
						' ' . $product_data[$i]['qty'],
						$product_data[$i]['rate'],
						$product_data[$i]['discount'],
						$product_data[$i]['tax_amt'],
						$product_data[$i]['tax_per'],
						$product_data[$i]['total']
					],
					[4, 5, 5, 8, 6, 6],
					22
				);
			}
		}
		$printInvioce->addDivider('-', 20);
		$printInvioce->tableStructure(['Total', $cart_qty, $total_amount], [8, 4, 22], 22);
		$printInvioce->leftRightStructure('GST', format_number('0.00'), 22);
		$printInvioce->leftRightStructure('Round Off', format_number($roundoffamt), 22);
		$printInvioce->leftRightStructure('Due:-', $due, 22);

		// Closes Center & Start left
		$printInvioce->numToWords($order_details->total, 22);

		// Closes Left & Start center
		$printInvioce->addDivider('-', 20);
		if (!empty($mop_list)) {
			foreach ($mop_list as $mop) {
				$printInvioce->leftRightStructure($mop['mode'], $mop['amount'], 22);
			}
			$printInvioce->addDivider('-', 20);
		}

		// Closes center & Start right
		$printInvioce->addLineRight('Customer Paid: ' . format_number($customer_paid), 22, true);
		$printInvioce->addLine('Balance Refund: ' . format_number($balance_refund), 22, true);

		// Closes right & Start center
		$printInvioce->addDivider('-', 20);
		$printInvioce->leftRightStructure('Total Sale', $total_sale, 22);
		$printInvioce->leftRightStructure('Total Return', '0.00', 22);
		$printInvioce->leftRightStructure('Saving on The Bill', $saving_on_the_bill, 22);
		$printInvioce->leftRightStructure('Net Sale', $net_sale, 22);
		$printInvioce->leftRightStructure('Round Off', $this->format_and_string($roundoffamt), 22);
		$printInvioce->leftRightStructure('Net Payable', $net_payable, 22);
		$printInvioce->addDivider('-', 20);
		$printInvioce->addLine($order_details->invoice_id . '\n', 22);

		// Closes center & Start left
		$printInvioce->addLineLeft('Terms and Conditions', 22, true);
		$printInvioce->addLine('--------------------', 22, true);
		foreach ($terms_conditions as $term) {
			$printInvioce->addLine($term, 20);
		}
		if ($order_details->discount > 0) {
			$printInvioce->leftRightStructure('Total Saving:', $order_details->discount, 22, true);
		}
		$printInvioce->leftRightStructure('Net Payable:', $net_payable, 22, true);

		// Closes Left & Start center
		$printInvioce->addDivider('-', 20);
		if (!empty($detatch_gst)) {
			$printInvioce->tableStructure(['Summary', 'Taxable', 'UT/CGST', 'UT/SGST', 'Cess'], [8, 9, 9, 8, 4], 20, true);
			$printInvioce->tableStructure(['', 'Amount', 'Amount', 'Amount', 'Amt'], [8, 9, 10, 8, 3], 20, true);
			$printInvioce->addDivider('-', 20);
			foreach ($detatch_gst as $index => $gst) {
				$printInvioce->tableStructure(
					[
						$gst->name,
						$gst->taxable,
						$gst->cgst,
						$gst->sgst,
						$gst->cess
					],
					[8, 10, 6, 6, 4],
					22
				);
			}
			$printInvioce->addDivider('-', 20);
			$printInvioce->tableStructure([
				'Total',
				format_number($taxable_amount),
				format_number($total_csgt),
				format_number($total_sgst),
				'0.00'
			], [6, 10, 7, 7, 4], 22, true);
			$printInvioce->addDivider('-', 20);
		}

		// Closes center & Start left
		$printInvioce->addLineLeft("Gate Pass No", 20);
		$printInvioce->leftRightStructure('Memo No', $order_details->invoice_id, 22);
		$printInvioce->leftRightStructure('Customer Name', $customer->first_name, 22);
		$printInvioce->leftRightStructure('Customer Mobile', (string) $order_details->user->mobile, 22);
		$printInvioce->leftRightStructure('Total QTY', $cart_qty, 22);
		$printInvioce->leftRightStructure('Total Amount', $total_amount, 22);
		$printInvioce->leftRightStructure('Cashier Name', $order_details->vuser->first_name . ' ' . $order_details->vuser->last_name, 22);

		if($total_amount > 1500 && $total_amount < 4000){
				$printInvioce->leftRightStructure('Luck Draw on billing of Rs.1500 to Rs.3999, where winners will get Gold Coin', 22);
		}


		$response = [
			'status' => 'success',
			'print_data' => ($printInvioce->getFinalResult())
		];
		if ($request->has('response_format') && $request->response_format == 'ARRAY') {
			return $response;
		}

		return response()->json($response, 200);
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
		$tax_code = $params['tax_code'];
		$store_id = $params['store_id'];

		$tax_type = 'INC';
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

		$main = DB::table($store_db_name . '.invhsnsacmain')->select('HSN_SAC_CODE')->where('CODE', $tax_code)->first();
		$det = DB::table($store_db_name . '.invhsnsacdet')->select('CODE', 'INVGSTRATE_CODE', 'SLAB_APPL', 'SLAB_BASIS')->where('INVHSNSACMAIN_CODE', $tax_code)->orderBy('CODE', 'desc')->first();

		if (!empty($det)) {
			if ($det->SLAB_APPL == 'Y') {
				if ($det->SLAB_BASIS == 'N') {
					$mrp = round($mrp / $qty, 2);
				} elseif ($det->SLAB_BASIS == 'R') {
					$mrp = $mrp;
				}

				$slabs = DB::table($store_db_name . '.invhsnsacslab')->select('INVGSTRATE_CODE', 'AMOUNT_FROM')->where('INVHSNSACMAIN_CODE', $tax_code)->where('INVHSNSACDET_CODE', $det->CODE)->orderBy('AMOUNT_FROM', 'ASC')->get()->toArray();

				//dd($slabs);

				$numbers = array_column($slabs, 'AMOUNT_FROM');
				$min = min($numbers);
				$max = max($numbers);
				$range = [];
				$rangeSatisfy = false;

				//dd($slabs);
				$lowest_invgst_rate_code = null;
				foreach ($slabs as $key => $value) {
					if ($key == 0) {
						$lowest_invgst_rate_code = $value->INVGSTRATE_CODE;
					}

					if (isset($slabs[$key + 1])) {

						$range[] = ['from' => $value->AMOUNT_FROM, 'to' => $slabs[$key + 1]->AMOUNT_FROM];
					}
					//dd($cgst);				
				}

				$gst = DB::table($store_db_name . '.invgstrate')->where('CODE', $lowest_invgst_rate_code)->first();

				$slab_cgst_amount = $mrp / (100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE) * $gst->CGST_RATE;
				$slab_sgst_amount = $mrp / (100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE) * $gst->SGST_RATE;
				$slab_igst_amount = $mrp / (100 + $gst->IGST_RATE + $gst->CESS_RATE) * $gst->IGST_RATE;
				$slab_cess_amount = $mrp / (100 + $gst->IGST_RATE + $gst->CESS_RATE) * $gst->CESS_RATE;

				$total_tax_amount = $this->formatValue($slab_cgst_amount) + $this->formatValue($slab_sgst_amount) + $this->formatValue($slab_cess_amount);

				$total_taxable_amount = $mrp - $total_tax_amount;
				//print_r($range);
				//echo $total_taxable_amount;
				foreach ($range as $keyR => $value) {
					if ($total_taxable_amount >= $value['from'] && $total_taxable_amount < $value['to']) {

						$rangeSatisfy = true;

						foreach ($slabs as $key => $svalue) {
							if ($svalue->AMOUNT_FROM == $value['from']) {

								$invGstRateCode = $svalue->INVGSTRATE_CODE;
							}
						}


						$gst = DB::table($store_db_name . '.invgstrate')->where('CODE', $invGstRateCode)->first();
						//dd($gst);

						$slab_cgst_amount = $mrp / (100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE) * $gst->CGST_RATE;
						$slab_sgst_amount = $mrp / (100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE) * $gst->SGST_RATE;
						$slab_igst_amount = $mrp / (100 + $gst->IGST_RATE + $gst->CESS_RATE) * $gst->IGST_RATE;
						$slab_cess_amount = $mrp / (100 + $gst->IGST_RATE + $gst->CESS_RATE) * $gst->CESS_RATE;

						$total_tax_amount = $this->formatValue($slab_cgst_amount) + $this->formatValue($slab_sgst_amount) + $this->formatValue($slab_igst_amount) + $this->formatValue($slab_cess_amount);

						$cgst = $gst->CGST_RATE;
						$sgst = $gst->SGST_RATE;
						$igst = $gst->IGST_RATE;
						$cess = $gst->CESS_RATE;
						$cgst_amount = round($slab_cgst_amount, 2);
						$sgst_amount = round($slab_sgst_amount, 2);
						$igst_amount = 0;
						$cess_amount = $this->formatValue($slab_cess_amount);
						// $tax_amount = $cgst_amount +$igst_amount + $sgst_amount + $cess_amount;
						// $tax_amount = $this->formatValue($tax_amount);
						// $taxable_amount = floatval($mrp) - floatval($tax_amount);
						// $taxable_amount = $this->formatValue($taxable_amount);
						// $total = $taxable_amount + $tax_amount;
						$tax_name = $gst->TAX_NAME;
					}
				}

				if (!$rangeSatisfy) {
					//echo 'inside range';
					$slab_len = count($slabs);
					$temp = $slabs[$slab_len - 1]->AMOUNT_FROM;
					if ($total_taxable_amount > $temp) {

						$invGstRateCode = $slabs[$slab_len - 1]->INVGSTRATE_CODE;
						$gst = DB::table($store_db_name . '.invgstrate')->where('CODE', $invGstRateCode)->first();
						//dd($gst);

						$slab_cgst_amount = $mrp / (100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE) * $gst->CGST_RATE;
						$slab_sgst_amount = $mrp / (100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE) * $gst->SGST_RATE;
						$slab_igst_amount = $mrp / (100 + $gst->IGST_RATE + $gst->CESS_RATE) * $gst->IGST_RATE;
						$slab_cess_amount = $mrp / (100 + $gst->IGST_RATE + $gst->CESS_RATE) * $gst->CESS_RATE;

						$total_tax_amount = $this->formatValue($slab_cgst_amount) + $this->formatValue($slab_sgst_amount) + $this->formatValue($slab_igst_amount) + $this->formatValue($slab_cess_amount);

						$cgst = $gst->CGST_RATE;
						$sgst = $gst->SGST_RATE;
						$igst = $gst->IGST_RATE;
						$cess = $gst->CESS_RATE;
						$cgst_amount = round($slab_cgst_amount, 2);
						$sgst_amount = round($slab_sgst_amount, 2);
						$igst_amount = 0;
						$cess_amount = $this->formatValue($slab_cess_amount);
						// $tax_amount = $cgst_amount +$igst_amount + $sgst_amount + $cess_amount;
						// $tax_amount = $this->formatValue($tax_amount);
						// $taxable_amount = floatval($mrp) - floatval($tax_amount);
						// $taxable_amount = $this->formatValue($taxable_amount);
						// $total = $taxable_amount + $tax_amount;
						$tax_name = $gst->TAX_NAME;
					}
				}

				//exit;

				$gst = DB::table($store_db_name . '.invgstrate')->where('CODE', $invGstRateCode)->first();
				//dd($gst);
				$slab_cgst_amount = $mrp / (100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE) * $gst->CGST_RATE;
				$slab_sgst_amount = $mrp / (100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE) * $gst->SGST_RATE;
				$slab_cess_amount = $mrp / (100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE) * $gst->CESS_RATE;
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
					// $tax_amount = $cgst_amount + $sgst_amount + $cess_amount;
					// $tax_amount = $this->formatValue($tax_amount);
					// $taxable_amount = floatval($mrp) - floatval($tax_amount);
					// $taxable_amount = $this->formatValue($taxable_amount);
					// $total = $taxable_amount + $tax_amount;
					$tax_name = $gst->TAX_NAME;
				}
			} elseif ($det->SLAB_APPL == 'N') {
				if ($qty > 0) {
					$mrp = round($mrp / $qty, 2);
					$gst = DB::table($store_db_name . '.invgstrate')->where('CODE', $det->INVGSTRATE_CODE)->first();
					$slab_cgst_amount = $mrp / (100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE) * $gst->CGST_RATE;
					$slab_sgst_amount = $mrp / (100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE) * $gst->SGST_RATE;
					$slab_cess_amount = $mrp / (100 + $gst->CGST_RATE + $gst->SGST_RATE + $gst->CESS_RATE) * $gst->CESS_RATE;
					$cgst = $gst->CGST_RATE;
					$sgst = $gst->SGST_RATE;
					$igst = $gst->IGST_RATE;
					$cess = $gst->CESS_RATE;
					$cgst_amount = $slab_cgst_amount;
					$sgst_amount = $slab_sgst_amount;
					$igst_amount = 0;
					$cess_amount = $this->formatValue($slab_cess_amount);
					// $tax_amount = (float)$cgst_amount + (float)$sgst_amount + (float)$cess_amount;
					// $tax_amount = $tax_amount;
					// $taxable_amount = floatval($mrp) - floatval($tax_amount);
					// $taxable_amount = $this->formatValue($taxable_amount);
					// $total = $taxable_amount + $tax_amount;
					$tax_name = $gst->TAX_NAME;
				}

				// dd($taxable_amount);
			}
		}

		// $taxable_amount = $taxable_amount * $qty;
		$cgst_amount = $cgst_amount * $qty;
		$cgst_amount = round($cgst_amount, 2);
		$sgst_amount = $sgst_amount * $qty;
		$sgst_amount = round($sgst_amount, 2);
		$igst_amount = $igst_amount * $qty;
		$igst_amount = round($igst_amount, 2);
		$slab_cess_amount = $slab_cess_amount * $qty;
		$total = $mrp * $qty;
		$taxable_amount = $total - $cgst_amount - $sgst_amount - $slab_cess_amount;
		$tax_amount = $total - $taxable_amount;
		$data = [
			'barcode'	=> $barcode,
			'hsn'		=> $main->HSN_SAC_CODE,
			'cgst'		=> $cgst,
			'sgst'		=> $sgst,
			'igst'		=> $igst,
			'cess'		=> $cess,
			'cgstamt'	=> (string) $cgst_amount,
			'sgstamt'	=> (string) $sgst_amount,
			'igstamt'	=> (string) $igst_amount,
			'cessamt'	=> (string) $slab_cess_amount,
			'netamt'	=> $mrp * $qty,
			'taxable'	=> (string) $taxable_amount,
			'tax'		=> (string) $tax_amount,
			'total'		=> $total,
			'tax_name'	=> $tax_name,
			'tax_type'  => $tax_type
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
				return (float) $value;
			} else {
				$strlen = $strlen - 2;
				return (float) substr($value, 0, -$strlen);
			}
		} else {
			return $value;
		}
	}

	// add to cart b2b

	public function b2b_add_to_cart(Request $request)
	{

		//dd($request->all());

		$v_id = $request->v_id;
		$trans_from = $request->trans_from;
		$store_id = $request->store_id;
		$barcode = $request->barcode;
		$c_id = $request->agent_id;
		$vu_id        = $request->vu_id;
		$total_qty    = $request->totalqty;
		$all_data     = $request->color;

		//   $product_data = array();
		$stores = DB::table('stores')->select('name', 'mapping_store_id', 'store_db_name')->where('store_id', $store_id)->first();
		$store_name = $stores->name;
		$store_db_name = $stores->store_db_name;
		//Getting barcode without strore tagging
		$item = DB::table($store_db_name . '.invitem')->select('GRPCODE', 'INVARTICLE_CODE', 'BARCODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE', 'LISTED_MRP', 'DESC1', 'DESC2', 'DESC3', 'DESC4', 'DESC5', 'DESC5', 'DESC6')->where('BARCODE', $barcode)->first();

		if (!$item) {
			$item = DB::table($store_db_name . '.invitem')->select('GRPCODE', 'INVARTICLE_CODE', 'BARCODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE', 'LISTED_MRP', 'DESC1', 'DESC2', 'DESC3', 'DESC4', 'DESC5', 'DESC6')->where('ICODE', $barcode)->first();
			if (!$item) {
				return response()->json(['status' => 'product_not_found', 'message' => 'Product Not Found'], 404);
			} else {
				$barcodefrom = $item->ICODE;
			}
		} else {
			$barcodefrom = $item->ICODE;
		}

		$article = DB::table($store_db_name . '.invarticle')->select('CODE', 'NAME')->where('CODE', $item->INVARTICLE_CODE)->first();
		$group = DB::table($store_db_name . '.invgrp')->select('LEV1GRPNAME', 'LEV2GRPNAME', 'GRPCODE', 'GRPNAME', 'PARCODE')->where('GRPCODE', $item->GRPCODE)->first();
		$section = DB::table($store_db_name . '.invgrp')->select('GRPCODE', 'GRPNAME', 'PARCODE')->where('GRPCODE', $group->PARCODE)->first();
		$division = DB::table($store_db_name . '.invgrp')->select('GRPCODE', 'GRPNAME')->where('GRPCODE', $section->PARCODE)->first();

		$product_name = $barcodefrom . $group->GRPNAME . $item->CNAME1;
		$DIVISION_CODE = $division->GRPCODE;
		$SECTION_CODE  = $section->GRPCODE;
		$DEPARTMENT_CODE = $item->GRPCODE;
		$ARTICLE_CODE = isValueExists($article, 'CODE');
		$RSP          = $item->MRP;


		$order_id = B2bOrder::where('user_id', $c_id)->where('status', 'success')->count();
		$order_id = $order_id + 1;

		$b2bcarts = B2bCart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();
		//dd($b2bcarts->sum('qty'));

		$check_product_in_cart_exists = $b2bcarts->where('barcode', $barcode)->first();
		//dd($check_product_in_cart_exists);
		if (empty($check_product_in_cart_exists)) {

			$B2bCart = new B2bCart;
			$B2bCart->store_id = $store_id;
			$B2bCart->transaction_type = 'sales';
			$B2bCart->v_id = $v_id;
			$B2bCart->order_id = $order_id;
			$B2bCart->user_id = $c_id;
			$B2bCart->barcode = $barcode;
			$B2bCart->item_name = $product_name;
			$B2bCart->item_id = $barcode;
			$B2bCart->qty = $total_qty;
			$B2bCart->unit_mrp = $RSP;
			$B2bCart->trans_from = $trans_from;
			$B2bCart->vu_id = $vu_id;
			$B2bCart->status = 'process';
			$B2bCart->date = date('Y-m-d');
			$B2bCart->time = date('H:i:s');
			$B2bCart->month = date('m');
			$B2bCart->year = date('Y');
			$B2bCart->department_id = $DEPARTMENT_CODE;
			$B2bCart->group_id = $SECTION_CODE;
			$B2bCart->division_id = $DIVISION_CODE;
			$B2bCart->subclass_id = $ARTICLE_CODE;
			$B2bCart->pdata = $all_data;
			$B2bCart->save();
			//       $carts = B2bCart::create([
			// 	'store_id' => $store_id,
			// 	'transaction_type' => 'sales',
			// 	'v_id' => $v_id,
			// 	'order_id' =>$order_id,
			// 	'user_id' => $c_id,
			// 	'barcode' => $barcode,
			// 	'item_name' =>$product_name,
			// 	'item_id' => $barcode,
			// 	'qty' =>     $total_qty,
			// 	'unit_mrp'=> $RSP,
			// 	'trans_from'=> $trans_from,
			// 	'vu_id'	=> $vu_id,
			// 	'status' => 'process',
			// 	'date' => date('Y-m-d'),
			// 	'time' => date('H:i:s'),
			// 	'month' => date('m'),
			// 	'year' => date('Y'),
			// 	'department_id' => $DEPARTMENT_CODE,
			// 	'group_id' => $SECTION_CODE,
			// 	'division_id' => $DIVISION_CODE,
			// 	'subclass_id' => $ARTICLE_CODE,
			// 	'pdata' =>json_encode($all_data),
			// ]);
			$b2bcart = B2bCart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();
			return response()->json([
				'status' => 'add_to_cart', 'message' => 'Product was successfully added to your cart.', 'cart_qty_total' => $b2bcart->sum('qty'),
			], 200);
		} else {
			//$check_product_in_cart_exists->id
			$id = $check_product_in_cart_exists->cart_id;
			$cart = B2bCart::find($id);
			$cart->date = date('Y-m-d');
			$cart->time  = date('H:i:s');
			$cart->month  = date('m');
			$cart->year   = date('y');
			$cart->qty   = $total_qty;
			$cart->unit_mrp = $RSP;
			$cart->pdata = $all_data;
			$cart->save();
			$b2bcart = B2bCart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();
			return response()->json([
				'status' => 'add_to_cart', 'message' => 'Product quantity successfully Updated.', 'cart_qty_total' => $b2bcart->sum('qty'),
			], 200);
		}
	}


	public function b2b_cart_details(Request $request)
	{

		//dd($request->all());

		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$c_id = $request->agent_id;
		$trans_from = $request->trans_from;
		$order_id = B2bOrder::where('user_id', $c_id)->where('status', 'success')->count();
		$order_id = $order_id + 1;
		$carts = DB::table('b2b_cart')->select('cart_id', 'item_name', 'barcode', 'unit_mrp', 'qty', 'department_id', 'subclass_id', 'division_id', 'store_id')->where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();
		$list = [];
		foreach ($carts as $key => $cart) {

			$list[] = $this->cartlist($cart);
		}
		$CartDetails = [];
		foreach ($list as $key => $cartlists) {

			$CartDetails[] =  array(
				'cart_id' => $cartlists->cart_id,
				'product_name' => $cartlists->item_name,
				'barcode' => $cartlists->barcode,
				'RSP'  => $cartlists->unit_mrp,
				'qty' => $cartlists->qty,
				'DEPARTMENT_NAME' => $cartlists->DEPARTMENT_NAME,
				'ARTICLE_NAME' => $cartlists->ARTICLE_NAME,
				'DIVISION_NAME' => $cartlists->DIVISION_NAME
			);
		}
		return response()->json([
			'status' => 'cart_details', 'message' => 'Your Cart Details', 'cart_qty_total' => $carts->sum('qty'),
			'data' => $CartDetails
		], 200);
	}

	function cartlist($cart)
	{

		$article = DB::table($this->store_db_name($cart->store_id) . '.invarticle')->select('CODE', 'NAME')->where('CODE', $cart->subclass_id)->first();
		$group = DB::table($this->store_db_name($cart->store_id) . '.invgrp')->select('LEV1GRPNAME', 'LEV2GRPNAME', 'GRPCODE', 'GRPNAME', 'PARCODE')->where('GRPCODE', $cart->department_id)->first();
		$section = DB::table($this->store_db_name($cart->store_id) . '.invgrp')->select('GRPCODE', 'GRPNAME', 'PARCODE')->where('GRPCODE', $group->PARCODE)->first();
		$division = DB::table($this->store_db_name($cart->store_id) . '.invgrp')->select('GRPCODE', 'GRPNAME')->where('GRPCODE', $section->PARCODE)->first();
		$cart->DEPARTMENT_NAME = $group->GRPNAME;
		$cart->ARTICLE_NAME = $article->NAME;
		$cart->SECTION_NAME = $section->GRPNAME;
		$cart->DIVISION_NAME = $division->GRPNAME;

		return $cart;
	}

	public function b2b_create_order(Request $request)
	{

		$v_id             = $request->v_id;
		$store_id         = $request->store_id;
		$trans_from       = $request->trans_from;
		$c_id             = $request->agent_id;
		$vu_id            = $request->vu_id;
		$destination_site = $request->destination_site;
		$size_matrix      = $request->size_matrix;
		$remarks          =  $request->remarks;


		$t_order_id = B2bOrder::where('user_id', $c_id)->where('status', 'success')->count();
		$t_order_id = $t_order_id + 1;
		$carts = DB::table('b2b_cart')->where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $t_order_id)->where('user_id', $c_id)->where('status', 'process')->get()->toArray();


		if (empty($carts)) {

			return response()->json([
				'status' => 'cart_empty', 'message' => 'Your cart is empty',
			], 200);
		} else {
			$order_id = b2b_order_id_generate($store_id, $c_id, $trans_from);
			// dd($order_id);
			// exit();
			$custom_order_id = b2b_custom_order_id_generate(['store_id' => $store_id, 'user_id' => $c_id, 'trans_from' => $trans_from]);

			try {
				DB::beginTransaction();
				$b2border = new B2bOrder;

				$b2border->order_id = $order_id;
				$b2border->custom_order_id = $custom_order_id;
				$b2border->transaction_type  = 'sales';
				$b2border->o_id = $t_order_id;
				$b2border->store_id = $store_id;
				$b2border->v_id = $v_id;
				$b2border->user_id = $c_id;
				$b2border->vu_id = $vu_id;
				$b2border->trans_from = $trans_from;
				$b2border->status = 'success';
				$b2border->date = date('Y-m-d');
				$b2border->time = date('h:i:s');
				$b2border->month = date('m');
				$b2border->year = date('Y');
				$b2border->save();


				foreach ($carts as  $cart) {

					$order_details = B2bOrderDetails::create([
						'transaction_type' => $cart->transaction_type,
						'store_id' => $cart->store_id,
						'v_id' =>  $cart->v_id,
						'order_id' => $cart->order_id,
						't_order_id' => $b2border->od_id,
						'user_id' => $cart->user_id,
						'barcode' => $cart->barcode,
						'item_name' => $cart->item_name,
						'item_id' => $cart->item_id,
						'qty' =>     $cart->qty,
						'unit_mrp' => $cart->unit_mrp,
						'trans_from' => $cart->trans_from,
						'vu_id'	=> $cart->vu_id,
						'status' => 'success',
						'date' => date('Y-m-d'),
						'time' => date('H:i:s'),
						'month' => date('m'),
						'year' => date('Y'),
						'department_id' => $cart->department_id,
						'group_id' => $cart->group_id,
						'division_id' => $cart->division_id,
						'subclass_id' => $cart->subclass_id,
						'pdata' => $cart->pdata,
					]);

					if ($order_details->status == 'success') {
						B2bCart::where('cart_id', $cart->cart_id)->delete();
					}
				}

				$B2bOrderExtra = new B2bOrderExtra;
				$B2bOrderExtra->v_id = $b2border->v_id;
				$B2bOrderExtra->store_id = $b2border->store_id;
				$B2bOrderExtra->order_id = $b2border->order_id;
				$B2bOrderExtra->agent_id = $c_id;
				$B2bOrderExtra->destination_site = $destination_site;
				$B2bOrderExtra->size_matrix = $size_matrix;
				$B2bOrderExtra->remarks = $remarks;
				$B2bOrderExtra->save();
				DB::commit();
				return response()->json([
					'status' => 'order_complete', 'message' => ' Order  complete.', 'order_id' => $b2border->order_id, 'datetime' => date("d M Y", strtotime($b2border->date)) . ' ' . $b2border->time,
				], 200);
			} catch (Exception $e) {

				DB::rollBack();
				return response()->json([
					'status' => 'error', 'message' => ' An error occurred, please try again later!',
				], 200);
			}
		}
	}

	public function b2b_order_details(Request $request)
	{

		$v_id             = $request->v_id;
		$store_id         = $request->store_id;
		$c_id             = $request->agent_id;
		$trans_from       = $request->trans_from;
		$order_id         = $request->order_id;


		$orders = B2bOrder::where('user_id', $c_id)->where('order_id', $order_id)->where('status', 'success')->first();

		//dd($orders->user->mobile);

		$productdetails = $orders->details()->get();
		$OrderItemDetails = [];
		$product_lists = [];
		foreach ($productdetails as $key => $productdetail) {

			$product_lists[] = $this->orderlist($productdetail);
		}
		foreach ($product_lists as $key => $product_list) {
			$OrderItemDetails[] = array(
				'cart_id' => $product_list->id,
				'product_name' => $product_list->item_name,
				'barcode' => $product_list->item_id,
				'RSP' => $product_list->unit_mrp,
				'qty' => $product_list->qty,
				'DEPARTMENT_NAME' => $product_list->DEPARTMENT_NAME,
				'ARTICLE_NAME' => $product_list->ARTICLE_NAME,
				'DIVISION_NAME' => $product_list->DIVISION_NAME,
				'color' => json_decode($product_list->pdata)
			);
		}

		return response()->json(['status' => 'order_details', 'message' => 'Order Details Details', 'data' => $OrderItemDetails, 'product_image_link' => product_image_link()],  200);
	}

	function orderlist($productdetail)
	{

		$article = DB::table($this->store_db_name($productdetail->store_id) . '.invarticle')->select('CODE', 'NAME')->where('CODE', $productdetail->subclass_id)->first();
		$group = DB::table($this->store_db_name($productdetail->store_id) . '.invgrp')->select('LEV1GRPNAME', 'LEV2GRPNAME', 'GRPCODE', 'GRPNAME', 'PARCODE')->where('GRPCODE', $productdetail->department_id)->first();
		$section = DB::table($this->store_db_name($productdetail->store_id) . '.invgrp')->select('GRPCODE', 'GRPNAME', 'PARCODE')->where('GRPCODE', $group->PARCODE)->first();
		$division = DB::table($this->store_db_name($productdetail->store_id) . '.invgrp')->select('GRPCODE', 'GRPNAME')->where('GRPCODE', $section->PARCODE)->first();
		$productdetail->DEPARTMENT_NAME = $group->GRPNAME;
		$productdetail->ARTICLE_NAME = $article->NAME;
		$productdetail->SECTION_NAME = $section->GRPNAME;
		$productdetail->DIVISION_NAME = $division->GRPNAME;

		return $productdetail;
	}
}
