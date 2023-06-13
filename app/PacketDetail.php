<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PacketDetail extends Model
{
  
    protected $table 	  = 'packet_details';
	protected $primaryKey = 'id';
	protected $fillable   = ['v_id', 'store_id', 'stock_point_id', 'packet_id', 'item_id','ref_packet_item_id','ref_advice_detail_id','barcode','variant_sku','product_name', 'batch_code', 'serial_code','status','qty', 'date','remark'];

}
