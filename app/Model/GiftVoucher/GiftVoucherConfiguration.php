<?php

namespace App\Model\GiftVoucher;

use Illuminate\Database\Eloquent\Model;

class GiftVoucherConfiguration extends Model
{
    protected $table = 'gv_config_master';
    protected $fillable = ['config_name','config_discription','config_display_type','allowed_value_type','config_parent','status','placeholder_name','radio_text1','radio_text2','config_code'];

}
