<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\CustomClasses\PrintInvoice;

use App\Model\Grn\Grn;
use App\Model\Grn\GrnList;
use App\Model\Grn\Advise;
use App\Model\Grn\AdviseList;
use App\Model\Grn\Advice;
use App\Model\Grn\GrnBatch;
use App\Model\Stock\Batch;
use App\Model\Stock\VendorItemBatch;

use App\Model\Items\ItemPrices;
use App\Model\Items\VendorItemPriceMapping;
use App\Model\Items\VendorSkuDetails;
use App\Model\Items\VendorSkuDetailBarcode;

use App\Model\Stock\Serial;
use App\Model\Grn\GrnSerial;
use App\Supplier;
use App\Store;
use App\Model\Store\StoreItems;
use DB;
use Auth;
use App\Model\Stock\StockPoints;
use App\Model\Stock\StockIn;
use App\Model\Stock\StockCurrentStatus;
use App\Model\Stock\StockIntransit;
use App\Http\Controllers\CartController as MainCart;
use Event;
use App\Events\GrnCreated;
use App\Model\Item\ItemList;
use App\Http\Controllers\ItemController;

use Validator;
use App\Model\InboundApi;
use App\Organisation;
use Log;
use App\Model\Supplier\Supplier as NewSupplier;


class GrnController extends Controller
{
    public function __construct()
    {
       // $this->middleware('auth',['except' => ['printGrn']]);
        // JobdynamicConnection(127);


    }

    public function list(Request $request){

        $where    = array('grn.v_id' => $request->v_id,'grn.deleted_by'=>0);
        $grn      = Grn::join('advice', 'advice.id', '=', 'grn.advice_id')->where($where) ;
        // print_r($grn);
        
        return zwDataTable($request, $grn);

    }

    public function add(Request $request){
        if($request->ajax()) {
            $request->validate(Grn::$rules);
            $data = array('v_id'        =>  $request->v_id,
                          'store_id'        =>  $request->store_id,
                          'advice_id'   => $request->advice_id,
                          'qty'         => $request->qty,
                          'subtotal'    => $request->subtotal,
                          'discount'    => $request->discount,
                          'tax'         => $request->tax,
                          'total'       => $request->total,
                          'damage_qty'  => $request->damage_qty,
                          'lost_qty'    => $request->lost_qty,
                          'remarks'     => $request->remark,
                        );
            if(empty($request->id)){
                $where = array('v_id'=>$request->v_id,'advice_id'=>$request->advice);
                $existgrn        = Grn::where($where)->first();
                if($existgrn){
                    //return '{"message":"The given data was invalid.","errors":{"other":["The advice field is required."]}}';

                    return response()->json([  'message' => 'The given data was invalid.','errors' => array('other'=>'The Grn was already generated for this advice')], 422);
                }

                $data['grn_no']   =   grn_no_generate(Auth::user()->v_id);
                $grn              =  Grn::create($data);
            }else{
                $grn = Grn::where('id', $request->id)->update($data);
                $grn = Grn::find($request->id);
            }
            $this->addgrnlist($request->grnlist,$grn->id);
        }  
    }//End of add grn

    private function addgrnlist(Request $request){

        $v_id       = $request->v_id;
        $store_id   = $request->store_id;
        $advice_id  = $request->advice_id;
        $remark     = $request->remark;
        $grn_list   = json_decode($request->grn_list);
        $grn_list   = collect($grn_list);
        $adviceList = AdviseList::where('advice_id', $advice_id)->get();
        #######################
        # Begin Transaction ###
        # #####################
        DB::beginTransaction();
        try{

            $grn = new Grn;
            $grn->v_id = $v_id;
            $grn->store_id = $store_id;
            $grn->advice_id = $advice_id;
            //$grnlist->grn_id      = $grnid;
            $grn->request_qty = $adviceList->sum('qty');
            $grn->qty         = $grn_list->sum('qty');
            $grn->subtotal    = $grn_list->sum('subtotal');
            $grn->discount    = $grn_list->sum('discount');
            $grn->tax         = $grn_list->sum('tax');
            $grn->total       = $grn_list->sum('total');
            $grn->damage_qty  = $grn_list->sum('damage_qty');
            $grn->lost_qty    = $grn_list->sum('lost_qty');
            $grn->remarks     = $remarks;
            $grn->save();

            foreach ($grn_list as $key => $grn) {

                $advice_list_item = $adviceList->where('id', $grn->advice_list_id)->first();
                $grnlist              = new GrnList();
                $grnlist->v_id        = $v_id;
                $grnlist->store_id    = $store_id;
                $grnlist->grn_id      = $grn->id;
                $grnlist->advice_list_id= $advice_list_item->id;
                $grnlist->sku_code     = $advice_list_item->sku_code;
                $grnlist->item_no     = $advice_list_item->item_no;
                $grnlist->item_desc   = $advice_list_item->item_desc;
                $grnlist->request_qty = $advice_list_item->qty;
                $grnlist->unit_mrp    = $advice_list_item->unit_mrp;
                $grnlist->cost_price  = $advice_list_item->cost_price;
                $grnlist->qty         = $grn->qty;
                $grnlist->subtotal    = $grn->subtotal;
                $grnlist->discount    = $grn->discount;
                $grnlist->tax         = $grn->tax;
                $grnlist->total       = $grn->total;
                $grnlist->damage_qty  = $grn->damage_qty;
                $grnlist->lost_qty    = $grn->lost_qty;
                $grnlist->remarks     = $grn->remarks;
                $grnlist->save();
            }

            DB::commit();
        }catch(Exception $e){
            DB::rollback();
            exit;
        }
        #######################
        ### ENd Transaction ###
        ######################

        
    }//End of addgrnlist



    ############### Start Grn Api ########################

    public function createGrn(Request $request) {

        $v_id       = $request->v_id;
        $store_id   = $request->store_id;
        $vu_id      = $request->vu_id;
        $advice_id  = $request->advice_id;
        $remarks    = $request->remarks;   
        $trans_from = $request->trans_from;
        $grn_id = $request->grn_id;
        $grn_list   = json_decode($request->grn_list);
        if($request->trans_from == 'CLOUD_TAB_WEB' || $request->trans_from == 'CLOUD_TAB' || $request->trans_from == 'ANDROID_VENDOR' || $request->trans_from == 'VENDOR_PANEL') {
            return $this->newCreateGrn($request);
        }

       // array_map('intval', $grn_list);
        $grn_list   = collect($grn_list);
        $grn_list = $grn_list->each(function($item, $key){
            if($item->received_qty == ''){
                $item->received_qty = 0;
            }
            
            if($item->damage_qty == ''){
                $item->damage_qty = 0;
            }

            if($item->lost_qty == ''){
                $item->lost_qty = 0;
            }

            return $item;
        });
         //print($grn_list);die;
        $adviceList = AdviseList::where('advice_id', $advice_id)->get(); 
        #######################
        # Begin Transaction ###
        # #####################
        DB::beginTransaction();
        try{
            // echo $request->grnid;die;
            if($request->grnid){
                $grn = Grn::find($request->grnid);
            }else{
                $where      = array('v_id'=>$request->v_id,'advice_id'=>$request->advice_id);
                $existgrn   = Grn::where($where)->first();
                /*Validation when grn already exist for particular advice*/
                if($existgrn){
                    return response()->json([ 'status'=>'fail','message' => 'The Grn was already generated for this advice'], 200);
                }
                $grn        = new Grn;
            }
            $grn->v_id      = $v_id;
            $grn->store_id  = $store_id;
            $grn->advice_id = $advice_id;
            $grn->vu_id     = $vu_id;
            $grn->grn_no    = grn_no_generate($v_id,$trans_from);
            /*Validation when grn all row empty*/
            if($grn_list->sum('received_qty') <= 0){
                return response()->json([ 'status'=>'fail','message' => 'Add atleast one item for grn'], 200);
            }
            $grn->request_qty = $adviceList->sum('qty');
            $grn->qty         = $grn_list->sum('received_qty');
            $grn->subtotal    = $grn_list->sum('subtotal');
            $grn->discount    = $grn_list->sum('discount');
            $grn->tax         = $grn_list->sum('tax');
            $grn->total       = $grn_list->sum('total');
            $grn->damage_qty  = $grn_list->sum('damage_qty');
            $grn->lost_qty    = $grn_list->sum('lost_qty');
            $grn->remarks     = $remarks;
            $grn->save();
            
            foreach ($grn_list as $key => $grn_item) {
                $advice_list_item = $adviceList->where('id', $grn_item->advice_list_id)->first();
                //print_r($advice_list_item); die;
                $where       = array('v_id'=> $v_id,'grn_id' => $grn->id,'item_no'=>$advice_list_item->item_no);
                $grnlist     = GrnList::where($where)->first();
                if(!$grnlist){
                    $grnlist = new GrnList();
                }
                //$grnlist = new GrnList();
                $grnlist->v_id        = $v_id;
                $grnlist->store_id    = $store_id;
                $grnlist->vu_id       = $vu_id;
                $grnlist->grn_id      = $grn->id;
                $grnlist->advice_list_id= $grn_item->id;
                $grnlist->sku_code     = $advice_list_item->sku_code;
                $grnlist->item_no     = $advice_list_item->item_no;
                $grnlist->item_desc   = $advice_list_item->item_desc;
                $grnlist->request_qty = $advice_list_item->qty;
                $grnlist->unit_mrp    = $advice_list_item->unit_mrp;
                $grnlist->qty         = $grn_item->received_qty;
                $grnlist->subtotal    = $grn_item->subtotal;
                $grnlist->discount    = $grn_item->discount;
                $grnlist->tax         = $grn_item->tax;
                $grnlist->tax_details          = $grn_item->tax_details;
                $grnlist->total       = $grn_item->total;
                $grnlist->damage_qty  = $grn_item->damage_qty;
                $grnlist->lost_qty    = $grn_item->lost_qty;
                $grnlist->remarks     = $grn_item->remarks;
                $grnlist->save();

                #####################
                #### Begin Batches ##
                #####################


                $whereitem = array('sku'=> $advice_list_item->item_no,'v_id'=>$request->v_id);
                $item      = VendorSkuDetails::where($whereitem)->first();
                if(!$item){

                    $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $advice_list_item->item_no)->first();
                    if($bar){
                        $whereitem = array('id'=> $bar->vendor_sku_detail_id,'v_id'=>$request->v_id);

                        $skuBarocde = VendorSkuDetailBarcode::where($whereitem)->first();
                        $skuBarocde->barcode = $bar->barcode;

                    }
                    $item      = $skuBarocde->vendorSkuDetail;
                    if(!$item){
                       return response()->json([ 'status'=>'fail','message' => $advice_list_item->item_no.' Item Not Found. Please Add Item First'], 200);
                   }
               }else{
                    $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('vendor_sku_detail_id', $item->id)->first();
                    $item->barcode = $bar->barcode;
               }

               $varient_price_ids = VendorItemPriceMapping::
               where('v_id', $request->v_id)
               ->where('item_id', $item->item_id)
               ->where('variant_combi', $item->variant_combi)
               ->first();

               if(!$varient_price_ids){
                $itemPrice         = new ItemPrices();
                $itemPrice->mrp    = $advice_list_item->unit_mrp;
                $itemPrice->rsp    = $advice_list_item->unit_mrp;
                $itemPrice->special_price  = $advice_list_item->unit_mrp;
                $itemPrice->save();
                $varient_price_ids                = new VendorItemPriceMapping();
                $varient_price_ids->v_id          = $request->v_id;
                $varient_price_ids->item_id       = $item->item_id;
                $varient_price_ids->variant_combi = $item->variant_combi;
                $varient_price_ids->item_price_id = $itemPrice->id;
                $varient_price_ids->save();
                    //$varientPrId  = $varient_price_ids->item_price_id;
                }

                if(isset($grn_item->batch) && count($grn_item->batch)>0){
                    foreach($grn_item->batch as $batch){
                        $batch_no     = isset($batch->batch_no) ? $batch->batch_no:NULL;
                        $mfg_date     = isset($batch->mfg_date) ? $batch->mfg_date:NULL;
                        $exp_date     = isset($batch->exp_date) ? $batch->exp_date:NULL;
                        $valid_months = isset($batch->valid_months) ? $batch->valid_months:NULL;

                        $requestbatch  = array('batch_no'=>$batch_no,'mfg_date'=>$mfg_date,'exp_date'=>$exp_date,'valid_months'=>$valid_months,'item_price_id'=>$varient_price_ids->item_price_id,'qty'=>$grn_item->received_qty);
                        $this->addBatch($requestbatch,$grnlist->id,$request->v_id);
                    }
                }else{
                    // $requestbatch  = array('batch_no'=>'','mfg_date'=>'','exp_date'=>'','valid_months'=>'','item_price_id'=>$varient_price_ids->item_price_id,'qty'=>$grn_item->received_qty);
                        // $this->addBatch($requestbatch,$grnlist->id,$request->v_id);
                }
                #####################
                ####  End Batches ###
                #####################




                if(isset($grn_item->serial) && count($grn_item->serial) > 0){
                    $this->addSerial($grn_item->serial,$grnlist->id,$request->v_id,$store_id)  ; 
                }

            

                if($advice->client_advice_no !=null && $advice->client_advice_no!=''){
                    $zwingTagVId = '<ZWINGV>'.$v_id.'<EZWINGV>';
                    $zwingTagStoreId = '<ZWINGSO>'.$store_id.'<EZWINGSO>';
                    $zwingTagTranId = '<ZWINGTRAN>'.$grn->id.'<EZWINGTRAN>';
                    event(new GrnCreated(['v_id' => $v_id, 'store_id' => $store_id, 'grn_id' => $grn->id, 'advice_id' => $advice_id, 'zv_id' => $zwingTagVId, 'zs_id' => $zwingTagStoreId, 'zt_id' => $zwingTagTranId]));
                }

                ######################  Auto Stock ###################################

                /*Stock Start*/
                //if($request->get('stock_point')){

                    // $itemlist = GrnList::where([
                    //     'grn_id' => $grn->id,
                    //     'v_id' => $vendor_id, 'store_id' => $store_id
                    // ])->with(['Items' => function ($query) use ($vendor_id) {
                    //     $query->where('v_id', $vendor_id);
                    // }, 'batches'])->get();
                    // $items = null;
                    
                    // if(!isset($itemlist->items)){
                    //     $itemlist = GrnList::where([
                    //         'grn_id' => $grn->id,
                    //         'v_id' => $vendor_id, 'store_id' => $store_id
                    //     ])->with(['skuItems' => function ($query) use ($vendor_id) {
                    //         $query->where('v_id', $vendor_id);
                    //     }, 'batches'])->get();
                        
                    //     // dd($itemlist);
                    //     $items = $itemlist->map(function ($item, $key) {
                    //         $items['item_id']       = $item->skuItems->item_id; //$varient->item_id;
                    //         $items['variant_sku']   = $item->skuItems->sku;
                    //         $items['variant_barcode'] = $item->skuItems->barcode;
                    //         $items['variant_combi'] = $item->skuItems->variant_combi;
                    //         $batch   = Batch::find($item->batches->batch_id);
                    //         $items['batch'][]    = array('available_qty' => $item->qty, 'batch_no' => $batch->batch_no, 'batch_id' => $batch->id, 'exp_date' => $batch->exp_date, 'mfg_date' => $batch->mfg_date, 'mrp' => $item->unit_mrp, 'valid_months' => $batch->valid_months, 'move_qty' => $item->qty, 'serial' => $item->serialNumbers);
                    //         /*Allocation store*/
                    //         $stockExist = StoreItems::where(['barcode' => $item->skuItems->barcode, 'v_id' => $item->v_id, 'store_id' => $item->store_id])->count();
                    //         if ($stockExist == 0) {
                    //             StoreItems::create([
                    //                 'v_id'      => $item->v_id,
                    //                 'store_id'  => $item->store_id,
                    //                 'barcode'   => $item->skuItems->barcode,
                    //                 'variant_sku' => $item->skuItems->sku,
                    //                 'item_id'   => $item->skuItems->item_id
                    //             ]);

                    //         }
                    //         return $items;
                    //     });
                    // }else{

                    //     $items = $itemlist->map(function ($item, $key) {
                    //         $items['item_id']       = $item->Items->item_id; //$varient->item_id;
                    //         $items['variant_sku']   = $item->Items->sku;
                    //         $items['variant_barcode'] = $item->Items->barcode;
                    //         $items['variant_combi'] = $item->Items->variant_combi;
                    //         $batch   = Batch::find($item->batches->batch_id);
                    //         $items['batch'][]    = array('available_qty' => $item->qty, 'batch_no' => $batch->batch_no, 'batch_id' => $batch->id, 'exp_date' => $batch->exp_date, 'mfg_date' => $batch->mfg_date, 'mrp' => $item->unit_mrp, 'valid_months' => $batch->valid_months, 'move_qty' => $item->qty, 'serial' => $item->serialNumbers);
                    //         /*Allocation store*/
                    //         $stockExist = StoreItems::where(['barcode' => $item->Items->barcode, 'v_id' => $item->v_id, 'store_id' => $item->store_id])->count();
                    //         if ($stockExist == 0) {
                    //             StoreItems::create([
                    //                 'v_id'      => $item->v_id,
                    //                 'store_id'  => $item->store_id,
                    //                 'barcode'   => $item->Items->barcode,
                    //                 'variant_sku' => $item->Items->sku,
                    //                 'item_id'   => $item->Items->item_id
                    //             ]);
                    //         }
                    //         return $items;
                    //     });
                    // }
                    // $from_point = array('name'=>$grn->grn_no,'id'=>$grn->id);
                    // $stock_point =  $request->get('stock_point'); 
                    // $request = new \Illuminate\Http\Request();
                    // $request->merge([
                    //     'v_id' => $vendor_id,
                    //     'move_from' => 'grn',
                    //     'to_point'  => json_encode($stock_point),
                    //     'from_point'=> json_encode($from_point),
                    //     'store_id'  => $grn->store_id,
                    //     'grn_id'    => $grn->id,
                    //     'items'     => json_encode($items)
                    // ]);

                    // $stockapi = new StockController;
                    // $stockapi->moveInventory($request);
                

               // }
                /*Stock End*/

                ###################### Auto Stock End ################################




            }
            DB::commit();
        }catch(Exception $e){
            DB::rollback();
            exit;
        }
        #######################
        ### ENd Transaction ###
        ######################
        $input      = $grn->created_at; 
        $date       = strtotime($input); 
        $created_at = date(' d F Y h:i:s', $date); 
        $cashier_name = isset($grn->cashier->first_name)?$grn->cashier->first_name.' '.$grn->cashier->last_name:'NA';
        $data       = ['grn_no' => $grn->grn_no , 'created_at' =>  $created_at , 'cashier_name' => $cashier_name ]; 
        return response()->json(["status" => 'success' ,'message' => 'Grn Created successfully', 'data' => $data ]);
    }//End of function

    public function newCreateGrn(Request $request) 
    {   
        $v_id = $request->v_id;
        JobdynamicConnection($v_id);
        $store_id = $request->store_id;
        $grnStockPoint = StockPoints::where('v_id', $v_id)->where('store_id', $store_id)->where('is_default', '1')->first();
        if(empty($grnStockPoint)){
             return response()->json(["status" => 'fail' ,'message' => 'Please select a default stock point for grn' ]);
        }

        if($request->has('stock_posting') && $request->stock_posting == 1) {
            // Stock Posting - Move to GRN stock point
            $grnStockPoint = StockPoints::where('v_id', $v_id)->where('store_id', $store_id)->where('is_default', '1')->first();

            $stockRequest = new \Illuminate\Http\Request();
            $stockRequest->merge([
                'v_id'              => $v_id,
                'grn_id'            => $request->grn_id,
                'stock_point_id'    => $grnStockPoint->id,
                'store_id'          => $store_id,
                'trans_from'        => $request->trans_from,
                'vu_id'             => $request->vu_id,
                'take'              => $request->take,
                'skip'              => $request->skip,
                'stock_posting'     => $request->stock_posting,
                'stock_count'       => $request->stock_count
            ]);

            $getStockPotingResponse = $this->newStockInGrn($stockRequest);
            $getStockPotingResponse = $getStockPotingResponse->getData();

            if($getStockPotingResponse->status == 'remaining_stock') {
                return response()->json((array)$getStockPotingResponse);
            }

            if($getStockPotingResponse->status == 'posted') {

                if($request->has('is_posted') && $request->is_posted == 1) {
                    // Update Advice Status
                    $updateAdviseStatus = Advise::find($request->advice_id);
                    $updateAdviseStatus->status = $updateAdviseStatus->current_status;
                    $updateAdviseStatus->save();

                    // Update GRN & GRN List Status
                    Grn::where('id', $request->grn_id)->update([ 'status' => 'posted' ]);
                    GrnList::where('grn_id', $request->grn_id)->update([ 'status' => 'posted' ]);

                    $grn = Grn::where([ 'v_id' => $v_id, 'store_id' => $store_id, 'id' => $request->grn_id ])->first();

                    if($updateAdviseStatus->client_advice_no !=null && $updateAdviseStatus->client_advice_no!=''){
                        $zwingTagVId = '<ZWINGV>'.$v_id.'<EZWINGV>';
                        $zwingTagStoreId = '<ZWINGSO>'.$store_id.'<EZWINGSO>';
                        $zwingTagTranId = '<ZWINGTRAN>'.$grn->id.'<EZWINGTRAN>';
                        event(new GrnCreated(['v_id' => $v_id, 'store_id' => $store_id, 'grn_id' => $grn->id, 'zv_id' => $zwingTagVId, 'zs_id' => $zwingTagStoreId, 'zt_id' => $zwingTagTranId]));
                    }
                    // $grn = Grn::find(154);
                    $print_url  =  env('API_URL').'/vendor/grn-print?grn_no='.$grn->grn_no.'&v_id='.$v_id.'&store_id='.$store_id;

                    $genDetails = [ 'grn_no' => $grn->grn_no, 'date' => date('d F Y', strtotime($grn->created_at)), 'received_qty' => $grn->qty, 'damage_qty' => $grn->damage_qty, 'lost_qty' => $grn->lost_qty, 'supplier' => @$grn->advice->supplier->name, 'place_from' => @$grn->advice->origin_from, 'total' => $grn->total, 'tax' => $grn->tax, 'remarks' => $grn->remarks,  'print_url'=>$print_url  ];
                } else {
                    // $genDetails['url'] = url('/').'/admin/grn/manage';
                }

                return response()->json(["status" => 'success' ,'message' => 'Grn Created successfully', 'data' => $genDetails]);
            }
        }

        if($request->trans_from === 'VENDOR_PANEL' || $request->trans_from === 'ANDROID_VENDOR') {
            if($request->has('grn_id') && !empty($request->grn_id) && $request->grn_id != "") {
                if(is_numeric($request->grn_id)) {
                    $grn = GRN::find($request->grn_id);
                } else {
                    return response()->json(["status" => 'fail' ,'message' => 'GRN ID is not character' ]);
                }
            } else {
                $deletedGrnIds = Grn::select('id')->where([ 'advice_id' => $request->advice_id, 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'status' => 'draft' ])->get()->pluck('id');
                if (!$deletedGrnIds->isEmpty()) {
                    $deletedBatchGrnListIds = GrnList::select('id')->whereIn('grn_id', $deletedGrnIds)->where([ 'is_batch' => 1 ])->get()->pluck('id');
                    if (!$deletedBatchGrnListIds->isEmpty()) {
                        GrnBatch::whereIn('grnlist_id', $deletedBatchGrnListIds)->delete();
                    }
                    $deletedSerialGrnListIds = GrnList::select('id')->whereIn('grn_id', $deletedGrnIds)->where([ 'is_serial' => 1 ])->get()->pluck('id');
                    if (!$deletedSerialGrnListIds->isEmpty()) {
                        GrnSerial::whereIn('grnlist_id', $deletedSerialGrnListIds)->delete();
                    }
                    GrnList::whereIn('grn_id', $deletedGrnIds)->delete();
                    Grn::whereIn('id', $deletedGrnIds)->delete();
                }
                return $this->entryGrn($request);
            }
        }

        
        $isGrnComplete = false;
        $grnData = json_decode(urldecode($request->grn_list));
        $grnData = collect($grnData);
        $grn_list_count = $request->grn_list_count;
        $grnData = $this->calculateGrn($grnData);
        $grn_list_where = [ 'v_id' => $v_id, 'store_id' => $store_id, 'grn_id' => $request->grn_id, 'vu_id' => $request->vu_id ];
        $save_grn_Count = GrnList::where($grn_list_where)->count();
        // dd($save_grn_Count);

        //this is use for done total list saved and function call again then it will return prashant
        // if($grn_list_count==$save_grn_Count){
            
        //     return response()->json(['status' => 'sucess', 'message' => 'All Grn List already  inserted!','data' => $grn], 200);
        // }
        //dd($save_grn_Count);

        // dd($grnData);

        // $totalGetQty = $grnData->sum('received_qty') + $grnData->sum('damage_qty') + $grnData->sum('excess_qty');

        // // Check All product recived / damage / lost qty is there or not

        // if($totalGetQty <= 0) {
        //     return response()->json([ 'status'=>'fail','message' => 'Add atleast one item for grn'], 200);
        // }

        // Check All item allocate to store or not Condition - Prasant done

        // foreach ($grnData as $key => $value) {
            
        // }

        $advie = Advise::find($request->advice_id);

        // DB::beginTransaction();

        try {
            
            // Insert Data in GRN

            // $grn = new Grn;

            // $grn->v_id = $v_id;
            // $grn->store_id = $store_id;
            // $grn->vu_id = $request->vu_id;
            // $grn->grn_no = grn_no_generate($v_id,$request->trans_from);
            // $grn->advice_id = $request->advice_id;
            // $grn->request_qty = $advie->qty;
            // $grn->qty = $grnData->sum('received_qty');
            // $grn->damage_qty = $grnData->sum('damage_qty');
            // // $grn->lost_qty = $grnData->sum('lost_qty');
            // $grn->short_qty = abs($grnData->sum('short_qty'));
            // $grn->excess_qty = $grnData->sum('excess_qty');
            // $grn->subtotal = $grnData->sum('subtotal');
            // $grn->discount = $grnData->sum('discount');
            // $grn->tax = $grnData->sum('tax');
            // $grn->total = $grnData->sum('total');
            // $grn->remarks = $request->remarks;
            // $grn->grn_from = 'ADVICE';

            // $grn->save();

            // Insert Data in GRN List table
            // dd($grnData);
            foreach ($grnData as $key => $grn_item) 
            {
                
                $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode','sku_code')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $grn_item->barcode)->first();
                // Check Item Allocation
                $checkItem = StoreItems::where('v_id', $v_id)->where('store_id', $store_id)->where('barcode', $grn_item->barcode)->first();
                if(empty($checkItem)) {
                    
                    $item = null;
                    if($bar){
                        $item = VendorSkuDetails::where(['id'=> $bar->vendor_sku_detail_id,'v_id'=>$v_id])->first();
                        $item->barcode = $bar->barcode;

                    }
                    StoreItems::create([ 'v_id' => trim($item->v_id), 'variant_sku' => trim($item->sku), 'sku_code' => $item->sku_code, 'item_id' => trim($item->item_id), 'store_id'  => trim($store_id), 'barcode'     =>trim($item->barcode) ]); 
                }

                //Added by Chandramani Tagging Sku code 
                $sku_code = null;
                if(!isset($grn_item->sku_code) ){
                    if($bar){                
                        $sku_code = $bar->sku_code;
                    }

                }else{

                    $sku_code = $grn_item->sku_code;
                }

                // DB::enableQueryLog();
                $grnlist = new GrnList;
                $grnlist->v_id          = $v_id;
                $grnlist->store_id      = $store_id;
                $grnlist->vu_id         = $request->vu_id;
                $grnlist->grn_id        = $grn->id;
                $grnlist->advice_list_id= $grn_item->advice_list_id;
                $grnlist->barcode     = $grn_item->barcode;
                $grnlist->sku_code     = $sku_code;
                // $grnlist->item_no     = $grn_item->barcode;
                $grnlist->name   = $grn_item->item_desc;
                $grnlist->request_qty = $grn_item->order_qty;
                $grnlist->unit_mrp    = $grn_item->unit_mrp;
                $grnlist->cost_price   = $grn_item->cost_price;
                $grnlist->qty         = (string)$grn_item->received_qty;
                $grnlist->subtotal    = format_number($grn_item->subtotal);
                $grnlist->discount    = $grn_item->discount;
                $grnlist->tax         = $grn_item->tax;
                $grnlist->tax_details = @$grn_item->tax_details;
                $grnlist->total       = format_number($grn_item->total);
                $grnlist->damage_qty  = (string)$grn_item->damage_qty;
                $grnlist->charges    = $grn_item->charges;
                $grnlist->short_qty    = (string)abs($grn_item->short_qty);
                $grnlist->excess_qty   = (string)$grn_item->excess_qty;
                $grnlist->remarks     = $grn_item->remarks;
                $grnlist->packet_id     = $grn_item->packet_id;
                $grnlist->packet_code     = $grn_item->packet_code;
                $grnlist->save();

                // $logs = DB::getQueryLog($grnlist);
                #####################
                #### Begin Batches ##
                #####################

                $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $grn_item->barcode)->first();
                $item = null;
                if($bar){
                    $whereitem = array('id'=> $bar->vendor_sku_detail_id,'v_id'=>$v_id);
                    $item      = VendorSkuDetails::where($whereitem)->first();
                    $item->barcode = $bar->barcode;

                }
                $varient_price_ids = VendorItemPriceMapping::
                                  where('v_id', $v_id)
                                ->where('item_id', $item->item_id)
                                ->where('variant_combi', $item->variant_combi)
                                ->first();

                if(!$varient_price_ids){
                    $itemPrice         = new ItemPrices();
                    $itemPrice->mrp    = $grn_item->unit_mrp;
                    $itemPrice->rsp    = $grn_item->unit_mrp;
                    $itemPrice->special_price  = $grn_item->unit_mrp;
                    $itemPrice->save();
                    $varient_price_ids                = new VendorItemPriceMapping();
                    $varient_price_ids->v_id          = $v_id;
                    $varient_price_ids->item_id       = $item->item_id;
                    $varient_price_ids->variant_combi = $item->variant_combi;
                    $varient_price_ids->item_price_id = $itemPrice->id;
                    $varient_price_ids->save();
                }

                if(isset($grn_item->batch) && count((array)$grn_item->batch)>0) {

                    //dd($grn_item->batch);
                    foreach($grn_item->batch as $batch){

                        $batch_no     = isset($batch->batch_no) ? $batch->batch_no:NULL;
                        $mfg_date     = isset($batch->mfg_date) ? $batch->mfg_date:NULL;
                        $exp_date     = isset($batch->exp_date) ? $batch->exp_date:NULL;
                        $valid_months = isset($batch->validty_no) ? $batch->validty_no:NULL;
                        $validty_type = isset($batch->validty_type) ? $batch->validty_type:NULL;
                        $mrp          = format_number($batch->mrp);
                        $params       = array('mrp'=>$mrp,'rsp'=>$mrp,'special_price'=>$mrp);
                        $price_id     = $this->priceAdd($params);

                        // if($valid_months){
                        //     $valid_months =  $valid_months.' '.$validity_num;
                        // }

                        $receivedQty = isset($batch->receivedQty)?$batch->receivedQty:$batch->qty;
                        $batch_damage_qty = 0;
                        if(isset($batch->damage_qty)){
                            $batch_damage_qty = $batch->damage_qty;
                        }
                        $requestbatch  = [ 'batch_no' => $batch_no, 'mfg_date' => $mfg_date, 'exp_date' => $exp_date, 'valid_months' => $valid_months, 'item_price_id' => $price_id, 'qty' => $receivedQty, 'validty_type' => $validty_type, 'damage_qty' => $batch_damage_qty ];
                        $batchId =   $this->addBatch($requestbatch,$grnlist->id,$v_id);
                        VendorItemBatch::create(['v_id'=>$v_id,
                            'variant_combi'=>'default',
                            'item_id' => $item->item_id, 'sku_code' =>  $item->sku_code
                            ,'batch_id'=>$batchId]);
                    }
                }
                // else{
                //     $requestbatch  = array('batch_no'=>'','mfg_date'=>'','exp_date'=>'','valid_months'=>'','item_price_id'=>$varient_price_ids->item_price_id,'qty'=>$grn_item->received_qty);
                //         // $this->addBatch($requestbatch,$grnlist->id,$v_id);
                // }

                #####################
                ####  End Batches ###
                #####################

                if(isset($grn_item->serial) && count($grn_item->serial) > 0) {
                    $this->addSerial($grn_item->serial,$grnlist->id,$v_id,$store_id); 
                }

                
                //chek how many list are remaing if done then break  prashant code
                // $current_save_grnlist_count = GrnList::where($grn_list_where)->count();
                // $remaining_list=$grn_list_count-$current_save_grnlist_count;
                // if($grn_list_count==$current_save_grnlist_count){
                //     break;
                // }

                if($advie->type == 'SST') {
                    $source_site_id = $advie->store_id;
                    $source_site_type = 'store';
                } else {
                    $source_site_id = $grn->supplier_id;
                    $source_site_type = 'supplier';
                }

                if($request->has('is_posted') && $request->is_posted == 1) {
                    // Save Last Inward Rate

                    if($grnlist->qty>0){
                    $inwardDiscount = $grnlist->discount / ( $grnlist->qty + $grnlist->damage_qty );
                    $inwardTax = $grnlist->tax / ( $grnlist->qty + $grnlist->damage_qty );
                    $inwardCharge = $grnlist->charges / ( $grnlist->qty + $grnlist->damage_qty );
                    }else{
                     $inwardDiscount = 0.0;
                     $inwardTax      =0.0;
                     $inwardCharge   =0.0;
                    }

                    $itemCon = new ItemController;
                    $rateRequest = new \Illuminate\Http\Request();
                    $rateRequest->merge([
                        'v_id'                      => $v_id,
                        'source_site_id'            => $source_site_id,
                        'source_site_type'          => $source_site_type,
                        'item_id'                   => $item->item_id,
                        'barcode'                   => $grnlist->barcode,
                        'supply_price'              => $grnlist->cost_price,
                        'discount'                  => format_number($inwardDiscount),
                        'tax'                       => format_number($inwardTax),
                        'charge'                    => format_number($inwardCharge),
                        'source_transaction_type'   => 'GRN',
                        'source_transaction_id'     => $grnlist->id,
                        'destination_site_type'     =>'store',
                        'destination_site_id'       => $grn->store_id,
                    ]);

                    // dd($grn);
                    $itemCon->saveLastInwardPrice($rateRequest);
                }

            }
            // DB::commit();
            // return [ 'response' => $testdata ];

            // Check all grn product has been insert in grn list
            $tempCount = GrnList::where($grn_list_where)->count();
            if($grn_list_count == $tempCount) {
                $grnReceivedQty = GrnList::where($grn_list_where)->sum('qty');
                $grnDamagedQty = GrnList::where($grn_list_where)->sum('damage_qty');
                $grnShortQty = GrnList::where($grn_list_where)->sum('short_qty');
                $grnExcessQty = GrnList::where($grn_list_where)->sum('excess_qty');
                $grnSubtotal = GrnList::where($grn_list_where)->sum('subtotal');
                $grnDiscount = GrnList::where($grn_list_where)->sum('discount');
                $grnTax = GrnList::where($grn_list_where)->sum('tax');
                $grnCharges = GrnList::where($grn_list_where)->sum('charges');
                $grnTotal = GrnList::where($grn_list_where)->sum('total');
                $grnUniquePacket = GrnList::distinct()->where($grn_list_where)->count('packet_code');
                if(empty($grn->requested_packets)) {
                    $grnUniquePacket = 0;
                }
                Grn::where('id', $grn->id)->update([ 'qty' => $grnReceivedQty, 'damage_qty' => $grnDamagedQty, 'short_qty' => $grnShortQty, 'excess_qty' => $grnExcessQty, 'subtotal' => format_number($grnSubtotal), 'discount' => format_number($grnDiscount), 'tax' => format_number($grnTax), 'charges' => format_number($grnCharges), 'total' => format_number($grnTotal), 'received_packets' => $grnUniquePacket ]);
                if($request->has('is_posted') && $request->is_posted == 0) {
                    $genDetails['url'] = url('/').'/admin/grn/manage';
                    $genDetails['grn_no'] = $grn->grn_no;
                    $genDetails['date'] = date('d F Y', strtotime($grn->created_at));


                    return response()->json(["status" => 'success' ,'message' => 'Grn Created successfully', 'data' => $genDetails]);
                } else {
                    return response()->json([ "status" => 'grn_continue']);
                }
            } else {
                $remaining_list = $grn_list_count - $tempCount;
                return response()->json(["status" => 'continue' , 'remaining' => $remaining_list]);
            }
            
            //end prashant code 
               
            if($advie->type == 'SST'){
                $adviceID =  $advie->id;
                $stockIntransit = StockIntransit::where('advice_id',$adviceID)->where('v_id',$v_id)->first();
                //dd($stockIntransit);
                if($stockIntransit){
                    $stockIntransit->is_moved = '1';
                    $stockIntransit->save();
                }
            }
            
            if($request->has('is_posted') && $request->is_posted == 1) {
                // Move to GRN stock point
                // $grnStockPoint = StockPoints::where('v_id', $v_id)->where('store_id', $store_id)->where('is_default', '1')->first();

                // $stockRequest = new \Illuminate\Http\Request();
                // $stockRequest->merge([
                //     'v_id'              => $v_id,
                //     'grn_id'            => $grn->id,
                //     'stock_point_id'    => $grnStockPoint->id,
                //     'store_id'          => $store_id,
                //     'trans_from'        => $request->trans_from,
                //     'vu_id'             => $request->vu_id
                // ]);

                //print_r($stockRequest);

                // $this->stockInGrn($stockRequest);
                // Update Advice Status

                // $updateAdviseStatus = Advise::find($request->advice_id);
                // $updateAdviseStatus->status = $updateAdviseStatus->current_status;
                // $updateAdviseStatus->save();

                // Update GRN & GRN List Status
                // Grn::where('id', $grn->id)->update([ 'status' => 'posted' ]);
                // GrnList::where('grn_id', $grn->id)->update([ 'status' => 'posted' ]);

                // DB::commit();

                // if($advie->client_advice_no !=null && $advie->client_advice_no!=''){
                //     $zwingTagVId = '<ZWINGV>'.$v_id.'<EZWINGV>';
                //     $zwingTagStoreId = '<ZWINGSO>'.$store_id.'<EZWINGSO>';
                //     $zwingTagTranId = '<ZWINGTRAN>'.$grn->id.'<EZWINGTRAN>';
                //     event(new GrnCreated(['v_id' => $v_id, 'store_id' => $store_id, 'grn_id' => $grn->id, 'zv_id' => $zwingTagVId, 'zs_id' => $zwingTagStoreId, 'zt_id' => $zwingTagTranId]));
                // }
                // // $grn = Grn::find(154);
                // $print_url  =  env('API_URL').'/vendor/grn-print?grn_no='.$grn->grn_no.'&v_id='.$v_id.'&store_id='.$store_id;

                // $genDetails = [ 'grn_no' => $grn->grn_no, 'date' => date('d F Y', strtotime($grn->created_at)), 'received_qty' => $grn->qty, 'damage_qty' => $grn->damage_qty, 'lost_qty' => $grn->lost_qty, 'supplier' => @$grn->advice->supplier->name, 'place_from' => @$grn->advice->origin_from, 'total' => $grn->total, 'tax' => $grn->tax, 'remarks' => $grn->remarks, 'product_list' => $grn->grn_product_details,'print_url'=>$print_url  ];
            } else {
                // $genDetails['url'] = url('/').'/admin/grn/manage';
            }

            // return response()->json(["status" => 'success' ,'message' => 'Grn Created successfully', 'data' => $genDetails]);

        } catch (Exception $e) {
            DB::rollback();
            echo $e->getMessage();
            exit;
        }
        }//End of newCreateGrn


    public function priceAdd($params){

        $mrp = $params['mrp'];
        $rsp = $params['rsp'];
        $sprice = $params['special_price'];

        $itemPrice = ItemPrices::where(['mrp'=>$mrp,'rsp'=>$rsp,'special_price'=>$sprice])->first();
        if(!$itemPrice){
            $itemPrice         = new ItemPrices();
            $itemPrice->mrp    = $mrp;
            $itemPrice->rsp    = $rsp;
            $itemPrice->special_price  = $sprice;
            $itemPrice->save(); 
        }
        return $itemPrice->id;
    }//End function priceAdd

    public function calculateGrn($data)
    {
        $data->map(function($item) {
            $adviseDetails = AdviseList::find($item->advice_list_id);
            $conditionQty = $adviseDetails->qty - $item->damage_qty - $item->received_qty;
            $item->qty_flag = 'equal';
            if($conditionQty < 0) {
                $item->excess_qty = abs($conditionQty);
                $item->short_qty = 0;
                $item->qty_flag = 'excess';
            } else {
                $item->short_qty = -1 * $conditionQty;
                $item->excess_qty = 0;
                $item->qty_flag = 'short';
            }
            $item->item_no = $item->barcode;
            $item->item_desc = $adviseDetails->item_desc;
            $item->order_qty = $adviseDetails->qty;
            $item->unit_mrp = $adviseDetails->unit_mrp;
            $item->cost_price = $adviseDetails->cost_price;
            $item->subtotal = $item->subtotal;
            $item->discount = $item->discount;
            $item->tax = $item->tax;
            $item->total = $item->total;
            $item->charges = $item->charges;
            $item->lost_qty = abs($item->lost_qty);
            $item->packet_id = $adviseDetails->packet_id;
            $item->packet_code = $adviseDetails->packet_code;
            return $item;
        });
        return $data;
    }

    public function createAdhoc(Request $request)
    {
        if($request->trans_from === 'VENDOR_PANEL') {
            if($request->has('grn_id') && !empty($request->grn_id) && $request->grn_id != "") {
                if(is_numeric($request->grn_id)) {
                    $grn = GRN::find($request->grn_id);
                } else {
                    return response()->json(["status" => 'fail' ,'message' => 'GRN ID is not number' ]);
                }
            } else {
                return $this->entryGrn($request, 'adhoc');
            }
        }

        $v_id       = $request->v_id;
        $store_id   = $request->store_id;
        $vu_id      = $request->vu_id;
        $remarks    = $request->remarks;   
        $trans_from = $request->trans_from;
        $grn_list   = json_decode($request->grn_list);
        $grn_list   = collect($grn_list);
        $grn_list_count = $request->grn_list_count;

        $grn_list_where = [ 'v_id' => $v_id, 'store_id' => $store_id, 'grn_id' => $request->grn_id, 'vu_id' => $request->vu_id ];

        // DB::beginTransaction();
        try {
            // $supplier = Supplier::where('name',trim($request->supplier))->select('id')->first();
            // if(!$supplier){
            //     $supplier = new Supplier;
            //     $supplier->name = $request->supplier;
            //     $supplier->v_id = $v_id;
            //     $supplier->save();
            // }

            // $data = [
            //     'v_id'          => $v_id,
            //     'grn_from'      => 'ADHOC',
            //     'store_id'      => $store_id,
            //     'vu_id'         => $vu_id,
            //     'qty'           => 0,
            //     'subtotal'      => 0,
            //     'discount'      => 0,
            //     'tax'           => 0,
            //     'total'         => 0,
            //     'damage_qty'    => 0,
            //     'charges'       => 0,
            //     'remarks'       => $remarks?$remarks:null,
            //     'supplier_id'   => $supplier->id,
            //     'origin_from'   => $request->place
            // ];
            // $data['grn_no'] = grn_no_generate($v_id,$trans_from);
            // $grn            = Grn::create($data);
            // $totalQuantity  = 0;
            // $totalDamageQty = 0;
            // $totalMrp       = 0;
            // dd($grn_list);
            foreach ($grn_list as $key => $grn_item) {
                $where     = array('v_id'=> $v_id,'grn_id' => $grn->id,'barcode'=>$grn_item->barcode);
                $grnlist   = GrnList::where($where)->first();
                if(!$grnlist){
                    $grnlist = new GrnList();
                }

                // Check Item Allocation

                $checkItem = StoreItems::where('v_id', $v_id)->where('store_id', $store_id)->where('barcode', $grn_item->barcode)->first();

                $bar = VendorSkuDetailBarcode::select('sku_code','vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $grn_item->barcode)->first();

                $item =  null;
                if($bar){
                    $item = VendorSkuDetails::where(['id'=> $bar->vendor_sku_detail_id,'v_id'=>$v_id])->first();
                    $item->barcode = $bar->barcode;

                }
                if(empty($checkItem)) {
                    StoreItems::create([ 'v_id' => trim($item->v_id), 'variant_sku' => trim($item->sku), 'sku_code' => $bar->sku_code ,  'item_id' => trim($item->item_id), 'store_id'  => trim($store_id), 'barcode'     =>trim($item->barcode) ]); 
                }

                //Adding sku_code
                $sku_code = null;
                if(!isset($grn_item->sku_code) ){
                    if($bar){                
                        $sku_code = $bar->sku_code;
                    }
                }else{
                    $sku_code = $grn_item->sku_code;
                }

                // $item = VendorSkuDetails::where(['barcode'=> $grn_item->barcode,'v_id'=> $v_id])->first();
                //$grnlist = new GrnList();
                // $grn_item->unit_mrp   = 0;
                // $grn_item->discount   = 0;
                // $grn_item->move_qty = $grn_item->received_qty;

                // $total   = $grn_item->received_qty*$grn_item->unit_mrp;
                
                $grnlist->v_id        = $v_id;
                $grnlist->store_id    = $store_id;
                $grnlist->vu_id       = $vu_id;
                $grnlist->grn_id      = $grn->id;
                $grnlist->sku_code     = $sku_code;
                $grnlist->barcode     = $grn_item->barcode;
                // $grnlist->item_no     = $grn_item->barcode;
                // $grnlist->item_desc   = $item->Item->name;
                $grnlist->name        = $item->Item->name;
                $request_qty=$grn_item->received_qty + $grn_item->damage_qty;
                $grnlist->request_qty = (string)$request_qty;
                if(property_exists($grn_item, 'unit_mrp')) {
                    $grnlist->unit_mrp    = $grn_item->unit_mrp ? $grn_item->unit_mrp : 0;
                }
                $grnlist->cost_price  = (string)$grn_item->supply_price;
                $grnlist->qty         = (string)$grn_item->received_qty;
                $grnlist->subtotal    = (string)$grn_item->subtotal;
                $grnlist->discount    = $grn_item->discount ? (string)$grn_item->discount : 0;
                $grnlist->charges     = $grn_item->charges ? (string)$grn_item->charges : 0;
                $grnlist->tax         = $grn_item->tax ? (string)$grn_item->tax : 0;
                $grnlist->tax         = $grn_item->tax_details ? (string)$grn_item->tax_details : 0;
                $grnlist->total       = (string)$grn_item->total;
                $grnlist->status      = 'draft';
                $grnlist->damage_qty  = $grn_item->damage_qty ? (string)$grn_item->damage_qty : 0;
                //$grnlist->lost_qty    = $grn_item->lost_qty?$grn_item->lost_qty:0;
                
                /*Packet */
                $grnlist->packet_id   = isset($grn_item->packet_id)?$grn_item->packet_id:'0';
                $grnlist->packet_code = isset($grn_item->packet_code)?$grn_item->packet_code:'0';
                $grnlist->remarks     = @$grn_item->remarks;
                $grnlist->save();

                // $totalQuantity  += $grn_item->received_qty;
                // $totalDamageQty += $grn_item->damage_qty;
                // $totalMrp       += $grn_item->unit_mrp;
                #####################
                #### Begin Batches ##
                #####################

                        $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $grn_item->barcode)->first();
                        $item =  null;
                        if($bar){
                            $whereitem = array('id'=> $bar->vendor_sku_detail_id,'v_id'=>$request->v_id);
                            $item      = VendorSkuDetails::where($whereitem)->first();
                            $item->barcode = $bar->barcode;
                        }
                        $varient_price_ids = VendorItemPriceMapping::
                                          where('v_id', $request->v_id)
                                        ->where('item_id', $item->item_id)
                                        ->where('variant_combi', $item->variant_combi)
                                        ->first();

                        if(!$varient_price_ids){
                            $itemPrice         = new ItemPrices();
                            $itemPrice->mrp    = $grn_item->unit_mrp;
                            $itemPrice->rsp    = $grn_item->unit_mrp;
                            $itemPrice->special_price  = $grn_item->unit_mrp;
                            $itemPrice->save();
                            $varient_price_ids                = new VendorItemPriceMapping();
                            $varient_price_ids->v_id          = $request->v_id;
                            $varient_price_ids->item_id       = $item->item_id;
                            $varient_price_ids->variant_combi = $item->variant_combi;
                            $varient_price_ids->item_price_id = $itemPrice->id;
                            $varient_price_ids->save();
                            //$varientPrId  = $varient_price_ids->item_price_id;
                        }
                if(isset($grn_item->batch) && count($grn_item->batch)>0){
                    foreach($grn_item->batch as $batch){
                        $batch_no     = isset($batch->batch_no) ? $batch->batch_no:NULL;
                        $mfg_date     = isset($batch->mfg_date) ? $batch->mfg_date:NULL;
                        $exp_date     = isset($batch->exp_date) ? $batch->exp_date:NULL;
                        $valid_months = isset($batch->valid_months) ? $batch->valid_months:NULL;
                        $validty_type = isset($batch->validty_type) ? $batch->validty_type:NULL;
                        $mrp          = format_number($batch->mrp);
                        $params       = array('mrp'=>$mrp,'rsp'=>$mrp,'special_price'=>$mrp);
                        $price_id     = $this->priceAdd($params);

                        $receivedQty = isset($batch->receivedQty)?$batch->receivedQty:$batch->qty;

                        // $requestbatch  = array('batch_no'=>$batch_no,'mfg_date'=>$mfg_date,'exp_date'=>$exp_date,'valid_months'=>$valid_months,'item_price_id'=>$varient_price_ids->item_price_id,'qty'=>$grn_item->received_qty);
                        $requestbatch  = [ 'batch_no' => $batch_no, 'mfg_date' => $mfg_date, 'exp_date' => $exp_date, 'valid_months' => $valid_months, 'item_price_id' => $price_id, 'qty' => $receivedQty, 'validty_type' => $validty_type, 'damage_qty' => $batch->damage_qty ];
                        $this->addBatch($requestbatch,$grnlist->id,$request->v_id);
                    }
                }else{
                    // $requestbatch  = array('batch_no'=>'','mfg_date'=>'','exp_date'=>'','valid_months'=>'','item_price_id'=>$varient_price_ids->item_price_id,'qty'=>$grn_item->received_qty);
                        // $this->addBatch($requestbatch,$grnlist->id,$request->v_id);
                }
                #####################
                ####  End Batches ###
                #####################
                if(isset($grn_item->serial) && count($grn_item->serial) > 0) {
                    $this->addSerial($grn_item->serial,$grnlist->id,$request->v_id,$store_id)  ; 
                }

                // Save Last Inward Rate

                $inwardDiscount = $grnlist->discount / ( $grnlist->qty + $grnlist->damage_qty );
                $inwardTax = $grnlist->tax / ( $grnlist->qty + $grnlist->damage_qty );
                $inwardCharge = $grnlist->charges / ( $grnlist->qty + $grnlist->damage_qty );

                $itemCon = new ItemController;
                $rateRequest = new \Illuminate\Http\Request();
                $rateRequest->merge([
                    'v_id'                      => $v_id,
                    'source_site_id'            => $grn->supplier_id,
                    'source_site_type'          => 'supplier',
                    'item_id'                   => $item->item_id,
                    'barcode'                   => $grnlist->barcode,
                    'supply_price'              => $grnlist->cost_price,
                    'discount'                  => format_number($inwardDiscount),
                    'tax'                       => format_number($inwardTax),
                    'charge'                    => format_number($inwardCharge),
                    'source_transaction_type'   => 'GRN',
                    'source_transaction_id'     => $grnlist->id,
                    'destination_site_type'     =>'store',
                    'destination_site_id'       => $grn->store_id,
                ]);

                // dd($grn);

                $itemCon->saveLastInwardPrice($rateRequest);

            }

            // DB::commit();
            // $grn->qty       = $totalQuantity;
            // $grn->damage_qty       = $totalDamageQty;
            // $grn->subtotal  = $totalMrp;
            // $grn->total     = $totalMrp;
            // $grn->save();

            // Check all grn product has been insert in grn list 
            $tempCount = GrnList::where($grn_list_where)->count();
            if($grn_list_count == $tempCount) {
                $grn_list_where = [ 'v_id' => $v_id, 'grn_id' => $grn->id ];
                $grnReceivedQty = GrnList::where($grn_list_where)->sum('qty');
                $grnDamagedQty = GrnList::where($grn_list_where)->sum('damage_qty');
                $grnSubtotal = GrnList::where($grn_list_where)->sum('subtotal');
                $grnDiscount = GrnList::where($grn_list_where)->sum('discount');
                $grnTax = GrnList::where($grn_list_where)->sum('tax');
                $grnCharges = GrnList::where($grn_list_where)->sum('charges');
                $grnTotal = GrnList::where($grn_list_where)->sum('total');
                Grn::where('id', $grn->id)->update([ 'qty' => $grnReceivedQty, 'damage_qty' => $grnDamagedQty, 'subtotal' => format_number($grnSubtotal), 'discount' => format_number($grnDiscount), 'tax' => format_number($grnTax), 'charges' => format_number($grnCharges), 'total' => format_number($grnTotal), 'request_qty' => $grnReceivedQty + $grnDamagedQty ]);
            } else {
                $remaining_list = $grn_list_count - $tempCount;
                return response()->json(["status" => 'continue' , 'remaining' => $remaining_list]);
            }

            // Move to GRN stock point
            // $grnStockPoint = StockPoints::where('v_id', $v_id)->where('store_id', $store_id)->where('non_editable', 1)->where('code', 'STORE_WAREHOUSE')->first();

            $grnStockPoint = StockPoints::where('v_id', $v_id)->where('store_id', $store_id)->where('is_default', '1')->first();

            $stockRequest = new \Illuminate\Http\Request();
            $stockRequest->merge([
                'v_id'              => $v_id,
                'grn_id'            => $grn->id,
                'stock_point_id'    => $grnStockPoint->id,
                'store_id'          => $store_id,
                'trans_from'        => $request->trans_from,
                'vu_id'             => $request->vu_id
            ]);

            $this->stockInGrn($stockRequest);

            // Remaining

            Grn::where('id', $grn->id)->update([ 'status' => 'posted' ]);
            GrnList::where('grn_id', $grn->id)->update([ 'status' => 'posted' ]);


           // DB::commit();
        } catch(Exception $e){
            // DB::rollback();
            //exit;
        }


        // $input      = $grn->created_at; 
        // $date       = strtotime($input); 
        // $created_at = date(' d F Y h:i:s', $date); 
        $cashier_name = isset($grn->cashier->first_name)?$grn->cashier->first_name.' '.$grn->cashier->last_name:'NA';
        // $data       = ['grn_no' => $grn->grn_no , 'created_at' =>  $created_at , 'cashier_name' => $cashier_name ]; 



        $genDetails = [ 'grn_no' => $grn->grn_no, 'date' => date('d F Y', strtotime($grn->created_at)), 'received_qty' => $grn->qty, 'damage_qty' => $grn->damage_qty, 'lost_qty' => $grn->lost_qty, 'supplier' => @$grn->supplier->name, 'place_from' =>$grn->origin_from, 'total' => $grn->total, 'tax' => $grn->tax, 'remarks' => $grn->remarks,'cashier_name' => $cashier_name, 'product_list' => [], 'id' => $grn->id ];

        return response()->json(["status" => 'success' ,'message' => 'Grn Created successfully', 'data' => $genDetails ]);

        //return response()->json(["status" => 'success' ,'message' => 'Adhoc Grn Created successfully', 'data' => $data ]);

    }//End of createAdhoc


    private function addBatch($request,$grnlist_id,$v_id)
    {
        // dd($request);
        if(empty($request['batch_no'])) {
            $whereB = [ 'v_id' => $v_id, 'item_price_id' => $request['item_price_id'] ];
            $batch  = Batch::where($whereB)->first();
            if(!$batch){
                $batch = new Batch();
                $batch->batch_no = '';
                $batch->batch_code = generateBatchCode($v_id);
                $batch->v_id     = $v_id;
                $batch->item_price_id = $request['item_price_id'];
                $batch->save();
            }
        }else{
            $where  = [ 'v_id' => $v_id, 'batch_no' => trim($request['batch_no']) ];
            $batch  = Batch::where($where)->first();
            if(empty($batch)) {
                if(!empty($request['valid_months'])) {
                    $request['exp_date'] = getBatchExpireDate([ 'mfg_date' => $request['mfg_date'], 'validty' => $request['valid_months'], 'type' => $request['validty_type'] ]);
                }
                $batch  = new Batch;
                $batch->v_id     = $v_id;
                $batch->batch_no = $request['batch_no'];
                $batch->batch_code = generateBatchCode($v_id);
                $batch->mfg_date = $request['mfg_date'];
                $batch->exp_date = $request['exp_date'];
                $batch->valid_months = $request['valid_months'];
                $batch->validity_unit = $request['validty_type'];
                $batch->item_price_id = $request['item_price_id'];
                $batch->save();
            }
        }
        $data = [ 'grnlist_id' => $grnlist_id, 'batch_id' => $batch->id, 'qty' => $request['qty'] ];
        // $checkGrnBatchMapping = GrnBatch::where([ 'grnlist_id' => $grnlist_id, 'batch_id' => $batch->id ])->first();
        // if(!empty($checkGrnBatchMapping)) {

        // }
        GrnBatch::updateOrCreate(
            [ 'grnlist_id' => $grnlist_id, 'batch_id' => $batch->id ],
            [ 'qty' => $request['qty'], 'damage_qty' => $request['damage_qty'], 'batch_code' => $batch->batch_code ]
        );
        if(!empty($batch->batch_no) && $batch->batch_no != ''){
            $updategrnlist   = [ 'is_batch' => 1 ];
            GrnList::where('id',$grnlist_id)->update($updategrnlist);
        }
        return $batch->id;
    }//End of addBatch

    private function addSerial($request,$grnlist_id,$v_id, $store_id)
    {
        // dd($request);
        if(count($request) > 0) {
            foreach ($request as  $value) {

                if(isset($value->serial_no) && !empty($value->serial_no)) {

                    $where = [ 'v_id' => $v_id, 'serial_no' => $value->serial_no ];
                    $serial  = Serial::where($where)->first();
                    $stock_point_id = null;

                    $stockPoints = StockPoints::select('id')->where([ 'is_active'=> '1','is_default' => '1', 'store_id' => $store_id ])->first();
                    if($stockPoints){
                        $stock_point_id = $stockPoints->id;
                    }

                    $sku_code = null;
                    $grnlist = GrnList::select('sku_code')->where('id', $grnlist_id)->first();
                    if($grnlist) {
                        $sku_code = $grnlist->sku_code;
                    }

                    if(empty($serial)) {
                       $serial_no = $value->serial_no;
                       $isWarranty  = isset($value->isWarranty) ? $value->isWarranty:0;
                       $manufacturingDate  = isset($value->mfg_date) ? $value->mfg_date:null;
                       $warrantyPeriod = isset($value->warrantyPeriod) ? $value->warrantyPeriod:null;
                       $validty_type  = isset($value->validty_type) ? $value->validty_type:'MON';
                       // $udf1  = isset($value->udf1) ? $value->udf1:NULL;
                       // $udf2  = isset($value->udf2) ? $value->udf2:NULL;
                       // $udf3  = isset($value->udf3) ? $value->udf3:NULL;
                       // $udf4  = isset($value->udf4) ? $value->udf4:NULL;
                       $mrp = format_number($value->mrp);
                       $params = [ 'mrp' => $mrp, 'rsp' => $mrp, 'special_price' => $mrp ];
                       $price_id = $this->priceAdd($params);

                       $serial            = new Serial;
                       $serial->v_id      = $v_id;
                       $serial->store_id      = $store_id;
                       $serial->stock_point_id = $stock_point_id;
                       $serial->serial_no = $serial_no;
                       $serial->sku_code = $sku_code;
                       $serial->serial_code = generateSerialCode($v_id);
                       $serial->is_warranty = (string)$isWarranty;
                       $serial->manufacturing_date = $manufacturingDate;
                       $serial->warranty_period = $warrantyPeriod;
                       $serial->validity_unit = $validty_type;
                       // $serial->udf1     = $udf1;
                       // $serial->udf2     = $udf2;
                       // $serial->udf3     = $udf3;
                       // $serial->udf4     = $udf4;
                       $serial->item_price_id = $price_id;
                       $serial->save();
                    }

                    // $data = [ 'grnlist_id' => $grnlist_id, 'serial_id' => $serial->id, 'serial_code' => $serial->serial_code ];
                    // $grnSerialMapping = GrnSerial::create($data);
                    $isDamaged = empty($value->qty) ? '1' : '0';
                    GrnSerial::updateOrCreate(
                        [ 'grnlist_id' => $grnlist_id, 'serial_id' => $serial->id, 'serial_code' => $serial->serial_code ],
                        [ 'is_damage' => $isDamaged ]
                    );
                    $updategrnlist    = array('is_serial'=>1);
                    GrnList::where('id',$grnlist_id)->update($updategrnlist);
                }

            }
        } else {
            return false;
        }
    
    }

    public function detail(Request $request){
        if($request->ajax()) {
            $grn  = Grn::with('grnlist')->find($request->id);
            return $grn;
        }
    }//End of detail

    public function getGrn(Request $request){
        $v_id     = $request->v_id;
        $store_id = $request->store_id;
        $grn      = Grn::with('grnlist')->where(['v_id'=>$v_id,'store_id'=>$store_id])->get();
        return $grn;
    }
    
    public function printGrn(Request $request)
    {
        $trans_from = 'ANDROID_VENDOR';
        if($request->has('trans_from')) {
            $trans_from = $request->trans_from;
        }
        //$request = new \Illuminate\Http\Request();

       //echo $request->v_id;
        // $request->merge([
        //     'v_id' => $v_id,
        //     'c_id' => $c_id,
        //     'store_id' => $store_id,
        //     'grn_no' => $order_id
        // ]);
        if($trans_from == 'CLOUD_TAB_WEB') {
            $htmlGrnData = $this->GetPrintGrn($request);
            $html = $htmlGrnData->getContent();
            $html_obj_data = json_decode($html);
            $mainCart = new MainCart;
            // dd($html_obj_data);
            $htmlData = $mainCart->get_html_structure($html_obj_data->print_data);
            // $html = $htmlData->getContent();
            // $html_obj_data = json_decode($html);
            return response()->json(['status' => 'success',  'print_data' => $html_obj_data->print_data, 'html_data' => $htmlData], 200);
        } else {
            $htmlData = $this->GetPrintGrn($request);
        }

        if($trans_from == 'ANDROID_VENDOR') {
            return $this->GetPrintGrn($request);
        }
       
        $html = $htmlData->getContent();

        $html_obj_data = json_decode($html);
           

        if($html_obj_data->status == 'success')
        {
          
            return $this->get_html_structure($html_obj_data->print_data);
        }

    }



    public function GetPrintGrn(Request $request){

        $v_id     = $request->v_id;
        $store_id = $request->store_id;
        $grn_no   = $request->grn_no;
        $grn_data = [];
        $manufacturer_name = 'basewin';
        if($request->has('manufacturer_name') ){
            $manufacturer_name= $request->manufacturer_name;
        }
        $invoice_title = 'Grn Invoice';
        $store    = Store::find($store_id);
        $grn      = Grn::with('grnlist')->where(['v_id'=>$v_id,'store_id'=>$store_id,'grn_no'=>$grn_no])->first();

       // dd($grn);

        $advice   = ($grn->grn_from == 'ADVICE')?$grn->advice->advice_no:'ADHOC';
        $count    = 1;
        $lost     = 0;
        $damage   = 0;
        if($grn){
            foreach($grn->grnlist as $grn_fetch){
                array_push($grn_data, [
                    'row'     => 1,
                    'sr_no'   => $count++,
                    'item_no' => $grn_fetch->item_no,
                    'item_name' => $grn_fetch->Items->name,
                    'desc'    => $grn_fetch->item_desc 
                ]); 
                array_push($grn_data, [
                    'row'       => 2,
                    'req_qty'   => $grn_fetch->request_qty ,
                    'qty'       => $grn_fetch->qty,
                    'mrp'       => $grn_fetch->unit_mrp,
                    'total'     => $grn_fetch->total,
                ]);
            
                $lost   += $grn_fetch->lost_qty;
                $damage += $grn_fetch->damage_qty;

            }
        }else{

        }

        $printInvioce = new PrintInvoice($manufacturer_name);
        // Start center
        //$printInvioce->addLineCenter($site_details->NAME, 24, true);
        $printInvioce->addLineCenter($invoice_title, 29, true);
        $printInvioce->addLineCenter($store->name, 24, true);
        //$printInvioce->addLine('A unit of\nV mart Retail Limited', 22);
        $printInvioce->addLine($store->address.' '.$store->location.','.$store->pincode, 22);
        $printInvioce->addLine('Contact No.: '.$store->contact_number, 22);
        $printInvioce->addLine('E-mail: '.$store->email.'\n', 22);

        $printInvioce->leftRightStructure('GSTIN', $store->gst, 22);
        if(isset($store->cin)){
            $printInvioce->leftRightStructure('CIN: ',$store->cin.'\n', 22);
        }
        $printInvioce->leftRightStructure('Grn No.:',$grn->grn_no, 22);
        $printInvioce->leftRightStructure('Advice: ',$advice, 22);

        $printInvioce->leftRightStructure('Date: ',date('h:i A', strtotime($grn->created_at)).' '.date('d-M-Y', strtotime($grn->created_at)), 22);
        if(isset($grn->cashier)){
        $printInvioce->leftRightStructure('Person Name: ',@$grn->cashier->first_name.' '.@$grn->cashier->last_name, 22);
        }

        // Closes Left & Start center
        $printInvioce->addDivider('-', 20);
        $printInvioce->tableStructure(['Sr', 'Name', 'Barcode',''], [5, 18, 10,1], 22);
        $printInvioce->tableStructure([' Qty', 'Mrp', 'Total'], [9, 11, 14], 22);
        $printInvioce->addDivider('-', 20);

         for($i = 0; $i < count($grn_data); $i++) {
            if($i % 2 == 0) {
                $printInvioce->tableStructure([
                    $grn_data[$i]['sr_no'],
                    $grn_data[$i]['desc'],
                    $grn_data[$i]['item_no'],
                    ''
                ],
                [5, 12, 8,1], 22);
            } else {
                $printInvioce->tableStructure([

                    // $grn_data[$i]['req_qty'],
                    ' ' . $grn_data[$i]['qty'],
                    $grn_data[$i]['mrp'],
                    $grn_data[$i]['total'] 
                ],
                [15, 15, 10], 22);
            }
        }
        $printInvioce->addDivider('-', 20);
        $printInvioce->leftRightStructure('Qty', $grn->qty, 22);
        $printInvioce->leftRightStructure('Lost Qty', $lost, 22);
        $printInvioce->leftRightStructure('Damage Qty', $damage, 22);
        $printInvioce->tableStructure(['Total Amt', $grn->total ], [8, 4], 22);
        
        // $printInvioce->leftRightStructure('Round Off', format_number($roundoffamt), 22);
        // $printInvioce->leftRightStructure('Due:-', $due, 22);
        //$html = $htmlData->getContent();
        // $rr = array('status' => 'success', 'print_data' =>($printInvioce->getFinalResult()));
        // $html_obj_data = json_decode($rr); 
        // return $this->get_html_structure($html_obj_data->print_data);
         

         return response()->json(['status' => 'success', 'print_data' =>($printInvioce->getFinalResult())], 200);

        return response()->json(['status' => 'success', 'print_data' =>($printInvioce->getFinalResult())], 200);

    }//End of printGrn



    public function get_html_structure($str)
    {   


        $string = str_replace('<center>','<tbodyclass="center">',$str);
        $string = str_replace('<left>','<tbodyclass="left">',$string);
        $string = str_replace('<right>','<tbodyclass="right">',$string);
        $string = str_replace('</center>','</tbody>',$string);
        $string = str_replace('</left>','</tbody>',$string);
        $string = str_replace('</right>','</tbody>',$string);
        $string = str_replace('normal>','span>',$string);
        $string = str_replace('bold>','b>',$string);
        $string = str_replace('<size','<tr><td',$string);
        $string = str_replace('size>','td></tr>',$string);
        $string = str_replace('text','pre',$string);
        $string = str_replace('td=30','tdstyle="font-size:90px"',$string);
        $string = str_replace('td=24','tdstyle="font-size:16px"',$string);
        $string = str_replace('td=22','tdstyle="font-size:15px"',$string);
        $string = str_replace('td=20','tdstyle="font-size:14px"',$string);
        $string = str_replace('\n','&nbsp;',$string);
        // $DOM = new \DOMDocument;
        // $DOM->loadHtml($string);

        $string = urlencode($string);
        // $string = str_replace('+','&nbsp;&nbsp;');
        $string = str_replace('tds','td s',$string);
        $string = str_replace('tbodyc','tbody c',$string);

         $renderPrintPreview = '<!DOCTYPE html><html><head>
                                <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                                <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
                                <title>Cool</title>
                                <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
                                <style type="text/css">
                                * {  font-family: Lato; }
                                div { margin: 30px 0; border: 1px solid #f5f5f5; }
                                table {  width: 350px;  }
                                .center { text-align: center;  }
                                .left { text-align: left; }
                                .left pre { padding:0 30px !important; }
                                .right { text-align: right;  }
                                .right pre { padding:0 30px !important; }
                                td { padding: 0 5px; }
                                tbody { display: table !important; width: inherit; word-wrap: break-word; }
                                pre {
                                    white-space: pre-wrap;       /* Since CSS 2.1 */
                                    white-space: -moz-pre-wrap;  /* Mozilla, since 1999 */
                                    white-space: -pre-wrap;      /* Opera 4-6 */
                                    white-space: -o-pre-wrap;    /* Opera 7 */
                                    word-wrap: break-word;       /* Internet Explorer 5.5+ */
                                    overflow: hidden;
                                    background-color: #fff;
                                    padding: 0;
                                    border: none;
                                    font-size: 12.5px !important;
                                }
                                </style>
                        </head>
                            
                        <body>
                            <center>
                            
                                <div style="width: 350px;">
                                <table>
                            '
                                .urldecode($string).
                            '</table>
                            </div>
                            
                                </center>
                        </body>
                            </html>';
        
        return $renderPrintPreview;
    }

    public function grnDetails(Request $request) 
    {
        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $trans_from = $request->trans_from;
        $vu_id = $request->vu_id;
        $grn_no = $request->grn_no;

        $grn = Grn::where('v_id', $v_id)->where('store_id', $store_id)->where('grn_no', $grn_no)->first();
        // dd($grn);
        $print_url  =  env('API_URL').'/vendor/grn-print?grn_no='.$grn->grn_no.'&v_id='.$v_id.'&store_id='.$store_id;

        if($grn->grn_from != 'ADVICE'){
            $supplier_name = $grn->supplier->name;
            $origin_from = $grn->origin_from;
            $advice_no = "";
        }else{
            @$supplier_name = $grn->advice->supplier->name;
            $origin_from = $grn->advice->origin_from;
            $advice_no = $grn->advice->advice_no;
        }

        $genDetails = [ 'grn_no' => $grn->grn_no,'advice_no'=>$advice_no ,'date' => date('d F Y', strtotime($grn->created_at)),'order_qty' => $grn->request_qty, 'received_qty' => $grn->qty, 'damage_qty' => $grn->damage_qty, 'lost_qty' => $grn->lost_qty, 'total_discount'=> $grn->discount, 'subtotal' => $grn->subtotal,'total_charges' => $grn->charges,'supplier' => $supplier_name, 'place_from' => $origin_from, 'total' => $grn->total, 'tax' => $grn->tax, 'remarks' => $grn->remarks, 'product_list' => $grn->grn_product_details,'print_url'=>$print_url, 'short_qty' => $grn->short_qty, 'excess_qty' => $grn->excess_qty ];

        return response()->json(["status" => 'success' ,'message' => 'Grn Details', 'data' => $genDetails ]);

    }

    public function grnList(Request $request)
    {
        $data = [];
        $supplierList=[];
        $v_id = $request->v_id;
        $store_id   = $request->store_id;
        $trans_from = $request->trans_from;
        $vu_id      = $request->vu_id;
        $where      = array('v_id'=>$v_id,'store_id'=>$store_id);


        /*Filter start*/
        if($request->has('filter')) {
            $filterData = json_decode($request->filter);
        // dd($filterData);
            foreach ($filterData as  $value) {
                if($value->key == 'created_at') {
                    if(!empty($value->start_date)) {
                        $where[] = [ 'created_at', '>=', $value->start_date ];
                        $where[] = [ 'created_at', '<=', $value->end_date ];
                    }
                } else {
                    if(!empty($value->value)) {
                        if($value->key == 'sort') {
                            $sortBy = $value->value;
                        } else {
                           
                            $where[] = [ $value->key, $value->value ];
                        }
                    }
                }
            }
        }


        $grn = Grn::where($where)->orderBy('id','desc')->get();

        foreach ($grn as $key => $value) {
            if(isset($value->advice_id) && !empty($value->advice_id)){
            $data[] = [
                    'grn_no'        => $value->grn_no,
                    'advice_no'     => $value->advice->advice_no,
                    'grn_from'      => $value->grn_from,
                    'date'          => date('d F Y', strtotime($value->created_at)),
                    'received_qty'  => $value->qty,
                    'damage_qty'    => $value->damage_qty,
                    'lost_qty'      => $value->lost_qty,
                    'remarks'       => $value->remarks
                ];
             }else{
                 $data[] = [
                    'grn_no'        => $value->grn_no,
                    'grn_from'      => $value->grn_from,
                    'date'          => date('d F Y', strtotime($value->created_at)),
                    'received_qty'  => $value->qty,
                    'damage_qty'    => $value->damage_qty,
                    'lost_qty'      => $value->lost_qty,
                    'remarks'       => $value->remarks
                ];
             }
        }
        $getFilterData = Grn::select('supplier_id')->where('v_id', $request->v_id)->get();
        foreach ($getFilterData as $key => $supp) {
            if($supp->supplier_id){
                $supplierList[] = [ 'id' => isValueExists($supp->supplier, 'id', 'num'), 'name' => isValueExists($supp->supplier, 'name', 'str')];
            }
        }
        $filter['supplier'] = collect($supplierList)->unique()->values();
        $filter['sort'] = [
            ['key' => 'created_at', 'name' => 'Sort By Date'],
            ['key' => 'supplier_name', 'name' => 'Sort By Supplier'],
            ['key' => 'origin_from', 'name' => 'Sort By Origin']
        ];
        $filter['filter_format'] = [
            [ 'key' => 'created_at', 'start_date' => '', 'end_date' => '' ],
            [ 'key' => 'supplier_id', 'value' => '' ],
            [ 'key' => 'status', 'value' => '' ],
            [ 'key' => 'sort', 'value' => '' ]
        ];

        return response()->json(["status" => 'success' ,'message' => 'Grn List', 'data' => [ 'grn_list' => $data ],'filter' => $filter  ]);

    }

    public function stockInGrn(Request $request)
    {
        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $trans_from = $request->trans_from;
        $vu_id = $request->vu_id;
        $grn_id = $request->grn_id;
        $stock_point_id = $request->stock_point_id;
        $data = [];
        $grn = Grn::where([ 'id' => $grn_id, 'v_id' => $v_id ])->first();
        $stockCon = new StockController;

        // Check if grn already stock in

        // $checkGrn = StockIn::where('v_id', $v_id)->where('store_id', $store_id)->where('grn_id', $grn->id)->first();

        // if(!empty($checkGrn)) {
        //     return response()->json([ 'status'=>'fail', 'message' => 'Grn already stocked in'], 200);
        // }
        // $type = 'equal';
        // DB::beginTransaction();

        // try {
            //dd($grn->grnlist);die;
            foreach ($grn->grnlist->chunk(50) as  $grnlist) {
                foreach ($grnlist as $key => $value) {

                    $type = 'equal';

                    $is_batch = $is_serial = 0;

                    // Check Negative

                    // $totalQty = $value->request_qty - ($value->qty + $value->damage_qty + $value->lost_qty);
                    if($value->short_qty != '0.0') {
                        $type = 'short';
                    }

                    if($value->excess_qty != '0.0') {
                        $type = 'excess';
                    }                

                    // dd($value->short_qty);

                    // Check if batch 
                  
                    if($value->is_batch == 1) {
                        $grnBatch = GrnBatch::join('batch','batch.id','grn_batch.batch_id')
                                    ->select('batch.id as batch_id','batch.batch_code','grn_batch.qty','grn_batch.damage_qty')
                                    ->where('grnlist_id', $value->id)->get();

                        //dd($grnBatch);

                        if(!empty($grnBatch)){
                         foreach ($grnBatch as $grnBtch) {
                            $grnRequestQty = 0;
                            /*Stock entry with batch start*/
                            $grnRequestQty = $grnBtch->qty + $grnBtch->damage_qty;
                            // try{

                            $data = [ 'variant_sku' => $value->Items->sku,'sku_code' => $value->sku_code,'barcode'=> $value->barcode ,'item_id' => $value->Items->item_id, 'store_id' => $value->store_id, 'stock_point_id' => $stock_point_id,'request_qty' => $grnRequestQty, 'qty' =>  $grnBtch->qty, 'ref_stock_point_id' => 0, 'grn_id' => $grn->id, 'batch_id' => $grnBtch->batch_id, 'batch_code' => $grnBtch->batch_code, 'serial_id' => $is_serial, 'v_id' => $v_id, 'lost_qty' => $value->lost_qty, 'damage_qty' => $grnBtch->damage_qty, 'type' => 'equal', 'vu_id'=>$vu_id,'transaction_scr_id'=>$grn->id,'transaction_type' => 'GRN', 'short_qty' => '0', 'excess_qty' => '0', 'status' => 'POST' ];
                            // dd($data);
                            $stockRequest = new \Illuminate\Http\Request();
                            $stockRequest->merge([ 'v_id' => $v_id, 'stockData' => $data, 'store_id' => $store_id, 'trans_from' => $trans_from, 'vu_id' => $vu_id ]);
                            // dd($stockRequest->all());
                            $stockCon->stockEntry($stockRequest);
                            // DB::commit();
                            // } catch (Exception $e) {
                            // DB::rollback();
                            // exit;
                            // }
                            /*Stock entry with batch end*/
                            GrnBatch::where([ 'grnlist_id' => $value->id, 'batch_id' => $grnBtch->batch_id ])->update([ 'move_qty' => $grnRequestQty, 'batch_code' => $grnBtch->batch_code ]);
                        }
                      }
                        // dd($grnBatch);
                        // $grnBatch->move_qty = $value->qty;
                        // $grnBatch->save();
                        // $grnBatch->move_qty = $value->qty;
                        // $grnBatch->save();
                        //$is_batch = $grnBatchMapping->batch_id;
                    }
                    // Check if serial 
                    
                    $getItemDetails = VendorSkuDetails::where([ 'v_id' => $v_id, 'sku_code' => $value->sku_code ])->first();
                    $getItemDetails->barcode = $value->barcode;

                      if($value->is_serial == 1) {
                        $grnSerial = GrnSerial::where('grnlist_id', $value->id)->get();
                        if($grnSerial) {
                        foreach($grnSerial as $grnsrl) {
                            $serialDamageQty = $grnsrl->is_damage == 1 ? 1 : 0;
                             GrnSerial::where([ 'grnlist_id' => $value->id, 'serial_code' => $grnsrl->serial_code])->update([ 'is_moved' => 1 ]);

                            /*Stock entry with serial start*/
                            $data = [ 'variant_sku' => $value->Items->sku,'sku_code' => $value->sku_code,'barcode'=> $value->barcode ,'item_id' => $value->Items->item_id, 'store_id' => $value->store_id, 'stock_point_id' => $stock_point_id,'request_qty' => '1', 'qty' =>  '1', 'ref_stock_point_id' => 0, 'grn_id' => $grn->id, 'batch_id' => '0', 'serial_id' => $grnsrl->serial_id,'serial_code'=>$grnsrl->serial_code   , 'v_id' => $v_id, 'lost_qty' => $value->lost_qty, 'damage_qty' => $serialDamageQty, 'type' => 'equal', 'vu_id'=>$vu_id,'transaction_scr_id'=>$grn->id,'transaction_type' => 'GRN', 'short_qty' => '0', 'excess_qty' => '0', 'status' => 'POST' ];
                            $stockRequest = new \Illuminate\Http\Request();
                            $stockRequest->merge([
                            'v_id'          => $v_id,
                            'stockData'     => $data,
                            'store_id'      => $store_id,
                            'trans_from'    => $trans_from,
                            'vu_id'         => $vu_id
                            ]);
                            // dd($stockRequest->all());
                            $stockCon->stockEntry($stockRequest);
                            /*Stock entry with serial end*/


                        }
                          //$grnSerial->is_moved = 1;
                          //$grnSerial->save();
                        // $is_serial = $grnSerial->serial_id;
                        }
                    }

                    /*if($value->qty > $value->request_qty){
                        $qty = $value->request_qty;
                    }else{
                        $qty = $value->qty;
                    }*/

                    
                    // $data = [ 'variant_sku' => $value->Items->sku, 'item_id' => $value->Items->item_id, 'store_id' => $value->store_id, 'stock_point_id' => $stock_point_id,'request_qty' => $value->request_qty, 'qty' =>  $value->qty, 'ref_stock_point_id' => 0, 'grn_id' => $grn->id, 'batch_id' => $is_batch, 'serial_id' => $is_serial, 'v_id' => $v_id, 'lost_qty' => $value->lost_qty, 'damage_qty' => $value->damage_qty, 'type' => $type,'transaction_type' => 'GRN' ];
                if($value->is_serial == '0' && $value->is_batch == '0' ){
                    $data = [ 'variant_sku' => $value->Items->sku,'sku_code' => $value->sku_code,'barcode'=> $value->barcode ,'item_id' => $value->Items->item_id, 'store_id' => $value->store_id, 'stock_point_id' => $stock_point_id,'request_qty' => $value->request_qty, 'qty' =>  $value->qty, 'ref_stock_point_id' => 0, 'grn_id' => $grn->id, 'batch_id' => $is_batch, 'serial_id' => $is_serial, 'v_id' => $v_id, 'lost_qty' => $value->lost_qty, 'damage_qty' => $value->damage_qty, 'type' => $type, 'vu_id'=>$vu_id,'transaction_scr_id'=>$grn->id,'transaction_type' => 'GRN', 'short_qty' => $value->short_qty, 'excess_qty' => $value->excess_qty, 'status' => 'POST' ];
                    $stockRequest = new \Illuminate\Http\Request();
                    $stockRequest->merge([
                        'v_id'          => $v_id,
                        'stockData'     => $data,
                        'store_id'      => $store_id,
                        'trans_from'    => $trans_from,
                        'vu_id'         => $vu_id
                    ]);
                    // dd($stockRequest->all());
                    $stockCon->stockEntry($stockRequest);
                }
                // if(($value->is_serial == '1' || $value->is_batch == '1') && ($value->excess_qty >0 || $value->short_qty >0)){
                //     $data = [ 'variant_sku' => $value->Items->sku,'sku_code' => $value->sku_code,'barcode'=> $value->barcode ,'item_id' => $value->Items->item_id, 'store_id' => $value->store_id, 'stock_point_id' => $stock_point_id,'request_qty' => $value->request_qty, 'qty' => '0', 'ref_stock_point_id' => 0, 'grn_id' => $grn->id, 'batch_id' => $is_batch, 'serial_id' => $is_serial, 'v_id' => $v_id, 'lost_qty' => $value->lost_qty, 'damage_qty' => $value->damage_qty, 'type' => $type, 'vu_id'=>$vu_id,'transaction_scr_id'=>$grn->id,'transaction_type' => 'GRN', 'short_qty' => $value->short_qty, 'excess_qty' => $value->excess_qty, 'status' => 'POST' ];
                //     $stockRequest = new \Illuminate\Http\Request();
                //     $stockRequest->merge([
                //         'v_id'          => $v_id,
                //         'stockData'     => $data,
                //         'store_id'      => $store_id,
                //         'trans_from'    => $trans_from,
                //         'vu_id'         => $vu_id
                //     ]);
                //     // dd($stockRequest->all());
                //     $stockCon->stockEntry($stockRequest);
                // }
                $grn->is_imported = 1;
                $grn->save();
                }
            }

        //     DB::commit();
            
        // } catch (Exception $e) {
        //     DB::rollback();
        //     exit;
        // }

    }

    public function entryGrn(Request $request, $type = 'normal') 
    {
        if($type == 'adhoc') {
            $supplier = NewSupplier::where('id', trim($request->supplier))->select('id')->first();
            if(!$supplier) {
                $supplier = new Supplier;
                $supplier->name = $request->supplier;
                $supplier->v_id = $request->v_id;
                $supplier->save();
            }
            $insertData = Grn::create([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'grn_no' => grn_no_generate($request->v_id, $request->trans_from), 'qty' => 0, 'subtotal' => 0, 'discount' => 0, 'tax' => 0, 'charges' => 0, 'total' => 0, 'supplier_id' => $supplier->id, 'origin_from' => $request->place, 'remarks' => $request->remarks, 'grn_from' => 'ADHOC' ]);
            $supplier_id=$supplier->id;
        } else {
            $adviceData = Advise::find($request->advice_id);
            $insertData = Grn::create([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'grn_no' => grn_no_generate($request->v_id, $request->trans_from), 'advice_id' => $request->advice_id, 'request_qty' => $adviceData->qty, 'qty' => 0, 'subtotal' => 0, 'discount' => 0, 'tax' => 0, 'charges' => 0, 'total' => 0, 'supplier_id' => $adviceData->supplier_id, 'origin_from' => $adviceData->origin_from, 'requested_packets' => $adviceData->no_of_packets ]);
            $supplier_id=$adviceData->supplier_id;
        }
        return response()->json([ "status" => 'grn_entry', 'grn_id' => $insertData->id,'supplier'=>$supplier_id  ]);
    }

    //inbound api for grn create 
    public function create(Request $request){

        
        if (!$request->isJson()) {

//            try{

                $data = $request->json()->all();
                $client = oauthUser($request);
                $client_id = $client->client_id;
                $clients   = $client->id;
                $custom_valiation = [];
                $validation = [
                    'organisation_code' => 'required',
                    'goods_receipt_doc_no' => 'required',
                    'dest_site_code' => 'required',
                    'created_date' => 'date_format:Y-m-d',
                    'supplier_code'=>'required',
                    'item_list' => 'array',
                    'item_list.*.grn_detail_no' => 'required',
                    'item_list.*.item_sku_code' => 'required',
                    'item_list.*.item_barcode' => 'required',
                    //'item_list.*.is_batch' => 'required|in:Yes,true,No,false',
                    'item_list.*.supply_price' => 'numeric',
                    'item_list.*.is_batch' => 'required|boolean',
                    'item_list.*.is_serial' => 'required|boolean',
                    'item_list.*.qty' => 'required',
                    'item_list.*.batch_details.*.batch_no' => 'required_if:item_list.*.is_batch,1,true',
                    'item_list.*.batch_details.*.qty' => 'required_if:item_list.*.is_batch,1,true',
                    'item_list.*.batch_details.*.unit_mrp' => 'required_if:item_list.*.is_batch,1,true',
                    'item_list.*.serial_list.*.serial_no' => 'required_if:item_list.*.is_serial,1,true',
                ];

                if($clients==1){
                    //For Ginesys ref_item_code is sku code
                    $messages = [
                            'item_list.*.item_sku_code.exists' => 'Item sku code does not Exists'
                            ];
                    $custom_valiation = ['item_list.*.item_sku_code' => 'required|exists:vendor_items,ref_item_code,deleted,NULL',
                        ];
                }else{
                    $messages = [
                            'item_list.*.item_barcode.exists' => 'Item Barcode does not Exists'
                            ];
                    $custom_valiation = ['item_list.*.item_barcode' => 'required|exists:vendor_sku_detail_barcodes,barcode,deleted_at,NULL',
                        ];
                }
                
                $validation = array_merge($validation, $custom_valiation);

                /** @var \Illuminate\Contracts\Validation\Validator $validation **/
                $validator = Validator::make($data,$validation,$messages);

                $vendor = Organisation::select('id')->where('ref_vendor_code', $data['organisation_code'])->first();

                if(!$vendor){

                    $error_list =  [ 
                        [ 'error_for' =>  'organisation_code' ,  'messages' => ['Unable to find This Organisation'] ] 
                    ]; 
                    return response()->json([ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' =>  $error_list] , 422);
                }

                $v_id = $vendor->id;

                $asyn = new InboundApi;
                $asyn->client_id = $client_id;
                $asyn->v_id = $v_id;
                $asyn->request = json_encode($data);
                $asyn->job_class = '';
                $asyn->api_name = 'client/ad-hoc-grn/create';
                $asyn->api_type = 'SYNC';
                $asyn->ack_id = '';
                $asyn->status = 'PENDING'; // PENDING|FAIL|SUCCESS
                $asyn->save();

                if($validator->fails()){

                    $error_list = [];
                    foreach($validator->messages()->get('*') as $key => $err){
                        $error_list[] = [ 'error_for' => $key , 'messages' => $err ];  
                    }
                    $response = [ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' => $error_list ];
                    $asyn->status = 'FAIL';
                    $asyn->response = json_encode($response);
                    $asyn->save();
                    return response()->json( $response, 422);
                }

                $dest_site_code=  $data['dest_site_code'];
                $store = Store::select('store_id','short_code')->where('v_id', $v_id)->where('store_reference_code', $dest_site_code)->first();
                
                if(!$store){
                    
                    $error_list =  [ 
                           [ 'error_for' =>  'dest_site_code' , 'messages' => ['Unable to find This dest_site_code'] ]
                    ]; 

                    $response = [ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' => $error_list  ];
                    $asyn->status = 'FAIL';
                    $asyn->response = json_encode($response);
                    $asyn->save();

                    return response()->json( $response, 422);
                }
                $reference_code=$data['supplier_code'];
                $supplier = NewSupplier::select('id','reference_code','trade_name','legal_name')->where('v_id', $v_id)->where('reference_code', $reference_code)->first();
                if(!$supplier){

                    $error_list =  [ 
                        [ 'error_for' =>  'supplier_code' ,  'messages' => ['Supplier code does not exist'] ] 
                    ]; 
                    return response()->json([ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' =>  $error_list] , 422);
                }
                $supplier_id =$supplier->id;
                $store_id = $store->store_id;
                

                $newItemList = [];
                $return_qty = 0;
                $grn_supply_price = 0;
                $grn_subtotal = 0;
                $grn_discount = 0;
                $grn_tax = 0;
                $grn_total= 0;
                $grn_charge=0;
                $item_list = $data['item_list'];
                dd($item_list);
                

            /*}catch( \Exception $e ) {
                Log::error($e);
                return response()->json([ 'status' => 'fail' , 'message' => 'Server Error'   ] , 500);
            }*/

        }

    }

    public function newStockInGrn(Request $request)
    {
        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $trans_from = $request->trans_from;
        $vu_id = $request->vu_id;
        $grn_id = $request->grn_id;
        $stock_point_id = $request->stock_point_id;
        $data = [];
        $grn = Grn::where([ 'id' => $grn_id, 'v_id' => $v_id ])->first();
        $grnList = GrnList::where([ 'grn_id' => $grn_id, 'v_id' => $v_id, 'store_id' => $store_id ])->skip($request->skip)->take($request->take)->get();
        $stockCon = new StockController;

        // DB::beginTransaction();

        // try {
            //dd($grn->grnlist);die;
            foreach ($grnList->chunk(50) as  $grnlist) {
                foreach ($grnlist as $key => $value) {

                    $type = 'equal';

                    $is_batch = $is_serial = 0;

                    // Check Negative

                    // $totalQty = $value->request_qty - ($value->qty + $value->damage_qty + $value->lost_qty);
                    if($value->short_qty != '0.0') {
                        $type = 'short';
                    }

                    if($value->excess_qty != '0.0') {
                        $type = 'excess';
                    }                

                    // dd($value->short_qty);

                    // Check if batch 
                  
                    if($value->is_batch == 1) {
                        $grnBatch = GrnBatch::join('batch','batch.id','grn_batch.batch_id')
                                    ->select('batch.id as batch_id','batch.batch_code','grn_batch.qty','grn_batch.damage_qty')
                                    ->where('grnlist_id', $value->id)->get();

                        //dd($grnBatch);

                        if(!empty($grnBatch)){
                         foreach ($grnBatch as $grnBtch) {
                            $grnRequestQty = 0;
                            /*Stock entry with batch start*/
                            $grnRequestQty = $grnBtch->qty + $grnBtch->damage_qty;
                            // try{

                            $data = [ 'variant_sku' => $value->Items->sku,'sku_code' => $value->sku_code,'barcode'=> $value->barcode ,'item_id' => $value->Items->item_id, 'store_id' => $value->store_id, 'stock_point_id' => $stock_point_id,'request_qty' => $grnRequestQty, 'qty' =>  $grnBtch->qty, 'ref_stock_point_id' => 0, 'grn_id' => $grn->id, 'batch_id' => $grnBtch->batch_id, 'batch_code' => $grnBtch->batch_code, 'serial_id' => $is_serial, 'v_id' => $v_id, 'lost_qty' => $value->lost_qty, 'damage_qty' => $grnBtch->damage_qty, 'type' => 'equal', 'vu_id'=>$vu_id,'transaction_scr_id'=>$grn->id,'transaction_type' => 'GRN', 'short_qty' => '0', 'excess_qty' => '0', 'status' => 'POST' ];
                            // dd($data);
                            $stockRequest = new \Illuminate\Http\Request();
                            $stockRequest->merge([ 'v_id' => $v_id, 'stockData' => $data, 'store_id' => $store_id, 'trans_from' => $trans_from, 'vu_id' => $vu_id ]);
                            // dd($stockRequest->all());
                            $stockCon->stockEntry($stockRequest);
                            // DB::commit();
                            // } catch (Exception $e) {
                            // DB::rollback();
                            // exit;
                            // }
                            /*Stock entry with batch end*/
                            GrnBatch::where([ 'grnlist_id' => $value->id, 'batch_id' => $grnBtch->batch_id ])->update([ 'move_qty' => $grnRequestQty, 'batch_code' => $grnBtch->batch_code ]);
                        }
                      }
                        // dd($grnBatch);
                        // $grnBatch->move_qty = $value->qty;
                        // $grnBatch->save();
                        // $grnBatch->move_qty = $value->qty;
                        // $grnBatch->save();
                        //$is_batch = $grnBatchMapping->batch_id;
                    }
                    // Check if serial 
                    
                    $getItemDetails = VendorSkuDetails::where([ 'v_id' => $v_id, 'sku_code' => $value->sku_code ])->first();
                    $getItemDetails->barcode = $value->barcode;

                      if($value->is_serial == 1) {
                        $grnSerial = GrnSerial::where('grnlist_id', $value->id)->get();
                        if($grnSerial) {
                        foreach($grnSerial as $grnsrl) {
                            $serialDamageQty = $grnsrl->is_damage == 1 ? 1 : 0;
                             GrnSerial::where([ 'grnlist_id' => $value->id, 'serial_code' => $grnsrl->serial_code])->update([ 'is_moved' => 1 ]);

                            /*Stock entry with serial start*/
                            $data = [ 'variant_sku' => $value->Items->sku,'sku_code' => $value->sku_code,'barcode'=> $value->barcode ,'item_id' => $value->Items->item_id, 'store_id' => $value->store_id, 'stock_point_id' => $stock_point_id,'request_qty' => '1', 'qty' =>  '1', 'ref_stock_point_id' => 0, 'grn_id' => $grn->id, 'batch_id' => '0', 'serial_id' => $grnsrl->serial_id,'serial_code'=>$grnsrl->serial_code   , 'v_id' => $v_id, 'lost_qty' => $value->lost_qty, 'damage_qty' => $serialDamageQty, 'type' => 'equal', 'vu_id'=>$vu_id,'transaction_scr_id'=>$grn->id,'transaction_type' => 'GRN', 'short_qty' => '0', 'excess_qty' => '0', 'status' => 'POST' ];
                            $stockRequest = new \Illuminate\Http\Request();
                            $stockRequest->merge([
                            'v_id'          => $v_id,
                            'stockData'     => $data,
                            'store_id'      => $store_id,
                            'trans_from'    => $trans_from,
                            'vu_id'         => $vu_id
                            ]);
                            // dd($stockRequest->all());
                            $stockCon->stockEntry($stockRequest);
                            /*Stock entry with serial end*/


                        }
                          //$grnSerial->is_moved = 1;
                          //$grnSerial->save();
                        // $is_serial = $grnSerial->serial_id;
                        }
                    }

                    /*if($value->qty > $value->request_qty){
                        $qty = $value->request_qty;
                    }else{
                        $qty = $value->qty;
                    }*/

                    
                    // $data = [ 'variant_sku' => $value->Items->sku, 'item_id' => $value->Items->item_id, 'store_id' => $value->store_id, 'stock_point_id' => $stock_point_id,'request_qty' => $value->request_qty, 'qty' =>  $value->qty, 'ref_stock_point_id' => 0, 'grn_id' => $grn->id, 'batch_id' => $is_batch, 'serial_id' => $is_serial, 'v_id' => $v_id, 'lost_qty' => $value->lost_qty, 'damage_qty' => $value->damage_qty, 'type' => $type,'transaction_type' => 'GRN' ];
                if($value->is_serial == '0' && $value->is_batch == '0' ){
                    $data = [ 'variant_sku' => $value->Items->sku,'sku_code' => $value->sku_code,'barcode'=> $value->barcode ,'item_id' => $value->Items->item_id, 'store_id' => $value->store_id, 'stock_point_id' => $stock_point_id,'request_qty' => $value->request_qty, 'qty' =>  $value->qty, 'ref_stock_point_id' => 0, 'grn_id' => $grn->id, 'batch_id' => $is_batch, 'serial_id' => $is_serial, 'v_id' => $v_id, 'lost_qty' => $value->lost_qty, 'damage_qty' => $value->damage_qty, 'type' => $type, 'vu_id'=>$vu_id,'transaction_scr_id'=>$grn->id,'transaction_type' => 'GRN', 'short_qty' => $value->short_qty, 'excess_qty' => $value->excess_qty, 'status' => 'POST' ];
                    $stockRequest = new \Illuminate\Http\Request();
                    $stockRequest->merge([
                        'v_id'          => $v_id,
                        'stockData'     => $data,
                        'store_id'      => $store_id,
                        'trans_from'    => $trans_from,
                        'vu_id'         => $vu_id
                    ]);
                    // dd($stockRequest->all());
                    $stockCon->stockEntry($stockRequest);
                }
                // if(($value->is_serial == '1' || $value->is_batch == '1') && ($value->excess_qty >0 || $value->short_qty >0)){
                //     $data = [ 'variant_sku' => $value->Items->sku,'sku_code' => $value->sku_code,'barcode'=> $value->barcode ,'item_id' => $value->Items->item_id, 'store_id' => $value->store_id, 'stock_point_id' => $stock_point_id,'request_qty' => $value->request_qty, 'qty' => '0', 'ref_stock_point_id' => 0, 'grn_id' => $grn->id, 'batch_id' => $is_batch, 'serial_id' => $is_serial, 'v_id' => $v_id, 'lost_qty' => $value->lost_qty, 'damage_qty' => $value->damage_qty, 'type' => $type, 'vu_id'=>$vu_id,'transaction_scr_id'=>$grn->id,'transaction_type' => 'GRN', 'short_qty' => $value->short_qty, 'excess_qty' => $value->excess_qty, 'status' => 'POST' ];
                //     $stockRequest = new \Illuminate\Http\Request();
                //     $stockRequest->merge([
                //         'v_id'          => $v_id,
                //         'stockData'     => $data,
                //         'store_id'      => $store_id,
                //         'trans_from'    => $trans_from,
                //         'vu_id'         => $vu_id
                //     ]);
                //     // dd($stockRequest->all());
                //     $stockCon->stockEntry($stockRequest);
                // }
                $grn->is_imported = 1;
                $grn->save();
                }
            }

        //     DB::commit();
            
        // } catch (Exception $e) {
        //     DB::rollback();
        //     exit;
        // }

        if($request->has('stock_count')) {
            if($request->stock_count == 0) {
                return response()->json([ 'status' => 'posted' ]);
            } else {
                return response()->json([ 'status' => 'remaining_stock', 'remaining_stock' => $request->take ]);
            }
        }

    }

}
