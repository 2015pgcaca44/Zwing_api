<?php

namespace App\Events;

class DataFetchCurl extends Event
{
	public $curlRequestData;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($curlRequestData)
    {
        $this->curlRequestData = $curlRequestData;
    }
}
