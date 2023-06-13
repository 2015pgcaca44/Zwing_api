<?php

namespace App\Http\Controllers\Erp\Ginesys;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiCallerController;
use App\Model\Client\ClientVendorMapping;

class AuthController
{
    private static $token = null;

	protected function __construct()
    {

    }

    public static function getToken($params){
        if(self::$token){
            return self::$token;
        }else{
              $clientvendor = ClientVendorMapping::select('api_token')->where('v_id',$params['v_id'])->first();
              if($clientvendor){
              self::$token = $clientvendor->api_token;
              }
            // self::$token =  '824Qapb6wK8cmJ4CEffi4J7BrOr5FJYRQcG3r+HpEgyCqNbPvP8XCQzzWkPfBx475ydppyMSNN7X/inVQxHNMw==';
            return self::$token;
            
            //Need to implement
            $request = [];
            $apiCaller = new  ApiCallerController([
                'url' => $params['apiBaseUrl'].'/oauth/token',
                'data'=> $request, 
                'header' => [ 'Content-Type:application/json']
            ]);
            # extract the body
            $response = $apiCaller->call();
            $response = json_decode($response);
            self::$token =  $response['token'];
            return self::$token;
        }
    }

}