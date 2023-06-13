<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CashPoint extends Model
{
  

    protected $table = 'cash_points';

	 protected $primaryKey = 'id';

	 protected $fillable = ['v_id','store_id','cash_point_name','cash_point_type', 'ref_id','cash_point_type_id','cash_point_code','max_cash_limit','status'];

}
