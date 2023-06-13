<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\DeviceStorage;
use App\DeviceVendorUser;
use DB;


class DeviceController extends Controller
{
   
   public function devicesyncstatus($request){
  		$v_id  = $request->v_id;
 		$udid  = $request->udid;
   		$deviceValue = DB::table('device_vendor_user')->join('device_storage', function ($join) use($request) {
								            $join->on('device_vendor_user.device_id', '=', 'device_storage.id')
								            	->where('device_storage.udid', $request->udid)
								            	->where('device_vendor_user.v_id', $request->v_id) ;
								        })->select('sync_status')->get()->first();
   		
   		//print_r($deviceValue);die;

    	if(!empty($deviceValue)){
   			return $deviceValue->sync_status;
   		}else{
   			return 0;  //No Device Found
   		}
   		



   } 


   public function getdevice($request){
      $v_id  = $request->v_id;
      $udid  = $request->udid;
      $deviceValue = DB::table('device_storage')->where('device_storage.udid', $request->udid)->count();

      if($deviceValue){
        return $deviceValue;
      }else{
        return 0;
      }
    } 
}
