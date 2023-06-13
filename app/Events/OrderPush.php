<?php

namespace App\Events;

class OrderPush extends Event
{
    public $curlRequestPushData;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($curlRequestPushData)
    {
        $this->curlRequestPushData = $curlRequestPushData;
    }
}
