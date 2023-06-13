<?php

namespace App\Model\Charges;

use Illuminate\Database\Eloquent\Model;

class ChargeCategorySlab extends Model
{
    protected $table = 'charge_category_slab';
   	public $timestamps = false;
    protected $fillable = ['charge_group_id','charge_cat_id','amount_from','amount_to'];
}
