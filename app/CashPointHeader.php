<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CashPointHeader extends Model
{
  

    protected $table = 'cash_point_header';

	 protected $primaryKey = 'id';

	 protected $fillable = ['v_id','cash_point_type','cash_point_type_id','cash_point_header_cash_limit','terminal_ref_id','cash_point_header_code','cash_point_header_ref_code','status','created_by','max_cash_limit','cash_point_name', 'store_count','deleted_by','deleted_at'];

}
