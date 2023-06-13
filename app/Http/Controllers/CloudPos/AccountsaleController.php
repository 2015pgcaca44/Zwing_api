<?php

namespace App\Http\Controllers\CloudPos;

use App\Http\Controllers\Controller;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\VendorSettingController;  //temp use  getVendorAppSetting
use Illuminate\Http\Request;
use DB;
use App\Order;
use App\User;
use App\CustomerGroupMapping;    
use App\DepRfdTrans;    
use App\Store;  
use App\Voucher;
use App\CrDrSettlementLog;    
use App\Payment;
use App\SettlementSession;
use App\Vendor\VendorRoleUserMapping;
use App\Http\CustomClasses\PrintInvoice;
use App\Vendor;
use App\CashTransactionLog;
use App\CashPoint;
use App\Http\Controllers\SmsController;
use Event;
use App\Events\DepositeRefund;

class AccountsaleController extends Controller
{
    //use VendorFactoryTrait;
    public function __construct()
    {
        $this->middleware('auth',['except' => ['account_sale','payAccountBalanceApprove','getDebitPurchasedList','getCreditNoteList']]);
    }

    public function createDepRfdRrans($request,$params){
        $v_id        = $request->v_id;
        $store_id    = $request->store_id;
        $trans_from  = $request->trans_from;
        $c_id        = !empty($request->c_id)?$request->c_id:$request->user_id;
        $vu_id       = $request->vu_id;
        $remark      = $request->remark;
        //$type      = $request->type;
        $amount      = !empty($params['amount'])?$params['amount']:'';

        if(empty($amount)){
            $amount      = $request->amount;     
        }
        $udidtoken      = $request->udidtoken;
        $trans_type     = !empty($request->tr_type)?$request->tr_type:$request->type;  // tr_type = type
        $trans_src      = '';
        if(!empty($request->invoice_no)){
            $trans_src_ref  = $request->invoice_no;   
        }else{
            $trans_src_ref  = !empty($request->order_id)?$request->order_id:'';   
        }
        $status       = $params['status'];
        if($trans_type == 'Debit'){
            $tran_sub_type = 'Debit-Note';
            $trans_src     = 'Sales-invoice';
            $amount        = -1*$amount;;
        }
        if($trans_type == 'Credit'){
            $tran_sub_type = 'Credit-Note';
            $trans_src     = 'Return-invoice';
        }
        $tran_sub_type = !empty($params['tran_sub_type'])?$params['tran_sub_type']:$tran_sub_type;
        $trans_src     = !empty($params['trans_src'])?$params['trans_src']:$trans_src;
        $acData       = ['v_id'=>$v_id,'src_store_id'=>$store_id,'vu_id'=>$vu_id,'user_id'=>$c_id,'terminal_id'=>get_terminal_id($store_id,$v_id,$udidtoken),'trans_type'=>$trans_type,'trans_sub_type'=>$tran_sub_type,'trans_src'=>$trans_src,'trans_src_ref'=>$trans_src_ref,'amount'=>$amount,'status'=>$status,'remark'=>$remark];
        if(!empty($request->id)){
             $insertData        = DepRfdTrans::where('id',$request->id)->update($acData);
        }else{
            $parm = array('v_id'=>$v_id,'store_id'=>$store_id,'c_id'=>$c_id);
            $acData['doc_no']   = $this->generateDocNo($parm);
            $insertData         = DepRfdTrans::create($acData);    
        }
        return $insertData;
    }//End of DepRfdRransInsert

    public function createVoucher($request,$params){

        $v_id        = $request->v_id;
        $store_id    = $request->store_id;
        $trans_from  = $request->trans_from;
        $c_id        = !empty($request->c_id)?$request->c_id:$request->user_id;
        $vu_id       = $request->vu_id;
        $amount        = !empty($params['amount'])?$params['amount']:'';

        if(empty($amount)){
            $amount      = $request->amount;     
        }
        $dep_ref_trans_ref = !empty($params['dep_ref_trans_ref'])?$params['dep_ref_trans_ref']:'';
        $trans_src_ref     = !empty($params['trans_src_ref'])?$params['trans_src_ref']:'';
        $status            = !empty($params['status'])?$params['status']:'Pending';
        $type              = !empty($params['type'])?$params['type']:'';
        $voucher           = !empty($params['voucher'])?$params['voucher']:'';
        $today_date        = date('Y-m-d H:i:s');
        $next_date         = date('Y-m-d H:i:s' ,strtotime('+30 days', strtotime($today_date)) );
        $next_date         =  !empty($request->valid_till)?$request->valid_till:$next_date;
        
        /*Query start*/
        $data    = ['v_id'=> $v_id , 'store_id' => $store_id ,'user_id'=> $c_id ,'amount' => $amount , 'dep_ref_trans_ref' =>$dep_ref_trans_ref, 'ref_id' => $trans_src_ref , 'status' => $status ,'type' => $type ];
        if(!empty($voucher) && $voucher != ''){
             $insertData  = DB::table('cr_dr_voucher')->where(['v_id'=>$v_id,'store_id'=>$store_id,'voucher_no'=>$voucher,'user_id'=>$c_id])->update($data);
        }else{
            $voucher_no = generateRandomString(6);
            $data['voucher_no']    = $voucher_no;
            $data['effective_at']  = $today_date;
            $data['expired_at']    = $next_date;

            $insertData  = Voucher::create($data);

        }
        return $insertData;
    }//End of createVoucher

    public function createVocherSettLog($request,$params){

        $v_id        = $request->v_id;
        $store_id    = $request->store_id;
        $trans_from  = $request->trans_from;
        $c_id        = !empty($request->c_id)?$request->c_id:$request->user_id;
        $vu_id       = $request->vu_id;
        $amount        = !empty($params['amount'])?$params['amount']:'';
        if(empty($amount)){
            $amount      = $request->amount;     
        }
        $trans_src        = !empty($params['trans_src'])?$params['trans_src']:'';
        $trans_src_ref_id = !empty($params['trans_src_ref_id'])?$params['trans_src_ref_id']:'';
        $order_id         = !empty($params['order_id'])?$params['order_id']:'';
        $status           = !empty($params['status'])?$params['status']:'PROCESS';
       // $trans_type       = !empty($params['trans_type'])?$params['trans_type']:'';
        $trans_type     = !empty($request->tr_type)?$request->tr_type:$request->type;  // tr_type = type
        //dd($trans_type);
        $applied_amount  = $params['applied_amount'];
        $voucher_id      = $params['voucher_id'];
        if($trans_type == 'Debit'){
            $applied_amount         = -1*$applied_amount;;
        }
        $voucherSettData = ['v_id'=>$v_id,'store_id'=>$store_id,'user_id'=>$c_id,'trans_src'=>$trans_src,'trans_src_ref_id'=>$trans_src_ref_id,'order_id'=>$order_id,'applied_amount'=>$applied_amount,'voucher_id'=>$voucher_id,'status'=>$status];
        #CrDrSettlementLog::where('voucher_id',$getVoucher->id)->update(['status'=>'APPLIED']);
        $voucherSett = CrDrSettlementLog::create($voucherSettData);
        return $voucherSett;
    }//End of createVocherSettLog



    public function account_sale(Request $request){

        $v_id       = $request->v_id;
        $store_id   = $request->store_id;
        $trans_from = $request->trans_from;
        $c_id       = $request->c_id;
        $vu_id       = $request->vu_id;
        //$type       = $request->type;
        $amount     = $request->amount;
        $udidtoken  = $request->udidtoken;
        $trans_type = $request->type;
        $trans_src  = '';
        $trans_src_ref   = $request->order_id;
        $credit_issue  = "debit_voucher";
        if($trans_type == 'Credit'){
            return $this->payCustomerBalance($request);
        }

        if(empty($request->order_id)){
         return response()->json(['status' => 'fail' , 'message' => 'Order id not found.' ],200);
        }else{
           $orders = Order::where('order_id', $request->order_id)->first();

           if(!$orders && empty($orders)){
            return response()->json(['status' => 'fail' , 'message' => 'Order not found.' ],200);
           } 
        }
         
        $newVocherData = '';
        $maxCreditLimit = '';
        $customer = User::where('c_id',$c_id)->where('v_id',$v_id)->first();
        if($customer){
            //Group
            foreach ($customer->groups as $custerGroup) {
                if($custerGroup->allow_credit == '1'){
                    $maxCreditLimit = $custerGroup->maximum_credit_limit;
                }
            }
            if(empty($maxCreditLimit)  && $maxCreditLimit=='0'){
                return response()->json(['status' => 'fail' , 'message' => 'There is no credit limit.' ],200);
            }
            //leftJoin('voucher','voucher.dep_ref_trans_ref','dep_rfd_trans.id')->where(['dep_rfd_trans.v_id'=>$v_id,'dep_rfd_trans.src_store_id'=>$store_id,'voucher.user_id'=>$c_id,'dep_rfd_trans.trans_type'=>'Debit'])

            $previousDebitAmount = DepRfdTrans::where(['v_id'=>$v_id,'src_store_id'=>$store_id,'user_id'=>$c_id,'status'=>'Success'])->select(DB::raw("SUM(dep_rfd_trans.amount) as amount"))->first();

            $maxCreditLimit  = $maxCreditLimit+$previousDebitAmount->amount;
            if($maxCreditLimit >= $amount){
                if($trans_type == 'Debit'){
                    $tran_sub_type = 'Debit-Note';
                    $trans_src     = 'Sales-invoice';
                }
                if($trans_type == 'Credit'){
                    $trans_src = 'Invoice-Return';
                }
                $params = array('v_id'=>$v_id,'store_id'=>$store_id,'c_id'=>$c_id);
                $acData       = ['doc_no'=>$this->generateDocNo($params),'v_id'=>$v_id,'src_store_id'=>$store_id,'vu_id'=>$vu_id,'user_id'=>$c_id,'terminal_id'=>get_terminal_id($store_id,$v_id,$udidtoken),'trans_type'=>$trans_type,'trans_sub_type'=>$tran_sub_type,'trans_src'=>$trans_src,'trans_src_ref'=>$trans_src_ref,'amount'=>-1*$amount,'status'=>'Success','trans_from'=>$trans_from];
                $accountSale  = DepRfdTrans::create($acData);

                /*Entry in voucher table*/
                $voucher_no = generateRandomString(6);
                $today_date = date('Y-m-d H:i:s');
                $next_date  =  date('Y-m-d H:i:s' ,strtotime('+30 days', strtotime($today_date)) );
                $voucher    = DB::table('cr_dr_voucher')->insertGetId(['v_id'=> $v_id , 'store_id' => $store_id ,  'user_id'=> $c_id , 'amount' => $amount , 'dep_ref_trans_ref' =>$accountSale->id, 'ref_id' => $trans_src_ref , 'status' => 'Pending' ,'type' => $credit_issue , 'voucher_no' => $voucher_no, 'effective_at' => $today_date   , 'expired_at' => $next_date ]);

                $settlementTransType = '';
                if($trans_type == 'Debit'){
                    $settlementTransType = 'Debit-Note';
                }

                if($trans_type == 'Credit'){
                    $settlementTransType = 'Credit-Note';
                }

                $newSettlemtn = DB::table('cr_dr_settlement_log')->insert(['v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $c_id ,'order_id' => $orders->od_id , 'voucher_id' => $voucher, 'status' => 'APPLIED', 'applied_amount' => -1*$amount,'trans_src'=> $settlementTransType , 'trans_src_ref_id' => $accountSale->id ]);
                /*End of voucher table entry*/

                //update voucher status temp
                
                //$voucherUpdate = Voucher::where('dep_ref_trans_ref',$accountSale->id)->update(['status'=>'Completed']);
                
                //update voucher status temp

                ##### Advance payment adjustment start

                /*$advanceCredit = DepRfdTrans::where('user_id',$c_id)->where('src_store_id',$store_id)->where('v_id',$v_id)->select(DB::raw("SUM(dep_rfd_trans.amount) as amount"))->first();
                if(!empty($advanceCredit) && $advanceCredit->amount > 0){

                    if($advanceCredit->amount >= $amount){
                        $creditAmt     = $debitAmt-$partialyCredit->amount; 
                        $params = array('v_id'=>$v_id,'store_id'=>$store_id,'c_id'=>$c_id);
                        $acData  = ['doc_no'=>$this->generateDocNo($params),'v_id'=>$v_id,'src_store_id'=>$store_id,'user_id'=>$c_id,'terminal_id'=>get_terminal_id($store_id,$v_id,$udidtoken),'trans_type'=>'Credit','trans_sub_type'=>$tran_sub_type,'trans_src'=>$trans_src,'trans_src_ref'=>$trans_src_ref,'amount'=>$creditAmt];
                        $accountSale  = DepRfdTrans::create($acData);

                    }

                }*/
                ##### Advance payment adjustment end
                //return response()->json(['status' => 'success' , 'id' => $acData],200);
                $accData = [ 'debit_note' => $accountSale->doc_no, 'issue_on' => date('d M Y', strtotime($accountSale->created_at)), 'amount' => $accountSale->amount, 'id' => $accountSale->id ];   
                $orderC = new OrderController;
                $order_arr = $orderC->getOrderResponse(['order' => $orders , 'v_id' => $v_id , 'trans_from' => $trans_from ]) ;
                return response()->json(['status' => 'success' , 'message' => 'Debit amount succcessfully' , 'data' => $accData,'order_summary' => $order_arr]);
                            
            }else{
           return response()->json(['status' => 'fail' , 'message' => 'Amount is greater than of credit limit.' ],200);
            }
        }else{
             return response()->json(['status' => 'fail' , 'message' => 'Customer not exist' ],200);
        }

    }//End of account_sale


    public function payCustomerBalance(Request $request){
        $v_id       = $request->v_id;
        $store_id   = $request->store_id;
        $trans_from = $request->trans_from;
        $c_id       = $request->c_id;
        $vu_id       = $request->vu_id;
        //$type       = $request->type;
        $amount     = $request->amount;
        $udidtoken  = $request->udidtoken;
        $trans_type = $request->trans_type;
        $trans_src  = '';
        $trans_src_ref   = '';
        $message = '';
        if($trans_type == 'Credit'){
            $tran_sub_type = 'Deposit-DN';
            $trans_src     = 'self';
        }
        //$credit_issue  = "debit_voucher";
        $existDeposite= DepRfdTrans::join('cr_dr_voucher as cdv','cdv.dep_ref_trans_ref','dep_rfd_trans.id')->where(['dep_rfd_trans.v_id'=>$v_id,'dep_rfd_trans.src_store_id'=>$store_id,'dep_rfd_trans.user_id'=>$c_id,'dep_rfd_trans.trans_type'=>'Debit'])->whereIn('cdv.status',['Completed','Partial settled']);
        $countDeposite = $existDeposite;

        if($countDeposite->count() > 0){
            $depositeData  = $existDeposite->select('dep_rfd_trans.*','cdv.status as status')->orderBy('dep_rfd_trans.id','asc')->get();
            foreach($depositeData as $deposite){
                $voucherId = 0;
                $voucherStatus = '';
                $getVoucher = Voucher::where('dep_ref_trans_ref',$deposite->id)->first();
                if($getVoucher){
                    $voucherId = $getVoucher->id;
                }
                $debitAmt = abs($deposite->amount);
                if($deposite->status == 'Partial settled'){

                    $partialyCredit = DepRfdTrans::where('trans_src_ref',$deposite->doc_no)->where('user_id',$c_id)->where('src_store_id',$store_id)->where('v_id',$v_id)->select(DB::raw("SUM(dep_rfd_trans.amount) as amount"))->first();

                    if($amount >= $partialyCredit->amount){

                        $trans_src_ref = $deposite->doc_no;
                        $creditAmt     = $debitAmt-$partialyCredit->amount; 
                        $params = array('v_id'=>$v_id,'store_id'=>$store_id,'c_id'=>$c_id);
                        $acData  = ['doc_no'=>$this->generateDocNo($params),'v_id'=>$v_id,'src_store_id'=>$store_id,'user_id'=>$c_id,'vu_id'=>$vu_id,'terminal_id'=>get_terminal_id($store_id,$v_id,$udidtoken),'trans_type'=>$trans_type,'trans_sub_type'=>$tran_sub_type,'trans_src'=>$trans_src,'trans_src_ref'=>$trans_src_ref,'amount'=>$creditAmt];

                         $accountSale  = DepRfdTrans::create($acData);
                        /*Voucher and settelment*/
                        //$voucherUpdate  = Voucher::where('dep_ref_trans_ref',$deposite->id)->update(['status'=>'Settled']);
                        $voucherStatus =  'Settled';
                        $voucherSettData = ['v_id'=>$v_id,'store_id'=>$store_id,'user_id'=>$c_id,'trans_src'=>'Deposite','trans_src_ref_id'=>$accountSale->id,'order_id'=>$accountSale->trans_src_ref,'applied_amount'=>$creditAmt,'voucher_id'=>$voucherId,'status'=>'APPLIED']; 
                         
                         //End
                         $amount = $amount-$creditAmt;

                    }else{
                        if($amount > 0){
                            $trans_src_ref = $deposite->doc_no;
                            $creditAmt     = $amount; 
                            $params = array('v_id'=>$v_id,'store_id'=>$store_id,'c_id'=>$c_id);
                            $acData  = ['doc_no'=>$this->generateDocNo($params),'v_id'=>$v_id,'src_store_id'=>$store_id,'user_id'=>$c_id,'vu_id'=>$vu_id,'terminal_id'=>get_terminal_id($store_id,$v_id,$udidtoken),'trans_type'=>$trans_type,'trans_sub_type'=>$tran_sub_type,'trans_src'=>$trans_src,'trans_src_ref'=>$trans_src_ref,'amount'=>$creditAmt];
                            $accountSale  = DepRfdTrans::create($acData);
                            //$voucherUpdate  = Voucher::where('dep_ref_trans_ref',$deposite->id)->update(['status'=>'Partial settled']);
                             $voucherStatus =  'Partial settled';
                             $voucherSettData = ['v_id'=>$v_id,'store_id'=>$store_id,'user_id'=>$c_id,'trans_src'=>'Deposite','trans_src_ref_id'=>$accountSale->id,'order_id'=>$accountSale->trans_src_ref,'applied_amount'=>$creditAmt,'voucher_id'=>$voucherId,'status'=>'PROCESS']; 

                            $amount = $amount-$creditAmt;
                        }
                    }
                }else{

                // $previousDebitAmount = DepRfdTrans::where(['v_id'=>$v_id,'src_store_id'=>$store_id,'user_id'=>$c_id])->select(DB::raw("SUM(dep_rfd_trans.amount) as amount"))->first();
                // $amount  =  $amount- $previousDebitAmount->amount;

                if($amount >= $debitAmt){
                    $trans_src_ref = $deposite->doc_no;
                    $creditAmt     = $debitAmt; 
                    $params = array('v_id'=>$v_id,'store_id'=>$store_id,'c_id'=>$c_id);
                    $acData  = ['doc_no'=>$this->generateDocNo($params),'v_id'=>$v_id,'src_store_id'=>$store_id,'user_id'=>$c_id,'vu_id'=>$vu_id,'terminal_id'=>get_terminal_id($store_id,$v_id,$udidtoken),'trans_type'=>$trans_type,'trans_sub_type'=>$tran_sub_type,'trans_src'=>$trans_src,'trans_src_ref'=>$trans_src_ref,'amount'=>$creditAmt];
                     $accountSale  = DepRfdTrans::create($acData);
                     //$voucherUpdate  = Voucher::where('dep_ref_trans_ref',$deposite->id)->update(['status'=>'Settled']);
                    $voucherStatus =  'Settled';
                    $voucherSettData = ['v_id'=>$v_id,'store_id'=>$store_id,'user_id'=>$c_id,'trans_src'=>'Deposite','trans_src_ref_id'=>$accountSale->id,'order_id'=>$accountSale->trans_src_ref,'applied_amount'=>$creditAmt,'voucher_id'=>$voucherId,'status'=>'APPLIED']; 

                     $amount = $amount-$creditAmt;
                }else{
                    if($amount > 0){
                        $trans_src_ref = $deposite->doc_no;
                        $creditAmt     = $amount; 
                        $params = array('v_id'=>$v_id,'store_id'=>$store_id,'c_id'=>$c_id);
                        $acData  = ['doc_no'=>$this->generateDocNo($params),'v_id'=>$v_id,'src_store_id'=>$store_id,'user_id'=>$c_id,'vu_id'=>$vu_id,'terminal_id'=>get_terminal_id($store_id,$v_id,$udidtoken),'trans_type'=>$trans_type,'trans_sub_type'=>$tran_sub_type,'trans_src'=>$trans_src,'trans_src_ref'=>$trans_src_ref,'amount'=>$creditAmt];
                        $accountSale  = DepRfdTrans::create($acData);
                        //$voucherUpdate= Voucher::where('dep_ref_trans_ref',$deposite->id)->update(['status'=>'Partial settled']);
                        $voucherStatus =  'Partial settled';
                        $voucherSettData = ['v_id'=>$v_id,'store_id'=>$store_id,'user_id'=>$c_id,'trans_src'=>'Deposite','trans_src_ref_id'=>$accountSale->id,'order_id'=>$accountSale->trans_src_ref,'applied_amount'=>$creditAmt,'voucher_id'=>$voucherId,'status'=>'PROCESS'];
                        $amount = $amount-$creditAmt;
                    }
                }
               }

                $getVoucher->status = $voucherStatus;
                $getVoucher->save();
                $voucherSett = CrDrSettlementLog::create($voucherSettData);

             }
             if($amount > 0){
                $params = array('v_id'=>$v_id,'store_id'=>$store_id,'c_id'=>$c_id);
                $acData  = ['doc_no'=>$this->generateDocNo($params),'v_id'=>$v_id,'src_store_id'=>$store_id,'user_id'=>$c_id,'vu_id'=>$vu_id,'terminal_id'=>get_terminal_id($store_id,$v_id,$udidtoken),'trans_type'=>$trans_type,'trans_sub_type'=>$tran_sub_type,'trans_src'=>$trans_src,'trans_src_ref'=>'','amount'=>$amount];
                $accountSale  = DepRfdTrans::create($acData);
             }

                // $params = array('v_id'=>$v_id,'store_id'=>$store_id,'c_id'=>$c_id);
                // $acData  = ['doc_no'=>$this->generateDocNo($params),'v_id'=>$v_id,'src_store_id'=>$store_id,'user_id'=>$c_id,'terminal_id'=>get_terminal_id($store_id,$v_id,$udidtoken),'trans_type'=>$trans_type,'trans_sub_type'=>$tran_sub_type,'trans_src'=>$trans_src,'trans_src_ref'=>$trans_src_ref,'amount'=>$amount];
                // $accountSale  = DepRfdTrans::create($acData);
               $message = 'good';
        }else{

            $params = array('v_id'=>$v_id,'store_id'=>$store_id,'c_id'=>$c_id);
            $acData  = ['doc_no'=>$this->generateDocNo($params),'v_id'=>$v_id,'src_store_id'=>$store_id,'user_id'=>$c_id,'vu_id'=>$vu_id,'terminal_id'=>get_terminal_id($store_id,$v_id,$udidtoken),'trans_type'=>$trans_type,'trans_sub_type'=>$tran_sub_type,'trans_src'=>$trans_src,'trans_src_ref'=>$trans_src_ref,'amount'=>$amount];
            $accountSale  = DepRfdTrans::create($acData);
            $message = 'good';
        }
        if($message == 'good'){
            return response()->json(['status' => 'success' , 'message' => 'Amount credited succcessfully']);
        }

        
    }//End of payCustomerBalance

    public function payAccountBalance(Request $request){

        $v_id       = $request->v_id;
        $store_id   = $request->store_id;
        $trans_from = $request->trans_from;
        $c_id       = $request->c_id;
        $vu_id      = $request->vu_id;
        $udidtoken  = $request->udidtoken;
        $trans_type = $request->trans_type;
        $amount     = $request->amount;
        $debitTransArr   = $request->debit_trans;
        $payment_method  = $request->method;
        $cash_collected  = $request->cash_collected;
        $cash_return     = $request->cash_return;
        $bank            = $request->bank;
        $wallet          = $request->wallet;
        $vpa             = $request->vpa;
        $error_description= $request->error_description;
        $payment_gateway_type = ''; 
        $payment_gateway_device_type = '';
        $trans_src       = '';
        $trans_src_ref   = '';
        $session_id      = 0;
        $terminal_id     = get_terminal_id($store_id,$v_id,$udidtoken);
        $status          = 'Process';
        $payment_type    = 'full';
        $gateway_response= '';
        $current_date = date('Y-m-d'); 
        $debitTranData = json_decode($debitTransArr);  //all transaction array
        if($trans_type == 'Credit'){
            $tran_sub_type = 'Deposit-DN';
            $trans_src     = 'self';
        }
        if($request->has('payment_gateway_device_type')){
            $payment_gateway_device_type = $request->payment_gateway_device_type;
        } 
        if($request->has('payment_gateway_type')){
            $payment_gateway_type = $request->payment_gateway_type;
        }

        $debitTransColl  = collect($debitTranData);
        $TotalAmt        = $debitTransColl->sum('amount');

        if(abs($amount) != abs($TotalAmt)){
            return response()->json(['status' => 'fail' , 'message' => 'Pay amount is not equal to sum of debit amount'],200);
        }
        

        if($amount>0){
            $params = array('v_id'=>$v_id,'store_id'=>$store_id,'c_id'=>$c_id);
            $acData  = ['doc_no'=>$this->generateDocNo($params),'v_id'=>$v_id,'src_store_id'=>$store_id,'user_id'=>$c_id,'vu_id'=>$vu_id,'terminal_id'=>$terminal_id,'trans_type'=>$trans_type,'trans_sub_type'=>$tran_sub_type,'trans_src'=>$trans_src,'trans_src_ref'=>$trans_src_ref,'amount'=>$amount,'status',$status];
            $creditNote  = DepRfdTrans::create($acData);

            $settlementSession = SettlementSession::select('id')->where(['v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id ,'trans_from' => $trans_from, 'settlement_date' => $current_date ])->orderBy('opening_time','desc')->first();
            if($settlementSession){
                $session_id = $settlementSession->id;
            }
            if(!empty($creditNote)){
                $status = 'success';
            }
            $payment = new Payment;
            $payment->store_id = $store_id;
            $payment->v_id = $v_id;
            $payment->order_id = $creditNote->doc_no;
            $payment->user_id = $c_id;
            $payment->session_id =$session_id;
            $payment->terminal_id =$terminal_id;
            $payment->pay_id = 'DEP_'.$creditNote->id;
            $payment->amount = $amount;
            $payment->method = $payment_method;
            $payment->cash_collected = $cash_collected;
            $payment->cash_return = $cash_return;
            $payment->payment_invoice_id = '';
            $payment->bank = $bank;
            $payment->wallet = $wallet;
            $payment->vpa = $vpa;
            $payment->error_description = $error_description;
            $payment->status = $status;
            $payment->payment_type = $payment_type;
            $payment->payment_gateway_type = $payment_gateway_type;
            $payment->payment_gateway_device_type = $payment_gateway_device_type;
            $payment->gateway_response = json_encode($gateway_response);
            $payment->trans_type = 'Deposite';
            $payment->date = date('Y-m-d');
            $payment->time = date('H:i:s');
            $payment->month= date('m');
            $payment->year = date('Y');
            $payment->save();
        }
        if(!empty($creditNote) && !empty($payment) && $debitTranData > 0 ){
            $where = array('v_id'=>$v_id,'user_id'=>$c_id);
            foreach($debitTranData as $dbt_data){
              $transaction = DepRfdTrans::where('doc_no',$dbt_data->doc_no)->where('src_store_id',$store_id)->where($where)->first();
              $getVoucher  = Voucher::where('dep_ref_trans_ref',$transaction->id)->where('store_id',$store_id)->where($where)->first();

              $creditAmt     =  $dbt_data->amount;
              $depositeAmt   = abs($dbt_data->amount);
              if($getVoucher->status == 'Partial settled'){
                $debitValue   = $getVoucher->settelment->where('status','PROCESS')->sum('applied_amount');
                //print_r($debitValue);
               // $debitValue    = abs($transaction->amount);  
              }else{
                $debitValue    = abs($transaction->amount);  
              } 
            if($depositeAmt==$debitValue){
                 $voucherStatus =  'Settled';
                 $voucherSettData = ['v_id'=>$v_id,'store_id'=>$store_id,'user_id'=>$c_id,'trans_src'=>'Deposite','trans_src_ref_id'=>$creditNote->id,'order_id'=>$transaction->trans_src_ref,'applied_amount'=>$creditAmt,'voucher_id'=>$getVoucher->id,'status'=>'APPLIED']; 
                 CrDrSettlementLog::where('voucher_id',$getVoucher->id)->update(['status'=>'APPLIED']);
                }else{
                  $voucherStatus =  'Partial settled';
                  $voucherSettData = ['v_id'=>$v_id,'store_id'=>$store_id,'user_id'=>$c_id,'trans_src'=>'Deposite','trans_src_ref_id'=>$creditNote->id,'order_id'=>$transaction->trans_src_ref,'applied_amount'=>$creditAmt,'voucher_id'=>$getVoucher->id,'status'=>'PROCESS'];
                }
                $getVoucher->status = $voucherStatus;
                $getVoucher->save();
                $voucherSett = CrDrSettlementLog::create($voucherSettData);
            }
          return response()->json(['status' => 'success' , 'message' => 'Amount credited succcessfully'],200);
        }else{
         return response()->json(['status' => 'fail' , 'message' => 'No debit transaction found.'],200);
        }

    }//End of payCustomerBalance


    public function payAccountBalanceRequest(Request $request){


        //This function belong to pay balance agins debit note / adhoc credit note 
        $v_id       = $request->v_id;
        $store_id   = $request->store_id;
        $trans_from = $request->trans_from;
        $c_id       = $request->user_id;
        $vu_id      = $request->vu_id;
        $udidtoken  = $request->udidtoken;
        $trans_type = $request->type;
        $amount     = $request->amount;
        $debitTransArr   = $request->debit_trans;
        $payment_method  = $request->method;
        $cash_collected  = $request->cash_collected;
        $cash_return     = $request->cash_return;
        $bank            = $request->bank;
        $wallet          = $request->wallet;
        $valid_till      = !empty($request->valid_till)?$request->valid_till:'';

        $vpa             = $request->vpa;
        $error_description= $request->error_description;
        $payment_gateway_type = ''; 
        $payment_gateway_device_type = '';
        $trans_src       = '';
        $trans_src_ref   = '';
        $session_id      = 0;
        $terminal_id     = get_terminal_id($store_id,$v_id,$udidtoken);
        $status          = 'Process';
        $payment_type    = 'full';
        $gateway_response= '';
        $current_date = date('Y-m-d'); 
        $debitTranData = json_decode($debitTransArr);  //all transaction array
        if($trans_type == 'Credit' || $trans_type == 'account_deposite'){
            $trans_type    = 'Credit'; 
            $tran_sub_type = 'Deposit-DN';
            $trans_src     = 'self';
        }
        if($trans_type == 'adhoc_credit_note'){
            $trans_type    = 'Credit'; 
            $tran_sub_type = 'Credit-Note';
            $trans_src     = 'self';
        }
         
        if($request->has('payment_gateway_device_type')){
            $payment_gateway_device_type = $request->payment_gateway_device_type;
        } 
        if($request->has('payment_gateway_type')){
            $payment_gateway_type = $request->payment_gateway_type;
        }

        $debitTransColl  = collect($debitTranData);
        $TotalAmt        = $debitTransColl->sum('amount');

        /*if(abs($amount) != abs($TotalAmt)){
            return response()->json(['status' => 'fail' , 'message' => 'Pay amount is not equal to sum of debit amount'],200);
        }*/

        if($amount>0){
            $settelmentLogTransSrc = 'Deposite';
            if($trans_type == 'refund_credit_note'){
                $tran_sub_type  = 'Refund-CN';
                $trans_src      = 'self';
                $trans_type     = 'Debit'; 
                // $amount        = -1*$amount;
                $settelmentLogTransSrc = 'Refund-CN';
            }else if($trans_type == 'adhoc_credit_note'){
                $settelmentLogTransSrc = 'Credit-Note';
            }
            $params = array('v_id'=>$v_id,'store_id'=>$store_id,'c_id'=>$c_id);

            $acData  = ['doc_no'=> $this->generateDocNo($params),'v_id'=>$v_id,'src_store_id'=>$store_id,'user_id'=>$c_id,'vu_id'=>$vu_id,'terminal_id'=>$terminal_id,'trans_type'=>$trans_type,'trans_sub_type'=>$tran_sub_type,'trans_src'=>$trans_src,'trans_src_ref'=>$trans_src_ref,'amount'=>$amount,'status'=>$status];

            //print_r($acData);die;

            //$creditNote  = DepRfdTrans::create($acData);
            if($request->type == 'refund_credit_note'){
                $request->merge(['tr_type' => 'Debit']);
            }else{
                $request->merge(['tr_type' => 'Credit']);    
            }
            
            $paramsVr   = array('status'=> $status,'tran_sub_type'=>$tran_sub_type,'trans_src'=>'self','amount'=>$amount);
            $creditNote = $this->createDepRfdRrans($request,$paramsVr);

            //dd($creditNote);

            if(!empty($creditNote)){
                $where = array('v_id'=>$v_id,'user_id'=>$c_id);
                if(!empty($debitTranData) && count($debitTranData) > 0){
                foreach($debitTranData as $dbt_data){
                    // echo $dbt_data->doc_no;
                  $transaction = DepRfdTrans::where('doc_no',trim($dbt_data->doc_no))
                    //->where('src_store_id',$store_id)
                    ->where($where)->first();
                  // dd($transaction);
                  $getVoucher  = Voucher::where('dep_ref_trans_ref',$transaction->id)
                                //->where('store_id',$store_id)
                                ->where($where)->first();
                  $creditAmt     =  $dbt_data->amount;
                  $depositeAmt   = abs($dbt_data->amount);
                  if($getVoucher->status == 'Partial settled'){
                    $debitValue   = $getVoucher->settelment->where('status','PROCESS')->sum('applied_amount');
                    //print_r($debitValue);
                   // $debitValue    = abs($transaction->amount);  
                  }else{
                    $debitValue    = abs($transaction->amount);  
                  } 
                  /*$voucherSettData = ['v_id'=>$v_id,'store_id'=>$store_id,'user_id'=>$c_id,'trans_src'=>'Deposite','trans_src_ref_id'=>$creditNote->id,'order_id'=>$transaction->trans_src_ref,'applied_amount'=>$creditAmt,'voucher_id'=>$getVoucher->id,'status'=>'PROCESS'];
                  $voucherSett = CrDrSettlementLog::create($voucherSettData);  */

                    $paramsLg    = array('trans_src_ref_id' => $creditNote->id,'order_id'=>$transaction->trans_src_ref,'trans_src' => $settelmentLogTransSrc,'applied_amount'=>$creditAmt,'voucher_id'=>$getVoucher->id,'status'=>'PROCESS');

                    $crDrLog     = $this->createVocherSettLog($request,$paramsLg);

                 }
                }else{
                    $paramsVc = array('dep_ref_trans_ref'=>$creditNote->id,'trans_src_ref'=>'','type'=>'voucher_credit','amount'=>$amount,'valid_till'=>$valid_till);
                    $vcher  = $this->createVoucher($request,$paramsVc);
                    $paramsLg    = array('trans_src_ref_id' => $creditNote->id,'trans_src' => $settelmentLogTransSrc ,'applied_amount'=>$amount,'voucher_id'=>$vcher->id,'status'=>'PROCESS');
                    $crDrLog     = $this->createVocherSettLog($request,$paramsLg);
                }
            }
            $params = array('order_id'=>$creditNote->doc_no,'v_id'=>$v_id,'store_id'=>$store_id,'vu_id'=>$creditNote->vu_id);
            if(!empty($debitTranData) && count($debitTranData) > 0){
                $creditOrderData = DepRfdTrans::where('dep_rfd_trans.id',$creditNote->id)->get();
            }else{
                $creditOrderData = DepRfdTrans::join('cr_dr_voucher','cr_dr_voucher.dep_ref_trans_ref','dep_rfd_trans.id')->select('dep_rfd_trans.*','cr_dr_voucher.expired_at as valid_till')->where('dep_rfd_trans.id',$creditNote->id)->get();
            }
            $orderCredit =  $creditOrderData->each(function($item){
                                $item->order_id      = $item->doc_no;
                                $item->store_id      = $item->src_store_id;
                                $item->payment_type  =  'full';
                                $item->discount      =  "0.00";
                                $item->subtotal      = abs($item->amount);
                                $item->total         =  $item->amount;
                                $item->date          =  date('Y-m-d',strtotime($item->created_at));
                                $item->total         =  abs($item->amount);
                                $item->amount         =  abs($item->amount);
                                $item->remark        = '';
                                $item->is_invoice    = "0";
                            });
            $order_arr = $this->getOrderResponse($params);
        }



        //get user profile
        $profileRequest = $request;
        $profileRequest['c_id'] = $request->user_id;
        $userInfo = User::select('api_token')->where(['v_id' => $request->v_id, 'c_id' => $request->user_id])->first();
        if(empty($userInfo->api_token)){
           $userInfo->api_token = str_random(50);
           $userInfo->save(); 
        }

        $profileRequest['api_token'] = $userInfo;

        $getProfile = new \App\Http\Controllers\CustomerController;
        $customerProfile = json_decode($getProfile->profile($profileRequest)->getContent());
        $customerProfile->data->api_token = @$profileRequest['api_token']->api_token;

        unset($customerProfile->status);
        unset($customerProfile->message);
        unset($customerProfile->summary);
        unset($customerProfile->address); 

         
        //&& count($debitTranData) > 0
        if(!empty($creditNote)   ){
             //return response()->json(['status' => 'success' , 'message' => 'Amount credited succcessfully'],200);
            $res =  ['status' => 'proceed_to_payment', 'message' => 'Proceed to Payment', 'data' => @$orderCredit[0], 'order_summary' => $order_arr,'account_sale'=>'0', 'transaction_type' => 'on_account_deposit', 'customer' => $customerProfile];
            return $res;


        }else{
         return response()->json(['status' => 'fail' , 'message' => 'No debit transaction found.'],200);
        }
    }//End of payAccountBalanceRequest



    


    public function payAccountBalanceApprove(Request $request)
    {
        //return response()->json(['status' => 'wait' , 'message' => 'No debit transaction found.'],200);
        $v_id       = $request->v_id;
        $store_id   = $request->store_id;
        $trans_from = $request->trans_from;
        $c_id       = $request->c_id;
        $vu_id      = $request->vu_id;
        $udidtoken  = $request->udidtoken;
        //$trans_type = $request->trans_type;
         $trans_type = $request->type;
        $amount      = $request->amount;
        //$debitTransArr   = $request->debit_trans;
        $payment_method   = $request->method;
        $cash_collected   = $request->cash_collected;
        $cash_return      = $request->cash_return;
        $bank             = $request->bank;
        $wallet           = $request->wallet;
        $vpa              = $request->vpa;
        $error_description= $request->error_description;
        $order_id         = $request->order_id;

        $payment_gateway_type = ''; 
        $payment_gateway_device_type = '';
        $trans_src       = '';
        $trans_src_ref   = '';
        $session_id      = 0;
        $terminal_id     = get_terminal_id($store_id,$v_id,$udidtoken);
        $status          = 'Process';
        $payment_type    = 'full';
        $gateway_response= '';
        $current_date = date('Y-m-d'); 
        //$debitTranData = json_decode($debitTransArr);  //all transaction array
        if($trans_type == 'account_deposite'){
            $tran_sub_type = 'Deposit-DN';
            $trans_src     = 'self';
        }
        if($request->has('payment_gateway_device_type')){
            $payment_gateway_device_type = $request->payment_gateway_device_type;
        } 
        if($request->has('payment_gateway_type')){
            $payment_gateway_type = $request->payment_gateway_type;
        }
        if($payment_method == 'voucher'){
         $payment_method  = 'voucher_credit';
        }

        //$debitTransColl  = collect($debitTranData);
        //$TotalAmt        = $debitTransColl->sum('amount');

        // if(abs($amount) != abs($TotalAmt)){
        //     return response()->json(['status' => 'fail' , 'message' => 'Pay amount is not equal to sum of debit amount'],200);
        // }


        
        
        if($amount>0 && !empty($order_id)) {
         $params = array('v_id'=>$v_id,'store_id'=>$store_id,'c_id'=>$c_id);
         $creditNote  = DepRfdTrans::where('doc_no',$order_id)->where(['v_id'=>$v_id,'src_store_id'=>$store_id,'user_id'=>$c_id])->first();
         if(!$creditNote){
          return response()->json(['status' => 'fail' , 'message' => 'Debit note not found.'],200);
         }
         $debitTranData  = CrDrSettlementLog::join('cr_dr_voucher as cdv','cdv.id','cr_dr_settlement_log.voucher_id')
                            ->join('dep_rfd_trans as drt','drt.id','cdv.dep_ref_trans_ref')
                            ->where('cr_dr_settlement_log.v_id',$v_id)
                            ->where('cr_dr_settlement_log.store_id',$store_id)
                            ->where('cr_dr_settlement_log.trans_src_ref_id',$creditNote->id)
                            ->select('drt.doc_no','cr_dr_settlement_log.applied_amount')
                            ->get();

        //dd($debitTranData); die;
        $debitTransList  = [];

            if(empty($creditNote)){
                return response()->json(['status' => 'fail' , 'message' => 'Order id not found.'],200);
            }
            
            $settlementSession = SettlementSession::select('id')->where(['v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id ,'trans_from' => $trans_from, 'settlement_date' => $current_date ])->latest()->first();
            if($settlementSession){
                $session_id = $settlementSession->id;
            }
            if(!empty($creditNote)){
               
            $payment = new Payment;
            $payment->store_id = $store_id;
            $payment->v_id = $v_id;
            $payment->order_id = $creditNote->doc_no;
            $payment->user_id = $c_id;
            $payment->session_id =$session_id;
            $payment->terminal_id =$terminal_id;
            $payment->pay_id = 'DEP_'.$creditNote->id;
            $payment->amount = $amount;
            $payment->method = $payment_method;
            $payment->cash_collected = $cash_collected;
            $payment->cash_return = $cash_return;
            $payment->payment_invoice_id = '';
            $payment->bank = $bank;
            $payment->wallet = $wallet;
            $payment->vpa = $vpa;
            $payment->error_description = $error_description;
            $payment->status = $status;
            $payment->payment_type = $payment_type;
            $payment->payment_gateway_type = $payment_gateway_type;
            $payment->payment_gateway_device_type = $payment_gateway_device_type;
            $payment->gateway_response = json_encode($gateway_response);
            $payment->trans_type = 'Deposite';
            $payment->date = date('Y-m-d');
            $payment->time = date('H:i:s');
            $payment->month= date('m');
            $payment->year = date('Y');
            $payment->save();
            }
        }
        if(!empty($creditNote) && !empty($payment) ){
           

            $where = array('v_id'=>$v_id,'user_id'=>$c_id);
            foreach($debitTranData as $dbt_data){

              $transaction = DepRfdTrans::join('cr_dr_voucher','cr_dr_voucher.dep_ref_trans_ref','dep_rfd_trans.id')->select('dep_rfd_trans.*','cr_dr_voucher.expired_at as valid_till')->where('doc_no',$dbt_data->doc_no)
                    //->where('src_store_id',$store_id)
                    //->where($where)
                    ->where('dep_rfd_trans.v_id',$v_id)
                    ->where('dep_rfd_trans.user_id',$c_id)
                    ->first();

                if(empty($transaction)){
                    $transaction = DepRfdTrans::where('doc_no',$dbt_data->doc_no)
                                    ->where('src_store_id',$store_id)
                                    ->where('dep_rfd_trans.v_id',$v_id)
                                    ->where('dep_rfd_trans.user_id',$c_id)
                                    ->first();
                }
 
              $getVoucher  = Voucher::where('dep_ref_trans_ref',$transaction->id)
                                //->where('store_id',$store_id)
                                ->where($where)->first();

              $creditAmt     =  $dbt_data->applied_amount;
              $depositeAmt   = abs($dbt_data->applied_amount);
              if($getVoucher->status == 'Partial settled'){
                $debitValue   = $getVoucher->settelment->where('status','APPLIED')->sum('applied_amount');
                $debitValue   = $getVoucher->amount - $debitValue;
                //print_r($debitValue);
               // $debitValue    = abs($transaction->amount);  
              }else{
                $debitValue    = abs($transaction->amount);  
              } 
                // echo $depositeAmt;
                // echo '<br>';
                //echo $debitValue;
                //die;

            if($depositeAmt==$debitValue){
                 $voucherStatus    =  'used';
                 
                 //$voucherSettData = ['v_id'=>$v_id,'store_id'=>$store_id,'user_id'=>$c_id,'trans_src'=>'Deposite','trans_src_ref_id'=>$creditNote->id,'order_id'=>$transaction->trans_src_ref,'applied_amount'=>$creditAmt,'voucher_id'=>$getVoucher->id,'status'=>'APPLIED']; 
                 CrDrSettlementLog::where('voucher_id',$getVoucher->id)->where('trans_src_ref_id',$creditNote->id)->update(['status'=>'APPLIED']);
                }else{
                  $voucherStatus =  'Partial settled';
                  //$voucherSettData = ['v_id'=>$v_id,'store_id'=>$store_id,'user_id'=>$c_id,'trans_src'=>'Deposite','trans_src_ref_id'=>$creditNote->id,'order_id'=>$transaction->trans_src_ref,'applied_amount'=>$creditAmt,'voucher_id'=>$getVoucher->id,'status'=>'PROCESS'];
                  //CrDrSettlementLog::where('voucher_id',$getVoucher->id)->update(['status'=>'APPLIED']);

                  CrDrSettlementLog::where('voucher_id',$getVoucher->id)->where('trans_src_ref_id',$creditNote->id)->update(['status'=>'APPLIED']);
                }

                if($creditNote->trans_type == 'Credit' && $creditNote->trans_sub_type == 'Credit-Note'){
                     $voucherStatus    =  'unused';

                }

                $getVoucher->status = $voucherStatus;
                $getVoucher->save();
                //$voucherSett = CrDrSettlementLog::create($voucherSettData);

                $checkValue = CrDrSettlementLog::where('voucher_id',$getVoucher->id)->where('status','APPLIED')->get()->sum('applied_amount');
                $prev_paid   = 0 ;
                $balance   = abs($transaction->amount);
                $total     = abs($transaction->amount);
                        //echo $checkValue;
                if(!empty($checkValue) && $checkValue >0){
                    $prev_paid = $checkValue;
                    $balance   = abs($transaction->amount)-$checkValue;
                    $total     = abs($transaction->amount)-$checkValue;
                }

                $debitDate = date('d-m-Y',strtotime($transaction->created_at));
                $debitTransList[] = array('doc_no'=>$transaction->doc_no,'value'=>abs($transaction->amount),'trans_src_ref'=>$transaction->trans_src_ref,'voucher_id'=>$getVoucher->id,'date_of_issue'=>$debitDate,'prev_paid'=>abs($prev_paid),'balance'=>abs($balance),'total'=>abs($total),'valid_till'=>$transaction->valid_till,'payable_amount'=>$transaction->amount,'remark'=>$transaction->remark,'refund_amount'=>abs($transaction->amount),'redeemed'=>0);
            }            
            $payment->status = 'success';
            $payment->save();

            $payments = Payment::where('v_id', $creditNote->v_id)->where('store_id', $creditNote->src_store_id)->where('order_id', $creditNote->doc_no)->where('status','success')->get();


 
            $amount_paid = $payments->sum('amount');

            if(abs($amount_paid) == abs($creditNote->amount)){
                $creditNote->status = 'Success';
                $creditNote->save();
                //Deposite and refund amount push to client
                $db_structure = DB::table('vendor')->select('db_structure')->where('id', $v_id)->first()->db_structure;
                $clientIntregated=getIsIntegartionAttribute($v_id);
                if(isset($payment) && isset($creditNote->id) && $clientIntregated){
                        $zwingTagVId = '<ZWINGV>'.$v_id.'<EZWINGV>';
                        $zwingTagStoreId = '<ZWINGSO>'.$store_id.'<EZWINGSO>';
                        $zwingTagTranId = '<ZWINGTRAN>'.$payment->payment_id.'<EZWINGTRAN>';
                        event(new DepositeRefund([
                            'payment_id' => $payment->payment_id,
                            'v_id' => $v_id,
                            'store_id' => $store_id,
                            'db_structure' => $db_structure,
                            'type'=>'SALES',
                            'zv_id' => $zwingTagVId,
                            'zs_id' => $zwingTagStoreId,
                            'zt_id' => $zwingTagTranId
                            ] 
                            )
                        );
                    }

               

                // Cash Transactions Posting
                $vendorUser = Vendor::where([ 'v_id' => $v_id, 'store_id' => $creditNote->src_store_id, 'id' => $vu_id ])->first();
                if($vendorUser->cash_management['status']) {
                    $this->cashTransactionLogs($creditNote, $session_id);
                }

                 /*sms */
                if($getVoucher->type == 'voucher_credit' && $getVoucher->status != 'Settled' ){
                $cust = User::select('mobile')->where('c_id', $c_id)->first();
                $smsC = new SmsController;
                $expired_at = explode(' ', $getVoucher->expired_at);
                $smsParams = ['mobile' => $cust->mobile, 'voucher_amount' => ($getVoucher->amount), 'voucher_no' => $getVoucher->voucher_no, 'expiry_date' => $expired_at[0], 'v_id' => $v_id, 'store_id' => $store_id];
                 $smsResponse = $smsC->send_voucher($smsParams);
                }
                /*sms end*/

            } 
           
          //return response()->json(['status' => 'success' , 'message' => 'Amount credited succcessfully'],200);
        $params = array('order_id'=>$creditNote->doc_no,'v_id'=>$v_id,'store_id'=>$store_id,'vu_id'=>$creditNote->vu_id);
        $order_arr = $this->getOrderResponse($params);
        $creditOrderData = DepRfdTrans::where('id',$creditNote->id)->get();

        $request->merge([
                        'v_id' => $v_id,
                        'c_id' => $c_id,
                        'store_id' => $store_id,
                        'order_id' => $creditNote->doc_no
                    ]);
        $htmlData = $this->get_deposite_recipt($request);
        $html = $htmlData->getContent();
        $html_obj_data = json_decode($html);
        $cartC  = new CartController;
        $htmlPrint  = $cartC->get_html_structure($html_obj_data->print_data);
        if ($html_obj_data->status == 'success') {
        $print_url = $html_obj_data->print_data;
        }

        $order  = $creditOrderData->each(function($item) use ($payment, $session_id, $terminal_id,$htmlPrint){
                        $item->payment_id       = $payment->id;
                        $item->order_id         = $item->doc_no;
                        $item->invoice_id       = $item->doc_no;
                        $item->session_id       = $session_id;
                        $item->terminal_id      = $terminal_id;
                        $item->store_id         = $item->src_store_id;
                        $item->payment_type     =  'full';
                        $item->payment_gateway_type        = $payment->payment_gateway_type;
                        $item->payment_gateway_device_type = $payment->payment_gateway_device_type;
                        $item->gateway_response = $terminal_id;
                        $item->discount         =  "0.00";
                        $item->subtotal         = abs($item->amount);
                        $item->total            =  abs($item->amount);
                        $item->amount            =  abs($item->amount);
                        $item->date             =  date('Y-m-d',strtotime($item->created_at));
                        $item->time            =  date('H:i:s',strtotime($item->created_at));
                        $item->html_data       = $htmlPrint;
                    });   
        
        $cParams       = ['user_id'=>$c_id,'v_id'=>$v_id,'store_id'=>$store_id];
        $customerInfo  = $this->customerInfo($cParams);
        $trasactionDate= date('d M Y H:i:s');
        $prevActBalance  = 0;
        $updateActBalance =0; 
        $cashier          = @$creditNote->vuser->first_name.' '.@$creditNote->vuser->last_name;
        $transaction_summary = array('data'=>$debitTransList,'transaction_id'=> $creditNote->doc_no,'cashier'=>$cashier,'date'=>$trasactionDate,'previous_act_balance'=>$prevActBalance,'update_act_balance'=>$updateActBalance);
        
        
                        
        return response()->json(['status' => 'payment_save', 'redirect_to_qr' => true, 'message' => 'Save Payment', 'data' => $order[0],'transaction_summary'=>$transaction_summary ,'customer_info'=>$customerInfo,'order_summary' => $order_arr, 'transaction_type' => 'on_account_deposit','print_url' => $print_url], 200); 

        }else{
         return response()->json(['status' => 'fail' , 'message' => 'No debit transaction found.'],200);
        }
    }//End of payAccountBalanceApprove 

    public function getOrderResponse($params){
        $doc_no  = $params['order_id'];
        $v_id    = $params['v_id'];
        $store_id= $params['store_id'];
        $vu_id   = $params['vu_id'];
        $summary = [];
        $subtotal= 0;
        $total   = 0;
        $amount_paid   = 0;
        $amount_due    = 0;
        $total_payable = 0; 

        $order   =  DepRfdTrans::where('v_id',$v_id)->where('dep_rfd_trans.doc_no',$doc_no)->first();
        $total_payable = abs($order->amount);
        $orderDetail  = DepRfdTrans::join('cr_dr_settlement_log as cdsl','cdsl.trans_src_ref_id','dep_rfd_trans.id')
                    ->where('dep_rfd_trans.doc_no',$doc_no)
                    ->where('dep_rfd_trans.v_id',$v_id)
                    ->where('dep_rfd_trans.src_store_id',$store_id)
                    ->get();
        $items_qty = 0;
        foreach ($orderDetail as $order_detail) {
            $vocherDetails  = Voucher::join('dep_rfd_trans','dep_rfd_trans.id','cr_dr_voucher.dep_ref_trans_ref')->where('cr_dr_voucher.id',$order_detail->voucher_id)->select('dep_rfd_trans.doc_no')->first();
            $items[]    = array('p_name'=>$vocherDetails->doc_no,'qty'=>'1','total'=>abs($order_detail->applied_amount));
            $items_qty   = $items_qty+1;
            $subtotal   += abs($order_detail->applied_amount);
            $total      += abs($order_detail->applied_amount);
        }
        $summary[] = [ 'name' => 'sub_total' , 'display_text' => 'Sub Total' , 'display_name' => 'Sub Total' , 'value' => format_number($subtotal),'sign'=>'' ];
        $payments = Payment::where('v_id', $order->v_id)->where('store_id', $order->src_store_id)->where('order_id', $order->doc_no)->where('status','success')->get();
            foreach ($payments as $key => $payment) {
                if($payment->payment_gateway_type == 'CASH'){
                    $summary[] = [ 'name' => 'cash' , 'display_text' => 'Cash' ,'display_name' => 'Cash' , 'value' =>  format_number($payment->amount) , 'color_flag' => '1' , 'mop_flag' => '1'];

                }else if($payment->payment_gateway_type == 'VOUCHER' || $payment->payment_gateway_type == ''){
                    $summary[] = [ 'name' => 'voucher_credit' , 'display_text' => 'Voucher ' ,'display_name' => 'Voucher ' , 'value' =>  format_number(abs($payment->amount)) , 'color_flag' => '1' , 'mop_flag' => '1' ];

                }else if($payment->payment_gateway_type == 'RAZOR_PAY'){
                    
                    if($payment->method == 'wallet'){

                        $summary[] = [ 'name' => 'online' , 'display_text' => 'Wallet' ,'display_name' => 'Wallet' , 'value' =>  format_number($payment->amount) , 'color_flag' => '1' , 'mop_flag' => '1'];
                    }else if($payment->method == 'netbanking'){

                        $summary[] = [ 'name' => 'netbanking' , 'display_text' => 'Net Banking' ,'display_name' => 'Net Banking' , 'value' => format_number(abs($payment->amount)) , 'color_flag' => '1' , 'mop_flag' => '1'];

                    }else if($payment->method == 'card'){

                        $summary[] = [ 'name' => 'card' , 'display_text' => 'Card' , 'display_name' => 'Card' , 'value' =>  format_number(abs($payment->amount)) , 'color_flag' => '1' , 'mop_flag' => '1'];
                    }

                }else if($payment->payment_gateway_type == 'EZETAP' || $payment->payment_gateway_type == 'EZSWYPE'){

                    $summary[] = [ 'name' => 'card' , 'display_text' => 'Card' , 'display_name' => 'Card' , 'value' =>  format_number(abs($payment->amount)) , 'color_flag' => '1' , 'mop_flag' => '1' ];
                }else{

                    $paymentName  = str_replace('_', ' ', $payment->method);

                    $summary[] = [ 'name' => $payment->method , 'display_text' => ucwords($paymentName) ,'display_name' => ucwords($paymentName) , 'value' =>  format_number(abs($payment->amount)) , 'color_flag' => '1' , 'mop_flag' => '1' ];

                }
                
            }

            $amount_paid    = (float)$payments->sum('amount');
            $total_payable -= abs($amount_paid);


        $summary[] = [ 'name' => 'total' , 'display_text' => 'Total' ,'display_name' => 'Total' , 'value' => format_number(abs($total)),'sign'=>'' ];
        $summary[] = [ 'name' => 'total_payable' , 'display_text' => 'Total Payable' ,'display_name' => 'Total Payable' , 'value' => format_number(abs($total_payable)), 'color_flag' => '1' ];

        $response['items'] = $items;
        $response['item_qty'] = (string)$items_qty;
        $response['summary'] = $summary;
        $response['total_payable'] = (float)format_number(abs($total_payable));
        $response['amount_due'] = (string)abs($amount_due);
        $response['amount_paid'] = (string)abs($amount_paid);
        $response['order_total'] = (string)abs($order->amount);
        $response['remark '] = (string)$order->remark;
        $response['date '] = (string)$order->created_at;


        return $response;

    }//End of getOrderResponse

    public function getDebitPurchasedList(Request $request){
        $v_id          = $request->v_id;
        $store_id      = $request->store_id;
        $user_id       = $request->user_id;
        $trans_from    = $request->trans_from;

      /*  $params = ['v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $user_id, 'role_id' => '', 'trans_from' => $trans_from,'udidtoken'=>''];
        $vendorSetting  =  new VendorSettingController;
        $allowAccountSale  = $vendorSetting->getAccountSaleSetting($params);
        dd($allowAccountSale);*/
        $cParams       = ['user_id'=>$user_id,'v_id'=>$v_id,'store_id'=>$store_id];
        $customerInfo  = $this->customerInfo($cParams);

        //print_r($customerInfo);die;

        $debitAll   = DepRfdTrans::join('cr_dr_voucher as cdv','cdv.dep_ref_trans_ref','dep_rfd_trans.id')
                        ->where('dep_rfd_trans.v_id',$v_id)
                        //->where('dep_rfd_trans.src_store_id',$store_id)
                        ->where('dep_rfd_trans.user_id',$user_id)
                        ->whereIN('cdv.status',['Completed','Partial settled'])
                        ->select('dep_rfd_trans.doc_no','cdv.amount as value','dep_rfd_trans.trans_src_ref','cdv.id as voucher_id','cdv.effective_at as date_of_issue')
                        ->get();
        $debitData  =  $debitAll->each(function($item){
                        $checkValue = CrDrSettlementLog::where('voucher_id',$item->voucher_id)->where('status','APPLIED')->get()->sum('applied_amount');

                        $prevPaid = CrDrSettlementLog::where('voucher_id',$item->voucher_id)->where('status','APPLIED')->where('trans_src', 'Deposite')->get()->sum('applied_amount');
                        //echo $checkValue;
                        $item->date_of_issue = date('Y-m-d',strtotime($item->date_of_issue));
                        if(!empty($checkValue) && $checkValue >0){
                            $item->prev_paid = $prevPaid;
                            $item->balance   = $checkValue;
                            $item->total     =  $checkValue ;
                            $item->payable     =  -1 *$checkValue ;
                        }else{
                            $item->prev_paid = $prevPaid;
                            $item->balance   = $checkValue;
                            $item->total     =  $checkValue ;
                            $item->paybale     = -1 * $checkValue ;
                        }
                       });

            #'dep_rfd_trans.doc_no,dep_rfd_trans.amount,dep_rfd_trans.order_id'

        return response()->json(["status" => 'success' ,'message' => 'Debit account transaction list', 'data' => $debitData]);
    }//End of getDebitPurchasedList


    public function getCreditNoteList(Request $request){

        $v_id          = $request->v_id;
        $store_id      = $request->store_id;
        $user_id       = $request->user_id;
        $trans_from    = $request->trans_from;

      /*  $params = ['v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $user_id, 'role_id' => '', 'trans_from' => $trans_from,'udidtoken'=>''];
        $vendorSetting  =  new VendorSettingController;
        $allowAccountSale  = $vendorSetting->getAccountSaleSetting($params);
        dd($allowAccountSale);*/
        $cParams       = ['user_id'=>$user_id,'v_id'=>$v_id,'store_id'=>$store_id];
        $customerInfo  = $this->customerInfo($cParams);

        //print_r($customerInfo);die;

        $debitAll   = DepRfdTrans::join('cr_dr_voucher as cdv','cdv.dep_ref_trans_ref','dep_rfd_trans.id')
                        ->where('dep_rfd_trans.v_id',$v_id)
                        ->where('dep_rfd_trans.src_store_id',$store_id)
                        ->where('dep_rfd_trans.user_id',$user_id)
                        ->where('dep_rfd_trans.status','success')
                        ->whereIn('cdv.status',['Pending','unused','partial'])
                        ->whereIn('dep_rfd_trans.trans_sub_type',['Credit-Note'])
                        ->where('dep_rfd_trans.trans_type','Credit')
                        ->select('dep_rfd_trans.doc_no','dep_rfd_trans.trans_sub_type','dep_rfd_trans.trans_src','dep_rfd_trans.trans_type','cdv.amount as value','dep_rfd_trans.trans_src_ref','cdv.id as voucher_id','cdv.effective_at as date_of_issue','cdv.expired_at as valid_till','dep_rfd_trans.remark')
                         ->orderBy('dep_rfd_trans.id','desc')
                        ->get()
                        ;

                       // dd($debitAll->toSql(), $debitAll->getBindings());

        $debitData  =  $debitAll->each(function($item){
                        $checkValue = CrDrSettlementLog::where('voucher_id',$item->voucher_id)->where('status','APPLIED')->get()->sum('applied_amount');
                        //echo $checkValue;
                        $item->date_of_issue = date('Y-m-d',strtotime($item->date_of_issue));
                        if(!empty($checkValue) && $checkValue >0){
                            $item->redeemed =  $item->value-$checkValue;
                            $item->balance   = $checkValue;
                            $item->total     = $item->value;
                        }else{
                            $item->redeemed =  0;
                            $item->balance   = $item->value;
                            $item->total     = $item->value;
                        }
                         
                       // $item->remark  = '';

                        if($item->trans_type == 'Credit' && $item->trans_src == 'self'){
                            $item->type = 'Adhoc';
                        }
                        if($item->trans_type == 'Credit' && $item->trans_src == 'Return-invoice'){
                            $item->type = 'Return';
                        }

                        unset($item->trans_type);
                        unset($item->trans_src);



                       });

            #'dep_rfd_trans.doc_no,dep_rfd_trans.amount,dep_rfd_trans.order_id'

        return response()->json(["status" => 'success' ,'message' => 'Credit transaction list', 'data' => $debitData]);
    
    }//End of getCreditNoteList

    private function generateDocNo($params) 
    {
        $v_id     = $params['v_id'];
        $store_id = $params['store_id'];
        $c_id     = $params['c_id'];

        $store = Store::select('short_code')      
                    ->where('store_id',$store_id)
                    ->where('v_id',$v_id)
                    ->first();
        $c_date =date('dmy');
        $number =  'DEP'.$store->short_code.$c_date.$this->docIncrementNo($params);
        return $number;
    }

    private function docIncrementNo($params){

        $v_id     = $params['v_id'];
        $store_id = $params['store_id'];
        $c_id     = $params['c_id'];
        $inc_no           = '0001';
        $currentdate      = date('Y-m-d');
        $lastTranscation  = DepRfdTrans::where('src_store_id',$store_id)->where('v_id',$v_id)
                                             //->where('user_id',$c_id)
                                             ->orderBy('id','DESC')
                                             ->first();    

        if(!empty($lastTranscation) && $lastTranscation->created_at->format('Y-m-d')==$currentdate)
        {
              $n  = strlen($inc_no);
              $current_id = substr($lastTranscation->doc_no,-$n);
              $inc=++$current_id;
              $inc_no =str_pad($inc,$n,"0",STR_PAD_LEFT);
        }else{
         $inc_no = '0001';
        }
         return $inc_no;
    }

    public function depositCreation(Request $request)
    {
        $isKeyExists = true;
        $requestKey = collect($request->all())->toArray();
        $depRfdTransModel = new DepRfdTrans;
        $depositColumns = collect($depRfdTransModel->getTableColumns());
        $depositColumns = $depositColumns->filter(function($item) {
            return !in_array($item, ['id','created_at','doc_no','updated_at','sync_status']) ? $item : '';
        })->map(function($val) use(&$request, &$isKeyExists) {
            if(!$request->has($val)){
                $isKeyExists = false;
            }
            return $val;
        })
        ->values();

        if(!$isKeyExists) {
            return response()->json(["status" => 'fail' ,'message' => 'Requrired column not found on request (Deposit).']);
        }

        $docNo = $this->generateDocNo([ 'v_id' => $request->v_id, 'store_id' => $request->src_store_id, 'c_id' => $request->c_id ]);
        $createDeposit = DepRfdTrans::create([ 'doc_no' => $docNo, 'v_id' => $request->v_id, 'src_store_id' => $request->src_store_id, 'vu_id' => $request->vu_id, 'user_id' => $request->user_id, 'terminal_id' => $request->terminal_id, 'trans_type' => $request->trans_type, 'trans_sub_type' => $request->trans_sub_type, 'trans_src_ref' => $request->trans_src_ref, 'trans_src' => $request->trans_src, 'amount' => format_number($request->amount), 'status' => ucfirst($request->status), 'trans_from' => $request->trans_from ]);

        return response()->json(["status" => 'success' ,'message' => 'Deposit succcessfully done.', 'data' => $createDeposit ]);
    }

    public function debitCreditNoteGeneration(Request $request)
    {
        $isKeyExists = true;
        $requestKey = collect($request->all())->toArray();
        $voucherModel = new Voucher;
        $depositColumns = collect($voucherModel->getTableColumns());
        $depositColumns = $depositColumns->filter(function($item) {
            return !in_array($item, ['id', 'voucher_no']) ? $item : '';
        })->map(function($val) use(&$request, &$isKeyExists) {
            if(!$request->has($val)){
                $isKeyExists = false;
            }
            return $val;
        })
        ->values();

        if(!$isKeyExists) {
            return response()->json(["status" => 'fail' ,'message' => 'Requrired column not found on request (Voucher).']);
        }

         $createVoucher = Voucher::create([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'user_id' => $request->user_id, 'dep_ref_trans_ref' => $request->dep_ref_trans_ref, 'ref_id' => $request->ref_id, 'voucher_no' => generateRandomString(6), 'type' => $request->type, 'amount' => format_number($request->amount), 'status' => $request->status, 'effective_at' => $request->effective_at, 'expired_at' => $request->expired_at ]);

        return response()->json(["status" => 'success' ,'message' => $request->type.' created successfully.', 'data' => $createVoucher ]);
    }

    public function debitCreditVoucherLog(Request $request)
    {
        $isKeyExists = true;
        $getColumns = new CrDrSettlementLog;
        $logColumns = collect($getColumns->getTableColumns());
        $logColumns = $logColumns->filter(function($item) {
            return !in_array($item, ['id']) ? $item : '';
        })->map(function($val) use(&$request, &$isKeyExists) {
            if(!$request->has($val)){
                $isKeyExists = false;
            }
            return $val;
        })
        ->values();
        
        if(!$isKeyExists) {
            return response()->json(["status" => 'fail' ,'message' => 'Requrired column not found on request (Credit Debit Log).']);
        }
        $createLog = CrDrSettlementLog::create([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'user_id' => $request->user_id, 'trans_src' => $request->trans_src, 'trans_src_ref_id' => $request->trans_src_ref_id, 'order_id' => $request->order_id, 'applied_amount' => format_number($request->applied_amount), 'voucher_id' => $request->voucher_id, 'status' => $request->status ]);

        return response()->json(["status" => 'success' ,'message' => 'Log created successfully.', 'data' => $createLog ]);
    }

    public function customerInfo($params)
    {
        $v_id = $params['v_id'];
        $c_id = $params['user_id'];
        $store_id = $params['store_id'];
        $allow_credit     = 0;
        $maxCreditLimit   = 0;
        $group_name = 'NA';
        $customerInfo  = null;
        $customer = User::where('c_id',$c_id)->where('v_id',$v_id)->first();
        $customer_bal = 0;
        if($customer){
            //Group
            foreach ($customer->groups as $custerGroup) {
                if($custerGroup->allow_credit == '1'){
                    $allow_credit   = $custerGroup->allow_credit;
                    $maxCreditLimit = $custerGroup->maximum_credit_limit;
                }
               $group_name =  $custerGroup->name;
            }
            if($allow_credit == '1'){
                /*'src_store_id'=>$store_id*/
            $previousDebitAmount = DepRfdTrans::where(['v_id'=>$v_id,'user_id'=>$c_id])->select(DB::raw("SUM(dep_rfd_trans.amount) as amount"))->where('status','Success')->first();
            $customer_bal    = $previousDebitAmount->amount;
            $maxCreditLimit  = $maxCreditLimit+$previousDebitAmount->amount;
            }
        $customerInfo  = array('mobile'=>$customer->mobile,'customer_name'=>$customer->first_name.' '.$customer->last_name,'group'=>$group_name,'allow_credit'=>$allow_credit,'customer_bal'=>$customer_bal,'max_limit'=>$maxCreditLimit);
        }
        return $customerInfo;
    }

    public function get_deposite_recipt(Request $request){

        $v_id       = $request->v_id;
        $store_id   = $request->store_id; 
        $c_id       = $request->c_id;
        $order_id   = $request->order_id;
        $vu_id      = $request->vu_id;
        $trans_from = $request->trans_from;
        $product_data= [];
        $gst_list    = [];
        $final_gst   = [];
        $detatch_gst = [];
        $rounded     = 0;
        $invoice_title = 'Credit Note';
        $cart_qty      = 0;
        $net_payable   = 0;
        $cash_collected = 0;
        $cash_return = 0;

        $bill_print_type = 0;
        $role        = VendorRoleUserMapping::select('role_id')->where('user_id', $vu_id)->first();
        $sParams     = ['v_id' => $v_id,'store_id'=>$store_id,'user_id'=>$c_id,'role_id'=>@$role->role_id,'trans_from' => $trans_from];
        $vendorS     = new VendorSettingController;
        $printSetting= $vendorS->getPrintSetting($sParams);
        if(count($printSetting) > 0){
            foreach($printSetting as $psetting){
                if($psetting->name == 'bill_print'){
                    $bill_print_type = $psetting->width;
                }
            }
        }

       /* if($bill_print_type == 'A4' && $trans_from == 'CLOUD_TAB_WEB' && $returnType != 'JSON'){
             return $this->get_deposite_a4_recipt($request);
        }  */ 

        $store         = Store::find($store_id);
        $order_details = DepRfdTrans::where('doc_no', $order_id)->where('v_id',$v_id)->first();
        $total_amount  = $order_details->amount;
        $payments     = $order_details->payvia;
        $cart_product  = CrDrSettlementLog::join('cr_dr_voucher as cdv','cdv.id','cr_dr_settlement_log.voucher_id')
                        ->join('dep_rfd_trans as drt','drt.id','cdv.dep_ref_trans_ref')
                        ->join('orders as od','od.order_id','drt.trans_src_ref')
                        ->select('drt.doc_no','od.qty as qty','cdv.amount as total_amt','cr_dr_settlement_log.applied_amount as paid_amt')
                        ->where('cr_dr_settlement_log.trans_src_ref_id', $order_details->id)
                        ->where('cr_dr_settlement_log.v_id', $order_details->v_id)
                        ->where('cr_dr_settlement_log.store_id', $order_details->src_store_id)
                        ->where('cr_dr_settlement_log.user_id', $order_details->user_id)
                        ->where('drt.status','Success')
                        ->get();
        $terms_conditions =  array('1.Goods once sold will not be taken back');  
        $count = 1;
        $gst_tax = 0;
        $gst_listing = [];
        foreach ($cart_product as $key => $value) {
            $product_data[]  = [
                'row'           => 1,
                'sr_no'         => $count++,
                'debit_no'      => $value->doc_no,
            ];
            $product_data[] = [
                'row'         => 2,
                'qty'         => $value->qty,
                'total'       => $value->total_amt, 
                'paid_amt'    => $value->paid_amt                        
            ];

            $cart_qty++;
        }

        foreach ($payments as $payment) {
            if ($payment->method == 'cash') {
                $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
                $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->cash_collected-$cashReturn ];
            } else {
                $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
            }
            $cash_collected += (float) $payment->cash_collected;
            $cash_return += (float) $payment->cash_return;

            /*Voucher Start*/
            if($payment->method == 'voucher_credit'){
                $voucher[] = $payment->amount;
                $net_payable = $net_payable-$payment->amount;
            }
        }

        $customer_paid = $cash_collected;
        $balance_refund= $cash_return;

        $manufacturer_name = 'basewin';
        if($request->has('manufacturer_name') ){
            $manufacturer_name= $request->manufacturer_name;
        }
        $manufacturer_name =  explode('|',$manufacturer_name);
        $printParams = [];
        if(isset($manufacturer_name[1])){
            $printParams['model_no'] = $manufacturer_name[1]  ;
        }

        $printInvioce = new PrintInvoice($manufacturer_name[0], $printParams);

        $printInvioce->addLineCenter($store->name, 24, true);
        $printInvioce->addLine($store->address1, 22);
        if($store->address2){
            $printInvioce->addLine($store->address2, 22);
        }
        $printInvioce->addLine($store->location.'-'.$store->pincode.', '.$store->state, 22);
        $printInvioce->addLine('Contact No: '.$store->contact_number, 22);
        $printInvioce->addLine('E-mail: '.$store->email, 22);
        $printInvioce->addLine('GSTIN: '.$store->gst, 22);
        if($store->cin){
            $printInvioce->addLine('CIN: '.$store->cin, 22);            
        }
        $printInvioce->addDivider('-', 20);
        $printInvioce->addLine($invoice_title  , 22,true);
        $printInvioce->addDivider('-', 20);
        $printInvioce->addLineLeft(' Invoice No : '.$order_details->doc_no , 22,true);
        $printInvioce->addLineLeft(' Date : '.date('d-M-Y', strtotime($order_details->created_at))." at ".date('h:i:s A', strtotime($order_details->created_at)), 22);
        $printInvioce->addLineLeft(' Cashier : '.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name , 22);
        $printInvioce->addLineLeft(' Customer Mobile : '.@$order_details->user->mobile , 22);
        $printInvioce->addDivider('-', 20);

        $printInvioce->tableStructure(['#', 'Debit No'], [3,31], 22);
        $printInvioce->tableStructure(['Item Qty', 'Total','Paid'], [10,12,12], 22);
        $printInvioce->addDivider('-', 20);

        for($i = 0; $i < count($product_data); $i++) {
            if($product_data[$i]['row'] == 1) {
                $printInvioce->tableStructure([
                                $product_data[$i]['sr_no'],
                                $product_data[$i]['debit_no'],
                            ],
                            [3,31], 22);
                        }
                if($product_data[$i]['row'] == 2)  {
                    $printInvioce->tableStructure([
                        $product_data[$i]['qty'],
                        $product_data[$i]['total'],
                        $product_data[$i]['paid_amt']
                    ],
                    [10,12,12], 22);
                }                
        }
        $printInvioce->addDivider('-', 20);
        $printInvioce->tableStructure(['Total', $cart_qty,$total_amount], [20, 4,14], 22,true);
        $printInvioce->addDivider('-', 20);
        $printInvioce->addLineLeft('Rupee: '.ucfirst(numberTowords(round($order_details->amount))).' Only' , 22);
        $printInvioce->addDivider('-', 20);
        $printInvioce->addLineLeft('  Customer Paid: '.format_number($customer_paid), 22, true);
        $printInvioce->addLineLeft('  Balance Refund: '.format_number($balance_refund), 22, true);
        $printInvioce->addDivider('-', 20);

        $printInvioce->leftRightStructure('Net Payable', format_number($net_payable), 22);
    
        $printInvioce->addDivider('-', 20);
        $printInvioce->addLineLeft(' Terms and Conditions', 22, true);
        $printInvioce->addDivider('-', 20);
        foreach ($terms_conditions as $term) {
            $printInvioce->addLineLeft($term, 20);
        }

        $response = ['status' => 'success', 'print_data' =>($printInvioce->getFinalResult())];
        if($request->has('response_format') && $request->response_format == 'ARRAY'){
         return $response;
        }
        return response()->json($response, 200);   

    }//End of printDepositeTransaction


    public function get_deposite_a4_recipt(Request $request){

            $v_id       = $request->v_id;
            $store_id   = $request->store_id; 
            $c_id       = $request->c_id;
            $order_id   = $request->order_id;
            $product_data= [];
            $gst_list    = [];
            $final_gst   = [];
            $detatch_gst = [];
            $rounded     = 0;
            $data    = '';
            $invoice_title = 'Credit Note';
            $style = "<style>*{padding:0;margin:0;box-sizing:border-box;-webkit-border-vertical-spacing:0;-webkit-border-horizontal-spacing:0;font-size:14px}.print_receipt_invoice thead tr th{border-right:1px #000 solid; color: #000; border-bottom:1px #000 solid;border-top:1px #000 solid;border-top:none; padding: 5px;}.print_receipt_invoice thead tr:last-child{border-right:none}.print_receipt_invoice tbody tr td{padding: 10px 5px; border-right:1px #000 solid}.print_receipt_invoice tbody tr td pre{min-height:29px;text-align:left;white-space:normal;word-wrap:break-word; font-size: 11px;max-height: 29px;overflow:hidden;line-height: 1.5;}.print_receipt_invoice tbody tr td:last-child{border-right:none}.print_receipt_top-head tr td{padding:2px}.print_invoice_terms td table{text-align: left;}.print_invoice_last td table td{text-align: left;}.print_store_sign td:nth-child(2){text-align: right;}.print_invoice_last td table:last-child{margin-top: 40px;}.print_invoice_table_start table tbody tr td{font-size:13px;}.print_invoice_terms td{ border-left: none;}</style>";

            $printArray    = array();
            $store         = Store::find($store_id);
            
            $order_details = DepRfdTrans::where('doc_no', $order_id)->where('v_id',$v_id)->first();
            $total_amount  = $order_details->amount;
            $payments     = $order_details->payvia;
            $cart  = CrDrSettlementLog::join('cr_dr_voucher as cdv','cdv.id','cr_dr_settlement_log.voucher_id')
            ->join('dep_rfd_trans as drt','drt.id','cdv.dep_ref_trans_ref')
            ->join('orders as od','od.order_id','drt.trans_src_ref')
            ->select('drt.doc_no','od.qty as qty','cdv.amount as total_amt','cr_dr_settlement_log.applied_amount as paid_amt')
            ->where('cr_dr_settlement_log.trans_src_ref_id', $order_details->id)
            ->where('cr_dr_settlement_log.v_id', $order_details->v_id)
            ->where('cr_dr_settlement_log.store_id', $order_details->src_store_id)
            ->where('cr_dr_settlement_log.user_id', $order_details->user_id)
            ->where('drt.status','Success');
           
            $count_cart_product = $cart->count();
           
           
            $startitem   = 0;
            $getItem     = 8;
            $countitem   = $count_cart_product;
            $totalpage   = ceil($count_cart_product/$getItem);
            $sr          = 1;

            for($i=0;$i < $totalpage ; $i++) {
               // $cart_product = $cart ->get();
             $cart_product = $cart->skip($startitem)->take(8)->get();

            $startitem  = $startitem+$getItem;
            $startitem  = $startitem;
             
            $customer_address = '';
            if(isset($order_details->user->address->address1)){
                $customer_address .= $order_details->user->address->address1;
            }
            if(isset($order_details->user->address->address2)){
                $customer_address .= $order_details->user->address->address2;
            }

            $count = 1;
            $gst_tax = 0;
             
            $roundoff = explode(".", $total_amount);
            $roundoffamt = 0;
            // dd($roundoff);
            if (!isset($roundoff[1])) {
                $roundoff[1] = 0;
            }
            if ($roundoff[1] >= 50) {
                $roundoffamt = $order_details->amount - $total_amount;
                $roundoffamt = -$roundoffamt;
            } else if ($roundoff[1] <= 49) {
                $roundoffamt = $total_amount - $order_details->amount;
                $roundoffamt = -$roundoffamt;
            }
            $bilLogo      = '';
            $bill_logo_id = 5;
            $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
            if($vendorImage)
            {
                $bilLogo = env('ADMIN_URL').$vendorImage->path;
            } 
            $payments  = $order_details->payvia;
            $cash_collected = 0;  
            $cash_return    = 0;
            $net_payable        = $total_amount;

            //dd($payments);
            $mop_list = [];
            foreach ($payments as $payment) {
            if ($payment->method == 'cash') {
            $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
            if($order_details->transaction_type == 'return'){
               $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
            }else{
               $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->cash_collected-$cashReturn ];
            }
            } else {
            $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
            }
            if ($payment->method == 'cash') {
                $cash_collected += (float) $payment->cash_collected;
                $cash_return += (float) $payment->cash_return;
            }
            }
            $customer_paid = $cash_collected;
            $balance_refund= $cash_return;
            $total_discount = (float)$order_details->discount+(float)$order_details->manual_discount+(float)$order_details->bill_buster_discount;

            

            $terms_conditions =  array('1.Goods once sold will not be taken back');  

            
            ########################
            ####### Print Start ####
            ########################
            
            
        $data  .= '<table class="print_invoice_table_start" width="98%" style="outline: 1px #000 solid; margin-top: 20px; margin-bottom: 0px; margin-left: auto; margin-right: auto;">';
            $data  .= '<tr><td>
                            <table width="100%" style="padding-left: 5px; padding-right: 5px;"><tr><td width="10%"><table ><tr><td><img src="'.$bilLogo.'" alt="" height="80px">
                            </td>
                            </tr>
                            </table></td>
                            <td width="90%" >
                            <table width="89%"   class="top-head" bgcolor="#fff" align="left" style=" text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;" >';
                            
            $data  .=  '<tr style="font-size: 16px; padding: 5px;"><td><b style="font-size: 18px;">'.$store->name.'</b></td></tr>';
            $data  .=  '<tr><td>'.$store->address1.'</td></tr>';
            if($store->address2){
             $data  .=  '<tr><td>'.$store->address2.'</td></tr>';
            }
            $data  .=  '<tr><td>'.$store->location.','.$store->pincode.','.$store->state.'</td></tr>';
            if($store->gst){
             $data  .=  '<tr><td>GSTIN: '.$store->gst.'</td></tr>';
            }
            $data  .=  '<tr><td>Tel: '.$store->contact_number.'</td></tr>';
            $data  .=  '<tr><td>Email: '.$store->email.'</td></tr>';
            $data  .=  '</table></td></tr></table></td></tr>';
             
            $data  .= '<tr><td><table style="width: 100%; color: #fff; padding: 5px; text-align: center; border: 1px #000 solid; border-left: none; border-right: none;"><tr><td valign="top" style="line-height: 1.5;  color: #000;"><b>'.$invoice_title.'</b></td></tr></table></td></tr>';
            $data  .=  '<tr>
            <td>
            <table style="width: 100%; color: #fff; padding: 5px;">';
            $data  .=  '<tr>
            <td valign="top" style="line-height: 1;  color: #000; font-size: 12px;text-align:left;">Customer 
            <br>
            <b>'.@$order_details->user->first_name.''.@$order_details->user->last_name.'</b>
            <br>'.@$order_details->user->mobile.'
            <br>'.@$order_details->user->gstin.'
            <br>'.$customer_address.'
            </td>';

            
        $printInvioce->tableStructure(['#', 'Debit No'], [3,31], 22);
        $printInvioce->tableStructure(['Item Qty', 'Total','Paid'], [10,12,12], 22);


            $data  .= '<td valign="top" style="line-height: 1.5; color: #000; font-size: 14px;" align="right">Date : '.date('d-M-Y', strtotime($order_details->created_at)).'
            <br>Invoice No: '.$order_details->invoice_id.'</td>
            </tr></table></td></tr>';
            $data  .= '<tr><td><div  style="height: 400px; overflow: hidden; border-top: 2px #000 solid; border-bottom: 2px #000 solid; "  width="100%" ><table height="100%" width="100%" class="print_receipt_invoice" bgcolor="#fff" style="width: 100%; color: #000;">';
            $data  .= '<thead ><tr align="left">
                        <th width="3%"  style=" font-size: 12px;" >Sr.</th>
                        <th width="10%" valign="center"  style="font-size: 12px; " >Debit No</th>
                        <th width="5%" valign="center"  style=" font-size: 12px;" >Item Qty</th>
                        <th width="5%" valign="center"  style=" font-size: 12px; " >Total</th>
                        <th width="10%" valign="center"  style=" font-size: 12px;" >Paid</th>
                         </tr></thead><tbody>';
           
            $srp= '';
            $barcode = '';
            $hsn ='';
            $item_name ='';
            $qty  = '';
            $unit = '';
            $mrp  = '';
            $disc = '';
            $taxp = '';
            $taxb = '';
            $taxable_amount = 0;
            $cart_qty       =0;
            $srp            = '';
            $barcode        = '';
            $hsn            = '';
            $item_name      = '';
            $qty            = '';
            $unit           = '';
            $total            = '';
            $paid           = '';
            $taxp           = '';
            $taxb           = ''; 
            
            foreach ($cart_product as $key => $value) {

                $remark  = isset($value->remark)?' -'.$value->remark:'';
                $tdata   = json_decode($value->tdata); 
                $srp     .= '<pre>'.$sr.'</pre>';
                $barcode .= '<pre>'.$value->doc_no.'</pre>';
                $qty     .= '<pre>'.$value->qty.'</pre>';
                $total   .= '<pre>'.$value->total_amt.'</pre>';
                $paid    .= '<pre>'.$value->paid_amt.'</pre>';
                $sr++;
                $cart_qty++;
            }

            $data   .= '<tr align="left">';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$srp.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$barcode.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$qty.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$total.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px; border-right: none;">'.$paid.'</td> </tr>';
             $data   .= '</tbody></table></td></tr></div>';
            $data   .= '<tr>
            <td>
            <table width="100%" style="color: #000;">
            <tr>
            <td>
            <table width="100%" style="padding: 5px;">
            <tr>
            ';
            if($totalpage-1 == $i){
            $data .= '<td width="61%" valign="top">';
            $data   .= '<table align="right" style="padding: 5px;"><tr><td>Total Qty.</td><td>' .$cart_qty.'</td></tr></table></td>';
            $data   .= '<td width="39%">';
            $data   .= '<table width="100%" style="padding: 5px;"><tr>
            <td width="70%" align="right">Amount Before tax</td>
            <td width="30%" align="right">&nbsp;'.$total_amount.'</td>
            </tr></table>';
             

            $data   .=  '</td></tr></table>';
            $data   .=  '<table width="100%"><tr><td width="40%"><table>';
           
                foreach($mop_list as $mop){
                  $data .=   '<tr><td bgcolor="#dcdcdc" align="left" style="padding: 5px;">
                                <b>Paid through '.$mop['mode'].':</b></td>
                                <td align="left" bgcolor="#dcdcdc" style="padding: 5px;"><b>'.$mop['amount'].'</b></td></tr>';
                }
    
            $data   .=  '</table></td></tr>';
            
            $data   .=  '<tr><td align="left">Amount: '.ucfirst(numberTowords(round($order_details->amount))).'</td></tr>';
            
            $AmountTitle = 'Total Amount';
            
            $data   .= '<table width="100%" style="padding: 5px;"><tr><td width="60%"></td><td width="40%"><table width="100%"><tr align="right"><td width="70%"><b>'.$AmountTitle.'</b></td><td width="30%"><b>'.$net_payable.'</b></td></tr></table></td></tr></table></td></tr></table></td></tr>';
            }else{
                    
                $data   .= '<td width="61%" height="205px" valign="top">';
                $data   .= '<table align="right" style="padding: 5px;"><tr><td></td><td></td></tr></table></td>';
                $data   .= '<td width="39%">';
                $data   .= '<table width="100%" style="padding: 5px;"><tr>
                <td width="70%" align="right"></td>
                <td width="30%" align="right"></td>
                </tr></table>';
                $data   .= '<table width="100%" style="padding: 5px;"><tr>
                             <td width="70%" align="right" ></td>
                             <td width="30%" align="right" ></td>
                             </tr></table>';
                $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                                <td width="70%" align="right" ></td>
                                <td width="30%" align="right" ></td></tr>
                             </table>';
                $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                                <td width="70%" align="right" ></td>
                                <td  width="30%" align="right" ></td></tr>
                              </table>';
                $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                              <td width="70%" align="right" ></td>
                              <td  width="30%" align="right" ></td></tr></table>';                              
                $data   .=  '</td></tr></table>';
                $data   .=  '<table width="100%"><tr><td width="40%"><table>';
               
                $data   .=  '</table></td></tr>';
                $data   .=  '<tr><td align="left"></td></tr>';
                if(isset($order_details->remark)){
                    $data   .=  '<tr><td align="left"></td></tr></table>';    
                }
                $data   .= '<table width="100%" style="padding: 5px;"><tr><td width="60%"></td><td width="40%"><table width="100%"><tr align="right"><td width="100%"><b>Continue..</b></td><td width="30%"></td></tr></table></td></tr></table></td></tr></table></td></tr>';
                
            }

            $data   .= '<tr class="print_invoice_terms"><td><table bgcolor="#fff" style="width: 100%; padding: 5px; color: #000; border: 1px #000 solid; border-left: none; border-right: none;">
                <tr width="100%">
                    <td style="padding-bottom: 10px;"><b>Terms and Conditions:</td >
                </tr>';
             foreach($terms_conditions as $term){
                $data .= '<tr width="100%"><td style="padding-bottom: 5px; text-decoration: dotted;">&bull;'.$term.'</td></tr>';
             }
            $data    .= '</table></td></tr>';
            $data    .= '<tr class="print_invoice_last"><td><table bgcolor="#fff" width="100%" style="color: #000000; padding: 5px;"><tr><td width="3%">For:</td><td colspan="1"><b>'.$store->name.'</b></td></tr></table><table width="100%" style="color: #000000; padding-top: 20px !important; padding: 5px;"><tr><td></td></tr><tr class="print_store_sign"><td width="50%">Authorised Signatory</td><td width="35%" align="right">Prepared by:</td><td align="right">&nbsp;'.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</td></tr></table></td></tr></table>';
             
            if($totalpage > 1){
                $data .= '<br><hr>';
            }
             
        }
        $return = array('status'=>'success','style'=>$style,'html'=>$data) ;
        return $return;
    
    }//End of function

    public function cashTransactionLogs($note, $session_id)
    {
        $amount = Payment::where([ 'v_id' => $note->v_id, 'store_id' => $note->src_store_id, 'order_id' => $note->doc_no, 'status' => 'success', 'method' => 'cash' ])->sum('amount');
        
        $transaction_type = '';

        if(isset($amount) && $amount > 0) {
            if($note->trans_type == 'Credit' && $note->trans_sub_type == 'Credit-Note' && $note->trans_src = 'self') {
                $transaction_behaviour = 'IN';
                $transaction_type = 'DEPOSIT';
            } elseif($note->trans_type == 'Debit' && $note->trans_sub_type == 'Refund-CN' && $note->trans_src = 'self') {
                $transaction_behaviour = 'OUT';
                $transaction_type = 'REFUND';
                $amount = -($amount);
            }else{
                $transaction_behaviour = '';
                $transaction_type = '';
                $amount = -($amount);
            }
            
            $currentTerminalCashPoint = CashPoint::where([ 'v_id' => $note->v_id, 'store_id' => $note->src_store_id, 'ref_id' => $note->terminal_id ])->first();

            $data = [ 'v_id' => $note->v_id, 'store_id' => $note->src_store_id, 'session_id' => $session_id, 'logged_session_user_id' => $note->vu_id, 'cash_point_id' => $currentTerminalCashPoint->id, 'cash_point_name' => $currentTerminalCashPoint->cash_point_name, 'transaction_type' => $transaction_type, 'transaction_behaviour' => $transaction_behaviour, 'amount' => $amount, 'transaction_ref_id' => $note->doc_no, 'cash_register_id' => $note->terminal_id, 'status' => 'APPROVED', 'approved_by' => $note->vu_id, 'remark' => $note->remark, 'date' => date('Y-m-d'), 'time' => date('h:i:s') ];

            $CashTransactionLogfrom = CashTransactionLog::create($data);
            $cartC  = new CartController;
            $cartC->cashPointSummaryUpdate($currentTerminalCashPoint->id,$currentTerminalCashPoint->cash_point_name,$note->src_store_id,$note->v_id, $session_id);
        }

    }
    
}

?>