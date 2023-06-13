<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VendorAuth extends Model
{
    protected $table = 'vendor_auth';

    protected $primaryKey = 'id';

   protected $fillable = ['mobile', 'v_id', 'store_id', 'first_name', 'last_name', 'gender', 'dob', 'email', 'password', 'status', 'mobile_active', 'email_active', 'otp', 'api_token', 'device_name', 'os_name', 'os_version', 'udid', 'imei', 'latitude', 'longitude', 'device_model_number', 'remember_token'];

     
}
