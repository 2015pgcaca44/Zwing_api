<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
	protected $connection  = 'mysql';
    protected $table = 'states';

    // protected $primaryKey = 'cart_id';

    protected $fillable = ['name', 'country_id'];

    public function cities(){
    	return $this->hasMany('App\City','state_id');
    }

    public function country(){
    	return $this->belongTo('App\Country');
    }
}
