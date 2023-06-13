<?php

namespace App\Model\Audit;

use Illuminate\Database\Eloquent\Model;

class AuditPlanAllocation extends Model
{
	protected $table = 'audit_plan_allocations';

	protected $fillable = ['v_id', 'audit_plan_id', 'store_id', 'stockpoint_list', 'plan_allocation_code', 'type', 'start_date', 'due_date', 'validity', 'frequency', 'interval', 'status', 'allocated_by', 'allocated_date', 'activated_by', 'activated_date', 'submitted_by', 'submitted_date', 'deleted_at', 'deleted_by', 'sync_status'];

	public function plan()
	{
	    return $this->hasOne('App\Model\Audit\AuditPlan', 'id', 'audit_plan_id');
	}

	public function store()
	{
	    return $this->hasOne('App\Store', 'store_id', 'store_id');
	}
}
