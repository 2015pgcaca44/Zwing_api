<?php

namespace App\Http\Controllers\Erp\Bluekaktus;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiCallerController;
use App\Model\Stock\StockPointTransfer;
use App\Model\Stock\StockPointTransferDetail;
use App\Model\OutboundApi;
use App\Model\Oauth\OauthClient;
use App\Store;
use App\Organisation;
use App\OrganisationDetails;

class GrtController extends Controller
{
    

   public function GrtPush($params){

   	    $id = null;
    	$error_for = null;
    	$outBound = new OutboundApi;
    	try {

   	          if($params['type']=='SST')
   	          {

                   return $this->sstPush($params);
    
              }
            }catch (\Exception $e){

			$outBound->error_before_call = $e->getMessage().'\n'.$e->getTraceAsString();
			$outBound->save();

			$error_msg = 'SST Receive Push Error: Error for- '.$error_for.' Message: unexcepted error';
			Log::error( $error_msg);
			Log::error($e);

			return ['error' => true , 'message' => $error_msg ];
		
		}  

   }


   public function sstPush($params){


   	    $id = null;
    	$error_for = null;
    	$outBound = new OutboundApi;
    	try {
            
            $v_id = $params['v_id'];
	    	$store_id = $params['store_id'];
	    	$grt_id = $params['grt_id'];
	    	$client_id = $params['client_id'];

	    	$error_for = 'V_id: '.$v_id. ' store_id: '.$store_id.' grt_id: '.$grt_id.' client_name: Ginesys';

	    	$vendor = Organisation::select('ref_vendor_code')->where('id', $v_id)->first();
			if(!$vendor){

				$error_msg = 'SST push Error: '.$error_for.'- Vendor , Message: Unable to find Vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;

			}elseif($vendor->ref_vendor_code =='' || $vendor->ref_vendor_code ==null){

				$error_msg = 'SST push Error: '.$error_for.'- vendor , Message: Unable to find Mapping of vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;
			}


	    	$id = null;
			$request = [];
            $clientCode = $vendor->ref_vendor_code;
			$sst = StockPointTransfer::where('id', $grt_id)->first();
		

			$client = OauthClient::where('id', $client_id)->first();

			$outBound->v_id = $v_id;
	    	$outBound->client_id = $client_id;
	    	$outBound->client_name = $client->name;
	    	$outBound->for_transaction = 'SST Push';
	    	$outBound->transaction_id = $grt_id;
	    	$outBound->event_class = isset($params['event_class'] )?$params['event_class']:'';
	    	$outBound->job_class = isset($params['job_class'])?$params['job_class']:'';
	    	$outBound->save();

			$store = Store::select('store_reference_code','state_id')->where('v_id', $v_id)->where('store_id', $store_id)->first();
			$storeCode = (int)$store->store_reference_code;

			if($sst){
				// $outStockpointId =  null;
				// $destination_store_id=null;
				// $replenishmentSourceCode=null;
				$issueToLocation=null;
		    	
		    	$destStore = Store::select('store_reference_code','state_id')->where('v_id', $v_id)->where('store_id', $sst->dest_store_id)->first();
		    	if($destStore && $destStore->store_reference_code!='' && $destStore->store_reference_code != null){

		    		$issueToLocation = $destStore->store_reference_code;
		    	}else{

		    		$error_msg = 'sst Push Error: '.$error_for.'- Store , Message: Unable to find  destination store mapping code';
		    		Log::error( $error_msg );

		    		$outBound->error_before_call = $error_msg;
					$outBound->save();

		    		return ['error' => true , 'message' => $error_msg ];
					exit;
		    	}
                 
      
               $issueRefNo = $sst->sto_no.'/'.$sst->id;
               
                $request['clientCode']  = $clientCode;
				$request['storeCode']  = $storeCode;
				$request['userId']     =$sst->v_id;
				$request['stockIssueData']['issueDate']=$id;
				$request['stockIssueData']['issueToLocation']=$issueToLocation;
				$request['stockIssueData']['issueRefNo']=$issueRefNo;
				$request['stockIssueData']['remarks']=$sst->remarks;
				$request['stockIssueData']['status']=$sst->status;
				$request['stockIssueData']['vehicleNo']=$id;
				$request['stockIssueData']['modeOfTransport']=$id;
				$request['stockIssueData']['grossWeight']=$id;
				$request['stockIssueData']['netWeight']=$id;
				$request['stockIssueData']['totalSaleAmount']=$sst->subtotal;
				$request['stockIssueData']['grandTotal']=$sst->total;
				$request['stockIssueData']['ebillNo']=$id;
				
				$sstDetails = StockPointTransferDetail::where('stock_point_transfer_id', $sst->id)->get();
				$sstItems = [];
				$serialNo = 0;
				
				if($sstDetails){

				 foreach ($sstDetails as $sst) {                          
				 		
                         $sstItems[] = [
                         	                'productCode'=> (string)$sst->packet_code,
									        'barCode'=>(string)$packetDetail->barCode,
									        'costPrice'=> (float)$sst->supply_price,
									        'quantity'=>(float)$sst->subtotal,
									        'mrp'=> (float)$sst->supply_price,
									        'boxNumber'=> 0,
									        'amount'=> ,
									        'detailTotalAmount'=> 0,
									        'hsnCode'=> 0,
									        'discountPercent'=> 0,
									        'discountPercentAmount'=> 0,
									        'totalDiscountAmount'=> 0,
                                            'taxAmount'=> 0,
                                            'rate'=> (float)$sst->supply_price,
                                           ];
                        $sstItems['tax"']=[
                                           'taxType'=>0,
                                           'taxPercentage'=>0,
                                           'taxOnAmount'=>0,
                                           'taxValue'=>0
                                          ];                  

				 	}	

				}else{
                
                $error_msg = 'sst Push Error: '.$error_for.'- items , Message: No item found in sst' ;
		    		Log::error($error_msg);
		    		$outBound->error_before_call = $error_msg;
					$outBound->save();

		    		return ['error' => true , 'message' => $error_msg ];
					exit;

				}

				$request['stockIssueData']['items'] = $sstItems;

				$outBound->api_request = json_encode($request);
          		$outBound->save();
				// dd(json_encode($request));

				$apiCaller = new  ApiCallerController([
					'url' => $this->config->apiBaseUrl.'/submit-stock-issue',
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

                return $this->config->handleApiResponse($response['header_status']);
   	          
            }catch (\Exception $e){

			$outBound->error_before_call = $e->getMessage().'\n'.$e->getTraceAsString();
			$outBound->save();

			$error_msg = 'SST Push Error: Error for- '.$error_for.' Message: unexcepted error';
			Log::error( $error_msg);
			Log::error($e);

			return ['error' => true , 'message' => $error_msg ];
		
		}  


 




   }

}
