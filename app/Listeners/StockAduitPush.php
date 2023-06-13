<?php

namespace App\Listeners;

use App\Events\StockAduit;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Http\Controllers\Erp\StockAduitController;
use App\Organisation;
use Exception;
use Log;
use DB;

class StockAduitPush implements ShouldQueue
{
    use InteractsWithQueue;

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
     * @param  StockAduit  $event
     * @return void
     */
    public function handle(StockAduit $event)
    {
        $params = $event->params;
        $params['job_class'] = __CLASS__;
        // $org = Organisation::select('client_id')->where('id', $params['v_id'])->first();
        Log::info('StockAduit push for audit_plan_id: '.$params['audit_plan_id'].' v_id :'.$params['v_id'].' DB name is: '. DB::connection()->getDatabaseName() );
        $org = DB::connection('mysql')->table('vendor')->select('client_id')->where('id', $params['v_id'])->first();
        if ($params['audit_plan_id']!='' && $org->client_id >= 1 ) {
            $params['client_id'] = $org->client_id;
            $stockTransfer = new StockAduitController;
            $response = $stockTransfer->StockAduitPush($params);
            if(isset($response['error']) ){
                throw new Exception ($response['message']);
            }
        }
    }
}
