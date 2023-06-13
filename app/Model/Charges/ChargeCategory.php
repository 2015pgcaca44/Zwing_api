<?php

namespace App\Model\Charges;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChargeCategory extends Model
{
    use SoftDeletes;
    protected $table = 'charge_category';
    protected $fillable = ['code','v_id','vu_id','name','slab','applicable_on','deleted_by'];
}
