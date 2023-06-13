<?php

namespace App\Model\Tax;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

   
class TaxHsnCat extends Model
{
    use SoftDeletes;

	protected $table    = 'tax_hsn_cat';
	protected $fillable = ['v_id','store_id','hsncode','cat_id','description'];
	public $timestamps  = false;

	public function group(){
        return $this->hasMany(
            'App\Model\Tax\TaxRateGroupMapping',
            'tax_group_id',
            'cat_id'
        );
    }

    public function groups(){
        return $this->hasOne(
            'App\Model\Tax\TaxGroup',
            'id',
            'cat_id'
        );
    }



    public function category(){
        return $this->hasOne(
            'App\Model\Tax\TaxCategory',
            'id',
            'cat_id'
        );
    }

    public function slab(){
        return $this->hasMany(
            'App\Model\Tax\TaxCategorySlab',
            'tax_cat_id',
            'cat_id'
        );
    }

     public function slabs(){
        return $this->hasMany(
            'App\Model\Tax\TaxCategorySlab',
            'tax_group_id',
            'cat_id'
        );
    }
}