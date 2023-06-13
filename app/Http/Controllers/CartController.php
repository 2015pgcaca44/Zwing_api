<?php

namespace App\Http\Controllers;

use App\Http\Traits\VendorFactoryTrait;
use App\Address;
use App\Cart;
use App\CartOffers;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\VendorSettingController;
use App\Http\CustomClasses\PrintInvoice;

use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Mail\OrderCreated;
use Illuminate\Support\Facades\Mail;

use Barryvdh\DomPDF\Facade as PDF;

use App\Order;
use App\Payment;
use App\Store;
use App\User;
use App\Reason;
use App\VendorImage;
use Auth;
use DB;
use Log;
use Endroid\QrCode\QrCode;
use Illuminate\Http\Request;
use Razorpay\Api\Api;
use App\Invoice;
use App\OrderDetails;
use App\OrderItemDetails;
use App\CartDetails;
use App\InvoiceDetails;
use App\InvoiceItemDetails;

use App\Model\Stock\StockCurrentStatus;
use App\Model\Stock\StockTransactions;
use App\Model\Stock\StockLogs;
use App\Model\Stock\StockPoints;
use App\Model\Stock\Serial;
use App\Model\Stock\SerialSold;
use App\Model\Items\VendorSkuDetails;
use App\Model\Items\VendorSku;
use App\Model\Items\VendorSkuDetailBarcode;
use App\Model\Items\VendorItem;

use Event;
use App\Events\Loyalty;
use App\Events\InvoiceCreated;

use App\LoyaltyBill;
use App\Http\Controllers\LoyaltyController;
use App\Organisation;
use App\SyncReports;
use App\OrderExtra;
use App\Carry;
use App\Vendor;
use App\Deposit;
use App\Http\Controllers\CloudPos\CartconfigController;
use App\Http\Controllers\CloudPos\AccountsaleController;
use App\Http\Controllers\Ginesys\PromotionController;
use App\Http\Controllers\Ginesys\CartController as GiniCartController;
use App\OrderDiscount;
use App\CartDiscount;
use App\VendorSetting;
use App\Kds;
use App\KdsDetails;
use App\Events\OrderPush;
use App\Vendor\VendorRoleUserMapping;
use App\OrderOffers;
use App\InvoiceOffers;
use App\SettlementSession;
use App\CashRegister;
use App\CashTransactionLog;
use App\CashPointSummary;
use App\CashPoint;
use App\OperationVerificationLog;
use App\Http\Controllers\CloudPos\ReturnController;
use App\Events\SaleItemReport;
use App\Model\PriceOverRideLog;
use App\PhonepeTransactions;
use App\DepRfdTrans;    
use App\Http\Controllers\CashManagementController;

use App\Model\GiftVoucher\GiftVoucherInvoiceDetails;
use App\Model\GiftVoucher\GiftVoucherGroup;
use App\Model\GiftVoucher\GiftVoucherConfiguration;
use App\Model\GiftVoucher\GiftVoucherConfigPresetMapping;
use App\Model\GiftVoucher\GiftVoucherConfigPreset;
use App\Model\GiftVoucher\GVGroupAssortmentMapping;
use App\Model\GiftVoucher\GiftVoucherTransactionLogs;
use Carbon\Carbon;
use App\Model\Payment\Mop;
use App\Voucher;

class CartController extends Controller
{
	use VendorFactoryTrait;

	public function __construct()
	{
		//$this->middleware('auth', ['except' => ['order_receipt', 'get_print_receipt', 'get_print_receipt_invoice', 'orderEmailRecipt']]);
		$this->cartconfig  = new CartconfigController;
	}

	private function store_db_name($store_id)
	{
		if ($store_id) {
			$store     = Store::find($store_id);
			$store_name = $store->store_db_name;
			return $store_name;
		} else {
			return false;
		}
	}

	public function add_to_cart(Request $request)
	{
		return $this->callMethod($request, __CLASS__, __METHOD__);
	}
	public function get_carry_bags_offline(Request $request)
	{
		return $this->callMethod($request, __CLASS__, __METHOD__);
	}

	public function bulk_add_to_cart(Request $request)
	{
		return $this->callMethod($request, __CLASS__, __METHOD__);
	}

	public function product_qty_update(Request $request)
	{
		return $this->callMethod($request, __CLASS__, __METHOD__);
	}

	public function remove_product(Request $request)
	{
		return $this->callMethod($request, __CLASS__, __METHOD__);
	}

	public function cart_details(Request $request)
	{
		return $this->callMethod($request, __CLASS__, __METHOD__);
	}

	public function calculatePromotions(Request $request){
		
		return $this->callMethod($request, __CLASS__, __METHOD__);
	}

	public function process_each_item_in_cart($request)
	{
		return $this->callMethod($request, __CLASS__, __METHOD__);
	}

	public function b2b_add_to_cart(Request $request)
	{

		return $this->callMethod($request, __CLASS__, __METHOD__);
	}

	public function b2b_cart_details(Request $request)
	{

		return $this->callMethod($request, __CLASS__, __METHOD__);
	}

	public  function b2b_create_order(Request $request)
	{

		return $this->callMethod($request, __CLASS__, __METHOD__);
	}

	public function b2b_order_details(Request $request)
	{

		return $this->callMethod($request, __CLASS__, __METHOD__);
	}
	// Cinepolis
	public function checkBeforePayment(Request $request)
	{
		return $this->callMethod($request, __CLASS__, __METHOD__);
	}

	public function get_print_receipt_invoice(Request $request)
	{
		return $this->callMethod($request, __CLASS__, __METHOD__);
	}
	// cinepolis
	public function process_to_payment(Request $request)
	{
		if($request->has('trans_type') && in_array($request->trans_type, ['order']) && $request->has('is_payment') && $request->is_payment) {
			$newOrderCon = new OrderController;
			return $newOrderCon->paymentOrderDetails($request);
		}
		$v_id 	  = $request->v_id;
		$c_id 	  = $request->c_id;
		$store_id = $request->store_id;
		$subtotal = $request->sub_total;
		$discount = $request->discount;
		$pay_by_voucher = $request->pay_by_voucher;
		$trans_from = $request->trans_from;
		$user_id = $request->vu_id;
		$role = VendorRoleUserMapping::select('role_id')->where('user_id', $user_id)->first();
		$role_id  = $role->role_id;
		$udidtoken = $request->udidtoken;
		$account_sale = '0';
		if ($request->has('payment_gateway_type')) {
			$payment_gateway_type = $request->payment_gateway_type; //'EZETAP'
		} else {
			$payment_gateway_type = 'RAZOR_PAY';
		}

		$vu_id = 0;
		if ($request->has('vu_id')) {
			$vu_id = $request->vu_id;
		}
		// validate sales person
		$accountSale = new AccountsaleController;
		if(!empty($request->type) && $request->type== 'account_deposite' || $request->type== 'adhoc_credit_note' ||  $request->type== 'refund_credit_note'   ){
			if(empty($request->debit_trans) && $request->debit_trans =='' && $request->type != 'adhoc_credit_note' ){
				return response()->json(['status' => 'fail', 'message' => 'Please select atleast one debit transaction'], 200);
			}
			return  $accountSale->payAccountBalanceRequest($request);
		} 

		// $t_order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
		$t_order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
		$t_order_id = $t_order_id + 1;
		 // //check  salesperson
        $vendorS = new VendorSettingController;
        $sParams = ['v_id' => $v_id,'store_id'=>$store_id,'user_id'=>$user_id,'role_id'=>$role_id,'trans_from' => $trans_from];
        $cartSettings = $vendorS->getCartSetting($sParams);
        $menu_list = ['assign_salesman'];
        foreach ($cartSettings as $key => $setting) {
            if (in_array($key, $menu_list)) {
                    // $cart_menu[] = ['name' =>  $key, 'status' => $status, 'display_text' =>  $display_text,'display_name'=>$display_name];
            	
            	if ($key == 'assign_salesman' && $setting->DEFAULT->status == 1){
            		 
                    if($setting->DEFAULT->options[0]->salesperson->value == 'mandatory'){
                        $cart_data = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $t_order_id)->where('user_id', $c_id)->where('status','process')->orderBy('cart_id','desc')->first();
                        if($cart_data->salesman_id == null){
                            return response()->json(['status'=>'fail','message'=>'Sales Person is Mendatory'], 200);
                        }
                    }
                }
            }
        }

		//Hold Bill
		$hold_bill = 0;
		$transaction_sub_type = 'sales';
		if ($request->has('hold_bill')) {
			$hold_bill = $request->hold_bill;
			$transaction_sub_type = 'hold';
			$hold_bill = 1;
		}
		if ($request->has('transaction_sub_type') && $request->get('transaction_sub_type')) {
			$transaction_sub_type = $request->transaction_sub_type;
			$hold_bill = 1;
		}

		if($request->has('trans_type') && in_array($request->trans_type, ['return','order'])){
			if($request->trans_type == 'order') {
				$trans_type = 'sales';
			} else {
				$trans_type = $request->trans_type;
			}
			$transaction_sub_type = $request->trans_type;
		}else{
			$trans_type = 'sales';
		}
		//Checking Opening balance has entered or not if payment is through cash
		$vendorSetting = new \App\Http\Controllers\VendorSettingController;

		$sParams = ['v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $user_id, 'role_id' => $role_id, 'trans_from' => $trans_from];
		$paymentTypeSettings = $vendorSetting->getPaymentTypeSetting($sParams);
		$cash_flag = false;
		foreach ($paymentTypeSettings as $type) {
			if ($type->name == 'cash') {
				if ($type->status == 1) {
					$cash_flag = true;
				}
			}
		}

		// Account Sale or customer credit limit
        $params = ['v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $user_id, 'role_id' => $role_id, 'trans_from' => $trans_from,'udidtoken'=>$udidtoken];
        $allowAccountSale  = $vendorSetting->getAccountSaleSetting($params);
        if($allowAccountSale=='mandatory'){
        	$account_sale = '1';
        }
        $cParams       = ['user_id'=>$c_id,'v_id'=>$v_id,'store_id'=>$store_id];
        $customerInfo  = $accountSale->customerInfo($cParams);
        // End account Sale or customer credit limit


		if ($trans_from != 'ANDROID_KIOSK' && $trans_from != 'IOS_KIOSK') {

			if (($vu_id > 0 && $payment_gateway_type == 'CASH') || $cash_flag) {
				$vendorSett = new \App\Http\Controllers\VendorSettlementController;
				$request->request->add(['user_id' => $user_id]);
				$response = $vendorSett->opening_balance_status($request);
				if ($response) {
					return $response;
				}
			}
		}
		//dd($request->bill_buster_discount);
		$net_amount = 0;
        $extra_charge = 0;
        if($request->has('net_amount')){
            $net_amount = $request->net_amount;
        }

        if($request->has('extra_charge')){
            $extra_charge = $request->extra_charge;
        }
		$bill_buster_discount = $request->bill_buster_discount;
		$tax = $request->tax_total;
		$total = $request->total;
		$trans_from = $request->trans_from;

		if ($request->payment_gateway_type == 'LOYALTY') {

			if ($request->has('loyalty')) {
				$userInformation = User::find($c_id);
				$loyaltyPrams = ['type' => $request->loyaltyType, 'event' => 'getUrl', 'mobile' => $userInformation->mobile, 'vu_id' => $request->vu_id, 'settings' => $request->loyaltySettings, 'zw_event' => 'generateUrl', 'v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $c_id, 'order_id' => $request->temp_order_id, 'billAmount' => $request->total];
				$loyaltyCon = new LoyaltyController;
				$loyaltyUrl = $loyaltyCon->index($loyaltyPrams);

				$orderDetails = Order::where('order_id', $request->temp_order_id)->first();

				$orderC = new OrderController;
				$order_arr = $orderC->getOrderResponse(['order' => $orderDetails, 'v_id' => $v_id, 'trans_from' => $trans_from]);

				$res =  ['status' => 'proceed_to_payment', 'message' => 'Proceed to Payment', 'data' => $orderDetails, 'order_summary' => $order_arr,'account_sale'=>$account_sale,'customer_info'=>$customerInfo, 'loyalty_url' => $loyaltyUrl->response['url']];

				if ($request->has('response') && $request->response == 'ARRAY') {
					return $res;
				} else {
					return response()->json($res, 200);
				}
			}
		}

		if ($request->payment_gateway_type == 'COUPON') {

			if ($request->has('loyalty')) {
				$userInformation = User::find($c_id);
				$loyaltyPrams = ['type' => $request->loyaltyType, 'event' => 'getCouponUrl', 'mobile' => $userInformation->mobile, 'vu_id' => $request->vu_id, 'settings' => $request->loyaltySettings, 'zw_event' => 'generateCouponUrl', 'v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $c_id, 'order_id' => $request->temp_order_id, 'billAmount' => $request->total];
				$loyaltyCon = new LoyaltyController;
				$loyaltyUrl = $loyaltyCon->index($loyaltyPrams);

				$orderDetails = Order::where('order_id', $request->temp_order_id)->first();

				$orderC = new OrderController;
				$order_arr = $orderC->getOrderResponse(['order' => $orderDetails, 'v_id' => $v_id, 'trans_from' => $trans_from]);
				
				$res =  ['status' => 'proceed_to_payment', 'message' => 'Proceed to Payment', 'data' => $orderDetails, 'order_summary' => $order_arr,'account_sale'=>$account_sale,'customer_info'=>$customerInfo, 'coupon_url' => $loyaltyUrl->response['url']];

				if ($request->has('response') && $request->response == 'ARRAY') {
					return $res;
				} else {
					return response()->json($res, 200);
				}
			}
		}

		
		$order_id = order_id_generate($store_id, $c_id, $trans_from);
		$existOrderId = Order::where('order_id',$order_id)->first();
		if($existOrderId){
			return response()->json(['status' => 'fail', 'message' => 'Order id already exists'], 200);
		}
		$custom_order_id = custom_order_id_generate(['store_id' => $store_id, 'user_id' => $c_id, 'trans_from' => $trans_from]);
		$store_data = Store::find($store_id);
		$order = new Order;

		$order->order_id = $order_id;
		$order->custom_order_id = $custom_order_id;
		$order->transaction_type = $trans_type;
		$order->o_id = $t_order_id;
		$order->v_id = $v_id;
		$order->store_id = $store_id;
		$order->user_id = $c_id;
		$order->trans_from = $trans_from;
		$order->subtotal = $subtotal;
		$order->discount = $discount;
		$order->net_amount = $net_amount;
		$order->extra_charge = $extra_charge;

		$order->transaction_sub_type = $transaction_sub_type;
		if ($request->has('manual_discount')) {
			$order->manual_discount = $request->manual_discount;
			$order->md_added_by = $request->vu_id;
		}
		// if($manual_discount){
		// 	$order->manual_discount = $manual_discount;
		// }

		$order->store_gstin = $store_data->gst;
		$order->store_gstin_state_id = $store_data->state_id;

		if($request->has('cust_gstin') && $request->cust_gstin != ''){

            $cust_gstin    = DB::table('customer_gstin')->select('state_id')->where('v_id',$v_id)->where('c_id', $c_id)->where('gstin', $request->cust_gstin)->first();
            if(!$cust_gstin){
            	return response()->json(['status' => 'fail' , 'message' => 'Unable to find Customer Gstin'], 200);
            }
            $order->comm_trans = 'B2B';
            $order->cust_gstin = $request->cust_gstin;
            $order->cust_gstin_state_id = $cust_gstin->state_id;
        }

		$order->bill_buster_discount = $bill_buster_discount;
		$order->tax = $tax;
		$order->total = (float) $total + (float) $pay_by_voucher;
		// if($request->has('trans_type') && $request->trans_type == 'order') {
		// 	$order->status = 'pending';
		// } else {
		// 	$order->status = 'process';
		// }
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

		// for  cinepolis KDS only starts
		$vSettings = VendorSetting::select('settings')->where('name', 'order')->where('v_id', $v_id)->first();
		$ckds = json_decode($vSettings->settings);
		// if(isset($ckds->kds->status) && $ckds->kds->status == 1 && isset($ckds->kds->generate_on) && $ckds->kds->generate_on == "order")
		// {
		// 	$kds = new Kds;
		// 	$kds->order_id = $order_id;
		// 	$kds->custom_order_id = $custom_order_id;
		// 	$kds->o_id = $t_order_id;
		// 	$kds->v_id = $v_id;
		// 	$kds->store_id = $store_id;
		// 	$kds->kds_status = 'pending';
		// 	$kds->user_id = $c_id;
		// 	$kds->trans_from = $trans_from;
		// 	$kds->subtotal = $subtotal;
		// 	$kds->discount = $discount;
		// 	$kds->transaction_sub_type = $transaction_sub_type;
		// 	if($request->has('manual_discount')){
		// 		$kds->manual_discount = $request->manual_discount;
		// 		$kds->md_added_by = $request->vu_id;
		// 	}
		// if($manual_discount){
		// 	$order->manual_discount = $manual_discount;
		// }


		$cart_data = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $t_order_id)->where('user_id', $c_id)->get();
		
		
		$cart_qty_total = $cart_data->sum('qty');
		//dd($order->id);
		$orderq = Order::find($order->od_id);
		//dd($orderq);
		$orderq->qty = (string) $cart_qty_total;
		$orderq->save();
		$porder_id = $order->od_id;
		$or_qty = 0;
		$itemList = [];
		foreach ($cart_data->toArray() as $value) {
			$cart_details_data  = CartDetails::where('cart_id', $value['cart_id'])->get()->toArray();
			$save_order_details = array_except($value, ['cart_id']);
			$save_order_details = array_add($value, 't_order_id', $porder_id);
			$order_details      = OrderDetails::create($save_order_details);

			foreach ($cart_details_data as $cdvalue) {
				$itemList[] = $cdvalue['barcode'];
				$save_order_item_details = array_add($cdvalue, 'porder_id', $order_details->id);
				OrderItemDetails::create($save_order_item_details);
			}

			// Copy Cart Offer data to Order Offer data
			$order_offers_data  = CartOffers::where('cart_id', $value['cart_id'])->get()->toArray();

			foreach ($order_offers_data as $odvalue) {
				$save_order_offers = array_add($odvalue, 'order_details_id', $order_details->id);
				OrderOffers::create($save_order_offers);
			}


			$or_qty += $value['qty'];
			//Deleting cart if hold bill is true
			if ($transaction_sub_type == 'hold') {
				CartDetails::where('cart_id', $value['cart_id'])->delete();
				CartOffers::where('cart_id', $value['cart_id'])->delete();
				Cart::where('cart_id', $value['cart_id'])->delete();
				$wherediscount = array('user_id' => $c_id, 'v_id' => $v_id, 'store_id' => $store_id, 'type' => 'manual_discount');
				if ($request->has('vu_id')) {
					$wherediscount['vu_id'] = $request->vu_id;
				}
				$check_manual_discount = CartDiscount::where($wherediscount)->orderBy('updated_at', 'desc')->first();
                 if($check_manual_discount){
					$orderDiscount = OrderDiscount::create([
							'v_id'		=> $order->v_id,
							'store_id'	=> $order->store_id,
							'order_id'	=> $order->order_id,
							'name'		=> 'Manual Discount',
							'type'		=> 'MD',
							'level'		=> 'I',
							'amount'	=> $check_manual_discount->discount,
							'basis'		=> $check_manual_discount->basis,
							'factor'	=> $check_manual_discount->factor,
							'item_list'	=> json_encode($itemList),
							'response'	=> ''
						]);
                  }
				if (!empty($check_manual_discount)) {
					$order->total = $order->subtotal;
					$order->save();
					CartDiscount::where($wherediscount)->delete();
				}
			}

		}

		$orderDetailExist = OrderDetails::where('t_order_id', (string)$order->od_id)->count();
		if($orderDetailExist == 0 || empty($orderDetailExist)){

			/*$cart_id_list = Cart::where('order_id', $order->o_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $user_id)->get(['cart_id']);
			CartDetails::whereIn('cart_id', $cart_id_list)->delete();
			CartOffers::whereIn('cart_id', $cart_id_list)->delete();
			Cart::whereIn('cart_id', $cart_id_list)->delete();

			$wherediscount = array('user_id' => $c_id, 'v_id' => $v_id, 'store_id' => $store_id, 'type' => 'manual_discount');
			CartDiscount::where($wherediscount)->delete();*/
			
			return response()->json(['status' => 'fail', 'message' => 'Order detail not exist. pleaase add item again'], 200);
		}
		/*Manual Discount*/
		if ($transaction_sub_type != 'hold') {
			$wherediscount = array('user_id' => $c_id, 'v_id' => $v_id, 'store_id' => $store_id, 'type' => 'manual_discount');
			if ($request->has('vu_id')) {
				$wherediscount['vu_id'] = $request->vu_id;
			}
			$check_manual_discount = CartDiscount::where($wherediscount)->orderBy('updated_at', 'desc')->first();
			$manual_discount       = 0;
			if ($check_manual_discount) {
				$request->merge([
					'order_id' => $order_id
				]);
				$this->manualDiscount($request);
				// $order->total = $total+$check_manual_discount->discount;
				// $order->save();
			}
		}
		/*Manual Discount*/

		//$order->qty = $or_qty;
		//$order->save();

		$payment = null;
		if ($pay_by_voucher > 0.00 && $total == 0.00) {

			$request->request->add(['t_order_id' => $t_order_id, 'order_id' => $order_id, 'pay_id' => 'user_order_id_' . $t_order_id, 'method' => 'credit_note_received', 'invoice_id' => '', 'bank' => '', 'wallet' => '', 'vpa' => '', 'error_description' => '', 'status' => 'success', 'payment_gateway_type' => 'VOUCHER', 'gateway_response' => '', 'cash_collected' => '', 'cash_return' => '', 'amount' => $pay_by_voucher]);

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
			$payment->method = 'credit_note_received';
			$payment->payment_gateway_type = 'VOUCHER';
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

		// Round Off

		$refreshOrder = Order::where([ 'v_id' => $order->v_id, 'store_id' => $order->store_id, 'user_id' => $order->user_id, 'order_id' => $order->order_id ])->first();
		
		if (!empty(getRoundValue($refreshOrder->product_total_amount))) {
			$rOffValue = getRoundValue($refreshOrder->product_total_amount);
			$refreshOrder->round_off = (string)$rOffValue;
			$refreshOrder->save();
		}

		$orderC = new OrderController;
		$order_arr = $orderC->getOrderResponse(['order' => $refreshOrder, 'v_id' => $v_id, 'trans_from' => $trans_from]);
		// dd($order_arr);
		$order = array_add($order, 'order_id', $porder_id);
		$order = array_add($order, 'trans_sub_type', $order['transaction_sub_type']);
		// for  cinepolis KDS only starts
		$vSettings = VendorSetting::select('settings')->where('name', 'order')->where('v_id', $v_id)->first();
		$ckds = json_decode($vSettings->settings);
		if (isset($ckds->kds->status) && $ckds->kds->status == 1 && isset($ckds->kds->generate_on)) {
			// checking for cinepolis if order exists on there server

			// if ($v_id == 27) {
			// 	$request->request->add(['order_id' => $order->order_id, 'checkPayment' => false]);
			// 	$thirdParty = $this->checkBeforePayment($request);
			// 	$userSession = $thirdParty['userSessionId'];
			// 	/* For cinepolis only ends*/

			// 	$kds = new Kds;
			// 	$kds->order_id = $order_id;
			// 	$kds->custom_order_id = $custom_order_id;
			// 	$kds->o_id = $t_order_id;
			// 	$kds->v_id = $v_id;
			// 	$kds->store_id = $store_id;
			// 	$kds->kds_status = 'pending';
			// 	$kds->user_id = $c_id;
			// 	$kds->trans_from = $trans_from;
			// 	$kds->subtotal = $subtotal;
			// 	$kds->discount = $discount;
			// 	$kds->transaction_sub_type = $transaction_sub_type;
			// 	if ($request->has('manual_discount')) {
			// 		$kds->manual_discount = $request->manual_discount;
			// 		$kds->md_added_by = $request->vu_id;
			// 	}
			// 	// if($manual_discount){
			// 	// 	$order->manual_discount = $manual_discount;
			// 	// }

			// 	$kds->bill_buster_discount = $bill_buster_discount;
			// 	$kds->tax = $tax;
			// 	$kds->total = (float) $total + (float) $pay_by_voucher;
			// 	$kds->status = 'process';
			// 	$kds->date = date('Y-m-d');
			// 	$kds->time = date('h:i:s');
			// 	$kds->month = date('m');
			// 	$kds->year = date('Y');
			// 	$kds->payment_type = 'full';
			// 	$kds->payment_via = $payment_gateway_type;
			// 	$kds->is_invoice = '0';
			// 	$kds->vu_id = $vu_id;
			// 	$kds->save();

			// 	foreach ($cart_data as $value) {
			// 		$cart_details_data  = CartDetails::where('cart_id', $value['cart_id'])->get()->toArray();
			// 		$save_order_details = array_except($value, ['cart_id', 't_order_id']);
			// 		// $save_order_details = array_add($value, 't_order_id', $porder_id);

			// 		if (isset($ckds->kds->status) && $ckds->kds->status == 1 && isset($ckds->kds->generate_on) && $ckds->kds->generate_on == "order") {
			// 			// $save_order_details = $save_order_details->toArray();
			// 			$save_order_details = array_add($value, 't_order_id', $kds->id);
			// 			$kds_details      = KdsDetails::create($save_order_details->toArray());
			// 		}

			// 		// for cinepolis KDS only ends
			// 	}
			// }
		}


		$res =  ['status' => 'proceed_to_payment', 'message' => 'Proceed to Payment', 'data' => $order, 'order_summary' => $order_arr,'account_sale'=>$account_sale,'customer_info'=>$customerInfo];

		if ($request->has('response') && $request->response == 'ARRAY') {
			return $res;
		} else {
			return response()->json($res, 200);
		}
	}

	private function priceOverrideLog($request,$barcode,$id)
    {
        $v_id       = $request->v_id;
        $store_id   = $request->store_id;
        $vu_id      = $request->vu_id;
        $barcode    = $barcode;
        $invocie_details_id = $id;
        $vSetting = new VendorSettingController;
        $role_id = VendorRoleUserMapping::select('role_id')->where('user_id',$vu_id)->first()->role_id;
        $settingsArray = ['v_id'=> $v_id,'store_id'=>$store_id,'user_id'=>$vu_id,'role_id'=>$role_id,'name'=>'cart'];
        $priceOverRideSetting = json_decode($vSetting->getSetting($settingsArray)->pluck('settings')->toArray()[0]);


        if(isset($priceOverRideSetting->price_override->DEFAULT->status) == 1){

            if(isset($priceOverRideSetting->price_override->DEFAULT->options[0]->varince_limit->value)){

	            $policyLimit =$priceOverRideSetting->price_override->DEFAULT->options[0]->varince_limit->value;
	            $bar = VendorSkuDetailBarcode::select('item_id','vendor_sku_detail_id','barcode')->where('is_active','1')->where('v_id', $v_id)->where('barcode', $barcode)->first();
	            
	            $item = null;
	            if($bar){
		          //   $item  =  VendorSkuDetails::select('id','vendor_sku_details.is_priceoverride_active','vendor_sku_details.price_overide_variance','vendor_sku_details.item_id')
				        // ->with(['vendorItem' => function($query) use ($v_id){
				        // $query->where('v_id',$v_id);
				        // }])
			        	// ->where(['vendor_sku_details.v_id' => $v_id , 'vendor_sku_details.id' => $bar->vendor_sku_detail_id])
			        	// ->first();

			        $item  =  VendorItem::select('vendor_items.allow_price_override','vendor_items.price_override_variance','vendor_items.item_id')
					->where(['vendor_items.v_id' => $v_id , 'vendor_items.item_id' => $bar->item_id])
					->first();	
	            }
		        if($item->allow_price_override == "1"){
		            $variance = min($item->price_overide_variance,$policyLimit);
		                $current_date = date('Y-m-d'); 

		            $settlementSession = SettlementSession::select('id','cash_register_id')->where(['v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id])->orderBy('id','desc')->first();
		            $priceOverride = new PriceOverRideLog;
		            $priceOverride->v_id = $v_id;
		            $priceOverride->store_id = $store_id;
		            $priceOverride->barcode = $barcode;
		            $priceOverride->item_id = $item->item_id;
		            $priceOverride->invoice_details_id = $invocie_details_id;
		            $priceOverride->percentage = $variance;
		            $priceOverride->terminal_id = $settlementSession->cash_register_id;
		            $priceOverride->session_id = $settlementSession->id;
		            $priceOverride->approved_by = $vu_id;
		            $priceOverride->save();
		        }
	        }
        }
        
    }

	public function payment_details(Request $request)
	{		
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
		$payment_gateway_device_type = '';
		$print_url = null;
		$vu_id = $request->vu_id;
		$role = VendorRoleUserMapping::select('role_id')->where('user_id', $vu_id)->first();
		$role_id  = $role->role_id;
		$payment_gateway_status = '';
		$invoice_seq   =null;
		$session_id = 0;

		// Payment Mapping Checker
		$exsitsMop = Mop::where([ 'code' => $method, 'status' => '1' ])->first();
		if(empty($exsitsMop)) {
			return response()->json([ 'status' => 'fail', 'message' => 'MOP not found' ]);
		}

		if(!empty($request->type) && $request->type == 'account_deposite' || $request->type =='adhoc_credit_note' || $request->type == 'refund_credit_note'){
			
			$accountSale = new AccountsaleController;
			return  $accountSale->payAccountBalanceApprove($request);
		} 

		if($request->has('trans_type') && $request->trans_type == 'return'){
			return $this->returnedSavePayment($request);
		}

		// if($request->has('trans_type') && $request->trans_type == 'order') {
		// 	$newOrderCon = new OrderController;
		// 	return $newOrderCon->takePayment($request);
		// }

		if($request->has('payment_gateway_status')){
			$payment_gateway_status = $request->payment_gateway_status ;
		}

		if($request->has('payment_gateway_device_type')){
			$payment_gateway_device_type = $request->payment_gateway_device_type;
		}
        
        if($request->has('invoice_seq')){
         $invoice_seq =   $request->invoice_seq;
        }

		$sParams = ['v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $user_id, 'role_id' => $role_id, 'trans_from' => $trans_from];

		$orders = Order::where('order_id',$order_id)->first();
		$orders_od_id = '';
		if(isset($orders->od_id)){
			$orders_od_id = $orders->od_id;
		}else{
			$orders_od_id = '';
		}
		$orderDetailExist = OrderDetails::where('t_order_id', (string)$orders_od_id)->count();
		if($orderDetailExist == 0 || empty($orderDetailExist)){

			/*$cart_id_list = Cart::where('order_id', $order->o_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $user_id)->get(['cart_id']);
			CartDetails::whereIn('cart_id', $cart_id_list)->delete();
			CartOffers::whereIn('cart_id', $cart_id_list)->delete();
			Cart::whereIn('cart_id', $cart_id_list)->delete();

			$wherediscount = array('user_id' => $c_id, 'v_id' => $v_id, 'store_id' => $store_id, 'type' => 'manual_discount');
			CartDiscount::where($wherediscount)->delete();*/

			return response()->json(['status' => 'fail', 'message' => 'Order detail not exist. pleaase add item again'], 200);
		}


		// Customer seat and hall information
		$customer_details = DB::table('customer_auth')->select('seat_no', 'hall_no')->where('c_id', $c_id)->first();
		$seat_no = isset($customer_details->seat_no)?$customer_details->seat_no:'';
		$hall_no = isset($customer_details->hall_no)?$customer_details->hall_no:'';
		// END Customer seat and hall information
		$current_date = date('Y-m-d'); 

		$settlementSession = SettlementSession::select('id')->where(['v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id])->orderBy('id','desc')->first();
		if($settlementSession){
			$session_id = $settlementSession->id;
		}
		$stores       = Store::find($store_id);
		$short_code   = $stores->short_code;
		$userDetail   = User::find($c_id);
		$totalPaymentAmount = format_number($orders->remaining_payment);
		// $totalOrderCal = format_number($orders->total_payment);
		// Check Total Payment

		if($request->payment_gateway_type == 'VOUCHER') {
			if(format_number($amount) > format_number($orders->total)) {
				$totalPaymentAmount = format_number($amount);
			}
		}

		if($request->payment_gateway_type == 'ACCOUNT_DEBIT') {
			if(format_number($amount) > format_number($orders->total)) {
				$totalPaymentAmount = format_number($amount);
			}
		}

		// dd(format_number($orders->total_payment));
		if (format_number($amount) > $totalPaymentAmount) {
			return response()->json(['status' => 'validation', 'message' => 'Paid amount is greater than invoice total'], 200);
		} else {
			// $totalPaymentAmount = $orders->total_payment;
			// dd($totalPaymentAmount);
			if (format_number($totalPaymentAmount) == format_number($amount)) {
				$payment_type = 'full';
			} else {
				$payment_type = 'partial';
			}
		}

		// if ($orders->total == $amount) {
		// 	$payment_type = 'full';
		// } else {
		// 	$totalPaymentAmount = $orders->total - $orders->total_payment;
		// 	if ($totalPaymentAmount == $amount) {
		// 		$payment_type = 'full';
		// 	} else {
		// 		$payment_type = 'partial';
		// 	}
		// }

		$remark = '';
		if ($orders && $request->has('remark')) {
			$orders->remark = $request->remark;
			$orders->save();
		}
		//for terminal
		$udidtoken = '';
		if ($request->has('udidtoken')) {
			$udidtoken    = $request->udidtoken;
			$terminalInfo = CashRegister::where('udidtoken',$udidtoken)->first(); 
		}
		if ($request->has('transaction_sub_type')) {
			if($request->has('trans_type') && $request->trans_type == 'order') {
				$orders->transaction_sub_type = $request->trans_type;
			} else {
				$orders->transaction_sub_type = $request->transaction_sub_type;
			}

			if ($request->transaction_sub_type == 'lay_by') {
				$payments = Payment::where('v_id', $orders->v_id)->where('store_id', $orders->store_id)->where('order_id', $orders->order_id)->where('status', 'success')->get();

				$amount_paid = (float) $payments->sum('amount');

				if ($request->lay_by_total > 0) {
					$lay_by_total_from_order = (float) $orders->lay_by_total;
					if ($amount_paid > 0 && $lay_by_total_from_order == 0) {
						$lay_by_total_from_order = $amount_paid;
					}
					$orders->lay_by_total = $lay_by_total_from_order + (float) $request->lay_by_total;
				}

				if ($request->has('lay_by_remark')) {
					$orders->lay_by_remark = $request->lay_by_remark;
				}
			}

			$orders->save();
		}

		if ($orders->payment_type != 'full') {
			$payment_type = 'partial';
		}

		$vu_id = 0;
		if ($request->has('vu_id')) {
			$vu_id = $request->vu_id;
		}

		$payment_save_status = false;
		if ($request->has('payment_gateway_type')) {
			$payment_gateway_type = trim($request->payment_gateway_type); //'EZETAP'
		} else {
			$payment_gateway_type = 'RAZOR_PAY';
		}

		$vendorS = new VendorSettingController;
		// Checking Opening balance has entered or not if payment is through cash
		if ($trans_from != 'ANDROID_KIOSK' && $trans_from != 'IOS_KIOSK') {

			if ($vu_id > 0 && $payment_gateway_type == 'CASH') {
				$open_sesssion_compulsory = $vendorS->getSettlementOpenSessionFunction($sParams);
				if ($open_sesssion_compulsory->status == '1') {
					$vendorSett = new \App\Http\Controllers\VendorSettlementController;
					$response = $vendorSett->opening_balance_status($request);
					if ($response) {
						return $response;
					}
				}
			}
		}

		//pine lab status pending then create payment and create  payment_initate_id
		 if($request->payment_gateway_type=='PINELAB_INTERNAL' && $request->status=='pending' && !empty($request->payment_initiate_id)) {

				$payment = new Payment;

				$payment->store_id = $store_id;
				$payment->v_id = $v_id;
				$payment->order_id = $order_id;
				$payment->user_id = $user_id;
				$payment->pay_id = $pay_id;
				$payment->amount = $amount;
				$payment->method = $method;
				$payment->mop_id = $exsitsMop->id;
				$payment->session_id =$session_id;
				$payment->terminal_id =$terminalInfo->id;
				$payment->cash_collected = $cash_collected;
				$payment->cash_return = $cash_return;
				$payment->payment_invoice_id = $invoice_id;
				$payment->bank = $bank;
				$payment->wallet = $wallet;
				$payment->vpa = $vpa;
				$payment->error_description = $error_description;
				$payment->status = $request->status;
				$payment->payment_type = $payment_type;
				$payment->payment_gateway_type = $payment_gateway_type;
				$payment->payment_gateway_device_type = $payment_gateway_device_type;
				$payment->gateway_response = json_encode($gateway_response);
				$payment->ref_txn_id=$request->payment_initiate_id;
				$payment->date = date('Y-m-d');
				$payment->time = date('H:i:s');
				$payment->month = date('m');
				$payment->year = date('Y');
				$payment->save();

				return response()->json([
		        	'status' 			=> 'payment_save',
		        	'redirect_to_qr' 	=> true,
		        	'message' 			=> 'Save Payment with Payment Initate Id',
		        	'data' 				=> $payment,
		        	'print_url' => $print_url
		        ], 200);
			}

		if($payment_gateway_status == 'OFFLINE'){
			//$method = 'paytm';
			if($method=='cash'){
				$cash_collected = $request->cash_collected;
				$cash_return = $request->cash_return;
			}

			$gateway_response = $request->gateway_response;
			$gateway_response = json_decode($gateway_response);
			
			$payment_save_status = true;

		}else if ($payment_gateway_type == 'RAZOR_PAY') {

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

			$payment_gateway_device_type = '';
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
		}  else if ($payment_gateway_type == 'PINELAB_INTERNAL') {

			$gateway_response = $request->gateway_response;
			$gateway_response = json_decode($gateway_response);
			$payment_save_status = true;
		}	else if ($payment_gateway_type == 'PINELAB_INTERNAL') {

			$gateway_response = $request->gateway_response;
			$gateway_response = json_decode($gateway_response);
			$payment_save_status = true;

		} else if ($payment_gateway_type == 'PHONEPE_ONLINE') { //shweta 080720

			if(is_numeric($request->gateway_response))
			{
				$phonepeTransaction = PhonepeTransactions::where('id' , $request->gateway_response)->first();
				$gateway_response = json_decode($phonepeTransaction->gateway_response);
			}else{
				$gateway_response = json_decode($request->gateway_response);
			}
			$payment_save_status = true;
		} else if ($payment_gateway_type == 'VOUCHER') {

			if (!$t_order_id) {
				$t_order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
				$t_order_id = $t_order_id + 1;
			}
			$pay_by_voucher = 0;
			$vSetting = new VendorSettingController;
			$role = VendorRoleUserMapping::select('role_id')->where('user_id', $vu_id)->first();
			$role_id = $role->role_id;
			$voucherSetting = $vSetting->getVoucherSetting(['v_id' => $v_id, 'user_id' => $vu_id, 'role_id' => $role_id,  'store_id' => $store_id,   'trans_from' => $trans_from]);
			$voucherUsedType = null;
			$applied_voucher = 0;
			if (isset($voucherSetting->status) &&  $voucherSetting->status == 1) {

				$vouchers = DB::table('cr_dr_settlement_log')->select('id', 'voucher_id', 'applied_amount')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('order_id', $t_order_id)->get();
				$applied_voucher = $vouchers->pluck('applied_amount', 'voucher_id')->all();


				//dd($applied_voucher);

				$pay_by_voucher = $vouchers->sum('applied_amount');
				$voucherUsedType = $voucherSetting->used_type;


				foreach ($vouchers as $voucher) {

					 
					$totalVoucher = 0;
					
					
					//'applied_amount'=>-1*$pay_by_voucher
					DB::table('cr_dr_settlement_log')->where('id', $voucher->id)->update(['applied_amount'=>-1*$pay_by_voucher]);
					



					$vou = DB::table('cr_dr_voucher')->select('id','amount', 'voucher_no', 'expired_at')->where('id', $voucher->voucher_id)->first();
					$totalVoucher = $vou->amount;
					$previous_applied = DB::table('cr_dr_settlement_log')->select('applied_amount')->where('voucher_id', $voucher->voucher_id)->where('trans_src','Redeem-CN')->get();
					$totalVoucherUsed = DB::table('cr_dr_settlement_log')->select('applied_amount')->where('voucher_id', $voucher->voucher_id)->get();

					$totalVoucherUsed   = $totalVoucherUsed->sum('applied_amount');
 					$totalAppliedAmount = abs($previous_applied->sum('applied_amount'));

					if ($voucherUsedType == 'PARTIAL') {
						if (abs($vou->amount) ==  $totalAppliedAmount || $totalVoucherUsed == '0' || $totalVoucherUsed == 0) {
							//echo 'eq';
							DB::table('cr_dr_voucher')->where('id', $voucher->voucher_id)->update(['status' => 'used']);
						} else if ($totalAppliedAmount > $vou->amount    ) {
							//echo 'gr';
							DB::table('cr_dr_voucher')->where('id', $voucher->voucher_id)->update(['status' => 'used']);
						} else {
							//echo 'nt';
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

					/*echo $vou->amount.'--';
					echo $totalAppliedAmount;die;*/

					/*Call dep rfd table*/
					$paramsVr = array('status'=> 'Process','tran_sub_type'=>'Redeem-CN','amount'=>$pay_by_voucher);
					$request->merge([
					//'order_id' => $invoice->invoice_id
					'tr_type'       => 'Debit',
					'user_id'  => $c_id,
					//'invoice_no' => $invoice->invoice_id
					]);
					$actSaleCtr  = new AccountsaleController;
					$crDrDep     = $actSaleCtr->createDepRfdRrans($request,$paramsVr);
					$crDDRr = $crDrDep->id;
					//die;	
					//$paramsLg    = array('trans_src_ref_id' => $crDDRr,'trans_src' =>'Redeem-CN','applied_amount'=>$pay_by_voucher,'voucher_id'=>$vou->id,'status'=>'APPLIED');
					//$crDrLog     = $actSaleCtr->createVocherSettLog($request,$paramsLg);

					//dd($crDrLog);
					/*End call dep_rfd_trans table*/


					DB::table('cr_dr_settlement_log')->where('id', $voucher->id)->update(['status' => 'APPLIED','trans_src_ref_id'=>$crDDRr]);

				}

				
			} else {
				$vouchers = DB::table('cr_dr_settlement_log')->select('applied_amount', 'voucher_id')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('order_id', $t_order_id)->get();
				$applied_voucher = $vouchers->pluck('applied_amount', 'voucher_id')->all();
				$pay_by_voucher = $vouchers->sum('applied_amount');
				foreach ($vouchers as $voucher) {
					DB::table('cr_dr_voucher')->where('id', $voucher->voucher_id)->update(['status' => 'used']);
				}
			}

			$donePay = Payment::where('store_id' , $store_id)->where('v_id', $v_id)->where('user_id', $user_id)->where('order_id', $order_id)->where('method','voucher_credit')->get();
			
			foreach($donePay as $voucherPay){
				$payid = explode( '_', $voucherPay->pay_id );

				foreach($applied_voucher as $key => $value){
					if(isset($payid[3]) && $payid[3] == $key){
						unset($applied_voucher[$key]);
					}
				}
			}

			//$pay_id = 'user_order_id_' . $t_order_id;
			$method = 'voucher_credit';

			//if($amount < $pay_by_voucher){
			$amount = $pay_by_voucher;
			//}

			$gateway_response = $request->gateway_response;
			$gateway_response = json_decode($gateway_response);
			$payment_save_status = true;
		}
		else if ($payment_gateway_type == 'ACCOUNT_DEBIT') {

			$pay_by_voucher = 0;
			$voucherUsedType = null;
			$applied_voucher = 0;


			$debit_voucher = DepRfdTrans::join('cr_dr_voucher','dep_rfd_trans.id','cr_dr_voucher.dep_ref_trans_ref')->where('dep_rfd_trans.user_id',$c_id)->where('dep_rfd_trans.trans_src_ref',$order_id)->where('cr_dr_voucher.status','Pending')
				->select('cr_dr_voucher.amount as amount','cr_dr_voucher.id as voucher_id','dep_rfd_trans.doc_no as doc_no')->first();

				 

			$cust = User::select('mobile')->where('c_id', $c_id)->first();
			$smsC = new SmsController;
			$message = "You have purchase $debit_voucher  ";
			$param= ['mobile' => $cust->mobile , 'message' => $message , 'for' => 'VOUCHER' ,'v_id' => $v_id, 'store_id' => $store_id ];
          	$smsC->send_sms($param);
			$pay_by_voucher = $debit_voucher->amount;
			$voucherUsedType = 'Completed';
			DB::table('cr_dr_voucher')->where('id', $debit_voucher->voucher_id)->update(['status' => 'Completed']);
			$pay_id = $debit_voucher->doc_no;
			$method = 'debit_voucher';
			$amount = $pay_by_voucher;
			$gateway_response = $request->gateway_response;
			$gateway_response = json_decode($gateway_response);
			$payment_save_status = true;	
		}
		elseif ($payment_gateway_type == 'LOYALTY') {

			return $this->pointRedemption($request);
		} elseif ($payment_gateway_type == 'COUPON') {

			return $this->couponRedemption($request);
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
		}elseif($payment_gateway_type == 'GIFT_VOUCHER') {
				
			$redeem_data=json_decode($request->redeem_data);
			foreach ($redeem_data as $key => $value) {

				$partial_value=$value->preset_details->allow_partial_redemption->config_value;
				$one_time_value=$value->preset_details->one_time->config_value;
				$redeem_amount=$value->redeem_amount;
				$gift_value=(float)$value->gift_value;

				$redeem_total=GiftVoucherTransactionLogs::where('gv_group_id',$value->gv_group_id)
												        ->where('voucher_code',$value->voucher_code)
												        ->sum('amount');
																	        
				//dd($redeem_total.'group_id-'.$value->gv_group_id.'code-'.$value->voucher_code.'-'.$redeem_amount);
				if($redeem_amount>$redeem_total){
					return ['status'=>'fail','message'=>$value->voucher_code.'-Voucher Redeem value greatet than gift value'];
				}

				$voucher_exist=GiftVoucherTransactionLogs::where('gv_group_id',$value->gv_group_id)
											  ->where('voucher_code',$value->voucher_code)->where('type','DEBIT_GV')->exists();
				if($voucher_exist){
					if($one_time_value=='Yes'){
						return ['status'=>'fail','message'=>'Voucher alreay reedem this voucher used only one times'];
					}elseif ($partial_value=='No') {
						return ['status'=>'fail','message'=>'Partial reddemption not allowed'];
					}
						
				}
				
				if($one_time_value=='Yes' || $partial_value=='No'){
					$gv_status='APPLIED';
				}elseif($partial_value=='Yes' && $redeem_total<$gift_value){
					$gv_status='PARTIAL';
					//'PROCESS','PARTIAL','APPLIED'
				}else{
					$gv_status='APPLIED';
				}
				$preset_codes=json_encode($value->preset_details);
				$redeem_amount=-1*($redeem_amount);
				$GiftVoucherTransactionLogs                   = new GiftVoucherTransactionLogs;
				$GiftVoucherTransactionLogs->v_id             = $v_id;
                $GiftVoucherTransactionLogs->store_id         = $store_id;
                $GiftVoucherTransactionLogs->gv_group_id      = $value->gv_group_id;
                $GiftVoucherTransactionLogs->vu_id            = $vu_id;
                $GiftVoucherTransactionLogs->customer_id      = $user_id;
                $GiftVoucherTransactionLogs->gv_id            = $value->gv_id;
                //$GiftVoucherTransactionLogs->gift_value       = (float)$value->gift_value;
                $GiftVoucherTransactionLogs->voucher_code     = $value->voucher_code;
                $GiftVoucherTransactionLogs->amount           = (string)($redeem_amount);
                $GiftVoucherTransactionLogs->ref_order_id     = $order_id;
                $GiftVoucherTransactionLogs->preset_codes     = $preset_codes;
                $GiftVoucherTransactionLogs->status           = $gv_status;
                $GiftVoucherTransactionLogs->type             = 'DEBIT_GV';
                $GiftVoucherTransactionLogs->mobile           = $value->mobile;
                $GiftVoucherTransactionLogs->save();
		                
			}
				
		} else {

			//$t_order_id = $request->t_order_id;
			// $pay_id = $request->pay_id; //tnx->txnId
			// $amount = $request->amount; //tnx->amount
			$cash_collected = $request->cash_collected;
			$cash_return = $request->cash_return;
			
			if($request->has('gateway_response')){
                $gateway_response = $request->gateway_response;
                $gateway_response = json_decode($gateway_response);

            }else if(!empty($gateway_response)){
				$payment->gateway_response = json_encode($gateway_response);
			}

			$payment_save_status = true;
			
		}




		if ((float) $amount > 0.00) {
			if ($payment_gateway_type == 'VOUCHER') {
				foreach ($applied_voucher as $key => $value) {
					$voucherDetails = Voucher::where([ 'v_id' => $v_id, 'id' => $key ])->first();
					$payment = new Payment;
					$payment->store_id = $store_id;
					$payment->v_id = $v_id;
					$payment->order_id = $order_id;
					$payment->user_id = $user_id;
					$payment->session_id =$session_id;
				    $payment->terminal_id =$terminalInfo->id;
					$payment->pay_id = @$voucherDetails->voucher_no;
					$payment->amount = $value;
					$payment->method = $method;
					$payment->mop_id = $exsitsMop->id;
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
				    $payment->payment_gateway_device_type = $payment_gateway_device_type;
					$payment->gateway_response = json_encode($gateway_response);
					$payment->date = date('Y-m-d');
					$payment->time = date('H:i:s');
					$payment->month = date('m');
					$payment->year = date('Y');
					$payment->save();
				}
			}else if ($payment_gateway_type == 'ACCOUNT_DEBIT') {

					$payment = new Payment;
					$payment->store_id = $store_id;
					$payment->v_id = $v_id;
					$payment->order_id = $order_id;
					$payment->user_id = $user_id;
					$payment->session_id =$session_id;
				    $payment->terminal_id =$terminalInfo->id;
					$payment->pay_id = $pay_id;
					$payment->amount = $amount;
					$payment->method = $method;
					$payment->mop_id = $exsitsMop->id;
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
				    $payment->payment_gateway_device_type = $payment_gateway_device_type;
					$payment->gateway_response = json_encode($gateway_response);
					$payment->date = date('Y-m-d');
					$payment->time = date('H:i:s');
					$payment->month = date('m');
					$payment->year = date('Y');
					$payment->save();

			} else {

				$paymentDataExist = '';
				if($request->has('payment_id')){
					$paymentDataExist = Payment::where('payment_id',$request->payment_id)->first();
				}
				if($paymentDataExist != ''){

					$payment = Payment::find($request->payment_id);  

					$payment->store_id = $store_id;
					$payment->v_id = $v_id;
					$payment->order_id = $order_id;
					$payment->user_id = $user_id;
					$payment->pay_id = $pay_id;
					$payment->amount = $amount;
					$payment->method = $method;
					$payment->mop_id = $exsitsMop->id;
					$payment->session_id =$session_id;
					$payment->terminal_id =$terminalInfo->id;
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
					$payment->payment_gateway_device_type = $payment_gateway_device_type;
					$payment->gateway_response = json_encode($gateway_response);
					$payment->date = date('Y-m-d');
					$payment->time = date('H:i:s');
					$payment->month = date('m');
					$payment->year = date('Y');
					$payment->save();

				}else{
					
					$payment = new Payment;

					$payment->store_id = $store_id;
					$payment->v_id = $v_id;
					$payment->order_id = $order_id;
					$payment->user_id = $user_id;
					$payment->pay_id = $pay_id;
					$payment->amount = $amount;
					$payment->method = $method;
					$payment->mop_id = $exsitsMop->id;
					$payment->session_id =$session_id;
					$payment->terminal_id =$terminalInfo->id;
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
					$payment->payment_gateway_device_type = $payment_gateway_device_type;
					$payment->gateway_response = json_encode($gateway_response);
					$payment->date = date('Y-m-d');
					$payment->time = date('H:i:s');
					$payment->month = date('m');
					$payment->year = date('Y');
					$payment->save();
				}
			}
		}

		if ($request->has('transaction_sub_type') && $request->transaction_sub_type == 'lay_by') {
			$deposit =  Deposit::create(['v_id' => $v_id, 'store_id' => $store_id, 'c_id' => $c_id, 'amount' => $amount, 'type' => 'ORDER', 'ref_id' => $order_id]);
		}

		if($request->has('trans_type') && $request->trans_type == 'order') {
			$newOrderCon = new OrderController;
			if(!empty($payment)) {
				$request->request->add([ 'payment_id' => $payment->payment_id, 'mop_id' => $exsitsMop->id ]);
			}
			return $newOrderCon->takePayment($request);
		}

		if (!$t_order_id) {
			// $t_order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
			$t_order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
			$t_order_id = $t_order_id + 1;
		}


		$db_structure = DB::table('vendor')->select('db_structure')->where('id', $v_id)->first()->db_structure;

		$paymenDetails = null;
		$invoice =  null;



		if ($status == 'success') {

			/* Begin Transaction */
			//DB::beginTransaction();

			try {

				// ----- Generate Order ID & Update Order status on orders and orders details -----

				$payments = Payment::where('v_id', $orders->v_id)->where('store_id', $orders->store_id)->where('order_id', $orders->order_id)->where('status', 'success')->get();
				// dd($orders->after_discount_total);
				if ($orders->after_discount_total == $payments->sum('amount')) {

					$orders->update(['status' => 'success', 'verify_status' => '1', 'verify_status_guard' => '1']);

					

				}


				OrderDetails::where('t_order_id', (string)$orders->od_id)->update(['status' => 'success']);

				// ----- Generate Invoice -----
               
                // for offline 
                 
                 	$role_id = getRoleId($vu_id);
					$params  = array('v_id'=>$v_id,'store_id'=>$store_id,'name' =>'vendor_app', 'user_id'=>$vu_id,'role_id'=>$role_id);
					$setting  = new VendorSettingController;
					$vendorAppSetting = $setting->getSetting($params)->pluck('settings')->toArray();
											 $vendorAppSettings = json_decode($vendorAppSetting[0]);
					if(isset($vendorAppSettings->offline) && $vendorAppSettings->offline->status =='1'){
				    $inc_id  = $invoice_seq;		
				    $zwing_invoice_id  = invoice_id_generate($store_id, $user_id, $trans_from,$invoice_seq,$udidtoken);
				
				    
                     

				  }else{
				  	//dd("abc");
				 $zwing_invoice_id  = invoice_id_generate($store_id, $user_id, $trans_from,$invoice_seq,$udidtoken);
				 //$zwing_invoice_id='Z2199560008';
                   //dd($zwing_invoice_id);
				// Getting incrementing id for invoice sequence
				$inc_id  = invoice_id_generate($store_id, $user_id, $trans_from,$invoice_seq,$udidtoken,'seq_id');
				}
				//dd($zwing_invoice_id);
				$custom_invoice_id = custom_invoice_id_generate(['store_id' => $store_id, 'user_id' => $user_id, 'trans_from' => $trans_from]);

				if ($payment_type == 'full') {

					// Apprtaion Discount Calculation

					if (!$orders->discounts->where('type', '!=', 'MD')->isEmpty()) {

						$totalDiscounts = $orders->discounts->whereIn('type', ['CO', 'LP'])->where('level', 'I')->all();
						$applicableItems = '';
						$allOrderItems = [];
						$discountedItems = $orders->details;
						$discountColumn = [
							'CO'	=> 'coupon_discount',
							'MD'	=> 'manual_discount',
							'LP'	=> 'lpdiscount'
						];

						foreach ($totalDiscounts as $key => $odiscount) {


							//dd($discountedItems);
							$discountedItems = discountApportionOnItems($discountedItems, $odiscount, $discountColumn[$odiscount->type]);

							// Re-calculate Tax of all items

							$vendor = Organisation::find($v_id);
							if ($vendor->db_structure == 2) {
								foreach ($discountedItems as $taxData) {
									$tdata   = json_decode($taxData->tdata);
									//$value['qty']
									$tax_total = $taxData->total;
									if ($tdata->tax_type == 'EXC') {
										$tax_total = $taxData->total - $tdata->tax;
									}
									$params  = array('barcode' => $taxData->item_id, 'qty' => 1, 's_price' => $tax_total, 'hsn_code' => $tdata->hsn, 'store_id' => $taxData->store_id, 'v_id' => $v_id);
									//dd($params);
									$cartConfig  = new CloudPos\CartController;
									$tax_details = $cartConfig->taxCal($params);

									$taxData->tax = format_number($tax_details['tax'], 2);
									$taxData->tdata = json_encode($tax_details);
								}
							} else {
								foreach ($discountedItems as $taxData) {
									$tax_code = json_decode($taxData->section_target_offers);
									$tax_code = json_decode(urldecode($tax_code->item_det));

									$GiniCartController = new GiniCartController();
									$itemTaxData = $GiniCartController->taxCal([
										'barcode' 	=> $taxData->item_id,
										'qty'		=> $taxData->qty,
										's_price'	=> $taxData->total,
										'tax_code'	=> $tax_code->INVHSNSACMAIN_CODE,
										'store_id'	=> $taxData->store_id
									]);

									$taxData->tax = format_number($itemTaxData['tax'], 2);
									$taxData->tdata = json_encode($itemTaxData);
								}
							}
						}


						//dd($discountedItems);
						// Update All Discount, Total & Tax of Orders, Order_Details

						foreach ($discountedItems as $dOrderDetails) {

							OrderDetails::find($dOrderDetails->id)->update([
								'lpdiscount'		=> $dOrderDetails->lpdiscount,
								'manual_discount'	=> $dOrderDetails->manual_discount,
								'coupon_discount'	=> $dOrderDetails->coupon_discount,
								'tax'				=> $dOrderDetails->tax,
								'total'				=> $dOrderDetails->total,
								'tdata'				=> $dOrderDetails->tdata
							]);
						}

						// Update Discount, Tax & Total 

						$orders->tax = $discountedItems->sum('tax');
						$orders->lpdiscount = $discountedItems->sum('lpdiscount');
						$orders->manual_discount = $discountedItems->sum('manual_discount');
						$orders->coupon_discount = $discountedItems->sum('coupon_discount');
						$orders->total = $orders->total - $discountedItems->sum('lpdiscount') - $discountedItems->sum('manual_discount') - $discountedItems->sum('coupon_discount');

						Order::where('order_id', $orders->order_id)->update([
							'lpdiscount'		=> $orders->lpdiscount,
							'manual_discount'	=> $orders->manual_discount,
							'coupon_discount'	=> $orders->coupon_discount,
							'tax'				=> $orders->tax,
							'total'				=> $orders->total
						]);
					}
					
					/*Lay By full payment status change */
                    if($request->has('transaction_sub_type') && $request->transaction_sub_type=='lay_by')
                    {
                    	Order::where('od_id',$orders->od_id)->update(['transaction_sub_type' => 'lay_by_processed']);
					}

                   /*end here*/

					$total_discounts   = (float)$orders->discount+(float)$orders->lpdiscount+(float)$orders->bill_buster_discount+(float)$orders->manual_discount;
					$discountDetails = ['total_discount'=>$total_discounts,'discount'=>$orders->discount,'manual_discount'=>$orders->manual_discount,'coupon_discount'=>$orders->coupon_discount,'bill_buster_discount'=>$orders->bill_buster_discount];
					$invoice = new Invoice;
					$invoice->invoice_id 		= $zwing_invoice_id;
					$invoice->custom_order_id 	= $custom_invoice_id;
					$invoice->ref_order_id 		= $orders->order_id;
					$invoice->transaction_type 	= $orders->transaction_type;
					$invoice->store_gstin 	= 	  $orders->store_gstin;
					$invoice->store_gstin_state_id 	= $orders->store_gstin_state_id;
					$invoice->comm_trans 	= $orders->comm_trans;
					$invoice->cust_gstin 	= $orders->cust_gstin;
					$invoice->cust_gstin_state_id 	= $orders->cust_gstin_state_id;
					$invoice->v_id 				= $v_id;
					$invoice->store_id 			= $store_id;
					$invoice->user_id 			= $user_id;
					$invoice->invoice_sequence  = $inc_id;
					$invoice->qty 				= $orders->qty;
					$invoice->subtotal 			= $orders->subtotal;
					$invoice->discount 			= $orders->discount;
					$invoice->lpdiscount 		= $orders->lpdiscount;
					$invoice->coupon_discount 	= $orders->coupon_discount;
					$invoice->bill_buster_discount= $orders->bill_buster_discount; 
					$invoice->remark            = $orders->remark;
					if (isset($orders->manual_discount)) {
						$invoice->manual_discount	= $orders->manual_discount;
					}
					$invoice->net_amount 		= $orders->net_amount;
					$invoice->extra_charge 		= $orders->extra_charge;
					$invoice->tax 				= $orders->tax;
					$invoice->total 			= $orders->total;
					$invoice->trans_from 		= $trans_from;
					$invoice->vu_id 			= $vu_id;
					$invoice->date 				= date('Y-m-d');
					$invoice->time 				= date('H:i:s');
					$invoice->month 			= date('m');
					$invoice->year 				= date('Y');
					$invoice->financial_year    = getFinancialYear();
					$invoice->discount_amount   = $total_discounts;
					$invoice->discount_details  = json_encode($discountDetails);


					$invoice->session_id   		= $session_id;
					$invoice->store_short_code  = $short_code;
					$invoice->terminal_name   	= isset($terminalInfo)?$terminalInfo->name:'';
					$invoice->terminal_id   	= isset($terminalInfo)?$terminalInfo->id:'';
					
					$invoice->round_off   		= $orders->round_off;

					$stores       = Store::find($store_id);
						$short_code   = $stores->short_code;
						$userDetail = '';
						$userDetail = User::where('c_id', $c_id)->first();//find($c_id);
						// // dd($userDetail);
						// if($userDetail->first_name == "Dummy"){
						// 	$userDetail->first_name='';
						// 	$userDetail->last_name='';
						// 	$userDetail->mobile='';
						// 	$userDetail->email='';
						// 	$userDetail->gender='';
						// 	$userDetail->address='';
						// 	$userDetail->customer_phone_code='';
						// }else{
						// 	$userDetail->customer_phone_code='+91';
						// }//dd($userDetail);
					// 	$invoice->customer_first_name     = isset($userDetail->first_name)?$userDetail->first_name:'';
					// 	$invoice->customer_last_name     = isset($userDetail->last_name)?$userDetail->last_name:'';
					// 	$invoice->customer_number     = isset($userDetail->mobile)?$userDetail->mobile:'';
					// 	$invoice->customer_email     = isset($userDetail->email)?$userDetail->email:'';
					// 	$invoice->customer_gender     = isset($userDetail->gender)?$userDetail->gender:'';
					// 	$invoice->customer_address  = isset($userDetail->address)?$userDetail->address:'';
					// 	// $invoice->customer_pincode  = isset($userDetail->address)?$userDetail->address->pincode:'';
						
					// 	/*if customer phone code exists then update else manually update the default country code +91*/


					// 	$invoice->customer_phone_code  = isset($userDetail->customer_phone_code)?$userDetail->customer_phone_code:'+91';
					// // if($userDetail->first_name == "Dummy"){ 
					// 	$invoice->save();
					// }//dd($userDetail);

					$invoice->customer_first_name     = isset($userDetail->first_name)?$userDetail->first_name:'';
					$invoice->customer_last_name     = isset($userDetail->last_name)?$userDetail->last_name:'';
					$invoice->customer_number     = isset($userDetail->mobile)?$userDetail->mobile:'';
					$invoice->customer_email     = isset($userDetail->email)?$userDetail->email:'';
					$invoice->customer_gender     = isset($userDetail->gender)?$userDetail->gender:'';
					$invoice->customer_address  = isset($userDetail->address)?$userDetail->address->address1:'';
					$invoice->customer_pincode  = isset($userDetail->address)?$userDetail->address->pincode:'';
					
					/*if customer phone code exists then update else manually update the default country code +91*/
					$invoice->customer_phone_code  = isset($userDetail->customer_phone_code)?$userDetail->customer_phone_code:'+91';

					$invoice->save();

					// for KDS only
					// $vSettings = VendorSetting::select('settings')->where('name', 'order')->where('v_id', $v_id)->first();
					// $ckds = json_decode($vSettings->settings);

					// if (isset($ckds->kds->status) && $ckds->kds->status == 1 && isset($ckds->kds->generate_on) && $ckds->kds->generate_on == "invoice") {
					// 	if ($db_structure == '2') {
					// 		if ($request->v_id == 27) {
					// 			$request->request->add(['checkPayment' => true]);
					// 			$thirdParty = $this->checkBeforePayment($request);
					// 			OrderExtra::where('order_id', $request->order_id)->update(['invoice_id' => $zwing_invoice_id, 'third_party_response' => $thirdParty]);
					// 		}
					// 	}
					// 	$kds = new Kds;
					// 	$kds->invoice_id 		= $zwing_invoice_id;
					// 	$kds->custom_order_id 	= $custom_invoice_id;
					// 	$kds->ref_order_id 		= $orders->order_id;
					// 	$kds->transaction_type 	= $orders->transaction_type;
					// 	$kds->v_id 				= $v_id;
					// 	$kds->store_id 			= $store_id;
					// 	$kds->user_id 			= $user_id;
					// 	$kds->kds_status 			= 'pending';
					// 	$kds->qty 				= $orders->qty;
					// 	$kds->subtotal 			= $orders->subtotal;
					// 	$kds->discount 			= $orders->discount;
					// 	$kds->lpdiscount 		= $orders->lpdiscount;
					// 	$kds->coupon_discount 	= $orders->coupon_discount;

					// 	if (isset($orders->manual_discount)) {
					// 		$kds->manual_discount	= $orders->manual_discount;
					// 	}
					// 	$kds->tax 				= $orders->tax;
					// 	$kds->total 			= $orders->total;
					// 	$kds->trans_from 		= $trans_from;
					// 	$kds->vu_id 			= $vu_id;
					// 	$kds->date 				= date('Y-m-d');
					// 	$kds->time 				= date('H:i:s');
					// 	$kds->month 			= date('m');
					// 	$kds->year 				= date('Y');
					// 	$kds->save();
					// }
					if ($db_structure != 2) {

						Payment::where('order_id', $order_id)->update(['invoice_id' => $zwing_invoice_id]);
						$payment = Payment::where('order_id', $order_id)->first();

						if ($orders->total == $payments->sum('amount')) {
							$orders->update(['status' => 'success', 'verify_status' => '1', 'verify_status_guard' => '1']);
						}

						// ------ Copy Order Details & Order Item Details to Invoice Details & Invoice Item Details ------

						$pinvoice_id = $invoice->id;

						$order_data = OrderDetails::where('t_order_id', (string)$orders->od_id)->get()->toArray();

						foreach ($order_data as $value) {
							
							if ($invoice->id) {
								/*Tax Detail Update Header Level*/
								// $tdata   = json_decode($value->tdata);
								// if($tdata->tax_name == '' || $tdata->tax_name == 'Exempted'){
								// 	$tdata->tax_name = 'GST 0%';
								// }else{
								// 	if(strpos($tdata->tax_name, 'GST') === false) $tdata->tax_name = 'GST '.$tdata->tax_name;
								// }
								// $taxDetails[$tdata->tax_name][] = $tdata->tax;

								/*Tax Detail Update Header Level End */

								$value['t_order_id']  = $invoice->id;
								$save_invoice_details = $value;
								$invoice_details_data = InvoiceDetails::create($save_invoice_details);
								$order_details_data  = OrderItemDetails::where('porder_id', $value['id'])->get()->toArray();

								foreach ($order_details_data as $indvalue) {
									$save_invoice_item_details = array_add($indvalue, 'pinvoice_id', $invoice_details_data->id);
									InvoiceItemDetails::create($save_invoice_item_details);
								}


								/* Update Stock start */

								$store_db_name = $this->store_db_name($value['store_id']);
								$barcode  =  $this->getBarcode($value['barcode'], $store_db_name);

								if ($barcode) {
									$barcode  = $barcode;
								} else {
									$barcode  = $value['barcode'];
								}

								

								$where = array('v_id' => $value['v_id'], 'barcode' => $barcode);
								$Item = null;
								if($v_id ==1){
									
									$Item  = VendorSkuDetails::where($where)->first();

								}else{

									$bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active','1')->where('v_id', $value['v_id'])->where('barcode', $barcode)->first();
									if($bar){
										$where = array('v_id' => $value['v_id'], 'vendor_sku_detail_id' => $bar->vendor_sku_detail_id);
										$Item  = VendorSku::select('sku','item_id')->where($where)->first();
									}
								}
								

								if ($Item) {

									$whereStockCurrentStatus = array(
										'variant_sku' 	=> $Item->sku,
										'item_id'		=> $Item->item_id,
										'store_id'		=> $value['store_id'],
										'v_id'			=> $value['v_id']
									);

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
											$stockpoint->is_editable= '0';
											$stockpoint->save();
										}

										$whereRefPoint	= array(
											/*'item_id'	=> $Item->item_id,*/
											'v_id'		=> $value['v_id'],
											'store_id'  => $value['store_id'],
											'is_sellable'=>'1'

										);

										$ref_stock_point = StockLogs::select('stock_point_id')->where($whereRefPoint)->orderBy('id', 'desc')->first();

										if(!$ref_stock_point){
											$whereRefPoint	= array(
												/*'item_id'	=> $Item->item_id,*/
												'v_id'		=> $value['v_id'],
												'store_id'  => $value['store_id'],
												'code'		=> 'Store_Shelf'
											);
											$ref_stock_point = StockLogs::select('stock_point_id')->where($whereRefPoint)->orderBy('id', 'desc')->first();
										}

										$stockdata 	= array(
											'variant_sku'			=> $Item->sku,
											'item_id'    			=> $Item->item_id,
											'store_id'	 			=> $value['store_id'],
											'stock_type' 			=> 'OUT',
											'stock_point_id'		=> $stockpoint->id,
											'qty'		 			=> $value['qty'],
											'ref_stock_point_id'	=> $ref_stock_point->stock_point_id,
											'v_id' 					=> $value['v_id'],
											'transaction_type'      => 'SALE'
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

								/* Update Stock end */
							}
						}
												// foreach ($taxDetails as $key => $value) {
												// 	$taxDetails[$key]   = array_sum($taxDetails[$key]);
												// }
												// $invoice->tax_details    = json_encode($taxDetails);
												// $invoice->stock_point_id = $ref_stock_point->stock_point_id;
												// $invoice->save();


											} else {
												//dd($order_id);
												  Payment::where('order_id', $order_id)->update(['invoice_id' => $zwing_invoice_id]);
												 $payment = Payment::where('order_id', $order_id)->first();

												$role_id = getRoleId($vu_id);
											  $params  = array('v_id'=>$v_id,
											  'store_id'=>$store_id,
											  'name' =>'store',
											  'user_id'=>$vu_id,
											  'role_id'=>$role_id
											);
											 $setting  = new VendorSettingController;
											 $storeSetting = $setting->getSetting($params)->pluck('settings')->toArray();
											 $storeSettings = json_decode($storeSetting[0]);
											 //dd($storeSettings);
											 if(isset($storeSettings->cashmanagement) && $storeSettings->cashmanagement->DEFAULT->status=='1'){
													$terminal_id = CashRegister::select('id')->where('udidtoken',$udidtoken)->first();
													$this->cashPointTranscationUpdate($store_id,$v_id,$payment->invoice_id,$vu_id,$terminal_id->id,$invoice->transaction_type);
											 }
       	 										//end cashmangement

											}

											/* Email Functionality */
											$emailParams = array(
												'v_id'			=> $v_id,
												'store_id'		=> $store_id,
												'invoice_id'	=> $invoice->invoice_id,
												'user_id'		=> $user_id
											);
											// $this->orderEmail($emailParams);

											$print_url  =  env('API_URL') . '/order-receipt/' . $c_id . '/' . $v_id . '/' . $store_id . '/' . $zwing_invoice_id;
										} elseif ($payment_type == 'partial') {
											// For the partial 
										}

										//	For Cloud POS

										if ($db_structure == '2') {
											if ($payment_type == 'full') {

												$whereRefPoint	= array(
													'v_id'		=> $v_id,
													'store_id'  => $store_id,
													'is_sellable'=>'1'
												);
												$ref_stock_point = StockPoints::select('id')->where($whereRefPoint)->orderBy('id', 'desc')->first();

												$pinvoice_id = $invoice->id;
												$order_data = OrderDetails::where('t_order_id', (string)$orders->od_id)->get()->toArray();

												foreach ($order_data as $value) {

													/* Copying serial data to serial sold table */
													if($value['serial_id'] > 0){
									                    $serial = Serial::find($value['serial_id']);
									                    $serialData = $serial->toArray();
									                    unset($serialData['id']);
									                    unset($serialData['created_at']);
									                    unset($serialData['updated_at']);
									                    $serialData['invoice_id'] = $invoice->id;
									                    $serialData['sales_date'] = $invoice->created_at;

									                    SerialSold::create($serialData);
									                    $serial->delete();
									                }

													/*Tax Detail Update Header Level*/
													$tdata   = json_decode($value['tdata']);
													if($tdata->tax_name == '' || $tdata->tax_name == 'Exempted'){
														$tdata->tax_name = 'GST 0%';
													}else{
														if(strpos($tdata->tax_name, 'GST') === false) $tdata->tax_name = 'GST '.$tdata->tax_name;
													}
													$taxDetails[$tdata->tax_name][] = $tdata->tax;
													/*Tax Detail Update Header Level End */

													$value['t_order_id']    = $invoice->id;
													$save_invoice_details   = $value;
													$invoice_details_data   = InvoiceDetails::create($save_invoice_details);
													if($invoice_details_data->override_flag == "1"){
														$this->priceOverrideLog($request,$invoice_details_data->barcode,$invoice_details_data->id);
													}
													if (isset($ckds->kds->status) && $ckds->kds->status == 1 && isset($ckds->kds->generate_on) && $ckds->kds->generate_on == "invoice") {
														$value['t_order_id']    = $kds->id;
														KdsDetails::create($save_invoice_details);
													}
													$order_details_data     = OrderItemDetails::where('porder_id', $value['id'])->get()->toArray();

													foreach ($order_details_data as $indvalue) {
														$save_invoice_item_details = array_add($indvalue, 'pinvoice_id', $invoice_details_data->id);
														InvoiceItemDetails::create($save_invoice_item_details);
													}

												// Copy Order Offer data to Invoice Offer data
													$invoice_offers_data  = OrderOffers::where('order_details_id', $value['id'])->get()->toArray();

													foreach ($invoice_offers_data as $odvalue) {
														$save_invoice_offers = array_add($odvalue, 'invoice_details_id', $invoice_details_data->id);
														InvoiceOffers::create($save_invoice_offers);
													}


													/*Update Stock start*/
								/*$barcode      =  $this->getBarcode($value['barcode'],$v_id);
		                        if($barcode){
		                            $barcode  = $barcode;
		                        }else{
		                            $barcode  = $value['barcode'];
		                        }*/
		                        
		                        // $params = array('v_id' => $value['v_id'], 'store_id' => $value['store_id'], 'barcode' => $value['barcode'], 'qty' => $value['qty'], 'invoice_id' => $invoice->invoice_id, 'order_id' => $invoice->ref_order_id,'transaction_type'=>'SALE','vu_id'=>$value['vu_id'],'trans_from'=>$value['trans_from']);
		                        // $this->cartconfig->updateStockQty($params);

	                        	$params = array('v_id' => $value['v_id'], 'store_id' => $value['store_id'], 'barcode' => $value['barcode'], 'qty' => $value['qty'], 'invoice_id' => $invoice->invoice_id,'transaction_scr_id'=>$invoice->id, 'order_id' => $invoice->ref_order_id,'transaction_type'=>'SALE','vu_id'=>$value['vu_id'],'trans_from'=>$value['trans_from']);

	                        	$this->cartconfig->updateStockQty($params);

	                        


		                        /*Update Stock end*/
		                    }
							$taxDetails = [];

		                    foreach ($taxDetails as $key => $value) {
		                    	$taxDetails[$key]   = array_sum($taxDetails[$key]);
		                    }
		                    $invoice->tax_details    = json_encode($taxDetails);
		                    $invoice->stock_point_id = $ref_stock_point->id;
		                    $invoice->save();

		                    // event(new SaleItemReport($invoice->invoice_id));
		                   	// dd('boo');
		                  // DB::select("call InsertSaleItemLevelReport('".$invoice->invoice_id."')"); 

						##########################
						## Remove Cart  ##########
						##########################
		                }
		            }


		            $cart_id_list = Cart::where('order_id', $orders->o_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $user_id)->get(['cart_id']);
		            CartDetails::whereIn('cart_id', $cart_id_list)->delete();
		            CartOffers::whereIn('cart_id', $cart_id_list)->delete();
		            Cart::whereIn('cart_id', $cart_id_list)->delete();

		            $wherediscount = array('user_id' => $c_id, 'v_id' => $v_id, 'store_id' => $store_id, 'type' => 'manual_discount');
		            CartDiscount::where($wherediscount)->delete();


		            $payment_method = (isset($payment->method)) ? $payment->method : '';

		            $user = Auth::user();
					// Mail::to($user->email)->send(new OrderCreated($user,$ord,$carts,$payment_method,  $complete_path));

		            $orderC = new OrderController;
		            $getOrderResponse = ['order' => $orders, 'v_id' => $v_id, 'trans_from' => $trans_from];
		            if ($request->has('transaction_sub_type')) {
		            	$getOrderResponse['transaction_sub_type'] = $request->transaction_sub_type;
		            }
		            $order_arr = $orderC->getOrderResponse($getOrderResponse);
		            // DB::commit();

		            //Invoice Created Event Is Call This event handle pushing of Pos bill to ERP

			        if(isset($invoice)){
			        	$zwingTagVId = '<ZWINGV>'.$v_id.'<EZWINGV>';
        				$zwingTagStoreId = '<ZWINGSO>'.$store_id.'<EZWINGSO>';
        				$zwingTagTranId = '<ZWINGTRAN>'.$invoice->id.'<EZWINGTRAN>';
        				$jobType = 'SALES';
        				if($invoice->transaction_type == 'return'){
        					$jobType = 'RETURN';
        				}
			        	event(new InvoiceCreated([
			        		'invoice_id' => $invoice->id,
			        		'v_id' => $v_id,
			        		'store_id' => $store_id,
			        		'db_structure' => $db_structure,
			        		'type'=> $jobType,
			        		'zv_id' => $zwingTagVId,
			        		'zs_id' => $zwingTagStoreId,
			        		'zt_id' => $zwingTagTranId
				        	] 
				        	)
				        );
			        }
		        } catch (Exception $e) {
		        	Log::error($e->getMessage());
		        	// DB::rollback();
		        	exit;
		        }

		        if (empty($order_arr['total_payable'])) {

				// Loyality

		        	if ($request->has('loyalty')) {
		        		$checkLoyaltyBillSubmit = LoyaltyBill::where('vendor_id', $v_id)->where('store_id', $store_id)->where('user_id', $user_id)->where('invoice_no', $zwing_invoice_id)->where('type', 'easeMyRetail')->where('is_submitted', '1')->first();
		        		if (empty($checkLoyaltyBillSubmit)) {
		        			$userInformation = User::find($c_id);
						// $invoice_id = Invoice::where('ref_order_id', $order_id)->first()->invoice_id;
		        			$loyaltyPrams = ['type' => $request->loyaltyType, 'event' => 'billPush', 'mobile' => $userInformation->mobile, 'vu_id' => $request->vu_id, 'settings' => $request->loyaltySettings, 'zw_event' => 'checkBill', 'v_id' => $v_id, 'store_id' => $store_id, 'invoice_id' => $zwing_invoice_id, 'user_id' => $user_id];
		        			    event(new Loyalty($loyaltyPrams));
		        		}
		        	}
		        }



			// Adding data in print data instead of url 
		        if ($request->has('manufacturer_name') && $request->manufacturer_name == 'SUNMI') {

		        	$request->merge([
		        		'v_id' => $v_id,
		        		'c_id' => $c_id,
		        		'store_id' => $store_id,
		        		'order_id' => $zwing_invoice_id
		        	]);
		        	$htmlData = $this->get_print_receipt($request);
		        	$html = $htmlData->getContent();
		        	$html_obj_data = json_decode($html);
		        	if ($html_obj_data->status == 'success') {
		        		$print_url = $html_obj_data->print_data;
		        	}
		        }

			// If trans form CLOUD TAB WEB

		        if ($request->trans_from == 'CLOUD_TAB_WEB' && !empty($invoice->id)) {

		        	$request->merge([
		        		'v_id' => $v_id,
		        		'c_id' => $c_id,
		        		'store_id' => $store_id,
		        		'order_id' => $zwing_invoice_id
		        	]);
		        	$htmlData = $this->get_print_receipt($request);
					$sParams  = ['v_id' => $v_id,'store_id'=>$store_id,'user_id'=>$c_id,'role_id'=>@$role_id,'trans_from' => $trans_from];
					$printSetting       = $vendorS->getPrintSetting($sParams);
					if(count($printSetting) > 0){
					  foreach($printSetting as $psetting){
						if($psetting->name == 'bill_print'){
							$bill_print_type = $psetting->width;
					  	}
					  }
					}
					if($bill_print_type == 'A4'){
						$payment->html_data = $htmlData;
					}else{ 	
			        	$html = $htmlData->getContent();
			        	$html_obj_data = json_decode($html);
			        	if ($html_obj_data->status == 'success') {
			        		$payment->html_data =  $this->get_html_structure($html_obj_data->print_data);
			        	}
		        	}

		        	$cust = User::where('c_id', $payment->user_id)->first();

		        	$payment->customer_name =  $cust->first_name . ' ' . $cust->last_name;
		        	$payment->mobile = $cust->mobile;
		        	$payment->email = $cust->email;
		        }
		        
		        /*in case of lay by giving customer information*/
		        if ($request->trans_from == 'CLOUD_TAB_WEB' && $request->transaction_sub_type == 'lay_by') {
		        	$cust = User::where('c_id', $payment->user_id)->first();
		        	$payment->customer_name =  $cust->first_name . ' ' . $cust->last_name;
		        	$payment->mobile = $cust->mobile;
		        	$payment->email = $cust->email;
		        }

			if($invoice && $invoice->cust_gstin != ''){
				$cust_gstin    = DB::table('customer_gstin')->select('state_id')->where('v_id',$v_id)->where('c_id', $c_id)->where('gstin', $invoice->cust_gstin)->first();
				if(!$cust_gstin){
				 return response()->json(['status' => 'fail' , 'message' => 'Unable to find Customer Gstin'], 200);
				}

				$payment->comm_trans = 'B2B';
				$payment->cust_gstin = $invoice->cust_gstin;
				$payment->cust_gstin_state_id = $cust_gstin->state_id;
			}



		        return response()->json([
		        	'status' 			=> 'payment_save',
		        	'redirect_to_qr' 	=> true,
		        	'message' 			=> 'Save Payment',
		        	'data' 				=> $payment,
		        	'order_summary' 	=> $order_arr,
		        	'print_url' => $print_url
		        ], 200);
		    } else if ($status == 'failed' || $status == 'error') {

			// ----- Generate Order ID & Update Order status on orders and orders details -----

		    	if ($trans_from == 'CLOUD_TAB' || $trans_from == 'CLOUD_TAB_ANDROID') { } else {
		    		$orders->update(['status' => $status]);
		    		OrderDetails::where('t_order_id', (string)$orders->od_id)->update(['status' => $status]);
		    	}
		    }
		}

		public function pointRedemption(Request $request)
		{
			$pointDetails = json_decode($request->gateway_response, true);
			$store = Store::find($request->store_id);
			$getSetting = $this->getStoreSetting($store->store_id);
			$order = Order::where('order_id', $request->order_id)->first();
			$finalItemList = [];

		// Add all item in Array

			foreach ($order->details as $key => $value) {
				$finalItemList[] = ['id' => $value->id, 'barcode' => $value->item_id, 'subtotal' => $value->subtotal, 'discount' => $value->discount, 'total' => $value->total, 'lpdiscount' => $value->lpdiscount, 'section_target_offers' => $value->section_target_offers, 'qty' => $value->qty, 'store_id' => $value->store_id];
			}

			$finalItemList = collect($finalItemList);

			if($getSetting['loyalty_type'] == 'mLoyal'){
				$redeemedvalue = $pointDetails['redeemedValue'];
			}else{
				$redeemedvalue = $pointDetails['redeemedvalue'];
			}

			$orderDiscount = OrderDiscount::create([
				'v_id'		=> $order->v_id,
				'store_id'	=> $order->store_id,
				'order_id'	=> $order->order_id,
				'name'		=> $getSetting['loyalty_type'].' Loyalty',
				'type'		=> 'LP',
				'level'		=> 'I',
				'amount'	=> $redeemedvalue,
				'basis'		=> 'A',
				'factor'	=> $redeemedvalue,
				'item_list'	=> json_encode($finalItemList->pluck('barcode')),
				'response'	=> $request->gateway_response
			]);

			$orderC = new OrderController;
			$order_arr = $orderC->getOrderResponse(['order' => $order, 'v_id' => $request->v_id, 'trans_from' => $request->trans_from]);

			return response()->json(['status' => 'payment_save', 'redirect_to_qr' => true, 'message' => 'Save Payment', 'data' => $orderDiscount, 'order_summary' => $order_arr], 200);
		}


		private function getStoreSetting($store_id){
			/*Get Store Setting start*/
			$storeSetting = StoreSettings::where('store_id',$store_id)->first();
			$coupon_type  = '';
			if($storeSetting){
			 $strSet       = json_decode($storeSetting->settings);
			 $coupon_type  = isset($strSet->easeMyRetail->COUPON_TYPE)?$strSet->easeMyRetail->COUPON_TYPE:'EMR';
			 $loyalty_type = isset($strSet->easeMyRetail->LOYALITY_TYPE)?$strSet->easeMyRetail->LOYALITY_TYPE:'EMR';
			}
			$data = array('coupon_type'=>$coupon_type,'loyalty_type'=>$loyalty_type);
			return $data;
			/*Store setting end*/
		}

		public function couponRedemption(Request $request)
		{
			$couponDetails = json_decode($request->gateway_response, true);
			$store = Store::find($request->store_id);
			$getSetting = $this->getStoreSetting($store->store_id);
			$order = Order::where('order_id', $request->order_id)->first();
			$finalItemList = [];
			$minPurchaseValue = 0;
			$totalAmount = 0;
			$isApplyCoupon = false;
			$discountAmount = 0;
			$orderData = [];
			$orderTotalAmount = $totalTax = 0;

		// Check Offer Code Exists or Not

			if ($couponDetails['OFFERCODE'] != null && $getSetting['coupon_type'] != 'mLoyal') {

			// Check Coupon Code Exists

				$offerDetails = DB::table($store->store_db_name . '.psite_couponoffer as pco')
				->select('pco.NAME', 'pco.ALLOW_RED_ON_PROMOITEM', 'pco.MINIMUM_RED_VALUE', 'pco.CODE')
				->join($store->store_db_name . '.psite_coupon_assign as pca', 'pca.COUPONOFFER_CODE', 'pco.CODE')
				->where('pca.ADMSITE_CODE', $store->mapping_store_id)
				->where('pco.SHORTCODE', $couponDetails['OFFERCODE'])
				->first();

				if (!empty($offerDetails)) {

				// Check Assorted Item is defined or not

					$assortmentList = DB::table($store->store_db_name . '.psite_coupon_assrt')->where('COUPONOFFER_CODE', $offerDetails->CODE)->get();

					if (!$assortmentList->isEmpty()) {

						foreach ($order->details as $key => $value) {

							$promoC = new PromotionController;
							$item = DB::table($store->store_db_name . '.invitem')->select('GRPCODE', 'INVARTICLE_CODE', 'BARCODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE', 'LISTED_MRP', 'DESC1', 'DESC2', 'DESC3', 'DESC4', 'DESC5', 'DESC5', 'DESC6')->where('ICODE', $value->item_id)->first();
							$params = ['item' => $item, 'store_id' => $store->store_id, 'is_coupon' => 1, 'assortment_list' => $assortmentList];
							$checkAssrtItem = $promoC->index($params);

							if ($checkAssrtItem == true) {
								$finalItemList[] = ['id' => $value->id, 'barcode' => $value->item_id, 'subtotal' => $value->subtotal, 'discount' => $value->discount, 'total' => $value->total, 'lpdiscount' => $value->lpdiscount, 'section_target_offers' => $value->section_target_offers, 'qty' => $value->qty, 'store_id' => $value->store_id];
							}
						}
					} else {

					// If any assortment is not tag in coupon

						foreach ($order->details as $key => $value) {
							$finalItemList[] = ['id' => $value->id, 'barcode' => $value->item_id, 'subtotal' => $value->subtotal, 'discount' => $value->discount, 'total' => $value->total, 'lpdiscount' => $value->lpdiscount, 'section_target_offers' => $value->section_target_offers, 'qty' => $value->qty, 'store_id' => $value->store_id];
						}
					}

				// Convert to Collection
					$finalItemList = collect($finalItemList);

				// Check Min Purchase Value From Loyalty & Ginesys Coupon 
					if ($couponDetails['min_purchase_value'] != 0 || $couponDetails['min_purchase_value'] == null) {
						$minPurchaseValue = format_number($couponDetails['min_purchase_value']);
					} else {
						$minPurchaseValue = format_number($offerDetails->MINIMUM_RED_VALUE);
					}

				// If Allow Redemption on Promo Item
					if (array_key_exists('ALLOW_REDEMPTION_ON_PROMO_ITEM', $couponDetails)) {
						if ($couponDetails['ALLOW_REDEMPTION_ON_PROMO_ITEM'] != 1 || $couponDetails['ALLOW_REDEMPTION_ON_PROMO_ITEM'] != '1') {
							$finalItemList = $finalItemList->transform(function ($item, $key) {
								if ($item['discount'] != '0.00' || !empty($item['discount'])) {
									return $item;
								}
							});
						}
					}

				// If Allow Point Redemption
					if ($couponDetails['allow_point_redemption'] != 1 || $couponDetails['allow_point_redemption'] != '1') {
						$finalItemList = $finalItemList->transform(function ($item, $key) {
							if ($item['lpdiscount'] != '0.00' || !empty($item['lpdiscount'])) {
								return $item;
							}
						});
					}

					$totalAmount = $finalItemList->sum('total');

				// Check if minimum purchase value is less than total amount
					if ($minPurchaseValue < $totalAmount) {
						$isApplyCoupon = true;
					} else {
						$isApplyCoupon = false;
					}
				}
			}else if($getSetting['coupon_type'] == 'mLoyal'){


				foreach ($order->details as $key => $value) {
					
					$finalItemList[] = ['id' => $value->id, 'barcode' => $value->item_id, 'subtotal' => $value->subtotal, 'discount' => $value->discount, 'total' => $value->total, 'lpdiscount' => $value->lpdiscount, 'section_target_offers' => $value->section_target_offers, 'qty' => $value->qty, 'store_id' => $value->store_id];
				
				}
				$finalItemList = collect($finalItemList);
				// Check Min Purchase Value From Loyalty & Ginesys Coupon 
					if ($couponDetails['MIN_PURCHASE_VALUE'] != 0 || $couponDetails['MIN_PURCHASE_VALUE'] == null) {
						$minPurchaseValue = format_number($couponDetails['MIN_PURCHASE_VALUE']);
					} 

				// If Allow Redemption on Promo Item
					if (array_key_exists('ALLOW_REDEMPTION_ON_PROMO_ITEM', $couponDetails)) {
						if ($couponDetails['ALLOW_REDEMPTION_ON_PROMO_ITEM'] != 1 || $couponDetails['ALLOW_REDEMPTION_ON_PROMO_ITEM'] != '1') {
							$finalItemList = $finalItemList->transform(function ($item, $key) {
								if ($item['discount'] != '0.00' || !empty($item['discount'])) {
									return $item;
								}
							});
						}
					}

				// If Allow Point Redemption
					if ($couponDetails['ALLOW_POINT_REDEMPTION'] != 1 || $couponDetails['ALLOW_POINT_REDEMPTION'] != '1') {
						$finalItemList = $finalItemList->transform(function ($item, $key) {
							if ($item['lpdiscount'] != '0.00' || !empty($item['lpdiscount'])) {
								return $item;
							}
						});
					}

					$totalAmount = $finalItemList->sum('total');

				// Check if minimum purchase value is less than total amount
					if ($minPurchaseValue < $totalAmount) {
						$isApplyCoupon = true;
					} else {
						$isApplyCoupon = false;
					}
				

			} else { 
			}

		// Coupon Calculations

			if ($isApplyCoupon) {

			  if($getSetting['coupon_type'] == 'mLoyal'){

				// Calculate Discount Amount & Convert Basis value
				if ($couponDetails['BASIS'] == '0') {
				  $discountAmount = $totalAmount * $couponDetails['FACTOR'] / 100;
				  $basis 			= 'P';
				 } elseif ($couponDetails['BASIS'] == '1') {
				  $discountAmount = $couponDetails['FACTOR'];
				  $basis = 'A';
				}
				// Check discount Amount not exceed max redeem value
				if($couponDetails['MAX_REDEEM_VALUE'] != 0 || $couponDetails['MAX_REDEEM_VALUE'] != null) {
					if ($discountAmount > $couponDetails['MAX_REDEEM_VALUE']) {
						$discountAmount = $couponDetails['MAX_REDEEM_VALUE'];
					}
				}
				}else{
					// Calculate Discount Amount & Convert Basis value
					if ($couponDetails['basis'] == '0') {
						$discountAmount = $totalAmount * $couponDetails['factor'] / 100;
						$basis = 'P';
					} elseif ($couponDetails['basis'] == '1') {
						$discountAmount = $couponDetails['factor'];
						$basis = 'A';
					}
					// Check discount Amount not exceed max redeem value
					if ($discountAmount > $couponDetails['max_redeem_value']) {
						$discountAmount = $couponDetails['max_redeem_value'];
					}
				}

				$finalItemList = collect($finalItemList);

				$orderDiscount = OrderDiscount::create([
					'v_id'		=> $order->v_id,
					'store_id'	=> $order->store_id,
					'order_id'	=> $order->order_id,
					'name'		=> $getSetting['coupon_type'].' Coupon',
					'type'		=> 'CO',
					'level'		=> 'I',
					'amount'	=> $discountAmount,
					'basis'		=> $basis,
					'factor'	=> $couponDetails['factor'],
					'item_list'	=> json_encode($finalItemList->pluck('barcode')),
					'response'	=> $request->gateway_response
				]);


				$orderC = new OrderController;
				$order_arr = $orderC->getOrderResponse(['order' => $order, 'v_id' => $request->v_id, 'trans_from' => $request->trans_from]);

				return response()->json(['status' => 'payment_save', 'redirect_to_qr' => true, 'message' => 'Save Payment', 'data' => $orderDiscount, 'order_summary' => $order_arr], 200);
			}
		}


		public function manualDiscount(Request $request)
		{
			
		//$pointDetails = json_decode($request->gateway_response, true);
			$v_id = $request->v_id;
			$store_id = $request->store_id;
			$c_id = $request->c_id;

			$wherediscount = array('user_id' => $c_id, 'v_id' => $v_id, 'store_id' => $store_id, 'type' => 'manual_discount');
			if ($request->has('vu_id')) {
				$wherediscount['vu_id'] = $request->vu_id;
			}
			$check_manual_discount = CartDiscount::where($wherediscount)->orderBy('updated_at', 'desc')->first();

			$dis_data = json_decode($check_manual_discount->dis_data, true);
			$dis_data = collect($dis_data['cart_data']);
			$store = Store::find($request->store_id);
			$order = Order::where('order_id', $request->order_id)->first();

			$order->manual_discount = $check_manual_discount->discount;
			$order->save();

			$finalItemList = [];

		// Add all item in Array

			foreach ($order->details as $key => $value) {
			// OrderDetails::find($value->id)->update([ 'manual_discount' => $dis_data->where('item_id', $value->item_id)->sum('discount') ]);
				$details = OrderDetails::find($value->id);
				$data = $dis_data->where('item_id', $value->item_id)->where('batch_id',$value->batch_id)->where('serial_id',$value->serial_id)->where('unit_mrp',$value->unit_mrp)->first();
				$details->manual_discount = (string)$data['discount'];
				$details->tax = (string)$data['tdata']['tax'];
				$details->total = (string)$data['total'];
				$details->tdata = json_encode($data['tdata']);
				$details->save();
				$finalItemList[] = ['id' => $value->id, 'barcode' => $value->item_id, 'subtotal' => $value->subtotal, 'discount' => $value->discount, 'total' => $value->total, 'lpdiscount' => $value->lpdiscount, 'section_target_offers' => $value->section_target_offers, 'qty' => $value->qty, 'store_id' => $value->store_id];
			}

			$finalItemList = collect($finalItemList);

			$orderDiscount = OrderDiscount::create([
				'v_id'		=> $order->v_id,
				'store_id'	=> $order->store_id,
				'order_id'	=> $order->order_id,
				'name'		=> 'Manual Discount',
				'type'		=> 'MD',
				'level'		=> 'I',
				'amount'	=> $check_manual_discount->discount,
				'basis'		=> $check_manual_discount->basis,
				'factor'	=> $check_manual_discount->factor,
				'item_list'	=> json_encode($finalItemList->pluck('barcode')),
				'response'	=> ''
			]);

			$orderC = new OrderController;
			$order_arr = $orderC->getOrderResponse(['order' => $order, 'v_id' => $request->v_id, 'trans_from' => $request->trans_from]);

			return response()->json(['status' => 'payment_save', 'redirect_to_qr' => true, 'message' => 'Save Payment', 'data' => $orderDiscount, 'order_summary' => $order_arr], 200);
		
		}

		public function order_qr_code(Request $request)
		{
			$order_id = $request->order_id;
			$qrCode = new QrCode($order_id);

			if ($request->has('go_to')) {
				if ($request->go_to == 'pos_checkout') {
					$order = DB::table('orders')->where('order_id', $order_id)->select('v_id', 'store_id', 'user_id', 'o_id')->first();
					$carts = DB::table('cart')->where('v_id', $order->v_id)->where('store_id', $order->store_id)->where('user_id', $order->user_id)->where('order_id', $order->o_id)->select('qty', 'barcode')->get();
					$items = '';
					foreach ($carts as $key => $value) {
						$temp_qty = $value->qty;
						while ($temp_qty > 0) {
							$items .= $value->barcode . PHP_EOL;
							$temp_qty--;
						}
					}
				//$cart_items = $carts->pluck('qty' , 'barcode')->all();
					$cart_items = json_encode($items);
					$qrCode = new QrCode($cart_items);
				}
			}

			header('Content-Type: image/png');
			echo $qrCode->writeString();
		}

		public function order_pre_verify_guide(Request $request)
		{
			return $this->callMethod($request, __CLASS__, __METHOD__);
		}

		public function order_details(Request $request)
		{
			return $this->callMethod($request, __CLASS__, __METHOD__);
		}

		public function order_receipt($c_id, $v_id, $store_id, $order_id, $usefor = '',$type='')
		{
			$vendor   = DB::connection('mysql')->table('vendor')->where('id', $v_id)->first();

			if ($vendor->db_structure == 2) {
				// if ($v_id == 27) {
				// 	$cartC = new Cinepolis\CartController;
				// 	$response = $cartC->order_receipt($c_id, $v_id, $store_id, $order_id );
				// //echo $response;
				// } else {
					$cartC = new CloudPos\CartController;
					$response = $cartC->order_receipt($c_id, $v_id, $store_id, $order_id,$usefor,$type);
				// }
			// return $response;

			} else if ($v_id == 4) {
				$cartC = new Spar\CartController;
				$response = $cartC->order_receipt($c_id, $v_id, $store_id, $order_id);
				// return $response;
				//echo $response;

			} else if ($v_id == 1) {
				$cartC = new Vmart\CartController;
				$response = $cartC->order_receipt($c_id, $v_id, $store_id, $order_id);
			// return $response;
			//echo $response;

			} else if ($v_id == 26) {

				$cartC = new Zwing\CartController;
				$response = $cartC->order_receipt($c_id, $v_id, $store_id, $order_id);
			// return $response;
			//echo $response;

			} else if ($v_id == 28) {

				$cartC = new Dmart\CartController;
				$response = $cartC->order_receipt($c_id, $v_id, $store_id, $order_id);
			// return $response;
			//echo $response;

			} else if ($v_id == 30) {

				$cartC = new Hero\CartController;
				$response = $cartC->order_receipt($c_id, $v_id, $store_id, $order_id);
			// return $response;
				echo $response;
			} else if ($v_id == 34) {

				$cartC = new Metro\CartController;
				$response = $cartC->order_receipt($c_id, $v_id, $store_id, $order_id);
			// return $response;
			//echo $response;

			} else if ($v_id == 17) {

				$cartC = new JustDelicious\CartController;
				$response = $cartC->order_receipt($c_id, $v_id, $store_id, $order_id);
			// return $response;
			//echo $response;

			} else if ($v_id == 20) {
				$cartC = new More\CartController;
				$response = $cartC->order_receipt($c_id, $v_id, $store_id, $order_id);
			// return $response;
			//echo $response;

			} else if ($v_id == 21) {
				$cartC = new MajorBrands\CartController;
				$response = $cartC->order_receipt($c_id, $v_id, $store_id, $order_id);
			// return $response;
			//echo $response;

			} else if ($v_id == 23) {
				$cartC = new XimiVogue\CartController;
				$response = $cartC->order_receipt($c_id, $v_id, $store_id, $order_id);
			//echo $response;
			} else if ($v_id == 35) {
				$cartC = new Skechers\CartController;
				$response = $cartC->order_receipt($c_id, $v_id, $store_id, $order_id);
			//echo $response;
			}else{
				$cartC = new Ginesys\CartController;
				$response = $cartC->order_receipt($c_id, $v_id, $store_id, $order_id);
			}

		//$response ='aasdfasd';
			if ($usefor == 'send_email') {
				return $response;
			} else {
				echo $response;
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

		// $renderPrintPreview = '<!DOCTYPE html><html><head>
		// 						<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		//                              <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
		//                          	<title>Cool</title>
		//                          	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
		//                           <style type="text/css">
		//                           * {  font-family: Lato; }
		// 					div { margin: 30px 0; border: 1px solid #f5f5f5; }
		// 					table {  width: 350px;  }
		// 					.center { text-align: center;  }
		// 					.left { text-align: left; }
		// 					.left pre { padding:0 30px !important; }
		// 					.right { text-align: right;  }
		// 					.right pre { padding:0 30px !important; }
		// 					td { padding: 0 5px; }
		// 					tbody { display: table !important; width: inherit; word-wrap: break-word; }
		// 					pre {
		// 					    white-space: pre-wrap;       /* Since CSS 2.1 */
		// 					    white-space: -moz-pre-wrap;  /* Mozilla, since 1999 */
		// 					    white-space: -pre-wrap;      /* Opera 4-6 */
		// 					    white-space: -o-pre-wrap;    /* Opera 7 */
		// 					    word-wrap: break-word;       /* Internet Explorer 5.5+ */
		// 					    overflow: hidden;
		// 					    background-color: #fff;
		// 					    padding: 0;
		// 					    border: none;
		// 					    font-size: 12.5px !important;
		// 					}
		//                           </style>
		//                      </head>

		//                      <body>
		//                          <center>

		//                              <div style="width: 350px;">
		//                              <table>
		//                          '
		//                              .urldecode($string).
		//                          '</table>
		//                          </div>

		//                              </center>
		//                      </body>
		//                          </html>';

			$renderPrintPreview = '<center><div style="width: 350px;"><table>' . urldecode($string) . '</table></div></center>';

			return $renderPrintPreview;
		}

		public function get_carry_bags(Request $request)
		{
			return $this->callMethod($request, __CLASS__, __METHOD__);
		}

		public function save_carry_bags(Request $request)
		{
			return $this->callMethod($request, __CLASS__, __METHOD__);
		}

		public function deliveryStatus(Request $request)
		{
			return $this->callMethod($request, __CLASS__, __METHOD__);
		}

		public function get_print_receipt(Request $request)
		{
			return $this->callMethod($request, __CLASS__, __METHOD__);
		}

		public function get_print_offline(Request $request)
		{
			return $this->callMethod($request, __CLASS__, __METHOD__);
		}

		public function print_ack(Request $request){

			$trans_from = $request->trans_from;
			$v_id = $request->v_id;
			$store_id = $request->store_id;
			$c_id = $request->c_id;
			$vu_id = $request->vu_id;
			$operation = $request->operation;
			$invoice_id = $request->invoice_id;
			$security_code_vu_id = null;

			$billPrint = null;
			if($operation == 'BILL_PRINT'){

				$billPrint = OperationVerificationLog::where(['v_id' => $v_id, 'store_id' => $store_id, 'order_id' => $invoice_id, 'operation' => $operation])->first();
			}else{
				$security_code_vu_id = $request->security_code_vu_id;
			}

			if(!$billPrint){

				OperationVerificationLog::create(['v_id' => $v_id, 'store_id' => $store_id, 'c_id' => $c_id, 'trans_from' => $trans_from, 'vu_id' => $vu_id, 'operation' => $operation, 'order_id' => $invoice_id, 'verify_by' =>  $security_code_vu_id, 'created_at' => date('Y-m-d H:i:s')]);
				return response()->json(['status' => 'success', 'message' => 'Print Acknowledged'], 200);

			}else{
				return response()->json(['status' => 'fail', 'message' => 'Printing Already Acknowledge'], 200);
			}

		}

		public function get_duplicate_receipt(Request $request)
		{

			$vu_id = $request->vu_id;
			$v_id = $request->v_id;
			$store_id = $request->store_id;
			$security_code_vu_id = $request->security_code_vu_id;
			//$c_id = $request->c_id;
			$order_id = $request->order_id;
			$cust_mobile_no = $request->cust_mobile_no;
			$trans_from = $request->trans_from;
			$operation = $request->operation;
			$isOrderReciept = false;
	        if($request->has('trans_type') && $request->trans_type == 'order') {
	            $isOrderReciept = true;
	        }
	        // dd($request->all());

			$user = User::select('c_id', 'mobile')->where('mobile', $cust_mobile_no)->first();
			if ($user) {

				$today_date = date('Y-m-d');
				if ($request->has('order_id')) {

					$order = Invoice::where('invoice_id', $order_id)->first();
				} else {

					$order = Invoice::where('user_id', $user->c_id)->where('v_id', $v_id)->where('store_id', $store_id)->orderBy('id', 'desc')->where('date', $today_date)->where('trans_from', $trans_from)->first();
				}
				if($isOrderReciept) {
					$order = Order::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'order_id' => $request->order_id ])->first();
				}

				if ($order) {

				// dd(date('Y-m-d H:i:s'));

					// DB::table('operation_verification_log')->insert(['v_id' => $v_id, 'store_id' => $store_id, 'c_id' => $user->c_id, 'trans_from' => $trans_from, 'vu_id' => $vu_id, 'operation' => $operation, 'order_id' => $order->invoice_id, 'verify_by' =>  $security_code_vu_id, 'created_at' => date('Y-m-d H:i:s')]);

					if($isOrderReciept) {
						$request->request->add(['c_id' => $user->c_id, 'order_id' => $order->order_id]);
					} else {
						$request->request->add(['c_id' => $user->c_id, 'order_id' => $order->invoice_id]);
					}
					if ($request->has('response_type') && $request->response_type == 'WEB_VIEW') {
						$request->request->add(['response_format' => 'ARRAY']);
						$print = $this->get_print_receipt($request);

					//dd($print); 

						$finalRes = null;
						if(isset($print['print_data']) ){
							$finalRes = $this->get_html_structure($print['print_data']);
						}else{
							$finalRes = $print;
						}

						return response()->json(['status' => 'success', 'print_data' => $finalRes], 200);
					}
					return $this->get_print_receipt($request);
				} else {
					return response()->json(['status' => 'fail', 'message' => 'Unbale to find any order which has been placed today'], 200);
				}
			} else {
				return response()->json(['status' => 'fail', 'message' => 'Customer not exists'], 200);
			}
		}

		public function taxCal($params)
		{
			$data    = array();
			$qty         = $params['qty'];
			$mrp         = $params['s_price'];
			$store_id    = $params['store_id'];
			$barcode     = $params['barcode'];
			$hsn_code    = $params['hsn_code'];
			$v_id        = $params['v_id'];

			$cgst_amount = 0;
			$sgst_amount = 0;
			$igst_amount = 0;
			$cess_amount = 0;
			$cgst        = 0;
			$sgst        = 0;
			$igst        = 0;
			$cess        = 0;
			$slab_amount = 0;
			$slab_cgst_amount = 0;
			$slab_sgst_amount = 0;
			$slab_cess_amount = 0;
			$tax_amount       = 0;
			$taxable_amount   = 0;
			$total            = 0;
			$to_amount        = 0;
			$tax_name         = '';
			$tax_type         = '';

			$bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $barcode)->first();
			if($bar){
				$item_master  = VendorSku::select('item_id','hsn_code','tax_type')->where(['vendor_sku_detail_id' => $bar->vendor_sku_detail_id, 'hsn_code' => $hsn_code, 'v_id' => $v_id])->first();

			}
			if (!$item_master) {
				$item_master = VendorSku::select('item_id','hsn_code','tax_type')->where(['sku' => $barcode, 'hsn_code' => $hsn_code, 'v_id' => $v_id])->first();
			}
			if ($item_master) {
			// echo "<pre>";
			//echo  $item_master->tax->category->slab;die;
			// print_r($item_master->tax->group);die;
				if (isset($item_master->tax->group)) {
				// if($item_master->category->group)
					if ($item_master->tax->category->slab == 'NO') {
					// print_r($item_master->tax->group);die;
						$grouRate = $item_master->tax->group;
					}
					if ($item_master->tax->category->slab == 'YES') {
					//echo $mrp;
						$getSlab   = $item_master->tax->slab->where('amount_from', '<=', $mrp)->where('amount_to', '>=', $mrp)->first();
						$grouRate  = $getSlab->ratemap;
					// $getRateMap = $getSlab->ratemap;
					}


					/*Start Tax Calculation*/
					foreach ($grouRate as $key => $value) {

						if ($value->type == 'CGST') {
							$cgst = $value->rate->name;
							$cgst_amount = $value->rate->rate;
						}

						if ($value->type == 'SGST') {
							$sgst = $value->rate->name;
							$sgst_amount = $value->rate->rate;
						}

						if ($value->type == 'IGST') {
							$igst = $value->rate->name;
							$igst_amount = $value->rate->rate;
						}

						if ($value->type == 'CESS') {
							$cess        = $value->rate->name;
							$cess_amount = $value->rate->rate;
						}
					}

				//echo $cgst_amount.' - '.$sgst_amount.' - '.$igst_amount.' - '.$cess_amount;

					if ($qty > 0) {

						$mrp  = round($mrp * $qty, 2);

					$slab_cgst_amount = $this->calculatePercentageAmt($cgst_amount, $mrp);  //$mrp / ( 100 + $cgst_amount + $sgst_amount + $cess_amount ) * $cgst_amount;
					$slab_sgst_amount = $this->calculatePercentageAmt($sgst_amount, $mrp); //$mrp / ( 100 + $cgst_amount + $sgst_amount + $cess_amount ) * $sgst_amount;
					$slab_cess_amount = $this->calculatePercentageAmt($cess_amount, $mrp); // $mrp / ( 100 + $cgst_amount + $sgst_amount + $cess_amount ) * $cess_amount;
					$slab_igst_amount = $this->calculatePercentageAmt($igst_amount, $mrp);

					$cgst           = $cgst_amount;
					$sgst           = $sgst_amount;
					$igst           = $igst_amount;
					$cess           = $cess_amount;

					$cgst_amount = $slab_cgst_amount;
					$sgst_amount = $slab_sgst_amount;
					$igst_amount = $slab_igst_amount;
					$cess_amount = $this->formatValue($slab_cess_amount);

					$tax_amount  = $cgst_amount + $sgst_amount + $igst_amount + $cess_amount;

					$tax_amount  = $this->formatValue($tax_amount);
					$taxable_amount = floatval($mrp) - floatval($tax_amount);
					$taxable_amount = $this->formatValue($taxable_amount);
					$total          = $taxable_amount + $tax_amount;
					$tax_name       = $item_master->tax->category->group->name;
				}

				/*End Tax Calculation*/
			}

			$tax_type = $item_master->vendorItem->tax_type;
		}

		$data = [
			'barcode'   => $barcode,
			'hsn'       => $hsn_code,
			'cgst'      => $cgst,
			'sgst'      => $sgst,
			'igst'      => $igst,
			'cess'      => $cess,
			'cgstamt'   => (string) $cgst_amount,
			'sgstamt'   => (string) $sgst_amount,
			'igstamt'   => (string) $igst_amount,
			'cessamt'   => (string) $slab_cess_amount,
			'netamt'    => $mrp * $qty,
			'taxable'   => (string) $taxable_amount,
			'tax'       => (string) $tax_amount,
			'total'     => $total * $qty,
			'tax_name'  => $tax_name,
			'tax_type'  => $tax_type
		];
		//dd($data);
		return $data;
	}	// End of taxCal

	public function returnedSavePayment(Request $request)
	{
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
		$payment_gateway_device_type = '';
		$print_url = null;
		$vu_id = $request->vu_id;
		$role = VendorRoleUserMapping::select('role_id')->where('user_id', $vu_id)->first();
		$role_id  = $role->role_id;
		$payment_gateway_status = '';
		$invoice_seq   =null;

		if($request->get('credit_issue') == 'cash'){
            $credit_issue = $request->get('credit_issue');
            $refund_mode = 'Cash Refund';
        }else if($request->get('credit_issue') == 'voucher'){
            $credit_issue = $request->get('credit_issue');
            $refund_mode = 'Store Credit';
        }
        else{
            $credit_issue = 'voucher';
            $refund_mode = 'Store Credit';
        }

		if($request->has('payment_gateway_status')){
			$payment_gateway_status = $request->payment_gateway_status ;
		}

		if($request->has('payment_gateway_device_type')){
			$payment_gateway_device_type = $request->payment_gateway_device_type;
		}
        
        if($request->has('invoice_seq')){
         $invoice_seq =   $request->invoice_seq;
        }
       
		$sParams = ['v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $user_id, 'role_id' => $role_id, 'trans_from' => $trans_from];

		$orders = Order::where('order_id', $order_id)->first();

		// Customer seat and hall information
		$customer_details = DB::table('customer_auth')->select('seat_no', 'hall_no')->where('c_id', $c_id)->first();
		$seat_no = $customer_details->seat_no;
		$hall_no = $customer_details->hall_no;
		// END Customer seat and hall information
		$current_date = date('Y-m-d'); 

		$settlementSession = SettlementSession::select('id')->where(['v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id ])->orderBy('id','desc')->first();
		if($settlementSession){
			$session_id = $settlementSession->id;
		}
		$stores       = Store::find($store_id);
		$short_code   = $stores->short_code;

		$userDetail   = User::find($c_id);
		$userDetail = '';
		$userDetail = User::where('c_id', $c_id)->first();//find($c_id);
		if($userDetail->first_name == "Dummy"){
			$userDetail->first_name='';
			$userDetail->last_name='';
			$userDetail->mobile='';
			$userDetail->email='';
			$userDetail->gender='';
			$userDetail->address='';
			$userDetail->address='';
			$userDetail->customer_phone_code='';
		}else{
			$userDetail->customer_phone_code='+91';
		}
		// dd($userDetail);

		$totalPaymentAmount = format_number($orders->remaining_payment);
		// $totalOrderCal = format_number($orders->total_payment);
		// Check Total Payment

		if($request->payment_gateway_type == 'VOUCHER') {
			if(format_number($amount) > format_number($orders->total)) {
				$totalPaymentAmount = format_number($amount);
			}
		}

		// dd(format_number($orders->total_payment));
		if (format_number($amount) > $totalPaymentAmount) {
			return response()->json(['status' => 'fail', 'message' => 'Paid amount is greater than invoice total'], 200);
		} else {
			// $totalPaymentAmount = $orders->total_payment;
			// dd($totalPaymentAmount);
			if (format_number($totalPaymentAmount) == format_number($amount)) {
				$payment_type = 'full';
			} else {
				$payment_type = 'partial';
			}
		}

		// if ($orders->total == $amount) {
		// 	$payment_type = 'full';
		// } else {
		// 	$totalPaymentAmount = $orders->total - $orders->total_payment;
		// 	if ($totalPaymentAmount == $amount) {
		// 		$payment_type = 'full';
		// 	} else {
		// 		$payment_type = 'partial';
		// 	}
		// }

		$remark = '';
		if ($orders && $request->has('remark')) {
			$orders->remark = $request->remark;
			$orders->save();
		}
		//for terminal
		$udidtoken = '';
		if ($request->has('udidtoken')) {
			$udidtoken    = $request->udidtoken;
			$terminalInfo = CashRegister::where('udidtoken',$udidtoken)->first(); 
		}
		if ($request->has('transaction_sub_type')) {
			$orders->transaction_sub_type = $request->transaction_sub_type;

			if ($request->transaction_sub_type == 'lay_by') {
				$payments = Payment::where('v_id', $orders->v_id)->where('store_id', $orders->store_id)->where('order_id', $orders->order_id)->where('status', 'success')->get();

				$amount_paid = (float) $payments->sum('amount');

				if ($request->lay_by_total > 0) {
					$lay_by_total_from_order = (float) $orders->lay_by_total;
					if ($amount_paid > 0 && $lay_by_total_from_order == 0) {
						$lay_by_total_from_order = $amount_paid;
					}
					$orders->lay_by_total = $lay_by_total_from_order + (float) $request->lay_by_total;
				}

				if ($request->has('lay_by_remark')) {
					$orders->lay_by_remark = $request->lay_by_remark;
				}
			}

			$orders->save();
		}

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

		$vendorS = new VendorSettingController;
		// Checking Opening balance has entered or not if payment is through cash
		if ($trans_from != 'ANDROID_KIOSK' && $trans_from != 'IOS_KIOSK') {

			if ($vu_id > 0 && $payment_gateway_type == 'CASH') {
				$open_sesssion_compulsory = $vendorS->getSettlementOpenSessionFunction($sParams);
				if ($open_sesssion_compulsory->status == '1') {
					$vendorSett = new \App\Http\Controllers\VendorSettlementController;
					$response = $vendorSett->opening_balance_status($request);
					if ($response) {
						return $response;
					}
				}
			}
		}

		if ($payment_gateway_type == 'VOUCHER') {
			if (!$t_order_id) {
				// $t_order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
				$t_order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
				$t_order_id = $t_order_id + 1;
			}
			if($request->has('trans_type') && $request->trans_type == 'return'){
				$amount = $request->amount;
			}

			$gateway_response = $request->gateway_response;
			$gateway_response = json_decode($gateway_response);
			$payment_save_status = true;
		} else {
			$cash_collected = $request->cash_collected;
			$cash_return = $request->cash_return;
			$payment_save_status = true;
		}

		if ($credit_issue == 'voucher') 
		{
				$vendorS = new VendorSettingController;
				$getFeatureSetting = $vendorS->getFeatureSetting($sParams);
				$expiry_credit_note_status = $getFeatureSetting->credit_note_expiry->DEFAULT->status;
				$expiry_credit_note_date = $getFeatureSetting->credit_note_expiry->DEFAULT->options[0]->minimun_no_days->value;
				$voucher_no = generateRandomString(6);
				$today_date = date('Y-m-d H:i:s');
				if($expiry_credit_note_status == '1')
				{
					if($expiry_credit_note_date != '')
					{
						$next_date =  date('Y-m-d H:i:s' ,strtotime($expiry_credit_note_date.'days', strtotime($today_date)));
					}else{
						$next_date = date('Y-m-d H:i:s' ,strtotime('+16 years', strtotime($today_date)));
					}
				}else{
					$next_date = date('Y-m-d H:i:s' ,strtotime('+16 years', strtotime($today_date)));
				}
				$actSaleCtr = new AccountsaleController;
				$paramsCr   = array('status'=> 'Process');
                $request->merge([
                    //'order_id' => $invoice->invoice_id
                    'tr_type'     => 'Credit',
                    'user_id'  => $c_id,
                    'invoice_no' => $invoice_id,
                    'amount'   =>  $amount
                ]);
				$crDrDep = $actSaleCtr->createDepRfdRrans($request,$paramsCr);
				$vcher = DB::table('cr_dr_voucher')->insertGetId(['v_id'=> $v_id , 'store_id' => $store_id ,  'user_id'=> $c_id ,'dep_ref_trans_ref'=> $crDrDep->id ,'amount' => $amount , 'ref_id' => $order_id , 'status' => 'unused' ,'type' => 'voucher_credit' , 'voucher_no' => $voucher_no, 'effective_at' => $today_date   , 'expired_at' => $next_date ]);
                 $paramsLg    = array('trans_src_ref_id' => $crDrDep->id,'trans_src' =>'Credit-Note','applied_amount'=>$amount,'voucher_id'=>$vcher,'status'=>'APPLIED');
                $crDrLog     = $actSaleCtr->createVocherSettLog($request,$paramsLg);
				/*
				DB::table('cr_dr_voucher')->insert(['v_id'=> $v_id , 'store_id' => $store_id ,  'user_id'=> $c_id , 'amount' => $amount , 'ref_id' => $order_id , 'status' => 'unused' ,'type' => 'voucher_credit' , 'voucher_no' => $voucher_no, 'effective_at' => $today_date   , 'expired_at' => $next_date ]);
				*/

				$cust = DB::table('customer_auth')->select(['mobile','first_name','last_name','email'])->where('c_id', $c_id)->first();
				$username = "roxfortgroup@gmail.com";
				$hash = "bb7a4a8a91634f50df712fdf5c11ef6745ea735b85afc9443d23ec9ae3e8b711";
				$test = "0";
				$sender = "MZWING";
				$mobile = $cust->mobile;
				$customer_name = $cust->first_name.' '.$cust->last_name;
				$customer_email= $cust->email;

				//$otp = rand(1111,9999);
				//$user_otp_update = User::where('mobile', $mobile)->where('mobile_active', 1)->update(['otp' => $otp]);
				$numbers = "91".$mobile; 
				//$message = "You have received a Credit Note of Rs ".$total.". Your code is ".$voucher_no." Expire at ".$next_date." . Please note this is one time use only";
				$dates = explode(' ',$next_date);
				$message = "You have received a voucher of Rs ".format_number($amount).". Your code is ".$voucher_no." Expire at ".$dates[0].". one time use only";


				//$message = "You have received a Voucher of Rs 2000. Your code is 54ogpg Expire at 2018-12-12. Please note this is one time use only";
				$message = urlencode($message);
				$data = "username=".$username."&hash=".$hash."&message=".$message."&sender=".$sender."&numbers=".$numbers."&test=".$test;
				$ch = curl_init('http://api.textlocal.in/send/?');
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				$result = curl_exec($ch); 
				//dd($result);
				curl_close($ch);
			}
				$payment = new Payment;

					$payment->store_id = $store_id;
					$payment->v_id = $v_id;
					$payment->order_id = $order_id;
					$payment->user_id = $user_id;
					$payment->pay_id = $pay_id;
					$payment->amount = $amount;
					$payment->session_id =$session_id;
					$payment->terminal_id =$terminalInfo->id;
					$payment->cash_collected = $cash_collected;
					$payment->cash_return = $cash_return;
					$payment->payment_invoice_id = $invoice_id;
					$payment->bank = $bank;
					$payment->wallet = $wallet;
					$payment->vpa = $vpa;
					$payment->error_description = $error_description;
					$payment->status = $status;
					$payment->method = $request->credit_issue == 'cash' ? 'cash' : 'credit_note_issued';
            		$payment->payment_gateway_type = strtoupper($credit_issue);
					$payment->payment_gateway_type = $payment_gateway_type;
					$payment->payment_gateway_device_type = $payment_gateway_device_type;
					$payment->gateway_response = json_encode($gateway_response);
					$payment->date = date('Y-m-d');
					$payment->time = date('H:i:s');
					$payment->month = date('m');
					$payment->year = date('Y');
					$payment->save();
		
		if (!$t_order_id) {
			// $t_order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
			$t_order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
			$t_order_id = $t_order_id + 1;
		}


		$db_structure = DB::table('vendor')->select('db_structure')->where('id', $v_id)->first()->db_structure;

		$paymenDetails = null;

		if ($status == 'success') {

			/* Begin Transaction */
			DB::beginTransaction();

			try {

				// ----- Generate Order ID & Update Order status on orders and orders details -----

				$payments = Payment::where('v_id', $orders->v_id)->where('store_id', $orders->store_id)->where('order_id', $orders->order_id)->where('status', 'success')->get();
				// dd($orders->after_discount_total);
				if ($orders->after_discount_total == $payments->sum('amount')) {

					$orders->update(['status' => 'success', 'verify_status' => '1', 'verify_status_guard' => '1']);

					

				}


				OrderDetails::where('t_order_id', (string)$orders->od_id)->update(['status' => 'success']);

				// ----- Generate Invoice -----
               
                // for offline 
                 
                 	$role_id = getRoleId($vu_id);
					$params  = array('v_id'=>$v_id,'store_id'=>$store_id,'name' =>'vendor_app', 'user_id'=>$vu_id,'role_id'=>$role_id);
					$setting  = new VendorSettingController;
					$vendorAppSetting = $setting->getSetting($params)->pluck('settings')->toArray();
											 $vendorAppSettings = json_decode($vendorAppSetting[0]);
					if(isset($vendorAppSettings->offline) && $vendorAppSettings->offline->status =='1'){
				    $inc_id  = $invoice_seq;		
				    $zwing_invoice_id  = invoice_id_generate($store_id, $user_id, $trans_from,$invoice_seq,$udidtoken);
				
				    
                     

				  }else{
				  	//dd("abc");
				 $zwing_invoice_id  = invoice_id_generate($store_id, $user_id, $trans_from,$invoice_seq,$udidtoken);
				 //$zwing_invoice_id='Z2199560008';
                   //dd($zwing_invoice_id);
				// Getting incrementing id for invoice sequence
				$inc_id  = invoice_id_generate($store_id, $user_id, $trans_from,$invoice_seq,$udidtoken,'seq_id');
				}
				//dd($zwing_invoice_id);
				$custom_invoice_id = custom_invoice_id_generate(['store_id' => $store_id, 'user_id' => $user_id, 'trans_from' => $trans_from]);

				if ($payment_type == 'full') {

					// Apprtaion Discount Calculation

					if (!$orders->discounts->where('type', '!=', 'MD')->isEmpty()) {

						$totalDiscounts = $orders->discounts->whereIn('type', ['CO', 'LP'])->where('level', 'I')->all();
						$applicableItems = '';
						$allOrderItems = [];
						$discountedItems = $orders->details;
						$discountColumn = [
							'CO'	=> 'coupon_discount',
							'MD'	=> 'manual_discount',
							'LP'	=> 'lpdiscount'
						];

						foreach ($totalDiscounts as $key => $odiscount) {


							//dd($discountedItems);
							$discountedItems = discountApportionOnItems($discountedItems, $odiscount, $discountColumn[$odiscount->type]);

							// Re-calculate Tax of all items

							$vendor = Organisation::find($v_id);
							if ($vendor->db_structure == 2) {
								foreach ($discountedItems as $taxData) {
									$tdata   = json_decode($taxData->tdata);
									//$value['qty']
									$tax_total = $taxData->total;
									if ($tdata->tax_type == 'EXC') {
										$tax_total = $taxData->total - $tdata->tax;
									}
									$params  = array('barcode' => $taxData->item_id, 'qty' => 1, 's_price' => $tax_total, 'hsn_code' => $tdata->hsn, 'store_id' => $taxData->store_id, 'v_id' => $v_id);
									//dd($params);
									$cartConfig  = new CloudPos\CartController;
									$tax_details = $cartConfig->taxCal($params);

									$taxData->tax = format_number($tax_details['tax'], 2);
									$taxData->tdata = json_encode($tax_details);
								}
							} else {
								foreach ($discountedItems as $taxData) {
									$tax_code = json_decode($taxData->section_target_offers);
									$tax_code = json_decode(urldecode($tax_code->item_det));

									$GiniCartController = new GiniCartController();
									$itemTaxData = $GiniCartController->taxCal([
										'barcode' 	=> $taxData->item_id,
										'qty'		=> $taxData->qty,
										's_price'	=> $taxData->total,
										'tax_code'	=> $tax_code->INVHSNSACMAIN_CODE,
										'store_id'	=> $taxData->store_id
									]);

									$taxData->tax = format_number($itemTaxData['tax'], 2);
									$taxData->tdata = json_encode($itemTaxData);
								}
							}
						}


						//dd($discountedItems);
						// Update All Discount, Total & Tax of Orders, Order_Details

						foreach ($discountedItems as $dOrderDetails) {

							OrderDetails::find($dOrderDetails->id)->update([
								'lpdiscount'		=> $dOrderDetails->lpdiscount,
								'manual_discount'	=> $dOrderDetails->manual_discount,
								'coupon_discount'	=> $dOrderDetails->coupon_discount,
								'tax'				=> $dOrderDetails->tax,
								'total'				=> $dOrderDetails->total,
								'tdata'				=> $dOrderDetails->tdata
							]);
						}

						// Update Discount, Tax & Total 

						$orders->tax = $discountedItems->sum('tax');
						$orders->lpdiscount = $discountedItems->sum('lpdiscount');
						$orders->manual_discount = $discountedItems->sum('manual_discount');
						$orders->coupon_discount = $discountedItems->sum('coupon_discount');
						$orders->total = $orders->total - $discountedItems->sum('lpdiscount') - $discountedItems->sum('manual_discount') - $discountedItems->sum('coupon_discount');

						Order::where('order_id', $orders->order_id)->update([
							'lpdiscount'		=> $orders->lpdiscount,
							'manual_discount'	=> $orders->manual_discount,
							'coupon_discount'	=> $orders->coupon_discount,
							'tax'				=> $orders->tax,
							'total'				=> $orders->total
						]);
					}
					
					/*Lay By full payment status change */
                    if($request->has('transaction_sub_type') && $request->transaction_sub_type=='lay_by')
                    {
                    	Order::where('od_id',$orders->od_id)->update(['transaction_sub_type' => 'lay_by_processed']);
					}

                   /*end here*/

					$total_discounts   = (float)$orders->discount+(float)$orders->lpdiscount+(float)$orders->bill_buster_discount+(float)$orders->manual_discount;
					$discountDetails = ['total_discount'=>$total_discounts,'discount'=>$orders->discount,'manual_discount'=>$orders->manual_discount,'coupon_discount'=>$orders->coupon_discount,'bill_buster_discount'=>$orders->bill_buster_discount];
					$invoice = new Invoice;
					$invoice->invoice_id 		= $zwing_invoice_id;
					$invoice->custom_order_id 	= $custom_invoice_id;
					$invoice->ref_order_id 		= $orders->order_id;
					$invoice->transaction_type 	= $orders->transaction_type;
					$invoice->store_gstin 	= 	  $orders->store_gstin;
					$invoice->store_gstin_state_id 	= $orders->store_gstin_state_id;
					$invoice->v_id 				= $v_id;
					$invoice->store_id 			= $store_id;
					$invoice->user_id 			= $user_id;
					$invoice->invoice_sequence  = $inc_id;
					$invoice->qty 				= $orders->qty;
					$invoice->subtotal 			= $orders->subtotal;
					$invoice->discount 			= $orders->discount;
					$invoice->lpdiscount 		= $orders->lpdiscount;
					$invoice->coupon_discount 	= $orders->coupon_discount;
					$invoice->bill_buster_discount= $orders->bill_buster_discount; 
					$invoice->remark            = $orders->remark;
					if (isset($orders->manual_discount)) {
						$invoice->manual_discount	= $orders->manual_discount;
					}
					$invoice->tax 				= $orders->tax;
					$invoice->total 			= $orders->total;
					$invoice->trans_from 		= $trans_from;
					$invoice->vu_id 			= $vu_id;
					$invoice->date 				= date('Y-m-d');
					$invoice->time 				= date('H:i:s');
					$invoice->month 			= date('m');
					$invoice->year 				= date('Y');
					$invoice->financial_year    = getFinancialYear();
					$invoice->discount_amount   = $total_discounts;
					$invoice->discount_details  = json_encode($discountDetails);


					$invoice->session_id   		= $session_id;
					$invoice->store_short_code  = $short_code;
					$invoice->terminal_name   	= isset($terminalInfo)?$terminalInfo->name:'';
					$invoice->terminal_id   	= isset($terminalInfo)?$terminalInfo->id:'';
					
					$invoice->round_off   		= '';
					$invoice->customer_first_name     = isset($userDetail->first_name)?$userDetail->first_name:'';
					$invoice->customer_last_name     = isset($userDetail->last_name)?$userDetail->last_name:'';
					$invoice->customer_number     = isset($userDetail->mobile)?$userDetail->mobile:'';
					$invoice->customer_email     = isset($userDetail->email)?$userDetail->email:'';
					$invoice->customer_gender     = isset($userDetail->gender)?$userDetail->gender:'';
					
					$invoice->customer_address  = isset($userDetail->address)?$userDetail->address:'';
					// $invoice->customer_pincode  = isset($userDetail->address)?$userDetail->address->pincode:'';
					
					/*if customer phone code exists then update else manually update the default country code +91*/

					$invoice->customer_phone_code  = isset($userDetail->customer_phone_code)?$userDetail->customer_phone_code:'+91';
					
					$invoice->save();
					
					// for KDS only
					// $vSettings = VendorSetting::select('settings')->where('name', 'order')->where('v_id', $v_id)->first();
					// $ckds = json_decode($vSettings->settings);

					// if (isset($ckds->kds->status) && $ckds->kds->status == 1 && isset($ckds->kds->generate_on) && $ckds->kds->generate_on == "invoice") {
					// 	if ($db_structure == '2') {
					// 		if ($request->v_id == 27) {
					// 			$request->request->add(['checkPayment' => true]);
					// 			$thirdParty = $this->checkBeforePayment($request);
					// 			OrderExtra::where('order_id', $request->order_id)->update(['invoice_id' => $zwing_invoice_id, 'third_party_response' => $thirdParty]);
					// 		}
					// 	}
					// 	$kds = new Kds;
					// 	$kds->invoice_id 		= $zwing_invoice_id;
					// 	$kds->custom_order_id 	= $custom_invoice_id;
					// 	$kds->ref_order_id 		= $orders->order_id;
					// 	$kds->transaction_type 	= $orders->transaction_type;
					// 	$kds->v_id 				= $v_id;
					// 	$kds->store_id 			= $store_id;
					// 	$kds->user_id 			= $user_id;
					// 	$kds->kds_status 			= 'pending';
					// 	$kds->qty 				= $orders->qty;
					// 	$kds->subtotal 			= $orders->subtotal;
					// 	$kds->discount 			= $orders->discount;
					// 	$kds->lpdiscount 		= $orders->lpdiscount;
					// 	$kds->coupon_discount 	= $orders->coupon_discount;

					// 	if (isset($orders->manual_discount)) {
					// 		$kds->manual_discount	= $orders->manual_discount;
					// 	}
					// 	$kds->tax 				= $orders->tax;
					// 	$kds->total 			= $orders->total;
					// 	$kds->trans_from 		= $trans_from;
					// 	$kds->vu_id 			= $vu_id;
					// 	$kds->date 				= date('Y-m-d');
					// 	$kds->time 				= date('H:i:s');
					// 	$kds->month 			= date('m');
					// 	$kds->year 				= date('Y');
					// 	$kds->save();
					// }
					
					if ($db_structure != 2) {

						Payment::where('order_id', $order_id)->update(['invoice_id' => $zwing_invoice_id]);
						$payment = Payment::where('order_id', $order_id)->first();

						if ($orders->total == $payments->sum('amount')) {
							$orders->update(['status' => 'success', 'verify_status' => '1', 'verify_status_guard' => '1']);
						}

						// ------ Copy Order Details & Order Item Details to Invoice Details & Invoice Item Details ------

						$pinvoice_id = $invoice->id;

						$order_data = OrderDetails::where('t_order_id', (string)$orders->od_id)->get()->toArray();
						foreach ($order_data as $value) {
							if ($invoice->id) {
								/*Tax Detail Update Header Level*/
								$tdata   = json_decode($value->tdata);
								if($tdata->tax_name == '' || $tdata->tax_name == 'Exempted'){
									$tdata->tax_name = 'GST 0%';
								}else{
									if(strpos($tdata->tax_name, 'GST') === false) $tdata->tax_name = 'GST '.$tdata->tax_name;
								}
								$taxDetails[$tdata->tax_name][] = $tdata->tax;

								/*Tax Detail Update Header Level End */

								$value['t_order_id']  = $invoice->id;
								$save_invoice_details = $value;
								$invoice_details_data = InvoiceDetails::create($save_invoice_details);
								$order_details_data  = OrderItemDetails::where('porder_id', $value['id'])->get()->toArray();

								foreach ($order_details_data as $indvalue) {
									$save_invoice_item_details = array_add($indvalue, 'pinvoice_id', $invoice_details_data->id);
									InvoiceItemDetails::create($save_invoice_item_details);
								}


								/* Update Stock start */

								$store_db_name = $this->store_db_name($value['store_id']);
								$barcode  =  $this->getBarcode($value['barcode'], $store_db_name);

								if ($barcode) {
									$barcode  = $barcode;
								} else {
									$barcode  = $value['barcode'];
								}

								$bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $value['v_id'])->where('barcode', $barcode)->first();
								if($bar){
									$where = array('v_id' => $value['v_id'], 'vendor_sku_detail_id' => $bar->vendor_sku_detail_id);
									$Item  = VendorSku::select('sku','item_id')->where($where)->first();
								}

								if ($Item) {

									$whereStockCurrentStatus = array(
										'variant_sku' 	=> $Item->sku,
										'item_id'		=> $Item->item_id,
										'store_id'		=> $value['store_id'],
										'v_id'			=> $value['v_id']
									);

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
											$stockpoint->is_editable= '0';
											$stockpoint->save();
										}

										$whereRefPoint	= array(
											/*'item_id'	=> $Item->item_id,*/
											'v_id'		=> $value['v_id'],
											'store_id'  => $value['store_id'],
											'is_sellable'=>'1'

										);

										$ref_stock_point = StockLogs::select('stock_point_id')->where($whereRefPoint)->orderBy('id', 'desc')->first();

										if(!$ref_stock_point){
											$whereRefPoint	= array(
												/*'item_id'	=> $Item->item_id,*/
												'v_id'		=> $value['v_id'],
												'store_id'  => $value['store_id'],
												'code'		=> 'Store_Shelf'
											);
											$ref_stock_point = StockLogs::select('stock_point_id')->where($whereRefPoint)->orderBy('id', 'desc')->first();
										}

										$stockdata 	= array(
											'variant_sku'			=> $Item->sku,
											'item_id'    			=> $Item->item_id,
											'store_id'	 			=> $value['store_id'],
											'stock_type' 			=> 'OUT',
											'stock_point_id'		=> $stockpoint->id,
											'qty'		 			=> $value['qty'],
											'ref_stock_point_id'	=> $ref_stock_point->stock_point_id,
											'v_id' 					=> $value['v_id'],
											'transaction_type'      => 'SALE'
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

														/* Update Stock end */
													}
												}
												foreach ($taxDetails as $key => $value) {
													$taxDetails[$key]   = array_sum($taxDetails[$key]);
												}
												$invoice->tax_details    = json_encode($taxDetails);
												$invoice->stock_point_id = $ref_stock_point->stock_point_id;
												$invoice->save();


											} else {
												//dd($order_id);
												  Payment::where('order_id', $order_id)->update(['invoice_id' => $zwing_invoice_id]);
												 $payment = Payment::where('order_id', $order_id)->first();

												$role_id = getRoleId($vu_id);
											  $params  = array('v_id'=>$v_id,
											  'store_id'=>$store_id,
											  'name' =>'store',
											  'user_id'=>$vu_id,
											  'role_id'=>$role_id
											);
											 $setting  = new VendorSettingController;
											 $storeSetting = $setting->getSetting($params)->pluck('settings')->toArray();
											 $storeSettings = json_decode($storeSetting[0]);
											 //dd($storeSettings);
											 if(isset($storeSettings->cashmanagement) && $storeSettings->cashmanagement->DEFAULT->status=='1'){
													$terminal_id = CashRegister::select('id')->where('udidtoken',$udidtoken)->first();
													$this->cashPointTranscationUpdate($store_id,$v_id,$payment->invoice_id,$vu_id,$terminal_id->id,$invoice->transaction_type);
											 }
       	 										//end cashmangement

											}

											/* Email Functionality */
											$emailParams = array(
												'v_id'			=> $v_id,
												'store_id'		=> $store_id,
												'invoice_id'	=> $invoice->invoice_id,
												'user_id'		=> $user_id
											);
									// $this->orderEmail($emailParams);

											$print_url  =  env('API_URL') . '/order-receipt/' . $c_id . '/' . $v_id . '/' . $store_id . '/' . $zwing_invoice_id;
										} elseif ($payment_type == 'partial') {
											// For the partial 
										}

										//	For Cloud POS

										if ($db_structure == '2') {
											if ($payment_type == 'full') {

												$whereRefPoint	= array(
													'v_id'		=> $v_id,
													'store_id'  => $store_id,
													'is_sellable'=>'1'
												);
												$ref_stock_point = StockPoints::select('id')->where($whereRefPoint)->orderBy('id', 'desc')->first();

												$pinvoice_id = $invoice->id;
												$order_data = OrderDetails::where('t_order_id', (string)$orders->od_id)->get()->toArray();

												foreach ($order_data as $value) {

													/*Tax Detail Update Header Level*/
													$tdata   = json_decode($value['tdata']);
													if($tdata->tax_name == '' || $tdata->tax_name == 'Exempted'){
														$tdata->tax_name = 'GST 0%';
													}else{
														if(strpos($tdata->tax_name, 'GST') === false) $tdata->tax_name = 'GST '.$tdata->tax_name;
													}
													$taxDetails[$tdata->tax_name][] = $tdata->tax;
													/*Tax Detail Update Header Level End */

													$value['t_order_id']    = $invoice->id;
													$save_invoice_details   = $value;
													$invoice_details_data   = InvoiceDetails::create($save_invoice_details);

													if (isset($ckds->kds->status) && $ckds->kds->status == 1 && isset($ckds->kds->generate_on) && $ckds->kds->generate_on == "invoice") {
														$value['t_order_id']    = $kds->id;
														KdsDetails::create($save_invoice_details);
													}
													$order_details_data     = OrderItemDetails::where('porder_id', $value['id'])->get()->toArray();

													foreach ($order_details_data as $indvalue) {
														$save_invoice_item_details = array_add($indvalue, 'pinvoice_id', $invoice_details_data->id);
														InvoiceItemDetails::create($save_invoice_item_details);
													}

												// Copy Order Offer data to Invoice Offer data
													$invoice_offers_data  = OrderOffers::where('order_details_id', $value['id'])->get()->toArray();

													foreach ($invoice_offers_data as $odvalue) {
														$save_invoice_offers = array_add($odvalue, 'invoice_details_id', $invoice_details_data->id);
														InvoiceOffers::create($save_invoice_offers);
													}


													/*Update Stock start*/
							/*$barcode      =  $this->getBarcode($value['barcode'],$v_id);
		                        if($barcode){
		                            $barcode  = $barcode;
		                        }else{
		                            $barcode  = $value['barcode'];
		                        }*/
		                        
		                        // $params = array('v_id' => $value['v_id'], 'store_id' => $value['store_id'], 'barcode' => $value['barcode'], 'qty' => $value['qty'], 'invoice_id' => $invoice->invoice_id, 'order_id' => $invoice->ref_order_id,'transaction_type'=>'SALE','vu_id'=>$value['vu_id'],'trans_from'=>$value['trans_from']);
		                        // $this->cartconfig->updateStockQty($params);

		                        $params = array('v_id' => $value['v_id'], 'store_id' => $value['store_id'], 'barcode' => $value['barcode'], 'qty' => $value['qty'], 'invoice_id' => $invoice->invoice_id,'transaction_scr_id'=>$invoice->id, 'order_id' => $invoice->ref_order_id,'transaction_type'=>'RETURN','vu_id'=>$value['vu_id'],'trans_from'=>$value['trans_from']);
		                        $this->cartconfig->updateStockQty($params);

		                        /*Update Stock end*/
		                    }
							$taxDetails = [];

		                    foreach ($taxDetails as $key => $value) {
		                    	$taxDetails[$key]   = array_sum($taxDetails[$key]);
		                    }
		                    $invoice->tax_details    = json_encode($taxDetails);
		                    $invoice->stock_point_id = $ref_stock_point->id;
		                    $invoice->save();

		                  // DB::select("call InsertSaleItemLevelReport('".$invoice->invoice_id."')"); 

						##########################
						## Remove Cart  ##########
						##########################
		                }
		            }


		            $cart_id_list = Cart::where('order_id', $orders->o_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $user_id)->get(['cart_id']);
		            CartDetails::whereIn('cart_id', $cart_id_list)->delete();
		            CartOffers::whereIn('cart_id', $cart_id_list)->delete();
		            Cart::whereIn('cart_id', $cart_id_list)->delete();

		            $wherediscount = array('user_id' => $c_id, 'v_id' => $v_id, 'store_id' => $store_id, 'type' => 'manual_discount');
		            CartDiscount::where($wherediscount)->delete();


		            $payment_method = (isset($payment->method)) ? $payment->method : '';

		            $user = Auth::user();
				// Mail::to($user->email)->send(new OrderCreated($user,$ord,$carts,$payment_method,  $complete_path));

		            $orderC = new OrderController;
		            $getOrderResponse = ['order' => $orders, 'v_id' => $v_id, 'trans_from' => $trans_from];
		            if ($request->has('transaction_sub_type')) {
		            	$getOrderResponse['transaction_sub_type'] = $request->transaction_sub_type;
		            }
		            $order_arr = $orderC->getOrderResponse($getOrderResponse);
		            DB::commit();

		            //Invoice Created Event Is Call This event handle pushing of Pos bill to ERP

			        if(isset($invoice)){
			        	$zwingTagVId = '<ZWINGV>'.$v_id.'<EZWINGV>';
        				$zwingTagStoreId = '<ZWINGSO>'.$store_id.'<EZWINGSO>';
        				$zwingTagTranId = '<ZWINGTRAN>'.$invoice->id.'<EZWINGTRAN>';
			        	event(new InvoiceCreated([
			        		'invoice_id' => $invoice->id,
			        		'v_id' => $v_id,
			        		'store_id' => $store_id,
			        		'db_structure' => $db_structure,
			        		'type'=>'SALE',
			        		'zv_id'	=> $zwingTagVId,
			        		'zs_id'	=> $zwingTagStoreId,
			        		'zt_id' => $zwingTagTranId
				        	] 
				        	)
				        );
			        }
		        } catch (Exception $e) {
		        	DB::rollback();
		        	exit;
		        }

		        if (empty($order_arr['total_payable'])) {

				// Loyality

		        	if ($request->has('loyalty')) {
		        		$checkLoyaltyBillSubmit = LoyaltyBill::where('vendor_id', $v_id)->where('store_id', $store_id)->where('user_id', $user_id)->where('invoice_no', $zwing_invoice_id)->where('type', 'easeMyRetail')->where('is_submitted', '1')->first();
		        		if (empty($checkLoyaltyBillSubmit)) {
		        			$userInformation = User::find($c_id);
						// $invoice_id = Invoice::where('ref_order_id', $order_id)->first()->invoice_id;
		        			$loyaltyPrams = ['type' => $request->loyaltyType, 'event' => 'billPush', 'mobile' => $userInformation->mobile, 'vu_id' => $request->vu_id, 'settings' => $request->loyaltySettings, 'zw_event' => 'checkBill', 'v_id' => $v_id, 'store_id' => $store_id, 'invoice_id' => $zwing_invoice_id, 'user_id' => $user_id];
		        			    event(new Loyalty($loyaltyPrams));
		        		}
		        	}
		        }



			// Adding data in print data instead of url 
		        if ($request->has('manufacturer_name') && $request->manufacturer_name == 'SUNMI') {

		        	$request->merge([
		        		'v_id' => $v_id,
		        		'c_id' => $c_id,
		        		'store_id' => $store_id,
		        		'order_id' => $zwing_invoice_id
		        	]);
		        	$htmlData = $this->get_print_receipt($request);
		        	$html = $htmlData->getContent();
		        	$html_obj_data = json_decode($html);
		        	if ($html_obj_data->status == 'success') {
		        		$print_url = $html_obj_data->print_data;
		        	}
		        }

			// If trans form CLOUD TAB WEB

		        if ($request->trans_from == 'CLOUD_TAB_WEB' && !empty($invoice->id)) {

		        	$request->merge([
		        		'v_id' => $v_id,
		        		'c_id' => $c_id,
		        		'store_id' => $store_id,
		        		'order_id' => $zwing_invoice_id
		        	]);
		        	$htmlData = $this->get_print_receipt($request);
					$sParams  = ['v_id' => $v_id,'store_id'=>$store_id,'user_id'=>$c_id,'role_id'=>@$role_id,'trans_from' => $trans_from];
					$printSetting       = $vendorS->getPrintSetting($sParams);
					if(count($printSetting) > 0){
					  foreach($printSetting as $psetting){
						if($psetting->name == 'bill_print'){
							$bill_print_type = $psetting->width;
					  	}
					  }
					}
					if($bill_print_type == 'A4'){
						$payment->html_data = $htmlData;
					}else{ 	
			        	$html = $htmlData->getContent();
			        	$html_obj_data = json_decode($html);
			        	if ($html_obj_data->status == 'success') {
			        		$payment->html_data =  $this->get_html_structure($html_obj_data->print_data);
			        	}
		        	}



		        	$cust = User::where('c_id', $payment->user_id)->first();

		        	$payment->customer_name =  $cust->first_name . ' ' . $cust->last_name;
		        	$payment->mobile = $cust->mobile;
		        	$payment->email = $cust->email;
		        }
		        
		        /*in case of lay by giving customer information*/
		        if ($request->trans_from == 'CLOUD_TAB_WEB' && $request->transaction_sub_type == 'lay_by') {
		        	$cust = User::where('c_id', $payment->user_id)->first();
		        	$payment->customer_name =  $cust->first_name . ' ' . $cust->last_name;
		        	$payment->mobile = $cust->mobile;
		        	$payment->email = $cust->email;
		        }



		        return response()->json([
		        	'status' 			=> 'payment_save',
		        	'redirect_to_qr' 	=> true,
		        	'message' 			=> 'Save Payment',
		        	'data' 				=> $payment,
		        	'order_summary' 	=> $order_arr,
		        	'print_url' => $print_url
		        ], 200);
		    } else if ($status == 'failed' || $status == 'error') {

			// ----- Generate Order ID & Update Order status on orders and orders details -----

		    	if ($trans_from == 'CLOUD_TAB' || $trans_from == 'CLOUD_TAB_ANDROID') { } else {
		    		$orders->update(['status' => $status]);
		    		OrderDetails::where('t_order_id', (string)$orders->od_id)->update(['status' => $status]);
		    	}
		    }
		
	}

	private function calculatePercentageAmt($percentage, $amount)
	{
		if (isset($percentage)  && isset($amount)) {
			$result = ($percentage / 100) * $amount;
			return round($result, 2);
		}
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

	public function apply_employee_discount(Request $request)
	{
		$v_id = $request->v_id;
		$c_id = $request->c_id;
		$store_id = $request->store_id;

		$employee_code = $request->employee_code;
		$company_name = $request->company_name;

		$params = ['employee_code' => $employee_code, 'company_name' => $company_name];

		$employDis = new EmployeeDiscountController;
		$employee_details = $employDis->get_details($params);

		$store_db_name = get_store_db_name(['store_id' => $store_id]);
		//dd($employee_details);
		if ($employee_details) {
			$employee = DB::table($v_id . '_employee_details')->where('employee_id', $employee_details->Employee_ID)->first();

			if ($employee) {

				DB::table($v_id . '_employee_details')->update(['available_discount' => $employee_details->Available_Discount_Amount]);
			} else {
				DB::table($v_id . '_employee_details')->insert([
					'employee_id' => $employee_details->Employee_ID,
					'first_name'  => $employee_details->First_Name,
					'last_name'  => $employee_details->Last_Name,
					'designation'  => $employee_details->Designation,
					'location'  => $employee_details->Location,
					'company_name'  => $employee_details->Comp_Name,
					'available_discount' => $employee_details->Available_Discount_Amount
				]);
			}

			$params = ['v_id' => $v_id, 'store_id' => $store_id, 'c_id' => $c_id, 'employee_available_discount' => $employee_details->Available_Discount_Amount, 'employee_id' => $employee_details->Employee_ID, 'company_name' => $company_name];

			return $this->process_each_item_in_cart($params);
		} else {
			return response()->json(['status' => 'fail', 'message' => 'Unable to find the employee'], 200);
		}
	}

	public function remove_employee_discount(Request $request)
	{
		$v_id = $request->v_id;
		$c_id = $request->c_id;
		$store_id = $request->store_id;

		// $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
		$order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
		$order_id = $order_id + 1;

		$cart = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->update(['employee_id' => '', 'employee_discount' => 0.00]);

		$params = ['v_id' => $v_id, 'store_id' => $store_id, 'c_id' => $c_id];

		$this->process_each_item_in_cart($params);

		return response()->json(['status' => 'success', 'message' => 'Removed Successfully']);
	}

	private function getBarcode($code, $store_db_name)
	{
		if ($code) {
			// using icode
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

			$skuD  =  VendorSkuDetails::select('sku_code')
            ->where('v_id', $v_id)
            ->where('item_id', $item_id)
            ->where('sku', $variant_sku)
            ->first();

			StockCurrentStatus::create([
				'item_id' => $item_id,
				'variant_sku' => $variant_sku,
				'sku_code' => $skuD->sku_code,
				'store_id' => $store_id,
				'v_id' => $v_id,
				'for_date' => $date,
				'opening_qty' => $openingStock,
				'out_qty' => $quantity,
				'int_qty' => 0
			]);
		}
	}	//End of updateStockCurrentStatusOnImport

	public function add_remark(Request $request)
	{
		$cart_id = $request->cart_id;
		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$cart_remark = $request->cart_remark;

		Cart::where('cart_id', $cart_id)->update(['remark' => $cart_remark]);

		return response()->json(['status' => 'success', 'message' => 'Remark Added successfully']);
	}

	/*Email Functionality*/

	public function orderEmailRecipt(Request $request)
	{
		$v_id        = $request->v_id;
		$store_id    = $request->store_id;
		$invoice_id  = $request->invoice_id;
		$user_id     = $request->c_id;
		$email_id    = $request->email_id;
		$type        = isset($request->type)?$request->type:'';

		$return      = array();
 
		 
		if(!empty($request->type) && $request->type== 'account_deposite' || $request->type== 'adhoc_credit_note' ||  $request->type== 'refund_credit_note'   ){
        $invoiceExist =DepRfdTrans::where('doc_no', $invoice_id)->where('v_id',$v_id)->count();
        }else{
        $invoiceExist= Invoice::where('invoice_id',$invoice_id)->count();
        }


		if ($invoiceExist > 0) {
			$emailParams = array('v_id' => $v_id, 'store_id' => $store_id, 'invoice_id' => $invoice_id, 'user_id' => $user_id, 'email_id' => $email_id,'type'=>$type);
			if ($this->orderEmail($emailParams)) {
				$return = array('status' => 'email_send', 'message' => 'Invoice Send successfully');
			} else {
				$return = array('status' => 'fail', 'message' => 'Email Send failed.Please Try Again');
			}
		} else {
			$return = array('status' => 'fail', 'message' => 'Invoice Not Found');
		}
		return response()->json($return);
	}	//End of orderEmailRecipt

	public function orderEmail($parms)
	{

		$v_id        = $parms['v_id'];
		$store_id    = $parms['store_id'];
		$user_id     = $parms['user_id'];
		$invoice_id  = $parms['invoice_id'];
		$email_id    = $parms['email_id'];
		$type        = $parms['type'];
		$date        = date('Y-m-d');
		$time        = date('h:i:s');
		$time        = strtotime($time);
		
		if(!empty($type) && $type== 'account_deposite' || $type== 'adhoc_credit_note' ||  $type== 'refund_credit_note'   ){
        $invoice =DepRfdTrans::where('doc_no', $invoice_id)->where('v_id',$v_id)->first();
        $payment     = $invoice->payvia;
        }else{
		$invoice     = Invoice::where('invoice_id', $invoice_id)->with(['payments', 'details','store'])->first();
		$payment     = $invoice->payments;
		}
	
		$last_invoice_name = $invoice->invoice_name;
		if ($last_invoice_name) {
			$arr =  explode('_', $last_invoice_name);
			$id = $arr[2] + 1;
			$current_invoice_name = $date . '_' . $time . '_' . $store_id . '_' . $id . '.pdf';
		} else {
			$current_invoice_name = $date . '_' . $time . '_' . $store_id . '_1.pdf';
		}
		$bilLogo      = '';
		$bill_logo_id = 5;
		$vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status', 1)->first();
		if ($vendorImage) {
			$bilLogo = env('ADMIN_URL') . $vendorImage->path;
		}



		try {
			//$user = Auth::user();
			$user  = $invoice->user;
			//if($user->email != null && $user->email != ''){
			if ($email_id != null && $email_id != '') {

				$to     = $email_id == '' ? $user->email : $email_id;      //$mail_res['to'];
				if (!$to) {
					return false;
				}
				if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
					return false;
				}
				$html          = $this->order_receipt($user_id, $v_id, $store_id, $invoice_id, 'send_email',$type);
				//$html          = substr($html, 0, strpos($html, "<end"));
				$html         = str_replace("<end=16>", '', $html);
				$pdf           = PDF::loadHTML($html);
				//$pdf->setPaper('a4');
				$pdf->setOptions(['dpi' => 110]);
				$path          = storage_path();
				$complete_path = $path . "/app/invoices/" . $current_invoice_name;
				$pdf->setWarnings(false)->save($complete_path);
				$payment_method = $payment[0]->method;

				$cc     = []; //$mail_res['cc'];
				$bcc    = []; //$mail_res['bcc'];

				//dd($cc);
				$mailer = Mail::to($to);
				if (count($bcc) > 0) {
					$mailer->bcc($bcc);
				}
				if (count($cc) > 0) {
					$mailer->bcc($cc);
				}



				$mailer->send(new OrderCreated($user, $invoice, $invoice->details, $payment_method, $complete_path, $bilLogo,$type));
				return true;
			}
		} catch (Exception $e) {
			//Nothing doing after catching email fail
		}
	}	//End of OrderEmail

	public function cashPointTranscationUpdate($store_id,$v_id,$invoice_id,$vu_id,$terminal_id,$transaction_type=null){

		if($transaction_type=='return'){
			$transaction_type = 'RETURN';
			$transaction_behaviour = 'OUT';
		}else{
			$transaction_type = 'SALES';
			$transaction_behaviour = 'IN';
		}

		$currentTerminalCashPoint = CashPoint::where('store_id',$store_id)
									->where('v_id',$v_id)
									->where('ref_id',$terminal_id)
									->first();
		$settlement_date = date('Y-m-d');
		$settlementSession = SettlementSession::where(['v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id,'cash_register_id'=>$terminal_id])->
		orderBy('id','desc')->first();                   
		$amount =  Payment::where('v_id',$v_id)
		->where('store_id',$store_id)
		->where('invoice_id',$invoice_id)
		->where('method','cash')
		->sum('amount');
                //dd(DB::getQueryLog());                    
		if(isset($amount) && $amount > 0){
			if($transaction_type == 'RETURN'){
				$amount = -($amount);
			}
			$CashTransactionLogfrom  = new  CashTransactionLog;
			$CashTransactionLogfrom->v_id = $v_id;
			$CashTransactionLogfrom->store_id = $store_id;
			$CashTransactionLogfrom->session_id =$settlementSession->id;
			$CashTransactionLogfrom->logged_session_user_id =$vu_id;
			$CashTransactionLogfrom->cash_point_id = $currentTerminalCashPoint->id;
			$CashTransactionLogfrom->cash_point_name = $currentTerminalCashPoint->cash_point_name;
			$CashTransactionLogfrom->transaction_type =$transaction_type;
			$CashTransactionLogfrom->transaction_behaviour =$transaction_behaviour;
			$CashTransactionLogfrom->amount = $amount;
			$CashTransactionLogfrom->transaction_ref_id =$invoice_id;
			$CashTransactionLogfrom->cash_register_id =$terminal_id;
			$CashTransactionLogfrom->status = "APPROVED";
			$CashTransactionLogfrom->approved_by =$vu_id;
			$CashTransactionLogfrom->remark =$transaction_type;  
			$CashTransactionLogfrom->date =date('Y-m-d');
			$CashTransactionLogfrom->time =date('h:i:s'); 
			$CashTransactionLogfrom->save();
			$cashMan = new CashManagementController;
			$cashMan->cashPointSummaryUpdate($currentTerminalCashPoint->id,$currentTerminalCashPoint->cash_point_name,$store_id,$v_id,$settlementSession->id);
		}
	}


	public function cashPointSummaryUpdate($cash_point_id,$cash_point_name,$store_id,$v_id,$session_id){

  		//dd($cash_point_id);

		$currentdate    = date('Y-m-d');
		$cashPointSummary =CashPointSummary::where('cash_point_id',$cash_point_id)
		->where('store_id',$store_id)
		->where('v_id',$v_id)
		->where('session_id',$session_id)
		->where('date',$currentdate)
		->first();
		$opning = '';                                     
		if(empty($cashPointSummary)){

			$lastcashsummary=  CashPointSummary::where('v_id',$v_id)
			->where('store_id',$store_id)
			->where('cash_point_id',$cash_point_id)
			->where('session_id',$session_id)
			->orderby('date','DESC')
			->first();
			if(empty($lastcashsummary)){
				$opening = '0.00';
				$closing = '0.00';
			}else{
				$opening = $lastcashsummary->opening;
              //dd($opening);
				$closing  =$lastcashsummary->opening;
			}

			$todayCashSummary     =  new CashPointSummary;
			$todayCashSummary->store_id = $store_id;
			$todayCashSummary->v_id      = $v_id;
			$todayCashSummary->cash_point_id =$cash_point_id;
			$todayCashSummary->cash_point_name=$cash_point_name;
			$todayCashSummary->opening   =$opening;
			$todayCashSummary->closing      =$closing;
			$todayCashSummary->date    =date('Y-m-d');
			$todayCashSummary->time   =date('h:i:s'); 
			$todayCashSummary->save(); 

			$opning   = $todayCashSummary->opening; 

		}

		$inCash=CashTransactionLog::where('v_id',$v_id)
		->where('store_id',$store_id)
		->where('cash_point_id',$cash_point_id)
		->where('transaction_behaviour','IN')
		->where('session_id',$session_id)
		->where('date',$currentdate)
		->sum('amount');
		$outCash=CashTransactionLog::where('v_id',$v_id)
		->where('store_id',$store_id)
		->where('cash_point_id',$cash_point_id)
		->where('transaction_behaviour','OUT')
		->where('session_id',$session_id)
		->where('date',$currentdate)
		->sum('amount');
		$totalcash= CashTransactionLog::where('v_id',$v_id)
		->where('store_id',$store_id)
		->where('cash_point_id',$cash_point_id)
		->where('date',$currentdate)
		->where('session_id',$session_id)
		->sum('amount');
		$closingCash =    $totalcash;

		$cashPointSummary    = CashPointSummary::where('v_id',$v_id)
		->where('store_id',$store_id)
		->where('cash_point_id',$cash_point_id)
		->where('date',$currentdate)
		->update([
			'pay_in' => $inCash,
			'pay_out'=>$outCash,
			'closing'=>$closingCash
		]);                                        
	}

	//get gift voucher details on the basis of phone number of voucher code
	public function gift_voucher_list_for_redeem(Request $request)
	{
		$this->validate($request, [
            'v_id'          => 'required',
            'store_id'      => 'required',
            'order_id'      => 'required',
            'c_id'          => 'required',
            'redeem_by'     => 'required',
            'action_type'   => 'required|in:by_mobile,by_scan',

        ]);
		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$order_id = $request->order_id;
		$user_id = $request->c_id;
		$redeem_by = trim($request->redeem_by);
		$action_type = $request->action_type;
		$orderDetail=[];
		
		//get user current cart item list
		$orders = Order::where('order_id',$order_id)->where('v_id',$v_id)->where('store_id',$store_id)
			->where('user_id',$user_id)->first('od_id');
		if(!empty($orders)){
			$orderDetail = OrderDetails::select('barcode','sku_code','t_order_id','item_name','qty','unit_mrp',
				'discount','total')
									    ->where('t_order_id', (string)$orders->od_id)
										->where('v_id', $v_id)->where('store_id', $store_id)
		                           		->where('user_id', $user_id)->get();
		}else{
			return response()->json( ['status'=>'fail','message'=>'Cart is empty'] );
		}
		//get voucher list which is tag my mobile number
		$currentDate = Carbon::now()->format('Y-m-d');
		if($request->has('action_type') && $action_type=='by_mobile'){

			$voucher_info = GiftVoucherInvoiceDetails::leftjoin('gift_voucher','gift_voucher.gv_id','gv_invoice_details.gv_id')
							->select('gv_invoice_details.mobile','gv_invoice_details.gv_group_id','gv_invoice_details.gv_id','gv_invoice_details.voucher_code','gv_invoice_details.gift_value')
							->where(function($q) use($currentDate)
                            {
                                 $q->where('gift_voucher.valid_upto','>=',$currentDate)
                                   ->orwhereNull('gift_voucher.valid_upto');
                             })
							->where('gv_invoice_details.mobile',$redeem_by)->get();
													

		}elseif($request->has('action_type') && $action_type=='by_scan'){
			$voucher_info=GiftVoucherInvoiceDetails::leftjoin('gift_voucher','gift_voucher.gv_id','gv_invoice_details.gv_id')
													->select('gv_invoice_details.mobile','gv_invoice_details.gv_group_id','gv_invoice_details.gv_id','gv_invoice_details.voucher_code','gv_invoice_details.gift_value','gift_voucher.ref_gv_code')
													->where(function($q) use($currentDate)
		                                            {
		                                                 $q->where('gift_voucher.valid_upto','>=',$currentDate)
		                                                   ->orwhereNull('gift_voucher.valid_upto');
		                                             })
												->where('gv_invoice_details.voucher_code',$redeem_by)
												->orwhere('gift_voucher.ref_gv_code',$redeem_by)->get();
												
		} 
	    $voucher_info = collect($voucher_info)->map(function($item) {
	    				
	    				$item->product_total_amount = 0;

	    				$item->warnings_msg = '';
	    				$item->applicability = '0 Product';
	    				$item->order_details = [];
	    				$item->preset_details = [];
	    				$item->selected = false;
	    				$item->redeem_amount = '';

	    				return $item ;
	    		});		

		$voucher_count=$voucher_info->count();

		if($voucher_count!=0){
			foreach ($voucher_info as $key => $value) {
				//$code_list=['one_time','allow_partial_redemption'];
				$group_info=GiftVoucherGroup::select('is_assortment','config_preset_id')->where('gv_group_id',$value->gv_group_id)->first();
				$preset_id=$group_info->config_preset_id;
				$gv_group_id=$value->gv_group_id;
				$where=array('v_id'=>$v_id,'gv_group_id'=>$gv_group_id,'voucher_code'=>$value->voucher_code,'gv_id'=>$value->gv_id,'type'=>'DEBIT_GV');
				$voucher_used=GiftVoucherTransactionLogs::where($where)->exists();
				if($voucher_used){
					//get preset value and check voucher eligibility
					$params_for_prest = array('v_id'=>$v_id,'gv_group_id'=>$gv_group_id,'voucher_code'=>$value->voucher_code,'preset_id'=>$preset_id,'gift_value'=>$value->gift_value,'gv_id'=>$value->gv_id);

					$return_data = $this->checkPresetSettings($params_for_prest);
					
					if(isset($return_data['r_value']) && $return_data['r_value']=='continue'){
						$value->gift_value=0.00;
						//unset($voucher_info[$key]);
						continue;
					}
					if(isset($return_data['r_value']) && $return_data['r_value']=='remaining_value'){
						$value->gift_value = $return_data['remaining_amout'];
					}
				}

				$params = array('gv_group_id'=>$value->gv_group_id);
				$assortment_data = $this->getAssortmentListForGv($params);
				
				if($assortment_data['assortment_count']>0){

					$params = array('assortment_id'=>$assortment_data['assortment_id']);
					$barcode_data = $this->getAssortmentBarcodeListForGv($params);

					if($barcode_data['barcode_count']>0){
						
						$barcode_exist_count = 0;
						$item_info = [];
						foreach ($orderDetail as $key => $item_value){
							
							$barcode_exist=in_array($item_value->barcode, $barcode_data['barcode_list'] );
							if($barcode_exist){
								$barcode_exist_count++;
								$value->applicability = $barcode_exist_count.' Product';	
								$value->product_total_amount+=$item_value->total;	
								$item_info[]=array('barcode' =>$item_value->barcode,'item_name'=>$item_value->item_name,'qty'=>$item_value->qty,'unit_mrp'=>$item_value->unit_mrp,'discount'=>$item_value->discount,'net_amount'=>$item_value->total);
								$value->order_details=$item_info;
							}
						}
					}
				}else{
						//without assortment list
						$barcode_exist_count=0;
						$item_info=[];
						foreach ($orderDetail as $key => $item_value) {
							
							$barcode_exist_count++;
							$value->applicability=$barcode_exist_count.' Product';	
							$value->product_total_amount+=$item_value->total;
							$item_info[]=array('barcode' =>$item_value->barcode,'item_name'=>$item_value->item_name,'qty'=>$item_value->qty,'unit_mrp'=>$item_value->unit_mrp,'discount'=>$item_value->discount,'net_amount'=>$item_value->total);
							$value->order_details=$item_info;
							
						}
						
				}
				
				$params = array('v_id'=>$v_id,'config_preset_id'=>$preset_id);
				$pdata=$this->getPresetDetails($params);
				$value->preset_details=$pdata;

				if((float)$value->gift_value>(float)$value->product_total_amount ){
					$value->warnings_msg='Item value less than voucher value';
				}
				
			}
			$voucher_info = collect($voucher_info)->filter(function($item){
								if($item->gift_value>0){
									return $item;
								}
							})->values();
			if(count($voucher_info)>0){
				return response()->json( ['status'=>'success','message'=>'Voucher list','data'=>$voucher_info]);
			}elseif(count($voucher_info)==0 && $action_type=='by_scan'){
				return response()->json( ['status'=>'fail','message'=>'Voucher Already Used ','data'=>$voucher_info]);

			}else{
				return response()->json( ['status'=>'fail','message'=>'No voucher found ','data'=>$voucher_info]);
			}
			
			//dd($voucher_info);
		}elseif($voucher_count==0 && $action_type=='by_scan'){
			return response()->json( ['status'=>'fail','message'=>'Voucher Expired ','data'=>$voucher_info]);

		}else{
			return response()->json( ['status'=>'fail','message'=>'Voucher not found','data'=>$voucher_info]);
		}


										
	}

	public function checkPresetSettings($params){
		$gv_group_id=$params['gv_group_id'];
		$voucher_code=$params['voucher_code'];
		$v_id=$params['v_id'];
		$preset_id=$params['preset_id'];
		$gv_id=$params['gv_id'];
		$gift_value=(float)$params['gift_value'];
		

		$return_val='';
		$params['config_code']='one_time';
		$one_time_used_val=$this->getPresetConfigId($params);
		if($one_time_used_val=='Yes'){
			$return_val='continue';
			return ['r_value'=>$return_val];
		}

		$params['config_code']='allow_partial_redemption';
		$partail_redeem_val=$this->getPresetConfigId($params);
		
		if($partail_redeem_val=='No'){
			$return_val='continue';
			return ['r_value'=>$return_val];
		}elseif($partail_redeem_val=='Yes'){
			$where=array('v_id'=>$v_id,'gv_group_id'=>$gv_group_id,'voucher_code'=>$voucher_code,'gv_id'=>$gv_id);
			$redeem_amount=GiftVoucherTransactionLogs::where($where)->sum('amount');;
			$redeem_amount=(float)$redeem_amount;
			$remaining_amout=$redeem_amount;
			if($gift_value>$redeem_amount){
				$return_val='remaining_value';
				return ['r_value'=>$return_val,'remaining_amout'=>$remaining_amout];
			}elseif($gift_value==$redeem_amount){
				$return_val='continue';
				return ['r_value'=>$return_val,'remaining_amout'=>$remaining_amout];
			}else{
				$return_val='continue';
				return ['r_value'=>$return_val];
			}

		}
	}
	//get PRESET CONFIG ID
	public function getPresetConfigId($params){
		//$code_list=['one_time','allow_partial_redemption'];
		$v_id=$params['v_id'];
		$preset_id=$params['preset_id'];
		$config_code=$params['config_code'];
		$congifg_id=DB::table('gv_config_master')->select('config_id')->where('config_code',$config_code)->first()->config_id;

		$where=array('v_id'=>$v_id,'config_id'=>$congifg_id,'config_preset_id'=>$preset_id);
		$config_value=GiftVoucherConfigPresetMapping::select('config_value')->where($where)->first()->config_value;

		return $config_value;

	}

	//get assortment for gift-voucher
	public function getAssortmentListForGv($params){

		$gv_group_id=$params['gv_group_id'];
		$assortment_list=GVGroupAssortmentMapping::where('gv_group_id',$gv_group_id)->get(['assortment_id']);
		$assortment_id= $assortment_list->pluck('assortment_id');
		$assortment_count=$assortment_list->count();
		return ['assortment_count'=>$assortment_count,'assortment_id'=>$assortment_id];

	}
	//get barcode list from assortment for gift-voucher
	public function getAssortmentBarcodeListForGv($params){

		$assortment_id=$params['assortment_id'];
		$assortment_code = DB::table('pro_promo_assortment')->whereIn('id',$assortment_id)
										 ->get(['CODE']);
		$codes_id=$assortment_code->pluck('CODE');
		$assortment_barcode = DB::table('vendor_sku_assortment_mapping')->whereIn('assortment_code',$codes_id)->get(['barcode']);
		$barcode_list= $assortment_barcode->pluck('barcode')->toArray();
		$barcode_count=$assortment_barcode->count();
		return ['barcode_count'=>$barcode_count,'barcode_list'=>$barcode_list];

	}
	//get gift voucher settings
	public function getPresetDetails($params){
		$v_id=$params['v_id'];
		$presetId=$params['config_preset_id'];
		$presetDetails=   GiftVoucherConfigPresetMapping::leftjoin('gv_config_master','gv_config_master.config_id',
                                                                        'gv_config_preset_mapping.config_id')
                                                            ->Where('gv_config_preset_mapping.config_preset_id',$presetId)
                                                            ->Where('gv_config_preset_mapping.v_id',$v_id)
                                                            ->select('gv_config_master.config_name','gv_config_master.config_code','gv_config_preset_mapping.config_value')
                                                            ->orderBy('gv_config_master.config_id')
                                                            ->get();
		$pdata=[] ;                                                           
        foreach ($presetDetails as $key => $value) {
        	if($value->config_code=='one_time' ){
        		if($value->config_value=='Yes'){
                    $value->config_name='One-time use only';
                }else{
                    $value->config_name='Non one-time use';
                }
        		$pdata[$value->config_code]=$value;
        	}
        	if($value->config_code=='allow_partial_redemption' ){
        		if($value->config_value=='Yes'){
                    $value->config_name='Partial Redemption  allowed';
                }else{
                    $value->config_name='Partial Redemption not allowed';
                }
        		$pdata[$value->config_code]=$value;
        	}
        }
        return $pdata;
            
	}   

}
