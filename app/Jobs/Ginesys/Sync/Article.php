<?php

namespace App\Jobs\Ginesys\Sync;

use App\Http\Controllers\Ginesys\DataPushApiController;
use App\Jobs\Job;
use Log;

class Article extends Job
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
        Log::info('['.$this->funArgs->store_db_name.'] Data Sync New Invarticle Start');
        $item->InvArticleNewSync($this->funArgs);
        Log::info('['.$this->funArgs->store_db_name.'] Data Sync New Invarticle End');
        Log::info('['.$this->funArgs->store_db_name.'] Data Sync Update Invarticle Start');
        $item->InvArticleUpdateSync($this->funArgs);
        Log::info('['.$this->funArgs->store_db_name.'] Data Sync Update Invarticle End');
    }
}
