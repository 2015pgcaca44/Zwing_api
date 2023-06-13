<?php

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;

class StockTransactions extends Model
{
    protected $table = 'stock_transactions';

    protected $fillable = [
        'variant_sku',
        'sku_code',
        'barcode',
        'item_id',
        'store_id',
        'stock_type',
        'transaction_type',
        'stock_point_id',
        'qty',
        'order_id',
        'v_id',
        'vu_id',
        'invoice_no'
    ];
}
