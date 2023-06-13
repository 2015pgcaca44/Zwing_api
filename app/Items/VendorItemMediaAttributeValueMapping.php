<?php

namespace App\Items;

use Illuminate\Database\Eloquent\Model;

class VendorItemMediaAttributeValueMapping extends Model
{
	protected $table 	  = 'vendor_item_media_attribute_value_mapping';
	protected $primaryKey = 'id';
	protected $fillable   = ['v_id','item_id','item_media_attribute_id','item_media_attribute_value_id'];
}
