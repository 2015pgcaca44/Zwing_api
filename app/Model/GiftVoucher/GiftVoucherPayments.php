<?php

namespace App\Model\GiftVoucher;

use Illuminate\Database\Eloquent\Model;

class GiftVoucherPayments extends Model
{
    protected $table = 'gv_payments';
    protected $primaryKey = 'gv_payments_id';
    protected $fillable = ['gv_order_id','gv_order_doc_no','invoice_id','payment_type','status','date','time','month','year','v_id','vu_id','customer_id','store_id','session_id','channel_id','terminal_id','amount','method','cash_collected','cash_return','payment_invoice_id','error_description','payment_gateway_type','payment_gateway_device_type','gateway_response','ref_txn_id','trans_type'];
}
