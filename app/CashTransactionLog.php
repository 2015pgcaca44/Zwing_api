<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CashTransactionLog extends Model
{
     protected $table = 'cash_transaction_logs';
	 protected $primaryKey = 'id';
	 protected $fillable = ['v_id', 'store_id', 'session_id', 'logged_session_user_id', 'cash_point_name', 'cash_point_id', 'transaction_ref_id', 'cash_register_id', 'transaction_type', 'transaction_behaviour', 'amount', 'status', 'approved_by', 'remark', 'date', 'time'];
}
