<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\InvoiceDetails;
use App\Store;
use App\Http\CustomClasses\PrintInvoice;
use App\VendorImage;
use DB;

class ReportController extends Controller
{
   
   public function __construct()
	{
	  $this->middleware('auth');
		 
	}

 public function getSalesItemDetail(Request $request){

     $store_id = $request->store_id;
     $v_id     = $request->v_id;
     $current_date =  date('y-m-d');
      $store           = Store::where('store_id',$store_id)
                           ->where('v_id',$v_id)
                           ->first();
     //dd($store->name);                      
    $itemdetails =InvoiceDetails::join('stores','stores.store_id','=','invoice_details.store_id')
         ->select('invoice_details.item_name','invoice_details.barcode','invoice_details.unit_mrp',DB::raw('round(sum(invoice_details.qty),2) as qty'))
         ->where('invoice_details.transaction_type','sales')
        ->where('invoice_details.store_id',$store_id)
       ->where('invoice_details.v_id',$v_id)
       ->where('invoice_details.date',$current_date)
       ->groupBy('invoice_details.barcode')
    ->get();
   
     if($itemdetails){
   
       $data = $itemdetails;

     }else{
      $data = []; 
     }

		return response()->json(['status'=>'sales_item_list','message' =>'sales item list','date'=>$current_date,'store_name'=>$store->name, 'data' =>  $data], 200);


 }


 public function salesItemPrint(Request $request){


    $v_id       = $request->v_id;
    $store_id   = $request->store_id; 
    $c_id       = $request->c_id;
    $order_id   = $request->order_id;
    $product_data= [];
    $rounded     = 0;
    $store         = Store::find($store_id);
    $current_date =  date('y-m-d');
    // $order_details = Invoice::where('invoice_id', $order_id)->first();

    // $cart_qty = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('qty');

    // $total_amount = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
        // dd($total_amount);

    $cart_product = InvoiceDetails::select('invoice_details.item_name','invoice_details.barcode','invoice_details.unit_mrp',DB::raw('round(sum(invoice_details.qty),2) as qty'))
         ->where('invoice_details.transaction_type','sales')
        ->where('invoice_details.store_id',$store_id)
       ->where('invoice_details.v_id',$v_id)
      ->where('invoice_details.date',$current_date)
       ->groupBy('invoice_details.barcode')
       ->get();
    $count = 1;
    $gst_tax = 0;
    $gst_listing = [];
    $invoice_title = 'Item wise report';
    foreach ($cart_product as $key => $value) {
       //dd($value);

        $itemname = explode(' ', $value->item_name);
        if (count($itemname) === 1) {
                    //$itemcode = $itemname[0];
        } else {
            $itemcode = $itemname[0]; 
            unset($itemname[0]);
            $item_name = implode(' ', $itemname);
        }

        $rate     = round($value->unit_mrp);


        $product_data[]  = [
            'row'           => 1,
            'sr_no'         => $count++,
            'item_code'     => $value->barcode,
            'name'          => $value->item_name,
        ];
        $product_data[] = [
            'row'           => 2,
            'rsp'           => $value->unit_mrp,
            'rate'          => "$rate",
            'qty'           => $value->qty,
            
                      
        ];     

    }

     //dd($product_data);
  
        $bilLogo      = '';
        $bill_logo_id = 11;
        $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
        if($vendorImage)
        {
            $bilLogo = env('ADMIN_URL').$vendorImage->path;
        }

        

        ########################
        ####### Print Start ####
        ########################
        //$terms_conditions =  array('(1) Exchange Within 7 days only.','(2) MRP Are Inclusive of Applicable Tax');

        $terms_conditions =  array('1.Goods once sold will not be taken back.','2. All disputes will be settled in Jaipur court.','3. E. & O.E.','4. This is computer generated invoice and does not require any stamp or signature');  
        if($v_id == 63){
          $terms_conditions =  array('1.Products can be exchanged within 7 days from date of purchase.',
            '2.Original receipt/invoice copy must be carried.','3.In the case of exchange – the product must be in it’s original and unused condition, along with all the original price tags, packing/boxes and barcodes received.','4.We will only be able to exchange products. Afraid there will not be refund in any form.','5.We do not offer any kind of credit note at the store.','6.There will be no exchange on discounted/sale products.','7.In the case o damage or defect – the store teams must be notified within 24 hours of purchase');  
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
$printInvioce->addLine('GSTIN: '.$store->gst, 22);
if($store->cin){
    $printInvioce->addLine('CIN: '.$store->cin, 22);            
}

$printInvioce->addDivider('-', 20);
$printInvioce->addLine($invoice_title  , 22,true);

$printInvioce->addDivider('-', 20);

$printInvioce->addLineLeft(' Date : '.date('d-M-Y'), 22);

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

$printInvioce->addDivider('-', 20);

        /*' '.$product_data[$i]['rate'],
                    $product_data[$i]['qty'],
                    $product_data[$i]['tax_amt'],
                    $product_data[$i]['total']
                    
                    $product_data[$i]['item_code'],
                    $taxable_amount?$product_data[$i]['hsn']:'',
                    $product_data[$i]['discount']


                    */

                    $printInvioce->tableStructure(['#','Barcode','Item Name',''], [5,10,18.99,0.01], 22);
                    $printInvioce->tableStructure(['','Unit MRP','Qty Sold',''], [8,15, 10.99,0.01], 22);
                
                    $printInvioce->addDivider('-', 20);


                    for($i = 0; $i < count($product_data); $i++) {
                        if($product_data[$i]['row'] == 1) {
                            $printInvioce->tableStructure([
                                $product_data[$i]['sr_no'],
                                $product_data[$i]['item_code'],
                                $product_data[$i]['name'],
                                "",
                            ],
                            [5,10,18.99,0.01], 22);
                        }
                        if($product_data[$i]['row'] == 2)  {
                            $printInvioce->tableStructure([
                            	"",
                                $product_data[$i]['rate'],
                                $product_data[$i]['qty'],
                                "",
                            ],
                            [8,15,10.99,0.01], 22);
                        }
                        // if($product_data[$i]['row'] == 3){
                        //     $printInvioce->tableStructure([
                        //         $product_data[$i]['item_code'],
                        //         $taxable_amount?$product_data[$i]['hsn']:'',
                        //         $product_data[$i]['discount']
                        //     ],
                        //     [20,8, 6], 22);
                        // }
                    }
                    $printInvioce->addDivider('-', 20);
                    // $printInvioce->tableStructure(['Total', $cart_qty,$total_amount], [20, 4,14], 22,true);
                    // $printInvioce->addDivider('-', 20);
                    // $printInvioce->addLineLeft('Rupee: '.ucfirst(numberTowords(round($order_details->total))).' Only' , 22);

                    // $printInvioce->addDivider('-', 20);
                    // $printInvioce->addLineLeft('  Customer Paid: '.format_number($customer_paid), 22, true);
                    // $printInvioce->addLineLeft('  Balance Refund: '.format_number($balance_refund), 22, true);
                    // $printInvioce->addDivider('-', 20);
                    // /*Tax Start */
                    // if($taxable_amount > 0){

                    //     $printInvioce->leftRightStructure('GST Summary','', 22);
                    //     $printInvioce->addDivider('-', 20);

                    //     if(!empty($detatch_gst)) {

                    //         if($total_cess > 0){
                    //             $printInvioce->tableStructure(['Desc', 'Taxable', 'CGST','SGST','CESS'], [8,9, 6,6,5], 22);
                    // //$printInvioce->tableStructure(['', 'Amt','Amt','Amt','Amt'], [8, 8, 6,6,6], 22);
                    //             $printInvioce->addDivider('-', 20);
                    //             foreach ($detatch_gst as $index => $gst) {
                    //                 $printInvioce->tableStructure([$gst->name,
                    //                     ' '.$gst->taxable,
                    //                     $gst->cgst,
                    //                     $gst->sgst,
                    //                     $gst->cess],
                    //                     [8,9, 6,6,5], 22);
                    //             }
                    //             $printInvioce->addDivider('-', 20);
                    //             $printInvioce->tableStructure(['Total',
                    //                 format_number($taxable_amount),
                    //                 format_number($total_csgt),
                    //                 format_number($total_sgst),
                    //                 format_number($total_cess)], [8, 9, 6,6,5], 22, true);
                    //         }else{
                    //          $printInvioce->tableStructure(['Desc', 'Taxable', 'CGST','SGST'], [8,12, 7,7], 22);
                    //$printInvioce->tableStructure(['', 'Amt','Amt','Amt','Amt'], [8, 8, 6,6,6], 22);

                //              $printInvioce->addDivider('-', 20);
                //              foreach ($detatch_gst as $index => $gst) {
                //                 $printInvioce->tableStructure([$gst->name,
                //                     ' '.$gst->taxable,
                //                     $gst->cgst,
                //                     $gst->sgst 
                //                 ],
                //                 [8,12, 7,7], 22);
                //             }

                //             $printInvioce->addDivider('-', 20);
                //             foreach ($detatch_gst as $index => $gst) {
                //                 $printInvioce->tableStructure([$gst->name,
                //                     ' '.$gst->taxable,
                //                     $gst->cgst,
                //                     $gst->sgst,
                //                     $gst->cess],
                //                     [8,12, 7,7], 22);
                //             }

                //             $printInvioce->addDivider('-', 20);
                //             $printInvioce->tableStructure(['Total',
                //                 format_number($taxable_amount),
                //                 format_number($total_csgt),
                //                 format_number($total_sgst) 
                //             ], [8, 12, 7,7], 22, true);
                //         }

                //         $printInvioce->addDivider('-', 20);
                //     }
                // }
                // $total_discount = $order_details->discount+$order_details->manual_discount+$order_details->bill_buster_discount;
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
                // $printInvioce->leftRightStructure('Net Payable', format_number($net_payable), 22);

                // if($v_id != 53){

                //     $printInvioce->addDivider('-', 20);
                //     $printInvioce->addLineLeft(' Terms and Conditions', 22, true);
                //     $printInvioce->addDivider('-', 20);
                //     foreach ($terms_conditions as $term) {
                //         $printInvioce->addLineLeft($term, 20);
                //     }

                // }
                $response = ['status' => 'success', 
                'print_data' =>($printInvioce->getFinalResult())];

                if($request->has('response_format') && $request->response_format == 'ARRAY'){
                    return $response;
                }
                return response()->json($response, 200);   

    }

}
