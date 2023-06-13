<?php

namespace App\Model\Charges;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChargeGroup extends Model
{
    //charge_group
    use SoftDeletes;
    protected $table = 'charge_group';

    protected $fillable = ['code','v_id','vu_id','name','slab','applicable_on','deleted_by'];

}
