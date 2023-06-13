<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DepRfdTrans extends Model
{

    protected $table 		= 'dep_rfd_trans';
	protected $primarykey 	= 'id';
	protected $fillable 	= ['doc_no', 'v_id', 'src_store_id','vu_id','user_id', 'terminal_id', 'trans_type','trans_sub_type', 'trans_src','trans_src_ref', 'amount','status','trans_from','remark','sync_status'];
    

	public function getTableColumns() {
        return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }

	public function cashier() {
		return $this->hasOne('App\VendorUserAuth','vu_id','vu_id');
	} //End of casshier

	public function vuser() {
		return $this->belongsTo('App\Vendor', 'vu_id', 'id');
	}

	public function user() {
		return $this->belongsTo('App\User', 'user_id', 'c_id');
	}

	public function payvia()
    {
        return $this->hasMany('App\Payment', 'order_id', 'doc_no');
    }

    public function payments()
    {
		return $this->belongsTo('App\Payment', 'order_id', 'doc_no');
    }

   
}
