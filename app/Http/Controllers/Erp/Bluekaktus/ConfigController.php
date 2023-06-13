<?php

namespace App\Http\Controllers\Erp\Bluekaktus;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Erp\ConfigInterface;
use Illuminate\Http\Request;

class ConfigController extends Controller implements ConfigInterface
{

	public $apiBaseUrl = '';
	public $authType = 'Bearer';
    public $authToken = null;

    public $userId = 1 ;
    public $authUsername = 'zwing';
    public $authPassword = 'Bluekaktus';
    public $authGrantType = 'password';

    public function __construct()
    {
        $this->setApiBaseUrl();
        $this->setToken();
    }

    public function getApiBaseUrl(){
        return $this->apiBaseUrl;
    }

    public function setApiBaseUrl(){
        $this->apiBaseUrl = env('BLUEKAKTUS_ERP_BASE_URL');
    }

    public function setToken(){
        $this->authToken = AuthController::getToken([
            'apiBaseUrl' => $this->apiBaseUrl ,
            'authUsername' => $this->authUsername,
            'authPassword' => $this->authPassword,
            'authGrantType' => $this->authGrantType,
        ]);
    }


    public function handleApiResponse($response){
        if(json_decode($response)){
            $response = json_decode($response);
            if($response->status == 'fail'){
                $message = '';
                if(isset($response->errors) ){
                    $message = implode(",", $response->errors);
                }else{
                    $message = $response->message;
                }
                return ['error' => true , 'message' => $message ];
            }else{
                return ['message' => 'Api Called successfully'];
            }
        }else{
            return ['error' => true,  'message' => 'Unknow response from api' ];
        }
    }

}