<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Model\Audit\AuditPlanUserAllocation;
use App\Model\Audit\AuditCountGroup;
use App\Model\Audit\AuditPlanDetails;
use App\Items\ItemDepartment;
use App\Items\ItemBrand;
use App\Model\Items\VendorSku;
use App\Model\Stock\StockPoints;
use App\Model\Stock\StockPointSummary;
use Auth;
use App\Model\Audit\AuditCountGroupDetail;
use App\StoreSettings;
use App\Model\Audit\AuditPlanAllocation;

class AuditController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function list(Request $request)
    {
        // if($request->ajax()) {
            $current_date      =  date('Y-m-d');
            $list = AuditPlanUserAllocation::join('audit_plan_allocations','audit_plan_allocations.audit_plan_id','audit_plan_user_allocations.audit_plan_id')->select('audit_plan_user_allocations.id', 'audit_plan_user_allocations.audit_plan_id', 'audit_plan_user_allocations.store_id', 'audit_plan_user_allocations.department_list', 'audit_plan_user_allocations.total_stockpoints_counts', 'audit_plan_user_allocations.category_list', 'audit_plan_user_allocations.brand_list', 'audit_plan_user_allocations.stockpoint_list', 'audit_plan_user_allocations.status','audit_plan_user_allocations.vu_id', 'audit_plan_user_allocations.audit_plan_allocation_code')->where('audit_plan_user_allocations.v_id', $request->v_id)->where('audit_plan_user_allocations.vu_id', $request->vu_id)->where('audit_plan_user_allocations.store_id', $request->store_id)->whereDate('due_date', '>=' ,$current_date)->orderBy('audit_plan_allocations.id','desc')->groupBy(['audit_plan_user_allocations.vu_id', 'audit_plan_user_allocations.audit_plan_allocation_code'])->paginate(10);

            $data = [];

            $list->filter(function ($item) {
                $item->name = $item->plan->name;
                $item->description = $item->plan->description;
                if($item->description == '') {
                    $item->description = '-';
                }
                $item->activated_on = date('d M Y', strtotime($item->planAllocation->activated_date));
                $item->due_on = date('d M Y', strtotime($item->planAllocation->due_date));
                $department_list = json_decode($item->department_list);
                $item->department_list = ItemDepartment::whereIn('id', $department_list)->get()->pluck('name');
                $filterData = json_decode($item->category_list);
                $item->category_list = VendorSku::select('cat_name_1')->distinct('cat_name_1')->whereIn('cat_code_1', $filterData)->where('v_id', Auth::user()->v_id)->get()->pluck('cat_name_1');
                $brand_list = json_decode($item->brand_list);
                $item->brand_list = ItemBrand::whereIn('id', $brand_list)->get()->pluck('name');
                $stockpoint_list = json_decode($item->stockpoint_list);
                $filterStockPoint = StockPoints::select('name','id')->whereIn('id', $stockpoint_list)->get();
                $filterStockPoint = collect($filterStockPoint)->filter(function ($points) use ($item) {
                                        $points->is_used = AuditCountGroup::where([ 'v_id' => Auth::user()->v_id, 'store_id' => $item->store_id, 'vu_id' => $item->vu_id, 'audit_plan_id' => $item->audit_plan_id, 'stock_point_id'  => $points->id  ])->exists();
                                        return $points;
                                    });
                $item->stockpoint_list = $filterStockPoint;
                $lastUsed = AuditCountGroup::where([ 'v_id' => Auth::user()->v_id, 'store_id' => $item->store_id, 'vu_id' => $item->vu_id, 'audit_plan_id' => $item->audit_plan_id ])->latest()->first();
                $item->last_count = (empty($lastUsed) ? '-' : date('d M Y h:i A', strtotime($lastUsed->updated_at)));
                $item->is_reconciliation = (int)$item->plan->is_reconciliation;
                unset($item->store_id);
                $item->unsetRelation('plan')->unsetRelation('planAllocation');
                return $item;
            });

                return response()->json(['data' => $list, 'status' => 'success'], 200);
        // }
    }

    public function stockpointWiseList(Request $request)
    {
        // if($request->ajax()) {
        $list = AuditCountGroup::with('stockpoint:id,name')->select('description', 'stock_point_id', 'completed_on', 'unique_products', 'total_product_qty')->where('v_id', $request->v_id)->where('vu_id', $request->vu_id)->where('store_id', $request->store_id)->where('audit_plan_id', $request->audit_plan_id)->get();
            
            return response()->json(['data' => $list, 'status' => 'success', 'message' => 'stockpoint list'], 200);
        // }
    }

    public function productChecker(Request $request)
    {
        // if($request->ajax()) {
        $product = AuditPlanDetails::where('v_id', $request->v_id)->where('barcode', $request->barcode)->where('audit_plan_id', $request->audit_plan_id)->first();
        if($product) {
            $stockpoint = StockPointSummary::where('v_id', $request->v_id)->where('store_id', $request->store_id)->where('barcode', $request->barcode)->where('stock_point_id', $request->stock_point_id)->first();
            if($stockpoint) {
                $product['name'] = '';
                $prodDetails['barcode'] = $product->barcode;
                $prodDetails['department'] = $product->department->name;
                $filterData = json_decode($product->category_list);
                $prodDetails['category'] = collect($filterData)->first()->name;
                $prodDetails['brand'] = $product->brand->name;
                return response()->json(['status' => 'success' ,'data' => $prodDetails], 200);      
            } else {
                return response()->json(['status' => 'fail', 'message' => 'Product not found stockpoint'], 200);    
            }
            
        } else {
            return response()->json(['status' => 'fail', 'message' => 'Product not found'], 200);
        }
        
        // }
    }

    public function saveCount(Request $request)
    {
        return $this->newSaveCount($request);
        // if($request->ajax()) {
            $this->validate($request, [
                'v_id'                   => 'required',
                'store_id'               => 'required',
                'vu_id'                  => 'required',
                'audit_plan_id'          => 'required',
                'stock_point_id'         => 'required',
                'product_list'           => 'required',
                'audit_allocation_code'  => 'required'
            ]);

            $productList = json_decode($request->product_list);
            $productList = collect($productList);

            $auditCountGroup = AuditCountGroup::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'audit_plan_id' => $request->audit_plan_id, 'stock_point_id' => $request->stock_point_id, 'audit_plan_allocation_code' => $request->audit_allocation_code ])->first();

            if(empty($auditCountGroup)) {
                $auditCountGroup = AuditCountGroup::create([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'audit_plan_id' => $request->audit_plan_id, 'stock_point_id' => $request->stock_point_id, 'description' => $request->description,'audit_plan_allocation_code' => $request->audit_allocation_code ,'unique_products' => $productList->count(), 'total_product_qty' => $productList->sum('qty') ]);
            } else {
                $auditCountGroup->description = $request->description;
                $auditCountGroup->unique_products = $productList->count();
                $auditCountGroup->total_product_qty = $productList->sum('qty');
                $auditCountGroup->save();
            }

            AuditCountGroupDetail::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'audit_plan_id' => $request->audit_plan_id, 'stock_point_id' => $request->stock_point_id ])->delete();

            foreach ($productList as $key => $value) {
                $data = [ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'audit_plan_id' => $request->audit_plan_id, 'stock_point_id' => $request->stock_point_id, 'audit_count_group_id' => $auditCountGroup->id, 'physical_qty' => $value->qty  ];
                $systemQty = StockPointSummary::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'stock_point_id' => $request->stock_point_id, 'barcode' => $value->barcode, 'variant_sku' => $value->sku ])->first();
                if(!empty($systemQty)) {
                    $data['system_qty'] = $systemQty->qty;
                }
                unset($value->qty);
                $merge = array_merge($data, (array)$value);
                $condition = collect($merge)->forget(['physical_qty', 'system_qty'])->toArray();
                AuditCountGroupDetail::updateOrCreate($condition, $merge);
            }

            AuditCountGroup::find($auditCountGroup->id)->update(['completed_on' => date('Y-m-d')]);

            $countedItems = AuditCountGroup::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'audit_plan_id' => $request->audit_plan_id, 'audit_plan_allocation_code' => $request->audit_allocation_code ])->sum('unique_products');

            AuditPlanUserAllocation::where(['v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'audit_plan_id' => $request->audit_plan_id, 'audit_plan_allocation_code' => $request->audit_allocation_code])->update([ 'status' => 'F', 'counted_items' => $countedItems ]);

            return response()->json([ 'status' => 'success', 'message' => 'Counting saved successfully' ], 200);
        // }
    }

    public function getStockpointProducts(Request $request)
    {
        $this->validate($request, [
            'v_id'          => 'required',
            'store_id'      => 'required',
            'vu_id'         => 'required',
            'audit_plan_id' => 'required',
            'stock_point_id'=> 'required'
        ]);
        // if($request->ajax()) {

        $AuditPlanUser = AuditPlanUserAllocation::select('category_list')->where(['v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'audit_plan_id' => $request->audit_plan_id])->first(); 

        // dd($AuditPlanUser->category_list);

        $category_list = jsonToArray(collect($AuditPlanUser->category_list)->toArray(), true);
        $category_list = implode(",", $category_list);
        
        $skuBarcode = VendorSku::select('vendor_sku_flat_table.sku','vendor_sku_flat_table.name',
            'vendor_sku_flat_table.cat_name_1','vendor_sku_flat_table.department_name','vendor_sku_flat_table.brand_name',
            'vendor_sku_detail_barcodes.v_id','vendor_sku_detail_barcodes.barcode')->join('vendor_sku_detail_barcodes','vendor_sku_detail_barcodes.vendor_sku_detail_id', 'vendor_sku_flat_table.vendor_sku_detail_id');

        $productList = AuditPlanDetails::
                //       leftJoin('vendor_sku_flat_table', function($query){
                //       $query->on('audit_plan_details.v_id', 'vendor_sku_flat_table.v_id')
                //                ->on('audit_plan_details.barcode', 'vendor_sku_flat_table.barcode')
                //                ->on('audit_plan_details.sku', 'vendor_sku_flat_table.sku');
                // })
                leftJoinSub($skuBarcode, 'sku_barcode', function($query){
                    $query->on('sku_barcode.v_id', 'audit_plan_details.v_id');
                    $query->on('sku_barcode.barcode', 'audit_plan_details.barcode');
                    $query->on('sku_barcode.sku', 'audit_plan_details.sku');
                })
                ->leftJoin('stock_point_summary', function($query) {
                    $query->on('audit_plan_details.v_id', 'stock_point_summary.v_id')
                          ->on('audit_plan_details.barcode', 'stock_point_summary.barcode')
                          ->on('audit_plan_details.sku', 'stock_point_summary.variant_sku');
                })
                  ->where(['audit_plan_details.v_id' => $request->v_id, 'audit_plan_details.audit_plan_id' => $request->audit_plan_id, 'stock_point_summary.stock_point_id' => $request->stock_point_id, 'stock_point_summary.store_id' => $request->store_id ])->whereRaw('MATCH(audit_plan_details.category_list) AGAINST("'.$category_list.'" IN BOOLEAN MODE)')
                  ->select('sku_barcode.name', 'sku_barcode.barcode', 'sku_barcode.cat_name_1 as category', 'sku_barcode.department_name as department', 'sku_barcode.brand_name as brand', 'sku_barcode.sku', 'stock_point_summary.qty as qty')
                  ->groupBy([ 'sku_barcode.barcode' ])
                  ->paginate(500);

            $total_page = $productList->lastpage();
            $total = $productList->total();

                  $productList = $productList->filter(function($item) use ($request){
                                    $item->stock_point_id = (int)$request->stock_point_id;
                                    $item->v_id = $request->v_id;
                                    $item->unit_mrp = 0;
                                    $item->batch_no = 0;
                                    $item->serial_no = 0;
                                    $item->qty = AuditCountGroupDetail::where('v_id', $request->v_id)->where('vu_id', $request->vu_id)->where('store_id', $request->store_id)->where('barcode', $item->barcode)->where('stock_point_id', $request->stock_point_id)->where('audit_plan_id', $request->audit_plan_id)->sum('physical_qty');
                                    // $item->qty = 3;
                                    $item->timestamp = time();
                                    $item->is_scan = $item->qty > 0 ? true : false;
                                    // $productChecker = StockPointSummary::where('v_id', $request->v_id)->where('store_id', $request->store_id)->where('barcode', $item->barcode)->where('stock_point_id', $request->stock_point_id)->exists();
                                    return $item;
                                  })->values()->all();
                  $description = AuditCountGroup::select('description')->where(['v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'audit_plan_id' => $request->audit_plan_id, 'stock_point_id' => $request->stock_point_id])->first();

        return response()->json(['status' => 'success', 'message' => 'stockpoint products', 'data' => $productList, 'description' => $description, 'last_page' => $total_page, 'total' => $total ], 200);
        // }
    }

    public function completeAudit(Request $request)
    {
        // if($request->ajax()) {
            AuditPlanUserAllocation::where(['v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'audit_plan_id' => $request->audit_plan_id, 'audit_plan_allocation_code' => $request->audit_allocation_code ])->update([ 'status' => 'C', 'completed_on' => date('Y-m-d h:i:s') ]);
        //disable inventory settings     
            $storeSettings = StoreSettings::where([ 'store_id' => $request->store_id, 'name' => '', 'status' => '1' ])->latest()->first();
            if (!empty($storeSettings)) {
                $settingsUpdate='{"audit": { "is_reconciliation":"0" }}';
              StoreSettings::where('id',$storeSettings->id)->update(['settings' => $settingsUpdate]); 
            }
        
            return response()->json(['message' => 'audit submitted successfully', 'status' => 'success'], 200);
        // }
    }

    public function reconsileChecker(Request $request) 
    {
        $storeSettingChecker = StoreSettings::where([ 'store_id' => $request->store_id, 'status' => '1', 'name' => 'audit' ])->first();
        if(empty($storeSettingChecker)) {
            $settings['is_reconciliation'] = $request->is_reconciliation;
            StoreSettings::create([ 'store_id' => $request->store_id, 'name' => 'audit', 'settings' => json_encode($settings), 'status' => '1' ]);
        } else {
            $settings = json_decode($storeSettingChecker->settings);    
            $settings->is_reconciliation = $request->is_reconciliation;
            $storeSettingChecker->settings = json_encode($settings);
            $storeSettingChecker->save();
        }

        return response()->json([ 'status' => 'checked' ], 200);
    }

    public function newSaveCount(Request $request)
    {
        // if($request->ajax()) {
            $this->validate($request, [
                'v_id'                   => 'required',
                'store_id'               => 'required',
                'vu_id'                  => 'required',
                'audit_plan_id'          => 'required',
                'stock_point_id'         => 'required',
                'product_list'           => 'required',
                'audit_allocation_code'  => 'required'
            ]);

            $productList = json_decode($request->product_list);
            $productList = collect($productList);

            if($productList->isEmpty() && !$request->has('id')) {
                $auditCountGroupDetails = AuditCountGroup::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'audit_plan_id' => $request->audit_plan_id, 'stock_point_id' => $request->stock_point_id, 'audit_plan_allocation_code' => $request->audit_allocation_code ])->first();

                if(empty($auditCountGroupDetails)) {
                    $auditCountGroupDetails = AuditCountGroup::create([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'audit_plan_id' => $request->audit_plan_id, 'stock_point_id' => $request->stock_point_id, 'description' => $request->description,'audit_plan_allocation_code' => $request->audit_allocation_code ]);
                } else {
                    $auditCountGroupDetails->description = $request->description;
                    // $auditCountGroupDetails->unique_products = $productList->count();
                    // $auditCountGroupDetails->total_product_qty = $productList->sum('qty');
                    $auditCountGroupDetails->save();
                }

                AuditCountGroupDetail::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'audit_plan_id' => $request->audit_plan_id, 'stock_point_id' => $request->stock_point_id ])->delete();
                return response()->json([ 'status' => 'created', 'data' => $auditCountGroupDetails ]);
            } else {
                // $auditGroupCount = AuditCountGroupDetail::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'audit_plan_id' => $request->audit_plan_id, 'stock_point_id' => $request->stock_point_id, 'audit_count_group_id' => $request->id ])->count();
                if($request->has('product_count') && $request->product_count === 0) {

                    $auditGroupUniqueCount = AuditCountGroupDetail::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'audit_plan_id' => $request->audit_plan_id, 'stock_point_id' => $request->stock_point_id, 'audit_count_group_id' => $request->id ])->distinct()->count('barcode');
                    $auditGroupTotalQty = AuditCountGroupDetail::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'audit_plan_id' => $request->audit_plan_id, 'stock_point_id' => $request->stock_point_id, 'audit_count_group_id' => $request->id ])->sum('physical_qty');
                    AuditCountGroup::find($request->id)->update([ 'completed_on' => date('Y-m-d'), 'unique_products' => $auditGroupUniqueCount, 'total_product_qty' => $auditGroupTotalQty ]);

                    $countedItems = AuditCountGroup::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'audit_plan_id' => $request->audit_plan_id, 'audit_plan_allocation_code' => $request->audit_allocation_code ])->sum('unique_products');

                    AuditPlanUserAllocation::where(['v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'audit_plan_id' => $request->audit_plan_id, 'audit_plan_allocation_code' => $request->audit_allocation_code])->update([ 'status' => 'F', 'counted_items' => $countedItems ]);

                    return response()->json([ 'status' => 'success', 'message' => 'Counting saved successfully' ], 200);

                } else {
                    foreach ($productList as $key => $value) {
                        $data = [ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'audit_plan_id' => $request->audit_plan_id, 'stock_point_id' => $request->stock_point_id, 'audit_count_group_id' => $request->id, 'physical_qty' => $value->qty  ];
                        $systemQty = StockPointSummary::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'stock_point_id' => $request->stock_point_id, 'barcode' => $value->barcode, 'variant_sku' => $value->sku ])->first();
                        if(!empty($systemQty)) {
                            $data['system_qty'] = $systemQty->qty;
                        }
                        unset($value->qty);
                        $merge = array_merge($data, (array)$value);
                        $condition = collect($merge)->forget(['physical_qty', 'system_qty'])->toArray();
                        AuditCountGroupDetail::updateOrCreate($condition, $merge);
                    }
                    $remaining_list = $productList->count();
                    return response()->json(["status" => 'continue' , 'remaining' => $remaining_list]);
                }
            }

            // AuditCountGroupDetail::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'audit_plan_id' => $request->audit_plan_id, 'stock_point_id' => $request->stock_point_id ])->delete();

            

            

            
        // }
    }
}
