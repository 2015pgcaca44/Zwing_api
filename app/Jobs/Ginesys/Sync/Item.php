<?php

namespace App\Jobs\Ginesys\Sync;

use App\Http\Controllers\Ginesys\DataPushApiController;
use App\Jobs\Job;
use Log;

class Item extends Job
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
        Log::info('['.$this->funArgs->store_db_name.'] Data Sync New Invitem Start');
        $item->InvItemNewSync($this->funArgs);
        Log::info('['.$this->funArgs->store_db_name.'] Data Sync New Invitem End');
        Log::info('['.$this->funArgs->store_db_name.'] Data Sync Update Invitem Start');
        $item->InvItemUpdateSync($this->funArgs);
        Log::info('['.$this->funArgs->store_db_name.'] Data Sync Update Invitem End');
    }
}
