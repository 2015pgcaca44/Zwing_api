<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class TimeZone extends Model
{
	protected $connection 	= 'mysql';
    protected $table 		= 'time_zone';
    protected $primaryKey 	= 'id';
    protected $fillable 	= ['name'];
}
