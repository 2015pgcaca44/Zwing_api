<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PhonepeMerchant extends Model
{
	protected $table = 'phonepe_merchant';
    protected $primaryKey = 'id'; 
	protected $fillable = ['v_id', 'merchant_id'];
}
