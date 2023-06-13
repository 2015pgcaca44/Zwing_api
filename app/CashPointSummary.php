<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CashPointSummary extends Model
{
    

    protected $table = 'cash_point_summary'; 
	protected $primaryKey = 'id';

	 protected $fillable = ['v_id','store_id','cash_point_id','cash_point_name','opening','pay_in','pay_out','closing','date','time','partant_session_id','session_id'];
}
