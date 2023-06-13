<?php
/**
 * @author: Shweta Trivedi
 * @Discription : Amazon Yojaka Connectors To call API
 * Link : https://docs.beta.dub.yojaka.xp.sellers.a2z.com/docs/connectors/swagger/orders.html
 * Date: 11/08/2020
 */

namespace App\Http\Controllers\AmazonConnectors ;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
//use GuzzleHttp\Psr7\Request;
use App\PhonepeMerchant;
use App\PhonepeTransactions;
use Carbon\Carbon;
use \Aws\Signature\SignatureV4 ;
use \Aws\Exception\AwsException;
use \Aws\Credentials\Credentials;
use \Aws\Sts\StsClient;

class OrderController extends Controller
{
	//An API for Create order.
    public function createOrder(Request $request)
	{
		$response = '';
	     try {
				
				$locationId = $request->locationId;	
				$skuId = $request->skuId;	
				$numberOfUnits = 4 ;
				$xAmzAccessToken  = $request->xAmzAccessToken;
				$region = env('AWS_REGION') ;
				//Generating Primary Credentials
				$PrimaryCredentials = new Credentials(env('AWS_ACCESS_KEY_ID'), env('AWS_SECRET_ACCESS_KEY'));
				
				$stsClient = new StsClient( array(
	                'credentials' => $PrimaryCredentials ,
	                // 'profile' => 'default',
	                'region' => $region,
	                'version' => '2011-06-15',
	            ));

	            $stsResult = $stsClient->assumeRole([
	             	'RoleArn' => env('ROLE_ARN'), 
    				'RoleSessionName' => 'RoleSession1' ]);

	            //Generating Temp Credentials for Specified Role
				$tempCredentials = $stsClient->createCredentials($stsResult);

				$headers = array('X-Amz-Access-Token' => $xAmzAccessToken, 'Content-Type' => 'application/json' );
				
				$url = "https://api.sandbox.dub.yojaka.xp.sellers.a2z.com/v1/sandbox/orders";
				$data = '{
		   				"locationId": "'.$locationId.'",
					    "lineItems": [{
					        "sku": "'.$skuId.'",
					        "numberOfUnits": "'.$numberOfUnits.'"
					    }]
					}';

				$request = new \GuzzleHttp\Psr7\Request('POST', $url, $headers, $data);

				// Construct a request signer
				$signer = new SignatureV4("execute-api", $region);

				// Sign the request
				$request = $signer->signRequest($request, $tempCredentials);
			
				//Getting Headers After sing request
				$header = $request->getHeaders();
				// Send the request
				try {

					//Need to use Guzzle Temporary using curl Beacuse getting empty response
					$curl = curl_init();

					curl_setopt_array($curl, array(
					  CURLOPT_URL => $url,
					  CURLOPT_RETURNTRANSFER => true,
					  CURLOPT_ENCODING => "",
					  CURLOPT_MAXREDIRS => 10,
					  CURLOPT_TIMEOUT => 30,
					  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					  CURLOPT_CUSTOMREQUEST => "POST",
					  CURLOPT_POSTFIELDS => $data,
					  CURLOPT_HTTPHEADER => array(
					    "Authorization: ".$header['Authorization'][0],
					    "Content-Type: application/json",
					    "Host: api.sandbox.dub.yojaka.xp.sellers.a2z.com",
					    "X-Amz-Date: ".$header['X-Amz-Date'][0],
					    "X-Amz-Security-Token: ".$header['X-Amz-Security-Token'][0],
					    "cache-control: no-cache",
					    "x-amz-access-token: ".$xAmzAccessToken
					  ),
					));

					$response = curl_exec($curl);
					$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
					switch($httpcode)
					{
						case 200 : $message = 'Order Created' ; break;
						case 401 : $message = 'Unauthorized. You are not authorized to invoke this API for the given parameters.' ; break;
						case 400 : $message = 'Bad request. The response contains the error details.' ; break;
						case 404 : $message = 'The specified order id is not found.' ; break;
						case 500 : $message = 'Internal server error. Contact Amazon support for assistance.';
						case 503 : $message = 'Service unavailable. Retry the request after some time.';
						default :  $message = 'Something went wrong.' ; 
					} 
					$err = curl_error($curl);
					curl_close($curl);
					if ($err) {
					  //echo "cURL Error #:" . $err;
					  return response()->json(['status' => 'faile', 'data' => $err ], 200);
					}else{
						$response = json_decode($response, true); 
						return response()->json(['status' => 'success', 'data' => $response, 'message' => $message ], 200);
					}
				}
				catch (Exception $exception) {
				    $responseBody = $exception->getResponse()->getBody(true);
				    return response()->json(['status' => 'faile', 'data' => $responseBody], 200);
				}
				
	         } catch (\GuzzleHttp\Exception\ClientException $e) {
				$response = json_decode((string) $e->getResponse()->getBody());
				//echo "<h1> --response error ---<h1>";
				return response()->json(['status' => 'faile', 'data' => $response ], 200);
			} 
			
	}

	//This API to retrieve complete details about a single order, given the order's id.
    public function getOrder(Request $request)
	{
		$response = '';
	     try {
				
				$locationId = $request->locationId ;	
				$orderId = $request->orderId ; 
				$xAmzAccessToken  = $request->xAmzAccessToken;
				$region = env('AWS_REGION') ;
				//Generating Primary Credentials
				$PrimaryCredentials = new Credentials(env('AWS_ACCESS_KEY_ID'), env('AWS_SECRET_ACCESS_KEY'));
				
				$stsClient = new StsClient( array(
	                'credentials' => $PrimaryCredentials ,
	                // 'profile' => 'default',
	                'region' => $region,
	                'version' => '2011-06-15',
	            ));

	            $stsResult = $stsClient->assumeRole([
	             	'RoleArn' => env('ROLE_ARN'), 
    				'RoleSessionName' => 'RoleSession1' ]);

	            //Generating Temp Credentials for Specified Role
				$tempCredentials = $stsClient->createCredentials($stsResult);

				$headers = array('X-Amz-Access-Token' => $xAmzAccessToken, 'Content-Type' => 'application/json' );

				$url ="https://api.sandbox.dub.yojaka.xp.sellers.a2z.com/v1/orders/$orderId";
					
				$request = new \GuzzleHttp\Psr7\Request('GET', $url, $headers);
	
				// Construct a request signer
				$signer = new SignatureV4("execute-api", $region);

				// Sign the request
				$request = $signer->signRequest($request, $tempCredentials);
			
				//Getting Headers After sing request
				$header = $request->getHeaders();

				// Send the request
				try {

					//Need to use Guzzle Temporary using curl Beacuse getting empty response
					$curl = curl_init();

					curl_setopt_array($curl, array(
					  CURLOPT_URL => $url,
					  CURLOPT_RETURNTRANSFER => true,
					  CURLOPT_ENCODING => "",
					  CURLOPT_MAXREDIRS => 10,
					  CURLOPT_TIMEOUT => 30,
					  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					  CURLOPT_CUSTOMREQUEST => "GET",
					  CURLOPT_POSTFIELDS => "",
					  CURLOPT_HTTPHEADER => array(
					    "Authorization: ".$header['Authorization'][0],
					    "Content-Type: application/json",
					    "Host: api.sandbox.dub.yojaka.xp.sellers.a2z.com",
					    "X-Amz-Date: ".$header['X-Amz-Date'][0],
					    "X-Amz-Security-Token: ".$header['X-Amz-Security-Token'][0],
					    "cache-control: no-cache",
					    "x-amz-access-token: ".$xAmzAccessToken
					  ),
					));

					$response = curl_exec($curl);
					$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
					switch($httpcode)
					{
						case 200 : $message = 'Order Details' ; break;
						case 401 : $message = 'Unauthorized. You are not authorized to invoke this API for the given parameters.' ; break;
						case 400 : $message = 'Bad request. The response contains the error details.' ; break;
						case 404 : $message = 'The specified order id is not found.' ; break;
						case 500 : $message = 'Internal server error. Contact Amazon support for assistance.';
						case 503 : $message = 'Service unavailable. Retry the request after some time.';
						default :  $message = 'Something went wrong.' ; 
					} 
					$err = curl_error($curl);

					curl_close($curl);

					if ($err) {
					  echo "cURL Error #:" . $err;
					}else{
						$response = json_decode($response, true); 
						return response()->json(['status' => 'success', 'data' => $response, 'message' => $message ], 200);
					}
				}
				catch (Exception $exception) {
				    $responseBody = $exception->getResponse()->getBody(true);
				    return response()->json(['status' => 'faile', 'data' => $responseBody], 200);
				}
				
	         } catch (\GuzzleHttp\Exception\ClientException $e) {
				$response = json_decode((string) $e->getResponse()->getBody());
				return response()->json(['status' => 'faile', 'data' => $response ], 200);
			} 
	}


	//Returns a list of active/open orders
    public function listOrder(Request $request)
	{
		$response = '';
	     try {
				
				$locationId = $request->locationId ;	
				$status = $request->status ; 
				$fromTimestamp   = $request->fromTimestamp ; 
				$cursor = $request->cursor ; 
				$maxResults = $request->maxResults ; 
				$toTimestamp = $request->toTimestamp ; 
				$xAmzAccessToken  = $request->xAmzAccessToken;
				$region = env('AWS_REGION') ;

				//Generating Primary Credentials
				$PrimaryCredentials = new Credentials(env('AWS_ACCESS_KEY_ID'), env('AWS_SECRET_ACCESS_KEY'));
				
				$stsClient = new StsClient( array(
	                'credentials' => $PrimaryCredentials ,
	                // 'profile' => 'default',
	                'region' => $region,
	                'version' => '2011-06-15',
	            ));

	            $stsResult = $stsClient->assumeRole([
	             	'RoleArn' => env('ROLE_ARN'), 
    				'RoleSessionName' => 'RoleSession1' ]);

	            //Generating Temp Credentials for Specified Role
				$tempCredentials = $stsClient->createCredentials($stsResult);

				$headers = array('X-Amz-Access-Token' => $xAmzAccessToken, 'Content-Type' => 'application/json' );
			
				$url ="https://api.sandbox.dub.yojaka.xp.sellers.a2z.com/v1/orders?locationId=$locationId&status=$status&maxResults=$maxResults&fromTimestamp=$fromTimestamp&cursor=$cursor&toTimestamp=$toTimestamp";
					
				$request = new \GuzzleHttp\Psr7\Request('GET', $url, $headers);
	
				// Construct a request signer
				$signer = new SignatureV4("execute-api", $region);

				// Sign the request
				$request = $signer->signRequest($request, $tempCredentials);
			
				//Getting Headers After sing request
				$header = $request->getHeaders();

				// Send the request
				try {

					//Need to use Guzzle Temporary using curl Beacuse getting empty response
					$curl = curl_init();

					curl_setopt_array($curl, array(
					  CURLOPT_URL => $url,
					  CURLOPT_RETURNTRANSFER => true,
					  CURLOPT_ENCODING => "",
					  CURLOPT_MAXREDIRS => 10,
					  CURLOPT_TIMEOUT => 30,
					  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					  CURLOPT_CUSTOMREQUEST => "GET",
					  CURLOPT_POSTFIELDS => "",
					  CURLOPT_HTTPHEADER => array(
					    "Authorization: ".$header['Authorization'][0],
					    "Content-Type: application/json",
					    "Host: api.sandbox.dub.yojaka.xp.sellers.a2z.com",
					    "X-Amz-Date: ".$header['X-Amz-Date'][0],
					    "X-Amz-Security-Token: ".$header['X-Amz-Security-Token'][0],
					    "cache-control: no-cache",
					    "x-amz-access-token: ".$xAmzAccessToken
					  ),
					));

					$response = curl_exec($curl);
					$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
					switch($httpcode)
					{
						case 200 : $message = 'Order List' ; break;
						case 401 : $message = 'Unauthorized. You are not authorized to invoke this API for the given parameters.' ; break;
						case 400 : $message = 'Bad request. The response contains the error details.' ; break;
						case 500 : $message = 'Internal server error. Contact Amazon support for assistance.';
						case 503 : $message = 'Service unavailable. Retry the request after some time.';
						default :  $message = 'Something went wrong.' ;
					} 
					$err = curl_error($curl);

					curl_close($curl);

					if ($err) {
					  echo "cURL Error #:" . $err;
					}else{
						$response = json_decode($response, true); 
						return response()->json(['status' => 'success', 'data' => $response, 'message' => $message ], 200);
					}
				}
				 catch (Exception $exception) {
				    $responseBody = $exception->getResponse()->getBody(true);
				    return response()->json(['status' => 'faile', 'data' => $responseBody], 200);
				}
				
	         } catch (\GuzzleHttp\Exception\ClientException $e) {
				$response = json_decode((string) $e->getResponse()->getBody());
				return response()->json(['status' => 'faile', 'data' => $response ], 200);
			} 
	}

	

	//confirmed the order for fulfilment processing.
    public function confirmOrder(Request $request)
	{
		$response = '';
	     try {
				
				$locationId = $request->locationId ;	
				$orderId = $request->orderId ;
				$xAmzAccessToken  = $request->xAmzAccessToken;
				$region = env('AWS_REGION') ;

				//Generating Primary Credentials
				$PrimaryCredentials = new Credentials(env('AWS_ACCESS_KEY_ID'), env('AWS_SECRET_ACCESS_KEY'));
				
				$stsClient = new StsClient( array(
	                'credentials' => $PrimaryCredentials ,
	                // 'profile' => 'default',
	                'region' => $region,
	                'version' => '2011-06-15',
	            ));

	            $stsResult = $stsClient->assumeRole([
	             	'RoleArn' => env('ROLE_ARN'), 
    				'RoleSessionName' => 'RoleSession1' ]);

	            //Generating Temp Credentials for Specified Role
				$tempCredentials = $stsClient->createCredentials($stsResult);

				$headers = array('X-Amz-Access-Token' => $xAmzAccessToken, 'Content-Type' => 'application/json' );

				$url = "https://api.sandbox.dub.yojaka.xp.sellers.a2z.com/v1/orders/$orderId/confirm-order";
					
				$request = new \GuzzleHttp\Psr7\Request('PUT', $url, $headers);
	
				// Construct a request signer
				$signer = new SignatureV4("execute-api", $region);

				// Sign the request
				$request = $signer->signRequest($request, $tempCredentials);
			
				//Getting Headers After sing request
				$header = $request->getHeaders();

				// Send the request
				try {

					//Need to use Guzzle Temporary using curl Beacuse getting empty response
					$curl = curl_init();

					curl_setopt_array($curl, array(
					  CURLOPT_URL => $url,
					  CURLOPT_RETURNTRANSFER => true,
					  CURLOPT_ENCODING => "",
					  CURLOPT_MAXREDIRS => 10,
					  CURLOPT_TIMEOUT => 30,
					  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					  CURLOPT_CUSTOMREQUEST => "PUT",
					  CURLOPT_POSTFIELDS => "",
					  CURLOPT_HTTPHEADER => array(
					    "Authorization: ".$header['Authorization'][0],
					    "Content-Type: application/json",
					    "Host: api.sandbox.dub.yojaka.xp.sellers.a2z.com",
					    "X-Amz-Date: ".$header['X-Amz-Date'][0],
					    "X-Amz-Security-Token: ".$header['X-Amz-Security-Token'][0],
					    "cache-control: no-cache",
					    "x-amz-access-token: ".$xAmzAccessToken
					  ),
					));

					$response = curl_exec($curl); 
					$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
					
					switch($httpcode)
					{
						case 200 : $message = 'Order Confirmed' ; break;
						case 204 : $message = 'Order Confirmed' ; break;
						case 401 : $message = 'Unauthorized. You are not authorized to invoke this API for the given parameters.' ; break;
						case 400 : $message = 'Bad request. The response contains the error details.' ; break;
						case 500 : $message = 'Internal server error. Contact Amazon support for assistance.';
						case 503 : $message = 'Service unavailable. Retry the request after some time.';
						default :  $message = 'Something went wrong.' ;
					} 
					
					$err = curl_error($curl);
					curl_close($curl);
					if ($err) {
					  //echo "cURL Error #:" . $err;
					  return response()->json(['status' => 'faile', 'data' => $err ], 200);
					}else{
						$response = json_decode($response, true); 
						return response()->json(['status' => 'success', 'data' => $response, 'message' => $message ], 200);
					}
				}
				catch (Exception $exception) {
				    $responseBody = $exception->getResponse()->getBody(true);
				    return response()->json(['status' => 'faile', 'data' => $responseBody], 200);
				}
				
	         } catch (\GuzzleHttp\Exception\ClientException $e) {
				$response = json_decode((string) $e->getResponse()->getBody());
				//echo "<h1> --response error ---<h1>";
				return response()->json(['status' => 'faile', 'data' => $response ], 200);
			} 
	}


	//An API for a client to provide the details of the packages that will be used to ship an order.
    public function createPackages(Request $request)
	{
		$response = '';
	     try {
				
	     	    $data = $request->getContent(); //dd($content); die;
				$locationId = $request->locationId ;	
				$orderId = $request->orderId ; 	
				$xAmzAccessToken  = $request->xAmzAccessToken;
				$region = env('AWS_REGION') ;
				//Generating Primary Credentials
				$PrimaryCredentials = new Credentials(env('AWS_ACCESS_KEY_ID'), env('AWS_SECRET_ACCESS_KEY'));
				
				$stsClient = new StsClient( array(
	                'credentials' => $PrimaryCredentials ,
	                'region' => $region,
	                'version' => '2011-06-15',
	            ));

	            $stsResult = $stsClient->assumeRole([
	             	'RoleArn' => env('ROLE_ARN'), 
    				'RoleSessionName' => 'RoleSession1' ]);

	            //Generating Temp Credentials for Specified Role
				$tempCredentials = $stsClient->createCredentials($stsResult);

				$headers = array('X-Amz-Access-Token' => $xAmzAccessToken, 'Content-Type' => 'application/json' );

				$url = "https://api.sandbox.dub.yojaka.xp.sellers.a2z.com/v1/orders/$orderId/create-packages";
			    
/*				$data =  '{
"packages": 
  [
    {
    "id": "001",
     "length": 
      {"value": 3.14159,
       "dimensionUnit": "CM"},
     "width": 
      {"value": 3.14159,
       "dimensionUnit": "CM"},
     "height": 
      {"value": 3.14159,
       "dimensionUnit": "CM"},
     "weight": 
      {"value": 1200.14159,
       "weightUnit": "grams"},
     "packagedLineItems": 
      [
      	{
      	"lineItem":
        {
           "id": "0"
        },
          "quantity": 2
      	}
      ]
    }
]
}'; */	
				$request = new \GuzzleHttp\Psr7\Request('PUT', $url, $headers, $data);
				
				// Construct a request signer
				$signer = new SignatureV4("execute-api", $region);

				// Sign the request
				$request = $signer->signRequest($request, $tempCredentials);
			
				//Getting Headers After sing request
				$header = $request->getHeaders();


				// Send the request
				try {

					//Need to use Guzzle Temporary using curl Beacuse getting empty response
					$curl = curl_init();

					curl_setopt_array($curl, array(
					  CURLOPT_URL => $url,
					  CURLOPT_RETURNTRANSFER => true,
					  CURLOPT_ENCODING => "",
					  CURLOPT_MAXREDIRS => 10,
					  CURLOPT_TIMEOUT => 30,
					  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					  CURLOPT_CUSTOMREQUEST => "PUT",
					  CURLOPT_POSTFIELDS => $data,
					  CURLOPT_HTTPHEADER => array(
					    "Authorization: ".$header['Authorization'][0],
					    "Content-Type: application/json",
					    "Host: api.sandbox.dub.yojaka.xp.sellers.a2z.com",
					    "X-Amz-Date: ".$header['X-Amz-Date'][0],
					    "X-Amz-Security-Token: ".$header['X-Amz-Security-Token'][0],
					    "cache-control: no-cache",
					    "x-amz-access-token: ".$xAmzAccessToken
					  ),
					));

					$response = curl_exec($curl);
					$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
					switch($httpcode)
					{
						case 204 : $message = 'Package Created' ; break;
						case 401 : $message = 'Unauthorized. You are not authorized to invoke this API for the given parameters.' ; break;
						case 400 : $message = 'Bad request. The response contains the error details.' ; break;
						case 404 : $message = 'The specified order id is not found.' ; break;
						case 422 : $message = 'Unprocessable Entity. The specified order has been cancelled by the marketplace.' ; break;
						case 500 : $message = 'Internal server error. Contact Amazon support for assistance.';
						case 503 : $message = 'Service unavailable. Retry the request after some time.';
						default :  $message = 'Something went wrong.' ;
					} 
					$err = curl_error($curl);
					curl_close($curl);
					if ($err) {
					  return response()->json(['status' => 'faile', 'data' => $err ], 200);
					}else{
						$response = json_decode($response, true); 
						return response()->json(['status' => 'success', 'data' => $response, 'message' => $message ], 200);
					}
				}catch (Exception $exception) {
				    $responseBody = $exception->getResponse()->getBody(true);
				    return response()->json(['status' => 'faile', 'data' => $responseBody], 200);
				}
				
	         } catch (\GuzzleHttp\Exception\ClientException $e) {
				$response = json_decode((string) $e->getResponse()->getBody());
				//echo "<h1> --response error ---<h1>";
				return response()->json(['status' => 'faile', 'data' => $response ], 200);
			} 
	}



	//An API for a client to retrieve an optional list of time-slots that marketplace/channel provides for the pickup of the packages of an order. 
	public function retrievePickupSlot(Request $request)
	{
		$response = '';
	     try {
				
				$locationId = $request->locationId;	
				$orderId = $request->orderId ; 	
				$xAmzAccessToken  = $request->xAmzAccessToken;
				$region = env('AWS_REGION') ;
				//Generating Primary Credentials
				$PrimaryCredentials = new Credentials(env('AWS_ACCESS_KEY_ID'), env('AWS_SECRET_ACCESS_KEY'));
				
				$stsClient = new StsClient( array(
	                'credentials' => $PrimaryCredentials ,
	                // 'profile' => 'default',
	                'region' => $region,
	                'version' => '2011-06-15',
	            ));

	            $stsResult = $stsClient->assumeRole([
	             	'RoleArn' => env('ROLE_ARN'), 
    				'RoleSessionName' => 'RoleSession1' ]);

	            //Generating Temp Credentials for Specified Role
				$tempCredentials = $stsClient->createCredentials($stsResult);

				$headers = array('X-Amz-Access-Token' => $xAmzAccessToken, 'Content-Type' => 'application/json' );
				
				$url = "https://api.sandbox.dub.yojaka.xp.sellers.a2z.com/v1/orders/$orderId/retrieve-pickup-slots";
				
				$request = new \GuzzleHttp\Psr7\Request('PUT', $url, $headers);

				// Construct a request signer
				$signer = new SignatureV4("execute-api", $region);

				// Sign the request
				$request = $signer->signRequest($request, $tempCredentials);
			
				//Getting Headers After sing request
				$header = $request->getHeaders();
				//print_r($header);
				// Send the request
				try {

					//Need to use Guzzle Temporary using curl Beacuse getting empty response
					$curl = curl_init();


					curl_setopt_array($curl, array(
					  CURLOPT_URL => $url,
					  CURLOPT_RETURNTRANSFER => true,
					  CURLOPT_ENCODING => "",
					  CURLOPT_MAXREDIRS => 10,
					  CURLOPT_TIMEOUT => 30,
					  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					  CURLOPT_CUSTOMREQUEST => "PUT",
					  CURLOPT_POSTFIELDS => '',
					  CURLOPT_HTTPHEADER => array(
					    "Authorization: ".$header['Authorization'][0],
					    "Content-Type: application/json",
					    "Host: api.sandbox.dub.yojaka.xp.sellers.a2z.com",
					    "X-Amz-Date: ".$header['X-Amz-Date'][0],
					    "X-Amz-Security-Token: ".$header['X-Amz-Security-Token'][0],
					    "cache-control: no-cache",
					    "x-amz-access-token: ".$xAmzAccessToken
					  ),
					));

					$response = curl_exec($curl);
					$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
					switch($httpcode)
					{
						case 200 : $message = 'Pickup slot retrieved' ; break;
						case 204 : $message = 'Package re' ; break;
						case 401 : $message = 'Unauthorized. You are not authorized to invoke this API for the given parameters.' ; break;
						case 400 : $message = 'Bad request. The response contains the error details.' ; break;
						case 404 : $message = 'The specified order id is not found.' ; break;
						case 422 : $message = 'Unprocessable Entity. The specified order has been cancelled by the marketplace.' ; break;
						case 500 : $message = 'Internal server error. Contact Amazon support for assistance.';
						case 503 : $message = 'Service unavailable. Retry the request after some time.';
						default :  $message = 'Something went wrong.' ; 
					} 
					$err = curl_error($curl);
					curl_close($curl);
					if ($err) {
					  //echo "cURL Error #:" . $err;
					  return response()->json(['status' => 'faile', 'data' => $err ], 200);
					}else{
						$response = json_decode($response, true); 
						return response()->json(['status' => 'success', 'data' => $response, 'message' => $message ], 200);
					}
				}
				catch (Exception $exception) {
				   $responseBody = $exception->getResponse()->getBody(true);
				   return response()->json(['status' => 'faile', 'data' => $responseBody], 200);
				}
				
	         } catch (\GuzzleHttp\Exception\ClientException $e) {
				$response = json_decode((string) $e->getResponse()->getBody());
				//echo "<h1> --response error ---<h1>";
				return response()->json(['status' => 'faile', 'data' => $response ], 200);
			} 
			
	}
	//API indicating that the invoice is to be generated and retrieved for a given order.
	public function generateInvoice(Request $request)
	{
		$response = '';
	     try {
				
				$locationId = $request->locationId;	
				$orderId = $request->orderId ; 	
				$xAmzAccessToken  = $request->xAmzAccessToken;
				$region = env('AWS_REGION') ;
				//Generating Primary Credentials
				$PrimaryCredentials = new Credentials(env('AWS_ACCESS_KEY_ID'), env('AWS_SECRET_ACCESS_KEY'));
				
				$stsClient = new StsClient( array(
	                'credentials' => $PrimaryCredentials ,
	                // 'profile' => 'default',
	                'region' => $region,
	                'version' => '2011-06-15',
	            ));

	            $stsResult = $stsClient->assumeRole([
	             	'RoleArn' => env('ROLE_ARN'), 
    				'RoleSessionName' => 'RoleSession1' ]);

	            //Generating Temp Credentials for Specified Role
				$tempCredentials = $stsClient->createCredentials($stsResult);

				$headers = array('X-Amz-Access-Token' => $xAmzAccessToken, 'Content-Type' => 'application/json' );
				
				$url = "https://api.sandbox.dub.yojaka.xp.sellers.a2z.com/v1/orders/$orderId/generate-invoice";
				$request = new \GuzzleHttp\Psr7\Request('PUT', $url, $headers);

				// Construct a request signer
				$signer = new SignatureV4("execute-api", $region);

				// Sign the request
				$request = $signer->signRequest($request, $tempCredentials);
			
				//Getting Headers After sing request
				$header = $request->getHeaders();
				// Send the request
				try {

					//Need to use Guzzle Temporary using curl Beacuse getting empty response
					$curl = curl_init();

					curl_setopt_array($curl, array(
					  CURLOPT_URL => $url,
					  CURLOPT_RETURNTRANSFER => true,
					  CURLOPT_ENCODING => "",
					  CURLOPT_MAXREDIRS => 10,
					  CURLOPT_TIMEOUT => 30,
					  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					  CURLOPT_CUSTOMREQUEST => "PUT",
					  CURLOPT_POSTFIELDS => '',
					  CURLOPT_HTTPHEADER => array(
					    "Authorization: ".$header['Authorization'][0],
					    "Content-Type: application/json",
					    "Host: api.sandbox.dub.yojaka.xp.sellers.a2z.com",
					    "X-Amz-Date: ".$header['X-Amz-Date'][0],
					    "X-Amz-Security-Token: ".$header['X-Amz-Security-Token'][0],
					    "cache-control: no-cache",
					    "x-amz-access-token: ".$xAmzAccessToken
					  ),
					));

					$response = curl_exec($curl);
					$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
					switch($httpcode)
					{
						case 200 : $message = 'Invoice is generated and retrieved for a given order' ; break;
						case 204 : $message = 'Invoice generated' ; break;
						case 401 : $message = 'Unauthorized. You are not authorized to invoke this API for the given parameters.' ; break;
						case 400 : $message = 'Bad request. The response contains the error details.' ; break;
						case 404 : $message = 'The specified order id is not found.' ; break;
						case 422 : $message = 'Unprocessable Entity. The specified order has been cancelled by the marketplace.' ; break;
						case 500 : $message = 'Internal server error. Contact Amazon support for assistance.';
						case 503 : $message = 'Service unavailable. Retry the request after some time.';
						default :  $message = 'Something went wrong.' ; 
					} 
					$err = curl_error($curl);
					curl_close($curl);
					if ($err) {
					  //echo "cURL Error #:" . $err;
					  return response()->json(['status' => 'faile', 'data' => $err ], 200);
					}else{
						$response = json_decode($response, true); 
						return response()->json(['status' => 'success', 'data' => $response, 'message' => $message ], 200);
					}
				}
				catch (Exception $exception) {
				   $responseBody = $exception->getResponse()->getBody(true);
				   return response()->json(['status' => 'faile', 'data' => $responseBody], 200);
				}
				
	         } catch (\GuzzleHttp\Exception\ClientException $e) {
				$response = json_decode((string) $e->getResponse()->getBody());
				//echo "<h1> --response error ---<h1>";
				return response()->json(['status' => 'faile', 'data' => $response ], 200);
			} 
			
	}

	//API to retrieve the specified order's invoice. This API will return the invoice only if the GenerateInvoice step of the order processing workflow has been completed.
	public function retrieveInvoice(Request $request)
	{
		$response = '';
	     try {
				
				$locationId = $request->locationId;	
				$orderId = $request->orderId ; 	
				$xAmzAccessToken  = $request->xAmzAccessToken;
				$region = env('AWS_REGION') ;
				//Generating Primary Credentials
				$PrimaryCredentials = new Credentials(env('AWS_ACCESS_KEY_ID'), env('AWS_SECRET_ACCESS_KEY'));
				
				$stsClient = new StsClient( array(
	                'credentials' => $PrimaryCredentials ,
	                // 'profile' => 'default',
	                'region' => $region,
	                'version' => '2011-06-15',
	            ));

	            $stsResult = $stsClient->assumeRole([
	             	'RoleArn' => env('ROLE_ARN'), 
    				'RoleSessionName' => 'RoleSession1' ]);

	            //Generating Temp Credentials for Specified Role
				$tempCredentials = $stsClient->createCredentials($stsResult);

				$headers = array('X-Amz-Access-Token' => $xAmzAccessToken, 'Content-Type' => 'application/json' );
				
				$url =  "https://api.sandbox.dub.yojaka.xp.sellers.a2z.com/v1/orders/$orderId/invoice" ;
				$request = new \GuzzleHttp\Psr7\Request('GET', $url, $headers);

				// Construct a request signer
				$signer = new SignatureV4("execute-api", $region);

				// Sign the request
				$request = $signer->signRequest($request, $tempCredentials);
			
				//Getting Headers After sing request
				$header = $request->getHeaders();
				// Send the request
				try {

					//Need to use Guzzle Temporary using curl Beacuse getting empty response
					$curl = curl_init();

					curl_setopt_array($curl, array(
					  CURLOPT_URL => $url,
					  CURLOPT_RETURNTRANSFER => true,
					  CURLOPT_ENCODING => "",
					  CURLOPT_MAXREDIRS => 10,
					  CURLOPT_TIMEOUT => 30,
					  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					  CURLOPT_CUSTOMREQUEST => "GET",
					  CURLOPT_POSTFIELDS => '',
					  CURLOPT_HTTPHEADER => array(
					    "Authorization: ".$header['Authorization'][0],
					    "Content-Type: application/json",
					    "Host: api.sandbox.dub.yojaka.xp.sellers.a2z.com",
					    "X-Amz-Date: ".$header['X-Amz-Date'][0],
					    "X-Amz-Security-Token: ".$header['X-Amz-Security-Token'][0],
					    "cache-control: no-cache",
					    "x-amz-access-token: ".$xAmzAccessToken
					  ),
					));

					$response = curl_exec($curl);
					$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
					switch($httpcode)
					{
						case 200 : $message = 'The invoice as a PDF' ; break;
						case 204 : $message = 'Invoice generated' ; break;
						case 401 : $message = 'Unauthorized. You are not authorized to invoke this API for the given parameters.' ; break;
						case 400 : $message = 'Bad request. The response contains the error details.' ; break;
						case 404 : $message = 'The specified order id is not found.' ; break;
						case 422 : $message = 'Unprocessable Entity. The specified order has been cancelled by the marketplace.' ; break;
						case 500 : $message = 'Internal server error. Contact Amazon support for assistance.';
						case 503 : $message = 'Service unavailable. Retry the request after some time.';
						default :  $message = 'Something went wrong.' ; 
					} 
					$err = curl_error($curl);
					curl_close($curl);
					if ($err) {
					  //echo "cURL Error #:" . $err;
					  return response()->json(['status' => 'faile', 'data' => $err ], 200);
					}else{
						$response = json_decode($response, true); 
						return response()->json(['status' => 'success', 'data' => $response, 'message' => $message ], 200);
					}
				}
				catch (Exception $exception) {
				   $responseBody = $exception->getResponse()->getBody(true);
				   return response()->json(['status' => 'faile', 'data' => $responseBody], 200);
				}
				
	         } catch (\GuzzleHttp\Exception\ClientException $e) {
				$response = json_decode((string) $e->getResponse()->getBody());
				//echo "<h1> --response error ---<h1>";
				return response()->json(['status' => 'faile', 'data' => $response ], 200);
			} 
			
	}

	//Generate ship-label for order 
	public function generateShipLabel(Request $request)
	{
		$response = '';
	     try {
				
				$locationId = $request->locationId;	
				$orderId = $request->orderId ;
				$packageId= $request->packageId ;  	
				$pickupTimeSlotId= $request->pickupTimeSlotId ; 
				$xAmzAccessToken  = $request->xAmzAccessToken;
				$region = env('AWS_REGION') ;
				//Generating Primary Credentials
				$PrimaryCredentials = new Credentials(env('AWS_ACCESS_KEY_ID'), env('AWS_SECRET_ACCESS_KEY'));
				
				$stsClient = new StsClient( array(
	                'credentials' => $PrimaryCredentials ,
	                // 'profile' => 'default',
	                'region' => $region,
	                'version' => '2011-06-15',
	            ));

	            $stsResult = $stsClient->assumeRole([
	             	'RoleArn' => env('ROLE_ARN'), 
    				'RoleSessionName' => 'RoleSession1' ]);

	            //Generating Temp Credentials for Specified Role
				$tempCredentials = $stsClient->createCredentials($stsResult);

				$headers = array('X-Amz-Access-Token' => $xAmzAccessToken, 'Content-Type' => 'application/json' );
				
				$url = "https://api.sandbox.dub.yojaka.xp.sellers.a2z.com/v1/orders/$orderId/generate-ship-label?packageId=$packageId&pickupTimeSlotId=$pickupTimeSlotId" ;
				$request = new \GuzzleHttp\Psr7\Request('PUT', $url, $headers);

				// Construct a request signer
				$signer = new SignatureV4("execute-api", $region);

				// Sign the request
				$request = $signer->signRequest($request, $tempCredentials);
			
				//Getting Headers After sing request
				$header = $request->getHeaders();
				// Send the request
				try {

					//Need to use Guzzle Temporary using curl Beacuse getting empty response
					$curl = curl_init();
					curl_setopt_array($curl, array(
					  CURLOPT_URL => $url,
					  CURLOPT_RETURNTRANSFER => true,
					  CURLOPT_ENCODING => "",
					  CURLOPT_MAXREDIRS => 10,
					  CURLOPT_TIMEOUT => 30,
					  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					  CURLOPT_CUSTOMREQUEST => "PUT",
					  CURLOPT_POSTFIELDS => '',
					  CURLOPT_HTTPHEADER => array(
					    "Authorization: ".$header['Authorization'][0],
					    "Content-Type: application/json",
					    "Host: api.sandbox.dub.yojaka.xp.sellers.a2z.com",
					    "X-Amz-Date: ".$header['X-Amz-Date'][0],
					    "X-Amz-Security-Token: ".$header['X-Amz-Security-Token'][0],
					    "cache-control: no-cache",
					    "x-amz-access-token: ".$xAmzAccessToken
					  ),
					));

					$response = curl_exec($curl);
					$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
					switch($httpcode)
					{
						case 200 : $message = 'Invoice is generated and retrieved for a given order' ; break;
						case 204 : $message = 'Invoice generated' ; break;
						case 401 : $message = 'Unauthorized. You are not authorized to invoke this API for the given parameters.' ; break;
						case 400 : $message = 'Bad request. The response contains the error details.' ; break;
						case 404 : $message = 'The specified order id is not found.' ; break;
						case 422 : $message = 'Unprocessable Entity. The specified order has been cancelled by the marketplace.' ; break;
						case 500 : $message = 'Internal server error. Contact Amazon support for assistance.';
						case 503 : $message = 'Service unavailable. Retry the request after some time.';
						default :  $message = 'Something went wrong.' ; 
					} 
					$err = curl_error($curl);
					curl_close($curl);
					if ($err) {
					  //echo "cURL Error #:" . $err;
					  return response()->json(['status' => 'faile', 'data' => $err ], 200);
					}else{
						$response = json_decode($response, true); 
						return response()->json(['status' => 'success', 'data' => $response, 'message' => $message ], 200);
					}
				}
				catch (Exception $exception) {
				   $responseBody = $exception->getResponse()->getBody(true);
				   return response()->json(['status' => 'faile', 'data' => $responseBody], 200);
				}
				
	         } catch (\GuzzleHttp\Exception\ClientException $e) {
				$response = json_decode((string) $e->getResponse()->getBody());
				//echo "<h1> --response error ---<h1>";
				return response()->json(['status' => 'faile', 'data' => $response ], 200);
			} 
			
	}

	//An API to indicate to Amazon Yojaka that a client has shipped an order.
	public function shipOrder(Request $request)
	{
		$response = '';
	     try {
				
				$locationId = $request->locationId;	
				$orderId = $request->orderId ;
				$xAmzAccessToken  = $request->xAmzAccessToken;
				$region = env('AWS_REGION') ;
				//Generating Primary Credentials
				$PrimaryCredentials = new Credentials(env('AWS_ACCESS_KEY_ID'), env('AWS_SECRET_ACCESS_KEY'));
				
				$stsClient = new StsClient( array(
	                'credentials' => $PrimaryCredentials ,
	                // 'profile' => 'default',
	                'region' => $region,
	                'version' => '2011-06-15',
	            ));

	            $stsResult = $stsClient->assumeRole([
	             	'RoleArn' => env('ROLE_ARN'), 
    				'RoleSessionName' => 'RoleSession1' ]);

	            //Generating Temp Credentials for Specified Role
				$tempCredentials = $stsClient->createCredentials($stsResult);

				$headers = array('X-Amz-Access-Token' => $xAmzAccessToken, 'Content-Type' => 'application/json' );
				
				$url = "https://api.sandbox.dub.yojaka.xp.sellers.a2z.com/v1/orders/$orderId/ship-order" ;
				$request = new \GuzzleHttp\Psr7\Request('PUT', $url, $headers);

				// Construct a request signer
				$signer = new SignatureV4("execute-api", $region);

				// Sign the request
				$request = $signer->signRequest($request, $tempCredentials);
			
				//Getting Headers After sing request
				$header = $request->getHeaders();
				// Send the request
				try {

					//Need to use Guzzle Temporary using curl Beacuse getting empty response
					$curl = curl_init();
					curl_setopt_array($curl, array(
					  CURLOPT_URL => $url,
					  CURLOPT_RETURNTRANSFER => true,
					  CURLOPT_ENCODING => "",
					  CURLOPT_MAXREDIRS => 10,
					  CURLOPT_TIMEOUT => 30,
					  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					  CURLOPT_CUSTOMREQUEST => "PUT",
					  CURLOPT_POSTFIELDS => '',
					  CURLOPT_HTTPHEADER => array(
					    "Authorization: ".$header['Authorization'][0],
					    "Content-Type: application/json",
					    "Host: api.sandbox.dub.yojaka.xp.sellers.a2z.com",
					    "X-Amz-Date: ".$header['X-Amz-Date'][0],
					    "X-Amz-Security-Token: ".$header['X-Amz-Security-Token'][0],
					    "cache-control: no-cache",
					    "x-amz-access-token: ".$xAmzAccessToken
					  ),
					));

					$response = curl_exec($curl);
					$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
					switch($httpcode)
					{
						case 200 : $message = 'Invoice is generated and retrieved for a given order' ; break;
						case 204 : $message = 'Order shipped successfully ' ; break;
						case 401 : $message = 'Unauthorized. You are not authorized to invoke this API for the given parameters.' ; break;
						case 400 : $message = 'Bad request. The response contains the error details.' ; break;
						case 404 : $message = 'The specified order id is not found.' ; break;
						case 422 : $message = 'Unprocessable Entity. The specified order has been cancelled by the marketplace.' ; break;
						case 500 : $message = 'Internal server error. Contact Amazon support for assistance.';
						case 503 : $message = 'Service unavailable. Retry the request after some time.';
						default :  $message = 'Something went wrong.' ; 
					} 
					$err = curl_error($curl);
					curl_close($curl);
					if ($err) {
					  //echo "cURL Error #:" . $err;
					  return response()->json(['status' => 'faile', 'data' => $err ], 200);
					}else{
						$response = json_decode($response, true); 
						return response()->json(['status' => 'success', 'data' => $response, 'message' => $message ], 200);
					}
				}
				catch (Exception $exception) {
				   $responseBody = $exception->getResponse()->getBody(true);
				   return response()->json(['status' => 'faile', 'data' => $responseBody], 200);
				}
				
	         } catch (\GuzzleHttp\Exception\ClientException $e) {
				$response = json_decode((string) $e->getResponse()->getBody());
				//echo "<h1> --response error ---<h1>";
				return response()->json(['status' => 'faile', 'data' => $response ], 200);
			} 
	}

	//Reject/cancel an order
	public function cancelOrder(Request $request)
	{
		$response = '';
	     try {
				
				$locationId = $request->locationId;	
				$orderId = $request->orderId ;
				$xAmzAccessToken  = $request->xAmzAccessToken;
				$region = env('AWS_REGION') ;
				//Generating Primary Credentials
				$PrimaryCredentials = new Credentials(env('AWS_ACCESS_KEY_ID'), env('AWS_SECRET_ACCESS_KEY'));
				
				$stsClient = new StsClient( array(
	                'credentials' => $PrimaryCredentials ,
	                // 'profile' => 'default',
	                'region' => $region,
	                'version' => '2011-06-15',
	            ));

	            $stsResult = $stsClient->assumeRole([
	             	'RoleArn' => env('ROLE_ARN'), 
    				'RoleSessionName' => 'RoleSession1' ]);

	            //Generating Temp Credentials for Specified Role
				$tempCredentials = $stsClient->createCredentials($stsResult);

				$headers = array('X-Amz-Access-Token' => $xAmzAccessToken, 'Content-Type' => 'application/json' );
				
				$url = "https://api.sandbox.dub.yojaka.xp.sellers.a2z.com/v1/orders/$orderId/reject-order";


				$data =  '{
						    "referenceId": "RJCT-ID-1",
						     "rejectedLineItems": 
						      [
						      	{
							      	"lineItem": {
										           "id": "LI1"
										        },
							        "reason": "OUT_OF_STOCK",
							        "quantity": 1
						      	}
						      ]
						    }'; 
						
				$request = new \GuzzleHttp\Psr7\Request('PUT', $url, $headers, $data );

				// Construct a request signer
				$signer = new SignatureV4("execute-api", $region);

				// Sign the request
				$request = $signer->signRequest($request, $tempCredentials);
			
				//Getting Headers After sing request
				$header = $request->getHeaders();
				// Send the request
				try {

					//Need to use Guzzle Temporary using curl Beacuse getting empty response
					$curl = curl_init();
					curl_setopt_array($curl, array(
					  CURLOPT_URL => $url,
					  CURLOPT_RETURNTRANSFER => true,
					  CURLOPT_ENCODING => "",
					  CURLOPT_MAXREDIRS => 10,
					  CURLOPT_TIMEOUT => 30,
					  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					  CURLOPT_CUSTOMREQUEST => "PUT",
					  CURLOPT_POSTFIELDS => $data,
					  CURLOPT_HTTPHEADER => array(
					    "Authorization: ".$header['Authorization'][0],
					    "Content-Type: application/json",
					    "Host: api.sandbox.dub.yojaka.xp.sellers.a2z.com",
					    "X-Amz-Date: ".$header['X-Amz-Date'][0],
					    "X-Amz-Security-Token: ".$header['X-Amz-Security-Token'][0],
					    "cache-control: no-cache",
					    "x-amz-access-token: ".$xAmzAccessToken
					  ),
					));

					$response = curl_exec($curl);
					$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
					switch($httpcode)
					{
						case 200 : $message = 'Invoice is generated and retrieved for a given order' ; break;
						case 204 : $message = 'Order cancelled successfully ' ; break;
						case 401 : $message = 'Unauthorized. You are not authorized to invoke this API for the given parameters.' ; break;
						case 400 : $message = 'Bad request. The response contains the error details.' ; break;
						case 404 : $message = 'The specified order id is not found.' ; break;
						case 422 : $message = 'Unprocessable Entity. The specified order has been cancelled by the marketplace.' ; break;
						case 500 : $message = 'Internal server error. Contact Amazon support for assistance.';
						case 503 : $message = 'Service unavailable. Retry the request after some time.';
						default :  $message = 'Something went wrong.' ; break;
					} 
					$err = curl_error($curl);
					curl_close($curl);
					if ($err) {
					  //echo "cURL Error #:" . $err;
					  return response()->json(['status' => 'faile', 'data' => $err ], 200);
					}else{
						$response = json_decode($response, true); 
						return response()->json(['status' => 'success', 'data' => $response, 'message' => $message ], 200);
					}
				}
				catch (Exception $exception) {
				   $responseBody = $exception->getResponse()->getBody(true);
				   return response()->json(['status' => 'faile', 'data' => $responseBody], 200);
				}
				
	         } catch (\GuzzleHttp\Exception\ClientException $e) {
				$response = json_decode((string) $e->getResponse()->getBody());
				//echo "<h1> --response error ---<h1>";
				return response()->json(['status' => 'faile', 'data' => $response ], 200);
			} 
	}

	//API to update details about the packages that are being used to fulfil an order.
	public function updatePackages(Request $request)
	{
		$response = '';
	     try {
				
	     	    $data = $request->getContent(); //dd($content); die;
				$locationId = $request->locationId ;	
				$orderId = $request->orderId ; 	
				$xAmzAccessToken  = $request->xAmzAccessToken;
				$region = env('AWS_REGION') ;
				//Generating Primary Credentials
				$PrimaryCredentials = new Credentials(env('AWS_ACCESS_KEY_ID'), env('AWS_SECRET_ACCESS_KEY'));
				
				$stsClient = new StsClient( array(
	                'credentials' => $PrimaryCredentials ,
	                'region' => $region,
	                'version' => '2011-06-15',
	            ));

	            $stsResult = $stsClient->assumeRole([
	             	'RoleArn' => env('ROLE_ARN'), 
    				'RoleSessionName' => 'RoleSession1' ]);

	            //Generating Temp Credentials for Specified Role
				$tempCredentials = $stsClient->createCredentials($stsResult);

				$headers = array('X-Amz-Access-Token' => $xAmzAccessToken, 'Content-Type' => 'application/json' );

				$url = "https://api.sandbox.dub.yojaka.xp.sellers.a2z.com/v1/orders/$orderId/update-packages";
			    
/*				$data =  '{
"packages": 
  [
    {
    "id": "001",
     "length": 
      {"value": 3.14159,
       "dimensionUnit": "CM"},
     "width": 
      {"value": 3.14159,
       "dimensionUnit": "CM"},
     "height": 
      {"value": 3.14159,
       "dimensionUnit": "CM"},
     "weight": 
      {"value": 1200.14159,
       "weightUnit": "grams"},
     "packagedLineItems": 
      [
      	{
      	"lineItem":
        {
           "id": "0"
        },
          "quantity": 2
      	}
      ]
    }
]
}'; */	
				$request = new \GuzzleHttp\Psr7\Request('PUT', $url, $headers, $data);
				
				// Construct a request signer
				$signer = new SignatureV4("execute-api", $region);

				// Sign the request
				$request = $signer->signRequest($request, $tempCredentials);
			
				//Getting Headers After sing request
				$header = $request->getHeaders();


				// Send the request
				try {

					//Need to use Guzzle Temporary using curl Beacuse getting empty response
					$curl = curl_init();

					curl_setopt_array($curl, array(
					  CURLOPT_URL => $url,
					  CURLOPT_RETURNTRANSFER => true,
					  CURLOPT_ENCODING => "",
					  CURLOPT_MAXREDIRS => 10,
					  CURLOPT_TIMEOUT => 30,
					  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					  CURLOPT_CUSTOMREQUEST => "PUT",
					  CURLOPT_POSTFIELDS => $data,
					  CURLOPT_HTTPHEADER => array(
					    "Authorization: ".$header['Authorization'][0],
					    "Content-Type: application/json",
					    "Host: api.sandbox.dub.yojaka.xp.sellers.a2z.com",
					    "X-Amz-Date: ".$header['X-Amz-Date'][0],
					    "X-Amz-Security-Token: ".$header['X-Amz-Security-Token'][0],
					    "cache-control: no-cache",
					    "x-amz-access-token: ".$xAmzAccessToken
					  ),
					));

					$response = curl_exec($curl);
					$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
					switch($httpcode)
					{
						case 204 : $message = 'Package Created' ; break;
						case 401 : $message = 'Unauthorized. You are not authorized to invoke this API for the given parameters.' ; break;
						case 400 : $message = 'Bad request. The response contains the error details.' ; break;
						case 404 : $message = 'The specified order id is not found.' ; break;
						case 422 : $message = 'Unprocessable Entity. The specified order has been cancelled by the marketplace.' ; break;
						case 500 : $message = 'Internal server error. Contact Amazon support for assistance.';
						case 503 : $message = 'Service unavailable. Retry the request after some time.';
						default :  $message = 'Something went wrong.' ;
					} 
					$err = curl_error($curl);
					curl_close($curl);
					if ($err) {
					  return response()->json(['status' => 'faile', 'data' => $err ], 200);
					}else{
						$response = json_decode($response, true); 
						return response()->json(['status' => 'success', 'data' => $response, 'message' => $message ], 200);
					}
				}catch (Exception $exception) {
				    $responseBody = $exception->getResponse()->getBody(true);
				    return response()->json(['status' => 'faile', 'data' => $responseBody], 200);
				}
				
	         } catch (\GuzzleHttp\Exception\ClientException $e) {
				$response = json_decode((string) $e->getResponse()->getBody());
				//echo "<h1> --response error ---<h1>";
				return response()->json(['status' => 'faile', 'data' => $response ], 200);
			} 
	}

}
