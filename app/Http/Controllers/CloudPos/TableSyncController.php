<?php

namespace App\Http\Controllers\CloudPos;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VendorSettingController;
use App\Http\Controllers\VendorController;
//use App\DataSyncStatus;
use App\DeviceVendorUser;
use Illuminate\Http\Request;
use DB;
use Auth;
use App\Cart;
use App\User;
use App\VendorAuth;
use App\Order;
use App\Item;
use App\Vendor;
use App\SettlementSession;
use App\Model\Stock\StockCurrentStatus;
use App\Model\Stock\StockPointSummary;
use App\Model\Stock\StockPoints;
use App\Model\Items\VendorSkuDetails;
use App\Model\Items\VendorSku;
use App\Model\Items\VendorSkuDetailBarcode;
use App\Model\Items\ItemMediaAttributeValues;
use App\Model\Item\ItemCategory;
use  App\Items\VendorItems;
use  App\Model\Item\ItemList;
use App\Model\Store\StoreItems;
use App\Http\Controllers\CloudPos\CartconfigController;
use App\Http\Controllers\CloudPos\CartController;
use App\Model\Grn\GrnList;
use App\StoreSyncLog;
use App\Invoice;
use App\Carry;
class TableSyncController extends Controller
{

  public function __construct()
  {
    $this->middleware('auth', ['except' => ['switchToOnline']]) ;
  }

  public function sync(Request $request)
    {
        $type = $request->type;
        $store_id = $request->store_id;
        $v_id = $request->v_id;
        if($type === 'store_user') {
            return $this->allStoreUser($request);
        } elseif($type === 'session') {
            return $this->lastSessionRecord($request);
        } elseif($type === 'user_wise_settings') {
            return $this->getUserWiseSettings($request);
        } elseif($type === 'item_master') {
            return $this->itemMaster($request);
        }
    }

    public function allStoreUser(Request $request)
    {
      if($request->has('last_time') && empty($request->last_time)) {
        return response()->json([ 'status' => 'sync_data', 'data' => [], 'time_stamp' => "" ]);
      }
        $type = $request->type;
        $store_id = $request->store_id;
        $v_id = $request->v_id;
        $userList = [];
        $allStoreUser = Vendor::select('id','mobile','first_name','last_name','password','v_id','store_id')->where('store_id', $store_id)->where('v_id', $v_id)->where('status', '1')->get();
        foreach ($allStoreUser as $key => $value) {
          $checkCashier = $value->roles->map(function($item) use ($value, &$userList) {
            if($item->role->name == 'cashier') {
              unset($value->roles);
              $userList[] = $value;
            }
          });
        }
          $timeStamp= '';
        if(!empty($userList)){
          $timeStamp = date('Y-m-d H:i:s');
          StoreSyncLog::updateOrCreate(
            ['v_id' => $v_id, 'store_id' => $store_id, 'vu_id' => $request->vu_id, 'trans_from' => $request->trans_from, 'type' => 'user_master', 'udid' => $request->udidtoken],
            ['entity_count' => count($userList), 'latest_sync_time' => $timeStamp]
          );
        }

        return response()->json([ 'status' => 'sync_data', 'data' => $userList, 'time_stamp' => $timeStamp, 'type' => $request->type ]);
    }

    public function lastSessionRecord(Request $request)
    {
      if($request->has('last_time') && empty($request->last_time)) {
        return response()->json([ 'status' => 'sync_data', 'data' => [], 'time_stamp' => "" ]);
      }
        $type = $request->type;
        $store_id = $request->store_id;
        $v_id = $request->v_id;
        $trans_from = $request->trans_from;
        $sessionRecords = [];
        $allStoreUser = Vendor::select('id')->where('store_id', $store_id)->where('v_id', $v_id)->where('status', '1')->get();
        foreach ($allStoreUser as $key => $user) {
          $checkCashier = $user->roles->map(function($item) use ($user, &$sessionRecords, $store_id, $v_id, $trans_from) {
            if($item->role->name == 'cashier') {
              $sessionRecord = SettlementSession::select('id','vu_id','settlement_date','trans_from','opening_balance','opening_time','closing_balance','closing_time')->where('store_id', $store_id)->where('v_id', $v_id)->where('vu_id', $user->id)->where('trans_from', $trans_from)->latest()->first();
              if($sessionRecord) {              
              $sessionRecords[] = $sessionRecord;
              }
            }
          });
        }

        $timeStamp = '';
        if(!empty($sessionRecords)){
          $timeStamp = date('Y-m-d H:i:s');
          StoreSyncLog::updateOrCreate(
            ['v_id' => $v_id, 'store_id' => $store_id, 'vu_id' => $request->vu_id, 'trans_from' => $request->trans_from, 'type' => 'session_master', 'udid' => $request->udidtoken],
            ['entity_count' => count($sessionRecords), 'latest_sync_time' => $timeStamp]
          );
        }
        
        return response()->json([ 'status' => 'sync_data', 'data' => $sessionRecords, 'time_stamp' => $timeStamp, 'type' => $request->type ]);
    }

    public function getUserWiseSettings(Request $request)
    {
      if($request->has('last_time') && empty($request->last_time)) {
        return response()->json([ 'status' => 'sync_data', 'data' => [], 'time_stamp' => "" ]);
      }
        $vu_id = $request->vu_id;
        $type = $request->type;
        $store_id = $request->store_id;
        $v_id = $request->v_id;
        $sessionRecords = [];
        $settingList = [];
        $allStoreUser = Vendor::select('id','mobile')->where('store_id', $store_id)->where('v_id', $v_id)->where('status', '1')->get();
        $settings = new VendorController;

        foreach ($allStoreUser as $key => $user) {
          $checkCashier = $user->roles->map(function($item) use ($user, &$settingList, $request, $settings) {
            if($item->role->name == 'cashier') {
              $request->request->add(['vu_id' => $user->id, 'response_format' => 'ARRAY', 'mobile' => $user->mbile]);
              $settingList[] = (object)[ 'user_id' => $user->id, 'settings' => $settings->get_settings($request) ];
            }
          });
        }

        $timeStamp= '';
        if(!empty($settingList)){
          $timeStamp = date('Y-m-d H:i:s');
          StoreSyncLog::updateOrCreate(
            ['v_id' => $v_id, 'store_id' => $store_id, 'vu_id' => $request->vu_id, 'trans_from' => $request->trans_from, 'type' => 'setting_master', 'udid' => $request->udidtoken],
            ['entity_count' => count($settingList), 'latest_sync_time' => $timeStamp]
          );
        }
        
        return response()->json([ 'status' => 'sync_data', 'data' => $settingList, 'time_stamp' => $timeStamp, 'type' => $request->type ]);
    }

    public function itemMaster(Request $request)
    {
        $type     = $request->type;
        $store_id = $request->store_id;
        $v_id     = $request->v_id;
        $vu_id    = $request->vu_id;
        if(!$request->has('last_time')) {
          $offset   = $request->from_record;
          $limit    = $request->to_record;
          $take     = $limit - $offset;
        }

        JobdynamicConnection($v_id);

        $vendorS = new VendorSettingController;
        $sParams = ['v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $vu_id, 'role_id' => '0', 'trans_from' => 'VENDOR','udidtoken'=>$request->udidtoken];
        $checkNegativeBilling  = $vendorS->getStockSetting($sParams);
        if(!$checkNegativeBilling){
          return   response()->json([ 'status' => 'fail', 'message' => 'Add stock setting first' ]);
        }

        $stockPoint = StockPoints::where(['v_id'=>$v_id,'store_id'=>$store_id,'is_sellable'=>'1','is_active'=>'1'])->first(); 
        if(!$stockPoint){
          return   response()->json([ 'status' => 'fail', 'message' => 'No sellable Stock Point is assign to this store' ]);
        }
        $stockPoint = $stockPoint->id;
        $where    = array( 'store_items.v_id' => $v_id, 'store_items.store_id' => $store_id, 'vendor_sku_flat_table.is_active' => '1','vendor_sku_flat_table.deleted_at' => Null, 'stock_point_summary.stop_billing' => '0', 'stock_point_summary.store_id' => $store_id); 

        // $data = StoreItems::Join('carry_bags', 'carry_bags.barcode' , 'store_items.barcode')->where('store_items.store_id', $store_id)->select('store_items.barcode')->get();

        $carry = [];

        $carry = Carry::select('barcode')->where('store_id',$store_id)->where('status', '1')->get();
        if(!$carry->isEmpty()){
          $carry = $carry->pluck('barcode')->all();
        }

        if($v_id == 84 || $v_id == 75 || $v_id == 78 ){//Checking performance
          
          $where    = array( 'store_items.v_id' => $v_id, 'store_items.store_id' => $store_id, 'vendor_sku_flat_table.is_active' => '1','vendor_sku_flat_table.deleted_at' => Null); 
          $master  = VendorSku::leftJoin('store_items',function($query){
                                  $query->on('store_items.v_id','vendor_sku_flat_table.v_id');
                                  $query->on('store_items.variant_sku','vendor_sku_flat_table.sku');
                                  // $query->on('store_items.barcode','vendor_sku_flat_table.barcode');
                                  $query->on('store_items.item_id','vendor_sku_flat_table.item_id');
                                })
                                ->join('vendor_sku_detail_barcodes', function($query){
                                  $query->on('vendor_sku_detail_barcodes.vendor_sku_detail_id','vendor_sku_flat_table.vendor_sku_detail_id');
                                  $query->on('vendor_sku_detail_barcodes.barcode','store_items.barcode');
                                  $query->on('vendor_sku_detail_barcodes.v_id','store_items.v_id');
                                })
                          
                                ->select('vendor_sku_flat_table.sku','vendor_sku_detail_barcodes.barcode','vendor_sku_flat_table.variant_combi','vendor_sku_flat_table.hsn_code','vendor_sku_flat_table.name','vendor_sku_flat_table.selling_uom_type as uom_type','vendor_sku_flat_table.selling_uom_name as uom_name','vendor_sku_flat_table.has_batch as is_batch','vendor_sku_flat_table.has_serial as is_serial','vendor_sku_flat_table.cat_name_1 as category','vendor_sku_flat_table.item_id')
                                ->where($where)
                                ->whereNotIn('vendor_sku_detail_barcodes.barcode', $carry)
                                ->groupBy('vendor_sku_flat_table.sku')
                                ->orderBy('vendor_sku_flat_table.name', 'ASC');
              // dd($master->toSql(), $master->getBindings()) ;

        }else{

          $master  = VendorSku::leftJoin('store_items',function($query){
                                  $query->on('store_items.v_id','vendor_sku_flat_table.v_id');
                                  $query->on('store_items.variant_sku','vendor_sku_flat_table.sku');
                                  $query->on('store_items.item_id','vendor_sku_flat_table.item_id');
                                })
                                ->join('vendor_sku_detail_barcodes', function($query){
                                  $query->on('vendor_sku_detail_barcodes.vendor_sku_detail_id','vendor_sku_flat_table.vendor_sku_detail_id');
                                  $query->on('vendor_sku_detail_barcodes.barcode','store_items.barcode');
                                  $query->on('vendor_sku_detail_barcodes.v_id','store_items.v_id');
                                })
                                ->join('stock_point_summary', 'stock_point_summary.sku_code', 'vendor_sku_flat_table.sku_code')
                                ->select('vendor_sku_flat_table.sku','vendor_sku_detail_barcodes.barcode','vendor_sku_flat_table.variant_combi','vendor_sku_flat_table.hsn_code','vendor_sku_flat_table.name','vendor_sku_flat_table.selling_uom_type as uom_type','vendor_sku_flat_table.selling_uom_name as uom_name','vendor_sku_flat_table.has_batch as is_batch','vendor_sku_flat_table.has_serial as is_serial','vendor_sku_flat_table.cat_name_1 as category','vendor_sku_flat_table.item_id')
                                ->where($where)
                                ->whereNotIn('vendor_sku_detail_barcodes.barcode', $carry)
                                ->groupBy('vendor_sku_flat_table.sku')
                                ->orderBy('vendor_sku_flat_table.name', 'ASC');
          }

          if($request->has('last_time')) {
            $master = $master->where('vendor_sku_flat_table.updated_at', '>=', $request->last_time);
          }
          
          $totalProducts = 0;
          if($request->has('total_records')){
            if($request->total_records > 0){
              $totalProducts   = $request->total_records;
            }else{
              $totalProducts   = $master->get()->count();
            }
          }else{
            $totalProducts   = $master->get()->count();

          }
          
      
          if($request->has('last_time')) {
            $item_master    = $master->get();
          } else {
            $item_master    = $master->skip($offset)
                                ->take($take)
                                ->get();
          }

          if($item_master->isEmpty()) {
            return response()->json([ 'status' => 'sync_data','total_no_of_records'=> 0, 'catalog_data' => [] , 'product_image_link' => product_image_link().$v_id.'/', 'time_stamp' => "" ]);
          }
          

       foreach ($item_master as $product) {
        $batches  = [];
        $serials = [];
        // $item_name = [];
         $cartconfig     = new CartconfigController;
         if($product->sku) {
           $item_name     =  $cartconfig->getItemName($product->name,$product->variant_combi); 
           // print_r($item_name);
           $category_name =  $product->category;
           $item_image    = 'default/zwing_default.png';
           // dd($item_image);
           // $itemSku = VendorSkuDetails::find($product->vsid);
           if(count($product->Item->media) > 0 ){
            foreach ($product->Item->media as $pa) {
              $item_image=ItemMediaAttributeValues::select('value')->find($pa->pivot->item_media_attribute_value_id);
              if($item_image){
                $item_image = $item_image->value;
              }else{
                $item_image = '';
              }
                    //unset($pa->pivot);
              $imageExplode = explode('/', $item_image);
              $item_image   = $imageExplode[count($imageExplode)-1]; 
            }
           }
          $priceArr  = array('v_id'=>$v_id,'store_id'=>$store_id,'item'=>$product,'unit_mrp'=>''); 
          $price = $cartconfig->getprice($priceArr);
          $mrp       =  !empty($price['s_price'])?$price['s_price']:$price['unit_mrp']; 
          $rsp       =  !empty($price['r_price'])?$price['r_price']:$price['unit_mrp']; 
          $mrp_arrs  =  $price['mrp_arrs']; 
          $qty       =  0;
          if($checkNegativeBilling->negative_stock_billing->status ==0){
            $getStock = StockPointSummary::where(['v_id'=>$v_id,'store_id'=>$store_id,
              'barcode'=>$product->barcode,'variant_sku'=>$product->sku,'item_id'=>$product->item_id,'stock_point_id'=>$stockPoint])->first();
            if($getStock){
              $qty      =  $getStock->qty;
            }
          }
          $barcode   =  $product->barcode;
          $multiple_batch = [];
          $params = array('barcode'=>$barcode,'qty'=>'1','s_price'=>$mrp,'hsn_code'=>$product->hsn_code,'store_id'=>$store_id,'v_id'=>$v_id);
          $carts  = new CartController;
          $tax_details =$carts->taxCal($params);


        if($product->is_batch == 1) {
          $grnData = GrnList::where(['barcode'=>$product->barcode,'v_id'=>$v_id,'store_id'=>$store_id])->orderBy('id','desc')->get();
          foreach ($grnData as $gdata) {
            foreach ($gdata->batches as $batch) {
              if($batch->batch_no != ''){
                $validty = !empty($batch->valid_months)?$batch->valid_months:'N/A';
                $batches[]  = array('id'=>$batch->id,'code'=>$batch->batch_no,'mfg_date'=>$batch->mfg_date,'exp_date'=>$batch->exp_date,'validty'=>$validty,'type'=>'batch','mrp'=>$batch->priceDetail->mrp);
              }
            } 
          }

          //Temporray contion added to get unique values
          $batches = collect($batches);
          $batches = $batches->unique('code')->toArray();
        }

      if($product->is_serial == 1) {
        $grnData = GrnList::where(['barcode'=>$barcode,'v_id'=>$v_id,'store_id'=>$store_id])->orderBy('id','desc')->get();
        foreach ($grnData as $gdata) {
          foreach ($gdata->serials as $serial) {
            $serials[]  = array('id'=>$serial->id,'code'=>$serial->serial_no,'type'=>'serial','mrp'=>$serial->priceDetail->mrp);
          } 
        }
        //Temporray contion added to get unique values
        $serials = collect($serials);
        $serials = $serials->unique('code')->toArray();
        
      }

          $data[] = [
                  'barcode' => $product->barcode,
                  'sku'     => $product->sku,
                  'name'    => utf8_encode($item_name),
                  'mrp'     => format_number($mrp, 2),
                  'rsp'     => format_number($rsp, 2),
                  'special_price' => format_number($mrp, 2),
                  'is_batch'  => $product->is_batch,
                  'is_serial' => $product->is_serial,
                  'batch'     => $batches,
                  'serial'    => $serials,
                  'uom'       => $product->uom_name,
                  'weight_flag' => ($product->uom_type == 'WEIGHT' ? true : false),
                  'category'    => $category_name,
                  'multiple_price' => $mrp_arrs,
                  'multiple_batch' => $multiple_batch,
                  'images'        => str_replace(' ', '%20', $item_image),
                  'current_stock' => (string) $qty,
              //'uom_conversion' =>getUomUnitPrice($barcode, $price['mrp'], $v_id),
                  'tax_details' =>$tax_details
              ];
               $isimage[$category_name]  =  $product->IS_IMAGE;

         }

       }

        $response = [];
        $sr       = 1;
        if (isset($data) && count($data) > 0) {
        foreach ($data as $key =>  $d) {
            $keyData    = explode('~', $key);
            $response[] = $d;
            $sr++;
        }
        }else{
          $response[] = [ 'title' => 'No Data','cat_id'=>$sr,'is_image'=> '' , 'data' => '']; 
            $sr++;
        }
        //dd($data);

        // $balanceProducts = $totalProducts - count($response);
        $timeStamp= '';
        if($master->skip($totalProducts - 1)->first()->barcode == $response[count($response)-1]['barcode']) {
          $timeStamp = date('Y-m-d H:i:s');
          StoreSyncLog::updateOrCreate(
            ['v_id' => $v_id, 'store_id' => $store_id, 'vu_id' => $request->vu_id, 'trans_from' => $request->trans_from, 'type' => 'item_master', 'udid' => $request->udidtoken],
            ['entity_count' => $totalProducts, 'latest_sync_time' => $timeStamp]
          );
          // $syncLog = new StoreSyncLog;
          // $syncLog->v_id = $v_id;
          // $syncLog->store_id = $store_id;
          // $syncLog->vu_id = $request->vu_id;
          // $syncLog->udid = $request->udidtoken;
          // $syncLog->type = 'item_master';
          // $syncLog->trans_from = $request->trans_from;
          // $syncLog->entity_count = $totalProducts;
          // $syncLog->latest_sync_time = $timeStamp;
          // $syncLog->last_transaction_id = 0;
          // $syncLog->save();
        }      

        return response()->json([ 'status' => 'sync_data','total_no_of_records'=>$totalProducts, 'catalog_data' => $response , 'product_image_link' => product_image_link().$v_id.'/', 'time_stamp' => $timeStamp, 'type' => $request->type ]);
    
    }

     public function success(Request $request)
     {

     }

     public function syncItemList(Request $request)
     { 
         $v_id       = $request->v_id;
         //dd($v_id);
         //$c_id       = $request->c_id;
        //$store_id   = $request->store_id; 
        
         $products =  VendorSku::select('vendor_sku_detail_barcodes.barcode','vendor_sku_flat_table.department_id','vendor_sku_flat_table.brand_id')
                      ->join('vendor_sku_detail_barcodes', function($query){
                          $query->on('vendor_sku_detail_barcodes.vendor_sku_detail_id','vendor_sku_flat_table.vendor_sku_detail_id');
                      })
                      ->groupBy('vendor_sku_flat_table.sku')
                      ->where('v_id',$v_id)->get(); 
         $itemList = [];   
         $itemdata=[];
         
         $productC = new ProductController;
          foreach($products as $product){
            // $itemdata[] =  $productC->getItemDetails($product);
            $item =  $productC->getItemDetailsForPromo(['item' => $item, 'v_id' => $v_id,'store_id'=> 0 ]);

            $itemdata[] = $item['item'];

          }
          foreach($itemdata as $item){
            $itemList[]=array(
                "DIVISION_GRPCODE"=>$item->DIVISION_GRPCODE,
                "SECTION_GRPCODE"=>$item->DIVISION_GRPCODE,
                "DEPARTMENT_GRPCODE"=>$item->DIVISION_GRPCODE,
                "INVARTICLE_CODE"=>$item->DIVISION_GRPCODE,
                "ICODE" => $item->barcode,
                "CCODE1"=> $item->CCODE1,
                "CCODE2"=> $item->CCODE2,
                "CCODE3"=>$item->CCODE3,
                "CCODE4"=>$item->CCODE4,
                "CCODE5"=> $item->CCODE5,
                "CCODE6"=> $item->CCODE6,
                "QTY"=> $item->qty,
                "DESC1"=> $item->DESC1,
                "DESC2"=>$item->DESC2,
                "DESC3"=>$item->DESC3,
                "DESC4"=>$item->DESC4,
                "DESC5"=>$item->DESC5,
                "DESC6"=>$item->DESC6,

            );
          }
        return response()->json([ 'status' => 'item_list', 'data' =>$itemList],200);
       
    
     }


     public function getItemDetails($product){
        $category = $product->category()->toArray();
        for($counter = 0; $counter < 6; $counter++){
            $code = 'CCODE'. ($counter +1);
            if(isset($category[$counter])){
                $product->$code = $category[$counter]->id;
            }else{
                $product->$code = '';
            }
        }
        //$stock  =$product->StockCurrentStatus;
        $it = $product->Item;
        $product->DIVISION_GRPCODE = '';
        $product->SECTION_GRPCODE='';
        $product->DEPARTMENT_GRPCODE = $it->department_id;
        $product->INVARTICLE_CODE = '';
        $product->DESC1 = $it->brand_id;
        $product->DESC2 = '';
        $product->DESC3 = '';
        $product->DESC4 = '';
        $product->DESC5 = '';
        $product->DESC6 = '';
       unset($product->Item);
       //$priceList = $product->vprice;
        return $product;
     }

     public function switchToOnline(Request $request) {
      // $vendor_token = str_random(50);
      $vendor_token = $request->vendor_token;
      $customer_token = $request->customer_token;

      $update_vendor = VendorAuth::where(['v_id' => $request->v_id, 'id' => $request->vu_id, 'store_id' => $request->store_id])->update(['api_token' => $vendor_token]);

      $update_customer = User::where(['v_id' => $request->v_id, 'c_id' => $request->c_id])->update(['api_token' => $customer_token]);

      // if(true) {
        return response()->json(['status' => 'success', 'vendor_api_token' => $vendor_token, 'customer_api_token' => $customer_token], 200);
      // }else {
        // return response()->json(['status' => 'fail'], 200);
      // }
     }

     public function latestInvoiceId(Request $request) {
        $invoice = Invoice::select('invoice_id', 'ref_order_id', 'created_at')->where(['v_id' => $request->v_id, 'vu_id' => $request->vu_id, 'store_id' => $request->store_id, 'terminal_id' => $request->terminal_id])->orderBy('created_at', 'desc')->first();
        if($invoice) return response()->json(['status' => 'success', 'message' => 'Latest Invoice Number', 'data' => $invoice], 200);
        else return response()->json(['status' => 'fail', 'message' => 'No Invoice Found'], 200);
     }
}