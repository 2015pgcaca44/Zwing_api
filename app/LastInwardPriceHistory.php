<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LastInwardPriceHistory extends Model
{
    protected $table = 'last_inward_price_history';
    protected $primaryKey = 'id'; 
	protected $fillable = ['last_inward_price_id','v_id', 'source_site_id', 'destination_site_id','source_site_type','item_id','barcode','supply_price','discount','discount_details','tax','tax_details','charge','charge_details','source_transaction_type','source_transaction_id']; 
}
