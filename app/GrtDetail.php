<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GrtDetail extends Model
{
    
    protected $table = 'grt_details';

	protected $primaryKey = 'id';

	protected $fillable = ['grt_id', 'v_id', 'store_id','stock_point_id','item_name', 'item_id','available_qty','packet_id','packet_code','barcode', 'sku', 'sku_code','qty','supply_price','subtotal', 'discount', 'discount_details', 'tax', 'tax_details', 'charge', 'charge_details', 'total', 'remark','ref_grt_detail_no','batch_id','batch_code','serial_id','serial_code'];
}
