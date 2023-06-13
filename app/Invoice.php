<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model {

    use SoftDeletes;
	protected $table = 'invoices';

	protected $fillable = ['v_id', 'store_id', 'invoice_id', 'custom_order_id', 'ref_order_id', 'user_id', 'vu_id', 'transaction_type', 'comm_trans', 'cust_gstin', 'cust_gstin_state_id', 'store_gstin', 'transaction_sub_type', 'store_short_code', 'invoice_sequence', 'stock_point_id', 'terminal_name', 'terminal_id', 'qty', 'subtotal', 'discount_amount', 'discount_details', 'discount', 'lpdiscount', 'manual_discount', 'coupon_discount', 'bill_buster_discount', 'ilm_discount_total', 'tax', 'tax_details', 'round_off','net_amount','extra_charge','charge_details','total', 'remark', 'third_party_response', 'date', 'time', 'month', 'year', 'financial_year', 'customer_name', 'customer_number', 'customer_email', 'customer_address', 'trans_from', 'session_id', 'settlement_session_id', 'invoice_name', 'deleted_by', 'store_gstin_state_id', 'customer_gender', 'customer_phone_code', 'customer_dob', 'customer_first_name', 'customer_last_name', 'customer_pincode', 'channel_id', 'deleted_at', 'sync_status'];

	public function user() {
		return $this->belongsTo('App\User', 'user_id', 'c_id');
	}

	public function vuser() {
		return $this->belongsTo('App\Vendor', 'vu_id', 'id');
	}

	public function payment() {
		return $this->belongsTo('App\Payment', 'ref_order_id', 'order_id');
	}

	public function store() {
        return $this->belongsTo('App\Store', 'store_id', 'store_id');
    }

    public function details()
    {
        return $this->hasMany('App\InvoiceDetails', 't_order_id', 'id');
    }

    public function payments()
    {
        return $this->hasMany('App\Payment', 'invoice_id', 'invoice_id');
    }

    public function order()
    {
        return $this->belongsTo('App\Order', 'ref_order_id', 'order_id');
    }

    public function payvia()
    {
        return $this->hasMany('App\Payment', 'order_id', 'ref_order_id');
    }

    public function getTotalDiscountAttribute()
    {
        return $this->discount + $this->manual_discount + $this->lpdiscount + $this->coupon_discount;
    }

    public function discounts()
    {
        return $this->hasMany('App\OrderDiscount', 'order_id', 'ref_order_id');
    }

}
