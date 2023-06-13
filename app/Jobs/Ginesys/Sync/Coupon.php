<?php

namespace App\Jobs\Ginesys\Sync;

use App\Http\Controllers\Ginesys\DataPushApiController;
use App\Jobs\Job;
use Log;

class Coupon extends Job
{
    protected $funArgs;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($funArgs)
    {
        $this->funArgs = $funArgs;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $item = new DataPushApiController;
        Log::info('['.$this->funArgs->store_db_name.'] Start Data Sync Truncate & Insert Psite Couponoffer');
        $item->psiteCouponOffer($this->funArgs);
        Log::info('['.$this->funArgs->store_db_name.'] End Data Sync Truncate & Insert Psite Couponoffer');

        Log::info('['.$this->funArgs->store_db_name.'] Start Data Sync Truncate & Insert Psite Coupon Assign');
        $item->psiteCouponAssign($this->funArgs);
        Log::info('['.$this->funArgs->store_db_name.'] End Data Sync Truncate & Insert Psite Coupon Assign');

        Log::info('['.$this->funArgs->store_db_name.'] Start Data Sync Truncate & Insert Psite Coupon Assort');
        $item->psiteCoupoAssort($this->funArgs);
        Log::info('['.$this->funArgs->store_db_name.'] End Data Sync Truncate & Insert Psite Coupon Assort');
    }
}
