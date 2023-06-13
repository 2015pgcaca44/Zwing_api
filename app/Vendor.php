<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\VendorSetting;

class Vendor extends Model
{
    protected $table = 'vendor_auth';

    protected $primaryKey = 'id';

    protected $fillable = ['mobile', 'employee_code', 'vendor_user_random', 'v_id', 'store_id', 'firstname', 'lastname', 'gender', 'dob', 'email', 'password', 'status', 'mobile_active', 'email_active', 'approved_by_store', 'otp', 'api_token', 'device_name', 'os_name', 'os_version', 'udid', 'imei', 'latitude', 'longitude', 'device_model_number', 'type', 'is_active', 'is_admin', 'remember_token'];

    public function settings()
    {
        return $this->hasMany('App\VendorSetting', 'v_id', 'vendor_id');
    }

    public function vendor()
    {
        return $this->belongsTo('App\Organisation', 'vendor_id');
    }

    public function getVuidAttribute()
    {
        return $this->id;
    }

    public function getVendoridAttribute()
    {
        return $this->v_id;
    }

    public function roles()
    {
        return $this->hasMany('App\Vendor\VendorRoleUserMapping', 'user_id');
    }

    public function getUserSettingsAttribute()
    {
        $settingCollection = collect([]);
        $userLevelsettings = VendorSetting::select('name','id','settings')->where('v_id', $this->v_id)->where('user_id', $this->id)->orderBy('updated_at','desc')->get();
        if(!$userLevelsettings->isEmpty()) {
            foreach ($userLevelsettings as $user) {
                $settingCollection->put($user->name, (object)['id' => $user->id, 'setting' => $user->settings]);
            }
        }
        $roleLevelsettings = VendorSetting::select('name','id','settings')->where('v_id', $this->v_id)->whereIn('role_id', $this->roles->pluck('id'))->whereNotIn('name', $settingCollection->keys()->toArray())->orderBy('updated_at','desc')->get();
        if(!$roleLevelsettings->isEmpty()) {
            foreach ($roleLevelsettings as $role) {
                $settingCollection->put($role->name, (object)['id' => $role->id, 'setting' => $role->settings]);
            }
        }

        $storeLevelsettings = VendorSetting::select('name','id','settings')->where('v_id', $this->v_id)->where('store_id', $this->store_id)->whereNotIn('name', $settingCollection->keys()->toArray())->orderBy('updated_at','desc')->get();
        if(!$storeLevelsettings->isEmpty()) {
            foreach ($storeLevelsettings as $store) {
                $settingCollection->put($store->name, (object)['id' => $store->id, 'setting' => $store->settings]);
            }
        }

        $vendorLevelsettings = VendorSetting::select('name','id','settings')->where('v_id', $this->v_id)->whereNotIn('name', $settingCollection->keys()->toArray())->whereNull('store_id')->whereNull('role_id')->whereNull('user_id')->orderBy('updated_at','desc')->get();
        if(!$vendorLevelsettings->isEmpty()) {
            foreach ($vendorLevelsettings as $vendor) {
                $settingCollection->put($vendor->name, (object)['id' => $vendor->id, 'setting' => $vendor->settings]);
            }
        }
        return $settingCollection->transform(function($item) {
            $item->setting = json_decode($item->setting);
            return $item;
        });
    }

    public function getDaySettlementVariancePeriodAttribute()
    {
        $data = '';
        if(!empty($this->user_settings)) {
            $settings = $this->user_settings['settlement']->setting;
            if(property_exists($settings, 'active_session_day')) {
                $settings = $settings->active_session_day->DEFAULT;
                if($settings->status == 1 && property_exists($settings, 'options')) {
                    $data = $settings->options[0]->minimun_no_days->value;
                }
            }
        }
        return $data;
    }


    public function getNegativeStockBillingAttribute()
    {
        $data = 0;
        if(!empty($this->user_settings)) {
            $settings = $this->user_settings['stock']->setting;
            if(property_exists($settings, 'negative_stock_billing')) {
                $data = $settings->negative_stock_billing;
            }
        }
        return $data;
    }

    public function storeData()
    {
        return $this->belongsTo('App\Store', 'store_id');
    }

    public function getCashManagementAttribute()
    {
        $data = [ 'status' => false, 'setting' => (Object)[] ];
        if(!empty($this->user_settings)) {
            $settings = $this->user_settings['store']->setting;
            if(property_exists($settings, 'cashmanagement')) {
                $settings = $settings->cashmanagement->DEFAULT;
                if($settings->status == 1) {
                    $data['status'] = true;
                    $data['setting'] = $settings;
                }
            }
        }
        return $data;
    }

    public function getOrderInventoryBlockingLevelAttribute()
    {
        $data = [ 'status' => false, 'setting' => 'order_created' ];
        if(!empty($this->user_settings)) {
            $settings = $this->user_settings['order']->setting;
            if(property_exists($settings, 'oms')) {
                $settings = $settings->oms->DEFAULT;
                if($settings->status == 1) {
                    if(property_exists($settings, 'options') && is_array($settings->options)) {
                        $optionSettings = $settings->options[0];
                        if(property_exists($optionSettings, 'inventory_blocking_level')) {
                            $data['status'] = true;
                            $data['setting'] = $optionSettings->inventory_blocking_level->value;
                        }
                    }
                }
            }
        }
        return $data;
    }
}
