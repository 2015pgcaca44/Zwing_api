<?php

namespace App\Http\Controllers\Erp\Ginesys;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Erp\ConfigInterface;
use Illuminate\Http\Request;
use App\Model\Client\ClientVendorMapping;

class ConfigController extends Controller implements ConfigInterface
{

    public $apiBaseUrl = '';
    public $authType = 'Bearer';
    public $authToken = null;
    public $v_id=null;

    public function __construct($v_id)
    {
        $this->setVendorId($v_id);
        $this->setApiBaseUrl();
        $this->setToken();
    }

    public function setVendorId($id){
          //dd($id);
        $this->v_id = $id;
       //dd($this->v_id);
    }

    public function getApiBaseUrl(){
        return $this->apiBaseUrl;
    }

    public function setApiBaseUrl(){
       
        $clientVendor      = ClientVendorMapping::select('api_url','api_token')->where('v_id',$this->v_id)->first();
        if($clientVendor){
           $this->apiBaseUrl = $clientVendor->api_url;
           $this->authToken = $clientVendor->api_token;
        }else{
          $this->apiBaseUrl = '';

        }
    }

    public function setToken(){
        ## setting token from setApiBaseUrl

        // $this->authToken = AuthController::getToken(['apiBaseUrl' => $this->apiBaseUrl,'v_id'=>$this->v_id ]);
    }

    

    public function handleApiResponse($response){
        
        if($response['header_status'] == 200 || $response['header_status'] ==201){
            return ['message' => 'Data Push Successfully' ]; 
        }else{
            $body = 'Api Called but Getting Error from Ginesys Api with empty response';
            if(isset($response['body'])){
                $body = $response['body'];
            }
            $header_status = '';
            if(isset($response['header_status'])){
                $header_status = $response['header_status'];
            }

            return [ 
                'error' => true , 
                'message' => $body, 
                'header_status' => $header_status
            ];
        }
    }

}