<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StoreSyncLog extends Model
{
    protected $table = 'store_sync_logs';

    protected $fillable = ['v_id', 'store_id', 'vu_id', 'entity_count', 'last_transaction_id', 'latest_sync_time', 'last_sync_time', 'udid', 'trans_from', 'type'];
}
