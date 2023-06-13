<?php

namespace App\Http\Controllers\V1\Spar;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use DB;

use Auth;

class OfferController extends Controller
{

	public function __construct()
	{
		$this->middleware('auth');
	}

    public function remove_duplicate_offer_for_bill($offers){

        $tempOffers = $offers;
        for($i=0; $i<count($offers); $i++){
            
            for($j=$i+1; $j<count($offers); $j++){
                if( $tempOffers[$i]['message'] == $offers[$j]['message']){
                    unset($offers[$i]);
                }

            }
        }
        return  $offers;

    }

    public function get_bill_buster_offers(){

        $current_timestamp =time();
        $bill_buster_offer = [];
        $offer = [];

        $bill_buster = [ 
            'Buy$NorMoreGetZ%offTiered',
            'Buy$NorMoreGetZ$offTiered',
            'BuyRsNOrMoreGetPrintedItemFreeTiered'
        ]; 

        $ru_prdvs = DB::table('spar_uat.ru_prdv')->select('ID_RU_PRDV','SC_RU_PRDV','TY_RU_PRDV','CD_MTH_PRDV','DE_RU_PRDV','DC_RU_PRDV_EP','DC_RU_PRDV_EF','MAX_ALL_SOURCES','ITM_PRC_CTGY_SRC','MAX_FREE_ITEM','MO_TH_SRC','MAX_PARENT_ID')->whereIn('DE_RU_PRDV', $bill_buster )->get();


        foreach ($ru_prdvs as  $ru_prdv) {

            $effective_date = date_create_from_format('d-M-y H.i.s A', $ru_prdv->DC_RU_PRDV_EF );
            $effective_timestamp =  $effective_date->getTimestamp();

            $expiry_date = date_create_from_format('d-M-y H.i.s A',  $ru_prdv->DC_RU_PRDV_EP);
            $expiry_timestamp =  $expiry_date->getTimestamp();

            if( ($effective_timestamp <= $current_timestamp) &&   ($current_timestamp <= $expiry_timestamp) ){
                if($ru_prdv->MO_TH_SRC  > 0 ){
                    if($ru_prdv->DE_RU_PRDV == 'BuyRsNOrMoreGetPrintedItemFreeTiered'){

                        $matchProduct = DB::table('spar_uat.price_master')->select('ITEM','ITEM_DESC')->where('ITEM', $ru_prdv->MAX_FREE_ITEM )->first();
                        if($matchProduct){
                            $message = "On shop ".$ru_prdv->MO_TH_SRC." and above get ".$matchProduct->ITEM_DESC." as Free";
                            $offer[] = [ 'message' => $message  , 'mo_th_src' => $ru_prdv->MO_TH_SRC , 'cd_mth_prdv' => $ru_prdv->CD_MTH_PRDV, 'target_qty' => 1 , 'target_product_id' => $matchProduct->ITEM  ]; 

                        }
                      
                    }else{

                        $co_prdv_itm = DB::table('spar_uat.co_prdv_itm')->select('MO_UN_ITM_PRDV_SLS','PE_UN_ITM_PRDV_SLS','PNT_PRC_UN_ITM_PRDV_SLS')->where('ID_RU_PRDV', $ru_prdv->ID_RU_PRDV )->first();

                        $cd_mth_prdv = $ru_prdv->CD_MTH_PRDV;


                        if($cd_mth_prdv == 0 ) { //NOt in Use
                            //$second = $mrp;
                            //$final_price = $second;
                            //$saving_price = 0;
                        } else if($cd_mth_prdv == 1) {//By Percentage Off
                            $second = $co_prdv_itm->PE_UN_ITM_PRDV_SLS;
                            //$per_discount = $mrp * $second / 100;
                            //$final_price = $mrp - $per_discount;
                            //$saving_price = $per_discount;
                        }   else if($cd_mth_prdv == 2) {//By Amount OFf
                            $second = $co_prdv_itm->MO_UN_ITM_PRDV_SLS;
                            //$final_price = $mrp - $second;
                            //$saving_price = $second;
                        } else if($cd_mth_prdv == 3) {//By Fixed Price
                            $second = $co_prdv_itm->PNT_PRC_UN_ITM_PRDV_SLS;
                            //$saving_price = $mrp - $second;
                            //$final_price = $second;
                        }

                        $choose = array(
                            '0' => '0',
                            '1' => '% OFF',
                            '2' => ' Rs. OFF',
                            '3' => ' Rs.'
                        );


                        $message  = 'On shop '.$ru_prdv->MO_TH_SRC.' get '.format_number($second).''.$choose[$cd_mth_prdv] . " on Total bill";


                        $offer[] = [  'message' => $message,   'mo_th_src' => $ru_prdv->MO_TH_SRC ,'cd_mth_prdv' =>  $ru_prdv->CD_MTH_PRDV , 'offer_price' => $second ];


                    }
                }
            }
                
        }

        $offer = $this->remove_duplicate_offer_for_bill($offer);

        return $offer;

    }

    public function remove_duplicate_offers($offers){
        $tempOffers = $offers;
        for($i=0; $i<count($offers); $i++){
            
            for($j=$i+1; $j<count($offers); $j++){
                
                if( $tempOffers[$i]['message'] == $offers[$j]['message']){
                    unset($offers[$i]);
                }

            }
        }

        return  $offers;
    }

    public function get_best_offer_from_same_qty($offers , $cart=[] , $product_barcode = ''){
        //dd($offers);

        $tempOffers = $offers;
        for($i=0; $i<count($offers); $i++){
            
            for($j=$i+1; $j<count($offers); $j++){
                
                if( isset($offers[$j]) && $tempOffers[$i]['qu_th'] == $offers[$j]['qu_th']){

                    //echo '<pre>';print_r( $tempOffers[$i]);
                    //    echo '<pre>';print_r( $offers[$j]);exit;
                    if($tempOffers[$i]['saving_price'] < $offers[$j]['saving_price'] ){

                        //echo '<pre>';print_r( $tempOffers[$i]);
                        //echo '<pre>';print_r( $offers[$j]);exit;

                        if( $offers[$j]['max_all_sources'] == 'allSources' && !empty($offers[$j]['ean_list'] ) ){

                            //echo '<pre>';print_r($cart_ean_list); print_r($offer['product_item_list']);echo '</pre>';exit;
                            $cart_ean_list = $cart->pluck('barcode')->all();
                            $cart_ean_list[] = $product_barcode; 
                            $cart_ean_list  = array_unique($cart_ean_list);
                           // sort($cart_ean_list);
                            //sort($offers[$j]['ean_list']);
                            $result = (count($offers[$j]['ean_list'])==count(array_intersect($offers[$j]['ean_list'], $cart_ean_list)) );
                            if($result){ // check all sources exist
                            
                                unset($offers[$i]);
                            }else{
                                unset($offers[$j]);
                            }

                        }else{
                            //echo 'inside else';;exit;
                            unset($offers[$i]);

                        } 
                       
                    }else{
                        unset($offers[$j]);
                    }
                    
                }

            }
        }

        //dd($offers);

        return  $offers;
    }

    public function change_index_with_qty($offers){

        $tempOffers= [];
        foreach($offers as $offer){

            $offer['qu_th'];
            $tempOffers[$offer['qu_th']] = $offer;
        }

        return $tempOffers;
    }

    public function offers($params){

        $ru_prdv = $params['ru_prdv'];
        //dd($ru_prdv);

        if( $params['given_mrp'] == '' ){
            $mrp = $params['mrps'][0];
        }else{
           $mrp = $params['given_mrp'] ;
        }

        $qu_th = $params['qu_th'];
        $mo_th = $params['mo_th'];
        $id_grp = $params['ID_GRP'];
        $weight_flag = $params['weight_flag'];
        $product_list = [];
        $product_item_list = [];


        if($ru_prdv->MAX_ALL_SOURCES == 'allSources' || $ru_prdv->ITM_PRC_CTGY_SRC == 'allSources' ){
            $co_el_prdv_itm = DB::table('spar_uat.co_el_prdv_itm')->select('ID_ITM')->where('ID_RU_PRDV', $ru_prdv->ID_RU_PRDV )->get();
            $product_list = $co_el_prdv_itm->pluck('ID_ITM')->all();

            $price_master = DB::table('spar_uat.price_master')->select('ITEM','ITEM_DESC')->whereIn('ITEM', $product_list )->get();

            $product_list = $price_master->pluck('ITEM_DESC')->all();
            $product_item_list = $price_master->pluck('ITEM')->all();
        }



        if (strpos($ru_prdv->DE_RU_PRDV, 'LowestPrice') !== false || strpos($ru_prdv->DE_RU_PRDV, 'HighestPrice') !== false)
         {
                    
                
            $max_grp_itm_lst = DB::table('spar_uat.max_grp_itm_lst')->select('ID_ITM')->where('ID_GRP', $id_grp )->get();
            $product_list = $max_grp_itm_lst->pluck('ID_ITM')->all();

            $price_master = DB::table('spar_uat.price_master')->select('ITEM','ITEM_DESC')->whereIn('ITEM', $product_list )->get();

            $product_list = $price_master->pluck('ITEM_DESC')->all();
            $product_item_list = $price_master->pluck('ITEM')->all();
        }
                
                
       
       // dd($offer_params);


            $qu_lm_mxmh = 0;
            if($ru_prdv->TY_RU_PRDV == 'MM'){

                $co_prdv_itm = DB::table('spar_uat.tr_itm_mxmh_prdv')->select('MO_RDN_PRC_MXMH as MO_UN_ITM_PRDV_SLS','PE_RDN_PRC_MXMH as PE_UN_ITM_PRDV_SLS','PNT_PRC_RDN_MXMH as PNT_PRC_UN_ITM_PRDV_SLS','QU_LM_MXMH','ID_PRM_PRD')->where('ID_RU_PRDV', $ru_prdv->ID_RU_PRDV )->first();
                

                if($ru_prdv->CD_BAS_CMP_TGT == 7){

                    $grp_list = DB::table('spar_uat.max_grp_itm_lst')->select('ID_ITM')->where('ID_GRP', $co_prdv_itm->ID_PRM_PRD )->first();

                    $matchProduct = DB::table('spar_uat.price_master')->select('ITEM','ITEM_DESC')->where('ITEM', $grp_list->ID_ITM )->first();
                   
                }else{

                    $matchProduct = DB::table('spar_uat.price_master')->select('ITEM','ITEM_DESC')->where('ITEM', $co_prdv_itm->ID_PRM_PRD )->first();

                }



                $qu_lm_mxmh = $co_prdv_itm->QU_LM_MXMH;


            }else{

                //dd($ru_prdv);
                $co_prdv_itm = DB::table('spar_uat.co_prdv_itm')->select('MO_UN_ITM_PRDV_SLS','PE_UN_ITM_PRDV_SLS','PNT_PRC_UN_ITM_PRDV_SLS')->where('ID_RU_PRDV', $ru_prdv->ID_RU_PRDV)->first();

                if(!$co_prdv_itm){
                    return [];
                }
                
            
            }


            //dd($co_prdv_itm);

            if(trim($weight_flag) == 'YES'){
                $qu_th = $qu_th * 100;
            }else{
                $qu_th = (int)$qu_th;
            }

            $mrp = $mrp * $qu_th;

            if($ru_prdv->CD_MTH_PRDV == 0 ) { //NOt in Use
                $second = $mrp;
                $final_price = $second;
                $saving_price = 0;
            } else if($ru_prdv->CD_MTH_PRDV == 1) {//By Percentage Off
                $second = $co_prdv_itm->PE_UN_ITM_PRDV_SLS;
                $per_discount = $mrp * $second / 100;
                $final_price = $mrp - $per_discount;
                $saving_price = $per_discount;
            }   else if($ru_prdv->CD_MTH_PRDV == 2) {//By Amount OFf
                $second = $co_prdv_itm->MO_UN_ITM_PRDV_SLS;
                $final_price = $mrp - $second;
                $saving_price = $second;
            } else if($ru_prdv->CD_MTH_PRDV == 3) {//By Fixed Price
                $second = $co_prdv_itm->PNT_PRC_UN_ITM_PRDV_SLS;
                $saving_price = $mrp - $second;
                $final_price = $second;
            }

            $choose = array(
                '0' => '0',
                '1' => '% OFF',
                '2' => ' Rs. OFF',
                '3' => ' Rs.'
            );


            if($ru_prdv->TY_RU_PRDV == 'MM'){


                if($ru_prdv->DE_RU_PRDV == 'BuyNofXgetYatZ%off') {
                    $message  = 'Buy '.$qu_th.', get '.$qu_lm_mxmh.' '.$matchProduct->ITEM_DESC.' for '.format_number($second).' % off';
                }else if($ru_prdv->DE_RU_PRDV == 'BuyNofXgetYatZ$'){
                     $message  = 'Buy '.$qu_th.', get '.$qu_lm_mxmh.' '.$matchProduct->ITEM_DESC.' for '.format_number($second).' Rs off';

                }else if($ru_prdv->DE_RU_PRDV == 'BuyRsNOrMoreOfXGetYatZ%OffTiered') {
                    
                    $message  = 'Shop for '.$mo_th.' and above  on '.$params['section_id'].' get '.$qu_lm_mxmh.' '.$matchProduct->ITEM_DESC.' for '.format_number($second).' % off';
                }else if($ru_prdv->DE_RU_PRDV == 'Buy$NorMoreOfXgetYatZ%off') {
                   
                    $message  = 'Shop for '.$mo_th.' and above on '.$params['section_id'].' get '.$qu_lm_mxmh.' '.$matchProduct->ITEM_DESC.' for '.format_number($second).' % off';
                }else if($ru_prdv->DE_RU_PRDV == 'BuyRsNorMoreOfXgetYatZRsoff' || $ru_prdv->DE_RU_PRDV == 'Buy$NorMoreOfXgetYatZRsoff' || $ru_prdv->DE_RU_PRDV == 'BuyRsNOrMoreOfXGetYatZRsOffTiered') {
            
                    $message  = 'Shop for '.$mo_th.' and above on '.$params['section_id'].' get '.$qu_lm_mxmh.' '.$matchProduct->ITEM_DESC.' for '.format_number($second).' Rs off';
                }else if($ru_prdv->DE_RU_PRDV == 'BuyRsNOrMoreOfXGetYatZRsTiered' || $ru_prdv->DE_RU_PRDV == 'BuyRsNOrMoreOfXGetYatZRs') {
                    
                    $message  = 'Shop for '.$mo_th.' and above on '.$params['section_id'].' get '.$qu_lm_mxmh.' '.$matchProduct->ITEM_DESC.' for '.format_number($second).' Rs';
                }else if($ru_prdv->DE_RU_PRDV == 'BuyNofXgetLowestPricedXatZ%off'){
                    $message  = 'Buy '.$qu_th.' get Lowest price item at '.format_number($second).''.$choose[$ru_prdv->CD_MTH_PRDV];
                }else if($ru_prdv->DE_RU_PRDV == 'BuyNofXgetHighestPricedXatZ%off'){
                    $message  = 'Buy '.$qu_th.' get Higest price mrp at '.format_number($second).''.$choose[$ru_prdv->CD_MTH_PRDV];
                }else{
                    
                    $message  = 'Buy '.$qu_th.' get '.$qu_lm_mxmh.' '.$matchProduct->ITEM_DESC.' for '.format_number($second).''.$choose[$ru_prdv->CD_MTH_PRDV];
                    
                 }

                if($ru_prdv->ITM_PRC_CTGY_SRC == 'allSources'){
                    //$message =  'Combi offer buy '.$qu_th.' each in group '.implode($product_list,',').' for '.format_number($second).''.$choose[$ru_prdv->CD_MTH_PRDV];
                     $message =  'Buy '.$qu_th.' each,  get '.$qu_lm_mxmh.' '.$matchProduct->ITEM_DESC.' for '.format_number($second).''.$choose[$ru_prdv->CD_MTH_PRDV];
                }

            }else{

                if($ru_prdv->DE_RU_PRDV == 'BuyNOrMoreOfXGetatUnitPriceTiered'){

                    $message  = 'Buy at '.format_number($second).' Rs per Kg.';

                }else if($ru_prdv->DE_RU_PRDV == 'BuyNofXgetMofXwithLowestPriceatZ%off'){
        
                    $message  = 'Buy any  get Lowest price item at '.format_number($second).''.$choose[$ru_prdv->CD_MTH_PRDV];

                }else if($ru_prdv->DE_RU_PRDV == 'BuyNofXgetLowestPricedXatZ%off'){
        
                    $message  = 'Buy any  get Lowest price item at '.format_number($second).''.$choose[$ru_prdv->CD_MTH_PRDV];

                }else if($ru_prdv->DE_RU_PRDV == 'BuyNofXgetHighestPricedXatZ%off'){
                
                    $message  = 'Buy any  get Higest price mrp at '.format_number($second).''.$choose[$ru_prdv->CD_MTH_PRDV];

                }else{
                     $message  = 'Buy '.$qu_th.' for '.format_number($second).''.$choose[$ru_prdv->CD_MTH_PRDV];

                }


            }

            if($ru_prdv->MAX_ALL_SOURCES == 'allSources'){
                //$message =  'Combi offer buy '.$qu_th.' each in group '.implode($product_list,',').' for '.format_number($second).''.$choose[$ru_prdv->CD_MTH_PRDV];
                 $message =  'Combi offer buy '.$qu_th.' each for '.format_number($second).''.$choose[$ru_prdv->CD_MTH_PRDV];
                  $message  = 'Buy '.$qu_th.' each, get '.$qu_lm_mxmh.' '.$matchProduct->ITEM_DESC.' for '.format_number($second).' % off';
            }



            if(trim($weight_flag) == 'YES'){

            }else{
                $qu_th = (int)$qu_th;
            }

            
            if($ru_prdv->TY_RU_PRDV == 'MM'){
                //$second > 0.00 is added to remove offer of 0% dept wise offer 
                if($second > 0){
                 $offer = [ 'saving_price' =>  $saving_price , 'selling_price' => format_number($mrp) , 'message' => $message  , 'qu_th' => $qu_th , 'cd_mth_prdv' =>  $ru_prdv->CD_MTH_PRDV , 'offer_price' => $second, 'weight_flag' => trim($weight_flag), 'ty_ru_prdv' =>$ru_prdv->TY_RU_PRDV , 'max_all_sources' => $ru_prdv->MAX_ALL_SOURCES, 'itm_prc_ctgy_src' => $ru_prdv->ITM_PRC_CTGY_SRC, 'product_list' => $product_list , 'product_item_list' => $product_item_list , 'mo_th' => $mo_th , 'target_qty' => $qu_lm_mxmh , 'target_product_id' => $matchProduct->ITEM ];

                }else{
                    $offer = null;
                }

            }else{

                if($ru_prdv->CD_MTH_PRDV == 3){

                    $offer = [ 'saving_price' =>  $saving_price , 'strike_price' => format_number($mrp) , 'selling_price' => format_number($final_price) , 'message' => $message  , 'qu_th' => $qu_th , 'cd_mth_prdv' => 3, 'offer_price' => $second, 'weight_flag' => trim($weight_flag) , 'ty_ru_prdv' =>$ru_prdv->TY_RU_PRDV ,'max_all_sources' =>$ru_prdv->MAX_ALL_SOURCES, 'itm_prc_ctgy_src' => $ru_prdv->ITM_PRC_CTGY_SRC, 'product_list' => $product_list ,  'ean_list' => $product_item_list ];

                }else{

                    if($qu_th > 1 ){

                        
                        $offer = [ 'saving_price' =>  $saving_price , 'strike_price' =>  format_number($mrp) , 'selling_price' => format_number($final_price)  , 'message' => $message , 'qu_th' => $qu_th , 'cd_mth_prdv' => $ru_prdv->CD_MTH_PRDV, 'offer_price' => $second, 'weight_flag' => trim($weight_flag ), 'ty_ru_prdv' =>$ru_prdv->TY_RU_PRDV ,'max_all_sources' =>$ru_prdv->MAX_ALL_SOURCES, 'itm_prc_ctgy_src' => $ru_prdv->ITM_PRC_CTGY_SRC, 'product_list' => $product_list , 'product_item_list' => $product_item_list ];
                        
                    }else{

                        $offer = [ 'saving_price' =>  $saving_price , 'strike_price' => format_number($mrp) , 'selling_price' => format_number($final_price) ,'message' => $message , 'qu_th' => $qu_th , 'cd_mth_prdv' => $ru_prdv->CD_MTH_PRDV, 'offer_price' => $second , 'weight_flag' => trim($weight_flag) , 'ty_ru_prdv' =>$ru_prdv->TY_RU_PRDV , 'max_all_sources' =>$ru_prdv->MAX_ALL_SOURCES, 'itm_prc_ctgy_src' => $ru_prdv->ITM_PRC_CTGY_SRC, 'product_list' => $product_list, 'product_item_list' => $product_item_list ];

                    }

                }

                if (strpos($ru_prdv->DE_RU_PRDV, 'LowestPrice') !== false){
                    $offer['lowest_higest'] = 'lowest'; 
                }

                if (strpos($ru_prdv->DE_RU_PRDV, 'HighestPrice') !== false){
                    $offer['lowest_higest'] = 'higest'; 
                }

            
            }

            //dd($offer);

            return $offer;


    }
    
    public function section_wise_offers($data){

        $current_timestamp = time();

        $ru_prdv = DB::table('spar_uat.ru_prdv')->select('ID_RU_PRDV','SC_RU_PRDV','TY_RU_PRDV','CD_MTH_PRDV','DE_RU_PRDV','DC_RU_PRDV_EF','DC_RU_PRDV_EP', 'MAX_ALL_SOURCES','ITM_PRC_CTGY_SRC','MAX_FREE_ITEM','CD_BAS_CMP_SRC','CD_BAS_CMP_TGT')->where('ID_RU_PRDV', $data['ID_RU_PRDV'])->first();

        $effective_date = date_create_from_format('d-M-y H.i.s A', $ru_prdv->DC_RU_PRDV_EF );
        $effective_timestamp =  $effective_date->getTimestamp();

        $expiry_date = date_create_from_format('d-M-y H.i.s A',  $ru_prdv->DC_RU_PRDV_EP);
        $expiry_timestamp =  $expiry_date->getTimestamp();

        if( ($effective_timestamp <= $current_timestamp) &&   ($current_timestamp <= $expiry_timestamp) ){

            if($data['offer_from'] == 'item') {  
                if($data['QU_TH'] > 0.00){

                    $params = [ 'ru_prdv' => $ru_prdv ,
                             'qu_th' =>  $data['QU_TH'] , 
                             'mo_th'  => $data['MO_TH'],
                             'ID_GRP' => $data['ID_GRP'],
                             'mrps' => $data['mrps'] , 
                             'csps' => $data['csps'], 
                             'given_mrp' => $data['given_mrp'] ,
                             'weight_flag' => $data['weight_flag'],
                             'offer_for' => 'item'
                         ] ;

                    return  $this->offers($params);

                }
            }else{


                $params = [ 'ru_prdv' => $ru_prdv ,
                         'qu_th' =>  $data['QU_TH'] , 
                         'mo_th'  => $data['MO_TH'],
                         'ID_GRP' => $data['ID_GRP'],
                         'mrps' => $data['mrps'] , 
                         'csps' => $data['csps'], 
                         'given_mrp' => $data['given_mrp'] ,
                         'weight_flag' => $data['weight_flag'],
                         'offer_for' => 'section',
                         'section_name' => $data['offer_from'],
                         'section_id' => $data['section_id']

                     ] ;

                return  $this->offers($params);



            }

            
        }

    
    }


    public function get_offers($offer_params){

        $current_timestamp = time();

        //$item_master =  $offer_params['item_master'];
        //$price_master =  $offer_params['price_master'];
        $carts =  $offer_params['cart'];
        $global_offer=[];
        $target_offer=[];
        $higest_lowest=[];
        //dd($carts);
        foreach ($carts as $key => $cart) {
            $item_master = DB::table('spar_uat.item_master')->where('EAN', $cart->barcode)->first();
            $price_master = DB::table('spar_uat.price_master')->where('ITEM', $item_master->ITEM)->first();

            $res = DB::table('cart_offers')->where('cart_id',$cart->cart_id)->first();

            if($res){
                $response[$cart->barcode] = json_decode($res->offers , true);
            }else{

                $response[$cart->barcode] = $this->fetch_individual_offers( [ 
                    'item_master'=>$item_master ,
                    'price_master' => $price_master,
                    'cart' => $carts,
                    'mrp' => $cart['per_unit_mrp'],
                    'product_barcode' => $cart['barcode'],
                    'product_qty' => $cart['qty'],
                    'global_offer' => $global_offer,
                    'target_offer' => $target_offer,
                    'higest_lowest' => $higest_lowest,
                    'without_cart' => false
                    
                    ] 
                );

                //dd($response);

            }

            $target_offer = $response[$cart->barcode]['target_offer'];
            $higest_lowest = $response[$cart->barcode]['higest_lowest'];
            $global_offer = $response[$cart->barcode]['global_offer'];

        }
        //dd($higest_lowest);
        //dd($response);

        $response[$offer_params['product_barcode']] = $this->fetch_individual_offers_without_cart( [ 
        //$response[$offer_params['product_barcode']] = $this->fetch_individual_offers( [ 
                'item_master'=>$offer_params['item_master'] ,
                'price_master' =>$offer_params['price_master'] ,
                'cart' => $carts,
                'mrp' => $offer_params['mrp'],
                'product_barcode' => $offer_params['product_barcode'],
                'product_qty' => $offer_params['product_qty'],
                'global_offer' => $global_offer,
                'target_offer' => $target_offer,
                'higest_lowest' => $higest_lowest,
                'without_cart' => true
                
                ] 
            );


        //dd($response);
        $global_available_offer = [] ;
        $global_applied_offer = [];
        $global_offer = $response[$offer_params['product_barcode']]['global_offer'];
        $global_target_offer=[];
        foreach ($global_offer as $section) {
            foreach ($section as $data) {
                $g_total_amount = $data['total_amount'];
                foreach($data['offer'] as $offer){
                    $global_available_offer[] = [ 'message' => $offer['message'] ];
                    //$global_target_offer[$offer['target_product_id']][] = $offer; 

                    if($g_total_amount >= $offer['mo_th']){
                        if($offer['ty_ru_prdv'] == 'MM' && isset($offer['target_product_id']) ){

                            $cart_ean_list = $carts->pluck('barcode')->all();
                            $cart_ean_list[] = $offer_params['product_barcode'];
                            $cart_ean_list = array_unique($cart_ean_list);
                            //dd($cart_ean_list);
                            $arr = [$offer['target_product_id']];
                            if(!empty(array_intersect( $arr, $cart_ean_list))){
                                //$global_applied_offer[] = $offer;

                                $cd_mth_prdv = $offer['cd_mth_prdv'];
                                $mrp = (float)$offer_params['mrp'];

                                if($cd_mth_prdv == 0 ) { //NOt in Use
                                    $second = $mrp;
                                    $final_price = $second;
                                    $saving_price = 0;
                                } else if($cd_mth_prdv == 1) {//By Percentage Off
                                    $second = $offer['offer_price'];
                                    $per_discount = $mrp * $second / 100;
                                    $final_price = $mrp - $per_discount;
                                    $saving_price = $per_discount;
                                }   else if($cd_mth_prdv == 2) {//By Amount OFf
                                    $second = $offer['offer_price'];
                                    $final_price = $mrp - $second;
                                    $saving_price = $second;
                                } else if($cd_mth_prdv == 3) {//By Fixed Price
                                    $second = (float)$offer['offer_price'];
                                    $saving_price = $mrp - $second;
                                    $final_price = $second;
                                }

                                $choose = array(
                                    '0' => '0',
                                    '1' => '% OFF',
                                    '2' => ' Rs. OFF',
                                    '3' => ' Rs.'
                                );

                                $final_offer = [ 'saving_price' =>  $saving_price ,
                                    'strike_price' =>  format_number($mrp) ,
                                    'selling_price' => format_number($final_price)  ,
                                    'message' => $offer['message'],
                                    'qu_th' => $offer['target_qty'] ,
                                    'cd_mth_prdv' => $offer['cd_mth_prdv'],
                                    'offer_price' => $second,
                                    //'weight_flag' => ,
                                    'ty_ru_prdv' => $offer['ty_ru_prdv'],
                                    'max_all_sources' => $offer['max_all_sources'],
                                    'product_list' => $offer['product_list'],
                                    'product_item_list' => $offer['product_item_list'] 
                                ] ;

                                $response[$offer_params['product_barcode']]['r_price'] = $saving_price;
                                $response[$offer_params['product_barcode']]['s_price'] = format_number($mrp);
                                $response[$offer_params['product_barcode']]['applied_offers'][] = [ 'message' => $offer['message'] , 'product_list' => $offer['product_list']  ];
                                
                            }
                            
                        }
                        
                    }
                   

                }
            }
            
        }

       

        //dd($global_applied_offer);

        //BILL BUSTER OFERS
        $total_r_price = 0;
        $total_s_price = 0;
        foreach ($response as $key => $value) {
            $total_r_price += $value['r_price'];
            $total_s_price += $value['s_price'];
        }

        $bill_applied_offer=[];
        $bill_available_offer=[];
        $bill_available_offer_for_applying=[];
        $bill_buster_offers= $this->get_bill_buster_offers();

        //echo $total_r_price;exit;
        //dd($bill_buster_offers);
        //dd($)
        foreach($bill_buster_offers as $offer){

            if($total_s_price >= $offer['mo_th_src'] ){
               
                if(isset($offer['target_product_id'])){

                    $cart_ean_list = $cart->pluck('barcode')->all();
                    $cart_ean_list[] = $offer_params['product_barcode']; 
                    $cart_ean_list  = array_unique($cart_ean_list);
                    //dd($offer);
                    //Need to get ean code from item master
                    $res = [ $offer['target_product_id'] ];
                    
                    if(!empty(array_intersect($res, $cart_ean_list))){
                        $prices= DB::table('spar_uat.price_master')->where('ITEM', $offer['target_product_id'])->first();
                        
                        $mrp = $total_s_price;
                        $final_price = $total_s_price -  (float)$prices->MRP1;
                        $saving_price = $price_master->MRP1;

                        $offer = [ 'saving_price' =>  $saving_price   , 'strike_price' => format_number($mrp) , 'selling_price' => format_number($final_price) , 'message' => $offer['message']  , 'cd_mth_prdv' => 2, 'offer_price' => $prices->MRP1 ];

                        $bill_available_offer_for_applying[] = $offer;

                    }
                    

                }else{

                    $cd_mth_prdv = $offer['cd_mth_prdv'];
                    $mrp =  $total_s_price;
                    if($cd_mth_prdv == 0 ) { //NOt in Use
                        $second = $mrp;
                        $final_price = $second;
                        $saving_price = 0;
                    } else if($cd_mth_prdv == 1) {//By Percentage Off
                        $second = $offer['offer_price'];
                        $per_discount = $mrp * $second / 100;
                        $final_price = $mrp - $per_discount;
                        $saving_price = $per_discount;
                    }   else if($cd_mth_prdv == 2) {//By Amount OFf
                        $second = $offer['offer_price'];
                        $final_price = $mrp - $second;
                        $saving_price = $second;
                    } else if($cd_mth_prdv == 3) {//By Fixed Price
                        $second = $offer['offer_price'];
                        $saving_price = $mrp - $second;
                        $final_price = $second;
                    }


                    $offer = [ 'saving_price' =>  $saving_price , 'strike_price' => format_number($mrp) , 'selling_price' => format_number($final_price) , 'message' => $offer['message']  , 'cd_mth_prdv' => $offer['cd_mth_prdv'], 'offer_price' => $second ];

                    $bill_available_offer_for_applying[] = $offer;
                }
            }
            //dd($offer);
            
        }

       //dd($bill_available_offer_for_applying);

        //GETTing best offer from bill buster
        if(!empty($bill_available_offer_for_applying)){
            foreach($bill_available_offer_for_applying as $key => $offer ){

                if(empty($bill_applied_offer)){

                    $bill_applied_offer = $offer;
                }else{

                    if($bill_applied_offer['saving_price'] < $offer['saving_price']){
                        $bill_applied_offer = $offer;
                    }

                }
                
            }

        }
        //
        $bill_applied_offers=[];
        if(!empty($bill_applied_offer)){
            $bill_applied_offers[] = [ 'message' =>  $bill_applied_offer['message'] ];
        }




        //dd($bill_buster_offers);

        //dd($global_available_offer);

        //foreach($global_offer as )
         
       //dd($applied_offers_arr);
        $barcode_response = $response[$offer_params['product_barcode']];
        
        $response[$offer_params['product_barcode']]['applied_offers'] = array_merge($barcode_response['applied_offers'], $global_applied_offer);
        $response[$offer_params['product_barcode']]['available_offers'] = array_merge($barcode_response['available_offers'], $global_available_offer);
       // dd($barcode_response['available_offers']);
        $response[$offer_params['product_barcode']]['applied_offers'] = array_merge($barcode_response['applied_offers'], $bill_applied_offers);
        //$response[$offer_params['product_barcode']]['available_offers'] = array_merge($barcode_response['available_offers'], $bill_available_offer);

        //dd($barcode_response);
        //dd($response);
        $final_data = $response[$offer_params['product_barcode']];
        //dd($final_data);

        return $final_data;


    }

    public function fetch_individual_offers($offer_params){

        //dd($offer_params);
        $item_master =  $offer_params['item_master'];
        $price_master =  $offer_params['price_master'];
        $target_offer = $offer_params['target_offer'];
        $higest_lowest = $offer_params['higest_lowest'];
        $global_offer = $offer_params['global_offer'];

        $cart =  $offer_params['cart'];

        $mrp_arr = array_filter( [ format_number($price_master->MRP1), format_number($price_master->MRP2) , format_number($price_master->MRP3) ]  );
        $csp_arr = array_filter( [ format_number($price_master->CSP1), format_number($price_master->CSP2) , format_number($price_master->CSP3) ]  );


        $offer_response = [];
        $offer = [];
        $dept_offer = [];
        $item_offer = [];
        $subclass_offer = [];
        $printclass_offer= [];
        $group_offer =[];
        $division_offer = [];


        $group_arr = [  'item' =>  $item_master->ITEM];

        if(!isset($global_offer['department'][$item_master->ID_MRHRC_GP_PRNT_DEPT] )){
             $group_arr['department'] = $item_master->ID_MRHRC_GP_PRNT_DEPT;
        }

        if(!isset($global_offer['subclass'][$item_master->ID_MRHRC_GP_SUBCLASS] )){
             $group_arr['subclass'] = $item_master->ID_MRHRC_GP_SUBCLASS;
        }


        if(!isset($global_offer['group'][$item_master->ID_MRHRC_GP_PRNT_GROUP] )){
             $group_arr['group'] = $item_master->ID_MRHRC_GP_PRNT_GROUP;
        }

        if(!isset($global_offer['division'][$item_master->ID_MRHRC_GP_PRNT_DIVISION] )){
             $group_arr['division'] = $item_master->ID_MRHRC_GP_PRNT_DIVISION;
        }


        foreach ($group_arr as $maxkey => $val){

            //MAX GROUP
            $max_grp_itm_lst = DB::table('spar_uat.max_grp_itm_lst')->select('ID_GRP')->where('ID_ITM', $val )->get();
            //Getting Array of id_grp column
            $group_ids =  $max_grp_itm_lst->pluck('ID_GRP')->all();

            $max_co_el_prdv_itm_grps = DB::table('spar_uat.max_co_el_prdv_itm_grp')->select('ID_GRP','ID_RU_PRDV','ITEM_GRP_TYPE','QU_TH','MO_TH')->whereIn('ID_GRP', $group_ids)->get();
            //dd($max_co_el_prdv_itm_grps);

            foreach ($max_co_el_prdv_itm_grps as $key => $max_co_el_prdv_itm_grp) {

                $offer_res =  $this->section_wise_offers( [
                    'ID_RU_PRDV' => $max_co_el_prdv_itm_grp->ID_RU_PRDV ,
                    'QU_TH' => $max_co_el_prdv_itm_grp->QU_TH ,
                    'MO_TH' => $max_co_el_prdv_itm_grp->MO_TH , 
                    'ID_GRP' => $max_co_el_prdv_itm_grp->ID_GRP , 
                    'mrps' => $mrp_arr , 
                    'csps' => $csp_arr , 
                    'given_mrp' => $offer_params['mrp'] ,
                    'weight_flag' => $price_master->WEIGHT_FLAG,
                    'offer_from' => $key,
                    'section_id' => $val
                ]);

                if($offer_res){

                    if($maxkey =='item'){
                        $item_offer[] = $offer_res;
                    }elseif($maxkey =='department'){
                        $dept_offer[$val]['offer'][] = $offer_res;
                    }else if($maxkey =='subclass'){
                        $subclasss_offer[$val]['offer'][] = $offer_res;
                    }else if($maxkey =='group'){
                        $group_offer[$val][] = $offer_res;
                    }else if($maxkey =='division'){
                        $division_offer[$val]['offer'][] = $offer_res;
                    }

                }

            }


        }


        //Col Prd ITem
        $co_el_prdv_itms = DB::table('spar_uat.co_el_prdv_itm')->select('ID_ITM','ID_RU_PRDV','ID_STR_RT','QU_TH','MO_TH')->where('ID_ITM', $item_master->ITEM)->get();

        foreach ($co_el_prdv_itms as $key => $co_el_prdv_itm) {

            $offer_res =  $this->section_wise_offers( [
                'ID_RU_PRDV' => $co_el_prdv_itm->ID_RU_PRDV ,
                'QU_TH' => $co_el_prdv_itm->QU_TH ,
                'MO_TH' => $co_el_prdv_itm->MO_TH , 
                'ID_GRP' => '', 
                'mrps' => $mrp_arr , 
                'csps' => $csp_arr , 
                'given_mrp' => $offer_params['mrp'] ,
                'weight_flag' => $price_master->WEIGHT_FLAG,
                'offer_from' => 'item',
                'section_id' => ''
            ]);

           if($offer_res){
            $item_offer[] = $offer_res;
           }

        }

        //dd($global_offer);
        //$offer['multiple_price_flag'] =  $price_master->MULTIPLE_MRP_FLAG;
        $data['mrp'] =  $mrp_arr;
        $data['csp'] =  $csp_arr;
        $offer['item'] =  $item_offer;
        
        if(!empty($dept_offer) ){
            foreach ($dept_offer as $key => $value) break;
            $global_offer['department'][$key] =  $dept_offer[$key];
        }
        if(!empty($subclass_offer) ){
            foreach ($subclass_offer as $key => $value) break;
            $global_offer['subclass'][$key] =  $subclass_offer[$key];
        }
        if(!empty($printclass_offer) ){
            foreach ($printclass_offer as $key => $value) break;
            $global_offer['printclass'][$key] =  $dept_key[$key];
        }
        if(!empty($group_offer) ){
            foreach ($group_offer as $key => $value) break;
            $global_offer['group'][$key] =  $group_offer[$key];
        }
        if(!empty($division_offer) ){
            foreach ($division_offer as $key => $value) break;
            $global_offer['division'][$key] =  $division_offer[$key];
        }

        //dd($global_offer);
        $data['offers'] = $offer;
        
        foreach( $data['offers'] as $key => $offer){
           $data['offers'][$key] = $this->remove_duplicate_offers($offer);
        }
        
        //dd($data);
        $cart_ean_list = $cart->pluck('barcode')->all();
        $cart_ean_list[] = $offer_params['product_barcode']; 
        $cart_ean_list  = array_unique($cart_ean_list);
        //dd($cart_ean_list);
    
        $final_data = [];
        $applied_offers_arr = [];
        $available_offers_arr = [];
        $available_offers_for_applying=[];
        $available_offers_for_display=[];

        $qu_th = $offer_params['product_qty'];

        //############# HIGEST LOWEST OFFER APPLYING START #############


        //############# HIGEST LOWEST OFFER APPLYING END #############

        //############# TARGET OFFER APPLYING START #############
        //This condition is added for applying target offer
        //dd($higest_lowest);
       // dd($offer_params['mrp']);
        //dd($global_offer[]);
        /*foreach($global_offer['department'] as $key => $offer){
            if($offer['ty_ru_prdv'] == 'MM'){
                $target_offer[$offer['target_product_id']][] = $offer;    
            }
        }*/
        //echo $qu_th.'::'.$offer_params['mrp'];exit;
        //dd($data);
        //dd($higest_lowest);
        foreach($data['offers']['item'] as $key => $offer){
            if($offer['ty_ru_prdv'] == 'MM'){
                $target_offer[$offer['target_product_id']][] = $offer;    
            }
            
            if(isset($offer['lowest_higest'])){
                if($offer['lowest_higest']=='higest'){

                    if($offer['cd_mth_prdv'] ==1 && $offer['offer_price'] =='100.00'){
                        //if($firstElem['qu_th'] ==  $qu_th){
                        //dd($offer);
                            $data['offers']['item'][$key]['saving_price'] = $offer_params['mrp'] ;
                            $data['offers']['item'][$key]['strike_price'] = ($qu_th * $offer_params['mrp']);
                            $data['offers']['item'][$key]['selling_price'] = ($qu_th * $offer_params['mrp']) -$offer_params['mrp'] ;
                            $data['offers']['item'][$key]['cd_mth_prdv'] = 2;
                            $data['offers']['item'][$key]['offer_price'] = $offer_params['mrp'];


                            $higest_lowest_applied_off_flag = true;
                            //echo $offer_params['mrp']." ::".$qu_th;exit;
                            //dd($data);
                            
                       // }

                    }
                    //dd($data);

                    if(!empty($higest_lowest['higest']) ){
                        //dd($cart);
                        $firstElem = reset($higest_lowest['higest']);
                        $higest_lowest_applied_off_flag = false; 
                        /*else{*/
                        $higestMrp = $cart->whereIn('barcode', $offer['product_item_list'])->max('per_unit_mrp');
                        $higestItem = $cart->whereIn('barcode', $offer['product_item_list'])->where('per_unit_mrp', $higestMrp)->first();
                        if($higestItem){
                            if($higestItem->barcode == $offer_params['product_barcode']){

                                unset($higest_lowest['higest']);
                                $higest_lowest_applied_off_flag = true;
                                $higest_lowest['higest'][$item_master->EAN] = $offer; 
                            }
                        }

                     //   }
                       
                    }else{
                        //dd($offer);
                        $higest_lowest_applied_off_flag = false; 
                        $higestMrp = $cart->whereIn('barcode', $offer['product_item_list'])->max('per_unit_mrp');
                        $higestItem = $cart->whereIn('barcode', $offer['product_item_list'])->where('per_unit_mrp', $higestMrp)->first();
                        if($higestItem){
                            if($higestItem->barcode == $offer_params['product_barcode']){
 
                                unset($higest_lowest['higest']);
                                $higest_lowest_applied_off_flag = true;
                                $higest_lowest['higest'][$item_master->EAN] = $offer; 
                            }

                        }
                       
                    }
                    
                }

                if($offer['lowest_higest']=='lowest'){
                    if(!empty($higest_lowest['lowest']) ){
                        $firstElem = reset($higest_lowest['lowest']);
                        if($firstElem['cd_mth_prdv'] ==1 && $firstElem['offer_price'] =='100.00'){
                            //if($firstElem['qu_th'] ==  $qu_th){
                            //dd($offer);
                                $data['offers']['item'][$key]['saving_price'] = $offer_params['mrp'] ;
                                $data['offers']['item'][$key]['strike_price'] = ($qu_th * $offer_params['mrp']);
                                $data['offers']['item'][$key]['selling_price'] = ($qu_th * $offer_params['mrp']) -$offer_params['mrp'] ;
                                $data['offers']['item'][$key]['cd_mth_prdv'] = 2;
                                $data['offers']['item'][$key]['offer_price'] = $offer_params['mrp'];


                                $higest_lowest_applied_off_flag = true;
                                //echo $offer_params['mrp']." ::".$qu_th;exit;
                               // dd($data);
                                
                           // }
                        }

                        $lowestMrp = $cart->whereIn('barcode', $offer['product_item_list'])->min('per_unit_mrp');
                        $lowestItem = $cart->whereIn('barcode', $offer['product_item_list'])->where('per_unit_mrp', $lowestMrp)->first();
                        /*foreach($lowestItem as $collec){
                            dd( $collec);
                        }*/
                        //$lowestItem = $lowestItem->each();
                        //dd($lowestItem);
                        $higest_lowest_applied_off_flag = false; 
                        if($lowestItem){
                            if($lowestItem->barcode == $offer_params['product_barcode']){

                                unset($higest_lowest['lowest']);
                                $higest_lowest_applied_off_flag = true;
                                $higest_lowest['lowest'][$item_master->EAN] = $offer; 
                            }
                        }




                        /*if($firstElem['strike_price'] <  $offer['strike_price']){
                            $higest_lowest_applied_off_flag = false;
                        }else if($firstElem['strike_price'] ==  $offer['strike_price']){
                             unset($higest_lowest['lowest']);
                            $higest_lowest_applied_off_flag = true;
                            $higest_lowest['lowest'][$item_master->EAN] = $offer;
                        }else{
                             
                        }*/
                    }else{
                        $lowestMrp = $cart->whereIn('barcode', $offer['product_item_list'])->min('per_unit_mrp');
                        $lowestItem = $cart->whereIn('barcode', $offer['product_item_list'])->where('per_unit_mrp', $lowestMrp)->first();
                    
                                            
                        $higest_lowest_applied_off_flag = false; 
                        if($lowestItem){
                            if($lowestItem->barcode == $offer_params['product_barcode']){

                                unset($higest_lowest['lowest']);
                                $higest_lowest_applied_off_flag = true;
                                $higest_lowest['lowest'][$item_master->EAN] = $offer; 
                            }
                        }
                    }
                    //dd($firstElem);
                    
                }
                
                $higest_lowest_applied_off_flag;
           }
        }

        //dd($data['offers']['item']);
        //dd($higest_lowest);
        //dd($target_offer);

        $target = false;
        if(isset($target_offer[$item_master->ITEM])){
            $target = true;

            foreach($target_offer[$item_master->ITEM] as $tOffer){

                
                $target_offer[$item_master->ITEM];

                $cd_mth_prdv = $tOffer['cd_mth_prdv'];
                $mrp = (float)$offer_params['mrp'];

                if($cd_mth_prdv == 0 ) { //NOt in Use
                    $second = $mrp;
                    $final_price = $second;
                    $saving_price = 0;
                } else if($cd_mth_prdv == 1) {//By Percentage Off
                    $second = $tOffer['offer_price'];
                    $per_discount = $mrp * $second / 100;
                    $final_price = $mrp - $per_discount;
                    $saving_price = $per_discount;
                }   else if($cd_mth_prdv == 2) {//By Amount OFf
                    $second = $tOffer['offer_price'];
                    $final_price = $mrp - $second;
                    $saving_price = $second;
                } else if($cd_mth_prdv == 3) {//By Fixed Price
                    $second = (float)$tOffer['offer_price'];
                    $saving_price = $mrp - $second;
                    $final_price = $second;
                }

                $choose = array(
                    '0' => '0',
                    '1' => '% OFF',
                    '2' => ' Rs. OFF',
                    '3' => ' Rs.'
                );


                //$message  = 'On shop '.$ru_prdv->MO_TH_SRC.' get '.format_number($second).''.$choose[$cd_mth_prdv] . " on Total bill"

                $offer = [ 'saving_price' =>  $saving_price ,
                            'strike_price' =>  format_number($mrp) ,
                            'selling_price' => format_number($final_price)  ,
                            'message' => $tOffer['message'],
                            'qu_th' => $tOffer['target_qty'] ,
                            'cd_mth_prdv' => $tOffer['cd_mth_prdv'],
                            'offer_price' => $second,
                            //'weight_flag' => ,
                            'ty_ru_prdv' => $tOffer['ty_ru_prdv'],
                            'max_all_sources' => $tOffer['max_all_sources'],
                            'itm_prc_ctgy_src' => $tOffer['itm_prc_ctgy_src'],
                            'product_list' => $tOffer['product_list'],
                            'product_item_list' => $tOffer['product_item_list'] 
                        ] ;

                $data['offers']['item'][] = $offer;
            }
            //dd($target_offer[$item_master->ITEM]);
        }
        //############# TARGET OFFER APPLYING END #############

        //dd($data);
        //dd($applied_offers_arr);
        //echo 'quty:'.$qu_th;exit;
        foreach($data['offers']['item'] as $offer){
            $available_offers_for_display[] = ['message' =>  $offer['message'] , 'product_list' => $offer['product_list'] ];
            $available_offers_for_applying[] = $offer;

            if(empty($applied_offers_arr) ){
                
                if(isset($offer['lowest_higest'])){
                   // $total_qu_th = 0;
                    //echo 'iside here';exit;
                    if(!empty(array_intersect($offer['product_item_list'], $cart_ean_list))){
                        //echo 'inside this';
                        if($higest_lowest_applied_off_flag){
                            $total_qu_th = $cart->whereIn('barcode', $offer['product_item_list'])->sum('qty');
                            $barcode_qty = $cart->where('barcode', $offer_params['product_barcode'])->sum('qty');
                            if($offer['qu_th'] ==1){
                                $applied_offers_arr = $offer;
     
                            }else{


                                if(isset($offer_params['without_cart']) && $offer_params['without_cart'] == true){
                                    $barcode_qty =1;
                                }else{
                                    $barcode_qty =0;
                                }
                                //echo 'iside here'.$total_qu_th.'::'.$barcode_qty;exit;
                                $total_qu_th += $barcode_qty;
                
                                if($total_qu_th == $offer['qu_th']){
                                    //echo ' inside this';exit();
                                    $applied_offers_arr = $offer;
                                } 
                            }
                        }
                    } 

                }else{

                    if($qu_th == $offer['qu_th']){
                        //echo 'inside this';exit;
                        if( ($offer['max_all_sources'] == 'allSources' ) && !empty($offer['product_list'] ) ){
                            //echo '<pre>';print_r($cart_ean_list); print_r($offer['product_item_list']);echo '</pre>';exit;
                           $result = (count($offer['product_item_list'])==count(array_intersect($offer['product_item_list'], $cart_ean_list)) );
                            //sort($cart_ean_list);
                           // sort($offer['product_item_list']);
                            if($result){ // check all sources exist
                                //echo 'inside this else';exit;
                                $applied_offers_arr = $offer;
                            }
                        }else if(isset($offer['lowest_higest']) && $higest_lowest_applied_off_flag === false){
                            //echo 'inside lowest higet false';exit;

                        }else if($offer['ty_ru_prdv'] == 'MM'){

                            if($target === false){

                            }else{

                                if($offer['itm_prc_ctgy_src'] == 'allSources'){

                                    $result = (count($offer['product_item_list'])==count(array_intersect($offer['product_item_list'], $cart_ean_list)) );
                                    //sort($cart_ean_list);
                                    // sort($offer['product_item_list']);
                                    if($result){ // check all sources exist
                                        //echo 'inside this else';exit;
                                        $applied_offers_arr = $offer;
                                    }

                                }

                            }
                            

                        }else{
        
                            $applied_offers_arr = $offer;

                        }                      
                       
                    }

                }
                
               
            }else{

                if($applied_offers_arr['qu_th'] == $offer['qu_th']){

                    if($applied_offers_arr['saving_price'] < $offer['saving_price']){

                        if( ($offer['max_all_sources'] == 'allSources' || $offer['itm_prc_ctgy_src'] == 'allSources' )&& !empty($offer['product_list'] ) ){
                           
                           //echo '<pre>';print_r($cart_ean_list); print_r($product_item_list);echo '</pre>';exit;
                            $result = (count($offer['product_item_list'])==count(array_intersect($offer['product_item_list'], $cart_ean_list)) );
                            //sort($cart_ean_list);
                           // sort($offer['product_item_list']);
                            if($result){ // check all sources exist
                                //echo 'inside this else';exit;
                                $applied_offers_arr = $offer;
                            }

                        }else{

                          $applied_offers_arr = $offer;
                        }
                    }

                }

            }

           
        }



        /*foreach($data['offers']['department'][$group_arr['department']] as $offer){
            $available_offers_for_display[] = ['message' =>  $offer['message'] , 'product_list' => $offer['product_list'] ];
            $available_offers_for_applying[] = $offer;

        }*/


        //dd($applied_offers_arr);
        $final_data['multiple_price_flag'] =  ( count( $data['mrp']) > 1 )? true:false;
        $final_data['multiple_mrp'] = $data['mrp'];
        if( !empty($applied_offers_arr) ){
            $final_data['r_price'] = $applied_offers_arr['strike_price'];
            $final_data['s_price'] = $applied_offers_arr['selling_price'];
            $final_data['applied_offers'][] = [ 'message' => $applied_offers_arr['message'] , 'product_list' => $applied_offers_arr['product_list']  ];

        }else{
            $final_data['applied_offers'] = [];
        }

        $final_data['available_offers'] =  $available_offers_for_display;
        //dd($available_offers_for_applying);
        if(!empty($data['offers']['item']) ){
            if(empty($applied_offers_arr) ){
                $available_offers_for_applying = array_values(  $this->get_best_offer_from_same_qty($available_offers_for_applying , $cart, $offer_params['product_barcode']) );  
                $available_offers_for_applying = $this->get_best_offer_from_same_qty($available_offers_for_applying , $cart, $offer_params['product_barcode'] ) ;
                
                $available_offers_for_applying = $this->change_index_with_qty($available_offers_for_applying);
                
                //dd($available_offers_for_applying);
                  
                $i=$qu_th -1;
                $remaining_qty = 1;
                //$j=1;
                while($i>0){
                    //$remaining_qty =$i - 1;
                   // echo $i.' '.$remaining_qty;

                    if(isset($available_offers_for_applying[$i])){
                        
                        $applied_offers_arr[] = [ 
                            'saving_price' =>  $available_offers_for_applying[$i]['saving_price'] ,
                            'strike_price' =>  (isset($available_offers_for_applying[$i]['strike_price']))?$available_offers_for_applying[$i]['strike_price']:0.00 ,
                            'selling_price' => $available_offers_for_applying[$i]['selling_price'] ,
                            'message' => $available_offers_for_applying[$i]['message']  ,
                            'qu_th' =>  $available_offers_for_applying[$i]['qu_th'],
                            'item' =>  1 
                        ];

                        $i = $remaining_qty;
                        $remaining_qty = 0;

                    }else{

                        $i--;
                        $remaining_qty++;

                    }

                    if($i == 0 ){
                       break;
                    }
                }



                //dd($applied_offers_arr);
                $temp_offer=[];
                $item = 0;

                /*$temp_offer = $applied_offers_arr;
                for($i=0; $i<count($applied_offers_arr); $i++){
                    
                    for($j=$i+1; $j<count($applied_offers_arr); $j++){
                        
                        if( $temp_offer[$i]['qu_th'] == $applied_offers_arr[$j]['qu_th']){

                            $applied_offers_arr[$j]['saving_price'] += $temp_offer[$i]['saving_price'];
                            $applied_offers_arr[$j]['strike_price'] += $temp_offer[$i]['strike_price'];
                            $applied_offers_arr[$j]['selling_price'] += $temp_offer[$i]['selling_price'];
                            $applied_offers_arr[$j]['item'] += $temp_offer[$i]['item'];
                            $applied_offers_arr[$j]['message'] = $temp_offer[$i]['message'].' applied '.$applied_offers_arr[$j]['item']. ' times';                            
                            unset($applied_offers_arr[$i]);
                            
                        }

                    }
                }*/


                $r_price = 0.00;
                $s_price = 0.00;
                $message = '';
                foreach($applied_offers_arr as $offer){
                    
                    $r_price += $offer['strike_price'];
                    $s_price += $offer['selling_price'];
                    /*if($message == ''){
                        $message .= $offer['message'];
                    }else{
                         $message .= ' ,'.$offer['message'];
                    }*/

                    $final_data['applied_offers'][] = [ 'message' => $offer['message'] ];
                   
                }

               // dd($temp_offer);
                
                $final_data['r_price'] =  $r_price;
                $final_data['s_price'] =  $s_price;
                /*if(!empty($applied_offers_arr)){
                    $final_data['applied_offers'][] = [ 'message' => $message ];
                }*/



            }
        }

        //dd($final_data);

        if(!isset($final_data['r_price'])){
            //echo 'inside this';exit;
            $final_data['r_price'] = $price_master->MRP1;
            $final_data['s_price'] = $price_master->CSP1;
        }

        //dd($final_data);

        if(isset($global_offer['department'][$item_master->ID_MRHRC_GP_PRNT_DEPT] )){
            if(!isset($global_offer['department'][$item_master->ID_MRHRC_GP_PRNT_DEPT]['total_amount'])){
                $global_offer['department'][$item_master->ID_MRHRC_GP_PRNT_DEPT]['total_amount'] = $final_data['s_price'];
            }else{
                $global_offer['department'][$item_master->ID_MRHRC_GP_PRNT_DEPT]['total_amount'] += $final_data['s_price'];
            }

             
        }


        if(isset($global_offer['subclass'][$item_master->ID_MRHRC_GP_SUBCLASS] )){
            if(!isset($global_offer['subclass'][$item_master->ID_MRHRC_GP_SUBCLASS]['total_amount'])){
                $global_offer['subclass'][$item_master->ID_MRHRC_GP_SUBCLASS]['total_amount'] = $final_data['s_price'];
            }else{
                $global_offer['subclass'][$item_master->ID_MRHRC_GP_SUBCLASS]['total_amount'] += $final_data['s_price'];
            }

             
        }


        if(isset($global_offer['group'][$item_master->ID_MRHRC_GP_PRNT_GROUP] )){
            if(!isset($global_offer['group'][$item_master->ID_MRHRC_GP_PRNT_GROUP]['total_amount'])){
                $global_offer['group'][$item_master->ID_MRHRC_GP_PRNT_GROUP]['total_amount'] = $final_data['s_price'];
            }else{
                $global_offer['group'][$item_master->ID_MRHRC_GP_PRNT_GROUP]['total_amount'] += $final_data['s_price'];
            }

             
        }


        if(isset($global_offer['division'][$item_master->ID_MRHRC_GP_PRNT_DIVISION] )){
            if(!isset($global_offer['division'][$item_master->ID_MRHRC_GP_PRNT_DIVISION]['total_amount'])){
                $global_offer['division'][$item_master->ID_MRHRC_GP_PRNT_DIVISION]['total_amount'] = $final_data['s_price'];
            }else{
                $global_offer['division'][$item_master->ID_MRHRC_GP_PRNT_DIVISION]['total_amount'] += $final_data['s_price'];
            }

             
        }

        //dd($final_data);
        $final_data['global_offer'] = $global_offer ;
        $final_data['target_offer'] = $target_offer;
        $final_data['higest_lowest'] = $higest_lowest;
        //dd($final_data);
        if(count($final_data['available_offers'])>0 ){
            if(count($final_data['applied_offers'])==0){
                //$final_data['r_price'] = $offer_params['mrp'] * $qu_th;
                $final_data['s_price'] = $offer_params['mrp'] * $qu_th;
            }
        }

        if(empty($final_data['available_offers']) && empty($final_data['applied_offers']) ){
            $final_data['r_price'] = $offer_params['mrp'] * $qu_th;
            $final_data['s_price'] = $offer_params['mrp'] * $qu_th;
        }

       //dd($final_data);

        if($final_data['r_price'] <= 0.00){
            //echo 'Inside else';exit;
            $index=0;
            if(count($final_data['available_offers'])>0 ){
                if(count($final_data['applied_offers'])==0){
                    //echo 'Inside else';exit;
                    $final_data['r_price'] = $offer_params['mrp'] * $qu_th;
                    $final_data['s_price'] = $offer_params['mrp'] * $qu_th;
                }else{

                    foreach($mrp_arr as $key => $mrp){
                        if($mrp == $offer_params['mrp']){
                            $index = $key;
                        }
                    }

                    $final_data['r_price'] = $offer_params['mrp'] * $qu_th;
                    $final_data['s_price'] = ($price_master->CSP1 <=0.00 || $price_master->CSP1=='' || $price_master->CSP1 === NULL)?($offer_params['mrp'] * $qu_th):($csp_arr[$index] * $qu_th);

                }
            }
           
        }
        //dd($final_data);
        // dd($data['offers']);

        //$response[$cart->barcode] = $final_data;


        return $final_data;
    }
    public function fetch_individual_offers_without_cart($offer_params){

        //dd($offer_params);
        $item_master =  $offer_params['item_master'];
        $price_master =  $offer_params['price_master'];
        $target_offer = $offer_params['target_offer'];
        $higest_lowest = $offer_params['higest_lowest'];
        $global_offer = $offer_params['global_offer'];

        $cart =  $offer_params['cart'];

        $mrp_arr = array_filter( [ format_number($price_master->MRP1), format_number($price_master->MRP2) , format_number($price_master->MRP3) ]  );
        $csp_arr = array_filter( [ format_number($price_master->CSP1), format_number($price_master->CSP2) , format_number($price_master->CSP3) ]  );


        $offer_response = [];
        $offer = [];
        $dept_offer = [];
        $item_offer = [];
        $subclass_offer = [];
        $printclass_offer= [];
        $group_offer =[];
        $division_offer = [];


        $group_arr = [  'item' =>  $item_master->ITEM];

        if(!isset($global_offer['department'][$item_master->ID_MRHRC_GP_PRNT_DEPT] )){
             $group_arr['department'] = $item_master->ID_MRHRC_GP_PRNT_DEPT;
        }

        if(!isset($global_offer['subclass'][$item_master->ID_MRHRC_GP_SUBCLASS] )){
             $group_arr['subclass'] = $item_master->ID_MRHRC_GP_SUBCLASS;
        }


        if(!isset($global_offer['group'][$item_master->ID_MRHRC_GP_PRNT_GROUP] )){
             $group_arr['group'] = $item_master->ID_MRHRC_GP_PRNT_GROUP;
        }

        if(!isset($global_offer['division'][$item_master->ID_MRHRC_GP_PRNT_DIVISION] )){
             $group_arr['division'] = $item_master->ID_MRHRC_GP_PRNT_DIVISION;
        }


        foreach ($group_arr as $maxkey => $val){

            //MAX GROUP
            $max_grp_itm_lst = DB::table('spar_uat.max_grp_itm_lst')->select('ID_GRP')->where('ID_ITM', $val )->get();
            //Getting Array of id_grp column
            $group_ids =  $max_grp_itm_lst->pluck('ID_GRP')->all();

            $max_co_el_prdv_itm_grps = DB::table('spar_uat.max_co_el_prdv_itm_grp')->select('ID_GRP','ID_RU_PRDV','ITEM_GRP_TYPE','QU_TH','MO_TH')->whereIn('ID_GRP', $group_ids)->get();
            //dd($max_co_el_prdv_itm_grps);

            foreach ($max_co_el_prdv_itm_grps as $key => $max_co_el_prdv_itm_grp) {

                $offer_res =  $this->section_wise_offers( [
                    'ID_RU_PRDV' => $max_co_el_prdv_itm_grp->ID_RU_PRDV ,
                    'QU_TH' => $max_co_el_prdv_itm_grp->QU_TH ,
                    'MO_TH' => $max_co_el_prdv_itm_grp->MO_TH , 
                    'ID_GRP' => $max_co_el_prdv_itm_grp->ID_GRP , 
                    'mrps' => $mrp_arr , 
                    'csps' => $csp_arr , 
                    'given_mrp' => $offer_params['mrp'] ,
                    'weight_flag' => $price_master->WEIGHT_FLAG,
                    'offer_from' => $key,
                    'section_id' => $val
                ]);

                if($offer_res){

                    if($maxkey =='item'){
                        $item_offer[] = $offer_res;
                    }elseif($maxkey =='department'){
                        $dept_offer[$val]['offer'][] = $offer_res;
                    }else if($maxkey =='subclass'){
                        $subclasss_offer[$val]['offer'][] = $offer_res;
                    }else if($maxkey =='group'){
                        $group_offer[$val][] = $offer_res;
                    }else if($maxkey =='division'){
                        $division_offer[$val]['offer'][] = $offer_res;
                    }

                }

            }


        }


        //Col Prd ITem
        $co_el_prdv_itms = DB::table('spar_uat.co_el_prdv_itm')->select('ID_ITM','ID_RU_PRDV','ID_STR_RT','QU_TH','MO_TH')->where('ID_ITM', $item_master->ITEM)->get();

        foreach ($co_el_prdv_itms as $key => $co_el_prdv_itm) {

            $offer_res =  $this->section_wise_offers( [
                'ID_RU_PRDV' => $co_el_prdv_itm->ID_RU_PRDV ,
                'QU_TH' => $co_el_prdv_itm->QU_TH ,
                'MO_TH' => $co_el_prdv_itm->MO_TH , 
                'ID_GRP' => '', 
                'mrps' => $mrp_arr , 
                'csps' => $csp_arr , 
                'given_mrp' => $offer_params['mrp'] ,
                'weight_flag' => $price_master->WEIGHT_FLAG,
                'offer_from' => 'item',
                'section_id' => ''
            ]);

           if($offer_res){
            $item_offer[] = $offer_res;
           }

        }

        //dd($global_offer);
        //$offer['multiple_price_flag'] =  $price_master->MULTIPLE_MRP_FLAG;
        $data['mrp'] =  $mrp_arr;
        $data['csp'] =  $csp_arr;
        $offer['item'] =  $item_offer;
        
        if(!empty($dept_offer) ){
            foreach ($dept_offer as $key => $value) break;
            $global_offer['department'][$key] =  $dept_offer[$key];
        }
        if(!empty($subclass_offer) ){
            foreach ($subclass_offer as $key => $value) break;
            $global_offer['subclass'][$key] =  $subclass_offer[$key];
        }
        if(!empty($printclass_offer) ){
            foreach ($printclass_offer as $key => $value) break;
            $global_offer['printclass'][$key] =  $dept_key[$key];
        }
        if(!empty($group_offer) ){
            foreach ($group_offer as $key => $value) break;
            $global_offer['group'][$key] =  $group_offer[$key];
        }
        if(!empty($division_offer) ){
            foreach ($division_offer as $key => $value) break;
            $global_offer['division'][$key] =  $division_offer[$key];
        }

        //dd($global_offer);
        $data['offers'] = $offer;
        
        foreach( $data['offers'] as $key => $offer){
           $data['offers'][$key] = $this->remove_duplicate_offers($offer);
        }
        
        //dd($data);
        $cart_ean_list = $cart->pluck('barcode')->all();
        $cart_ean_list[] = $offer_params['product_barcode']; 
        $cart_ean_list  = array_unique($cart_ean_list);
        //dd($cart_ean_list);
    
        $final_data = [];
        $applied_offers_arr = [];
        $available_offers_arr = [];
        $available_offers_for_applying=[];
        $available_offers_for_display=[];

        $qu_th = $offer_params['product_qty'];

        //############# HIGEST LOWEST OFFER APPLYING START #############


        //############# HIGEST LOWEST OFFER APPLYING END #############

        //############# TARGET OFFER APPLYING START #############
        //This condition is added for applying target offer
        //dd($higest_lowest);
       // dd($offer_params['mrp']);
        //dd($global_offer[]);
        /*foreach($global_offer['department'] as $key => $offer){
            if($offer['ty_ru_prdv'] == 'MM'){
                $target_offer[$offer['target_product_id']][] = $offer;    
            }
        }*/
        //echo $qu_th.'::'.$offer_params['mrp'];exit;
        //dd($data);
        //dd($higest_lowest);
        foreach($data['offers']['item'] as $key => $offer){
            if($offer['ty_ru_prdv'] == 'MM'){
                $target_offer[$offer['target_product_id']][] = $offer;    
            }
            
            if(isset($offer['lowest_higest'])){
                if($offer['lowest_higest']=='higest'){

                    if($offer['cd_mth_prdv'] ==1 && $offer['offer_price'] =='100.00'){
                        //if($firstElem['qu_th'] ==  $qu_th){
                        //dd($offer);
                            $data['offers']['item'][$key]['saving_price'] = $offer_params['mrp'] ;
                            $data['offers']['item'][$key]['strike_price'] = ($qu_th * $offer_params['mrp']);
                            $data['offers']['item'][$key]['selling_price'] = ($qu_th * $offer_params['mrp']) -$offer_params['mrp'] ;
                            $data['offers']['item'][$key]['cd_mth_prdv'] = 2;
                            $data['offers']['item'][$key]['offer_price'] = $offer_params['mrp'];


                            $higest_lowest_applied_off_flag = true;
                            //echo $offer_params['mrp']." ::".$qu_th;exit;
                            //dd($data);
                            
                       // }

                    }
                    //dd($data);

                    if(!empty($higest_lowest['higest']) ){
                        //dd($cart);
                        $firstElem = reset($higest_lowest['higest']);
                        $higest_lowest_applied_off_flag = false; 
                        /*else{*/
                        $higestMrp = $cart->whereIn('barcode', $offer['product_item_list'])->max('per_unit_mrp');
                        $higestItem = $cart->whereIn('barcode', $offer['product_item_list'])->where('per_unit_mrp', $higestMrp)->first();
                        if($higestItem){
                            if($higestItem->barcode == $offer_params['product_barcode']){

                                unset($higest_lowest['higest']);
                                $higest_lowest_applied_off_flag = true;
                                $higest_lowest['higest'][$item_master->EAN] = $offer; 
                            }
                        }

                     //   }
                       
                    }else{
                        //dd($offer);
                        $higest_lowest_applied_off_flag = false; 
                        $higestMrp = $cart->whereIn('barcode', $offer['product_item_list'])->max('per_unit_mrp');
                        $higestItem = $cart->whereIn('barcode', $offer['product_item_list'])->where('per_unit_mrp', $higestMrp)->first();
                        if($higestItem){
                            if($higestItem->barcode == $offer_params['product_barcode']){
 
                                unset($higest_lowest['higest']);
                                $higest_lowest_applied_off_flag = true;
                                $higest_lowest['higest'][$item_master->EAN] = $offer; 
                            }

                        }
                       
                    }
                    
                }

                if($offer['lowest_higest']=='lowest'){
                    if(!empty($higest_lowest['lowest']) ){
                        $firstElem = reset($higest_lowest['lowest']);
                        if($firstElem['cd_mth_prdv'] ==1 && $firstElem['offer_price'] =='100.00'){
                            //if($firstElem['qu_th'] ==  $qu_th){
                            //dd($offer);
                                $data['offers']['item'][$key]['saving_price'] = $offer_params['mrp'] ;
                                $data['offers']['item'][$key]['strike_price'] = ($qu_th * $offer_params['mrp']);
                                $data['offers']['item'][$key]['selling_price'] = ($qu_th * $offer_params['mrp']) -$offer_params['mrp'] ;
                                $data['offers']['item'][$key]['cd_mth_prdv'] = 2;
                                $data['offers']['item'][$key]['offer_price'] = $offer_params['mrp'];


                                $higest_lowest_applied_off_flag = true;
                                //echo $offer_params['mrp']." ::".$qu_th;exit;
                               // dd($data);
                                
                           // }
                        }

                        $lowestMrp = $cart->whereIn('barcode', $offer['product_item_list'])->min('per_unit_mrp');
                        $lowestItem = $cart->whereIn('barcode', $offer['product_item_list'])->where('per_unit_mrp', $lowestMrp)->first();
                        /*foreach($lowestItem as $collec){
                            dd( $collec);
                        }*/
                        //$lowestItem = $lowestItem->each();
                        //dd($lowestItem);
                        $higest_lowest_applied_off_flag = false; 
                        if($lowestItem){
                            if($lowestItem->barcode == $offer_params['product_barcode']){

                                unset($higest_lowest['lowest']);
                                $higest_lowest_applied_off_flag = true;
                                $higest_lowest['lowest'][$item_master->EAN] = $offer; 
                            }
                        }




                        /*if($firstElem['strike_price'] <  $offer['strike_price']){
                            $higest_lowest_applied_off_flag = false;
                        }else if($firstElem['strike_price'] ==  $offer['strike_price']){
                             unset($higest_lowest['lowest']);
                            $higest_lowest_applied_off_flag = true;
                            $higest_lowest['lowest'][$item_master->EAN] = $offer;
                        }else{
                             
                        }*/
                    }else{
                        dd($cart);
                        $lowestMrp = $cart->whereIn('barcode', $offer['product_item_list'])->min('per_unit_mrp');
                        $lowestItem = $cart->whereIn('barcode', $offer['product_item_list'])->where('per_unit_mrp', $lowestMrp)->first();
                    
                                            
                        $higest_lowest_applied_off_flag = false; 
                        if($lowestItem){
                            if($lowestItem->barcode == $offer_params['product_barcode']){

                                unset($higest_lowest['lowest']);
                                $higest_lowest_applied_off_flag = true;
                                $higest_lowest['lowest'][$item_master->EAN] = $offer; 
                            }
                        }
                    }
                    //dd($firstElem);
                    
                }
                
                $higest_lowest_applied_off_flag;
           }
        }

        //dd($data['offers']['item']);
        //dd($higest_lowest);
        //dd($target_offer);

        $target = false;
        if(isset($target_offer[$item_master->ITEM])){
            $target = true;

            foreach($target_offer[$item_master->ITEM] as $tOffer){

                
                $target_offer[$item_master->ITEM];

                $cd_mth_prdv = $tOffer['cd_mth_prdv'];
                $mrp = (float)$offer_params['mrp'];

                if($cd_mth_prdv == 0 ) { //NOt in Use
                    $second = $mrp;
                    $final_price = $second;
                    $saving_price = 0;
                } else if($cd_mth_prdv == 1) {//By Percentage Off
                    $second = $tOffer['offer_price'];
                    $per_discount = $mrp * $second / 100;
                    $final_price = $mrp - $per_discount;
                    $saving_price = $per_discount;
                }   else if($cd_mth_prdv == 2) {//By Amount OFf
                    $second = $tOffer['offer_price'];
                    $final_price = $mrp - $second;
                    $saving_price = $second;
                } else if($cd_mth_prdv == 3) {//By Fixed Price
                    $second = (float)$tOffer['offer_price'];
                    $saving_price = $mrp - $second;
                    $final_price = $second;
                }

                $choose = array(
                    '0' => '0',
                    '1' => '% OFF',
                    '2' => ' Rs. OFF',
                    '3' => ' Rs.'
                );


                //$message  = 'On shop '.$ru_prdv->MO_TH_SRC.' get '.format_number($second).''.$choose[$cd_mth_prdv] . " on Total bill"

                $offer = [ 'saving_price' =>  $saving_price ,
                            'strike_price' =>  format_number($mrp) ,
                            'selling_price' => format_number($final_price)  ,
                            'message' => $tOffer['message'],
                            'qu_th' => $tOffer['target_qty'] ,
                            'cd_mth_prdv' => $tOffer['cd_mth_prdv'],
                            'offer_price' => $second,
                            //'weight_flag' => ,
                            'ty_ru_prdv' => $tOffer['ty_ru_prdv'],
                            'max_all_sources' => $tOffer['max_all_sources'],
                            'itm_prc_ctgy_src' => $tOffer['itm_prc_ctgy_src'],
                            'product_list' => $tOffer['product_list'],
                            'product_item_list' => $tOffer['product_item_list'] 
                        ] ;

                $data['offers']['item'][] = $offer;
            }
            //dd($target_offer[$item_master->ITEM]);
        }
        //############# TARGET OFFER APPLYING END #############

        //dd($data);
        //dd($applied_offers_arr);
        //echo 'quty:'.$qu_th;exit;
        foreach($data['offers']['item'] as $offer){
            $available_offers_for_display[] = ['message' =>  $offer['message'] , 'product_list' => $offer['product_list'] ];
            $available_offers_for_applying[] = $offer;

            if(empty($applied_offers_arr) ){
                
                if(isset($offer['lowest_higest'])){
                   // $total_qu_th = 0;
                    //echo 'iside here';exit;
                    if(!empty(array_intersect($offer['product_item_list'], $cart_ean_list))){
                        //echo 'inside this';
                        if($higest_lowest_applied_off_flag){
                            $total_qu_th = $cart->whereIn('barcode', $offer['product_item_list'])->sum('qty');
                            $barcode_qty = $cart->where('barcode', $offer_params['product_barcode'])->sum('qty');
                            if($offer['qu_th'] ==1){
                                $applied_offers_arr = $offer;
     
                            }else{


                                if(isset($offer_params['without_cart']) && $offer_params['without_cart.'] == true){
                                    $barcode_qty =1;
                                }else{
                                    $barcode_qty =0;
                                }
                                //echo 'iside here'.$total_qu_th.'::'.$barcode_qty;exit;
                                $total_qu_th += $barcode_qty;
                
                                if($total_qu_th == $offer['qu_th']){
                                    //echo ' inside this';exit();
                                    $applied_offers_arr = $offer;
                                } 
                            }
                        }
                    } 

                }else{

                    if($qu_th == $offer['qu_th']){
                        //echo 'inside this';exit;
                        if( ($offer['max_all_sources'] == 'allSources' ) && !empty($offer['product_list'] ) ){
                            //echo '<pre>';print_r($cart_ean_list); print_r($offer['product_item_list']);echo '</pre>';exit;
                           $result = (count($offer['product_item_list'])==count(array_intersect($offer['product_item_list'], $cart_ean_list)) );
                            //sort($cart_ean_list);
                           // sort($offer['product_item_list']);
                            if($result){ // check all sources exist
                                //echo 'inside this else';exit;
                                $applied_offers_arr = $offer;
                            }
                        }else if(isset($offer['lowest_higest']) && $higest_lowest_applied_off_flag === false){
                            //echo 'inside lowest higet false';exit;

                        }else if($offer['ty_ru_prdv'] == 'MM'){

                            if($target === false){

                            }else{

                                if($offer['itm_prc_ctgy_src'] == 'allSources'){

                                    $result = (count($offer['product_item_list'])==count(array_intersect($offer['product_item_list'], $cart_ean_list)) );
                                    //sort($cart_ean_list);
                                    // sort($offer['product_item_list']);
                                    if($result){ // check all sources exist
                                        //echo 'inside this else';exit;
                                        $applied_offers_arr = $offer;
                                    }

                                }

                            }
                            

                        }else{
        
                            $applied_offers_arr = $offer;

                        }                      
                       
                    }

                }
                
               
            }else{

                if($applied_offers_arr['qu_th'] == $offer['qu_th']){

                    if($applied_offers_arr['saving_price'] < $offer['saving_price']){

                        if( ($offer['max_all_sources'] == 'allSources' || $offer['itm_prc_ctgy_src'] == 'allSources' )&& !empty($offer['product_list'] ) ){
                           
                           //echo '<pre>';print_r($cart_ean_list); print_r($product_item_list);echo '</pre>';exit;
                            $result = (count($offer['product_item_list'])==count(array_intersect($offer['product_item_list'], $cart_ean_list)) );
                            //sort($cart_ean_list);
                           // sort($offer['product_item_list']);
                            if($result){ // check all sources exist
                                //echo 'inside this else';exit;
                                $applied_offers_arr = $offer;
                            }

                        }else{

                          $applied_offers_arr = $offer;
                        }
                    }

                }

            }

           
        }



        /*foreach($data['offers']['department'][$group_arr['department']] as $offer){
            $available_offers_for_display[] = ['message' =>  $offer['message'] , 'product_list' => $offer['product_list'] ];
            $available_offers_for_applying[] = $offer;

        }*/


        //dd($applied_offers_arr);
        $final_data['multiple_price_flag'] =  ( count( $data['mrp']) > 1 )? true:false;
        $final_data['multiple_mrp'] = $data['mrp'];
        if( !empty($applied_offers_arr) ){
            $final_data['r_price'] = $applied_offers_arr['strike_price'];
            $final_data['s_price'] = $applied_offers_arr['selling_price'];
            $final_data['applied_offers'][] = [ 'message' => $applied_offers_arr['message'] , 'product_list' => $applied_offers_arr['product_list']  ];

        }else{
            $final_data['applied_offers'] = [];
        }

        $final_data['available_offers'] =  $available_offers_for_display;
        //dd($available_offers_for_applying);
        if(!empty($data['offers']['item']) ){
            if(empty($applied_offers_arr) ){
                $available_offers_for_applying = array_values(  $this->get_best_offer_from_same_qty($available_offers_for_applying , $cart, $offer_params['product_barcode']) );  
                $available_offers_for_applying = $this->get_best_offer_from_same_qty($available_offers_for_applying , $cart, $offer_params['product_barcode'] ) ;
                
                $available_offers_for_applying = $this->change_index_with_qty($available_offers_for_applying);
                
                //dd($available_offers_for_applying);
                  
                $i=$qu_th -1;
                $remaining_qty = 1;
                //$j=1;
                while($i>0){
                    //$remaining_qty =$i - 1;
                   // echo $i.' '.$remaining_qty;

                    if(isset($available_offers_for_applying[$i])){
                        
                        $applied_offers_arr[] = [ 
                            'saving_price' =>  $available_offers_for_applying[$i]['saving_price'] ,
                            'strike_price' =>  (isset($available_offers_for_applying[$i]['strike_price']))?$available_offers_for_applying[$i]['strike_price']:0.00 ,
                            'selling_price' => $available_offers_for_applying[$i]['selling_price'] ,
                            'message' => $available_offers_for_applying[$i]['message']  ,
                            'qu_th' =>  $available_offers_for_applying[$i]['qu_th'],
                            'item' =>  1 
                        ];

                        $i = $remaining_qty;
                        $remaining_qty = 0;

                    }else{

                        $i--;
                        $remaining_qty++;

                    }

                    if($i == 0 ){
                       break;
                    }
                }



                //dd($applied_offers_arr);
                $temp_offer=[];
                $item = 0;

                /*$temp_offer = $applied_offers_arr;
                for($i=0; $i<count($applied_offers_arr); $i++){
                    
                    for($j=$i+1; $j<count($applied_offers_arr); $j++){
                        
                        if( $temp_offer[$i]['qu_th'] == $applied_offers_arr[$j]['qu_th']){

                            $applied_offers_arr[$j]['saving_price'] += $temp_offer[$i]['saving_price'];
                            $applied_offers_arr[$j]['strike_price'] += $temp_offer[$i]['strike_price'];
                            $applied_offers_arr[$j]['selling_price'] += $temp_offer[$i]['selling_price'];
                            $applied_offers_arr[$j]['item'] += $temp_offer[$i]['item'];
                            $applied_offers_arr[$j]['message'] = $temp_offer[$i]['message'].' applied '.$applied_offers_arr[$j]['item']. ' times';                            
                            unset($applied_offers_arr[$i]);
                            
                        }

                    }
                }*/


                $r_price = 0.00;
                $s_price = 0.00;
                $message = '';
                foreach($applied_offers_arr as $offer){
                    
                    $r_price += $offer['strike_price'];
                    $s_price += $offer['selling_price'];
                    /*if($message == ''){
                        $message .= $offer['message'];
                    }else{
                         $message .= ' ,'.$offer['message'];
                    }*/

                    $final_data['applied_offers'][] = [ 'message' => $offer['message'] ];
                   
                }

               // dd($temp_offer);
                
                $final_data['r_price'] =  $r_price;
                $final_data['s_price'] =  $s_price;
                /*if(!empty($applied_offers_arr)){
                    $final_data['applied_offers'][] = [ 'message' => $message ];
                }*/



            }
        }

        //dd($final_data);

        if(!isset($final_data['r_price'])){
            //echo 'inside this';exit;
            $final_data['r_price'] = $price_master->MRP1;
            $final_data['s_price'] = $price_master->CSP1;
        }

        //dd($final_data);

        if(isset($global_offer['department'][$item_master->ID_MRHRC_GP_PRNT_DEPT] )){
            if(!isset($global_offer['department'][$item_master->ID_MRHRC_GP_PRNT_DEPT]['total_amount'])){
                $global_offer['department'][$item_master->ID_MRHRC_GP_PRNT_DEPT]['total_amount'] = $final_data['s_price'];
            }else{
                $global_offer['department'][$item_master->ID_MRHRC_GP_PRNT_DEPT]['total_amount'] += $final_data['s_price'];
            }

             
        }


        if(isset($global_offer['subclass'][$item_master->ID_MRHRC_GP_SUBCLASS] )){
            if(!isset($global_offer['subclass'][$item_master->ID_MRHRC_GP_SUBCLASS]['total_amount'])){
                $global_offer['subclass'][$item_master->ID_MRHRC_GP_SUBCLASS]['total_amount'] = $final_data['s_price'];
            }else{
                $global_offer['subclass'][$item_master->ID_MRHRC_GP_SUBCLASS]['total_amount'] += $final_data['s_price'];
            }

             
        }


        if(isset($global_offer['group'][$item_master->ID_MRHRC_GP_PRNT_GROUP] )){
            if(!isset($global_offer['group'][$item_master->ID_MRHRC_GP_PRNT_GROUP]['total_amount'])){
                $global_offer['group'][$item_master->ID_MRHRC_GP_PRNT_GROUP]['total_amount'] = $final_data['s_price'];
            }else{
                $global_offer['group'][$item_master->ID_MRHRC_GP_PRNT_GROUP]['total_amount'] += $final_data['s_price'];
            }

             
        }


        if(isset($global_offer['division'][$item_master->ID_MRHRC_GP_PRNT_DIVISION] )){
            if(!isset($global_offer['division'][$item_master->ID_MRHRC_GP_PRNT_DIVISION]['total_amount'])){
                $global_offer['division'][$item_master->ID_MRHRC_GP_PRNT_DIVISION]['total_amount'] = $final_data['s_price'];
            }else{
                $global_offer['division'][$item_master->ID_MRHRC_GP_PRNT_DIVISION]['total_amount'] += $final_data['s_price'];
            }

             
        }

        //dd($final_data);
        $final_data['global_offer'] = $global_offer ;
        $final_data['target_offer'] = $target_offer;
        $final_data['higest_lowest'] = $higest_lowest;
        //dd($final_data);
        if(count($final_data['available_offers'])>0 ){
            if(count($final_data['applied_offers'])==0){
                //$final_data['r_price'] = $offer_params['mrp'] * $qu_th;
                $final_data['s_price'] = $offer_params['mrp'] * $qu_th;
            }
        }

        if(empty($final_data['available_offers']) && empty($final_data['applied_offers']) ){
            $final_data['r_price'] = $offer_params['mrp'] * $qu_th;
            $final_data['s_price'] = $offer_params['mrp'] * $qu_th;
        }

       //dd($final_data);

        if($final_data['r_price'] <= 0.00){
            //echo 'Inside else';exit;
            $index=0;
            if(count($final_data['available_offers'])>0 ){
                if(count($final_data['applied_offers'])==0){
                    //echo 'Inside else';exit;
                    $final_data['r_price'] = $offer_params['mrp'] * $qu_th;
                    $final_data['s_price'] = $offer_params['mrp'] * $qu_th;
                }else{

                    foreach($mrp_arr as $key => $mrp){
                        if($mrp == $offer_params['mrp']){
                            $index = $key;
                        }
                    }

                    $final_data['r_price'] = $offer_params['mrp'] * $qu_th;
                    $final_data['s_price'] = ($price_master->CSP1 <=0.00 || $price_master->CSP1=='' || $price_master->CSP1 === NULL)?($offer_params['mrp'] * $qu_th):($csp_arr[$index] * $qu_th);

                }
            }
           
        }
        //dd($final_data);
        // dd($data['offers']);

        //$response[$cart->barcode] = $final_data;


        return $final_data;
    }


}





