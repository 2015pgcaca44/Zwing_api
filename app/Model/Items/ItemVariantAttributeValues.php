<?php

namespace App\Model\Items;

use Illuminate\Database\Eloquent\Model;

class ItemVariantAttributeValues extends Model
{
    protected $table 	  =  'item_variant_attribute_values';
    	protected $primaryKey = 'id';
	protected $fillable   =  ['value'];
}
