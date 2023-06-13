<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PhonepeTransactions extends Model
{
     protected $table = 'phonepe_transactions';
	 protected $primaryKey = 'id';
	 protected $fillable = ['v_id','store_id','merchant_id', 'transaction_id','provider_reference_id','mobile','amount','amount_in_rupee','code','payment_state','remark','gateway_response','api_type'];
}
