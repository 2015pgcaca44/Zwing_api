<?php

namespace App\Model\GiftVoucher;

use Illuminate\Database\Eloquent\Model;

class GiftVoucherInvoiceDetails extends Model
{
    protected $table = 'gv_invoice_details';
    protected $primaryKey = 'id';
    protected $fillable = ['v_id','vu_id','store_id','gv_order_id','customer_id','subtotal','total','tax_amount','tdata','sale_value','gift_value','gv_group_id','gv_id','voucher_code','voucher_sequence','gift_customer_id','transaction_type','status','channel_id','temp_data','mobile','session_id'];


}
