<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VendorDetails extends Model
{
   
    protected $table = 'vendor_details';
    protected $connection = 'mysql';
    protected $primaryKey = 'id';
    protected $fillable = ['address','registered_address' ,'state', 'city','country', 'pincode', 'pan_no', 'gst_no','active'];
    public static $rules = array(
		'vendor' 		=> 'required',
		'gstno'			=> 'required',
		'panno'			=> 'required',
		'address'		=> 'required',
		'state'			=> 'required',
		'city'			=> 'required',
		'country'		=> 'required',
		'pincode'		=> 'numeric',
		 
		// 'vendor_image'	=> 'required',
	);

	public function countryDetail()
    {
     return $this->hasOne('App\Country', 'id','country');
    }

    public function timezone()
    {
     return $this->hasOne('App\Model\TimeZone', 'id','time_zone_id');
    }
}
