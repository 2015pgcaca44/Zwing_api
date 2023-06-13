<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductRatingType extends Model
{
    protected $table = 'product_rating_types';

    protected $primaryKey = 'id';

    protected $fillable = ['v_id','user_id','type'];
}
