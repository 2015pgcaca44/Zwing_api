<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Hash;
use Auth;
use App\Payment;
use App\Order;
use App\Invoice;
use App\Vendor;
use App\VendorImage;
use App\Store;
use App\SettlementSession;
use App\SettlementSessionsCurrency;
use App\LoginSession;
use App\Http\CustomClasses\PrintInvoice;
use App\Http\Controllers\CloudPos\CartController;
use App\Http\Controllers\CartController as MainCart;
use App\Http\Controllers\VendorSettingController;
use App\Http\Controllers\CashManagementController;
use App\Vendor\VendorRoleUserMapping;
use App\Model\Payment\Mop;
use App\CashRegister;
use App\CashPoint;
use App\Organisation;
use App\CashPointSummary;
use App\CashTransaction;
use App\CashTransactionLog;
use App\VendorSetting;
use App\Http\Controllers\VendorController;

class VendorSettlementController extends Controller
{

 public function __construct()
  {
   
    $this->vendorS  = new VendorSettingController;
  }  

  public function cash_status(Request $request){
    //date_default_timezone_set('Asia/Kolkata');
    $vu_id =  $request->vu_id;
    $trans_from = 'ANDROID_VENDOR';
    if($request->has('trans_from')){
      $trans_from = $request->trans_from;
    }
    $vendorUser = Vendor::select('v_id','store_id')->where('id' , $vu_id)->first();
    $role = VendorRoleUserMapping::select('role_id')->where('user_id',$vu_id)->first();
    $v_id = $vendorUser->v_id;
    $store_id = $vendorUser->store_id;

    $sParams = ['v_id' => $v_id,'store_id'=>$store_id,'user_id'=>$vu_id,'role_id'=>$role->role_id,'trans_from' => $trans_from];

    $vendorS = new VendorSettingController;
    $paymentTypeSettings = $vendorS->getPaymentTypeSetting($sParams);
    $cash_status = 0;
    foreach($paymentTypeSettings as $type){
      if($type->name == 'cash' && $type->status == 1){
        $cash_status = 1;
      }
    }
    return [ 'v_id' => $v_id, 'store_id' => $store_id,'vu_id' => $vu_id ,'cash_status' => $cash_status ,'trans_from' => $trans_from ];

  }

  public function opening_balance_status(Request $request){
      //date_default_timezone_set('Asia/Kolkata');
    $opening_flag = $this->opening_balance_flag($request);

    if( is_array($opening_flag) ){

    }else{
      $user_id = null;
      if(!empty($request->user_id) ){
        $user_id = $request->user_id;
      }else if(!empty($request->vu_id)){
        $user_id = $request->vu_id;
      }

          //echo 'inside this';exit;
      $role = VendorRoleUserMapping::select('role_id')->where('user_id', $user_id)->first();
      $role_id  = $role->role_id;
      $vendorS = new VendorSettingController;
        $sParams = ['v_id' => $request->v_id,'store_id'=>$request->store_id,'user_id'=>$user_id,'role_id'=>$role_id,'trans_from' => $request->trans_from];
      $open_sesssion_compulsory = $vendorS->getSessionCompulsorySettingFunction($sParams);
      if($open_sesssion_compulsory->status != 0)
      {
        return response()->json([ 'status' => 'add_opening_balance', 'message' => 'Opening Balance is not entered'],200);
      }

    }

  }

  public function opening_balance_flag(Request $request){

      //date_default_timezone_set('Asia/Kolkata');
    //dd($request->all());
      $cash_r = $this->cash_status($request);
  
        $cash_status =  '1';//$cash_r['cash_status'] ; 
        $settlementSession = 0;
        if($cash_status){
          $current_date = date('Y-m-d'); 
          if($request->has('settlement_session_id') && $request->settlement_session_id > 0){
           $settlementSession = SettlementSession::select('opening_balance','closing_balance','opening_time','closing_time')->where('id', $request->settlement_session_id )->first();

         }else{

           $settlementSession = SettlementSession::select('opening_balance','closing_balance','opening_time','closing_time','trans_from')->where(['v_id' => $cash_r['v_id'] ,'store_id' => $cash_r['store_id'] , 'vu_id' => $cash_r['vu_id'] , 'type' => 'CASH' , 'trans_from' => $cash_r['trans_from'] , 'settlement_date' => $current_date ])->orderBy('opening_time','desc')->first();
           //dd($settlementSession);
         }

         $opening_flag = 0;
         if($settlementSession){
           if(empty($settlementSession->opening_balance) ||  $settlementSession->opening_balance == '' || $settlementSession->opening_balance == null){
            
            $opening_flag = 0;
            //dd('abc');
          }else{
            //dd($settlementSession->closing_balance);
            if($settlementSession->closing_balance==null|| $settlementSession->closing_balance == '' || $settlementSession->closing_time==null){
            if($settlementSession->partant_session_id==null){

              $opening_flag = [ 'opening_flag' => 1, 'opening_time' => $settlementSession->opening_time , 'trans_from' => $settlementSession->trans_from ];
              }else{
                $settlement =SettlementSession::where('partant_session_id',$settlementSession->partant_session_id)->first();
                $opening_flag = [ 'opening_flag' => 1, 'opening_time' => $settlement->opening_time , 'trans_from' => $settlement->trans_from ];
              }
           }else{
            $opening_flag = 0;
          }
        }
      }else{
       $opening_flag = 0;
      }

      return $opening_flag;
    }

  }

  public function closing_balance_flag(Request $request){      
    
    $cash_r       = $this->cash_status($request);
    $cash_status  = '1';
    $closing_flag =  1;
    
        if($cash_status)
        {  
           $settlementSession = SettlementSession::where('v_id',$cash_r['v_id'])
                                               ->where('store_id',$cash_r['store_id'])
                                               ->where('vu_id',$cash_r['vu_id'])
                                               ->where('type','CASH')
                                               ->where('trans_from',$cash_r['trans_from'])
                                               ->orderBy('id','desc')
                                               ->first(); 

         if(!empty($settlementSession) && $settlementSession->partant_session_id!=null && $settlementSession->closing_time==null)
         {

             $partantSession=SettlementSession::where('partant_session_id',$settlementSession->partant_session_id)
                              ->where('store_id' , $settlementSession->store_id)
                              ->where('vu_id' , $settlementSession->vu_id)
                              ->where('type', 'CASH')
                              ->where('trans_from',$settlementSession->trans_from)
                              ->first();
            if($partantSession)
            {   
              $current_date = date_create(date('Y-m-d'));
              $settlement_date = date_create($partantSession->settlement_date);
              $interval = $settlement_date ->diff($current_date);
              $currentSessionday = $interval->format('%a');
              $role_id = getRoleId($request->vu_id);
              $sParams =['v_id' => $request->v_id, 'store_id' => $request->store_id, 'user_id' => $request->vu_id, 'role_id' => $role_id, 'trans_from' =>$request->trans_from,'udidtoken'=>$request->udidtoken];
              $maxActiveSessionDay=$this->vendorS->getActiveSessionday($sParams);
             if($currentSessionday>$maxActiveSessionDay)
             {
              $closing_flag = [ 'closing_flag' =>0, 'settlement_session_id'=>$settlementSession->id]; 
             }else
             {
              $closing_flag=1;
             }
            }else
            {
              $closing_flag=1;
            }                                           
         }elseif(!empty($settlementSession) && $settlementSession->partant_session_id==null && $settlementSession->closing_time==null){

              $current_date = date_create(date('Y-m-d'));
              $settlement_date = date_create($settlementSession->settlement_date);
              $interval = $settlement_date ->diff($current_date);
              $currentSessionday = $interval->format('%a');
              $role_id = getRoleId($request->vu_id);
              $sParams =['v_id' => $request->v_id, 'store_id' => $request->store_id, 'user_id' => $request->vu_id, 'role_id' => $role_id, 'trans_from' =>$request->trans_from,'udidtoken'=>$request->udidtoken];
              $maxActiveSessionDay=$this->vendorS->getActiveSessionday($sParams);
              if($currentSessionday>$maxActiveSessionDay)
             {
              $closing_flag = [ 'closing_flag' =>0, 'settlement_session_id'=>$settlementSession->id]; 
             }
         }else
          {
            $closing_flag=1;
          }
      } 

     return $closing_flag;
    }

    public function closing_balance_status(Request $request){
      //date_default_timezone_set('Asia/Kolkata');
      $cash_r = $this->cash_status($request);
      $cash_status =  $cash_r['cash_status'] ;
      if($cash_status){

        $current_date = date('Y-m-d' , strtotime('-1 days'));
        $settlementSession = SettlementSession::select('closing_balance')->where(['v_id' => $cash_r['v_id'] ,'store_id' => $cash_r['store_id'] , 'vu_id' => $cash_r['vu_id'] , 'type' => 'CASH' , 'trans_from' => $cash_r['trans_from'] , 'settlement_date' => $current_date ])->first();
            // $settlementSession = SettlementSession::select('closing_balance')->where(['v_id' => $cash_r['v_id'] ,'store_id' => $cash_r['store_id'] , 'vu_id' => $cash_r['vu_id'] , 'type' => 'CASH' , 'settlement_date' => $current_date ])->first();
        if($settlementSession){
          if(empty($settlementSession->closing_balance) ||  $settlementSession->closing_balance = '' || $settlementSession->closing_balance == null){
            return response()->json([ 'status' => 'add_closing_balance', 'message' => 'Your Settlement is pending'], 200);
          }
        }else{

          $role = VendorRoleUserMapping::select('role_id')->where('user_id', $request->user_id)->first();
          $role_id  = $role->role_id;
          $vendorS = new VendorSettingController;
            $sParams = ['v_id' => $request->v_id,'store_id'=>$request->store_id,'user_id'=>$request->user_id,'role_id'=>$role_id,'trans_from' => $request->trans_from];
          $open_sesssion_compulsory = $vendorS->getSessionCompulsorySettingFunction($sParams);
          if($open_sesssion_compulsory->status != 0)
          {
            return response()->json([ 'status' => 'add_opening_balance', 'message' => 'Opening Balance is not entered'],200);
          }
        }
      }
    }

    public function opening_balance(Request $request)
    {
      $v_id = $request->v_id;
      $store_id = $request->store_id;
      $vu_id = $request->vu_id;
      $type = $request->type;
      $trans_from = $request->trans_from;
      $udidtoken  = $request->udidtoken;
      $opening_balance = $request->opening_balance;
      $settlement_date = $request->settlement_date;
    // if ($request->has('terminal_id')) {
    //  $terminal_id = $request->terminal_id;
    // }

      if(empty($opening_balance) || $opening_balance == 0){
         return response()->json([ 'status' => 'fail', 'message' => 'Opening stock amount cannot be 0'],200); 
      }
      $terminal_id = CashRegister::select('id')->where('udidtoken',$udidtoken)->first();        
      $role_id = getRoleId($vu_id);
      $params  = array('v_id'=>$v_id,
        'store_id'=>$store_id,
        'name' =>'store',
        'user_id'=>$vu_id,
        'role_id'=>$role_id,
        'udidtoken' =>$udidtoken,
      );
      $setting  = new VendorSettingController;
      $storeSetting = $setting->getSetting($params)->pluck('settings')->toArray();
      $param =$params;
      $param['name']='settlement';
      $settlementSetting = $setting->getSetting($param)->pluck('settings')->toArray();
       $settlementSettings = json_decode($settlementSetting[0]);
      $denomination_status = isset($settlementSettings->denomination)&&$settlementSettings->denomination->DEFAULT->status=='1'?'1':'0';
        //dd($denomination_status);
        //if(!empty($storeSetting)){

      $storeSettings = json_decode($storeSetting[0]);
      //dd($storeSettings);
            //dd($him->enable_shopping_radius->status); 



        //dd($role_id);    
    // $vSettings = VendorSetting::select('settings')->where('name', 'store')->where('v_id',$v_id)->first();
  //        $sett = json_decode($vSettings->settings);
    //abc
      if(isset($storeSettings->cashmanagement) && $storeSettings->cashmanagement->DEFAULT->status=='1'){
          //dd($storeSettings->cashmanagement->DEFAULT->status);
       if($storeSettings->cashmanagement->store_cash_negative_billing->status!='1'){
        $storecash=storeCashPoint($store_id,$v_id);
        $availableCashInstorecash=cashPointAvailableCash($v_id,$store_id,$storecash->id);

        if($availableCashInstorecash<$opening_balance){

          return response()->json([ 'status' => 'fail', 'message' => 'Insufficient cash on Store-Cash'],200); 

        }

      }
      $settlementSession = new SettlementSession;
      $settlementSession->v_id = $v_id;
      $settlementSession->store_id = $store_id;
      $settlementSession->vu_id = $vu_id;
      $settlementSession->type = $type;
      $settlementSession->trans_from = $trans_from;
      $settlementSession->settlement_date = $settlement_date;
      $settlementSession->opening_balance = $opening_balance;
      $settlementSession->opening_time = date('Y-m-d H:i:s');
      // if ($request->has('terminal_id')) {
      $settlementSession->cash_register_id =$terminal_id->id;
      $settlementSession->denomination_status= $denomination_status;
      //}
      $settlementSession->save();
      $this->cashPointSessionOpen($store_id,$v_id,$terminal_id->id,$opening_balance,$vu_id,$settlementSession->id);
      
      if($request->has('currency')){
        $currencydata  = array('currency'=>json_decode($request->currency),'v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id,'settlement_id'=>$settlementSession->id, 'settlement_sessions_type' => 'OPEN');
        $this->addCurrency($currencydata);
      }

      return response()->json(['status' => 'success' , 'message' => 'Opening Balance added Succesfully', 'data' => $settlementSession ]);

    }
       //dd("cashsettingoff");
       //}

    else{
        //dd("ok");
        //dd($terminal_id);
        //dd($terminal_id->id);
      $settlement_date = date('Y-m-d');
    // dd($request->all());
      $settlementSession = SettlementSession::where(['v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id , 'type' => $type , 'trans_from' => $trans_from , 'settlement_date' => $settlement_date ])->
    // $settlementSession = SettlementSession::where(['v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id , 'type' => $type , 'settlement_date' => $settlement_date ])->
      orderBy('updated_at','desc')->first();

    //$this->cashPointSessionOpen($store_id,$v_id,$terminal_id,$opening_balance,$settlementSession->id);  

      if($settlementSession){

       if($settlementSession->closing_balance = '' || $settlementSession->closing_balance == null){
        $settlementSession->opening_balance = $opening_balance;
        $settlementSession->opening_time = date('Y-m-d H:i:s');
        $settlementSession->save();

        return response()->json(['status' => 'success' , 'message' => 'Opening Balance Updated Succesfully' ]);

      }else{

        $settlementSession = new SettlementSession;
        $settlementSession->v_id = $v_id;
        $settlementSession->store_id = $store_id;
        $settlementSession->vu_id = $vu_id;
        $settlementSession->type = $type;
        $settlementSession->trans_from = $trans_from;
        $settlementSession->settlement_date = $settlement_date;
        $settlementSession->opening_balance = $opening_balance;
        $settlementSession->opening_time = date('Y-m-d H:i:s');
        // if ($request->has('terminal_id')) {
        $settlementSession->cash_register_id = $terminal_id->id;
        $settlementSession->denomination_status= $denomination_status;
        // /}
        $settlementSession->save();

        if($request->has('currency')){
         $currencydata  = array('currency'=>json_decode($request->currency),'v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id,'settlement_id'=>$settlementSession->id, 'settlement_sessions_type' => 'OPEN');
         $this->addCurrency($currencydata);
       }

       return response()->json(['status' => 'success' , 'message' => 'Opening Balance added Succesfully', 'data' => $settlementSession ]);

     }

     return response()->json(['status' => 'success' , 'message' => 'Opening Balance Updated Succesfully' ]);

   }else{

     $settlementSession = new SettlementSession;
     $settlementSession->v_id = $v_id;
     $settlementSession->store_id = $store_id;
     $settlementSession->vu_id = $vu_id;
     $settlementSession->type = $type;
     $settlementSession->trans_from = $trans_from;
     $settlementSession->settlement_date = $settlement_date;
     $settlementSession->opening_balance = $opening_balance;
     $settlementSession->opening_time = date('Y-m-d H:i:s');
      // if ($request->has('terminal_id')) {
     $settlementSession->cash_register_id =$terminal_id->id;
     $settlementSession->denomination_status= $denomination_status;
      //}
     $settlementSession->save();

     if($request->has('currency')){
      $currencydata  = array('currency'=>json_decode($request->currency),'v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id,'settlement_id'=>$settlementSession->id, 'settlement_sessions_type' => 'OPEN');
      $this->addCurrency($currencydata);
    }

    return response()->json(['status' => 'success' , 'message' => 'Opening Balance added Succesfully', 'data' => $settlementSession ]);

  }
}

}


public function closing_balance(Request $request)
{

  if(Auth::user()->cash_management['status']) {
    $newCashMan = new CashManagementController;
    $currentSessionList = $newCashMan->getCurrentTransactions($request);
    $currentSessionList = collect($currentSessionList)->pluck('id');
    if($currentSessionList->count() > 0) {
      $message = count($currentSessionList)." pending cash management transaction(s) found.<br /><br />Tap 'Continue' to close the active session and cancel all pending cash transaction(s).";
      return response()->json([ 'status' => 'warning', 'ids' => $currentSessionList, 'message' => $message ]);
    }
  }
  //date_default_timezone_set('Asia/Kolkata');
  $v_id = $request->v_id;
  $store_id = $request->store_id;
  $vu_id = $request->vu_id;
  $type = $request->type;
  $trans_from = $request->trans_from;
  $closing_balance = $request->closing_balance;
  $settlement_date = $request->settlement_date;

  $settlement_session_id = 0;
  if($request->has('settlement_session_id')){
     $settlement_session_id = $request->settlement_session_id;
  }
    //Checking if Opening balance is done or not
   $this->opening_balance_status($request);

    //'settlement_date' => $settlement_date
  if($settlement_session_id > 0){

    $settlementSession =  SettlementSession::find($settlement_session_id);
  }else{  
    $settlementSession = SettlementSession::where(['v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id , 'type' => $type , 'trans_from' => $trans_from  ])->orderBy('id','desc')->first();
  }

  $role_id = getRoleId($vu_id);
  $params  = array('v_id'=>$v_id,'store_id'=>$store_id,'name' =>'store','user_id'=>$vu_id,'role_id'=>$role_id);
  $setting  = new VendorSettingController;
  $storeSetting = $setting->getSetting($params)->pluck('settings')->toArray();
  $storeSettings = json_decode($storeSetting[0]);
  if(isset($storeSettings->cashmanagement) && $storeSettings->cashmanagement->DEFAULT->status=='1')
  {
      $current_date = date('Y-m-d');
      $settlementSession->settlement_date;
      if($settlementSession->settlement_date==$current_date){
          $cashmanagement  = new CashManagementController;

          $cashPointCash = $cashmanagement->closeSession($store_id,$v_id,$settlementSession->cash_register_id,$vu_id,$settlementSession->id,$closing_balance);
          $overOrShort = '';
          $overOrShort  = (float)$cashPointCash['totalamount'] -(float)$closing_balance;
          if($overOrShort < 0){
            $overOrShortS = '('.format_number($overOrShort).')';
          }else{
           $overOrShortS = format_number($overOrShort);
          }     
         $settlementSession->closing_balance = $closing_balance;
         $settlementSession->closing_time = date('Y-m-d H:i:s');
         $settlementSession->status = '1';
         $settlementSession->short_access=$overOrShortS;
         $settlementSession->session_close_type="REGULAR";
         $settlementSession->save();

         if($request->has('currency')){
           $currencydata  = array('currency'=>json_decode($request->currency),'v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id,'settlement_id'=>$settlementSession->id, 'settlement_sessions_type' => 'CLOSE');
           $this->addCurrency($currencydata);
         }
        return response()->json(['status' => 'success' , 'message' => 'Balance  added Succesfully' ]);  
      }else{
         return response()->json([ 'status' => 'fail', 'message' => 'This store has enabled cash mangement.Please contact store manager to closed your previous session.'],200); 
      }
  }else{

      $payments = DB::table('payments as p')
                   ->select(DB::raw('p.amount, CAST(p.cash_collected as decimal) as cash_collected,CAST( p.cash_return as decimal) as cash_return, p.method'))
                   ->join('orders as o', 'o.order_id' , 'p.order_id')
                   ->where('o.date', date('Y-m-d'))
                   ->where('p.session_id',$settlementSession->id)
                   ->where('o.vu_id', $vu_id)
                   ->where('o.created_at','>=',$settlementSession->opening_time)
                   ->where('o.created_at','<=',date('Y-m-d H:i:s'))
                   ->get();
      $tender = $payments->sum('cash_collected');
      $refund = $payments->sum('cash_return');     
      $opening_balance = (float) $settlementSession->opening_balance;
      $closing_balance = (float) $closing_balance;

      $overOrShortS = '';
      $overOrShort = $closing_balance - ($opening_balance + ($tender - $refund));
         if($overOrShort < 0){
           $overOrShortS = '('.format_number($overOrShort).')';
         }else{
           $overOrShortS = format_number($overOrShort);
         }  
      $settlementSession->closing_balance = $closing_balance;
      $settlementSession->closing_time = date('Y-m-d H:i:s');
      $settlementSession->status = '1';
      $settlementSession->short_access=$overOrShortS;
      $settlementSession->session_close_type="REGULAR";
      $settlementSession->save();
    if($request->has('currency')){
       $currencydata  = array('currency'=>json_decode($request->currency),'v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id,'settlement_id'=>$settlementSession->id, 'settlement_sessions_type' => 'CLOSE');
       $this->addCurrency($currencydata);
   }
    return response()->json(['status' => 'success' , 'message' => 'Balance  added Succesfully' ]);
  }
}


private function addCurrency($request){
    //print_r($request['settlement_id']);die;
  if(count($request['currency']) > 0 ){
   // $checkCurreny = SettlementSessionsCurrency::where(['v_id' => $request['v_id'] ,'store_id' => $request['store_id'] ,'vu_id' => $request['vu_id'],'settlement_id' =>$request['settlement_id'] ])->delete();
   foreach($request['currency'] as $item){
    $total =  $item->currency*$item->qty;
    $data  =  array('v_id' => $request['v_id'] ,'store_id' => $request['store_id'] , 'vu_id' => $request['vu_id'],'settlement_id'=>$request['settlement_id'],'currency_type'=>$item->currency_type,'currency'=>$item->currency,'qty'=>$item->qty,'total'=>$total, 'settlement_session_type' => $request['settlement_sessions_type']);
    $settlementCurreny = SettlementSessionsCurrency::create($data);
  }
}
}


public function print_settlement_old(Request $request){
  //date_default_timezone_set('Asia/Kolkata');
  $v_id = $request->v_id;
  $store_id = $request->store_id;
  $vu_id = $request->vu_id;
  $settlement_session_id = 0;
  if($request->has('settlement_session_id')){
   $settlement_session_id = $request->settlement_session_id;
 }

 $trans_from = '';
 if($request->has('trans_from')){
   $trans_from = $request->trans_from; 
 }

 $current_date = date('Y-m-d');

 if($settlement_session_id > 0){
   $settlementS = SettlementSession::select('id','opening_balance','closing_balance','opening_time','closing_time','created_at','updated_at','settlement_date')->where('id',$settlement_session_id)->first();
   $current_date = $settlementS->settlement_date;
 }else{

        //$settlementS = SettlementSession::select('id','opening_balance','closing_balance','opening_time','closing_time','created_at','updated_at')->where('vu_id',$vu_id)->where('v_id' , $v_id)->where('store_id' , $store_id)->where('settlement_date', $current_date)->orderBy('opening_time','desc')->first();

  $settlementS = SettlementSession::select('id','opening_balance','closing_balance','opening_time','closing_time','created_at','updated_at')->where('vu_id',$vu_id)->where('v_id' , $v_id)->where('store_id' , $store_id)->where('settlement_date', $current_date)->orderBy('id','desc')->first();
}

$vendor = Vendor::select('first_name','last_name','mobile')->where('id', $vu_id)->first();


        //$current_date = '2018-09-14';
$payments = DB::table('payments as p')
->select(DB::raw('p.amount, CAST(p.cash_collected as decimal) as cash_collected,CAST( p.cash_return as decimal) as cash_return, p.method'))
->join('orders as o', 'o.order_id' , 'p.order_id')
->where('o.date', $current_date)
->where('o.vu_id', $vu_id)
->where('o.created_at','>=',$settlementS->opening_time)
->where('o.created_at','<=',$settlementS->closing_time)
          //->groupBy('p.method')
->get();

$payments_by_method = DB::table('payments as p')
->select(DB::raw('sum(p.amount) as amount, p.method , count(*) as count'))
->join('orders as o', 'o.order_id' , 'p.order_id')
->where('o.date', $current_date)
->where('o.vu_id', $vu_id)
->where('o.created_at','>=',$settlementS->opening_time)
->where('o.created_at','<=',$settlementS->closing_time)
->groupBy('p.method')
->get();
$mop_summary_count=[] ;
$mop_summary_rs=[];
foreach($payments_by_method as $pay){
 $method = ucfirst(str_replace('_',' ', $pay->method));
 $mop_summary_count[] = ['name' => $method , 'value' => $pay->count ] ;
 $mop_summary_rs[] = ['name' => $method , 'value' => $pay->amount ] ;
}

    //dd($payments_by_method);



    //$payments = Payment::select('amount','method','cash_collected','cash_return')->where('date',$current_date)->where('vu_id',$vu_id)->get();
$tender = $payments->sum('cash_collected');
$refund = $payments->sum('cash_return');

    //$orders = Order::select('total')->where('date',$current_date)->where('vu_id',$vu_id)->get();
$orders = Order::select('total')
    //->where('transaction_type','sales')
->where('date',$current_date)
->where('created_at','>=',$settlementS->opening_time)
->where('created_at','<=',$settlementS->closing_time)
->where('vu_id',$vu_id)
->get();

if($orders->isEmpty()){
 return response()->json(["status" => 'fail' , 'message'=> 'No Order found']);
}
$order_count = $orders->count();
$order_sum = $orders->sum('total');



$terminal_summary = [];
$cash_summary = [];

$loginS = LoginSession::select('device_id')->where('vu_id',(int)$vu_id)->where('v_id' , (int)$v_id)->where('store_id' , (int)$store_id)->orderBy('id','desc')->first();

if(!$settlementS){
 return response()->json(["status" => 'fail' , 'message'=> 'No Session found']);
}

$terminal_summary[] = ['name' => 'Terminal Name' , 'value' => $loginS->device_id] ;
$terminal_summary[] = ['name' => 'Report Time' , 'value' => 'Print Date '.date('d-M-Y') ]  ;
$terminal_summary[] = ['name' => 'Session ID' , 'value' => (string)$settlementS->id] ;
$terminal_summary[] = ['name' => 'Open On' , 'value' => (string)$settlementS->opening_time]  ;
$terminal_summary[] = ['name' => 'Close On' , 'value' => (string)$settlementS->closing_time ] ; 
$terminal_summary[] = ['name' => 'Total Sale Value' , 'value' => (string)$order_sum , 'bold' => 1] ; 
$terminal_summary[] = ['name' => 'Total Bill Count' , 'value' => (string)$order_count ,'bold' => 1 ]; 

$opening_balance = (float) $settlementS->opening_balance;
$closing_balance = (float) $settlementS->closing_balance;

$overOrShortS = '';
$overOrShort = $closing_balance - ($opening_balance + ($tender - $refund));
if($overOrShort < 0){
 $overOrShortS = '('.format_number($overOrShort).')';
}else{
 $overOrShortS = format_number($overOrShort);
}

$cash_summary[] = ['name' => 'Opening (A)' , 'value' => (string)$settlementS->opening_balance] ;
$cash_summary[] = ['name' => 'Tender (B)' , 'value' => (string)$tender ]  ;
$cash_summary[] = ['name' => 'Change (C)' , 'value' => (string)$refund ] ;
$cash_summary[] = ['name' => 'Refund ' , 'value' => '' ];
$cash_summary[] = ['name' => 'Cash Total (D=B-C)' , 'value' => format_number($tender-$refund )] ;
$cash_summary[] = ['name' => 'Closing (E) ' , 'value' => $settlementS->closing_balance ] ; 
$cash_summary[] = ['name' => 'Over/short(E-A-D)' , 'value' => $overOrShortS ,'bold' => 1  ] ; 

$data['print_header'] =  "Cash Summary Report";
$data['cashier_name'] = "Cashier Name: ".$vendor->first_name.' '.$vendor->last_name.' \n'."Mobile: ".$vendor->mobile;

$data['body'][] = [ 'header' => [ 'left_text' => 'Terminal Summary' , 'right_text' => '' ], 'body' => $terminal_summary ];
$data['body'][] = [ 'header' => [ 'left_text' => 'Cash Summary' , 'right_text' => 'In Rs' ], 'body' => $cash_summary ];
$data['body'][] = [ 'header' => [ 'left_text' => 'MOP Summary' , 'right_text' => 'Count' ], 'body' => $mop_summary_count ];
$data['body'][] = [ 'header' => [ 'left_text' => 'Mop Summary' , 'right_text' => 'In Rs.' ], 'body' => $mop_summary_rs ];

return response()->json(['status' => 'success' , 'print_count' => 1 , 'data' => $data ],200);

}

public function print_settlement(Request $request){


    $v_id = $request->v_id;
    $store_id = $request->store_id;
    $vu_id = $request->vu_id;
    $settlement_session_id = 0;
    $udidtoken=$request->udidtoken;
    $mop_summary_count = [];
    $mop_summary_rs = [];
    $vendorC  = new VendorController;
    $tender  = 0;
    $crparams = array('v_id'=>$v_id,'store_id'=>$store_id,'vu_id'=>1);
    $currency = $vendorC->getCurrencyDetail($crparams);
    $currencyR = explode(' ', $currency['name']);

    if($currencyR > 1){
        $len = count($currencyR);
        $currencyName = $currencyR[$len-1];
    }else{
        $currencyName  =  $currencyR ;
    }
   if($request->has('settlement_session_id')){
    $settlement_session_id = $request->settlement_session_id;
    }
    $trans_from = '';
   if($request->has('trans_from')){
     $trans_from = $request->trans_from; 
   }
   $current_date = date('Y-m-d');

  if($settlement_session_id > 0)
   {
    $settlement = SettlementSession::where('id',$settlement_session_id)->first();
   }else
   {
    $settlement = SettlementSession::where('vu_id',$vu_id)->where('v_id' , $v_id)->where('store_id' , $store_id)->orderBy('id','desc')->first();
    }
    if($settlement->closing_time == null){
      return response()->json(["status" => 'fail' , 'message'=> 'First closed your current session.']);
    }
    $terminal_id=$settlement->cash_register_id;

    
    if($settlement->partant_session_id!=Null)
    {
       $settlementS = SettlementSession::find($settlement->partant_session_id);
        $sessionH =  SettlementSession::select('id')->where('partant_session_id',$settlement->partant_session_id)->where('vu_id',$settlement->vu_id)->where('v_id',$settlement->v_id)->where('store_id' , $settlement->store_id)->get()->toArray();
         $sessionAll=[];
        foreach($sessionH as $value){
         $sessionAll[]=$value['id'];
        }
        $settlement_id =$settlementS->id; 
        $current_date =$settlementS->settlement_date;
        $opening_time = $settlementS->opening_time;
        $closing_time = $settlement->closing_time;
        $opening_balance = (float) $settlementS->opening_balance;
        $closing_balance = (float) $settlement->closing_balance;
        $currentSessionid     = [$settlementS->id];
        $sessionList      =  array_merge($currentSessionid,$sessionAll);
    }else{
        $settlement_id =$settlement->id; 
        $current_date =$settlement->settlement_date;
        $opening_time = $settlement->opening_time;
        $closing_time = $settlement->closing_time;
        $opening_balance = (float) $settlement->opening_balance;
        $closing_balance = (float) $settlement->closing_balance;
        $sessionList      = [$settlement->id];
    }
     $role_id = getRoleId($vu_id);
     $params  = array('v_id'=>$v_id,'store_id'=>$store_id,'name' =>'store','user_id'=>$vu_id,'role_id'=>$role_id);
     $storeSetting =  $this->vendorS->getSetting($params)->pluck('settings')->toArray();
     $storeSettings = json_decode($storeSetting[0]);

 


 $vendor = Vendor::select('first_name','last_name','mobile')->where('id', $vu_id)->first();
 if($closing_time!=Null)
 { 
       $payments = DB::table('payments as p')
                    ->select(DB::raw('p.amount,p.payment_id, CAST(p.cash_collected as decimal) as cash_collected,CAST( p.cash_return as decimal) as cash_return, p.method'))
                    ->join('orders as o', 'o.order_id' , 'p.order_id')
                    ->where('o.vu_id', $vu_id)
                    ->where('o.created_at','>=',$opening_time)
                    ->where('o.created_at','<=',$closing_time)
                    ->get();
      $payments_id = $payments->pluck('payment_id')->toArray();
      $payments_by_method = DB::table('payments as p')
                              ->select(DB::raw('sum(p.amount) as amount, p.method ,p.payment_type,o.transaction_type ,count(*) as count'))
                              ->join('invoices as o', 'o.invoice_id' , 'p.invoice_id')
                              ->where('o.vu_id', $vu_id)
                              ->whereIn('p.payment_id', $payments_id)
                              ->where('o.time','>=',$opening_time)
                              ->where('o.time','<=',$closing_time)
                              ->groupBy('p.method','o.transaction_type')
                              ->get();

  foreach($payments_by_method as $pay)
  {

      if($pay->method)
        $method = ucfirst(str_replace('_',' ', $pay->method));
      if($pay->transaction_type == 'return' && $pay->method=='voucher_credit')
      {
       $mop_summary_count[] = ['name' => 'Store Credit Issue' , 'value' => $pay->count ] ;
       $mop_summary_rs[] = ['name' => 'Store Credit Issue' , 'value' => $pay->amount ] ;
      }elseif($pay->transaction_type == 'sales' && $pay->method=='voucher_credit')
       {
       $mop_summary_count[] = ['name' => 'Store Credit Received' , 'value' => $pay->count ] ;
       $mop_summary_rs[] = ['name' => 'Store Credit Received' , 'value' => $pay->amount ] ;
     }
     else
     {
      $mop_summary_count[] = ['name' => $method , 'value' => $pay->count ] ;
      $mop_summary_rs[] = ['name' => $method , 'value' => $pay->amount ] ;
     }
    }
      $tender = $payments->sum('cash_collected');
      $refund = $payments->sum('cash_return');
      $orders = Invoice::select('total')
                  ->where('date',$current_date)
                  ->where('created_at','>=',$opening_time)
                  ->where('created_at','<=',$closing_time)
                  ->where('vu_id',$vu_id)
                  ->get();

  if($orders->isEmpty())
  {
     $order_count=0;
     $order_sum=0;
  }else{
   $order_count = $orders->count();
   $order_sum = $orders->sum('total');

  }

}
  $terminal_summary = [];
  $cash_summary = [];
$terminalinfo = CashRegister::select('id','name','udid')->where('udidtoken',$udidtoken)->first();


if(!$settlement){
 return response()->json(["status" => 'fail' , 'message'=> 'No Session found']);
}
$overOrShortS = '';
 if(isset($storeSettings->cashmanagement) && $storeSettings->cashmanagement->DEFAULT->status=='1'){
        $cashLog     =   CashTransactionLog::where('logged_session_user_id',$vu_id)
                                             ->where('store_id',$store_id)
                                             ->where('v_id',$v_id)
                                             ->where('cash_register_id',$terminal_id)
                                             ->whereIn('session_id',$sessionList)
                                             ->get();
        if($cashLog){                                   
        $cashIn =$cashLog->where('transaction_behaviour','IN')
                          ->where('transaction_type','!=','SALES')
                          ->where('transaction_type','!=','RETRUN')
                          ->sum('amount');
         $cashOut =$cashLog->where('transaction_behaviour','OUT')
                            ->sum('amount'); 
       }else{
       $cashIn=0.00; 
       $cashout=0.00;
       }
       $pay_in = format_number(($cashIn-$opening_balance));
       $pay_out =format_number($cashOut);
       $tenders=($tender+ $pay_in); 
       $refunds =($refund+ $pay_out); 
      $overOrShort = $closing_balance - ($opening_balance + ($tender - $refund));
      }else{
      $overOrShort = $closing_balance - ($opening_balance + ($tender - $refund));
      }
    if($overOrShort < 0){
       $overOrShortS = '('.format_number($overOrShort).')';
      }else{
       $overOrShortS = format_number($overOrShort);
      }
$terminal_summary[] = ['name' => 'Terminal Name' , 'value' => $terminalinfo->name.'-'.$terminalinfo->udid] ;
$terminal_summary[] = ['name' => 'Report Time' , 'value' => 'Print Date '.date('d-M-Y') ]  ;
$terminal_summary[] = ['name' => 'Session ID' , 'value' => (string)$settlement_id] ;
$terminal_summary[] = ['name' => 'Open On' , 'value' => (string)$opening_time]  ;
$terminal_summary[] = ['name' => 'Close On' , 'value' => (string)$closing_time ] ; 
$terminal_summary[] = ['name' => 'Total Sale Value' , 'value' => (string)$order_sum , 'bold' => 1] ; 
$terminal_summary[] = ['name' => 'Total Bill Count' , 'value' => (string)$order_count ,'bold' => 1 ]; 




$cash_summary[] = ['name' => 'Opening (A)' , 'value' => (string)$opening_balance] ;
$cash_summary[] = ['name' => 'Tender (B)' , 'value' => (string)$tender ]  ;
$cash_summary[] = ['name' => 'Change (C)' , 'value' => (string)$refund ] ;
$cash_summary[] = ['name' => 'Refund ' , 'value' => '' ];
$cash_summary[] = ['name' => 'Cash Total (D=B-C)' , 'value' => format_number($tender-$refund )] ;
$cash_summary[] = ['name' => 'Closing (E) ' , 'value' => $settlement->closing_balance ] ; 
$cash_summary[] = ['name' => 'Over/short(E-A-D)' , 'value' => $overOrShortS ,'bold' => 1  ] ; 


$openingTime      = explode(' ', $opening_time);
$closingTime      = explode(' ', $closing_time);

$manufacturer_name = 'basewin';
if($request->has('manufacturer_name') ){
 $manufacturer_name= $request->manufacturer_name;
}

$manufacturer_name =  explode('|',$manufacturer_name);
$printParams = [];
if(isset($manufacturer_name[1])){
 $printParams['model_no'] = $manufacturer_name[1]  ;
}


$bill_print_type = 0;
$role        = VendorRoleUserMapping::select('role_id')->where('user_id', $vu_id)->first();
$sParams     = ['v_id' => $v_id,'store_id'=>$store_id,'user_id'=>$vu_id,'role_id'=>@$role->role_id,'trans_from' => $trans_from];
$vendorS     = new VendorSettingController;
$printSetting= $vendorS->getPrintSetting($sParams);
if(count($printSetting) > 0){
    foreach($printSetting as $psetting){
        if($psetting->name == 'bill_print'){
            $bill_print_type = $psetting->width;
        }
    }
}
if($bill_print_type == 'A4' && $trans_from == 'CLOUD_TAB_WEB'){
    $data = $this->print_A4_html_page_settlement($request);
    return response()->json(['status' => 'success', 'print_data' =>$data], 200);
    // dd($data);  
}else {
$printInvioce = new PrintInvoice($manufacturer_name[0], $printParams);

$printInvioce->addLineCenter('Cash Summary Report', 30, true);
$printInvioce->addDivider(' ', 20);
$printInvioce->addLineLeft("Cashier Name: ".$vendor->first_name.' '.$vendor->last_name , 22);
$printInvioce->addLineLeft("Mobile: ".$vendor->mobile , 22);
$printInvioce->addDivider(' ', 20);
$printInvioce->addDivider('-', 22);
$printInvioce->addLineLeft('Terminal Summary'  , 22,true);
$printInvioce->addDivider('-', 22);

$printInvioce->addLineLeft('Terminal Name          '.$terminalinfo->name.'-'.$terminalinfo->udid , 22);
$printInvioce->addLineLeft('Report Time             Print Date', 22);
$printInvioce->addLineLeft('                       '.date('d-M-Y'), 22);
$printInvioce->addLineLeft('Session ID             '.$settlement_id, 22);
$printInvioce->addLineLeft('Open ON                '.$openingTime[0], 22);
$printInvioce->addLineLeft('                       '.$openingTime[1], 22);
$printInvioce->addLineLeft('Close On               '.$closingTime[0], 22);
$printInvioce->addLineLeft('                       '.$closingTime[1], 22);
$printInvioce->addLineLeft('Total Sale Value       '.$order_sum, 22,true);
$printInvioce->addLineLeft('Total Bill Value       '.$order_count, 22,true);
$printInvioce->addDivider('-', 22);
$printInvioce->addLineLeft('Cash Summary            In '.ucfirst($currencyName), 22,true);
$printInvioce->addDivider('-', 20);
$printInvioce->addLineLeft('Opening (A)               '.$opening_balance, 22);
$printInvioce->addLineLeft('Tender (B)                '.$tender, 22);
$printInvioce->addLineLeft('Change (C)                '.$refund, 22);
$printInvioce->addLineLeft('Refund                    ', 22);
 if(isset($storeSettings->cashmanagement) && $storeSettings->cashmanagement->DEFAULT->status=='1'){
 $printInvioce->addLineLeft('Pay-In (E)               '.$pay_in,22); 
$printInvioce->addLineLeft('Pay-Out (F)                '.$pay_out,22); 
$printInvioce->addLineLeft('Cash Total (D=(B+E)-(C+F)) '.format_number($tenders-$refunds), 22);
$printInvioce->addLineLeft('Closing (G)               '.$closing_balance , 22);
$printInvioce->addLineLeft('Over/short(G-A-D)         '.$overOrShortS, 22);    
}else{
$printInvioce->addLineLeft('Cash Total (D=B-C)        '.format_number($tender-$refund ), 22);
$printInvioce->addLineLeft('Closing (E)               '.$closing_balance , 22);
$printInvioce->addLineLeft('Over/short(E-A-D)         '.$overOrShortS, 22);
 }
$printInvioce->addDivider('-', 20);
    //$printInvioce->addLineLeft('MOP Summary             Count', 22,true);
if(count($mop_summary_count)>0){
$printInvioce->tableStructure(['MOP Summary', 'Count'], [26,8], 22,true);

$printInvioce->addDivider('-', 20);

foreach($mop_summary_count as $msc){
  $printInvioce->tableStructure([$msc['name'], $msc['value']], [26,8], 22);
}
$printInvioce->addDivider('-', 20);
    //$printInvioce->addLineLeft('Mop Summary                In Rs', 22,true);
$printInvioce->tableStructure(['MOP Summary', 'In '.ucfirst($currencyName)], [26,8], 22,true);
$printInvioce->addDivider('-', 20);
foreach($mop_summary_rs as $msr){
      //$printInvioce->addLineLeft($msr['name'].'          '.$msr['value'], 22);
 $printInvioce->tableStructure([$msr['name'], $msr['value']], [26,8], 22);
}
}
if($trans_from == 'CLOUD_TAB_WEB') {
 $mainCart = new MainCart;
 $htmlData = $mainCart->get_html_structure($printInvioce->getFinalResult());
 return response()->json(['status' => 'success',  'print_data' => ($printInvioce->getFinalResult()), 'html_data' => $htmlData], 200);
} else {
 return response()->json(['status' => 'success', 'print_data' =>($printInvioce->getFinalResult())], 200);
}
}
    //return response()->json(['status' => 'success' , 'print_count' => 1 , 'data' => $data ],200);


  }//End of print_settlement

  public function settlement_receipt(Request $request){

    $v_id     = $request->v_id;
    $store_id = $request->store_id;
    $vu_id    = $request->vu_id;
    $settlement_session_id = 0;
    if($request->has('settlement_session_id')){
      $settlement_session_id = $request->settlement_session_id;
    }
    $trans_from = '';
    if($request->has('trans_from')){
     $trans_from = $request->trans_from; 
   }

   $request = new \Illuminate\Http\Request();
   $request->merge([
    'v_id'     => $v_id,
    'vu_id'    => $vu_id,
    'store_id' => $store_id,
    'settlement_session_id' => $settlement_session_id,
    'trans_from' => $request->trans_from
  ]);
   $htmlData = $this->print_settlement($request);
   $html = $htmlData->getContent();
   $html_obj_data = json_decode($html);
   if($html_obj_data->status == 'success')
   {
     $cloudCart =  new CartController;
     return $cloudCart->get_html_structure($html_obj_data->print_data);
   }
   else{
     return false;
   }
    } //End of settlement_receipt

    public function print_settlement_record(Request $request){
      $v_id = $request->v_id;
      $store_id = $request->store_id;
      $vu_id = $request->vu_id;
      $trans_from = '';
      if($request->has('trans_from')){
       $trans_from = $request->trans_from; 
     }
     $operation = $request->operation;
     DB::table('operation_verification_log')->insert([ 'v_id' => $v_id, 'store_id' => $store_id, 'trans_from' => $trans_from, 'vu_id' =>$vu_id ,'operation' => $operation ,  'created_at' => date('Y-m-d H:i:s') ]);
     return response()->json(['status'=> 'success' , 'message' => 'Print Settlement Record Save'] , 200);
   }

   private function cashPointSessionOpen($store_id,$v_id,$terminal_id,$opening_balance,$vu_id,$session_id){
    $currentTerminalCashPoint = CashPoint::where('store_id',$store_id)
    ->where('v_id',$v_id)
    ->where('ref_id',$terminal_id)
    ->first();
    $terninalcashSummmary                  = new CashPointSummary;       
    $terninalcashSummmary->store_id        =  $store_id;
    $terninalcashSummmary->v_id            =  $v_id;
    $terninalcashSummmary->session_id      =  $session_id;
    $terninalcashSummmary->cash_point_id   =  $currentTerminalCashPoint->id;
    $terninalcashSummmary->cash_point_name =  $currentTerminalCashPoint->cash_point_name;
    $terninalcashSummmary->opening         =  $opening_balance;
    $terninalcashSummmary->pay_in          =  '0.00';
    $terninalcashSummmary->pay_out         =  '0.00';
    $terninalcashSummmary->closing         =  $opening_balance;
    $terninalcashSummmary->date            =  date('Y-m-d'); 
    $terninalcashSummmary->time            =  date('h:i:s');
    $terninalcashSummmary->save();

    $storeCashPoint  = storeCashPoint($store_id,$v_id);
    $docno = generateDocNo($store_id);
    $transBehaviourDetails = getCashTranscationType($v_id, $currentTerminalCashPoint->id, 'IN');
    $currentTranscation= CashTransaction::create([
                                                'v_id'  =>$v_id,
                                                'store_id'=>$store_id,
                                                'session_id'=>$session_id,
                                                'request_from_user'=>$vu_id,
                                                'request_from'=>$currentTerminalCashPoint->cash_point_name,
                                                'request_from_id'=>$currentTerminalCashPoint->id,
                                                'request_ref_id'=>$currentTerminalCashPoint->ref_id,
                                                'request_to'=>$storeCashPoint->cash_point_name,
                                                'request_to_id'=>$storeCashPoint->id,
                                                'transaction_behaviour'=>'IN',
                                                'transaction_type'=> $transBehaviourDetails->type,
                                                'in_Cash_point_type'=>$transBehaviourDetails->cash_point_type,
                                                'amount'=>$opening_balance,
                                                'status'=>'APPROVED',
                                                'doc_no'=>$docno,
                                                'date' =>date('Y-m-d'),
                                                'time'=>date('h:i:s'),
                                              ]);

    $CashTransactionLogto  = new  CashTransactionLog;
    $CashTransactionLogto->v_id = $v_id;
    $CashTransactionLogto->store_id = $store_id;
    $CashTransactionLogto->cash_point_id = $storeCashPoint->id;
    $CashTransactionLogto->cash_point_name = $storeCashPoint->cash_point_name;
    $CashTransactionLogto->transaction_type = 'SCPOUT';
    $CashTransactionLogto->transaction_behaviour = 'OUT';
    $CashTransactionLogto->amount = -($opening_balance);
    $CashTransactionLogto->transaction_ref_id =$currentTranscation->id;
    $CashTransactionLogto->status = 'APPROVED';
    $CashTransactionLogto->approved_by ='Auto';
    $CashTransactionLogto->remark ='out for terminal as open session';  
    $CashTransactionLogto->date = date('Y-m-d');
    $CashTransactionLogto->time =date('h:i:s'); 
    $CashTransactionLogto->save();

    $terninalcashpoint  = new  CashTransactionLog;
    $terninalcashpoint->v_id = $v_id;
    $terninalcashpoint->store_id = $store_id;
    $terninalcashpoint->session_id =$session_id;
    $terninalcashpoint->logged_session_user_id =$vu_id;
    $terninalcashpoint->cash_point_id = $currentTerminalCashPoint->id;
    $terninalcashpoint->cash_point_name = $currentTerminalCashPoint->cash_point_name;
    $terninalcashpoint->transaction_type = 'TCPIN';
    $terninalcashpoint->transaction_behaviour ='IN';
    $terninalcashpoint->amount =$opening_balance;
    $terninalcashpoint->transaction_ref_id =$currentTranscation->id;
    $terninalcashpoint->cash_register_id = $currentTerminalCashPoint->ref_id;
    $terninalcashpoint->status = 'APPROVED';
    $terninalcashpoint->remark ='open session';
    $terninalcashpoint->approved_by ='Auto';
    $terninalcashpoint->date =date('Y-m-d');
    $terninalcashpoint->time =date('h:i:s'); 
    $terninalcashpoint->save();
    
    $cashmanagement  = new CashManagementController;
    $cashmanagement->cashPointSummaryUpdate($CashTransactionLogto->cash_point_id,$CashTransactionLogto->cash_point_name,$CashTransactionLogto->store_id,$CashTransactionLogto->v_id,$CashTransactionLogto->session_id);   

  }

  public function getPreviousSessionStatus(Request $request){

         $v_id              = $request->v_id;
         $store_id          = $request->store_id;
         $vu_id             = $request->vu_id;
         $udidtoken         = $request->udidtoken;
         $data=[ 'status'=>0,
                 'session_details'=>[],
               ];
         $currentTerminal   = CashRegister::where('udidtoken',$udidtoken)
                                    ->where('store_id',$store_id)
                                    ->where('v_id',$v_id)
                                    ->first();
    
        $lastTerminal       =  SettlementSession::where('store_id',$store_id)
                                                  ->where('v_id',$v_id)
                                                  ->where('vu_id',$vu_id)
                                                  ->whereNull('closing_time')
                                                  ->orderBy('id','desc')
                                                  ->first();
        //dd($lastTerminal);
        if($lastTerminal){
        
            if($currentTerminal->id !=$lastTerminal->cash_register_id){
               

            }else{
              return $data;
         
            }

        }else{

          return $data;

        }

  }

public function getSessionDetails(Request $request){
 
    $v_id     = $request->v_id;
    $store_id = $request->store_id; 
    $vu_id    = $request->vu_id;

$settlementSession = SettlementSession::join('cash_registers','cash_registers.id','settlement_sessions.cash_register_id')->select('settlement_sessions.id','settlement_sessions.partant_session_id','settlement_sessions.opening_balance','settlement_sessions.settlement_date','settlement_sessions.opening_time','cash_registers.name')->where(['settlement_sessions.v_id' => $v_id ,'settlement_sessions.store_id' => $store_id, 'settlement_sessions.vu_id' => $vu_id])->orderBy('settlement_sessions.id','desc')->first();

    if($settlementSession){

        $terminal_name  = $settlementSession->name;
        $opening_time   = $settlementSession->opening_time;   

       if($settlementSession->partant_session_id==null|| $settlementSession->partant_session_id=''){
         $status   = ['status'=>1,
                      'details'=>['opening_balance'=>$settlementSession->opening_balance,'session_id'=>$settlementSession->id,'terminal_name'=>$terminal_name,'open_at'=>$opening_time]
                     ];
       }else{
          $parentSession = SettlementSession::where(['v_id' => $v_id ,'store_id' => $store_id, 'vu_id' => $vu_id,'id'=>$settlementSession->partant_session_id])->first();

          $status   = ['status'=>1,
                       'details'=>['opening_balance'=>$parentSession->opening_balance,
                                    'session_id'   =>$settlementSession->id,'terminal_name'=>$terminal_name,'open_at'=>$opening_time
                                  ]
                      ];
       }

    }else{
      $status   = ['status'=>0,'details'=>(object)[]];
    }
    return $status;

}


 public function getExpireDetails(Request $request){

    $v_id     = $request->v_id;
    $store_id = $request->store_id; 
    $vu_id    = $request->vu_id;
    $settlementSession = SettlementSession::join('cash_registers','cash_registers.id','settlement_sessions.cash_register_id')->select('settlement_sessions.id','settlement_sessions.partant_session_id','settlement_sessions.opening_balance','settlement_sessions.settlement_date','settlement_sessions.opening_time','cash_registers.name')->where(['settlement_sessions.v_id' => $v_id ,'settlement_sessions.store_id' => $store_id, 'settlement_sessions.vu_id' => $vu_id, 'closing_time' => null])->orderBy('settlement_sessions.id','desc')->first();

    if($settlementSession){

       if($settlementSession->partant_session_id==null|| $settlementSession->partant_session_id=''){
           $settlement_date = date_create($settlementSession->settlement_date);
           $opening_balance = $settlementSession->opening_balance;
       }else{
        $parentSession = SettlementSession::where(['v_id' => $v_id ,'store_id' => $store_id, 'vu_id' => $vu_id,'id'=>$settlementSession->partant_session_id])->first();
          $settlement_date = date_create($parentSession->settlement_date);
          $opening_balance = $parentSession->opening_balance;
       }
          $terminal_name = $settlementSession->name;
          $opening_time   = $settlementSession->opening_time;
          $current_date = date_create(date('Y-m-d'));
          $interval = $settlement_date ->diff($current_date);
          $currentSessionday = $interval->format('%a');
          $role_id = getRoleId($request->vu_id);
          $sParams =['v_id' => $request->v_id, 'store_id' => $request->store_id, 'user_id' => $request->vu_id, 'role_id' => $role_id, 'trans_from' =>$request->trans_from,'udidtoken'=>$request->udidtoken];
          $maxActiveSessionDay=$this->vendorS->getActiveSessionday($sParams);
         if($currentSessionday>$maxActiveSessionDay)
         {
           $status   = ['status'=>1,'details'=>['opening_balance'=>$opening_balance,'session_id'=>$settlementSession->id,'terminal_name'=>$terminal_name,'open_at'=>$opening_time]];
         }else{
           $status   = ['status'=>0,'details'=>(object)[]];
        }

    }else{
       $status   = ['status'=>0,'details'=>(object)[]];
    }
    return $status;
    

 } 

 public function forceClosed(Request $request){
 
    $v_id            = $request->v_id;
    $store_id        = $request->store_id; 
    $vu_id           = $request->vu_id;
    $session_id      = $request->idddd;
    $closing_balance = $request->closing_balance;

    $settlementSession =  SettlementSession::find($session_id);
    $role_id = getRoleId($vu_id);
    $params  = array('v_id'=>$v_id,'store_id'=>$store_id,'name' =>'store','user_id'=>$vu_id,'role_id'=>$role_id);
    $vSettings = new VendorController;
    $setting  = new VendorSettingController;
    $storeSetting = $setting->getSetting($params)->pluck('settings')->toArray();
    $storeSettings = json_decode($storeSetting[0]);
    if(isset($storeSettings->cashmanagement) && $storeSettings->cashmanagement->DEFAULT->status=='1')
    {
        $current_date = date('Y-m-d');
        $settlementSession->settlement_date;
        // if($settlementSession->settlement_date==$current_date){
            $cashmanagement  = new CashManagementController;
            $cashPointCash = $cashmanagement->closeSession($store_id,$v_id,$settlementSession->cash_register_id,$vu_id,$settlementSession->id,$closing_balance);
            $overOrShort = '';
            $overOrShort  = (float)$cashPointCash['totalamount'] -(float)$closing_balance;
            if($overOrShort < 0){
              $overOrShortS = '('.format_number($overOrShort).')';
            }else{
             $overOrShortS = format_number($overOrShort);
            }     
           $settlementSession->closing_balance = $closing_balance;
           $settlementSession->closing_time = date('Y-m-d H:i:s');
           $settlementSession->status = '1';
           $settlementSession->short_access=$overOrShortS;
           $settlementSession->session_close_type="FORCEFULLY";
           $settlementSession->save();
           if($request->has('currency')){
             $currencydata  = array('currency'=>json_decode($request->currency),'v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id,'settlement_id'=>$settlementSession->id, 'settlement_sessions_type' => 'CLOSE');
             $this->addCurrency($currencydata);
           }

            return response()->json(['status' => 'success' , 'message' => 'session closed Succesfully' ]);
            //$vSettings->get_settings($request);  
           //return response()->json(['status' => 'success' , 'message' => 'Balance  added Succesfully' ]);  
        // }else{
        //    return response()->json([ 'status' => 'fail', 'message' => 'This store has enabled cash mangement.Please contact store manager to closed your previous session.'],200); 
        // }
    }else{

        $payments = DB::table('payments as p')
                     ->select(DB::raw('p.amount, CAST(p.cash_collected as decimal) as cash_collected,CAST( p.cash_return as decimal) as cash_return, p.method'))
                     ->join('orders as o', 'o.order_id' , 'p.order_id')
                     ->where('o.date', date('Y-m-d'))
                     ->where('p.session_id',$settlementSession->id)
                     ->where('o.vu_id', $vu_id)
                     ->where('o.created_at','>=',$settlementSession->opening_time)
                     ->where('o.created_at','<=',date('Y-m-d H:i:s'))
                     ->get();
        $tender = $payments->sum('cash_collected');
        $refund = $payments->sum('cash_return');     
        $opening_balance = (float) $settlementSession->opening_balance;
        $closing_balance = (float) $closing_balance;

        $overOrShortS = '';
        $overOrShort = $closing_balance - ($opening_balance + ($tender - $refund));
           if($overOrShort < 0){
             $overOrShortS = '('.format_number($overOrShort).')';
           }else{
             $overOrShortS = format_number($overOrShort);
           }  
        $settlementSession->closing_balance = $closing_balance;
        $settlementSession->closing_time = date('Y-m-d H:i:s');
        $settlementSession->status = '1';
        $settlementSession->short_access=$overOrShortS;
        $settlementSession->session_close_type="FORCEFULLY";
        $settlementSession->save();
      if($request->has('currency')){
         $currencydata  = array('currency'=>json_decode($request->currency),'v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id,'settlement_id'=>$settlementSession->id, 'settlement_sessions_type' => 'CLOSE');
         $this->addCurrency($currencydata);
     }
      //$vSettings->get_settings($request);
      return response()->json(['status' => 'success' , 'message' => 'session closed Succesfully' ]);
    }

    }

    public function print_A4_html_page_settlement($request)
    {
            $product_data= [];
            $gst_list    = [];
            $final_gst   = [];
            $detatch_gst = [];
            $rounded     = 0;
            $data    = '';
            
            $v_id = $request->v_id;
            $store_id = $request->store_id;
            $vu_id = $request->vu_id;
            $settlement_session_id = 0;
            $udidtoken=$request->udidtoken;
            $mop_summary_count = [];
            $mop_summary_rs = [];
            $vendorC  = new VendorController;
            $tender  = 0;
            $crparams = array('v_id'=>$v_id,'store_id'=>$store_id,'vu_id'=>1);
            $currency = $vendorC->getCurrencyDetail($crparams);
            $currencyR = explode(' ', $currency['name']);

            if($currencyR > 1){
                $len = count($currencyR);
                $currencyName = $currencyR[$len-1];
            }else{
                $currencyName  =  $currencyR ;
            }
           if($request->has('settlement_session_id')){
            $settlement_session_id = $request->settlement_session_id;
            }
            $trans_from = '';
           if($request->has('trans_from')){
             $trans_from = $request->trans_from; 
           }
           $current_date = date('Y-m-d');

          if($settlement_session_id > 0)
           {
            $settlement = SettlementSession::where('id',$settlement_session_id)->first();
           }else
           {
            $settlement = SettlementSession::where('vu_id',$vu_id)->where('v_id' , $v_id)->where('store_id' , $store_id)->orderBy('id','desc')->first();
            }
            if($settlement->closing_time == null){
              return response()->json(["status" => 'fail' , 'message'=> 'First closed your current session.']);
            }
            $terminal_id=$settlement->cash_register_id;
            
            if($settlement->partant_session_id!=Null)
            {
               $settlementS = SettlementSession::find($settlement->partant_session_id);
                $sessionH =  SettlementSession::select('id')->where('partant_session_id',$settlement->partant_session_id)->where('vu_id',$settlement->vu_id)->where('v_id',$settlement->v_id)->where('store_id' , $settlement->store_id)->get()->toArray();
                 $sessionAll=[];
                foreach($sessionH as $value){
                 $sessionAll[]=$value['id'];
                }
                $settlement_id =$settlementS->id; 
                $current_date =$settlementS->settlement_date;
                $opening_time = $settlementS->opening_time;
                $closing_time = $settlement->closing_time;
                $opening_balance = (float) $settlementS->opening_balance;
                $closing_balance = (float) $settlement->closing_balance;
                $currentSessionid     = [$settlementS->id];
                $sessionList      =  array_merge($currentSessionid,$sessionAll);
            }else{
                $settlement_id =$settlement->id; 
                $current_date =$settlement->settlement_date;
                $opening_time = $settlement->opening_time;
                $closing_time = $settlement->closing_time;
                $opening_balance = (float) $settlement->opening_balance;
                $closing_balance = (float) $settlement->closing_balance;
                $sessionList      = [$settlement->id];
            }
             $role_id = getRoleId($vu_id);
             $params  = array('v_id'=>$v_id,'store_id'=>$store_id,'name' =>'store','user_id'=>$vu_id,'role_id'=>$role_id);
             $storeSetting =  $this->vendorS->getSetting($params)->pluck('settings')->toArray();
             $storeSettings = json_decode($storeSetting[0]);


         $vendor = Vendor::select('first_name','last_name','mobile')->where('id', $vu_id)->first();
         $organization = DB::table('vendor')->where('id', $v_id)->first();
         $store = DB::table('stores')->where('store_id', $store_id)->first();
         if($closing_time!=Null)
         { 
               $payments = DB::table('payments as p')
                            ->select(DB::raw('p.amount,p.payment_id, CAST(p.cash_collected as decimal) as cash_collected,CAST( p.cash_return as decimal) as cash_return, p.method'))
                            ->join('orders as o', 'o.order_id' , 'p.order_id')
                            ->where('o.vu_id', $vu_id)
                            ->where('o.created_at','>=',$opening_time)
                            ->where('o.created_at','<=',$closing_time)
                            ->get();
              $payments_id = $payments->pluck('payment_id')->toArray();
              $payments_by_method = DB::table('payments as p')
                                      ->select(DB::raw('sum(p.amount) as amount, p.method ,p.payment_type,o.transaction_type ,count(*) as count'))
                                      ->join('invoices as o', 'o.invoice_id' , 'p.invoice_id')
                                      ->where('o.vu_id', $vu_id)
                                      ->whereIn('p.payment_id', $payments_id)
                                      ->where('o.time','>=',$opening_time)
                                      ->where('o.time','<=',$closing_time)
                                      ->groupBy('p.method','o.transaction_type')
                                      ->get();

               // $payments_by_method1 = DB::table('payments as p')
               //                        ->select(DB::raw('sum(p.amount) as amount, p.method ,p.payment_type,o.transaction_type ,count(*) as count'))
               //                        ->join('invoices as o', 'o.invoice_id' , 'p.invoice_id')
               //                        ->where('o.vu_id', $vu_id)
               //                        ->whereIn('p.payment_id', $payments_id)
               //                        ->where('o.time','>=',$opening_time)
               //                        ->where('o.time','<=',$closing_time)
               //                        ->groupBy('p.method')
               //                        ->get();                       
          foreach($payments_by_method as $pay)
          {

              if($pay->method)
                $method = ucfirst(str_replace('_',' ', $pay->method));
              if($pay->transaction_type == 'return' && $pay->method=='voucher_credit')
              {
               $mop_summary_count[] = ['name' => 'Store Credit Issue' , 'value' => $pay->count ] ;
               $mop_summary_rs[] = ['name' => 'Store Credit Issue' , 'value' => $pay->amount ] ;
              }elseif($pay->transaction_type == 'sales' && $pay->method=='voucher_credit')
               {
               $mop_summary_count[] = ['name' => 'Store Credit Received' , 'value' => $pay->count ] ;
               $mop_summary_rs[] = ['name' => 'Store Credit Received' , 'value' => $pay->amount ] ;
             }
             else
             {
              $mop_summary_count[] = ['name' => $method , 'value' => $pay->count ] ;
              $mop_summary_rs[] = ['name' => $method , 'value' => $pay->amount ] ;
             }
            }
              $tender = $payments->sum('cash_collected');
              $refund = $payments->sum('cash_return');

////// cahnges for cash return ///////
              $cash_change = Invoice::join('payments','invoices.invoice_id','payments.invoice_id')
                          ->where('invoices.transaction_type','return')
                          ->where('invoices.date',$current_date)
                          ->where('invoices.created_at','>=',$opening_time)
                          ->where('invoices.created_at','<=',$closing_time)
                          ->where('invoices.vu_id',$vu_id)
                          ->where('payments.method','cash')
                          ->sum('payments.amount');

              // $cash_change1 = $payments->sum('cash_collected');
              $orders = Invoice::select('total')
                          ->where('transaction_type','sales')
                          ->where('date',$current_date)
                          ->where('created_at','>=',$opening_time)
                          ->where('created_at','<=',$closing_time)
                          ->where('vu_id',$vu_id)
                          ->get();

          if($orders->isEmpty())
          {
             $order_count=0;
             $order_sum=0;
          }else{
           $order_count = $orders->count();
           $order_sum = $orders->sum('total');

          }

          $return_orders = Invoice::select('total')
                          ->where('transaction_type','return')
                          ->where('date',$current_date)
                          ->where('created_at','>=',$opening_time)
                          ->where('created_at','<=',$closing_time)
                          ->where('vu_id',$vu_id)
                          ->get();
          if($return_orders->isEmpty())
          {
             $retun_count=0;
             $retun_sum=0;
          }else{
           $retun_count = $return_orders->count();
           $retun_sum = $return_orders->sum('total');

          }

          
        }
       // dd($payments_by_method);
          $terminal_summary = [];
          $cash_summary = [];
        $terminalinfo = CashRegister::select('id','name','udid')->where('udidtoken',$udidtoken)->first();


        if(!$settlement){
         return response()->json(["status" => 'fail' , 'message'=> 'No Session found']);
        }
        $overOrShortS = '';
         if(isset($storeSettings->cashmanagement) && $storeSettings->cashmanagement->DEFAULT->status=='1'){
                $cashLog     =   CashTransactionLog::where('logged_session_user_id',$vu_id)
                                                     ->where('store_id',$store_id)
                                                     ->where('v_id',$v_id)
                                                     ->where('cash_register_id',$terminal_id)
                                                     ->whereIn('session_id',$sessionList)
                                                     ->get();
                if($cashLog){                                   
                $cashIn =$cashLog->where('transaction_behaviour','IN')
                                  ->where('transaction_type','!=','SALES')
                                  ->where('transaction_type','!=','RETRUN')
                                  ->sum('amount');
                 $cashOut =$cashLog->where('transaction_behaviour','OUT')
                                    ->sum('amount'); 
               }else{
               $cashIn=0.00; 
               $cashout=0.00;
               }
               $pay_in = format_number(($cashIn-$opening_balance));
               $pay_out =format_number($cashOut);
               $tenders=($tender+ $pay_in); 
               $refunds =($refund+ $pay_out); 
              $overOrShort = $closing_balance - ($opening_balance + ($tender - $refund - $cash_change));
              }else{
              $overOrShort = $closing_balance - ($opening_balance + ($tender - $refund - $cash_change));
              }
            if($overOrShort < 0){
               $overOrShortS = '('.format_number($overOrShort).')';
              }else{
               $overOrShortS = format_number($overOrShort);
              }
         $bilLogo      = '';
         $logo = '';
            $bill_logo_id = 5;
            $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
            if($vendorImage)
            {
                $bilLogo = env('ADMIN_URL').$vendorImage->path;
            } 
            $organisation = Organisation::find($v_id);
            if($organisation->db_type == 'MULTITON' && $organisation->db_name != ''){
                $connPrm    =array('host'=> $organisation->connection->host,'port'=>$organisation->connection->port,'username'=>$organisation->connection->username,'password' =>  $organisation->connection->password,'db_name'=>$organisation->db_name);
                dynamicConnectionNew($connPrm);
            }
            // dd(DB::connection()->getDatabaseName(),$organisation);
            $applogo  = VendorImage::where('v_id', $v_id)->where('type', 1)->where('status',1)->first();
            if($applogo)
            {
                $logo = env('ADMIN_URL').$applogo->path;
                // $logo = "url('public/img/Logo.png')";
            }
            // dd($logo,$bilLogo,$applogo);
        $terminal_summary[] = ['name' => 'Terminal Name' , 'value' => $terminalinfo->name.'-'.$terminalinfo->udid] ;
        $terminal_summary[] = ['name' => 'Report Time' , 'value' => 'Print Date '.date('d-M-Y') ]  ;
        $terminal_summary[] = ['name' => 'Session ID' , 'value' => (string)$settlement_id] ;
        $terminal_summary[] = ['name' => 'Open On' , 'value' => (string)$opening_time]  ;
        $terminal_summary[] = ['name' => 'Close On' , 'value' => (string)$closing_time ] ; 
        $terminal_summary[] = ['name' => 'Total Sale Value' , 'value' => (string)$order_sum , 'bold' => 1] ; 
        $terminal_summary[] = ['name' => 'Total Bill Count' , 'value' => (string)$order_count ,'bold' => 1 ]; 




        $cash_summary[] = ['name' => 'Opening (A)' , 'value' => (string)$opening_balance] ;
        $cash_summary[] = ['name' => 'Tender (B)' , 'value' => (string)$tender ]  ;
        $cash_summary[] = ['name' => 'Change (C)' , 'value' => (string)$refund ] ;
        $cash_summary[] = ['name' => 'Refund ' , 'value' => '' ];
        $cash_summary[] = ['name' => 'Cash Total (D=B-C)' , 'value' => format_number($tender-$refund )] ;
        $cash_summary[] = ['name' => 'Closing (E) ' , 'value' => $settlement->closing_balance ] ; 
        $cash_summary[] = ['name' => 'Over/short(E-A-D)' , 'value' => $overOrShortS ,'bold' => 1  ] ; 


        $openingTime      = explode(' ', $opening_time);
        $closingTime      = explode(' ', $closing_time);

        $manufacturer_name = 'basewin';
        if($request->has('manufacturer_name') ){
         $manufacturer_name= $request->manufacturer_name;
        }

        $manufacturer_name =  explode('|',$manufacturer_name);
        $printParams = [];
        if(isset($manufacturer_name[1])){
         $printParams['model_no'] = $manufacturer_name[1]  ;
        }
        
          $title = 'Cash Summary Report';
            $style = "<style>body{font-family: 'ibmplex';}.header{font-size: 14px;line-height: 24px; font-weight: 600;}table tr td{font-size: 12px;line-height: 16px;}
            @font-face {
              font-family: 'ibm_plex';
                      src: url('https://test.api.gozwing.com/font/ibm-plex-sans-v8-latin-regular.woff2;')('woff2'),
                           url('https://test.api.gozwing.com/font/ibm-plex-sans-v8-latin-regular.woff') format('woff');
                           font-weight: 600;
                           font-style: normal;
            }</style>";
            ########################
            ####### Print Start ####
            ########################
            
          $data = '
            <table width="90%"  style="margin: 24px auto; text-align:left;">
            <tr><td>';  
            $data  .= '<table width="100%"><tr>
            <td class="header" width="70%" style="font-size: 20px;">Cash Summary Report</td>
            <td align="right"><img src="'.$bilLogo.'" alt="client logo" height="80px"></td>
            </tr>
            </table> <br>
            <hr>';


           $data  .=  '<table width="100%" class="body">
                        <tr><td width="35%">Organization</td>
                            <td>'.$organization->name.'</td></tr>
                        <tr><td width="35%">Store</td>
                            <td>'.$store->name.'</td></tr>
                        <tr><td width="35%">Cashier Name</td>
                            <td>'.$vendor->first_name.' '.$vendor->last_name.'</td></tr>
                        <tr><td width="35%">Mobile</td>
                            <td>'.$vendor->mobile.'</td></tr>
                        <tr><td width="35%">Report printed on</td>
                            <td>'.date('d-M-Y').'</td></tr>
                      </table>
                      <br>
                      <br>'; 
           $data  .=  '<table width="100%">
                      <tr><td class="header">Terminal Summary</td></tr>
                      </table>
                      <hr>';

           $data  .=  '<table width="100%" class="body">
                        <tr><td width="35%">Terminal Name</td>
                            <td>'.$terminalinfo->name.'-'.$terminalinfo->udid.'</td></tr>
                        <tr><td width="35%">Session ID</td>
                            <td>'.$settlement_id.'</td></tr>
                        <tr><td width="35%">Session opened on</td>
                            <td>'.$openingTime[0].'</td></tr>
                        <tr><td width="35%">Session closed on</td>
                            <td>'.$closingTime[0].'</td></tr>
                        <tr><td width="35%">Total sale value</td>
                            <td>'.$order_sum.'</td></tr>
                        <tr><td width="35%">Total no. of sale bills</td>
                            <td>'.$order_count.'</td></tr>
                        <tr><td width="35%">Total return value</td>
                            <td>'.$retun_sum.'</td></tr>
                        <tr><td width="35%">Total no. of return bills</td>
                            <td>'.$retun_count.'</td></tr>
                      </table>
                      <br><br>';
            $data  .=  '<table width="100%">
                        <tr><td class="header">Cash Summary</td></tr>
                        </table><hr>';

            $data  .=  '<table width="100%" class="body">
                        <tr><td width="35%">Opening (A)</td>
                            <td>'.$opening_balance.'</td></tr>
                        <tr><td width="35%">Tender (B)</td>
                            <td>'.$tender.'</td></tr>
                        <tr><td width="35%">Change (C)</td>
                            <td>'."-".$refund.'</td></tr>
                        <tr><td width="35%">Refund Cash (D)</td>
                            <td>'.$cash_change.'</td></tr>
                        <tr><td width="35%">Cash Total (E=B-C-D)</td>
                            <td>'.format_number($tender-$refund-$cash_change).'</td></tr>
                        <tr><td width="35%">Closing (F)</td>
                            <td>'.$closing_balance.'</td></tr>
                        <tr><td width="35%">Over/Short (F-A-E)</td>
                            <td>'.$overOrShortS.'</td></tr>
                      </table><br><br>';
            $data  .=  '<table width="100%">
                        <tr><td class="header" width="35%">MOP Sales Summary</td>
                        <td class="header" width="35%">Count</td>
                        <td class="header" width="30%">Value In Rupees</td></tr>
                        </table><hr>';

            $data  .=  '<table width="100%" class="body">';
                      foreach ($payments_by_method as $value) {
                        if($value->transaction_type == 'sales'){
                            $data  .=  '<tr><td width="35%">'.$value->method.'</td>
                              <td width="35%">'.$value->count.'</td>
                              <td width="30%">'.$value->amount.'</td></tr>';
                          }
                      }
                      '</table><br><br>';
            $data  .=  '<table width="100%">
                        <tr><td class="header" width="35%">MOP Return Summary</td>
                        <td class="header" width="35%">Count</td>
                        <td class="header" width="30%">Value In Rupees</td></tr>
                        </table><hr>';

            $data  .=  '<table width="100%" class="body">';
                      foreach ($payments_by_method as $value) {
                        if($value->transaction_type == 'return'){
                            $data  .=  '<tr><td width="35%">'.$value->method.'</td>
                              <td width="35%">'.$value->count.'</td>
                              <td width="30%">'.$value->amount.'</td></tr>';
                          }
                      }
                      '</table><br><br>';
            $data  .=  '<table width="40%">
                        <tr><td style="display: flex; align-items:center;">Powered by <img src="'.$logo.'" alt="Zwing logo" height="80px"></td></tr>
                        </table></td></tr></table>';

         // dd($data );

        $return = array('style'=>$style,'html'=>$data) ;
        // $return = array('status'=>'success','html'=>$data) ;
        return $return;
    }//End of print_A4_html_page_stt

}





