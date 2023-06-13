<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ZwingRegionCountryMapping extends Model
{
	protected $connection = 'mysql';
    protected $fillable = ['country_id','country_code','region_id'];
}
