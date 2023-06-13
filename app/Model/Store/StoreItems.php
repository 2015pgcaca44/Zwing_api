<?php

namespace App\Model\Store;

use Illuminate\Database\Eloquent\Model;

class StoreItems extends Model
{
    protected $table = 'store_items';

	protected $fillable = ['v_id','variant_sku','item_id','barcode', 'store_id','created_at', 'deallocate_status'];

    public function itemName() {
        return $this->hasOne(
            'App\Item',
            'id',
            'item_id'
        );
    }

}
