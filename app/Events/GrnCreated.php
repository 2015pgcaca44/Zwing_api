<?php

namespace App\Events;

class GrnCreated extends Event
{

	public $params = null;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($params)
    {
        $params['event_class'] = __CLASS__;
        $this->params = $params;
    }
}
