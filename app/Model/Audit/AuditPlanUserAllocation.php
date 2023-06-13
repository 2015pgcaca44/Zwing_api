<?php

namespace App\Model\Audit;

use Illuminate\Database\Eloquent\Model;

class AuditPlanUserAllocation extends Model
{
	protected $table = 'audit_plan_user_allocations';

	protected $fillable = ['v_id', 'store_id', 'vu_id', 'audit_plan_id', 'audit_plan_allocation_code', 'department_list', 'brand_list', 'category_list', 'stockpoint_list', 'status', 'allocated_items', 'total_stockpoints_counts', 'counted_items', 'completed_on'];

	public function plan()
	{
	    return $this->belongsTo('App\Model\Audit\AuditPlan', 'audit_plan_id', 'id');
	}

	public function planAllocation()
	{
	    return $this->belongsTo('App\Model\Audit\AuditPlanAllocation', 'audit_plan_allocation_code', 'plan_allocation_code')->where('store_id', $this->store_id);
	}
}