<?php

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    protected $table = 'stock_adjustments';

    protected $fillable = [
        'variant_sku',
        'sku_code',
        'barcode',
        'item_id',
        'store_id',
        'stock_point_id',
        'ref_stock_point_id',
        'stock_type',
        'supply_price',
        'qty',
        'grn_id',
        'v_id',
        'vu_id',
        'via_from',
        'remarks',
        'transaction_scr_id'
    ];
}
