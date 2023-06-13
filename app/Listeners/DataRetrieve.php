<?php

namespace App\Listeners;

use App\Events\DataFetchCurl;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Http\Controllers\CloudPos\DataFetchingApi;

class DataRetrieve implements ShouldQueue
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
     * @param  DataFetchCurl  $event
     * @return void
     */
    public function handle(DataFetchCurl $event)
    {
         $event->curlRequestData;
         $curl = new DataFetchingApi;
         $curl->dataFetchRequest();
    }
}
