<?php

namespace App\Model\Items;

use Illuminate\Database\Eloquent\Model;

class VendorSkuAssortmentMapping extends Model
{
    protected $table 	  = 'vendor_sku_assortment_mapping';
    protected $fillable   = ['v_id' ,'barcode','sku_code', 'assortment_code' ];

}
