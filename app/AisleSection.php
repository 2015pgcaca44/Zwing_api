<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AisleSection extends Model
{
    protected $table = 'aisle_sections';

    protected $primaryKey = 'id';

    protected $fillable = [ 'v_id', 'store_id', 'aisle_id', 'code', 'barcode', 'status', 'created_at', 'updated_at'];

    public function aisle(){

    	return $this->belongsTo('App\Aisle');
    }

    public function products(){

    	return $this->hasMany('App\AisleSectionProduct');
    }
}
