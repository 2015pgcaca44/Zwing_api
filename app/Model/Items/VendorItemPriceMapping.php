<?php

namespace App\Model\Items;

use Illuminate\Database\Eloquent\Model;

class VendorItemPriceMapping extends Model
{
    protected $table = 'vendor_item_price_mapping';

    protected $fillable = ['v_id', 'item_id', 'variant_combi', 'sku_code','item_price_id','store_id'];

    public function priceDetail() {
        return $this->hasOne(
            'App\Model\Items\ItemPrices',
            'id',
            'item_price_id'
        );
    }
}
