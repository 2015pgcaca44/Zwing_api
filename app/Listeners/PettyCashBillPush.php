<?php

namespace App\Listeners;

use App\Events\CashPointTransfer;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Http\Controllers\Erp\CashManagementController;
use App\Organisation;
use App\CashTransaction;
use App\StoreExpense;
use App\CashPoint;
use App\CashPointHeader;
use Exception;
use Log;
use DB;

class PettyCashBillPush implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */

    public $queue = 'external';

    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  Loyalty  $event
     * @return void
     */
    public function handle(CashPointTransfer $event)
    {
        $params = $event->params;
        $params['job_class'] = __CLASS__;
        // $org = Organisation::select('client_id')->where('id', $params['v_id'])->first();
         Log::info('Petty push for cash_transaction_id: '.$params['cash_transaction_id'].' v_id :'.$params['v_id'].' DB name is: '. DB::connection()->getDatabaseName() );
        $org = DB::connection('mysql')->table('vendor')->select('client_id')->where('id', $params['v_id'])->first();
        if ($params['cash_transaction_id']!=''  && $org->client_id >= 1 ) {
            $params['client_id'] = $org->client_id;
            $transfer_type       = $params['transfer_type'];
            JobdynamicConnection($params['v_id']);    
            if($transfer_type=='1')
            { 
                $transaction   =  CashTransaction::where('id',$params['cash_transaction_id'])
                                                ->where('v_id',$params['v_id'])
                                                ->where('store_id',$params['store_id'])
                                                ->first();
                $cashPointId    =  $transaction->request_to_id;                                
            }else
            {
                $transaction   =  StoreExpense::where('id',$params['cash_transaction_id'])
                                            ->where('v_id',$params['v_id'])
                                            ->where('store_id',$params['store_id'])
                                            ->first();
                $cashPointId   =  $transaction->expense_type_id;                             
            }
            $cashpointtypes = CashPoint::join('cash_point_types','cash_point_types.id','=','cash_points.cash_point_type_id')
                                        ->where('cash_points.id',$cashPointId)
                                        ->where('cash_points.v_id',$params['v_id'])
                                        ->where('cash_points.store_id',$params['store_id'])
                                        ->first(); 
            $cashpoint_ref_header = CashPointHeader::where('id',$cashpointtypes->cash_point_header_id)->first();
            if(!empty($cashpointtypes) && $cashpointtypes->is_third_party=='1')
            {   
               $params['PTCHeadCode'] = $cashpoint_ref_header->cash_point_header_ref_code;   
                $cash = new CashManagementController;
                $response = $cash->pettyCashPush($params);
              if(isset($response['error']) )
              {
                 throw new Exception ($response['message']);
              }
            } 
        }
    }

     /**
     * Determine whether the listener should be queued.
     *
     * @param  \App\Events\OrderPlaced  $event
     * @return bool
     */
    // public function shouldQueue($event)
    // {
    //     return  $event->params['db_structure'] ==2;
    // }
}
