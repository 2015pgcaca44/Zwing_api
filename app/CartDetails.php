<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CartDetails extends Model
{
    protected $table = 'cart_details';

    // protected $primaryKey = 'cart_id';

    protected $fillable = ['cart_id', 'barcode', 'qty', 'mrp', 'price', 'discount', 'ext_price', 'tax', 'taxes', 'message', 'ru_prdv', 'type', 'type_id', 'promo_id', 'is_promo'];
}
