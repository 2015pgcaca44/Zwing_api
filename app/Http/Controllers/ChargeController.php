<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Model\Charges\ChargeRates;
use App\Model\Charges\ChargeGroup;
use App\Model\Charges\ChargeRateGroupMapping;
use App\Model\Charges\ChargeGroupSlab;
use App\Http\Controllers\Promotion\DiscountController;

class ChargeController extends Controller
{
	public function __construct()
	{
		$this->middleware('auth');
	}

	public function calculate($params)
	{
		$charge = 0;
		$name = ''; 
		$charge_rate = 0;
		$id = null;
		if(!empty($params['charge_group_id']) && !empty($params['amount'])){
			$chargeGroup = ChargeGroup::select('id','slab','name')->find($params['charge_group_id']);
			$rate = null;
			$name = $chargeGroup->name;
			$id = $chargeGroup->id;
			if($chargeGroup){
				if($chargeGroup->slab == 'NO'){
					$group = ChargeRateGroupMapping::select('charge_rate_id')->where('charge_group_id', $params['charge_group_id'])->first();
					if($group){
						$rate = ChargeRates::select('type','rate')->first($group->charge_rate_id);
						$charge_rate = $rate->rate;
					}
				}else if($chargeGroup->slab == 'YES'){
					$slab = ChargeGroupSlab::select('id')->where('charge_group_id', $params['charge_group_id'])->where('amount_from','>=', $params['amount'])->where('amount_to','<=',$params['amount'])->first();
					if($slab){
						$groups = ChargeRateGroupMapping::select('charge_rate_id')->where('charge_group_id', $params['charge_group_id'])->where('group_slab_id', $slab->id)->get();
						if($groups){
							$rate = ChargeRates::select('type','rate')->first($group->charge_rate_id);
							$charge_rate = $rate->rate;
						}
					}
				}

				if($rate){
					$charge = (new DiscountController($params['amount'], $rate->type, $rate->rate))->calculateDiscount();

					$charge = $charge['discount'];
				}

			}

		}

		return [ 'charge' => $charge , 'name' => $name ,'rate' => $charge_rate , 'id' => $id ];
	}

	

}
