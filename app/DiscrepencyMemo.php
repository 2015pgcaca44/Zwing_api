<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DiscrepencyMemo extends Model
{
    protected $table = 'discrepency_memos';

    protected $primaryKey = 'id';

    protected $fillable = ['order_id','v_id', 'store_id', 'product_id', 'barcode','qty' 'created_at', 'updated_at'];
}
