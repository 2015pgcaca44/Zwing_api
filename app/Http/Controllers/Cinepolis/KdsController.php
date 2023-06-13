<?php

namespace App\Http\Controllers\Cinepolis;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SmsController;

use App\Mail\OrderCreated;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade as PDF;

use App\Invoice;
use App\Order;
use App\OrderDetails;
use App\Payment;
use App\InvoiceDetails;
use App\OrderExtra;
use App\Kds;
use App\KdsDetails;
use App\SmsLog;
use App\Model\Items\VendorSkuDetails;
use App\Model\Items\VendorSkuDetailBarcode;
use Auth;
use DB;

class KdsController extends Controller
{
    // public function index(Request $request)
    // {
    //  $order_items = [['name'=>'Caramel Popcorn','qty'=>1],['name'=>'Cheese Popcorn','qty'=>2]];
    //  return response()->json(['status'=>'pending','order_no' => 'Z2027001J7D00002', 'audi' => '03', 'seat' => 'k15', 'name' => 'Harmeet singh','order_items' => $order_items], 200);
    // }

    public function index1(Request $request)
    {
        $order_items=[];
        $items = KdsDetails::select('kds.id','kds.user_id','kds.kds_status','kds.invoice_id','kds_details.qty','kds_details.item_name')
            ->where('kds.kds_status','pending')
            ->where('kds.v_id',27)
            ->where('kds.store_id',40)
            ->join('kds','kds.id','=','kds_details.t_order_id')
            ->get()
            ->toArray();
        foreach ($items as $key => $kds) {
            $cust = DB::table('customer_auth')->select('first_name')->where('c_id', $kds['user_id'])->first();
            $order = DB::table('order_extra')->select('seat_no','hall_no')->where('order_id', $kds['invoice_id'])->first();

            $data = ['qty'=>$kds['qty'],'item'=>$kds['item_name']];
            array_push($order_items, $data);
            
        }
        
        return response()->json(['status'=>$kds['kds_status'],'invoice_id'=>$kds['invoice_id'],'user_name'=>$cust->first_name,'seat_no'=>$order->seat_no,'hall_no'=>$order->hall_no,'order_items'=>$order_items], 200);
    }

    public function index(Request $request)
    {
        $order_items=[];
        $items = KdsDetails::select('kds.id','kds.user_id','kds.kds_status','kds.invoice_id','kds_details.qty','kds_details.item_name')
            ->where('kds.kds_status','pending')
            ->where('kds.v_id',$request->v_id)
            ->where('kds.store_id',$request->store_id)
            ->join('kds','kds.id','=','kds_details.t_order_id')
            ->groupBy('kds.invoice_id')
            ->get();

        foreach ($items as $key => $kds) {
                $cust = DB::table('customer_auth')->select('first_name')->where('c_id', $kds['user_id'])->first();
                $order = DB::table('order_extra')->select('seat_no','hall_no')->where('order_id', $kds['invoice_id'])->first();
          $kdsDetails = KdsDetails::select('kds_details.qty','kds_details.item_name')
          ->where('kds_details.t_order_id',$kds['id'])->get()->toArray();
          $ndata = ['status'=>$kds['kds_status'],'invoice_id'=>$kds['invoice_id'],'user_name'=>$cust->first_name,'seat_no'=>$order->seat_no,'hall_no'=>$order->hall_no,'orders'=>$kdsDetails];
          array_push($order_items, $ndata);
        }
        
        return response()->json($order_items, 200);
    }

    public function sendSms(Request $request)
    {
        // $params = [];
        // $sms = new SmsController;
        // $params = $request->all();
        // $otp = '1234';
        // $params['message'] = "Welcome to ZWING your otp is ".$otp;
        // $sms->send_sms($params);

       $this->orderEmailRecipt($request);
    }

    public function orderEmailRecipt(Request $request){
        $v_id        = $request->v_id;
        $store_id    = $request->store_id;
        $invoice_id  = $request->order_id;
        $user_id     = $request->c_id;
        $email_id    = $request->email;
        $return      = array();
        // $invoiceExist= Invoice::where('invoice_id',$invoice_id)->count();
        $invoiceExist= Order::where('order_id',$invoice_id)->count();
        if($invoiceExist > 0){
            $emailParams = array('v_id'=>$v_id,'store_id'=>$store_id,'order_id'=>$invoice_id,'user_id'=>$user_id,'email_id'=>$email_id);
            if($this->orderEmail($emailParams)){
                $return = array('status'=>'email_send','message'=>'Invoice Send successfully');
            }else{
                $return = array('status'=>'fail','message'=>'Email Send failed.Please Try Again');
            }
        }else{
            $return = array('status'=>'fail','message'=>'Invoice Not Found');
        }
         return response()->json($return);
    }//End of orderEmailRecipt

    public function orderEmail($parms){
        $v_id        = $parms['v_id'];
        $store_id    = $parms['store_id'];
        $user_id     = $parms['user_id'];
        $invoice_id  = $parms['order_id'];
        $email_id    = $parms['email_id'];
        $date        = date('Y-m-d');
        $time        = date('h:i:s');
        $time        = strtotime($time); 
        // $invoice     = Invoice::where('invoice_id',$invoice_id)->with(['payments','details'])->first();
        $order     = Order::where('order_id',$invoice_id)->with(['payments','details'])->first();
        $payment     = $order->payments;

            // dd($order);
         
        $last_order_name = $order->invoice_name;
        if($last_order_name){
        $arr =  explode('_',$last_order_name);
        $id = $arr[2] + 1;
        $current_invoice_name = $date.'_'.$time.'_'.$store_id.'_'.$id.'.pdf';
        }else{
        $current_invoice_name = $date.'_'.$time.'_'.$store_id.'_1.pdf';
        }
        $bilLogo      = '';
        $bill_logo_id = 5;
        // $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
        // if($vendorImage)
        // {
        //     $bilLogo = env('ADMIN_URL').$vendorImage->path;
        // }
        try{
            // $user = Auth::user();
            // dd($user);
            //if($user->email != null && $user->email != ''){
            if($email_id != null && $email_id != ''){
                $html          = $this->order_receipt($user_id , $v_id, $store_id, $invoice_id);
                $pdf           = PDF::loadHTML($html);
                $path          =  storage_path();
                $complete_path = $path."/app/invoices/".$current_invoice_name;
                $pdf->setWarnings(false)->save($complete_path);
                $payment_method = $payment[0]->method;
                $to     = $email_id;      //$mail_res['to'];
                $cc     = [];//$mail_res['cc'];
                $bcc    = [];//$mail_res['bcc'];
                $user  = $email_id;
                $mailer = Mail::to($user); 
                if(count($bcc)> 0){
                    $mailer->bcc($bcc);
                }
                if(count($cc) > 0){
                    $mailer->bcc($cc);
                }
                $mailer->send(new OrderCreated($user,$order,$order->details,$payment_method,$complete_path,$bilLogo));

        }

        }catch(Exception $e){

            print_r($e);
                //Nothing doing after catching email fail
        }
    }//End of OrderEmail

    public function order_receipt($c_id,$v_id , $store_id, $order_id){

        $request = new \Illuminate\Http\Request();
        $request->merge([
            'v_id' => $v_id,
            'c_id' => $c_id,
            'store_id' => $store_id,
            'order_id' => $order_id
        ]);
        $htmlController = new CartController;
        $htmlData = $htmlController->get_print_receipt_invoice($request);
        $html = $htmlData->getContent();
        $html_obj_data = json_decode($html);
        if($html_obj_data->type == 'print_kds_kot')
        {
            return $htmlController->get_html_structure($html_obj_data->data);
        }

        $stores = Store::where('v_id', $v_id)->where('store_id', $store_id)->first();
        $order  = Invoice::where('invoice_id', $order_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->first();
      
        $return_sign = '';
        // if($order->transaction_type == 'return'){
        //     $return_sign = '-';
        // }
        
        $carts = InvoiceDetails::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('t_order_id', $order->id)->get();

        $user = User::select('first_name','last_name', 'mobile','seat_no','hall_no')->where('c_id',$c_id)->first();
       
        $store_db_name = $stores->store_db_name;
        $total         = 0.00;
        $total_qty     = 0;
        $item_discount = 0.00;
        $counter       = 0;
        $tax_details   = [];
        $tax_details_data = [];
        $cart_item_text   ='';
        $tax_item_text    = '';
        $param            = [];
        $params           = [];
        $tax_category_arr = [ 'A','B', 'C','D' ,'E','F' ,'G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V'];
        $tax_code_inc = 0;
        $cart_tax_code = [];

        foreach ($carts as $key => $cart) {

            $counter++;
            $total += $cart->total;
            $item_discount += $cart->discount;
            $total_qty += $cart->qty;
            $tax_category = '';
           
            $cart_tax_code_msg = '';

            $loopQty = $cart->qty;
            while($loopQty > 0){
               $param[] = $cart->total / $cart->qty; 
               $params[] = ['item_id' => $cart->item_id , 'price' => $cart->total / $cart->qty ];
               $loopQty--;
            }
            
            if($order->transaction_type == 'sales'){
                //$res = DB::table('cart_offers')->where('cart_id',$cart->cart_id)->first();
                $offer_data = json_decode($cart->pdata, true);



            }else if($order->transaction_type == 'return'){

                $offer_data = json_decode($cart->pdata, true);
            }
            
            $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $barcode)->first();
            $item_master = null;
            if($bar){
                $item_master = VendorSkuDetails::where(['id'=> $bar->vendor_sku_detail_id,'v_id'=>$v_id])->first();
                $item_master->barcode = $bar->barcode;
            }
            if(!$item_master){
                $item_master = VendorSkuDetails::where(['sku'=> $cart->barcode,'v_id'=>$v_id])->first();
                $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('vendor_sku_detail_id', $item_master->vendor_sku_detail_id)->first();
                $item_master->barcode = $bar->barcode;
            }
           
            $hsn_code = '';
            /*if(isset($offer_data['hsn_code'])){
                $hsn_code = $offer_data['hsn_code'];
            }*/
            if(isset($item_master->hsn_code) && $item_master->hsn_code != ''){
                $hsn_code = $item_master->hsn_code;
            }
            foreach ($offer_data['pdata'] as $key => $value) {
                $tax_details_data[$cart->item_id] = ['tax' =>  $value['tax'] , 'total' => $value['ex_price'] ];

                /*foreach($value['tax'] as $nkey => $tax){
                    if(isset($tax_details[$tax['tax_code']])){
                        $tax_details[$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
                        $tax_details[$tax['tax_code']]['tax'] += $tax['tax'] ;
                    }else{
                        $tax_details[$tax['tax_code']] = $tax;
                        
                    }
                    
                }*/

                if(empty($value['tax']) ){

                    if(isset($tax_details[00][00])){
                        $cart_tax_code_msg .= $cart_tax_code[00][00];
                        $cart_tax_code_msg .= $cart_tax_code[00][01];
                    }else{

                        $tax_details[00][00] = [ "tax_category" => "0",
                          "tax_desc" => "CGST_00_RC",
                          "tax_code" => "0",
                          "tax_rate" => "0",
                          "taxable_factor" => "0",
                          "taxable_amount" => $cart->total,
                          "tax" => 0.00 ] ;

                        $cart_tax_code_msg .= $tax_category_arr[$tax_code_inc];
                        $cart_tax_code[00][00] = $tax_category_arr[$tax_code_inc];
                        $tax_code_inc++;

                        $tax_details[00][01] = [ "tax_category" => "0",
                          "tax_desc" => "SGST_00_RC",
                          "tax_code" => "0",
                          "tax_rate" => "0",
                          "taxable_factor" => "0",
                          "taxable_amount" => $cart->total,
                          "tax" => 0.00 ] ;
                        $cart_tax_code_msg .= $tax_category_arr[$tax_code_inc];
                        $cart_tax_code[00][01] = $tax_category_arr[$tax_code_inc];
                        $tax_code_inc++;
                    }

                }else{
                    
                    foreach($value['tax'] as $nkey => $tax){
                        $tax_category = $tax['tax_category'];
                        if(isset($tax_details[$tax_category][$tax['tax_code']])){
                            $tax_details[$tax_category][$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
                            $tax_details[$tax_category][$tax['tax_code']]['tax'] += $tax['tax'] ;
                            $cart_tax_code_msg .= $cart_tax_code[$tax_category][$tax['tax_code']];
                        }else{
                            $tax_details[$tax_category][$tax['tax_code']] = $tax;
                            $cart_tax_code_msg .= $tax_category_arr[$tax_code_inc];
                            $cart_tax_code[$tax_category][$tax['tax_code']] = $tax_category_arr[$tax_code_inc];
                            $tax_code_inc++;
                            
                        }
                        
                    }
                }
                break;
            }

            //$cart_item_arr[] = ['hsn_code' => $hsn_code , 'item_name' => $cart->item_name , 'unit_mrp' => $cart->unit_mrp, 'qty' => $cart->qty , 'discount' => $cart->discount , 'total' => $cart->total , 'tax_category' => $tax_category ]; 
            
            /*Adding seat number and hall number into view invoice if vendor is kind of cinema*/
            // if($v_id == 27){
            //     $seatHallno = '<p style="margin: 5px 0;">Seat number : '.$user->seat_no.'</p>
            //                 <p style="margin: 5px 0;">Hall number : '.$user->hall_no.'</p>';
            // }else{
            //     $seatHallno = '';
            // }

           $cart_item_text .=
             '<tr class="td-center">
                <td colspan="4" style="text-align:left">'.$counter.' '.substr($cart->item_name, 0,20).'</td>
              
            </tr>
            <tr class="td-center">
                <td style="padding-left:20px;text-align:left">'.$cart->qty.'</td>
                <td> '.format_number($cart->unit_mrp).'</td>
                <td>'.format_number($cart->discount / $cart->qty).'</td>
                <td>'.$return_sign.$cart->total.'</td>
            </tr>';

        }
        
        if( $order->transaction_type == 'return'){
           $cart_item_text .=
             '<tr class="td-center">
                <td colspan="3" style="text-align:left">&nbsp;&nbsp;&nbsp; Orig. Receipt: '.$order->ref_order_id.'</td>
                <td></td>
    
            </tr>';   
        }
        //dd($tax_details);
        $transaction_type = $order->transaction_type;
        $employee_discount_text = '';
        $employee_details = '';
        if($order->employee_discount > 0.00){
            $total = $total - $order->employee_discount;
            $employee_discount_text .=
            '<tr>
                <td colspan="3">Employee Discount</td> 
                <td> -'.format_number($order->employee_discount).'</td>
            </tr>';

            $emp_d = DB::table($v_id.'_employee_details')->where('employee_id', $order->employee_id)->first();
            $employee_details .=
            '<div style="text-align:left;line-height: 0.4;padding-top:10px">
                <p>EMPLOYEE NAME : '.$emp_d->first_name.' '.$emp_d->last_name.'</p>
                <p>COMPANY NAME : '.$emp_d->company_name.'</p>
                <p>ID : '.$order->employee_id.'</p>
                <p>AVAILABLE AMOUNT : '.$order->employee_available_discount.' </p>
            </div>';
        }

        $bill_buster_discount_text = '';
        if($order->bill_buster_discount > 0){
            $total = $total - $order->bill_buster_discount;
            $bill_buster_discount_text .=
            '<tr>
                <td colspan="3">Bill Buster</td> 
                <td> -'.format_number($order->bill_buster_discount).'</td>
            </tr>';

            //Recalcualting taxes when bill buster is applied
            $promo_c = new PromotionController(['store_db_name' => $store_db_name]);
            $tax_details =[];
            $ratio_val = $promo_c->get_offer_amount_by_ratio($param, $order->bill_buster_discount);
            $ratio_total = array_sum($ratio_val);

            $discount = 0;
            $total_discount = 0;
            //dd($param);
            foreach($params as $key => $par){
                $discount = round( ($ratio_val[$key]/$ratio_total) * $order->bill_buster_discount , 2);
                $params[$key]['discount'] =  $discount;
                $total_discount += $discount;
            }
            //dd($params);
            //echo $total_discount;exit;
            //Thid code is added because facing issue when rounding of discount value
            if($total_discount > $order->bill_buster_discount){
                $total_diff = $total_discount - $order->bill_buster_discount;
                foreach($params as $key => $par){
                    if($total_diff > 0.00){
                        $params[$key]['discount'] -= 0.01;
                        $total_diff -= 0.01;
                    }else{
                        break;
                    }
                }
            }else if($total_discount < $order->bill_buster_discount){
                $total_diff =  $order->bill_buster_discount - $total_discount;
                foreach($params as $key => $par){
                    if($total_diff > 0.00){
                        $params[$key]['discount'] += 0.01;
                        $total_diff -= 0.01;
                    }else{
                        break;
                    }
                }
            }
            //dd($params);
            foreach($params as $key => $para){
                $discount = $para['discount'];  
                $item_id = $para['item_id'] ;
                // $tax_details_data[$key]
                foreach($tax_details_data[$item_id]['tax'] as $nkey => $tax){
                    $tax_category = $tax['tax_category'];
                    $taxable_total = $para['price'] - $discount;
                    $tax['taxable_amount'] = round( $taxable_total , 2 );
                    $tax['tax'] =  round( ($tax['taxable_amount'] * $tax['tax_rate']) /100 , 2 );
                    //$tax_total += $tax['tax'];
                    if(isset($tax_details[$tax_category][$tax['tax_code']])){
                        $tax_details[$tax_category][$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
                        $tax_details[$tax_category][$tax['tax_code']]['tax'] += $tax['tax'] ;
                    }else{
                        
                        $tax_details[$tax_category][$tax['tax_code']] = $tax;
                    }

                }
            }

        }

        //dd($tax_details_data);

        $discount_text = '';
        if(($item_discount + $order->bill_buster_discount) > 0){
           $discount_text = '<p>***TOTAL SAVING : Rs. '.format_number($item_discount+ $order->bill_buster_discount).' *** </p>';
        }

        $tax_counter =0;
        $total_tax = 0;
        //dd($tax_details);
        foreach($tax_details as $tax_category){
            foreach($tax_category as $tax){
                
                $total_tax += $tax['tax'];
                $tax_item_text .=
                 '<tr >
                    <td>'.$tax_category_arr[$tax_counter].'  '.substr($tax['tax_desc'],0,-2).' ('.$tax['tax_rate'].'%) '.'</td>
                    <td>'.format_number($tax['taxable_amount']).'</td>
                    <td>'.format_number($tax['tax']).'</td>
                </tr>';
                $tax_counter++;
            }
        }

        //$rounded =  round($total);
        $rounded =  $total;
        $rounded_off =  $rounded - $total;
        $transaction_type_msg = '';

        $paymentMethod = Payment::where('v_id', $order->v_id)->where('store_id',$order->store_id)->where('order_id',$order_id)->get()->pluck('method')->all() ;
        //dd($paymentMethod);
        $total_tax = 0;
        $total_inc_tax = $total_tax + $total;
        if(in_array('cash',$paymentMethod)){
            $rounded = round($total_inc_tax);
            $rounded_off = $rounded - $total_inc_tax;
            $zwing_online = (string)$rounded;
        }else{
            $rounded = $total_inc_tax;
            $rounded_off = '0';
        }

        if($order->transaction_type == 'sales')
        {

            $payments = Payment::where('v_id',$v_id)->where('store_id',$store_id)->where('user_id',$c_id)->where('order_id', $order_id)->get();
            if($payments){

                foreach($payments as $payment){
                    if($payment->method != 'voucher_credit'){
                        $transaction_type_msg .= '<tr>
                        <td colspan="3">&nbsp;&nbsp; Zwing Online</td> 
                        <td>'.format_number($payment->amount).'</td>
                        </tr>';
                    }else{

                        $transaction_type_msg .= '<tr>
                        <td colspan="3">&nbsp;&nbsp; Credit Note </td> 
                        <td>'.format_number($payment->amount).'</td>
                        </tr>';
                    }
                }

                /*
                foreach($payments as $payment){
                    if($payment->method == 'voucher_credit'){
                        $vouchers = DB::table('voucher_applied as va')
                                        ->join('voucher as v', 'v.id' , 'va.voucher_id')
                                        ->select('v.voucher_no', 'v.amount')
                                        ->where('va.v_id' , $v_id)->where('va.store_id' ,$store_id)
                                        ->where('va.user_id' , $c_id)->where('va.order_id' , $order_id)->get();
                        $voucher_total = 0;
                        foreach($vouchers as $voucher){
                            $voucher_total += $voucher->amount;
                            $voucher_applied_list[] = [ 'voucher_code' =>$voucher->voucher_no , 'voucher_amount' => format_number($voucher->amount) ] ;
                        }

                        if($voucher_total > $total){
                            
                            $lapse_voucher_amount = $voucher_total - $total;
                            $bill_voucher_amount =  $total ;

                        }else{
                            $bill_voucher_amount =  $voucher_total ;
                        }

                       

                        $transaction_type_msg .= '<tr>
                        <td colspan="3">&nbsp;&nbsp; Credit Note </td> 
                        <td>'.format_number($payment->amount).'</td>
                        </tr>';

                    }else{
                        $zwing_online = format_number($payment->amount);

                        $transaction_type_msg .= '<tr>
                        <td colspan="3">&nbsp;&nbsp; Zwing Online</td> 
                        <td>'.format_number($voucher_total).'</td>
                        </tr>';
                    }
                }*/
                

            }else{
                return response()->json([ 'status'=>'fail', 'message'=> 'Payment is not processed' ], 200);
            }

        }else{
            $voucher = DB::table('voucher')->where('ref_id', $order->order_id)->where('user_id',$order->user_id)->first();
            if($voucher){

            
                $transaction_type_msg .= '<tr>
                        <td colspan="3">&nbsp;&nbsp; Store credit</td> 
                        <td> '.$return_sign.format_number($rounded).'</td>
                    </tr>
                    <tr>
                    <td></td>
                    <td colspan="3">Store Credit #: '.$voucher->voucher_no.'<td>
                    </tr>';
            }

        }
        $bill_logo_id = 5;
        $bilLogo = '';
        $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
        if($vendorImage)
        {
            $bilLogo = env('ADMIN_URL').$vendorImage->path;
        }    
        //dd($order);
        
        
        //dd($tax_details);
        $html = 
        '<!DOCTYPE html>
        <html>
            <head>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            </head>
            <title></title>
            <style type="text/css">
            .container {
                max-width : 400px;
                margin:auto;
                margin : auto;
               #font-family: Arial, Helvetica, sans-serif;
                font-family: courier, sans-serif;
                font-size: 14px;
            }
            .clearfix {
                clear: both;
            }

            body {
                background-color:#ffff;

            }

            table {
                width: 100%;
                font-size: 14px;
            }
            .td-center td 
            {
                text-align: center;
            }
            .invoice-address p {
                line-height: 0.6;
            }
            hr {
                border-top:1px dashed #000;
                border-bottom: none;
                
            }
            </style>
            <body>
                <div class="container">
                <div class="logo">
                  <center><img   src="'.$bilLogo.'" ></center>
                </div>
                <center>
                    <h2 style="margin-bottom:5px;">'.$stores->name.'</h2>

                    <div class="invoice-contact">
                        <p style="margin: 5px 0;">'.$stores->address1.'</p>
                        <p style="margin: 5px 0;">'.$stores->address2.'</p>
                        <p style="margin: 5px 0;">'.$stores->city.' - '.$stores->pincode.'</p>
                        <p style="margin: 5px 0;">'.$stores->location.'</p>
                        <p style="margin: 5px 0;">Contact No: '.$stores->contact_number.'</p>
                        <p style="margin: 5px 0;">Email: '.$stores->email.'</p>
                    </div>

                     
                    <hr/>
                    <div class="invoice-address">
                        <p style="margin: 5px 0;">GSTIN - '.$stores->gst.'</P>
                        <p style="margin: 5px 0;">TIN - '.$stores->tin.'</P>
                        <p style="margin: 5px 0;">Helpline - '.$stores->helpline.'</P>
                        <p style="margin: 5px 0;">Store Timing - '.$stores->opening_time.' To '.$stores->closing_time.'</P>
                        <p style="margin: 5px 0;">EMAIL - '.$stores->email.'</P>

                        
                    </div>
                    <hr/>
                    <div style="text-align:left;margin-top:10px">
                        <p style="margin: 5px 0;">Name : '.$user->first_name.' '.$user->last_name.'</p>
                        <p style="margin: 5px 0;">Mobile : '.$user->mobile.'</p>
                        '.@$seatHallno.'
                    </div>

                    <hr/>
                    <table>
                    
                    <tr class="td-center">
                        <td>ITEM</td>
                        <td>Rate</td>
                        <td>Disc</td>
                        <td>Amount TC</td>
                    </tr>
                    <tr>
                        <td>/QTY</td>
                        <td>(Rs./UNIT)</td>
                        <td>(Rs./UNIT)</td>
                        <td> </td>
                    </tr>
                    </table>
                    <hr>
                    <table>
                    <tr class="td-center" style="line-height: 0;">
                        <td height="2">&nbsp;&nbsp;&nbsp;&nbsp;</td>
                        <td height="2">&nbsp;</td>
                        <td height="2">&nbsp;&nbsp;&nbsp;&nbsp;</td>
                        <td height="2">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                    </tr>

                   '.$cart_item_text.'
                    <tr>
                        <td colspan="4">&nbsp;</td>
                        
                    </tr>
                    '.$employee_discount_text.'
                    '.$bill_buster_discount_text.'
                    <tr>
                        <td colspan="3">Total Amount</td> 
                        <td>'.format_number($total).'</td>
                    </tr>
                    <tr><td>&nbsp;<td></tr>
                    <!--
                    <tr>
                        <td colspan="3">Tax</td> 
                        <td>'.format_number($total_tax).'</td>
                    </tr>
                    <tr><td>&nbsp;<td></tr>

                    <tr>
                        <td colspan="3">Total(Inc. Tax)</td> 
                        <td>'.format_number($total_inc_tax).'</td>
                    </tr>
                    <tr><td>&nbsp;<td></tr>
                    -->
                    <tr>
                        <td colspan="3">&nbsp;&nbsp; Total Rounded</td> 
                        <td>'.format_number($rounded).'</td>
                    </tr>
                    <tr><td>&nbsp;<td></tr>

                    <tr>
                        <td colspan="3">&nbsp;&nbsp; Rounded Off Amt</td> 
                        <td>'.format_number($rounded_off).'</td>
                    </tr>
                    <tr><td>&nbsp;<td></tr>
                    '.$transaction_type_msg.'
                    <tr><td>&nbsp;<td></tr>

                    <tr>
                        <td colspan="3">Total Tender</td> 
                        <td>'.$return_sign.format_number($rounded).'</td>
                    </tr>
                    <tr><td>&nbsp;<td></tr>

                    <tr>
                        <td colspan="3">&nbsp;&nbsp; Change Due</td> 
                        <td>0.00</td>
                    </tr>
                    <tr><td>&nbsp;<td></tr>
                    
                    <tr>
                        <td colspan="3">Total number of items/Qty</td> 
                        <td>'.$counter.'/'.$return_sign.$total_qty.'</td>
                    </tr>
                    </table>
                    '.$employee_details.'
                    '.$discount_text.'
                   <!-- <p>Tax Details</p>
                    
                    <table>
                    <tr>
                        
                        <td>Tax Desc</td>
                        <td>TAXABLE</td>
                        <td>Tax</td>
                    </tr>
                    '.$tax_item_text.'
                    <tr>
                    -->
                        <td colspan="6">&nbsp;</td>
                        
                    </tr>
                    <tr>
                        <td colspan="2">Total tax value</td> 
                        <td>'.format_number($total_tax).'</td>
                    </tr>
                </table>
                
                <div class="invoice-address">
                   
                </div>
                <hr/>
                <p>Tax Invoice/Bill Of Supply - '.strtoupper($transaction_type).'<p>
                <p>'.$order->order_id.'</p>
                <p></p>
                <hr/>
                <p>'.date('H:i:s d-M-Y', strtotime($order->created_at)).'</p>
                <p>&nbsp;</p>
                <p>&nbsp;</p>
                <div style="text-align:left">
               
                <div>
                </center>
                </div>
            </body>
        </html>';

        return $html;

    }
}
