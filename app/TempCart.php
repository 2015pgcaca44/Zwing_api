<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TempCart extends Model
{
    protected $table = 'temp_cart';

    protected $primaryKey = 'cart_id';

    protected $fillable = ['store_id', 'v_id', 'order_id', 'user_id', 'product_id', 'barcode', 'qty', 'amount', 'status', 'date', 'time', 'month', 'year', 'created_at', 'updated_at', 'delivery'];
}
