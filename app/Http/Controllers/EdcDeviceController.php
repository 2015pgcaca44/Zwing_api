<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\EdcDevice;
use Auth;
use Validator;
use App\MposDevice;
use Event;
use App\Events\EdcLog;
use App\Organisation;

class EdcDeviceController extends Controller
{
    

    public function add(Request $request){

         $requiredfieldvalidator = Validator::make($request->all(), [
            'serial_number'   => 'required',
            'bluetooth_id'    => 'required',
            'bluetooth_name'  => 'required',
            'edc_type'        => 'required',
            'username'        => 'required',
            'password'        => 'required',
        ]);

        /*$serialUnique  = Validator::make($request->all(), [
            'serial_number'   => 'required|unique:edc_devices,serial_number' 
        ]);
        
        if($serialUnique->fails()){
            return response()->json(['status' => 'fail', 'message' => 'The serial number has already been taken.' ], 200);
        }
        */
 

        if($requiredfieldvalidator->fails()){
            return response()->json(['status' => 'fail', 'message' => 'All Field Are Mandatory' ], 200);
        }

        

        if(!$request->udid){
            $request->udid = 0;
        }

        $checkserialnumber   = EdcDevice::where('serial_number',$request->serial_number)->first();
        if($checkserialnumber){
            $edcdevice   = EdcDevice::find($checkserialnumber->id);
            $msg         = 'Update';
        }else{
             $edcdevice  = new EdcDevice();
             $msg        = 'Add';
        }

       
        $edcdevice->serial_number   = $request->serial_number;
        $edcdevice->bluetooth_id    = $request->bluetooth_id;
        $edcdevice->bluetooth_name  = $request->bluetooth_name;
        $edcdevice->edc_type        = $request->edc_type;
        $edcdevice->udid            = $request->udid;
        $edcdevice->username        = $request->username;
        $edcdevice->password        = $request->password;
        $edcdevice->v_id            = $request->vendor_id;
        $edcdevice->store_id        = $request->store_id;
        $edcdevice->save();

        $edcdata = array('udid' => $edcdevice->udid,'serial_number'=>$edcdevice->serial_number,'bluetooth_id'=>$edcdevice->bluetooth_id,'edc_type' => $edcdevice->edc_type,'username'=>$edcdevice->username,'password'=>$edcdevice->password,'v_id' => $edcdevice->v_id,'store_id'=>$edcdevice->store_id,'vu_id'=>$edcdevice->vu_id,'type' => 'Add');
        $result = event(new EdcLog($edcdata));  //Event capture for vendor login

        return response()->json(['status' => 'success', 'message' => 'Edc '.$msg.' Successfully`'], 200);
    } //End add function

    public function view(Request $request){
        $edcdevice = EdcDevice::where('serial_number',$request->id)->orWhere('udid',$request->id)->first();
        if($edcdevice){
        return response()->json(['status' => 'success', 'data' => $edcdevice], 200);
        }else
        {
            return response()->json(['status' => 'fail', 'message' => 'No Edc Device Found'], 200);
        }
    }//End view

    public function updateserialnumber(Request $request){
        
        /*$serialUnique  = Validator::make($request->all(), [
            'serial_number'   => 'required|unique:edc_devices,serial_number' 
        ]);
        if($serialUnique->fails()){
            return response()->json(['status' => 'fail', 'message' => 'The serial number has already been taken.' ], 200);
        }*/
         
        $edcdevice = EdcDevice::where('udid',$request->udid)->first();
        if($edcdevice){
            $edcdevice->serial_number= $request->serial_number;
            $edcdevice->save();
            return response()->json(['status' => 'success','message' => 'Edc Update Successfully', 'data' => $edcdevice], 200);
        }else{
            return response()->json(['status' => 'fail', 'message' => 'No Edc Device Found For This Udid'], 200);
        }
    }


    public function updateUdid(Request $request){
        $edcdevice = EdcDevice::where('serial_number',$request->serial_number)->first();
        if($edcdevice){
            $edcdevice->udid        = $request->udid;
            $edcdevice->v_id        = $request->v_id;
            $edcdevice->store_id    = $request->store_id;
            $edcdevice->udid_update_status    = 1;
            $edcdevice->save();
            $edcdata = array('udid' => $edcdevice->udid,'serial_number'=>$edcdevice->serial_number,'bluetooth_id'=>$edcdevice->bluetooth_id,'edc_type' => $edcdevice->edc_type,'username'=>$edcdevice->username,'password'=>$edcdevice->password,'v_id' => $edcdevice->v_id,'store_id'=>$edcdevice->store_id,'vu_id'=>$request->vu_id,'type' => 'Update');
            $result = event(new EdcLog($edcdata));  //Event capture for vendor login
            return response()->json(['status' => 'success','message' => 'Edc Update Successfully', 'data' => $edcdevice], 200);
        }else{
            return response()->json(['status' => 'fail', 'message' => 'No Serial Number Found'], 200);
        }
    }

   public function getAllEdcDeviceList(Request $request){
  
      $orglists=Organisation::select('id','name')->where('deleted',0)->get();
     $data = [];
    
       foreach($orglists as $orglist)
       {
          if($orglist->edcDevice->count()>0)
              $data[]  = array(
                             'v_id' => $orglist->id,
                             'name' =>$orglist->name,
                             'list'=>$orglist->edcDevice
                       );
                    }
                    

        //dd($data);

       return response()->json(['status' => 'success','message' => 'Edc Device List', 'data' => $data], 200);
      //dd($data);
 }






}
