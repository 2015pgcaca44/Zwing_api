<?php

namespace App\Http\Controllers\Loyality;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\VendorSetting;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use App\User;
use App\LoyaltyLogs;
use Auth;
use App\LoyaltyCustomer;
use App\Invoice;
use App\LoyaltyBill;

class EaseMyRetailController extends Controller
{
	public function __construct($params)
	{

		$storeSettings = json_decode($params['settings']);

		// Get accessToken from emseMyRetail Auth URL

		$client = new Client();
		$res = $client->request('POST', $storeSettings->easeMyRetail->TOKEN_HOST_ADDRESS, [
		    'json'	=> [
		    	'username' 	=> $storeSettings->easeMyRetail->EMRUSERNAME,
		    	'password'	=> $storeSettings->easeMyRetail->PASSWORD
		    ]
		]);

		$statusCode =  $res->getStatusCode();
		// echo $res->getBody()->getContents();
		if ($statusCode == 200) {
			$accessToken = json_decode($res->getBody(), true);
			$accessToken = $accessToken['data'][0]['accessToken'];
			$this->accessToken = 'Bearer '.$accessToken;
			$this->url = $storeSettings->easeMyRetail->HOSTADDRESS;
			$this->storeId = $storeSettings->easeMyRetail->StoreCode;
			$this->enterpriseId = $storeSettings->easeMyRetail->ENTERPRISEID;
			$this->v_id = $params['v_id'];
			$this->store_id = $params['store_id'];
			$this->mobile = $params['mobile'];
			$this->zw_event = $params['zw_event'];
			$funcName = (string)$params['event'];
			$this->response = $this->$funcName($params);
			return $this->response;
			// dd($funcRes);

		} else {
			return response()->json([ 'message' => $res->getBody() ]);
		}
		// exit;
	}

	public function callAPI($params)
	{
		$client = new Client();
		$res = $client->request($params['method'], $this->url, $params['data']);
		$data = [ 'url' => $this->url, 'data' => $params['data'] ];
		LoyaltyLogs::create([
			'v_id'		=> $this->v_id,
			'store_id'	=> $this->store_id,
			'status'	=> $res->getStatusCode(),
			'mobile'	=> $this->mobile,
			'request'	=> json_encode($data),
			'response'	=> json_encode(json_decode($res->getBody(), true)),
			'type'		=> 'EMR'
		]);
		return $res;
	}

	public function searchCustomer($params)
	{
		$param['method'] = 'POST';
		// $param['mobile'] = $params['mobile'];
		// $param['v_id'] = $params['v_id'];
		// $param['store_id'] = $params['store_id'];
		$param['data'] = [
			'headers' => [ 
		    	'Authorization'	=> $this->accessToken
		    ],
			'json'	=> [
		    	'mobile' 	=> $params['mobile'],
		    	'eventType'	=> $params['event']
		    ],
		    'query'	=> [
		    	'storeId'		=> $this->storeId,
		    	'enterpriseId'	=> $this->enterpriseId
		    ]
		];
		$getProfile = $this->callAPI($param);
		$searchCustomerResponse = json_decode($getProfile->getBody(), true);

		// dd($searchCustomerResponse);
		if ($searchCustomerResponse['status'] == "2000" && $searchCustomerResponse['error'] == null) {

			if ($this->zw_event == 'checkAccount' || $this->zw_event == 'checkAccountOrCreate') {
				$this->loyaltyCustomer('1');
			}
			
		} elseif ($searchCustomerResponse['status'] == "4000" && $searchCustomerResponse['error'] != null) {

			if ($this->zw_event == 'checkAccountOrCreate') {
				$this->createCustomer($params);
			}

		}
	}

	public function loyaltyCustomer($status)
	{
		$checkAccount = LoyaltyCustomer::where('mobile', $this->mobile)->where('type', 'easeMyRetail')->where('vendor_id', $this->v_id)->first();
		if (empty($checkAccount)) {
			LoyaltyCustomer::create([
				'vendor_id'		=> $this->v_id,
				'store_id'		=> $this->store_id,
				'mobile'		=> $this->mobile,
				'loyalty_id'	=> $this->storeId,
				'type'			=> 'easeMyRetail',
				'is_created'	=> $status
			]);
		} else {
			$checkAccount->is_created = $status;
			$checkAccount->save();
		}
	}

	public function createCustomer($params)
	{
		$userDetails = User::find($params['user_id']);
		// dd($userDetails);
		$param['method'] = 'POST';
		$param['data'] = [
			'headers' => [ 
		    	'Authorization'	=> $this->accessToken
		    ],
			'json'	=> [
		    	'firstname' 	=> $userDetails->first_name,
		    	'lastname'		=> $userDetails->last_name,
		    	'dateofbirth'	=> $userDetails->dob,
		    	'pincode'		=> '',
		    	'Emailid'		=> $userDetails->email,
		    	'mobile'		=> $userDetails->mobile,
		    	'gender'		=> $userDetails->gender,
		    	'eventType'		=> 'createCustomer'
		    ],
		    'query'	=> [
		    	'storeId'		=> $this->storeId,
		    	'enterpriseId'	=> $this->enterpriseId
		    ]
		];
		$getProfile = $this->callAPI($param);
		$createCustomerResponse = json_decode($getProfile->getBody(), true);
		
		if ($createCustomerResponse['status'] == "2000" && $createCustomerResponse['error'] == null) {

			if ($this->zw_event == 'checkAccountOrCreate') {
				$this->loyaltyCustomer('1');
			}
			
		} elseif ($createCustomerResponse['status'] == "4000" && $createCustomerResponse['error'] != null) {


		}
	}

	public function billPush($param)
	{
		$data = $this->createBillPushResponse($param['invoice_id']);
		// dd($data);
		$param['method'] = 'POST';
		$param['data'] = [
			'headers' => [ 
		    	'Authorization'	=> $this->accessToken
		    ],
			'json'	=> [
		    	'billingDetails' 	=> $data,
		    	'eventType'			=> 'addTransaction',
		    	'membershipId'		=> $this->mobile,
		    	'BillEvent'			=> 'NEW',
		    	'ISDCode'			=> '',
		    	'IsEmployee'		=> 0
		    ],
		    'query'	=> [
		    	'storeId'		=> $this->storeId,
		    	'enterpriseId'	=> $this->enterpriseId
		    ]
		];
		$getProfile = $this->callAPI($param);
		$billPushResponse = json_decode($getProfile->getBody(), true);

		if ($billPushResponse['status'] == "2000" && $billPushResponse['error'] == null) {

			if ($this->zw_event == 'checkBill') {
				$this->loyaltyBillPush($param, '1');
			}
			
		} elseif ($billPushResponse['status'] == "4000" && $billPushResponse['error'] != null) {

			if ($this->zw_event == 'checkBill') {
				$this->loyaltyBillPush($param, '0');
			}

		}
		// dd($createCustomerResponse);
	}

	public function loyaltyBillPush($param, $status)
	{
		LoyaltyBill::create([
			'vendor_id'		=> $this->v_id,
			'store_id'		=> $this->store_id,
			'user_id'		=> $param['user_id'],
			'invoice_no'	=> $param['invoice_id'],
			'type'			=> 'easeMyRetail',
			'is_submitted'	=> $status
		]);
	}

	private function createBillPushResponse($invoice)
	{
		$data = [];
		$POSBillItems = [];
		$POSBillMOP = [];
		$AllowPointAccrual = '1';
		$LPDiscountAmt = $totalDiscount = 0;
		$invoice = Invoice::where('invoice_id', $invoice)->first();
		$customer = $invoice->user;
		$RefBillNo = null;

		$mrpAmt = $basicAmt = $discountAmt = $cash_collected = $cash_return = 0;
		$totalMrpAmt = $totalBasicAmt = $totalPromoAmt = $totalGrossAmount = 0;

		// Coupon Calculation 

		$coupon = $invoice->discounts->where('name', 'EMR Coupon')->where('type', 'CO')->first();
		$CouponCode = $EMRREDCouponRef = '';
		$MDiscountAmt = '0';
		$MDiscountFactor = 0;
		if (!empty($coupon)) {
			$couponResposne = json_decode($coupon->response);
			$totalDiscount = $totalDiscount + $coupon->amount;
			$CouponCode = $couponResposne->couponcode;
			$EMRREDCouponRef = $couponResposne->referenceno;
			$MDiscountAmt = format_number($coupon->amount);
			$MDiscountFactor = $couponResposne->factor;
			$AllowPointAccrual = $couponResposne->allow_point_accrual;
		}

		// Loyalty Calculation 

		$loyalty = $invoice->discounts->where('name', 'EMR Loyalty')->where('type', 'LP')->first();
		if (!empty($loyalty)) {
			$loyaltyResposne = json_decode($loyalty->response);
			$totalDiscount = $totalDiscount + $invoice->lpdiscount;
			$AllowPointAccrual = $loyaltyResposne->Allow_Point_Accrual;
			$LPDiscountAmt = $loyalty->amount;
		}
		

		foreach ($invoice->details as $key => $value) {
			$item_det = json_decode($value->section_target_offers);
			$item_det = json_decode(urldecode($item_det->item_det));

			$tax = json_decode($value->tdata);

			$taxPercent = $tax->cgst + $tax->sgst;

			if (!empty($value->discount) && $value->discount != 0.00 && $value->discount != '0.00') {
				$pdata = collect(json_decode($value->pdata));
				$promo_code = $pdata->unique('promo_code')->first()->promo_code;
			} else {
				$promo_code = null;
			}

			$mrpAmt = $value->unit_mrp * $value->qty;
			$basicAmt = $value->unit_csp * $value->qty;
			$discountAmt = $value->lpdiscount + $value->coupon_discount;
			$itemGrossAmt = $value->subtotal - $value->discount;
			$IGrossAmt = $value->subtotal - $value->discount;
			$IMGrossAmt = $value->subtotal - $value->discount - $value->coupon_discount;
			$itemSalePrice = $value->subtotal - $value->discount;
			$itemQty = $value->qty;
			$itemTaxable = $tax->taxable;
			$itemTax = $tax->tax;
			$MDiscountAmt = $value->coupon_discount;


			$totalMrpAmt += $mrpAmt;
			$totalBasicAmt += $basicAmt;

			if ($value->transaction_type == 'return') {
				$itemQty = -$value->qty;
				$mrpAmt = -$mrpAmt;
				$basicAmt = -$basicAmt;
				$itemGrossAmt = -$itemGrossAmt;
				$itemTax = -$itemTax;
				$itemTaxable = -$itemTaxable;
				$IMGrossAmt = -$IMGrossAmt;
				$RefBillNo = get_invoice_no($invoice->order->ref_order_id);
			}

			// dd($item_det);
			$POSBillItems[] = [
				'POSBillItemId'			=> $value->id,
				'ItemId'				=> $value->item_id,
				'BarCode'				=> $value->barcode,
				'ItemName'				=> $value->item_name,
				'Division'				=> $item_det->DIVISION_NAME,
				'Section'				=> $item_det->SECTION_NAME,
				'Department'			=> $item_det->DEPARTMENT_NAME,
				'Article'				=> $item_det->ARTICLE_NAME,
				'Qty'					=> (int)$itemQty,
				'RtQty'					=> 0,
				'MRP'					=> (int)$value->unit_mrp,
				'RSP'					=> (int)$value->unit_csp,
				'ESP'					=> (int)$value->unit_csp,
				'MRPAmt'				=> $mrpAmt,
				'BasicAmt'				=> $basicAmt,
				'PromoAmt'				=> (float)$value->discount,
				'GrossAmt'				=> (float)$itemGrossAmt,
				'IDiscountDisplay'		=> null,
				'IDiscountAmt'			=> 0,
				'SalePrice'				=> (float)$itemSalePrice,
				'IGrossAmt'				=> (float)$IGrossAmt,
				'MDiscountAmt'			=> (float)$MDiscountAmt,
				'DiscountAmt'			=> (float)$discountAmt,
				'NetAmt'				=> (float)$value->total,
				'TaxPercent'			=> $taxPercent,
				'TaxAmt'				=> (float)$itemTax,
				'TaxableAmt'			=> (float)$itemTaxable,
				'IDiscountFactor'		=> 0,
				'MDiscountFactor'		=> (float)$MDiscountFactor,
				'PromoCode'				=> (string)$promo_code,
				'PromoDiscountFactor'	=> null,
				'RefPOSBillItemId'		=> null,
				'LPDiscountBenefit'		=> null,
				'LPPointBenefit'		=> null,
				'MGrossAmt'				=> (float)$IMGrossAmt,
				'LPDiscountAmt'			=> (float)$value->lpdiscount,
				'LPAmountSpendFactor'	=> null,
				'LPPointEarnedFactor'	=> null,
				'LPPointsCalculated'	=> 0,
				'LPDiscountFactor'		=> null,
				'RefBillNo'				=> $RefBillNo
			];
		}
		// dd($invoice->payments);
		foreach ($invoice->payments as $payment_value) {
			if ($payment_value->method != 'loyalty_emr') {
				
				if ($payment_value->method == 'cash') {
					$cash_collected = $payment_value->cash_collected;
					$cash_return = $payment_value->cash_return;
					$Tender = $cash_collected;
					$MOPBaseAmt = $payment_value->amount;
				} else {
					$cash_collected = $payment_value->amount;
					$cash_return = 0;
					$Tender = 0;
					$MOPBaseAmt = $payment_value->amount;
				}
				if ($invoice->transaction_type == 'return') {
					$cash_collected = -$cash_collected;
					$MOPBaseAmt = -$MOPBaseAmt;
				}
				$data['POSBillMOP'][] = [
					'POSBillMOPId'		=> 0,
					'MOPId'				=> (string)getMOPInfo($payment_value->method, 2, 'id'),
					'MOPName'			=> getMOPInfo($payment_value->method, 2, 'name'),
					'MOPType'			=> getMOPInfo($payment_value->method, 2, 'code'),
					'BaseTender'		=> (int)$cash_collected,
					'BaseBalance'		=> (int)$cash_return,
					'BaseAmt'			=> (int)$MOPBaseAmt,
					'Tender'			=> (int)$Tender,
					'CCNo'				=> null,
					'CurrencyId'		=> "1"
				];

			} 

			// if ($payment_value->method == 'loyalty_emr') {
			// 	$LPDiscountAmt += $payment_value->amount;
			// 	$gatewayResponse = json_decode($payment_value->gateway_response);
			// 	if ($gatewayResponse->Allow_Point_Accrual == '0') {
			// 		$AllowPointAccrual = '0';
			// 	} elseif ($gatewayResponse->Allow_Point_Accrual == '1') {
			// 		$AllowPointAccrual = '1';
			// 	}
			// }
		}

		
		$grossAmount = $invoice->subtotal - $invoice->discount;
		$saleAmount = $invoice->subtotal - $invoice->discount;
		$netAmount = $invoice->total;
		$netPayable = $invoice->total;

		if ($invoice->transaction_type == 'return') {
			$totalMrpAmt = -$totalMrpAmt;
			$totalBasicAmt = -$totalBasicAmt;
			$grossAmount = -$grossAmount;
			$netAmount = -$netAmount;
			$netPayable = -$netPayable;
		}

		// dd($EMRREDCouponRef);

		$data['POSBILL'][] = [ 
			'BillId' 			=> $invoice->id,
			'BillNo'			=> $invoice->invoice_id,
			'BillGUID'			=> $invoice->ref_order_id,
			'BillDate'			=> str_replace('','T',$invoice->created_at),
			'TerminalId'		=> '',
			'customerId'		=> (string)$invoice->user_id,
			'cardNo'			=> '',
			'StockPointId'		=> $invoice->store->mapping_store_id,
			'DiscountId'		=> '',
			'MRPAmt'			=> format_number($totalMrpAmt),
			'BasicAmt'			=> format_number($totalBasicAmt),
			'PromoAmt'			=> ($invoice->discount == 0 ? (string)0 : format_number($invoice->discount)),
			'SaleAmt'			=> format_number($saleAmount),
			'ReturnAmt'			=> '0',
			'GrossAmt'			=> format_number($grossAmount),
			'LPDiscountAmt'		=> ($LPDiscountAmt == 0 ? (string)$LPDiscountAmt : format_number($LPDiscountAmt)),
			'discountBenefitId'	=> '',
			'pointBenefit'		=> '',
			'DiscountAmt'		=> ($totalDiscount == 0 ? (string)$totalDiscount : format_number($totalDiscount)),
			'NetAmt'			=> format_number($netAmount),
			'ChargeAmt'			=> '0',
			'NetPayable'		=> (string)(float)$netPayable,
			'RoundOff'			=> '0',
			'ExTaxAmt'			=> '0',
			'PromoCode'			=> '',
			'PromoBenefit'		=> '',
			'CouponCode'		=> $CouponCode,
			'MDiscountAmt'		=> $MDiscountAmt,
			'EMRREDCouponRef'	=> $EMRREDCouponRef,
			'AllowPointAccrual'	=> $AllowPointAccrual
		];

		$data['POSBillItems'] = $POSBillItems;

		// $data['POSBillMOP'] = $POSBillMOP;

		$data['POSCustomer'][] = [
			'firstfame'				=> $customer->first_name,
			'lastname'				=> $customer->last_name,
			'dateofbirth '			=> $customer->dob,
			'pincode'				=> '',
			'emailid'				=> $customer->email,
			'mobile'				=> $customer->mobile,
			'gender'				=> 'M',
			'membershipcardnumber'	=> $customer->mobile,
			'ISDCode'				=> ""
		];
		// dd($data);
		return $data;
		// dd($data);
	}

	public function getUrl($param)
	{
		// dd($param);
		$param['method'] = 'POST';
		$param['data'] = [
			'headers' => [ 
		    	'Authorization'	=> $this->accessToken
		    ],
			'json'	=> [
		    	'mobile' 			=> $this->mobile,
		    	'eventType'			=> 'pointRedemptionView',
		    	'billGUID'			=> $param['order_id'],
		    	'ISDCode'			=> '91',
		    	'billValue'			=> (int)$param['billAmount'],
		    	'IsEmployee'		=> "1"
		    ],
		    'query'	=> [
		    	'storeId'		=> $this->storeId,
		    	'enterpriseId'	=> $this->enterpriseId
		    ]
		];
		$getProfile = $this->callAPI($param);
		$pointRedemptionView = json_decode($getProfile->getBody(), true);

		if ($pointRedemptionView['status'] == "2000" && $pointRedemptionView['error'] == null) {

			if ($this->zw_event == 'generateUrl') {
				// dd($pointRedemptionView['data'][0]['responseUrl']);
				return [ 'url' => $pointRedemptionView['data'][0]['responseUrl'] ];
			}
			
		} elseif ($pointRedemptionView['status'] == "4000" && $pointRedemptionView['error'] != null) {

			// if ($this->zw_event == 'checkBill') {
			// 	$this->loyaltyBillPush($param, '0');
			// }

		}
		// dd($createCustomerResponse);
	}

	public function getCouponUrl($param)
	{
		// dd($param);
		$param['method'] = 'POST';
		$param['data'] = [
			'headers' => [ 
		    	'Authorization'	=> $this->accessToken
		    ],
			'json'	=> [
		    	'mobile' 			=> $this->mobile,
		    	'eventType'			=> 'couponRedemptionView',
		    	'billGUID'			=> $param['order_id'],
		    	'ISDCode'			=> '91',
		    	'billValue'			=> (int)$param['billAmount'],
		    	'IsEmployee'		=> "1"
		    ],
		    'query'	=> [
		    	'storeId'		=> $this->storeId,
		    	'enterpriseId'	=> $this->enterpriseId
		    ]
		];
		$getProfile = $this->callAPI($param);
		$pointRedemptionView = json_decode($getProfile->getBody(), true);

		if ($pointRedemptionView['status'] == "2000" && $pointRedemptionView['error'] == null) {

			if ($this->zw_event == 'generateCouponUrl') {
				// dd($pointRedemptionView['data'][0]['responseUrl']);
				return [ 'url' => $pointRedemptionView['data'][0]['responseUrl'] ];
			}
			
		} elseif ($pointRedemptionView['status'] == "4000" && $pointRedemptionView['error'] != null) {

			// if ($this->zw_event == 'checkBill') {
			// 	$this->loyaltyBillPush($param, '0');
			// }

		}
		// dd($createCustomerResponse);
	}
}
