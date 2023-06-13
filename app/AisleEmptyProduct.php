<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AisleEmptyProduct extends Model
{
    protected $table = 'aisle_empty_products';

    protected $primaryKey = 'id';

    protected $fillable = ['vendor_user_id','v_id', 'store_id', 'barcode', 'created_at', 'updated_at'];

    public function product(){
    	return $this->hasOne('App\Product','barcode', 'barcode');
    }
}
