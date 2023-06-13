<?php

namespace App\Model\Audit;

use Illuminate\Database\Eloquent\Model;

class AuditPlan extends Model
{
	protected $table = 'audit_plans';

	protected $fillable = ['v_id', 'name', 'description', 'plan_code', 'is_reconciliation', 'status', 'created_by', 'updated_by', 'deleted_at'];

	public function getDescriptionAttribute($value)
   	{
    	return (empty($value) ? '' : $value);
   	}

   	public function details()
   	{
   	    return $this->hasMany('App\Model\Audit\AuditPlanDetails', 'audit_plan_id', 'id');
   	}
}
