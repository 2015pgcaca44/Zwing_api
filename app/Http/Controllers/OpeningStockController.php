<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use Auth;
use Event;
use App\Model\Stock\OpeningStock; 
use App\Model\Stock\OpeningStockDetails;
use App\Store;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\StockController;
use App\Events\CreateOpeningStock;


class OpeningStockController extends Controller
{
    public function __construct()
    {
       // $this->middleware('auth',['except' => ['printGrn']]);
    }
    ############### Start OpENING sTOCK Api ########################
    public function createOpeningStock(Request $request) 
    {

        
        if($request->has('opening_stock_id') && !empty($request->opening_stock_id) && $request->opening_stock_id != 0) {
            if(is_numeric($request->opening_stock_id)) {
                $osData = OpeningStock::find($request->opening_stock_id);
            } else {
                return response()->json(["status" => 'fail' ,'message' => 'Opening Stock  ID is not number' ]);
            }
        } else {
            return $this->entryOpeningStock($request);
        }
        $v_id       = $request->v_id;
        $vu_id      = $request->vu_id;
        $item_list = json_decode($request->item_list);
        $opening_stock_id=$request->opening_stock_id;
        $list_count = $request->list_count;
        $os_list_where = [ 'v_id' => $v_id,'opening_stock_id' => $opening_stock_id, 'vu_id' => $vu_id ];

        // DB::beginTransaction();

        try {

            foreach ($item_list as $item) {

                    //create opening stock    
                    $openingStockDetails=[
                                        'v_id' =>$v_id,
                                        'vu_id'=>$vu_id,
                                        'item_id'=>$item->item_id, 
                                        'store_id' => $item->store_id, 
                                        'barcode' => $item->barcode, 
                                        'quantity' => $item->qty, 
                                        'supply_price' => (float)$item->supplyPrice, 
                                        'sub_total' => (float)$item->subTotal,
                                        'total_tax'=>(float)$item->totalTax,
                                        'total_amount'=>(float)$item->totalAmount,
                                        'opening_stock_id'=>$opening_stock_id, 
                                    ];
                    $os_details   = OpeningStockDetails::create($openingStockDetails); 
                    if($request->has('save_as') && $request->save_as=='save_as_confirm') {
                        //save last inward price 
                        $itemCon = new ItemController;
                        $rateRequest = new \Illuminate\Http\Request();
                        $rateRequest->merge([
                            'v_id' =>$v_id,
                            'source_site_id' => $os_details['store_id'],
                            'destination_site_id' => $os_details['store_id'],
                            'source_site_type' => 'store',
                            'destination_site_type'=>'store',
                            'item_id' => $os_details['item_id'],
                            'barcode' => $os_details['barcode'],
                            'supply_price' => trim($os_details['supply_price']),
                            'discount' => 0.00,
                            'discount_details' => '',
                            'tax' =>$os_details['total_tax'],
                            'tax_details'=>isset($item->tax_details)?json_encode($item->tax_details):'',
                            'source_transaction_id' => $os_details['id'],
                            'source_transaction_type' => 'OPN',
                        ]);
                        $itemCon->saveLastInwardPrice($rateRequest);
                        $stockCon = new StockController;
                        $stockRequest = new \Illuminate\Http\Request();
                        $stockInData    =[
                                            'v_id' =>$v_id,
                                            'vu_id' =>$vu_id,
                                            'variant_sku' => $item->variant_sku,
                                            'sku_code' => $item->sku_code,
                                            'barcode' => $item->barcode,
                                            'item_id' => $item->item_id,
                                            'store_id' => $item->store_id,
                                            'stock_point_id' => $item->stock_point_id,
                                            'qty' => $item->qty,
                                            'ref_stock_point_id' => 0,
                                            'batch_id' => $item->batch_id,
                                            'serial_id' => 0,
                                            'case_type'=>'OPN',
                                            'transaction_scr_id' => $opening_stock_id,
                                            'transaction_type' => 'OPN',
                                            'stock_type'=>'IN',
                                            'status'=>'POST'
                                        ];     

                        $stockRequest->merge([
                                            'v_id' =>$v_id,
                                            'vu_id' =>$vu_id,
                                            'store_id' => $item->store_id,
                                            'trans_from' => 'abc',
                                            'stockData'=> $stockInData,
                                         ]);                  
                        $stockCon->stockIn($stockRequest);
                    }
            }
            // DB::commit();

            $tempCount = OpeningStockDetails::where($os_list_where)->count();
            if($list_count == $tempCount && $request->save_as=='save_as_confirm') {
                if($opening_stock_id){
                    OpeningStock::where('id', $opening_stock_id)->update([ 'status' => 'Complete' ]);
                    $zwingTagVId = '<ZWINGV>'.$v_id.'<EZWINGV>';
                    $zwingTagStoreId = '<ZWINGSO>'.$osData->store_id.'<EZWINGSO>';
                    $zwingTagTranId = '<ZWINGTRAN>'.$osData->id.'<EZWINGTRAN>';
                 event(new CreateOpeningStock(['v_id' => $v_id, 'store_id' =>$osData->store_id, 'os_id' =>$osData->id, 'zv_id' => $zwingTagVId, 'zs_id' => $zwingTagStoreId, 'zt_id' => $zwingTagTranId] ) );
                }
                return response()->json(["status" => 'success' ,'message' => 'Opening Stock  Created successfully']);

            }elseif ($list_count == $tempCount) {
                return response()->json(["status" => 'success' ,'message' => 'Opening Stock  Created successfully']);
            } else {
                    $remaining_list = $list_count - $tempCount;
                    return response()->json(["status" => 'continue' , 'remaining' => $remaining_list]);
            }

        } catch (Exception $e) {
            // DB::rollback();
            exit;
        }   
        

    }    

    public function entryOpeningStock(Request $request) 
    {
         
            
        $stock_summary = json_decode($request->stock_summary);
        $checkExistance = OpeningStock::where('store_id',$stock_summary->store_id)->where('stock_point_id',$stock_summary->stock_point_id)
                                               ->where('status','Complete')->where('v_id',$request->v_id)->exists();
        if($checkExistance){
            return response()->json([
                                'status' => 'fail',
                                'message' => 'Opening Stock already done for this store and stock point'
                            ]);
        }
        if(!isset($stock_summary->store_id) && empty($stock_summary->store_id) || !isset($stock_summary->stock_point_id) && empty($stock_summary->stock_point_id) || !isset($stock_summary->qty) && empty($stock_summary->qty) || $stock_summary->supplyPrice == 0 && empty($stock_summary->item_id)){

            return response()->json([
                                'status' => 'fail',
                                'message' => 'Fields can not be zero or missing parameter'
                            ]);
                        
        }else{

            $exists = OpeningStock::select('id')->where('v_id',$request->v_id)->where('store_id', $stock_summary->store_id)
                        ->where('stock_point_id', $stock_summary->stock_point_id)->first();
            if(!empty($exists) ){                    
            OpeningStock::where('id', $exists->id)->delete();
            OpeningStockDetails::where('opening_stock_id', $exists->id)->delete();
            }  
            $openingStock=[ 
                            'v_id' =>$request->v_id,
                            'vu_id'=>$request->vu_id,
                            'code'=>$this->generateCode($stock_summary->store_id),
                            'store_id'=>$stock_summary->store_id, 
                            'stock_point_id' => $stock_summary->stock_point_id, 
                            'qty' => $stock_summary->qty, 
                            'supply_price' => $stock_summary->supplyPrice,
                            'subtotal' => $stock_summary->subTotal,
                            'tax'=>(float)$stock_summary->tax,
                            'total'=>$stock_summary->total,
                            'total_item'=>$stock_summary->total_item, 
                            'status'=>$request->save_as=='save_as_confirm'?'Pending':'Draft',
                            'updated_by'=>$request->vu_id,
                            ];
                
            $OpeningStockId   = OpeningStock::create($openingStock);
        
        return response()->json([ "status" => 'openingstock_entry', 'opening_stock_id' => $OpeningStockId->id  ]);
        }
    }

    private function generateCode($store_id) {
     $store = Store::select('short_code')      
                            ->where('store_id',$store_id)
                            ->first();
               $c_date =date('dmy');
     $number =  'OS'.$store->short_code.$c_date.$this->codeIncrementNo($store_id);
     return $number;
    }


    private function codeIncrementNo($store_id){
           $inc_no = '0001';
           $currentdate = date('Y-m-d');
           $lastTranscation=OpeningStock::orderBy('id','DESC')
                           ->first();     
            $count =  OpeningStock::count();
          if(!empty($lastTranscation) && $count !=0)
          {
            $n  = strlen($inc_no);
                  $current_id = substr($lastTranscation->code,-$n);
                  $inc=++$current_id;
                $inc_no =str_pad($inc,$n,"0",STR_PAD_LEFT);
          }else{
           $inc_no = '0001';
          }
          return $inc_no;
    }


}
