<?php

namespace App\Model\Supplier;

use Illuminate\Database\Eloquent\Model;

class SupplierContact extends Model
{
    protected $table = 'supplier_contact';

	protected $fillable = ['supplier_id', 'person_name','designation','phone','email'];
}
