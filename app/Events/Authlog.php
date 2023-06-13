<?php

namespace App\Events;
use App\EventLog;
use Illuminate\Queue\SerializesModels;


class Authlog extends Event
{
    /**
     * Create a new event instance.
     *
     * @return void
     */

    use SerializesModels;

    public function __construct($userdata)
    {
        $this->userdata = $userdata;

    
        /*print_r($userdata['store_id']);
        die;

        $eventlog  = new EventLog;
        $eventlog->store_id = $userdata['store_id'];
        $eventlog->vendor_id = $userdata['vendor_id'];
        $eventlog->staff_id = $userdata['staff_id'];
        $eventlog->ip_address = $userdata['ip_address'];
		 */
        //return $eventlog->save(); 
        
        //print_r($this->userid);
    }

     
}
