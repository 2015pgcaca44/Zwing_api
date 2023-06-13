<?php

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;

class OpeningStock extends Model
{
   protected $table = 'opening_stock';

	protected $primaryKey = 'id';

	protected $fillable = ['store_id', 'v_id','vu_id','code','stock_point_id','qty','supply_price','subtotal','tax','total','total_item','status','updated_by', 'sync_status'];
}
