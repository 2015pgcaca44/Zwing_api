<?php

namespace App\Vendor;

use Illuminate\Database\Eloquent\Model;

class VendorGroup extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 'v_id', 'name','code' ];
    
	public function users(){
    	$relation = $this->belongsToMany('App\Vendor', 'app_user_group_mapping','group_id','user_id');
    	//$relation = $relation->withTimestamps();
        return $relation;
	}

     public function roles(){

        $relation =  $this->belongsToMany('App\Model\Vendor\VendorRole', 'vendor_group_role_mapping','group_id','role_id');
        //$relation = $relation->withTimestamps();
        return $relation;

    }
}
