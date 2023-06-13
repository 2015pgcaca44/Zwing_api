<?php

namespace App\Model\Tax;

use Illuminate\Database\Eloquent\Model;

class TaxCategorySlab extends Model
{
	protected $table = 'tax_slab';
	protected $fillable = ['tax_group_id','tax_cat_id','amount_from','amount_to'];
	public $timestamps = false;

	 public function group(){
    	return $this->hasOne(
	        'App\Model\Tax\TaxGroup',
	        'id',
	        'tax_group_id' 
	    );
    }//End of group

    public function ratemap(){
        return $this->hasMany(
            'App\Model\Tax\TaxRateGroupMapping',
            'tax_slab_id',
            'id'
        );
    }
}
