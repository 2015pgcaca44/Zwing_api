<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use App\Vendor;
use App\CashRegister;
//use App\Subscriptions;
use Auth;
class LicenceDetailController extends Controller
{

    public function __construct()
	{
		$this->middleware('auth');
	}

    public function getDevicesLicenceDetail(Request $request){

        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $udidtoken = $request->udidtoken;
       // DB::enableQueryLog();
        $cash = CashRegister::join('subscriptions','cash_registers.id','subscriptions.cr_id')
                                 ->select(DB::raw('cash_registers.name,cash_registers.udid,cash_registers.licence_no,subscriptions.purchase_date,subscriptions.exp_date,DATEDIFF(subscriptions.exp_date,subscriptions.purchase_date) as total_days,DATEDIFF(subscriptions.exp_date,CURDATE()) as r_days'))
                                 ->where('cash_registers.v_id',$v_id)
                                 ->where('cash_registers.store_id',$store_id)
                                 ->where('cash_registers.udidtoken',$udidtoken) 
                                  ->orderby('subscriptions.purchase_date','desc')
                                 ->first();
       /// dd(DB::getQueryLog());                      
         if(!empty($cash)){
                   if($cash->r_days<=5){
                    $warning = 'Your license will expire soon.Please contact support for renewal.';
                   }else{
                    $warning = '';  
                   }
                $data = array(
                               'name' =>$cash->name,
                               'licence_no'=> $this->l_Formate($cash->licence_no),
                               'device_address'=>$cash->udid,
                               'purchase_date'=>date("d-F-Y", strtotime($cash->purchase_date)),
                               'expiry_date'=>date("d-F-Y", strtotime($cash->exp_date)),
                               'days_left' =>$cash->r_days,
                               'percentage' =>$this->countPercentage($cash->total_days,$cash->r_days),
                               'warning' =>$warning
                               ); 
                               return response()->json(['status' => 'success', 'message' => 'Licence details', 'data' => $data], 200);             
         }else{
          return response()->json(['status' => 'licence_not_valid', 'message' =>  'Invaild Licence No.'], 420);
         }                        
  
    }
  
    Protected function l_Formate($number,$char= 'X'){
     $str = substr($number,0,3).str_repeat($char, strlen($number)-6).substr($number,-3);
     $newstr = '';
     for($i=0; $i< strlen($str); $i++){
     $newstr.= $str[$i];
     if(($i+1) % 3 ==0){
       $newstr.= '-';
      }    
     }
     return rtrim($newstr,'-');
    }
  
    Protected function countPercentage($total_days,$r_day){
  
       if($r_day>0){
        $per = ($r_day*100/($total_days));
        $percentage =(int)$per;
       }else{
        $percentage = 0;  
       }
  
      return $percentage;
  
    }

    public function  revokeLicence(Request $request){

        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $udidtoken = $request->udidtoken;
        $licence_no  = $request->licence;
        
        $cash = CashRegister::where('v_id',$v_id)
                             ->where('store_id',$store_id)
                             ->where('licence_no',$licence_no) 
                             ->where('udidtoken',$udidtoken)->first();
     if(!empty($cash)){ 
        $revoke = CashRegister::where('licence_no', $licence_no)
        ->update([
          'udid' =>NULL,
          'udidtoken' => NULL,
        ]); 

      if(config('database.default') != 'mysql') {
        DB::connection('mysql')->table('cash_registers')->where('licence_no', $licence_no)->update([
          'udid' =>NULL,
          'udidtoken' => NULL,
        ]);
      }
        if($revoke){
            return response()->json(['status' => 'success', 'message' => 'Device revoked successfully.'], 200);
        }else{
         
            return response()->json(['status' => 'fail', 'message' => 'An error occured Plz try again!'], 200);

        }          
    }else{

        return response()->json(['status' => 'fail', 'message' => 'Invalid Licence No.'], 200);
      
    }
}
}