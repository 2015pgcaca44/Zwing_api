<?php

namespace App\Model\GiftVoucher;

use Illuminate\Database\Eloquent\Model;

class GiftVoucherConfigPresetMapping extends Model
{
    protected $table = 'gv_config_preset_mapping';
    protected $fillable = ['v_id','config_preset_id','config_id','config_value'];
}
