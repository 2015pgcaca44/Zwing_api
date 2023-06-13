<?php

namespace App\Model\GiftVoucher;

use Illuminate\Database\Eloquent\Model;

class GiftVoucherTransactionLogs extends Model
{
    protected $table = 'gv_transaction_log';
    protected $primaryKey = 'id';
    protected $fillable = ['v_id','vu_id','customer_id','store_id','gv_group_id','gv_id','voucher_code','ref_order_id','type','amount','status','preset_codes','mobile'];
}
