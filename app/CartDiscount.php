<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CartDiscount extends Model
{
     protected $table = 'cart_discount';
     protected $fillable = [ 'v_id', 'store_id', 'vu_id', 'user_id', 'type', 'basis', 'factor', 'total','discount', 'dis_data'];
}
