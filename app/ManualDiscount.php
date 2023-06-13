<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ManualDiscount extends Model
{
    //

    protected $table 	  = 'manual_discounts';
	protected $primaryKey = 'id';
	protected $fillable   = ['v_id','name','description','discount_code','applicable_level','assortment_id','assortment_name', 'discount_type','discount_factor','status','effective_date','valid_upto','created_by'];

}
