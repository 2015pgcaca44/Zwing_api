<?php

namespace App\Http\Controllers\Erp\Ginesys;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiCallerController;
use App\Organisation;
use App\Model\OutboundApi;
use App\Model\Oauth\OauthClient;
use App\Model\Stock\StockPoints;
use App\Model\Stock\StockPointHeader;
use App\Model\Items\VendorItems;
use App\Packet;
use App\PacketDetail;
use App\Model\Supplier\Supplier;
use App\Store;
use App\Model\Grn\Advise;
use App\Model\Grn\AdviseList;
use App\Model\Item\ItemList;
use Log;
use App\Model\Items\VendorSkuDetailBarcode;




class PacketController extends Controller
{
     
  private $config= null;
	
	public function __construct()
    {
    	//$this->config = new ConfigController;
    }


  public function packetPush($params){
    	
    	$id = null;
    	$error_for = null;
    	$checkExits = OutboundApi::where([ 'for_transaction' => 'Packet Push', 'transaction_id' => $params['packet_id'], 'client_id' => $params['client_id'], 'v_id' => (int)$params['v_id'], 'store_id' => (int)$params['store_id'] ])->first();
    	if(empty($checkExits)) {
    		$outBound = new OutboundApi;
    	} else {
    		$outBound = new OutboundApi;
    		$outBound->parent_id = $checkExits['_id'];
    	}
    	try {

	    	$v_id = $params['v_id'];
	    	$store_id = $params['store_id'];
	    	$packet_id = $params['packet_id'];
	    	$client_id = $params['client_id'];
            JobdynamicConnection($v_id); 
	    	$error_for = 'V_id: '.$v_id. ' store_id: '.$store_id.' packet_id: '.$packet_id.' client_name: Ginesys';

	    	$vendor = Organisation::select('ref_vendor_code')->where('id', $v_id)->first();
			if(!$vendor){

				$error_msg = 'Paket push Error: '.$error_for.'- Vendor , Message: Unable to find Vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;

			}elseif($vendor->ref_vendor_code =='' || $vendor->ref_vendor_code ==null){

				$error_msg = 'Paket push Error: '.$error_for.'- vendor , Message: Unable to find Mapping of vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;
			}


	    	$id = 'string';
			$request = [];

			$packet = Packet::where('id', $packet_id)->first();
		

			$client = OauthClient::where('id', $client_id)->first();

			$outBoundEntryExists = OutboundApi::where([ 'v_id' => $v_id, 'client_id' => $client_id, 'for_transaction' => 'Packet Push', 'transaction_id' => $packet_id ])->first();
	    	if(!empty($outBoundEntryExists)) {
	    		$outBound->parent_id = $outBoundEntryExists->_id;
	    	}

			$outBound->v_id = (int)$v_id;
			$outBound->store_id = (int)$store_id;
	    	$outBound->client_id = $client_id;
	    	$outBound->client_name = $client->name;
	    	$outBound->for_transaction = 'Packet Push';
	    	$outBound->transaction_id = $packet_id;
	    	$outBound->event_class = isset($params['event_class'] )?$params['event_class']:'';
	    	$outBound->job_class = isset($params['job_class'])?$params['job_class']:'';
	    	$outBound->save();

			$store = Store::select('store_reference_code','state_id')->where('v_id', $v_id)->where('store_id', $store_id)->first();
			$storeCode = (int)$store->store_reference_code;

			if($packet){
				$outStockpointId =  null;
				//$destination_store_id=null;
                $replenishmentSiteCode=null;		    	
		    	// $stockPoint =  StockPoints::select('ref_stock_point_code')->where('store_id',$store_id)->where('v_id',$v_id)->where('id',$packet->stock_point_id)->first();

		    	$StockpointhdId =  StockPoints::select('stock_point_header_id')->where('store_id',$store_id)->where('v_id',$v_id)->where('id',$packet->stock_point_id)->first();

            	$stockPoint =  StockPointHeader::select('ref_stock_point_header_code')->where('id', $StockpointhdId->stock_point_header_id)->first();
		    	if($stockPoint && $stockPoint->ref_stock_point_header_code!='' && $stockPoint->ref_stock_point_header_code != null){

		    		$outStockpointId = $stockPoint->ref_stock_point_header_code;
		    	}else{

		    		$error_msg = 'Packet Push Error: '.$error_for.'- Stock Point , Message: Unable to find stock Point mapping code';
		    		Log::error( $error_msg );

		    		$outBound->error_before_call = $error_msg;
					$outBound->save();

		    		return ['error' => true , 'message' => $error_msg ];
					exit;
		    	}
                if($packet->destination_type=='Store'){
		    	$destinationstore =  $store = Store::select('store_reference_code','state_id')->where('v_id', $v_id)->where('store_id', $packet->destination_store_id)->first();
		    	if($destinationstore && $destinationstore->store_reference_code !='' && $destinationstore->store_reference_code != null){

		    		$replenishmentSiteCode = $destinationstore->store_reference_code;
		    	}else{

		    		$error_msg = 'Packet Push Error: '.$error_for.'- destination store , Message: Unable to find destination store mapping code ' ;
		    		Log::error($error_msg);
		    		$outBound->error_before_call = $error_msg;
					$outBound->save();

		    		return ['error' => true , 'message' => $error_msg ];
					exit;
		    	}
                }else{
                   $supplier  = Supplier::where('v_id',$v_id)->where('id',$packet->destination_supplier_id)->first();
                   if($supplier  && $supplier->reference_code != '' && $supplier->reference_code !=null ){
                  	 $replenishmentSiteCode =$supplier->reference_code;
                    }else{
                      
                      $error_msg = 'Packet Push Error: '.$error_for.'- destination supplier , Message: Unable to find destination supplier reference code ' ;
		    		Log::error($error_msg);
		    		$outBound->error_before_call = $error_msg;
					$outBound->save();

		    		return ['error' => true , 'message' => $error_msg ];
					exit;
                    
                    }

                }               
				$remarks = $packet->remarks;
				$packetCreationMode =null; 
				$adviceId=null;
				$adviceDetailId=null;
                if($packet->creation_mode=='Against Advice'){
                  $packetCreationMode = 'Against Order';
                  $advice      = Advise::find($packet->trans_src_doc_id);
                  $adviceId  =   $advice->against_id;

                }else{
                $packetCreationMode =  'Adhoc';	
                }

				$request['storeId']  = $storeCode;  //int
				$request['packetNo']  = $packet->packet_code;  //string 
				$request['packetDate']  = $packet->updated_at;  //string 
				$request['remarks']  = $remarks;  //string
				$request['outStockpointId']  = (int)$outStockpointId;  //string
				$request['replenishmentSiteCode']  = (int)$replenishmentSiteCode;
				$request['packetCreationMode']  = $packetCreationMode;


				$packetDetails = PacketDetail::where('v_id', $v_id)->where('packet_id', $packet->id)->get();
				$packetItems = [];
				$serialNo = 0;
				
				if($packetDetails){

				 foreach ($packetDetails as $item) {
				 		# code...
				 	       $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $item->barcode)->first();
        					$itemList = null;
        					if($bar){
				 	       		$itemList=ItemList::where('v_id',$v_id)->where('vendor_sku_detail_id', $bar->vendor_sku_detail_id)->first();
        					}
				 	       $vitem      = VendorItems::where('v_id',$v_id)->where('item_id',$itemList->item_id)->first();   
				 		  if($packet->creation_mode=='Against Advice'){
				 		  $adviceDetail= AdviseList::where('advice_id',$packet->trans_src_doc_id)->where('item_no',$item->barcode)->first();
				 		  $adviceDetailId=(string)$adviceDetail->ref_advice_detail_id;
				 		  }
                         $packetItems[] = [
                         	               'itemId'=>(string)$vitem->ref_item_code,
                         	                'qty'=>(float)$item->qty,
                                            'AdviceId'=>$adviceId,
                                            'adviceDetailId'=>$adviceDetailId
                                           ];

				 	}	

				}else{
                
                $error_msg = 'Packet Push Error: '.$error_for.'- items , Message: No item found in packet' ;
		    		Log::error($error_msg);
		    		$outBound->error_before_call = $error_msg;
					$outBound->save();

		    		return ['error' => true , 'message' => $error_msg ];
					exit;

				}

				$request['packetItems'] = $packetItems;

				$outBound->api_request = json_encode($request);
          		$outBound->save();

				// dd(json_encode($request));
                $config = new ConfigController($v_id); 
				$apiCaller = new  ApiCallerController([
					'url' => $config->apiBaseUrl.'/Packet',
					'data'=> $request, 
					'header' => [ 'Content-Type:application/json'],
					'auth_type' => $config->authType,
					'auth_token' => $config->authToken,
				]);
				# extract the body
				$response = $apiCaller->call();
		        $outBound->api_response = $response['body'];
		        $outBound->response_status_code = $response['header_status'];
		        $outBound->doc_no = $packet->packet_code;
		        $outBound->save();
		        $bodyresponse=json_decode($response['body']);
		        if(isset($bodyresponse->status) && $bodyresponse->status=='Created'){
                  
                  $packetDetails = json_decode($bodyresponse->result);
                  foreach ($packetDetails  as $key => $pac) {
                            
                    $packets=Packet::select('id')->where('packet_code',$pac->PacketNumber)->first();
                    $vitem      = VendorItems::where('v_id',$v_id)->where('ref_item_code',$pac->ItemId)->first();

                    $packetDetail =PacketDetail::where('packet_id',$packets->id)->where('item_id',$vitem->item_id)->update([
                                                 'ref_packet_item_id' =>$pac->PacketItemId,
                                                  'ref_advice_detail_id'=>$pac->AdviceDetailId
                                                ]);    
                  }
		         }

		        // Sync Status
		        if(in_array($response['header_status'], [200, 201])) {

		        	$packet->sync_status = '1';
		        	$packet->save();
		        } else {
		        	$packet->sync_status = '2';
		        	$packet->save();
		        }

                return $config->handleApiResponse($response);
				//$body = $(echo $response | sed -e 's/HTTPSTATUS\:.*//g');

				// dd(json_decode($response));

			}
		}catch (\Exception $e){

			$outBound->error_before_call = $e->getMessage().'\n'.$e->getTraceAsString();
			$outBound->save();

			$error_msg = 'Packet Push Error: Error for- '.$error_for.' Message: unexcepted error';
			Log::error( $error_msg);
			Log::error($e);

			return ['error' => true , 'message' => $error_msg ];
		
		}

	}


	public function packetvoid($params){

    $id = null;
    	$error_for = null;
    	$checkExits = OutboundApi::where([ 'for_transaction' => 'Packet void', 'transaction_id' => $params['packet_id'], 'client_id' => $params['client_id'], 'v_id' => (int)$params['v_id'], 'store_id' => (int)$params['store_id'] ])->first();
    	if(empty($checkExits)) {
    		$outBound = new OutboundApi;
    	} else {
    		$outBound = new OutboundApi;
    		$outBound->parent_id = $checkExits['_id'];
    	}
    	try {

	    	$v_id = $params['v_id'];
	    	$store_id = $params['store_id'];
	    	$packet_id = $params['packet_id'];
	        $client_id = $params['client_id'];
            JobdynamicConnection($v_id);
	    	$error_for = 'V_id: '.$v_id. ' store_id: '.$store_id.' packet_id: '.$packet_id.' client_name: Ginesys';

	    	$vendor = Organisation::select('ref_vendor_code')->where('id', $v_id)->first();
			if(!$vendor){

				$error_msg = 'Paket void Error: '.$error_for.'- Vendor , Message: Unable to find Vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;

			}elseif($vendor->ref_vendor_code =='' || $vendor->ref_vendor_code ==null){

				$error_msg = 'Paket void Error: '.$error_for.'- vendor , Message: Unable to find Mapping of vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;
			}

	    	$id = 'string';
			$request = [];

			$packet = Packet::where('id', $packet_id)->first();
		

			$client = OauthClient::where('id', $client_id)->first();

			$outBound->v_id = (int)$v_id;
			$outBound->store_id = (int)$store_id;
	    	$outBound->client_id = $client_id;
	    	$outBound->client_name = $client->name;
	    	$outBound->for_transaction = 'Packet void';
	    	$outBound->transaction_id = $packet_id;
	    	$outBound->event_class = isset($params['event_class'] )?$params['event_class']:'';
	    	$outBound->job_class = isset($params['job_class'])?$params['job_class']:'';
	    	$outBound->save();

			$store = Store::select('store_reference_code','state_id')->where('v_id', $v_id)->where('store_id', $store_id)->first();
			$storeCode = (int)$store->store_reference_code;

			if($packet){
                               
				$remarks = $packet->remarks;
				$packetCreationMode = 'Adhoc'; 

				$request['storeId']  = $storeCode;  //int
				$request['packetNo']  = $packet->packet_code;  //string 
                
                $outBound->api_request = json_encode($request);
          		$outBound->save();

                $config = new ConfigController($v_id); 
				$apiCaller = new  ApiCallerController([
					'url' => $config->apiBaseUrl.'/Packet/void',
					'data'=> $request, 
					'header' => [ 'Content-Type:application/json'],
					'auth_type' => $config->authType,
					'auth_token' => $config->authToken
					// ,'method' => 'PATCH'
				]);
				# extract the body
				$response = $apiCaller->call();
		        $outBound->api_response = $response['body'];
		        $outBound->response_status_code = $response['header_status'];
		        $outBound->doc_no = $packet->packet_code;
		        $outBound->save();
		        // Sync Status
		        if(in_array($response['header_status'], [200, 201])) {
		        	$packet->sync_status = '1';
		        	$packet->save();
		        } else {
		        	$packet->sync_status = '2';
		        	$packet->save();
		        }
		        return $config->handleApiResponse($response);
				//$body = $(echo $response | sed -e 's/HTTPSTATUS\:.*//g');

				// dd(json_decode($response));

			}
		}catch (\Exception $e){

			$outBound->error_before_call = $e->getMessage().'\n'.$e->getTraceAsString();
			$outBound->save();

			$error_msg = 'Packet Void Error: Error for- '.$error_for.' Message: unexcepted error';
			Log::error( $error_msg);
			Log::error($e);

			return ['error' => true , 'message' => $error_msg ];
		
		}

	}


}
