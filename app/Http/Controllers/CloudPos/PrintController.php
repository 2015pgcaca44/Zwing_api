<?php
namespace App\Http\Controllers\CloudPos;
use App\Http\Controllers\Controller;
use App\Http\Controllers\VendorSettingController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\OrderController;
use App\Http\CustomClasses\PrintInvoice;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Mail\OrderCreated;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade as PDF;
use App\Store;
use App\Terms;
use App\State;
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
use App\Reason;
use App\Vendor\VendorRoleUserMapping;
// Vendor sku detail
use App\Model\Items\VendorSkuDetails;
use App\Model\Stock\StockCurrentStatus;
use App\Model\Stock\StockTransactions;
use App\Model\Stock\StockLogs;
use App\Model\Stock\StockPoints;
use App\CartDiscount;
use App\Organisation;
use App\EinvoiceDetails;
use App\Model\Payment\Mop;
class PrintController extends Controller
{
    /*public function __construct()
    {
        $this->middleware('auth' , ['except' => ['order_receipt','rt_log'] ]);
        $this->cartconfig  = new CartconfigController;     
    }*/
    // public function _construct(QrCode $qrCode){
    //     $this->qrCode = '';
    // }
    public function print_html_page($request){
        
            $terms_conditions =  array('');
            if($request->v_id == 39){
                return $this->print_html_page_for_agrocel($request);
            }
            if ($request->v_id == 119 || $request->v_id == 84) {
                 return $this->A5_print_html_page($request);
            }
            if($request->v_id == 149){//149
                return $this->A5_PurePlay_print_html_page($request);
            }
            if($request->v_id == 127){ //117
             return $this->print_html_page_for_localls($request);
            }
            if($request->v_id == 118){
             return $this->print_html_page_for_mapcha($request);
            }

            if($request->v_id == 16){
                return $this->biba_A4_print_html_page($request);
            }

            if($request->v_id == 17){
               return $this->biba_b2b_A4_invoice($request);
            }

            if($request->v_id == 147){
                return $this->print_html_page_for_RJ_Corp($request);
            }

            // if($request->type == 'account_deposite'){
            // }
            // if($request->v_id == 127){
            //     return $this->print_invoice_for_credit_note($request);
            // }

            // if($request->v_id == 127){
            //     return $this->print_invoice_for_Payment_Against_Debit_Note($request);
            // }

            $v_id       = $request->v_id;
            $store_id   = $request->store_id; 
            $c_id       = $request->c_id;
            $order_id   = $request->order_id;
            $product_data= [];
            $gst_list    = [];
            $final_gst   = [];
            $detatch_gst = [];
            $rounded     = 0;
            $data    = '';

            $invoice_title = 'Retail Invoice';
                $style = "<style>*{padding:0;margin:0;box-sizing:border-box;-webkit-border-vertical-spacing:0;-webkit-border-horizontal-spacing:0;font-size:14px}.print_receipt_invoice thead tr th{border-right:1px #000 solid; color: #000; border-bottom:1px #000 solid;border-top:1px #000 solid;border-top:none; padding: 5px;}.print_receipt_invoice thead tr:last-child{border-right:none}.print_receipt_invoice tbody tr td{padding: 10px 5px; border-right:1px #000 solid}.print_receipt_invoice tbody tr td pre{min-height:29px;text-align:left;white-space:normal;word-wrap:break-word; font-size: 11px;max-height: 29px;overflow:hidden;line-height: 1.5;}.print_receipt_invoice tbody tr td:last-child{border-right:none}.print_receipt_top-head tr td{padding:2px}.print_invoice_terms td table{text-align: left;}.print_invoice_last td table td{text-align: left;}.print_store_sign td:nth-child(2){text-align: right;}.print_invoice_last td table:last-child{margin-top: 40px;}.print_invoice_table_start table tbody tr td{font-size:13px;}.print_invoice_table_start table tbody tr td p{font-size:13px;}.print_invoice_terms td{ border-left: none;}</style>";


            $printArray  = array();
            $store         = Store::find($store_id);
            $order_details = Invoice::where('invoice_id', $order_id)->first();

            $einvoice = EinvoiceDetails::where('invoice_id',$order_details->invoice_id)->where('status','Success')->first();
            $qrImage = '';
            if($einvoice && !empty($einvoice->signed_qr_code)){
              
               $qrImage      = $this->generateQRCode(['content'=>$einvoice->signed_qr_code]);
                //$qrImage      = $einvoice->qrcode_image_path;
            }


            $cart_q = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','0')->where('user_id', $order_details->user_id)->sum('qty');
            $cart_qt = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','1')->where('user_id', $order_details->user_id)->count('qty');
            $cart_qty = $cart_q + $cart_qt;
            $total_amount = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
            // dd($total_amount);
            $count_cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->count();

            if($order_details->transaction_type == 'return'){
                $invoice_title     = 'Credit Note';
            }

            $startitem   = 0;
            $getItem     = 8;
            $countitem   = $count_cart_product;
            $totalpage   = ceil($count_cart_product/$getItem);
            $sr          = 1;

            for($i=0;$i < $totalpage ; $i++) {
               
             $cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->skip($startitem)->take(8)->get();

            $startitem  = $startitem+$getItem;
            $startitem  = $startitem;
             
            $customer_address = '';
            if(isset($order_details->user->address->address1)){
                $customer_address .= $order_details->user->address->address1;
            }
            if(isset($order_details->user->address->address2)){
                $customer_address .= $order_details->user->address->address2;
            }

            $count = 1;
            $gst_tax = 0;
            $gst_listing = [];
            $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
            //dd($gst_list);
            $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
            $cgst = $sgst = $cess = 0 ;
            // dd($final_gst);
 
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
            $bill_logo_id = 5;
            $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
            if($vendorImage)
            {
                $bilLogo = env('ADMIN_URL').$vendorImage->path;
            } 
            $payments  = $order_details->payvia;
            $cash_collected = 0;  
            $cash_return    = 0;
            $net_payable    = $total_amount;

            //dd($payments);
            $mop_list = [];
            foreach ($payments as $payment) {
            if ($payment->method == 'cash') {
            $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
            if($order_details->transaction_type == 'return'){
               $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
            }else{
               $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->cash_collected-$cashReturn ];
            }
            } else {
            $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
            }
            if ($payment->method == 'cash') {
            $cash_collected += (float) $payment->cash_collected;
            $cash_return += (float) $payment->cash_return;
            }
            /*Voucher Start*/
            if($payment->method == 'voucher_credit'){
            $voucher[] = $payment->amount;
            $net_payable = $net_payable-$payment->amount;
            }
            }
            $customer_paid = $cash_collected;
            $balance_refund= $cash_return;
            $total_discount = (float)$order_details->discount+(float)$order_details->manual_discount+(float)$order_details->bill_buster_discount;

            if($v_id == 10){
                $terms =  Terms::where('v_id',$v_id)->get();
                    $terms_condition = json_decode($terms);
                    foreach ($terms_condition as $value) {
                        $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                    }
                // $terms_conditions =  array(' To be exchanged, the products need to be in perfect condition and in their original packaging, along with the original invoice from the Janavi store.',' The product may be exchanged within 30 days of the purchase date.',' Products sold during promotional periods, personalized products, special orders or made to measure products cannot be returned or exchanged.',' A non-refundable credit note can be issued and redeemed at any Janavi store within India within 1 year from the date of issue.',' Any transaction of ₹2,00,000/- or more per day is prohibited in cash.',' PAN Card or Form 60 (along with a valid passport copy in case of Non-Resident person) must be provided by the customer for purchase of ₹2,00,000/- or more per transaction.');
            }else if($v_id == 30){
                $terms =  Terms::where('v_id',$v_id)->get();
                    $terms_condition = json_decode($terms);
                    foreach ($terms_condition as $value) {
                        $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                    }
                // $terms_conditions  =  array('Goods once sold cannot be taken back or exchanged under any circumstances');
            }else {
                $terms =  Terms::where('v_id',$v_id)->get();
                    $terms_condition = json_decode($terms);
                    foreach ($terms_condition as $value) {
                        $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                    }
                // $terms_conditions =  array('Any Discrepancy/Complaint regarding the goods should be notified within  1 day of the date mentioned on the invoice','Payment should be made by cheque/DD in favor of K Janavi payable at New Delhi','All Dispatches are subject to Delhi jurisdiction only' );

            }
            ########################
            ####### Print Start ####
            ########################
            
            
            //$data = '<body style="padding: 20px;">';
            
            //$data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"><table class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';
            /* $data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"> <table width="10%"><tr><td><img src="'.$bilLogo.'" alt="" height="80px"></td></tr>
           </table><table width="80%" class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';*/
        $data  .= '<table class="print_invoice_table_start" width="98%" style="outline: 1px #000 solid; margin-top: 20px; margin-bottom: 0px; margin-left: auto; margin-right: auto;">';
            $data  .= '<tr><td>
                            <table width="100%" style="padding-left: 5px; padding-right: 5px;"><tr>';
                                if($request->v_id == 127){
                                    $data .= '<td width="25%">';
                                }else{
                                    $data .= '<td width="10%">';
                                }
                                $data .= '<table ><tr><td><img src="'.$bilLogo.'" alt="" height="80px"></td>
                                </tr>
                                </table></td>';
                                if($request->v_id == 127){
                                   $data .= '<td width="50%">';
                                }else{
                                   $data .= '<td width="90%">';
                                }
                                $data .= '<table width="100%"   class="top-head" bgcolor="#fff" align="left" style=" text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;" >';
                                if($v_id == 10){
                                     $data  .=  '<tr style="font-size: 16px; padding: 5px;"><td><b style="font-size: 18px;">JANAVI INDIA</b></td></tr>';
                                }else{
                                     $data  .=  '<tr style="font-size: 16px; padding: 5px;"><td><b style="font-size: 18px;">'.$store->name.'</b></td></tr>';
                                }                         
                               
                                $data  .=  '<tr><td>'.$store->address1.'</td></tr>';
                                if($store->address2){
                                 $data  .=  '<tr><td>'.$store->address2.'</td></tr>';
                                }
                                $data  .=  '<tr><td>'.$store->location.','.$store->pincode.','.$store->state.'</td></tr>';
                                if($store->gst && $v_id != 10){
                                 $data  .=  '<tr><td>GSTIN: '.$store->gst.'</td></tr>';
                                }
                                $data  .=  '<tr><td>Tel: '.$store->contact_number.'</td></tr>';
                                $data  .=  '<tr><td>Email: '.$store->email.'</td></tr>';
            if(!empty($qrImage)){
                $data  .=  '<tr><td><img src='.$qrImage.'></td></tr>';    
            }
            $data  .=  '</table></td>';
            if($request->v_id == 127){
                $data .= '<td width="25%"></td>';
            }
            $data .= '</tr></table></td></tr>';
                $data  .= '<tr><td><table style="width: 100%; color: #fff; padding: 5px; text-align: center; border: 1px #000 solid; border-left: none; border-right: none;"><tr><td valign="top" style="line-height: 1.5;  color: #000;"><b>'.$invoice_title.'</b></td></tr></table></td></tr>';
            
            $data  .=  '<tr>
            <td>
            <table style="width: 100%; color: #fff; padding: 5px;">';
            $data  .=  '<tr>
            <td valign="top" style="line-height: 1;  color: #000; font-size: 12px;text-align:left;">Customer:
            <br>
            <b>'. @$order_details->user->first_name.''.@$order_details->user->last_name.'</b>';
            if($v_id != 10){
            $data .= '<br>'.@$order_details->user->mobile;    
            }
           /* if($order_details->invoice_id == 'Z7021003L3I00002'){
                dd($order_details->user->gstin);
            }*/


            if(!empty($order_details->user->gstin)){
            $data .= '<br>GSTIN: '.@$order_details->user->gstin;    
            }
            if(!empty($customer_address)){
             $data  .='<br>'.$customer_address;    
            }
            $data   .= '</td>';

            //<br>'.@$order_details->user->mobile.'
            $data  .= '<td valign="top" style="line-height: 1.5; color: #000; font-size: 14px;" align="right">Date : '.date('d-M-Y', strtotime($order_details->created_at)).'
            <br>Invoice No: '.$order_details->invoice_id.'</td>
            </tr></table></td></tr>';
            // $data  .= '<tr><td valign="top" style="line-height: 1.5;  color: #000">'.@$order_details->user->mobile.'</td>';
            // $data .=  '<td valign="top" style="line-height: 2.1; color: #000" align="right">Invoice No:<span style="color: #000;">'.$order_details->invoice_id.'</span></td></tr></table></td></tr>';
            /*$data  .= '<tr><td></td><td valign="top" style="line-height: 2.1; color: #000" align="right">Cashier <span style="color: #000;">'.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</span></td></tr></table></td></tr>';*/
            $data  .= '<tr><td><div  style="height: 320px; overflow: hidden; border-top: 2px #000 solid; border-bottom: 2px #000 solid; "  width="100%" ><table height="100%" width="100%" class="print_receipt_invoice" bgcolor="#fff" style="width: 100%; color: #000;">';
            $data  .= '<thead ><tr align="left">';
                $data .= '<th width="3%"  style=" font-size: 12px;" >Sr.</th>
                        <th width="10%" valign="center"  style="font-size: 12px; " >Barcode</th>
                        <th width="7%" valign="center"  style=" font-size: 12px;" >HSN Code</th>
                        <th width="40%" valign="center"  style=" font-size: 12px; " >Product Description</th>
                        <th width="5%" valign="center"  style=" font-size: 12px;" >Qty.</th>
                        <th width="5%" valign="center"  style=" font-size: 12px; " >Unit</th>
                        <th width="10%" valign="center"  style=" font-size: 12px;" >Price</th>
                        <th width="8%" valign="center"  style=" font-size: 12px; " >Discount</th>
                        <th width="5%" valign="center"  style=" font-size: 12px;" >GST %</td>
                        <th width="7%" valign="center"  style=" font-size: 12px; border-right: none;" >Net Amount</th></tr></thead><tbody>';
           
            $srp= '';
            $barcode = '';
            $hsn ='';
            $item_name ='';
            $qty  = '';
            $unit = '';
            $mrp  = '';
            $disc = '';
            $taxp = '';
            $taxb = '';

            $taxable_amount = 0;
            $total_csgt     = 0;
            $total_sgst     = 0;
            $total_cess     = 0;
            $srp            = '';
            $barcode        = '';
            $hsn            = '';
            $item_name      = '';
            $qty            = '';
            $unit           = '';
            $mrp            = '';
            $disc           = '';
            $taxp           = '';
            $taxb           = '';
            $tax_name       = '';
            $tax_cgst       = '';
            $tax_sgst       = '';
            $tax_igst       = '';  
            $taxable        = '';         
            foreach ($cart_product as $key => $value) {

                $remark = isset($value->remark)?' -'.$value->remark:'';
                $tdata    = json_decode($value->tdata);
                
                $gst_list[] = [
                    'name'              => $tdata->tax_name,
                    'wihout_tax_price'  => $tdata->taxable,
                    'tax_amount'        => $tdata->tax,
                    'cgst'              => $tdata->cgstamt,
                    'sgst'              => $tdata->sgstamt,
                    'cess'              => $tdata->cessamt,
                    'igst'              => $tdata->igstamt
                ];
                // print_r($gst_list)
                $itemLevelmanualDiscount=0;
                if($value->item_level_manual_discount!=null){
                    $iLmd = json_decode($value->item_level_manual_discount);
                    $itemLevelmanualDiscount= (float)$iLmd->discount;
                }
                $discount = $value->discount+$value->manual_discount + $value->bill_buster_discount+$itemLevelmanualDiscount;
                $netamt   =   $value->subtotal-$discount;
                $taxper   = $tdata->cgst + $tdata->sgst;
                $taxable_amount += $tdata->taxable;
                $total_csgt  += $tdata->cgstamt;
                $total_sgst  += $tdata->sgstamt;
                $total_cess  += $tdata->cessamt;
                $srp       .= '<pre>'.$sr.'</pre>';
                $barcode   .= '<pre>'.$value->barcode.'</pre>';
                $hsn       .= '<pre>'.$tdata->hsn.'</pre>';
                $item_name .= '<pre>'.$value->item_name.$remark.'</pre>';
                $qty       .= '<pre>'.$value->qty.'</pre>';
                $unit      .= '<pre>PCS</pre>';
                $mrp       .= '<pre>'.$value->unit_mrp.'</pre>';
                $disc      .= '<pre>'.$discount.'</pre>';
                $taxp      .= '<pre>'.$taxper.'</pre>';
                $taxb      .= '<pre>'.$netamt.'</pre>';
                $sr++;
            }
            $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
            $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
            $cgst = $sgst = $cess = $igst = 0 ;
            foreach ($gst_listing as $key => $value) {
                $tax_ab = [];
                $tax_cg = [];
                $tax_sg = [];
                $tax_ig = [];
                $tax_ces = [];

                foreach ($gst_list as $val) {

                    if ($val['name'] == $value) {
                        $total_gst             += str_replace(",", '', $val['tax_amount']);
                        $taxable_amount        += str_replace(",", '', $val['wihout_tax_price']);
                        $tax_ab[]       =  str_replace(",", '', $val['wihout_tax_price']);
                        $tax_cg[]       =  str_replace(",", '', $val['cgst']);
                        $tax_sg[]       =  str_replace(",", '', $val['sgst']);
                        $tax_ig[]       =  str_replace(",", '', $val['igst']);
                        $tax_ces[]      =  str_replace(",", '', $val['cess']);
                        $cgst              += str_replace(",", '', $val['cgst']);
                        $sgst              += str_replace(",", '', $val['sgst']);
                        $cess              += str_replace(",", '', $val['cess']);
                        $igst              += str_replace(",", '', @$val['igst']);
                        $final_gst[$value] = (object)[
                            'name'      => $value,
                        'taxable'   => array_sum($tax_ab),//$this->format_and_string($taxable_amount),
                        'cgst'      => round(array_sum($tax_cg),2),
                        'sgst'      => round(array_sum($tax_sg),2),
                        'igst'      => round(array_sum($tax_ig),2),
                        'cess'      => round(array_sum($tax_ces),2)
                    ];
                }
            }
        }
        $total_csgt = round($cgst,2);
        $total_sgst = round($sgst,2);
        $total_cess = round($cess,2);
        $total_igst = round($igst,2);

        foreach ($final_gst as $key => $value) {
            $tax_details = json_decode(json_encode($value),true);
            $taxable   .= '<p>'.$tax_details['taxable'].'</p>';
            $tax_name .= '<p>'.$tax_details['name'].'</p>';
            $tax_cgst .= '<p>'.$tax_details['cgst'].'</p>';
            $tax_sgst .= '<p>'.$tax_details['sgst'].'</p>';
            $tax_igst .= '<p>'.$tax_details['igst'].'</p>';
        }

            $data   .= '<tr align="left">';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$srp.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$barcode.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$hsn.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$item_name.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$qty.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$unit.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$mrp.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$disc.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$taxp.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px; border-right: none;">'.$taxb.'</td> </tr>';
            $total_csgt = round($total_csgt,2);
            $total_sgst = round($total_sgst,2);
            $total_cess = round($total_cess,2);
            $data   .= '</tbody>';
            $data .= '</table></td></tr></div>';
            $data   .= '<tr>
            <td>
            <table width="100%" style="color: #000;">
            <tr>
            <td>
            <table width="100%" style="padding: 5px;">
            <tr>
            ';

            if($totalpage-1 == $i){

            /*Calcualte complete taxable value*/
            $taxable_amount = 0;
            $total_csgt     = 0;
            $total_sgst     = 0;
            $total_cess     = 0;
            $sub_total      = 0;
            $total_igst     = 0;
            $invoiceData  = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->get();
            foreach($invoiceData as $invdata){
                $Ntdata    = json_decode($invdata->tdata);
                $itemLevelmanualDiscount=0;
                if($invdata->item_level_manual_discount!=null){
                 $iLmd = json_decode($invdata->item_level_manual_discount);
                 $itemLevelmanualDiscount = (float)$iLmd->discount;
                }
                $discount = $invdata->discount+$invdata->manual_discount + $invdata->bill_buster_discount+$itemLevelmanualDiscount;

                $taxper          = $Ntdata->cgst + $Ntdata->sgst;
                $taxable_amount += $Ntdata->taxable;
                $total_csgt     += $Ntdata->cgstamt;
                $total_sgst     += $Ntdata->sgstamt;
                $total_cess     += $Ntdata->cessamt;
                $total_igst     += $Ntdata->igstamt;
                $sub_total      += $invdata->subtotal;
            }
            /*Calcualte complete taxable value end*/


            $data .= '<td width="61%" valign="top">';
            if($v_id == 89){
                if($taxable_amount > 0){
                    if($total_igst == 0){
                    $data   .= '<table width="100%" style="padding: 5px;"><tr><td><table style="margin-bottom: 10px;"><tr style="margin-bottom: 10px;"><td style="font-size: 16px; font-weight: 600;">GST Summary</td></tr></table></td><td><table align="right"><tr><td>Total Qty.</td><td>' .$cart_qty.'</td></tr></table></td></tr><tr style="margin-top: 10px;"><td><table width="80%" style="border: 1px #c0c0c0 solid; padding: 5px 0px;"><tr><td style="padding: 0px 5px;">Description</td><td style="padding: 0px 5px;">Taxable</td><td style="padding: 0px 5px;">CGST</td><td style="padding: 0px 5px;">SGST</td></tr><tr><td style="padding: 0px 5px;">'.$tax_name.'</td><td style="padding: 0px 5px;">'.$taxable.'</td><td style="padding: 0px 5px;">'.$tax_cgst.'</td><td style="padding: 0px 5px;">'.$tax_sgst.'</td></tr><tr><td style="border-top:1px #c0c0c0 solid; padding: 5px 5px 0px;"><b>Total</b></td><td style="border-top:1px #c0c0c0 solid; padding: 5px 5px 0px;"><b>'.$taxable_amount.'</b></td><td style="border-top:1px #c0c0c0 solid; padding: 5px 5px 0px;"><b>'.$total_csgt.'</b></td><td style="border-top:1px #c0c0c0 solid; padding: 5px 5px 0px;"><b>'.$total_sgst.'</b></td></tr></table></td></tr></table></td>';               
                    }else{
                        $data   .= '<table width="100%" style="padding: 5px;"><tr><td><table style="margin-bottom: 10px;"><tr style="margin-bottom: 10px;"><td style="font-size: 16px; font-weight: 600;">GST Summary</td></tr></table></td><td><table align="right"><tr><td>Total Qty.</td><td>' .$cart_qty.'</td></tr></table></td></tr><tr style="margin-top: 10px;"><td><table width="80%" style="border: 1px #c0c0c0 solid; padding: 5px 0px;"><tr><td style="padding: 0px 5px;">Description</td><td style="padding: 0px 5px;">Taxable</td><td style="padding: 0px 5px;">IGST</td></tr><tr><td style="padding: 0px 5px;">'.$tax_name.'</td><td style="padding: 0px 5px;">'.$taxable.'</td><td style="padding: 0px 5px;">'.$tax_igst.'</td></tr><tr><td style="border-top:1px #c0c0c0 solid; padding: 5px 5px 0px;"><b>Total</b></td><td style="border-top:1px #c0c0c0 solid; padding: 5px 5px 0px;"><b>'.$taxable_amount.'</b></td><td style="border-top:1px #c0c0c0 solid; padding: 5px 5px 0px;"><b>'.$total_igst.'</b></td></tr></table></td></tr></table></td>';         
                    }
                }
                else{
                    $data   .= '<table align="right" style="padding: 5px;"><tr><td>Total Qty.</td><td>' .$cart_qty.'</td></tr></table></td>';
                }
            }else{
                $data   .= '<table align="right" style="padding: 5px;"><tr><td>Total Qty.</td><td>' .$cart_qty.'</td></tr></table></td>';
            }
            $data   .= '<td width="39%">';
            
            $sbtotal = (float)$sub_total-(float)$total_discount;
            $data   .= '<table width="100%" style="padding: 5px;"><tr>
            <td width="70%" align="right">Sub total</td>
            <td width="30%" align="right">&nbsp;'.$sub_total.'</td>
            </tr></table>';
            $data   .= '<table width="100%" style="padding: 5px;"><tr>
            <td width="70%" align="right" ><b>Discount Amount</b></td>
            <td width="30%" align="right" >&nbsp; <b>'.$total_discount.'</b></td>
            </tr></table>';
            $data   .=  '<table width="100%" style="padding: 5px;"><tr>
            <td width="70%" align="right" >Net amount</td>
            <td width="30%" align="right" >&nbsp;'.$sbtotal.'</td></tr>
            </table>';
            $data   .=  '<table width="100%" style="padding: 5px;"><tr>
            <td width="70%" align="right" >Taxable Amount</td>
            <td width="30%" align="right" >&nbsp;'.$taxable_amount.'</td></tr>
            </table>';
            $data   .=  '<table width="100%" style="padding: 5px;"><tr>
            <td width="70%" align="right" >CGST Amount</td>
            <td  width="30%" align="right" >&nbsp; '.$total_csgt.'</td></tr>
            </table>';
            $data   .=  '<table width="100%" style="padding: 5px;"><tr>
            <td width="70%" align="right" >SGST Amount</td>
            <td  width="30%" align="right" >&nbsp;'.$total_sgst.'</td></tr></table>';


            $data   .=  '</td></tr></table>';
            $data   .=  '<table width="100%"><tr><td width="40%"><table>';
           
                foreach($mop_list as $mop){
                  $data .=   '<tr><td bgcolor="#dcdcdc" align="left" style="padding: 5px;">
                                <b>Paid through '.$mop['mode'].':</b></td>
                                <td align="left" bgcolor="#dcdcdc" style="padding: 5px;"><b>'.$mop['amount'].'</b></td></tr>';
                }
            
            
            $data   .=  '</table></td></tr>';
            
            $data   .=  '<tr><td align="left">Amount: '.ucfirst(numberTowords(round($order_details->total))).'</td></tr>';
            
            if(isset($order_details->remark)){
                $data   .=  '<tr><td align="left">Remark: '.$order_details->remark.'</td></tr></table>';    
            }
            if($order_details->transaction_type == 'return'){
                $AmountTitle = 'Refunded amount';
            }else{
                $AmountTitle = 'Total Amount';
            }
            $data   .= '<table width="100%" style="padding: 5px;"><tr><td width="60%"></td><td width="40%"><table width="100%"><tr align="right"><td width="70%"><b>'.$AmountTitle.'</b></td><td width="30%"><b>'.$net_payable.'</b></td></tr></table></td></tr></table></td></tr></table></td></tr>';
            }else{
                    
                $data   .= '<td width="61%" height="205px" valign="top">';
                $data   .= '<table align="right" style="padding: 5px;"><tr><td></td><td></td></tr></table></td>';
                $data   .= '<td width="39%">';
                $data   .= '<table width="100%" style="padding: 5px;"><tr>
                <td width="70%" align="right"></td>
                <td width="30%" align="right"></td>
                </tr></table>';
                $data   .= '<table width="100%" style="padding: 5px;"><tr>
                             <td width="70%" align="right" ></td>
                             <td width="30%" align="right" ></td>
                             </tr></table>';
                $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                                <td width="70%" align="right" ></td>
                                <td width="30%" align="right" ></td></tr>
                             </table>';
                $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                                <td width="70%" align="right" ></td>
                                <td  width="30%" align="right" ></td></tr>
                              </table>';
                $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                              <td width="70%" align="right" ></td>
                              <td  width="30%" align="right" ></td></tr></table>';                              
                $data   .=  '</td></tr></table>';
                $data   .=  '<table width="100%"><tr><td width="40%"><table>';                              
                $data   .=  '</table></td></tr>';
                
                $data   .=  '<tr><td align="left"></td></tr>';
                
                if(isset($order_details->remark)){
                    $data   .=  '<tr><td align="left"></td></tr></table>';    
                }

                $data   .= '<table width="100%" style="padding: 5px;"><tr><td width="60%"></td><td width="40%"><table width="100%"><tr align="right"><td width="100%"><b>Continue..</b></td><td width="30%"></td></tr></table></td></tr></table></td></tr></table></td></tr>';

                
            }

            $data   .= '<tr class="print_invoice_terms"><td><table bgcolor="#fff" style="width: 100%; padding: 5px; color: #000; border: 1px #000 solid; border-left: none; border-right: none;">
                <tr width="100%">
                    <td style="padding-bottom: 10px;"><b>Terms and Conditions:</td >
                </tr>';
             foreach($terms_conditions as $term){
                $data .= '<tr width="100%"><td style="padding-bottom: 5px; text-decoration: dotted;">&bull;'.$term.'</td></tr>';
             }
            $data    .= '</table></td></tr>';
            
            $data    .= '<tr class="print_invoice_last"><td><table bgcolor="#fff" width="100%" style="color: #000000; padding: 5px;"><tr><td width="3%">For:</td><td colspan="1"><b>'.$store->name.'</b></td></tr></table><table width="100%" style="color: #000000; padding-top: 20px !important; padding: 5px;"><tr><td></td></tr><tr class="print_store_sign"><td width="50%">Authorised Signatory</td><td width="35%" align="right">Prepared by:</td><td align="right">&nbsp;'.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</td></tr></table></td></tr></table>';
            
            if($totalpage > 1){
                $data .= '<br><hr>';
            }
            if($v_id == 10){
                $data .= '<table width="100%" style="padding-top:5px;"><tr><td align="center"><span style="font-size:12px;">'.$store->district.' (GST number: '.$store->gst.')<br>Corporate Office: A-10, Sector 4, Noida, Uttar Pradesh 201301</span></td></tr></table>';
             // $data .= '<span style="font-size:12px">'.$store->district.' (GST number: '.$store->gst.')<br>Corporate Office: A-10, Sector 4, Noida, Uttar Pradesh 201301</span>';
            }
        }
        // dd($data);
        $return = array('status'=>'success','style'=>$style,'html'=>$data) ;
        return $return;
    
    }//End of print_html_page

    public function print_invoice_for_credit_note($request){
        $v_id       = $request->v_id;
        $store_id   = $request->store_id; 
        $c_id       = $request->c_id;
        $order_id   = $request->order_id;
        $product_data= [];
        $gst_list    = [];
        $final_gst   = [];
        $detatch_gst = [];
        $rounded     = 0;
        $data    = '';

        $invoice_title = 'Retail Invoice';
        $style = "<style>*{padding:0;margin:0;box-sizing:border-box;-webkit-border-vertical-spacing:0;-webkit-border-horizontal-spacing:0;font-size:14px}.print_receipt_invoice thead tr th{border-right:1px #000 solid; color: #000; border-bottom:1px #000 solid;border-top:1px #000 solid;border-top:none; padding: 5px;}.print_receipt_invoice thead tr:last-child{border-right:none}.print_receipt_invoice tbody tr td{padding: 10px 5px; border-right:1px #000 solid}.print_receipt_invoice tbody tr td pre{min-height:29px;text-align:left;white-space:normal;word-wrap:break-word; font-size: 11px;max-height: 29px;overflow:hidden;line-height: 1.5;}.print_receipt_invoice tbody tr td:last-child{border-right:none}.print_receipt_top-head tr td{padding:2px}.print_invoice_terms td table{text-align: left;}.print_invoice_last td table td{text-align: left;}.print_store_sign td:nth-child(2){text-align: right;}.print_invoice_last td table:last-child{margin-top: 40px;}.print_invoice_table_start table tbody tr td{font-size:13px;}.print_invoice_table_start table tbody tr td p{font-size:13px;}.print_invoice_terms td{ border-left: none;}</style>";

            $printArray  = array();
            $store         = Store::find($store_id);
            $order_details = Invoice::where('invoice_id', $order_id)->first();

            $einvoice = EinvoiceDetails::where('invoice_id',$order_details->invoice_id)->where('status','Success')->first();

            $total_amount = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
            
            $count_cart_product = DB::table('cr_dr_voucher')->where('cr_dr_voucher.v_id',$order_details->v_id)->where('cr_dr_voucher.store_id',$order_details->store_id)->where('cr_dr_voucher.user_id', $order_details->user_id)->count();

            if($order_details->transaction_type == 'return'){
                $invoice_title     = 'Deposit Receipt';
            }

            $startitem   = 0;
            $getItem     = 8;
            $countitem   = $count_cart_product;
            $totalpage   = ceil($count_cart_product/$getItem);
            $sr          = 1;

            for($i=0;$i < $totalpage ; $i++) {
                $cart_product = DB::table('cr_dr_voucher')->leftJoin('dep_rfd_trans','cr_dr_voucher.ref_id','dep_rfd_trans.trans_src_ref')->where('cr_dr_voucher.v_id',$order_details->v_id)->where('cr_dr_voucher.store_id',$order_details->store_id)->where('cr_dr_voucher.user_id', $order_details->user_id)->skip($startitem)->take(8)->get();
                $startitem  = $startitem+$getItem;
                $startitem  = $startitem; 
// dd($cart_product);
                $count = 1;
                $bilLogo      = '';
                $bill_logo_id = 5;
                $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
                if($vendorImage)
                {
                    $bilLogo = env('ADMIN_URL').$vendorImage->path;
                } 
                $payments  = $order_details->payvia;
                $cash_collected = 0;  
                $cash_return    = 0;
                $net_payable    = $total_amount;
                $mop_list = [];
                foreach ($payments as $payment) {
                    if ($payment->method == 'cash') {
                        $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
                        if($order_details->transaction_type == 'return'){
                            $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
                        }else{
                            $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->cash_collected-$cashReturn ];
                        }
                    } else {
                        $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
                    }
                    if ($payment->method == 'cash') {
                        $cash_collected += (float) $payment->cash_collected;
                        $cash_return += (float) $payment->cash_return;
                    }
                    /*Voucher Start*/
                    if($payment->method == 'voucher_credit'){
                        $voucher[] = $payment->amount;
                        $net_payable = $net_payable-$payment->amount;
                    }
                }
                $customer_paid = $cash_collected;
                $balance_refund= $cash_return;
                $total_discount = (float)$order_details->discount+(float)$order_details->manual_discount+(float)$order_details->bill_buster_discount;
                $terms_conditions =  array('Any Discrepancy/Complaint regarding the goods should be notified within  1 day of the date mentioned on the invoice','Payment should be made by cheque/DD in favor of K Janavi payable at New Delhi','All Dispatches are subject to Delhi jurisdiction only' );

                ########################
                ####### Print Start ####
                ########################

                $data  .= '<table class="print_invoice_table_start" width="98%" style="outline: 1px #000 solid; margin-top: 20px; margin-bottom: 0px; margin-left: auto; margin-right: auto;">';
                $data  .= '<tr><td><table width="100%" style="padding-left: 5px; padding-right: 5px;"><tr>';
                $data .= '<td width="25%">';
                $data .= '<table ><tr><td><img src="'.$bilLogo.'" alt="" height="80px"></td></tr></table></td>';
                $data .= '<td width="50%">';               
                $data .= '<table width="100%"   class="top-head" bgcolor="#fff" align="left" style=" text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;" >';                
                $data  .=  '<tr style="font-size: 16px; padding: 5px;"><td><b style="font-size: 18px;">'.$store->name.'</b></td></tr>';                
                $data  .=  '<tr><td>'.$store->address1.'</td></tr>';
                if($store->address2){
                    $data  .=  '<tr><td>'.$store->address2.'</td></tr>';
                }
                $data  .=  '<tr><td>'.$store->location.'-'.$store->pincode.','.$store->state.'</td></tr>';
                
                $data  .=  '<tr><td>Contact No: '.$store->contact_number.'</td></tr>';
                $data  .=  '<tr><td>E-mail: '.$store->email.'</td></tr>';
                $data  .=  '<tr><td>GSTIN: '.$store->gst.'</td></tr>';
                $data  .=  '</table></td>';
                $data .= '<td width="25%"></td>';
                $data .= '</tr></table></td></tr>';
                $data  .= '<tr><td><table style="width: 100%; color: #fff; padding: 5px; text-align: center; border: 1px #000 solid; border-left: none; border-right: none;"><tr><td valign="top" style="line-height: 1.5;  color: #000;"><b>'.$invoice_title.'</b></td></tr></table></td></tr>';
                
                $data  .=  '<tr>
                <td>
                <table style="width: 100%; color: #fff; padding: 5px;">';
                $data  .=  '<tr>
                <td valign="top" style="line-height: 1;  color: #000; font-size: 12px;text-align:left;">
                Customer Mobile :'.@$order_details->user->mobile.'</b>';
                $data .= '<br>Customer Name :' . @$order_details->user->first_name.''.@$order_details->user->last_name;
                $data   .= '</td>';
                $data  .= '<td valign="top" style="line-height: 1.5; color: #000; font-size: 14px;" align="right">Document No: '.$order_details->invoice_id.'<br>Date : '.date('d-M-Y', strtotime($order_details->date)).' at '.date('h:i:sa',strtotime($order_details->time)).'</td></tr></table></td></tr>';
                $data  .= '<tr><td><div  style="overflow: hidden; border-top: 2px #000 solid; border-bottom: 2px #000 solid; "  width="100%" ><table height="100%" width="100%" class="print_receipt_invoice" bgcolor="#fff" style="width: 100%; color: #000;">';
                $data  .= '<thead ><tr align="left">';
                $data .= '<th width="5%"  style=" font-size: 12px;" >#</th>
                          <th width="25%" valign="center"  style="font-size: 12px; text-align:center;" >Document No</th>
                          <th width="25%" valign="center"  style=" font-size: 12px; text-align:center;" >Credit Note No</th>
                          <th width="45%" valign="center"  style=" font-size: 12px; text-align:center; border-right: none;" >Amount</th></tr></thead><tbody>';
           
                $srp            = '';
                $document_no    = '';
                $voucher_no     = '';
                $amount         = '';        
                foreach ($cart_product as $key => $value) {
                    $remark = isset($value->remark)?' -'.$value->remark:'';
                    $srp       .= '<pre>'.$sr.'</pre>';
                    $document_no   .= '<pre style="text-align:center;">'.$value->doc_no.'</pre>';
                    $voucher_no       .= '<pre style="text-align:center;">'.$value->voucher_no.'</pre>';
                    $amount      .= '<pre style="text-align:right;">'.abs($value->amount).'</pre>';
                    $sr++;
                }
                $data   .= '<tr align="left">';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$srp.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$document_no.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$voucher_no.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px; border-right: none;">'.$amount.'</td></tr>';
                $data   .= '</tbody>';
                $data .= '</table></td></tr></div>';
                $data   .= '<tr>
                <td>
                <table width="100%" style="color: #000;">
                <tr>
                <td>
                <table width="100%" style="padding: 5px;">
                <tr>
                ';

                if($totalpage-1 == $i){
                    $total      = 0;
                    $invoiceData  = DB::table('cr_dr_voucher')->leftJoin('dep_rfd_trans','cr_dr_voucher.ref_id','dep_rfd_trans.trans_src_ref')->where('cr_dr_voucher.v_id',$order_details->v_id)->where('cr_dr_voucher.store_id',$order_details->store_id)->where('cr_dr_voucher.user_id', $order_details->user_id)->get();
                    foreach($invoiceData as $invdata){
                        $total      += abs($invdata->amount);
                    }
                    $data   .= '<td width="100%">';
                    $data   .= '<table width="100%" style="padding: 5px;"><tr>
                    <td width="80%" align="right">Total Amount</td>
                    <td width="20%" align="right">&nbsp;'.$total.'</td>
                    </tr></table>';
                    $data   .=  '</td></tr></table>';
                    $data   .=  '<table width="100%">';
                    $data   .=  '</table></td></tr>';
                    $data   .=  '<tr><td align="left" style="padding: 5px;">Rupees: '.ucfirst(numberTowords(round($total))).'</td></tr>';
                    if($order_details->transaction_type == 'return'){
                        $AmountTitle = 'Tender Refund';
                    }else{
                        $AmountTitle = 'Total Amount';
                    }
                    $data   .= '<table width="50%" style="padding: 5px;"><tr><td width="60%"><table width="100%"><tr align="right"><td width="30%" style="text-align:left"><b>Customer Paid : </b></td><td width="70%" style="text-align:left"><b>'.$cash_collected.'</b></td></tr><tr><td width="30%" style="text-align:left"><b>'.$AmountTitle.' : </b></td><td width="70%" style="text-align:left"><b>'.$balance_refund.'</b></td></tr></table></td></tr></table>';
                    if(isset($order_details->remark)){
                        $data   .=  '<tr><td align="left" style="padding: 5px;">Remark: '.$order_details->remark.'</td></tr>';    
                    }
                }else{
                    $data   .= '<td width="61%" height="205px" valign="top">';
                    $data   .= '<table align="right" style="padding: 5px;"><tr><td></td><td></td></tr></table></td>';
                    $data   .= '<td width="39%">';
                    $data   .= '<table width="100%" style="padding: 5px;"><tr><td width="70%" align="right"></td><td width="30%" align="right"></td></tr></table>';
                    $data   .= '<table width="100%" style="padding: 5px;"><tr>
                                 <td width="70%" align="right" ></td>
                                 <td width="30%" align="right" ></td>
                                 </tr></table>';
                    $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                                    <td width="70%" align="right" ></td>
                                    <td width="30%" align="right" ></td></tr>
                                 </table>';
                    $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                                    <td width="70%" align="right" ></td>
                                    <td  width="30%" align="right" ></td></tr>
                                  </table>';
                    $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                                  <td width="70%" align="right" ></td>
                                  <td  width="30%" align="right" ></td></tr></table>';                              
                    $data   .=  '</td></tr></table>';
                    $data   .=  '<table width="100%"><tr><td width="40%"><table>';
                    $data   .=  '</table></td></tr>';
                    $data   .=  '<tr><td align="left"></td></tr>';
                    
                    if(isset($order_details->remark)){
                         $data   .=  '<tr><td align="left" style="padding: 5px;">Remark: '.$order_details->remark.'</td></tr>';   
                    }
                    $data   .= '<table width="100%" style="padding: 5px;"><tr><td width="60%"></td><td width="40%"><table width="100%"><tr align="right"><td width="100%"><b>Continue..</b></td><td width="30%"></td></tr></table></td></tr></table></td></tr></table></td></tr>';
                }
                $data   .= '<tr class="print_invoice_terms"><td><table bgcolor="#fff" style="width: 100%; padding: 5px; color: #000; border: 1px #000 solid; border-left: none; border-right: none;"><tr width="100%"><td style="padding-bottom: 10px;"><b>Terms & Conditions</td></tr>';
                foreach($terms_conditions as $term){
                    $data .= '<tr width="100%"><td style="padding-bottom: 5px; text-decoration: dotted;">&bull;'.$term.'</td></tr>';
                }
                $data    .= '</table></td></tr>';
                $data    .= '<tr class="print_invoice_last"><td><table width="100%" style="color: #000000; padding-top: 20px !important; padding: 5px;"><tr><td></td></tr><tr class="print_store_sign"><td width="10%" align="left">Cashier:</td><td width="90%" style="text-align:left;">&nbsp;'.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</td></tr></table></td></tr></table>';

                    $data .= '</table></td></tr></table></td></tr>';
                if($totalpage > 1){
                    $data .= '<br><hr>';
                }
            }
            $return = array('status'=>'success','style'=>$style,'html'=>$data) ;
            return $return;
    }

    public function print_invoice_for_Payment_Against_Debit_Note($request){
        $v_id       = $request->v_id;
        $store_id   = $request->store_id; 
        $c_id       = $request->c_id;
        $order_id   = $request->order_id;
        $product_data= [];
        $gst_list    = [];
        $final_gst   = [];
        $detatch_gst = [];
        $rounded     = 0;
        $data    = '';

        $invoice_title = 'Retail Invoice';
        $style = "<style>*{padding:0;margin:0;box-sizing:border-box;-webkit-border-vertical-spacing:0;-webkit-border-horizontal-spacing:0;font-size:14px}.print_receipt_invoice thead tr th{border-right:1px #000 solid; color: #000; border-bottom:1px #000 solid;border-top:1px #000 solid;border-top:none; padding: 5px;}.print_receipt_invoice thead tr:last-child{border-right:none}.print_receipt_invoice tbody tr td{padding: 10px 5px; border-right:1px #000 solid}.print_receipt_invoice tbody tr td pre{min-height:29px;text-align:left;white-space:normal;word-wrap:break-word; font-size: 11px;max-height: 29px;overflow:hidden;line-height: 1.5;}.print_receipt_invoice tbody tr td:last-child{border-right:none}.print_receipt_top-head tr td{padding:2px}.print_invoice_terms td table{text-align: left;}.print_invoice_last td table td{text-align: left;}.print_store_sign td:nth-child(2){text-align: right;}.print_invoice_last td table:last-child{margin-top: 40px;}.print_invoice_table_start table tbody tr td{font-size:13px;}.print_invoice_table_start table tbody tr td p{font-size:13px;}.print_invoice_terms td{ border-left: none;}</style>";

            $printArray  = array();
            $store         = Store::find($store_id);
            $order_details = Invoice::where('invoice_id', $order_id)->first();

            $total_amount = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');

            $count_cart_product = DB::table('cr_dr_voucher')->where('cr_dr_voucher.v_id',$order_details->v_id)->where('cr_dr_voucher.store_id',$order_details->store_id)->where('cr_dr_voucher.user_id', $order_details->user_id)->count();   

            if($order_details->transaction_type == 'return'){
                $invoice_title     = 'Payment Receipt';
            }

            $startitem   = 0;
            $getItem     = 8;
            $countitem   = $count_cart_product;
            $totalpage   = ceil($count_cart_product/$getItem);
            $sr          = 1;

            for($i=0;$i < $totalpage ; $i++) {
                $cart_product = DB::table('cr_dr_voucher')->select(DB::raw("sum(cr_dr_settlement_log.applied_amount) as total_balance"),'dep_rfd_trans.doc_no','cr_dr_voucher.voucher_no','cr_dr_settlement_log.applied_amount','cr_dr_voucher.amount')->leftjoin('cr_dr_settlement_log','cr_dr_voucher.id','cr_dr_settlement_log.voucher_id')->leftJoin('dep_rfd_trans','cr_dr_voucher.ref_id','dep_rfd_trans.trans_src_ref')->where('cr_dr_voucher.v_id',$order_details->v_id)->where('cr_dr_voucher.store_id',$order_details->store_id)->where('cr_dr_voucher.user_id', $order_details->user_id)->groupBy('cr_dr_voucher.id')->skip($startitem)->take(8)->get();
                    $startitem  = $startitem+$getItem;
                    $startitem  = $startitem;
                $count = 1;
                $bilLogo      = '';
                $bill_logo_id = 5;
                $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
                if($vendorImage)
                {
                    $bilLogo = env('ADMIN_URL').$vendorImage->path;
                } 
                $payments  = $order_details->payvia;
                $cash_collected = 0;  
                $cash_return    = 0;
                $net_payable    = $total_amount;
                $mop_list = [];
                foreach ($payments as $payment) {
                    if ($payment->method == 'cash') {
                        $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
                        if($order_details->transaction_type == 'return'){
                            $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
                        }else{
                            $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->cash_collected-$cashReturn ];
                        }
                    } else {
                        $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
                    }
                    if ($payment->method == 'cash') {
                        $cash_collected += (float) $payment->cash_collected;
                        $cash_return += (float) $payment->cash_return;
                    }
                    /*Voucher Start*/
                    if($payment->method == 'voucher_credit'){
                        $voucher[] = $payment->amount;
                        $net_payable = $net_payable-$payment->amount;
                    }
                }
                $customer_paid = $cash_collected;
                $balance_refund= $cash_return;
                $total_discount = (float)$order_details->discount+(float)$order_details->manual_discount+(float)$order_details->bill_buster_discount;
                $terms_conditions =  array('Any Discrepancy/Complaint regarding the goods should be notified within  1 day of the date mentioned on the invoice','Payment should be made by cheque/DD in favor of K Janavi payable at New Delhi','All Dispatches are subject to Delhi jurisdiction only' );

                ########################
                ####### Print Start ####
                ########################

                $data  .= '<table class="print_invoice_table_start" width="98%" style="outline: 1px #000 solid; margin-top: 20px; margin-bottom: 0px; margin-left: auto; margin-right: auto;">';
                $data  .= '<tr><td><table width="100%" style="padding-left: 5px; padding-right: 5px;"><tr>';
                $data .= '<td width="25%">';
                $data .= '<table ><tr><td><img src="'.$bilLogo.'" alt="" height="80px"></td></tr></table></td>';
                $data .= '<td width="50%">';               
                $data .= '<table width="100%"   class="top-head" bgcolor="#fff" align="left" style=" text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;" >';                
                $data  .=  '<tr style="font-size: 16px; padding: 5px;"><td><b style="font-size: 18px;">'.$store->name.'</b></td></tr>';                
                $data  .=  '<tr><td>'.$store->address1.'</td></tr>';
                if($store->address2){
                    $data  .=  '<tr><td>'.$store->address2.'</td></tr>';
                }
                $data  .=  '<tr><td>'.$store->location.'-'.$store->pincode.','.$store->state.'</td></tr>';
                
                $data  .=  '<tr><td>Contact No: '.$store->contact_number.'</td></tr>';
                $data  .=  '<tr><td>E-mail: '.$store->email.'</td></tr>';
                $data  .=  '<tr><td>GSTIN: '.$store->gst.'</td></tr>';
                $data  .=  '</table></td>';
                $data .= '<td width="25%"></td>';
                $data .= '</tr></table></td></tr>';
                $data  .= '<tr><td><table style="width: 100%; color: #fff; padding: 5px; text-align: center; border: 1px #000 solid; border-left: none; border-right: none;"><tr><td valign="top" style="line-height: 1.5;  color: #000;"><b>'.$invoice_title.'</b></td></tr></table></td></tr>';
                
                $data  .=  '<tr>
                <td>
                <table style="width: 100%; color: #fff; padding: 5px;">';
                $data  .=  '<tr>
                <td valign="top" style="line-height: 1;  color: #000; font-size: 12px;text-align:left;">
                Customer Mobile :'.@$order_details->user->mobile.'</b>';
                $data .= '<br>Customer Name :' . @$order_details->user->first_name.''.@$order_details->user->last_name;
                $data   .= '</td>';
                $data  .= '<td valign="top" style="line-height: 1.5; color: #000; font-size: 14px;" align="right">Document No: '.$order_details->invoice_id.'<br>Date : '.date('d-M-Y', strtotime($order_details->date)).' at '.date('h:i:sa',strtotime($order_details->time)).'</td></tr></table></td></tr>';
                $data  .= '<tr><td><div  style="overflow: hidden; border-top: 2px #000 solid; border-bottom: 2px #000 solid; "  width="100%" ><table height="100%" width="100%" class="print_receipt_invoice" bgcolor="#fff" style="width: 100%; color: #000;">';
                $data  .= '<thead ><tr align="left">';
                $data .= '<th width="5%"  style=" font-size: 12px;" >#</th>
                          <th width="25%" valign="center"  style="font-size: 12px; text-align:center;" >Document No</th>
                          <th width="25%" valign="center"  style=" font-size: 12px; text-align:center;" >Debit Note No</th>
                          <th width="45%" valign="center"  style=" font-size: 12px; text-align:center; border-right: none;" >Amount</th></tr></thead><tbody>';
                $total_applied_amount  = 0;
                $total = 0;
                $srp            = '';
                $document_no    = '';
                $voucher_no     = '';
                $amount         = ''; 
                $applied_Amount = '';
                foreach ($cart_product as $key => $value) {
                    $total_applied_amount += $value->total_balance;
                    $total += abs($value->amount);
                    $remark = isset($value->remark)?' -'.$value->remark:'';
                    $srp       .= '<pre>'.$sr.'</pre>';
                    $document_no   .= '<pre style="text-align:center;">'.$value->doc_no.'</pre>';
                    $voucher_no       .= '<pre style="text-align:center;">'.$value->voucher_no.'</pre>';
                    $amount      .= '<pre style="text-align:right;">'.abs($value->amount).'</pre>';
                    $applied_Amount .= '<pre style="text-align:right;">('.$value->total_balance.')</pre>';
                    $sr++;
                }
                $data   .= '<tr align="left">';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$srp.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$document_no.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$voucher_no.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px; border-right: none;">'.$amount.'</td></tr>';
                $data   .= '</tbody>';
                $data .= '</table></td></tr></div>';
                $data   .= '<tr>
                <td>
                <table width="100%" style="color: #000;">
                <tr>
                <td>
                <table width="100%" style="padding: 5px;">
                <tr>
                ';

                if($totalpage-1 == $i){
                    $data   .= '<td width="100%">';
                    $data   .= '<table width="100%" style="padding: 5px;"><tr>
                    <td width="80%" align="right">Total Amount</td>
                    <td width="20%" align="right">&nbsp;'.$total.'</td>
                    </tr></table>';
                    $data   .=  '</td></tr></table>';
                    $data   .=  '<table width="100%">';
                    $data   .=  '</table></td></tr>';
                    $data   .=  '<tr><td align="left" style="padding: 5px;">Rupees: '.ucfirst(numberTowords(round($total))).'</td></tr>';
                    if($order_details->transaction_type == 'return'){
                        $AmountTitle = 'Tender Refund';
                    }else{
                        $AmountTitle = 'Total Amount';
                    }
                    $data   .= '<table width="50%" style="padding: 5px;"><tr><td width="60%"><table width="100%"><tr align="right"><td width="30%" style="text-align:left"><b>Customer Paid : </b></td><td width="70%" style="text-align:left"><b>'.$cash_collected.'</b></td></tr><tr><td width="30%" style="text-align:left"><b>'.$AmountTitle.' : </b></td><td width="70%" style="text-align:left"><b>'.$balance_refund.'</b></td></tr></table></td></tr></table>';
                    // if(isset($order_details->remark)){
                    //     $data   .=  '<tr><td align="left" style="padding: 5px;">Remark: '.$order_details->remark.'</td></tr>';    
                    // }
                    
                }else{
                    $data   .= '<td width="61%" height="205px" valign="top">';
                    $data   .= '<table align="right" style="padding: 5px;"><tr><td></td><td></td></tr></table></td>';
                    $data   .= '<td width="39%">';
                    $data   .= '<table width="100%" style="padding: 5px;"><tr><td width="70%" align="right"></td><td width="30%" align="right"></td></tr></table>';
                    $data   .= '<table width="100%" style="padding: 5px;"><tr>
                                 <td width="70%" align="right" ></td>
                                 <td width="30%" align="right" ></td>
                                 </tr></table>';
                    $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                                    <td width="70%" align="right" ></td>
                                    <td width="30%" align="right" ></td></tr>
                                 </table>';
                    $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                                    <td width="70%" align="right" ></td>
                                    <td  width="30%" align="right" ></td></tr>
                                  </table>';
                    $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                                  <td width="70%" align="right" ></td>
                                  <td  width="30%" align="right" ></td></tr></table>';                              
                    $data   .=  '</td></tr></table>';
                    $data   .=  '<table width="100%"><tr><td width="40%"><table>';
                    $data   .=  '</table></td></tr>';
                    $data   .=  '<tr><td align="left"></td></tr>';
                    
                    // if(isset($order_details->remark)){
                    //      $data   .=  '<tr><td align="left" style="padding: 5px;">Remark: '.$order_details->remark.'</td></tr>';  
                    // }
                    $data   .= '<table width="100%" style="padding: 5px;"><tr><td width="60%"></td><td width="40%"><table width="100%"><tr align="right"><td width="100%"><b>Continue..</b></td><td width="30%"></td></tr></table></td></tr></table></td></tr></table></td></tr>';
                }
                $data .= '<tr><td style="text-align:center;padding-bottom:10px;">Balance as on Date</td></tr><tr><td><div  style="overflow: hidden; border-top: 2px #000 solid; border-bottom: 2px #000 solid; "  width="100%" ><table height="100%" width="100%" class="print_receipt_invoice" bgcolor="#fff" style="width: 100%; color: #000;">';
                $data  .= '<thead ><tr align="left">';
                $data .= '<th width="5%"  style=" font-size: 12px;" >#</th>
                          <th width="25%" valign="center"  style="font-size: 12px; text-align:center;" >Document No</th>
                          <th width="25%" valign="center"  style=" font-size: 12px; text-align:center;" >Debit Note No</th>
                          <th width="45%" valign="center"  style=" font-size: 12px; text-align:center; border-right: none;" >Amount</th></tr></thead><tbody>';
                          $data   .= '<tr align="left">';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$srp.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$document_no.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$voucher_no.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px; border-right: none;">'.$applied_Amount.'</td></tr>';

                $data   .= '</tbody>';
                $data .= '</table></td></tr></div>';
                $data   .= '<tr><td><table width="100%" style="padding: 5px;"><tr>
                    <td width="80%" align="right">Total Balance</td>
                    <td width="20%" align="right">&nbsp;('.$total_applied_amount.')</td>
                    </tr></table></td></tr>';
                $data   .= '<tr class="print_invoice_terms"><td><table bgcolor="#fff" style="width: 100%; padding: 5px; color: #000; border: 1px #000 solid; border-left: none; border-right: none;"><tr width="100%"><td style="padding-bottom: 10px;"><b>Terms & Conditions</td></tr>';
                foreach($terms_conditions as $term){
                    $data .= '<tr width="100%"><td style="padding-bottom: 5px; text-decoration: dotted;">&bull;'.$term.'</td></tr>';
                }
                $data    .= '</table></td></tr>';
                $data    .= '<tr class="print_invoice_last"><td><table width="100%" style="color: #000000; padding-top: 20px !important; padding: 5px;"><tr><td></td></tr><tr class="print_store_sign"><td width="10%" align="left">Cashier:</td><td width="90%" style="text-align:left;">&nbsp;'.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</td></tr></table></td></tr></table>';
                
                $data .= '</table></td></tr></table></td></tr>';
                if($totalpage > 1){
                    $data .= '<br><hr>';
                }
            }
            $return = array('status'=>'success','style'=>$style,'html'=>$data) ;
            return $return;
    }

    public function print_html_page_for_localls($request){
            $v_id       = $request->v_id;
            $store_id   = $request->store_id; 
            $c_id       = $request->c_id;
            $order_id   = $request->order_id;
            $product_data= [];
            $gst_list    = [];
            $final_gst   = [];
            $detatch_gst = [];
            $rounded     = 0;
            $data    = '';
            // $terms_conditions =  '';
            $invoice_title = 'Retail Invoice';
                $style = "<style>

            @font-face {
                font-family: 'open_sans';
                src: url('https://test.api.gozwing.com/einvoice/open-sans-v20-latin-regular.woff2;') format('woff2'),
                     url('https://test.api.gozwing.com/einvoice/open-sans-v20-latin-regular.woff;') format('woff');
                font-weight: 600;
                font-style: normal;
            }
            @font-face {
                font-family: 'open_sans';
                src: url('https://test.api.gozwing.com/einvoice/open-sans-v20-latin-600.woff;') format('woff2'),
                     url('https://test.api.gozwing.com/einvoice/open-sans-v20-latin-600.woff;') format('woff');
                font-weight: normal;
                font-style: normal;
            }


                *{padding:0;margin:0;box-sizing:border-box;-webkit-border-vertical-spacing:0;-webkit-border-horizontal-spacing:0;font-size:14px}.bold{font-weight: bold;} body{font-family: 'opensans'; font-size: 14px;}.print_receipt_invoice thead tr th{border-right:1px #000 solid; color: #000; border-bottom:1px #000 solid;border-top:1px #000 solid;border-top:none; padding: 5px;}.print_receipt_invoice thead tr:last-child{border-right:none}.print_receipt_invoice tbody tr td{padding: 10px 5px;}.print_receipt_invoice tbody tr td pre{min-height:29px;font-family: 'opensans';text-align:left;white-space:normal;word-wrap:break-word; font-size: 11px;max-height: 29px;overflow:hidden;line-height: 1.5;}.print_receipt_invoice tbody tr td:last-child{border-right:none}.print_receipt_top-head tr td{padding:2px}.print_invoice_terms td table{text-align: left;}.print_invoice_last td table td{text-align: left;}.print_store_sign td:nth-child(2){text-align: right;}.print_invoice_last td table:last-child{margin-top: 40px;}.print_invoice_table_start table tbody tr td{font-size:12px;}.print_invoice_table_start table tbody tr td p{font-size:12px;}.print_invoice_terms td{ border-left: none;}</style>";    
            


            $printArray  = array();
            $store         = Store::find($store_id);
            $order_details = Invoice::where('invoice_id', $order_id)->first();

            $einvoice = EinvoiceDetails::where('invoice_id',$order_details->invoice_id)->where('status','Success')->first();
            $qrImage = '';
            if($einvoice && !empty($einvoice->signed_qr_code)){
              
               $qrImage      = $this->generateQRCode(['content'=>$einvoice->signed_qr_code]);
                //$qrImage      = $einvoice->qrcode_image_path;
            }


            $cart_q = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','0')->where('user_id', $order_details->user_id)->sum('qty');
            $cart_qt = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','1')->where('user_id', $order_details->user_id)->count('qty');
            $cart_qty = $cart_q + $cart_qt;
            $total_amount = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
            // dd($total_amount);
            $count_cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->count();

            if($order_details->transaction_type == 'return'){
                $invoice_title     = 'Credit Note';
            }

            $startitem   = 0;
            $getItem     = 8;
            $countitem   = $count_cart_product;
            $totalpage   = ceil($count_cart_product/$getItem);
            $sr          = 1;

            for($i=0;$i < $totalpage ; $i++) {
              // DB::enableQueryLog(); 
             $cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->skip($startitem)->take(8)->get();
             // dd(DB::getQueryLog());
            $startitem  = $startitem+$getItem;
            $startitem  = $startitem;
             
            $customer_address = '';
            if(isset($order_details->user->address->address1)){
                $customer_address .= $order_details->user->address->address1;
            }
            if(isset($order_details->user->address->address2)){
                $customer_address .= $order_details->user->address->address2;
            }

            $count = 1;
            $gst_tax = 0;
            $gst_listing = [];
            $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
            //dd($gst_list);
            $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
            $cgst = $sgst = $cess = 0 ;
            // dd($final_gst);
 
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
            $bill_logo_id = 5;
            $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
            if($vendorImage)
            {
                $bilLogo = env('ADMIN_URL').$vendorImage->path;
            } 
            $payments  = $order_details->payvia;
            $cash_collected = 0;  
            $cash_return    = 0;
            $net_payable    = $total_amount;

            //dd($payments);
            $mop_list = [];
            foreach ($payments as $payment) {
            $paymentdata = Mop::select('name')->where('code',$payment->method)->first();
            $mopname = '';
            if(isset($paymentdata->name)){
                $mopname = $paymentdata->name;
            }else{
                $mopname = '';
            }
            // dd($payment->amount);
            if ($payment->method == 'cash') {
            $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
            if($order_details->transaction_type == 'return'){
               $mop_list[] = [ 'mode' => $mopname, 'amount' => $payment->amount ];
            }else{
               $mop_list[] = [ 'mode' => $mopname, 'amount' => $payment->cash_collected-$cashReturn ];
            }
            } else {
            $mop_list[] = [ 'mode' => $mopname, 'amount' => $payment->amount ];
            }
            if ($payment->method == 'cash') {
            $cash_collected += (float) $payment->cash_collected;
            $cash_return += (float) $payment->cash_return;
            }
            /*Voucher Start*/
            if($payment->method == 'voucher_credit'){
            $voucher[] = $payment->amount;
            $net_payable = $net_payable-$payment->amount;
            }
            }
            $store_name = 0;$store_name1 = '';$store_name2 = '';
            $store_name = $store->name;
            $ltrim = explode(' ',$store_name);
            $store_name1 = $ltrim[0].'<br>';
            if(isset($ltrim[1]) || isset($ltrim[2]) || isset($ltrim[3]) || isset($ltrim[4]))
            {
                $store_name2 = $ltrim[1]." ".$ltrim[2]." ".$ltrim[3]." ".$ltrim[4];
            }
            

            
            // dd($store_name,$store_name1,$store_name2);
            $customer_paid = $cash_collected;
            $balance_refund= $cash_return;
            $total_discount = (float)$order_details->discount+(float)$order_details->manual_discount+(float)$order_details->bill_buster_discount;

                $terms_conditions =  array('MRP are inclusive of applicable Taxes.','Goods Once Sold. No Exchange, No Return & No Refund.','This is digital Invoice, dose not require any signature.', 'Certified that above particulars are true & correct.','All Disputes are subject to Palghar Jurisdiction' ); 
                // $terms =  Terms::where('v_id',$v_id)->get();
                //     $terms_condition = json_decode($terms);
                //         // dd($terms_condition);
                //     foreach ($terms_condition as $value) {
                //         $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                //     // dd($terms_conditions);
                //     }

            ########################
            ####### Print Start ####
            ########################
            
            
            //$data = '<body style="padding: 20px;">';
            
            //$data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"><table class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';
            /* $data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"> <table width="10%"><tr><td><img src="'.$bilLogo.'" alt="" height="80px"></td></tr>
           </table><table width="80%" class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';*/
        $data  .= '<table class="print_invoice_table_start" width="98%" style="outline: 1px #000 solid; margin-top: 20px; margin-bottom: 0px; margin-left: auto; margin-right: auto;">';
        // $data .= '<table width="100%"><tr><td style="text-align: center;">Tax Invoice</td></tr></table>';
            $data  .= '<tr><td>
                            <table width="100%" style="padding-left: 5px; padding-right: 5px;"><tr><td colspan="2" style="text-align: center;"><b>TAX INVOICE</b></td></tr><tr>';
                            // if($request->v_id == 127){
                                $data .=  '<td width="75%" >
                                            <table width="90%"   class="top-head" bgcolor="#fff" align="left" style=" text-align: left; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;" >';
                                $data  .=  '<tr style="font-size: 16px; padding: 5px;"><td style="font-size: 15px;">'.$store_name1.$store_name2.'</td></tr>';       
                                $data  .=  '<tr><td>'.$store->address1;
                                if($store->address2){
                                    $data  .=  $store->address2;
                                }
                                $data  .=  '-'.$store->pincode.'.' .$store->location.'.'.$store->state.'</td></tr>';
                                $data  .=  '<tr><td><table width="100%"><tbody>
                                                        <tr>
                                                            <td width="50%">Mobile No : '.$store->contact_number.'</td>
                                                            <td width="50%">www.localls.in</td>
                                                        </tr>
                                                        </tbody></table>
                                                </td></tr>';
                                    $data  .=  '<tr><td><table width="100%"><tbody>
                                                        <br>
                                                        <tr>
                                                            <td width="50%">GSTIN : '.$store->gst.'</td>
                                                            <td width="50%">Invoice No :'.$order_details->invoice_id.'</td>
                                                        </tr>
                                                        </tbody></table>
                                                </td></tr>';
                                    $data  .=  '<tr><td><table width="100%"><tbody>
                                                        <tr>
                                                            <td width="50%">FSSAI : 21521090000201</td>
                                                            <td width="50%">Date :'.date('d/M/Y', strtotime($order_details->date)).'</td>
                                                        </tr>
                                                        </tbody></table>
                                                </td></tr>';
                                    $data  .=  '<tr><td><table width="100%"><tbody>
                                                        <tr>
                                                            <td width="50%"></td>
                                                            <td width="50%">Time :'.$order_details->time.'</td>
                                                        </tr>
                                                        </tbody></table>
                                                </td></tr>';
                                    $data  .=  '<tr><td><table width="100%"><tbody>
                                                        <tr>
                                                            <td width="50%">Customer Number : '.$order_details->user->mobile.'</td>
                                                            <td width="50%">Customer Name : '.@$order_details->user->first_name.' '.@$order_details->user->last_name.'</td>
                                                        </tr>
                                                        </tbody></table>
                                                </td></tr></table>';
                               $data .= '<td width="25%"><table ><tr><td style="text-align:right; padding-right: 15px;"><img src="'.$bilLogo.'" alt="" height="auto" width="100%"></td></tr>';   
                            
            if(!empty($qrImage)){
                $data  .=  '<tr><td><img src='.$qrImage.'></td></tr>';    
            }
            $data  .=  '</table></td></tr></table></td></tr>';
            // $data  .= '<tr><td valign="top" style="line-height: 1.5;  color: #000">'.@$order_details->user->mobile.'</td>';
            // $data .=  '<td valign="top" style="line-height: 2.1; color: #000" align="right">Invoice No:<span style="color: #000;">'.$order_details->invoice_id.'</span></td></tr></table></td></tr>';
            /*$data  .= '<tr><td></td><td valign="top" style="line-height: 2.1; color: #000" align="right">Cashier <span style="color: #000;">'.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</span></td></tr></table></td></tr>';*/
            $data  .= '<tr><td><div  style="height: 500px; overflow: hidden; border-top: 1px #000 solid; border-bottom: 1px #000 solid; "  width="100%" ><table height="100%" width="100%" class="print_receipt_invoice" bgcolor="#fff" style="width: 100%; color: #000;">';
            $data  .= '<thead ><tr align="left">';
            // if($request->v_id == 127){
                $data .= '<th width="3%"  style="text-align: center; font-size: 12px;" >Sr.</th>
                        <th width="10%" valign="center"  style="text-align: center; font-size: 12px; " >Barcode</th>
                        <th width="25%" valign="center"  style="text-align: center; font-size: 12px; " >Product Description</th>
                        <th width="10%" valign="center"  style="text-align: center; font-size: 12px;" >HSN Code</th>
                        <th width="5%" valign="center"  style="text-align: center; font-size: 12px;" >Qty.</th>
                        <th width="12%" valign="center"  style="text-align: center; font-size: 12px; " >Rate</th>
                        <th width="8%" valign="center"  style="text-align: center; font-size: 12px; " >Discount</th>
                        <th width="9%" valign="center"  style="text-align: center; font-size: 12px;" >GST %</td>
                        <th width="11%" valign="center"  style="text-align: center; font-size: 12px;" >Tax Amount</th>
                        <th width="8%" valign="center"  style="text-align: center; font-size: 12px; border-right: none;" >Amount</th></tr></thead><tbody>';
            
            $srp= '';
            $barcode = '';
            $hsn ='';
            $item_name ='';
            $qty  = '';
            $unit = '';
            $mrp  = '';
            $disc = '';
            $taxp = '';
            $taxb = '';

            $taxable_amount = 0;
            $total_csgt     = 0;
            $total_sgst     = 0;
            $total_cess     = 0;
            $total_rate     = 0;
            $srp            = '';
            $barcode        = '';
            $hsn            = '';
            $item_name      = '';
            $qty            = '';
            $rate           = '';
            $mrp            = '';
            $disc           = '';
            $taxp           = '';
            $taxb           = '';
            $Taxamount      = '';
            $tax_name       = '';
            $tax_cgst       = '';
            $tax_sgst       = '';
            $tax_igst       = '';  
            $taxable        = '';         
// dd($cart_product);
            foreach ($cart_product as $key => $value) {
                $remark = isset($value->remark)?' -'.$value->remark:'';
                $tdata    = json_decode($value->tdata);
                
                $gst_list[] = [
                    'name'              => $tdata->tax_name,
                    'wihout_tax_price'  => $tdata->taxable,
                    'tax_amount'        => $tdata->tax,
                    'cgst'              => $tdata->cgstamt,
                    'sgst'              => $tdata->sgstamt,
                    'cess'              => $tdata->cessamt,
                    'igst'              => $tdata->igstamt
                ];
                // print_r($gst_list)
                $itemLevelmanualDiscount=0;
                if($value->item_level_manual_discount!=null){
                    $iLmd = json_decode($value->item_level_manual_discount);
                    $itemLevelmanualDiscount= (float)$iLmd->discount;
                }
                $discount = $value->discount+$value->manual_discount + $value->bill_buster_discount+$itemLevelmanualDiscount;
                $netamt   =   $value->subtotal-$discount;
                $taxper   = $tdata->cgst + $tdata->sgst +$tdata->igst;
                $taxable_amount += $tdata->taxable;
                $total_csgt  += $tdata->cgstamt;
                $total_sgst  += $tdata->sgstamt;
                $total_cess  += $tdata->cessamt;
                $taxamount  = $tdata->cgstamt + $tdata->sgstamt + $tdata->igstamt + $tdata->cessamt;
                $totalmrp = $value->unit_mrp * $value->qty;
                if($tdata->tax_type == 'INC'){ 
                    $net_value = ($totalmrp - $discount) - $taxamount;
                }
                else{
                    $net_value = ($totalmrp - $discount) + $taxamount;
                }
                $total_rate += $net_value;
                // if($tdata->tax_type == 'INC'){
                //     $taxvalue = $totalmrp - $discount;
                //     $taxdata = 1 + $taxper/100;
                //     $taxableamt = $taxvalue/$taxdata;
                //     $gst = $taxableamt * $taxper/100;
                // }else{
                //     $gst = ($totalmrp - $discount) * $taxper/100;
                // }

                if($tdata->tax_type == 'INC'){
                    $totalamt = $totalmrp;
                }else{
                    $excgst = ($totalmrp - $discount) * $taxper/100;
                    $totalamt = $totalmrp + $excgst;
                }

                $srp       .= '<pre style="text-align: center;">'.$sr.'</pre>';
                $barcode   .= '<pre style="text-align: right;">'.$value->barcode.'</pre>';
                $hsn       .= '<pre style="text-align: center;">'.$tdata->hsn.'</pre>';
                $item_name .= '<pre style="text-align: left;">'.$value->item_name.'</pre>';
                $qty       .= '<pre style="text-align: center;">'.$value->qty.'</pre>';
                $rate      .= '<pre style="text-align: right;"><span>&#8377; </span>'.$net_value.'</pre>';
                $mrp       .= '<pre style="text-align: center;">'.$value->unit_mrp.'</pre>';
                $disc      .= '<pre style="text-align: right;"><span>&#8377; </span>'.$discount.'</pre>';
                $taxp      .= '<pre style="text-align: center;">'.$taxper.'%</pre>';
                $taxb      .= '<pre style="text-align: right;"><span>&#8377; </span>'.$totalamt.'</pre>';
                $Taxamount .= '<pre style="text-align: right;"><span>&#8377; </span>'.number_format($taxamount,2).'</pre>';
                $sr++;
            }
            $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
            $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
            $cgst = $sgst = $cess = $igst = 0 ;
            foreach ($gst_listing as $key => $value) {
                $tax_ab = [];
                $tax_cg = [];
                $tax_sg = [];
                $tax_ig = [];
                $tax_ces = [];

                foreach ($gst_list as $val) {

                    if ($val['name'] == $value) {
                        $total_gst             += str_replace(",", '', $val['tax_amount']);
                        $taxable_amount        += str_replace(",", '', $val['wihout_tax_price']);
                        $tax_ab[]       =  str_replace(",", '', $val['wihout_tax_price']);
                        $tax_cg[]       =  str_replace(",", '', $val['cgst']);
                        $tax_sg[]       =  str_replace(",", '', $val['sgst']);
                        $tax_ig[]       =  str_replace(",", '', $val['igst']);
                        $tax_ces[]      =  str_replace(",", '', $val['cess']);
                        $cgst              += str_replace(",", '', $val['cgst']);
                        $sgst              += str_replace(",", '', $val['sgst']);
                        $cess              += str_replace(",", '', $val['cess']);
                        $igst              += str_replace(",", '', @$val['igst']);
                        $final_gst[$value] = (object)[
                            'name'      => $value,
                        'taxable'   => array_sum($tax_ab),//$this->format_and_string($taxable_amount),
                        'cgst'      => round(array_sum($tax_cg),2),
                        'sgst'      => round(array_sum($tax_sg),2),
                        'igst'      => round(array_sum($tax_ig),2),
                        'cess'      => round(array_sum($tax_ces),2)
                    ];
                }
            }
        }
        $total_csgt = round($cgst,2);
        $total_sgst = round($sgst,2);
        $total_cess = round($cess,2);
        $total_igst = round($igst,2);

        foreach ($final_gst as $key => $value) {
            $tax_details = json_decode(json_encode($value),true);
            $taxable   .= '<p><span>&#8377; </span>'.$tax_details['taxable'].'</p>';
            $tax_name .= '<p>'.$tax_details['name'].'</p>';
            $tax_cgst .= '<p><span>&#8377; </span>'.$tax_details['cgst'].'</p>';
            $tax_sgst .= '<p><span>&#8377; </span>'.$tax_details['sgst'].'</p>';
            $tax_igst .= '<p><span>&#8377; </span>'.$tax_details['igst'].'</p>';
        }

            $data   .= '<tr align="left">';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$srp.'</td>';
                $data   .= '<td valign="top" style="text-align: right; font-size: 12px;">'.$barcode.'</td>';
                $data   .= '<td valign="top" style="text-align: left; font-size: 12px;">'.$item_name.'</td>';
                $data   .= '<td valign="top" style="text-align: center; font-size: 12px;">'.$hsn.'</td>';
                $data   .= '<td valign="top" style="text-align: center; font-size: 12px;">'.$qty.'</td>';
                $data   .= '<td valign="top" style="text-align: right; font-size: 12px;">'.$rate.'</td>';
                $data   .= '<td valign="top" style="text-align: right; font-size: 12px;">'.$disc.'</td>';
                $data   .= '<td valign="top" style="text-align: center; font-size: 12px;">'.$taxp.'</td>';
                $data   .= '<td valign="top" style="text-align: right; font-size: 12px;">'.$Taxamount.'</td>';
                $data   .= '<td valign="top" style="text-align: right; font-size: 12px; border-right: none;">'.$taxb.'</td> </tr>';
            $total_csgt = round($total_csgt,2);
            $total_sgst = round($total_sgst,2);
            $total_cess = round($total_cess,2);
            $data   .= '</tbody>';

                // }
            $data .= '</table></td></tr></div>';
            
            // dd($totalpage - 1);
               if($totalpage - 1 == $i){ 
                    // dd('hi');
                    /*Calcualte complete taxable value*/
                    $taxable_amount = 0;
                    $total_csgt     = 0;
                    $total_sgst     = 0;
                    $total_cess     = 0;
                    $sub_total      = 0;
                    $total_igst     = 0;
                    $total_rate     = 0;
                    $total_discount = 0;
                    $total_tax_amount = 0;
                    $total_amount    = 0;
                    $total_payable   = '';
                    $invoiceData  = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->get();
                    foreach($invoiceData as $invdata){
                        $Ntdata    = json_decode($invdata->tdata);
                        $itemLevelmanualDiscount=0;
                        if($invdata->item_level_manual_discount!=null){
                         $iLmd = json_decode($invdata->item_level_manual_discount);
                         $itemLevelmanualDiscount = (float)$iLmd->discount;
                        }
                        $discount = $invdata->discount+$invdata->manual_discount + $invdata->bill_buster_discount+$itemLevelmanualDiscount;
                        $total_discount += $discount;
                        $taxper          = $Ntdata->cgst + $Ntdata->sgst + $Ntdata->igst;
                        $taxable_amount += $Ntdata->taxable;
                        $total_csgt     += $Ntdata->cgstamt;
                        $total_sgst     += $Ntdata->sgstamt;
                        $total_cess     += $Ntdata->cessamt;
                        $total_igst     += $Ntdata->igstamt;
                        $sub_total      += $invdata->subtotal;
                         $taxamount  = $Ntdata->cgstamt + $Ntdata->sgstamt + $Ntdata->igstamt + $Ntdata->cessamt;
                         $total_tax_amount += $taxamount;
                        $totalmrp = $invdata->unit_mrp * $invdata->qty;
                        if($Ntdata->tax_type == 'INC'){ 
                            $net_value = ($totalmrp - $discount) - $taxamount;
                        }
                        else{
                            $net_value = ($totalmrp - $discount) + $taxamount;
                        }
                        $total_rate += $net_value;
                        // if($Ntdata->tax_type == 'INC'){
                        //     $taxvalue = $totalmrp - $discount;
                        //     $taxdata = 1 + $taxper/100;
                        //     $taxableamt = $taxvalue/$taxdata;
                        //     $gst = $taxableamt * $taxper/100;
                        // }else{
                        //     $gst = ($totalmrp - $discount) * $taxper/100;
                        // }

                        if($Ntdata->tax_type == 'INC'){
                            $totalamt = $totalmrp;
                        }else{
                            $excgst = ($totalmrp - $discount) * $taxper/100;
                            $totalamt = $totalmrp + $excgst;
                        }

                        $total_amount += $totalamt;

                        $total_payable = $total_amount - $total_discount;
                    }
                    /*Calcualte complete taxable value end*/

$data   .= '<tr>
            <td>
            <table width="100%" style="color: #000;">
            <tr>
            <td>
            ';
              $data .= '<tfoot><tr><td width="3%"></td><td width="10%"></td><td width="25%" style="padding: 4px;text-align: right"><b>Total</b></td><td width="10%"></td><td width="5%" style="padding: 4px;text-align: center;"><b>'.$cart_qty.'</b></td><td width="12%" style="padding: 4px;text-align: right;"><b><span>&#8377; </span>'.number_format($total_rate,2).'</b></td><td width="8%" style="text-align: right;"><span>&#8377; </span>'.number_format($total_discount,2).'</td><td width="9%"></td><td width="11%" style="text-align: right;"><span>&#8377; </span>'.$total_tax_amount.'</td><td width="8%" style="text-align: right;"><span>&#8377; </span>'.$total_amount.'</td></tr></tfoot>';
            $data .= '<table width="100%"><tr><td style="padding: 10px;border-bottom: 1px #000 solid;padding-left: 30px;">TOTAL : '.ucfirst(numberTowords(round($total_payable))).'</td></tr></table>';
            $data .= '<table width="100%"><tr><td style="padding: 10px; text-transform: capitalize; border-bottom: 1px #000 solid;padding-left: 30px;">Mode of Payment : ';
                    // dd($mop_list);
                if(count($mop_list) > 1){
                    foreach($mop_list as $mop){
                        $data .= $mop['mode'].' / ';
                    }
                }else{
                    foreach($mop_list as $mop){
                        $data .= $mop['mode'];
                    }
                }
            $data .= '</td></tr></table>';
            // $data .= '<td width="61%" valign="top">';
            // if($v_id == 149){
                if($taxable_amount > 0){
                    if($total_igst == 0){
                    $data   .= '<table width="100%" style="padding: 23px;padding-left: 16px;"><tr style="margin-top: 10px;"><td style="width: 45%;"><table width="100%" style="padding: 5px 0px;"><tr><td style="width: 21%; padding-bottom: 10px; text-align: right;"><b>GST Summary</b></td></tr><tr><td width="20%" style="text-align: right;"><b>Description</b></td><td width="10%" style="text-align: right;"><b>Taxable</b></td><td width="10%" style="text-align: right;"><b>CGST</b></td><td width="10%" style="text-align: right;"><b>SGST</b></td></tr><tr><td style="text-align: right;">'.$tax_name.'</td><td style="text-align: right;">'.$taxable.'</td><td style="text-align: right;">'.$tax_cgst.'</td><td style="text-align: right;">'.$tax_sgst.'</td></tr><tr><td style="text-align: right;"><b>Total</b></td><td style="text-align: right;"><b><span>&#8377; </span>'.$taxable_amount.'</b></td><td style="text-align: right;"><b><span>&#8377; </span>'.$total_csgt.'</b></td><td style="text-align: right;"><b><span>&#8377; </span>'.$total_sgst.'</b></td><td style="text-align: right;"><b><span>&#8377; </span>'.$total_cess.'</b></td></tr></table></td><td width="55%"><table width="100%"><tr><td style="text-align: right;width: 44%;"><b>Bill Value</b></td><td style="text-align: center;width: 3%;"><b>:</b></td><td style="text-align: left;width: 53%;"><b><span>&#8377; </span>'.number_format($total_amount,2).'</b></td></tr><tr><td style="text-align: right;width: 44%;"><b>Net Discount</b></td><td style="text-align: center;width: 3%;"><b>:</b></td><td style="text-align: left;width: 53%;"><b><span>&#8377; </span>'.number_format($total_discount,2).'</b></td></tr><tr><td style="text-align: right;width: 44%;"><b>Net payable</b></td><td style="text-align: center;width: 3%;"><b>:</b></td><td style="text-align: left;width: 53%;"><b><span>&#8377; </span>'.number_format($total_payable,2).'</b></td></tr></table></td></tr></table></td>';               
                    }else{
                        $data   .= '<table width="100%" style="padding: 23px;padding-left: 16px;"><tr style="margin-top: 10px;"><td width="45%"><table width="100%" style="padding: 5px 0px;"><tr><td style="width: 21%; padding-bottom: 10px; text-align: right;"><b>GST Summary</b></td></tr><tr><td width="20%" style="text-align: right;"><b>Description</b></td><td width="10%" style="text-align:right;"><b>Taxable</b></td><td width="10%" style="text-align: right;"><b>IGST</b></td></tr><tr><td style="text-align: right;">'.$tax_name.'</td><td style="text-align: right;">'.$taxable.'</td><td style="text-align: right;">'.$tax_igst.'</td></tr><tr><td style="text-align: right;"><b>Total</b></td><td style="text-align: right;"><b><span>&#8377; </span>'.$taxable_amount.'</b></td><td style="text-align: right;"><b><span>&#8377; </span>'.$total_igst.'</b></td></tr></table></td><td width="55%"><table width="100%"><tr><td style="text-align: right;width: 44%;"><b>Bill Value</b></td><td style="text-align: center;width: 3%;"><b>:</b></td><td style="text-align: left;width: 53%;"><b><span>&#8377; </span>'.number_format($total_amount,2).'</b></td></tr><tr><td style="text-align: right;width: 44%;"><b>Net Discount</b></td><td style="text-align: center;width: 3%;"><b>:</b></td><td style="text-align: left;width: 53%;"><b><span>&#8377; </span>'.number_format($total_discount,2).'</b></td></tr><tr><td style="text-align: right;width: 44%;"><b>Net payable</b></td><td style="text-align: center;width: 3%;"><b>:</b></td><td style="text-align: left;width: 53%;"><b><span>&#8377; </span>'.number_format($total_payable,2).'</b></td></tr></table></td></tr></table></td>';         
                    }
                }
                else{
                    $data   .= '';
                }
                $data   .= '<table width = "100%">';
                $data .= '<tr><td width="60%" style="padding: 10px;"><table><tr><td style="padding-bottom: 10px;"><b>Terms and Conditions:</b></td></tr>';
                if(empty($terms_conditions)){
                    $data .= '';
                }else{
                    foreach($terms_conditions as $term){
                       $data .= '<tr width="100%"><td class="terms-spacing" style="font-size: 13px;">'.$term.'</td></tr>';
                    }
                }
                $data .= '</table>';
                $data .='<td style="width: 40%; text-align: left; font-style: italic;"><table width="100%"><tbody><tr><td style="padding-bottom: 10px;"><b>BRING YOUR OWN BAG</b></td></tr><tr><td>We at LOCALLS support to Global Environment <br> and do not provide any type of plastic or paper bags. <br> So, request you to bring your own bag during the shopping <br><br><span style="font-size: 13px; font-style:normal;">THANK YOU FOR SHOPPING WITH LOCALLS</span></td></tr></tbody></table></td></tr>';
                }else{
                    $data   .= '<table width = "100%" style="padding-bottom: 300px;">';
                    $data .= '<tr><td width="60%" style="padding: 10px;"><table><tr><td style="padding-bottom: 10px;"><b>Terms and Conditions:</b></td></tr>';
                    if(empty($terms_conditions)){
                        $data .= '';
                    }else{
                        foreach($terms_conditions as $term){
                           $data .= '<tr width="100%"><td class="terms-spacing" style="font-size: 13px;">'.$term.'</td></tr>';
                        }
                    }
                    $data .= '</table>';
                    $data .='<td style="width: 40%; text-align: left; font-style: italic;"><table width="100%"><tbody><tr><td style="padding-bottom: 10px;"><b>BRING YOUR OWN BAG</b></td></tr><tr><td>We at LOCALLS support to Global Environment <br> and do not provide any type of plastic or paper bags. <br> So, request you to bring your own bag during the shopping <br><br><span style="font-size: 13px; font-style:normal;">THANK YOU FOR SHOPPING WITH LOCALLS</span></td></tr></tbody></table></td></tr>';
                }
                // $data .= '</table>';
                        
            if($totalpage > 1){
                $data .= '<br><hr>';
            }
        }
        // dd($data);
        $return = array('status'=>'success','style'=>$style,'html'=>$data) ;
        return $return;
    }       

        public function A5_print_html_page($request){

            $v_id       = $request->v_id;
            $store_id   = $request->store_id; 
            $c_id       = $request->c_id;
            $order_id   = $request->order_id;
            $product_data= [];
            $gst_list    = [];
            $final_gst   = [];
            $detatch_gst = [];
            $rounded     = 0;
            $data    = '';
            $terms_conditions =  array('');
            $invoice_title = 'Retail Invoice';
            if($request->v_id == 126){
                $style = "<style>*{padding:0;margin:0;box-sizing:border-box;-webkit-border-vertical-spacing:0;-webkit-border-horizontal-spacing:0;font-size:14px}.print_invoice_table_afive{outline: 1px #000 solid;}.print_invoice_table_afive thead tr th{ color: #000; padding: 5px;}.print_invoice_table_afive thead tr th, .print_invoice_table_afive tbody tr td{ padding: 5px; border-right: 1px #000 solid; border-bottom: 1px #000 solid; text-align: left;} .print_invoice_table_afive tbody tr td:last-child{border-right: none !important;} .print_invoice_table_afive tbody tr td pre{min-height:29px;white-space:normal;word-wrap:break-word; font-size: 11px;max-height: 29px;overflow:hidden;line-height: 1.5;} .print_invoice_table_afive .no-border{border-bottom: none !important; }.pmode tr:last-child td{border-bottom: none;}</style>";
                
            }else{
                $style = "<style>*{padding:0;margin:0;box-sizing:border-box;-webkit-border-vertical-spacing:0;-webkit-border-horizontal-spacing:0;font-size:14px}.print_invoice_table_afive{border: 1px #000 solid;border-bottom:none;}.print_invoice_table_afive thead tr th{ color: #000; padding: 5px;}.print_invoice_table_afive thead tr th, .print_invoice_table_afive tbody tr td{ padding: 5px; border-right: 1px #000 solid; border-bottom: 1px #000 solid; text-align: left;} .print_invoice_table_afive tbody tr td:last-child{border-right: none !important;} .print_invoice_table_afive tbody tr td pre{ font-family: Times New Roman; min-height:29px;white-space:normal;word-wrap:break-word; font-size: 11px;max-height: 29px;overflow:hidden;line-height: 1.5;} .print_invoice_table_afive .no-border{border-bottom: none !important; }.pmode tr:last-child td{border-bottom: none;}</style>";
            }

            $printArray  = array();
            $store         = Store::find($store_id);
            $order_details = Invoice::where('invoice_id', $order_id)->first();
            $order_invoice_id = '';
            if(isset($order_details->invoice_id)){
                $order_invoice_id = $order_details->invoice_id;
            }else{
                $order_invoice_id = '';
            }
            $einvoice = EinvoiceDetails::where('invoice_id',$order_invoice_id)->where('status','Success')->first();
            $qrImage = '';
            if($einvoice && !empty($einvoice->signed_qr_code)){
              
               $qrImage      = $this->generateQRCode(['content'=>$einvoice->signed_qr_code]);
                //$qrImage      = $einvoice->qrcode_image_path;
            }
            $order_id = '';
            if(isset($order_details->id)){
                $order_id = $order_details->id;
            }else{
                $order_id = '';
            }
            $order_vid = '';
            if(isset($order_details->v_id)){
                $order_vid = $order_details->v_id;
            }else{
                $order_vid = '';
            }
            $order_storeid = '';
            if(isset($order_details->store_id)){
                $order_storeid = $order_details->store_id;
            }else{
                $order_storeid = '';
            }
            $order_userid = '';
            if(isset($order_details->user_id)){
                $order_userid = $order_details->user_id;
            }else{
                $order_userid = '';
            }
            $cart_q = InvoiceDetails::where('t_order_id', $order_id)->where('v_id', $order_vid)->where('store_id', $order_storeid)->where('weight_flag','0')->where('user_id', $order_userid)->sum('qty');
            $cart_qt = InvoiceDetails::where('t_order_id', $order_id)->where('v_id', $order_vid)->where('store_id', $order_storeid)->where('weight_flag','1')->where('user_id', $order_userid)->count('qty');
            $cart_qty = $cart_q + $cart_qt;
            $total_amount = InvoiceDetails::where('t_order_id', $order_id)->where('v_id', $order_vid)->where('store_id', $order_storeid)->where('user_id', $order_userid)->sum('total');
            // dd($total_amount);
            $count_cart_product = InvoiceDetails::where('t_order_id', $order_id)->where('v_id', $order_vid)->where('store_id', $order_storeid)->where('user_id', $order_userid)->count();
            $order_transaction_type = '';
            if(isset($order_details->transaction_type)){
                $order_transaction_type = $order_details->transaction_type;
            }else{
                $order_transaction_type = '';
            }
            if($order_transaction_type == 'return'){
                $invoice_title     = 'Credit Note';
            }

            $startitem   = 0;
            $getItem     = 8;
            $countitem   = $count_cart_product;
            $totalpage   = ceil($count_cart_product/$getItem);
            $sr          = 1;

            for($i=0;$i < $totalpage ; $i++) {
               
                $cart_product = InvoiceDetails::where('t_order_id', $order_id)->where('v_id', $order_vid)->where('store_id', $order_storeid)->where('user_id', $order_userid)->skip($startitem)->take(8)->get();
   
               $startitem  = $startitem+$getItem;
               $startitem  = $startitem;
            
             
            $customer_address = '';
            if(isset($order_details->user->address->address1)){
                $customer_address .= $order_details->user->address->address1;
            }
            if(isset($order_details->user->address->address2)){
                $customer_address .= $order_details->user->address->address2;
            }

            $count = 1;
            $gst_tax = 0;
            $gst_listing = [];
            $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
            //dd($gst_list);
            $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
            $cgst = $sgst = $cess = 0 ;
            // dd($final_gst);
 
            $roundoff = explode(".", $total_amount);
            $roundoffamt = 0;
            // dd($roundoff);
            if (!isset($roundoff[1])) {
                $roundoff[1] = 0;
            }
            $order_details_total = '';
            if(isset($order_details->total)){
                $order_details_total = $order_details->total;
            }else{
                $order_details_total = '';
            }
            if ($roundoff[1] >= 50) {
                $roundoffamt = $order_details_total - $total_amount;
                $roundoffamt = -$roundoffamt;
            } else if ($roundoff[1] <= 49) {
                $roundoffamt = $total_amount - $order_details_total;
                $roundoffamt = -$roundoffamt;
            }
            $bilLogo      = '';
            $bill_logo_id = 5;
            $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
            if($vendorImage)
            {
                $bilLogo = env('ADMIN_URL').$vendorImage->path;
            }

            $bottombilLogo      = '';
            $bottom_bill_logo_id = 11;
            $bottomvendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bottom_bill_logo_id)->where('status',1)->first();
            if($bottomvendorImage)
            {
                $bottombilLogo = env('ADMIN_URL').$bottomvendorImage->path;
            }

            $order_details_payvia = '';
            if(isset($order_details->payvia)){
                $order_details_payvia = $order_details->payvia;
            }else{
                $order_details_payvia = '';
            }
            $payments  = $order_details_payvia;
            $cash_collected = 0;  
            $cash_return    = 0;
            $net_payable        = $total_amount;

            // dd($payments);
            $mop_list = [];
            foreach ($payments as $payment) {
                if($payment->status == 'success'){
                    $paymentdata = Mop::select('name')->where('code',$payment->method)->first();
                    $mopname = '';
                    if(isset($paymentdata->name)){
                        $mopname = $paymentdata->name;
                    }else{
                        $mopname = '';
                    }
                    if ($payment->method == 'cash') {
                        $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
                        if($order_transaction_type == 'return'){
                            $mop_list[] = [ 'mode' => $mopname, 'amount' => $payment->amount ];
                        }else{
                            $mop_list[] = [ 'mode' => $mopname, 'amount' => $payment->cash_collected-$cashReturn ];
                        }
                    } else {
                        $mop_list[] = [ 'mode' => $mopname, 'amount' => $payment->amount ];
                    }
                    if ($payment->method == 'cash') {
                        $cash_collected += (float) $payment->cash_collected;
                        $cash_return += (float) $payment->cash_return;
                    }
                    /*Voucher Start*/
                    if($payment->method == 'voucher_credit'){
                        $voucher[] = $payment->amount;
                        $net_payable = $net_payable-$payment->amount;
                    }
                }else{
                    // $mop_list =;
                }
            }
            // $paymentdata = Mop::select('name')->where('code',$paymentmethod)->get();
            // dd($mop_list);
            $order_details_discount = '';
            $order_details_manual_discount = '';
            $order_details_bill_buster_discount = '';
            if(isset($order_details->discount)){
                $order_details_discount = $order_details->discount;
            }else{
                $order_details_discount = '';
            }
            if(isset($order_details->manual_discount)){
                $order_details_manual_discount = $order_details->manual_discount;
            }else{
                $order_details_manual_discount = '';
            }
            if(isset($order_details->bill_buster_discount)){
                $order_details_bill_buster_discount = $order_details->bill_buster_discount;
            }else{
                $order_details_bill_buster_discount = '';
            }
            $customer_paid = $cash_collected;
            $balance_refund= $cash_return;
            $total_discount = (float)$order_details_discount+(float)$order_details_manual_discount+(float)$order_details_bill_buster_discount;
            // $carts=DB::table('invoices')->join('orders','invoices.ref_order_id','=','orders.order_id')
            //     ->join('invoice_details', function($join) {
            //     $join->on('invoices.id','=','invoice_details.t_order_id');
            //     $join->on('invoices.user_id','=','invoice_details.user_id');
            //     $join->on('invoices.store_id','=','invoice_details.store_id');
            //     $join->on('invoices.v_id', 'invoice_details.v_id');
            //     })
            //     ->where('invoices.invoice_id',$order_details->invoice_id)
            //     ->where('invoices.v_id', $order_details->v_id)
            //     ->get(); 
            $order_details_invoice_id = '';
            if(isset($order_details->invoice_id)){
                $order_details_invoice_id = $order_details->invoice_id;
            }else{
                $order_details_invoice_id = '';
            }
            $refinvoicedetails = DB::table('invoices')->select('orders.ref_order_id')->join('orders','invoices.ref_order_id','=','orders.order_id')
                ->where('invoices.invoice_id',$order_details_invoice_id)
                ->where('invoices.v_id', $order_vid)
                ->first(); 

                $ref_invoice = DB::table('orders')->select('invoices.date')->join('invoices','invoices.invoice_id','=','orders.ref_order_id')
                ->where('invoices.invoice_id',$refinvoicedetails->ref_order_id)
                ->where('invoices.v_id', $order_vid)
                ->first(); 

                // dd($refinvoicedetails->ref_order_id);
            $terms_conditions =  array('1. Products once sold cannot be returned back.','2. Products bought can be exchanged within 15 days of invoice only and in proper saleable condition along with the original copy of bill','3. Products bought on discount or offer cannot be exchanged with full price products.' );
                // $terms =  Terms::where('v_id',$v_id)->get();
                //     $terms_condition = json_decode($terms);
                //     foreach ($terms_condition as $value) {
                //         $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                //     }
            // dd($order_details);
            ########################
            ####### Print Start ####
            ########################
            
            
            //$data = '<body style="padding: 20px;">';
            
            //$data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"><table class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';
            /* $data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"> <table width="10%"><tr><td><img src="'.$bilLogo.'" alt="" height="80px"></td></tr>
           </table><table width="80%" class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';*/
           
        $data  .= '<table class="" width="92%" style="margin-top: 38px; margin-bottom: 0px; margin-left: auto; margin-right: auto;">';
            
        if($order_transaction_type == 'sales'){
            
                $data .= '<tr><td colspan="2" align="center" style="font-size: 16px; font-weight: 600; padding-bottom: 13px;">Tax Invoice</td></tr>';
            
        }else{
            $data .= '<tr><td align="center" style="font-size: 16px; font-weight: 600; padding-bottom: 13px;">Credit Note</td></tr>';
        }
        
        $data  .= '<tr><td><table width="100%" style="padding-left: 5px; padding-right: 5px; position: relative; top: 30px; left: 50%; transform: translateX(-50%);">';
        
        if($request->v_id == 126){
            $data   .=  '<tr>
                        <td width="30%">';
                    
        }else{
            $data  .=  '<tr style="vertical-align="top">
                        <td width="30%">
            <table ><tr><td><img src="'.$bilLogo.'" alt="" height="auto" width="50%">
                        </td>
                        </tr>
                        </table>';
        }
        $data   .= '</td>
                    <td width="40%" align="center">';
        if($request->v_id == 126){
            $data .= '<table width="100%" class="top-head" bgcolor="#fff" align="left" style=" text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;"><tbody><tr style="font-size: 16px; padding: 5px;"><td align="left"></td></tr></tbody></table>';    
        }else{
            $data  .=  '<table width="100%" style="text-align: center;"><tr><tr><td style="padding: 0px; font-size: 13px;">'.$store->name.'</td></tr><tr><td style="padding: 0px; font-size: 13px;">'.$store->address1.'</td></tr>';
                
            if($store->address2){
                $data  .=  '<tr><td style="padding: 0px; font-size: 13px;">'.$store->address2.'</td></tr>';        
            }
            $data  .=  '<tr><td style="padding: 0px; font-size: 13px;">'.$store->location.','.$store->pincode.','.$store->state.'</td></tr>';     
            $data  .=  '<tr><td style="padding: 0px; font-size: 13px;">Email: '.$store->email.'</td></tr>';
            $data  .=  '<tr><td style="padding: 0px; font-size: 13px;">Tel: '.$store->contact_number.'</td></tr>';        
            if($store->gst){
                $data  .=  '<tr><td style="padding: 0px; font-size: 13px;">GSTIN: '.$store->gst.'</td></tr>';        
            }
        }
        $data .= '</table></td>';
        if($request->v_id == 126){
            $data .= '<td width="30%"><table style="text-align: left;" width="100%"><tr>';
        }else{
            $data .= '<td width="30%"><table style="text-align: right;" width="100%"><tr>';
        }
        if($order_transaction_type == 'sales'){
            $data .= '<td style="padding: 0px; font-size: 13px;">Invoice:- '.$order_details->invoice_id.'</td>';
        }else{
           $data .= '<td style="padding: 0px; font-size: 13px;">Credit Note No:- '.$order_details->invoice_id.'</td>';
        }
        $data .= '</tr><tr><td style="padding: 0px; font-size: 13px;">Date:- '.date('d-M-Y', strtotime($order_details->created_at)).'</td></tr>';
    
        $data .= '<tr><td style="padding: 0px; font-size: 13px;">Name:- '.@$order_details->user->first_name.' '.@$order_details->user->last_name.'</td></tr><tr><td style="padding: 0px; font-size: 13px;">Phone No.:- '.$order_details->user->mobile.'</td></tr><tr><td style="padding: 0px 0px 0px 0px; font-size: 13px;">GSTIN:- '.@$order_details->cust_gstin.'</td></tr>';    
        if($refinvoicedetails->ref_order_id){
            if($request->v_id == 126){
                $data .= '<tr><td style="padding: 0px 0px 0px 0px; font-size: 13px;">Order No and Due Date:- '.$order_details->remark.'</td></tr><tr><td style="padding: 0px 0px 0px 0px; font-size: 13px;">Original Invoice No:- '.$refinvoicedetails->ref_order_id.'</td></tr><tr><td style="padding: 0px 0px 12px 0px; font-size: 13px;">Original Invoice Date:- '.date('d-M-Y', strtotime($ref_invoice->date)).'</td></tr>';
                
            }else{
                $data .= '<tr><td style="padding: 0px 0px 0px 0px; font-size: 13px;">Against Invoice No:- '.$refinvoicedetails->ref_order_id.'</td></tr><tr><td style="padding: 0px 0px 12px 0px; font-size: 13px;">Original Invoice Date:- '.date('d-M-Y', strtotime($ref_invoice->date)).'</td></tr>';
            }
        }else{
            if($request->v_id == 126){
                $data .= '<tr><td style="padding: 0px 0px 12px 0px; font-size: 13px;">Order No and Due Date:- '.$order_details->remark.'</td></tr>';
            }else{
                $data .= '';
            }
        }
        $data .= '</table></td></tr></table>';

             
            // $data  .= '<tr><td><table style="width: 100%; color: #fff; padding: 5px; text-align: center; border: 1px #000 solid; border-left: none; border-right: none;"><tr><td valign="top" style="line-height: 1.5;  color: #000;"><b>'.$invoice_title.'</b></td></tr></table></td></tr>';
            // $data  .=  '<tr>
            // <td>
            // <table style="width: 100%; color: #fff; padding: 5px;">';
            // $data  .=  '<tr>
            // <td valign="top" style="line-height: 1;  color: #000; font-size: 12px;text-align:left;">Customer 
            // <br>
            // <b>'.@$order_details->user->first_name.''.@$order_details->user->last_name.'</b>
            // <br>'.@$order_details->user->mobile.'
            // <br>'.@$order_details->user->gstin.'
            // <br>'.$customer_address.'
            // </td>';

            //<br>'.@$order_details->user->mobile.'
            // $data  .= '<td valign="top" style="line-height: 1.5; color: #000; font-size: 14px;" align="right">Date : '.date('d-M-Y', strtotime($order_details->created_at)).'
            // <br>Invoice No: '.$order_details->invoice_id.'</td>
            // </tr></table></td></tr>';
            // $data  .= '<tr><td valign="top" style="line-height: 1.5;  color: #000">'.@$order_details->user->mobile.'</td>';
            // $data .=  '<td valign="top" style="line-height: 2.1; color: #000" align="right">Invoice No:<span style="color: #000;">'.$order_details->invoice_id.'</span></td></tr></table></td></tr>';
            /*$data  .= '<tr><td></td><td valign="top" style="line-height: 2.1; color: #000" align="right">Cashier <span style="color: #000;">'.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</span></td></tr></table></td></tr>';*/
        $data  .= '<table class="" width="70%><tr><td><div  style="height: 400px; overflow: hidden; border-top: 2px #000 solid; border-bottom: 2px #000 solid; "   >';
        $data .= '<table height="100%" width="100%" class="print_invoice_table_afive" bgcolor="#fff" style="width: 100%; color: #000;">';
            
        $data  .= '<thead ><tr align="left" style="white-space: nowrap;">
                    <th width="5%" style=" font-size: 12px; text-align: center;" >S.No.</th>';
        $data .= '<th width="15%" valign="center"  style="font-size: 12px; text-align: center;" >Stock No.</th>';                
        $data .= '<th width="15%"valign="center"  style=" font-size: 12px; text-align: center;" >Item Description</th>';                
        $data .= '<th width="8%"valign="center"  style=" font-size: 12px; text-align: center;" >HSN Code</th>';
        $data .= '<th width="10%"valign="center"  style=" font-size: 12px; text-align: center;" >MRP</th>';                
        $data .= '<th width="6%"valign="center"  style=" font-size: 12px; text-align: center;" >Qty.</th>';                
        $data .= '<th width="12%"valign="center"  style=" font-size: 12px; text-align: center;" >Total</th>';                
        $data .= '<th width="5%"valign="center"  style=" font-size: 12px; text-align: center;" >Discount</th>
                  <th width="6%"valign="center"  style=" font-size: 12px; text-align: center;" >GST %</td>';
        $data .= '<th width="6%"valign="center"  style=" font-size: 12px; text-align: center;" >GST</td>';                
        $data .= '<th width="12%"valign="center"  style=" font-size: 12px; border-right: none; text-align: center;" >Item Net Value </th></tr></thead><tbody>';   
        $srp= '';
        $barcode = '';
        $hsn ='';
        $item_name ='';
        $qty  = '';
        $unit = '';
        $mrp  = '';
        $disc = '';
        $taxp = '';
        $taxb = '';

        $taxable_amount = 0;
        $total_csgt     = 0;
        $total_sgst     = 0;
        $total_cess     = 0;
        $total_mrp      = 0;
        $total_gst            = 0;
        $total_net_value       = 0;
        $total_discount    = 0;
        $total_qty         = 0;
        $srp            = '';
        $barcode        = '';
        $hsn            = '';
        $qty            = '';
        $unit           = '';
        $mrp            = '';
        $disc           = '';
        $taxp           = '';
        $taxb           = '';
        $varient        = '';
        $GST            = '';
        $NetValue       = '';
        foreach ($cart_product as $key => $value) {

            $remark = isset($value->remark)?' -'.$value->remark:'';
            $tdata    = json_decode($value->tdata);
            $cdata    = json_decode($value->section_target_offers);
            if(isset($cdata->sku_code)){
                $tempcdata = $cdata->sku_code;
            }else{
                $tempcdata = '';
            }
            $style_data = DB::table('vendor_sku_flat_table')->select('cat_name_1')->where('sku_code', $tempcdata)->first();

            $itemLevelmanualDiscount=0;
            if($value->item_level_manual_discount!=null){
                $iLmd = json_decode($value->item_level_manual_discount);
                $itemLevelmanualDiscount= (float)$iLmd->discount;
            }
            $discount = $value->discount+$value->manual_discount + $value->bill_buster_discount+$itemLevelmanualDiscount;

            $taxper   = $tdata->cgst + $tdata->sgst + $tdata->igst;
            $taxable_amount += $tdata->taxable;
            $total_csgt  += $tdata->cgstamt;
            $total_sgst  += $tdata->sgstamt;
            $total_cess  += $tdata->cessamt;
            $total_discount += $discount;

            $totalmrp = $value->unit_mrp * $value->qty;
            if($tdata->tax_type == 'INC'){
                $taxvalue = $totalmrp - $discount;
                $taxdata = 1 + $taxper/100;
                $taxableamt = $taxvalue/$taxdata;
                $gst = $taxableamt * $taxper/100;
            }else{
                $gst = ($totalmrp - $discount) * $taxper/100;
            }
            $total_gst += $gst;
            if($tdata->tax_type == 'INC'){ 
                $net_value = $totalmrp - $discount;
            }
            else{
                $net_value = ($totalmrp - $discount) + $gst;
            }
            $total_net_value += $net_value;

            $total_qty  += $value->qty;

            // dd($total_qty);

            $total_mrp  += $totalmrp; 

            $srp       .= '<pre style="font-size:12px;">'.$sr.'</pre>';
            $barcode   .= '<pre style="font-size:12px;">'.$value->barcode.'</pre>';
            $hsn       .= '<pre style="font-size:12px;">'.$tdata->hsn.'</pre>';
            $qty       .= '<pre style="font-size:12px;">'.$value->qty.'</pre>';
            $unit      .= '<pre style="font-size:12px;">PCS</pre>';
            $mrp       .= '<pre style="font-size:12px;">'.$value->unit_mrp.'</pre>';
            $disc      .= '<pre style="font-size:12px;">'.number_format($discount,2).'</pre>';
            $taxp      .= '<pre style="font-size:12px;">'.number_format($taxper,2).'</pre>';
            $taxb      .= '<pre style="font-size:12px;">'.number_format($totalmrp,2).'</pre>';
            $GST       .= '<pre style="font-size:12px;">'.number_format($gst,2).'</pre>';
            $NetValue  .= '<pre style="font-size:12px;">'.number_format($net_value,2).'</pre>';
            $tempVarient = isset($style_data->cat_name_1) ? $style_data->cat_name_1 : ' ';
            $varient   .= '<pre style="font-size:12px;">'.$tempVarient.'</pre>';
            $sr++;
        }
        $data   .= '<tr align="left" style="height: 270px;">';   
        $data   .= '<td valign="top" style="text-align: center;">'.$srp.'</td>';
        $data   .= '<td valign="top" style="text-align: center;">'.$barcode.'</td>';        
        $data   .= '<td valign="top" style="text-align: center;">'.$varient.'</td>';
        $data   .= '<td valign="top" style="text-align: center;">'.$hsn.'</td>';
        $data   .= '<td valign="top" style="text-align: right;">'.$mrp.'</td>';
        $data   .= '<td valign="top" style="text-align: center;">'.$qty.'</td>';
        $data   .= '<td valign="top" style="text-align: right;">'.$taxb.'</td>';
        $data   .= '<td valign="top" style="text-align: right;">'.$disc.'</td>';
        $data   .= '<td valign="top" style="text-align: center;">'.$taxp.'</td>';
        $data   .= '<td valign="top" style="text-align: center;">'.$GST.'</td>';        
        $data   .= '<td valign="top" style="text-align: right;">'.$NetValue.'</td></tr>';
            // $total_csgt = round($total_csgt,2);
            // $total_sgst = round($total_sgst,2);
            // $total_cess = round($total_cess,2);

        if($totalpage-1 == $i){
            // dd($total_qty);
            $taxable_amount   = 0;
            $total_csgt       = 0;
            $total_sgst       = 0;
            $total_cess       = 0;
            $sub_total        = 0;
            $total_mrp        = 0;
            $total_gst        = 0;
            $total_net_value  = 0;
            $total_discount   = 0;
            $total_qty        = 0;
            $invoiceData  = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->get();
            foreach($invoiceData as $invdata){
                $Ntdata    = json_decode($invdata->tdata);
                $itemLevelmanualDiscount=0;
                if($invdata->item_level_manual_discount!=null){
                 $iLmd = json_decode($invdata->item_level_manual_discount);
                 $itemLevelmanualDiscount = (float)$iLmd->discount;
                }
                $discount = $invdata->discount+$invdata->manual_discount + $invdata->bill_buster_discount+$itemLevelmanualDiscount;
                $taxper          = $Ntdata->cgst + $Ntdata->sgst + $Ntdata->igst;
                // $taxable_amount += $Ntdata->taxable;
                $total_csgt     += $Ntdata->cgstamt;
                $total_sgst     += $Ntdata->sgstamt;
                $total_cess     += $Ntdata->cessamt;
                $sub_total      += $invdata->subtotal;

                $total_discount += $discount;

                $totalmrp = $invdata->unit_mrp * $invdata->qty;

                if($Ntdata->tax_type == 'INC'){
                    $taxvalue = $totalmrp - $discount;
                    $taxdata = 1 + $taxper/100;
                    $taxableamt = $taxvalue/$taxdata;
                    $gst = $taxableamt * $taxper/100;
                }else{
                    $gst = ($totalmrp - $discount) * $taxper/100;
                }

                $total_gst += $gst;
                if($Ntdata->tax_type == 'INC'){ 
                    $net_value = $totalmrp - $discount;
                }
                else{
                    $net_value = ($totalmrp - $discount) + $gst;
                }
                // dd($net_value);
                $total_net_value += $net_value;
                $taxableAmt = $total_net_value - $total_gst;
                $taxable_amount = $taxableAmt;
                $total_qty  += $invdata->qty;
                $total_mrp  += $totalmrp; 
            }
            
            $data .= '<tr align="left"><td colspan="5" valign="top" style="font-size: 12px;">Total</td>';                
            $data .= '<td colspan="1" valign="top" style="font-size: 12px; text-align: center;">'.$total_qty.'</td>';
            $data .= '<td colspan="1" valign="top" style="font-size: 12px; border-bottom: none !important;"></td>';
            $data .= '<td colspan="2" valign="top" style="font-size: 12px;">Total Bill Value</td>';
            $data .= '<td colspan="2" valign="top" style="font-size: 12px; text-align: right;"><b><span style="font-size: 13px;">&#8377;</span>&nbsp;'.number_format($total_mrp,2).'</b></td></tr>';
            $data .= '<tr><td colspan="6" valign="top" style="font-size: 12px;">In Rupees:- '.ucfirst(numberTowords(round($total_net_value))).' Only</td>';   
            $data .= '<td colspan="1" valign="top" style="font-size: 12px; border-bottom: none !important;"></td>';
            $data .= '<td colspan="2" valign="top" style="font-size: 12px;">Total Discount</td>';
            $data .= '<td colspan="2" valign="top" style="font-size: 12px; text-align: right;"><b><span style="font-size: 13px;">&#8377;</span>&nbsp;'.number_format($total_discount,2).'</b></td></tr>';
            if($request->v_id == 126){
                if($order_transaction_type == 'sales'){
                    $data .= '<tr><td colspan="3" valign="top" style="font-size: 12px;">Payment Mode</td>';
                    $data .= '<td colspan="3" valign="top" style="font-size: 12px; text-align: right;">Amount Paid</td>';
                }else{
                    $data .= '<tr><td colspan="3" valign="top" style="font-size: 12px;">Refund Mode</td>';
                    $data .= '<td colspan="3" valign="top" style="font-size: 12px; text-align: right;">Amount Refunded</td>';
                }
            }else{
                $data .= '<tr><td colspan="3" valign="top" style="font-size: 12px;">Payment Mode</td>';
                
                    $data .= '<td colspan="3" valign="top" style="font-size: 12px; text-align: right;">Amount Paid</td>';
                
            }
            $data .= '<td colspan="1" valign="top" style=" border-bottom: none !important;"></td>';
            $data .= '<td colspan="2" valign="top" style="font-size: 12px;">Taxable Value</td>';
            
            $data .= '<td colspan="2" valign="top" style="font-size: 12px; text-align: right;"><b><span style="font-size: 13px;">&#8377;</span>&nbsp;'.number_format($taxable_amount,2).'</b></td></tr>';
            $data .= '<tr><td colspan="3" style="padding: 0px;"><table width="100%" class="pmode">';
            if($request->v_id == 126){
                if($order_transaction_type == 'sales'){
                    foreach($mop_list as $mop){
                        $data  .= '<tr>
                            <td valign="top" style="font-size: 12px; text-transform: capitalize;">'.$mop['mode'].'</td>
                            <td valign="top" style="font-size: 12px; text-align: right;">'.$mop['amount'].'</td></tr>';
                    }
                }else{
                    foreach($mop_list as $mop){
                        $data  .= '<tr>
                            <td valign="top" style="font-size: 12px; text-transform: capitalize;">'.$mop['mode'].'</td></tr>';
                    }
                }
            }else{
                foreach($mop_list as $mop){
                    $data  .= '<tr>
                        <td valign="top" style="font-size: 12px; text-transform: capitalize;">'.$mop['mode'].'</td>
                        <td valign="top" style="font-size: 12px; text-align: right;">'.$mop['amount'].'</td></tr>';
                }
            }
            $data .= '</table></td>';
            if($request->v_id == 126){
                if($order_transaction_type == 'sales'){
                    $data .= '<td colspan="3" valign="top" style="font-size: 12px; text-align: right;"><b>'.number_format($total_net_value,2).'</b></td>';
                }else{
                    $data .= '<td colspan="3" valign="top" style="font-size: 12px; text-align: right;"><b>'.number_format(round($total_net_value,2)).'</b></td>';
                }
            }else{
                $data .= '<td colspan="3" valign="top" style="font-size: 12px; text-align: right;"><b>'.number_format($total_net_value,2).'</b></td>';
            }
            $data .= '<td colspan="1" valign="top" style=" border-bottom: none !important;"></td>';
            $data .= '<td colspan="2" valign="top" style="font-size: 12px;">GST</td>';
            $data .= '<td colspan="2" valign="top" style="font-size: 12px; text-align: right;"><b><span style="font-size: 13px;">&#8377;</span>&nbsp;'.number_format($total_gst,2).'</b></td></tr>';    
            $data .= '<tr><td colspan="7" valign="top" style="font-size: 12px; border-bottom: none !important;">Attended by: '.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</td>';
            // $data .= '<tr style="height: 40px;"><td colspan="11" valign="top" style="font-size: 12px; border-bottom: none !important;">Bill Remark: '.$order_details->remark.'</td></tr>';
            // $data   .= '</table></td></tr></div>';
            
            }else{
                $data .= '';
            }
            // $data   .= '<tr class="print_invoice_terms"><td><table style="width: 100%; padding: 10px 0; color: #000;">
            //     <tr width="100%">
            //         <td align="center">For Terms and Conditions Please turn over</td >
            //     </tr>';
            //  foreach($terms_conditions as $term){
            //     $data .= '<tr width="100%"><td style="padding-bottom: 5px; text-decoration: dotted;">&bull;'.$term.'</td></tr>';
            //  }
            // $data    .= '</table></td></tr>';
            // $data    .= '<tr class="print_invoice_last"><td><table bgcolor="#fff" width="100%" style="color: #000000; padding: 5px;"><tr><td width="3%">For:</td><td colspan="1"><b>'.$store->name.'</b></td></tr></table><table width="100%" style="color: #000000; padding-top: 20px !important; padding: 5px;"><tr><td></td></tr><tr class="print_store_sign"><td width="50%">Authorised Signatory</td><td width="35%" align="right">Prepared by:</td><td align="right">&nbsp;'.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</td></tr></table></td></tr></table>';
             
        }
        $return = array('status'=>'success','style'=>$style,'html'=>$data) ;
        return $return;
    }

     public function A5_PurePlay_print_html_page($request){

        $v_id       = $request->v_id;
        $store_id   = $request->store_id; 
        $c_id       = $request->c_id;
        $order_id   = $request->order_id;
        $product_data= [];
        $gst_list    = [];
        $final_gst   = [];
        $detatch_gst = [];
        $rounded     = 0;
        $data    = '';
        $terms_conditions =  array('');
        $invoice_title = 'Retail Invoice';
        
        $style = "<style>
        *{padding:0;margin:0;box-sizing:border-box;-webkit-border-vertical-spacing:0;-webkit-border-horizontal-spacing:0;font-size:14px} body{font-family: serif;}.print_invoice_table_afive{border: 1px #000 solid;border-bottom:none;}.print_invoice_table_afive thead tr th{ color: #000; padding: 5px;}.print_invoice_table_afive thead tr th, .print_invoice_table_afive tbody tr td{ padding: 4px; border-right: 1px #000 solid; border-bottom: 1px #000 solid; text-align: left;} .print_invoice_table_afive tbody tr td:last-child{border-right: none !important;} .print_invoice_table_afive tbody tr td pre{ font-family: Times New Roman; min-height:auto;white-space:normal;word-wrap:break-word; font-size: 11px;max-height: 29px;overflow:hidden;line-height: 1.5;margin-bottom: 0px;} .print_invoice_table_afive .no-border{border-bottom: none !important; }.pmode tr:last-child td{border-bottom: none;}</style>";

            $printArray  = array();
            $store         = Store::find($store_id);
            $order_details = Invoice::where('invoice_id', $order_id)->first();
            $order_invoice_id = '';
            if(isset($order_details->invoice_id)){
                $order_invoice_id = $order_details->invoice_id;
            }else{
                $order_invoice_id = '';
            }
            $einvoice = EinvoiceDetails::where('invoice_id',$order_invoice_id)->where('status','Success')->first();
            $qrImage = '';
            if($einvoice && !empty($einvoice->signed_qr_code)){
              
               $qrImage      = $this->generateQRCode(['content'=>$einvoice->signed_qr_code]);
                //$qrImage      = $einvoice->qrcode_image_path;
            }
            $order_id = '';
            if(isset($order_details->id)){
                $order_id = $order_details->id;
            }else{
                $order_id = '';
            }
            $order_vid = '';
            if(isset($order_details->v_id)){
                $order_vid = $order_details->v_id;
            }else{
                $order_vid = '';
            }
            $order_storeid = '';
            if(isset($order_details->store_id)){
                $order_storeid = $order_details->store_id;
            }else{
                $order_storeid = '';
            }
            $order_userid = '';
            if(isset($order_details->user_id)){
                $order_userid = $order_details->user_id;
            }else{
                $order_userid = '';
            }
            $cart_q = InvoiceDetails::where('t_order_id', $order_id)->where('v_id', $order_vid)->where('store_id', $order_storeid)->where('weight_flag','0')->where('user_id', $order_userid)->sum('qty');
            $cart_qt = InvoiceDetails::where('t_order_id', $order_id)->where('v_id', $order_vid)->where('store_id', $order_storeid)->where('weight_flag','1')->where('user_id', $order_userid)->count('qty');
            $cart_qty = $cart_q + $cart_qt;
            $total_amount = InvoiceDetails::where('t_order_id', $order_id)->where('v_id', $order_vid)->where('store_id', $order_storeid)->where('user_id', $order_userid)->sum('total');
            // dd($total_amount);
            $count_cart_product = InvoiceDetails::where('t_order_id', $order_id)->where('v_id', $order_vid)->where('store_id', $order_storeid)->where('user_id', $order_userid)->count();
            $order_transaction_type = '';
            if(isset($order_details->transaction_type)){
                $order_transaction_type = $order_details->transaction_type;
            }else{
                $order_transaction_type = '';
            }
            if($order_transaction_type == 'return'){
                $invoice_title     = 'Credit Note';
            }

            $startitem   = 0;
            $getItem     = 16;
            $countitem   = $count_cart_product;
            $totalpage   = ceil($count_cart_product/$getItem);
           // $totalpage = 1; 
           // $countitem = 1;
           $total_page = 0;
            for($i=0;$i < $totalpage; $i++){
                if($totalpage-1== $i){

                    if($countitem >= 5 && $countitem <= 16){ 
                        $total_page = $totalpage + 1;
                    }elseif ($countitem >= 21 && $countitem <= 32) {
                        $total_page = $totalpage + 1;
                    }else{
                        $total_page = $totalpage;
                    }

                    // $countproduct = abs($countitem - 16);
                    // if($countproduct >= 5){
                    //     $total_page = $totalpage + 1;
                    // }elseif($countproduct == 0){
                    //     $total_page = $totalpage + 1;
                    // }else{
                    //     $total_page = $totalpage;
                    // }
                }
            }
            // dd($total_page);
            $sr          = 1;

            for($i=0;$i < $total_page ; $i++) {
                $cart_product = InvoiceDetails::where('t_order_id', $order_id)->where('v_id', $order_vid)->where('store_id', $order_storeid)->where('user_id', $order_userid)->skip($startitem)->take(16)->get();
   
                $startitem  = $startitem+$getItem;
                $startitem  = $startitem;

                $customer_address = '';
                if(isset($order_details->user->address->address1)){
                    $customer_address .= $order_details->user->address->address1;
                }
                if(isset($order_details->user->address->address2)){
                    $customer_address .= $order_details->user->address->address2;
                }

                $count = 1;
                $gst_tax = 0;
                $gst_listing = [];
                $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
                //dd($gst_list);
                $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
                $cgst = $sgst = $cess = 0 ;
                // dd($final_gst);
     
                $roundoff = explode(".", $total_amount);
                $roundoffamt = 0;
                // dd($roundoff);
                if (!isset($roundoff[1])) {
                    $roundoff[1] = 0;
                }
                $order_details_total = '';
                if(isset($order_details->total)){
                    $order_details_total = $order_details->total;
                }else{
                    $order_details_total = '';
                }
                if ($roundoff[1] >= 50) {
                    $roundoffamt = $order_details_total - $total_amount;
                    $roundoffamt = -$roundoffamt;
                } else if ($roundoff[1] <= 49) {
                    $roundoffamt = $total_amount - $order_details_total;
                    $roundoffamt = -$roundoffamt;
                }
                $bilLogo      = '';
                $bill_logo_id = 5;
                $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
                if($vendorImage)
                {
                    $bilLogo = env('ADMIN_URL').$vendorImage->path;
                }

                $bottombilLogo      = '';
                $bottom_bill_logo_id = 11;
                $bottomvendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bottom_bill_logo_id)->where('status',1)->first();
                if($bottomvendorImage)
                {
                    $bottombilLogo = env('ADMIN_URL').$bottomvendorImage->path;
                }

                $order_details_payvia = '';
                if(isset($order_details->payvia)){
                    $order_details_payvia = $order_details->payvia;
                }else{
                    $order_details_payvia = '';
                }
                $payments  = $order_details_payvia;
                $cash_collected = 0;  
                $cash_return    = 0;
                $net_payable        = $total_amount;
                $mop_list = [];
                foreach ($payments as $payment) {
                    if($payment->status == 'success'){
                        $paymentdata = Mop::select('name')->where('code',$payment->method)->first();
                        $mopname = '';
                        if(isset($paymentdata->name)){
                            $mopname = $paymentdata->name;
                        }else{
                            $mopname = '';
                        }
                        if ($payment->method == 'cash') {
                            $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
                            if($order_transaction_type == 'return'){
                                $mop_list[] = [ 'mode' => $mopname, 'amount' => $payment->amount ];
                            }else{
                                $mop_list[] = [ 'mode' => $mopname, 'amount' => $payment->cash_collected-$cashReturn ];
                            }
                        } else {
                            $mop_list[] = [ 'mode' => $mopname, 'amount' => $payment->amount ];
                        }
                        if ($payment->method == 'cash') {
                            $cash_collected += (float) $payment->cash_collected;
                            $cash_return += (float) $payment->cash_return;
                        }
                        /*Voucher Start*/
                        if($payment->method == 'voucher_credit'){
                            $voucher[] = $payment->amount;
                            $net_payable = $net_payable-$payment->amount;
                        }
                    }else{
                        // $mop_list =;
                    }
                }
                $order_details_discount = '';
                $order_details_manual_discount = '';
                $order_details_bill_buster_discount = '';
                if(isset($order_details->discount)){
                    $order_details_discount = $order_details->discount;
                }else{
                    $order_details_discount = '';
                }
                if(isset($order_details->manual_discount)){
                    $order_details_manual_discount = $order_details->manual_discount;
                }else{
                    $order_details_manual_discount = '';
                }
                if(isset($order_details->bill_buster_discount)){
                    $order_details_bill_buster_discount = $order_details->bill_buster_discount;
                }else{
                    $order_details_bill_buster_discount = '';
                }
                $customer_paid = $cash_collected;
                $balance_refund= $cash_return;
                $total_discount = (float)$order_details_discount+(float)$order_details_manual_discount+(float)$order_details_bill_buster_discount;
                $order_details_invoice_id = '';
                if(isset($order_details->invoice_id)){
                    $order_details_invoice_id = $order_details->invoice_id;
                }else{
                    $order_details_invoice_id = '';
                }
                $refinvoicedetails = DB::table('invoices')->select('orders.ref_order_id')->join('orders','invoices.ref_order_id','=','orders.order_id')
                    ->where('invoices.invoice_id',$order_details_invoice_id)
                    ->where('invoices.v_id', $order_vid)
                    ->first(); 

                $ref_invoice = DB::table('orders')->select('invoices.date')->join('invoices','invoices.invoice_id','=','orders.ref_order_id')
                    ->where('invoices.invoice_id',$refinvoicedetails->ref_order_id)
                    ->where('invoices.v_id', $order_vid)
                    ->first(); 
                $terms_conditions =  array('1. Products once sold cannot be returned back.','2. Products bought can be exchanged within 15 days of invoice only and in proper saleable condition along with the original copy of bill','3. Products bought on discount or offer cannot be exchanged with full price products.' );
                // $terms =  Terms::where('v_id',$v_id)->get();
                //     $terms_condition = json_decode($terms);
                //     foreach ($terms_condition as $value) {
                //         $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                //     }
            ########################
            ####### Print Start ####
            ########################
            
            
            //$data = '<body style="padding: 20px;">';
            
            //$data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"><table class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';
            /* $data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"> <table width="10%"><tr><td><img src="'.$bilLogo.'" alt="" height="80px"></td></tr>
           </table><table width="80%" class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';*/
            $data  .= '<table class="" width="92%" style="margin-top: 38px; page-break-after: always; margin-bottom: 0px; margin-left: auto; margin-right: auto;">';
            if($order_transaction_type == 'sales'){
                $data .= '<tr><td align="center" style="font-size: 16px;font-weight: 600; padding-bottom: 8px;">Sales Invoice</td></tr>'; 
            }else{
                $data .= '<tr><td align="center" style="font-size: 16px; font-weight: 600; padding-bottom: 13px;">Credit Note</td></tr>';
            }
            $data  .= '<tr><td><table width="100%" style="padding-left: 5px; padding-right: 5px;padding-bottom:8px;">';   

            $data  .=  '<tr style="vertical-align="top"><td width="30%"><table ><tr><td style="padding-left: 16px;padding-bottom: 20px;text-align:left;"><img src="'.$bilLogo.'" alt="" height="auto" width="50%"></td></tr></table>';
            $data   .= '</td><td width="40%" align="center">';
            $data  .=  '<table width="100%"><tr><td style="padding: 0px; font-size: 13px;text-align:center;"><b>'.$store->name.'</b></td></tr><tr><td style="padding: 0px; font-size: 13px;text-align:center;">'.$store->address1.'</td></tr>';            
            if($store->address2){
                $data  .=  '<tr><td style="padding: 0px; font-size: 13px;text-align:center;">'.$store->address2.'</td></tr>';
            }
            $data  .=  '<tr><td style="padding: 0px; font-size: 13px; text-align: center;">'.$store->city.'-'.$store->pincode.', '.$store->state.'</td></tr>';
                    
            $data  .=  '<tr><td style="padding: 0px; font-size: 13px;padding-left:22%;">Email:- '.$store->email.'</td></tr>';
                    
            $data  .=  '<tr><td style="padding: 0px; font-size: 13px;padding-left: 22%;">Phone No:- '.$store->contact_number.'</td></tr>';
                    
                    // if($store->gst){
                    //     $data .= '';
                    // }
            $data .= '</table></td>';
                $data .= '<td width="30%" style="padding-left: 16px; padding-top: 15px;"><table style="text-align: left;" width="100%"><tr>';
            
            if($order_transaction_type == 'sales'){
                $data .= '<td style="padding: 0px; font-size: 13px;">Invoice No:- '.$order_details->invoice_id.'</td>'; 
            }else{
               $data .= '<td style="padding: 0px; font-size: 13px;">Credit Note No:- '.$order_details->invoice_id.'</td>';
            }
            $data .= '</tr><tr><td style="padding: 0px; font-size: 13px;">Invoice Date:- '.date('d-M-Y', strtotime($order_details->created_at)).'</td></tr>';
            
            $data .= '<tr><td style="padding: 0px; font-size: 13px;">Customer Name:- '.@$order_details->user->first_name.' '.@$order_details->user->last_name.'</td></tr><tr><td style="padding: 0px; font-size: 13px;">Phone No:- '.$order_details->user->mobile.'</td></tr>';    
                
            if($refinvoicedetails->ref_order_id){
                    $data .= '<tr><td style="padding: 0px 0px 0px 0px; font-size: 13px;">Against Invoice No:- '.$refinvoicedetails->ref_order_id.'</td></tr><tr><td style="padding: 0px 0px 12px 0px; font-size: 13px;">Original Invoice Date:- '.date('d-M-Y', strtotime($ref_invoice->date)).'</td></tr>';
            }else{
                $data .= '';
            }
            $data .= '</table></td></tr></table>';

             
            // $data  .= '<tr><td><table style="width: 100%; color: #fff; padding: 5px; text-align: center; border: 1px #000 solid; border-left: none; border-right: none;"><tr><td valign="top" style="line-height: 1.5;  color: #000;"><b>'.$invoice_title.'</b></td></tr></table></td></tr>';
            // $data  .=  '<tr>
            // <td>
            // <table style="width: 100%; color: #fff; padding: 5px;">';
            // $data  .=  '<tr>
            // <td valign="top" style="line-height: 1;  color: #000; font-size: 12px;text-align:left;">Customer 
            // <br>
            // <b>'.@$order_details->user->first_name.''.@$order_details->user->last_name.'</b>
            // <br>'.@$order_details->user->mobile.'
            // <br>'.@$order_details->user->gstin.'
            // <br>'.$customer_address.'
            // </td>';

            //<br>'.@$order_details->user->mobile.'
            // $data  .= '<td valign="top" style="line-height: 1.5; color: #000; font-size: 14px;" align="right">Date : '.date('d-M-Y', strtotime($order_details->created_at)).'
            // <br>Invoice No: '.$order_details->invoice_id.'</td>
            // </tr></table></td></tr>';
            // $data  .= '<tr><td valign="top" style="line-height: 1.5;  color: #000">'.@$order_details->user->mobile.'</td>';
            // $data .=  '<td valign="top" style="line-height: 2.1; color: #000" align="right">Invoice No:<span style="color: #000;">'.$order_details->invoice_id.'</span></td></tr></table></td></tr>';
            /*$data  .= '<tr><td></td><td valign="top" style="line-height: 2.1; color: #000" align="right">Cashier <span style="color: #000;">'.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</span></td></tr></table></td></tr>';*/
            $data  .= '<table class="" width="70%><tr><td><div  style="height: 400px; overflow: hidden; border-top: 2px #000 solid; border-bottom: 2px #000 solid; "   >';
            if($i > 0){
                if($total_page - 1 == $i){
                    if($countitem >= 5 && $countitem <= 16){
                         $data .= '<table height="100%" width="100%" class="print_invoice_table_afive" bgcolor="#fff" style="width: 100%; color: #000; display: none;">';  
                     }elseif ($countitem >= 21 && $countitem <= 32) {
                         $data .= '<table height="100%" width="100%" class="print_invoice_table_afive" bgcolor="#fff" style="width: 100%; color: #000; display: none;">';  
                     }else{
                        $data .= '<table height="100%" width="100%" class="print_invoice_table_afive" bgcolor="#fff" style="width: 100%; color: #000;">';
                    }
                }else{
                    $data .= '<table height="100%" width="100%" class="print_invoice_table_afive" bgcolor="#fff" style="width: 100%; color: #000;">';
                }
            }else{
                $data .= '<table height="100%" width="100%" class="print_invoice_table_afive" bgcolor="#fff" style="width: 100%; color: #000;">';
            }
            //     if($count_product >= 4){
            //         $data .= '<table height="100%" width="100%" class="print_invoice_table_afive" bgcolor="#fff" style="width: 100%; color: #000; display: none;">';                    
            //     }elseif ($count_product == 0) {
            //         $data .= '<table height="100%" width="100%" class="print_invoice_table_afive" bgcolor="#fff" style="width: 100%; color: #000; display: none;">';
            //     }
            // }else{
            //     $data .= '<table height="100%" width="100%" class="print_invoice_table_afive" bgcolor="#fff" style="width: 100%; color: #000;">';
            // }          
            $data  .= '<thead ><tr align="left" style="white-space: nowrap;"><th width="6%" style=" font-size: 12px; text-align: center;" >S.No.</th>';
            $data .= '<th width="25%"valign="center"  style=" font-size: 12px; text-align: center;" >Item Name</th>';            
            $data .= '<th width="10%"valign="center"  style=" font-size: 12px; text-align: center;" >HSN Code</th>';
            $data .= '<th width="10%"valign="center"  style=" font-size: 12px; text-align: center;" >Price</th>';               
            $data .= '<th width="7%"valign="center"  style=" font-size: 12px; text-align: center;" >Qty.</th>';
            $data .= '<th width="11%"valign="center"  style=" font-size: 12px; text-align: center;" >Total Price</th>';            
            $data .= '<th width="9%"valign="center"  style=" font-size: 12px; text-align: center;" >Discount</th>
                      <th width="8%"valign="center"  style=" font-size: 12px; text-align: center;" >GST %</td>';  
            $data .= '<th width="14%"valign="center"  style=" font-size: 12px; border-right: none; text-align: center;" >Item Net Value </th></tr></thead><tbody>';
           
            $srp= '';
            $barcode = '';
            $hsn ='';
            $item_name ='';
            $qty  = '';
            $unit = '';
            $mrp  = '';
            $disc = '';
            $taxp = '';
            $taxb = '';

            $taxable_amount = 0;
            $total_csgt     = 0;
            $total_sgst     = 0;
            $total_cess     = 0;
            $total_mrp      = 0;
            $total_gst            = 0;
            $total_net_value       = 0;
            $total_discount    = 0;
            $total_qty         = 0;
            $srp            = '';
            $barcode        = '';
            $hsn            = '';
            $qty            = '';
            $unit           = '';
            $mrp            = '';
            $disc           = '';
            $taxp           = '';
            $taxb           = '';
            $varient        = '';
            $GST            = '';
            $NetValue       = '';
            // dd($order_details);
            foreach ($cart_product as $key => $value) {

                $remark = isset($value->remark)?' -'.$value->remark:'';
                $tdata    = json_decode($value->tdata);
                $cdata    = json_decode($value->section_target_offers);
                if(isset($cdata->sku_code)){
                    $tempcdata = $cdata->sku_code;
                }else{
                    $tempcdata = '';
                }
                $style_data = DB::table('vendor_sku_flat_table')->select('cat_name_1')->where('sku_code', $tempcdata)->first();
 
                $itemLevelmanualDiscount=0;
                if($value->item_level_manual_discount!=null){
                    $iLmd = json_decode($value->item_level_manual_discount);
                    $itemLevelmanualDiscount= (float)$iLmd->discount;
                }
                $discount = $value->discount+$value->manual_discount + $value->bill_buster_discount+$itemLevelmanualDiscount;

                $taxper   = $tdata->cgst + $tdata->sgst + $tdata->igst;
                $taxable_amount += $tdata->taxable;
                $total_csgt  += $tdata->cgstamt;
                $total_sgst  += $tdata->sgstamt;
                $total_cess  += $tdata->cessamt;
                $total_discount += $discount;

                $totalmrp = $value->unit_mrp * $value->qty;
                if($tdata->tax_type == 'INC'){
                    $taxvalue = $totalmrp - $discount;
                    $taxdata = 1 + $taxper/100;
                    $taxableamt = $taxvalue/$taxdata;
                    $gst = $taxableamt * $taxper/100;
                }else{
                    $gst = ($totalmrp - $discount) * $taxper/100;
                }
                $total_gst += $gst;
                if($tdata->tax_type == 'INC'){ 
                    $net_value = $totalmrp - $discount;
                }
                else{
                    $net_value = ($totalmrp - $discount) + $gst;
                }
                $total_net_value += $net_value;

                $total_qty  += $value->qty;

                $total_mrp  += $totalmrp; 

                $srp       .= '<pre style="font-size:12px;">'.$sr.'</pre>';
                $barcode   .= '<pre style="font-size:12px;">'.$value->barcode.'</pre>';
                $hsn       .= '<pre style="font-size:12px;">'.$tdata->hsn.'</pre>';
                $qty       .= '<pre style="font-size:12px;">'.$value->qty.'</pre>';
                $unit      .= '<pre style="font-size:12px;">PCS</pre>';
                $mrp       .= '<pre style="font-size:12px;">'.$value->unit_mrp.'</pre>';
                $disc      .= '<pre style="font-size:12px;">'.number_format($discount,2).'</pre>';
                $taxp      .= '<pre style="font-size:12px;">'.number_format($taxper,2).'</pre>';
                $taxb      .= '<pre style="font-size:12px;">'.number_format($totalmrp,2).'</pre>';
                $GST       .= '<pre style="font-size:12px;">'.number_format($gst,2).'</pre>';
                $NetValue  .= '<pre style="font-size:12px;">'.number_format($net_value,2).'</pre>';
                $tempVarient = isset($style_data->cat_name_1) ? $style_data->cat_name_1 : ' ';
                $varient   .= '<pre style="font-size:12px;">'.$tempVarient.'</pre>';
                $sr++;
            }
            // if($total_page-1 == $i){
            //     $totallastpageitem = $countitem - 16;
            //     if($totallastpageitem >=1 && $totallastpageitem <=4 ){
            //         $data   .= '<tr align="left" style="height: 84px;">';
            //     }
            // }else{
            // }
            $data   .= '<tr align="left">';
            $data   .= '<td valign="top" style="text-align: center;">'.$srp.'</td>';
            $data   .= '<td valign="top" style="text-align: center;">'.$varient.'</td>';
            $data   .= '<td valign="top" style="text-align: center;">'.$hsn.'</td>';
            $data   .= '<td valign="top" style="text-align: right;">'.$mrp.'</td>';
            $data   .= '<td valign="top" style="text-align: center;">'.$qty.'</td>';
            $data   .= '<td valign="top" style="text-align: right;">'.$taxb.'</td>';
            $data   .= '<td valign="top" style="text-align: right;">'.$disc.'</td>';
            $data   .= '<td valign="top" style="text-align: center;">'.$taxp.'</td>';
            $data   .= '<td valign="top" style="text-align: right;">'.$NetValue.'</td></tr>';
            // $total_csgt = round($total_csgt,2);
            // $total_sgst = round($total_sgst,2);
            // $total_cess = round($total_cess,2);
                $taxable_amount   = 0;
                $total_csgt       = 0;
                $total_sgst       = 0;
                $total_cess       = 0;
                $sub_total        = 0;
                $total_mrp        = 0;
                $total_gst        = 0;
                $total_net_value  = 0;
                $total_discount   = 0;
                $total_qty        = 0;
                $invoiceData  = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->get();
                foreach($invoiceData as $invdata){
                    $Ntdata    = json_decode($invdata->tdata);
                    $itemLevelmanualDiscount=0;
                    if($invdata->item_level_manual_discount!=null){
                     $iLmd = json_decode($invdata->item_level_manual_discount);
                     $itemLevelmanualDiscount = (float)$iLmd->discount;
                    }
                    $discount = $invdata->discount+$invdata->manual_discount + $invdata->bill_buster_discount+$itemLevelmanualDiscount;
                    $taxper          = $Ntdata->cgst + $Ntdata->sgst + $Ntdata->igst;
                    // $taxable_amount += $Ntdata->taxable;
                    $total_csgt     += $Ntdata->cgstamt;
                    $total_sgst     += $Ntdata->sgstamt;
                    $total_cess     += $Ntdata->cessamt;
                    $sub_total      += $invdata->subtotal;

                    $total_discount += $discount;

                    $totalmrp = $invdata->unit_mrp * $invdata->qty;

                    if($Ntdata->tax_type == 'INC'){
                        $taxvalue = $totalmrp - $discount;
                        $taxdata = 1 + $taxper/100;
                        $taxableamt = $taxvalue/$taxdata;
                        $gst = $taxableamt * $taxper/100;
                    }else{
                        $gst = ($totalmrp - $discount) * $taxper/100;
                    }

                    $total_gst += $gst;
                    if($Ntdata->tax_type == 'INC'){ 
                        $net_value = $totalmrp - $discount;
                    }
                    else{
                        $net_value = ($totalmrp - $discount) + $gst;
                    }
                    $total_net_value += $net_value;
                    $taxableAmt = $total_net_value - $total_gst;
                    $taxable_amount = $taxableAmt;
                    $total_qty  += $invdata->qty;
                    $total_mrp  += $totalmrp; 
                }
                if($totalpage-1 == $i){
                    // $count = $countitem - 16;
                    if($countitem >= 5 && $countitem <=8){
                        if($countitem >= 5 && $countitem <= 16){
                            $data .= '<table width="100%" style="border: 1px #000 solid; border-bottom: none;">';     
                        }elseif ($countitem >= 21 && $countitem <= 32) {
                            $data .= '<table width="100%" style="border: 1px #000 solid; border-bottom: none;">';
                        }else{
                            $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;">';
                        }
                        $data .= '<tr align="left"><td valign="top" style="font-size: 12px; width: 51%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">Total</td>';
                        $data .= '<td  valign="top" style="font-size: 12px; text-align: center; width: 7%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">'.$total_qty.'</td>';
                        $data .= '<td  valign="top" style="font-size: 12px; border-bottom: none !important;  width: 11%; border-right: 1px #000 solid;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">Total Bill Value</td>';
                        $data .= '<td  valign="top" style="text-align: right; width: 14%;  padding: 4px; border-bottom: 1px #000 solid;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($total_mrp,2).'</b></td></tr></table>';
                        
                        $totalAmtinwords = ucfirst(numberTowords(round($total_net_value)));
                        $totalamountinwords = str_replace("ty","ty ",$totalAmtinwords);
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td  valign="top" style="font-size: 12px; width: 58%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;text-align:left;">In Rupees:- '.$totalamountinwords.' Only</td>';
                      
                        $data .= '<td valign="top" style="font-size: 12px; width: 11%; border-right: 1px #000 solid; border-bottom: none !important;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; padding: 4px; border-bottom: 1px #000 solid; border-right: 1px #000 solid;text-align:left;">Total Discount</td>';
                        $data .= '<td valign="top" style="text-align: right; width: 14%; padding: 4px; border-bottom: 1px #000 solid;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($total_discount,2).'</b></td></tr></table>';
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td valign="top" style="font-size: 12px; width: 41%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;text-align:left;">Payment Mode</td>';
                        $data .= '<td valign="top" style="font-size: 12px; text-align: right; width: 17%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">Amount Paid</td>';
                        $data .= '<td valign="top" style=" border-bottom: none !important; width: 11%; border-right: 1px #000 solid;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;text-align:left;">Taxable Value</td>';
                        $data .= '<td valign="top" style="text-align: right; width: 14%; padding: 4px; border-bottom: 1px #000 solid;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($taxable_amount,2).'</b></td></tr></table>';
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td style="padding: 0px; width: 41%;"><table width="100%" class="pmode">';
                        foreach($mop_list as $mop){
                            $data  .= '<tr>
                            <td valign="top" style="font-size: 12px; text-transform: capitalize; width: 50%; padding: 4px; border-right: 1px #000 solid;text-align:left;">'.$mop['mode'].'</td>
                            <td valign="top" style="font-size: 12px; text-align: right; width: 50%; border-right: 1px #000 solid; padding: 4px;">'.$mop['amount'].'</td></tr>';
                        }
                        $data .= '</table></td>';
                        $data .= '<td valign="top" style="text-align: right; width: 17%; border-right: 1px #000 solid; padding: 4px;"><b style="font-size:12px;">'.number_format($total_net_value,2).'</b></td>';
                    
                        $data .= '<td valign="top" style=" border-bottom: none !important; width: 11%; border-right: 1px #000 solid;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; border-right: 1px #000 solid; padding: 4px;text-align:left;">GST</td>';
                        $data .= '<td valign="top" style="text-align: right; width: 14%; padding: 4px;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($total_gst,2).'</b></td></tr></table>';
                    
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none;"><tr><td valign="top" style="font-size: 12px; width: 100%; border-top: 1px #000 solid; border-bottom: none !important; padding: 4px;text-align:left;">Attended by:- '.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</td></tr></table>';
                    }elseif ($countitem >= 21 && $countitem <= 24) {
                                                if($countitem >= 5 && $countitem <= 16){
                            $data .= '<table width="100%" style="border: 1px #000 solid; border-bottom: none;">';     
                        }elseif ($countitem >= 21 && $countitem <= 32) {
                            $data .= '<table width="100%" style="border: 1px #000 solid; border-bottom: none;">';
                        }else{
                            $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;">';
                        }
                        $data .= '<tr align="left"><td valign="top" style="font-size: 12px; width: 51%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">Total</td>';
                        $data .= '<td  valign="top" style="font-size: 12px; text-align: center; width: 7%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">'.$total_qty.'</td>';
                        $data .= '<td  valign="top" style="font-size: 12px; border-bottom: none !important;  width: 11%; border-right: 1px #000 solid;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">Total Bill Value</td>';
                        $data .= '<td  valign="top" style="text-align: right; width: 14%;  padding: 4px; border-bottom: 1px #000 solid;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($total_mrp,2).'</b></td></tr></table>';
                        
                        $totalAmtinwords = ucfirst(numberTowords(round($total_net_value)));
                        $totalamountinwords = str_replace("ty","ty ",$totalAmtinwords);
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td  valign="top" style="font-size: 12px; width: 58%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;text-align:left;">In Rupees:- '.$totalamountinwords.' Only</td>';
                      
                        $data .= '<td valign="top" style="font-size: 12px; width: 11%; border-right: 1px #000 solid; border-bottom: none !important;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; padding: 4px; border-bottom: 1px #000 solid; border-right: 1px #000 solid;text-align:left;">Total Discount</td>';
                        $data .= '<td valign="top" style="text-align: right; width: 14%; padding: 4px; border-bottom: 1px #000 solid;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($total_discount,2).'</b></td></tr></table>';
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td valign="top" style="font-size: 12px; width: 41%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;text-align:left;">Payment Mode</td>';
                        $data .= '<td valign="top" style="font-size: 12px; text-align: right; width: 17%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">Amount Paid</td>';
                        $data .= '<td valign="top" style=" border-bottom: none !important; width: 11%; border-right: 1px #000 solid;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;text-align:left;">Taxable Value</td>';
                        $data .= '<td valign="top" style="text-align: right; width: 14%; padding: 4px; border-bottom: 1px #000 solid;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($taxable_amount,2).'</b></td></tr></table>';
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td style="padding: 0px; width: 41%;"><table width="100%" class="pmode">';
                        foreach($mop_list as $mop){
                            $data  .= '<tr>
                            <td valign="top" style="font-size: 12px; text-transform: capitalize; width: 50%; padding: 4px; border-right: 1px #000 solid;text-align:left;">'.$mop['mode'].'</td>
                            <td valign="top" style="font-size: 12px; text-align: right; width: 50%; border-right: 1px #000 solid; padding: 4px;">'.$mop['amount'].'</td></tr>';
                        }
                        $data .= '</table></td>';
                        $data .= '<td valign="top" style="text-align: right; width: 17%; border-right: 1px #000 solid; padding: 4px;"><b style="font-size:12px;">'.number_format($total_net_value,2).'</b></td>';
                    
                        $data .= '<td valign="top" style=" border-bottom: none !important; width: 11%; border-right: 1px #000 solid;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; border-right: 1px #000 solid; padding: 4px;text-align:left;">GST</td>';
                        $data .= '<td valign="top" style="text-align: right; width: 14%; padding: 4px;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($total_gst,2).'</b></td></tr></table>';
                    
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td valign="top" style="font-size: 12px; width: 100%; border-top: 1px #000 solid; border-bottom: none !important; padding: 4px;text-align:left;">Attended by:- '.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</td></tr></table>';
                    }elseif($countitem >= 9 && $countitem <= 16){
                        $data .= '';
                    }elseif($countitem >= 25 && $countitem <= 32){
                        $data .= '';
                    }elseif($countitem >= 17 && $countitem <= 20){
                         if($countitem >= 5 && $countitem <= 16){
                            $data .= '<table width="100%" style="border: 1px #000 solid; border-bottom: none;">';     
                        }elseif ($countitem >= 21 && $countitem <= 32) {
                            $data .= '<table width="100%" style="border: 1px #000 solid; border-bottom: none;">';
                        }else{
                            $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;">';
                        }
                        $data .= '<tr align="left"><td valign="top" style="font-size: 12px; width: 51%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">Total</td>';
                        $data .= '<td  valign="top" style="font-size: 12px; text-align: center; width: 7%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">'.$total_qty.'</td>';
                        $data .= '<td  valign="top" style="font-size: 12px; border-bottom: none !important;  width: 11%; border-right: 1px #000 solid;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">Total Bill Value</td>';
                        $data .= '<td  valign="top" style="text-align: right; width: 14%;  padding: 4px; border-bottom: 1px #000 solid;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($total_mrp,2).'</b></td></tr></table>';
                        
                        $totalAmtinwords = ucfirst(numberTowords(round($total_net_value)));
                        $totalamountinwords = str_replace("ty","ty ",$totalAmtinwords);
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td  valign="top" style="font-size: 12px; width: 58%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;text-align:left;">In Rupees:- '.$totalamountinwords.' Only</td>';
                      
                        $data .= '<td valign="top" style="font-size: 12px; width: 11%; border-right: 1px #000 solid; border-bottom: none !important;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; padding: 4px; border-bottom: 1px #000 solid; border-right: 1px #000 solid;text-align:left;">Total Discount</td>';
                        $data .= '<td valign="top" style="text-align: right; width: 14%; padding: 4px; border-bottom: 1px #000 solid;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($total_discount,2).'</b></td></tr></table>';
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td valign="top" style="font-size: 12px; width: 41%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;text-align:left;">Payment Mode</td>';
                        $data .= '<td valign="top" style="font-size: 12px; text-align: right; width: 17%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">Amount Paid</td>';
                        $data .= '<td valign="top" style=" border-bottom: none !important; width: 11%; border-right: 1px #000 solid;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;text-align:left;">Taxable Value</td>';
                        $data .= '<td valign="top" style="text-align: right; width: 14%; padding: 4px; border-bottom: 1px #000 solid;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($taxable_amount,2).'</b></td></tr></table>';
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td style="padding: 0px; width: 41%;"><table width="100%" class="pmode">';
                        foreach($mop_list as $mop){
                            $data  .= '<tr>
                            <td valign="top" style="font-size: 12px; text-transform: capitalize; width: 50%; padding: 4px; border-right: 1px #000 solid;text-align:left;">'.$mop['mode'].'</td>
                            <td valign="top" style="font-size: 12px; text-align: right; width: 50%; border-right: 1px #000 solid; padding: 4px;">'.$mop['amount'].'</td></tr>';
                        }
                        $data .= '</table></td>';
                        $data .= '<td valign="top" style="text-align: right; width: 17%; border-right: 1px #000 solid; padding: 4px;"><b style="font-size:12px;">'.number_format($total_net_value,2).'</b></td>';
                    
                        $data .= '<td valign="top" style=" border-bottom: none !important; width: 11%; border-right: 1px #000 solid;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; border-right: 1px #000 solid; padding: 4px;text-align:left;">GST</td>';
                        $data .= '<td valign="top" style="text-align: right; width: 14%; padding: 4px;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($total_gst,2).'</b></td></tr></table>';
                    
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td valign="top" style="font-size: 12px; width: 100%; border-top: 1px #000 solid; border-bottom: none !important; padding: 4px;text-align:left;">Attended by:- '.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</td></tr></table>';
                    }else{
                         if($countitem >= 5 && $countitem <= 16){
                            $data .= '<table width="100%" style="border: 1px #000 solid; border-bottom: none;">';     
                        }elseif ($countitem >= 21 && $countitem <= 32) {
                            $data .= '<table width="100%" style="border: 1px #000 solid; border-bottom: none;">';
                        }else{
                            $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;">';
                        }
                        $data .= '<tr align="left"><td valign="top" style="font-size: 12px; width: 51%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">Total</td>';
                        $data .= '<td  valign="top" style="font-size: 12px; text-align: center; width: 7%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">'.$total_qty.'</td>';
                        $data .= '<td  valign="top" style="font-size: 12px; border-bottom: none !important;  width: 11%; border-right: 1px #000 solid;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">Total Bill Value</td>';
                        $data .= '<td  valign="top" style="text-align: right; width: 14%;  padding: 4px; border-bottom: 1px #000 solid;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($total_mrp,2).'</b></td></tr></table>';
                        
                        $totalAmtinwords = ucfirst(numberTowords(round($total_net_value)));
                        $totalamountinwords = str_replace("ty","ty ",$totalAmtinwords);
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td  valign="top" style="font-size: 12px; width: 58%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;text-align:left;">In Rupees:- '.$totalamountinwords.' Only</td>';
                      
                        $data .= '<td valign="top" style="font-size: 12px; width: 11%; border-right: 1px #000 solid; border-bottom: none !important;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; padding: 4px; border-bottom: 1px #000 solid; border-right: 1px #000 solid;text-align:left;">Total Discount</td>';
                        $data .= '<td valign="top" style="text-align: right; width: 14%; padding: 4px; border-bottom: 1px #000 solid;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($total_discount,2).'</b></td></tr></table>';
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td valign="top" style="font-size: 12px; width: 41%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;text-align:left;">Payment Mode</td>';
                        $data .= '<td valign="top" style="font-size: 12px; text-align: right; width: 17%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">Amount Paid</td>';
                        $data .= '<td valign="top" style=" border-bottom: none !important; width: 11%; border-right: 1px #000 solid;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;text-align:left;">Taxable Value</td>';
                        $data .= '<td valign="top" style="text-align: right; width: 14%; padding: 4px; border-bottom: 1px #000 solid;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($taxable_amount,2).'</b></td></tr></table>';
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td style="padding: 0px; width: 41%;"><table width="100%" class="pmode">';
                        foreach($mop_list as $mop){
                            $data  .= '<tr>
                            <td valign="top" style="font-size: 12px; text-transform: capitalize; width: 50%; padding: 4px; border-right: 1px #000 solid;text-align:left;">'.$mop['mode'].'</td>
                            <td valign="top" style="font-size: 12px; text-align: right; width: 50%; border-right: 1px #000 solid; padding: 4px;">'.$mop['amount'].'</td></tr>';
                        }
                        $data .= '</table></td>';
                        $data .= '<td valign="top" style="text-align: right; width: 17%; border-right: 1px #000 solid; padding: 4px;"><b style="font-size:12px;">'.number_format($total_net_value,2).'</b></td>';
                    
                        $data .= '<td valign="top" style=" border-bottom: none !important; width: 11%; border-right: 1px #000 solid;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; border-right: 1px #000 solid; padding: 4px;text-align:left;">GST</td>';
                        $data .= '<td valign="top" style="text-align: right; width: 14%; padding: 4px;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($total_gst,2).'</b></td></tr></table>';
                    
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td valign="top" style="font-size: 12px; width: 100%; border-top: 1px #000 solid; border-bottom: none !important; padding: 4px;text-align:left;">Attended by:- '.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</td></tr></table>';
                        
                        $data .= '<table width="100%" style="text-align:left; border: 1px #000 solid; padding: 4px;">';
                        
                        $data .= '<tr><td valign="top" style="padding-top:2px;font-size: 12px; font-weight: 600; border-bottom: none !important;">Return & Exchange Policy:- </td></tr>';
                        foreach($terms_conditions as $term){
                            $data .= '<tr width="100%"><td class="terms-spacing" style="padding-bottom: 4px; font-size: 13px;">'.$term.'</td></tr>';
                        }
                        $data .= '</table>';
                    }
                }else{
                    $data .= '';
                }
                if($total_page > 1){
                if($total_page-1 == $i){
                    // $count = $countitem - 16;
                    if($countitem >= 5 && $countitem <=8){
                        $data .= '<table width="100%" style="text-align:left; border: 1px #000 solid; padding: 4px;">';
                
                        $data .= '<tr><td valign="top" style="padding-top:2px;font-size: 12px; font-weight: 600; border-bottom: none !important;">Return & Exchange Policy:- </td></tr>';
                        foreach($terms_conditions as $term){
                            $data .= '<tr width="100%"><td class="terms-spacing" style="padding-bottom: 4px; font-size: 13px;">'.$term.'</td></tr>';
                        }
                        $data .= '</table>';
                    }elseif($countitem >= 9 && $countitem <= 16){
                        if($countitem >= 5 && $countitem <= 16){
                            $data .= '<table width="100%" style="border: 1px #000 solid; border-bottom: none;">';     
                        }elseif ($countitem >= 21 && $countitem <= 32) {
                            $data .= '<table width="100%" style="border: 1px #000 solid; border-bottom: none;">';
                        }else{
                            $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;">';
                        }
                        $data .= '<tr align="left"><td valign="top" style="font-size: 12px; width: 51%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">Total</td>';
                        $data .= '<td  valign="top" style="font-size: 12px; text-align: center; width: 7%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">'.$total_qty.'</td>';
                        $data .= '<td  valign="top" style="font-size: 12px; border-bottom: none !important;  width: 11%; border-right: 1px #000 solid;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">Total Bill Value</td>';
                        $data .= '<td  valign="top" style="text-align: right; width: 14%;  padding: 4px; border-bottom: 1px #000 solid;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($total_mrp,2).'</b></td></tr></table>';
                        
                        $totalAmtinwords = ucfirst(numberTowords(round($total_net_value)));
                        $totalamountinwords = str_replace("ty","ty ",$totalAmtinwords);
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td  valign="top" style="font-size: 12px; width: 58%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;text-align:left;">In Rupees:- '.$totalamountinwords.' Only</td>';
                      
                        $data .= '<td valign="top" style="font-size: 12px; width: 11%; border-right: 1px #000 solid; border-bottom: none !important;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; padding: 4px; border-bottom: 1px #000 solid; border-right: 1px #000 solid;text-align:left;">Total Discount</td>';
                        $data .= '<td valign="top" style="text-align: right; width: 14%; padding: 4px; border-bottom: 1px #000 solid;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($total_discount,2).'</b></td></tr></table>';
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td valign="top" style="font-size: 12px; width: 41%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;text-align:left;">Payment Mode</td>';
                        $data .= '<td valign="top" style="font-size: 12px; text-align: right; width: 17%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">Amount Paid</td>';
                        $data .= '<td valign="top" style=" border-bottom: none !important; width: 11%; border-right: 1px #000 solid;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;text-align:left;">Taxable Value</td>';
                        $data .= '<td valign="top" style="text-align: right; width: 14%; padding: 4px; border-bottom: 1px #000 solid;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($taxable_amount,2).'</b></td></tr></table>';
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td style="padding: 0px; width: 41%;"><table width="100%" class="pmode">';
                        foreach($mop_list as $mop){
                            $data  .= '<tr>
                            <td valign="top" style="font-size: 12px; text-transform: capitalize; width: 50%; padding: 4px; border-right: 1px #000 solid;text-align:left;">'.$mop['mode'].'</td>
                            <td valign="top" style="font-size: 12px; text-align: right; width: 50%; border-right: 1px #000 solid; padding: 4px;">'.$mop['amount'].'</td></tr>';
                        }
                        $data .= '</table></td>';
                        $data .= '<td valign="top" style="text-align: right; width: 17%; border-right: 1px #000 solid; padding: 4px;"><b style="font-size:12px;">'.number_format($total_net_value,2).'</b></td>';
                    
                        $data .= '<td valign="top" style=" border-bottom: none !important; width: 11%; border-right: 1px #000 solid;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; border-right: 1px #000 solid; padding: 4px;text-align:left;">GST</td>';
                        $data .= '<td valign="top" style="text-align: right; width: 14%; padding: 4px;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($total_gst,2).'</b></td></tr></table>';
                    
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td valign="top" style="font-size: 12px; width: 100%; border-top: 1px #000 solid; border-bottom: none !important; padding: 4px;text-align:left;">Attended by:- '.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</td></tr></table>';
                        
                        $data .= '<table width="100%" style="text-align:left; border: 1px #000 solid; padding: 4px;">';
                        
                        $data .= '<tr><td valign="top" style="padding-top:2px;font-size: 12px; font-weight: 600; border-bottom: none !important;">Return & Exchange Policy:- </td></tr>';
                        foreach($terms_conditions as $term){
                            $data .= '<tr width="100%"><td class="terms-spacing" style="padding-bottom: 4px; font-size: 13px;">'.$term.'</td></tr>';
                        }
                        $data .= '</table>';
                    }elseif($countitem >= 17 && $countitem <= 20){
                        $data .= '<table width="100%" style="text-align:left; border: 1px #000 solid; padding: 4px;">';
                
                        $data .= '<tr><td valign="top" style="padding-top:2px;font-size: 12px; font-weight: 600; border-bottom: none !important;">Return & Exchange Policy:- </td></tr>';
                        foreach($terms_conditions as $term){
                            $data .= '<tr width="100%"><td class="terms-spacing" style="padding-bottom: 4px; font-size: 13px;">'.$term.'</td></tr>';
                        }
                        $data .= '</table>';
                    }else{
                         if($countitem >= 5 && $countitem <= 16){
                            $data .= '<table width="100%" style="border: 1px #000 solid; border-bottom: none;">';     
                        }elseif ($countitem >= 21 && $countitem <= 32) {
                            $data .= '<table width="100%" style="border: 1px #000 solid; border-bottom: none;">';
                        }else{
                            $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;">';
                        }
                        $data .= '<tr align="left"><td valign="top" style="font-size: 12px; width: 51%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">Total</td>';
                        $data .= '<td  valign="top" style="font-size: 12px; text-align: center; width: 7%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">'.$total_qty.'</td>';
                        $data .= '<td  valign="top" style="font-size: 12px; border-bottom: none !important;  width: 11%; border-right: 1px #000 solid;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">Total Bill Value</td>';
                        $data .= '<td  valign="top" style="text-align: right; width: 14%;  padding: 4px; border-bottom: 1px #000 solid;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($total_mrp,2).'</b></td></tr></table>';
                        
                        $totalAmtinwords = ucfirst(numberTowords(round($total_net_value)));
                        $totalamountinwords = str_replace("ty","ty ",$totalAmtinwords);
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td  valign="top" style="font-size: 12px; width: 58%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;text-align:left;">In Rupees:- '.$totalamountinwords.' Only</td>';
                      
                        $data .= '<td valign="top" style="font-size: 12px; width: 11%; border-right: 1px #000 solid; border-bottom: none !important;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; padding: 4px; border-bottom: 1px #000 solid; border-right: 1px #000 solid;text-align:left;">Total Discount</td>';
                        $data .= '<td valign="top" style="text-align: right; width: 14%; padding: 4px; border-bottom: 1px #000 solid;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($total_discount,2).'</b></td></tr></table>';
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td valign="top" style="font-size: 12px; width: 41%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;text-align:left;">Payment Mode</td>';
                        $data .= '<td valign="top" style="font-size: 12px; text-align: right; width: 17%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;">Amount Paid</td>';
                        $data .= '<td valign="top" style=" border-bottom: none !important; width: 11%; border-right: 1px #000 solid;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; border-right: 1px #000 solid; padding: 4px; border-bottom: 1px #000 solid;text-align:left;">Taxable Value</td>';
                        $data .= '<td valign="top" style="text-align: right; width: 14%; padding: 4px; border-bottom: 1px #000 solid;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($taxable_amount,2).'</b></td></tr></table>';
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td style="padding: 0px; width: 41%;"><table width="100%" class="pmode">';
                        foreach($mop_list as $mop){
                            $data  .= '<tr>
                            <td valign="top" style="font-size: 12px; text-transform: capitalize; width: 50%; padding: 4px; border-right: 1px #000 solid;text-align:left;">'.$mop['mode'].'</td>
                            <td valign="top" style="font-size: 12px; text-align: right; width: 50%; border-right: 1px #000 solid; padding: 4px;">'.$mop['amount'].'</td></tr>';
                        }
                        $data .= '</table></td>';
                        $data .= '<td valign="top" style="text-align: right; width: 17%; border-right: 1px #000 solid; padding: 4px;"><b style="font-size:12px;">'.number_format($total_net_value,2).'</b></td>';
                    
                        $data .= '<td valign="top" style=" border-bottom: none !important; width: 11%; border-right: 1px #000 solid;"></td>';
                        $data .= '<td valign="top" style="font-size: 12px; width: 17%; border-right: 1px #000 solid; padding: 4px;text-align:left;">GST</td>';
                        $data .= '<td valign="top" style="text-align: right; width: 14%; padding: 4px;"><b style="font-size: 12px;"><span style="font-size: 12px;">&#8377;</span>&nbsp;'.number_format($total_gst,2).'</b></td></tr></table>';
                    
                        $data .= '<table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;"><tr><td valign="top" style="font-size: 12px; width: 100%; border-top: 1px #000 solid; border-bottom: none !important; padding: 4px;text-align:left;">Attended by:- '.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</td></tr></table>';
                        
                        $data .= '<table width="100%" style="text-align:left; border: 1px #000 solid; padding: 4px;">';
                        
                        $data .= '<tr><td valign="top" style="padding-top:2px;font-size: 12px; font-weight: 600; border-bottom: none !important;">Return & Exchange Policy:- </td></tr>';
                        foreach($terms_conditions as $term){
                            $data .= '<tr width="100%"><td class="terms-spacing" style="padding-bottom: 4px; font-size: 13px;">'.$term.'</td></tr>';
                        }
                        $data .= '</table>';
                    }
                
                // if($countitem >= 5 && $countitem <= 16){
                // }elseif ($countitem >= 21 && $countitem <= 32) {
                //     $data .= '<table width="100%" height="112px"></table>';
                // }elseif ($countitem >= 1 && $countitem < 3) {
                //     $data .= '<table width="100%" height="40px"></table>';
                // }elseif($countitem == 3){
                //     $data .= '<table width="100%" height="30px"></table>';
                // }else{
                //     $data .= '';
                // }
            }else{
                $data .= '<table width="100%"><tr><td style="padding: 5px; text-align: right;font-size:11px;">To be continued...</td></tr></table>';
                
            }
            }
        
            if($total_page > 1){
                if($total_page-1 == $i){
                    
                    if($countitem >=1 && $countitem <=4){
                        $height = 61 - (18 *($countitem - 1));
                        $data .= '<div width="100%" style="height: '.$height.'px;"></div>';     
                    }elseif ($countitem >=5 && $countitem <=8) {
                        $data .= '<div width="100%" style="height: 252px"></div>';   
                    }elseif ($countitem >= 9 && $countitem <= 16) {
                        $data .= '<div width="100%" style="height: 138px"></div>';      
                    }elseif($countitem >=17 && $countitem <=20){
                        $height = 61 - (18 *(($countitem - 16) - 1));
                        $data .= '<div width="100%" style="height: '.$height.'px;"></div>';     
                    }elseif ($countitem >=21 && $countitem <=24) {
                        $data .= '<div width="100%" style="height: 252px"></div>';     
                    }elseif ($countitem >= 25 && $countitem <= 32) {
                        $data .= '<div width="100%" style="height: 138px"></div>';     
                    }
                }else{
                    if($countitem >=1 && $countitem <=4){
                        $height = 61 - (18 *($countitem - 1));
                        $data .= '<div width="100%" style="height: '.$height.'px;"></div>';     
                    }elseif ($countitem >=5 && $countitem <=8) {
                        $height = ((16 - $countitem) * 18) - 124;
                        $data .= '<div width="100%" style="height: '.$height.'px;"></div>';     
                    }elseif ($countitem >=9 && $countitem <=16) {
                        $height = (16 - $countitem) * 18;
                        $data .= '<div width="100%" style="height: '.$height.'px;"></div>';     
                    }elseif($countitem >=17 && $countitem <=20){
                        // $height = 61 - (18 *(($countitem - 16) - 1));
                        $data .= '<div width="100%"></div>';  
                    }elseif ($countitem >=21 && $countitem <=24) {
                        $height = ((16 - $countitem) * 18) - 124;
                        $data .= '<div width="100%" style="height: '.$height.'px;"></div>';      
                    }elseif ($countitem >=25 && $countitem <=32) {
                        $height = ((16 * ($i+1)) - $countitem) * 18;
                        $data .= '<div width="100%" style="height: '.$height.'px;"></div>';     
                    }
                }
            }else{
                if ($totalpage-1 == $i) {
                        if($countitem >=1 && $countitem <=4){
                            $height = 61 - (18 *($countitem - 1));
                            $data .= '<div width="100%" style="height: '.$height.'px;"></div>';     
                        }elseif ($countitem >=5 && $countitem <=8) {
                            $height = ((16 - $countitem) * 18) - 124;
                            $data .= '<div width="100%" style="height: '.$height.'px;"></div>';     
                        }elseif ($countitem >=9 && $countitem <=16) {
                            $height = (16 - $countitem) * 18;
                            $data .= '<div width="100%" style="height: '.$height.'px;"></div>';     
                        }elseif($countitem >=17 && $countitem <=20){
                            // $height = 61 - (18 *(($countitem - 16) - 1));
                            $data .= '<div width="100%"></div>';     
                        }elseif ($countitem >=21 && $countitem <=24) {
                            $height = ((16 - $countitem) * 18) - 124;
                            $data .= '<div width="100%" style="height: '.$height.'px;"></div>';     
                        }elseif ($countitem >=25 && $countitem <=32) {
                            $height = (16 - $countitem) * 18;
                            $data .= '<div width="100%" style="height: '.$height.'px;"></div>';     
                        }
                    }
            }
            // $data   .= '<tr class="print_invoice_terms"><td><table style="width: 100%; padding: 10px 0; color: #000;">
            //     <tr width="100%">
            //         <td align="center">For Terms and Conditions Please turn over</td >
            //     </tr>';
            //  foreach($terms_conditions as $term){
            //     $data .= '<tr width="100%"><td style="padding-bottom: 5px; text-decoration: dotted;">&bull;'.$term.'</td></tr>';
            //  }
            // $data    .= '</table></td></tr>';
            // $data    .= '<tr class="print_invoice_last"><td><table bgcolor="#fff" width="100%" style="color: #000000; padding: 5px;"><tr><td width="3%">For:</td><td colspan="1"><b>'.$store->name.'</b></td></tr></table><table width="100%" style="color: #000000; padding-top: 20px !important; padding: 5px;"><tr><td></td></tr><tr class="print_store_sign"><td width="50%">Authorised Signatory</td><td width="35%" align="right">Prepared by:</td><td align="right">&nbsp;'.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</td></tr></table></td></tr></table>';
             
            $data .= '<table width="100%"><tr><td align="left" width="50%"><span style="font-size:10px;">PUREPLAY SKIN SCIENCES (INDIA) PVT LTD.<br> GST No:- 27AAHCP3973D1ZA</span></td><td align="right" width="50%"><img src="'.$bottombilLogo.'" alt="" height="26px"></td></tr></table></table>';    
        }
        $return = array('status'=>'success','style'=>$style,'html'=>$data) ;
        return $return;
    }

         public function print_html_page_for_mapcha($request){

            $v_id       = $request->v_id;
            $store_id   = $request->store_id; 
            $c_id       = $request->c_id;
            $order_id   = $request->order_id;
            $product_data= [];
            $gst_list    = [];
            $final_gst   = [];
            $detatch_gst = [];
            $rounded     = 0;
            $data    = '';
            $terms_conditions =  array('');
            $invoice_title = 'Retail Invoice';
            $style = "<style>@font-face {
                font-family: 'glacial_indifferencebold';
                src: url('https://test.api.gozwing.com/einvoice/GlacialIndifference-Bold.woff2;') format('woff2'),
                     url('https://test.api.gozwing.com/einvoice/GlacialIndifference-Bold.woff;') format('woff');
                font-weight: 600;
                font-style: normal;
            
            }
            @font-face {
                font-family: 'glacial_indifferenceregular';
                src: url('https://test.api.gozwing.com/einvoice/GlacialIndifference-Regular.woff2') format('woff2'),
                     url('https://test.api.gozwing.com/einvoice/GlacialIndifference-Regular.woff;') format('woff');
                font-weight: normal;
                font-style: normal;
            
            }hr{border: 1px #000 solid; background-color: #000;} .bold{font-weight: bold;} body{font-family: glacial_indifferenceregular; font-size: 14px;} .head-p.invoice{font-size: 24px; padding-top: 0px !important;} 
            .mapcha table thead tr th{border-left: none; border-right: none; padding: 10px 0px; border-bottom: 2px #000 solid;} .mapcha table tbody tr td{border-right: none;} .head-p{ padding-top: 15px; padding-bottom: 15px;} .mapcha table tbody tr td pre{min-height: 20px; font-size: 14px !important; font-family: glacial_indifferenceregular;} .pr-3{padding-right:10px !important;} .terms-spacing{padding-bottom:
                10px;} .spacing{padding: 2px 0px;} *{padding:0;margin:0;box-sizing:border-box;-webkit-border-vertical-spacing:0;-webkit-border-horizontal-spacing:0;font-size:14px}.print_receipt_invoice thead tr th{border-right:1px #000 solid; color: #000; border-bottom:1px #000 solid;border-top:1px #000 solid;border-top:none; padding: 5px;}.print_receipt_invoice thead tr:last-child{border-right:none}.print_receipt_invoice tbody tr td{padding: 10px 5px; border-right:1px #000 solid}.print_receipt_invoice tbody tr td pre{min-height:29px;text-align:left;white-space:normal;word-wrap:break-word; font-size: 11px;max-height: 29px;overflow:hidden;line-height: 1.5;}.print_receipt_invoice tbody tr td:last-child{border-right:none}.print_receipt_top-head tr td{padding:2px}.print_invoice_terms td table{text-align: left;}.print_invoice_last td table td{text-align: left;}.print_store_sign td:nth-child(2){text-align: right;}.print_invoice_last td table:last-child{margin-top: 40px;}.print_invoice_table_start table tbody tr td{font-size:13px;}.print_invoice_terms td{ border-left: none;}</style>";


            $printArray  = array();
            $store         = Store::find($store_id);
            $order_details = Invoice::where('invoice_id', $order_id)->first();

            $einvoice = EinvoiceDetails::where('invoice_id',$order_details->invoice_id)->where('status','Success')->first();
            $qrImage = '';
            if($einvoice && !empty($einvoice->signed_qr_code)){
              
               $qrImage      = $this->generateQRCode(['content'=>$einvoice->signed_qr_code]);
                //$qrImage      = $einvoice->qrcode_image_path;
            }


            $cart_q = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','0')->where('user_id', $order_details->user_id)->sum('qty');
            $cart_qt = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','1')->where('user_id', $order_details->user_id)->count('qty');
            $cart_qty = $cart_q + $cart_qt;
            $total_amount = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
            // dd($total_amount);
            $count_cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->count();

            if($order_details->transaction_type == 'return'){
                $invoice_title     = 'Credit Note';
            }

            $startitem   = 0;
            $getItem     = 8;
            $countitem   = $count_cart_product;
            $totalpage   = ceil($count_cart_product/$getItem);
            $sr          = 1;

            for($i=0;$i < $totalpage ; $i++) {
               
             $cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->skip($startitem)->take(8)->get();

            $startitem  = $startitem+$getItem;
            $startitem  = $startitem;
             
            $customer_address = '';
            if(isset($order_details->user->address->address1)){
                $customer_address .= $order_details->user->address->address1;
            }
            if(isset($order_details->user->address->address2)){
                $customer_address .= $order_details->user->address->address2;
            }

            $count = 1;
            $gst_tax = 0;
            $gst_listing = [];
            $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
            //dd($gst_list);
            $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
            $cgst = $sgst = $cess = 0 ;
            // dd($final_gst);
 
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
            $bill_logo_id = 5;
            $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
            if($vendorImage)
            {
                $bilLogo = env('ADMIN_URL').$vendorImage->path;
            } 
            $payments  = $order_details->payvia;
            $cash_collected = 0;  
            $cash_return    = 0;
            $net_payable    = $total_amount;

            //dd($payments);
            $mop_list = [];
            foreach ($payments as $payment) {
            $paymentdata = Mop::select('name')->where('code',$payment->method)->first();
            $mopname = '';
            if(isset($paymentdata->name)){
                $mopname = $paymentdata->name;
            }else{
                $mopname = '';
            }
            if ($payment->method == 'cash') {
            $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
            if($order_details->transaction_type == 'return'){
               $mop_list[] = [ 'mode' => $mopname, 'amount' => $payment->amount ];
            }else{
               $mop_list[] = [ 'mode' => $mopname, 'amount' => $payment->cash_collected-$cashReturn ];
            }
            } else {
            $mop_list[] = [ 'mode' => $mopname, 'amount' => $payment->amount ];
            }
            if ($payment->method == 'cash') {
            $cash_collected += (float) $payment->cash_collected;
            $cash_return += (float) $payment->cash_return;
            }
            /*Voucher Start*/
            if($payment->method == 'voucher_credit'){
            $voucher[] = $payment->amount;
            $net_payable = $net_payable-$payment->amount;
            }
            }
            $customer_paid = $cash_collected;
            $balance_refund= $cash_return;
            $total_discount = (float)$order_details->discount+(float)$order_details->manual_discount+(float)$order_details->bill_buster_discount;

               // $terms_conditions =  array('
               //  Product can be exchanged within 7 days of purchase. The product must be in it’s original and unused condition, along with all the original price tags, packing/boxes and barcodes received.','Not applicable to jewellery for hygiene purposes.','Original invoice copy must be produced.', 'We will only be able to exchange products. Afraid there will not be refund in any form.', 'There will be no exchange on discounted/sale products.
               //  ', 'In the case of damage or defect - the store team must be notified within 24 hours of purchase.' );
            $terms =  Terms::where('v_id',$v_id)->get();
                    $terms_condition = json_decode($terms);
                    foreach ($terms_condition as $value) {
                        $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                    }

            ########################
            ####### Print Start ####
            ########################
            
            
            //$data = '<body style="padding: 20px;">';
            
            //$data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"><table class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';
            /* $data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"> <table width="10%"><tr><td><img src="'.$bilLogo.'" alt="" height="80px"></td></tr>
           </table><table width="80%" class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';*/
        $data  .= '<table class="mapcha" width="90%" style=" margin-top: 20px; margin-bottom: 0px; margin-left: auto; margin-right: auto;">';
            $data  .= '<tr><td width="100%">
                            <table width="100%"><tr style="vertical-align: top;"><td class="head-p" width="20%"><img src="'.$bilLogo.'" alt="" height="80px"></td>
                            <td width="40%">
                            <table width="100%" align="left" style="color: #000;" >';
            $data  .=  '<tr style="padding: 5px;"><td class="head-p invoice bold">INVOICE</td></tr>';

            $data  .=  '<tr><td class="spacing "><span class="bold">Invoice #</span> &nbsp; '.$order_details->invoice_id.'</td></tr>';
            // if($store->address2){
             $data  .=  '<tr><td class="spacing "><span class="bold">Issue Date</span> &nbsp; '.date('d-M-Y', strtotime($order_details->created_at)).'</td></tr>';
             $data  .=  '<tr><td class="spacing bold"></td></tr>';

            // }
            // $data  .=  '<tr><td>'.$store->location.','.$store->pincode.','.$store->state.'</td></tr>';
            // if($store->gst){
            //  $data  .=  '<tr><td>GSTIN: '.$store->gst.'</td></tr>';
            // }
            // $data  .=  '<tr><td>Tel: '.$store->contact_number.'</td></tr>';
            // $data  .=  '<tr><td>Email: '.$store->email.'</td></tr>';
            // if(!empty($qrImage)){
            //     $data  .=  '<tr><td><img src='.$qrImage.'></td></tr>';    
            // }
            $data  .=  '</table></td>
                        <td width="40%">
                        <table width="100%" align="left" style="color: #000;" >';
                            
                        $data  .=  '<tr style="padding: 5px;"><td class="head-p bold">Issued by</td></tr>';
            
                        $data  .=  '<tr><td class="spacing bold">Mapcha Design Studio</td></tr>';
                        if($store->address2){
                         $data  .=  '<tr><td class="spacing">'.$store->address2.'</td></tr>';
                        }
                        $data  .=  '<tr><td class="spacing">'.$store->location.','.$store->pincode.','.$store->state.'</td></tr>';
                        if($store->gst){
                         $data  .=  '<tr><td class="spacing" style="padding-bottom: 10px; padding-top: 15px;">GST: '.$store->gst.'</td></tr>';
                        }
                        // $data  .=  '<tr><td>Tel: '.$store->contact_number.'</td></tr>';
                        // $data  .=  '<tr><td>Email: '.$store->email.'</td></tr>';
                        // if(!empty($qrImage)){
                        //     $data  .=  '<tr><td><img src='.$qrImage.'></td></tr>';    
                        // }
                        $data  .=  '</table></td></tr></table>';
                        $data .= '<hr>';
                    $data .= '<table width="100%"><tr style="vertical-align: top;"><td width="20%"></td>
                            <td width="40%" >
                            <table width="100%" align="left" style="color: #000;" >';
                            
            $data  .=  '<tr><td class="head-p bold">Client</td></tr>';

             $data  .=  '<tr><td class="spacing bold">'.@$order_details->user->first_name.' '.@$order_details->user->last_name.'</td></tr>';
            // if($store->address2){
             $data  .=  '<tr><td class="spacing">'.$customer_address.'</td></tr>';
             $data  .=  '<tr><td class="spacing">'.$order_details->customer_email.'</td></tr>';
             $data  .=  '<tr><td class="spacing">'.$order_details->customer_number.'</td></tr>';
            // }
            // $data  .=  '<tr><td>'.$store->location.','.$store->pincode.','.$store->state.'</td></tr>';
            // if($store->gst){
            //  $data  .=  '<tr><td>GSTIN: '.$store->gst.'</td></tr>';
            // }
            // $data  .=  '<tr><td>Tel: '.$store->contact_number.'</td></tr>';
            // $data  .=  '<tr><td>Email: '.$store->email.'</td></tr>';
            // if(!empty($qrImage)){
            //     $data  .=  '<tr><td><img src='.$qrImage.'></td></tr>';    
            // }
            $data  .=  '</table></td>
                        <td width="40%">
                        <table width="100%" align="left" style="padding-left: 5px; padding-right: 5px; color: #000;" >';
                            
                        $data  .=  '<tr style="padding: 5px;"><td class="head-p bold">Payment</td></tr>';
            
                        $data  .=  '<tr><td class="spacing bold">Payment Method</td></tr>';
                        foreach($mop_list as $mop){
                            $data .= '<tr><td style="text-transform: capitalize;">'.$mop['mode'].' (&#8377;'.$mop['amount'].') </td></tr>';
                        }
                        if($store->address2){
                         $data  .=  '<tr><td class="spacing bold">Card Details</td></tr>';
                        }
                        $data  .=  '<tr><td class="spacing bold">Bank Account</td></tr>';
                        $data  .=  '<tr><td class="spacing bold">UPI</td></tr>';
                        if($store->gst){
                         $data  .=  '<tr><td class="head-p" style="padding-bottom: 10px;">GST: '.@$order_details->cust_gstin.'</td></tr>';
                        }
                        // $data  .=  '<tr><td>Tel: '.$store->contact_number.'</td></tr>';
                        // $data  .=  '<tr><td>Email: '.$store->email.'</td></tr>';
                        // if(!empty($qrImage)){
                        //     $data  .=  '<tr><td><img src='.$qrImage.'></td></tr>';    
                        // }
                        $data  .=  '</table></td></tr></table>';
            $data  .= '<table width="100%"><tr><td><div  style="height: 235px; overflow: hidden; border-top: 2px #000 solid; border-bottom: 2px #000 solid; "  width="100%" ><table height="100%" width="100%" class="print_receipt_invoice" bgcolor="#fff" style="width: 100%; color: #000;">';
            $data  .= '<thead ><tr align="left">
                        <th width="15%" valign="center" class="bold">PRODUCT NAME</th>
                        <th width="8%" valign="center" class="bold">HSN CODE</th>
                        <th width="4%" valign="center" class="bold">QTY.</th>
                        <th width="7.5%" valign="center" class="bold">UNIT MRP</th>
                        <th width="4%" valign="center" class="bold">CGST</th>
                        <th width="4%" valign="center" class="bold">IGST</th>
                        <th width="7.5%" valign="center" class="bold">TOTAL</th></tr></thead><tbody>';
           
            $barcode = '';
            $hsn ='';
            $item_name ='';
            $qty  = '';
            $unit = '';
            $mrp  = '';
            $disc = '';
            $taxp = '';
            $taxb = '';

            $taxable_amount = 0;
            $total_csgt     = 0;
            $total_sgst     = 0;
            $total_cess     = 0;
            $total_discount  = 0;
            $total_amount   = 0;
            $total_inc_tax   = 0;
            $srp            = '';
            $barcode        = '';
            $hsn            = '';
            $item_name      = '';
            $qty            = '';
            $unit           = '';
            $mrp            = '';
            $TotalMrp           = '';
            $tax_cgst           = '';
            $tax_sgst           = '';

            foreach ($cart_product as $key => $value) {

                $remark = isset($value->remark)?' -'.$value->remark:'';
                $tdata  = json_decode($value->tdata);
                $itemLevelmanualDiscount=0;
                if($value->item_level_manual_discount!=null){
                    $iLmd = json_decode($value->item_level_manual_discount);
                    $itemLevelmanualDiscount= (float)$iLmd->discount;
                }
                $discount = $value->discount+$value->manual_discount + $value->bill_buster_discount+$itemLevelmanualDiscount;
                $taxper   = $tdata->cgst + $tdata->sgst;
                $taxable_amount += $tdata->taxable;
                $total_csgt  += $tdata->cgstamt;
                $total_sgst  += $tdata->sgstamt;
                $total_cess  += $tdata->cessamt;
                $total_discount += $discount;
                $totalmrp = $value->unit_mrp * $value->qty;
                $total_amount += $totalmrp;
                $cgst = $tdata->cgst;
                $sgst = $tdata->sgst;
                $itemName = substr($value->item_name, 0, 20);
                $total_inc_tax = $total_amount + $cgst + $sgst; 
                $barcode   .= '<pre>'.$value->barcode.'</pre>';
                $hsn       .= '<pre>'.$tdata->hsn.'</pre>';
                $item_name .= '<pre>'.$itemName.'</pre>';
                $qty       .= '<pre>'.$value->qty.'</pre>';
                $tax_cgst      .= '<pre>'.number_format($cgst,2).'</pre>';
                $tax_sgst      .= '<pre>'.number_format($sgst,2).'</pre>';
                $mrp       .= '<pre>'.number_format($value->unit_mrp,2).'</pre>';
                $TotalMrp      .= '<pre>'.number_format($totalmrp,2).'</pre>';
                $taxb      .= '<pre>'.$tdata->taxable.'</pre>';
                $sr++;
            }
                // dd($items);
            $data   .= '<tr align="left">';

                $data   .= '<td valign="top" class="mapcha">'.$item_name.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$hsn.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$qty.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$mrp.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$tax_cgst.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$tax_sgst.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$TotalMrp.'</td></tr>';
            $total_csgt = round($total_csgt,2);
            $total_sgst = round($total_sgst,2);
            $total_cess = round($total_cess,2);
            $data   .= '</tbody></table></td></tr></div></td></tr></table>';
            if($totalpage-1 == $i){
                $total_csgt       = 0;
                $total_sgst       = 0;
                $total_cess       = 0;
                $total_mrp        = 0;
                $total_amount  = 0;
                $total_discount   = 0;
                $invoiceData  = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->get();
                foreach($invoiceData as $invdata){
                    $Ntdata    = json_decode($invdata->tdata);
                    $itemLevelmanualDiscount=0;
                    if($invdata->item_level_manual_discount!=null){
                     $iLmd = json_decode($invdata->item_level_manual_discount);
                     $itemLevelmanualDiscount = (float)$iLmd->discount;
                    }
                    $discount = $invdata->discount+$invdata->manual_discount + $invdata->bill_buster_discount+$itemLevelmanualDiscount;
                $taxper   = $Ntdata->cgst + $Ntdata->sgst;
                $taxable_amount += $Ntdata->taxable;
                $total_csgt  += $Ntdata->cgstamt;
                $total_sgst  += $Ntdata->sgstamt;
                $total_cess  += $Ntdata->cessamt;
                $total_discount += $discount;
                $totalmrp = $invdata->unit_mrp * $invdata->qty;
                $total_amount += $totalmrp;
                $cgst = $tdata->cgst;
                $sgst = $tdata->sgst;
                $total_inc_tax = $total_amount + $cgst + $sgst; 
                }
            $data .= '<table width="100%"><tr><td width="60%"></td>
                    <td width="40%">
                        <table width="100%"><tr><td align="left" style="padding-top: 10px;" width="50%" class="terms-spacing bold">TOTAL</td><td align="left"  width="30%" class="terms-spacing pr-3 bold">'.number_format($total_amount,2).'</td></tr>
                        <tr><td align="left" class="terms-spacing bold"  width="50%">DISCOUNT</td><td align="left" class="terms-spacing bold pr-3"  width="30%">'.number_format($total_discount,2).'</td></tr>
                        <tr><td align="left" class="terms-spacing bold"  width="50%">CGST</td><td align="left" class="terms-spacing bold pr-3"  width="30%">'.number_format($total_csgt,2).'</td></tr>
                        <tr><td align="left" class="terms-spacing bold"  width="50%">IGST</td><td align="left" class="terms-spacing bold pr-3"  width="30%">'.number_format($total_sgst,2).'</td></tr>
                        <tr><td align="left" class="terms-spacing bold"  width="50%" style="padding-bottom: 10px;" >TOTAL INCL. TAX</td><td align="left" class="terms-spacing bold pr-3"  width="30%">'.number_format($total_inc_tax,2).'</td></tr>                        
                        </table>
                    </td>
            </tr></table>';
        }else{
            $data .= '<table width="100%" style="padding: 5px;" height="160px">
                                <tr>
                                    <td width="60%"></td>
                                    <td width="40%">
                                        <table width="100%">
                                            <tr align="right">
                                                <td width="100%"><b>Continue..</b></td>
                                                <td width="30%"></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            ';
        }
            $data   .= '<table width = "100%"><tr class="print_invoice_terms"><td><table bgcolor="#fff" style="width: 100%; color: #000; border: 2px #000 solid; border-bottom: none; border-left: none; border-right: none; padding-bottom: 15px;">
                <tr width="100%">
                    <td class="head-p bold" style="padding-top: 10px;">Terms and Conditions:</td >
                </tr>';
             foreach($terms_conditions as $term){
                $data .= '<tr width="100%"><td class="terms-spacing">&bull;'.$term.'</td></tr>';
             }
            $data    .= '</table></td></tr></table>';
            $data    .= '<table width="100%"><tr class="print_invoice_last">
            <td width="30%" class="bold" >Instagram&nbsp; @mapcha.studio</td><td width="40%" class="bold" align="center" style="font-size: 18px;">Thank you for your purchase!</td><td width="30%" align="right" class="bold">www.mapcha.co</td></tr>
           <tr class="print_invoice_last">
            <td width="30%" class=" bold">Facebook&nbsp; Mapcha</td><td width="40%"></td><td width="30%" class="bold" align="right">info@mapcha.co</td></tr></table></td></tr></table>';
             
            if($totalpage > 1){
                $data .= '<br><hr>';
            }
             
        }
        $return = array('status'=>'success','style'=>$style,'html'=>$data) ;
        return $return;
    }//End of print_html_page


    public function print_html_page_for_RJ_Corp($request){

            $v_id       = $request->v_id;
            $store_id   = $request->store_id; 
            $c_id       = $request->c_id;
            $order_id   = $request->order_id;
            $product_data= [];
            $gst_list    = [];
            $final_gst   = [];
            $detatch_gst = [];
            $rounded     = 0;
            $data    = '';
            $terms_conditions =  array('');
            $invoice_title = 'Retail Invoice';
            $style = "<style>hr{background-color: #000;} .bold{font-weight: bold;} body{font-family: glacial_indifferenceregular; font-size: 14px;} .head-p.invoice{font-size: 24px; padding-top: 0px !important;} 
            .mapcha table thead tr th{border-left: none; padding: 10px 10px;} .head-p{ padding-top: 15px; padding-bottom: 15px;} .mapcha table tbody tr td pre{min-height: 20px; font-size: 14px !important; font-family: glacial_indifferenceregular;} .pr-3{padding-right:10px !important;} .terms-spacing{padding-bottom:
                10px;} .spacing{padding: 2px 0px;} *{padding:0;margin:0;box-sizing:border-box;-webkit-border-vertical-spacing:0;-webkit-border-horizontal-spacing:0;font-size:14px}.print_receipt_invoice thead tr th{border-right:1px #000 solid;color: #000; border-bottom:1px #000 solid;border-top:1px #000 solid;padding: 5px; font-size: 12px;}.print_receipt_invoice thead tr:last-child{border-right:none}.print_receipt_invoice tbody tr td{border-bottom: none !important;border-right:1px #000 solid; padding: 0px;}.print_receipt_invoice tbody tr td pre{border-bottom: 1px #000 solid; min-height:39px;text-align:left;white-space:normal;word-wrap:break-word; font-size: 11px;overflow:hidden;line-height: 19px; padding: 0px 5px;}.print_receipt_invoice tbody tr td:last-child{border-right:none}.print_receipt_top-head tr td{padding:2px}.print_invoice_terms td table{text-align: left;}.print_invoice_last td table td{text-align: left;}.print_store_sign td:nth-child(2){text-align: right;}.print_invoice_last td table:last-child{margin-top: 40px;}.print_invoice_table_start table tbody tr td{font-size:13px;}.print_invoice_terms td{ border-left: none;}.mapcha table thead tr th:last-child{border-right: none;}</style>";


            $printArray  = array();
            $store         = Store::find($store_id);
            $venderData    = DB::table('vendor')->where('id', $v_id)->first();
            // dd($venderData,$v_id); 
            $order_details = Invoice::where('invoice_id', $order_id)->first();

            $einvoice = EinvoiceDetails::where('invoice_id',$order_details->invoice_id)->where('status','Success')->first();
            $qrImage = '';
            if($einvoice && !empty($einvoice->signed_qr_code)){
              
               $qrImage      = $this->generateQRCode(['content'=>$einvoice->signed_qr_code]);
                //$qrImage      = $einvoice->qrcode_image_path;
            }


            $cart_q = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','0')->where('user_id', $order_details->user_id)->sum('qty');
            $cart_qt = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','1')->where('user_id', $order_details->user_id)->count('qty');
            $cart_qty = $cart_q + $cart_qt;
            $total_amount = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
            // dd($total_amount);
            $count_cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->count();

            if($order_details->transaction_type == 'return'){
                $invoice_title     = 'Credit Note';
            }

            $startitem   = 0;
            $getItem     = 8;
            $countitem   = $count_cart_product;
            $totalpage   = ceil($count_cart_product/$getItem);
            $sr          = 1;
            $salesman    = '';
            for($i=0;$i < $totalpage ; $i++) {
            // DB::enableQueryLog();
             $cart_product = InvoiceDetails::leftjoin('vendor_auth', 'vendor_auth.id', 'invoice_details.salesman_id')->leftjoin('vendor_sku_detail_barcodes','vendor_sku_detail_barcodes.barcode','invoice_details.barcode')->leftjoin('vendor_sku_flat_table','vendor_sku_flat_table.item_id','vendor_sku_detail_barcodes.item_id')->where('invoice_details.t_order_id', $order_details->id)->where('invoice_details.v_id', $order_details->v_id)->where('invoice_details.store_id', $order_details->store_id)->where('invoice_details.user_id', $order_details->user_id)->groupBy('vendor_sku_detail_barcodes.barcode')->skip($startitem)->take(8)->get();
             // dd(DB::getQueryLog());
             // dd($cart_product);
             // foreach ($cart_product as $key => $value) {
             //    // if(!empty($value->getSalesman)){

             //         $salesman = isset($value->getSalesman->first_name)?$value->getSalesman->first_name:''; 
              
             //    // }else{
             //    //     // dd('hi');
             //    // }
             // }
            $startitem  = $startitem+$getItem;
            $startitem  = $startitem;
             
            $customer_address = '';
            if(isset($order_details->user->address->address1)){
                $customer_address .= $order_details->user->address->address1;
            }
            if(isset($order_details->user->address->address2)){
                $customer_address .= $order_details->user->address->address2;
            }

            $count = 1;
            $gst_tax = 0;
            $gst_listing = [];
            $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
            //dd($gst_list);
            $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
            $cgst = $sgst = $cess = 0 ;
            // dd($final_gst);
 
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
            $bill_logo_id = 5;
            $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
            if($vendorImage)
            {
                $bilLogo = env('ADMIN_URL').$vendorImage->path;
            } 
            $payments  = $order_details->payvia;
            $cash_collected = 0;  
            $cash_return    = 0;
            $net_payable    = $total_amount;

            //dd($payments);
            $mop_list = [];
            foreach ($payments as $payment) {
            $paymentdata = Mop::select('name')->where('code',$payment->method)->first();
            $mopname = '';
            if(isset($paymentdata->name)){
                $mopname = $paymentdata->name;
            }else{
                $mopname = '';
            }
            // dd($payment->amount);
            if ($payment->method == 'cash') {
            $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
            if($order_details->transaction_type == 'return'){
               $mop_list[] = [ 'mode' => $mopname, 'amount' => $payment->amount ];
            }else{
               $mop_list[] = [ 'mode' => $mopname, 'amount' => $payment->cash_collected-$cashReturn ];
            }
            } else {
            $mop_list[] = [ 'mode' => $mopname, 'amount' => $payment->amount ];
            }
            if ($payment->method == 'cash') {
            $cash_collected += (float) $payment->cash_collected;
            $cash_return += (float) $payment->cash_return;
            }
            /*Voucher Start*/
            if($payment->method == 'voucher_credit'){
            $voucher[] = $payment->amount;
            $net_payable = $net_payable-$payment->amount;
            }
            }
            $customer_paid = $cash_collected;
            $balance_refund= $cash_return;
            $total_discount = (float)$order_details->discount+(float)$order_details->manual_discount+(float)$order_details->bill_buster_discount;
            
            $state = State::where('id', $order_details->store_gstin_state_id)->first();
            $state_name = convertState($store->state);
            // dd($state->name,$state_name);
            $refinvoicedetails = DB::table('invoices')->select('orders.ref_order_id')->join('orders','invoices.ref_order_id','=','orders.order_id')
                ->where('invoices.invoice_id',$order_details->invoice_id)
                ->where('invoices.v_id', $order_details->v_id)
                ->first(); 

                $ref_invoice = DB::table('orders')->select('invoices.date')->join('invoices','invoices.invoice_id','=','orders.ref_order_id')
                ->where('invoices.invoice_id',$refinvoicedetails->ref_order_id)
                ->where('invoices.v_id', $order_details->v_id)
                ->first(); 

               // $terms_conditions =  array('1.The below T&C are in addition to the T&C as per Nike Guidelines (Nike T&C displayed at the store).','2.Merchandise can be exchanged within 7 days from the date of purchase in unused condition with original packing along with the price tag and the original Retail Invoice of RJ Corp Ltd.','3.Manufacturing defect in the product will be considered for exchange within 6 months of purchase of the original Retail invoice of RJ Corp Ltd.','4.Exchange cannot be claimed on products held to have been used inappropriately determined by Nike.','5.Exchange will be offered for the same purchase value In case of price differential, the store will not refund cash to the customer.','6.Claims will be restricted to the extent of the purchase value of the product only .','7.In case of any conflict the Nike T&C will prevail.','8.Courts at New Delhi shall have the exclusive Jurisdiction to entertain all disputes Courts at New Delhi shall have the exclusive Jurisdiction to entertain all disputes.','9.No Exchange/Return on Discounted Items.');
                $terms =  Terms::where('v_id',$v_id)->get();
                    $terms_condition = json_decode($terms);
                    foreach ($terms_condition as $value) {
                        $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                    }
               // dd($order_details);
            ########################
            ####### Print Start ####
            ########################
            
            
            //$data = '<body style="padding: 20px;">';
            
            //$data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"><table class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';
            /* $data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"> <table width="10%"><tr><td><img src="'.$bilLogo.'" alt="" height="80px"></td></tr>
           </table><table width="80%" class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';*/
        $data  .= '<table width="90%" style=" margin-top: 20px; margin-bottom: 0px; margin-left: auto; margin-right: auto;">';
            $data  .= '<tr><td width="100%">
                            <table width="100%"><tr style="vertical-align: top;"><td class="head-p" width="20%"><img src="'.$bilLogo.'" alt="" height="90px"></td>
                            <td width="60%">
                            <table width="100%" style="color: #000; text-align: center;" >';
            // $data  .=  '<tr><td class="spacing "><b>JL2</b></td></tr>';

            $data  .=  '<tr><td class="spacing "><b>'.$venderData->name.'</b></td></tr>';
            // if($store->address2){
            $data  .=  '<tr><td class="spacing ">'.$store->address1.'</td></tr>';
            if($store->address2){
                $data  .=  '<tr><td class="spacing ">'.$store->address2.'</td></tr>';
            }
            $data  .=  '<tr><td class="spacing ">'.$store->location.'-'.$store->pincode.'</td></tr>';
            $data  .=  '<tr><td class="spacing ">Telephone No : '.$store->contact_number.'</td></tr>';
            $data  .=  '<tr><td class="spacing ">GSTIN : '.$store->gst.'</td></tr>';
            $data  .=  '<tr><td class="spacing ">CIN : U62200DL1980PLC010262</td></tr>';
             $data  .=  '<tr><td class="spacing bold"></td></tr>';
            $data  .=  '</table></td>
                         <td width="20% ; font-size:24x;" >Customer Copy</td>
                        </tr></table>';

            // $data  .= '<tr><td width="100%">
            //                 <table width="100%" style="color: #000; text-align: center;" >';
            // $data  .=  '<tr><td class="spacing ">DUPLICATE</td></tr></table></td></tr>';

            $data  .= '<tr><td width="100%">
                            <table width="100%" style="color: #000; text-align: center;" >';
            $data  .=  '<tr><td class="spacing "><b><h4>TAX INVOICE</h4></b></td></tr></table></td></tr>';


            $data .='<table width="100%" style="padding-bottom: 10px; line-height: 1.4; padding-top: 10px;">
                    <tr>
                        <td style="width: 50%;">
                            <table width="100%">
                                <tr>
                                    <td>Reverse Charge</td>
                                    <td>:</td>
                                    <td>No</td>
                                </tr>
                                <tr>
                                    <td>Invoice No.</td>
                                    <td>:</td>
                                    <td>'.$order_details->invoice_id.'</td>
                                </tr>
                                <tr>
                                    <td>Invoice Date</td>
                                    <td>:</td>
                                    <td>'.date('d-M-Y', strtotime($order_details->created_at)).'</td>
                                </tr>
                                <tr>
                                    <td>State</td>
                                    <td>:</td>
                                    <td>'.$store->state.'('.mb_substr($store->state_id, 0, 2).')</td>
                                </tr>
                            </table>
                        </td>
                        <td style="width: 50%;">
                            <table width="50%">
                                <tr>
                                    <td>Transport Mode</td>
                                    <td>:</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>Vehicle Number</td>
                                    <td>:</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>Date of Supply</td>
                                    <td>:</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>Place of Supply</td>
                                    <td>:</td>
                                    <td></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>';
                $data .='<table width="100%" style="padding-bottom: 10px;">
                    <tr>
                        <td style="width: 50%;">
                            <table>
                                <tr>
                                    <td >Name Address</td>
                                    <td>:</td>
                                    <td>'.@$order_details->user->first_name.' '.@$order_details->user->last_name. '&nbsp;&nbsp;'.$order_details->user->mobile.'</td>
                                </tr>
                            </table>
                        </td>
                        <td style="width: 25%;">
                        <table>
                            <tr>
                                <td>GSTIN NO</td>
                                <td>:</td>
                                <td></td>
                            </tr>
                        </table>
                        </td>
                        <td style="width: 25%;">
                        <table>
                            <tr>
                                <td>State Code</td>
                                <td>:</td>
                                <td>'.$state_name.'</td>
                            </tr>
                        </table>
                        </td>
                    </tr>
            </table>';  
            $data  .= '<table width="100%"><tr><td><div  style="overflow: hidden; border-bottom: 1px #000 solid; "  width="100%" ><table height="100%" width="100%" class="print_receipt_invoice" bgcolor="#fff" style="width: 100%; color: #000;border: 1px #000 solid;border-top: none;border-bottom: none;">';
            $data  .= '<thead><tr align="left">
                        <th width="2%" align="center" class="bold">Sr No.</th>
                        <th width="5%" align="left" class="bold">Name of Product</th>
                        <th width="6%" align="left" class="bold">Size</th>
                        <th width="6%" align="left" class="bold" style="white-space: nowrap;">Sale Staff</th>
                        <th width="4%" align="left" class="bold">Qty</th>
                        <th width="4%" align="center" class="bold">Rate Incl. GST</th>
                        <th width="4%" align="right" class="bold">Amount Incl. GST</th>
                        <th width="4%" align="right" class="bold">Discount (%)</th>
                        <th width="4%" align="right" class="bold">Taxable Value</th>
                        <th width="5%" align="right" class="bold">CGST% Amount</th>
                        <th width="5%" align="right" class="bold">SGST% Amount</th>
                        <th width="5%" align="right" class="bold">IGST% Amount</th>
                        <th width="5%" align="right" class="bold">Total</th>
                        </tr></thead><tbody>';
           
             $barcode = '';
            $hsn ='';
            $item_name ='';
            $qty  = '';
            $unit = '';
            $mrp  = '';
            $disc = '';
            $taxp = '';
            $taxb = '';

            $taxable_amount = 0;
            $total_csgt     = 0;
            $total_sgst     = 0;
            $total_cess     = 0;
            $total_igst     = 0;
            $total_qty      = 0;
            $total_discount  = 0;
            $total_amount   = 0;
            $total_amt_bef_tax   = 0;
            $tax_amount    = 0;
            $totalAmtBefDis = 0;
            // $total_tax_amount  = 0;
            $srp            = '';
            $barcode        = '';
            $disc           = '';
            $hsn            = '';
            $item_name      = '';
            $qty            = '';
            $unit           = '';
            $mrp            = '';
            $TotalMrp           = '';
            $TotalAmt           = '';
            $tax_cgst           = '';
            $tax_sgst           = '';
            // $color          = '';
            $size           = '';
            // $tax_name       = '';
            $tax_igst       = '';  
            $taxableAmount   = '';
            $Salesman        = '';
            // $tax_cgstamt    = '';
            // $tax_sgstamt    = '';
            // $tax_igstamt    = '';
            // $taxable        = '';   
            // $tax_cess       = '';  
            // $taxamt         = '';
            // $taxcgst        = '';
            // $taxsgst        = '';
            // $taxigst        = '';
            // $taxcess        = '';
            // $taxcessper        = '';
            // $Salesman       = '';
            foreach ($cart_product as $key => $value) {
                // dd($value);
                $remark = isset($value->remark)?' -'.$value->remark:'';
                $tdata  = json_decode($value->tdata);
                $itemLevelmanualDiscount=0;
                if($value->item_level_manual_discount!=null){
                    $iLmd = json_decode($value->item_level_manual_discount);
                    $itemLevelmanualDiscount= (float)$iLmd->discount;
                }
                $discount = $value->discount+$value->manual_discount + $value->bill_buster_discount+$itemLevelmanualDiscount;
                $taxper   = $tdata->cgst + $tdata->sgst;
                // $taxable_amount += $tdata->taxable;
                $total_csgt  += $tdata->cgstamt;
                $total_sgst  += $tdata->sgstamt;
                $total_cess  += $tdata->cessamt;
                $total_igst  += $tdata->igstamt;
                $total_discount += $discount;
                $totalmrp = $value->unit_mrp * $value->qty;
                 // $totalmrp = $value->unit_mrp * $value->qty;
                if($tdata->tax_type == 'INC'){
                    $taxvalue = $totalmrp - $discount;
                    $taxdata = 1 + $taxper/100;
                    $taxableamt = $taxvalue/$taxdata;
                    $gst = $taxableamt * $taxper/100;
                }else{
                    $gst = ($totalmrp - $discount) * $taxper/100;
                }
                $total_gst += $gst;
                if($tdata->tax_type == 'INC'){ 
                    $totalamt = $totalmrp;
                    $totaltaxamt = $tdata->cgstamt + $tdata->sgstamt + $tdata->igstamt + $tdata->cessamt;
                    $taxable_amt = $totalamt - $totaltaxamt;
                }
                else{
                    $totalamt = $totalmrp + $gst;
                    $totaltaxamt = $tdata->cgstamt + $tdata->sgstamt + $tdata->igstamt + $tdata->cessamt;
                    $taxable_amt = $totalamt - $totaltaxamt;
                }
                $taxable_amount += $taxable_amt;
                $total_amount += $totalamt;
                $cgst = $tdata->cgst;
                $sgst = $tdata->sgst;
                $igst = $tdata->igst;
                $igst = $tdata->cgstamt;
                $igst = $tdata->sgstamt;
                $igst = $tdata->igstamt;
                // dd($total_amount);
                $total_qty  += $value->qty;
                 $totaltaxamount = $total_csgt + $total_sgst + $total_igst + $total_cess;  
                $total_inc_tax = $total_amount - $totaltaxamount; 
                $tax_amount = $total_amount - $total_inc_tax;
                $totaltaxamt = $tdata->cgstamt + $tdata->sgstamt + $tdata->igstamt + $tdata->cessamt;


                $itemName = substr($value->item_name, 0, 8);

                 // if(!empty($value->getSalesman)){

                     $salesman = isset($value->first_name)?$value->first_name:'N/A'; 
                // }
                // $total_inc_tax = $total_amount + $cgst + $sgst; 
                $srp       .= '<pre style="text-align: center; justify-content:center; align-items: center; display: flex;">'.$sr.'</pre>';
                $Salesman   .= '<pre style="text-align: left; justify-content:center; align-items: center; display: flex;">'.$salesman.'</pre>';
                // $hsn       .= '<pre style="text-align: left;white-space: nowrap;">'.$tdata->hsn.'</pre>';
                $item_name .= '<pre style="justify-content:center; align-items: center; display: flex;">'.$itemName.'<b>&nbsp;HSN&nbsp;</b>'.$tdata->hsn.'&nbsp;</pre>';
                $qty       .= '<pre style="justify-content:center; align-items: center; display: flex;">'.$value->qty.'</pre>';
                $taxableAmount .= '<pre style="justify-content:center; align-items: center; display: flex;">'.$taxable_amt.'</pre>';
                // $color     .= '<pre style="text-align: left;">'.$value->va_color.'</pre>';
                $size     .= '<pre style="justify-content:center; align-items: center; display: flex;">'.$value->va_size.'</pre>';
                $disc      .= '<pre style="justify-content:center; align-items: center; display: flex;">'.number_format($discount,2).'</pre>';
                $tax_cgst      .= '<pre style="text-align: center;">'.number_format($cgst,2).'<br>'.number_format($tdata->cgstamt,2).'</pre>';
                $tax_sgst      .= '<pre style="text-align: center;">'.number_format($sgst,2).'<br>'.number_format($tdata->sgstamt,2).'</pre>';
                $tax_igst      .= '<pre style="text-align: center;">'.number_format($igst,2).'<br>'.number_format($tdata->igstamt,2).'</pre>';
                // $tax_cgstamt      .= '<pre style="text-align: center;">'.number_format($tdata->cgstamt,2).'</pre>';
                // $tax_sgstamt      .= '<pre style="text-align: center;">'.number_format($tdata->sgstamt,2).'</pre>';
                // $tax_igstamt      .= '<pre style="text-align: center;">'.number_format($tdata->igstamt,2).'</pre>';
                $mrp       .= '<pre style="text-align: right;">'.number_format($value->unit_mrp,2).'</pre>';
                $TotalMrp      .= '<pre style="justify-content:center; align-items: center; display: flex;">'.number_format($totalmrp,2).'</pre>';
                $TotalAmt      .= '<pre style="justify-content:center; align-items: center; display: flex;">'.number_format($totalamt,2).'</pre>';
                // $taxb      .= '<pre style="text-align: center;">'.$tdata->taxable.'</pre>';
                //  if(!empty($salesman)){
                // $Salesman .= '<pre style="text-align: center;">'.@$salesman.'</pre>';    
                // }
                $sr++;
            }
        // dd($taxable);
            $data   .= '<tr align="left">';

                $data   .= '<td valign="top" class="mapcha">'.$srp.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$item_name.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$size.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$Salesman.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$qty.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$TotalAmt.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$TotalAmt.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$disc.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$taxableAmount.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$tax_cgst.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$tax_sgst.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$tax_igst.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$TotalAmt.'</td></tr>';
            $total_csgt = round($total_csgt,2);
            $total_sgst = round($total_sgst,2);
            $total_cess = round($total_cess,2);
            $data   .= '</tbody>
            <tfoot><tr><td colspan="2"></td><td colspan="1" style="padding: 5px;"><b>Total</b></td><td colspan="1"></td><td colspan="1" style="padding: 5px; text-align: center;"><b>'.$total_qty.'</b></td><td colspan="1"></td><td colspan="1" style="padding: 5px; text-align: center;"><b>'.number_format($total_amount,2).'</b></td><td colspan="1"></td><td colspan="1" style="text-align: center;">'.number_format($taxable_amount,2).'</td><td colspan="1" style="text-align: center;">'.number_format($total_csgt,2).'</td><td colspan="1" style="text-align: center;">'.number_format($total_sgst,2).'</td><td colspan="1" style="text-align: center;">'.number_format($total_igst,2).'</td><td colspan="1" style="text-align: center;">'.number_format($total_amount,2).'</td></tr></tfoot></table></td></tr></div></td></tr></table>';
            if($totalpage-1 == $i){
                $total_csgt       = 0;
                $total_sgst       = 0;
                $total_cess       = 0;
                $total_mrp        = 0;
                $total_igst       = 0;
                $total_qty        = 0;
                $total_amount  = 0;
                $taxable_amount    = 0;
                $total_discount   = 0;
                $totalAmtBefDis = 0;
                $total_amt_bef_tax  = 0;
                $invoiceData  = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->get();
                foreach($invoiceData as $invdata){
                    $Ntdata    = json_decode($invdata->tdata);
                    // dd($Ntdata);
                    $itemLevelmanualDiscount=0;
                    if($invdata->item_level_manual_discount!=null){
                     $iLmd = json_decode($invdata->item_level_manual_discount);
                     $itemLevelmanualDiscount = (float)$iLmd->discount;
                    }
                    $discount = $invdata->discount+$invdata->manual_discount + $invdata->bill_buster_discount+$itemLevelmanualDiscount;
                    $taxper   = $Ntdata->cgst + $Ntdata->sgst;
                    // $taxable_amount += $Ntdata->taxable;
                    $total_csgt  += $Ntdata->cgstamt;
                    $total_sgst  += $Ntdata->sgstamt;
                    $total_igst  += $Ntdata->igstamt;
                    $total_cess  += $Ntdata->cessamt;
                    $total_discount += $discount;
                    $totalmrp = $invdata->unit_mrp * $invdata->qty;


                    if($tdata->tax_type == 'INC'){
                        $taxvalue = $totalmrp - $discount;
                        $taxdata = 1 + $taxper/100;
                        $taxableamt = $taxvalue/$taxdata;
                        $gst = $taxableamt * $taxper/100;
                    }else{
                        $gst = ($totalmrp - $discount) * $taxper/100;
                    }
                    $total_gst += $gst;
                    if($tdata->tax_type == 'INC'){ 
                        $totalamt = $totalmrp;
                        $totaltaxamt = $Ntdata->cgstamt + $Ntdata->sgstamt + $Ntdata->igstamt + $Ntdata->cessamt;
                        $taxable_amt = $totalamt - $totaltaxamt;
                    }
                    else{
                        $totalamt = $totalmrp + $gst;
                        $totaltaxamt = $Ntdata->cgstamt + $Ntdata->sgstamt + $Ntdata->igstamt + $Ntdata->cessamt;
                        $taxable_amt = $totalamt - $totaltaxamt;
                    }
                    $taxable_amount += $taxable_amt;
                    // dd($taxable_amount);
                    $total_amount += $totalamt;
                    $total_amt_bef_dis = $total_amount - $total_discount;
                    // $totalAmtBefDis +=  $total_amt_bef_dis;
                    // $cgst = $tdata->cgst;
                    // $sgst = $tdata->sgst;
                // $tax_cgst = $Ntdata->cgstamt;
                // $tax_sgst = $Ntdata->sgstamt;
                // $tax_igst = $Ntdata->igstamt;
                    $total_qty  += $invdata->qty;
                    $cess_percentage = $Ntdata->cess;
                    $tax_cess = $Ntdata->cessamt;
                    $taxname = $Ntdata->tax_name;
                // print_r($invdata->unit_mrp);
                    $totaltaxamount = $total_csgt + $total_sgst + $total_igst + $total_cess;  
                    $total_amt_bef_tax = $total_amount - $totaltaxamount;
                }
// dd($total_amt_bef_dis);
                $data .='<table width="100%" style="margin-top: 10px;"><tr><td style="width: 55%; vertical-align: top;">
                    <table width="100%" style="border: 1px #000 solid; padding: 5px; margin-bottom: 10px;"><tr style="border="1px #000 solid;"><td><b>Total Invoice Amount in Words :</b>Rupees '.ucfirst(numberTowords(round($total_amount))).' Only
                    </td></tr>
                    <tr><table width="100%" style="border: 1px #000 solid; padding: 5px;"><tr><td style="padding-bottom: 10px;"><b>TERMS AND CONDITIONS</b></td></tr>';
                    foreach($terms_conditions as $term){
                        $data .= '<tr><td style="padding-bottom: 5px; font-size: 12px;">'.$term.'</td></tr>';
                    }
                    $data .= '<tr><td style="padding-top: 20px; text-align: center; font-weight: 600; font-size: 16px;"><b>Thank You Visit Again</b></td></tr>
                    </table>
                </td>
                <td style="width: 1%"></td>
                <td style="width: 44%; vertical-align: top;">
                        <table width="100%" style="border: 1px #000 solid;"><tr><td style="border-bottom: 1px #000 solid; padding: 3px; border-right: 1px #000 solid;">Total Amount Before Discount</td><td style="border-bottom: 1px #000 solid; padding: 3px; text-align: right;">'.number_format($total_amt_bef_dis,2).'</td></tr>
                    <tr><td style="border-bottom: 1px #000 solid; padding: 3px; border-right: 1px #000 solid;">Less Discount Amount</td><td style="border-bottom: 1px #000 solid; padding: 3px; text-align: right;">'.number_format($total_discount,2).'</td></tr>
                    <tr><td style="border-bottom: 1px #000 solid; padding: 3px; border-right: 1px #000 solid;"><b>Taxable Amount</b></td><td style="border-bottom: 1px #000 solid; padding: 3px; text-align: right;">'.number_format($taxable_amount,2).'</td></tr>
                    <tr><td style="border-bottom: 1px #000 solid; padding: 3px; border-right: 1px #000 solid;">ADD : CGST</td><td style="border-bottom: 1px #000 solid; padding: 3px; text-align: right;">'.number_format($total_csgt,2).'</td></tr>
                    <tr><td style="border-bottom: 1px #000 solid; padding: 3px; border-right: 1px #000 solid;">ADD : SGST / UTGST </td><td style="border-bottom: 1px #000 solid; padding: 3px; text-align: right;">'.number_format($total_sgst,2).'</td></tr>
                    <tr><td style="border-bottom: 1px #000 solid; padding: 3px; border-right: 1px #000 solid;">ADD : IGST </td><td style="border-bottom: 1px #000 solid; padding: 3px; text-align: right;">'.number_format($total_igst,2).'</td></tr>
                    <tr><td style="border-bottom: 1px #000 solid; padding: 3px; border-right: 1px #000 solid;"><b>Tax Amount : GST</b></td><td style="border-bottom: 1px #000 solid; padding: 3px; text-align: right;">'.number_format($totaltaxamount,2).'</td></tr>
                    <tr><td style="border-right: 1px #000 solid; padding: 3px;"><b>Total Amount After Tax</b></td><td style="padding: 3px; text-align: right;">'.number_format($total_amount,2).'</td></tr></table>
                    <table width="100%" style="border: 1px #000 solid; padding: 5px; margin-top: 10px;"><tr><td>Certified that the particulars given above are true and correct</td></tr><tr style="text-align: center; font-size: 16px; font-weight: 600;"><td style="padding: 20px;">FOR RJ CORP LTD.</td></tr><tr style="text-align: center; font-size: 16px; font-weight: 600;"><td>CASHIER</td></tr></table>
                </td>
                </tr></table>';
            }else{
                $data   .= '<table width="100%" style="padding: 5px;">
                                <tr>
                                    <td width="60%"></td>
                                    <td width="40%">
                                        <table width="100%">
                                            <tr align="right">
                                                <td width="100%"><b>Continue..</b></td>
                                                <td width="30%"></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            </td></tr></table></td></tr>';

                
            }

            if($totalpage > 1){
                $data .= '<br><hr>';
            }
             
        }
        // dd($data);
        $return = array('status'=>'success','style'=>$style,'html'=>$data);
        return $return;
    }//End of print_html_page


    public function biba_A4_print_html_page($request){

            $v_id       = $request->v_id;
            $store_id   = $request->store_id; 
            $c_id       = $request->c_id;
            $order_id   = $request->order_id;
            $product_data= [];
            $gst_list    = [];
            $final_gst   = [];
            $detatch_gst = [];
            $rounded     = 0;
            $data    = '';
            $terms_conditions =  array('');
            $invoice_title = 'TAX INVOICE';
            $style = "<style>hr{background-color: #000;} .bold{font-weight: bold;} body{font-family: glacial_indifferenceregular; font-size: 14px;} .head-p.invoice{font-size: 24px; padding-top: 0px !important;} 
            .mapcha table thead tr th{border-left: none; padding: 4px;} .head-p{ padding-top: 15px; padding-bottom: 15px;} .mapcha table tbody tr td pre{font-family: glacial_indifferenceregular; border-bottom: 1px #000 solid; padding: 4px;} .pr-3{padding-right:4px !important;} .terms-spacing{padding-bottom:
                4px;} .spacing{padding: 2px 0px;} *{padding:0;margin:0;box-sizing:border-box;-webkit-border-vertical-spacing:0;-webkit-border-horizontal-spacing:0;font-size:11px}.print_receipt_invoice thead tr th{background-color: #f4f4f4; border-right:1px #000 solid; color: #000; border-bottom:1px #000 solid;border-top:1px #000 solid;padding: 5px;}.print_receipt_invoice thead tr:last-child{border-right:none}.print_receipt_invoice tbody tr td{border-right:1px #000 solid}.print_receipt_invoice tbody tr td pre{text-align:left;white-space:normal;word-wrap:break-word; font-size: 11px;max-height: 29px;overflow:hidden;line-height: 1.5;}.print_receipt_invoice tbody tr td:last-child{border-right:none}.print_receipt_top-head tr td{padding:2px}.print_invoice_terms td table{text-align: left;}.print_invoice_last td table td{text-align: left;}.print_store_sign td:nth-child(2){text-align: right;}.print_invoice_last td table:last-child{margin-top: 40px;}.print_invoice_table_start table tbody tr td{font-size:13px;}.mapcha table tbody tr td pre:last-child{border-bottom: none;}.print_invoice_terms td{ border-left: none;}.mapcha table thead tr th:last-child{border-right: none;}</style>";


            $printArray  = array();
            $store         = Store::find($store_id);
            $order_details = Invoice::where('invoice_id', $order_id)->first();

            $einvoice = EinvoiceDetails::where('invoice_id',$order_details->invoice_id)->where('status','Success')->first();
            $qrImage = '';
            if($einvoice && !empty($einvoice->signed_qr_code)){
              
               $qrImage      = $this->generateQRCode(['content'=>$einvoice->signed_qr_code]);
                //$qrImage      = $einvoice->qrcode_image_path;
            }


            $cart_q = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','0')->where('user_id', $order_details->user_id)->sum('qty');
            $cart_qt = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','1')->where('user_id', $order_details->user_id)->count('qty');
            $cart_qty = $cart_q + $cart_qt;
            $total_amount = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
            // dd($total_amount);
            $count_cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->count();

            if($order_details->transaction_type == 'return'){
                $invoice_title     = 'CREDIT NOTE';
            }

            $startitem   = 0;
            $getItem     = 8;
            $countitem   = $count_cart_product;
            $totalpage   = ceil($count_cart_product/$getItem);
            $sr          = 1;

            for($i=0;$i < $totalpage ; $i++) {

                $cart_product = InvoiceDetails::leftjoin('vendor_sku_detail_barcodes','vendor_sku_detail_barcodes.barcode','invoice_details.barcode')->leftjoin('vendor_sku_flat_table','vendor_sku_flat_table.item_id','vendor_sku_detail_barcodes.item_id')->where('invoice_details.t_order_id', $order_details->id)->where('invoice_details.v_id', $order_details->v_id)->where('invoice_details.store_id', $order_details->store_id)->where('invoice_details.user_id', $order_details->user_id)->groupBy('vendor_sku_detail_barcodes.barcode')->skip($startitem)->take(8)->get();


             // $cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->skip($startitem)->take(8)->get();
             // dd(DB::getQueryLog());
// dd($cart_product);
            $startitem  = $startitem+$getItem;
            $startitem  = $startitem;
             
            $customer_address = '';
            if(isset($order_details->user->address->address1)){
                $customer_address .= $order_details->user->address->address1;
            }
            if(isset($order_details->user->address->address2)){
                $customer_address .= $order_details->user->address->address2;
            }

            $count = 1;
            $gst_tax = 0;
            $gst_listing = [];
            $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
            //dd($gst_list);
            $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
            $cgst = $sgst = $cess = 0 ;
            // dd($final_gst);
 
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
            $bill_logo_id = 5;
            $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
            if($vendorImage)
            {
                $bilLogo = env('ADMIN_URL').$vendorImage->path;
            } 
            $payments  = $order_details->payvia;
            $cash_collected = 0;  
            $cash_return    = 0;
            $net_payable    = $total_amount;

            //dd($payments);
            $mop_list = [];
            foreach ($payments as $payment) {
            $paymentdata = Mop::select('name')->where('code',$payment->method)->first();
            $mopname = '';
            if(isset($paymentdata->name)){
                $mopname = $paymentdata->name;
            }else{
                $mopname = '';
            }
            // dd($payment->amount);
            if ($payment->method == 'cash') {
            $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
            if($order_details->transaction_type == 'return'){
               $mop_list[] = [ 'mode' => $mopname, 'amount' => $payment->amount ];
            }else{
               $mop_list[] = [ 'mode' => $mopname, 'amount' => $payment->cash_collected-$cashReturn ];
            }
            } else {
            $mop_list[] = [ 'mode' => $mopname, 'amount' => $payment->amount ];
            }
            if ($payment->method == 'cash') {
            $cash_collected += (float) $payment->cash_collected;
            $cash_return += (float) $payment->cash_return;
            }
            /*Voucher Start*/
            if($payment->method == 'voucher_credit'){
            $voucher[] = $payment->amount;
            $net_payable = $net_payable-$payment->amount;
            }
            }
            $txn_id = $payment->ref_txn_id;
            $customer_paid = $cash_collected;
            $balance_refund= $cash_return;
            $total_discount = (float)$order_details->discount+(float)$order_details->manual_discount+(float)$order_details->bill_buster_discount;
            // dd($order_details);
            $refinvoicedetails = DB::table('invoices')->select('orders.ref_order_id')->join('orders','invoices.ref_order_id','=','orders.order_id')
                ->where('invoices.invoice_id',$order_details->invoice_id)
                ->where('invoices.v_id', $order_details->v_id)
                ->first(); 

                $ref_invoice = DB::table('orders')->select('invoices.date')->join('invoices','invoices.invoice_id','=','orders.ref_order_id')
                ->where('invoices.invoice_id',$refinvoicedetails->ref_order_id)
                ->where('invoices.v_id', $order_details->v_id)
                ->first(); 
               $terms_conditions =  array('1.Goods once sold will not be returned.','2.Goods sold will be exchanged within 15 days.','3.No Exchange wil be entertained without Original Bill copy.','4.No cash refund.','5.Store credit will not be valid unless stamped & signed by store Manager.','6.Store credit will be valid for 60 days from the isue date.','7.No guarantee on unstiched fabrics.','8.Please follow washing instruction.');
                // $terms =  Terms::where('v_id',$v_id)->get();
                //     $terms_condition = json_decode($terms);
                //     foreach ($terms_condition as $value) {
                //         $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                //     }
               // dd($order_details);
            ########################
            ####### Print Start ####
            ########################
            
            
            //$data = '<body style="padding: 20px;">';
            
            //$data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"><table class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';
            /* $data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"> <table width="10%"><tr><td><img src="'.$bilLogo.'" alt="" height="80px"></td></tr>
           </table><table width="80%" class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';*/

           $data  .= '<table width="90%"><tr><td style="text-align: left; border-bottom: 1px #000 solid;"><b>'.date('d/M/Y', strtotime($order_details->date)).'&nbsp;&nbsp;&nbsp;'.$order_details->time.'</b></td><td style="text-align: center; border-bottom: 1px #000 solid; padding: 10px;"><b style="font-size: 12px;">'.$invoice_title.'<br> Invoice No. '.$order_details->invoice_id.'</b></td><td style="text-align: right; border-bottom: 1px #000 solid;"><b>Duplicate Copy<b></td></tr></table>';
        $data  .= '<table class="mapcha" width="90%" style=" margin-top: 20px; margin-bottom: 0px; margin-left: auto; margin-right: auto;">';
            $data  .= '<tr><td width="100%">
                            <table width="100%"><tr style="vertical-align: top;"><td class="head-p" width="30%"><img src="'.$bilLogo.'" alt="" height="80px"></td>
                            <td width="30%">
                            <table width="100%" align="left" style="color: #000;" >';
            $data  .=  '<tr><td class="spacing "><b style="font-size: 12px;">JL2</b></td></tr>';

            $data  .=  '<tr><td class="spacing ">'.$store->name.'</td></tr>';
            // if($store->address2){
            $data  .=  '<tr><td class="spacing ">'.$store->address1.'</td></tr>';
            if($store->address2){
                $data  .=  '<tr><td class="spacing ">'.$store->address2.'</td></tr>';
            }
            $data  .=  '<tr><td class="spacing ">'.$store->location.'-'.$store->pincode.'</td></tr>';
            $data  .=  '<tr><td class="spacing ">PH. No- '.$store->contact_number.'</td></tr>';
            $data  .=  '<tr><td class="spacing ">GSTIN: '.$store->gst.'</td></tr>';
             $data  .=  '<tr><td class="spacing bold"></td></tr>';
            $data  .=  '</table></td>
                        <td width="40%">
                        <table width="100%" align="left" style="color: #000;" >';
                            
                         $data  .=  '<tr style="padding: 5px;"><td class="bold">Customer Name</td><td>:</td><td>'.@$order_details->user->first_name.' '.@$order_details->user->last_name.'</td></tr>';
                        $data  .=  '<tr><td class="spacing bold">Mobile</td><td>:</td><td>'.$order_details->user->mobile.'</td></tr>';
                        $data  .=  '<tr><td class="spacing bold">Email</td><td>:</td><td>'.$order_details->customer_email.'</td></tr>';
                        $data  .=  '<tr><td class="spacing bold">GSTIN</td><td>:</td><td>'.@$order_details->cust_gstin.'</td></tr>';
                        $data  .=  '</table></td></tr></table>';
                        
                   
            $data  .= '<table width="100%" style="page-break-after: always;"><tr><td><div  style="overflow: hidden; border-bottom: 1px #000 solid; "  width="100%" ><table height="100%" width="100%" class="print_receipt_invoice" bgcolor="#fff" style="width: 100%; color: #000;border: 1px #000 solid;border-top: none;border-bottom: none;">';
            $data  .= '<thead ><tr align="left">
                        <th width="2%" align="left" class="bold">Sr #</th>
                        <th width="5%" align="left" class="bold">Item #</th>
                        <th width="6%" align="left" class="bold">Product Des.</th>
                        <th width="4%" align="left" class="bold">Color</th>
                        <th width="4%" align="left" class="bold">Size</th>
                        <th width="6%" align="left" class="bold">HSN</th>
                        <th width="4%" align="right" class="bold">MRP</th>
                        <th width="4%" align="right" class="bold">Dis. Amt.</th>
                        <th width="4%" align="right" class="bold">Net Price</th>
                        <th width="4%" align="right" class="bold">Qty.</th>
                        <th width="5%" align="right" class="bold">Amount</th></tr></thead><tbody>';
           
            $barcode = '';
            $hsn ='';
            $item_name ='';
            $qty  = '';
            $unit = '';
            $mrp  = '';
            $disc = '';
            $taxp = '';
            $taxb = '';

            $taxable_amount = 0;
            $total_csgt     = 0;
            $total_sgst     = 0;
            $total_cess     = 0;
            $total_igst     = 0;
            $total_qty      = 0;
            $total_discount  = 0;
            $total_amount   = 0;
            $total_inc_tax   = 0;
            $tax_amount    = 0;
            $total_taxable_amt = 0;
            $total_tax_amount  = 0;
            $srp            = '';
            $barcode        = '';
            $disc           = '';
            $hsn            = '';
            $item_name      = '';
            $qty            = '';
            $unit           = '';
            $mrp            = '';
            $TotalMrp           = '';
            $TotalAmt           = '';
            $tax_cgst           = '';
            $tax_sgst           = '';
            $color          = '';
            $size           = '';
            $tax_name       = '';
            $tax_igst       = '';  
            $taxable        = '';   
            $tax_cess       = '';  
            $taxamt         = '';
            $taxcgst        = '';
            $taxsgst        = '';
            $taxigst        = '';
            $taxcess        = '';
            $taxcessper        = '';
            foreach ($cart_product as $key => $value) {
                // dd($value);
                $remark = isset($value->remark)?' -'.$value->remark:'';
                $tdata  = json_decode($value->tdata);
                $itemLevelmanualDiscount=0;
                if($value->item_level_manual_discount!=null){
                    $iLmd = json_decode($value->item_level_manual_discount);
                    $itemLevelmanualDiscount= (float)$iLmd->discount;
                }
                $discount = $value->discount+$value->manual_discount + $value->bill_buster_discount+$itemLevelmanualDiscount;
                $taxper   = $tdata->cgst + $tdata->sgst;
                $taxable_amount += $tdata->taxable;
                $total_csgt  += $tdata->cgstamt;
                $total_sgst  += $tdata->sgstamt;
                $total_cess  += $tdata->cessamt;
                $total_discount += $discount;
                $totalmrp = $value->unit_mrp * $value->qty;
                // dd($value);
                if($tdata->tax_type == 'INC'){
                    $totalamt = $totalmrp;
                }else{
                    $excgst = ($totalmrp - $discount) * $taxper/100;
                    $totalamt = $totalmrp + $excgst;
                }
                $total_amount += $totalamt;
                $cgst = $tdata->cgst;
                $sgst = $tdata->sgst;
                // dd($total_amount);
                $total_qty  += $value->qty;
                $product_variant = explode("-", $value->variant_combi);
                // dd($product_variant);
                // $totaltaxamount = $total_csgt + $total_sgst + $total_igst + $total_cess;  
                // $total_inc_tax = $total_amount - $totaltaxamount; 
                // $tax_amount = $total_amount - $total_inc_tax;
                // dd($total_amount);
                $totaltaxamt = $tdata->cgstamt + $tdata->sgstamt + $tdata->igstamt + $tdata->cessamt;
                $tax_amnt = $totalamt - ($totalamt - $totaltaxamt);
                $total_taxable_amt += $totalamt - $tax_amnt;
                    $total_tax_amount  += $tax_amnt;
                $gst_list[] = [
                    'name'              => $tdata->tax_name,
                    'wihout_tax_price'  => $totalamt - $tax_amnt,
                    'taxAmount'        =>  $tax_amnt,
                    'cgst'              => $tdata->cgstamt,
                    'sgst'              => $tdata->sgstamt,
                    'cess'              => $tdata->cessamt,
                    'igst'              => $tdata->igstamt,
                    'cess'              => $tdata->cessamt,
                    'cessper'           => $tdata->cess,
                ];
                $itemName = substr($value->item_name, 0, 20);
                // $total_inc_tax = $total_amount + $cgst + $sgst; 
                $srp       .= '<pre style="text-align: left;">'.$sr.'</pre>';
                $barcode   .= '<pre style="text-align: left;">'.$tdata->barcode.'</pre>';
                $hsn       .= '<pre style="text-align: left;white-space: nowrap;">'.$tdata->hsn.'</pre>';
                $item_name .= '<pre style="text-align: left;white-space: nowrap;">'.$itemName.'</pre>';
                $qty       .= '<pre style="text-align: left;">'.number_format($value->qty,2).'</pre>';
                $tempVarientColor = isset($value->va_color) ? $value->va_color : 'N/A';
                $color     .= '<pre style="text-align: center;">'.$tempVarientColor.'</pre>';
                $tempVarientSize = isset($value->va_size) ? $value->va_size : 'N/A';
                $size     .= '<pre style="text-align: left;">'.$tempVarientSize.'</pre>';
                $disc      .= '<pre style="text-align: right;">'.number_format($discount,2).'</pre>';
                $tax_cgst      .= '<pre style="text-align: right;">'.number_format($cgst,2).'</pre>';
                $tax_sgst      .= '<pre style="text-align: right;">'.number_format($sgst,2).'</pre>';
                $mrp       .= '<pre style="text-align: right;">'.number_format($value->unit_mrp,2).'</pre>';
                $TotalMrp      .= '<pre style="text-align: right;">'.number_format($totalmrp,2).'</pre>';
                $TotalAmt      .= '<pre style="text-align: right;">'.number_format($totalamt,2).'</pre>';
                $taxb      .= '<pre style="text-align: center;">'.$tdata->taxable.'</pre>';
                $sr++;
            }
            // print_r($total_amount);
            // dd($barcode);
            // dd($tdata->cgstamt);
            $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
            $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
            $cgst = $sgst = $cess = $igst = $cessper = 0 ;
            foreach ($gst_listing as $key => $value) {
                $tax_ab = [];
                $tax_cg = [];
                $tax_sg = [];
                $tax_ig = [];
                $tax_ces = [];
                $tax_cesper = [];
                $tax_amt = [];
                foreach ($gst_list as $val) {

                    if ($val['name'] == $value) {
                        $total_gst             += str_replace(",", '', $val['taxAmount']);
                        $taxable_amount        += str_replace(",", '', $val['wihout_tax_price']);
                        $tax_ab[]       =  str_replace(",", '', $val['wihout_tax_price']);
                        $tax_amt[]       =  str_replace(",", '', $val['taxAmount']);
                        $tax_cg[]       =  str_replace(",", '', $val['cgst']);
                        $tax_sg[]       =  str_replace(",", '', $val['sgst']);
                        $tax_ig[]       =  str_replace(",", '', $val['igst']);
                        $tax_ces[]      =  str_replace(",", '', $val['cess']);
                        $tax_cesper[]      =  str_replace(",", '', $val['cessper']);
                        $cgst              += str_replace(",", '', $val['cgst']);
                        $sgst              += str_replace(",", '', $val['sgst']);
                        $cess              += str_replace(",", '', $val['cess']);
                        $cessper              += str_replace(",", '', $val['cessper']);
                        $igst              += str_replace(",", '', @$val['igst']);
                        $final_gst[$value] = (object)[
                            'name'      => $value,
                        'taxable'   => array_sum($tax_ab),//$this->format_and_string($taxable_amount),
                        'tax_amt'   => array_sum($tax_amt),//$this->format_and_string($taxable_amount),
                        'cgst'      => round(array_sum($tax_cg),2),
                        'sgst'      => round(array_sum($tax_sg),2),
                        'igst'      => round(array_sum($tax_ig),2),
                        'cess'      => round(array_sum($tax_ces),2),
                        'cessper'      => round(array_sum($tax_cesper),2)
                    ];
                }
            }
        }
        $total_csgt = round($cgst,2);
        $total_sgst = round($sgst,2);
        $total_cess = round($cess,2);
        $total_igst = round($igst,2);

        foreach ($final_gst as $key => $value) {
            $tax_details = json_decode(json_encode($value),true);
            $taxable   .= '<p>'.number_format($tax_details['taxable'],2).'</p>';
            $taxamt   .= '<p>'.number_format($tax_details['tax_amt'],2).'</p>';
            $tax_name .= '<p>'.$tax_details['name'].'</p>';
            $taxcgst .= '<p>'.number_format($tax_details['cgst'],2).'</p>';
            $taxsgst .= '<p>'.number_format($tax_details['sgst'],2).'</p>';
            $taxigst .= '<p>'.number_format($tax_details['igst'],2).'</p>';
            $taxcess .= '<p>'.number_format($tax_details['cess'],2).'</p>';
            $taxcessper .= '<p>'.number_format($tax_details['cessper'],2).'</p>';
        }
        // dd($taxable);
            $data   .= '<tr align="left">';

                $data   .= '<td valign="top" class="mapcha" style="border-bottom: 1px #000 solid;">'.$srp.'</td>';
                $data   .= '<td valign="top" class="mapcha" style="border-bottom: 1px #000 solid;">'.$barcode.'</td>';
                $data   .= '<td valign="top" class="mapcha" style="border-bottom: 1px #000 solid;">'.$item_name.'</td>';
                $data   .= '<td valign="top" class="mapcha" style="border-bottom: 1px #000 solid;">'.$color.'</td>';
                $data   .= '<td valign="top" class="mapcha" style="border-bottom: 1px #000 solid;">'.$size.'</td>';
                $data   .= '<td valign="top" class="mapcha" style="border-bottom: 1px #000 solid;">'.$hsn.'</td>';
                $data   .= '<td valign="top" class="mapcha" style="border-bottom: 1px #000 solid;">'.$mrp.'</td>';
                $data   .= '<td valign="top" class="mapcha" style="border-bottom: 1px #000 solid;">'.$disc.'</td>';
                $data   .= '<td valign="top" class="mapcha" style="border-bottom: 1px #000 solid;">'.$TotalMrp.'</td>';
                $data   .= '<td valign="top" class="mapcha" style="border-bottom: 1px #000 solid;">'.$qty.'</td>';
                $data   .= '<td valign="top" class="mapcha" style="border-bottom: 1px #000 solid;">'.$TotalAmt.'</td></tr>';
            $total_csgt = round($total_csgt,2);
            $total_sgst = round($total_sgst,2);
            $total_cess = round($total_cess,2);
            $data   .= '</tbody>
            <tfoot><tr><td colspan="9" style="padding: 4px; background-color: #f4f4f4;"><b>Total</b></td><td style="padding: 4px; text-align: right; background-color: #f4f4f4;"><b>'.$cart_qty.'</b></td><td style="padding: 4px; text-align: right; background-color: #f4f4f4;"><b>'.number_format($total_amount,2).'</b></td></tr></tfoot></table></td></tr></div></td></tr></table>';
            $data .= '<table width="100%"><tbody><tr><td style="border-bottom: 1px #000 solid; padding: 4px;">Rs.'.ucfirst(numberTowords(round($total_amount))).' Only. </td></tr></tbody></table>';
            if($totalpage-1 == $i){
                $total_csgt       = 0;
                $total_sgst       = 0;
                $total_cess       = 0;
                $total_mrp        = 0;
                $total_igst       = 0;
                $total_qty        = 0;
                $total_amount  = 0;
                $tax_amount    = 0;
                $total_discount   = 0;
                // $total_taxable_amt = 0;
                // $total_tax_amount  = 0;
                $invoiceData  = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->get();
                foreach($invoiceData as $invdata){
                    $Ntdata    = json_decode($invdata->tdata);
                    // dd($Ntdata);
                    $itemLevelmanualDiscount=0;
                    if($invdata->item_level_manual_discount!=null){
                     $iLmd = json_decode($invdata->item_level_manual_discount);
                     $itemLevelmanualDiscount = (float)$iLmd->discount;
                    }
                    $discount = $invdata->discount+$invdata->manual_discount + $invdata->bill_buster_discount+$itemLevelmanualDiscount;
                    $taxper   = $Ntdata->cgst + $Ntdata->sgst;
                    $taxable_amount += $Ntdata->taxable;
                    $total_csgt  += $Ntdata->cgstamt;
                    $total_sgst  += $Ntdata->sgstamt;
                    $total_igst  += $Ntdata->igstamt;
                    $total_cess  += $Ntdata->cessamt;
                    $total_discount += $discount;
                    $totalmrp = $invdata->unit_mrp * $invdata->qty;
                // print_r($totalmrp);
                    if($Ntdata->tax_type == 'INC'){
                        $totalamt = $totalmrp;
                    }else{
                        $excgst = ($totalmrp - $discount) * $taxper/100;
                        $totalamt = $totalmrp + $excgst;
                    }
                // if($Ntdata->tax_type == 'INC'){
                //     $total_inc_tax = $total_amount -($Ntdata->cgstamt + $Ntdata->sgstamt); 
                // }
                    $total_amount += $totalamt;
                    // $cgst = $tdata->cgst;
                    // $sgst = $tdata->sgst;
                // $tax_cgst = $Ntdata->cgstamt;
                // $tax_sgst = $Ntdata->sgstamt;
                // $tax_igst = $Ntdata->igstamt;
                    $total_qty  += $invdata->qty;
                    $cess_percentage = $Ntdata->cess;
                    $tax_cess = $Ntdata->cessamt;
                    $taxname = $Ntdata->tax_name;
                // print_r($invdata->unit_mrp);
                    $totaltaxamount = $total_csgt + $total_sgst + $total_igst + $total_cess;  
                    $total_inc_tax = $total_amount - $totaltaxamount; 
                    $tax_amount = $total_amount - $total_inc_tax;
                    // $total_taxable_amt += $total_inc_tax;
                    // $total_tax_amount  += $tax_amount;
                }
                $total_net_amount = round($total_amount);
            $data .= '<table width="100%"><tr style="vertical-align: top;">
                    <td width="25%"></td>
                    <td width="35%" style="padding: 8px 0;">
                    <table width="100%">
                    <tr>
                    <td style="width: 40%;">Payment Modes</td>';
                    // foreach($mop_list as $mop){
                        $paymntmethod = $payment->method;
                        if($order_details->transaction_type == 'sales'){
                            if (str_contains($paymntmethod, 'cash'))  {
                                $data .= '<td style="text-transform: capitalize; width:30%; text-align: right;">CASH PAID :</td><td style="width:30%; text-align: right;">'.$customer_paid.'</td>';
                                    // $data .= '<td style="text-transform: capitalize;">'.$mop['mode'].' paid </td>';
                            }else{
                                $data .= '<td style="text-transform: capitalize; text-align: right; width:30%;">CASH PAID :</td>';
                            }
                        }
                    $data .= '</tr>
                    <tr>
                        <td style="width: 30%;"></td>';
                            // dd($balance_refund);
                        $paymntmethod = $payment->method; 
                        if($order_details->transaction_type == 'sales'){
                            if (str_contains($paymntmethod, 'cash'))  {
                                if($balance_refund > 0){
                                    $data .= '<td style=" text-align: right; width: 30%;">BALANCE REFUND :</td><td style="width: 30%;">'.$balance_refund.'</td>';
                                }else{
                                    $data .= '<td style=" text-align: right; width: 30%;">BALANCE REFUND :0</td>';
                                }
                            }else{
                                $data .= '<td style=" text-align: right; width: 30%;">BALANCE REFUND :</td>';
                            }
                        }
                    $data .= '</tr>
                </table>
                    </td>
                    <td width="40%" style="padding: 8px 0;">
                        <table align="rights" width="100%"><tr><td align="right" width="50%" class="terms-spacing bold">Total MRP Value</td><td>:</td><td align="right"  width="30%" class="terms-spacing pr-3 bold">'.number_format($total_amount,2).'</td></tr>';
                        
                        if($order_details->transaction_type == 'sales'){
                            $data .= '<tr><td align="right" class="terms-spacing bold"  width="50%">Total Discount</td><td>:</td><td align="right" class="terms-spacing bold pr-3"  width="30%">'.number_format($total_discount,2).'</td></tr>
                                <tr><td align="right" class="terms-spacing bold"  width="50%" style="border-bottom: 1px #000 solid;">Net Payable</td><td style="border-bottom: 1px #000 solid;">:</td><td align="right" class="terms-spacing bold pr-3"  width="30%" style="border-bottom: 1px #000 solid;">'.number_format($total_net_amount,2).'</td></tr>';
                        }else{
                            $data .= '<tr><td align="right" class="terms-spacing bold"  width="50%" style="border-bottom: 1px #000 solid;">Total Discount</td><td style="border-bottom: 1px #000 solid;">:</td><td align="right" class="terms-spacing bold pr-3"  width="30%" style="border-bottom: 1px #000 solid;">'.number_format($total_discount,2).'</td></tr>';
                        }
                        $data .= '<tr><td align="right" class="terms-spacing bold"  width="50%" style="padding-top: 5px;">Total Before Tax</td><td style="padding-top: 5px;">:</td><td align="right" class="terms-spacing bold pr-3"  width="30%" style="padding-top: 5px;">'.number_format($total_inc_tax,2).'</td></tr>
                        <tr><td align="right" class="terms-spacing bold"  width="50%" style="padding-bottom: 10px;" >Tax Amount</td><td>:</td><td align="right" class="terms-spacing bold pr-3"  width="30%">'.number_format($tax_amount,2).'</td></tr>                        
                        </table>
                    </td>
            </tr></table>';
            $data .= '<table width="100%" style="padding-bottom: 8px;">';
            foreach($mop_list as $mop){
                if (str_contains($mop['mode'], 'cash'))  {
                    // if($balance_refund > 0){
                    //     $data .= '<tr>
                    //     <td width="15%" style="text-transform: capitalize;" class="bold">CASH</td><td width="15%" style="text-align: right;">'.$customer_paid.'</td>';
                    // }else{
                        $data .= '<tr>
                        <td width="15%" style="text-transform: capitalize;" class="bold">CASH</td><td width="15%" style="text-align: right;">'.number_format($mop['amount'],2).'</td>';
                    // }
                        $data .= '<td width="35%" style="padding: 0 30px;"></td><td width="20%"><b style="padding-right: 20px;">Txn. Date:</b>&nbsp;'.$order_details->date.'</td><td style="width="20%; text-align: right;"><b style="padding-right: 20px;>Time:</b>'.$order_details->time.'</td></tr>';
                }else{
                    $data .= '<tr>
                    <td width="15%" style="text-transform: capitalize;" class="bold">'.$mop['mode'].'</td><td width="15%" style="text-align: right;">'.number_format($mop['amount'],2).'</td>';
                    $data .= '<td width="30%" style="padding: 0 30px;"><b>Txn. ID:</b>&nbsp;'.$txn_id.'</td><td width="20%"><b style="padding-right: 20px;">Txn. Date:</b>&nbsp;'.$order_details->date.'</td><td style="width: 20%;"><b style="padding-right: 20px;">Time:</b>'.$order_details->time.'</td></tr>';
                }
                    
                }
            
            
            // $data .= '<tr>
            //         <td width="25%" class="bold">VISA</td>
            //         <td width="25%">--</td>
            //         <td width="25%"><b>Txn. ID:</b> --  </td>
            //         <td width="25%"><b>Txn. Date:</b> 04/06/2021 <b>Time:</b> 14:28:19</td>
            // </tr>
            // <tr>
            //         <td width="25%" class="bold">UPI</td>
            //         <td width="25%">--</td>
            //         <td width="25%"><b>Txn. ID:</b> --  </td>
            //         <td width="25%"><b>Txn. Date:</b> 04/06/2021 <b>Time:</b> 14:28:19</td>
            // </tr>';
           $data .= '</table>';
       
            $data .= '
            <table width="100%" style="border: 1px #000 solid; border-left: none; border-right: none; border-bottom: none;">
            <tr>
                <td style="padding: 4px;">GST SUMMARY</td>
            </tr>
                </table>
            ';
            $data .= '
            <table width="100%" style="border: 1px #000 solid; border-left: none; border-right: none; border-bottom: none;">
            <thead>
            <tr>
                <th style="text-align: left;">DESCRIPTION</th>
                <th style="text-align: right;">TAXABLE AMT</th>
                <th style="text-align: right;">TAX AMT</th>
                <th style="text-align: right;">CGST AMT</th>
                <th style="text-align: right;">SGST AMT</th>
                <th style="text-align: right;">IGST AMT</th>
                <th style="text-align: right;">CESS AMT</th>
                <th style="text-align: right;">CESS %</th>
            </tr>
            </thead>
            <tbody>';
                $data .= '<tr>
                    <td style="padding: 4px; text-align: left;">'.$tax_name.'</td>
                    <td style="padding: 4px; text-align: right;">'.$taxable.'</td>
                    <td style="padding: 4px; text-align: right;">'.$taxamt.'</td>
                    <td style="padding: 4px; text-align: right;">'.$taxcgst.'</td>
                    <td style="padding: 4px; text-align: right;">'.$taxsgst.'</td>
                    <td style="padding: 4px; text-align: right;">'.$taxigst.'</td>
                    <td style="padding: 4px; text-align: right;">'.$taxcess.'</td>
                    <td style="padding: 4px; text-align: right;">'.$taxcessper.'</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td style="padding: 4px; border-bottom: 1px #000 solid; font-weight: 600; border-top: 1px #000 solid; text-align: left;">Total</td>
                    <td style="padding: 4px; border-bottom: 1px #000 solid; font-weight: 600; border-top: 1px #000 solid; text-align: right;">'.number_format($total_taxable_amt,2).'</td>
                    <td style="padding: 4px; border-bottom: 1px #000 solid; font-weight: 600; border-top: 1px #000 solid; text-align: right;">'.number_format($total_tax_amount,2).'</td>
                    <td style="padding: 4px; border-bottom: 1px #000 solid; font-weight: 600; border-top: 1px #000 solid; text-align: right;">'.number_format($total_csgt,2).'</td>
                    <td style="padding: 4px; border-bottom: 1px #000 solid; font-weight: 600; border-top: 1px #000 solid; text-align: right;">'.number_format($total_sgst,2).'</td>
                    <td style="padding: 4px; border-bottom: 1px #000 solid; font-weight: 600; border-top: 1px #000 solid; text-align: right;">'.number_format($total_igst,2).'</td>
                    <td style="padding: 4px; border-bottom: 1px #000 solid; font-weight: 600; border-top: 1px #000 solid; text-align: right;">'.number_format($tax_cess,2).'</td>
                    <td style="padding: 4px; border-bottom: 1px #000 solid; font-weight: 600; border-top: 1px #000 solid; text-align: right;">'.number_format($cessper,2).'</td>
                </tr>
            </tfoot>
                </table>
            ';
            $data   .= '<table width = "100%">
                <tr width="100%">
                    <td class="head-p bold" style="padding-top: 10px;">Terms and Conditions:</td>
                </tr>';
                $data .= '<tr><td style="30%"><table>';
                foreach($terms_conditions as $term){
                   $data .= '<tr width="100%"><td class="terms-spacing">'.$term.'</td></tr>';
                }
                $data .= '</table>';
            $data .='<td style="width: 45%; font-weight: 600; text-align: center;">
            There will be No Return No Exchange <br> during EOSS & Promotion Period <br><br>
            (Please Join us on Facebook @ www.facebook.com/BibaIndia)
            </td><td style="width: 25%; text-align: right;"><table width="100%"><tbody><tr><td style="padding-bottom: 16px;">BIBA APPARELS PVT LTD</td></tr><tr><td>Authorised Signatory</td></tr></tbody></table></td></tr>';
            $data    .= '</table></td></tr></table>';
            $data    .= '<hr>';
            $data    .= '<table width="60%" style="
    margin: 0 auto;
    padding: 10px;"><tr class="print_invoice_last">
            <td class="bold" style="text-align: center; font-size: 9px">Corporate Address: 13th Floor, Capital Cyber Scape,Sector-59, Golf Course Extension Road,Gurugram,Haryana-122102,INDIA <br> Registered Address: 13th Floor, Capital Cyber Scape,Sector-59, Golf Course Extension Road,Gurugram,Haryana-122102,INDIA <br> CIN : U74110HR2002PTC083029 | Phone :0124-5047000 | Email : info@bibaindia.com | Website:- www.biba.in <br><br>
            This invoice covered under Policy no. OG-21-1113-1018-00000034 of Bajaj Allianz - General Insurance Company Ltd
            </td></tr></table></td></tr></table>';
            }else{
                $data   .= '<table width="100%" style="padding: 5px;">
                                <tr>
                                    <td width="60%"></td>
                                    <td width="40%">
                                        <table width="100%">
                                            <tr align="right">
                                                <td width="100%"><b>Continue..</b></td>
                                                <td width="30%"></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            </td></tr></table></td></tr>';

                
            }
            if($totalpage > 1){
                $data .= '<br><hr>';
            }
             
        }
        $return = array('status'=>'success','style'=>$style,'html'=>$data) ;
        return $return;
    }//End of print_html_page

    public function biba_b2b_A4_invoice($request){

            $v_id       = $request->v_id;
            $store_id   = $request->store_id; 
            $c_id       = $request->c_id;
            $order_id   = $request->order_id;
            $product_data= [];
            $gst_list    = [];
            $final_gst   = [];
            $detatch_gst = [];
            $rounded     = 0;
            $data    = '';
            $terms_conditions =  array('');
            $invoice_title = 'Retail Invoice';
            $style = "<style>
            body{font-family: 'ibm';}.header{font-size: 16px;line-height: 24px;}
            @font-face {
              font-family: 'ibm';
                      src: url('https://test.api.gozwing.com/font/ibm-plex-sans-v8-latin-regular.woff2')('woff2'),
                           url('https://test.api.gozwing.com/font/ibm-plex-sans-v8-latin-regular.woff') format('woff');
                           font-weight: 600;
                           font-style: normal;
            }
            hr{background-color: #000;} .bold{font-weight: bold;} body{font-family: glacial_indifferenceregular; font-size: 14px;} .head-p.invoice{font-size: 24px; padding-top: 0px !important;} 
            .mapcha table thead tr th{border-left: none; padding: 10px 10px;} .head-p{ padding-top: 8px; padding-bottom: 8px;} .mapcha table tbody tr td pre{min-height: 20px; font-size: 14px !important; font-family: glacial_indifferenceregular;} .pr-3{padding-right: 4px !important;} .terms-spacing{padding: 4px 0px;} .spacing{padding: 2px 0px;} *{padding:0;margin:0;box-sizing:border-box;-webkit-border-vertical-spacing:0;-webkit-border-horizontal-spacing:0;font-size:11px}.print_receipt_invoice thead tr th{border-right:1px #000 solid; white-space: nowrap; color: #000; border-bottom:1px #000 solid;border-top:1px #000 solid;padding: 4px;}.print_receipt_invoice thead tr:last-child{border-right:none}.print_receipt_invoice tbody tr td{border-right:1px #000 solid; padding: 0px;}.print_receipt_invoice tbody tr td pre{border-bottom: 1px #000 solid; min-height:20px;text-align:left;white-space:normal;word-wrap:break-word; font-size: 11px;overflow:hidden;line-height: 19px; padding: 0px 5px;}.print_receipt_invoice tbody tr td:last-child{border-right:none}.print_receipt_top-head tr td{padding:2px}.print_invoice_terms td table{text-align: left;}.print_invoice_last td table td{text-align: left;}.print_store_sign td:nth-child(2){text-align: right;}.print_invoice_last td table:last-child{margin-top: 40px;}.print_invoice_table_start table tbody tr td{font-size:13px;}.print_invoice_terms td{ border-left: none;}.mapcha table thead tr th:last-child{border-right: none;}</style>";


            $printArray  = array();
            $store         = Store::find($store_id);
            $order_details = Invoice::where('invoice_id', $order_id)->first();

            $einvoice = EinvoiceDetails::where('invoice_id',$order_details->invoice_id)->where('status','Success')->first();
            $qrImage = '';
            // if($einvoice && !empty($einvoice->signed_qr_code)){
              
               $qrImage      = $this->generateQRCode(['content'=>'D76263ASMDBV287']);
                //$qrImage      = $einvoice->qrcode_image_path;
            // }


            $cart_q = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','0')->where('user_id', $order_details->user_id)->sum('qty');
            $cart_qt = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','1')->where('user_id', $order_details->user_id)->count('qty');
            $cart_qty = $cart_q + $cart_qt;
            $total_amount = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
            // dd($total_amount);
            $count_cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->count();

            if($order_details->transaction_type == 'return'){
                $invoice_title     = 'Credit Note';
            }

            $startitem   = 0;
            $getItem     = 16;
            $countitem   = $count_cart_product;
            $totalpage   = ceil($count_cart_product/$getItem);
            $sr          = 1;

            
            $customer_address = '';
            if(isset($order_details->user->address->address1)){
                $customer_address .= $order_details->user->address->address1;
            }
            if(isset($order_details->user->address->address2)){
                $customer_address .= $order_details->user->address->address2;
            }

            $count = 1;
            $gst_tax = 0;
            $gst_listing = [];
            $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
            //dd($gst_list);
            $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
            $cgst = $sgst = $cess = 0 ;
            // dd($final_gst);
 
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
            $bill_logo_id = 5;
            $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
            if($vendorImage)
            {
                $bilLogo = env('ADMIN_URL').$vendorImage->path;
            } 
            $payments  = $order_details->payvia;
            $cash_collected = 0;  
            $cash_return    = 0;
            $net_payable    = $total_amount;

            //dd($payments);
            $mop_list = [];
            foreach ($payments as $payment) {
            $paymentdata = Mop::select('name')->where('code',$payment->method)->first();
            $mopname = '';
            if(isset($paymentdata->name)){
                $mopname = $paymentdata->name;
            }else{
                $mopname = '';
            }
            // dd($payment->amount);
            if ($payment->method == 'cash') {
            $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
            if($order_details->transaction_type == 'return'){
               $mop_list[] = [ 'mode' => $mopname, 'amount' => $payment->amount ];
            }else{
               $mop_list[] = [ 'mode' => $mopname, 'amount' => $payment->cash_collected-$cashReturn ];
            }
            } else {
            $mop_list[] = [ 'mode' => $mopname, 'amount' => $payment->amount ];
            }
            if ($payment->method == 'cash') {
            $cash_collected += (float) $payment->cash_collected;
            $cash_return += (float) $payment->cash_return;
            }
            /*Voucher Start*/
            if($payment->method == 'voucher_credit'){
            $voucher[] = $payment->amount;
            $net_payable = $net_payable-$payment->amount;
            }
            }
            $customer_paid = $cash_collected;
            $balance_refund= $cash_return;
            $total_discount = (float)$order_details->discount+(float)$order_details->manual_discount+(float)$order_details->bill_buster_discount;

               // $terms_conditions =  array('1.Goods once sold will not be returned.','2.Goods sold will be exchanged within 15 days.','3.No Exchange wil be entertained without Original Bill copy.','4.No cash refund.','5.Store credit will not be valid unless stamped & signed by store Manager.','6.Store credit will be valid for 60 days from the isue date.','7.No guarantee on unstiched fabrics.','8.Please follow washing instruction.');
            $terms =  Terms::where('v_id',$v_id)->get();
                    $terms_condition = json_decode($terms);
                    foreach ($terms_condition as $value) {
                        $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                    }
               // dd($order_details);
            ########################
            ####### Print Start ####
            ########################
            
            
            //$data = '<body style="padding: 20px;">';
            
            //$data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"><table class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';
            /* $data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"> <table width="10%"><tr><td><img src="'.$bilLogo.'" alt="" height="80px"></td></tr>
           </table><table width="80%" class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';*/
        $data  .= '<table width="90%" style=" margin-top: 20px; margin-bottom: auto; margin-left: auto; margin-right: auto;">';
            $data  .= '<tr><td width="100%">
                            <table width="100%"><tr><td style="text-align:center;padding-bottom:8px; font-size:12px !important;"><b>TAX INVOICE</b></td></tr></table>
                            <hr>
                            <table width="100%"><tr style="vertical-align: top;"><td class="head-p" width="35%"><img src="'.$bilLogo.'" alt="" height="80px" style="margin-bottom: 8px;"><br><span>Date: '.$order_details->date.'</span></td>
                            <td width="65%" class="head-p">
                            <table width="100%" align="left" style="color: #000;" >';
            $data  .=  '<tr><td class="spacing "><b>JL2</b></td></tr>';

            $data  .=  '<tr><td class="spacing ">'.$store->name.'</td></tr>';
            // if($store->address2){
            $data  .=  '<tr><td class="spacing ">'.$store->address1.'</td></tr>';
            if($store->address2){
                $data  .=  '<tr><td class="spacing ">'.$store->address2.'</td></tr>';
            }
            $data  .=  '<tr><td class="spacing ">'.$store->location.'-'.$store->pincode.'</td></tr>';
            $data  .=  '<tr><td class="spacing ">PH. No- '.$store->contact_number.'</td></tr>';
            $data  .=  '<tr><td class="spacing ">GSTIN: '.$store->gst.'</td></tr>';
             $data  .=  '<tr><td class="spacing bold"></td></tr>';
            $data  .=  '</table></td>
                        </tr></table>';
            $data .= '<table width="100%" style="background-color: #e0e0e0; text-align: center; border: 1px #000 solid; padding: 4px; font-weight: bold;">
                    <tr>
                        <td>No. : B46G2021-0000010</td>
                    </tr>
                </table>';  
            $data .='<table width="100%" style="margin: 8px 0;">
                    <tr>
                        <td style="width: 75%; vertical-align: top;">
                            <table>
                                <tr>
                                    <td class="spacing"><b>Delivery Location:</b></td>
                                </tr>
                                <tr>
                                    <td class="spacing"><b>OMM</b></td>
                                </tr>
                                <tr>
                                    <td class="spacing">BIBA APPARELS PVT. LTD - OBERAI MALL <br>
                                        Oberoi Garden City Opp. W.E Highway <br>
                                        GOREGAON -EAST , MUMBAI -400063</td>
                                </tr>
                                <tr>
                                    <td class="spacing">Ph-022-42950768</td>
                                </tr>
                                <tr>
                                    <td class="spacing" style="padding-bottom: 20px;">GSTIN No. : 27AABCB9274B1ZS</td>
                                </tr>
                                 <tr>
                                    <td class="spacing"><b>Transporter Name:</b></td>
                                </tr>
                                <tr>
                                    <td class="spacing"><b>G.R.NO:</b></td>
                                </tr>
                                <tr>
                                    <td class="spacing"><b>Way Bill:</b></td>
                                </tr>
                                <tr>
                                    <td class="spacing"><b>CARRIER</b></td>
                                </tr>
                            </table>
                        </td>
                        <td style="width: 25%;"><img width="100%" src='.$qrImage.'></td>
                    </tr>
                </table>';
                $data .= '<hr>';     
                $data .='<table width="100%" style="padding: 8px 0px;">
                    <tr>
                        <td style="font-weight: bold;">e-Invoice Details:</td>
                    </tr>
                    <tr>
                        <td style="width: 60%; vertical-align: top;">
                            <table>
                                <tr>
                                    <td style="font-weight: bold;">IRN:</td>
                                    <td>e83af9d7b653b4c4b3eac0521041e14560857dfac1f2078c80bb8ea1ad3d
                                    a6cf</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </table>
                        </td>
                        <td  style="width: 10%"></td>
                        <td style="width: 30%">
                            <table>
                                <tr>
                                    <td style="font-weight: bold;">Ack. No.:</td>
                                    <td>112010028606896</td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold;">Ack. Date Time:</td>
                                    <td>10-10-2020 06:41:00 PM</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>';   
                 for($i=0;$i < $totalpage ; $i++) {
             $cart_product = InvoiceDetails::leftjoin('vendor_sku_detail_barcodes','vendor_sku_detail_barcodes.barcode','invoice_details.barcode')->leftjoin('vendor_sku_flat_table','vendor_sku_flat_table.item_id','vendor_sku_detail_barcodes.item_id')->where('invoice_details.t_order_id', $order_details->id)->where('invoice_details.v_id', $order_details->v_id)->where('invoice_details.store_id', $order_details->store_id)->where('invoice_details.user_id', $order_details->user_id)->groupBy('vendor_sku_detail_barcodes.barcode')->skip($startitem)->take(16)->get();

            $startitem  = $startitem+$getItem;
            $startitem  = $startitem;
            // if($totalpage-1 == $i){
            //     $data .= '<table width="100%"><tr><td style="text-align:center;padding-bottom:8px; font-size:12px !important;"><b>TAX INVOICE</b></td></tr></table>';
            // }
            $data  .= '<table width="100%"><tr><td><div  style="overflow: hidden; border-bottom: 1px #000 solid; "  width="100%" ><table height="100%" width="100%" class="print_receipt_invoice" bgcolor="#fff" style="width: 100%; color: #000;border: 1px #000 solid;border-top: none;border-bottom: none;">';
            $data  .= '<thead style="background-color: #e0e0e0;"><tr align="left">
                        <th width="2%" align="center" class="bold">SNo.</th>
                        <th width="5%" align="left" class="bold">Item #</th>
                        <th width="6%" align="left" class="bold">HSN</th>
                        <th width="6%" align="left" class="bold" style="white-space: nowrap;">Product Des.</th>
                        <th width="4%" align="left" class="bold">Color</th>
                        <th width="4%" align="center" class="bold">Size</th>
                        <th width="4%" align="right" class="bold">MRP</th>
                        <th width="4%" align="right" class="bold">Qty.</th>
                        <th width="4%" align="right" class="bold">Rate</th>
                        <th width="5%" align="right" class="bold" style="border-right:none;">Total Amount</th></tr></thead><tbody>';
           
             
            $barcode = '';
            $hsn ='';
            $item_name ='';
            $qty  = '';
            $unit = '';
            $mrp  = '';
            $disc = '';
            $taxp = '';
            $taxb = '';

            $taxable_amount = 0;
            $total_cgst_per = 0;
            $total_sgst_per = 0;
            $total_igst_per = 0;
            $total_cess_per = 0;
            $total_csgt     = 0;
            $total_sgst     = 0;
            $total_cess     = 0;
            $total_igst     = 0;
            $total_qty      = 0;
            $total_discount  = 0;
            $total_amount   = 0;
            $total_inc_tax   = 0;
            $tax_amount    = 0;
            $total_taxable_amt = 0;
            $total_tax_amount  = 0;
            $srp            = '';
            $barcode        = '';
            $disc           = '';
            $hsn            = '';
            $item_name      = '';
            $qty            = '';
            $unit           = '';
            $mrp            = '';
            $TotalMrp           = '';
            $TotalAmt           = '';
            $tax_cgst           = '';
            $tax_sgst           = '';
            $color          = '';
            $size           = '';
            $tax_name       = '';
            $tax_igst       = '';  
            $taxable        = '';   
            $tax_cess       = '';  
            $taxamt         = '';
            $taxcgst        = '';
            $taxsgst        = '';
            $taxigst        = '';
            $taxcess        = '';
            $taxcessper        = '';
            $taxcgstper        = '';
            $taxsgstper        = '';
            $taxigstper        = '';
            foreach ($cart_product as $key => $value) {
                $remark = isset($value->remark)?' -'.$value->remark:'';
                $tdata  = json_decode($value->tdata);
                $itemLevelmanualDiscount=0;
                if($value->item_level_manual_discount!=null){
                    $iLmd = json_decode($value->item_level_manual_discount);
                    $itemLevelmanualDiscount= (float)$iLmd->discount;
                }
                $discount = $value->discount+$value->manual_discount + $value->bill_buster_discount+$itemLevelmanualDiscount;
                $taxper   = $tdata->cgst + $tdata->sgst;
                $taxable_amount += $tdata->taxable;
                $total_csgt  += $tdata->cgstamt;
                $total_sgst  += $tdata->sgstamt;
                $total_cess  += $tdata->cessamt;
                $total_cgst_per += $tdata->cgst;
                $total_sgst_per += $tdata->sgst;
                $total_igst_per += $tdata->igst;
                $total_cess_per += $tdata->cess;
                $total_discount += $discount;
                $totalmrp = $value->unit_mrp * $value->qty;
                if($tdata->tax_type == 'INC'){
                    $totalamt = $totalmrp;
                }else{
                    $excgst = ($totalmrp - $discount) * $taxper/100;
                    $totalamt = $totalmrp + $excgst;
                }
                $total_amount += $totalamt;
                $cgst = $tdata->cgst;
                $sgst = $tdata->sgst;
                // dd($total_amount);
                $total_qty  += $value->qty;
                 $totaltaxamount = $total_csgt + $total_sgst + $total_igst + $total_cess;  
                $total_inc_tax = $total_amount - $totaltaxamount; 
                $tax_amount = $total_amount - $total_inc_tax;
                $totaltaxamt = $tdata->cgstamt + $tdata->sgstamt + $tdata->igstamt + $tdata->cessamt;
                $gst_list[] = [
                    'name'              => $tdata->hsn,
                    'wihout_tax_price'  => $total_amount - $totaltaxamt,
                    'taxAmount'        =>  $tdata->tax,
                    'cgst'              => $tdata->cgstamt,
                    'sgst'              => $tdata->sgstamt,
                    'cess'              => $tdata->cessamt,
                    'igst'              => $tdata->igstamt,
                    'cess'              => $tdata->cessamt,
                    'cessper'           => $tdata->cess,
                    'cgstper'           => $tdata->cgst,
                    'sgstper'           => $tdata->sgst,
                    'igstper'           => $tdata->igst,
                ];
                $itemName = substr($value->item_name, 0, 20);
                // $total_inc_tax = $total_amount + $cgst + $sgst; 
                $srp       .= '<pre style="text-align: center;">'.$sr.'</pre>';
                $barcode   .= '<pre style="text-align: left;">'.$tdata->barcode.'</pre>';
                $hsn       .= '<pre style="text-align: left;white-space: nowrap;">'.$tdata->hsn.'</pre>';
                $item_name .= '<pre style="text-align: left;white-space: nowrap;">'.$itemName.'</pre>';
                $qty       .= '<pre style="text-align: right;">'.$value->qty.'</pre>';
                $tempVarientColor = isset($value->va_color) ? $value->va_color : 'N/A';
                $color     .= '<pre style="text-align: left;">'.$tempVarientColor.'</pre>';
                $tempVarientSize = isset($value->va_size) ? $value->va_size : 'N/A';
                $size     .= '<pre style="text-align: center;">'.$tempVarientSize.'</pre>';
                $disc      .= '<pre style="text-align: center;">'.number_format($discount,2).'</pre>';
                $tax_cgst      .= '<pre style="text-align: center;">'.number_format($cgst,2).'</pre>';
                $tax_sgst      .= '<pre style="text-align: center;">'.number_format($sgst,2).'</pre>';
                $mrp       .= '<pre style="text-align: right;">'.number_format($value->unit_mrp,2).'</pre>';
                $TotalMrp      .= '<pre style="text-align: right;">'.number_format($totalmrp,2).'</pre>';
                $TotalAmt      .= '<pre style="text-align: right;">'.number_format($totalamt,2).'</pre>';
                $taxb      .= '<pre style="text-align: center;">'.$tdata->taxable.'</pre>';
                $sr++;
            }
            $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
            $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
            $cgst = $sgst = $cess = $igst = $cessper = $cgstper = $sgstper = $igstper = 0;
            foreach ($gst_listing as $key => $value) {
                $tax_ab = [];
                $tax_cg = [];
                $tax_sg = [];
                $tax_ig = [];
                $tax_ces = [];
                $tax_cesper = [];
                $tax_cgstper = [];
                $tax_sgstper = [];
                $tax_igstper = [];
                $tax_amt = [];
                foreach ($gst_list as $val) {

                    if ($val['name'] == $value) {
                        $total_gst             += str_replace(",", '', $val['taxAmount']);
                        $taxable_amount        += str_replace(",", '', $val['wihout_tax_price']);
                        $tax_ab[]       =  str_replace(",", '', $val['wihout_tax_price']);
                        $tax_amt[]       =  str_replace(",", '', $val['taxAmount']);
                        $tax_cg[]       =  str_replace(",", '', $val['cgst']);
                        $tax_sg[]       =  str_replace(",", '', $val['sgst']);
                        $tax_ig[]       =  str_replace(",", '', $val['igst']);
                        $tax_ces[]      =  str_replace(",", '', $val['cess']);
                        $tax_cesper[]      =  str_replace(",", '', $val['cessper']);
                        $tax_cgstper[]      =  str_replace(",", '', $val['cgstper']);
                        $tax_sgstper[]      =  str_replace(",", '', $val['sgstper']);
                        $tax_igstper[]      =  str_replace(",", '', $val['igstper']);
                        $cgst              += str_replace(",", '', $val['cgst']);
                        $sgst              += str_replace(",", '', $val['sgst']);
                        $cess              += str_replace(",", '', $val['cess']);
                        $cessper              += str_replace(",", '', $val['cessper']);
                        $cgstper              += str_replace(",", '', $val['cgstper']);
                        $sgstper              += str_replace(",", '', $val['sgstper']);
                        $igstper              += str_replace(",", '', $val['igstper']);
                        $igst              += str_replace(",", '', @$val['igst']);
                        $final_gst[$value] = (object)[
                            'name'      => $value,
                        'taxable'   => array_sum($tax_ab),//$this->format_and_string($taxable_amount),
                        'tax_amt'   => array_sum($tax_amt),//$this->format_and_string($taxable_amount),
                        'cgst'      => round(array_sum($tax_cg),2),
                        'sgst'      => round(array_sum($tax_sg),2),
                        'igst'      => round(array_sum($tax_ig),2),
                        'cess'      => round(array_sum($tax_ces),2),
                        'cessper'      => round(array_sum($tax_cesper),2),
                        'cgstper'      => round(array_sum($tax_cgstper),2),
                        'sgstper'      => round(array_sum($tax_sgstper),2),
                        'igstper'      => round(array_sum($tax_igstper),2),
                    ];
                }
            }
        }
        $total_csgt = round($cgst,2);
        $total_sgst = round($sgst,2);
        $total_cess = round($cess,2);
        $total_igst = round($igst,2);

        foreach ($final_gst as $key => $value) {
            $tax_details = json_decode(json_encode($value),true);
            $taxable   .= '<p>'.$tax_details['taxable'].'</p>';
            $taxamt   .= '<p>'.$tax_details['tax_amt'].'</p>';
            $tax_name .= '<p>'.$tax_details['name'].'</p>';
            $taxcgst .= '<p>'.$tax_details['cgst'].'</p>';
            $taxsgst .= '<p>'.$tax_details['sgst'].'</p>';
            $taxigst .= '<p>'.$tax_details['igst'].'</p>';
            $taxcess .= '<p>'.$tax_details['cess'].'</p>';
            $taxcessper .= '<p>'.$tax_details['cessper'].'</p>';
            $taxcgstper .= '<p>'.$tax_details['cgstper'].'</p>';
            $taxsgstper .= '<p>'.$tax_details['sgstper'].'</p>';
            $taxigstper .= '<p>'.$tax_details['igstper'].'</p>';
        }
        // dd($taxable);
            $data   .= '<tr align="left">';

                $data   .= '<td valign="top" class="mapcha">'.$srp.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$barcode.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$hsn.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$item_name.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$color.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$size.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$mrp.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$qty.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$TotalMrp.'</td>';
                $data   .= '<td valign="top" class="mapcha">'.$TotalAmt.'</td></tr>';
            $total_csgt = round($total_csgt,2);
            $total_sgst = round($total_sgst,2);
            $total_cess = round($total_cess,2);
            $data   .= '</tbody>
            <tfoot style="background-color: #e0e0e0;"><tr><td colspan="7" style="padding: 4px;"><b>Total</b></td><td colspan="1" style="padding: 4px; text-align: center;"><b>'.$total_qty.'</b></td><td colspan="2" style="padding: 4px; text-align: right;"><b>'.$total_amount.'</b></td></tr></tfoot></table></td></tr></div></td></tr></table>';
            if($totalpage-1 == $i){
                $total_csgt       = 0;
                $total_sgst       = 0;
                $total_cess       = 0;
                $total_cgst_per = 0;
                $total_sgst_per = 0;
                $total_igst_per = 0;
                $total_cess_per = 0;
                $taxable_amount = 0;
                $total_mrp        = 0;
                $total_igst       = 0;
                $total_qty        = 0;
                $total_amount  = 0;
                $tax_amount    = 0;
                $total_discount   = 0;
                $total_taxable_amt = 0;
                $total_tax_amount  = 0;
                $invoiceData  = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->get();
                foreach($invoiceData as $invdata){
                    $Ntdata    = json_decode($invdata->tdata);
                    // dd($Ntdata);
                    $itemLevelmanualDiscount=0;
                    if($invdata->item_level_manual_discount!=null){
                     $iLmd = json_decode($invdata->item_level_manual_discount);
                     $itemLevelmanualDiscount = (float)$iLmd->discount;
                    }
                    $discount = $invdata->discount+$invdata->manual_discount + $invdata->bill_buster_discount+$itemLevelmanualDiscount;
                    $taxper   = $Ntdata->cgst + $Ntdata->sgst;
                    $taxable_amount += $Ntdata->taxable;
                    $total_csgt  += $Ntdata->cgstamt;
                    $total_sgst  += $Ntdata->sgstamt;
                    $total_igst  += $Ntdata->igstamt;
                    $total_cess  += $Ntdata->cessamt;
                    $total_cgst_per += $Ntdata->cgst;
                    $total_sgst_per += $Ntdata->sgst;
                    $total_igst_per += $Ntdata->igst;
                    $total_cess_per += $Ntdata->cess;
                    $total_discount += $discount;
                    $totalmrp = $invdata->unit_mrp * $invdata->qty;
                // print_r($totalmrp);
                    if($Ntdata->tax_type == 'INC'){
                        $totalamt = $totalmrp;
                    }else{
                        $excgst = ($totalmrp - $discount) * $taxper/100;
                        $totalamt = $totalmrp + $excgst;
                    }
                // if($Ntdata->tax_type == 'INC'){
                //     $total_inc_tax = $total_amount -($Ntdata->cgstamt + $Ntdata->sgstamt); 
                // }
                    $total_amount += $totalamt;
                    // $cgst = $tdata->cgst;
                    // $sgst = $tdata->sgst;
                // $tax_cgst = $Ntdata->cgstamt;
                // $tax_sgst = $Ntdata->sgstamt;
                // $tax_igst = $Ntdata->igstamt;
                    $total_qty  += $invdata->qty;
                    $cess_percentage = $Ntdata->cess;
                    $tax_cess = $Ntdata->cessamt;
                    $taxname = $Ntdata->tax_name;
                // print_r($invdata->unit_mrp);
                    $totaltaxamount = $total_csgt + $total_sgst + $total_igst + $total_cess;  
                    $total_inc_tax = $total_amount - $totaltaxamount; 
                    $tax_amount = $total_amount - $total_inc_tax;
                    $total_taxable_amt += $total_inc_tax;
                    $total_tax_amount  += $tax_amount;
                }
            $data .= '<table width="100%" style="position: relative; padding-bottom: 8px;"><tr style="vertical-align: top;">
                    <td width="60%" style="padding-top: 8px; height: auto;">
                    <table width="100%">
                    <tr>
                    <td>Remarks</td>';
                    $data .= '<td style="text-transform: capitalize; text-align:left;">'.$order_details->remark.'</td>';
                    $data .= '</tr>
                    <tr style="position: absolute;bottom: 5px; border: 1px #000 solid; border-left: none;  border-right: none;">
                    <td>GOA-GT1020-00010</td>';
                    $data .= '</tr>
                </table>
                    </td>
                    <td width="10%"></td>
                    <td width="30%">
                        <table align="right" width="100%"><tr><td align="left" width="50%" class="terms-spacing bold">Total MRP Value</td><td align="right"  width="30%" style="border: 1px #000 solid; border-top: none;border-bottom: none;" class="terms-spacing pr-3 bold">'.$total_amount.'</td></tr>
                        <tr><td align="left" class="terms-spacing bold"  width="50%">Total Qty. Transfer</td><td align="right" style="border: 1px #000 solid; border-bottom: none;" class="terms-spacing bold pr-3"  width="30%">'.$total_qty.'</td></tr>
                        <tr><td align="left" class="terms-spacing bold"  width="50%">Total Discount Value</td><td align="right" style="border: 1px #000 solid; border-bottom: none;" class="terms-spacing bold pr-3"  width="30%">'.$total_discount.'</td></tr>
                        <tr><td align="left" class="terms-spacing bold"  width="50%">Total Transfer Value</td><td align="right" style="border: 1px #000 solid;" class="terms-spacing bold pr-3"  width="30%">'.$total_inc_tax.'</td></tr>                 
                        </table>
                    </td>
            </tr></table>';
       
            $data .= '
            <table width="100%" style="border: 1px #000 solid; border-bottom: none;">
            <tr>
                <td style="padding: 4px; background-color: #e0e0e0; font-weight: bold;">GST SUMMARY</td>
            </tr>
                </table>
            ';
            $data .= '
            <table width="100%" style="border: 1px #000 solid;border-top: none;" class="print_receipt_invoice">
            <thead>
            <tr style="background-color: #e0e0e0;">
                <th style="text-align: left;">HSN Code</th>
                <th style="text-align: right;">Taxable Amt.</th>
                <th colspan="2" style="text-align: right;">Integrated GST</th>
                <th colspan="2" style="text-align: right;">Central GST</th>
                <th colspan="2" style="text-align: right;">State GST</th>
                <th colspan="2" style="text-align: right;border-right:none;">Cess</th>
            </tr>
            <tr style="background-color: #e0e0e0;">
                <th style="border-left: none; border-top:none;"></th>
                <th style="border-top:none;"></th>
                <th style="text-align: center; border-top:none;border-right:none;">Rate</th>
                <th style="text-align: right; border-top:none;">Amount</th>
                <th style="text-align: center; border-top:none;border-right:none;">Rate</th>
                <th style="text-align: right; border-top:none;">Amount</th>
                <th style="text-align: center; border-top:none;border-right:none;">Rate</th>
                <th style="text-align: right; border-top:none;">Amount</th>
                <th style="text-align: center; border-top:none;border-right:none;">Rate</th>
                <th style="text-align: right; border-top:none;border-right:none;">Amount</th>
    </tr>
            </thead>
            <tbody>';
                $data .= '<tr>
                    <td style="padding: 4px; text-align: left;border-right: 1px #000 solid;">'.$tax_name.'</td>
                    <td style="padding: 4px; text-align: right;border-right: 1px #000 solid;">'.$taxable.'</td>
                    <td style="padding: 4px; text-align: center;">'.$taxigstper.'</td>
                    <td style="padding: 4px; text-align: right;border-right: 1px #000 solid;">'.$taxigst.'</td>
                    <td style="padding: 4px; text-align: center;">'.$taxcgstper.'</td>
                    <td style="padding: 4px; text-align: right;border-right: 1px #000 solid;">'.$taxcgst.'</td>
                    <td style="padding: 4px; text-align: center;">'.$taxsgstper.'</td>
                    <td style="padding: 4px; text-align: right;border-right: 1px #000 solid;">'.$taxsgst.'</td>
                    <td style="padding: 4px; text-align: center;">'.$taxcessper.'</td>
                    <td style="padding: 4px; text-align: right;">'.$taxcess.'</td>
                </tr>
            </tbody>
            <tfoot>
                <tr style="background-color: #e0e0e0;">
                    <td style="padding: 4px; font-weight: bold; border: 1px #000 solid; border-right: none; border-left: none; text-align: left;border-bottom:none;"><b>Total</b></td>
                    <td style="padding: 4px; font-weight: bold; border: 1px #000 solid; border-left: none; text-align: right;border-bottom:none;">'.$taxable_amount.'</td>
                    <td style="padding: 4px; font-weight: bold; border: 1px #000 solid; border-right: none; border-left: none; text-align: center;border-bottom:none;">'.$total_igst_per.'</td>
                    <td style="padding: 4px; font-weight: bold; border: 1px #000 solid; border-left: none; text-align: right;border-bottom:none;">'.$total_igst.'</td>
                    <td style="padding: 4px; font-weight: bold; border: 1px #000 solid; border-right: none; border-left: none; text-align: center;border-bottom:none;">'.$total_cgst_per.'</td>
                    <td style="padding: 4px; font-weight: bold; border: 1px #000 solid; border-left: none; text-align: right;border-bottom:none;">'.$total_csgt.'</td>
                    <td style="padding: 4px; font-weight: bold; border: 1px #000 solid; border-right: none; border-left: none; text-align: center;border-bottom:none;">'.$total_sgst_per.'</td>
                    <td style="padding: 4px; font-weight: bold; border: 1px #000 solid; border-left: none; text-align: right;border-bottom:none;">'.$total_sgst.'</td>
                    <td style="padding: 4px; font-weight: bold; border: 1px #000 solid; border-right: none;border-left: none; text-align: center;border-bottom:none;">'.$total_cess_per.'</td>
                    <td style="padding: 4px; font-weight: bold; border: 1px #000 solid; border-right: none; border-left: none; text-align: right;border-bottom:none;">'.$total_cess.'</td>
                </tr>
            </tfoot>
                </table>
            ';
           $data .= '<table width="100%" style="padding: 8px 0;"><tr><td style="width:50%">Prepared by:'.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</td><td style="width:50%; text-align: center; "><table width="100%"><tr><td style="padding-bottom: 16px;">For BIBA APPARELS PVT. LTD</td></tr><tr><td>Authorised Signatory</td></tr></table></tr></table>';
            }
            $data .= '<table width="80%" style="margin: 0 auto; "><tr class="print_invoice_last"><td class="bold" style="text-align: center; font-size: 9px; padding-bottom: 8px;">Corporate Address: 13th Floor, Capital Cyber Scape,Sector-59, Golf Course Extension Road,Gurugram,Haryana-122102,INDIA <br>Registered Address: 13th Floor, Capital Cyber Scape,Sector-59, Golf Course Extension Road,Gurugram,Haryana-122102,INDIA <br>CIN : U74110HR2002PTC083029 | Phone :0124-5047000 | Email : info@bibaindia.com | Website:- www.biba.in</td></tr><tr><td class="bold" style="text-align: center; font-size: 9px;">This invoice covered under Policy No - OG-21-1113-1018-00000034 of Bajaj Allianz - General Insurance Company Ltd</td></tr></table>';
            if($totalpage > 1){
                $data .= '<br><hr>';
            }
             
        }
        $return = array('status'=>'success','style'=>$style,'html'=>$data) ;
        return $return;
    }//End of print_html_page

    public function print_html_page_for_agrocel($request){
         $v_id       = $request->v_id;
            $store_id   = $request->store_id; 
            $c_id       = $request->c_id;
            $order_id   = $request->order_id;
            $product_data= [];
            $gst_list    = [];
            $final_gst   = [];
            $detatch_gst = [];
            $rounded     = 0;
            $blank       = str_repeat('&nbsp;',33);
            $data    = '';
            $terms_conditions =  array('');
            $invoice_title = 'Tax Invoice';
            $style = "<style>*{padding:0;margin:0;box-sizing:border-box;-webkit-border-vertical-spacing:0;-webkit-border-horizontal-spacing:0;font-size:14px}.print_receipt_invoice thead tr th{border-right:1px #000 solid; color: #000; border-bottom:1px #000 solid;border-top:1px #000 solid;border-top:none; padding: 5px;}.print_receipt_invoice thead tr:last-child{border-right:none}.print_receipt_invoice tbody tr td{padding: 10px 5px; border-right:1px #000 solid}.print_receipt_invoice tbody tr td pre{min-height:29px;text-align:left;white-space:normal;word-wrap:break-word; font-size: 11px;max-height: 29px;overflow:hidden;line-height: 1.5;}.print_receipt_invoice tbody tr td:last-child{border-right:none}.print_receipt_top-head tr td{padding:2px}.print_invoice_terms td table{text-align: left;}.print_invoice_last td table td{text-align: left;}.print_store_sign td:last-child{text-align: right;}.print_invoice_table_start table tbody tr td{font-size:13px;}.print_invoice_terms td{ border-left: none;}.tablbrd tr td{font-size:10px !important;border: 1px solid #000000;text-align: left !important; padding: 2px;}</style>";


            $printArray  = array();
            $store         = Store::find($store_id);
            $order_details = Invoice::where('invoice_id', $order_id)->first();

            $cart_q = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','0')->where('user_id', $order_details->user_id)->sum('qty');
            $cart_qt = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','1')->where('user_id', $order_details->user_id)->count('qty');
            $cart_qty = $cart_q + $cart_qt;
            $total_amount = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
            // dd($total_amount);
            $count_cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->count();

            if($order_details->transaction_type == 'return'){
                $invoice_title     = 'Credit Note';
            }

            $startitem   = 0;
            $getItem     = 8;
            $countitem   = $count_cart_product;
            $totalpage   = ceil($count_cart_product/$getItem);
            $sr          = 1;
            

            for($i=0;$i < $totalpage ; $i++) {
               
             $cart_product = InvoiceDetails::leftjoin('batch','invoice_details.batch_id','batch.id')->where('invoice_details.t_order_id', $order_details->id)->where('invoice_details.v_id', $order_details->v_id)->where('invoice_details.store_id', $order_details->store_id)->where('invoice_details.user_id', $order_details->user_id)->skip($startitem)->take(8)->get();

            $startitem  = $startitem+$getItem;
            $startitem  = $startitem;
             
            $customer_address = '';
            if(isset($order_details->user->address->address1)){
                $customer_address .= $order_details->user->address->address1;
            }


            $storeAddress = explode('~', $store->address1);
            $storeAdd1    = !empty($storeAddress[0])?$storeAddress[0]:'';
            $storeAdd2    = !empty($storeAddress[1])?$storeAddress[1]:'';
            

            $count = 1;
            $gst_tax = 0;
            $gst_listing = [];
            $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
            //dd($gst_list);
            $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
            $cgst = $sgst = $cess = 0 ;
            // dd($final_gst);
           
            // dd($jsondata);
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
            $bill_logo_id = 5;
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
            $mop_list = [];
            foreach ($payments as $payment) {
            if ($payment->method == 'cash') {
            $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
            $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->cash_collected-$cashReturn ];
            } else {
            $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
            }
            if ($payment->method == 'cash') {
            $cash_collected += (float) $payment->cash_collected;
            $cash_return += (float) $payment->cash_return;
            }
            /*Voucher Start*/
            if($payment->method == 'voucher_credit'){
            $voucher[] = $payment->amount;
            $net_payable = $net_payable-$payment->amount;
            }
            }
            $customer_paid = $cash_collected;
            $balance_refund= $cash_return;
            $total_discount = (float)$order_details->discount+(float)$order_details->manual_discount+(float)$order_details->bill_buster_discount;
            
            $datas = $store->address1;
            $jsondata = json_decode($datas, true);
             $orgAccountDetails = DB::table('org_accounts_store_mapping as oasm')
                            ->rightJoin('org_account_details as oad', 'oad.id', 'oasm.account_id')
                            ->select('oad.vpa', 'oad.account_type', 'oad.bank_name', 'oad.branch_name', 'oad.account_number', 'oad.ifsc_code', 'oad.status')
                            ->where('v_id', $request->v_id)
                            ->where('store_id', $request->store_id)
                            ->where('status', '1')
                            ->first();

        // $orgDetails = [];
        
        // if($orgAccountDetails){
        //     foreach ($orgAccountDetails as $key => $value) {
        //         if($key != 'status')
        //             $orgDetails[$key] = $value == null ? '' : $value;
        //     }
        //     $orgDetails['invoice_no'] = $order_details->invoice_id;
        //     $orgDetails['invoice_date'] = date('d-M-Y', strtotime($order_details->created_at));
        //     $orgDetails['amount'] = round($net_payable);
        //     $orgDetails['supplier_gstin'] = $store->gst;
        // }

        //     $qrimage = $this->generateQRCode(['content' => json_encode($orgDetails)]);

             // $terms_conditions =  array('Sold Material will not be taken back.','We shall not be responsible for the sealed, Taged packed Seeds by Producer.','We shall not be responsible for not germination of Seed due to mistake in proper sowing method or any change in natural atmoshphere which are beyond our control.' );
            $terms =  Terms::where('v_id',$v_id)->get();
                $terms_condition = json_decode($terms);
                foreach ($terms_condition as $value) {
                    $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                }
            ########################
            ####### Print Start ####
            ########################
            
            
            //$data = '<body style="padding: 20px;">';
            
            //$data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"><table class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';
            /* $data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"> <table width="10%"><tr><td><img src="'.$bilLogo.'" alt="" height="80px"></td></tr>
           </table><table width="80%" class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';*/
        $data  .= '<table class="print_invoice_table_start" width="98%" style="outline: 1px #000 solid; margin-top: 20px; margin-bottom: 0px; margin-left: auto; margin-right: auto;">';
            $data  .= '<tr><td>
                            <table width="100%" style="padding-left: 5px; padding-right: 5px;"><tr><td width="20%"><table ><tr><td><img src="'.$bilLogo.'" alt="" height="80px">
                            </td>
                            </tr>
                            </table></td>
                            <td width="50%" >
                            <table width="100%"   class="top-head" bgcolor="#fff" align="left" style=" text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;" >';
             $data  .=  '<tr style="font-size: 12px; padding: 7px;"><td><b style="font-size: 14px;   text-decoration: underline;">Cash memo/ Credit memo</b></td></tr>'; 
                          
            $data  .=  '<tr style="font-size: 16px; padding: 5px;"><td><b style="font-size: 18px;">'.$store->name.'</b></td></tr>';
            if(!empty($jsondata)){
                $data  .=  '<tr><td>'.$jsondata['address'].'</td></tr>';
            }else{
                $data  .=  '<tr><td>'.$storeAdd1.'</td></tr>';
            }
            if($store->address2){
             $data  .=  '<tr><td>'.$store->address2.'</td></tr>';
            }
             $data  .=  '<tr><td><b>'.$storeAdd2.'</b></td></tr>';
            $data  .=  '<tr><td>'.$store->location.','.$store->pincode.','.$store->state.'</td></tr>';
            if($store->gst){
             $data  .=  '<tr><td>GSTIN / UIN: '.$store->gst.'</td></tr>';
            }
            $data  .=  '<tr><td>Tel: '.$store->contact_number.'</td></tr>';
            // $data  .=  '<tr><td>Email: '.$store->email.'</td></tr>';
            $data  .=  '</table></td>
                        <td width="30%" align="right">';
            if(!empty($jsondata)){
                $data  .=   '<table style="text-align: right;  " class="tablbrd">';
               
                $data  .= '<tr><td colspan="4" style="text-align: center!important">Licence Information</td></tr>';
                $data  .= '<tr><td>License Type</td><td>License No</td><td>Start Date</td><td>Expiry Date</td></tr>';
                
                    foreach ($jsondata['license'] as $jsonvalue) {
                    $data  .= '<tr><td>'.$jsonvalue['license_type'].'</td><td>'.$jsonvalue['license_no'].'</td><td>'.$jsonvalue['start_date'].'</td><td>'.$jsonvalue['expiry_date'].'</td></tr>';
                    // dd($jsonvalue);
                    }
                
                $data  .=  '</table>';
            }

            $data  .=  '</td></tr></table></td></tr>';
             
            $data  .= '<tr><td><table style="width: 100%; color: #fff; padding: 5px; text-align: center; border: 1px #000 solid; border-left: none; border-right: none;"><tr><td valign="top" style="line-height: 1.5;  color: #000;"><b>'.$invoice_title.'</b></td></tr></table></td></tr>';
            $data  .=  '<tr>
            <td>
            <table style="width: 100%; color: #fff; padding: 5px;">';
            $data  .=  '<tr>
            <td valign="top" style="line-height: 1.5;  color: #000; font-size: 14px;text-align:left;"> 
            Customer Name    : '.@$order_details->user->first_name.' '.@$order_details->user->last_name.'
            <br>Mobile       : '.$order_details->user->mobile.'
            <br>Place        : '.$customer_address.'
            <br>Village      : '.@$order_details->user->address->citych->name.'
            <br>Survey Number: '.@$order_details->user->address->address2.'
            </td>';

            //<br>'.@$order_details->user->mobile.'
            //Date : '.date('d-M-Y', strtotime($order_details->created_at)).'<br>
            // Bill Book No.  : '.$blank.'
            //<br>Challan No.:   '.$blank.'
            //<br>Order No.  :     '.$blank.'
        $data  .= '<td valign="top" style="line-height: 1.5; color: #000; font-size: 14px;" align="right"> 
             Date :'.date('d-M-Y', strtotime($order_details->created_at)).'<br>           
             Invoice No.:   '.$order_details->invoice_id.'
              <br>GSTIN No     : '.@$order_details->user->gstin.'
            </td>
            </tr></table></td></tr>';
            // $data  .= '<tr><td valign="top" style="line-height: 1.5;  color: #000">'.@$order_details->user->mobile.'</td>';
            // $data .=  '<td valign="top" style="line-height: 2.1; color: #000" align="right">Invoice No:<span style="color: #000;">'.$order_details->invoice_id.'</span></td></tr></table></td></tr>';
            /*$data  .= '<tr><td></td><td valign="top" style="line-height: 2.1; color: #000" align="right">Cashier <span style="color: #000;">'.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</span></td></tr></table></td></tr>';*/
            $data  .= '<tr><td><div  style="height: 350px; overflow: hidden; border-top: 2px #000 solid; border-bottom: 2px #000 solid; "  width="100%" ><table height="100%" width="100%" class="print_receipt_invoice" bgcolor="#fff" style="width: 100%; color: #000;">';
            $data  .= '<thead ><tr align="left">
                        <th width="3%"  style=" font-size: 12px;" >Sr.</th>
                        <th width="10%" valign="center"  style="font-size: 12px; " >Barcode</th>
                        <th width="7%" valign="center"  style=" font-size: 12px;" >HSN Code</th>
                        <th width="40%" valign="center"  style=" font-size: 12px; " >Product Description</th>
                        <th width="5%" valign="center"  style=" font-size: 12px;" >Qty.</th>
                        <th width="5%" valign="center"  style=" font-size: 12px; " >Unit</th>
                        <th width="10%" valign="center"  style=" font-size: 12px;" >Price</th>
                        <th width="8%" valign="center"  style=" font-size: 12px; " >Discount</th>
                        <th width="8%" valign="center"  style=" font-size: 12px; " >Batch</th>
                        <th width="8%" valign="center"  style=" font-size: 12px; " >Mfg Date</th>
                        <th width="8%" valign="center"  style=" font-size: 12px; " >Exp Date</th>
                        <th width="5%" valign="center"  style=" font-size: 12px;" >GST %</td>
                        <th width="7%" valign="center"  style=" font-size: 12px; border-right: none;" >Taxable Amount</th></tr></thead><tbody>';
           
            $srp= '';
            $barcode = '';
            $hsn ='';
            $item_name ='';
            $qty  = '';
            $unit = '';
            $mrp  = '';
            $disc = '';
            $taxp = '';
            $taxb = '';

            $taxable_amount = 0;
            $total_csgt     = 0;
            $total_sgst     = 0;
            $total_cess     = 0;
            $total_igst     = 0;
            $total_gst      = 0;
            $srp            = '';
            $barcode        = '';
            $hsn            = '';
            $item_name      = '';
            $qty            = '';
            $unit           = '';
            $mrp            = '';
            $disc           = '';
            $taxp           = '';
            $batch          = '';
            $mfgdate        = '';
            $expdate        = '';
            $taxb           = '';

            foreach ($cart_product as $key => $value) {

                $remark = isset($value->remark)?' -'.$value->remark:'';
                $tdata    = json_decode($value->tdata);
                $itemLevelmanualDiscount=0;
                 if($value->item_level_manual_discount!=null){
                    $iLmd = json_decode($value->item_level_manual_discount);
                    $itemLevelmanualDiscount= (float)$iLmd->discount;
                 }
                $discount = $value->discount+$value->manual_discount + $value->bill_buster_discount+$itemLevelmanualDiscount;
                $taxper   = $tdata->cgst + $tdata->sgst;
                $taxable_amount += $tdata->taxable;
                $total_csgt  += $tdata->cgstamt;
                $total_sgst  += $tdata->sgstamt;
                $total_cess  += $tdata->cessamt;
                $total_igst  += $tdata->igstamt;
                $total_gst = $total_csgt + $total_sgst + $total_cess + $total_igst;
                $srp       .= '<pre>'.$sr.'</pre>';
                $barcode   .= '<pre>'.$value->barcode.'</pre>';
                $hsn       .= '<pre>'.$tdata->hsn.'</pre>';
                $item_name .= '<pre>'.$value->item_name.$remark.'</pre>';
                $qty       .= '<pre>'.$value->qty.'</pre>';
                $unit      .= '<pre>PCS</pre>';
                $mrp       .= '<pre>'.$value->unit_csp.'</pre>';
                $disc      .= '<pre>'.$discount.'</pre>';
                $batch     .= '<pre>'.$value->batch_no.'</pre>';
                $mfgdate   .= '<pre>'.$value->mfg_date.'</pre>';
                $expdate   .= '<pre>'.$value->exp_date.'</pre>';
                $taxp      .= '<pre>'.$taxper.'</pre>';
                $taxb      .= '<pre>'.$tdata->taxable.'</pre>';
                $sr++;
            }

            $data   .= '<tr align="left">';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$srp.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$barcode.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$hsn.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$item_name.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$qty.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$unit.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$mrp.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$disc.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$batch.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$mfgdate.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$expdate.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;">'.$taxp.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px; border-right: none;">'.$taxb.'</td> </tr>';
            $total_csgt = round($total_csgt,2);
            $total_sgst = round($total_sgst,2);
            $total_cess = round($total_cess,2);
            $total_igst = round($total_igst,2);
            $total_gst = round($total_gst,2);
            
        $orgDetails   = [];
        $qrimage      = '';
        if($orgAccountDetails){
            foreach ($orgAccountDetails as $key => $value) {
              if($key != 'status')
                $orgDetails[$key] = $value == null ? '' : $value;
            }
            $orgDetails['invoice_no'] = $order_details->invoice_id;
            $orgDetails['invoice_date'] = date('d-M-Y', strtotime($order_details->created_at));
            $orgDetails['amount'] = round($net_payable);
            $orgDetails['supplier_gstin'] = $store->gst;
            $orgDetails['GST Amount'] = $total_gst;
            $orgDetails['CGST Amount'] = $total_csgt;
            $orgDetails['SGST Amount'] = $total_sgst;
            $orgDetails['CESS Amount'] = $total_cess;
            $orgDetails['IGST Amount'] = $total_igst;
            $qrimage = $this->generateQRCode(['content' => json_encode($orgDetails)]);
        }

            
            $data   .= '</tbody></table></td></tr></div>';
            $data   .= '<tr>
            <td>
            <table width="100%" style="color: #000;">
            <tr>
            <td>
            <table width="100%" style="padding: 5px;">
            <tr>
            ';
            if($totalpage-1 == $i){
            $data .= '<td width="61%" valign="top">';
            $data   .= '<table align="right" style="padding: 5px;"><tr><td>Total Qty.</td><td>' .$cart_qty.'</td></tr></table></td>';
            $data   .= '<td width="39%">';
            //   $data   .= '<table width="100%" style="padding: 5px;"><tr>
            // <td width="70%" align="right">Amount Before tax</td>
            // <td width="30%" align="right">&nbsp;'.$total_amount.'</td>
            // </tr></table>'; 
            if(!empty($total_discount)){
            $data   .= '<table width="100%" style="padding: 5px;"><tr>
                         <td width="70%" align="right" ><b>Discount Amount</b></td>
                         <td width="30%" align="right" >&nbsp; <b>-'.$total_discount.'</b></td>
                         </tr></table>';   
            }
            $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                            <td width="70%" align="right" >Taxable Amount</td>
                            <td width="30%" align="right" >&nbsp;'.$taxable_amount.'</td></tr>
                         </table>';
            $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                            <td width="70%" align="right" >Add CGST Amount</td>
                            <td  width="30%" align="right" >&nbsp; '.$total_csgt.'</td></tr>
                          </table>';
            $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                          <td width="70%" align="right" >Add SGST Amount</td>
                          <td  width="30%" align="right" >&nbsp;'.$total_sgst.'</td></tr></table>';
            if(!empty($order_details->round_off)){
             $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                          <td width="70%" align="right" >Round Off</td>
                          <td  width="30%" align="right" >&nbsp;'.$order_details->round_off.'</td></tr></table>';    
            }
            
            

            /*$data   .=  '<table width="100%" style="padding: 5px;"><tr>
                          <td width="70%" align="right" >Add IGST Amount</td>
                          <td  width="30%" align="right" >&nbsp;'.$total_sgst.'</td></tr></table>';*/

            $data   .=  '</td></tr></table>';
            $data   .=  '<table width="100%">';
            
            if($order_details->transaction_type == 'sales'){
                foreach($mop_list as $mop){
                    $data .=   '<tr><td bgcolor="#dcdcdc" align="right"  >
                    <b>Paid through '.$mop['mode'].':</b></td>
                    <td align="right" bgcolor="#dcdcdc" ><b>'.$mop['amount'].'</b></td></tr>';
                }
            }
            
            // $data   .=  '</table></td></tr>';
            $data   .=  '<tr><td align="left">Remark: '.$order_details->remark.'</td></tr>';
            $data   .=  '<tr><td align="left">Amount: '.ucfirst(numberTowords_new(round($order_details->total))).'</td></tr>';
            $data   .=  '<tr><td align="left">Pan No: AACCA7205J</td></tr>';
            $data   .=  '<tr><td align="left">CIN: U24210GJ1985PTC007569</td></tr></table>';    

            $data   .= '<table width="100%" style="padding: 5px;"><tr><td width="60%"></td><td width="40%"><table width="100%"><tr align="right"><td width="70%"><b>Total Amount</b></td><td width="30%"><b>'.round($net_payable).'</b></td></tr></table></td></tr></table></td></tr></table></td></tr>';
            }else{
                    
                $data   .= '<td width="61%" height="205px" valign="top">';
                $data   .= '<table align="right" style="padding: 5px;"><tr><td></td><td></td></tr></table></td>';
                $data   .= '<td width="39%">';
                $data   .= '<table width="100%" style="padding: 5px;"><tr>
                <td width="70%" align="right"></td>
                <td width="30%" align="right"></td>
                </tr></table>';
                $data   .= '<table width="100%" style="padding: 5px;"><tr>
                             <td width="70%" align="right" ></td>
                             <td width="30%" align="right" ></td>
                             </tr></table>';
                $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                                <td width="70%" align="right" ></td>
                                <td width="30%" align="right" ></td></tr>
                             </table>';
                $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                                <td width="70%" align="right" ></td>
                                <td  width="30%" align="right" ></td></tr>
                              </table>';
                $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                              <td width="70%" align="right" ></td>
                              <td  width="30%" align="right" ></td></tr></table>';                              
                $data   .=  '</td></tr></table>';
                $data   .=  '<table width="100%"><tr><td width="40%"><table>';
               
                    
                              
                $data   .=  '</table></td></tr>';
                
                $data   .=  '<tr><td align="left"></td></tr>';
                
                if(isset($order_details->remark)){
                    $data   .=  '<tr><td align="left"></td></tr></table>';    
                }

                $data   .= '<table width="100%" style="padding: 5px;"><tr><td width="60%"></td><td width="40%"><table width="100%"><tr align="right"><td width="100%"><b>Continue..</b></td><td width="30%"></td></tr></table></td></tr></table></td></tr></table></td></tr>';

                
            }

            $data   .= '<tr class="print_invoice_terms"><td><table bgcolor="#fff" style="width: 100%; padding: 5px; color: #000; border: 1px #000 solid; border-left: none; border-right: none;">
                <tr width="100%">
                    <td style="padding-bottom: 10px;"><b>Note:</td >
                </tr>';
             foreach($terms_conditions as $term){
                $data .= '<tr width="100%"><td style="padding-bottom: 5px; text-decoration: dotted;">&bull;'.$term.'</td></tr>';
             }
            $data    .= '</table></td></tr>';

            $data    .= '<tr class="print_invoice_last"><td><table bgcolor="#fff" width="100%" style="color: #000000; padding: 5px;"><tr><td width="3%">For:</td><td colspan="1"><b>'.$store->name.'</b></td></tr><tr><td width="12%">Head Office:</td><td colspan="1"><b>Agrocel Industries Limited (Agri - Service - Division)</b><br>
Opposite to Shree Swaminarayan Gurukul,<br>
Near Koday Char Rasta<br>
Koday Tal - Mandvi-Kachchh<br>
Gujarat - 370460<br>
<b>E-mail: koday.centre@agrocel.net</b></td>';
if(!empty($qrimage)){
    $data .= '<td colspan="1" style="text-align: right; width: 28%;"><img width="51%" style="margin-top: -6%;" src='.$qrimage.'></td>';
}

$data .= '</tr></table>';

$data .= '<table width="100%" style="color: #000000; padding-top: 20px !important; padding: 5px;"><tr><td></td></tr><tr class="print_store_sign"><td width="25%">Material Receiver</td><td width="25%">Godown - Keeper</td><td width="50%">Authorised Signatory</td></tr></table></td></tr></table>';
             
            if($totalpage > 1){
                $data .= '<br><hr>';
            }
             
        }
        $return = array('status'=>'success','style'=>$style,'html'=>$data) ;
        return $return;
    }//End of print_html_page_for_agrocel
    
    //print_html_for_mail_attachment
    public function print_html_page_mail($request){
        

            $v_id       = $request->v_id;
            $store_id   = $request->store_id; 
            $c_id       = $request->c_id;
            $order_id   = $request->order_id;
            $product_data= [];
            $gst_list    = [];
            $final_gst   = [];
            $detatch_gst = [];
            $rounded     = 0;
            $data    = '';
            $terms_conditions =  array('');
            $invoice_title = 'Retail Invoice';
            $style = "<style type='text/css' media='all'>
         *{padding:0; margin:0; box-sizing:border-box; -webkit-border-vertical-spacing:0; -webkit-border-horizontal-spacing:0; font-size:14px;}.print_invoice_terms td table{text-align: left;}.print_invoice_last td table td{text-align: left;}.print_store_sign td:nth-child(2){text-align: right;}.print_invoice_last td table:last-child{margin-top: 40px;}.print_invoice_terms td{ border-left: none;}
        </style>";


            $printArray  = array();
            $store         = Store::find($store_id);
            $order_details = Invoice::where('invoice_id', $order_id)->first();
            $cart_q = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','0')->where('user_id', $order_details->user_id)->sum('qty');
            $cart_qt = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','1')->where('user_id', $order_details->user_id)->count('qty');
            $cart_qty = $cart_q + $cart_qt;
            $total_amount = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
            // dd($total_amount);
            $count_cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->count();

            if($order_details->transaction_type == 'return'){
                $invoice_title     = 'Credit Note';
            }

            $startitem   = 0;
            $getItem     = 8;
            $countitem   = $count_cart_product;
            $totalpage   = ceil($count_cart_product/$getItem);
            $sr          = 1;

            for($i=0;$i < $totalpage ; $i++) {
               
             $cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->skip($startitem)->take(8)->get();

            $startitem  = $startitem+$getItem;
            $startitem  = $startitem;
             
            $customer_address = '';
            if(isset($order_details->user->address->address1)){
                $customer_address .= $order_details->user->address->address1;
            }
            if(isset($order_details->user->address->address2)){
                $customer_address .= $order_details->user->address->address2;
            }

            $count = 1;
            $gst_tax = 0;
            $gst_listing = [];
            $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
            //dd($gst_list);
            $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
            $cgst = $sgst = $cess = 0 ;
            // dd($final_gst);
 
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
            $bill_logo_id = 5;
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
            $mop_list = [];
            foreach ($payments as $payment) {
            if ($payment->method == 'cash') {
            $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
            $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->cash_collected-$cashReturn ];
            } else {
            $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
            }
            if ($payment->method == 'cash') {
            $cash_collected += (float) $payment->cash_collected;
            $cash_return += (float) $payment->cash_return;
            }
            /*Voucher Start*/
            if($payment->method == 'voucher_credit'){
            $voucher[] = $payment->amount;
            $net_payable = $net_payable-$payment->amount;
            }
            }
            $customer_paid = $cash_collected;
            $balance_refund= $cash_return;
            $total_discount = (float)$order_details->discount+(float)$order_details->manual_discount+(float)$order_details->bill_buster_discount;
            
            if($v_id == 21){
                // $terms_conditions =  array('Brand accepts only exchange of unused products within 15 days from the
                // date of purchase at stores within the country.','Exchanged product must be in its original condition along with tag
                // attached and accompanied with original invoice.','Product sold during sale/promotion cannot be exchanged/returned.',' Personalized/Customized products will neither be exchanged nor returned.','For more/any information on exchange or any other queries please contact on our customer care at info@janaviindia.com');
                $terms =  Terms::where('v_id',$v_id)->get();
                    $terms_condition = json_decode($terms);
                    foreach ($terms_condition as $value) {
                        $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                    }
            }else if($v_id == 30){
                // $terms_conditions  =  array('Goods once sold cannot be taken back or exchanged under any circumstances');
                $terms =  Terms::where('v_id',$v_id)->get();
                    $terms_condition = json_decode($terms);
                    foreach ($terms_condition as $value) {
                        $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                    }
            }else {

                // $terms_conditions =  array('Any Discrepancy/Complaint regarding the goods should be notified within  1 day of the date mentioned on the invoice','Payment should be made by cheque/DD in favor of K Janavi payable at New Delhi','All Dispatches are subject to Delhi jurisdiction only' );
                $terms =  Terms::where('v_id',$v_id)->get();
                    $terms_condition = json_decode($terms);
                    foreach ($terms_condition as $value) {
                        $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                    }

            }
            ########################
            ####### Print Start ####
            ########################
            
            
            //$data = '<body style="padding: 20px;">';
            
            //$data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"><table class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';
            /* $data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"> <table width="10%"><tr><td><img src="'.$bilLogo.'" alt="" height="80px"></td></tr>
           </table><table width="80%" class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';*/
        $data  .= '<table class="print_invoice_table_start" width="98%" style="outline: 1px #000 solid; margin-top: 20px; margin-bottom: 0px; margin-left: auto; margin-right: auto;">';
            $data  .= '<tr><td>
                            <table width="100%" style="padding-left: 5px; padding-right: 5px;"><tr><td width="10%"><table ><tr><td><img src="'.$bilLogo.'" alt="" height="80px">
                            </td>
                            </tr>
                            </table></td>
                            <td width="90%" >
                            <table width="89%"   class="top-head" bgcolor="#fff" align="center" style=" text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;" >';
                            
            $data  .=  '<tr style="font-size: 16px; padding: 5px;"><td><b style="font-size: 18px;">'.$store->name.'</b></td></tr>';
            $data  .=  '<tr><td>'.$store->address1.'</td></tr>';
            if($store->address2){
             $data  .=  '<tr><td>'.$store->address2.'</td></tr>';
            }
            $data  .=  '<tr><td>'.$store->location.','.$store->pincode.','.$store->state.'</td></tr>';
            if($store->gst){
             $data  .=  '<tr><td>GSTIN: '.$store->gst.'</td></tr>';
            }
            $data  .=  '<tr><td>Tel: '.$store->contact_number.'</td></tr>';
            $data  .=  '<tr><td>Email: '.$store->email.'</td></tr>';
            $data  .=  '</table></td></tr></table></td></tr>';
             
            $data  .= '<tr><td><table style="width: 100%; color: #fff; padding: 5px; text-align: center; border: 1px #000 solid; border-left: none; border-right: none;"><tr><td valign="top" style="line-height: 1.5;  color: #000;"><b>'.$invoice_title.'</b></td></tr></table></td></tr>';
            $data  .=  '<tr>
            <td>
            <table style="width: 100%; color: #fff; padding: 5px;">';
            $data  .=  '<tr>
            <td valign="top" style="line-height: 1;  color: #000; font-size: 12px;text-align:left;">Customer 
            <br>
            <b>'.@$order_details->user->first_name.''.@$order_details->user->last_name.'</b>
            <br>'.@$order_details->user->mobile.'
            <br>'.@$order_details->user->gstin.'
            <br>'.$customer_address.'
            </td>';

            //<br>'.@$order_details->user->mobile.'
            $data  .= '<td valign="top" style="line-height: 1.5; color: #000; font-size: 14px;" align="right">Date : '.date('d-M-Y', strtotime($order_details->created_at)).'
            <br>Invoice No: '.$order_details->invoice_id.'</td>
            </tr></table></td></tr>';
            // $data  .= '<tr><td valign="top" style="line-height: 1.5;  color: #000">'.@$order_details->user->mobile.'</td>';
            // $data .=  '<td valign="top" style="line-height: 2.1; color: #000" align="right">Invoice No:<span style="color: #000;">'.$order_details->invoice_id.'</span></td></tr></table></td></tr>';
            /*$data  .= '<tr><td></td><td valign="top" style="line-height: 2.1; color: #000" align="right">Cashier <span style="color: #000;">'.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</span></td></tr></table></td></tr>';*/
            $data  .= '<tr><td><table height="100%" width="100%" style="width:100%"><tr><td  valign="top" height="300" style="border-top: 1px #000000 solid;  border-bottom: 1px #000000 solid; overflow:hidden"><table height="100%" width="100%" class="print_receipt_invoice" bgcolor="#fff" style="width: 100%; color: #000;">';
            $data  .= '<thead ><tr align="left">
                        <th width="3%"  valign="top" style=" font-size: 12px; border-right:1px #000 solid; color: #000; border-bottom:1px #000 solid; border-top:1px #000 solid; border-top:none; padding: 5px;" >Sr.</th>
                        <th width="10%" valign="center"  style="font-size: 12px;  border-right:1px #000 solid; color: #000; border-bottom:1px #000 solid; border-top:1px #000 solid; border-top:none; padding: 5px;" >Barcode</th>
                        <th width="7%" valign="center"  style=" font-size: 12px; border-right:1px #000 solid; color: #000; border-bottom:1px #000 solid; border-top:1px #000 solid; border-top:none; padding: 5px;" >HSN Code</th>
                        <th width="40%" valign="center"  style=" font-size: 12px; border-right:1px #000 solid; color: #000; border-bottom:1px #000 solid; border-top:1px #000 solid; border-top:none; padding: 5px; " >Product Description</th>
                        <th width="5%" valign="center"  style=" font-size: 12px; border-right:1px #000 solid; color: #000; border-bottom:1px #000 solid; border-top:1px #000 solid; border-top:none; padding: 5px;" >Qty.</th>
                        <th width="5%" valign="center"  style=" font-size: 12px; border-right:1px #000 solid; color: #000; border-bottom:1px #000 solid; border-top:1px #000 solid; border-top:none; padding: 5px; " >Unit</th>
                        <th width="10%" valign="center"  style=" font-size: 12px; border-right:1px #000 solid; color: #000; border-bottom:1px #000 solid; border-top:1px #000 solid; border-top:none; padding: 5px;" >Price</th>
                        <th width="8%" valign="center"  style=" font-size: 12px; border-right:1px #000 solid; color: #000; border-bottom:1px #000 solid; border-top:1px #000 solid; border-top:none; padding: 5px; " >Discount</th>
                        <th width="5%" valign="center"  style=" font-size: 12px; border-right:1px #000 solid; color: #000; border-bottom:1px #000 solid; border-top:1px #000 solid; border-top:none; padding: 5px;" >GST %</th>
                        <th width="7%" valign="center"  style=" font-size: 12px; color: #000; border-bottom:1px #000 solid; border-top:1px #000 solid; border-top:none; padding: 5px; border-right: none;" >Taxable Amount</th></tr></thead><tbody>';
           
            $srp= '';
            $barcode = '';
            $hsn ='';
            $item_name ='';
            $qty  = '';
            $unit = '';
            $mrp  = '';
            $disc = '';
            $taxp = '';
            $taxb = '';

            $taxable_amount = 0;
            $total_csgt     = 0;
            $total_sgst     = 0;
            $total_cess     = 0;
            $srp            = '';
            $barcode        = '';
            $hsn            = '';
            $item_name      = '';
            $qty            = '';
            $unit           = '';
            $mrp            = '';
            $disc           = '';
            $taxp           = '';
            $taxb           = '';

            foreach ($cart_product as $key => $value) {

                $remark = isset($value->remark)?' -'.$value->remark:'';
                $tdata    = json_decode($value->tdata);
                $discount = $value->discount+$value->manual_discount + $value->bill_buster_discount;
                $taxper   = $tdata->cgst + $tdata->sgst;
                $taxable_amount += $tdata->taxable;
                $total_csgt  += $tdata->cgstamt;
                $total_sgst  += $tdata->sgstamt;
                $total_cess  += $tdata->cessamt;
                $srp       .= '<pre style="min-height:29px; text-align:left; white-space:normal; word-wrap:break-word; font-size: 11px; max-height: 29px; overflow:hidden; line-height: 1.5;">'.$sr.'</pre>';
                $barcode   .= '<pre style="min-height:29px; text-align:left; white-space:normal; word-wrap:break-word; font-size: 11px; max-height: 29px; overflow:hidden; line-height: 1.5;">'.$value->barcode.'</pre>';
                $hsn       .= '<pre style="min-height:29px; text-align:left; white-space:normal; word-wrap:break-word; font-size: 11px; max-height: 29px; overflow:hidden; line-height: 1.5;">'.$tdata->hsn.'</pre>';
                $item_name .= '<pre style="min-height:29px; text-align:left; white-space:normal; word-wrap:break-word; font-size: 11px; max-height: 29px; overflow:hidden; line-height: 1.5;">'.$value->item_name.$remark.'</pre>';
                $qty       .= '<pre style="min-height:29px; text-align:left; white-space:normal; word-wrap:break-word; font-size: 11px; max-height: 29px; overflow:hidden; line-height: 1.5;">'.$value->qty.'</pre>';
                $unit      .= '<pre style="min-height:29px; text-align:left; white-space:normal; word-wrap:break-word; font-size: 11px; max-height: 29px; overflow:hidden; line-height: 1.5;">PCS</pre>';
                $mrp       .= '<pre style="min-height:29px; text-align:left; white-space:normal; word-wrap:break-word; font-size: 11px; max-height: 29px; overflow:hidden; line-height: 1.5;">'.$value->unit_mrp.'</pre>';
                $disc      .= '<pre style="min-height:29px; text-align:left; white-space:normal; word-wrap:break-word; font-size: 11px; max-height: 29px; overflow:hidden; line-height: 1.5;">'.$discount.'</pre>';
                $taxp      .= '<pre style="min-height:29px; text-align:left; white-space:normal; word-wrap:break-word; font-size: 11px; max-height: 29px; overflow:hidden; line-height: 1.5;">'.$taxper.'</pre>';
                $taxb      .= '<pre style="min-height:29px; text-align:left; white-space:normal; word-wrap:break-word; font-size: 11px; max-height: 29px; overflow:hidden; line-height: 1.5;">'.$tdata->taxable.'</pre>';
                $sr++;
            }

            $data   .= '<tr align="left">';
                $data   .= '<td valign="top" style="font-size: 12px; padding: 10px 5px; position:relative">
                <div style="position: absolute;border-right: 1px solid #000;height: 480px;left: 0;top: 0;width: 25px;"></div>
                <div style="position: absolute;border-right: 1px solid #000;height: 480px;left: 0;top: 0;width: 114px;"></div>
                <div style="position: absolute;border-right: 1px solid #000;height: 480px;left: 0;top: 0;width: 175px;"></div>
                <div style="position: absolute;border-right: 1px solid #000;height: 480px;left: 0;top: 0;width: 529px;"></div>
                <div style="position: absolute;border-right: 1px solid #000;height: 480px;left: 0;top: 0;width: 573px;"></div>
                <div style="position: absolute;border-right: 1px solid #000;height: 480px;left: 0;top: 0;width: 617px;"></div>
                <div style="position: absolute;border-right: 1px solid #000;height: 480px;left: 0;top: 0;width: 706px"></div>
                <div style="position: absolute;border-right: 1px solid #000;height: 480px;left: 0;top: 0;width: 776px;"></div>
                <div style="position: absolute;border-right: 1px solid #000;height: 480px;left: 0;top: 0;width: 820px;"></div>
                '.$srp.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px; padding: 10px 5px;">'.$barcode.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px; padding: 10px 5px;">'.$hsn.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px; padding: 10px 5px;">'.$item_name.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px; padding: 10px 5px;">'.$qty.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px; padding: 10px 5px;">'.$unit.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px; padding: 10px 5px;">'.$mrp.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px; padding: 10px 5px;">'.$disc.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px; padding: 10px 5px;">'.$taxp.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px;  border-right: none; padding: 10px 5px;">'.$taxb.'</td> </tr>';
            $total_csgt = round($total_csgt,2);
            $total_sgst = round($total_sgst,2);
            $total_cess = round($total_cess,2);
            $data   .= '</tbody></table></td></tr></table></td></tr>';
            $data   .= '<tr>
            <td>
            <table width="100%" style="color: #000;">
            <tr>
            <td>
            <table width="100%" style="padding: 5px;">
            <tr>
            ';
            if($totalpage-1 == $i){
            $data .= '<td width="61%" valign="top">';
            $data   .= '<table align="right" style="padding: 5px;"><tr><td>Total Qty.</td><td>' .$cart_qty.'</td></tr></table></td>';
            $data   .= '<td width="39%">';
            $data   .= '<table width="100%" style="padding: 5px;"><tr>
            <td width="70%" align="right">Amount Before tax</td>
            <td width="30%" align="right">&nbsp;'.$total_amount.'</td>
            </tr></table>';
            $data   .= '<table width="100%" style="padding: 5px;"><tr>
                         <td width="70%" align="right" ><b>Discount Amount</b></td>
                         <td width="30%" align="right" >&nbsp; <b>'.$total_discount.'</b></td>
                         </tr></table>';
            $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                            <td width="70%" align="right" >Taxable Amount</td>
                            <td width="30%" align="right" >&nbsp;'.$taxable_amount.'</td></tr>
                         </table>';
            $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                            <td width="70%" align="right" >Add CGST Amount</td>
                            <td  width="30%" align="right" >&nbsp; '.$total_csgt.'</td></tr>
                          </table>';
            $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                          <td width="70%" align="right" >Add SGST Amount</td>
                          <td  width="30%" align="right" >&nbsp;'.$total_sgst.'</td></tr></table>';


            $data   .=  '</td></tr></table>';
            $data   .=  '<table width="100%"><tr><td width="40%"><table>';
           
                foreach($mop_list as $mop){
                  $data .=   '<tr><td bgcolor="#dcdcdc" align="left" style="padding: 5px;">
                                <b>Paid through '.$mop['mode'].':</b></td>
                                <td align="left" bgcolor="#dcdcdc" style="padding: 5px;"><b>'.$mop['amount'].'</b></td></tr>';
                }
            
            
            $data   .=  '</table></td></tr>';
            
            $data   .=  '<tr><td align="left">Amount: '.ucfirst(numberTowords(round($order_details->total))).'</td></tr>';
            
            if(isset($order_details->remark)){
                $data   .=  '<tr><td align="left">Remark: '.$order_details->remark.'</td></tr></table>';    
            }

            $data   .= '<table width="100%" style="padding: 5px;"><tr><td width="60%"></td><td width="40%"><table width="100%"><tr align="right"><td width="70%"><b>Total Amount</b></td><td width="30%"><b>'.$net_payable.'</b></td></tr></table></td></tr></table></td></tr></table></td></tr>';
            }else{
                    
                $data   .= '<td width="61%" height="205px" valign="top">';
                $data   .= '<table align="right" style="padding: 5px;"><tr><td></td><td></td></tr></table></td>';
                $data   .= '<td width="39%">';
                $data   .= '<table width="100%" style="padding: 5px;"><tr>
                <td width="70%" align="right"></td>
                <td width="30%" align="right"></td>
                </tr></table>';
                $data   .= '<table width="100%" style="padding: 5px;"><tr>
                             <td width="70%" align="right" ></td>
                             <td width="30%" align="right" ></td>
                             </tr></table>';
                $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                                <td width="70%" align="right" ></td>
                                <td width="30%" align="right" ></td></tr>
                             </table>';
                $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                                <td width="70%" align="right" ></td>
                                <td  width="30%" align="right" ></td></tr>
                              </table>';
                $data   .=  '<table width="100%" style="padding: 5px;"><tr>
                              <td width="70%" align="right" ></td>
                              <td  width="30%" align="right" ></td></tr></table>';                              
                $data   .=  '</td></tr></table>';
                $data   .=  '<table width="100%"><tr><td width="40%"><table>';
               
                    
                              
                $data   .=  '</table></td></tr>';
                
                $data   .=  '<tr><td align="left"></td></tr>';
                
                if(isset($order_details->remark)){
                    $data   .=  '<tr><td align="left"></td></tr></table>';    
                }

                $data   .= '<table width="100%" style="padding: 5px;"><tr><td width="60%"></td><td width="40%"><table width="100%"><tr align="right"><td width="100%"><b>Continue..</b></td><td width="30%"></td></tr></table></td></tr></table></td></tr></table></td></tr>';

                
            }

            $data   .= '<tr class="print_invoice_terms"><td><table bgcolor="#fff" style="width: 100%; padding: 5px; color: #000; border: 1px #000 solid; border-left: none; border-right: none;">
                <tr width="100%">
                    <td style="padding-bottom: 10px;"><b>Terms and Conditions:</td >
                </tr>';
             foreach($terms_conditions as $term){
                $data .= '<tr width="100%"><td style="padding-bottom: 5px; text-decoration: dotted;">&bull;'.$term.'</td></tr>';
             }
            $data    .= '</table></td></tr>';
            $data    .= '<tr class="print_invoice_last"><td><table bgcolor="#fff" width="100%" style="color: #000000; padding: 5px;"><tr><td width="3%">For:</td><td colspan="1"><b>'.$store->name.'</b></td></tr></table><table width="100%" style="color: #000000; padding-top: 20px !important; padding: 5px;"><tr><td></td></tr><tr class="print_store_sign"><td width="50%">Authorised Signatory</td><td width="35%" align="right">Prepared by:</td><td align="right">&nbsp;'.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</td></tr></table></td></tr></table>';
             
            if($totalpage > 1){
                $data .= '<br><hr>';
            }
             
        }
        $return = array('status'=>'success','style'=>$style,'html'=>$data) ;
        return $return;
    }  

    public function generateQRCode($params){
    //Using google api
        // dd($params['content']);
        // $data = json_decode(json_encode($params));
        // dd($data);
// dd($params['content']);
    $width = $height = 500;
    $url   = urlencode($params['content']);
    //$width = $height = 100;
    //$url   = urlencode("http://create.stephan-brumme.com");
    $error = "H"; // handle up to 30% data loss, or "L" (7%), "M" (15%), "Q" (25%)
   /* echo "<img src=\"http://chart.googleapis.com/chart?".
    "chs={$width}x{$height}&cht=qr&chld=$error&chl=$url\" />";*/

    return "http://chart.googleapis.com/chart?chs={$width}x{$height}&cht=qr&chld=$error&chl=$url";
  }//End of generateQRCode
}