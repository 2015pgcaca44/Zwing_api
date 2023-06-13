<?php

namespace App\Http\Controllers\Promotion;


class DiscountController
{

	private $discountType;
	private $discountFactor;
	private $amount;


	public function __construct($amount, $discountType, $discountFactor = 0){
		$this->amount = $amount;
		$this->discountType = $discountType;
		$this->discountFactor = $discountFactor;
	}

	public function calculateDiscount(){
		$discount = 0;

		switch ($this->discountType) {
			case 'P':
				$discount = $this->calculatePerDiscount();
				break;
			case 'A':
				$discount = $this->calculateAmountDiscount();
				break;
			case 'F':
				$discount = $this->calculateFixedDiscount();
				break;
			default:
				$discount = 0;
				break;
		}	

		if($discount < 0){
			$discount = 0;
		}

		$grossAmount = $this->amount - $discount;
		return ['discount' => $discount, 'gross' => $grossAmount ];
	} 

	public function calculatePerDiscount(){
		if($this->discountFactor > 100){
            $discountFactor = 100;
        }
        return  $this->amount * $this->discountFactor / 100 ;
	}

	public function calculateAmountDiscount(){
		if($this->amount <= $this->discountFactor){
            $discountFactor = 0;
        }
        return $discountFactor ;
	}

	public function calculateFixedDiscount(){
		return $this->amount - $this->discountFactor ;
	}
}