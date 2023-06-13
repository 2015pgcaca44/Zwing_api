<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    protected $table = 'cash_registers';

	protected $fillable = ['name','code','v_id','store_id','terminal_type','terminal_code','udid','licence_no'];
}
