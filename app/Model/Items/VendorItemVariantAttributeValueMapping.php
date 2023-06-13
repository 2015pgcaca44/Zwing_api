<?php

namespace App\Model\Items;

use Illuminate\Database\Eloquent\Model;

class VendorItemVariantAttributeValueMapping extends Model
{
    protected $table 	=  'vendor_item_variant_attribute_value_mapping';
    	protected $primaryKey = 'id';
	protected $fillable =  ['v_id','item_id','item_variant_attribute_id','item_variant_attribute_value_id'];
}
