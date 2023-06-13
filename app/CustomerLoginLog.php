<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerLoginLog extends Model
{
    protected $table = 'customer_login_log';

    protected $primaryKey = 'id';

    protected $fillable = ['latitude', 'longitude', 'location', 'user_id', 'created_at', 'updated_at'];
}
