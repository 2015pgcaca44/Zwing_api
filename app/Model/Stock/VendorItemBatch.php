<?php

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;

class VendorItemBatch extends Model
{
    protected $table = 'vendor_item_batch';
    protected $fillable = [
        'v_id',
        'variant_combi',
        'sku',
        'item_id',
        'sku_code',
        'batch_id',
        'batch_code'
    ];
}
