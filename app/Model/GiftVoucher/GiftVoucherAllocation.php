<?php

namespace App\Model\GiftVoucher;

use Illuminate\Database\Eloquent\Model;

class GiftVoucherAllocation extends Model
{
    //
    protected $table = 'gv_allocation';
    protected $fillable = ['v_id','store_id','gv_group_id','pack_id','gv_id','is_received','is_deallocated'];
}
