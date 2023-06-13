<?php

namespace App\Http\Controllers\PathFinder;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use Validator;
use Log;
use DB;
use App\Model\Oauth\OauthClient;
use App\Invoice;
use App\InvoiceDetails;
use App\Organisation;
use App\Store;

class ReportController extends Controller
{
    //

    public function __construct()
  {
    //$this->middleware('auth');
     $this->middleware('auth', ['except' => ['getSalesReport']]);
  }



 public function getSalesReport(Request $request){

  //dd($request->all());

  if ($request->isJson()) {
      
      try {
       $data = $request->json()->all();
       //dd($data); 
      $validator = Validator::make($data,[
                'organisation_code' => 'required',
                'store_code' => 'required',
                'from_date' => 'required|date_format:d-m-Y',
                'to_date' => 'required|date_format:d-m-Y',
                ]
            ); 
           if($validator->fails()){
              $error_list = [];
              foreach($validator->messages()->get('*') as $key => $err){
                $error_list[] = [ 'error_for' => $key , 'messages' => $err ];  
              }

              return response()->json([ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' => $error_list ] , 422);
           }else{
                 
                $client = oauthUser($request);
            $client_id = $client->client_id;

            //This code is added when we are using client
            //$clientMapping = new ClientMappingController;
            $vendor = Organisation::select('id','vendor_code')->where('ref_vendor_code', $data['organisation_code'])->first();
            $v_id = $vendor->id;

            $store = Store::select('store_id','short_code')->where('v_id', $v_id)->where('mapping_store_id', $data['store_code'])->first();
            if(!$store){
              $store = Store::select('store_id','short_code')->where('v_id', $v_id)->where('store_reference_code', $data['store_code'])->first();
            }
            //dd($vendor);
            if(!$vendor){

              $error_list =  [ 
                [ 'error_for' =>  'organisation_code' ,  'messages' => ['Unable to find This Organisation'] ] 
              ]; 
              return response()->json([ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' =>  $error_list] , 422);
            }else{
                       $from_date=date("Y-m-d", strtotime($data['from_date']));
                        $to_date=date("Y-m-d", strtotime($data['to_date']));
                   $invoices=Invoice::join('payments','payments.invoice_id','invoices.invoice_id')
                                      ->select(DB::raw('group_concat(payments.method) as method'),'invoices.invoice_id','invoices.id','invoices.time','invoices.date','invoices.date','invoices.subtotal','invoices.discount','invoices.transaction_type','invoices.manual_discount','invoices.lpdiscount','invoices.coupon_discount','invoices.bill_buster_discount','invoices.tax','invoices.total','invoices.v_id')
                                 ->where('invoices.v_id',$v_id)
                                 ->where('invoices.store_id',$store->store_id)
                                  ->whereBetween('invoices.date',[$from_date,$to_date])
                                 ->groupBy('invoices.invoice_id')
                                 ->orderBy('invoices.id','DESC')
                                 ->get();
                      //dd($invoices);           
                   if($invoices){
                    $data = [];
             foreach ($invoices as $key => $invoice) {
                  
              $data[]=['receipt_number' =>$invoice->invoice_id,
                      'receipt_date'=>$invoice->date,
                      'transaction_time'=>$invoice->time,
                      'invoice_amount'=>(format_number((float)$invoice->subtotal-(float)$invoice->tax)),
                      'discount_amount'=>format_number($invoice->discount + $invoice->manual_discount + $invoice->lpdiscount + $invoice->coupon_discount + $invoice->bill_buster_discount),
                      'tax_amount'=>$this->getTaxDetails($invoice->id,$invoice->v_id),
                      'net_sale_amount'=>$invoice->total,
                      'payment_mode'=>$invoice->method,
                      'transaction_status'=>$invoice->transaction_type,
                      //'net_sale_amount' =>

                     ];
                    }
             }else{
                    $data = [];
             }                   
                   
                   return response()->json([ 'status' => 'success' , 'message' => 'sales_data' , 'data' =>$data] , 200);
            }

           }

      }catch( \Exception $e ) {

        Log::error($e);
          return response()->json([ 'status' => 'fail' , 'message' => 'Server Error'   ] , 500);
  }

    }
 }

   public function getTaxDetails($id,$v_id){

  
     $cartTax = InvoiceDetails::where('t_order_id',$id)->where('v_id',$v_id)->get()->pluck('id');
     
      $totalTax = InvoiceDetails::whereIn('id', $cartTax)->get();
                          
    
        $total_tax = 0;
        $sub_total = 0;
        $cgstamt   = 0;
        $sgstamt   = 0;
        $igstamt   = 0;
        $cessamt   = 0;
        $tax_cal   = array();
        $gstdatail = 0; 
        $gst       = array();

       // dd($b);
        if(isset($totalTax)  && count($totalTax) > 0 ){
        foreach ($totalTax as $key => $value) {
            $decodeTax = json_decode($value->tdata);

            $total_tax += @$decodeTax->tax_amount;

            $cgstamt += @$decodeTax->cgstamt;

            $sgstamt += @$decodeTax->sgstamt;

            $igstamt += @$decodeTax->igstamt;

            $cessamt += @$decodeTax->cessamt;

            $sub_total += $decodeTax->taxable;

            if($decodeTax->cgstamt != 0){
                $totalgst   = $decodeTax->cgstamt+$decodeTax->sgstamt+$decodeTax->igstamt+$decodeTax->cessamt;
                // dd($totalgst);
                @$gst['total']  +=$totalgst;  
            }elseif($decodeTax->igstamt != 0){
                @$gst['total']  = $decodeTax->cgstamt+$decodeTax->sgstamt+$decodeTax->igstamt+$decodeTax->cessamt; 
            }else{
              
               @$gst['total']  += 0;

            }

        }
        //dd($cgstamt);
        }
        $gstdatail = $gst['total'];
        $tax_cal['cgstamt']= round($cgstamt,3);
        $tax_cal['sgstamt']= round($sgstamt,3);
        $tax_cal['igstamt']= round($igstamt,3);
         $tax_cal['cessamt']= round($cessamt,3);
        $tax_cal['gst']    = round($gstdatail,3); 
        //dd($tax_cal);
      return $tax_cal;  

   }     

}
