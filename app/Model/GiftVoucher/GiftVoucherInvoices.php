<?php

namespace App\Model\GiftVoucher;

use Illuminate\Database\Eloquent\Model;

class GiftVoucherInvoices extends Model
{
    protected $table = 'gv_invoices';
    protected $primaryKey = 'id';
    protected $fillable = ['v_id','vu_id','store_id','invoice_id','custom_order_id','ref_order_id','customer_id','customer_first_name','customer_last_name','customer_number','customer_email','customer_address','customer_pincode','customer_gender','customer_phone_code','customer_dob','transaction_type','comm_trans','customer_gstin','customer_gst_state_id','store_gstin','store_state_id','store_short_code','voucher_qty','subtotal','total','tax_amount','date','time','month','year','financial_year','trans_from','invoice_sequence','terminal_name','terminal_id','session_id','channel_id','sync_status','deleted_at','deleted_by'];

    public function payvia()
    {
        return $this->hasMany('App\Model\GiftVoucher\GiftVoucherPayments','custom_order_id','gv_order_id');
    }
    public function user() {
		return $this->belongsTo('App\User', 'customer_id', 'c_id');
	}
}
