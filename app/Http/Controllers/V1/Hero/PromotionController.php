<?php

namespace App\Http\Controllers\V1\Hero;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

class PromotionController extends Controller
{

    private $store_db_name;
    
    public function __construct($params = []){

        if(isset($params['store_db_name']) ){
            $this->store_db_name = $params['store_db_name'];
        }else if(isset($params['store_id']) ){
            $this->store_db_name = get_store_db_name( [ 'store_id' => $params['store_id'] ] );
        }
    }
        
    public function process_individual_item($params){

        $barcode = $params['barcode'];
        $qty = $params['qty'];
        $mrp = $params['mrp'];
        $store = $params['store_id'];
        $final_data = [];
        $cart_item = $params['cart_item'];
        $user_id = $params['user_id'];
        $carts = $params['carts'];

        $item_master = $params['item_master'];
        $price_master = $params['price_master'];
        $mrp_arr = $params['mrp_arr'];
        $csp_arr = $params['csp_arr'];
        $mapping_store_id = $params['mapping_store_id'];

        $ru_prdv_data = [];
        $filter_data = [];
        $push_data = [];

        

        // Get Ru Prdv From ITEM, SUBCLASS, DEPARTMENT, GROUP, DIVISON

        $ru_prdv_data = array_collapse([
            $this->get_rule_id($item_master->ITEM, 'item')
        ]);  

        //dd($ru_prdv_data);

        // Get Filter Promotion

        $filter_data = $this->filterPromotionID($ru_prdv_data);

        //dd($filter_data);

        foreach ($filter_data as $key => $value) {
            $spilt = explode("-", $key);

            // Push Multiple Data In Single Array

            if ($spilt[0] == 'BuyNOrMoreOfXGetatUnitPriceTiered') {
                // echo 'BuyNOrMoreOfXGetatUnitPriceTiered<br>';
                $push_data[$spilt[0]][$spilt[1]] = $value;
            } elseif ($spilt[0] == 'BuyNofXforZRs' || $spilt[0] == 'BuyNofXforZ$') {
                // echo 'BuyNofXforZRs<br>';
                $push_data[$spilt[0]][$spilt[1]] = $value;
            } elseif ($spilt[0] == 'BuyNofXforZRsoff' || $spilt[0] == 'BuyNofXforZ$off') {
                // echo 'BuyNofXforZRsoff<br>';
                $push_data[$spilt[0]][$spilt[1]] = $value;
            } elseif ($spilt[0] == 'BuyNofXforZ%off') {
                // echo 'BuyNofXforZ%off<br>';
                $push_data[$spilt[0]][$spilt[1]] = $value;
            } elseif ($spilt[0] == 'BuyNofXgetYatZRs' || $spilt[0] == 'BuyNofXgetYatZ$') {
                 //echo 'BuyNofXgetYatZRs<br>';
                $push_data[$spilt[0]][$spilt[1]] = $value;
            } elseif ($spilt[0] == 'BuyNofXgetYatZ%off') {
                 $push_data[$spilt[0]][$spilt[1]] = $value;
            }elseif ($spilt[0] == 'BuyNofXgetYatZ$off') {
                 $push_data[$spilt[0]][$spilt[1]] = $value;
            }elseif ($spilt[0] == 'Buy$NofXatZ$offTiered') {
                $push_data[$spilt[0]][$spilt[1]] = $value;
                //echo 'Buy$NorMoreOfXgetYatZ$<br>';
            } elseif ($spilt[0] == 'BuyNofXforZ%offTiered') {
                // echo 'BuyNofXforZ%offTiered<br>';
                $push_data[$spilt[0]][$spilt[1]] = $value;
            } elseif ($spilt[0] == 'BuyNofXforZ$offTiered') {
                // echo 'BuyNofXforZ%offTiered<br>';
                $push_data[$spilt[0]][$spilt[1]] = $value;
            }elseif ($spilt[0] == 'BuyNofXforZ$Tiered') {
                // echo 'BuyNofXforZ%offTiered<br>';
                $push_data[$spilt[0]][$spilt[1]] = $value;
            }elseif ($spilt[0] == 'BuyNofXgetLowestPricedXatZ%off') {
                // echo 'BuyNofXforZ%offTiered<br>';
                $push_data[$spilt[0]][$spilt[1]] = $value;
            }elseif ($spilt[0] == 'BuyNofXgetHighestPricedXatZ%off') {
                // echo 'BuyNofXforZ%offTiered<br>';
                $push_data[$spilt[0]][$spilt[1]] = $value;
            }elseif ($spilt[0] == 'BuyNofXgetMofXwithLowestPriceatZ%off') {
                // echo 'BuyNofXforZ%offTiered<br>';
                $push_data[$spilt[0]][$spilt[1]] = $value;
            }elseif ($spilt[0] == 'BuyNofXgetMofXwithLowestPriceatZ%off') {
                // echo 'BuyNofXforZ%offTiered<br>';
                $push_data[$spilt[0]][$spilt[1]] = $value;
            } elseif ($spilt[0] == 'Buy$NorMoreOfXgetYatZ$') {
                echo 'Buy$NorMoreOfXgetYatZ$<br>';
            } elseif ($spilt[0] == 'Buy$NorMoreOfXgetYatZ%off') {
                echo 'Buy$NorMoreOfXgetYatZ%off<br>';
            } elseif ($spilt[0] == 'Buy$NorMoreOfXgetYatZ$') {
                echo 'Buy$NorMoreOfXgetYatZ$<br>';
            }
        }
        
        //dd($push_data);

        foreach ($push_data as $key => $value) {
            if ($key == 'BuyNofXforZ%offTiered') {
                $final_data[] = $this->buy_source_get_percentage_tiered($mrp, $qty, $value, $barcode, $store, $user_id, $cart_item, $carts);
            } elseif ($key == 'BuyNOrMoreOfXGetatUnitPriceTiered') {
                $final_data[] = $this->buy_at_per_kg($mrp, $qty, $value, $barcode, $store, $user_id, $cart_item, $carts);
            } elseif ($key == 'BuyNofXforZRs' || $key == 'BuyNofXforZ$') {
                $final_data[] = $this->buy_source_get_fixed_price($mrp, $qty, $value, $barcode, $store, $user_id, $cart_item, $carts);
            } elseif ($key == 'BuyNofXforZRsoff' || $key == 'BuyNofXforZ$off') {
                $final_data[] = $this->buy_source_get_amount($mrp, $qty, $value, $barcode, $store, $user_id, $cart_item, $carts);
            } elseif ($key == 'BuyNofXforZ%off') {
                $final_data[] = $this->buy_source_get_percentage($mrp, $qty, $value, $barcode, $store, $user_id, $cart_item, $carts);
            } elseif ($key == 'BuyNofXgetYatZRs' || $key == 'BuyNofXgetYatZ$') {

                $final_data[] = $this->buy_source_get_target_fixed_price($mrp, $qty, $value , $barcode, $store, $user_id, $cart_item, $carts);
            }elseif ($key == 'BuyNofXgetYatZ%off') {
                $final_data[] = $this->buy_source_get_target_percentage($mrp, $qty, $value , $barcode, $store, $user_id, $cart_item, $carts);
                
            }elseif ($key == 'BuyNofXgetYatZ$off') {
                $final_data[] = $this->buy_source_get_target_amount($mrp, $qty, $value , $barcode, $store, $user_id, $cart_item, $carts);
                
            }elseif ($key == 'BuyNofXforZ$Tiered' || $key == 'BuyNofXforZRsTiered') {
                //echo 'Inside this';exit;
                $final_data[] = $this->buy_source_get_fixed_price_tiered($mrp, $qty, $value , $barcode, $store, $user_id, $cart_item, $carts);
            }elseif ($key == 'BuyNofXforZ$offTiered') {
                //echo 'Inside this';exit;
                $final_data[] = $this->buy_source_get_amount_tiered($mrp, $qty, $value , $barcode, $store, $user_id, $cart_item, $carts);
            }elseif ($key == 'BuyNofXgetLowestPricedXatZ%off') {
                //echo 'inside this';exit;
                $final_data[] = $this->buy_source_get_lowest_percentage_price($mrp, $qty, $value , $barcode, $store, $user_id, $cart_item, $carts);
            }elseif ($key == 'BuyNofXgetHighestPricedXatZ%off') {
                //echo 'inside this';exit;
                $final_data[] = $this->buy_source_get_higest_percentage_price($mrp, $qty, $value , $barcode, $store, $user_id, $cart_item , $carts);
            }elseif ($key == 'BuyNofXgetMofXwithLowestPriceatZ%off') {
                //echo 'inside this';exit;
                $final_data[] = $this->buy_source_get_source_lowest_percentage_price($mrp, $qty, $value , $barcode, $store, $user_id, $cart_item, $carts);
            }elseif ($key == 'Buy$NofXatZ$offTiered') {
                //echo 'inside this';exit;
                $final_data[] = $this->shop_amount_get_source_amount_tiered($mrp, $qty, $value , $barcode, $store, $user_id , $cart_item , $carts);
            }



        }

        //dd($final_data);

		if(count($final_data) > 1){//Find best offer from the available offers
            $max_discount_price = 0 ;
            $new_final_data = [];
            foreach ($final_data as $key => $f_data) {
                $pdata_dis = 0;
                foreach($f_data['pdata'] as $item){
                    if($item['discount'] != ''){
                        $pdata_dis += $item['discount'];    
                    }
                }
                if($pdata_dis > $max_discount_price){
                    $new_final_data = $f_data;
                    $max_discount_price = $pdata_dis;
                }
            }
            $final_data = $new_final_data;
        }else{
            if(!empty($final_data)){
                $final_data = $final_data[0];    
            }
            
        }

        if(!empty($final_data)){
            if($final_data['pdata'][0]['discount'] < 0){
                $final_data['pdata'][0]['ex_price'] += $final_data['pdata'][0]['discount'];
                $final_data['pdata'][0]['discount'] = 0;
            }
        }

        //dd($final_data); 

        ###### Target Offers for Item Start #####################
        
        $cart_offers = $carts->pluck('target_offer');
        $target_offer = [];
        foreach($cart_offers as $tOffer){
            if($tOffer !='' && !empty($tOffer) ){
                $off = json_decode($tOffer);
                $first_key = key($off);
                $target_offer[$first_key] = $off->$first_key;
            }
        }
        
        //dd($target_offer);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $barcode)->first();
        if(isset($target_offer[$source_item->ITEM])){
            $param = ['carts' => $carts, 'source_item' => $source_item , 'offer' => $target_offer[$source_item->ITEM],
                        'qty' => $qty, 'mrp' =>$mrp ,  'item_id' =>$barcode, 'store_id' => $store , 'user_id' => $user_id , 'price_master' => $price_master ,'cart_item' => $cart_item] ;
            
            if($target_offer[$source_item->ITEM]->promo_type == 'BuyNofXgetYatZ$'){
                $final_data = $this->calculate_target_offer_of_fixed_price($param);
            }elseif($target_offer[$source_item->ITEM]->promo_type == 'BuyNofXgetYatZ%off'){
                $final_data = $this->calculate_target_offer_of_percentage($param);
            }elseif($target_offer[$source_item->ITEM]->promo_type == 'BuyNofXgetYatZ$off'){
                $final_data = $this->calculate_target_offer_of_amount($param);
            }

            
        }


        
            $data['mrp'] =  $mrp_arr;
            $data['csp'] =  $csp_arr;

            $final_data['multiple_price_flag'] =  ( count( $data['mrp']) > 1 )? true:false;
            $final_data['multiple_mrp'] = $data['mrp'];
            $final_data['hsn_code'] = $item_master->HSN;
        

        //dd($target_offers);
        ###### Target Offers for Item ENDS #####################

        //dd($final_data);
        return $final_data;

    }

    public function index($params)
    {
        
        $barcode = $params['product_barcode'];
        $qty = $params['product_qty'];
        $mrp = $params['mrp'];
        $v_id = $params['v_id'];
        $store = $params['store_id'];
        $user_id = $params['c_id'];
        $mapping_store_id = $params['mapping_store_id'];
        $final_data = [];
        $cart_item = false;

        $item_master = $params['item_master'];
        $price_master = $params['price_master'];
        $csp_arr = [];
        $mrp_arr = [];
        $mrp_arrs = array_filter( [ $price_master->MRP1, $price_master->MRP2 , $price_master->MRP3 ]  );
        $csp_arrs = array_filter( [ $price_master->CSP1, $price_master->CSP2 , $price_master->CSP3 ]  );

        foreach ($mrp_arrs as $key => $value) {
            if($value == 0 || $value ===null){

            }else{
                $mrp_arr[] = format_number($value);
            }
        }

        foreach ($csp_arrs as $key => $value) {
            if($value == 0 || $value ===null){

            }else{
                $csp_arr[] = format_number($value);
            }
        }

        $carts = $params['cart'];

        $params = [ 'barcode' => $barcode, 'qty' => $qty, 'mrp' => $mrp, 'v_id' => $v_id , 'store_id' => $store, 'item_master' => $item_master , 'price_master' => $price_master , 'user_id' => $user_id  , 'cart_item' => $cart_item, 'mrp_arr' => $mrp_arr, 'csp_arr' => $csp_arr ,'carts' => $carts , 'mapping_store_id' => $mapping_store_id ];
        $final_data = $this->process_individual_item($params);
        //dd($final_data);

        ##########################################################
        ###### Target Offers for GROUP START #####################
        $section_final_data = [];
        $ru_prdv_data_section = array_collapse([
            $this->get_rule_id($item_master->ID_MRHRC_GP_SUBCLASS, 'subclass'),
            $this->get_rule_id($item_master->ID_MRHRC_GP_PRNT_CLASS, 'printclass'),
            $this->get_rule_id($item_master->ID_MRHRC_GP_PRNT_DEPT, 'department'),
            $this->get_rule_id($item_master->ID_MRHRC_GP_PRNT_GROUP, 'group'),
            $this->get_rule_id($item_master->ID_MRHRC_GP_PRNT_DIVISION, 'division')
        ]);  
        //dd($ru_prdv_data_section);
        $section_data = $this->filterPromotionID($ru_prdv_data_section);
        //dd($section_data);
        $section_push_data=[];
        foreach ($section_data as $key => $value) {
            $spilt = explode("-", $key);
            // Push Multiple Data In Single Array
            if ($spilt[0] == 'BuyRsNOrMoreOfXGetYatZ%OffTiered') {
                $section_push_data[$spilt[0]][$spilt[1]] = $value;
            }elseif ($spilt[0] == 'Buy$NorMoreOfXgetYatZ$') {
                $section_push_data[$spilt[0]][$spilt[1]] = $value;
            }elseif ($spilt[0] == 'Buy$NorMoreOfXgetYatZ%off') {
                $section_push_data[$spilt[0]][$spilt[1]] = $value;
            }elseif ($spilt[0] == 'BuyRsNOrMoreOfXGetYatZRsOffTiered') {
                $section_push_data[$spilt[0]][$spilt[1]] = $value;
            }elseif ($spilt[0] == 'BuyRsNOrMoreOfXGetYatZRsTiered') {
                $section_push_data[$spilt[0]][$spilt[1]] = $value;
            }elseif ($spilt[0] == 'Buy$NorMoreOfXgetYatZ$off') {
                $section_push_data[$spilt[0]][$spilt[1]] = $value;
            }elseif($spilt[0] == 'Buy$NofXatZ%offTiered'){
                $section_push_data[$spilt[0]][$spilt[1]] = $value;
            }


        }

        //-dd($section_push_data);
        foreach ($section_push_data as $key => $value) {
            if ($key == 'BuyRsNOrMoreOfXGetYatZ%OffTiered') {
                //echo 'inside this';exit;
                foreach($value as $k => $val){
                            $senVal = [ $k => $val] ;
                    $section_final_data[] = $this->shop_amount_get_target_percentage_tiered($mrp, $qty, $senVal , $barcode, $store, $user_id , $cart_item, $carts );
                }
            }elseif ($key == 'Buy$NorMoreOfXgetYatZ$') {
                //echo 'inside this';exit;
                foreach($value as $k => $val){
                            $senVal = [ $k => $val] ;
                    $section_final_data[] = $this->shop_amount_get_target_fixed_price($mrp, $qty, $senVal , $barcode, $store, $user_id , $cart_item, $carts );
                }
            }elseif ($key == 'Buy$NorMoreOfXgetYatZ%off') {
                //echo 'inside this';exit;
                foreach($value as $k => $val){
                            $senVal = [ $k => $val] ;
                    $section_final_data[] = $this->shop_amount_get_target_percentage($mrp, $qty, $senVal , $barcode, $store, $user_id , $cart_item, $carts );
                }
            }elseif ($key == 'BuyRsNOrMoreOfXGetYatZRsOffTiered') {
                //echo 'inside this';exit;
                foreach($value as $k => $val){
                            $senVal = [ $k => $val] ;
                    $section_final_data[] = $this->shop_amount_get_target_amount_tiered($mrp, $qty, $senVal , $barcode, $store, $user_id , $cart_item, $carts );
                }
            }elseif ($key == 'BuyRsNOrMoreOfXGetYatZRsTiered') {
                //echo 'inside this';exit;
                foreach($value as $k => $val){
                            $senVal = [ $k => $val] ;
                    $section_final_data[] = $this->shop_amount_get_target_fixed_price_tiered($mrp, $qty, $senVal , $barcode, $store, $user_id , $cart_item, $carts );
                }
            }elseif ($key == 'Buy$NorMoreOfXgetYatZ$off') {
                //echo 'inside this';exit;
                foreach($value as $k => $val){
                            $senVal = [ $k => $val] ;
                    $section_final_data[] = $this->shop_amount_get_target_amount($mrp, $qty, $senVal , $barcode, $store, $user_id , $cart_item, $carts );
                }
            }elseif ($key == 'Buy$NofXatZ%offTiered') {
                //echo 'inside this';exit;
                //dd($value);
                foreach($value as $k => $val){
                            $senVal = [ $k => $val] ;
                    $section_final_data[] = $this->shop_amount_get_percentage_tiered($mrp, $qty, $senVal , $barcode, $store, $user_id , $cart_item, $carts );
                }
            }
        }
        //dd($section_final_data);
        $section_available_offer = [];
        $department=[];
        $department_offer=[];
        $subclass=[];
        $subclass_offer=[];
        $printclass=[];
        $printclass_offer=[];
        $group=[];
        $group_offer=[];
        $division=[];
        $division_offer=[];

        foreach($section_final_data as $key => $value){
            //$section_available_offer[] = $value['available_offer'] ;
            foreach ( $value['available_offer'] as $keys => $va) {
                $section_available_offer[$keys] = $va;
            }
            //dd($section_available_offer);
            //$department[] = $value['section_target']['department'];
            if(isset($value['section_target']['department'])){
                foreach ($value['section_target']['department'] as $key => $value) {
                    if(isset($department[$key])){
                        $department[$key] = array_merge($department[$key], $value);
                    }else{
                        $department[$key] =  $value;
                    }
                    
                }
            }
            if(isset($value['section_offer']['department'])){
                foreach ($value['section_offer']['department'] as $key => $value) {
                    if(isset($department_offer[$key])){
                        $department_offer[$key] = array_merge($department_offer[$key], $value);
                    }else{
                        $department_offer[$key] =  $value;
                    }
                    
                }
            }


            if(isset($value['section_target']['subclass'])){
                foreach ($value['section_target']['subclass'] as $key => $value) {
                    if(isset($subclass[$key])){
                        $subclass[$key] = array_merge($subclass[$key], $value);
                    }else{
                        $subclass[$key] =  $value;
                    }
                    
                }
            }
            if(isset($value['section_offer']['subclass'])){
                foreach ($value['section_offer']['subclass'] as $key => $value) {
                    if(isset($subclass_offer[$key])){
                        $subclass_offer[$key] = array_merge($subclass_offer[$key], $value);
                    }else{
                        $subclass_offer[$key] =  $value;
                    }
                    
                }
            }


            if(isset($value['section_target']['printclass'])){
                foreach ($value['section_target']['printclass'] as $key => $value) {
                    if(isset($printclass[$key])){
                        $printclass[$key] = array_merge($printclass[$key], $value);
                    }else{
                        $printclass[$key] =  $value;
                    }
                    
                }
            }

            if(isset($value['section_offer']['printclass'])){
                foreach ($value['section_offer']['printclass'] as $key => $value) {
                    if(isset($printclass_offer[$key])){
                        $printclass_offer[$key] = array_merge($printclass_offer[$key], $value);
                    }else{
                        $printclass_offer[$key] =  $value;
                    }
                    
                }
            }

            if(isset($value['section_target']['group'])){
                foreach ($value['section_target']['group'] as $key => $value) {
                    if(isset($group[$key])){
                        $group[$key] = array_merge($group[$key], $value);
                    }else{
                        $group[$key] =  $value;
                    }
                    
                }
            }

            if(isset($value['section_offer']['group'])){
                foreach ($value['section_offer']['group'] as $key => $value) {
                    if(isset($group_offer[$key])){
                        $group_offer[$key] = array_merge($group_offer[$key], $value);
                    }else{
                        $group_offer[$key] =  $value;
                    }
                    
                }
            }


            if(isset($value['section_target']['division'])){
                foreach ($value['section_target']['division'] as $key => $value) {
                    if(isset($division[$key])){
                        $division[$key] = array_merge($division[$key], $value);
                    }else{
                        $division[$key] =  $value;
                    }
                    
                }
            }

            if(isset($value['section_offer']['division'])){
                foreach ($value['section_offer']['division'] as $key => $value) {
                    if(isset($division_offer[$key])){
                        $division_offer[$key] = array_merge($division_offer[$key], $value);
                    }else{
                        $division_offer[$key] =  $value;
                    }
                    
                }
            }

        }
        //dd($printclass_offer);

        $section_target['department'] = $department;
        $section_target['subclass'] = $subclass;
        $section_target['printclass'] = $printclass;
        $section_target['group'] = $group;
        $section_target['division'] = $division;

        $section_offers['department'] = $department_offer;
        $section_offers['subclass'] = $subclass_offer;
        $section_offers['printclass'] = $printclass_offer;
        $section_offers['group'] = $group_offer;
        $section_offers['division'] = $division_offer;

        //dd($section_offers);
        $section_target_offer = [];
        $section_total =[];
        $section_target_offer['department']=[];
        $section_target_offer['subclass']=[];
        $section_target_offer['printclass']=[];
        $section_target_offer['group']=[];
        $section_target_offer['division']=[];
        //dd($section_target_offer);
        foreach($carts as $cart){
            $sOffers = $cart->section_target_offers;
            if($sOffers !='' && !empty($sOffers) ){
                $off = json_decode($sOffers);
                //echo '<pre>';print_r($off->department);exit;
                if(!empty($off->department) ){
                    if(isset($section_total['department'][$cart->department_id])){
                        $section_total['department'][$cart->department_id]['total'] += $cart->total;
                    }else{
                        $section_total['department'][$cart->department_id]['total'] = $cart->total;
                        $section_target_offer['department'][$cart->department_id] = $off->department;
                    }
                    
                }
               
                if(!empty($off->subclass) ){
                    if(isset($section_total['subclass'][$cart->subclass_id])){
                        $section_total['subclass'][$cart->subclass_id]['total'] += $cart->total;
                    }else{
                        $section_total['subclass'][$cart->subclass_id]['total'] = $cart->total;
                        $section_target_offer['subclass'][$cart->subclass_id] = $off->subclass;
                        
                    }
                    
                }

                if(!empty($off->printclass) ){
                    if(isset($section_total['printclass'][$cart->printclass_id])){
                        $section_total['printclass'][$cart->printclass_id]['total'] += $cart->total;
                    }else{
                        $section_total['printclass'][$cart->printclass_id]['total'] = $cart->total;
                        $section_target_offer['printclass'][$cart->printclass_id] = $off->printclass;
                        
                    }
                    
                }


                if(!empty($off->group) ){
                    if(isset($section_total['group'][$cart->group_id])){
                        $section_total['group'][$cart->group_id]['total'] += $cart->total;
                    }else{
                        $section_total['group'][$cart->group_id]['total'] = $cart->total;
                        $section_target_offer['group'][$cart->group_id] = $off->group;
                        
                    }
                    
                }


                if(!empty($off->division) ){
                    if(isset($section_total['division'][$cart->division_id])){
                        $section_total['division'][$cart->division_id]['total'] += $cart->total;
                    }else{
                        $section_total['division'][$cart->division_id]['total'] = $cart->total;
                        $section_target_offer['division'][$cart->division_id] = $off->division;
                    }
                    
                }
            }
        }

        //dd($section_target_offer);
        
        //dd($section_target_offer);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $barcode)->first();
        $target_item_list   = $carts->pluck('item_id')->all();
        $target_item_list[] = $source_item->ITEM;
        //dd($target_item_list);
        $s_final_data = [];
        foreach($section_target_offer as $level => $target_offers){
            //$target_offer = (arr$target_offer;
            //dd($target_offers);
            foreach ($target_offers as $section_id => $section_offer) {
                    //dd($target_offer);
                foreach($section_offer as $key => $target_offer){
                    $offer_type = key($target_offer);
                    //dd($offer_type);
                    if(in_array($key , $target_item_list)){
                       // dd($target_offer[$source_item->ITEM]);
                        
                        if($source_item->ITEM == $key){
                            
                           $param = ['carts' => $carts, 'source_item' => $source_item->ITEM , 'offer' => $target_offer->$offer_type,
                                    'qty' => $qty, 'mrp' =>$mrp ,  'store_id' => $store , 'user_id' => $user_id , 'item_desc' => $price_master->ITEM_DESC , 'section_total' => $section_total , 'cart_item' => false] ;
                        }else{
                            $cart_single_item = $carts->where('item_id', $key)->first();

                            //dd($cart_single_item);
                            $param = ['carts' => $carts, 'source_item' => $cart_single_item->item_id , 'offer' => $target_offer->$offer_type,
                                    'qty' =>  $cart_single_item->qty, 'mrp' =>  $cart_single_item->unit_mrp ,  'store_id' => $store , 'user_id' => $user_id , 'item_desc' => $cart_single_item->item_name , 'section_total' => $section_total , 'cart_item' => true  ] ;
                        }
                        
                        
                        if($offer_type == 'BuyRsNOrMoreOfXGetYatZ%OffTiered'){
                            $s_final_data[$level][$section_id][] = $this->calculate_shop_target_offer_of_percentage_tiered($param);
                        }elseif($offer_type == 'Buy$NorMoreOfXgetYatZ$'){
                            $s_final_data[$level][$section_id][] = $this->calculate_shop_target_offer_of_fixed_price($param);
                        }elseif($offer_type == 'Buy$NorMoreOfXgetYatZ%off'){
                            $s_final_data[$level][$section_id][] = $this->calculate_shop_target_offer_of_percentage($param);
                        }elseif($offer_type == 'BuyRsNOrMoreOfXGetYatZRsOffTiered'){
                            $s_final_data[$level][$section_id][] = $this->calculate_shop_target_offer_of_amount_tiered($param);
                        }elseif($offer_type == 'BuyRsNOrMoreOfXGetYatZRsTiered'){
                            $s_final_data[$level][$section_id][] = $this->calculate_shop_target_offer_of_fixed_price_tiered($param);
                        }elseif($offer_type == 'Buy$NorMoreOfXgetYatZ$off'){
                            $s_final_data[$level][$section_id][] = $this->calculate_shop_target_offer_of_amount($param);
                        }

                        
                    }

                }
            }
        }

        //dd($section_total);
        //dd($s_final_data);
        $final_datas = [];
        if(count($s_final_data) > 0 ){
            //Finding the best Section Offers
            foreach ($s_final_data as $level => $levels) {
                foreach ($levels as $section_id => $section) {
                    $best_dis = 0;
                    foreach($section as $key => $final_d){
                        if( $final_d['pdata'][0]['discount'] > $best_dis ){
                            $final_datas[$level][$section_id] = $section[$key];
                            $best_dis = $final_d['pdata'][0]['discount'];
                        }
                    }
                }
            }  
            //dd($s_final_data);
        }
        
        //dd($final_datas);
        //echo $item_master->ID_MRHRC_GP_PRNT_DEPT;exit;
        foreach ($final_datas as $level => $levels) {
            foreach ($levels as $section_id => $section) {
                if( $section['item_id'] == $source_item->ITEM){
                    $final_data = $section;
					$final_data['multiple_price_flag'] =  ( count( $mrp_arrs) > 1 )? true:false;
					$final_data['multiple_mrp'] = $mrp_arrs;
                }
            }
        }

        //dd($section_offers);
        //Section offer without Target
        $s_o_final_data = [];
        foreach($section_offers as $level => $section_offer){
            //$target_offer = (arr$target_offer;
            //dd($target_offers);
            foreach ($section_offer as $section_id => $offers) {
                    //dd($target_offer);
                foreach($offers as $key => $offer){
                    //dd($offer);
                    $offer = json_decode(json_encode($offer));
                    $param = ['carts' => $carts, 'source_item' => $source_item->ITEM , 'offer' => $offer,
                                'qty' => $qty, 'mrp' =>$mrp ,  'store_id' => $store , 'user_id' => $user_id , 'item_desc' => $price_master->ITEM_DESC , 'section_total' => $section_total , 'cart_item' => false] ;
                    
                    if($key == 'Buy$NofXatZ%offTiered'){
                        $s_o_final_data[$level][$section_id][] = $this->calculate_shop_offer_of_percentage_tiered($param);
                    }

                }

            }

        }

        //dd($s_o_final_data);

        $final_datas_s = [];
        if(count($s_o_final_data) > 0 ){
            //Finding the best Section Offers
            foreach ($s_o_final_data as $level => $levels) {
                foreach ($levels as $section_id => $section) {
                    $best_dis = 0;
                    foreach($section as $key => $final_d){
                        
                        if( $final_d['item_id'] == $source_item->ITEM){
                            $final_data = $final_d;
                            $final_data['multiple_price_flag'] =  ( count( $mrp_arrs) > 1 )? true:false;
                            $final_data['multiple_mrp'] = $mrp_arrs;
                        }
                    }
                }
            }  
            //dd($s_final_data);
        }
        //Section offer without Target
        
        //dd($final_datas_s);
        //echo $item_master->ID_MRHRC_GP_PRNT_DEPT;exit;
    
        //dd($final_data);
        ###### Target Offers for GROUP ENDS #####################
        #########################################################

        //If Offer not found
        if(!isset($final_data['pdata'])){
            //echo 'indisde this ';exit;
            $check_product_in_cart = $carts->where('barcode', $barcode)->first();
            if (empty($check_product_in_cart)) {
                $in_cart = 0;
                $qty = $qty;
                $final_qty = $qty;
            } else {
                $in_cart = 1;
                $qty = $qty + $check_product_in_cart->qty;
                $final_qty = $qty;
            }
            //$total_qty = $qty;

			$total = $mrp * $qty;
            $ex_price = $total;
            foreach($mrp_arr as $key => $mr){
                if($mr == $mrp){
                    if(isset($csp_arr[$key]) && $csp_arr[$key] > 0 ){
                       // echo 'finall inside';exit;
                       $ex_price = $csp_arr[$key] * $qty; 
                    }else{
                        $ex_price = $total;
                    }
                    
                }
            }

            $discount = $total - $ex_price ; 
			
            $final_data['pdata'][] = [ 'qty' => $qty, 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total, 'message' => '' , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0];
            $final_data['available_offer'] = [];
            $final_data['applied_offer'] = [];
            $final_data['item_id'] = $price_master->ITEM;


        }else{

            if(empty($final_data['available_offer']) && empty($final_data['applied_offer'])){
                //echo 'inside this';exit;
                foreach($final_data['pdata'] as $key => $pdata){

                    $total = $pdata['mrp'] * $pdata['qty'];
                    $ex_price = $total;
                    foreach($mrp_arr as $key => $mr){
                        if($mr == $mrp){
                            if(isset($csp_arr[$key]) && $csp_arr[$key] > 0 ){
                               // echo 'finall inside';exit;
                               $ex_price = $csp_arr[$key] * $pdata['qty']; 
                            }else{
                                $ex_price = $total;
                            }
                            
                        }
                    }

                    $discount = $total - $ex_price ; 

                    $final_data['pdata'][$key]['ex_price'] = $ex_price;
                    $final_data['pdata'][$key]['total_price'] =  $total;
                    $final_data['pdata'][$key]['discount'] = $discount;
                }

                
            }

        }



       // dd($final_data);
        #1#####################################
        ##### --- BILL BUSTER  START --- #####
        $cart_total = 0;
        $current_item_sum = 0;
        $carts = $carts->where('item_id' ,'!=', $item_master->ITEM);
        $cart_total = $carts->sum('total');
        foreach($final_data['pdata'] as $fData){
            $current_item_sum += $fData['ex_price'];
        }

        $total_amount = $cart_total + $current_item_sum;
        //Bill Buster Calculation
        $ru_prdv_data_bill_buster = $this->get_rule_id($item_master->ITEM, 'billbuster');
        $filter_data_bill_buster = $this->filterPromotionID($ru_prdv_data_bill_buster);
        //dd($filter_data_bill_buster);
        $bill_buster_dis =[];
		$push_data_bill=[];
        foreach ($filter_data_bill_buster as $key => $value) {
            $spilt = explode("-", $key);

            if ($spilt[0] == 'Buy$NorMoreGetZ$offTiered') {
                // echo 'BuyNOrMoreOfXGetatUnitPriceTiered<br>';
                $push_data_bill[$spilt[0]][$spilt[1]] = $value;
            }elseif ($spilt[0] == 'Buy$NorMoreGetZ%offTiered') {
                $push_data_bill[$spilt[0]][$spilt[1]] = $value;
            }elseif ($spilt[0] == 'BuyRsNOrMoreGetPrintedItemFreeTiered') {
                $push_data_bill[$spilt[0]][$spilt[1]] = $value;
            }


        }
        //dd($push_data_bill);
        //echo $total_amount;exit;
        foreach ($push_data_bill as $key => $value) {
            if ($key == 'Buy$NorMoreGetZ$offTiered') {
                $response = $this->shop_bill_get_amount_tiered($total_amount, $qty, $value, $barcode, $store, $user_id);
                if(!empty($response)){
                    $bill_buster_dis[$response['discount']] = $response;
                }
            }elseif($key == 'Buy$NorMoreGetZ%offTiered'){
                $response = $this->shop_bill_get_percentage_tiered($total_amount, $qty, $value, $barcode, $store, $user_id);
                if(!empty($response)){
                    $bill_buster_dis[$response['discount']] = $response;
                }
            }elseif($key == 'BuyRsNOrMoreGetPrintedItemFreeTiered'){
                /*$response = $this->shop_bill_get_printed_tiered($total_amount, $qty, $value, $barcode, $store, $user_id);
                if(!empty($response)){
                    $bill_buster_dis[$response['discount']] = $response;
                }*/
            }
        }


        if(!empty($bill_buster_dis)){
            $max = max( array_keys($bill_buster_dis) );
            $bill_buster_dis = $bill_buster_dis[$max];

            $final_data['applied_offer'][] = $bill_buster_dis['message'];
        }
        ##### --- BILL BUSTER  END --- #####
        ####################################
        //dd($bill_buster_dis);
              

        $final_data['section_target'] = $section_target;
        $final_data['section_offer'] = $section_offers;



        $total_discount = 0;
        $total_mrp = 0;
        $total_amount = 0;
        $total_price = 0;
        $total_qty = 0;
        $is_slab = 0;
        $total_csp = 0;
        $total_tax =0;

        $tax_region = DB::table($this->store_db_name.'.tax_regions')->where('store_id',$mapping_store_id)->first();
        $tax_rates = DB::table($this->store_db_name.'.tax_rate')->where('ID_CTGY_TX', $item_master->TAX_CATEGORY)->where('ID_RN_FM_TX', $tax_region->region_from)->where('ID_RN_TO_TX',$tax_region->region_to)->get();
        foreach ($final_data['pdata'] as $key => $value) {
            
            $total_mrp = $value['mrp'];
            $total_price += $value['total_price'];
            $total_amount += $value['ex_price'];
            $total_discount += (float)$value['discount'];
            $total_qty += $value['qty'];

            $taxes =[];
            foreach ($tax_rates as $tkey => $tax_rate) {

                $taxable_amount = round ( ($value['ex_price'] / $value['qty']) * $tax_rate->TXBL_FCT , 2 );
                $tax = round( ( $taxable_amount / 100  ) * $tax_rate->TX_RT , 2 );
                $taxable_amount = $taxable_amount * $value['qty'];
                $tax = $tax * $value['qty'];
                $taxes[] = [ 'tax_category' => $tax_rate->ID_CTGY_TX , 'tax_desc' => $tax_rate->TX_CD_DSCR , 'tax_code' => $tax_rate->TX_CD , 'tax_rate' => $tax_rate->TX_RT , 'taxable_factor' => $tax_rate->TXBL_FCT , 'taxable_amount' => $taxable_amount , 'tax' => $tax ];
                $total_tax += $tax;
            }

            $final_data['pdata'][$key]['tax'] = $taxes;

            /*foreach($mrp_arr as $key => $mr){
                if($mr == $mrp){
                    if(isset($csp_arr[$key]) && $csp_arr[$key] > 0 ){
                       $total_csp += $csp_arr[$key] * $value['qty']; 
                    }else{
                        $total_csp = $total_price;
                    }
                    
                }
            }*/
            
        } 
        //dd($final_data);
        //dd(array_values($final_data['available_offer']));
        //dd($final_data);

        //if(!empty($final_data['available_offer']) ){
            $final_data['r_price'] = $total_price ;
            $final_data['s_price'] = $total_amount ;

        /*}else{

            $final_data['r_price'] = $total_price ;
            $final_data['s_price'] = $total_csp ;

        }*/

        //dd($final_data);

        $available_offer = [];
        foreach($final_data['available_offer'] as $key => $value){

            $available_offer[] =  ['message' => $value ];
        }
        $final_data['available_offer'] = $available_offer;
        $applied_offer = [];
        foreach($final_data['applied_offer'] as $key => $value){

            $applied_offer[] =  ['message' => $value ];
        }
        $final_data['applied_offer'] = $applied_offer;

        //dd($final_data);


        $final_data['total_qty']= $total_qty;
        $final_data['total_discount'] = $total_discount;
        $final_data['total_tax'] = $total_tax;
        

        //dd($final_data);
        return $final_data;

    }

    public function get_rule_id($id, $type)
    {

        $data = [];
        $final_data = [];
        $today = time();
        if($type == 'billbuster'){

            $bill_buster = [ 
                'Buy$NorMoreGetZ%offTiered',
                'Buy$NorMoreGetZ$offTiered',
                'BuyRsNOrMoreGetPrintedItemFreeTiered'
            ]; 


            $ru_prdvs = DB::table($this->store_db_name.'.ru_prdv')->select('ID_RU_PRDV','SC_RU_PRDV','TY_RU_PRDV','CD_MTH_PRDV','DE_RU_PRDV','DC_RU_PRDV_EP','DC_RU_PRDV_EF','MAX_ALL_SOURCES','ITM_PRC_CTGY_SRC','MAX_FREE_ITEM','MO_TH_SRC','MAX_PARENT_ID','ID_PRM')->whereIn('DE_RU_PRDV', $bill_buster)->where('MO_TH_SRC','>','0')->orderBy('TS_CRT_RCRD')->get();
            //dd($get_promo_details);
            foreach($ru_prdvs as $get_promo_details){

                $startdate = date_create_from_format('d-M-y h.i.s A' ,$get_promo_details->DC_RU_PRDV_EF );
                $startdate = $startdate->getTimestamp();
                $enddate = date_create_from_format('d-M-y h.i.s A' ,$get_promo_details->DC_RU_PRDV_EP );
                $enddate = $enddate->getTimestamp();
                    
                if (($today >= $startdate) && ($today <= $enddate)) {
                    array_push($final_data, [ 'ru_prdv' => $get_promo_details->ID_RU_PRDV, 'mo_th' => $get_promo_details->MO_TH_SRC, 'type' => 'bill_buster', 'level' => $type, 'promo_type' => $get_promo_details->DE_RU_PRDV, 'promo_id' => $get_promo_details->ID_PRM ,'max_free_item' => $get_promo_details->MAX_FREE_ITEM ]);
                }

            }

            //Removing duplicate offers Based on amount an promo_type
            $tempOffers = $final_data;
            for($i=0; $i<count($final_data); $i++){
                
                for($j=$i+1; $j<count($final_data); $j++){
                    
                    if( ($tempOffers[$i]['mo_th'] == $final_data[$j]['mo_th']) &&  ($tempOffers[$i]['promo_type'] == $final_data[$j]['promo_type'])){
                        unset($final_data[$i]);
                    }

                }
            }


            //dd($final_data);

        }else{

            $list_grp_id = DB::table($this->store_db_name.'.max_grp_itm_lst')->select('ID_GRP')->where('ID_ITM', $id)->orderBy('ID_GRP','desc')->get()->pluck('ID_GRP');

            $max_co_el_prdv_itm_grp = DB::table($this->store_db_name.'.max_co_el_prdv_itm_grp')->select('MO_TH','QU_TH','ID_RU_PRDV','ID_GRP')->whereIn('ID_GRP', $list_grp_id)->get();

            foreach ($max_co_el_prdv_itm_grp as $key => $value) {
                array_push($data, [ 'ru_prdv' => $value->ID_RU_PRDV, 'mo_th' => $value->MO_TH, 'qu_th' => $value->QU_TH, 'type' => 'max', 'id' => $value->ID_GRP, 'level' => $type ]);
            }

            if ($type == 'item') {
                
                $co_el_prdv_itm = DB::table($this->store_db_name.'.co_el_prdv_itm')->select('MO_TH','QU_TH','ID_RU_PRDV','ID_ITM')->where('ID_ITM', $id)->get();

                if (count($co_el_prdv_itm) > 0) {
                    foreach ($co_el_prdv_itm as $key => $value) {
                        array_push($data, [ 'ru_prdv' => $value->ID_RU_PRDV, 'mo_th' => $value->MO_TH, 'qu_th' => $value->QU_TH, 'type' => 'co_el', 'id' => $value->ID_ITM, 'level' => $type ]);
                    }
                }

            }

            //dd($data);
            $rule_data_type = [];
            // echo '<br/>';
            foreach ($data as $key => $value) {
                
                $get_promo_details = DB::table($this->store_db_name.'.ru_prdv')->select('ID_STR_RT','TY_RU_PRDV','DE_RU_PRDV','MAX_ALL_SOURCES','ITM_PRC_CTGY_SRC','MAX_FREE_ITEM','CD_BAS_CMP_SRC','CD_BAS_CMP_TGT','DC_RU_PRDV_EF','DC_RU_PRDV_EP','ID_PRM','ID_RU_PRDV','CD_MTH_PRDV','MAX_ALL_TARGETS')->where('ID_RU_PRDV', $value['ru_prdv'])->orderBy('TS_CRT_RCRD')->first();
                if($get_promo_details){

                    $startdate = date_create_from_format('d-M-y h.i.s A' ,$get_promo_details->DC_RU_PRDV_EF );
                    $startdate = $startdate->getTimestamp();
                    $enddate = date_create_from_format('d-M-y h.i.s A' ,$get_promo_details->DC_RU_PRDV_EP );
                    $enddate = $enddate->getTimestamp();
                        
                    if (($today >= $startdate) && ($today <= $enddate)) {
                        array_push($final_data, [ 'ru_prdv' => $value['ru_prdv'], 'mo_th' => $value['mo_th'], 'qu_th' => $value['qu_th'], 'type' => $value['type'], 'id' => $value['id'], 'level' => $value['level'], 'promo_type' => $get_promo_details->DE_RU_PRDV, 'promo_id' => $get_promo_details->ID_PRM, 'cd_bas_cmp_tgt' => $get_promo_details->CD_BAS_CMP_TGT ]);
                    }

                }

            }


        }

        return $final_data;
    }

    public function filterPromotionID($array)  
    {
        $data = [];
        $filter_data = [];
        $data = array_unique(array_column($array, 'promo_id'));
        foreach ($array as $key => $value) {
            if (in_array($value['promo_id'], $data)) {
                $filter_data[$value['promo_type'].'-'.$value['promo_id']][] = $value;
            }
        }

        return $filter_data;
    }

    public function buy_at_per_kg($mrp, $qty, $ru_prdv, $item_id, $store_id, $user_id, $cart_item= false ,$carts)
    {
        //dd($ru_prdv);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $item_id)->first();
        $data = [];
        $final_data = [];
        $final_product = [];
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                if ($value['qu_th'] != 0.00) {
                    $condition = DB::table($this->store_db_name.'.co_prdv_itm')->select('PNT_PRC_UN_ITM_PRDV_SLS')->where('ID_RU_PRDV', $value['ru_prdv'])->first();
                    $total_saving = $mrp - $condition->PNT_PRC_UN_ITM_PRDV_SLS;
                    $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'] ];
                }
            }
        }

        // dd($data);

        // Find Max Discount

        $max_discount = max(array_keys($data));

        // Check Quantity Based Condition

        if ($data[$max_discount]['TYPE'] == 'max') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH', 'co_prdv_itm.PNT_PRC_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV','max_grp_itm_lst.ID_ITM')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }

        if ($data[$max_discount]['TYPE'] == 'co_el') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'co_el_prdv_itm.MO_TH', 'co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM', 'ru_prdv.CD_MTH_PRDV', 'co_prdv_itm.PNT_PRC_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }
            // dd($list_ru_prdv);

            // dd();

            if (count($list_ru_prdv) == 1) {
                
                $gram_rate = $list_ru_prdv[0]->PNT_PRC_UN_ITM_PRDV_SLS / 100;
                $ex_price = $qty * 100; 
                $ex_price = $ex_price * $gram_rate;
                $total_price = $mrp * $qty;
                $discount = $total_price - $ex_price;
                $message = 'buy 1 kg for Rs. '.$list_ru_prdv[0]->PNT_PRC_UN_ITM_PRDV_SLS;
                $final_data['pdata'][] = [ 'qty' => $qty, 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message , 'ru_prdv' => $list_ru_prdv[0]->ID_RU_PRDV, 'type' => 'max', 'type_id' => $list_ru_prdv[0]->ID_GRP, 'promo_id' => $list_ru_prdv[0]->ID_PRM, 'is_slab' => 1, 'is_promo' => 1 ];

                $applied_offer[] = $message;

            } else {
                
                // Get Max Number of Qty

                $max = 0;
                foreach($list_ru_prdv as $obj) {
                    if($obj->QU_TH > $max) {
                        $max = $obj->QU_TH;
                    }
                }

                // Format Qty to integer

                $quantity_collection = [];

                foreach ($list_ru_prdv as $key => $value) {
                    $quantity_collection[] = $value->QU_TH;
                }

                // Qty Reduction Function

                $qty = $qty / 1000;

                while ($qty > 0) {
                    if (in_array($qty, $quantity_collection)) {
                        // echo 'FIND Qty :- '.$qty.'<br>';
                        $qty = $qty - $qty;
                        array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                    } else {
                        if ($qty > $max) {
                            // echo 'LARGE Qty :- '.$max.'<br>';
                            $qty = $qty - $max;
                            array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                        } else {
                            array_push($final_product,[ 'qty' => $qty, 'is_promo' => 0 ]);
                            $qty = 0;
                        }
                    }
                }

            }

        

        // Available Offer

            $available_offer = [];

            foreach ($list_ru_prdv as $key => $value) {
                $available_offer[number_format($value->QU_TH)] = 'buy 1 kg for Rs. '.$value->PNT_PRC_UN_ITM_PRDV_SLS;
            }

            $final_data['available_offer'] = $available_offer;

            // dd($final_qty);

            // Applied Offer

            $final_data['applied_offer'] = $applied_offer;
        $final_data['item_id']  = $source_item->ITEM;
        // dd($final_data);
        return $final_data;
    }

    public function buy_source_get_fixed_price($mrp, $qty, $ru_prdv, $item_id, $store_id, $user_id, $cart_item= false, $carts)
    {
		
        //dd($ru_prdv);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $item_id)->first();
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }
        
        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }
        $total_qty = $qty;
        // dd($total_qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                $condition = DB::table($this->store_db_name.'.co_prdv_itm')->select('PNT_PRC_UN_ITM_PRDV_SLS')->where('ID_RU_PRDV', $value['ru_prdv'])->first();
                $total_saving = ($mrp * $value['qu_th']) -$condition->PNT_PRC_UN_ITM_PRDV_SLS;
                $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'], 'ID' => $value['id'] , 'QU_TH' => $value['qu_th']];
            }
        }
        
        $f_data = [];
        foreach($data as $k =>  $v){
            $f_data[$v['TYPE']][$v['QU_TH']][$k] = $v;
        }
        //dd($f_data);

        // Find Max Discount

        $id_prm_arr = [];
        foreach($f_data as $type => $f_dat){
            foreach($f_dat as $d){
                $max_discount = max(array_keys($d));    
                $id_prm_arr[$type][] = $d[$max_discount]['ID_PRM'];
            }
        } 
        //dd($id_prm_arr);

        // Check Quantity Based Condition
       
        if (isset($id_prm_arr['max'])) {
            $list_ru_prdvs[] = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH', 'co_prdv_itm.PNT_PRC_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV','max_grp_itm_lst.ID_ITM')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->whereIn('ru_prdv.ID_PRM', $id_prm_arr['max'])
                            //->where('max_grp_itm_lst.ID_ITM', $source_item->ITEM)
                            ->get();
        }
        //dd($list_ru_prdvs);
        if (isset($id_prm_arr['co_el'])) {
            $list_ru_prdvs[] = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'co_el_prdv_itm.MO_TH', 'co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM', 'ru_prdv.CD_MTH_PRDV', 'co_prdv_itm.PNT_PRC_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->whereIn('ru_prdv.ID_PRM', $id_prm_arr['co_el'])
                            //->where('co_el_prdv_itm.ID_ITM', $source_item->ITEM)
                            ->get();
        }

        //dd($list_ru_prdvs);
        $final_data_arr = null;
        foreach($list_ru_prdvs as  $list_ru_prdv){

            //dd($list_ru_prdv);
            $all_list_ru_prdv = $list_ru_prdv;
           // $all_sources = array_unique($list_ru_prdv->pluck('ID_ITM')->all() );
            $list_ru_prdv = $list_ru_prdv->where('ID_ITM', $source_item->ITEM);
            //dd($all_sources);
            //dd($list_ru_prdv);
            $promo_count = count($list_ru_prdv);

            // Get Max Number of Qty

            $max = 0;
            foreach($list_ru_prdv as $obj) {
                if($obj->QU_TH > $max) {
                    $max = number_format($obj->QU_TH);
                }
            }

            // Formart Qty to integer
            //dd($list_ru_prdv);
            $quantity_collection = [];
            $quantity_discount_collection = [];
            $add_qty = 1;
            $cartItems =[];
            $final_product=[];
            $final_data=[];
            $total_qty = $qty;
            foreach ($list_ru_prdv as $key => $value) {
                
                /*if($value->ID_RU_PRDV == '62313'){
                    continue;
                }*/
                //dd($value);
                //$single_list_ru_prdv = $value;
                $single_list_ru_prdv = $all_list_ru_prdv->where('ID_RU_PRDV', $value->ID_RU_PRDV);
                $all_sources = array_unique($single_list_ru_prdv->pluck('ID_ITM')->all() );

                $all_sources_check = true;
                if($value->ITM_PRC_CTGY_SRC == 'allSources' || $value->MAX_ALL_SOURCES == 'allSources'){
                    
                    //dd($all_sources);
                    $cart_item_list = $carts->pluck('item_id')->all();
                    $cart_item_list[] = $source_item->ITEM; 
                    $cart_item_list  = array_unique($cart_item_list);
                    //dd($cart_item_list);
                    $result = (count($all_sources)==count(array_intersect($all_sources, $cart_item_list)) );
                    if($result){
                        //echo 'inside up if';exit;
                        $source_cart_item_list = $carts->whereIn('item_id', $all_sources);
                        //$source_sum = $source_cart_item_list->sum('qty');
                        $cart_item_list = $source_cart_item_list->pluck('qty','item_id')->all();
                        if(!$cart_item){
                            if(isset($cart_item_list[$source_item->ITEM])){
                                $cart_item_list[$source_item->ITEM]++;      
                            }else{
                                $cart_item_list[$source_item->ITEM] = $qty;
                            }
                           
                        }
                        //dd($all_list_ru_prdv);
                        foreach($all_sources as $sources){
                            if(isset($cart_item_list[$sources]) ){
                                $current_rule = $all_list_ru_prdv->where('ID_ITM', $sources)->where('ID_RU_PRDV', $value->ID_RU_PRDV)->first();
                                if( $cart_item_list[$sources] >= $current_rule->QU_TH ){

                                    /*$cartItem = $carts->where('item_id', $sources)->first();
                                    $loopQty = $current_rule->QU_TH;
                                    while($loopQty > 0){
                                        $cartItems[] = ['item_id' => $cartItem->item_id ,'qty' => 1 , 'unit_mrp' => $cartItem->unit_mrp];
                                        $loopQty--;
                                    }*/

                                }else{
                                   $all_sources_check = false; 
                                   break;
                                }
                            }else{
                                $all_sources_check = false; 
                                break;
                            }

                        }
                
                    }else{
                        $all_sources_check = false;
                    }

                }

                if( $all_sources_check == true){
                    
                    $qty_col = number_format($value->QU_TH);
                    $fixed_price = $value->PNT_PRC_UN_ITM_PRDV_SLS;
                    //This condition is added to find the best discount for same qty
                    if(isset($quantity_discount_collection[$qty_col])){

                        if($quantity_discount_collection[$qty_col] > $fixed_price){
                            $quantity_discount_collection[$qty_col] = $fixed_price;
                        }else{
                            unset($list_ru_prdv[$key]);
                        }
                        
                    }else{
                        $quantity_discount_collection[$qty_col] = $fixed_price;    
                    }
                    //dd($cartItems);
                }

                $quantity_collection = array_keys($quantity_discount_collection);
                //Condition for all sources

            }

            //dd($quantity_collection);
            // Qty Reduction Function
            if( count($quantity_collection) == 0){
                
                array_push($final_product,[ 'qty' => $total_qty, 'is_promo' => 0 ]);
            }else{
                //dd($quantity_collection);
                while ($total_qty > 0) {
                
                    if (in_array($total_qty, $quantity_collection)) {
                        // echo 'FIND Qty :- '.$qty.'<br>'; 
                        array_push($final_product,[ 'qty' => $total_qty, 'is_promo' => 1 ]);
                        $total_qty = $total_qty - $total_qty;
                    } else {
                        //This condtion is added to get best Offers
                        $combination = $this->find_combination($quantity_collection, $total_qty);
                        //dd($combination);
                        if(!empty($combination)){
                            $best_combi =['discount' => 0 , 'final_product' => [] ];
                            
                            foreach($combination as $key => $combi){
                                
                                $temp_qty = $total_qty;
                                $best_final_product= [];
                                $total_price = 0;
                                $ex_price = 0;
                                $discount = 0;
                                //$source_cart_item_list = $carts->whereIn('item_id', $all_sources);
                                //$source_sum = $source_cart_item_list->sum('qty');
                                $cart_item_list = $carts->pluck('qty','item_id')->all();
                                if(!$cart_item){
                                  $cart_item_list[$source_item->ITEM]++;
                                }
                                //dd($cart_item_list);

                                $total_combi_dis = 0;
                                foreach($combi as $combi_qty){
                                    $temp_qty = $temp_qty - $combi_qty;
                                    $id = $this->searchQty($combi_qty / $add_qty, $list_ru_prdv);

                                    $mrp_on_offer = $mrp;
                                    $param = [];
                                    $params = [];
                                    //echo $cart_source_qty;exit;

                                    $single_list_ru_prdv = $all_list_ru_prdv->where('ID_RU_PRDV', $id->ID_RU_PRDV);
                                    $all_sources = array_unique($single_list_ru_prdv->pluck('ID_ITM')->all() );
                                    //dd($all_sources);
                                    //dd($single_list_ru_prdv);
                                    if(count($all_sources) == count(array_intersect($all_sources, array_keys($cart_item_list))) )
                                    {
                                        //dd($cart_item_list);
                                        foreach($all_sources as $sources){
                                            $cart_item_list[$sources]--;

                                            if($cart_item_list[$sources] == 0){
                                                unset($cart_item_list[$sources]);
                                            }

                                            $cartItem = $carts->where('item_id', $sources)->first();
                                            if($cartItem){
                                                $single_source = $single_list_ru_prdv->where('ID_ITM', $sources)->first();
                                                $loopQty = $single_source->QU_TH;
                                                while($loopQty > 0){
                                                    $cartItems[] = ['item_id' => $cartItem->item_id ,'qty' => 1 , 'unit_mrp' => $cartItem->unit_mrp];
                                                    $loopQty--;
                                                } 
                                            }else{
                                                if(!$cart_item){
                                                    $cartItems[] = ['item_id' => $sources ,'qty' => 1 , 'unit_mrp' => $mrp];
                                                }
                                            }   
                                        }

                                        $cartItems = collect($cartItems)->sortByDesc('unit_mrp');
                                        //dd($cartItems);
                                        $temp_combi_qty = $combi_qty;
                                        $cartItems = $cartItems->filter(function($q , $key) use (&$temp_combi_qty, &$param, &$params, &$cart_source_total, &$cart_source_qty) {
                                           
                                            //while($value['qty']> 0){

                                                $cart_source_total += $q['unit_mrp'];
                                                $params[] = [ 'item_id' => $q['item_id'], 'unit_mrp' => $q['unit_mrp'] ];
                                                $param[] = $q['unit_mrp'];

                                                $cart_source_qty += $q['qty'];

                                                $temp_combi_qty--;
                                                return null;
                                           // }
                                                  
                                        });
                                        //dd($param);
                                        $param_count_of_cart = count($param);

                                        $offer_amount = $cart_source_total - $id->PNT_PRC_UN_ITM_PRDV_SLS;
                                        //dd($cart_source_total);
                                        $ratio_val = $this->get_offer_amount_by_ratio($param, $offer_amount);
                                        //dd($ratio_val);
                                        $ratio_total = array_sum($ratio_val);
                                        //dd($ratio_val);
                                        $discount = 0;
                                        //$total_discount = 0;
                                        foreach($params as $key => $par){
                                            $discount = round( ($ratio_val[$key]/$ratio_total) * $offer_amount , 2);
                                            $params[$key]['discount'] =  $discount;
                                            //$total_discount += $discount;
                                        }
                                        //dd($params);
                                        foreach($params as $key => $par){
                                            if($par['item_id'] == $source_item->ITEM ){
                                                //$total_price += $par['unit_mrp'] * 1;
                                                //$total_qty += 1;
                                                $discount += $par['discount'];
                                                //$mrp = $par['unit_mrp'];
                                            }
                                        }
                                        
                                        array_push($best_final_product,[ 'qty' => $combi_qty, 'is_promo' => 1 ]);
                                    }else{
                                        array_push($best_final_product,[ 'qty' => $combi_qty, 'is_promo' => 0 ]);
                                    }
                                    $total_combi_dis += $discount;
                                    
                
                                }
                                if($temp_qty > 0 ){
                                    array_push($best_final_product,[ 'qty' => $temp_qty, 'is_promo' => 0 ]);
                                }
                                
                                if($best_combi['discount'] < $total_combi_dis){
                                    $best_combi['discount'] = $total_combi_dis;
                                    $best_combi['final_product'] = $best_final_product;
                                }
                            }
                            //exit;
                            //dd($best_combi);

                            $final_product = $best_combi['final_product'] ; 
                        }else{
                            array_push($final_product,[ 'qty' => $total_qty, 'is_promo' => 0 ]);
                        } 
                        $total_qty = 0;                
                    }

                    $final_data_arr[] = $final_data;

                }
            }
           
            //$cartItems = collect($cartItems)->sortByDesc('unit_mrp');
            //dd($cartItems);
            //dd($final_product);
            //dd($list_ru_prdv);
            
            $cart_source_qty = 0;
            foreach ($final_product as $key => $value) {
                if ($value['is_promo'] == 1) {
                    $cart_source_total = 0;
                    $id = $this->searchQty($value['qty'], $list_ru_prdv);
                    // echo '<pre>';
                    // dd($id);
                    //echo $offer_amount = $id->PNT_PRC_UN_ITM_PRDV_SLS;exit;
                    $mrp_on_offer = $mrp;
                    $param = [];
                    $params = [];
                    //echo $cart_source_qty;exit;

                    $single_list_ru_prdv = $all_list_ru_prdv->where('ID_RU_PRDV', $id->ID_RU_PRDV);
                    $all_sources = array_unique($single_list_ru_prdv->pluck('ID_ITM')->all() );
                    //dd($single_list_ru_prdv);
                    foreach($all_sources as $sources){
                        $cartItem = $carts->where('item_id', $sources)->first();
                        if($cartItem){
                            $single_source = $single_list_ru_prdv->where('ID_ITM', $sources)->first();
                            $loopQty = $single_source->QU_TH;
                            while($loopQty > 0){
                                $cartItems[] = ['item_id' => $cartItem->item_id ,'qty' => 1 , 'unit_mrp' => $cartItem->unit_mrp];
                                $loopQty--;
                            } 
                        }else{
                            if(!$cart_item){
                                $cartItems[] = ['item_id' => $sources ,'qty' => 1 , 'unit_mrp' => $mrp];
                            }
                        }   
                    }
                    $cartItems = collect($cartItems)->sortByDesc('unit_mrp');
                    
                    //dd($cartItems);
                    $cartItems = $cartItems->filter(function($q , $key) use (&$value, &$param, &$params, &$cart_source_total, &$cart_source_qty) {
                       
                        //while($value['qty']> 0){

                            $cart_source_total += $q['unit_mrp'];
                            $params[] = [ 'item_id' => $q['item_id'], 'unit_mrp' => $q['unit_mrp'] ];
                            $param[] = $q['unit_mrp'];

                            $cart_source_qty += $q['qty'];

                            $value['qty']--;
                            return null;
                       // }
                            
                        
                    });

                    //dd($param);

                    $param_count_of_cart = count($param);

                    $offer_amount = $cart_source_total - $id->PNT_PRC_UN_ITM_PRDV_SLS;
					//dd($cart_source_total);
                    $ratio_val = $this->get_offer_amount_by_ratio($param, $offer_amount);
                    //dd($ratio_val);
                    $ratio_total = array_sum($ratio_val);
                    //dd($ratio_val);
                    
                    $discount = 0;
                    $total_discount = 0;
                    foreach($params as $key => $par){
                        $discount = round( ($ratio_val[$key]/$ratio_total) * $offer_amount , 2);
                        $params[$key]['discount'] =  $discount;
                        $total_discount += $discount;
                    }

                    //Thid code is added because facing issue when rounding of discount value
                    if($total_discount > $offer_amount){
                        $total_diff = $total_discount - $offer_amount;
                        foreach($params as $key => $par){
                            if($total_diff > 0.00){
                                $params[$key]['discount'] -= 0.01;
                                $total_diff -= 0.01;
                            }else{
                                break;
                            }
                        }
                    }else if($total_discount < $offer_amount){
                        $total_diff =  $offer_amount - $total_discount;
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
                    $total_qty = 0;
                    $total_price = 0;
                    $discount =0;
                    foreach($params as $key => $par){
                        if($par['item_id'] == $source_item->ITEM ){
                            $total_price += $par['unit_mrp'] * 1;
                            $total_qty += 1;
                            $discount += $par['discount'];
                            $mrp = $par['unit_mrp'];
                        }
                    }

                    $discount = round($discount ,2);
                    //$total_price = $mrp * $value['qty'];
                    //$discount = $offer_amount;
                    $ex_price = $total_price - $discount;                 
                    
                    $message = 'buy '.$id->QU_TH.' for Rs. '.$id->PNT_PRC_UN_ITM_PRDV_SLS; 
                    if($total_qty !=0){
                       $final_data['pdata'][] = [ 'qty' => $total_qty, 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message , 'ru_prdv' => $id->ID_RU_PRDV, 'type' => $data[$max_discount]['TYPE'], 'promo_id' => $data[$max_discount]['ID_PRM'], 'type_id' => $data[$max_discount]['ID'], 'is_slab' => ($promo_count == 1 ? 0 : 2), 'is_promo' => 1 ];
                    }

                    $final_data['applied_offer'][] = $message;

                } else {

                    $total_price = $value['qty'] * $mrp;
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => '', 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];

                    
                }
            }
            //dd($final_data);
            $final_data_arr[] = $final_data;
            $final_data = [];

        }
            //dd($final_data_arr);
        if(count($final_data_arr) > 1){//Find best offer from the available offers
            $max_discount_price = 0 ;
            $new_final_data = [];
            foreach ($final_data_arr as $key => $f_data) {
                if(empty($f_data)){
                    continue;
                }
                //dd($f_data);
                $pdata_dis = 0;
                foreach($f_data['pdata'] as $item){
                    if($item['discount'] != ''){
                        $pdata_dis += $item['discount'];    
                    }
                }
                if($pdata_dis < 0){
                    $pdata_dis = 0;
                }
                if($pdata_dis >= $max_discount_price){
                    $new_final_data = $f_data;
                    $max_discount_price = $pdata_dis;
                }
            }
            $final_data = $new_final_data;
        }else{
            if(!empty($final_data_arr)){
                $final_data = $final_data_arr[0];    
            }
            
        }
        //dd($final_data);
        $applied_offer = isset($final_data['applied_offer'])?$final_data['applied_offer']:[];
        unset($final_data['applied_offer']);
			//dd($final_data);
            // Check item Already Exits in Cart

            if ($in_cart == 1) {
                $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
            }

            // Available Offer

            $available_offer = [];

            foreach ($list_ru_prdv as $key => $value) {
                $available_offer[number_format($value->QU_TH)] = 'buy '.number_format($value->QU_TH).' for Rs. '.$value->PNT_PRC_UN_ITM_PRDV_SLS;
            }

            $final_data['available_offer'] = $available_offer;

            // dd($final_qty);

            // Applied Offer

            $final_data['applied_offer'] = $applied_offer;

        $final_data['item_id']  = $source_item->ITEM;
        return $final_data;
    }

    public function buy_source_get_amount($mrp, $qty, $ru_prdv, $item_id, $store_id, $user_id, $cart_item= false, $carts)
    {
        // dd($ru_prdv);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $item_id)->first();
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }
        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }
        // dd($qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                $condition = DB::table($this->store_db_name.'.co_prdv_itm')->select('MO_UN_ITM_PRDV_SLS')->where('ID_RU_PRDV', $value['ru_prdv'])->first();
                $total_saving = $condition->MO_UN_ITM_PRDV_SLS;
                $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'], 'ID' => $value['id'], 'QU_TH' => $value['qu_th'] ];
            }
        }
        // dd($data);

        // Find Max Discount

        $f_data = [];
        foreach($data as $k =>  $v){
            $f_data[$v['QU_TH']][$k] = $v;
        }
        //dd($f_data);

        // Find Max Discount

        $id_prm_arr = [];
        foreach($f_data as $d){
            $max_discount = max(array_keys($d));    
            $id_prm_arr[] = $d[$max_discount]['ID_PRM'];
        }

        // Check Quantity Based Condition

        if ($data[$max_discount]['TYPE'] == 'max') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH', 'co_prdv_itm.MO_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV','max_grp_itm_lst.ID_ITM')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->whereIn('ru_prdv.ID_PRM', $id_prm_arr)
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }

        if ($data[$max_discount]['TYPE'] == 'co_el') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'co_el_prdv_itm.MO_TH', 'co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM', 'ru_prdv.CD_MTH_PRDV', 'co_prdv_itm.MO_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->whereIn('ru_prdv.ID_PRM', $id_prm_arr)
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }                
            // dd($list_ru_prdv);

            $promo_count = count($list_ru_prdv);

            // Get Max Number of Qty

            $max = 0;
            foreach($list_ru_prdv as $obj) {
                if($obj->QU_TH > $max) {
                    $max = number_format($obj->QU_TH);
                }
            }

            // Formart Qty to integer

            $quantity_collection = [];

            foreach ($list_ru_prdv as $key => $value) {
                $quantity_collection[] = number_format($value->QU_TH);
            }

            // dd($quantity_collection);

            // Qty Reduction Function

            while ($qty > 0) {
                
                if (in_array($qty, $quantity_collection)) {
                    // echo 'FIND Qty :- '.$qty.'<br>';
                    $qty = $qty - $qty;
                    array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                } else {
                    if ($qty > $max) {
                        // echo 'LARGE Qty :- '.$max.'<br>';
                        $qty = $qty - $max;
                        array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                    } else {
                        array_push($final_product,[ 'qty' => $qty, 'is_promo' => 0 ]);
                        $qty = 0;
                    }
                }

            }

            // dd($final_product);
            //echo $qty;exit;
            foreach ($final_product as $key => $value) {
                if ($value['is_promo'] == 1) {
                    $id = $this->searchQty($value['qty'], $list_ru_prdv);
                    $discount = $id->MO_UN_ITM_PRDV_SLS ;
                    $total_price = $mrp * $value['qty'];
                    $ex_price = $total_price - $id->MO_UN_ITM_PRDV_SLS;
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => 'buy '.$value['qty'].' for Rs. '.$id->MO_UN_ITM_PRDV_SLS.' Off', 'ru_prdv' => $id->ID_RU_PRDV, 'type' => $data[$max_discount]['TYPE'], 'promo_id' => $data[$max_discount]['ID_PRM'], 'type_id' => $data[$max_discount]['ID'], 'is_slab' => ($promo_count == 1 ? 0 : 2), 'is_promo' => 1 ];
                        $applied_offer[] = 'buy '.$value['qty'].' for Rs. '.$id->MO_UN_ITM_PRDV_SLS.' Off';
                } else {
                    $total_price = $value['qty'] * $mrp;
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => '', 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];
                }
            }

            //dd($final_data);

            // Check item Already Exits in Cart

            if ($in_cart == 1) {
                $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
            }

            // Available Offer

            $available_offer = [];

            foreach ($list_ru_prdv as $key => $value) {
                $available_offer[number_format($value->QU_TH)] = 'buy '.number_format($value->QU_TH).' for Rs. '.$value->MO_UN_ITM_PRDV_SLS.' Off';
            }

            $final_data['available_offer'] = $available_offer;

            // dd($final_qty);

            // Applied Offer

            $final_data['applied_offer'] = $applied_offer;

        
        $final_data['item_id']  = $source_item->ITEM;

        return $final_data;
    }

    public function buy_source_get_percentage($mrp, $qty, $ru_prdv, $item_id, $store_id, $user_id, $cart_item= false, $carts)
    {
        // dd($ru_prdv);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $item_id)->first();
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }
        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }
        // dd($qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                $condition = DB::table($this->store_db_name.'.co_prdv_itm')->select('PE_UN_ITM_PRDV_SLS')->where('ID_RU_PRDV', $value['ru_prdv'])->first();
                $total_saving = $mrp * $condition->PE_UN_ITM_PRDV_SLS / 100;
                //$total_saving = $mrp - $total_saving;
                $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'], 'ID' => $value['id'] , 'QU_TH' => $value['qu_th']];
            }
        }

        // dd($data);

        // Find Max Discount

        $f_data = [];
        foreach($data as $k =>  $v){
            $f_data[$v['QU_TH']][$k] = $v;
        }
        //dd($f_data);

        // Find Max Discount

        $id_prm_arr = [];
        foreach($f_data as $d){
            $max_discount = max(array_keys($d));    
            $id_prm_arr[] = $d[$max_discount]['ID_PRM'];
        }

        // Check Quantity Based Condition

        if ($data[$max_discount]['TYPE'] == 'max') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH', 'co_prdv_itm.PE_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV','max_grp_itm_lst.ID_ITM')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->whereIn('ru_prdv.ID_PRM', $id_prm_arr)
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }

        if ($data[$max_discount]['TYPE'] == 'co_el') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'co_el_prdv_itm.MO_TH', 'co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM', 'ru_prdv.CD_MTH_PRDV', 'co_prdv_itm.PE_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->whereIn('ru_prdv.ID_PRM', $id_prm_arr)
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }                
            // dd($list_ru_prdv);

            $promo_count = count($list_ru_prdv);

            // Get Max Number of Qty

            $max = 0;
            foreach($list_ru_prdv as $obj) {
                if($obj->QU_TH > $max) {
                    $max = number_format($obj->QU_TH);
                }
            }

            // Formart Qty to integer

            $quantity_collection = [];

            foreach ($list_ru_prdv as $key => $value) {
                $quantity_collection[] = number_format($value->QU_TH);
            }

            // dd($quantity_collection);

            // Qty Reduction Function

            while ($qty > 0) {
                
                if (in_array($qty, $quantity_collection)) {
                    // echo 'FIND Qty :- '.$qty.'<br>';
                    $qty = $qty - $qty;
                    array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                } else {
                    if ($qty > $max) {
                        // echo 'LARGE Qty :- '.$max.'<br>';
                        $qty = $qty - $max;
                        array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                    } else {
                        array_push($final_product,[ 'qty' => $qty, 'is_promo' => 0 ]);
                        $qty = 0;
                    }
                }

            }

            //dd($final_product);

            foreach ($final_product as $key => $value) {
                if ($value['is_promo'] == 1) {
                    $id = $this->searchQty($value['qty'], $list_ru_prdv);
                    $total_price = $mrp * $value['qty'];
                    $discount = $total_price * $id->PE_UN_ITM_PRDV_SLS / 100;
                    $ex_price = $total_price - $discount;
                    //$ex_price = $ex_price * $value['qty'];
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => 'buy '.$value['qty'].' for '.$id->PE_UN_ITM_PRDV_SLS.' % Off', 'ru_prdv' => $id->ID_RU_PRDV, 'type' => $data[$max_discount]['TYPE'], 'promo_id' => $data[$max_discount]['ID_PRM'], 'type_id' => $data[$max_discount]['ID'], 'is_slab' => ($promo_count == 1 ? 0 : 2), 'is_promo' => 1 ];
                        $applied_offer[] = 'buy '.$value['qty'].' for Rs. '.$id->PE_UN_ITM_PRDV_SLS.' % Off';
                } else {
                    $total_price = $value['qty'] * $mrp;
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => '', 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];
                }
            }
			//dd($final_product);
            // Check item Already Exits in Cart

            if ($in_cart == 1) {
                $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
            }

            // Available Offer

            $available_offer = [];

            foreach ($list_ru_prdv as $key => $value) {
                $available_offer[number_format($value->QU_TH)] = 'buy '.number_format($value->QU_TH).' for Rs. '.$value->PE_UN_ITM_PRDV_SLS.' % Off';
            }

            $final_data['available_offer'] = $available_offer;

            // dd($final_qty);

            // Applied Offer

            $final_data['applied_offer'] = $applied_offer;

        
        $final_data['item_id']  = $source_item->ITEM;

        return $final_data;
    }

    public function buy_source_get_target_fixed_price($mrp, $qty, $ru_prdv, $item_id, $store_id = '20001', $user_id='test', $cart_item= false, $carts)
    {
        //dd($ru_prdv);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $item_id)->first();
        //$carts = DB::table('cart')->where('user_id', $user_id)->where('store_id', $store_id)->get();
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }
        

        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }
        // dd($qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                $condition = DB::table($this->store_db_name.'.tr_itm_mxmh_prdv')->select('PNT_PRC_RDN_MXMH as PNT_PRC_UN_ITM_PRDV_SLS', 'ID_PRM_PRD','QU_LM_MXMH')->where('ID_RU_PRDV', $value['ru_prdv'])->first();
                if($condition){

                    if($value['cd_bas_cmp_tgt'] == 7){
                        $grp_list = DB::table($this->store_db_name.'.max_grp_itm_lst')->select('ID_ITM')->where('ID_GRP', $condition->ID_PRM_PRD )->first();
                        $target_product = DB::table($this->store_db_name.'.price_master')->where('ITEM', $grp_list->ID_ITM)->first();
                       
                    }else{

                        $target_product = DB::table($this->store_db_name.'.price_master')->where('ITEM', $condition->ID_PRM_PRD)->first();
                    }

                    $total_saving = $target_product->MRP1 -$condition->PNT_PRC_UN_ITM_PRDV_SLS;
                    $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'], 'ID' => $value['id'] ];

                }
            }
        }
        //dd($data);

        // Find Max Discount

        $max_discount = max(array_keys($data));

        // Check Quantity Based Condition

        if ($data[$max_discount]['TYPE'] == 'max' ){
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select($this->store_db_name.'.ru_prdv.ID_RU_PRDV',$this->store_db_name.'.ru_prdv.QU_AN_SRC', $this->store_db_name.'.ru_prdv.QU_AN_TGT',$this->store_db_name.'.ru_prdv.MAX_ALL_SOURCES',$this->store_db_name.'.ru_prdv.ITM_PRC_CTGY_SRC', $this->store_db_name.'.max_co_el_prdv_itm_grp.MO_TH', $this->store_db_name.'.max_co_el_prdv_itm_grp.QU_TH', $this->store_db_name.'.tr_itm_mxmh_prdv.QU_LM_MXMH', $this->store_db_name.'.ru_prdv.CD_MTH_PRDV',$this->store_db_name.'.max_grp_itm_lst.ID_ITM',$this->store_db_name.'.tr_itm_mxmh_prdv.PNT_PRC_RDN_MXMH as PNT_PRC_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', $this->store_db_name.'.ru_prdv.ID_RU_PRDV', '=', $this->store_db_name.'.max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            ->join($this->store_db_name.'.tr_itm_mxmh_prdv', 'ru_prdv.ID_RU_PRDV', '=', 'tr_itm_mxmh_prdv.ID_RU_PRDV')
                            ->where($this->store_db_name.'.ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }

        if ( $data[$max_discount]['TYPE'] == 'co_el') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC','co_el_prdv_itm.MO_TH','co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM', 'tr_itm_mxmh_prdv.QU_LM_MXMH', 'ru_prdv.CD_MTH_PRDV', 'tr_itm_mxmh_prdv.PNT_PRC_RDN_MXMH as PNT_PRC_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            ->join($this->store_db_name.'.tr_itm_mxmh_prdv', 'ru_prdv.ID_RU_PRDV', '=', 'tr_itm_mxmh_prdv.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }
            $all_sources = $list_ru_prdv->pluck('ID_ITM')->all();
            $list_ru_prdv = $list_ru_prdv->where('ID_ITM', $source_item->ITEM);
           // dd($all_sources);
            //dd($list_ru_prdv);

            $promo_count = count($list_ru_prdv);

            // Get Max Number of Qty

            $max = 0;
            foreach($list_ru_prdv as $obj) {
                if($obj->QU_TH > $max) {
                    $max = number_format($obj->QU_TH);
                }
            }

            // Formart Qty to integer

            $quantity_collection = [];

            foreach ($list_ru_prdv as $key => $value) {
                $quantity_collection[] = number_format($value->QU_TH);
                $single_list_ru_prdv = $value;
            }

            //dd($quantity_collection);

            // Qty Reduction Function

            //dd($single_list_ru_prdv);

            //Checking all sources is exists or not
            $target= [];
            $all_sources_check = true;
            if($single_list_ru_prdv->ITM_PRC_CTGY_SRC == 'allSources' || $single_list_ru_prdv->MAX_ALL_SOURCES == 'allSources'){
                $cart_item_list = $carts->pluck('item_id')->all();
                $cart_item_list[] = $item_id; 
                $cart_item_list  = array_unique($cart_item_list);

                $result = (count($all_sources)==count(array_intersect($all_sources, $cart_item_list)) );
                if($result){
                    $all_sources_check = true;
                }else{
                    $all_sources_check = false;
                }
            }

            $target[$target_product->ITEM] =(array) $single_list_ru_prdv;
            $target[$target_product->ITEM]['product_list'] = $all_sources;
            $target[$target_product->ITEM]['promo_type'] = 'BuyNofXgetYatZ$';

            if($source_item->ITEM == $target_product->ITEM && $all_sources_check == true){
                while ($qty > 0) {
                
                    if (in_array($qty, $quantity_collection)) {
                        // echo 'FIND Qty :- '.$qty.'<br>';
                        $qty = $qty - $qty;
                        array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                    } else {
                        if ($qty > $max) {
                            // echo 'LARGE Qty :- '.$max.'<br>';
                            $qty = $qty - $max;
                            array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                        } else {
                            array_push($final_product,[ 'qty' => $qty, 'is_promo' => 0 ]);
                            $qty = 0;
                        }
                    }

                }

            }else{
                array_push($final_product,[ 'qty' => $qty, 'is_promo' => 0 ]);
            }

            

            //dd($single_list_ru_prdv);

            foreach ($final_product as $key => $value) {
                if ($value['is_promo'] == 1) {
                    $id = $this->searchQty($value['qty'], $list_ru_prdv);
                    $total_price = $mrp * $value['qty'];
                    $ex_price = $id->PNT_PRC_UN_ITM_PRDV_SLS * $value['qty'];
                    $discount = $total_price - $ex_price; 
                    $message = 'buy '.$value['qty'].' each , get '.$single_list_ru_prdv->QU_LM_MXMH.' '.$target_product->ITEM_DESC.' for @ Rs. '.$id->PNT_PRC_UN_ITM_PRDV_SLS;
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message, 'ru_prdv' => $id->ID_RU_PRDV, 'type' => $data[$max_discount]['TYPE'], 'promo_id' => $data[$max_discount]['ID_PRM'], 'type_id' => $data[$max_discount]['ID'], 'is_slab' => ($promo_count == 1 ? 0 : 2), 'is_promo' => 1 ];
                        $applied_offer[] = 'buy '.$value['qty'].' for Rs. '.$id->PNT_PRC_UN_ITM_PRDV_SLS.' Off';
                } else {
                    $total_price = $qty * $mrp;
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => '', 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];
                }
            }

            //dd($final_data);

            // Check item Already Exits in Cart

            if ($in_cart == 1) {
                $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
            }

            // Available Offer

            $available_offer = [];

            foreach ($list_ru_prdv as $key => $value) {
                $message = 'buy '.$value->QU_TH.' each , get '.$single_list_ru_prdv->QU_LM_MXMH.' '.$target_product->ITEM_DESC.' for @ Rs. '.$value->PNT_PRC_UN_ITM_PRDV_SLS;

                $available_offer[number_format($value->QU_TH)] = $message;


            }

            $final_data['available_offer'] = $available_offer;

            // dd($final_qty);

            // Applied Offer

            $final_data['applied_offer'] = $applied_offer;

        
        
        $final_data['item_id']  = $source_item->ITEM;
        $final_data['target'] = $target;        
        //dd($final_data);
        return $final_data;
    }

    public function buy_source_get_target_percentage($mrp, $qty, $ru_prdv, $item_id, $store_id = '20001', $user_id='test', $cart_item= false, $carts)
    {
        //dd($ru_prdv);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $item_id)->first();
        //$carts = DB::table('cart')->where('user_id', $user_id)->where('store_id', $store_id)->get();
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }

        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }
        // dd($qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                $condition = DB::table($this->store_db_name.'.tr_itm_mxmh_prdv')->select('PE_RDN_PRC_MXMH as PE_UN_ITM_PRDV_SLS', 'ID_PRM_PRD','QU_LM_MXMH')->where('ID_RU_PRDV', $value['ru_prdv'])->first();

                if($condition){
                    if($value['cd_bas_cmp_tgt'] == 7){
                        $grp_list = DB::table($this->store_db_name.'.max_grp_itm_lst')->select('ID_ITM')->where('ID_GRP', $condition->ID_PRM_PRD )->first();
                        $target_product = DB::table($this->store_db_name.'.price_master')->where('ITEM', $grp_list->ID_ITM)->first();
                       
                    }else{

                        $target_product = DB::table($this->store_db_name.'.price_master')->where('ITEM', $condition->ID_PRM_PRD)->first();
                    }

                    $total_saving = $mrp * $condition->PE_UN_ITM_PRDV_SLS / 100;
                    //$total_saving = $mrp - $total_saving;

                    $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'], 'ID' => $value['id'] ];
                }

                
            }
        }
        //dd($target_product);

        // Find Max Discount

        $max_discount = max(array_keys($data));

        // Check Quantity Based Condition

        if ($data[$max_discount]['TYPE'] == 'co_el') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC','co_el_prdv_itm.MO_TH','co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM', 'tr_itm_mxmh_prdv.QU_LM_MXMH', 'ru_prdv.CD_MTH_PRDV', 'tr_itm_mxmh_prdv.PE_RDN_PRC_MXMH as PE_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            ->join($this->store_db_name.'.tr_itm_mxmh_prdv', 'ru_prdv.ID_RU_PRDV', '=', 'tr_itm_mxmh_prdv.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }
        
        if ($data[$max_discount]['TYPE'] == 'max') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH','tr_itm_mxmh_prdv.QU_LM_MXMH', 'tr_itm_mxmh_prdv.PE_RDN_PRC_MXMH as PE_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV','max_grp_itm_lst.ID_ITM')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            ->join($this->store_db_name.'.tr_itm_mxmh_prdv', 'ru_prdv.ID_RU_PRDV', '=', 'tr_itm_mxmh_prdv.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }

            $all_sources = $list_ru_prdv->pluck('ID_ITM')->all();
            $list_ru_prdv = $list_ru_prdv->where('ID_ITM', $source_item->ITEM);
            //dd($all_sources);
            //dd($all_sources);

            $promo_count = count($list_ru_prdv);

            // Get Max Number of Qty

            $max = 0;
            foreach($list_ru_prdv as $obj) {
                if($obj->QU_TH > $max) {
                    $max = number_format($obj->QU_TH);
                }
            }

            // Formart Qty to integer

            $quantity_collection = [];

            foreach ($list_ru_prdv as $key => $value) {
                $quantity_collection[] = number_format($value->QU_TH);
                $single_list_ru_prdv = $value;
            }

            //dd($quantity_collection);

            // Qty Reduction Function

            //dd($single_list_ru_prdv);

            //Checking all sources is exists or not
            $target= [];
            $all_sources_check = true;
            if($single_list_ru_prdv->ITM_PRC_CTGY_SRC == 'allSources' || $single_list_ru_prdv->MAX_ALL_SOURCES == 'allSources'){
                $cart_item_list = $carts->pluck('item_id')->all();
                $cart_item_list[] = $item_id; 
                $cart_item_list  = array_unique($cart_item_list);

                $result = (count($all_sources)==count(array_intersect($all_sources, $cart_item_list)) );
                if($result){
                    $all_sources_check = true;
                }else{
                    $all_sources_check = false;
                }
            }

            $target[$target_product->ITEM] =(array) $single_list_ru_prdv;
            $target[$target_product->ITEM]['product_list'] = $all_sources;
            $target[$target_product->ITEM]['promo_type'] = 'BuyNofXgetYatZ%off';

            if($source_item->ITEM == $target_product->ITEM && $all_sources_check == true){
                while ($qty > 0) {
                
                    if (in_array($qty, $quantity_collection)) {
                        // echo 'FIND Qty :- '.$qty.'<br>';
                        $qty = $qty - $qty;
                        array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                    } else {
                        if ($qty > $max) {
                            // echo 'LARGE Qty :- '.$max.'<br>';
                            $qty = $qty - $max;
                            array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                        } else {
                            array_push($final_product,[ 'qty' => $qty, 'is_promo' => 0 ]);
                            $qty = 0;
                        }
                    }

                }

            }else{
                array_push($final_product,[ 'qty' => $qty, 'is_promo' => 0 ]);
            }

            

            //dd($single_list_ru_prdv);
            //dd($final_product);

            foreach ($final_product as $key => $value) {
                if ($value['is_promo'] == 1) {
                    $id = $this->searchQty($value['qty'], $list_ru_prdv);
                                        
                    $total_price = $mrp * $value['qty'];
                    $discount = $mrp * $id->PE_UN_ITM_PRDV_SLS / 100;
                    $ex_price = $total_price - $discount;

                    $message = 'buy '.$value['qty'].'  get '.$single_list_ru_prdv->QU_LM_MXMH.' '.$target_product->ITEM_DESC.' for '.$id->PE_UN_ITM_PRDV_SLS.' % OFF';
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message, 'ru_prdv' => $id->ID_RU_PRDV, 'type' => $data[$max_discount]['TYPE'], 'promo_id' => $data[$max_discount]['ID_PRM'], 'type_id' => $data[$max_discount]['ID'], 'is_slab' => ($promo_count == 1 ? 0 : 2), 'is_promo' => 1 ];
                        $applied_offer[] = 'buy '.$value['qty'].' for Rs. '.$id->PNT_PRC_UN_ITM_PRDV_SLS.' Off';
                } else {
                    $total_price = $qty * $mrp;
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => '', 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];
                }
            }

            //dd($final_data);

            // Check item Already Exits in Cart

            if ($in_cart == 1) {
                $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
            }

            // Available Offer

            $available_offer = [];

            foreach ($list_ru_prdv as $key => $value) {
                $message = 'buy '.$value->QU_TH.' each , get '.$single_list_ru_prdv->QU_LM_MXMH.' '.$target_product->ITEM_DESC.' for '.$value->PE_UN_ITM_PRDV_SLS.' % Off';

                $available_offer[number_format($value->QU_TH)] = $message;


            }

            $final_data['available_offer'] = $available_offer;

            // dd($final_qty);

            // Applied Offer

            $final_data['applied_offer'] = $applied_offer;

        

        
        $final_data['item_id']  = $source_item->ITEM;
        $final_data['target'] = $target;        
        //dd($final_data);
        return $final_data;
    }

    public function buy_source_get_target_amount($mrp, $qty, $ru_prdv, $item_id, $store_id = '20001', $user_id='test', $cart_item= false, $carts)
    {
        //dd($ru_prdv);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $item_id)->first();
        //$carts = DB::table('cart')->where('user_id', $user_id)->where('store_id', $store_id)->get();
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }

        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }
        // dd($qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                $condition = DB::table($this->store_db_name.'.tr_itm_mxmh_prdv')->select('MO_RDN_PRC_MXMH as MO_UN_ITM_PRDV_SLS', 'ID_PRM_PRD','QU_LM_MXMH')->where('ID_RU_PRDV', $value['ru_prdv'])->first();
                
                if($value['cd_bas_cmp_tgt'] == 7){
                    $grp_list = DB::table($this->store_db_name.'.max_grp_itm_lst')->select('ID_ITM')->where('ID_GRP', $condition->ID_PRM_PRD )->first();
                    $target_product = DB::table($this->store_db_name.'.price_master')->where('ITEM', $grp_list->ID_ITM)->first();
                   
                }else{
                    $target_product = DB::table($this->store_db_name.'.price_master')->where('ITEM', $condition->ID_PRM_PRD)->first();
                }
                

                $total_saving = $condition->MO_UN_ITM_PRDV_SLS;
                $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'], 'ID' => $value['id'] ];
            }
        }
        //dd($target_product);

        // Find Max Discount

        $max_discount = max(array_keys($data));

        // Check Quantity Based Condition

        if ($data[$max_discount]['TYPE'] == 'co_el') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC','co_el_prdv_itm.MO_TH','co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM', 'tr_itm_mxmh_prdv.QU_LM_MXMH', 'ru_prdv.CD_MTH_PRDV', 'tr_itm_mxmh_prdv.MO_RDN_PRC_MXMH as MO_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            ->join($this->store_db_name.'.tr_itm_mxmh_prdv', 'ru_prdv.ID_RU_PRDV', '=', 'tr_itm_mxmh_prdv.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }

        if ($data[$max_discount]['TYPE'] == 'max') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH','tr_itm_mxmh_prdv.QU_LM_MXMH', 'tr_itm_mxmh_prdv.MO_RDN_PRC_MXMH as MO_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV','max_grp_itm_lst.ID_ITM')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            ->join($this->store_db_name.'.tr_itm_mxmh_prdv', 'ru_prdv.ID_RU_PRDV', '=', 'tr_itm_mxmh_prdv.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }
            $all_sources = $list_ru_prdv->pluck('ID_ITM')->all();
            $list_ru_prdv = $list_ru_prdv->where('ID_ITM', $source_item->ITEM);
            //dd($all_sources);
            //dd($list_ru_prdv);

            $promo_count = count($list_ru_prdv);

            // Get Max Number of Qty

            $max = 0;
            foreach($list_ru_prdv as $obj) {
                if($obj->QU_TH > $max) {
                    $max = number_format($obj->QU_TH);
                }
            }

            // Formart Qty to integer

            $quantity_collection = [];

            foreach ($list_ru_prdv as $key => $value) {
                $quantity_collection[] = number_format($value->QU_TH);
                $single_list_ru_prdv = $value;
            }

            //dd($quantity_collection);

            // Qty Reduction Function

            //dd($single_list_ru_prdv);

            //Checking all sources is exists or not
            $target= [];
            $all_sources_check = true;
            if($single_list_ru_prdv->ITM_PRC_CTGY_SRC == 'allSources' || $single_list_ru_prdv->MAX_ALL_SOURCES == 'allSources'){
                $cart_item_list = $carts->pluck('item_id')->all();
                $cart_item_list[] = $item_id; 
                $cart_item_list  = array_unique($cart_item_list);

                $result = (count($all_sources)==count(array_intersect($all_sources, $cart_item_list)) );
                if($result){
                    $all_sources_check = true;
                }else{
                    $all_sources_check = false;
                }
            }

            $target[$target_product->ITEM] =(array) $single_list_ru_prdv;
            $target[$target_product->ITEM]['product_list'] = $all_sources;
            $target[$target_product->ITEM]['promo_type'] = 'BuyNofXgetYatZ$off';

            if($source_item->ITEM == $target_product->ITEM && $all_sources_check == true){
                while ($qty > 0) {
                
                    if (in_array($qty, $quantity_collection)) {
                        // echo 'FIND Qty :- '.$qty.'<br>';
                        $qty = $qty - $qty;
                        array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                    } else {
                        if ($qty > $max) {
                            // echo 'LARGE Qty :- '.$max.'<br>';
                            $qty = $qty - $max;
                            array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                        } else {
                            array_push($final_product,[ 'qty' => $qty, 'is_promo' => 0 ]);
                            $qty = 0;
                        }
                    }

                }

            }else{
                array_push($final_product,[ 'qty' => $qty, 'is_promo' => 0 ]);
            }

            

            //dd($single_list_ru_prdv);
            //dd($final_product);

            foreach ($final_product as $key => $value) {
                if ($value['is_promo'] == 1) {
                    $id = $this->searchQty($value['qty'], $list_ru_prdv);

                    $discount = $id->MO_UN_ITM_PRDV_SLS / $value['qty'];
                    $total_price = $mrp * $value['qty'];
                    $ex_price = $total_price - $id->MO_UN_ITM_PRDV_SLS;

                    $message = 'buy '.$value['qty'].'  get '.$single_list_ru_prdv->QU_LM_MXMH.' '.$target_product->ITEM_DESC.' for '.$id->MO_UN_ITM_PRDV_SLS.'Rs. OFF';
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message, 'ru_prdv' => $id->ID_RU_PRDV, 'type' => $data[$max_discount]['TYPE'], 'promo_id' => $data[$max_discount]['ID_PRM'], 'type_id' => $data[$max_discount]['ID'], 'is_slab' => ($promo_count == 1 ? 0 : 2), 'is_promo' => 1 ];
                        $applied_offer[] = 'buy '.$value['qty'].' for Rs. '.$id->PNT_PRC_UN_ITM_PRDV_SLS.' Off';
                } else {
                    $total_price = $qty * $mrp;
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => '', 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];
                }
            }

            //dd($final_data);

            // Check item Already Exits in Cart

            if ($in_cart == 1) {
                $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
            }

            // Available Offer

            $available_offer = [];

            foreach ($list_ru_prdv as $key => $value) {
                $message = 'buy '.$value->QU_TH.' , get '.$single_list_ru_prdv->QU_LM_MXMH.' '.$target_product->ITEM_DESC.' for '.$value->MO_UN_ITM_PRDV_SLS.'Rs. Off';

                $available_offer[number_format($value->QU_TH)] = $message;


            }

            $final_data['available_offer'] = $available_offer;

            // dd($final_qty);

            // Applied Offer

            $final_data['applied_offer'] = $applied_offer;

        

        
        $final_data['item_id']  = $source_item->ITEM;
        $final_data['target'] = $target;        
        //dd($final_data);
        return $final_data;
    }

    

    public function buy_source_get_percentage_tiered($mrp, $qty, $ru_prdv, $item_id, $store_id = '20001', $user_id='test', $cart_item= false, $carts)
    {
        //dd($ru_prdv);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $item_id)->first();
        //$carts = DB::table('cart')->where('user_id', $user_id)->where('store_id', $store_id)->get();
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }
        
    
        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }

        $total_qty = $qty;

        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                $condition = DB::table($this->store_db_name.'.co_prdv_itm')->select('PE_UN_ITM_PRDV_SLS')->where('ID_RU_PRDV', $value['ru_prdv'])->first();
                if($condition){
                    // echo '<pre>';
                    // print_r($value);
                    // print_r($condition);
                    $total_saving = $mrp * $condition->PE_UN_ITM_PRDV_SLS / 100;
                    //$total_saving = $mrp - $total_saving;
                    
                    $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'], 'ID' => $value['id'] ];
                }
            }
        }
        //dd($data);
        // Find Max Discount

        $max_discount = max(array_keys($data));

        // Check Quantity Based Condition
        if ($data[$max_discount]['TYPE'] == 'max') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH', 'co_prdv_itm.PE_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV','max_grp_itm_lst.ID_ITM')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }

        if ($data[$max_discount]['TYPE'] == 'co_el') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'co_el_prdv_itm.MO_TH', 'co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM', 'ru_prdv.CD_MTH_PRDV', 'co_prdv_itm.PE_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }                
            $all_sources = array_unique($list_ru_prdv->pluck('ID_ITM')->all());
            $list_ru_prdv = $list_ru_prdv->where('ID_ITM', $source_item->ITEM);
            //dd($list_ru_prdv);
            //dd($all_sources);
            $promo_count = count($list_ru_prdv);
            // Get Max Number of Qty

            $max = 0;
            foreach($list_ru_prdv as $obj) {
                if($obj->QU_TH > $max) {
                    $max = number_format($obj->QU_TH);
                }
            }

            //echo $max;exit;

            // Formart Qty to integer

            $quantity_collection = [];
            $add_qty = 1;
            $source_any_qty = false;
            $available_offer = [];
            $qty_message ='';
            foreach ($list_ru_prdv as $key => $value) {

                
                //This condition is added when QU_AN_SRC is set 
                if($value->CD_BAS_CMP_SRC == '7'){
                    $source_any_qty = true;
                }

                if($value->QU_AN_SRC > 0){
                    $source_any_qty = true;
                    $add_qty = $value->QU_AN_SRC;
                    $qty_message = ' including any '.$value->QU_AN_SRC;
                }

                $available_offer[number_format($value->QU_TH)] = 'buy '.number_format($value->QU_TH).$qty_message.' for '.$value->PE_UN_ITM_PRDV_SLS.' % Off';

                $quantity_collection[] = number_format($value->QU_TH * $add_qty);
            }

            //dd($quantity_collection);
            $cart_source_qty = 0;
            if($source_any_qty){
                //Removing current item from sources
                $all_sources = array_diff($all_sources , [$source_item->ITEM]);
                //dd($all_sources);
                $carts = $carts->whereIn('item_id', $all_sources);
                $cart_source_qty = $carts->sum('qty');
                $total_qty += $cart_source_qty;
            }
            //dd($quantity_collection);

            // Qty Reduction Function

            while ($total_qty > 0) {
                
                if (in_array($total_qty, $quantity_collection)) {
                    // echo 'FIND Qty :- '.$qty.'<br>';
                    array_push($final_product,[ 'qty' => $total_qty  , 'is_promo' => 1 ]);
                    $total_qty = $total_qty - $total_qty;
                } else {
                    
                    //This condtion is added to get best Offers
                    $combination = $this->find_combination($quantity_collection, $total_qty);
                    //dd($combination);
                    if(!empty($combination)){

                        $best_combi =['discount' => 0 , 'final_product' => [] ];
                    
                        foreach($combination as $key => $combi){
                            $temp_qty = $total_qty;
                            $best_final_product= [];
                            $total_price = 0;
                            $ex_price = 0;
                            $discount = 0;
                            foreach($combi as $combi_qty){
                                $temp_qty = $temp_qty - $combi_qty;
                                $id = $this->searchQty($combi_qty / $add_qty, $list_ru_prdv);
                                
                                $total_price = $mrp * $combi_qty;
                                $discount = $total_price * $id->PE_UN_ITM_PRDV_SLS / 100;
                                $ex_price = $total_price - $discount;
                                array_push($best_final_product,[ 'qty' => $combi_qty  , 'is_promo' => 1 ]);
                            }

                            if($temp_qty > 0 ){
                                array_push($best_final_product,[ 'qty' => $temp_qty  , 'is_promo' => 0 ]);
                            }
                            
                            if($best_combi['discount'] < $discount){
                                $best_combi['discount'] = $discount;
                                $best_combi['final_product'] = $best_final_product;
                            }
                        }

                        //dd($best_combi);

                        $final_product = $best_combi['final_product'] ;

                    }else{
                        array_push($final_product,[ 'qty' => $total_qty , 'is_promo' => 0 ]);
                        
                    }
                    $total_qty = 0;
                    
                }

            }

            //dd($final_product);
            

            foreach ($final_product as $key => $value) {

                if ($value['is_promo'] == 1) {
                    $id = $this->searchQty($value['qty'] / $add_qty, $list_ru_prdv);
                    // echo '<pre>';
                    // print_r($id);
                    if($cart_source_qty > 0){
                        if($value['qty'] >  $cart_source_qty ){
                           $value['qty'] -= $cart_source_qty;
                           $cart_source_qty = 0;
                        }
                        
                    }

                    $message = 'buy '.$id->QU_TH.$qty_message.' for '.$id->PE_UN_ITM_PRDV_SLS.'% Off' ;
                    $applied_offer[] = $message;
                    
                    $total_price = $mrp * $value['qty'];
                    $discount = $total_price * $id->PE_UN_ITM_PRDV_SLS / 100;
                    $ex_price = $total_price - $discount;
                    //$ex_price = $ex_price * $value['qty'];
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message, 'ru_prdv' => $id->ID_RU_PRDV, 'type' => $data[$max_discount]['TYPE'], 'promo_id' => $data[$max_discount]['ID_PRM'], 'type_id' => $data[$max_discount]['ID'], 'is_slab' => ($promo_count == 1 ? 0 : 2) , 'is_promo' => 1];
                        
                    
                } else {
                    
                    if($cart_source_qty > 0){
                        if($value['qty'] >  $cart_source_qty ){
                           $value['qty'] -= $cart_source_qty;
                           $cart_source_qty = 0;
                        }
                        
                    }

                    
                    $total_price = $mrp * $value['qty'];
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => 0, 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '' , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0];

                        
                    
                }
            }


            // Check item Already Exits in Cart

            if ($in_cart == 1) {
                $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
            }

            // Available Offer



            $final_data['available_offer'] = $available_offer;

            // dd($final_qty);

            // Applied Offer

            $final_data['applied_offer'] = $applied_offer;

        
        $final_data['item_id']  = $source_item->ITEM;
        //dd($final_data);

        return $final_data;
    }

    public function buy_source_get_fixed_price_tiered($mrp, $qty, $ru_prdv, $item_id, $store_id = '20001', $user_id='test',$cart_item= false, $carts)
    {
        //dd($ru_prdv);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $item_id)->first();
        //$carts = DB::table('cart')->where('user_id', $user_id)->where('store_id', $store_id)->get();
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }
        
        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }

        $total_qty = $qty;

        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
            
                $condition = DB::table($this->store_db_name.'.co_prdv_itm')->select('PNT_PRC_UN_ITM_PRDV_SLS')->where('ID_RU_PRDV', $value['ru_prdv'])->first();
                if($condition){
                    // echo '<pre>';
                    // print_r($value);
                    // print_r($condition);
                    $total_saving = $mrp - $condition->PNT_PRC_UN_ITM_PRDV_SLS;
                    
                    $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'], 'ID' => $value['id'] ];
                }
                
            }
        }

        // Find Max Discount

        $max_discount = max(array_keys($data));

        // Check Quantity Based Condition

        if ($data[$max_discount]['TYPE'] == 'co_el') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'co_el_prdv_itm.MO_TH', 'co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM', 'ru_prdv.CD_MTH_PRDV', 'co_prdv_itm.PNT_PRC_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }

        if ($data[$max_discount]['TYPE'] == 'max') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH', 'co_prdv_itm.PNT_PRC_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV','max_grp_itm_lst.ID_ITM')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }
            //dd($list_ru_prdv);
            $all_sources = array_unique($list_ru_prdv->pluck('ID_ITM')->all() );
            $list_ru_prdv = $list_ru_prdv->where('ID_ITM', $source_item->ITEM);
            //dd($all_sources);

            $promo_count = count($list_ru_prdv);
            // Get Max Number of Qty

            $max = 0;
            foreach($list_ru_prdv as $obj) {
                if($obj->QU_TH > $max) {
                    $max = number_format($obj->QU_TH);
                }
            }

            // Formart Qty to integer

            $quantity_collection = [];
            $add_qty = 1;
            $source_any_qty = false;
            $available_offer = [];
            $qty_message ='';
            foreach ($list_ru_prdv as $key => $value) {
                //This condition is added when QU_AN_SRC is set 
                if($value->CD_BAS_CMP_SRC == '7'){
                    $source_any_qty = true;
                }

                if($value->QU_AN_SRC > 0){
                    $source_any_qty = true;
                    $add_qty = $value->QU_AN_SRC;
                    $qty_message = ' including any '.$value->QU_AN_SRC;
                }

                $available_offer[number_format($value->QU_TH)] = 'buy '.number_format($value->QU_TH).' for Rs. '.$value->PNT_PRC_UN_ITM_PRDV_SLS;


                $quantity_collection[] = number_format($value->QU_TH * $add_qty);
            }

            //dd($quantity_collection);
            $cart_source_qty = 0;
            $cart_source_item = [];
            if($source_any_qty){
                //Removing current item from sources
                $all_sources = array_diff($all_sources , [$source_item->ITEM]);
                //dd($all_sources);
                $cart_source_item = $carts->whereIn('item_id', $all_sources);

                //dd($cart_source_item);
                $cartItems =[];
                foreach($cart_source_item as $ikey => $cartItem){
                    $loopQty = $cartItem->qty;
                    while($loopQty > 0){
                        $cartItems[] = ['item_id' => $cartItem->item_id ,'qty' => 1 , 'unit_mrp' => $cartItem->unit_mrp];
                        $loopQty--;
                    }
                }

                $loopQty = $total_qty;
                while($loopQty > 0){
                    $cartItems[] = ['item_id' => $source_item->ITEM ,'qty' => 1, 'unit_mrp' => $mrp];
                    $loopQty--;
                }

                $cart_source_qty = $cart_source_item->sum('qty');
                $total_qty += $cart_source_qty;
                
                //dd($cartItems );
            }else{
                $loopQty = $total_qty;
                while($loopQty > 0){
                    $cartItems[] = ['item_id' => $source_item->ITEM ,'qty' => 1, 'unit_mrp' => $mrp];
                    $loopQty--;
                }
            }

            //dd($quantity_collection);
            // Qty Reduction Function
            //echo $qty;exit;
            while ($total_qty > 0) {
                
                if (in_array($total_qty, $quantity_collection)) {
                    // echo 'FIND Qty :- '.$qty.'<br>';
                    
                    array_push($final_product,[ 'qty' => $total_qty, 'is_promo' => 1 ]);
                    $total_qty = $total_qty - $total_qty;
                } else {

                    //This condtion is added to get best Offers
                    $combination = $this->find_combination($quantity_collection, $total_qty);
                    //dd($combination);
                    if(!empty($combination)){
                        $best_combi =['discount' => 0 , 'final_product' => [] ];
                        
                        foreach($combination as $key => $combi){
                            $temp_qty = $total_qty;
                            $best_final_product= [];
                            $total_price = 0;
                            $ex_price = 0;
                            $discount = 0;
                            foreach($combi as $combi_qty){
                                $temp_qty = $temp_qty - $combi_qty;
                                $id = $this->searchQty($combi_qty / $add_qty, $list_ru_prdv);
                                $total_price = $total_price + ($mrp * $combi_qty );
                                $ex_price = $ex_price + $id->PNT_PRC_UN_ITM_PRDV_SLS;
                                $discount = $total_price - $ex_price; 
                                array_push($best_final_product,[ 'qty' => $combi_qty, 'is_promo' => 1 ]);
                            }
                            if($temp_qty > 0 ){
                                array_push($best_final_product,[ 'qty' => $temp_qty, 'is_promo' => 0 ]);
                            }
                            
                            if($best_combi['discount'] < $discount){
                                $best_combi['discount'] = $discount;
                                $best_combi['final_product'] = $best_final_product;
                            }
                        }
                        //exit;
                        //dd($best_combi);

                        $final_product = $best_combi['final_product'] ; 
                    }else{
                        array_push($final_product,[ 'qty' => $total_qty, 'is_promo' => 0 ]);
                    } 
                    $total_qty = 0;                
                }

            }

           // $final_product = $best_combi['final_product'] ;  

           //dd($final_product);
            $cartItems = collect($cartItems)->sortByDesc('unit_mrp');
            //dd($cartItems);
            foreach ($final_product as $key => $value) {
                if ($value['is_promo'] == 1) {
                    $cart_source_total = 0;
                    $id = $this->searchQty($value['qty'] / $add_qty, $list_ru_prdv);
                    // echo '<pre>';
                     //print_r($id);
                    //echo $offer_amount = $id->PNT_PRC_UN_ITM_PRDV_SLS;exit;
                    $mrp_on_offer = $mrp;
                    $param = [];
                    $params = [];
                    //echo $cart_source_qty;exit;
                    $cartItems = $cartItems->filter(function($q , $key) use (&$value, &$param, &$params, &$cart_source_total) {
                        $filter_coll_flag = true;
                        if($value['qty'] < $q['qty']){
                            $q['qty'] - $value['qty'];
                            
                            $q['qty'] = $q['qty'] - $value['qty'];
                            $value['qty'] = 0;

                            //dd($q);
                            //echo $q->qty .' - '. $value['qty'];exit;
                            //$q->
                        }else{

                            $value['qty'] -= $q['qty'];
                           // unset($cart_source_item[$key]);
                            $filter_coll_flag = false;
                        }
                        //dd($value);

                        if($filter_coll_flag){
                            return $q;
                        }else{
                            
                            $cart_source_total += $q['unit_mrp'];
                            if($q['qty'] >1 ){
                                $cQty = $q['qty'];
                                while($cQty > 0){
                                    $params[] = [ 'item_id' => $q['item_id'], 'unit_mrp' => $q['unit_mrp'] ];
                                    $param[] = $q['unit_mrp'];
                                    $cQty--;
                                }
                                
                            }else{
                                $params[] = [ 'item_id' => $q['item_id'], 'unit_mrp' => $q['unit_mrp'] ];
                                $param[] = $q['unit_mrp'];
                            }

                            return null;
                        }
                        
                    });

                    $param_count_of_cart = count($param);

                    $offer_amount = $cart_source_total - $id->PNT_PRC_UN_ITM_PRDV_SLS;
                    //dd($param);

                    $ratio_val = $this->get_offer_amount_by_ratio($param, $offer_amount);
                    //dd($ratio_val);
                    $ratio_total = array_sum($ratio_val);
                    //dd($ratio_val);
                    
                    $discount = 0;
                    $total_discount = 0;
                    foreach($params as $key => $par){
                        $discount = round( ($ratio_val[$key]/$ratio_total) * $offer_amount , 2);
                        $params[$key]['discount'] =  $discount;
                        $total_discount += $discount;
                    }

                    //Thid code is added because facing issue when rounding of discount value
                    if($total_discount > $offer_amount){
                        $total_diff = $total_discount - $offer_amount;
                        foreach($params as $key => $par){
                            if($total_diff > 0.00){
                                $params[$key]['discount'] -= 0.01;
                                $total_diff -= 0.01;
                            }else{
                                break;
                            }
                        }
                    }else if($total_discount < $offer_amount){
                        $total_diff =  $offer_amount - $total_discount;
                        foreach($params as $key => $par){
                            if($total_diff > 0.00){
                                $params[$key]['discount'] += 0.01;
                                $total_diff -= 0.01;
                            }else{
                                break;
                            }
                        }

                    }

                    $total_qty = 0;
                    $total_price = 0;
                    $discount =0;
                    foreach($params as $key => $par){
                        if($par['item_id'] == $source_item->ITEM ){
                            $total_price += $par['unit_mrp'] * 1;
                            $total_qty += 1;
                            $discount += $par['discount'];
                            $mrp = $par['unit_mrp'];
                        }
                    }

                    $discount = round($discount ,2);
                    //$total_price = $mrp * $value['qty'];
                    //$discount = $offer_amount;
                    $ex_price = $total_price - $discount;                 
                    
                    $message = 'buy '.$id->QU_TH.' for Rs. '.$id->PNT_PRC_UN_ITM_PRDV_SLS; 
                    if($total_qty !=0){
                       $final_data['pdata'][] = [ 'qty' => $total_qty, 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message , 'ru_prdv' => $id->ID_RU_PRDV, 'type' => $data[$max_discount]['TYPE'], 'promo_id' => $data[$max_discount]['ID_PRM'], 'type_id' => $data[$max_discount]['ID'], 'is_slab' => ($promo_count == 1 ? 0 : 2), 'is_promo' => 1 ];
                    }




                    $applied_offer[] = $message;
                    
                } else {

                    $total_price = 0;
                    $total_qty = 0;
                    $params=[];
                    $cartItems = $cartItems->filter(function($q , $key) use(&$params) {

                        $params[] = [ 'item_id' => $q['item_id'], 'unit_mrp' => $q['unit_mrp'] ];

                    });

                    foreach($params as $key => $par){
                        if($par['item_id'] == $source_item->ITEM ){
                            $total_price += $par['unit_mrp'] * 1;
                            $total_qty += 1;
                            $mrp = $par['unit_mrp'];
                        }
                    }

                    if($total_qty > 0){
                        $final_data['pdata'][] = [ 'qty' => $total_qty, 'mrp' => $mrp, 'discount' => 0, 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];
                    }

                    
                }
            }


            // Check item Already Exits in Cart

            if ($in_cart == 1) {
                $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
            }

            
            $final_data['available_offer'] = $available_offer;

            // dd($final_qty);

            // Applied Offer

            $final_data['applied_offer'] = $applied_offer;

        

        //dd($final_data);
        $final_data['item_id']  = $source_item->ITEM;

        return $final_data;
    }

    public function buy_source_get_amount_tiered($mrp, $qty, $ru_prdv, $item_id, $store_id = '20001', $user_id='test',$cart_item= false, $carts)
    {
        //dd($ru_prdv);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $item_id)->first();
        //$carts = DB::table('cart')->where('user_id', $user_id)->where('store_id', $store_id)->get();
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }
        
        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }

        $total_qty = $qty;

        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
            
                $condition = DB::table($this->store_db_name.'.co_prdv_itm')->select('MO_UN_ITM_PRDV_SLS')->where('ID_RU_PRDV', $value['ru_prdv'])->first();
                if($condition){
                    // echo '<pre>';
                    // print_r($value);
                    // print_r($condition);
                    $total_saving =  $condition->MO_UN_ITM_PRDV_SLS;
                    
                    $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'], 'ID' => $value['id'] ];
                }
                
            }
        }

        // Find Max Discount

        $max_discount = max(array_keys($data));

        // Check Quantity Based Condition

        if ($data[$max_discount]['TYPE'] == 'co_el') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'co_el_prdv_itm.MO_TH', 'co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM', 'ru_prdv.CD_MTH_PRDV', 'co_prdv_itm.MO_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }

        if ($data[$max_discount]['TYPE'] == 'max') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH', 'co_prdv_itm.MO_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV','max_grp_itm_lst.ID_ITM')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }
            //dd($list_ru_prdv);
            $all_sources = array_unique($list_ru_prdv->pluck('ID_ITM')->all() );
            $list_ru_prdv = $list_ru_prdv->where('ID_ITM', $source_item->ITEM);
            //dd($all_sources);

            $promo_count = count($list_ru_prdv);
            // Get Max Number of Qty

            $max = 0;
            foreach($list_ru_prdv as $obj) {
                if($obj->QU_TH > $max) {
                    $max = number_format($obj->QU_TH);
                }
            }

            // Formart Qty to integer

            $quantity_collection = [];
            $add_qty = 1;
            $source_any_qty = false;
            $available_offer = [];
            $qty_message ='';
            foreach ($list_ru_prdv as $key => $value) {
                //This condition is added when QU_AN_SRC is set 
                if($value->CD_BAS_CMP_SRC == '7'){
                    $source_any_qty = true;
                }

                if($value->QU_AN_SRC > 0){
                    $source_any_qty = true;
                    $add_qty = $value->QU_AN_SRC;
                    $qty_message = ' including any '.$value->QU_AN_SRC;
                }

                $available_offer[number_format($value->QU_TH)] = 'buy '.number_format($value->QU_TH).' for '.$value->MO_UN_ITM_PRDV_SLS.' Rs off';


                $quantity_collection[] = number_format($value->QU_TH * $add_qty);
            }

            //dd($quantity_collection);
            $cart_source_qty = 0;
            $cart_source_item = [];
            if($source_any_qty){
                //Removing current item from sources
                $all_sources = array_diff($all_sources , [$source_item->ITEM]);
                //dd($all_sources);
                $cart_source_item = $carts->whereIn('item_id', $all_sources);
                $cart_source_qty = $cart_source_item->sum('qty');
                $total_qty += $cart_source_qty;
            }

            //dd($quantity_collection);

            // Qty Reduction Function
            //echo $qty;exit;
            while ($total_qty > 0) {
                
                if (in_array($total_qty, $quantity_collection)) {
                    // echo 'FIND Qty :- '.$qty.'<br>';
                    
                    array_push($final_product,[ 'qty' => $total_qty, 'is_promo' => 1 ]);
                    $total_qty = $total_qty - $total_qty;
                } else {

                    //This condtion is added to get best Offers
                    $combination = $this->find_combination($quantity_collection, $total_qty);
                    //dd($combination);
                    if(!empty($combination)){
                        $best_combi =['discount' => 0 , 'final_product' => [] ];
                        
                        foreach($combination as $key => $combi){
                            $temp_qty = $total_qty;
                            $best_final_product= [];
                            $total_price = 0;
                            $ex_price = 0;
                            $discount = 0;
                            foreach($combi as $combi_qty){
                                $temp_qty = $temp_qty - $combi_qty;
                                $id = $this->searchQty($combi_qty / $add_qty, $list_ru_prdv);
                                $total_price += $total_price + ($mrp * $combi_qty );
                                $discount += $id->MO_UN_ITM_PRDV_SLS; 
                                $ex_price = $total_price - $ex_price ;
                                
                                array_push($best_final_product,[ 'qty' => $combi_qty, 'is_promo' => 1 ]);
                            }
                            if($temp_qty > 0 ){
                                array_push($best_final_product,[ 'qty' => $temp_qty, 'is_promo' => 0 ]);
                            }
                            
                            if($best_combi['discount'] < $discount){
                                $best_combi['discount'] = $discount;
                                $best_combi['final_product'] = $best_final_product;
                            }
                        }
                        //exit;
                        //dd($best_combi);

                        $final_product = $best_combi['final_product'] ; 
                    }else{
                        array_push($final_product,[ 'qty' => $total_qty, 'is_promo' => 0 ]);
                    } 
                    $total_qty = 0;                
                }

            }

           // $final_product = $best_combi['final_product'] ;  

           //dd($final_product);

            foreach ($final_product as $key => $value) {
                if ($value['is_promo'] == 1) {
                    $cart_source_total = 0;
                    $id = $this->searchQty($value['qty'] / $add_qty, $list_ru_prdv);
                    // echo '<pre>';
                     //print_r($id);
                    //echo $offer_amount = $id->PNT_PRC_UN_ITM_PRDV_SLS;exit;
                    $param=[];
                    //echo $cart_source_qty;exit;
                    if($cart_source_qty > 0){

                        if($value['qty'] >  $cart_source_qty ){
                           $value['qty'] -= $cart_source_qty;
                           $cart_source_qty = 0;
                        }

                        //dd($cart_source_item);
                        foreach ($cart_source_item as $key => $q) {
                            $cart_q = $q->qty;
                            while($cart_q > 0){
                                $param[] = $q->unit_mrp;
                                $cart_q--;
                            }
                            $cart_source_total += $q->unit_mrp * $q->qty;
                        }
                       //exit;
                    }
                    //dd($param);

                    $param_count_of_cart = count($param);
                    $loopQty = $value['qty'];
                    while($loopQty > 0){
                       $param[] = $mrp; 
                       $loopQty--;
                    }

                    //$offer_amount = ($cart_source_total + ($value['qty'] * $mrp) ) - $id->PNT_PRC_UN_ITM_PRDV_SLS;
                    $offer_amount = $id->MO_UN_ITM_PRDV_SLS;
                    //dd($param);

                    $ratio_val = $this->get_offer_amount_by_ratio($param, $offer_amount);
                    //dd($ratio_val);
                    $ratio_total = array_sum($ratio_val);
                    //dd($ratio_val);
                    $loopQty = $value['qty'];

                    $total_price=0;
                    $discount = 0;
                    $ex_price =0;
                    while($loopQty > 0){
                        
                        $total_price += $mrp * 1;
                        //$ex_price = $offer_amount;
                        //$discount = $total_price - $ex_price;  
                        $discount += ($ratio_val[$param_count_of_cart]/$ratio_total) * $offer_amount;
                        //$ex_price = $total_price - $discount;   
                        $loopQty--;
                        $param_count_of_cart++;         

                        //$message = 'buy '.$id->QU_TH.' for Rs. '.$id->PNT_PRC_UN_ITM_PRDV_SLS;
                        //$final_data['pdata'][] = [ 'qty' => 1, 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message , 'ru_prdv' => $id->ID_RU_PRDV, 'type' => $data[$max_discount]['TYPE'], 'promo_id' => $data[$max_discount]['ID_PRM'], 'type_id' => $data[$max_discount]['ID'], 'is_slab' => ($promo_count == 1 ? 0 : 2), 'is_promo' => 1 ];

                    }

                    $discount = round($discount ,2);
                    //$total_price = $mrp * $value['qty'];
                    //$discount = $offer_amount;
                    $ex_price = $total_price - $discount;                 
                    
                    $message = 'buy '.$id->QU_TH.' for '.$id->MO_UN_ITM_PRDV_SLS.' Rs Off'; 
                    if($value['qty']!=0){
                       $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message , 'ru_prdv' => $id->ID_RU_PRDV, 'type' => $data[$max_discount]['TYPE'], 'promo_id' => $data[$max_discount]['ID_PRM'], 'type_id' => $data[$max_discount]['ID'], 'is_slab' => ($promo_count == 1 ? 0 : 2), 'is_promo' => 1 ];
                    }




                    $applied_offer[] = $message;
                    
                } else {

                    if($cart_source_qty > 0){

                        if($value['qty'] >  $cart_source_qty ){
                           $value['qty'] -= $cart_source_qty;
                           $cart_source_qty = 0;
                        }
                       //exit;
                    }

                    while ($value['qty'] > 0) {
                        $total_price = $mrp * 1;
                        $final_data['pdata'][] = [ 'qty' => 1, 'mrp' => $mrp, 'discount' => 0, 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '' , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0];

                        $value['qty']--;
                    }
                    
                }
            }


            // Check item Already Exits in Cart

            if ($in_cart == 1) {
                $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
            }

            
            $final_data['available_offer'] = $available_offer;

            // dd($final_qty);

            // Applied Offer

            $final_data['applied_offer'] = $applied_offer;

        

        //dd($final_data);
        $final_data['item_id']  = $source_item->ITEM;

        return $final_data;
    }

    public function buy_source_get_lowest_percentage_price($mrp, $qty, $ru_prdv, $item_id, $store_id = '20001', $user_id='test',$cart_item= false, $carts){

        //echo $mrp;exit;
        //dd($ru_prdv);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $item_id)->first();
        //dd($source_item);
        //$carts = DB::table('cart')->where('user_id', $user_id)->where('store_id', $store_id)->get();
        if($cart_item){
            //echo 'inside this';exit;
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }
       
        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }

        $total_qty = $qty;


        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                $condition = DB::table($this->store_db_name.'.co_prdv_itm')->select('PE_UN_ITM_PRDV_SLS')->where('ID_RU_PRDV', $value['ru_prdv'])->first();
                if($condition){
                    // echo '<pre>';
                    // print_r($value);
                    // print_r($condition);
                    $total_saving = $mrp * $condition->PE_UN_ITM_PRDV_SLS / 100;
                    //$total_saving = $mrp - $total_saving;
                    
                    $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'], 'ID' => $value['id'] ];
                }
            }
        }
        //dd($data);
        // Find Max Discount

        $max_discount = max(array_keys($data));

        // Check Quantity Based Condition

        if ($data[$max_discount]['TYPE'] == 'co_el') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'co_el_prdv_itm.MO_TH', 'co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM', 'ru_prdv.CD_MTH_PRDV', 'co_prdv_itm.PE_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }
        
        if ($data[$max_discount]['TYPE'] == 'max') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH', 'co_prdv_itm.PE_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV','max_grp_itm_lst.ID_ITM')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();

        }
            $all_sources = $list_ru_prdv->pluck('ID_ITM')->all();
            $list_ru_prdv = $list_ru_prdv->where('ID_ITM', $source_item->ITEM);
            //dd($all_sources);

            $promo_count = count($list_ru_prdv);

            // Get Max Number of Qty

            $max = 0;
            foreach($list_ru_prdv as $obj) {
                if($obj->QU_TH > $max) {
                    $max = number_format($obj->QU_TH);
                }
            }

            // Formart Qty to integer

            $quantity_collection = [];
            $add_qty = 1;
            $source_any_qty = false;
            $available_offer = [];
            $qty_message ='';
            foreach ($list_ru_prdv as $key => $value) {
                //This condition is added when QU_AN_SRC is set 
                if($value->CD_BAS_CMP_SRC == '7'){
                    $source_any_qty = true;
                }

                if($value->QU_AN_SRC > 0){
                    $source_any_qty = true;
                    $add_qty = $value->QU_AN_SRC;
                    $qty_message = ' including any '.$value->QU_AN_SRC;
                }

                $available_offer[number_format($value->QU_TH)] = 'buy any '.$value->QU_TH.', get Lowest item for '.$value->PE_UN_ITM_PRDV_SLS.'% Off';


                $quantity_collection[] = number_format($value->QU_TH * $add_qty);
            }

            //dd($quantity_collection);
            $cart_source_qty = 0;
            $cart_source_item = [];
            if($source_any_qty){
                //Removing current item from sources
                $all_sources = array_diff($all_sources , [$source_item->ITEM]);
                
                //dd($all_sources);
                $cart_source_item = $carts->whereIn('item_id', $all_sources);

                //dd($cart_source_item);
                $cartItems =[];
                foreach($cart_source_item as $ikey => $cartItem){
                    $loopQty = $cartItem->qty;
                    while($loopQty > 0){
                        $cartItems[] = ['item_id' => $cartItem->item_id ,'qty' => 1 , 'unit_mrp' => $cartItem->unit_mrp];
                        $loopQty--;
                    }
                }

                $loopQty = $total_qty;
                while($loopQty > 0){
                    $cartItems[] = ['item_id' => $source_item->ITEM ,'qty' => 1, 'unit_mrp' => $mrp];
                    $loopQty--;
                }

                $cart_source_qty = $cart_source_item->sum('qty');
                $total_qty += $cart_source_qty;
                
                //dd($cartItems );
            }else{
                $loopQty = $total_qty;
                while($loopQty > 0){
                    $cartItems[] = ['item_id' => $source_item->ITEM ,'qty' => 1, 'unit_mrp' => $mrp];
                    $loopQty--;
                }
            }
            // dd($quantity_collection);

            // Qty Reduction Function

            while ($total_qty > 0) {
                
                if (in_array($total_qty, $quantity_collection)) {
                    // echo 'FIND Qty :- '.$qty.'<br>';
                    $total_qty = $total_qty - $total_qty;
                    array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                } else {
                    
                    //This condtion is added to get best Offers
                    $combination = $this->find_combination($quantity_collection, $total_qty);
                    if(!empty($combination)){

                        $best_combi =['discount' => 0 , 'final_product' => [] ];
                    
                        foreach($combination as $key => $combi){
                            $temp_qty = $total_qty;
                            $best_final_product= [];
                            $total_price = 0;
                            $ex_price = 0;
                            $discount = 0;
                            foreach($combi as $combi_qty){
                                $temp_qty = $temp_qty - $combi_qty;
                                $id = $this->searchQty($combi_qty / $add_qty, $list_ru_prdv);
                                
                                $total_price = $mrp * $combi_qty;
                                $discount = $total_price * $id->PE_UN_ITM_PRDV_SLS / 100;
                                $ex_price = $total_price - $discount;
                                array_push($best_final_product,[ 'qty' => $combi_qty, 'is_promo' => 1 ]);
                            }

                            if($temp_qty > 0 ){
                                array_push($best_final_product,[ 'qty' => $temp_qty, 'is_promo' => 0 ]);
                            }
                            
                            if($best_combi['discount'] < $discount){
                                $best_combi['discount'] = $discount;
                                $best_combi['final_product'] = $best_final_product;
                            }
                        }

                        //dd($best_combi);

                        $final_product = $best_combi['final_product'] ;

                    }else{
                        array_push($final_product,[ 'qty' => $qty, 'is_promo' => 0 ]);
                        
                    }
                    $total_qty = 0;
                    
                }

            }

            $cartItems = collect($cartItems)->sortByDesc('unit_mrp');

            //dd($final_product);
            foreach ($final_product as $key => $value) {
                if ($value['is_promo'] == 1) {
                    $id = $this->searchQty($value['qty'], $list_ru_prdv);
                    // echo '<pre>';
                    //print_r($id);exit;
                    $offer_amount = $id->PE_UN_ITM_PRDV_SLS;
                    $mrp_on_offer = $mrp;
                    $param = [];
                    $params = [];
                    
                    //dd($cartItems);
                    $cartItems = $cartItems->filter(function($q , $key) use (&$value, &$mrp_on_offer, &$offer_amount, &$param, &$params) {
                        $filter_coll_flag = true;
                        if($value['qty'] < $q['qty']){
                            $q['qty'] - $value['qty'];
                            
                            $q['qty'] = $q['qty'] - $value['qty'];
                            $value['qty'] = 0;

                            //dd($q);
                            //echo $q->qty .' - '. $value['qty'];exit;
                            //$q->
                        }else{

                            $value['qty'] -= $q['qty'];
                           // unset($cart_source_item[$key]);
                            $filter_coll_flag = false;

                        }
                        //dd($value);

                        if($filter_coll_flag){
                            return $q;
                        }else{
                            //Findig Lowest mrp
                            if($q['unit_mrp'] < $mrp_on_offer){
                                $mrp_on_offer = $q['unit_mrp'];
                            }
                            
                            if($q['qty'] >1 ){
                                $cQty = $q['qty'];
                                while($cQty > 0){
                                    $params[] = [ 'item_id' => $q['item_id'], 'unit_mrp' => $q['unit_mrp'] ];
                                    $param[] = $q['unit_mrp'];
                                    $cQty--;
                                }
                                
                            }else{
                                $params[] = [ 'item_id' => $q['item_id'], 'unit_mrp' => $q['unit_mrp'] ];
                                $param[] = $q['unit_mrp'];
                            }

                            return null;
                        }
                        
                    });

                    if($value['qty'] ==0){
                        $mrp = $mrp_on_offer;
                    }
                        
                        
                        
                       //exit;
                       //echo $offer_amount; exit;
                    
                    //dd($cartItems);
                    //dd($param);
                    //echo 'mrp:'.$mrp.' Qty:'.$value['qty'].' Offer_amount: '.$offer_amount;exit;
                    $param_count_of_cart = count($param);
                    
                    $offer_amount = $mrp_on_offer * $offer_amount / 100;
                    
                    $ratio_val = $this->get_offer_amount_by_ratio($param, $offer_amount);
                    $ratio_total = array_sum($ratio_val);
                    //dd($ratio_val);
                    $discount = 0;
                    $total_discount = 0;
                    foreach($params as $key => $par){
                        $discount = round( ($ratio_val[$key]/$ratio_total) * $offer_amount , 2);
                        $params[$key]['discount'] =  $discount;
                        $total_discount += $discount;
                    }

                    //Thid code is added because facing issue when rounding of discount value
                    if($total_discount > $offer_amount){
                        $total_diff = $total_discount - $offer_amount;
                        foreach($params as $key => $par){
                            if($total_diff > 0.00){
                                $params[$key]['discount'] -= 0.01;
                                $total_diff -= 0.01;
                            }else{
                                break;
                            }
                        }
                    }else if($total_discount < $offer_amount){
                        $total_diff =  $offer_amount - $total_discount;
                        foreach($params as $key => $par){
                            if($total_diff > 0.00){
                                $params[$key]['discount'] += 0.01;
                                $total_diff -= 0.01;
                            }else{
                                break;
                            }
                        }

                    }

                    $total_qty = 0;
                    $total_price = 0;
                    $discount =0;
                    foreach($params as $key => $par){
                        if($par['item_id'] == $source_item->ITEM ){
                            $total_price += $par['unit_mrp'] * 1;
                            $total_qty += 1;
                            $discount += $par['discount'];
                            $mrp = $par['unit_mrp'];
                        }
                    }

                    $discount = round($discount ,2);
                    //$total_price = $mrp * $value['qty'];
                    //$discount = $offer_amount;
                    $ex_price = $total_price - $discount;                 
                    
                     $message = 'buy any '.$id->QU_TH.', get Lowest item for '.$id->PE_UN_ITM_PRDV_SLS.'% Off';
                        
                        if($total_qty!=0){
                            $final_data['pdata'][] = [ 'qty' => $total_qty, 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message , 'ru_prdv' => $id->ID_RU_PRDV, 'type' => $data[$max_discount]['TYPE'], 'promo_id' => $data[$max_discount]['ID_PRM'], 'type_id' => $data[$max_discount]['ID'], 'is_slab' => ($promo_count == 1 ? 0 : 2), 'is_promo' => 1 ];
                        }
                    //$ex_price = $ex_price * $value['qty'];

                    

                   // dd($final_data);

                    $applied_offer[] = $message;
                    
                } else {

                    $total_price = 0;
                    $total_qty = 0;
                    $params=[];
                    $cartItems = $cartItems->filter(function($q , $key) use(&$params) {

                        $params[] = [ 'item_id' => $q['item_id'], 'unit_mrp' => $q['unit_mrp'] ];

                    });

                    foreach($params as $key => $par){
                        if($par['item_id'] == $source_item->ITEM ){
                            $total_price += $par['unit_mrp'] * 1;
                            $total_qty += 1;
                            $mrp = $par['unit_mrp'];
                        }
                    }

                    if($total_qty > 0){
                        $final_data['pdata'][] = [ 'qty' => $total_qty, 'mrp' => $mrp, 'discount' => 0, 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];
                    }

                    
                    
                }
            }
            //dd($final_data);
            // Check item Already Exits in Cart

            if ($in_cart == 1) {
                $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
            }

            // Available Offer
            $final_data['available_offer'] = $available_offer;

            //dd($final_data);

            // Applied Offer

            $final_data['applied_offer'] = $applied_offer;




        $final_data['item_id']  = $source_item->ITEM;
        //dd($final_data);

        return $final_data;

    }

    public function buy_source_get_source_lowest_percentage_price($mrp, $qty, $ru_prdv, $item_id, $store_id = '20001', $user_id='test', $cart_item= false, $carts){

        //echo $mrp;exit;
        //dd($ru_prdv);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $item_id)->first();
        //dd($source_item);
        //$carts = DB::table('cart')->where('user_id', $user_id)->where('store_id', $store_id)->get();
        if($cart_item){
            //echo 'inside this';exit;
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }
       
        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }

        $total_qty = $qty;


        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                $condition = DB::table($this->store_db_name.'.co_prdv_itm')->select('PE_UN_ITM_PRDV_SLS')->where('ID_RU_PRDV', $value['ru_prdv'])->first();
                if($condition){
                    // echo '<pre>';
                    // print_r($value);
                    // print_r($condition);
                    $total_saving = $mrp * $condition->PE_UN_ITM_PRDV_SLS / 100;
                    //$total_saving = $mrp - $total_saving;
                    
                    $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'], 'ID' => $value['id'] ];
                }
            }
        }
        //dd($data);
        // Find Max Discount

        $max_discount = max(array_keys($data));

        // Check Quantity Based Condition

        if ($data[$max_discount]['TYPE'] == 'co_el') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'co_el_prdv_itm.MO_TH', 'co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM', 'ru_prdv.CD_MTH_PRDV', 'co_prdv_itm.PE_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }
        
        if ($data[$max_discount]['TYPE'] == 'max') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH', 'co_prdv_itm.PE_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV','max_grp_itm_lst.ID_ITM')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();

        }
            $all_sources = $list_ru_prdv->pluck('ID_ITM')->all();
            $list_ru_prdv = $list_ru_prdv->where('ID_ITM', $source_item->ITEM);
            //dd($all_sources);

            $promo_count = count($list_ru_prdv);

            // Get Max Number of Qty

            $max = 0;
            foreach($list_ru_prdv as $obj) {
                if($obj->QU_TH > $max) {
                    $max = number_format($obj->QU_TH);
                }
            }

            // Formart Qty to integer

            $quantity_collection = [];
            $add_qty = 1;
            $source_any_qty = false;
            $available_offer = [];
            $qty_message ='';
            foreach ($list_ru_prdv as $key => $value) {
                //This condition is added when QU_AN_SRC is set 
                if($value->CD_BAS_CMP_SRC == '7'){
                    $source_any_qty = true;
                }

                if($value->QU_AN_SRC > 0){
                    $source_any_qty = true;
                    $add_qty = $value->QU_AN_SRC;
                    $qty_message = ' including any '.$value->QU_AN_SRC;
                }

                $available_offer[number_format($value->QU_TH)] = 'buy any '.$value->QU_TH.', get Lowest item for '.$value->PE_UN_ITM_PRDV_SLS.'% Off';


                $quantity_collection[] = number_format($value->QU_TH * $add_qty);
            }

            //dd($quantity_collection);
            $cart_source_qty = 0;
            $cart_source_item = [];
            if($source_any_qty){
                //Removing current item from sources
                $all_sources = array_diff($all_sources , [$source_item->ITEM]);
                
                //dd($all_sources);
                $cart_source_item = $carts->whereIn('item_id', $all_sources);

                //dd($cart_source_item);
                $cartItems =[];
                foreach($cart_source_item as $ikey => $cartItem){
                    $loopQty = $cartItem->qty;
                    while($loopQty > 0){
                        $cartItems[] = ['item_id' => $cartItem->item_id ,'qty' => 1 , 'unit_mrp' => $cartItem->unit_mrp];
                        $loopQty--;
                    }
                }

                $loopQty = $total_qty;
                while($loopQty > 0){
                    $cartItems[] = ['item_id' => $source_item->ITEM ,'qty' => 1, 'unit_mrp' => $mrp];
                    $loopQty--;
                }

                $cart_source_qty = $cart_source_item->sum('qty');
                $total_qty += $cart_source_qty;
                
                //dd($cartItems );
            }else{
                $loopQty = $total_qty;
                while($loopQty > 0){
                    $cartItems[] = ['item_id' => $source_item->ITEM ,'qty' => 1, 'unit_mrp' => $mrp];
                    $loopQty--;
                }
            }
            // dd($quantity_collection);

            // Qty Reduction Function

            while ($total_qty > 0) {
                
                if (in_array($total_qty, $quantity_collection)) {
                    // echo 'FIND Qty :- '.$qty.'<br>';
                    $total_qty = $total_qty - $total_qty;
                    array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                } else {
                    
                    //This condtion is added to get best Offers
                    $combination = $this->find_combination($quantity_collection, $total_qty);
                    if(!empty($combination)){

                        $best_combi =['discount' => 0 , 'final_product' => [] ];
                    
                        foreach($combination as $key => $combi){
                            $temp_qty = $total_qty;
                            $best_final_product= [];
                            $total_price = 0;
                            $ex_price = 0;
                            $discount = 0;
                            foreach($combi as $combi_qty){
                                $temp_qty = $temp_qty - $combi_qty;
                                $id = $this->searchQty($combi_qty / $add_qty, $list_ru_prdv);
                                
                                $total_price = $mrp * $combi_qty;
                                $discount = $total_price * $id->PE_UN_ITM_PRDV_SLS / 100;
                                $ex_price = $total_price - $discount;
                                array_push($best_final_product,[ 'qty' => $combi_qty, 'is_promo' => 1 ]);
                            }

                            if($temp_qty > 0 ){
                                array_push($best_final_product,[ 'qty' => $temp_qty, 'is_promo' => 0 ]);
                            }
                            
                            if($best_combi['discount'] < $discount){
                                $best_combi['discount'] = $discount;
                                $best_combi['final_product'] = $best_final_product;
                            }
                        }

                        //dd($best_combi);

                        $final_product = $best_combi['final_product'] ;

                    }else{
                        array_push($final_product,[ 'qty' => $qty, 'is_promo' => 0 ]);
                        
                    }
                    $total_qty = 0;
                    
                }

            }

            $cartItems = collect($cartItems)->sortByDesc('unit_mrp');

            //dd($final_product);
            foreach ($final_product as $key => $value) {
                if ($value['is_promo'] == 1) {
                    $id = $this->searchQty($value['qty'], $list_ru_prdv);
                    // echo '<pre>';
                    //print_r($id);exit;
                    $offer_amount = $id->PE_UN_ITM_PRDV_SLS;
                    $mrp_on_offer = $mrp;
                    $param = [];
                    $params = [];
                    
                    //dd($cartItems);
                    $cartItems = $cartItems->filter(function($q , $key) use (&$value, &$mrp_on_offer, &$offer_amount, &$param, &$params) {
                        $filter_coll_flag = true;
                        if($value['qty'] < $q['qty']){
                            $q['qty'] - $value['qty'];
                            
                            $q['qty'] = $q['qty'] - $value['qty'];
                            $value['qty'] = 0;

                            //dd($q);
                            //echo $q->qty .' - '. $value['qty'];exit;
                            //$q->
                        }else{

                            $value['qty'] -= $q['qty'];
                           // unset($cart_source_item[$key]);
                            $filter_coll_flag = false;

                        }
                        //dd($value);

                        if($filter_coll_flag){
                            return $q;
                        }else{
                            //Findig Lowest mrp
                            if($q['unit_mrp'] < $mrp_on_offer){
                                $mrp_on_offer = $q['unit_mrp'];
                            }
                            
                            if($q['qty'] >1 ){
                                $cQty = $q['qty'];
                                while($cQty > 0){
                                    $params[] = [ 'item_id' => $q['item_id'], 'unit_mrp' => $q['unit_mrp'] ];
                                    $param[] = $q['unit_mrp'];
                                    $cQty--;
                                }
                                
                            }else{
                                $params[] = [ 'item_id' => $q['item_id'], 'unit_mrp' => $q['unit_mrp'] ];
                                $param[] = $q['unit_mrp'];
                            }

                            return null;
                        }
                        
                    });

                    if($value['qty'] ==0){
                        $mrp = $mrp_on_offer;
                    }
                        
                        
                        
                       //exit;
                       //echo $offer_amount; exit;
                    
                    //dd($cartItems);
                    //dd($param);
                    //echo 'mrp:'.$mrp.' Qty:'.$value['qty'].' Offer_amount: '.$offer_amount;exit;
                    $param_count_of_cart = count($param);
                    
                    $offer_amount = $mrp_on_offer * $offer_amount / 100;
                    
                    $ratio_val = $this->get_offer_amount_by_ratio($param, $offer_amount);
                    $ratio_total = array_sum($ratio_val);
                    //dd($ratio_val);
                    $discount = 0;
                    $total_discount = 0;
                    foreach($params as $key => $par){
                        $discount = round( ($ratio_val[$key]/$ratio_total) * $offer_amount , 2);
                        $params[$key]['discount'] =  $discount;
                        $total_discount += $discount;
                    }

                    //Thid code is added because facing issue when rounding of discount value
                    if($total_discount > $offer_amount){
                        $total_diff = $total_discount - $offer_amount;
                        foreach($params as $key => $par){
                            if($total_diff > 0.00){
                                $params[$key]['discount'] -= 0.01;
                                $total_diff -= 0.01;
                            }else{
                                break;
                            }
                        }
                    }else if($total_discount < $offer_amount){
                        $total_diff =  $offer_amount - $total_discount;
                        foreach($params as $key => $par){
                            if($total_diff > 0.00){
                                $params[$key]['discount'] += 0.01;
                                $total_diff -= 0.01;
                            }else{
                                break;
                            }
                        }

                    }

                    $total_qty = 0;
                    $total_price = 0;
                    $discount =0;
                    foreach($params as $key => $par){
                        if($par['item_id'] == $source_item->ITEM ){
                            $total_price += $par['unit_mrp'] * 1;
                            $total_qty += 1;
                            $discount += $par['discount'];
                            $mrp = $par['unit_mrp'];
                        }
                    }

                    $discount = round($discount ,2);
                    //$total_price = $mrp * $value['qty'];
                    //$discount = $offer_amount;
                    $ex_price = $total_price - $discount;                 
                    
                     $message = 'buy any '.$id->QU_TH.', get Lowest item for '.$id->PE_UN_ITM_PRDV_SLS.'% Off';
                        
                        if($total_qty!=0){
                            $final_data['pdata'][] = [ 'qty' => $total_qty, 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message , 'ru_prdv' => $id->ID_RU_PRDV, 'type' => $data[$max_discount]['TYPE'], 'promo_id' => $data[$max_discount]['ID_PRM'], 'type_id' => $data[$max_discount]['ID'], 'is_slab' => ($promo_count == 1 ? 0 : 2), 'is_promo' => 1 ];
                        }
                    //$ex_price = $ex_price * $value['qty'];

                    

                   // dd($final_data);

                    $applied_offer[] = $message;
                    
                } else {

                    $total_price = 0;
                    $total_qty = 0;
                    $params=[];
                    $cartItems = $cartItems->filter(function($q , $key) use(&$params) {

                        $params[] = [ 'item_id' => $q['item_id'], 'unit_mrp' => $q['unit_mrp'] ];

                    });

                    foreach($params as $key => $par){
                        if($par['item_id'] == $source_item->ITEM ){
                            $total_price += $par['unit_mrp'] * 1;
                            $total_qty += 1;
                            $mrp = $par['unit_mrp'];
                        }
                    }

                    if($total_qty > 0){
                        $final_data['pdata'][] = [ 'qty' => $total_qty, 'mrp' => $mrp, 'discount' => 0, 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];
                    }

                    
                    
                }
            }
            //dd($final_data);
            // Check item Already Exits in Cart

            if ($in_cart == 1) {
                $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
            }

            // Available Offer
            $final_data['available_offer'] = $available_offer;

            //dd($final_data);

            // Applied Offer

            $final_data['applied_offer'] = $applied_offer;




        $final_data['item_id']  = $source_item->ITEM;
        //dd($final_data);

        return $final_data;

    }

    public function buy_source_get_higest_percentage_price($mrp, $qty, $ru_prdv, $item_id, $store_id = '20001', $user_id='test', $cart_item= false, $carts){

        //echo $mrp;exit;
        //dd($ru_prdv);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $item_id)->first();
        //dd($source_item);
        //$carts = DB::table('cart')->where('user_id', $user_id)->where('store_id', $store_id)->get();
        if($cart_item){
            //echo 'inside this';exit;
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }
       
        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }

        $total_qty = $qty;


        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                $condition = DB::table($this->store_db_name.'.co_prdv_itm')->select('PE_UN_ITM_PRDV_SLS')->where('ID_RU_PRDV', $value['ru_prdv'])->first();
                if($condition){
                    // echo '<pre>';
                    // print_r($value);
                    // print_r($condition);
                    $total_saving = $mrp * $condition->PE_UN_ITM_PRDV_SLS / 100;
                    //$total_saving = $mrp - $total_saving;
                    
                    $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'], 'ID' => $value['id'] ];
                }
            }
        }
        //dd($data);
        // Find Max Discount

        $max_discount = max(array_keys($data));

        // Check Quantity Based Condition

        if ($data[$max_discount]['TYPE'] == 'co_el') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'co_el_prdv_itm.MO_TH', 'co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM', 'ru_prdv.CD_MTH_PRDV', 'co_prdv_itm.PE_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }
        
        if ($data[$max_discount]['TYPE'] == 'max') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH', 'co_prdv_itm.PE_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV','max_grp_itm_lst.ID_ITM')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();

        }
            $all_sources = $list_ru_prdv->pluck('ID_ITM')->all();
            $list_ru_prdv = $list_ru_prdv->where('ID_ITM', $source_item->ITEM);
            //dd($all_sources);

            $promo_count = count($list_ru_prdv);

            // Get Max Number of Qty

            $max = 0;
            foreach($list_ru_prdv as $obj) {
                if($obj->QU_TH > $max) {
                    $max = number_format($obj->QU_TH);
                }
            }

            // Formart Qty to integer

            $quantity_collection = [];
            $add_qty = 1;
            $source_any_qty = false;
            $available_offer = [];
            $qty_message ='';
            foreach ($list_ru_prdv as $key => $value) {
                //This condition is added when QU_AN_SRC is set 
                if($value->CD_BAS_CMP_SRC == '7'){
                    $source_any_qty = true;
                }

                if($value->QU_AN_SRC > 0){
                    $source_any_qty = true;
                    $add_qty = $value->QU_AN_SRC;
                    $qty_message = ' including any '.$value->QU_AN_SRC;
                }

                $available_offer[number_format($value->QU_TH)] = 'buy any '.$value->QU_TH.', get Higest item for '.$value->PE_UN_ITM_PRDV_SLS.'% Off';


                $quantity_collection[] = number_format($value->QU_TH * $add_qty);
            }

            //dd($quantity_collection);
            $cart_source_qty = 0;
            $cart_source_item = [];
            if($source_any_qty){
                //Removing current item from sources
                $all_sources = array_diff($all_sources , [$source_item->ITEM]);
                
                //dd($all_sources);
                $cart_source_item = $carts->whereIn('item_id', $all_sources);

                //dd($cart_source_item);
                $cartItems =[];
                foreach($cart_source_item as $ikey => $cartItem){
                    $loopQty = $cartItem->qty;
                    while($loopQty > 0){
                        $cartItems[] = ['item_id' => $cartItem->item_id ,'qty' => 1 , 'unit_mrp' => $cartItem->unit_mrp];
                        $loopQty--;
                    }
                }

                $loopQty = $total_qty;
                while($loopQty > 0){
                    $cartItems[] = ['item_id' => $source_item->ITEM ,'qty' => 1, 'unit_mrp' => $mrp];
                    $loopQty--;
                }

                $cart_source_qty = $cart_source_item->sum('qty');
                $total_qty += $cart_source_qty;
                
                //dd($cartItems );
            }else{
                $loopQty = $total_qty;
                while($loopQty > 0){
                    $cartItems[] = ['item_id' => $source_item->ITEM ,'qty' => 1, 'unit_mrp' => $mrp];
                    $loopQty--;
                }
            }
            // dd($quantity_collection);

            // Qty Reduction Function

            while ($total_qty > 0) {
                
                if (in_array($total_qty, $quantity_collection)) {
                    // echo 'FIND Qty :- '.$qty.'<br>';
                    $total_qty = $total_qty - $total_qty;
                    array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                } else {
                    
                    //This condtion is added to get best Offers
                    $combination = $this->find_combination($quantity_collection, $total_qty);
                    if(!empty($combination)){

                        $best_combi =['discount' => 0 , 'final_product' => [] ];
                    
                        foreach($combination as $key => $combi){
                            $temp_qty = $total_qty;
                            $best_final_product= [];
                            $total_price = 0;
                            $ex_price = 0;
                            $discount = 0;
                            foreach($combi as $combi_qty){
                                $temp_qty = $temp_qty - $combi_qty;
                                $id = $this->searchQty($combi_qty / $add_qty, $list_ru_prdv);
                                
                                $total_price = $mrp * $combi_qty;
                                $discount = $total_price * $id->PE_UN_ITM_PRDV_SLS / 100;
                                $ex_price = $total_price - $discount;
                                array_push($best_final_product,[ 'qty' => $combi_qty, 'is_promo' => 1 ]);
                            }

                            if($temp_qty > 0 ){
                                array_push($best_final_product,[ 'qty' => $temp_qty, 'is_promo' => 0 ]);
                            }
                            
                            if($best_combi['discount'] < $discount){
                                $best_combi['discount'] = $discount;
                                $best_combi['final_product'] = $best_final_product;
                            }
                        }

                        //dd($best_combi);

                        $final_product = $best_combi['final_product'] ;

                    }else{
                        array_push($final_product,[ 'qty' => $qty, 'is_promo' => 0 ]);
                        
                    }
                    $total_qty = 0;
                    
                }

            }

            $cartItems = collect($cartItems)->sortByDesc('unit_mrp');

            //dd($final_product);
            foreach ($final_product as $key => $value) {
                if ($value['is_promo'] == 1) {
                    $id = $this->searchQty($value['qty'], $list_ru_prdv);
                    // echo '<pre>';
                    //print_r($id);exit;
                    $offer_amount = $id->PE_UN_ITM_PRDV_SLS;
                    $mrp_on_offer = $mrp;
                    $param = [];
                    $params = [];
                    
                    //dd($cartItems);
                    $cartItems = $cartItems->filter(function($q , $key) use (&$value, &$mrp_on_offer, &$offer_amount, &$param, &$params) {
                        $filter_coll_flag = true;
                        if($value['qty'] < $q['qty']){
                            $q['qty'] - $value['qty'];
                            
                            $q['qty'] = $q['qty'] - $value['qty'];
                            $value['qty'] = 0;

                            //dd($q);
                            //echo $q->qty .' - '. $value['qty'];exit;
                            //$q->
                        }else{

                            $value['qty'] -= $q['qty'];
                           // unset($cart_source_item[$key]);
                            $filter_coll_flag = false;

                        }
                        //dd($value);

                        if($filter_coll_flag){
                            return $q;
                        }else{
                            //Findig Higest mrp
                            if($q['unit_mrp'] > $mrp_on_offer){
                                $mrp_on_offer = $q['unit_mrp'];
                            }
                            
                            if($q['qty'] >1 ){
                                $cQty = $q['qty'];
                                while($cQty > 0){
                                    $params[] = [ 'item_id' => $q['item_id'], 'unit_mrp' => $q['unit_mrp'] ];
                                    $param[] = $q['unit_mrp'];
                                    $cQty--;
                                }
                                
                            }else{
                                $params[] = [ 'item_id' => $q['item_id'], 'unit_mrp' => $q['unit_mrp'] ];
                                $param[] = $q['unit_mrp'];
                            }

                            return null;
                        }
                        
                    });

                    if($value['qty'] ==0){
                        $mrp = $mrp_on_offer;
                    }
                        
                        
                        
                       //exit;
                       //echo $offer_amount; exit;
                    
                    //dd($cartItems);
                    //dd($param);
                    //echo 'mrp:'.$mrp.' Qty:'.$value['qty'].' Offer_amount: '.$offer_amount;exit;
                    $param_count_of_cart = count($param);
                    
                    $offer_amount = $mrp_on_offer * $offer_amount / 100;
                    
                    $ratio_val = $this->get_offer_amount_by_ratio($param, $offer_amount);
                    $ratio_total = array_sum($ratio_val);
                    //dd($ratio_val);
                    $discount = 0;
                    $total_discount = 0;
                    foreach($params as $key => $par){
                        $discount = round( ($ratio_val[$key]/$ratio_total) * $offer_amount , 2);
                        $params[$key]['discount'] =  $discount;
                        $total_discount += $discount;
                    }

                    //Thid code is added because facing issue when rounding of discount value
                    if($total_discount > $offer_amount){
                        $total_diff = $total_discount - $offer_amount;
                        foreach($params as $key => $par){
                            if($total_diff > 0.00){
                                $params[$key]['discount'] -= 0.01;
                                $total_diff -= 0.01;
                            }else{
                                break;
                            }
                        }
                    }else if($total_discount < $offer_amount){
                        $total_diff =  $offer_amount - $total_discount;
                        foreach($params as $key => $par){
                            if($total_diff > 0.00){
                                $params[$key]['discount'] += 0.01;
                                $total_diff -= 0.01;
                            }else{
                                break;
                            }
                        }

                    }

                    $total_qty = 0;
                    $total_price = 0;
                    $discount =0;
                    foreach($params as $key => $par){
                        if($par['item_id'] == $source_item->ITEM ){
                            $total_price += $par['unit_mrp'] * 1;
                            $total_qty += 1;
                            $discount += $par['discount'];
                            $mrp = $par['unit_mrp'];
                        }
                    }

                    $discount = round($discount ,2);
                    //$total_price = $mrp * $value['qty'];
                    //$discount = $offer_amount;
                    $ex_price = $total_price - $discount;                 
                    
                     $message = 'buy any '.$id->QU_TH.', get Higest item for '.$id->PE_UN_ITM_PRDV_SLS.'% Off';
                        
                        if($total_qty!=0){
                            $final_data['pdata'][] = [ 'qty' => $total_qty, 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message , 'ru_prdv' => $id->ID_RU_PRDV, 'type' => $data[$max_discount]['TYPE'], 'promo_id' => $data[$max_discount]['ID_PRM'], 'type_id' => $data[$max_discount]['ID'], 'is_slab' => ($promo_count == 1 ? 0 : 2), 'is_promo' => 1 ];
                        }
                    //$ex_price = $ex_price * $value['qty'];

                    

                   // dd($final_data);

                    $applied_offer[] = $message;
                    
                } else {

                    $total_price = 0;
                    $total_qty = 0;
                    $params=[];
                    $cartItems = $cartItems->filter(function($q , $key) use(&$params) {

                        $params[] = [ 'item_id' => $q['item_id'], 'unit_mrp' => $q['unit_mrp'] ];

                    });

                    foreach($params as $key => $par){
                        if($par['item_id'] == $source_item->ITEM ){
                            $total_price += $par['unit_mrp'] * 1;
                            $total_qty += 1;
                            $mrp = $par['unit_mrp'];
                        }
                    }

                    if($total_qty > 0){
                        $final_data['pdata'][] = [ 'qty' => $total_qty, 'mrp' => $mrp, 'discount' => 0, 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];
                    }

                    
                    
                }
            }
            //dd($final_data);
            // Check item Already Exits in Cart

            if ($in_cart == 1) {
                $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
            }

            // Available Offer
            $final_data['available_offer'] = $available_offer;

            //dd($final_data);

            // Applied Offer

            $final_data['applied_offer'] = $applied_offer;




        $final_data['item_id']  = $source_item->ITEM;
        //dd($final_data);

        return $final_data;

    }

    public function shop_amount_get_source_amount_tiered($mrp, $qty, $ru_prdv, $item_id, $store_id = '20001', $user_id='test',$cart_item = true, $carts)
    {
        //dd($ru_prdv);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $item_id)->first();
       // $carts = DB::table('cart')->where('user_id', $user_id)->where('store_id', $store_id)->get();
        if($cart_item){
            $check_product_in_cart =[];
        }else{
          
          $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }
        

        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }

        $total_qty = $qty;

        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        $offer_value = [];
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                $condition = DB::table($this->store_db_name.'.co_prdv_itm')->select('MO_UN_ITM_PRDV_SLS')->where('ID_RU_PRDV', $value['ru_prdv'])->first();
                // echo '<pre>';
                // print_r($value);
                // print_r($condition);
                $total_saving = $mrp - $condition->MO_UN_ITM_PRDV_SLS;
                
                $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'], 'ID' => $value['id'] ];
            }
        }

        // Find Max Discount

        $max_discount = max(array_keys($data));

        // Check Quantity Based Condition

        if ($data[$max_discount]['TYPE'] == 'co_el') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'co_el_prdv_itm.MO_TH', 'co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM', 'ru_prdv.CD_MTH_PRDV', 'co_prdv_itm.MO_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }

        if ($data[$max_discount]['TYPE'] == 'max') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH', 'co_prdv_itm.MO_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV','max_grp_itm_lst.ID_ITM')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }
            //dd($list_ru_prdv);
            $all_sources = array_unique($list_ru_prdv->pluck('ID_ITM')->all() );
            $list_ru_prdv = $list_ru_prdv->where('ID_ITM', $source_item->ITEM);
            //dd($list_ru_prdv);

            $promo_count = count($list_ru_prdv);
            // Get Max Number of Qty

            $amount_collection = [];

            foreach ($list_ru_prdv as $key => $value) {
                $amount_collection[] = $value->MO_TH;
                sort($amount_collection);
            }

            
            
            //$carts = $carts->whereIn('item_id', $all_sources);
            $all_sources = array_diff($all_sources , [$source_item->ITEM]);
           // dd($all_sources);
            $cart_source_item = $carts->whereIn('item_id', $all_sources);
            $cart_totals = $cart_source_item->sum('total') ;
            //if($cart_item == false){
                $cart_totals += $mrp * $qty;
            //}


            $cartItems =[];
            foreach($cart_source_item as $ikey => $cartItem){
                $loopQty = $cartItem->qty;
                while($loopQty > 0){
                    $cartItems[] = ['item_id' => $cartItem->item_id ,'qty' => 1 , 'unit_mrp' => $cartItem->unit_mrp];
                    $loopQty--;
                }
            }

            $loopQty = $total_qty;
            while($loopQty > 0){
                $cartItems[] = ['item_id' => $source_item->ITEM ,'qty' => 1, 'unit_mrp' => $mrp];
                $loopQty--;
            }

            $offer_value = [  'is_promo' => 0];
            foreach ($amount_collection as $amount) {
                if($cart_totals >= $amount ){
                    $offer_value = [ 'amount' => $amount ,  'is_promo' => 1];
                }
            }
            $final_product[] = $offer_value;


            //dd($cartItems);
           // $final_product = $best_combi['final_product'] ;  

           //dd($final_product);
            $cartItems = collect($cartItems);
            foreach ($final_product as $key => $value) {
                if ( $value['is_promo'] == 1) {
                    $id = $this->searchAmount($value['amount'] , $list_ru_prdv);
                    // echo '<pre>';
                     //print_r($id);
                    $param= [];
                    $params= [];
                    $cartItems = $cartItems->filter(function($q , $key) use (&$param, &$params) {
                       
                            
                            $params[] = [ 'item_id' => $q['item_id'], 'unit_mrp' => $q['unit_mrp'] ];
                            $param[] = $q['unit_mrp'];
                                  
                            return null;
                    
                    });

                    $offer_amount = $id->MO_UN_ITM_PRDV_SLS;
                    
                    $ratio_val = $this->get_offer_amount_by_ratio($param, $offer_amount);
                    $ratio_total = array_sum($ratio_val);
                    //dd($ratio_val);
                    $discount = 0;
                    $total_discount = 0;
                    foreach($params as $key => $par){
                        $discount = round( ($ratio_val[$key]/$ratio_total) * $offer_amount , 2);
                        $params[$key]['discount'] =  $discount;
                        $total_discount += $discount;
                    }

                    //Thid code is added because facing issue when rounding of discount value
                    if($total_discount > $offer_amount){
                        $total_diff = $total_discount - $offer_amount;
                        foreach($params as $key => $par){
                            if($total_diff > 0.00){
                                $params[$key]['discount'] -= 0.01;
                                $total_diff -= 0.01;
                            }else{
                                break;
                            }
                        }
                    }else if($total_discount < $offer_amount){
                        $total_diff =  $offer_amount - $total_discount;
                        foreach($params as $key => $par){
                            if($total_diff > 0.00){
                                $params[$key]['discount'] += 0.01;
                                $total_diff -= 0.01;
                            }else{
                                break;
                            }
                        }

                    }

                    $total_qty = 0;
                    $total_price = 0;
                    $discount =0;
                    foreach($params as $key => $par){
                        if($par['item_id'] == $source_item->ITEM ){
                            $total_price += $par['unit_mrp'] * 1;
                            $total_qty += 1;
                            $discount += $par['discount'];
                            $mrp = $par['unit_mrp'];
                        }
                    }

                    $discount = round($discount ,2);
                    //$total_price = $mrp * $value['qty'];
                    //$discount = $offer_amount;
                    $ex_price = $total_price - $discount;  
                    
                    if($total_qty> 0){
        
                        $message = 'shop for '.$id->MO_TH.' above get  '.$id->MO_UN_ITM_PRDV_SLS.' Rs Off';
                        $final_data['pdata'][] = [ 'qty' => $total_qty, 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message , 'ru_prdv' => $id->ID_RU_PRDV, 'type' => $data[$max_discount]['TYPE'], 'promo_id' => $data[$max_discount]['ID_PRM'], 'type_id' => $data[$max_discount]['ID'], 'is_slab' => ($promo_count == 1 ? 0 : 2), 'is_promo' => 1 ];

                        $applied_offer[] = $message;
                    }
                    
                } else {


                    $total_price = 0;
                    $total_qty = 0;
                    $params=[];
                    $cartItems = $cartItems->filter(function($q , $key) use(&$params) {

                        $params[] = [ 'item_id' => $q['item_id'], 'unit_mrp' => $q['unit_mrp'] ];

                    });

                    foreach($params as $key => $par){
                        if($par['item_id'] == $source_item->ITEM ){
                            $total_price += $par['unit_mrp'] * 1;
                            $total_qty += 1;
                            $mrp = $par['unit_mrp'];
                        }
                    }

                    if($total_qty > 0){
                        $total_price = $mrp * $qty;
                        $final_data['pdata'][] = [ 'qty' => $total_qty, 'mrp' => $mrp, 'discount' => 0, 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '' , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0];
                    }
                }
            }



            //dd($final_data);
            // Check item Already Exits in Cart

            if ($in_cart == 1) {
                $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
            }

            // Available Offer

            $available_offer = [];

            foreach ($list_ru_prdv as $key => $value) {
                $available_offer[number_format($value->MO_TH)] = 'Shop of '.number_format($value->MO_TH).' above get '.$value->MO_UN_ITM_PRDV_SLS.' Rs Off';
            }

            $final_data['available_offer'] = $available_offer;

            // dd($final_qty);

            // Applied Offer

            $final_data['applied_offer'] = $applied_offer;

        

        //dd($final_data);
        $final_data['item_id']  = $source_item->ITEM;

        return $final_data;
    }


    public function calculate_target_offer_of_fixed_price($param){

        //dd($param['offer']);
        $item_id = $param['item_id'];
        $price_master = $param['price_master'];
        $rule = $param['offer'];
        $mrp = $param['mrp'];
        $qty = $param['qty'];
        $source_item = $param['source_item'];
        $carts = $param['carts'];
        $cart_item = $param['cart_item'];

        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }
        
        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }
        // dd($qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        //$value = (array)$param['offer'];
        // Find Max Discount

        $all_sources = $rule->product_list;
        $all_sources_check = true;
        $source_cart_item_list = $carts->whereIn('item_id', $all_sources);
        $source_sum = $source_cart_item_list->sum('qty');
        $cart_item_list = $source_cart_item_list->pluck('qty','item_id')->all();
        if($rule->ITM_PRC_CTGY_SRC == 'allSources' || $rule->MAX_ALL_SOURCES == 'allSources'){

            //dd($cart_item_list);
            foreach($all_sources as $sources){
                if(isset($cart_item_list[$sources]) ){
                    
                    if( $cart_item_list[$sources] >= $rule->QU_TH ){

                    }else{
                       $all_sources_check = false; 
                       break;
                    }
                }else{
                    $all_sources_check = false; 
                    break;
                }

            }

            if( $qty >= $rule->QU_LM_MXMH){

            }else{
                $all_sources_check = false; 
            }
            
            
        }



        $quantity_collection[] = $rule->QU_LM_MXMH;
        //dd($quantity_collection);
        $max = max($quantity_collection);
        if($all_sources_check){
            
            while ($qty > 0) {
                
                if (in_array($qty, $quantity_collection)) {
                     //echo 'FIND Qty :- '.$qty.'<br>';  exit;

                    if($rule->ITM_PRC_CTGY_SRC == 'allSources' || $rule->MAX_ALL_SOURCES == 'allSources'){
                        $off_apply_flag = true;
                        foreach($all_sources as $sources){
                            
                            if(isset($cart_item_list[$sources])){
                                if($cart_item_list[$sources] >= $rule->QU_TH ){
                                    $cart_item_list[$sources] = $cart_item_list[$sources] - $max;
                                }else{
                                    $off_apply_flag = false;
                                }
                            }
                        }
                        if($off_apply_flag){
                            array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                        }else{
                            array_push($final_product,[ 'qty' => $max, 'is_promo' => 0 ]);
                        }

                        
                    }else{
                       if($source_sum >= $rule->QU_TH && $qty >= $rule->QU_LM_MXMH){
                            $off_apply_flag = true;
                            $source_sum = $source_sum - $rule->QU_TH;
                            
                        }else{
                            $off_apply_flag = false;
                        }
                        
                        if($off_apply_flag){
                            array_push($final_product,[ 'qty' => $qty, 'is_promo' => 1 ]);
                        }else{
                            array_push($final_product,[ 'qty' => $qty, 'is_promo' => 0 ]);
                        }
                    }
                    
                    $qty = $qty - $qty;
                } else {
                    if ($qty > $max) {
                        // echo 'LARGE Qty :- '.$max.'<br>';
                        $qty = $qty - $max;
                        if($rule->ITM_PRC_CTGY_SRC == 'allSources' || $rule->MAX_ALL_SOURCES == 'allSources'){
                            $off_apply_flag = true;
                            foreach($all_sources as $sources){
                                
                                if(isset($cart_item_list[$sources])){
                                    if($cart_item_list[$sources] >= $rule->QU_TH ){
                                        $cart_item_list[$sources] = $cart_item_list[$sources] - $max;
                                    }else{
                                        $off_apply_flag = false;
                                    }
                                }
                            }
                            
                            if($off_apply_flag){
                                array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                            }else{
                                array_push($final_product,[ 'qty' => $max, 'is_promo' => 0 ]);
                            }

                            
                        }else{

                            if($source_sum >= $rule->QU_TH && $qty >= $rule->QU_LM_MXMH){
                                $off_apply_flag = true;
                                $source_sum = $source_sum - $rule->QU_TH;
                                
                            }else{
                                $off_apply_flag = false;
                            }
                            
                            if($off_apply_flag){
                                array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                            }else{
                                array_push($final_product,[ 'qty' => $max, 'is_promo' => 0 ]);
                            }
                            
                        }
                        
                    } else {
                        array_push($final_product,[ 'qty' => $qty, 'is_promo' => 0 ]);
                        $qty = 0;
                    }
                }

            }

            //dd($final_product);

        
            foreach ($final_product as $key => $value) {
                if ($value['is_promo'] == 1) {
                    $id = $param['offer'];
                    $total_price = $mrp * $value['qty'];
                    $ex_price = $id->PNT_PRC_UN_ITM_PRDV_SLS * $value['qty'];
                    $discount = $total_price - $ex_price; 
                    $message = 'buy '.$value['qty'].' each , get '.$id->QU_LM_MXMH.' '.$price_master->ITEM_DESC.' for @ Rs. '.$id->PNT_PRC_UN_ITM_PRDV_SLS;
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 1 ];
                        $applied_offer[] = $message;

                }else{

                    $total_price = $value['qty'] * $mrp;
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => '', 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];

                }

            }

        }else{

            $total = $mrp * $qty;
            $final_data['pdata'][] = [ 'qty' => $qty, 'mrp' => $mrp, 'discount' => 0, 'ex_price' => $total, 'total_price' => $total, 'message' => '' , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0];
            
            $final_data['item_id'] = $price_master->ITEM;
        }
                
        //dd($final_data);
            // Check item Already Exits in Cart

        if ($in_cart == 1) {
            $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
        }

        // Available Offer

        $available_offer = [];


        $final_data['available_offer'] = $applied_offer;

        // dd($final_qty);

        // Applied Offer

        $final_data['applied_offer'] = $applied_offer;

        $final_data['item_id']  = $source_item->ITEM;

        //dd($final_data);
        return $final_data;


    }

    public function calculate_target_offer_of_percentage($param){

        //dd($param['offer']);
        $item_id = $param['item_id'];
        $price_master = $param['price_master'];
        $rule = $param['offer'];
        $mrp = $param['mrp'];
        $qty = $param['qty'];
        $source_item = $param['source_item'];
        $carts = $param['carts'];
        $cart_item = $param['cart_item'];
        if($cart_item){
            $check_product_in_cart =[];
        }else{
          $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }
        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }
        // dd($qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        //$value = (array)$param['offer'];
        // Find Max Discount

        $all_sources = $rule->product_list;
        $all_sources_check = true;
        $source_cart_item_list = $carts->whereIn('item_id', $all_sources);
        $source_sum = $source_cart_item_list->sum('qty');
        $cart_item_list = $source_cart_item_list->pluck('qty','item_id')->all();
        if($rule->ITM_PRC_CTGY_SRC == 'allSources' || $rule->MAX_ALL_SOURCES == 'allSources'){

            //dd($cart_item_list);
            foreach($all_sources as $sources){
                if(isset($cart_item_list[$sources]) ){
                    
                    if( $cart_item_list[$sources] >= $rule->QU_TH ){

                    }else{
                       $all_sources_check = false; 
                    }
                }

            }

            if( $qty >= $rule->QU_LM_MXMH){

            }else{
                $all_sources_check = false; 
            }
            
            
        }

        $quantity_collection[] = $rule->QU_LM_MXMH;
        //dd($quantity_collection);
        $max = max($quantity_collection);
        if($all_sources_check){
            
            while ($qty > 0) {
                
                if (in_array($qty, $quantity_collection)) {
                     //echo 'FIND Qty :- '.$qty.'<br>';  exit;

                    if($rule->ITM_PRC_CTGY_SRC == 'allSources' || $rule->MAX_ALL_SOURCES == 'allSources'){
                        $off_apply_flag = true;
                        foreach($all_sources as $sources){
                            
                            if(isset($cart_item_list[$sources])){
                                if($cart_item_list[$sources] >= $rule->QU_TH ){
                                    $cart_item_list[$sources] = $cart_item_list[$sources] - $max;
                                }else{
                                    $off_apply_flag = false;
                                }
                            }
                        }
                        if($off_apply_flag){
                            array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                        }else{
                            array_push($final_product,[ 'qty' => $max, 'is_promo' => 0 ]);
                        }

                        
                    }else{
                       if($source_sum >= $rule->QU_TH && $qty >= $rule->QU_LM_MXMH){
                            $off_apply_flag = true;
                            $source_sum = $source_sum - $rule->QU_TH;
                            
                        }else{
                            $off_apply_flag = false;
                        }
                        
                        if($off_apply_flag){
                            array_push($final_product,[ 'qty' => $qty, 'is_promo' => 1 ]);
                        }else{
                            array_push($final_product,[ 'qty' => $qty, 'is_promo' => 0 ]);
                        }
                    }
                    
                    $qty = $qty - $qty;
                } else {
                    if ($qty > $max) {
                        // echo 'LARGE Qty :- '.$max.'<br>';
                        $qty = $qty - $max;
                        if($rule->ITM_PRC_CTGY_SRC == 'allSources' || $rule->MAX_ALL_SOURCES == 'allSources'){
                            $off_apply_flag = true;
                            foreach($all_sources as $sources){
                                
                                if(isset($cart_item_list[$sources])){
                                    if($cart_item_list[$sources] >= $rule->QU_TH ){
                                        $cart_item_list[$sources] = $cart_item_list[$sources] - $max;
                                    }else{
                                        $off_apply_flag = false;
                                    }
                                }
                            }
                            
                            if($off_apply_flag){
                                array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                            }else{
                                array_push($final_product,[ 'qty' => $max, 'is_promo' => 0 ]);
                            }

                            
                        }else{

                            if($source_sum >= $rule->QU_TH && $qty >= $rule->QU_LM_MXMH){
                                $off_apply_flag = true;
                                $source_sum = $source_sum - $rule->QU_TH;
                                
                            }else{
                                $off_apply_flag = false;
                            }
                            
                            if($off_apply_flag){
                                array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                            }else{
                                array_push($final_product,[ 'qty' => $max, 'is_promo' => 0 ]);
                            }
                            
                        }
                        
                    } else {
                        array_push($final_product,[ 'qty' => $qty, 'is_promo' => 0 ]);
                        $qty = 0;
                    }
                }

            }

            //dd($final_product);

        
            foreach ($final_product as $key => $value) {
                if ($value['is_promo'] == 1) {
                    $id = $param['offer'];

                     $message_each_text = '';
                    if($id->ITM_PRC_CTGY_SRC == 'allSources' || $id->MAX_ALL_SOURCES == 'allSources'){
                        $message_each_text = ' each ';
                    }
                    $total_price = $mrp * $value['qty'];
                    $discount = $mrp * $id->PE_UN_ITM_PRDV_SLS / 100;
                    $ex_price = $total_price - $discount;

                    $message = 'buy '.$value['qty'].' '.$message_each_text.' , get '.$id->QU_LM_MXMH.' '.$price_master->ITEM_DESC.' for '.$id->PE_UN_ITM_PRDV_SLS.' % Off';
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 1 ];
                        $applied_offer[] = $message;

                }else{
                    $total_price = $value['qty'] * $mrp;
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => '', 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];

                }

            }

        }
                
        //dd($final_data);
            // Check item Already Exits in Cart

        if ($in_cart == 1) {
            $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
        }

        // Available Offer

        $available_offer = [];


        $final_data['available_offer'] = $applied_offer;

        // dd($final_qty);

        // Applied Offer

        $final_data['applied_offer'] = $applied_offer;

        

        $final_data['item_id']  = $source_item->ITEM;
        return $final_data;


    }

    public function calculate_target_offer_of_amount($param){

        //dd($param['offer']);
        $item_id = $param['item_id'];
        $price_master = $param['price_master'];
        $rule = $param['offer'];
        $mrp = $param['mrp'];
        $qty = $param['qty'];
        $source_item = $param['source_item'];
        $carts = $param['carts'];
        $cart_item = $param['cart_item'];
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }
        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }
        // dd($qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        //$value = (array)$param['offer'];
        // Find Max Discount

        $all_sources = $rule->product_list;
        $all_sources_check = true;
        $source_cart_item_list = $carts->whereIn('item_id', $all_sources);
        $source_sum = $source_cart_item_list->sum('qty');
        $cart_item_list = $source_cart_item_list->pluck('qty','item_id')->all();
        if($rule->ITM_PRC_CTGY_SRC == 'allSources' || $rule->MAX_ALL_SOURCES == 'allSources'){

            //dd($cart_item_list);
            foreach($all_sources as $sources){
                if(isset($cart_item_list[$sources]) ){
                    
                    if( $cart_item_list[$sources] >= $rule->QU_TH ){

                    }else{
                       $all_sources_check = false; 
                    }
                }

            }

            if( $qty >= $rule->QU_LM_MXMH){

            }else{
                $all_sources_check = false; 
            }
            
            
        }

        $quantity_collection[] = $rule->QU_LM_MXMH;
        //dd($quantity_collection);
        //dd($source_sum);
        $max = max($quantity_collection);
        if($all_sources_check){
            
            while ($qty > 0) {
                
                if (in_array($qty, $quantity_collection)) {
                     //echo 'FIND Qty :- '.$qty.'<br>';  exit;

                    if($rule->ITM_PRC_CTGY_SRC == 'allSources' || $rule->MAX_ALL_SOURCES == 'allSources'){
                        $off_apply_flag = true;
                        foreach($all_sources as $sources){
                            
                            if(isset($cart_item_list[$sources])){
                                if($cart_item_list[$sources] >= $rule->QU_TH ){
                                    $cart_item_list[$sources] = $cart_item_list[$sources] - $max;
                                }else{
                                    $off_apply_flag = false;
                                }
                            }
                        }
                        if($off_apply_flag){
                            array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                        }else{
                            array_push($final_product,[ 'qty' => $max, 'is_promo' => 0 ]);
                        }

                        
                    }else{
                       if($source_sum >= $rule->QU_TH && $qty >= $rule->QU_LM_MXMH){
                            $off_apply_flag = true;
                            $source_sum = $source_sum - $rule->QU_TH;
                            
                        }else{
                            $off_apply_flag = false;
                        }
                        
                        if($off_apply_flag){
                            array_push($final_product,[ 'qty' => $qty, 'is_promo' => 1 ]);
                        }else{
                            array_push($final_product,[ 'qty' => $qty, 'is_promo' => 0 ]);
                        }
                    }
                    
                    $qty = $qty - $qty;
                } else {
                    if ($qty > $max) {
                        // echo 'LARGE Qty :- '.$max.'<br>';
                        $qty = $qty - $max;
                        if($rule->ITM_PRC_CTGY_SRC == 'allSources' || $rule->MAX_ALL_SOURCES == 'allSources'){
                            $off_apply_flag = true;
                            foreach($all_sources as $sources){
                                
                                if(isset($cart_item_list[$sources])){
                                    if($cart_item_list[$sources] >= $rule->QU_TH ){
                                        $cart_item_list[$sources] = $cart_item_list[$sources] - $max;
                                    }else{
                                        $off_apply_flag = false;
                                    }
                                }
                            }
                            
                            if($off_apply_flag){
                                array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                            }else{
                                array_push($final_product,[ 'qty' => $max, 'is_promo' => 0 ]);
                            }

                            
                        }else{

                            if($source_sum >= $rule->QU_TH && $qty >= $rule->QU_LM_MXMH){
                                $off_apply_flag = true;
                                $source_sum = $source_sum - $rule->QU_TH;
                                
                            }else{
                                $off_apply_flag = false;
                            }
                            
                            if($off_apply_flag){
                                array_push($final_product,[ 'qty' => $max, 'is_promo' => 1 ]);
                            }else{
                                array_push($final_product,[ 'qty' => $max, 'is_promo' => 0 ]);
                            }
                            
                        }
                        
                    } else {
                        array_push($final_product,[ 'qty' => $qty, 'is_promo' => 0 ]);
                        $qty = 0;
                    }
                }

            }

            //dd($final_product);

        
            foreach ($final_product as $key => $value) {
                if ($value['is_promo'] == 1) {
                    $id = $param['offer'];
                    
                    $total_price = $mrp * $value['qty'];
                    $discount = $id->MO_UN_ITM_PRDV_SLS ;
                    $ex_price = $total_price - $discount;

                    $message = 'buy '.$value['qty'].' , get '.$id->QU_LM_MXMH.' '.$price_master->ITEM_DESC.' for '.$id->MO_UN_ITM_PRDV_SLS.' Rs Off';
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 1 ];
                        $applied_offer[] = $message;

                }else{
                    $total_price = $value['qty'] * $mrp;
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => '', 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];

                }

            }

        }
                
        //dd($final_data);
            // Check item Already Exits in Cart

        if ($in_cart == 1) {
            $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
        }

        // Available Offer

        $available_offer = [];


        $final_data['available_offer'] = $applied_offer;

        // dd($final_qty);

        // Applied Offer

        $final_data['applied_offer'] = $applied_offer;

        

        $final_data['item_id']  = $source_item->ITEM;
        return $final_data;


    }

    ######################################################
    ###########  SECTION FUNCTION START ##################

    public function shop_amount_get_target_percentage_tiered($mrp, $qty, $ru_prdv, $item_id, $store_id = '20001', $user_id='test', $cart_item = false, $carts)
    {
        //dd($ru_prdv);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $item_id)->first();
        //$carts = DB::table('cart')->where('user_id', $user_id)->where('store_id', $store_id)->get();
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }

        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }
        //dd($qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                $condition = DB::table($this->store_db_name.'.tr_itm_mxmh_prdv')->select('PE_RDN_PRC_MXMH as PE_UN_ITM_PRDV_SLS', 'ID_PRM_PRD','QU_LM_MXMH')->where('ID_RU_PRDV', $value['ru_prdv'])->first();

                if($condition){
                    if($value['cd_bas_cmp_tgt'] == 7){
                        $grp_list = DB::table($this->store_db_name.'.max_grp_itm_lst')->select('ID_ITM')->where('ID_GRP', $condition->ID_PRM_PRD )->first();
                        $target_product = DB::table($this->store_db_name.'.price_master')->where('ITEM', $grp_list->ID_ITM)->first();
                       
                    }else{

                        $target_product = DB::table($this->store_db_name.'.price_master')->where('ITEM', $condition->ID_PRM_PRD)->first();
                    }

                    $total_saving = $mrp * $condition->PE_UN_ITM_PRDV_SLS / 100;
                    //$total_saving = $mrp - $total_saving;

                    $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'], 'ID' => $value['id'] ];
                }

                
            }
        }
        
        //dd($target_product);

        // Find Max Discount

        $max_discount = max(array_keys($data));

        // Check Quantity Based Condition

        if ($data[$max_discount]['TYPE'] == 'co_el') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC','co_el_prdv_itm.MO_TH','co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM', 'tr_itm_mxmh_prdv.QU_LM_MXMH', 'ru_prdv.CD_MTH_PRDV', 'tr_itm_mxmh_prdv.PE_RDN_PRC_MXMH as PE_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            ->join($this->store_db_name.'.tr_itm_mxmh_prdv', 'ru_prdv.ID_RU_PRDV', '=', 'tr_itm_mxmh_prdv.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            ->where('tr_itm_mxmh_prdv.PE_RDN_PRC_MXMH','>','0')
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }
        
        if ($data[$max_discount]['TYPE'] == 'max') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH','tr_itm_mxmh_prdv.QU_LM_MXMH', 'tr_itm_mxmh_prdv.PE_RDN_PRC_MXMH as PE_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV','max_grp_itm_lst.ID_ITM')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            ->join($this->store_db_name.'.tr_itm_mxmh_prdv', 'ru_prdv.ID_RU_PRDV', '=', 'tr_itm_mxmh_prdv.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            ->where('tr_itm_mxmh_prdv.PE_RDN_PRC_MXMH','>','0')
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }

            //dd($list_ru_prdv);
            //$all_sources = $list_ru_prdv->pluck('ID_ITM')->all();
            //$list_ru_prdv = $list_ru_prdv->where('ID_ITM', $source_item->ITEM);
            //dd($all_sources);
            //dd($all_sources);

            foreach($list_ru_prdv as $key => $value){

                $offer =(array) $value;
                $offer['promo_type'] = 'BuyRsNOrMoreOfXGetYatZ%OffTiered';
                $offer['level'] = $data[$max_discount]['LEVEL'];

                $target[$data[$max_discount]['LEVEL']][$target_product->ITEM]['BuyRsNOrMoreOfXGetYatZ%OffTiered'][] = $offer;
            }

            //dd($target);

        

            // Available Offer

            $available_offer = [];

            foreach ($list_ru_prdv as $key => $value) {
                $message = 'Shop for '.$value->MO_TH.'  , get  '.$target_product->ITEM_DESC.' for '.$value->PE_UN_ITM_PRDV_SLS.' % Off';
                $available_offer[number_format($value->MO_TH)] = $message;

            }

            $final_data['available_offer'] = $available_offer;
            // dd($final_qty);
            // Applied Offer

            $final_data['applied_offer'] = [];

        

        
        //$final_data['item_id']  = $source_item->ITEM;
        $final_data['section_target'] = $target;        
        //dd($final_data);
        return $final_data;
    }

    public function calculate_shop_target_offer_of_percentage_tiered($param){

        //dd($param['offer']);
        //$item_id = $param['item_id'];
        $item_desc = $param['item_desc'];
        $rule = $param['offer'];
        $mrp = $param['mrp'];
        $qty = $param['qty'];
        $source_item = $param['source_item'];
        $carts = $param['carts'];
        $section_total = $param['section_total'];
        $cart_item = $param['cart_item'];
        $user_id = $param['user_id'];
        $store_id = $param['store_id'];
        $item_id = $source_item;
        
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }

        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }

    
        // dd($qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        //$value = (array)$param['offer'];
        // Find Max Discount
       //dd($rule);
        //dd($section_total);
        foreach ($rule as $key => $value) {             
            
            if( $section_total[$value->level][$value->ID_ITM]['total'] >= $value->MO_TH ){
                 
                $total_saving = $mrp - $value->PE_UN_ITM_PRDV_SLS / 100;
            
                $data[$total_saving] = [ 'ID_RU_PRDV' => $value->ID_RU_PRDV, 'MO_TH' => $value->MO_TH , 'LEVEL' =>$value->level, 'PROMO_TYPE' => $value->promo_type, 'ID' => $source_item ];

            }

           
        }


        //dd($data);
        // Find Max Discount
        if(!empty($data)){

        $max_discount = max(array_keys($data));
        $data = $data[$max_discount];

        $final_product[] = [ 'is_promo' => 1 , 'qty' => $qty , 'mo_th' => $data['MO_TH']];

                //dd($data);
        //exit;
        //dd($quantity_collection);
        

            //dd($final_product);

        
            foreach ($final_product as $key => $value) {
                if ($value['is_promo'] == 1) {
                    //$id = $param['offer'];

                    $id = $this->searchAmount($value['mo_th'] , $rule);
                    //dd($id);
                    
                    $total_price = $mrp * $value['qty'];
                    $discount = $mrp * $id->PE_UN_ITM_PRDV_SLS / 100;
                    $ex_price = $total_price - $discount;

                    $message = 'shop for '.$id->MO_TH.'  get  '.$item_desc.' for '.$id->PE_UN_ITM_PRDV_SLS.' % Off';
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 1 ];
                        $applied_offer[] = $message;

                }else{
                    $total_price = $value['qty'] * $mrp;
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => '', 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];

                }

            }

        }else{

            $total_price = $qty * $mrp;
            $final_data['pdata'][] = [ 'qty' => $qty, 'mrp' => $mrp, 'discount' => '', 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];


        }
                
        //dd($final_data);
            // Check item Already Exits in Cart

        if ($in_cart == 1) {
            $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
        }

        // Available Offer

        $available_offer = [];


        $final_data['available_offer'] = $applied_offer;

        // dd($final_qty);

        // Applied Offer

        $final_data['applied_offer'] = $applied_offer;

        
        //dd($final_data);
        $final_data['item_id']  = $source_item;
        return $final_data;


    }

    public function shop_amount_get_target_fixed_price($mrp, $qty, $ru_prdv, $item_id, $store_id = '20001', $user_id='test', $cart_item = false, $carts)
    {
        //dd($ru_prdv);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $item_id)->first();
        //$carts = DB::table('cart')->where('user_id', $user_id)->where('store_id', $store_id)->get();
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }

        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }
        // dd($qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                $condition = DB::table($this->store_db_name.'.tr_itm_mxmh_prdv')->select('PNT_PRC_RDN_MXMH as PNT_PRC_UN_ITM_PRDV_SLS', 'ID_PRM_PRD','QU_LM_MXMH')->where('ID_RU_PRDV', $value['ru_prdv'])->first();

                if($condition){
                    if($value['cd_bas_cmp_tgt'] == 7){
                        $grp_list = DB::table($this->store_db_name.'.max_grp_itm_lst')->select('ID_ITM')->where('ID_GRP', $condition->ID_PRM_PRD )->first();
                        $target_product = DB::table($this->store_db_name.'.price_master')->where('ITEM', $grp_list->ID_ITM)->first();
                       
                    }else{

                        $target_product = DB::table($this->store_db_name.'.price_master')->where('ITEM', $condition->ID_PRM_PRD)->first();
                    }

                    $total_saving = $mrp - $condition->PNT_PRC_UN_ITM_PRDV_SLS ;
                    //$total_saving = $mrp - $total_saving;

                    $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'], 'ID' => $value['id'] ];
                }

                
            }
        }
        
        //dd($data);
        //dd($target_product);

        // Find Max Discount

        $max_discount = max(array_keys($data));

        // Check Quantity Based Condition

        if ($data[$max_discount]['TYPE'] == 'co_el') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC','co_el_prdv_itm.MO_TH','co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM', 'tr_itm_mxmh_prdv.QU_LM_MXMH', 'ru_prdv.CD_MTH_PRDV', 'tr_itm_mxmh_prdv.PNT_PRC_RDN_MXMH as PNT_PRC_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            ->join($this->store_db_name.'.tr_itm_mxmh_prdv', 'ru_prdv.ID_RU_PRDV', '=', 'tr_itm_mxmh_prdv.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }
        
        if ($data[$max_discount]['TYPE'] == 'max') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH','tr_itm_mxmh_prdv.QU_LM_MXMH', 'tr_itm_mxmh_prdv.PNT_PRC_RDN_MXMH as PNT_PRC_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV','max_grp_itm_lst.ID_ITM')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            ->join($this->store_db_name.'.tr_itm_mxmh_prdv', 'ru_prdv.ID_RU_PRDV', '=', 'tr_itm_mxmh_prdv.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }

            //dd($list_ru_prdv);
            //$all_sources = $list_ru_prdv->pluck('ID_ITM')->all();
            //$list_ru_prdv = $list_ru_prdv->where('ID_ITM', $source_item->ITEM);
            //dd($all_sources);
            //dd($all_sources);

            foreach($list_ru_prdv as $key => $value){

                $offer =(array) $value;
                $offer['promo_type'] = 'Buy$NorMoreOfXgetYatZ$';
                $offer['level'] = $data[$max_discount]['LEVEL'];

                $target[$data[$max_discount]['LEVEL']][$target_product->ITEM]['Buy$NorMoreOfXgetYatZ$'][] = $offer;
            }

            //dd($target);

        

            // Available Offer

            $available_offer = [];

            foreach ($list_ru_prdv as $key => $value) {
                $message = 'Shop for '.$value->MO_TH.'  , get  '.$target_product->ITEM_DESC.' at '.$value->PNT_PRC_UN_ITM_PRDV_SLS.'';
                $available_offer[number_format($value->MO_TH)] = $message;

            }

            $final_data['available_offer'] = $available_offer;
            // dd($final_qty);
            // Applied Offer

            $final_data['applied_offer'] = [];

        

        
        //$final_data['item_id']  = $source_item->ITEM;
        $final_data['section_target'] = $target;        
        //dd($final_data);
        return $final_data;
    }

    public function calculate_shop_target_offer_of_fixed_price($param){

        //dd($param['offer']);
        $item_desc = $param['item_desc'];
        $rule = $param['offer'];
        $mrp = $param['mrp'];
        $qty = $param['qty'];
        $source_item = $param['source_item'];
        $carts = $param['carts'];
        $section_total = $param['section_total'];
        $user_id = $param['user_id'];
        $store_id = $param['store_id'];
        $item_id = $source_item;

        $cart_item = $param['cart_item'];
        
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }

        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }

        // dd($qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        //$value = (array)$param['offer'];
        // Find Max Discount
       //dd($rule);
        foreach ($rule as $key => $value) {             
            
            if( $section_total[$value->level][$value->ID_ITM]['total'] >= $value->MO_TH ){
                 
                $total_saving = $mrp - $value->PNT_PRC_UN_ITM_PRDV_SLS;
            
                $data[$total_saving] = [ 'ID_RU_PRDV' => $value->ID_RU_PRDV, 'MO_TH' => $value->MO_TH , 'LEVEL' =>$value->level, 'PROMO_TYPE' => $value->promo_type, 'ID' => $source_item ];

            }

           
        }


        //dd($data);
        // Find Max Discount
        if(!empty($data)){
        $max_discount = max(array_keys($data));
        $data = $data[$max_discount];

        $final_product[] = [ 'is_promo' => 1 , 'qty' => $qty , 'mo_th' => $data['MO_TH']];

                //dd($data);
        //exit;
        //dd($quantity_collection);
        

            //dd($final_product);
            //dd($rule);
            foreach ($final_product as $key => $value) {
                if ($value['is_promo'] == 1) {
                    //$id = $param['offer'];

                    $id = $this->searchAmount($value['mo_th'] , $rule);
                    //dd($id);
                    
                    $total_price = $mrp * $value['qty'];
                    $discount = $mrp - $id->PNT_PRC_UN_ITM_PRDV_SLS;
                    $ex_price = $total_price - $discount;

                    $message = 'shop for '.$id->MO_TH.'  get  '.$item_desc.' at Rs. '.$id->PNT_PRC_UN_ITM_PRDV_SLS;
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 1 ];
                        $applied_offer[] = $message;

                }else{
                    $total_price = $value['qty'] * $mrp;
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => '', 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];

                }

            }

        }else{

            $total_price = $qty * $mrp;
            $final_data['pdata'][] = [ 'qty' => $qty, 'mrp' => $mrp, 'discount' => '', 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];


        }
                
        //dd($final_data);
            // Check item Already Exits in Cart

        if ($in_cart == 1) {
            $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
        }

        // Available Offer

        $available_offer = [];


        $final_data['available_offer'] = $applied_offer;

        // dd($final_qty);

        // Applied Offer

        $final_data['applied_offer'] = $applied_offer;

        

        $final_data['item_id']  = $source_item;
        return $final_data;


    }


    public function shop_amount_get_target_percentage($mrp, $qty, $ru_prdv, $item_id, $store_id = '20001', $user_id='test', $cart_item = false, $carts)
    {
        //dd($ru_prdv);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $item_id)->first();
        //$carts = DB::table('cart')->where('user_id', $user_id)->where('store_id', $store_id)->get();
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }

        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }
        // dd($qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                $condition = DB::table($this->store_db_name.'.tr_itm_mxmh_prdv')->select('PE_RDN_PRC_MXMH as PE_UN_ITM_PRDV_SLS', 'ID_PRM_PRD','QU_LM_MXMH')->where('ID_RU_PRDV', $value['ru_prdv'])->first();

                if($condition){
                    if($value['cd_bas_cmp_tgt'] == 7){
                        $grp_list = DB::table($this->store_db_name.'.max_grp_itm_lst')->select('ID_ITM')->where('ID_GRP', $condition->ID_PRM_PRD )->first();
                        $target_product = DB::table($this->store_db_name.'.price_master')->where('ITEM', $grp_list->ID_ITM)->first();
                       
                    }else{

                        $target_product = DB::table($this->store_db_name.'.price_master')->where('ITEM', $condition->ID_PRM_PRD)->first();
                    }

                    $total_saving = $mrp * $condition->PE_UN_ITM_PRDV_SLS / 100;
                    //$total_saving = $mrp - $total_saving;

                    $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'], 'ID' => $value['id'] ];
                }

                
            }
        }
        
        //dd($target_product);

        // Find Max Discount

        $max_discount = max(array_keys($data));

        // Check Quantity Based Condition

        if ($data[$max_discount]['TYPE'] == 'co_el') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC','co_el_prdv_itm.MO_TH','co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM', 'tr_itm_mxmh_prdv.QU_LM_MXMH', 'ru_prdv.CD_MTH_PRDV', 'tr_itm_mxmh_prdv.PE_RDN_PRC_MXMH as PE_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            ->join($this->store_db_name.'.tr_itm_mxmh_prdv', 'ru_prdv.ID_RU_PRDV', '=', 'tr_itm_mxmh_prdv.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            ->where('co_el_prdv_itm.MO_TH','>','0')
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }
        
        if ($data[$max_discount]['TYPE'] == 'max') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH','tr_itm_mxmh_prdv.QU_LM_MXMH', 'tr_itm_mxmh_prdv.PE_RDN_PRC_MXMH as PE_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV','max_grp_itm_lst.ID_ITM')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            ->join($this->store_db_name.'.tr_itm_mxmh_prdv', 'ru_prdv.ID_RU_PRDV', '=', 'tr_itm_mxmh_prdv.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            ->where('max_co_el_prdv_itm_grp.MO_TH','>','0')
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }

            //dd($list_ru_prdv);
            //$all_sources = $list_ru_prdv->pluck('ID_ITM')->all();
            //$list_ru_prdv = $list_ru_prdv->where('ID_ITM', $source_item->ITEM);
            //dd($all_sources);
            //dd($all_sources);

            foreach($list_ru_prdv as $key => $value){

                $offer =(array) $value;
                $offer['promo_type'] = 'Buy$NorMoreOfXgetYatZ%off';
                $offer['level'] = $data[$max_discount]['LEVEL'];

                $target[$data[$max_discount]['LEVEL']][$target_product->ITEM]['Buy$NorMoreOfXgetYatZ%off'][] = $offer;
            }

            //dd($target);

        

            // Available Offer

            $available_offer = [];

            foreach ($list_ru_prdv as $key => $value) {
                $message = 'Shop for '.$value->MO_TH.'  , get  '.$target_product->ITEM_DESC.' for '.$value->PE_UN_ITM_PRDV_SLS.' % Off';
                $available_offer[number_format($value->MO_TH)] = $message;

            }

            $final_data['available_offer'] = $available_offer;
            // dd($final_qty);
            // Applied Offer

            $final_data['applied_offer'] = [];

        

        
        //$final_data['item_id']  = $source_item->ITEM;
        $final_data['section_target'] = $target;        
        //dd($final_data);
        return $final_data;
    }

    public function calculate_shop_target_offer_of_percentage($param){

        //dd($param['offer']);
        $item_desc = $param['item_desc'];
        $rule = $param['offer'];
        $mrp = $param['mrp'];
        $qty = $param['qty'];
        $source_item = $param['source_item'];
        $carts = $param['carts'];
        $section_total = $param['section_total'];
        $user_id = $param['user_id'];
        $store_id = $param['store_id'];
        $item_id = $source_item;
        $cart_item = $param['cart_item'];
        
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }

        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }
        // dd($qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        //$value = (array)$param['offer'];
        // Find Max Discount
       //dd($rule);
        foreach ($rule as $key => $value) {             
            
            if( $section_total[$value->level][$value->ID_ITM]['total'] >= $value->MO_TH ){
                 
                $total_saving = $mrp - $value->PE_UN_ITM_PRDV_SLS / 100;
            
                $data[$total_saving] = [ 'ID_RU_PRDV' => $value->ID_RU_PRDV, 'MO_TH' => $value->MO_TH , 'LEVEL' =>$value->level, 'PROMO_TYPE' => $value->promo_type, 'ID' => $source_item];

            }

           
        }


        //dd($data);
        // Find Max Discount
        if(!empty($data)){
        $max_discount = max(array_keys($data));
        $data = $data[$max_discount];

        $final_product[] = [ 'is_promo' => 1 , 'qty' => $qty , 'mo_th' => $data['MO_TH']];

                //dd($data);
        //exit;
        //dd($quantity_collection);
        

            //dd($final_product);

        
            foreach ($final_product as $key => $value) {
                if ($value['is_promo'] == 1) {
                    //$id = $param['offer'];

                    $id = $this->searchAmount($value['mo_th'] , $rule);
                    //dd($id);
                    
                    $total_price = $mrp * $value['qty'];
                    $discount = $mrp * $id->PE_UN_ITM_PRDV_SLS / 100;
                    $ex_price = $total_price - $discount;

                    $message = 'shop for '.$id->MO_TH.'  get  '.$item_desc.' for '.$id->PE_UN_ITM_PRDV_SLS.' % Off';
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 1 ];
                        $applied_offer[] = $message;

                }else{
                    $total_price = $value['qty'] * $mrp;
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => '', 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];

                }

            }

        }else{

            $total_price = $qty * $mrp;
            $final_data['pdata'][] = [ 'qty' => $qty, 'mrp' => $mrp, 'discount' => '', 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];


        }
                
        //dd($final_data);
            // Check item Already Exits in Cart

        if ($in_cart == 1) {
            $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
        }

        // Available Offer

        $available_offer = [];


        $final_data['available_offer'] = $applied_offer;

        // dd($final_qty);

        // Applied Offer

        $final_data['applied_offer'] = $applied_offer;

        

        $final_data['item_id']  = $source_item;
        return $final_data;


    }

    public function shop_amount_get_target_amount_tiered($mrp, $qty, $ru_prdv, $item_id, $store_id = '20001', $user_id='test', $cart_item = false, $carts)
    {
        //dd($ru_prdv);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $item_id)->first();
        //$carts = DB::table('cart')->where('user_id', $user_id)->where('store_id', $store_id)->get();
        if($cart_item){
            $check_product_in_cart =[];
        }else{
          $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }

        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }
        // dd($qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                $condition = DB::table($this->store_db_name.'.tr_itm_mxmh_prdv')->select('MO_RDN_PRC_MXMH as MO_UN_ITM_PRDV_SLS', 'ID_PRM_PRD','QU_LM_MXMH')->where('ID_RU_PRDV', $value['ru_prdv'])->first();

                if($condition){
                    if($value['cd_bas_cmp_tgt'] == 7){
                        $grp_list = DB::table($this->store_db_name.'.max_grp_itm_lst')->select('ID_ITM')->where('ID_GRP', $condition->ID_PRM_PRD )->first();
                        $target_product = DB::table($this->store_db_name.'.price_master')->where('ITEM', $grp_list->ID_ITM)->first();
                       
                    }else{

                        $target_product = DB::table($this->store_db_name.'.price_master')->where('ITEM', $condition->ID_PRM_PRD)->first();
                    }

                    $total_saving = $mrp - $condition->MO_UN_ITM_PRDV_SLS ;
                    //$total_saving = $mrp - $total_saving;

                    $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'], 'ID' => $value['id'] ];
                }

                
            }
        }
        
        //dd($data);
        //dd($target_product);

        // Find Max Discount

        $max_discount = max(array_keys($data));

        // Check Quantity Based Condition

        if ($data[$max_discount]['TYPE'] == 'co_el') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC','co_el_prdv_itm.MO_TH','co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM', 'tr_itm_mxmh_prdv.QU_LM_MXMH', 'ru_prdv.CD_MTH_PRDV', 'tr_itm_mxmh_prdv.MO_RDN_PRC_MXMH as MO_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            ->join($this->store_db_name.'.tr_itm_mxmh_prdv', 'ru_prdv.ID_RU_PRDV', '=', 'tr_itm_mxmh_prdv.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            ->where('co_el_prdv_itm.MO_TH','>','0')
                            
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }
        
        if ($data[$max_discount]['TYPE'] == 'max') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH','tr_itm_mxmh_prdv.QU_LM_MXMH', 'tr_itm_mxmh_prdv.MO_RDN_PRC_MXMH as MO_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV','max_grp_itm_lst.ID_ITM')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            ->join($this->store_db_name.'.tr_itm_mxmh_prdv', 'ru_prdv.ID_RU_PRDV', '=', 'tr_itm_mxmh_prdv.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            ->where('max_co_el_prdv_itm_grp.MO_TH','>','0')
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }

            //dd($list_ru_prdv);
            //$all_sources = $list_ru_prdv->pluck('ID_ITM')->all();
            //$list_ru_prdv = $list_ru_prdv->where('ID_ITM', $source_item->ITEM);
            //dd($all_sources);
            //dd($all_sources);

            foreach($list_ru_prdv as $key => $value){

                $offer =(array) $value;
                $offer['promo_type'] = 'BuyRsNOrMoreOfXGetYatZRsOffTiered';
                $offer['level'] = $data[$max_discount]['LEVEL'];

                $target[$data[$max_discount]['LEVEL']][$target_product->ITEM]['BuyRsNOrMoreOfXGetYatZRsOffTiered'][] = $offer;
            }

            //dd($target);

        

            // Available Offer

            $available_offer = [];

            foreach ($list_ru_prdv as $key => $value) {
                $message = 'Shop for '.$value->MO_TH.'  , get  '.$target_product->ITEM_DESC.' at '.$value->MO_UN_ITM_PRDV_SLS.' Rs. Off';
                $available_offer[number_format($value->MO_TH)] = $message;

            }

            $final_data['available_offer'] = $available_offer;
            // dd($final_qty);
            // Applied Offer

            $final_data['applied_offer'] = [];

        

        
        //$final_data['item_id']  = $source_item->ITEM;
        $final_data['section_target'] = $target;        
        //dd($final_data);
        return $final_data;
    }

    public function calculate_shop_target_offer_of_amount_tiered($param){

        //dd($param['offer']);
        $item_desc = $param['item_desc'];
        $rule = $param['offer'];
        $mrp = $param['mrp'];
        $qty = $param['qty'];
        $source_item = $param['source_item'];
        $carts = $param['carts'];
        $section_total = $param['section_total'];
        $user_id = $param['user_id'];
        $store_id = $param['store_id'];
        $item_id = $source_item;
        $cart_item = $param['cart_item'];
        
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }

        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }
        // dd($qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        //$value = (array)$param['offer'];
        // Find Max Discount
       //dd($rule);
        foreach ($rule as $key => $value) {             
            
            if( $section_total[$value->level][$value->ID_ITM]['total'] >= $value->MO_TH ){
                 
                $total_saving = $value->MO_UN_ITM_PRDV_SLS ;
            
                $data[$total_saving] = [ 'ID_RU_PRDV' => $value->ID_RU_PRDV, 'MO_TH' => $value->MO_TH , 'LEVEL' =>$value->level, 'PROMO_TYPE' => $value->promo_type, 'ID' => $source_item ];
            }
           
        }


        //dd($data);
        // Find Max Discount
        if(!empty($data)){

        $max_discount = max(array_keys($data));
        $data = $data[$max_discount];
        //dd($data);
        $final_product[] = [ 'is_promo' => 1 , 'qty' => $qty , 'mo_th' => $data['MO_TH']];

                //dd($data);
        //exit;
        //dd($quantity_collection);
        

            //dd($final_product);

        
            foreach ($final_product as $key => $value) {
                if ($value['is_promo'] == 1) {
                    //$id = $param['offer'];

                    $id = $this->searchAmount($value['mo_th'] , $rule);
                    //dd($id);
                    
                    $total_price = $mrp * $value['qty'];
                    $discount =  $id->MO_UN_ITM_PRDV_SLS ;
                    $ex_price = $total_price - $discount;

                    $message = 'shop for '.$id->MO_TH.'  get  '.$item_desc.' for '.$id->MO_UN_ITM_PRDV_SLS.' Rs. Off';
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 1 ];
                        $applied_offer[] = $message;

                }else{
                    $total_price = $value['qty'] * $mrp;
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => '', 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];

                }

            }

        }else{

            $total_price = $qty * $mrp;
            $final_data['pdata'][] = [ 'qty' => $qty, 'mrp' => $mrp, 'discount' => '', 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];


        }
                
        //dd($final_data);
            // Check item Already Exits in Cart

        if ($in_cart == 1) {
            $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
        }

        // Available Offer

        $available_offer = [];


        $final_data['available_offer'] = $applied_offer;

        // dd($final_qty);

        // Applied Offer

        $final_data['applied_offer'] = $applied_offer;

        

        $final_data['item_id']  = $source_item;
        //dd($final_data);
        return $final_data;


    }

    public function shop_amount_get_target_fixed_price_tiered($mrp, $qty, $ru_prdv, $item_id, $store_id = '20001', $user_id='test', $cart_item = false, $carts)
    {
        //dd($ru_prdv);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $item_id)->first();
        //$carts = DB::table('cart')->where('user_id', $user_id)->where('store_id', $store_id)->get();
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }

        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }
        // dd($qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                $condition = DB::table($this->store_db_name.'.tr_itm_mxmh_prdv')->select('PNT_PRC_RDN_MXMH as PNT_PRC_UN_ITM_PRDV_SLS', 'ID_PRM_PRD','QU_LM_MXMH')->where('ID_RU_PRDV', $value['ru_prdv'])->first();

                if($condition){
                    if($value['cd_bas_cmp_tgt'] == 7){
                        $grp_list = DB::table($this->store_db_name.'.max_grp_itm_lst')->select('ID_ITM')->where('ID_GRP', $condition->ID_PRM_PRD )->first();
                        $target_product = DB::table($this->store_db_name.'.price_master')->where('ITEM', $grp_list->ID_ITM)->first();
                       
                    }else{

                        $target_product = DB::table($this->store_db_name.'.price_master')->where('ITEM', $condition->ID_PRM_PRD)->first();
                    }

                    $total_saving = $mrp - $condition->PNT_PRC_UN_ITM_PRDV_SLS ;
                    //$total_saving = $mrp - $total_saving;

                    $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'], 'ID' => $value['id'] ];
                }

                
            }
        }
        
        //dd($data);
        //dd($target_product);

        // Find Max Discount

        $max_discount = max(array_keys($data));

        // Check Quantity Based Condition

        if ($data[$max_discount]['TYPE'] == 'co_el') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC','co_el_prdv_itm.MO_TH','co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM', 'tr_itm_mxmh_prdv.QU_LM_MXMH', 'ru_prdv.CD_MTH_PRDV', 'tr_itm_mxmh_prdv.PNT_PRC_RDN_MXMH as PNT_PRC_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            ->join($this->store_db_name.'.tr_itm_mxmh_prdv', 'ru_prdv.ID_RU_PRDV', '=', 'tr_itm_mxmh_prdv.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            ->where('co_el_prdv_itm.MO_TH','>','0')
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }
        
        if ($data[$max_discount]['TYPE'] == 'max') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH','tr_itm_mxmh_prdv.QU_LM_MXMH', 'tr_itm_mxmh_prdv.PNT_PRC_RDN_MXMH as PNT_PRC_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV','max_grp_itm_lst.ID_ITM')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            ->join($this->store_db_name.'.tr_itm_mxmh_prdv', 'ru_prdv.ID_RU_PRDV', '=', 'tr_itm_mxmh_prdv.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            ->where('max_co_el_prdv_itm_grp.MO_TH','>','0')
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }

            //dd($list_ru_prdv);
            //$all_sources = $list_ru_prdv->pluck('ID_ITM')->all();
            //$list_ru_prdv = $list_ru_prdv->where('ID_ITM', $source_item->ITEM);
            //dd($all_sources);
            //dd($all_sources);

            foreach($list_ru_prdv as $key => $value){

                $offer =(array) $value;
                $offer['promo_type'] = 'BuyRsNOrMoreOfXGetYatZRsTiered';
                $offer['level'] = $data[$max_discount]['LEVEL'];

                $target[$data[$max_discount]['LEVEL']][$target_product->ITEM]['BuyRsNOrMoreOfXGetYatZRsTiered'][] = $offer;
            }

            //dd($target);

        

            // Available Offer

            $available_offer = [];

            foreach ($list_ru_prdv as $key => $value) {
                $message = 'Shop for '.$value->MO_TH.'  , get  '.$target_product->ITEM_DESC.' at '.$value->PNT_PRC_UN_ITM_PRDV_SLS.'';
                $available_offer[number_format($value->MO_TH)] = $message;

            }

            $final_data['available_offer'] = $available_offer;
            // dd($final_qty);
            // Applied Offer

            $final_data['applied_offer'] = [];

        

        
        //$final_data['item_id']  = $source_item->ITEM;
        $final_data['section_target'] = $target;        
        //dd($final_data);
        return $final_data;
    }

    public function calculate_shop_target_offer_of_fixed_price_tiered($param){

        //dd($param['offer']);
        $item_desc = $param['item_desc'];
        $rule = $param['offer'];
        $mrp = $param['mrp'];
        $qty = $param['qty'];
        $source_item = $param['source_item'];
        $carts = $param['carts'];
        $section_total = $param['section_total'];
        $user_id = $param['user_id'];
        $store_id = $param['store_id'];
        $item_id = $source_item;
        $cart_item = $param['cart_item'];
        
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }

        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }
        // dd($qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        //$value = (array)$param['offer'];
        // Find Max Discount
       //dd($rule);
        foreach ($rule as $key => $value) {             
            
            if( $section_total[$value->level][$value->ID_ITM]['total'] >= $value->MO_TH ){
                 
                $total_saving = $mrp - $value->PNT_PRC_UN_ITM_PRDV_SLS;
            
                $data[$total_saving] = [ 'ID_RU_PRDV' => $value->ID_RU_PRDV, 'MO_TH' => $value->MO_TH , 'LEVEL' =>$value->level, 'PROMO_TYPE' => $value->promo_type, 'ID' => $source_item];

            }

           
        }


        //dd($data);
        // Find Max Discount
        if(!empty($data)){

        $max_discount = max(array_keys($data));
        $data = $data[$max_discount];
        //dd($data);

        $final_product[] = [ 'is_promo' => 1 , 'qty' => $qty , 'mo_th' => $data['MO_TH']];

                //dd($data);
        //exit;
        //dd($quantity_collection);
        

            //dd($final_product);
            //dd($rule);
            foreach ($final_product as $key => $value) {
                if ($value['is_promo'] == 1) {
                    //$id = $param['offer'];

                    $id = $this->searchAmount($value['mo_th'] , $rule);
                    //dd($id);
                    
                    $total_price = $mrp * $value['qty'];
                    $discount = $mrp - $id->PNT_PRC_UN_ITM_PRDV_SLS;
                    $ex_price = $total_price - $discount;

                    $message = 'shop for '.$id->MO_TH.'  get  '.$item_desc.' at Rs. '.$id->PNT_PRC_UN_ITM_PRDV_SLS;
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 1 ];
                        $applied_offer[] = $message;

                }else{
                    $total_price = $value['qty'] * $mrp;
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => '', 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];

                }

            }

        }else{

            $total_price = $qty * $mrp;
            $final_data['pdata'][] = [ 'qty' => $qty, 'mrp' => $mrp, 'discount' => '', 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];


        }
                
        //dd($final_data);
            // Check item Already Exits in Cart

        if ($in_cart == 1) {
            $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
        }

        // Available Offer

        $available_offer = [];


        $final_data['available_offer'] = $applied_offer;

        // dd($final_qty);

        // Applied Offer

        $final_data['applied_offer'] = $applied_offer;

        

        $final_data['item_id']  = $source_item;
        return $final_data;


    }

     public function shop_amount_get_target_amount($mrp, $qty, $ru_prdv, $item_id, $store_id = '20001', $user_id='test', $cart_item = false, $carts)
    {
        //dd($ru_prdv);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $item_id)->first();
        //$carts = DB::table('cart')->where('user_id', $user_id)->where('store_id', $store_id)->get();
       
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }

        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }
        // dd($qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                $condition = DB::table($this->store_db_name.'.tr_itm_mxmh_prdv')->select('MO_RDN_PRC_MXMH as MO_UN_ITM_PRDV_SLS', 'ID_PRM_PRD','QU_LM_MXMH')->where('ID_RU_PRDV', $value['ru_prdv'])->first();

                if($condition){
                    if($value['cd_bas_cmp_tgt'] == 7){
                        $grp_list = DB::table($this->store_db_name.'.max_grp_itm_lst')->select('ID_ITM')->where('ID_GRP', $condition->ID_PRM_PRD )->first();
                        $target_product = DB::table($this->store_db_name.'.price_master')->where('ITEM', $grp_list->ID_ITM)->first();
                       
                    }else{

                        $target_product = DB::table($this->store_db_name.'.price_master')->where('ITEM', $condition->ID_PRM_PRD)->first();
                    }

                    $total_saving = $mrp - $condition->MO_UN_ITM_PRDV_SLS ;
                    //$total_saving = $mrp - $total_saving;

                    $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'], 'ID' => $value['id'] ];
                }

                
            }
        }
        
        //dd($data);
        //dd($target_product);

        // Find Max Discount

        $max_discount = max(array_keys($data));

        // Check Quantity Based Condition

        if ($data[$max_discount]['TYPE'] == 'co_el') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC','co_el_prdv_itm.MO_TH','co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM', 'tr_itm_mxmh_prdv.QU_LM_MXMH', 'ru_prdv.CD_MTH_PRDV', 'tr_itm_mxmh_prdv.MO_RDN_PRC_MXMH as MO_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            ->join($this->store_db_name.'.tr_itm_mxmh_prdv', 'ru_prdv.ID_RU_PRDV', '=', 'tr_itm_mxmh_prdv.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            ->where('tr_itm_mxmh_prdv.MO_RDN_PRC_MXMH','>','0')
                            
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }
        
        if ($data[$max_discount]['TYPE'] == 'max') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH','tr_itm_mxmh_prdv.QU_LM_MXMH', 'tr_itm_mxmh_prdv.MO_RDN_PRC_MXMH as MO_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV','max_grp_itm_lst.ID_ITM')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            ->join($this->store_db_name.'.tr_itm_mxmh_prdv', 'ru_prdv.ID_RU_PRDV', '=', 'tr_itm_mxmh_prdv.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            ->where('tr_itm_mxmh_prdv.MO_RDN_PRC_MXMH','>','0')
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }

            //dd($list_ru_prdv);
            //$all_sources = $list_ru_prdv->pluck('ID_ITM')->all();
            //$list_ru_prdv = $list_ru_prdv->where('ID_ITM', $source_item->ITEM);
            //dd($all_sources);
            //dd($all_sources);

            foreach($list_ru_prdv as $key => $value){

                $offer =(array) $value;
                $offer['promo_type'] = 'Buy$NorMoreOfXgetYatZ$off';
                $offer['level'] = $data[$max_discount]['LEVEL'];

                $target[$data[$max_discount]['LEVEL']][$target_product->ITEM]['Buy$NorMoreOfXgetYatZ$off'][] = $offer;
            }

            //dd($target);

        

            // Available Offer

            $available_offer = [];

            foreach ($list_ru_prdv as $key => $value) {
                $message = 'Shop for '.$value->MO_TH.'  , get  '.$target_product->ITEM_DESC.' at '.$value->MO_UN_ITM_PRDV_SLS.' Rs. Off';
                $available_offer[number_format($value->MO_TH)] = $message;

            }

            $final_data['available_offer'] = $available_offer;
            // dd($final_qty);
            // Applied Offer

            $final_data['applied_offer'] = [];

        

        
        //$final_data['item_id']  = $source_item->ITEM;
        $final_data['section_target'] = $target;        
        //dd($final_data);
        return $final_data;
    }

    public function calculate_shop_target_offer_of_amount($param){

        //dd($param['offer']);
        $item_desc = $param['item_desc'];
        $rule = $param['offer'];
        $mrp = $param['mrp'];
        $qty = $param['qty'];
        $source_item = $param['source_item'];
        $carts = $param['carts'];
        $section_total = $param['section_total'];
        $user_id = $param['user_id'];
        $store_id = $param['store_id'];
        $item_id = $source_item;

        $cart_item = $param['cart_item'];
        
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }

        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }
        // dd($qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        //$value = (array)$param['offer'];
        // Find Max Discount
       //dd($rule);
        foreach ($rule as $key => $value) {             
            
            if( $section_total[$value->level][$value->ID_ITM]['total'] >= $value->MO_TH ){
                 
                $total_saving = $value->MO_UN_ITM_PRDV_SLS ;
            
                $data[$total_saving] = [ 'ID_RU_PRDV' => $value->ID_RU_PRDV, 'MO_TH' => $value->MO_TH , 'LEVEL' =>$value->level, 'PROMO_TYPE' => $value->promo_type, 'ID' => $source_item ];
            }
           
        }


        //dd($data);
        // Find Max Discount
        if(!empty($data)){

        $max_discount = max(array_keys($data));
        $data = $data[$max_discount];
        //dd($data);
        $final_product[] = [ 'is_promo' => 1 , 'qty' => $qty , 'mo_th' => $data['MO_TH']];

                //dd($data);
        //exit;
        //dd($quantity_collection);
        

            //dd($final_product);

        
            foreach ($final_product as $key => $value) {
                if ($value['is_promo'] == 1) {
                    //$id = $param['offer'];

                    $id = $this->searchAmount($value['mo_th'] , $rule);
                    //dd($id);
                    
                    $total_price = $mrp * $value['qty'];
                    $discount =  $id->MO_UN_ITM_PRDV_SLS ;
                    $ex_price = $total_price - $discount;

                    $message = 'shop for '.$id->MO_TH.'  get  '.$item_desc.' for '.$id->MO_UN_ITM_PRDV_SLS.' % Off';
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 1 ];
                        $applied_offer[] = $message;

                }else{
                    $total_price = $value['qty'] * $mrp;
                    $final_data['pdata'][] = [ 'qty' => $value['qty'], 'mrp' => $mrp, 'discount' => '', 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];

                }

            }

        }else{

            $total_price = $qty * $mrp;
            $final_data['pdata'][] = [ 'qty' => $qty, 'mrp' => $mrp, 'discount' => '', 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0 ];


        }
        
                
        //dd($final_data);
            // Check item Already Exits in Cart

        if ($in_cart == 1) {
            $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
        }

        // Available Offer

        $available_offer = [];


        $final_data['available_offer'] = $applied_offer;

        // dd($final_qty);

        // Applied Offer

        $final_data['applied_offer'] = $applied_offer;

        

        $final_data['item_id']  = $source_item;
        return $final_data;


    }

    public function shop_amount_get_percentage_tiered($mrp, $qty, $ru_prdv, $item_id, $store_id = '20001', $user_id='test', $cart_item = false, $carts)
    {
        //dd($ru_prdv);
        $source_item = DB::table($this->store_db_name.'.item_master')->select('ITEM')->where('EAN', $item_id)->first();
        //$carts = DB::table('cart')->where('user_id', $user_id)->where('store_id', $store_id)->get();
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }
        
    
        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }

        $total_qty = $qty;

        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        $level = '';
        
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                $condition = DB::table($this->store_db_name.'.co_prdv_itm')->select('PE_UN_ITM_PRDV_SLS')->where('ID_RU_PRDV', $value['ru_prdv'])->first();
                if($condition){
                    $level = $value['level'];
                    // echo '<pre>';
                    // print_r($value);
                    // print_r($condition);
                    $total_saving = $mrp * $condition->PE_UN_ITM_PRDV_SLS / 100;
                    //$total_saving = $mrp - $total_saving;
                    
                    $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'], 'ID' => $value['id'] ];
                }
            }
        }
        //dd($data);
        // Find Max Discount

        $max_discount = max(array_keys($data));

        // Check Quantity Based Condition
        if ($data[$max_discount]['TYPE'] == 'max') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH', 'co_prdv_itm.PE_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV','max_grp_itm_lst.ID_ITM')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }

        if ($data[$max_discount]['TYPE'] == 'co_el') {
            $list_ru_prdv = DB::table($this->store_db_name.'.ru_prdv')
                            ->select('ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'co_el_prdv_itm.MO_TH', 'co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM', 'ru_prdv.CD_MTH_PRDV', 'co_prdv_itm.PE_UN_ITM_PRDV_SLS')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            ->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $data[$max_discount]['ID_PRM'])
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
        }                
            //dd($list_ru_prdv);
    
           foreach($list_ru_prdv as $key => $value){

                $offer =(array) $value;
                $offer['promo_type'] = 'Buy$NofXatZ%offTiered';
                $offer['level'] = $data[$max_discount]['LEVEL'];

                $target[$data[$max_discount]['LEVEL']][$value->ID_ITM]['Buy$NofXatZ%offTiered'][] = $offer;
            }

            //dd($target);

        

            // Available Offer

            $available_offer = [];

            foreach ($list_ru_prdv as $key => $value) {
                $message = 'shop for '.$value->MO_TH.' above get  '.$value->PE_UN_ITM_PRDV_SLS.' % Off';
                $available_offer[number_format($value->MO_TH)] = $message;

            }

            $final_data['available_offer'] = $available_offer;
            // dd($final_qty);
            // Applied Offer

            $final_data['applied_offer'] = [];

        

        
        //$final_data['item_id']  = $source_item->ITEM;
        $final_data['section_offer'] = $target;        
        //dd($final_data);
        return $final_data;
    }

    public function calculate_shop_offer_of_percentage_tiered($param){

        //dd($param['offer']);
        //$item_id = $param['item_id'];
        $item_desc = $param['item_desc'];
        $rule = $param['offer'];
        $mrp = $param['mrp'];
        $qty = $param['qty'];
        $source_item = $param['source_item'];
        $carts = $param['carts'];
        $section_total = $param['section_total'];
        $cart_item = $param['cart_item'];
        $user_id = $param['user_id'];
        $store_id = $param['store_id'];
        $item_id = $source_item;
        
        if($cart_item){
            $check_product_in_cart =[];
        }else{
            $check_product_in_cart = $carts->where('barcode', $item_id)->first();
        }

        if (empty($check_product_in_cart)) {
            $in_cart = 0;
            $qty = $qty;
            $final_qty = $qty;
        } else {
            $in_cart = 1;
            $qty = $qty + $check_product_in_cart->qty;
            $final_qty = $qty;
        }

        $total_qty = $qty;
        // dd($qty);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        $amount_collection = [];
        $level = '';
        $max_discount = 0;
        $available_offer = [];
        //$value = (array)$param['offer'];
        // Find Max Discount
        //dd($rule);
        //dd($section_total);
        foreach ($rule as $key => $value) {             
            
            //if( $section_total[$value->level][$value->ID_ITM]['total'] >= $value->MO_TH ){
                $level = $value->level;
                $total_saving = $mrp - $value->PE_UN_ITM_PRDV_SLS / 100;
            
                $data[$value->MO_TH] = [ 'ID_RU_PRDV' => $value->ID_RU_PRDV, 'MO_TH' => $value->MO_TH , 'LEVEL' =>$value->level, 'PROMO_TYPE' => $value->promo_type, 'ID' => $source_item ];

                $amount_collection[] = $value->MO_TH;

            //}
                $available_offer[]  = 'shop for '.$value->MO_TH.' above get  '.$value->PE_UN_ITM_PRDV_SLS.' % Off';
        }

        $promo_count = count($data);
        sort($amount_collection);
        //dd($source_item);
        //dd($rule);
        $section_carts = $carts->where($level.'_id', $rule[0]->ID_ITM);
        $all_sources = array_unique($section_carts->pluck('item_id')->all() );

        $all_sources = array_diff($all_sources , [$source_item]);
        //dd($all_sources);
        $cart_source_item = $carts->whereIn('item_id', $all_sources);
        $cart_totals = $cart_source_item->sum('total') ;
        $cart_totals += $mrp * $qty;


        $cartItems =[];
        foreach($cart_source_item as $ikey => $cartItem){
            $loopQty = $cartItem->qty;
            while($loopQty > 0){
                $cartItems[] = ['item_id' => $cartItem->item_id ,'qty' => 1 , 'unit_mrp' => $cartItem->unit_mrp];
                $loopQty--;
            }
        }

        $loopQty = $total_qty;
        while($loopQty > 0){
            $cartItems[] = ['item_id' => $source_item ,'qty' => 1, 'unit_mrp' => $mrp];
            $loopQty--;
        }

        $offer_value = [  'is_promo' => 0];
        foreach ($amount_collection as $amount) {
            if($cart_totals >= $amount ){
                $offer_value = [ 'amount' => $amount ,  'is_promo' => 1];
                $max_discount = $amount;
            }
        }

        //$final_product[] = [ 'is_promo' => 1 , 'qty' => $qty , 'mo_th' => $data['MO_TH']];
        $final_product[] = $offer_value;

            //dd($final_product);
            //dd($cartItems);
            //dd($rule);
            $cartItems = collect($cartItems);
            foreach ($final_product as $key => $value) {
                if ( $value['is_promo'] == 1) {
                    $id = $this->searchAmount($value['amount'] , $rule);
                    // echo '<pre>';
                     //print_r($id);
                    $param= [];
                    $params= [];
                    $cartItems = $cartItems->filter(function($q , $key) use (&$param, &$params) {
                       
                            
                            $params[] = [ 'item_id' => $q['item_id'], 'unit_mrp' => $q['unit_mrp'] ];
                            $param[] = $q['unit_mrp'];
                                  
                            return null;
                    
                    });
                    //dd($params);
                    $offer_amount = ($cart_totals * $id->PE_UN_ITM_PRDV_SLS ) / 100;
                    
                    $ratio_val = $this->get_offer_amount_by_ratio($param, $offer_amount);
                    $ratio_total = array_sum($ratio_val);
                    //dd($ratio_val);
                    $discount = 0;
                    $total_discount = 0;
                    foreach($params as $key => $par){
                        $discount = round( ($ratio_val[$key]/$ratio_total) * $offer_amount , 2);
                        $params[$key]['discount'] =  $discount;
                        $total_discount += $discount;
                    }

                    //Thid code is added because facing issue when rounding of discount value
                    if($total_discount > $offer_amount){
                        $total_diff = $total_discount - $offer_amount;
                        foreach($params as $key => $par){
                            if($total_diff > 0.00){
                                $params[$key]['discount'] -= 0.01;
                                $total_diff -= 0.01;
                            }else{
                                break;
                            }
                        }
                    }else if($total_discount < $offer_amount){
                        $total_diff =  $offer_amount - $total_discount;
                        foreach($params as $key => $par){
                            if($total_diff > 0.00){
                                $params[$key]['discount'] += 0.01;
                                $total_diff -= 0.01;
                            }else{
                                break;
                            }
                        }

                    }

                    $total_qty = 0;
                    $total_price = 0;
                    $discount =0;
                    //dd($params);
                    foreach($params as $key => $par){
                        if($par['item_id'] == $source_item){
                            $total_price += $par['unit_mrp'] * 1;
                            $total_qty += 1;
                            $discount += $par['discount'];
                            $mrp = $par['unit_mrp'];
                        }
                    }

                    $discount = round($discount ,2);
                    //$total_price = $mrp * $value['qty'];
                    //$discount = $offer_amount;
                    $ex_price = $total_price - $discount;  
                    
                    if($total_qty> 0){
        
                        $message = 'shop for '.$id->MO_TH.' above get  '.$id->PE_UN_ITM_PRDV_SLS.' % Off';
                        $final_data['pdata'][] = [ 'qty' => $total_qty, 'mrp' => $mrp, 'discount' => $discount, 'ex_price' => $ex_price, 'total_price' => $total_price, 'message' => $message , 'ru_prdv' => $id->ID_RU_PRDV, 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => ($promo_count == 1 ? 0 : 2), 'is_promo' => 1 ];

                        $applied_offer[] = $message;
                    }

                    //dd($final_data);
                    
                } else {


                    $total_price = 0;
                    $total_qty = 0;
                    $params=[];
                    $cartItems = $cartItems->filter(function($q , $key) use(&$params) {

                        $params[] = [ 'item_id' => $q['item_id'], 'unit_mrp' => $q['unit_mrp'] ];

                    });

                    foreach($params as $key => $par){
                        if($par['item_id'] == $source_item ){
                            $total_price += $par['unit_mrp'] * 1;
                            $total_qty += 1;
                            $mrp = $par['unit_mrp'];
                        }
                    }

                    if($total_qty > 0){
                        $total_price = $mrp * $qty;
                        $final_data['pdata'][] = [ 'qty' => $total_qty, 'mrp' => $mrp, 'discount' => 0, 'ex_price' => $total_price, 'total_price' => $total_price, 'message' => '' , 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'type_id' => '', 'is_slab' => 0, 'is_promo' => 0];
                    }
                }
            }
                
        //dd($final_data);
            // Check item Already Exits in Cart

        if ($in_cart == 1) {
            $final_data['cart_message'] = $check_product_in_cart->qty.' quantity from cart';
        }

        // Available Offer

        $final_data['available_offer'] = $available_offer;

        // dd($final_qty);

        // Applied Offer

        $final_data['applied_offer'] = $applied_offer;

        
        //dd($final_data);
        $final_data['item_id']  = $source_item;
        return $final_data;


    }

    ###########  SECTION FUNCTION ENDS ###################
    ######################################################


    //Bill Buster Function START
    public function shop_bill_get_amount_tiered($total_amount, $qty, $ru_prdv, $item_id, $store_id = '20001', $user_id='test',$cart_item = true){

        //echo $total_amount;exit;
        //dd($ru_prdv);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                if($total_amount >= $value['mo_th'] && $value['mo_th'] > 0.00){
            
                    $condition = DB::table($this->store_db_name.'.co_prdv_itm')->select('MO_UN_ITM_PRDV_SLS')->where('ID_RU_PRDV', $value['ru_prdv'])->first();
                // echo '<pre>';
                // print_r($value);
                // print_r($condition);
                    $total_saving = $condition->MO_UN_ITM_PRDV_SLS;
                    $message = 'Shop for '.$value['mo_th'].' get '.$condition->MO_UN_ITM_PRDV_SLS.' Rs Off on Total Bill';
                    if(isset($data[$total_saving]) && $data[$total_saving]['mo_th'] < $value['mo_th'] ){

                        $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'mo_th' => $value['mo_th'] ,'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'] , 'discount' => $total_saving , 'message' => $message ];
                    }else{

                        $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'mo_th' => $value['mo_th'] ,'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'],  'discount' => $total_saving , 'message' => $message];

                    }
                }
            }
        }
        //dd($data);
        if(!empty($data)){
            $max = max(array_keys($data));
            return $data[$max];
        }else{

            return [];
        }



    }

    public function shop_bill_get_percentage_tiered($total_amount, $qty, $ru_prdv, $item_id, $store_id = '20001', $user_id='test',$cart_item = true){

        //echo $total_amount;exit;
        //dd($ru_prdv);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                if($total_amount >= $value['mo_th'] && $value['mo_th'] > 0.00){
            
                    $condition = DB::table($this->store_db_name.'.co_prdv_itm')->select('PE_UN_ITM_PRDV_SLS')->where('ID_RU_PRDV', $value['ru_prdv'])->first();
                // echo '<pre>';
                // print_r($value);
                // print_r($condition);
                    $total_saving = $total_amount  * $condition->PE_UN_ITM_PRDV_SLS /100;
                    $message = 'Shop for '.$value['mo_th'].' get '.$condition->PE_UN_ITM_PRDV_SLS.' % Off on Total Bill';
                    if(isset($data[$total_saving]) && ($data[$total_saving] == $total_saving) && $data[$total_saving]['mo_th'] < $value['mo_th'] ){

                        $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'mo_th' => $value['mo_th'] ,'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'] , 'discount' => $total_saving , 'offer_amount' => $condition->PE_UN_ITM_PRDV_SLS, 'message' => $message ];
                    }else{

                        $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'mo_th' => $value['mo_th'] ,'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'],  'discount' => $total_saving  , 'offer_amount' => $condition->PE_UN_ITM_PRDV_SLS , 'message' => $message ];

                    }
                }
            }
        }

        //dd($data);

        if(!empty($data)){
            $max = max(array_keys($data));
            return $data[$max];
        }else{

            return [];
        }

    }

    public function shop_bill_get_printed_tiered($total_amount, $qty, $ru_prdv, $item_id, $store_id = '20001', $user_id='test',$cart_item = true){

        //dd($ru_prdv);
        $data = [];
        $final_data = [];
        $final_product = [];
        $applied_offer = [];
        foreach ($ru_prdv as $key => $val) {
            foreach ($val as $key => $value) {
                if($total_amount >= $value['mo_th'] && $value['mo_th'] > 0.00){

            
                $matchProduct = DB::table($this->store_db_name.'.price_master')->select('ITEM','ITEM_DESC')->where('ITEM', $value['max_free_item'] )->first();
                //dd($matchProduct);

                $condition = DB::table($this->store_db_name.'.co_prdv_itm')->select('PE_UN_ITM_PRDV_SLS')->where('ID_RU_PRDV', $value['ru_prdv'])->first();
                // echo '<pre>';
                // print_r($value);
                //dd($conditon);
                    $total_saving = $total_amount  * $condition->PE_UN_ITM_PRDV_SLS /100;
                    $message = 'Shop for '.$value['mo_th'].' get '.$condition->PE_UN_ITM_PRDV_SLS.' % Off on Total Bill';
                    if(isset($data[$total_saving]) && ($data[$total_saving] == $total_saving) && $data[$total_saving]['mo_th'] < $value['mo_th'] ){

                        $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'mo_th' => $value['mo_th'] ,'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'] , 'discount' => $total_saving , 'offer_amount' => $condition->PE_UN_ITM_PRDV_SLS, 'message' => $message ];
                    }else{

                        $data[$total_saving] = [ 'ID_RU_PRDV' => $value['ru_prdv'], 'mo_th' => $value['mo_th'] ,'ID_PRM' => $value['promo_id'], 'TYPE' => $value['type'], 'LEVEL' => $value['level'], 'PROMO_TYPE' => $value['promo_type'],  'discount' => $total_saving  , 'offer_amount' => $condition->PE_UN_ITM_PRDV_SLS , 'message' => $message ];

                    }
                }
            }
        }

        //dd($data);

        if(!empty($data)){
            $max = max(array_keys($data));
            return $data[$max];
        }else{

            return [];
        }

    }
    //Bill Buster function END

    public function searchQty($id, $array)
    {
        foreach ($array as $key => $value) {
            if (number_format($value->QU_TH) == $id) {
                return $value;
            }
        }
        return null;
    }

    public function searchAmount($amount, $array)
    {
        foreach ($array as $key => $value) {
            if ($value->MO_TH == $amount) {
                return $value;
            }
        }
        return null;
    }

    public function find_combination($findfrom , $findfor){
        $combi_array = [];
        sort($findfrom);
        
        foreach($findfrom as $find){
            if($findfor > $find){
                $remainder = $findfor%$find;
                if( ($remainder)==0){
                    $individualCombi = [];
                    $multi = $findfor / $find;
                    
                    while($multi > 0){
                        array_push($individualCombi, $find);
                        $multi--;
                    }
                    
                    $combi_array[] = $individualCombi;
                    
                }else{
                    
                    $individualCombi = [];
                    $multi = (int) ($findfor / $find);
                    while($multi > 0){
                        array_push($individualCombi, $find);
                        $multi--;
                    }
                    
                    if(in_array($remainder,$findfrom)){
                        array_push($individualCombi, $remainder);
                    }
                    $combi_array[] = $individualCombi;
                }
            }
        }
        
        return $combi_array;
    }

    public function format_number($amount)
    {
        //$amount = number_format((float)$amount, 2, '.', '');
        //$amount = round($amount,2);
        if($amount=='' || $amount === null){
            return null;
        }else{
        $amount = sprintf("%.2f",$amount);
        //$amount = round($amount,2);
        return $amount;
        }

    }

    public function get_offer_amount_by_ratio($param , $offer_amount){

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


}
