<?php

namespace App\Listeners;

use App\Events\Loyalty;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Http\Controllers\LoyaltyController;

class LoyaltyBillPush implements ShouldQueue
// class LoyaltyBillPush
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
     * @param  Loyalty  $event
     * @return void
     */
    public function handle(Loyalty $event)
    {
        if ($event->loyaltyPrams['event'] == 'billPush') {
            $loyaltyCon = new LoyaltyController;
            $loyaltyResponse = $loyaltyCon->index($event->loyaltyPrams);
        }
    }
}
