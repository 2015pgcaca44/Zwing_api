<?php

namespace App\Listeners;

use App\Events\DepositeRefund;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Http\Controllers\Erp\InvoiceController;
use App\Organisation;
use Exception;
use Log;
use DB;

class DepositeRefundPush implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public $queue = 'external';
    
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
    public function handle(DepositeRefund $event)
    {
        $params = $event->params;
        $params['job_class'] = __CLASS__;
        
        //$org = Organisation::select('client_id')->where('id', $params['v_id'])->first();
        Log::info( 'Deposite Refund Push for payment_id: '.$params['payment_id'].' v_id :'.$params['v_id'].' DB name is: '. DB::connection()->getDatabaseName() );
        $org = DB::connection('mysql')->table('vendor')->select('client_id')->where('id', $params['v_id'])->first();
        if ($params['payment_id']!='' && $org->client_id >= 1 ) {
            $params['client_id'] = $org->client_id;
            $invoicePush = new InvoiceController;
            $response = $invoicePush->depositeRefund($params);
            if(isset($response['error']) ){
                throw new Exception ($response['message']);
            }
        }
    }

     /**
     * Determine whether the listener should be queued.
     *
     * @param  \App\Events\OrderPlaced  $event
     * @return bool
     */
    public function shouldQueue($event)
    {
        return  $event->params['db_structure'] ==2;
    }
}
