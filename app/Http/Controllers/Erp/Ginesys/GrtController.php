<?php

namespace App\Http\Controllers\Erp\Ginesys;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiCallerController;
use App\Organisation;
use App\Model\OutboundApi;
use App\Model\Oauth\OauthClient;
use App\Model\Stock\StockPoints;
use App\Model\Items\VendorItems;
use App\Packet;
use App\PacketDetail;
use App\GrtHeader;
use App\GrtDetail;
use App\Store;
use App\Model\Supplier\Supplier;
use Log;

class GrtController extends Controller
{
    
    public function __construct()
    {
    	//$this->config = new ConfigController;
    }

    public function grtPush($params){

    	$id = null;
    	$error_for = null;
    	$checkExits = OutboundApi::where([ 'for_transaction' => 'grt Push', 'transaction_id' => $params['grt_id'], 'client_id' => $params['client_id'], 'v_id' => (int)$params['v_id'], 'store_id' => (int)$params['store_id'] ])->first();
    	if(empty($checkExits)) {
    		$outBound = new OutboundApi;
    	} else {
    		$outBound = new OutboundApi;
    		$outBound->parent_id = $checkExits['_id'];
    	}
    	try {

	    	$v_id = $params['v_id'];
	    	$store_id = $params['store_id'];
	    	$grt_id = $params['grt_id'];
	    	$client_id = $params['client_id'];
            JobdynamicConnection($v_id);
	    	$error_for = 'V_id: '.$v_id. ' store_id: '.$store_id.' grt_id: '.$grt_id.' client_name: Ginesys';
           
	    	$vendor = Organisation::select('ref_vendor_code')->where('id', $v_id)->first();
			if(!$vendor){

				$error_msg = 'GRT push Error: '.$error_for.'- Vendor , Message: Unable to find Vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;

			}elseif($vendor->ref_vendor_code =='' || $vendor->ref_vendor_code ==null){

				$error_msg = 'GRT push Error: '.$error_for.'- vendor , Message: Unable to find Mapping of vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;
			}
	        $source_currency= null;
	        $target_currency=null;
	        
	        $currecy=getStoreAndClientCurrency($v_id,$store_id);
	        if($currecy['status']=='error'){
	            $error_msg = $extrarate['message'];
	            $outBound->error_before_call = $error_msg;
	            $outBound->save();
	            Log::error($error_msg);

	            return [ 'error' => true , 'message' => $error_msg ];
	            exit;

	        }else{

	         $source_currency = $currecy['store_currency'];
	         $target_currency = $currecy['client_currency']; 


	        }
	        $extrarate=getExchangeRate($v_id,$source_currency,$target_currency,1);
	        if($extrarate['status']=='error'){
	            $error_msg = $extrarate['message'];
	            $outBound->error_before_call = $error_msg;
	            $outBound->save();
	            Log::error($error_msg);

	            return [ 'error' => true , 'message' => $error_msg ];
                exit;
	        }

	    	$id = 'string';
			$request = [];

			$grt = GrtHeader::where('id', $grt_id)->first();
		

			$client = OauthClient::where('id', $client_id)->first();

			$outBoundEntryExists = OutboundApi::where([ 'v_id' => $v_id, 'client_id' => $client_id, 'for_transaction' => 'grt Push', 'transaction_id' => $grt_id ])->first();
	    	if(!empty($outBoundEntryExists)) {
	    		$outBound->parent_id = $outBoundEntryExists->_id;
	    	}

			$outBound->v_id = (int)$v_id;
			$outBound->store_id = (int)$store_id;
	    	$outBound->client_id = $client_id;
	    	$outBound->client_name = $client->name;
	    	$outBound->for_transaction = 'grt Push';
	    	$outBound->transaction_id = $grt_id;
	    	$outBound->event_class = isset($params['event_class'] )?$params['event_class']:'';
	    	$outBound->job_class = isset($params['job_class'])?$params['job_class']:'';
	    	$outBound->save();

			$store = Store::select('store_reference_code','state_id')->where('v_id', $v_id)->where('store_id', $store_id)->first();
			$storeCode = (int)$store->store_reference_code;

			if($grt){
				$outStockpointId =  null;
				$destination_store_id=null;
				$replenishmentSourceCode=null;
		    	
                 
	          $supplier=Supplier::select('reference_code')->where('v_id',$v_id)->where('id',$grt->supplier_id)->first();
	          if($supplier  && $supplier->reference_code != '' && $supplier->reference_code !=null ){
	          	 $replenishmentSourceCode =$supplier->reference_code;

	          }else{
	           $error_msg = 'Grt Push Error: '.$error_for.'- Supplier , Message: Unable to find Supplier reference_code';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;

            }

				$request['storeCode']  = $storeCode;  //int
				$request['grtNo']  = $grt->grt_no;  //string 
				$request['grtNoSequence']  = (int)$grt->grt_no_sequence;
				$request['grtDate']  = $grt->created_at;  //string 
				$request['reason']  = null;  //string
				$request['replenishmentSourceCode']  = (int)$replenishmentSourceCode;
				$request['remarks']  = $grt->remark;
				
				$grtDetails = GrtDetail::where('v_id', $v_id)->where('grt_id', $grt->id)->get();
				$grtItems = [];
				$serialNo = 0;
				
				if($grtDetails){

				 foreach ($grtDetails as $grt) {

				 	$packetDetail   = PacketDetail::where('packet_id',$grt->packet_id)->where('item_id',$grt->item_id)->first();
				 	$ref_packet_item_id = '';
				 	if($packetDetail){
				 		$ref_packet_item_id = $packetDetail->ref_packet_item_id;
				 	}
                         
                        $taxdetail    =  json_decode($grt->tax_details);  
                        // if(isset($taxdetail->igst_rate) ){
                        // 	$taxdetail->igst = $taxdetail->igst_rate;
                        // 	$taxdetail->igstamt = $taxdetail->igst_amt;
                        // 	$taxdetail->cgst = $taxdetail->cgst_rate;
                        // 	$taxdetail->cgstamt = $taxdetail->cgst_amt;
                        // 	$taxdetail->sgst = $taxdetail->sgst_rate;
                        // 	$taxdetail->sgstamt = $taxdetail->sgst_amt;
                        // 	$taxdetail->cess = $taxdetail->cess_rate;
                        // 	$taxdetail->cessamt = $taxdetail->cess_amt;
                        // 	if($taxdetail->taxable == 0){
                        // 		$taxdetail->taxable = $grt->total;
                        // 	}
                        // }            

                        $supply_price   =  getExchangeRate($v_id,$source_currency,$target_currency,$grt->supply_price);  

                        $taxableAmount['amount']     =  0;
                        if(isset($taxdetail->taxable) ){
	                        $taxableAmount  =  getExchangeRate($v_id,$source_currency,$target_currency,$taxdetail->taxable);
	                    }

                        $igstAmount['amount']     =  0;
                        if(isset($taxdetail->igstamt) ){
                        	$igstAmount     =  getExchangeRate($v_id,$source_currency,$target_currency,$taxdetail->igstamt);
                        }

                        $cgstAmount['amount']     =  0;
                        if(isset($taxdetail->cgstamt) ){
                        	$cgstAmount     =  getExchangeRate($v_id,$source_currency,$target_currency,$taxdetail->cgstamt);
                        }

                        $sgstAmount['amount']     =  0;
                        if(isset($taxdetail->sgstamt) ){
                        	$sgstAmount     =  getExchangeRate($v_id,$source_currency,$target_currency,$taxdetail->sgstamt);
                        }

                        $cessAmount['amount']     =  0;
                        if(isset($taxdetail->cessamt) ){
                        	$cessAmount     =  getExchangeRate($v_id,$source_currency,$target_currency,$taxdetail->cessamt);    
                        }
                        
                        $igstRate = 0;
                        if(isset($taxdetail->igst) ){
                        	$igstRate = $taxdetail->igst;
                        }

                        $cgstRate = 0;
                        if(isset($taxdetail->cgst) ){
                        	$cgstRate = $taxdetail->cgst;
                        }

                        $sgstRate = 0;
                        if(isset($taxdetail->sgst) ){
                        	$sgstRate = $taxdetail->sgst;
                        }

                        $cessRate = 0;
                        if(isset($taxdetail->cess) ){
                        	$cessRate = $taxdetail->cess;
                        }
				 		
                        $grtItems[] = [
                         	                'packetNumber'=> (string)$grt->packet_code,
									        'packetItemRefId'=>(string)$ref_packet_item_id,
									        'rate'=> (float)$supply_price['amount'],
									        'taxableAmount'=>(float)$taxableAmount['amount'],
									        'igstRate'=> (float)$igstRate,
									        'igstAmount'=>(float)$igstAmount['amount'],
									        'cgstRate'=> (float)$cgstRate,
									        'cgstAmount'=> (float)$cgstAmount['amount'],
									        'sgstRate'=> (float)$sgstRate,
									        'sgstAmount'=>(float)$sgstAmount['amount'],
									        'cessRate'=> (float)$cessRate,
									        'cessAmount'=>(float)$cessAmount['amount']

                                       ];

				 	}	

				}else{
                
                $error_msg = 'grt Push Error: '.$error_for.'- items , Message: No item found in grt' ;
		    		Log::error($error_msg);
		    		$outBound->error_before_call = $error_msg;
					$outBound->save();

		    		return ['error' => true , 'message' => $error_msg ];
					exit;

				}

				$request['Items'] = $grtItems;

				$outBound->api_request = json_encode($request);
          		$outBound->save();

				// dd(json_encode($request));
				$config = new ConfigController($v_id);

				$apiCaller = new  ApiCallerController([
					'url' => $config->apiBaseUrl.'/GRT',
					'data'=> $request, 
					'header' => [ 'Content-Type:application/json'],
					'auth_type' => $config->authType,
					'auth_token' => $config->authToken,
				]);
				# extract the body
				$response = $apiCaller->call();
		        $outBound->api_response = $response['body'];
		        $outBound->response_status_code = $response['header_status'];
		        $outBound->doc_no = $grt->grt_no;
		        $outBound->save();

		        // Sync Status
		        if(in_array($response['header_status'], [200, 201])) {
		        	$grt->sync_status = '1';
		        	$grt->save();
		        } else {
		        	$grt->sync_status = '2';
		        	$grt->save();
		        }

                return $config->handleApiResponse($response);

			}
		}catch (\Exception $e){

			$outBound->error_before_call = $e->getMessage().'\n'.$e->getTraceAsString();
			$outBound->save();

			$error_msg = 'grt Push Error: Error for- '.$error_for.' Message: unexcepted error';
			Log::error( $error_msg);
			Log::error($e);

			return ['error' => true , 'message' => $error_msg ];
		
		}
    
    
	}


	public function storeTransferPush($params){

    	$id = null;
    	$error_for = null;
    	$checkExits = OutboundApi::where([ 'for_transaction' => 'Store Transfer push', 'transaction_id' => $params['stock_transfer_order_id'], 'client_id' => $params['client_id'], 'v_id' => (int)$params['v_id'], 'store_id' => (int)$params['store_id'] ])->first();
    	if(empty($checkExits)) {
    		$outBound = new OutboundApi;
    	} else {
    		$outBound = new OutboundApi;
    		$outBound->parent_id = $checkExits['_id'];
    	}
    	try {

	    	$v_id = $params['v_id'];
	    	$store_id = $params['store_id'];
	    	$stock_transfer_order_id = $params['stock_transfer_order_id'];
	    	$client_id = $params['client_id'];
            JobdynamicConnection($v_id);
	    	$error_for = 'V_id: '.$v_id. ' store_id: '.$store_id.'  id: '.$stock_transfer_order_id.' client_name: Ginesys';
           
	    	$vendor = Organisation::select('ref_vendor_code')->where('id', $v_id)->first();
			if(!$vendor){

				$error_msg = 'Store Transfer push Error: '.$error_for.'- Vendor , Message: Unable to find Vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;

			}elseif($vendor->ref_vendor_code =='' || $vendor->ref_vendor_code ==null){

				$error_msg = 'Store Transfer push Error: '.$error_for.'- vendor , Message: Unable to find Mapping of vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;
			}
	        $source_currency= null;
	        $target_currency=null;
	        
	        $currecy=getStoreAndClientCurrency($v_id,$store_id);
	        if($currecy['status']=='error'){
	            $error_msg = $extrarate['message'];
	            $outBound->error_before_call = $error_msg;
	            $outBound->save();
	            Log::error($error_msg);

	            return [ 'error' => true , 'message' => $error_msg ];
	            exit;

	        }else{

	         $source_currency = $currecy['store_currency'];
	         $target_currency = $currecy['client_currency']; 


	        }
	        $extrarate=getExchangeRate($v_id,$source_currency,$target_currency,1);
	        if($extrarate['status']=='error'){
	            $error_msg = $extrarate['message'];
	            $outBound->error_before_call = $error_msg;
	            $outBound->save();
	            Log::error($error_msg);

	            return [ 'error' => true , 'message' => $error_msg ];
                exit;
	        }

	    	$id = 'string';
			$request = [];

			$stockTranferOrder = StockTransferOrder::where('id', $stock_transfer_order_id)->first();
		

			$client = OauthClient::where('id', $client_id)->first();

			$outBoundEntryExists = OutboundApi::where([ 'v_id' => $v_id, 'client_id' => $client_id, 'for_transaction' => 'Store Transfer push', 'transaction_id' => $stock_transfer_order_id ])->first();
	    	if(!empty($outBoundEntryExists)) {
	    		$outBound->parent_id = $outBoundEntryExists->_id;
	    	}

			$outBound->v_id = (int)$v_id;
			$outBound->store_id = (int)$store_id;
	    	$outBound->client_id = $client_id;
	    	$outBound->client_name = $client->name;
	    	$outBound->for_transaction = 'Store Transfer push';
	    	$outBound->transaction_id = $stock_transfer_order_id;
	    	$outBound->event_class = isset($params['event_class'] )?$params['event_class']:'';
	    	$outBound->job_class = isset($params['job_class'])?$params['job_class']:'';
	    	$outBound->save();

			$store = Store::select('store_reference_code','state_id')->where('v_id', $v_id)->where('store_id', $store_id)->first();
			$storeCode = (int)$store->store_reference_code;

			if($stockTranferOrder){
				$outStockpointId =  null;
				$destination_store_id=null;
				$replenishmentSourceCode=null;
		    	
                 
	          $destStore = Store::select('store_reference_code','state_id')->where('v_id', $v_id)->where('store_id', $stockTranferOrder->dest_store_id)->first();


	          if($destStore  && $destStore->store_reference_code != '' && $store_reference_code->store_reference_code !=null ){
	          	 $replenishmentSourceCode =$destStore->store_reference_code;

	          }else{
	           $error_msg = 'Store Transfer push Error: '.$error_for.'- Destination Store , Message: Unable to find destination store reference_code';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;

            }

				$request['storeCode']  = $storeCode;  //int
				$request['grtNo']  = $stockTranferOrder->sto_no;  //string 
				$request['grtNoSequence']  = (int)$stockTranferOrder->sto_no_sequence;
				$request['grtDate']  = $stockTranferOrder->created_at;  //string 
				$request['reason']  = null;  //string
				$request['replenishmentSourceCode']  = (int)$replenishmentSourceCode;
				$request['remarks']  = $stockTranferOrder->remark;
				
				$stoDetails = StockTransferOrderDetails::where('v_id', $v_id)->where('sto_trf_ord_id', $stockTranferOrder->id)->get();
				$grtItems = [];
				$serialNo = 0;
				
				if($stoDetails){

				 foreach ($stoDetails as $sto) {

				 	$packetDetail   = PacketDetail::where('packet_id',$sto->packet_id)->where('item_id',$sto->item_id)->first();
				 	$ref_packet_item_id = '';
				 	if($packetDetail){
				 		$ref_packet_item_id = $packetDetail->ref_packet_item_id;
				 	}

                        $taxableAmount['amount']     =  0;
                        $igstAmount['amount']     =  0;
                        $cgstAmount['amount']     =  0;
                        $sgstAmount['amount']     =  0;
                        $cessAmount['amount']     =  0;
                        $igstRate = 0;
                        $cgstRate = 0;
                        $sgstRate = 0;
                        $cessRate = 0;
                         
                        if(isset($sto->tax_details) ){


	                        $taxdetail    =  json_decode($sto->tax_details);  
	                                

	                        $supply_price   =  getExchangeRate($v_id,$source_currency,$target_currency,$grt->supply_price);  

	                        if(isset($taxdetail->taxable) ){
		                        $taxableAmount  =  getExchangeRate($v_id,$source_currency,$target_currency,$taxdetail->taxable);
		                    }

	                        if(isset($taxdetail->igstamt) ){
	                        	$igstAmount     =  getExchangeRate($v_id,$source_currency,$target_currency,$taxdetail->igstamt);
	                        }

	                        
	                        if(isset($taxdetail->cgstamt) ){
	                        	$cgstAmount     =  getExchangeRate($v_id,$source_currency,$target_currency,$taxdetail->cgstamt);
	                        }

	                        
	                        if(isset($taxdetail->sgstamt) ){
	                        	$sgstAmount     =  getExchangeRate($v_id,$source_currency,$target_currency,$taxdetail->sgstamt);
	                        }

	                        
	                        if(isset($taxdetail->cessamt) ){
	                        	$cessAmount     =  getExchangeRate($v_id,$source_currency,$target_currency,$taxdetail->cessamt);    
	                        }
	                        
	                        
	                        if(isset($taxdetail->igst) ){
	                        	$igstRate = $taxdetail->igst;
	                        }

	                        
	                        if(isset($taxdetail->cgst) ){
	                        	$cgstRate = $taxdetail->cgst;
	                        }

	                        
	                        if(isset($taxdetail->sgst) ){
	                        	$sgstRate = $taxdetail->sgst;
	                        }

	                        
	                        if(isset($taxdetail->cess) ){
	                        	$cessRate = $taxdetail->cess;
	                        }
				 		}

                        $stoItems[] = [
                         	                'packetNumber'=> (string)$sto->packet_code,
									        'packetItemRefId'=>(string)$ref_packet_item_id,
									        'rate'=> (float)$supply_price['amount'],
									        'taxableAmount'=>(float)$taxableAmount['amount'],
									        'igstRate'=> (float)$igstRate,
									        'igstAmount'=>(float)$igstAmount['amount'],
									        'cgstRate'=> (float)$cgstRate,
									        'cgstAmount'=> (float)$cgstAmount['amount'],
									        'sgstRate'=> (float)$sgstRate,
									        'sgstAmount'=>(float)$sgstAmount['amount'],
									        'cessRate'=> (float)$cessRate,
									        'cessAmount'=>(float)$cessAmount['amount']

                                       ];

				 	}	

				}else{
                
                $error_msg = 'Store Transfer push Error: '.$error_for.'- items , Message: No item found in grt' ;
		    		Log::error($error_msg);
		    		$outBound->error_before_call = $error_msg;
					$outBound->save();

		    		return ['error' => true , 'message' => $error_msg ];
					exit;

				}

				$request['Items'] = $stoItems;

				$outBound->api_request = json_encode($request);
          		$outBound->save();

				// dd(json_encode($request));
				$config = new ConfigController($v_id);

				$apiCaller = new  ApiCallerController([
					'url' => $config->apiBaseUrl.'/GRT',
					'data'=> $request, 
					'header' => [ 'Content-Type:application/json'],
					'auth_type' => $config->authType,
					'auth_token' => $config->authToken,
				]);
				# extract the body
				$response = $apiCaller->call();
		        $outBound->api_response = $response['body'];
		        $outBound->response_status_code = $response['header_status'];
		        $outBound->doc_no = $stockTranferOrder->sto_no;
		        $outBound->save();

		        // Sync Status
		        if(in_array($response['header_status'], [200, 201])) {
		        	$stockTranferOrder->sync_status = '1';
		        	$stockTranferOrder->save();
		        } else {
		        	$stockTranferOrder->sync_status = '2';
		        	$stockTranferOrder->save();
		        }

                return $config->handleApiResponse($response);

			}
		}catch (\Exception $e){

			$outBound->error_before_call = $e->getMessage().'\n'.$e->getTraceAsString();
			$outBound->save();

			$error_msg = 'Store Transfer push Error: Error for- '.$error_for.' Message: unexcepted error';
			Log::error( $error_msg);
			Log::error($e);

			return ['error' => true , 'message' => $error_msg ];
		
		}
    	
	}



}
