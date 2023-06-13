<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VendorUserAuth extends Model
{
	protected $table = 'vender_users_auth';

	protected $primaryKey = 'vu_id';

	protected $fillable = ['mobile', 'employee_code', 'vendor_user_random', 'vendor_id', 'store_id', 'first_name', 'last_name', 'gender', 'dob', 'email', 'password', 'status', 'mobile_active', 'email_active', 'approved_by_store', 'otp', 'api_token', 'device_name', 'os_name', 'os_version', 'udid', 'imei', 'latitude', 'longitude', 'device_model_number', 'type', 'remember_token', 'is_active', 'is_admin', 'rights','deleted','deleted_at'];

	public function store()
    {
        return $this->belongsTo('App\Store', 'store_id', 'store_id');
    }

    public static $guard_superviser_rule = array(
        'first_name' 		=> 'required|regex:/(^[A-Za-z0-9 ]+$)+/',
        'last_name' 		=> 'required|regex:/(^[A-Za-z0-9 ]+$)+/',
//        'mobile' 			=> 'required|digits:10|numeric|unique:vender_users_auth,mobile',
        'store' 			=> 'required',
        'gender' 			=> 'required|regex:/(^[a-z0-9 ]+$)+/',
        'security_code'     => 'numeric'
    );

    public static $general_rule = array(
        'first_name' 		=> 'required|regex:/(^[A-Za-z0-9 ]+$)+/',
        'last_name' 		=> 'required|regex:/(^[A-Za-z0-9 ]+$)+/',
//        'email' 			=> 'email|unique:vender_users_auth,email',
//        'mobile' 			=> 'required|digits:10|numeric|unique:vender_users_auth,mobile',
        'store' 			=> 'required',
        'gender' 			=> 'required|regex:/(^[a-z0-9 ]+$)+/',
//        'password' 			=> 'required|min:4',
//        'confirm_password' 	=> 'required|same:password',
        'security_code'     => 'nullable|numeric'
    );

    public static $password_rule = array(
        'first_name' 		=> 'required|regex:/(^[A-Za-z0-9 ]+$)+/',
        'last_name' 		=> 'required|regex:/(^[A-Za-z0-9 ]+$)+/',
//        'email' 			=> 'email|unique:vender_users_auth,email,'.$id.',vu_id',
//        'mobile' 			=> 'required|digits:10|numeric|unique:vender_users_auth,mobile,'.$id.',vu_id',
        'store' 			=> 'required',
        'gender' 			=> 'required|regex:/(^[a-z0-9 ]+$)+/',
        'password' 			=> 'required|min:4',
        'confirm_password' 	=> 'required|same:password',
        'security_code'     => 'nullable|numeric'
    );
}