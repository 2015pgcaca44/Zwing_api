<?php

namespace App\Model\Tax;

use Illuminate\Database\Eloquent\Model;

class TaxGroupPresetDetails extends Model
{
	public $timestamps = false;
	protected $table 	= 'tax_group_preset_details';
	protected $fillable = ['preset_id','preset_name','status'];
}
