<?php

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;

class StockPointSummary extends Model
{
    protected $table = 'stock_point_summary';
    protected $fillable = [
        'v_id',
        'item_id',
        'store_id',
        'stock_point_id',
        'item_id',
        'variant_sku',
        'sku_code',
        'barcode',
        'qty',
        'batch_id',
        'batch_code',
        'serial_id',
        'serial_code',
        'stop_billing',
        'pause_all_trans',
        'reason_stop_billing',
        'reason_pause_all_trans',
        'active_status'
    ];

    public function sku(){
        return $this->hasOne(
            'App\Model\Items\VendorSkuDetails',
            'item_id',
            'item_id'
        )->with('category');
    }

    public function point()
    {
        return $this->hasOne('App\Model\Stock\StockPoints', 'id', 'stock_point_id');
    }

    public function serials(){
        return $this->hasMany('App\Model\Stock\Serial','serial_code','serial_code');
    }

    public function batch(){
        return $this->hasOne('App\Model\Stock\Batch','id','batch_id');
    }

}
