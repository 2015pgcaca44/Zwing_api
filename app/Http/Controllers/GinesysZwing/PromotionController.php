<?php

namespace App\Http\Controllers\Vmart;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VendorSettingController;
use App\Http\Interfaces\PromotionInterface;
use Auth;
use DB;

class PromotionController extends Controller implements PromotionInterface {

	/**
	 * 1) Item is not linked with store
	 * 2) Division, section are getting from name , it should be get from id
	 */
	
	public function index($params){
		
		$item = $params['item'];
		$v_id = $params['v_id'];
		$trans_from = $params['trans_from'];
		$id = $params['barcode'];
		$qty = $params['qty'];
		$mapping_store_id = $params['mapping_store_id'];
		$carts = $params['carts'];

		$item = $params['item'] = $this->getItemOfferDetails($params);

		dd($item);

	}



	/**
     * Get the all details of item which is related to offer
     *
     * @param  Array  $params
     * @return Array|Object  Items get return with offer details
     */
	public function getItemOfferDetails($params){
		
		$item = $params['item'];
		$mapping_store_id = $params['mapping_store_id'];

		$article = DB::table('vmart.invarticle')->select('CODE', 'NAME')->where('CODE', $item->INVARTICLE_CODE)->first();
		$group = DB::table('vmart.invgrp')->select('LEV1GRPNAME', 'LEV2GRPNAME', 'GRPCODE', 'GRPNAME', 'PARCODE')->where('GRPCODE', $item->GRPCODE)->first();
		$division = DB::table('vmart.invgrp')->select('GRPCODE', 'GRPNAME','PARCODE')->where('GRPCODE', $group->PARCODE)->first();
		$section = DB::table('vmart.invgrp')->select('GRPCODE', 'GRPNAME')->where('GRPCODE', $division->PARCODE)->first();
		//$admsite = DB::table('vmart.admsite')->select('NAME')->where('CODE', $mapping_store_id)->first();

		$item->DIVISION_CODE = $division->GRPCODE;
		$item->SECTION_CODE = $section->GRPCODE;
		$item->DEPARTMENT_CODE = $item->GRPCODE;
		$item->ARTICLE_CODE = $article->CODE;
		
		
		//dd($item);
		return $item;
	}


	/**
	 * Get all the Offer of paticular items
	 * 
	 * @param  Array $params 
	 * @return Array|Object All Offer of paticular items
	 */
	public function getAllPromotions($params){

	}

	
	/**
	 * filtering all offer based on validation such date or status 
	 * 
	 * @param  Array $params 
	 * @return Array|Object All Offer of paticular items
	 */
	public function filterAllPromotions($params){


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

	}
}