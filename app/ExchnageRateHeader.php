<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ExchnageRateHeader extends Model
{
    protected $table = 'exchnage_rate_headers';

	protected $primaryKey = 'id';

	protected $fillable = ['v_id', 'pricebook_name', 'pricebook_discription', 'effective_date', 'valid_upto', 'status', 'created_by'];
}
