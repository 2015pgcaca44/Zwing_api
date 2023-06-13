<?php

use Illuminate\Database\Seeder;

use App\Store;
use App\Model\Stock\StockLogs;
use App\Model\Audit\AuditPlan;
use App\Model\Audit\AuditPlanAllocation;
use App\Model\Audit\AuditCountGroup;
use App\Model\Audit\AuditCountGroupDetail;


class UpdateAuditCountDetailsFromStockLog extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $v_id     = 75;  //vendor_sku_details

        JobdynamicConnection($v_id);

        $plans = AuditPlan::where('v_id', $v_id)->get();
        foreach($plans as $plan){
            echo " Audit Plan : ".$plan->id;
            $countGroup = AuditCountGroup::where('v_id', $v_id)->where('audit_plan_id', $plan->id)->first();
            $allocation = AuditPlanAllocation::where('v_id', $v_id)->where('audit_plan_id', $plan->id)->first();

            if($countGroup && $allocation){
                $details = AuditCountGroupDetail::where('v_id', $v_id)->where('audit_count_group_id', $countGroup->id)->get();

                foreach($details as $detail){
                    echo " - Audit Count Group : ".$detail->id;
                    $batch_no = $detail->batch_no;
                    if($batch_no == null || $batch_no == ''){
                        $batch_no = 0;
                    }

                    $serial_no = $detail->serial_no;
                    if($serial_no == null ||  $serial_no == ''){
                        $serial_no = 0;
                    }

                    $system_qty = StockLogs::where('v_id', $v_id)
                    ->where('store_id', $detail->store_id)
                    ->where('variant_sku', $detail->sku)
                    //->where('batch_id', $batch_no)
                    //->where('serial_id', $serial_no)
                    ->where('stock_point_id', $detail->stock_point_id)
                    ->whereBetween('date', ['2021-04-07', $allocation->activated_date])
                    ->sum('qty');

                    $detail->system_qty = $system_qty;
                    $detail->save();

                }

            }

        }

        echo ' Done ';

    }
}
