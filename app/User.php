<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'v_id', 'mobile','customer_phone_code', 'email', 'api_token', 'first_name', 'last_name', 'gender', 'dob', 'otp', 'device_name', 'os_name', 'os_version', 'udid', 'imei', 'latitude', 'longitude', 'device_model_number', 'email_active','anniversary_date','gstin'
    ];

    protected $table = 'customer_auth';
    
    protected $primaryKey = 'c_id';

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'status', 'mobile_active', 'created_at', 'updated_at', 'remember_token'
    ];


    public function groups() {
        return $this->belongsToMany('App\CustomerGroup', 'customer_group_mappings','c_id','group_id')->orderBy('maximum_credit_limit','asc');
    }

    // public function getGroupAttribute(){

    //     $groups = App\CustomerGroup::join('customer_group_mappings','customer_group_mappings.group_id','')



    //     return $this->c_id;
    // }



    public function address() {
         return $this->hasOne('App\Address', 'c_id', 'c_id');
    }

    public function payments()
    {
        return $this->hasMany('App\Payment', 'user_id', 'c_id');
    }

    public function vouchers()
    {
        return $this->hasMany('App\Voucher', 'user_id', 'c_id')->where('v_id', $this->v_id);
    }

    public function getStoreCreditAttribute()
    {
        $totalAmount = $this->vouchers->where('status','unused')->sum('amount');
        return $totalAmount;
    }

    public function invoices()
    {
        return $this->hasMany('App\Invoice', 'user_id', 'c_id')->where('v_id', $this->v_id);
    }

}
