<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
	protected $connection 	= 'mysql';
    protected $table = 'countries';

    //protected $primaryKey = 'cart_id';

    protected $fillable = ['sortname', 'name', 'phonecode','dial_code'];

    public function states(){
    	
    	return $this->hasMany('App\State','country_id');
    }
}
