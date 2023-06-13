<?php

namespace App\Model\Tax;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxCategory extends Model
{
    use SoftDeletes;
    protected $table = 'tax_category';
    protected $fillable = ['name','code','v_id','slab','applicable_on'];

    public function group(){

    	return $this->hasOne(
	        'App\Model\Tax\TaxGroup',
	        'id',
	        'id' 
	    );
    } //For temparary

    public function slabvalue(){
    	return $this->hasMany('App\Model\Tax\TaxCategorySlab','tax_cat_id','id')->with('group');
    }

    
    

}
