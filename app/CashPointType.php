<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CashPointType extends Model
{
     protected $table = 'cash_point_types';

	 protected $primaryKey = 'id';

	 protected $fillable = ['type_name','cash_point_type_code','is_third_party','is_pos_visible','status'];
}
