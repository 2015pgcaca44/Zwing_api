<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;


class VendorFactoryController extends Controller
{
	//public $instance;

	public function getInstance(Request $request, $class_name){
		$v_id = $request->v_id;
		$base_namespace = 'App\Http\Controllers';
		$path = explode('\\', $class_name);
		$class_name = array_pop($path);
		$instance = null;
		if($v_id == 16){
          
            $class = $base_namespace."\Spar\\".$class_name;
			$instance = new $class();

        } else if($v_id == 3) {

            $class = $base_namespace."\Vmart\\".$class_name;
			$instance = new $class();

        }else if($v_id == 26) {

            $class = $base_namespace."\Zwing\\".$class_name;
			$instance = new $class();

        }else if($v_id == 28) {

            $class = $base_namespace."\Hero\\".$class_name;
			$instance = new $class();

        }else if($v_id == 30) {

            $class = $base_namespace."\Hero\\".$class_name;
			$instance = new $class();

        }else if($v_id == 34){
	
			$class = $base_namespace."\Metro\\".$class_name;
			$instance = new $class();
		}

		return $instance; 
	}
}