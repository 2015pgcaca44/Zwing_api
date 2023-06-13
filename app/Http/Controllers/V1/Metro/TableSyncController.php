<?php

namespace App\Http\Controllers\V1\Metro;

use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\VendorSettingController;
//use App\DataSyncStatus;
use App\DeviceVendorUser;
use Illuminate\Http\Request;
use DB;

use App\Cart;
use App\Order;

class TableSyncController extends Controller
{

    public function __construct()
	{
		//$this->middleware('auth');
	}

	public function sync(Request $request){

		$v_id = $request->v_id;
        $store_id = $request->store_id; 
        //$c_id = $request->c_id;
        //$vu_id = $request->vu_id;

        $stores =  DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();
        $store_db_name = $stores->store_db_name;

        $price_master = DB::table($store_db_name.'.price_master as pm')
                        ->select('pm.ITEM as barcode', 'pm.ITEM_DESC as name', 'pm.IMAGE as image', 'pm.MRP1 as mrp')
                        ->limit(90000)
                        ->get();

		//$carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status','process')->orderBy('updated_at','desc')->get();

        return response()->json( ['status' => 'success', 'data' => $price_master ] ,200);
	
	}

    public function success_pre(Request $request){

        $vu_id      =  $request->vu_id;
        $v_id       =  $request->v_id;
        $udid       =  $request->udid;
        $store_id   =  $request->store_id;
        $trans_from =  $request->trans_from;
        $sync_status = '1';
        $getDevice  = DB::table('device_storage')->where('device_storage.udid',$udid)->select('id')->get()->first();
         if(@$getDevice->id){

            $sync_data  = DataSyncStatus::select('id')->where('vu_id',$vu_id)->where('v_id',$v_id)->where('device_id',$getDevice->id)->where('store_id',$store_id)->count();

            //echo $sync_data->id;die;

        if($sync_data == 0){
            $dataSynLog = DataSyncStatus::create(['vu_id' => $vu_id,'v_id' => $v_id,'device_id'=>$getDevice->id,'store_id'=>$store_id,'trans_from' => $trans_from,'sync_status' => $sync_status]);
        }else{
            $dataSynLog = DataSyncStatus::where('vu_id',$vu_id)->where('v_id',$v_id)->where('device_id',$getDevice->id)->where('store_id',$store_id)->update(['sync_status'=>'1']);
        }

            return response()->json( ['status' => 'success'] ,200);
        }else{
            return response()->json( ['status' => 'fail','message'=>'Device Not Found'] ,200);
        }
        
    }

     public function success(Request $request){

        $vu_id      =  $request->vu_id;
        $v_id       =  $request->v_id;
        $udid       =  $request->udid;
        $store_id   =  $request->store_id;
        $trans_from =  $request->trans_from;
        $sync_status = '1';

        $getDevice  = DB::table('device_storage')->where('device_storage.udid',$udid)->select('id')->get()->first();
        if(@$getDevice->id){

            $sync_data  = DeviceVendorUser::select('id')->where('vu_id',$vu_id)->where('v_id',$v_id)->where('device_id',$getDevice->id)->where('store_id',$store_id)->count();

            //echo $sync_data->id;die;

        if($sync_data == 0){
            $dataSynLog = DeviceVendorUser::create(['vu_id' => $vu_id,'v_id' => $v_id,'device_id'=>$getDevice->id,'store_id'=>$store_id,'trans_from' => $trans_from,'sync_status' => $sync_status]);
        }else{
            $dataSynLog = DeviceVendorUser::where('v_id',$v_id)->where('device_id',$getDevice->id)->where('store_id',$store_id)->update(['sync_status'=>'1']);
        }

            return response()->json( ['status' => 'success'] ,200);
        }else{
            return response()->json( ['status' => 'fail','message'=>'Device Not Found'] ,200);
        }


     }

}