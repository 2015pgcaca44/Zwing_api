<?php

namespace App\Model\Tax;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxGroup extends Model
{
	use SoftDeletes;
	protected $table 	= 'tax_group';
	protected $fillable = ['name','code','v_id'];


	public function taxRate() {
	    return $this->belongsToMany(
	        'App\Model\Tax\TaxRate',
	        'tax_rate_group_mapping',
	        'tax_group_id',
	        'tax_code_id'
	    );
	}

	public function slabvalue(){
    	return $this->hasMany('App\Model\Tax\TaxCategorySlab','tax_group_id','id');
    }

    public function mapping(){
    	return $this->hasOne(
	        'App\Model\Tax\TaxRateGroupMapping',
	        'tax_group_id',
	        'id' 
	    );
    }

}