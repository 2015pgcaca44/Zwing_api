<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Organisation extends Model
{
	protected $table = 'vendor';

	protected $fillable = ['db_name', 'name', 'email', 'vendor_code', 'client_id', 'ref_vendor_code', 'phone', 'phone_2', 'vendor_store_type', 'vendor_image', 'hostpath', 'parent_id', 'is_parent', 'status','db_structure', 'deleted', 'deleted_by'];

	public function edcDevice()
	{
		return $this->hasMany('App\EdcDevice','v_id');
	}

	public function detail(){
		return $this->hasMany('App\OrganisationDetails','v_id');
	}

	public function connection(){
		return $this->hasOne('App\DbConfig', 'id','db_config_id');
	}
}
