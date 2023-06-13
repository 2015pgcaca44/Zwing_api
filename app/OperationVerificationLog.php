<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OperationVerificationLog extends Model
{
	protected $table = 'operation_verification_log';

	protected $fillable = ['v_id', 'store_id', 'trans_from', 'operation', 'c_id', 'order_id', 'phone', 'invoice_id', 'vu_id', 'verify_by'];

	public function edcDevice()
	{
		return $this->hasMany('App\EdcDevice','v_id');
	}
}
