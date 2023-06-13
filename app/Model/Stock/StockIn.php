<?php

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;

class StockIn extends Model
{
    protected $table = 'stock_in';

    protected $fillable = [
        'variant_sku',
        'sku_code',
        'barcode',
        'item_id',
        'store_id',
        'stock_point_id',
        'ref_stock_point_id',
        'qty',
        'grn_id',
        'batch_id',
        'serial_id',
        'v_id',
        'invoice_no',
        'vu_id',
        'transaction_type',
        'transaction_scr_id',
        'batch_code',
        'serial_code',
        'status'
    ];
}
