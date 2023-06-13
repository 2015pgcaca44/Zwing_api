<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class EventLog extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'event_log';
    protected $primaryKey = '_id';
    protected $fillable = ['store_id','store_name','v_id','staff_id','staff_name','type','api_token','ip_address','latitude','longitude','trans_form','transaction_id'];
}
