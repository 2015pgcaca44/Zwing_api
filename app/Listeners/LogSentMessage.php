<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSent;
use Log;

class LogSentMessage
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  MessageSent  $event
     * @return void
     */
    public function handle(MessageSent $event)
    {
        Log::info('Senting Mail :- '.json_encode($event->data));
    }
}
