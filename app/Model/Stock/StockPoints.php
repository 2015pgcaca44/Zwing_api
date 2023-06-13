<?php

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;

class StockPoints extends Model
{
    protected $table = 'stock_points';
    protected $primaryKey = 'id';

    protected $fillable = ['v_id', 'store_id', 'name', 'code','ref_stock_point_code','non_editable','type', 'stock_point_header_id', 'is_editable', 'is_default', 'is_sellable', 'is_active', 'is_deleted'];

    public static $stockpointsrule = array(
        'name' 		=> 'required|regex:/(^[A-Za-z0-9 ]+$)+/',
        'store' 	=> 'required'
    );

    public static $stockpoints_store_rule = array(
        'name' 		=> 'required|regex:/(^[A-Za-z0-9 ]+$)+/',
    );

    public function store()
    {
        return $this->belongsTo('App\Store', 'store_id', 'store_id');
    }
}
