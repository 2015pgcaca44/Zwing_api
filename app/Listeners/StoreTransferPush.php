<?php

namespace App\Listeners;

use App\Events\StoreTransferCreated;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Http\Controllers\Erp\GrtController;
use App\Organisation;
use Exception;
use Log;
use DB;

class StoreTransferPush implements ShouldQueue
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
     * @param  GrtCreated  $event
     * @return void
     */
    public function handle(StoreTransferCreated $event)
    {
        
        $params = $event->params;
        $params['job_class'] = __CLASS__;
        // $org = Organisation::select('client_id')->where('id', $params['v_id'])->first();
        Log::info( ' Store Transfer Push for  id: '.$params['stock_transfer_order_id'].' v_id :'.$params['v_id'].' DB name is: '. DB::connection()->getDatabaseName() );
        $org = DB::connection('mysql')->table('vendor')->select('client_id')->where('id', $params['v_id'])->first();
        if ($params['stock_transfer_order_id']!='' && $org->client_id >= 1 ) {
            Log::info( ' Store transfer Push for  id: '.$params['stock_transfer_order_id'].' v_id :'.$params['v_id'].' client_id: '. $org->client_id);
            
            $params['client_id'] = $org->client_id;
            $grt = new GrtController;
            $response = $grt->storeTransferPush($params);
            if(isset($response['error']) ){
                throw new Exception ($response['message']);
            }
        }

    }
}
