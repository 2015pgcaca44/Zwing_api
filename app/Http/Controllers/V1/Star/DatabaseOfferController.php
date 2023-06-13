<?php

namespace App\Http\Controllers\V1\Star;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use DB;

use Auth;

class DatabaseOfferController extends Controller
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

                        $message = "On shop ".$ru_prdv->MO_TH_SRC." and above get ".$ru_prdv->MAX_FREE_ITEM." as Free";

                        $offer[] = [ 'message' => $message  , 'mo_th_src' => $ru_prdv->MO_TH_SRC , 'cd_mth_prdv' => $ru_prdv->CD_MTH_PRDV ]; 
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


        if($ru_prdv->MAX_ALL_SOURCES == 'allSources'){
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

            if($ru_prdv->MAX_ALL_SOURCES == 'allSources' ){
                //$message =  'Combi offer buy '.$qu_th.' each in group '.implode($product_list,',').' for '.format_number($second).''.$choose[$ru_prdv->CD_MTH_PRDV];
                 $message =  'Combi offer buy '.$qu_th.' each for '.format_number($second).''.$choose[$ru_prdv->CD_MTH_PRDV];
            }


            if(trim($weight_flag) == 'YES'){

            }else{
                $qu_th = (int)$qu_th;
            }

            
            if($ru_prdv->TY_RU_PRDV == 'MM'){
                //$second > 0.00 is added to remove offer of 0% dept wise offer 
                if($second > 0){
                 $offer = [  //'saving_price' =>  $saving_price , 'selling_price' => format_number($mrp) ,
                  'message' => $message  , 'qu_th' => $qu_th , 'cd_mth_prdv' =>  $ru_prdv->CD_MTH_PRDV , 'offer_price' => $second, 'weight_flag' => trim($weight_flag), 'ty_ru_prdv' =>$ru_prdv->TY_RU_PRDV , 'max_all_sources' => $ru_prdv->MAX_ALL_SOURCES, 'product_list' => $product_list , 'product_item_list' => $product_item_list , 'mo_th' => $mo_th , 'target_qty' => $qu_lm_mxmh , 'target_product_id' => $matchProduct->ITEM  , 'effective_date' => $ru_prdv->DC_RU_PRDV_EF, 'expiry_date' =>$ru_prdv->DC_RU_PRDV_EP ];

                }else{
                    $offer = null;
                }

            }else{

                if($ru_prdv->CD_MTH_PRDV == 3){

                    $offer = [ //'saving_price' =>  $saving_price , 'strike_price' => format_number($mrp) , 'selling_price' => format_number($final_price) ,
                     'message' => $message  , 'qu_th' => $qu_th , 'cd_mth_prdv' => 3, 'offer_price' => $second, 'weight_flag' => trim($weight_flag) , 'ty_ru_prdv' =>$ru_prdv->TY_RU_PRDV ,'max_all_sources' =>$ru_prdv->MAX_ALL_SOURCES, 'product_list' => $product_list ,  'ean_list' => $product_item_list  , 'effective_date' => $ru_prdv->DC_RU_PRDV_EF, 'expiry_date' =>$ru_prdv->DC_RU_PRDV_EP];

                }else{

                    if($qu_th > 1 ){

                        
                        $offer = [ //'saving_price' =>  $saving_price , 'strike_price' =>  format_number($mrp) , 'selling_price' => format_number($final_price)  ,
                         'message' => $message , 'qu_th' => $qu_th , 'cd_mth_prdv' => $ru_prdv->CD_MTH_PRDV, 'offer_price' => $second, 'weight_flag' => trim($weight_flag ), 'ty_ru_prdv' =>$ru_prdv->TY_RU_PRDV ,'max_all_sources' =>$ru_prdv->MAX_ALL_SOURCES, 'product_list' => $product_list , 'product_item_list' => $product_item_list  , 'effective_date' => $ru_prdv->DC_RU_PRDV_EF, 'expiry_date' =>$ru_prdv->DC_RU_PRDV_EP];
                        
                    }else{

                        $offer = [ //'saving_price' =>  $saving_price , 'strike_price' => format_number($mrp) , 'selling_price' => format_number($final_price) ,
                        'message' => $message , 'qu_th' => $qu_th , 'cd_mth_prdv' => $ru_prdv->CD_MTH_PRDV, 'offer_price' => $second , 'weight_flag' => trim($weight_flag) , 'ty_ru_prdv' =>$ru_prdv->TY_RU_PRDV , 'max_all_sources' =>$ru_prdv->MAX_ALL_SOURCES, 'product_list' => $product_list, 'product_item_list' => $product_item_list , 'effective_date' => $ru_prdv->DC_RU_PRDV_EF, 'expiry_date' =>$ru_prdv->DC_RU_PRDV_EP ];

                    }

                }

                if (strpos($ru_prdv->DE_RU_PRDV, 'LowestPrice') !== false){
                    $offer['lowest_higest'] = 'lowest'; 
                }

                if (strpos($ru_prdv->DE_RU_PRDV, 'HighestPrice') !== false){
                    $offer['lowest_higest'] = 'higest'; 
                }

            
            }



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


    public function create_offers(Request $request){
        $skip = 0;
        $take =0;
        if($request->has('page')){
            $page = $request->page;

            $take = 1000;
            if($page ==1){
                $skip == 0;
            }else{
                $skip = $take * ($page -1);
            }

            
        }else{
            $skip = $request->skip;
            $take = $request->take;
        }
       
        

        $current_timestamp = time();
        $item_masters = DB::table('spar_uat.item_master')->skip($skip)->take($take)->get();
        //$item_masters = DB::table('spar_uat.item_master')->where('ITEM', '114120561')->get();

        //$item_masters = DB::table('spar_uat.item_master')->orderBy('id','asc')->chunk(100, function($item_masters){


        foreach ($item_masters as $key => $item_master) {
           
            $price_master = DB::table('spar_uat.price_master')->where('ITEM', $item_master->ITEM)->first();
            $mrp_arrs = array_filter( [ format_number($price_master->MRP1), format_number($price_master->MRP2) , format_number($price_master->MRP3) ]  );
       
           // foreach($mrp_arrs as $mrp){

                $status = $this->fetch_individual_offers( [ 
                    'item_master'=>$item_master ,
                    'price_master' => $price_master,
                    //'cart' => $carts,
                    'mrp' => $price_master->MRP1,
                    //'product_barcode' => $cart['barcode'],
                    //'product_qty' => $cart['qty'],
                    //'global_offer' => $global_offer
                    
                    ] 
                );

               /* if($status){
                    echo 'Success: Data has been inserted successfull for Item'.$item_master->ITEM. ' Price '.$mrp;
                    echo '<br\>';
                }else{
                    echo 'ERROR: Unable to insert a data for Item'.$item_master->ITEM. ' Price '.$mrp;
                    echo '<br\>';

                }*/

                //return $status;

            //}

        }

        //});

        //dd($final_data);

        //return $final_data;


    }

    public function fetch_individual_offers($offer_params){

        $item_master =  $offer_params['item_master'];
        $price_master =  $offer_params['price_master'];
       // $cart =  $offer_params['cart'];

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

        //$offer['multiple_price_flag'] =  $price_master->MULTIPLE_MRP_FLAG;
        $data['mrp'] =  $mrp_arr;
        $data['csp'] =  $csp_arr;
        $offer['item'] =  $item_offer;
        $global_offer['department'] =  $dept_offer;
        $global_offer['subclass'] =  $subclass_offer;
        $global_offer['printclass'] =  $printclass_offer;
        $global_offer['group'] =  $group_offer;
        $global_offer['division'] =  $division_offer;

        $data['offers'] = $offer;

        
       
        $data['offers']['item'] = $this->remove_duplicate_offers($data['offers']['item']);
        foreach( $global_offer as $gkey => $offers){
            foreach($offers as $key => $offer){
                $global_offer[$gkey] = $this->remove_duplicate_offers($offer);
            }
        }

        //dd($data);

        $data = json_encode($data);

        $ids =  DB::table('spar_uat.available_item_offers')->insertGetId(
            [ 
                'store_id' => $item_master->STORE,
                'ean' => $item_master->EAN , 
                'item_id' => $item_master->ITEM,
                'item_desc' => $price_master->ITEM_DESC,
                'tax_category' => $item_master->TAX_CATEGORY,
                'hsn' => $item_master->HSN,
                'subclass_id' => $item_master->ID_MRHRC_GP_SUBCLASS,
                'printclass_id' => $item_master->ID_MRHRC_GP_PRNT_CLASS,
                'department_id' => $item_master->ID_MRHRC_GP_PRNT_DEPT,
                'group_id' => $item_master->ID_MRHRC_GP_PRNT_GROUP ,
                'division_id' => $item_master->ID_MRHRC_GP_PRNT_DIVISION ,
                'weight_flag' => $price_master->WEIGHT_FLAG,
                'offers' => $data
            ]
        );

        //dd($global_offer);

        foreach($global_offer as $key => $value){

            if(!empty($value) ){

                //$id = $group_arr[$key];
                
                $response = DB::table('spar_uat.available_'.$key.'_offers')->where($key.'_id', $group_arr[$key])->first();
                if($response){

                }else{

                    $idss =  DB::table('spar_uat.available_'.$key.'_offers')->insertGetId(
                        [$key.'_id' => $group_arr[$key] , 'offers' => json_encode($value)]
                    );
                }  
            }
        }



    
       if( $ids > 0 ){

            return true;
       }else{
            return false;
       }

    }




    public function get_offers_without_cart($offer_params){

        $current_timestamp = time();

        $item_master =  $offer_params['item_master'];
        $price_master =  $offer_params['price_master'];
        $carts =  $offer_params['cart'];


        
        $mrp_arr = array_filter( [ $price_master->MRP1, $price_master->MRP2 , $price_master->MRP3 ]  );
        $csp_arr = array_filter( [ $price_master->CSP1, $price_master->CSP2 , $price_master->CSP3 ]  );

        $offer_response = [];
        $offer = [];
        $dept_offer = [];
        $item_offer = [];
        $subclass_offer = [];
        $printclass_offer= [];
        $group_offer =[];
        $division_offer = [];


        $group_arr = [  'item' =>  $item_master->ITEM,
                        'department' => $item_master->ID_MRHRC_GP_PRNT_DEPT , 
                        'subclass' => $item_master->ID_MRHRC_GP_SUBCLASS ,
                        'group' => $item_master->ID_MRHRC_GP_PRNT_GROUP , 
                        'division' => $item_master->ID_MRHRC_GP_PRNT_DIVISION ];

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
                        $dept_offer[$val][] = $offer_res;
                    }else if($maxkey =='subclass'){
                        $subclasss_offer[$val][] = $offer_res;
                    }else if($maxkey =='group'){
                        $group_offer[$val][] = $offer_res;
                    }else if($maxkey =='division'){
                        $division_offer[$val][] = $offer_res;
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
                'offer_from' => 'ID_ITM'
            ]);

           if($offer_res){

            $item_offer[] = $offer_res;
           }

        }

        //$offer['multiple_price_flag'] =  $price_master->MULTIPLE_MRP_FLAG;
        $data['mrp'] =  $mrp_arr;
        $data['csp'] =  $csp_arr;
        $offer['item'] =  $item_offer;
        $offer['department'] =  $dept_offer;
        $offer['subclass'] =  $subclass_offer;
        $offer['printclass'] =  $printclass_offer;
        $offer['group'] =  $group_offer;
        $offer['division'] =  $division_offer;

        $data['offers'] = $offer;
        //dd($data);

        foreach( $data['offers'] as $key => $offer){
           $data['offers'][$key] = $this->remove_duplicate_offers($offer);
        }

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
        //echo 'quty:'.$qu_th;exit;
        foreach($data['offers']['item'] as $offer){
            $available_offers_for_display[] = ['message' =>  $offer['message'] , 'product_list' => $offer['product_list'] ];
            $available_offers_for_applying[] = $offer;

            if(empty($applied_offers_arr) ){
                
                if($qu_th == $offer['qu_th']){

                    if( $offer['max_all_sources'] == 'allSources' && !empty($offer['product_list'] ) ){
                        //echo '<pre>';print_r($cart_ean_list); print_r($offer['product_item_list']);echo '</pre>';exit;
                        $result = !empty(array_intersect($offer['product_item_list'], $cart_ean_list));
                        if($result){ // check all sources exist
                            //echo 'inside this if';
                            $applied_offers_arr = $offer;
                        }

                    }else{
                        $applied_offers_arr = $offer;

                    }                      
                   
                }
                
               
            }else{

                if($applied_offers_arr['qu_th'] == $offer['qu_th']){

                    if($applied_offers_arr['saving_price'] < $offer['saving_price']){

                        if( $offer['max_all_sources'] == 'allSources' && !empty($offer['product_list'] ) ){
                           
                           //echo '<pre>';print_r($cart_ean_list); print_r($product_item_list);echo '</pre>';exit;
                            $result = !empty(array_intersect($offer['product_item_list'], $cart_ean_list));

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



        foreach($data['offers']['department'][$group_arr['department']] as $offer){
            $available_offers_for_display[] = ['message' =>  $offer['message'] , 'product_list' => $offer['product_list'] ];
            $available_offers_for_applying[] = $offer;

        }


       //dd($applied_offers_arr);
        $final_data['multiple_price_flag'] =  ( count( $data['mrp']) > 1 )? true:false;
        $final_data['multiple_mrp'] = $data['mrp'];
        if( !empty($applied_offers_arr) ){
            $final_data['r_price'] = $applied_offers_arr['strike_price'];
            $final_data['s_price'] = $applied_offers_arr['selling_price'];
            $final_data['applied_offers'] = [ 'message' => $applied_offers_arr['message'] , 'product_list' => $applied_offers_arr['product_list']  ];

        }else{
             $final_data['applied_offers'] = [];
        }

        $final_data['available_offers'] =  $available_offers_for_display;



        if(!empty($data['offers']['item']) ){
            if(empty($applied_offers_arr) ){
                $available_offers_for_applying = $this->get_best_offer_from_same_qty($available_offers_for_applying);
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
                            'strike_price' =>  $available_offers_for_applying[$i]['strike_price'] ,
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

                $temp_offer = $applied_offers_arr;
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
                }


                $r_price = 0;
                $s_price = 0;
                $message = '';
                foreach($applied_offers_arr as $offer){
                    
                    $r_price += $offer['strike_price'];
                    $s_price += $offer['selling_price'];
                    if($message == ''){
                        $message .= $offer['message'];
                    }else{
                         $message .= ' ,'.$offer['message'];
                    }
                   
                }

               // dd($temp_offer);

                $final_data['r_price'] =  $r_price;
                $final_data['s_price'] =  $s_price;
                $final_data['applied_offers']['message'] = $message ;


            }
        }

    

       // dd($applied_offers_arr);
       dd($final_data);

        return $final_data;


    }

}
