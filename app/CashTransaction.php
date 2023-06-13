<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CashTransaction extends Model
{
     protected $table = 'cash_transactions';
     
	 protected $primaryKey = 'id';

	 protected $fillable = ['v_id','store_id','session_id','request_from_user','request_from','request_from_id','request_ref_id','request_to','request_to_id','request_to_ref_id','transaction_behaviour', 'request_to_user','transaction_type','in_Cash_point_type','amount','status','approved_by','remark','doc_no','date','time','sync_status'];
}
