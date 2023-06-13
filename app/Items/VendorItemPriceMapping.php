<?php

namespace App\Items;

use Illuminate\Database\Eloquent\Model;

class VendorItemPriceMapping extends Model
{
    protected $table = 'vendor_item_price_mapping';

     protected $fillable = ['v_id', 'item_id', 'variant_combi', 'item_price_id','store_id','price_book_id'];

    public function priceDetail() {
        return $this->hasOne(
            'App\Items\ItemPrices',
            'id',
            'item_price_id'
        );
    }

    public function priceBook(){
    	return $this->hasMany('App\Model\Items\PriceBook','id','price_book_id');
    }
}
