<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PineLabDevice extends Model
{
    //


	protected $table = 'pine_lab_devices';
    protected $primaryKey = 'id'; 
	protected $fillable = ['v_id', 'imei', 'merchant_store_pos_code'];

}
