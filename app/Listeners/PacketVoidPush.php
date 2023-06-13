<?php

namespace App\Listeners;

use App\Events\PacketVoid;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Http\Controllers\Erp\PacketController;
use Log;
use DB;
use Exception;

class PacketVoidPush implements ShouldQueue
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
     * @param  PacketVoid  $event
     * @return void
     */
    public function handle(PacketVoid $event)
    {
        $params = $event->params;
        $params['job_class'] = __CLASS__;
        //$org = Organisation::select('client_id')->where('id', $params['v_id'])->first();
        Log::info('packet void push for packet_id: '.$params['packet_id'].' v_id :'.$params['v_id'].' DB name is: '. DB::connection()->getDatabaseName() );
        $org = DB::connection('mysql')->table('vendor')->select('client_id')->where('id', $params['v_id'])->first();
        
        if ($params['packet_id']!='' && $org->client_id >= 1 ) {
            Log::info( ' Packet Push Void for packet id: '.$params['packet_id'].' v_id :'.$params['v_id'].' client_id: '. $org->client_id );
            
            $params['client_id'] = $org->client_id;
            $packet = new PacketController;
            $response = $packet->packetvoid($params);
            if(isset($response['error']) ){
                throw new Exception ($response['message']);
            }
        }
    }
}
