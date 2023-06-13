<?php

namespace App\Model\Items;

use Illuminate\Database\Eloquent\Model;

class VendorItemVariantAttributes extends Model
{
    protected $table 	  =  'vendor_item_variant_attributes';
    protected $primaryKey = 'id';
	protected $fillable   =  ['v_id','item_variant_attribute_id'];
}
