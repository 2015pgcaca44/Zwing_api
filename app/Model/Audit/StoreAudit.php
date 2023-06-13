<?php

namespace App\Model\Audit;

use Illuminate\Database\Eloquent\Model;

class StoreAudit extends Model
{
    protected $table = 'store_audit';
    protected $fillable = [
        'v_id',
        'store_id',
        'name',
        'description',
        'department_id',
        'schedule_date',
        'audit_date',
        'status',
    ];

    public static $rules = array(
//        'v_id'			    => 'required|numeric',
//        'store_id'			=> 'required|numeric',
        'department_id'	    => 'required|numeric',
        'name'			    => 'required',
        'schedule_date'		=> 'required',
    );
}
