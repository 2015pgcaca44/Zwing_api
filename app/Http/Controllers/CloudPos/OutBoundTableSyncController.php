<?php

namespace App\Http\Controllers\CloudPos;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\SettlementSession;
use App\Order;
use App\OrderDetails;
use App\OrderItemDetails;
use App\User;
use App\CashRegister;
use App\Invoice;
use App\InvoiceDetails;
use App\InvoiceItemDetails;
use App\Payment;
use App\Store;
use App\Model\Item\ItemList;
use App\Model\Items\VendorItems;
use App\Http\Controllers\CloudPos\CartconfigController;
use DB;
use Auth;
use App\Model\Items\VendorSkuDetailBarcode;
use App\Model\Items\VendorSku;

class OutBoundTableSyncController extends Controller
{
  public function __construct()
  {
    //$this->middleware('auth');
  }

  public function outBoundSync(Request $request){

   $type = $request->type;
   $store_id = $request->store_id;
   $v_id = $request->v_id;
   $trans_from = $request->trans_from;
   if($type === 'session') {
     return $this->sessionRecordUpdate($request);
   }elseif($type==='invoice'){
     return $this->invoiceSync($request);
   }elseif($type ==='checkinvoice'){
    return $this->isInvoiceIdExit($request);
   }     
 }

 public function sessionRecordUpdate(Request $request){

  $sessions_data = $request->session_data;
  $type = $request->type;
  $store_id = $request->store_id;
  $v_id = $request->v_id;
  $trans_from =$request->trans_from;
  $udidtoken  = $request->udidtoken;
  
  if($trans_from == 'CLOUD_TAB_WEB') {
    $session_blob = $request->file('session');
    $data = json_decode(file_get_contents($session_blob));

    $session_data = json_decode($data->session_data);
  } else {
    $session_data = json_decode($sessions_data);
  }

    foreach ($session_data as $item) {

     $session =SettlementSession::updateOrCreate(
      [
        'id' => $item->id,
      ],
      [
      'vu_id' => $item->vu_id,
      'settlement_date' => date('Y-m-d',strtotime($item->settlement_date)),
      'trans_from' => $request->trans_from,
      'opening_balance'=> $item->opening_balance,
      'opening_time'       => $item->opening_time,
      'closing_balance'   => isset($item->closing_balance) ? $item->closing_balance : null,
      'closing_time'    =>isset($item->closing_time) ? $item->closing_time : null,
      'store_id'        =>$store_id,
      'v_id'            =>$v_id,
      'cash_register_id' =>$request->terminal_id,
      'status'          =>isset($item->closing_balance) && $item->closing_balance != null ? '1' : '0',
      'session_close_type' => isset($item->closing_balance) && $item->closing_balance != null ? 'REGULAR' : null
    ]);
  }
  if($session){ 
   return response()->json(['status' => 'success', 'message' =>'Updated Successfully'],200);
 }else{
   return response()->json(['status' => 'fail', 'message' =>'Some error has occurred, Please try again'],200);
 } 
}

public function invoiceSync(Request $request){


      if($request->trans_from == 'CLOUD_TAB_WEB' || $request->trans_from == 'ANDROID_VENDOR') {
        $data = $request->file('invoice_data');
        $data_invoice = json_decode(file_get_contents($data));
        // $data_invoice = json_decode($request->data);

        $customer = $data_invoice->customer;
        $payment_list  = $data_invoice->payment_list;
        $item_list     = $data_invoice->item_list;
        $date          = $data_invoice->date;
        $time          = $data_invoice->time;
        $transaction_sub_type=$data_invoice->transaction_sub_type;
        $transaction_type=$data_invoice->transaction_type;
        $session_id        = $data_invoice->session_id;
        $invoice_id    = $data_invoice->invoice_no;
      } else {
        $customer = $request->customer;
        $payment_list  = $request->payment_list;
        $item_list     = $request->item_list;
        $date          = $request->date;
        $time          = $request->time;
        $transaction_sub_type=$request->transaction_sub_type;
        $transaction_type=$request->transaction_type;
        $session_id        = $request->session_id;
        $invoice_id    = $request->invoice_no;
      }

  $date_time = $date.' '.$time;
  $type = $request->type;
  $store_id = $request->store_id;
  $v_id    = $request->v_id;
  $vu_id   = $request->vu_id;
  $month         = date('n',strtotime($date));
  $year          = date('Y',strtotime($date));
  $trans_from        =$request->trans_from; 
  $udidtoken         = $request->udidtoken;
  $payment_data      = json_decode($payment_list);
  $terminalInfo = CashRegister::where('udidtoken',$udidtoken)->first(); 
  $customerData = json_decode($customer, true);
  $stores       = Store::find($store_id);
  $short_code   = $stores->short_code;
  $invoice       = Invoice::where('invoice_id',$invoice_id)->first();
  if($invoice){
  return response()->json(['status' => 'success','message' => 'Invoice sync successfully'], 200);
   }else{
  try{      
    DB::beginTransaction(); 
    // dd($customerData);
    // echo $customerData;
    if(empty($customerData)) {
      $userDetail = User::where('v_id',$v_id)->where('mobile', '3'.Auth::user()->mobile)->first();
    } else {
      $userDetail=User::where('v_id',$v_id)->where('mobile',$customerData['mobile'])->first();
    }    
    if($userDetail){
      $c_id = $userDetail->c_id;
    }else{
      $userDetail = new User;
      if(gettype($customerData) == 'array') {
        $userDetail->first_name=$customerData['first_name'];
        $userDetail->last_name =$customerData['last_name'];
        $userDetail->mobile=$customerData['mobile'];
        $userDetail->v_id = $v_id;    
        // $userDetail->save();
        // $c_id =$userDetail->c_id; 
      } else {
        // $userDetail = new User;
        $userDetail->first_name=$customerData->first_name;
        $userDetail->last_name =$customerData->last_name;
        $userDetail->mobile=$customerData->mobile;
        $userDetail->v_id=$v_id;    
      }
      $userDetail->save();
      $c_id =$userDetail->c_id; 
    }          
    $itemsList      = json_decode($item_list);
    $itemcollect = collect($itemsList);
    $items = $itemcollect->map(function ($item, $key) {
          if($item->md_discount=='' || $item->md_discount==null){
           $item->md_discount = 0 ;
          }
        return $item;
       });
    $totalqty = $items->sum('qty'); 
    $tax      =$items->sum('tax');
    $total    =$items->sum('total');
    $subtotal  =$items->sum('subtotal');
    $discount  = $items->sum('discount');
    $manual_discount = $items->sum('md_discount');  
    // $t_order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
    $t_order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
    $t_order_id = $t_order_id + 1;
    $order_id = order_id_generate($store_id, $c_id, $trans_from);
    $custom_order_id = custom_order_id_generate(['store_id' => $store_id, 'user_id' => $c_id, 'trans_from' => $trans_from]);
    $store_data = Store::find($store_id);
    $order = new Order;
    $order->order_id = $order_id;
    $order->custom_order_id = $custom_order_id;
    $order->o_id = $t_order_id;
    $order->v_id = $v_id;
    $order->store_id = $store_id;
    $order->user_id = $c_id;
    $order->trans_from = $trans_from;
    $order->qty = $totalqty;
    $order->subtotal = $subtotal;
    $order->discount = $discount;
    $order->transaction_sub_type = $transaction_sub_type;
    if ($manual_discount>0){
      $order->manual_discount = $manual_discount;
      $order->md_added_by = $request->vu_id;
    }
    $order->store_gstin = $store_data->gst;
    $order->store_gstin_state_id = $store_data->state_id;
    //$order->bill_buster_discount = $bill_buster_discount;
    $order->tax = (float)$tax;
    $order->total = (float) $total;
    $order->status = 'success';
    $order->date = $date;
    $order->time = $time;
    $order->month = $month;
    $order->year = date('Y');
    $order->payment_type = 'full';
    $order->is_invoice = '0';
    $order->vu_id = $vu_id;
    $order->channel_id = '2';
    $order->created_at = $date_time;
    $order->verify_status = '1';
    $order->verify_status_guard = '1';
    $order->save();
    $section_target_offer = [];

    if($order){
      foreach ($items as $item) {
       $pdata=$this->calculatePata($item);
       if($trans_from=="ANDROID_VENDOR"){
         $tdata =$item->tax_data;
       }else{
         $tdata =json_encode($item->tax_data);
       }
       $section_target_offer=$this->calculateSectionData($item,$trans_from);
       $order_details = new OrderDetails;
       $order_details->transaction_type=$transaction_type;
       $order_details->trans_from=$trans_from;
       $order_details->v_id =$v_id;
       $order_details->store_id=$store_id;
       $order_details->t_order_id=$order->od_id;
       $order_details->order_id=$order->o_id;
       $order_details->user_id=$c_id;
       $order_details->weight_flag=$item->weight_flag==true?'1':'0';
       $order_details->barcode=$item->barcode;
       $order_details->item_name=$item->item_name;
       $order_details->item_id=$item->barcode;
       $order_details->qty=$item->qty;
       $order_details->subtotal=$item->subtotal;
       $order_details->unit_mrp=$item->unit_mrp;
       $order_details->unit_csp=$item->unit_rsp;
       $order_details->discount=$item->discount;
       $order_details->batch_id=$item->batch_id;
       $order_details->serial_id=$item->serial_id;
       $order_details->manual_discount=$item->md_discount;
       $order_details->tax=$item->tax;
       $order_details->pdata=json_encode($pdata);
       $order_details->tdata=$tdata;
       $order_details->section_target_offers=json_encode($section_target_offer);
       $order_details->status='success';
       $order_details->total=$item->total;
       // $order_details->department_id=$department_id;
       $order_details->vu_id=$vu_id;
       $order_details->date =$date;
       $order_details->time=$time;
       $order_details->month=$month;
       $order_details->year=$year;
       $order_details->channel_id='2';
       $order_details->created_at = $date_time;
       $order_details->save();

       foreach ($pdata as $itemdata) { 
          //dd($itemdata);               
        $OrderItemDetails =  new OrderItemDetails;
        $OrderItemDetails->porder_id =$order_details->id;
        $OrderItemDetails->qty= $itemdata['qty'];
        $OrderItemDetails->barcode = $itemdata['item_id'];
        $OrderItemDetails->mrp =$itemdata['mrp'];
        $OrderItemDetails->price =$itemdata['mrp'];
        $OrderItemDetails->discount =$itemdata['discount']; 
        $OrderItemDetails->ext_price =$itemdata['mrp'];
        $OrderItemDetails->channel_id='2';
        $OrderItemDetails->created_at = $date_time;
        $OrderItemDetails->save();
      }                          
    }   

  }
  $total_discounts   = (float)$order->discount+(float)$order->lpdiscount+(float)$order->bill_buster_discount+(float)$order->manual_discount;
  $discountDetails = ['total_discount'=>$total_discounts,'discount'=>$order->discount,'manual_discount'=>$order->manual_discount,'coupon_discount'=>$order->coupon_discount,'bill_buster_discount'=>$order->bill_buster_discount];
  $custom_invoice_id = custom_invoice_id_generate(['store_id' => $store_id, 'user_id' => $c_id, 'trans_from' => $trans_from]);
  $invoice = new Invoice;
  $invoice->invoice_id    = $invoice_id;
  $invoice->custom_order_id   = $custom_invoice_id;
  $invoice->ref_order_id    = $order->order_id;
  $invoice->transaction_type  = $transaction_type;
  $invoice->store_gstin   =     $order->store_gstin;
  $invoice->store_gstin_state_id  = $order->store_gstin_state_id;
  $invoice->v_id         = $v_id;
  $invoice->store_id      = $store_id;
  $invoice->user_id       = $c_id;
          //$invoice->invoice_sequence = $inc_id;
  $invoice->qty         = $order->qty;
  $invoice->subtotal      = $order->subtotal;
  $invoice->discount      = $order->discount;
  $invoice->lpdiscount    = $order->lpdiscount;
  $invoice->coupon_discount   = $order->coupon_discount;
  $invoice->bill_buster_discount= $order->bill_buster_discount; 
  $invoice->remark            = $order->remark;
  if(isset($order->manual_discount)) {
    $invoice->manual_discount = $order->manual_discount;
  }
  $invoice->tax         = $order->tax;
  $invoice->total       = $order->total;
  $invoice->trans_from    = $trans_from;
  $invoice->vu_id       = $vu_id;
  $invoice->date        = $date;
  $invoice->time        = $time;
  $invoice->month       = $month;
  $invoice->year        = $year;
  $invoice->financial_year = getFinancialYear();
  $invoice->discount_amount   = $total_discounts;
  $invoice->session_id      = $session_id;
  $invoice->store_short_code  = $short_code;
  $invoice->terminal_name     = isset($terminalInfo)?$terminalInfo->name:'';
  $invoice->terminal_id     = isset($terminalInfo)?$terminalInfo->id:'';
  $invoice->round_off       = '';
  $invoice->customer_first_name     = isset($userDetail->first_name)?$userDetail->first_name:'';
  $invoice->customer_last_name     = isset($userDetail->last_name)?$userDetail->last_name:'';
  $invoice->customer_number     = isset($userDetail->mobile)?$userDetail->mobile:'';
  $invoice->customer_email     = isset($userDetail->email)?$userDetail->email:'';
  $invoice->customer_gender     = isset($userDetail->gender)?$userDetail->gender:'';
  $invoice->customer_address  = isset($userDetail->address)?$userDetail->address->address1:'';
  $invoice->customer_pincode  = isset($userDetail->address)?$userDetail->address->pincode:'';
  /*if customer phone code exists then update else manually update the default country code +91*/
  $invoice->customer_phone_code  = isset($userDetail->customer_phone_code)?$userDetail->customer_phone_code:'+91';
  $invoice->channel_id  = '2';

  $invoice->created_at = $date_time;
  $invoice->save();

  $order_data = OrderDetails::where('t_order_id', $order->od_id)->get()->toArray();        

  foreach ($order_data as $value) {
    if ($invoice->id) {
      /*Tax Detail Update Header Level*/
                // $tdata   = json_decode($value->tdata);
                // if($tdata->tax_name == '' || $tdata->tax_name == 'Exempted'){
                //   $tdata->tax_name = 'GST 0%';
                // }else{
                //   if(strpos($tdata->tax_name, 'GST') === false) $tdata->tax_name = 'GST '.$tdata->tax_name;
                // }
                // $taxDetails[$tdata->tax_name][] = $tdata->tax;

      /*Tax Detail Update Header Level End */

      $value['t_order_id']  = $invoice->id;
      $value['channel_id']  = '2';
      $save_invoice_details = $value;
      $invoice_details_data = InvoiceDetails::create($save_invoice_details);
      $order_details_data  = OrderItemDetails::where('porder_id', $value['id'])->get()->toArray();
      $params = array('v_id' => $invoice->v_id, 'store_id' => $invoice->store_id, 'barcode' => $value['barcode'], 'qty' => $value['qty'], 'invoice_id' => $invoice->invoice_id,'transaction_scr_id'=>$invoice->id, 'order_id' => $invoice->ref_order_id,'transaction_type'=>'SALE','vu_id'=>$invoice->vu_id,'trans_from'=>$invoice->trans_from);
      $cartconfig = new CartconfigController;
      $cartconfig->updateStockQty($params);
      foreach ($order_details_data as $indvalue) {
        $save_invoice_item_details = array_add($indvalue, 'pinvoice_id', $invoice_details_data->id);
        InvoiceItemDetails::create($save_invoice_item_details);
      }
    }         

  }
  $pydata = count($payment_data);
  if($pydata>1){
   $payment_type='partial';
 }else{
   $payment_type='full';
 }
    // dd($pydata);
 foreach ($payment_data as $payments) {

  $payment = new Payment;

  $payment->store_id = $store_id;
  $payment->v_id = $v_id;
  $payment->order_id = $order->order_id;
  $payment->user_id = $c_id;
        // $payment->pay_id = $pay_id;
  $payment->amount = $payments->amount;
  $payment->method = $payments->type->name;
        // $payment->session_id =$session_id;
  $payment->terminal_id =$terminalInfo->id;
  $payment->cash_collected = $payments->type->cash_collected;
  $payment->cash_return = $payments->type->balance_refund;
  $payment->invoice_id = $invoice_id;
        // $payment->bank = $bank;
        // $payment->wallet = $wallet;
        // $payment->vpa = $vpa;
        // $payment->error_description = $error_description;
  $payment->status = 'success';
  $payment->payment_type = $payment_type;
        // $payment->payment_gateway_type = $payment_gateway_type;
        // $payment->payment_gateway_device_type = $payment_gateway_device_type;
        // $payment->gateway_response = json_encode($gateway_response);
  $payment->date = $date;
  $payment->time = $time;
  $payment->month =$month;
  $payment->year = $year;
  $payment->channel_id = '2';
  $payment->created_at = $date_time;
  $payment->save();

}
DB::commit();
return response()->json(['status' => 'success','message' => 'Invoice sync successfully'], 200);
}catch(Exception $e){  
  DB::rollBack();
  return response()->json(['status' => 'fail', 'message' =>'Some error has occurred Plz try again'],200);
}
}
}  
public function calculatePata($item){

  // dd($item);

  $pdata = [];
    //dd($item);
  if($item->weight_flag==true){
    $data = [];
    $subTotal = round(($item->qty * $item->unit_rsp),3);
    $data[] =   [ 'item_id' => $item->barcode, 'qty' =>$item->qty, 'mrp' =>$item->unit_mrp,'unit_mrp' =>$item->unit_mrp,'unit_rsp'=>$item->unit_rsp,'discount' =>0, 'sub_total' => $subTotal, 'total' => $subTotal, 'message' =>'', 'discount_price_basis' => '' ];
    $pdata = array_merge($pdata,$data);

  }else{
   $offerpdata = collect($pdata);
   $pdataItemQty =$offerpdata->where('item_id',$item->barcode)->sum('qty');
   $qty=$item->qty;
   $remainQty=$qty-$pdataItemQty;
   while ($remainQty>0) {
    $data = [];
    $subTotal = 1 * $item->unit_rsp;
    $data[] =   [ 'item_id' => $item->barcode, 'qty' =>1, 'mrp' =>$item->unit_mrp,'unit_mrp' =>$item->unit_mrp,'unit_rsp'=>$item->unit_rsp,'discount' =>0, 'sub_total' => $subTotal, 'total' => $subTotal, 'message' =>'', 'discount_price_basis' => '' ];
    $pdata = array_merge($pdata , $data);
    $remainQty--;
  } 

}
return $pdata;
}

public function calculateSectionData($item,$trans_from) {

    if($trans_from=="ANDROID_VENDOR"){
          $multiple_price =json_decode($item->multiple_price);
       }else{
         $multiple_price = $item->multiple_price;
       }

  $section_target_offer = [];
  $getBarcode = VendorSkuDetailBarcode::where('barcode', $item->barcode)->where('v_id', $item->v_id)->first();
  $getItem = VendorSku::where([ 'v_id' => $item->v_id, 'vendor_sku_detail_id' => $getBarcode->vendor_sku_detail_id ])->first();
  $offer_data =  ['applied_offers'=>[],
                  'available_offers'=>[]
                 ];                 
  $section_target_offer = ['p_id'=>$getItem->item_id,'category' => $getItem->cat_name_1, 'brand_name' => '', 'sub_categroy' => '', 'offer' =>'NO', 'offer_data'=>$offer_data, 'multiple_price_flag' => $item->weight_flag, 'multiple_mrp' =>$multiple_price, 'unit_mrp' => $item->unit_mrp, 'uom' => $item->uom];

  return $section_target_offer;
}


public function isInvoiceIdExit(Request $request){

  $type = $request->type;
  $store_id = $request->store_id;
  $v_id    = $request->v_id;
  $vu_id   = $request->vu_id;
  $invoice_id    = $request->invoice_no;
  $trans_from        =$request->trans_from;
  $udidtoken         = $request->udidtoken;
  $invoice       = Invoice::where('invoice_id',$invoice_id)->first();
 if($invoice){ 
   return response()->json(['status'=> 'invoice_id_exist', 'message' =>'Invoice with this id already exists'],200); 
   }else{
    return response()->json(['status'=> 'invoice_id_not_exist', 'message' =>'Invoice Id does not exist'],200);
   }
}

}