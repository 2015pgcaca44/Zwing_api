<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    protected $table = 'wishlists';

    protected $primaryKey = 'wishlist_id';

    protected $fillable = ['store_id', 'v_id', 'user_id', 'product_id', 'barcode', 'date', 'time', 'month', 'year'];
}
