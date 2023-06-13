<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerGroup extends Model
{

	//protected $connection  = 'mysql';
    protected $table = 'customer_groups';

    protected $primaryKey = 'id';
    
    protected $fillable = ['name','v_id','description','group_code','items_limit_perbill','maximum_limit_perbill','items_limit_perday','maximum_limit_perday','value_limit_perbill','maximum_value_perbill','value_limit_perday','maximum_value_perday','allow_credit','maximum_credit_limit','allow_manual_discount','maximum_discount_perbill','allowed_tagging','allow_manual_discount_bill_level'];

    public function customers(){
    	return $this->belongsToMany('App\User', 'customer_group_mappings');
    }
}
