<?php

namespace App\Model\GiftVoucher;

use Illuminate\Database\Eloquent\Model;

class GiftVoucherOrder extends Model
{
    protected $table = 'gv_order';
    protected $primaryKey = 'gv_order_id';
    protected $fillable = ['gv_order_doc_no','transaction_type','trans_from','voucher_qty','subtotal','total','payment_type','payment_via','status','date','time','month','financial_year','v_id','vu_id','customer_id','store_id','tax_amount','discount','round_off','session_id','customer_gstin','customer_gst_state_id','store_gstin','store_state_id','return_reason_code','is_void','void_by','void_reason_code','channel_id','comm_trans'];
}
