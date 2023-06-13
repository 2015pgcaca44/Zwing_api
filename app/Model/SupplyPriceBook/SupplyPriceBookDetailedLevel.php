<?php

namespace App\Model\SupplyPriceBook;

use Illuminate\Database\Eloquent\Model;

class SupplyPriceBookDetailedLevel extends Model
{
    protected $table = 'spb_detailed_level';

	 protected $primaryKey = 'spb_detailed_id';

	 protected $fillable = ['v_id','spb_id','department_id','category_list','brand_list','barcode','sales_price','spb_category','base_price_type','base_price_table','behaviour','factor','tax_type','tax_code','discount_type','discount_value','charge_code'];
}
