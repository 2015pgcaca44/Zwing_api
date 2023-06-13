<?php

namespace App\Listeners;

use App\Events\DaySettlementCreated;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Http\Controllers\Erp\DaySettlementController;
use App\Organisation;
use Exception;
use Log;
use DB;

class DaySettlementPush implements ShouldQueue
{
    //use InteractsWithQueue;

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
     * @param  DaySettlementCreated  $event
     * @return void
     */
    public function handle(DaySettlementCreated $event)
    {
        $params = $event->params;
        $params['job_class'] = __CLASS__;
        //$org = Organisation::select('client_id')->where('id', $params['v_id'])->first();
        Log::info( 'DaySettlementPush Push for ds_id: '.$params['ds_id'].' v_id :'.$params['v_id'].' DB name is: '. DB::connection()->getDatabaseName() );
        $org = DB::connection('mysql')->table('vendor')->select('client_id')->where('id', $params['v_id'])->first();
        if ($params['ds_id']!='' && $org->client_id >= 1 ) {
            Log::info( 'DaySettlementPush Push for ds_id: '.$params['ds_id'].' v_id :'.$params['v_id'].' client_id : '. $org->client_id );
            
            $params['client_id'] = $org->client_id;
            $stockTransfer = new DaySettlementController;
            $response = $stockTransfer->settlementPush($params);
            if(isset($response['error']) ){
                throw new Exception ($response['message']);
            }
        }
    }
}
