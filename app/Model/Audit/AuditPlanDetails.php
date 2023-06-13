<?php

namespace App\Model\Audit;

use Illuminate\Database\Eloquent\Model;

class AuditPlanDetails extends Model
{
	protected $table = 'audit_plan_details';

	protected $fillable = ['v_id', 'audit_plan_id', 'department_id', 'barcode', 'sku', 'category_list', 'brand_list', 'deleted_at', 'deleted_by'];

	public function department()
	{
	    return $this->hasOne('App\Items\ItemDepartment', 'id', 'department_id');
	}

	public function brand()
	{
	    return $this->hasOne('App\Items\ItemBrand', 'id', 'brand_list');
	}
}
