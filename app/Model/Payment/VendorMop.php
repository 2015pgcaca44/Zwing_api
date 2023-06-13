<?php

namespace App\Model\Payment;

use Illuminate\Database\Eloquent\Model;

class VendorMop extends Model
{
	protected $table = 'vendor_mop_mapping';

	protected $fillable = ['mop_id','v_id', 'trans_from','code','ref_mop_code', 'ref_mop_type','status'];

	public function mop()
	{
	    return $this->hasOne('App\Model\Payment\Mop', 'id', 'mop_id');
	}

}
