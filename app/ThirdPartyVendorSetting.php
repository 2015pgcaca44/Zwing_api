<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ThirdPartyVendorSetting extends Model
{
    protected $table = 'third_party_vendor_settings';
    protected $primaryKey = 'id';
    protected $fillable = ['v_id','name','setting_for','setting_value'];
}
