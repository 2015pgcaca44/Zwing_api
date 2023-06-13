<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FailedSyncReports extends Model
{
    use SoftDeletes;
    protected $table = 'failed_sync_reports';

    protected $fillable = ['v_id', 'store_id', 'job_id', 'transaction_id', 'transaction_type', 'transaction_type_slug', 'doc_no', 'response', 'date'];
}
