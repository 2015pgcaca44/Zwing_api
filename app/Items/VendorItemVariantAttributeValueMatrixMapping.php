<?php

namespace App\Items;

use Illuminate\Database\Eloquent\Model;

class VendorItemVariantAttributeValueMatrixMapping extends Model
{
   protected $table 	=  'vendor_item_variant_attribute_value_matrix_mapping';
   	protected $primaryKey = 'id';
   protected $fillable =  ['v_id','item_id','variant_combi','item_variant_attribute_id','item_variant_attribute_value_id'];

    public function attribute() {
        return $this->belongsTo(
            'App\Items\ItemVariantAttributes',
            'item_variant_attribute_id',
            'id');
    }

    public function value() {
        return $this->hasOne(
            'App\Items\ItemVariantAttributeValues',
            'id',
            'item_variant_attribute_value_id');
    }
}
