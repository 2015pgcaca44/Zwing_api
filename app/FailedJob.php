<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FailedJob extends Model
{
	protected $connection = 'mysql';
	
	protected $table = 'failed_jobs';

	protected $fillable = ['connection', 'queue', 'payload', 'exception'];
}
