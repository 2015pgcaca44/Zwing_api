<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Hash;
use Auth;
use App\Payment;
use App\Order;
use App\Vendor;
use App\SettlementSession;
use App\LoginSession;

class VendorSettlementController extends Controller
{

	public function cash_status(Request $request){
		date_default_timezone_set('Asia/Kolkata');
        $vu_id =  $request->vu_id;
        $trans_from = 'ANDROID_VENDOR';
        if($request->has('trans_from')){
            $trans_from = $request->trans_from;
        }

        $vendorUser = Vendor::select('vendor_id','store_id')->where('vu_id' , $vu_id)->first();
        $v_id = $vendorUser->vendor_id;
        $store_id = $vendorUser->store_id;
        
        $vendorS = new VendorSettingController;
        $paymentTypeSettings = $vendorS->getPaymentTypeSetting(['v_id' => $v_id, 'trans_from' => $trans_from]);
        $cash_status = 0;
        foreach($paymentTypeSettings as $type){
            if($type->name == 'cash' && $type->status == 1){
                $cash_status = 1;
            }
        }

        return [ 'v_id' => $v_id, 'store_id' => $store_id,'vu_id' => $vu_id ,'cash_status' => $cash_status ,'trans_from' => $trans_from ];

    }

    public function opening_balance_status(Request $request){
    	date_default_timezone_set('Asia/Kolkata');
        $opening_flag = $this->opening_balance_flag($request);
       
        if($opening_flag){
            
        }else{

        	//echo 'inside this';exit;
            return response()->json([ 'status' => 'add_opening_balance', 'message' => 'Opening Balance is not entered'],200);

        }

    }

    public function opening_balance_flag(Request $request){
    	date_default_timezone_set('Asia/Kolkata');
    	$cash_r = $this->cash_status($request);
        $cash_status =  $cash_r['cash_status'] ;
        if($cash_status){

            $current_date = date('Y-m-d');
            $settlementSession = SettlementSession::select('opening_balance','closing_balance')->where(['v_id' => $cash_r['v_id'] ,'store_id' => $cash_r['store_id'] , 'vu_id' => $cash_r['vu_id'] , 'type' => 'CASH' , 'trans_from' => $cash_r['trans_from'] , 'settlement_date' => $current_date ])->orderBy('opening_time','desc')->first();

            $opening_flag = 0;
            if($settlementSession){

            	if(empty($settlementSession->closing_balance) ||  $settlementSession->closing_balance = '' || $settlementSession->closing_balance == null){
            		$opening_flag = 1;
            	}else{
            		$opening_flag = 0;
            	}

                
            }else{
                $opening_flag = 0;
            }

            return $opening_flag;
        }

    }

    public function closing_balance_status(Request $request){
    	date_default_timezone_set('Asia/Kolkata');
        $cash_r = $this->cash_status($request);
        $cash_status =  $cash_r['cash_status'] ;
        if($cash_status){

            $current_date = date('Y-m-d' , strtotime('-1 days'));
            $settlementSession = SettlementSession::select('closing_balance')->where(['v_id' => $cash_r['v_id'] ,'store_id' => $cash_r['store_id'] , 'vu_id' => $cash_r['vu_id'] , 'type' => 'CASH' , 'trans_from' => $cash_r['trans_from'] , 'settlement_date' => $current_date ])->first();
            if($settlementSession){
                if(empty($settlementSession->closing_balance) ||  $settlementSession->closing_balance = '' || $settlementSession->closing_balance == null){

                    return response()->json([ 'status' => 'add_closing_balance', 'message' => 'Your Settlement is pending'], 200);
                }
            }else{

                return response()->json([ 'status' => 'add_opening_balance', 'message' => 'Opening Balance is not entered'], 200);
            }

            
        }
    }

	public function opening_balance(Request $request){
		date_default_timezone_set('Asia/Kolkata');
		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$vu_id = $request->vu_id;
		$type = $request->type;
		$trans_from = $request->trans_from;
		$opening_balance = $request->opening_balance;
		$settlement_date = $request->settlement_date;

		$settlementSession = SettlementSession::where(['v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id , 'type' => $type , 'trans_from' => $trans_from , 'settlement_date' => $settlement_date ])->
			orderBy('updated_at','desc')->first();

		if($settlementSession){

			if(empty($settlementSession->closing_balance) ||  $settlementSession->closing_balance = '' || $settlementSession->closing_balance == null){

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
				$settlementSession->save();

				return response()->json(['status' => 'success' , 'message' => 'Opening Balance added Succesfully' ]);

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
			$settlementSession->save();

			return response()->json(['status' => 'success' , 'message' => 'Opening Balance added Succesfully' ]);

		}
	}

	public function closing_balance(Request $request){
		date_default_timezone_set('Asia/Kolkata');
		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$vu_id = $request->vu_id;
		$type = $request->type;
		$trans_from = $request->trans_from;
		$closing_balance = $request->closing_balance;
		$settlement_date = $request->settlement_date;

		//Checking if Opening balance is done or not
		$this->opening_balance_status($request);

		$settlementSession = SettlementSession::where(['v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id , 'type' => $type , 'trans_from' => $trans_from , 'settlement_date' => $settlement_date ])->
			orderBy('updated_at','desc')->first();

		$settlementSession->closing_balance = $closing_balance;
		$settlementSession->closing_time = date('Y-m-d H:i:s');

		$settlementSession->save();

		return response()->json(['status' => 'success' , 'message' => 'Balance  added Succesfully' ]);

	}
	
	
	public function print_settlement(Request $request){
		date_default_timezone_set('Asia/Kolkata');
		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$vu_id = $request->vu_id;

		$trans_from = '';
        if($request->has('trans_from')){
           $trans_from = $request->trans_from; 
        }

        $current_date = date('Y-m-d');

        $settlementS = SettlementSession::select('id','opening_balance','closing_balance','opening_time','closing_time','created_at','updated_at')->where('vu_id',$vu_id)->where('v_id' , $v_id)->where('store_id' , $store_id)->where('settlement_date', $current_date)->orderBy('opening_time','desc')->first();

        $vendor = Vendor::select('first_name','last_name','mobile')->where('vu_id', $vu_id)->first();

        
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

		foreach($payments_by_method as $pay){
			$method = ucfirst(str_replace('_',' ', $pay->method));
			$mop_summary_count[] = ['name' => $method , 'value' => $pay->count ] ;
			$mop_summary_rs[] = ['name' => $method , 'value' => $pay->amount ] ;
		}

		//dd($payments_by_method);



		//$payments = Payment::select('amount','method','cash_collected','cash_return')->where('date',$current_date)->where('vu_id',$vu_id)->get();
		$tender = $payments->sum('cash_collected');
		$refund = $payments->sum('cash_return');

		$orders = Order::select('total')->where('date',$current_date)->where('vu_id',$vu_id)->get();
		if($orders->isEmpty()){
			return response()->json(["status" => 'fail' , 'message'=> 'No Order found']);
		}
		$order_count = $orders->count();
		$order_sum = $orders->sum('total');



		$terminal_summary = [];
		$cash_summary = [];

		$loginS = LoginSession::select('device_id')->where('vu_id',$vu_id)->where('v_id' , $v_id)->where('store_id' , $store_id)->orderBy('id','desc')->first();
		

		if(!$settlementS){
			return response()->json(["status" => 'fail' , 'message'=> 'No Session found']);
		}

		$terminal_summary[] = ['name' => 'Terminal Name' , 'value' => $loginS->device_id] ;
		$terminal_summary[] = ['name' => 'Report Time' , 'value' => 'Print Date '.date('d-M-Y') ]  ;
		$terminal_summary[] = ['name' => 'Session ID' , 'value' => (string)$settlementS->id] ;
		$terminal_summary[] = ['name' => 'Open On' , 'value' => (string)$settlementS->created_at]  ;
		$terminal_summary[] = ['name' => 'Close On' , 'value' => (string)$settlementS->updated_at ] ; 
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
		$cash_summary[] = ['name' => 'Over/(short)(E-D)' , 'value' => $overOrShortS ,'bold' => 1  ] ; 

		$data['print_header'] =  "Cash Summary Report";
		$data['cashier_name'] = "Cashier Name: ".$vendor->first_name.' '.$vendor->last_name.' \n'."Mobile: ".$vendor->mobile;
		
		$data['body'][] = [ 'header' => [ 'left_text' => 'Terminal Summary' , 'right_text' => '' ], 'body' => $terminal_summary ];
		$data['body'][] = [ 'header' => [ 'left_text' => 'Cash Summary' , 'right_text' => 'In Rs' ], 'body' => $cash_summary ];
		$data['body'][] = [ 'header' => [ 'left_text' => 'MOP Summary' , 'right_text' => 'Count' ], 'body' => $mop_summary_count ];
		$data['body'][] = [ 'header' => [ 'left_text' => 'Mop Summary' , 'right_text' => 'In Rs.' ], 'body' => $mop_summary_rs ];

		return response()->json(['status' => 'success' , 'print_count' => 1 , 'data' => $data ],200);

	}

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


}