<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;
use DB;

trait VendorFactoryTrait
{
	public function getInstance(Request $request, $class_name)
	{
		$v_id 			= $request->v_id;
		//$vendor         = DB::table('vendor')->where('id', $v_id)->first();
		$vendor =DB::connection('mysql')->table('vendor')->where('id', $v_id)->first();
		$base_namespace = 'App\Http\Controllers';
		$path 			= explode('\\', $class_name);
		$class_name 	= array_pop($path);
		$instance 		= null;

		if($vendor->db_structure == 2){
			// if($v_id == 27){
			// 	$class = $base_namespace."\Cinepolis\\".$class_name;
			// 	$instance = new $class();
			// } else {
				$class = $base_namespace . "\CloudPos\\" . $class_name;
				$instance = new $class();
			// }
		} else if ($v_id == 1) {
			$class = $base_namespace . "\Vmart\\" . $class_name;
			$instance = new $class();
		} else if ($v_id == 2) {
			$class = $base_namespace . "\Manyavar\\" . $class_name;
			$instance = new $class();
		} else if ($v_id == 3) {
			$class = $base_namespace . "\Crimsouneclub\\" . $class_name;
			$instance = new $class();
		} else if ($v_id == 4) {
			$class = $base_namespace . "\Spar\\" . $class_name;
			$instance = new $class();
		} elseif ($v_id == 5) {

			$class = $base_namespace . "\Ananda\\" . $class_name;
			$instance = new $class();
		} elseif ($v_id == 6) {

			$class = $base_namespace . "\Haldiram\\" . $class_name;
			$instance = new $class();
		} else if ($v_id == 7) {

			$class = $base_namespace . "\Zwing\\" . $class_name;
			$instance = new $class();
		} else if ($v_id == 8) {

			$class = $base_namespace . "\Metro\\" . $class_name;
			$instance = new $class();
		} else if ($v_id == 9) {

			$class = $base_namespace . "\Star\\" . $class_name;
			$instance = new $class();
		} else if ($v_id == 10) {

			$class = $base_namespace . "\Hero\\" . $class_name;
			$instance = new $class();
		} else if ($v_id == 11) {

			$class = $base_namespace . "\Dmart\\" . $class_name;
			$instance = new $class();
		} elseif ($v_id == 12) {

			$class = $base_namespace . "\Falafel\\" . $class_name;
			$instance = new $class();
		} elseif ($v_id == 13) {

			$class = $base_namespace . "\Biba\\" . $class_name;
			$instance = new $class();
		} elseif ($v_id == 15) {

			$class = $base_namespace . "\Ginesys\\" . $class_name;
			$instance = new $class();
		} elseif ($v_id == 17) {

			$class = $base_namespace . "\JustDelicious\\" . $class_name;
			$instance = new $class();
		} elseif ($v_id == 19) {
			$class = $base_namespace . "\NurturingGreen\\" . $class_name;
			$instance = new $class();
		} elseif ($v_id == 20) {
			$class = $base_namespace . "\More\\" . $class_name;
			$instance = new $class();
		} elseif ($v_id == 21) {
			$class = $base_namespace . "\MajorBrands\\" . $class_name;
			$instance = new $class();
		} elseif ($v_id == 22) {
			$class = $base_namespace . "\Nyasa\\" . $class_name;
			$instance = new $class();
		} elseif ($v_id == 23) {
			$class = $base_namespace . "\XimiVogue\\" . $class_name;
			$instance = new $class();
		} elseif ($v_id == 24) {
			$class = $base_namespace . "\Gurram\\" . $class_name;
			$instance = new $class();
		} 
		// elseif ($v_id == 27) {
		// 	$class = $base_namespace . "\Cinepolis\\" . $class_name;
		// 	$instance = new $class();
		// } 
		elseif ($v_id == 35) {
			$class = $base_namespace . "\Skechers\\" . $class_name;
			$instance = new $class();
		} elseif ($v_id == 14) {
			$class = $base_namespace . "\Bazaarkolkata\\" . $class_name;
			$instance = new $class();
		} else if ($v_id == 53) {
			$class = $base_namespace . "\Vmart\\" . $class_name;
			$instance = new $class();
		}

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
