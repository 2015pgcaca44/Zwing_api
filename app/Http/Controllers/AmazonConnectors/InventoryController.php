<?php
/**
 * @author: Shweta T
 * @Discription : Amazon Yojaka Connectors To call API
 * Link : https://docs.beta.dub.yojaka.xp.sellers.a2z.com/docs/connectors/apis.html
 * Date: 23/07/2020
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

class InventoryController extends Controller
{
	//Get inventory : Get the current inventory for a given SKU at a given location.
    public function getInventory(Request $request)
	{
		$response = '';
	     try {
				
				$locationId = $request->locationId;	
				$skuId = $request->skuId;	
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

				$url = "https://api.sandbox.dub.yojaka.xp.sellers.a2z.com/v1/inventories?locationId=$locationId&skuId=$skuId";
					
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
						case 200 : $message = 'Inventory details' ; break;
						case 401 : $message = 'Unauthorized. You are not authorized to invoke this API for the given parameters.' ; break;
						case 400 : $message = 'Bad request. The response contains the error details.' ; break;
						case 409 : $message = 'Conflict. Concurrent updates to the same inventory are not supported.' ; break;
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
				//$response = (new Client)->send($request);
				$response = json_decode($response, true); 
				return response()->json(['status' => 'success', 'data' => $response], 200);
	         } catch (\GuzzleHttp\Exception\ClientException $e) {
				$response = json_decode((string) $e->getResponse()->getBody());
				//echo "<h1> --response error ---<h1>";
				return response()->json(['status' => 'faile', 'data' => $response ], 200);
			} 
			
	}

	//Get inventory : Get the current inventory for a given SKU at a given location.
    public function updateInventory(Request $request)
	{
		$response = '';
	     try {
				
				$locationId = $request->locationId ;	
				$skuId = $request->skuId ;	
				$quantity = $request->quantity ;
				$inventoryUpdateSequence = $request->quantity ;
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

				$url = "https://api.sandbox.dub.yojaka.xp.sellers.a2z.com/v1/inventories?locationId=$locationId&skuId=$skuId&quantity=$quantity&inventoryUpdateSequence=$inventoryUpdateSequence";
					
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
						case 200 : $message = 'Inventory updated successfully.' ; break;
						case 401 : $message = 'Unauthorized. You are not authorized to invoke this API for the given parameters.' ; break;
						case 400 : $message = 'Bad request. The response contains the error details.' ; break;
						case 409 : $message = 'Conflict. Concurrent updates to the same inventory are not supported.' ; break;
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
				    echo $responseBody;
				}
				//$response = (new Client)->send($request);
				$response = json_decode($response, true); 
				return response()->json(['status' => 'success', 'data' => $response], 200);
	         } catch (\GuzzleHttp\Exception\ClientException $e) {
				$response = json_decode((string) $e->getResponse()->getBody());
				//echo "<h1> --response error ---<h1>";
				return response()->json(['status' => 'faile', 'data' => $response ], 200);
			} 
	}

	

	//Get inventory : Get the current inventory for a given SKU at a given location.
    public function updatePrices(Request $request)
	{
		$response = '';
	     try {
				
				$locationId = $request->locationId ;	
				$skuId = $request->skuId ;	
				$marketplace = $request->marketplace ; //AMAZON_IN
				$channel = $request->channel ; //FBA
				$maxRetailPrice =  $request->maxRetailPrice ; //FBA
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

				$url = "https://api.sandbox.dub.yojaka.xp.sellers.a2z.com/v1/skus/$skuId/prices?marketplaceName=$marketplace&channelName=$channel";
			
				$data =  '{
							"maxRetailPrice": {
								"currency": "INR",
								"value": "'.$maxRetailPrice.'"
							}
						  }'; 	
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
						case 204 : $message = 'Price Updateed' ; break;
						case 401 : $message = 'Unauthorized. You are not authorized to invoke this API for the given parameters.' ; break;
						case 404 : $message = 'The specified SKU id is not found' ; break;
						case 404 : $message = 'The specified SKU id is not found' ; break;
						default :  $message = 'Something went wrong.' ; break;
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
				    return response()->json(['status' => 'success', 'data' => $responseBody], 200);
				}
				/*//$response = (new Client)->send($request);
				$response = json_decode($response, true); 
				return response()->json(['status' => 'success', 'data' => $response], 200);*/
	         } catch (\GuzzleHttp\Exception\ClientException $e) {
				$response = json_decode((string) $e->getResponse()->getBody());
				//echo "<h1> --response error ---<h1>";
				return response()->json(['status' => 'faile', 'data' => $response ], 200);
			} 
	}


}
