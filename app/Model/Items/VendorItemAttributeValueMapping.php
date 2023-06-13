<?php

namespace App\Model\Items;

use Illuminate\Database\Eloquent\Model;

class VendorItemAttributeValueMapping extends Model
{
	protected $table 	  = 'vendor_item_attribute_value_mapping';
	protected $primaryKey = 'id';
	protected $fillable   = ['v_id','item_id','item_attribute_id','item_attribute_value_id'];
}
