<?php

namespace App\Http\Controllers\Erp\Bluekaktus;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiCallerController;

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

            $request = ['username' => $params['authUsername'], 'password' => $params['authPassword'] , 'grant_type' =>  $params['authGrantType']];
            // dd($request);
            $apiCaller = new  ApiCallerController([
                'url' => $params['apiBaseUrl'].'/oauth/token',
                'data'=> $request, 
                'header' => [ 'Content-Type:application/x-www-form-urlencoded']
            ]);
            # extract the body
            $response = $apiCaller->call();
            $response = json_decode($response['body']);
            if(isset($response->access_token)){
                self::$token =  $response->access_token;
            }
            return self::$token;
        }
    }

}