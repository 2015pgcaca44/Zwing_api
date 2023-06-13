<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class B2bCart extends Model
{
    protected $table = 'b2b_cart';

    protected $primaryKey = 'cart_id';

    protected $fillable = [ 'transaction_type', 'store_id', 'v_id', 'order_id', 'user_id', 'weight_flag', 'plu_barcode', 'barcode', 'item_name', 'item_id', 'qty', 'subtotal', 'unit_mrp', 'unit_csp', 'override_unit_price', 'override_reason', 'override_flag', 'override_by', 'discount', 'employee_id', 'employee_discount', 'bill_buster_discount', 'tax', 'total', 'is_catalog', 'status', 'return_code', 'trans_from', 'vu_id', 'date', 'time', 'month', 'year', 'delivery', 'slab', 'target_offer', 'section_target_offers', 'section_offers', 'department_id', 'subclass_id', 'printclass_id', 'pdata', 'tdata', 'group_id', 'division_id'];

    public function store()
    {
        return $this->belongsTo('App\Store', 'store_id', 'store_id');
    }
}
