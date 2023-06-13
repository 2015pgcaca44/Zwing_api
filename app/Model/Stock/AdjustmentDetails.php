<?php

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;

class AdjustmentDetails extends Model
{
    //
    protected $table = 'adjustment_details';

    protected $primaryKey = 'id';

    protected $fillable = ['v_id', 'store_id', 'vu_id', 'stock_point_id', 'item_id', 'adj_id', 'barcode', 'sku', 'sku_code', 'qty', 'stock_type', 'remarks', 'supply_price', 'subtotal', 'tax', 'discount', 'charge', 'total', 'status'];


}
