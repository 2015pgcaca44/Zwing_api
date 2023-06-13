<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderItemDetails extends Model
{
    protected $table = 'order_items';

    // protected $primaryKey = 'od_id';

    protected $fillable = ['porder_id', 'barcode', 'qty', 'mrp', 'price', 'discount', 'ext_price', 'tax', 'taxes', 'message', 'ru_prdv', 'type', 'type_id', 'promo_id', 'is_promo','channel_id'];

}
