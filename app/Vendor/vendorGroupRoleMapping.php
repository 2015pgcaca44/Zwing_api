<?php

namespace App\Model\Vendor;

use Illuminate\Database\Eloquent\Model;

class vendorGroupRoleMapping extends Model
{
    protected $table = 'vendor_group_role_mapping';

    protected $primaryKey = 'id';

    protected $fillable = ['group_id','role_id'];
}
