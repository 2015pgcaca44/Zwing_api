<?php

namespace App\Model\Tax;
use App\Model\Tax\TaxRate;

use Illuminate\Database\Eloquent\Model;

class TaxRateGroupMapping extends Model
{
    protected $table = 'tax_rate_group_mapping';

    protected $fillable = ['tax_group_id','tax_slab_id','tax_code_id','trade_type','type','tg_preset_detail_id'];

    public function rate(){
        return $this->hasOne(
         'App\Model\Tax\TaxRate',
         'id',
         'tax_code_id'
        );
    }

    public function detail(){
         return $this->hasOne(
         'App\Model\Tax\TaxGroup',
         'id',
         'tax_group_id'
        );
    }

    public function getRateMapAttribute(Request $request){

	   $getData  = TaxRate::join('tax_group_preset','tax_group_preset.');


    }//End of getRatemapAttr



    
}
