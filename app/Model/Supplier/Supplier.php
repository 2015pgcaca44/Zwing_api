<?php

namespace App\Model\Supplier;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $table = 'supplier';

	protected $fillable = ['v_id', 'supplier_code','reference_code','trade_name','legal_name'];

	public static $rules = array(
        'supplier_code' 	=> 'required',
        'trade_name' 		=> 'required',
        'legal_supplier' 	=> 'required',
    );


    public function address()
	{
		return $this->hasOne('App\Model\Supplier\SupplierAddress', 'supplier_id', 'id');
	}


}
