<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Traits\ErpFactoryTrait;
use App\Organisation;
use App\InvoicePush;
use App\Invoice;
use App\Store;
use App\Model\OutboundApi;
use App\Model\Oauth\OauthClient;
use Log;
use DB;


class InvoiceController extends Controller
{
    use ErpFactoryTrait;
    
	public function __construct()
    {

    }

    public function InvoicePush($params){
      //dd($params);
    	$checkExits = OutboundApi::where([ 'for_transaction' => 'Inovice Push', 'transaction_id' => $params['invoice_id'], 'client_id' => $params['client_id'], 'v_id' => (int)$params['v_id'], 'store_id' => (int)$params['store_id'] ])->first();
    	if(empty($checkExits)) {
    		$outBound = new OutboundApi;
    	} else {
    		$outBound = new OutboundApi;
    		$outBound->parent_id = $checkExits['_id'];
    	}

    	try{

    		$v_id = $params['v_id'];
	    	$store_id = $params['store_id'];
	    	$invoice_id = $params['invoice_id'];
	    	$client_id = $params['client_id'];

	    	// Check Transaction ID Exists

	    	$outBoundEntryExists = OutboundApi::where([ 'v_id' => $v_id, 'client_id' => $client_id, 'for_transaction' => 'Inovice Push', 'transaction_id' => $invoice_id ])->first();
	    	if(!empty($outBoundEntryExists)) {
	    		$outBound->parent_id = $outBoundEntryExists->_id;
	    	}

	    	$client = OauthClient::where('id', $client_id)->first();
	    	JobdynamicConnection($v_id);
	    	$outBound->v_id = (int)$v_id;
	    	$outBound->store_id = (int)$store_id;
	    	$outBound->client_id = $client_id;
	    	$outBound->client_name = $client->name;
	    	$outBound->for_transaction = 'Inovice Push';
	    	$outBound->transaction_id = $invoice_id;
	    	$outBound->event_class = isset($params['event_class'] )?$params['event_class']:'';
	    	$outBound->job_class = isset($params['job_class'])?$params['job_class']:'';
	    	$outBound->save();

	    	$dbName = DB::connection()->getDatabaseName();
	    	$error_for = 'V_id: '.$v_id. ' store_id: '.$store_id.' invoice_id: '.$invoice_id.' client_id: '.$client_id. ' DB Name: '.$dbName;

	    	$vendor = Organisation::select('ref_vendor_code')->where('id', $v_id)->first();
			if(!$vendor){

				$error_msg = 'Invoice Push Error: '.$error_for.'- Vendor , Message: Unable to find Vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;

			}elseif($vendor->ref_vendor_code =='' || $vendor->ref_vendor_code ==null){

				$error_msg = 'Invoice Push Error: '.$error_for.'- vendor , Message: Unable to find Mapping of vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;
			}

	    	$store = Store::select('store_reference_code','state_id','gst')->where('v_id', $v_id)->where('store_id', $store_id)->first();

	    	$params['client'] = $client;
	    	$params['error_for'] = $error_for;
	    	$params['outBound'] = $outBound;
	    	$params['store'] = $store;
	    	$params['vendor'] = $vendor;

    		return $this->callMethod($params, __CLASS__, __METHOD__);

    	}catch (\Exception $e){
			
			$outBound->error_before_call = $e->getMessage().'\n'.$e->getTraceAsString();
			$outBound->save();

			$error_msg = 'Bill Push Error: Error for- '.$error_for.' Message: unexcepted error';
			Log::error($error_msg );
			Log::error($e);

			return ['error' => true , 'message' => $error_msg ];
		
		}
        
	}

	public function depositeRefund($params){

		$checkExits = OutboundApi::where([ 'for_transaction' => 'Deposite Refund', 'transaction_id' => $params['payment_id'], 'client_id' => $params['client_id'], 'v_id' => (int)$params['v_id'], 'store_id' => (int)$params['store_id'] ])->first();
    	if(empty($checkExits)) {
    		$outBound = new OutboundApi;
    	} else {
    		$outBound = new OutboundApi;
    		$outBound->parent_id = $checkExits['_id'];
    	}

		try{

    		$v_id = $params['v_id'];
	    	$store_id = $params['store_id'];
	    	$payment_id = $params['payment_id'];
	    	$client_id = $params['client_id'];

	    	$client = OauthClient::where('id', $client_id)->first();
	    	JobdynamicConnection($v_id);
	    	$outBound->v_id = (int)$v_id;
	    	$outBound->store_id = (int)$store_id;
	    	$outBound->client_id = $client_id;
	    	$outBound->client_name = $client->name;
	    	$outBound->for_transaction = 'Deposite Refund';
	    	$outBound->transaction_id = $payment_id;
	    	$outBound->event_class = isset($params['event_class'] )?$params['event_class']:'';
	    	$outBound->job_class = isset($params['job_class'])?$params['job_class']:'';
	    	$outBound->save();

	    	$dbName = DB::connection()->getDatabaseName();
	    	$error_for = 'V_id: '.$v_id. ' store_id: '.$store_id.' payment_id: '.$payment_id.' client_id: '.$client_id. ' DB Name: '.$dbName;

	    	$vendor = Organisation::select('ref_vendor_code')->where('id', $v_id)->first();
			if(!$vendor){

				$error_msg = 'Deposite Refunds Error: '.$error_for.'- Vendor , Message: Unable to find Vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;

			}elseif($vendor->ref_vendor_code =='' || $vendor->ref_vendor_code ==null){

				$error_msg = 'Deposite Refunds Error: '.$error_for.'- vendor , Message: Unable to find Mapping of vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;
			}

	    	$store = Store::select('store_reference_code','state_id','gst')->where('v_id', $v_id)->where('store_id', $store_id)->first();

	    	$params['client'] = $client;
	    	$params['error_for'] = $error_for;
	    	$params['outBound'] = $outBound;
	    	$params['store'] = $store;
	    	$params['vendor'] = $vendor;

    		return $this->callMethod($params, __CLASS__, __METHOD__);

    	}catch (\Exception $e){
			
			$outBound->error_before_call = $e->getMessage().'\n'.$e->getTraceAsString();
			$outBound->save();

			$error_msg = 'Deposite Refunds Error: Error for- '.$error_for.' Message: unexcepted error';
			Log::error($error_msg );
			Log::error($e);

			return ['error' => true , 'message' => $error_msg ];
		
		}

	}

	public function getAllUnsyncInvoice(Request $request){
		$fromdate = $request['fromdate'];
		$todate = $request['todate'];
    	$v_id = $request['v_id'];
    	$clientid = DB::connection('mysql')->table('vendor')->select('client_id')->where('id', $v_id)->first();
		$client_id = $clientid->client_id;

		$vendor = Organisation::select('ref_vendor_code')->where('id', $v_id)->first();
		$client = OauthClient::where('id', $client_id)->first();
    	$request['client'] = $client;
    	$request['vendor'] = $vendor;
		$request['client_id'] = $client_id;
		$request['fromdate'] = $fromdate;
		$request['todate'] = $todate;
		
    	return $this->callMethod($request, __CLASS__, __METHOD__);
	}

}