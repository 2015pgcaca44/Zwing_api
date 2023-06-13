<?php

namespace App\Http\Controllers\Erp\Ginesys;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiCallerController;
use App\Http\Traits\VendorFactoryTrait;
use Illuminate\Http\Request;
use Log;
use DB;


class StoreController extends Controller
{
	private $config= null;
	
	public function __construct()
    {
    	//$this->config = new ConfigController;
    }

    public function operationStarted($params){

    	$id = null;
    	$outBound = $params['outBound'];
    	$v_id = $params['v_id'];
    	$store_id = $params['store_id'];
    	$client_id = $params['client_id'];
    	// $api_status = $params['api_status'];
        JobdynamicConnection($v_id); 
    	$client = $params['client'];
    	$error_for = $params['error_for'];
    	$store = $params['store'];
    	$vendor = $params['vendor'];
    	
    	$request = [];
        $operationStarted = str_replace(' ', 'T', $store->store_active_date);
                             
		$request['storeId'] = (int)$store->store_reference_code; // Int
		$request['operationStartDate'] = $operationStarted; //2020-02-04T08:52:07.825Z

        $outBound->api_request = json_encode($request);
        $outBound->save();

		// dd(json_encode($request));
        $config = new ConfigController($v_id);
		$apiCaller = new  ApiCallerController([
			'url' => $config->apiBaseUrl.'/OperationStartdateUpdate',
			'data'=> $request, 
			'method' => 'PUT',
			'header' => [ 'Content-Type:application/json'],
			'auth_type' => $config->authType,
			'auth_token' => $config->authToken
		]);
		# extract the body
		$response = $apiCaller->call();
        $outBound->api_response = isset($response['body'])?$response['body']:'';
        $outBound->response_status_code = $response['header_status'];
        $outBound->save();
        
        return $config->handleApiResponse($response); 

		// if(json_encode($response)){

		// }
		// dd(json_decode($response));
		//return $response;
	}

}