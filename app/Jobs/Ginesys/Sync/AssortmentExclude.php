<?php

namespace App\Jobs\Ginesys\Sync;

use App\Http\Controllers\Ginesys\DataPushApiController;
use App\Jobs\Job;
use Log;

class AssortmentExclude extends Job
{
    protected $funArgs;
    /**
     * Create a new job instance.
     *
     * @return void
     */

    public $timeout = 120;

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
        Log::info('['.$this->funArgs->store_db_name.'] Start Data Sync Truncate & Insert Promo Assortment Exclude');
        $item->PromoAssortmentExcludeSync($this->funArgs);
        Log::info('['.$this->funArgs->store_db_name.'] End Data Sync Truncate & Insert Promo Assortment Exclude');
    }
}
