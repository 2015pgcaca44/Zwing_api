<?php

namespace App\Events;
use App\EdcMappingLog;
use Illuminate\Queue\SerializesModels;


class EdcLog extends Event
{
    /**
     * Create a new event instance.
     *
     * @return void
     */

	use SerializesModels;

    public function __construct($edcdata)
    {
        $this->edcdata = $edcdata;         
    }
}
