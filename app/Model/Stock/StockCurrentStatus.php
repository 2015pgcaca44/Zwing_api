<?php

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class StockCurrentStatus extends Model {
    use SoftDeletes;
    protected $table = 'stock_current_status';

    protected $fillable = [
        'variant_sku',
        'item_id',
        'barcode',
        'store_id',
        'for_date',
        'opening_qty',
        'out_qty',
        'int_qty',
        'v_id',
        'grn_qty',
        'grt_qty',
        'sale_qty',
        'return_qty',
        'transfer_out_qty',
        'adj_qty',
        'adj_in_qty',
        'adj_out_qty',
        'damage_qty',
        'stop_billing'
    ];

    public function Item(){
         return $this->hasOne(
            'App\Model\Items\Item',
            'id',
            'item_id'
        );
    }

    public function VendorItem(){
         return $this->hasOne(
            'App\Model\Items\VendorItem',
            'id',
            'item_id'
        );
    }

    /*public function sku(){
        return $this->hasOne(
            'App\Model\Items\VendorSkuDetails',
            'sku',
            'variant_sku'
        );
    }*/

    // public function sku(){
    //     return $this->hasOne(
    //         'App\Model\Items\VendorSkuDetails',
    //         'item_id',
    //         'item_id'
    //     )->where('sku',$this->variant_sku);
    // }


      public function sku(){
        return $this->hasOne(
            'App\Model\Items\VendorSku',
            'item_id',
            'item_id'
        )->where('sku',$this->variant_sku);
    }


    public function variantPrices() {
        return $this->belongsToMany(
            'App\Model\Items\ItemPrices',
            'vendor_item_price_mapping',
            'item_id',
            'item_price_id' 
        );
    }
 
}
