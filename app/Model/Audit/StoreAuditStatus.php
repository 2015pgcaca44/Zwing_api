<?php

namespace App\Model\Audit;

use Illuminate\Database\Eloquent\Model;

class StoreAuditStatus extends Model
{
    protected $table = 'store_audit_status';
    protected $fillable = [
        'store_audit_id',
        'action',
        'action_by',
    ];

    public static $rules = array(
        'action_by'			=> 'required|numeric',
        'store_audit_id'	    => 'required|numeric',
        'action'		=> 'required',
    );
}
