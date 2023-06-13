<?php

namespace App\Http\Controllers\Erp\Ginesys;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiCallerController;
use App\Http\Traits\VendorFactoryTrait;
use Illuminate\Http\Request;
use App\InvoicePush;
use App\Invoice;
use App\InvoiceDetails;
use App\OrderDiscount;
use App\Store;
use App\Vendor;
use App\ManualDiscount;
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
use App\Model\Client\ClientVendorMapping;
use Log;
use DB;
use App\DepRfdTrans;
use App\Voucher;
use App\Model\GiftVoucher\GiftVoucherTransactionLogs;
use App\CrDrSettlementLog;    


class InvoiceController extends Controller
{
	private $config= null;
	
	public function __construct()
    {
    	//$this->config = new ConfigController;
    }

    public function InvoicePush($params){
        // dd($params['type']);
		// if(isset($params['type'])){
		// 	if($params['type']=='RETURN'){
		// 		return $this->returnInvoicePush($params);
		// 	}
		// }
    	
    	$id = null;
    	if(!isset($params['unsync']) == 1){
	    	$outBound = $params['outBound'];
	    }
    	$v_id = $params['v_id'];
    	$store_id = $params['store_id'];
    	$invoice_id = $params['invoice_id'];
    	$client_id = $params['client_id'];

    	$client = $params['client'];
    	$error_for = $params['error_for'];
    	$store = $params['store'];
    	$vendor = $params['vendor'];
    	$source_currency= null;
    	$target_currency=null;
    	    	
    	JobdynamicConnection($v_id);
    
    	//Againg checking return because facing issue
    	$invoice = Invoice::where('id', $invoice_id)->first();
    	if($invoice->transaction_type == 'return' || $invoice->transaction_type == 'RETURN'){

    		return $this->returnInvoicePush($params);
    	}

    	$currecy=getStoreAndClientCurrency($v_id,$store_id);
    	if($currecy['status']=='error'){
            $error_msg = $currecy['message'];
            if(!isset($params['unsync']) == 1){
	    		$outBound->error_before_call = $error_msg;
				$outBound->save();
			
	    		Log::error($error_msg);

	    		return [ 'error' => true , 'message' => $error_msg ];
    		}
    	}else{

         $source_currency = $currecy['store_currency'];
         $target_currency = $currecy['client_currency']; 


    	}

    	#### Stock point is not finalize ####
    	//$stock = StockPoints::select('ref_stock_code')->where('v_id', $v_id)->where('store_id', $store_id)->first();
    	$stockPointId = null;
    	// $stockPointSellable =  StockPoints::select('ref_stock_point_code')->where('store_id', $store_id)->where('is_sellable', '1')->first();

    	$StockpointhdId =  StockPoints::select('stock_point_header_id')->where('store_id', $store_id)->where('is_sellable', '1')->first();

		$stockPointSellable =  StockPointHeader::select('ref_stock_point_header_code')->where('id', $StockpointhdId->stock_point_header_id)->first();
		
    	if($stockPointSellable && $stockPointSellable->ref_stock_point_header_code!='' && $stockPointSellable->ref_stock_point_header_code != null){

    		$stockPointId = $stockPointSellable->ref_stock_point_header_code;
    		// $stockPointId = explode('-', $stockPointId);
    		// $stockPointId = $stockPointId[1];
    	}else{

    	

			$error_msg = 'Invoice Push Error: '.$error_for.'- Sellling Stock Point , Message: Unable to find Selling stock Point ';
    		// dd('indie this');
    		if(!isset($params['unsync']) == 1){
	    		$outBound->error_before_call = $error_msg;
				$outBound->save();
			
	    		Log::error($error_msg);

	    		return [ 'error' => true , 'message' => $error_msg ];
    		}
			
    	}
    	$extrarate=getExchangeRate($v_id,$source_currency,$target_currency,1);
    	if($extrarate['status']=='error'){
            $error_msg = $extrarate['message'];
            if(!isset($params['unsync']) == 1){
	    		$outBound->error_before_call = $error_msg;
				$outBound->save();
			
	    		Log::error($error_msg);

	    		return [ 'error' => true , 'message' => $error_msg ];
	    	}
    	}

    	$billNo = $invoice->invoice_id;

    	

    	#### nNeed to capture bill sequence number ####
    	$billNoSequence = (int)$invoice->invoice_sequence; // $invoice->id;
    	$billDate = str_replace(' ','T',$invoice->created_at);

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
    		//dd($roundOff);
    	}
    	$exchangeRound = getExchangeRate($v_id,$source_currency,$target_currency,$roundOff);
    	$roundOff = $exchangeRound['amount'];

    	//dd($roundOff);

    	$netPayable = (float)round( $invoice->total ,2);
    	$exchangeNetPayable = getExchangeRate($v_id,$source_currency,$target_currency,$netPayable);
    	//dd($netPayable);
    	$netPayable      =  $exchangeNetPayable['amount'];
    	//dd($netPayable);
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

    	$billRemarks = null;
    	if($invoice->remark !='' && $invoice->remark!= null){
    		$billRemarks = $invoice->remark;
    	}
    	//$billPromoCode = $invoice->
		$request = [];

		$request['storeId']  = (int) $store->store_reference_code;  //Int
		$request['stockPointId']  = (int)$stockPointId;  //int;
		$request['billNo']  = $billNo; //string
		$request['billNoSequence']  = $billNoSequence; //int; 
		$request['billDate']  = $billDate;  //String 2019-11-27T11:59:52.182Z
		$request['terminalName']  = $terminalName; //String
		$request['noOfPrints']  = $noOfPrints;  //Int
		$request['roundOff']  = $roundOff;  //Int
		$request['netPayable']  = $netPayable;  //Int
		$request['tradeGroupId']  = $tradeGroupId;  //Int
		$request['createdBy']  = $cashierName;  //Int
		$request['ownerGSTINNo']  = $ownerGSTINNo;  //String
		$request['ownerGSTINStateCode']  = $ownerGSTINStateCode;  //String
		$request['counterPartyGSTINNo']  = $counterPartyGSTINNo;  //String
		$request['counterPartyGSTINStateCode']  = $counterPartyGSTINStateCode;  //String
		$request['counterPartyGSTINRegDate']  = $counterPartyGSTINRegDate;  //String 2019-11-27T11:59:52.182Z
		$request['ReplaceTransaction']  = $replaceTransaction;  //Int


		$billPromoDescription = '';	
		$billDiscountId = $id;
		$billDiscountDescription = $id;
		$billDiscountMaxAmount = 0;

		$billManualDisc = OrderDiscount::where('order_id', $invoice->ref_order_id)->where('type','MD')->first();
		if($billManualDisc){
			if($billManualDisc->discount_id > 0){
				$md = ManualDiscount::find($billManualDisc->discount_id);
				if($md){
					$billDiscountId = $md->discount_code;
					$billDiscountDescription = $md->description;
					$billDiscountMaxAmount = (float) $billManualDisc->amount;
				}
			}
		}
		
		//Need to confirm that whole field is optional or not
		$request['optionalInfo'] = [

			'billPromoDescription' => $billPromoDescription, // Desc of bIll level Promo
			'billRemarks' => $billRemarks, //String
			'billPromoCode' => $id, //String
			'billPromoNo' => $id, //String
			'billPromoName' => $id, //String
			'billPromoStartDate' => $id, //String 2019-11-27T11:59:52.182Z
			'billPromoEndDate' => $id, //String 2019-11-27T11:59:52.182Z
			'billPromoStartTime' => $id, //String 2019-11-27T11:59:52.182Z
			'billPromoEndTime' => $id, //String 2019-11-27T11:59:52.182Z
			'billPromoSlabRangeFrom' => 0, //Int
			'billPromoSlabRangeTo' => 0, //Int
			'billPromoBenefit' => $id, //String
			'billDiscountId' => $billDiscountId, //Int //Bill Level manual dis code
			'billDiscountDescription' => $billDiscountDescription, //String //Bill Level manual dis desc
			'billDiscountMaxAmount' => $billDiscountMaxAmount, //Int Amount of Bill level manual Discount
			'billDiscountMinApplicableAmount' => 0, //Int
			'manualPromoCleared' => $id, //String
			'couponCode' => $id, //String
			'couponOfferCode' => 0, //String
			'redemptionMobileNo' => $id, //String
			'otp' => $id //String
		];


		#### Need to do the mapping with Ginesys implement in ####
		$custCode = null;
		if( $invoice->user_id > 0){
			$custCode = (string) $invoice->user_id; // need to change and ginesys code
		}
		$custIsdCode = null;
		// $country = Country::where('id' ,$cust->address->country_id )->first();
		if($invoice->customer_phone_code != null && $invoice->customer_phone_code !=''){
			$custIsdCode = $invoice->customer_phone_code;
			$custIsdCode = str_replace('+','',$custIsdCode);
		}
		$custMobileNo = (string)$invoice->customer_number;
		$custFirstname = null;
		if($invoice->customer_first_name != null && $invoice->customer_first_name !=''){
			$custFirstname = $invoice->customer_first_name;
		}else{
		   $custFirstname = "Dummy";
		}
		#### Need to Capture ####
		$custMiddlename = null;
		$custLastname = null;
		if($invoice->customer_last_name != null && $invoice->customer_last_name !=''){
			$custLastname = $invoice->customer_last_name;
		}else{
			if(substr($custMobileNo, 0,1) == '3'){
				$custLastname = "Customer";	
			}else{
				$custLastname = ".";	
			}

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
		if($invoice->customer_dob != null && $invoice->customer_dob!='0000-00-00'){
			$custDateOfBirth = $invoice->customer_dob.'T00:00:00';
		}
		$custEmail = null;
		if($invoice->customer_email != null && $invoice->customer_email !=''){
			$custEmail = $invoice->customer_email;
		}

		$customer_address1 = null;
		if($invoice->customer_address !=null && $invoice->customer_address != ''){
         $customer_address1 = $invoice->customer_address;
		}


		$request['posCustomer'] = [
			'isdCode' => $custIsdCode, //String
			'mobileNo' => $custMobileNo, //String
			'firstName' => $custFirstname, //String
			'middleName' => $custMiddlename, //String
			'lastName' => $custLastname, //String
			'gender' => $custGender, //String Ginesys have M // F
			'customerGroup' => 'POSCUST', //This is added for RJ Corp Hard coded right Now
			//Need to confirm that whole field is optional or not
			'optionalInfo' => [
				'salutation' => $id, //String
				'email' => $custEmail, //String
				'phone1' => $id, //String
				'phone2' => $id, //String
				'address1' => $customer_address1, //String
				'address2' => $id, //String
				'address3' => $id, //String
				'city' => $id, //String
				'country' => $id, //String
				'district' => $id, //String
				'state' => $id, //String
				'pincode' => $id, //String
				'remarks' => $id, //String
				'profession' => $id, //String
				'spouseName' => $id, //String
				'dateOfBirth' => $custDateOfBirth, //String 2019-11-27T11:59:52.182Z
				'dateOfAnniversary' => $id, //String 2019-11-27T11:59:52.182Z
				'pan' => $id //String
			]
		];

		if($invoice->customer_first_name == 'Dummy') {
			$request['posCustomer'] = null;
		} 
		
		$invoiceDetails = InvoiceDetails::where('v_id', $v_id)->where('t_order_id', $invoice->id)->get();
		$itemTotal   = $invoiceDetails->sum('total');
		//dd($invoice->total);
        $roundOff = 0.0;
    	if($invoice->round_off==null || $invoice->round_off==''){
    		//dd('h');
    		$roundOff = (float) round( $invoice->total- $itemTotal,2);
            $request['roundOff']  = $roundOff; 		
    	}
    	//dd($request);
		$posBillItems = [];
		$serialNo = 0;
		if(!$invoiceDetails->isEmpty()){
			foreach ($invoiceDetails as $key => $item) {
				$vendorSku = null;
				// DB::enableQueryLog();
				$bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $item->barcode)->first();
				if($bar){
					$vendorSku = VendorSku::select('item_id','vendor_sku_detail_id','sku')->where(['vendor_sku_detail_id' => $bar->vendor_sku_detail_id, 'v_id' => $v_id])->first();
					if(!$vendorSku){
						$error_msg = 'Unable to find item in flat table for barcode: '.$bar->barcode .' with vendor_sku_detail_id '.$bar->vendor_sku_detail_id;
						Log::error($error_msg );
						if(!isset($params['unsync']) == 1){
							$outBound->error_before_call = $error_msg;
							$outBound->save();
						

							return ['error' => true , 'message' => $error_msg ];
							exit;
						}
					}
					$vendorSku->barcode = $bar->barcode;

				}

				$vendorItems = null;
				if($vendorSku){
					$vendorItems = VendorItems::where('v_id', $v_id)->where('item_id', $vendorSku->item_id)->first();

				}
				// dd(DB::getQueryLog());
				if(!$vendorItems){
					$error_msg = 'Bill Push Error: Error for- '.$error_for.' Message: Unable to find item ';
					Log::error($error_msg);
					if(!isset($params['unsync']) == 1){
						$outBound->error_before_call = $error_msg;
						$outBound->save();
					

						return [ 'error' => true , 'message' => $error_msg ];
						exit;
					}
				}

				$tdata = json_decode($item->tdata);
				$serialNo++; 
				#### This id should be of giensys id ####
				$itemId = $vendorItems->ref_item_code; //
				$hsnsacCode = (int)$tdata->hsn; //
				$quantity = (float)$item->qty;
				$mrp = (float)$item->unit_mrp;
				$exchangemrp =getExchangeRate($v_id,$source_currency,$target_currency,$mrp);
				$mrp = $exchangemrp['amount'];
				$rsp = (float)$item->unit_csp;
				$exchangersp =getExchangeRate($v_id,$source_currency,$target_currency,$rsp);
				$rsp  = $exchangersp['amount'];
				#### Need to implement this ####
				$esp = $rsp; // if mrp is selling the pass mrp if rsp is selling then passs rsp
				$promoDiscountAmount = (float)$item->discount; //Promo discount
				$itemPromoDiscription = '';
				if($item->discount > 0){
					//Need to change this logic
					$offer = json_decode($item->section_target_offers);
					if(isset($offer->offer_data->applied_offers)){
						foreach ($offer->offer_data->applied_offers as $offer){
							if(isset($offer->message)){
								$master = DB::table('pro_promo_master')->select('DESCRIPTION')->where('name',$offer->message)->first();
								if($master){
									$itemPromoDiscription = $master->DESCRIPTION;
								}
							}
						}
					}

				}
				$exchangepromoDiscountAmount =getExchangeRate($v_id,$source_currency,$target_currency,$promoDiscountAmount);
				$promoDiscountAmount = $exchangepromoDiscountAmount['amount'];
				$itemLevelmanualDiscount=0;
				
				$itemDiscountId = $id;
				$itemDiscountDesc = $id;
				
				if($item->item_level_manual_discount!=null){
	                $iLmd = json_decode($item->item_level_manual_discount);
	                $itemLevelmanualDiscount = (float)$iLmd->discount;
	                if(!empty($iLmd->md_id) ){
	                	$manualD = ManualDiscount::select('description')->find($iLmd->md_id);
	                	if($manualD){
		                	$itemDiscountId = $iLmd->md_id;
		                	$itemDiscountDesc = $manualD->description;
	                	}
	                }
	            }

				$manualDiscountAmount = (float)$itemLevelmanualDiscount;
				$exchangemanualDiscountAmount =getExchangeRate($v_id,$source_currency,$target_currency,$manualDiscountAmount);
				$manualDiscountAmount   = $exchangemanualDiscountAmount['amount'];
				$billDiscountApportionedAmount = (float)$item->bill_buster_discount + (float)$item->manual_discount ; // Bill level promotion dis
				$exchangebillDiscountApportionedAmount = getExchangeRate($v_id,$source_currency,$target_currency,$billDiscountApportionedAmount);
				$billDiscountApportionedAmount   = $exchangebillDiscountApportionedAmount['amount']; 

				$loyaltyDiscountAmount = (float)$item->lpdiscount; // Loyality point Dis
				$exchangeLoyaltyDiscountAmount = getExchangeRate($v_id,$source_currency,$target_currency,$loyaltyDiscountAmount);
				$loyaltyDiscountAmount   =  $exchangeLoyaltyDiscountAmount['amount'];  
				$extraChargeAmount = 0; //Extra Tax Amount charge

				$exchangeNetAmount = getExchangeRate($v_id,$source_currency,$target_currency,$item->total);
				$netAmount = (float)$exchangeNetAmount['amount'];
				$taxRegime = 'G' ;// G for GST And V for Vat

				// $netAmount = (float)$item->total;
				$stores = Store::where('v_id', $v_id)->where('store_id', $store_id)->first();
				$country=Country::select('name','sortname')->where('id',$stores->country)->first();
                if(!$country){
                   $taxRegime = 'G' ;
                }elseif($country->sortname=='IN'){
                  $taxRegime = 'G' ;
                }else{
                  $taxRegime = 'V' ;  
                }
				// G for GST And V for Vat

					#### Need to store the total tax rate ####
				    //$exchange$taxRatet=
					$taxRate = (float)$tdata->igst + (float)$tdata->cgst + (float)$tdata->sgst + (float)$tdata->cess;
					$taxDescription = $tdata->tax_name ; //Tax name, Rate name, Description

					// $taxAmount = (float)$item->tax;
					$taxAmount = getExchangeRate($v_id,$source_currency,$target_currency,$item->tax);
					$exchangeTaxableAmount = getExchangeRate($v_id,$source_currency,$target_currency,$tdata->taxable);
					$taxAmount = (float) $taxAmount['amount'];
					$taxableAmount = (float) $exchangeTaxableAmount['amount'];
					$igstRate = (float)$tdata->igst;
					$cgstRate = (float)$tdata->cgst;
					$sgstRate = (float)$tdata->sgst;
					$cessRate = (float)$tdata->cess;

					$exchangeigstAmount =  getExchangeRate($v_id,$source_currency,$target_currency,$tdata->igstamt);
					$exchangecgstAmount =  getExchangeRate($v_id,$source_currency,$target_currency,$tdata->cgstamt);
					$exchangesgstAmount =  getExchangeRate($v_id,$source_currency,$target_currency,$tdata->sgstamt);
					$exchangecessAmount =  getExchangeRate($v_id,$source_currency,$target_currency,$tdata->cessamt);

					$igstAmount  =  (float) $exchangeigstAmount['amount'];
					$cgstAmount  =  (float) $exchangecgstAmount['amount'];
					$sgstAmount  =  (float) $exchangesgstAmount['amount'];
					$cessAmount  =  (float) $exchangecessAmount['amount'];
					#### Item is not mapped ####
					$itemRemarks = '';

					if($taxRegime == 'V'){
						$igstRate = 0;
						$cgstRate = 0;
						$sgstRate = 0;
						$cessRate = 0;
						$igstAmount  =  0;
						$cgstAmount  =  0;
						$sgstAmount  =  0;
						$cessAmount  =  0;

						$request['ownerGSTINNo'] = null;
					}

				$salesPersonId = 0;
				$salesPersonCode = '';
				$salesPersonName = $id;
				if( $item->salesman_id > 0 ){
					$salesMan = Vendor::select('first_name','last_name','employee_code')->where('id', $item->salesman_id)->first();
					if($salesMan){
						$salesPersonId = $item->salesman_id;
						$salesPersonName = $salesMan->first_name.' '.$salesMan->last_name;
						$salesPersonCode = $salesMan->employee_code;
					}
				}






				$posBillItem =	[
					'serialNo' => $serialNo , //Int 
					'itemId' => $itemId, //String
					'barcode' => $item->barcode , // This column is add for RJ Corp
					'hsnsacCode' => (string)$hsnsacCode, //String
					'quantity' => $quantity, //Int
					'mrp' => $mrp, //Int
					'rsp' => $rsp, //Int
					'esp' => $esp, //Int
					'promoDiscountAmount' => $promoDiscountAmount, //Int
					'manualDiscountAmount' => $manualDiscountAmount, //Int
					'billDiscountApportionedAmount' => $billDiscountApportionedAmount, //Int
					'loyaltyDiscountAmount' => $loyaltyDiscountAmount, //Int
					'extraChargeAmount' => $extraChargeAmount, //Int
					'netAmount' => $netAmount, //Int
					'taxRegime' => $taxRegime, //String
					'taxRate' => $taxRate, //Int
					'taxDescription' => $taxDescription, //String
					'taxAmount' => $taxAmount, //Int
					'taxableAmount' => $taxableAmount, //Int
					'igstRate' => $igstRate, //Int
					'igstAmount' => $igstAmount, //Int
					'cgstRate' => $cgstRate, //Int
					'cgstAmount' => $cgstAmount, //Int
					'sgstRate' => $sgstRate, //Int
					'sgstAmount' => $sgstAmount, //Int
					'cessRate' => $cessRate, //Int
					'cessAmount' => $cessAmount, //Int
					'optionalInfo' => [
						'itemRemarks' => $id, //String
						'itemPromoDiscription' => $itemPromoDiscription,//This column is add for RJ Corp
						'itemPromoCode' => $id, //String
						'itemPromoNo' => $id, //String 
						'itemPromoName' => $id, //String
						'itemPromoStartDate' => $id, //String 2019-11-27T11:59:52.182Z
						'itemPromoEndDate' => $id, //String 2019-11-27T11:59:52.182Z
						'itemPromoStartTime' => $id, //String 2019-11-27T11:59:52.182Z
						'itemPromoEndTime' => $id, //String 2019-11-27T11:59:52.182Z
						'itemPromoSlabRangeFrom' => 0, //Int
						'itemPromoSlabRangeTo' => 0, //Int
						'itemPromoBenefit' => $id, //String
						'itemPromoDiscType' => $id, //String
						'itemPromoDiscFactor' => 0, //String
						'itemPromoDiscPriceBasis' => $id, //String
						'itemPromoBuySatisfied' => 0, //Int
						'itemPromoGetSatisfied' => 0, //Int
						'itemPromoSatisfied' => 0, //Int
						'itemDiscountId' => $itemDiscountId, //Int
						'itemDiscountDesc' => $itemDiscountDesc, //String
						'itemDiscountBasis' => $id, //String
						'itemDiscountFactor' => 0, //Int
						'billPromoSatisfied' => 0, //Int
						'billDiscountBasis' => $id, //String
						'billDiscountFactor' => 0, //String
						'originalBillNo' => $id, //String
						'originalBillDate' => $id, //String 2019-11-27T11:59:52.182Z
						'originalBillStoreId' => $id, //String
						'returnReason' => "", //String
						'salesPersonId' => $salesPersonId, //Int
						'salesPersonCode' => $salesPersonCode, //Rj Corp Fields
						'salesPersonName' => $salesPersonName, //String
						'extraChargeFactor' => 0, //Int
					]
				];

				$posBillItems[] = $posBillItem;
			}

		}else{
			$error_msg = 'Bill Push Error: Error for- '.$error_for.' Message: Unable to find and items in invoices';
			// dd('No list');
			Log::error($error_msg );
			if(!isset($params['unsync']) == 1){
				$outBound->error_before_call = $error_msg;
				$outBound->save();
			

				return ['error' => true , 'message' => $error_msg ];
				exit;
			}
		}

		$request['posBillItems'] = $posBillItems;

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
		$creditNoteNumber='';
		foreach ($payments as $key => $payment) {
			$displayOrder++;
			#### Implement mop payments  #### 
			
			$exchangebaseAmt =getExchangeRate($v_id,$source_currency,$target_currency,$payment->amount);
			$baseAmt = (float)$exchangebaseAmt['amount'];

			if($payment->method == 'cash' && $payment->cash_collected !=null){
				$exchangebaseTender = getExchangeRate($v_id,$source_currency,$target_currency,$payment->cash_collected);
				if($exchangebaseTender['amount'] == 0){
					$baseTender = $baseAmt;
				}else{
					$baseTender=$exchangebaseTender['amount'];
				}
				$baseTender=$exchangebaseTender['amount'];
				$bBalance = (float)$payment->cash_return==null?0:$payment->cash_return;
				$exchangebaseBalance= getExchangeRate($v_id,$source_currency,$target_currency,$bBalance);
				$baseBalance = (float)$exchangebaseBalance['amount'];
			}else{
				$exchangebaseTender = getExchangeRate($v_id,$source_currency,$target_currency,$payment->amount);
				$baseTender = (float) $exchangebaseTender['amount'];
				$baseBalance = (float)0;
			}

			$method = $payment->method;
			if($payment->method == 'card'){
				$method = 'CARD_OFFLINE';
			}else if($payment->method == 'google_tez'){
				$method = 'GOOGLE_TEZ_OFFLINE';
			}else if($payment->method == 'paytm'){
				$method = 'PAYTM_OFFLINE';
			}else if($payment->method == 'upi'){
				$method = 'UPI';
			}else if($payment->method == 'voucher_credit'){
				$method = 'voucher';
			}


			$vendorMop = VendorMop::join('mops','mops.id','vendor_mop_mapping.mop_id')
			                        ->where('vendor_mop_mapping.v_id', $v_id)
			                        ->where('vendor_mop_mapping.store_id',$store_id)
			                        ->where('mops.code', $method)
			                        ->first();
			if(!$vendorMop){
				$vendorMop = VendorMop::join('mops','mops.id','vendor_mop_mapping.mop_id')
			                        ->where('vendor_mop_mapping.v_id', $v_id)
			                        // ->where('vendor_mop_mapping.store_id',$store_id)
			                        ->where('mops.code', $method)
			                        ->first();
			}	
				                      
			if(!$vendorMop && $method!='debit_voucher'){
				$error_msg = 'Bill Push Error: Error for- '.$error_for.' Message: Unable to find Mop Mapping in Vendor Mop Table ';
				Log::error($error_msg);
				if(!isset($params['unsync']) == 1){
					$outBound->error_before_call = $error_msg;
					$outBound->save();
				

					return [ 'error' => true , 'message' => $error_msg ];
					exit;
				}
			}

			if($payment->method == 'credit_note_received'){
              
               $mopShortCode = 'CNR'; //Need to send ginesys code
			   $mopType = 'CNR';
			   $creditNoteNumber =(string) $payment->pay_id;
			}elseif($method=='debit_voucher'){
				$mopShortCode='DNI';
				$mopType='DNI';
				$debitNoteNumber = DepRfdTrans::join('cr_dr_voucher','cr_dr_voucher.dep_ref_trans_ref','dep_rfd_trans.id')
										->select('cr_dr_voucher.voucher_no')
			                            ->where('dep_rfd_trans.trans_src_ref', $payment->order_id)
			                            ->first();
			                        
			}else{
				if(isset($vendorMop->ref_mop_code)){
					$mopShortCode = $vendorMop->ref_mop_code; //Need to send ginesys code
				}else{
					$mopShortCode = 0;
				}
				if(isset($vendorMop->ref_mop_type)){
					$mopType = $vendorMop->ref_mop_type; //Need to send ginesys Type
				}else{
					$mopType = 0;
				}

			}
			
			$forexRate = 0;
			$forexTender = 0;
			$forexBalance = 0;
			$forexAmt = 0;

			#### We Need to Implement this ####
			//Should be 1 for only Gift Voucher type mop only
			$isDenoApplicable = $payment->method == 'GIFT_VOUCHER'?1:0;
			if($isDenoApplicable == 1){

			    $gvTransactionlogs=GiftVoucherTransactionLogs::select('voucher_code','amount','type')
			  											->where('ref_order_id',$payment->order_id)
												        ->where('type','DEBIT_GV')
												        ->get();
				foreach ($gvTransactionlogs as $key => $value) {

					$mopDeno[]=[
								'denoNumber' => $value->voucher_code , // String nullable 
								'denoDescription' => $value->type, // String nullable
								'perUnitValue' => abs($value->amount), // Double 
								'numberOfUnits' =>1, // Int
								'denoAmount' => abs($value->amount) , // Double
							];			        	
				}							        
			  
			}else{
				// $mopDeno = [
				// 	[
				// 	'denoNumber' => '' , // String nullable 
				// 	'denoDescription' => '', // String nullable
				// 	'perUnitValue' => 0.0, // Double 
				// 	'numberOfUnits' =>0, // Int
				// 	'denoAmount' => 0.0 , // Double
				// 	]
				// ];

				$mopDeno =  null;	
			}
			
			
			/*if($isDenoApplicable != 0){
				$mopDeno = [
					[
					'denoNumber' => $id , // String nullable 
					'denoDescription' => '', // String nullable
					'perUnitValue' => $id, // Double 
					'numberOfUnits' =>1, // Int
					'denoAmount' => $id , // Double
					]
				];
			}*/
			$debitNoteNumber=($method=='debit_voucher'?$debitNoteNumber->voucher_no:$id);
			$posBillMOP = [
			
				'mopShortCode' => $mopShortCode, //String
				'mopType' => $mopType, //String
				'displayOrder' => $displayOrder, //Double
				'baseTender' => $baseTender, //Double
				'baseBalance' => (float)$baseBalance, //Double
				'baseAmt' => $baseAmt, //Double
				'forexRate' => $forexRate, //Double
				'forexBalance' => $forexBalance, //Double
				'forexTender' => $forexTender, //Double
				'forexAmt' => $forexAmt, //Double
				'isDenoApplicable' => $isDenoApplicable, //Int
				'isBaseCurrency' => 1, //Int

				'mopDeno' => $mopDeno,

				'optionalInfo' => [
					'creditCardCommissionPercentage' => 0, //Double
					'creditCardCommissionAmount' => 0, //Double
					'creditNoteNumber' => $creditNoteNumber, //String
					'debitNoteNumber' => $debitNoteNumber //String
				]
				
			];

			$posBillMOPList[] = $posBillMOP;
		}
		$request['posBillMOP'] = $posBillMOPList;

		if(isset($params['unsync']) == 1){
			return $request;
		}
		//$request['mopDeno'] = $mopDeno;
		//dd(json_encode($request));
		$outBound->api_request = json_encode($request);
        $outBound->save();
		
		$config = new ConfigController($v_id);
		$apiCaller = new  ApiCallerController([
			'url' => $config->apiBaseUrl.'/POSBill',
			'data'=> $request, 
			'header' => [ 'Content-Type:application/json'],
			'auth_type' => $config->authType,
			'auth_token' => $config->authToken
		]);
		# extract the body
		$response = $apiCaller->call();
        $outBound->api_response = $response['body'];
        $outBound->response_status_code = $response['header_status'];
        $outBound->doc_no = $billNo;
        $outBound->save();

        // Sync Status
        if(in_array($response['header_status'], [200, 201])) {
        	$invoice->sync_status = '1';
        	$invoice->save();
        } else {
        	$invoice->sync_status = '2';
        	$invoice->save();
        }

        return $config->handleApiResponse($response);

		// if(json_encode($response)){

		// }
		// dd(json_decode($response));
		//return $response;
	}


	public function returnInvoicePush($params){


        $id = null;
        if(!isset($params['unsync']) == 1){
	    	$outBound = $params['outBound'];
	    }
    	$v_id = $params['v_id'];
    	$store_id = $params['store_id'];
    	$invoice_id = $params['invoice_id'];
    	$client_id = $params['client_id'];

    	$client = $params['client'];
    	$error_for = $params['error_for'].' Trans: RETURN ';
    	$store = $params['store'];
    	$vendor = $params['vendor'];
    	$source_currency= null;
    	$target_currency=null;
    	JobdynamicConnection($v_id);
    	$currecy=getStoreAndClientCurrency($v_id,$store_id);
    	if($currecy['status']=='error'){
            $error_msg = $currecy['message'];
            if(!isset($params['unsync']) == 1){
	    		$outBound->error_before_call = $error_msg;
				$outBound->save();
	    		Log::error($error_msg);

	    		return [ 'error' => true , 'message' => $error_msg ];
	    	}

    	}else{

         $source_currency = $currecy['store_currency'];
         $target_currency = $currecy['client_currency']; 


    	}

    	#### Stock point is not finalize ####
    	//$stock = StockPoints::select('ref_stock_code')->where('v_id', $v_id)->where('store_id', $store_id)->first();
    	$stockPointId = null;
    	$StockpointhdId =  StockPoints::select('stock_point_header_id')->where('store_id', $store_id)->where('is_default', '1')->first();

		$stockPointSellable =  StockPointHeader::select('ref_stock_point_header_code')->where('id', $StockpointhdId->stock_point_header_id)->first();
		
    	if($stockPointSellable && $stockPointSellable->ref_stock_point_header_code!='' && $stockPointSellable->ref_stock_point_header_code != null){

    		$stockPointId = $stockPointSellable->ref_stock_point_header_code;
    		// $stockPointId = explode('-', $stockPointId);
    		// $stockPointId = $stockPointId[1];
    	}else{

    	

			$error_msg = 'Invoice Push Error: '.$error_for.'- Sellling Stock Point , Message: Unable to find Selling stock Point ';
    		// dd('indie this');
    		if(!isset($params['unsync']) == 1){
	    		$outBound->error_before_call = $error_msg;
				$outBound->save();
	    		Log::error($error_msg);

	    		return [ 'error' => true , 'message' => $error_msg ];
	    	}
			
    	}
    	$currecy=getStoreAndClientCurrency($v_id,$store_id);
    	if($currecy['status']=='error'){
            $error_msg = $currecy['message'];
            if(!isset($params['unsync']) == 1){
	    		$outBound->error_before_call = $error_msg;
				$outBound->save();
	    		Log::error($error_msg);

	    		return [ 'error' => true , 'message' => $error_msg ];
	    	}

    	}else{

         $source_currency = $currecy['store_currency'];
         $target_currency = $currecy['client_currency']; 

    	}

    	$invoice = Invoice::where('id', $invoice_id)->first();
    	$billNo = $invoice->invoice_id;

    	

    	#### nNeed to capture bill sequence number ####
    	$billNoSequence = (int)$invoice->invoice_sequence; // $invoice->id;
    	$billDate = str_replace(' ','T',$invoice->created_at);

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
    	
    	$exchangeRound = getExchangeRate($v_id,$source_currency,$target_currency,$roundOff);
    	$roundOff = $exchangeRound['amount'];

    	$netPayable = (float)round( $invoice->total ,2);
    	$exchangeNetPayable = getExchangeRate($v_id,$source_currency,$target_currency,$netPayable);
    	//dd($netPayable);
    	$netPayable      =  $exchangeNetPayable['amount'];
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

    	$billRemarks = null;
    	if($invoice->remark !='' && $invoice->remark!= null){
    		$billRemarks = $invoice->remark;
    	}
    	//$billPromoCode = $invoice->
		$request = [];

		$request['storeId']  = (int) $store->store_reference_code;  //Int
		$request['stockPointId']  = (int)$stockPointId;  //int;
		$request['billNo']  = $billNo; //string
		$request['billNoSequence']  = $billNoSequence; //int; 
		$request['billDate']  = $billDate;  //String 2019-11-27T11:59:52.182Z
		$request['terminalName']  = $terminalName; //String
		$request['noOfPrints']  = $noOfPrints;  //Int
		$request['roundOff']  = -$roundOff;  //Int
		$request['netPayable']  = -$netPayable;  //Int
		$request['tradeGroupId']  = $tradeGroupId;  //Int
		$request['createdBy']  = $cashierName;  //Int
		$request['ownerGSTINNo']  = $ownerGSTINNo;  //String
		$request['ownerGSTINStateCode']  = $ownerGSTINStateCode;  //String
		$request['counterPartyGSTINNo']  = $counterPartyGSTINNo;  //String
		$request['counterPartyGSTINStateCode']  = $counterPartyGSTINStateCode;  //String
		$request['counterPartyGSTINRegDate']  = $counterPartyGSTINRegDate;  //String 2019-11-27T11:59:52.182Z
		$request['ReplaceTransaction']  = $replaceTransaction;  //Int
		
		//Need to confirm that whole field is optional or not
		$request['optionalInfo'] = [

			'billRemarks' => $billRemarks, //String
			'billPromoCode' => $id, //String
			'billPromoNo' => $id, //String
			'billPromoName' => $id, //String
			'billPromoStartDate' => $id, //String 2019-11-27T11:59:52.182Z
			'billPromoEndDate' => $id, //String 2019-11-27T11:59:52.182Z
			'billPromoStartTime' => $id, //String 2019-11-27T11:59:52.182Z
			'billPromoEndTime' => $id, //String 2019-11-27T11:59:52.182Z
			'billPromoSlabRangeFrom' => 0, //Int
			'billPromoSlabRangeTo' => 0, //Int
			'billPromoBenefit' => $id, //String
			'billDiscountId' => $id, //Int
			'billDiscountDescription' => $id, //String
			'billDiscountMaxAmount' => 0, //Int
			'billDiscountMinApplicableAmount' => 0, //Int
			'manualPromoCleared' => $id, //String
			'couponCode' => $id, //String
			'couponOfferCode' => 0, //String
			'redemptionMobileNo' => $id, //String
			'otp' => $id //String
		];


		#### Need to do the mapping with Ginesys implement in ####
		$custCode = null;
		if( $invoice->user_id > 0){
			$custCode = (string) $invoice->user_id; // need to change and ginesys code
		}
		$custIsdCode = null;
		// $country = Country::where('id' ,$cust->address->country_id )->first();
		if($invoice->customer_phone_code != null && $invoice->customer_phone_code !=''){
			$custIsdCode = $invoice->customer_phone_code;
			$custIsdCode = str_replace('+','',$custIsdCode);
		}
		$custMobileNo = (string)$invoice->customer_number;
		$custFirstname = null;
		if($invoice->customer_first_name != null && $invoice->customer_first_name !=''){
			$custFirstname = $invoice->customer_first_name;
		}else{
		   $custFirstname = "Dummy";
		}
		#### Need to Capture ####
		$custMiddlename = null;
		$custLastname = null;
		if($invoice->customer_last_name != null && $invoice->customer_last_name !=''){
			$custLastname = $invoice->customer_last_name;
		}else{
			if(substr($custMobileNo, 0,1) == '3'){
				$custLastname = "Customer";	
			}else{
				$custLastname = ".";	
			}
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
		if($invoice->customer_email != null && $invoice->customer_email !=''){
			$custEmail = $invoice->customer_email;
		}

		$customer_address1 = null;
		if($invoice->customer_address !=null && $invoice->customer_address != ''){
         $customer_address1 = $invoice->customer_address;
		}


		$request['posCustomer'] = [
			'isdCode' => $custIsdCode, //String
			'mobileNo' => $custMobileNo, //String
			'firstName' => $custFirstname, //String
			'middleName' => $custMiddlename, //String
			'lastName' => $custLastname, //String
			'gender' => $custGender, //String Ginesys have M // F
			'customerGroup' => 'POSCUST', //This is added for RJ Corp Hard coded right Now
			
			//Need to confirm that whole field is optional or not
			'optionalInfo' => [
				'salutation' => $id, //String
				'email' => $custEmail, //String
				'phone1' => $id, //String
				'phone2' => $id, //String
				'address1' => $customer_address1, //String
				'address2' => $id, //String
				'address3' => $id, //String
				'city' => $id, //String
				'country' => $id, //String
				'district' => $id, //String
				'state' => $id, //String
				'pincode' => $id, //String
				'remarks' => $id, //String
				'profession' => $id, //String
				'spouseName' => $id, //String
				'dateOfBirth' => $custDateOfBirth, //String 2019-11-27T11:59:52.182Z
				'dateOfAnniversary' => $id, //String 2019-11-27T11:59:52.182Z
				'pan' => $id //String
			]
		];

		if($invoice->customer_first_name == 'Dummy') {
			$request['posCustomer'] = null;
		} 

		$invoiceDetails = InvoiceDetails::where('v_id', $v_id)->where('t_order_id', $invoice->id)->get();
	    $itemTotal   = $invoiceDetails->sum('total');
        $roundOff = 0.0;
    	if($invoice->round_off==null || $invoice->round_off==''){
    		//dd('h');
    		$roundOff = (float) round( $invoice->total- $itemTotal,2);
            $request['roundOff']  = round($roundOff*-1,2); 		
    	}
		$posBillItems = [];
		$serialNo = 0;
		if(!$invoiceDetails->isEmpty()){
			foreach ($invoiceDetails as $key => $item) {
				// DB::enableQueryLog();
				$bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $item->barcode)->first();
				if($bar){
					$vendorSku = VendorSku::select('item_id','vendor_sku_detail_id','sku')->where(['vendor_sku_detail_id' => $bar->vendor_sku_detail_id, 'v_id' => $v_id])->first();

				}
				$vendorItems = VendorItems::where('v_id', $v_id)->where('item_id', $vendorSku->item_id)->first();
				// dd(DB::getQueryLog());
				if(!$vendorItems){
					$error_msg = 'Bill Push Error: Error for- '.$error_for.' Message: Unable to find item ';
					Log::error($error_msg);
					if(!isset($params['unsync']) == 1){
						$outBound->error_before_call = $error_msg;
						$outBound->save();

						return [ 'error' => true , 'message' => $error_msg ];
						exit;
					}
				}
				$tdata = json_decode($item->tdata);
				$serialNo++; 
				#### This id should be of giensys id ####
				$itemId = $vendorItems->ref_item_code; //
				$hsnsacCode = (int)$tdata->hsn; //
				$quantity = (float)$item->qty;
				$mrp = (float)$item->unit_mrp;
				$exchangemrp =getExchangeRate($v_id,$source_currency,$target_currency,$mrp);
				$mrp = $exchangemrp['amount'];
				$rsp = (float)$item->unit_csp;
				$exchangersp =getExchangeRate($v_id,$source_currency,$target_currency,$rsp);
				$rsp  = $exchangersp['amount'];
				#### Need to implement this ####
				$esp = $rsp; // if mrp is selling the pass mrp if rsp is selling then passs rsp
				$promoDiscountAmount = (float)$item->discount; //Promo discount
				$itemPromoDiscription = '';
				if($item->discount > 0){
					//Need to change this logic
					$offer = json_decode($item->section_target_offers);

					if(isset($offer->offer_data->applied_offers)){

						foreach ($offer->offer_data->applied_offers as $off){
							
							if(isset($off->message)){
								$master = DB::table('pro_promo_master')->select('DESCRIPTION')->where('name',$off->message)->first();
								if($master){
									$itemPromoDiscription = $master->DESCRIPTION;
								}
							}
						}
					}

				}
				$exchangepromoDiscountAmount =getExchangeRate($v_id,$source_currency,$target_currency,$promoDiscountAmount);
				$promoDiscountAmount = $exchangepromoDiscountAmount['amount'];
				$itemLevelmanualDiscount=0;
	            $itemDiscountId = $id;
				$itemDiscountDesc = $id;
				
				if($item->item_level_manual_discount!=null){
	                $iLmd = json_decode($item->item_level_manual_discount);
	                $itemLevelmanualDiscount = (float)$iLmd->discount;
	                if(!empty($iLmd->md_id) ){
	                	$manualD = ManualDiscount::select('description')->find($iLmd->md_id);
	                	if($manualD){
		                	$itemDiscountId = $iLmd->md_id;
		                	$itemDiscountDesc = $manualD->description;
	                	}
	                }
	            }

				$manualDiscountAmount = (float)$itemLevelmanualDiscount;
				$exchangemanualDiscountAmount =getExchangeRate($v_id,$source_currency,$target_currency,$manualDiscountAmount);
				$manualDiscountAmount   = $exchangemanualDiscountAmount['amount'];
				$billDiscountApportionedAmount = (float)$item->bill_buster_discount + (float)$item->manual_discount ; // Bill level promotion dis
				$exchangebillDiscountApportionedAmount = getExchangeRate($v_id,$source_currency,$target_currency,$billDiscountApportionedAmount);
				$billDiscountApportionedAmount   = $exchangebillDiscountApportionedAmount['amount']; 

				$loyaltyDiscountAmount = (float)$item->lpdiscount; // Loyality point Dis
				$exchangeLoyaltyDiscountAmount = getExchangeRate($v_id,$source_currency,$target_currency,$loyaltyDiscountAmount);
				$loyaltyDiscountAmount   =  $exchangeLoyaltyDiscountAmount['amount'];  
				$extraChargeAmount = 0; //Extra Tax Amount charge

				$exchangeNetAmount = getExchangeRate($v_id,$source_currency,$target_currency,$item->total);
				$netAmount = (float)$exchangeNetAmount['amount'];
				$stores = Store::where('v_id', $v_id)->where('store_id', $store_id)->first();
				$country=Country::select('name','sortname')->where('id',$stores->country)->first();
                if(!$country){
                   $taxRegime = 'G' ;
                }elseif($country->sortname=='IN'){
                  $taxRegime = 'G' ;
                }else{
                  $taxRegime = 'V' ;  
                }
				//$taxRegime = 'G' ;// G for GST And V for Vat
					#### Need to store the total tax rate ####
					$taxRate = (float)$tdata->igst + (float)$tdata->cgst + (float)$tdata->sgst + (float)$tdata->cess;
					$taxDescription = $tdata->tax_name ; //Tax name, Rate name, Description

					// $taxAmount = (float)$item->tax;
					$taxAmount = getExchangeRate($v_id,$source_currency,$target_currency,$item->tax);
					$exchangeTaxableAmount = getExchangeRate($v_id,$source_currency,$target_currency,$tdata->taxable);
					$taxAmount = (float) $taxAmount['amount'];
					$taxableAmount = (float) $exchangeTaxableAmount['amount'];
					$igstRate = (float)$tdata->igst;
					$cgstRate = (float)$tdata->cgst;
					$sgstRate = (float)$tdata->sgst;
					$cessRate = (float)$tdata->cess;

					$exchangeigstAmount =  getExchangeRate($v_id,$source_currency,$target_currency,$tdata->igstamt);
					$exchangecgstAmount =  getExchangeRate($v_id,$source_currency,$target_currency,$tdata->cgstamt);
					$exchangesgstAmount =  getExchangeRate($v_id,$source_currency,$target_currency,$tdata->sgstamt);
					$exchangecessAmount =  getExchangeRate($v_id,$source_currency,$target_currency,$tdata->cessamt);

					$igstAmount  =  (float) $exchangeigstAmount['amount'];
					$cgstAmount  =  (float) $exchangecgstAmount['amount'];
					$sgstAmount  =  (float) $exchangesgstAmount['amount'];
					$cessAmount  =  (float) $exchangecessAmount['amount'];					#### Item is not mapped ####
					$itemRemarks = '';

					if($taxRegime == 'V'){
						$igstRate = 0;
						$cgstRate = 0;
						$sgstRate = 0;
						$cessRate = 0;
						$igstAmount  =  0;
						$cgstAmount  =  0;
						$sgstAmount  =  0;
						$cessAmount  =  0;

						$request['ownerGSTINNo'] = null;
					}

				$salesPersonId = 0;
				$salesPersonCode = '';
				$salesPersonName = $id;
				if( $item->salesman_id > 0 ){
					$salesMan = Vendor::select('first_name','last_name','employee_code')->where('id', $item->salesman_id)->first();
					if($salesMan){
						$salesPersonId = $item->salesman_id;
						$salesPersonName = $salesMan->first_name.' '.$salesMan->last_name;
						$salesPersonCode = $salesMan->employee_code;
					}
				}

				$posBillItem =	[
					'serialNo' => $serialNo , //Int 
					'itemId' => $itemId, //String
					'barcode' => $item->barcode , // This column is add for RJ Corp
					'hsnsacCode' => (string)$hsnsacCode, //String
					'quantity' => -$quantity, //Int
					'mrp' => $mrp, //Int
					'rsp' => $rsp, //Int
					'esp' => $esp, //Int
					'promoDiscountAmount' => $promoDiscountAmount>0?-$promoDiscountAmount:$promoDiscountAmount, //Int
					'manualDiscountAmount' => $manualDiscountAmount>0?-$manualDiscountAmount:$manualDiscountAmount, //Int
					'billDiscountApportionedAmount' => $billDiscountApportionedAmount>0?-$billDiscountApportionedAmount:$billDiscountApportionedAmount, //Int
					'loyaltyDiscountAmount' => $loyaltyDiscountAmount>0?-$loyaltyDiscountAmount:$loyaltyDiscountAmount, //Int
					'extraChargeAmount' => $extraChargeAmount>0?-$extraChargeAmount:$extraChargeAmount, //Int
					'netAmount' => -$netAmount, //Int
					'taxRegime' => $taxRegime, //String
					'taxRate' => $taxRate, //Int
					'taxDescription' => $taxDescription, //String
					'taxAmount' => $taxAmount>0?-$taxAmount:$taxAmount, //Int
					'taxableAmount' => $taxableAmount>0?-$taxableAmount:$taxableAmount, //Int
					'igstRate' => $igstRate, //Int
					'igstAmount' => $igstAmount>0?-$igstAmount:$igstAmount, //Int
					'cgstRate' => $cgstRate, //Int
					'cgstAmount' => $cgstAmount>0?-$cgstAmount:$cgstAmount, //Int
					'sgstRate' => $sgstRate, //Int
					'sgstAmount' => $sgstAmount>0?-$sgstAmount:$sgstAmount, //Int
					'cessRate' => $cessRate, //Int
					'cessAmount' => $cessAmount>0?-$cessAmount:$cessAmount, //Int
					'optionalInfo' => [
						'itemRemarks' => $id, //String
						'itemPromoDiscription' => $itemPromoDiscription,//This column is add for RJ Corp
						'itemPromoCode' => $id, //String
						'itemPromoNo' => $id, //String
						'itemPromoName' => $id, //String
						'itemPromoStartDate' => $id, //String 2019-11-27T11:59:52.182Z
						'itemPromoEndDate' => $id, //String 2019-11-27T11:59:52.182Z
						'itemPromoStartTime' => $id, //String 2019-11-27T11:59:52.182Z
						'itemPromoEndTime' => $id, //String 2019-11-27T11:59:52.182Z
						'itemPromoSlabRangeFrom' => 0, //Int
						'itemPromoSlabRangeTo' => 0, //Int
						'itemPromoBenefit' => $id, //String
						'itemPromoDiscType' => $id, //String
						'itemPromoDiscFactor' => 0, //String
						'itemPromoDiscPriceBasis' => $id, //String
						'itemPromoBuySatisfied' => 0, //Int
						'itemPromoGetSatisfied' => 0, //Int
						'itemPromoSatisfied' => 0, //Int
						'itemDiscountId' => $itemDiscountId, //Int
						'itemDiscountDesc' => $itemDiscountDesc, //String
						'itemDiscountBasis' => $id, //String
						'itemDiscountFactor' => 0, //Int
						'billPromoSatisfied' => 0, //Int
						'billDiscountBasis' => $id, //String
						'billDiscountFactor' => 0, //String
						'originalBillNo' => $id, //String
						'originalBillDate' => $id, //String 2019-11-27T11:59:52.182Z
						'originalBillStoreId' => $id, //String
						'returnReason' => "", //String
						'salesPersonId' => $salesPersonId, //Int
						'salesPersonCode' => $salesPersonCode, //Rj Corp Field
						'salesPersonName' => $salesPersonName, //String
						'extraChargeFactor' => 0, //Int
					]
				];

				$posBillItems[] = $posBillItem;
			}

		}else{
			$error_msg = 'Bill Push Error: Error for- '.$error_for.' Message: Unable to find and items in invoices';
			// dd('No list');
			Log::error($error_msg );
			if(!isset($params['unsync']) == 1){
				$outBound->error_before_call = $error_msg;
				$outBound->save();

				return ['error' => true , 'message' => $error_msg ];
				exit;
			}
		}

		$request['posBillItems'] = $posBillItems;

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
			$creditNoteNumber=null; 
			if($payment->method == 'credit_note_issued'){
              
               $mopShortCode = 'CNI'; //Need to send ginesys code
			   $mopType = 'CNI';
			   $creditNoteNumber =(string) $payment->pay_id;
			   $exchangeTender= getExchangeRate($v_id,$source_currency,$target_currency,$payment->amount);
			   $baseTender = -(float)$exchangeTender['amount'];
			   $baseBalance = (float)0;

			}else{
				$baseTender = (float)0;
				$exchangeBaseBalance= getExchangeRate($v_id,$source_currency,$target_currency,$payment->amount);
				$baseBalance = (float)$exchangeBaseBalance['amount'];
			

				$vendorMop = VendorMop::join('mops','mops.id','vendor_mop_mapping.mop_id')
				                        ->where('vendor_mop_mapping.v_id', $v_id)
				                        ->where('vendor_mop_mapping.store_id',$store_id)
				                        ->where('mops.code', $payment->method)
				                        ->first();
				if(!$vendorMop){
					$vendorMop = VendorMop::join('mops','mops.id','vendor_mop_mapping.mop_id')
				                        ->where('vendor_mop_mapping.v_id', $v_id)
				                        // ->where('vendor_mop_mapping.store_id',$store_id)
				                        ->where('mops.code', $payment->method)
				                        ->first();
				}		
						                      
				if(!$vendorMop){
					$error_msg = 'Bill Push Error: Error for- '.$error_for.' Message: Unable to find Mop Mapping in Vendor Mop Table ';
					Log::error($error_msg);
					if(!isset($params['unsync']) == 1){
						$outBound->error_before_call = $error_msg;
						$outBound->save();

						return [ 'error' => true , 'message' => $error_msg ];
						exit;
					}
				}

				$mopShortCode = $vendorMop->ref_mop_code; //Need to send ginesys code
				$mopType = $vendorMop->ref_mop_type; //Need to send ginesys Type

	        }
			$exchangeBaseAmt  = getExchangeRate($v_id,$source_currency,$target_currency,$payment->amount);
			$baseAmt = (float)$exchangeBaseAmt['amount'];
			$forexRate = 0;
			$forexTender = 0;
			$forexBalance = 0;
			$forexAmt = 0;

			#### We Need to Implement this ####
			//Should be 1 for only Gift Voucher type mop only
			$isDenoApplicable = 0;
			if($isDenoApplicable == 0){
			  $mopDeno =  null;	
			}
			
			
			if($isDenoApplicable != 0){
				$mopDeno = [
					[
					'denoNumber' => $id , // String nullable 
					'denoDescription' => $id , // String nullable
					'perUnitValue' => $id, // Double 
					'numberOfUnits' => $id, // Int
					'denoAmount' => $id , // Double
					]
				];
			}

			$posBillMOP = [
			
				'mopShortCode' => $mopShortCode, //String
				'mopType' => $mopType, //String
				'displayOrder' => $displayOrder, //Double
				'baseTender' => $baseTender, //Double
				'baseBalance' => $baseBalance, //Double
				'baseAmt' => -$baseAmt, //Double
				'forexRate' => $forexRate, //Double
				'forexBalance' => $forexBalance, //Double
				'forexTender' => $forexTender, //Double
				'forexAmt' => $forexAmt, //Double
				'isDenoApplicable' => 0, //Int
				'isBaseCurrency' => 1, //Int

				'mopDeno' => $mopDeno,

				'optionalInfo' => [
					'creditCardCommissionPercentage' => 0, //Double
					'creditCardCommissionAmount' => 0, //Double
					'creditNoteNumber' => $creditNoteNumber, //String
					'debitNoteNumber' => $id //String
				]
				
			];

			$posBillMOPList[] = $posBillMOP;
		}
		$request['posBillMOP'] = $posBillMOPList;
		if(isset($params['unsync']) == 1){
			return $request;
		}
		$outBound->api_request = json_encode($request);
        $outBound->save();

		// dd(json_encode($request));
		$config = new ConfigController($v_id);
		$apiCaller = new  ApiCallerController([
			'url' => $config->apiBaseUrl.'/POSBill',
			'data'=> $request, 
			'header' => [ 'Content-Type:application/json'],
			'auth_type' => $config->authType,
			'auth_token' => $config->authToken
		]);
		# extract the body
		$response = $apiCaller->call();
        $outBound->api_response = $response['body'];
        $outBound->response_status_code = $response['header_status'];
        $outBound->doc_no = $billNo;
        $outBound->save();

        // Sync Status
        if(in_array($response['header_status'], [200, 201])) {
        	$invoice->sync_status = '1';
        	$invoice->save();
        } else {
        	$invoice->sync_status = '2';
        	$invoice->save();
        }

        return $config->handleApiResponse($response);


	}

	public function depositeRefund($params){

		$id = null;
    	$outBound = $params['outBound'];
    	$v_id = $params['v_id'];
    	$store_id = $params['store_id'];
    	//$invoice_id = $params['invoice_id'];
    	$client_id = $params['client_id'];
    	$payment_id = $params['payment_id'];
    	$client = $params['client'];
    	$error_for = $params['error_for'];
    	$store = $params['store'];
    	$vendor = $params['vendor'];
    	$creditNoteNumber= null;
    	$source_currency= null;
    	$target_currency=null;
    	
    	    	
    	JobdynamicConnection($v_id);

    	$currecy=getStoreAndClientCurrency($v_id,$store_id);

    	if($currecy['status']=='error'){
            $error_msg = $currecy['message'];
    		$outBound->error_before_call = $error_msg;
			$outBound->save();
    		Log::error($error_msg);

    		return [ 'error' => true , 'message' => $error_msg ];

    	}else{

         $source_currency = $currecy['store_currency'];
         $target_currency = $currecy['client_currency']; 


    	}

    	$payment = Payment::where('payment_id', $payment_id)->first();
    	$noOfPrints = 1; 

    	//$vendorUser = Vendor::select('first_name','last_name')->where('id', $invoice->vu_id)->first();
    	
    	$ownerGSTINStateCode = '';
    	
    	$customerInfo = User::leftjoin('addresses','addresses.c_id','customer_auth.c_id')
    	->select('customer_auth.c_id','customer_auth.mobile','customer_auth.customer_phone_code','customer_auth.first_name','customer_auth.last_name',
    		'customer_auth.gender','customer_auth.dob','customer_auth.email','addresses.address1')
    						->where('customer_auth.c_id', $payment->user_id)->first();
    	
    	$custGstInfo    = DB::table('customer_gstin')->select('state_id','gstin')
    					   ->where('v_id',$v_id)->where('c_id', $customerInfo->c_id)->first();
    	
    	$counterPartyGSTINNo = ''; //customer gstin number 
	    $counterPartyGSTINStateCode = '';				   
    	if(!empty($custGstInfo)){

    		$counterPartyGSTINNo = $custGstInfo->gstin; //customer gstin number 
	    	$counterPartyGSTINStateCode = ''; //need to change from id to ginesys code
	    	$custState = ClientState::select('ref_state_code')->where('client_id', $client_id)->where('state_id', $custGstInfo->state_id)->first();
	    	if($custState){
	    		$counterPartyGSTINStateCode = $custState->ref_state_code;
	    	}
    	}				   
    	
    	
    	#### Need to implement this ####
    	$counterPartyGSTINRegDate = null; //Gsting number Registration date for customer
    	$replaceTransaction = 0; // 0 fo no 1 for yes if wnat of update then pass 1 

    	
		$dep_rfd_trans = DepRfdTrans::select('terminal_id','id','amount','doc_no','remark','created_at','trans_src','trans_sub_type','trans_type','sync_status')
			                   ->where('doc_no', $payment->order_id)
			                   ->first();	

		if(empty($dep_rfd_trans)){
			$error_msg = 'Deposite Refunds Error: Error for- '.$error_for.' Message: Unable to find Doc No' ;
		}
		
		$billType='';

		if($dep_rfd_trans->trans_src=='self' && $dep_rfd_trans->trans_type=='Credit' && $dep_rfd_trans->trans_sub_type=='Deposit-DN'){
			$billType='D';
			
			$voucher_id=CrDrSettlementLog::select('voucher_id')
			                   ->where('trans_src_ref_id', $dep_rfd_trans->id)
			                   ->first()->voucher_id;

			if(isset($voucher_id) && !empty($voucher_id)){

				$creditNoteNumber = Voucher::select('voucher_no')
			                   ->where('id',$voucher_id)
			                   ->first()->voucher_no;

			}                 
			
		}elseif($dep_rfd_trans->trans_src=='self' && $dep_rfd_trans->trans_type=='Credit'){
			$billType='D';
			$creditNoteNumber = Voucher::select('voucher_no')
			                   ->where('dep_ref_trans_ref', $dep_rfd_trans->id)
			                   ->first()->voucher_no;

		}elseif($dep_rfd_trans->trans_src=='order' && $dep_rfd_trans->trans_type=='Credit' && $dep_rfd_trans->trans_sub_type=='Credit-Note'){
			$billType='D';
			$creditNoteNumber = Voucher::select('voucher_no')
			                   ->where('dep_ref_trans_ref', $dep_rfd_trans->id)
			                   ->first()->voucher_no;

		}elseif($dep_rfd_trans->trans_src=='self' && $dep_rfd_trans->trans_type=='Debit'){
			$billType='R';
		}elseif($dep_rfd_trans->trans_src=='order' && $dep_rfd_trans->trans_sub_type=='Refund-CN'){
			$billType='R';
		}

		$billDate = str_replace(' ','T',$dep_rfd_trans->created_at);

		$terminalName = '';
    	$terminal = CashRegister::find($dep_rfd_trans->terminal_id);
    	if($terminal){
	    	$terminalName = $terminal->licence_no.'-'.$store->short_code.'-'.$terminal->terminal_code;
	    }else{
	    	$error_msg = 'Deposite Refunds Error: Error for- '.$error_for.' Message: Unable to find terminal' ;
	    }

	    $remarks = null;
    	if($dep_rfd_trans->remark !='' && $dep_rfd_trans->remark!= null){
    		$remarks = $dep_rfd_trans->remark;
    	}

		$request = [];
		$billNo=$dep_rfd_trans->doc_no;
		$request['storeId']  = (int) $store->store_reference_code;  //Int
		$request['billNo']  = $billNo; //string
		$request['billDate']  = $billDate;  //String 2019-11-27T11:59:52.182Z
		$request['terminalName']  = $terminalName; //String
		$request['billType']  = $billType; //String
		$request['amount']  = abs($dep_rfd_trans->amount); //String
		$request['remarks']  = $remarks;
		$request['noOfPrints']  = $noOfPrints;  //Int

		#### Need to do the mapping with Ginesys implement in ####
		$custCode = null;
		if( $customerInfo->c_id > 0){
			$custCode = (string) $customerInfo->c_id; // need to change and ginesys code
		}
		$custIsdCode = null;
		// $country = Country::where('id' ,$cust->address->country_id )->first();
		if($customerInfo->customer_phone_code != null && $customerInfo->customer_phone_code !=''){
			$custIsdCode = $customerInfo->customer_phone_code;
			$custIsdCode = str_replace('+','',$custIsdCode);
		}
		$custMobileNo = (string)$customerInfo->mobile;
		$custFirstname = null;
		if($customerInfo->first_name != null && $customerInfo->first_name !=''){
			$custFirstname = $customerInfo->first_name;
		}else{
		   $custFirstname = "Dummy";
		}
		#### Need to Capture ####
		$custMiddlename = null;
		$custLastname = null;
		if($customerInfo->last_name != null && $customerInfo->last_name !=''){
			$custLastname = $customerInfo->last_name;
		}else{
			if(substr($custMobileNo, 0,1) == '3'){
				$custLastname = "Customer";	
			}else{
				$custLastname = ".";	
			}
		}
		
		$custGender = 'M'; //ucfirst($cust->gender);
		if($customerInfo->gender){
			if($customerInfo->gender == 'male'){
				$custGender = 'M';
			}
			if($customerInfo->gender == 'female'){
				$custGender = 'F';
			}
		}

		$custDateOfBirth = null;
		
		if($customerInfo->dob != null && $customerInfo->dob!='0000-00-00'){
			$custDateOfBirth = $customerInfo->dob.'T00:00:00';
		}

		$custEmail = null;
		if($customerInfo->email != null && $customerInfo->email !=''){
			$custEmail = $customerInfo->email;
		}

		$customer_address1 = null;
		if($customerInfo->address1 !=null && $customerInfo->address1 != ''){
         $customer_address1 = $customerInfo->address1;
		}


		$request['customer'] = [
			'isdCode' => $custIsdCode, //String
			'mobileNo' => $custMobileNo, //String
			'firstName' => $custFirstname, //String
			'middleName' => $custMiddlename, //String
			'lastName' => $custLastname, //String
			'gender' => $custGender, //String Ginesys have M // F
			
			//Need to confirm that whole field is optional or not
			'optionalInfo' => [
				'salutation' => $id, //String
				'email' => $custEmail, //String
				'phone1' => $id, //String
				'phone2' => $id, //String
				'address1' => $customer_address1, //String
				'address2' => $id, //String
				'address3' => $id, //String
				'city' => $id, //String
				'country' => $id, //String
				'district' => $id, //String
				'state' => $id, //String
				'pincode' => $id, //String
				'remarks' => $id, //String
				'profession' => $id, //String
				'spouseName' => $id, //String
				'dateOfBirth' => $custDateOfBirth, //String 2019-11-27T11:59:52.182Z
				'dateOfAnniversary' => $id, //String 2019-11-27T11:59:52.182Z
				'pan' => $id, //String
				'counterPartyGSTINNo'=>$counterPartyGSTINNo, //string
				'counterPartyGSTINStateCode'=>$counterPartyGSTINStateCode  //string
			]
		];

		
		
		$payments = Payment::where('v_id', $v_id)->where('payment_id', $payment_id)->get();

		if(!$payments->isEmpty()){
		
		}else{
			$error_msg = 'Deposite Refunds Error: Error for- '.$error_for.' Message: Unable to find any Payments' ;
			Log::error($error_msg);
			return ['error' => true , 'message' => $error_msg ];
			exit;
		}

		$posBillMOPList = [];
		$displayOrder = 0;

		foreach ($payments as $key => $payment) {

			$displayOrder++;

			$exchangebaseAmt =getExchangeRate($v_id,$source_currency,$target_currency,$payment->amount);
			$baseAmt = (float)$exchangebaseAmt['amount'];
			if($payment->method == 'cash' && $payment->cash_collected !=null){
				$exchangebaseTender = getExchangeRate($v_id,$source_currency,$target_currency,$payment->cash_collected);
				
				if($exchangebaseTender['amount'] == 0){
					$baseTender = $baseAmt;
				}else{
					$baseTender=$exchangebaseTender['amount'];
				}

				//$baseTender=$exchangebaseTender['amount'];
				$bBalance = (float)$payment->cash_return==null?0:$payment->cash_return;
				$exchangebaseBalance= getExchangeRate($v_id,$source_currency,$target_currency,$bBalance);
				$baseBalance = (float)$exchangebaseBalance['amount'];
			}else{

				$exchangebaseTender = getExchangeRate($v_id,$source_currency,$target_currency,$payment->amount);
				$baseTender = (float) $exchangebaseTender['amount'];
				$baseBalance = (float)0;
			}



			#### Implement mop payments  #### 

			$method = $payment->method;
			if($payment->method == 'card'){
				$method = 'CARD_OFFLINE';
			}else if($payment->method == 'google_tez'){
				$method = 'GOOGLE_TEZ_OFFLINE';
			}else if($payment->method == 'paytm'){
				$method = 'PAYTM_OFFLINE';
			}else if($payment->method == 'voucher_credit'){
				$method = 'voucher';
			}


			$vendorMop = VendorMop::join('mops','mops.id','vendor_mop_mapping.mop_id')
			                        ->where('vendor_mop_mapping.v_id', $v_id)
			                        ->where('vendor_mop_mapping.store_id',$store_id)
			                        ->where('mops.code', $method)
			                        ->first();
			if(!$vendorMop){

				$vendorMop = VendorMop::join('mops','mops.id','vendor_mop_mapping.mop_id')
			                        ->where('vendor_mop_mapping.v_id', $v_id)
			                        // ->where('vendor_mop_mapping.store_id',$store_id)
			                        ->where('mops.code', $method)
			                        ->first();
			}	
				                   
			if(!$vendorMop){
				$error_msg = 'Deposite Refunds Error: Error for- '.$error_for.' Message: Unable to find Mop Mapping in Vendor Mop Table ';
				Log::error($error_msg);

				$outBound->error_before_call = $error_msg;
				$outBound->save();

				return [ 'error' => true , 'message' => $error_msg ];
				exit;
			}

				$mopType=$vendorMop->ref_mop_type==null?$vendorMop->ref_mop_code:$vendorMop->ref_mop_type;
				$mopShortCode = $vendorMop->ref_mop_code; //Need to send ginesys code
				$mopType = $mopType; //Need to send ginesys Type
				
				$forexRate = 0;
				$forexTender = 0;
				$forexBalance = 0;
				$forexAmt = 0;

			    /*$debitNoteNumber = DepRfdTrans::join('cr_dr_voucher','cr_dr_voucher.dep_ref_trans_ref','dep_rfd_trans.id')
										->select('cr_dr_voucher.voucher_no','dep_rfd_trans.doc_no')
			                            ->where('dep_rfd_trans.trans_src_ref', $payment->order_id)
			                            ->first();*/

			#### We Need to Implement this ####
			//Should be 1 for only Gift Voucher type mop only
			$isDenoApplicable = $payment->method == 'GIFT_VOUCHER'?1:0;
			if($isDenoApplicable == 1){

			    $gvTransactionlogs=GiftVoucherTransactionLogs::select('voucher_code','amount','type')
			  											->where('ref_order_id',$payment->order_id)
												        ->where('type','DEBIT_GV')
												        ->get();
				foreach ($gvTransactionlogs as $key => $value) {

					$mopDeno[]=[
								'denoNumber' => $value->voucher_code , // String nullable 
								'denoDescription' => $value->type, // String nullable
								'perUnitValue' => abs($value->amount), // Double 
								'numberOfUnits' =>1, // Int
								'denoAmount' => abs($value->amount) , // Double
							];			        	
				}							        
			  
			}else{
				$mopDeno = [
					[
					'denoNumber' => '' , // String nullable 
					'denoDescription' => '', // String nullable
					'perUnitValue' => 0, // Double 
					'numberOfUnits' =>0, // Int
					'denoAmount' => 0, // Double
					]
				];
			}
			
			if($mopShortCode=='CSH' || $mopType=='CSH' || $mopShortCode=='C1'){
				$creditNoteNumber_new='';

				if( ($dep_rfd_trans->trans_src=='self' || $dep_rfd_trans->trans_src=='order') && $dep_rfd_trans->trans_sub_type=='Refund-CN'){
					
					$baseTenderTemp=0.0;
					$baseBalanceTemp=$baseTender;

					$baseAmtTemp=-1*$baseAmt;
				}else{
					$baseAmtTemp=$baseAmt;
					$baseTenderTemp=$baseTender;
					$baseBalanceTemp=$baseBalance;
				}
				/*$temp=$baseBalance;
				$baseBalance=$baseTender;
				$baseTender=$temp;*/
				//$baseAmt=-1*$baseAmt;
				
			}else{
				$creditNoteNumber_new=$creditNoteNumber;
				$baseAmtTemp=$baseAmt;
				$baseBalanceTemp=$baseBalance;
				$baseTenderTemp=$baseTender;
			}
			
			$posBillMOP = [
			
				'mopShortCode' => $mopShortCode, //String
				'mopType' => $mopType, //String
				'displayOrder' => $displayOrder, //Double
				'baseTender' => $baseTenderTemp, //Double
				'baseBalance' => (float)$baseBalanceTemp, //Double
				'baseAmt' => $baseAmtTemp, //Double
				'forexRate' => $forexRate, //Double
				'forexBalance' => $forexBalance, //Double
				'forexTender' => $forexTender, //Double
				'forexAmt' => $forexAmt, //Double
				'isDenoApplicable' => $isDenoApplicable, //Int
				'isBaseCurrency' => 1, //Int

				'mopDeno' => $mopDeno,

				'optionalInfo' => [
					'creditCardCommissionPercentage' => 0, //Double
					'creditCardCommissionAmount' => 0, //Double
					'creditNoteNumber' => $creditNoteNumber_new, //String
					'debitNoteNumber' => $id //String
				]
				
			];
		

			$posBillMOPList[] = $posBillMOP;

		}
		
		$customMopType='';
		if($dep_rfd_trans->trans_src=='self' && $dep_rfd_trans->trans_sub_type=='Credit-Note'){
			$customMopType='CNI';
			$baseAmt=-1*$baseAmt;
			$baseTender=$baseAmt;
			$baseBalance=0.0;
			
		}elseif($dep_rfd_trans->trans_src=='self' && $dep_rfd_trans->trans_sub_type=='Deposit-DN'){
			$customMopType='DNA';
			$baseAmt=-1*$baseAmt;
			$baseTender=$baseAmt;
			$baseBalance=0.0;
			$creditNoteNumber='';
		}elseif( ($dep_rfd_trans->trans_src=='self' || $dep_rfd_trans->trans_src=='order') && $dep_rfd_trans->trans_sub_type=='Refund-CN'){
			$customMopType='CNR';
		}elseif($dep_rfd_trans->trans_src=='order' && $dep_rfd_trans->trans_sub_type=='Credit-Note' && $dep_rfd_trans->trans_type=='Credit'){
			$customMopType='CNI';
			$baseAmt=-1*$baseAmt;
			$baseTender=$baseAmt;
			$baseBalance=0.0;
			
		}
		
		$customMop = [
			
				'mopShortCode' => $customMopType, //String
				'mopType' => $customMopType, //String
				'displayOrder' => ++$displayOrder, //Double
				'baseTender' => $baseTender, //Double
				'baseBalance' => (float)$baseBalance, //Double
				'baseAmt' => $baseAmt, //Double
				'forexRate' => $forexRate, //Double
				'forexBalance' => $forexBalance, //Double
				'forexTender' => $forexTender, //Double
				'forexAmt' => $forexAmt, //Double
				'isDenoApplicable' => $isDenoApplicable, //Int
				'isBaseCurrency' => 1, //Int

				'mopDeno' => $mopDeno,

				'optionalInfo' => [
					'creditCardCommissionPercentage' => 0, //Double
					'creditCardCommissionAmount' => 0, //Double
					'creditNoteNumber' => $creditNoteNumber, //String
					'debitNoteNumber' => $id //String
				]
				
		];
		$posBillMOPList[] = $customMop;	
		$request['posDepRefBillMOP'] = $posBillMOPList;
		//dd($request);
		//dd(json_encode($request));
		$outBound->api_request = json_encode($request);
        $outBound->save();
		
		$config = new ConfigController($v_id);
		$apiCaller = new  ApiCallerController([
			'url' => $config->apiBaseUrl.'/POSDepRefBill',
			'data'=> $request, 
			'header' => [ 'Content-Type:application/json'],
			'auth_type' => $config->authType,
			'auth_token' => $config->authToken
		]);
		# extract the body
		$response = $apiCaller->call();
        $outBound->api_response = $response['body'];
        $outBound->response_status_code = $response['header_status'];
        $outBound->doc_no = $billNo;
        $outBound->save();

        // Sync Status
        if(in_array($response['header_status'], [200, 201])) {
        	$dep_rfd_trans->sync_status = '1';
        	$dep_rfd_trans->save();
        } else {
        	$dep_rfd_trans->sync_status = '2';
        	$dep_rfd_trans->save();
        }

        return $config->handleApiResponse($response);
	}

	public function getAllUnsyncInvoice(Request $request){
		// dd('here');
		$params = [];
		$unsynced_data = [];
		$params['v_id'] = $request['v_id'];
    	$params['client'] = $request['client'];
    	$params['vendor'] = $request['vendor'];
    	
		$params['client_id'] = $request['client_id'];
		$params['fromdate'] = $request['fromdate'];
		$fromdate = $params['fromdate'];
    	$params['todate'] = $request['todate'];
    	$todate = $params['todate'];
    	$params['unsync'] = 1;
    	$dbName = DB::connection()->getDatabaseName();

    	JobdynamicConnection($params['v_id']);

		$invoices = Invoice::whereBetween('created_at', [$fromdate, $todate])->where('sync_status', '!=', '1')->get();

		foreach ($invoices as $invoice) {
			$params['invoice'] = $invoice;
			$params['invoice_id'] = $invoice->id;
			$params['store_id'] = $invoice->store_id;
			$params['type'] = $invoice->transaction_type;

			$store = Store::select('store_reference_code','state_id','gst')->where('v_id', $params['v_id'])->where('store_id', $params['store_id'])->first();
			$params['store'] = $store;

			$error_for = 'V_id: '.$params['v_id']. ' store_id: '.$params['store_id'].' client_id: '.$params['client_id']. ' DB Name: '.$dbName;

	    	$params['error_for'] = $error_for;

			$unsynced_data[] = $this->InvoicePush($params);
			
		}
		return response()->json(['status' => 'success', 'message' => 'data fetched successfully','data'=> $unsynced_data], 200);

	}

}