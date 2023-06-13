<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class kds extends Model
{
    

	protected $table = 'kds';

	protected $fillable = ['invoice_id', 'custom_order_id', 'ref_order_id', 'transaction_type', 'v_id', 'store_id', 'user_id', 'subtotal', 'discount', 'lpdiscount', 'manual_discount', 'coupon_discount', 'tax', 'total', 'trans_from', 'vu_id','kds_status', 'invoice_name','qty', 'remark', 'date', 'time', 'month', 'year', 'deleted_at'];


}
