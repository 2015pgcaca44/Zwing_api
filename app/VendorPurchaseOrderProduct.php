<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VendorPurchaseOrderProduct extends Model
{
    protected $table = 'vendor_purchase_order_products';

    protected $primaryKey = 'id';

    protected $fillable = ['order_id','v_id', 'store_id', 'product_id', 'barcode','qty', 'created_at', 'updated_at'];
}
