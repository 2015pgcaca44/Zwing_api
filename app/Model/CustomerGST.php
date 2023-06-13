<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class CustomerGST extends Model
{
	protected $table = 'customer_gstin';

    protected $fillable = ['v_id', 'c_id','legal_name', 'state_id', 'created_by', 'gstin'];

}
