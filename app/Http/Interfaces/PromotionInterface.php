<?php 

namespace App\Http\Interfaces;

interface PromotionInterface {
	
	/**
     * Get the all details of item which is related to offer
     *
     * @param  Array  $params
     * @return Array|Object  Items get return with offer details
     */
	public function getItemOfferDetails($params);


	/**
	 * Get all the Offer of paticular items
	 * 
	 * @param  Array $params 
	 * @return Array|Object All Offer of paticular items
	 */
	public function getAllPromotions($params);

	
	/**
	 * filtering all offer based on validation such date or status 
	 * 
	 * @param  Array $params 
	 * @return Array|Object All Offer of paticular items
	 */
	public function filterAllPromotions($params);


	/**
	 * This function will calculate All Rule / Promotions
	 * 
	 */
	public function calculatingAllPromotions($params);


	/**
	 * This function will calculate Individual Rule / Promotions
	 * 
	 */
	public function calculatingIndividualPromotions($params);
	
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
	public function calculateDiscount($params);


}