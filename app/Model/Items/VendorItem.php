<?php

namespace App\Model\Items;

use Illuminate\Database\Eloquent\Model;

class VendorItem extends Model
{
    protected $table = 'vendor_items';

    protected $fillable = ['v_id', 'sku', 'has_batch', 'has_serial',  'uom_conversion_id', 'brand_id', 'department_id', 'tax_type', 'item_id', 'item_code', 'ref_item_code', 'track_inventory', 'track_inventory_by','negative_inventory', 'negative_inventory_override_by_store_policy' ,'allow_price_override','price_override_variance','price_override_override_by_store_policy','allow_manual_discount','manual_discount_percent','manual_discount_override_by_store_policy'];

    public function item() {
        return $this->hasOne(
            'App\Model\Items\Item',
            'id',
            'item_id'
        );
    }

    public function batch(){
    	return $this->hasMany(
    		'App\Model\Stock',
    		'id'
    	);
    }
}
