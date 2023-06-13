<?php

namespace App\Model\Items;

use Illuminate\Database\Eloquent\Model;

class VendorItemBrand extends Model
{
    protected $table 	  = 'vendor_item_brand_mapping';
    protected $primaryKey = 'id';
    protected $fillable   = ['v_id' ,'brand_id', 'brand_code' , 'ref_brand_code' ];

    public function brand(){
    	return $this->hasOne(
    		'App\Model\Items\ItemBrand',
    		'id',
    		'brand_id'
    		);

    }
}
