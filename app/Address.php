<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $table = 'addresses';

    protected $primaryKey = 'id';

    protected $fillable = ['c_id', 'name', 'address_nickname', 'mobile', 'pincode', 'address1', 'address2', 'landmark', 'city','city_id', 'state', 'deleted_status', 'is_primary'];

     public function citych() {
         return $this->hasOne('App\City', 'id', 'city_id');
    }
}
