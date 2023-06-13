<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Scan extends Model
{
    protected $table = 'scan';

    protected $primaryKey = 'scan_id';

    protected $fillable = ['store_id', 'v_id', 'user_id', 'product_id','product_name', 'barcode', 'date', 'time', 'month', 'year'];
}
