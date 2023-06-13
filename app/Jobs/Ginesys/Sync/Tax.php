<?php

namespace App\Jobs\Ginesys\Sync;

use App\Http\Controllers\Ginesys\DataPushApiController;
use App\Jobs\Job;
use Log;

class Tax extends Job
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
        Log::info('['.$this->funArgs->store_db_name.'] Data Sync New Invhsnsacmain Start');
        $item->InvHsnsacmainNewSync($this->funArgs);
        Log::info('['.$this->funArgs->store_db_name.'] Data Sync New Invhsnsacmain End');
        Log::info('['.$this->funArgs->store_db_name.'] Data Sync Update Invhsnsacmain Start');
        $item->InvHsnsacmainUpdateSync($this->funArgs);
        Log::info('['.$this->funArgs->store_db_name.'] Data Sync Update Invhsnsacmain End');

        Log::info('['.$this->funArgs->store_db_name.'] Data Sync New Invhsnsacdet Start');
        $item->InvHsnsadetNewSync($this->funArgs);
        Log::info('['.$this->funArgs->store_db_name.'] Data Sync New Invhsnsacdet End');
        Log::info('['.$this->funArgs->store_db_name.'] Data Sync Update Invhsnsacdet Start');
        $item->InvHsnsadetUpdateSync($this->funArgs);
        Log::info('['.$this->funArgs->store_db_name.'] Data Sync Update Invhsnsacdet End');

        Log::info('['.$this->funArgs->store_db_name.'] Data Sync New Invhsnsacslab Start');
        $item->InvHsnsaclabNewSync($this->funArgs);
        Log::info('['.$this->funArgs->store_db_name.'] Data Sync New Invhsnsacslab End');
        Log::info('['.$this->funArgs->store_db_name.'] Data Sync Update Invhsnsacslab Start');
        $item->InvHsnsaclabUpdateSync($this->funArgs);
        Log::info('['.$this->funArgs->store_db_name.'] Data Sync Update Invhsnsacslab End');
    }
}
