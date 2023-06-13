<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceDetails extends Model
{
	use SoftDeletes;
    protected $table = 'invoice_details';

    // protected $primaryKey = 'od_id';

    protected $fillable = ['transaction_type', 'store_id', 'v_id', 'order_id', 't_order_id', 'user_id', 'weight_flag', 'plu_barcode', 'barcode','sku_code', 'item_name', 'item_id','batch_id','serial_id', 'qty', 'subtotal', 'unit_mrp', 'unit_csp', 'override_unit_price', 'override_reason', 'override_flag', 'override_by', 'discount', 'lpdiscount', 'manual_discount', 'coupon_discount', 'employee_id', 'employee_discount', 'bill_buster_discount','item_level_manual_discount','tax','net_amount','extra_charge','charge_details', 'total', 'is_catalog', 'status', 'return_code', 'trans_from', 'vu_id', 'salesman_id', 'date', 'time', 'month', 'year', 'delivery', 'slab', 'target_offer', 'section_target_offers', 'section_offers', 'department_id', 'subclass_id', 'printclass_id', 'pdata', 'tdata', 'group_id', 'division_id','remark' ,'deleted_at','channel_id'];

    public function store()
    {
        return $this->belongsTo('App\Store', 'store_id', 'store_id');
    }

    public function getTotalDiscountAttribute()
    {
    	return $this->manual_discount + $this->lpdiscount + $this->coupon_discount;
    }

    public function getSalesman(){
        return $this->hasMany('App\VendorAuth', 'id', 'salesman_id');
    }

}
