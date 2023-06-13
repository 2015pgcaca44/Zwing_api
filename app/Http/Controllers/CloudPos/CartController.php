<?php

namespace App\Http\Controllers\CloudPos;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VendorSettingController;
use App\Http\Controllers\CloudPos\CartconfigController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\OrderController;
use App\Http\CustomClasses\PrintInvoice;
use App\Http\CustomClasses\PrintJsonInvoice;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Mail\OrderCreated;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\CloudPos\AccountsaleController;
use App\Http\Controllers\CloudPos\DebitnoteprintController;
use App\Http\Controllers\CloudPos\CreditnoteprintController;
use Barryvdh\DomPDF\Facade as PDF;
use App\Store;
use App\Order;
use App\Terms;
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
use App\Model\Payment\Mop;
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
use App\Model\Items\VendorSku;
use App\Model\Items\VendorSkuDetailBarcode;
use App\Model\Items\VendorItem;
use App\Model\Stock\StockCurrentStatus;
use App\Model\Stock\StockPointSummary;
use App\Model\Stock\StockTransactions;
use App\Model\Stock\StockLogs;
use App\Model\Stock\StockPoints;
use App\Model\Stock\Serial;
use App\Model\Stock\SerialSold;
use App\CartDiscount;
use App\Organisation;
use App\SettlementSession;
use App\CashRegister;
use App\DepRfdTrans;

use App\Model\Tax\TaxRate;
use App\Model\Tax\TaxGroupPresetDetails;
use App\Http\Controllers\Einvoice\EinvoiceController;
//gv table
use App\Model\GiftVoucher\GiftVoucherCartDetails;
use App\Model\GiftVoucher\GiftVoucherOrder;
use App\Model\GiftVoucher\GiftVoucherOrderDetails;
use App\Model\GiftVoucher\GiftVoucherPayments;
use App\Model\GiftVoucher\GiftVoucherInvoices;
use App\Model\GiftVoucher\GiftVoucherInvoiceDetails;
use App\Model\Grn\GrnList;

class CartController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth' , ['except' => ['order_receipt','rt_log','taxCalApi','inboundTaxCalculation','testTax'] ]);
        $this->cartconfig  = new CartconfigController;     
    }

    private function store_db_name($store_id)
    {    
        if($store_id){
            $store      = Store::find($store_id);
            $store_name = $store->store_db_name;
            return $store_name;
        }else{
            return false;
        }
    } 

    public function add_to_cart(Request $request)
    {
        // dd($request->all());
        $v_id       = $request->v_id;
        $c_id       = $request->c_id;
        $store_id   = $request->store_id;
        $vu_id      = $request->vu_id;
        //$product_id = $request->product_id;
        $barcode    = $request->barcode;
        // $sku_code   = $request->sku_code;
        $batch_id   = !empty($request->batch_id)?$request->batch_id:0;
        $serial_id  = !empty($request->serial_id)?$request->serial_id:0;
        $change_mrp = !empty($request->change_mrp)?$request->change_mrp:'';
        //$barcode  = $request->ogbarcode;
        $qty        = $request->qty;
        $unit_mrp   = $request->unit_mrp;
        $unit_rsp   = $request->unit_rsp;
        $r_price    = $request->r_price;
        $s_price    = $request->s_price;
        $net_amount = $s_price;
        $extra_charge = 0;
        $charge_details = [];
        
        if($request->has('net_amount')){
            $net_amount    = $request->net_amount;
        }

        if($request->has('extra_charge')){
            $extra_charge    = $request->extra_charge;
        }

        if($request->has('charge_details')){
            $charge_details    = $request->charge_details;
        }

        $discount   = $request->discount;
        //$pdata    = urldecode($request->pdata);
        //$spdata   = urldecode($request->pdata);
        $all_data   = json_encode($request->data);
        //$request->request->add(['override_flag' => '1']);

        $target_offer = null;
        if($request->has('target_offer')){
            $target_offer = urldecode($request->target_offer); //This is target pdata
        }
        $product_response = urldecode($request->data['item_det']);
        $product_response = json_decode($product_response);
        $mrp_arrs   = $request->mrp_arrs;
        $multiple_mrp_flag = $request->multiple_mrp_flag;

        //$product_response = urldecode($request->data['item_det']);
        //$product_response = json_decode($product_response);
        $single_cart_data = [];
        //$pdata            = json_decode($pdata);
        $taxs             = [];
        $trans_from       = $request->trans_from;
        $total_tax        = 0;

        // if(empty($pdata)){
        ////echo 'indisde this ';exit;
        //     $total = $unit_mrp * $qty;
        //     $final_data['pdata'][] = [ 'qty' => $qty, 'mrp' => $unit_mrp,'r_price'=>$r_price,'s_price'=>$s_price, 'discount' => 0, 'ex_price' => $total, 'total_price' => $total, 'message' => '' , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0,'tax'=>$taxs];
        //     $final_data['available_offer']  = [];
        //     $final_data['applied_offer']    = [];
        //     $final_data['item_id']          = $barcode;
        //     $final_data['r_price']          = $r_price* $qty;
        //     $final_data['s_price']          = $s_price* $qty;
        //     $final_data['total_tax']        = $total_tax;
        //     $final_data['multiple_price_flag'] =  $multiple_mrp_flag;
        //     $final_data['multiple_mrp']     = $mrp_arrs;
        //     $pdata   = json_encode($final_data);
        //     $pdataD  = json_decode($pdata);
        // }else{
        //     $pdataD = $pdata;
        // }


        
        $plu_flag = false;
        $plu_barcode = 0;

        $item_master = null;
        $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $barcode)->first();
        $item_master = null;
        if($bar){

            $item_master = VendorSku::select('vendor_sku_detail_id','item_id','sku_code','name','hsn_code','has_batch','variant_combi','tax_type')->where(['vendor_sku_detail_id'=> $bar->vendor_sku_detail_id,'v_id'=>$v_id, 'deleted_at' => null])->first();
            $item_master->barcode = $bar->barcode;
        
        }
        if(!$item_master){
            $item_master = VendorSku::select('vendor_sku_detail_id','item_id','sku_code','name','hsn_code','has_batch','variant_combi','tax_type')->where(['sku'=> $barcode,'v_id'=>$v_id, 'deleted_at' => null])->first();

            $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('vendor_sku_detail_id', $item_master->vendor_sku_detail_id)->first();
            $item_master->barcode = $bar->barcode;
        }

        $sku_code = $item_master->sku_code;


        /*Tax Calculation*/
        $from_gstin = Store::select('gst')->where('store_id', $store_id)->first()->gst;
        $to_gstin = null;
        $invoice_type= 'B2C';
        if($request->has('cust_gstin') && $request->cust_gstin != ''){
            $invoice_type= 'B2B';
            $to_gstin = $request->cust_gstin;
        }
        $params = array('barcode'=>$barcode, 'sku_code' => $sku_code, 'qty'=>$qty,'s_price'=>$net_amount,'hsn_code'=>$item_master->hsn_code,'store_id'=>$store_id,'v_id'=>$v_id , 'from_gstin' => $from_gstin , 'to_gstin' => $to_gstin , 'invoice_type' => $invoice_type );
        // dd($params);
        $tax_details = $this->taxCal($params);

        //$tax_details = 0;
        /*Tax Calculation end*/

        // $order_id    = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
        $order_id    = $order_id + 1;

        Cart::where('sku_code', '!=', $sku_code)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('order_id', '!=',  $order_id)->where('status', 'process')->delete();

        $cart_list = Cart::where('sku_code', '!=', $sku_code)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('order_id', $order_id)->where('status', 'process')->get();

        $subtotal  = $net_amount*$qty;
        $taxpayble = 0;
        $tax_type = null;
        if(isset($item_master->tax_type) ){
            $tax_type = $item_master->tax_type;
        }else{
            $tax_type = $item_master->Item->tax_type;
        }
        if($tax_type == 'EXC'){
            $taxpayble = format_number($tax_details['tax'], 2);
            $net_amount   = $net_amount+$taxpayble;
            $r_price   = $net_amount-$taxpayble;
            $total     = $net_amount*$qty-$discount;
        }else{
            $total          = (($net_amount*$qty-$discount)+$taxpayble)+$extra_charge;
        }

        //echo $r_price;die;

        if($request->has('trans_type') && in_array($request->trans_type, ['exchange','return','order'])){
            $trans_type = $request->trans_type == 'order' ? 'sales' : $request->trans_type;
        }else{
            $trans_type = 'sales';
        }

        $cart           = new Cart;
        $cart->transaction_type = $trans_type;
        $cart->store_id = $store_id;
        $cart->v_id     = $v_id;
        $cart->order_id = $order_id;
        $cart->user_id  = $c_id;
        if($plu_flag){
            $cart->plu_barcode = $plu_barcode;  
        }
        
        $cart->barcode  = $barcode;
        $cart->sku_code  = $sku_code;
        $cart->qty      = (string)$qty;
        $cart->item_id  = $item_master->barcode;
        $cart->batch_id = $batch_id;
        $cart->serial_id= $serial_id;

        $cart->item_name= $this->cartconfig->getItemName($item_master->name,$item_master->variant_combi);
                    //$item_master->Item->name.' ('.$item_master->variant_combi.')';
        $cart->unit_mrp = $unit_mrp;
        $cart->unit_csp = $unit_rsp;
        //'unit_csp' => $unit_rsp,
        $cart->subtotal = (string)$r_price;
        $cart->net_amount    = (string)$net_amount;
        $cart->extra_charge   = (string)$extra_charge;
        $cart->charge_details = json_encode($charge_details);
        $cart->total    = (string)$s_price;
        $cart->discount = $discount;

        $cart->status   = 'process';
        $cart->trans_from = $trans_from;
        $cart->vu_id    = $vu_id;
        $cart->date     = date('Y-m-d');
        $cart->time     = date('h:i:s');
        $cart->month    = date('m');
        $cart->year     = date('Y');
        $cart->tax      = format_number($tax_details['tax'], 2);

        //This condition is added for tagging salesman for exhchange
        if ($request->has('exchange_against_invoice_id') && $request->exchange_against_invoice_id > 0) {

            $invoice = Invoice::select('salesman_id')->where('id',$request->exchange_against_invoice_id)->first();
            if($invoice){
                $invoice_details = InvoiceDetails::select('salesman_id')->where('t_order_id', $invoice->id)->where('barcode', $request->barcode)->first();
                if($invoice_details){
                    $cart->salesman_id = $invoice->salesman_id;
                }else{
                    return response()->json(['status'=> 'fail' , 'message' => 'Unable to find Exchanged Items' ] );
                }
                
            }else{
                return response()->json(['status'=> 'fail' , 'message' => 'Unable to find Exchange Invoice ID' ] );
            }
        }
    //$cart->pdata    = $spdata;
    if($request->has('is_catalog')){
        $cart->is_catalog = $request->is_catalog;
    }
    if($request->has('weight_flag')){
        $cart->weight_flag = (string)$request->weight_flag;
    }
    $cart->tdata    = json_encode($tax_details);
    if($request->has('target_offer')){
        $cart->target_offer = $target_offer;
    }
    if($request->has('override_flag') && $request->override_flag == "1"){
        $cart->override_unit_price = $unit_mrp;
        $cart->override_reason = "";
        $cart->override_flag = $request->override_flag;
        $cart->override_by = $vu_id;
    }
    $cart->section_target_offers = $all_data;
    $cart->department_id = $product_response->DEPARTMENT_CODE;
    $cart->group_id = $product_response->SECTION_CODE;
    $cart->division_id = $product_response->DIVISION_CODE;
    $cart->subclass_id = $product_response->ARTICLE_CODE;
    $cart->printclass_id = isset($request->get_assortment_count)?$request->get_assortment_count:0 ;
        //$cart->target_offer = (isset($data->target))?json_encode($data->target):'';
        //$cart->section_offers = (isset($data->section_offer))?json_encode($data->section_offer):'';
        //$cart->subclass_id = $item_master->ID_MRHRC_GP_SUBCLASS;
        //$cart->printclass_id = $item_master->ID_MRHRC_GP_PRNT_CLASS;
        //$cart->group_id = $item_master->ID_MRHRC_GP_PRNT_GROUP;
        //$cart->division_id = $item_master->ID_MRHRC_GP_PRNT_DIVISION;
    $cart->save();
   

    // $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id  , 'exclude_barcode' => $barcode ];


    // $cartD  = array('barcode'=>$barcode,'cart_id'=>$cart->cart_id,'pdata'=>$pdataD);
    // $this->addCartDetail($cartD);
    //$offerD = array('cart_id'=>$cart->cart_id,'item_id'=>$barcode,'mrp'=>$unit_mrp,'qty'=>$qty,'offers'=>$pdata);
    $params = ['trans_type'=>$trans_type,'v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id , 'exclude_sku_code' => $sku_code,'batch_id' => $batch_id,'serial_id'=>$serial_id,'change_mrp'=>$change_mrp , 'request' => $request ];
        //$this->process_each_item_in_cart($params);

    $carts = DB::table('cart')->where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();

    $total_amount = format_number($carts->sum('total'));

    // dd($total_amount);
    
    if($request->has('cust_gstin') && $request->cust_gstin !=''){
        $params['cust_gstin'] = $request->cust_gstin;
    }
    if($request->has('override_flag') && $request->override_flag !=''){
        $params['override_flag'] = $request->override_flag;
    }
    if($request->promo_cal){
        $this->process_each_item_in_cart($params);
    }

    $cart_sum = Cart::where('v_id', $v_id)->where('store_id', $store_id)->where('order_id',$order_id)->where('weight_flag','0')->where('user_id', $c_id)->sum('qty');

    $cart_count = Cart::where('v_id', $v_id)->where('store_id', $store_id)->where('order_id',$order_id)->where('weight_flag','1')->where('user_id', $c_id)->count('qty');

    $cart_qty = $cart_sum + $cart_count;

    $cart_qty = $cart_sum + $cart_count;

    ///// total item limit /////////
    $vendorS = new VendorSettingController;
    $role = VendorRoleUserMapping::select('role_id')->where('user_id', $vu_id)->first();
    $role_id = $role->role_id;
    $trans_from = $request->trans_from;
    $sParams = ['v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $vu_id, 'role_id' => $role_id, 'trans_from' => $trans_from];
    $getProductSetting = $vendorS->getProductSetting($sParams);
    if($cart_qty > $getProductSetting->max_item_in_cart->options[0]->no_of_items->value){
        return response()->json(['status'=> 'fail' , 'message' => 'Item limit is over' ] );
    }


    if($v_id == 24 || $v_id == 11){

        $request->request->add(['get_data_of' => 'SINGLE_ITEM']);
        return $this->cart_details($request);
    }

    if ($request->has('get_data_of')) {
        if ($request->get_data_of == 'CART_DETAILS') {
            return $this->cart_details($request);
        } else if ($request->get_data_of == 'CATALOG_DETAILS') {
            $catalogC = new CatalogController;
            return $catalogC->getCatalog($request);
        }
    }
    $data = ['status' => 'add_to_cart', 'message' => 'Product was successfully added to your cart.', 'total_qty' => $cart_qty, 'total_amount' => $total_amount,];
    if($cart->override_flag == '1'){
        $data['price_override'] = '1';
    }
    return response()->json($data, 200);


    }

    private function reCalculateTax($params)
    {   
        //dd($params);
         foreach ($params['items'] as $key => $value) {
            $sprice  = $value->total;
            if( $value->net_amount > 0 ){
                $sprice  = $value->net_amount;
            }

            if($value->tax_type == 'EXC'){
              $sprice = $value->total-$value->tax;
            }

            $itemTax = $this->taxCal([
                'barcode'   => $value->item_id,
                'qty'       => $value->qty,
                's_price'   => $sprice,
                'hsn_code'  => $value->hsn,
                'store_id'  => $params['store_id'],
                'v_id'      => $params['v_id'],
                'from_gstin'=> isset($params['from_gstin'])?$params['from_gstin']:'',
                'to_gstin'  => isset($params['to_gstin'])?$params['to_gstin']:'',
                'invoice_type'  => isset($params['invoice_type'])?$params['invoice_type']:'B2C',
            ]);

            //dd($itemTax);

            //print_r($itemTax);

            $total = $value->total;
            //echo $itemTax['tax_type'];die;

            /*if($itemTax['tax_type'] == 'EXC'){
                $total = $value->total+$itemTax['tax'];
            } */

            Cart::find($value->cart_id)->update([ 'bill_buster_discount' => (string)$value->bill_discount, 'total' => (string)$total, 'tax' => $itemTax['tax'], 'tdata' => json_encode($itemTax) ]);
        }
    }

    private function addCartDetail($params)
    {
        //echo $params['cart_id'];
        $cart_id  = $params['cart_id'];
        $barcode  = $params['barcode'];
        $pdata    = $params['pdata'] ;
        $pdata    = isset($pdata->pdata) > 0? $pdata->pdata : $pdata;
        //print_r($pdata);die;
        //die;
        CartDetails::where('cart_id',$cart_id)->delete();
        if(count($pdata) > 0 ) {

            foreach ($pdata as $item) {

                if (empty($item->promo_code)) { $is_promo = 0;}
                else {$is_promo = 1;}
                $cartdetail  = new CartDetails();
                $cartdetail->cart_id = $cart_id;
                $cartdetail->barcode = $barcode;
                $cartdetail->qty     = $item->qty;
                $cartdetail->mrp     = $item->mrp;
                $cartdetail->discount= $item->discount;
                $cartdetail->ext_price = $item->total;
                //$cartdetail->price     = $item->unit_rsp;
                //$cartdetail->ext_price = $item->ex_price;
                $cartdetail->price   = $item->mrp;
                $cartdetail->ru_prdv = (isset($item->slab_code)) ? $item->slab_code : '';
                $cartdetail->promo_id= (isset($item->promo_code)) ? $item->promo_code : '';
                $cartdetail->is_promo= (isset($is_promo)) ? $is_promo : '';
                $cartdetail->message = $item->message;
                $cartdetail->save();
            }
        } else {
            $cart = Cart::find($cart_id);
            CartDetails::create([ 'cart_id' => $cart_id, 'barcode' => $barcode, 'qty' => $cart->qty, 'mrp' => $cart->unit_mrp, 'price' => $cart->unit_csp, 'discount' => $cart->discount, 'ext_price' => $cart->total, 'is_promo' => 0 ]);
        }
    }//End of addCartDetail

    public function update_to_cart($values) 
    {
       // echo 'hello';
        // dd($values);

        $start_time = microtime(true); 
        $v_id       = $values->v_id;
        $c_id       = $values->c_id;
        $store_id   = $values->store_id;
        //$product_id = $values->product_id;
        $barcode    = $values->barcode;
        $batch_id   = !empty($values->batch_id)?$values->batch_id:0;
        $serial_id  = !empty($values->serial_id)?$values->serial_id:0;
        $change_mrp = !empty($values->change_mrp)?$values->change_mrp:'';
        $qty        = $values->qty; 
        $unit_mrp   = $values->unit_mrp;
        $unit_rsp   = $values->unit_rsp;
        $r_price    = $values->r_price;
        $s_price    = $values->s_price;
        $net_amount = $s_price;
        $extra_charge = 0;
        $charge_details = [];
        
        if(isset($values->net_amount)){
            $net_amount    = $values->net_amount;
        }

        if(isset($values->extra_charge)){
            $extra_charge    = $values->extra_charge;
        }

        if(isset($values->charge_details)){
            $charge_details    = $values->charge_details;
        }

        $discount = $values->discount;
        //$pdata = urldecode($values->pdata);
        $target_offer = urldecode($values->target_offer);
        //$spdata = urldecode($values->pdata);
        $all_data = json_encode($values->data);
        $product_response = urldecode($values->data['item_det']);
        $product_response = json_decode($product_response);
        //$pdata = json_decode($pdata);
        //$product_response = urldecode($values->data['item_det']);
        //$product_response = json_decode($product_response);
        // $product_response = json_decode($product_response);
        $taxs       = [];
        $trans_type = 'sales';
        if(isset($values->trans_type) ) {
            if($values->trans_type == 'order') {
                $trans_type = 'sales';
            } else {
                $trans_type = $values->trans_type;
            }

        }

        // if(empty($pdata)){
        //     //echo 'indisde this ';exit;
        //     //$total = $unit_mrp * $qty;
        //     $final_data['pdata'][] = [ 'qty' => $qty, 'mrp' => $unit_mrp,'r_price'=>$r_price,'s_price'=> $s_price, 'discount' => 0, 'ex_price' => $s_price, 'total_price' => $s_price, 'message' => '' , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0,'tax'=>$taxs];
        //     $final_data['available_offer']  = [];
        //     $final_data['applied_offer']    = [];
        //     $final_data['item_id']          = $barcode;
        //     $final_data['r_price']          = $r_price;
        //     $final_data['s_price']          = $s_price;
        //     $final_data['total_tax']        = isset($total_tax)?$total_tax:0;
        //     $final_data['multiple_price_flag'] =  isset($multiple_mrp_flag)?$multiple_mrp_flag:false;
        //     $final_data['multiple_mrp']     = isset($mrp_arrs)?$mrp_arrs:[$r_price];

        //     $pdata = json_encode($final_data);
        //     $pdataD = json_decode($pdata);
        // }
        
        $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $barcode)->first();
        $item_master = null;
        if($bar){
          $item_master = VendorSku::select('vendor_sku_detail_id','item_id','name','hsn_code','has_batch','variant_combi','tax_type')->where(['vendor_sku_detail_id'=> $bar->vendor_sku_detail_id,'v_id'=>$v_id, 'deleted_at' => null])->first();
          $item_master->barcode = $bar->barcode;
        }
        if(!$item_master){
            $item_master = VendorSku::select('vendor_sku_detail_id','item_id','name','hsn_code','has_batch','variant_combi','tax_type')->where(['sku'=> $barcode,'v_id'=>$v_id, 'deleted_at' => null])->first();
            $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('vendor_sku_detail_id', $item_master->vendor_sku_detail_id)->first();
            $item_master->barcode = $bar->barcode;
        }
        
        // dd($item_master);
        /*Tax Calculation*/
        $from_gstin = Store::select('gst')->where('store_id', $store_id)->first()->gst;
        $to_gstin = null;
        $invoice_type= 'B2C';
        if(isset($values->cust_gstin) && $values->cust_gstin != ''){
            $invoice_type= 'B2B';
            $to_gstin = $values->cust_gstin;
        }
        $params      = array('barcode'=>$barcode,'qty'=>$qty,'s_price'=>$net_amount,'hsn_code'=>$item_master->hsn_code,'store_id'=>$store_id,'v_id'=>$v_id ,'from_gstin' => $from_gstin , 'to_gstin' => $to_gstin ,'invoice_type' => $invoice_type);
        $tax_details = $this->taxCal($params);
        // dd($tax_details);


        $subtotal  = $net_amount*$qty;
        $taxpayble = 0;
        $tax_type = null;
        if(isset($item_master->tax_type) ){
            $tax_type = $item_master->tax_type;
        }else{
            $tax_type = $item_master->Item->tax_type;
        }
        if($tax_type == 'EXC'){
            $taxpayble = format_number($tax_details['tax'], 2);
            $net_amount   = $net_amount+$taxpayble;
            //$r_price   = $net_amount-$taxpayble;
            $total     = $net_amount;
        }else{
            $total     = ($net_amount)+$taxpayble + $extra_charge;
        }


       // echo $s_price;die;
        //Commented this because in case of discount total is caluation in minus
        // $total     = ($s_price*$qty-$discount)+$taxpayble;
        //$total     = ($s_price)+$taxpayble;

        //$tax_details = 0;
        /*Tax Calculation end*/


        // dd($pdata);

        // $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
        $order_id = $order_id + 1;
      
        //DB::enableQueryLog();

        $cart_id = Cart::where('transaction_type', $trans_type)->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('status', 'process')->where('item_id', $barcode);
        if($batch_id > 0){
            $cart_id  = $cart_id->where('batch_id',$batch_id);
        }
        if($serial_id > 0){
            $cart_id  = $cart_id->where('serial_id',$serial_id);
        }
        if(!isset($values->override_flag)){
            if(!empty($change_mrp) && $change_mrp >0){
                $cart_id  = $cart_id->where('unit_mrp', format_number($change_mrp));
            }
        }else{
            if($values->override_flag == 1 && !empty($values->actual_unit_mrp)){
                $cart_id  = $cart_id->where('unit_mrp', format_number($values->actual_unit_mrp));
            }
        }

        // if(item_level_manual_discount)
        //dd($cart_id->get());
        //dd(DB::getQueryLog());
        $cartData = [
            'store_id'           => $store_id,
            'transaction_type'   => $trans_type,
            'v_id'               => $v_id,
            'order_id'           => $order_id,
            'user_id'            => $c_id,
            'barcode'            => $barcode,
            'item_id'            => $barcode,
            'batch_id'           => $batch_id,
            'serial_id'          => $serial_id,
            'qty'                => (string)$qty,
            'unit_mrp'           => $unit_mrp,
            'unit_csp'           => $unit_rsp,
            'subtotal'           => $r_price,
            'net_amount'         => (string)$net_amount,
            'extra_charge'       => (string)$extra_charge,
            'charge_details'     =>  json_encode($charge_details),
            'total'              => (string)$total,
            'trans_from'         => $values->trans_from,
            'vu_id'              => $values->vu_id,
            'discount'           => $discount,
            'status'             => 'process',
            'date'               => date('Y-m-d'),
            'time'               => date('H:i:s'),
            'month'              => date('m'),
            'year'               => date('Y'),
            'tax'                => format_number($tax_details['tax'], 2),
            //'pdata'             => $spdata,
            'tdata'             => json_encode($tax_details),
            'target_offer'      => $target_offer,
            'section_target_offers' => $all_data,
            'weight_flag'       => (string)$values->weight_flag,
            'printclass_id'    => isset($values->data['get_assortment_count'])?$values->data['get_assortment_count']:0
        ];
        if(isset($values->override_flag) && $values->override_flag == '1'){
                $cartData['override_unit_price'] = $unit_mrp;
                $cartData['override_reason'] = "";
                $cartData['override_flag'] = $values->override_flag;
                $cartData['override_by'] = $values->vu_id;
        }

        $cart_id->update($cartData);
        
         // dd($cart_id);
        $cartGet =  Cart::where('transaction_type', $trans_type)->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('status', 'process')->where('item_id', $barcode);
        if($batch_id > 0){
            $cartGet  = $cartGet->where('batch_id',$batch_id);
        }
        if($serial_id > 0){
            $cartGet  = $cartGet->where('serial_id',$serial_id);
        }

        if(!empty($change_mrp) && $change_mrp >0){
            $cartGet  = $cartGet->where('unit_mrp',format_number($change_mrp));
        }
        $cartGet = $cartGet->first();

        // dd($cartGet);
        //$cartD  = array( 'barcode' => $barcode , 'cart_id' => $cartGet->cart_id, 'pdata' => $pdata);
        //$this->addCartDetail($cartD);

        $carts = DB::table('cart')->where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();

        //echo (' Time is milli sec: '.(microtime(true) - $start_time));exit;
        $params = ['trans_type'=>$trans_type,'v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id  , 'exclude_barcode' => $barcode , 'call_process_to_each' => false,'batch_id'=>$batch_id,'serial_id'=>$serial_id,'change_mrp'=>$change_mrp ];

        if(isset($values->request) ){
            $params['request'] = $values->request;
        }
        // dd($params);

        if(isset($values->cust_gstin) && $values->cust_gstin !=''){
            $params['cust_gstin'] = $values->cust_gstin;
        }
        if(isset($values->call_process_to_each) && $values->call_process_to_each == false){

        }else{
             if($values->promo_cal){
                $this->process_each_item_in_cart($params);    
            }    
        }


        //this is Temporary condition need to chage this to add in order by promo,manual
        if(isset($cartGet->item_level_manual_discount) && isset($values->request) ){
          $md = json_decode($cartGet->item_level_manual_discount);
          //dd($md);
          if($md->basis == 'P'){

            $newRequest = new \Illuminate\Http\Request();
            $newRequest->replace([
                'v_id' => $cartGet->v_id,
                'store_id' => $cartGet->store_id,
                'trans_from' => $cartGet->trans_from,
                'cart_id' => $cartGet->cart_id,
                'trans_from' => $cartGet->trans_from,
                'terminal_id' => $values->request->terminal_id,
                'api_token' => $values->request->api_token,
                'vu_id' => $values->request->vu_id,
                'c_id' => $values->request->c_id,
                'customer_group_code' => $values->request->customer_group_code,
                'udidtoken' => $values->request->udidtoken,
                'session_id' => $values->request->session_id,
                'manual_discount_factor'=> $md->factor,
                'manual_discount_basis' => $md->basis,
                'remove_discount'       => 0,
                'return'                => 0,
                'is_applicable'         => 0,
                'applicable_level'      => 'ITEM_LEVEL'

            ]);

            // dd($newRequest);
        
            $offerconfig = new \App\Http\Controllers\OfferController;
            $check = $offerconfig->manualDiscount($newRequest);

          }else{

            $ctotal = $cartGet->subtotal - $md->discount;
            // dd($ctotal);

            $params      = array('barcode'=>$cartGet->barcode,'qty'=>$cartGet->qty,'s_price'=>$ctotal,'hsn_code'=>$item_master->hsn_code,'store_id'=>$store_id,'v_id'=>$v_id ,'from_gstin' => $from_gstin , 'to_gstin' => $to_gstin ,'invoice_type' => $invoice_type);
            $tax_details = $this->taxCal($params);
            // dd($tax_details);

            $total = 0;
            $subtotal  = $ctotal*$cartGet->qty;
            $taxpayble = 0;
            $tax_type = null;
            if(isset($item_master->tax_type) ){
                $tax_type = $item_master->tax_type;
            }else{
                $tax_type = $item_master->Item->tax_type;
            }
            if($tax_type == 'EXC'){
                $taxpayble = format_number($tax_details['tax'], 2);
                $ctotal   = $ctotal+$taxpayble;
                //$r_price   = $ctotal-$taxpayble;
                $total     = $ctotal;
                // dd($total);
            }else{
                $total     = ($ctotal)+$taxpayble;
            }

            // dd($total);
            // dd($tax_details);
            $cartGet->update([
                'tax' => format_number($tax_details['tax'], 2),
                'tdata' => json_encode($tax_details),
                'total' =>  format_number($total,2)
                ]);

            // dd($cartGet);
          }
         
        }
        $carts = DB::table('cart')->where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();
        //MEMO LEVEL Promotions
        // $carts = DB::table('cart')->where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();

        // $memoPromotions = null;
        // $offerParams['carts'] = $carts;
        // $offerParams['store_id'] = $store_id;
        // $offerParams['v_id'] = $v_id;
        // $memoPromo = new PromotionController;
        // $memoPromotions = $memoPromo->memoIndex($offerParams);
        // //dd($memoPromotions);
        // //dd($barcode);        
        // if (!empty($memoPromotions)) {
        //     $mParams['store_id'] = $store_id;
        //     $mParams['v_id'] = $v_id;
        //     $memoPromotions = collect($memoPromotions);
        //     // dd($memoPromotions);
        //     if(!empty($memoPromotions->where('item_id', $barcode)->count())) {
        //         // dd('What up');
        //         $mParams['items'] = $memoPromotions->values();
        //         $this->reCalculateTax($mParams);
        //         foreach($memoPromotions as $promos){
        //            $mParams['items']= [ $promos ];
        //             DB::table('cart_offers')->where('cart_id' , $promos->cart_id)->delete();
        //             $offerD = array('cart_id'=>$promos->cart_id,'item_id'=>$promos->item_id,'mrp'=>$promos->unit_mrp,'qty'=>$promos->qty,'offers'=> json_encode($mParams['items']));
        //             CartOffers::create($offerD);
        //         }
        //     }
        //     // $carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();
        // } else {
        //     $mParams['store_id'] = $store_id;
        //     $mParams['v_id'] = $v_id;
        //     $itemData = [];
        //     foreach ($carts as $mCart) {
        //         $itemDet = json_decode($mCart->section_target_offers);
        //         $itemDet = urldecode($itemDet->item_det);
        //         $itemDet = json_decode($itemDet);
        //         $itemData[] = (object)[ 'item_id' => $mCart->item_id, 'qty' => $mCart->qty, 'total' => $mCart->total, 'hsn' => $itemDet->hsn_code, 'cart_id' => $mCart->cart_id, 'discount' => 0 ];
        //     }
        //     $mParams['items'] = $itemData;
        //     $this->reCalculateTax($mParams);
        // }


        // DB::table('cart_offers')->where('cart_id' , $cartGet->cart_id)->delete();
        // $offerD = array('cart_id'=>$cartGet->cart_id,'item_id'=>$cartGet->barcode,'mrp'=>$cartGet->unit_mrp,'qty'=>$cartGet->qty,'offers'=>$spdata);
        // CartOffers::create($offerD);


        
        // $carts = DB::table('cart')->where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();

        $cart_sum = Cart::where('v_id', $v_id)->where('store_id', $store_id)->where('order_id',$order_id)->where('weight_flag','0')->where('user_id', $c_id)->where('transaction_type',$trans_type)->sum('qty');

        $cart_count = Cart::where('v_id', $v_id)->where('store_id', $store_id)->where('order_id',$order_id)->where('weight_flag','1')->where('user_id', $c_id)->where('transaction_type',$trans_type)->count('qty');

        $cart_qty = $cart_sum + $cart_count;

        ///// total item limit /////////
        $vendorS = new VendorSettingController;
        $role = VendorRoleUserMapping::select('role_id')->where('user_id', $values->vu_id)->first();
        $role_id = $role->role_id;
        $trans_from = $values->trans_from;
        $sParams = ['v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $values->vu_id, 'role_id' => $role_id, 'trans_from' => $trans_from];
        $getProductSetting = $vendorS->getProductSetting($sParams);
        if($cart_qty > $getProductSetting->max_item_in_cart->options[0]->no_of_items->value){
            return response()->json(['status'=> 'fail' , 'message' => 'Item limit is over' ] );
        }

        if($v_id == 24 || $v_id == 11 ){
            
            $request = new \Illuminate\Http\Request();
            $request->replace(['v_id' => $values->v_id , 'store_id' => $values->store_id, 'c_id' => $values->c_id, 'vu_id' => $values->vu_id, 'barcode' => $values->barcode ,'trans_from' => $values->trans_from ,'get_data_of' => 'SINGLE_ITEM']);
            // if(isset( $values->vu_id) && $values->vu_id > 0){
            //     $request->request->add(['vu_id' => $values->vu_id ] );
            // }
            return $this->cart_details($request);
        }

        if (isset($values->get_data_of)) {
            $request = new \Illuminate\Http\Request();
            $request->replace(['v_id' => $values->v_id , 'store_id' => $values->store_id, 'c_id' => $values->c_id  , 'trans_from' => $values->trans_from ]);
            if(isset( $values->vu_id) && $values->vu_id > 0){
                $request->replace(['vu_id' => $values->vu_id ] );
            }

            if ($values->get_data_of == 'CART_DETAILS') {
                return $this->cart_details($request);
            } else if ($values->get_data_of == 'CATALOG_DETAILS') {
                $catalogC = new CatalogController;
                return $catalogC->getCatalog($request);
            }
        }

        return response()->json(['status' => 'product_qty_update', 'message' => 'Product quantity successfully Updated',
            //, 'data' => $cart
            'total_qty' => $cart_qty, 'total_amount' => $carts->sum('total'),
        ], 200);
    }

    public function calculatePromotions(Request $request){



        $params = ['v_id' => $request->v_id, 
            'store_id' => $request->store_id,
            'c_id' => $request->c_id,
            'request' => $request
        ];

        $role = VendorRoleUserMapping::select('role_id')->where('user_id',$request->vu_id)->first();
        $role_id  = $role->role_id;

        $vendorS = new VendorSettingController;
        $sParams = ['v_id' => $request->v_id,'store_id'=>$request->store_id,'user_id'=>$request->vu_id,'role_id'=>$role_id,'trans_from' => $request->trans_from];
        $promotionS = $vendorS->getPromotionSetting($sParams);

        if($promotionS->status ==0 ){
            return response()->json(['status' => 'fail' , 'message' => 'Promotion is disabled'],200);
        }

        if($request->has('cust_gstin') && $request->cust_gstin!='' && $request->cust_gstin!='0'){
            $params['cust_gstin'] = $request->cust_gstin;
        }
        $this->process_each_item_in_cart($params);

        return $this->cart_details($request);
    }

    public function process_each_item_in_cart($params){

        // dd($params);
        $v_id       = $params['v_id'];
        $store_id   = $params['store_id'];
        $c_id       = $params['c_id'];
        $batch_id   = 0;
        $serial_id  = 0;
        // $change_mrp = null;
        $change_mrp = !empty($params['change_mrp'])?format_number($params['change_mrp']):null;
        if(!isset($params['trans_type'])){
            $trans_type = 'sales';
        }else{
            $trans_type = $params['trans_type'];
        }
        //$stores        = DB::table('stores')->select('name', 'mapping_store_id' ,'store_db_name')->where('store_id', $store_id)->first();
        //$store_db_name = $stores->store_db_name;
        $db_structure = DB::table('vendor')->select('db_structure')->where('id',$v_id)->first()->db_structure;

        // $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
        $order_id = $order_id + 1;

        #######Comment this beacuse need to calculate Target Offer #######
        // DB::enableQueryLog();
        // if(isset($params['exclude_barcode'])){
        //     $cart_list = Cart::where('item_id', '!=', $params['exclude_barcode'] )->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('order_id', $order_id)->where('status', 'process');
        // }else{
        if( $v_id == 24 || $v_id == 11 ){
            $cart_list = Cart::where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('order_id', $order_id)->where('status', 'process')->orderBy('printclass_id');
        }else{

            $cart_list = Cart::where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('order_id', $order_id)->where('status', 'process')->orderByDesc('target_offer');
        }


        // }

        if(isset($params['target_sku_codes']) && count($params['target_sku_codes'])> 1 ){
            $cart_list = $cart_list->whereIn('sku_code', $params['target_sku_codes']);
        }
        // else if(!empty($change_mrp) && $change_mrp >0){
        //     $cart_list = $cart_list->where('unit_mrp',$change_mrp);
        // }

        
        $cart_list = $cart_list->get();
        // dd(DB::getQueryLog($cart_list));
        
        // dd($cart_list->pluck('barcode'));
        $promoC = new PromotionController;
        $promoC->setter(['v_id' => $v_id, 'store_id' => $store_id ,'db_structure' => '2']);
        $all_store_promo = $promoC->getAllPromotions(['mapping_store_id' => $store_id]);


        foreach ($cart_list as $key => $cart) {
            // dd($cart);
            //Added inside because for GUy and GEt promotion wanted updated target offer data
            $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();
            $single_cart_data['v_id'] = $v_id;
            $single_cart_data['is_cart'] = 1;
            $single_cart_data['is_update'] = 0;
            $single_cart_data['store_id'] = $store_id;
            $single_cart_data['c_id'] = $c_id;
            $single_cart_data['trans_from'] = $cart->trans_from;
            $single_cart_data['barcode'] = $cart->barcode;
            $single_cart_data['sku_code'] = $cart->sku_code;
            $single_cart_data['qty'] = $cart->qty;
            $single_cart_data['vu_id'] = $cart->vu_id;
            $single_cart_data['mapping_store_id'] = $store_id;
            $single_cart_data['batch_id'] = $cart->batch_id;
            $single_cart_data['serial_id'] = $cart->serial_id;
            $single_cart_data['change_mrp'] = $cart->unit_mrp;
            $single_cart_data['item_level_manual_discount'] = $cart->item_level_manual_discount;

            $batch_id   = $cart->batch_id;
            $serial_id  = $cart->serial_id;
            $change_mrp =$cart->unit_mrp;
                
            $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $cart->barcode)->first();
            // dd($cart->toArray());
            $item = null;
            if($bar){
                $item  =  VendorSku::select('vendor_sku_flat_table.vendor_sku_detail_id','vendor_sku_flat_table.sku','vendor_sku_flat_table.sku_code','vendor_sku_flat_table.variant_combi','vendor_sku_flat_table.item_id','vendor_sku_flat_table.brand_id','vendor_sku_flat_table.department_id','vendor_sku_flat_table.deleted_at' ,'vendor_sku_flat_table.hsn_code','vendor_sku_flat_table.tax_type', 'vendor_sku_flat_table.tax_group_id' ,'vendor_sku_flat_table.is_active')
                ->leftJoin('stock_point_summary', 'stock_point_summary.sku_code', 'vendor_sku_flat_table.sku_code')
                ->where(['vendor_sku_flat_table.v_id' => $v_id , 'vendor_sku_flat_table.vendor_sku_detail_id' => $bar->vendor_sku_detail_id 
                    // ,'stock_point_summary.stop_billing' => '0','stock_point_summary.store_id'=>$store_id
                    ]
                 )
                ->first();

                $item->barcode = $bar->barcode;

            }
            $productC = new ProductController;

            // $item = $productC->getItemDetailsForPromo(['item' => $item, 'vu_id'=>$cart->vu_id, 'v_id' => $v_id , 'unit_mrp' => $cart->unit_mrp,'store_id'=>$store_id]);
            //Sanjeev
            $productC = new ProductController;
            $itemData = ['item' => $item, 'v_id' => $v_id ,'vu_id'=>$cart->vu_id, 'unit_mrp' => $cart->unit_mrp,'store_id'=>$store_id,'batch_id'=>$batch_id,'serial_id'=>$serial_id,'change_mrp'=>$change_mrp];
            if(isset($params['override_flag']) && $params['override_flag'] == '1'){
                $itemData['override_flag'] = $params['override_flag'];
            }
            $item = $productC->getItemDetailsForPromo($itemData);
            $price = $item['price'];
            $item = $item['item'];
            $single_cart_data['item'] = $item;
            $single_cart_data['store_db_name'] ='';
            $single_cart_data['db_structure'] = $db_structure;

            $single_cart_data['carts'] = $carts;
            $single_cart_data['all_store_promo'] = $all_store_promo;
            $single_cart_data['promo_cal'] = true;
            $single_cart_data['tax_type'] = true;


            //dd($single_cart_data);
            $promoC = new PromotionController;
            $offer_data = $promoC->index($single_cart_data);

            $responseData = $offer_data;
            // dd($responseData);
            // Filter Item Details

            $itemDetCart = urldecode($responseData['item_det']);
            $itemDetCart = json_decode($itemDetCart);
            $itemDetCart = collect($itemDetCart)->forget(['store_id','for_date','opening_qty','out_qty','int_qty','v_id','created_at','updated_at','stop_billing','deleted_at','variant_combi','sku','qty','tax_group_id','is_active']);
            

            $responseData['item_det'] = urlencode(json_encode($itemDetCart));
            $responseData['pdata'] = [];
            //dd($offer_data);
            //
            $call_process_to_each = false;
            if(isset($params['call_process_to_each'])){
                $call_process_to_each = $params['call_process_to_each'];
            }

            // $data = (object)[ 'v_id' => $single_cart_data['v_id'], 'store_id' => $single_cart_data['store_id'], 'c_id' => $single_cart_data['c_id'], 'barcode' => $offer_data['barcode'], 'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'data' => $responseData, 'trans_from' => $single_cart_data['trans_from'], 'vu_id' => $single_cart_data['vu_id'], 'weight_flag' => $offer_data['weight_flag'] , 'target_offer' => $offer_data['target_offer'], 'call_process_to_each' => $call_process_to_each, 'get_assortment_count' => $cart->printclass_id ];

            $data = (object)[ 'trans_type'=>$trans_type,'v_id' => $single_cart_data['v_id'], 'store_id' => $single_cart_data['store_id'], 'c_id' => $single_cart_data['c_id'], 'barcode' => $offer_data['barcode'], 'sku_code' => $single_cart_data['sku_code'], 'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'data' => $responseData, 'trans_from' => $single_cart_data['trans_from'], 'vu_id' => $single_cart_data['vu_id'], 'weight_flag' => $offer_data['weight_flag'] , 'target_offer' => $offer_data['target_offer'], 'call_process_to_each' => $call_process_to_each, 'get_assortment_count' => $cart->printclass_id,'batch_id'=>$single_cart_data['batch_id'],'serial_id'=>$single_cart_data['serial_id'],'change_mrp'=> $single_cart_data['change_mrp'],'item_level_manual_discount'=>$single_cart_data['item_level_manual_discount'] , 'extra_charge' => $offer_data['extra_charge'] , 'net_amount' => $offer_data['net_amount'], 'charge_details' => $offer_data['charge_details']];
            
            $single_cart_data['batch_id'] = $batch_id;
            $single_cart_data['serial_id'] = $serial_id;
            $single_cart_data['change_mrp'] = $cart->unit_mrp;

            if(isset($params['request'])){
                $data->request = $params['request'];
            }
                // dd($data);
                // $cart = new CartController;
            $this->update_to_cart($data);

        }

        //Order by desc added for targer_offer to calculate target offer properly
        $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->orderByDesc('target_offer')->get();

        if($v_id == 23  || $v_id == 24 || $v_id == 11 ){

        }else{
            //This condition is added to recalculate target item promotion
            if( !isset($params['target_offer_called'])){
                $t_pdata = [];
                foreach($carts as $cart){
                    // dd($cart);
                    $t_pdata = array_merge($t_pdata , json_decode($cart->target_offer));
                }
                $t_sku_code = array_unique( collect($t_pdata)->pluck('sku_code')->all() );
                // dd($t_pdata);
                if(count($t_sku_code) >=1){
                    $para = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id , 'target_offer_called' => true , 'target_sku_codes' => $t_sku_code,'batch_id'=>$batch_id,'serial_id'=>$serial_id,'change_mrp'=>$change_mrp ];
                    if(isset($params['cust_gstin']) && $params['cust_gstin'] !='' ){
                        $para['cust_gstin'] = $params['cust_gstin'];
                    }
                    if(isset($params['request'])){
                        $para['request'] = $params['request'];
                    }

                    $this->process_each_item_in_cart($para);
                }
            }

        }
        

        //MEMO LEVEL Promotions
        $carts = DB::table('cart')->where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();

        $memoPromotions = null;
        $offerParams['carts'] = $carts;
        $offerParams['store_id'] = $store_id;
        $offerParams['v_id'] = $v_id;
        $offerParams['promo_cal'] = true;
        $memoPromo = new PromotionController;
        $memoPromotions = $memoPromo->memoIndex($offerParams);

        if($c_id == 7963){
            // dd($memoPromotions);
        }
        //dd($memoPromotions);
        //dd($barcode);        
        if (!empty($memoPromotions)) {
            $mParams['store_id'] = $store_id;
            $mParams['v_id'] = $v_id;
            $memoPromotions = collect($memoPromotions);
            // dd($memoPromotions);
            //if(!empty($memoPromotions->where('item_id', $barcode)->count())) {
                // dd('What up');
            $mParams['items'] = $memoPromotions->values();
            $from_gstin = Store::select('gst')->where('store_id', $store_id)->first()->gst;
            $to_gstin = null;
            $invoice_type= 'B2C';
            if(isset($params['cust_gstin']) && $params['cust_gstin'] != ''){
                $invoice_type= 'B2B';
                $to_gstin = $params['cust_gstin'];
            }
            $mParams['from_gstin'] = $from_gstin;
            $mParams['to_gstin'] = $to_gstin;
            $mParams['invoice_type'] = $invoice_type;
            foreach ($mParams['items'] as $iteKey => $ite) {

                $mCart = $carts->where('cart_id', $ite->cart_id)->first();
                
                $itemDet = json_decode($mCart->section_target_offers);
                $tdata   = json_decode($mCart->tdata);
                $itemDet = urldecode($itemDet->item_det);
                
                $itemDet = json_decode($itemDet);

                $mParams['items'][$iteKey]->hsn = $itemDet->hsn_code;
                $mParams['items'][$iteKey]->tax_type = $tdata->tax_type;
                $mParams['items'][$iteKey]->tax = $mCart->tax;
                $mParams['items'][$iteKey]->net_amount = $mCart->net_amount;
            }
            $this->reCalculateTax($mParams);
            foreach($memoPromotions as $promos){
               $mParams['items']= [ $promos ];
               DB::table('cart_offers')->where('cart_id' , $promos->cart_id)->delete();
               $offerD = array('cart_id'=>$promos->cart_id,'item_id'=>$promos->item_id,'mrp'=>$promos->unit_mrp,'qty'=>$promos->qty,'offers'=> json_encode($mParams['items']));
               CartOffers::create($offerD);
           }
            //}
            // $carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();
       } else {
        $mParams['store_id'] = $store_id;
        $mParams['v_id'] = $v_id;
        $itemData = [];
        foreach ($carts as $mCart) {
            $itemDet = json_decode($mCart->section_target_offers);
            $tdata   = json_decode($mCart->tdata);
            $itemDet = urldecode($itemDet->item_det);
            
            $itemDet = json_decode($itemDet);

            $itemData[] = (object)[ 'item_id' => $mCart->item_id, 'qty' => $mCart->qty, 'tax' => $mCart->tax, 'net_amount' => $mCart->net_amount , 'total' => $mCart->total, 'hsn' => $itemDet->hsn_code, 'cart_id' => $mCart->cart_id, 'discount' => 0,'tax_type'=>$tdata->tax_type ,'bill_discount' => 0 ];
        }
        
        $mParams['items'] = $itemData;
        $from_gstin = Store::select('gst')->where('store_id', $store_id)->first()->gst;
        $to_gstin = null;
        $invoice_type= 'B2C';
        if(isset($params['cust_gstin']) && $params['cust_gstin'] != ''){
            $invoice_type= 'B2B';
            $to_gstin = $params['cust_gstin'];
        }
        $mParams['from_gstin'] = $from_gstin;
        $mParams['to_gstin'] = $to_gstin;
        $mParams['invoice_type'] = $invoice_type;

        //dd($mParams);
        $this->reCalculateTax($mParams);
    }

}

public function taxCal($params){
    

    $v_id        = $params['v_id']; 
    if($v_id == 111 || $v_id == 143){
        return $this->taxCalNew($params);
    }
    $data    = array();
    $actualQty         = $params['qty'];
    $qty         = 1;
    $mrp         = $params['s_price'];
    $mrpTotal    = $params['s_price'];
    $store_id    = $params['store_id'];
    $barcode     = $params['barcode'];
    $hsn_code    = $params['hsn_code']; 

    $tax_for     = isset($params['tax_for'])?$params['tax_for']:'';

    // print_r($params);die;
   
    $invoice_type= 'B2C';
    $from_gstin  = ''; 
    $to_gstin    = ''; 
    $igst_flag   = false;


    if(isset($params['invoice_type']) && $params['invoice_type'] !='' &&  $params['invoice_type'] == 'B2B' ){
        $invoice_type =  $params['invoice_type'];
          $from_gstin  = $params['from_gstin']; 
          $to_gstin    = $params['to_gstin']; 
        
        if($params['from_gstin'] =='' || $params['to_gstin'] =='' ){
            return response()->json(['status' => 'fail', 'message' => 'Gstin is Empty'], 200);
        }
        if((strlen($from_gstin) ==15) && (strlen($to_gstin) == 15)){
            if(substr($from_gstin, 0,2) != substr($to_gstin,0,2)){
                $igst_flag = true;
            }
        }else{
            return response()->json(['status' => 'fail', 'message' => 'Gstin is not Valid '], 200);
        }
    }
 



    $cgst_amount = 0;
    $sgst_amount = 0;
    $igst_amount = 0;
    $cess_amount = 0;
    $cgst        = 0;
    $sgst        = 0;
    $igst        = 0;
    $cess        = 0;
    $slab_amount = 0;
    $slab_cgst_amount = 0;
    $slab_sgst_amount = 0;
    $slab_cess_amount = 0;
    $tax_amount       = 0;
    $taxable_amount   = 0;
    $total            = 0;
    $to_amount        = 0;
    $tax_name         = '';
    $tax_type         = isset($params['tax_type'])?$params['tax_type']:'';
    $item_master = '';

    $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $barcode)->first();
            $item = null;
    if($bar){
        $item_master  = VendorSku::select('vendor_sku_detail_id','tax_type','hsn_code','item_id')
            ->where(['vendor_sku_detail_id'=> $bar->vendor_sku_detail_id,'hsn_code'=>$hsn_code,'v_id'=>$v_id,'deleted_at' => null])->with(['tax'=>function($query) use($v_id){
            $query->where('v_id',$v_id);
        }])->first();

    }

    if(!$item_master){
        
        $item_master = VendorSku::select('tax_type','hsn_code','item_id')
        ->where(['sku'=> $barcode,'hsn_code'=>$hsn_code,'v_id'=>$v_id,'deleted_at' => null])->with(['tax'=>function($query) use($v_id){
            $query->where('v_id',$v_id);
        }])->first();

    }
        //echo $item_master->hsn_code;

    if($item_master){
                // echo "<pre>";
                 //echo  $item_master->tax->category->slab;die;
              //  print_r($item_master->tax->group);die;

        $mrp   = $mrp / $qty;

    
        if(isset($item_master->tax->group) ){
           
                    // if($item_master->category->group)
            $tempslab = isset($item_master->tax->category->slab) ? $item_master->tax->category->slab : ' ';
            if($tempslab == 'NO'){
                // print_r($item_master->tax->group);die;
                //$vl = $item_master->tax->where('v_id',$v_id);
                $grouRate = $item_master->tax->group;                               
            }
            if($tempslab == 'YES'){
                //echo $mrp;
                
                $getSlabmrp =  $this->getTaxSlabMrp($mrp,$item_master->tax, $from_gstin, $to_gstin, $invoice_type);
                if($getSlabmrp){
                    $tempmrp = $getSlabmrp;
                }
                //die;
                //$getSlab   = $item_master->tax->slab->where('amount_from','<=',$tempmrp)->where('amount_to','>=',$tempmrp)->first();
                $getSlab   = $item_master->tax->slab->where('amount_from','<=',$mrp)->where('amount_to','>=',$mrp)->first();
                if($getSlab){
                    $grouRate  = $getSlab->ratemap;
                } 
                
            }
                
                /*Start Tax Calculation*/
                if(isset($grouRate) && count($grouRate) > 0){
                    foreach ($grouRate as $key => $value) {
                        if($igst_flag == false){
                            if($value->type == 'CGST'){
                                 $cgst = $value->rate->name;
                                 $cgst_amount = $value->rate->rate;
                            }

                             if($value->type == 'SGST'){
                                 $sgst = $value->rate->name;
                                 $sgst_amount = $value->rate->rate;
                             }

                        }else{

                            if($value->type == 'IGST' && isset($value->rate->rate)){
                                $igst = $value->rate->name;
                                $igst_amount = $value->rate->rate;
                            }

                        }
                      
                    if($value->type == 'CESS'){
                        $cess        = $value->rate->name;
                        $cess_amount = $value->rate->rate;
                    }
                }
            }

                //echo $cgst_amount.' - '.$sgst_amount.' - '.$igst_amount.' - '.$cess_amount;die;
            $tax_type = null;
            if(isset($item_master->tax_type) ){
                $tax_type = $item_master->tax_type;
            }else{
                $tax_type = $item_master->Item->tax_type;
            }

            if($tax_for == 'GRT' || $tax_for == 'SST' || $tax_for == 'GRN' ){
                $tax_type = 'EXC';
            }
            if($qty > 0){
             if($tax_type == 'EXC'){
                    //$mrp  = round($mrp / $qty, 2);

                $slab_cgst_amount = $this->calculatePercentageAmt($cgst_amount,$mrp);
                $slab_sgst_amount = $this->calculatePercentageAmt($sgst_amount,$mrp);
                $slab_cess_amount = $this->calculatePercentageAmt($cess_amount,$mrp);
                $slab_igst_amount = $this->calculatePercentageAmt($igst_amount,$mrp);

                $cgst           = $cgst_amount;
                $sgst           = $sgst_amount;
                $igst           = $igst_amount;
                $cess           = $cess_amount;

                $cgst_amount = $slab_cgst_amount ;
                $sgst_amount = $slab_sgst_amount ;
                $igst_amount = $slab_igst_amount;
                //$cess_amount = $this->formatValue($slab_cess_amount);
                $cess_amount = $slab_cess_amount;

                $tax_amount  = $cgst_amount + $sgst_amount + $igst_amount+$cess_amount;

                //$tax_amount  = $this->formatValue($tax_amount);
                    $taxable_amount = floatval($mrp);// - floatval($tax_amount);
                    //$taxable_amount = $this->formatValue($taxable_amount);
                    $total          = $taxable_amount + $tax_amount;
                    $tax_name       = @$item_master->tax->category->group->name;
                }else{

                    //$mrp  = round($mrp / $qty, 2); 
                    $slab_cgst_amount = $mrp / ( 100 + $cgst_amount + $sgst_amount + $cess_amount ) * $cgst_amount;
                    $slab_sgst_amount = $mrp / ( 100 + $cgst_amount + $sgst_amount + $cess_amount ) * $sgst_amount;
                    $slab_cess_amount = $mrp / ( 100 + $cgst_amount + $sgst_amount + $cess_amount ) * $cess_amount;
                    $slab_igst_amount = $mrp / ( 100 + $igst_amount + $cess_amount ) * $igst_amount;

                    $cgst           = $cgst_amount;
                    $sgst           = $sgst_amount;
                    $igst           = $igst_amount;
                    $cess           = $cess_amount;

                    $cgst_amount = $slab_cgst_amount ;
                    $sgst_amount = $slab_sgst_amount ;
                    $igst_amount = $slab_igst_amount;
                    //$cess_amount = $this->formatValue($slab_cess_amount);
                    $cess_amount = $slab_cess_amount;

                    $tax_amount  = $cgst_amount + $sgst_amount + $igst_amount+$cess_amount;

                    $tax_amount  = $tax_amount;
                    $taxable_amount = floatval($mrp) - floatval($tax_amount);
                    $taxable_amount = $taxable_amount;
                    $total          = $taxable_amount + $tax_amount;
                    if(isset($item_master->tax->category->group)){
                        $tax_name       = $item_master->tax->category->group->name;
                    }else{
                        $tax_name = '';
                    }
                }

            }
            /*End Tax Calculation*/
        }
        if(isset($item_master->tax_type) ){
            $tax_type = $item_master->tax_type;
        }else{
            $tax_type = $item_master->Item->tax_type;
        }
        if($tax_for == 'GRT' || $tax_for == 'SST' || $tax_for == 'GRN' ){
            $tax_type = 'EXC';
        }
    }
    $cgst_amount = $cgst_amount * $qty;
    $cgst_amount = $cgst_amount;
    $sgst_amount = $sgst_amount * $qty;
    $sgst_amount = $sgst_amount;
    $igst_amount = $igst_amount * $qty;
    $igst_amount = $igst_amount;
    $slab_cess_amount = $slab_cess_amount * $qty;
    
    if($tax_type == 'EXC'){
        $total = $total * $qty;
    }else{
        $total = $mrp * $qty;
    }



    if($igst_flag==false){
    $taxable_amount = $total - round($cgst_amount,2) - round($sgst_amount,2) - round($slab_cess_amount,2); 
    $tax_amount = $total - $taxable_amount;
    $taxdisplay = $cgst+$sgst;
    }else{
    $taxable_amount = $total - round($igst_amount,2) - round($slab_cess_amount,2); 
    $tax_amount = $total - $taxable_amount;
    $taxdisplay = $igst;
    }

    $data = [
        'barcode'   => $barcode,
        'hsn'       => $hsn_code,
        'qty'       => $actualQty,
        'cgst'      => $cgst,
        'sgst'      => $sgst,
        'igst'      => $igst,
        'cess'      => $cess,
        'cgstamt'   => (string)round($cgst_amount,2),
        'sgstamt'   => (string)round($sgst_amount,2),
        'igstamt'   => (string)round($igst_amount,2),
        'cessamt'   => (string)round($slab_cess_amount,2),
        'netamt'    => $mrp,  //$mrp * $qty,
        'taxable'   => (string)round($taxable_amount,2),
        'tax'       => (string)round($tax_amount,2),
        'total'     => $total, //$total * $qty,
        'tax_name'  => 'GST '.$taxdisplay.'%',//$tax_name,
        'tax_type'  => $tax_type
        ];  
     //  dd($data);
        return $data;    


}//End of taxCal

    private function getTaxSlabMrp($mrp,$gettax, $from_gstin ='', $to_gstin='', $invoice_type='B2C'){
                /*Tax calculate with slab: Only taxable value compare with slab from - To
                Start ---
                */                  
                $cgst_amount = 0;
                $sgst_amount = 0;
                $igst_amount = 0;
                $cess_amount = 0;
                $cgst        = 0;
                $sgst        = 0;
                $igst        = 0;
                $cess        = 0;
                $igst_flag   = false;


                if(isset($invoice_type) && $invoice_type !='' &&  $invoice_type == 'B2B' ){

                    $from_gstin  = $from_gstin; //$params['from_gstin']; 
                    $to_gstin    = $to_gstin;  //$params['to_gstin']; 
                    
                    if($from_gstin =='' || $to_gstin =='' ){
                        return response()->json(['status' => 'fail', 'message' => 'Gstin is Empty'], 200);
                    }

                    if((strlen($from_gstin) ==15) && (strlen($to_gstin) == 15)){
                        if(substr($from_gstin, 0,2) != substr($to_gstin,0,2)){
                            $igst_flag = true;
                        }
                    }else{
                        return response()->json(['status' => 'fail', 'message' => 'Gstin is not Valid '], 200);
                    }
                }

                $getSlabs  = $gettax->slab->sortBy('amount_from');
                $keyD=0;
                foreach ($getSlabs as $key => $slab) {
                    if ($mrp >= $slab->amount_from   && $mrp < $slab->amount_to  ) {
                        if($key == 0){
                            $keyD = 0;
                        }else{
                            $keyD = $key-1;
                        }
                    }

                }
                $grouFirstRate = $getSlabs[$keyD]->ratemap;
                if(isset($grouFirstRate) && count($grouFirstRate) > 0){
                    foreach ($grouFirstRate as $key => $value) {
                        
                        if($igst_flag == false){
                            if($value->type == 'CGST'){
                                 $cgst = $value->rate->name;
                                 $cgst_amount = $value->rate->rate;
                            }

                             if($value->type == 'SGST'){
                                 $sgst = $value->rate->name;
                                 $sgst_amount = $value->rate->rate;
                             }

                        }else{

                            if($value->type == 'IGST' && isset($value->rate->rate)){
                                $igst = $value->rate->name;
                                $igst_amount = $value->rate->rate;
                            }

                        }

                        if($value->type == 'CESS'){
                        $cess        = $value->rate->name;
                        $cess_amount = $value->rate->rate;
                        }
                    }
                }
                $slab_cgst_amount = $mrp / ( 100 + $cgst_amount + $sgst_amount + $cess_amount ) * $cgst_amount;
                $slab_sgst_amount = $mrp / ( 100 + $cgst_amount + $sgst_amount + $cess_amount ) * $sgst_amount;
                $slab_cess_amount = $mrp / ( 100 + $cgst_amount + $sgst_amount + $cess_amount ) * $cess_amount;
                $slab_igst_amount = $mrp / ( 100 + $igst_amount + $cess_amount ) * $igst_amount;
                $taxamount = $slab_cgst_amount+$slab_sgst_amount+$slab_cess_amount;
                $mrp = $mrp-$taxamount;
                return $mrp;

                /*End ------*/

    }//End of getTaxSlabMrp


    private function getTaxSlabMrpNew($mrp,$gettax, $from_gstin ='', $to_gstin='', $invoice_type='B2C'){
                /*Tax calculate with slab: Only taxable value compare with slab from - To
                Start ---
                */                  
                $cgst_amount = 0;
                $sgst_amount = 0;
                $igst_amount = 0;
                $cess_amount = 0;
                $cgst        = 0;
                $sgst        = 0;
                $igst        = 0;
                $cess        = 0;
                $igst_flag   = false;


                if(isset($invoice_type) && $invoice_type !='' &&  $invoice_type == 'B2B' ){

                    $from_gstin  = $from_gstin; //$params['from_gstin']; 
                    $to_gstin    = $to_gstin;  //$params['to_gstin']; 
                    
                    if($from_gstin =='' || $to_gstin =='' ){
                        return response()->json(['status' => 'fail', 'message' => 'Gstin is Empty'], 200);
                    }

                    if((strlen($from_gstin) ==15) && (strlen($to_gstin) == 15)){
                        if(substr($from_gstin, 0,2) != substr($to_gstin,0,2)){
                            $igst_flag = true;
                        }
                    }else{
                        return response()->json(['status' => 'fail', 'message' => 'Gstin is not Valid '], 200);
                    }
                }

                $getSlabs  = $gettax->slabs->sortBy('amount_from');
                $keyD=0;
                foreach ($getSlabs as $key => $slab) {
                    if ($mrp >= $slab->amount_from   && $mrp < $slab->amount_to  ) {
                        if($key == 0){
                            $keyD = 0;
                        }else{
                            $keyD = $key-1;
                        }
                    }
                }
                $grouFirstRate = $getSlabs[$keyD]->ratemap;
                if(isset($grouFirstRate) && count($grouFirstRate) > 0){
                    foreach ($grouFirstRate as $key => $value) {
                        
                        if($igst_flag == false){
                            if($value->type == 'CGST'){
                                 $cgst = $value->rate->name;
                                 $cgst_amount = $value->rate->rate;
                            }
                            if($value->type == 'SGST'){
                                 $sgst = $value->rate->name;
                                 $sgst_amount = $value->rate->rate;
                            }
                        }else{
                            if($value->type == 'IGST' && isset($value->rate->rate)){
                                $igst = $value->rate->name;
                                $igst_amount = $value->rate->rate;
                            }

                        }

                        if($value->type == 'CESS'){
                        $cess        = $value->rate->name;
                        $cess_amount = $value->rate->rate;
                        }
                    }
                }
                $slab_cgst_amount = $mrp / ( 100 + $cgst_amount + $sgst_amount + $cess_amount ) * $cgst_amount;
                $slab_sgst_amount = $mrp / ( 100 + $cgst_amount + $sgst_amount + $cess_amount ) * $sgst_amount;
                $slab_cess_amount = $mrp / ( 100 + $cgst_amount + $sgst_amount + $cess_amount ) * $cess_amount;
                $slab_igst_amount = $mrp / ( 100 + $igst_amount + $cess_amount ) * $igst_amount;
                $taxamount = $slab_cgst_amount+$slab_sgst_amount+$slab_cess_amount;
                $mrp = $mrp-$taxamount;
                return $mrp;

                /*End ------*/

    }//End of getTaxSlabMrp


    private function calculatePercentageAmt($percentage,$amount){
        if(isset($percentage)  && isset($amount)){
            $result = ($percentage / 100) * $amount;
            return $result;
        }
    }

    public function formatValue($value)
    {
        if (is_float($value) && $value != '0.00') {
            $tax = explode(".", $value);
            if (count($tax) == 1) {
                $strlen = 1;
            } else {
                $strlen = strlen($tax[1]);
            }
            if ($strlen == 2 || $strlen == 1) {
                return (float)$value;
            } else {
                $strlen = $strlen - 2;
                return (float)substr($value, 0, -$strlen);
            }
        } else {
            return $value;
        }
    }


    public function apply_employee_discount(Request $request){

        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;

        $employee_code = $request->employee_code;
        $company_name = $request->company_name;

        $params = [ 'employee_code' => $employee_code , 'company_name'=> $company_name ] ;

        $employDis = new EmployeeDiscountController;
        $employee_details = $employDis->get_details($params);

        $store_db_name = get_store_db_name(['store_id' => $store_id]);
        //dd($employee_details);
        if($employee_details){
            $employee = DB::table($v_id.'_employee_details')->where('employee_id', $employee_details->Employee_ID)->first();
            
            if($employee){

             DB::table($v_id.'_employee_details')->update(['available_discount' => $employee_details->Available_Discount_Amount]);

         }else{
            DB::table($v_id.'_employee_details')->insert([
                'employee_id' => $employee_details->Employee_ID,
                'first_name'  => $employee_details->First_Name,
                'last_name'  => $employee_details->Last_Name,
                'designation'  => $employee_details->Designation,
                'location'  => $employee_details->Location,
                'company_name'  => $employee_details->Comp_Name,
                'available_discount' => $employee_details->Available_Discount_Amount
            ]);
        }

        $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id , 'employee_available_discount' => $employee_details->Available_Discount_Amount , 'employee_id' => $employee_details->Employee_ID , 'company_name' => $company_name , 'request' => 'request' ];

        if($request->has('cust_gstin') && $request->cust_gstin !=''){
            $params['cust_gstin'] = $request->cust_gstin;
        }

        return $this->process_each_item_in_cart($params);



    }else{
        return response()->json(['status' => 'fail', 'message' => 'Unable to find the employee'], 200);
    }

}


public function remove_employee_discount(Request $request){

    $v_id = $request->v_id;
    $c_id = $request->c_id;
    $store_id = $request->store_id;

    // $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
    $order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
    $order_id = $order_id + 1;

    $cart = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->update(['employee_id' => '' , 'employee_discount' => 0.00]);


    $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id , 'request' => $request   ];
    if($request->has('cust_gstin') && $request->cust_gstin !=''){
        $params['cust_gstin'] = $request->cust_gstin;
    }

    $this->process_each_item_in_cart($params);

    return response()->json(['status' => 'success', 'message' => 'Removed Successfully' ]);

}

public function product_qty_update(Request $request)
{
    $v_id       = $request->v_id;
    $c_id       = $request->c_id;
    $store_id   = $request->store_id;
    $vu_id      = $request->vu_id;
    $trans_from = $request->trans_from;
    $batch_id   = !empty($request->batch_id)?$request->batch_id:0;
    $serial_id   = !empty($request->serial_id)?$request->serial_id:0;
    if($request->has('ogbarcode')){
        $barcode = $request->ogbarcode;
    }else{
        $barcode = $request->barcode;
    }

    $cust_gstin = '';
    if($request->has('cust_gstin')){
        $cust_gstin = $request->cust_gstin;
    }

    if($request->has('unit_csp')){
        $unit_csp = $request->unit_csp;
    }
    
    $qty        = $request->qty;
    $unit_mrp   = $request->unit_mrp;
    $change_mrp = !empty($request->change_mrp)?$request->change_mrp:$unit_mrp;

    //$change_mrp = round($change_mrp,2);

    //echo $change_mrp;

        //$unit_rsp   = $request->unit_rsp;
    $r_price    = $request->r_price;
    $s_price    = (float)$request->s_price*(int)$qty;
    $net_amount = $s_price;
    $extra_charge = 0;
    $charge_details = [];
    
    if($request->has('net_amount')){
        $net_amount    = $request->net_amount;
    }

    if($request->has('extra_charge')){
        $extra_charge    = $request->extra_charge;
    }

    if($request->has('charge_details')){
        $charge_details    = $request->charge_details;
    }

    $discount   = $request->discount;
    //$pdata      = $request->pdata;
    $stores         = Store::select('name', 'mapping_store_id' ,'store_db_name')->where('store_id', $store_id)->first();
    $store_name     = $stores->name;
    $store_db_name  = $stores->store_db_name;

        $promo_cal = false;
    
        // if($v_id == 24 || $v_id == 11 || $v_id == 76){
            
    $role = VendorRoleUserMapping::select('role_id')->where('user_id',$vu_id)->first();
    $role_id  = $role->role_id;

    if($request->has('trans_type') && !empty($request->trans_type) && in_array($request->trans_type, ['return','order'])){
        $trans_type = $request->trans_type;
    }else{
        $trans_type = 'sales';
    }
    

    $promo_cal = false;

    if($request->has('promo_cal')){
        $promo_cal = $request->promo_cal;
    }else{

        $vendorS = new VendorSettingController;
        $sParams = ['v_id' => $v_id,'store_id'=>$store_id,'user_id'=>$vu_id,'role_id'=>$role_id,'trans_from' => $trans_from];
        $promotionS = $vendorS->getPromotionSetting($sParams);

        if($promotionS->status ==0 || $promotionS->options[0]->promo_apply_type->value == 'manual'){
       
        }else{
            $promo_cal = true;
        }

    }
        // if( $v_id ==81){
        //         $promo_cal = false;
        // }
        
        
    /*Get Price List */
    $sku_code = null;
    $item = null;
    $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','sku_code','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $barcode)->first();
    if($bar){

        $item  =  VendorSku::select('vendor_sku_flat_table.vendor_sku_detail_id','vendor_sku_flat_table.sku','vendor_sku_flat_table.sku_code','vendor_sku_flat_table.variant_combi','vendor_sku_flat_table.item_id','vendor_sku_flat_table.brand_id','vendor_sku_flat_table.department_id','vendor_sku_flat_table.deleted_at' ,'vendor_sku_flat_table.hsn_code','vendor_sku_flat_table.tax_type', 'vendor_sku_flat_table.tax_group_id' ,'vendor_sku_flat_table.is_active')
            ->where(['vendor_sku_flat_table.v_id' => $v_id , 'vendor_sku_flat_table.vendor_sku_detail_id' => $bar->vendor_sku_detail_id ])
            ->first();
        $item->barcode = $bar->barcode;
        $sku_code = $bar->sku_code;
    }else{
        return response()->json(['status' => 'product_not_found', 'message' => 'Product Not Found'], 404);
    }

    $barcodefrom = $item->barcode;


            // $mrplist    = array();
            // foreach($priceList as $mp){
            //     $mrplist[] = array('mrp'=>$mp->priceDetail->mrp,'rsp'=>$mp->priceDetail->rsp,'s_price'=>$mp->priceDetail->special_price);
            // }
            // $mrplist  =  collect($mrplist);
            // $unit_mrp =  $mrplist->max('mrp'); 
            // $r_price  =  $mrplist->max('rsp')  ;
            // $s_price  =  !empty($mrplist->max('s_price'))?$mrplist->max('s_price'):$mrplist->max('mrp');
            // $s_price  = $s_price*$qty;
            // $data     = '';
            // $mrp_arrs = [];

            // //$price_master->variantPrices
            // foreach ($priceList as $price) {
            //     $mrp_arrs[] =  format_number($price->priceDetail->mrp);
            // }
            // $multiple_mrp_flag  = ( count( $mrp_arrs) > 1 )? true:false;


        ## Price Calculation End

    $promoC = new PromotionController;
    $db_structure = DB::table('vendor')->select('db_structure')->where('id',$v_id)->first()->db_structure;

    $productC = new ProductController;

    $item = $productC->getItemDetailsForPromo(['item' => $item, 'v_id' => $v_id,'vu_id' => $vu_id , 'unit_mrp' => $unit_mrp,'store_id'=>$store_id,'change_mrp'=>$change_mrp,'batch_id'=>$batch_id,'serial_id'=>$serial_id]);
    $price = $item['price'];
    $mrp_arrs          = $price['mrp_arrs'];
    $multiple_mrp_flag = $price['multiple_mrp_flag'];
    $item = $item['item'];


        //dd($item->toArray());

    // $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
    $order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
    $order_id = $order_id + 1;

   /* $check_product_exists = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('barcode', $barcode)->where('status', 'process')->where('unit_mrp',$change_mrp)->first();*/

    $check_product_exists = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('sku_code', $sku_code)->where('status', 'process');
    if($batch_id > 0){
     $check_product_exists  = $check_product_exists->where('batch_id',$batch_id);
    }
    if($serial_id > 0){
        $check_product_exists  = $check_product_exists->where('serial_id',$serial_id);
    }
    $check_product_exists = $check_product_exists->where('unit_mrp',format_number($change_mrp) )->first();


    // dd($check_product_exists);
    $check_product_exists->unit_mrp = $change_mrp;//$price['unit_mrp'];
    if($request->has('unit_csp')){
        if(empty($request->unit_csp)){
            $check_product_exists->unit_csp =  (!empty($price['net_amount']))?$price['net_amount']:$price['unit_mrp'];
        }else{
            $check_product_exists->unit_csp =  $unit_csp;
        }
    }else{
        $check_product_exists->unit_csp = (!empty($price['net_amount']))?$price['net_amount']:$price['unit_mrp'];
    }
    $check_product_exists->save();
    // dd($check_product_exists);
    $carts = DB::table('cart')->where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();


    $params = ['v_id' => $v_id, 'trans_from' => $trans_from, 'barcode' => $barcodefrom,'sku_code' => $sku_code ,  'batch_id'=>$batch_id,'serial_id'=>$serial_id ,'change_mrp'=>$change_mrp,'qty' =>  $qty, 'mapping_store_id' => $store_id , 'item' => $item, 'carts' => $carts , 'store_db_name' => $store_db_name, 'is_cart' => 1, 'is_update' => 1, 'db_structure' => $db_structure , 'promo_cal' => $promo_cal ];
        // dd($params);

    $offer_data = $promoC->index($params);
    $data = $offer_data;
        // dd($offer_data);
    $request->request->add(['trans_type'=>$trans_type,'barcode' => $barcodefrom, 'sku_code' => $sku_code ,'ogbarcode' => $barcodefrom ,'batch_id'=>$batch_id ,'serial_id'=>$serial_id,'change_mrp'=>$change_mrp,'qty' =>$qty , 'unit_mrp' => $unit_mrp ,'unit_rsp'=> $r_price, 'r_price' => $r_price , 's_price' => $s_price , 'discount' => $offer_data['discount'] , 'pdata' => $offer_data['pdata'] ,'data'=> $data,'multiple_mrp_flag'=>$multiple_mrp_flag,'mrp_arrs'=>$mrp_arrs, 'is_catalog' =>'0', 'weight_flag' => $offer_data['weight_flag'], 'target_offer' => $offer_data['target_offer'], 'extra_charge' => $offer_data['extra_charge'] , 'net_amount' => $offer_data['net_amount'], 'charge_details' => $offer_data['charge_details'] ]);

    $data = (object)['trans_type'=>$trans_type, 'v_id' => $v_id, 'store_id' => $store_id, 'c_id' => $c_id, 'barcode' => $offer_data['barcode'], 'sku_code' => $offer_data['sku_code'] ,'batch_id'=>$batch_id ,'serial_id'=>$serial_id ,'change_mrp'=>$change_mrp,'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'tdata' => $offer_data['tdata'], 'data' => $offer_data, 'trans_from' => $trans_from, 'vu_id' => $vu_id, 'weight_flag' => $offer_data['weight_flag'], 'target_offer' => $offer_data['target_offer'] , 'promo_cal' => $promo_cal ,'cust_gstin' => $cust_gstin, 'request' => $request , 'extra_charge' => $offer_data['extra_charge'] , 'net_amount' => $offer_data['net_amount'], 'charge_details' => $offer_data['charge_details'] ];
        /*
        $data = (object)[ 'v_id' => $v_id, 'store_id' => $store_id, 'c_id' => $c_id, 'barcode' => $offer_data['barcode'], 'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'tdata' => $offer_data['tdata'], 'data' => $offer_data, 'trans_from' => $trans_from, 'vu_id' => $vu_id ];
 

        $data = (object)[ 'v_id' => $v_id, 'store_id' => $store_id, 'c_id' => $c_id, 'barcode' => $barcode, 'qty' => $qty, 'unit_mrp' => $unit_mrp, 'unit_rsp' => @$unit_rsp, 'r_price' => $r_price, 's_price' => $s_price, 'discount' => $discount, 'pdata' => $pdata, 'data' => $dataO, 'trans_from' => $trans_from, 'vu_id' => $vu_id ];*/
        

            // dd($data);
            // $cart = new CartController;
        $this->update_to_cart($data);
        
        if($promo_cal){
            $params = ['trans_type'=>$trans_type,'v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id  , 'exclude_sku_code' => $sku_code ,'change_mrp'=>$change_mrp,'batch_id'=>$batch_id,'serial_id'=>$serial_id , 'request' => $request];
            if($request->has('cust_gstin') && $request->cust_gstin !=''){
                $params['cust_gstin'] = $request->cust_gstin;
            }
            $this->process_each_item_in_cart($params);
        }

        $cart_list = Cart::where('sku_code', '!=', $sku_code)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('order_id', $order_id)->where('status', 'process')->get();


        // dd($data);

        $carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status', 'process')->where('order_id', $order_id)->get();
        if ($request->has('get_data_of')) {
            if ($request->get_data_of == 'CART_DETAILS') {
                return $this->cart_details($request);
            } else if ($request->get_data_of == 'CATALOG_DETAILS') {
                $catalogC = new CatalogController;
                return $catalogC->getCatalog($request);
            }
        }


        return response()->json(['status' => 'product_qty_update', 'message' => 'Product quantity successfully Updated', 'total_qty' => $carts->sum('qty'), 'total_amount' =>(string) $carts->sum('total')], 200);



    // }
}

    public function remove_product(Request $request)
    {
        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;
        $vu_id = $request->vu_id;
        $trans_from = $request->trans_from;

        $wherediscount = array('user_id'=>$c_id,'v_id'=>$v_id,'store_id'=>$store_id,'type'=>'manual_discount');
        if ($request->has('vu_id')) {
            $wherediscount['vu_id'] = $request->vu_id;
        }

        $role = VendorRoleUserMapping::select('role_id')->where('user_id',$vu_id)->first();
        $role_id  = $role->role_id;

        $promo_cal = false;

        if($request->has('promo_cal')){
            $promo_cal = $request->promo_cal;
        }else{

            $vendorS = new VendorSettingController;
            $sParams = ['v_id' => $v_id,'store_id'=>$store_id,'user_id'=>$vu_id,'role_id'=>$role_id,'trans_from' => $trans_from];
            $promotionS = $vendorS->getPromotionSetting($sParams);

            if($promotionS->status ==0 || $promotionS->options[0]->promo_apply_type->value == 'manual'){
                
            }else{
                $promo_cal = true;
            }
        }

        $carts = null;
        //$barcode = $request->barcode;
        if($request->has('all') && $request->all!=null){
            if($request->all == 1){
                // $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
                $order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
                $order_id = $order_id + 1;
                $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();
                foreach ($carts as $key => $cart) {
                    Cart::where('cart_id', $cart->cart_id)->delete();
                    DB::table('cart_details')->where('cart_id' , $cart->cart_id)->delete();
                    DB::table('cart_offers')->where('cart_id' , $cart->cart_id)->delete();
                }
                DB::table('cr_dr_settlement_log')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('order_id', $order_id)->delete();

                CartDiscount::where($wherediscount)->delete();

                $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();
            }

        }else{

            $cart_id = 0;
            if($request->has('cart_id')&& $request->cart_id!=null && $request->cart_id !=''){
                $cart_id = $request->cart_id;
            }else{
                // $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
                $order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
                $order_id = $order_id + 1;
                $cart = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('barcode', $request->barcode)->where('status', 'process')->first();
                if($cart){
                    $cart_id = $cart->cart_id;
                }
            }

            if($cart_id > 0){
                $singleCart = Cart::where('cart_id', $cart_id)->first();
                Cart::where('cart_id', $cart_id)->delete();
                DB::table('cart_details')->where('cart_id' , $cart_id)->delete();
                DB::table('cart_offers')->where('cart_id' , $cart_id)->delete();
                // $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
                $order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
                $order_id = $order_id + 1;
                $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();
                if($carts->isEmpty()){
                    DB::table('cr_dr_settlement_log')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('order_id', $order_id)->delete();
                    //CartDiscount::where($wherediscount)->delete();
                }

                CartDiscount::where($wherediscount)->delete();
                if($promo_cal){

                    $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id , 'request' => $request];

                    if($request->has('cust_gstin') && $request->cust_gstin !=''){
                        $params['cust_gstin'] = $request->cust_gstin;
                    }
                    $this->process_each_item_in_cart($params);
                }


            }


        }


        if($v_id == 24 || $v_id == 11){
            $request->request->add(['get_data_of' => 'SINGLE_ITEM' ]);
            return $this->cart_details($request);
        }else{

            $roundoff_total = 0;
            $cart_qty_total = 0;

            if($carts){
                $roundoff_total = $carts->sum('total');
                $cart_qty_total = $carts->where('weight_flag','!=',1)->sum('qty') + $carts->where('weight_flag',1)->count();
            }
            
            return response()->json(['status' => 'remove_product', 'message' => 'Item Removed successfully',
                'total'  => format_number($roundoff_total), 
                'cart_qty_total'    => (string)round($cart_qty_total)
             ],200);
        }

        //$params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id ];
        //$this->process_each_item_in_cart($params);

        
    }

    function is_decimal( $val )
    {
        return is_numeric( $val ) && floor( $val ) != $val;
    }

    public function cart_details(Request $request)
    {
        // dd($request->all());
        $v_id       = $request->v_id;
        $c_id       = $request->c_id;
        $store_id   = $request->store_id; 
        $trans_from = $request->trans_from;
        $user_id = $request->vu_id;
        $cart_data  = array();
        $product_data = [];
        $tax_total  = 0;
        $cart_qty_total = 0;
        $price_override = 0;
        $role = VendorRoleUserMapping::select('role_id')->where('user_id',$user_id)->first();
        $role_id  = $role->role_id;

            // $order_id = Order::where('user_id', $c_id)->where('status', 'success')->orWhere('status' ,'error')->count();
        // $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
        $order_id = $order_id + 1;

        /*previous customer cart declined*/

        if($request->has('cart_items') && isset($request->cart_items)){
            $Checkcart = json_decode($request->cart_items,true);
            $cart = collect($Checkcart);
            $cartIds = $cart->pluck('cart_id')->toArray();
            if($cartIds){
                Cart::whereIn('cart_id',$cartIds)->delete();
                CartDetails::whereIn('cart_id',$cartIds)->delete();
            }
        }

            // Get Customer Data

        $customer_details = User::find($c_id);

        $carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status','process')
            //->orderBy('updated_at','desc')->get();
        ->orderBy('cart_id','desc')->get();
        $sub_total      = $carts->sum('subtotal');
        $item_discount  = $carts->sum('discount');
        $employee_discount = $carts->sum('employee_discount');
        $employee_id    = 0;
        $net_amount     = $carts->sum('net_amount');
        $extra_charge   = $carts->sum('extra_charge');
        $total          = $carts->sum('total');
        $tax_total      = $carts->sum('tax');
        $bill_buster_discount = 0;
        $price          = array();
        $rprice         = array();
        $qty            = array();
        $merge          = array();
        $saving         = 0;
        $carry_bag_added = false;
        $tax_details    = [];
        $tax_details_data=[];
        $param          =[];
        $params         = [];
        $total_subtotal = 0;
        $total_tax_inc  = 0;
        $total_tax_exc  = 0;
        $total_discount = 0;
        $total_amount   = 0;
        $taxC           = 0;
        $bill_buster    = [];
        $reCalTax = 0;
        $bill_buster_offers = [];
        $tatalItemLevelManualDiscount=0;
        /*Update  Manual Discount*/
        $whereMdiscount = array('user_id'=>$c_id,'v_id'=>$v_id,'store_id'=>$store_id,'type'=>'manual_discount');
        if ($request->has('vu_id')) {
            $whereMdiscount['vu_id'] = $request->vu_id;
        }
        $mDiscount  = CartDiscount::where($whereMdiscount)->orderBy('updated_at','desc')->first();

        if($mDiscount){

            $mRequest = [
                'manual_discount_factor'=> $mDiscount->factor,
                'manual_discount_basis' => $mDiscount->basis,
                'remove_discount'       => 0,
                'return'                => 0,
                'is_applicable'         =>1
            ];

            $dis_data = json_decode($mDiscount->dis_data);
            if($dis_data->type == 'predefined'){
                $mRequest['type'] = 'predefined';
                $mRequest['md_id'] = $dis_data->md_id;
            }
            $request->merge($mRequest);
        
            $offerconfig = new \App\Http\Controllers\OfferController;
            $check = $offerconfig->manualDiscount($request);
           
           if($check != null){
            $manualDiscount=$check->getdata();
            if($manualDiscount->status=='fail'){
              $where     = array('v_id'=>$v_id,'store_id'=>$store_id,'user_id'=>$c_id,'vu_id'=>$request->vu_id);
              // CartDiscount::where($where)->delete();

             }
           }
            // $response = json_decode((string) $check->getResponse()->getBody());
            
        }
        /*Update Manual Discount*/

        $wherediscount = array('user_id'=>$c_id,'v_id'=>$v_id,'store_id'=>$store_id,'type'=>'manual_discount');
        if ($request->has('vu_id')) {
            $wherediscount['vu_id'] = $request->vu_id;
        }
        $check_manual_discount = CartDiscount::where($wherediscount)->orderBy('updated_at','desc')->first();


        $single_item_discount = 0;
        foreach ($carts as $key => $cart) {
            $getOverrideStatus = false;
            $price_override = 0;
            $single_item_discount = (float)$cart->discount + (float)$cart->employee_discount + (float)$cart->bill_buster_discount;
            $bill_buster_discount += (float)$cart->bill_buster_discount;
                    // dd($bill_buster_discount);
            $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $cart->barcode)->first();
            $Item = null;
            if($bar){
                $where    = array('v_id'=>$v_id,'vendor_sku_detail_id'=>$bar->vendor_sku_detail_id,'deleted_at' => null);
                $Item     = VendorSku::select('vendor_sku_detail_id','v_id','item_id','has_batch','uom_conversion_id','barcode','variant_combi','tax_type')->where($where)->first();
            }

            $employee_id = $cart->employee_id;
            $loopQty  = $cart->qty;
            while($loopQty > 0){
               $param[] = $cart->total / $cart->qty; 
               $params[] = ['item_id' => $cart->item_id , 'price' => $cart->total / $cart->qty ];
               $loopQty--;
            }

            if(!empty($cart->memoPromo)) {
                $memoOffer = json_decode($cart->memoPromo->offers);
                            //dd($memoOffer);
                $message = '';
                if(is_array($memoOffer)){
                    if(isset($memoOffer[0]->name)){
                        $message = $memoOffer[0]->name;
                    }elseif(isset($memoOffer[0]->message)){
                        $message = $memoOffer[0]->message;
                    }
                    $bill_buster[] = [ 'amount' => $memoOffer[0]->discount, 'message' => $message ];
                    $bill_buster_offers[] = (object)[ 'name' => $message ];
                }
            }

            /*Is Price Override Active for a product*/
            $itemId = $Item->Item->id;
            // $getOverrideStatusData = VendorSkuDetails::select('is_priceoverride_active')->where(['v_id' => $request->v_id, 'item_id' => $itemId])->first()->is_priceoverride_active;
            //         if($getOverrideStatusData == '1') {
            //             $getOverrideStatus = true;
            //         }
            //         else {
            //             $getOverrideStatus = false;
            //         }

            $getOverrideStatus = true;
            $vendorItems =VendorItem::select('allow_price_override','price_override_override_by_store_policy')->where('item_id', $itemId)->first();
            if($vendorItems->price_override_override_by_store_policy == '1' && $vendorItems->allow_price_override == '0'){
                $getOverrideStatus = false;
            }
            //dd($offer_data);
            $carry_bags = DB::table('carry_bags')->select('barcode')->where('v_id', $v_id)->where('store_id', $store_id)->where('status','1')->where('deleted_status', '0')->get();
            $carr_bag_arr = $carry_bags->pluck('barcode')->all();      
            $carry_bag_flag = in_array($cart->item_id, $carr_bag_arr);
            
            if($carry_bag_flag){
                $carry_bag_added = true;
            }

            $remark = '';
            if(isset($cart->remark) ){
                $remark = $cart->remark;
            }
            $product_details = json_decode($cart->section_target_offers);
            //Current Stock
            //$stock = $Item->currentStock->where('store_id' , $store_id)->sortByDesc('created_at')->first();

            $stockPoint = StockPoints::where(['v_id'=>$v_id,'store_id'=>$store_id,'is_sellable'=>'1','is_active'=>'1'])->first()->id;
            $stock = StockPointSummary::where(['v_id'=>$v_id,'store_id'=>$store_id,
            'barcode'=>$cart->barcode,'stock_point_id'=>$stockPoint])->first();
            if($stock){
                //$currentStock = ((int)$stock->opening_qty + (int)$stock->int_qty ) - (int)$stock->out_qty;
                $currentStock = $stock->qty; 
            }else{
                $currentStock = 0;
            }

            //Batches
            $batches = [];

            if($Item->has_batch ==1){//Batch Enabled
                $batch =  DB::table('grn_list as gl')
                ->join('grn_batch as gb', 'gb.grnlist_id' , 'gl.id')
                ->join('batch as b', 'b.id' , 'gb.batch_id')
                ->join('item_prices as ip' , 'ip.id', 'b.item_price_id')
                ->where('gl.barcode', $Item->barcode)
                ->whereNotNull('b.batch_no')
                ->select(DB::raw(' max(ip.mrp) as mrp , b.batch_no ') )
                ->groupBy('b.batch_no')
                ->get();
                if($batch){
                    $batches = $batch->all();
                }
            }

            $product_data['current_stock']   = $currentStock;
            $product_data['batch']   = $batches;
            

            $product_data['carry_bag_flag'] = $carry_bag_flag;
            $product_data['p_id']           = (int)$cart->item_id;
            $product_data['category']       = ($product_details->category)?$product_details->category:'';
            $product_data['brand_name']     = ($product_details->brand_name)?$product_details->brand_name:'';
            $product_data['sub_categroy']   = ($product_details->sub_categroy)?$product_details->sub_categroy:'';;
            $product_data['whishlist']      = 'No';
            $product_data['weight_flag']    = ($Item->uom->selling->type == 'WEIGHT'? true:false);
            $product_data['quantity_change_flag'] = (strlen($cart->plu_barcode) == 13)?false:true;
            $product_data['p_name']         = utf8_encode(ucwords(strtolower($cart->item_name)));
            $product_data['offer']          = $product_details->offer;//(count(@$offer_data['applied_offer']) > 0)?'Yes':'No';//$product_details->offer;
            $product_data['offer_data']     = $product_details->offer_data;
            //$product_data['offer_data']     = $product_details->offer_data;
            //$product_data['qty'] = '';
            $product_data['is_priceoverride_active'] = $getOverrideStatus;
            /*Price */

            //$priceList = $Item->vprice->where('v_id',$v_id)->where('variant_combi',$Item->variant_combi);    
            $priceArr  = array('v_id'=>$v_id,'store_id'=>$store_id,'item'=>$Item,'unit_mrp'=>isset($cart->unit_mrp)?$cart->unit_mrp:'');
            $config   =  $this->cartconfig;
            //$price    =  $config->getprice($priceList, $cart->unit_mrp);
            $price    =  $config->getprice($priceArr);
            $unit_mrp =  $price['unit_mrp']; 
           // dd($unit_mrp);
            $r_price  =  $price['r_price'] ;
            $s_price  =  !empty($price['s_price'])?$price['s_price']:$price['unit_mrp'] ;
            $mrp_arrs = $price['mrp_arrs'];
            $multiple_mrp_flag = $price['multiple_mrp_flag'];

            $product_data['multiple_price_flag'] = $multiple_mrp_flag; //isset($offer_data['multiple_price_flag'])?$offer_data['multiple_price_flag']:false;


            $product_data['multiple_mrp'] = $mrp_arrs;  //isset($offer_data['multiple_mrp'])?$offer_data['multiple_mrp']:false;

            $tdata         = json_decode($cart->tdata);
            //$taxC    = 0;
            $ttax =0;

             $itemLevelmanualDiscount=0;
             if($cart->item_level_manual_discount!=null){
                $iLmd = json_decode($cart->item_level_manual_discount);
                $itemLevelmanualDiscount= $iLmd->discount;
             }
            if($check_manual_discount){
                $dis_data = json_decode($check_manual_discount->dis_data,true);
                foreach ($dis_data['cart_data'] as $cdata) {
                    if($cdata['item_id'] == $cart->item_id && $cdata['batch_id'] == $cart->batch_id && $cdata['serial_id'] == $cart->serial_id && $cdata['unit_mrp'] == $cart->unit_mrp){
                            //$r_price = $cdata['total'];
                        $single_item_discount += (float)$cdata['discount'];
                        $cart->manual_discount += (float)$cdata['discount'];
                        $s_price = format_number($cdata['total']);
                        $total_subtotal   += $cdata['tdata']['total'];
                        $ttax   = $cdata['tdata']['tax'];
                        $cart->total = $s_price;
                        $cart->tax = $cdata['tdata']['tax'];
                        $reCalTax += $cdata['tdata']['tax'];
                    }
                }

            }else{

                $s_price  = isset($offer_data['s_price'])?$offer_data['s_price']:$cart->unit_mrp;
                $s_price  = $s_price*$cart->qty;
                $total_subtotal   += $cart->total;
                $ttax  = $tdata->tax;
            }

            
            $r_price  = isset($offer_data['r_price'])?$offer_data['r_price']:$cart->unit_mrp; //     
            $tax_type = null;
            if(isset($Item->tax_type) ){
                $tax_type = $Item->tax_type;
            }else{
                $tax_type = $Item->Item->tax_type;
            }
            if($tax_type == 'INC'){
                $total_tax_inc   += $ttax;  //$tdata->tax;
            }
            if($tax_type == 'EXC'){
             $total_tax_exc   +=     $ttax;  //$tdata->tax;
            }
            
            //format_number($offer_data['r_price']);
            $product_data['r_price'] = $product_details->r_price;


            //format_number($offer_data['s_price']);
            $product_data['s_price'] = $product_details->s_price;
            $product_data['unit_mrp'] = $product_details->unit_mrp;
            $product_data['discount'] = format_number($cart->discount + $cart->manual_discount + $cart->lpdiscount + $cart->coupon_discount + $cart->bill_buster_discount+$itemLevelmanualDiscount);

            $product_data['extra_charge'] = format_number($cart->extra_charge);
            $product_data['net_amount'] = format_number($cart->net_amount);

            /*if(!empty($offer_data['applied_offers']) ){
                $product_data['r_price'] = format_number($offer_data['r_price']);
                $product_data['s_price'] = format_number($offer_data['s_price']);
            }*/

            $product_data['varient']    = '';
            /*Product Image*/
            $product_data['images'] = 'zwing_default.png';

            /*Get Item Image*/
            $paramImg   = array('barcode'=>$cart->barcode,'v_id'=>$v_id);
            $getimages  = $this->cartconfig->getItemImage($paramImg);
            $product_data['images'] = $getimages['single_image'];
            $product_data['images_array'] = $getimages['multiple_image'];

            /*Product Image End*/

            $product_data['description']= '';
            $product_data['deparment']  = '';
            $product_data['barcode']    = $cart->barcode;
            $product_data['batch_id']   = $cart->batch_id;
            $product_data['serial_id']  = $cart->serial_id;
            $product_data['tdata'] = $cart->tdata;
             
            if($single_item_discount != 0 && isset($check_manual_discount->basis) ){
                $product_data['manual_discount'] = ['basis'=> $check_manual_discount->basis,'factor'=>$check_manual_discount->factor,'mdiscount'=>$single_item_discount, 'item_md' => format_number($itemLevelmanualDiscount)];
            }else{
                $product_data['manual_discount'] = ['basis'=>'', 'factor'=>'', 'mdiscount'=>'','item_md' => format_number($itemLevelmanualDiscount)];
            }
            if($itemLevelmanualDiscount>0){
             if($product_data['manual_discount']['mdiscount']==''){
                $mDiscount =0;
              }else{
               
               $mDiscount =$product_data['manual_discount']['mdiscount'];

              }
              $product_data['manual_discount']['mdiscount'] = $mDiscount+$itemLevelmanualDiscount;
             }    
            $product_data['remark'] = $remark;
            $product_data['inventory_status'] = '1';
            $product_data['uom'] = $product_details->uom;

            //$tax_total = $tax_total +  $tax_amount ;
            $tax_amount                 = $cart->tax;
                
            if($cart->weight_flag){
                $cart_qty_total =  $cart_qty_total + 1;
            }else{

                if($cart->plu_barcode){
                    $cart_plu_qty = $cart->qty;
                    $cart_plu_qty = explode('.',$cart_plu_qty);
                    //dd($cart_plu_qty);
                    if(count($cart_plu_qty) > 1 ){
                        $cart_qty_total =  $cart_qty_total + 1;
                    }else{
                        $cart_qty_total =  $cart_qty_total + $cart->qty;    
                    }
                }else{
                    $cart_qty_total =  $cart_qty_total + $cart->qty; 
                }   
            }
            
            $response['carry_bag_flag']  = $carry_bag_flag;
            // if($Item->Item->tax_type == 'INC'){
            //   $total_tax_inc                  += $cart->tax;
            // }
            // if($Item->Item->tax_type == 'EXC'){
            //   $total_tax_exc                  += $cart->tax;
            // }
            $salesman_name ='';
            $salesmans = Vendor::where('id', $cart->salesman_id)->first();
            //dd($salesmans);
            if($salesmans){
                $salesman_name = $salesmans->first_name.' '.$salesmans->last_name;
            }

            $total_discount        += $cart->discount;
            
            $cart_total =  $cart->total;
            if($Item->tax_type == 'INC'){
                $tamt   = $cart->subtotal-$cart->tax;
                $total_amount          += $tamt;
            }else{
                $total_amount          += $cart->subtotal;
            }
             
            $add_data = true;
            if($request->get_data_of == 'SINGLE_ITEM'){
                if(isset($request->barcode) &&  $cart->barcode == $request->barcode){
                    $add_data = true;
                }else{
                    $add_data = false;
                }   
            }
           if($itemLevelmanualDiscount>0){
            $tatalItemLevelManualDiscount  += $itemLevelmanualDiscount;
           }
            if($add_data){
                if($cart->override_flag){
                    $price_override = 1;
                }
                $cart_data[] = array(
                    'cart_id'       => $cart->cart_id,
                    'product_data'  => $product_data,
                    'amount'        => $cart_total,
                    'qty'           => $cart->qty,
                    'tax_amount'    => format_number($tax_amount),
                    'delivery'      => $cart->delivery,
                    'salesman_id'   => $cart->salesman_id,
                    'salesman_name' => $salesman_name,
                    'price_override' => $price_override
                
                        // 'ptotal'        => $cart->amount * $cart->qty,
                );
            }


            //$tax_total = $tax_total +  $tax_amount ;
            
            $qty[] = $cart->qty;
            //$merge = array_combine($rprice,$qty);

            /*price override flag*/
           
            
        }

        // dd($bill_buster);

        $store_db_name = get_store_db_name(['store_id' => $store_id]);

        ######################################
        ##### --- BILL BUSTER  START --- #####

        //Bill Buster Calculation

        if($employee_discount > 0.00){
            $total = $total - $employee_discount;
        }
        // dd(array_unique($bill_buster_offers, SORT_REGULAR));
        $finalBillBustor = [];
        if(count($bill_buster_offers) > 0) {
            $finalBillBustor = [];
            $bill_buster = collect($bill_buster);
            foreach (array_unique($bill_buster_offers, SORT_REGULAR) as $memMsg) {
                $finalBillBustor[] = [ 'amount' => $bill_buster->where('message', $memMsg->name)->sum('amount'), 'message' => $memMsg->name ];
            }
        }

        // dd($finalBillBustor);


        ##### --- BILL BUSTER  END --- #####
        ####################################
        //dd($tax_details_data);
        //dd($param);
        // $bill_buster_discount = 0;
        //if(isset($bill_buster_dis['discount']) && $bill_buster_dis['discount'] > 0 ){}

        $total          = $total - $bill_buster_discount;
        // $tax_total      = 0;
        //$total = $total + $tax_total;
        $saving         = $item_discount + $bill_buster_discount;
        $bags           = $this->get_carry_bags($request);
        $bags           = $bags['data'];
        
        if(empty($bprice->Price)) {
            $carry_bag_total = 0;
        } else {
            $carry_bag_total = $bprice->Price;
        }

        //$total = $sub_total + $carry_bag_total;
        //$less = 
        
        $offeredAmount = 0;
        $grand_total = $sub_total + $carry_bag_total + $tax_total;
        $offerUsed = PartnerOfferUsed::where('user_id',$c_id)->where('order_id',$order_id)->first();
        if( $offerUsed){

            $offers =  PartnerOffer::where('id',$offerUsed->partner_offer_id)->first();
            if($offers){
                if($offers->type == 'PRICE'){

                    $offerMsg = "Get Cash Back Upto $offers->value ";
                    $offeredAmount = $offers->value;
                }else if($offers->type == 'PERCENTAGE'){
                    $offerMsg = "Get Upto $offers->value % Discount max upto $offers->max";

                    $offeredAmount = ($grand_total  * $offers->value) / 100;
                    if( $offers->max != 0  && $offeredAmount >= $offers->max){
                        $offeredAmount = $offers->max;
                    }

                }

                $grand_total = $grand_total - $offeredAmount;
            }

        }

        $store = DB::table('stores')->select('delivery')->where('store_id', $store_id)->where('v_id', $v_id)->first();

            // if ($store->delivery == 'Yes') {
            //     $product_data['delivery'] = $wflag;
            // }
            // $sub_total = (int)$sub_total + $bprice->Price;
        
        $total_saving   = $total_discount;
        $roundoff_total = round($total_subtotal);

        $voucher_array  = [];
           /* $pay_by_voucher = 0;
            $vouchers = DB::table('voucher_applied as va')
                        ->join('voucher as v','v.id','va.voucher_id')
                        ->select('v.*')
                        ->where('va.user_id', $c_id)->where('va.v_id', $v_id)->where('va.store_id', $store_id)
                        ->where('va.order_id', $order_id)->get();*/

        $voucher_total = 0 ;
        $pay_by_voucher = 0;


        /*Voucher Check*/

        $vouchers = DB::table('cr_dr_settlement_log')->select('id','voucher_id','applied_amount')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('order_id', $order_id)->get();
        // $vouchers = DB::table('cr_dr_settlement_log')->select('id','voucher_id','applied_amount')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->get();
        $voucher_total = 0; 
        foreach ($vouchers as $key => $voucher) {
            $voucher_applied = DB::table('cr_dr_settlement_log')->where('voucher_id', $voucher->voucher_id)->where('status', 'APPLIED')->get();
            $totalVoucher = DB::table('cr_dr_voucher')->where('id', $voucher->voucher_id)->first();
            if($totalVoucher){
                $totalVoucher = $totalVoucher->amount;
                $voucher_remain_amount = $totalVoucher - $voucher_applied->sum('applied_amount');
                array_push($voucher_array, ['name' => 'Voucher Credit', 'amount' => $voucher_remain_amount]);
                $voucher_total += $voucher_remain_amount;
                if ($roundoff_total >= $voucher_remain_amount) {
                    if($voucher_remain_amount > 0) {
                        $voucher_applied_amount = $voucher_remain_amount;
                        $pay_by_voucher += $voucher_remain_amount;
                        $roundoff_total = $roundoff_total - $voucher_remain_amount;
                    }
                } else {
                    $voucher_applied_amount = $roundoff_total;
                    $pay_by_voucher += $roundoff_total;
                    $roundoff_total = 0;
                }
                DB::table('cr_dr_settlement_log')->where('id', $voucher->id)->update(['status' => 'PROCESS' , 'applied_amount' => format_number($voucher_applied_amount) ]);
            }
        }

        /*Voucher Check end*/

            

        $voucher_total = $pay_by_voucher;

        $vendorS = new VendorSettingController;
        $sParams = ['v_id' => $v_id,'store_id'=>$store_id,'user_id'=>$user_id,'role_id'=>$role_id,'trans_from' => $trans_from];
        // $sParams = ['v_id' => $v_id, 'trans_from' => $trans_from];
        $product_max_qty =  $vendorS->getProductMaxQty($sParams) ;
        $cart_max_item   = $vendorS->getMaxItemInCart($sParams);
        $store_credit_status   = $vendorS->getStoreCredit($sParams);
        //dd($store_credit_status);
        // Check Store Credit
        if($store_credit_status->display_status == 1) {
            $store_credit = format_number($customer_details->store_credit);
        } else {
            $store_credit = '0.00';
        }

        // Tax calculation when manual discount appled
        if($check_manual_discount) {
            $tax_total = $reCalTax;
        }

        $paymentTypeSettings = $vendorS->getPaymentTypeSetting($sParams);

        //Item Type : "" => normal, "1"=>tax, "2"=> manual discount
         if($total_tax_exc >0){
            $sb_total = $total_amount; 
        }else{
            //$sb_total = ($total_amount -($total_tax_exc+$total_tax_inc));     
            $sb_total = $total_amount; 
        }

        $mDiscount=0;
        if($check_manual_discount!=null && $tatalItemLevelManualDiscount>0){
          
          $mDiscount   = $check_manual_discount->discount+$tatalItemLevelManualDiscount;

        }elseif($check_manual_discount==null && $tatalItemLevelManualDiscount>0){

           $mDiscount  = $tatalItemLevelManualDiscount;
        }elseif ($tatalItemLevelManualDiscount==0 && $check_manual_discount!=null ) {

           $mDiscount=$check_manual_discount->discount;
        }else{
           $mDiscount = 0; 
        }
        
        //dd($mDiscount);
        //$sb_total = ($total_amount -($total_tax_exc+$total_tax_inc)); 
        $bill_summary   = [];
        $bill_summary[] = [ 'name' => 'sub_total' , 'display_text' => 'Sub Total' ,'display_name' => 'Sub Total' , 'item_type'=>"",'value' => (string)format_number($sb_total),'sign'=>''];
        if($total_discount > 0.0){
            $bill_summary[] = [ 'name' => 'discount' , 'display_text' => 'Discount' ,'display_name' => 'Discount','item_type'=>"", 'value' => (string)format_number($total_discount),'sign'=>'-' ];
        }
        if($voucher_total > 0){

            $bill_summary[] = [ 'name' => 'voucher' , 'display_text' => 'Voucher Total','display_name' => 'Voucher Total','item_type'=>"" ,'value' => (string)format_number($voucher_total) ,'mop_flag' => '1','sign'=>'-'];
        }

        if($bill_buster_discount > 0){
            $bill_summary[] = [ 'name' => 'bill_buster' , 'display_text' => 'Bill Buster Discount','display_name' => 'Bill Buster Discount','item_type'=>"2", 'value' => (string)format_number($bill_buster_discount),'sign'=>'-' ];
        }

        if($mDiscount>0){
            $mdiscountType = [];
            if($tatalItemLevelManualDiscount > 0) {
                $mdiscountType[] = ['name' => 'item_level', 'value' => format_number($tatalItemLevelManualDiscount)];
            }
            if($mDiscount > $tatalItemLevelManualDiscount) {
                $mdiscountType[] = ['name' => 'bill_level', 'value' => format_number($mDiscount - $tatalItemLevelManualDiscount)];   
            }
            $bill_summary[] = [ 'name' => 'manual_discount' , 'display_text' => 'Manual Discount' ,'display_name' => 'Manual Discount' ,'item_type'=>"2", 'value' => (string)format_number($mDiscount),'sign' => '-', 'type' => $mdiscountType];
        }
        if($total_tax_exc > 0){
            $bill_summary[] = [ 'name' => 'tax_total' , 'display_text' => 'Tax Total (Excluded)' , 'display_name' => 'Tax Total (Excluded)','type' => 'EXCLUSIVE','item_type'=>"1", 'value' => (string)format_number($total_tax_exc),'sign'=>'' ];
        }
        if($total_tax_inc > 0){
            $bill_summary[] = [ 'name' => 'tax_total' , 'display_text' => 'Tax Total (Included)' , 'display_name' => 'Tax Total (Included)','type' => 'INCLUSIVE','item_type'=>"1" ,'value' => (string)$total_tax_inc,'sign'=>'' ];
        }

        if($extra_charge > 0){
            $bill_summary[] = [ 'name' => 'extra_charge' , 'display_text' => 'Extra Charge' ,'display_name' => 'Extra Charge' , 'value' => (string)format_number($extra_charge),'sign'=>'+' ];
        }
        // Round Off Calculation
        if (!empty(getRoundValue($total_subtotal))) {

             

            $bill_summary[] = [ 'name' => 'roundoff' , 'display_text' => 'Round Off' ,'display_name' => 'Round Off' ,'item_type'=>"", 'value' => abs(getRoundValue($total_subtotal)), 'sign' => getRoundValue($total_subtotal) < 0 ? '-' : '+' ];
        }
        $bill_summary[] = [ 'name' => 'total' , 'display_text' => 'Total' ,'display_name' => 'Total' ,'item_type'=>"", 'value' => (string)format_number($roundoff_total),'sign'=>''];


        $hold_bill_count = 0;
        $hold_bill_count_flag = false;
        $cartSettings = $vendorS->getCartSetting($sParams);
        if(isset($cartSettings->recall_bill) ){
            if(isset($cartSettings->recall_bill->$trans_from)){
                $status = $cartSettings->recall_bill->$trans_from;
                if($status->status == 1){
                    $hold_bill_count_flag = true;
                }
            }else{
                $status = $cartSettings->recall_bill->DEFAULT;
                if($status->status == 1){
                    $hold_bill_count_flag = true;
                }
            }
        }

        if($hold_bill_count_flag){
            $hold_bill_count = Order::where('transaction_sub_type', 'hold');
            if($request->has('vu_id')){
                $hold_bill_count = $hold_bill_count->where('vu_id', $request->vu_id)->count();
            }else{
                $hold_bill_count = $hold_bill_count->where('user_id', $c_id)->count();
            }
        }



        return response()->json(['status' => 'cart_details', 'message' => 'Your Cart Details', 
        'payment_type'      =>  $paymentTypeSettings,
        'data'              => $cart_data, 'product_image_link' => product_image_link().$v_id.'/',
         //'offer_data'  => $global_offer_data,
        'current_date'      => date('d F Y'),
        'cart_max_item'     => (string)$cart_max_item,
        'product_max_qty'   => (string)$product_max_qty,
        'carry_bag_added'   => $carry_bag_added,
        'bags'              => $bags, 
        'carry_bag_qty_total' => (string)collect($bags)->sum('Qty'),
        'sub_total'         => (format_number($sub_total))?format_number($sub_total):'0.00', 
        'tax_total'         => (format_number($tax_total))?format_number($tax_total):'0.00',
        'employee_id'       => $employee_id,
        'employee_discount' => (format_number($employee_discount))?format_number($employee_discount):'0.00',
        'bill_buster_discount' => (format_number($bill_buster_discount))?format_number($bill_buster_discount):'0.00',
        'discount' => (format_number($item_discount))?format_number($item_discount):'0.00', 
        'net_amount' => (format_number($net_amount))?format_number($net_amount):'0.00', 
        'extra_charge' => (format_number($extra_charge))?format_number($extra_charge):'0.00', 
        //'grand_total' => (format_number($grand_total))?format_number($grand_total):'0.00', 
        'order_id'          => $order_id, 
        'carry_bag_total'   => (format_number($carry_bag_total))?format_number($carry_bag_total):'0.00',
        'voucher_total'     => $voucher_total,
        'vouchers'          => $voucher_array,
        'pay_by_voucher'    => $pay_by_voucher,
        'total'             => format_number($roundoff_total), 
        'cart_qty_total'    => (string)round($cart_qty_total),
        'saving'            => (format_number($saving))?format_number($saving):'0.00',
        'delivered'         => @$store->delivery , 
        'offered_mount'     => (format_number($offeredAmount))?format_number($offeredAmount):'0.00',
        'hold_bill_count' => (string)$hold_bill_count,
        'store_credit'      => $store_credit,
        'bill_buster'       => $finalBillBustor,
        'bill_summary'      => $bill_summary,
        'price_override' => $price_override
         ],200);
        // echo array_sum($saving);    

    }

    public function process_to_payment(Request $request)
    {
            $v_id     = $request->v_id;
            $c_id     = $request->c_id;
            $store_id = $request->store_id;
            $subtotal = $request->sub_total;
            $discount = $request->discount;
            $pay_by_voucher = $request->pay_by_voucher;
            $trans_from     = $request->trans_from;
        if ($request->has('payment_gateway_type')) {
            $payment_gateway_type = $request->payment_gateway_type; //'EZETAP'
        } else {
            $payment_gateway_type = 'RAZOR_PAY';
        }

        $vu_id = 0;
        if ($request->has('vu_id')) {
            $vu_id = $request->vu_id;
        }

        //Hold Bill
        $hold_bill = 0;
        $transaction_sub_type = 'sales';
        if($request->has('hold_bill')){
            $hold_bill = $request->hold_bill;
            $transaction_sub_type = 'hold';
            $hold_bill = 1;
        }
        
        if($request->has('transaction_sub_type')){
            $transaction_sub_type = $request->transaction_sub_type;
            $hold_bill = 1;
        }

        //Checking Opening balance has entered or not if payment is through cash
        if ($vu_id > 0 && $payment_gateway_type == 'CASH') {

            $vendorSett = new \App\Http\Controllers\VendorSettlementController;
            $response = $vendorSett->opening_balance_status($request);
            if ($response) {
                return $response;
            }
        }

        $net_amount = 0;
        $extra_charge = 0;
        if($request->has('net_amount')){
            $net_amount = $request->net_amount;
        }

        if($request->has('extra_charge')){
            $extra_charge = $request->extra_charge;
        }

        $bill_buster_discount = $request->bill_buster_discount;
        $tax                  = $request->tax_total;
        $total                = $request->total;
        $trans_from = $request->trans_from;

        $t_order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $t_order_id = $t_order_id + 1;
        $order_id = order_id_generate($store_id, $c_id, $trans_from);
        $custom_order_id = custom_order_id_generate(['store_id' => $store_id, 'user_id' => $c_id, 'trans_from' => $trans_from]);

        $order = new Order;

        $order->order_id        = $order_id;
        $order->custom_order_id = $custom_order_id;
        $order->o_id            = $t_order_id;
        $order->v_id            = $v_id;
        $order->store_id        = $store_id;
        $order->user_id         = $c_id;
        $order->trans_from      = $trans_from;
        $order->subtotal        = $subtotal;
        $order->discount        = $discount;
        $order->bill_buster_discount = $bill_buster_discount;
        $order->tax             = $tax;
        $order->net_amount      = $net_amount;
        $order->extra_charge    = $extra_charge;
        $order->total           = (float)$total + (float)$pay_by_voucher;
        $order->status          = 'process';
        $order->transaction_sub_type          = $transaction_sub_type;
        $order->date            = date('Y-m-d');
        $order->time            = date('h:i:s');
        $order->month           = date('m');
        $order->year            = date('Y');
        $order->payment_type    = 'full';
        $order->payment_via     = $payment_gateway_type;
        $order->is_invoice      = '0';
        $order->vu_id           = $vu_id;
        $order->save();

        $cart_data = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $t_order_id)->where('user_id', $c_id)->get()->toArray();

        $porder_id = $order->od_id;
        $itemLevelmanualDiscount=0;
        foreach ($cart_data as $value) {
            $cart_details_data = CartDetails::where('cart_id', $value['cart_id'])->get()->toArray();
            $save_order_details = array_except($value, ['cart_id']);
            $save_order_details = array_add($value, 't_order_id', $porder_id);
            $order_details = OrderDetails::create($save_order_details);

            if($value['item_level_manual_discount']!=null){
                $iLmd = json_decode($value['item_level_manual_discount']);
                $itemLevelmanualDiscount += (float)$iLmd->discount;
            }

            foreach ($cart_details_data as $cdvalue) {
                $save_order_item_details = array_add($cdvalue, 'porder_id', $order_details->id);
                OrderItemDetails::create($save_order_item_details);
            }

            //Deleting cart if hold bill is true
            if($hold_bill){
                CartDetails::where('cart_id', $value['cart_id'])->delete();
                CartOffers::where('cart_id', $value['cart_id'])->delete();
                Cart::where('cart_id', $value['cart_id'])->delete();
            }
        }

        $order->ilm_discount_total = $itemLevelmanualDiscount;
        $order->save();

        $payment = null;
        if ($pay_by_voucher > 0.00 && $total == 0.00) {

            $request->request->add(['t_order_id' => $t_order_id, 'order_id' => $order_id, 'pay_id' => 'user_order_id_' . $t_order_id, 'method' => 'voucher_credit', 'invoice_id' => '', 'bank' => '', 'wallet' => '', 'vpa' => '', 'error_description' => '', 'status' => 'success', 'payment_gateway_type' => 'Voucher', 'cash_collected' => '', 'cash_return' => '', 'amount' => $pay_by_voucher]);

            return $this->payment_details($request);

        } else if ($pay_by_voucher > 0.00 && $total > 0.00) {

            $payment = new Payment;
            $payment->store_id = $store_id;
            $payment->v_id = $v_id;
            //$payment->t_order_id = 0;
            $payment->order_id = $order_id;
            $payment->user_id = $c_id;
            $payment->pay_id = 'user_order_id_' . $t_order_id;
            $payment->amount = $pay_by_voucher;
            $payment->method = 'voucher_credit';
            //$payment->invoice_id = '';
            $payment->payment_invoice_id = '';
            $payment->bank = '';
            $payment->wallet = '';
            $payment->vpa = '';
            $payment->error_description = '';
            $payment->status = 'success';
            $payment->date = date('Y-m-d');
            $payment->time = date('h:i:s');
            $payment->month = date('m');
            $payment->year = date('Y');

            $payment->save();

        }

        $orderC = new OrderController;
        $order_arr = $orderC->getOrderResponse(['order' => $order , 'v_id' => $v_id , 'trans_from' => $trans_from ]) ;

        $order = array_add($order, 'order_id', $porder_id);

        $res =  ['status' => 'proceed_to_payment', 'message' => 'Proceed to Payment', 'data' => $order , 'order_summary' => $order_arr ];
        if($request->has('response') && $request->response == 'ARRAY'){

            return $res;
        }else{
            return response()->json($res, 200);
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
            if($request->has('gateway_response')){
                $gateway_response = $request->gateway_response;
                $gateway_response = json_decode($gateway_response);

            }

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

            $vouchers = DB::table('cr_dr_settlement_log')->select('id','voucher_id','applied_amount')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('order_id', $t_order_id)->get();
            $voucherUsedType = $voucherSetting->used_type;
            foreach($vouchers as $voucher) {
                $totalVoucher = 0;
                $vou = DB::table('cr_dr_voucher')->select('amount')->where('id', $voucher->voucher_id)->first();
                $totalVoucher = $vou->amount;
                $previous_applied = DB::table('cr_dr_settlement_log')->select('applied_amount')->where('voucher_id' , $voucher->voucher_id)->get();
                $totalAppliedAmount = $previous_applied->sum('applied_amount');

                if( $voucherUsedType == 'PARTIAL' ){
                    if( $vou->amount ==  $totalAppliedAmount ){
                     DB::table('cr_dr_voucher')->where('id', $voucher->voucher_id)->update(['status' => 'used']);
                     }else if($totalAppliedAmount > $vou->amount){
                         DB::table('cr_dr_voucher')->where('id', $voucher->voucher_id)->update(['status' => 'used']);
                     }else{
                         DB::table('cr_dr_voucher')->where('id', $voucher->voucher_id)->update(['status' => 'partial']);
                     }
                }else{

                    DB::table('cr_dr_voucher')->where('id', $voucher->voucher_id)->update(['status' => 'used']);
                }

                DB::table('cr_dr_settlement_log')->where('id', $voucher->id)->update(['status' => 'APPLIED' ]);
            }
        
        }else{

            $vouchers = DB::table('cr_dr_settlement_log')->select('voucher_id')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('order_id', $t_order_id)->get();

            foreach ($vouchers as $voucher) {
                DB::table('cr_dr_voucher')->where('id', $voucher->voucher_id)->update(['status' => 'used']);
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


                    $value['t_order_id']    = $invoice->id; 
                    $save_invoice_details   = $value;
                    $invoice_details_data   = InvoiceDetails::create($save_invoice_details);
                    $order_details_data     = OrderItemDetails::where('porder_id', $value['id'])->get()->toArray();

                    //Transfering Serial table data to Serial Sold after invoice created
                    if($value['serial_id'] > 0){
                        $serial = Serial::find($value['serial_id']);
                        $serialData[] = $serial->toArray();
                        unset($serialData['id']);
                        unset($serialData['created_at']);
                        unset($serialData['updated_at']);
                        $serialData['invoice_id'] = $invoice->id;
                        $serialData['sales_date'] = $invoice->created_at;

                        SerialSold::create($serialData);
                        $serial->delete();
                    }

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
                            $params = array('v_id'=>$value['v_id'],'store_id'=>$value['store_id'],'barcode'=>$value['barcode'],'qty'=>$value['qty'],'invoice_id'=>$invoice->invoice_id,'order_id'=>$invoice->ref_order_id,'transaction_type'=>'SALE' , 'transaction_scr_id' => $invoice_details_data->id);
                            $this->cartconfig->updateStockQty($params);

                            /*Update Stock end*/

                }
                ##########################
                ## Remove Cart  ##########
                ##########################

                $cart_id_list = Cart::where('order_id', $orders->o_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $user_id)->get(['cart_id']);
                CartDetails::whereIn('cart_id', $cart_id_list)->delete();
                CartOffers::whereIn('cart_id', $cart_id_list)->delete();
                Cart::whereIn('cart_id', $cart_id_list)->delete();


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

            if($request->type == 'adhoc_credit_note'){
                //Need to impolment voucher send sms

            }
            /*Email Functionality*/
            $emailParams = array('v_id'=>$v_id,'store_id'=>$store_id,'invoice_id'=>$invoice->invoice_id,'user_id'=>$user_id);

            $crtCont  = new \App\Http\Controllers\CartController;
            // $crtCont->orderEmail($emailParams);
            //$this->orderEmail($emailParams);

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
    

    public function orderEmailRecipt(Request $request){
        $v_id        = $request->v_id;
        $store_id    = $request->store_id;
        $invoice_id  = $request->invoice_id;
        $user_id     = $request->c_id;
        $email_id    = $request->email_id;
        $type        = isset($request->type)?$request->type:'';


        $return      = array();
        
        if(!empty($type) && $type== 'account_deposite' || $type== 'adhoc_credit_note' ||  $type== 'refund_credit_note'){
        $invoiceExist =DepRfdTrans::where('doc_no', $invoice_id)->where('v_id',$v_id)->first();
        }else{
        $invoiceExist= Invoice::where('invoice_id',$invoice_id)->count();
        }
        if($invoiceExist > 0){
            $emailParams = array('v_id'=>$v_id,'store_id'=>$store_id,'invoice_id'=>$invoice->invoice_id,'user_id'=>$user_id,'email_id'=>$email_id,'type'=>$type);
            if($this->orderEmail($emailParams)){
                $return = array('status'=>'success','message'=>'Invoice Send successfully');
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
        $invoice_id  = $parms['invoice_id'];
        $email_id    = $parms['email_id'];
        $date        = date('Y-m-d');
        $time        = date('h:i:s');
        $time        = strtotime($time); 


        $organisation = Organisation::find($v_id);
        if($organisation->db_type == 'MULTITON' && $organisation->db_name != ''){
            $connPrm    =array('host'=> $organisation->connection->host,'port'=>$organisation->connection->port,'username'=>$organisation->connection->username,'password' =>  $organisation->connection->password,'db_name'=>$organisation->db_name);
            dynamicConnectionNew($connPrm);
        }


        $invoice     = Invoice::where('invoice_id',$invoice_id)->with(['payments','details'])->first();
        $payment     = $invoice->payments;

            // dd($invoice);

        $last_invoice_name = $invoice->invoice_name;
        if($last_invoice_name){
            $arr =  explode('_',$last_invoice_name);
            $id = $arr[2] + 1;
            $current_invoice_name = $date.'_'.$time.'_'.$store_id.'_'.$id.'.pdf';
        }else{
            $current_invoice_name = $date.'_'.$time.'_'.$store_id.'_1.pdf';
        }
        $bilLogo      = '';
        $bill_logo_id = 5;
        $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
        if($vendorImage)
        {
            $bilLogo = env('ADMIN_URL').$vendorImage->path;
        }

        try{
            $user = Auth::user();
            //if($user->email != null && $user->email != ''){
            if($email_id != null && $email_id != ''){

                $html          = $this->order_receipt($user_id , $v_id, $store_id, $invoice_id);
                $pdf           = PDF::loadHTML($html);
                $path          =  storage_path();
                $complete_path = $path."/app/invoices/".$current_invoice_name;
                $pdf->setWarnings(false)->save($complete_path);
                $payment_method = $payment[0]->method;
                $vendor  = Vendor::find($v_id);
                
                $to     = $email_id;      //$mail_res['to'];
                $cc     = [];//$mail_res['cc'];
                $bcc    = [];//$mail_res['bcc'];

                //dd($cc);
                $mailer = Mail::to($user->email); 
                if(count($bcc)> 0){
                    $mailer->bcc($bcc);
                }
                if(count($cc) > 0){
                    $mailer->bcc($cc);
                }
                
                $mailer->send(new OrderCreated($user,$invoice,$invoice->details,$payment_method,$complete_path,$bilLogo));

            }

        }catch(Exception $e){

            print_r($e);
                //Nothing doing after catching email fail
        }






                //$html = $this->order_receipt($user_id , $v_id, $store_id, $invoice->invoice_id);
                //$pdf = PDF::loadHTML($html);

                /*$current_invoice_name = '2019-04-171555452284_31_1.pdf';
                $path          =  storage_path();
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

                }*/
                


    }//End of OrderEmail


    public function order_qr_code(Request $request)
    {
        $order_id = $request->order_id;
        $qrCode = new QrCode($order_id);
        header('Content-Type: image/png');
        echo $qrCode->writeString();
    }

    public function order_pre_verify_guide(Request $request){
        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id; 
        $order_id = $request->order_id; 
        $o_id = Order::where('order_id', $order_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->first();
        $message_data = [];
        $message_data['title'][] = ['message' => 'Thank You for Shopping!'];
        $message_data['body'][] = [ 'message' => 'Please proceed with your purchase to'];
        if($o_id->qty <= 5 ){
            $message_data['body'][] = [ 'message' => 'The exit and show your'];
            $message_data['body'][] = [ 'message' => 'QR Receipt to the staff'];
        }else if($o_id->qty > 5 && $o_id->qty <=15 ){
            $message_data['body'][] = [ 'message' => 'ZWING Packing Zone 5' , 'bold_flag' => true ];
            $message_data['body'][] = [ 'message' => 'near Aisle 5'];
        }else{
            $message_data['body'][] = [ 'message' => 'ZWING Express Counter' , 'bold_flag' => true , 'italic_flag' => true ];
            $message_data['body'][] = [ 'message' => 'for packing'];
        }



        return response()->json(['status' => 'pre_verify_screen', 'message' => 'Order Details Details', 'data' => $message_data]);


    }


    public function order_details(Request $request)
    {
        // For Order POS
        if($request->has('billing_mode') && $request->billing_mode == 'order') {
            $orderCon = new OrderController;
            return $orderCon->orderDetails($request);
        }
        $v_id           = $request->v_id;
        $c_id           = $request->c_id;
        $store_id       = $request->store_id;
        $invoice_id     = $request->order_id;
        $store_db_name  = $this->store_db_name($store_id);
        $trans_from     = $request->trans_from;
        $cart_qty_total = 0;
        $return_request = 0;
        $total_tax_inc  = 0;
        $total_tax_exc  = 0;
        $orderType = 'invoice';
        $tatalItemLevelManualDiscount=0;
        $diffrentStore = false;

        /*Check return request*/
        if($request->has('return_request')){
            if($request->return_request == 1){
                $return_request = 1;
            }
        }
        /*Exist Vu_id or C_id*/
        $vu_id = 0;
        if ($request->has('vu_id')) {
            $vu_id = $request->vu_id;
        } else if ($request->has('c_id')) {
            $c_id = $request->c_id;
        }

        $where  = array('invoice_id'=>$invoice_id,
            'v_id'      => $v_id,
            'store_id'  => $store_id);
        
        if($request->has('return_request') && $request->return_request == 1){
            $where['user_id'] = $c_id;
        }else{
            if ($vu_id > 0) {
                $where['vu_id'] = $vu_id;
            } else {
                $where['user_id'] = $c_id;
            }
        }

        /*Get Invoice Row*/
        $order  = Invoice::where($where)->first();
        if(empty($order)) {
            $order  = Invoice::where([ 'v_id' => $v_id, 'invoice_id' => $invoice_id ])->first();
            if(!empty($order)) {
                $diffrentStore = true;
            } else {
                return response()->json(['status' => 'pre_verify_screen', 'message' => 'Order Details Details']);
            }
        }
        $stores = $order->store;
        // dd($diffrentStore);

        $item_qty        = 0;
        $c_id            = $order->user_id;
        $user_api_token  = $order->user->api_token;
        $customer_number = $order->user->mobile;
        $payments        = $order->payvia;    //->method; Payment method
        $payment_via     = $payments->pluck('method')->all();    //->method; Payment method
        $tempMethod=[];
        foreach ($payment_via as $key => $value) {
            if($value == 'voucher_credit'){
                $tempMethod[] = 'Store Credit';
            }else{

                $tempMethod[] = ucfirst(strtolower(str_replace('_', ' ', $value)));
            }
        }
        $payment_via = $tempMethod;
        //dd($payment_via);

        $cart_data           = array();
        $return_req_process  = array();
        $return_req_approved = array();
        $product_data        = [];
        $tax_total           = 0;
        $cart_qty_total      = 0;

        /*Get Invoice Detail*/
        $carts = InvoiceDetails::where('t_order_id', $order->id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->get();
        if($diffrentStore) {
            $carts = InvoiceDetails::where('t_order_id', $order->id)->where('v_id', $v_id)->where('store_id', $order->store_id)->where('user_id', $c_id)->get();
        }
        // dd($carts);

        //$cart_qty_total = $carts->sum('qty');
        $sub_total      = $carts->sum('subtotal');
        $discount       = $carts->sum('discount');
        $discount_total = $discount+$carts->sum('manual_discount')+$carts->sum('lpdiscount')+$carts->sum('coupon_discount');
        $discount_total = round($discount_total,2);
        $employee_discount = $carts->sum('employee_discount');
        $manual_discount = $carts->sum('manual_discount');
        $net_amount    = $carts->sum('net_amount');
        $extra_charge    = $carts->sum('extra_charge');
        $total          = $carts->sum('total');
        $tax_total      = $carts->sum('tax');
        //$total     = $sub_total+$tax_total;
        $bill_buster_discount = 0;
        $tax_details          = [];

        $data = [];
        //For Return operation only
        $return_items     = [];
        $return_item_ids  = [];
        $checkItem = collect([]);
        if($order->transaction_type == 'sales'){
            //echo $order_id     = $order->invoice_id;
            $return_order = DB::table('orders as o')
            ->join('order_details as od', 'od.t_order_id', 'o.od_id')
            ->where('o.ref_order_id' , $order->invoice_id)
            ->where('o.transaction_type','return')
            ->selectRaw('sum(od.`qty`) as sum, od.*')
            ->groupBy('item_id','unit_mrp','batch_id','serial_id')
            ->get();

           /* $return_order = DB::table('invoice as inv')
                            ->join('invoice_details as inv_d', 'inv_d.t_order_id', 'inv.id')
                            ->where('inv.ref_order_id' , $order_id)
                            ->where('o.transaction_type','return')
                            ->groupBy('od.item_id')
                            //->select('od.*')
                             ->selectRaw('sum(qty) as sum, od.item_id')
                             ->get();*/

                             $return_items    = $return_order->pluck('sum','item_id')->all();
                             $return_item_ids = $return_order->pluck('item_id')->all();

                         }



            /*Iterate Cart*/
            foreach ($carts as $key => $cart) {
                $offer_data   = json_decode($cart->pdata, true);
                $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $cart->barcode)->first();
                if($bar){

                    $where    = array('v_id'=>$v_id,'vendor_sku_detail_id'=>$bar->vendor_sku_detail_id,'deleted_at' => null);
                    $Item     = VendorSku::select('vendor_sku_detail_id','item_id','has_batch','barcode','variant_combi')->where($where)->first();
                }
                $offerData= isset($offer_data['pdata'])?$offer_data['pdata']:$offer_data;


                // foreach ($offerData as $key => $value) {
                //     if(isset($value['tax'])){
                //         foreach($value['tax'] as $nkey => $tax){
                //             if(isset($tax_details[$tax['tax_code']])){
                //                 $tax_details[$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
                //                 $tax_details[$tax['tax_code']]['tax'] += $tax['tax'] ;
                //             }else{
                //                 $tax_details[$tax['tax_code']] = $tax;
                //             }
                //         }
                //     }

                // }

                $applied_offer   = [];
                $available_offer = [];

                ########################
                ### Carry Bag Start ####
                ########################

                $carr_bag_arr    = [];
                $whereCarry      = array('v_id'=>$v_id,'status'=>'1');
                $carry_bags      = Carry::where($whereCarry)->where('store_id', $store_id)->get();
                if($carry_bags->isEmpty()){
                    $carry_bags = Carry::where($whereCarry)->where('store_id', '0')->get();
                }
                //dd($carry_bags);
                if($carry_bags){
                    $carr_bag_arr = $carry_bags->pluck('barcode')->all();
                }
                $carry_bag_flag = in_array($cart->item_id, $carr_bag_arr);
                ########################
                ### Carry Bag End ####
                ########################

                $product_details = json_decode($cart->section_target_offers);

                /*Get Item Image*/
                $paramImg = array('barcode'=>$cart->barcode,'v_id'=>$v_id);
                $config   =  $this->cartconfig;

                $getimages  = $config->getItemImage($paramImg);
                $product_default_image = $getimages['single_image'];
                $return_qty = 0;
                if($cart->transaction_type == 'return'){
                    $return_qty = $cart->qty;
                }


                $request    = [];
                $return_qty = 0;
                if($order->transaction_type == 'sales') {
                $checkItem = $return_order->where('item_id', $cart->item_id)
                                       ->where('unit_mrp', $cart->unit_mrp)
                                       ->filter(function($item) use ($cart) {
                                            return $item->batch_id == $cart->batch_id && $item->serial_id == $cart->serial_id;
                                        });
                }
                if($checkItem->count() == 1){
                    // $request = $return_order->where('item_id', $cart->item_id);
                    $request = $checkItem->values();   

                    foreach($request as $req){
                        if($req->status == 'approved'){
                            $return_qty += $req->qty;
                        }

                        if($req->status == 'process'){
                            $return_flag = true;

                        }
                    }

                }

            //$product_data['serial']   = $currentStock;
            $product_data['return_flag']     = ($return_qty > 0)?true:false;
            $product_data['return_qty']      = (string)$return_qty;
            $product_data['carry_bag_flag']  = $carry_bag_flag;
            $product_data['isProductReturn'] = ($return_qty > 0)?true:false;
            $product_data['p_id']            = (int)$cart->item_id;
            $product_data['category']        = ($product_details->category)?$product_details->category:'';
            $product_data['brand_name']      = ($product_details->brand_name)?$product_details->brand_name:'';
            $product_data['sub_categroy']    = ($product_details->sub_categroy)?$product_details->sub_categroy:'';
            $product_data['whishlist']       = 'No';
            $product_data['weight_flag']     = ($cart->weight_flag == 1)?true:false;
            $product_data['p_name']          = $cart->item_name;
            $product_data['offer']           = $product_details->offer;
            $product_data['offer_data']      = $product_details->offer_data;
            //$product_data['qty'] = '';
            $product_data['multiple_price_flag'] = $product_details->multiple_price_flag;
            $product_data['multiple_mrp']    = $product_details->multiple_mrp;
            $product_data['r_price']         = (string)$cart->subtotal;
            $product_data['s_price']         = (string)$cart->total;
            $product_data['batch_id']         = (string)$cart->batch_id;
            $product_data['serial_id']         = (string)$cart->serial_id;
            $product_data['unit_mrp']        = $product_details->unit_mrp;
            $product_data['uom']             = $product_details->uom;
            $product_data['discount']        = format_number($cart->discount + $cart->manual_discount + $cart->lpdiscount + $cart->coupon_discount + $cart->bill_buster_discount);
            $product_data['net_amount'] = format_number($cart->net_amount);
            $product_data['extra_charge'] = format_number($cart->extra_charge);
            /*if(!empty($offer_data['applied_offers']) ){
                $product_data['r_price'] = format_number($offer_data['r_price']);
                $product_data['s_price'] = format_number($offer_data['s_price']);
            }*/

            $product_data['varient']     = '';
            $product_data['images']      = $product_default_image;
            $product_data['description'] = '';
            $product_data['deparment']   = '';
            $product_data['barcode']     = $cart->barcode;

            //$tax_total = $tax_total +  $tax_amount ;


            $tax_amount = $cart->tax;
            if($cart->weight_flag){
                $cart_qty_total =  $cart_qty_total + 1;
            }else{

                if($cart->plu_barcode){
                    $cart_plu_qty = $cart->qty;
                    $cart_plu_qty = explode('.',$cart_plu_qty);
                    //dd($cart_plu_qty);
                    if(count($cart_plu_qty) > 1 ){
                        $cart_qty_total =  $cart_qty_total + 1;
                    }else{
                        $cart_qty_total =  $cart_qty_total + $cart->qty;    
                    }
                }else{
                    $cart_qty_total =  $cart_qty_total + $cart->qty; 
                }   
            }
            
            $return_product_qty = $cart->qty;
            if($checkItem->count() == 1){
                $return_product_qty = $cart->qty - $checkItem->first()->sum;
            }
            // manual discount
             $itemLevelmanualDiscount=0;
             if($cart->item_level_manual_discount!=null){
                $iLmd = json_decode($cart->item_level_manual_discount);
                $itemLevelmanualDiscount= (float)$iLmd->discount;
             }
            $cart_data[] = array(
                'cart_id'       => $cart->invoice_id,
                'product_data'  => $product_data,
                'amount'        => $cart->total ,
                'qty'           => $cart->qty,
                'return_product_qty' => $return_product_qty,
                'tax_amount'    => $tax_amount,
                'delivery'      => $cart->delivery,
                'item_flag'     => 'NORMAL',
                'salesman_id'   => $cart->salesman_id,
                'discount'      => format_number($cart->discount + $cart->manual_discount + $cart->lpdiscount + $cart->coupon_discount + $cart->bill_buster_discount+$itemLevelmanualDiscount)
            );
            if($itemLevelmanualDiscount>0){
              $tatalItemLevelManualDiscount  += $itemLevelmanualDiscount;
            }

            //$tax_total = $tax_total +  $tax_amount ;

            //This code is added for displayin andy return items
            if(in_array($cart->item_id, $return_item_ids)){
                //dd($request);
                foreach($request as $req){
                    $product_data['r_price'] = format_number($req->subtotal);
                    $product_data['s_price'] = format_number($req->total);

                    if($req->status == 'process'){

                        $return_req_process[] = array(
                            'cart_id'       => $cart->invoice_id,
                            'product_data'  => $product_data,
                            'amount'        => $req->total ,
                            'qty'           => $req->qty,
                            //'return_product_qty' => $cart->qty,
                            'tax_amount'    => $req->tax,
                            'delivery'      => $cart->delivery,
                            'item_flag'     => 'RETURN_PROCESS'
                        );
                    }

                    if($req->status == 'approved'){

                        $return_req_approved[] = array(
                            'cart_id'       => $cart->invoice_id,
                            'product_data'  => $product_data,
                            'amount'        => $req->total ,
                            'qty'           => $req->qty,
                            //'return_product_qty' => $cart->qty,
                            'tax_amount'    => $req->tax,
                            'delivery'      => $cart->delivery,
                            'item_flag'     => 'RETURN_APPROVED'
                        );
                    }

                }
            }

            $tdata         = json_decode($cart->tdata);

            $tax_type = null;
            if(isset($Item->tax_type) ){
                $tax_type = $Item->tax_type;
            }else{
                $tax_type = $Item->Item->tax_type;
            }
                
            if($tax_type == 'INC'){
                $total_tax_inc   += $tdata->tax;
            }
            if( $tax_type == 'EXC'){
                $total_tax_exc   += $tdata->tax;
            }

        }

        if($employee_discount > 0.00){
            $total = $total - $employee_discount;
        }
        $bill_buster_discount = $order->bill_buster_discount;
        if($bill_buster_discount > 0.00){
            $total = $total - $bill_buster_discount;
        }
        $tax_total  = 0;
        // $total = $total + $tax_total;
        $saving     = (float)$discount + (float)$bill_buster_discount;
        $address = (object)array();
        $o_id = Order::where(['order_id'=>$order->ref_order_id,'v_id'=>$v_id,'store_id'=>$store_id])->first();
        if($diffrentStore) {
            $o_id = Order::where([ 'order_id' => $order->ref_order_id, 'v_id' => $v_id, 'store_id' => $order->store_id ])->first();
        }
        if($o_id->address_id > 0){
            $address = $o_id->user->address;
        }

        //$sb_total = ($sub_total-($total_tax_exc+$total_tax_inc));
        if($total_tax_exc >0){
            $sb_total = $sub_total; 
        }else{
           $sb_total = ($sub_total-($total_tax_exc+$total_tax_inc));    
        }


        $bill_summary=[];

        $bill_summary[] = [ 'name' => 'sub_total' , 'display_text' => 'Sub Total' ,'display_name' => 'Sub Total' , 'value' => (string)format_number($sb_total),'sign'=>'' ];
        if($discount>0){
        $bill_summary[] = [ 'name' => 'discount' , 'display_text' => 'Discount' ,'display_name' => 'Discount' , 'value' => (string)format_number($discount) ];
         }
        if($manual_discount > 0 || $tatalItemLevelManualDiscount>0){

            $mdiscountType = [];
            if($tatalItemLevelManualDiscount > 0) {
                $mdiscountType[] = ['name' => 'item_level', 'value' => format_number($tatalItemLevelManualDiscount)];
            }
            if($manual_discount > 0) {
                $mdiscountType[] = ['name' => 'bill_level', 'value' => format_number($manual_discount)];   
            }
            $bill_summary[] = [ 'name' => 'manual_discount' , 'display_text' => 'Manual Discount' ,'display_name' => 'Manual Discount' , 'value' => format_number($manual_discount+$tatalItemLevelManualDiscount),'sign'=>'-','type' => $mdiscountType];
        }

        //$bill_summary[] = [ 'name' => 'bill_buster_discount' , 'display_text' => 'Bill Discount' , 'value' => (string)format_number($bill_buster_discount) ];
        if($bill_buster_discount >0){
        $bill_summary[] = [ 'name' => 'bill_buster_discount' , 'display_text' => 'Bill Discount' ,'display_name' => 'Bill Discount' , 'value' => (string)format_number($bill_buster_discount),'sign'=>'-' ];
        }
       
        if($total_tax_exc > 0){
            $bill_summary[] = [ 'name' => 'tax_total' , 'display_text' => 'Tax Total (Excluded)' ,'display_name' => 'Tax Total (Excluded)' , 'value' => (string)format_number($total_tax_exc),'sign'=>'' ];
        }
        if($total_tax_inc > 0){
            $bill_summary[] = [ 'name' => 'tax_total' , 'display_text' => 'Tax Total (Included)' ,'display_name' => 'Tax Total (Included)' , 'value' => (string)format_number($total_tax_inc),'sign'=>'' ];
        }

        if($extra_charge > 0){
            $bill_summary[] = [ 'name' => 'extra_charge' , 'display_text' => 'Extra Charge' ,'display_name' => 'Extra Charge' , 'value' => (string)format_number($extra_charge),'sign'=>'+' ];
        }

        // Round Off Calculation
        if (!empty(getRoundValue($total))) {

            $bill_summary[] = [ 'name' => 'roundoff' , 'display_text' => 'Round Off' ,'display_name' => 'Round Off' ,'item_type'=>"", 'value' => abs(getRoundValue($total)), 'sign' => getRoundValue($total) < 0 ? '-' : '+' ];
        }
        $total  = round($total);

        $bill_summary[] = [ 'name' => 'total' , 'display_text' => 'Total' ,'display_name' => 'Total' , 'value' => (string)round($total),'sign'=>'' ];

        foreach ($payments->groupBy('method') as $key => $payment) 
        {
            if ($key == 'voucher_credit') {
                $bill_summary[] = [ 'name' => 'payment_'.$key , 'display_text' => 'Store Credit' ,'display_name' => 'Store Credit' , 'value' => (string)format_number($payment->sum('amount')) ,'mop_flag' => '1'];
            } else {
                $bill_summary[] = [ 'name' => 'payment_'.$key , 'display_text' => ucfirst($key) ,'display_name' => ucfirst($key) , 'value' => (string)format_number($payment->sum('amount')) ,'mop_flag' => '1' ];
            }
        }

        $return_reasons = [];
        if($return_request){
            $return_reasons = Reason::select('id','description')->where('type','RETURN')->where('v_id', $v_id )->get();
        }

        $customer = User::select('first_name','last_name')->where('c_id', $order->user_id)->first();
        $group = DB::table('customer_group_mappings')->where('c_id', $order->user_id)->first();
        $group_code = 'REGULAR';
        if($group){
            $group_code = DB::table('customer_groups')->where('id', $group->group_id)->first()->code;
        }

        $print_url  =  env('API_URL').'/order-receipt/'.$c_id.'/'.$v_id.'/'.$store_id.'/'.$invoice_id;

        //Temporary condition need to remove
        if($trans_from == 'ANDROID_VENDOR' && $group_code != 'DUMMY'){
            $group_code = 'REGULAR';
        }

        $orderC = new OrderController;
        $mainOrder = Order::where([ 'v_id' => $v_id, 'order_id' => $order->ref_order_id, 'store_id' => $store_id  ])->first();
        $summary = $orderC->getOrderResponse([ 'order' => $mainOrder, 'v_id' => $v_id, 'trans_from' => $trans_from ]);
        $billSummary = collect($summary['summary']);
        $amountDue = $billSummary->where('name', 'amount_due')->first();
        $pay_method = $billSummary->filter(function($item) { return array_key_exists('mop_flag', $item) && $item['mop_flag'] == '1'; })->values();


        return response()->json(['status'   => 'order_details', 'message' => 'Order Details Details', 
            'transaction_type'  =>  $order->transaction_type,
            'mobile'               => $order->user->mobile,
            'payment_method'       =>  implode(',',$payment_via),
            'return_reasons'        => $return_reasons,
            'data'                 => $cart_data,
            'return_req_process'   => $return_req_process,
            'return_req_approved'  => $return_req_approved,
            'product_image_link'   => product_image_link().$v_id.'/',
            'store_header_logo'    => store_logo_link().'spar_logo_round.png',
                        //'offer_data' => $global_offer_data,
            'return_request_flag'  => ($return_request)?true:false,
            'bags'                 => [], 
            'carry_bag_total'      => '0.00',
            'sub_total'            => (format_number($sub_total))?format_number($sub_total):'0.00', 
            'tax_total'            => (format_number($tax_total))?format_number($tax_total):'0.00',
            'tax_details'          => $tax_details,
            'bill_buster_discount' => (format_number($bill_buster_discount))?format_number($bill_buster_discount):'0.00',
            'discount'             => (format_number($discount))?format_number($discount):'0.00', 
            'net_amount'           => (format_number($net_amount))?format_number($net_amount):'0.00', 
            'extra_charge'         => (format_number($extra_charge))?format_number($extra_charge):'0.00', 
                        //'grand_total' => (format_number($grand_total))?format_number($grand_total):'0.00', 
            'date'                 => $order->date, 
            'time'                 => $order->time,
            'order_id'             => $order->invoice_id, 
            'total'                => format_number($total), 
                        //'total_label' => 'Total (With Tax)',
            'cart_qty_total'       => (string)$cart_qty_total,
            'saving'               => (format_number($saving))?format_number($saving):'0.00',
            'store_address'        => $stores->address1.' '.$stores->address2.' '.$stores->state.' - '.$stores->pincode,
            'store_timings'       => $stores->opening_time.' '.$stores->closing_time,
            'store_name'          => $stores->name,
            'delivered'           => $stores->delivery , 
            'address'             => $address,
            'user_api_token'      => $user_api_token,
            'bill_summary'        => $bill_summary,
            'bill_remark'         => $order->remark,
            'customer_name'       => $customer->first_name.' '.$customer->last_name,
            'customer_group_code' => $group_code,
            'print_url'           => $print_url,
            'c_id'                => $c_id,
            'order_type'          => $orderType,
            'amount_due'          => empty(format_number($amountDue['value']))? '0.00' : format_number($amountDue['value'])
             ],200);


    }//End of order_details


    public function order_details_old(Request $request)
    {
        $v_id     = $request->v_id;
        $store_id = $request->store_id; 
        $order_id = $request->order_id; 
        $vu_id    = 0;
        if($request->has('vu_id')){
            $vu_id = $request->vu_id;
        }else if($request->has('c_id')){
            $c_id = $request->c_id;
        }

        $stores =  DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();
        $o_id   = Invoice::where('invoice_id', $order_id)->where('v_id', $v_id)->where('store_id', $store_id)->get();
        if($vu_id > 0){
            $o_id = $o_id->where('vu_id', $vu_id)->first();
        }else{
            $o_id = $o_id->where('user_id', $c_id)->first();
        }
        $c_id = $o_id->user_id;
        
        $order_num_id = Invoice::where('invoice_id', $order_id)->first();


        //echo $order_num_id->invoice_id;
        $return_request = DB::table('return_request')->where('order_id', $order_num_id->invoice_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('confirm','1')->get();

        //dd($return_request);

        $return_item_ids     = [];
        if(!$return_request->isEmpty()){
            $return_item_ids = $return_request->pluck('item_id')->all();
        }

        $cart_data           = array();
        $return_req_process  = array();
        $return_req_approved = array();
        $product_data        = [];
        $tax_total           = 0;
        $cart_qty_total      = 0;
        
        //dd($o_id);
        $carts = InvoiceDetails::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('t_order_id', $o_id->id)->get();

        //dd($carts);
        $sub_total = $carts->sum('subtotal');
        $discount  = $carts->sum('discount');
        $employee_discount = $carts->sum('employee_discount');
        $total     = $carts->sum('total');
        $tax_total = $carts->sum('tax');
        //$total     = $sub_total+$tax_total;
        $bill_buster_discount = 0;
        $tax_details = [];


        $return_items = [];


            // $return_order = DB::table('invoices as in')
            //                 ->join('invoice_details as ind', 'ind.t_order_id', 'in.id')
            //                 ->where('in.invoice_id' , $order_num_id->invoice_id)
            //                 ->where('in.transaction_type','return')
            //                  ->selectRaw('sum(qty) as sum, ind.*')
            //                 ->get();

            // $return_items = $return_order->pluck('sum','item_id')->all();

        if($order_num_id->transaction_type == 'sales'){
            $return_order = DB::table('orders as o')
            ->join('order_details as od', 'od.t_order_id', 'o.od_id')
            ->where('o.ref_order_id' , $order_num_id->invoice_id)
            ->where('o.transaction_type','return')
            ->selectRaw('sum(qty) as sum, od.*')
            ->get();

            $return_items = $return_order->pluck('sum','item_id')->all();
        }

        
        foreach ($carts as $key => $cart) {

           // dd($cart);
            //$res = OrderDetails::where('t_order_id', $cart->od_id)->first();

            //dd($res);
            $offer_data = json_decode($cart->pdata, true);
            
            foreach ($offer_data['pdata'] as $key => $value) {
                if(isset($value['tax'])){
                    foreach($value['tax'] as $nkey => $tax){
                        if(isset($tax_details[$tax['tax_code']])){
                            $tax_details[$tax['tax_code']]['taxable_amount'] += $tax['taxable_amount'] ;
                            $tax_details[$tax['tax_code']]['tax'] += $tax['tax'] ;
                        }else{
                            $tax_details[$tax['tax_code']] = $tax;
                        }
                    }
                }
                
            }

            $available_offer = [];
            foreach($offer_data['available_offer'] as $key => $value){

                $available_offer[] =  ['message' => $value ];
            }
            $offer_data['available_offer'] = $available_offer;
            $applied_offer = [];
            foreach($offer_data['applied_offer'] as $key => $value){

                $applied_offer[] =  ['message' => $value ];
            }
            $offer_data['applied_offer'] = $applied_offer;
            //dd($offer_data);

            //Counting the duplicate offers
            $tempOffers = $offer_data['applied_offer'];
            for($i=0; $i<count($offer_data['applied_offer']); $i++){
                $apply_times = 1 ;
                $apply_key = 0;
                for($j=$i+1; $j<count($tempOffers); $j++){

                    if(isset($offer_data['applied_offer'][$j]['message']) && $tempOffers[$i]['message'] == $offer_data['applied_offer'][$j]['message']){
                        unset($offer_data['applied_offer'][$j]);
                        $apply_times++;
                        $apply_key = $j;
                    }

                }
                if($apply_times > 1 ){
                    $offer_data['applied_offer'][$i]['message'] = $offer_data['applied_offer'][$i]['message'].' - ' .$apply_times.' times';
                }

            }
            $offer_data['available_offer'] = array_values($offer_data['available_offer']);
            $offer_data['applied_offer'] = array_values($offer_data['applied_offer']);

            $carr_bag_arr =  [ '114903443', '114952448' ,'114974444'];
            $carry_bag_flag = in_array($cart->item_id, $carr_bag_arr);

            $request = [];
            $return_flag = false;
            $return_qty = 0;
            if(in_array($cart->item_id, $return_item_ids)){
                $request = $return_request->where('item_id', $cart->item_id);   

                foreach($request as $req){
                    if($req->status == 'approved'){
                        $return_qty += $req->qty;
                    }

                    if($req->status == 'process'){
                        $return_flag = true;

                    }
                }

            }

            $product_data['return_flag'] = $return_flag;
            $product_data['return_qty'] = (string)$return_qty;
            $product_data['carry_bag_flag'] = $carry_bag_flag;
            $product_data['isProductReturn'] = ($cart->transaction_type == 'return')?true:false;
            $product_data['p_id'] = (int)$cart->item_id;
            $product_data['category'] = '';
            $product_data['brand_name'] = '';
            $product_data['sub_categroy'] = '';
            $product_data['whishlist'] = 'No';
            $product_data['weight_flag'] = ($cart->weight_flag == 1)?true:false;
            $product_data['p_name'] = $cart->item_name;
            $product_data['offer'] = (count($offer_data['applied_offer']) > 0)?'Yes':'No';
            $product_data['offer_data'] = [ 'applied_offers' => $offer_data['applied_offer'] , 'available_offers' =>$offer_data['available_offer']  ];
            //$product_data['qty'] = '';
            $product_data['multiple_price_flag'] = $offer_data['multiple_price_flag'];
            $product_data['multiple_mrp'] = $offer_data['multiple_mrp'];
            $product_data['r_price'] = format_number($offer_data['r_price']);
            $product_data['s_price'] = format_number($offer_data['s_price']);
            $product_data['unit_mrp'] = format_number($cart->unit_mrp);
            /*if(!empty($offer_data['applied_offers']) ){
                $product_data['r_price'] = format_number($offer_data['r_price']);
                $product_data['s_price'] = format_number($offer_data['s_price']);
            }*/

            $product_data['varient'] = '';
            $product_data['images'] = 'zwing_default.png';
            $product_data['description'] = '';
            $product_data['deparment'] = '';
            $product_data['barcode'] = $cart->barcode;

            //$tax_total = $tax_total +  $tax_amount ;
            $tax_amount = $cart->tax;
            if($cart->weight_flag == '1'){
                $cart_qty_total =  $cart_qty_total + 1;
            }else{

                if($cart->plu_barcode){
                    $cart_plu_qty = $cart->qty;
                    $cart_plu_qty = explode('.',$cart_plu_qty);
                    //dd($cart_plu_qty);
                    if(count($cart_plu_qty) > 1 ){
                        $cart_qty_total =  $cart_qty_total + 1;
                    }else{
                        $cart_qty_total =  $cart_qty_total + $cart->qty;    
                    }
                }else{
                    $cart_qty_total =  $cart_qty_total + $cart->qty; 
                }   
            }
            
            $return_product_qty = $cart->qty;
            if (isset($return_items[$cart->item_id]) ) {

                $return_product_qty = $cart->qty - $return_items[$cart->item_id];
            }

            $cart_data[] = array(
                'cart_id'       => $cart->cart_id,
                'product_data'  => $product_data,
                'amount'        => $cart->total ,
                'qty'           => $cart->qty,
                'return_product_qty' => $return_product_qty,
                'tax_amount'    => $tax_amount,
                'delivery'      => $cart->delivery,
                'item_flag'     => 'NORMAL',
                'salesman_id'   => $cart->salesman_id
            );
            //$tax_total = $tax_total +  $tax_amount ;

            //This code is added for displayin andy return items
            if(in_array($cart->item_id, $return_item_ids)){

                //dd($request);
                foreach($request as $req){
                    $product_data['r_price'] = format_number($req->subtotal);
                    $product_data['s_price'] = format_number($req->total);

                    if($req->status == 'process'){

                        $return_req_process[] = array(
                            'cart_id'       => $cart->cart_id,
                            'product_data'  => $product_data,
                            'amount'        => $req->total ,
                            'qty'           => $req->qty,
                            //'return_product_qty' => $cart->qty,
                            'tax_amount'    => $req->tax,
                            'delivery'      => $cart->delivery,
                            'item_flag'     => 'RETURN_PROCESS'
                        );
                    }

                    if($req->status == 'approved'){

                        $return_req_approved[] = array(
                            'cart_id'       => $cart->cart_id,
                            'product_data'  => $product_data,
                            'amount'        => $req->total ,
                            'qty'           => $req->qty,
                            //'return_product_qty' => $cart->qty,
                            'tax_amount'    => $req->tax,
                            'delivery'      => $cart->delivery,
                            'item_flag'     => 'RETURN_APPROVED'
                        );
                    }

                }
            }
        }

        if($employee_discount > 0.00){
            $total = $total - $employee_discount;
        }
        $bill_buster_discount = $o_id->bill_buster_discount;
        if($bill_buster_discount > 0.00){
            $total = $total - $bill_buster_discount;
        }

        $tax_total = 0;
        //$total = $total + $tax_total;
        $saving = $discount + $bill_buster_discount;

        $bags = DB::table('user_carry_bags')->select('vendor_carry_bags.Name','user_carry_bags.Qty','vendor_carry_bags.BAG_ID')->selectRaw('user_carry_bags.Qty * vendor_carry_bags.Price as Price')->leftJoin('vendor_carry_bags', 'user_carry_bags.Bag_ID', '=', 'vendor_carry_bags.BAG_ID')->where('user_carry_bags.V_ID', $v_id)->where('user_carry_bags.Store_ID', $store_id)->where('user_carry_bags.User_ID', $c_id)->where('user_carry_bags.Order_ID', $order_num_id['o_id'])->get();
        $bprice = DB::table('user_carry_bags')->selectRaw('SUM(user_carry_bags.Qty * vendor_carry_bags.Price) as Price')->leftJoin('vendor_carry_bags', 'user_carry_bags.Bag_ID', '=', 'vendor_carry_bags.BAG_ID')->where('user_carry_bags.V_ID', $v_id)->where('user_carry_bags.Store_ID', $store_id)->where('user_carry_bags.User_ID', $c_id)->where('user_carry_bags.Order_ID', $order_num_id['o_id'])->first();
        // $cart_data['bags'] = $bags;
        
        if(empty($bprice->Price)) {
            $carry_bag_total = 0;
        } else {
            $carry_bag_total = $bprice->Price;
        }
        $store = DB::table('stores')->select('delivery')->where('store_id', $store_id)->where('v_id', $v_id)->first();
        //$total = (int)$sub_total + (int)$carry_bag_total;
        //$less = array_sum($saving) - (int)$sub_total;
        $address = (object)array();
        if($o_id->address_id > 0){
            $address = Address::where('c_id', $c_id)->where('deleted_status', 0)->where('id',$o_id->address_id)->first();
        }

        $paymentMethod = Payment::where('v_id', $o_id->v_id)->where('store_id',$o_id->store_id)->where('order_id',$o_id->order_id)->get()->pluck('method')->all() ;
        
        return response()->json(['status' => 'order_details', 'message' => 'Order Details Details', 
            'mobile' => $o_id->user->mobile,
            'payment_method'=>  implode(',',$paymentMethod),
            'data' => $cart_data,
            'return_req_process' => $return_req_process,
            'return_req_approved' => $return_req_approved,
            'product_image_link' => product_image_link(),
            'store_header_logo' => store_logo_link().'spar_logo_round.png',
            //'offer_data' => $global_offer_data,
            'return_request_flag' => ($return_request)?true:false,
            'bags' => $bags, 
            'carry_bag_total' => (format_number($carry_bag_total))?format_number($carry_bag_total):'0.00',
            'sub_total' => (format_number($sub_total))?format_number($sub_total):'0.00', 
            'tax_total' => (format_number($tax_total))?format_number($tax_total):'0.00',
            'tax_details' => $tax_details,
            'bill_buster_discount' => (format_number($bill_buster_discount))?format_number($bill_buster_discount):'0.00',
            'discount' => (format_number($discount))?format_number($discount):'0.00', 
            //'grand_total' => (format_number($grand_total))?format_number($grand_total):'0.00', 
            'date' => $o_id->date, 
            'time' => $o_id->time,
            'order_id' => $order_id, 
            'total' => format_number($total), 
            //'total_label' => 'Total (With Tax)',
            'cart_qty_total' => (string)$cart_qty_total,
            'saving' => (format_number($saving))?format_number($saving):'0.00',
            'store_address' => $stores->address1.' '.$stores->address2.' '.$stores->state.' - '.$stores->pincode,
            'store_timings' => $stores->opening_time.' '.$stores->closing_time,
            'delivered' => $store->delivery , 
            'address'=> $address,
            'c_id' => $c_id ],200);

    }
    
    public function order_verify_status(Request $request){

        $v_id     = $request->v_id;
        $c_id     = $request->c_id;
        $store_id = $request->store_id; 
        $order_id = $request->order_id; 
        
        $vu_id = 0;
        if($request->has('vu_id')){
            $vu_id = $request->vu_id;
        }

        $order = Order::select('order_id', 'v_id' , 'store_id', 'date','time','total','verify_status','verify_status_guard')->where('user_id',$c_id)->where('order_id', $order_id)->where('v_id',$v_id)->where('store_id',$store_id)->first();

        if($vu_id > 0){
            $order->verify_status = '1';
            $order->verify_status_guard = '1';    
            $order->save();
        }
        
        $message = '';
        if($order->verify_status !='1'){    
            $message = 'Verification is pending, Please visit to nearest staff for verification.';
        }else{
            $message = 'verification successfully';
        }

        $verification_data = [
            'cashier_verify_status' => ($order->verify_status == '1')?true:false ,
            'guard_verify_status' => ($order->verify_status_guard == '1')?true:false,
            'order_id' => $order->order_id,
            'amount' => $order->total,
            'v_id' => $order->v_id,
            'store_id' =>  $order->store_id,
            'date' => $order->date,
            'time' => $order->time ];

            return response()->json(['status' => 'verification', 'message' => $message ,'verification' => $verification_data ], 200);
        }

        public function get_print_receipt_json(Request $request){

            $v_id       = $request->v_id;
            $store_id   = $request->store_id; 
            $c_id       = $request->c_id;
            $order_id   = $request->order_id;
            $userfor    = !empty($request->userfor)?$request->userfor:'';
            $product_data= [];
            $gst_list    = [];
            $final_gst   = [];
            $detatch_gst = [];
            $rounded     = 0;
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

                

                $first_row  =  [
                    array('value'=>$count,'length'=>3),
                    array('value'=>$value->item_name,'length'=>8),
                    array('value'=>' '.$rate,'length'=>6),
                    array('value'=>$value->qty,'length'=>5),
                    array('value'=>$value->tax,'length'=>5),
                    array('value'=> $value->total,'length'=>7)
                ];
                $second_row =  [
                    array('value'=>' '.$value->barcode,'length'=>18),
                    array('value'=>$tdata->hsn,'length'=>10),
                    array('value'=>$value->discount+$value->manual_discount + $value->bill_buster_discount,'length'=>6)
                ];

                $product_data[]  = (object)['first_row'=>$first_row,'second_row'=>$second_row];

                $gst_list[] = [
                    'name'              => $tdata->tax_name,
                    'wihout_tax_price'  => $tdata->taxable,
                    'tax_amount'        => $tdata->tax,
                    'cgst'              => $tdata->cgstamt,
                    'sgst'              => $tdata->sgstamt,
                    'cess'              => $tdata->cessamt
                ];
                $count++;        
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

         $tt = array('first_row'=>[
            array('value'=>$value->name,'length'=>9),
            array('value'=>' '.$value->taxable,'length'=>11),
            array('value'=>$value->cgst,'length'=>7),
            array('value'=>$value->sgst,'length'=>7) 
        ] );




         $detatch_gst[] = $tt;
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
        $paymeny_method = Mop::where('code', $payment->method)->first();
                // dd($paymeny_method->name);
        if ($payment->method == 'cash') {
            $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
            // $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->cash_collected-$cashReturn ];
            $mop_list[] = [ 'mode' => $paymeny_method->name, 'amount' => $payment->cash_collected-$cashReturn ];
        } else {
            // $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
            $mop_list[] = [ 'mode' => $paymeny_method->name, 'amount' => $payment->amount ];
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

        ########################
        ####### Print Start ####
        ########################
        //$terms_conditions =  array('(1) Exchange Within 7 days only.','(2) MRP Are Inclusive of Applicable Tax');

    $terms_conditions =  array('1. MRP Are Inclusive of Applicable Tax');
    if($v_id == 18){
        $terms_conditions =  array('1. MRP Are Inclusive of Applicable Tax', '2. Fresh Finds is a venture of Jubilant Consumer Pvt. Ltd.' );
    }

    if($order_details->transaction_type == 'return'){
     $invoice_title     = 'Credit Note';
 }else{
     $invoice_title     = 'Tax invoice';
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
$invoice_date   = date('d-M-Y', strtotime($order_details->created_at))." at ".date('h:i:s A', strtotime($order_details->created_at));
$cashierName    = @$order_details->vuser->first_name.' '.@$order_details->vuser->last_name;
$customerMobile = @$order_details->user->mobile;
$total_discount = (float)$order_details->discount+(float)$order_details->manual_discount+(float)$order_details->bill_buster_discount;
$printArray['separator']     = array('name'=>'-','length'=>20);
$printArray['bill_header'][] = array('label'=>"",'value'=>$store->name,'lalign'=>'center','valign'=>'center');
$printArray['bill_header'][] = array('label'=>"",'value'=>$store->address1,'lalign'=>'left','valign'=>'left');
$printArray['bill_header'][] = array('label'=>"Contact No",'value'=>$store->contact_number,'lalign'=>'left','valign'=>'left');
$printArray['bill_header'][] = array('label'=>"E-mail",'value'=>$store->email,'lalign'=>'left','valign'=>'left');
$printArray['bill_header'][] = array('label'=>"GSTIN",'value'=>$store->gst,'lalign'=>'left','valign'=>'left');
$printArray['bill_header'][] = array('label'=>"CIN",'value'=>$store->cin,'lalign'=>'left','valign'=>'left');


$printArray['invoice_detail'][] = array('label'=>'','title'=>$invoice_title,'lalign'=>'','valign'=>'');
$printArray['invoice_detail'][] = array('label'=>"Invoice No",'value'=>$order_details->invoice_id,'lalign'=>'left','valign'=>'left');
if ($order_details->transaction_type == 'return') {
    $printArray['invoice_detail'][] = array('label'=>"Reference No",'value'=>$refererence_order->ref_order_id,'lalign'=>'left','valign'=>'left');
}
$printArray['invoice_detail'][] = array('label'=>"Date",'value'=>$invoice_date,'lalign'=>'left','valign'=>'left');
$printArray['invoice_detail'][] =array('label'=>"Cashier",'value'=>$cashierName,'lalign'=>'left','valign'=>'left');
$printArray['invoice_detail'][] = array('label'=>"Customer Mobile",'value'=>$customerMobile,'lalign'=>'left','valign'=>'left');


$printArray['cart_header']['first_row'][] = array('name'=>"#",'length'=>3);
$printArray['cart_header']['first_row'][] = array('name'=>"Item",'length'=>8);
$printArray['cart_header']['first_row'][] = array('name'=>"Rate",'length'=>6);
$printArray['cart_header']['first_row'][] = array('name'=>"Qty",'length'=>5);
$printArray['cart_header']['first_row'][] = array('name'=>"Tax",'length'=>5);
$printArray['cart_header']['first_row'][] = array('name'=>"Amount",'length'=>7);

$printArray['cart_header']['second_row'][] = array('name'=>"Barcode",'length'=>18);
$printArray['cart_header']['second_row'][] = array('name'=>"hsn",'length'=>10);
$printArray['cart_header']['second_row'][] = array('name'=>"Disc",'length'=>6);
$cartItem = array();
        /*for($i = 0; $i < count($product_data); $i++) {
        if($i % 2 == 0) {
                $cartItem  = array('first_row'=>[
                    array('value'=>$product_data[$i]['sr_no'],'length'=>3),
                    array('value'=>$product_data[$i]['name'],'length'=>8),
                    array('value'=>' '.$product_data[$i]['rate'],'length'=>6),
                    array('value'=>$product_data[$i]['qty'],'length'=>5),
                    array('value'=>$product_data[$i]['tax_amt'],'length'=>5),
                    array('value'=> $product_data[$i]['tax_amt'],'length'=>7)
                ]);
        } else {
                $cartItem = array('second_row'=>[
                    array('value'=>' '.$product_data[$i]['item_code'],'length'=>18),
                    array('value'=>$taxable_amount?$product_data[$i]['hsn']:'','length'=>10),
                    array('value'=>$product_data[$i]['discount'],'length'=>6)
                    ]);
                }
    
                $printArray['cart_item'][]  = $cartItem;
            }*/

            $printArray['cart_item'] = (object)$product_data;
    //20, 4,14
            $printArray['cart_footer'] = array(array('title'=>'Total','length'=>17),
                array('qty'=>$cart_qty,'length'=>5),
                array( 'total'=>$total_amount,'length'=>12));

            $printArray['grand_total'] = array('lable'=>'','value'=>'Rupee: '.ucfirst(numberTowords(round($order_details->total))),'valign'=>'left','lalign'=>'left'); 


            $printArray['customer_paid'][] = array('lable'=>'Customer Paid','value'=>format_number($customer_paid),'valign'=>'left','lalign'=>'left'); 
            $printArray['customer_paid'][] = array('lable'=>'Balance Refund','value'=>format_number($balance_refund),'valign'=>'left','lalign'=>'left'); 

            $printArray['customer_paid'][] = array('lable'=>'Balance Refund','value'=>format_number($balance_refund),'valign'=>'left','lalign'=>'left'); 

            $printArray['gst_header'][] = array('label'=>'','title'=>'GST Summary','lalign'=>'','valign'=>'');
            $printArray['gst_header']['first_row'][] = array('name'=>"Desc",'length'=>9);
            $printArray['gst_header']['first_row'][] = array('name'=>"Taxable",'length'=>11);
            $printArray['gst_header']['first_row'][] = array('name'=>"CGST",'length'=>7);
            $printArray['gst_header']['first_row'][] = array('name'=>"SGST",'length'=>7);
            $printArray['gst_item'] = $detatch_gst;


            $printArray['order_total'][] =   array('name'=>"Total",'length'=>9);
            $printArray['order_total'][] =   array('name'=>format_number($taxable_amount),'length'=>11);
            $printArray['order_total'][] =   array('name'=>format_number($total_csgt),'length'=>7);
            $printArray['order_total'][] =   array('name'=>format_number($sgst),'length'=>7);

            $printArray['order_summary'][] =   array('label'=>"Saving",'value'=>$total_discount,'lalign'=>'left','valign'=>'left');
            $printArray['order_summary'][] =   array('label'=>"Total Qty",'value'=>$cart_qty,'lalign'=>'left','valign'=>'left');
            $printArray['order_summary'][] =   array('label'=>"Total Sale",'value'=>$total_amount,'lalign'=>'left','valign'=>'left');

            if(!empty($mop_list)) {
                foreach ($mop_list as $mop) {
                 $printArray['payment_meth'][] =   array('label'=>$mop['mode'],'value'=>$mop['amount'],'lalign'=>'left','valign'=>'right');
             }
         }
         $printArray['payable_amt'][] =   array('label'=>'Net Payable','value'=> format_number($net_payable),'lalign'=>'left','valign'=>'right');

         $printArray['bill_footer']['terms_conditions'][] =   array('title'=>'Terms & conditions');
         foreach ($terms_conditions as $term) {
           $printArray['bill_footer']['terms_conditions'][] = array('name'=>$term,'align'=>'left');
       }


       return json_encode($printArray);

    }//End of get_print_receipt_json

    
    public function get_carry_bags_offline(Request $request)
    {
        $v_id           = $request->v_id;
        $store_id       = $request->store_id; 
        // $c_id           = $request->c_id;
        // $order_id       = Order::where('user_id', Auth::user()->c_id)->where('status', 'success')->count();
        // $order_id       = $order_id + 1;
        $store_db_name  = get_store_db_name(['store_id' => $store_id]);
        $carry_bag     = Carry::select('barcode')->where('v_id', $v_id)->where('store_id', $store_id)->where('status','1')->where('deleted_status', '0')->get();
        $carr_bag_arr   = $carry_bag->pluck('barcode')->all();

        $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->whereIn('barcode', $carr_bag_arr)->get();

        if(!$bar->isEmpty()){

            $carry_bags  =  VendorSku::select('vendor_sku_flat_table.vendor_sku_detail_id','vendor_sku_flat_table.barcode')
            ->join('stock_current_status', 'stock_current_status.item_id', 'vendor_sku_flat_table.item_id')
            ->where(['vendor_sku_flat_table.v_id' => $v_id , 'stock_current_status.stop_billing' => 0])
            ->whereIn('vendor_sku_flat_table.vendor_sku_detail_id' ,$bar->pluck('vendor_sku_detail_id')->all() )
            ->get();

        }


        
        $data = array();
        if(isset($carry_bags) && count($carry_bags)> 0){

            foreach ($carry_bags as $key => $value) {
                $data[] = array(
                    'BAG_ID' => $value->barcode,
                );
            }


        }
        //return response()->json(['status' => 'get_carry_bags_by_store', 'data' => $data ],200);
        return ['status' => 'get_carry_bags_by_store', 'data' => $data ];
    }

    public function get_print_receipt_offline(Request $request){
        $v_id       = $request->v_id;
        $store_id   = $request->store_id; 
        $vu_id = $request->vu_id;
        $product_data= [];
        $gst_list    = [];
        $final_gst   = [];
        $detatch_gst = [];
        $rounded     = 0;
        $printArray  = array();

        $store   = Store::find($store_id);
        $cashier = Vendor::find($vu_id);

        $printArray['separator'] = array('name'=>"-",'length'=>42);
        $printArray['bill_header'][] = array('field'=>'store_name','label'=>"",'value'=>(isset($store->name) ? $store->name : ""),'lalign'=>'center','valign'=>'center');
        $printArray['bill_header'][] = array('field'=>'store_address','label'=>"",'value'=>(isset($store->address1) ? $store->address1 : ""),'lalign'=>'left','valign'=>'left');
        $printArray['bill_header'][] = array('field'=>'store_state','label'=>"",'value'=>(isset($store->state) ? $store->state : ""),'lalign'=>'left','valign'=>'left');
        $printArray['bill_header'][] = array('field'=>'store_pincode','label'=>"",'value'=>(isset($store->pincode) ? $store->pincode : ""),'lalign'=>'left','valign'=>'left');
        $printArray['bill_header'][] = array('field'=>'store_contact','label'=>"Contact No",'value'=>(isset($store->contact_number) ? $store->contact_number : ""),'lalign'=>'left','valign'=>'left');
        $printArray['bill_header'][] = array('field'=>'store_email','label'=>"E-mail",'value'=>(isset($store->email) ? $store->email : ""),'lalign'=>'left','valign'=>'left');
        $printArray['bill_header'][] = array('field'=>'store_gstin','label'=>"GSTIN",'value'=>(isset($store->gst) ? $store->gst : ""),'lalign'=>'left','valign'=>'left');
        $printArray['bill_header'][] = array('field'=>'store_cin','label'=>"CIN",'value'=>(isset($store->cin) ? $store->cin : ""),'lalign'=>'left','valign'=>'left');

        $printArray['invoice_detail'][] = array('title'=>'Invoice Detail');
        $printArray['invoice_detail'][] = array('field'=>'invoice_no','label'=>"Invoice No",'value'=>'','lalign'=>'left','valign'=>'left');
        if ($order_details->transaction_type == 'return') {
            $printArray['invoice_detail'][] = array('field'=>'refererence_no','label'=>"Reference No",'value'=>'','lalign'=>'left','valign'=>'left');
        }
        $printArray['invoice_detail'][] = array('field'=>'invoice_date','label'=>"Date",'value'=>'','lalign'=>'left','valign'=>'left');
        $printArray['invoice_detail'][] =array('field'=>'cashier_name','label'=>"Cashier",'value'=>(isset($cashier->first_name) ? $cashier->first_name : "").' '.(isset($cashier->last_name) ? $cashier->last_name : ""),'lalign'=>'left','valign'=>'left');
        $printArray['invoice_detail'][] = array('field'=>'customer_contact','label'=>"Customer Mobile",'value'=>'','lalign'=>'left','valign'=>'left');


        $printArray['cart_header']['first_row'][] = array('name'=>"#",'length'=>3);
        $printArray['cart_header']['first_row'][] = array('name'=>"Item",'length'=>8);
        $printArray['cart_header']['first_row'][] = array('name'=>"Rate",'length'=>6);
        $printArray['cart_header']['first_row'][] = array('name'=>"Qty",'length'=>5);
        $printArray['cart_header']['first_row'][] = array('name'=>"Tax",'length'=>5);
        $printArray['cart_header']['first_row'][] = array('name'=>"Amount",'length'=>7);

        $printArray['cart_header']['second_row'][] = array('name'=>"Barcode",'length'=>18);
        $printArray['cart_header']['second_row'][] = array('name'=>"hsn",'length'=>10);
        $printArray['cart_header']['second_row'][] = array('name'=>"Disc",'length'=>6);
        
        $printArray['cart_item'] = (object)[];
        

        $printArray['cart_footer'][] = array('title'=>"Total",'length'=>17);
        $printArray['cart_footer'][] = array('qty'=>"",'length'=>5);
        $printArray['cart_footer'][] = array('total'=>"",'length'=>12);
        $printArray['grand_total'] = array("lable"=>"","value"=>"","valign"=>'left','lalign'=>'left');
        $printArray['customer_paid'][] = array('field'=>'customer_paid','lable'=>'Customer Paid','value'=>'','valign'=>'left','lalign'=>'left'); 
        $printArray['customer_paid'][] = array('field'=>'balance_refund','lable'=>'Balance Refund','value'=>'','valign'=>'left','lalign'=>'left'); 

        $printArray['gst_header'][] = array('label'=>"",'title'=>"GST Summary","lalign"=>"","valign"=>"");
        $printArray['gst_header']['first_row'][] = array('name'=>"Desc",'length'=>9);
        $printArray['gst_header']['first_row'][] = array('name'=>"Taxable",'length'=>11);
        $printArray['gst_header']['first_row'][] = array('name'=>"CGST",'length'=>7);
        $printArray['gst_header']['first_row'][] = array('name'=>"SGST",'length'=>7);
        $printArray['gst_item'][] = array('first_row'=>[
            array('field'=>'tax_name','value'=>'','length'=>9),
            array('field'=>'tax_amount','value'=>' '.'','length'=>11),
            array('field'=>'tax_cgst','value'=>'','length'=>7),
            array('field'=>'tax_sgst','value'=>'','length'=>7) 
        ] );



                // $tdata    = json_decode($value->tdata);            
                // $gst_tax += $value->tax;
                // $itemname = explode(' ', $value->item_name);
                // if (count($itemname) === 1) {
                //     //$itemcode = $itemname[0];
                // } else {
                // $itemcode = $itemname[0]; 
                //     unset($itemname[0]);
                //     $item_name = implode(' ', $itemname);
                // }

        $printArray['order_total'][] =   array('field'=>'total','name'=>"Total",'length'=>9);
        $printArray['order_total'][] =   array('field'=>'bill_amount','name'=>"",'length'=>11);
        $printArray['order_total'][] =   array('field'=>'cgst_tax','name'=>"",'length'=>7);
        $printArray['order_total'][] =   array('field'=>'sgst_tax','name'=>"",'length'=>7);

        $printArray['order_summary'][] =   array('field'=>'total_saving','label'=>"Saving",'value'=>"",'lalign'=>'left','valign'=>'right');
        $printArray['order_summary'][] =   array('field'=>'total_qty','label'=>"Total Qty",'value'=>"",'lalign'=>'left','valign'=>'right');
        $printArray['order_summary'][] =   array('field'=>'total_sale','label'=>"Total Sale",'value'=>"",'lalign'=>'left','valign'=>'right');

        $printArray['payment_meth'][] =   array('field'=>'payment_method','label'=>'','value'=>'','lalign'=>'left','valign'=>'right');


        $printArray['payable_amt'][] =   array('field'=>'net_payable','label'=>'Net Payable','value'=> '','lalign'=>'left','valign'=>'right');

        $printArray['bill_footer']['terms_conditions'][] =   array('field'=>'terms_conditions','title'=>'Terms & conditions');
        $printArray['bill_footer']['terms_conditions'][] = array('name'=>'more terms','align'=>'left');
        
        return $printArray;

    }//End of get_print_receipt_offline

    public function get_print_offline(Request $request)
    {
        $v_id       = $request->v_id;
        $store_id   = $request->store_id;
        $vu_id = $request->vu_id;
        // $v_app = DB::table('vendor_settings')->select('settings')->where('name','vendor_app')->where('v_id',$v_id)->where(['store_id'=>$store_id,'user_id'=>$vu_id,'role_id'=>null])->first();
        // $vsett = json_decode($v_app->settings);
        // if(isset($vsett->offline->status) && $vsett->offline->status == 1){
            $request = new \Illuminate\Http\Request();
            $request->merge([
                'v_id' => $v_id,
                'store_id' => $store_id,
                'vu_id' => $vu_id
            ]);
            return $this->get_print_receipt_offline($request);
        //}
    }

    public function get_print_receipt(Request $request)
    {
        // dd($request->all());
        $v_id        = $request->v_id;
        $store_id    = $request->store_id;
        $c_id        = $request->c_id;
        $vu_id       = $request->vu_id;
        $order_id    = $request->order_id;
        $usefor      = !empty($request->usefor)?$request->usefor:'';
        $product_data= [];
        $gst_list    = [];
        $final_gst   = [];
        $detatch_gst = [];
        $rounded     = 0;
        $trans_from  = $request->trans_from;
        $returnType  = 'CUSTOM_HTML';
        $isOrderReciept = false;
        // if($request->v_id == 127){
        //     $acct  = new DebitnoteprintController;
        //     return $acct->get_debitnote_recipt($request);
        // }
        // if($request->v_id == 127){
        //     $acct  = new CreditnoteprintController;
        //     return $acct->get_creditnote_recipt($request);
        // }
        if($request->has('trans_type') && $request->trans_type == 'order') {
            $isOrderReciept = true;
        }

        if($request->has('return_type')){
            $returnType  = $request->return_type;
        }
        if($request->has('print_for') && !empty($request->print_for) && $request->print_for=='GV'){
            $print_for  = $request->print_for;
        }else{
            $print_for='';   
        }
        // dd($request->all());
        $bill_print_type = 0;
        $role        = VendorRoleUserMapping::select('role_id')->where('user_id', $vu_id)->first();
        $sParams     = ['v_id' => $v_id,'store_id'=>$store_id,'user_id'=>$c_id,'role_id'=>@$role->role_id,'trans_from' => $trans_from];
        $vendorS     = new VendorSettingController;
        $printSetting= $vendorS->getPrintSetting($sParams);
        if(count($printSetting) > 0){
            foreach($printSetting as $psetting){
                if($psetting->name == 'bill_print'){
                    $bill_print_type = $psetting->width;
                }
            }
        }
        if(!empty($request->type) && $request->type== 'adhoc_credit_note' ||  $request->type== 'refund_credit_note'   ){
            /*$request->merge([
                        'v_id' => $v_id,
                        'c_id' => $c_id,
                        'store_id' => $store_id,
                        'order_id' => $order_id
                    ]);*/
            $acct  = new AccountsaleController;
            return $acct->get_deposite_recipt($request);
        }

        if($bill_print_type == 'A4' && $trans_from == 'CLOUD_TAB_WEB' && $returnType != 'JSON' && $print_for!='GV'){
            $callPrint = new PrintController;
            return $callPrint->print_html_page($request);
        }         

        if($v_id == 111   || $v_id == 143 || $print_for=='GV'){

            return $this->callGlobalTaxPrint($request);
        }


        // dd($vendorS->getPrintSetting($sParams));

        $vendorC  = new VendorController;
        $crparams = array('v_id'=>$v_id,'store_id'=>$store_id,'vu_id'=>1,'info_type'=>'CURRENCY');
        $currency = $vendorC->getCurrencyDetail($crparams);
        $currencyR = explode(' ', $currency['name']);
        if($currencyR > 1){
            $len = count($currencyR);
            $currencyName = $currencyR[$len-1];
        }else{
            $currencyName  =  $currencyR ;
        }
        if($currency['code'] != 'INR'){
           return $this->callVatRecipt($request);
        }
        if($request->has('print_type') && $request->print_type == 'json'){
            $request = new \Illuminate\Http\Request();
            $request->merge([
                'v_id' => $v_id,
                'c_id' => $c_id,
                'store_id' => $store_id,
                'order_id' => $order_id
            ]);
            return $this->get_print_receipt_json($request);
        }
        if($v_id == 26){
            $this->callCustomPrint($request);
        }
        if($bill_print_type == 'A4' && $returnType != 'JSON' ){
            $callPrint = new PrintController;
            //return $callPrint->print_html_page($request);
            if($usefor == 'send_email'){
                return $callPrint->print_html_page_mail($request);
            }else{
                 return $callPrint->print_html_page($request);
            }
        }
        $store         = Store::find($store_id);
        if($isOrderReciept) {
            $order_details = Order::where(['order_id' => $order_id, 'v_id' => $request->v_id, 'store_id' => $request->store_id ])->first();
        } else {
            $order_details = Invoice::where('invoice_id', $order_id)->first();
        }



        /*B2C QR Code start*/

        /*B2C QR Code end*/

        if($returnType == 'JSON') {
            $cart_product = [];
            $total_amount = 0;
            $order_details = (object)[ 'total' => 0, 'payvia' => [], 'transaction_type' => 'sales', 'vuser' => (object)[ 'first_name' => '', 'last_name' => '' ], 'user' => (object)[ 'mobile' => '', 'address' => (object)[ 'address1' => '', 'address2' => '', 'city' => '', 'state' => '', 'landmark' => '' ], 'hall_no' => '', 'seat_no' => '' ] ];
        } else {
            if($isOrderReciept) {
                $cart_q = OrderDetails::where('t_order_id', $order_details->od_id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','0')->where('user_id', $order_details->user_id)->sum('qty');

                $cart_qt = OrderDetails::where('t_order_id', $order_details->od_id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','1')->where('user_id', $order_details->user_id)->count('qty');

                $cart_qty = $cart_q + $cart_qt;

                $total_amount = OrderDetails::where('t_order_id', $order_details->od_id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
                    // dd($total_amount);

                $cart_product = OrderDetails::where('t_order_id', $order_details->od_id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->get();
            }else {
                $cart_q = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','0')->where('user_id', $order_details->user_id)->sum('qty');

                $cart_qt = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','1')->where('user_id', $order_details->user_id)->count('qty');

                $cart_qty = $cart_q + $cart_qt;

                $total_amount = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
                    // dd($total_amount);

                $cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->get();
            }
        }
        $count       = 1;
        $gst_tax     = 0;
        $gst_listing = [];
        $total_savings_amount = 0;
        $tatalItemLevelManualDiscount=0;

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
            $itemLevelmanualDiscount=0;
             if($value->item_level_manual_discount!=null){
                $iLmd = json_decode($value->item_level_manual_discount);
                $itemLevelmanualDiscount= (float)$iLmd->discount;
             }
             if($itemLevelmanualDiscount>0){
              $tatalItemLevelManualDiscount  += $itemLevelmanualDiscount;
              }
             if($v_id == 36 || $v_id == 127){
                $configCon = new CartconfigController;

                $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $value->barcode)->first();
                $Item = null;
                if($bar){
                    $where    = array('v_id'=>$v_id,'vendor_sku_detail_id'=> $bar->vendor_sku_detail_id,'deleted_at' => null);
                    $Item     = VendorSku::select('vendor_sku_detail_id','v_id','item_id','has_batch','uom_conversion_id','variant_combi','tax_type','cat_name_1')->where($where)->first();
                    $Item->barcode = $bar->barcode;
                }
                // dd($Item->cat_name_1,$cart_product);
                $priceArr  = array('v_id'=>$v_id,'store_id'=>$store_id,'item'=>$Item,'unit_mrp'=>'');
                $price    =  $configCon->getprice($priceArr);
                $unit_m = (int)$price['unit_mrp'];
                $amount = ($unit_m*$value->qty) - ($value->unit_mrp*$value->qty);
                $total_save_with_dis = ($value->discount+$value->manual_discount + $value->bill_buster_discount) + ($amount)+$itemLevelmanualDiscount;

                // if($value->barcode == '9996230102'){
                    
                //     dd($unit_m);
                // }
                $total_savings_amount += $total_save_with_dis;
                $product_data[]  = [
                    'row'           => 1,
                    'sr_no'         => $count++,
                    'name'          => $value->item_name,
                    'qty'           => $value->qty,
                    'tax_amt'       => $value->tax,  //$value->tax.$tax_type,
                    'rate'          => $unit_m,
                    'total'         => $value->total 
                ];
                $product_data[] = [
                    'row'           => 2,
                    'discount'      => $value->discount+$value->manual_discount + $value->bill_buster_discount+$itemLevelmanualDiscount,
                    'rsp'           => $value->unit_mrp,
                    'item_code'     => $value->barcode,
                    'sm_value'      => '3',   
                    'tax_per'       => $tdata->cgst + $tdata->sgst,
                    'total'         => $value->total,
                    'hsn'           => $tdata->hsn,
                    'category'           => $Item->cat_name_1       
                ];
                $gst_list[] = [
                    'name'              => $tdata->tax_name,
                    'wihout_tax_price'  => $tdata->taxable,
                    'tax_amount'        => $tdata->tax,
                    'cgst'              => $tdata->cgstamt,
                    'sgst'              => $tdata->sgstamt,
                    'cess'              => $tdata->cessamt,
                    'igst'              => $tdata->igstamt
                ];
            }else{

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
                    'discount'      => $value->discount+$value->manual_discount + $value->bill_buster_discount+$itemLevelmanualDiscount,
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
                    'cess'              => $tdata->cessamt,
                    'igst'              => $tdata->igstamt
                ];
            }

                    }
                    $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
            //dd($gst_list);
                    $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = $total_igst = 0 ;
                    $cgst = $sgst = $cess = $igst= 0 ;
                    foreach ($gst_listing as $key => $value) {

               // dd($gst_list);
                        $tax_ab = [];
                        $tax_cg = [];
                        $tax_sg = [];
                        $tax_ces = [];
                        $tax_igst = [];

                        foreach ($gst_list as $val) {

                            if ($val['name'] == $value) {
                                $total_gst             += str_replace(",", '', $val['tax_amount']);
                                $taxable_amount        += str_replace(",", '', $val['wihout_tax_price']);
                                $tax_ab[]       =  str_replace(",", '', $val['wihout_tax_price']);
                                $tax_cg[]       =  str_replace(",", '', $val['cgst']);
                                $tax_sg[]       =  str_replace(",", '', $val['sgst']);
                                $tax_ces[]      =  str_replace(",", '', $val['cess']);
                                $tax_igst[]      =  str_replace(",", '', $val['igst']);
                                $cgst              += str_replace(",", '', $val['cgst']);
                                $sgst              += str_replace(",", '', $val['sgst']);
                                $cess              += str_replace(",", '', $val['cess']);
                                $igst              += str_replace(",", '', @$val['igst']);
                                $final_gst[$value] = (object)[
                                    'name'      => $value,
                            'taxable'   => array_sum($tax_ab),//$this->format_and_string($taxable_amount),
                            'cgst'      => round(array_sum($tax_cg),2),
                            'sgst'      => round(array_sum($tax_sg),2),
                            'cess'      => round(array_sum($tax_ces),2),
                            'igst'      => round(array_sum($tax_igst),2),
                        ];
                        // $total_taxable += $taxable_amount;

                    }
                }
            }
            $total_csgt = round($cgst,2);
            $total_sgst = round($sgst,2);
            $total_cess = round($cess,2);
            $total_igst = round($igst,2);
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
            if ($order_details->transaction_type == 'return') {
                $refererence_order = Order::where(['order_id' => $order_details->ref_order_id, 'v_id' => $request->v_id, 'store_id' => $request->store_id ])->first();
            }
            
            // dd($refererence_oerder,$order_details);
            $bilLogo      = '';
            $bill_logo_id = 11;
            $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
            if($vendorImage)
            {
                $bilLogo = env('ADMIN_URL').$vendorImage->path;
            }

            if($isOrderReciept) {
                $payments  = $order_details->payments;
            } else {
                $payments  = $order_details->payvia;
            }
            $cash_collected = 0;  
            $cash_return    = 0;
            $net_payable        = $total_amount;

            // dd($payments);

            foreach ($payments as $payment) {
                $paymeny_method = Mop::where('code', $payment->method)->first();

                if($paymeny_method){
                    // dd($paymeny_method->name);
                    if ($payment->method == 'cash') {
                        $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
                        if($order_details->transaction_type == 'return'){
                            // $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
                            $mop_list[] = [ 'mode' => $paymeny_method->name, 'amount' => $payment->amount ];
                        }else{
                           // $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->cash_collected-$cashReturn ];
                            $mop_list[] = [ 'mode' => $paymeny_method->name, 'amount' => $payment->cash_collected-$cashReturn ];
                        }
                    } else {
                        // $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
                        $mop_list[] = [ 'mode' => $paymeny_method->name, 'amount' => $payment->amount ];
                    }
                    // dd($mop_list);
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
            }

            $customer_paid = $cash_collected;
            $balance_refund= $cash_return;

            ########################
            ####### Print Start ####
            ########################
            //$terms_conditions =  array('(1) Exchange Within 7 days only.','(2) MRP Are Inclusive of Applicable Tax');

            $terms_conditions =  array('1. MRP Are Inclusive of Applicable Tax');
            if($v_id == 18){
                $terms =  Terms::where('v_id',$v_id)->get();
                $terms_condition = json_decode($terms);
                foreach ($terms_condition as $value) {
                    $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                }
                // $terms_conditions =  array('1. MRP Are Inclusive of Applicable Tax', '2. Fresh Finds is a venture of Jubilant Consumer Pvt. Ltd.' );
            }
            
            if($v_id == 11){
                $terms =  Terms::where('v_id',$v_id)->get();
                $terms_condition = json_decode($terms);
                foreach ($terms_condition as $value) {
                    $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                }
              // $terms_conditions =  array('1.Products can be exchanged within 7 days from date of purchase.','2.Original receipt/invoice copy must be carried.','3.In the case of exchange – the product must be in it’s original and unused condition, along with all the original price tags, packing/boxes and barcodes received.','4.We will only be able to exchange products. Afraid there will not be refund in any form.','5.We do not offer any kind of credit note at the store.','6.There will be no exchange on discounted/sale products.','7.In the case o damage or defect – the store teams must be notified within 24 hours of purchase');

            }

            if($v_id == 127){
                $terms =  Terms::where('v_id',$v_id)->get();
                $terms_condition = json_decode($terms);
                foreach ($terms_condition as $value) {
                    $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                }
              // $terms_conditions =  array('1.Undergarments , being hygiene products , are not subject to exchange or return.','2.Products can be exchanged within 7days from date of purchase.','3.Original receipt/invoice copy mustbe carried.','4.In the case of exchange – theproduct must be in it’s original and unused condition, along with all theoriginal price tags, packing/boxes andbarcodes received.','5.We will only be able to exchange products. Afraid there will not be refund in any form.','6.We do not offer any kind of credit note at the store.','7.There will be no exchange on discounted/sale products.','8.In the case o damage or defect – the store teams must be notified within 24 hours of purchase');
                 // dd($terms_conditions);
            }
            if($v_id == 128){
                $terms_conditions =  array('Thank You');
            }
            if($v_id == 21){
                $terms =  Terms::where('v_id',$v_id)->get();
                $terms_condition = json_decode($terms);
                foreach ($terms_condition as $value) {
                    $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                }
                // $terms_conditions =  array('Brand accepts only exchange of unused products within 15 days from the
                // date of purchase at stores within the country.','Exchanged product must be in its original condition along with tag
                // attached and accompanied with original invoice.','Product sold during sale/promotion cannot be exchanged/returned.',' Personalized/Customized products will neither be exchanged nor returned.','For more/any information on exchange or any other queries please contact on our customer care at info@janaviindia.com');
            } 

 
            if($v_id == 77){
                $terms =  Terms::where('v_id',$v_id)->get();
                $terms_condition = json_decode($terms);
                foreach ($terms_condition as $value) {
                    $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                }
                // $terms_conditions = array("Warranty 60 days(From the date of purchase) against manufacturing defect only when purchase from an exclusive Campus showroom only.","Goods once sold will not be refunded","Any dispute is subject to Delhi jurisdiction only");
            }
             if($v_id == 119){
                $terms =  Terms::where('v_id',$v_id)->get();
                $terms_condition = json_decode($terms);
                foreach ($terms_condition as $value) {
                    $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                }
                // $terms_conditions =  array('1. Goods once sold, cannot be returned.',' 2. Products can be exchanged within 10 days from date of purchase. Please carry a bill at the time of exchange.','THANK YOU!','PLEASE VISIT AGAIN WITH YOUR FRIENDS & FAMILY.');
            } 
            if($v_id == 80){
                $terms =  Terms::where('v_id',$v_id)->get();
                $terms_condition = json_decode($terms);
                foreach ($terms_condition as $value) {
                    $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                }
                // $terms_conditions =  array('1.NO EXCHANGE NO RETURN','2.NO GUARANTEE DURING THIS FASHION ERA OF ANY ITEM OF ANY KIND.','3.CUSTOMER CALL TIMING:11 AM TO 8 PM','4.WEDNESDAY IS HOLIDAY.', '5.WHETHER ITEM/PRODUCT IS WASHABLE OR TO BE DRY CLEANED,CUSTOMER SHOULD MANAGE THERESELF ACCORDINGLY.','6.NO GUARANTEE FOR STITCHING.','7.KINDLY BRING INVOICE/BILL COMPULSORY DURING PICKUP OF ANY ALTERATION/STITCHING ITEM OR FOL PIKKO OF SAREES.WITHOUT BILL/INVOICE ITEMS WILL NOT BE GIVEN.');
            }
 
            if($order_details->transaction_type == 'return'){
             $invoice_title     = 'Credit Note';
         }else{
            if($v_id == 7){
                $invoice_title     = 'Tax invoice';
            }if($v_id == 18){
                $invoice_title     = 'Tax Invoice Cum Bill of Supply';
            }else{
                if($isOrderReciept) {
                    $invoice_title = 'Order Detail';
                } else {
                    $invoice_title     = 'Invoice Detail';
                }
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
        if($returnType == 'JSON') {

            $order_details->invoice_id = '';
            // $order_details->created_at = '';
            // $order_details->vuser->first_name = '';
            // $order_details->vuser->last_name = '';
            // $order_details->user->mobile = '';
            // $order_details->user->address->address1 = '';
            // $order_details->user->address->address2 = '';
            // $order_details->user->address->city = '';
            // $order_details->user->address->state = '';
            // $order_details->user->address->landmark = '';
            // $order_details->user->hall_no = '';
            // $order_details->user->seat_no = '';
            $taxable_amount = 1;
            $product_data = [];
            $cart_qty = '';
            $total_amount = '';
            $order_details->discount = '';
            $order_details->manual_discount = '';
            $order_details->bill_buster_discount = '';
            $order_details->total = '';
            $customer_paid = '';
            $balance_refund = '';
            $detatch_gst = [];
            $total_cess = '';
            $taxable_amount = '';
            $total_csgt = '';
            $total_sgst = '';
            $total_cess = '';
            // $total_discount = '';
            $cart_qty = '';
            $total_amount = '';
            $mop_list = '';
            $net_payable = '';

            $printInvioce = new PrintJsonInvoice($manufacturer_name[0], $printParams , $returnType);
        } else {
            $printInvioce = new PrintInvoice($manufacturer_name[0], $printParams , $returnType);
        }
        
        if($v_id == '119'){
            $printInvioce->addLineCenter('Kumar Shirts', 24, true, false, 'store_name');
        }
        else{
            $printInvioce->addLineCenter($store->name, 24, true, false, 'store_name');
        }
        if($v_id == '119'){
        $printInvioce->addLineCenter('Stockist: Eurus Solutions Pvt Ltd', 24, true, false, 'store_name');
        }
        $printInvioce->addLine($store->address1, 22, false, false, 'address_1');
        if($store->address2){
        $printInvioce->addLine($store->address2, 22, false, false, 'address_2');
        }
        $printInvioce->addLine($store->location.'-'.$store->pincode.', '.$store->state, 22, false, false, 'location');
        if($v_id != '119'){
            $printInvioce->addLine('Contact No: '.$store->contact_number, 22, false, false, 'contact_no');
        }
        if($v_id != 119 && $v_id != 80){
            $printInvioce->addLine('E-mail: '.$store->email, 22, false, false, 'email');
        }
        $printInvioce->addLine('GSTIN: '.$store->gst, 22, false, false, 'gst');
        if($store->cin){
        $printInvioce->addLine('CIN: '.$store->cin, 22, false, false, 'cin');            
        }
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        $printInvioce->addLine($invoice_title  , 22, true, false, 'invoice_title');
        $printInvioce->addDivider('-', 20, false, false, 'separator');

        if($isOrderReciept) {
            $printInvioce->addLineLeft(' Order No : '.$order_details->order_id , 22, true, false, 'invoice_no');
        } else {
            $printInvioce->addLineLeft(' Invoice No : '.$order_details->invoice_id , 22, true, false, 'invoice_no');
            if ($order_details->transaction_type == 'return') {
                $printInvioce->addLineLeft(' Reference No : '.$refererence_order->ref_order_id , 22, true, false, 'refererence_no');
            }
        }
        if($returnType == 'JSON'){
            $printInvioce->addLineLeft(' Date : ' , 22, false, false, 'invoice_date_time');
        }else{
            $printInvioce->addLineLeft(' Date : '.date('d-M-Y', strtotime($order_details->created_at))." at ".date('h:i:s A', strtotime($order_details->created_at)), 22, false, false, 'invoice_date_time');
        }
        if($v_id != '119'){
            $printInvioce->addLineLeft(' Cashier : '.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name , 22, false, false, 'cashier_name');
            
            if(isset($order_details->user->first_name) && $order_details->user->first_name != 'Dummy'){
                $printInvioce->addLineLeft(' Customer Mobile : '.@$order_details->user->mobile , 22, false, false, 'customer_mob');
            }
        }
        

        if($v_id ==  57 || $v_id ==  54){
            $lastname = $order_details->user->last_name == 'Customer'?'':$order_details->user->last_name;
            $printInvioce->addLineLeft(' Customer Name : '.@$order_details->user->first_name.' '.@$lastname , 22, false, false, 'customer_name');    
        }
        if(isset($order_details->comm_trans) && $order_details->comm_trans == 'B2B'){
             $printInvioce->addLineLeft(' Customer GSTIN : '.@$order_details->cust_gstin, 22, false, false, 'customer_gstin');  
        }

        /***************************************/
            # Customer Address When Resturant Type #
        /**************************************/

        if($store->type == 5 || $store->type == 6){
        if(isset($order_details->user->address->address1)){
        $printInvioce->addLineLeft(' Customer Address : '.$order_details->user->address->address1 , 22, false, false, 'customer_address');
        if($order_details->user->address->address2){
         $printInvioce->addLineLeft(' '.$order_details->user->address->address2 , 22, false, false, 'customer_address_2');
        }
        if($order_details->user->address->city){
         $printInvioce->addLineLeft($order_details->user->address->city.', '.$order_details->user->address->state , 22, false, false, 'customer_city');
        }
        if($order_details->user->address->landmark){
         $printInvioce->addLineLeft('Landmark: '.$order_details->user->address->landmark , 22, false, false, 'customer_landmark');
        }
        }
        }


        if($order_details->user->hall_no){
        $printInvioce->addLineLeft(' Hall No : '.$order_details->user->hall_no , 22, false, false. 'hall_no');
        }
        if($order_details->user->seat_no){
        $printInvioce->addLineLeft(' Table No : '.$order_details->user->seat_no , 22, false, false, 'seat_no');
        }

        $printInvioce->addDivider('-', 20, false, false, 'separator');


        if($v_id ==  149){
            $printInvioce->tableStructure(['#', 'Item', 'Rate','Qty','', 'Amount'], [3, 8, 8, 8, 0, 7], 22, false, false, 'item_header_1', ['sr_no','name', 'rate', 'qty', '', 'total']);
        }elseif($v_id ==  128){
                    $printInvioce->tableStructure(['#', 'Item', 'Rate','Qty','Tax Amt', 'Amount'], [3, 8, 8, 8, 0, 7], 22, false, false, 'item_header_1', ['sr_no','name', 'rate', 'qty', 'tax_amt', 'total']);

                    $printInvioce->tableStructure(['', '', '','','Tax Amt', ''], [0, 0, 0, 0, 30, 0], 22, false, false, 'item_header_1', ['','', '', '', 'tax_amt', '']);
        }else{
            $printInvioce->tableStructure(['#', 'Item', 'Rate','Qty','Tax Amt', 'Amount'], [3, 7, 6, 4, 6, 7], 22, false, false, 'item_header_1', ['sr_no','name', 'rate', 'qty', 'tax_amt', 'total']);
        }
        
        
        if($returnType == 'JSON'){
            $printInvioce->tableStructure(['Barcode','hsn', 'Disc'], [18,10, 6], 22, false, false, 'item_header_2', ['barcode', 'hsn', 'disc']);
        }else{
            if($taxable_amount > 0){
                if($v_id ==  149){
                    $printInvioce->tableStructure(['Barcode','', 'Disc'], [18,0, 16], 22, false, false, 'item_header_2', ['barcode', '', 'disc']);
                }else if($v_id ==  127){
                    $printInvioce->tableStructure(['Barcode','category', 'hsn'], [18,8, 8], 22, false, false, 'item_header_2', ['barcode', 'category', 'hsn']);
                }elseif($v_id ==  128){
                    $printInvioce->tableStructure(['Barcode','', 'Disc'], [22,8, 4], 22, false, false, 'item_header_2', ['barcode', '', 'disc']);
                    $printInvioce->tableStructure(['hsn','', ''], [32,0, 0], 22, false, false, 'item_header_2', ['hsn', '', '']);
                }else if($v_id ==  148){
                    $printInvioce->tableStructure(['Barcode','hsn', ''], [18,16, 0], 22, false, false, 'item_header_2', ['barcode', 'hsn', '']);
                }else{
                    $printInvioce->tableStructure(['Barcode','hsn', 'Disc'], [18,10, 6], 22, false, false, 'item_header_2', ['barcode', 'hsn', 'disc']);
                }
        }else{
        $printInvioce->tableStructure(['Barcode','', 'Disc  '], [22,2 , 10], 22, false, false, 'item_header_2', ['barcode', '', 'disc']);
        }
        }

        $printInvioce->addDivider('-', 20, false, false, 'separator');

        if($returnType == 'JSON'){

            $printInvioce->tableStructure([
                $product_data[0] = '',
                $product_data[1] = '',
                $product_data[2] = '',
                $product_data[3] = '',
                $product_data[4] = '',
                $product_data[5] = ''
            ],
            [3, 8, 6,5,5,7], 22, false, false, 'item_detail_1', ['sr_no', 'name', 'rate', 'qty', 'tax_amt', 'total']);
            $printInvioce->tableStructure([
                ' '.$product_data[6] = '',
                $product_data[7] = '',
                $product_data[8] = ''
            ],
            [18,10, 6], 22, false, false, 'item_detail_2', ['item_code', 'hsn', 'discount']);

        }else{

            for($i = 0; $i < count($product_data); $i++) {
            if($i % 2 == 0) {
                if($v_id ==  149){ 
                    $printInvioce->tableStructure([
                        $product_data[$i]['sr_no'],
                        $product_data[$i]['name'],
                        ' '.$product_data[$i]['rate'],
                        $product_data[$i]['qty'],
                        // $product_data[$i]['qty'],
                        $product_data[$i]['total']
                    ],
                    [3, 12, 7,5,6], 22, false, false, 'item_detail_1', ['sr_no', 'name', 'rate', 'qty', '', 'total']);
                }else if($v_id ==  128){ 
                    $printInvioce->tableStructure([
                        $product_data[$i]['sr_no'],
                        $product_data[$i]['name'],
                        ' '.$product_data[$i]['rate'],
                        $product_data[$i]['qty'],
                        $product_data[$i]['tax_amt'],
                        $product_data[$i]['total']
                    ],
                    [3, 8, 8, 8, 0, 7], 22, false, false, 'item_detail_1', ['sr_no', 'name', 'rate', 'qty', 'tax_amt', 'total']);


                    $printInvioce->tableStructure([
                        $product_data[$i]['sr_no'],
                        $product_data[$i]['name'],
                        ' '.$product_data[$i]['rate'],
                        $product_data[$i]['qty'],
                        $product_data[$i]['tax_amt'],
                        $product_data[$i]['total']
                    ],
                    [0, 0, 0, 0, 30, 0], 22, false, false, 'item_detail_1', ['', '', '', '', 'tax_amt', '']);
                }else {
                    $printInvioce->tableStructure([
                        $product_data[$i]['sr_no'],
                        $product_data[$i]['name'],
                        ' '.$product_data[$i]['rate'],
                        $product_data[$i]['qty'],
                        $product_data[$i]['tax_amt'],
                        $product_data[$i]['total']
                    ],
                    [3, 8, 6,5,5,7], 22, false, false, 'item_detail_1', ['sr_no', 'name', 'rate', 'qty', 'tax_amt', 'total']);
                }

        } else {
                    if($v_id ==  149){
                            $printInvioce->tableStructure([
                                ' '.$product_data[$i]['item_code'],
                                // $taxable_amount?$product_data[$i]['hsn']:'',
                                $product_data[$i]['discount']
                        ],
                        [18, 6], 22, false, false, 'item_detail_2', ['item_code', '', 'discount']);
                    }elseif($v_id ==  148){
                            $printInvioce->tableStructure([
                                ' '.$product_data[$i]['item_code'],
                                $taxable_amount?$product_data[$i]['hsn']:'',
                                // $product_data[$i]['discount']
                        ],
                        [18, 6], 22, false, false, 'item_detail_2', ['item_code', 'hsn', '']);
                    }elseif($v_id ==  127){
                            $printInvioce->tableStructure([
                                ' '.$product_data[$i]['item_code'],
                                $product_data[$i]['category'],
                                $taxable_amount?$product_data[$i]['hsn']:'',
                                // $product_data[$i]['discount']
                        ],
                        [18, 8, 8], 22, false, false, 'item_detail_2', ['item_code', 'category', 'hsn']);
                    }elseif($v_id ==  128){
                        $printInvioce->tableStructure([
                            ' '.$product_data[$i]['item_code'],
                            $taxable_amount?$product_data[$i]['hsn']:'',
                            $product_data[$i]['discount']
                        ],
                        [30,0, 4], 22, false, false, 'item_detail_2', ['item_code', '', 'discount']);

                        $printInvioce->tableStructure([
                            ' '.$product_data[$i]['hsn'],
                            $taxable_amount?$product_data[$i]['item_code']:'',
                            $product_data[$i]['discount']
                        ],
                        [32,0, 0], 22, false, false, 'item_detail_2', ['hsn', '', '']);
                    }else{
                        $printInvioce->tableStructure([
                            ' '.$product_data[$i]['item_code'],
                            $taxable_amount?$product_data[$i]['hsn']:'',
                            $product_data[$i]['discount']
                        ],
                        [18,10, 6], 22, false, false, 'item_detail_2', ['item_code', 'hsn', 'discount']);
                    }
                }
            }
        }
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        $printInvioce->tableStructure(['Total', $cart_qty, $total_amount], [20, 4,14], 22, true, false, 'order_total', ['total', 'cart_qty', 'total_amount']);
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        if($returnType == 'JSON'){
            $printInvioce->addLineLeft(ucfirst($currencyName).': '.($order_details->total) , 22, false, false, 'amount_words');
        }else{
            $printInvioce->addLineLeft(ucfirst($currencyName).': '.ucfirst(numberTowords(round($order_details->total))).' Only' , 22, false, false, 'amount_words');
        }

        $printInvioce->addDivider('-', 20, false, false, 'separator');
        if($customer_paid > 0){
            $printInvioce->addLineLeft('  Customer Paid: '.format_number($customer_paid), 22, true, false, 'customer_paid');
            $printInvioce->addLineLeft('  Balance Refund: '.format_number($balance_refund), 22, true, false, 'balance_refund');
        }

        if($returnType == 'JSON'){    
            $printInvioce->addLineLeft('Customer Paid'.($customer_paid), 22, true, false, 'customer_paid');
            $printInvioce->addLineLeft('Balance Refund'.($balance_refund), 22, true, false, 'balance_refund');
        }
        if($customer_paid!=0 || $returnType == 'JSON'){
        $printInvioce->addDivider('-', 20, false, false, 'separator');

        }
        /*Tax Start */


        if($returnType == 'JSON' && $taxable_amount == ''){

            $printInvioce->leftRightStructure('GST Summary','', 22, false, false, 'gst_summary');
            $printInvioce->addDivider('-', 20, false, false, 'separator');

            $printInvioce->tableStructure(['Desc', 'Taxable', 'CGST','SGST','CESS'], [8,9, 6,6,5], 22, false, false, 'gst_header', ['desc', 'taxable', 'cgst', 'sgst', 'cess']);
                $printInvioce->addDivider('-', 20, false, false, 'separator');
                $gst['name'] = '';
                $gst['taxable'] = '';
                $gst['cgst'] = '';
                $gst['sgst'] = '';
                $gst['cess'] = '';
                // foreach ($detatch_gst as $index => $gst) {
                    $printInvioce->tableStructure([$gst['name'],
                        ' '.$gst['taxable'],
                        $gst['cgst'],
                        $gst['sgst'],
                        $gst['cess']],
                        [8,9, 6,6,5], 22, false, false, 'gst_listing', ['gst_name', 'gst_taxable', 'gst_cgst', 'gst_sgst', 'gst_cess']);
                // }
                $printInvioce->addDivider('-', 20, false, false, 'separator');
                $printInvioce->tableStructure(['Total',
                    $taxable_amount,
                    $total_csgt,
                    $total_sgst,
                    $total_cess], [8, 9, 6,6,5], 22, true, false, 'gst_item', ['total', 'tax_amt', 'total_csgt', 'total_sgst', 'total_cess']);

            //     $printInvioce->tableStructure(['Total',
            //     $taxable_amount,
            //     $total_csgt,
            //     $total_sgst 
            // ], [8, 12, 7,7], 22, true, false, 'gst_order_summary', ['total', 'taxable_amount', 'total_csgt', 'total_sgst']);
                $printInvioce->addDivider('-', 20, false, false, 'separator');
        }

        if($taxable_amount > 0){

        $printInvioce->leftRightStructure('GST Summary','', 22, false, false, 'gst_summary');
        $printInvioce->addDivider('-', 20, false, false, 'separator');

        if(!empty($detatch_gst)) {

            if($total_cess > 0){
                $printInvioce->tableStructure(['Desc', 'Taxable', 'CGST','SGST','CESS'], [8,9, 6,6,5], 22, false, false, 'gst_header', ['desc', 'taxable', 'cgst', 'sgst', 'cess']);
                        //$printInvioce->tableStructure(['', 'Amt','Amt','Amt','Amt'], [8, 8, 6,6,6], 22);
                $printInvioce->addDivider('-', 20, false, false, 'separator');
                foreach ($detatch_gst as $index => $gst) {
                    $printInvioce->tableStructure([$gst->name,
                        ' '.$gst->taxable,
                        $gst->cgst,
                        $gst->sgst,
                        $gst->cess],
                        [8,9, 6,6,5], 22, false, false, 'gst_listing', ['gst_name', 'gst_taxable', 'gst_cgst', 'gst_sgst', 'gst_cess']);
                }
                $printInvioce->addDivider('-', 20, false, false, 'separator');
                $printInvioce->tableStructure(['Total',
                    format_number($taxable_amount),
                    format_number($total_csgt),
                    format_number($total_sgst),
                    format_number($total_cess)], [8, 9, 6,6,5], 22, true, false, 'gst_item', ['total', 'tax_amt', 'total_csgt', 'total_sgst', 'total_cess']);
            }else if($total_igst > 0){
                $printInvioce->tableStructure(['Desc', 'Taxable', 'IGST'], [8,9, 17], 22, false, false, 'gst_header', ['desc', 'taxable', 'igst']);
                        //$printInvioce->tableStructure(['', 'Amt','Amt','Amt','Amt'], [8, 8, 6,6,6], 22);
                $printInvioce->addDivider('-', 20, false, false, 'separator');
                foreach ($detatch_gst as $index => $gst) {
                    $printInvioce->tableStructure([$gst->name,
                        ' '.$gst->taxable,
                        $gst->igst
                        
                        ],
                        [8,9, 17], 22, false, false, 'gst_listing', ['gst_name', 'gst_taxable', 'gst_cgst', 'gst_sgst', 'gst_cess']);
                }
                $printInvioce->addDivider('-', 20, false, false, 'separator');
                $printInvioce->tableStructure(['Total',
                    format_number($taxable_amount),
                    format_number($total_igst)], [8, 9, 17], 22, true, false, 'gst_item', ['total', 'tax_amt', 'total_igst']);
            }else{
             $printInvioce->tableStructure(['Desc', 'Taxable', 'CGST','SGST'], [8,12, 7,7], 22, false, false, 'gst_header', ['desc', 'taxable', 'cgst', 'sgst']);
                        //$printInvioce->tableStructure(['', 'Amt','Amt','Amt','Amt'], [8, 8, 6,6,6], 22);

             $printInvioce->addDivider('-', 20, false, false, 'separator');
             foreach ($detatch_gst as $index => $gst) {
                $printInvioce->tableStructure([$gst->name,
                    ' '.$gst->taxable,
                    $gst->cgst,
                    $gst->sgst 
                ],
                [8,12, 7,7], 22, false, false, 'gst_item', ['gst_name', 'gst_taxable', 'gst_cgst', 'gst_sgst']);
            }

            $printInvioce->addDivider('-', 20, false, false, 'separator');
            foreach ($detatch_gst as $index => $gst) {
                $printInvioce->tableStructure([$gst->name,
                    ' '.$gst->taxable,
                    $gst->cgst,
                    $gst->sgst,
                    $gst->cess],
                    [8,12, 7,7], 22, false, false, 'gst_item', ['gst_name', 'gst_taxable', 'gst_cgst', 'gst_sgst', 'gst_cess']);
            }

            $printInvioce->addDivider('-', 20, false, false, 'separator');
            $printInvioce->tableStructure(['Total',
                $taxable_amount,
                $total_csgt,
                $total_sgst 
            ], [8, 12, 7,7], 22, true, false, 'gst_order_summary', ['total', 'taxable_amount', 'total_csgt', 'total_sgst']);
        }

        $printInvioce->addDivider('-', 20, false, false, 'separator');
        }
        }
       $total_discount = (float)$order_details->discount+(float)$order_details->manual_discount+(float)$order_details->bill_buster_discount+(float)$tatalItemLevelManualDiscount;
        if($returnType == 'JSON'){
            $printInvioce->leftRightStructure('Saving', '', 22, false, false, 'saving');
        }else{
            if($v_id == 36){
                $printInvioce->leftRightStructure('Saving', format_number($total_savings_amount), 22, false, false, 'saving');
            }else{
                $printInvioce->leftRightStructure('Saving', $total_discount, 22, false, false, 'saving');
        }
        }
        $printInvioce->leftRightStructure('Total QTY', $cart_qty, 22, false, false, 'total_qty');
        if($order_details->transaction_type != "return"){
         $printInvioce->leftRightStructure('Total Sale', format_number($total_amount), 22, false, false, 'total_sale');
       }
       if(!empty($order_details->round_off)){
        $printInvioce->leftRightStructure('Round Off', $order_details->round_off, 22, false, false, 'rounded_off');    
        }


            // Closes Left & Start center
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        if($returnType == 'JSON'){

            $mop['name'] = '';
            $mop['amount'] = '';
            $printInvioce->leftRightStructure($mop['name'], round($mop['amount'],2), 22, false, false, 'mop');
            $printInvioce->addDivider('-', 20, false, false, 'separator');

        }else{

        if(!empty($mop_list)) {
            foreach ($mop_list as $mop) {
                if($mop['mode'] == 'credit_note_received') {
                    $mop['mode'] = 'Credit Note Redeemed';
                }
                $printInvioce->leftRightStructure($mop['mode'], format_number($mop['amount']), 22, false, false, 'mop');
            }
                $printInvioce->addDivider('-', 20, false, false, 'separator');
            }

        }
        if($order_details->transaction_type != "return"){
            if($returnType == 'JSON'){
                $printInvioce->leftRightStructure('Net Payable', $net_payable, 22, false, false, 'net_payable');
            }else{
                if($isOrderReciept) {
                    $printInvioce->leftRightStructure('Total Paid', format_number($order_details->TotalPayment), 22, false, false, 'total_paid');
                    $printInvioce->leftRightStructure('Amount Due', format_number($order_details->RemainingPayment), 22, true, true, 'amount_due');
                } else {
                    $printInvioce->leftRightStructure('Net Payable', format_number($net_payable), 22, false, false, 'net_payable');
                }
            }
        }
        
        if($v_id == 11 || $returnType == 'JSON'){
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        $printInvioce->addLineCenter(' Terms and Conditions', 22, true, false, 'terms_conditions_header');
        $printInvioce->addDivider('-', 20, false, false, 'separator');    
       }else{
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        $printInvioce->addLineLeft(' Terms and Conditions', 22, true, false, 'terms_conditions_header');
        $printInvioce->addDivider('-', 20, false, false, 'separator');       
       }
       foreach ($terms_conditions as $term) {
        $printInvioce->addTcLineLeft($term, 20, false, false, 'terms');
       }

       if($returnType == 'JSON') {
        $printInvioce->getEndLines('', 20, false, false, 'endlines');
       }
        // dd($printInvioce);

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
        // dd($printInvioce);
        if($returnType == 'JSON') {
            return $printInvioce->getFinalResult();
        } else {
            $response = ['status' => 'success', 
        'print_data' =>($printInvioce->getFinalResult())];
        }

        if($request->has('response_format') && $request->response_format == 'ARRAY'){
            return $response;
        }
        return response()->json($response, 200);

}


public function callGlobalTaxPrint($request){

        $v_id        = $request->v_id;
        $store_id    = $request->store_id;
        $c_id        = $request->c_id;
        $vu_id       = $request->vu_id;
        $order_id    = $request->order_id;
        $usefor      = !empty($request->usefor)?$request->usefor:'';
        $product_data= [];
        $gst_list    = [];
        $final_gst   = [];
        $detatch_gst = [];
        $rounded     = 0;
        $trans_from  = $request->trans_from;
        $returnType  = 'CUSTOM_HTML';
        if($request->has('return_type')){
            $returnType  = $request->return_type;
        }
        if($request->has('print_for') && !empty($request->print_for) && $request->print_for=='GV'){
            $print_for  = $request->print_for;
        }else{
            $print_for='';   
        }

        $bill_print_type = 0;
        $role        = VendorRoleUserMapping::select('role_id')->where('user_id', $vu_id)->first();
        $sParams     = ['v_id' => $v_id,'store_id'=>$store_id,'user_id'=>$c_id,'role_id'=>@$role->role_id,'trans_from' => $trans_from];
        $vendorS     = new VendorSettingController;
        $printSetting= $vendorS->getPrintSetting($sParams);
        if(count($printSetting) > 0){
            foreach($printSetting as $psetting){
                if($psetting->name == 'bill_print'){
                    $bill_print_type = $psetting->width;
                }
            }
        }
        
        if(!empty($request->type) && $request->type== 'account_deposite' || $request->type== 'adhoc_credit_note' ||  $request->type== 'refund_credit_note'){

            $acct  = new AccountsaleController;
            return $acct->get_deposite_recipt($request);
        }
        // dd($vendorS->getPrintSetting($sParams));
        $vendorC  = new VendorController;
        $crparams = array('v_id'=>$v_id,'store_id'=>$store_id,'vu_id'=>1,'info_type'=>'CURRENCY');
        $currency = $vendorC->getCurrencyDetail($crparams);
        $currencyR = explode(' ', $currency['name']);
        if($currencyR > 1){
            $len = count($currencyR);
            $currencyName = $currencyR[$len-1];
        }else{
            $currencyName  =  $currencyR ;
        }
        if($currency['code'] != 'INR'){
           return $this->callVatRecipt($request);
        }
        if($request->has('print_type') && $request->print_type == 'json'){
            $request = new \Illuminate\Http\Request();
            $request->merge([
                'v_id' => $v_id,
                'c_id' => $c_id,
                'store_id' => $store_id,
                'order_id' => $order_id
            ]);
            return $this->get_print_receipt_json($request);
        }
        
        $store         = Store::find($store_id);
        if($print_for=='GV'){
            $order_details = GiftVoucherInvoices::where('invoice_id', $order_id)->first();

        }else{
            $order_details = Invoice::where('invoice_id', $order_id)->first();
            
        }

        if($returnType == 'JSON') {
            $cart_product = [];
            $total_amount = 0;
            $order_details = (object)[ 'total' => 0, 'payvia' => [], 'transaction_type' => 'sales', 'vuser' => (object)[ 'first_name' => '', 'last_name' => '' ], 'user' => (object)[ 'mobile' => '', 'address' => (object)[ 'address1' => '', 'address2' => '', 'city' => '', 'state' => '', 'landmark' => '' ], 'hall_no' => '', 'seat_no' => '' ] ];
        } else {
            if($print_for=='GV'){

                $cart_q = GiftVoucherInvoiceDetails::where('gv_order_id', $order_details->custom_order_id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('customer_id', $order_details->customer_id)->count('gv_order_id');
                $cart_qty = $cart_q;

                $total_amount = GiftVoucherInvoiceDetails::where('gv_order_id', $order_details->custom_order_id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('customer_id', $order_details->customer_id)->sum('total');

                $cart_product = GiftVoucherInvoiceDetails::where('gv_order_id', $order_details->custom_order_id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('customer_id', $order_details->customer_id)->get();
            }else{

                $cart_q = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','0')->where('user_id', $order_details->user_id)->sum('qty');

                $cart_qt = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','1')->where('user_id', $order_details->user_id)->count('qty');

                $cart_qty = $cart_q + $cart_qt;

                $total_amount = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
                    // dd($total_amount);

                $cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->get();
            }
        }
        $count       = 1;
        $gst_tax     = 0;
        $gst_listing = [];
        $apply_tax   = [];
        $all_apply_tax = [];

        $total_savings_amount = 0;
        $tatalItemLevelManualDiscount=0;
        $i = 0;
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
            $itemLevelmanualDiscount=0;
             if($value->item_level_manual_discount!=null){
                $iLmd = json_decode($value->item_level_manual_discount);
                $itemLevelmanualDiscount= (float)$iLmd->discount;
             }
             if($itemLevelmanualDiscount>0){
              $tatalItemLevelManualDiscount  += $itemLevelmanualDiscount;
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
                'discount'      => $value->discount+$value->manual_discount + $value->bill_buster_discount+$itemLevelmanualDiscount,
                'rsp'           => $value->unit_mrp,
                'item_code'     => $value->barcode,
                'sm_value'      => '3',   
                'tax_per'       => $tdata->total_tax_per,
                'total'         => $value->total,
                'hsn'           => $tdata->hsn        
            ];


            // $gst_list[] = [
            //     'name'              => $tdata->tax_name,
            //     'wihout_tax_price'  => $tdata->taxable,
            //     'tax_amount'        => $tdata->tax,
            //     'cgst'              => $tdata->UGST_amt,
            //     'sgst'              => $tdata->VGST_amt,
            //     'cess'              => 0,
            //     'igst'              => 0
            // ];
             //print_r($gst_list);die;

            $gst_list[$i]['name']             = $tdata->tax_name;
            $gst_list[$i]['wihout_tax_price'] = $tdata->taxable;
            $gst_list[$i]['tax_amount']       = $tdata->tax;
                            
           foreach($tdata as $key=>$value){
             $exist  = strpos($key,'_amt');
             if($exist){
                $keyvalueRpl = str_replace('_amt','',$key);
                $keyvalue = $key;
                $gst_list[$i][$keyvalue] = $value;
                 $gst_list[$i]['value'][$keyvalue]  = $tdata->{$keyvalueRpl};
                $gst_list[$i]['apply_tax'][] = $key;
                $apply_tax[] = $key; 
                $all_apply_tax[] = $key;

             }
           }
           $i++;
        }

                    //print_r($gst_list);

                    //print_r($apply_tax);die;
                    $apply_tax   = array_unique($apply_tax);
                    $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
                    //dd($gst_list);
                    $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = $total_igst = 0 ;
                    $cgst = $sgst = $cess = $igst= 0 ;
                    foreach ($gst_listing as $key => $value) {

                       // dd($gst_list);
                        $tax_ab   = [];
                        $tax_cg   = [];
                        $tax_sg   = [];
                        $tax_ces  = [];
                        $tax_igst = [];
                        $tax_tot  = [];
                        foreach($apply_tax as $val){
                          $tax_tot[$val]  = 0;
                        }
                        
                        foreach ($gst_list as $keyD => $val) {

                            if ($val['name'] == $value) {
                                $total_gst      += str_replace(",", '', $val['tax_amount']);
                                $taxable_amount += str_replace(",", '', $val['wihout_tax_price']);
                                $tax_ab[]        =  str_replace(",", '', $val['wihout_tax_price']);

                               /* $tax_cg[]     =  str_replace(",", '', $val['cgst']);
                                $tax_sg[]     =  str_replace(",", '', $val['sgst']);
                                $tax_ces[]    =  str_replace(",", '', $val['cess']);
                                $tax_igst[]   =  str_replace(",", '', $val['igst']);
                                $cgst         += str_replace(",", '', $val['cgst']);
                                $sgst         += str_replace(",", '', $val['sgst']);
                                $cess         += str_replace(",", '', $val['cess']);
                                $igst         += str_replace(",", '', @$val['igst']);*/

                                //$apply_tax    = $val['apply_tax'];

                                if(isset($val['apply_tax'])){
                                    foreach($val['apply_tax'] as $getVal){
                                         $tax_cal[$getVal][] = $val[$getVal];
                                         $tax_tot[$getVal]   += $val[$getVal];
                                    }
                                }    

                                /*$final_gst[$value] = (object)[
                                'name'      => $value,
                                'taxable'   => array_sum($tax_ab),//$this->format_and_string($taxable_amount),
                                'cgst'      => round(array_sum($tax_cg),2),
                                'sgst'      => round(array_sum($tax_sg),2),
                                'cess'      => round(array_sum($tax_ces),2),
                                'igst'      => round(array_sum($tax_igst),2),
                                ];*/
                                $final_gst[$value]['name'] = $value;
                                $final_gst[$value]['taxable'] = array_sum($tax_ab);
                                $final_gst[$value]['value'] = isset($val['value'])?$val['value']:'';



                                if(isset($val['apply_tax'])){ 
                                    foreach($val['apply_tax'] as $getVal){

                                      $final_gst[$value][$getVal] = round(array_sum($tax_cal[$getVal]),2);
                                    }
                                }

                               /* $exist  = strpos($keyD,'amt');
                                if($tax_tot){
                                   $keyvalue = str_replace('_amt','',$keyD);
                                   $final_gst[$value][$keyvalue] +=  $val[$keyD];
                                }*/

                        // $total_taxable += $taxable_amount;

                    }
                }

               
            }
            /*$total_csgt = round($cgst,2);
            $total_sgst = round($sgst,2);
            $total_cess = round($cess,2);
            $total_igst = round($igst,2);*/
            // dd($final_gst);

            //print_r($final_gst);die;

            foreach ($final_gst as $key => $value) {
                $detatch_gst[] = (object)$value;
            }

           //print_r($detatch_gst);die;
            
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
                $paymeny_method = Mop::where('code', $payment->method)->first();
                // dd($paymeny_method->name);
                if ($payment->method == 'cash') {
                    $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
                    if($order_details->transaction_type == 'return'){
                        // $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
                        $mop_list[] = [ 'mode' => $paymeny_method->name, 'amount' => $payment->amount ];
                    }else{
                       // $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->cash_collected-$cashReturn ];
                       $mop_list[] = [ 'mode' => $paymeny_method->name, 'amount' => $payment->cash_collected-$cashReturn ];
                    }
                } else {
                    // $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
                    $mop_list[] = [ 'mode' => $paymeny_method->name, 'amount' => $payment->amount ];
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

            ########################
            ####### Print Start ####
            ########################
            //$terms_conditions =  array('(1) Exchange Within 7 days only.','(2) MRP Are Inclusive of Applicable Tax');

            $terms_conditions =  array('1. MRP Are Inclusive of Applicable Tax');
            if($v_id == 18){
                $terms_conditions =  array('1. MRP Are Inclusive of Applicable Tax', '2. Fresh Finds is a venture of Jubilant Consumer Pvt. Ltd.' );
            }

        if($order_details->transaction_type == 'return'){
            $invoice_title     = 'Credit Note';
        }else{
            $invoice_title     = 'Invoice Detail';    
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
        if($returnType == 'JSON') {

            $order_details->invoice_id = '';
            $taxable_amount = 1;
            $product_data = [];
            $cart_qty = '';
            $total_amount = '';
            $order_details->discount = '';
            $order_details->manual_discount = '';
            $order_details->bill_buster_discount = '';
            $order_details->total = '';
            $customer_paid = '';
            $balance_refund = '';
            $detatch_gst = [];
            $total_cess = '';
            $taxable_amount = '';
            $total_csgt = '';
            $total_sgst = '';
            $total_cess = '';
            // $total_discount = '';
            $cart_qty = '';
            $total_amount = '';
            $mop_list = '';
            $net_payable = '';

            $printInvioce = new PrintJsonInvoice($manufacturer_name[0], $printParams , $returnType);
        } else {
            $printInvioce = new PrintInvoice($manufacturer_name[0], $printParams , $returnType);
        }

        $printInvioce->addLineCenter($store->name, 24, true, false, 'store_name');
        $printInvioce->addLine($store->address1, 22, false, false, 'address_1');
        if($store->address2){
        $printInvioce->addLine($store->address2, 22, false, false, 'address_2');
        }
        $printInvioce->addLine($store->location.'-'.$store->pincode.', '.$store->state, 22, false, false, 'location');
        $printInvioce->addLine('Contact No: '.$store->contact_number, 22, false, false, 'contact_no');
        $printInvioce->addLine('E-mail: '.$store->email, 22, false, false, 'email');
        $printInvioce->addLine('GSTIN: '.$store->gst, 22, false, false, 'gst');
        if($store->cin){
        $printInvioce->addLine('CIN: '.$store->cin, 22, false, false, 'cin');            
        }
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        $printInvioce->addLine($invoice_title  , 22, true, false, 'invoice_title');
        $printInvioce->addDivider('-', 20, false, false, 'separator');

        $printInvioce->addLineLeft(' Invoice No : '.$order_details->invoice_id , 22, true, false, 'invoice_no');
        if ($order_details->transaction_type == 'return') {
            $refererence_order = Order::where(['order_id' => $order_details->ref_order_id, 'v_id' => $order_details->v_id, 'store_id' => $order_details->store_id ])->first();
            $printInvioce->addLineLeft(' Reference No : '.$refererence_order->ref_order_id , 22, true, false, 'refererence_no');
        }
        if($returnType == 'JSON'){
            $printInvioce->addLineLeft(' Date : ' , 22, false, false, 'invoice_date_time');
        }else{
            $printInvioce->addLineLeft(' Date : '.date('d-M-Y', strtotime($order_details->created_at))." at ".date('h:i:s A', strtotime($order_details->created_at)), 22, false, false, 'invoice_date_time');
        }
        $printInvioce->addLineLeft(' Cashier : '.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name , 22, false, false, 'cashier_name');
        $printInvioce->addLineLeft(' Customer Mobile : '.@$order_details->user->mobile , 22, false, false, 'customer_mob');

        if($v_id ==  57 || $v_id ==  54){
            $lastname = $order_details->user->last_name == 'Customer'?'':$order_details->user->last_name;
            $printInvioce->addLineLeft(' Customer Name : '.@$order_details->user->first_name.' '.@$lastname , 22, false, false, 'customer_name');    
        }
        if(isset($order_details->comm_trans) && $order_details->comm_trans == 'B2B'){
             $printInvioce->addLineLeft(' Customer GSTIN : '.@$order_details->cust_gstin, 22, false, false, 'customer_gstin');  
        }

        /***************************************/
            # Customer Address When Resturant Type #
        /**************************************/

        if($store->type == 5 || $store->type == 6){
        if(isset($order_details->user->address->address1)){
        $printInvioce->addLineLeft(' Customer Address : '.$order_details->user->address->address1 , 22, false, false, 'customer_address');
        if($order_details->user->address->address2){
         $printInvioce->addLineLeft(' '.$order_details->user->address->address2 , 22, false, false, 'customer_address_2');
        }
        if($order_details->user->address->city){
         $printInvioce->addLineLeft($order_details->user->address->city.', '.$order_details->user->address->state , 22, false, false, 'customer_city');
        }
        if($order_details->user->address->landmark){
         $printInvioce->addLineLeft('Landmark: '.$order_details->user->address->landmark , 22, false, false, 'customer_landmark');
        }
        }
        }


        if($order_details->user->hall_no){
        $printInvioce->addLineLeft(' Hall No : '.$order_details->user->hall_no , 22, false, false. 'hall_no');
        }
        if($order_details->user->seat_no){
        $printInvioce->addLineLeft(' Table No : '.$order_details->user->seat_no , 22, false, false, 'seat_no');
        }

        $printInvioce->addDivider('-', 20, false, false, 'separator');



        $printInvioce->tableStructure(['#', 'Item', 'Rate','Qty','Tax Amt', 'Amount'], [3, 8, 6, 5, 5, 7], 22, false, false, 'item_header_1', ['sr_no','name', 'rate', 'qty', 'tax_amt', 'total']);
        
        if($returnType == 'JSON'){
            $printInvioce->tableStructure(['Barcode','hsn', 'Disc'], [18,10, 6], 22, false, false, 'item_header_2', ['barcode', 'hsn', 'disc']);
        }else{
            if($taxable_amount > 0){
        $printInvioce->tableStructure(['Barcode','hsn', 'Disc'], [18,10, 6], 22, false, false, 'item_header_2', ['barcode', 'hsn', 'disc']);
        }else{
        $printInvioce->tableStructure(['Barcode','', 'Disc  '], [22,2 , 10], 22, false, false, 'item_header_2', ['barcode', '', 'disc']);
        }
        }

        $printInvioce->addDivider('-', 20, false, false, 'separator');

        if($returnType == 'JSON'){

            $printInvioce->tableStructure([
                $product_data[0] = '',
                $product_data[1] = '',
                $product_data[2] = '',
                $product_data[3] = '',
                $product_data[4] = '',
                $product_data[5] = ''
            ],
            [3, 8, 6,5,5,7], 22, false, false, 'item_detail_1', ['sr_no', 'name', 'rate', 'qty', 'tax_amt', 'total']);
            $printInvioce->tableStructure([
                ' '.$product_data[6] = '',
                $product_data[7] = '',
                $product_data[8] = ''
            ],
            [18,10, 6], 22, false, false, 'item_detail_2', ['item_code', 'hsn', 'discount']);

        }else{

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
            [3, 8, 6,5,5,7], 22, false, false, 'item_detail_1', ['sr_no', 'name', 'rate', 'qty', 'tax_amt', 'total']);

        } else {

            $printInvioce->tableStructure([
                ' '.$product_data[$i]['item_code'],
                $taxable_amount?$product_data[$i]['hsn']:'',
                $product_data[$i]['discount']
            ],
            [18,10, 6], 22, false, false, 'item_detail_2', ['item_code', 'hsn', 'discount']);

                }
            }
        }
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        $printInvioce->tableStructure(['Total', $cart_qty, $total_amount], [20, 4,14], 22, true, false, 'order_total', ['total', 'cart_qty', 'total_amount']);
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        if($returnType == 'JSON'){
            $printInvioce->addLineLeft(ucfirst($currencyName).': '.($order_details->total) , 22, false, false, 'amount_words');
        }else{
            $printInvioce->addLineLeft(ucfirst($currencyName).': '.ucfirst(numberTowords(round($order_details->total))).' Only' , 22, false, false, 'amount_words');
        }

        $printInvioce->addDivider('-', 20, false, false, 'separator');
        if($customer_paid > 0){
            $printInvioce->addLineLeft('  Customer Paid: '.format_number($customer_paid), 22, true, false, 'customer_paid');
            $printInvioce->addLineLeft('  Balance Refund: '.format_number($balance_refund), 22, true, false, 'balance_refund');
        }

        if($returnType == 'JSON'){    
            $printInvioce->addLineLeft('Customer Paid'.($customer_paid), 22, true, false, 'customer_paid');
            $printInvioce->addLineLeft('Balance Refund'.($balance_refund), 22, true, false, 'balance_refund');
        }
        if($customer_paid!=0 || $returnType == 'JSON'){
        $printInvioce->addDivider('-', 20, false, false, 'separator');

        }
        /*Tax Start */


        if($returnType == 'JSON' && $taxable_amount == ''){
            $printInvioce->leftRightStructure('Tax Summary','', 22, false, false, 'gst_summary');
            $printInvioce->addDivider('-', 20, false, false, 'separator');

            $printInvioce->tableStructure(['Desc', 'Taxable', 'CGST','SGST','CESS'], [8,9, 6,6,5], 22, false, false, 'gst_header', ['desc', 'taxable', 'cgst', 'sgst', 'cess']);
                $printInvioce->addDivider('-', 20, false, false, 'separator');
                $gst['name'] = '';
                $gst['taxable'] = '';
                $gst['cgst'] = '';
                $gst['sgst'] = '';
                $gst['cess'] = '';
                // foreach ($detatch_gst as $index => $gst) {
                    $printInvioce->tableStructure([$gst['name'],
                        ' '.$gst['taxable'],
                        $gst['cgst'],
                        $gst['sgst'],
                        $gst['cess']],
                        [8,9, 6,6,5], 22, false, false, 'gst_listing', ['gst_name', 'gst_taxable', 'gst_cgst', 'gst_sgst', 'gst_cess']);
                // }
                $printInvioce->addDivider('-', 20, false, false, 'separator');
                $printInvioce->tableStructure(['Total',
                    $taxable_amount,
                    $total_csgt,
                    $total_sgst,
                    $total_cess], [8, 9, 6,6,5], 22, true, false, 'gst_item', ['total', 'tax_amt', 'total_csgt', 'total_sgst', 'total_cess']);

            //     $printInvioce->tableStructure(['Total',
            //     $taxable_amount,
            //     $total_csgt,
            //     $total_sgst 
            // ], [8, 12, 7,7], 22, true, false, 'gst_order_summary', ['total', 'taxable_amount', 'total_csgt', 'total_sgst']);
                $printInvioce->addDivider('-', 20, false, false, 'separator');
        }

        if($taxable_amount > 0){

        $printInvioce->leftRightStructure('GST Summary','', 22, false, false, 'gst_summary');
        $printInvioce->addDivider('-', 20, false, false, 'separator');

        if(!empty($detatch_gst)) {

            if($total_igst > 0){
                $printInvioce->tableStructure(['Desc', 'Taxable', 'IGST'], [8,9, 17], 22, false, false, 'gst_header', ['desc', 'taxable', 'igst']);
                        //$printInvioce->tableStructure(['', 'Amt','Amt','Amt','Amt'], [8, 8, 6,6,6], 22);
                $printInvioce->addDivider('-', 20, false, false, 'separator');
                foreach ($detatch_gst as $index => $gst) {
                    $printInvioce->tableStructure([$gst->name,
                        ' '.$gst->taxable.'('.$gst->value.')',
                        $gst->igst
                        
                        ],
                        [8,9, 17], 22, false, false, 'gst_listing', ['gst_name', 'gst_taxable', 'gst_cgst', 'gst_sgst', 'gst_cess']);
                }
                $printInvioce->addDivider('-', 20, false, false, 'separator');
                $printInvioce->tableStructure(['Total',
                    format_number($taxable_amount),
                    format_number($total_igst)], [8, 9, 17], 22, true, false, 'gst_item', ['total', 'tax_amt', 'total_igst']);
            }else{
                $display_tax = ['Desc', 'Taxable'];
                $tax_factor  = [8,12];
                if(isset($val['apply_tax'])){
                    foreach ($apply_tax as $value) {
                        $keyval = str_replace('amt', '', $value);
                        $keyval = str_replace('_', '', $keyval);

                       $display_tax[] = $keyval;
                       $tax_factor[]  = floor(14/count($apply_tax));
                    }
                }    
            /*
             $printInvioce->tableStructure($display_tax, $tax_factor, 22, false, false, 'gst_header', ['desc', 'taxable', 'cgst', 'sgst']);
                        //$printInvioce->tableStructure(['', 'Amt','Amt','Amt','Amt'], [8, 8, 6,6,6], 22);

             $printInvioce->addDivider('-', 20, false, false, 'separator');


             foreach ($detatch_gst as $index => $gst) {
                 
                $display_tax_name = [$gst->name,' '.$gst->taxable];
                $tax_factor_code  = [8,12];
                foreach ($apply_tax as $value) {
                if(isset($gst->$value)){
                   $display_tax_name[] = $gst->$value;
                   $tax_factor_code[]  = floor(14/count($apply_tax));
                   }
                }

                $printInvioce->tableStructure($display_tax_name,
                $tax_factor_code, 22, false, false, 'gst_item', ['gst_name', 'gst_taxable', 'gst_cgst', 'gst_sgst']);
            }
            */

 
            $printInvioce->tableStructure(['Desc', 'Taxable', 'Tax'], [12,10,12], 22);
            $printInvioce->addDivider('-', 20, false, false, 'separator');
            //print_r($detatch_gst);die;
            $getTotalTax  = 0;
             foreach ($detatch_gst as $index => $gst) {
            foreach ($apply_tax as $value) {
                if(isset($gst->$value)){

                    $taxPer=$gst->value[$value];
                   //$display_tax_name[] = $gst->$value;
                   //$tax_factor_code[]  = floor(14/count($apply_tax));
                    $keyval = str_replace('amt', '', $value);
                    $keyval = str_replace('_', '', $keyval);
                    $printInvioce->tableStructure([$keyval."(%$taxPer)",$gst->taxable, $gst->$value], [12,10,12], 22);
                    $getTotalTax += $gst->$value;


                   }
                }
            }




            /*$printInvioce->addDivider('-', 20, false, false, 'separator');
             foreach ($detatch_gst as $index => $gst) {
                $display_tax_name = [$gst->name,' '.$gst->taxable];
                $tax_factor_code  = [8,12];
                foreach ($apply_tax as $value) {
                   $display_tax_name[] = $gst->$value;
                   $tax_factor_code[]  = floor(14/count($apply_tax));
                }
                $printInvioce->tableStructure($display_tax_name,
                $tax_factor_code, 22, false, false, 'gst_item', ['gst_name', 'gst_taxable', 'gst_cgst', 'gst_sgst']);
            }*/

            $printInvioce->addDivider('-', 20, false, false, 'separator');
            //print_r($tax_tot);die;

            foreach($tax_tot as $key => $tot){
              $display_total_tax[] = $tot;
              $total_tax_factor[] = floor(14/count($tax_tot));
            }



            /*$printInvioce->tableStructure(['Total', $taxable_amount, array_sum($total_tax_factor)],[10,9,15], 22, true, false, 'gst_order_summary', ['total', 'taxable_amount', 'total_csgt', 'total_sgst']);*/

             $printInvioce->tableStructure(['Subtotal', '', $taxable_amount],[10,9,15], 22, true, false, 'gst_order_summary', ['total', 'taxable_amount', 'total_csgt', 'total_sgst']);
             $printInvioce->tableStructure(['Tax', '', $getTotalTax],[10,9,15], 22, true, false, 'gst_order_summary', ['total', 'taxable_amount', 'total_csgt', 'total_sgst']);
             $printInvioce->tableStructure(['Total', '', $taxable_amount+$getTotalTax],[10,9,15], 22, true, false, 'gst_order_summary', ['total', 'taxable_amount', 'total_csgt', 'total_sgst']);




            /*$display_total_tax = ['Total',$taxable_amount];
            $total_tax_factor  = [8,12];
            //print_r($tax_tot['UGST_amt']);
            foreach($tax_tot as $key => $tot){
              $display_total_tax[] = $tot;
              $total_tax_factor[] = floor(14/count($tax_tot));
            }

            //print_r($display_total_tax);die;

            $printInvioce->tableStructure($display_total_tax,$total_tax_factor, 22, true, false, 'gst_order_summary', ['total', 'taxable_amount', 'total_csgt', 'total_sgst']);*/

        }

        $printInvioce->addDivider('-', 20, false, false, 'separator');
        }
        }
       $total_discount = (float)$order_details->discount+(float)$order_details->manual_discount+(float)$order_details->bill_buster_discount+(float)$tatalItemLevelManualDiscount;
        if($returnType == 'JSON'){
            $printInvioce->leftRightStructure('Saving', '', 22, false, false, 'saving');
        }else{
            if($v_id == 36){
                $printInvioce->leftRightStructure('Saving', format_number($total_savings_amount), 22, false, false, 'saving');
            }else{
                $printInvioce->leftRightStructure('Saving', $total_discount, 22, false, false, 'saving');
        }
        }
        $printInvioce->leftRightStructure('Total QTY', $cart_qty, 22, false, false, 'total_qty');
        if($order_details->transaction_type != "return"){
         $printInvioce->leftRightStructure('Total Sale', format_number($total_amount), 22, false, false, 'total_sale');
       }
        if(!empty($order_details->round_off)){
        $printInvioce->leftRightStructure('Round Off', $order_details->round_off, 22, false, false, 'rounded_off');    
        }


            // Closes Left & Start center
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        if($returnType == 'JSON'){

            $mop['name'] = '';
            $mop['amount'] = '';
            $printInvioce->leftRightStructure($mop['name'], round($mop['amount'],2), 22, false, false, 'mop');
            $printInvioce->addDivider('-', 20, false, false, 'separator');

        }else{

        if(!empty($mop_list)) {
            foreach ($mop_list as $mop) {
                $printInvioce->leftRightStructure($mop['mode'], round($mop['amount'],2), 22, false, false, 'mop');
            }
                $printInvioce->addDivider('-', 20, false, false, 'separator');
            }

        }
        if($order_details->transaction_type != "return"){
        if($returnType == 'JSON'){
            $printInvioce->leftRightStructure('Net Payable', $net_payable, 22, false, false, 'net_payable');
        }else{
            $printInvioce->leftRightStructure('Net Payable', format_number($net_payable), 22, false, false, 'net_payable');
        }
    }
        
        if($v_id == 11 || $returnType == 'JSON'){
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        $printInvioce->addLineCenter(' Terms and Conditions', 22, true, false, 'terms_conditions_header');
        $printInvioce->addDivider('-', 20, false, false, 'separator');    
       }else{
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        $printInvioce->addLineLeft(' Terms and Conditions', 22, true, false, 'terms_conditions_header');
        $printInvioce->addDivider('-', 20, false, false, 'separator');       
       }
       foreach ($terms_conditions as $term) {
        $printInvioce->addTcLineLeft($term, 20, false, false, 'terms');
       }

       if($returnType == 'JSON') {
        $printInvioce->getEndLines('', 20, false, false, 'endlines');
       }
        // dd($printInvioce);

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

       /* $detailQrCode  = array('Supplier Gstin:'  =>isset($order_details->cust_gstin)?$order_details->cust_gstin:'',
                       'Supplier UPI ID:' =>'',
                       //'Payees Bank Account number and IFSC:'=>,
                       $mop_list,
                       'Invoice number:'      => $order_details->invoice_id,
                       'Invoice Date:'        => $order_details->date,
                       'CGST Amount:'         => $gst['cgst'],
                       'SGST Amount:'         => $gst['sgst'],
                       'CESS Amount:'         => $gst['cess'],
                       'Total invoice value'  => $total_amount
               
                );


        print_r($detailQrCode);die;*/

        if($returnType == 'JSON') {
            return $printInvioce->getFinalResult();
        } else {
            $response = ['status' => 'success', 
        'print_data' =>($printInvioce->getFinalResult())];
        }

        if($request->has('response_format') && $request->response_format == 'ARRAY'){
            return $response;
        }
        return response()->json($response, 200);



}//End of callGlobalTaxPrint

public function callCustomPrint($request){

    $v_id       =  $request->v_id;
    $store_id   =  $request->store_id; 
    $c_id       =  $request->c_id;
    $order_id   =  $request->order_id;
    $product_data= [];
    $gst_list    = [];
    $final_gst   = [];
    $detatch_gst = [];
    $rounded     = 0;

    if($order_id == null){
        return null;
    }

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
            'rate'          => "$rate",
            'name'          => $value->item_name,
        ];
        $product_data[] = [
            'row'           => 2,
            'rsp'           => $value->unit_mrp,
            'rate'          => "$rate",
            'qty'           => $value->qty,
            'tax_amt'       => $value->tax,
            'total'         => $value->total, 
            'sm_value'      => '3',   
            'tax_per'       => $tdata->cgst + $tdata->sgst                        
        ];

        $product_data[]   = [
            'row'           => 3,
            'item_code'     => $value->barcode,
            'hsn'           => $tdata->hsn,
            'discount'      => $value->discount+$value->manual_discount + $value->bill_buster_discount
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
        $tax_ces= [];

        foreach ($gst_list as $val) {

            if ($val['name'] == $value) {
                $total_gst      += str_replace(",", '', $val['tax_amount']);
                $taxable_amount += str_replace(",", '', $val['wihout_tax_price']);
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
            $paymeny_method = Mop::where('code', $payment->method)->first();
                // dd($paymeny_method->name);
            if ($payment->method == 'cash') {
                $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
                // $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->cash_collected-$cashReturn ];
                $mop_list[] = [ 'mode' => $paymeny_method->name, 'amount' => $payment->cash_collected-$cashReturn ];
            } else {
                // $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
                $mop_list[] = [ 'mode' => $paymeny_method->name, 'amount' => $payment->amount ];
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

        $terms_conditions =  array('1.Goods once sold will not be taken back.','2. All disputes will be settled in Jaipur court.','3. E. & O.E.','4. This is computer generated invoice and does not require any stamp or signature');  
        //if($v_id == 63){
          $terms_conditions =  array('1.Products can be exchanged within 7 days from date of purchase.',
            '2.Original receipt/invoice copy must be carried.','3.In the case of exchange – the product must be in it’s original and unused condition, along with all the original price tags, packing/boxes and barcodes received.','4.We will only be able to exchange products. Afraid there will not be refund in any form.','5.We do not offer any kind of credit note at the store.','6.There will be no exchange on discounted/sale products.','7.In the case of damage or defect – the store teams must be notified within 24 hours of purchase');  

          if($v_id == 57){
                $terms =  Terms::where('v_id',$v_id)->get();
                $terms_condition = json_decode($terms);
                foreach ($terms_condition as $value) {
                    $terms_conditions = $arrayName = json_decode($value->terms_conditions);
                }
                // $terms_conditions =  array('1.Products displayed/ sold by Aadyam Handwoven are manufactured as per the applicable local laws of India and are in conformity with the required Indian industry standards.',
                //     '2.The price of our merchandise is the Maximum Retail Price (MRP) for the said product. Such MRP shall be inclusive of all local taxes as are applicable in India.'
                //     ,'3.Exchanges will be entertained only within 30 days, if-Products to be exchanged are not damaged and in re-sellable condition-All tags are present-Invoice hard/soft copy should be present'
                //     ,'4.Credit note will only be authorized under the discretion of the Management.',
                //     '5.Goods once sold will not be returned for cash or reverse payment to credit/debit cards. However, it can be exchanged as per the exchange policy',
                //     '6.Gift cards cannot be returned or exchanged for cash, however they can be redeemed for product.',
                //     '7.Our products are handwoven and does not aim to maintain consistency in design. Certain flaws in the product are characteristic of handwoven products.',
                //     '8.Please refer to the wash care instructionsof each product to ensure longevity of product',
                //     '9.Deliveries to different locations will be accepted at an additional cost (depending on location) at the sole discretion of Aadyam Handwoven',
                //     '10.The tax rate applied and charged upon the order shall include combined tax rate for both state and local tax rates in accordance with the address where his/herorder is being shipped.',
                //     '11.Aadyam Handwoven (Grasim Jana Seva Trust) reserves the right to collect taxes and/or such other levy/ duty/ surcharge that it may have to incur in addition to the normal taxes it may have to pay.',
                //     '12.Aadyam Handwoven (Grasim Jana Seva Trust) reserves the right to change and/or update their terms and conditions without giving prior notice.'
                // );
            
          }
      //}

      if($order_details->transaction_type == 'return'){
         $invoice_title     = 'Credit Note';
     }else{
         $invoice_title     = 'Tax Invoice Detail';
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

$printInvioce->addLineLeft(' Invoice No : '.$order_details->invoice_id , 22,true);
if ($order_details->transaction_type == 'return') {
    $refererence_order = Order::where(['order_id' => $order_details->ref_order_id, 'v_id' => $order_details->v_id, 'store_id' => $order_details->store_id ])->first();
    $printInvioce->addLineLeft(' Reference No : '.$refererence_order->ref_order_id , 22,true);
}
$printInvioce->addLineLeft(' Date : '.date('d-M-Y', strtotime($order_details->created_at))." at ".date('h:i:s A', strtotime($order_details->created_at)), 22);
if($v_id != 53){
    $printInvioce->addLineLeft(' Cashier : '.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name , 22);
    $printInvioce->addLineLeft(' Customer Mobile : '.@$order_details->user->mobile , 22);
}
if(  $v_id ==  57){
    $lastname = $order_details->user->last_name == 'Customer'?'':$order_details->user->last_name;
    $printInvioce->addLineLeft(' Customer Name : '.@$order_details->user->first_name.' '.@$lastname , 22, false, false, 'customer_name');    
}


if($v_id ==  26){
    $lastname = $order_details->user->last_name == 'Customer'?'':$order_details->user->last_name;
    $printInvioce->addLineLeft(' Customer Name : '.@$order_details->user->first_name.' '.@$lastname , 22, false, false, 'customer_name');    
}

if(isset($order_details->comm_trans) && $order_details->comm_trans == 'B2B'){
 $printInvioce->addLineLeft(' Customer GSTIN : '.@$order_details->cust_gstin, 22, false, false, 'customer_gstin');  
}

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

                    $printInvioce->tableStructure(['#', 'Item'], [3,31], 22);
                    $printInvioce->tableStructure(['Rate','Qty','Tax', 'Amt'], [9,7,8, 10], 22);
                    $printInvioce->tableStructure(['Barcode','Hsn', 'Disc'], [20,8, 6], 22);

                    $printInvioce->addDivider('-', 20);


                    for($i = 0; $i < count($product_data); $i++) {
                        if($product_data[$i]['row'] == 1) {
                            $printInvioce->tableStructure([
                                $product_data[$i]['sr_no'],
                                $product_data[$i]['name'],
                            ],
                            [3,31], 22);
                        }
                        if($product_data[$i]['row'] == 2)  {
                            $printInvioce->tableStructure([
                                $product_data[$i]['rate'],
                                $product_data[$i]['qty'],
                                $product_data[$i]['tax_amt'],
                                $product_data[$i]['total']
                            ],
                            [9,7,8, 10], 22);
                        }
                        if($product_data[$i]['row'] == 3){
                            $printInvioce->tableStructure([
                                $product_data[$i]['item_code'],
                                $taxable_amount?$product_data[$i]['hsn']:'',
                                $product_data[$i]['discount']
                            ],
                            [20,8, 6], 22);
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

                            if($total_cess > 0){
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
                            }else{
                             $printInvioce->tableStructure(['Desc', 'Taxable', 'CGST','SGST'], [8,12, 7,7], 22);
                    //$printInvioce->tableStructure(['', 'Amt','Amt','Amt','Amt'], [8, 8, 6,6,6], 22);

                             $printInvioce->addDivider('-', 20);
                             foreach ($detatch_gst as $index => $gst) {
                                $printInvioce->tableStructure([$gst->name,
                                    ' '.$gst->taxable,
                                    $gst->cgst,
                                    $gst->sgst 
                                ],
                                [8,12, 7,7], 22);
                            }

                            $printInvioce->addDivider('-', 20);
                            foreach ($detatch_gst as $index => $gst) {
                                $printInvioce->tableStructure([$gst->name,
                                    ' '.$gst->taxable,
                                    $gst->cgst,
                                    $gst->sgst,
                                    $gst->cess],
                                    [8,12, 7,7], 22);
                            }

                            $printInvioce->addDivider('-', 20);
                            $printInvioce->tableStructure(['Total',
                                format_number($taxable_amount),
                                format_number($total_csgt),
                                format_number($total_sgst) 
                            ], [8, 12, 7,7], 22, true);
                        }

                        $printInvioce->addDivider('-', 20);
                    }
                }
                $total_discount = $order_details->discount+$order_details->manual_discount+$order_details->bill_buster_discount;
                $printInvioce->leftRightStructure('Saving', $total_discount, 22);
                $printInvioce->leftRightStructure('Total QTY', $cart_qty, 22);
                $printInvioce->leftRightStructure('Total Sale', format_number($total_amount), 22);
                if(!empty($order_details->round_off)){
                $printInvioce->leftRightStructure('Round Off', $order_details->round_off, 22, false, false, 'rounded_off');    
                }

                // Closes Left & Start center
                $printInvioce->addDivider('-', 20);
                if(!empty($mop_list)) {
                    foreach ($mop_list as $mop) {
                        $printInvioce->leftRightStructure($mop['mode'], $mop['amount'], 22);
                    }
                    $printInvioce->addDivider('-', 20);
                }
                $printInvioce->leftRightStructure('Net Payable', format_number($net_payable), 22);

                if($v_id != 53){

                    $printInvioce->addDivider('-', 20);
                    $printInvioce->addLineLeft(' Terms and Conditions', 22, true);
                    $printInvioce->addDivider('-', 20);
                    foreach ($terms_conditions as $term) {
                        $printInvioce->addLineLeft($term, 20);
                    }

                }
                $response = ['status' => 'success', 
                'print_data' =>($printInvioce->getFinalResult())];

                if($request->has('response_format') && $request->response_format == 'ARRAY'){
                    return $response;
                }
                return response()->json($response, 200);   

    }//End of callCustomPrint


    public function callCustomPrintWithVat(Request $request){

        $v_id        = $request->v_id;
        $store_id    = $request->store_id;
        $c_id        = $request->c_id;
        $vu_id       = $request->vu_id;
        $order_id    = $request->order_id;
        $usefor      = !empty($request->usefor)?$request->usefor:'';
        $product_data= [];
        $gst_list    = [];
        $final_gst   = [];
        $detatch_gst = [];
        $rounded     = 0;
        $trans_from  = $request->trans_from;
        $returnType  = 'CUSTOM_HTML';
        if($request->has('return_type')){
            $returnType  = $request->return_type;

        }

        $bill_print_type = 0;
        $role        = VendorRoleUserMapping::select('role_id')->where('user_id', $vu_id)->first();
        $sParams     = ['v_id' => $v_id,'store_id'=>$store_id,'user_id'=>$c_id,'role_id'=>@$role->role_id,'trans_from' => $trans_from];
        $vendorS     = new VendorSettingController;
        $printSetting= $vendorS->getPrintSetting($sParams);
        if(count($printSetting) > 0){
            foreach($printSetting as $psetting){
                if($psetting->name == 'bill_print'){
                    $bill_print_type = $psetting->width;
                }
            }
        }          

        $vendorC  = new VendorController;
        $crparams = array('v_id'=>$v_id,'store_id'=>$store_id,'vu_id'=>1,'info_type'=>'CURRENCY');
        $currency = $vendorC->getCurrencyDetail($crparams);
        $currencyR = explode(' ', $currency['name']);
        if($currencyR > 1){
            $len = count($currencyR);
            $currencyName = $currencyR[$len-1];
        }else{
            $currencyName  =  $currencyR ;
        }
       

        $store         = Store::find($store_id);
        $order_details = Invoice::where('invoice_id', $order_id)->first();

        if($returnType == 'JSON') {
            $cart_product = [];
            $total_amount = 0;
            $order_details = (object)[ 'total' => 0, 'payvia' => [], 'transaction_type' => 'sales', 'vuser' => (object)[ 'first_name' => '', 'last_name' => '' ], 'user' => (object)[ 'mobile' => '', 'address' => (object)[ 'address1' => '', 'address2' => '', 'city' => '', 'state' => '', 'landmark' => '' ], 'hall_no' => '', 'seat_no' => '' ] ];
        } else {
            $cart_q = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','0')->where('user_id', $order_details->user_id)->sum('qty');

            $cart_qt = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','1')->where('user_id', $order_details->user_id)->count('qty');

            $cart_qty = $cart_q + $cart_qt;

            $total_amount = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
                // dd($total_amount);

            $cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->get();
        }
        $count = 1;
        $gst_tax = 0;
        $gst_listing = [];


        $total_savings_amount = 0;
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
            $itemLevelmanualDiscount=0;
             if($value->item_level_manual_discount!=null){
                $iLmd = json_decode($value->item_level_manual_discount);
                $itemLevelmanualDiscount= (float)$iLmd->discount;
             }

                    $product_data[]  = [
                        'row'           => 1,
                        'sr_no'         => $count++,
                        'rate'          => "$rate",
                        'name'          => $value->item_name,
                    ];
                    $product_data[] = [
                        'row'           => 2,
                        'rsp'           => $value->unit_mrp,
                        'rate'          => "$rate",
                        'qty'           => $value->qty,
                        'tax_amt'       => $value->tax,
                        'total'         => $value->total, 
                        'sm_value'      => '3',   
                        'tax_per'       => $tdata->cgst + $tdata->sgst                        
                    ];

                    $product_data[]   = [
                    'row'           => 3,
                    'item_code'     => $value->barcode,
                    'hsn'           => $tdata->hsn,
                    'discount'      => $value->discount+$value->manual_discount + $value->bill_buster_discount
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
                $paymeny_method = Mop::where('code', $payment->method)->first();
                // dd($paymeny_method->name);
                if ($payment->method == 'cash') {
                    $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
                    if($order_details->transaction_type == 'return'){
                        // $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
                        $mop_list[] = [ 'mode' => $paymeny_method->name, 'amount' => $payment->amount ];
                    }else{
                       // $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->cash_collected-$cashReturn ];
                        $mop_list[] = [ 'mode' => $paymeny_method->name, 'amount' => $payment->cash_collected-$cashReturn ];
                    }
                } else {
                    // $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
                    $mop_list[] = [ 'mode' => $paymeny_method->name, 'amount' => $payment->amount ];
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

            ########################
            ####### Print Start ####
            ########################
            //$terms_conditions =  array('(1) Exchange Within 7 days only.','(2) MRP Are Inclusive of Applicable Tax');

            $terms_conditions =  array('1. MRP Are Inclusive of Applicable Tax'); 
            if($order_details->transaction_type == 'return'){
                $invoice_title     = 'Credit Note';
            }else{
                $invoice_title     = 'Tax invoice';
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
        if($returnType == 'JSON') {
            $order_details->invoice_id = '';
            $taxable_amount = 1;
            $product_data = [];
            $cart_qty = '';
            $total_amount = '';
            $order_details->discount = '';
            $order_details->manual_discount = '';
            $order_details->bill_buster_discount = '';
            $order_details->total = '';
            $customer_paid = '';
            $balance_refund = '';
            $detatch_gst = [];
            $total_cess = '';
            $taxable_amount = '';
            $total_csgt = '';
            $total_sgst = '';
            $total_cess = '';
            // $total_discount = '';
            $cart_qty = '';
            $total_amount = '';
            $mop_list = '';
            $net_payable = '';

            $printInvioce = new PrintJsonInvoice($manufacturer_name[0], $printParams , $returnType);
        } else {
            $printInvioce = new PrintInvoice($manufacturer_name[0], $printParams , $returnType);
        }

        $printInvioce->addLineCenter($store->name, 24, true, false, 'store_name');
        $printInvioce->addLine($store->address1, 22, false, false, 'address_1');
        if($store->address2){
        $printInvioce->addLine($store->address2, 22, false, false, 'address_2');
        }
        $printInvioce->addLine($store->location.'-'.$store->pincode.', '.$store->state, 22, false, false, 'location');
        $printInvioce->addLine('Contact No: '.$store->contact_number, 22, false, false, 'contact_no');
        $printInvioce->addLine('E-mail: '.$store->email, 22, false, false, 'email');
        $printInvioce->addLine('VAT NO: '.$store->gst, 22, false, false, 'gst');
        if($store->cin){
        $printInvioce->addLine('CIN: '.$store->cin, 22, false, false, 'cin');            
        }
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        $printInvioce->addLine($invoice_title  , 22, true, false, 'invoice_title');
        $printInvioce->addDivider('-', 20, false, false, 'separator');

        $printInvioce->addLineLeft(' Invoice No : '.$order_details->invoice_id , 22, true, false, 'invoice_no');
        if ($order_details->transaction_type == 'return') {
            $refererence_order = Order::where(['order_id' => $order_details->ref_order_id, 'v_id' => $order_details->v_id, 'store_id' => $order_details->store_id ])->first();
            $printInvioce->addLineLeft(' Reference No : '.$refererence_order->ref_order_id , 22, true, false, 'refererence_no');
        }
        if($returnType == 'JSON'){
            $printInvioce->addLineLeft(' Date : ' , 22, false, false, 'invoice_date_time');
        }else{
            $printInvioce->addLineLeft(' Date : '.date('d-M-Y', strtotime($order_details->created_at))." at ".date('h:i:s A', strtotime($order_details->created_at)), 22, false, false, 'invoice_date_time');
        }
        $printInvioce->addLineLeft(' Cashier : '.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name , 22, false, false, 'cashier_name');
        $printInvioce->addLineLeft(' Customer Mobile : '.@$order_details->user->mobile , 22, false, false, 'customer_mob');

        if($v_id ==  57 || $v_id ==  54){
            $lastname = $order_details->user->last_name == 'Customer'?'':$order_details->user->last_name;
            $printInvioce->addLineLeft(' Customer Name : '.@$order_details->user->first_name.' '.@$lastname , 22, false, false, 'customer_name');    
        }
        if(isset($order_details->comm_trans) && $order_details->comm_trans == 'B2B'){
             $printInvioce->addLineLeft(' Customer GSTIN : '.@$order_details->cust_gstin, 22, false, false, 'customer_gstin');  
        }

        /***************************************/
            # Customer Address When Resturant Type #
        /**************************************/

        if($store->type == 5 || $store->type == 6){
        if(isset($order_details->user->address->address1)){
        $printInvioce->addLineLeft(' Customer Address : '.$order_details->user->address->address1 , 22, false, false, 'customer_address');
        if($order_details->user->address->address2){
         $printInvioce->addLineLeft(' '.$order_details->user->address->address2 , 22, false, false, 'customer_address_2');
        }
        if($order_details->user->address->city){
         $printInvioce->addLineLeft($order_details->user->address->city.', '.$order_details->user->address->state , 22, false, false, 'customer_city');
        }
        if($order_details->user->address->landmark){
         $printInvioce->addLineLeft('Landmark: '.$order_details->user->address->landmark , 22, false, false, 'customer_landmark');
        }
        }
        }
        $printInvioce->addDivider('-', 20, false, false, 'separator');

        $printInvioce->tableStructure(['#', 'Item'], [3,31], 22);
        $printInvioce->tableStructure(['Rate','Qty','Tax', 'Amt'], [9,7,8, 10], 22);
        $printInvioce->tableStructure(['Barcode','Hsn', 'Disc'], [20,8, 6], 22);

        if($returnType == 'JSON'){
            $printInvioce->tableStructure(['Barcode','hsn', 'Disc'], [18,10, 6], 22, false, false, 'item_header_2', ['barcode', 'hsn', 'disc']);
        } 
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        if($returnType == 'JSON'){

            $printInvioce->tableStructure([
                $product_data[0] = '',
                $product_data[1] = '',
                $product_data[2] = '',
                $product_data[3] = '',
                $product_data[4] = '',
                $product_data[5] = ''
            ],
            [3, 8, 6,5,5,7], 22, false, false, 'item_detail_1', ['sr_no', 'name', 'rate', 'qty', 'tax_amt', 'total']);
            $printInvioce->tableStructure([
                ' '.$product_data[6] = '',
                $product_data[7] = '',
                $product_data[8] = ''
            ],
            [18,10, 6], 22, false, false, 'item_detail_2', ['item_code', 'hsn', 'discount']);

        }else{
            for($i = 0; $i < count($product_data); $i++) {
                        if($product_data[$i]['row'] == 1) {
                            $printInvioce->tableStructure([
                                $product_data[$i]['sr_no'],
                                $product_data[$i]['name'],
                            ],
                            [3,31], 22);
                        }
                        if($product_data[$i]['row'] == 2)  {
                            $printInvioce->tableStructure([
                                $product_data[$i]['rate'],
                                $product_data[$i]['qty'],
                                $product_data[$i]['tax_amt'],
                                $product_data[$i]['total']
                            ],
                            [9,7,8, 10], 22);
                        }
                        if($product_data[$i]['row'] == 3){
                            $printInvioce->tableStructure([
                                $product_data[$i]['item_code'],
                                $taxable_amount?$product_data[$i]['hsn']:'',
                                $product_data[$i]['discount']
                            ],
                            [20,8, 6], 22);
                        }
                    }
        }
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        $printInvioce->tableStructure(['Total', $cart_qty, $total_amount], [20, 4,14], 22, true, false, 'order_total', ['total', 'cart_qty', 'total_amount']);
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        if($returnType == 'JSON'){
            $printInvioce->addLineLeft(ucfirst($currencyName).': '.($order_details->total) , 22, false, false, 'amount_words');
        }else{
            $printInvioce->addLineLeft(ucfirst($currencyName).': '.ucfirst(numberTowords(round($order_details->total))).' Only' , 22, false, false, 'amount_words');
        }

        $printInvioce->addDivider('-', 20, false, false, 'separator');
        if($customer_paid > 0){
            $printInvioce->addLineLeft('  Customer Paid: '.format_number($customer_paid), 22, true, false, 'customer_paid');
            $printInvioce->addLineLeft('  Balance Refund: '.format_number($balance_refund), 22, true, false, 'balance_refund');
        }

        if($returnType == 'JSON'){    
            $printInvioce->addLineLeft('Customer Paid'.($customer_paid), 22, true, false, 'customer_paid');
            $printInvioce->addLineLeft('Balance Refund'.($balance_refund), 22, true, false, 'balance_refund');
        }
        if($customer_paid!=0 || $returnType == 'JSON'){
        $printInvioce->addDivider('-', 20, false, false, 'separator');

        }
        /*Tax Start */


        if($returnType == 'JSON' && $taxable_amount == ''){

            $printInvioce->leftRightStructure('Tax Summary','', 22, false, false, 'gst_summary');
            $printInvioce->addDivider('-', 20, false, false, 'separator');

            $printInvioce->tableStructure(['Desc', 'Taxable', 'Tax'], [10,12,12], 22, false, false, 'gst_header', ['desc', 'taxable', 'vat']);
                $printInvioce->addDivider('-', 20, false, false, 'separator');
                $gst['name'] = '';
                $gst['taxable'] = '';
                $gst['cgst'] = '';
                $gst['sgst'] = '';
                $gst['cess'] = '';
                // foreach ($detatch_gst as $index => $gst) {
                    $printInvioce->tableStructure([$gst['name'],
                        ' '.$gst['taxable'],
                        $gst['cgst'],
                        $gst['sgst'],
                        $gst['cess']],
                        [8,9, 6,6,5], 22, false, false, 'gst_listing', ['gst_name', 'gst_taxable', 'gst_cgst', 'gst_sgst', 'gst_cess']);
                // }
                $printInvioce->addDivider('-', 20, false, false, 'separator');
                $printInvioce->tableStructure(['Total',
                    $taxable_amount,
                    $total_csgt,
                    $total_sgst,
                    $total_cess], [8, 9, 6,6,5], 22, true, false, 'gst_item', ['total', 'tax_amt', 'total_csgt', 'total_sgst', 'total_cess']);

            //     $printInvioce->tableStructure(['Total',
            //     $taxable_amount,
            //     $total_csgt,
            //     $total_sgst 
            // ], [8, 12, 7,7], 22, true, false, 'gst_order_summary', ['total', 'taxable_amount', 'total_csgt', 'total_sgst']);
                $printInvioce->addDivider('-', 20, false, false, 'separator');
        }

        if($taxable_amount > 0){

        $printInvioce->leftRightStructure('Tax Summary','', 22, false, false, 'gst_summary');
        $printInvioce->addDivider('-', 20, false, false, 'separator');

        if(!empty($detatch_gst)) {

            if($total_cess > 0){
                $printInvioce->tableStructure(['Desc', 'Taxable', 'VAT'], [10,12,12], 22, false, false, 'gst_header', ['desc', 'taxable', 'vat']);
                        //$printInvioce->tableStructure(['', 'Amt','Amt','Amt','Amt'], [8, 8, 6,6,6], 22);
                $printInvioce->addDivider('-', 20, false, false, 'separator');
                foreach ($detatch_gst as $index => $gst) {
                    $printInvioce->tableStructure([str_replace('GST', 'VAT', $gst->name),
                        ' '.$gst->taxable,
                        $gst->cgst+$gst->sgst],
                        [10,12,12], 22, false, false, 'gst_listing', ['gst_name', 'gst_taxable', 'tax']);
                }
                $printInvioce->addDivider('-', 20, false, false, 'separator');
                $printInvioce->tableStructure(['Total',
                    format_number($taxable_amount),
                   format_number($total_csgt+$total_sgst)], [10,12,12], 22, true, false, 'gst_item', ['total', 'tax_amt', 'vat']);
            }else{
             $printInvioce->tableStructure(['Desc', 'Taxable', 'VAT'], [10,12,12], 22, false, false, 'gst_header', ['desc', 'taxable', 'vat']);
                        //$printInvioce->tableStructure(['', 'Amt','Amt','Amt','Amt'], [8, 8, 6,6,6], 22);

             $printInvioce->addDivider('-', 20, false, false, 'separator');
             foreach ($detatch_gst as $index => $gst) {
                $printInvioce->tableStructure([str_replace('GST', 'VAT', $gst->name),
                    ' '.$gst->taxable,
                    $gst->cgst,
                    $gst->sgst 
                ],
                [8,12, 7,7], 22, false, false, 'gst_item', ['gst_name', 'gst_taxable', 'vat']);
            }

            $printInvioce->addDivider('-', 20, false, false, 'separator');
            foreach ($detatch_gst as $index => $gst) {
                $printInvioce->tableStructure([$gst->name,
                    ' '.$gst->taxable,
                    $gst->cgst,
                    $gst->sgst,
                    $gst->cess],
                    [8,12, 7,7], 22, false, false, 'gst_item', ['gst_name', 'gst_taxable', 'gst_cgst', 'gst_sgst', 'gst_cess']);
            }

            $printInvioce->addDivider('-', 20, false, false, 'separator');
            $printInvioce->tableStructure(['Total',
                $taxable_amount,
                $total_csgt,
                $total_sgst 
            ], [8, 12, 7,7], 22, true, false, 'gst_order_summary', ['total', 'taxable_amount', 'total_csgt', 'total_sgst']);
        }

        $printInvioce->addDivider('-', 20, false, false, 'separator');
        }
        }
        $total_discount = (float)$order_details->discount+(float)$order_details->manual_discount+(float)$order_details->bill_buster_discount;
        if($returnType == 'JSON'){
            $printInvioce->leftRightStructure('Saving', '', 22, false, false, 'saving');
        }else{
           
                $printInvioce->leftRightStructure('Saving', $total_discount, 22, false, false, 'saving');
        
        }
        $printInvioce->leftRightStructure('Total QTY', $cart_qty, 22, false, false, 'total_qty');
        if($order_details->transaction_type != "return"){
         $printInvioce->leftRightStructure('Total Sale', format_number($total_amount), 22, false, false, 'total_sale');
       }
       if(!empty($order_details->round_off)){
        $printInvioce->leftRightStructure('Round Off', $order_details->round_off, 22, false, false, 'rounded_off');    
       }

            // Closes Left & Start center
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        if($returnType == 'JSON'){

            $mop['name'] = '';
            $mop['amount'] = '';
            $printInvioce->leftRightStructure($mop['name'], round($mop['amount'],2), 22, false, false, 'mop');
            $printInvioce->addDivider('-', 20, false, false, 'separator');

        }else{

        if(!empty($mop_list)) {
            foreach ($mop_list as $mop) {
                $printInvioce->leftRightStructure($mop['mode'], round($mop['amount'],2), 22, false, false, 'mop');
            }
                $printInvioce->addDivider('-', 20, false, false, 'separator');
            }

        }
        if($order_details->transaction_type != "return"){
        if($returnType == 'JSON'){
            $printInvioce->leftRightStructure('Net Payable', $net_payable, 22, false, false, 'net_payable');
        }else{
            $printInvioce->leftRightStructure('Net Payable', format_number($net_payable), 22, false, false, 'net_payable');
        }
    }
        
    if($v_id == 11 || $returnType == 'JSON'){
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        $printInvioce->addLineCenter(' Terms and Conditions', 22, true, false, 'terms_conditions_header');
        $printInvioce->addDivider('-', 20, false, false, 'separator');    
      }else{
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        $printInvioce->addLineLeft(' Terms and Conditions', 22, true, false, 'terms_conditions_header');
        $printInvioce->addDivider('-', 20, false, false, 'separator');       
       }
       foreach ($terms_conditions as $term) {
        $printInvioce->addTcLineLeft($term, 20, false, false, 'terms');
       }

       if($returnType == 'JSON') {
        $printInvioce->getEndLines('', 20, false, false, 'endlines');
       }
        // dd($printInvioce);

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

        if($returnType == 'JSON') {
            return $printInvioce->getFinalResult();
        } else {
            $response = ['status' => 'success', 
        'print_data' =>($printInvioce->getFinalResult())];
        }

        if($request->has('response_format') && $request->response_format == 'ARRAY'){
            return $response;
        }
        return response()->json($response, 200);
    

    }//End of callCustomPrintWithVat




    public function callVatRecipt(Request $request){

        $v_id        = $request->v_id;
        $store_id    = $request->store_id;
        $c_id        = $request->c_id;
        $vu_id       = $request->vu_id;
        $order_id    = $request->order_id;
        $usefor      = !empty($request->usefor)?$request->usefor:'';
        $product_data= [];
        $gst_list    = [];
        $final_gst   = [];
        $detatch_gst = [];
        $rounded     = 0;
        $trans_from  = $request->trans_from;
        $returnType  = 'CUSTOM_HTML';
        if($request->has('return_type')){
            $returnType  = $request->return_type;

        }

        $bill_print_type = 0;
        $role        = VendorRoleUserMapping::select('role_id')->where('user_id', $vu_id)->first();
        $sParams     = ['v_id' => $v_id,'store_id'=>$store_id,'user_id'=>$c_id,'role_id'=>@$role->role_id,'trans_from' => $trans_from];
        $vendorS     = new VendorSettingController;
        $printSetting= $vendorS->getPrintSetting($sParams);
        if(count($printSetting) > 0){
            foreach($printSetting as $psetting){
                if($psetting->name == 'bill_print'){
                    $bill_print_type = $psetting->width;
                }
            }
        }          

        $vendorC  = new VendorController;
        $crparams = array('v_id'=>$v_id,'store_id'=>$store_id,'vu_id'=>1,'info_type'=>'CURRENCY');
        $currency = $vendorC->getCurrencyDetail($crparams);
        $currencyR = explode(' ', $currency['name']);
        if($currencyR > 1){
            $len = count($currencyR);
            $currencyName = $currencyR[$len-1];
        }else{
            $currencyName  =  $currencyR ;
        }
       

        $store         = Store::find($store_id);
        $order_details = Invoice::where('invoice_id', $order_id)->first();

        if($returnType == 'JSON') {
            $cart_product = [];
            $total_amount = 0;
            $order_details = (object)[ 'total' => 0, 'payvia' => [], 'transaction_type' => 'sales', 'vuser' => (object)[ 'first_name' => '', 'last_name' => '' ], 'user' => (object)[ 'mobile' => '', 'address' => (object)[ 'address1' => '', 'address2' => '', 'city' => '', 'state' => '', 'landmark' => '' ], 'hall_no' => '', 'seat_no' => '' ] ];
        } else {
            $cart_q = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','0')->where('user_id', $order_details->user_id)->sum('qty');

            $cart_qt = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('weight_flag','1')->where('user_id', $order_details->user_id)->count('qty');

            $cart_qty = $cart_q + $cart_qt;

            $total_amount = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
                // dd($total_amount);

            $cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->get();
        }
        $count = 1;
        $gst_tax = 0;
        $gst_listing = [];


        $total_savings_amount = 0;
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
            $itemLevelmanualDiscount=0;
             if($value->item_level_manual_discount!=null){
                $iLmd = json_decode($value->item_level_manual_discount);
                $itemLevelmanualDiscount= (float)$iLmd->discount;
             }
             if($v_id == 36){
                $configCon = new CartconfigController;
                $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $value->barcode)->first();
                $Item = null;
                if($bar){
                    $where    = array('v_id'=>$v_id,'vendor_sku_detail_id'=> $bar->vendor_sku_detail_id,'deleted_at' => null);
                    $Item     = VendorSku::select('vendor_sku_detail_id','v_id','item_id','has_batch','uom_conversion_id','variant_combi','tax_type')->where($where)->first();
                    $Item->barcode = $bar->barcode;
                }
                $priceArr  = array('v_id'=>$v_id,'store_id'=>$store_id,'item'=>$Item,'unit_mrp'=>'');
                $price    =  $configCon->getprice($priceArr);
                $unit_m = (int)$price['unit_mrp'];
               $amount = ($unit_m*$value->qty) - ($value->unit_mrp*$value->qty);
                $total_save_with_dis = ($value->discount+$value->manual_discount + $value->bill_buster_discount)+$itemLevelmanualDiscount;
                // if($value->barcode == '9996230102'){
                    
                //     dd($unit_m);
                // }
                $total_savings_amount += $total_save_with_dis;

                $product_data[]  = [
                'row'           => 1,
                'sr_no'         => $count++,
                'name'          => $value->item_name,
                'qty'           => $value->qty,
                            'tax_amt'       => $value->tax,  //$value->tax.$tax_type,
                            'rate'          => $unit_m,
                            'total'         => $value->total 

                        ];
                        $product_data[] = [
                            'row'           => 2,
                            'discount'      => $value->discount+$value->manual_discount + $value->bill_buster_discount+$itemLevelmanualDiscount,
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

            else{
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
                            'discount'      => $value->discount+$value->manual_discount + $value->bill_buster_discount+$itemLevelmanualDiscount,
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
                $paymeny_method = Mop::where('code', $payment->method)->first();
                // dd($paymeny_method->name);
                if ($payment->method == 'cash') {
                    $cashReturn = empty($payment->cash_return)?0:$payment->cash_return;
                    if($order_details->transaction_type == 'return'){
                        // $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
                        $mop_list[] = [ 'mode' => $paymeny_method->name, 'amount' => $payment->amount ];
                    }else{
                       // $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->cash_collected-$cashReturn ];
                        $mop_list[] = [ 'mode' => $paymeny_method->name, 'amount' => $payment->cash_collected-$cashReturn ];
                    }
                } else {
                    // $mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
                    $mop_list[] = [ 'mode' => $paymeny_method->name, 'amount' => $payment->amount ];
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

            ########################
            ####### Print Start ####
            ########################
            //$terms_conditions =  array('(1) Exchange Within 7 days only.','(2) MRP Are Inclusive of Applicable Tax');

            $terms_conditions =  array('1. MRP Are Inclusive of Applicable Tax'); 
            if($order_details->transaction_type == 'return'){
                $invoice_title     = 'Credit Note';
            }else{
                $invoice_title     = 'Tax invoice';
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
        if($returnType == 'JSON') {
            $order_details->invoice_id = '';
            $taxable_amount = 1;
            $product_data = [];
            $cart_qty = '';
            $total_amount = '';
            $order_details->discount = '';
            $order_details->manual_discount = '';
            $order_details->bill_buster_discount = '';
            $order_details->total = '';
            $customer_paid = '';
            $balance_refund = '';
            $detatch_gst = [];
            $total_cess = '';
            $taxable_amount = '';
            $total_csgt = '';
            $total_sgst = '';
            $total_cess = '';
            // $total_discount = '';
            $cart_qty = '';
            $total_amount = '';
            $mop_list = '';
            $net_payable = '';

            $printInvioce = new PrintJsonInvoice($manufacturer_name[0], $printParams , $returnType);
        } else {
            $printInvioce = new PrintInvoice($manufacturer_name[0], $printParams , $returnType);
        }

        $printInvioce->addLineCenter($store->name, 24, true, false, 'store_name');
        $printInvioce->addLine($store->address1, 22, false, false, 'address_1');
        if($store->address2){
        $printInvioce->addLine($store->address2, 22, false, false, 'address_2');
        }
        $printInvioce->addLine($store->location.'-'.$store->pincode.', '.$store->state, 22, false, false, 'location');
        $printInvioce->addLine('Contact No: '.$store->contact_number, 22, false, false, 'contact_no');
        $printInvioce->addLine('E-mail: '.$store->email, 22, false, false, 'email');
        $printInvioce->addLine('VAT NO: '.$store->gst, 22, false, false, 'gst');
        if($store->cin){
        $printInvioce->addLine('CIN: '.$store->cin, 22, false, false, 'cin');            
        }
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        $printInvioce->addLine($invoice_title  , 22, true, false, 'invoice_title');
        $printInvioce->addDivider('-', 20, false, false, 'separator');

        $printInvioce->addLineLeft(' Invoice No : '.$order_details->invoice_id , 22, true, false, 'invoice_no');
        if ($order_details->transaction_type == 'return') {
            $printInvioce->addLineLeft(' Reference No : '.$refererence_order->ref_order_id , 22, true, false, 'refererence_no');
        }
        if($returnType == 'JSON'){
            $printInvioce->addLineLeft(' Date : ' , 22, false, false, 'invoice_date_time');
        }else{
            $printInvioce->addLineLeft(' Date : '.date('d-M-Y', strtotime($order_details->created_at))." at ".date('h:i:s A', strtotime($order_details->created_at)), 22, false, false, 'invoice_date_time');
        }
        $printInvioce->addLineLeft(' Cashier : '.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name , 22, false, false, 'cashier_name');
        $printInvioce->addLineLeft(' Customer Mobile : '.@$order_details->user->mobile , 22, false, false, 'customer_mob');

        /***************************************/
            # Customer Address When Resturant Type #
        /**************************************/

        if($store->type == 5 || $store->type == 6){
        if(isset($order_details->user->address->address1)){
        $printInvioce->addLineLeft(' Customer Address : '.$order_details->user->address->address1 , 22, false, false, 'customer_address');
        if($order_details->user->address->address2){
         $printInvioce->addLineLeft(' '.$order_details->user->address->address2 , 22, false, false, 'customer_address_2');
        }
        if($order_details->user->address->city){
         $printInvioce->addLineLeft($order_details->user->address->city.', '.$order_details->user->address->state , 22, false, false, 'customer_city');
        }
        if($order_details->user->address->landmark){
         $printInvioce->addLineLeft('Landmark: '.$order_details->user->address->landmark , 22, false, false, 'customer_landmark');
        }
        }
        }
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        $printInvioce->tableStructure(['#', 'Item', 'Rate','Qty','Tax Amt', 'Amount'], [3, 7, 6, 4, 6, 8], 22, false, false, 'item_header_1', ['sr_no','name', 'rate', 'qty', 'tax_amt', 'total']);
        
        if($returnType == 'JSON'){
            $printInvioce->tableStructure(['Barcode','hsn', 'Disc'], [18,10, 6], 22, false, false, 'item_header_2', ['barcode', 'hsn', 'disc']);
        }else{
            if($taxable_amount > 0){
        $printInvioce->tableStructure(['Barcode','hsn', 'Disc'], [18,10, 6], 22, false, false, 'item_header_2', ['barcode', 'hsn', 'disc']);
        }else{
        $printInvioce->tableStructure(['Barcode','', 'Disc  '], [22,2 , 10], 22, false, false, 'item_header_2', ['barcode', '', 'disc']);
        }
        }
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        if($returnType == 'JSON'){

            $printInvioce->tableStructure([
                $product_data[0] = '',
                $product_data[1] = '',
                $product_data[2] = '',
                $product_data[3] = '',
                $product_data[4] = '',
                $product_data[5] = ''
            ],
            [3, 7, 6,4,6,8], 22, false, false, 'item_detail_1', ['sr_no', 'name', 'rate', 'qty', 'tax_amt', 'total']);
            $printInvioce->tableStructure([
                ' '.$product_data[6] = '',
                $product_data[7] = '',
                $product_data[8] = ''
            ],
            [18,10, 6], 22, false, false, 'item_detail_2', ['item_code', 'hsn', 'discount']);

        }else{

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
            [3, 8, 6,5,5,7], 22, false, false, 'item_detail_1', ['sr_no', 'name', 'rate', 'qty', 'tax_amt', 'total']);

        } else {

            $printInvioce->tableStructure([
                ' '.$product_data[$i]['item_code'],
                $taxable_amount?$product_data[$i]['hsn']:'',
                $product_data[$i]['discount']
            ],
            [18,10, 6], 22, false, false, 'item_detail_2', ['item_code', 'hsn', 'discount']);

                }
            }
        }
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        $printInvioce->tableStructure(['Total', $cart_qty, $total_amount], [20, 4,14], 22, true, false, 'order_total', ['total', 'cart_qty', 'total_amount']);
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        if($returnType == 'JSON'){
            $printInvioce->addLineLeft(ucfirst($currencyName).': '.($order_details->total) , 22, false, false, 'amount_words');
        }else{
            $printInvioce->addLineLeft(ucfirst($currencyName).': '.ucfirst(numberTowords(round($order_details->total))).' Only' , 22, false, false, 'amount_words');
        }

        $printInvioce->addDivider('-', 20, false, false, 'separator');
        if($customer_paid > 0){
            $printInvioce->addLineLeft('  Customer Paid: '.format_number($customer_paid), 22, true, false, 'customer_paid');
            $printInvioce->addLineLeft('  Balance Refund: '.format_number($balance_refund), 22, true, false, 'balance_refund');
        }

        if($returnType == 'JSON'){    
            $printInvioce->addLineLeft('Customer Paid'.($customer_paid), 22, true, false, 'customer_paid');
            $printInvioce->addLineLeft('Balance Refund'.($balance_refund), 22, true, false, 'balance_refund');
        }
        if($customer_paid!=0 || $returnType == 'JSON'){
        $printInvioce->addDivider('-', 20, false, false, 'separator');

        }
        /*Tax Start */


        if($returnType == 'JSON' && $taxable_amount == ''){

            $printInvioce->leftRightStructure('Tax Summary','', 22, false, false, 'gst_summary');
            $printInvioce->addDivider('-', 20, false, false, 'separator');

            $printInvioce->tableStructure(['Desc', 'Taxable', 'Tax'], [10,12,12], 22, false, false, 'gst_header', ['desc', 'taxable', 'vat']);
                $printInvioce->addDivider('-', 20, false, false, 'separator');
                $gst['name'] = '';
                $gst['taxable'] = '';
                $gst['cgst'] = '';
                $gst['sgst'] = '';
                $gst['cess'] = '';
                // foreach ($detatch_gst as $index => $gst) {
                    $printInvioce->tableStructure([$gst['name'],
                        ' '.$gst['taxable'],
                        $gst['cgst'],
                        $gst['sgst'],
                        $gst['cess']],
                        [8,9, 6,6,5], 22, false, false, 'gst_listing', ['gst_name', 'gst_taxable', 'gst_cgst', 'gst_sgst', 'gst_cess']);
                // }
                $printInvioce->addDivider('-', 20, false, false, 'separator');
                $printInvioce->tableStructure(['Total',
                    $taxable_amount,
                    $total_csgt,
                    $total_sgst,
                    $total_cess], [8, 9, 6,6,5], 22, true, false, 'gst_item', ['total', 'tax_amt', 'total_csgt', 'total_sgst', 'total_cess']);

            //     $printInvioce->tableStructure(['Total',
            //     $taxable_amount,
            //     $total_csgt,
            //     $total_sgst 
            // ], [8, 12, 7,7], 22, true, false, 'gst_order_summary', ['total', 'taxable_amount', 'total_csgt', 'total_sgst']);
                $printInvioce->addDivider('-', 20, false, false, 'separator');
        }

        if($taxable_amount > 0){

        $printInvioce->leftRightStructure('Tax Summary','', 22, false, false, 'gst_summary');
        $printInvioce->addDivider('-', 20, false, false, 'separator');

        if(!empty($detatch_gst)) {

            if($total_cess > 0){
                $printInvioce->tableStructure(['Desc', 'Taxable', 'VAT'], [10,12,12], 22, false, false, 'gst_header', ['desc', 'taxable', 'vat']);
                        //$printInvioce->tableStructure(['', 'Amt','Amt','Amt','Amt'], [8, 8, 6,6,6], 22);
                $printInvioce->addDivider('-', 20, false, false, 'separator');
                foreach ($detatch_gst as $index => $gst) {
                    $printInvioce->tableStructure([str_replace('GST', 'VAT', $gst->name),
                        ' '.$gst->taxable,
                        $gst->cgst+$gst->sgst],
                        [10,12,12], 22, false, false, 'gst_listing', ['gst_name', 'gst_taxable', 'tax']);
                }
                $printInvioce->addDivider('-', 20, false, false, 'separator');
                $printInvioce->tableStructure(['Total',
                    format_number($taxable_amount),
                   format_number($total_csgt+$total_sgst)], [10,12,12], 22, true, false, 'gst_item', ['total', 'tax_amt', 'vat']);
            }else{
             $printInvioce->tableStructure(['Desc', 'Taxable', 'VAT'], [10,12,12], 22, false, false, 'gst_header', ['desc', 'taxable', 'vat']);
                        //$printInvioce->tableStructure(['', 'Amt','Amt','Amt','Amt'], [8, 8, 6,6,6], 22);

             $printInvioce->addDivider('-', 20, false, false, 'separator');
             foreach ($detatch_gst as $index => $gst) {
                $printInvioce->tableStructure([str_replace('GST', 'VAT', $gst->name),
                    ' '.$gst->taxable,
                    $gst->cgst,
                    $gst->sgst 
                ],
                [8,12, 7,7], 22, false, false, 'gst_item', ['gst_name', 'gst_taxable', 'vat']);
            }

            $printInvioce->addDivider('-', 20, false, false, 'separator');
            foreach ($detatch_gst as $index => $gst) {
                $printInvioce->tableStructure([$gst->name,
                    ' '.$gst->taxable,
                    $gst->cgst,
                    $gst->sgst,
                    $gst->cess],
                    [8,12, 7,7], 22, false, false, 'gst_item', ['gst_name', 'gst_taxable', 'gst_cgst', 'gst_sgst', 'gst_cess']);
            }

            $printInvioce->addDivider('-', 20, false, false, 'separator');
            $printInvioce->tableStructure(['Total',
                $taxable_amount,
                $total_csgt,
                $total_sgst 
            ], [8, 12, 7,7], 22, true, false, 'gst_order_summary', ['total', 'taxable_amount', 'total_csgt', 'total_sgst']);
        }

        $printInvioce->addDivider('-', 20, false, false, 'separator');
        }
        }
        $total_discount = (float)$order_details->discount+(float)$order_details->manual_discount+(float)$order_details->bill_buster_discount;
        if($returnType == 'JSON'){
            $printInvioce->leftRightStructure('Saving', '', 22, false, false, 'saving');
        }else{
           
                $printInvioce->leftRightStructure('Saving', $total_discount, 22, false, false, 'saving');
        
        }
        $printInvioce->leftRightStructure('Total QTY', $cart_qty, 22, false, false, 'total_qty');
        if($order_details->transaction_type != "return"){
         $printInvioce->leftRightStructure('Total Sale', format_number($total_amount), 22, false, false, 'total_sale');
       }
        if(!empty($order_details->round_off)){
        $printInvioce->leftRightStructure('Round Off', $order_details->round_off, 22, false, false, 'rounded_off');    
        }


            // Closes Left & Start center
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        if($returnType == 'JSON'){

            $mop['name'] = '';
            $mop['amount'] = '';
            $printInvioce->leftRightStructure($mop['name'], round($mop['amount'],2), 22, false, false, 'mop');
            $printInvioce->addDivider('-', 20, false, false, 'separator');

        }else{

        if(!empty($mop_list)) {
            foreach ($mop_list as $mop) {
                $printInvioce->leftRightStructure($mop['mode'], round($mop['amount'],2), 22, false, false, 'mop');
            }
                $printInvioce->addDivider('-', 20, false, false, 'separator');
            }

        }
        if($order_details->transaction_type != "return"){
        if($returnType == 'JSON'){
            $printInvioce->leftRightStructure('Net Payable', $net_payable, 22, false, false, 'net_payable');
        }else{
            $printInvioce->leftRightStructure('Net Payable', format_number($net_payable), 22, false, false, 'net_payable');
        }
    }
        
    if($v_id == 11 || $returnType == 'JSON'){
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        $printInvioce->addLineCenter(' Terms and Conditions', 22, true, false, 'terms_conditions_header');
        $printInvioce->addDivider('-', 20, false, false, 'separator');    
      }else{
        $printInvioce->addDivider('-', 20, false, false, 'separator');
        $printInvioce->addLineLeft(' Terms and Conditions', 22, true, false, 'terms_conditions_header');
        $printInvioce->addDivider('-', 20, false, false, 'separator');       
       }
       foreach ($terms_conditions as $term) {
        $printInvioce->addTcLineLeft($term, 20, false, false, 'terms');
       }

       if($returnType == 'JSON') {
        $printInvioce->getEndLines('', 20, false, false, 'endlines');
       }
        // dd($printInvioce);

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

        if($returnType == 'JSON') {
            return $printInvioce->getFinalResult();
        } else {
            $response = ['status' => 'success', 
        'print_data' =>($printInvioce->getFinalResult())];
        }

        if($request->has('response_format') && $request->response_format == 'ARRAY'){
            return $response;
        }
        return response()->json($response, 200);
    }//End of callVatRecipt
    
    public function format_and_string($value) 
    {
        return (string) sprintf('%0.2f', $value);
    }

    public function get_duplicate_receipt(Request $request){

        $vu_id = $request->vu_id;
        $v_id = $request->v_id;
        $store_id = $request->store_id; 
        $security_code_vu_id = $request->security_code_vu_id;
        //$c_id = $request->c_id;
        //$order_id = $request->order_id;
        $cust_mobile_no = $request->cust_mobile_no;
        $trans_from = $request->trans_from;
        $operation = $request->operation;
        $isOrderReciept = false;
        if($request->has('trans_type') && $request->trans_type == 'order') {
            $isOrderReciept = true;
        }

        $user = User::select('c_id', 'mobile')->where('mobile',$cust_mobile_no)->first();
        if($user){

            $today_date = date('Y-m-d');
            if($isOrderReciept) {
                $order = Order::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'order_id' => $request->order_id ])->first();
            } else {
                $order = Order::where('user_id', $user->c_id)->where('status','success')->orderBy('od_id' , 'desc')->where('date', $today_date)->where('trans_from', $trans_from)->first();
            }

            if($order){

               // dd(date('Y-m-d H:i:s'));

                DB::table('operation_verification_log')->insert([ 'v_id' => $v_id, 'store_id' => $store_id, 'c_id' =>$user->c_id, 'trans_from' => $trans_from, 'vu_id' =>$vu_id ,'operation' => $operation , 'order_id' => $order->order_id , 'verify_by' =>  $security_code_vu_id , 'created_at' => date('Y-m-d H:i:s') ]);

                $request->request->add(['c_id' => $user->c_id , 'order_id' => $order->order_id]);
                if($request->has('trans_from') && $request->trans_from == 'CLOUD_TAB_WEB'){
                    $print = $this->get_print_receipt($request);   
                    return $this->get_html_structure($print);
                }
                return $this->get_print_receipt($request);

            }else{
                return response()->json(['status'=> 'fail' , 'message' => 'Unable to find any order which has been placed today'] , 200);
            }
        }else{
            return response()->json(['status'=> 'fail' , 'message' => 'Customer not exists'] , 200);
        }

    }


    public function get_html_structure($str)
    {

        //  $string = str_replace('<center>','<tbodyclass="center">',$str);
        // $string = str_replace('<left>','<tbodyclass="left">',$string);
        // $string = str_replace('<right>','<tbodyclass="right">',$string);
        // $string = str_replace('</center>','</tbody>',$string);
        // $string = str_replace('</left>','</tbody>',$string);
        // $string = str_replace('</right>','</tbody>',$string);

        $string = str_replace('<center>','',$str);
        $string = str_replace('<left>','',$string);
        $string = str_replace('<right>','',$string);
        $string = str_replace('</center>','',$string);
        $string = str_replace('</left>','',$string);
        $string = str_replace('</right>','',$string);
        
        $string = str_replace('normal>','span>',$string);
        $string = str_replace('bold>','b>',$string);
        $string = str_replace('<size','<tr><td',$string);
        $string = str_replace('size>','td></tr>',$string);
        $string = str_replace('text','pre',$string);
        $string = str_replace('td=30','tdstyle="font-size:90px"',$string);
        $string = str_replace('td=24','tdstyle="font-size:16px"',$string);
        $string = str_replace('td=22','tdstyle="font-size:15px"',$string);
        $string = str_replace('td=20','tdstyle="font-size:14px"',$string);
        $string = str_replace('td=19','tdstyle="font-size:12px"',$string);

        $string = str_replace('\n','&nbsp;',$string);
        // $DOM = new \DOMDocument;
        // $DOM->loadHtml($string);

        $string = urlencode($string);
        // $string = str_replace('+','&nbsp;&nbsp;');
        $string = str_replace('tds','td s',$string);
        $string = str_replace('tbodyc','tbody c',$string);

        $renderPrintPreview = '<!DOCTYPE html><html><head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
        <title>Invoice</title>
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
        table.center tbody tr:first-child td b pre{font-size: 18px !important;}
        </style>
        </head>

        <body>
        <center>

        <div style="width: 350px;">
        <table class="center">
        '
        .urldecode($string).
        '</table>
        </div>

        </center>
        </body>
        </html>';
        
        return $renderPrintPreview;


    }//End of get_html_structure

    public function get_html_structure_new($str)
    {   
        $renderPrintPreview = '<!DOCTYPE html><html><head>
         '.$str->style.'
        </head>
        <body>
        <center>
        <div>
        <table class="center">
        '
        .$str->html.
        '</table>
        </div>

        </center>
        </body>
        </html>';
        
        return $renderPrintPreview;


    }
    
    public function order_receipt($c_id,$v_id , $store_id, $order_id,$usefor='',$type=''){
        if(empty($type)){
            $type =  isset($_GET['type'])?$_GET['type']:'';    
        }
        $organisation = Organisation::find($v_id);
        if($organisation->db_type == 'MULTITON' && $organisation->db_name != ''){
            $connPrm    =array('host'=> $organisation->connection->host,'port'=>$organisation->connection->port,'username'=>$organisation->connection->username,'password' =>  $organisation->connection->password,'db_name'=>$organisation->db_name);
            dynamicConnectionNew($connPrm);
        }

        $request = new \Illuminate\Http\Request();
        $request->merge([
            'v_id' => $v_id,
            'c_id' => $c_id,
            'store_id' => $store_id,
            'order_id' => $order_id,
            'usefor'     => $usefor,
            'type'    => $type
        ]);
        $htmlData = $this->get_print_receipt($request);

        if(is_array($htmlData) && isset($htmlData['html'])){
            $html_obj_data = json_decode(json_encode($htmlData));
            if($html_obj_data->status == 'success')
                {
                   // return $this->get_html_structure($html_obj_data->html);
                    return $this->get_html_structure_new($html_obj_data);
                }
        }else{
            $html = $htmlData->getContent();
            $html_obj_data = json_decode($html);
            if($html_obj_data->status == 'success')
                {
                    return $this->get_html_structure($html_obj_data->print_data);
                }
        }
        
        //die;

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

        $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $cart->barcode)->first();
        if($bar){
            $item_master = VendorSku::select('vendor_sku_detail_id','hsn_code')->where(['vendor_sku_detail_id'=> $bar->vendor_sku_detail_id,'v_id'=>$v_id, 'deleted_at' => null])->first();
        }

        if(!$item_master){
            $item_master = VendorSku::select('hsn_code')->where(['sku'=> $cart->barcode,'v_id'=>$v_id, 'deleted_at' => null])->first();
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
            $voucher = DB::table('cr_dr_voucher')->where('ref_id', $order->order_id)->where('user_id',$order->user_id)->first();
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


    public function get_carry_bags(Request $request)
    {
        $v_id           = $request->v_id;
        $store_id       = $request->store_id; 
        $c_id           = $request->c_id;
        $order_id       = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id       = $order_id + 1;

        $store_db_name  = get_store_db_name(['store_id' => $store_id]);
        $carry_bags     = Carry::select('barcode')->where('v_id', $v_id)->where('store_id', $store_id)->where('status','1')->where('deleted_status', '0')->get();
        $carr_bag_arr   = $carry_bags->pluck('barcode')->all();

        $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->whereIn('barcode', $carr_bag_arr)->get();

        if($bar){

            $carry_bags  =  VendorSku::select('vendor_sku_flat_table.vendor_sku_detail_id','vendor_sku_flat_table.item_id','vendor_sku_flat_table.has_batch','vendor_sku_detail_barcodes.barcode','vendor_sku_flat_table.variant_combi','vendor_sku_flat_table.name')
            ->leftjoin('stock_point_summary', 'stock_point_summary.sku_code', 'vendor_sku_flat_table.sku_code')
            ->leftjoin('vendor_sku_detail_barcodes', 'vendor_sku_detail_barcodes.item_id', 'stock_point_summary.item_id')

            ->where(['vendor_sku_flat_table.v_id' => $v_id , 'stock_point_summary.stop_billing' => '0','stock_point_summary.store_id' => $store_id])
            ->whereIn('vendor_sku_flat_table.vendor_sku_detail_id' , $bar->pluck('vendor_sku_detail_id')->all() )
            ->groupby('vendor_sku_flat_table.vendor_sku_detail_id')
            ->get();

        }

        $carts          = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

        $data = array();
        if(count($carry_bags)> 0){

        foreach ($carry_bags as $key => $value) {

                ## Price Calculation Start
                //$priceList  = $value->vprice->where('v_id',$v_id)->where('variant_combi',$value->variant_combi);    
                $priceArr  = array('v_id'=>$v_id,'store_id'=>$store_id,'item'=>$value,'unit_mrp'=>'');
                    // $mrplist    = array();
                    // foreach($priceList as $mp){
                    // $mrplist[] = array('mrp'=>$mp->priceDetail->mrp,'rsp'=>$mp->priceDetail->rsp,'s_price'=>$mp->priceDetail->special_price);
                    // }
                    // $mrplist  =  collect($mrplist);
                    // $unit_mrp =  $mrplist->max('mrp'); 
                    // $r_price  =  $mrplist->max('rsp')  ;

                $config   =  $this->cartconfig;
                //$price    =  $config->getprice($priceList);
                $price    =  $config->getprice($priceArr);
                // print_r($price['unit_mrp']);
                $unit_mrp =  $price['unit_mrp']; 
                $r_price  =  $price['r_price'] ;
                $s_price  =  !empty($price['s_price'])?$price['s_price']:$price['unit_mrp'] ;
                $mrp_arrs = $price['mrp_arrs'];
                $multiple_mrp_flag = $price['multiple_mrp_flag'];


                ## Price Calculation End

                $BAG_ID  =  $value->barcode;
                $NAME    =  $value->name;
                $PRICE   =  format_number($unit_mrp);

                $cart    = $carts->where('item_id',$BAG_ID)->first();

                if(empty($cart)) {
                    $data[] = array(
                        'BAG_ID' => $BAG_ID,
                        'Name' => $NAME,
                        'Price' => $PRICE,
                        'Qty' => 0
                    );
                } else {
                    if($BAG_ID == $cart->item_id) {
                        $data[] = array(
                            'BAG_ID' => $BAG_ID,
                            'Name' => $NAME,
                            'Price' => $PRICE,
                            'Qty' => $cart->qty
                        );
                    } else {
                        $data[] = array(
                            'BAG_ID' => $BAG_ID,
                            'Name' => $NAME,
                            'Price' => $PRICE,
                            'Qty' => 0
                        );
                    }
                }

            }
        }
        // dd($data);
        //return response()->json(['status' => 'get_carry_bags_by_store', 'data' => $data ],200);
        return ['status' => 'get_carry_bags_by_store', 'data' => $data ];
    }

    public function save_carry_bags(Request $request)
    { 
        // dd($request->all());
        $cart_barcode = [];
        $api_token = $request->api_token;
        $customer_group_code = $request->customer_group_code;
        $udidtoken = $request->udidtoken;
        $terminal_id = $request->terminal_id;
        $trans_type = $request->trans_type;
        $url = $request->url;
        $vu_id = $request->vu_id;
        $session_id = $request->session_id;
        $v_id = $request->v_id;
        $store_id = $request->store_id; 
        $c_id = $request->c_id; 
        $trans_from = $request->trans_from;
        //$order_id = $request->order_id; 
        $bags = $request->bags; 
        $bags = json_decode($bags, true);

        $store_db_name = get_store_db_name(['store_id' => $store_id]);

        // $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
        $order_id = $order_id + 1;

        $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

        $db_structure = DB::table('vendor')->select('db_structure')->where('id',$v_id)->first()->db_structure;
        foreach ($bags as $key => $value) {

            $barcode = $barcodefrom =  $value[0];
            
            if(is_decimal($value[1])){
                return response()->json(['status' => 'fail', 'message' => 'Value should not be Decimal and Negative'],200);
            }
            else{
                $qty = $value[1];
            }
            $exists = $carts->where('barcode', $value[0]);

            $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode','item_id')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $value[0])->first();

            
            // dd($is_valid);
            $item = null;
            $cust_gstin = null;
            if($request->has('cust_gstin') && $request->cust_gstin !=''){
                $cust_gstin = $request->cust_gstin;
            }
            $scan = 'FALSE';
            if($request->has('scan') && $request->scan != ''){
                $scan = $request->scan;
            }
            $request = new Request([
                'barcode' => $bar->barcode,
                'batch_id' => "0",
                'serial_id' => "0",
                'qty' => $qty,
                'cust_gstin' => $cust_gstin,
                'scan' => $scan,
                'c_id' => $c_id,
                'cust_gstin' => $cust_gstin,
                'qty' => $qty,
                'scan' => $scan,
                'customer_group_code' => $customer_group_code,
                'session_id' => $session_id,
                'store_id' => $store_id,
                'terminal_id' => $terminal_id,
                'trans_from' => $trans_from,
                'trans_type' => $trans_type,
                'udidtoken' => $udidtoken,
                'url' => $url,
                'vu_id' => $vu_id,
                'v_id' => $v_id
            ]);

            $sbagsdetails = new ProductController;
            $responseDetails = $sbagsdetails->product_details($request);
            // dd($responseDetails);
            // $data = $responseDetails;
            // dd($data);
            if((json_decode($responseDetails->getContent())->status == 'product_not_found') || (json_decode($responseDetails->getContent())->status == 'fail')){
                return response()->json(['status' => 'fail', 'message' => $barcode. ' '.'Product not found'],200);
            }
            $role = VendorRoleUserMapping::select('role_id')->where('user_id',$vu_id)->first();
            $role_id  = $role->role_id;
            $item = VendorItem::select('track_inventory','negative_inventory')->where('item_id', $bar->item_id)->where('v_id', $v_id)->first();

            $sParams = ['v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $vu_id, 'role_id' => $role_id, 'trans_from' => $trans_from,'udidtoken'=>$udidtoken];
            $vendorS = new VendorSettingController;
            $stockSetting = $vendorS->getStockSetting($sParams);

            $negative_stock_billing_status = null;
            if($stockSetting->negative_stock_billing->status != null){
                $negative_stock_billing_status = $stockSetting->negative_stock_billing->status;
            }
            if($item['track_inventory'] == '1'){
                $negative_stock_billing_status = $item->negative_inventory;
            }
            $stockPoint = StockPoints::where(['v_id'=>$v_id,'store_id'=>$store_id,'is_sellable'=>'1','is_active'=>'1'])->first()->id;
            $stock = StockPointSummary::select(DB::raw("SUM(qty) as total_qty"))->where(['v_id' => $v_id , 'barcode' => $bar->barcode, 'item_id' => $bar->item_id, 'stop_billing' => '0','stock_point_id'=>$stockPoint, 'store_id' => $store_id])->orderBy('id','desc')->first();
            if($negative_stock_billing_status == 0){
                if ($stock->total_qty <= 0){
                    return response()->json(['status' => 'fail', 'message' => 'Negative stock billing is not allowed'], 200);
                }
            }
            if($exists) {
                $status = '1';
            } else {
                $status = '2';
            }
        }

        if($status == 1) {
            return response()->json(['status' => 'success', 'message' => 'Carry Bags Added'],200);
        } else {
            return response()->json(['status' => 'success', 'message' => 'Carry Bags Updated'],200);
        }
        // $carry_bags = DB::table('vendor_carry_bags')->select('BAG_ID','Name','Price')->where('V_ID', $v_id)->where('Store_ID', $store_id)->get();
        // return response()->json(['status' => 'get_carry_bags_by_store', 'data' => print_r(expression)$bags ],200);
    }

    public function deliveryStatus(Request $request)
    {
        $c_id = $request->c_id;
        // $v_id = $request->v_id;
        // $store_id = $request->store_id;
        $cart_id = $request->cart_id;
        $status = $request->status;
        $cart = Cart::find($cart_id)->update([ 'delivery' => $status ]);
        return response()->json(['status' => 'delivery_status_update'],200);
    }

    public function taxCalApi(Request $request)
    {
        $data       = array();
        $qty         = $request->qty;
        $mrp         = $request->s_price;
        $store_id    = $request->store_id;
        $barcode     = $request->barcode;
        $hsn_code    = $request->hsn_code; 
        $v_id        = $request->v_id; 
        $invoice_type= 'B2C';
        $from_gstin  = ''; 
        $to_gstin    = ''; 
        $igst_flag   = false;

        JobdynamicConnection($v_id);


        if(empty($hsn_code) ){
             $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $barcode)->first();

            $item_master = null;
            if($bar){
                $item_master  = VendorSku::select('hsn_code')->where(['vendor_sku_detail_id'=> $bar->vendor_sku_detail_id,'v_id'=>$v_id])->first();
                
                if($item_master){
                    $hsn_code = $item_master->hsn_code;
                }
            }
        }

        if($v_id == 111 || $v_id == 143){
             $params = array('barcode'=>$barcode,'qty'=>$qty,'s_price'=>$mrp,'hsn_code'=>$hsn_code,'store_id'=>$store_id,'v_id'=>$v_id , 'from_gstin' => $from_gstin , 'to_gstin' => $to_gstin , 'invoice_type' => $invoice_type,'type'=>'API' );
        
            return $this->taxCalNew($params);
        }
        //JobdynamicConnection($v_id);

        if(isset($params['invoice_type']) && $params['invoice_type'] !='' &&  $params['invoice_type'] == 'B2B' ){
            $invoice_type =  $params['invoice_type'];
            $from_gstin  = $params['from_gstin']; 
            $to_gstin    = $params['to_gstin']; 
            
            if($params['from_gstin'] !='' && $params['to_gstin'] !='' ){
                return response()->json(['status' => 'fail', 'message' => 'Gstin is Empty'], 200);
            }
            if((strlen($from_gstin) ==15) && (strlen($to_gstin) == 15)){
                if(substr($from_gstin, 0,2) != substr($to_gstin,0,2)){
                    $igst_flag = true;
                }
            }else{
                return response()->json(['status' => 'fail', 'message' => 'Gstin is not Valid '], 200);
            }
        }

        $cgst_amount = 0;
        $sgst_amount = 0;
        $igst_amount = 0;
        $cess_amount = 0;
        $cgst        = 0;
        $sgst        = 0;
        $igst        = 0;
        $cess        = 0;
        $slab_amount = 0;
        $slab_cgst_amount = 0;
        $slab_sgst_amount = 0;
        $slab_cess_amount = 0;
        $tax_amount       = 0;
        $taxable_amount   = 0;
        $total            = 0;
        $to_amount        = 0;
        $tax_name         = '';
        $tax_type         = '';
        $mrp              = round($mrp / $qty, 2);

        $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $barcode)->first();
        $item_master = null;
        if($bar){
            $item_master  = VendorSkuDetails::where(['id'=> $bar->vendor_sku_detail_id,'hsn_code'=>$hsn_code,'v_id'=>$v_id])->with(['tax'=>function($query) use($v_id){
                $query->where('v_id',$v_id);
                }])->first();
            $item_master->barcode = $bar->barcode;

        }

        if(!$item_master){
            $item_master = VendorSkuDetails::where(['sku'=> $barcode,'hsn_code'=>$hsn_code,'v_id'=>$v_id])->with(['tax'=>function($query) use($v_id){
                $query->where('v_id',$v_id);
            }])->first();
            $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('vendor_sku_detail_id', $item_master->id)->first();
            $item_master->barcode = $bar->barcode;


        }
            //echo $item_master->hsn_code;
        if($item_master){


            
                    // echo "<pre>";
                     //echo  $item_master->tax->category->slab;die;
                  //  print_r($item_master->tax->group);die;
            if(isset($item_master->tax->group) ){
                        // if($item_master->category->group)
                if($item_master->tax->category->slab == 'NO'){
                           // print_r($item_master->tax->group);die;
                            //$vl = $item_master->tax->where('v_id',$v_id);
                    $grouRate = $item_master->tax->group;                               
                }
                if($item_master->tax->category->slab == 'YES'){
                        //echo $mrp;
                        $tempmrp=0;
                        $getSlabmrp =  $this->getTaxSlabMrp($mrp,$item_master->tax, $from_gstin, $to_gstin, $invoice_type);
                        if($getSlabmrp){
                            $tempmrp = $getSlabmrp;
                        }
                        //die;
                        $getSlab   = $item_master->tax->slab->where('amount_from','<=',$tempmrp)->where('amount_to','>=',$tempmrp)->first();
                        /*$getSlab   = $item_master->tax->slab->where('amount_from','<=',$mrp)->where('amount_to','>=',$mrp)->first();
                        if($getSlab){
                            $grouRate  = $getSlab->ratemap;
                        } */
                        
                    }
                    
                    /*Start Tax Calculation*/
                    if(isset($grouRate) && count($grouRate) > 0){
                        foreach ($grouRate as $key => $value) {
                            if($igst_flag == false){
                                if($value->type == 'CGST'){
                                     $cgst = $value->rate->name;
                                     $cgst_amount = $value->rate->rate;
                                }

                                 if($value->type == 'SGST'){
                                     $sgst = $value->rate->name;
                                     $sgst_amount = $value->rate->rate;
                                 }

                            }else{

                                if($value->type == 'IGST' && isset($value->rate->rate)){
                                    $igst = $value->rate->name;
                                    $igst_amount = $value->rate->rate;
                                }

                            }
                          
                        if($value->type == 'CESS'){
                            $cess        = $value->rate->name;
                            $cess_amount = $value->rate->rate;
                        }
                    }
                }

                    //echo $cgst_amount.' - '.$sgst_amount.' - '.$igst_amount.' - '.$cess_amount;die;
                $tax_type = null;
                if($request->has('tax_type')) {
                    $tax_type = $request->tax_type;
                } else {
                    if(isset($item_master->vendorItem->tax_type) ){
                        $tax_type = $item_master->vendorItem->tax_type;
                    }else{
                        $tax_type = $item_master->Item->tax_type;
                    }
                }
                if($qty > 0){
                 if($tax_type == 'EXC'){
                        //$mrp  = round($mrp / $qty, 2);

                    $slab_cgst_amount = $this->calculatePercentageAmt($cgst_amount,$mrp);
                    $slab_sgst_amount = $this->calculatePercentageAmt($sgst_amount,$mrp);
                    $slab_cess_amount = $this->calculatePercentageAmt($cess_amount,$mrp);
                    $slab_igst_amount = $this->calculatePercentageAmt($igst_amount,$mrp);

                    $cgst           = $cgst_amount;
                    $sgst           = $sgst_amount;
                    $igst           = $igst_amount;
                    $cess           = $cess_amount;

                    $cgst_amount = $slab_cgst_amount ;
                    $sgst_amount = $slab_sgst_amount ;
                    $igst_amount = $slab_igst_amount;
                    $cess_amount = $this->formatValue($slab_cess_amount);

                    $tax_amount  = $cgst_amount + $sgst_amount + $igst_amount+$cess_amount;

                    $tax_amount  = $this->formatValue($tax_amount);
                        $taxable_amount = floatval($mrp);// - floatval($tax_amount);
                        $taxable_amount = $this->formatValue($taxable_amount);
                        $total          = $taxable_amount + $tax_amount;
                        $tax_name       = $item_master->tax->category->group->name;
                    }else{

                        //$mrp  = round($mrp / $qty, 2); 
                        $slab_cgst_amount = $mrp / ( 100 + $cgst_amount + $sgst_amount + $cess_amount ) * $cgst_amount;
                        $slab_sgst_amount = $mrp / ( 100 + $cgst_amount + $sgst_amount + $cess_amount ) * $sgst_amount;
                        $slab_cess_amount = $mrp / ( 100 + $cgst_amount + $sgst_amount + $cess_amount ) * $cess_amount;
                        $slab_igst_amount = $mrp / ( 100 + $igst_amount + $cess_amount ) * $igst_amount;

                        $cgst           = $cgst_amount;
                        $sgst           = $sgst_amount;
                        $igst           = $igst_amount;
                        $cess           = $cess_amount;

                        $cgst_amount = $slab_cgst_amount ;
                        $sgst_amount = $slab_sgst_amount ;
                        $igst_amount = $slab_igst_amount;
                        $cess_amount = $this->formatValue($slab_cess_amount);

                        $tax_amount  = $cgst_amount + $sgst_amount + $igst_amount+$cess_amount;

                        $tax_amount  = $this->formatValue($tax_amount);
                        $taxable_amount = floatval($mrp) - floatval($tax_amount);
                        $taxable_amount = $this->formatValue($taxable_amount);
                        $total          = $taxable_amount + $tax_amount;
                        $tax_name       = $item_master->tax->category->group->name;
                    }

                }
                /*End Tax Calculation*/
            }
            if($request->has('tax_type')) {
                $tax_type = $request->tax_type;
            } else {
                if(isset($item_master->vendorItem->tax_type) ){
                    $tax_type = $item_master->vendorItem->tax_type;
                }else{
                    $tax_type = $item_master->Item->tax_type;
                }
            }
        }

        $cgst_amount = $cgst_amount * $qty;
        $cgst_amount = round($cgst_amount, 2);
        $sgst_amount = $sgst_amount * $qty;
        $sgst_amount = round($sgst_amount, 2);
        $igst_amount = $igst_amount * $qty;
        $igst_amount = round($igst_amount, 2);
        $slab_cess_amount = $slab_cess_amount * $qty;
        
        if($tax_type == 'EXC'){
             
            $total = $total * $qty;
        }else{
            $total = $mrp * $qty;
                 
        }
        $taxable_amount = $total - $cgst_amount - $sgst_amount - $slab_cess_amount; 
        $tax_amount = $total - $taxable_amount;
        $taxdisplay = $cgst+$sgst;

        $data = [
            'barcode'   => $barcode,
            'hsn'       => $hsn_code,
            'cgst'      => $cgst,
            'sgst'      => $sgst,
            'igst'      => $igst,
            'cess'      => $cess,
            'cgstamt'   => (string)$cgst_amount,
            'sgstamt'   => (string)$sgst_amount,
            'igstamt'   => (string)$igst_amount,
            'cessamt'   => (string)$slab_cess_amount,
            'netamt'    => $mrp,  //$mrp * $qty,
            'taxable'   => (string)$taxable_amount,
            'tax'       => (string)$tax_amount,
            'total'     => $total, //$total * $qty,
            'tax_name'  => 'GST '.$taxdisplay.'%',//$tax_name,
            'tax_type'  => $tax_type
            ];  
           // dd($data);
            return response()->json([ 'data' => $data ], 200);
    } 

    public function testTax(){

        
        $v_id = 127;
        JobdynamicConnection($v_id);
        $invoice_id= 'Z119001102120004';
        $setting   =    DB::table('store_settings')->where('name','Einvoice')->first();
        $invoice     =  Invoice::where('invoice_id',$invoice_id)->first();
        $yearConvert =  date('Y',strtotime($invoice->date));   
        $financeYear =  date('y',strtotime("+12 months $invoice->date")); 
        $monthConvert = date('m',strtotime($invoice->date));   
        $params    = array('v_id'=>$v_id,'settings'=> $setting->settings,'method'=>'POST','invoice_id'=>$invoice->invoice_id,'return_year'=>'2020-2021','return_month'=>$monthConvert);
        //"$yearConvert-$financeYear"
        //print_r($params);die;
        $eInvoice  = new EinvoiceController($params);

        $data     = $eInvoice->checkApi($params);

        print_r($data);

         return $eInvoice->generateQRCode(['content'=>'sanjeev']);



        //$result    = $eInvoice->generateEinvoice($params);
        //$result    = $eInvoice->IrnStatus($params);

          $result = $eInvoice->checkNewOne($params);

        return $result;

          //return $eInvoice->new($params);
         // return $eInvoice->second($params);


        //$result =  $eInvoice->generateEinvoice($params);


        die;

 
         $params = array('barcode'=>'654654654','qty'=>'1','s_price'=>'100','hsn_code'=>'3405','store_id'=>'0','v_id'=>19 , 'from_gstin' => '' , 'to_gstin' => '' , 'invoice_type' => '' );

        return $this->taxCalNew($params);

    }

    public function taxCalNew($params){

        $data      = array();
        $qty       = $params['qty'];
        $mrp       = $params['s_price'];
        $store_id  = $params['store_id'];
        $barcode   = $params['barcode'];
        $hsn_code  = $params['hsn_code']; 
        $v_id      = $params['v_id'];
        $invoice_type = !empty($params['invoice_type'])?$params['invoice_type']:'B2C';
        $total = 0;
        $taxable_amount= 0; 
        $tax_amount   =  0;
        $taxdisplay   =  0;
        $from_gstin   =  ''; 
        $to_gstin     =  ''; 
        $igst_flag    =  false;
        $current_date =  date('Y-m-d h:i:s');
        $sumAllTax = 0;
        //JobdynamicConnection($v_id);
        if(isset($params['invoice_type']) && $params['invoice_type'] !='' &&  $params['invoice_type'] == 'B2B' ){

            $invoice_type   =  $params['invoice_type'];
              $from_gstin   =  $params['from_gstin']; 
              $to_gstin     =  $params['to_gstin']; 
            
            if($params['from_gstin'] == '' && $params['to_gstin'] =='' ){
                return response()->json(['status' => 'fail', 'message' => 'Gstin is Empty'], 200);
            }
            if((strlen($from_gstin) ==15) && (strlen($to_gstin) == 15)){
                if(substr($from_gstin, 0,2) != substr($to_gstin,0,2)){
                    $igst_flag = 2;
                }
            }else{
                return response()->json(['status' => 'fail', 'message' => 'Gstin is not Valid '], 200);
            }
        }

        $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id')->where('v_id', $v_id)->where('barcode', $barcode)->first();
        $item_master = null;
        if($bar){
            $item_master  = VendorSku::select('tax_type','hsn_code','item_id')
            ->where(['vendor_sku_detail_id'=> $bar->vendor_sku_detail_id,'hsn_code'=>$hsn_code,'v_id'=>$v_id,'deleted_at' => null])->with(['tax'=>function($query) use($v_id){
                        $query->where('v_id',$v_id);
                }])->first();            
        }


        if(!$item_master){
            $item_master = VendorSku::select('tax_type','hsn_code','item_id')
            ->where(['sku'=> $barcode,'hsn_code'=>$hsn_code,'v_id'=>$v_id,'deleted_at' => null])->with(['tax'=>function($query) use($v_id){
            $query->where('v_id',$v_id);
            }])->first();
        }

        if($item_master){
            //$mrp              = round($mrp / $qty, 2);
            if(isset($item_master->tax->group) && ($current_date >= $item_master->tax->groups['effective_from']  &&  $current_date <= $item_master->tax->groups['valid_upto']) ){

                //print_r($item_master->tax->groups);die;
              if($item_master->tax->groups->has_slab == '0'){
                    $grouRate = $item_master->tax->group;                               
              }
              if($item_master->tax->groups->has_slab == '1'){
               
               $getSlabmrp =  $this->getTaxSlabMrpNew($mrp,$item_master->tax, $from_gstin, $to_gstin, $invoice_type);
                if($getSlabmrp){
                    $tempmrp = $getSlabmrp;
                }
                $getSlab   = $item_master->tax->slab->where('amount_from','<=',$mrp)->where('amount_to','>=',$mrp)->first();
                if($getSlab){
                    $grouRate  = $getSlab->ratemap;
                } 
              

                 $grouRate = $item_master->tax->group;                               

              }//End slab condition

              if(isset($grouRate) && count($grouRate) > 0){
                //print_r($grouRate);
                $taxData = [];
                foreach ($grouRate as $item) {
                    $rateData   = TaxRate::find($item->tax_code_id);
                    $presetData = TaxGroupPresetDetails::find($item->tg_preset_detail_id);     
                    $taxData[$presetData->preset_name]  = $rateData->rate;
                }                       
              }

            $tax_type = null;
            if(isset($request->tax_type)) {
                $tax_type = $request->tax_type;
            } else {
              if(isset($item_master->vendorItem->tax_type) ){
                $tax_type = $item_master->vendorItem->tax_type;
              }else{
                $tax_type = $item_master->Item->tax_type;
              }
            }

            if($qty > 0){
                $taxInfo = [];
                 if($tax_type == 'EXC'){
                    $sumAllTax = array_sum($taxData);
                    foreach ($taxData as $key => $value) {
                       //$taxAmt  =  $mrp / ( 100 + $sumAllTax) * $value;
                        $taxAmt  =  $this->calculatePercentageAmt($value,$mrp);
                       $taxInfo[$key.'_amt']  = round($taxAmt,2);
                    }
                    $tax_amount  = array_sum($taxInfo);
                    $tax_amount  = $this->formatValue($tax_amount);
                    $taxable_amount = floatval($mrp);// - floatval($tax_amount);
                    $taxable_amount = $this->formatValue($taxable_amount);
                    $total          = $taxable_amount + $tax_amount;
                    $tax_name       = $item_master->tax->groups->name;

                 }else{
                    //print_r($taxData);
                    $sumAllTax = array_sum($taxData);
                    //echo $sumAllTax;die;
                    foreach ($taxData as $key => $value) {
                       $taxAmt  =  $mrp / ( 100 + $sumAllTax) * $value;
                       $taxInfo[$key.'_amt']  = round($taxAmt,2);
                    }
                    //print_r($taxInfo);
                    $tax_amount  = array_sum($taxInfo);
                    $tax_amount  = $this->formatValue($tax_amount);
                    $taxable_amount = floatval($mrp) - floatval($tax_amount);
                    $taxable_amount = $this->formatValue($taxable_amount);
                    $total          = $taxable_amount + $tax_amount;
                    $tax_name       = $item_master->tax->groups->name;
                 
                 }
                
                if($igst_flag == true){
                    $igstTax     = 0;
                    $igstTaxAmt  = 0;
                    foreach ($taxData as $key => $value) {

                        if($tax_type == 'EXC'){
                            $taxAmt  =  $this->calculatePercentageAmt($value,$mrp);
                        }else{
                            $taxAmt  =  $mrp / ( 100 + $sumAllTax) * $value;    
                        }

                        
                     /* $taxAmt  =  $mrp / ( 100 + $sumAllTax) * $value;
                      $taxInfo[$key.'_amt']  = round($taxAmt,2);*/
                      $igstTax    += $value; 
                      $igstTaxAmt += $taxAmt;
                    }
                    $taxData = [];
                    $taxInfo = [];
                    $taxData['IGST']     = $igstTax;
                    $taxInfo['IGST_amt']  = $igstTaxAmt;
            
                }

              }
            
            }else{
                if(isset($request->tax_type)) {
                    $tax_type = $request->tax_type;
                } else {
                    if(isset($item_master->vendorItem->tax_type) ){
                        $tax_type = $item_master->vendorItem->tax_type;
                    }else{
                        $tax_type = $item_master->Item->tax_type;
                    }
                }
                $total            = $mrp;
                $taxData['CGST']  = 0;
                $taxData['SGST']  = 0;
                $taxInfo['CGST_amt']  = 0;
                $taxInfo['SGST_amt']  = 0; 
                $sumAllTax = array_sum($taxData);
                $tax_name  = 'Tax ';

            }

            /*if($tax_type == 'EXC'){
              $total = $total * $qty;
            }else{
              $total = $mrp * $qty;
            }*/
           // 
            $taxable_amount = $total - $tax_amount; 
            $tax_amount = $total - $taxable_amount;
            $taxdisplay = $sumAllTax;            
            /*
            'cgst'      => $cgst,
            'sgst'      => $sgst,
            'igst'      => $igst,
            'cess'      => $cess,
            'cgstamt'   => (string)$cgst_amount,
            'sgstamt'   => (string)$sgst_amount,
            'igstamt'   => (string)$igst_amount,
            'cessamt'   => (string)$slab_cess_amount,
            */    
        }

        $data['barcode'] = $barcode;
        $data['hsn']     = $hsn_code;
        $taxData = [];
        if(count($taxData) > 0){
          foreach($taxData as $key => $val){
           $data[$key]  = $val;
          }
        }
        $taxInfo = [];
        if(count($taxInfo) > 0){
         foreach($taxInfo as $key => $val){
          $data[$key]  = $val;
         }
        }
        $tax_name         = null;
        $sumAllTax = null;
        $tax_type = null;
        $data['netamt']  = $mrp;  //$mrp * $qty,
        $data['taxable'] = (string)$taxable_amount;
        $data['tax']     = (string)$tax_amount;
        $data['total']   = $total;
        $data['tax_name']= $tax_name.' '.$taxdisplay.'%';
        $data['total_tax_per'] = $sumAllTax;
        $data['tax_type']= $tax_type;
        $datas = $data;
        //return response()->json([ 'data' => $datas ], 200);
       // echo $igst_flag;
        //print_r($data);
         
     if(isset($params['type']) && $params['type']=='API'){
        return response()->json([ 'data' => $data ], 200);
     }else{
        return $data;      
     }
    
    }//End of taxCalNew

    public function inboundTaxCalculation(Request $request)
    {  
        $this->validate($request, [
            'v_id'          => 'required'
        ]);
        $tax_applied = true;

    
        // INC & EXC Tax
        $taxType = 'INC';
        if($request->has('tax_type')) {
            $taxType = $request->tax_type;
        }

        $from_gstin   = '';
        $to_gstin     = '';
        $tax_for      = '';
        $invoice_type = '';

        if($request->has('product_list')) {
            $responseData = [];
            $productList = json_decode($request->product_list);

            if(isset($request->from_gstin) && !empty($request->from_gstin)){
                        $from_gstin    = $request->from_gstin;
                    }
                    if(isset($request->to_gstin) && !empty($request->to_gstin)){
                        $to_gstin    = $request->to_gstin;
                        $invoice_type  = 'B2B';

                    }       

            if(isset($request->for) && !empty($request->for)){
                if($request->for == 'GRT' || $request->for == 'SST' || trim($request->for) == 'GRN' ){
                      $tax_for   = $request->for;
                      $tax_type    = 'EXC';
                      //$invoice_type = 'B2B';
                    //If GSTIN SAME for from_gstin and to_gstin tax not applied in GRT/SST/GRN
                    if((strlen($request->from_gstin) ==15) && (strlen($request->to_gstin) == 15)){
                      if(trim($request->from_gstin) == trim($request->to_gstin)){
                        $tax_applied = false;
                      }
                    }else{
                         $tax_applied = false;
                    }

                }
            } 



            foreach ($productList as $key => $value) {
                $objToArry = (array)$value;
                if(array_key_exists('barcode', $objToArry) && array_key_exists('price', $objToArry) && array_key_exists('qty', $objToArry) && array_key_exists('hsn_code', $objToArry)) {
                    // $taxRequest = new \Illuminate\Http\Request();
                    $taxRequest = [
                        'v_id'         => $request->v_id,
                        's_price'      => $value->price,
                        'barcode'      => $value->barcode,
                        'qty'          => $value->qty,
                        'hsn_code'     => $value->hsn_code,
                        'tax_type'     => $value->tax_type,
                        'store_id'     => $request->store_id,
                        'from_gstin'   => $from_gstin,
                        'to_gstin'     => $to_gstin,
                        'tax_for'      => $tax_for,
                        'invoice_type' => $invoice_type

                    ];
                    
                    

            //print_r($taxRequest);
            if($tax_applied == true){
              $responseData[$value->barcode]  = $this->taxCal($taxRequest);     
            }else{
                 $responseData[$value->barcode] = $this->noTaxResponse($taxRequest);     
            }

                    //$responseData[$value->barcode] = $this->taxCal($taxRequest);    
                    
                }
            }

            return response()->json([ 'status' => 'success', 'data' => $responseData ]);
        } else {
            $taxparm = [ 'v_id' => $request->v_id, 's_price' => $request->s_price, 'barcode' => $request->barcode, 'qty' => $request->qty, 'hsn_code' => $request->hsn_code, 'tax_type' => $taxType, 'store_id' => $request->store_id
                    ];
            if(isset($request->from_gstin) && !empty($request->from_gstin)){
                $taxparm['from_gstin'] = $request->from_gstin;
            }
            if(isset($request->to_gstin) && !empty($request->to_gstin)){
                $taxparm['to_gstin'] = $request->to_gstin;
                $taxparm['invoice_type'] = 'B2B';
            }       

            if(isset($request->for) && !empty($request->for)){
              
                if($request->for == 'GRT' || $request->for == 'SST' || $request->for == 'GRN' ){
                      $taxparm['tax_for'] = $request->for;
                      $taxparm['tax_type'] = 'EXC';
                    //If GSTIN SAME for from_gstin and to_gstin tax not applied in GRT/SST/GRN
                    if((strlen($request->from_gstin) ==15) && (strlen($request->to_gstin) == 15)){
                      if(trim($request->from_gstin) == trim($request->to_gstin)){
                        $tax_applied = false;
                      }
                    }else{
                         $tax_applied = false;
                    }

                }

            } 
            if($tax_applied == true){
             $response = $this->taxCal($taxparm);     
            }else{
                 $response = $this->noTaxResponse($taxparm);     
            }

            return response()->json([ 'data' => $response ], 200);
        }
    }


    private function noTaxResponse($params){

    $actualQty      = $params['qty'];
    $qty            = 1;
    $mrp            = $params['s_price'];
    $mrpTotal       = $params['s_price'];
    $store_id       = $params['store_id'];
    $barcode        = $params['barcode'];
    $hsn_code       = $params['hsn_code']; 
    $invoice_type   = 'B2C';
    $from_gstin     = ''; 
    $to_gstin       = ''; 
    $igst_flag      = false;


    $cgst_amount = 0;
    $sgst_amount = 0;
    $igst_amount = 0;
    $cess_amount = 0;
    $cgst        = 0;
    $sgst        = 0;
    $igst        = 0;
    $cess        = 0;
    $slab_amount = 0;
    $slab_cgst_amount = 0;
    $slab_sgst_amount = 0;
    $slab_cess_amount = 0;
    $tax_amount       = 0;
    $taxable_amount   = 0;
    $total            = 0;
    $to_amount        = 0;
    $tax_name         = 'GST 0%';
    $tax_type         = $params['tax_type']; ;
    $item_master = '';
    
    $cgst_amount = $cgst_amount * $qty;
    $cgst_amount = $cgst_amount;
    $sgst_amount = $sgst_amount * $qty;
    $sgst_amount = $sgst_amount;
    $igst_amount = $igst_amount * $qty;
    $igst_amount = $igst_amount;
    $slab_cess_amount = $slab_cess_amount * $qty;
    
    if($tax_type == 'EXC'){
        $total = $mrp * $qty;
    }else{
        $total = $mrp * $qty;
    }

    if($igst_flag==false){
        $taxable_amount = $total - round($cgst_amount,2) - round($sgst_amount,2) - round($slab_cess_amount,2); 
        $tax_amount = $total - $taxable_amount;
        $taxdisplay = $cgst+$sgst;
    }else{
        $taxable_amount = $total - round($igst_amount,2) - round($slab_cess_amount,2); 
        $tax_amount = $total - $taxable_amount;
        $taxdisplay = $igst;
    }

    $data = [
        'barcode'   => $barcode,
        'hsn'       => $hsn_code,
        'qty'       => $actualQty,
        'cgst'      => $cgst,
        'sgst'      => $sgst,
        'igst'      => $igst,
        'cess'      => $cess,
        'cgstamt'   => (string)round($cgst_amount,2),
        'sgstamt'   => (string)round($sgst_amount,2),
        'igstamt'   => (string)round($igst_amount,2),
        'cessamt'   => (string)round($slab_cess_amount,2),
        'netamt'    => $mrp,  //$mrp * $qty,
        'taxable'   => (string)round($taxable_amount,2),
        'tax'       => (string)round($tax_amount,2),
        'total'     => $total, //$total * $qty,
        'tax_name'  => 'GST '.$taxdisplay.'%',//$tax_name,
        'tax_type'  => $tax_type
        ];  
       //dd($data);
        return $data;    


    }

}
