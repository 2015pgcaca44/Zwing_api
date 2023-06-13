<?php

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;

class StockTransferOrderDetails extends Model
{
    protected $table = 'stock_transfer_order_details';
    public $timestamps = true;
    protected $fillable = [
			"sto_trf_ord_id",
			"v_id",                    
			"store_id",  
			"stock_point_id",
			"vu_id",                 
			"item_name",
			"packet_id",
			"packet_code",
			"sku",   
			"sku_code",       
			"barcode",         
			"item_id",
			"qty",      
			"supply_price",
			"charge",        
			"subtotal",    
			"discount",  
			"tax",         
			"total"  ];
}
