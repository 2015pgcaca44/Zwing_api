<?php

namespace App\Model\Audit;

use Illuminate\Database\Eloquent\Model;

class AuditReconcilationReports extends Model
{
	protected $table = 'audit_reconciliation_reports';

	protected $fillable = ['v_id', 'store_id', 'audit_plan_id', 'audit_plan_allocation_code', 'audit_plan_details_id', 'stock_point_id', 'barcode', 'sku', 'available_qty', 'actual_qty', 'short', 'excess', 'total', 'status', 'created_by'];
}
