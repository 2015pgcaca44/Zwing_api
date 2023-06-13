<?php

namespace App\Http\Controllers\CloudPos;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VendorSettingController;
use App\Http\Interfaces\PromotionInterface;
use App\Model\Items\VendorSkuDetails;
use App\Model\Items\VendorSkuDetailBarcode;
use App\Model\Items\VendorItem;
use Auth;
use DB;
use Carbon\Carbon;
use App\Store;
use App\Model\Items\VendorSkuAssortmentMapping;

/**
 * Promotion calculation of an item it is use to resolve GINESYS and ZWING CLOUDSPOS Promotions
 * 
 */
class PromotionController extends Controller implements PromotionInterface {

    /**
     * 1) Item is not linked with store
     * 2) Division, section are getting from name , it should be get from id
     */
    private $store_db_name;

    /**
     * $table_prefix is empty for GINESYS and pro_ for CLOUDPOS 
     * @var string
     */
    private $table_prefix ='pro_';

    /**
     * $v_id is Null for GINESYS and id for CLOUDPOS
     * @var string
     */
    private $v_id = null;


    /**
     * $v_id is Null for GINESYS and id for CLOUDPOS
     * @var string
     */
    private $db_structure = null;

    /**
     * $split_by_qty is true if you want calculate complex promotions
     * @var Boolean
     */
    private $split_by_qty = true;

    public function __construct()
    {
        //$this->store_db_name = env('DB_DATABASE');
         $this->store_db_name = DB::connection()->getDatabaseName();
    }


    public function setter($params){

        $this->v_id = $params['v_id'];
        if(isset($params['store_db_name']) ) {
            $this->store_db_name = $params['store_db_name'];
        } else if(isset($params['store_id']) ) {
            $this->store_db_name = get_store_db_name( [ 'store_id' => $params['store_id'] ] );
        }

        $orgnization = DB::table('vendor')->select('db_structure')->where('id', $params['v_id'])->first();
        $db_structure = $orgnization->db_structure;
        $this->db_structure = $db_structure;

        // if(isset($params['db_structure']) && $params['db_structure'] == 2 ){
        //     $this->store_db_name = env('DB_DATABASE');
        //     $this->table_prefix = 'pro_';
        // }else{
            if($db_structure == 2){
               // $this->store_db_name = env('DB_DATABASE');
                $this->store_db_name = DB::connection()->getDatabaseName();
                $this->table_prefix = 'pro_';
            }
        // }

    }

    public function index($params)
    {
         
        $this->setter($params);
        $final_data = [];
        $tdata=null;

        if (array_key_exists('is_coupon', $params)) {
            $finalCouponItemList = [];
            foreach ($params['assortment_list'] as $assrt) {
                $item = $params['item'] = $this->getItemOfferDetails($params);
                $assortment = (object)['ASSORTMENT_CODE' => $assrt->ASSRT_CODE];
                if (!empty($this->promotionDerivation($assortment, $item))) {
                    $finalCouponItemList[] = [ 'ASSORTMENT_CODE' => $this->promotionDerivation($assortment, $params['item'])[0]];
                }
            }
            if (count($finalCouponItemList) > 0) {
                return true;
            } else {
                return false;
            }
        }

        $item   = $params['item'];
        //dd($item);
        $v_id       = $params['v_id'];
        $trans_from = $params['trans_from'];
        $id         = $params['sku_code'];
        $barcode    = $params['barcode'];
        $batch_id   = !empty($params['batch_id'])?$params['batch_id']:0;
        $serial_id  = !empty($params['serial_id'])?$params['serial_id']:0;
        $change_mrp = !empty($params['change_mrp'])?$params['change_mrp']:'';

        //isset($params['batch_id'])?$params['batch_id']:0;
        
        $qty        = $params['qty'];
        $mapping_store_id = $params['mapping_store_id'];
        $carts            = $params['carts'];
        $is_offer         = 'No';
        $total_baisc_value= $total_discount = $total_gross = $total_qty = 0;


        $available_offer  = [];
        $applied_offer    = [];

        //echo $change_mrp;
        //dd($carts);

          //dd($item->toArray());
        //dd($carts);
        $item = $params['item'] = $this->getItemOfferDetails($params);
        if ($params['is_cart'] == 0) {

            $cart_items = collect($carts)->transform(function ($cartitem, $key) use($item, &$qty,&$batch_id,&$serial_id,&$change_mrp, &$barcode) {

                /*if(empty($change_mrp)){
                    $change_mrp = $cartitem->unit_mrp;
                    $change_rsp = $cartitem->unit_csp;
                }else{
                    $change_mrp = $change_mrp;
                    $change_rsp = $change_mrp;
                }*/

                $ilm_discount = 0;
                


                // dd($item->ICODE);
                if($cartitem->sku_code == $item->sku_code && $cartitem->batch_id == $batch_id && $cartitem->serial_id == $serial_id && $cartitem->unit_mrp == $change_mrp && $cartitem->barcode == $barcode) {
                //If item is exists then incrementing the current carts
                    if($cartitem->weight_flag == 1) {
                        $qty = $qty;
                    } else {
                        // $qty = $cartitem->qty + 1;
                        $qty = $qty;
                    }
                    return ['barcode' => $cartitem->barcode ,'sku_code' => $cartitem->sku_code, 'item_id' => $cartitem->item_id ,'batch_id'=>$cartitem->batch_id, 'serial_id'=>$cartitem->serial_id ,'qty' => $qty , 'unit_mrp' => $cartitem->unit_mrp, 'unit_rsp' => $cartitem->unit_csp, 'exclude_discount' => $ilm_discount , 'division_id' => $cartitem->division_id, 'section_id' => $cartitem->group_id, 'department_id' => $cartitem->department_id, 'article_id' => $cartitem->subclass_id, 'weight_flag' => $cartitem->weight_flag  ];
                } else {
                   return ['barcode' => $cartitem->barcode,'sku_code' => $cartitem->sku_code, 'item_id' => $cartitem->item_id ,'batch_id'=>$cartitem->batch_id, 'serial_id'=>$cartitem->serial_id , 'qty' => $cartitem->qty , 'unit_mrp' => $cartitem->unit_mrp, 'unit_rsp' => $cartitem->unit_csp, 'exclude_discount' => $ilm_discount, 'division_id' => $cartitem->division_id, 'section_id' => $cartitem->group_id, 'department_id' => $cartitem->department_id, 'article_id' => $cartitem->subclass_id, 'weight_flag' => $cartitem->weight_flag]; 
                }
            });



            if ($qty == 1) {
                $pqty = 1;
            } else {
                $pqty = $qty;
            }
            $item_val = $cart_items->where('sku_code',  $item->sku_code)->where('barcode', $barcode);
            if($batch_id > 0){
              $item_val = $item_val->where('batch_id',$batch_id);
            }
            if($serial_id > 0){
              $item_val = $item_val->where('serial_id',$serial_id);
            }
            if(!empty($change_mrp) && $change_mrp > 0){
               $item_val  = $item_val->where('unit_mrp',$change_mrp); 
               $item_mrp  = $change_mrp;
               $item_rsp  = $change_mrp;
            }else{
                $item_mrp  = $item->LISTED_MRP;
                $item_rsp  = $item->MRP;
            }
            $item_val = $item_val->first();

            if(!$item_val) {
                $weight_flag = 0;
                if($item->Item->uom->selling->type == 'WEIGHT') {
                    $weight_flag = 1;
                } else {
                    $weight_flag = 0;
                }
                $cart_items->push(['barcode' => $item->barcode, 'sku_code' => $item->sku_code, 'batch_id'=>$batch_id,'serial_id'=>$serial_id ,'item_id' =>  $item->barcode , 'qty' => $pqty , 'unit_mrp' => $item_mrp , 'unit_rsp' => $item_rsp, 'division_id' => $item->DIVISION_CODE, 'section_id' => $item->SECTION_CODE, 'department_id' => $item->DEPARTMENT_CODE, 'article_id' => $item->ARTICLE_CODE, 'weight_flag' => $weight_flag ]);
            }


        } elseif ($params['is_cart'] == 1) {

            $update = $params['is_update'];
            $cart_items = collect($carts)->transform(function ($cartitem, $key) use($update, $qty, $id,$batch_id,$serial_id,$change_mrp , $barcode) {
                 //dd($cartitem);
                /* if(empty($change_mrp)){
                    $change_mrp = $cartitem->unit_mrp;
                    $change_rsp = $cartitem->unit_csp;
                }else{
                    $change_mrp = $change_mrp;
                    $change_rsp = $change_mrp;
                }*/

                if ($update == 1 && $cartitem->sku_code == $id && $cartitem->batch_id == $batch_id && $cartitem->serial_id == $serial_id && $cartitem->unit_mrp == $change_mrp && $cartitem->barcode == $barcode) {
                    $qty = $qty;
                } else  {
                    $qty = $cartitem->qty;
                }

                $ilm_discount = 0;
                // $ilmd = json_decode($cartitem->item_level_manual_discount);
                // if(!empty($ilmd->discount) ){
                //     if($ilmd->discount > 0){
                //         $ilm_discount = $ilmd->discount;
                //     }
                // }

                //echo $qty;die;
                return ['barcode' => $cartitem->barcode,'sku_code' => $cartitem->sku_code,'batch_id'=>$cartitem->batch_id,'serial_id'=>$cartitem->serial_id ,'item_id' => $cartitem->item_id , 'qty' => $qty, 'unit_mrp' => $cartitem->unit_mrp, 'unit_rsp' => $cartitem->unit_csp, 'exclude_discount' => $ilm_discount ,'division_id' => $cartitem->division_id, 'section_id' => $cartitem->group_id, 'department_id' => $cartitem->department_id, 'article_id' => $cartitem->subclass_id, 'weight_flag' => $cartitem->weight_flag ];
            });
            // dd($cart_items);
            // $item_val = $cart_items->where('item_id',  $item->ICODE)->first();
            // if(!$item_val){
            //     $cart_items->push(['barcode' => $item->BARCODE, 'item_id' =>  $item->ICODE , 'qty' => 1 , 'unit_mrp' => $item->LISTED_MRP , 'unit_rsp' => $item->MRP, 'division_id' => $item->DIVISION_CODE, 'section_id' => $item->SECTION_CODE, 'department_id' => $item->DEPARTMENT_CODE, 'article_id' => $item->ARTICLE_CODE ]);
            // }
        }
        $params['cart_items'] = $cart_items;
        $getCount = 0;
        // dd($cart_items);
        if($params['promo_cal']){
            // dd('inside promo');
            if(isset($params['all_store_promo']) && $params['all_store_promo'] !=''){
                $params['all_store_promo'];
            }else{
                $params['all_store_promo'] = $this->getAllPromotions($params);


            }
            $params['promotions'] = $this->filterAllPromotions($params);



            $allPromotions = $this->calculatingAllPromotions($params);

            //echo 'hh';
            // dd($allPromotions);
        }else{

            $allPromotions['available_offers'] = [];
            $allPromotions['target_offer'] = [];
            $allPromotions['offer'] = [];
        }

        if( $this->v_id == 24 || $this->v_id == 11 ){
            $assort = VendorSkuAssortmentMapping::select('assortment_code')->where('v_id', $this->v_id)->where('sku_code', $item->sku_code)->get();
            if(!$assort->isEmpty()){

                $assort_code = $assort->pluck('assortment_code')->all();
                $getCount = DB::table($this->store_db_name.'.'.$this->table_prefix.'promo_slab')->whereIn('GET_ASSORTMENT_CODE', $assort_code)->count();

            }
        }

        foreach( $allPromotions['available_offers'] as $key => $value ) {
            $available_offer[] = ["message" => $value ];
        }
        $target_offer = $allPromotions['target_offer'];
        $allPromotions = $params['promo'] = $allPromotions['offer'];

        $params['promo'] = $allPromotions;
        //dd($allPromotions);

        $charge_details = [];
        $extra_charge = 0;
        $net_amount = $total_gross;

        if (empty($allPromotions)) {

            //dd($cart_items);
            $cart_items = collect($cart_items)->where('sku_code', $params['sku_code'])->where('barcode', $params['barcode'])->where('batch_id',$params['batch_id'])->where('serial_id',$serial_id);
            if(!empty($change_mrp) && $change_mrp > 0){
                $cart_items  = $cart_items->where('unit_mrp',$change_mrp);
            }



            $pdata = [];
            //dd($cart_items);
            foreach ($cart_items as $cart) {
                $pdata[] = [ 'item_id' => $cart['item_id'], 'barcode' => $cart['barcode'],'sku_code' => $cart['sku_code'], 'batch_id'=>$cart['batch_id'],'serial_id'=>$cart['serial_id'],'mrp' => $cart['unit_mrp'] , 'unit_mrp' => $cart['unit_mrp'], 'unit_rsp' => $cart['unit_rsp'], 'discount' => 0, 'sub_total' => $cart['unit_rsp'], 'total' => $cart['unit_rsp'], 'message' => '', 'discount_price_basis' => '', 'qty' => $cart['qty'], 'promo_code'=> '', 'no' => '', 'start_date' => '', 'end_date' => ''];
                // $total_baisc_value += (float)$cart['unit_mrp'] * (float)$cart['qty'];
                $total_baisc_value += (float)$cart['unit_rsp'] * (float)$cart['qty'];
                $total_discount += 0;
                $total_gross += $total_baisc_value;
                $total_qty += (float)$cart['qty'];
            }
            $allPromotions = $pdata;
            $net_amount = $total_gross;
           //echo $total_qty;die;
        } else {

            // $clarr = collect($allPromotions);


            // $allPromotions  = $clarr->where('batch_id',$batch_id)->where('serial_id',$serial_id);
            // if(!empty($change_mrp) && $change_mrp > 0){
            //     $allPromotions  = $allPromotions->where('unit_mrp',$change_mrp);
            // }
            
           // dd($allPromotions);

            
            $extra_charge_status = '0';
            $extra_charge_type = '';
            $extra_charge_group_id = 0;
            foreach ($allPromotions as $val) {
                if($val['sku_code'] == $params['sku_code']  && $val['item_id'] == $barcode  && $val['batch_id'] == $batch_id && $val['serial_id'] == $serial_id && $val['unit_mrp'] == $change_mrp ){
                    if (empty($val['discount'])) {
                        //$available_offer[]['message'] = $val['message'];
                    } else {
                        $applied_offer[]['message'] = $val['message'];
                    }
                    $total_baisc_value += $val['sub_total'];
                    $total_discount += $val['discount'];
                    $total_gross += $val['total'];
                    $total_qty += $val['qty'];

                    if(!empty($val['extra_charge_status']) ){
                        $extra_charge_status =  $val['extra_charge_status'];
                        $extra_charge_type =  $val['extra_charge_type'];
                        $extra_charge_group_id =  $val['extra_charge_group_id'];
                    }

                }
            }

            $net_amount = $total_gross;

            //Calculating Extra Charge
            
            $charge_name = '';
            $charge_rate = '';
            $charge_group_id = null;
            if($extra_charge_status == '1' && $item->tax_type == 'INC'){

                if($extra_charge_type == 'CUSTOM'){

                    $charge = new \App\Http\Controllers\ChargeController;
                    $charges = $charge->calculate(['amount' => $total_gross ,'charge_group_id' => $extra_charge_group_id] );
                    $charge_name = $charges['name'];
                    $charge_rate = $charges['rate'];
                    $charge_group_id = $charges['id'];
                    $extra_charge = $charges['charge'];

                }else{//if EXTRA CHARGE IS DEFAULT
                    $cart = new \App\Http\Controllers\CloudPos\CartController;
                    $taxData = $cart->taxCal(['v_id' => $v_id, 'qty' => $total_qty , 's_price' => $total_gross, 'store_id' => '' , 'barcode' => $item->BARCODE, 'hsn_code' => $item->hsn_code]);
                    $extra_charge = $taxData['tax'];

                }

                $total_gross += $extra_charge;
                // $net_amount = $total_gross +  $extra_charge;
                $charge_details = ['sku_code' => $item->sku_code ,'barcode' => $item->BARCODE , 'charge_type' => $extra_charge_type ,'charge_name' => $charge_name, 'charge_amount' => $extra_charge, 'charge_group_id' => $charge_group_id,  'netamt' => $net_amount, 'total' => $total_gross];
            }
        }
        if ($total_discount > 0) {
            $is_offer = 'Yes';
        } else {
            $is_offer = 'No';
        }
        // dd($is_offer);
        if($params['is_cart'] == 1){
        foreach ($params['carts'] as $key => $value) {
            $tdata = $value->tdata;
        }
        }else{$tdata='';}

        // dd($available_offer);
        $product_desc = "";
        if($v_id = 23){$product_desc = $item->CNAME1;}else{$product_desc = $item->DEPARTMENT_NAME;}
        $itemArray = $item->toArray();
        unset($itemArray['current_stock']);
        unset($itemArray['vprice']);
        unset($itemArray['item']);
        // dd($total_qty);
        //echo $total_discount;die;


        if($total_qty == 0){
            //$total_qty = $qty;
            $cart_items = collect($cart_items)->where('sku_code', $params['sku_code'])->where('barcode', $params['barcode'])->where('batch_id',$params['batch_id'])->where('serial_id',$serial_id);
            if(!empty($change_mrp) && $change_mrp > 0){
                $cart_items  = $cart_items->where('unit_mrp',$change_mrp);
            }



            $pdata = [];
            //dd($cart_items);
            foreach ($cart_items as $cart) {
                $pdata[] = [ 'item_id' => $cart['item_id'],'sku_code' => $cart['sku_code'],'batch_id'=>$cart['batch_id'],'serial_id'=>$cart['serial_id'],'mrp' => $cart['unit_mrp'] , 'unit_mrp' => $cart['unit_mrp'], 'unit_rsp' => $cart['unit_rsp'], 'discount' => 0, 'sub_total' => $cart['unit_rsp'], 'total' => $cart['unit_rsp'], 'message' => '', 'discount_price_basis' => '', 'qty' => $cart['qty'], 'promo_code'=> '', 'no' => '', 'start_date' => '', 'end_date' => ''];
                // $total_baisc_value += (float)$cart['unit_mrp'] * (float)$cart['qty'];
                $total_baisc_value += (float)$cart['unit_rsp'] * (float)$cart['qty'];
                $total_discount += 0;
                $total_gross += $total_baisc_value;
                $total_qty += (float)$cart['qty'];
            }
            $allPromotions = $pdata;

        }

        $sign = 1;
        if(array_key_exists('trans_type', $params) && $params['trans_type'] == 'exchange'){
            $sign = -1;
        }
       
        $final_data = [
            'p_id'                  => $params['sku_code'],
            'category'              => $item->CNAME1,
            'brand_name'            => $item->CNAME2,
            'sub_categroy'          => '',
            'p_name'                => $params['barcode'].' '.$product_desc,
            'offer'                 => $is_offer,
            'offer_data'            => (object)['applied_offers' => array_values(array_unique($applied_offer, SORT_REGULAR)), 'available_offers' => array_unique($available_offer, SORT_REGULAR)],
            'qty'                   => $sign * $total_qty,
            'multiple_price_flag'   => false,
            'multiple_mrp'          => [format_number($item->LISTED_MRP)],
            'unit_mrp'              => format_number($item->LISTED_MRP),
            'unit_rsp'              => format_number($item->MRP),
            'r_price'               => format_number($sign * $total_baisc_value),
            'discount'              => format_number($sign * $total_discount),
            's_price'               => format_number($sign * $total_gross),
            'extra_charge'          => format_number($sign * $extra_charge),
            'net_amount'            => format_number($sign * $net_amount),
            'varient'               => '',
            'images'                => '',
            'description'           => '',
            'deparment'             => '',
            'barcode'               => $params['barcode'],
            'sku_code'              => $params['sku_code'],
            'serial_id'             => $params['serial_id'],
            'batch_id'              => $params['batch_id'],
            'pdata'                 => urlencode(json_encode($allPromotions)),
            'target_offer'          => urlencode(json_encode($target_offer)), //This is target Pdata
            'charge_details'        => $charge_details,
            'tdata'                 => $tdata,
            'review'                => '',
            'item_det'              => urlencode(json_encode($itemArray)),
            'whishlist'             => 'No',
            'weight_flag'           => ($item->Item->uom->selling->type == 'WEIGHT' ? 1 : 0),
            'uom'                   => $item->Item->uom->selling->name,
            'get_assortment_count'  => $getCount
        ];
        // dd(json_decode(urldecode($final_data['pdata'])));
        // dd($final_data);
        return $final_data;

        // incomplete memo level promotion need to be resolve

    }

    /**
     * Get the all details of item which is related to offer
     *
     * @param  Array  $params
     * @return Array|Object  Items get return with offer details
     */
    public function getItemOfferDetails($params)
    {
        $item = $params['item'];
        //dd($item);
        if(isset($this->db_structure) && $this->db_structure == 2 ){
            $departments = $item->department()->toArray();
            $item->DEPARTMENT_CODE = '';
            $item->DEPARTMENT_NAME = '';
            foreach ($departments as $key => $department) {
                $item->DEPARTMENT_CODE = $department->id;
                $item->DEPARTMENT_NAME = $department->name;
            }

            if($item->department_id > 0){
                $item->DEPARTMENT_CODE = $item->department_id;
            }
                $item->DIVISION_CODE = '';
                $item->DIVISION_NAME = '';
                $item->SECTION_CODE = '';
                $item->ARTICLE_CODE = '';
                $item->ARTICLE_NAME = '';
                $item->SECTION_NAME = '';

        }else{

            // $mapping_store_id = $params['mapping_store_id'];
            $article = DB::table($this->store_db_name.'.'.$this->table_prefix.'invarticle')->select('CODE', 'NAME')->where('CODE', $item->INVARTICLE_CODE)->where('v_id', $this->v_id)->first();
            $group = DB::table($this->store_db_name.'.'.$this->table_prefix.'invgrp')->select('LEV1GRPNAME', 'LEV2GRPNAME', 'GRPCODE', 'GRPNAME', 'PARCODE')->where('GRPCODE', $item->GRPCODE)->where('v_id', $this->v_id)->first();
            $section = DB::table($this->store_db_name.'.'.$this->table_prefix.'invgrp')->select('GRPCODE', 'GRPNAME','PARCODE')->where('GRPCODE', $group->PARCODE)->where('v_id', $this->v_id)->first();
            $division = DB::table($this->store_db_name.'.'.$this->table_prefix.'invgrp')->select('GRPCODE', 'GRPNAME')->where('GRPCODE', $section->PARCODE)->where('v_id', $this->v_id)->first();
            
            //$admsite = DB::table('vmart.admsite')->select('NAME')->where('CODE', $mapping_store_id)->first();
            $item->DIVISION_CODE = $division->GRPCODE;
            $item->SECTION_CODE = $section->GRPCODE;
            $item->DEPARTMENT_CODE = $item->GRPCODE;
            $item->ARTICLE_CODE = isValueExists($article, 'CODE');
            $item->DEPARTMENT_NAME = $group->GRPNAME;
            $item->ARTICLE_NAME = $article->NAME;
            $item->SECTION_NAME = $section->GRPNAME;
            $item->DIVISION_NAME = $division->GRPNAME;
        }
        
        // dd($item);
        return $item;
    }


    /**
     * Get all the Offer of paticular items
     * 
     * @param  Array $params 
     * @return Array|Object All Offer of paticular items
     */
    public function getAllPromotions($params)
    {
        $current_date = date('Y-m-d');
        //date_default_timezone_set('Asia/Kolkata');
        
        //DB::enableQueryLog();
        // Get all promotions of requested store & filter from psite_ptomo_assign table
        $store_promo_list = DB::table($this->store_db_name.'.'.$this->table_prefix.'psite_promo_assign as ppa')
                ->select('ppa.PROMO_CODE','ppa.STARTDATE', 'ppa.ENDDATE', 'pb.ASSORTMENT_CODE', 'ppa.PRIORITY')
                ->join($this->store_db_name.'.'.$this->table_prefix.'promo_buy as pb', 'pb.PROMO_CODE', 'ppa.PROMO_CODE')
                // ->join($this->store_db_name.'.'.$this->table_prefix.'promo_assortment_include as pai', 'pai.ASSORTMENT_CODE', 'pb.ASSORTMENT_CODE')
                // ->leftJoin($this->store_db_name.'.'.$this->table_prefix.'promo_assortment_exclude as pae', 'pae.ASSORTMENT_CODE', 'pb.ASSORTMENT_CODE')
                ->where('ppa.ADMSITE_CODE', $params['mapping_store_id'])
                ->where('ppa.v_id', $this->v_id)
                ->whereRaw(' ? >= STR_TO_DATE(ppa.STARTDATE, "%m/%d/%Y") ', [$current_date])
                ->whereRaw(' ? <= STR_TO_DATE(ppa.ENDDATE, "%m/%d/%Y") ', [$current_date])
                ->where('ppa.STATUS', 'A')->get();

        //dd($store_promo_list);
        // Get all assortment include data from assortment_code

        // if($params['barcode'] == '653298982'){
        //     dd($store_promo_list);
        // }
    
        

        //dd($promo_list);
        // $promo_list = array_collapse($promo_list);
        // dd($promo_list);

        // Exclude expire promotion & get all assortment code
        // foreach ($assortment_data as $key => $value) {
        //  $promo = $value['promo'];
        //  $startdate = $this->convert_in_indian_date_format($promo->STARTDATE);
        //  $enddate = $this->convert_in_indian_date_format($promo->ENDDATE);
        //  if (($current_date >= $startdate) && ($current_date <= $enddate)) {
        //      if (!empty($this->promotionDerivation($value, $params['item']))) {
        //          $promo_list[] = $this->promotionDerivation($value, $params['item']);;
        //      }
        //  } else {
        //      unset($assortment_data[$key]);
        //  }
        // }

        // dd($promo_list);
        // Get all promotion condition

        
        // if($params['barcode'] == '653298982'){
        //     dd($barcode_promo_list);
        // }
        
        return $store_promo_list;

    }


    /**
     * filtering all offer based on validation such date or status 
     * 
     * @param  Array $params 
     * @return Array|Object All Offer of paticular items
     */
    public function filterAllPromotions($params)
    {   

        $promo_list = array();
        $barcode_promo_list = array();
        $assortment_data = array();
        $store_promo_list = $params['all_store_promo'];
        //Filtering promotion based on items
        foreach ($store_promo_list as $key => $value) {
            
            //dd('inside date');
            if (!empty($this->promotionDerivation($value, $params['item']))) {
            // dd('Inside Promotion derivation');
                $promo_list[] = [ 'PROMO_CODE' => $value->PROMO_CODE, 'PRIORITY' => $value->PRIORITY,'start_date'=>$value->STARTDATE,'end_date'=> $value->ENDDATE,'ASSORTMENT_CODE' => $this->promotionDerivation($value, $params['item'])[0]];
            }
            
        }

        foreach ($promo_list as $key => $value) {
            $promo_master = DB::table($this->store_db_name.'.'.$this->table_prefix.'promo_master')
                ->where('code', $value['PROMO_CODE'])
                ->where('TYPE', 'I')
                ->where('v_id', $this->v_id)
                ->first();


                //dd($promo_master);
                // $promo_slab = DB::table($this->store_db_name.'.'.$this->table_prefix.'promo_slab')->where('PROMO_CODE', $value->PROMO_CODE)->get();
                //dd($promo_master);
                if($promo_master  != null){
                    $barcode_promo_list[$value['PROMO_CODE']] = array(
                        'promo_code'        => $value['PROMO_CODE'],
                        'assortment_code'   => $value['ASSORTMENT_CODE'],
                        'priority'          => $value['PRIORITY'],
                        'type'              => $promo_master->TYPE,
                        'basis'             => $promo_master->BASIS,
                        'buy_factor_type'   => $promo_master->BUY_FACTOR_TYPE,
                        'barcode'           => $params['barcode'],
                        'promo_name'        => $promo_master->NAME,
                        'no'                => $promo_master->NO,
                        'promo_summary'     => $promo_master->PROMO_SUMMARY,
                        'start_date'        => $value['start_date'],
                        'end_date'          => $value['end_date'],
                        'extra_charge_status' => $promo_master->extra_charge_status,
                        'extra_charge_type'   => $promo_master->extra_charge_type,
                        'extra_charge_group_id' => $promo_master->extra_charge_group_id
                        // 'promo_slab'     => $promo_slab
                    );
                }
                
        }

        return $barcode_promo_list;
    }

    public function promotionDeriMultipleCat($key , $category, &$value, &$level, &$idata){
        $catCode = 'CCODE'.$level;
        $catE = $this->includeMatchCheckNullCheck($key, $value->$catCode);
                
        if($catE == 0){
            $idata['inc_category_'.$level] = 0;

            if(is_array($category)) {
                if(isset($category['children'])){
                    $level++;
                    foreach($category['children'] as $keyNew => $valNew){
                        $this->promotionDeriMultipleCat($keyNew , $valNew, $value, $level, $idata);
                    }
                }
            }
        }
    }


    public function promotionDerivation($params, $item)
    {
        // dd($params);
        if($this->v_id == 24 || $this->v_id == 11 || $this->v_id == 23 || $this->v_id == 26){
            $assort = VendorSkuAssortmentMapping::where('v_id', $this->v_id)->where('sku_code', $item->sku_code)->where('assortment_code', $params->ASSORTMENT_CODE)->first();
            if($assort){
                return $params->ASSORTMENT_CODE;
            }else{
                return 0;
            }
        }

        $promo_assortment_include = DB::table($this->store_db_name.'.'.$this->table_prefix.'promo_assortment_include')->where('ASSORTMENT_CODE', $params->ASSORTMENT_CODE)->where('v_id', $this->v_id)->get();

        // if($item->barcode == '653298982'){

        //     dd($promo_assortment_include);
        // }
        //dd($promo_assortment_include);
        $idata = [];
        $edata = [];
        $iassort = [];
        $eassort = [];
        $return_data = [];
        foreach ($promo_assortment_include as $key => $value) {
            //dd($item->ICODE.' - '.$value->ICODE);
            $idata['inc_division'] = $this->includeMatchCheckNullCheck($item->DIVISION_CODE, $value->DIVISION_GRPCODE);
            $idata['inc_section'] = $this->includeMatchCheckNullCheck($item->SECTION_CODE, $value->SECTION_GRPCODE);
            $idata['inc_department'] = $this->includeMatchCheckNullCheck($item->DEPARTMENT_CODE, $value->DEPARTMENT_GRPCODE);
            $idata['inc_article'] = $this->includeMatchCheckNullCheck($item->ARTICLE_CODE, $value->INVARTICLE_CODE);
            $idata['inc_icode'] = $this->includeMatchCheckNullCheck($item->ICODE, $value->ICODE);

            $idata['inc_category_1'] = 0;
            $idata['inc_category_2'] = 0;
            $idata['inc_category_3'] = 0;
            $idata['inc_category_4'] = 0;
            $idata['inc_category_5'] = 0;
            $idata['inc_category_6'] = 0;

            $categoryTree = isset($item->categoryTree)?$item->categoryTree:[];
            if(is_object($categoryTree) ){
                $categoryTree = (array)$categoryTree;
            }
            if(count($categoryTree) > 0 ){//This Condition is added to check Multiple Category exists for CloudPos
                
                $idata['inc_category_1'] = 2;
                $idata['inc_category_2'] = 2;
                $idata['inc_category_3'] = 2;
                $idata['inc_category_4'] = 2;
                $idata['inc_category_5'] = 2;
                $idata['inc_category_6'] = 2;
        
                foreach($item->categoryTree as $key => $category){
                    $cidata = [];
                    $level =1;
                    $this->promotionDeriMultipleCat($key , $category, $value, $level,  $cidata);
                    
                    for($i= 1; $i<=6; $i++){
                        if(!isset($cidata['inc_category_'.$i])){
                            $catCode = 'CCODE'.$i;
                            $cidata['inc_category_'.$i] = $this->includeMatchCheckNullCheck('', $value->$catCode);
                        }
                    }

                    if(in_array(2, array_values($cidata))){

                    }else{
                        $idata['inc_category_1'] = 0;
                        $idata['inc_category_2'] = 0;
                        $idata['inc_category_3'] = 0;
                        $idata['inc_category_4'] = 0;
                        $idata['inc_category_5'] = 0;
                        $idata['inc_category_6'] = 0;
                    }
                }
                // dd($idata);
            }
            // else{// This is for Ginesys Client

            //     $idata['inc_category_1'] = $this->includeMatchCheckNullCheck($item->CCODE1, $value->CCODE1);
            //     $idata['inc_category_2'] = $this->includeMatchCheckNullCheck($item->CCODE2, $value->CCODE2);
            //     $idata['inc_category_3'] = $this->includeMatchCheckNullCheck($item->CCODE3, $value->CCODE3);
            //     $idata['inc_category_4'] = $this->includeMatchCheckNullCheck($item->CCODE4, $value->CCODE4);
            //     $idata['inc_category_5'] = $this->includeMatchCheckNullCheck($item->CCODE5, $value->CCODE5);
            //     $idata['inc_category_6'] = $this->includeMatchCheckNullCheck($item->CCODE6, $value->CCODE6);
            // }

            // if($params->ASSORTMENT_CODE == 15) {
            //     dd($item);
            // }

            $idata['inc_stock_check'] = $this->includeMatchCheckNullOrDateCheck($item->GENERATED, $value);
            $idata['inc_price_range'] = $this->includeMatchCheckNullOrRangerCheck($item->LISTED_MRP, $value);
            $idata['inc_desc_1'] = $this->includeMatchCheckNullCheck($item->DESC1, $value->DESC1);
            $idata['inc_desc_2'] = $this->includeMatchCheckNullCheck($item->DESC2, $value->DESC2);
            $idata['inc_desc_3'] = $this->includeMatchCheckNullCheck($item->DESC3, $value->DESC3);
            $idata['inc_desc_4'] = $this->includeMatchCheckNullCheck($item->DESC4, $value->DESC4);
            $idata['inc_desc_5'] = $this->includeMatchCheckNullCheck($item->DESC5, $value->DESC5);
            $idata['inc_desc_6'] = $this->includeMatchCheckNullCheck($item->DESC6, $value->DESC6);

            //dd($idata);
            if (in_array(2, $idata)) {
                // $iassort[] = $value->ASSORTMENT_CODE;
            } else {
                $iassort[] = $value->ASSORTMENT_CODE;
            }
        }
        //dd($iassort);
        $promo_assortment_exclude = DB::table($this->store_db_name.'.'.$this->table_prefix.'promo_assortment_exclude')->where('ASSORTMENT_CODE', $params->ASSORTMENT_CODE)->where('v_id', $this->v_id)->get();
        //dd($promo_assortment_exclude);
        if (count($promo_assortment_exclude) > 0) {
            foreach ($promo_assortment_exclude as $key => $value) {

                $edata['exc_division'] = $this->includeMatchCheckNullCheck($item->DIVISION_CODE, $value->DIVISION_GRPCODE);
                $edata['exc_section'] = $this->includeMatchCheckNullCheck($item->SECTION_CODE, $value->SECTION_GRPCODE);
                $edata['exc_department'] = $this->includeMatchCheckNullCheck($item->DEPARTMENT_CODE, $value->DEPARTMENT_GRPCODE);
                $edata['exc_article'] = $this->includeMatchCheckNullCheck($item->ARTICLE_CODE, $value->INVARTICLE_CODE);
                $edata['exc_icode'] = $this->includeMatchCheckNullCheck($item->ICODE, $value->ICODE);

                $edata['inc_category_1'] = 0;
                $edata['inc_category_2'] = 0;
                $edata['inc_category_3'] = 0;
                $edata['inc_category_4'] = 0;
                $edata['inc_category_5'] = 0;
                $edata['inc_category_6'] = 0;

           
                if(count($categoryTree) > 0 ){//This Condition is added to check Multiple Category exists for CloudPos
                    
                    $edata['inc_category_1'] = 2;
                    $edata['inc_category_2'] = 2;
                    $edata['inc_category_3'] = 2;
                    $edata['inc_category_4'] = 2;
                    $edata['inc_category_5'] = 2;
                    $edata['inc_category_6'] = 2;
            
                    foreach($categoryTree as $key => $category){
                        $cidata = [];
                        $level =1;
                        $this->promotionDeriMultipleCat($key , $category, $value, $level,  $cidata);
                        
                        for($i= 1; $i<=6; $i++){
                            if(!isset($cidata['inc_category_'.$i])){
                                $catCode = 'CCODE'.$i;
                                $cidata['inc_category_'.$i] = $this->includeMatchCheckNullCheck('', $value->$catCode);
                            }
                        }

                        if(in_array(2, array_values($cidata))){

                        }else{
                            $edata['inc_category_1'] = 0;
                            $edata['inc_category_2'] = 0;
                            $edata['inc_category_3'] = 0;
                            $edata['inc_category_4'] = 0;
                            $edata['inc_category_5'] = 0;
                            $edata['inc_category_6'] = 0;
                        }
                    }
                    // dd($idata);
                }
                // $edata['exc_category_1'] = $this->includeMatchCheckNullCheck($item->CCODE1, $value->CCODE1);
                // $edata['exc_category_2'] = $this->includeMatchCheckNullCheck($item->CCODE1, $value->CCODE2);
                // $edata['exc_category_3'] = $this->includeMatchCheckNullCheck($item->CCODE1, $value->CCODE3);
                // $edata['exc_category_4'] = $this->includeMatchCheckNullCheck($item->CCODE1, $value->CCODE4);
                // $edata['exc_category_5'] = $this->includeMatchCheckNullCheck($item->CCODE1, $value->CCODE5);
                // $edata['exc_category_6'] = $this->includeMatchCheckNullCheck($item->CCODE1, $value->CCODE6);
                $edata['exc_stock_check'] = $this->includeMatchCheckNullOrDateCheck($item->GENERATED, $value);
                $edata['exc_price_range'] = $this->includeMatchCheckNullOrRangerCheck($item->LISTED_MRP, $value);
                $edata['exc_desc_1'] = $this->includeMatchCheckNullCheck($item->DESC1, $value->DESC1);
                $edata['exc_desc_2'] = $this->includeMatchCheckNullCheck($item->DESC2, $value->DESC2);
                $edata['exc_desc_3'] = $this->includeMatchCheckNullCheck($item->DESC3, $value->DESC3);
                $edata['exc_desc_4'] = $this->includeMatchCheckNullCheck($item->DESC4, $value->DESC4);
                $edata['exc_desc_5'] = $this->includeMatchCheckNullCheck($item->DESC5, $value->DESC5);
                $edata['exc_desc_6'] = $this->includeMatchCheckNullCheck($item->DESC6, $value->DESC6);

                // if($params->ASSORTMENT_CODE == 337){

                //         if($item->barcode == 'FW008BLAC-37'){
                //             dd($edata);
                //         }
                //         // dd($promo_assortment_exclude);
                //     }

                if (!in_array(2, $edata)) {
                    $eassort[] = $value->ASSORTMENT_CODE;
                }
               
            }

            if (array_unique($iassort) == array_unique($eassort)) {//This true means assortment is not applicable
                return 0;
            } else {
                return $iassort;
            }
        } else {
            return $iassort;
        }
        // return $iassort;
    }


    public function includeMatchCheckNullCheck($ivalue, $pvalue) 
    {
        if (!empty($pvalue)) {
            if ($pvalue == $ivalue) {
                return 0;
            } else {
                return 2;
            }
        } else {
            return 0;
        }
    }

    public function excludeMatchCheckNullCheck($ivalue, $pvalue) 
    {
        if (!empty($pvalue)) {
            if ($pvalue == $ivalue) {
                return 1;
            } else {
                return 2;
            }
        } else {
            return 0;
        }
    }

    public function includeMatchCheckNullOrDateCheck($ivalue, $pvalue) 
    {
        if (!empty($pvalue->STOCKINDATE_FROM) && !empty($pvalue->STOCKINDATE_TO)) {
            $stockindate = date('Y-m-d', strtotime($ivalue));
            $stock_from = $this->convert_in_indian_date_format($pvalue->STOCKINDATE_FROM);
            $stock_to = $this->convert_in_indian_date_format($pvalue->STOCKINDATE_TO);
            if (($stockindate >= $stock_from) && ($stockindate <= $stock_to)) {
                return 1;
            } else {
                return 2;
            }
        } else {
            return 0;
        }
    }

    public function includeMatchCheckNullOrRangerCheck($ivalue, $pvalue) 
    {
        if (!empty($pvalue->PRICE_RANGE_FROM) && !empty($pvalue->PRICE_RANGE_TO)) {
            if ($ivalue >= $pvalue->PRICE_RANGE_FROM && $ivalue <= $pvalue->PRICE_RANGE_TO) {
                return 1;
            } else {
                return 2;
            }
        } else {
            return 0;
        }
    }
    
    /**
     * This function will calculate All Rule / Promotions
     * 
     */
    public function calculatingAllPromotions($params)
    {//INcomplete function need to implement
          
        $promotions = $params['promotions'];
        $carts = $params['cart_items']; 
        // dd($carts);
        $item = $params['item']; 
        $item_id = $params['barcode'];
        $sku_code = $params['sku_code'];
        $batch_id   = $params['batch_id']; 
        $serial_id  = $params['serial_id']; 
        $change_mrp = $params['change_mrp'];

        $final_data = [];
        $offer =[];
        $allOffer= [];
        $available_offers =[];

        // $carts = $carts->toArray();
        // //dd($carts);
        // $newCarts=[];
        // foreach ($carts as $key => $item) {
        //     $qty = $item['qty'];
        //     $newItems = [];
        //     // if($params['item']['barcode'] == $item['barcode']) {
        //     if($item['weight_flag'] == 1) {
        //         $newCarts[] = $item;
        //     } else {
        //         while($qty > 0) {
        //             $tempItems = $item;
        //             $tempItems['qty'] =1;
        //             $newCarts[] = $tempItems;
        //             $qty--;
        //         }
        //     }
            
        // }
        // $carts = collect($newCarts);
        // $tempCarts = collect($newCarts);
        //dd($carts);
        $tempCarts = clone $carts;

        // dd($promotions);
        $promotions = collect($promotions);
        $promotions = $promotions->sortByDesc('priority');
        $pdata= [];
        $target_pdata = [];
        $i=0;

        // dd($promotions);
        foreach ($promotions as $key => $promotion) {
            //dd($promotion);
            $cart_promo = [ 'promo' => $promotion, 'carts' => $carts, 'item' => $item ];
            $params['promotion'] = $promotion;
            
            $params['all_sources'] = $this->getAllSouces($cart_promo);
            // dd($params['all_sources']);
            $Nparams = $params;
            $Nparams['cart_items'] = $tempCarts;
            

            $offer = $this->calculatingIndividualPromotions($Nparams);

            //echo 'gg';
            // dd($offer);

            if($offer){
                $available_offers[] = $offer['available_offer']; 
            }
            //unset($Nparams);
            //dd($offer);
            $remainingItem = [];
            if( $offer['sliceItems'] != null){
                $remainingItem = $offer['sliceItems']->pluck('sku_code')->all();
            }
            
            
            if($i ==0){
                $pdata = array_merge($pdata , $offer['pdata']);
                $target_pdata = array_merge($target_pdata , $offer['target_pdata']);

            }
            
            if(count($pdata) == 0 ){
                $pdata = array_merge($pdata , $offer['pdata']);
            }

            if(count($target_pdata) == 0 ){
                $target_pdata = array_merge($target_pdata , $offer['target_pdata']);
            }


            if(count($remainingItem) >= 1 ){

                // $pdata = array_merge($pdata , $offer['pdata']);
                // $target_pdata = array_merge($target_pdata , $offer['target_pdata']);

                $tempCarts = $tempCarts->filter(function ($value, $key) use(&$remainingItem) {
                    // $remainingItem
                    //dd($value);
                    if(in_array($value['sku_code'], $remainingItem)){
                        foreach ($remainingItem as $key => $rvalue) {
                            if($rvalue == $value['sku_code']){
                                unset($remainingItem[$key]);
                                break;
                            }
                        }
                        return $value;
                    }else{
                        return $value;
                    }
                });
            }
            $tempCarts = $tempCarts->values();
            //dd($tempCarts);
            $allOffer[] = $offer; 
            $i++;
        }
        // dd($allOffer);
        $checkingTargetPdata = true;
        if(count($target_pdata) > 0){
            $currentDis = collect($pdata)->where('sku_code', $sku_code)->sum('discount');
            $targetDis = collect($target_pdata)->where('sku_code', $sku_code)->sum('discount');
            if($currentDis == $targetDis){
                $checkingTargetPdata = false;
            }
        }
        //Target Offer Controller Calculation
        $t_pdata = null;

        if(count($target_pdata) ){

        }

        if($checkingTargetPdata ){
            foreach($params['carts'] as $cart){
                // dd($cart);
                $target_offer = json_decode($cart->target_offer, true);
                //dd($target_offer);
                if($target_offer){
                if(count($target_offer) >= 1){
                    $target_data = collect($target_offer)->where('sku_code', $sku_code);  //Add batch serial

                    if(!$target_data->isEmpty()){
                        $totalCurrentItemQty = $carts->where('sku_code',$sku_code)->where('barcode',$item_id)->where('batch_id',$batch_id)->where('serial_id',$serial_id)->where('unit_mrp',$change_mrp)->sum('qty');
                        $t_pdata = [];
                        foreach($target_data as $todata){
                            if($totalCurrentItemQty > 0){
                                $t_pdata[] = $todata;
                                $totalCurrentItemQty--;
                            }
                        }
                    }
                }
            }
                //$t_pdata = array_merge($t_pdata , json_decode($cart->target_offer));
            }
        }
        $t_pdata = collect($t_pdata);

        // dd($t_pdata);

        //If Target offer is exist then using only targer offer Not using normal Promotion
        if($t_pdata){
            if($t_pdata->where('sku_code', $sku_code)->where('barcode', $item_id)->first()){
                $tpdata = $t_pdata->where('sku_code', $sku_code)->where('barcode', $item_id)->toArray();
                $pdata = [];
                foreach($tpdata as $data){
                    $pdata[] =  (array)$data;
                }
                // dd($pdata);
            }
            // dd($pdata);
        }

        //dd($target_pdata);
        // dd($carts);
        // Creating PDATA for Non Offer Item
        $totalCurrentItemQty = $carts->where('sku_code',$sku_code)->where('barcode',$item_id)->where('batch_id',$batch_id)->where('serial_id',$serial_id)->where('unit_mrp',$change_mrp)->sum('qty');  


        $offerpdata = collect($pdata);
        $totalCurrentDisItemQty = $offerpdata->where('sku_code' , $sku_code)->where('item_id',$item_id)->sum('qty');
        $remainQty = $totalCurrentItemQty - $totalCurrentDisItemQty;
        // dd($offerpdata);
            // dd($remainQty);
        if($params['item']->Item->uom->selling->type == 'WEIGHT') {



            while($remainQty > 0.0){
                $single_unit_qty = $remainQty;
                if($this->split_by_qty){
                    if($remainQty >= 1.0){
                        $remainQty -= 1.0;
                        $single_unit_qty = 1.0;
                    }else{
                        $single_unit_qty = $remainQty;
                        $remainQty = 0.0;
                    }
                }
                $data = [];
                $val = $carts->where('sku_code', $sku_code)->where('barcode',$item_id)->where('batch_id',$batch_id)->where('serial_id',$serial_id)->where('unit_mrp',$change_mrp)->first();
                $subTotal = $single_unit_qty * $val['unit_rsp'];
                $data[] =   [ 'item_id' => $val['item_id'],  'sku_code' => $val['sku_code'] ,'qty' => $single_unit_qty, 'mrp' => $val['unit_mrp'], 'unit_mrp' => $val['unit_mrp'],'batch_id'=>$batch_id,'serial_id'=>$serial_id,'unit_rsp' => $val['unit_rsp'],'discount' => 0 , 'sub_total' => $subTotal, 'total' => $subTotal, 'message' =>'', 'discount_price_basis' => '' ];

                 
                $pdata = array_merge($pdata , $data);
                
            }



        } else {
            $val = $carts->where('sku_code', $sku_code)->where('barcode',$item_id)->where('batch_id',$batch_id)->where('serial_id',$serial_id)->where('unit_mrp',$change_mrp)->first();
            // dd($carts);
            while ( $remainQty > 0) {
                $data = [];
                $qty = $remainQty;
                if($this->split_by_qty){
                    $qty =1;
                }
                $subTotal = $qty * $val['unit_rsp'];
                $data[] =   [ 'item_id' => $val['item_id'], 'sku_code' => $val['sku_code'] ,'qty' => $qty, 'mrp' => $val['unit_mrp'], 'unit_mrp' => $val['unit_mrp'],'batch_id'=>$batch_id,'serial_id'=>$serial_id,'unit_rsp' => $val['unit_rsp'],'discount' => 0 , 'sub_total' => $subTotal, 'total' => $subTotal, 'message' =>'', 'discount_price_basis' => '' ];
                $pdata = array_merge($pdata , $data);
                $remainQty -= $qty;
            }
        }

        $offer = $pdata;
        // dd($offer);
        
        //Getting Best offer from All offer based of client condition
        /*$allOffer = collect($allOffer);
        $allOfferTrue = $allOffer->where('promotion_applied_flag',true);
        if(!$allOfferTrue->isEmpty()){
            $maxPriority = $allOfferTrue->max('priority');
            $allOffer = $allOfferTrue->where('priority', $maxPriority);
            $allOffer = $allOffer->sortByDesc('total_promo_discount');
            foreach($allOffer as $offers){
                $offer  = $offers['pdata'];
                break;
            }
        }else{
     
            foreach($allOffer as $offers){
                
                $offer  = $offers['pdata'];
                break;
            }
        }*/
       
        // dd($offer);
        return [ 'offer' => $offer, 'available_offers' => $available_offers , 'target_offer' => $target_pdata ];
    }

    /**
     * This function will calculate Individual Rule / Promotions
     * 
     */
    public function calculatingIndividualPromotions($params){
        // dd($params['cart_items']);
        $promotion = $params['promotion'];
        // dd($promotion);
        $carts = $params['cart_items']; 
        $allSources = $params['all_sources'];//Will contain item of same assortment
        $cartOfSourceItems = [];//Will contain item of same assortment
        $data = array();
        $type = $from = $to = 0;
        $available_offer = $promotion['promo_name'];
        $final_promotion = [];
        $item_id = $params['barcode'];
        $sku_code = $params['sku_code'];
        $batch_id = $params['batch_id'];
        $serial_id = $params['serial_id'];
        $change_mrp = $params['serial_id'];

        $target_pdata = [];
        // dd($carts);

        $allSourcesKey = array_keys($allSources);
        //Getting only item from cart which is present in all Sources and splittig by qty
        
        $total_qty = 0;
        foreach ($carts as $key => $items) {
            if(in_array($items['sku_code'] , $allSourcesKey ) ){
                if($items['weight_flag'] == 1){

                    $tx_discount = 0;
                    if(isset($items['exclude_discount'])){
                        $tx_discount = $items['exclude_discount'];
                    }
                    $t_qty = $items['qty'];

                    while($items['qty'] > 0.0){

                        $unit_tx_discount = $tx_discount;

                        $single_unit_qty = $items['qty'];
                        if($this->split_by_qty){
                            if($items['qty'] >= 1.0){
                                $items['qty'] -= 1.0;
                                $single_unit_qty = 1.0;
                                $unit_tx_discount = $tx_discount / $t_qty;
                            }else{
                                $single_unit_qty = $items['qty'];
                                $items['qty'] = 0.0;

                                $unit_tx_discount = $tx_discount / $t_qty;
                                $unit_tx_discount = $unit_tx_discount * $single_unit_qty;
                            }

                        }

                        $cartOfSourceItems[] = [ 'item_id' =>$items['item_id'] , 'sku_code' => $items['sku_code'], 'unit_mrp' => $items['unit_mrp'] ,'batch_id'=>$items['batch_id'],'serial_id'=>$items['serial_id'],'qty' => $single_unit_qty, 'unit_rsp' => $items['unit_rsp'] 
                            , 'unit_exclude_discount' => $unit_tx_discount
                            ,'subtotal_unit_mrp' => ($items['unit_mrp'] * $single_unit_qty) - $unit_tx_discount
                            ,'subtotal_unit_rsp' => ($items['unit_rsp'] * $single_unit_qty) - $unit_tx_discount
                            , 'weight_flag' => $items['weight_flag'], 'assort_code' => $allSources[$items['sku_code']]   ] ;
                        
                        $total_qty += $single_unit_qty;
                    }

                    //Need to adjust apportion for exclude items
                    
                }else{

                    $tx_discount = 0;
                    if(isset($items['exclude_discount'])){
                        $tx_discount = $items['exclude_discount'];
                    }
                    $t_qty = $items['qty'];

                    while($items['qty'] > 0){

                        $unit_tx_discount = $tx_discount;
                        $qty = $items['qty'];
                        if($this->split_by_qty){
                            $qty = 1;
                        
                            $unit_tx_discount = $tx_discount / $t_qty;
                        }


                        $cartOfSourceItems[] = [ 'item_id' =>$items['item_id'] , 'sku_code' =>$items['sku_code'] , 'unit_mrp' => $items['unit_mrp'] ,'batch_id'=>$items['batch_id'],'serial_id'=>$items['serial_id'] ,'qty' => $qty, 'unit_rsp' => $items['unit_rsp'] 
                            , 'unit_exclude_discount' => $unit_tx_discount
                            ,'subtotal_unit_mrp' => ($items['unit_mrp'] * $qty) - $unit_tx_discount
                            ,'subtotal_unit_rsp' => ($items['unit_rsp'] * $qty) - $unit_tx_discount
                            , 'weight_flag' => $items['weight_flag'] , 'assort_code' => $allSources[$items['sku_code']] ] ;

                        $items['qty'] = $items['qty'] -  $qty;
                        //dd($items['qty']);
                        $total_qty = $total_qty + $qty;
                    }

                    //Need to adjust apportion for exclude discount
                }
            }
        }

        // dd($cartOfSourceItems);
        //dd($total_qty);

        // Get all promotion slab from promo code
        $promo_slab = DB::table($this->store_db_name.'.'.$this->table_prefix.'promo_slab')->where('PROMO_CODE', $promotion['promo_code'])->where('v_id', $this->v_id)->get();

        // dd($promo_slab);
        $tempTotalQty = $total_qty;

        if($promotion['basis'] == 'QSIMPLE'){
            if($promotion['buy_factor_type'] == 'A') {
                $promo_slab = $promo_slab->sortByDesc('SIMPLE_FACTOR');
                $promo_slab[format_number($tempTotalQty)] = $promo_slab[0];
                unset($promo_slab[0]);
            } else {
                $promo_slab = $promo_slab->sortByDesc('SIMPLE_FACTOR');
                $promo_slab[format_number($promo_slab[0]->SIMPLE_FACTOR)] = $promo_slab[0];
                unset($promo_slab[0]);
            }
        } elseif ($promotion['basis'] == 'QSLAB') {
            $promo_slab = $this->slabQtySplit($promo_slab);
        }
      
        // dd($promo_slab);
        
        $promo_slab = collect($promo_slab);


        //Sorting item based on unit_mrp higheset on top
        $cartOfSourceItems = collect($cartOfSourceItems);
        $cartOfSourceItems = $cartOfSourceItems->sortByDesc('unit_mrp');
 
        // dd($cartOfSourceItems);

        // $tempTotalQty = $total_qty;
        $split_qty = [];
        $max = $promo_slab->keys()->first();
        // dd($total_qty);

        $start = 0 ; $end =0;
        $pdata =[];
        $promotion_applied_flag = false;
        $total_promo_discount = 0;
        $item_with_no_promotion = false;
        
        
        //Promotion on Value Based Means Promotion Applied on Amount not Quantity
        if($promotion['basis'] == 'VSLAB'){
            // dd($promo_slab);
            $promo_slab = $promo_slab->sortByDesc('SLAB_RANGE_FROM');
            foreach ($promo_slab as $key => $slab_value) {
                $item_with_no_promotion =true;
                $calculateBy = 'unit_rsp'; //Ginesys column MRP
                if($slab_value->DISCOUNT_PRICE_BASIS == 'L'){
                    $calculateBy = 'unit_mrp';
                }elseif($slab_value->DISCOUNT_PRICE_BASIS == 'M'){
                    $calculateBy = 'unit_rsp';
                }

                //$totalTempAmount = $cartOfSourceItems->sum($calculateBy);
                $totalTempAmount = $cartOfSourceItems->sum(function ($product)use($calculateBy) {
                    return $product[$calculateBy] * $product['qty'];
                });
                // dd($cartOfSourceItems);
                $SliceCartOfSourceItems = $cartOfSourceItems;
                //dd($slab_value);
                //if($slab_value->SLAB_RANGE_FROM <= $totalTempAmount &&  $totalTempAmount <= $slab_value->SLAB_RANGE_TO){
                if($slab_value->SLAB_RANGE_FROM <= $totalTempAmount){
                    //dd($slab_value);
                    if($slab_value->GET_BENEFIT_CODE == 3){
                        //Getting Get Assortment
                        $get_all_include_assortment_list = DB::table($this->store_db_name.'.'.$this->table_prefix.'promo_assortment_include')->where('ASSORTMENT_CODE', $slab_value->GET_ASSORTMENT_CODE)->get();
                        // dd($get_all_include_assortment_list);

                        $data = [];
                        foreach ($carts as $key => $value) {
                            foreach ($get_all_include_assortment_list as $assort) {
                                $sort_data = [ 'assort' => $assort, 'item_value' => $value ];
                                $code = $this->getAllBarcodeByAssort($sort_data);
                                if ($code == $value['sku_code']) {
                                    $data[$code] = $code;
                                }
                            }
                        }

                        // dd(array_keys($data));
                        $getCarts =  $carts->whereIn('sku_code', array_keys($data) );
                        // dd($getCarts);
                        $getCartOfSourceItems = [];
                        foreach ($getCarts as $key => $items) {

                            if($items['weight_flag'] == 1){
                                while($items['qty'] > 0.0){
                                    $single_unit_qty = 0.0;
                                    if($items['qty'] >= 1.0){
                                        $items['qty'] -= 1.0;
                                        $single_unit_qty = 1.0;
                                    }else{
                                        $single_unit_qty = $items['qty'];
                                        $items['qty'] = 0.0;
                                    }

                                    $getCartOfSourceItems[] = [ 'item_id' =>$items['item_id'] ,'sku_code' =>$items['sku_code']  , 'unit_mrp' => $items['unit_mrp']  
                                        ,'subtotal_unit_mrp' => $items['unit_mrp'] * $single_unit_qty
                                        ,'subtotal_unit_rsp' => $items['unit_rsp'] * $single_unit_qty
                                        ,'batch_id'=>$items['batch_id'],'serial_id'=>$items['serial_id'] ,'qty' => $single_unit_qty, 'unit_rsp' => $items['unit_rsp'] ] ;
                                    
                                    $total_qty += $single_unit_qty;
                                }

                            }else{
                                $qty = 1;
                                // if(in_array($items['item_id'] , $allSources) ){
                                    while($items['qty'] > 0){
                                        $getCartOfSourceItems[] = [ 'item_id' =>$items['item_id'], 'sku_code' =>$items['sku_code'] , 'unit_mrp' => $items['unit_mrp'] 
                                            ,'subtotal_unit_mrp' => $items['unit_mrp'] * $qty
                                            ,'subtotal_unit_rsp' => $items['unit_rsp'] * $qty
                                             ,'qty' => $qty ,'batch_id'=>$items['batch_id'],'serial_id'=>$items['serial_id'], 'unit_rsp' => $items['unit_rsp'] ] ;
                                        $items['qty']--;
                                        $total_qty++;
                                    }
                                // }
                            }
                        }

                        // dd($SliceCartOfSourceItems);
                        $SliceCartOfSourceItems = collect($getCartOfSourceItems);
                        // dd($SliceCartOfSourceItems);

                        if ($slab_value->GET_FACTOR >= 1) {
                            $SliceCartOfSourceItems = $SliceCartOfSourceItems->sortByDesc('unit_mrp');
                            $SliceCartOfSourceItems = $SliceCartOfSourceItems->slice(0, $slab_value->GET_FACTOR);
                            if($SliceCartOfSourceItems->count() >= $slab_value->GET_FACTOR){
                                
                            }else{
                                $SliceCartOfSourceItems = collect([]);
                            }
                        }

                        // dd($SliceCartOfSourceItems);

                        
                        //dd($slab_value);
                        
                        $calculateBy = 'unit_rsp'; //Ginesys column MRP
                        if($slab_value->GET_METHOD == 'L'){
                            $calculateBy = 'unit_mrp';
                        }elseif($slab_value->GET_METHOD == 'M'){
                            $calculateBy = 'unit_rsp';
                        }

                    }

                    if($promotion['buy_factor_type'] != 'A'){
                        if ($slab_value->GET_FACTOR >= 1) {
                            $SliceCartOfSourceItems = $SliceCartOfSourceItems->sortBy('unit_mrp');
                            $SliceCartOfSourceItems = $SliceCartOfSourceItems->slice(0, $slab_value->GET_FACTOR);
                        }

                    }

                    if(!$SliceCartOfSourceItems->isEmpty()){

                        $promotion_applied_flag = true;
                        $item_with_no_promotion =false;

                        //$discount = 0;

                        $total_discount =0;
                        if($slab_value->DISCOUNT_PRICE_BASIS != 'E') {
                            //$total_price = $SliceCartOfSourceItems->sum($calculateBy);
                            $total_price = $SliceCartOfSourceItems->sum(function ($product)use($calculateBy) {
                                return $product[$calculateBy] * $product['qty'];
                            });
                            $priceArray = $SliceCartOfSourceItems->pluck('subtotal_'.$calculateBy)->all();
                            $ratios = $this->get_offer_amount_by_ratio( $priceArray );
                            $ratio_total = array_sum($ratios);
                            
                            $calParams = [ 'total_price' => $total_price, 'discount_type'=> $slab_value->DISCOUNT_TYPE ,'discount_factor' => $slab_value->DISCOUNT_FACTOR];
                            // dd($calParams);
                            $discounts = $this->calculateDiscount($calParams);
                            // dd($discounts);
                            $discount = $discounts['discount'];
                            $total_promo_discount += $discount;
                            $SliceCartOfSourceItems = $SliceCartOfSourceItems->values();
                            //distributing discount amount based on ratio to all items
                            
                            $SliceCartOfSourceItems->transform(function($item , $key) use($ratios, $ratio_total, $discount , &$total_discount){
                                $discount = round( ($ratios[$key]/$ratio_total) * $discount , 2);
                                $total_discount += $discount;
                                return array_merge($item , [ 'discount' => $discount ] );
                            });


                            $total_discount = (string)$total_discount;
                            $discount = (string)$discount;
                            //This code is added because facing issue when rounding of discount value
                            if($total_discount > $discount){
                                $total_discount = (float)$total_discount;
                                $discount = (float)$discount;

                                $total_diff = $total_discount - $discount;
                                $SliceCartOfSourceItems->transform(function($item, $key)use(&$total_diff){
                                    if($total_diff > 0.00){
                                        $item['discount'] -= 0.01;
                                        $total_diff -= 0.01;
                                    }
                                    return $item;
                                });

                            }else if($total_discount < $discount){
                                $total_discount = (float)$total_discount;
                                $discount = (float)$discount;
                                
                                $total_diff =  $discount - $total_discount;
                                $SliceCartOfSourceItems->transform(function($item, $key)use(&$total_diff){
                                    if($total_diff > 0.00){
                                        $item['discount'] += 0.01;
                                        $total_diff -= 0.01;
                                    }
                                    return $item;
                                });
                            }

                        }else{//for Each


                            $SliceCartOfSourceItems->transform(function($item , $key) use( &$slab_value, &$total_discount, $calculateBy){
                                $total_price = $item['subtotal_'.$calculateBy];
                                $calParams = [ 'total_price' => $total_price, 'discount_type'=> $slab_value->DISCOUNT_TYPE ,'discount_factor' => $slab_value->DISCOUNT_FACTOR];
                                $discounts = $this->calculateDiscount($calParams);
                                // dd($discounts);
                                if($item['qty'] <1){

                                    $discounts['discount'] = (float)$discounts['discount'] * $item['qty'];
                                }
                                $total_discount += $discounts['discount'];
                                return array_merge($item , [ 'discount' => $discounts['discount'] ] );
                            });

                        }
                        $cur_item_dis = false;
                        // dd($SliceCartOfSourceItems);
                        $currentItems = $SliceCartOfSourceItems->where('sku_code', $sku_code);
                        // dd($currentItems);
                        foreach ($currentItems as $key => $val) {
                            //dd($val);
                            if($val['discount'] > 0 ){
                                $cur_item_dis = true;
                                //$total_price = $mrp * $val['qty'];
                                $subTotal = (float)$val['qty'] * (float)$val[$calculateBy];
                                $pdata[] =   [ 'item_id' => $val['item_id'], 'sku_code' => $val['sku_code'], 'batch_id'=>$val['batch_id'],'serial_id'=>$val['serial_id'], 'qty' => $val['qty'], 'mrp' => $val[$calculateBy], 'unit_mrp' => $val['unit_mrp'],'unit_rsp' => $val['unit_rsp'],'discount' => $val['discount'], 'sub_total' => $subTotal, 'total' => $subTotal - $val['discount'], 'message' => $promotion['promo_name'], 'discount_price_basis' => $slab_value->DISCOUNT_PRICE_BASIS,'promo_code'=> $promotion['promo_code'],'no'=>$promotion['no'],'start_date'=>$promotion['start_date'],'end_date' =>$promotion['end_date'], 'extra_charge_status' => $promotion['extra_charge_status'], 'extra_charge_type' => $promotion['extra_charge_type'], 'extra_charge_group_id' => $promotion['extra_charge_group_id']];
                            }
                        }


                        //Target Offer Pdata for Get OFFer
                        if($slab_value->GET_BENEFIT_CODE == 3){
                            // dd($currentItems);
                            foreach ($SliceCartOfSourceItems as $key => $val) {
                                // dd($val);
                                if($val['discount'] > 0 ){
                                    if(!$cur_item_dis){
                                        //$total_price = $mrp * $val['qty'];
                                        $subTotal = (float)$val['qty'] * (float)$val[$calculateBy];
                                        $target_pdata[] =   [ 'item_id' => $val['item_id'], 'sku_code' => $val['sku_code'], 'qty' => $val['qty'], 'mrp' => $val[$calculateBy], 'unit_mrp' => $val['unit_mrp'],'batch_id'=>$val['batch_id'],'serial_id'=>$val['serial_id'],'unit_rsp' => $val['unit_rsp'],'discount' => $val['discount'], 'sub_total' => $subTotal, 'total' => $subTotal - $val['discount'], 'message' => $promotion['promo_name'], 'discount_price_basis' => $slab_value->DISCOUNT_PRICE_BASIS,'promo_code'=> $promotion['promo_code'],'no'=>$promotion['no'],'start_date'=>$promotion['start_date'],'end_date' =>$promotion['end_date'], 'extra_charge_status' => $promotion['extra_charge_status'], 'extra_charge_type' => $promotion['extra_charge_type'], 'extra_charge_group_id' => $promotion['extra_charge_group_id']  ];
                                    }
                                }
                            }

                        }

                        if($total_discount > 0){
                            break;
                        }

                    }
                }
                // dd('Cool');
            }

            if($params['v_id'] == 26){

            //dd($promotion);
            }

            // dd($target_pdata);

        }else{
            $getAssort = [];

            $intpart = floor( $tempTotalQty ) ;   // results in 3
            $fraction = $tempTotalQty - $intpart; // results in 0.75
            //dd($max);
            if($fraction > 0.001 && $promotion['basis'] == 'QSLAB'){// It is use of 
                // $promo_slab = $promo_slab->where('id','>',0)->sortByDesc('SLAB_RANGE_FROM');
                
                if($promotion['basis'] == 'QSLAB'){// This will only range value is not float
                    foreach ($promo_slab as $key => $slab_value) {
                        while ( $tempTotalQty > 0.001) {
                            if($slab_value->SLAB_RANGE_FROM <= $tempTotalQty){
                                if($slab_value->SLAB_RANGE_TO == 0.00){
                                    array_push($split_qty, ['qty' => $key , 'applied_qty' => (string)$tempTotalQty,   'is_promo' => 1 , 'slab_flag' => 1 , 'slab_from' =>$slab_value->SLAB_RANGE_FROM , 'slab_to' => $slab_value->SLAB_RANGE_TO ]);
                                    $tempTotalQty = $tempTotalQty - $tempTotalQty;
                                    break;
                                }

                                if($tempTotalQty <= $slab_value->SLAB_RANGE_TO){
                                    $offerQty = (int)$tempTotalQty;
                                    array_push($split_qty, ['qty' => $offerQty.'.00' , 'applied_qty' => (string)$tempTotalQty,   'is_promo' => 1 , 'slab_flag' => 1 , 'slab_from' =>$slab_value->SLAB_RANGE_FROM , 'slab_to' => $slab_value->SLAB_RANGE_TO]);
                                    $tempTotalQty = $tempTotalQty - $tempTotalQty;
                                }else{
                                    array_push($split_qty, ['qty' => $key , 'applied_qty' => format_number($slab_value->SLAB_RANGE_TO), 'is_promo' => 1, 'slab_flag' => 1 , 'slab_from' =>$slab_value->SLAB_RANGE_FROM , 'slab_to' => $slab_value->SLAB_RANGE_TO]);
                                    $tempTotalQty -= $slab_value->SLAB_RANGE_TO;
                                }
                            }else{
                                break;
                            }
                            
                        }
                    }
                }

            }else{
                $slab_flag = 0;
                if($promotion['basis'] == 'QSLAB'){
                    $slab_flag = 1;
                }

                while ($tempTotalQty > 0) {
                    if($promotion['buy_factor_type'] == 'R'){

                        // $calculateBy = 'unit_rsp';
                        // if($slab_value->DISCOUNT_PRICE_BASIS == 'L'){
                        //     $calculateBy = 'unit_mrp';
                        // }elseif($slab_value->DISCOUNT_PRICE_BASIS == 'M'){
                        //     $calculateBy = 'unit_rsp';
                        // }

                        // $result = $promo_slab[format_number(1)];
                        $cartOfSourceItems = $cartOfSourceItems->sortBy('unit_mrp');

                        $promo_buy = DB::table($this->store_db_name.'.'.$this->table_prefix.'promo_buy')->select('ASSORTMENT_CODE','FACTOR')->where('PROMO_CODE', $promotion['promo_code'])->where('v_id', $this->v_id)->get()->pluck('FACTOR','ASSORTMENT_CODE')->all();

                        $buy_assort = collect([]);
                        $allQtyFromallAssort = true;
                        foreach($promo_buy as $assort_code => $assortQty){
                            $tempAssortQty = $assortQty;
                            $chunk = collect([]);
                            // dd($chunk);

                            $cartOfSourceItems = $cartOfSourceItems->filter(function($item, $key) use ($assort_code , &$chunk, &$tempAssortQty){
                                if($item['assort_code'] == $assort_code && $tempAssortQty > 0){
                                    $chunk->push($item);
            
                                    $tempAssortQty--;
                                }else{
                                    return true;
                                }
                            });



                            if($chunk->count() != $assortQty){
                                $allQtyFromallAssort = false;
                                $tempTotalQty = 0;
                                break;
                            }

                        
                            // dd($cartOfSourceItems);

                            $buy_assort = $buy_assort->merge($chunk);
                
                        }

                        if($allQtyFromallAssort){
                            array_push($split_qty, ['qty' => format_number(1), 'is_promo' => 1, 'buy_assort' => $buy_assort ]);
                        }

                    }else{

                        if($promotion['buy_factor_type'] == 'A') {

                            $result = $promo_slab->search(function ($value, $key) use($tempTotalQty) {
                                if (format_number($key) == format_number($tempTotalQty) ) {
                                    //echo 'inside this';exit;
                                    return true;
                                }
                            });

                        }else{

                            $result = $promo_slab->search(function ($value, $key) use($tempTotalQty) {
                                if ((int)$key == $tempTotalQty) {
                                    //echo 'inside this';exit;
                                    return true;
                                }
                            });
                        }

                        
                        // dd($tempTotalQty);
                        if ($result) {
                            $result = $promo_slab[format_number($tempTotalQty)];
                            // dd($result);    
                            if($result->GET_BENEFIT_CODE == 3){
                                $get_assort_code =  $result->GET_ASSORTMENT_CODE;
                                $params['slab_value'] = $result;
                                if(!isset($getAssort[$get_assort_code])){
                                    $getAssort[$result->GET_ASSORTMENT_CODE] =  $this->getGetAssortment($params);
                                }
                                unset($params['slab_value']);

                                if($getAssort[$result->GET_ASSORTMENT_CODE]->sum('qty') >= $result->GET_FACTOR ){

                                    array_push($split_qty, ['qty' => format_number($tempTotalQty), 'is_promo' => 1, 'get_qty' => $result->GET_FACTOR, 'get_assort_code' => $get_assort_code , 'slab_flag' => $slab_flag , 'slab_from' => $result->SLAB_RANGE_FROM , 'slab_to' => $result->SLAB_RANGE_TO ]);

                                }else{
                                    $promo_slab->shift();
                                    $max = $promo_slab->keys()->first();
                            
                                    continue;
                                }

                            }else{
                                // dd($result);
                                $split_qty_params = ['qty' => format_number($tempTotalQty), 'is_promo' => 1 , 'slab_flag' => $slab_flag , 'slab_from' => $result->SLAB_RANGE_FROM , 'slab_to' => $result->SLAB_RANGE_TO ];
                                if($result->GET_FACTOR > 0 ){
                                    $split_qty_params['get_qty'] = $result->GET_FACTOR;
                                }
                                array_push($split_qty, $split_qty_params);
                            }
                            $tempTotalQty = $tempTotalQty - $tempTotalQty;
                        } else {
                            if($max > 0){
                                if ($tempTotalQty > $max) {
                                    $push_data = [];
                                    
                                    $result = $promo_slab[format_number($max)];

                                    if($result->GET_BENEFIT_CODE == 3){
                                        $get_assort_code =  $result->GET_ASSORTMENT_CODE;
                                        $params['slab_value'] = $result;
                                        if(!isset($getAssort[$get_assort_code])){
                                            $getAssort[$result->GET_ASSORTMENT_CODE] =  $this->getGetAssortment($params);
                                        }
                                        unset($params['slab_value']);

                                        if($getAssort[$result->GET_ASSORTMENT_CODE]->sum('qty') >= $result->GET_FACTOR ){
                                            $push_data = ['qty' => format_number($max), 'is_promo' => 1 , 'get_qty' => $result->GET_FACTOR , 'get_assort_code' => $result->GET_ASSORTMENT_CODE, 'slab_from' => $result->SLAB_RANGE_FROM , 'slab_to' => $result->SLAB_RANGE_TO   ];

                                        }else{
                                            $promo_slab->shift();
                                            $max = $promo_slab->keys()->first();
                                    
                                            continue;
                                        }

                                    }else{

                                        $push_data = ['qty' => format_number($max), 'is_promo' => 1 , 'slab_flag' => $slab_flag,'slab_from' => $result->SLAB_RANGE_FROM , 'slab_to' => $result->SLAB_RANGE_TO ];
                                        if($result->GET_FACTOR > 0 ){
                                            $push_data['get_qty'] = $result->GET_FACTOR;
                                        }
                                        
                                    }

                                    if($promotion['basis'] == 'QSLAB'){

                                        if($result->SLAB_RANGE_TO ==0 || $result->SLAB_RANGE_TO =='' ||$result->SLAB_RANGE_TO == null ){
                                            $push_data['qty'] = format_number($tempTotalQty); 
                                            $push_data['max_slab_qty'] = format_number($max); 
                                            $cyclic_for_qslab = false;
                                            // array_push($split_qty , $push_data);
                                            // $tempTotalQty = 0;
                                            // break;
                                        }
                                    }
                                    // dd('outside this');
                                    $tempTotalQty = $tempTotalQty - $max;
                                    array_push($split_qty , $push_data);
                                    
                                } else {

                                    array_push($split_qty, ['qty' => format_number($tempTotalQty), 'is_promo' => 0]);
                                    $tempTotalQty = 0;
                                    
                                }
                            }else{
                                array_push($split_qty, ['qty' => format_number($tempTotalQty), 'is_promo' => 0]);
                                $tempTotalQty = 0;
                            }
                        }

                    }
                }
            
            }

            // dd($split_qty);
            //Creating a common function for below code
            //Getting Get Assortment code
            // dd($getAssort);
            foreach ($split_qty as $key => $value) {

                $fromQty = $toQty = 0; 
                $get_qty_count = 0;
                $remainQty = $value['qty'];
                $split_qty_end_flag = false;
                
                if(isset($value['applied_qty'])){
                    $end = $start + $value['applied_qty'];
                }elseif(isset($value['get_qty'])){
                    if(isset($value['get_assort_code'])){

                        // dd($getAssort);
                        $cartOfSourceItems = $getAssort[$value['get_assort_code']];

                        //This Condition is added for Any Qty in Get Assortment
                        if($value['get_qty'] == 0){
                            $value['get_qty'] = $cartOfSourceItems->sum('qty');
                            $split_qty_end_flag = true;
                        }

                        // dd($cartOfSourceItems);
                    }else{//Means get from Buy assortment
                        
                        if(isset($value['slab_flag']) && $value['slab_flag'] == 1){
                            $shiftQty = (int) ($value['slab_from'] - $value['get_qty'] );
                        }else{

                            $shiftQty = (int) ($value['qty'] - $value['get_qty'] );
                        }
                        while($shiftQty > 0){
                            $cartOfSourceItems->shift();
                            $shiftQty--;
                        }
                        // dd($value['qty'] - $value['get_qty']);
                    }

                    // $cartOfSourceItems = $cartOfSourceItems->sortBy('unit_mrp');
                    // dd($cartOfSourceItems);
                    if($value['get_qty'] > 0){
                        $get_qty_count = $value['get_qty'];
                        $end = $start + $value['get_qty'];
                    }else{
                        $end = $start + ($cartOfSourceItems->count() );
                    }
                    $remainQty =$value['get_qty'];

                }else{
                    $end = $start + $value['qty'];
                }
                $SliceCartOfSourceItems = [];

                // collect($cartOfSourceItems->toArray())
                // echo " ".$start.' - '.$end;   exit;
                //$SliceCartOfSourceItems = $cartOfSourceItems->slice($start, $value['qty'])->values();
                // dd($cartOfSourceItems);
                // dd($cartOfSourceItems);
                if(isset($value['buy_assort'])){
                   $SliceCartOfSourceItems = $value['buy_assort'];
                }else{


                    $cartOfSourceItems->transform(function ($val, $key) use(&$SliceCartOfSourceItems , &$remainQty, &$filterEndQty){

                        $qtyConsumeFull = false;
                        $itemVal = $val;
                        if($remainQty >= $val['qty']){
                            $remainQty -= $val['qty'];
                            $qtyConsumeFull = true;
                        }else{
                            if($remainQty > 0){
                                $val['qty'] -= $remainQty;
                                $itemVal['qty'] = $remainQty;
                                $remainQty = 0;
                                $qtyConsumeFull = false;

                            }else{
                                $itemVal = null;
                            }
                            
                           
                        }

                        if($itemVal){

                            $itemVal['subtotal_unit_mrp'] = ($itemVal['qty'] * $itemVal['unit_mrp']) ;
                            //- ($itemVal['qty'] * $itemVal['unit_exclude_discount'] );
                            $itemVal['subtotal_unit_rsp'] = ($itemVal['qty'] * $itemVal['unit_rsp']) ;
                            //- ($itemVal['qty'] * $itemVal['unit_exclude_discount']);


                            $SliceCartOfSourceItems[] = $itemVal;

                        }


                        if(!$qtyConsumeFull){
                            return $val;
                        }

                    });

                    $SliceCartOfSourceItems = collect($SliceCartOfSourceItems);


                    $cartOfSourceItems =  $cartOfSourceItems->filter(function ($val, $key)  {
                        return !is_null($val);
                    });

                    if(isset($value['get_assort_code'])){
                        $getAssort[$value['get_assort_code']] = $cartOfSourceItems;
                        // dd($cartOfSourceItems);
                    }

                    // dd($cartOfSourceItems);
                    
                    // $filterQty = 0;
                    // $filterEndQty =0;
                    // $SliceCartOfSourceItems = collect($cartOfSourceItems->toArray())->transform(function ($val, $key) use($start, $end , &$filterQty, &$filterEndQty) {
                    //     //Need to make if else as common code
                    //     if($this->split_by_qty){

                    //         $filterQty += (float)$val['qty'];
                    //         if($start < $filterQty && $filterQty <= $end){
                    //             return $val;
                    //         }

                    //     }else{
                    //         $filterEndQty += $val['qty'];
                    //         if( ($filterQty <= $start && $start <=$filterEndQty) && ($filterQty <= $end && $end <=$filterEndQty) )
                    //         {

                    //             $val['qty'] = $end - $start;
                    //             // dd($val['qty']);
                    //             $val['subtotal_unit_mrp'] = $val['qty'] * $val['unit_mrp'];
                    //             $val['subtotal_unit_rsp'] = $val['qty'] * $val['unit_rsp'];
                    //             return $val;
                    //         }
                    //         $filterQty = $filterEndQty;
                    //     }
                    // });
                    
                    //dd($SliceCartOfSourceItems);

                    if($get_qty_count > 0){
                        if($SliceCartOfSourceItems->sum('qty') >= $get_qty_count){

                        }else{
                            $SliceCartOfSourceItems = collect([]);
                        }
                    }

                }

                // $SliceCartOfSourceItems =  $SliceCartOfSourceItems->filter(function ($val, $key)  {
                //     return !is_null($val);
                // });
                // echo '<pre>';
                // print_r($SliceCartOfSourceItems);
                // echo '</pre>';
                // dd($SliceCartOfSourceItems);

                if ($value['is_promo'] == 1) {
                    
                    if(isset($value['max_slab_qty']) ){
                        $slab_value = $promo_slab[$value['max_slab_qty']];
                    }else{

                        $slab_value = $promo_slab[$value['qty']];
                    }
                    // dd($slab_value);

                    $total_discount =0;
                    if(!$SliceCartOfSourceItems->isEmpty()){
                        $promotion_applied_flag = true;
                        $calculateBy = 'unit_rsp'; //Ginesys column MRP
                        if($slab_value->DISCOUNT_PRICE_BASIS == 'L'){
                            $calculateBy = 'unit_mrp';
                        }elseif($slab_value->DISCOUNT_PRICE_BASIS == 'M'){
                            $calculateBy = 'unit_rsp';
                        }

                        
                        //print_r($SliceCartOfSourceItems);
                        // dd($SliceCartOfSourceItems);
                        if($slab_value->DISCOUNT_PRICE_BASIS != 'E') {
                            
                            //Getting only the number of get qty on which promotion should get apply E.G If you get factor is 2 and you have 4 get item then get only 2 item
                            if ($slab_value->GET_FACTOR >= 1) {
                                //$SliceCartOfSourceItems = $SliceCartOfSourceItems->sortBy('unit_mrp');
                                $SliceCartOfSourceItems = $SliceCartOfSourceItems->slice(0, $slab_value->GET_FACTOR);
                                $total_price = $SliceCartOfSourceItems->sum('subtotal_'.$calculateBy);
                            } else {
                                $total_price = $SliceCartOfSourceItems->sum('subtotal_'.$calculateBy);
                            }
                            $priceArray = $SliceCartOfSourceItems->pluck('subtotal_'.$calculateBy)->all();
                            // dd($priceArray);
                            $ratios = $this->get_offer_amount_by_ratio( $priceArray );
                            //dd($ratios);
                            $ratio_total = array_sum($ratios);
                            // $total_price = $SliceCartOfSourceItems->sum($calculateBy);
                            // dd($SliceCartOfSourceItems);
                            $calParams = [ 'total_price' => $total_price, 'discount_type'=> $slab_value->DISCOUNT_TYPE ,'discount_factor' => $slab_value->DISCOUNT_FACTOR];
                            $discounts = $this->calculateDiscount($calParams);
                            $discount = $discounts['discount'];

            
                            // dd($discount);
                            $total_promo_discount += $discount;
                            $SliceCartOfSourceItems = $SliceCartOfSourceItems->values();
                            //distributing discount amount based on ratio to all items
                            $total_discount =0;
                            $SliceCartOfSourceItems->transform(function($item , $key) use($ratios, $ratio_total, $discount , &$total_discount){
                                $discount = round( ($ratios[$key]/$ratio_total) * $discount , 2);
                                $total_discount += $discount;
                                return array_merge($item , [ 'discount' => $discount ] );
                            });
                            
                            $total_discount = (string)$total_discount;
                            $discount = (string)$discount;
                            //This code is added because facing issue when rounding of discount value
                            if($total_discount > $discount){
                                $total_discount = (float)$total_discount;
                                $discount = (float)$discount;

                                $total_diff = $total_discount - $discount;
                                $SliceCartOfSourceItems->transform(function($item, $key)use(&$total_diff){
                                    if($total_diff > 0.00){
                                        $item['discount'] -= 0.01;
                                        $total_diff -= 0.01;
                                    }
                                    return $item;
                                });

                            }else if($total_discount < $discount){
                                $total_discount = (float)$total_discount;
                                $discount = (float)$discount;
                                
                                $total_diff =  $discount - $total_discount;
                                $SliceCartOfSourceItems->transform(function($item, $key)use(&$total_diff){
                                    if($total_diff > 0.00){
                                        $item['discount'] += 0.01;
                                        $total_diff -= 0.01;
                                    }
                                    return $item;
                                });
                            }

                        } else{ // For EACH
                            // dd($SliceCartOfSourceItems);
                            // if ($slab_value->GET_FACTOR >= 1) {
                            //     $SliceCartOfSourceItems = $SliceCartOfSourceItems->sortBy('unit_mrp');
                            //     $SliceCartOfSourceItems = $SliceCartOfSourceItems->slice(0, $slab_value->GET_FACTOR);
                            // }

                            //$total_discount =0;
                            $SliceCartOfSourceItems->transform(function($item , $key) use( &$slab_value, &$total_promo_discount, $calculateBy){
                                $total_price = $item[$calculateBy];
                                $calParams = [ 'total_price' => $total_price, 'discount_type'=> $slab_value->DISCOUNT_TYPE ,'discount_factor' => $slab_value->DISCOUNT_FACTOR];

                                $discounts = $this->calculateDiscount($calParams);
                                if($item['qty'] <1){
                                    $total_discount = (float)$discounts['discount'] * $item['qty'];
                                    $discounts['discount'] = (float)$discounts['discount'] * $item['qty'];
                                }
                                $total_promo_discount += $discounts['discount'];
                                return array_merge($item , [ 'discount' => $discounts['discount'] ] );
                            });

                        }


                        // dd($SliceCartOfSourceItems);
                        
                        $curr_item_dis = false;

                        $currentItems = $SliceCartOfSourceItems->where('sku_code', $sku_code);
                        // dd($currentItems);
                        foreach ($currentItems as $key => $val) {
                            //dd($val);
                            if($val['discount'] > 0 ){
                                $curr_item_dis = true;
                                //$total_price = $mrp * $val['qty'];
                                $subTotal = (float)$val['qty'] * (float)$val[$calculateBy];
                                $pdata[] =   [ 'item_id' => $val['item_id'], 'sku_code' => $val['sku_code'], 'qty' => $val['qty'], 'mrp' => $val[$calculateBy], 'unit_mrp' => $val['unit_mrp'],'batch_id'=>$val['batch_id'],'serial_id'=>$val['serial_id'],'unit_rsp' => $val['unit_rsp'],'discount' => $val['discount'], 'sub_total' => $subTotal, 'total' => $subTotal - $val['discount'], 'message' => $promotion['promo_name'], 'discount_price_basis' => $slab_value->DISCOUNT_PRICE_BASIS,'promo_code'=> $promotion['promo_code'],'no'=>$promotion['no'],'start_date'=>$promotion['start_date'],'end_date' =>$promotion['end_date'], 'extra_charge_status' => $promotion['extra_charge_status'], 'extra_charge_type' => $promotion['extra_charge_type'], 'extra_charge_group_id' => $promotion['extra_charge_group_id']  ];
                            }
                        }

                        //Target Offer Pdata for Get OFFer
                        if($slab_value->GET_BENEFIT_CODE == 3){
                            // dd($currentItems);
                            foreach ($SliceCartOfSourceItems as $key => $val) {
                                //dd($val);
                                if($val['discount'] > 0 ){
                                    if(!$curr_item_dis){
                                        //$total_price = $mrp * $val['qty'];
                                        $subTotal = (float)$val['qty'] * (float)$val[$calculateBy];
                                        $target_pdata[] =   [ 'item_id' => $val['item_id'], 'sku_code' => $val['sku_code'], 'qty' => $val['qty'], 'mrp' => $val[$calculateBy], 'unit_mrp' => $val['unit_mrp'],'batch_id'=>$val['batch_id'],'serial_id'=>$val['serial_id'],'unit_rsp' => $val['unit_rsp'],'discount' => $val['discount'], 'sub_total' => $subTotal, 'total' => $subTotal - $val['discount'], 'message' => $promotion['promo_name'], 'discount_price_basis' => $slab_value->DISCOUNT_PRICE_BASIS,'promo_code'=> $promotion['promo_code'],'no'=>$promotion['no'],'start_date'=>$promotion['start_date'],'end_date' =>$promotion['end_date'], 'extra_charge_status' => $promotion['extra_charge_status'], 'extra_charge_type' => $promotion['extra_charge_type'], 'extra_charge_group_id' => $promotion['extra_charge_group_id']   ];
                                    }
                                }
                            }

                        }
                        // dd($pdata);

                    }

                    /*
                    //Need to understand Why this condition is added
                    
                    if($promotion['basis'] == 'QSLAB'){
                        // dd($cartOfSourceItems);
                        if($total_discount > 0){
                            break;
                        }else{
                            $end = $start = 0;
                        }

                    }
                    */

                    // dd($pdata);

                } else {
                    $item_with_no_promotion = true;
                    /*
                    $currentItems = $SliceCartOfSourceItems->where('item_id', $item_id);
                    //dd($currentItems);
                    foreach ($currentItems as $key => $val) {
                        //dd($val);
                        //$total_price = $mrp * $val['qty'];
                        $subTotal = $val['qty'] * $val['unit_mrp'];
                        $pdata[] =   [ 'item_id' => $val['item_id'], 'qty' => $val['qty'], 'mrp' => $val['unit_mrp'], 'unit_mrp' => $val['unit_mrp'],'unit_rsp' => $val['unit_rsp'],'discount' => 0 , 'sub_total' => $subTotal, 'total' => $subTotal, 'message' => $promotion['promo_name'], 'discount_price_basis' => '' ];
                  
                    }*/
                }

                //This is added in case of Any Qty
                if($split_qty_end_flag){
                    break;
                }
                $start = $end ;
            }


        }

        //dd($pdata);
        //$pdataC = collect($pdata);
        // $pqty = $pdataC->where('item_id' , $item_id)->sum('qty');
        // //dd($cartOfSourceItems);
        // $pTotalQty = $cartOfSourceItems->where('item_id', $item_id)->sum('qty');

        // $remainQty = $pTotalQty - $pqty;
        // while($remainQty > 0){
        //  $val = $cartOfSourceItems->where('item_id', $item_id)->first();
        //  $subTotal = $val['qty'] * $val['unit_mrp'];
        //  $pdata[] =   [ 'item_id' => $val['item_id'], 'qty' => $val['qty'], 'mrp' => $val['unit_mrp'], 'unit_mrp' => $val['unit_mrp'],'unit_rsp' => $val['unit_rsp'],'discount' => 0 , 'sub_total' => $subTotal, 'total' => $subTotal, 'message' =>'', 'discount_price_basis' => '' ];
            
        //  $remainQty--;
        // }


        // dd($pdata);
        if(!$item_with_no_promotion){
            $SliceCartOfSourceItems = null;
        }
        
        if($total_promo_discount <= 0){
            $SliceCartOfSourceItems = null;
        }

        return [ 'priority' => $promotion['priority'], 'total_promo_discount' => $total_promo_discount , 'promotion_applied_flag' => $promotion_applied_flag , 'pdata' => $pdata , 'sliceItems' => $SliceCartOfSourceItems , 'available_offer' => $available_offer , 'target_pdata' => $target_pdata ];             

    }

    public function getGetAssortment($params){
        $first_slab = $params['slab_value'];
        $carts = $params['cart_items'];
        // dd($carts);
        $get_all_include_assortment_list = DB::table($this->store_db_name.'.'.$this->table_prefix.'promo_assortment_include')->where('ASSORTMENT_CODE', $first_slab->GET_ASSORTMENT_CODE)->get();
        // dd($get_all_include_assortment_list);

        $data = [];
        foreach (collect($carts)->unique('sku_code') as $key => $value) {
            // dd($value);
            // $value =['item_id' => $value->item_id, 'barcode' => $value->barcode];
            foreach ($get_all_include_assortment_list as $assort) {
                $sort_data = [ 'assort' => $assort, 'item_value' => $value ];
                $code = $this->getAllBarcodeByAssort($sort_data);
                if ($code == $value['sku_code']) {
                    $data[$code] = $code;
                }
            }
        }
        // dd($data);
        // dd(array_keys($data));
        $getCarts =  $carts->whereIn('sku_code', array_keys($data) );
        $getCarts= $getCarts->sortByDesc('unit_mrp');
        // dd($getCarts);
        $getCartOfSourceItems = [];
        foreach ($getCarts as $key => $items) {

            if($items['weight_flag'] == 1){
                $itemQty = (float)$items['qty'];
    
                while($itemQty > 0.000){
                    $single_unit_qty = $itemQty;
                    if($this->split_by_qty){
                        if($itemQty >= 1.000){
                            $itemQty -= 1.0;
                            $single_unit_qty = 1.0;
                        }else{
                            // echo $itemQty;
                            $single_unit_qty = $itemQty;
                            $items['qty'] = $itemQty = 0.000;

                        }
                    }

                    $getCartOfSourceItems[] = [ 'item_id' =>$items['item_id'], 'sku_code' =>$items['sku_code']  , 'unit_mrp' => $items['unit_mrp']  , 'qty' => $single_unit_qty, 'unit_rsp' => $items['unit_rsp']
                        ,'subtotal_unit_mrp' => $items['unit_mrp'] * $single_unit_qty
                        ,'subtotal_unit_rsp' => $items['unit_rsp'] * $single_unit_qty
                        ,'batch_id'=>$items['batch_id'],'serial_id'=>$items['serial_id']
                        , 'weight_flag' => $items['weight_flag'] ] ;
                    
                    // $total_qty += $single_unit_qty;
                }
                // dd($getCartOfSourceItems);

            }else{
                // if(in_array($items['item_id'] , $allSources) ){
                    while($items['qty'] > 0){
                        $qty = $items['qty'];
                        if($this->split_by_qty){
                            $qty = 1;
                        }
                        $getCartOfSourceItems[] = [ 'item_id' =>$items['item_id'], 'sku_code' =>$items['sku_code'] , 'unit_mrp' => $items['unit_mrp']  ,'qty' => $qty, 'unit_rsp' => $items['unit_rsp'] 
                            ,'subtotal_unit_mrp' => $items['unit_mrp'] * $qty
                            ,'subtotal_unit_rsp' => $items['unit_rsp'] * $qty
                            ,'batch_id'=>$items['batch_id'],'serial_id'=>$items['serial_id'] ] ;
                        $items['qty'] -= $qty;
                        // $total_qty++;
                    }
                // }
            }
        }

        // dd($getCartOfSourceItems);
        return collect($getCartOfSourceItems);
        // dd($SliceCartOfSourceItems);
        //dd($slab_value);
    

    }
    public function slabQtySplit($params) 
    {
        $data = array();
        foreach ($params as $key => $value) {
            $from = $value->SLAB_RANGE_FROM;
            // $value->QTY = format_number($from);
            $data[format_number($from)] = $value;
            while ($value->SLAB_RANGE_TO > $from) {
                $from++;
                // $value->QTY = format_number($from);
                $data[format_number($from)] = $value;
                // $data[format_number($from)]->QTY = $from;
            }
        }
        krsort($data);
        return $data;
    }

    public function promoConditionByQty($params) 
    {
        // dd($params['condition']);
        $items = $params['items'];
        $data = array();
        $to = $params['to'];
        $from = $params['from'];
        $condition = $params['condition'];
        $total_qty = $params['total_qty'];

        if ($from == $to) {
            if ($total_qty >= $from) {

                if($condition->DISCOUNT_PRICE_BASIS == 'L') {
                        
                        $items = $this->arraySorting($items,'unit_mrp');
                        $count = 1;
                        foreach($items as $fetch){
                            if($count <= $condition->SIMPLE_FACTOR) {
                              $disParams = array( 'total_price' => $fetch['unit_mrp'], 'discount_type' => $condition->DISCOUNT_TYPE,'discount_factor' => $condition->DISCOUNT_FACTOR);
                              $s_price  = $this->calculateDiscount($disParams);
                            } else {
                                $s_price = ['discount' => 0,'gross' => $fetch['unit_mrp'] ];
                            }
                            $data[$fetch['sku_code']][] = [ 'item_id' => $fetch['item_id'],'sku_code' => $fetch['sku_code'], 'basic_price' => $fetch['unit_mrp'], 'discount' => $s_price['discount'], 'gross' => $s_price['gross'], 'qty' => $fetch['qty'] ];
                            $count++;
                        }

                } elseif($condition->DISCOUNT_PRICE_BASIS == 'M') {

                }

            } else {
                foreach ($items as $key => $item) {
                    $data[$item['sku_code']][] = [ 'item_id' => $item['item_id'], 'sku_code' => $item['sku_code'] , 'basic_price' => $item['unit_mrp'], 'discount' => 0, 'gross' => $item['unit_mrp'], 'qty' => $item['qty'] ];
                }
            }
        }
        return $data;
    }

    
    /**
     * This function will get calculate Discount
     *
     * 1) It will calculate % Discount
     * 2) It will calculate Amount off Discount
     * 3) It will calculate Flat Amount
     * 
     * @param  Array $params 
     * @return Array|Object Offer amount and offer message
     */
    public function calculateDiscount($params){
        //dd($params);
        $total_price = $params['total_price'];
        $discountType = $params['discount_type'];
        $discountFactor = $params['discount_factor'];

        $discount = 0;
        /**
         * Calculating Percentage Discount
         * @var [type]
         */
        if($discountType == 'P'){
            if($discountFactor > 100){
                $discountFactor = 100;
            }
            $discount = $total_price * $discountFactor / 100 ;
        }


        /**
         * Calculating Amount Discount
         * @var [type]
         */
        if($discountType == 'A'){
            if($total_price <= $discountFactor){
                // $discountFactor = $total_price;
                $discountFactor = 0;
            }
            $discount = $discountFactor ;
            $ex_price = $total_price - $discount ;
        }


        /**
         * Calculating Fixed Price Discount
         * @var [type]
         */
        if($discountType == 'F'){
            $discount = $total_price - $discountFactor ;
        }

        if($discount > 0){
           
        }else{
            $discount = 0;
        }

        $gross_price = $total_price - $discount;
        return ['discount' => $discount,'gross' => $gross_price ];

    }

    public function convert_in_indian_date_format($date) {
        if (strpos($date, "-") !== false) {
            $fdate = str_replace(' 00:00:00','', $date);
            $fdate = explode("-", $fdate);
        } else if (strpos($date, "/") !== false) {
            $fdate = str_replace(' 00:00:00','', $date);
            $fdate = explode("/", $fdate);
        }
        if (strpos($date, "00:00:00") !== false) {
            return date('Y-m-d', strtotime($fdate[2] . '-' . $fdate[1] . '-' . $fdate[0]));
        } else {
            return date('Y-m-d', strtotime($fdate[2] . '-' . $fdate[0] . '-' . $fdate[1]));
        }
        
    }

    public function getAllSouces($params)
    {
        // dd($params);
        $data = array();
        $sort_data = array();

        // $all_assortment_code = DB::table($this->store_db_name.'.'.$this->table_prefix.'promo_buy')->where('PROMO_CODE', $params['promo']['promo_code'])->where('v_id', $this->v_id)->get()->pluck('ASSORTMENT_CODE')->all();
        // $all_barcode = $params['carts']->pluck('barcode')->all();
        // // dd($all_assortment_code);

        // $data = VendorSkuAssortmentMapping::where('v_id', $this->v_id )->whereIn('assortment_code', $all_assortment_code)->whereIn('barcode', $all_barcode)->get()->pluck('barcode')->all();
        // $data = array_combine($data, $data);
        

        foreach ($params['carts'] as $key => $value) {

            $all_assortment_code = DB::table($this->store_db_name.'.'.$this->table_prefix.'promo_buy')->where('PROMO_CODE', $params['promo']['promo_code'])->where('v_id', $this->v_id)->get()->pluck('ASSORTMENT_CODE');
            

            if($this->v_id == 24 || $this->v_id == 11 || $this->v_id == 23 || $this->v_id == 26){
                // dd($all_assortment_code);
                $assort = VendorSkuAssortmentMapping::where('v_id', $this->v_id)->where('sku_code', $value['sku_code'])->whereIn('assortment_code', $all_assortment_code )->get();
                if($params['promo']['buy_factor_type'] == 'R'){
                    if(!$assort->isEmpty()){
                        $data[$value['sku_code']] = $assort->first()->assortment_code;
                    }
                }else{
                    if(!$assort->isEmpty()){
                        $data[$value['sku_code']] = $value['sku_code'];
                    }
                }
            }else{

                // dd($all_assortment_code);
                $get_all_include_assortment_list = DB::table($this->store_db_name.'.'.$this->table_prefix.'promo_assortment_include')->whereIn('ASSORTMENT_CODE', $all_assortment_code)->where('v_id', $this->v_id)->where('ICODE', $value['sku_code'])->get();
                
                if($get_all_include_assortment_list->isEmpty()){
                    $get_all_include_assortment_list = DB::table($this->store_db_name.'.'.$this->table_prefix.'promo_assortment_include')->whereIn('ASSORTMENT_CODE', $all_assortment_code)->where('v_id', $this->v_id)->get();
                }
                //dd($get_all_include_assortment_list);
                foreach ($get_all_include_assortment_list as $assort) {
                    $sort_data = [ 'assort' => $assort, 'item_value' => $value ];
                    $sku_code = $this->getAllBarcodeByAssort($sort_data);
                    //dd($barcode);
                    if ($sku_code == $value['sku_code']) {
                        //echo 'Inside value';
                        $data[$sku_code] = $sku_code;
                    }
                }

            }

            //dd($data);

            // $get_all_exclude_assortment_list = DB::table($this->store_db_name.'.'.$this->table_prefix.'promo_assortment_exclude')->where('ASSORTMENT_CODE', $all_assortment_code)->where('v_id', $this->v_id)->get();
            // if (count($get_all_exclude_assortment_list) > 0) {
            //     foreach ($get_all_exclude_assortment_list as $eassort) {
            //         $esort_data = [ 'assort' => $eassort, 'item_value' => $value ];
            //         $ebarcode = $this->getAllBarcodeByAssort($esort_data);
            //         if ($ebarcode == $value['item_id']) {
            //             unset($data[$value['item_id']]);
            //         }
            //     }
            // }

        }

        //     //dd($data);

        //     // $get_all_exclude_assortment_list = DB::table($this->store_db_name.'.'.$this->table_prefix.'promo_assortment_exclude')->where('ASSORTMENT_CODE', $all_assortment_code)->where('v_id', $this->v_id)->get();
        //     // if (count($get_all_exclude_assortment_list) > 0) {
        //     //     foreach ($get_all_exclude_assortment_list as $eassort) {
        //     //         $esort_data = [ 'assort' => $eassort, 'item_value' => $value ];
        //     //         $ebarcode = $this->getAllBarcodeByAssort($esort_data);
        //     //         if ($ebarcode == $value['item_id']) {
        //     //             unset($data[$value['item_id']]);
        //     //         }
        //     //     }
        //     // }

        // }
        // dd($data);
        // $flattened = array_flatten($data);
        //$unique = array_unique($data, SORT_REGULAR);
        
        //dd($unique);
        return $data;
    }

   public function getAllBarcodeByAssortMultipleCat($category_id , $category, &$assort, &$level, &$columnCondition, &$result)
    {
        $catCode = 'CCODE'.$level;

        if(in_array($catCode, $columnCondition)){
            //dd($result);
            if(isset($result[$catCode]) && $result[$catCode] == 1){

            }else{
                if($category_id  == $assort->$catCode){
                   $result[$catCode] = 1;
                }else{
                    $result[$catCode] = 0;
                }
            }
        }else{
            $result[$catCode] = 1;
        }

        if(isset($category['children'])){
            $level++;
            foreach($category['children'] as $keyNew => $valNew){
                $this->getAllBarcodeByAssortMultipleCat($keyNew , $valNew, $assort, $level, $columnCondition, $result);
            }
        }

        return $result;
        
    }
    
    public function getAllBarcodeByAssort($params) 
    {
        //dd($params);
        
        $item = $params['item_value'];
        if($this->v_id == 24 || $this->v_id == 11 || $this->v_id == 23 || $this->v_id == 26 ){
            $assort = VendorSkuAssortmentMapping::where('v_id', $this->v_id)->where('sku_code', $item['sku_code'])->where('assortment_code', $params['assort']->ASSORTMENT_CODE)->first();
            if($assort){
                return  $item['sku_code'];
            }else{
                return 0;
            }
        }

        //if($item['item_id'] == '100001'){

            $columnCondition = [];
            $values = [];
            $keys = [];
            //dd($params['assort']);
            foreach ($params['assort'] as $key => $value) {
                if (!empty($value)) {
                    if ($key != 'ASSORTMENT_CODE' && $key != 'CODE' && $key != 'PRICE_RANGE_BASIS' && $key != 'PRICE_RANGE_FROM' && $key != 'PRICE_RANGE_TO' && $key != 'STOCKINDATE_FROM' && $key != 'STOCKINDATE_TO' && $key != 'QTY' && $key != 'ITEM_REWARD_VALUE' && $key != 'created_at' && $key != 'updated_at' && $key != 'id' && $key != 'v_id') {
                        array_push($columnCondition, $key);
                        //array_push($values, $value);
                    }
                }
                $keys[$key] = $value;
            }

            // $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $item['item_id'])->first();
            // if($bar){
            //     $sku = VendorSkuDetails::where('id', $bar->vendor_sku_detail_id )->where('v_id', $this->v_id)->first();
            //     $sku->barcode = $bar->barcode;
            // }
            $sku = VendorSkuDetails::where('sku_code', $item['sku_code'])->where('v_id', $this->v_id)->first();
            $it = $sku->vendorItem;
            

            // for($i= 1; $i<=6; $i++){
            //     if(!isset($idata['inc_category_'.$i])){
            //         $catCode = 'CCODE'.$i;
            //         $idata['inc_category_'.$i] = $this->includeMatchCheckNullCheck('', $value->$catCode);
            //     }
            // }
            
            //dd($params['assort']);
            
            $condition = true;
            foreach($columnCondition as $key => $column_name){
                if ($column_name == 'ICODE') {
                    if($item['sku_code'] == $params['assort']->$column_name){
                        return $item['sku_code'];
                    }else{
                        $condition = false;
                    }
                }

                // for($counter = 0; $counter < 5; $counter++){
                //     $code = 'CCODE'. ($counter +1);
                //     if(isset($category[$counter])){
                //         $sku->$code = $category[$counter]->id;
                //     }else{
                //         $sku->$code = '';
                //     }
                // }

                if($column_name == 'DEPARTMENT_GRPCODE'){
                    //dd($it);
                    if($it->department_id != $params['assort']->$column_name){
 
                        $condition = false;
                    }
                }


                if($column_name == 'DESC1'){
                    if($it->brand_id != $params['assort']->$column_name){
                        //echo 'inside Desc1';
                        $condition = false;
                    }
                }
            }

            //dd($sku);

            #### Category Verification start Here ####
            //Verifying Category Exisits in Assortment
            $category = $sku->category()->toArray();      
            //dd($category);         
            $productC = new ProductController;
            $categoryTree = $productC->createTree($category, 0);
            //dd($categoryTree);

            $categoryCondition = false;
            foreach($categoryTree as $key => $category){
                $level =1;
                $result = [];
                $this->getAllBarcodeByAssortMultipleCat($key , $category, $params['assort'], $level,  $columnCondition , $result);
                // dd($result);

                for($i= 1; $i<=6; $i++){
                    $catCode = 'CCODE'.$i;
                    if(!isset($result[$catCode])){
                        if(in_array($catCode, $columnCondition)){
                            $result[$catCode] = 0;
                        }else{
                            $result[$catCode] = 1 ;
                        }
                    }
                }

                //Checking if 0 mean false is exist or not 
                if(in_array( 0, array_values($result))){

                }else{//if Any one category hirearchy is true , CategoryCondition is true
                    $categoryCondition = true;
                }

            }
            #### Category Verification start Here ####
            
            if(!$categoryCondition){
                $condition = false;
            }

            //dd($condition);

            if($condition){
                return $item['sku_code'];
            }

        //}
    }

    //Sort Array 
    private function arraySorting(&$arr,$key) 
    { 
        $n = sizeof($arr); 
        for($i = 0; $i < $n; $i++) 
        { 
            $swapped = False; 
            for ($j = 0; $j < $n - $i - 1; $j++) 
            { 
                if ($arr[$j][$key] < $arr[$j+1][$key]) 
                { 
                    $t = $arr[$j]; 
                    $arr[$j] = $arr[$j+1]; 
                    $arr[$j+1] = $t; 
                    $swapped = True; 
                } 
            } 
            if ($swapped == False) 
                break; 
        } 
        return $arr;
    }//End of arraySorting

    public function get_offer_amount_by_ratio($param , $offer_amount = 0){

        $c = count($param);
        if($c < 1)
            return ''; //empty input
        if($c == 1)
            return [ $param[0] ]; //only 1 input
        $gcd = $this->gcd($param[0], $param[1]); //find gcd of inputs
        for($i = 2; $i < $c; $i++) 
            $gcd = $this->gcd($gcd, $param[$i]);
        $var[] = $param[0] / $gcd; //init output
        for($i = 1; $i < $c; $i++)
            $var[] = ($param[$i] / $gcd); //calc ratio
        return $var; 

        //$ratio_total = array_sum($var);
        // $final_val = ($var[0]/$ratio_total) * $offer_amount;
        //return round($final_val,2);
    }

    public function gcd($a, $b) {
        $_a = abs($a);
        $_b = abs($b);

        while ($_b != 0) {

            $remainder = $_a % $_b;
            $_a = $_b;
            $_b = $remainder;   
        }
        return $a;
    }


    // Memo Level Promotion

    public function memoIndex($params)
    {
        // dd($params);
        $storeMemoPromoList = $item = $promo_list = [];
        $current_date = date('Y-m-d');
        $store = Store::find($params['store_id']);
        // $this->store_db_name = get_store_db_name([ 'store_id' => $params['store_id'] ]);
        $this->v_id = $params['v_id'];
        $mapping_store_id = $store->mapping_store_id;
        // dd($this->store_db_name);

        // Check Memo Level Promotion Exsist 

        $memoPromoList  = DB::table($this->store_db_name.'.'.$this->table_prefix.'psite_promo_assign as ppa')
                ->select('ppa.PROMO_CODE','ppa.STARTDATE', 'ppa.ENDDATE', 'pb.ASSORTMENT_CODE', 'ppa.PRIORITY','pm.BASIS','pm.NAME','ppa.STARTDATE', 'ppa.ENDDATE', 'pm.NO')
                ->leftJoin($this->store_db_name.'.'.$this->table_prefix.'promo_buy as pb', 'pb.PROMO_CODE', 'ppa.PROMO_CODE')
                ->leftJoin($this->store_db_name.'.'.$this->table_prefix.'promo_master as pm', 'pm.CODE', 'ppa.PROMO_CODE')
                ->where('ppa.ADMSITE_CODE', $params['store_id'])
                ->where('ppa.v_id', $this->v_id)
                ->where('pm.TYPE', 'M')
                ->where('ppa.STATUS', 'A')->get();

        // dd($memoPromoList);

        foreach ($memoPromoList as $key => $value) {
            $startdate = $this->convert_in_indian_date_format($value->STARTDATE);
            $enddate = $this->convert_in_indian_date_format($value->ENDDATE);
            if (($current_date >= $startdate) && ($current_date <= $enddate)) {
                    $storeMemoPromoList[] = (object)[ 'PROMO_CODE' => $value->PROMO_CODE, 'PRIORITY' => $value->PRIORITY, 'STARTDATE'=>$value->STARTDATE,'ENDDATE'=> $value->ENDDATE,'ASSORTMENT_CODE' => $value->ASSORTMENT_CODE, 'NAME' => $value->NAME, 'BASIS' => $value->BASIS, 'NO' => $value->NO ];
            } 
        }

        // dd($storeMemoPromoList);

        if (!empty($storeMemoPromoList)) {
            
            // Get all item details

            foreach ($params['carts'] as $key => $value) {
                // dd($value);
                $itemDet = json_decode($value->section_target_offers);
                $itemDet = urldecode($itemDet->item_det);
                $itemDet = json_decode($itemDet);
                $itemDet->QTY = $value->qty;
                $itemDet->DISCOUNT = $value->discount;
                $itemDet->NETAMT = $value->total;
                $itemDet->UNITMRP = $value->unit_mrp;
                $itemDet->UNITCSP = $value->unit_csp;
                $itemDet->CARTID = $value->cart_id;
                $itemDet->GENERATED = $value->created_at;
                $itemDet->INVHSNSACMAIN_CODE = $itemDet->hsn_code;
                $item[] = $itemDet;
            }

            // dd($itemDet);

            // Checking each promotion

            foreach ($storeMemoPromoList as $key => $value) {
                $promo_list[$value->PROMO_CODE] = [ 'PROMO_CODE' => $value->PROMO_CODE, 'PRIORITY' => $value->PRIORITY, 'start_date' => $value->STARTDATE, 'end_date'=> $value->ENDDATE, 'BASIS' => $value->BASIS, 'NO' => $value->NO, 'NAME' => $value->NAME, 'items' => [] ];
                // dd($value);
                foreach ($item as $item_value) {
                    if (!empty($this->promotionDerivation($value, $item_value))) {
                        $promo_list[$value->PROMO_CODE]['ASSORTMENT_CODE'] = $this->promotionDerivation($value, $item_value);
                        array_push($promo_list[$value->PROMO_CODE]['items'], $item_value);
                    }
                }

            }

            // dd($promo_list);

            // Resolve Each Promo
            $discount = 0;
            if (!empty($promo_list)) {
                
                $pdata =[];
                foreach ($promo_list as $key => $value) {
                    // dd($value['items']);
                    // Split Qty of each item
                    $cartOfSourceItems = [];
                    $total_qty = $netamt = 0;
                    foreach ($value['items'] as $items) {
                        if($items->QTY > 0.00){
                            $netamt = $items->NETAMT / $items->QTY; 
                            $singleDis = $items->DISCOUNT / $items->QTY;
                            $remainQty = $items->QTY;
                            while($remainQty > 0){
                                
                                $single_unit_qty = 0.0;
                                if($remainQty >= 1.0){
                                    $remainQty -= 1.0;
                                    $single_unit_qty = 1.0;
                                }else{
                                    $single_unit_qty = $remainQty;
                                    $remainQty = 0.0;
                                }

                                $cartOfSourceItems[] = (object)[ 'item_id' =>$items->barcode , 'sku_code' =>$items->sku_code , 'unit_mrp' => $items->UNITMRP  
                                    , 'subtotal_unit_mrp' => $items->UNITMRP  * $single_unit_qty
                                    , 'subtotal_unit_rsp' => $items->UNITCSP  * $single_unit_qty
                                    ,'qty' => $single_unit_qty , 'unit_rsp' => $items->UNITCSP , 'netamt' => $netamt * $single_unit_qty, 'discount' => $singleDis , 'bill_discount' => 0 , 'cart_id' => $items->CARTID, 'INVHSNSACMAIN_CODE' => $items->INVHSNSACMAIN_CODE ] ;
                                $items->QTY--;
                                $total_qty+= $single_unit_qty;
                            }
                        }
                    }

                    if($key == 24){

                        //dd($cartOfSourceItems);
                    }
                    
                    $start = 0 ; $end =0;
                    
                    $promotion_applied_flag = false;
                    $total_promo_discount = 0;
                    $item_with_no_promotion = false;
                    if($value['BASIS'] == 'VSLAB'){

                        // $promo_slab = DB::table($this->store_db_name.'.'.$this->table_prefix.'promo_slab')->where('PROMO_CODE', $key)->orderBy('SLAB_CODE', 'asc')->get();
                        $promo_slab = DB::table($this->store_db_name.'.'.$this->table_prefix.'promo_slab')->where('PROMO_CODE', $key)->orderBy('SLAB_RANGE_FROM', 'desc')->get();
                        //dd($promo_slab);
                        
                        foreach ($promo_slab as $key => $slab_value) {
                            $item_with_no_promotion =true;
                            $calculateBy = 'netamt'; //Ginesys column MRP
                          
                            $cartOfSourceItems = collect($cartOfSourceItems);
                            //$totalTempAmount = $cartOfSourceItems->sum($calculateBy);

                            $totalTempAmount = $cartOfSourceItems->sum(function ($product)use($calculateBy) {
                                return $product->$calculateBy * $product->qty;
                            });

                            // if($slab_value->DISCOUNT_PRICE_BASIS == 'L'){
                            //     $calculateBy = 'unit_mrp';
                            // }elseif($slab_value->DISCOUNT_PRICE_BASIS == 'M'){
                            //     $calculateBy = 'unit_rsp';
                            // }
                            $SliceCartOfSourceItems = $cartOfSourceItems;
                            $SliceCartOfSourceItems = $SliceCartOfSourceItems->sortBy('unit_mrp');

                            // if($slab_value->SLAB_RANGE_FROM <= $totalTempAmount &&  $totalTempAmount <= $slab_value->SLAB_RANGE_TO){
                            if($slab_value->SLAB_RANGE_FROM <= $totalTempAmount ){


                                if($slab_value->GET_BENEFIT_CODE == 3){
                                    
                                    
                                    //Getting Get Assortment
                                    $get_all_include_assortment_list = DB::table($this->store_db_name.'.'.$this->table_prefix.'promo_assortment_include')->where('ASSORTMENT_CODE', $slab_value->GET_ASSORTMENT_CODE)->get();
                                    // dd($get_all_include_assortment_list);
                                    $data = [];  

                                    
                                    foreach ($item as $key => $va) {

                                        $va = (array)$va;

                                        foreach ($get_all_include_assortment_list as $assort) {
                                            $sort_data = [ 'assort' => $assort, 'item_value' => $va ];

                                            $code = $this->getAllBarcodeByAssort($sort_data);

                                            if ($code == $va['sku_code']) {
                                                $data[$code] = $code;
                                            }
                                        }
                                    }

                                    

                                    
                                    // dd(array_keys($data));
                                    $getCarts =  collect($item)->whereIn('sku_code', array_keys($data) );
                                    // if($this->v_id == 11){

                                    //     dd($getCartOfSourceItems);
                                    // }

                                    // dd($getCarts);
                                    $getCartOfSourceItems = [];
                                    foreach ($getCarts as $key => $items) {
                                        // if(in_array($items['item_id'] , $allSources) ){
                                            // while($items['qty'] > 0){
                                            //     $getCartOfSourceItems[] = [ 'item_id' =>$items['item_id'] , 'unit_mrp' => $items['unit_mrp']  ,'qty' => 1, 'unit_rsp' => $items['unit_rsp'] ] ;
                                            //     $items['qty']--;
                                            //     $total_qty++;
                                            // }
                                        // }
                                        

                                        $qty = $items->QTY;
                                        $netamt = $items->NETAMT / $items->QTY; 
                                        $singleDis = $items->DISCOUNT / $items->QTY;



                                        while($qty > 0){
                                            $single_unit_qty = 1;
                                            if($qty >= $single_unit_qty  ){
                                                $single_unit_qty = $qty;
                                            }

                                            $getCartOfSourceItems[] = (object)[ 'item_id' =>$items->barcode , 'sku_code' =>$items->sku_code ,  'unit_mrp' => $items->UNITMRP  
                                                , 'subtotal_unit_mrp' => $items->UNITMRP  * $single_unit_qty
                                                , 'subtotal_unit_rsp' => $items->UNITCSP  * $single_unit_qty

                                                ,'qty' => $single_unit_qty , 'unit_rsp' => $items->UNITCSP , 'netamt' => $netamt * $single_unit_qty, 'discount' => $singleDis , 'bill_discount' => 0 , 'cart_id' => $items->CARTID, 'INVHSNSACMAIN_CODE' => $items->INVHSNSACMAIN_CODE ] ;
                                            $qty -= $single_unit_qty;
                                            $total_qty += $single_unit_qty ;
                                        }
                                    }




                                    // dd($SliceCartOfSourceItems);
                                    $SliceCartOfSourceItems = collect($getCartOfSourceItems);
                                    // dd($SliceCartOfSourceItems);
                                    if ($slab_value->GET_FACTOR >= 1) {
                                        $SliceCartOfSourceItems = $SliceCartOfSourceItems->sortBy('unit_mrp');
                                        $SliceCartOfSourceItems = $SliceCartOfSourceItems->slice(0, $slab_value->GET_FACTOR);

                                        if($SliceCartOfSourceItems->count() >= $slab_value->GET_FACTOR){
                                            
                                        }else{
                                            $SliceCartOfSourceItems = collect([]);
                                        }

                                    }
                                    //dd($slab_value);
                                    
                                    

                                }

                                if(!$SliceCartOfSourceItems->isEmpty()){
                                    //dd($SliceCartOfSourceItems);
                                    $promotion_applied_flag = true;
                                    $item_with_no_promotion =false;

                                    $total_price = $SliceCartOfSourceItems->sum(function ($product)use($calculateBy) {
                                        return $product->$calculateBy * $product->qty;
                                    });
                                    $priceArray = $SliceCartOfSourceItems->pluck($calculateBy)->all();
                                    //dd($priceArray);
                                    $ratios = $this->get_offer_amount_by_ratio( $priceArray );
                                    $ratio_total = array_sum($ratios);
                                    
                                    $calParams = [ 'total_price' => $total_price, 'discount_type'=> $slab_value->DISCOUNT_TYPE ,'discount_factor' => $slab_value->DISCOUNT_FACTOR];
                                    //dd($calParams);
                                    $discounts = $this->calculateDiscount($calParams);
                                    //dd($discounts);
                                    $discount = $discounts['discount'];
                                    $total_promo_discount += $discount;
                                    $SliceCartOfSourceItems = $SliceCartOfSourceItems->values();
                                    //distributing discount amount based on ratio to all items
                                    $total_discount =0;
                                    $SliceCartOfSourceItems->transform(function($item , $key) use($ratios, $ratio_total, $discount , &$total_discount){
                                        $discount = round( ($ratios[$key]/$ratio_total) * $discount , 2);
                                        $total_discount += $discount;
                                        // return array_merge($item , [ 'discount' => $discount ] );
                                        $item->bill_discount = $discount;
                                        return $item;
                                    });
                                    //dd($total_discount);
                                    // dd($SliceCartOfSourceItems);
                                    
                                    $total_discount = (string)$total_discount;
                                    $discount = (string)$discount;
                                    //This code is added because facing issue when rounding of discount value
                                    if($total_discount > $discount){
                                        $total_discount = (float)$total_discount;
                                        $discount = (float)$discount;

                                        $total_diff = format_number($total_discount - $discount);
                                        $SliceCartOfSourceItems->transform(function($item, $key)use(&$total_diff){
                                            if($total_diff > 0.00){
                                                $item->bill_discount -= 0.01;
                                                $total_diff -= 0.01;
                                            }
                                            return $item;
                                        });

                                    }else if($total_discount < $discount){
                                        $total_discount = (float)$total_discount;
                                        $discount = (float)$discount;
                                        
                                        $total_diff =  format_number($discount - $total_discount);
                                        $SliceCartOfSourceItems->transform(function($item, $key)use(&$total_diff){
                                            if($total_diff > 0.00){
                                                $item->bill_discount += 0.01;
                                                $total_diff -= 0.01;
                                            }
                                            return $item;
                                        });
                                    }
                                    $curr_item_dis = false;
                                    //dd($SliceCartOfSourceItems);
                                    $currentItems = $SliceCartOfSourceItems;
                                    // dd($value);
                                    foreach ($currentItems as $key => $val) {
                                        //dd($val);
                                        if($val->bill_discount > 0 ) {
                                            $curr_item_dis = true;
                                            //$total_price = $mrp * $val['qty'];
                                            $subTotal = (float)$val->qty * (float)$val->$calculateBy;
                                            $pdata[] =   [ 'item_id' => $val->item_id,'sku_code' => $val->sku_code, 'qty' => $val->qty, 'mrp' => $val->$calculateBy, 'unit_mrp' => $val->unit_mrp,'unit_rsp' => $val->unit_rsp,'discount' => $val->discount, 'bill_discount' => $val->bill_discount ,  'sub_total' => $subTotal, 
                                            // 'total' => $subTotal - ($val->discount + $val->bill_discount),
                                            'total' => $subTotal - $val->bill_discount,
                                             'message' => $value['NAME'], 'discount_price_basis' => $slab_value->DISCOUNT_PRICE_BASIS,'promo_code'=> $value['PROMO_CODE'],'no'=>$value['NO'],'start_date'=>$value['start_date'],'end_date' =>$value['end_date'], 'cart_id' => $val->cart_id, 'hsn' => $val->INVHSNSACMAIN_CODE ];
                                        }
                                    }

                                    //Target Offer Pdata for Get OFFer
                                    if($slab_value->GET_BENEFIT_CODE == 3){
                                        // dd($currentItems);
                                        foreach ($SliceCartOfSourceItems as $key => $val) {
                                            // dd($val);
                                            if($val['discount'] > 0 ){
                                                if(!$curr_item_dis){
                                                    //$total_price = $mrp * $val['qty'];
                                                    $subTotal = (float)$val->qty * (float)$val->$calculateBy;

                                                    $target_pdata[] = [ 'item_id' => $val->item_id,'sku_code' => $val->sku_code, 'qty' => $val->qty, 'mrp' => $val->$calculateBy, 'unit_mrp' => $val->unit_mrp,'unit_rsp' => $val->unit_rsp,'discount' => $val->discount, 'bill_discount' => $val->bill_discount ,  'sub_total' => $subTotal, 'total' => $subTotal - ($val->discount + $val->bill_discount), 'message' => $value['NAME'], 'discount_price_basis' => $slab_value->DISCOUNT_PRICE_BASIS,'promo_code'=> $value['PROMO_CODE'],'no'=>$value['NO'],'start_date'=>$value['start_date'],'end_date' =>$value['end_date'], 'cart_id' => $val->cart_id, 'hsn' => $val->INVHSNSACMAIN_CODE ];
                                                }
                                            }
                                        }

                                    }

                                    // if($this->v_id){
                                    //     dd($pdata);
                                    // }

                                    // return $this->memoFilterPromo($pdata);

                                }

                                if($discount > 0){
                                    break;
                                }

                            }

                        }

                    }

                }
                // dd($pdata);
                if(count($pdata) > 0) {
                    return $this->memoFilterPromo($pdata);
                }

            } else {
                return '';
            }
            // End Resolve Check

        } else {
            return '';
        }
    }

    public function memoFilterPromo($data)
    {
        // dd($data);
        $items = [];
        $data = collect($data);
        $data = $data->groupBy('sku_code');
        // dd($data);
        foreach ($data as $key => $value) {
            $discount = $bill_discount =  $subtotal = $total = $qty =  0;
            foreach ($value as $item) {
                $qty += (float)$item['qty'];
                $discount += (float)$item['discount'];
                $bill_discount += (float)$item['bill_discount'];
                $subtotal += $item['sub_total'];
                $total += $item['total'];
            }
            $items[] = (object)[ 'cart_id' => $value[0]['cart_id'], 'item_id' => $value[0]['item_id'], 'sku_code' => $value[0]['sku_code'], 'unit_mrp' => $value[0]['unit_mrp'], 'unit_rsp' => $value[0]['unit_rsp'], 'qty' => $qty, 'sub_total' => $subtotal, 'discount' => $discount, 'bill_discount' => $bill_discount, 'total' => $total, 'no' => $value[0]['no'], 'promo_code' => $value[0]['promo_code'], 'name' => $value[0]['message'], 'start_date' => $value[0]['start_date'], 'end_date' => $value[0]['end_date'], 'hsn' => $value[0]['hsn'] ];
        }
        return $items;
    }


}
