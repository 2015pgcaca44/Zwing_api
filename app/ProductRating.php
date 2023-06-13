<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductRating extends Model
{
    protected $table = 'product_ratings';

    protected $primaryKey = 'id';

    protected $fillable = ['product_id','barcode','v_id', 'store_id', 'user_id', 'star', 'date', 'month', 'year', 'time'];
}
