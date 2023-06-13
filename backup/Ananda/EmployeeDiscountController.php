<?php

namespace App\Http\Controllers\Ananda;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use SoapClient;
use SimpleXMLElement;

use App\User;
use DB;
use Auth;

class EmployeeDiscountController extends Controller
{


    public function __construct()
	{
		$this->middleware('auth');
	}

    public function get_client(){

        $url = "http://172.16.150.89:7001/EmployeeDiscount/EmployeeWS?wsdl";
        //$params = [ 'encoding'=> 'UTF-8'];

        $client = new SoapClient($url);

        return $client;
    }

    public function get_details($params) {
        
        $arg0 = (string)$params['employee_code'];
        $arg1 = (string)$params['company_name'];

        $client = $this->get_client();
        //dd($client->__getFunctions()); 
        //dd($client->__getTypes()); 

        $response = $client->getEmployeeDetails(['arg0' => $arg0 , 'arg1' => $arg1]);
        $response = $response->return;
        $response = new SimpleXMLElement($response);

        return $response;
    }


    public function update_discount($params){
        
        $arg0 = (string)$params['employee_code'];
        $arg1 = (float)$params['disounted_amount'];
        $arg2 = (string)$params['company_name'];

        $client = $this->get_client();

        $response = $client->updateEmployee(['arg0' => $arg0, 'arg1' => $arg1, 'arg2' => $arg2]);
        if($response->return){
            return ['status' => 'success'];
        }else{
            return ['status' => 'fail'];
        }

    }


}