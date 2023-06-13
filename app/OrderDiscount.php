<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderDiscount extends Model
{
	use \Awobaz\Compoships\Compoships;
	protected $table = 'order_discounts';

	protected $fillable = ['v_id', 'store_id', 'order_id', 'discount_id', 'name', 'type', 'level', 'basis', 'factor', 'amount', 'item_list', 'response'];
}
