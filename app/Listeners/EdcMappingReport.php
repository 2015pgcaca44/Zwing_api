<?php

namespace App\Listeners;

use App\Events\EdcLog;
use App\EdcMappingLog;

class EdcMappingReport
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  EdcLog  $event
     * @return void
     */
    public function handle(EdcLog $event)
    {
        $edclog                = new EdcMappingLog;
        $edclog->udid          = $event->edcdata['udid'];
        $edclog->serial_number = $event->edcdata['serial_number'];
        $edclog->bluetooth_id  = $event->edcdata['bluetooth_id'];
        $edclog->edc_type      = $event->edcdata['edc_type'];
        $edclog->username      = $event->edcdata['username']; 
        $edclog->password      = $event->edcdata['password']; 
        $edclog->v_id          = $event->edcdata['v_id']; 
        $edclog->store_id      = $event->edcdata['store_id']; 
        $edclog->vu_id         = $event->edcdata['vu_id']; 
        $edclog->type          = $event->edcdata['type']; 
        $result                = $edclog->save(); 

    }
}
