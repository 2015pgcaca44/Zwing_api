<?php

namespace App\Model\Items;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorSkuDetailBarcode extends Model
{
	use SoftDeletes;

    protected $fillable = ['v_id','vendor_sku_detail_id','sku_code','item_id','barcode','is_active'];

    public function sku(){
    	return $this->belongsTo('App\Model\Items\VendorSkuDetails','vendor_sku_detail_id');
    }

    public function vendorSkuDetail(){
    	return $this->belongsTo('App\Model\Items\VendorSkuDetails','vendor_sku_detail_id');
    }
}
