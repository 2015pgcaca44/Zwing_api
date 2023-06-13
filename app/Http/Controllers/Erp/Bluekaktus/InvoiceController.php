<?php

namespace App\Http\Controllers\Erp\Bluekaktus;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiCallerController;
use App\Http\Traits\VendorFactoryTrait;
use Illuminate\Http\Request;
use App\InvoicePush;
use App\Invoice;
use App\InvoiceDetails;
use App\Store;
use App\Vendor;
use App\Organisation;
use App\OrganisationDetails;
use App\Model\Stock\StockPoints;
use App\Model\Stock\StockPointHeader;
use App\State;
use App\User;
use App\Country;
use App\Payment;
use App\CashRegister;
use App\OperationVerificationLog;
use App\Model\Client\ClientState;
use App\Model\Items\VendorItems;
use App\Model\Items\VendorSkuDetails;
use App\Model\Items\VendorSku;
use App\Model\Items\VendorSkuDetailBarcode;
use App\Model\OutboundApi;
use App\Model\Oauth\OauthClient;
use App\Model\Payment\VendorMop;
use Log;
use DB;


class InvoiceController extends Controller
{
	private $config= null;
	
	public function __construct()
    {
    	$this->config = new ConfigController;
    }

    public function InvoicePush($params){
    	$id = null;
    	$outBound = $params['outBound'];
    	$v_id = $params['v_id'];
    	$store_id = $params['store_id'];
    	$invoice_id = $params['invoice_id'];
    	$client_id = $params['client_id'];

    	$client = $params['client'];
    	$error_for = $params['error_for'];
    	$store = $params['store'];
    	$vendor = $params['vendor'];
        JobdynamicConnection($v_id); 

    	$country_code=null;
		$orgAddress = OrganisationDetails::where('v_id',$v_id)->where('active','1')->first();
		if($orgAddress){
			$country_code = $orgAddress->countryDetail->sortname;
		}

    	#### Stock point is not finalize ####
    	//$stock = StockPoints::select('ref_stock_code')->where('v_id', $v_id)->where('store_id', $store_id)->first();
    	
    	/*$stockPointId = null;
    	$stockPointSellable =  StockPoints::select('ref_stock_point_code')->where('store_id', $store_id)->where('is_sellable', '1')->first();
    	if($stockPointSellable && $stockPointSellable->ref_stock_point_code!='' && $stockPointSellable->ref_stock_point_code != null){

    		$stockPointId = $stockPointSellable->ref_stock_point_code;
    	}else{

			$error_msg = 'Invoice Push Error: '.$error_for.'- Sellling Stock Point , Message: Unable to find Selling stock Point ';
    		// dd('indie this');
    		$outBound->error_before_call = $error_msg;
			$outBound->save();
    		Log::error($error_msg);

    		return [ 'error' => true , 'message' => $error_msg ];
			
    	}*/
        //dd($invoice_id);
        ///dd(DB::connection()->getDatabaseName());
    	$invoice = Invoice::where('id', $invoice_id)->first();
    	//dd($invoice);
    	$billNo = $invoice->invoice_id;

    	$billNoSequence = (int)$invoice->invoice_sequence; // $invoice->id;
    	$billDate = $invoice->created_at ;

    	if($country_code =='BD'){
	    	$billDate = strtotime('-30 minutes', strtotime($invoice->created_at));
	    	$billDate = str_replace(' ', 'T', gmdate('Y-m-d H:i:s', $billDate));
    	}

    	$terminalName = '';
    	$terminal = CashRegister::find($invoice->terminal_id);
    	if($terminal){
	    	$terminalName = $terminal->licence_no.'-'.$store->short_code.'-'.$terminal->terminal_code;
	    }
    	#### Need to implements ####
    	$noOfPrints = 1; //need to implements Because Ginesys could not accept zero
    	$operationLog = OperationVerificationLog::where('v_id', $v_id)->where('invoice_id', $invoice->invoice_id)->whereIn('operation', ['BILL_PRINT', 'BILL_REPRINT'])->get();
    	if(!$operationLog->isEmpty()){
    		if($operationLog->count() > 0){
    			$noOfPrints = $operationLog->count();
    		}
    	}
    	$roundOff = 0.0;
    	if($invoice->round_off){
    		$roundOff = (float) round( $invoice->round_off ,2 );
    	}
    	$netPayable = (float)round( $invoice->total ,2);
    	$tradeGroupId = 1; //if Local intrastat or interstat tax Then 1 else 2 	    	
    	if($invoice->cust_gstin_state_id !=null && $invoice->cust_gstin_state_id!=0){
	    	if($store->state_id != $invoice->cust_gstin_state_id){
	    		$tradeGroupId = 2;
	    	}
    	}

    	$vendorUser = Vendor::select('first_name','last_name')->where('id', $invoice->vu_id)->first();
    	$cashierName =  $vendorUser->first_name.' '.$vendorUser->last_name; //Name of the cashier
    	$ownerGSTINNo = $invoice->store_gstin; 
    	#### Need to map state Code and create unique code for state  ####
    	// dd($state);
    	$ownerGSTINStateCode = '';
    	$storeState = ClientState::select('ref_state_code')->where('client_id', $client_id)->where('state_id', $invoice->store_gstin_state_id)->first();
    	if($storeState){
    		$ownerGSTINStateCode = $storeState->ref_state_code;
    	}
    	
    	$cust = User::where('c_id', $invoice->user_id)->first();
    	$counterPartyGSTINNo = $invoice->cust_gstin; //customer gstin number 
    	$counterPartyGSTINStateCode = ''; //need to change from id to ginesys code
    	$custState = ClientState::select('ref_state_code')->where('client_id', $client_id)->where('state_id', $invoice->cust_gstin_state_id)->first();
    	if($custState){
    		$counterPartyGSTINStateCode = $custState->ref_state_code;
    	}
    	
    	#### Need to implement this ####
    	$counterPartyGSTINRegDate = null; //Gsting number Registration date for customer
    	$replaceTransaction = 0; // 0 fo no 1 for yes if wnat of update then pass 1 

    	$billRemarks = $invoice->remark;
    	//$billPromoCode = $invoice->
		$request = [];
		$roundOff = 0.0;
		$request['clientCode'] = $vendor->ref_vendor_code;
		$request['storeCode'] =  $store->store_reference_code;  //string
		$request['userId'] =  $this->config->userId;  //string
		$request['salesData'] = [
			'invoiceNo' => $invoice->invoice_id, //String WHINV/4/2321/171218/1
			'invoiceDate' => $billDate, //String 2019-12-06 10:14:40.600 UTC TIME
			'invoiceTransType' => "I", //String I FIxed value
			'invoiceType' => "R", //String R Fixed value
			'posOrderNo' => $invoice->invoice_sequence, //String
			'invoiceTotalRoundoff' => $roundOff, //String
			'invoiceTotalValue' => (float)$invoice->total, //String
			'associatedCreditNo' => null,
			'creditAmount' => null,
			'associatedCreditInvoiceNo' => null,
			'salesPerson' => $cashierName,
			'remarks' =>  $invoice->remark
		];

		#### Need to do the mapping with Ginesys implement in ####
		$custCode = $cust->id; // need to change and ginesys code
		$custIsdCode = '+91';
		// $country = Country::where('id' ,$cust->address->country_id )->first();
		if($invoice->customer_phone_code != null && $invoice->customer_phone_code !=''){
			$custIsdCode = $invoice->customer_phone_code;
		}

		$custMobileNo = (string)$invoice->customer_number;
		$custFirstname = $invoice->customer_first_name;
		#### Need to Capture ####
		$custMiddlename = null;
		$custLastname = $invoice->customer_last_name;

		$customerName = 'Pos Customer';
		if($invoice->customer_first_name != ''){
			$customerName = $invoice->customer_first_name.' '.$invoice->customer_last_name;
		}

		$custGender = 'M'; //ucfirst($cust->gender);
		if($invoice->customer_gender){
			if($invoice->customer_gender == 'male'){
				$custGender = 'M';
			}
			if($invoice->customer_gender == 'female'){
				$custGender = 'F';
			}
		}

		$custDateOfBirth = null;
		if($invoice->customer_dob != null){
			$custDateOfBirth = $invoice->customer_dob.'T00:00:00';
		}
		$custEmail = null;
		if($invoice->customer_email !=null && $invoice->customer_email!=''){
			$custEmail = $invoice->customer_email;
		}


		$request['salesData']['customer'] =[
			'name' => $customerName, //String
			'mobileNo' => $invoice->customer_number, //String
			'email' => $invoice->customer_email, //String
		];

		$custAddress =[
			'pinCode' => $invoice->customer_pincode, //String
			'telephone' => "", //String
			'fax' => "", //String
			'address' => $invoice->customer_address, //String
			'addressLine1' => "", //String
			'addressLine2' => "", //String
			'addressLine3' => "", //String
			'city' => "", //String
			'state' => "", //String
			'country' => "", //String
			'gstIn' => $invoice->cust_gstin //String
		];

		$request['salesData']['billing'] = $custAddress;
		$request['salesData']['shipping'] = $custAddress;

		$invoiceDetails = InvoiceDetails::where('v_id', $v_id)->where('t_order_id', $invoice->id)->get();
		$posBillItems = [];
		$serialNo = 0;
		if(!$invoiceDetails->isEmpty()){
			$itemTotal   = $invoiceDetails->sum('total');
			$roundOff = 0.0;
    	if($invoice->round_off==null || $invoice->round_off==''){
    		//dd('h');
    		$roundOff = (float) round( $invoice->total- $itemTotal,2);
            $request['salesData']['invoiceTotalRoundoff']  = $roundOff; 		
    	}else{

    	}
			foreach ($invoiceDetails as $key => $item) {
				// DB::enableQueryLog();
				$bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $item->barcode)->first();
				if($bar){
					$vendorSku = VendorSku::select('vendor_sku_detail_id','item_id','sku')->where(['vendor_sku_detail_id' => $bar->vendor_sku_detail_id, 'v_id' => $v_id])->first();
					$vendorSku->barCode = $bar->barcode;

				}


				$vendorItems = VendorItems::where('v_id', $v_id)->where('item_id', $vendorSku->item_id)->first();
				// dd(DB::getQueryLog());
				if(!$vendorItems){
					$error_msg = 'Bill Push Error: Error for- '.$error_for.' Message: Unable to find item ';
					Log::error($error_msg);

					$outBound->error_before_call = $error_msg;
					$outBound->save();

					return [ 'error' => true , 'message' => $error_msg ];
					exit;
				}
				$discountPercentage=0;
				$discountAmount  = 0;
				$cartDiscountPercentage=0;
				$cartDiscountAmount=0;
				$promoCode = null;
                $promoDiscountPercentage=0;
                $promoDiscountAmounts=0;
                $productDiscountAmount=0;
                $detailTotalAmount=0;
                $amount=0;
                $discountTypeCode=null;

				if($item->item_level_manual_discount!=null){

                  $ilmd = json_decode($item->item_level_manual_discount);
                  if($ilmd->basis=='P'){
                      $discountPercentage = $ilmd->factor;
                   }
                   $discountAmount  = (float)$ilmd->discount;

				}
				$tdata = json_decode($item->tdata);
				$serialNo++; 
				#### This id should be of giensys id ####
				$itemId = $vendorItems->ref_item_code; //
				$hsnsacCode = (int)$tdata->hsn; //
				$quantity = (float)$item->qty;
				$mrp = (float)$item->unit_mrp;
				$rsp = (float)$item->unit_csp;
				#### Need to implement this ####
				$esp = $rsp; // if mrp is selling the pass mrp if rsp is selling then passs rsp
				$promoDiscountAmount = (float)$item->discount; //Promo discount
				$itemLevelmanualDiscount=0;
				if($item->item_level_manual_discount!=null){
	                $iLmd = json_decode($item->item_level_manual_discount);
	                $itemLevelmanualDiscount = (float)$iLmd->discount;
	            }
				$manualDiscountAmount = (float)$item->manual_discount + $itemLevelmanualDiscount;

				$billDiscountApportionedAmount = (float)$item->bill_buster_discount; // Bill level 
				//promotion dis
				if($manualDiscountAmount>0){
                  $cartDiscountAmount = $manualDiscountAmount;
				}
				if($promoDiscountAmount>0){
				   $promoDiscountAmounts = $promoDiscountAmount;
		     	}
				$loyaltyDiscountAmount = (float)$item->lpdiscount; // Loyality point Dis
				$extraChargeAmount = 0; //Extra Tax Amount charge
				$amount = (float)$item->subtotal;
				$detailTotalAmount = (float)$item->total;
				$taxRegime = 'G' ;// G for GST And V for Vat
					#### Need to store the total tax rate ####
					$taxRate = (float)$tdata->igst + (float)$tdata->cgst + (float)$tdata->sgst + (float)$tdata->cess;
					$taxDescription = $tdata->tax_name ; //Tax name, Rate name, Description
					$taxAmount = (float)$item->tax;
					$taxableAmount = (float)$tdata->taxable;
					$igstRate = (float)$tdata->igst;
					$cgstRate = (float)$tdata->cgst;
					$sgstRate = (float)$tdata->sgst;
					$cessRate = (float)$tdata->cess;
					$igstAmount = (float)$tdata->igstamt;
					$cgstAmount = (float)$tdata->cgstamt;
					$sgstAmount = (float)$tdata->sgstamt;
					$cessAmount = (float)$tdata->cessamt;
					#### Item is not mapped ####
					$itemRemarks = '';

					$posBillItem =	[
					'productCode' => $vendorSku->sku , //String 
					'barCode' => $bar->barcode , //String 
					'quantity' => $quantity , //int 
					'mrp' => $mrp , //int 
					'rate' => $rsp , //int 
					'amount' => $amount , //int 
					'discountPercentage' => $discountPercentage, //int 
					'discountAmount' => $discountAmount, //int 
					'discountTypeCode' =>$discountTypeCode , //int  // "10% OFF"
					'promoCode'=>$promoCode, 
                    'promoDiscountPercentage'=>$promoDiscountPercentage,
                    'promoDiscountAmount'=>$promoDiscountAmounts,
					'taxAmount' => $taxAmount, //int 
					'detailTotalAmount' => $detailTotalAmount  , //int 
					'cartDiscountPercentage' =>$cartDiscountPercentage,
					'cartDiscountAmount' => $cartDiscountAmount , //int 
					'productDiscountAmount' => $productDiscountAmount,
					'ruleName' => '' //string
				];

				$taxes= [];
				//Need to finalize this
				$cgst =null;
				$sgst =null;
				$igst =null;
				$cess =null;

				if($country_code == 'BD'){
					if($cgstRate > 0){
						$taxes[] = [
							'taxType'=> 'VAT 7.5%',
					        'taxPercentage'=> $cgstRate, //int
					        'taxOnAmount'=> $taxableAmount, //int
					        'taxValue'=> $cgstAmount //int
						];
					}

				}else{
					if($cgstRate > 0){
						$taxes[] = [
							'taxType'=> 'CGST',
					        'taxPercentage'=> $cgstRate, //int
					        'taxOnAmount'=> $taxableAmount, //int
					        'taxValue'=> $cgstAmount //int
						];
					}

					if($sgstRate > 0){
						$taxes[] = [
							'taxType'=> 'SGST',
					        'taxPercentage'=> $sgstRate, //int
					        'taxOnAmount'=> $taxableAmount, //int
					        'taxValue'=> $sgstAmount //int
						];
					}


					if($igstRate > 0){
						$taxes[] = [
							'taxType'=> 'IGST',
					        'taxPercentage'=> $igstRate, //int
					        'taxOnAmount'=> $taxableAmount, //int
					        'taxValue'=> $igstAmount //int
						];
					}


					if($cessRate > 0){
						$taxes[] = [
							'taxType'=> 'Cess',
					        'taxPercentage'=> $cessRate, //int
					        'taxOnAmount'=> $taxableAmount, //int
					        'taxValue'=> $cessAmount //int
						];
					}
				}

				$posBillItem['tax'] = $taxes;

				$posBillItems[] = $posBillItem;
			}

		}else{
			$error_msg = 'Bill Push Error: Error for- '.$error_for.' Message: Unable to find and items in invoices';
			// dd('No list');
			Log::error($error_msg );

			$outBound->error_before_call = $error_msg;
			$outBound->save();

			return ['error' => true , 'message' => $error_msg ];
			exit;
		}

		$request['salesData']['itemDetails'] = $posBillItems;

		$payments = Payment::where('v_id', $v_id)->where('invoice_id', $invoice->invoice_id)->get();

		if(!$payments->isEmpty()){
		
		}else{
			$error_msg = 'Bill Push Error: Error for- '.$error_for.' Message: Unable to find any Payments' ;
			Log::error($error_msg);
			return ['error' => true , 'message' => $error_msg ];
			exit;
		}

		$posBillMOPList = [];
		$displayOrder = 0;
		foreach ($payments as $key => $payment) {
			$displayOrder++;
			#### Implement mop payments  #### 
			
			if($payment->method == 'cash'){
				$baseTender = (float)$payment->cash_collected;
				$baseBalance = (float)$payment->cash_return;
			}else{
				$baseTender = (float)$payment->amount;
				$baseBalance = (float)0;
			}

			// $vendorMop = VendorMop::where('v_id', $v_id)->where('code', $payment->method)->first();
			// $vendorMop = VendorMop::join('mops','mops.id','vendor_mop_mapping.mop_id')
			//                         ->where('vendor_mop_mapping.v_id', $v_id)
			//                         ->where('vendor_mop_mapping.store_id',$store_id)
			//                         ->where('mops.code', $payment->method)
			//                         ->first();
			// if(!$vendorMop){
			// 	$error_msg = 'Bill Push Error: Error for- '.$error_for.' Message: Unable to find Mop Mapping in Vendor Mop Table ';
			// 	Log::error($error_msg);

			// 	$outBound->error_before_call = $error_msg;
			// 	$outBound->save();

			// 	return [ 'error' => true , 'message' => $error_msg ];
			// 	exit;
			// }

			$mopShortCode = $payment->method; //Need to send ginesys code
			// $mopType = $vendorMop->ref_mop_type; //Need to send ginesys Type
			
			$baseAmt = (float)$payment->amount;
			$forexRate = 0;
			$forexTender = 0;
			$forexBalance = 0;
			$forexAmt = 0;


			$posBillMOP = [				
				'paymentMethod' => $mopShortCode, //String
				'paymentAmount' => $baseAmt, //String
				'authNo' => '', //String
				'authBankCode' => '', //String
				'authCreditCardType' => "", //String REF/123
			];

			$posBillMOPList[] = $posBillMOP;
		}
		$request['salesData']['paymentDetails'] = $posBillMOPList;
		$outBound->api_request = json_encode($request);
        $outBound->save();
		//dd(json_encode($request));
		$apiCaller = new  ApiCallerController([
			'url' => $this->config->apiBaseUrl.'/api/zwing/submit-invoice',
			'data'=> $request, 
			'header' => [ 'Content-Type:application/json'],
			'auth_type' => $this->config->authType,
			'auth_token' => $this->config->authToken,
		]);
		# extract the body
		$response = $apiCaller->call();
        $outBound->api_response = $response['body'];
        $outBound->response_status_code = $response['header_status'];
        $outBound->save();
        return $this->config->handleApiResponse($response['body']);
		// dd(json_decode($response));
		//return $response;

	}


	public function getAllUnsyncInvoice(Request $request){

		$invoice = new \App\Http\Controllers\Erp\Ginesys\InvoiceController;
		return $invoice->getAllUnsyncInvoice($request);

	}

}