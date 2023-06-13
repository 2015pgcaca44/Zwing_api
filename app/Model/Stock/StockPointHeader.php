<?php

//namespace App;
namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;

class StockPointHeader extends Model
{
    protected $table = 'stock_point_header';

	 protected $primaryKey = 'id';

	 protected $fillable = ['name','code','stock_point_type','ref_stock_point_header_code','status','type','is_sellable','is_default']; 
}
