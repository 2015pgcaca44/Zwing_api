<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
	protected $table 	  = 'items';
	protected $primaryKey = 'id';
	protected $fillable   = [
	    'name',
        'short_description',
        'long_description',
        'sku',
        'uom_id',
        'mrp',
        'department_id',
        'brand_id',
        'uom_conversion_id',
        'has_batch',
        'has_serial',
        'hsn_code',
        'tax_group_id',
        'tax_type'
    ];

	public static $rules = array(
		'name' 		=> 'required',
        'brand'     => 'required',
        'department'     => 'required',
        'product_attributes'     => 'required',
        'uom'       => 'required',
        'variant'     => 'required',
        'variant_products'     => 'required',
        'category'     => 'required',
        'description'     => 'required',
        'short_desc'     => 'required',
	);


	public function productAttribute() {
		return $this->belongsToMany(
		    'App\ItemAttributes',
            'vendor_item_attribute_value_mapping',
            'item_id',
            'item_attribute_id'
        )->withPivot('item_id', 'item_attribute_id', 'item_attribute_value_id');
	}

	public function productVariantAttribute() {
	    return $this->belongsToMany(
	        'App\Items\ItemVariantAttributes',
            'vendor_item_variant_attribute_value_mapping',
            'item_id',
            'item_variant_attribute_id'
        )->withPivot('item_id', 'item_variant_attribute_id', 'item_variant_attribute_value_id');
    }

    public function categories() {
	    return $this->belongsToMany(
            'App\ItemCategory',
            'vendor_item_category_mapping',
            'item_id',
            'item_category_id'
        );
    }

    public function skuDetails() {
	    return $this->hasMany(
	        'App\Items\VendorSkuDetails',
            'item_id',
            'id'
        );
    }

    public function media() {
        return $this->belongsToMany(
            'App\Items\ItemMediaAttributes',
            'vendor_item_media_attribute_value_mapping',
            'item_id',
            'item_media_attribute_id'
        )->withPivot('item_id', 'item_media_attribute_id', 'item_media_attribute_value_id');
    }

    public function department() {
        return $this->belongsTo(
            'App\Items\ItemDepartment',
            'department_id',
            'id'
        );
    }

    public function brand() {
        return $this->hasOne(
            'App\Items\ItemBrand',
            'id',
            'brand_id'
        );
    }

    public function uom() {
	    return $this->hasOne(
	        'App\Model\Item\UomConversions',
            'id',
            'uom_conversion_id'
        )->with(['selling:id,name', 'purchase:id,name']);
    }

    public function vendor() {
        return $this->hasOne(
            'App\Items\VendorItems',
            'item_id',
            'id'
        );
    }

    public function hsnCodeDetail() {
        return $this->hasOne(
            'App\Model\Tax\HsnCode',
            'hsncode',
            'hsn_code'
        );
    }

    public function taxGroupDetail() {
        return $this->hasOne(
            'App\Model\Tax\TaxGroup',
            'id',
            'tax_group_id'
        );
    }
}
