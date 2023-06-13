<?php

namespace App\Vendor;

use Illuminate\Database\Eloquent\Model;

class VendorRoleUserMapping extends Model
{
    protected $table = 'vendor_role_user_mapping';
    protected $primaryKey = 'id';
    protected $fillable = ['role_id','user_id'];

    public function role()
    {
        return $this->belongsTo('App\Vendor\VendorRole', 'role_id');
    }

}
