<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CrDrSettlementLog extends Model
{
    //protected $table = 'voucher';

    protected $table = 'cr_dr_settlement_log';
    public $timestamps = false;
    protected $primarykey = 'id';
    protected $fillable = ['v_id', 'store_id', 'user_id', 'trans_src', 'trans_src_ref_id', 'order_id', 'applied_amount', 'voucher_id','status'];
    
    public function getTableColumns() {
        return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }
}
