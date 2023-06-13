<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $table = 'cart';

    protected $primaryKey = 'cart_id';

    protected $fillable = [ 'transaction_type', 'store_id', 'v_id', 'order_id', 'user_id', 'weight_flag', 'plu_barcode', 'barcode', 'sku_code','item_name', 'item_id','batch_id','serial_id', 'qty', 'subtotal', 'unit_mrp', 'unit_csp', 'override_unit_price', 'override_reason', 'override_flag', 'override_by', 'discount', 'employee_id', 'employee_discount', 'bill_buster_discount', 'tax', 'net_amount', 'extra_charge', 'charge_details', 'total', 'is_catalog', 'status', 'return_code', 'trans_from', 'vu_id', 'date', 'time', 'month', 'year', 'delivery', 'slab', 'target_offer', 'section_target_offers', 'section_offers', 'department_id', 'subclass_id', 'printclass_id', 'pdata', 'tdata', 'group_id', 'division_id','remarks','item_level_manual_discount'];

    public function store()
    {
        return $this->belongsTo('App\Store', 'store_id', 'store_id');
    }

    public function memoPromo()
    {
        return $this->belongsTo('App\CartOffers', 'cart_id', 'cart_id');
    }

}
