<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    //protected $table = 'voucher';

    protected $table = 'cr_dr_voucher';
    public $timestamps = false;
    protected $primarykey = 'id';
    protected $fillable = ['v_id', 'voucher_no','store_id', 'user_id','dep_ref_trans_ref', 'ref_id', 'type', 'amount','status', 'effective_at', 'expired_at'];


	public function settelment()
	{
		return $this->hasMany('App\CrDrSettlementLog', 'voucher_id', 'id');
	}
	public function getTableColumns() {
        return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }

}
