<?php

namespace App\Model\Supplier;

use Illuminate\Database\Eloquent\Model;

class SupplierAddress extends Model
{
    protected $table = 'supplier_address';

	protected $fillable = ['supplier_id', 'location','address_line_1','city_id','country_id','pincode','gstin'];
}
