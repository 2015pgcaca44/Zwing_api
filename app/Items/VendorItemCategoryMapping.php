<?php

namespace App\Items;

use Illuminate\Database\Eloquent\Model;

class VendorItemCategoryMapping extends Model
{
    protected $table 	  = 'vendor_item_category_mapping';
	protected $primaryKey = 'id';
	protected $fillable   = ['v_id','item_id','item_category_id'];
}
