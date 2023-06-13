<?php

namespace App\Model\GiftVoucher;

use Illuminate\Database\Eloquent\Model;

class GiftVoucherCartDetails extends Model
{
    protected $table = 'gv_cart_details';
    protected $primaryKey = 'gv_cart_id';
    protected $fillable = ['store_id','v_id','vu_id','customer_id','gv_group_id','gv_id','sale_value','mobile','gift_customer_id','gift_value','voucher_code',
    					   'voucher_sequence','tdata','session_id','subtotal','total','tax_amount'];
}
