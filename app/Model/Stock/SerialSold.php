<?php

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;

class SerialSold extends Model
{
    protected $table = 'serial_sold';

    protected $fillable = [
        'v_id',
        'store_id',
        'invoice_id',
        'sales_date',
        'serial_no',
        'item_price_id',
        'stock_point_id',
        'sku_code',
        'serial_code',
        'is_warranty',
        'manufacturing_date',
        'warranty_period',
        'udf1',
        'udf2',
        'udf3',
        'udf4',
        'created_by'
    ];

     public function priceDetail() {
        return $this->hasOne(
            'App\Items\ItemPrices',
            'id',
            'item_price_id'
        );
    }
}
