<?php

namespace App\Http\Controllers\Erp\Ginesys;

use App\CashTransaction;
use App\Http\Controllers\ApiCallerController;
use App\Http\Controllers\Controller;
use App\StoreExpense;
use Log;
use DB;


class CashManagementController extends Controller
{
	private $config= null;
	
	public function __construct()
    {
    	// $this->config = new ConfigController;
    }

    public function pettyCashPush($params){

   
    	$id = null;
    	$outBound = $params['outBound'];
    	$v_id = $params['v_id'];
    	$store_id = $params['store_id'];
    	$client_id = $params['client_id'];
    	$cash_transaction_id = $params['cash_transaction_id'];
    	$PTCHeadCode =$params['PTCHeadCode'];
        $transfer_type = $params['transfer_type'];
    	$client = $params['client'];
    	$error_for = $params['error_for'];
    	$store = $params['store'];
    	$vendor = $params['vendor'];
        $source_currency= null;
        $target_currency=null;
        JobdynamicConnection($v_id);
    	//CashTransaction::where
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

        $extrarate=getExchangeRate($v_id,$source_currency,$target_currency,1);
        if($extrarate['status']=='error'){
            $error_msg = $extrarate['message'];
            $outBound->error_before_call = $error_msg;
            $outBound->save();
            Log::error($error_msg);

            return [ 'error' => true , 'message' => $error_msg ];
            exit;
        }

    	if($transfer_type=='1'){

    		$transaction=CashTransaction::where('store_id',$store_id)
    		                ->where('v_id',$v_id)
    		                ->where('id',$cash_transaction_id)
    		                ->first();
    	  if($transaction)
    	  {
             if($transaction->transaction_behaviour=='IN')
             {
               $headMode = 'R';
             }else
             {
               $headMode = 'P';
             }

             $docNo       = $transaction->doc_no;
             $PTCHeadMode = $headMode;
             $docDate     = $transaction->date;
             $remarks     = $transaction->remark;
             $amount      =  $transaction->amount;
    	  }else
    	  {
            $error_msg = 'Petty Cash Push Error: '.$error_for.'- Transaction Id, Message: Invalid Transaction Id ';
	    		$outBound->error_before_call = $error_msg;
				$outBound->save();
	    		Log::error($error_msg);
    		   return [ 'error' => true , 'message' => $error_msg ];
    	  }                

    	}else
    	{
          $transaction= StoreExpense::where('store_id',$store_id)
    		                ->where('v_id',$v_id)
    		                ->where('id',$cash_transaction_id)
    		                ->first();
           if($transaction)
           {
             $docNo          = $transaction->doc_no;
             $PTCHeadMode    = 'P';
             $docDate        = $transaction->created_at;
             $remarks        = $transaction->expense_remark;
             $exchangeamount =  getExchangeRate($v_id,$source_currency,$target_currency,$transaction->amount);
             $amount         = $exchangeamount['amount'];



    	   }else
    	     {
    	  	   $error_msg = 'Petty Cash Push Error: '.$error_for.'- Transaction Id, Message: Invalid Transaction Id ';
	    		$outBound->error_before_call = $error_msg;
				$outBound->save();
				Log::error($error_msg);
				return ['error' => true, 'message' => $error_msg];
			}
		}

		$request = [];

		$request['storeId'] = (int) $store->store_reference_code; // Int
		$request['billNo'] = (string) $docNo;
		$request['billdate'] = $docDate;
		$request['amount'] = (float) $amount; // Float
		$request['TerminalName'] = 'noterminal';
		$request['PTCHeadCode'] = (int) $PTCHeadCode;
		$request['PTCHeadMode'] = $PTCHeadMode; // String
		$request['refDocNo'] = $id;
		$request['refDocDate'] = $id;
		$request['Remarks'] = $remarks; // String


        $outBound->api_request = json_encode($request);
        $outBound->save();
		// dd(json_encode($request));
		$config = new ConfigController($v_id);

		$apiCaller = new ApiCallerController([
			'url' => $config->apiBaseUrl . '/PettyCashBill',
			'data' => $request,
			'header' => ['Content-Type:application/json'],
			'auth_type' => $config->authType,
			'auth_token' => $config->authToken,
		]);
		# extract the body
		$response = $apiCaller->call();
		$outBound->api_response = $response['body'];
		$outBound->response_status_code = $response['header_status'];
        $outBound->doc_no = $docNo;
		$outBound->save();

		// Sync Status
        if(in_array($response['header_status'], [200, 201])) {
        	$transaction->sync_status = '1';
        	$transaction->save();
        } else {
        	$transaction->sync_status = '2';
        	$transaction->save();
        }

		return $config->handleApiResponse($response);

		// if(json_encode($response)){

		// }
		// dd(json_decode($response));
		//return $response;
	}

}