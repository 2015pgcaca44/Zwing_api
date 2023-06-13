<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MOPClient extends Model
{
	protected $table = 'payment_mode_client_mappings';

	protected $fillable = ['paymemt_mode_id', 'third_party_client_id', 'mop_code'];

	public function mopName()
	{
	    return $this->hasOne('App\MPOList', 'id', 'paymemt_mode_id');
	}
}
