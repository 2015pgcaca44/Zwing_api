<?php

namespace App\Model\Audit;

use Illuminate\Database\Eloquent\Model;

class AuditCountGroupDetail extends Model
{
	protected $table = 'audit_count_group_details';

	protected $fillable = ['v_id', 'store_id', 'vu_id', 'audit_plan_id', 'stock_point_id', 'audit_count_group_id', 'barcode', 'sku', 'batch_no', 'serial_no', 'physical_qty', 'system_qty'];
}
