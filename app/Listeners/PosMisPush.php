<?php

namespace App\Listeners;

use App\Events\StockAdjust;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Http\Controllers\Erp\StockAdjustmentController;
use App\Organisation;
use Log;
use DB;
use Exception;


class PosMisPush implements ShouldQueue
{
   // use InteractsWithQueue;

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
     * @param  StockAdjustment  $event
     * @return void
     */
    public function handle(StockAdjust $event)
    {
        
        $params = $event->params;
        $params['job_class'] = __CLASS__;
        //$org = Organisation::select('client_id')->where('id', $params['v_id'])->first();
        Log::info('StockAdjustment push for adj_id: '.$params['adj_id'].' v_id :'.$params['v_id'].' DB name is: '. DB::connection()->getDatabaseName() );
        $org = DB::connection('mysql')->table('vendor')->select('client_id')->where('id', $params['v_id'])->first();
        if ($params['adj_id']!='' && $org->client_id >= 1 ) {
            $params['client_id'] = $org->client_id;
            $stockAdjustment = new StockAdjustmentController;
            $response = $stockAdjustment->posMisPush($params);
            if(isset($response['error']) ){
                throw new Exception ($response['message']);
            }
        }

    }
}
