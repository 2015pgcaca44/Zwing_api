<?php

namespace App\Jobs;

class TestSyncJob extends Job
{
    // public $tries = 1;

    protected $v_id;

    protected $store_id;

    public function __construct($v_id, $store_id)
    {
        $this->v_id = $v_id;
        $this->store_id = $store_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // return DB::table('vendor')->first();
        return ['Cool'];
    }
}
