<?php

namespace App\Model\SupplyPriceBook;

use Illuminate\Database\Eloquent\Model;

class SupplyPriceBook extends Model
{
    protected $table = 'supply_price_book';

	 protected $primaryKey = 'spb_id';

	 protected $fillable = ['v_id','spb_name','spb_description','spb_type','status','created_by','save_status'];
}
