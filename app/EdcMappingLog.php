<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EdcMappingLog extends Model
{
    protected $table 		= 'edc_mapping_log';
	protected $primaryKey 	= 'id';
	protected $fillable 	= ['serial_number', 'bluetooth_id', 'bluetooth_name', 'edc_type','udid','username','password','v_id','store_id','vu_id','type'];

	public function vendoruser(){
		return $this->hasOne('App\Vendor','vu_id','vu_id');
	}

	public function store(){
		return $this->hasOne('App\Stores','store_id','store_id');
	}

	public function vendor(){
		return $this->hasOne('App\VendorAuth','id','v_id');
	}


}
