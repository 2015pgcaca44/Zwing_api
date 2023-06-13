<?php

namespace App\Http\Controllers\V1\Ananda;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VendorSettingController;
use Auth;
use DB;

class PromotionController extends Controller {
	public function final_check_promo_sitewise($request, $cart = 0) {
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
		$item_value = array();
		$final_promo_assortment_list = array();
		$discount = 0;
		$promo_list = array();
		$psite_promo_list = array();
		$final_promo_list = array();
		$today = date('Y-m-d');
		$today = date('Y-m-d', strtotime($today));
		$count = 0;
		$list_assortment = array();
		$list_assortment_code = array();
		$item = DB::table('ananda.invitem')->select('GRPCODE', 'INVARTICLE_CODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE')->where('ICODE', $id)->first();
		$group = DB::table('ananda.invgrp')->select('LEV1GRPNAME', 'LEV2GRPNAME', 'GRPCODE', 'GRPNAME')->where('GRPCODE', $item->GRPCODE)->first();
		$article = DB::table('ananda.invarticle')->select('CODE', 'NAME')->where('CODE', $item->INVARTICLE_CODE)->first();
		$division = DB::table('ananda.invgrp')->select('GRPCODE', 'GRPNAME')->where('GRPNAME', $group->LEV1GRPNAME)->first();
		$section = DB::table('ananda.invgrp')->select('GRPCODE', 'GRPNAME')->where('GRPNAME', $group->LEV2GRPNAME)->first();
		$admsite = DB::table('ananda.admsite')->select('NAME')->where('CODE', $scode)->first();
		$item_value = [
			'division' => $division->GRPCODE,
			'section' => $section->GRPCODE,
			'department' => $group->GRPCODE,
			'article' => $article->CODE,
			'icode' => $item->ICODE,
			'category_1' => $item->CCODE1,
			'category_2' => $item->CCODE2,
			'category_3' => $item->CCODE3,
			'category_4' => $item->CCODE4,
			'category_5' => $item->CCODE5,
			'category_6' => $item->CCODE6,
			'stock_date' => $item->STOCKINDATE,
			'mrp' => $item->MRP,
		];
		// dd($item_value);
		$psite_promo_assign = DB::table('ananda.psite_promo_assign')->select('STARTDATE', 'ENDDATE', 'PROMO_CODE')->where('ADMSITE_CODE', $scode)->where('STATUS', 'A')->get();
		// dd($psite_promo_assign);
		if (count($psite_promo_assign) > 0) {
			foreach ($psite_promo_assign as $key => $promo_assign) {
				$startdate = date('Y-m-d', strtotime($promo_assign->STARTDATE));
				$enddate = date('Y-m-d', strtotime($promo_assign->ENDDATE));
				// echo 'Start Date :- '.$startdate.' End Date :- '.$enddate.' CODE :- '.$promo_assign->PROMO_CODE.'<br>';
				// if (($today >= $startdate) && ($today <= $enddate)) {
				// echo 'Start Date :- '.$startdate.' End Date :- '.$enddate.' CODE :- '.$promo_assign->PROMO_CODE.'<br>';
				$promo_buy = DB::table('ananda.promo_buy')->select('ASSORTMENT_CODE')->where('PROMO_CODE', $promo_assign->PROMO_CODE)->get();
				foreach ($promo_buy as $promo_buy_value) {
					array_push($promo_data, ['ASSORTMENT_CODE' => $promo_buy_value->ASSORTMENT_CODE, 'PROMO_CODE' => $promo_assign->PROMO_CODE]);
					array_push($psite_promo_list, $promo_assign->PROMO_CODE);
				}
				// echo $promo_assign->PROMO_CODE.'<br>';
				// }
			}
		}
		// print_r($promo_data);
		// dd('Ok');

		foreach ($promo_data as $key => $sort_data) {
			$pai = DB::table('ananda.promo_assortment_include')->where('ASSORTMENT_CODE', $sort_data['ASSORTMENT_CODE'])->get();
			foreach ($pai as $pai_code) {
				array_push($list_assortment_code, ['CODE' => $pai_code->CODE, 'ASSORTMENT_CODE' => $sort_data['ASSORTMENT_CODE'], 'PROMO_CODE' => $sort_data['PROMO_CODE'], 'DATA' => $pai_code]);
			}

		}

		// dd($list_assortment_code);

		foreach (array_chunk($list_assortment_code, 50, true) as $key => $assort_data) {
			foreach ($assort_data as $keys => $value) {
				// dd($value);
				if ($this->test_check($item_value, $value, $value['CODE'], $value['DATA']) != 0) {
					$list_assortment[] = $this->test_check($item_value, $value, $value['CODE'], $value['DATA']);
				}
			}
		}
		// dd($list_assortment);
		// dd($distinct);
		foreach ($list_assortment as $key => $value) {
			$final_psite_promo_assign = DB::table('ananda.psite_promo_assign')->select('PROMO_CODE', 'STARTDATE', 'ENDDATE', 'PRIORITY')->where('PROMO_CODE', $value['PROMO_CODE'])->where('STATUS', 'A')->where('ADMSITE_CODE', $scode)->first();
			if (count($final_psite_promo_assign) > 0) {
				$startdate = $this->convert_in_indian_date_format($final_psite_promo_assign->STARTDATE);
				$enddate = $this->convert_in_indian_date_format($final_psite_promo_assign->ENDDATE);
				if (($today >= $startdate) && ($today <= $enddate)) {
					$promo_name = DB::table('ananda.promo_master')->select('NAME')->where('CODE', $final_psite_promo_assign->PROMO_CODE)->first();
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
		$final_available_offers = [];
		$final_applied_offers = [];
		$review = [];

		// dd($taxmain);
		// dd($final_promo_list);
		if (count($final_promo_list) > 0) {
			$all_cart = $this->filter_promotions($final_promo_list, $qty, $item->MRP, $scode, $id);
			$is_offer = 'No';
			$is_offer_apply = false;
			// dd($all_cart['merge']);
			// return $all_cart;

			// dd($all_cart);
			$final_all_cat = array();
			foreach ($all_cart['merge'] as $key => $value) {
				foreach ($value as $key => $val) {
					array_push($final_all_cat, $val);
					if (array_key_exists('promo_code', $val) && $val['promotion'] != 0) {
						$is_offer_apply = true;
						$is_offer = 'Yes';
						$promo_msg = DB::table('ananda.promo_master')->where('CODE', $val['promo_code'])->first()->NAME;
						$applied_offers[] = ['message' => $promo_msg];
						removeElementWithValue($all_cart['promo_m_slab'], 'PROMO_CODE', $val['promo_code']);
					}
				}
			}
			// dd($applied_offers);
			// if ($is_offer_apply == false) {
			//     $available_offers = [];
			// } else {
			// dd($applied_offers);
			foreach ($all_cart['promo_m_slab'] as $key => $value) {
				$available_offers[] = $value->NAME;
			}
			$available_offers = array_unique($available_offers, SORT_REGULAR);
			foreach ($available_offers as $value) {
				$final_available_offers[] = ['message' => $value];
			}
			$applied_offers = array_unique($applied_offers, SORT_REGULAR);
			// dd($applied_offers);
			foreach ($applied_offers as $values) {
				$final_applied_offers[] = ['message' => $values['message']];
			}
			// }

			// dd($available_offers);
			foreach ($final_all_cat as $key => $value) {
				$total_amount += $value['gross'];
				$total_qty += $value['qty'];
				if (array_key_exists('promotion', $value)) {
					//$total_promotion += 0;
					$total_promotion += (float) $value['promotion'];
				} else {
					$total_promotion += 0;
					//$total_promotion += $value['promotion'];
				}
				if (array_key_exists('sale_price', $value)) {
					//$total_sale_price += 0;
					if ($value['sale_price'] > 0.00 || $value['sale_price'] > 0) {
						$total_sale_price += (float) $value['sale_price'];
					} else {
						$total_sale_price += (float) $value['basic_price'];
					}

				} else {
					$total_sale_price += 0;
					//$total_sale_price += $value['sale_price'];
				}
				$total_basic_price += $value['basic_price'];
			}

			// $taxmain = DB::table('ananda.invhsnsacmain as main')
			//     ->select('main.HSN_SAC_CODE', 'det.EFFECTIVE_DATE', 'det.CODE')
			//     ->join('ananda.invhsnsacdet as det', 'main.CODE', '=', 'det.INVHSNSACMAIN_CODE')
			//     ->where('main.CODE', $item->INVHSNSACMAIN_CODE)
			//     ->first();

			// $taxslab = DB::table('ananda.invhsnsacslab as slab')
			//         ->select('slab.AMOUNT_FROM', 'slab.INVGSTRATE_CODE','gst.TAX_NAME','gst.CGST_RATE','gst.SGST_RATE','gst.CESS_RATE','gst.IGST_RATE')
			//         ->join('ananda.invgstrate as gst', 'slab.INVGSTRATE_CODE', 'gst.CODE')
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
		// dd($total_sale_price);
		$vendorS = new VendorSettingController;
		$product_default_image = $vendorS->getProductDefaultImage(['v_id' => $v_id, 'trans_from' => $trans_from]);

		// dd($final_promo_list);
		if ($cart == 1) {
			$push_data = [
				'p_id' => $id,
				'category' => $item->CNAME1,
				'brand_name' => $item->CNAME2,
				'sub_categroy' => '',
				'p_name' => $id . ' ' . $group->GRPNAME,
				'offer' => $is_offer,
				'offer_data' => ['applied_offers' => array_unique($applied_offers, SORT_REGULAR), 'available_offers' => array_unique($available_offers, SORT_REGULAR)],
				'multiple_price_flag' => false,
				'multiple_mrp' => [$this->format_and_string($item->MRP)],
				'unit_mrp' => $this->format_and_string($item->MRP),
				'r_price' => $this->format_and_string($total_basic_price),
				's_price' => $this->format_and_string($total_sale_price),
				'discount' => $this->format_and_string($total_promotion),
				'varient' => '',
				'images' => $product_default_image,
				'description' => '',
				'deparment' => '',
				'barcode' => $id,
				'whishlist' => 'No',
				'weight_flag' => false,
				'quantity_change_flag' => true,
				'carry_bag_flag' => false,
			];
			// dd($push_data);
		} else {
			$push_data = [
				'p_id' => $id,
				'category' => $item->CNAME1,
				'brand_name' => $item->CNAME2,
				'sub_categroy' => '',
				'style_code' => '',
				'p_name' => $id . ' ' . $item->CNAME1,
				'offer' => $is_offer,
				'offer_data' => (object) ['applied_offers' => $final_applied_offers, 'available_offers' => $final_available_offers],
				'qty' => $qty,
				'multiple_price_flag' => false,
				'multiple_mrp' => [$this->format_and_string($item->MRP)],
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
				'whishlist' => 'No',
			];
		}
		return $push_data;
	}

	public function filter_promotions($promos, $qty, $amt, $scode, $barcode) {
		// dd($promos);
		$data = $listing_promo = $final_product = $promo_carts = $regular_carts = array();
		$total_qty = $qty;
		$basic_price = $amt;
		$qunatity_check = array();
		$promo_list = implode(",", array_column($promos, 'PROMO_CODE'));
		$promo_list = explode(",", $promo_list);
		// echo $promo_list;
		$promo_list = array_unique($promo_list);
		// dd($promo_list);
		// $promo_m_priority = DB::table('ananda.promo_master')
		//                 ->select('psite_promo_assign.PRIORITY','promo_master.NAME','promo_master.BASIS','psite_promo_assign.PROMO_CODE')
		//                 ->join('ananda.psite_promo_assign', 'promo_master.CODE', '=', 'psite_promo_assign.PROMO_CODE')
		//                 ->where('psite_promo_assign.ADMSITE_CODE',$scode)
		//                 ->whereIn('promo_master.CODE', $promo_list)
		//                 ->orderBy('psite_promo_assign.PRIORITY', 'desc')
		//                 ->get();

		$promo_m_slab = DB::table('ananda.promo_slab')
			->select('promo_slab.SIMPLE_FACTOR', 'promo_slab.SLAB_RANGE_FROM', 'promo_slab.SLAB_RANGE_TO', 'promo_slab.GET_BENEFIT_CODE', 'promo_slab.GET_FACTOR', 'promo_slab.GET_ASSORTMENT_CODE', 'promo_slab.DISCOUNT_TYPE', 'promo_slab.DISCOUNT_PRICE_BASIS', 'promo_slab.SLAB_CODE', 'promo_master.NAME', 'promo_master.TYPE', 'promo_master.BASIS', 'promo_master.BUY_FACTOR_TYPE', 'psite_promo_assign.PRIORITY', 'promo_slab.PROMO_CODE')
			->join('ananda.psite_promo_assign', 'promo_slab.PROMO_CODE', '=', 'psite_promo_assign.PROMO_CODE')
			->join('ananda.promo_master', 'promo_slab.PROMO_CODE', '=', 'promo_master.CODE')
			->where('psite_promo_assign.ADMSITE_CODE', $scode)
			->whereIn('promo_slab.PROMO_CODE', $promo_list)
			->orderBy('psite_promo_assign.PRIORITY', 'desc')
			->orderBy('promo_slab.SLAB_CODE', 'desc')
			->get();

		// $promo_m_slab = DB::table('ananda.psite_promo_assign')
		//                 ->select('psite_promo_assign.PRIORITY','promo_slab.SIMPLE_FACTOR','promo_slab.SLAB_RANGE_FROM','promo_slab.SLAB_RANGE_TO','promo_slab.GET_BENEFIT_CODE','promo_slab.GET_FACTOR','promo_slab.GET_ASSORTMENT_CODE','promo_slab.DISCOUNT_TYPE','promo_slab.DISCOUNT_PRICE_BASIS','psite_promo_assign.PROMO_CODE','promo_slab.SLAB_CODE','promo_master.NAME','promo_master.TYPE','promo_master.BASIS','promo_master.BUY_FACTOR_TYPE')
		//                 ->join('ananda.promo_slab', 'psite_promo_assign.PROMO_CODE', '=', 'promo_slab.PROMO_CODE')
		//                 ->join('ananda.promo_master', 'psite_promo_assign.PROMO_CODE', '=', 'promo_master.CODE')
		//                 ->where('psite_promo_assign.ADMSITE_CODE',$scode)
		//                 ->whereIn('psite_promo_assign.PROMO_CODE', $promo_list)
		//                 ->orderBy('psite_promo_assign.PRIORITY', 'desc')
		//                 // ->orderBy('promo_slab.SLAB_CODE', 'desc')
		//                 ->get();

		// $promo_m_slab = array_unique($promo_m_slab);

		// dd($promo_m_slab);

		foreach ($promo_m_slab as $key => $value) {
			if (!empty($value->BASIS)) {
				if ($value->BASIS == 'QSIMPLE') {
					$slab = $value->SIMPLE_FACTOR;
				} else if ($value->BASIS == 'QSLAB') {
					$slab = $value->SLAB_RANGE_FROM;
				}
				$listing_promo[] = array(
					'PRIORITY' => $value->PRIORITY,
					'NAME' => $value->NAME,
					'TYPE' => $value->BASIS,
					'PROMO_CODE' => $value->PROMO_CODE,
					'SLAB_CODE' => $value->SLAB_CODE,
					'QTY' => (int) $slab,
				);
			}
		}
		// dd($listing_promo);
		rsort($listing_promo);
		$remove_qty = array_unique(array_column($listing_promo, 'QTY'), SORT_REGULAR);
		$listing_promo_final = array_intersect_key($listing_promo, $remove_qty);
		// dd($listing_promo_final);
		$max = max(array_column($listing_promo_final, 'QTY'));
		// dd($max);
		while ($qty > 0) {
			if ($this->in_multiarray($qty, $listing_promo, "QTY")) {
				// echo 'FIND Qty :- '.$qty.'<br>';
				array_push($final_product, ['qty' => $qty, 'is_promo' => 1, 'o_promo' => 1]);
				$qty = $qty - $qty;
			} else {
				if ($qty > $max) {
					// echo 'LARGE Qty :- '.$max.'<br>';
					$qty = $qty - $max;
					array_push($final_product, ['qty' => $max, 'is_promo' => 1, 'o_promo' => 0]);
				} else {
					array_push($final_product, ['qty' => $qty, 'is_promo' => 0, 'o_promo' => 1]);
					$qty = 0;
				}
			}
		}
		// dd($final_product);
		foreach ($final_product as $key => $value) {
			if ($value['is_promo'] == 1) {
				$id = $this->searchQty($value['qty'], $listing_promo);
				$promo_slab = DB::table('ananda.promo_slab')
					->select('promo_slab.*', 'promo_master.BASIS')
					->join('ananda.promo_master', 'promo_slab.PROMO_CODE', 'promo_master.CODE')
					->where('SLAB_CODE', $id)
					->first();

				if ($promo_slab->BASIS == 'QSLAB' || $promo_slab->BASIS == 'QSIMPLE') {
					$regular_carts[] = $this->quantity_based($promo_slab, $basic_price, $value['qty'], $barcode, $promos[0]['ASSORTMENT_CODE']);
				}
			} elseif ($value['o_promo'] == 1) {
				// dd($listing_promo_final[0]['SLAB_CODE']);
				$promo_slab = DB::table('ananda.promo_slab')
					->select('promo_slab.*', 'promo_master.BASIS', 'promo_master.BUY_FACTOR_TYPE')
					->join('ananda.promo_master', 'promo_slab.PROMO_CODE', 'promo_master.CODE')
					->where('SLAB_CODE', $listing_promo_final[0]['SLAB_CODE'])
					->first();
				if ($promo_slab->BASIS == 'QSLAB' || $promo_slab->BASIS == 'QSIMPLE') {
					$regular_carts[] = $this->quantity_based($promo_slab, $basic_price, $value['qty'], $barcode, $promos);
				}
			} else {
				$regular_carts[][] = array('basic_price' => $amt, 'promotion' => '', 'sale_price' => '', 'qty' => $value['qty'], 'gross' => $amt);
			}
		}
		// dd($regular_carts);
		$merge = array_merge($promo_carts, $regular_carts);
		// dd($merge
		$push_array = ['promo_m_slab' => $promo_m_slab, 'merge' => $merge];
		return $push_array;
	}

	public function quantity_based($value, $mrp, $qty, $id, $sort) {
		// dd($value);
		// $sort_code = explode("-", $sort);
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
				array_push($data, ['basic_price' => $basic_price, 'promotion' => $promotion * $qty, 'sale_price' => $sale_price, 'gross' => $gross, 'qty' => $qty, 'slab_code' => $value->SLAB_CODE, 'promo_code' => $value->PROMO_CODE]);
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
				array_push($data, ['basic_price' => $basic_price * $value->GET_FACTOR, 'promotion' => $pool_promotion * $value->GET_FACTOR, 'sale_price' => $pool_sale * $value->GET_FACTOR * $value->GET_FACTOR, 'gross' => $pool_gross * $value->GET_FACTOR, 'qty' => $value->GET_FACTOR, 'slab_code' => $value->SLAB_CODE, 'promo_code' => $value->PROMO_CODE]);
				array_push($data, ['basic_price' => $basic_price * $multiple_qty, 'promotion' => $promotion * $multiple_qty, 'sale_price' => $mrp * $multiple_qty, 'gross' => $gross * $multiple_qty, 'qty' => $multiple_qty, 'slab_code' => $value->SLAB_CODE, 'promo_code' => $value->PROMO_CODE]);
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
				array_push($data, ['basic_price' => $basic_price, 'promotion' => $promotion, 'sale_price' => $sale_price, 'gross' => $gross, 'qty' => $qty, 'slab_code' => $value->SLAB_CODE, 'promo_code' => $value->PROMO_CODE]);
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
				// dd($qty);
				// Check if qty same as simple factor
				if ($qty == $value->SIMPLE_FACTOR) {
					if ($value->DISCOUNT_FACTOR == '0.100') {
						array_push($data, ['basic_price' => $basic_price * $value->GET_FACTOR, 'promotion' => $pool_promotion * $value->GET_FACTOR, 'sale_price' => $pool_sale * $value->GET_FACTOR * $value->GET_FACTOR, 'gross' => $pool_gross * $value->GET_FACTOR, 'qty' => $value->GET_FACTOR, 'slab_code' => $value->SLAB_CODE, 'promo_code' => $value->PROMO_CODE]);
						array_push($data, ['basic_price' => $basic_price * $multiple_qty, 'promotion' => $promotion * $multiple_qty, 'sale_price' => $mrp * $multiple_qty, 'gross' => $gross * $multiple_qty, 'qty' => $multiple_qty, 'slab_code' => $value->SLAB_CODE, 'promo_code' => $value->PROMO_CODE]);
					}
				} else {
					// dd($sort);
					$all_barcode = [];
					$sort_code = $this->searchAssort($value->PROMO_CODE, $sort);
					// dd($sort_code);
					foreach ($sort_code as $pcode) {
						$get_assort = DB::table('ananda.promo_assortment_include')->where('ASSORTMENT_CODE', $pcode['ASSORTMENT_CODE'])->get();
						foreach ($get_assort as $sort_value) {
							$barcode = $this->getAllBarcodeByAssort($sort_value);
							// array_push($all_barcode, $barcode);
							if (is_array($barcode)) {
								foreach ($barcode as $bcode) {
									array_push($all_barcode, $bcode->ICODE);
								}
							} else {
								array_push($all_barcode, $barcode);
							}
						}
					}
					// dd($all_barcode);
					$cart_count = DB::table('cart')->where('user_id', Auth::id())->where('status', 'process')->where('item_id', '!=', $id)->whereIn('item_id', $all_barcode)->sum('qty');
					$total_qty = $cart_count + $qty;
					$total_qty = (int) $total_qty;
					// dd($total_qty);
					if ($total_qty > $value->SIMPLE_FACTOR) {
						do {
							$total_qty = $total_qty - $value->SIMPLE_FACTOR;
							// dd($total_qty);
						} while ($value->SIMPLE_FACTOR < $total_qty);
						// dd($total_qty);
						if ($total_qty == $value->SIMPLE_FACTOR) {
							if ($value->DISCOUNT_FACTOR == '0.100') {
								array_push($data, ['basic_price' => $basic_price, 'promotion' => $basic_price - 0.10, 'sale_price' => 0.10, 'gross' => 0, 'qty' => $qty, 'slab_code' => $value->SLAB_CODE, 'promo_code' => $value->PROMO_CODE]);
							}
							// dd($data);
						} else {
							array_push($data, ['basic_price' => $basic_price * $multiple_qty, 'promotion' => 0, 'sale_price' => $basic_price * $multiple_qty, 'gross' => $gross * $multiple_qty, 'qty' => $multiple_qty, 'slab_code' => $value->SLAB_CODE, 'promo_code' => $value->PROMO_CODE]);
						}
					} else {
						if ($total_qty == $value->SIMPLE_FACTOR) {
							if ($value->DISCOUNT_FACTOR == '0.100') {
								array_push($data, ['basic_price' => $basic_price, 'promotion' => $basic_price - 0.10, 'sale_price' => 0.10, 'gross' => 0, 'qty' => $qty, 'slab_code' => $value->SLAB_CODE, 'promo_code' => $value->PROMO_CODE]);
							}
							// dd($data);
						} else {
							array_push($data, ['basic_price' => $basic_price * $multiple_qty, 'promotion' => 0, 'sale_price' => $basic_price * $multiple_qty, 'gross' => $gross * $multiple_qty, 'qty' => $multiple_qty, 'slab_code' => $value->SLAB_CODE, 'promo_code' => $value->PROMO_CODE]);
						}
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

	public function getAllBarcodeByAssort($array) {
		$values = [];
		$keys = [];
		// dd($array);
		foreach ($array as $key => $value) {
			if (!empty($value)) {
				if ($key != 'ASSORTMENT_CODE' && $key != 'CODE' && $key != 'PRICE_RANGE_BASIS' && $key != 'PRICE_RANGE_FROM' && $key != 'PRICE_RANGE_TO' && $key != 'STOCKINDATE_FROM' && $key != 'STOCKINDATE_TO' && $key != 'QTY' && $key != 'ITEM_REWARD_VALUE') {
					// $values[] = $value;
					array_push($values, $value);
				}
			}
			$keys[$key] = $value;
		}
		// dd($keys);
		// krsort($values);
		$cloumn_name = array_search(end($values), $keys);
		if ($cloumn_name == 'ICODE') {
			return end($values);
		} else {
			if ($cloumn_name != 'DIVISION_GRPCODE' || $cloumn_name != 'SECTION_GRPCODE' || $cloumn_name != 'DEPARTMENT_GRPCODE' || $cloumn_name != 'INVARTICLE_CODE') {
				$inbar = DB::table('ananda.invitem')->where($cloumn_name, end($values))->get(['ICODE'])->toArray();
				return $inbar;
			}
		}
	}

	public function searchQty($id, $array) {
		foreach ($array as $key => $value) {
			if ($value['QTY'] == $id) {
				return $value['SLAB_CODE'];
			}
		}
		return null;
	}

	public function searchAssort($id, $array) {
		$data = [];
		foreach ($array as $key => $value) {
			if ($value['PROMO_CODE'] == $id) {
				$data[] = $value;
			}
		}
		return $data;
	}

	function in_multiarray($elem, $array, $field) {
		$top = sizeof($array) - 1;
		$bottom = 0;
		while ($bottom <= $top) {
			if ($array[$bottom][$field] == $elem) {
				return true;
			} else
			if (is_array($array[$bottom][$field])) {
				if (in_multiarray($elem, ($array[$bottom][$field]))) {
					return true;
				}
			}

			$bottom++;
		}
		return false;
	}

	public function test_check($item, $acode, $code, $datas) {
		// dd($data->DIVISION_GRPCODE);
		$data = array();
		$return_data = [];
		// $promo_assortment_include = DB::table('ananda.promo_assortment_include')->where('ASSORTMENT_CODE', $acode['ASSORTMENT_CODE'])->where('CODE', (int)$code)->first();
		// $promo_assortment_exclude = DB::table('ananda.promo_assortment_exclude')->where('ASSORTMENT_CODE', $acode)->first();
		// $data['ASSORTMENT_CODE'] = $id;
		// dd($promo_assortment_include);
		$data['inc_division'] = $this->includeMatchCheckNullCheck($item['division'], $datas->DIVISION_GRPCODE);
		$data['inc_section'] = $this->includeMatchCheckNullCheck($item['section'], $datas->SECTION_GRPCODE);
		$data['inc_department'] = $this->includeMatchCheckNullCheck($item['department'], $datas->DEPARTMENT_GRPCODE);
		$data['inc_article'] = $this->includeMatchCheckNullCheck($item['article'], $datas->INVARTICLE_CODE);
		$data['inc_icode'] = $this->includeMatchCheckNullCheck($item['icode'], $datas->ICODE);
		$data['inc_category_1'] = $this->includeMatchCheckNullCheck($item['category_1'], $datas->CCODE1);
		$data['inc_category_2'] = $this->includeMatchCheckNullCheck($item['category_2'], $datas->CCODE2);
		$data['inc_category_3'] = $this->includeMatchCheckNullCheck($item['category_3'], $datas->CCODE3);
		$data['inc_category_4'] = $this->includeMatchCheckNullCheck($item['category_4'], $datas->CCODE4);
		$data['inc_category_5'] = $this->includeMatchCheckNullCheck($item['category_5'], $datas->CCODE5);
		$data['inc_category_6'] = $this->includeMatchCheckNullCheck($item['category_6'], $datas->CCODE6);
		$data['inc_stock_check'] = $this->includeMatchCheckNullOrDateCheck($item['stock_date'], $datas);
		$data['inc_price_range'] = $this->includeMatchCheckNullOrRangerCheck($item['mrp'], $datas);
		// $data['code'] = $acode.'-'.$code;
		// $acode = array_push($acode, ['CODE' => $code]);
		$return_data = ['ASSORTMENT_CODE' => $acode['ASSORTMENT_CODE'], 'PROMO_CODE' => $acode['PROMO_CODE'], 'CODE' => $code];
		if (in_array(2, $data)) {
			return 0;
		} else {
			return $return_data;
		}
	}

	public function includeMatchCheckNullCheck($ivalue, $pvalue) {
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

	public function includeMatchCheckNullOrDateCheck($ivalue, $pvalue) {
		// dd($pvalue);
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

	public function includeMatchCheckNullOrRangerCheck($ivalue, $pvalue) {
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

	public function final_cross_check($id, $item, $group, $article, $division, $section, $column, $qid) {
		// dd($id);
		$data = array();
		$group = $group;
		$article = $article;
		$division = $division;
		$section = $section;
		$total = array();
		$etotal = array();
		$promo_assortment_include = DB::table('ananda.promo_assortment_include')->where('ASSORTMENT_CODE', $id)->where($column, $qid)->first();
		$promo_assortment_exclude = DB::table('ananda.promo_assortment_exclude')->where('ASSORTMENT_CODE', $id)->where($column, $qid)->first();
		$fdivision = $fsection = $fdepartment = $farticle = $ficode = $fcategory_1 = $fcategory_2 = $fcategory_3 = $fcategory_4 = $fcategory_5 = $fcategory_6 = '';
		$data['ASSORTMENT_CODE'] = $id;

		if (!empty($promo_assortment_include->DIVISION_GRPCODE)) {
			// $data['DIVISION_GRPCODE'] = $division;
			if ($division == $promo_assortment_include->DIVISION_GRPCODE) {
				array_push($total, 2);
			} else {
				array_push($total, 1);
			}
		}
		if (!empty($promo_assortment_include->SECTION_GRPCODE)) {
			// $data['SECTION_GRPCODE'] = $section;
			if ($section == $promo_assortment_include->SECTION_GRPCODE) {
				array_push($total, 2);
			} else {
				array_push($total, 1);
			}
		}
		if (!empty($promo_assortment_include->DEPARTMENT_GRPCODE)) {
			// $data['DEPARTMENT_GRPCODE'] = $group;
			if ($group == $promo_assortment_include->DEPARTMENT_GRPCODE) {
				array_push($total, 2);
			} else {
				array_push($total, 1);
			}
		}
		if (!empty($promo_assortment_include->INVARTICLE_CODE)) {
			// $data['INVARTICLE_CODE'] = $article;
			if ($article == $promo_assortment_include->INVARTICLE_CODE) {
				array_push($total, 2);
			} else {
				array_push($total, 1);
			}
		}
		if (!empty($promo_assortment_include->ICODE)) {
			// $data['ICODE'] = $item->ICODE;
			if ($item->ICODE == $promo_assortment_include->ICODE) {
				array_push($total, 2);
			} else {
				array_push($total, 1);
			}
		}

		if (!empty($promo_assortment_include->CCODE1)) {
			// $data['CCODE1'] = $item->CCODE1;
			if ($item->CCODE1 == $promo_assortment_include->CCODE1) {
				array_push($total, 2);
			} else {
				array_push($total, 1);
			}
		}

		if (!empty($promo_assortment_include->CCODE2)) {
			// $data['CCODE2'] = $item->CCODE2;
			if ($item->CCODE2 == $promo_assortment_include->CCODE2) {
				array_push($total, 2);
			} else {
				array_push($total, 1);
			}
		}

		if (!empty($promo_assortment_include->CCODE3)) {
			// $data['CCODE3'] = $item->CCODE3;
			if ($item->CCODE3 == $promo_assortment_include->CCODE3) {
				array_push($total, 2);
			} else {
				array_push($total, 1);
			}
		}

		if (!empty($promo_assortment_include->CCODE4)) {
			// $data['CCODE4'] = $item->CCODE4;
			if ($item->CCODE4 == $promo_assortment_include->CCODE4) {
				array_push($total, 2);
			} else {
				array_push($total, 1);
			}
		}

		if (!empty($promo_assortment_include->CCODE5)) {
			// $data['CCODE5'] = $item->CCODE5;
			if ($item->CCODE5 == $promo_assortment_include->CCODE5) {
				array_push($total, 2);
			} else {
				array_push($total, 1);
			}
		}

		if (!empty($promo_assortment_include->CCODE6)) {
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

		if (!empty($promo_assortment_exclude->DIVISION_GRPCODE)) {
			// $data['DIVISION_GRPCODE'] = $division;
			if ($division == $promo_assortment_exclude->DIVISION_GRPCODE) {
				array_push($etotal, 1);
			} else {
				array_push($etotal, 2);
			}
		}
		if (!empty($promo_assortment_exclude->SECTION_GRPCODE)) {
			// $data['SECTION_GRPCODE'] = $section;
			if ($section == $promo_assortment_exclude->SECTION_GRPCODE) {
				array_push($etotal, 1);
			} else {
				array_push($etotal, 2);
			}
		}
		if (!empty($promo_assortment_exclude->DEPARTMENT_GRPCODE)) {
			// $data['DEPARTMENT_GRPCODE'] = $group;
			if ($group == $promo_assortment_exclude->DEPARTMENT_GRPCODE) {
				array_push($etotal, 1);
			} else {
				array_push($etotal, 2);
			}
		}
		if (!empty($promo_assortment_exclude->INVARTICLE_CODE)) {
			// $data['INVARTICLE_CODE'] = $article;
			if ($article == $promo_assortment_exclude->INVARTICLE_CODE) {
				array_push($etotal, 1);
			} else {
				array_push($etotal, 2);
			}
		}
		if (!empty($promo_assortment_exclude->ICODE)) {
			// $data['ICODE'] = $item->ICODE;
			if ($item->ICODE == $promo_assortment_exclude->ICODE) {
				array_push($etotal, 1);
			} else {
				array_push($etotal, 2);
			}
		}

		if (!empty($promo_assortment_exclude->CCODE1)) {
			// $data['CCODE1'] = $item->CCODE1;
			if ($item->CCODE1 == $promo_assortment_exclude->CCODE1) {
				array_push($etotal, 1);
			} else {
				array_push($etotal, 2);
			}
		}

		if (!empty($promo_assortment_exclude->CCODE2)) {
			// $data['CCODE2'] = $item->CCODE2;
			if ($item->CCODE2 == $promo_assortment_exclude->CCODE2) {
				array_push($etotal, 1);
			} else {
				array_push($etotal, 2);
			}
		}

		if (!empty($promo_assortment_exclude->CCODE3)) {
			// $data['CCODE3'] = $item->CCODE3;
			if ($item->CCODE3 == $promo_assortment_exclude->CCODE3) {
				array_push($etotal, 1);
			} else {
				array_push($etotal, 2);
			}
		}

		if (!empty($promo_assortment_exclude->CCODE4)) {
			// $data['CCODE4'] = $item->CCODE4;
			if ($item->CCODE4 == $promo_assortment_exclude->CCODE4) {
				array_push($etotal, 1);
			} else {
				array_push($etotal, 2);
			}
		}

		if (!empty($promo_assortment_exclude->CCODE5)) {
			// $data['CCODE5'] = $item->CCODE5;
			if ($item->CCODE5 == $promo_assortment_exclude->CCODE5) {
				array_push($etotal, 1);
			} else {
				array_push($etotal, 2);
			}
		}

		if (!empty($promo_assortment_exclude->CCODE6)) {
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
					return $id . '-' . $promo_assortment_include->CODE;
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

	public function convert_in_indian_date_format($date) {
		if (strpos($date, "-") !== false) {
			$fdate = explode("-", $date);
		} else if (strpos($date, "/") !== false) {
			$fdate = explode("/", $date);
		}
		// print_r($fdate);
		return date('Y-m-d', strtotime($fdate[2] . '-' . $fdate[0] . '-' . $fdate[1]));
	}

	function format_and_string($value) {
		return (string) sprintf('%0.2f', $value);
	}
}
