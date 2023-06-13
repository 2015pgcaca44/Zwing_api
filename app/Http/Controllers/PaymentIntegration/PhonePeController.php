<?php
/**
 * @author: Shweta T
 * @Discription : PhonePe OFFLINE API for Request-Charge, Payement-Status  
 * Date: 18/06/2020
 */

namespace App\Http\Controllers\PaymentIntegration;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use App\PhonepeMerchant;
use App\PhonepeTransactions;
use Carbon\Carbon;

class PhonePeController extends Controller
{
    public function charge(Request $request)
	{
			$response = '';
	        try {
				$v_id = $request->v_id;
				if($PhonepeMerchant = PhonepeMerchant::where('v_id',$request->v_id)->first())
					$merchantId   = $PhonepeMerchant->merchant_id;
				else
					return response()->json(['status' => 'fail', 'message' => 'Phonepe Merchant not found.'], 200);
				
				$xProviderId = config('services.phonePe.xProviderId');
				$saltIndex = config('services.phonePe.saltIndex');
				$saltKey = config('services.phonePe.saltKey');
				$callback  =  env('API_URL') . '/payment-integration/callback'; 
				$transactionId =  'ZW'.Carbon::now()->timestamp; 
				$amount_in_rupee = $request->amount_in_rupee;
	            $client = new Client(); //GuzzleHttp\Client
				$pos_data = ['merchantId' => $merchantId, 
				'transactionId' => $transactionId, 
				'merchantOrderId' => $request->merchantOrderId, 
				'amount' => $request->amount, // In paisa 
				'instrumentType' => 'MOBILE',
				'instrumentReference' => $request->mobile,
				'message' => 'collect for zwing order',
				'email' => $request->email,
				'shortName' => $request->name,
				'expiresIn' => 180,
				'storeId' => $request->storeId,
				'terminalId' => $request->terminalId ];
				$request =  base64_encode(json_encode($pos_data));
				$data = [ 'request' => $request ];
				//X-VERIFY = SHA256(base64 encoded payload + "/v3/charge" + salt key) + ### + salt index
				$hash = hash("sha256", $request."/v3/charge".$saltKey);  
				$xVerify  = $hash.'###'.$saltIndex;
				$headers = ['Content-Type' => 'application/json',
							'X-VERIFY'     => $xVerify ,
							'X-CALLBACK-URL' => $callback,
							'X-PROVIDER-ID'  => $xProviderId ];
				$res = $client->request('POST', 'https://mercury-uat.phonepe.com/v3/charge', ['json' => $data, 'headers' => $headers] );
				$response = json_decode($res->getBody(), true); 
				//Save In Database to check status oncallback  
				$transactionsInfo 	= array('v_id' => $v_id,
								'store_id' => $pos_data['storeId'],
								'merchant_id' => $merchantId, 
								'transaction_id' => $response['data']['transactionId'], 
								'mobile' => $pos_data['instrumentReference'], 
								'amount' => $pos_data['amount'],
								'amount_in_rupee' => $amount_in_rupee, // In paisa
								'provider_reference_id' => $response['data']['providerReferenceId'],
								'remark' => $response['message'],
								'gateway_response' => json_encode($response),
								'api_type' => 'OFFLINE'
								);
				PhonepeTransactions::create($transactionsInfo);
				return response()->json(['status' => 'success', 'data' => $response], 200);
	         } catch (\GuzzleHttp\Exception\ClientException $e) {
				$response = json_decode((string) $e->getResponse()->getBody());
				return response()->json(['status' => 'fail', 'data' => $response ], 200);
	        } 
	}


	public function callback(Request $request)
	{
		//need to update payment status for transaction  
		$responseJson =  base64_decode($request->response); 
		$response = json_decode($responseJson); //dd($response->data);
        $merchantId = $response->data->merchantId; 
		$transactionId = $response->data->transactionId; 
		$paymentState = $response->data->paymentState;
        $remark = $response->message;
        mail('shwetajoshi27@gmail.com','Phonepe callback', "Hi.....<br>".$paymentState."--".$remark,'shweta.t@gsl.in');
		$code = $response->code; 
		$phonepeTransaction = PhonepeTransactions::where('transaction_id' , $transactionId)->where('merchant_id' , $merchantId)->first();
		$phonepeTransaction->update(['payment_state' => $paymentState ,'code' => $code ,'remark' => $remark, 'gateway_response' => $responseJson]);
		return response()->json(['status' => 'success', 'data' => $phonepeTransaction], 200);
	}

	public function getTransactionById(Request $request)
	{	
		if($PhonepeMerchant = PhonepeMerchant::where('v_id',$request->v_id)->first())
			$merchantId   = $PhonepeMerchant->merchant_id;
		else
			return response()->json(['status' => 'fail', 'message' => 'Phonepe Merchant not found.'], 200);

		$transactionId =  $request->transactionId ;  
		$phonepeTransaction = PhonepeTransactions::where('transaction_id' , $transactionId)->where('merchant_id' , $merchantId)->first();
		return response()->json(['status' => 'success', 'data' => $phonepeTransaction], 200);
	}


	public function status(Request $request)
	{	
		$response = '';
		try {
			
			if($PhonepeMerchant = PhonepeMerchant::where('v_id',$request->v_id)->first())
				$merchantId   = $PhonepeMerchant->merchant_id;
			else
				return response()->json(['status' => 'fail', 'message' => 'Phonepe Merchant not found.'], 200);
			$xProviderId = config('services.phonePe.xProviderId');
			$saltIndex = config('services.phonePe.saltIndex');
			$saltKey = config('services.phonePe.saltKey');
			$transactionId =  $request->transactionId ;  
			$client = new Client(); //GuzzleHttp\Client
			//X-VERIFY = SHA256("/v3/transaction/{merchantId}/{transactionId}/status" + saltKey) + "###" + saltIndex
			$hash = hash("sha256", "/v3/transaction/$merchantId/$transactionId/status".$saltKey); 
			$xVerify  = $hash.'###'.$saltIndex;//dd($xVerify);
			$url = "https://mercury-uat.phonepe.com/v3/transaction/$merchantId/$transactionId/status";
			$headers = ['Content-Type' => 'application/json',
						'X-VERIFY'     => $xVerify ,
						'X-PROVIDER-ID'  => $xProviderId ];
			$res = $client->request('GET', $url, ['headers' => $headers] );
			$response = json_decode($res->getBody(), true); //dd($response['data']);
			//Update Transaction in database
			if(!empty($response['data']))
			{
				$paymentState = $response['data']['paymentState'];
				$remark = $response['message'];
				$code = $response['code'];
				$phonepeTransaction = PhonepeTransactions::where('transaction_id' , $transactionId)->where('merchant_id' , $merchantId)->first();
				$phonepeTransaction->update(['payment_state' => $paymentState ,'code' => $code ,'remark' => $remark, 'gateway_response' => json_encode($response)]);
			}
			
			//To check callback base
			$callback = json_encode($response);
			$callback = base64_encode($callback);
			return response()->json(['status' => 'success', 'data' => $response, 'callback' =>$callback ], 200);
		 } catch (\GuzzleHttp\Exception\ClientException $e) {
			$response = Psr7\str($e->getResponse());
			return response()->json(['status' => 'fail','data' => $response], 200);
		} 
	}

	//Cancel Payment Request API
	public function cancel(Request $request)
	{	
		$response = '';
		try {
			
			if($PhonepeMerchant = PhonepeMerchant::where('v_id',$request->v_id)->first())
				$merchantId = $PhonepeMerchant->merchant_id;
			else
				return response()->json(['status' => 'fail', 'message' => 'Phonepe Merchant not found.'], 200);
			
			$xProviderId = config('services.phonePe.xProviderId');
			$saltIndex = config('services.phonePe.saltIndex');
			$saltKey = config('services.phonePe.saltKey');
			$transactionId =  $request->transactionId ;  
			$client = new Client(); //GuzzleHttp\Client
			//X-VERIFY = SHA256("/v3/charge/{merchantId}/{transactionId}/cancel" + salt key) + ### + salt index
			$hash = hash("sha256", "/v3/charge/$merchantId/$transactionId/cancel".$saltKey); 
			$xVerify  = $hash.'###'.$saltIndex;//dd($xVerify);
			$url = "https://mercury-uat.phonepe.com/v3/charge/$merchantId/$transactionId/cancel";
			$headers = ['Content-Type' => 'application/json',
						'X-VERIFY'     => $xVerify ,
						'X-PROVIDER-ID'  => $xProviderId ];
			$res = $client->request('POST', $url, ['headers' => $headers] );
			$response = json_decode($res->getBody(), true); 
			//Update Transaction in database
			if(!empty($response['code']))
			{
				$code = $response['code'];
				if($code== 'SUCCESS')
				{
					$paymentState = 'PAYMENT_CANCELED';
					$remark = $response['message'];
				}else{
					$paymentState = 'PAYMENT_CANCELED_FAILD';
					$remark = $response['message'];
				}

				$phonepeTransaction = PhonepeTransactions::where('transaction_id' , $transactionId)->where('merchant_id' , $merchantId)->first();
				$phonepeTransaction->update(['payment_state' => $paymentState ,'code' => $code ,'remark' => $remark, 'gateway_response' => json_encode($response)]);
			}
			return response()->json(['status' => 'success', 'data' => $response], 200);
		 } catch (\GuzzleHttp\Exception\ClientException $e) {
			$response = json_decode((string) $e->getResponse()->getBody());
			return response()->json(['status' => 'fail','data' => $response], 200);
		} 
	}

		//Remind Payment Request API : Used to send the reminder for payment request.
		public function remind(Request $request)
		{	
			$response = '';
			try {
				
				if($PhonepeMerchant = PhonepeMerchant::where('v_id',$request->v_id)->first())
					$merchantId = $PhonepeMerchant->merchant_id;
				else
					return response()->json(['status' => 'fail', 'message' => 'Phonepe Merchant not found.'], 200);
				
				$xProviderId = config('services.phonePe.xProviderId');
				$saltIndex = config('services.phonePe.saltIndex');
				$saltKey = config('services.phonePe.saltKey');
				$transactionId =  $request->transactionId ;  
				$client = new Client(); //GuzzleHttp\Client
				//X-VERIFY = SHA256("/v3/charge/{merchantId}/{transactionId}/remind" + salt key) + ### + salt index
				$hash = hash("sha256", "/v3/charge/$merchantId/$transactionId/remind".$saltKey); 
				$xVerify  = $hash.'###'.$saltIndex;//dd($xVerify);
				$url = "https://mercury-uat.phonepe.com/v3/charge/$merchantId/$transactionId/remind";
				$headers = ['Content-Type' => 'application/json',
							'X-VERIFY'     => $xVerify ,
							'X-PROVIDER-ID'  => $xProviderId ];
				$res = $client->request('POST', $url, ['headers' => $headers] );
				$response = json_decode($res->getBody(), true);
				return response()->json(['status' => 'success', 'data' => $response], 200);
			 } catch (\GuzzleHttp\Exception\ClientException $e) {
				$response = json_decode((string) $e->getResponse()->getBody());
				return response()->json(['status' => 'fail','data' => $response], 200);
			} 
		}

	//Refund Payment Request API
	public function refund(Request $request)
	{	
		$response = '';
		try {
			if($PhonepeMerchant = PhonepeMerchant::where('v_id',$request->v_id)->first())
				$merchantId = $PhonepeMerchant->merchant_id;
			else
				return response()->json(['status' => 'fail', 'message' => 'Phonepe Merchant not found--.'], 200);
			
			$xProviderId = config('services.phonePe.xProviderId');
			$saltIndex = config('services.phonePe.saltIndex');
			$saltKey = config('services.phonePe.saltKey');
			$transactionId =  $request->transactionId ;
			$amount = (float)$request->amount ;
	        $client = new Client(); //GuzzleHttp\Client
			$pos_data = ['merchantId' => $merchantId, 
						'transactionId' => $transactionId, 
						'providerReferenceId' => $request->providerReferenceId, 
						'amount'=>$amount,
						'merchantOrderId' => $request->merchantOrderId,  
						'message' => 'Refund for cancelled order on zwing'
						]; 
						//'subMerchant' => 'DemoMerchant'
			$request =  base64_encode(json_encode($pos_data));
			$data = [ 'request' => $request ];
			//X-VERIFY = SHA256(base64 encoded payload + "/v3/credit/backToSource" + salt key) + ### + salt index
			$hash = hash("sha256", $request."/v3/credit/backToSource".$saltKey); 
			$xVerify  = $hash.'###'.$saltIndex;
			$url = "https://mercury-uat.phonepe.com/v3/credit/backToSource";
			$callback  =  env('API_URL') . '/payment-integration/callback';
			$headers = ['Content-Type' => 'application/json',
						'X-VERIFY'     => $xVerify ,
						'X-CALLBACK-URL' => $callback,
						'X-PROVIDER-ID'  => $xProviderId ];
			$res = $client->request('POST', $url , ['json' => $data, 'headers' => $headers] );
			$response = json_decode($res->getBody(), true); 
			//Update Transaction in database
			if(!empty($response['code']))
			{
				$paymentState = $response['data']['payResponseCode'];
				$remark = $response['message'];
				$code = $response['code'];
				$phonepeTransaction = PhonepeTransactions::where('transaction_id' , $transactionId)->where('merchant_id' , $merchantId)->first();
				$phonepeTransaction->update(['payment_state' => $paymentState ,'code' => $code ,'remark' => $remark, 'gateway_response' => json_encode($response)]);
			}
			return response()->json(['status' => 'success', 'data' => $response], 200);
		 } catch (\GuzzleHttp\Exception\ClientException $e) {
			$response = json_decode((string) $e->getResponse()->getBody());
			return response()->json(['status' => 'fail','data' => $response], 200);
		} 
	}

	//cancel transaction on window close
	public function windowClose(Request $request) {
		$data = $this->getTransactionById($request);
		$transaction = $data->getData();
		if($transaction->data->code != 'PAYMENT_SUCCESS') {
			$cancel = $this->cancel($request);
			return response()->json(['status' => 'PENDING', 'data' => $cancel->getData()], 200);
		} else {
			return 'SUCCESS';
		}
		
	}

}
