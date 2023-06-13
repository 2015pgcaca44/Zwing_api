<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ZwingRegion extends Model
{
	protected $connection = 'mysql';
    protected $fillable = ['name','description','code'];

    public function countries(){
    	return $this->belongsToMany('App\Country', 'zwing_region_country_mappings' ,'region_id', 'country_id' );
    }
}
