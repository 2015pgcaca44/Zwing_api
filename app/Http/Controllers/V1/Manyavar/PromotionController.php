<?php

namespace App\Http\Controllers\V1\Manyavar;

use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\VendorSettingController;
use Illuminate\Http\Request;
use DB;
use Auth;

class PromotionController extends Controller
{
    public function final_check_promo_sitewise($request, $cart = 0)
    {
        // dd($request);
        date_default_timezone_set('Asia/Kolkata');
        $v_id = $request['v_id'];
        $trans_from = $request['trans_from'];
        $id = $request['barcode'];
        $qty = $request['qty'];
        $scode = $request['scode'];
        $data = array();
        $push_data = [];
        $unique_data = array();
        $promo_data = array();
        $single_data = array();
        $final_promo_assortment_list = array();
        $discount = 0;
        $promo_list = array();
        $psite_promo_list = array();
        $final_promo_list = array();
        $today = date('Y-m-d');
        $today = date('Y-m-d', strtotime($today));
        $count = 0;
        $item = DB::table('manyavar.invitem')->select('GRPCODE','INVARTICLE_CODE','CCODE1','CCODE2','CCODE3','CCODE4','CCODE5','CCODE6','ICODE','GENERATED','MRP','CNAME1','CNAME2','INVHSNSACMAIN_CODE')->where('ICODE', $id)->first();
        // dd($item);
        $group = DB::table('manyavar.invgrp')->select('LEV1GRPNAME','LEV2GRPNAME','GRPCODE','GRPNAME')->where('GRPCODE', $item->GRPCODE)->first();
        $article = DB::table('manyavar.invarticle')->select('CODE','NAME')->where('CODE', $item->INVARTICLE_CODE)->first();
        $division = DB::table('manyavar.invgrp')->select('GRPCODE','GRPNAME')->where('GRPNAME', $group->LEV1GRPNAME)->first();
        $section = DB::table('manyavar.invgrp')->select('GRPCODE','GRPNAME')->where('GRPNAME', $group->LEV2GRPNAME)->first();
        $admsite = DB::table('manyavar.admsite')->select('NAME')->where('CODE', $scode)->first();
        
        
        $psite_promo_assign = DB::table('manyavar.psite_promo_assign')->select('STARTDATE','ENDDATE','PROMO_CODE')->where('ADMSITE_CODE', $scode)->where('STATUS', 'A')->get();
        // dd($psite_promo_assign);
        if(count($psite_promo_assign) > 0) {
            foreach ($psite_promo_assign as $key => $promo_assign) {
                $startdate = date('Y-m-d', strtotime($promo_assign->STARTDATE));
                $enddate = date('Y-m-d', strtotime($promo_assign->ENDDATE));
                // echo 'Start Date :- '.$startdate.' End Date :- '.$enddate.' CODE :- '.$promo_assign->PROMO_CODE.'<br>';
                // if (($today >= $startdate) && ($today <= $enddate)) {
                    // echo 'Start Date :- '.$startdate.' End Date :- '.$enddate.' CODE :- '.$promo_assign->PROMO_CODE.'<br>';
                    $promo_buy = DB::table('manyavar.promo_buy')->select('ASSORTMENT_CODE')->where('PROMO_CODE', $promo_assign->PROMO_CODE)->first();
                    array_push($promo_data, $promo_buy->ASSORTMENT_CODE);
                    array_push($psite_promo_list, $promo_assign->PROMO_CODE);
                    // echo $promo_assign->PROMO_CODE.'<br>';
                // }
            }               
        }
        // dd($promo_data);
        $adivision = DB::table('manyavar.promo_assortment_include')
                    ->select('ASSORTMENT_CODE')
                    ->where('DIVISION_GRPCODE', $division->GRPCODE)
                    ->whereIn('ASSORTMENT_CODE', $promo_data)
                    ->orderBy('ASSORTMENT_CODE','DESC')
                    ->distinct()
                    ->get()->pluck('ASSORTMENT_CODE');

        if (count($adivision) > 0) {
            foreach ($adivision as $key => $value) {
                if (!empty($this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'DIVISION_GRPCODE', $division->GRPCODE))) {
                    array_push($data, $this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'DIVISION_GRPCODE', $division->GRPCODE));
                }
            }
        }
                
        $asection = DB::table('manyavar.promo_assortment_include')
                    ->select('ASSORTMENT_CODE')
                    ->where('SECTION_GRPCODE', $section->GRPCODE)
                    ->whereIn('ASSORTMENT_CODE', $promo_data)
                    ->orderBy('ASSORTMENT_CODE','DESC')
                    ->distinct()
                    ->get()->pluck('ASSORTMENT_CODE');
                    // dd($asection);

        if (count($asection) > 0) {
            foreach ($asection as $key => $value) {
                if (!empty($this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'SECTION_GRPCODE', $section->GRPCODE))) {
                    array_push($data, $this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'SECTION_GRPCODE', $section->GRPCODE));
                }   
            }   
        }
        
        $adepartment = DB::table('manyavar.promo_assortment_include')
                    ->select('ASSORTMENT_CODE')
                    ->where('DEPARTMENT_GRPCODE', $group->GRPCODE)
                    ->whereIn('ASSORTMENT_CODE', $promo_data)
                    ->orderBy('ASSORTMENT_CODE','DESC')
                    ->distinct()
                    ->get()->pluck('ASSORTMENT_CODE');

        // dd($adepartment);

        if (count($adepartment) > 0) {
            foreach ($adepartment as $key => $value) {
                if (!empty($this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'DEPARTMENT_GRPCODE', $group->GRPCODE))) {
                    array_push($data, $this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'DEPARTMENT_GRPCODE', $group->GRPCODE));
                }   
            }   
        }
        
        $aarticle = DB::table('manyavar.promo_assortment_include')
                    ->select('ASSORTMENT_CODE')
                    ->where('INVARTICLE_CODE', $article->CODE)
                    ->whereIn('ASSORTMENT_CODE', $promo_data)
                    ->orderBy('ASSORTMENT_CODE','DESC')
                    ->distinct()
                    ->get()->pluck('ASSORTMENT_CODE');

        
        if (count($aarticle) > 0) {
            foreach ($aarticle as $key => $value) {
                if (!empty($this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'INVARTICLE_CODE', $article->CODE))) {
                    array_push($data, $this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'INVARTICLE_CODE', $article->CODE));
                }   
            }   
        }

        // dd($data);

        $item_code = DB::table('manyavar.promo_assortment_include')
                    ->select('ASSORTMENT_CODE')
                    ->where('ICODE', $id)
                    ->whereIn('ASSORTMENT_CODE', $promo_data)
                    ->orderBy('ASSORTMENT_CODE','DESC')
                    ->distinct()
                    ->get()->pluck('ASSORTMENT_CODE');

        if (count($item_code) > 0) {
            foreach ($item_code as $key => $value) {
                if (!empty($this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'ICODE', $id))) {
                    array_push($data, $this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'ICODE', $id));
                }   
            }   
        }

        
        if (!empty($item->CCODE1)) {
            $category_1 = DB::table('manyavar.promo_assortment_include')
                        ->select('ASSORTMENT_CODE')
                        ->where('CCODE1', $item->CCODE1)
                        ->whereIn('ASSORTMENT_CODE', $promo_data)
                        ->orderBy('ASSORTMENT_CODE','DESC')
                        ->distinct()
                        ->get()->pluck('ASSORTMENT_CODE');
                        // dd($category_1);
            if (count($category_1) > 0) {
                foreach ($category_1 as $key => $value) {
                    if (!empty($this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'CCODE1', $item->CCODE1))) {
                        array_push($data, $this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'CCODE1', $item->CCODE1));
                    }   
                }   
            }
        }

        
        if (!empty($item->CCODE2)) {
            $category_2 = DB::table('manyavar.promo_assortment_include')
                        ->select('ASSORTMENT_CODE')
                        ->where('CCODE1', $item->CCODE1)
                        ->whereIn('ASSORTMENT_CODE', $promo_data)
                        ->orderBy('ASSORTMENT_CODE','DESC')
                        ->distinct()
                        ->get()->pluck('ASSORTMENT_CODE');

            if (count($category_2) > 0) {
                foreach ($category_2 as $key => $value) {
                    if (!empty($this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'CCODE2', $item->CCODE2))) {
                        array_push($data, $this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'CCODE2', $item->CCODE2));
                    }   
                }   
            }
        }

        
        if (!empty($item->CCODE3)) {
            $category_3 = DB::table('manyavar.promo_assortment_include')
                        ->select('ASSORTMENT_CODE')
                        ->where('CCODE1', $item->CCODE1)
                        ->whereIn('ASSORTMENT_CODE', $promo_data)
                        ->orderBy('ASSORTMENT_CODE','DESC')
                        ->distinct()
                        ->get()->pluck('ASSORTMENT_CODE');

            if (count($category_3) > 0) {
                foreach ($category_3 as $key => $value) {
                    if (!empty($this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'CCODE3', $item->CCODE3))) {
                        array_push($data, $this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'CCODE3', $item->CCODE3));
                    }   
                }   
            }
        }

        
        if (!empty($item->CCODE4)) {
            $category_4 = DB::table('manyavar.promo_assortment_include')
                        ->select('ASSORTMENT_CODE')
                        ->where('CCODE1', $item->CCODE1)
                        ->whereIn('ASSORTMENT_CODE', $promo_data)
                        ->orderBy('ASSORTMENT_CODE','DESC')
                        ->distinct()
                        ->get()->pluck('ASSORTMENT_CODE');

            if (count($category_4) > 0) {
                foreach ($category_4 as $key => $value) {
                    if (!empty($this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'CCODE4', $item->CCODE4))) {
                        array_push($data, $this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'CCODE4', $item->CCODE4));
                    }   
                }   
            }
        }

        if (!empty($item->CCODE5)) {
            $category_5 = DB::table('manyavar.promo_assortment_include')
                        ->select('ASSORTMENT_CODE')
                        ->where('CCODE1', $item->CCODE1)
                        ->whereIn('ASSORTMENT_CODE', $promo_data)
                        ->orderBy('ASSORTMENT_CODE','DESC')
                        ->distinct()
                        ->get()->pluck('ASSORTMENT_CODE');

            if (count($category_5) > 0) {
                foreach ($category_5 as $key => $value) {
                    if (!empty($this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'CCODE5', $item->CCODE5))) {
                        array_push($data, $this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'CCODE5', $item->CCODE5));
                    }   
                }   
            }
        }

        
        if (!empty($item->CCODE6)) {
            $category_6 = DB::table('manyavar.promo_assortment_include')
                        ->select('ASSORTMENT_CODE')
                        ->where('CCODE1', $item->CCODE1)
                        ->whereIn('ASSORTMENT_CODE', $promo_data)
                        ->orderBy('ASSORTMENT_CODE','DESC')
                        ->distinct()
                        ->get()->pluck('ASSORTMENT_CODE');

            if (count($category_6) > 0) {
                foreach ($category_6 as $key => $value) {
                    if (!empty($this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'CCODE6', $item->CCODE6))) {
                        array_push($data, $this->final_cross_check($value, $item, $group->GRPCODE, $article->CODE, $division->GRPCODE, $section->GRPCODE, 'CCODE6', $item->CCODE6));
                    }   
                }   
            }
        }
        // dd($data);

        
        $single_data = array_unique($data, SORT_REGULAR);
        rsort($single_data);
        $unique_data = array_unique($single_data, SORT_REGULAR);
        rsort($unique_data);
        $count = 1;
        foreach ($unique_data as $key => $value) {
        	$split_value = explode("-", $value);
            $promobuy = DB::table('manyavar.promo_buy')->select('ASSORTMENT_NAME','PROMO_CODE')->where('ASSORTMENT_CODE', $split_value[0])->whereIn('PROMO_CODE', $psite_promo_list)->first();
            // echo $promobuy->ASSORTMENT_NAME.' :- '.$promobuy->PROMO_CODE.' - '.$value.'<br><br>';
            // echo '<tr><td>'.$count++.'</td><td>'.$promobuy->ASSORTMENT_NAME.'</td><td>'.$value.'</td><td>'.$promobuy->PROMO_CODE.'</td></tr>';
            array_push($promo_list, [ 'ASSORTMENT_CODE' => $value, 'PROMO_CODE' => $promobuy->PROMO_CODE ]);
        }
        // dd();
        $distinct = array_unique($promo_list, SORT_REGULAR);
        // dd($distinct);
        foreach ($distinct as $key => $value) {
            $final_psite_promo_assign = DB::table('manyavar.psite_promo_assign')->select('PROMO_CODE','STARTDATE','ENDDATE','PRIORITY')->where('PROMO_CODE', $value['PROMO_CODE'])->where('STATUS','A')->where('ADMSITE_CODE', $scode)->first();
            if (count($final_psite_promo_assign) > 0) {
                // $startdate = date('Y-m-d', strtotime($final_psite_promo_assign->STARTDATE));
                // $enddate = date('Y-m-d', strtotime($final_psite_promo_assign->ENDDATE));
                $startdate = $this->convert_in_indian_date_format($final_psite_promo_assign->STARTDATE);
                $enddate = $this->convert_in_indian_date_format($final_psite_promo_assign->ENDDATE);
                // echo $today.'<br>';
                // echo $startdate.' - '.$enddate.'<br>';
                if (($today >= $startdate) && ($today <= $enddate)) {
                    $promo_name = DB::table('manyavar.promo_master')->select('NAME')->where('CODE', $final_psite_promo_assign->PROMO_CODE)->first();
                    // echo '<tr><td class="text-center">'.$count++.'</td><td>'.$promo_name->NAME.'</td><td class="text-center">'.$final_psite_promo_assign->PRIORITY.'</td></tr>';
                    // echo 'Start Date :- '.$startdate.' End Date :- '.$enddate.' CODE :- '.$final_psite_promo_assign->PRIORITY.' - '.$final_psite_promo_assign->PROMO_CODE.'<br><br>';
                    array_push($final_promo_list, $value);
                }
            }
        }
        // dd($final_promo_list);
        // echo '</table>';
        $ccount = 1;
        $total_amount = 0;
        $total_qty = 0;
        $total_sale_price = 0;
        $total_promotion = 0;
        $total_basic_price = 0;
        $is_offer = 'No';
        $applied_offers = [];
        $available_offers = [];
        $review = [];

        // dd($taxmain);
        // dd($final_promo_list);
        if (count($final_promo_list) > 0) {
            $all_cart = $this->filter_promotions($final_promo_list, $qty, $item->MRP, $scode, $id);
            $is_offer = 'No';
            $is_offer_apply = false;
            // dd($all_cart['merge']);
            // return $all_cart;
            
            // dd($all_cart['merge']);
            $final_all_cat = array();
            foreach ($all_cart['merge'] as $key => $value) {
                foreach ($value as $key => $val) {
                    array_push($final_all_cat, $val);
                    if (array_key_exists('promo_code', $val) && $val['promotion'] != 0) {
                        $is_offer_apply = true;
                        $is_offer = 'Yes';
                        $promo_msg = DB::table('manyavar.promo_master')->where('CODE', $val['promo_code'])->first()->NAME;
                        $applied_offers[] = (object)[ 'message' => $promo_msg ];
                        removeElementWithValue($all_cart['promo_m_slab'], 'PROMO_CODE', $val['promo_code']);
                    }
                }
            }
            // dd($applied_offers);
            if ($is_offer_apply == false) {
                $available_offers = [];
            } else {
                foreach ($all_cart['promo_m_slab'] as $key => $value) {
                    $available_offers[] = (object)[ 'message' => $value->NAME ];
                }
            }

            
            //dd($final_all_cat);
            foreach ($final_all_cat as $key => $value) {
                $total_amount += $value['gross'];
                $total_qty += $value['qty'];
                if (array_key_exists('promotion', $value)) {
                    //$total_promotion += 0;
					$total_promotion += (float)$value['promotion'];
                } else {
					$total_promotion += 0;
                    //$total_promotion += $value['promotion'];
                }
                if (array_key_exists('sale_price', $value)) {
                    //$total_sale_price += 0;
                    if($value['sale_price'] > 0.00){
                       $total_sale_price += (float)$value['sale_price']; 
                   }else{
                        $total_sale_price += (float)$value['basic_price'];
                   }
					
                } else {
					$total_sale_price += 0;
                    //$total_sale_price += $value['sale_price'];
                }
                $total_basic_price += $value['basic_price'];
            }

            // $taxmain = DB::table('manyavar.invhsnsacmain as main')
            //     ->select('main.HSN_SAC_CODE', 'det.EFFECTIVE_DATE', 'det.CODE')
            //     ->join('manyavar.invhsnsacdet as det', 'main.CODE', '=', 'det.INVHSNSACMAIN_CODE')
            //     ->where('main.CODE', $item->INVHSNSACMAIN_CODE)
            //     ->first();



            // $taxslab = DB::table('manyavar.invhsnsacslab as slab')
            //         ->select('slab.AMOUNT_FROM', 'slab.INVGSTRATE_CODE','gst.TAX_NAME','gst.CGST_RATE','gst.SGST_RATE','gst.CESS_RATE','gst.IGST_RATE')
            //         ->join('manyavar.invgstrate as gst', 'slab.INVGSTRATE_CODE', 'gst.CODE')
            //         ->where('slab.INVHSNSACMAIN_CODE', '=', $item->INVHSNSACMAIN_CODE)
            //         ->where('slab.INVHSNSACDET_CODE', '=', $taxmain->CODE)
            //         ->get();

            // $final_all_cat['tax_details'] = $taxmain;
            // $review['slab'] = $taxslab;

            // $actual_price = 0;
            // $dividegst = 0;
            // $tax_amount = 0;

            // foreach ($taxslab as $key => $value) {
            //     if (round($value->AMOUNT_FROM) <= $total_amount) {
            //         $final_all_cat['apply_tax'] = $value;
            //         $dividegst = 100 + $value->IGST_RATE;
            //         $actual_price = $total_amount * 100;
            //         $actual_price = $actual_price / $dividegst;
            //         $final_all_cat['wihout_tax_price'] = number_format($actual_price, 2);
            //         $tax_amount = $total_amount - (float)$actual_price;
            //         $final_all_cat['tax_amount'] = number_format($tax_amount, 2);
            //     }
            // }
            // dd($applied_offers);
            //     $total_amount += $value['gross'];
            //     $total_qty += $value['qty'];
            //     $total_promotion += $value['promotion'];
            //     $total_sale_price += $value['sale_price'];
            //     $total_basic_price += $value['basic_price'];
            // }

        } else {
			
            $final_all_cat = '';
            $total_sale_price = $item->MRP * $qty;
            $total_amount += $item->MRP * $qty;
            $total_basic_price += $item->MRP * $qty;
            // $total_qty += $qty;
        }

        $vendorS = new VendorSettingController;
        $product_default_image = $vendorS->getProductDefaultImage(['v_id' => $v_id , 'trans_from' => $trans_from]);
		
        // dd($final_promo_list);
        if ($cart == 1) {
            $push_data = [
                'p_id' => $id,
                'category' => $item->CNAME1,
                'brand_name' => $item->CNAME2,
                'sub_categroy' => '',
                'p_name' => $id.' '.$group->GRPNAME,
                'offer' => $is_offer,
                'offer_data' => (object)[ 'applied_offers' => array_unique($applied_offers, SORT_REGULAR), 'available_offers' => array_unique($available_offers, SORT_REGULAR) ],
                'multiple_price_flag' => false,
                'multiple_mrp' => [ $this->format_and_string($item->MRP) ],
                'unit_mrp' => $this->format_and_string($item->MRP),
                'r_price' => $this->format_and_string($total_basic_price),
                's_price' => $this->format_and_string($total_amount),
                'discount' => $this->format_and_string($total_promotion),
                'varient' => '',
                'images' => $product_default_image,
                'description' => '',
                'deparment' => '',
                'barcode' => $id,
                'whishlist' => 'No',
                'weight_flag' => false,
                'quantity_change_flag' => true,
                'carry_bag_flag' => false
            ];
            // dd($push_data);
        } else {
            $push_data = [
                'p_id' => $id,
                'category' => $item->CNAME1,
                'brand_name' => $item->CNAME2,
                'sub_categroy' => '',
                'style_code' => '',
                'p_name' => $id.' '.$item->CNAME1,
                'offer' => $is_offer,
                'offer_data' => (object)[ 'applied_offers' => array_unique($applied_offers, SORT_REGULAR), 'available_offers' => array_unique($available_offers, SORT_REGULAR) ],
                'qty' => $qty,
                'multiple_price_flag' => false,
                'multiple_mrp' => [ $this->format_and_string($item->MRP) ],
                'unit_mrp' => $this->format_and_string($item->MRP),
                'r_price' => $this->format_and_string($total_basic_price),
                's_price' => $this->format_and_string($total_sale_price),
                'discount' => $this->format_and_string($total_promotion),
                'varient' => '',
                'images' => $product_default_image,
                'description' => '',
                'deparment' => '',
                'barcode' => $id,
                'pdata' => urlencode(json_encode($final_all_cat)),
                'review' => [],
                'whishlist' => 'No'
            ];
        }
        return $push_data;
    }

    public function filter_promotions($promos, $qty, $amt, $scode, $barcode)
    {
    	// dd($promos[0]['ASSORTMENT_CODE']);
        $data = $listing_promo = $final_product = $promo_carts = $regular_carts = array();
        $total_qty = $qty;
        $basic_price = $amt;
        $qunatity_check = array();
        $promo_list = implode(",", array_column($promos, 'PROMO_CODE'));
        $promo_list = explode(",", $promo_list);
        // echo $promo_list;
        // dd();
        $promo_m_priority = DB::table('manyavar.promo_master')
                        ->select('psite_promo_assign.PRIORITY','promo_master.NAME','promo_master.BASIS','psite_promo_assign.PROMO_CODE')
                        ->join('manyavar.psite_promo_assign', 'promo_master.CODE', '=', 'psite_promo_assign.PROMO_CODE')
                        ->where('psite_promo_assign.ADMSITE_CODE',$scode)
                        ->whereIn('promo_master.CODE', $promo_list)
                        ->orderBy('psite_promo_assign.PRIORITY', 'desc')
                        ->get();

        $promo_m_slab = DB::table('manyavar.promo_slab')
                        ->select('promo_slab.SLAB_CODE','promo_slab.PROMO_CODE','promo_slab.SIMPLE_FACTOR','promo_slab.SLAB_RANGE_FROM','promo_slab.SLAB_RANGE_TO','psite_promo_assign.PRIORITY','promo_master.NAME','promo_master.BASIS','psite_promo_assign.PROMO_CODE')
                        ->join('manyavar.psite_promo_assign', 'promo_slab.PROMO_CODE', '=', 'psite_promo_assign.PROMO_CODE')
                        ->join('manyavar.promo_master', 'promo_slab.PROMO_CODE', '=', 'promo_master.CODE')
                        ->where('psite_promo_assign.ADMSITE_CODE',$scode)
                        ->whereIn('promo_slab.PROMO_CODE', $promo_list)
                        ->orderBy('psite_promo_assign.PRIORITY', 'desc')
                        ->orderBy('promo_slab.SLAB_CODE', 'desc')
                        ->get();

        // dd($promo_m_slab);

        foreach ($promo_m_slab as $key => $value) {
            if ($value->BASIS == 'QSIMPLE') {
                $slab = $value->SIMPLE_FACTOR;
            } else if($value->BASIS == 'QSLAB') {
                $slab = $value->SLAB_RANGE_FROM;
            }
            $listing_promo[] = array(
                'PRIORITY' => $value->PRIORITY,
                'NAME' => $value->NAME,
                'TYPE' => $value->BASIS,
                'PROMO_CODE' => $value->PROMO_CODE,
                'SLAB_CODE' => $value->SLAB_CODE,
                'QTY' => (int)$slab
            );
        }
        // dd($listing_promo);
        rsort($listing_promo);
        // dd($listing_promo);
        $max = max(array_column($listing_promo, 'QTY'));
        // dd($max);
        while ($qty > 0) {
            if ($this->in_multiarray($qty, $listing_promo, "QTY")) {
                // echo 'FIND Qty :- '.$qty.'<br>';
                array_push($final_product,[ 'qty' => $qty, 'is_promo' => 1, 'o_promo' => 1 ]);
                $qty = $qty - $qty;
            } else {
                if ($qty > $max) {
                    // echo 'LARGE Qty :- '.$max.'<br>';
                    $qty = $qty - $max;
                    array_push($final_product,[ 'qty' => $max, 'is_promo' => 1, 'o_promo' => 0 ]);
                } else {
                    array_push($final_product,[ 'qty' => $qty, 'is_promo' => 0, 'o_promo' => 1 ]);
                    $qty = 0;
                }
            }
        }
        // dd($final_product);
        foreach ($final_product as $key => $value) {
            if ($value['is_promo'] == 1) {
                $id = $this->searchQty($value['qty'], $listing_promo);
                $promo_slab = DB::table('manyavar.promo_slab')
                                    ->select('promo_slab.*','promo_master.BASIS')
                                    ->join('manyavar.promo_master','promo_slab.PROMO_CODE','promo_master.CODE')
                                    ->where('SLAB_CODE', $id)
                                    ->first();

                if ($promo_slab->BASIS == 'QSLAB' || $promo_slab->BASIS == 'QSIMPLE') {
                    $regular_carts[] = $this->quantity_based($promo_slab, $basic_price, $value['qty'], $barcode, $promos[0]['ASSORTMENT_CODE']);
                }
            } elseif($value['o_promo'] == 1) {
            	// dd($listing_promo[0]['SLAB_CODE']);
            	$promo_slab = DB::table('manyavar.promo_slab')
                                    ->select('promo_slab.*','promo_master.BASIS','promo_master.BUY_ASSORTMENT_CODE')
                                    ->join('manyavar.promo_master','promo_slab.PROMO_CODE','promo_master.CODE')
                                    ->where('SLAB_CODE', $listing_promo[0]['SLAB_CODE'])
                                    ->first();
                if ($promo_slab->BASIS == 'QSLAB' || $promo_slab->BASIS == 'QSIMPLE') {
                    $regular_carts[] = $this->quantity_based($promo_slab, $basic_price, $value['qty'], $barcode, $promos[0]['ASSORTMENT_CODE']);
                }
            } else {
                $regular_carts[][] = array('basic_price' => $amt, 'promotion' => '', 'sale_price' => '', 'qty' => $value['qty'], 'gross' => $amt);
            }
        }
        // dd($regular_carts);
        $merge = array_merge($promo_carts, $regular_carts);
        // dd($merge
        $push_array = [ 'promo_m_slab' => $promo_m_slab, 'merge' => $merge ];
        return $push_array;
    }

    public function quantity_based($value, $mrp, $qty, $id, $sort)
    {
    	// dd($value);
        $sort_code = explode("-", $sort);
        $data = array();
        $basic_price = $promotion = $discount = $sale_price = $gross = '';
        switch ($value->DISCOUNT_TYPE) {
            case 'P':
                switch ($value->GET_BENEFIT_CODE) {
                    case '1':
                        $basic_price = $mrp;
                        $promotion = $basic_price * $value->DISCOUNT_FACTOR / 100;
                        $sale_price = $basic_price - $promotion;
                        $gross = $sale_price * $qty;
                        array_push($data, ['basic_price' => $basic_price, 'promotion' => $promotion * $qty, 'sale_price' => $sale_price, 'gross' => $gross, 'qty' => $qty, 'slab_code' => $value->SLAB_CODE , 'promo_code' => $value->PROMO_CODE ]);
                        break;
                    case '2':
                        $multiple_qty = $value->SIMPLE_FACTOR - $value->GET_FACTOR;
                        $basic_price = $mrp;
                        $promotion = 0;
                        $sale_price = 0;
                        $pool_promotion = $basic_price * $value->DISCOUNT_FACTOR / 100;
                        $pool_sale = $basic_price - $pool_promotion;
                        $pool_gross = $basic_price - $pool_promotion;
                        $gross = $basic_price;
                        // if ($value->DISCOUNT_FACTOR == '0.100') {
                            array_push($data, ['basic_price' => $basic_price * $value->GET_FACTOR, 'promotion' => $pool_promotion * $value->GET_FACTOR, 'sale_price' => $pool_sale * $value->GET_FACTOR * $value->GET_FACTOR, 'gross' => $pool_gross * $value->GET_FACTOR, 'qty' => $value->GET_FACTOR, 'slab_code' => $value->SLAB_CODE , 'promo_code' => $value->PROMO_CODE ]);
                            array_push($data, ['basic_price' => $basic_price * $multiple_qty, 'promotion' => $promotion * $multiple_qty, 'sale_price' => $mrp * $multiple_qty, 'gross' => $gross * $multiple_qty, 'qty' => $multiple_qty, 'slab_code' => $value->SLAB_CODE , 'promo_code' => $value->PROMO_CODE ]);
                        // }
                        
                        break;
                }
                break;
            case 'F':
                switch ($value->GET_BENEFIT_CODE) {
                    case '1':
                        $basic_price = $mrp;
                        $promotion = $basic_price * $qty - $value->DISCOUNT_FACTOR;
                        $sale_price = $value->DISCOUNT_FACTOR / $qty;
                        $gross = $sale_price * $qty;
                        array_push($data, ['basic_price' => $basic_price, 'promotion' => $promotion, 'sale_price' => $sale_price, 'gross' => $gross, 'qty' => $qty, 'slab_code' => $value->SLAB_CODE , 'promo_code' => $value->PROMO_CODE ]);
                        break;
                    case '2':
                        $multiple_qty = $value->SIMPLE_FACTOR - $value->GET_FACTOR;
                        $basic_price = $mrp;
                        $promotion = 0;
                        $sale_price = 0;
                        $pool_promotion = $basic_price - $value->DISCOUNT_FACTOR;
                        $pool_sale = $basic_price - $pool_promotion;
                        $pool_gross = $basic_price - $pool_promotion;
                        $gross = $basic_price;
                        if ($qty == 1) {
                        	// dd($sort_code);
                        	$promo_assortment_include = DB::table('manyavar.promo_assortment_include')->where('ASSORTMENT_CODE', $sort_code[0])->where('CODE', $sort_code[1])->first();
                        	// dd($promo_assortment_include);
                        	// dd(Auth::id());
                        	$check_cart = DB::table('cart')->where('user_id', Auth::id())->where('status', 'process')->where('item_id', $id)->get();
                        	// $check_cart_id = DB::table('cart')->where('user_id', Auth::id())->where('status', 'process')->where('item_id', $id)->first();
                        	// dd($check_cart);
                        	if (!$check_cart->isEmpty()) {
                        		foreach ($check_cart as $key => $cart_value) {
                        			// $get_product = DB::table('manyavar.invitem')->where('INVARTICLE_CODE', $promo_assortment_include->INVARTICLE_CODE)->where('ICODE', $id)->first();
                        			// dd($get_product);
                        			if ($cart_value->discount == '0.00' && $cart_value->item_id == $id) {
                        				// dd('ok');
                        				array_push($data, ['basic_price' => $cart_value->unit_mrp, 'promotion' => $cart_value->discount, 'sale_price' => $cart_value->total, 'gross' => $cart_value->total, 'qty' => $cart_value->qty, 'slab_code' => 0, 'promo_code' => 0 ]);
                        			} else {
                                        $getall_barcode = DB::table('manyavar.promo_assortment_include')->select('ICODE')->where('ASSORTMENT_CODE', $sort_code[0])->whereNotNull('ICODE')->get();
                                        $allbarcode = [];
                                        // dd($getall_barcode);
                                        foreach ($getall_barcode as $key => $getall_barcode_value) {
                                            $allbarcode[] = $getall_barcode_value->ICODE;
                                        }
                                        $get_product_from_article = DB::table('manyavar.invitem')->select('ICODE')->where('GRPCODE', $promo_assortment_include->DEPARTMENT_GRPCODE)->get();
                                        foreach ($get_product_from_article as $key => $get_product_from_article_value) {
                                            $allbarcode[] = $get_product_from_article_value->ICODE;
                                        }
                                        if (in_array($id, $allbarcode)) {
                                            array_push($data, ['basic_price' => $basic_price * $value->GET_FACTOR, 'promotion' => $pool_promotion * $value->GET_FACTOR, 'sale_price' => $pool_sale * $value->GET_FACTOR * $value->GET_FACTOR, 'gross' => $pool_gross * $value->GET_FACTOR, 'qty' => $value->GET_FACTOR, 'slab_code' => $value->SLAB_CODE , 'promo_code' => $value->PROMO_CODE ]);
                                        }
                        				// if (count($get_product) == 1) {
	                        			// 	array_push($data, ['basic_price' => $basic_price * $value->GET_FACTOR, 'promotion' => $pool_promotion * $value->GET_FACTOR, 'sale_price' => $pool_sale * $value->GET_FACTOR * $value->GET_FACTOR, 'gross' => $pool_gross * $value->GET_FACTOR, 'qty' => $value->GET_FACTOR, 'slab_code' => $value->SLAB_CODE , 'promo_code' => $value->PROMO_CODE ]);
	                        			// }
                        			}
	                        	}
	                  
                        	} else {
                        		// dd('oj');
                        		$check_cart = DB::table('cart')->where('user_id', Auth::id())->where('status', 'process')->get();
                                // dd($check_cart);
                        		if (!$check_cart->isEmpty()) {
                                    $getall_barcode = DB::table('manyavar.promo_assortment_include')->select('ICODE')->where('ASSORTMENT_CODE', $sort_code[0])->whereNotNull('ICODE')->get();
                                    $allbarcode = [];
                                    // dd($getall_barcode);
                                    foreach ($getall_barcode as $key => $getall_barcode_value) {
                                        $allbarcode[] = $getall_barcode_value->ICODE;
                                    }
                                    $get_product_from_article = DB::table('manyavar.invitem')->select('ICODE')->where('GRPCODE', $promo_assortment_include->DEPARTMENT_GRPCODE)->get();
                                    foreach ($get_product_from_article as $key => $get_product_from_article_value) {
                                        $allbarcode[] = $get_product_from_article_value->ICODE;
                                    }
                                    // dd($allbarcode);

                                    if (in_array($id, $allbarcode)) {
                                        if ($value->DISCOUNT_FACTOR == '0.100') {
                                            array_push($data, ['basic_price' => $basic_price, 'promotion' => $pool_promotion, 'sale_price' => $pool_sale * $value->GET_FACTOR * $value->GET_FACTOR, 'gross' => $pool_gross * $value->GET_FACTOR, 'qty' => $qty, 'slab_code' => $value->SLAB_CODE , 'promo_code' => $value->PROMO_CODE ]);
                                         }
                                    } else {
                                        array_push($data, ['basic_price' => $basic_price * $multiple_qty, 'promotion' => $promotion * $multiple_qty, 'sale_price' => $mrp * $multiple_qty, 'gross' => $gross * $multiple_qty, 'qty' => $multiple_qty, 'slab_code' => $value->SLAB_CODE , 'promo_code' => $value->PROMO_CODE ]);
                                    }
                        			
	                        		// if (count($get_product) == 1) {
	                        		// 	if ($value->DISCOUNT_FACTOR == '0.100') {
	                        		// 		array_push($data, ['basic_price' => $basic_price, 'promotion' => $pool_promotion, 'sale_price' => $pool_sale * $value->GET_FACTOR * $value->GET_FACTOR, 'gross' => $pool_gross * $value->GET_FACTOR, 'qty' => $qty, 'slab_code' => $value->SLAB_CODE , 'promo_code' => $value->PROMO_CODE ]);
	                        		// 	}
	                        		// 	// array_push($data, ['basic_price' => $basic_price * $value->GET_FACTOR, 'promotion' => $pool_promotion * $value->GET_FACTOR, 'sale_price' => $pool_sale * $value->GET_FACTOR * $value->GET_FACTOR, 'gross' => $pool_gross * $value->GET_FACTOR, 'qty' => $value->GET_FACTOR, 'slab_code' => $value->SLAB_CODE , 'promo_code' => $value->PROMO_CODE ]);
	                        		// } else {
	                        		// 	array_push($data, ['basic_price' => $basic_price * $multiple_qty, 'promotion' => $promotion * $multiple_qty, 'sale_price' => $mrp * $multiple_qty, 'gross' => $gross * $multiple_qty, 'qty' => $multiple_qty, 'slab_code' => $value->SLAB_CODE , 'promo_code' => $value->PROMO_CODE ]);
	                        		// }
                        		} else {
                        			array_push($data, ['basic_price' => $basic_price * $multiple_qty, 'promotion' => $promotion * $multiple_qty, 'sale_price' => $mrp * $multiple_qty, 'gross' => $gross * $multiple_qty, 'qty' => $multiple_qty, 'slab_code' => $value->SLAB_CODE , 'promo_code' => $value->PROMO_CODE ]);
                        		}
                        		// dd('okj');
                        		
                        	}
                        	
                        	
                        // 	// dd($get_product);
                        } else {
                        	if ($value->DISCOUNT_FACTOR == '0.100') {
	                            array_push($data, ['basic_price' => $basic_price * $value->GET_FACTOR, 'promotion' => $pool_promotion * $value->GET_FACTOR, 'sale_price' => $pool_sale * $value->GET_FACTOR * $value->GET_FACTOR, 'gross' => $pool_gross * $value->GET_FACTOR, 'qty' => $value->GET_FACTOR, 'slab_code' => $value->SLAB_CODE , 'promo_code' => $value->PROMO_CODE ]);
	                            array_push($data, ['basic_price' => $basic_price * $multiple_qty, 'promotion' => $promotion * $multiple_qty, 'sale_price' => $mrp * $multiple_qty, 'gross' => $gross * $multiple_qty, 'qty' => $multiple_qty, 'slab_code' => $value->SLAB_CODE , 'promo_code' => $value->PROMO_CODE ]);
	                        }
                        }
                        
                        break;
                }
                break;
        }
        // dd($data);
        return $data;
        // return [ 'basic_price' => $basic_price, 'promotion' => $promotion, 'sale_price' => $sale_price, 'gross' => $gross ];
    }

    public function searchQty($id, $array) 
    {
        foreach ($array as $key => $value) {
            if ($value['QTY'] == $id) {
                return $value['SLAB_CODE'];
            }
        }
        return null;
    }

    function in_multiarray($elem, $array,$field)
    {
        $top = sizeof($array) - 1;
        $bottom = 0;
        while($bottom <= $top)
        {
            if($array[$bottom][$field] == $elem)
                return true;
            else 
                if(is_array($array[$bottom][$field]))
                    if(in_multiarray($elem, ($array[$bottom][$field])))
                        return true;

            $bottom++;
        }        
        return false;
    }

    public function final_cross_check($id, $item, $group, $article, $division, $section, $column, $qid)
    {
        // dd($id);
        $data = array();
        $group = $group;
        $article = $article;
        $division = $division;
        $section = $section;
        $total = array();
        $etotal = array();
        $promo_assortment_include = DB::table('manyavar.promo_assortment_include')->where('ASSORTMENT_CODE', $id)->where($column, $qid)->first();
        $promo_assortment_exclude = DB::table('manyavar.promo_assortment_exclude')->where('ASSORTMENT_CODE', $id)->where($column, $qid)->first();
        $fdivision = $fsection = $fdepartment = $farticle = $ficode = $fcategory_1 = $fcategory_2 = $fcategory_3 = $fcategory_4 = $fcategory_5 = $fcategory_6 = '';
        $data['ASSORTMENT_CODE'] = $id;

        if(!empty($promo_assortment_include->DIVISION_GRPCODE)) {
            // $data['DIVISION_GRPCODE'] = $division;
            if ($division == $promo_assortment_include->DIVISION_GRPCODE) {
                array_push($total, 2);
            } else {
                array_push($total, 1);
            }
        } 
        if(!empty($promo_assortment_include->SECTION_GRPCODE)) {
            // $data['SECTION_GRPCODE'] = $section;
            if ($section == $promo_assortment_include->SECTION_GRPCODE) {
                array_push($total, 2);
            } else {
                array_push($total, 1);
            }
        }
        if(!empty($promo_assortment_include->DEPARTMENT_GRPCODE)) {
            // $data['DEPARTMENT_GRPCODE'] = $group;
            if ($group == $promo_assortment_include->DEPARTMENT_GRPCODE) {
                array_push($total, 2);
            } else {
                array_push($total, 1);
            }
        }
        if(!empty($promo_assortment_include->INVARTICLE_CODE)) {
            // $data['INVARTICLE_CODE'] = $article;
            if ($article == $promo_assortment_include->INVARTICLE_CODE) {
                array_push($total, 2);
            } else {
                array_push($total, 1);
            }
        }
        if(!empty($promo_assortment_include->ICODE)) {
            // $data['ICODE'] = $item->ICODE;
            if ($item->ICODE == $promo_assortment_include->ICODE) {
                array_push($total, 2);
            } else {
                array_push($total, 1);
            }
        }

        if(!empty($promo_assortment_include->CCODE1)) {
            // $data['CCODE1'] = $item->CCODE1;
            if ($item->CCODE1 == $promo_assortment_include->CCODE1) {
                array_push($total, 2);
            } else {
                array_push($total, 1);
            }
        }

        if(!empty($promo_assortment_include->CCODE2)) {
            // $data['CCODE2'] = $item->CCODE2;
            if ($item->CCODE2 == $promo_assortment_include->CCODE2) {
                array_push($total, 2);
            } else {
                array_push($total, 1);
            }
        }

        if(!empty($promo_assortment_include->CCODE3)) {
            // $data['CCODE3'] = $item->CCODE3;
            if ($item->CCODE3 == $promo_assortment_include->CCODE3) {
                array_push($total, 2);
            } else {
                array_push($total, 1);
            }
        }

        if(!empty($promo_assortment_include->CCODE4)) {
            // $data['CCODE4'] = $item->CCODE4;
            if ($item->CCODE4 == $promo_assortment_include->CCODE4) {
                array_push($total, 2);
            } else {
                array_push($total, 1);
            }
        }

        if(!empty($promo_assortment_include->CCODE5)) {
            // $data['CCODE5'] = $item->CCODE5;
            if ($item->CCODE5 == $promo_assortment_include->CCODE5) {
                array_push($total, 2);
            } else {
                array_push($total, 1);
            }
        }

        if(!empty($promo_assortment_include->CCODE6)) {
            // $data['CCODE6'] = $item->CCODE6;
            if ($item->CCODE6 == $promo_assortment_include->CCODE6) {
                array_push($total, 2);
            } else {
                array_push($total, 1);
            }
        }

        if (!empty($promo_assortment_include->STOCKINDATE_FROM) && !empty($promo_assortment_include->STOCKINDATE_TO)) {
            $genrated = date('Y-m-d', strtotime($item->GENERATED));
            $stock_from = $this->convert_in_indian_date_format($promo_assortment_include->STOCKINDATE_FROM);
            $stock_to = $this->convert_in_indian_date_format($promo_assortment_include->STOCKINDATE_TO);
            if (($genrated >= $stock_from) && ($genrated <= $stock_to)) {
                array_push($total, 2);
            } else {
                array_push($total, 1);
            }
        }

        if (!empty($promo_assortment_include->PRICE_RANGE_FROM) && !empty($promo_assortment_include->PRICE_RANGE_TO)) {
            if ($item->MRP >= $promo_assortment_include->PRICE_RANGE_FROM && $item->MRP <= $promo_assortment_include->PRICE_RANGE_TO) {
                array_push($total, 2);
            } else {
                array_push($total, 1);
            }
        }

        if(!empty($promo_assortment_exclude->DIVISION_GRPCODE)) {
            // $data['DIVISION_GRPCODE'] = $division;
            if ($division == $promo_assortment_exclude->DIVISION_GRPCODE) {
                array_push($etotal, 1);
            } else {
                array_push($etotal, 2);
            }
        } 
        if(!empty($promo_assortment_exclude->SECTION_GRPCODE)) {
            // $data['SECTION_GRPCODE'] = $section;
            if ($section == $promo_assortment_exclude->SECTION_GRPCODE) {
                array_push($etotal, 1);
            } else {
                array_push($etotal, 2);
            }
        }
        if(!empty($promo_assortment_exclude->DEPARTMENT_GRPCODE)) {
            // $data['DEPARTMENT_GRPCODE'] = $group;
            if ($group == $promo_assortment_exclude->DEPARTMENT_GRPCODE) {
                array_push($etotal, 1);
            } else {
                array_push($etotal, 2);
            }
        }
        if(!empty($promo_assortment_exclude->INVARTICLE_CODE)) {
            // $data['INVARTICLE_CODE'] = $article;
            if ($article == $promo_assortment_exclude->INVARTICLE_CODE) {
                array_push($etotal, 1);
            } else {
                array_push($etotal, 2);
            }
        }
        if(!empty($promo_assortment_exclude->ICODE)) {
            // $data['ICODE'] = $item->ICODE;
            if ($item->ICODE == $promo_assortment_exclude->ICODE) {
                array_push($etotal, 1);
            } else {
                array_push($etotal, 2);
            }
        }

        if(!empty($promo_assortment_exclude->CCODE1)) {
            // $data['CCODE1'] = $item->CCODE1;
            if ($item->CCODE1 == $promo_assortment_exclude->CCODE1) {
                array_push($etotal, 1);
            } else {
                array_push($etotal, 2);
            }
        }

        if(!empty($promo_assortment_exclude->CCODE2)) {
            // $data['CCODE2'] = $item->CCODE2;
            if ($item->CCODE2 == $promo_assortment_exclude->CCODE2) {
                array_push($etotal, 1);
            } else {
                array_push($etotal, 2);
            }
        }

        if(!empty($promo_assortment_exclude->CCODE3)) {
            // $data['CCODE3'] = $item->CCODE3;
            if ($item->CCODE3 == $promo_assortment_exclude->CCODE3) {
                array_push($etotal, 1);
            } else {
                array_push($etotal, 2);
            }
        }

        if(!empty($promo_assortment_exclude->CCODE4)) {
            // $data['CCODE4'] = $item->CCODE4;
            if ($item->CCODE4 == $promo_assortment_exclude->CCODE4) {
                array_push($etotal, 1);
            } else {
                array_push($etotal, 2);
            }
        }

        if(!empty($promo_assortment_exclude->CCODE5)) {
            // $data['CCODE5'] = $item->CCODE5;
            if ($item->CCODE5 == $promo_assortment_exclude->CCODE5) {
                array_push($etotal, 1);
            } else {
                array_push($etotal, 2);
            }
        }

        if(!empty($promo_assortment_exclude->CCODE6)) {
            // $data['CCODE6'] = $item->CCODE6;
            if ($item->CCODE6 == $promo_assortment_exclude->CCODE6) {
                array_push($etotal, 1);
            } else {
                array_push($etotal, 2);
            }
        }

        if (!empty($promo_assortment_exclude->STOCKINDATE_FROM) && !empty($promo_assortment_exclude->STOCKINDATE_TO)) {
            $genrated = date('Y-m-d', strtotime($item->GENERATED));
            $stock_from = $this->convert_in_indian_date_format($promo_assortment_exclude->STOCKINDATE_FROM);
            $stock_to = $this->convert_in_indian_date_format($promo_assortment_exclude->STOCKINDATE_TO);
            if (($genrated >= $stock_from) && ($genrated <= $stock_to)) {
                array_push($etotal, 1);
            } else {
                array_push($etotal, 2);
            }
        }

        if (!empty($promo_assortment_exclude->PRICE_RANGE_FROM) && !empty($promo_assortment_exclude->PRICE_RANGE_TO)) {
            if ($item->MRP >= $promo_assortment_exclude->PRICE_RANGE_FROM && $item->MRP <= $promo_assortment_exclude->PRICE_RANGE_TO) {
                array_push($etotal, 1);
            } else {
                array_push($etotal, 2);
            }
        }
        

        // if(!empty($promo_assortment_include->DESC1)) {
        //  $data['DESC1'] = $item->DESC1;
        // }
        // if(!empty($promo_assortment_include->DESC2)) {
        //  $data['DESC2'] = $item->DESC2;
        // }
        // if(!empty($promo_assortment_include->DESC3)) {
        //  $data['DESC3'] = $item->DESC3;
        // }
        // if(!empty($promo_assortment_include->DESC4)) {
        //  $data['DESC4'] = $item->DESC4;
        // }
        // if(!empty($promo_assortment_include->DESC5)) {
        //  $data['DESC5'] = $item->DESC5;
        // }
        // if(!empty($promo_assortment_include->DESC6)) {
        //  $data['DESC6'] = $item->DESC6;
        // }
        // echo '<pre>';
        // print_r($data);
        // $total = round($fdivision + $fsection + $fdepartment);
        // $total = round($fdivision + $fsection + $fdepartment + $farticle + $ficode + $fcategory_1 + $fcategory_2 + $fcategory_3 + $fcategory_4 + $fcategory_5 + $fcategory_6);
        // echo 'Total :- '.implode(",", $total).' -- '.$id.'<br><br>';
        // echo 'Division :- '.$promo_assortment_include->DIVISION_GRPCODE.' :- '.$fdivision.'<br>';
        // echo 'Section :- '.$promo_assortment_include->SECTION_GRPCODE.' :- '.$fsection.'<br>';
        // echo 'Department :- '.$promo_assortment_include->DEPARTMENT_GRPCODE.' :- '.$fdepartment.'<br>';
        // echo 'Article :- '.$promo_assortment_include->INVARTICLE_CODE.' :- '.$farticle.'<br>';
        // echo '-------------------------------------------------------';
        // $final_promo_assortment_include = DB::table('promo_assortment_include')
        //                      ->where(function($q) use ($data) {
        //                          foreach ($data as $key => $value) {
        //                              $q->where($key, '=', $value);
        //                          }
        //                      })
        //                      ->first();
        // dd($data);
        // if (count($final_promo_assortment_include) > 0) {
        //  return $final_promo_assortment_include->ASSORTMENT_CODE;
        // }
        // print_r($final_promo_assortment_include);
        // echo '<pre>';
        $total = array_count_values($total);
        // echo $total[1];
        // print_r($column);
        // print_r($id);
        // print_r($total);
        // echo '</pre>';

        // if ($total == 2) {
        //  return $id;
        // }

        // dd();
        if (count($total) == 1) {
            // if ($total[1] == 2) {
            //  return $id;
            // }
            foreach ($total as $key => $value) {
                if ($key == 2) {
                    // if (end($etotal) == 2) {
                        return $id.'-'.$promo_assortment_include->CODE;
                    // }
                }
            }
            // print_r($total);
        } 
        // else {
        //  if ($total[2] > $total[1]) {
        //      return $id;
        //  }
        // }
    }

    public function convert_in_indian_date_format($date)
    {
        if (strpos($date, "-") !== false) {
            $fdate = explode("-", $date);
        } else if (strpos($date, "/") !== false) {
            $fdate = explode("/", $date);
        }
        // print_r($fdate);
        return date('Y-m-d', strtotime($fdate[2].'-'.$fdate[0].'-'.$fdate[1]));
    }

    function format_and_string($value)
    {
        return (string)sprintf('%0.2f', $value);
    }
}
