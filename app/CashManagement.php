<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CashManagement extends Model
{
   
    protected $table = 'cash_management';

    protected $primaryKey = 'id';

    protected $fillable = ['cashier_id', 'terminal_id', 'amount', '	against_cashier_id','cash_type', 'status', 'type', 'store_id', 'v_id', 'date', 'time'];
}
