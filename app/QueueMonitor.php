<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class QueueMonitor extends Model
{
	protected $connection = 'mysql';

	protected $table = 'queue_monitors';

	protected $fillable = ['job_id', 'name', 'queue', 'exception', 'exception_message', 'exception_class', 'data', 'attempt', 'parent_id', 'status', 'v_id', 'store_id', 'platform', 'transaction_id'];
}
