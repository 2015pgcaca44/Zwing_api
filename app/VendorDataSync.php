<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VendorDataSync extends Model
{
	protected $table = 'vendor_data_syncs';

	protected $fillable = ['v_id', 'duration', 'status'];
}
