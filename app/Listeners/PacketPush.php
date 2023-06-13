<?php

namespace App\Listeners;

use App\Events\PacketCreated;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Http\Controllers\Erp\PacketController;
use App\Organisation;
use Exception;
use Log;
use DB;
class PacketPush implements ShouldQueue
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
     * @param  PacketCreated  $event
     * @return void
     */
    public function handle(PacketCreated $event)
    {
        
        $params = $event->params;
        $params['job_class'] = __CLASS__;
        //$org = Organisation::select('client_id')->where('id', $params['v_id'])->first();
        Log::info('Packet push for packet_id: '.$params['packet_id'].' v_id :'.$params['v_id'].' DB name is: '. DB::connection()->getDatabaseName() );
        $org = DB::connection('mysql')->table('vendor')->select('client_id')->where('id', $params['v_id'])->first();
        if ($params['packet_id']!='' && $org->client_id >= 1 ) {
            Log::info( ' Packet Push for grt id: '.$params['packet_id'].' v_id :'.$params['v_id'].' client_id: '. $org->client_id );
            
            $params['client_id'] = $org->client_id;
            $packet = new PacketController;
            $response = $packet->packetPush($params);
            if(isset($response['error']) ){
                throw new Exception ($response['message']);
            }
        }

    }
}
