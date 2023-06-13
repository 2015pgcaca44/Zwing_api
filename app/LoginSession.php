<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class LoginSession extends Eloquent
{
	protected $connection = 'mongodb';
    protected $collection = 'login_sessions';
    protected $primaryKey = '_id';
    protected $fillable = ['store_id', 'v_id', 'api_token','vu_id'];
}
