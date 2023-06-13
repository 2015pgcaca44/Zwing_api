<?php

namespace App\Http\Controllers\Promotion;

use App\Http\Controllers\Controller;

class PromoController extends Controller
{
	/**
	 * Discount class for calculating Discount
	 * @var Class
	 */
	private $discount;

	/**
	 * Type of Promo i.e Quantity, QuantitySlab, ValueSlab
	 * value - QSIMPLE, QSLAB, VSLAB 
	 * @var String
	 */
	private $promoType;



	/**
	 * PromoType class PromoQty class Or PromoValue Class
	 * @var Class
	 */
	private $promoTypeC; 

	/**
	 * Type of Buy Factor i.e Specific Qty, Any Qty, Ratio 
	 * value - S, A, R 
	 * @var String
	 */
	private $buyFactorType; 

	private $carts;

	public function __construct(DiscountController $dis, PromoTypeController $promoType){
		$this->discount = $dis;
		$this->promoType = $promoType;
	}


	public function calculate(){

		if( $this->promoType == 'QSIMPLE' ){
			$this->promoTypeC = new PromoQtyController;
		}else if($this->promoType == 'QSLAB'){
			$this->promoTypeC = new PromoQtySlabController;
		}else if($this->promoType == 'VSLAB'){
			$this->promoTypeC = new PromoValueController;
		}

		$this->promoTypeC->calculate();
		
	}






}