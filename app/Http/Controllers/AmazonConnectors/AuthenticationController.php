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

use App\PhonepeMerchant;
use App\PhonepeTransactions;
use Carbon\Carbon;
//use \Aws\S3\S3Client; //XX
//use \Aws\Iam\IamClient; 
use \Aws\Sts\StsClient; 
//use \Aws\Common\Credentials\Credentials;
use \Aws\Signature\SignatureV4 ;//XX
use \Aws\Exception\AwsException;
use \Aws\Credentials\Credentials;
//use GuzzleHttp\Psr7\Request;


class AuthenticationController extends Controller
{
    public function getAccessToken(Request $request)
	{
			$response = '';
	        try {
				$client = new Client(); 
				$data = ['grant_type' => $request->grant_type,
						'refresh_token' => $request->refresh_token,
						'client_id' => config('services.amazon.client_id'),
						'client_secret' => config('services.amazon.client_secret')
						];
			
				$headers = ['X-Amz-Content-Sha256' => 'b243eaca21bd52d7e499f0c0525cb37a11da9cf62d554c721d0c244b5ac809a4',
							'X-Amz-Date' => '20200723T090914Z',
							'Authorization' => 'AWS4-HMAC-SHA256 Credential=/20200723/eu-west-1/execute-api/aws4_request, 
												SignedHeaders=host;x-amz-content-sha256;x-amz-date, 
												Signature=009942c7937222de2119ee2cd20633de8b2a4d0d7ad2f149269f7ef40709d755',
							'Content-Type' => 'application/x-www-form-urlencoded'];
				$res = $client->request('POST', 'https://api.amazon.com/auth/O2/token', ['form_params'=>$data, 'headers' => $headers] );
				$response = json_decode($res->getBody(), true); 
				return response()->json(['status' => 'success', 'data' => $response], 200);
	         } catch (\GuzzleHttp\Exception\ClientException $e) {
				$response = json_decode((string) $e->getResponse()->getBody());
				return response()->json(['status' => 'fail', 'data' => $response ], 200);
			} 
	}

//Assume-role  temporary security credentials 
public function getSecurityToken(Request $request)
{
	$client = StsClient::factory(array(
	                'credentials' => array(
	                    'key'    => 'AKIAQR4RC4CE253RJ6AH',
	                    'secret' => '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',
	                ),
	                'region' => 'eu-west-1',
	                'version' => '2011-06-15',
	            ));
	$result = $client->getSessionToken();
	echo "<pre>";
	
	print_r($result); 
}

public function getSignature()
{
	//$request = new \GuzzleHttp\Psr7\Request; 
	$headers = ['X-Amz-Access-Token' => 'Atza|IwEBIElVIOcLuJtztDmtYCwH1AJGeP3oHnf7ktpr3uRN07c1x3wnnuoq2R3tdxrA7DS-de_uGheQ_ZxyBXIntqB7dXMwdV6KYawzlDgd1RuJJIcimNmrBpodtPb5nFdYsRl0T2nU4Bw-znlcEXbubLFpoaIhtsz6udwFd7QQX_YmXfoRYJEr8w7rKwEV5hQJF8P1XhPOlH4BATyI7j-2smMHGy5xMSG21VpWfNO2l2rB691z6obp2YQIPlamTaSWh9LI5GAvkAzQ9btU2KP5qVOU44kByeONx3tvQVf7JbfG0t9SFqYirT0eaEpcM-MB8rtmGZeZ1W8617Rez3lujCcnIZXwBpObroa4gkT304Pz4mrRseb4EI11J_vSJLZl5ohFnuYTjpohKtlnw8jZCt4JVcIOOPqXcg0P2E1luL89A26jrw',
	'X-Amz-Security-Token'=> 'FwoGZXIvYXdzEFgaDJvF/ZokCnPy3gQrZCKCAe6OwpiY//zmJtwIpi3jj8/Q8bK4wRbRxdd/LIwSbQUFLelrsZ8jy3KA7KqlrIB7Q3ue1Kc312rEBpnbZsepECMIrbO2XZhCCYjbqHO4SmOiKtQ1FdgRecrf7RdEluGM9sV1UbkuXClD3NWdXvnQ6MyWw/BHKd6Z612nJ2OZj0EBkAYouauE+QUyKDRU+YAOhgCGw8xLeOvS2i9dQqTl3WZ1c4PGFK3Lx2HxYaFiIfmEyc8=',
	'Content-Type' => 'application/json'];

    /*$request = $client->request(
	    'PUT',
	    'https://api.sandbox.dub.yojaka.xp.sellers.a2z.com',
	    [
	        'form_params' => [
	            "type" => "client",
	            "action" => "read",
	            "limit" => 10
	        ], 
	        'headers' => $headers
	    ]      
	);*/

	 $request = new \GuzzleHttp\Psr7\Request(
	   'PUT',
	    'https://api.sandbox.dub.yojaka.xp.sellers.a2z.com',
	    [
	        'form_params' => [
	            "type" => "client",
	            "action" => "read",
	            "limit" => 10
	        ], 
	        'headers' => $headers
	    ]   
	);

	//$key = 'AKIAQR4RC4CE253RJ6AH';
	//$secret = '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';


	$key = 'ASIAQR4RC4CEYDUMLI66';
	$secret = '+gwH+N3SR24mYppybGl5LYvYDQ1IoHxYXpOqzV70';
	$credentials = new Credentials($key, $secret);

	// Construct a request signer
	$region = 'eu-west-1';
	$signer = new SignatureV4("execute-api", $region);

	// Sign the request
	$request = $signer->signRequest($request, $credentials);



	// Send the request
	try {
	    $response = (new Client)->send($request);
	    print_r($response);
	}
	    catch (Exception $exception) {
	    $responseBody = $exception->getResponse()->getBody(true);
	    echo $responseBody;
	}
	$response = (new Client)->send($request);




	}
	
	/*use \Illuminate\Support\Facades\Artisan;
	public function apilog_job(Request $request)
	{
		//ApilogJob::handle();
		$exitCode = Artisan::call('queue:work');
		//$exitCode = exec('php artisan queue:work ');
		echo $exitCode;
	}*/
}
