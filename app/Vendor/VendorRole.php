<?php

namespace App\Vendor;

use Illuminate\Database\Eloquent\Model;

class VendorRole extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 'v_id', 'name','code'];
    

    public function permissions(){

        $relation =  $this->belongsToMany('App\Model\Vendor\VendorPermission', 
            'vendor_role_permission_mapping',
            'role_id',
            'permission_id');
        //$relation = $relation->withTimestamps();
        return $relation;

    }
}
