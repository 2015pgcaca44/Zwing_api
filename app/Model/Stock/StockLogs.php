<?php

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;

class StockLogs extends Model
{
    protected $table = 'stock_logs';

    protected $fillable = [
        'variant_sku',
        'sku_code',
        'barcode',
        'item_id',
        'store_id',
        'stock_type',
        'stock_point_id',
        'qty',
        'ref_stock_point_id',
        'grn_id',
        'batch_id',
        'batch_code',
        'serial_id',
        'serial_code',
        'v_id',
        'date',
        'transaction_type',
        'transaction_scr_id',
        'stock_intransit_id',
        'vu_id'
    ];

    public static $stockLogRule = array(
        'move_from'     => 'required',
//        'to_point' 		=> 'required',
    );

    public static $noAdhocRule = array(
        'to_point' 		=> 'required',
    );

    public function serialInfo() {
        return $this->hasOne(
            'App\Model\Stock\Serial',
            'id',
            'serial_id'
        );
    }

    public function batchInfo() {
        return $this->hasOne(
            'App\Model\Stock\Batch',
            'id',
            'batch_id'
        );
    }
}
