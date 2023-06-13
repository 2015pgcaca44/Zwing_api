<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use DB;
use App\Vendor;
use App\CashRegister;
use App\Organisation;

class CashRegisterTagController extends Controller
{

  public function cash_register_tag(Request $request)
  {

    $this->validate($request, [
      'udid' => 'required',
      'licence_no' => 'required'
    ]);
    $udid        =  $request->udid;
    $licence_no  =  $request->licence_no;
  if(env('APP_ENV') == "development"){
    if ($licence_no == 'm27tE7k39@Zg') {

      return response()->json(['status' => 'success', 'message' => 'Tagged successfully to cash-register.', 'udidtoken' => 'GT6A7lWWdkjCilh9jKtm7Yc9'], 200);
    }
  }

    $existLicense = CashRegister::select('licence_no', 'udidtoken','v_id','store_id')
      ->where('licence_no', $licence_no)
      ->first();
    if($existLicense){
      $organisation = Organisation::find($existLicense->v_id);
      if($organisation->db_type == 'MULTITON'){
        //dynamicConnection($organisation->db_name);
        $connPrm    =array('host'=> $organisation->connection->host,'port'=>$organisation->connection->port,'username'=>$organisation->connection->username,'password' =>  $organisation->connection->password,'db_name'=>$organisation->db_name);
        dynamicConnectionNew($connPrm);
      }
    }


    $licence = CashRegister::select('licence_no', 'udidtoken','v_id','store_id')
      ->where('licence_no', $licence_no)
      ->where('udid', $udid)
      ->where('is_deleted', '!=', '1')
      ->first();

    $vendorC  = new VendorController;
    

    //dd($licence);
    if (!empty($licence)) {

    $crparams = array('v_id'=>$licence->v_id,'store_id'=>$licence->store_id,'vu_id'=>1,'info_type'=>'COUNTRY');
    $country_details = $vendorC->getCurrencyDetail($crparams); 
      // dd($licence);
      //$udidtoken =encrypt_decrypt('encrypt', $udid);
      $udidtoken = Hash::make($udid);
      $tag = CashRegister::where('licence_no', $licence_no)
        ->update([
          'udid' => $udid,
          'udidtoken' => $udidtoken
        ]);

      if(config('database.default') == 'dynamic'){
        $tag_mysql = DB::connection('mysql')->table('cash_registers')->where('licence_no', $licence_no)
        ->update([
          'udid' => $udid,
          'udidtoken' => $udidtoken
        ]);
      }

      return response()->json(['status' => 'success', 'message' => 'Tagged successfully to cash-register.', 'udidtoken' => $udidtoken,'country_details'=>$country_details], 200);
    } elseif ($this->isExistsudid($udid)) {
      return response()->json(['status' => 'fail', 'message' => 'This device is already registered with another license key.'], 200);
    } else {

      $lno = CashRegister::where('licence_no', $licence_no)
                        ->where('is_deleted', '!=', '1')
                        ->first();
      if (is_null($lno)) {

        return response()->json(['status' => 'fail', 'message' => 'Invaild Licence No.'], 200);
      } else {

        $licno = CashRegister::select('licence_no')
          ->where('licence_no', $licence_no)
          ->whereNotNull('udid')
          ->where('is_deleted', '!=', '1')
          ->first();

        if (is_null($licno)) {
          //$udidtoken =encrypt_decrypt('encrypt', $udid);
          $udidtoken = Hash::make($udid);
          $tag = CashRegister::where('licence_no', $licence_no)
            ->update([
              'udid' => $udid,
              'udidtoken' => $udidtoken
            ]);

        if(config('database.default') == 'dynamic'){
          $tag_mysql = DB::connection('mysql')->table('cash_registers')->where('licence_no', $licence_no)
                  ->update([
                    'udid' => $udid,
                    'udidtoken' => $udidtoken
                    ]);
        }



        $crparams = array('v_id'=>$lno->v_id,'store_id'=>$lno->store_id,'vu_id'=>1,'info_type'=>'COUNTRY');
        $country_details = $vendorC->getCurrencyDetail($crparams);
          return response()->json(['status' => 'success', 'message' => 'Tagged successfully to cash-register.', 'udidtoken' => $udidtoken,'country_details'=>$country_details], 200);
        } else {

          return response()->json(['status' => 'fail', 'message' => 'The license you’ve entered is already in use.'], 200);
        }
      }
    }
  }


  function isExistsudid($number)
  {

    return CashRegister::where('udid', $number)
      ->where('is_deleted', '!=', '1')
      ->exists();
  }


 
}
