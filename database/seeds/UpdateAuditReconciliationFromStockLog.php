<?php

use Illuminate\Database\Seeder;


use App\Store;
use App\Model\Stock\StockLogs;
use App\Model\Audit\AuditPlan;
use App\Model\Audit\AuditPlanDetails;
use App\Model\Audit\AuditPlanAllocation;
use App\Model\Audit\AuditReconcilationReports;
use App\Model\Audit\AuditCountGroup;
use App\Model\Audit\AuditCountGroupDetail;

class UpdateAuditReconciliationFromStockLog extends Seeder
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
            $details = AuditPlanDetails::where('v_id', $v_id)->where('audit_plan_id', $plan->id)->get();

            if($allocation){
                foreach($details as $detail){

                    // echo ' Plan details id : '.$detail->id;
                    $barcodeCount = AuditCountGroupDetail::where([ 'v_id' => $v_id, 'store_id' => $detail->store_id, 'audit_plan_id' => $plan->id, 'stock_point_id' => $detail->stock_point_id, 'sku' => $detail->sku ]);

                    $reconcile = AuditReconcilationReports::where('v_id', $v_id)->where('audit_plan_id', $plan->id)->where('audit_plan_details_id', $detail->id)->first();
                    if($reconcile){
                        // echo ' reconcile : '.$reconcile->id;

                    //     $available_qty = StockLogs::where('v_id', $v_id)
                    //     ->where('store_id', $reconcile->store_id)
                    //     ->where('variant_sku', $reconcile->sku)
                    //     //->where('batch_id', $batch_no)
                    //     //->where('serial_id', $serial_no)
                    //     ->where('stock_point_id', $reconcile->stock_point_id)
                    //     ->whereBetween('date', ['2021-04-07', $allocation->activated_date])
                    //     ->sum('qty');
                    
                        $available_qty = $barcodeCount->sum('system_qty');
                        $reconcile->available_qty = $available_qty;
                        $short_qty = $excess_qty = 0;
                        if($barcodeCount->count() > 0) {
                            if($available_qty > $barcodeCount->sum('physical_qty')) {
                                $short_qty = $available_qty - $barcodeCount->sum('physical_qty');
                                $excess_qty = 0;
                                $qty = $short_qty;
                            } else if($available_qty < $barcodeCount->sum('physical_qty')) {
                                $short_qty = 0;
                                $excess_qty = $barcodeCount->sum('physical_qty') - $available_qty;
                                $qty = $excess_qty;
                            }
                        } else {
                            $short_qty = $available_qty;
                        }


                        $reconcile->short = $short_qty;
                        $reconcile->excess = $excess_qty;
                        $reconcile->save();

                    }


                }
            }

        }

        echo ' Done ';
    }
}
