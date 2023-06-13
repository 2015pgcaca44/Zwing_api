<?php

namespace App\Model\Charges;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChargeRates extends Model
{
	use SoftDeletes;
    protected $table = 'charge_rates';

    protected $fillable = ['code','v_id','vu_id','name','rate','type','deleted_by'];

}
