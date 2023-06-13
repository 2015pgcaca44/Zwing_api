<?php

namespace App\Http\Controllers\PaymentIntegration;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;

class PineLabsController extends Controller
{
	public function uploadBill(Request $request)
	{
		// if($request->ajax()) {
			// dd($request->all());
			$response = '';
	        try {
	            $client = new Client(); //GuzzleHttp\Client
	            $pos_data = [ 'TransactionNumber' => $request->TransactionNumber, 'SequenceNumber' => $request->SequenceNumber, 
	            'AllowedPaymentMode' => $request->AllowedPaymentMode, 'MerchantStorePosCode' => $request->MerchantStorePosCode, 'Amount' => $request->Amount, 'UserID' => $request->UserID, 'MerchantID' => $request->MerchantID, 'SecurityToken' => $request->SecurityToken,'IMEI' => $request->IMEI, 'AutoCancelDurationInMinutes' => $request->AutoCancelDurationInMinutes ];

	            $res = $client->request('POST', 'https://www.plutuscloudserviceuat.in:8201/API/CloudBasedIntegration/V1/UploadBilledTransaction', [
	                'form_params' => $pos_data
	            ]);


	            $response = json_decode($res->getBody(), true);

	        } catch (\GuzzleHttp\Exception\ClientException $e) {
	            // echo Psr7\str($e->getRequest());
	            $response = Psr7\str($e->getResponse());
	            // dd($response);
	        }
	        // dd($response); 
	        return response()->json([ 'data' => $response ], 200);

		// }
	}

	public function GetStatus(Request $request)
	{
		// if($request->ajax()) {
			// dd($request->all());
			$response = '';
	        try {
	            $client = new Client(); //GuzzleHttp\Client
	            $pos_data = [ 'MerchantID' => $request->MerchantID, 'SecurityToken' => $request->SecurityToken, 
	            'IMEI' => $request->IMEI, 'MerchantStorePosCode' => $request->MerchantStorePosCode, 'PlutusTransactionReferenceID' => $request->PlutusTransactionReferenceID ];

	            $res = $client->request('POST', 'https://www.plutuscloudserviceuat.in:8201/API/CloudBasedIntegration/V1/GetCloudBasedTxnStatus', [
	                'form_params' => $pos_data
	            ]);


	            $response = json_decode($res->getBody(), true);

	        } catch (\GuzzleHttp\Exception\ClientException $e) {
	            // echo Psr7\str($e->getRequest());
	            $response = Psr7\str($e->getResponse());
	            // dd($response);
	        }
	        // dd($response); 
	        return response()->json([ 'data' => $response ], 200);

		// }
	}

	public function CancelTransaction(Request $request)
	{
		// if($request->ajax()) {
			// dd($request->all());
			$response = '';
	        try {
	            $client = new Client(); //GuzzleHttp\Client
	            $pos_data = [ 'MerchantID' => $request->MerchantID, 'SecurityToken' => $request->SecurityToken, 
	            'IMEI' => $request->IMEI, 'MerchantStorePosCode' => $request->MerchantStorePosCode, 'PlutusTransactionReferenceID' => $request->PlutusTransactionReferenceID, 'Amount' => $request->Amount ];

	            $res = $client->request('POST', 'https://www.plutuscloudserviceuat.in:8201/API/CloudBasedIntegration/V1/CancelTransaction', [
	                'form_params' => $pos_data
	            ]);


	            $response = json_decode($res->getBody(), true);

	        } catch (\GuzzleHttp\Exception\ClientException $e) {
	            // echo Psr7\str($e->getRequest());
	            $response = Psr7\str($e->getResponse());
	            // dd($response);
	        }
	        // dd($response); 
	        return response()->json([ 'data' => $response ], 200);

		// }
	}


}
