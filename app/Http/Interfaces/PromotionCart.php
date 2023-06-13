<?php 

namespace App\Http\Interfaces;


class PromotionCart implements PromotionInterface
{

	 public function getItemOfferDetails($value='')
	 {
	 	 echo "This is item offer details".$value;
	 }


	 public function getAllPromotions($value=''){
	 	echo "This is all promotions";
	 }

	 public function filterAllPromotions($value=''){
	 	echo "This is all promotions";
	 }

	 public function calculateDiscount($value=''){
	 	echo "This is all promotions";
	 }

}

?>
