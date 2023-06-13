<?php

namespace App\Http\Controllers\CloudPos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;
use App\User;
use App\DepRfdTrans;    
use App\Store;  
use App\Voucher; 
use App\Http\CustomClasses\PrintInvoice;
use App\Invoice;
class DebitnoteprintController extends Controller
{
	public function get_debitnote_recipt(Request $request){

        $v_id       = $request->v_id;
        $store_id   = $request->store_id; 
        $order_id   = $request->order_id;
        $product_data= [];
        $invoice_title = 'Payment Receipt';
        $cash_collected = 0;
        $cash_return = 0;

        $store         = Store::find($store_id);
        $order_details = Invoice::where('invoice_id', $order_id)->first();
        $total_amount  = $order_details->total;
        $payments     = $order_details->payvia;
        $cart_product  = Voucher::select(DB::raw("sum(cr_dr_settlement_log.applied_amount) as total_balance"),'dep_rfd_trans.doc_no','cr_dr_voucher.voucher_no','cr_dr_settlement_log.applied_amount','cr_dr_voucher.amount')->leftjoin('cr_dr_settlement_log','cr_dr_voucher.id','cr_dr_settlement_log.voucher_id')->leftJoin('dep_rfd_trans','cr_dr_voucher.ref_id','dep_rfd_trans.trans_src_ref')->where('cr_dr_voucher.v_id',$order_details->v_id)->where('cr_dr_voucher.store_id',$order_details->store_id)->where('cr_dr_voucher.user_id', $order_details->user_id)->groupBy('cr_dr_voucher.id')->get();
       	$terms_conditions =  array('1.Goods once sold will not be taken back');  
        $count = 1;
        $total_amt = 0;
        $total_balance_amt = 0;
        foreach ($cart_product as $key => $value) {
            $product_data[]  = [
                'row'           => 1,
                'sr_no'         => $count++,
                'voucher_no'	=> $value->voucher_no,
                'document_no'      => $value->doc_no,
            ];
            $product_data[] = [
                'row'         => 2,
                'amount'      => $value->amount,                       
                'balance_amt'    => $value->total_balance                    
            ];
            $total_amt += $value->amount;
            $total_balance_amt += $value->total_balance;
        }

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
        $printInvioce->addLine('GSTIN: '.$store->gst, 22);
        $printInvioce->addDivider('-', 20);
        $printInvioce->addLine($invoice_title  , 22);
        $printInvioce->addDivider('-', 20);
        $printInvioce->addLineLeft(' Document No : '.$order_details->invoice_id , 22,true);
        $printInvioce->addLineLeft(' Date : '.date('d-M-Y', strtotime($order_details->created_at))." at ".date('h:i:s A', strtotime($order_details->created_at)), 22);
        $printInvioce->addLineLeft(' Cashier : '.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name , 22);
        $printInvioce->addLineLeft(' Customer Mobile : '.@$order_details->user->mobile , 22);
        $printInvioce->addLineLeft(' Customer Name : '.@$order_details->user->first_name.' '.@$lastname , 22);
        $printInvioce->addDivider('-', 20);

        $printInvioce->tableStructure(['#', 'Document No',' Debit Note No'], [3,20,16], 22);
        $printInvioce->tableStructure(['Amount'], [34], 22);
        $printInvioce->addDivider('-', 20);
 
        for($i = 0; $i < count($product_data); $i++) {
            if($product_data[$i]['row'] == 1) {
                $printInvioce->tableStructure([
                                $product_data[$i]['sr_no'],
                                $product_data[$i]['document_no'],
                                ' '. $product_data[$i]['voucher_no']
                            ],
                            [3,20,16], 22);
            }
            if($product_data[$i]['row'] == 2)  {
                    $printInvioce->tableStructure([
		                $product_data[$i]['amount']
                    ],
                    [34], 22);
            }                  
        }
        $printInvioce->addDivider('-', 20);
        $printInvioce->tableStructure(['Total Amount', $total_amt], [20, 14], 22,true);
        $printInvioce->addDivider('-', 20);
        $printInvioce->addLineCenter('Rupees: '.ucfirst(numberTowords(round($total_amt))).' Only' , 22, true);
        $printInvioce->addDivider('-', 20);
        $printInvioce->addLineCenter('  Customer Paid: '.format_number($total_amt), 22, true);
        $printInvioce->addLineCenter('  Tender Refund: '.format_number($balance_refund), 22, true);
        $printInvioce->addDivider('-', 20);
        $printInvioce->addLineCenter('Balance as on Date', 22);

        $printInvioce->tableStructure(['#', 'Document No', 'Debit Note No'], [3,20,16], 22);
        $printInvioce->tableStructure(['Amount'], [34], 22);

        $printInvioce->addDivider('-', 20);

        for($i = 0; $i < count($product_data); $i++) {
            if($product_data[$i]['row'] == 1) {
                $printInvioce->tableStructure([
                                $product_data[$i]['sr_no'],
                                $product_data[$i]['document_no'],
                                ' '.$product_data[$i]['voucher_no'],
                            ],
                            [3,20,16], 22);
            } 
            if($product_data[$i]['row'] == 2)  {
                    $printInvioce->tableStructure([
	                    '('.$product_data[$i]['balance_amt'].')'
                    ],
                    [34], 22);
            }              
        }
        $printInvioce->addDivider('-', 20);
        $printInvioce->tableStructure(['Total Balance','('.$total_balance_amt.')'], [20, 14], 22,true);
        $printInvioce->addDivider('-', 20);
        $printInvioce->addLineLeft(' Terms & Conditions', 22, true);
        $printInvioce->addDivider('-', 20);
        foreach ($terms_conditions as $term) {
            $printInvioce->addLineLeft($term, 20);
        }

        $response = ['status' => 'success', 'print_data' =>($printInvioce->getFinalResult())];
        if($request->has('response_format') && $request->response_format == 'ARRAY'){
         return $response;
        }
        return response()->json($response, 200);   

    }

}

?>