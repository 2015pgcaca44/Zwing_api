<?php

namespace App\Http\Controllers\CloudPos;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VendorSettingController;
use App\Vendor\VendorRoleUserMapping;
// Vendor sku detail
use App\Model\Items\VendorSkuDetails;
use App\Model\Items\VendorSku;
use App\Model\Items\VendorItem;
use App\Model\Items\VendorSkuDetailBarcode;
use App\Model\Items\ItemMediaAttributeValues;
use App\Model\Stock\StockCurrentStatus;
use App\Model\Stock\StockTransactions;
use App\Model\Stock\StockLogs;
use App\Model\Stock\StockPoints;
use App\Model\Stock\StockIn;
use App\Model\Stock\StockOut;
use App\Http\Controllers\StockController;
use App\Model\Items\PriceBook;
use App\LastInwardPrice;
use App\Model\Item\ItemList;
use App\Model\Grn\GrnList;
use Auth;
use App\Model\Stock\StockPointSummary;
use Illuminate\Http\Request;
use App\VendorSetting;
use DB;

class CartconfigController extends Controller
{
    public function __construct()
    {
        //JobdynamicConnection(127);    
        $this->middleware('auth',['except' => ['getItemPrice','getItemSupplyPrice']]);
        $this->stock = new StockController;
    }

    /**** Start Get Item Price*****/
    public function getprice($params)
    {
        if (count($params) >0) {

            $v_id     = $params['v_id'];
            $store_id = $params['store_id'];
            $item     = $params['item'];
            $unit_mrp = $params['unit_mrp'];
            $priceId  = array();
            $priceList = null;
            
            //dd($item->vprice->where('store_id', $store_id));
          
            $vprice  = $item->vprice->where('v_id', $v_id)->where('variant_combi', $item->variant_combi);
           

            // dd($vprice);
            foreach ($vprice as $key => $value) {
              if($value->store_id !=0  && $value->price_book_id != 0){
                    //$priceId[] = $value->price->id;
                    if($value->store_id == $store_id){
                        $priceId[] = $value->price_book_id;
                    }
              }else{
                $priceList[] = $value;
              }
            }

            $priceValidId = 0;
            $timestamp    = date('Y-m-d');
            $priceBook    = PriceBook::whereIn('id',$priceId)
                            ->whereDate('effective_date','<=',$timestamp)
                            ->whereDate('valid_to','>=',$timestamp)
                            ->where('v_id',$v_id)
                            ->where('status','1')
                            ->orderBy('updated_at','desc')
                            ->orderBy('effective_date','desc')->first();
            if($priceBook){
                  $priceValidId = $priceBook->id;
            }                
            /*foreach($priceBook as $pvalue){
                if (!empty($pvalue->effective_date) && !empty($pvalue->valid_to)) {
                    $priceValidId = $pvalue->id;

                }
            }*/
            if($priceValidId != 0) {
                $priceList = $item->vprice->where('v_id', $v_id)->where('store_id',$store_id)->where('variant_combi', $item->variant_combi)->whereIn('price_book_id',$priceValidId );
            }else{
                $priceList = $item->vprice->where('v_id', $v_id)->where('store_id', 0)->where('price_book_id', 0)->where('variant_combi', $item->variant_combi);
                //dd($priceList);
            }


            /* Old Code Start*/
            $mrplist = array();
            foreach ($priceList as $mp) {
                $mrplist[] = array('mrp' => $mp->priceDetail->mrp, 'rsp' => $mp->priceDetail->rsp, 's_price' => $mp->priceDetail->special_price);
            }
            $mrplist  = collect($mrplist);
            // dd($mrplist);
            // echo $mrplist->max('mrp');die;
            if ($unit_mrp) {
                $mrpcurrent = $mrplist->where('mrp', $unit_mrp)->first();
                //dd($mrpcurrent);
            if(!$mrpcurrent){
                $unit_mrp =  $unit_mrp;
                $r_price  =  $unit_mrp; //  * $value[1];
                $s_price  =  $unit_mrp;
            }else{
                $unit_mrp =  $mrpcurrent['mrp'];
                $r_price  =  $mrpcurrent['rsp']; //  * $value[1];
                $s_price  =  !empty($mrpcurrent['s_price']) ? $mrpcurrent['s_price'] : $mrpcurrent['rsp'];
            }
                
            } else {
                $unit_mrp =  $mrplist->max('mrp');
                // $r_price  =  $mrplist->max('rsp');//  * $value[1];
                // $s_price  =  !empty($mrplist->max('s_price'))?$mrplist->max('s_price'):$mrplist->max('mrp');

                $mrpcurrent = $mrplist->where('mrp', $unit_mrp)->first();
                $unit_mrp =  $mrpcurrent['mrp'];
                $r_price  =  $mrpcurrent['rsp']; //  * $value[1];
                $s_price  =  !empty($mrpcurrent['s_price']) ? $mrpcurrent['s_price'] : $mrpcurrent['rsp'];
            }
            // * $value[1];
            $data     = '';
            $mrp_arrs = array();

            //$price_master->variantPrices
            // foreach ($priceList as $price) {
            //     $mrp_arrs[] =  format_number($price->priceDetail->mrp);
            // }
            // $multiple_mrp_flag  = ( count( $mrp_arrs) > 1 )? true:false;


            $mrp_arrs1 = $mrplist->map(function ($item) {
                
                $sprice = !empty($item['s_price'])?$item['s_price']:$item['rsp'];
                $sprice = !empty($sprice)?$sprice:$item['mrp'];
                return format_number($sprice);
            });
            //$mrp_arrs = $mrp_arrs->->get()->toArray();

            $mrp_arrs = $mrp_arrs1->toArray();

            $multiple_mrp_flag  = (count($mrp_arrs) > 1) ? true : false;
            $mrp       = (!empty($s_price) ? $s_price : $unit_mrp); //$price['unit_mrp'];

            // check unit_mrp priority wise from product settings
            // $vSettings = VendorSetting::select('settings')->where('name', 'product')->where('v_id', Auth::user()->v_id)->first();
            // $sett = json_decode($vSettings->settings);
            // if(isset($sett->default_price)) {
            //     if ($sett->default_price->mrp->DEFAULT->status) {
            //         $s_price = $mrpcurrent['mrp'];
            //         $unit_mrp = $mrpcurrent['mrp'];
            //     } elseif ($sett->default_price->rsp->DEFAULT->status) {
            //         $s_price = $mrpcurrent['rsp'];
            //         $unit_mrp = $mrpcurrent['rsp'];
            //     } elseif ($sett->default_price->selling_price->DEFAULT->status) {
            //         $s_price = $mrpcurrent['s_price'];
            //         $unit_mrp = $mrpcurrent['s_price'];
            //     }
            // }
            $return = array('unit_mrp' => $unit_mrp, 'r_price' => $unit_mrp, 's_price' => $s_price, 'mrp_arrs' => $mrp_arrs, 'multiple_mrp_flag' => $multiple_mrp_flag, 'mrp' => $mrp);
            // dd($return);
            return $return;
        } else {
            return false;
        }
    } //End of getprice
    /*########  Update Stock Of Existing Item ##########*/

    public function updateStockQty($parmas)
    {
        $v_id       = $parmas['v_id'];
        $barcode    = $parmas['barcode'];
        $qty        = $parmas['qty'];
        $invoice_id = $parmas['invoice_id'];
        $order_id   = $parmas['order_id'];
        $store_id   = $parmas['store_id'];
        $transaction_type = $parmas['transaction_type'];
        $transaction_scr_id = $parmas['transaction_scr_id'];
        $vu_id      = $parmas['vu_id'];
        $trans_from = $parmas['trans_from'];
        $todaydate  = date('Y-m-d');
        $status    = !empty($parmas['status'])?$parmas['status']:'POST';
        $batch_id    = !empty($parmas['batch_id'])?$parmas['batch_id']:0;
        $serial_id    = !empty($parmas['serial_id'])?$parmas['serial_id']:0;

        $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $barcode)->first();
        if($bar){
            $Item = VendorSku::select('sku','sku_code','item_id')->where(['vendor_sku_detail_id' => $bar->vendor_sku_detail_id, 'v_id' => $v_id])->first();    
            $Item->barcode = $bar->barcode;
        }
        if (!$Item) {
            $Item = VendorSku::select('sku','sku_code','item_id','vendor_sku_detail_id')->where(['sku' => $barcode, 'v_id' => $v_id])->first();
            $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('vendor_sku_detail_id', $Item->vendor_sku_detail_id)->first();
            $Item->barcode = $bar->barcode;
        }
        //dd($Item);
        if ($Item) {

            #### START INVENTORY CONDITION #####
            #### code added to handel allow inventory and negative inventory condition ###
            $vendorItem = VendorItem::select('track_inventory','negative_inventory','negative_inventory_override_by_store_policy')->where('v_id', $v_id)->where('item_id', $Item->item_id)->first();

            if($vendorItem->track_inventory == '0'){
                return response()->json(['status' => 'Fail', 'message' => 'Inventory Tracking is disabled for this item'], 200);
            }
            /*else{//IF inventory tracking enabled and negative inventory allowed then billing should happen without doing grn
                

                $vSetting = new VendorSettingController;
                $role_id = VendorRoleUserMapping::select('role_id')->where('user_id',$vu_id)->first()->role_id;

                $settingsArray = ['v_id'=> $v_id,'store_id'=>$store_id,'user_id'=>$vu_id,'role_id'=>$role_id,'name'=>'stock' , 'trans_from' => $trans_from];
                $stockSetting = json_decode($vSetting->getSetting($settingsArray)->pluck('settings')->toArray()[0]);
                $negative_billing_allowed = false;
                foreach($stockSetting as $setting){
                    if(isset($setting->negative_stock_billing) ){
                        if($setting->negative_stock_billing->status){
                            $negative_billing_allowed = true;
                            if($vendorItems->negative_inventory_override_by_store_policy == '1'){ 
                                if($vendorItems->negative_inventory == '1'){
                                    $negative_billing_allowed = true;
                                }else{
                                    $negative_billing_allowed = false;
                                }
                            }
                        }else{
                            $negative_billing_allowed = false;
                        }
                    }
                }
        
                

                if($negative_billing_allowed){

                    $stockPointExist = [];

                    $defaultStockPointExist = StockPoints::select('id')->where('store_id', $store_id)->where('v_id', $v_id)->where('is_active','1')->where('is_deleted','0')->where('is_default' , '1')->first();

                    $stockPointExist[] = $defaultStockPointExist->id;

                    $sellableStockPointExist = StockPoints::select('id')->where('store_id', $store_id)->where('v_id', $v_id)->where('is_active','1')->where('is_deleted','0')->where('is_sellable' , '1')->first();

                    $stockPointExist[] = $sellableStockPointExist->id;

                    foreach($stockPointExist as $stock_point_id){
                        $stock = StockPointSummary::select('qty','id')->where('store_id',$request->store_id)->where('item_id', $request->item_id)->where('barcode',$request->barcode)->where('v_id', $v_id)->where('stock_point_id', $stock_point_id)->first();

                        if(!$stock){
                            $newSummary = new StockPointSummary;
                            $newSummary->store_id = $request->store_id;
                            $newSummary->v_id = $v_id;
                            $newSummary->item_id = $value->item_id;
                            $newSummary->barcode = $value->barcode;
                            $newSummary->sku_code = $value->sku_code;
                            $newSummary->variant_sku = $value->sku;
                            $newSummary->stock_point_id = $stock_point_id;
                            $newSummary->qty = 0;
                            $newSummary->active_status = '1';
                            $newSummary->save();
                        }

                    }

                }
            }*/

            #### END INVENTORY CONDITION #####


            $whereStockCurrentStatus = array('sku_code' => $Item->sku_code, 'item_id' => $Item->item_id, 'store_id' => $store_id, 'v_id' => $v_id , 'batch_id' => $batch_id , 'serial_id' => $serial_id);
            $stockCurrentStatus = StockPointSummary::where($whereStockCurrentStatus)->orderBy('id', 'desc')->first();

            if ($stockCurrentStatus) {

            $stocktransdata     = array(
                'variant_sku' => $Item->sku,
                'sku_code'    => $Item->sku_code,
                'barcode'     => $barcode,
                'item_id'    => $Item->item_id,
                'store_id'   => $store_id,
                // 'stock_type' =>  $stock_type,
                // 'stock_point_id' => $stockpoint->id,
                'qty'        => $qty,
                'v_id'      =>  $v_id,
                'vu_id'    => $vu_id,
                'order_id'  =>  $order_id,
                'invoice_no' =>  $invoice_id,
                'transaction_type' => $transaction_type

            );

            //$stockTranID = StockTransactions::create($stocktransdata)->id;

 
            //$this->updateStockCurrentStatus($Item->sku, $Item->item_id, $qty, $v_id, $store_id,$transaction_type);

            /*$stockCurrentStatus->out_qty = $stockCurrentStatus->out_qty+$value['qty'];
            $stockCurrentStatus->save();*/


            if($transaction_type == 'SALE' ) {

                $stockpointwhere  = array('v_id' => $v_id, 'store_id' => $store_id, 'name' => 'SALE');
                $stockpoint = StockPoints::where($stockpointwhere)->first();
                if (!$stockpoint) {
                    $stockpoint = new StockPoints;
                    $stockpoint->v_id       = $v_id;
                    $stockpoint->store_id   = $store_id;
                    $stockpoint->name       = 'SALE';
                    $stockpoint->code       = 'SL001';
                    $stockPoint->is_active  = '1';
                    $stockpoint->save();
                }
                $whereRefPoint   = array('v_id' => $v_id, 'store_id' => $store_id,'is_sellable'=>'1');
                ######################
                ## Stock Log Update ##
                ######################
                $ref_stock_point = StockPoints::select('id')->where($whereRefPoint)->orderBy('id', 'desc')->first();
                if (!$ref_stock_point) {
                    return response()->json(['status' => 'error', 'message' => 'Sellable Stock Point Not Define'], 200);
                }
                if(array_key_exists('stock_point_id', $parmas)) {
                    $refStockPointId = $parmas['stock_point_id'];
                } else {
                    $refStockPointId = $ref_stock_point->id;
                }
            
                $stock_type = 'OUT';
                // $transaction_scr_id = $stockTranID;
                // StockTransactions::find($stockTranID)->update([ 'stock_type' => 'OUT', 'stock_point_id' => $refStockPointId, 'transaction_type' => 'SALE' ]);

                $data = [ 'variant_sku' => $Item->sku,'sku_code' => $Item->sku_code, 'barcode'=> $Item->barcode,'item_id' => $Item->item_id, 'store_id' => $store_id, 'stock_point_id' => $refStockPointId, 'qty' =>  $qty, 'ref_stock_point_id' =>$stockpoint->id, 'grn_id' => '', 'batch_id' => $batch_id, 'serial_id' => $serial_id, 'v_id' => $v_id,'vu_id'=>$vu_id,'type' => $stock_type,'transaction_type' => 'SALE','transaction_scr_id'=>$transaction_scr_id,'date'=>$todaydate,'status'=>'POST'];
                
                $stockRequest = new \Illuminate\Http\Request();
                $stockRequest->merge([
                    'v_id'          => $v_id,
                    'stockData'     => $data,
                    'store_id'      => $store_id,
                    'trans_from'    => $trans_from,
                    'vu_id'         => $vu_id,
                    'transaction_type' => 'SALE'
                ]);

                $this->stock->stockOut($stockRequest);
               
                /*Stock Out End*/

            }

            if($transaction_type == 'RETURN' ){
                $stockpointwhere  = array('v_id' => $v_id, 'store_id' => $store_id, 'is_sellable' => '1');
                $stockpoint = StockPoints::where($stockpointwhere)->first();
                $refStockPointId = 0;
                $stock_type = 'IN';
                //$transaction_scr_id = $stockTranID;
                //StockTransactions::find($stockTranID)->update([ 'stock_type' => 'IN', 'stock_point_id' => $refStockPointId, 'transaction_type' => 'RETURN' ]);
                // $data = [ 'variant_sku' => $Item->sku, 'barcode'=> $Item->barcode,'item_id' => $Item->item_id, 'store_id' => $store_id, 'stock_point_id' => $stockpoint->id, 'qty' =>  $qty, 'ref_stock_point_id' =>$refStockPointId, 'grn_id' => '', 'batch_id' => '', 'serial_id' => '', 'v_id' => $v_id,'vu_id'=>$vu_id,'type' => $stock_type,'transaction_type' => $transaction_type,'transaction_scr_id'=>$transaction_scr_id,'date'=>$todaydate,'status'=>'POST'];

                $data = [ 'variant_sku' => $Item->sku, 'sku_code' => $Item->sku_code, 'barcode'=> $Item->barcode,'item_id' => $Item->item_id, 'store_id' => $store_id, 'stock_point_id' => $stockpoint->id, 'qty' =>  $qty, 'ref_stock_point_id' =>$refStockPointId, 'grn_id' => '', 'batch_id' => $batch_id, 'serial_id' => $serial_id, 'v_id' => $v_id,'vu_id'=>$vu_id,'type' => $stock_type,'transaction_type' => $transaction_type,'transaction_scr_id'=>$transaction_scr_id,'date'=>$todaydate,'status' => $status ];
                
                $stockRequest = new \Illuminate\Http\Request();
                $stockRequest->merge([
                    'v_id'          => $v_id,
                    'stockData'     => $data,
                    'store_id'      => $store_id,
                    'trans_from'    => $trans_from,
                    'vu_id'         => $vu_id,
                    'transaction_type' => 'RETURN'
                ]);

                $this->stock->stockIn($stockRequest); 
            }

                //dd($ref_stock_point);
                /*$stockdata  = array(
                    'variant_sku' => $Item->sku,
                    'item_id'    => $Item->item_id,
                    'store_id'   => $store_id,
                    'stock_type' => $stock_type,
                    'stock_point_id' => $stockpoint->id,
                    'qty'        => $qty,
                    'ref_stock_point_id' => $refStockPointId,
                    'v_id'      =>  $v_id,
                    'transaction_type'      => $transaction_type
                );
                StockLogs::create($stockdata);*/

                #############################
                ##Stock Transaction update ##
                #############################

                // $stocktransdata     = array(
                //     'variant_sku' => $Item->sku,
                //     'barcode'     => $barcode,
                //      'item_id'    => $Item->item_id,
                //     'store_id'   => $store_id,
                //     'stock_type' =>  $stock_type,
                //     'stock_point_id' => $stockpoint->id,
                //     'qty'        => $qty,
                //     'v_id'      =>  $v_id,
                //     'vu_id'    => $vu_id,
                //     'order_id'  =>  $order_id,
                //     'invoice_no' =>  $invoice_id,
                //     'transaction_type' => $transaction_type

                // );
                // StockTransactions::create($stocktransdata);

                /* $stockCurrentdata = array('variant_sku'=> $Item->sku,
                                    'item_id'    => $Item->item_id,
                                    'store_id'   => $value['store_id'],
                                    'stock_type' => 'OUT',
                                    'stock_point_id' => $stockpoint->id,
                                    'qty'       => $value['qty'],
                                    'v_id'      =>  $value['v_id'],
                                    'order_id'  =>  $orders->od_id,
                                    'invoice_no'=>  $invoice->invoice_id);*/
            }
        } else {
            return response()->json(['status' => 'Fail', 'message' => 'Unable to find this item'], 200);
        }
    } //End of updateStockQty


    private function getBarcode($code, $v_id)
    {
        if ($code) {
            //using icode
            //$barcode = DB::table($store_db_name.'.invitem')->select('BARCODE')->where('ICODE', $code)->first();
            $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $code)->first();
            $item_master = null;
            if($bar){
                $item_master = VendorSku::select('barcode')->where(['vendor_sku_detail_id' => $bar->vendor_sku_detail_id, 'v_id' => $v_id])->first();

            }
            if (!$item_master) {
                $item_master = VendorSku::select('vendor_sku_detail_id')->where(['sku' => $code, 'v_id' => $v_id])->first();
                $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('vendor_sku_detail_id', $item_master->vendor_sku_detail_id)->first();
                $item_master->barcode = $bar->barcode;


            }

            if ($item_master->barcode) {
                return $item_master->barcode;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    private function updateStockCurrentStatus($variant_sku, $item_id, $quantity, $v_id, $store_id,$transaction_type)
    {
        $date = date('Y-m-d');
        $todayStatus = StockCurrentStatus::select('id', 'out_qty')
            ->where('item_id', $item_id)
            ->where('variant_sku', $variant_sku)
            ->where('store_id', $store_id)
            ->where('v_id', $v_id)
            ->where('for_date', $date)
            ->first();


        if ($todayStatus) {
            if($transaction_type == 'SALE'){
                $todayStatus->out_qty += $quantity;
                //echo 's';
            }
            else  if($transaction_type == 'RETURN'){
                $todayStatus->int_qty += $quantity;
                //echo 'r';
            }
            //die;
            $todayStatus->save();
            //  print($todayStatus); die;
        } else {
           
            $stockPastStatus = StockCurrentStatus::select('opening_qty', 'out_qty', 'int_qty')
                ->where('item_id', $item_id)
                ->where('variant_sku', $variant_sku)
                ->where('store_id', $store_id)
                ->where('v_id', $v_id)
                ->orderBy('for_date', 'DESC')
                ->first();

            if ($stockPastStatus) {
                $openingStock = $stockPastStatus->opening_qty + $stockPastStatus->int_qty - $stockPastStatus->out_qty;
            } else {
                $openingStock = 0;
            }

            $sto = StockCurrentStatus::create([
                'item_id' => $item_id,
                'variant_sku' => $variant_sku,
                'store_id' => $store_id,
                'v_id' => $v_id,
                'for_date' => $date,
                'opening_qty' => $openingStock,
                'out_qty' => $transaction_type=='SALE'?$quantity:0,
                'int_qty' => $transaction_type=='RETURN'?$quantity:0
            ]);
        }
    } //End of updateStockCurrentStatusOnImport




    private function stockIn(Request $request)
    {
        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $trans_from = $request->trans_from;
        $vu_id = $request->vu_id;
        $stockData = $request->stockData;

        StockIn::create($stockData);

        $this->stockLog($stockData, 'IN');
        $this->updateCurrentStock($stockData);

    }

    private function stockOut($stockData)
    {}

    /*Get Item Default Image*/
    public function getItemImage($parmas)
    {
        $barcode   = $parmas['barcode'];
        $v_id      = $parmas['v_id'];
        $item_image = 'default/zwing_default.png';   //Default Image
        $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $barcode)->first();
        $item_master = null;
        if($bar){
            $item_master   = VendorSku::where(['vendor_sku_detail_id' => $bar->vendor_sku_detail_id, 'v_id' => $v_id])->first();
        }
        if (!$item_master) {
            $item_master   = VendorSku::where(['sku' => $barcode, 'v_id' => $v_id])->first();
        }

        // print_r($item_master->Item->media);die;
        $item_multiple_images = array();
        foreach ($item_master->Item->media as $pa) {

            //echo $pa->pivot->item_media_attribute_value_id;
            //echo '<br>';
            $item_image = ItemMediaAttributeValues::select('value')->find($pa->pivot->item_media_attribute_value_id);
            if($item_image){
                $item_image = $item_image->value;
            }
            else{
                $item_image = '';
            }

            //unset($pa->pivot);
            $imageExplode  = explode('/', $item_image);
            $item_image  = $imageExplode[count($imageExplode) - 1];
            $item_multiple_images[] = $item_image;
        }

        $return_image = array('single_image' => $item_image, 'multiple_image' => $item_multiple_images);
        return $return_image;
    } //End of getItemImage


    public function getItemName($name, $variant_comb)
    {
     //dd($variant_comb);

        if (strpos($variant_comb, 'default') !== false) {
            $item_name = $name;
        } else {
            $item_name = $name . '(' . $variant_comb . ')';
        }
        return $item_name;
    }

    public function getItemPrice(Request $request){
      
      $this->validate($request, [
      'v_id' => 'required',
      'store_id' => 'required',
      'item' =>'required',
      'unit_mrp'=>'required',
    ]);

    $parmas=['v_id'=>$request->v_id,'store_id'=>$request->store_id,'item'=>json_decode($request->item),'unit_mrp'=>$request->unit_mrp];
    $v_id = $request->v_id;
    $store_id = $request->store_id;
    $item = json_decode($request->item);
    $unit_mrp = $request->unit_mrp;
    $priceId  = array();

    $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $item->barcode)->first();

    if($bar){
        /*$item  = VendorSkuDetails::where(['vendor_sku_details.id'=> $bar->vendor_sku_detail_id,'vendor_sku_details.v_id'=>$v_id,'stock_current_status.stop_billing'=>0])
           ->join('stock_current_status','stock_current_status.item_id','vendor_sku_details.item_id')
           ->select('stock_current_status.*',
            'vendor_sku_details.*')
           ->first();  */

           $item  = VendorSkuDetails::where(['vendor_sku_details.id'=> $bar->vendor_sku_detail_id,'vendor_sku_details.v_id'=>$v_id])
           //->join('stock_current_status','stock_current_status.item_id','vendor_sku_details.item_id')
           ->select('vendor_sku_details.*')
           ->first();
    }

     $vprice  =$item->vprice->where('v_id', $v_id)->where('variant_combi', $item->variant_combi);
            foreach ($vprice as $key => $value) {
              if($value->store_id !=0  && $value->price_book_id != 0){
                    //$priceId[] = $value->price->id;
                    if($value->store_id == $store_id){
                        $priceId[] = $value->price_book_id;
                    }
              }
            }
            $priceValidId = 0;
            $timestamp    = date('Y-m-d');
            $priceBook    = PriceBook::whereIn('id',$priceId)
                            ->whereDate('effective_date','<=',$timestamp)
                            ->whereDate('valid_to','>=',$timestamp)
                            ->where('v_id',$v_id)
                            ->where('status','1')
                            ->orderBy('effective_date','desc')->first();
            if($priceBook){
                  $priceValidId = $priceBook->id;
            }                
            if($priceValidId != 0) {
                $priceList = $item->vprice->where('v_id', $v_id)->where('store_id',$store_id)->where('variant_combi', $item->variant_combi)->whereIn('price_book_id',$priceValidId );
            }else{
                $priceList = $item->vprice->where('v_id', $v_id)->where('store_id', 0)->where('price_book_id', 0)->where('variant_combi', $item->variant_combi);
                //dd($priceList);
            }
            /* Old Code Start*/
            $mrplist = array();
            foreach ($priceList as $mp) {
                $mrplist[] = array('mrp' => $mp->priceDetail->mrp, 'rsp' => $mp->priceDetail->rsp, 's_price' => $mp->priceDetail->special_price);
            }
            $mrplist  = collect($mrplist);
            // dd($mrplist);
            // echo $mrplist->max('mrp');die;
            if ($unit_mrp) {
                $mrpcurrent = $mrplist->where('mrp', $unit_mrp)->first();
                //dd($mrpcurrent);
                $unit_mrp =  $mrpcurrent['mrp'];
                $r_price  =  $mrpcurrent['rsp']; //  * $value[1];
                $s_price  =  !empty($mrpcurrent['s_price']) ? $mrpcurrent['s_price'] : $mrpcurrent['mrp'];
            } else {
                $unit_mrp =  $mrplist->max('mrp');
                // $r_price  =  $mrplist->max('rsp');//  * $value[1];
                // $s_price  =  !empty($mrplist->max('s_price'))?$mrplist->max('s_price'):$mrplist->max('mrp');

                $mrpcurrent = $mrplist->where('mrp', $unit_mrp)->first();
                $unit_mrp =  $mrpcurrent['mrp'];
                $r_price  =  $mrpcurrent['rsp']; //  * $value[1];
                $s_price  =  !empty($mrpcurrent['s_price']) ? $mrpcurrent['s_price'] : $mrpcurrent['mrp'];
            }
            $data     = '';
            $mrp_arrs = array();
            $mrp_arrs1 = $mrplist->map(function ($item) {
                return format_number($item['mrp']);
            });

            $mrp_arrs = $mrp_arrs1->toArray();

            $multiple_mrp_flag  = (count($mrp_arrs) > 1) ? true : false;
            $mrp       = (!empty($s_price) ? $s_price : $unit_mrp); //$price['unit_mrp'];
            $return = array('unit_mrp' => $unit_mrp, 'r_price' => $unit_mrp, 's_price' => $s_price, 'mrp_arrs' => $mrp_arrs, 'multiple_mrp_flag' => $multiple_mrp_flag, 'mrp' => $mrp);
            return $return;

    }


    public function getItemSupplyPrice(Request $request){


        $this->validate($request,[
                                 'v_id' => 'required',
                                 'barcode' => 'required',
                                 'store_id' => 'required',
                                 ]);
        // try{

           $v_id      = $request->v_id; 
           $store_id  = $request->store_id;
           $barcode  = $request->barcode;
           $sourcetype  = empty($request->source_site_type)?'store':$request->source_site_type;
           $detail = array( 'supply_price'=>0.00,
                             'charge'    =>0.00,
                             'discount' =>0.00
                           ); 
           $last_inward_price = LastInwardPrice::where('v_id',$v_id)
                                             ->where('barcode',$barcode)
                                             ->where('destination_site_id',$store_id)
                                           ->orderBy('id','DESC')
                                           ->first();
           if(!empty($last_inward_price)){                                
            $detail = array('supply_price'=>$last_inward_price->supply_price,
                             'charge'    =>$last_inward_price->charge,
                             'discount' =>$last_inward_price->discount
                            );                              
            }

            // else{
            //       $grnList         =    GrnList::where('v_id',$v_id)
            //                              ->where('barcode',$barcode)
            //                              ->where('store_id',$store_id)
            //                              ->orderBy('id','DESC')
            //                              ->first();
            //  if(!empty($grnList) && $grnList->cost_price>0.00){
                                   
            //   $detail = array('supply_price'=>$grnList->cost_price,
            //                  'charge'    => $grnList->charges,
            //                  'discount' => $grnList->discount
            //                 );
            //    }                                                         
            //    else{
            //     $itemDetails = ItemList::where('v_id',$v_id)->where('barcode', $barcode)->first();

            //     $params= array('v_id' =>   $v_id,
            //                   'store_id' => $store_id,
            //                   'item'     => json_encode($itemDetails),
            //                   'unit_mrp' => '0',
            //                   );

            //    $price=$this->getItemPrice($params);
            //    $item_price  = isset($price->s_price)?$price->s_price:$price->unit_mrp;
            //    $detail = array( 'supply_price'=>$item_price,
            //                     'charge'    =>0.00,
            //                     'discount' =>0.00
            //                   );
            //     }

            //} 
            return response()->json(['status' => 'sucess','data'=>$detail, 'message' => 'Item price available'], 200);
            
        // }catch( \Exception $e ) {
        //      //Log::error($e);

        //         return response()->json([ 'status' => 'fail' , 'message' => 'Server Error'   ] , 500);
        // }  

    }
}
