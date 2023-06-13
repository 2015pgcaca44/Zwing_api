<?php

namespace App\Jobs\Ginesys\Sync;

use App\Http\Controllers\Ginesys\DataPushApiController;
use App\Jobs\Job;
use Log;

class Promo extends Job
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
        Log::info('['.$this->funArgs->store_db_name.'] Start Data Sync New Promo Master');
        $item->PromoMasterNewSync($this->funArgs);
        Log::info('['.$this->funArgs->store_db_name.'] End Data Sync New Promo Master ');
        Log::info('['.$this->funArgs->store_db_name.'] Start Data Sync Update Promo Master');
        $item->PromoMasterUpdateSync($this->funArgs);
        Log::info('['.$this->funArgs->store_db_name.'] End Data Sync Update Promo Master ');

        Log::info('['.$this->funArgs->store_db_name.'] Start Data Sync New Promo Buy');
        $item->PromoBuyNewSync($this->funArgs);
        Log::info('['.$this->funArgs->store_db_name.'] End Data Sync New Promo Buy ');
        Log::info('['.$this->funArgs->store_db_name.'] Start Data Sync Update Promo Buy');
        $item->PromoBuyUpdateSync($this->funArgs);
        Log::info('['.$this->funArgs->store_db_name.'] End Data Sync Update Promo Buy ');

        Log::info('['.$this->funArgs->store_db_name.'] Start Data Sync New Promo Slab');
        $item->PromoSlabNewSync($this->funArgs);
        Log::info('['.$this->funArgs->store_db_name.'] End Data Sync New Promo Slab ');
        Log::info('['.$this->funArgs->store_db_name.'] Start Data Sync Update Promo Slab');
        $item->PromoSlabUpdateSync($this->funArgs);
        Log::info('['.$this->funArgs->store_db_name.'] End Data Sync Update Promo Slab ');
    }
}
