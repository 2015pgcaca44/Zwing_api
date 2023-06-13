<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;
use DB;
use App\Organisation;

trait ErpFactoryTrait
{
	public function getInstance($request, $class_name)
	{
		$v_id = null;
		if(isset($request->v_id) ){
			$v_id 			= $request->v_id;
		}else{
			$v_id 			= $request['v_id'];
		}
		// $vendor = Organisation::select('client_id')->where('id', $v_id)->first();
		$vendor = DB::connection('mysql')->table('vendor')->select('client_id')->where('id', $v_id)->first();
		$client_id = $vendor->client_id;
		// $vendor         = DB::table('vendor')->where('id', $v_id)->first();
		$base_namespace = 'App\Http\Controllers\Erp';
		$path 			= explode('\\', $class_name);
		$class_name 	= array_pop($path);
		$instance 		= null;
		$class          = null;
		if($client_id == 1) {
			$class = $base_namespace . "\Ginesys\\" . $class_name;
			$instance = new $class();
		} else if ($client_id == 2) {
			$class = $base_namespace . "\Bluekaktus\\" . $class_name;
			$instance = new $class();
		}else{
		}
		// dd('client_id'.$client_id);

		return $instance;
	}

	public function callMethod($request, $class_name, $method_name)
	{
		$path = explode('::', $method_name);
		$method_name = array_pop($path);
		//echo $method_name;exit;
		//$factory = new VendorFactoryController;
		return  $this->getInstance($request, $class_name)->$method_name($request);
		//return  $factory->getInstance($request , $class_name )->$method_name($request);
	}
}
