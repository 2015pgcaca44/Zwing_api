<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EdcDevice extends Model
{
	protected $table 		= 'edc_devices';
	protected $primaryKey 	= 'id';
	protected $fillable 	= ['serial_number', 'bluetooth_id', 'bluetooth_name', 'edc_type','udid','username','password','v_id','store_id'];

	 
	public function mpos(){
		return $this->hasOne('App\MposDevice','edc_id','id');
	}
}
