<?php

namespace App\Model\Vendor;

use Illuminate\Database\Eloquent\Model;

class VendorPermission extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 'v_id', 'name','code' ,'resource'];
}
