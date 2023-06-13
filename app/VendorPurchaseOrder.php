<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VendorPurchaseOrder extends Model
{
    protected $table = 'vendor_purchase_orders';

    protected $primaryKey = 'id';

    protected $fillable = ['v_id', 'store_id', 'sm_approval_req_sent', 'sm_approved', 'dp_approved', 'bill_image', 'created_at', 'updated_at'];
}
