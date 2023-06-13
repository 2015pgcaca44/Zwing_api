<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LoyaltyCustomer extends Model
{
	protected $table = 'loyalty_customers';

	protected $fillable = ['vendor_id', 'store_id', 'loyalty_id', 'user_id', 'mobile', 'type', 'is_created'];
}
