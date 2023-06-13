<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\CashPointSummary;
use App\SettlementSession;
use App\CashPoint;
use App\CashPointType;
use App\VendorSetting;
use DB;

class SudoSessionController extends Controller
{


	public function sudoSessionClosed(Request $request){
		

		$opensessionDetails =SettlementSession::where('status','!=','1')
												->whereNull('closing_time')
												->get();
		  										
		if(!empty($opensessionDetails)){

			 $total=count($opensessionDetails);
		     $row = 0;
			foreach ($opensessionDetails as $opensessiondetail) {
				$row++;
			 $this->closeSettlementSession($opensessiondetail->id);                      
			}
			if($total==$row){
             $this->cashPointSummaryupdate();
			}

		}

	}

	private function closeSettlementSession($id){
        //dd($id);
		$settlement  = SettlementSession::find($id);
		$oenpingdate  =$settlement->settlement_date;
		$date = date_create($oenpingdate);
		date_time_set($date, 23, 59);
	    $closing_time =date_format($date, 'Y-m-d H:i:s'); 
        $closing_balance = 0.00; 
        $cashmanagementSetting= $this->isCashmanagementSettingEnable($settlement);

		if($cashmanagementSetting){
        
         $cashPointList = $this->terminalCashPoint($settlement->store_id,$settlement->v_id,$settlement->cash_register_id);
		 $cashPointSummary = $this->cashPointlastdetails($cashPointList,$settlement->id);
		 //dd($cashPointSummary);
         $totalcash = $cashPointSummary->closing;
         
		}else{
          $data = $this->getPaymentsdetails($settlement->id,$settlement->vu_id,$settlement->opening_time,$closing_time);
          $closing =($data['tender']- $data['refund']);
          $totalClosingcash = ($closing+$settlement->opening_balance);
          $totalcash = format_number($totalClosingcash);
		}
	    $overOrShort = '';
        $overOrShort  = (float)$totalcash -(float)$closing_balance;
	    if($overOrShort < 0){
	     $overOrShortS = '('.format_number($overOrShort).')';
	    }else{
	     $overOrShortS = format_number($overOrShort);
	    }     

		if($settlement){
			$settlement->status = '1';
			$settlement->closing_balance=(float)$closing_balance;
			$settlement->closing_time=$closing_time;
			$settlement->short_access=$overOrShortS;
			$settlement->session_close_type ="SUDO";
			$settlement->save();
			$this->openSettlementSession($settlement,$closing_time);
		} 
	}

	private function openSettlementSession($settlement,$closing_time){
		if($settlement->partant_session_id==null){
		 	$partant_session_id = $settlement->id; 
		}else{  
           $partant_session_id = $settlement->partant_session_id; 
		}
			$todaydate= date('Y-m-d');
			$date = date_create($todaydate);
			date_time_set($date, 00, 01);
			$opening_time =date_format($date, 'Y-m-d H:i:s'); 
         
         $cashmanagementSetting= $this->isCashmanagementSettingEnable($settlement);

		if($cashmanagementSetting){
        
         $cashPointList = $this->terminalCashPoint($settlement->store_id,$settlement->v_id,$settlement->cash_register_id);
		 $cashPointSummary = $this->cashPointlastdetails($cashPointList,$settlement->id);
         $opening_balance = $cashPointSummary->closing;
         

		}else{

          $data = $this->getPaymentsdetails($settlement->id,$settlement->vu_id,$settlement->opening_time,$closing_time);
          $closing =($data['tender']- $data['refund']);
          $tolalclosing = (float)$settlement->opening_balance+(float)$closing;  
          $opening_balance = format_number($tolalclosing);
		}

		$settlementSession = new SettlementSession;
		$settlementSession->v_id=     $settlement->v_id;
		$settlementSession->store_id= $settlement->store_id;
		$settlementSession->vu_id=    $settlement->vu_id;
		$settlementSession->partant_session_id=$partant_session_id;
		$settlementSession->type= $settlement->type;
		$settlementSession->trans_from = $settlement->trans_from;
		$settlementSession->settlement_date = $todaydate;
		$settlementSession->opening_balance = $opening_balance;
		$settlementSession->opening_time = $opening_time;
		$settlementSession->cash_register_id =$settlement->cash_register_id;
		$settlementSession->save();

		if($cashmanagementSetting){
			$this->terminalCashPointSummaryupdate($settlement,$settlementSession->id,$settlementSession->partant_session_id,$todaydate,$opening_time);
		}

	}

	private function terminalCashPointSummaryupdate($settlement,$session_id,$partant_session_id,$todaydate,$opening_time){

         $cashPointList = $this->terminalCashPoint($settlement->store_id,$settlement->v_id,$settlement->cash_register_id);
		 $cashPointSummary = $this->cashPointlastdetails($cashPointList,$settlement->id);
         $todayCashSummary     =  new CashPointSummary;
         $todayCashSummary->store_id = $settlement->store_id;
         $todayCashSummary->v_id      = $settlement->v_id;
         $todayCashSummary->session_id = $session_id;
         $todayCashSummary->partant_session_id=$partant_session_id;
         $todayCashSummary->cash_point_id =$cashPointList->id;
         $todayCashSummary->cash_point_name=$cashPointList->cash_point_name;
         $todayCashSummary->opening   = $cashPointSummary->closing;
         $todayCashSummary->pay_in   = '0.00';
         $todayCashSummary->pay_out   = '0.00';
         $todayCashSummary->closing   =$cashPointSummary->closing;
         $todayCashSummary->date    =  $todaydate;
         $todayCashSummary->time   =$opening_time; 
         $todayCashSummary->save(); 

	}

    private function cashPointlastdetails($cashPointList,$settlement){
          $cashPointSummary =CashPointSummary::where('cash_point_id',$cashPointList->id)
				                               ->where('session_id',$settlement)
				                               ->first(); 
           return $cashPointSummary;                    	
    }

	private function isCashmanagementSettingEnable($opensessiondetail){

		$role_id = getRoleId($opensessiondetail->vu_id);

		$params  = array('v_id'=>$opensessiondetail->v_id,
			            'store_id'=>$opensessiondetail->store_id,
			            'name' =>'store',
			            'user_id'=>$opensessiondetail->vu_id,
			            'role_id'=>$role_id
	                    );
		$storeSetting = $this->getSetting($params)->pluck('settings')->toArray();
		$storeSettings = json_decode($storeSetting[0]);
		if(isset($storeSettings->cashmanagement) && $storeSettings->cashmanagement->DEFAULT->status=='1'){
			return $opensessiondetail; 
		}else{
			return false;
			
		}

	}

    public function terminalCashPoint($store_id,$v_id,$ref_id){
      
            $cashPoint=CashPoint::where('store_id',$store_id)
			                      ->where('v_id',$v_id)
			                      ->where('ref_id',$ref_id)
			                      ->first();
           return $cashPoint;           
    }
	public function getSetting($params)
	{
		//dd($params);
		$v_id = $params['v_id'];
		$store_id = $params['store_id'];
		$name = $params['name'];
		$user_id = $params['user_id'];
		$role_id = $params['role_id'];
dd();
		$settings = VendorSetting::select('id', 'name', 'settings')->where('v_id', $v_id);
		if ($name != '') {
			$settings = $settings->where('name', $name);
		}

		//checking priority wise setting
		$userIdExists = VendorSetting::whereuser_id($user_id)->where('name',$name)->exists();
		if(isset($userIdExists) && $userIdExists == true){ dd('if');
			$settings = $settings->where('user_id',$user_id)->orderBy('updated_at','desc');
		}
		else{ dd('else');
			$roleIdExists = VendorSetting::whererole_id($role_id)->where('name',$name)->exists();
			if(isset($roleIdExists) && $roleIdExists == true){
				$settings = $settings->where('role_id',$role_id)->orderBy('updated_at','desc');
			}else{
				$storeIdExists = VendorSetting::wherestore_id($store_id)->where('name',$name)->exists();
				if(isset($storeIdExists) && $storeIdExists == true){
					$settings = $settings->where('store_id',$store_id)->orderBy('updated_at','desc');
				}
			}
		}

		$settings = $settings->get();
		if ($settings->isEmpty()) {
			return null;
		} else {
			return $settings;
		}
	}
	public function cashPointSummaryupdate(){
                     
             $type=['Store-Cash','Petty-Cash'];
             $cashTypes = CashPointType::whereIn('type_name',$type)->get();
             $cpt = []; 
             foreach ($cashTypes as $cashType) {
              $cpt[] = $cashType->id;
             }    
              $cashPoints = CashPoint::whereIn('cash_point_type_id',$cpt)->get();

               foreach ($cashPoints as $cashPoint) {
        
                   $cashPointSumary=CashPointSummary::where('store_id',$cashPoint->store_id)
                                          ->where('v_id',$cashPoint->v_id)
                                         ->where('cash_point_id',$cashPoint->id)
                                         ->orderBy('id','DESC')->first();
                                         
                  if($cashPointSumary){
                    $opening = $cashPointSumary->closing;
                    $closing =  $cashPointSumary->closing;   
                  }else{
                    $opening = '0.00';
                    $closing = '0.00';

                  }
	                $todayCashSummary     =  new CashPointSummary;
	                $todayCashSummary->store_id = $cashPoint->store_id;
			        $todayCashSummary->v_id      = $cashPoint->v_id;
			        $todayCashSummary->cash_point_id =$cashPoint->id;
			        $todayCashSummary->cash_point_name=$cashPoint->cash_point_name;
			        $todayCashSummary->opening   =$opening;
			        $todayCashSummary->closing      =$closing;
			        $todayCashSummary->date    =date('Y-m-d');
			        $todayCashSummary->time   =date('h:i:s'); 
			        $todayCashSummary->save();        

               }

	} 
	public function getPaymentsdetails($id,$vu_id,$opening_time,$closing_time){
   
      $payments = DB::table('payments as p')
						 ->select(DB::raw('p.amount, CAST(p.cash_collected as decimal) as cash_collected,CAST( p.cash_return as decimal) as cash_return, p.method'))
						 ->join('orders as o', 'o.order_id' , 'p.order_id')
						 ->where('o.date', date('Y-m-d'))
						 ->where('p.session_id',$id)
						 ->where('o.vu_id', $vu_id)
						 ->where('o.created_at','>=',$opening_time)
						 ->where('o.created_at','<=',$closing_time)
						 ->get();
	 $tender = $payments->sum('cash_collected');
	 $refund = $payments->sum('cash_return');
	 return (['tender'=>$tender,'refund'=>$refund]);
	}
}
