<?php

namespace App\Model\Charges;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChargeCodeCat extends Model
{
	use SoftDeletes;
    protected $table = 'charge_code_cat';
    protected $fillable = ['v_id','store_id','chargecode','charge_cat_id','description','deleted_by'];
}
