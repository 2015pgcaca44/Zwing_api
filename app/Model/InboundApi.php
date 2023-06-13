<?php

namespace App\Model;

// use Illuminate\Database\Eloquent\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class InboundApi extends Model
{
    protected $table = 'inbound_apis';
    protected $collection = 'inbound_apis';

    protected $connection = 'mongodb';

    // protected $primaryKey = 'id';

    protected $fillable = ['client_id','client_name', 'v_id','store_id', 'request', 'response', 'response_status_code',  'job_class','api_type', 'api_name', 'ack_id', 'api_status','doc_no'];

    
}
