<?php

namespace App\Events;

use App\EventLog;

class PacketVoid
{
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public $params = null;
    public function __construct($params)
    {
        $eventLog = new EventLog;
        $eventLog->v_id = $params['v_id'];
        $eventLog->type = __CLASS__;
        $eventLog->transaction_id = $params['packet_id'];
        $eventLog->save(); 

        $params['event_class'] = __CLASS__;
        $this->params = $params;
    }
}
