<?php

namespace App\Model\Items;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
	protected $table 	  = 'items';
	protected $primaryKey = 'id';
	protected $fillable   = [
	    'name', 'short_description', 'long_description','sku', 'uom_id', 'mrp', 'department_id', 'brand_id'
    ];

	public static $rules = array(
		'name' 		=> 'required'
	);


	public function productAttribute() {
		return $this->belongsToMany(
		    'App\Model\ItemAttributes',
            'vendor_item_attribute_value_mapping',
            'item_id',
            'item_attribute_id'
        )->withPivot('item_id', 'item_attribute_id', 'item_attribute_value_id');
	}

	public function productVariantAttribute() {
	    return $this->belongsToMany(
	        'App\Model\Items\ItemVariantAttributes',
            'vendor_item_variant_attribute_value_mapping',
            'item_id',
            'item_variant_attribute_id'
        )->withPivot('item_id', 'item_variant_attribute_id', 'item_variant_attribute_value_id');
    }

    public function categories() {
	    return $this->belongsToMany(
            'App\Model\Items\ItemCategory',
            'vendor_item_category_mapping',
            'item_id',
            'item_category_id'
        );
    }

    public function skuDetails() {
	    return $this->hasMany(
	        'App\Model\Items\VendorSkuDetails',
            'item_id',
            'id'
        );
    }

    public function media() {
        return $this->belongsToMany(
            'App\Model\Items\ItemMediaAttributes',
            'vendor_item_media_attribute_value_mapping',
            'item_id',
            'item_media_attribute_id'
        )->withPivot('item_id', 'item_media_attribute_id', 'item_media_attribute_value_id');
    }

    public function department() {
        return $this->belongsTo(
            'App\Model\Items\ItemDepartment',
            'department_id',
            'id'
        );
    }

    public function brand() {
        return $this->hasOne(
            'App\Model\Items\ItemBrand',
            'id',
            'brand_id'
        );
    }

    public function uom() {
	    return $this->hasOne(
	        'App\Model\Item\UomConversions',
            'id',
            'uom_conversion_id'
        )->with(['selling:id,name,type', 'purchase:id,name,type']);
    }
}
