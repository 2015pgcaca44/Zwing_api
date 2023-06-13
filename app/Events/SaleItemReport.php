<?php

namespace App\Events;

class SaleItemReport extends Event
{
    /**
     * Create a new event instance.
     *
     * @return void
     */

    public $params = null;

    public function __construct($params)
    {
    	$params['event_class'] = __CLASS__;
        $this->params = $params;
       // $this->params = $params;
    }
}
