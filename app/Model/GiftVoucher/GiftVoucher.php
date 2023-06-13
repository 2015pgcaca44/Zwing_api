<?php

namespace App\Model\GiftVoucher;

use Illuminate\Database\Eloquent\Model;

class GiftVoucher extends Model
{
    //
    protected $table = 'gift_voucher';
    protected $fillable = ['v_id','gv_group_id','gv_code','ref_gv_code','gift_value','sales_value','status','is_blocked','block_reason','allow_partial_refund','created_by','updated_by','deleted_at','deleted_by','pack_code','voucher_status','voucher_sequence','effective_from','valid_upto'];
}
