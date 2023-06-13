<?php

namespace App\Model\GiftVoucher;

use Illuminate\Database\Eloquent\Model;

class GiftVoucherPacks extends Model
{
    protected $table = 'gv_packs';
    protected $fillable = ['gv_pack_code','gv_group_id','ref_gv_pack_code','ref_gv_pack_code'];
}
