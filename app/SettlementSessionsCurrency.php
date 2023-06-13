<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SettlementSessionsCurrency extends Model
{
	protected $table 	= 'settlement_sessions_currency';
	protected $fillable = ['store_id', 'v_id','vu_id','settlement_id','currency_type','currency','qty','total','settlement_session_type'];
}
