<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DeviceStorage extends Model
{
	protected $connection  = 'mysql';
	protected $table = 'device_storage';

	protected $primaryKey = 'id';

	protected $fillable = ['udid','name','v_id'];
}
