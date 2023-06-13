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
use App\Model\Items\VendorSkuDetails;
use Event;
use App\Events\Loyalty;
use App\LoyaltyBill;
use App\Http\Controllers\LoyaltyController;
use App\Organisation;
use App\SyncReports;
use App\OrderExtra;
use App\Carry;
use App\Vendor;
use App\Deposit;
use App\Http\Controllers\CloudPos\CartconfigController;
use App\Http\Controllers\Ginesys\PromotionController;
use App\Http\Controllers\Ginesys\CartController as GiniCartController;
use App\OrderDiscount;

class CartController extends Controller {
	use VendorFactoryTrait;

	public function __construct() {
		$this->middleware('auth', ['except' => ['order_receipt', 'get_print_receipt','orderEmailRecipt']]);
		$this->cartconfig  = new CartconfigController;     
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

	public function add_to_cart(Request $request) {
		return $this->callMethod($request, __CLASS__, __METHOD__);
	}

	public function bulk_add_to_cart(Request $request) {
		return $this->callMethod($request, __CLASS__, __METHOD__);
	}

	public function product_qty_update(Request $request) {
		return $this->callMethod($request, __CLASS__, __METHOD__);

	}

	public function remove_product(Request $request) {
		return $this->callMethod($request, __CLASS__, __METHOD__);
	}

	public function cart_details(Request $request) {
		return $this->callMethod($request, __CLASS__, __METHOD__);
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
        if($request->has('hold_bill')){
            $hold_bill = $request->hold_bill;
            $transaction_sub_type = 'hold';
            $hold_bill = 1;
        }        
        if($request->has('transaction_sub_type')){
            $transaction_sub_type = $request->transaction_sub_type;
            $hold_bill = 1;
        }


		//Checking Opening balance has entered or not if payment is through cash
		$vendorSetting = new \App\Http\Controllers\VendorSettingController;
		$paymentTypeSettings = $vendorSetting->getPaymentTypeSetting(['v_id' => $v_id, 'trans_from' => $trans_from]);
		$cash_flag = false;
		foreach($paymentTypeSettings as $type){
			if($type->name == 'cash'){
				if($type->status == 1){
					$cash_flag = true;
				}
			}
		}

		if ( ($vu_id > 0 && $payment_gateway_type == 'CASH') || $cash_flag) {
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
			
			if ($request->has('loyalty')) {
				$userInformation = User::find($c_id);
				$loyaltyPrams = [ 'type' => $request->loyaltyType, 'event' => 'getUrl', 'mobile' => $userInformation->mobile, 'vu_id' => $request->vu_id, 'settings' => $request->loyaltySettings, 'zw_event' => 'generateUrl', 'v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $c_id, 'order_id' => $request->temp_order_id, 'billAmount' => $request->total ];
				$loyaltyCon = new LoyaltyController;
				$loyaltyUrl = $loyaltyCon->index($loyaltyPrams);

				$orderDetails = Order::where('order_id', $request->temp_order_id)->first();

				$orderC = new OrderController;
				$order_arr = $orderC->getOrderResponse(['order' => $orderDetails , 'v_id' => $v_id , 'trans_from' => $trans_from ]) ;

				$res =  ['status' => 'proceed_to_payment', 'message' => 'Proceed to Payment', 'data' => $orderDetails , 'order_summary' => $order_arr, 'loyalty_url' => $loyaltyUrl->response['url'] ];

				if($request->has('response') && $request->response == 'ARRAY') {	
					return $res;
				} else {
					return response()->json($res, 200);
				}

			}

		}

		if ($request->payment_gateway_type == 'COUPON') {
			
			if ($request->has('loyalty')) {
				$userInformation = User::find($c_id);
				$loyaltyPrams = [ 'type' => $request->loyaltyType, 'event' => 'getCouponUrl', 'mobile' => $userInformation->mobile, 'vu_id' => $request->vu_id, 'settings' => $request->loyaltySettings, 'zw_event' => 'generateCouponUrl', 'v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $c_id, 'order_id' => $request->temp_order_id, 'billAmount' => $request->total ];
				$loyaltyCon = new LoyaltyController;
				$loyaltyUrl = $loyaltyCon->index($loyaltyPrams);

				$orderDetails = Order::where('order_id', $request->temp_order_id)->first();

				$orderC = new OrderController;
				$order_arr = $orderC->getOrderResponse(['order' => $orderDetails , 'v_id' => $v_id , 'trans_from' => $trans_from ]) ;

				$res =  ['status' => 'proceed_to_payment', 'message' => 'Proceed to Payment', 'data' => $orderDetails , 'order_summary' => $order_arr, 'coupon_url' => $loyaltyUrl->response['url'] ];

				if($request->has('response') && $request->response == 'ARRAY') {	
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
		if($request->has('manual_discount')){
			$order->manual_discount = $request->manual_discount;
			$order->md_added_by = $request->vu_id;
		}

		$order->bill_buster_discount = $bill_buster_discount;
		$order->tax = $tax;
		$order->total = (float)$total + (float)$pay_by_voucher;
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
		$or_qty = 0;
		foreach ($cart_data as $value) {
			$cart_details_data  = CartDetails::where('cart_id', $value['cart_id'])->get()->toArray();
			$save_order_details = array_except($value, ['cart_id']);
			$save_order_details = array_add($value, 't_order_id', $porder_id);
			$order_details      = OrderDetails::create($save_order_details);
			foreach ($cart_details_data as $cdvalue) {
				$save_order_item_details = array_add($cdvalue, 'porder_id', $order_details->id);
				OrderItemDetails::create($save_order_item_details);
			}
			$or_qty += $value['qty'];
			//Deleting cart if hold bill is true
			if($transaction_sub_type == 'hold'){

				CartDetails::where('cart_id', $value['cart_id'])->delete();
				CartOffers::where('cart_id', $value['cart_id'])->delete();
				Cart::where('cart_id', $value['cart_id'])->delete();
			}
		}

		//$order->qty = $or_qty;
		//$order->save();

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
		$order_arr = $orderC->getOrderResponse(['order' => $order , 'v_id' => $v_id , 'trans_from' => $trans_from ]) ;

		$order = array_add($order, 'order_id', $porder_id);

	
			$res =  ['status' => 'proceed_to_payment', 'message' => 'Proceed to Payment', 'data' => $order , 'order_summary' => $order_arr ];
		
		if($request->has('response') && $request->response == 'ARRAY') {	
			return $res;
		} else {
			return response()->json($res, 200);
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
		$totalLPDiscount = null;null;
		$print_url = null;

		$orders = Order::where('order_id', $order_id)->first();

		// Customer seat and hall information
        $customer_details = DB::table('customer_auth')->select('seat_no','hall_no')->where('c_id',$c_id)->first();
        $seat_no = $customer_details->seat_no;
        $hall_no = $customer_details->hall_no;
        // END Customer seat and hall information

        // Check Total Payment
        
        // dd(format_number($orders->total_payment));
        if (format_number($amount) > format_number($orders->total_payment)) {
        	return response()->json([ 'status' => 'validation', 'message' => 'Paid amount is greater than invoice total' ], 200);
        } else {
        	$totalPaymentAmount = $orders->total_payment;
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

		$remark ='';
		if($orders && $request->has('remark')){
			$orders->remark = $request->remark;
			$orders->save();
		}

		if($request->has('transaction_sub_type')) {
            $orders->transaction_sub_type = $request->transaction_sub_type;
            
            if($request->transaction_sub_type == 'lay_by'){
            	$payments = Payment::where('v_id', $orders->v_id)->where('store_id', $orders->store_id)->where('order_id', $orders->order_id)->where('status','success')->get();

            	$amount_paid = (float)$payments->sum('amount');

            	if($request->lay_by_total > 0){
            		$lay_by_total_from_order = (float)$orders->lay_by_total;
            		if($amount_paid > 0 && $lay_by_total_from_order == 0){
            			$lay_by_total_from_order = $amount_paid;
            		}
	            	$orders->lay_by_total = $lay_by_total_from_order + (float)$request->lay_by_total;
	            }

	            if($request->has('lay_by_remark')){
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

		// Checking Opening balance has entered or not if payment is through cash
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

			return $this->pointRedemption($request);

		} elseif ($payment_gateway_type == 'COUPON') {

			return $this->couponRedemption($request);

		} elseif ($payment_gateway_type == 'PAYTM_OFFLINE' || $payment_gateway_type == 'PAYTM') {
			
			$method = 'paytm';
			if($payment_gateway_type == 'PAYTM'){
				$gateway_response = $request->gateway_response;
				$gateway_response = json_decode($gateway_response);
			}
			$payment_save_status = true;

		} elseif ($payment_gateway_type == 'GOOGLE_TEZ_OFFLINE' || $payment_gateway_type == 'GOOGLE_TEZ') {
			
			$method = 'google_tez';
			if($payment_gateway_type == 'GOOGLE_TEZ'){
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

		if($request->has('transaction_sub_type') && $request->transaction_sub_type == 'lay_by') 
		{
			$deposit =  Deposit::create(['v_id' => $v_id , 'store_id' => $store_id, 'c_id' => $c_id, 'amount' => $amount , 'type' => 'ORDER' , 'ref_id' => $order_id ]);
		}

		if(!$t_order_id)
		{
            $t_order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
            $t_order_id = $t_order_id + 1;
        }

		$vSetting = new VendorSettingController;
        $voucherSetting = $vSetting->getVoucherSetting(['v_id' => $v_id , 'trans_from' => $trans_from]);
        $voucherUsedType = null;
        if(isset($voucherSetting->status) &&  $voucherSetting->status == 1) 
        {
            $vouchers = DB::table('voucher_applied')->select('id','voucher_id','applied_amount')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('order_id', $t_order_id)->get();
            $voucherUsedType = $voucherSetting->used_type;
            foreach($vouchers as $voucher) {
                $totalVoucher = 0;
                $vou = DB::table('voucher')->select('amount','voucher_no','expired_at')->where('id', $voucher->voucher_id)->first();
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

                        //This is added for sending sms for partial voucher
                        $cust = User::select('mobile')->where('c_id', $c_id)->first();
                        $smsC = new SmsController;
                        $expired_at = explode(' ',$vou->expired_at);
                        $smsParams = ['mobile' => $cust->mobile, 'voucher_amount' => ($vou->amount - $totalAppliedAmount) , 'voucher_no' => $vou->voucher_no, 'expiry_date' => $expired_at[0],'v_id' => $v_id ,'store_id' => $store_id];
                        $smsResponse = $smsC->send_voucher($smsParams);
                    }
                }else{

                    DB::table('voucher')->where('id', $voucher->voucher_id)->update(['status' => 'used']);
                }

                DB::table('voucher_applied')->where('id', $voucher->id)->update(['status' => 'APPLIED' ]);
            }
        } else {
            $vouchers = DB::table('voucher_applied')->select('voucher_id')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('order_id', $t_order_id)->get();

            foreach ($vouchers as $voucher) {
                DB::table('voucher')->where('id', $voucher->voucher_id)->update(['status' => 'used']);
            }
        }

        $db_structure = DB::table('vendor')->select('db_structure')->where('id',$v_id)->first()->db_structure;

        $paymenDetails = null;

		if ($status == 'success') {
			
			/* Begin Transaction */
            DB::beginTransaction();

            try {

			// ----- Generate Order ID & Update Order status on orders and orders details -----
			
			$payments = Payment::where('v_id', $orders->v_id)->where('store_id', $orders->store_id)->where('order_id', $orders->order_id)->where('status', 'success')->get();
			// dd($orders->after_discount_total);
			if($orders->after_discount_total == $payments->sum('amount')) {

				$orders->update([ 'status' => 'success', 'verify_status' => '1', 'verify_status_guard' => '1']);

				// if($request->has('transaction_sub_type') && $request->transaction_sub_type == 'lay_by'){
				// 	$orders->update(['transaction_sub_type' => 'lay_by_processed']);
				// }

			}

			OrderDetails::where('t_order_id', $orders->od_id)->update([ 'status' => 'success' ]);	

			// ----- Generate Invoice -----

			$zwing_invoice_id  = invoice_id_generate($store_id, $user_id, $trans_from);
			$custom_invoice_id = custom_invoice_id_generate(['store_id' => $store_id, 'user_id' => $user_id, 'trans_from' => $trans_from]);

			if ($payment_type == 'full') {

				// Apprtaion Discount Calculation

				if (!$orders->discounts->isEmpty()) {
			
					$totalDiscounts = $orders->discounts->whereIn('type', ['CO','LP'])->where('level', 'I')->all();
					$applicableItems = '';
					$allOrderItems = [];
					$discountedItems = $orders->details;
					$discountColumn = [
						'CO'	=> 'coupon_discount',
						'MD'	=> 'manual_discount',
						'LP'	=> 'lpdiscount'
					];

					foreach ($totalDiscounts as $key => $odiscount) {

						$discountedItems = discountApportionOnItems($discountedItems, $odiscount, $discountColumn[$odiscount->type]);

						// Re-calculate Tax of all items

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
				
				$invoice = new Invoice;
				$invoice->invoice_id 		= $zwing_invoice_id;
				$invoice->custom_order_id 	= $custom_invoice_id;
				$invoice->ref_order_id 		= $orders->order_id;
				$invoice->transaction_type 	= $orders->transaction_type;
				$invoice->v_id 				= $v_id;
				$invoice->store_id 			= $store_id;
				$invoice->user_id 			= $user_id;
				//$invoice->qty 				= $orders->qty;
				$invoice->subtotal 			= $orders->subtotal;
				$invoice->discount 			= $orders->discount;
				$invoice->lpdiscount 		= $orders->lpdiscount;
				$invoice->coupon_discount 	= $orders->coupon_discount;
				if(isset($orders->manual_discount) ){
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
				$invoice->save();
				

				if($db_structure != 2) {

					Payment::where('order_id', $order_id)->update([ 'invoice_id' => $zwing_invoice_id  ]);
					$payment = Payment::where('order_id', $order_id)->first();

					if($orders->total == $payments->sum('amount'))
					{
						$orders->update([ 'status' => 'success', 'verify_status' => '1', 'verify_status_guard' => '1' ]);
					}

					// ------ Copy Order Details & Order Item Details to Invoice Details & Invoice Item Details ------

					$pinvoice_id = $invoice->id; 

					$order_data = OrderDetails::where('t_order_id', $orders->od_id)->get()->toArray();

					foreach ($order_data as $value) {

						if($invoice->id) {

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
							$barcode  =  $this->getBarcode($value['barcode'],$store_db_name);
							
							if($barcode) {
								$barcode  = $barcode;
							} else {
								$barcode  = $value['barcode'];
							}

							$where = array('v_id'=>$value['v_id'],'barcode'=>$barcode);
							$Item  = VendorSkuDetails::where($where)->first();

							if($Item) {
									 
								$whereStockCurrentStatus = array(
									'variant_sku' 	=> $Item->sku,
									'item_id'		=> $Item->item_id,
									'store_id'		=> $value['store_id'],
									'v_id'			=> $value['v_id']);

								$stockCurrentStatus = StockCurrentStatus::where($whereStockCurrentStatus)->orderBy('id','desc')->first();

								if($stockCurrentStatus)	{

								  	$this->updateStockCurrentStatus($Item->sku, $Item->item_id, $value['qty'],$value['v_id'],$value['store_id']);

									/*$stockCurrentStatus->out_qty = $stockCurrentStatus->out_qty+$value['qty'];
									$stockCurrentStatus->save();*/
									
									$stockpointwhere  = array('v_id'=>$value['v_id'],'store_id'=>$value['store_id'],'name'=>'SALE');
									$stockpoint = StockPoints::where($stockpointwhere)->first();
									if(!$stockpoint) {
										$stockpoint = new StockPoints;
										$stockpoint->v_id 		= $value['v_id'];
										$stockpoint->store_id 	= $value['store_id'];
										$stockpoint->name 		= 'SALE';
										$stockpoint->code 		= 'SL001';
										$stockpoint->save(); 
									}

									$whereRefPoint	= array(
										'item_id'	=> $Item->item_id,
										'v_id'		=> $value['v_id'],
										'store_id'  => $value['store_id']);

									$ref_stock_point = StockLogs::select('stock_point_id')->where($whereRefPoint)->orderBy('id','desc')->first();

									$stockdata 	= array(
										'variant_sku'			=> $Item->sku,
										'item_id'    			=> $Item->item_id,
										'store_id'	 			=> $value['store_id'],
										'stock_type' 			=> 'OUT',
										'stock_point_id'		=> $stockpoint->id,
										'qty'		 			=> $value['qty'],
										'ref_stock_point_id'	=> $ref_stock_point->stock_point_id,
										'v_id' 					=>  $value['v_id']);

									StockLogs::create($stockdata);

									$stocktransdata 	= array(
										'variant_sku'=> $Item->sku,
										'item_id'    => $Item->item_id,
										'store_id'	 => $value['store_id'],
										'stock_type' => 'OUT',
										'stock_point_id' => $stockpoint->id,
										'qty'		 => $value['qty'],
										'v_id' 		=>  $value['v_id'],
										'order_id'  =>  $orders->od_id,
										'invoice_no'=>  $invoice->invoice_id);

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
				} else {
					Payment::where('order_id', $order_id)->update([ 'invoice_id' => $zwing_invoice_id  ]);
					$payment->update([ 'invoice_id' => $zwing_invoice_id ]);
				}

				/* Email Functionality */
		        $emailParams = array(
		        	'v_id'			=> $v_id,
		        	'store_id'		=> $store_id,
		        	'invoice_id'	=> $invoice->invoice_id,
		        	'user_id'		=> $user_id);
		        // $this->orderEmail($emailParams);

			  	$print_url  =  env('API_URL').'/order-receipt/'.$c_id.'/'.$v_id.'/'.$store_id.'/'.$zwing_invoice_id;

			} elseif ($payment_type == 'partial') {
				// For the partial 
			}

			//	For Cloud POS

			if($db_structure == '2') {
				if ($payment_type == 'full') {
					$pinvoice_id = $invoice->id;
		            $order_data = OrderDetails::where('t_order_id', $orders->od_id)->get()->toArray();

		            foreach ($order_data as $value) {
		                
		                $value['t_order_id']    = $invoice->id; 
		                $save_invoice_details   = $value;
		                $invoice_details_data   = InvoiceDetails::create($save_invoice_details);
		                $order_details_data     = OrderItemDetails::where('porder_id', $value['id'])->get()->toArray();

		                foreach ($order_details_data as $indvalue) {
		                    $save_invoice_item_details = array_add($indvalue, 'pinvoice_id', $invoice_details_data->id);
		                    InvoiceItemDetails::create($save_invoice_item_details);
		                }


		                    /*Update Stock start*/
		                        /*$barcode      =  $this->getBarcode($value['barcode'],$v_id);
		                        if($barcode){
		                            $barcode  = $barcode;
		                        }else{
		                            $barcode  = $value['barcode'];
		                        }*/
		                         $params = array('v_id'=>$value['v_id'],'store_id'=>$value['store_id'],'barcode'=>$value['barcode'],'qty'=>$value['qty'],'invoice_id'=>$invoice->invoice_id,'order_id'=>$invoice->ref_order_id);
		                         $this->cartconfig->updateStockQty($params);

		                    /*Update Stock end*/
		 
		            }
		            ##########################
		            ## Remove Cart  ##########
		            ##########################
				}
			}
			

			$cart_id_list = Cart::where('order_id', $orders->o_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $user_id)->get(['cart_id']);
			CartDetails::whereIn('cart_id', $cart_id_list)->delete();
			CartOffers::whereIn('cart_id', $cart_id_list)->delete();
			Cart::whereIn('cart_id', $cart_id_list)->delete();

			$payment_method = (isset($payment->method)) ? $payment->method : '';

			$user = Auth::user();
			// Mail::to($user->email)->send(new OrderCreated($user,$ord,$carts,$payment_method,  $complete_path));

			$orderC = new OrderController;
			$getOrderResponse = ['order' => $orders , 'v_id' => $v_id , 'trans_from' => $trans_from ];
			if($request->has('transaction_sub_type')){
				$getOrderResponse['transaction_sub_type'] = $request->transaction_sub_type;
			}
			$order_arr = $orderC->getOrderResponse($getOrderResponse) ;

			DB::commit();
            } catch(Exception $e) {
              DB::rollback();
              exit;
            }

			if (empty($order_arr['total_payable'])) {
				
				// Loyality

				if($request->has('loyalty')) {
					$checkLoyaltyBillSubmit = LoyaltyBill::where('vendor_id', $v_id)->where('store_id', $store_id)->where('user_id', $user_id)->where('invoice_no', $zwing_invoice_id)->where('type', 'easeMyRetail')->where('is_submitted', '1')->first();
					if (empty($checkLoyaltyBillSubmit)) {
						$userInformation = User::find($c_id);
						// $invoice_id = Invoice::where('ref_order_id', $order_id)->first()->invoice_id;
						$loyaltyPrams = [ 'type' => $request->loyaltyType, 'event' => 'billPush', 'mobile' => $userInformation->mobile, 'vu_id' => $request->vu_id, 'settings' => $request->loyaltySettings, 'zw_event' => 'checkBill', 'v_id' => $v_id, 'store_id' => $store_id, 'invoice_id' => $zwing_invoice_id, 'user_id' => $user_id ];
						Event::fire(new Loyalty($loyaltyPrams));
					}
				}

			}

			return response()->json([
				'status' 			=> 'payment_save',
				'redirect_to_qr' 	=> true,
				'message' 			=> 'Save Payment',
				'data' 				=> $payment,
				'order_summary' 	=> $order_arr,
				'print_url'=>$print_url
				], 200);


		} else if($status == 'failed' || $status == 'error') {

			// ----- Generate Order ID & Update Order status on orders and orders details -----

			if($trans_from == 'CLOUD_TAB' || $trans_from == 'CLOUD_TAB_ANDROID') {

			} else {
				$orders->update([ 'status' => $status ]);
				OrderDetails::where('t_order_id', $orders->od_id)->update([ 'status' => $status ]);
			}
		
		}
	}

	public function pointRedemption(Request $request)
	{
		$pointDetails = json_decode($request->gateway_response, true);
		$store = Store::find($request->store_id);
		$order = Order::where('order_id', $request->order_id)->first();
		$finalItemList = [];

		// Add all item in Array

		foreach ($order->details as $key => $value) {
			$finalItemList[] = [ 'id' => $value->id, 'barcode' => $value->item_id, 'subtotal' => $value->subtotal, 'discount' => $value->discount, 'total' => $value->total, 'lpdiscount' => $value->lpdiscount, 'section_target_offers' => $value->section_target_offers, 'qty' => $value->qty, 'store_id' => $value->store_id ];
		}

		$finalItemList = collect($finalItemList);

		$orderDiscount = OrderDiscount::create([
			'v_id'		=> $order->v_id,
			'store_id'	=> $order->store_id,
			'order_id'	=> $order->order_id,
			'name'		=> 'EMR Loyalty',
			'type'		=> 'LP',
			'level'		=> 'I',
			'amount'	=> $pointDetails['redeemedvalue'],
			'basis'		=> 'A',
			'factor'	=> $pointDetails['redeemedvalue'],
			'item_list'	=> json_encode($finalItemList->pluck('barcode')),
			'response'	=> $request->gateway_response
		]);

		$orderC = new OrderController;
		$order_arr = $orderC->getOrderResponse(['order' => $order , 'v_id' => $request->v_id , 'trans_from' => $request->trans_from ]) ;

		return response()->json(['status' => 'payment_save', 'redirect_to_qr' => true, 'message' => 'Save Payment', 'data' => $orderDiscount , 'order_summary' => $order_arr ], 200);
	}

	public function couponRedemption(Request $request)
	{
		$couponDetails = json_decode($request->gateway_response, true);
		$store = Store::find($request->store_id);
		$order = Order::where('order_id', $request->order_id)->first();
		$finalItemList = [];
		$minPurchaseValue = 0;
		$totalAmount = 0;
		$isApplyCoupon = false;
		$discountAmount = 0;
		$orderData = [];
		$orderTotalAmount = $totalTax = 0;

		// Check Offer Code Exists or Not

		if ($couponDetails['OFFERCODE'] != null) {
			
			// Check Coupon Code Exists

			$offerDetails = DB::table($store->store_db_name.'.psite_couponoffer as pco')
								->select('pco.NAME','pco.ALLOW_RED_ON_PROMOITEM','pco.MINIMUM_RED_VALUE','pco.CODE')
								->join($store->store_db_name.'.psite_coupon_assign as pca', 'pca.COUPONOFFER_CODE', 'pco.CODE')
								->where('pca.ADMSITE_CODE', $store->mapping_store_id)
								->where('pco.SHORTCODE', $couponDetails['OFFERCODE'])
								->first();

			if (!empty($offerDetails)) {
				
				// Check Assorted Item is defined or not

				$assortmentList = DB::table($store->store_db_name.'.psite_coupon_assrt')->where('COUPONOFFER_CODE', $offerDetails->CODE)->get();

				if (!$assortmentList->isEmpty()) {
					
					foreach ($order->details as $key => $value) {

						$promoC = new PromotionController;
						$item = DB::table($store->store_db_name.'.invitem')->select('GRPCODE', 'INVARTICLE_CODE','BARCODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE', 'LISTED_MRP', 'DESC1', 'DESC2', 'DESC3', 'DESC4', 'DESC5', 'DESC5', 'DESC6')->where('ICODE', $value->item_id)->first();
						$params = [ 'item' => $item, 'store_id' => $store->store_id, 'is_coupon' => 1, 'assortment_list' => $assortmentList ];
						$checkAssrtItem = $promoC->index($params);

						if ($checkAssrtItem == true) {
							$finalItemList[] = [ 'id' => $value->id, 'barcode' => $value->item_id, 'subtotal' => $value->subtotal, 'discount' => $value->discount, 'total' => $value->total, 'lpdiscount' => $value->lpdiscount, 'section_target_offers' => $value->section_target_offers, 'qty' => $value->qty, 'store_id' => $value->store_id ];
						}
					}

				} else {

					// If any assortment is not tag in coupon

					foreach ($order->details as $key => $value) {
						$finalItemList[] = [ 'id' => $value->id, 'barcode' => $value->item_id, 'subtotal' => $value->subtotal, 'discount' => $value->discount, 'total' => $value->total, 'lpdiscount' => $value->lpdiscount, 'section_target_offers' => $value->section_target_offers, 'qty' => $value->qty, 'store_id' => $value->store_id ];
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
						$finalItemList = $finalItemList->transform(function($item, $key) {
							if ($item['discount'] != '0.00' || !empty($item['discount'])) {
								return $item;
							}
						});
					}
				}

				// If Allow Point Redemption
				if ($couponDetails['allow_point_redemption'] != 1 || $couponDetails['allow_point_redemption'] != '1') {
					$finalItemList = $finalItemList->transform(function($item, $key) {
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

		} else {

		}

		// Coupon Calculations

		if ($isApplyCoupon) {
			
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

			$finalItemList = collect($finalItemList);

			$orderDiscount = OrderDiscount::create([
				'v_id'		=> $order->v_id,
				'store_id'	=> $order->store_id,
				'order_id'	=> $order->order_id,
				'name'		=> 'EMR Coupon',
				'type'		=> 'CO',
				'level'		=> 'I',
				'amount'	=> $discountAmount,
				'basis'		=> $basis,
				'factor'	=> $couponDetails['factor'],
				'item_list'	=> json_encode($finalItemList->pluck('barcode')),
				'response'	=> $request->gateway_response
			]);


			$orderC = new OrderController;
			$order_arr = $orderC->getOrderResponse(['order' => $order , 'v_id' => $request->v_id , 'trans_from' => $request->trans_from ]) ;

			return response()->json(['status' => 'payment_save', 'redirect_to_qr' => true, 'message' => 'Save Payment', 'data' => $orderDiscount , 'order_summary' => $order_arr ], 200);

		}
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

	public function order_pre_verify_guide(Request $request) {
		return $this->callMethod($request, __CLASS__, __METHOD__);
	}

	public function order_details(Request $request) {
		return $this->callMethod($request, __CLASS__, __METHOD__);
	}

	public function order_receipt($c_id, $v_id, $store_id, $order_id,$usefor='')
	{
		$vendor   = DB::table('vendor')->where('id',$v_id)->first();

		if($vendor->db_structure == 2){
			 
			$cartC = new CloudPos\CartController;
			$response = $cartC->order_receipt($c_id, $v_id, $store_id, $order_id);
			// return $response;

		}else if ($v_id == 4) {
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

		}else if ($v_id == 17) {

			$cartC = new JustDelicious\CartController;
			$response = $cartC->order_receipt($c_id, $v_id, $store_id, $order_id);
			// return $response;
			//echo $response;

		}
		else if ($v_id == 20) {
					$cartC = new More\CartController;
					$response = $cartC->order_receipt($c_id, $v_id, $store_id, $order_id);
					// return $response;
					//echo $response;

				}
		else if ($v_id == 21) {
					$cartC = new MajorBrands\CartController;
					$response = $cartC->order_receipt($c_id, $v_id, $store_id, $order_id);
					// return $response;
					//echo $response;

				}

		else if ($v_id == 23) {
				$cartC = new XimiVogue\CartController;
				$response = $cartC->order_receipt($c_id, $v_id, $store_id, $order_id);
				//echo $response;
			}

		else if ($v_id == 35) {
				$cartC = new Skechers\CartController;
				$response = $cartC->order_receipt($c_id, $v_id, $store_id, $order_id);
				//echo $response;
			}

		if($usefor == 'send_email'){
			return $response;
		}else{
			echo $response;
		}
	}

	public function get_carry_bags(Request $request) {
		return $this->callMethod($request, __CLASS__, __METHOD__);
	}

	public function save_carry_bags(Request $request) {
		return $this->callMethod($request, __CLASS__, __METHOD__);
	}

	public function deliveryStatus(Request $request) {
		return $this->callMethod($request, __CLASS__, __METHOD__);
	}

	public function get_print_receipt(Request $request) {
		return $this->callMethod($request, __CLASS__, __METHOD__);
	}

	public function get_duplicate_receipt(Request $request){
        
        $vu_id = $request->vu_id;
        $v_id = $request->v_id;
        $store_id = $request->store_id; 
        $security_code_vu_id = $request->security_code_vu_id;
        //$c_id = $request->c_id;
        $order_id = $request->order_id;
        $cust_mobile_no = $request->cust_mobile_no;
        $trans_from = $request->trans_from;
        $operation = $request->operation;

        $user = User::select('c_id', 'mobile')->where('mobile',$cust_mobile_no)->first();
        if($user){

            $today_date = date('Y-m-d');
            if($request->has('order_id')){

            	$order = Invoice::where('invoice_id', $order_id)->first();

            }else{

            	$order = Invoice::where('user_id', $user->c_id)->where('v_id', $v_id)->where('store_id', $store_id)->orderBy('id' , 'desc')->where('date', $today_date)->where('trans_from', $trans_from)->first();
            }

            if($order){

               // dd(date('Y-m-d H:i:s'));

                DB::table('operation_verification_log')->insert([ 'v_id' => $v_id, 'store_id' => $store_id, 'c_id' =>$user->c_id, 'trans_from' => $trans_from, 'vu_id' =>$vu_id ,'operation' => $operation , 'order_id' => $order->invoice_id , 'verify_by' =>  $security_code_vu_id , 'created_at' => date('Y-m-d H:i:s') ]);

                $request->request->add(['c_id' => $user->c_id , 'order_id' => $order->invoice_id]);
                return $this->get_print_receipt($request);

            }else{
                return response()->json(['status'=> 'fail' , 'message' => 'Unbale to found any order which has been placed today'] , 200);
            }
        }else{
            return response()->json(['status'=> 'fail' , 'message' => 'Customer not exists'] , 200);
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
     
        $item_master  = VendorSkuDetails::where(['barcode'=> $barcode,'hsn_code'=>$hsn_code,'v_id'=>$v_id])->first();
        if(!$item_master){
         $item_master = VendorSkuDetails::where(['sku'=> $barcode,'hsn_code'=>$hsn_code,'v_id'=>$v_id])->first();
        }
        if($item_master){
                // echo "<pre>";
                 //echo  $item_master->tax->category->slab;die;
                // print_r($item_master->tax->group);die;
            if(isset($item_master->tax->group) ){
                    // if($item_master->category->group)
                if($item_master->tax->category->slab == 'NO'){
                       // print_r($item_master->tax->group);die;
                        $grouRate = $item_master->tax->group;                               
                }
                if($item_master->tax->category->slab == 'YES'){
                    //echo $mrp;
                    $getSlab   = $item_master->tax->slab->where('amount_from','<=',$mrp)->where('amount_to','>=',$mrp)->first();
                    $grouRate  = $getSlab->ratemap;
                   // $getRateMap = $getSlab->ratemap;
                }


                /*Start Tax Calculation*/
                foreach ($grouRate as $key => $value) {
                        
                    if($value->type == 'CGST'){
                        $cgst = $value->rate->name;
                        $cgst_amount = $value->rate->rate;
                    }

                    if($value->type == 'SGST'){
                        $sgst = $value->rate->name;
                        $sgst_amount = $value->rate->rate;
                    }

                    if($value->type == 'IGST'){
                        $igst = $value->rate->name;
                        $igst_amount = $value->rate->rate;
                    }

                    if($value->type == 'CESS'){
                        $cess        = $value->rate->name;
                        $cess_amount = $value->rate->rate;
                    }

                }

                //echo $cgst_amount.' - '.$sgst_amount.' - '.$igst_amount.' - '.$cess_amount;

                if($qty > 0){

                    $mrp  = round($mrp * $qty, 2);
 
                    $slab_cgst_amount = $this->calculatePercentageAmt($cgst_amount,$mrp);  //$mrp / ( 100 + $cgst_amount + $sgst_amount + $cess_amount ) * $cgst_amount;
                    $slab_sgst_amount = $this->calculatePercentageAmt($sgst_amount,$mrp); //$mrp / ( 100 + $cgst_amount + $sgst_amount + $cess_amount ) * $sgst_amount;
                    $slab_cess_amount = $this->calculatePercentageAmt($cess_amount,$mrp); // $mrp / ( 100 + $cgst_amount + $sgst_amount + $cess_amount ) * $cess_amount;
                    $slab_igst_amount = $this->calculatePercentageAmt($igst_amount,$mrp);

                    $cgst           = $cgst_amount;
                    $sgst           = $sgst_amount;
                    $igst           = $igst_amount;
                    $cess           = $cess_amount;

                    $cgst_amount = $slab_cgst_amount ;
                    $sgst_amount = $slab_sgst_amount ;
                    $igst_amount = $slab_igst_amount;
                    $cess_amount = $this->formatValue($slab_cess_amount);

                    $tax_amount  = $cgst_amount + $sgst_amount + $igst_amount+$cess_amount;

                    $tax_amount  = $this->formatValue($tax_amount);
                    $taxable_amount = floatval($mrp) - floatval($tax_amount);
                    $taxable_amount = $this->formatValue($taxable_amount);
                    $total          = $taxable_amount + $tax_amount;
                    $tax_name       = $item_master->tax->category->group->name;
                }

                 /*End Tax Calculation*/

            }
        
            $tax_type = $item_master->Item->tax_type;
        }

        $data = [
            'barcode'   => $barcode,
            'hsn'       => $hsn_code,
            'cgst'      => $cgst,
            'sgst'      => $sgst,
            'igst'      => $igst,
            'cess'      => $cess,
            'cgstamt'   => (string)$cgst_amount,
            'sgstamt'   => (string)$sgst_amount,
            'igstamt'   => (string)$igst_amount,
            'cessamt'   => (string)$slab_cess_amount,
            'netamt'    => $mrp * $qty,
            'taxable'   => (string)$taxable_amount,
            'tax'       => (string)$tax_amount,
            'total'     => $total * $qty,
            'tax_name'  => $tax_name,
            'tax_type'  => $tax_type
        ];  
        //dd($data);
        return $data;
    }	// End of taxCal
	
	private function calculatePercentageAmt($percentage,$amount) 
	{
        if(isset($percentage)  && isset($amount)){
            $result = ($percentage / 100) * $amount;
            return round($result,2);
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
                return (float)$value;
            } else {
                $strlen = $strlen - 2;
                return (float)substr($value, 0, -$strlen);
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

        $params = [ 'employee_code' => $employee_code , 'company_name'=> $company_name ] ;

        $employDis = new EmployeeDiscountController;
        $employee_details = $employDis->get_details($params);

        $store_db_name = get_store_db_name(['store_id' => $store_id]);
        //dd($employee_details);
        if($employee_details){
            $employee = DB::table($v_id.'_employee_details')->where('employee_id', $employee_details->Employee_ID)->first();
            
            if($employee){

                 DB::table($v_id.'_employee_details')->update(['available_discount' => $employee_details->Available_Discount_Amount]);
                
            }else{
                DB::table($v_id.'_employee_details')->insert([
                    'employee_id' => $employee_details->Employee_ID,
                    'first_name'  => $employee_details->First_Name,
                    'last_name'  => $employee_details->Last_Name,
                    'designation'  => $employee_details->Designation,
                    'location'  => $employee_details->Location,
                    'company_name'  => $employee_details->Comp_Name,
                    'available_discount' => $employee_details->Available_Discount_Amount
                ]);
            }

            $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id , 'employee_available_discount' => $employee_details->Available_Discount_Amount , 'employee_id' => $employee_details->Employee_ID , 'company_name' => $company_name ];

            return $this->process_each_item_in_cart($params);

            

        }else{
            return response()->json(['status' => 'fail', 'message' => 'Unable to find the employee'], 200);
        }
    }
	
	public function remove_employee_discount(Request $request)
	{    
        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;

        $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

        $cart = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->update(['employee_id' => '' , 'employee_discount' => 0.00]);

        $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id   ];

        $this->process_each_item_in_cart($params);

        return response()->json(['status' => 'success', 'message' => 'Removed Successfully' ]);
    }

	private function getBarcode($code,$store_db_name)
	{
		if($code) {
			// using icode
			$barcode = DB::table($store_db_name.'.invitem')->select('BARCODE')->where('ICODE', $code)->first();
			if($barcode->BARCODE) {
				return $barcode->BARCODE;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	private function updateStockCurrentStatus($variant_sku, $item_id, $quantity,$v_id,$store_id) 
	{
		$date = date('Y-m-d');
        $todayStatus = StockCurrentStatus::select('id', 'out_qty')
            ->where('item_id', $item_id)
            ->where('variant_sku', $variant_sku)
            ->where('store_id', $store_id)
            ->where('v_id', $v_id)
            ->where('for_date', $date)
            ->first();

        if($todayStatus) {
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

            if($stockPastStatus) {
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
        $return      = array();
        $invoiceExist= Invoice::where('invoice_id',$invoice_id)->count();
        if($invoiceExist > 0){
            $emailParams = array('v_id'=>$v_id,'store_id'=>$store_id,'invoice_id'=>$invoice_id,'user_id'=>$user_id);
            if($this->orderEmail($emailParams)){
                $return = array('status'=>'email_send','message'=>'Invoice Send successfully');
            }else{
                $return = array('status'=>'fail','message'=>'Email Send failed.Please Try Again');
            }
        }else{
            $return = array('status'=>'fail','message'=>'Invoice Not Found');
        }
         return response()->json($return);
    }	//End of orderEmailRecipt

    public function orderEmail($parms)
    {
        $v_id        = $parms['v_id'];
        $store_id    = $parms['store_id'];
        $user_id     = $parms['user_id'];
        $invoice_id  = $parms['invoice_id'];
        $date        = date('Y-m-d');
        $time        = date('h:i:s');
        $time        = strtotime($time); 
        $invoice     = Invoice::where('invoice_id',$invoice_id)->with(['payments','details'])->first();
        $payment     = $invoice->payments;
         
        $last_invoice_name = $invoice->invoice_name;
        if($last_invoice_name){
        $arr =  explode('_',$last_invoice_name);
        $id = $arr[2] + 1;
        $current_invoice_name = $date.'_'.$time.'_'.$store_id.'_'.$id.'.pdf';
        }else{
        $current_invoice_name = $date.'_'.$time.'_'.$store_id.'_1.pdf';
        }
        $bilLogo      = '';
        $bill_logo_id = 5;
        $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
        if($vendorImage)
        {
            $bilLogo = env('ADMIN_URL').$vendorImage->path;
        }

        try {
            //$user = Auth::user();
            $user  = $invoice->user;
            if($user->email != null && $user->email != ''){

                $html          = $this->order_receipt($user_id , $v_id, $store_id, $invoice_id,'send_email');
                $pdf           = PDF::loadHTML($html);
                $path          =  storage_path();
                $complete_path = $path."/app/invoices/".$current_invoice_name;
                $pdf->setWarnings(false)->save($complete_path);
                $payment_method = $payment[0]->method;
  
                $to     = $user->email;      //$mail_res['to'];
                $cc     = [];//$mail_res['cc'];
                $bcc    = [];//$mail_res['bcc'];

                //dd($cc);
                $mailer = Mail::to($user->email); 
                if(count($bcc)> 0){
                    $mailer->bcc($bcc);
                }
                if(count($cc) > 0){
                    $mailer->bcc($cc);
                }
                
                $mailer->send(new OrderCreated($user,$invoice,$invoice->details,$payment_method,$complete_path,$bilLogo));
                return true;

        }

        }catch(Exception $e){
                //Nothing doing after catching email fail
        }
    }	//End of OrderEmail

}