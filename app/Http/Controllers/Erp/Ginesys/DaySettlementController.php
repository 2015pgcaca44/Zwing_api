<?php

namespace App\Http\Controllers\Erp\Ginesys;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiCallerController;
use App\Model\OutboundApi;
use App\Model\Oauth\OauthClient;
use App\Store;
use App\DaySettlement;
use App\Organisation;
use Log;

class DaySettlementController extends Controller
{
    
    private $config= null;
	
	public function __construct()
    {
    	//$this->config = new ConfigController;
    }
    

    public function settlementPush($params){
    
        $id = null;
    	$error_for = null;
    	$checkExits = OutboundApi::where([ 'for_transaction' => 'Day Settlement Push', 'transaction_id' => $params['ds_id'], 'client_id' => $params['client_id'], 'v_id' => (int)$params['v_id'], 'store_id' => (int)$params['store_id'] ])->first();
    	if(empty($checkExits)) {
    		$outBound = new OutboundApi;
    	} else {
    		$outBound = new OutboundApi;
    		$outBound->parent_id = $checkExits['_id'];
    	}
    	try {

    		Log::info( 'DaySettlementPush Push for ds_id: '.$params['ds_id'].' v_id :'.$params['v_id'].' client name: Ginesys ,Inside ginesys daysettlement Controllers' );

	    	$v_id = $params['v_id'];
	    	$store_id = $params['store_id'];
	    	$ds_id = $params['ds_id'];
	    	$client_id = $params['client_id'];

	    	$error_for = 'v_id: '.$v_id. ' store_id: '.$store_id.' ds_id: '.$ds_id.' client_name: Ginesys';
            JobdynamicConnection($v_id); 
			$client = OauthClient::where('id', $client_id)->first();

			$outBoundEntryExists = OutboundApi::where([ 'v_id' => $v_id, 'client_id' => $client_id, 'for_transaction' => 'Day Settlement Push', 'transaction_id' => $ds_id ])->first();
	    	if(!empty($outBoundEntryExists)) {
	    		$outBound->parent_id = $outBoundEntryExists->_id;
	    	}

			$outBound->v_id = (int)$v_id;
			$outBound->store_id = (int)$store_id;
	    	$outBound->client_id = $client_id;
	    	$outBound->client_name = $client->name;
	    	$outBound->for_transaction = 'Day Settlement Push';
	    	$outBound->transaction_id = $ds_id;
	    	$outBound->event_class = isset($params['event_class'] )?$params['event_class']:'';
	    	$outBound->job_class = isset($params['job_class'])?$params['job_class']:'';
	    	$outBound->save();

			$vendor = Organisation::select('ref_vendor_code')->where('id', $v_id)->first();
			if(!$vendor){

				$error_msg = 'Day Settlement Error: '.$error_for.'- Vendor , Message: Unable to find Vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;

			}elseif($vendor->ref_vendor_code =='' || $vendor->ref_vendor_code ==null){

				$error_msg = 'Day Settlement Error: '.$error_for.'- vendor , Message: Unable to find Mapping of vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;
			}
                          
	    	$store = Store::select('store_reference_code','state_id','gst')->where('v_id',$v_id)->where('store_id',$store_id)->first();

	    	if(!$store){
				$error_msg = 'Day Settlement Error: '.$error_for.'- Store , Message: Unable to find stores ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();
	    		return ['error' => true , 'message' => $error_msg ];
			  exit;
			}elseif($store->store_reference_code =='' || $store->store_reference_code ==null){

				$error_msg = 'Day Settlement Error: '.$error_for.'- Store , Message: Unable to find Mapping of store ';
	    		Log::error( $error_msg );
	    		$outBound->error_before_call = $error_msg;
				$outBound->save();
	    		return ['error' => true , 'message' => $error_msg ];
				exit;
			}

			$storeCode = (int)$store->store_reference_code;
			$id = 'string';
			$request = [];
			$daySettlement = DaySettlement::where('id', $ds_id)->first();

				//What date is this and how this is different from grcDate
			$footfall=0;
			$remarks = null;
            $noSaleReasonId = 0;
            $grc=$daySettlement->nos_grn_created;
            $grt=$daySettlement->nos_grt_created;
            $noPosbill =(float)$daySettlement->nos_sales_bills_generated+(float)$daySettlement->nos_return_bills_generated;
            $noPacket=$daySettlement->no_packet_sealed;
            $noAuditJournal=0;
            $noMiscIssueReceive=$daySettlement->nos_adj_created;
            $noPTCBill=$daySettlement->nos_store_expenses_generated;
            $noDepositRefund=0;
            $stf=$daySettlement->nos_spt_created;
			$request['storeCode']  = $storeCode;  //int
			//$request['settlementId']  = (string)$daySettlement->id;  //string 
			$request['settlementFor']  = $daySettlement->date;
			$request['noSaleReasonId']  = null;  //string 2019-11-28T10:23:59.895Z 
			$request['footfall']  = $footfall;  //string 2019-11-28T10:23:59.895Z
			$request['comment']  = $remarks;  //string
            $request['auditInfo'] = [
                                     ['auditType'=>'GRC',
                                       'value'=>(float)$grc
                                     ],
                                     ['auditType'=>'POSBill',
                                       'value'=>(float)$noPosbill
                                     ],
                                     ['auditType'=>'Packet',
                                       'value'=>(float)$noPacket
                                     ],
                                     ['auditType'=>'GRT',
                                       'value'=>(float)$grt
                                     ],
                                     ['auditType'=>'AuditJournal',
                                       'value'=>(float)$noAuditJournal
                                     ],
                                     ['auditType'=>'MiscIssueReceive',
                                       'value'=>(float)$noMiscIssueReceive
                                     ],
                                     ['auditType'=>'PTCBill',
                                       'value'=>(float)$noPTCBill
                                     ],
                                     ['auditType'=>'DepositRefund',
                                       'value'=>(float)$noDepositRefund
                                     ],
                                     ['auditType'=>'STF',
                                       'value'=>(float)$stf
                                     ]
                                     ];

                $outBound->api_request = json_encode($request);
          		$outBound->save();
				// dd(json_encode($request));
                $config = new ConfigController($v_id);
				$apiCaller = new  ApiCallerController([
					'url' => $config->apiBaseUrl.'/POSSettlement',
					'data'=> $request, 
					'header' => [ 'Content-Type:application/json'],
					'auth_type' => $config->authType,
					'auth_token' => $config->authToken,
				]);
				# extract the body
				$response = $apiCaller->call();
		        $outBound->api_response = $response['body'];
		        $outBound->response_status_code = $response['header_status'];
		        $outBound->save();
		        // Sync Status
		        if(in_array($response['header_status'], [200, 201])) {
		        	$daySettlement->sync_status = '1';
		        	$daySettlement->save();
		        } else {
		        	$daySettlement->sync_status = '2';
		        	$daySettlement->save();
		        }
                return $config->handleApiResponse($response); 
				//$body = $(echo $response | sed -e 's/HTTPSTATUS\:.*//g');

				// dd(json_decode($response));

		
		}catch (\Exception $e){

			$outBound->error_before_call = $e->getMessage().'\n'.$e->getTraceAsString();
			$outBound->save();

			$error_msg = 'Day Settlement Push Error: Error for- '.$error_for.' Message: unexcepted error';
			Log::error( $error_msg);
			Log::error($e);

			return ['error' => true , 'message' => $error_msg ];
		
		}

	}
}
