<?php

namespace App\Listeners;

use App\Events\GrnCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Http\Controllers\Erp\GrnController;
use App\Organisation;
use Exception;
use Log;
use DB;

class GrnPush implements ShouldQueue
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
    public function handle(GrnCreated $event)
    {
        $params = $event->params;
        $params['job_class'] = __CLASS__;
        // $org = Organisation::connection('mysql')->select('client_id')->where('id', $params['v_id'])->first();
        Log::info( ' Grn Push for grn id: '.$params['grn_id'].' v_id :'.$params['v_id'].' DB name is: '. DB::connection()->getDatabaseName() );
        $org = DB::connection('mysql')->table('vendor')->select('client_id')->where('id', $params['v_id'])->first();
       
        if ($params['grn_id']!=''  && $org->client_id >= 1 ) {
            Log::info( ' Grn Push for grn id: '.$params['grn_id'].' v_id :'.$params['v_id'].' client_id: '. $org->client_id);

            $params['client_id'] = $org->client_id;
            $grnC = new GrnController;
            $response = $grnC->grnPush($params);
            if(isset($response['error']) ){
                throw new Exception ($response['message']);
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
