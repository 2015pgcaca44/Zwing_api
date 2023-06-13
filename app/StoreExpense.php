<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StoreExpense extends Model
{
    protected $table = 'store_expenses';
     
	 protected $primaryKey = 'id';

	 protected $fillable = [ 'v_id','store_id','expense_desc','amount','expense_type_name','expense_type_id','expense_remark','doc_no','created_by','date','time'];
}
