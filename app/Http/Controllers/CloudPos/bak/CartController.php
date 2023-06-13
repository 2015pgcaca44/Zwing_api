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


class CartController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth' , ['except' => ['order_receipt','rt_log'] ]);
        $this->cartconfig  = new CartconfigController;     
    }

    private function store_db_name($store_id)
    {    
        if($store_id){
            $store     = Store::find($store_id);
            $store_name= $store->store_db_name;
            return $store_name;
        }else{
            return false;
        }
    } 

    public function add_to_cart(Request $request)
    {
        //dd($request);
        $v_id       = $request->v_id;
        $c_id       = $request->c_id;
        $store_id   = $request->store_id;
        $vu_id      = $request->vu_id;
        //$product_id = $request->product_id;
        $barcode    = $request->barcode;
        //$barcode = $request->ogbarcode;
        $qty        = $request->qty;
        $unit_mrp   = $request->unit_mrp;
        $unit_rsp   = $request->unit_rsp;
        $r_price    = $request->r_price;
        $s_price    = $request->s_price;
        $discount   = $request->discount;
        $pdata      = urldecode($request->pdata);
        $spdata     = urldecode($request->pdata);
        $all_data   = json_encode($request->data);
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
        $pdata            = json_decode($pdata);
        $taxs             = [];
        $trans_from       = $request->trans_from;
        $total_tax        = 0;
         
        if(empty($pdata)){
            //echo 'indisde this ';exit;
            $total = $unit_mrp * $qty;
            $final_data['pdata'][] = [ 'qty' => $qty, 'mrp' => $unit_mrp,'r_price'=>$r_price,'s_price'=>$s_price, 'discount' => 0, 'ex_price' => $total, 'total_price' => $total, 'message' => '' , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0,'tax'=>$taxs];
            $final_data['available_offer']  = [];
            $final_data['applied_offer']    = [];
            $final_data['item_id']          = $barcode;
            $final_data['r_price']          = $r_price* $qty;
            $final_data['s_price']          = $s_price* $qty;
            $final_data['total_tax']        = $total_tax;
            $final_data['multiple_price_flag'] =  $multiple_mrp_flag;
            $final_data['multiple_mrp']     = $mrp_arrs;
            $pdata   = json_encode($final_data);
            $pdataD  = json_decode($pdata);
        }else{
            $pdataD = $pdata;
        }
       

        
        $plu_flag = false;
        $plu_barcode = 0;
         
        $item_master = VendorSkuDetails::where(['barcode'=> $barcode,'v_id'=>$v_id])->first();
        if(!$item_master){
            $item_master = VendorSkuDetails::where(['sku'=> $barcode,'v_id' => $v_id])->first();
        }


        /*Tax Calculation*/

        $params = array('barcode'=>$barcode,'qty'=>$qty,'s_price'=>$s_price,'hsn_code'=>$item_master->hsn_code,'store_id'=>$store_id,'v_id'=>$v_id);
        $tax_details = $this->taxCal($params);

        //$tax_details = 0;
        /*Tax Calculation end*/

        $order_id    = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id    = $order_id + 1;

        Cart::where('item_id', '!=', $barcode)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('order_id', '!=',  $order_id)->where('status', 'process')->delete();

        $cart_list = Cart::where('item_id', '!=', $barcode)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('order_id', $order_id)->where('status', 'process')->get();

        $subtotal  = $s_price*$qty;
        $taxpayble = 0;
        if($item_master->Item->tax_type == 'EXC'){
            $taxpayble = format_number($tax_details['tax'], 2);
        }
        $total          = ($s_price*$qty-$discount)+$taxpayble;
        $cart           = new Cart;
        $cart->store_id = $store_id;
        $cart->v_id     = $v_id;
        $cart->order_id = $order_id;
        $cart->user_id  = $c_id;
        if($plu_flag){
          $cart->plu_barcode = $plu_barcode;  
        }
        $cart->barcode  = $barcode;
        $cart->qty      = $qty;
        $cart->item_id  = $item_master->barcode;
        $cart->item_name= $this->cartconfig->getItemName($item_master->Item->name,$item_master->variant_combi);
                    //$item_master->Item->name.' ('.$item_master->variant_combi.')';
        $cart->unit_mrp = $unit_mrp;
        $cart->unit_csp = $unit_rsp;
        //'unit_csp' => $unit_rsp,
        $cart->subtotal = $r_price;
        $cart->total    = $s_price;
        $cart->discount = $discount;
       
        $cart->status   = 'process';
        $cart->trans_from = $trans_from;
        $cart->vu_id    = $vu_id;
        $cart->date     = date('Y-m-d');
        $cart->time     = date('h:i:s');
        $cart->month    = date('m');
        $cart->year     = date('Y');
        $cart->tax      = format_number($tax_details['tax'], 2);
        $cart->pdata    = $spdata;
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
        $cart->section_target_offers = $all_data;
        $cart->department_id = $product_response->DEPARTMENT_CODE;
        $cart->group_id = $product_response->SECTION_CODE;
        $cart->division_id = $product_response->DIVISION_CODE;
        $cart->subclass_id = $product_response->ARTICLE_CODE;
        //$cart->target_offer = (isset($data->target))?json_encode($data->target):'';
        //$cart->section_offers = (isset($data->section_offer))?json_encode($data->section_offer):'';
        //$cart->subclass_id = $item_master->ID_MRHRC_GP_SUBCLASS;
        //$cart->printclass_id = $item_master->ID_MRHRC_GP_PRNT_CLASS;
        //$cart->group_id = $item_master->ID_MRHRC_GP_PRNT_GROUP;
        //$cart->division_id = $item_master->ID_MRHRC_GP_PRNT_DIVISION;
        $cart->save();

        $cartD  = array('barcode'=>$barcode,'cart_id'=>$cart->cart_id,'pdata'=>$pdataD);
        $this->addCartDetail($cartD);
        $offerD = array('cart_id'=>$cart->cart_id,'item_id'=>$barcode,'mrp'=>$unit_mrp,'qty'=>$qty,'offers'=>$pdata);
        
        $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id  , 'exclude_barcode' => $barcode ];
        //$this->process_each_item_in_cart($params);

        $carts = DB::table('cart')->where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();

        $total_amount = format_number($carts->sum('total'));
        $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id , 'exclude_barcode' => $barcode ];
        $this->process_each_item_in_cart($params);

        // $carts = DB::table('cart')->where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();

        // // Memo Level Promo

        // $memoPromotions = null;
        // $offerParams['carts'] = $carts;
        // $offerParams['store_id'] = $store_id;
        // $offerParams['v_id'] = $v_id;
        // $memoPromo = new PromotionController;
        // $memoPromotions = $memoPromo->memoIndex($offerParams);

        // if (!empty($memoPromotions)) {
        //     $mParams['store_id'] = $store_id;
        //     $mParams['v_id'] = $v_id;
        //     // $mParams['items'] = $memoPromotions;
        //     $memoPromotions = collect($memoPromotions);
        //     if(!empty($memoPromotions->where('item_id', $barcode)->count())) {
        //         $mParams['items'] = $memoPromotions->values();
        //         $this->reCalculateTax($mParams);
        //         foreach($memoPromotions as $promos){
        //             $mParams['items']= [ $promos ];
        //             DB::table('cart_offers')->where('cart_id' , $promos->cart_id)->delete();
        //             $offerD = array('cart_id'=>$promos->cart_id,'item_id'=>$promos->item_id,'mrp'=>$promos->unit_mrp,'qty'=>$promos->qty,'offers'=> json_encode($mParams['items']));
        //             CartOffers::create($offerD);

        //         }
        //     }
        //     $carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();
        // }
        
        if ($request->has('get_data_of')) {
            if ($request->get_data_of == 'CART_DETAILS') {
                return $this->cart_details($request);
            } else if ($request->get_data_of == 'CATALOG_DETAILS') {
                $catalogC = new CatalogController;
                return $catalogC->getCatalog($request);
            }
        }

        return response()->json(['status' => 'add_to_cart', 'message' => 'Product was successfully added to your cart.', 'total_qty' => $carts->sum('qty'), 'total_amount' => $total_amount,
        ], 200);


        /*
        foreach ($data as $key => $val) {
                if ($key == 'pdata') {
                    foreach ($val as $key => $value) {
                        $cart_details = DB::table('cart_details')->insert([
                            'cart_id' => $cart->cart_id,
                            'qty' => $value->qty,
                            'mrp' => $value->mrp,
                            'price' => $value->total_price,
                            'discount' => $value->discount,
                            'ext_price' => $value->ex_price,
                            'tax' => '',
                            'message' => $value->message,
                            'ru_prdv' => $value->ru_prdv,
                            'type' => $value->type,
                            'type_id' => $value->type_id,
                            'promo_id' => $value->promo_id,
                            'is_promo' => $value->is_promo,
                            'taxes' => isset($value->tax)?json_encode($value->tax):''
                        ]);
                    }
                }
            }

        if ($trans_from == 'ANDROID_VENDOR' || $trans_from == 'IOS_VENDOR') {

            $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id ];
            $this->process_each_item_in_cart($params);

            return response()->json(['status' => 'add_to_cart', 'message' => 'Product was successfully added to your cart.', 'data' => $cart ],200);
        } else {
            if($request->has('process_each_item') && $request->process_each_item != 1){
                $carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status','process')->get();
                $cart_summa['total_qty'] = $carts->sum('qty');
                $cart_summa['total_amount'] = $carts->sum('total');
            }else{

                $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id ];
                $cart_summa = $this->process_each_item_in_cart($params);
            }

            $response = ['status' => 'success', 'message' => 'Product was successfully added to your cart.',
            //'data' => $cart ,
            'total_qty' => $cart_summa['total_qty'] , 'total_amount' => $cart_summa['total_amount'] ] ;
            if($request->has('get_data_of')){
                $catalogC = new CatalogController;
                return $catalogC->getCatalog($request);
            }

            return response()->json($response ,200);
        }
        */        
    }

    private function reCalculateTax($params)
    {
        foreach ($params['items'] as $key => $value) {
            $itemTax = $this->taxCal([
                'barcode'   => $value->item_id,
                'qty'       => $value->qty,
                's_price'   => $value->total,
                'hsn_code'  => $value->hsn,
                'store_id'  => $params['store_id'],
                'v_id'      => $params['v_id']
            ]);

            Cart::find($value->cart_id)->update([ 'bill_buster_discount' => $value->discount, 'total' => $value->total, 'tax' => $itemTax['tax'], 'tdata' => json_encode($itemTax) ]);
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
        //dd($values);
        $v_id       = $values->v_id;
        $c_id       = $values->c_id;
        $store_id   = $values->store_id;
        //$product_id = $values->product_id;
        $barcode    = $values->barcode;
        $qty        = $values->qty;
        $unit_mrp   = $values->unit_mrp;
        $unit_rsp   = $values->unit_rsp;
        $r_price    = $values->r_price;
        $s_price    = $values->s_price;
        $discount = $values->discount;
        $pdata = urldecode($values->pdata);
        $target_offer = urldecode($values->target_offer);
        $spdata = urldecode($values->pdata);
        $all_data = json_encode($values->data);
        $product_response = urldecode($values->data['item_det']);
        $product_response = json_decode($product_response);
        $pdata = json_decode($pdata);
        //$product_response = urldecode($values->data['item_det']);
        //$product_response = json_decode($product_response);
        // $product_response = json_decode($product_response);
        $taxs       = [];
        
 
       

        /*if(empty($pdata)){
            //echo 'indisde this ';exit;
            $total = $unit_mrp * $qty;
            $final_data['pdata'][] = [ 'qty' => $qty, 'mrp' => $unit_mrp, 'discount' => 0, 'ex_price' => $total, 'total_price' => $total, 'message' => '' , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0];
            $final_data['available_offer'] = [];
            $final_data['applied_offer'] = [];
            $final_data['item_id'] = $barcode;

            $pdata = json_encode($final_data);
            $data = json_decode($pdata);
        }*/


        if(empty($pdata)){
            //echo 'indisde this ';exit;
            //$total = $unit_mrp * $qty;
            $final_data['pdata'][] = [ 'qty' => $qty, 'mrp' => $unit_mrp,'r_price'=>$r_price,'s_price'=>$s_price, 'discount' => 0, 'ex_price' => $total, 'total_price' => $total, 'message' => '' , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0,'tax'=>$taxs];
            $final_data['available_offer']  = [];
            $final_data['applied_offer']    = [];
            $final_data['item_id']          = $barcode;
            $final_data['r_price']          = $r_price;
            $final_data['s_price']          = $s_price;
            $final_data['total_tax']        = $total_tax;
            $final_data['multiple_price_flag'] =  $multiple_mrp_flag;
            $final_data['multiple_mrp']     = $mrp_arrs;

            $pdata = json_encode($final_data);
            $pdataD = json_decode($pdata);
        }
        
        $item_master     = VendorSkuDetails::where(['barcode'=> $barcode,'v_id'=>$v_id])->first();
        if(!$item_master){
            $item_master = VendorSkuDetails::where(['sku'=> $barcode,'v_id'=>$v_id])->first();
        }

        // dd($item_master);


        /*Tax Calculation*/
        $params      = array('barcode'=>$barcode,'qty'=>$qty,'s_price'=>$s_price,'hsn_code'=>$item_master->hsn_code,'store_id'=>$store_id,'v_id'=>$v_id);
        $tax_details = $this->taxCal($params);

        $subtotal  = $s_price*$qty;
        $taxpayble = 0;
        if($item_master->Item->tax_type == 'EXC'){
            $taxpayble = format_number($tax_details['tax'], 2);
        }
        $total     = ($s_price*$qty-$discount)+$taxpayble;

        //$tax_details = 0;
        /*Tax Calculation end*/

         
        // dd($pdata);

        $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

        $cart_id = Cart::where('transaction_type', 'sales')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('status', 'process')->where('item_id', $barcode)->update([
            'store_id'           => $store_id,
            'transaction_type'   => 'sales',
            'v_id'               => $v_id,
            'order_id'           => $order_id,
            'user_id'            => $c_id,
            'barcode'            => $barcode,
            'item_id'            => $barcode,
            'qty'                => $qty,
            'unit_mrp'           => $unit_mrp,
            'unit_csp'           => $unit_rsp,
            'subtotal'           => $r_price,
            'total'              => $s_price,
            'trans_from'         => $values->trans_from,
            'vu_id'              => $values->vu_id,
            'discount'           => $discount,
            'status'             => 'process',
            'date'               => date('Y-m-d'),
            'time'               => date('H:i:s'),
            'month'              => date('m'),
            'year'               => date('Y'),
            'tax'                => format_number($tax_details['tax'], 2),
            'pdata'             => $spdata,
            'tdata'             => json_encode($tax_details),
            'target_offer'      => $target_offer,
            'section_target_offers' => $all_data,
            'weight_flag'       => (string)$values->weight_flag,
        ]);
        // dd($cart_id);
        $cartGet = Cart::where('transaction_type', 'sales')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('status', 'process')->where('item_id', $barcode)->first();
        $cartD  = array( 'barcode' => $barcode , 'cart_id' => $cartGet->cart_id, 'pdata' => $pdata);
        $this->addCartDetail($cartD);

        $carts = DB::table('cart')->where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();

        $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id  , 'exclude_barcode' => $barcode , 'call_process_to_each' => false ];
        if(isset($values->call_process_to_each) && $values->call_process_to_each == false){

        }else{

            $this->process_each_item_in_cart($params);        
        }

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

        
        $carts = DB::table('cart')->where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();


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
            'total_qty' => $carts->sum('qty'), 'total_amount' => $carts->sum('total'),
        ], 200);
    }

    public function process_each_item_in_cart($params){
        $v_id = $params['v_id'];
        $store_id = $params['store_id'];
        $c_id = $params['c_id'];

        //$stores        = DB::table('stores')->select('name', 'mapping_store_id' ,'store_db_name')->where('store_id', $store_id)->first();
        //$store_db_name = $stores->store_db_name;
        $db_structure = DB::table('vendor')->select('db_structure')->where('id',$v_id)->first()->db_structure;

        $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

        #######Comment this beacuse need to calculate Target Offer #######
    
        // if(isset($params['exclude_barcode'])){
        //     $cart_list = Cart::where('item_id', '!=', $params['exclude_barcode'] )->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('order_id', $order_id)->where('status', 'process');
        // }else{
            $cart_list = Cart::where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('order_id', $order_id)->where('status', 'process')->orderByDesc('target_offer');
        // }

        if(isset($params['target_item_ids']) && count($params['target_item_ids'])> 1 ){
            $cart_list = $cart_list->whereIn('item_id', $params['target_item_ids']);
        }
        
        $cart_list = $cart_list->get();
        
        //dd($cart_list);



        foreach ($cart_list as $key => $cart) {
            
            //Added inside because for GUy and GEt promotion wanted updated target offer data
            $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();
            
            $single_cart_data['v_id'] = $v_id;
            $single_cart_data['is_cart'] = 1;
            $single_cart_data['is_update'] = 0;
            $single_cart_data['store_id'] = $store_id;
            $single_cart_data['c_id'] = $c_id;
            $single_cart_data['trans_from'] = $cart->trans_from;
            $single_cart_data['barcode'] = $cart->item_id;
            $single_cart_data['qty'] = $cart->qty;
            $single_cart_data['vu_id'] = $cart->vu_id;
            $single_cart_data['mapping_store_id'] = $store_id;
            
            $item  = VendorSkuDetails::where(['vendor_sku_details.barcode'=> $cart->barcode ,'vendor_sku_details.v_id'=>$v_id,'stock_current_status.stop_billing'=>0])
                                       ->join('stock_current_status','stock_current_status.item_id','vendor_sku_details.item_id')
                                       ->select('stock_current_status.*',
                                        'vendor_sku_details.*')
                                       ->first();

            $productC = new ProductController;
            $item = $productC->getItemDetailsForPromo(['item' => $item, 'v_id' => $v_id , 'unit_mrp' => $cart->unit_mrp]);
            $price = $item['price'];

            $item = $item['item'];

            $single_cart_data['item'] = $item;
            $single_cart_data['store_db_name'] ='';
            $single_cart_data['db_structure'] = $db_structure;

            $single_cart_data['carts'] = $carts;

            //dd($single_cart_data);
            $promoC = new PromotionController;
            $offer_data = $promoC->index($single_cart_data);

            $responseData = $offer_data;

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

            $data = (object)[ 'v_id' => $single_cart_data['v_id'], 'store_id' => $single_cart_data['store_id'], 'c_id' => $single_cart_data['c_id'], 'barcode' => $offer_data['barcode'], 'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'data' => $responseData, 'trans_from' => $single_cart_data['trans_from'], 'vu_id' => $single_cart_data['vu_id'], 'weight_flag' => $offer_data['weight_flag'] , 'target_offer' => $offer_data['target_offer'], 'call_process_to_each' => $call_process_to_each ];

                // dd($data);
                // $cart = new CartController;
            $this->update_to_cart($data);

        }

        //Order by desc added for targer_offer to calculate target offer properly
        $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->orderByDesc('target_offer')->get();
        //This condition is added to recalculate target item promotion
        if( !isset($params['target_offer_called'])){
            $t_pdata = [];
            foreach($carts as $cart){
                // dd($cart);
                $t_pdata = array_merge($t_pdata , json_decode($cart->target_offer));
            }
            $t_item_id = array_unique( collect($t_pdata)->pluck('item_id')->all() );
            // dd($t_pdata);
            if(count($t_item_id) >=1){
                $para = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id , 'target_offer_called' => true , 'target_item_ids' => $t_item_id ];
                $this->process_each_item_in_cart($para);
            }
        }
        

        //MEMO LEVEL Promotions
        $carts = DB::table('cart')->where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('order_id', $order_id)->where('status', 'process')->get();

        $memoPromotions = null;
        $offerParams['carts'] = $carts;
        $offerParams['store_id'] = $store_id;
        $offerParams['v_id'] = $v_id;
        $memoPromo = new PromotionController;
        $memoPromotions = $memoPromo->memoIndex($offerParams);
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
                $itemDet = urldecode($itemDet->item_det);
                $itemDet = json_decode($itemDet);
                $itemData[] = (object)[ 'item_id' => $mCart->item_id, 'qty' => $mCart->qty, 'total' => $mCart->total, 'hsn' => $itemDet->hsn_code, 'cart_id' => $mCart->cart_id, 'discount' => 0 ];
            }
            $mParams['items'] = $itemData;
            $this->reCalculateTax($mParams);
        }

    }

    public function taxCal($params){

        $data    = array();
        $qty         = $params['qty'];
        $mrp         = $params['s_price'];
        $store_id    = $params['store_id'];
        $barcode     = $params['barcode'];
        $hsn_code    = $params['hsn_code']; 
        $v_id        = $params['v_id']; 

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
        
        $item_master  = VendorSkuDetails::where(['barcode'=> $barcode,'hsn_code'=>$hsn_code,'v_id'=>$v_id])->with(['tax'=>function($query) use($v_id){
                        $query->where('v_id',$v_id);
        }])->first();
        if(!$item_master){
            $item_master = VendorSkuDetails::where(['sku'=> $barcode,'hsn_code'=>$hsn_code,'v_id'=>$v_id])->with(['tax'=>function($query) use($v_id){
                            $query->where('v_id',$v_id);
            }])->first();
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
                    /*$tempmrp  = round($mrp / $qty, 2);
                    $getSlab   = $item_master->tax->slab->where('amount_from','<=',$tempmrp)->where('amount_to','>=',$tempmrp)->first();*/

                    $getSlab   = $item_master->tax->slab->where('amount_from','<=',$mrp)->where('amount_to','>=',$mrp)->first();

                    if($getSlab){
                        $grouRate  = $getSlab->ratemap;
                    } 
                   // $getRateMap = $getSlab->ratemap;
                }
                /*Start Tax Calculation*/
                if(isset($grouRate) && count($grouRate) > 0){
                foreach ($grouRate as $key => $value) {
                        
                    if($value->type == 'CGST'){
                         $cgst = $value->rate->name;
                        $cgst_amount = $value->rate->rate;
                    }

                    if($value->type == 'SGST'){
                         $sgst = $value->rate->name;
                        $sgst_amount = $value->rate->rate;
                    }

                    if($value->type == 'IGST'){
                        $igst = $value->rate->name;
                        $igst_amount = $value->rate->rate;
                    }

                    if($value->type == 'CESS'){
                        $cess        = $value->rate->name;
                        $cess_amount = $value->rate->rate;
                    }
                }
              }

                //echo $cgst_amount.' - '.$sgst_amount.' - '.$igst_amount.' - '.$cess_amount;die;

                if($qty > 0){
                 if($item_master->Item->tax_type == 'EXC'){
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
            $tax_type = $item_master->Item->tax_type;
        }
        $cgst_amount = $cgst_amount * $qty;
        $cgst_amount = round($cgst_amount, 2);
        $sgst_amount = $sgst_amount * $qty;
        $sgst_amount = round($sgst_amount, 2);
        $igst_amount = $igst_amount * $qty;
        $igst_amount = round($igst_amount, 2);
        $slab_cess_amount = $slab_cess_amount * $qty;
        $total = $mrp * $qty;
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
        //dd($data);
        return $data;
    
    }//End of taxCal

    private function calculatePercentageAmt($percentage,$amount){
        if(isset($percentage)  && isset($amount)){
            $result = ($percentage / 100) * $amount;
            return round($result,2);
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

            $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id , 'employee_available_discount' => $employee_details->Available_Discount_Amount , 'employee_id' => $employee_details->Employee_ID , 'company_name' => $company_name ];

            return $this->process_each_item_in_cart($params);

            

        }else{
            return response()->json(['status' => 'fail', 'message' => 'Unable to find the employee'], 200);
        }

    }
    
    
    public function remove_employee_discount(Request $request){
        
        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;

        $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

        $cart = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->update(['employee_id' => '' , 'employee_discount' => 0.00]);


        $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id   ];

        $this->process_each_item_in_cart($params);

        return response()->json(['status' => 'success', 'message' => 'Removed Successfully' ]);

    }

    public function product_qty_update(Request $request)
    {
        //dd($request);
        $v_id       = $request->v_id;
        $c_id       = $request->c_id;
        $store_id   = $request->store_id;
        $trans_from = $request->trans_from;
        $vu_id      = $request->vu_id;
        if($request->has('ogbarcode')){
            $barcode = $request->ogbarcode;
        }else{
            $barcode = $request->barcode;
        }

        if($request->has('unit_csp')){
            $unit_csp = $request->unit_csp;
        }
        $qty        = $request->qty;
        $unit_mrp   = $request->unit_mrp;
        //$unit_rsp   = $request->unit_rsp;
        $r_price    = $request->r_price;
        $s_price    = (float)$request->s_price*(int)$qty;
        $discount   = $request->discount;
        $pdata      = $request->pdata;
        $stores         = Store::select('name', 'mapping_store_id' ,'store_db_name')->where('store_id', $store_id)->first();
        $store_name     = $stores->name;
        $store_db_name  = $stores->store_db_name;
         
        /*Get Price List */
        $item       = VendorSkuDetails::where(['barcode'=>$barcode,'v_id'=>$v_id])->first();
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
        $item = $productC->getItemDetailsForPromo(['item' => $item, 'v_id' => $v_id , 'unit_mrp' => $unit_mrp]);
        $price = $item['price'];
        $mrp_arrs          = $price['mrp_arrs'];
        $multiple_mrp_flag = $price['multiple_mrp_flag'];
        $item = $item['item'];


        //dd($item->toArray());
        
        $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

        $check_product_exists = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('barcode', $barcode)->where('status', 'process')->first();

        $check_product_exists->unit_mrp = $price['unit_mrp'];
        if($request->has('unit_csp')){
            $check_product_exists->unit_csp =  $unit_csp;
        }else{
            $check_product_exists->unit_csp = (!empty($price['s_price']))?$price['s_price']:$price['unit_mrp'];;
        }
        $check_product_exists->save();

        $carts = DB::table('cart')->where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();


        $params = ['v_id' => $v_id, 'trans_from' => $trans_from, 'barcode' => $barcodefrom, 'qty' =>  $qty, 'mapping_store_id' => $store_id , 'item' => $item, 'carts' => $carts , 'store_db_name' => $store_db_name, 'is_cart' => 1, 'is_update' => 1, 'db_structure' => $db_structure  ];
        // dd($params);

        $offer_data = $promoC->index($params);
        $data = $offer_data;
        // dd($offer_data);

        $request->request->add(['barcode' => $barcodefrom ,'ogbarcode' => $barcodefrom , 'qty' =>$qty , 'unit_mrp' => $unit_mrp ,'unit_rsp'=> $r_price, 'r_price' => $r_price , 's_price' => $s_price , 'discount' => $offer_data['discount'] , 'pdata' => $offer_data['pdata'] ,'data'=> $data,'multiple_mrp_flag'=>$multiple_mrp_flag,'mrp_arrs'=>$mrp_arrs, 'is_catalog' =>'0', 'weight_flag' => $offer_data['weight_flag'], 'target_offer' => $offer_data['target_offer'] ]);

        $data = (object)[ 'v_id' => $v_id, 'store_id' => $store_id, 'c_id' => $c_id, 'barcode' => $offer_data['barcode'], 'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'tdata' => $offer_data['tdata'], 'data' => $offer_data, 'trans_from' => $trans_from, 'vu_id' => $vu_id, 'weight_flag' => $offer_data['weight_flag'], 'target_offer' => $offer_data['target_offer'] ];

        /*
        $data = (object)[ 'v_id' => $v_id, 'store_id' => $store_id, 'c_id' => $c_id, 'barcode' => $offer_data['barcode'], 'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'tdata' => $offer_data['tdata'], 'data' => $offer_data, 'trans_from' => $trans_from, 'vu_id' => $vu_id ];
 
        
        if(empty($pdata)){
            //echo 'indisde this ';exit;
            // $total  = $unit_mrp * $qty;
            // $final_data['pdata'][] = [ 'qty' => $qty, 'mrp' => $unit_mrp, 'discount' => 0, 'ex_price' => $total, 'total_price' => $total, 'message' => '' , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0];
            // $final_data['available_offer'] = [];
            // $final_data['applied_offer']   = [];
            // $final_data['item_id']         = $barcode;
            // $pdata = json_encode($final_data);
            // $dataO  = json_decode($pdata);

            $taxs = [];
             $total = $unit_mrp * $qty;
             $total_tax = 0;
            $final_data['pdata'][] = [ 'qty' => $qty, 'mrp' => $unit_mrp,'r_price'=>$r_price,'s_price'=>$s_price, 'discount' => 0, 'ex_price' => $total, 'total_price' => $total, 'message' => '' , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0,'tax'=>$taxs];
            $final_data['available_offer']  = [];
            $final_data['applied_offer']    = [];
            $final_data['item_id']          = $barcode;
            $final_data['r_price']          = $r_price* $qty;
            $final_data['s_price']          = $s_price* $qty;
            $final_data['total_tax']        = $total_tax;
            $final_data['multiple_price_flag'] =  $multiple_mrp_flag;
            $final_data['multiple_mrp']     = $mrp_arrs;
            $pdata   = json_encode($final_data);
            $dataO  = json_decode($pdata);
        }

        $data = (object)[ 'v_id' => $v_id, 'store_id' => $store_id, 'c_id' => $c_id, 'barcode' => $barcode, 'qty' => $qty, 'unit_mrp' => $unit_mrp, 'unit_rsp' => @$unit_rsp, 'r_price' => $r_price, 's_price' => $s_price, 'discount' => $discount, 'pdata' => $pdata, 'data' => $dataO, 'trans_from' => $trans_from, 'vu_id' => $vu_id ];*/
        

            // dd($data);
            // $cart = new CartController;
        $this->update_to_cart($data);
        
        $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id  , 'exclude_barcode' => $barcodefrom ];
        $this->process_each_item_in_cart($params);

        $cart_list = Cart::where('item_id', '!=', $barcode)->where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('order_id', $order_id)->where('status', 'process')->get();

        // foreach ($cart_list as $key => $cart) {
        //     $single_cart_data['v_id']       = $v_id;
        //     $single_cart_data['is_cart']    = 1;
        //     $single_cart_data['is_update']  = 0;
        //     $single_cart_data['store_id']   = $store_id;
        //     $single_cart_data['c_id']       = $c_id;
        //     $single_cart_data['trans_from'] = $cart->trans_from;
        //     $single_cart_data['barcode']    = $cart->item_id;
        //     $single_cart_data['item_name']  = $cart->item_name;
        //     $single_cart_data['qty']        = $cart->qty;

        //     $single_cart_data['unit_mrp']   = $cart->unit_mrp;
        //     $single_cart_data['unit_rsp']   = $cart->unit_rsp;
        //     $single_cart_data['r_price']    = $cart->r_price;
        //     $single_cart_data['s_price']    = $cart->s_price;
        //     $single_cart_data['discount']   = $cart->discount;
        //     $single_cart_data['pdata']      = $cart->pdata;


        //     $single_cart_data['vu_id']      = $cart->vu_id;
        //     $single_cart_data['mapping_store_id'] = $cart->store->mapping_store_id;
            

        //     //$item = DB::table($cart->store->store_db_name.'.invitem')->select('GRPCODE', 'INVARTICLE_CODE','BARCODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE', 'LISTED_MRP', 'DESC1', 'DESC2', 'DESC3', 'DESC4', 'DESC5', 'DESC6')->where('ICODE', $cart->item_id)->first();

        //     $item = VendorSkuDetails::where('barcode', $cart->barcode)->first();
        //     $price_master = $item->variantPrices[0];

        //     $single_cart_data['item']           = $item;
        //     $single_cart_data['store_db_name']  = $cart->store->store_db_name;

        //     $carts = Cart::where('store_id', $cart->store_id)->where('v_id', $cart->v_id)->where('order_id', $cart->order_id)->where('user_id', $cart->user_id)->where('status', 'process')->get();

        //     $single_cart_data['carts'] = $carts;
            
        //     /*Start Promotion
        //         //$promoC = new PromotionController;
        //         //$offer_data = $promoC->index($single_cart_data);
        //     /*End Promotion*/

        //     // dd($offer_data);
        //     $data = (object)[ 'v_id' => $single_cart_data['v_id'], 'store_id' => $single_cart_data['store_id'], 'c_id' => $single_cart_data['c_id'], 'barcode' => $single_cart_data['barcode'], 'qty' => $single_cart_data['qty'], 'unit_mrp' => $single_cart_data['unit_mrp'], 'unit_rsp' => $single_cart_data['unit_rsp'], 'r_price' => $single_cart_data['r_price'], 's_price' => $single_cart_data['s_price'], 'discount' => $single_cart_data['discount'], 'pdata' => $single_cart_data['pdata'], 'data' => $single_cart_data, 'trans_from' => $single_cart_data['trans_from'], 'vu_id' => $single_cart_data['vu_id'] ];
        //     // dd($data);
        //     // $cart = new CartController;
        //     $this->update_to_cart($data);
        //     // $this->process_each_item_in_cart($single_cart_data);
        // }

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
    


    }

    public function remove_product(Request $request)
    {
        $v_id = $request->v_id;

        $c_id = $request->c_id;
        $store_id = $request->store_id;
        $v_id = $request->v_id;
        $wherediscount = array('user_id'=>$c_id,'v_id'=>$v_id,'store_id'=>$store_id,'type'=>'manual_discount');
        if ($request->has('vu_id')) {
            $wherediscount['vu_id'] = $request->vu_id;
        }
        //$barcode = $request->barcode;
        if($request->has('all')){
            if($request->all == 1){
                $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
                $order_id = $order_id + 1;
                $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();
                foreach ($carts as $key => $cart) {
                    Cart::where('cart_id', $cart->cart_id)->delete();
                    DB::table('cart_details')->where('cart_id' , $cart->cart_id)->delete();
                    DB::table('cart_offers')->where('cart_id' , $cart->cart_id)->delete();
                }
               DB::table('voucher_applied')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('order_id', $order_id)->delete();
            
               CartDiscount::where($wherediscount)->delete();
            }

        }else{

            if($request->has('cart_id')){
                $cart_id = $request->cart_id;
                Cart::where('cart_id', $cart_id)->delete();
                DB::table('cart_details')->where('cart_id' , $cart_id)->delete();
                DB::table('cart_offers')->where('cart_id' , $cart_id)->delete();
                $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
                $order_id = $order_id + 1;
                $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();
                if($carts->isEmpty()){
                    DB::table('voucher_applied')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('order_id', $order_id)->delete();
                    CartDiscount::where($wherediscount)->delete();

                }

                $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id ];
                $this->process_each_item_in_cart($params);


            }


        }

        //$params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id ];
        //$this->process_each_item_in_cart($params);

        return response()->json(['status' => 'remove_product', 'message' => 'Item Removed successfully' ],200);
        
    }

    function is_decimal( $val )
    {
        return is_numeric( $val ) && floor( $val ) != $val;
    }

    public function cart_details(Request $request)
    {
            $v_id       = $request->v_id;
            $c_id       = $request->c_id;
            $store_id   = $request->store_id; 
            $trans_from = $request->trans_from;
            $user_id = $request->vu_id;
            $cart_data  = array();
            $product_data = [];
            $tax_total  = 0;
            $cart_qty_total = 0;
            $role = VendorRoleUserMapping::select('role_id')->where('user_id',$user_id)->first();
            $role_id  = $role->role_id;

            //$order_id = Order::where('user_id', $c_id)->where('status', 'success')->orWhere('status' ,'error')->count();
            $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
            $order_id = $order_id + 1;

            // Get Customer Data

            $customer_details = User::find($c_id);
            
            $carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status','process')
            //->orderBy('updated_at','desc')->get();
            ->orderBy('cart_id','desc')->get();
            $sub_total      = $carts->sum('subtotal');
            $item_discount  = $carts->sum('discount');
            $employee_discount = $carts->sum('employee_discount');
            $employee_id    = 0;
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

            /*Update  Manual Discount*/
            $whereMdiscount = array('user_id'=>$c_id,'v_id'=>$v_id,'store_id'=>$store_id,'type'=>'manual_discount');
            if ($request->has('vu_id')) {
             $whereMdiscount['vu_id'] = $request->vu_id;
            }
            $mDiscount  = CartDiscount::where($whereMdiscount)->orderBy('updated_at','desc')->first();

            if($mDiscount){
                $request->merge([
                'manual_discount_factor'=> $mDiscount->factor,
                'manual_discount_basis' => $mDiscount->basis,
                'remove_discount'       => 0,
                'return'                => 0
                ]);
                
                $offerconfig = new \App\Http\Controllers\OfferController;
                $offerconfig->manualDiscount($request);
            }
            /*Update Manual Discount*/
            
            $wherediscount = array('user_id'=>$c_id,'v_id'=>$v_id,'store_id'=>$store_id,'type'=>'manual_discount');
            if ($request->has('vu_id')) {
                $wherediscount['vu_id'] = $request->vu_id;
            }
            $check_manual_discount = CartDiscount::where($wherediscount)->orderBy('updated_at','desc')->first();

            $single_item_discount = 0;
            foreach ($carts as $key => $cart) {
                $single_item_discount = (float)$cart->discount + (float)$cart->employee_discount + (float)$cart->bill_buster_discount;
                $bill_buster_discount += (float)$cart->bill_buster_discount;
                // dd($bill_buster_discount);
                $where    = array('v_id'=>$v_id,'barcode'=>$cart->barcode);
                $Item     = VendorSkuDetails::where($where)->first();
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
                    if(isset($memoOffer[0]->name)){
                        $message = $memoOffer[0]->name;
                    }elseif(isset($memoOffer[0]->message)){
                        $message = $memoOffer[0]->message;
                    }
                    $bill_buster[] = [ 'amount' => $memoOffer[0]->discount, 'message' => $message ];
                    $bill_buster_offers[] = (object)[ 'name' => $message ];
                }
                
                

                /*$res = DB::table('cart_offers')->where('cart_id',$cart->cart_id)->first();
                if($res){
                    $offer_data = json_decode($res->offers, true);

                    foreach ($offer_data['pdata'] as $key => $value) {
                        $tax_details_data[$cart->item_id] = ['tax' =>  $value['tax'] , 'total' => $value['ex_price'] ];
                        
                    }
                    $available_offer =[];
                    foreach($offer_data['available_offer'] as $key => $value){

                        $available_offer[] =  ['message' => $value ];
                    }
                    $applied_offer = [];
                    $offer_data['available_offer'] = $available_offer;

                    foreach($offer_data['applied_offer'] as $key => $value){

                        $applied_offer[] =  ['message' => $value ];
                    }
                    $offer_data['applied_offer'] = $applied_offer;

                    //Counting the duplicate offers
                    $tempOffers = $offer_data['applied_offer'];
                    for($i=0; $i<count($offer_data['applied_offer']); $i++){
                        $apply_times = 1 ;
                        $apply_key = 0;
                        for($j=$i+1; $j<count($tempOffers); $j++){
                            
                            if( isset($offer_data['applied_offer'][$j]['message']) && $tempOffers[$i]['message'] == $offer_data['applied_offer'][$j]['message']){
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
                }*/

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
                $stock = $Item->currentStock->where('store_id' , $store_id)->sortByDesc('created_at')->first();
                if($stock){
                $currentStock = ((int)$stock->opening_qty + (int)$stock->int_qty ) - (int)$stock->out_qty;
                }else{
                    $currentStock = 0;
                }
            
                //Batches
                $batches = [];
                if($Item->item->has_batch ==1){//Batch Enabled
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
                $product_data['weight_flag']    = ($Item->Item->uom->selling->type == 'WEIGHT'? true:false);
                $product_data['quantity_change_flag'] = (strlen($cart->plu_barcode) == 13)?false:true;
                $product_data['p_name']         = utf8_encode(ucwords(strtolower($cart->item_name)));
                $product_data['offer']          = $product_details->offer;//(count(@$offer_data['applied_offer']) > 0)?'Yes':'No';//$product_details->offer;
                $product_data['offer_data']     = $product_details->offer_data;
                //$product_data['offer_data']     = $product_details->offer_data;
                //$product_data['qty'] = '';

                /*Price */

                $priceList  = $Item->vprice->where('v_id',$v_id)->where('variant_combi',$Item->variant_combi);    

                $config   =  $this->cartconfig;
                $price    =  $config->getprice($priceList, $cart->unit_mrp);
                $unit_mrp =  $price['unit_mrp']; 
                $r_price  =  $price['r_price'] ;
                $s_price  =  !empty($price['s_price'])?$price['s_price']:$price['unit_mrp'] ;
                $mrp_arrs = $price['mrp_arrs'];
                $multiple_mrp_flag = $price['multiple_mrp_flag'];

                $product_data['multiple_price_flag'] = $multiple_mrp_flag; //isset($offer_data['multiple_price_flag'])?$offer_data['multiple_price_flag']:false;


               $product_data['multiple_mrp'] = $mrp_arrs;  //isset($offer_data['multiple_mrp'])?$offer_data['multiple_mrp']:false;
            
                $tdata         = json_decode($cart->tdata);
                //$taxC    = 0;
                $ttax =0;
                if($check_manual_discount){
                    $dis_data = json_decode($check_manual_discount->dis_data,true);
                    foreach ($dis_data['cart_data'] as $cdata) {
                        if($cdata['item_id'] == $cart->item_id){
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
                if($Item->Item->tax_type == 'INC'){
                    $total_tax_inc   += $ttax;  //$tdata->tax;
                }
                if($Item->Item->tax_type == 'EXC'){
                 $total_tax_exc   +=     $ttax;  //$tdata->tax;
                }

                //format_number($offer_data['r_price']);
                $product_data['r_price'] = $product_details->r_price;

                
                //format_number($offer_data['s_price']);
                $product_data['s_price'] = $product_details->s_price;
                $product_data['unit_mrp'] = $product_details->unit_mrp;
                $product_data['discount'] = format_number($cart->discount + $cart->manual_discount + $cart->lpdiscount + $cart->coupon_discount + $cart->bill_buster_discount);

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
                $total_amount          += $cart->subtotal;
                $cart_data[] = array(
                        'cart_id'       => $cart->cart_id,
                        'product_data'  => $product_data,
                        'amount'        => $cart->total,
                        'qty'           => $cart->qty,
                        'tax_amount'    => format_number($tax_amount),
                        'delivery'      => $cart->delivery,
                        'salesman_id'   => $cart->salesman_id,
                        'salesman_name' => $salesman_name
                        // 'ptotal'        => $cart->amount * $cart->qty,
                );

               
                //$tax_total = $tax_total +  $tax_amount ;
                
                $qty[] = $cart->qty;
                //$merge = array_combine($rprice,$qty);

                
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

            $vouchers = DB::table('voucher_applied')->select('id','voucher_id','applied_amount')->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('order_id', $order_id)->get();
            $voucher_total = 0 ; 
            foreach ($vouchers as $key => $voucher) {
                $voucher_applied = DB::table('voucher_applied')->where('voucher_id', $voucher->voucher_id)->where('status', 'APPLIED')->get();
                $totalVoucher = DB::table('voucher')->where('id', $voucher->voucher_id)->first()->amount;
                $voucher_remain_amount = $totalVoucher - $voucher_applied->sum('applied_amount');
                array_push($voucher_array, ['name' => 'Voucher Credit', 'amount' => $voucher_remain_amount]);
                $voucher_total += $voucher_remain_amount;
                if ($roundoff_total >= $voucher_remain_amount) {
                    $voucher_applied_amount = $voucher_remain_amount;
                    $pay_by_voucher += $voucher_remain_amount;
                    $roundoff_total = $roundoff_total - $voucher_remain_amount;
                } else {
                    $voucher_applied_amount = $roundoff_total;
                    $pay_by_voucher += $roundoff_total;
                    $roundoff_total = 0;
                }
                DB::table('voucher_applied')->where('id', $voucher->id)->update(['status' => 'PROCESS' , 'applied_amount' => $voucher_applied_amount ]);
            }

            /*Voucher Check end*/
            
            // if($check_manual_discount){
            //     $dis_data       = json_decode($check_manual_discount->dis_data);
            //     $roundoff_total = $roundoff_total - $dis_data['discount_amt'];
            //     $taxDis         = $this->calculatePercentageAmt($cgst_amount,$dis_data['discount_amt']);
            // }

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
          $sb_total = ($total_amount -($total_tax_exc+$total_tax_inc)); 
            $bill_summary   = [];
            $bill_summary[] = [ 'name' => 'sub_total' , 'display_text' => 'Sub Total' , 'item_type'=>"",'value' => (string)format_number($sb_total),'sign'=>''];
            if($total_discount > 0.0){
                $bill_summary[] = [ 'name' => 'discount' , 'display_text' => 'Discount' ,'item_type'=>"", 'value' => (string)format_number($total_discount),'sign'=>'-' ];
            }
            if($voucher_total > 0){

            $bill_summary[] = [ 'name' => 'voucher' , 'display_text' => 'Voucher Total' ,'item_type'=>"" ,'value' => (string)format_number($voucher_total) ,'mop_flag' => '1','sign'=>'-'];
            }

            if($bill_buster_discount > 0){
                $bill_summary[] = [ 'name' => 'bill_buster' , 'display_text' => 'Bill Buster Discount' ,'item_type'=>"2", 'value' => (string)format_number($bill_buster_discount),'sign'=>'-' ];
            }

            if($check_manual_discount){
                $bill_summary[] = [ 'name' => 'manual_discount' , 'display_text' => 'Manual Discount' ,'item_type'=>"2", 'value' => (string)format_number($check_manual_discount->discount),'sign'=>'-' ];
            }
            if($total_tax_exc > 0){
                $bill_summary[] = [ 'name' => 'tax_total' , 'display_text' => 'Tax Total (Excluded)' , 'type' => 'EXCLUSIVE','item_type'=>"1", 'value' => (string)format_number($total_tax_exc),'sign'=>'' ];
            }
           if($total_tax_inc > 0){
                $bill_summary[] = [ 'name' => 'tax_total' , 'display_text' => 'Tax Total (Included)' , 'type' => 'INCLUSIVE','item_type'=>"1" ,'value' => (string)$total_tax_inc,'sign'=>'' ];
            }
            $bill_summary[] = [ 'name' => 'total' , 'display_text' => 'Total' ,'item_type'=>"", 'value' => (string)format_number($roundoff_total),'sign'=>''];

            


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
            'bill_summary'      => $bill_summary ],200);
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

        foreach ($cart_data as $value) {
            $cart_details_data = CartDetails::where('cart_id', $value['cart_id'])->get()->toArray();
            $save_order_details = array_except($value, ['cart_id']);
            $save_order_details = array_add($value, 't_order_id', $porder_id);
            $order_details = OrderDetails::create($save_order_details);
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
                         $params = array('v_id'=>$value['v_id'],'store_id'=>$value['store_id'],'barcode'=>$value['barcode'],'qty'=>$value['qty'],'invoice_id'=>$invoice->invoice_id,'order_id'=>$invoice->ref_order_id,'transaction_type'=>'SALE');
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
        $return      = array();
        $invoiceExist= Invoice::where('invoice_id',$invoice_id)->count();
        if($invoiceExist > 0){
            $emailParams = array('v_id'=>$v_id,'store_id'=>$store_id,'invoice_id'=>$invoice->invoice_id,'user_id'=>$user_id,'email_id'=>$email_id);
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
        $invoice_id  = $parms['invoice_id'];
        $email_id    = $parms['email_id'];
        $date        = date('Y-m-d');
        $time        = date('h:i:s');
        $time        = strtotime($time); 
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
        $stores = $order->store;

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
        

        //$cart_qty_total = $carts->sum('qty');
        $sub_total      = $carts->sum('subtotal');
        $discount       = $carts->sum('discount');
        $discount_total = $discount+$carts->sum('manual_discount')+$carts->sum('lpdiscount')+$carts->sum('coupon_discount');
        $discount_total = round($discount_total,2);
        $employee_discount = $carts->sum('employee_discount');
        $manual_discount = $carts->sum('manual_discount');
        $total          = $carts->sum('total');
        $tax_total      = $carts->sum('tax');
        //$total     = $sub_total+$tax_total;
        $bill_buster_discount = 0;
        $tax_details          = [];

        $data = [];
        //For Return operation only
        $return_items     = [];
        $return_item_ids  = [];
        if($order->transaction_type == 'sales'){
            //echo $order_id     = $order->invoice_id;
            $return_order = DB::table('orders as o')
                            ->join('order_details as od', 'od.t_order_id', 'o.od_id')
                            ->where('o.ref_order_id' , $order->invoice_id)
                            ->where('o.transaction_type','return')
                            ->selectRaw('sum(od.`qty`) as sum, od.*')
                            ->groupBy('item_id')
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
            $where    = array('v_id'=>$v_id,'barcode'=>$cart->barcode);
            $Item     = VendorSkuDetails::where($where)->first();
            $offerData= isset($offer_data['pdata'])?$offer_data['pdata']:$offer_data;

            foreach ($offerData as $key => $value) {
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
            if(in_array($cart->item_id, $return_item_ids)){
                $request = $return_order->where('item_id', $cart->item_id);   

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
            $product_data['unit_mrp']        = $product_details->unit_mrp;
            $product_data['uom']             = $product_details->uom;
            $product_data['discount']        = format_number($cart->discount + $cart->manual_discount + $cart->lpdiscount + $cart->coupon_discount + $cart->bill_buster_discount);
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
            if (isset($return_items[$cart->item_id]) ) {
                $return_product_qty = $cart->qty - $return_items[$cart->item_id];
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
                    'discount'      => format_number($cart->discount + $cart->manual_discount + $cart->lpdiscount + $cart->coupon_discount + $cart->bill_buster_discount)
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
            
            if($Item->Item->tax_type == 'INC'){
                $total_tax_inc   += $tdata->tax;
            }
            if($Item->Item->tax_type == 'EXC'){
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
        if($o_id->address_id > 0){
            $address = $o_id->user->address;
        }

        $sb_total = ($sub_total-($total_tax_exc+$total_tax_inc));
        $bill_summary=[];

        $bill_summary[] = [ 'name' => 'sub_total' , 'display_text' => 'Sub Total' , 'value' => (string)format_number($sb_total),'sign'=>'' ];
        $bill_summary[] = [ 'name' => 'discount' , 'display_text' => 'Discount' , 'value' => (string)format_number($discount) ];
        if($manual_discount > 0 ){
            $bill_summary[] = [ 'name' => 'manual_discount' , 'display_text' => 'Manual Discount' , 'value' => format_number($manual_discount),'sign'=>'-' ];
        }

        //$bill_summary[] = [ 'name' => 'bill_buster_discount' , 'display_text' => 'Bill Discount' , 'value' => (string)format_number($bill_buster_discount) ];

        $bill_summary[] = [ 'name' => 'bill_buster_discount' , 'display_text' => 'Bill Discount' , 'value' => (string)format_number($bill_buster_discount),'sign'=>'-' ];
       
       
        if($total_tax_exc > 0){
            $bill_summary[] = [ 'name' => 'tax_total' , 'display_text' => 'Tax Total (Excluded)' , 'value' => (string)format_number($total_tax_exc),'sign'=>'' ];
        }
        if($total_tax_inc > 0){
            $bill_summary[] = [ 'name' => 'tax_total' , 'display_text' => 'Tax Total (Included)' , 'value' => (string)format_number($total_tax_inc),'sign'=>'' ];
        }

        $bill_summary[] = [ 'name' => 'total' , 'display_text' => 'Total' , 'value' => (string)round($total),'sign'=>'' ];

        foreach ($payments->groupBy('method') as $key => $payment) 
        {
            if ($key == 'voucher_credit') {
                $bill_summary[] = [ 'name' => 'payment_'.$key , 'display_text' => 'Store Credit' , 'value' => (string)format_number($payment->sum('amount')) ,'mop_flag' => '1'];
            } else {
                $bill_summary[] = [ 'name' => 'payment_'.$key , 'display_text' => ucfirst($key) , 'value' => (string)format_number($payment->sum('amount')) ,'mop_flag' => '1' ];
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
                        'delivered'           => $stores->delivery , 
                        'address'             => $address,
                        'user_api_token'      => $user_api_token,
                        'bill_summary'        => $bill_summary,
                        'bill_remark'         => $order->remark,
                        'customer_name'       => $customer->first_name.' '.$customer->last_name,
                        'customer_group_code' => $group_code,
                        'print_url'           => $print_url,
                        'c_id'                => $c_id ],200);

 
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

        $vendorC  = new VendorController;
        $crparams = array('v_id'=>$v_id,'store_id'=>$store_id,'vu_id'=>1);
        $currency = $vendorC->getCurrencyDetail($crparams);
        $currencyR = explode(' ', $currency['name']);
        if($currencyR > 1){
            $len = count($currencyR);
            $currencyName = $currencyR[$len-1];
        }else{
            $currencyName  =  $currencyR ;
        }


        if($v_id == 26){
            $this->callCustomPrint($request);
        }
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
                        'discount'      => $value->discount+$value->manual_discount + $value->bill_buster_discount+$value->lpdiscount+$value->coupon_discount,
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
            if($v_id == 7){
                $invoice_title     = 'Tax invoice';
            }if($v_id == 18){
                $invoice_title     = 'Tax Invoice Cum Bill of Supply';
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
        if($v_id != 28){
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
            $printInvioce->addLineLeft(' Hall No : '.$order_details->user->hall_no , 22);
        }
        if($order_details->user->seat_no){
            $printInvioce->addLineLeft(' Table No : '.$order_details->user->seat_no , 22);
        }

        $printInvioce->addDivider('-', 20);



        $printInvioce->tableStructure(['#', 'Item', 'Rate','Qty','Amount'], [3, 11, 7,5,8], 22);
        if($taxable_amount > 0){
             if($v_id == 28){
                $printInvioce->tableStructure(['Barcode','', 'Disc'], [18,10, 6], 22);
             }
            else{
                $printInvioce->tableStructure(['Barcode','hsn', 'Disc'], [18,10, 6], 22);    
            }
            
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
                     [3, 8, 6,5,5,7], 22);
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
        $printInvioce->addLineLeft(ucfirst($currencyName).': '.ucfirst(numberTowords(round($order_details->total))).' Only' , 22);
        
        $printInvioce->addDivider('-', 20);
        if($customer_paid > 0){
        $printInvioce->addLineLeft('  Customer Paid: '.format_number($customer_paid), 22, true);
        $printInvioce->addLineLeft('  Balance Refund: '.format_number($balance_refund), 22, true);
    }
        $printInvioce->addDivider('-', 20);
        /*Tax Start */
        if($taxable_amount > 0){
            
            $printInvioce->leftRightStructure('Tax Summary','', 22);
            $printInvioce->addDivider('-', 20);
           
            if(!empty($detatch_gst)) {

                if($v_id == 28){

                    $printInvioce->tableStructure(['Desc', 'Taxable', 'Tax'], [10,12,12], 22);
                    $printInvioce->addDivider('-', 20);
                    foreach ($detatch_gst as $index => $gst) {
                        $printInvioce->tableStructure([str_replace('GST', 'VAT', $gst->name),
                            ' '.$gst->taxable,
                            $gst->cgst+$gst->sgst],
                            [10,12,12], 22);
                    }
                    $printInvioce->addDivider('-', 20);

                    $printInvioce->tableStructure(['Total',
                    format_number($taxable_amount),
                    format_number($total_csgt+$total_sgst)  
                    ], [10,12,12], 22, true);

                }else{
                
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
              }
                
                $printInvioce->addDivider('-', 20);
            }
        }
        $total_discount = (float)$order_details->discount+(float)$order_details->manual_discount+(float)$order_details->bill_buster_discount+(float)$order_details->lpdiscount+(float)$order_details->coupon_discount;
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
        $printInvioce->leftRightStructure('Net Payable', format_number($net_payable), 22);
        $printInvioce->addDivider('-', 20);

        if(!$v_id = 24){
        $printInvioce->addLineLeft(' Terms and Conditions', 22, true);
        $printInvioce->addDivider('-', 20);
        foreach ($terms_conditions as $term) {
            $printInvioce->addLineLeft($term, 20);
        }
    }
    if($v_id == 24){
        if($store->cin){
            $printInvioce->addLine('CIN: '.$store->cin, 22);            
        }
        $printInvioce->addLine($store->address1, 22);
        if($store->address2){
        $printInvioce->addLine($store->address2, 22);
        }
        $printInvioce->addLine($store->location.'-'.$store->pincode.', '.$store->state, 22);
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

        $response = ['status' => 'success', 
            'print_data' =>($printInvioce->getFinalResult())];

        if($request->has('response_format') && $request->response_format == 'ARRAY'){
            return $response;
        }
        return response()->json($response, 200);
    }

    public function callCustomPrint($request){


        $v_id       = $request->v_id;
        $store_id   = $request->store_id; 
        $c_id       = $request->c_id;
        $order_id   = $request->order_id;
        $product_data= [];
        $gst_list    = [];
        $final_gst   = [];
        $detatch_gst = [];
        $rounded     = 0;

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

        $terms_conditions =  array('1.Goods once sold will not be taken back.','2. All disputes will be settled in Jaipur court.','3. E. & O.E.','4. This is computer generated invoice and does not require any stamp or signature');  
        if($v_id == 63){
          $terms_conditions =  array('1.Products can be exchanged within 7 days from date of purchase.',
            '2.Original receipt/invoice copy must be carried.','3.In the case of exchange – the product must be in it’s original and unused condition, along with all the original price tags, packing/boxes and barcodes received.','4.We will only be able to exchange products. Afraid there will not be refund in any form.','5.We do not offer any kind of credit note at the store.','6.There will be no exchange on discounted/sale products.','7.In the case o damage or defect – the store teams must be notified within 24 hours of purchase');  
        }

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
        $printInvioce->addLineLeft(' Date : '.date('d-M-Y', strtotime($order_details->created_at))." at ".date('h:i:s A', strtotime($order_details->created_at)), 22);
        if($v_id != 53){
            $printInvioce->addLineLeft(' Cashier : '.@$order_details->vuser->first_name.' '.@$order_details->vuser->last_name , 22);
            $printInvioce->addLineLeft(' Customer Mobile : '.@$order_details->user->mobile , 22);
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
        $printInvioce->leftRightStructure('Total Sale', $total_amount, 22);
       
       
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

        $user = User::select('c_id', 'mobile')->where('mobile',$cust_mobile_no)->first();
        if($user){

            $today_date = date('Y-m-d');
            $order = Order::where('user_id', $user->c_id)->where('status','success')->orderBy('od_id' , 'desc')->where('date', $today_date)->where('trans_from', $trans_from)->first();

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
                return response()->json(['status'=> 'fail' , 'message' => 'Unable to found any order which has been placed today'] , 200);
            }
        }else{
            return response()->json(['status'=> 'fail' , 'message' => 'Customer not exists'] , 200);
        }

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
    

    }//End of get_html_structure
    
    public function order_receipt($c_id,$v_id , $store_id, $order_id){


        $request = new \Illuminate\Http\Request();
        $request->merge([
            'v_id' => $v_id,
            'c_id' => $c_id,
            'store_id' => $store_id,
            'order_id' => $order_id
        ]);
        $htmlData = $this->get_print_receipt($request);
        $html = $htmlData->getContent();
        $html_obj_data = json_decode($html);
        if($html_obj_data->status == 'success')
        {
            return $this->get_html_structure($html_obj_data->print_data);
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
            
            $item_master = VendorSkuDetails::where(['barcode'=> $cart->barcode,'v_id'=>$v_id])->first();
            if(!$item_master){
                $item_master = VendorSkuDetails::where(['sku'=> $cart->barcode,'v_id'=>$v_id])->first();
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


    public function get_carry_bags(Request $request)
    {
        $v_id           = $request->v_id;
        $store_id       = $request->store_id; 
        $c_id           = $request->c_id;
        $order_id       = Order::where('user_id', Auth::user()->c_id)->where('status', 'success')->count();
        $order_id       = $order_id + 1;
        $store_db_name  = get_store_db_name(['store_id' => $store_id]);
        $carry_bags     = Carry::select('barcode')->where('v_id', $v_id)->where('store_id', $store_id)->where('status','1')->where('deleted_status', '0')->get();
        $carr_bag_arr   = $carry_bags->pluck('barcode')->all();  
        $carry_bags     = VendorSkuDetails::whereIn('vendor_sku_details.barcode', $carr_bag_arr)
                            ->where('vendor_sku_details.v_id', $v_id)
                            ->join('stock_current_status','stock_current_status.item_id','vendor_sku_details.item_id')
                            ->groupBy('vendor_sku_details.barcode')
                            ->get();
         //dd($carry_bags);

        $carts          = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();
        $data = array();
        if(count($carry_bags)> 0){

        foreach ($carry_bags as $key => $value) {

                ## Price Calculation Start
                $priceList  = $value->vprice->where('v_id',$v_id)->where('variant_combi',$value->variant_combi);    
                    // $mrplist    = array();
                    // foreach($priceList as $mp){
                    // $mrplist[] = array('mrp'=>$mp->priceDetail->mrp,'rsp'=>$mp->priceDetail->rsp,'s_price'=>$mp->priceDetail->special_price);
                    // }
                    // $mrplist  =  collect($mrplist);
                    // $unit_mrp =  $mrplist->max('mrp'); 
                    // $r_price  =  $mrplist->max('rsp')  ;

                $config   =  $this->cartconfig;
                $price    =  $config->getprice($priceList);
                $unit_mrp =  $price['unit_mrp']; 
                $r_price  =  $price['r_price'] ;
                $s_price  =  !empty($price['s_price'])?$price['s_price']:$price['unit_mrp'] ;
                $mrp_arrs = $price['mrp_arrs'];
                $multiple_mrp_flag = $price['multiple_mrp_flag'];


                ## Price Calculation End


            $BAG_ID  =  $value->barcode;
            $NAME    =  $value->Item->name;
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
        //return response()->json(['status' => 'get_carry_bags_by_store', 'data' => $data ],200);
        return ['status' => 'get_carry_bags_by_store', 'data' => $data ];
    }

    public function save_carry_bags(Request $request)
    {
        //echo 'inside this';exit;
        $cart_barcode = [];
        $v_id = $request->v_id;
        $store_id = $request->store_id; 
        $c_id = $request->c_id; 
        $trans_from = $request->trans_from;
        //$order_id = $request->order_id; 
        $bags = $request->bags; 
        $bags = json_decode($bags, true);
        //dd($bags);
        $store_db_name = get_store_db_name(['store_id' => $store_id]);

        $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

        $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

        $db_structure = DB::table('vendor')->select('db_structure')->where('id',$v_id)->first()->db_structure;

        foreach ($bags as $key => $value) {
            $barcode = $barcodefrom =  $value[0];
            $qty = $value[1];

            $exists = $carts->where('barcode', $value[0])->first();
            $item   = VendorSkuDetails::where(['barcode'=>$value[0],'v_id'=>$v_id ])->first();
           
            ### Price Calculation Start
            $priceList = $item->vprice->where('v_id',$v_id)->where('variant_combi',$item->variant_combi);
            //dd($priceList);
            $config = new CartconfigController;
            $price = $config->getprice($priceList);

            // $unit_mrp          =  $price['unit_mrp']; 
            // $r_price           =  $price['r_price'] * $qty;
            // $s_price           =  !empty($price['s_price'])?$price['s_price']:$price['unit_mrp'] * $qty;
            $mrp_arrs          = $price['mrp_arrs'];
            $multiple_mrp_flag = $price['multiple_mrp_flag'];
            ### Price Calculation end

            $promoC = new PromotionController;;

            $item->ICODE = $item->barcode;
            $item->BARCODE = $item->barcode;

            $category = $item->category()->toArray();
            for($counter = 0; $counter < 5; $counter++){
                $code = 'CCODE'. ($counter +1);
                if(isset($category[$counter])){
                    $item->$code = $category[$counter]->id;
                }else{
                    $item->$code = '';
                }
            }
            
            $it = $item->Item;
            $item->DEPARTMENT_CODE = $it->department_id;
            
            $item->DESC1 = $it->brand_id;
            $item->DESC2 = '';
            $item->DESC3 = '';
            $item->DESC4 = '';
            $item->DESC5 = '';
            $item->DESC6 = '';
            

            $item->LISTED_MRP = $price['unit_mrp'] ;
            $item->MRP =  !empty($price['s_price'])?$price['s_price']:$price['unit_mrp'];
            //dd($item);
            $params = ['v_id' => $v_id, 'trans_from' => $trans_from, 'barcode' => $barcodefrom, 'qty' =>  $qty, 'mapping_store_id' => $store_id , 'item' => $item, 'carts' => $carts , 'store_db_name' => $store_db_name, 'is_cart' => 0, 'is_update' => 0, 'db_structure' => $db_structure  ];
            
            $offer_data = $promoC->index($params);

            $data = $offer_data;
            //dd($data);

            if($exists) {
                
                if($value[1] < 1 ){
                    $request->request->add(['cart_id' => $exists->cart_id]);
                    $this->remove_product($request);
                }else{
                    unset($cart_barcode[ array_search($value[0], $cart_barcode) ] );
                    $request->request->add(['qty' => $value[1],
                        'unit_mrp'      => $offer_data['unit_mrp'],
                        'unit_rsp'      => $offer_data['unit_rsp'],
                        'r_price'       => $offer_data['r_price'],
                        's_price'       => $offer_data['s_price'],
                        'discount'      => $offer_data['discount'],
                        'pdata'         => $offer_data['pdata'],
                        'target_offer'  => $offer_data['target_offer'],
                        'get_data_of'   => 'CART_DETAILS',
                        'ogbarcode'     => $barcodefrom,
                        'barcode'       => $barcodefrom,
                        'data'          => $data,
                        'multiple_mrp_flag'=>$multiple_mrp_flag,
                        'mrp_arrs'      =>$mrp_arrs,
                        'is_catalog'    =>'0',
                    ]);
                    $this->product_qty_update($request);
                }
                $status = '1';
            } else {
                if($value[1] > 0 ){
                    //echo '1-';
                    //dd($value);
                    $request->request->add(['qty' => $value[1],
                        'unit_mrp'      => $offer_data['unit_mrp'],
                        'unit_rsp'      => $offer_data['unit_rsp'],
                        'r_price'       => $offer_data['r_price'],
                        's_price'       => $offer_data['s_price'],
                        'discount'      => $offer_data['discount'],
                        'pdata'         => $offer_data['pdata'],
                        'target_offer'  => $offer_data['target_offer'],
                        //'get_data_of'   => 'CART_DETAILS',
                        'ogbarcode'     => $barcodefrom,
                        'barcode'       => $barcodefrom,
                        'data'          => $data,
                        'multiple_mrp_flag'=>$multiple_mrp_flag,
                        'mrp_arrs'      =>$mrp_arrs,
                        'is_catalog'    =>'0',
                    ]);
                    $this->add_to_cart($request);
                }
                $status = '2';
            }
        }


        if($status == 1) {
            return response()->json(['status' => 'add_carry_bags', 'message' => 'Carry Bags Added'],200);
        } else {
            return response()->json(['status' => 'add_carry_bags', 'message' => 'Carry Bags Updated'],200);
        }
        //print_r($
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

 
}
