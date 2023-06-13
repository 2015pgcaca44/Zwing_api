<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AisleSectionProduct extends Model
{
    protected $table = 'aisle_section_products';

    protected $primaryKey = 'id';

    protected $fillable = [ 'aisle_section_id','vendor_user_id', 'barcode'];

    protected $hidden = ['created_at' , 'updated_at'];

    public function section(){

    	return $this->belongsTo('App\AisleSection');
    }

    public function info(){
    	return $this->hasOne('App\AisleSectionProductInfo');
    }

    public function product(){
        return $this->hasOne('App\Product' , 'barcode' , 'barcode');
    }
}


