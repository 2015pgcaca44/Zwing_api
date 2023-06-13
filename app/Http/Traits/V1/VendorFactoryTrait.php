<?php

namespace App\Http\Traits\V1;

use Illuminate\Http\Request;


trait VendorFactoryTrait
{

	public function getInstance(Request $request, $class_name){
		
		$v_id = $request->v_id;
		$base_namespace = 'App\Http\Controllers\V1';
		$path = explode('\\', $class_name);
		$class_name = array_pop($path);
		$instance = null;
		if($v_id == 4){
          
            $class = $base_namespace."\Spar\\".$class_name;
			$instance = new $class();

        }else if($v_id == 9){
          
            $class = $base_namespace."\Star\\".$class_name;
			$instance = new $class();

        } else if($v_id == 1) {

            $class = $base_namespace."\Vmart\\".$class_name;
			$instance = new $class();

        }else if($v_id == 7) {

            $class = $base_namespace."\Zwing\\".$class_name;
			$instance = new $class();

        }else if($v_id == 11) {

            $class = $base_namespace."\Dmart\\".$class_name;
			$instance = new $class();

        }else if($v_id == 10) {

            $class = $base_namespace."\Hero\\".$class_name;
			$instance = new $class();

        }else if($v_id == 8){
	
			$class = $base_namespace."\Metro\\".$class_name;
			$instance = new $class();
		}else if($v_id == 2){
	
			$class = $base_namespace."\Manyavar\\".$class_name;
			$instance = new $class();
		}
		else if($v_id == 3){
	
			$class = $base_namespace."\Crimsouneclub\\".$class_name;
			$instance = new $class();
		}  elseif($v_id == 5){
	
			$class = $base_namespace."\Ananda\\".$class_name;
			$instance = new $class();
		} elseif($v_id == 6){
	
			$class = $base_namespace."\Haldiram\\".$class_name;
			$instance = new $class();
		}elseif($v_id == 12){
	
			$class = $base_namespace."\Falafel\\".$class_name;
			$instance = new $class();
		}
		elseif($v_id == 13){
	
			$class = $base_namespace."\Biba\\".$class_name;
			$instance = new $class();
		}

		return $instance; 
	}

	public function callMethod($request , $class_name , $method_name){

        $path = explode('::',$method_name);
        $method_name = array_pop($path);
        //echo $method_name;exit;
        //$factory = new VendorFactoryController;
        return  $this->getInstance($request , $class_name )->$method_name($request);
        //return  $factory->getInstance($request , $class_name )->$method_name($request);
    }
}