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
class PrintController extends Controller
{
    /*public function __construct()
    {
        $this->middleware('auth' , ['except' => ['order_receipt','rt_log'] ]);
        $this->cartconfig  = new CartconfigController;     
    }*/
    public function print_html_page($request){

            $v_id       = $request->v_id;
            $store_id   = $request->store_id; 
            $c_id       = $request->c_id;
            $order_id   = $request->order_id;
            $product_data= [];
            $gst_list    = [];
            $final_gst   = [];
            $detatch_gst = [];
            $rounded     = 0;
            $invoice_title = 'Retail Invoice';
            $printArray  = array();
            $store         = Store::find($store_id);
            $order_details = Invoice::where('invoice_id', $order_id)->first();
            $cart_q = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','0')->where('user_id', $order_details->user_id)->sum('qty');
            $cart_qt = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','1')->where('user_id', $order_details->user_id)->count('qty');
            $cart_qty = $cart_q + $cart_qt;
            $total_amount = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
            // dd($total_amount);
            $cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->get();
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
             $terms_conditions =  array('Any Discrepancy/Complaint regarding the goods should be notified within  1 days of the date mentioned on the invoice','Payment should be made by cheque/DD in favor of K Janavi payable at New Delh','All Dispatches are subject to Delhi jurisdiction only' );
            ########################
            ####### Print Start ####
            ########################
            
            $style = "<style>*{padding:0;margin:0;box-sizing:border-box;-webkit-border-vertical-spacing:0;-webkit-border-horizontal-spacing:0;font-size:14px}.print_receipt_invoice thead tr th{border-right:1px #000 solid; color: #000; border-bottom:1px #000 solid;border-top:1px #000 solid;border-top:none; padding: 5px;}.print_receipt_invoice thead tr:last-child{border-right:none}.print_receipt_invoice tbody tr td{padding: 10px 5px; border-right:1px #000 solid}.print_receipt_invoice tbody tr td pre{min-height:30px;text-align:left;white-space:normal;word-wrap:break-word; font-size: 11px;}.print_receipt_invoice tbody tr td:last-child{border-right:none}.print_receipt_top-head tr td{padding:2px}.print_invoice_terms td table{text-align: left;}.print_invoice_last td table td{text-align: left;}.print_store_sign td:nth-child(2){text-align: right;}.print_invoice_last td table:last-child{margin-top: 40px;}.print_invoice_table_start table tbody tr td{font-size:13px;}.print_invoice_terms td{ border-left: none;}</style>";
            //$data = '<body style="padding: 20px;">';
            
            //$data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"><table class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';
            /* $data  = '<table class="print_invoice_table_start" width="100%" style="outline: 1px #000 solid;"><tr><td bgcolor="#fff"> <table width="10%"><tr><td><img src="'.$bilLogo.'" alt="" height="80px"></td></tr>
           </table><table width="80%" class="print_receipt_top" bgcolor="#fff" style="width: 100%; text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;">';*/
        $data  = '<table class="print_invoice_table_start" width="98%" style="outline: 1px #000 solid; margin: 0 auto;">';
            $data  .= '<tr><td>
                            <table width="100%" style="padding-left: 5px; padding-right: 5px;"><tr><td width="10%"><table ><tr><td><img src="'.$bilLogo.'" alt="" height="80px">
                            </td>
                            </tr>
                            </table></td>
                            <td width="90%" >
                            <table width="89%"   class="top-head" bgcolor="#fff" align="left" style=" text-align: center; padding-left: 5px; padding-right: 5px; padding-top: 10px; padding-bottom: 10px; color: #000;" >';
                            
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
            <td valign="top" style="line-height: 1;  color: #000; font-size: 12px;">Customer 
            <br>
            <b>'.@$order_details->user->first_name.''.@$order_details->user->last_name.'</b>
            <br>'.@$order_details->user->mobile.'
            <br>'.@$order_details->user->gstin.'
            </td>';

            //<br>'.@$order_details->user->mobile.'
            $data  .= '<td valign="top" style="line-height: 1.5; color: #000; font-size: 14px;" align="right">Date : '.date('d-M-Y', strtotime($order_details->created_at)).'
            <br>Invoice No: '.$order_details->invoice_id.'</td>
            </tr></table></td></tr>';
            // $data  .= '<tr><td valign="top" style="line-height: 1.5;  color: #000">'.@$order_details->user->mobile.'</td>';
            // $data .=  '<td valign="top" style="line-height: 2.1; color: #000" align="right">Invoice No:<span style="color: #000;">'.$order_details->invoice_id.'</span></td></tr></table></td></tr>';
            /*$data  .= '<tr><td></td><td valign="top" style="line-height: 2.1; color: #000" align="right">Cashier <span style="color: #000;">'.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</span></td></tr></table></td></tr>';*/
            $data  .= '<tr><td><div  style="height: 420px; overflow: hidden; border-top: 2px #000 solid; border-bottom: 2px #000 solid; "  width="100%" ><table height="100%" width="100%" class="print_receipt_invoice" bgcolor="#fff" style="width: 100%; color: #000;">';
            $data  .= '<thead ><tr align="left">
                        <th width="3%"  style=" font-size: 12px;" >Sr.</th>
                        <th width="10%" valign="center"  style="font-size: 12px; " >Barcode</th>
                        <th width="7%" valign="center"  style=" font-size: 12px;" >HSN Code</th>
                        <th width="40%" valign="center"  style=" font-size: 12px; " >Product Description</th>
                        <th width="5%" valign="center"  style=" font-size: 12px;" >Qty.</th>
                        <th width="5%" valign="center"  style=" font-size: 12px; " >Unit</th>
                        <th width="10%" valign="center"  style=" font-size: 12px;" >Price</th>
                        <th width="8%" valign="center"  style=" font-size: 12px; " >Discount</th>
                        <th width="5%" valign="center"  style=" font-size: 12px;" >GST %</td>
                        <th width="7%" valign="center"  style=" font-size: 12px; border-right: none;" >Taxable Amount</th></tr></thead><tbody>';
            $sr = 1;
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
            foreach ($cart_product as $key => $value) {

                $remark = isset($value->remark)?' -'.$value->remark:'';
                $tdata    = json_decode($value->tdata);
                $discount = $value->discount+$value->manual_discount + $value->bill_buster_discount;
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
                $data   .= '<td valign="top" style="font-size: 12px;">'.$taxp.'</td>';
                $data   .= '<td valign="top" style="font-size: 12px; border-right: none;">'.$taxb.'</td> </tr>';
            $total_csgt = round($total_csgt,2);
            $total_sgst = round($total_sgst,2);
            $total_cess = round($total_cess,2);
            $data   .= '</tbody></table></td></tr></div>';
            $data   .= '<tr>
            <td>
            <table width="100%" style="color: #000;">
            <tr>
            <td>
            <table width="100%" style="padding: 5px;">
            <tr>
            <td width="61%" valign="top">';
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
            
            $data   .=  '<tr><td align="left">Amount Word: '.ucfirst(numberTowords(round($order_details->total))).'</td></tr>';

            $data   .=  '<tr><td align="left">Remark: '.$order_details->remark.'</td></tr></table>';
            $data   .= '<table width="100%" style="padding: 5px;"><tr><td width="60%"></td><td width="40%"><table width="100%"><tr align="right"><td width="70%"><b>Total Amount</b></td><td width="30%"><b>'.$net_payable.'</b></td></tr></table></td></tr></table></td></tr></table></td></tr>';
            $data   .= '<tr class="print_invoice_terms"><td><table bgcolor="#fff" style="width: 100%; padding: 5px; color: #000; border: 1px #000 solid; border-left: none; border-right: none;">
                <tr width="100%">
                    <td style="padding-bottom: 10px;"><b>Terms and Conditions:</td >
                </tr>';
             foreach($terms_conditions as $term){
                $data .= '<tr width="100%"><td style="padding-bottom: 5px; text-decoration: dotted;">&bull;'.$term.'</td></tr>';
             }
            $data    .= '</table></td></tr>';
            $data    .= '<tr class="print_invoice_last"><td><table bgcolor="#fff" width="100%" style="color: #000000; padding: 5px;"><tr><td width="3%">For:</td><td colspan="1"><b>'.$store->name.'</b></td></tr></table><table width="100%" style="color: #000000; padding-top: 20px !important; padding: 5px;"><tr><td></td></tr><tr class="print_store_sign"><td width="50%">Authorised Signatory</td><td width="35%" align="right">Prepared by:</td><td align="right">&nbsp;'.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name.'</td></tr></table></td></tr></table>';
        $return = array('style'=>$style,'html'=>$data) ;
        return $return;
    }
}