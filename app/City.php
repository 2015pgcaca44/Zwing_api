<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
	protected $connection  = 'mysql';
    protected $table = 'cities';

    // protected $primaryKey = 'cart_id';

    protected $fillable = ['name', 'state_id'];

    public function states(){
    	return $this->belongTo('App\State','state_id');
    }
}
