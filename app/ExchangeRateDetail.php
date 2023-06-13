<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ExchangeRateDetail extends Model
{
    
    protected $table = 'exchange_rate_details';

	protected $primaryKey = 'id';

	protected $fillable = ['cc_hd_id', 'source_currency', 'target_currency', 'exchange_rate', 'status', 'created_by'];
}
