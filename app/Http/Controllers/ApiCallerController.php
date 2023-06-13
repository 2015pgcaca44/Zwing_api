<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Log;

class ApiCallerController extends Controller
{
    protected $url; 
    protected $data;
    protected $method=null; 
    protected $header = null; 
    protected $requestType = Null; 
    
    public function __construct($params)
    {
        $this->url =  $params['url'];
        $this->data =  $params['data'];
        if(isset($params['method'])){
        $this->method =  $params['method'];
        }
        //Checking if header exists or not
        if(isset($params['header']) ){
            if(is_array($params['header'])){

                
                $this->header      =  $params['header'];

                foreach($params['header'] as $key => $header){
                    
                    //Checking if header contains json request
                    if(strpos($header, 'application/json', ) !==false){
                        $this->requestType = 'JSON';
                        $this->data = json_encode($params['data']);
                        // $this->header[] = 'Content-Length: ' . strlen($this->data);
                        break;
                    }

                    //Checking if header contains url ecoded
                    if(strpos($header, 'application/x-www-form-urlencoded') !==false){
                        $this->data = http_build_query($params['data']);
                        // $this->header[] = 'Content-Length: ' . strlen($this->data);
                        break;
                    }

                }
            }
        }

        if( isset($params['auth_type']) && $params['auth_type'] == 'Bearer'){
            $this->header[] = 'Authorization: Bearer '.$params['auth_token'];
        }
        
    }


    public function getHeaders($respHeaders) {
        $headers = array();
        $headerText = substr($respHeaders, 0, strpos($respHeaders, "\r\n\r\n"));
        foreach (explode("\r\n", $headerText) as $i => $line) {
            if ($i === 0) {
                $headers['http_code'] = $line;
            } else {
                list ($key, $value) = explode(': ', $line);
                $headers[$key] = $value;
            }
        }
        return $headers;
    }


    public function call(){
        $ch = curl_init($this->url);
        if($this->method == 'PUT'){
         curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');  
        }else{
        curl_setopt($ch, CURLOPT_POST, true);
       }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        if($this->header){
            // curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->header );
        }

        $result = curl_exec($ch); 
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            $error_msg = 'Error in exceuting third party api: '.$error_msg;
            Log::error($error_msg);
            return ['header_status'=>$httpcode,'body'=> $error_msg];
            // die('There is an error in api call');
        }else{
            // echo 'Inside else call';
            // dd($this->header);
        }

        // dd($result);
        //if(!$result){ die('Connection not established');}
        curl_close($ch);

        //dd($result);

        // $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        //dd($result);

        // if($this->requestType == 'JSON'){

        //     // extract header
        //     $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        //     dd($header);
        //     $header = substr($result, 0, $headerSize);
        //     $header = $this->getHeaders($header);
        //     // extract body
        //     $body = substr($body, $headerSize);
        //     dd($body);
         //return [ 'header' => $header , 'body' => $body ];
        // }else{
          //return $result;
            return ['header_status'=>$httpcode,'body'=>$result];
        // }
        
    }

}