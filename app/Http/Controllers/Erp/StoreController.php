<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Traits\ErpFactoryTrait;
use App\Organisation;
use App\Store;
use App\Model\OutboundApi;
use App\Model\Oauth\OauthClient;
use Log;

class StoreController extends Controller
{
    use ErpFactoryTrait;
    
	public function __construct()
    {

    }

    public function operationStarted($params){

    	$checkExits = OutboundApi::where([ 'for_transaction' => 'Operation Started of Store', 'transaction_id' => $params['store_id'], 'client_id' => $params['client_id'], 'v_id' => (int)$params['v_id'], 'store_id' => (int)$params['store_id'] ])->first();
    	if(empty($checkExits)) {
    		$outBound = new OutboundApi;
    	} else {
    		$outBound = new OutboundApi;
    		$outBound->parent_id = $checkExits['_id'];
    	}
    	$error_for ='';
    	try{

    		$v_id = $params['v_id'];
	    	$store_id = $params['store_id'];
	    	$client_id = $params['client_id'];
	    	// $api_status = $params['api_status'];

	    	$client = OauthClient::where('id', $client_id)->first();
	    	JobdynamicConnection($v_id); 

	    	$outBoundEntryExists = OutboundApi::where([ 'v_id' => $v_id, 'client_id' => $client_id, 'for_transaction' => 'Operation Started of Store', 'transaction_id' => $store_id ])->first();
	    	if(!empty($outBoundEntryExists)) {
	    		$outBound->parent_id = $outBoundEntryExists->_id;
	    	}

	    	$outBound->v_id = (int)$v_id;
	    	$outBound->store_id = (int)$store_id;

	    	$outBound->client_id = $client_id;
	    	$outBound->client_name = $client->name;
	    	$outBound->for_transaction = 'Operation Started of Store';
	    	$outBound->transaction_id = $store_id;
	    	$outBound->event_class = isset($params['event_class'] )?$params['event_class']:'';
	    	$outBound->job_class = isset($params['job_class'])?$params['job_class']:'';
	    	$outBound->save();

	    	$error_for = 'V_id: '.$v_id. ' store_id: '.$store_id.' client_id: '.$client_id;

	    	$vendor = Organisation::select('ref_vendor_code')->where('id', $v_id)->first();
			if(!$vendor){

				$error_msg = 'Operation Started Push Error: '.$error_for.'- Vendor , Message: Unable to find Vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;

			}elseif($vendor->ref_vendor_code =='' || $vendor->ref_vendor_code ==null){

				$error_msg = 'Operation Started Error: '.$error_for.'- vendor , Message: Unable to find Mapping of vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;
			}

	    	$store = Store::select('store_reference_code','api_status_on_at','store_active_date')->where('v_id', $v_id)->where('store_id', $store_id)->first();

	    	$params['client'] = $client;
	    	$params['error_for'] = $error_for;
	    	$params['outBound'] = $outBound;
	    	$params['store'] = $store;
	    	$params['vendor'] = $vendor;

    		return $this->callMethod($params, __CLASS__, __METHOD__);

    	}catch (\Exception $e){
			// dd($e);
			$outBound->error_before_call = $e->getMessage().'\n'.$e->getTraceAsString();
			$outBound->save();

			$error_msg = 'Operation Started Error: Error for- '.$error_for.' Message: unexcepted error';
			Log::error($error_msg );
			Log::error($e);

			return ['error' => true , 'message' => $error_msg ];
		
		}
        
	}

}