<?php

namespace App\Model;

// use Illuminate\Database\Eloquent\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class OutboundApi extends Model
{
    protected $table = 'outbound_apis';
    protected $collection = 'outbound_apis';

    protected $connection = 'mongodb';

    // protected $primaryKey = 'id';

    protected $fillable = ['client_id', 'client_name', 'v_id','error_before_call', 'api_request', 'api_response', 'response_status_code', 'event_class',  'job_class', 'api_name' , 'for_transaction' , 'transaction_id', 'parent_id'];

    
}
