<?php

namespace App\Model\GiftVoucher;

use Illuminate\Database\Eloquent\Model;

class GiftVoucherConfigPreset extends Model
{
    protected $table = 'gv_config_preset';
    protected $fillable = ['v_id','config_preset_name','config_preset_discription','status','is_duplicate'];
}
