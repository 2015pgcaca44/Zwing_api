<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class SmsLog extends Eloquent
{
	protected $connection = 'mongodb';
	protected $collection = 'sms_logs';
	protected $primaryKey = '_id'; 
    protected $fillable = ['v_id' ,'store_id' ,'request' ,'response', 'for'];
}
