<?php

namespace App\Listeners;

use App\Events\OrderPush;
use App\Invoice;
use App\OrderExtra;
// use Illuminate\Contracts\Queue\ShouldQueue;
use App\Http\Controllers\CloudPos\DataFetchingApi;

class PushOrderCinema
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
     * @param  OrderPush  $event
     * @return void
     */
    public function handle(OrderPush $event)
    {
       foreach ($event->curlRequestPushData as $key => $value) {
        $cinepolisOrderPush = [
                    'UserSessionId' => $value['UserSessionId'],
                    'CinemaId' => $value['CinemaId'],
                    'Concessions' =>$value['Concessions'],
                    'ReturnOrder' => $value['ReturnOrder'],
                ];

           $curl = curl_init();
                    curl_setopt_array($curl, array(
                    CURLOPT_URL => "http://14.143.181.141:88/WSVistaWebClient/RESTTicketing.svc/order/concessions",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30000,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => json_encode($cinepolisOrderPush),
                    CURLOPT_HTTPHEADER => array(
                    // Set here requred headers
                    "accept: */*",
                    "accept-language: en-US,en;q=0.8",
                    "content-type: application/json",
                    ),
                    ));

                    $response = curl_exec($curl);
                     $err = curl_error($curl);
                    curl_close($curl);
                    
             if(isset($value['invoice_id'])){
             Invoice::where('invoice_id', $value['invoice_id'])->update([ 'third_party_response' => $response ]);

                        $o_id = $value['invoice_id'];
                 }
            elseif(isset($value['order_id']))
                {
                        Invoice::where('ref_order_id', $value['order_id'])->update([ 'third_party_response' => $response ]);
                        $o_id = $value['order_id'];
                }
                    // insert into the order_extra table for separate seat number and hall number
                    $orderExtra = new OrderExtra;
                    $orderExtra->v_id = $value['v_id'];
                    $orderExtra->store_id = $value['store_id'];
                    if(isset($value['invoice_id'])){
                    $orderExtra->invoice_id = $value['invoice_id'];
                }elseif(isset($value['order_id'])){
                    $orderExtra->order_id = $value['order_id'];
                }
                    $orderExtra->seat_no = $value['seat_no'];
                    $orderExtra->hall_no = $value['hall_no'];
                    $orderExtra->save();
       }
    }
}
