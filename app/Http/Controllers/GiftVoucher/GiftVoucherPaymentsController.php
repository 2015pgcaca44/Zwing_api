<?php

namespace App\Http\Controllers\GiftVoucher;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use Auth;
use Event;
use App\Model\GiftVoucher\GiftVoucher;
use App\Model\GiftVoucher\GiftVoucherCategory;
use App\Model\GiftVoucher\GiftVoucherGroup;
use App\Model\GiftVoucher\GiftVoucherPacks;
use App\Model\GiftVoucher\GiftVoucherConfiguration;
use App\Model\GiftVoucher\GiftVoucherConfigPresetMapping;
use App\Model\GiftVoucher\GiftVoucherConfigPreset;
use App\Model\GiftVoucher\GiftVoucherAllocation;
use App\Model\GiftVoucher\GiftVoucherCartDetails;
use App\Model\GiftVoucher\GiftVoucherOrder;
use App\Model\GiftVoucher\GiftVoucherOrderDetails;
use App\Model\GiftVoucher\GiftVoucherPayments;
use App\Model\GiftVoucher\GiftVoucherInvoices;
use App\Model\GiftVoucher\GiftVoucherInvoiceDetails;
use App\SettlementSession;
use App\CashRegister;
use App\Store;
use Carbon\Carbon;
use App\User;
use App\Vendor\VendorRoleUserMapping;
use App\Http\Controllers\VendorSettingController;
use App\Model\GiftVoucher\GiftVoucherTransactionLogs;

class GiftVoucherPaymentsController extends Controller
{
    public function __construct()
    {
       
    }
    
    //payment save 
    public function savePayment(Request $request)
    {
        $this->validate($request, [
            'v_id'               => 'required',
            'store_id'           => 'required',
            'vu_id'              => 'required',
            'c_id'               => 'required',
            'order_id'           => 'required',
            'amount'             => 'required',
            'status'             => 'required',
            'trans_from'         => 'required',
            'trans_type'         => 'required',
            
        ]);

        $v_id               = $request->v_id;
        $store_id           = $request->store_id;
        $c_id               = $request->c_id;
        $trans_from         = $request->trans_from;
        $vu_id              = $request->vu_id;
        $order_id           = $request->order_id;
        $pay_id             = $request->pay_id;
        $amount             = $request->amount;
        $method             = $request->get('method');
        $invoice_id         = $request->invoice_id;
        $bank               = $request->bank;
        $wallet             = $request->wallet;
        $vpa                = $request->vpa;
        $error_description  = $request->error_description;
        $status             = $request->status;
        $transaction_type   = $request->trans_type;
        $cash_collected     = null;
        $cash_return        = null;
        $gateway_response   = null;
        $payment_invoice_id = null;
        $print_url          = null;
        $session_id         = 0;
        $invoice_seq       = null;
        $payment_gateway_device_type = '';
        $payment_gateway_type = $request->payment_gateway_type;
        $role = VendorRoleUserMapping::select('role_id')->where('user_id', $vu_id)->first();
        $role_id  = $role->role_id;

        $orders = GiftVoucherOrder::where('gv_order_id', $order_id)->first();

        //for terminal
        $udidtoken = '';
        if ($request->has('udidtoken')) {
            $udidtoken    = $request->udidtoken;
            $terminalInfo = CashRegister::where('udidtoken',$udidtoken)->first();
            $terminal_id=$terminalInfo->id;; 
        }else{
            $terminal_id='';
        }
        
        if($request->has('payment_gateway_device_type')){
            $payment_gateway_device_type = $request->payment_gateway_device_type;
        }
        if($request->has('gateway_response')){
            $gateway_response = $request->gateway_response;
        }
        $settlementSession = SettlementSession::select('id')->where(['v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id])->orderBy('id','desc')->first();
        if($settlementSession){
            $session_id = $settlementSession->id;
        }

        $totalPaymentAmount = format_number($orders->total);
        $payments = GiftVoucherPayments::where('v_id', $orders->v_id)->where('store_id', $orders->store_id)->where('gv_order_id', $orders->gv_order_id)->where('status', 'success')->get();
        if (format_number($amount) > $totalPaymentAmount) {
            return response()->json(['status' => 'validation', 'message' => 'Paid amount is greater than invoice total'], 200);
        }else if($totalPaymentAmount == $payments->sum('amount') && $transaction_type=='sales') {
            return response()->json(['status' => 'fail', 'message' => 'Amount already paid for this order'], 200);
            
        }else {
            
            if (format_number($totalPaymentAmount) == format_number($amount)) {
                $payment_type = 'full';
            } else {
                $payment_type = 'partial';
            }
        }

        if($request->has('invoice_seq')){
          $invoice_seq =   $request->invoice_seq;
        }
        //DB::beginTransaction();

        try {
                if($status=='success') {

                    $payment                              = new GiftVoucherPayments;
                    $payment->store_id                    = $store_id;
                    $payment->v_id                        = $v_id;
                    $payment->vu_id                       = $vu_id;
                    $payment->gv_order_id                 = $order_id;
                    $payment->gv_order_doc_no             = $orders->gv_order_doc_no;
                    $payment->customer_id                 = $c_id;
                    //$payment->pay_id                      = $pay_id;
                    $payment->amount                      = $amount;
                    $payment->method                      = $method;
                    $payment->session_id                  = $session_id;
                    $payment->terminal_id                 = $terminal_id;
                    $payment->cash_collected              = $cash_collected;
                    $payment->cash_return                 = $cash_return;
                    //$payment->payment_invoice_id = $invoice_id;
                    //$payment->bank                        = $bank;
                    //$payment->wallet                      = $wallet;
                    //$payment->vpa                         = $vpa;
                    $payment->trans_type                    = $transaction_type;
                    $payment->error_description           = $error_description;
                    $payment->status                      = $status;
                    $payment->payment_type                = $payment_type;
                    $payment->payment_gateway_type        = $payment_gateway_type;
                    $payment->payment_gateway_device_type = $payment_gateway_device_type;
                    $payment->gateway_response            = json_encode($gateway_response);
                   // $payment->ref_txn_id=$request->payment_initiate_id;
                    $payment->date                        = date('Y-m-d');
                    $payment->time                        = date('H:i:s');
                    $payment->month                       = date('m');
                    $payment->year                        = date('Y');
                    $payment->save();

                               
                    $payments=$payment->where('v_id', $orders->v_id)->where('store_id', $orders->store_id)->where('gv_order_id', $orders->gv_order_id)->where('status', 'success')->get();
                    
                    if ($totalPaymentAmount == $payments->sum('amount') ) {

                        /*Update order staus and clear cart here*/

                        $orders->update(['status' => 'success']);
                        GiftVoucherOrderDetails::where('gv_order_id',$orders->gv_order_id)->update(['status' => 'success']);
                        $where_cart=array('v_id'=>$v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'customer_id'=>$c_id);
                        GiftVoucherCartDetails::where($where_cart)->delete();

                        /*Update gift voucher status as sold and effective_date or valid_upto */

                        $where_od=array('v_id'=>$v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'customer_id'=>$c_id,'gv_order_id'=>$orders->gv_order_id,'status'=>'success');
                        //$gv_id_list = GiftVoucherOrderDetails::where($where_od)->get(['gv_id']);
                        //$effective_from=Carbon::now()->format('Y-m-d H:i:s');
                        //GiftVoucher::whereIn('gv_id', $gv_id_list)->update(['status' => 'Sold','effective_from'=>$effective_from]);
                        $voucher_data = GiftVoucherOrderDetails::where($where_od)->get(['gv_id','gv_group_id','voucher_code','gift_value','mobile']);
                        foreach ($voucher_data as $key => $value) {

                                $effective_from=Carbon::now()->format('Y-m-d H:i:s');
                                $params = array('v_id'=>$v_id,'gv_group_id'=>$value->gv_group_id);
                                $valid_upto=$this->calVoucherValidUpto($params);
                                $where_voucher=array('v_id'=>$v_id,'gv_id'=>$value->gv_id,'gv_group_id'=>$value->gv_group_id);
                                GiftVoucher::where($where_voucher)->update(['status' => 'Sold','effective_from'=>$effective_from,'valid_upto'=>$valid_upto]);
                                
                                //dd((string)$amount_trans);
                                $GiftVoucherTransactionLogs                   = new GiftVoucherTransactionLogs;
                                $GiftVoucherTransactionLogs->v_id             = $v_id;
                                $GiftVoucherTransactionLogs->store_id         = $store_id;
                                $GiftVoucherTransactionLogs->gv_group_id      = $value->gv_group_id;
                                $GiftVoucherTransactionLogs->vu_id            = $vu_id;
                                $GiftVoucherTransactionLogs->customer_id      = $c_id;
                                $GiftVoucherTransactionLogs->gv_id            = $value->gv_id;
                                //$GiftVoucherTransactionLogs->gift_value       = 0.0;
                                $GiftVoucherTransactionLogs->voucher_code     = $value->voucher_code;
                                $GiftVoucherTransactionLogs->amount           = $value->gift_value;
                                $GiftVoucherTransactionLogs->ref_order_id     = $orders->gv_order_doc_no;
                                $GiftVoucherTransactionLogs->preset_codes     = '';
                                $GiftVoucherTransactionLogs->status           = 'UNUSED';
                                $GiftVoucherTransactionLogs->type             = 'CREDIT_GV';
                                $GiftVoucherTransactionLogs->mobile           = $value->mobile;
                                $GiftVoucherTransactionLogs->save();     
                                                  
                        }
                        /*Invoice id genrate code start */

                        $stores       = Store::find($store_id);
                        $short_code   = $stores->short_code;
                        $userDetail   = User::find($c_id);

                        $inc_id  = invoice_id_generate_for_gv($store_id, $c_id, $trans_from,$invoice_seq,$udidtoken,'seq_id');
                        $zwing_invoice_id  = invoice_id_generate_for_gv($store_id, $c_id, $trans_from,$invoice_seq,$udidtoken);
                        $invoice = new GiftVoucherInvoices;
                        $invoice->invoice_id        = $zwing_invoice_id;
                        $invoice->custom_order_id   = $orders->gv_order_id;
                        $invoice->ref_order_id      = $orders->gv_order_doc_no;
                        $invoice->transaction_type  = $orders->transaction_type;
                        $invoice->store_gstin       = $orders->store_gstin;
                        $invoice->store_state_id    = $orders->store_state_id;
                        $invoice->comm_trans        = $orders->comm_trans;
                        $invoice->customer_gstin    = $orders->customer_gstin;
                        $invoice->customer_gst_state_id  = $orders->customer_gst_state_id;
                        $invoice->v_id              = $v_id;
                        $invoice->store_id          = $store_id;
                        $invoice->customer_id       = $c_id;
                        $invoice->invoice_sequence  = $inc_id;
                        $invoice->voucher_qty       = $orders->voucher_qty;
                        $invoice->subtotal          = $orders->subtotal;
                        $invoice->tax_amount        = $orders->tax_amount;
                        $invoice->total             = $orders->total;
                        $invoice->trans_from        = $trans_from;
                        $invoice->vu_id             = $vu_id;
                        $invoice->date              = date('Y-m-d');
                        $invoice->time              = date('H:i:s');
                        $invoice->month             = date('m');
                        $invoice->year              = date('Y');
                        $invoice->financial_year    = getFinancialYear();
                        $invoice->session_id        = $session_id;
                        $invoice->store_short_code  = $short_code;
                        $invoice->terminal_name     = isset($terminalInfo)?$terminalInfo->name:'';
                        $invoice->terminal_id       = isset($terminalInfo)?$terminalInfo->id:'';
                        $invoice->customer_first_name  = isset($userDetail->first_name)?$userDetail->first_name:'';
                        $invoice->customer_last_name   = isset($userDetail->last_name)?$userDetail->last_name:'';
                        $invoice->customer_number      = isset($userDetail->mobile)?$userDetail->mobile:'';
                        $invoice->customer_email       = isset($userDetail->email)?$userDetail->email:'';
                        $invoice->customer_gender      = isset($userDetail->gender)?$userDetail->gender:'';
                        $invoice->customer_address     = isset($userDetail->address)?$userDetail->address->address1:'';
                        $invoice->customer_pincode     = isset($userDetail->address)?$userDetail->address->pincode:'';
                        /*if customer phone code exists then update else manually update the default country code +91*/
                        $invoice->customer_phone_code  = isset($userDetail->customer_phone_code)?$userDetail->customer_phone_code:'+91';
                        $invoice->save();

                        $print_url  =  env('API_URL') . '/order-receipt/' . $c_id . '/' . $v_id . '/' . $store_id . '/' . $zwing_invoice_id;
                        GiftVoucherPayments::where('gv_order_id', $order_id)->update(['invoice_id' => $zwing_invoice_id]);
                        $payment = GiftVoucherPayments::where('gv_order_id', $order_id)->first();
                        $order_data = GiftVoucherOrderDetails::where('gv_order_id',$order_id)->get()->toArray();
                        $gv_group_id = GiftVoucherOrderDetails::select('gv_group_id')->where('gv_order_id',$order_id)
                                                                ->first()->gv_group_id;
                        $group_type=GiftVoucherGroup::select('value_type')->where('gv_group_id',$gv_group_id)->first()->value_type;
                        //dd($order_data);
                        foreach ($order_data as $key => $value) {
                            $save_invoice_details = array_except($value, ['gv_od_id']);
                            $save_invoice_details   = $value;
                            
                            $invoice_details_data   = GiftVoucherInvoiceDetails::create($save_invoice_details);
                            if($group_type=="Custom"){
                                GiftVoucher::where('gv_id',$value['gv_id'])->update(['gift_value' => $value['gift_value'],'sales_value'=>$value['sale_value']]);
                            }
                        }

                        /*Invoice genration end here */
                        // If trans form CLOUD TAB WEB

                        if ($request->trans_from == 'CLOUD_TAB_WEB' && !empty($invoice->id)) {

                            $request->merge([
                                'v_id' => $v_id,
                                'c_id' => $c_id,
                                'store_id' => $store_id,
                                'order_id' => $zwing_invoice_id,
                                'print_for'=>'GV',
                            ]);
                            $vendorS = new VendorSettingController;
                            $CartController = new \App\Http\Controllers\CloudPos\CartController;
                            $htmlData = $CartController->get_print_receipt($request);
                            $sParams  = ['v_id' => $v_id,'store_id'=>$store_id,'user_id'=>$c_id,'role_id'=>@$role_id,'trans_from' => $trans_from];
                            $printSetting       = $vendorS->getPrintSetting($sParams);
                            if(count($printSetting) > 0){
                              foreach($printSetting as $psetting){
                                if($psetting->name == 'bill_print'){
                                    $bill_print_type = $psetting->width;
                                }
                              }
                            }
                            if($bill_print_type == 'A4'){
                                $payment->html_data = $htmlData;
                            }else{  
                                $html = $htmlData->getContent();
                                $html_obj_data = json_decode($html);
                                if ($html_obj_data->status == 'success') {
                                    $payment->html_data =  $this->get_html_structure($html_obj_data->print_data);
                                }
                            }



                            $cust = User::where('c_id', $payment->customer_id)->first();

                            $payment->customer_name =  $cust->first_name . ' ' . $cust->last_name;
                            $payment->mobile = $cust->mobile;
                            $payment->email = $cust->email;
                        }

                        //print invoice end

                    }
                    //DB::commit();
                    $orderC = new GiftVoucherOrderController;
                    $getOrderResponse = ['order' => $orders, 'v_id' => $v_id, 'trans_from' => $trans_from];
                    $order_arr = $orderC->getGvOrderResponse($getOrderResponse);
                    
                    return response()->json([
                        'status'            => 'payment_save',
                        'redirect_to_qr'    => true,
                        'message'           => 'Save Payment',
                        'data'              => $payment,
                        'order_summary'     => $order_arr,
                        'print_url'         => $print_url
                    ], 200);

                }elseif($status == 'failed' || $status == 'error') {
                    
                    $orders->update(['status' => 'error']);
                    GiftVoucherOrderDetails::where('gv_order_id',$orders->gv_order_id)->update(['status' => 'error']);
                    return response()->json([
                                'status'            => 'fail',
                                'redirect_to_qr'    => false,
                                'message'           => 'Save Payment',
                            ], 200);

                }
                    

        } catch (Exception $e) {
          //  DB::rollback();
            exit;
        }

    }

    //calculate valid upto date for voucher
    public function calVoucherValidUpto($params){

            $v_id=$params['v_id'];
            $gv_group_id=$params['gv_group_id'];
            $group_details = GiftVoucherGroup::select('validity_in','validity_for','validity')->where('v_id',$v_id)
                                             ->where('gv_group_id',$gv_group_id)->first();
            $valid_upto=null;
            if($group_details->validity=='Limited-Time'){
                if(isset($group_details->validity_in) && isset($group_details->validity_for) && $group_details->validity_for>0){
                        $days=0;
                        if($group_details->validity_in=='Month'){
                            $days=30;
                        }elseif($group_details->validity_in=='Year'){
                            $days=365;
                        }elseif($group_details->validity_in=='Week'){
                            $days=7;
                        }elseif($group_details->validity_in=='Day'){
                            $days=1;
                        }
                        $add_days_count=($group_details->validity_for*$days)-1;
                        $valid_upto = Carbon::now()->addDays($add_days_count)->format('Y-m-d');
                        
                        
                }
            }

            return $valid_upto;


    }
    public function get_html_structure($str)
    {
            $string = string($str)->replace('<center>', '<tbodyclass="center">');
            $string = string($string)->replace('<left>', '<tbodyclass="left">');
            $string = string($string)->replace('<right>', '<tbodyclass="right">');
            $string = string($string)->replace('</center>', '</tbody>');
            $string = string($string)->replace('</left>', '</tbody>');
            $string = string($string)->replace('</right>', '</tbody>');
            $string = string($string)->replace('normal>', 'span>');
            $string = string($string)->replace('bold>', 'b>');
            $string = string($string)->replace('<size', '<tr><td');
            $string = string($string)->replace('size>', 'td></tr>');
            $string = string($string)->replace('text', 'pre');
            $string = string($string)->replace('td=30', 'tdstyle="font-size:90px"');
            $string = string($string)->replace('td=24', 'tdstyle="font-size:16px"');
            $string = string($string)->replace('td=22', 'tdstyle="font-size:15px"');
            $string = string($string)->replace('td=20', 'tdstyle="font-size:14px"');
            $string = string($string)->replace('\n', '&nbsp;');
        // $DOM = new \DOMDocument;
        // $DOM->loadHtml($string);

            $string = urlencode($string);
        // $string = string($string)->replace('+','&nbsp;&nbsp;');
            $string = string($string)->replace('tds', 'td s');
            $string = string($string)->replace('tbodyc', 'tbody c');

        // $renderPrintPreview = '<!DOCTYPE html><html><head>
        //                      <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        //                              <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
        //                              <title>Cool</title>
        //                              <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
        //                           <style type="text/css">
        //                           * {  font-family: Lato; }
        //                  div { margin: 30px 0; border: 1px solid #f5f5f5; }
        //                  table {  width: 350px;  }
        //                  .center { text-align: center;  }
        //                  .left { text-align: left; }
        //                  .left pre { padding:0 30px !important; }
        //                  .right { text-align: right;  }
        //                  .right pre { padding:0 30px !important; }
        //                  td { padding: 0 5px; }
        //                  tbody { display: table !important; width: inherit; word-wrap: break-word; }
        //                  pre {
        //                      white-space: pre-wrap;       /* Since CSS 2.1 */
        //                      white-space: -moz-pre-wrap;  /* Mozilla, since 1999 */
        //                      white-space: -pre-wrap;      /* Opera 4-6 */
        //                      white-space: -o-pre-wrap;    /* Opera 7 */
        //                      word-wrap: break-word;       /* Internet Explorer 5.5+ */
        //                      overflow: hidden;
        //                      background-color: #fff;
        //                      padding: 0;
        //                      border: none;
        //                      font-size: 12.5px !important;
        //                  }
        //                           </style>
        //                      </head>

        //                      <body>
        //                          <center>

        //                              <div style="width: 350px;">
        //                              <table>
        //                          '
        //                              .urldecode($string).
        //                          '</table>
        //                          </div>

        //                              </center>
        //                      </body>
        //                          </html>';

            $renderPrintPreview = '<center><div style="width: 350px;"><table>' . urldecode($string) . '</table></div></center>';

            return $renderPrintPreview;
        }
    
 

}
