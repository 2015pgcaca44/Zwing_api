<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VendorSetting extends Model
{
    protected $table = 'vendor_settings';

    protected $primaryKey = 'id';

    protected $fillable = ['v_id', 'name', 'settings', 'setting_group',' status', 'category_id', 'role_id', 'user_id', 'store_id'];
}
