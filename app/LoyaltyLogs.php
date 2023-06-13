<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class LoyaltyLogs extends Eloquent
{
	protected $connection = 'mongodb';
    protected $collection = 'loyalty_logs';
    protected $table = 'loyalty_logs';
    protected $primaryKey = '_id';
    protected $fillable = ['v_id', 'store_id', 'status', 'mobile', 'email', 'request', 'response', 'type'];
}
