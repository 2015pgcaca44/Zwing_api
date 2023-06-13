<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StockPointTransferDetail extends Model
{
    

   protected $table = 'stock_point_transfer_details';

   protected $primaryKey = 'id';

   protected $fillable = ['v_id','store_id','vu_id','stock_point_id', 'stock_point_transfer_id','sku','sku_code','barcode','item_id','batch_id','serial_id','qty', 'status'];
}
