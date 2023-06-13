<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Aisle extends Model
{
    protected $table = 'aisles';

    protected $primaryKey = 'id';

    protected $fillable = [ 'v_id', 'store_id', 'name', 'number', 'status', 'created_at', 'updated_at'];

    public function sections(){
    	return $this->hasMany('App\AisleSection');
    }
}
