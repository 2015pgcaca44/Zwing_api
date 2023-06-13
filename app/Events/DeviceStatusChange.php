<?php

namespace App\Events;

class DeviceStatusChange extends Event //Api Status change
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
