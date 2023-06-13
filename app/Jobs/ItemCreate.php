<?php

namespace App\Jobs;
use Log;
use App\Model\InboundApi;
use App\Http\Controllers\ItemController;
use Illuminate\Http\Request;

class ItemCreate extends Job
{
    protected $funArgs;
    /**
     * Create a new job instance.
     *
     * @return void
     */

    public $queue = 'itemcreate-sequential';

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

        $v_id = $this->funArgs['v_id'];
        $client_id = $this->funArgs['client_id'];
        $ack_id = $this->funArgs['ack_id'];
        $par = 'v_id-'.$v_id. '::client_id-'.$client_id.'::ack_id-'.$ack_id;
        Log::info(' Item Creating Job Processing Start '.$par);
        $itemController = new ItemController;
        $request = new \Illuminate\Http\Request();
                $request->merge([
                    'v_id'        => $v_id,
                    'client_id'   => $client_id,
                    'ack_id'      => $ack_id,
                    'type'        => 'pos',
                ]);
        //['v_id' => $v_id, 'client_id' => $client_id, 'ack_id' => $ack_id,'type'=>'pos']
        $itemController->processItemMasterCreationJob($request);
        Log::info(' Item Creating Job Processing End '.$par);
    }
}
