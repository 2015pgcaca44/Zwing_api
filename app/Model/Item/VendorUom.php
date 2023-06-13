<?php

namespace App\Model\Item;

use Illuminate\Database\Eloquent\Model;

class VendorUom extends Model
{
    protected $table = 'vendor_uom_mapping';

    protected $primarykey = 'id';

    protected $fillable = ['id', 'uom_id', 'uom_code','ref_uom_code'];

    public function uom(){

    	return $this->hasOne([
    		'App\Model\Item\Uom',
    		'id',
    		'uom_id'
    	]);
    }
  
}
