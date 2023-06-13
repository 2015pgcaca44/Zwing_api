<?php

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;

class OpeningStockDetails extends Model
{
    protected $table = 'opening_stocks_details';

	protected $primaryKey = 'id';

	protected $fillable = ['store_id', 'v_id','vu_id','opening_stock_id','item_id', 'barcode','quantity','supply_price','sub_total','total_tax','total_amount'];
}
