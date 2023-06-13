<?php

use Illuminate\Database\Seeder;

use App\Store;
use App\Model\Stock\StockLogs;
use App\Model\Stock\StockPointSummary;


class stockPointSummaryUpdateFromStocklog extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        
       $v_id     = 38;  //vendor_sku_details

       // update stock_logs  set batch_id = 0 , batch_code =null where v_id  =38; 
       
       // dynamicConnectionNew($v_id);
       JobdynamicConnection($v_id);
       StockPointSummary::where('v_id',$v_id)->delete();
       //$store_id = 39; 
       $stores   = DB::table('stores')->where('v_id',$v_id)->orderBy('store_id','desc')->get();
       foreach($stores as $store){
        $storeId = $store->store_id;
        $stockPoints  = DB::table('stock_points')
                        ->where('v_id',$v_id)->where('store_id',$storeId)->orderBy('id','desc')->get();

        foreach ($stockPoints as $point) {
           $total_qty = 0;
           $stockQty  = DB::table('stock_logs')->select('sku_code','batch_id','item_id','serial_id','serial_code','batch_code','variant_sku',DB::raw('sum(qty) as total_qty'))
                      ->where('v_id',$v_id)->where('store_id',$storeId)
                      ->where('stock_point_id',$point->id)->groupBy('variant_sku','batch_id')->get();

            //dd($stockQty);
            if(!$stockQty->isEmpty()){
                foreach ($stockQty as $value) {
                  $barcode = DB::table('vendor_sku_detail_barcodes')->where('v_id',$v_id)->where('sku_code',$value->sku_code)->first();
                  //if($barcode){
                    $total_qty = $value->total_qty;
                    $data = array('v_id'=>$v_id,'store_id'=>$storeId,'stock_point_id'=>$point->id,'item_id'=>$value->item_id,'variant_sku'=>$value->variant_sku,'barcode'=>isset($barcode->barcode)?$barcode->barcode:0,'qty'=>$total_qty,'sku_code'=>$value->sku_code,'batch_id'=> !empty($value->batch_id)?$value->batch_id:0,'serial_id'=> !empty($value->serial_id)?$value->serial_id:0,'batch_code'=>$value->batch_code,'serial_code'=> $value->serial_code, 'active_status' => '1');
                    StockPointSummary::create($data);
                  //}
                }
            }          
        }
      } 
    
    }
}
