<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Model\Stock\StockLogs;
use App\Model\Stock\StockPoints;
use App\Model\Stock\StockCurrentStatus;
use App\Model\Stock\StockIntransit;
use App\Model\Stock\StockIn;
use App\Model\Stock\StockOut;
use App\Model\Stock\StockAdjustment;
use App\Model\Stock\StockPointSummary;
use App\Model\Grn\Grn;
use App\Model\Grn\GrnList;
use App\Model\Grn\AdviseList;
use App\Model\Items\VendorSkuDetails;
use App\Store;
use App\VendorSetting;
use DB;

class StockController extends Controller
{

   public function __construct()
   {
    //JobdynamicConnection(127);    
   }
   
   public function moveInventory(Request $request)
   {
        $request->validate(StockLogs::$stockLogRule);
        $stock_point_id = null;
        if($request->move_from != 'adhoc') {
            $request->validate(StockLogs::$noAdhocRule);
            $stock_point_id = StockPoints::find(json_decode($request->to_point)->id)->id;

            if(!$stock_point_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid Destination Stock Point'
                ], 409);
            }
        }
        $ref_stock_point_id = null;
        $items = json_decode($request->items);
        if(count($items) == 0){
            return response()->json([
                    'status' => 'error',
                    'message' => 'Item Cannot be empty'
                ], 409);
        }

        $validate_result = $this->validateItems($items, $request->move_from);
        if($validate_result['status'] == 'error') {
            return response()->json($validate_result, 422);
        }

        $vendor_id = '';

        DB::beginTransaction();
        try {
            switch ($request->move_from) {
                case 'adhoc':

                    $data = array('v_id' => Auth::user()->vendor_id,
                        'grn_from' => 'ADHOC',
                        'store_id' => Auth::user()->store_id,
                        'qty' => 0,
                        'subtotal' => 0,
                        'discount' => 0,
                        'tax' => 0,
                        'total' => 0,
                        'damage_qty' => 0,
                        'lost_qty' => 0,
                    );

                    $data['grn_no'] = grn_no_generate(Auth::user()->vendor_id);
                    $grn = Grn::create($data);
                    $totalQuantity = 0;
                    $totalMrp = 0;

                    foreach ($items as $item) {
                        $item_info = Item::select('id', 'has_serial', 'has_batch')->where('id', $item->item_id)->first();
                        if ($item_info) {
                            $varient_price_ids = VendorItemPriceMapping::select('item_price_id')
                                ->with('priceDetail:id,mrp')
                                ->where('v_id', Auth::user()->vendor_id)
                                ->where('item_id', $item->item_id)
                                ->where('variant_combi', $item->variant_combi)
                                ->get();
                            foreach ($item->batch as $bch) {
                                if (!$bch->mrp) {
                                    return response()->json([
                                        'status' => 'error',
                                        'message' => 'Item MRP cannot be zero.',
                                        'errors' => [
                                            'mrp' => [
                                                'MRP cannot be 0.'
                                            ]
                                        ]
                                    ], 422);
                                }
                                $priceId = null;
                                $batch_id = null;
                                foreach ($varient_price_ids as $varient_price_id) {
                                    if ($varient_price_id->priceDetail->mrp == $bch->mrp) {
                                        $priceId = $varient_price_id->item_price_id;
                                        break;
                                    }
                                }
                                if (!$priceId) {
                                    $priceId = ItemPrices::select('id')
                                        ->where('mrp', $bch->mrp)
                                        ->where('rsp', $bch->mrp)
                                        ->where('special_price', $bch->mrp)
                                        ->first();
                                    if (!$priceId) {
                                        $priceId = ItemPrices::create([
                                            'mrp' => $bch->mrp,
                                            'rsp' => $bch->mrp,
                                            'special_price' => $bch->mrp,
                                        ])->id;
                                    } else {
                                        $priceId = $priceId->id;
                                    }
                                    VendorItemPriceMapping::create([
                                        'v_id' => Auth::user()->vendor_id,
                                        'item_id' => $item->item_id,
                                        'variant_combi' => $item->variant_combi,
                                        'item_price_id' => $priceId,
                                    ]);
                                }

                                if ($item_info->has_batch == '1') {
                                    if (!$bch->batch_no || !$bch->mfg_date || !($bch->exp_date || $bch->valid_months)) {
                                        return response()->json([
                                            'status' => 'error',
                                            'message' => 'Fill the product batch information.',
                                            'errors' => []
                                        ], 422);
                                    }
                                    $batch_id = Batch::select('id')
                                        ->where('v_id', Auth::user()->vendor_id)
                                        ->where('batch_no', $bch->batch_no)
                                        ->first();
                                    if (!$batch_id) {
                                        $batch_id = Batch::create([
                                            'v_id' => Auth::user()->vendor_id,
                                            'batch_no' => $bch->batch_no,
                                            'mfg_date' => $bch->mfg_date,
                                            'exp_date' => $bch->exp_date,
                                            'valid_months' => $bch->valid_months,
                                            'item_price_id' => $priceId
                                        ])->id;
                                    } else {
                                        $batch_id = $batch_id->id;
                                    }
                                } else {
                                    $batch_id = Batch::select('id')
                                        ->where('v_id', Auth::user()->vendor_id)
                                        ->whereNull('batch_no')
                                        ->where('item_price_id', $priceId)
                                        ->first();
                                    if (!$batch_id) {
                                        $batch_id = Batch::create([
                                            'v_id' => Auth::user()->vendor_id,
                                            'item_price_id' => $priceId
                                        ])->id;
                                    } else {
                                        $batch_id = $batch_id->id;
                                    }
                                }

                                $where = array(
                                    'v_id' => Auth::user()->vendor_id,
                                    'grn_id' => $grn->id,
                                    'item_no' => $item->variant_barcode,
                                    'qty' => $bch->move_qty
                                );
                                $grnList = GrnList::where($where)->first();
                                if(!$grnList){
                                    $grnList = new GrnList();
                                }
                                $grnList->v_id        = Auth::user()->vendor_id;
                                $grnList->grn_id      = $grn->id;
                                $grnList->item_no     = $item->variant_barcode;
                                $grnList->request_qty = $bch->move_qty;
                                $grnList->store_id    = Auth::user()->store_id;
                                $grnList->qty         = $bch->move_qty;
                                $grnList->unit_mrp    = $bch->mrp;
                                $grnList->subtotal    = $bch->mrp;
                                $grnList->discount    = 0;
                                $grnList->tax         = 0;
                                $grnList->total       = 0;
                                $grnList->damage_qty  = 0;
                                $grnList->lost_qty    = 0;
                                $grnList->is_batch    = 1;
                                $grnList->save();
                                $totalQuantity += $bch->move_qty;
                                $totalMrp += $bch->mrp;

                                #####################
                                #### Begin Batches ##
                                #####################

                                GrnBatch::create([
                                    'grnlist_id' => $grnList->id,
                                    'batch_id' => $batch_id,
                                    'qty' => $bch->move_qty
                                ]);

                                #####################
                                ####  End Batches ###
                                #####################

                                if ($item_info->has_serial == '1') {
                                    if (!empty($bch->serial)) {
                                        foreach ($bch->serial as $srl) {
                                            if (!empty($srl->serial_no)) {
                                                $serialNumberId = Serial::create([
                                                    'v_id' => Auth::user()->vendor_id,
                                                    'serial_no' => $srl->serial_no
                                                ])->id;

                                                GrnSerial::create([
                                                    'grnlist_id' => $grnList->id,
                                                    'serial_id' => $serialNumberId
                                                ]);
//                                                StockIn::create([
//                                                    'variant_sku' => $item->variant_sku,
//                                                    'item_id' => $item->item_id,
//                                                    'store_id' => Auth::user()->store_id,
//                                                    'stock_point_id' => $stock_point_id,
//                                                    'qty' => 1,
//                                                    'ref_stock_point_id' => $ref_stock_point_id,
//                                                    'batch_id' => $batch_id,
//                                                    'serial_id' => $serialNumberId,
//                                                    'v_id' => Auth::user()->vendor_id
//                                                ]);
//
//                                                StockLogs::create([
//                                                    'variant_sku' => $item->variant_sku,
//                                                    'item_id' => $item->item_id,
//                                                    'store_id' => Auth::user()->store_id,
//                                                    'stock_type' => 'IN',
//                                                    'stock_point_id' => $stock_point_id,
//                                                    'qty' => 1,
//                                                    'ref_stock_point_id' => $ref_stock_point_id,
//                                                    'batch_id' => $batch_id,
//                                                    'serial_id' => $serialNumberId,
//                                                    'v_id' => Auth::user()->vendor_id
//                                                ]);
                                            }
                                        }
                                        $grnList->is_serial = 1;
                                        $grnList->save();
                                    }
                                }
//                                else {
//                                    StockIn::create([
//                                        'variant_sku' => $item->variant_sku,
//                                        'item_id' => $item->item_id,
//                                        'store_id' => Auth::user()->store_id,
//                                        'stock_point_id' => $stock_point_id,
//                                        'qty' => $bch->move_qty,
//                                        'ref_stock_point_id' => $ref_stock_point_id,
//                                        'batch_id' => $batch_id,
//                                        'v_id' => Auth::user()->vendor_id
//                                    ]);
//
//                                    StockLogs::create([
//                                        'variant_sku' => $item->variant_sku,
//                                        'item_id' => $item->item_id,
//                                        'store_id' => Auth::user()->store_id,
//                                        'stock_type' => 'IN',
//                                        'stock_point_id' => $stock_point_id,
//                                        'qty' => $bch->move_qty,
//                                        'ref_stock_point_id' => $ref_stock_point_id,
//                                        'batch_id' => $batch_id,
//                                        'v_id' => Auth::user()->vendor_id
//                                    ]);
//                                }
                            }
                        } else {
                            return response()->json([
                                'status' => 'error',
                                'message' => 'The given data was altered.',
                                'errors' => [
                                    'item_id' => [
                                        'Item Not found'
                                    ]
                                ]
                            ], 422);
                        }
                    }

                    $grn->qty = $totalQuantity;
                    $grn->subtotal = $totalMrp;
                    $grn->total = $totalMrp;
                    $grn->save();

                    break;
                case 'grn':
                    $v_id     = isset(Auth::user()->vendor_id)?Auth::user()->vendor_id:Auth::user()->v_id;
                    $store_id = isset($request->store_id)?$request->store_id:Auth::user()->store_id;
                    $transaction_type = 'GRN';
                    if (isset($request->from_point)) {
                        $grn_id = json_decode($request->from_point)->id;
                    } else {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'The given data was invalid.',
                            'errors' => [
                                'grn_id' => [
                                    'The grn field is required.'
                                ]
                            ]
                        ], 422);
                    }

                    $grn_list = collect(GrnList::select('id', 'grn_id', 'item_no', 'qty', 'is_batch', 'is_serial')
                        ->with(['serialNumbers', 'batches'])
                        ->where('grn_id', $grn_id)
                        ->where('v_id', $v_id)
                        ->get())->groupBy('item_no');

                    $ref_stock_point_id = StockPoints::where('v_id', $v_id)
                        ->where('name', 'grn')
                        ->whereNull('store_id')
                        ->first();
                    if (!$ref_stock_point_id) {
                        $ref_stock_point_id = StockPoints::create([
                            'v_id' => $v_id,
                            'name' => 'grn',
                            'code' => 'GRN'
                        ]);
                    }
                    $ref_stock_point_id = $ref_stock_point_id->id;

                    // Validation
//                foreach ($items as $item) {
//                    if(!isset($grn_list[$item->variant_barcode]) || $grn_list[$item->variant_barcode][0]->qty != $item->move_qty) {
//                        return response()->json([
//                            'status' => 'error',
//                            'message' => 'GRN Data Modified/Altered'
//                        ], 409);
//                    }
//                }
                    $items = collect($items)->groupBy('variant_barcode', $preserveKeys = false);
                    foreach ($items as $barcode => $item) { // For each item sku
                        foreach ($item as $batches) { // Always have only one element
//                            print(json_encode($item)); die;
                            foreach ($batches->batch as $batch) { // For each of its batches
                                if(isset($grn_list[$barcode])){
                                }else{
                                    // dd($item);
                                    $barcode = $item[0]->variant_sku;
                                }
                                foreach ($grn_list[$barcode] as $grn_list_item) { // Matching it in the grn item list to retrieve grn_list_id
                                    if ($batch->batch_id == $grn_list_item->batches->batch_id) {
                                        $grn_list_id = $grn_list_item->batches->grnlist_id;
                                        if ($grn_list_item->is_serial == 0) {
                                            $this->updateStockCurrentStatusOnImport($batches->variant_sku, $batches->item_id, $batch->move_qty,$store_id);
                                            GrnBatch::where('grnlist_id', $grn_list_id)
                                                ->where('batch_id', $grn_list_item->batches->batch_id)
                                                ->update([
                                                    'move_qty' => DB::raw('move_qty + ' . $batch->move_qty)
                                                ]);
                                            StockIn::create([
                                                'variant_sku' => $batches->variant_sku,
                                                'item_id' => $batches->item_id,
                                                'store_id' => $store_id,
                                                'stock_point_id' => $stock_point_id,
                                                'qty' => $batch->move_qty,
                                                'ref_stock_point_id' => $ref_stock_point_id,
                                                'grn_id' => $grn_id,
                                                'batch_id' => $batch->batch_id,
                                                'v_id' => $v_id
                                            ]);
                                            StockLogs::create([
                                                'variant_sku' => $batches->variant_sku,
                                                'item_id' => $batches->item_id,
                                                'store_id' => $store_id,
                                                'stock_type' => 'IN',
                                                'stock_point_id' => $stock_point_id,
                                                'qty' => $batch->move_qty,
                                                'ref_stock_point_id' => $ref_stock_point_id,
                                                'grn_id' => $grn_id,
                                                'batch_id' => $batch->batch_id,
                                                'v_id' => $v_id,
                                                'transaction_type' => $transaction_type
                                            ]);
                                        } else {
                                            if (!empty($batch->serial)) {
                                                foreach ($batch->serial as $serialNumber) {  // For each of user selected serial numbers
                                                    if (!empty($serialNumber->serial_no) && $serialNumber->selected) {
                                                        $serialNumberId = Serial::where('v_id', $v_id)->where('serial_no', $serialNumber->serial_no)->first()->id;
//                                                        print(json_encode($batches->variant_sku)); die;
                                                        $this->updateStockCurrentStatusOnImport($batches->variant_sku, $batches->item_id, 1,$store_id);
                                                        GrnSerial::where('grnlist_id', $grn_list_id)->where('serial_id', $serialNumberId)->update([
                                                            'is_moved' => 1
                                                        ]);
                                                        GrnBatch::where('grnlist_id', $grn_list_id)->where('batch_id', $grn_list_item->batches->batch_id)->update([
                                                            'move_qty' => DB::raw('move_qty + 1')
                                                        ]);
                                                        StockIn::create([
                                                            'variant_sku' => $batches->variant_sku,
                                                            'item_id' => $batches->item_id,
                                                            'store_id' => $store_id,
                                                            'stock_point_id' => $stock_point_id,
                                                            'qty' => 1,
                                                            'ref_stock_point_id' => $ref_stock_point_id,
                                                            'grn_id' => $grn_id,
                                                            'batch_id' => $batch->batch_id,
                                                            'serial_id' => $serialNumberId,
                                                            'v_id' => $v_id
                                                        ]);
                                                        StockLogs::create([
                                                            'variant_sku'   => $batches->variant_sku,
                                                            'item_id'       => $batches->item_id,
                                                            'store_id'      => $store_id,
                                                            'stock_type'    => 'IN',
                                                            'stock_point_id'=> $stock_point_id,
                                                            'qty'           => 1,
                                                            'ref_stock_point_id' => $ref_stock_point_id,
                                                            'grn_id'        => $grn_id,
                                                            'batch_id'      => $batch->batch_id,
                                                            'serial_id'     => $serialNumberId,
                                                            'transaction_type'=>$transaction_type,
                                                            'v_id' => $v_id
                                                        ]);
                                                    }
                                                }
                                            }
                                        }
                                        break;
                                    }
                                }
                            }
                        }
                    }

//                foreach ($grn_list as $barcode => $batches) {
//                    foreach ($batches as $batch) {
//                        $item_info = VendorSkuDetails::select('item_id', 'sku')
//                            ->where('barcode', $batch->item_no)
//                            ->first();
//
//                        if($batch->is_serial == 0) {
//                            StockIn::create([
//                                'variant_sku' => $item_info->sku,
//                                'item_id' => $item_info->item_id,
//                                'store_id' => Auth::user()->store_id,
//                                'stock_point_id' => $stock_point_id,
//                                'qty' => $batch->batches->qty,
//                                'ref_stock_point_id' => $ref_stock_point_id,
//                                'grn_id' => $grn_id,
//                                'batch_id' => $batch->batches->batch_id,
//                                'v_id' => Auth::user()->vendor_id
//                            ]);
//
//                            StockLogs::create([
//                                'variant_sku' => $item_info->sku,
//                                'item_id' => $item_info->item_id,
//                                'store_id' => Auth::user()->store_id,
//                                'stock_type' => 'IN',
//                                'stock_point_id' => $stock_point_id,
//                                'qty' => $batch->batches->qty,
//                                'ref_stock_point_id' => $ref_stock_point_id,
//                                'grn_id' => $grn_id,
//                                'batch_id' => $batch->batches->batch_id,
//                                'v_id' => Auth::user()->vendor_id
//                            ]);
//                        } else {
//                            foreach ($batch->serialNumbers as $serialNumber) {
//                                StockIn::create([
//                                    'variant_sku' => $item_info->sku,
//                                    'item_id' => $item_info->item_id,
//                                    'store_id' => Auth::user()->store_id,
//                                    'stock_point_id' => $stock_point_id,
//                                    'qty' => 1,
//                                    'ref_stock_point_id' => $ref_stock_point_id,
//                                    'grn_id' => $grn_id,
//                                    'batch_id' => $batch->batches->batch_id,
//                                    'serial_id' => $serialNumber->serial_id,
//                                    'v_id' => Auth::user()->vendor_id
//                                ]);
//
//                                StockLogs::create([
//                                    'variant_sku' => $item_info->sku,
//                                    'item_id' => $item_info->item_id,
//                                    'store_id' => Auth::user()->store_id,
//                                    'stock_type' => 'IN',
//                                    'stock_point_id' => $stock_point_id,
//                                    'qty' => 1,
//                                    'ref_stock_point_id' => $ref_stock_point_id,
//                                    'grn_id' => $grn_id,
//                                    'batch_id' => $batch->batches->batch_id,
//                                    'serial_id' => $serialNumber->serial_id,
//                                    'v_id' => Auth::user()->vendor_id
//                                ]);
//                            }
//                        }
//                    }
//                }

                    // Check if all grn items moved
                    $grn_list_ids = GrnList::select('id')
                        ->where('grn_id', $grn_id)
                        ->where('v_id', Auth::user()->vendor_id)
                        ->get();
                    $allGrnMove = true;
                    foreach ($grn_list_ids as $grn_list_id) {
//                    print(json_encode(GrnBatch::where('grnlist_id', $grn_list_id->id)->where('qty', '>', DB::raw('move_qty'))->first())); die;
                        if (GrnBatch::where('grnlist_id', $grn_list_id->id)->where('qty', '>', DB::raw('move_qty'))->first()) {
                            $allGrnMove = false;
                            break;
                        }

                        if (GrnSerial::where('grnlist_id', $grn_list_id->id)->where('is_moved', '0')->first()) {
                            $allGrnMove = false;
                            break;
                        }
                    }
                    if ($allGrnMove) {
                        Grn::find($grn_id)->update([
                            'store_id' => Auth::user()->store_id,
                            'is_imported' => 1
                        ]);
                    }
                    break;
                case 'stock':
                    $transaction_type = 'SPT';  //Stock point to Stock point move
                    if (isset($request->from_point)) {
                        $ref_stock_point_id = StockPoints::find(json_decode($request->from_point)->id)->id;
                        if (!$ref_stock_point_id) {
                            return response()->json([
                                'status' => 'error',
                                'message' => 'Invalid Source Stock Point'
                            ], 409);
                        } else if ($ref_stock_point_id == $stock_point_id) {
                            return response()->json([
                                'status' => 'error',
                                'message' => 'Cannot move in same Stock Point'
                            ], 409);
                        }
                    } else {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'The given data was invalid.',
                            'errors' => [
                                'from_point' => [
                                    'The from point field is required.'
                                ]
                            ]
                        ], 422);
                    }

                    // Validation
                    foreach ($items as $item) {
                        foreach ($item->batch as $bch) {
                            if (StockLogs::select(DB::raw('sum(qty) as available_qty'))
                                    ->where('variant_sku', $item->variant_sku)
                                    ->where('stock_point_id', $ref_stock_point_id)
                                    ->where('batch_id', $bch->batch_id)
//                                    ->where('batch_id', Batch::select('id')->where('batch_no', $bch->batch_no)->first()->id)
                                    ->first()->available_qty < $bch->move_qty) {
                                return response()->json([
                                    'status' => 'error',
                                    'message' => 'Move quantity cannot be more than available quantity'
                                ], 409);
                            }
                        }
                    }

                    foreach ($items as $item) {
                        $item_info = Item::select('id', 'has_serial', 'has_batch')->where('id', $item->item_id)->first();
                        if ($item_info) {
                            $varient_price_ids = VendorItemPriceMapping::select('item_price_id')
                                ->with('priceDetail:id,mrp')
                                ->where('v_id', Auth::user()->vendor_id)
                                ->where('item_id', $item->item_id)
                                ->where('variant_combi', $item->variant_combi)
                                ->get();
                            foreach ($item->batch as $bch) {
                                if (!$bch->mrp) {
                                    return response()->json([
                                        'status' => 'error',
                                        'message' => 'Item MRP cannot be zero.',
                                        'errors' => [
                                            'mrp' => [
                                                'MRP cannot be 0.'
                                            ]
                                        ]
                                    ], 422);
                                }
                                $priceId = null;
                                $batch_id = null;
                                foreach ($varient_price_ids as $varient_price_id) {
                                    if ($varient_price_id->priceDetail->mrp == $bch->mrp) {
                                        $priceId = $varient_price_id->item_price_id;
                                        break;
                                    }
                                }
                                if (!$priceId) {
                                    $priceId = ItemPrices::select('id')
                                        ->where('mrp', $bch->mrp)
                                        ->where('rsp', $bch->mrp)
                                        ->where('special_price', $bch->mrp)
                                        ->first();
                                    if (!$priceId) {
                                        return response()->json([
                                            'status' => 'error',
                                            'message' => 'MRP doesn\'t exist in item prices'
                                        ], 409);
                                    } else {
                                        $priceId = $priceId->id;
                                    }
                                }

                                if ($item_info->has_batch == '1') {
                                    if (!$bch->batch_no || !$bch->mfg_date || !($bch->exp_date || $bch->valid_months)) {
                                        return response()->json([
                                            'status' => 'error',
                                            'message' => 'Fill the product batch information.',
                                            'errors' => []
                                        ], 422);
                                    }
                                    $batch_id = Batch::select('id')
                                        ->where('v_id', Auth::user()->vendor_id)
                                        ->where('batch_no', $bch->batch_no)
                                        ->first();
                                    if (!$batch_id) {
                                        return response()->json([
                                            'status' => 'error',
                                            'message' => 'Item batch doesn\'t exist in item anymore'
                                        ], 409);
                                    } else {
                                        $batch_id = $batch_id->id;
                                    }
                                } else {
                                    $batch_id = Batch::select('id')
                                        ->where('v_id', Auth::user()->vendor_id)
                                        ->whereNull('batch_no')
                                        ->where('item_price_id', $priceId)
                                        ->first()->id;
                                }

                                if ($item_info->has_serial == '1') {
                                    if (!empty($bch->serial)) {
                                        foreach ($bch->serial as $srl) {
//                                        print(json_encode($srl));
//                                        die;
                                            if (!empty($srl->serial_no) && $srl->selected) {
                                                $serialNumberId = Serial::where('v_id', Auth::user()->vendor_id)->where('serial_no', $srl->serial_no)->first()->id;
//                                            print($serialNumberId);
                                                StockIn::create([
                                                    'variant_sku' => $item->variant_sku,
                                                    'item_id' => $item->item_id,
                                                    'store_id' => Auth::user()->store_id,
                                                    'stock_point_id' => $stock_point_id,
                                                    'qty' => 1,
                                                    'ref_stock_point_id' => $ref_stock_point_id,
                                                    'batch_id' => $batch_id,
                                                    'serial_id' => $serialNumberId,
                                                    'v_id' => Auth::user()->vendor_id
                                                ]);

                                                StockOut::create([
                                                    'variant_sku' => $item->variant_sku,
                                                    'item_id' => $item->item_id,
                                                    'store_id' => Auth::user()->store_id,
                                                    'stock_point_id' => $stock_point_id,
                                                    'qty' => 1,
                                                    'ref_stock_point_id' => $ref_stock_point_id,
                                                    'batch_id' => $batch_id,
                                                    'serial_id' => $serialNumberId,
                                                    'v_id' => Auth::user()->vendor_id
                                                ]);

                                                StockLogs::create([
                                                    'variant_sku' => $item->variant_sku,
                                                    'item_id' => $item->item_id,
                                                    'store_id' => Auth::user()->store_id,
                                                    'stock_type' => 'IN',
                                                    'stock_point_id' => $stock_point_id,
                                                    'qty' => 1,
                                                    'ref_stock_point_id' => $ref_stock_point_id,
                                                    'batch_id' => $batch_id,
                                                    'serial_id' => $serialNumberId,
                                                    'v_id' => Auth::user()->vendor_id,
                                                    'transaction_type' => $transaction_type
                                                ]);

                                                StockLogs::create([
                                                    'variant_sku' => $item->variant_sku,
                                                    'item_id' => $item->item_id,
                                                    'store_id' => Auth::user()->store_id,
                                                    'stock_type' => 'OUT',
                                                    'stock_point_id' => $ref_stock_point_id,
                                                    'qty' => -1,
                                                    'ref_stock_point_id' => $stock_point_id,
                                                    'batch_id' => $batch_id,
                                                    'serial_id' => $serialNumberId,
                                                    'v_id' => Auth::user()->vendor_id,
                                                    'transaction_type' => $transaction_type
                                                ]);
                                            }
                                        }
                                    }
                                } else {
                                    StockIn::create([
                                        'variant_sku' => $item->variant_sku,
                                        'item_id' => $item->item_id,
                                        'store_id' => Auth::user()->store_id,
                                        'stock_point_id' => $stock_point_id,
                                        'qty' => $bch->move_qty,
                                        'ref_stock_point_id' => $ref_stock_point_id,
                                        'batch_id' => $batch_id,
                                        'v_id' => Auth::user()->vendor_id
                                    ]);

                                    StockOut::create([
                                        'variant_sku' => $item->variant_sku,
                                        'item_id' => $item->item_id,
                                        'store_id' => Auth::user()->store_id,
                                        'stock_point_id' => $stock_point_id,
                                        'qty' => $bch->move_qty,
                                        'ref_stock_point_id' => $ref_stock_point_id,
                                        'batch_id' => $batch_id,
                                        'v_id' => Auth::user()->vendor_id
                                    ]);

                                    StockLogs::create([
                                        'variant_sku' => $item->variant_sku,
                                        'item_id' => $item->item_id,
                                        'store_id' => Auth::user()->store_id,
                                        'stock_type' => 'IN',
                                        'stock_point_id' => $stock_point_id,
                                        'qty' => $bch->move_qty,
                                        'ref_stock_point_id' => $ref_stock_point_id,
                                        'batch_id' => $batch_id,
                                        'v_id' => Auth::user()->vendor_id,
                                        'transaction_type' => $transaction_type
                                    ]);

                                    StockLogs::create([
                                        'variant_sku' => $item->variant_sku,
                                        'item_id' => $item->item_id,
                                        'store_id' => Auth::user()->store_id,
                                        'stock_type' => 'OUT',
                                        'stock_point_id' => $ref_stock_point_id,
                                        'qty' => (-1) * $bch->move_qty,
                                        'ref_stock_point_id' => $stock_point_id,
                                        'batch_id' => $batch_id,
                                        'v_id' => Auth::user()->vendor_id,
                                        'transaction_type' => $transaction_type
                                    ]);
                                }
                            }
                        } else {
                            return response()->json([
                                'status' => 'error',
                                'message' => 'The given data was altered.',
                                'errors' => [
                                    'item_id' => [
                                        'Item Not found'
                                    ]
                                ]
                            ], 422);
                        }
                    }
                    break;
                default:
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid Inventory Source'
                    ], 409);
            }
            DB::commit();
        } catch(Exception $e){
            DB::rollback();
//            exit;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully Moved Inventory'
        ], 200);
   }



   private function updateStockCurrentStatusOnImport($variant_sku, $item_id, $quantity,$store_id='') {
        
        $vendor_id = isset(Auth::user()->vendor_id)?Auth::user()->vendor_id:Auth::user()->v_id;
        $store_id = isset($store_id)?$store_id:Auth::user()->store_id;

        $todayStatus = StockCurrentStatus::select('id', 'int_qty')
            ->where('item_id', $item_id)
            ->where('variant_sku', $variant_sku)
            ->where('store_id', $store_id)
            ->where('v_id', $vendor_id)
            ->where('for_date', Carbon::today()->toDateString())
            ->first();

        if($todayStatus) {
            $todayStatus->int_qty += $quantity;
            $todayStatus->save();
//            print($todayStatus); die;
        } else {
            $stockPastStatus = StockCurrentStatus::select('opening_qty', 'out_qty', 'int_qty')
                ->where('item_id', $item_id)
                ->where('variant_sku', $variant_sku)
                ->where('store_id', $store_id)
                ->where('v_id', $vendor_id)
                ->orderBy('for_date', 'DESC')
                ->first();

            if($stockPastStatus) {
                $openingStock = $stockPastStatus->opening_qty + $stockPastStatus->int_qty - $stockPastStatus->out_qty;
            } else {
                $openingStock = 0;
            }

            $skuD  =  VendorSkuDetails::select('sku_code')
            ->where('v_id', $vendor_id)
            ->where('item_id', $item_id)
            ->where('sku', $variant_sku)
            ->first();

            StockCurrentStatus::create([
                'item_id' => $item_id,
                'variant_sku' => $variant_sku,
                'sku_code' => $skuD->sku_code,
                'store_id' => $store_id,
                'v_id' => $vendor_id,
                'for_date' => Carbon::today()->toDateString(),
                'opening_qty' => $openingStock,
                'out_qty' => 0,
                'int_qty' => $quantity
            ]);
        }
    }

    public function stockEntry(Request $request)
    {
        $v_id       = $request->v_id;
        $store_id   = $request->store_id;
        $trans_from = $request->trans_from;
        $vu_id      = $request->vu_id;
        $stockData  = $request->stockData;
        $transaction_type = $stockData['transaction_type'];
        $store = Store::where([ 'v_id' => $v_id, 'store_id' => $store_id ])->first();
 
        if(isValueChecker($stockData['qty'])) {
            $stockInData        = $stockData;
            // if($stockData['type'] == 'short') {
                // $stockInData['qty']   = $stockInData['qty'];
            // } else {
                $stockInData['qty']   = $stockInData['request_qty'];
            // }
            $stockInData['stock_type'] = 'IN';
            unset($stockInData['lost_qty']);
            unset($stockInData['damage_qty']);
            unset($stockInData['short_qty']);
            unset($stockInData['excess_qty']);
            // unset($stockInData['type']);
            $stockRequest = new \Illuminate\Http\Request();
            $stockRequest->merge([
                'v_id'          => $v_id,
                'stockData'     => $stockInData,
                'store_id'      => $store_id,
                'trans_from'    => $trans_from,
                'vu_id'         => $vu_id
            ]);
            $this->stockIn($stockRequest);
        }

        if(isValueChecker($stockData['grn_id'])) {


            // $grn = GrnList::where('grn_id', $stockData['grn_id'])->where('')->first();

            // Ajd Qty Checker
            if($stockData['type'] != 'equal') {


                $lostStockData = $stockData;
                
                if($stockData['type'] == 'short') {
                    $lostStockData['qty'] = $lostStockData['short_qty'];
                    $lostStockData['stock_type'] = 'OUT';
                    $remarks = adjustmentRemark($lostStockData['short_qty'], $stockData['type']);
                } else {
                    $lostStockData['qty'] = $lostStockData['excess_qty'];
                    $lostStockData['stock_type'] = 'IN';
                    $remarks = adjustmentRemark($lostStockData['excess_qty'], $stockData['type']);
                }
                $lostStockData['stock_point_id'] = $lostStockData['stock_point_id'];
                // if((int)$lostStockData < 0) {
                
                $lostStockData['via_from']   = 'GRN';
                $lostStockData['remarks']    = $remarks;
                $lostStockData['transaction_type'] = 'ADJ';

                // } else {
                    // $lostStockData['stock_type'] = 'OUT';
                // }
                unset($lostStockData['lost_qty']);
                unset($lostStockData['damage_qty']);
                unset($lostStockData['type']);
                unset($lostStockData['short_qty']);
                unset($lostStockData['excess_qty']);


                

                $stockAdjRequest = new \Illuminate\Http\Request();
                $stockAdjRequest->merge([
                    'v_id'          => $v_id,
                    'stockData'     => $lostStockData,
                    'store_id'      => $store_id,
                    'trans_from'    => $trans_from,
                    'vu_id'         => $vu_id
                ]);
                $this->stockAdj($stockAdjRequest);
            }

            // Damage Qty Checker
            if($stockData['damage_qty'] != '0.0') {

                $damageStockData = $stockData;
                $damageStockData['qty'] = $damageStockData['damage_qty'];
                $damageStockData['rec_qty'] = $stockData['qty'];
                $damageStockData['request_qty'] = $damageStockData['request_qty'];
                $damageStockData['stock_point_id'] = $store->default_stock_point['DAMAGE'];
                $damageStockData['stock_type'] = $damageStockData['type'];
                $grnStockPoint = StockPoints::where('v_id', $v_id)->where('store_id', $store_id)->where('is_default', '1')->first();
                $damageStockData['ref_stock_point_id'] = $grnStockPoint->id;
                $damageStockData['transaction_type'] = $transaction_type;
                $damageStockData['stock_type'] = 'IN';
                $damageStockData['case_type'] = 'damage';
                unset($damageStockData['lost_qty']);
                unset($damageStockData['damage_qty']);
                unset($damageStockData['type']);
                unset($damageStockData['short_qty']);
                unset($damageStockData['excess_qty']);

                // Damage Stock Coutable Settings

                $stockSetting = VendorSetting::where('v_id', $v_id)->where('name', 'stock')->first();
                $checkSetting = json_decode($stockSetting['settings']);
                if(property_exists($checkSetting, 'damage_stock')) {
                    if($checkSetting->damage_stock->stock_countable->status == 1) {
                        $damageStockData['case_type'] = 'default';
                    }
                }

                $stockRequest = new \Illuminate\Http\Request();
                $stockRequest->merge([
                    'v_id'          => $v_id,
                    'stockData'     => $damageStockData,
                    'store_id'      => $store_id,
                    'trans_from'    => $trans_from,
                    'vu_id'         => $vu_id
                ]);

                $this->damageStockIn($stockRequest);
            }
        }
    }

    public function stockIn(Request $request)
    {
        //dd($request->all());
        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $trans_from = $request->trans_from;
        $vu_id = $request->vu_id;
        $stockData = $request->stockData;
        $type = 'default';
        if(array_key_exists('case_type', $stockData)) {
            $type = $stockData['case_type'];
        }
       // dd($v_id);
        if($stockData['status'] == 'UNPOST') {
            $stockData['transaction_scr_id'] = StockIn::create($stockData)->id;
        }

        if($stockData['status'] == 'POST') {
            if(array_key_exists('posted_id', $stockData)) {
                $stockData['transaction_scr_id'] = $stockData['posted_id'];
                $this->stockLog($stockData, 'IN', $type);
            } else {
                $stockData['transaction_scr_id'] = StockIn::create($stockData)->id;
                $this->stockLog($stockData, 'IN', $type);
            }
        }
        // if($request->stockData[''])
        //$this->updateCurrentStock($stockData);

    }

    public function damageStockIn(Request $request)
    {
        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $trans_from = $request->trans_from;
        $vu_id = $request->vu_id;
        $stockData = $request->stockData;

        //print_r($stockData);die;

        //Add all request qty 
        if($stockData['rec_qty'] == 0){
            $stockDataRequestQty = $stockData;
            $stockDataRequestQty['qty'] = $stockData['request_qty'];
            $stockDataRequestQty['stock_point_id'] = $stockData['ref_stock_point_id'];
            $stockDataRequestQty['ref_stock_point_id'] = '0';

            $stockData['stock_type'] = 'IN';
            $stockInRequest = new \Illuminate\Http\Request();
            $stockInRequest->merge([
                'v_id'          => $v_id,
                'stockData'     => $stockDataRequestQty,
                'store_id'      => $store_id,
                'trans_from'    => $trans_from,
                'vu_id'         => $vu_id
                ]);
            $this->stockIn($stockInRequest);
        }

        // Stock OUT

        $stockOut = $request->stockData;
        $stockOut['stock_point_id'] = $stockData['ref_stock_point_id'];
        $stockOut['ref_stock_point_id'] = $stockData['stock_point_id'];
        // $stockOut['transaction_type'] = 'SPT';
        $stockOut['stock_type'] = 'OUT';
        $stockOutRequest = new \Illuminate\Http\Request();
        $stockOutRequest->merge([
            'v_id'          => $v_id,
            'stockData'     => $stockOut,
            'store_id'      => $store_id,
            'trans_from'    => $trans_from,
            'vu_id'         => $vu_id
        ]);
        $this->stockOut($stockOutRequest);

        // Stock IN

        // $stockData['transaction_type'] = 'SPT';
        $stockData['stock_type'] = 'IN';
        $stockInRequest = new \Illuminate\Http\Request();
        $stockInRequest->merge([
            'v_id'          => $v_id,
            'stockData'     => $stockData,
            'store_id'      => $store_id,
            'trans_from'    => $trans_from,
            'vu_id'         => $vu_id
        ]);
        $this->stockIn($stockInRequest);



        // $stockData['transaction_scr_id'] = StockIn::create($stockData)->id;

        // $this->stockLog($stockData, 'IN', 'damage');
        //$this->updateCurrentStock($stockData);

    }

    public function stockOut(Request $request)
    {

        $v_id       = $request->v_id;
        JobdynamicConnection($v_id);
        $store_id   = $request->store_id;
        $trans_from = $request->trans_from;
        $vu_id      = $request->vu_id;
        $stockData  = $request->stockData;
        $stockData['qty'] = (string)abs($stockData['qty']);
        $type = 'default';
        if(array_key_exists('case_type', $stockData)) {
            $type = $stockData['case_type'];
        }
        if($stockData['status'] == 'UNPOST') {
            $stockData['transaction_scr_id'] = StockOut::create($stockData)->id;
        }
        if($stockData['status'] == 'POST') {
            if(array_key_exists('posted_id', $stockData)) {
                $stockData['transaction_scr_id'] = $stockData['posted_id'];
                $this->stockLog($stockData, 'OUT');
            } else {
                $stockData['transaction_scr_id'] = StockOut::create($stockData)->id;
                $this->stockLog($stockData, 'OUT');
            }
        }
        // $this->updateCurrentStock($stockData);
    }//End of stockOut

    public function stockAdj(Request $request)
    {

        $v_id = $request->v_id;

        $store_id = $request->store_id;
        $trans_from = $request->trans_from;
        $vu_id = $request->vu_id;
        $stockData = $request->stockData;
        if($stockData['stock_type'] == 'OUT'){
            $stockData['qty']  = -1*$stockData['qty'];
        }
        $stockData['qty'] = (string)$stockData['qty'];
        $adjId = StockAdjustment::create($stockData);
        $stockData['transaction_scr_id']  = $adjId->id;
        // $this->stockLog($stockData, $stockData['stock_type']);
        if($stockData['stock_type'] == 'OUT') {

           
            $stockOutRequest = new \Illuminate\Http\Request();
            $stockOutRequest->merge([
                'v_id'          => $v_id,
                'stockData'     => $stockData,
                'store_id'      => $store_id,
                'trans_from'    => $trans_from,
                'vu_id'         => $vu_id
            ]);
            $this->stockOut($stockOutRequest);
        } else {

           
            $stockInRequest = new \Illuminate\Http\Request();
            $stockInRequest->merge([
                'v_id'          => $v_id,
                'stockData'     => $stockData,
                'store_id'      => $store_id,
                'trans_from'    => $trans_from,
                'vu_id'         => $vu_id
            ]);
            $this->stockIn($stockInRequest); 
        }
        // $this->stockIn($stockData);

    }

    public function stockLog($data, $type, $case = 'default')
    {
        //dd($type);
        //dd($data);
        $v_id  = $data['v_id'];
        if($type == 'OUT'){
            $data['qty'] = -1*abs($data['qty']);
        }

        $data['qty'] = (string)$data['qty'];
        $data['date'] = date('Y-m-d');
        $stock = StockLogs::create($data);
        $this->stockPointSummary($data);
        StockLogs::find($stock->id)->update([ 'stock_type' => $type ]);
        $this->updateCurrentStock($data, $case);
        if($data['transaction_type'] == 'SALE'){
          $data['stock_point_id'] = $data['ref_stock_point_id'];
          $this->stockPointSummary($data);
        } 
    }
    
    public function stockPointSummary($data){
        
        $serial_id = empty($data['serial_id'])?0:$data['serial_id'];
        $batch_id  = empty($data['batch_id'])?0:$data['batch_id'];
        $stockPointItem = StockPointSummary::where(['v_id'=>$data['v_id'],'store_id'=>$data['store_id'],'stock_point_id'=>$data['stock_point_id'],'item_id'=>$data['item_id'],'variant_sku'=>$data['variant_sku'],'batch_id'=>$batch_id,'serial_id'=>$serial_id])->first();
        if($stockPointItem){
            $stockPointItem->qty  += (float)$data['qty'];
            $stockPointItem->qty = (string)$stockPointItem->qty;
            $stockPointItem->save(); 
        }else{
            $stockPointData = array('v_id'=>$data['v_id'],'store_id'=>$data['store_id'],'stock_point_id'=>$data['stock_point_id'],'item_id'=>$data['item_id'],'variant_sku'=>$data['variant_sku'], 'sku_code' => @$data['sku_code'] , 'barcode'=>$data['barcode'],'qty'=>(string)$data['qty'],
                'batch_id'=>$batch_id,'batch_code'=>@$data['batch_code'],'serial_id'=>@$serial_id,'serial_code'=>@$data['serial_code']);
            StockPointSummary::create($stockPointData);
        }

    }

    public function updateCurrentStock($data, $case = 'default') 
    {

        $vendor_id  = $data['v_id'];
        $store_id   = $data['store_id'];
        $variant_sku= $data['variant_sku'];
        $barcode  = $data['barcode'];
        $item_id   = $data['item_id'];
        $today     = date('Y-m-d');
        $quantity  = (string)abs($data['qty']);
        $int_qty   = 0;
        $out_qty   = 0;
        $grn_qty   = 0;
        $adj_in_qty   = 0;
        $adj_out_qty   = 0;
        $sale_qty  = 0;
        $return_qty = 0;
        $damage_qty = 0;
        $transfer_out_qty = 0;

        //JobdynamicConnection($vendor_id);
        $transaction_type = $data['transaction_type'] == 'sales'?'SALE':$data['transaction_type'];
        $stock_type  = isset($data['stock_type'])?$data['stock_type']:$data['type'];
        if($transaction_type == 'GRN'){
            if($case == 'damage') {
                $damage_qty =  $quantity ;
            } 
            $grn_qty =  $quantity ;
            
        }
        if($transaction_type == 'ADJ'){
         if($stock_type == 'IN') {
            $adj_in_qty = $quantity;
         } else {
            $adj_out_qty = $quantity;
         }
        }
        if($transaction_type == 'SALE' || $transaction_type == 'sales'){
         $sale_qty =  $quantity ;
        }
        if($transaction_type == 'RETURN'){
         $return_qty =  $quantity;
        }
        

        

        $todayStatus = StockCurrentStatus::select('id', 'out_qty','int_qty','grn_qty','adj_in_qty','adj_out_qty','sale_qty','return_qty', 'damage_qty','transfer_out_qty')
            ->where('item_id', $item_id)
            ->where('variant_sku', $variant_sku)
            ->where('store_id', $store_id)
            ->where('v_id', $vendor_id)
            ->where('for_date', $today)    //Carbon::today()->toDateString()
            ->first();
            // if($stock_type == 'OUT'){

            //     echo $todayStatus->out_qty.'---';
            // echo $todayStatus->out_qty +$quantity; 
            //  die;     
            // }

        if($todayStatus) {

            if($transaction_type != 'SST') {
                if($case != 'damage') {
                    if($stock_type == 'OUT'){
                        $todayStatus->out_qty = $todayStatus->out_qty +$quantity;
                        $todayStatus->out_qty = (string)$todayStatus->out_qty;              
                    }
                    if($stock_type == 'IN'){
                        $todayStatus->int_qty =$todayStatus->int_qty +$quantity;
                        $todayStatus->int_qty = (string)$todayStatus->int_qty;
                    }
                }
            }

            if($transaction_type == 'SST') {
              
                    if($stock_type == 'OUT'){
                        $todayStatus->transfer_out_qty = $todayStatus->transfer_out_qty+$quantity;
                        $todayStatus->transfer_out_qty= (string)$todayStatus->transfer_out_qty;              
                    }       
            }

            if($transaction_type == 'GRN'){
                // if($case != 'damage') {
                    // $todayStatus->damage_qty = $todayStatus->damage_qty+$quantity ;
                // } else {
                    $todayStatus->grn_qty = $todayStatus->grn_qty+$quantity ;
                    $todayStatus->grn_qty = (string)$todayStatus->grn_qty;
                // }
            }
            if($transaction_type == 'ADJ'){
                if($stock_type == 'OUT'){
                    // if($todayStatus->adj_qty < 0) {
                        $todayStatus->adj_out_qty = $todayStatus->adj_out_qty + (-1 * abs($quantity));
                        $todayStatus->adj_out_qty = (string)$todayStatus->adj_out_qty;
                    // }
                } else {
                    $todayStatus->adj_in_qty = $todayStatus->adj_in_qty + $quantity;
                    $todayStatus->adj_in_qty = (string)$todayStatus->adj_in_qty;
                }
                // if(array_key_exists('via_from', $data)) {
                //     $todayStatus->grn_qty = $todayStatus->grn_qty+$quantity ;
                // }
            }
            if($transaction_type == 'sales' || $transaction_type == 'SALE'){
                
                $todayStatus->sale_qty = $todayStatus->sale_qty+$quantity;
                $todayStatus->sale_qty = (string) $todayStatus->sale_qty;
            }
            if($transaction_type == 'RETURN'){
                $todayStatus->return_qty = $todayStatus->return_qty+$quantity;
                $todayStatus->return_qty = (string) $todayStatus->return_qty;
            }
            $todayStatus->barcode = $barcode;
            $todayStatus->save();
        
        } else {
            if($transaction_type != 'SST') {
                if($case != 'damage') {
                    if($stock_type == 'OUT'){
                     $out_qty = (string)$quantity;
                    } 
                    if($stock_type == 'IN' ){
                     $int_qty  = (string)$quantity;
                    }
                }
            }
            if($transaction_type == 'SST') {
             if($stock_type == 'OUT'){
                $transfer_out_qty = (string)$quantity;            
             }
            }
            
            $stockPastStatus = StockCurrentStatus::select('opening_qty', 'out_qty', 'int_qty')
                ->where('item_id', $item_id)
                ->where('variant_sku', $variant_sku)
                ->where('store_id', $store_id)
                ->where('v_id', $vendor_id)
                ->orderBy('for_date', 'DESC')
                ->first();

            if($stockPastStatus) {
                $openingStock = $stockPastStatus->opening_qty + $stockPastStatus->int_qty - $stockPastStatus->out_qty;
            } else {
                $openingStock = 0;
            }

            $skuD  =  VendorSkuDetails::select('sku_code')
            ->where('v_id', $vendor_id)
            ->where('item_id', $item_id)
            ->where('sku', $variant_sku)
            ->first();

            if($skuD){
                StockCurrentStatus::create([
                'item_id' => $item_id,
                'variant_sku' => $variant_sku,
                'barcode'  =>  $barcode,
                'sku_code'  =>  $skuD->sku_code,
                'store_id' => $store_id,
                'v_id' => $vendor_id,
                'for_date' => $today,
                'opening_qty' => (string)$openingStock,
                'out_qty' => (string)$out_qty,
                'int_qty' => (string)$int_qty,
                'grn_qty' => (string)$grn_qty,
                'adj_in_qty' => (string)$adj_in_qty,
                'adj_out_qty' => (string)$adj_out_qty,
                'sale_qty' => (string)$sale_qty,
                'return_qty' =>(string)$return_qty,
                'transfer_out_qty' => (string)$transfer_out_qty,
                'damage_qty' => (string)$damage_qty
                ]);
            }

            
        }
    }


    public function stockTransfer(Request $request){
        $vendor_id       = $request->v_id;
        $stockOutRequest = new \Illuminate\Http\Request();
        $stockOutRequest->merge([
            'v_id'          => $vendor_id,
            'stockData'     => $request->stockData,
            'store_id'      => $request->store_id,
            'trans_from'    => $request->trans_from,
            'vu_id'         => $request->vu_id
        ]);
        //dd($request->stockData);
       $this->stockOut($stockOutRequest);
    }//End of stockTransfer

    public function stockIntransit($data){
        return StockIntransit::insertGetId($data);
    }


}//End of StockController
