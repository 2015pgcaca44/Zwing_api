<?php

namespace App\Http\Controllers\V1\Spar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

class PromotionNewController extends Controller
{

    private $store_db_name;

    public function __construct($params = [])
    {

        if(isset($params['store_db_name']) ){
            $this->store_db_name = $params['store_db_name'];
        }else if(isset($params['store_id']) ){
            $this->store_db_name = get_store_db_name( [ 'store_id' => $params['store_id'] ] );
        }
    }

    public function check_multiple_mrp($params){

        $price_master = $params['price_master'] ; 
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

        return ['mrp_arr' => $mrp_arr ,  'csp_arr' => $csp_arr ];
    }

    public function fetching_all_offer($params){

        $item_id = $params['item_id'];
        $level = $params['level'];
        //echo $current = date('Y-m-d H:i:s');
        $today = time();
        //Getting rule of an item

        if($level == 'bill_buster' ){

            $bill_buster = [ 
                'Buy$NorMoreGetZ%offTiered',
                'Buy$NorMoreGetZ$offTiered',
                //'BuyRsNOrMoreGetPrintedItemFreeTiered'
            ]; 

            $list_ru_prdvs = DB::table($this->store_db_name.'.ru_prdv')->select('ID_RU_PRDV','SC_RU_PRDV','TY_RU_PRDV','CD_MTH_PRDV','DE_RU_PRDV','DC_RU_PRDV_EP','DC_RU_PRDV_EF','MAX_ALL_SOURCES','ITM_PRC_CTGY_SRC','MAX_FREE_ITEM','MO_TH_SRC','MAX_PARENT_ID','ID_PRM')->whereIn('DE_RU_PRDV', $bill_buster)->where('MO_TH_SRC','>','0')->orderBy('TS_CRT_RCRD')->get();
        
        }else{

            $max = DB::table($this->store_db_name.'.ru_prdv')
                    ->select(DB::raw(" 'max_co_el' as table_type"),'ru_prdv.ID_PRM','ru_prdv.DC_RU_PRDV_EP','ru_prdv.DC_RU_PRDV_EF','ru_prdv.ID_RU_PRDV','ru_prdv.DE_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC','ru_prdv.CD_BAS_CMP_TGT','ru_prdv.CD_MTH_PRDV','max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH','max_grp_itm_lst.ID_ITM'//,'co_prdv_itm.MO_UN_ITM_PRDV_SLS','co_prdv_itm.PE_UN_ITM_PRDV_SLS','co_prdv_itm.PNT_PRC_UN_ITM_PRDV_SLS'
                        )
                    ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                    ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                    //->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                    //->whereIn('ru_prdv.ID_PRM', $id_prm_arr['max'])
                    ->where('max_grp_itm_lst.ID_ITM', $item_id)
                    //->where(DB::raw("STR_TO_DATE(ru_prdv.DC_RU_PRDV_EP, '%d-%M-%y %h.%i.%s %p')"), '>=' ,$current )
                    //->where(DB::raw("STR_TO_DATE(ru_prdv.DC_RU_PRDV_EF, '%d-%M-%y %h.%i.%s %p')"), '<=' ,$current )
                    ->get();

            if($level == 'item'){

                $col = DB::table($this->store_db_name.'.ru_prdv')
                    ->select(DB::raw(" 'col_el' as table_type"),'ru_prdv.ID_PRM','ru_prdv.DC_RU_PRDV_EP','ru_prdv.DC_RU_PRDV_EF','ru_prdv.ID_RU_PRDV','ru_prdv.DE_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC','ru_prdv.CD_MTH_PRDV','ru_prdv.CD_BAS_CMP_TGT', 'co_el_prdv_itm.MO_TH', 'co_el_prdv_itm.QU_TH','co_el_prdv_itm.ID_ITM'//,'co_prdv_itm.MO_UN_ITM_PRDV_SLS','co_prdv_itm.PE_UN_ITM_PRDV_SLS', 'co_prdv_itm.PNT_PRC_UN_ITM_PRDV_SLS','ru_prdv.CD_BAS_CMP_TGT'
                    )
                    ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                    //->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                    ->where('co_el_prdv_itm.ID_ITM', $item_id)
                    //->whereIn('ru_prdv.ID_PRM', $id_prm_arr['co_el'])
                    //->where(DB::raw("STR_TO_DATE(ru_prdv.DC_RU_PRDV_EP, '%d-%M-%y %h.%i.%s %p')"), '>=' ,$current )
                    //->where(DB::raw("STR_TO_DATE(ru_prdv.DC_RU_PRDV_EF, '%d-%M-%y %h.%i.%s %p')"), '<=' ,$current )
                    ->get();

                
            }

            if(isset($col)){
                $list_ru_prdvs =  $max->merge($col);
            }else{
                $list_ru_prdvs =  $max;
            }
        }

        //Filtering the rule based on date validation
        $list_ru_prdvs = $list_ru_prdvs->filter(function($item) use($today){
            $startdate = date_create_from_format('d-M-y h.i.s A' ,$item->DC_RU_PRDV_EF );
            $startdate = $startdate->getTimestamp();
            $enddate = date_create_from_format('d-M-y h.i.s A' ,$item->DC_RU_PRDV_EP );
            $enddate = $enddate->getTimestamp();
            if (($today >= $startdate) && ($today <= $enddate)) {
                return $item;
            }

        }); 

        return $list_ru_prdvs;
    }

    public function calculating_individual_rule_offer($params){
        //dd($params);
        $rule = $params['rule'];
        $cart_items = $params['cart_items'];
        $all_sources = $params['all_sources'];
        $mrp = $params['mrp'];
        $total_qty = $params['total_qty'];
        $offer_cal_flag = $params['offer_cal_flag'];
        $target_offer_message = $params['target_offer_message'];
        $item_id = $params['item_id'];
        $level = $params['level'];
        $offer_message_prefix = '';
        $offer_message = '';
        $qty_message ='';

        if($rule->DE_RU_PRDV == 'Buy$NofXatZ$offTiered' || ( isset($params['based_on']) && $params['based_on']=='amount') ) {//Offer based to total Amount
            
            $offer_message_prefix = 'Shop for ';             
            //condtion added or  showing available offers
            if($rule->CD_MTH_PRDV == 1){
                if($rule->PE_UN_ITM_PRDV_SLS > 0.0){
                  $offer_message =$offer_message_prefix.$rule->MO_TH.' above'.$target_offer_message.' for '.$rule->PE_UN_ITM_PRDV_SLS.' % Off';  
                }else{
                    return null;
                }
               
            }else if($rule->CD_MTH_PRDV == 2){
                if($rule->MO_UN_ITM_PRDV_SLS > 0.0){
                    $offer_message = $offer_message_prefix.$rule->MO_TH.' above'.$target_offer_message.'for '.$rule->MO_UN_ITM_PRDV_SLS.' Off ';
                }else{
                    return null;
                }
            }
            else if($rule->CD_MTH_PRDV == 3){
                if($rule->PNT_PRC_UN_ITM_PRDV_SLS > 0.0){
                    $offer_message = $offer_message_prefix.$rule->MO_TH.' above'.$target_offer_message.' for '.' Rs. '.$rule->PNT_PRC_UN_ITM_PRDV_SLS;
                }else{
                   return null; 
                }
            }/*else if($rule->CD_MTH_PRDV == 4){
                'buy '.$rule->QU_TH.' for '.$rule->PE_UN_ITM_PRDV_SLS.' % Off'.$qty_message;
            }*/
            $available_offer[] = $offer_message;

            //dd($all_sources);
            //Getting only item from cart which is present in all Sources and splittig by qty
            $cartSourceItems = [];
            $total_qty = 0;
            foreach ($cart_items as $key => $items) {
                if(in_array($items['item_id'] , $all_sources) ){
                    while($items['qty'] > 0){
                        $cartSourceItems[] = ['item_id' =>$items['item_id'] , 'unit_mrp' => $items['unit_mrp']  ,'qty' => 1 ] ;
                        $items['qty']--;
                        $total_qty++;
                    }
                }
            }
            $cartSourceItems =collect($cartSourceItems);
            $cart_source_total = $cartSourceItems->sum('unit_mrp');
            //dd($cartSourceItems);
           
            //Calculating Offers
            if($cart_source_total >= $rule->MO_TH ){

                $offer = [ 'promo_id' => $rule->ID_PRM , 'rule_id' => $rule->ID_RU_PRDV ];
                $priceArray = $cartSourceItems->pluck('unit_mrp')->all();
                $ratios = $this->get_offer_amount_by_ratio( $priceArray );
                $ratio_total = array_sum($ratios);

                if($level != 'item'){
                    $cartSourceFilterItems = $cartSourceItems->where('item_id', $item_id);
                }else{
                    $cartSourceFilterItems = $cartSourceItems;
                }

                // Percentage Off
                if($rule->CD_MTH_PRDV == 1){
                    $total_price = $cartSourceFilterItems->sum('unit_mrp');
                    $discount = $total_price * $rule->PE_UN_ITM_PRDV_SLS / 100 ;
                }

                //Amount off
                if($rule->CD_MTH_PRDV == 2){
                    $discount = $rule->MO_UN_ITM_PRDV_SLS ;
                    $total_price = $mrp * $rule->QU_TH;
                    $ex_price = $total_price - $discount ;

                }

                //Fixed Price off
                if($rule->CD_MTH_PRDV == 3){
                    $total_price = $cartSourceFilterItems->sum('unit_mrp');
                    $discount = $total_price - $rule->PNT_PRC_UN_ITM_PRDV_SLS ;
                }

                if($discount > 0 ){
                    $offer = array_merge($offer ,  [ 'amount' => $rule->MO_TH, 'discount' => $discount, 'message' => $offer_message ]
                    );
                }
                // dd($cartSourceItems);
                // distributing discount amount based on ratio to all items
                $total_discount =0;
                $cartSourceItems->transform(function($item , $key) use($ratios, $ratio_total, $discount , &$total_discount){
                    $discount = round( ($ratios[$key]/$ratio_total) * $discount , 2);
                    $total_discount += $discount;
                    return array_merge($item , [ 'discount' => $discount ] );
                });

                //This code is added because facing issue when rounding of discount value
                if($total_discount > $discount){
                    $total_diff = $total_discount - $discount;
                    $cartSourceItems->transform(function($item, $key)use(&$total_diff){
                        if($total_diff > 0.00){
                            $item['discount'] -= 0.01;
                            $total_diff -= 0.01;
                        }
                        return $item;
                    });

                }else if($total_discount < $discount){
                    $total_diff =  $discount - $total_discount;
                    $cartSourceItems->transform(function($item, $key)use(&$total_diff){
                        if($total_diff > 0.00){
                            $item['discount'] += 0.01;
                            $total_diff -= 0.01;
                        }
                        return $item;
                    });
                }

                //dd($cartSourceItems);
                $pdata = [];

                $currentItems = $cartSourceItems->where('item_id', $item_id);
                //dd($currentItems);
                foreach ($currentItems as $key => $val) {
                    //dd($val);
                    if($val['discount'] > 0 ){
                        $total_price = $mrp * $val['qty'];
                        $pdata[] =   [ 'qty' => $val['qty'], 'mrp' => $mrp, 'discount' => $val['discount'], 'ex_price' => $total_price - $val['discount'] , 'total_price' => $total_price, 'message' => '', 'ru_prdv' => $rule->ID_RU_PRDV, 'type' => '', 'promo_id' => $rule->ID_PRM, 'is_promo' => 1 ];
                    }
                }
                $offer['pdata'] = $pdata;

                if(isset($offer['pdata']) && !empty($offer['pdata'])){
                    $offer['amount'] = (string)$offer['amount'];
                    $offer['based_on'] = 'amount';
                    //$offer_arr[] = $offer;
                    return ['available_offer' => $offer_message  , 'offer' => $offer];
                }else{
                    return ['available_offer' => $offer_message , 'offer' => null];
                }

            }

        }else{//Offer Based on Qty

            $all_sources_check = false;
            //IF Rule have condition all sources This is Incomplete
            if($rule->ITM_PRC_CTGY_SRC == 'allSources' || $rule->MAX_ALL_SOURCES == 'allSources'){
                $cart_item_list = $cart_items->pluck('qty','item_id')->all();

                foreach($all_sources as $sources){
                    if(isset($cart_item_list[$sources]) ){
                        if( $cart_item_list[$sources] >= $rule->QU_TH ){
                            $all_sources_check = true;
                        }else{
                           $all_sources_check = false; 
                           break;
                        }
                    }else{
                        $all_sources_check = false; 
                        break;
                    }
                }
                //All sources for target items is Incomplete
                /*if( $qty >= $rule->QU_LM_MXMH){

                }else{
                    $all_sources_check = false; 
                }*/
            }

            //If Rule have Quantity any Sources
            $source_any_qty = false;
            if($rule->CD_BAS_CMP_SRC == '7'){ //mix source
                $source_any_qty = true;
            }

            
            $add_qty =1;
            if($rule->QU_AN_SRC > 0){ // including any 
                $source_any_qty = true;
                $add_qty = $rule->QU_AN_SRC;
                $qty_message = ' including any '.$rule->QU_AN_SRC;
            }
            //dd($cart_items);
            $cart_source_qty = 0;
            $cartSourceItems = [];
            if($source_any_qty){
                // dd($cart_items);
                //Getting only item from cart which is present in all Sources and splittig by qty
                
                $total_qty = 0;
                foreach ($cart_items as $key => $items) {
                    if(in_array($items['item_id'] , $all_sources) ){
                        while($items['qty'] > 0){
                            $cartSourceItems[] = ['item_id' =>$items['item_id'] , 'unit_mrp' => $items['unit_mrp'] , 'qty' => 1 ] ;
                            $items['qty']--;
                            $total_qty++;
                        }
                    }
                }
                $cartSourceItems =collect($cartSourceItems);
                //dd($filteredCart);
            }else if( $all_sources_check){
                $qty_message = ' include all';
                foreach ($cart_items as $key => $items) {
                    if(in_array($items['item_id'] , $all_sources) ){
                        while($items['qty'] > 0){
                            $cartSourceItems[] = ['item_id' =>$items['item_id'] , 'unit_mrp' => $items['unit_mrp'] , 'qty' => 1 ] ;
                            $items['qty']--;
                        }
                    }
                }
                $cartSourceItems =collect($cartSourceItems);
            }else{

                $items = $cart_items->where('item_id',$item_id)->first();
                if(in_array($items['item_id'] , $all_sources) ){
                    while($items['qty'] > 0){
                        $cartSourceItems[] = ['item_id' =>$items['item_id'] , 'unit_mrp' => $items['unit_mrp'] , 'qty' => 1 ] ;
                        $items['qty']--;
                        $total_qty++;
                    }
                }
                
                $cartSourceItems =collect($cartSourceItems);
            }

            //dd($cartSourceItems);

            $higest_lowest_offer = ['BuyNofXgetLowestPricedXatZ%off' ,
                                    'BuyNofXgetHighestPricedXatZ%off',
                                    'BuyNofXgetMofXwithLowestPriceatZ%off'];

            if(in_array($rule->DE_RU_PRDV, $higest_lowest_offer )){
                $offer_message_prefix = 'buy any '; 
                if($rule->DE_RU_PRDV == 'BuyNofXgetHighestPricedXatZ%off'){
                    $target_offer_message = ', get Higest item ';
                }else{
                    $target_offer_message = ', get Lowest item ';
                }
                
            }else{
                $offer_message_prefix = 'buy '; 
            }
            //condtion added or  showing available offers
            if($rule->CD_MTH_PRDV == 1){
               $offer_message =$offer_message_prefix.$rule->QU_TH.$target_offer_message.' for '.$rule->PE_UN_ITM_PRDV_SLS.' % Off'.$qty_message;
            }else if($rule->CD_MTH_PRDV == 2){
                $offer_message = $offer_message_prefix.$rule->QU_TH.$target_offer_message.' for Rs. '.$rule->MO_UN_ITM_PRDV_SLS.' Off '.$qty_message;
            }
            else if($rule->CD_MTH_PRDV == 3){
                $offer_message = $offer_message_prefix.$rule->QU_TH.$target_offer_message.' for Rs. '.$rule->PNT_PRC_UN_ITM_PRDV_SLS.$qty_message;
            }/*else if($rule->CD_MTH_PRDV == 4){
                'buy '.$rule->QU_TH.' for '.$rule->PE_UN_ITM_PRDV_SLS.' % Off'.$qty_message;
            }*/
            $available_offer[] = $offer_message;
        
            //Calculating Offers
            if( ($total_qty >=  ($rule->QU_TH * $add_qty)) && $offer_cal_flag || $all_sources_check ){
                $cartSourceItems = $cartSourceItems->sortByDesc('unit_mrp');
                //dd($cartSourceItems);
                //Getting split items based on the offer quantity
                $offer_qty = $rule->QU_TH * $add_qty;

                if(!$all_sources_check){
                    $cartSourceItems = $cartSourceItems->filter(function($item , $key) use(&$offer_qty){
                        if($offer_qty > 0 ){
                            $offer_qty--;
                            return true;
                        }
                    });
                }

                $cartSourceItems = $cartSourceItems->values();
                //dd($cartSourceItems);
                $offer = ['promo_id' => $rule->ID_PRM , 'rule_id' => $rule->ID_RU_PRDV ];
                $priceArray = $cartSourceItems->pluck('unit_mrp')->all();
                $ratios = $this->get_offer_amount_by_ratio( $priceArray );
                $ratio_total = array_sum($ratios);


                if(in_array($rule->DE_RU_PRDV, $higest_lowest_offer)){
                    if($rule->DE_RU_PRDV == 'BuyNofXgetHighestPricedXatZ%off'){
                        $cartSourceFilterItems = $cartSourceItems->first();
                    }else{
                        $cartSourceFilterItems = $cartSourceItems->last();
                    }
                    $cartSourceFilterItems = collect([$cartSourceFilterItems]);
                }else{
                    $cartSourceFilterItems = $cartSourceItems;
                }
                //dd($cartSourceFilterItems);
                //dd($rule);
                // Percentage Off
                if($rule->CD_MTH_PRDV == 1){
                    $total_price = $cartSourceFilterItems->sum('unit_mrp');
                    $discount = $total_price * $rule->PE_UN_ITM_PRDV_SLS / 100 ;
                    //$message =$offer_message_prefix.$rule->QU_TH.' for '.$rule->PE_UN_ITM_PRDV_SLS.' % Off'.$qty_message;
                }

                //Amount off
                if($rule->CD_MTH_PRDV == 2){
                    $discount = $rule->MO_UN_ITM_PRDV_SLS ;
                    $total_price = $mrp * $rule->QU_TH;
                    $ex_price = $total_price - $discount ;
                    //$message = $offer_message_prefix.$rule->QU_TH.' for Rs. '.$rule->MO_UN_ITM_PRDV_SLS.' Off '.$qty_message;

                }

                //Fixed Price off
                if($rule->CD_MTH_PRDV == 3){
                    $total_price = $cartSourceFilterItems->sum('unit_mrp');
                    $discount = $total_price - $rule->PNT_PRC_UN_ITM_PRDV_SLS ;
                    
                    //$message = $offer_message_prefix.$rule->QU_TH.' for Rs. '.$rule->PNT_PRC_UN_ITM_PRDV_SLS.' Off '.$qty_message;
                }
                //dd($discount);
                if($discount > 0 ){
                    $offer = array_merge($offer ,  [ 'qty' => $rule->QU_TH * $add_qty, 'discount' => $discount, 'message' => $offer_message ]
                    );
                }
                // dd($cartSourceItems);
                // distributing discount amount based on ratio to all items
                $total_discount =0;
                $cartSourceItems->transform(function($item , $key) use($ratios, $ratio_total, $discount , &$total_discount){
                    $discount = round( ($ratios[$key]/$ratio_total) * $discount , 2);
                    $total_discount += $discount;
                    return array_merge($item , [ 'discount' => $discount ] );
                });
                //This code is added because facing issue when rounding of discount value
                if($total_discount > $discount){
                    $total_diff = $total_discount - $discount;
                    $cartSourceItems->transform(function($item, $key)use(&$total_diff){
                        if($total_diff > 0.00){
                            $item['discount'] -= 0.01;
                            $total_diff -= 0.01;
                        }
                        return $item;
                    });

                }else if($total_discount < $discount){
                    $total_diff =  $discount - $total_discount;
                    $cartSourceItems->transform(function($item, $key)use(&$total_diff){
                        if($total_diff > 0.00){
                            $item['discount'] += 0.01;
                            $total_diff -= 0.01;
                        }
                        return $item;
                    });
                }

                //dd($cartSourceItems);
                $pdata = [];

                $currentItems = $cartSourceItems->where('item_id', $item_id);
                foreach ($currentItems as $key => $val) {
                    //dd($val);
                    if($val['discount'] > 0 ){
                        $total_price = $mrp * $val['qty'];
                        $pdata[] =   [ 'qty' => $val['qty'], 'mrp' => $mrp, 'discount' => $val['discount'], 'ex_price' => $total_price - $val['discount'] , 'total_price' => $total_price, 'message' => '', 'ru_prdv' => $rule->ID_RU_PRDV, 'type' => '', 'promo_id' => $rule->ID_PRM, 'is_promo' => 1 ];
                    }
                }
                $offer['pdata'] = $pdata;


                if(isset($offer) && !empty($offer)){
                    $offer['qty'] = (string)$offer['qty'];
                    $offer['based_on'] = 'qty';
                    //$offer_arr[] = $offer;
                    return ['available_offer' => $offer_message  , 'offer' => $offer];
                }else{
                    return ['available_offer' => $offer_message , 'offer' => null];
                }
            }
            
        }
        return ['available_offer' => $offer_message , 'offer' => null];
        
    }

    public function calculating_group_rule($params){

        //dd($params);
        $level = $params['level'];
        $cart = $params['cart'];
        $cart_items = $params['cart_items'];
        $item_id =  $params['item_id'];
        $item_qty =  $params['item_qty'];
        $mrp = $params['mrp'];
        $total_qty = $item_qty;

        $based_on = null;
        if($level != 'item'){
            $based_on = 'amount';
        }
        

        $list_ru_prdvs = $params['list_ru_prdvs'];

        $available_offer = [];
        $applied_offer = [];
        $target = [];
        $final_data = [];

        $offer_arr = [];

        foreach ($list_ru_prdvs as $key => $rule) {
            $promo_list=null;
            unset($rule->DC_RU_PRDV_EP);
            unset($rule->DC_RU_PRDV_EF);
            //Need to check that all select is required or not
            //dd($rule);
            //Getting all Item of paticular rule
            if($rule->table_type == 'max_co_el'){
                $promo_list = DB::table($this->store_db_name.'.ru_prdv')
                            ->select(//'ru_prdv.ID_PRM','ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH', 'co_prdv_itm.MO_UN_ITM_PRDV_SLS','co_prdv_itm.PE_UN_ITM_PRDV_SLS','co_prdv_itm.PNT_PRC_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV',
                                'max_grp_itm_lst.ID_ITM')
                            ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                            ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                            //->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $rule->ID_PRM)
                            ->get();

            }

            if($rule->table_type == 'col_el'){
                $promo_list = DB::table($this->store_db_name.'.ru_prdv')
                            ->select(//'ru_prdv.ID_PRM','ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC','ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'co_el_prdv_itm.MO_TH', 'co_el_prdv_itm.QU_TH', 'ru_prdv.CD_MTH_PRDV','co_prdv_itm.MO_UN_ITM_PRDV_SLS','co_prdv_itm.PE_UN_ITM_PRDV_SLS', 'co_prdv_itm.PNT_PRC_UN_ITM_PRDV_SLS',
                            'co_el_prdv_itm.ID_ITM')
                            ->join($this->store_db_name.'.co_el_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_el_prdv_itm.ID_RU_PRDV')
                            //->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                            ->where('ru_prdv.ID_PRM', $rule->ID_PRM)
                            //->where('co_el_prdv_itm.ID_ITM', $item_id)
                            ->get();
            }

            //This condition is added for target offer
            $target_offer_rule = ['BuyNofXgetYatZ%off','BuyNofXgetYatZ$off','BuyNofXgetYatZRs','BuyNofXgetYatZ$'];
            if(in_array($rule->DE_RU_PRDV, $target_offer_rule) || $level !='item' ){
                
                $condition = DB::table($this->store_db_name.'.tr_itm_mxmh_prdv')
                ->select('MO_RDN_PRC_MXMH as MO_UN_ITM_PRDV_SLS', 'PE_RDN_PRC_MXMH as PE_UN_ITM_PRDV_SLS', 'PNT_PRC_RDN_MXMH as PNT_PRC_UN_ITM_PRDV_SLS','ID_PRM_PRD','QU_LM_MXMH','ID_PRM_PRD')
                ->where('ID_RU_PRDV', $rule->ID_RU_PRDV)->first();
                
                //Getting Target Products
                if($rule->CD_BAS_CMP_TGT == 7){
                    $grp_list = DB::table($this->store_db_name.'.max_grp_itm_lst')->select('ID_ITM')->where('ID_GRP', $condition->ID_PRM_PRD )->first();
                    $target_product = DB::table($this->store_db_name.'.price_master')
                    ->select('ITEM','ITEM_DESC')
                    ->where('ITEM', $grp_list->ID_ITM)->first();
                }else{
                    
                    $target_product = DB::table($this->store_db_name.'.price_master')
                    ->select('ITEM','ITEM_DESC')
                    ->where('ITEM', $condition->ID_PRM_PRD)->first();
                }
                
            }else{

                $condition = DB::table($this->store_db_name.'.co_prdv_itm')
                ->select('MO_UN_ITM_PRDV_SLS','PE_UN_ITM_PRDV_SLS', 'PNT_PRC_UN_ITM_PRDV_SLS')
                ->where('co_prdv_itm.ID_RU_PRDV' , $rule->ID_RU_PRDV)
                ->first();
            }

            //Adding the condtion key into rule variable 
            if($condition){
                $rule->MO_UN_ITM_PRDV_SLS = $condition->MO_UN_ITM_PRDV_SLS;
                $rule->PE_UN_ITM_PRDV_SLS = $condition->PE_UN_ITM_PRDV_SLS;
                $rule->PNT_PRC_UN_ITM_PRDV_SLS = $condition->PNT_PRC_UN_ITM_PRDV_SLS;
                if(isset($condition->QU_LM_MXMH)){
                   $rule->QU_LM_MXMH =  $condition->QU_LM_MXMH;
                }
            }
            //dd($rule);
            //$promo_list =  $max_promo->merge($col_promo);
            //dd($promo_list);
            if($level == 'item'){
                $all_sources = array_unique( $promo_list->pluck('ID_ITM')->all() );   
            }else{
                $section_carts = $cart->where($level.'_id', $rule->ID_ITM);
                $all_sources = array_unique( $section_carts->pluck('item_id')->all() );
            }
            //dd($all_sources);
            
            //Can we add this code in calculating_individual_rule_offer
            //calculating_individual_rule_offer use here and in ITEM Target Offer
            $target_offer_message ='';
            $offer_cal_flag = true;
            if(isset($rule->QU_LM_MXMH)){
                $tRule = (array) $rule;
                $tRule['product_list'] = $all_sources;
                $tRule['target_item'] = $target_product->ITEM;
                $target[$target_product->ITEM][] = $tRule;
                $target_offer_message = ' , get '.$target_product->ITEM_DESC.' ';

                //This condition added to calculation target item offer when target item is present . Offer sould not calculate when sources is present
                if(isset($rule->target_item) && $item_id == $rule->target_item ){

                }else{
                    $offer_cal_flag = false;
                }
            }

            ########################################
            ## calculation Individual Rule Offers ##
            $offers = $this->calculating_individual_rule_offer(['rule' => $rule , 'cart_items' => $cart_items , 'all_sources' => $all_sources , 'mrp' => $mrp, 'offer_cal_flag' => $offer_cal_flag , 'target_offer_message' => $target_offer_message, 'item_id' => $item_id, 'total_qty' => $total_qty ,'based_on' => $based_on , 'level' => $level]  );

            if($offers){
                $offer = $offers['offer'];
                $available_offer[] = $offers['available_offer'];
                if($offer){
                    $offer_arr[] = $offer;
                }
            }
            ## calculation Individual Rule Offers ##
            #########################################
        }
        
        //ITEM Target Offer;
        ###### Target Offers for Item START #####################
        $target_offer = [];

        if($level == 'item'){
            $cart_offers = $cart->pluck('target_offer');
            foreach($cart_offers as $tOffer){
                if($tOffer !='' && !empty($tOffer) ){
                    $off = json_decode($tOffer);
                    $first_key = key($off);
                    $target_offer[$first_key] = $off->$first_key;
                }
            }

        }else{
            $cart_offers = $cart->pluck('section_target_offers');
            //dd($cart_offers);
            foreach($cart_offers as $tOffer){
                if($tOffer !='' && !empty($tOffer) ){
                    $off = json_decode($tOffer);
                    $off = $off->$level;
                    foreach ($off as $items_id => $off) {
                        $target_offer[$items_id] = $off;
                    }                    
                }
            }
        }  
        
        //dd($item_id);
        if(isset($target_offer[$item_id])){
            foreach($target_offer[$item_id] as $rule){
               
                //$all_sources Need to take from $rule['product_list']
                if($level == 'item'){
                    $all_sources = array_unique( $promo_list->pluck('ID_ITM')->all() );   
                }else{
                    $section_carts = $cart->where($level.'_id', $rule->ID_ITM);
                    $all_sources = array_unique( $section_carts->pluck('item_id')->all() );
                }

                $all_sources = array_merge($all_sources,[$item_id]);
                $offer_cal_flag = true;
                $target_offer_message = ' ';
                ########################################
                ## calculation Individual Rule Offers ##
                $offers = $this->calculating_individual_rule_offer(['rule' => $rule , 'cart_items' => $cart_items , 'all_sources' => $all_sources, 'mrp' => $mrp, 'offer_cal_flag' => $offer_cal_flag , 'target_offer_message' => $target_offer_message , 'total_qty' => $total_qty, 'item_id' => $item_id,'based_on' => $based_on, 'level' => $level]  );
                //dd($offers);    
                if($offers){
                    $offer = $offers['offer'];
                    $available_offer[] = $offers['available_offer'];
                    if($offer){
                        $offer_arr[] = $offer;
                    }
                }
                ## calculation Individual Rule Offers ##
                #########################################
            }
        }
        ###### Target Offers for Item ENDS #####################
        //dd($offer_arr);
        $qty_offer_arr=[];
        $amount_offer_arr=[];
        foreach($offer_arr as $offer_type){
            if($offer_type['based_on'] == 'qty'){
                $qty_offer_arr[] = $offer_type;
            }
            if($offer_type['based_on'] == 'amount'){
                $amount_offer_arr[] = $offer_type;
            }
            
        }
        //dd($amount_offer_arr);
        //Getting all promotions and finding best promotions suitable base on quantity and filtering an offer of same quantity
        $temp_qty = $total_qty;
        if(count($qty_offer_arr) > 0 ){
            $offerCollection = collect($qty_offer_arr);
            $qtyGroup= $offerCollection->groupBy('qty');
            //dd($qtyGroup);
            //Getting only max discount offer of same qty
            $qtyGroup->transform(function($item , $key){
                $discount = $item->max('discount');
                $item->filter(function($subItem , $subkey) use($discount){
                    return $subItem['discount'] == $discount;
                });
                return $item;
            });
            $offerCollection = $qtyGroup->flatten(1);

            //dd($offerCollection);
            //Getting a offer of best combination of unique qty
            $quantity_collection = $offerCollection->pluck('qty')->all();
            $combinations = $this->find_combination($quantity_collection , $total_qty);
            //dd($combinations);
            $best_combi = [];
            $final_data=[];
        
            if(!empty($combinations) ) {
                $best_combi =['discount' => 0 , 'key' => 0 ];
                foreach($combinations as $key => $combination){
                    $current_combi = ['discount' => 0 , 'key' => $key ];
                    foreach($combination as $combi_qty){
                        $offer = $offerCollection->where('qty' , $combi_qty)->first();
                        $current_combi['discount'] += $offer['discount'];
                    }

                    if($current_combi['discount'] >= $best_combi['discount']){
                        $best_combi = $current_combi;
                    }
                }
                $pdata = [];
                foreach ($combinations[$best_combi['key']] as $combi_qty) {
                    $temp_qty -= $combi_qty;
                    $offer = $offerCollection->where('qty' , $combi_qty)->first();
                    //dd($offer);
                    $applied_offer[] = $offer['message'];
                    $pdata = array_merge( $pdata , $offer['pdata']);
                }
                $final_data['pdata'] = $pdata;
            }
        }

        //Getting all promotions and finding best promotions suitable base on Amount and filtering an offer of same quantity
        if(count($amount_offer_arr) > 0 ){

            $offerCollection = collect($amount_offer_arr);
            $amountGroup= $offerCollection->groupBy('amount');
            //dd($qtyGroup);
            //Getting only max discount offer of same amount
            $amountGroup->transform(function($item , $key){
                $discount = $item->max('discount');
                $item->filter(function($subItem , $subkey) use($discount){
                    return $subItem['discount'] == $discount;
                });
                return $item;
            });

            $pdata = [];
            $offerCollection = $amountGroup->flatten(1);
            $max_discount = $offerCollection->max('discount');
            $offer = $offerCollection->where('discount' , $max_discount)->first();
            //Getting a offer of best combination of unique qty
            $applied_offer[] = $offer['message'];
            $final_data['pdata'] = $offer['pdata'];
          
            //if offered applied make temp_qty zero if not applied then 
            $temp_qty = 0;
        }


        $final_data['available_offer'] = $available_offer;
        $final_data['applied_offer'] = $applied_offer;
        $final_data['target'] = $target;
        $final_data['level'] = $level;
        //dd($final_data);
        return $final_data;
    }

    public function section_level_offer_calculation($params){
        
        $item_master = $params['item_master'];
        $cart = $params['cart'];
        $cart_items = $params['cart_items'];
        $item_id =  $params['item_id'];
        $item_qty =  $params['item_qty'];
        $mrp = $params['mrp'];

        $item_val = $cart_items->where('item_id', $item_id)->first();
        if(!$item_val){
            $cart_items->push(['item_id' => $item_id , 'qty' => 1 , 'unit_mrp' => $mrp]);
        }

        //dd($cart_items);
        $total_qty = $item_qty;
    
        $section_target = [];

        $section_rule = [];
        $section_rule['subclass'] = $this->fetching_all_offer(['item_id' => $item_master->ID_MRHRC_GP_SUBCLASS, 'level' => 'subclass']);
        $section_rule['printclass'] = $this->fetching_all_offer(['item_id' => $item_master->ID_MRHRC_GP_PRNT_CLASS, 'level' => 'printclass']);
        $section_rule['department'] = $this->fetching_all_offer(['item_id' => $item_master->ID_MRHRC_GP_PRNT_DEPT, 'level' => 'department']);
        $section_rule['group'] = $this->fetching_all_offer(['item_id' => $item_master->ID_MRHRC_GP_PRNT_GROUP, 'level' => 'group']);
        $section_rule['division'] = $this->fetching_all_offer(['item_id' => $item_master->ID_MRHRC_GP_PRNT_DIVISION, 'level' => 'division']);
        
        //dd($section_rule);
        //dd($list_ru_prdvs);
        $offer_arr = [];
        $available_offer = [];
        $applied_offer = [];
        foreach($section_rule as $level => $list_ru_prdvs){
            if($level != 'department'){
                continue;
            }
            $target = [];
            foreach ($list_ru_prdvs as $key => $rule) {
                
                $promo_list=null;
                unset($rule->DC_RU_PRDV_EP);
                unset($rule->DC_RU_PRDV_EF);
                //Need to check that all select is required or not
                //dd($rule);
                //Getting all Item of paticular rule
                if($rule->table_type == 'max_co_el'){

                    $promo_list = DB::table($this->store_db_name.'.ru_prdv')
                        ->select(//'ru_prdv.ID_PRM','ru_prdv.ID_RU_PRDV','ru_prdv.CD_BAS_CMP_SRC','ru_prdv.QU_AN_SRC', 'ru_prdv.QU_AN_TGT','ru_prdv.MAX_ALL_SOURCES','ru_prdv.ITM_PRC_CTGY_SRC', 'max_co_el_prdv_itm_grp.MO_TH', 'max_co_el_prdv_itm_grp.QU_TH', 'co_prdv_itm.MO_UN_ITM_PRDV_SLS','co_prdv_itm.PE_UN_ITM_PRDV_SLS','co_prdv_itm.PNT_PRC_UN_ITM_PRDV_SLS', 'ru_prdv.CD_MTH_PRDV',
                            'max_grp_itm_lst.ID_ITM')
                        ->join($this->store_db_name.'.max_co_el_prdv_itm_grp', 'ru_prdv.ID_RU_PRDV', '=', 'max_co_el_prdv_itm_grp.ID_RU_PRDV')
                        ->join($this->store_db_name.'.max_grp_itm_lst', 'max_co_el_prdv_itm_grp.ID_GRP', '=', 'max_grp_itm_lst.ID_GRP')
                        //->join($this->store_db_name.'.co_prdv_itm', 'ru_prdv.ID_RU_PRDV', '=', 'co_prdv_itm.ID_RU_PRDV')
                        ->where('ru_prdv.ID_PRM', $rule->ID_PRM)
                        ->get();

                }

                $condition = DB::table($this->store_db_name.'.tr_itm_mxmh_prdv')
                ->select('MO_RDN_PRC_MXMH as MO_UN_ITM_PRDV_SLS', 'PE_RDN_PRC_MXMH as PE_UN_ITM_PRDV_SLS', 'PNT_PRC_RDN_MXMH as PNT_PRC_UN_ITM_PRDV_SLS','ID_PRM_PRD','QU_LM_MXMH','ID_PRM_PRD')
                ->where('ID_RU_PRDV', $rule->ID_RU_PRDV)->first();
                
                //Getting Target Products
                if($rule->CD_BAS_CMP_TGT == 7){
                    $grp_list = DB::table($this->store_db_name.'.max_grp_itm_lst')->select('ID_ITM')->where('ID_GRP', $condition->ID_PRM_PRD )->first();
                    $target_product = DB::table($this->store_db_name.'.price_master')
                    ->select('ITEM','ITEM_DESC')
                    ->where('ITEM', $grp_list->ID_ITM)->first();
                }else{
                    
                    $target_product = DB::table($this->store_db_name.'.price_master')
                    ->select('ITEM','ITEM_DESC')
                    ->where('ITEM', $condition->ID_PRM_PRD)->first();
                }

                if($condition){
                    $rule->MO_UN_ITM_PRDV_SLS = $condition->MO_UN_ITM_PRDV_SLS;
                    $rule->PE_UN_ITM_PRDV_SLS = $condition->PE_UN_ITM_PRDV_SLS;
                    $rule->PNT_PRC_UN_ITM_PRDV_SLS = $condition->PNT_PRC_UN_ITM_PRDV_SLS;
                    if(isset($condition->QU_LM_MXMH)){
                       $rule->QU_LM_MXMH =  $condition->QU_LM_MXMH;
                    }
                }

                $section_carts = $cart->where($level.'_id', $rule->ID_ITM);
                $all_sources = array_unique( $section_carts->pluck('item_id')->all() );
                //dd($all_sources);

                $target_offer_message ='';
                $offer_cal_flag = true;
                if(isset($rule->QU_LM_MXMH)){
                    $tRule = (array) $rule;
                    $tRule['product_list'] = $all_sources;
                    $tRule['target_item'] = $target_product->ITEM;
                    $target[$target_product->ITEM][] = $tRule;
                    $target_offer_message = ' , get '.$target_product->ITEM_DESC.' ';

                    //This condition added to calculation target item offer when target item is present . Offer sould not calculate when sources is present
                    if(isset($rule->target_item) && $item_id == $rule->target_item ){

                    }else{
                        $offer_cal_flagl = false;
                    }
                }

                ########################################
                ## calculation Individual Rule Offers ##
                $offers = $this->calculating_individual_rule_offer(['rule' => $rule , 'cart_items' => $cart_items , 'all_sources' => $all_sources , 'mrp' => $mrp, 'offer_cal_flag' => $offer_cal_flag , 'target_offer_message' => $target_offer_message, 'item_id' => $item_id, 'total_qty' => $total_qty ,'based_on' => 'amount']  );

                if($offers){   
                    $offer = $offers['offer'];
                    $available_offer[] = $offers['available_offer'];
                    if($offer){
                        $offer_arr[] = $offer;
                    }
                }
                ## calculation Individual Rule Offers ##
                #########################################

            }

            //ITEM Target Offer;
            ###### Target Offers for Item START #####################
            $cart_offers = $cart->pluck('section_target_offers');
            //dd($cart);
            $target_offer = [];
            foreach($cart_offers as $tOffer){
                if($tOffer !='' && !empty($tOffer) ){
                    $off = json_decode($tOffer);
                    $first_key = key($off);
                    $target_offer[$first_key] = $off->$first_key;
                }
            }

            //dd($target_offer);
            if(isset($target_offer[$item_id])){
                foreach($target_offer[$item_id] as $rule){
                    $all_sources = [$item_id];
                    $offer_cal_flag = true;
                    $target_offer_message = ' ';
                    ########################################
                    ## calculation Individual Rule Offers ##
                    $offers = $this->calculating_individual_rule_offer(['rule' => $rule , 'cart_items' => $cart_items , 'all_sources' => $all_sources , 'mrp' => $mrp, 'offer_cal_flag' => $offer_cal_flag , 'target_offer_message' => $target_offer_message , 'item_id' => $item_id]  );

                    $offer = $offers['offer'];
                    $available_offer[] = $offers['available_offer'];
                    if($offer){
                        $offer_arr[] = $offer;
                    }
                    ## calculation Individual Rule Offers ##
                    #########################################
                }
            }
            ###### Target Offers for Item ENDS #####################

            if(count($offer_arr) > 0 ){

                $offerCollection = collect($offer_arr);
                $amountGroup= $offerCollection->groupBy('amount');
                //dd($qtyGroup);
                //Getting only max discount offer of same amount
                $amountGroup->transform(function($item , $key){
                    $discount = $item->max('discount');
                    $item->filter(function($subItem , $subkey) use($discount){
                        return $subItem['discount'] == $discount;
                    });
                    return $item;
                });

                $pdata = [];
                $offerCollection = $amountGroup->flatten(1);
                $max_discount = $offerCollection->max('discount');
                $offer = $offerCollection->where('discount' , $max_discount)->first();
                //Getting a offer of best combination of unique qty
                $applied_offer[] = $offer['message'];
                $final_data['pdata'] = $offer['pdata'];
              
                //if offered applied make temp_qty zero if not applied then 
                $temp_qty = 0;
            }

            $section_target[$level] = $target;
            //$section['offer_arr'] = $offer_arr[];
        }
        $final_data['available_offer'] = $available_offer;
        $final_data['section_target'] = $section_target;

        return $final_data;
        //dd($final_data);
    }


	public function index($params)				
	{
		//dd($params);
		//IS this parameter required
        $item_master = $params['item_master'];
        $item_id = $params['item_master']->ITEM;
        $item_qty = $params['product_qty'];
        $mrp = $params['mrp'];
		$cart = $params['cart'];
        $price_master = $params['price_master'];
        
        //Converting collection to array then collection then filtering only specified column
        $cart_items = collect($cart->toArray())->transform(function ($item, $key) use($item_id, &$item_qty) {
            if($item['item_id'] == $item_id){//If item is exists then incrementing the current carts
                $item_qty = $item['qty'] + 1;
                return ['item_id' => $item['item_id'] , 'qty' => $item_qty , 'unit_mrp' => $item['unit_mrp']];
            }else{
               return ['item_id' => $item['item_id'] , 'qty' => $item['qty'] , 'unit_mrp' => $item['unit_mrp']]; 
            }
        });
		
        $params = ['cart_items' => $cart_items , 'item_id' => $item_id , 'mrp' => $mrp , 'item_qty' => $item_qty, 'cart' => $cart ];

        $item_val = $cart_items->where('item_id', $item_id)->first();
        if(!$item_val){
            $cart_items->push(['item_id' => $item_id , 'qty' => 1 , 'unit_mrp' => $mrp]);
        }
        
        $all_available_offer = [];
        $target_offers = [];
        //$final_data = $this->item_level_offer_calculation($params);
        
        #############################
        ## Items OFFER START ######
        $list_ru_prdvs = $this->fetching_all_offer(['item_id' => $item_id, 'level' => 'item']);
        $params['list_ru_prdvs'] = $list_ru_prdvs;
        $params['level'] = 'item';

        $final_data  = $this->calculating_group_rule($params);
        $target_offers = $final_data['target'];
        $all_available_offer = array_merge($all_available_offer , $final_data['available_offer']);
        ## Items OFFER ENDS ######
        #############################
        

        //dd($final_data);

        #############################
        ## SECTION OFFER START ######
        $section_final_data = [];
        $section_target_offers = [];
        $section_available_offer = [];

        //Subclass
        $level = 'subclass';
        $list_ru_prdvs = $this->fetching_all_offer(['item_id' => $item_master->ID_MRHRC_GP_SUBCLASS, 'level' => $level]);
        //dd($list_ru_prdvs);
        $params['list_ru_prdvs'] = $list_ru_prdvs;
        $params['level'] = $level;
        $section_final_data[$level]  = $this->calculating_group_rule($params);


        //Printclass
        $level = 'printclass';
        $list_ru_prdvs = $this->fetching_all_offer(['item_id' => $item_master->ID_MRHRC_GP_PRNT_CLASS, 'level' => $level]);
        //dd($list_ru_prdvs);
        $params['list_ru_prdvs'] = $list_ru_prdvs;
        $params['level'] = $level;
        $section_final_data[$level] = $this->calculating_group_rule($params);


        //Group
        $level = 'group';
        $list_ru_prdvs = $this->fetching_all_offer(['item_id' => $item_master->ID_MRHRC_GP_PRNT_GROUP, 'level' => $level]);
        //dd($list_ru_prdvs);
        $params['list_ru_prdvs'] = $list_ru_prdvs;
        $params['level'] = $level;
        $section_final_data[$level]  = $this->calculating_group_rule($params);
        

        //Division
        $level = 'division';
        $list_ru_prdvs = $this->fetching_all_offer(['item_id' => $item_master->ID_MRHRC_GP_PRNT_DIVISION, 'level' => $level]);
        //dd($list_ru_prdvs);
        $params['list_ru_prdvs'] = $list_ru_prdvs;
        $params['level'] = $level;
        $section_final_data[$level]  = $this->calculating_group_rule($params);
       

        //Department
        $level = 'department';
        $list_ru_prdvs = $this->fetching_all_offer(['item_id' => $item_master->ID_MRHRC_GP_PRNT_DEPT, 'level' => $level]);
        //dd($list_ru_prdvs);
        $params['list_ru_prdvs'] = $list_ru_prdvs;
        $params['level'] = $level;
        $section_final_data[$level]  = $this->calculating_group_rule($params);
        
        //dd($section_final_data);
        //Getting best discount form section discount
        $best_dis = 0;
        $sec_final_data = null;
        foreach ($section_final_data as $key => $value) {
            $all_available_offer = array_unique(array_merge($all_available_offer ,  $value['available_offer']) );
            $section_target_offers[$key] = $value['target'];
            $current_dis = 0;
            if(isset($value['pdata'])){
                foreach($value['pdata'] as $pdata){
                    $current_dis += $pdata['discount'];
                }
            }
            if($best_dis <= $current_dis ){
                $best_dis = $current_dis;
                $sec_final_data = $value;
                $sec_final_data['total_dis'] = $best_dis;
            }
        }


        //Finding best discount from item and section best discount
        if(isset($final_data['pdata']) ){
            $final_data_dis = 0;
            foreach($value['pdata'] as $pdata){
                $final_data_dis += $pdata['discount'];
            }

            if($final_data_dis < $sec_final_data['total_dis']){
                $final_data = $sec_final_data;
            }

        }else{
            if(isset($sec_final_data['pdata']) ){
                $final_data = $sec_final_data;
            }
        }
        unset($final_data['level']);
        unset($final_data['total_dis']);

        ## SECTION OFFER ENDS #######
        #############################
        
        


        ######################################
        ##### --- BILL BUSTER  START --- #####
        $level = 'bill_buster';
        $list_ru_prdvs = $this->fetching_all_offer(['item_id' => $item_id, 'level' => $level ]);
        $params['list_ru_prdvs'] = $list_ru_prdvs;
        $params['level'] = $level;

        $final_data  = $this->calculating_group_rule($params);
        $target_offers = $final_data['target'];
        $all_available_offer = array_merge($all_available_offer , $final_data['available_offer']);
    
        ##### --- BILL BUSTER  ENDS  --- #####
        ######################################
    
        
        //dd($final_data);
        //if No Offer available
        /*if($temp_qty > 0 ){
            $final_data['pdata'][] = [ 'qty' => $temp_qty, 'mrp' => $mrp, 'discount' => 0, 'ex_price' => $mrp * $temp_qty , 'total_price' => $mrp * $temp_qty, 'message' => '', 'ru_prdv' => '', 'type' => '', 'promo_id' => '', 'is_promo' => 0 ];
        }*/


        //dd($target_offer);
        //Section Offer incomplete
        //Section Target Offer incomplete
        //Bill Buster Offer incomplete
        //Employee Discount incomplete
        
        $final_data['item_id'] = $item_id;
        $final_data['unit_mrp'] = $mrp;
        $final_data['r_price'] = 0;
        $final_data['s_price'] = 0;
        $final_data['total_qty'] = $item_qty;
        $final_data['total_discount'] = 0;
        $final_data['total_tax'] = 0;
        $final_data['hsn_code'] = ''; //Need to add this also

        foreach($final_data['pdata'] as $key => $val){
            $final_data['total_discount'] += $val['discount'];
            $final_data['r_price'] += $val['total_price'];
            $final_data['s_price'] += $val['ex_price'];
        }

        #####################################
        ## Multiple MRP calculation Start ###
        $mrps = $this->check_multiple_mrp(['price_master' => $price_master]);
        ## Multiple MRP calculation Ends ####
        #####################################
        $mrp_arr = $mrps['mrp_arr'];
        $csp_arr = $mrps['csp_arr'];
        foreach($mrp_arr as $key => $mr){
            if($mr == $mrp){
                if(isset($csp_arr[$key]) && $csp_arr[$key] > 0 ){
                   // echo 'finall inside';exit;
                   $unit_csp = $csp_arr[$key];
                   //$ex_price = $csp_arr[$key] * $qty; 
                }else{
                    $unit_csp = $mrp;
                    //$ex_price = $total;
                }
            }
        }

        $final_data['multiple_price_flag'] =  ( count( $mrp_arr) > 1 )? true:false;
        $final_data['multiple_mrp'] = $mrp_arr;
        $final_data['unit_csp'] = $unit_csp;
        $final_data['available_offer'] = array_unique ($all_available_offer );
        $final_data['target'] = $target_offers;
        $final_data['section_target'] = $section_target_offers;
        $final_data['section_offer'] = [];

        dd($final_data);
        
        return $final_data;
        
	}

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

    public function find_combination($findfrom , $findfor){
        $combi_array = [];
        sort($findfrom);
        
        foreach($findfrom as $find){
            if($findfor == $find){
                $combi_array[] = [ $find ];
            }else if($findfor > $find){
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



}