<?php

namespace App\Events;

class StockTransfer extends Event
{
	 public $params = null;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($params)
    {
        //
        // $eventLog = new EventLog;
        // $eventLog->v_id = $params['v_id'];
        // $eventLog->type = __CLASS__;
        // $eventLog->transaction_id = $params['invoice_id'];
        // $eventLog->save();

        $params['event_class'] = __CLASS__;
        $this->params = $params;
    }
}
