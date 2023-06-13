<?php

namespace App\Http\Controllers\Erp\Bluekaktus;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiCallerController;
use Illuminate\Http\Request;
use App\Model\Stock\StockPoints;
use App\Model\Stock\StockPointHeader;
use App\Model\Grn\Grn;
use App\Model\Grn\GrnList;
use App\Model\Grn\Advise;
use App\Model\Grn\AdviseList;
use App\Model\OutboundApi;
use App\Model\Oauth\OauthClient;
use App\Model\Items\VendorSkuDetails;
use App\Model\Items\VendorSku;
use App\Model\Items\VendorSkuDetailBarcode;
use App\Store;
use App\Organisation;
use App\OrganisationDetails;

use Log;

class GrnController extends Controller
{
	private $config= null;
	
	public function __construct()
    {
    	$this->config = new ConfigController;
    }

    public function grnPush($params){
    
    	$id = null;
    	$error_for = null;
    	$outBound = new OutboundApi;
    	try {

    		Log::info( ' Grn Push for grn id: '.$params['grn_id'].' v_id :'.$params['v_id'].' client_name: Bluekaktus inside grn push controller');

	    	$v_id = $params['v_id'];
	    	$store_id = $params['store_id'];
	    	$grn_id = $params['grn_id'];
	    	// $advice_id = $params['advice_id'];
	    	$client_id = 2;
             
	    	$error_for = 'V_id: '.$v_id. ' store_id: '.$store_id.' grn_id: '.$grn_id.' client_name: Ginesys';

	    	$id = 'string';
			$request = [];
            JobdynamicConnection($v_id); 
			$country_code=null;
			$orgAddress = OrganisationDetails::where('v_id',$v_id)->where('active','1')->first();
			if($orgAddress){
				$country_code = $orgAddress->countryDetail->sortname;
			}

			$grn = Grn::where('id', $grn_id)->first();
			$advice= Advise::select('client_advice_no','type','destination_short_code','source_store_code')->where('id', $grn->advice_id)->first();

			if($advice->type == 'GRT'){
				return $this->grnRetrunPush($params);
			}

			$client = OauthClient::where('id', $client_id)->first();

			$outBoundEntryExists = OutboundApi::where([ 'v_id' => $v_id, 'client_id' => $client_id, 'for_transaction' => 'Grn Push', 'transaction_id' => $grn_id ])->first();
	    	if(!empty($outBoundEntryExists)) {
	    		$outBound->parent_id = $outBoundEntryExists->_id;
	    	}

			$outBound->v_id = $v_id;
	    	$outBound->client_id = $client_id;
	    	$outBound->client_name = $client->name;
	    	$outBound->for_transaction = 'Grn Push';
	    	$outBound->transaction_id = $grn_id;
	    	$outBound->event_class = isset($params['event_class'] )?$params['event_class']:'';
	    	$outBound->job_class = isset($params['job_class'])?$params['job_class']:'';
	    	$outBound->save();

			$store = Store::select('store_reference_code','state_id')->where('v_id', $v_id)->where('store_id', $store_id)->first();
			$storeCode = (int)$store->store_reference_code;

			$vendor = Organisation::select('ref_vendor_code')->where('id', $v_id)->first();
			if(!$vendor){

				$error_msg = 'GRN Push Error: '.$error_for.'- Vendor , Message: Unable to find Vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;

			}elseif($vendor->ref_vendor_code =='' || $vendor->ref_vendor_code ==null){

				$error_msg = 'GRN Push Error: '.$error_for.'- vendor , Message: Unable to find Mapping of vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;

			}


			if(isset($grn) && $grn->grn_from =='ADVICE'){
				$grnDate =  $grn->created_at;
				if($country_code =='BD'){
			    	$grnDate = strtotime('-30 minutes', strtotime($grnDate));
			    	$grnDate = str_replace(' ', 'T', gmdate('Y-m-d H:i:s', $grnDate));
		    	}

				$grcNo = $grn->grn_no; 
				$grcDate = $grnDate;
				$replenishmentSourceCode = (int)$advice->source_store_code; // Ginesys source code
				$sourceAdviceNo = $advice->client_advice_no; // Ginesys source code
				// $storeCuid = '';
				#### Stock point is not finalize ####
		    	//$stock = StockPoints::select('ref_stock_point_code')->where('v_id', $v_id)->where('store_id', $store_id)->first();
		    	$receiveStockpointId = null;
				$damageStockpointId =  null;
		    	
		    	/*$stockPointDamage =  StockPoints::select('ref_stock_point_code')->where('store_id', $store_id)->where('code', 'DAMAGE')->first();
		    	if($stockPointDamage && $stockPointDamage->ref_stock_point_code!='' && $stockPointDamage->ref_stock_point_code != null){

		    		$damageStockpointId = $stockPointDamage->ref_stock_point_code;
		    	}else{

		    		$error_msg = 'GRN Push Error: '.$error_for.'- Damage Stock Point , Message: Unable to find Damae stock Point ';
		    		Log::error( $error_msg );

		    		$outBound->error_before_call = $error_msg;
					$outBound->save();

		    		return ['error' => true , 'message' => $error_msg ];
					exit;
		    	}*/

		    	/*$stockPointReceive =  StockPoints::select('ref_stock_point_code')->where('store_id', $store_id)->where('is_default', '1')->first();
		    	if($stockPointReceive && $stockPointReceive->ref_stock_point_code!='' && $stockPointReceive->ref_stock_point_code != null){

		    		$receiveStockpointId = $stockPointReceive->ref_stock_point_code;
		    	}else{

		    		$error_msg = 'GRN Push Error: '.$error_for.'- Receive Stock Point , Message: Unable to find Damae stock Point ' ;
		    		Log::error($error_msg);
		    		$outBound->error_before_call = $error_msg;
					$outBound->save();

		    		return ['error' => true , 'message' => $error_msg ];
					exit;
		    	}*/


				//What date is this and how this is different from grcDate
				$createdOn = str_replace(' ', 'T', $grn->created_at);
				$remarks = $grn->remarks;

				$request['clientCode'] =  $vendor->ref_vendor_code;//String - Organisation Code
				$request['storeCode'] =  $storeCode ;//String
				$request['userId'] =  $this->config->userId ;//Int
				//Pending need to finalize with blueKaktus
				$request['grcReceiptData'] =  [
					'ackwNo' => $grcNo, //String - ACK/1
					'ackwDate' => $grcDate, //String - 2019-12-06 10:14:40.600
					'issueNo' =>  $sourceAdviceNo//String - WHISSU/2320/2321/181218/1
				];

				$grnDetails = GrnList::where('v_id', $v_id)->where('grn_id', $grn->id)->get();
				$grcItems = [];
				$serialNo = 0;
				if(!$grnDetails->isEmpty()){
					foreach ($grnDetails as $key => $item) {
						$adviceList = AdviseList::where('id', $item->advice_list_id)->first();
						$bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $item->barcode)->first();
						if($bar){
							$vendorsku = VendorSku::select('vendor_sku_detail_id','barcode','sku')->where(['v_id' => $v_id, 'vendor_sku_detail_id' => $bar->vendor_sku_detail_id ])->first();
							$vendorsku->barcode = $bar->barcode;

						}

						if(!$vendorsku){
							$error_msg = 'GRN Push Error: '.$error_for.'- Grn,  Message: Unable to find Barcode for an item'; 
							Log::error($error_msg);
							$outBound->error_before_call = $error_msg;
							$outBound->save();
						}

						$packetId = $adviceList->packet_id;
						$adviceDetailId = $adviceList->ref_advice_detail_id;

						$itemId = $item->barcode;
						$issuedQantity = (int)$item->request_qty;
						$receiveQuantity = (int)$item->qty;
						$damageQuantity = $item->damage_qty;
						$shortExcessQuantity = ( $item->excess_qty >= 0)? $item->excess_qty : -$item->short_qty;
						$itemRemarks = $item->remarks;

						$barCode = [
							'barCode' => $vendorsku->barcode, //String
							'issueQuantity' => $issuedQantity, //int
							'receiveQuantity' => $receiveQuantity, //int
						];

						$grcItem = [
							'productCode' => $vendorsku->sku, //String
							'rate' => $item->unit_mrp, //Float
							'packetCode' => (int)'1', //int
							'barCodes' =>[ $barCode]
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

				$request['grcReceiptData']['itemDetails'] = $grcItems;

				$outBound->api_request = json_encode($request);
          		$outBound->save();
				// dd(json_encode($request));

				$apiCaller = new  ApiCallerController([
					'url' => $this->config->apiBaseUrl.'/api/zwing/grc-receipt',
					'data'=> $request, 
					'header' => [ 'Content-Type:application/json'],
					'auth_type' => $this->config->authType,
					'auth_token' => $this->config->authToken,
				]);
				# extract the body
				$response = $apiCaller->call();
				// dd($response);
				$outBound->api_response = $response;
				$outBound->save();

				return $this->config->handleApiResponse($response);
				//$body = $(echo $response | sed -e 's/HTTPSTATUS\:.*//g');

				// dd(json_decode($response));

			}
		}catch (\Exception $e){

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
    	$outBound = new OutboundApi;
    	try {

	    	$v_id = $params['v_id'];
	    	$store_id = $params['store_id'];
	    	$grn_id = $params['grn_id'];
	    	// $advice_id = $params['advice_id'];
	    	$client_id = 1;

	    	$error_for = 'V_id: '.$v_id. ' store_id: '.$store_id.' grn_id: '.$grn_id.' client_name: Ginesys';

	    	$client = OauthClient::where('id', $client_id)->first();

			$outBound->v_id = $v_id;
	    	$outBound->client_id = $client_id;
	    	$outBound->client_name = $client->name;
	    	$outBound->for_transaction = 'Grn Push';
	    	$outBound->transaction_id = $invoice_id;
	    	$outBound->event_class = isset($params['event_class'] )?$params['event_class']:'';
	    	$outBound->job_class = isset($params['job_class'])?$params['job_class']:'';
	    	$outBound->save();

	    	$store = Store::select('store_reference_code','state_id')->where('v_id', $v_id)->where('store_id', $store_id)->first();
			$storeCode = (int)$store->store_reference_code;
	    	$vendor = Vendor::select('ref_vendor_code')->where('v_id', $v_id)->first();
			if(!$vendor){

				$error_msg = 'GRN Push Error: '.$error_for.'- Vendor , Message: Unable to find Vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;

			}elseif($vendor->ref_vendor_code =='' || $vendor->ref_vendor_code ==null){

				$error_msg = 'GRN Push Error: '.$error_for.'- vendor , Message: Unable to find Mapping of vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;

			}

	    	$id = 'string';
			$request = [];

			$advice= Advise::select('client_advice_no','destination_short_code','source_store_code')->where('id', $advice_id)->where('type', 'GRT')->first();
			$store = Store::select('store_reference_code','state_id')->where('v_id', $v_id)->where('store_id', $store_id)->first();
			$storeCode = (int)$store->store_reference_code;

			$grn = Grn::where('id', $grn_id)->first();
			$grn = Grn::where('id', $grn_id)->first();
			if(isset($grn) && $grn->grn_from =='ADVICE'){

				$grtNo = $grn->grn_no; 
				$grtNoSequence = $grn->grn_no_seq; 
				$today_date = date('Y-m-d H:i:s'); 
				$grcDate = $grn->created_at;
				$sourceAdviceNo =  (int)$advice->source_store_code; // Ginesys source code
			
		    	$receiveStockpointId = null;
				$damageStockpointId =  null;
		    	// $outStockpointId =  StockPoints::select('ref_stock_point_code')->where('store_id', $store_id)->where('is_default', '1')->first();
		    	$StockpointhdId =  StockPoints::select('stock_point_header_id')->where('store_id', $store_id)->where('is_default', '1')->first();

		    	$outStockpointId =  StockPointHeader::select('ref_stock_point_header_code')->where('id', $StockpointhdId->stock_point_header_id)->first();

		    	if($outStockpointId && $outStockpointId->ref_stock_point_header_code!='' && $outStockpointId->ref_stock_point_header_code != null){

		    		$damageStockpointId = $outStockpointId->ref_stock_point_header_code;
		    	}else{

		    		$error_msg = 'GRN Push Error: '.$error_for.'- Out Stock Point , Message: Unable to find Out stock Point ';
		    		Log::error($error_msg );

		    		$outBound->error_before_call = $error_msg;
					$outBound->save();

		    		return ['error' => true , 'message' => $error_msg ];
					exit;
		    	}

				//What date is this and how this is different from grcDate
				$createdOn = str_replace(' ', 'T', $grn->created_at);
				$remarks = $grn->remarks;
				

				$request['clientCode'] =  $vendor->ref_vendor_code;//String - Organisation Code
				$request['storeCode'] =  $storeCode ;//String
				$request['userId'] =  $this->config->userId ;//Int
				//Pending need to finalize with blueKaktus
				$request['grcReceiptData'] =  [
					'ackwNo' => $grtNo, //String - ACK/1
					'ackwDate' => $grtDate, //String - 2019-12-06 10:14:40.600
					'issueNo' =>  $sourceAdviceNo//String - WHISSU/2320/2321/181218/1
				];

				$grnDetails = GrnList::where('v_id', $v_id)->where('grn_id', $grn->id)->get();
				$grcItems = [];
				if(!$grnDetails->isEmpty()){
					foreach ($grnDetails as $key => $item) {
						
						$adviceList = AdviseList::where('id', $item->advice_list_id)->first();
						$itemId = $item->barcode; //Confusing it should be item id or item barcode column
						$vendorsku = VendorSku::select('vendor_sku_detail_id','sku')->where(['v_id' => $v_id, 'sku' => $item->barcode ])->first();

						$bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('vendor_sku_detail_id', $vendorsku->vendor_sku_detail_id)->first();
						$vendorsku->barcode = $bar->barcode;

						if(!$vendorsku){
							$error_msg = 'GRT Push Error: '.$error_for.'- Grt,  Message: Unable to find Barcode for an item'; 
							Log::error($error_msg);
							$outBound->error_before_call = $error_msg;
							$outBound->save();
						}

						$barCode = [
							'barCode' => $vendorsku->barcode, //String
							'issueQuantity' => $issuedQantity, //int
							'receiveQuantity' => $receiveQuantity, //int
						];

						$grcItem = [
							'productCode' => $vendorsku->sku, //String
							'rate' => $item->unit_mrp, //Float
							'packetCode' => (int)'1', //int
							'quantity' => $item->qty, //String
							'barCodes' =>[ $barCode]
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

				$request['grcReceiptData']['itemDetails'] = $grcItems;

				$outBound->api_request = json_encode($request);
          		$outBound->save();

				// dd(json_encode($request));
				$apiCaller = new  ApiCallerController([
					'url' => $this->config->apiBaseUrl.'/grc-receipt',
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