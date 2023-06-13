<?php

namespace App\Model\Vendor;

use Illuminate\Database\Eloquent\Model;

class VendorUserGroupMapping extends Model
{
    protected $table = 'vendor_group_user_mapping';

    protected $primaryKey = 'id';

    protected $fillable = ['user_id','group_id'];
}
