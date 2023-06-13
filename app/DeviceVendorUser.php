<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DeviceVendorUser extends Model
{
    protected $table = 'device_vendor_user';

 	protected $primaryKey = 'id';

	protected $fillable = ['device_id', 'vu_id','v_id','store_id','trans_from','sync_status'];
}
