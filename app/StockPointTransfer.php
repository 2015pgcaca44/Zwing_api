<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StockPointTransfer extends Model
{
    //

   protected $table = 'stock_point_transfers';

   protected $primaryKey = 'id';

   protected $fillable = ['id','v_id','store_id','vu_id','origin_stockpoint_id','destination_stockpoint_id','no_of_products','qty', 'status','remarks', 'sync_status', 'doc_no'];

}
