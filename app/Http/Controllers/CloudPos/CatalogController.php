<?php

namespace App\Http\Controllers\CloudPos;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VendorSettingController;
use Illuminate\Http\Request;
use DB;

use App\Cart;
use App\Order;
use App\Store;
use App\Model\Stock\StockCurrentStatus;
use App\Model\Items\VendorSkuDetails;
use App\Model\Items\VendorSku;
use App\Model\Items\ItemMediaAttributeValues;
use App\Model\Stock\StockPointSummary;
use App\Model\Stock\StockPoints;
use App\Carry;
use App\Vendor\VendorRoleUserMapping;

class CatalogController extends Controller
{

    public function __construct()
    {
        //$this->middleware('auth');
        $this->cartconfig  = new CartconfigController;
    }

    public function getCatalog(Request $request){
        $v_id     = $request->v_id;
        $store_id = $request->store_id; 
        $c_id     = $request->c_id;
        $user_id = $request->vu_id;
        $role_id = VendorRoleUserMapping::select('role_id')->where('user_id',$user_id)->first()->role_id;
        $trans_from = $request->trans_from;

        /*Getting product max quantity*/        
        $vendorS = new VendorSettingController;
                        $sParams = ['v_id' => $v_id,'store_id'=>$store_id,'user_id'=>$user_id,'role_id'=>$role_id,'trans_from' => $trans_from];
                        $product_max_qty =  $vendorS->getProductMaxQty($sParams);

        // $stockPoint = StockPoints::where(['v_id'=>$v_id,'store_id'=>$store_id,'is_sellable'=>1])->first();
        // if(!$stockPoint){
        //      return response()->json(['status' => 'error','message'=> 'No Sellable Stock Point Found' ],200);
        // }  
        // $where    = array('stock_point_summary.v_id'=>$v_id,'stock_point_summary.store_id'=>$store_id,'stock_point_summary.stock_point_id'=>$stockPoint->id);
        // $item_master =  StockPointSummary::where($where)->with(['sku' => function($query) use($v_id){
        //                                     $query->where('v_id',$v_id); 
        //                                 }])->groupBy('variant_sku')->get();
        // if($item_master->isEmpty()){
        //     return response()->json(['status' => 'error','message'=> 'No Catalogs Items Found' ],200);
        // }

        // $carry_bags    = Carry::select('barcode')->where('v_id', $v_id)->where('store_id', $store_id)->where('status','1')->where('deleted_status', '0')->get();
        // $carry_bag_arr = $carry_bags->pluck('barcode')->all();  

        // $carts         = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status','process')->orderBy('updated_at','desc')->get();
        // $total          = $carts->sum('total');
        // $cart_qty_total = $carts->sum('qty');
        // $cartItems      = $carts->pluck('qty','item_id')->all();
        
        // // Item Master Loop Start

        // foreach ($item_master as $product) {
        //     $item_name      =  $this->cartconfig->getItemName($product->sku->Item->name,$product->sku->variant_combi); 

 
        //     $category_name  =  $product->sku->category[0]->name;
        //     $item_image     = 'default/zwing_default.png';
        //     $current_stock  = $product->qty;
    
        //     foreach ($product->sku->media('IMAGE') as $pa) {
        //         $item_image = $pa->value;
        //           //unset($pa->pivot);
        //          $imageExplode = explode('/', $item_image);
        //          $item_image   = $imageExplode[count($imageExplode)-1];
        //     }

        //     $priceArr  = array('v_id'=>$v_id,'store_id'=>$store_id,'item'=>$product->sku,'unit_mrp'=>'');
        //     $config    = new CartconfigController;
        //     //$price   = $config->getprice($priceList);
        //     $price     = $config->getprice($priceArr);

        //     /*Price Calculation end*/

        //     $mrp       =  !empty($price['s_price'])?$price['s_price']:$price['unit_mrp']; //$price['unit_mrp'];
        //     $qty       =  0;
        //     $barcode   =  $product->sku->barcode;
        //     $cart_id   =  $product->sku->id;
        //     //if (strpos($product->CATEGORY, 'Carry Bag') !== false) {
        //     if (preg_match('/Carry Bag/', $category_name) ){
        //         if( in_array($item_name, $carry_bag_arr) ){
        //             //echo $product->ITEM;
        //         }else{
        //             continue;
        //         }
        //     }

        //     $qty     = 0;
        //     $cart_id = '';
        //     if(isset($cartItems[$barcode])){
        //         $qty     = $cartItems[$barcode];
        //         $cart    = $carts->where('item_id', $barcode)->first();
        //         $cart_id = $cart->cart_id;
        //     }
        //     $itemDesc   = $item_name;
        //     if($item_name == NULL){
        //         $itemDesc = "";
        //     }
        //      if($qty != 0){
        //         $decimal_qty = $product->sku->Item->uom->selling->type == 'WEIGHT' ? '1':$qty;
        //     }else{
        //         $decimal_qty = 0;
        //     }


        //     $data[$product->sku->category[0]->name . '~' . $product->sku->category[0]->id][] = [
        //         'item_name' => utf8_encode($item_name),
        //         'images'    => $item_image,
        //         'unit_mrp'  => format_number($mrp, 2),
        //         'category'  => $category_name,
        //         'qty'       => (string) $qty,
        //         'decimal_qty' => $decimal_qty,
        //         'barcode'   => $barcode,
        //         'current_stock'  => $current_stock,
        //         'cart_id'   => $cart_id,
        //         'cat_id'    => $product->sku->sku,
        //         'weight'    => ($product->sku->Item->uom->selling->type == 'WEIGHT' ? true : false),
        //         'uom'       => $product->sku->Item->uom->purchase->name,
        //         'uom_conversion' => getUomUnitPrice($barcode, $price['mrp'], $v_id)
        //     ];
        //     $isimage[$product->sku->Item->categories[0]->name]  =  $product->IS_IMAGE;
 
        
        // }



      

        /*Old code*/

        $where    = array('stock_current_status.v_id'=>$v_id,'stock_current_status.store_id'=>$store_id,'stop_billing'=>0);
     //DB::connection()->enableQueryLog();
        $item_master= StockCurrentStatus::with(['Item'=> function($query){
                            $query->with('categories')->where('deleted', '0')->orderBy('id','desc')->first() ;
                        },'sku' => function($query) use($v_id){
                            $query->where('v_id',$v_id);

                        }])->first()
                         ->where($where)  
                         ->whereNotNull('variant_sku')                       
                         ->groupBy('variant_sku')
                         ->orderBy('stock_current_status.updated_at', 'DESC')
                         ->get();
    //dd(DB::getQueryLog());
            /*$item_master= VendorSkuDetails::join('stock_current_status',function($query) use($v_id){
                            $query->on('stock_current_status','stock_current_status.variant_sku','vendor_sku_details.sku');
                            $query->on('stock_current_status.item_id','vendor_sku_details.item_id');
                            $query->where('stock_current_status.v_id',$v_id)
                          })*/


        
        $carry_bags    = DB::table('carry_bags')->select('barcode')->where('v_id', $v_id)->where('store_id', $store_id)->where('status','1')->where('deleted_status', '0')->get();
        $carry_bag_arr = $carry_bags->pluck('barcode')->all();  
        $carts         = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status','process')->orderBy('updated_at','DESC')->get();

        $total          = $carts->sum('total');
        $cart_qty_total = $carts->sum('qty');
        $cartItems      = $carts->pluck('qty','item_id')->all();
       
        foreach ($item_master as $product) {

            if(isset($product->sku)){
            // echo $product->Item->name.' - '.@$product->sku->variant_combi.' -- '.@$product->sku->item_id;
            // echo '<br>';


            $item_name    =  $this->cartconfig->getItemName($product->Item->name,$product->sku->sku); 

            //$item_name      =  $product->Item->name.' ('.$product->sku->variant_combi.')';
            $category_name  =  $product->Item->categories[0]->name;
            $item_image     = 'default/zwing_default.png';
            $stockCheck     = StockCurrentStatus::where(['v_id'=>$v_id,'variant_sku'=>$product->variant_sku,'store_id'=>$store_id])->orderBy('id','desc')->first();

            $current_stock  = ($stockCheck->opening_qty+$stockCheck->int_qty)-$stockCheck->out_qty;
    
            foreach ($product->Item->media as $pa) {
                $item_image = ItemMediaAttributeValues::select('value')->find($pa->pivot->item_media_attribute_value_id)->value;
                  //unset($pa->pivot);
                 $imageExplode = explode('/', $item_image);
                 $item_image   = $imageExplode[count($imageExplode)-1];
            }

            //$priceList= $product->sku->vprice->where('v_id',$v_id)->where('variant_combi',$product->sku->variant_combi);
            $priceArr  = array('v_id'=>$v_id,'store_id'=>$store_id,'item'=>$product->sku,'unit_mrp'=>'');
            $config    = new CartconfigController;
            //$price   = $config->getprice($priceList);
            $price     = $config->getprice($priceArr);

            /*Price Calculation end*/

            $mrp       =  !empty($price['s_price'])?$price['s_price']:$price['unit_mrp']; //$price['unit_mrp'];
            $qty       =  0;
            $barcode   =  $product->sku->barcode;
            $cart_id   =  $product->sku->id;
            //dd($cart_id );
            //if (strpos($product->CATEGORY, 'Carry Bag') !== false) {
            if (preg_match('/Carry Bag/', $category_name) ){
                if( in_array($item_name, $carry_bag_arr) ){
                    //echo $product->ITEM;
                }else{
                    continue;
                }
            }

            $qty     = 0;
            $cart_id = '';
            if(isset($cartItems[$barcode])){
                $qty     = $cartItems[$barcode];
                $cart    = $carts->where('item_id', $barcode)->first();
                $cart_id = $cart->cart_id;
            }
            $itemDesc   = $item_name;
            if($item_name == NULL){
                $itemDesc = "";
            }
             if($qty != 0){
                $decimal_qty = $product->Item->uom->selling->type == 'WEIGHT' ? '1':$qty;
            }else{
                $decimal_qty = 0;
            }


            $data[$product->Item->categories[0]->name . '~' . $product->Item->categories[0]->id][] = [
                'item_name' => utf8_encode($item_name),
                'images'    => $item_image,
                'unit_mrp'  => format_number($mrp, 2),
                'category'  => $category_name,
                'qty'       => (string) $qty,
                'decimal_qty' => $decimal_qty,
                'barcode'   => $barcode,
                'current_stock'  => $current_stock,
                'cart_id'   => $cart_id,
                'cat_id'    => $product->sku->sku,
                'weight'    => ($product->Item->uom->selling->type == 'WEIGHT' ? true : false),
                'uom'       => $product->Item->uom->purchase->name,
                'uom_conversion' => getUomUnitPrice($barcode, $price['mrp'], $v_id)
            ];
            $isimage[$product->Item->categories[0]->name]  =  $product->IS_IMAGE;
 
            }
        }

        /*Old code end*/
       
        $response = [];
        $sr       = 1;
        if (isset($data) && count($data) > 0) {
            foreach ($data as $key =>  $d) {
                $keyData    = explode('~', $key);
                //$response[] = [ 'title' => $key,'cat_id'=>$sr,'is_image'=> $isimage[$key] , 'data' => $d ]; 
                $response[] = [ 'title' => $keyData[0],'cat_id'=>$keyData[1],'is_image'=> $isimage[$keyData[0]] , 'data' => $d, 'weight' => true ]; 
                $sr++;
            }
        }else{
            $response[] = [ 'title' => 'No Data','cat_id'=>$sr,'is_image'=> '' , 'data' => '']; 
                $sr++;
        }
        $roundoff_total = round($total);
        return response()->json(['status' => 'success' , 'product_max_qty'   => (string)$product_max_qty,'catalog_data' => $response , 'product_image_link' => product_image_link().$v_id.'/', 'total' => (string)format_number($total) , 'cart_qty_total' => (string)$cart_qty_total ],200);
    }

    public function saveCatalog(Request $request){

        ini_set('max_execution_time', '120'); //120 seconds = 2 minutes
        $v_id       = $request->v_id;
        $vu_id      = $request->vu_id;
        $store_id   = $request->store_id;
        $c_id       = $request->c_id;
        $catalogs   = $request->catalogs;
        $catalogs   = json_decode($catalogs, true);
        $trans_from = $request->trans_from;

        $order_id       = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id       = $order_id + 1;

        $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();
        //dd($carts);
        $cart_barcode = $carts->pluck('item_id')->all();
        foreach($catalogs as $key => $value){
          if(in_array($value[0], $cart_barcode) ){
            unset($cart_barcode[ array_search($value[0], $cart_barcode) ] );
          }  
        }

        $cartC = new CartController;
        foreach($cart_barcode as $bar){
            // $remove_cart = $carts->where('item_id', $bar)->where('is_catalog','1')->first();
            $remove_cart = $carts->where('item_id', $bar)->first();
            //dd($remove_cart);
            if ($remove_cart) {
                $request->request->add(['cart_id' => $remove_cart->cart_id]);
                $cartC->remove_product($request);
            }
        }
        // dd($cart_barcode);

        foreach ($catalogs as $key => $value) {
            $barcode = $value[0];
            $qty = $value[1];
            $weight_flag = $value[2];

            $request->request->add(['barcode' => $barcode, 'qty' => $qty]);
            $productC = new ProductController;
            $productC->product_details($request);

        }

        $status = -1;

        $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

        $total = $carts->sum('total');

        if($total > 0.00){
            $status = 1;
        }

        if ($status == 1) {
            return response()->json(['status' => 'save_catalogs', 'message' => 'Catalogs Added', 'total' => format_number($total)], 200);
        } elseif ($status == 2) {
            return response()->json(['status' => 'save_catalogs', 'message' => 'Catalogs  Updated', 'total' => format_number($total)], 200);
        } else {
            return response()->json(['status' => 'save_catalogs', 'message' => 'Catalogs  Cleared', 'total' => format_number(0)], 200);
        }


    }

    public function saveCatalogOld(Request $request)
    {
        // echo date('Y:m:d h:i:s');die;
        $v_id       = $request->v_id;
        $vu_id      = $request->vu_id;
        $store_id   = $request->store_id;
        $c_id       = $request->c_id;
        $catalogs   = $request->catalogs;
        $catalogs   = json_decode($catalogs, true);
        $trans_from = $request->trans_from;

        $promo_cal = false;
    
        if($v_id == 24 || $v_id == 17){
            
        }else{
            $promo_cal = true;
        }

        if($request->has('promo_cal')){
            $promo_cal = $request->promo_cal;
        }

        $stores         = Store::where('v_id', $v_id)->where('store_id', $store_id)->first();
        $store_db_name  = $stores->store_db_name;
        //dd($catalogs);
        $order_id       = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id       = $order_id + 1;

        $db_structure = DB::table('vendor')->select('db_structure')->where('id', $v_id)->first()->db_structure;

        $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();
        //dd($carts);
        $cart_barcode = $carts->pluck('item_id')->all();
        $cartC = new CartController;
        //dd($catalogs);
        $status = -1;
        foreach ($catalogs as $key => $value) {
            $barcode = $value[0];
            $qty = $value[1];
            $weight_flag = $value[2];

            $stores        = DB::table('stores')->select('name', 'mapping_store_id' ,'store_db_name')->where('store_id', $store_id)->first();
            $store_name    = $stores->name;
            $store_db_name = $stores->store_db_name;

            // Getting barcode without strore tagging
            $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $barcode)->first();
            if($bar){
                $item  =  VendorSku::select('vendor_sku_detail_id','vendor_sku_flat_table.sku','vendor_sku_flat_table.variant_combi','vendor_sku_flat_table.item_id','vendor_sku_flat_table.brand_id','vendor_sku_flat_table.department_id','vendor_sku_flat_table.deleted_at' ,'vendor_sku_flat_table.hsn_code', 'vendor_sku_flat_table.tax_group_id' ,'vendor_sku_flat_table.is_active')
                ->join('stock_current_status', 'stock_current_status.item_id', 'vendor_sku_flat_table.item_id')
                ->where(['vendor_sku_flat_table.v_id' => $v_id , 'vendor_sku_flat_table.vendor_sku_detail_id' => $bar->vendor_sku_detail_id , 'stock_current_status.stop_billing' => 0])
                ->first();

                $item->barcode = $bar->barcode;

            }

            // dd($item);
            if(!empty($item)){
                $item  =  VendorSku::select('vendor_sku_detail_id','vendor_sku_flat_table.sku','vendor_sku_flat_table.variant_combi','vendor_sku_flat_table.item_id','vendor_sku_flat_table.brand_id','vendor_sku_flat_table.department_id','vendor_sku_flat_table.deleted_at' ,'vendor_sku_flat_table.hsn_code', 'vendor_sku_flat_table.tax_group_id' ,'vendor_sku_flat_table.is_active')
                ->join('stock_current_status', 'stock_current_status.item_id', 'vendor_sku_flat_table.item_id')
                ->where(['vendor_sku_flat_table.v_id' => $v_id , 'vendor_sku_flat_table.sku' => $barcode , 'stock_current_status.stop_billing' => 0])
                ->first();

                $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('vendor_sku_detail_id', $item->vendor_sku_detail_id)->first();
                $item->barcode = $bar->barcode;

                $barcodefrom = $bar->barcode;
            }else{
                return response()->json(['status' => 'product_not_found', 'message' => 'Product Not Found'], 404);
            }

            // dd($barcode);

            $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
            $order_id = $order_id + 1;

            $carts = DB::table('cart')->where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

            $exists = $carts->where('barcode', $value[0])->first();

            $check_product_in_cart_exists = $carts->where('barcode', $barcodefrom)->first();
            //dd($check_product_in_cart_exists);
            // $response = $this->check_product_exist_in_cart($request);

            // if (empty($check_product_in_cart_exists)) {
            //     $qty = 1;
            // } else {
            //     $qty = $check_product_in_cart_exists->qty + 1;
            // }

            ### Price Calculation Start
            //$priceList = $item->vprice->where('v_id',$v_id)->where('variant_combi',$item->variant_combi);
            $priceArr  = array('v_id'=>$v_id,'store_id'=>$store_id,'item'=>$item,'unit_mrp'=>'');
            // dd($priceArr);
            $config = new CartconfigController;
            //$price = $config->getprice($priceArr);
            $price  = $config->getprice($priceArr);

            $unit_mrp          =  $price['unit_mrp'];
            $r_price           =  $price['r_price'] * $qty;
            $s_price           =  !empty($price['s_price'])?$price['s_price']:$price['unit_mrp'] * $qty;
            $mrp_arrs          = $price['mrp_arrs'];
            $multiple_mrp_flag = $price['multiple_mrp_flag'];
            ### Price Calculation end

            $promoC = new PromotionController;

            //(array) $push_data = ['v_id' => $v_id, 'trans_from' => $trans_from, 'barcode' => $barcodefrom, 'qty' => (string) $qty, 'scode' => $stores->mapping_store_id];

            //$offer_data = $promoC->final_check_promo_sitewise($push_data, 0);
            
            $db_structure = DB::table('vendor')->select('db_structure')->where('id',$v_id)->first()->db_structure;

            $db_structure = DB::table('vendor')->select('db_structure')->where('id', $v_id)->first()->db_structure;

            //$item = DB::table($store_db_name.'.invitem')->select('GRPCODE', 'INVARTICLE_CODE','BARCODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE', 'LISTED_MRP', 'DESC1', 'DESC2', 'DESC3', 'DESC4', 'DESC5', 'DESC5', 'DESC6')->where('BARCODE', $barcode)->first();

            $item = $promoC->getItemDetailsForPromo(['item' => $item, 'v_id' => $v_id,'store_id'=>$store_id]);
        
            $mrp_arrs          = $item['price']['mrp_arrs'];
            $multiple_mrp_flag = $item['price']['multiple_mrp_flag'];
            $item = $item['item'];

            //dd($item);
            $params = ['v_id' => $v_id, 'trans_from' => $trans_from, 'barcode' => $barcodefrom, 'qty' =>  $qty, 'mapping_store_id' => $store_id, 'item' => $item, 'carts' => $carts, 'store_db_name' => $store_db_name, 'is_cart' => 0, 'is_update' => 0, 'db_structure' => $db_structure , 'promo_cal' => $promo_cal  ];
            $offer_data = $promoC->index($params);
            $data = $offer_data;

            $itemDet = urldecode($data['item_det']);
            $itemDet = json_decode($itemDet);
            $itemDet = collect($itemDet)->forget(['store_id','for_date','opening_qty','out_qty','int_qty','v_id','created_at','updated_at','stop_billing','deleted_at','variant_combi','sku','qty','tax_group_id','is_active']);
            

            $data['item_det'] = urlencode(json_encode($itemDet));
            $data['pdata'] = [];
            // dd($data);

            if ($exists) {

                if ($value[1] <= 0.000) {
                    $request->request->add(['cart_id' => $exists->cart_id]);
                    $cartC->remove_product($request);
                }else{
                    unset($cart_barcode[ array_search($value[0], $cart_barcode) ] );
                    $request->request->add(['qty' => $value[1],
                        'unit_mrp'      => $offer_data['unit_mrp'],
                        'unit_rsp'      => $offer_data['unit_rsp'],
                        'r_price'       => $offer_data['r_price'],
                        's_price'       => $offer_data['s_price'],
                        'discount'      => $offer_data['discount'],
                        'pdata'         => $offer_data['pdata'],
                        'get_data_of'   => 'CART_DETAILS',
                        'ogbarcode'     => $barcodefrom,
                        'barcode'       => $barcodefrom,
                        'weight_flag'   => $weight_flag,
                        'data'          => $data,
                        'multiple_mrp_flag'=>$multiple_mrp_flag,
                        'mrp_arrs'      =>$mrp_arrs,
                        'is_catalog'    =>'0',
                        'promo_cal' => $promo_cal
                    ]);
                    $cartC->product_qty_update($request);
                }
                $status = '1';
            } else {
                if ($value[1] > 0.000) {
                    //echo '1-';
                    //dd($value);
                    $request->request->add([
                        'qty' => $value[1],
                        'unit_mrp'      => $offer_data['unit_mrp'],
                        'unit_rsp'      => $offer_data['unit_rsp'],
                        'r_price'       => $offer_data['r_price'],
                        's_price'       => $offer_data['s_price'],
                        'discount'      => $offer_data['discount'],
                        'pdata'         => $offer_data['pdata'],
                        //'get_data_of'   => 'CART_DETAILS',
                        'ogbarcode'     => $barcodefrom,
                        'barcode'       => $barcodefrom,
                         'weight_flag'   => $weight_flag,
                        'data'          => $data,
                        'multiple_mrp_flag'=>$multiple_mrp_flag,
                        'mrp_arrs'      =>$mrp_arrs,
                        'is_catalog'    =>'0',
                        'promo_cal' => $promo_cal
                    ]);
                    $cartC->add_to_cart($request);
                }
                $status = '2';
            }
        }
        //dd($request);
        // dd($cart_barcode);
        foreach($cart_barcode as $bar){
            // $remove_cart = $carts->where('item_id', $bar)->where('is_catalog','1')->first();
            $remove_cart = $carts->where('item_id', $bar)->first();
            //dd($remove_cart);
            if ($remove_cart) {
                $request->request->add(['cart_id' => $remove_cart->cart_id]);
                $cartC->remove_product($request);
            }
        }

        $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

        $total = $carts->sum('total');

        if ($status == 1) {
            return response()->json(['status' => 'save_catalogs', 'message' => 'Catalogs Added', 'total' => format_number($total)], 200);
        } elseif ($status == 2) {
            return response()->json(['status' => 'save_catalogs', 'message' => 'Catalogs  Updated', 'total' => format_number($total)], 200);
        } else {
            return response()->json(['status' => 'save_catalogs', 'message' => 'Catalogs  Cleared', 'total' => format_number(0)], 200);
        }
        //print_r($
        // $carry_bags = DB::table('vendor_carry_bags')->select('BAG_ID','Name','Price')->where('V_ID', $v_id)->where('Store_ID', $store_id)->get();
        // return response()->json(['status' => 'get_carry_bags_by_store', 'data' => print_r(expression)$bags ],200);
    }
}
