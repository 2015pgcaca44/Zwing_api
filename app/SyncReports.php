<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class SyncReports extends Eloquent
{
	protected $connection = 'mongodb';
    protected $collection = 'sync_reports';
    protected $primaryKey = 'id';
    protected $fillable = [ 'vendor_id', 'event_name', 'event_short_name', 'file_name', 'number_of_entry', 'created_at', 'upload_date', 'deleted_date','updated_at'];
}
