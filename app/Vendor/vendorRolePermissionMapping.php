<?php

namespace App\Model\Vendor;

use Illuminate\Database\Eloquent\Model;

class vendorRolePermissionMapping extends Model
{
    protected $table = 'vendor_role_permission_mapping';

    protected $primaryKey = 'id';

    protected $fillable = ['role_id','permission_id'];
}
