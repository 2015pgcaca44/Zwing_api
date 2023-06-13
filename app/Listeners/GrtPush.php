<?php

namespace App\Listeners;

use App\Events\GrtCreated;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Http\Controllers\Erp\GrtController;
use App\Organisation;
use Exception;
use Log;
use DB;

class GrtPush implements ShouldQueue
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
    public function handle(GrtCreated $event)
    {
        
        $params = $event->params;
        $params['job_class'] = __CLASS__;
        // $org = Organisation::select('client_id')->where('id', $params['v_id'])->first();
        Log::info( ' Grt Push for grt id: '.$params['grt_id'].' v_id :'.$params['v_id'].' DB name is: '. DB::connection()->getDatabaseName() );
        $org = DB::connection('mysql')->table('vendor')->select('client_id')->where('id', $params['v_id'])->first();
        if ($params['grt_id']!='' && $org->client_id >= 1 ) {
            Log::info( ' Grt Push for grt id: '.$params['grt_id'].' v_id :'.$params['v_id'].' client_id: '. $org->client_id);
            
            $params['client_id'] = $org->client_id;
            $grt = new GrtController;
            $response = $grt->grtPush($params);
            if(isset($response['error']) ){
                throw new Exception ($response['message']);
            }
        }

    }
}
