<?php

namespace App\Model\Charges;

use Illuminate\Database\Eloquent\Model;

class ChargeRateGroupMapping extends Model
{
    protected $table = 'charge_rate_group_mapping';

    protected $fillable = ['charge_group_id','charge_rate_id','group_slab_id'];

}
