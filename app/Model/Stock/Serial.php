<?php

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Serial extends Model
{
    use SoftDeletes;
    protected $table = 'serial';
    protected $fillable = [
        'v_id',
        'store_id',
        'serial_no',
        'item_price_id',
        'stock_point_id',
        'sku_code',
        'serial_code',
        'is_warranty',
        'manufacturing_date',
        'warranty_period',
        'udf1',
        'udf2',
        'udf3',
        'udf4',
        'created_by',
        'status',
        'validity_unit'
    ];

     public function priceDetail() {
        return $this->hasOne(
            'App\Items\ItemPrices',
            'id',
            'item_price_id'
        );
    }

     public function price(){
        return $this->hasOne('App\Items\ItemPrices','id','item_price_id');
    }

}
