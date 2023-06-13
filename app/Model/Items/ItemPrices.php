<?php

namespace App\Model\Items;

use Illuminate\Database\Eloquent\Model;

class ItemPrices extends Model
{
    protected $table = 'item_prices';

    protected $fillable = ['mrp', 'rsp','special_price'];
}
