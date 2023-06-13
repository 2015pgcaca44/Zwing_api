<?php

namespace App\Model\Charges;

use Illuminate\Database\Eloquent\Model;

class ChargeGroupSlab extends Model
{
    protected $table = 'charge_group_slab';
   	public $timestamps = false;
    protected $fillable = ['charge_group_id','amount_from','amount_to'];
}
