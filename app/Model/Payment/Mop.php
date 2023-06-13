<?php

namespace App\Model\Payment;

use Illuminate\Database\Eloquent\Model;

class Mop extends Model
{
	protected $table = 'mops';

	protected $fillable = ['name', 'description','code','type', 'is_integrated','status'];

	public function vendors()
	{
	    return $this->hasMany('App\Model\Payment\VendorMop', 'id', 'mop_id');
	}

	public static $rules = [
		'mop_name'=> 'required',
		'mop_code'=> 'required|unique:mops,code',
		'mop_desc'=> 'required'
	];

	public function getNameAttribute($value) 
	{
		return strtolower($value);
	}

}
