<?php

namespace App\Listeners;

use App\Events\Authlog;

class Userlogout
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct($userdata)
    {
         echo $this->userdata = $userdata;
    }

    /**
     * Handle the event.
     *
     * @param  Authlog  $event
     * @return void
     */
    public function handle(Authlog $event)
    {
        //
    }
}
