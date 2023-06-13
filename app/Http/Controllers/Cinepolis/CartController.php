<?php 
namespace App\Http\Controllers\Cinepolis;

use App\Http\Controllers\CloudPos\CartController as Extended_CloudPos_Cart_Controller;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VendorSettingController;
use App\Http\Controllers\OrderController;
use App\Http\CustomClasses\PrintInvoice;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Mail\OrderCreated;
use Illuminate\Support\Facades\Mail;

use Barryvdh\DomPDF\Facade as PDF;


use App\Store;
use App\Order;
use App\Invoice;
use App\Cart;
use App\CartOffers;
use App\Address;
use App\PartnerOffer;
use App\PartnerOfferUsed;
use App\User;
use App\VendorImage;
use DB;
use App\Payment;
use Endroid\QrCode\QrCode;
use App\Wishlist;
use Auth;
use Razorpay\Api\Api;
use App\InvoiceDetails;
use App\InvoiceItemDetails;
use App\OrderDetails;
use App\OrderItemDetails;
use App\CartDetails;
use App\Carry;
use App\Vendor;
use App\OrderExtra;

 

// Vendor sku detail
use App\Model\Items\VendorSkuDetails;
use App\Model\Stock\StockCurrentStatus;
use App\Model\Stock\StockTransactions;
use App\Model\Stock\StockLogs;
use App\Model\Stock\StockPoints;

// Event for cinepolis order push
use App\Events\OrderPush;
/**
 * 
 */
class CartController extends Extended_CloudPos_Cart_Controller
{
    
    public function checkBeforePayment(Request $request)
    {
        $response='';
        $v_id = $request->v_id;
        $store_id = $request->store_id;

        $orders = Order::where('order_id', $request->order_id)->first();
        $order_data = OrderDetails::where('t_order_id', $orders->od_id)->get()->toArray();

        // Customer seat and hall information
        $zwUserId  = generateThirdPartyUserId($v_id, $store_id);
        $customer_details = DB::table('customer_auth')->select('c_id','seat_no','hall_no','first_name','last_name','email','mobile')->where('c_id',$request->c_id)->first();
        $seat_no = $customer_details->seat_no;
        $hall_no = $customer_details->hall_no;
        $c_id = $customer_details->c_id;
        // END Customer seat and hall information
        if(!$request->checkPayment == true){
        foreach($order_data as $value) {
                    $seatinfo = [];
                    $cinepolisOrderPush = [
                    'UserSessionId' => $zwUserId,
                    'CinemaId' => 889,
                    'Concessions' => [[
                    'ItemId' => $value['barcode'],
                    'Quantity' => $value['qty']
                    ]],
                    'ReturnOrder' => true,
                ];
                    // ,
                    //     'ReturnOrder' => true,
                    //     'v_id' => $request->v_id,
                    //     'store_id' => $request->store_id,
                    //     'seat_no' => $seat_no,
                    //     'hall_no' => $hall_no,
                    //     'amount' => $request->amount
                    //     ];

                    // $dataNeededToPush =  array(
                    //         'cinepolis' => $cinepolisOrderPush,

                    // );
                // $cinepolisOrderPush = [
                //         'UserSessionId' => $value['UserSessionId'],
                //         'CinemaId' => $value['CinemaId'],
                //         'Concessions' => [$value['Concessions']],
                //         'ReturnOrder' => $value['ReturnOrder'],
                //     ];
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

            // event(new OrderPush($dataNeededToPush));
            // $lastRecord  = OrderExtra::select('ex_id','third_party_response')->orderBy('ex_id','desc')->first();
            // $lastJson = json_decode($lastRecord->third_party_response);
            }
                $orderExtra = new OrderExtra;
                $orderExtra->v_id = $v_id;
                $orderExtra->store_id = $store_id;
                $orderExtra->order_id = $request->order_id;
                $orderExtra->third_party_response = $response;
                $orderExtra->usersession = $cinepolisOrderPush['UserSessionId'];
                $orderExtra->seat_no = $seat_no;
                $orderExtra->hall_no = $hall_no;
                $orderExtra->user_id = $c_id;
                $orderExtra->save();
                $jsonDecoder = json_decode($response);
                $lastRecord = OrderExtra::select('ex_id','third_party_response')->orderBy('ex_id','desc')->first();
                $lastJson = json_decode($lastRecord->third_party_response);
            if ($jsonDecoder->ExtendedResultCode != 0) {
                return ['status'=> 'error','message'=> 'error'];
             }else{
                return ['status'=> 'success','message'=>'continue','userSessionId'=>$lastRecord->ex_id];
             }
         }else{
            $lastRecord = OrderExtra::select('ex_id','usersession')->where('order_id',$request->order_id)->orderBy('ex_id','desc')->first();
                // Start external payment
                    $externalPayment = [
                    'UserSessionId' => $lastRecord->usersession,
                    'AutoCompleteOrder' => false,
                    'ExternalPaymentInfo' => [
                        'PaymentMethod'=>'cash'
                    ],
                    'OrderCompletionInfo' => [
                        'CustomerInfo' => [
                          'Email'=> 'sunny.k@gsl.in',
                          'Phone'=> $customer_details->mobile,
                          'Name'=> $customer_details->first_name,
                          'ZipCode'=> '122002',
                          'PickupName'=> $customer_details->first_name
                        ],
                    "SendBookingConfirmationEmail" => true
                    ]
                ];
                    $curlExe = curl_init();
                    curl_setopt_array($curlExe, array(
                    CURLOPT_URL => "http://14.143.181.141:88/WSVistaWebClient/RESTTicketing.svc/order/startexternalpayment",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30000,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => json_encode($externalPayment),
                    CURLOPT_HTTPHEADER => array(
                    // Set here requred headers
                    "accept: */*",
                    "accept-language: en-US,en;q=0.8",
                    "content-type: application/json",
                    ),
                    ));

                    $CurlRes = curl_exec($curlExe);
                // End external payment
                    $CurlCon = json_decode($CurlRes);
                    if($CurlCon->ExtendedResultCode == 0){
                      $orderPayment = [
                    'PerformPayment' => true,
                    'UserSessionId' => $lastRecord->usersession,
                    'CustomerName' => $customer_details->first_name.$customer_details->last_name,
                    'CustomerEmail' =>  $customer_details->email,
                    'BookingMode'=> 1,
                    'PaymentInfo' =>[
                        "PaymentValueCents"=> $request->amount*100
                    ],
                    'ReturnOrder' => true,
                ];
                     $curlEx = curl_init();
                    curl_setopt_array($curlEx, array(
                    CURLOPT_URL => "http://14.143.181.141:88/WSVistaWebClient/RESTTicketing.svc/order/payment",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30000,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => json_encode($orderPayment),
                    CURLOPT_HTTPHEADER => array(
                    // Set here requred headers
                    "accept: */*",
                    "accept-language: en-US,en;q=0.8",
                    "content-type: application/json",
                    ),
                    ));

                    $res = curl_exec($curlEx);
                    return $res;
                }

         }
    }

    public function payment_details(Request $request)
    {
        $v_id       = $request->v_id;
        $c_id       = $request->c_id;
        $store_id   = $request->store_id;
        $t_order_id = $request->t_order_id;
        $order_id   = $request->order_id;
        $user_id    = $request->c_id;
        $pay_id     = $request->pay_id;
        $amount     = $request->amount;
        $method     = $request->method;
        $invoice_id = $request->invoice_id;
        $bank       = $request->bank;
        $wallet     = $request->wallet;
        $vpa        = $request->vpa;
        $error_description = $request->error_description;
        $status     = $request->status;
        $trans_from = $request->trans_from;
        $payment_type = 'full';
        $cash_collected     = null;
        $cash_return        = null;
        $gateway_response   = null;
        $payment_invoice_id = null;
        $orders = Order::where('order_id', $order_id)->first();

        // Customer seat and hall information starts
        $customer_details = DB::table('customer_auth')->select('seat_no','hall_no')->where('c_id',$c_id)->first();
        $seat_no = $customer_details->seat_no;
        $hall_no = $customer_details->hall_no;
        // Customer seat and hall information ends

        if ($orders->payment_type != 'full') {
            $payment_type = 'partial';
        }
        $vu_id = 0;
        if ($request->has('vu_id')) {
            $vu_id = $request->vu_id;
        }

        $payment_save_status = false;
        if ($request->has('payment_gateway_type')) {
            $payment_gateway_type = $request->payment_gateway_type; //'EZETAP'
        } else {
            $payment_gateway_type = 'RAZOR_PAY';
        }



         //Checking Opening balance has entered or not if payment is through cash
        if ($vu_id > 0 && $payment_gateway_type == 'CASH') {

            $vendorSett = new \App\Http\Controllers\VendorSettlementController;
            $response = $vendorSett->opening_balance_status($request);
            if ($response) {
                return $response;
            }
        }

        if ($payment_gateway_type == 'RAZOR_PAY') {

            $api_key = env('RAZORPAY_API_KEY');
            $api_secret = env('RAZORPAY_API_SECERET');

            $api = new Api($api_key, $api_secret);
            $razorAmount = $amount * 100;
            $razorpay_payment = $api->payment->fetch($pay_id)->capture(array('amount' => $razorAmount)); // Captures a payment

            if ($razorpay_payment) {

                if ($razorpay_payment->status == 'captured') {

                    // $date = date('Y-m-d');
                    // $time = date('h:i:s');
                    

                    // $payment->store_id = $store_id;
                    // $payment->v_id = $v_id;
                    // $payment->t_order_id = $t_order_id;
                    // $payment->order_id = $order_id;
                    // $payment->user_id = $user_id;
                    // $payment->pay_id = $pay_id;
                    // $payment->amount = $amount;
                    $method = $razorpay_payment->method;
                    $payment_invoice_id = $razorpay_payment->invoice_id;
                    $bank = $razorpay_payment->bank;
                    $wallet = $razorpay_payment->wallet;
                    $vpa = $razorpay_payment->vpa;
                    // $payment->error_description = $error_description;
                    // $payment->status = $status;
                    // $payment->date = date('Y-m-d');
                    // $payment->time = date('h:i:s');
                    // $payment->month = date('m');
                    // $payment->year = date('Y');

                    // $payment->save();

                    $payment_save_status = true;

                }

            }

        } else if ($payment_gateway_type == 'EZETAP') {

            // $t_order_id = $request->t_order_id;
            // $pay_id = $request->pay_id; //tnx->txnId
            // $amount = $request->amount; //tnx->amount
            // $method = $request->method; //tnx->paymentMode
            // $invoice_id = $request->invoice_id; //tnx->invoiceNumber
            // $status = $request->status; // $gateway_response->status

            // $date = date('Y-m-d');
            // $time = date('h:i:s');
            // $payment = new Payment;

            $gateway_response = $request->gateway_response;

            $gateway_response = json_decode($gateway_response);

            //dd($gateway_response->result);
            //var_dump($gateway_response->result->txn);
            if (!empty($gateway_response)) {
                $status = $gateway_response->status;
                $tnx = $gateway_response->result->txn;

                $pay_id = $tnx->txnId; //tnx->txnId
                $amount = $tnx->amount; //tnx->amount
                $method = $tnx->paymentMode; //tnx->paymentMode
                $invoice_id = $tnx->invoiceNumber; //tnx->invoiceNumber
            }

            // $payment->store_id = $store_id;
            // $payment->v_id = $v_id;
            // //$payment->t_order_id = $t_order_id;
            // $payment->order_id = $order_id;
            // $payment->user_id = $user_id;
            // $payment->pay_id = $pay_id;
            // $payment->amount = $amount;
            // $payment->method = $method;
            // $payment->invoice_id = $invoice_id;
            // $payment->status = $status;
            // $payment->payment_gateway_type = $payment_gateway_type;
            // $payment->gateway_response = json_encode($gateway_response);
            // $payment->date = date('Y-m-d');
            // $payment->time = date('h:i:s');
            // $payment->month = date('m');
            // $payment->year = date('Y');

            // $payment->save();

            $payment_save_status = true;

        } else if ($payment_gateway_type == 'EZSWYPE') {

            //$t_order_id = $request->t_order_id;
            // $pay_id = $request->pay_id; //tnx->txnId
            // $amount = $request->amount; //tnx->amount
            // $method = $request->method; //tnx->paymentMode
            // $invoice_id = $request->invoice_id; //tnx->invoiceNumber
            // $status = $request->status; // $gateway_response->status

            if ($method != 'card' && $method != 'cash') {
                $method = 'wallet';
            }

            // $date = date('Y-m-d');
            // $time = date('h:i:s');
            // $payment = new Payment;

            $gateway_response = $request->gateway_response;

            $gateway_response = json_decode($gateway_response);

            //dd($gateway_response->result);
            //var_dump($gateway_response->result->txn);

            // $payment->store_id = $store_id;
            // $payment->v_id = $v_id;
            // //$payment->t_order_id = $t_order_id;
            // $payment->order_id = $order_id;
            // $payment->user_id = $user_id;
            // $payment->pay_id = $pay_id;
            // $payment->amount = $amount;
            // $payment->method = $method;
            // $payment->invoice_id = $invoice_id;
            // $payment->status = $status;
            // $payment->payment_gateway_type = $payment_gateway_type;
            // $payment->gateway_response = json_encode($gateway_response);
            // $payment->date = date('Y-m-d');
            // $payment->time = date('h:i:s');
            // $payment->month = date('m');
            // $payment->year = date('Y');

            // $payment->save();

            $payment_save_status = true;

        } else {

            //$t_order_id = $request->t_order_id;
            // $pay_id = $request->pay_id; //tnx->txnId
            // $amount = $request->amount; //tnx->amount
            $cash_collected = $request->cash_collected;
            $cash_return = $request->cash_return;
            // $method = $request->method; //tnx->paymentMode
            // $invoice_id = $request->invoice_id; //tnx->invoiceNumber
            // $status = $request->status; // $gateway_response->status

            // $date = date('Y-m-d');
            // $time = date('h:i:s');
            // $payment = new Payment;

            // $payment->store_id = $store_id;
            // $payment->v_id = $v_id;
            // //$payment->t_order_id = $t_order_id;
            // $payment->order_id = $order_id;
            // $payment->user_id = $user_id;
            // $payment->pay_id = $pay_id;
            // $payment->amount = $amount;
            // $payment->method = $method;
            // $payment->cash_collected = $cash_collected;
            // $payment->cash_return = $cash_return;
            // $payment->invoice_id = $invoice_id;
            // $payment->status = $status;
            // $payment->payment_gateway_type = $payment_gateway_type;
            // //$payment->gateway_response = json_encode($gateway_response);
            // $payment->date = date('Y-m-d');
            // $payment->time = date('h:i:s');
            // $payment->month = date('m');
            // $payment->year = date('Y');

            // $payment->save();

            $payment_save_status = true;

        }

        // dd($razorpay_payment);
        //$razorpay_payment = (object)$razorpay_payment = ['status' => 'captured', 'method'=>'cart','invoice_id' => '', 'wallet'=> '' , 'vpa' =>''];

        $payment = new Payment;

        $payment->store_id = $store_id;
        $payment->v_id = $v_id;
        $payment->order_id = $order_id;
        $payment->user_id = $user_id;
        $payment->pay_id = $pay_id;
        $payment->amount = $amount;
        $payment->method = $method;
        $payment->cash_collected = $cash_collected;
        $payment->cash_return = $cash_return;
        $payment->payment_invoice_id = $invoice_id;
        $payment->bank = $bank;
        $payment->wallet = $wallet;
        $payment->vpa = $vpa;
        $payment->error_description = $error_description;
        $payment->status = $status;
        $payment->payment_type = $payment_type;
        $payment->payment_gateway_type = $payment_gateway_type;
        $payment->gateway_response = json_encode($gateway_response);
        $payment->date = date('Y-m-d');
        $payment->time = date('H:i:s');
        $payment->month = date('m');
        $payment->year = date('Y');

        $payment->save();

        if(!$t_order_id){
            $t_order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
            $t_order_id = $t_order_id + 1;
        }

        $vSetting = new VendorSettingController;
        $voucherSetting = $vSetting->getVoucherSetting(['v_id' => $v_id , 'trans_from' => $trans_from]);
        $voucherUsedType = null;
        if(isset($voucherSetting->status) &&  $voucherSetting->status ==1){

            $vouchers = DB::table('voucher_applied')->select('id','voucher_id','applied_amount')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('order_id', $t_order_id)->get();
            $voucherUsedType = $voucherSetting->used_type;
            foreach($vouchers as $voucher) {
                $totalVoucher = 0;
                $vou = DB::table('voucher')->select('amount')->where('id', $voucher->voucher_id)->first();
                $totalVoucher = $vou->amount;
                $previous_applied = DB::table('voucher_applied')->select('applied_amount')->where('voucher_id' , $voucher->voucher_id)->get();
                $totalAppliedAmount = $previous_applied->sum('applied_amount');

                if( $voucherUsedType == 'PARTIAL' ){
                    if( $vou->amount ==  $totalAppliedAmount ){
                        DB::table('voucher')->where('id', $voucher->voucher_id)->update(['status' => 'used']);
                    }else if($totalAppliedAmount > $vou->amount){
                        DB::table('voucher')->where('id', $voucher->voucher_id)->update(['status' => 'used']);
                    }else{
                        DB::table('voucher')->where('id', $voucher->voucher_id)->update(['status' => 'partial']);
                    }
                }else{

                    DB::table('voucher')->where('id', $voucher->voucher_id)->update(['status' => 'used']);
                }

                DB::table('voucher_applied')->where('id', $voucher->id)->update(['status' => 'APPLIED' ]);
            }
        }else{

            $vouchers = DB::table('voucher_applied')->select('voucher_id')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('order_id', $t_order_id)->get();

            foreach ($vouchers as $voucher) {
                DB::table('voucher')->where('id', $voucher->voucher_id)->update(['status' => 'used']);
            }
            
        }


        //echo $payment_type;die;

        if ($status == 'success' ) {

            /* Begin Transaction */
            DB::beginTransaction();
            try{


            $orders->update([ 'status' => 'success', 'verify_status' => '1', 'verify_status_guard' => '1' ]);
            OrderDetails::where('t_order_id', $orders->od_id)->update([ 'status' => 'success' ]);   

            // ----- Generate Invoice -----

            $zwing_invoice_id = invoice_id_generate($store_id, $user_id, $trans_from);
            $custom_invoice_id = custom_invoice_id_generate(['store_id' => $store_id, 'user_id' => $user_id, 'trans_from' => $trans_from]);
            // dd($zwing_invoice_id);
            if ($payment_type == 'full') {
                $invoice = new Invoice;

                $invoice->invoice_id = $zwing_invoice_id;
                $invoice->custom_order_id = $custom_invoice_id;
                $invoice->ref_order_id = $orders->order_id;
                $invoice->transaction_type = $orders->transaction_type;
                $invoice->v_id = $v_id;
                $invoice->store_id = $store_id;
                $invoice->user_id = $user_id;
                $invoice->subtotal = $orders->subtotal;
                $invoice->discount = $orders->discount;
                $invoice->tax = $orders->tax;
                $invoice->total = $orders->total;
                $invoice->trans_from = $trans_from;
                $invoice->vu_id = $vu_id;
                $invoice->date = date('Y-m-d');
                $invoice->time = date('H:i:s');
                $invoice->month = date('m');
                $invoice->year = date('Y');

                $invoice->save();

                $payment->update([ 'invoice_id' => $zwing_invoice_id ]);

                // Pushing order to cinepolis vista server


            } elseif ($payment_type == 'partial') {
                // For the partial 
            }

            // ------ Copy Order Details & Order Item Details to Invoice Details & Invoice Item Details ------

            $pinvoice_id = $invoice->id;

            $order_data = OrderDetails::where('t_order_id', $orders->od_id)->get()->toArray();
            // dd($orders->od_id);
            // die;

             foreach ($order_data as $value) {
                
                /*Only use for Cinepolis start*/
                if($request->v_id == 27){

                    $seatinfo = [];
                    $cinepolisOrderPush = [
                    'UserSessionId' => 'a53ac319775849499dd04fd23a6c867b',
                    'CinemaId' => 889,
                    'Concessions' => [
                    'ItemId' => $value['barcode'],
                    'Quantity' => $value['qty'],
                    ],
                    'ReturnOrder' => true,
                    'v_id' => $v_id,
                    'store_id' => $store_id,
                    'invoice_id' => $invoice->invoice_id,
                    'seat_no' => $seat_no,
                    'hall_no' => $hall_no,
                        ];

                   
                    $dataNeededToPush =  array(
                            'cinepolis' => $cinepolisOrderPush,

                    );
                    event(new OrderPush($dataNeededToPush));
                }
                /*Only use for Cinepolis start*/
 

                $value['t_order_id']    = $invoice->id; 
                $save_invoice_details   = $value;
                $invoice_details_data   = InvoiceDetails::create($save_invoice_details);
                $order_details_data     = OrderItemDetails::where('porder_id', $value['id'])->get()->toArray();


                foreach ($order_details_data as $indvalue) {
                    $save_invoice_item_details = array_add($indvalue, 'pinvoice_id', $invoice_details_data->id);
                    InvoiceItemDetails::create($save_invoice_item_details);
                }


                    /*Update Stock start*/
                        /*$barcode      =  $this->getBarcode($value['barcode'],$v_id);
                        if($barcode){
                            $barcode  = $barcode;
                        }else{
                            $barcode  = $value['barcode'];
                        }*/
                         $params = array('v_id'=>$value['v_id'],'store_id'=>$value['store_id'],'barcode'=>$value['barcode'],'qty'=>$value['qty'],'invoice_id'=>$invoice->invoice_id,'order_id'=>$invoice->ref_order_id);
                         $this->cartconfig->updateStockQty($params);

                    /*Update Stock end*/
 
            }
            ##########################
            ## Remove Cart  ##########
            ##########################

            $cart_id_list = Cart::where('order_id', $orders->o_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $user_id)->get(['cart_id']);
            CartDetails::whereIn('cart_id', $cart_id_list)->delete();
            Cart::whereIn('cart_id', $cart_id_list)->delete();
            CartOffers::whereIn('cart_id', $cart_id_list)->delete();

            $payment_method = (isset($payment->method)) ? $payment->method : '';

            $user = Auth::user();
            // Mail::to($user->email)->send(new OrderCreated($user,$ord,$carts,$payment_method,  $complete_path));


            $orderC = new OrderController;
            $order_arr = $orderC->getOrderResponse(['order' => $orders , 'v_id' => $v_id , 'trans_from' => $trans_from ]) ;
            DB::commit();
            }catch(Exception $e){
              DB::rollback();
              exit;
            }



            /*Email Functionality*/

            $current_invoice_name = 'adf.pdf';
            // if($last_invoice_name){
            //    $arr =  explode('_',$last_invoice_name);
            //    $id = $arr[2] + 1;
            //     $current_invoice_name = $date.$time.'_'.$store->mapping_store_id.'_'.$store_id.'_'.$id.'.pdf';
            // }else{
            //     $current_invoice_name = $date.$time.'_'.$store->mapping_store_id.'_'.$store_id.'_1.pdf';
            // }

            /*

            try{
                $html = $this->order_receipt($user_id , $v_id, $store_id, $invoice->invoice_id);
                $pdf = PDF::loadHTML($html);
                $path =  storage_path();
                $complete_path = $path."/app/invoices/".$current_invoice_name;
                $pdf->setWarnings(false)->save($complete_path);

                $payment_method = (isset($payment->method) )?$payment->method:'';

                $user = Auth::user();
                if($user->email != null && $user->email != ''){
                    
                    $mail_res = get_email_triggers(['v_id' => $v_id ,'store_id' => $store_id , 'email_trigger_code' => 'order_created']);

                    $to = 'sanjeev.y@gsl.in';
                    $cc = $mail_res['cc'];
                    $bcc = $mail_res['bcc'];

                    //dd($cc);
                    $mailer = Mail::to($user->email); 
                    if(count($bcc)> 0){
                        $mailer->bcc($bcc);
                    }
                    if(count($cc) > 0){
                        $mailer->bcc($cc);
                    }
                    
                    $mailer->send(new OrderCreated($user,$ord,$carts,$payment_method,  $complete_path));

                }
                
            }catch(Exception $e){
                        //Nothing doing after catching email fail
            }
            */

            $print_url  =  'https://dev.api.gozwing.com/order-receipt/'.$c_id.'/'.$v_id.'/'.$store_id.'/'.$invoice->invoice_id;
            return response()->json(['status' => 'payment_save', 'redirect_to_qr' => true, 'message' => 'Save Payment', 'data' => $payment,'order_summary' => $order_arr, 'print_url'=>$print_url], 200);
           
            // }

        } else if($status == 'failed' || $status == 'error') {

            // ----- Generate Order ID & Update Order status on orders and orders details -----

            // $new_order_id = order_id_generate($store_id, $user_id, $trans_from);
            // $custom_order_id = custom_order_id_generate(['store_id' => $store_id, 'user_id' => $user_id, 'trans_from' => $trans_from]);

            // $orders->update([ 'order_id' => $new_order_id, 'custom_order_id' => $custom_order_id, 'status' => $status ]);
            $orders->update([ 'status' => $status ]);

            OrderDetails::where('t_order_id', $orders->od_id)->update([ 'status' => $status ]);
        }
    }

    public function get_print_receipt(Request $request){
        $v_id       = $request->v_id;
        $store_id   = $request->store_id; 
        $c_id       = $request->c_id;
        $order_id   = $request->order_id;
        $product_data= [];
        $gst_list    = [];
        $final_gst   = [];
        $detatch_gst = [];
        $rounded = 0;
        $store         = Store::find($store_id);
       $order_details = Invoice::where('invoice_id', $order_id)->first();
        $cart_qty = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('qty');

        $total_amount = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
        // dd($total_amount);

        $cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->get();
        $count = 1;
        $gst_tax = 0;
        $gst_listing = [];



        foreach ($cart_product as $key => $value) {
                $tdata    = json_decode($value->tdata);
                $gst_tax += $value->tax;
                $itemname = explode(' ', $value->item_name);
                if (count($itemname) === 1) {
                    //$itemcode = $itemname[0];
                } else {
                $itemcode = $itemname[0]; 
                    unset($itemname[0]);
                    $item_name = implode(' ', $itemname);
                }

                $rate     = round($value->unit_mrp);
                $tax_type = '';
                if($tdata->tax_type == 'EXC'){
                    $tax_type = '(E)';
                    $tax_term_contion = 'Exclusive';
                }else if($tdata->tax_type == 'INC'){
                    $tax_type = '(I)';
                     $tax_term_contion = 'Inclusive';
                }

                $product_data[]  = [
                        'row'           => 1,
                        'sr_no'         => $count++,
                        'name'          => $value->item_name,
                        'qty'           => $value->qty,
                        'tax_amt'       => $value->tax,  //$value->tax.$tax_type,
                        'rate'          => "$rate",
                        'total'         => $value->total 
                            
                    ];
                $product_data[] = [
                        'row'           => 2,
                        'discount'      => $value->discount+$value->manual_discount,
                        'rsp'           => $value->unit_mrp,
                        'item_code'     => $value->barcode,
                        'sm_value'      => '3',   
                        'tax_per'       => $tdata->cgst + $tdata->sgst,
                        'total'         => $value->total,
                        'hsn'           => $tdata->hsn        
                    ];
              
               $gst_list[] = [
                    'name'              => $tdata->tax_name,
                    'wihout_tax_price'  => $tdata->taxable,
                    'tax_amount'        => $tdata->tax,
                    'cgst'              => $tdata->cgstamt,
                    'sgst'              => $tdata->sgstamt,
                    'cess'              => $tdata->cessamt
                ];
                
        }

        $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
        //dd($gst_list);
        $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
        $cgst = $sgst = $cess = 0 ;
        foreach ($gst_listing as $key => $value) {

           // dd($gst_list);
            $tax_ab = [];
            $tax_cg = [];
            $tax_sg = [];
            $tax_ces = [];

            foreach ($gst_list as $val) {

                if ($val['name'] == $value) {
                    $total_gst             += str_replace(",", '', $val['tax_amount']);
                    $taxable_amount        += str_replace(",", '', $val['wihout_tax_price']);
                    $tax_ab[]       =  str_replace(",", '', $val['wihout_tax_price']);
                    $tax_cg[]       =  str_replace(",", '', $val['cgst']);
                    $tax_sg[]       =  str_replace(",", '', $val['sgst']);
                    $tax_ces[]      =  str_replace(",", '', $val['cess']);
                    $cgst              += str_replace(",", '', $val['cgst']);
                    $sgst              += str_replace(",", '', $val['sgst']);
                    $cess              += str_replace(",", '', $val['cess']);
                    $final_gst[$value] = (object)[
                        'name'      => $value,
                        'taxable'   => array_sum($tax_ab),//$this->format_and_string($taxable_amount),
                        'cgst'      => round(array_sum($tax_cg),2),
                        'sgst'      => round(array_sum($tax_sg),2),
                        'cess'      => round(array_sum($tax_ces),2)
                    ];
                    // $total_taxable += $taxable_amount;
                   
                }
            }
         }
          $total_csgt = round($cgst,2);
            $total_sgst = round($sgst,2);
            $total_cess = round($cess,2);
        // dd($final_gst);

        foreach ($final_gst as $key => $value) {
            $detatch_gst[] = $value;
        }

        $roundoff = explode(".", $total_amount);
        $roundoffamt = 0;
        // dd($roundoff);
        if (!isset($roundoff[1])) {
            $roundoff[1] = 0;
        }
        if ($roundoff[1] >= 50) {
            $roundoffamt = $order_details->total - $total_amount;
            $roundoffamt = -$roundoffamt;
        } else if ($roundoff[1] <= 49) {
            $roundoffamt = $total_amount - $order_details->total;
            $roundoffamt = -$roundoffamt;
        }

         
        $bilLogo      = '';
        $bill_logo_id = 11;
        $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
        if($vendorImage)
        {
            $bilLogo = env('ADMIN_URL').$vendorImage->path;
        }
        $payments  = $order_details->payment_via;
        $cash_collected = 0;  
        $cash_return    = 0;
        $net_payable        = $total_amount;

        // dd($payments);

        // foreach ($payments as $payment) {
        //     if ($payment->method == 'cash') {
        //         $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
        //         $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->cash_collected-$cashReturn ];
        //     } else {
        //         $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
        //     }
        //     $cash_collected += (float) $payment->cash_collected;
        //     $cash_return += (float) $payment->cash_return;

        //     /*Voucher Start*/
        //     if($payment->method == 'voucher_credit'){
        //         $voucher[] = $payment->amount;
        //         $net_payable = $net_payable-$payment->amount;
        //     }
        // }

        $customer_paid = $cash_collected;
        $balance_refund= $cash_return;

        ########################
        ####### Print Start ####
        ########################
        //$terms_conditions =  array('(1) Exchange Within 7 days only.','(2) MRP Are Inclusive of Applicable Tax');

        $terms_conditions =  array('1. MRP Are Inclusive of Applicable Tax');

        if($order_details->transaction_type == 'return'){
             $invoice_title     = 'Credit Note';
        }else{
            if($v_id == 7){
                $invoice_title     = 'Tax invoice';
            }else{
                 $invoice_title     = 'Invoice Detail';
            }
        }
        $manufacturer_name = 'basewin';
        if($request->has('manufacturer_name') ){
           $manufacturer_name= $request->manufacturer_name;
        }
        
        $manufacturer_name =  explode('|',$manufacturer_name);
        $printParams = [];
        if(isset($manufacturer_name[1])){
            $printParams['model_no'] = $manufacturer_name[1]  ;
        }

        $printInvioce = new PrintInvoice($manufacturer_name[0], $printParams);

        $printInvioce->addLineCenter($store->name, 24, true);
        $printInvioce->addLine($store->address1, 22);
        if($store->address2){
        $printInvioce->addLine($store->address2, 22);
        }
        $printInvioce->addLine($store->location.'-'.$store->pincode.', '.$store->state, 22);
        $printInvioce->addLine('Contact No: '.$store->contact_number, 22);
        $printInvioce->addLine('E-mail: '.$store->email, 22);
        // $printInvioce->addLine('GSTIN: '.$store->gst, 22);
        if($store->cin){
            $printInvioce->addLine('CIN: '.$store->cin, 22);            
        }
        $printInvioce->addDivider('-', 20);
        $printInvioce->addLine($invoice_title  , 22,true);
        $printInvioce->addDivider('-', 20);

        $printInvioce->addLineLeft(' Invoice No : '.$order_details->invoice_id , 22,true);
        $printInvioce->addLineLeft(' Date : '.date('d-M-Y', strtotime($order_details->created_at))." at ".date('h:i:s A', strtotime($order_details->created_at)), 22);
        // $printInvioce->addLineLeft(' Cashier : '.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name , 22);
        $printInvioce->addLineLeft(' Customer Mobile : '.@$order_details->user->mobile , 22);
        
        /***************************************/
        # Customer Address When Resturant Type #
        /**************************************/

        if($store->type == 5 || $store->type == 6){
         if(isset($order_details->user->address->address1)){
            $printInvioce->addLineLeft(' Customer Address : '.$order_details->user->address->address1 , 22);
            if($order_details->user->address->address2){
             $printInvioce->addLineLeft(' '.$order_details->user->address->address2 , 22);
            }
            if($order_details->user->address->city){
             $printInvioce->addLineLeft($order_details->user->address->city.', '.$order_details->user->address->state , 22);
            }
            if($order_details->user->address->landmark){
             $printInvioce->addLineLeft('Landmark: '.$order_details->user->address->landmark , 22);
            }
         }
        }
        // Cinepolis Audi and seat numbers
        if($order_details->user->hall_no && $order_details->user->seat_no){ 
            $printInvioce->tableStructure([' Audi No : '.$order_details->user->hall_no,' Seat No : '.$order_details->user->seat_no], [10, 12], 22,true);
        }
        

        $printInvioce->addDivider('-', 20);



        $printInvioce->tableStructure(['Item','Qty','Amount'], [20, 4,14], 22);
        // if($taxable_amount > 0){
        //     $printInvioce->tableStructure(['Barcode','hsn', 'Disc'], [18,10, 6], 22);
        // }else{
        //      $printInvioce->tableStructure(['Barcode','', 'Disc'], [22,2 , 10], 22);
        // }

        $printInvioce->addDivider('-', 20);

        for($i = 0; $i < count($product_data); $i++) {
            if($i % 2 == 0) {

                $printInvioce->tableStructure([
                    
                    $product_data[$i]['name'],
                    
                    $product_data[$i]['qty'],
                    
                    $product_data[$i]['total']
                    ],
                     [20, 4,14], 22);
            } else {
                // $printInvioce->tableStructure([
                //     ' '.$product_data[$i]['item_code'],
                //     $taxable_amount?$product_data[$i]['hsn']:'',
                //     $product_data[$i]['discount']
                //     ],
                //     [18,10, 6], 22);
            }
        }


      
        // $printInvioce->addLineLeft('Rupee: '.ucfirst(numberTowords(round($order_details->total))).' Only' , 22);
        
        // $printInvioce->addDivider('-', 20);
        // $printInvioce->addLineLeft('  Customer Paid: '.format_number($customer_paid), 22, true);
        // $printInvioce->addLineLeft('  Balance Refund: '.format_number($balance_refund), 22, true);
        // $printInvioce->addDivider('-', 20);

        // $printInvioce->tableStructure(['CSGT','Tax'], [10,12], 22);
        
        $printInvioce->addDivider('-', 20);
        for($i = 0; $i < count($product_data); $i++) {
            if($i % 2 == 0) {
                if($i >= 1){
                    $tax = 'CGST 2.5% TAX';
                }else{
                    $tax = 'SGST 2.5% TAX';
                }
                $printInvioce->tableStructure([
                   
                    $tax,
                    $product_data[$i]['tax_amt']
                    ],
                     [10,12], 22);
            } else {
                // $printInvioce->tableStructure([
                //     ' '.$product_data[$i]['item_code'],
                //     $taxable_amount?$product_data[$i]['hsn']:'',
                //     $product_data[$i]['discount']
                //     ],
                //     [18,10, 6], 22);
            }
        }
          $printInvioce->addDivider('-', 20);
        $printInvioce->tableStructure(['Total', $cart_qty,$total_amount], [20, 4,14], 22,true);
        // $printInvioce->addDivider('-', 20);
        /*Tax Start */
        if($taxable_amount > 0){
            
            $printInvioce->leftRightStructure('GST Summary','', 22);
            $printInvioce->addDivider('-', 20);
           
            if(!empty($detatch_gst)) {
                $printInvioce->tableStructure(['Desc', 'Taxable', 'CGST','SGST','CESS'], [8,9, 6,6,5], 22);
                //$printInvioce->tableStructure(['', 'Amt','Amt','Amt','Amt'], [8, 8, 6,6,6], 22);
           

                $printInvioce->addDivider('-', 20);
                foreach ($detatch_gst as $index => $gst) {
                    $printInvioce->tableStructure([$gst->name,
                        ' '.$gst->taxable,
                        $gst->cgst,
                        $gst->sgst,
                        $gst->cess],
                        [8,9, 6,6,5], 22);
                }
                $printInvioce->addDivider('-', 20);
                $printInvioce->tableStructure(['Total',
                    format_number($taxable_amount),
                    format_number($total_csgt),
                    format_number($total_sgst),
                    format_number($total_cess)], [8, 9, 6,6,5], 22, true);
                $printInvioce->addDivider('-', 20);
            }
        }

        // $total_discount = $order_details->discount+$order_details->manual_discount;
        // $printInvioce->leftRightStructure('Saving', $total_discount, 22);
        // $printInvioce->leftRightStructure('Total QTY', $cart_qty, 22);
        // $printInvioce->leftRightStructure('Total Sale', $total_amount, 22);
       
       
        // Closes Left & Start center
        // $printInvioce->addDivider('-', 20);
        // if(!empty($mop_list)) {
        //     foreach ($mop_list as $mop) {
        //         $printInvioce->leftRightStructure($mop['mode'], $mop['amount'], 22);
        //     }
        //     $printInvioce->addDivider('-', 20);
        // }
        // $printInvioce->leftRightStructure('Net Payable', $net_payable, 22);
        // $printInvioce->addDivider('-', 20);
        // $printInvioce->addLineLeft(' Terms and Conditions', 22, true);
        // $printInvioce->addDivider('-', 20);
        // foreach ($terms_conditions as $term) {
        //     $printInvioce->addLineLeft($term, 20);
        // }


        /*KOT For Resturent*/
        if($store->type == 5 || $store->type == 6){
            
        $printInvioce->addDivider(' ', 20);
        $printInvioce->addDivider(' ', 20);

        $printInvioce->addDivider(' ', 20);
        $printInvioce->addDivider('*', 20);

        $printInvioce->addDivider(' ', 20);
        $printInvioce->addDivider(' ', 20);

        $printInvioce->addDivider('-', 20);


        $printInvioce->tableStructure(['#', 'Item', 'Rate','Qty','Tax','Amount'], [3, 10, 6,7,5,5], 22);
        if($taxable_amount > 0){
            $printInvioce->tableStructure(['Barcode','hsn', 'Disc'], [18,10, 6], 22);
        }else{
             $printInvioce->tableStructure(['Barcode','', 'Disc'], [22,2 , 10], 22);
        }

        $printInvioce->addDivider('-', 20);

        for($i = 0; $i < count($product_data); $i++) {
            if($i % 2 == 0) {

                $printInvioce->tableStructure([
                    $product_data[$i]['sr_no'],
                    $product_data[$i]['name'],
                    ' '.$product_data[$i]['rate'],
                    $product_data[$i]['qty'],
                    $product_data[$i]['tax_amt'],
                    $product_data[$i]['total']
                    ],
                     [3, 10, 6,4,7,6], 22);
            } else {
                $printInvioce->tableStructure([
                    ' '.$product_data[$i]['item_code'],
                    $taxable_amount?$product_data[$i]['hsn']:'',
                    $product_data[$i]['discount']
                    ],
                    [18,10, 6], 22);
            }
        }
        }

        $response = ['status' => 'success', 
            'print_data' =>($printInvioce->getFinalResult())];

        if($request->has('response_format') && $request->response_format == 'ARRAY'){
            return $response;
        }

        return response()->json($response, 200);
    }

    public function get_print_receipt_invoice(Request $request){
        $v_id       = $request->v_id;
        $store_id   = $request->store_id; 
        $c_id       = $request->c_id;
        $order_id   = $request->order_id;
        $product_data= [];
        $gst_list    = [];
        $final_gst   = [];
        $detatch_gst = [];
        $rounded = 0;
        $store         = Store::find($store_id);
        $order_details = Order::where('order_id', $order_id)->first();
        $cart_qty = OrderDetails::where('t_order_id', $order_details->od_id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('qty');

        $total_amount = OrderDetails::where('t_order_id', $order_details->od_id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
        // dd($total_amount);

        $cart_product = OrderDetails::where('t_order_id', $order_details->od_id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->get();
        $count = 1;
        $gst_tax = 0;
        $gst_listing = [];



        foreach ($cart_product as $key => $value) {
                $tdata    = json_decode($value->tdata);
                $gst_tax += $value->tax;
                $itemname = explode(' ', $value->item_name);
                if (count($itemname) === 1) {
                    //$itemcode = $itemname[0];
                } else {
                $itemcode = $itemname[0]; 
                    unset($itemname[0]);
                    $item_name = implode(' ', $itemname);
                }

                $rate     = round($value->unit_mrp);
                $tax_type = '';
                if($tdata->tax_type == 'EXC'){
                    $tax_type = '(E)';
                    $tax_term_contion = 'Exclusive';
                }else if($tdata->tax_type == 'INC'){
                    $tax_type = '(I)';
                     $tax_term_contion = 'Inclusive';
                }

                $product_data[]  = [
                        'row'           => 1,
                        'sr_no'         => $count++,
                        'name'          => $value->item_name,
                        'qty'           => $value->qty,
                        'tax_amt'       => $value->tax,  //$value->tax.$tax_type,
                        'rate'          => "$rate",
                        'total'         => $value->total 
                            
                    ];
                $product_data[] = [
                        'row'           => 2,
                        'discount'      => $value->discount+$value->manual_discount,
                        'rsp'           => $value->unit_mrp,
                        'item_code'     => $value->barcode,
                        'sm_value'      => '3',   
                        'tax_per'       => $tdata->cgst + $tdata->sgst,
                        'total'         => $value->total,
                        'hsn'           => $tdata->hsn        
                    ];
              
               $gst_list[] = [
                    'name'              => $tdata->tax_name,
                    'wihout_tax_price'  => $tdata->taxable,
                    'tax_amount'        => $tdata->tax,
                    'cgst'              => $tdata->cgstamt,
                    'sgst'              => $tdata->sgstamt,
                    'cess'              => $tdata->cessamt
                ];
                
        }

        $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
        //dd($gst_list);
        $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
        $cgst = $sgst = $cess = 0 ;
        foreach ($gst_listing as $key => $value) {

           // dd($gst_list);
            $tax_ab = [];
            $tax_cg = [];
            $tax_sg = [];
            $tax_ces = [];

            foreach ($gst_list as $val) {

                if ($val['name'] == $value) {
                    $total_gst             += str_replace(",", '', $val['tax_amount']);
                    $taxable_amount        += str_replace(",", '', $val['wihout_tax_price']);
                    $tax_ab[]       =  str_replace(",", '', $val['wihout_tax_price']);
                    $tax_cg[]       =  str_replace(",", '', $val['cgst']);
                    $tax_sg[]       =  str_replace(",", '', $val['sgst']);
                    $tax_ces[]      =  str_replace(",", '', $val['cess']);
                    $cgst              += str_replace(",", '', $val['cgst']);
                    $sgst              += str_replace(",", '', $val['sgst']);
                    $cess              += str_replace(",", '', $val['cess']);
                    $final_gst[$value] = (object)[
                        'name'      => $value,
                        'taxable'   => array_sum($tax_ab),//$this->format_and_string($taxable_amount),
                        'cgst'      => round(array_sum($tax_cg),2),
                        'sgst'      => round(array_sum($tax_sg),2),
                        'cess'      => round(array_sum($tax_ces),2)
                    ];
                    // $total_taxable += $taxable_amount;
                   
                }
            }
         }
          $total_csgt = round($cgst,2);
            $total_sgst = round($sgst,2);
            $total_cess = round($cess,2);
        // dd($final_gst);

        foreach ($final_gst as $key => $value) {
            $detatch_gst[] = $value;
        }

        $roundoff = explode(".", $total_amount);
        $roundoffamt = 0;
        // dd($roundoff);
        if (!isset($roundoff[1])) {
            $roundoff[1] = 0;
        }
        if ($roundoff[1] >= 50) {
            $roundoffamt = $order_details->total - $total_amount;
            $roundoffamt = -$roundoffamt;
        } else if ($roundoff[1] <= 49) {
            $roundoffamt = $total_amount - $order_details->total;
            $roundoffamt = -$roundoffamt;
        }

         
        $bilLogo      = '';
        $bill_logo_id = 11;
        $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
        if($vendorImage)
        {
            $bilLogo = env('ADMIN_URL').$vendorImage->path;
        }
        $payments  = $order_details->payment_via;
        $cash_collected = 0;  
        $cash_return    = 0;
        $net_payable        = $total_amount;

        // dd($payments);

        // foreach ($payments as $payment) {
        //     if ($payment->method == 'cash') {
        //         $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
        //         $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->cash_collected-$cashReturn ];
        //     } else {
        //         $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
        //     }
        //     $cash_collected += (float) $payment->cash_collected;
        //     $cash_return += (float) $payment->cash_return;

        //     /*Voucher Start*/
        //     if($payment->method == 'voucher_credit'){
        //         $voucher[] = $payment->amount;
        //         $net_payable = $net_payable-$payment->amount;
        //     }
        // }

        $customer_paid = $cash_collected;
        $balance_refund= $cash_return;

        ########################
        ####### Print Start ####
        ########################
        //$terms_conditions =  array('(1) Exchange Within 7 days only.','(2) MRP Are Inclusive of Applicable Tax');

        $terms_conditions =  array('1. MRP Are Inclusive of Applicable Tax');

        if($order_details->transaction_type == 'return'){
             $invoice_title     = 'Credit Note';
        }else{
            
                 $invoice_title     = 'KOT';
        }
        $manufacturer_name = 'basewin';
        if($request->has('manufacturer_name') ){
           $manufacturer_name= $request->manufacturer_name;
        }
        $printInvioce = new PrintInvoice($manufacturer_name);

        $printInvioce->addLineCenter($store->name, 24, true);
        $printInvioce->addLine($store->address1, 22);
        if($store->address2){
        $printInvioce->addLine($store->address2, 22);
        }
        $printInvioce->addLine($store->location.'-'.$store->pincode.', '.$store->state, 22);
        $printInvioce->addLine('Contact No: '.$store->contact_number, 22);
        $printInvioce->addLine('E-mail: '.$store->email, 22);
        // $printInvioce->addLine('GSTIN: '.$store->gst, 22);
        if($store->cin){
            $printInvioce->addLine('CIN: '.$store->cin, 22);            
        }
        $printInvioce->addDivider('-', 20);
        $printInvioce->addLine($invoice_title  , 22,true);
        $printInvioce->addDivider('-', 20);

        $printInvioce->addLineLeft(' Invoice No : '.$order_details->order_id , 22,true);
        $printInvioce->addLineLeft(' Date : '.date('d-M-Y', strtotime($order_details->created_at))." at ".date('h:i:s A', strtotime($order_details->created_at)), 22);
        // $printInvioce->addLineLeft(' Cashier : '.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name , 22);
        $printInvioce->addLineLeft(' Customer Mobile : '.@$order_details->user->mobile , 22);
        
        /***************************************/
        # Customer Address When Resturant Type #
        /**************************************/

        if($store->type == 5 || $store->type == 6){
         if(isset($order_details->user->address->address1)){
            $printInvioce->addLineLeft(' Customer Address : '.$order_details->user->address->address1 , 22);
            if($order_details->user->address->address2){
             $printInvioce->addLineLeft(' '.$order_details->user->address->address2 , 22);
            }
            if($order_details->user->address->city){
             $printInvioce->addLineLeft($order_details->user->address->city.', '.$order_details->user->address->state , 22);
            }
            if($order_details->user->address->landmark){
             $printInvioce->addLineLeft('Landmark: '.$order_details->user->address->landmark , 22);
            }
         }
        }

        // Cinepolis Audi and seat numbers
        if($order_details->user->hall_no && $order_details->user->seat_no){ 
            $printInvioce->tableStructure([' Audi No : '.$order_details->user->hall_no,' Seat No : '.$order_details->user->seat_no], [10, 12], 22,true);
        }
        

        $printInvioce->addDivider('-', 20);



        $printInvioce->tableStructure(['Item','Qty'], [20, 14], 22);
        // if($taxable_amount > 0){
        //     $printInvioce->tableStructure(['Barcode','hsn', 'Disc'], [18,10, 6], 22);
        // }else{
        //      $printInvioce->tableStructure(['Barcode','', 'Disc'], [22,2 , 10], 22);
        // }

        $printInvioce->addDivider('-', 20);

        for($i = 0; $i < count($product_data); $i++) {
            if($i % 2 == 0) {

                $printInvioce->tableStructure([
                    
                    $product_data[$i]['name'],
                    
                    $product_data[$i]['qty']
                    
                    ],
                     [20, 14], 22);
            } else {
                // $printInvioce->tableStructure([
                //     ' '.$product_data[$i]['item_code'],
                //     $taxable_amount?$product_data[$i]['hsn']:'',
                //     $product_data[$i]['discount']
                //     ],
                //     [18,10, 6], 22);
            }
        }


      
        // $printInvioce->addLineLeft('Rupee: '.ucfirst(numberTowords(round($order_details->total))).' Only' , 22);
        
        // $printInvioce->addDivider('-', 20);
        // $printInvioce->addLineLeft('  Customer Paid: '.format_number($customer_paid), 22, true);
        // $printInvioce->addLineLeft('  Balance Refund: '.format_number($balance_refund), 22, true);
        // $printInvioce->addDivider('-', 20);

        // $printInvioce->tableStructure(['CSGT','Tax'], [10,12], 22);
        
      
          $printInvioce->addDivider('-', 20);
        $printInvioce->tableStructure(['Total', $cart_qty,$total_amount], [20, 4,14], 22,true);
        
        /*KOT For Resturent*/
        if($store->type == 5 || $store->type == 6){
            
        $printInvioce->addDivider(' ', 20);
        $printInvioce->addDivider(' ', 20);

        $printInvioce->addDivider(' ', 20);
        $printInvioce->addDivider('*', 20);

        $printInvioce->addDivider(' ', 20);
        $printInvioce->addDivider(' ', 20);

        $printInvioce->addDivider('-', 20);


        $printInvioce->tableStructure(['#', 'Item', 'Rate','Qty','Tax','Amount'], [3, 10, 6,7,5,5], 22);
        if($taxable_amount > 0){
            $printInvioce->tableStructure(['Barcode','hsn', 'Disc'], [18,10, 6], 22);
        }else{
             $printInvioce->tableStructure(['Barcode','', 'Disc'], [22,2 , 10], 22);
        }

        $printInvioce->addDivider('-', 20);

        for($i = 0; $i < count($product_data); $i++) {
            if($i % 2 == 0) {

                $printInvioce->tableStructure([
                    $product_data[$i]['sr_no'],
                    $product_data[$i]['name'],
                    ' '.$product_data[$i]['rate'],
                    $product_data[$i]['qty'],
                    $product_data[$i]['tax_amt'],
                    $product_data[$i]['total']
                    ],
                     [3, 10, 6,4,7,6], 22);
            } else {
                $printInvioce->tableStructure([
                    ' '.$product_data[$i]['item_code'],
                    $taxable_amount?$product_data[$i]['hsn']:'',
                    $product_data[$i]['discount']
                    ],
                    [18,10, 6], 22);
            }
        }
        }
        return response()->json(['type' => 'print_kds_kot', 
            'data' =>($printInvioce->getFinalResult())], 200);
    
    }

    public function get_print_receipt_order(Request $request){

        $v_id       = $request->v_id;
        $store_id   = $request->store_id; 
        $c_id       = $request->c_id;
        $order_id   = $request->order_id;
        $product_data= [];
        $gst_list    = [];
        $final_gst   = [];
        $detatch_gst = [];
        $rounded = 0;
        $store         = Store::find($store_id);
        $order_details = Invoice::where('invoice_id', $order_id)->first();

        $cart_qty = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('qty');

        $total_amount = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
        // dd($total_amount);

        $cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->get();
        $count = 1;
        $gst_tax = 0;
        $gst_listing = [];



        foreach ($cart_product as $key => $value) {

                $tdata    = json_decode($value->tdata);
                $gst_tax += $value->tax;
                $itemname = explode(' ', $value->item_name);
                if (count($itemname) === 1) {
                    //$itemcode = $itemname[0];
                } else {
                $itemcode = $itemname[0]; 
                    unset($itemname[0]);
                    $item_name = implode(' ', $itemname);
                }

                $rate     = round($value->unit_mrp);
                $tax_type = '';
                if($tdata->tax_type == 'EXC'){
                    $tax_type = '(E)';
                    $tax_term_contion = 'Exclusive';
                }else if($tdata->tax_type == 'INC'){
                    $tax_type = '(I)';
                     $tax_term_contion = 'Inclusive';
                }

                $product_data[]  = [
                        'row'           => 1,
                        'sr_no'         => $count++,
                        'name'          => $value->item_name,
                        'qty'           => $value->qty,
                        'tax_amt'       => $value->tax,  //$value->tax.$tax_type,
                        'rate'          => "$rate",
                        'total'         => $value->total 
                            
                    ];
                $product_data[] = [
                        'row'           => 2,
                        'discount'      => $value->discount+$value->manual_discount,
                        'rsp'           => $value->unit_mrp,
                        'item_code'     => $value->barcode,
                        'sm_value'      => '3',   
                        'tax_per'       => $tdata->cgst + $tdata->sgst,
                        'total'         => $value->total,
                        'hsn'           => $tdata->hsn        
                    ];
              
               $gst_list[] = [
                    'name'              => $tdata->tax_name,
                    'wihout_tax_price'  => $tdata->taxable,
                    'tax_amount'        => $tdata->tax,
                    'cgst'              => $tdata->cgstamt,
                    'sgst'              => $tdata->sgstamt,
                    'cess'              => $tdata->cessamt
                ];
                
        }


        $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
        //dd($gst_list);
        $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
        $cgst = $sgst = $cess = 0 ;
        foreach ($gst_listing as $key => $value) {

           // dd($gst_list);
            $tax_ab = [];
            $tax_cg = [];
            $tax_sg = [];
            $tax_ces = [];

            foreach ($gst_list as $val) {

                if ($val['name'] == $value) {
                    $total_gst             += str_replace(",", '', $val['tax_amount']);
                    $taxable_amount        += str_replace(",", '', $val['wihout_tax_price']);
                    $tax_ab[]       =  str_replace(",", '', $val['wihout_tax_price']);
                    $tax_cg[]       =  str_replace(",", '', $val['cgst']);
                    $tax_sg[]       =  str_replace(",", '', $val['sgst']);
                    $tax_ces[]      =  str_replace(",", '', $val['cess']);
                    $cgst              += str_replace(",", '', $val['cgst']);
                    $sgst              += str_replace(",", '', $val['sgst']);
                    $cess              += str_replace(",", '', $val['cess']);
                    $final_gst[$value] = (object)[
                        'name'      => $value,
                        'taxable'   => array_sum($tax_ab),//$this->format_and_string($taxable_amount),
                        'cgst'      => round(array_sum($tax_cg),2),
                        'sgst'      => round(array_sum($tax_sg),2),
                        'cess'      => round(array_sum($tax_ces),2)
                    ];
                    // $total_taxable += $taxable_amount;
                   
                }
            }
         }
          $total_csgt = round($cgst,2);
            $total_sgst = round($sgst,2);
            $total_cess = round($cess,2);
        // dd($final_gst);

        foreach ($final_gst as $key => $value) {
            $detatch_gst[] = $value;
        }

        $roundoff = explode(".", $total_amount);
        $roundoffamt = 0;
        // dd($roundoff);
        if (!isset($roundoff[1])) {
            $roundoff[1] = 0;
        }
        if ($roundoff[1] >= 50) {
            $roundoffamt = $order_details->total - $total_amount;
            $roundoffamt = -$roundoffamt;
        } else if ($roundoff[1] <= 49) {
            $roundoffamt = $total_amount - $order_details->total;
            $roundoffamt = -$roundoffamt;
        }

         
        $bilLogo      = '';
        $bill_logo_id = 11;
        $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
        if($vendorImage)
        {
            $bilLogo = env('ADMIN_URL').$vendorImage->path;
        }

        $payments  = $order_details->payvia;
        $cash_collected = 0;  
        $cash_return    = 0;
        $net_payable        = $total_amount;

        //dd($payments);

        foreach ($payments as $payment) {
            if ($payment->method == 'cash') {
                $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
                $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->cash_collected-$cashReturn ];
            } else {
                $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
            }
            $cash_collected += (float) $payment->cash_collected;
            $cash_return += (float) $payment->cash_return;

            /*Voucher Start*/
            if($payment->method == 'voucher_credit'){
                $voucher[] = $payment->amount;
                $net_payable = $net_payable-$payment->amount;
            }
        }

        $customer_paid = $cash_collected;
        $balance_refund= $cash_return;

        ########################
        ####### Print Start ####
        ########################
        //$terms_conditions =  array('(1) Exchange Within 7 days only.','(2) MRP Are Inclusive of Applicable Tax');

        $terms_conditions =  array('1. MRP Are Inclusive of Applicable Tax');

        if($order_details->transaction_type == 'return'){
             $invoice_title     = 'Credit Note';
        }else{
            if($v_id == 7){
                $invoice_title     = 'Tax invoice';
            }else{
                 $invoice_title     = 'Invoice Detail';
            }
        }
        $manufacturer_name = 'basewin';
        if($request->has('manufacturer_name') ){
           $manufacturer_name= $request->manufacturer_name;
        }
        $printInvioce = new PrintInvoice($manufacturer_name);

        $printInvioce->addLineCenter($store->name, 24, true);
        $printInvioce->addLine($store->address1, 22);
        if($store->address2){
        $printInvioce->addLine($store->address2, 22);
        }
        $printInvioce->addLine($store->location.'-'.$store->pincode.', '.$store->state, 22);
        $printInvioce->addLine('Contact No: '.$store->contact_number, 22);
        $printInvioce->addLine('E-mail: '.$store->email, 22);
        if($store->gst){
        $printInvioce->addLine('GSTIN: '.$store->gst, 22);
    }
        if($store->cin){
            $printInvioce->addLine('CIN: '.$store->cin, 22);            
        }
        $printInvioce->addDivider('-', 20);
        $printInvioce->addLine($invoice_title  , 22,true);
        $printInvioce->addDivider('-', 20);

        $printInvioce->addLineLeft(' Invoice No : '.$order_details->invoice_id , 22,true);
        $printInvioce->addLineLeft(' Date : '.date('d-M-Y', strtotime($order_details->created_at))." at ".date('h:i:s A', strtotime($order_details->created_at)), 22);
        $printInvioce->addLineLeft(' Cashier : '.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name , 22);
        $printInvioce->addLineLeft(' Customer Mobile : '.@$order_details->user->mobile , 22);
        
        /***************************************/
        # Customer Address When Resturant Type #
        /**************************************/

        if($store->type == 5 || $store->type == 6){
         if(isset($order_details->user->address->address1)){
            $printInvioce->addLineLeft(' Customer Address : '.$order_details->user->address->address1 , 22);
            if($order_details->user->address->address2){
             $printInvioce->addLineLeft(' '.$order_details->user->address->address2 , 22);
            }
            if($order_details->user->address->city){
             $printInvioce->addLineLeft($order_details->user->address->city.', '.$order_details->user->address->state , 22);
            }
            if($order_details->user->address->landmark){
             $printInvioce->addLineLeft('Landmark: '.$order_details->user->address->landmark , 22);
            }
         }
        }


        if($order_details->user->hall_no){
            $printInvioce->addLineLeft(' Audi No : '.$order_details->user->hall_no , 22);
        }
        if($order_details->user->seat_no){
            $printInvioce->addLineLeft(' Table No : '.$order_details->user->seat_no , 22);
        }

        $printInvioce->addDivider('-', 20);



        $printInvioce->tableStructure(['#', 'Item', 'Rate','Qty','Tax','Amount'], [3, 10, 6,7,5,5], 22);
        if($taxable_amount > 0){
            $printInvioce->tableStructure(['Barcode','hsn', 'Disc'], [18,10, 6], 22);
        }else{
             $printInvioce->tableStructure(['Barcode','', 'Disc'], [22,2 , 10], 22);
        }

        $printInvioce->addDivider('-', 20);

        for($i = 0; $i < count($product_data); $i++) {
            if($i % 2 == 0) {

                $printInvioce->tableStructure([
                    $product_data[$i]['sr_no'],
                    $product_data[$i]['name'],
                    ' '.$product_data[$i]['rate'],
                    $product_data[$i]['qty'],
                    $product_data[$i]['tax_amt'],
                    $product_data[$i]['total']
                    ],
                     [3, 10, 6,4,7,6], 22);
            } else {
                $printInvioce->tableStructure([
                    ' '.$product_data[$i]['item_code'],
                    $taxable_amount?$product_data[$i]['hsn']:'',
                    $product_data[$i]['discount']
                    ],
                    [18,10, 6], 22);
            }
        }
        $printInvioce->addDivider('-', 20);
        $printInvioce->tableStructure(['Total', $cart_qty,$total_amount], [20, 4,14], 22,true);
        $printInvioce->addDivider('-', 20);
        $printInvioce->addLineLeft('Rupee: '.ucfirst(numberTowords(round($order_details->total))).' Only' , 22);
        
        $printInvioce->addDivider('-', 20);
        $printInvioce->addLineLeft('  Customer Paid: '.format_number($customer_paid), 22, true);
        $printInvioce->addLineLeft('  Balance Refund: '.format_number($balance_refund), 22, true);
        $printInvioce->addDivider('-', 20);
        /*Tax Start */
        if($taxable_amount > 0){
            
            $printInvioce->leftRightStructure('GST Summary','', 22);
            $printInvioce->addDivider('-', 20);
           
            if(!empty($detatch_gst)) {
                $printInvioce->tableStructure(['Desc', 'Taxable', 'CGST','SGST','CESS'], [8,9, 6,6,5], 22);
                //$printInvioce->tableStructure(['', 'Amt','Amt','Amt','Amt'], [8, 8, 6,6,6], 22);
           

                $printInvioce->addDivider('-', 20);
                foreach ($detatch_gst as $index => $gst) {
                    $printInvioce->tableStructure([$gst->name,
                        ' '.$gst->taxable,
                        $gst->cgst,
                        $gst->sgst,
                        $gst->cess],
                        [8,9, 6,6,5], 22);
                }
                $printInvioce->addDivider('-', 20);
                $printInvioce->tableStructure(['Total',
                    format_number($taxable_amount),
                    format_number($total_csgt),
                    format_number($total_sgst),
                    format_number($total_cess)], [8, 9, 6,6,5], 22, true);
                $printInvioce->addDivider('-', 20);
            }
        }
        $total_discount = $order_details->discount+$order_details->manual_discount;
        $printInvioce->leftRightStructure('Saving', $total_discount, 22);
        $printInvioce->leftRightStructure('Total QTY', $cart_qty, 22);
        $printInvioce->leftRightStructure('Total Sale', $total_amount, 22);
       
       
        // Closes Left & Start center
        $printInvioce->addDivider('-', 20);
        if(!empty($mop_list)) {
            foreach ($mop_list as $mop) {
                $printInvioce->leftRightStructure($mop['mode'], $mop['amount'], 22);
            }
            $printInvioce->addDivider('-', 20);
        }
        $printInvioce->leftRightStructure('Net Payable', $net_payable, 22);
        $printInvioce->addDivider('-', 20);
        $printInvioce->addLineLeft(' Terms and Conditions', 22, true);
        $printInvioce->addDivider('-', 20);
        foreach ($terms_conditions as $term) {
            $printInvioce->addLineLeft($term, 20);
        }


        /*KOT For Resturent*/
        if($store->type == 5 || $store->type == 6){
            
        $printInvioce->addDivider(' ', 20);
        $printInvioce->addDivider(' ', 20);

        $printInvioce->addDivider(' ', 20);
        $printInvioce->addDivider('*', 20);

        $printInvioce->addDivider(' ', 20);
        $printInvioce->addDivider(' ', 20);

        $printInvioce->addDivider('-', 20);


        $printInvioce->tableStructure(['#', 'Item', 'Rate','Qty','Tax','Amount'], [3, 10, 6,7,5,5], 22);
        if($taxable_amount > 0){
            $printInvioce->tableStructure(['Barcode','hsn', 'Disc'], [18,10, 6], 22);
        }else{
             $printInvioce->tableStructure(['Barcode','', 'Disc'], [22,2 , 10], 22);
        }

        $printInvioce->addDivider('-', 20);

        for($i = 0; $i < count($product_data); $i++) {
            if($i % 2 == 0) {

                $printInvioce->tableStructure([
                    $product_data[$i]['sr_no'],
                    $product_data[$i]['name'],
                    ' '.$product_data[$i]['rate'],
                    $product_data[$i]['qty'],
                    $product_data[$i]['tax_amt'],
                    $product_data[$i]['total']
                    ],
                     [3, 10, 6,4,7,6], 22);
            } else {
                $printInvioce->tableStructure([
                    ' '.$product_data[$i]['item_code'],
                    $taxable_amount?$product_data[$i]['hsn']:'',
                    $product_data[$i]['discount']
                    ],
                    [18,10, 6], 22);
            }
        }
        }
        return response()->json(['status' => 'success', 
            'print_data' =>($printInvioce->getFinalResult())], 200);
    }

    public function get_html_structure($str)
    {
        $string = str_replace('<center>','<tbodyclass="center">',$str);
        $string = str_replace('<left>','<tbodyclass="left">',$string);
        $string = str_replace('<right>','<tbodyclass="right">',$string);
        $string = str_replace('</center>','</tbody>',$string);
        $string = str_replace('</left>','</tbody>',$string);
        $string = str_replace('</right>','</tbody>',$string);
        $string = str_replace('normal>','span>',$string);
        $string = str_replace('bold>','b>',$string);
        $string = str_replace('<size','<tr><td',$string);
        $string = str_replace('size>','td></tr>',$string);
        $string = str_replace('text','pre',$string);
        $string = str_replace('td=30','tdstyle="font-size:90px"',$string);
        $string = str_replace('td=24','tdstyle="font-size:16px"',$string);
        $string = str_replace('td=22','tdstyle="font-size:15px"',$string);
        $string = str_replace('td=20','tdstyle="font-size:14px"',$string);
        $string = str_replace('\n','&nbsp;',$string);
        // removing end tag
        $string = preg_replace('/<end[^>]*>.*?/i', '', $string);
        // $DOM = new \DOMDocument;
        // $DOM->loadHtml($string);

        $string = urlencode($string);
        // $string = str_replace('+','&nbsp;&nbsp;');
        $string = str_replace('tds','td s',$string);
        $string = str_replace('tbodyc','tbody c',$string);

         $renderPrintPreview = '<!DOCTYPE html><html><head>
                                <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                                <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
                                <title>Cool</title>
                                <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
                                <style type="text/css">
                                * {  font-family: Lato; }
                                div { margin: 30px 0; border: 1px solid #f5f5f5; }
                                table {  width: 350px;  }
                                .center { text-align: center;  }
                                .left { text-align: left; }
                                .left pre { padding:0 30px !important; }
                                .right { text-align: right;  }
                                .right pre { padding:0 30px !important; }
                                td { padding: 0 5px; }
                                tbody { display: table !important; width: inherit; word-wrap: break-word; }
                                pre {
                                    white-space: pre-wrap;       /* Since CSS 2.1 */
                                    white-space: -moz-pre-wrap;  /* Mozilla, since 1999 */
                                    white-space: -pre-wrap;      /* Opera 4-6 */
                                    white-space: -o-pre-wrap;    /* Opera 7 */
                                    word-wrap: break-word;       /* Internet Explorer 5.5+ */
                                    overflow: hidden;
                                    background-color: #fff;
                                    padding: 0;
                                    border: none;
                                    font-size: 12.5px !important;
                                }
                                </style>
                        </head>
                            
                        <body>
                            <center>
                            
                                <div style="width: 350px;">
                                <table>
                            '
                                .urldecode($string).
                            '</table>
                            </div>
                            
                                </center>
                        </body>
                            </html>';
        return $renderPrintPreview;
    }
    
}

 ?>