<?php

namespace App\Listeners;

use App\Events\CreateOpeningStock;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Http\Controllers\Erp\OpeningStockController;
use App\Organisation;
use Exception;
use Log;
use DB;

class OpeningStockPush implements ShouldQueue
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
     * @param  CreateOpeningStock  $event
     * @return void
     */
    public function handle(CreateOpeningStock $event)
    {
        $params = $event->params;
        $params['job_class'] = __CLASS__;
        //$org = Organisation::select('client_id')->where('id', $params['v_id'])->first();
        Log::info( 'OpeningStock Push for os_id: '.$params['os_id'].' v_id :'.$params['v_id'].' DB name is: '. DB::connection()->getDatabaseName() );
        $org = DB::connection('mysql')->table('vendor')->select('client_id')->where('id', $params['v_id'])->first();
        if ($params['os_id']!='' && $org->client_id >= 1 ) {
            $params['client_id'] = $org->client_id;
            $stockTransfer = new OpeningStockController;
            $response = $stockTransfer->OpeningStockPush($params);
            if(isset($response['error']) ){
                throw new Exception ($response['message']);
            }
        }
    }
}
