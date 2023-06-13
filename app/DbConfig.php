<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DbConfig extends Model
{
	protected $connection  = 'mysql';
    protected $table 	  = 'db_config';
	protected $primaryKey = 'id';
}
