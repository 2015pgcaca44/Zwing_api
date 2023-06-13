<?php

namespace App\Model\Store;

use Illuminate\Database\Eloquent\Model;

class StoreAllocation extends Model
{
    
    protected $table = 'store_allocation';

	protected $fillable = ['name','v_id', 'store_id'];


	 public static $rules = array(
        'name' 		=> 'required',
    );


}
