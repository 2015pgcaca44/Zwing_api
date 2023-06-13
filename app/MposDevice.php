<?php

namespace App;

use Illuminate\Database\Eloquent\Model;


class MposDevice extends Model
{
    protected $table 		= 'mpos_device';
	protected $primaryKey 	= 'id';
	protected $fillable 	= ['udid', 'name', 'edc_id'];
}
