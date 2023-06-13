<?php

namespace App\Items;

use Illuminate\Database\Eloquent\Model;

class VendorItemCategoryIds extends Model
{
	protected $table 	  = 'vendor_item_category_ids';
	protected $primaryKey = 'id';
	protected $fillable   = ['v_id','item_category_id','level'];
}
