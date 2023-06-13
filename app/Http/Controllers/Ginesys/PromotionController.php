<?php

namespace App\Http\Controllers\Ginesys;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VendorSettingController;
use App\Http\Interfaces\PromotionInterface;
use Auth;
use DB;
use Carbon\Carbon;
use App\Store;

class PromotionController extends Controller implements PromotionInterface {

	/**
	 * 1) Item is not linked with store
	 * 2) Division, section are getting from name , it should be get from id
	 */
	private $store_db_name;

	public function index($params)
	{
		// dd($params);
		$final_data = [];
		$tdata=null;
		if(isset($params['store_db_name']) ) {
            $this->store_db_name = $params['store_db_name'];
        } else if(isset($params['store_id']) ) {
            $this->store_db_name = get_store_db_name( [ 'store_id' => $params['store_id'] ] );
        }

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

		$item = $params['item'];


		$v_id = $params['v_id'];
		$trans_from = $params['trans_from'];
		$id = $params['barcode'];
		$qty = $params['qty'];
		$mapping_store_id = $params['mapping_store_id'];
		$carts = $params['carts'];
		$is_offer = 'No';
		$total_baisc_value = $total_discount = $total_gross = $total_qty = 0;
		$available_offer = [];
		$applied_offer = [];
		// dd($item);
		$item = $params['item'] = $this->getItemOfferDetails($params);
		// dd($carts);
		if ($params['is_cart'] == 0) {
			$cart_items = collect($carts)->transform(function ($cartitem, $key) use($item, &$qty) {
				// dd($item->ICODE);
	            if($cartitem->item_id == $item->ICODE) {//If item is exists then incrementing the current carts
	                $qty = $cartitem->qty + 1;
	                return ['barcode' => $cartitem->barcode, 'item_id' => $cartitem->item_id , 'qty' => $qty , 'unit_mrp' => $cartitem->unit_mrp, 'unit_rsp' => $cartitem->unit_csp, 'division_id' => $cartitem->division_id, 'section_id' => $cartitem->group_id, 'department_id' => $cartitem->department_id, 'article_id' => $cartitem->subclass_id  ];
	            } else {
	               return ['barcode' => $cartitem->barcode, 'item_id' => $cartitem->item_id , 'qty' => $cartitem->qty , 'unit_mrp' => $cartitem->unit_mrp, 'unit_rsp' => $cartitem->unit_csp, 'division_id' => $cartitem->division_id, 'section_id' => $cartitem->group_id, 'department_id' => $cartitem->department_id, 'article_id' => $cartitem->subclass_id]; 
	            }
	        });
	        if ($qty == 1) {
	        	$pqty = 1;
	        } else {
	        	$pqty = $qty;
	        }
	        $item_val = $cart_items->where('item_id',  $item->ICODE)->first();
	        if(!$item_val){
	            $cart_items->push(['barcode' => $item->BARCODE, 'item_id' =>  $item->ICODE , 'qty' => $pqty , 'unit_mrp' => $item->LISTED_MRP , 'unit_rsp' => $item->MRP, 'division_id' => $item->DIVISION_CODE, 'section_id' => $item->SECTION_CODE, 'department_id' => $item->DEPARTMENT_CODE, 'article_id' => $item->ARTICLE_CODE ]);
	        }
		} elseif ($params['is_cart'] == 1) {
			$update = $params['is_update'];
			$cart_items = collect($carts->toArray())->transform(function ($cartitem, $key) use($update, $qty, $id) {
				// dd($id);
				if ($update == 1 && $cartitem['barcode'] == $id) {
					$qty = $qty;
				} else {
					$qty = $cartitem['qty'];
				}
				return ['barcode' => $cartitem['barcode'], 'item_id' => $cartitem['item_id'] , 'qty' => $qty, 'unit_mrp' => $cartitem['unit_mrp'], 'unit_rsp' => $cartitem['unit_csp'], 'division_id' => $cartitem['division_id'], 'section_id' => $cartitem['group_id'], 'department_id' => $cartitem['department_id'], 'article_id' => $cartitem['subclass_id'] ];
	        });
			// dd($cart_items);
	        // $item_val = $cart_items->where('item_id',  $item->ICODE)->first();
	        // if(!$item_val){
	        //     $cart_items->push(['barcode' => $item->BARCODE, 'item_id' =>  $item->ICODE , 'qty' => 1 , 'unit_mrp' => $item->LISTED_MRP , 'unit_rsp' => $item->MRP, 'division_id' => $item->DIVISION_CODE, 'section_id' => $item->SECTION_CODE, 'department_id' => $item->DEPARTMENT_CODE, 'article_id' => $item->ARTICLE_CODE ]);
	        // }
		}
        $params['cart_items'] = $cart_items;

        // dd($cart_items);
        
		$allPromotions = $this->getAllPromotions($params);
		foreach( $allPromotions['available_offers'] as $key => $value ) {
			$available_offer[] = ["message" => $value ];
		}
		$allPromotions = $params['promo'] = $allPromotions['offer'];

		$params['promo'] = $allPromotions;
		//dd($allPromotions);

		if (empty($allPromotions)) {
			$cart_items = collect($cart_items)->where('item_id', $params['barcode']);
			$pdata = [];
			foreach ($cart_items as $cart) {
				$pdata[] = [ 'item_id' => $cart['item_id'], 'unit_mrp' => $cart['unit_mrp'], 'unit_rsp' => $cart['unit_rsp'], 'discount' => 0, 'sub_total' => $cart['unit_rsp'], 'total' => $cart['unit_rsp'], 'message' => '', 'discount_price_basis' => '', 'qty' => $cart['qty'], 'promo_code'=> '', 'no' => '', 'start_date' => '', 'end_date' => ''];

				$total_baisc_value += $cart['unit_mrp'] * $cart['qty'];
				$total_discount += 0;
				$total_gross += $total_baisc_value;
				$total_qty += $cart['qty'];
			}
			$allPromotions = $pdata;

		} else {
			foreach ($allPromotions as $val) {
				if (empty($val['discount'])) {
					//$available_offer[]['message'] = $val['message'];
				} else {
					$applied_offer[]['message'] = $val['message'];
				}
				$total_baisc_value += $val['sub_total'];
				$total_discount += $val['discount'];
				$total_gross += $val['total'];
				$total_qty += $val['qty'];
			}

		}
		// dd($total_qty);

		if ($total_discount > 0) {
			$is_offer = 'Yes';
		} else {
			$is_offer = 'No';
		}

		// dd($is_offer);
		if($params['is_cart'] == 1){
		foreach ($params['carts'] as $key => $value) {
			$tdata = $value['tdata'];
		}
		}else{$tdata='';}
		// dd($available_offer);
		$product_desc = "";
		if($v_id = 23){$product_desc = $item->CNAME1;}else{$product_desc = $item->DEPARTMENT_NAME;}

		// if($params['barcode'] == 'MA3754'){
		// 	dd($item);
		// }
		$final_data = [
			'p_id'					=> $params['barcode'],
			'category'				=> $item->CNAME1,
			'brand_name'			=> $item->CNAME2,
			'sub_categroy'			=> '',
			'p_name'				=> $params['barcode'].' '.$product_desc,
			'offer'					=> $is_offer,
			'offer_data' 			=> (object)['applied_offers' => array_values(array_unique($applied_offer, SORT_REGULAR)), 'available_offers' => array_unique($available_offer, SORT_REGULAR)],
			'qty'					=> $total_qty,
			'multiple_price_flag'	=> false,
			'multiple_mrp'			=> [format_number($item->LISTED_MRP)],
			'unit_mrp'				=> format_number($item->LISTED_MRP),
			'unit_rsp'				=> format_number($item->MRP),
			'r_price'				=> format_number($total_baisc_value),
			's_price'				=> format_number($total_gross),
			'discount'				=> format_number($total_discount),
			'varient'				=> '',
			'images'				=> '',
			'description'			=> '',
			'deparment'				=> '',
			'barcode'				=> $params['barcode'],
			'pdata'					=> urlencode(json_encode($allPromotions)),
			'tdata'					=> $tdata,
			'review'				=> '',
			'item_det'				=> urlencode(json_encode($item,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)),
			'whishlist'				=> 'No'
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
		// $mapping_store_id = $params['mapping_store_id'];

		$article = DB::table($this->store_db_name.'.invarticle')->select('CODE', 'NAME')->where('CODE', $item->INVARTICLE_CODE)->first();
		$group = DB::table($this->store_db_name.'.invgrp')->select('LEV1GRPNAME', 'LEV2GRPNAME', 'GRPCODE', 'GRPNAME', 'PARCODE')->where('GRPCODE', $item->GRPCODE)->first();
		$section = DB::table($this->store_db_name.'.invgrp')->select('GRPCODE', 'GRPNAME','PARCODE')->where('GRPCODE', $group->PARCODE)->first();
		$division = DB::table($this->store_db_name.'.invgrp')->select('GRPCODE', 'GRPNAME')->where('GRPCODE', $section->PARCODE)->first();
		
		//$admsite = DB::table('vmart.admsite')->select('NAME')->where('CODE', $mapping_store_id)->first();
		$item->DIVISION_CODE = $division->GRPCODE;
		$item->SECTION_CODE = $section->GRPCODE;
		$item->DEPARTMENT_CODE = $item->GRPCODE;
		$item->ARTICLE_CODE = isValueExists($article, 'CODE');
		$item->DEPARTMENT_NAME = $group->GRPNAME;
		$item->ARTICLE_NAME = $article->NAME;
		$item->SECTION_NAME = $section->GRPNAME;
		$item->DIVISION_NAME = $division->GRPNAME;
		
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
		date_default_timezone_set('Asia/Kolkata');
		$promo_list = array();
		$barcode_promo_list = array();
		$assortment_data = array();
		// Get all promotions of requested store & filter from psite_ptomo_assign table
		$store_promo_list = DB::table($this->store_db_name.'.psite_promo_assign as ppa')
				->select('ppa.PROMO_CODE','ppa.STARTDATE', 'ppa.ENDDATE', 'pb.ASSORTMENT_CODE', 'ppa.PRIORITY')
				->join($this->store_db_name.'.promo_buy as pb', 'pb.PROMO_CODE', 'ppa.PROMO_CODE')
				// ->join($this->store_db_name.'.promo_assortment_include as pai', 'pai.ASSORTMENT_CODE', 'pb.ASSORTMENT_CODE')
				// ->leftJoin($this->store_db_name.'.promo_assortment_exclude as pae', 'pae.ASSORTMENT_CODE', 'pb.ASSORTMENT_CODE')
				->where('ppa.ADMSITE_CODE', $params['mapping_store_id'])
				->where('ppa.STATUS', 'A')->get();

		// dd($store_promo_list);

		// Get all assortment include data from assortment_code

		foreach ($store_promo_list as $key => $value) {
			$startdate = $this->convert_in_indian_date_format($value->STARTDATE);
			$enddate = $this->convert_in_indian_date_format($value->ENDDATE);
			if (($current_date >= $startdate) && ($current_date <= $enddate)) {
				// echo $value->PROMO_CODE.'<br />';
				// dd($value);
				if (!empty($this->promotionDerivation($value, $params['item']))) {
					$promo_list[] = [ 'PROMO_CODE' => $value->PROMO_CODE, 'PRIORITY' => $value->PRIORITY,'start_date'=>$value->STARTDATE,'end_date'=> $value->ENDDATE,'ASSORTMENT_CODE' => $this->promotionDerivation($value, $params['item'])[0]];
				}
			} else {
				unset($assortment_data[$key]);
			}
		}

		// dd($promo_list);
		// $promo_list = array_collapse($promo_list);
		// dd($promo_list);

		// Exclude expire promotion & get all assortment code
		// foreach ($assortment_data as $key => $value) {
		// 	$promo = $value['promo'];
		// 	$startdate = $this->convert_in_indian_date_format($promo->STARTDATE);
		// 	$enddate = $this->convert_in_indian_date_format($promo->ENDDATE);
		// 	if (($current_date >= $startdate) && ($current_date <= $enddate)) {
		// 		if (!empty($this->promotionDerivation($value, $params['item']))) {
		// 			$promo_list[] = $this->promotionDerivation($value, $params['item']);;
		// 		}
		// 	} else {
		// 		unset($assortment_data[$key]);
		// 	}
		// }

		// dd($promo_list);
		// Get all promotion condition

		foreach ($promo_list as $key => $value) {
			$promo_master = DB::table($this->store_db_name.'.promo_master')
				->where('code', $value['PROMO_CODE'])
				->where('TYPE', 'I')
				->first();


				// dd($promo_master);
				// $promo_slab = DB::table($this->store_db_name.'.promo_slab')->where('PROMO_CODE', $value->PROMO_CODE)->get();
				//dd($promo_master);
				if($promo_master  != null){
					$barcode_promo_list[$value['PROMO_CODE']] = array(
						'promo_code'		=> $value['PROMO_CODE'],
						'assortment_code'	=> $value['ASSORTMENT_CODE'],
						'priority'			=> $value['PRIORITY'],
						'type'				=> $promo_master->TYPE,
						'basis'				=> $promo_master->BASIS,
						'buy_factor_type'	=> $promo_master->BUY_FACTOR_TYPE,
						'barcode'			=> $params['barcode'],
						'promo_name'		=> $promo_master->NAME,
						'no'				=> $promo_master->NO,
						'promo_summary'		=> $promo_master->PROMO_SUMMARY,
						'start_date'        => $value['start_date'],
						'end_date'			=> $value['end_date']
						// 'promo_slab'		=> $promo_slab
					);
				}
				
		}

		$params['promotions'] = $barcode_promo_list;
		//dd($barcode_promo_list);
		return $this->calculatingAllPromotions($params);

	}


	/**
	 * filtering all offer based on validation such date or status 
	 * 
	 * @param  Array $params 
	 * @return Array|Object All Offer of paticular items
	 */
	public function filterAllPromotions($params)
	{
		dd($params);
	}

	public function promotionDerivation($params, $item)
	{
		// dd($item);
		$promo_assortment_include = DB::table($this->store_db_name.'.promo_assortment_include')->where('ASSORTMENT_CODE', $params->ASSORTMENT_CODE)->get();
		$idata = [];
		$edata = [];
		$iassort = [];
		$eassort = [];
		$return_data = [];
		foreach ($promo_assortment_include as $key => $value) {
			$idata['inc_division'] = $this->includeMatchCheckNullCheck($item->DIVISION_CODE, $value->DIVISION_GRPCODE);
			$idata['inc_section'] = $this->includeMatchCheckNullCheck($item->SECTION_CODE, $value->SECTION_GRPCODE);
			$idata['inc_department'] = $this->includeMatchCheckNullCheck($item->DEPARTMENT_CODE, $value->DEPARTMENT_GRPCODE);
			$idata['inc_article'] = $this->includeMatchCheckNullCheck($item->ARTICLE_CODE, $value->INVARTICLE_CODE);
			$idata['inc_icode'] = $this->includeMatchCheckNullCheck($item->ICODE, $value->ICODE);
			$idata['inc_category_1'] = $this->includeMatchCheckNullCheck($item->CCODE1, $value->CCODE1);
			$idata['inc_category_2'] = $this->includeMatchCheckNullCheck($item->CCODE2, $value->CCODE2);
			$idata['inc_category_3'] = $this->includeMatchCheckNullCheck($item->CCODE3, $value->CCODE3);
			$idata['inc_category_4'] = $this->includeMatchCheckNullCheck($item->CCODE4, $value->CCODE4);
			$idata['inc_category_5'] = $this->includeMatchCheckNullCheck($item->CCODE5, $value->CCODE5);
			$idata['inc_category_6'] = $this->includeMatchCheckNullCheck($item->CCODE6, $value->CCODE6);
			$idata['inc_stock_check'] = $this->includeMatchCheckNullOrDateCheck($item->GENERATED, $value);
			$idata['inc_price_range'] = $this->includeMatchCheckNullOrRangerCheck($item->LISTED_MRP, $value);
			$idata['inc_desc_1'] = $this->includeMatchCheckNullCheck($item->DESC1, $value->DESC1);
			$idata['inc_desc_2'] = $this->includeMatchCheckNullCheck($item->DESC2, $value->DESC2);
			$idata['inc_desc_3'] = $this->includeMatchCheckNullCheck($item->DESC3, $value->DESC3);
			$idata['inc_desc_4'] = $this->includeMatchCheckNullCheck($item->DESC4, $value->DESC4);
			$idata['inc_desc_5'] = $this->includeMatchCheckNullCheck($item->DESC5, $value->DESC5);
			$idata['inc_desc_6'] = $this->includeMatchCheckNullCheck($item->DESC6, $value->DESC6);
			if (in_array(2, $idata)) {
				// $iassort[] = $value->ASSORTMENT_CODE;
			} else {
				$iassort[] = $value->ASSORTMENT_CODE;
			}
		}
		$promo_assortment_exclude = DB::table($this->store_db_name.'.promo_assortment_exclude')->where('ASSORTMENT_CODE', $params->ASSORTMENT_CODE)->get();
		// // dd($promo_assortment_exclude);
		if (count($promo_assortment_exclude) > 0) {
			foreach ($promo_assortment_exclude as $key => $value) {
				$edata['exc_division'] = $this->includeMatchCheckNullCheck($item->DIVISION_CODE, $value->DIVISION_GRPCODE);
				$edata['exc_section'] = $this->includeMatchCheckNullCheck($item->SECTION_CODE, $value->SECTION_GRPCODE);
				$edata['exc_department'] = $this->includeMatchCheckNullCheck($item->DEPARTMENT_CODE, $value->DEPARTMENT_GRPCODE);
				$edata['exc_article'] = $this->includeMatchCheckNullCheck($item->ARTICLE_CODE, $value->INVARTICLE_CODE);
				$edata['exc_icode'] = $this->includeMatchCheckNullCheck($item->ICODE, $value->ICODE);
				$edata['exc_category_1'] = $this->includeMatchCheckNullCheck($item->CCODE1, $value->CCODE1);
				$edata['exc_category_2'] = $this->includeMatchCheckNullCheck($item->CCODE2, $value->CCODE2);
				$edata['exc_category_3'] = $this->includeMatchCheckNullCheck($item->CCODE3, $value->CCODE3);
				$edata['exc_category_4'] = $this->includeMatchCheckNullCheck($item->CCODE4, $value->CCODE4);
				$edata['exc_category_5'] = $this->includeMatchCheckNullCheck($item->CCODE5, $value->CCODE5);
				$edata['exc_category_6'] = $this->includeMatchCheckNullCheck($item->CCODE6, $value->CCODE6);
				$edata['exc_stock_check'] = $this->includeMatchCheckNullOrDateCheck($item->GENERATED, $value);
				$edata['exc_price_range'] = $this->includeMatchCheckNullOrRangerCheck($item->LISTED_MRP, $value);
				$edata['exc_desc_1'] = $this->includeMatchCheckNullCheck($item->DESC1, $value->DESC1);
				$edata['exc_desc_2'] = $this->includeMatchCheckNullCheck($item->DESC2, $value->DESC2);
				$edata['exc_desc_3'] = $this->includeMatchCheckNullCheck($item->DESC3, $value->DESC3);
				$edata['exc_desc_4'] = $this->includeMatchCheckNullCheck($item->DESC4, $value->DESC4);
				$edata['exc_desc_5'] = $this->includeMatchCheckNullCheck($item->DESC5, $value->DESC5);
				$edata['exc_desc_6'] = $this->includeMatchCheckNullCheck($item->DESC6, $value->DESC6);
				if (!in_array(2, $edata)) {
					$eassort[] = $value->ASSORTMENT_CODE;
				}
				if (array_unique($iassort) == array_unique($eassort)) {
					return 0;
				} else {
					return $iassort;
				}
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
		
		//dd($params['promotions']);
		$promotions = $params['promotions'];
        $carts = $params['cart_items']; 
        $item = $params['item']; 
        $item_id = $params['barcode'];
        $final_data = [];
        $offer =[];
        $allOffer= [];
        $available_offers =[];

       
        $carts = $carts->toArray();
  
        $newCarts=[];
        foreach ($carts as $key => $item) {
        	$qty = $item['qty'];
        	$newItems = [];
        	while($qty > 0){
        		$tempItems = $item;
        		$tempItems['qty'] =1;
        		$newCarts[] = $tempItems;
        		$qty--;
        	}
        }
        $carts = collect($newCarts);
    
        $tempCarts = collect($newCarts);;
        //dd($promotions);
        $promotions = collect($promotions);
        $promotions = $promotions->sortByDesc('priority');
        $pdata= [];
        $i=0;
        foreach ($promotions as $key => $promotion) {
        	//dd($promotion);
        	$cart_promo = [ 'promo' => $promotion, 'carts' => $carts, 'item' => $item ];
        	$params['promotion'] = $promotion;
        	$params['all_sources'] = $this->getAllSouces($cart_promo);
        	//dd($params['all_sources']);
        	$Nparams = $params;
        	$Nparams['cart_items'] = $tempCarts;
        	$offer = $this->calculatingIndividualPromotions($Nparams);
        	if($offer){
        		$available_offers[] = $offer['available_offer']; 
        	}
        	//unset($Nparams);
        	//dd($offer);
        	$remainingItem = [];
        	if( $offer['sliceItems'] != null){
        		$remainingItem = $offer['sliceItems']->pluck('item_id')->all();
        	}
        	
        	$pdata = array_merge($pdata , $offer['pdata']);
        	if($i ==1){
        		//dd($offer);
        	}
        	//dd($pdata);
        	if (!empty($remainingItem)) {
	        	$tempCarts = $tempCarts->filter(function ($value, $key) use(&$remainingItem) {
	        		// $remainingItem
	        		//dd($value);
	        		if(in_array($value['item_id'], $remainingItem)){
	        			foreach ($remainingItem as $key => $rvalue) {
	        				if($rvalue == $value['item_id']){
	        					unset($remainingItem[$key]);
	        					break;
	        				}
	        			}
	        			return $value;
	        		}
	        	});
	        }
        	$tempCarts = $tempCarts->values();
        	//dd($tempCarts);
        	$allOffer[] = $offer; 
        	$i++;
        }

        // Check Priority Based on Ginesys Logic

        $allOffer = collect($allOffer);
        $appliedOffer = $allOffer->where('promotion_applied_flag', 'true')->sortByDesc('priority')->first();
        if($appliedOffer != null) {
        	$pdata = $appliedOffer['pdata'];
        }

        // dd($pdata);
        
        $totalCurrentItemQty = $carts->where('item_id',$item_id)->sum('qty');
        $offerpdata = collect($pdata);
        //dd($offerpdata);
        $totalCurrentDisItemQty = $offerpdata->where('item_id' , $item_id)->sum('qty');
        $remainQty = $totalCurrentItemQty - $totalCurrentDisItemQty;
        //dd($remainQty);
        while ( $remainQty > 0) {
        	$val = $carts->where('item_id', $item_id)->first();
        	//dd($val);
     		$data = [];
			$subTotal = $val['qty'] * $val['unit_rsp'];
			$data[] =   [ 'item_id' => $val['item_id'], 'qty' => $val['qty'], 'mrp' => $val['unit_mrp'], 'unit_mrp' => $val['unit_mrp'],'unit_rsp' => $val['unit_rsp'],'discount' => 0 , 'sub_total' => $subTotal, 'total' => $subTotal, 'message' =>'', 'discount_price_basis' => '' ];

			$pdata = array_merge($pdata , $data);
			
			$remainQty--;
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
        //dd($offer);
        return [ 'offer' => $offer, 'available_offers' => $available_offers ];;
	}

	/**
	 * This function will calculate Individual Rule / Promotions
	 * 
	 */
	public function calculatingIndividualPromotions($params)
	{
		//dd($params);
		if($params['store_id'] == '305'){
			print_r($params);
			die;
		}
		$promotion = $params['promotion'];
        $carts = $params['cart_items']; 
        $allSources = $params['all_sources'];//Will contain item of same assortment
        $cartOfSourceItems = [];//Will contain item of same assortment
        $data = array();
        $type = $from = $to = 0;
        $available_offer = $promotion['promo_name'];
        $final_promotion = [];
        $item_id = $params['barcode'];
        // dd($allSources);

        //Getting only item from cart which is present in all Sources and splittig by qty
        $total_qty = 0;
        foreach ($carts as $key => $items) {
            if(in_array($items['item_id'] , $allSources) ){
                while($items['qty'] > 0){
                    $cartOfSourceItems[] = [ 'item_id' =>$items['item_id'] , 'unit_mrp' => $items['unit_mrp']  ,'qty' => 1, 'unit_rsp' => $items['unit_rsp'] ] ;
                    $items['qty']--;
                    $total_qty++;
                }
            }
        }

        //dd($total_qty);

        // Get all promotion slab from promo code
        $promo_slab = DB::table($this->store_db_name.'.promo_slab')->where('PROMO_CODE', $promotion['promo_code'])->get();

        // dd($promo_slab);

    	if($promotion['basis'] == 'QSIMPLE'){
    		if($promotion['buy_factor_type'] == 'A') {
    			$promo_slab = $promo_slab->sortByDesc('SIMPLE_FACTOR');
	    		$promo_slab[format_number(1)] = $promo_slab[0];
	    		unset($promo_slab[0]);
    		} else {
    			$promo_slab = $promo_slab->sortByDesc('SIMPLE_FACTOR');
	    		$promo_slab[format_number($promo_slab[0]->SIMPLE_FACTOR)] = $promo_slab[0];
	    		unset($promo_slab[0]);
    		}
    	} elseif ($promotion['basis'] == 'QSLAB') {
    		$promo_slab = $this->slabQtySplit($promo_slab);
    	}

    	
    	// dd($promo_slab->first()->get);
    	
    	$promo_slab = collect($promo_slab);

    	//Sorting item based on unit_mrp higheset on top
    	$cartOfSourceItems = collect($cartOfSourceItems);
    	$cartOfSourceItems = $cartOfSourceItems->sortByDesc('unit_mrp');
 
    	//dd($cartOfSourceItems);
    	// dd($promotion);
    	$tempTotalQty = $total_qty;
    	$split_qty = [];
    	$max = $promo_slab->keys()->first();
    	// dd($total_qty);
    	
    	$start = 0 ; $end =0;
    	$pdata =[];
    	$promotion_applied_flag = false;
    	$total_promo_discount = 0;
    	$item_with_no_promotion = false;
    	if($promotion['basis'] == 'VSLAB'){
    		
    		foreach ($promo_slab as $key => $slab_value) {
    			$item_with_no_promotion =true;
    			$calculateBy = 'unit_rsp'; //Ginesys column MRP
	        	if($slab_value->DISCOUNT_PRICE_BASIS == 'L'){
	        		$calculateBy = 'unit_mrp';
	        	}elseif($slab_value->DISCOUNT_PRICE_BASIS == 'M'){
	        		$calculateBy = 'unit_rsp';
	        	}

	        	//dd($cartOfSourceItems);
	        	$totalTempAmount = $cartOfSourceItems->sum($calculateBy);
	        	//dd($totalTempAmount);
	        	$SliceCartOfSourceItems = $cartOfSourceItems;

    			if($slab_value->SLAB_RANGE_FROM <= $totalTempAmount &&  $totalTempAmount <= $slab_value->SLAB_RANGE_TO){

    				if($slab_value->GET_BENEFIT_CODE == 3){
    					//Getting Get Assortment
    					$get_all_include_assortment_list = DB::table($this->store_db_name.'.promo_assortment_include')->where('ASSORTMENT_CODE', $slab_value->GET_ASSORTMENT_CODE)->get();
    					// dd($get_all_include_assortment_list);

    					$data = [];
    					foreach ($carts as $key => $value) {
	    					foreach ($get_all_include_assortment_list as $assort) {
								$sort_data = [ 'assort' => $assort, 'item_value' => $value ];
								$barcode = $this->getAllBarcodeByAssort($sort_data);
								if ($barcode == $value['item_id']) {
									$data[$barcode] = $barcode;
								}
							}
						}

						// dd(array_keys($data));
						$getCarts =  $carts->whereIn('item_id', array_keys($data) );
						// dd($getCarts);
						$getCartOfSourceItems = [];
						foreach ($getCarts as $key => $items) {
				            // if(in_array($items['item_id'] , $allSources) ){
				                while($items['qty'] > 0){
				                    $getCartOfSourceItems[] = [ 'item_id' =>$items['item_id'] , 'unit_mrp' => $items['unit_mrp']  ,'qty' => 1, 'unit_rsp' => $items['unit_rsp'] ] ;
				                    $items['qty']--;
				                    $total_qty++;
				                }
				            // }
				        }

				        // dd($SliceCartOfSourceItems);
    					$SliceCartOfSourceItems = collect($getCartOfSourceItems);
    					// dd($SliceCartOfSourceItems);

    					if ($slab_value->GET_FACTOR >= 1) {
			        		$SliceCartOfSourceItems = $SliceCartOfSourceItems->sortBy('unit_mrp');
		        			$SliceCartOfSourceItems = $SliceCartOfSourceItems->slice(0, $slab_value->GET_FACTOR);
		        		}
    					//dd($slab_value);
    					
    					$calculateBy = 'unit_rsp'; //Ginesys column MRP
			        	if($slab_value->GET_METHOD == 'L'){
			        		$calculateBy = 'unit_mrp';
			        	}elseif($slab_value->GET_METHOD == 'M'){
			        		$calculateBy = 'unit_rsp';
			        	}

    				}

    				if(!$SliceCartOfSourceItems->isEmpty()){

	    				$promotion_applied_flag = true;
	    				$item_with_no_promotion =false;

			        	$total_price = $SliceCartOfSourceItems->sum($calculateBy);
			        	$priceArray = $SliceCartOfSourceItems->pluck($calculateBy)->all();
		            	$ratios = $this->get_offer_amount_by_ratio( $priceArray );
		            	$ratio_total = array_sum($ratios);
		        		
		        		$calParams = [ 'total_price' => $total_price, 'discount_type'=> $slab_value->DISCOUNT_TYPE ,'discount_factor' => $slab_value->DISCOUNT_FACTOR];
		        		$discounts = $this->calculateDiscount($calParams);
		        		$discount = $discounts['discount'];
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


			            $currentItems = $SliceCartOfSourceItems->where('item_id', $item_id);
			            // dd($currentItems);
			            foreach ($currentItems as $key => $val) {
			                //dd($val);
			                if($val['discount'] > 0 ){
			                    //$total_price = $mrp * $val['qty'];
			                    $subTotal = $val['qty'] * $val[$calculateBy];
			                    $pdata[] =   [ 'item_id' => $val['item_id'], 'qty' => $val['qty'], 'mrp' => $val[$calculateBy], 'unit_mrp' => $val['unit_mrp'],'unit_rsp' => $val['unit_rsp'],'discount' => $val['discount'], 'sub_total' => $subTotal, 'total' => $subTotal - $val['discount'], 'message' => $promotion['promo_name'], 'discount_price_basis' => $slab_value->DISCOUNT_PRICE_BASIS,'promo_code'=> $promotion['promo_code'],'no'=>$promotion['no'],'start_date'=>$promotion['start_date'],'end_date' =>$promotion['end_date']];
			                }
			            }

			        }
    			}


    		}

    		//dd($pdata);

    	}else{
    		$getAssort = [];
    		$getColle = null;
    		// dd($tempTotalQty);
	    	while ($tempTotalQty > 0) {
	    		$result = $promo_slab->search(function ($value, $key) use($tempTotalQty) {
					if ((int)$key == $tempTotalQty) {
						return true;
					}
				});
	    		if ($result) {
	    			$result = $promo_slab[format_number($tempTotalQty)];
	    			// dd($result);
	    			if($result->GET_BENEFIT_CODE == 3){
                        $get_assort_code =  $result->GET_ASSORTMENT_CODE;
                        $params['slab_value'] = $result;
                        if(!isset($getAssort[$get_assort_code])){
                            $getAssort[$result->GET_ASSORTMENT_CODE] =  $getColle = $this->getGetAssortment($params);
                        }
                        unset($params['slab_value']);

                        if($getColle->sum('qty') >= $result->GET_FACTOR ){

                        	$checkItem_id = $cartOfSourceItems->first()['item_id'];
                        	//Buy and Get Assortment are same
	                        if($getColle->where('item_id',$checkItem_id)->first()){
	                        	if($tempTotalQty >= ($tempTotalQty + $result->GET_FACTOR)){

	                        		array_push($split_qty, ['qty' => format_number($tempTotalQty), 'is_promo' => 1, 'get_qty' => $result->GET_FACTOR ]);

	                        	}
	                        	
	                        }else{

                            	array_push($split_qty, ['qty' => format_number($tempTotalQty), 'is_promo' => 1, 'get_qty' => $result->GET_FACTOR, 'get_assort_code' => $get_assort_code ]);
	                        }

                            

                        }else{
                            $promo_slab->shift();
                            $max = $promo_slab->keys()->first();
                    
                            continue;
                        }

                    }else{
                        // dd($result);
                        $split_qty_params = ['qty' => format_number($tempTotalQty), 'is_promo' => 1];
                        if($result->GET_FACTOR > 0 ){
                            $split_qty_params['get_qty'] = $result->GET_FACTOR;
                        }
                        array_push($split_qty, $split_qty_params);
                    }
	    			$tempTotalQty = $tempTotalQty - $tempTotalQty;

	    		} else {
	    			// dd($max);
	    			if($max > 0){
		    			if ($tempTotalQty > $max) {

		    				$push_data = [];
                          
                            $result = $promo_slab[format_number($max)];
                            // dd($promo_slab);
                            if($result->GET_BENEFIT_CODE == 3){
                                $get_assort_code =  $result->GET_ASSORTMENT_CODE;
                                $params['slab_value'] = $result;
                                if(!isset($getAssort[$get_assort_code])){
                                    $getAssort[$result->GET_ASSORTMENT_CODE] =  $getColle = $this->getGetAssortment($params);
                                }
                                unset($params['slab_value']);
                                if($getColle->sum('qty') >= $result->GET_FACTOR ){

                            
                                	$checkItem_id = $cartOfSourceItems->first()['item_id'];
                                	//Buy and Get Assortment are same
			                        if($getColle->where('item_id',$checkItem_id)->first()){
			                        	if($tempTotalQty >= ($max + $result->GET_FACTOR )){
				                        	$push_data = ['qty' => format_number($max), 'is_promo' => 1 , 'get_qty' => $result->GET_FACTOR ];

				                        	$tempTotalQty -= $result->GET_FACTOR;
				                        }
			                        }else{

                                    	$push_data = ['qty' => format_number($max), 'is_promo' => 1 , 'get_qty' => $result->GET_FACTOR , 'get_assort_code' => $result->GET_ASSORTMENT_CODE ];
			                        }

                                }else{
                                    $promo_slab->shift();
                                    $max = $promo_slab->keys()->first();
                            
                                    continue;
                                }

                            }else{

                                $push_data = ['qty' => format_number($max), 'is_promo' => 1];
                                if($result->GET_FACTOR > 0 ){
                                    $push_data['get_qty'] = $result->GET_FACTOR;
                                }
                                
                            }
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

	    	// if($item_id == 'MA6246'){
	    		// dd($split_qty);	
	    	// }
	    	

	    	foreach ($split_qty as $key => $value) {

	    		$fromQty = $toQty = 0; 

	    		if(isset($value['get_qty'])){
                    if(isset($value['get_assort_code'])){

                        //This Condition is added for Any Qty in Get Assortment
                        if($value['get_qty'] == 0){
                            $value['get_qty'] =1;
                        }

                        $cartOfSourceItems = $getAssort[$value['get_assort_code']];
                   
                        // dd($getAssort);
                    }else{//Means get from Buy assortment
                        $cartOfSourceItems->shift();
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
				
	        	// echo " ".$start.' - '.$end;	
	        	
	        	$SliceCartOfSourceItems = $cartOfSourceItems->slice($start, $value['qty'])->values();
	        	// dd($SliceCartOfSourceItems);
	        	// if($key == 1){
	        	// 	// dd($SliceCartOfSourceItems);
	        	// }

	    		if ($value['is_promo'] == 1) {
	    			$promotion_applied_flag = true;
	    			$slab_value = $promo_slab[$value['qty']];

					$calculateBy = 'unit_rsp'; //Ginesys column MRP
		        	if($slab_value->DISCOUNT_PRICE_BASIS == 'L'){
		        		$calculateBy = 'unit_mrp';
		        	}elseif($slab_value->DISCOUNT_PRICE_BASIS == 'M'){
		        		$calculateBy = 'unit_rsp';
		        	}
		        	//print_r($SliceCartOfSourceItems);
		        	//dd($SliceCartOfSourceItems);
		        	if($slab_value->DISCOUNT_PRICE_BASIS != 'E') {
			        	
		        		if ($slab_value->GET_FACTOR >= 1) {
		        			$SliceCartOfSourceItems = $SliceCartOfSourceItems->sortBy('unit_mrp');
		        			$SliceCartOfSourceItems = $SliceCartOfSourceItems->slice(0, $slab_value->GET_FACTOR);
		        			$total_price = $SliceCartOfSourceItems->sum($calculateBy);
		        		} else {
		        			$total_price = $SliceCartOfSourceItems->sum($calculateBy);
		        		}
		        		$priceArray = $SliceCartOfSourceItems->pluck($calculateBy)->all();
		            	$ratios = $this->get_offer_amount_by_ratio( $priceArray );
		            	$ratio_total = array_sum($ratios);
		        		// $total_price = $SliceCartOfSourceItems->sum($calculateBy);
		        		// dd($SliceCartOfSourceItems);
		        		$calParams = [ 'total_price' => $total_price, 'discount_type'=> $slab_value->DISCOUNT_TYPE ,'discount_factor' => $slab_value->DISCOUNT_FACTOR];
		        		$discounts = $this->calculateDiscount($calParams);
		        		$discount = $discounts['discount'];
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

		        	} else{

			        	if ($slab_value->GET_FACTOR >= 1) {
			        		$SliceCartOfSourceItems = $SliceCartOfSourceItems->sortBy('unit_mrp');
		        			$SliceCartOfSourceItems = $SliceCartOfSourceItems->slice(0, $slab_value->GET_FACTOR);
		        		}

			        	//$total_discount =0;
			            $SliceCartOfSourceItems->transform(function($item , $key) use( &$slab_value, &$total_promo_discount){
			                $total_price = $item['unit_mrp'];
			                $calParams = [ 'total_price' => $total_price, 'discount_type'=> $slab_value->DISCOUNT_TYPE ,'discount_factor' => $slab_value->DISCOUNT_FACTOR];
		        			$discounts = $this->calculateDiscount($calParams);
			              	$total_promo_discount += $discounts['discount'];
			                return array_merge($item , [ 'discount' => $discounts['discount'] ] );
			            });

			        }


		            // dd($SliceCartOfSourceItems);
		            

		            $currentItems = $SliceCartOfSourceItems->where('item_id', $item_id);
		            // dd($currentItems);
		            foreach ($currentItems as $key => $val) {
		                //dd($val);
		                if($val['discount'] > 0 ){
		                    //$total_price = $mrp * $val['qty'];
		                    $subTotal = $val['qty'] * $val[$calculateBy];
		                    $pdata[] =   [ 'item_id' => $val['item_id'], 'qty' => $val['qty'], 'mrp' => $val[$calculateBy], 'unit_mrp' => $val['unit_mrp'],'unit_rsp' => $val['unit_rsp'],'discount' => $val['discount'], 'sub_total' => $subTotal, 'total' => $subTotal - $val['discount'], 'message' => $promotion['promo_name'], 'discount_price_basis' => $slab_value->DISCOUNT_PRICE_BASIS,'promo_code'=> $promotion['promo_code'],'no'=>$promotion['no'],'start_date'=>$promotion['start_date'],'end_date' =>$promotion['end_date']  ];
		                }
		            }

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
		// 	$val = $cartOfSourceItems->where('item_id', $item_id)->first();
		// 	$subTotal = $val['qty'] * $val['unit_mrp'];
		// 	$pdata[] =   [ 'item_id' => $val['item_id'], 'qty' => $val['qty'], 'mrp' => $val['unit_mrp'], 'unit_mrp' => $val['unit_mrp'],'unit_rsp' => $val['unit_rsp'],'discount' => 0 , 'sub_total' => $subTotal, 'total' => $subTotal, 'message' =>'', 'discount_price_basis' => '' ];
			
		// 	$remainQty--;
		// }


    	// dd($pdata);
    	if(!$item_with_no_promotion){
    		$SliceCartOfSourceItems = null;
    	}
        return [ 'priority' => $promotion['priority'], 'total_promo_discount' => $total_promo_discount , 'promotion_applied_flag' => $promotion_applied_flag , 'pdata' => $pdata , 'sliceItems' => $SliceCartOfSourceItems , 'available_offer' => $available_offer ];             

	}

	public function getGetAssortment($params){
        $first_slab = $params['slab_value'];
        $carts = $params['cart_items'];
        // dd($carts);
        $get_all_include_assortment_list = DB::table($this->store_db_name.'.'.'promo_assortment_include')->where('ASSORTMENT_CODE', $first_slab->GET_ASSORTMENT_CODE)->get();
        // dd($get_all_include_assortment_list);

        $data = [];
        foreach (collect($carts)->unique('barcode') as $key => $value) {
            // dd($value);
            // $value =['item_id' => $value->item_id, 'barcode' => $value->barcode];
            foreach ($get_all_include_assortment_list as $assort) {
                $sort_data = [ 'assort' => $assort, 'item_value' => $value ];
                $barcode = $this->getAllBarcodeByAssort($sort_data);
                if ($barcode == $value['item_id']) {
                    $data[$barcode] = $barcode;
                }
            }
        }
        // dd($data);
        // dd(array_keys($data));
        $getCarts =  $carts->whereIn('item_id', array_keys($data) );
        $getCarts= $getCarts->sortByDesc('unit_mrp');
        // dd($getCarts);
        $getCartOfSourceItems = [];
        foreach ($getCarts as $key => $items) {

            if(isset($items['weight_flag']) && $items['weight_flag'] == 1){
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

                    $getCartOfSourceItems[] = [ 'item_id' =>$items['item_id'] , 'unit_mrp' => $items['unit_mrp']  , 'qty' => $single_unit_qty, 'unit_rsp' => $items['unit_rsp']
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
                        // if($this->split_by_qty){
                        //     $qty = 1;
                        // }
                        $getCartOfSourceItems[] = [ 'item_id' =>$items['item_id'] , 'unit_mrp' => $items['unit_mrp']  ,'qty' => $qty, 'unit_rsp' => $items['unit_rsp'] 
                            ,'subtotal_unit_mrp' => $items['unit_mrp'] * $qty
                            ,'subtotal_unit_rsp' => $items['unit_rsp'] * $qty
                            // ,'batch_id'=>$items['batch_id'],'serial_id'=>$items['serial_id'] 
                            ] ;
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
							  $s_price	= $this->calculateDiscount($disParams);
							} else {
								$s_price = ['discount' => 0,'gross' => $fetch['unit_mrp'] ];
							}
							$data[$fetch['item_id']][] = [ 'item_id' => $fetch['item_id'], 'basic_price' => $fetch['unit_mrp'], 'discount' => $s_price['discount'], 'gross' => $s_price['gross'], 'qty' => $fetch['qty'] ];
							$count++;
						}

				} elseif($condition->DISCOUNT_PRICE_BASIS == 'M') {

				}

			} else {
				foreach ($items as $key => $item) {
					$data[$item['item_id']][] = [ 'item_id' => $item['item_id'], 'basic_price' => $item['unit_mrp'], 'discount' => 0, 'gross' => $item['unit_mrp'], 'qty' => $item['qty'] ];
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
	public function calculateDiscount($params)
	{
		$total_price = $params['total_price'];
		$discountType = $params['discount_type'];
		$discountFactor = $params['discount_factor'];

		$discount = 0;
		/**
		 * Calculating Percentage Discount
		 * @var [type]
		 */
		if($discountType == 'P'){

            $discount = $total_price * $discountFactor / 100 ;
		}


		/**
		 * Calculating Amount Discount
		 * @var [type]
		 */
		if($discountType == 'A'){
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


		$gross_price = $total_price - $discount;
		return ['discount' => $discount,'gross' => $gross_price ];

	}

	public function convert_in_indian_date_format($date) 
	{
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
		foreach ($params['carts'] as $key => $value) {
			$all_assortment_code = DB::table($this->store_db_name.'.promo_buy')->where('PROMO_CODE', $params['promo']['promo_code'])->get()->pluck('ASSORTMENT_CODE');
			// dd($all_assortment_code);
			$get_all_include_assortment_list = DB::table($this->store_db_name.'.promo_assortment_include')->whereIn('ASSORTMENT_CODE', $all_assortment_code)->get();
			// dd($get_all_include_assortment_list);
			foreach ($get_all_include_assortment_list as $assort) {
				$sort_data = [ 'assort' => $assort, 'item_value' => $value ];
				$barcode = $this->getAllBarcodeByAssort($sort_data);
				if ($barcode == $value['item_id']) {
					$data[$barcode] = $barcode;
				}
			}

			$c_id = null;
			if(isset($params['c_id'])){
				$c_id = $params['c_id'];
			}

			$get_all_exclude_assortment_list_count = DB::table($this->store_db_name.'.promo_assortment_exclude')->whereIn('ASSORTMENT_CODE', $all_assortment_code)->count();
			$esort_data = [ 'assort' => $all_assortment_code, 'item_value' => $value, 'data' => $data, 'c_id' => $c_id ];
			$excludeData = $this->getAllBarcodeByEAssort($esort_data);
			// dd($excludeData);
			if (!empty($excludeData)) {
				unset($data[$value['item_id']]);
			}

			/*$get_all_exclude_assortment_list = DB::table($this->store_db_name.'.promo_assortment_exclude')->where('ASSORTMENT_CODE', $all_assortment_code)->get();

			if (count($get_all_exclude_assortment_list) > 0) {
				foreach ($get_all_exclude_assortment_list as $eassort) {
					$esort_data = [ 'assort' => $eassort, 'item_value' => $value ];
					$ebarcode = $this->getAllBarcodeByAssort($esort_data);
					if ($ebarcode == $value['item_id']) {
						unset($data[$value['item_id']]);
					}
				}
			}*/

		}
		// $flattened = array_flatten($data);
		$unique = array_unique($data, SORT_REGULAR);
		
		// dd($unique);
		return $unique;
	}

	public function getAllBarcodeByAssort($params) 
	{
		// dd($params);
		$item = $params['item_value'];
		$values = [];
		$keys = [];
		// dd($array);
		foreach ($params['assort'] as $key => $value) {
			if (!empty($value)) {
				if ($key != 'ASSORTMENT_CODE' && $key != 'CODE' && $key != 'PRICE_RANGE_BASIS' && $key != 'PRICE_RANGE_FROM' && $key != 'PRICE_RANGE_TO' && $key != 'STOCKINDATE_FROM' && $key != 'STOCKINDATE_TO' && $key != 'QTY' && $key != 'ITEM_REWARD_VALUE') {
					array_push($values, $value);
				}
			}
			$keys[$key] = $value;
		}
		// dd($values);
		// krsort($values);
		$cloumn_name = array_search(end($values), $keys);
		// dd($cloumn_name);
		if ($cloumn_name == 'ICODE') {
			return end($values);
		} else {
			if ($cloumn_name != 'DIVISION_GRPCODE' && $cloumn_name != 'SECTION_GRPCODE' && $cloumn_name != 'DEPARTMENT_GRPCODE' && $cloumn_name != 'INVARTICLE_CODE') {
				$inbar = DB::table($this->store_db_name.'.invitem')->where($cloumn_name, end($values))->where('ICODE', $item['item_id'])->first();
				if (!empty($inbar)) {
					return $item['item_id'];
				}
			} else
			if ($cloumn_name == 'DEPARTMENT_GRPCODE') {
				return ($item['department_id'] == $params['assort']->DEPARTMENT_GRPCODE ? $item['item_id'] : '');
			} elseif ($cloumn_name == 'INVARTICLE_CODE') {
				return ($item['article_id'] == $params['assort']->INVARTICLE_CODE ? $item['item_id'] : '');
			}
		}
	}

	public function getAllBarcodeByEAssort($params)
	{
		// dd($params['data']);
		$data = [];
		$item_id = $params['item_value']['item_id'];
		// Division

		$division = DB::table($this->store_db_name.'.promo_assortment_exclude')->whereIn('ASSORTMENT_CODE', $params['assort'])->where('DIVISION_GRPCODE', $params['item_value']['division_id'])->count();
		if (!empty($division)) {
			$data[$item_id] = $item_id;
		}

		$section = DB::table($this->store_db_name.'.promo_assortment_exclude')->whereIn('ASSORTMENT_CODE', $params['assort'])->where('SECTION_GRPCODE', $params['item_value']['section_id'])->count();
		if (!empty($section)) {
			$data[$item_id] = $item_id;
		}

		$deparment = DB::table($this->store_db_name.'.promo_assortment_exclude')->whereIn('ASSORTMENT_CODE', $params['assort'])->where('DEPARTMENT_GRPCODE', $params['item_value']['department_id'])->count();
		if (!empty($deparment)) {
			$data[$item_id] = $item_id;
		}

		$article = DB::table($this->store_db_name.'.promo_assortment_exclude')->whereIn('ASSORTMENT_CODE', $params['assort'])->where('INVARTICLE_CODE', $params['item_value']['article_id'])->count();
		if (!empty($article)) {
			$data[$item_id] = $item_id;
		}

		$icode = DB::table($this->store_db_name.'.promo_assortment_exclude')->whereIn('ASSORTMENT_CODE', $params['assort'])->where('ICODE', $params['item_value']['item_id'])->count();
		if (!empty($icode)) {
			$data[$item_id] = $item_id;
		}

		if (in_array($item_id, $data)) {
			unset($params['data'][$item_id]);
			return $data;
		} else {
			// return $params['data'];
			return [];
		}
	}

	//Sort Array 
	private	function arraySorting(&$arr,$key) 
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

	public function get_offer_amount_by_ratio($param , $offer_amount = 0)
	{

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

    public function gcd($a, $b)
     {
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
    	$this->store_db_name = $store->store_db_name;
    	$mapping_store_id = $store->mapping_store_id;
    	$discount =0;
    	// Check Memo Level Promotion Exsist 

    	$memoPromoList  = DB::table($this->store_db_name.'.psite_promo_assign as ppa')
				->select('ppa.PROMO_CODE','ppa.STARTDATE', 'ppa.ENDDATE', 'pb.ASSORTMENT_CODE', 'ppa.PRIORITY','pm.BASIS','pm.NAME','ppa.STARTDATE', 'ppa.ENDDATE', 'pm.NO')
				->leftJoin($this->store_db_name.'.promo_buy as pb', 'pb.PROMO_CODE', 'ppa.PROMO_CODE')
				->leftJoin($this->store_db_name.'.promo_master as pm', 'pm.CODE', 'ppa.PROMO_CODE')
				->where('ppa.ADMSITE_CODE', $mapping_store_id)
				->where('pm.TYPE', 'M')
				->where('ppa.STATUS', 'A')
				->orderByDesc('ppa.PRIORITY')
				->get();

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
    			$itemDet = json_decode($value->section_target_offers);
    			$itemDet = urldecode($itemDet->item_det);
    			$itemDet = json_decode($itemDet);
    			$itemDet->QTY = $value->qty;
    			$itemDet->DISCOUNT = $value->discount;
    			// $itemDet->NETAMT = $value->total;
    			$itemDet->NETAMT = $value->subtotal - $value->discount;
    			$itemDet->UNITMRP = $value->unit_mrp;
    			$itemDet->UNITCSP = $value->unit_csp;
    			$itemDet->CARTID = $value->cart_id;
    			$itemDet->item_id = $value->item_id;
    			$item[] = $itemDet;
    		}

    		// if($params['store_id'] == 425){
      //   		dd($storeMemoPromoList);
      //   	}

    		// Checking each promotion

    		foreach ($storeMemoPromoList as $key => $value) {
    			$promo_list[$value->PROMO_CODE] = [ 'PROMO_CODE' => $value->PROMO_CODE, 'PRIORITY' => $value->PRIORITY, 'start_date' => $value->STARTDATE, 'end_date'=> $value->ENDDATE, 'BASIS' => $value->BASIS, 'NO' => $value->NO, 'NAME' => $value->NAME, 'items' => [] ];
    			
    			foreach ($item as $item_value) {
    				if (!empty($this->promotionDerivation($value, $item_value))) {
    					
						$promo_list[$value->PROMO_CODE]['ASSORTMENT_CODE'] = $this->promotionDerivation($value, $item_value);

						array_push($promo_list[$value->PROMO_CODE]['items'], (array)$item_value);
					}
    			}
    			// dd($item);
    		}
    		// dd($promo_list);
    		// Resolve Each Promo
    		// if($params['store_id'] = 425){
 
    		// 	dd($promo_list);
    		// }

    		if (!empty($promo_list)) {

    			//dd($promo_list);
    			$pdata =[];
    			foreach ($promo_list as $key => $value) {
    				// dd($value['items']);
    				// Split Qty of each item
		    		$cartOfSourceItems = [];
		    		$total_qty = $netamt = 0;
		    		foreach ($value['items'] as $items) {
		    			$items = (object)$items;
		    			$item_qty = $items->QTY;
		    			while($item_qty > 0){
		    				$netamt = $items->NETAMT / $items->QTY; 
		                    $cartOfSourceItems[] = (object)[ 'item_id' =>$items->ICODE , 'unit_mrp' => $items->UNITMRP  ,'qty' => 1, 'unit_rsp' => $items->UNITCSP, 'netamt' => $netamt, 'discount' => $items->DISCOUNT, 'cart_id' => $items->CARTID, 'INVHSNSACMAIN_CODE' => $items->INVHSNSACMAIN_CODE ] ;
		                    $item_qty--;
		                    $total_qty++;
		                }
		    		}

		    		// dd($cartOfSourceItems);
    				
    				$start = 0 ; $end =0;
			    	// $pdata =[];
			    	$promotion_applied_flag = false;
			    	$total_promo_discount = 0;
			    	$item_with_no_promotion = false;
			    	if($value['BASIS'] == 'VSLAB'){

			    		$promo_slab = DB::table($this->store_db_name.'.promo_slab')->where('PROMO_CODE', $key)->orderBy('SLAB_RANGE_FROM', 'desc')->get();
			    		// dd($promo_slab);
			    		// if($params['store_id'] = 425){
			    			
			    		// 	dd($promo_slab);
			    		// }
			    		
			    		foreach ($promo_slab as $key => $slab_value) {
			    			$item_with_no_promotion =true;
			    			$calculateBy = 'netamt'; //Ginesys column MRP
				        	// if($slab_value->DISCOUNT_PRICE_BASIS == 'L'){
				        	// 	$calculateBy = 'unit_mrp';
				        	// }elseif($slab_value->DISCOUNT_PRICE_BASIS == 'M'){
				        	// 	$calculateBy = 'unit_rsp';
				        	// }

			    			

				        	$cartOfSourceItems = collect($cartOfSourceItems);
				        	$totalTempAmount = $cartOfSourceItems->sum($calculateBy);
				        	// dd($totalTempAmount);
				      		//   	if($params['store_id'] = 425){
				    			
				    		// 	dd($calculateBy);
				    		// }

				        	$SliceCartOfSourceItems = $cartOfSourceItems;

			    			if($slab_value->SLAB_RANGE_FROM <= $totalTempAmount &&  ($totalTempAmount <= $slab_value->SLAB_RANGE_TO || $slab_value->SLAB_RANGE_TO ==0 )){

			    				// if($params['store_id'] = 425){
					    			
					    		// 	dd($slab_value);
					    		// }

			    				if($slab_value->GET_BENEFIT_CODE == 3){
			    					//Getting Get Assortment
			    					$get_all_include_assortment_list = DB::table($this->store_db_name.'.promo_assortment_include')->where('ASSORTMENT_CODE', $slab_value->GET_ASSORTMENT_CODE)->get();
			    					// dd($get_all_include_assortment_list);
			    					
									$data = [];  

                                    
                                    foreach ($item as $key => $va) {

                                        $va = (array)$va;

                                        foreach ($get_all_include_assortment_list as $assort) {
                                            $sort_data = [ 'assort' => $assort, 'item_value' => $va ];

                                            $barcode = $this->getAllBarcodeByAssort($sort_data);
                              

                                            if ($barcode == $va['item_id']) {
                                                $data[$barcode] = $barcode;
                                            }
                                        }
                                    }

									// dd(array_keys($data));
									$getCarts =  collect($item)->whereIn('item_id', array_keys($data) );

									// dd($getCarts);

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
                                            // if($qty >= $single_unit_qty  ){
                                            //     $single_unit_qty = $qty;
                                            // }

                                            $getCartOfSourceItems[] = (object)[ 'item_id' =>$items->ICODE , 'unit_mrp' => $items->UNITMRP  
                                                , 'subtotal_unit_mrp' => $items->UNITMRP  * $single_unit_qty
                                                , 'subtotal_unit_rsp' => $items->UNITCSP  * $single_unit_qty

                                                ,'qty' => $single_unit_qty , 'unit_rsp' => $items->UNITCSP , 'netamt' => $netamt * $single_unit_qty, 'discount' => $singleDis , 'bill_discount' => 0 , 'cart_id' => $items->CARTID, 'INVHSNSACMAIN_CODE' => $items->INVHSNSACMAIN_CODE ] ;
                                            $qty -= $single_unit_qty;
                                            $total_qty += $single_unit_qty ;
                                        }


							        }
							        


							        // dd($SliceCartOfSourceItems);
			    					$SliceCartOfSourceItems = collect($getCartOfSourceItems);
			    					
			    					if ($slab_value->GET_FACTOR >= 1) {
						        		$SliceCartOfSourceItems = $SliceCartOfSourceItems->sortBy('unit_mrp');
					        			$SliceCartOfSourceItems = $SliceCartOfSourceItems->slice(0, $slab_value->GET_FACTOR);

                                        if($SliceCartOfSourceItems->count() >= $slab_value->GET_FACTOR){
                                            
                                        }else{
                                            $SliceCartOfSourceItems = collect([]);
                                        }
					        		}

			    					//dd($slab_value);
			    					// dd($SliceCartOfSourceItems);
			    					$calculateBy = 'unit_rsp'; //Ginesys column MRP
						        	if($slab_value->GET_METHOD == 'L'){
						        		$calculateBy = 'unit_mrp';
						        	}elseif($slab_value->GET_METHOD == 'M'){
						        		$calculateBy = 'unit_rsp';
						        	}

			    				}


			    				if(!$SliceCartOfSourceItems->isEmpty()){
			    				// dd($SliceCartOfSourceItems);
			    				

				    				$promotion_applied_flag = true;
				    				$item_with_no_promotion =false;

						        	$total_price = $SliceCartOfSourceItems->sum($calculateBy);
						        	$priceArray = $SliceCartOfSourceItems->pluck($calculateBy)->all();
					            	$ratios = $this->get_offer_amount_by_ratio( $priceArray );
					            	$ratio_total = array_sum($ratios);

					        		
					        		$calParams = [ 'total_price' => $total_price, 'discount_type'=> $slab_value->DISCOUNT_TYPE ,'discount_factor' => $slab_value->DISCOUNT_FACTOR];
					        		$discounts = $this->calculateDiscount($calParams);
					        		$discount = $discounts['discount'];
					        		$total_promo_discount += $discount;


					        		
					        		$SliceCartOfSourceItems = $SliceCartOfSourceItems->values();
						            //distributing discount amount based on ratio to all items
						            $total_discount =0;
						            $SliceCartOfSourceItems->transform(function($item , $key) use($ratios, $ratio_total, $discount , &$total_discount){
						                $discount = round( ($ratios[$key]/$ratio_total) * $discount , 2);
						                $total_discount += $discount;
						                // return array_merge($item , [ 'discount' => $discount ] );
						                $item->discount = $discount;
						                return $item;
						            });


						            // dd($SliceCartOfSourceItems);
						            
						            $total_discount = (string)$total_discount;
						            $discount = (string)$discount;
						            //This code is added because facing issue when rounding of discount value
						            if($total_discount > $discount){
						            	$total_discount = (float)$total_discount;
						            	$discount = (float)$discount;

						                $total_diff = $total_discount - $discount;
						                $SliceCartOfSourceItems->transform(function($item, $key)use(&$total_diff){
						                    if($total_diff > 0.00){
						                        $item->discount -= 0.01;
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
						                        $item->discount += 0.01;
						                        $total_diff -= 0.01;
						                    }
						                    return $item;
						                });
						            }

						            
						            // dd($SliceCartOfSourceItems);
						            $currentItems = $SliceCartOfSourceItems;
						            // dd($value);
						            foreach ($currentItems as $key => $val) {
						                //dd($val);
						                if($val->discount > 0 ) {
						                    //$total_price = $mrp * $val['qty'];
						                    $subTotal = $val->qty * $val->$calculateBy;
						                    $pdata[] =   [ 'item_id' => $val->item_id, 'qty' => $val->qty, 'mrp' => $val->$calculateBy, 'unit_mrp' => $val->unit_mrp,'unit_rsp' => $val->unit_rsp,'discount' => $val->discount, 'sub_total' => $subTotal, 'total' => $subTotal - $val->discount, 'message' => $value['NAME'], 'discount_price_basis' => $slab_value->DISCOUNT_PRICE_BASIS,'promo_code'=> $value['PROMO_CODE'],'no'=>$value['NO'],'start_date'=>$value['start_date'],'end_date' =>$value['end_date'], 'cart_id' => $val->cart_id, 'hsn' => $val->INVHSNSACMAIN_CODE ];
						                }
						            }

						            // dd($pdata);
						            //return $this->memoFilterPromo($pdata);
						        }
						        // dd($pdata);
						        if($discount > 0){
						        	$pdataCollection = collect($pdata);
						        	$item_ids = $pdataCollection->pluck('item_id')->all();
						        	$item_ids = array_unique($item_ids);
						        
						        	foreach ($item_ids as  $idOfitem) {
						        		$cart = $params['carts']->where('item_id',$idOfitem)->first();
						        		$totalQty = $cart->qty;
						        		$offQty = $pdataCollection->sum('qty');
						        		$remainQty = $totalQty - $offQty;
						        		$pdataItem = $pdataCollection->where('item_id',$idOfitem)->first();
						        		// dd($pdataItem);
						        		while($remainQty > 0){
						        			$pdataItem = (object)$pdataItem;
						        			$discount = $cart->discount / $cart->qty; 
						                    $pdata[] =   [ 'item_id' => $pdataItem->item_id, 'qty' => 1, 'mrp' => $pdataItem->mrp, 'unit_mrp' => $pdataItem->unit_mrp,'unit_rsp' => $pdataItem->unit_rsp,'discount' => 0, 'sub_total' => $pdataItem->sub_total , 'total' => $pdataItem->sub_total - $discount, 'message' => '', 'discount_price_basis' => $pdataItem->discount_price_basis,'promo_code'=> '','no'=>$pdataItem->no,'start_date'=> '','end_date' => '', 'cart_id' => $pdataItem->cart_id, 'hsn' => $pdataItem->hsn ];
						                    $remainQty--;
						        		}
						        	}
						        	
                                    break;
                                }

			    			}

			    		}

			    	}

    			}

                if(count($pdata) > 0) {
       				// if($params['store_id'] == 65){
						     //    		dd($pdata);

					    		// 	}
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
    	$items = [];
    	$data = collect($data);
    	$data = $data->groupBy('item_id');
    	
    	foreach ($data as $key => $value) {
    		$discount = $subtotal = $total = $qty =  0;
    		foreach ($value as $item) {
    			$qty += $item['qty'];
    			$discount += $item['discount'];
    			$subtotal += $item['sub_total'];
    			$total += $item['total'];
    		}
    		$items[] = (object)[ 'cart_id' => $value[0]['cart_id'], 'item_id' => $value[0]['item_id'], 'unit_mrp' => $value[0]['unit_mrp'], 'unit_rsp' => $value[0]['unit_rsp'], 'qty' => $qty, 'sub_total' => $subtotal, 'discount' => $discount, 'total' => $total, 'no' => $value[0]['no'], 'promo_code' => $value[0]['promo_code'], 'name' => $value[0]['message'], 'start_date' => $value[0]['start_date'], 'end_date' => $value[0]['end_date'], 'hsn' => $value[0]['hsn'] ];
    	}
    	return $items;
    }


}
