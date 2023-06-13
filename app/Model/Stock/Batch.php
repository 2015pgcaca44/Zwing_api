<?php

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Batch extends Model
{
    use SoftDeletes;
    protected $table = 'batch';
    protected $fillable = [
        'v_id',
        'batch_no',
        'batch_code',
        'mfg_date',
        'exp_date',
        'valid_months',
        'item_price_id',
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
}
