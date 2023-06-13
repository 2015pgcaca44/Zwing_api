<?php

namespace App\Model\SupplyPriceBook;

use Illuminate\Database\Eloquent\Model;

class SupplyPriceBookHeaderLevel extends Model
{
    protected $table = 'spb_header_level';

	 protected $primaryKey = 'spb_header_id';

	 protected $fillable = ['v_id','spb_id','spb_category','base_price_type','base_price_table','behaviour','factor','tax_type','tax_code','discount_type','discount_value','charge_code'];
}
