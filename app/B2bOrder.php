<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class B2bOrder extends Model
{
    protected $table = 'b2b_orders';

    protected $primaryKey = 'od_id';

    protected $fillable = ['order_id', 'custom_order_id', 'ref_order_id', 'transaction_type', 'transaction_sub_type', 'o_id', 'v_id', 'store_id', 'user_id', 'address_id', 'partner_offer_id', 'qty', 'subtotal', 'discount', 'lpdiscount', 'manual_discount', 'coupon_discount', 'employee_id', 'employee_discount', 'employee_available_discount', 'bill_buster_discount', 'md_added_by', 'bill_buster_data', 'tax', 'total', 'status', 'payment_type', 'payment_via', 'is_invoice', 'error_description', 'trans_from', 'vu_id', 'verify_status', 'verified_by', 'verify_status_guard', 'verified_by_guard', 'invoice_name', 'transaction_no', 'return_by', 'return_code', 'qty','remark', 'date', 'time', 'month', 'year', 'deleted_at'];


 public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'c_id');
    }

    public function vuser()
    {
        return $this->belongsTo('App\Vendor', 'vu_id', 'vu_id');
    }


    public function store()
    {
        return $this->belongsTo('App\Store', 'store_id', 'store_id');
    }

  public function details()
    {
        return $this->hasMany('App\B2bOrderDetails', 't_order_id', 'od_id');
    }

}
