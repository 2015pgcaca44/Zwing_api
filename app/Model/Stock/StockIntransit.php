<?php

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;

class StockIntransit extends Model
{
    protected $table = 'stock_intransit';

    protected $fillable = [
        'v_id',
        'destination_store_id',
        'source_store_id',
        'advice_id',
        'is_moved',
        'remarks',
        'transaction_type' 
    ];
}
