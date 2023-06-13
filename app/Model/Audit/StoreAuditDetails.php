<?php

namespace App\Model\Audit;

use Illuminate\Database\Eloquent\Model;

class StoreAuditDetails extends Model
{
    protected $table = 'store_audit_details';
    protected $fillable = [
        'store_audit_status_id',
        'item_barcode',
        'quantity_available',
    ];

    public static $rules = array(
        'quantity_available'			=> 'required|numeric',
        'store_audit_status_id'	    => 'required|numeric',
        'item_barcode'		=> 'required',
    );
}
