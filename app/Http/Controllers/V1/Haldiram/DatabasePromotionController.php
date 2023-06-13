<?php

namespace App\Http\Controllers\V1\Haldiram;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

class DatabasePromotionController extends Controller
{

        
    public function process_individual_item($params){

       /* $barcode = $params['barcode'];
        $qty = $params['qty'];
        $mrp = $params['mrp'];
        $store = $params['store_id'];
        $final_data = [];
        $cart_item = $params['cart_item'];
        $user_id = $params['user_id'];
        $carts = $params['carts'];*/

        $item_master = $params['item_master'];
       /* $price_master = $params['price_master'];
        $mrp_arr = $params['mrp_arr'];
        $csp_arr = $params['csp_arr'];
        $mapping_store_id = $params['mapping_store_id'];*/

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

        
        

        dd($push_data);

        
        return $final_data;

    }

    public function index(Request $request)
    {
        
        $item_masters = DB::table('spar_uat.item_master')->where('ITEM', '100038911')->paginate(1);
        foreach($item_masters as $item_master){
            /*$barcode = $params['product_barcode'];
            $qty = $params['product_qty'];
            $mrp = $params['mrp'];
            $v_id = $params['v_id'];
            $store = $params['store_id'];
            $user_id = $params['c_id'];
            $mapping_store_id = $params['mapping_store_id'];
            $final_data = [];
            $cart_item = false;

            $item_master = $params['item_master' => $item_master];
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

            $carts = $params['cart'];*/

            $params = [ //'barcode' => $barcode, 'qty' => $qty, 'mrp' => $mrp, 'v_id' => $v_id , 'store_id' => $store , 'price_master' => $price_master , 'user_id' => $user_id  , 'cart_item' => $cart_item, 'mrp_arr' => $mrp_arr, 'csp_arr' => $csp_arr ,'carts' => $carts , 'mapping_store_id' => $mapping_store_id ,
                 'item_master' => $item_master
            ];

            $final_data = $this->process_individual_item($params);

        }
        

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


            $ru_prdvs = DB::table('spar_uat.ru_prdv')->select('ID_RU_PRDV','SC_RU_PRDV','TY_RU_PRDV','CD_MTH_PRDV','DE_RU_PRDV','DC_RU_PRDV_EP','DC_RU_PRDV_EF','MAX_ALL_SOURCES','ITM_PRC_CTGY_SRC','MAX_FREE_ITEM','MO_TH_SRC','MAX_PARENT_ID','ID_PRM')->whereIn('DE_RU_PRDV', $bill_buster)->where('MO_TH_SRC','>','0')->orderBy('TS_CRT_RCRD')->get();
            //dd($get_promo_details);
            foreach($ru_prdvs as $get_promo_details){

                $startdate = date_create_from_format('d-M-y h.i.s A' ,$get_promo_details->DC_RU_PRDV_EF );
                $startdate = $startdate->getTimestamp();
                $enddate = date_create_from_format('d-M-y h.i.s A' ,$get_promo_details->DC_RU_PRDV_EP );
                $enddate = $enddate->getTimestamp();
                    
                if (($today >= $startdate) && ($today <= $enddate)) {
                    array_push($final_data, [ 'ru_prdv' => $get_promo_details->ID_RU_PRDV, 'mo_th' => $get_promo_details->MO_TH_SRC, 'type' => 'bill_buster', 'level' => $type, 'promo_type' => $get_promo_details->DE_RU_PRDV, 'promo_id' => $get_promo_details->ID_PRM ,'max_free_item' => $get_promo_details->MAX_FREE_ITEM, 'start_date' => $get_promo_details->DC_RU_PRDV_EF , 'end_date' => $get_promo_details->DC_RU_PRDV_EP  ]);
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

            $list_grp_id = DB::table('spar_uat.max_grp_itm_lst')->select('ID_GRP')->where('ID_ITM', $id)->orderBy('ID_GRP','desc')->get()->pluck('ID_GRP');

            $max_co_el_prdv_itm_grp = DB::table('spar_uat.max_co_el_prdv_itm_grp')->select('MO_TH','QU_TH','ID_RU_PRDV','ID_GRP')->whereIn('ID_GRP', $list_grp_id)->get();

            foreach ($max_co_el_prdv_itm_grp as $key => $value) {
                array_push($data, [ 'ru_prdv' => $value->ID_RU_PRDV, 'mo_th' => $value->MO_TH, 'qu_th' => $value->QU_TH, 'type' => 'max', 'id' => $value->ID_GRP, 'level' => $type ]);
            }

            if ($type == 'item') {
                
                $co_el_prdv_itm = DB::table('spar_uat.co_el_prdv_itm')->select('MO_TH','QU_TH','ID_RU_PRDV','ID_ITM')->where('ID_ITM', $id)->get();

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
                
                $get_promo_details = DB::table('spar_uat.ru_prdv')->select('ID_STR_RT','TY_RU_PRDV','DE_RU_PRDV','MAX_ALL_SOURCES','ITM_PRC_CTGY_SRC','MAX_FREE_ITEM','CD_BAS_CMP_SRC','CD_BAS_CMP_TGT','DC_RU_PRDV_EF','DC_RU_PRDV_EP','ID_PRM','ID_RU_PRDV','CD_MTH_PRDV','MAX_ALL_TARGETS')->where('ID_RU_PRDV', $value['ru_prdv'])->orderBy('TS_CRT_RCRD')->first();
                $startdate = date_create_from_format('d-M-y h.i.s A' ,$get_promo_details->DC_RU_PRDV_EF );
                $startdate = $startdate->getTimestamp();
                $enddate = date_create_from_format('d-M-y h.i.s A' ,$get_promo_details->DC_RU_PRDV_EP );
                $enddate = $enddate->getTimestamp();
                    
                    if (($today >= $startdate) && ($today <= $enddate)) {
                        array_push($final_data, [ 'ru_prdv' => $value['ru_prdv'], 'mo_th' => $value['mo_th'], 'qu_th' => $value['qu_th'], 'type' => $value['type'], 'id' => $value['id'], 'level' => $value['level'], 'promo_type' => $get_promo_details->DE_RU_PRDV, 'promo_id' => $get_promo_details->ID_PRM, 'cd_bas_cmp_tgt' => $get_promo_details->CD_BAS_CMP_TGT, , 'start_date' => $get_promo_details->DC_RU_PRDV_EF , 'end_date' => $get_promo_details->DC_RU_PRDV_EP ]);
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

     



}
