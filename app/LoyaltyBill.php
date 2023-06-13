<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LoyaltyBill extends Model
{
	protected $table = 'loyalty_bills';

	protected $fillable = ['vendor_id', 'store_id', 'user_id', 'mobile', 'invoice_no', 'type', 'is_submitted'];
}
