<?php

namespace App\Listeners;

use App\Events\Authlog;
use App\EventLog;
use App\DeviceStorage;

class Userlogin
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {  
    }

    /**
     * Handle the event.
     *
     * @param  Authlog  $event
     * @return void
     */
    public function handle(Authlog $event)
    {
        $eventlog               = new EventLog;
        $eventlog->store_id     = $event->userdata['store_id'];
        $eventlog->store_name     = $event->userdata['store_name'];
        $eventlog->v_id         = $event->userdata['v_id'];
        $eventlog->staff_id     = $event->userdata['staff_id'];
        $eventlog->staff_name     = $event->userdata['staff_name'];
        $eventlog->type         = $event->userdata['type'];
        $eventlog->api_token    = $event->userdata['api_token'];
        $eventlog->ip_address   = $event->userdata['ip_address']; 
        $eventlog->latitude     = $event->userdata['latitude']; 
        $eventlog->longitude    = $event->userdata['longitude']; 
        $eventlog->trans_from    = $event->userdata['trans_from']; 
        $result                 = $eventlog->save(); 
         
        if(isset($event->userdata['udid'])){
            
            $device  = DeviceStorage::where('udid',$event->userdata['udid'])->first();
            if(!$device){
                $devicename  = explode('_',$event->userdata['udid']);
                // print_r( $devicename );die;
                $devicedata = array('udid'=> $event->userdata['udid'],
                                    'name'=> end($devicename),
                                    'v_id'=>  $event->userdata['v_id']);
                DeviceStorage::create($devicedata);
            }
        }

    }
}
