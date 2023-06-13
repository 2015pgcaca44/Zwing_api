<?php

namespace App\Http\Controllers\Erp\Ginesys;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiCallerController;
use Illuminate\Http\Request;
use App\Model\Stock\StockPoints;
use App\Model\Stock\StockPointHeader;
use App\Model\Grn\Grn;
use App\Model\Grn\GrnList;
use App\Model\Grn\Advise;
use App\Model\Grn\AdviseList;
use App\Model\Item\ItemList;
use App\Model\Items\VendorItems;
use App\Model\OutboundApi;
use App\Model\Oauth\OauthClient;
use App\Store;

use Log;

class GrnController extends Controller
{
	private $config= null;
	
	public function __construct()
    {
    	//$this->config = new ConfigController(null);
    }

    public function grnPush($params){
    	
    	$id = null;
    	$error_for = null;
    	$checkExits = OutboundApi::where([ 'for_transaction' => 'Grn Push', 'transaction_id' => $params['grn_id'], 'client_id' => $params['client_id'], 'v_id' => (int)$params['v_id'], 'store_id' => (int)$params['store_id'] ])->first();
    	if(empty($checkExits)) {
    		$outBound = new OutboundApi;
    	} else {
    		$outBound = new OutboundApi;
    		$outBound->parent_id = $checkExits['_id'];
    	}
    	try {

    		Log::info( ' Grn Push for grn id: '.$params['grn_id'].' v_id :'.$params['v_id'].' client_name: Ginesys inside grn push controller');

	    	$v_id = $params['v_id'];
	    	$store_id = $params['store_id'];
	    	$grn_id = $params['grn_id'];
	    	// $advice_id = $params['advice_id'];
	    	$client_id = 1;
            JobdynamicConnection($v_id); 
	    	$error_for = 'V_id: '.$v_id. ' store_id: '.$store_id.' grn_id: '.$grn_id.' client_name: Ginesys';

	    	$id = 'string';
			$request = [];

			$grn = Grn::where('id', $grn_id)->first();
			$advice= Advise::select('client_advice_id','type','destination_short_code','source_store_code')->where('id', $grn->advice_id)->first();

			if($advice->type == 'GRT'){
				return $this->grnRetrunPush($params);
			}

			$client = OauthClient::where('id', $client_id)->first();

			$outBoundEntryExists = OutboundApi::where([ 'v_id' => $v_id, 'client_id' => $client_id, 'for_transaction' => 'Grn Push', 'transaction_id' => $grn_id ])->first();
	    	if(!empty($outBoundEntryExists)) {
	    		$outBound->parent_id = $outBoundEntryExists->_id;
	    	}

			$outBound->v_id = (int)$v_id;
			$outBound->store_id = (int)$store_id;
	    	$outBound->client_id = $client_id;
	    	$outBound->client_name = $client->name;
	    	$outBound->for_transaction = 'Grn Push';
	    	$outBound->transaction_id = $grn_id;
	    	$outBound->event_class = isset($params['event_class'] )?$params['event_class']:'';
	    	$outBound->job_class = isset($params['job_class'])?$params['job_class']:'';
	    	$outBound->save();

			$store = Store::select('store_reference_code','state_id')->where('v_id', $v_id)->where('store_id', $store_id)->first();
			$storeCode = (int)$store->store_reference_code;

			if(isset($grn) && $grn->grn_from =='ADVICE'){

				$grcNo = $grn->grn_no; 
				$grcDate = str_replace(' ', 'T', $grn->created_at);
				$replenishmentSourceCode = (int)$advice->source_store_code; // Ginesys source code
				$replenishmentSourceAdviceId = $advice->client_advice_id; // Ginesys source code
				// $storeCuid = '';
				#### Stock point is not finalize ####
		    	//$stock = StockPoints::select('ref_stock_point_code')->where('v_id', $v_id)->where('store_id', $store_id)->first();
		    	$receiveStockpointId = null;
				$damageStockpointId =  null;
		    	
		    	// $stockPointDamage =  StockPoints::select('ref_stock_point_code')->where('store_id', $store_id)->where('code', 'DAMAGE')->first();
				$StockpointhdId =  StockPoints::select('stock_point_header_id')->where('store_id', $store_id)->where('code', 'DAMAGE')->first();

		    	$stockPointDamage =  StockPointHeader::select('ref_stock_point_header_code')->where('id', $StockpointhdId->stock_point_header_id)->first();

		    	if($stockPointDamage && $stockPointDamage->ref_stock_point_header_code!='' && $stockPointDamage->ref_stock_point_header_code != null){

		    		$damageStockpointId = $stockPointDamage->ref_stock_point_header_code;
		    	}else{

		    		$error_msg = 'GRN Push Error: '.$error_for.'- Damage Stock Point , Message: Unable to find Damage stock Point ';
		    		Log::error( $error_msg );

		    		$outBound->error_before_call = $error_msg;
					$outBound->save();

		    		return ['error' => true , 'message' => $error_msg ];
					exit;
		    	}

		    	// $stockPointReceive =  StockPoints::select('ref_stock_point_code')->where('store_id', $store_id)->where('is_default', '1')->first();

		    	$StockpointhdId =  StockPoints::select('stock_point_header_id')->where('store_id', $store_id)->where('is_default', '1')->first();

		    	$stockPointReceive =  StockPointHeader::select('ref_stock_point_header_code')->where('id', $StockpointhdId->stock_point_header_id)->first();

		    	if($stockPointReceive && $stockPointReceive->ref_stock_point_header_code!='' && $stockPointReceive->ref_stock_point_header_code != null){

		    		$receiveStockpointId = $stockPointReceive->ref_stock_point_header_code;
		    	}else{

		    		$error_msg = 'GRN Push Error: '.$error_for.'- Receive Stock Point , Message: Unable to find Receive stock Point ' ;
		    		Log::error($error_msg);
		    		$outBound->error_before_call = $error_msg;
					$outBound->save();

		    		return ['error' => true , 'message' => $error_msg ];
					exit;
		    	}


				//What date is this and how this is different from grcDate
				$createdOn = str_replace(' ', 'T', $grn->created_at);
				$remarks = $grn->remarks;

				$request['storeCode']  = $storeCode;  //int
				$request['grcNo']  = $grcNo;  //string 
				$request['grcDate']  = $grcDate;  //string 2019-11-28T10:23:59.895Z
				$request['replenishmentSourceCode']  = $replenishmentSourceCode;  //Int
				$request['replenishmentSourceAdviceId']  = $replenishmentSourceAdviceId;  //string
				$request['receiveStockpointId']  = (int)$receiveStockpointId;  //int
				$request['damageStockpointId']  = (int)$damageStockpointId;  //int
				$request['createdOn']  = $createdOn;  //string 2019-11-28T10:23:59.895Z
				$request['remarks']  = $remarks;  //string


				$grnDetails = GrnList::where('v_id', $v_id)->where('grn_id', $grn->id)->get();
				$grcItems = [];
				$serialNo = 0;
				if(!$grnDetails->isEmpty()){
					foreach ($grnDetails as $key => $item) {

					  // $itemDetails =ItemList::where('barcode',$item->barcode)->where('v_id',$item->v_id)->first();
					  // $vItems=   VendorItems::where('item_id',$itemDetails->item_id)->where('v_id',$itemDetails->v_id)->first();
						$vItems = AdviseList::where('id', $item->advice_list_id)->where('item_no',$item->barcode)->first();
                       
						$adviceList = AdviseList::where('id', $item->advice_list_id)->first();
						$packetId = (string)$adviceList->packet_id;
						$adviceDetailId = (string)$adviceList->ref_advice_detail_id;
						#### Item id of ginesys ####
						$itemId = $vItems->ref_item_id;

						$receiveQuantity = (float)$item->qty;
						$damageQuantity = (float)$item->damage_qty;
						if($item->excess_qty==0.00 && $item->short_qty==0.00){
                          $shortExcessQuantity = (float)$item->excess_qty;
						}elseif($item->excess_qty>0){
                         $shortExcessQuantity  = (float)-$item->excess_qty;
						}else{
                         $shortExcessQuantity = (float)$item->short_qty;
						}
						
						$itemRemarks = (string)$item->remarks;

						$grcItem = [
							'packetId' => $packetId, //string
							'adviceDetailId' => $adviceDetailId, //string
							'itemId' => $itemId, //string Nullable true
							'receiveQuantity' => $receiveQuantity, //Double
							'damageQuantity' => $damageQuantity, //Double
							'shortExcessQuantity' => $shortExcessQuantity, //Double
							'itemRemarks' => $itemRemarks //string
						];

						$grcItems[] = $grcItem;
					}

				}else{
					$error_msg = 'GRN Push Error: '.$error_for.'- Grn,  Message: Unable to find Grn ';
					Log::error( $error_msg );

					$outBound->error_before_call = $error_msg;
					$outBound->save();
					return ['error' => true , 'message' => $error_msg ];
					exit;
				}

				$request['grcItems'] = $grcItems;
                $config = new ConfigController($v_id);
	          
	          	$outBound->api_request = json_encode($request);
          		$outBound->save();

				$apiCaller = new  ApiCallerController([
					'url' => $config->apiBaseUrl.'/GRC',
					'data'=> $request, 
					'header' => [ 'Content-Type:application/json'],
					'auth_type' => $config ->authType,
					'auth_token' => $config ->authToken,
				]);
				# extract the body
				$response = $apiCaller->call();
		        $outBound->api_response = $response['body'];
		        $outBound->response_status_code = $response['header_status'];
		        $outBound->doc_no = $grcNo;
		        $outBound->save();
		        // Sync Status
		        if(in_array($response['header_status'], [200, 201])) {
		        	$grn->sync_status = '1';
		        	$grn->save();
		        } else {
		        	$grn->sync_status = '2';
		        	$grn->save();
		        }
                return $config->handleApiResponse($response);
				//$body = $(echo $response | sed -e 's/HTTPSTATUS\:.*//g');

				// dd(json_decode($response));

			}
		}catch (\Exception $e){
            //dd($e->getMessage());
			$outBound->error_before_call = $e->getMessage().'\n'.$e->getTraceAsString();
			$outBound->save();

			$error_msg = 'GRN Receive Push Error: Error for- '.$error_for.' Message: unexcepted error';
			Log::error( $error_msg);
			Log::error($e);

			return ['error' => true , 'message' => $error_msg ];
		
		}

	}

	public function grnRetrunPush($params){
		$id = null;
		$error_for = null;
    	$checkExits = OutboundApi::where([ 'for_transaction' => 'Grn Push', 'transaction_id' => $params['grn_id'], 'client_id' => $params['client_id'], 'v_id' => (int)$params['v_id'], 'store_id' => (int)$params['store_id'] ])->first();
    	if(empty($checkExits)) {
    		$outBound = new OutboundApi;
    	} else {
    		$outBound = new OutboundApi;
    		$outBound->parent_id = $checkExits['_id'];
    	}
    	try {

	    	$v_id = $params['v_id'];
	    	$store_id = $params['store_id'];
	    	$grn_id = $params['grn_id'];
	    	// $advice_id = $params['advice_id'];
	    	$client_id = 1;

	    	$error_for = 'V_id: '.$v_id. ' store_id: '.$store_id.' grn_id: '.$grn_id.' client_name: Ginesys';

	    	$client = OauthClient::where('id', $client_id)->first();

			$outBound->v_id = (int)$v_id;
			$outBound->store_id = (int)$store_id;
	    	$outBound->client_id = $client_id;
	    	$outBound->client_name = $client->name;
	    	$outBound->for_transaction = 'Grn Push';
	    	$outBound->transaction_id = $grn_id;
	    	$outBound->event_class = isset($params['event_class'] )?$params['event_class']:'';
	    	$outBound->job_class = isset($params['job_class'])?$params['job_class']:'';
	    	$outBound->save();

	    	$id = 'string';
			$request = [];

			$advice= Advise::select('client_advice_id','destination_short_code','source_store_code')->where('id', $advice_id)->where('type', 'GRT')->first();
			$store = Store::select('store_reference_code','state_id')->where('v_id', $v_id)->where('store_id', $store_id)->first();
			$storeCode = (int)$store->store_reference_code;

			$grn = Grn::where('id', $grn_id)->first();
			$grn = Grn::where('id', $grn_id)->first();
			if(isset($grn) && $grn->grn_from =='ADVICE'){

				$grtNo = $grn->grn_no; 
				$grtNoSequence = $grn->grn_no_seq; 
				$today_date = str_replace(' ', 'T', date('Y-m-d H:i:s') ); 
				$grcDate = str_replace(' ', 'T', $grn->created_at);
				$replenishmentSourceCode =  (int)$advice->source_store_code; // Ginesys source code
				// $storeCuid = '';
				#### Stock point is not finalize ####
		    	//$stock = StockPoints::select('ref_stock_point_code')->where('v_id', $v_id)->where('store_id', $store_id)->first();
		    	$receiveStockpointId = null;
				$outStockpointId =  null;
		    	
		    	// $outStockpointId =  StockPoints::select('ref_stock_point_code')->where('store_id', $store_id)->where('is_default', '1')->first();

		    	$StockpointhdId =  StockPoints::select('stock_point_header_id')->where('store_id', $store_id)->where('is_default', '1')->first();

		    	$outStockpointId =  StockPointHeader::select('ref_stock_point_header_code')->where('id', $StockpointhdId->stock_point_header_id)->first();
		    	
		    	if($outStockpointId && $outStockpointId->ref_stock_point_code!='' && $outStockpointId->ref_stock_point_code != null){

		    		$outStockpointId = (int)$outStockpointId->ref_stock_point_code;
		    	}else{

		    		$error_msg = 'GRN Push Error: '.$error_for.'- Out Stock Point , Message: Unable to find Out stock Point ';
		    		Log::error($error_msg );

		    		$outBound->error_before_call = $error_msg;
					$outBound->save();

		    		return ['error' => true , 'message' => $error_msg ];
					exit;
		    	}

	

		    	$grtCreationMode = 'Against Order';
				//What date is this and how this is different from grcDate
				$createdOn = str_replace(' ', 'T', $grn->created_at);
				$remarks = $grn->remarks;
				$autoCancelPendingAdivceItem= 0; //Wheter to cancel all peding item or not

				$request['StoreCode'] =  $storeCode ;//String
				$request['GRTNo'] =  $grtNo; //String
				$request['GRTNoSequence'] =  $grtNoSequence ;//String
				$request['GRTDate'] =  $today_date ;//String //Api Hittig Date
				$request['GRTCreationMode'] =  $grtCreationMode ;//String
				$request['ReplenishmentSourceCode'] =  $replenishmentSourceCode ;//String
				$request['Reason'] =  $id ;//String
				$request['OutStockpointId'] =  $outStockpointId ;//String
				$request['CreatedOn'] =  $createdOn ;//String
				$request['LastModifiedOn'] =  $id ;//String
				$request['Remarks'] =  $remarks ;//String
				$request['AutoCancelPendingAdviceItems'] =  (string)$autoCancelPendingAdivceItem ;//String Wheter to cancel all peding item or not


				$grnDetails = GrnList::where('v_id', $v_id)->where('grn_id', $grn->id)->get();
				$grcItems = [];
				$serialNo = 0;
				if(!$grnDetails->isEmpty()){
					foreach ($grnDetails as $key => $item) {
						
						$adviceList = AdviseList::where('id', $item->advice_list_id)->first();

						#### Need to store packet Id ####
						$packetId = '';
						#### Need to add adivce details id in grn list table ####
						$adviceDetailId = $adviceList->ref_advice_detail_id;
						#### Item id of ginesys ####
						$itemId = $item->barcode; //Confusing it should be item id or item barcode column

						$receiveQuantity = (float)$item->qty;
						
						$itemRemarks = $item->remarks;

						$grcItem = [
							'ItemId' => $itemId, //String
							'Quantity' => $receiveQuantity, //String
							'Remarks' => $itemRemarks, //String
							'AdviceId' => $advice->client_advice_id, //String
							'AdviceDetailId' => $adviceDetailId //String
						];

						$grcItems[] = $grcItem;
					}

				}else{

					$error_msg = 'GRN Push Error: '.$error_for.'- Grn,  Message: Unable to find Grn '; 
					Log::error($error_msg);

					$outBound->error_before_call = $error_msg;
					$outBound->save();

					return ['error' => true , 'message' => $error_msg ];
					exit;
				}

				$request['GRTItem'] = $grcItems;

				$config = new ConfigController($v_id);

				$outBound->api_request = json_encode($request);
          		$outBound->save();

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
		        $outBound->doc_no = $grtNo;
		        $outBound->save();

                return $config->handleApiResponse($response); 
				//$body = $(echo $response | sed -e 's/HTTPSTATUS\:.*//g');

				// dd(json_decode($response));
			}	
			

		}catch (\Exception $e){

			$outBound->error_before_call = $e->getMessage().'\n'.$e->getTraceAsString();
			$outBound->save();

			$error_msg = 'GRN Receive Push Error: Error for- '.$error_for.' Message: unexcepted error';

			Log::error($error_msg );
			Log::error($e);

			return ['error' => true , 'message' => $error_msg ];
		
		}

	}

}