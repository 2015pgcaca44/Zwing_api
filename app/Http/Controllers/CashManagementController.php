<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\CashPointType;
use App\CashPoint;
use App\CashRegister;
use App\CashTransaction;
use App\CashTransactionLog;
use App\SettlementSession;
use App\CashPointSummary;
use App\Vendor;
use DB;


class CashManagementController extends Controller
{
  

  public function __construct()
	{
		$this->middleware('auth');
	}  

  public function getCashPointTypeList(Request $request){

  	      $v_id = $request->v_id;
          $store_id = $request->store_id;
          $udidtoken = $request->udidtoken;
          $vu_id     = $request->vu_id;
          $cashpointTypes=CashPointType::select('id','type_name')->where('is_pos_visible','1')->get();
          $currentterminal_id = CashRegister::select('id')->where('udidtoken',$udidtoken)->first();
          $data= [];
          foreach ($cashpointTypes as  $cashpointType) 
          {
              if($cashpointType->id=='2'){    
                  $data[]=     array('cash_point_type_id' =>$cashpointType->id,
                                     'cash_point_type_name'=>$cashpointType->type_name,
                                     'cash_point_list'=>CashPoint::select('id','cash_point_name')
                                                                    ->where('store_id',$store_id)
                                                                    ->where('v_id',$v_id)
                                                                    ->where('status','1')->where('cash_point_type_id',$cashpointType->id)
                                                                    ->get()
                                    );
              }else{
                $CashPoints=CashPoint::select('id','cash_point_name','ref_id')
                                     ->where('store_id',$store_id)
                                     ->where('v_id',$v_id)->where('status','1')
                                     ->where('cash_point_type_id',$cashpointType->id)
                                     ->where('ref_id','<>', $currentterminal_id->id)->get();

                $CashPointlist=[];
                foreach ($CashPoints as $key => $CashPoint) 
                {
                 $CashPointlist[]  =       [
                                             'id' => $CashPoint->id,
                                             'cash_point_name' =>$CashPoint->cash_point_name,
                                             'cashiredetails' =>$this->iscashirelogin($CashPoint->ref_id,$v_id,$store_id)
                                            ];           
                }       
                $data[]=            [     
                                     'cash_point_type_id' =>$cashpointType->id,
                                     'cash_point_type_name'=>$cashpointType->type_name,
                                     'cash_point_list'=>$CashPointlist
                                    ];
              }
          }            
        $data = array('cash_point_type' =>$data);                             
     return response()->json(['status' => 'cash_point_type_list', 'data' =>$data], 200);                 
  }

  public function cashPointList(Request $request){

      $v_id         =  $request->v_id;
      $store_id     =  $request->store_id;
      $udidtoken    =  $request->udidtoken;
      $vu_id        =  $request->vu_id;
      $cptype       =  $request->cash_point_type;
      $currentterminal_id = CashRegister::select('id','name')
                                        ->where('udidtoken',$udidtoken)
                                        ->first();
      $cashpointlist = CashPoint::select('id','cash_point_name')
                                ->where('store_id',$store_id)->where('v_id',$v_id)
                                ->where('status','1')
                                ->where('cash_point_type_id',$cptype)
                                ->whereNull('ref_id')
                                ->orwhere('ref_id','<>', $currentterminal_id->id)
                                ->get();
      $data = array('cash_point_list' =>$cashpointlist);                             

      return response()->json(['status' => 'cash_point_type_list', 'data' =>$data], 200);       
  }

  public function cashPointTransfer(Request $request){
 
        $this->validate($request, [
                                  'cash_point_type_id'=>'required',  
                                  'cash_point_id' => 'required',
                                  'cash_trans_type'=>'required',
                                  'amount' => 'required|numeric|between:1,99999999.99'
                                  ]);

        $v_id              = $request->v_id;
        $store_id          = $request->store_id;
        $udidtoken         = $request->udidtoken;
        $vu_id             = $request->vu_id;
        $remarks           = $request->remarks;
        $amount            = $request->amount;
        $CashPointTypeid   = $request->cash_point_type_id;     
        $CashPointid       = $request->cash_point_id;
        $docno             = generateDocNo($store_id);
        $session_id        = SettlementSession::select('id','status')->where('v_id',$v_id)
                                              ->where('store_id',$store_id)
                                              ->where('vu_id',$vu_id)
                                              ->orderBy('id','desc')
                                              ->first();
        $current_session_id =   $session_id->id;
        $cashpointType      =  CashPointType::select('id','type_name')
                                            ->where('id',$CashPointTypeid)
                                            ->first();
        $inCashPointType     = $cashpointType->type_name;                                       
        $requestFromTerminal = CashRegister::select('id')
                                            ->where('udidtoken',$udidtoken)
                                            ->where('store_id',$store_id)
                                            ->where('v_id',$v_id)
                                            ->first();
        $requestFromDetails  = CashPoint::select('id','cash_point_name','ref_id')
                                         ->where('store_id',$store_id)
                                         ->where('v_id',$v_id)
                                         ->where('status','1')
                                         ->where('ref_id',$requestFromTerminal->id)
                                         ->first();

        $requestFrom      = $requestFromDetails->cash_point_name;
        $requestFromId    = $requestFromDetails->id;
        $requestFromRefId = $requestFromDetails->ref_id;
        $CashPointdetails = CashPoint::select('id','cash_point_name','ref_id')
                                     ->where('store_id',$store_id)
                                     ->where('v_id',$v_id)
                                     ->where('status','1')
                                     ->where('id',$CashPointid)
                                     ->first();
        $requestTo=$CashPointdetails->cash_point_name;
        $requestToId= $CashPointdetails->id;
        $requestToRefId=$CashPointdetails->ref_id;

        if($request->cash_trans_type==="CASHIN")
        {
            if($CashPointTypeid=='1'){

              $sessiondetails =SettlementSession::select('status','vu_id')
                                                     ->where('v_id',$v_id)
                                                      ->where('store_id',$store_id)
                                                       ->where('cash_register_id',$requestToRefId)
                                                       ->orderby('settlement_date','desc')
                                                       ->first();
              if($sessiondetails && $sessiondetails->status != '1'){
                  $transBehaviourInDetails = getCashTranscationType($v_id, $requestFromId, 'IN');
                  $cashout =  CashTransaction::create([
                                                       'v_id'  =>$v_id,
                                                        'store_id'=>$store_id,
                                                        'session_id'=>$current_session_id,
                                                        'request_from_user'=>$vu_id,
                                                        'request_from'=>$requestFrom,
                                                        'request_from_id'=>$requestFromId,
                                                        'request_ref_id'=>$requestFromRefId,
                                                        'request_to'=>$requestTo,
                                                        'request_to_id'=>$requestToId,
                                                        'request_to_ref_id'=>$requestToRefId,
                                                        'request_to_user'=>$sessiondetails->vu_id,
                                                        'transaction_behaviour'=>'IN',
                                                        'transaction_type'=> $transBehaviourInDetails->type,
                                                        'in_Cash_point_type' => $transBehaviourInDetails->cash_point_type,
                                                        'amount'=>$amount,
                                                        'status'=>'IN-TRANSIT',
                                                        'remark'=>$remarks,
                                                        'doc_no'=>$docno,
                                                        'date' =>date('Y-m-d'),
                                                        'time'=>date('h:i:s'),
                                                      ]);

                  return response()->json(['status' =>'success', 'message' => 'Successfully submitted cash in request to terminal.'], 200);

              }else{

                 return response()->json(['status' => 'fail', 'message' =>'No cashire login to this Terminal'], 200);

              }          

            }else{ 
              $transBehaviourInDetails = getCashTranscationType($v_id, $requestFromId, 'IN');
              $cashout =  CashTransaction::create([
                                                'v_id'  =>$v_id,
                                                'store_id'=>$store_id,
                                                'session_id'=>$current_session_id,
                                                'request_from_user'=>$vu_id,
                                                'request_from'=>$requestFrom,
                                                'request_from_id'=>$requestFromId,
                                                'request_ref_id'=>$requestFromRefId,
                                                'request_to'=>$requestTo,
                                                'request_to_id'=>$requestToId,
                                                'request_to_ref_id'=>'',
                                                'request_to_user'=>'',
                                                'transaction_behaviour'=>'IN',
                                                'transaction_type'=> $transBehaviourInDetails->type,
                                                'in_Cash_point_type'=>$transBehaviourInDetails->cash_point_type,
                                                'amount'=>$amount,
                                                'status'=>'IN-TRANSIT',
                                                'remark'=>$remarks,
                                                'doc_no'=>$docno,
                                                'date' =>date('Y-m-d'),
                                                'time'=>date('h:i:s'),
                                                ]);
              return response()->json(['status' =>'success', 
                                      'message' => 'Successfully submitted cash in request to store cash.'
                                     ], 200);
            } 
        }elseif($request->cash_trans_type==="CASHOUT"){
             $availableCash=$this->currentSessioncashPointCash($store_id,$v_id,$requestFromId,$current_session_id);
            if($amount>$availableCash)
            {
               return response()->json(['status' => 'fail', 
                                        'type'=>'insufficient funds', 
                                        'message' =>'This terminal does not have sufficient funds to perform this action.'
                                      ], 200);
            }else{   
                if($CashPointTypeid=='1')
                {
                     $sessiondetails = SettlementSession::select('status','vu_id')
                                                         ->where('v_id',$v_id)
                                                         ->where('store_id',$store_id)
                                                         ->where('cash_register_id',$requestToRefId)
                                                         ->orderby('settlement_date','desc')
                                                         ->first();
                    if($sessiondetails && $sessiondetails->status != '1')
                    {
                          $transBehaviourOutDetails = getCashTranscationType($v_id, $requestFromId, 'OUT');
                          $cashout =  CashTransaction::create([
                                                              'v_id'  =>$v_id,
                                                              'store_id'=>$store_id,
                                                              'session_id'=>$current_session_id,
                                                              'request_from_user'=>$vu_id,
                                                              'request_from'=>$requestFrom,
                                                              'request_from_id'=>$requestFromId,
                                                              'request_ref_id'=>$requestFromRefId,
                                                              'request_to'=>$requestTo,
                                                              'request_to_id'=>$requestToId,
                                                              'request_to_ref_id'=>$requestToRefId,
                                                              'request_to_user'=>$sessiondetails->vu_id,
                                                              'transaction_behaviour'=>'OUT',
                                                              'transaction_type'=> $transBehaviourOutDetails->type,
                                                              'in_Cash_point_type'=> $transBehaviourOutDetails->cash_point_type,
                                                              'amount'=>$amount,
                                                              'status'=>'IN-TRANSIT',
                                                              'remark'=>$remarks,
                                                              'doc_no'=>$docno,
                                                              'date' =>date('Y-m-d'),
                                                              'time'=>date('h:i:s'),
                                                              ]);
                          return response()->json(['status' =>'success', 
                                                   'message' => 'Successfully submitted cash out request to terminal.'
                                                  ], 200);
                    }else{
                       return response()->json(['status' => 'fail', 'message' =>'No cashire login to this Terminal'], 200);
                    }          
                }else{ 
                  $transBehaviourOutDetails = getCashTranscationType($v_id, $requestFromId, 'OUT');
                   $cashout =  CashTransaction::create([
                                                        'v_id'  =>$v_id,
                                                        'store_id'=>$store_id,
                                                        'session_id'=>$current_session_id,
                                                        'request_from_user'=>$vu_id,
                                                        'request_from'=>$requestFrom,
                                                        'request_from_id'=>$requestFromId,
                                                        'request_ref_id'=>$requestFromRefId,
                                                        'request_to'=>$requestTo,
                                                        'request_to_id'=>$requestToId,
                                                        'request_to_ref_id'=>'',
                                                        'request_to_user'=>'',
                                                        'transaction_behaviour'=>'OUT',
                                                        'transaction_type'=> $transBehaviourOutDetails->type,
                                                        'in_Cash_point_type'=>$transBehaviourOutDetails->cash_point_type,
                                                        'amount'=>$amount,
                                                        'status'=>'IN-TRANSIT',
                                                        'remark'=>$remarks,
                                                        'doc_no'=>$docno,
                                                        'date' =>date('Y-m-d'),
                                                        'time'=>date('h:i:s'),
                                                      ]);
                    return response()->json(['status' =>'success', 
                                             'message' => 'Successfully submitted cash out request to store cash.'
                                            ],200);
                }
            }
        }else{
            return response()->json(['status' => 'cash_trans_type_not_valid', 'message' =>'Invaild cash_trans_type.'], 200);
        }         
  }

  public function iscashirelogin($cr_id,$v_id,$store_id){

      $cashire=SettlementSession::join('vendor_auth','vendor_auth.id','settlement_sessions.vu_id')
                                ->select('settlement_sessions.status','vendor_auth.first_name','vendor_auth.last_name')
                                ->where('settlement_sessions.v_id',$v_id)
                                ->where('settlement_sessions.store_id',$store_id)
                                ->where('settlement_sessions.cash_register_id',$cr_id)
                                ->orderby('settlement_sessions.updated_at','desc')
                                ->orderby('settlement_sessions.settlement_date','desc')
                                ->first();
      if($cashire){
        if($cashire->status=='1'){
          return  array('is_session_active' =>'0',
                        'cashirename' =>''
                       );
        }else{
          return  array( 'is_session_active' =>'1',
                         'cashirename' =>$cashire->first_name . ' ' .$cashire->last_name
                      );
        }
      }else{
       return  array('is_session_active' => '0',
                      'cashirename' =>'',
                    );
      }
  }
  public function  getCurrentTransactions(Request $request){
        $v_id               =  $request->v_id;
        $store_id           =  $request->store_id;
        $udidtoken          =  $request->udidtoken;
        $vu_id              =  $request->vu_id;
        $request_to_ref_id  =  $this->getCashRegister($udidtoken,$store_id,$v_id);
        $receivedcurrentTransactions =CashTransaction::where('v_id',$v_id)
                                                     ->where('store_id',$store_id)
                                                     ->where('request_to_ref_id',$request_to_ref_id)
                                                     ->where('request_to_user',$vu_id)
                                                     ->where('status','IN-TRANSIT')
                                                     ->orderBy('id','desc')
                                                     ->get();

        $sendcurrentTransactions =CashTransaction::where('v_id',$v_id)
                                                 ->where('store_id',$store_id)
                                                 ->where('request_ref_id',$request_to_ref_id)
                                                 ->where('request_from_user',$vu_id)
                                                 ->where('status','IN-TRANSIT')
                                                 ->orderBy('id','desc')
                                                 ->get();                          
        if($receivedcurrentTransactions)
        {
            $receivedata=[];
            foreach($receivedcurrentTransactions as $receivedcurrentTransaction)
            {
              if($receivedcurrentTransaction->transaction_behaviour=="IN")
              {
                   $transactionType = "Cash Out";
              }else{
                   $transactionType = "Cash In";
              }
              if($receivedcurrentTransaction->in_Cash_point_type == "Terminal" AND $receivedcurrentTransaction->request_from_user !=Null )
              {
                 $Cashier = $this->getcashireName($receivedcurrentTransaction->request_from_user);
              }else{
                 $Cashier = '';
              }
              $receivedata[] =    array(
                                        'id'   =>$receivedcurrentTransaction->id,
                                        'source'=>$receivedcurrentTransaction->request_from,
                                        'amount' =>$receivedcurrentTransaction->amount,
                                        'cashier_name' =>$Cashier,
                                        'transaction_type'=>$transactionType,
                                        'remarks' =>$receivedcurrentTransaction->remark,
                                        'date' =>$receivedcurrentTransaction->date,
                                        'time' =>$receivedcurrentTransaction->time,
                                        'status'=>'REQUEST',
                                      );
            }
      
        }else{
           $receivedata = [];
        }
        if($sendcurrentTransactions){
              $senddata=[];
              foreach($sendcurrentTransactions as $receivedcurrentTransaction)
              {
                  if($receivedcurrentTransaction->transaction_behaviour=="IN"){
                      $transactionType = "Cash In";
                   }else{
                     $transactionType = "Cash Out";
                   }
                   if($receivedcurrentTransaction->in_Cash_point_type == "Terminal" AND $receivedcurrentTransaction->request_to_user !=Null){
                      $Cashier = $this->getcashireName($receivedcurrentTransaction->request_to_user);
                   }else{
                      $Cashier = '';
                   }
                    $senddata[] = array(
                                          'id'   =>$receivedcurrentTransaction->id,
                                          'source'=>$receivedcurrentTransaction->request_to,
                                          'amount' =>$receivedcurrentTransaction->amount,
                                          'cashier_name' =>$Cashier,
                                          'transaction_type'=>$transactionType,
                                          'remarks' =>$receivedcurrentTransaction->remark,
                                          'date' =>$receivedcurrentTransaction->date,
                                          'time' =>$receivedcurrentTransaction->time,
                                          'status'=>'REQUESTED',
                                        );
              }
        }else{
           $senddata = [];
        }
    return   $current_data = array_merge($receivedata,$senddata);
  } 
  public function updateTransactionStatus(Request $request)
  {
        $v_id               =  $request->v_id;
        $store_id           =  $request->store_id;
        $udidtoken          =  $request->udidtoken;
        $vu_id              =  $request->vu_id;
        $transaction_id     =  $request->transaction_id;
        $status             =  $request->status;
        $sessiondetails = SettlementSession::select('id','status','vu_id')
                                            ->where('v_id',$v_id)
                                            ->where('store_id',$store_id)
                                            ->where('vu_id',$vu_id)
                                            ->orderBy('id','desc')
                                            ->first();
        $transaction = CashTransaction::find($transaction_id);  

        if($transaction->transaction_behaviour == 'OUT') {
          return $this->updateOutTransactionStatus($request);
        }

        if($transaction){
            $availableCash=$this->currentSessioncashPointCash($store_id,$v_id,$transaction->request_to_id,$sessiondetails->id);

            if($status=="APPROVED" && $transaction->transaction_behaviour=='IN' &&$transaction->amount>$availableCash)
            {
              
                $transaction->status = 'DISMISSED';
                $transaction->approved_by = $vu_id;  
                $transaction->save(); 
                return response()->json(['status' => 'fail', 'type'=>'insufficient funds', 'message' =>'This terminal does not have sufficient funds to perform this action.'], 200);
            }else{  
               $transaction->status =  $status;
               $transaction->approved_by = $vu_id;  
               $transaction->save();  
            }
            if($transaction->status == "APPROVED"){

              // if($transaction->transaction_behaviour=="IN" && $transaction->transaction_type == "TPCASHIN"){
              //     $fromamount= $transaction->amount;
              //     $toamount  = -($transaction->amount);
              //     $totransaction_type ='TPCASHOUT';
              //     $totransaction_behaviour = 'OUT';
              // }elseif($transaction->transaction_behaviour=="OUT" && $transaction->transaction_type == "TPCASHOUT"){
                  
              //     $fromamount= -($transaction->amount);
              //     $toamount  = $transaction->amount;
              //     $totransaction_type ='TPCASHIN';
              //     $totransaction_behaviour = 'IN';

              // }elseif($transaction->transaction_behaviour=="OUT" && $transaction->transaction_type == "SCPOUT"){
                 
              //     $fromamount= -($transaction->amount);
              //     $toamount  = $transaction->amount;
              //     $totransaction_type ='TPCASHIN';
              //     $totransaction_behaviour = 'IN';

              // }elseif($transaction->transaction_behaviour=="IN" && $transaction->transaction_type == "SCPOUT"){
              //     $fromamount= $transaction->amount;
              //     $toamount  = -($transaction->amount);
              //     $totransaction_type ='SCPOUT';
              //     $totransaction_behaviour = 'OUT';
              // }

                // TO Cash Transaction Log

                $transBehaviourOutDetails = getCashTranscationType($v_id, $transaction->request_to_id, 'OUT');

                $CashTransactionLogto  = new  CashTransactionLog;
                $CashTransactionLogto->v_id = $v_id;
                $CashTransactionLogto->store_id = $store_id;
                $CashTransactionLogto->session_id =$sessiondetails->id;
                $CashTransactionLogto->logged_session_user_id = $transaction->request_to_user;
                $CashTransactionLogto->cash_point_id = $transaction->request_to_id;
                $CashTransactionLogto->cash_point_name = $transaction->request_to;
                $CashTransactionLogto->transaction_type = $transBehaviourOutDetails->type;
                $CashTransactionLogto->transaction_behaviour = 'OUT';
                $CashTransactionLogto->amount = -($transaction->amount);
                $CashTransactionLogto->status = $transaction->status;
                $CashTransactionLogto->approved_by =$transaction->approved_by;
                $CashTransactionLogto->transaction_ref_id = $transaction->id;
                $CashTransactionLogto->cash_register_id = $transaction->request_to_ref_id;
                $CashTransactionLogto->remark =$transaction->remark;  
                $CashTransactionLogto->date = date('Y-m-d');
                $CashTransactionLogto->time =date('h:i:s'); 
                $CashTransactionLogto->save();
                $this->cashPointSummaryUpdate($CashTransactionLogto->cash_point_id,$CashTransactionLogto->cash_point_name,$CashTransactionLogto->store_id,$CashTransactionLogto->v_id,$CashTransactionLogto->session_id);  

                // From Cash Transaction Log

                $transBehaviourInDetails = getCashTranscationType($v_id, $transaction->request_from_id, 'IN');

                $CashTransactionLogfrom  = new  CashTransactionLog;
                $CashTransactionLogfrom->v_id = $v_id;
                $CashTransactionLogfrom->store_id = $store_id;
                $CashTransactionLogfrom->session_id =$transaction->session_id;
                $CashTransactionLogfrom->logged_session_user_id = $transaction->request_from_user;
                $CashTransactionLogfrom->cash_point_id = $transaction->request_from_id;
                $CashTransactionLogfrom->cash_point_name = $transaction->request_from;
                $CashTransactionLogfrom->transaction_type = $transBehaviourInDetails->type;
                $CashTransactionLogfrom->transaction_behaviour = 'IN';
                $CashTransactionLogfrom->amount = $transaction->amount;
                $CashTransactionLogfrom->transaction_ref_id = $transaction->id;
                $CashTransactionLogfrom->cash_register_id = $transaction->request_ref_id;
                $CashTransactionLogfrom->status = $transaction->status;
                $CashTransactionLogfrom->approved_by =$transaction->approved_by;
                $CashTransactionLogfrom->remark =$transaction->remark;  
                $CashTransactionLogfrom->date =date('Y-m-d');
                $CashTransactionLogfrom->time =date('h:i:s'); 
                $CashTransactionLogfrom->save();
                $this->cashPointSummaryUpdate($CashTransactionLogfrom->cash_point_id,$CashTransactionLogfrom->cash_point_name,$CashTransactionLogfrom->store_id,$CashTransactionLogfrom->v_id,$CashTransactionLogfrom->session_id);
                

                return response()->json(['status' =>'success', 'message' =>'Request approved successfully.'], 200);
            }else{
             
              return response()->json(['status' =>'success', 'message' =>'Request has been dismissed '], 200);
       
            }
        }else{
            return response()->json(['status' => 'fail', 'message' =>'Some error has occurred Plz try again'], 200);
        }
  }

  public function getTrnsastionHistory(Request $request){
 
      $currenthistory =$this->getCurrentTransactions($request);
      $v_id               =  $request->v_id;
      $store_id           =  $request->store_id;
      $udidtoken          =  $request->udidtoken;
      $vu_id              =  $request->vu_id;
      $cash_register_id   = $this->getCashRegister($udidtoken,$store_id,$v_id);
      $session_id         = $this->currentSessionId($udidtoken,$store_id,$v_id,$vu_id);
      $transactionshistory   =CashTransactionLog::leftjoin('cash_transactions','cash_transactions.id','cash_transaction_logs.transaction_ref_id')
                                                ->select('cash_transaction_logs.id','cash_transaction_logs.transaction_behaviour','cash_transaction_logs.amount','cash_transaction_logs.remark','cash_transactions.request_from','cash_transactions.in_Cash_point_type','cash_transaction_logs.time','cash_transactions.request_from_user','cash_transaction_logs.date','cash_transactions.request_to','cash_transactions.request_from_user','cash_transaction_logs.cash_point_name','cash_transactions.request_to_user')
                                                ->where('cash_transaction_logs.store_id',$store_id) 
                                                ->where('cash_transaction_logs.v_id',$v_id)
                                                ->where('cash_transaction_logs.cash_register_id',$cash_register_id)
                                                ->where('cash_transaction_logs.session_id',$session_id)
                                                ->where('cash_transaction_logs.transaction_type','!=','SALES')
                                                ->where('cash_transaction_logs.transaction_type','!=','RETURN')
                                                ->orderBy('id','desc')
                                                ->get(); 
      if($transactionshistory)
      {
            $approvetransactionshistory=[];
            foreach ($transactionshistory as $key => $transactions)
            {
                  if($transactions->transaction_behaviour=="IN")
                  {
                     $transactionType = "Cash In";
                  }else{
                     $transactionType = "Cash Out";
                  }
                  if($transactions->request_from == $transactions->cash_point_name){
                    $source =  $transactions->request_to;
                    $casheir_id  = $transactions->request_to_user;
                  }else{
                    $source =  $transactions->request_from;
                    $casheir_id = $transactions->request_from_user;
                  }  
                  if($transactions->in_Cash_point_type=="Terminal" AND $casheir_id != NULL)
                  {
                     $Cashier = $this->getcashireName($casheir_id);
                  }else{
                     $Cashier = '';
                  }
                 $approvetransactionshistory[] =  array(
                                                        'id'=>$transactions->id,
                                                        'source'=>$source,
                                                        'amount' =>$transactions->amount,
                                                        'cashier_name'=>$Cashier,
                                                        'transaction_type'=>$transactionType,
                                                        'remarks' =>$transactions->remark,
                                                        'time'  =>$transactions->time,
                                                        'date'  => $transactions->date,
                                                        'status'=>'APPROVED',
                                                     );
          }
      }else{
           $approvetransactionshistory=[];
      } 
        $transactionshistory =array_merge($currenthistory,$approvetransactionshistory);
      if(count($transactionshistory)>0){
           $data = $transactionshistory;
      }else{
          $data = [];
      }
    return response()->json(['status' => 'terminal-transactions-history', 'data' => $data], 200);
  }
  private function getCashRegister($udidtoken,$store_id,$v_id){
    $terminal_id  = CashRegister::select('id')
                                 ->where('udidtoken',$udidtoken)
                                 ->where('store_id',$store_id)
                                 ->where('v_id',$v_id)
                                 ->first();
    return  $terminal_id->id;
  }
  private function getcashireName($id){

      $cashiredetails = Vendor::select('first_name' ,'last_name')
                               ->where('id',$id)
                               ->first();
      return  $cashiredetails->first_name. ' ' .$cashiredetails->last_name;
  }
  private function currentSessionId($udidtoken,$store_id,$v_id,$vu_id){

      $cash_register_id =  get_terminal_id($store_id,$v_id,$udidtoken);
      $sessiondetails   =  SettlementSession::select('id','status','vu_id')
                                              ->where('v_id',$v_id)
                                              ->where('store_id',$store_id)
                                              ->where('vu_id',$vu_id)
                                              ->where('cash_register_id',$cash_register_id)
                                              ->orderBy('id','desc')
                                              ->first();
     return $sessiondetails->id;                                                
  }
  public function cashPointSummaryUpdate($cash_point_id,$cash_point_name,$store_id,$v_id,$session_id){

          $cashPoint= CashPoint::where('id',$cash_point_id)
                                ->where('store_id',$store_id)
                                ->where('v_id',$v_id)
                                ->first();
          $currentdate    = date('Y-m-d');
          $cashPointSummary =CashPointSummary::where('cash_point_id',$cash_point_id)
                                               ->where('store_id',$store_id)
                                               ->where('v_id',$v_id)
                                               ->where('session_id',$session_id)
                                               ->where('date',$currentdate)
                                               ->first();
          $opening = '';                                     
          if(empty($cashPointSummary)){

               $lastcashsummary=  CashPointSummary::where('v_id',$v_id)
                                                  ->where('store_id',$store_id)
                                                  ->where('cash_point_id',$cash_point_id)
                                                  ->where('session_id',$session_id)
                                                  ->orderby('date','DESC')
                                                  ->first();
              if(empty($lastcashsummary)){
                   $opening = '0.00';
                   $closing = '0.00';
              }else{
                    $opening = $lastcashsummary->closing;
                    $closing  =$lastcashsummary->closing;
              }

               $todayCashSummary     =  new CashPointSummary;
               $todayCashSummary->store_id = $store_id;
               $todayCashSummary->v_id      = $v_id;
               $todayCashSummary->cash_point_id =$cash_point_id;
               $todayCashSummary->cash_point_name=$cash_point_name;
               $todayCashSummary->session_id   =$session_id;
               $todayCashSummary->opening   =$opening;
               $todayCashSummary->closing      =$closing;
               $todayCashSummary->date    =date('Y-m-d');
               $todayCashSummary->time   =date('h:i:s'); 
               $todayCashSummary->save(); 
               $opening   = $todayCashSummary->opening;  

               $cashPointSummary = $todayCashSummary;
          }
           
          $inCash     =   CashTransactionLog::where('v_id',$v_id)
                                            ->where('store_id',$store_id)
                                            ->where('cash_point_id',$cash_point_id)
                                            ->where('transaction_behaviour','IN')
                                            ->where('date',$currentdate)
                                            ->where('session_id',$session_id)
                                            ->sum('amount');
          $outCash    =   CashTransactionLog::where('v_id',$v_id)
                                            ->where('store_id',$store_id)
                                            ->where('cash_point_id',$cash_point_id)
                                            ->where('transaction_behaviour','OUT')
                                            ->where('date',$currentdate)
                                            ->where('session_id',$session_id)
                                            ->sum('amount');
          $totalcash  =   CashTransactionLog::where('v_id',$v_id)
                                            ->where('store_id',$store_id)
                                            ->where('cash_point_id',$cash_point_id)
                                            ->where('date',$currentdate)
                                            ->where('session_id',$session_id)
                                            ->sum('amount');
          if(empty($cashPointSummary)){                     
              $closingCash =    ($totalcash+$opening);
           }else{ 
              $closingCash =    ($totalcash+$cashPointSummary->opening);
          }
          if($cashPoint->cash_point_type=="Terminal"){
                $totalcashin =   (float)$inCash-(float)$cashPointSummary->opening;
                $cashclosing  =  $totalcash;   
          }else{
                $totalcashin = $inCash;
                $cashclosing  =$closingCash;
          }
          $cashPointSummary    = CashPointSummary::where('v_id',$v_id)
                                                 ->where('store_id',$store_id)
                                                 ->where('cash_point_id',$cash_point_id)
                                                 ->where('date',$currentdate)
                                                 ->where('session_id',$session_id)
                                                 ->update([
                                                          'pay_in' =>$totalcashin,
                                                          'pay_out'=>$outCash,
                                                          'closing'=>$cashclosing
                                                  ]);                                        
  } 
  public function closeSession($store_id,$v_id,$terminal_id,$vu_id,$session_id,$closing_balance){
        $docno = generateDocNo($store_id);
        $currentTerminalCashPoint = CashPoint::where('store_id',$store_id)
                                              ->where('v_id',$v_id)
                                              ->where('ref_id',$terminal_id)
                                              ->first();
                                        
        $currentSessionAmount=$this->currentSessioncashPointCash($store_id,$v_id,$currentTerminalCashPoint->id,$session_id); 
        $totalsaleCash     = $this->sessionCashPointCashSale($store_id,$v_id,$currentTerminalCashPoint->id,$session_id);
        if($totalsaleCash){
           $saleamount = $totalsaleCash; 
        }else{
          $saleamount = $totalsaleCash;   
        }
        if($currentSessionAmount){
           $totalamount = $currentSessionAmount;
        }else{
          $totalamount = '0.00'; 
        }
        $data = [ 'totalamount'=>$totalamount,
                  'total_sales'=>$saleamount,
                ];
        $storeCashPoint  = storeCashPoint($store_id,$v_id);
        $transBehaviourDetails = getCashTranscationType($v_id, $currentTerminalCashPoint->id, 'OUT');
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
                                                      'transaction_behaviour'=>'OUT',
                                                      'transaction_type'=> $transBehaviourDetails->type,
                                                      'in_Cash_point_type'=>$transBehaviourDetails->cash_point_type,
                                                      'amount'=>$closing_balance,
                                                      'doc_no'=>$docno,
                                                      'status'=>'PENDING',
                                                      'date' =>date('Y-m-d'),
                                                      'time'=>date('h:i:s'),
                                                      ]);
        return $data;                                                       
  }
  public function currentSessioncashPointCash($store_id,$v_id,$cash_point_id,$session_id){

      $availableCash=CashTransactionLog::where('v_id',$v_id)
                                        ->where('store_id',$store_id)
                                        ->where('cash_point_id',$cash_point_id)
                                        ->where('session_id',$session_id)
                                        ->sum('amount'); 
      return $availableCash;                 
  }
  public function sessionCashPointCashSale($store_id,$v_id,$cash_point_id,$session_id){

    $availableCash=CashTransactionLog::where('v_id',$v_id)
                                      ->where('store_id',$store_id)
                                      ->where('cash_point_id',$cash_point_id)
                                      ->where('session_id',$session_id)
                                      ->where('transaction_type','SALES')
                                      ->sum('amount'); 
    return $availableCash;
  }

  public function updateOutTransactionStatus(Request $request)
  {
        $v_id               =  $request->v_id;
        $store_id           =  $request->store_id;
        $udidtoken          =  $request->udidtoken;
        $vu_id              =  $request->vu_id;
        $transaction_id     =  $request->transaction_id;
        $status             =  $request->status;
        $sessiondetails = SettlementSession::select('id','status','vu_id')
                                            ->where('v_id',$v_id)
                                            ->where('store_id',$store_id)
                                            ->where('vu_id',$vu_id)
                                            ->orderBy('id','desc')
                                            ->first();
        $transaction = CashTransaction::find($transaction_id);       
        if($transaction){
            $availableCash=$this->currentSessioncashPointCash($store_id,$v_id,$transaction->request_to_id,$sessiondetails->id);

            if($status=="APPROVED" && $transaction->transaction_behaviour=='IN' &&$transaction->amount>$availableCash)
            {
              
                $transaction->status = 'DISMISSED';
                $transaction->approved_by = $vu_id;  
                $transaction->save(); 
                return response()->json(['status' => 'fail', 'type'=>'insufficient funds', 'message' =>'This terminal does not have sufficient funds to perform this action.'], 200);
            }else{  
               $transaction->status =  $status;
               $transaction->approved_by = $vu_id;  
               $transaction->save();  
            }
            if($transaction->status == "APPROVED") { 

                // From Cash Transaction Log

                $transBehaviourInDetails = getCashTranscationType($v_id, $transaction->request_from_id, 'OUT');

                $CashTransactionLogfrom  = new  CashTransactionLog;
                $CashTransactionLogfrom->v_id = $v_id;
                $CashTransactionLogfrom->store_id = $store_id;
                $CashTransactionLogfrom->session_id =$transaction->session_id;
                $CashTransactionLogfrom->logged_session_user_id = $transaction->request_from_user;
                $CashTransactionLogfrom->cash_point_id = $transaction->request_from_id;
                $CashTransactionLogfrom->cash_point_name = $transaction->request_from;
                $CashTransactionLogfrom->transaction_type = $transBehaviourInDetails->type;
                $CashTransactionLogfrom->transaction_behaviour = 'OUT';
                $CashTransactionLogfrom->amount = -($transaction->amount);
                $CashTransactionLogfrom->transaction_ref_id = $transaction->id;
                $CashTransactionLogfrom->cash_register_id = $transaction->request_ref_id;
                $CashTransactionLogfrom->status = $transaction->status;
                $CashTransactionLogfrom->approved_by =$transaction->approved_by;
                $CashTransactionLogfrom->remark =$transaction->remark;  
                $CashTransactionLogfrom->date =date('Y-m-d');
                $CashTransactionLogfrom->time =date('h:i:s'); 
                $CashTransactionLogfrom->save();
                $this->cashPointSummaryUpdate($CashTransactionLogfrom->cash_point_id,$CashTransactionLogfrom->cash_point_name,$CashTransactionLogfrom->store_id,$CashTransactionLogfrom->v_id,$CashTransactionLogfrom->session_id);

                 // TO Cash Transaction Log

                $transBehaviourOutDetails = getCashTranscationType($v_id, $transaction->request_to_id, 'IN');

                $CashTransactionLogto  = new  CashTransactionLog;
                $CashTransactionLogto->v_id = $v_id;
                $CashTransactionLogto->store_id = $store_id;
                $CashTransactionLogto->session_id =$sessiondetails->id;
                $CashTransactionLogto->logged_session_user_id = $transaction->request_to_user;
                $CashTransactionLogto->cash_point_id = $transaction->request_to_id;
                $CashTransactionLogto->cash_point_name = $transaction->request_to;
                $CashTransactionLogto->transaction_type = $transBehaviourOutDetails->type;
                $CashTransactionLogto->transaction_behaviour = 'IN';
                $CashTransactionLogto->amount = $transaction->amount;
                $CashTransactionLogto->status = $transaction->status;
                $CashTransactionLogto->approved_by =$transaction->approved_by;
                $CashTransactionLogto->transaction_ref_id = $transaction->id;
                $CashTransactionLogto->cash_register_id = $transaction->request_to_ref_id;
                $CashTransactionLogto->remark =$transaction->remark;  
                $CashTransactionLogto->date = date('Y-m-d');
                $CashTransactionLogto->time =date('h:i:s'); 
                $CashTransactionLogto->save();
                $this->cashPointSummaryUpdate($CashTransactionLogto->cash_point_id,$CashTransactionLogto->cash_point_name,$CashTransactionLogto->store_id,$CashTransactionLogto->v_id,$CashTransactionLogto->session_id); 
                

                return response()->json(['status' =>'success', 'message' =>'Request approved successfully.'], 200);
            }else{
             
              return response()->json(['status' =>'success', 'message' =>'Request has been dismissed '], 200);
       
            }
        }else{
            return response()->json(['status' => 'fail', 'message' =>'Some error has occurred Plz try again'], 200);
        }
  }

  public function updateAllTransactions(Request $request)
  {
    $idsList = json_decode($request->ids);
    foreach ($idsList as $key => $value) {
      $request->request->add([ 'status' => 'DISMISSED', 'transaction_id' => $value ]);
      $this->updateTransactionStatus($request);
    }

    return response()->json(['status' => 'success'], 200);
  }

}
