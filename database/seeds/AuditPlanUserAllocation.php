<?php

use Illuminate\Database\Seeder;
use App\Model\Audit\AuditPlanUserAllocation;
use App\Model\Audit\AuditPlanAllocation;

class AuditPlanUserAllocations extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
    	AuditPlanUserAllocation::truncate();
    	AuditPlanUserAllocation::create([ 'department_list' => '["67","147"]', 'v_id' => '76', 'vu_id' => '1137', 'store_id' => '196', 'audit_plan_id' => '1', 'brand_list' => '["60","147"]', 'category_list' => '[{"name":"Men","code":"C001"},{"name":"t-shirts","code":"C002"},{"name":"KIDS","code":"C005"}]', 'stockpoint_list' => '["765","766","767","768","770"]', 'status' => 'A', 'allocated_items' => '6', 'total_stockpoints_counts' => '0', 'counted_items' => '0' ]);

    	AuditPlanAllocation::where('audit_plan_id', 1)->update([  'activated_date' => date('Y-m-d'), 'activated_by' => 1137 ]);
    }
}
