<?php

namespace App\Model\GiftVoucher;

use Illuminate\Database\Eloquent\Model;

class GiftVoucherOrderDetails extends Model
{
    protected $table = 'gv_order_details';
    protected $primaryKey = 'gv_od_id';
    protected $fillable = ['gv_order_id','v_id','vu_id','customer_id','store_id','gift_value','tdata','gv_group_id','gv_id','sale_value','voucher_code','voucher_sequence','mobile','status','transaction_type','session_id','gift_customer_id','tax_amount','subtotal','total','date','time','month','year'];
}
