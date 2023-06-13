<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    protected $table = 'product_reviews';

    protected $primaryKey = 'id';

    protected $fillable = ['product_id','barcode','v_id', 'store_id', 'user_id', 'title', 'description'];
}
