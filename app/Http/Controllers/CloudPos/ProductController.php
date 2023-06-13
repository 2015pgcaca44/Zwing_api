<?php

namespace App\Http\Controllers\CloudPos;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VendorSettingController;
use App\Order;
use App\Item;
use App\Scan;
use DB;

use App\Model\Store\StoreItems;
use App\Model\Stock\StockCurrentStatus;
use App\Model\Stock\StockPointSummary;
use App\Model\Stock\StockPoints;
use App\Model\Items\VendorSkuDetails;
use App\Model\Items\VendorSku;
use App\Model\Items\VendorSkuDetailBarcode;
use App\Model\Items\VendorItem;
use App\Model\Items\ItemMediaAttributeValues;
use App\Model\Item\ItemCategory;
use App\VendorSetting;
use App\Vendor\VendorRoleUserMapping;
use App\Model\Grn\GrnList;
use Illuminate\Http\Request;
use App\Model\PriceOverRideLog;
use App\SettlementSession;
use App\CashRegister;
use App\Cart;
use App\Vendor;

class ProductController extends Controller
{

	public function __construct()
	{
		$this->middleware('auth');
		// $this->middleware('auth' , ['except' => ['product_search'] ]);
		$this->productconfig  = new ProductconfigController;
	}

	public  function createTree(&$menus, $parent_id)
	{
		$menu_add = [];
		foreach ($menus as $key => $menu) {

			if ($menu->parent_id == $parent_id) { //This is a node
				$menu_add[$menu->id] = ['text' => $menu->name, 'id' => $menu->id];
				unset($menus[$key]);
			} else { // 
				if (isset($menu_add[$menu->parent_id])) {
					$children = $this->createTree($menus, $menu->parent_id);
					if (count($children) > 0) {
						unset($menu_add[$menu->parent_id]['children']);
						$menu_add[$menu->parent_id]['children'] = $children;
					}
				}
			}
		}
		//print_r($menus);
		return $menu_add;
	}

	public function getItemDetailsForPromo($params)
	{
		
		$item      = $params['item'];
		$v_id      = $params['v_id'];
		$store_id  = $params['store_id'];
		$vu_id  =   $params['vu_id'];
		$batch_id  = !empty($params['batch_id'])?$params['batch_id']:0;
		$serial_id = !empty($params['serial_id'])?$params['serial_id']:0;
		$change_mrp= !empty($params['change_mrp'])?$params['change_mrp']:'';
		$item->ICODE = $item->sku_code;
		$item->BARCODE = $item->barcode;
		$item->DEPARTMENT_CODE = $item->department_id;
        $item->DESC1 = $item->brand_id;
        $item->SECTION_CODE = '';
        $item->DIVISION_CODE = '';
        $item->ARTICLE_CODE = '';
        $item->DESC2 = '';
        $item->DESC3 = '';
        $item->DESC4 = '';
        $item->DESC5 = '';
        $item->DESC6 = '';
        // $params['override_flag'] = 1;

		// $category = $item->category()->sortBy('parent_id')->toArray();
		// // dd($category);
		// $categoryTree = $this->createTree($category, 0);
		// $item->categoryTree = $categoryTree;

		/*for($counter = 0; $counter < 5; $counter++){
            $code = 'CCODE'. ($counter +1);
            if(isset($category[$counter])){
                $item->$code = $category[$counter]->id;
            }else{
                $item->$code = '';
            }
        }
		*/


		// $it = $item->vendorItem;
		// $item->DEPARTMENT_CODE = $it->department_id;


		// $item->DESC1 = $it->brand_id;
		// $item->DESC2 = '';
		// $item->DESC3 = '';
		// $item->DESC4 = '';
		// $item->DESC5 = '';
		// $item->DESC6 = '';

		### Price Calculation Start
        $price = [];
        if($store_id > 0){
	        $priceArr  = array('v_id'=>$v_id,'store_id'=>$store_id,'item'=>$item,'unit_mrp'=>isset($params['unit_mrp'])?$params['unit_mrp']:'');
	        $config = new CartconfigController;
	        $price = $config->getprice($priceArr);


	        /*Price override starts*/
	        if(isset($params['override_flag']) && $params['override_flag'] == "1"){
				$vSetting = new VendorSettingController;
				$role_id = VendorRoleUserMapping::select('role_id')->where('user_id',$vu_id)->first()->role_id;

				$settingsArray = ['v_id'=> $v_id,'store_id'=>$store_id,'user_id'=>$vu_id,'role_id'=>$role_id,'name'=>'cart'];
				$priceOverRideSetting = json_decode($vSetting->getSetting($settingsArray)->pluck('settings')->toArray()[0]);
			
				if($priceOverRideSetting->price_override->DEFAULT->status == 1){

					$priceArr  = array('v_id'=>$v_id,'store_id'=>$store_id,'item'=>$item,'unit_mrp'=>'');
				        $config = new CartconfigController;
				        $price = $config->getprice($priceArr);
				    if(isset($priceOverRideSetting->price_override->DEFAULT->options[0]->varince_limit->value)){
						$policy_limit = $priceOverRideSetting->price_override->DEFAULT->options[0]->varince_limit->value;
					}
					$price = $this->priceOverRideManipulation($price,$item->BARCODE,$settingsArray,$policy_limit);
				}else if($priceOverRideSetting->price_override->DEFAULT->status == 0){
						$overRideCheck = 1;
				}
			}
				/*Price override ends*/
			### Price Calculation end
	        if(!empty($batch_id) && $batch_id >0){
	        	$grnData = GrnList::where(['barcode'=>$item->barcode,'v_id'=>$v_id,'store_id'=>$store_id])->orderBy('id','desc')->get();
				foreach ($grnData as $gdata) {
					$batchPrice = $gdata->batches->where('id',$batch_id)->first();
					//dd($batchPrice->priceDetail->mrp);
					if($batchPrice){
						$batchMrp   = $batchPrice->priceDetail->special_price;	
						if(empty($batchMrp)){
							$batchMrp   = $batchPrice->priceDetail->rsp;	
							if(empty($batchMrp)){
							 $batchMrp   = $batchPrice->priceDetail->mrp;	
							}
						}	
					}
				}
				 if(isset($params['override_flag']) && $params['override_flag'] == "1"){
				 	$item->LISTED_MRP = $change_mrp;
					$item->MRP 		  = $change_mrp;
					$item->MRP_ARRS   = [];
				 }else{
					$item->LISTED_MRP = $batchMrp;
					$item->MRP 		  = $batchMrp;
					$item->MRP_ARRS   = [];
				}
	        }else if(!empty($serial_id) && $serial_id >0){
	        	$serialMrp = null;
	        	$grnData = GrnList::where(['barcode'=>$item->barcode,'v_id'=>$v_id,'store_id'=>$store_id])->orderBy('id','desc')->get();
				foreach ($grnData as $gdata) {
					$serialPrice = $gdata->serials->where('id',$serial_id)->first();
					//dd($batchPrice->priceDetail->mrp);
					if($serialPrice){
						$serialMrp   = $serialPrice->priceDetail->special_price;	
						if(empty($serialMrp)){
							$serialMrp   = $serialPrice->priceDetail->rsp;	
							if(empty($serialMrp)){
							 $serialMrp   = $serialPrice->priceDetail->mrp;	
							}
						}	
					}
				}

				if(isset($params['override_flag']) && $params['override_flag'] == "1"){
				 	$item->LISTED_MRP = $change_mrp;
					$item->MRP 		  = $change_mrp;
					$item->MRP_ARRS   = [];
				 }else{
				 	if(!$serialMrp){
						return ['status' => 'fail' , 'message' => 'Unable to find serial mrp', 'info' => 'serial id : '.$serial_id.' Unable to find mrp or serail is deleted or unable to find serial id'];
					}
				$item->LISTED_MRP = $serialMrp;
				$item->MRP 		  = $serialMrp;
				$item->MRP_ARRS   = [];
			}

	        }
	        else if(!empty($change_mrp) && $change_mrp >0){
				$getNewMrp = collect($price['mrp_arrs'])->transform(function($itm) use($change_mrp){
				if($itm == $change_mrp){
					return ['LISTED_MRP'=> $itm]; 
				}else{
					return ['LISTED_MRP'=> $change_mrp];
				}
				});
				//Use multiple mrp
				$getNewMrp = $getNewMrp->where('LISTED_MRP',$change_mrp)->first();
				if($getNewMrp){
					$mainMrp  = format_number($getNewMrp['LISTED_MRP']);
					$mainRsp  = format_number($getNewMrp['LISTED_MRP']);
					
				}else{

					$mainMrp  = format_number($item->LISTED_MRP);
					$mainRsp  = format_number($item->MRP);
				}
				$item->LISTED_MRP = $mainMrp;
				$item->MRP = $mainMrp;
				$item->MRP_ARRS = $price['mrp_arrs'];
	        }else{
	         $item->LISTED_MRP = $price['unit_mrp'];
			 $item->MRP = (!empty($price['s_price'])) ? $price['s_price'] : $price['unit_mrp'];
			 $item->MRP_ARRS = $price['mrp_arrs'];
	        }

			
		}
		return ['item' => $item, 'price' => $price];
	
	}


	private function priceOverRideManipulation($price,$barcode,$data,$policyLimit)
	{
		$v_id = $data['v_id'];
		$store_id = $data['store_id'];
		$vu_id = $data['user_id'];
        $unit_mrp   = $price['unit_mrp'];
        $r_price    = $price['r_price'];
        $s_price    = $price['s_price'];
        $mrp    = $price['mrp'];
        $variance = null;
        $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode','item_id')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $barcode)->first();
        $item = null;
        if($bar){
	        /*$item  =  VendorSkuDetails::select('vendor_sku_details.is_priceoverride_active','vendor_sku_details.price_overide_variance','vendor_sku_details.item_id')
				->with(['vendorItem' => function($query) use ($v_id){
				$query->where('v_id',$v_id);
				}])
				->where(['vendor_sku_details.v_id' => $v_id , 'vendor_sku_details.id' => $bar->vendor_sku_detail_id])
				->first();*/
			$item  =  VendorItem::select('vendor_items.allow_price_override','vendor_items.price_override_variance','vendor_items.item_id')
			->where(['vendor_items.v_id' => $v_id , 'vendor_items.item_id' => $bar->item_id])
			->first();	
        }

		if($item->allow_price_override == "1"){
			$variance = min($item->price_override_variance,$policyLimit); // Pick the lowest value
			$price['unit_mrp'] = format_number($unit_mrp - ($unit_mrp*$variance/100));
			$price['r_price'] = format_number($r_price - ($r_price*$variance/100));
			$price['s_price'] = format_number($s_price - ($s_price*$variance/100));
			$price['mrp'] = format_number($mrp - ($mrp*$variance/100));

			if(count($price['mrp_arrs']) > 0){
				$price['mrp_arrs'] = collect($price['mrp_arrs'])->map(function($item) use ($variance) {
						 return format_number($item - ($item*$variance/100));
				})->toArray();
               
			}
		}

		// $current_date = date('Y-m-d'); 

		// $settlementSession = SettlementSession::select('id','cash_register_id')->where(['v_id' => $v_id ,'store_id' => $store_id , 'vu_id' => $vu_id , 'settlement_date' => $current_date ])->orderBy('opening_time','desc')->first();
		// $priceOverride = new PriceOverRideLog;
		// $priceOverride->v_id = $v_id;
		// $priceOverride->store_id = $store_id;
		// $priceOverride->barcode = $barcode;
		// $priceOverride->item_id = $item->item_id;
		// $priceOverride->order_details_id = "";
		// $priceOverride->percentage = $variance;
		// $priceOverride->terminal_id = $settlementSession->cash_register_id;
		// $priceOverride->session_id = $settlementSession->id;
		// $priceOverride->approved_by = $vu_id;
		// $priceOverride->save();
		return $price;
	}

	public function getItem($v_id, $bar){

		$item  =  VendorSku::select('vendor_sku_flat_table.vendor_sku_detail_id','vendor_sku_flat_table.sku','vendor_sku_flat_table.sku_code','vendor_sku_flat_table.variant_combi','vendor_sku_flat_table.item_id','vendor_sku_flat_table.brand_id','vendor_sku_flat_table.department_id','vendor_sku_flat_table.deleted_at' ,'vendor_sku_flat_table.hsn_code','vendor_sku_flat_table.tax_type' , 'vendor_sku_flat_table.tax_group_id' ,'vendor_sku_flat_table.is_active','vendor_sku_flat_table.uom_conversion_id')
				->with(['vendorItem' => function($query) use ($v_id){
				$query->where('v_id',$v_id);
				}]);
				// if($checkProductInventory) {
				// 	$item = $item->join('stock_point_summary', 'stock_point_summary.sku_code', 'vendor_sku_flat_table.sku_code')
				// 		->where('stock_point_summary.stop_billing' , '0')
				// 		->where('stock_point_summary.store_id' , $store_id)
				// 		;
				// }
				
				$item = $item->where('vendor_sku_flat_table.vendor_sku_detail_id' , $bar->vendor_sku_detail_id)
						->where('vendor_sku_flat_table.v_id' , $v_id)
						->first();
				if($item){
					$item->barcode = $bar->barcode;
				}
		return $item;
	}

	public function product_details(Request $request)
	{	
			// dd($request->all());
			$start_time = microtime(true); 
			$v_id 		= $request->v_id;
			$trans_from = $request->trans_from;
			$store_id 	= $request->store_id;
			$barcode 	= $request->barcode;
			$udidtoken  = $request->udidtoken;
			if($request->has('trans_type') && in_array($request->trans_type, ['exchange','return','order'])){
				$trans_type = $request->trans_type;
			} else {
				$trans_type = 'sales';
			}
			$vu_id = 0;
			if ($request->has('vu_id')) {
				$vu_id = $request->vu_id;
			}
			$c_id     	= $request->c_id;
			$scanFlag 	= $request->scan;
			$product_data = array();
			$stores 	   = DB::table('stores')->select('name', 'mapping_store_id', 'store_db_name')->where('store_id', $store_id)->first();
			$store_name    = $stores->name;
			$store_db_name = $stores->store_db_name;
			$batch_id      = !empty($request->batch_id)?$request->batch_id:0;
			$serial_id     = !empty($request->serial_id)?$request->serial_id:0;
			$actual_unit_mrp = !empty($request->unit_mrp)?$request->unit_mrp:0;
			$change_mrp    = !empty($request->change_mrp)?round($request->change_mrp,2):'';
			$checkProductInventory = false;

			$role = VendorRoleUserMapping::select('role_id')->where('user_id',$vu_id)->first();
        	$role_id  = $role->role_id;

			// Getting barcode without store tagging

        	$item = null;
        	$bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode','item_id','sku_code')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $barcode)->first();
        	if(!$bar){
        		return response()->json(['status' => 'product_not_found', 'message' => 'Product Not Found (Barcode does not exists)'], 404);
        	}
        	$item = VendorItem::select('track_inventory','negative_inventory','negative_inventory_override_by_store_policy')->where('item_id', $bar->item_id)->where('v_id', $v_id)->first();

        	//Getting Stock Setting 
        	$sParams = ['v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $vu_id, 'role_id' => $role_id, 'trans_from' => $trans_from,'udidtoken'=>$udidtoken];
        	$vendorS = new VendorSettingController;
        	$stockSetting = $vendorS->getStockSetting($sParams);
        	$productMaxQty = 50;
        	$productMaxQty = $vendorS->getProductMaxQty($sParams);

        	$negative_stock_billing_status = 0;
        	if($stockSetting->negative_stock_billing->status != 0){
        		if($item->negative_inventory_override_by_store_policy == '1' ){

	        		$negative_stock_billing_status = $item->negative_inventory;
	        	}
        		$negative_stock_billing_status = $stockSetting->negative_stock_billing->status;
        	}
        	

        	//This condition is added for tagging batch and serial id exchange items
			if($trans_type == 'exchange'){
				if ($request->has('exchange_against_invoice_id') && $request->exchange_against_invoice_id > 0) {

					$invoice = Invoice::select('id')->where('invoice_id',$request->exchange_against_invoice_id)->first();
					if($invoice){
						$invoice_details = InvoiceDetails::select('batch_id','serial_id','unit_mrp','unit_rsp')->where('t_order_id', $invoice->id)->where('barcode', $request->barcode)->first();
						if($invoice_details){

							$invoiceExchange = $vendorS->getAgainstInvoiceExchange($sParams);
							if($invoiceExchange->status == 1 && $invoiceExchange->value= 'previous_invoice'){
								$batch_id = $request->batch_id;
								$serial_id = $request->serial_id;
								$change_mrp = $request->unit_mrp;
							}
						}else{
							return response()->json(['status'=> 'fail' , 'message' => 'Unable to find Exchanged Items' ] );
						}
			        }else{
			            return response()->json(['status'=> 'fail' , 'message' => 'Unable to find Exchange Invoice ID' ] );
			        }
			    }
			}

        	
        	// Check Policies
        	$vendorAuth = Vendor::where([ 'v_id' => $v_id, 'store_id' => $store_id, 'id' => $vu_id ])->first();
        	if($trans_type == 'order' && $vendorAuth->order_inventory_blocking_level['setting'] == 'order_created') {
        		$checkProductInventory = true;
        	}

        	if($bar){

				$item = $this->getItem($v_id, $bar);
				if($item){
					$item->barcode = $bar->barcode;
				}else{

					$dbName = getDatabaseName($v_id);
					DB::statement('call '.$dbName.'.CreateVendorSkuFlatTable(?,?,?)', [$v_id, $bar->item_id, $dbName]); 

					$sitem =StoreItems::where('v_id', $v_id)->where('store_id', $store_id)->where('sku_code', $bar->sku_code)->first();	
					if(!$sitem){
						return response()->json(['status' => 'fail', 'data' => 'This item is not allocated to store'], 200);
					}
					$item = $this->getItem($v_id, $bar);

				}
			}

			if (!$item) {
				$item  =  VendorSku::select('vendor_sku_flat_table.vendor_sku_detail_id','vendor_sku_flat_table.sku','vendor_sku_flat_table.sku_code','vendor_sku_flat_table.variant_combi','vendor_sku_flat_table.item_id','vendor_sku_flat_table.brand_id','vendor_sku_flat_table.department_id','vendor_sku_flat_table.deleted_at' ,'vendor_sku_flat_table.hsn_code','vendor_sku_flat_table.tax_type' , 'vendor_sku_flat_table.tax_group_id' ,'vendor_sku_flat_table.is_active','vendor_sku_flat_table.uom_conversion_id')
				->with(['vendorItem' => function($query) use ($v_id){
				$query->where('v_id',$v_id);
				}]);

				// if($checkProductInventory) {
				// 	$item = $item->join('stock_point_summary', 'stock_point_summary.sku_code', 'vendor_sku_flat_table.sku_code')
				// 		->where('stock_point_summary.stop_billing' , '0')
				// 		->where('stock_point_summary.store_id' , $store_id)
				// 		;
				// }

				$item = $item->where('vendor_sku_flat_table.sku' , $barcode)
						->where('vendor_sku_flat_table.v_id' , $v_id)
						->first();
				if (!$item) {

					return response()->json(['status' => 'product_not_found', 'message' => 'Product Not Found'], 404);
				} else {

					$bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('vendor_sku_detail_id', $item->vendor_sku_detail_id)->first();

					$barcodefrom = $item->barcode = $bar->barcode;
					$sku_code = $item->sku_code;
				}
			} else {
				$barcodefrom = $item->barcode;
				$sku_code = $item->sku_code;
			}

			//Check Batches of product
			if($item->vendorItem->track_inventory_by == 'BATCH' && $request->has('batch_id') && $batch_id == 0) {
				$batches = array();
				$grnData = GrnList::where(['barcode'=>$barcode,'v_id'=>$v_id,'store_id'=>$store_id])->orderBy('id','desc')->get();
				foreach ($grnData as $gdata) {
					foreach ($gdata->batches as $batch) {
					  if($batch->batch_no != ''){
						$batches[]  = array('id'=>$batch->id,'code'=>$batch->batch_no,'mfg_date'=>$batch->mfg_date,'exp_date'=>$batch->exp_date,'validty'=>$batch->valid_months,'type'=>'batch','mrp'=>$batch->priceDetail->mrp);
					   }
					}	
				}
				//return response()->json(['status' => 'get_product_batch', 'data' => $batches], 200);
				return response()->json(['status' => 'fail', 'data' => $batches,'message' => 'This product cannot be added to cart as no batch has been added. Please contact your store manager'], 200);
			}

			//Check Serial of product
			if($item->vendorItem->track_inventory_by == 'SERIAL' && $request->has('serial_id') && $serial_id == 0) {
				$batches = array();

				$grnData = GrnList::where(['barcode'=>$barcode,'v_id'=>$v_id,'store_id'=>$store_id])->orderBy('id','desc')->get();
			
				foreach ($grnData as $gdata) {
					foreach ($gdata->serials as $serial) {
						$batches[]  = array('id'=>$serial->id,'code'=>$serial->serial_no,'type'=>'serial','mrp'=>$serial->priceDetail->mrp);
					}	
				}
				//return response()->json(['status' => 'get_product_batch', 'data' => $batches], 200);
				return response()->json(['status' => 'fail', 'data' => $batches,'message' => 'This product cannot be added to cart as no batch has been added. Please contact your store manager'], 200);
			}


			$stockPoint = StockPoints::where(['v_id'=>$v_id,'store_id'=>$store_id,'is_sellable'=>'1','is_active'=>'1'])->first()->id;

			$stock = StockPointSummary::select(DB::raw("SUM(qty) as total_qty"))->where(['v_id' => $v_id , 'sku_code' => $item->sku_code, 'item_id' => $item->item_id, 'stop_billing' => '0','stock_point_id'=>$stockPoint, 'store_id' => $store_id])->orderBy('id','desc')->first();
			//Temporary Condition need to remove in future
			if(!$stock){
				$stock = StockPointSummary::select(DB::raw("SUM(qty) as total_qty"))->where(['v_id' => $v_id , 'barcode' => $barcode, 'item_id' => $item->item_id, 'stop_billing' => '0','stock_point_id'=>$stockPoint, 'store_id' => $store_id])->orderBy('id','desc')->first();
			}

		
			if($negative_stock_billing_status == 0 && $checkProductInventory){
				if ($stock->total_qty <= 0){
					return response()->json(['status' => 'negative_inventory', 'message' => $checkProductInventory ? 'This product cannot be added to the order, please scan or select another product.' : 'Negative stock billing is not allowed'], 420);
				}
			}
			


			// if($checkSetting->negative_stock_billing->status === 0 && !$stock){
			// 	return response()->json(['status' => 'fail', 'message' => 'Negative stock billing is not allowed'], 404);
			// }

			// if($checkSetting->negative_stock_billing->is_warning === 1){
			// 	$warning = $checkSetting->negative_stock_billing->warning_msg;
			// }

			// $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
			$order_id = Order::where('user_id', $c_id)->whereIn('status', ['success','pending','confirm','picked','packing','shipped','cancel'])->count();
			$order_id = $order_id + 1;

			$promoData = ['item' => $item, 'v_id' => $v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'change_mrp'=>$change_mrp,'batch_id'=>$batch_id,'serial_id'=>$serial_id];
			// if($request->has('override_flag')){
			// 	$promoData['override_flag'] = $request->override_flag;
			// 	Cart::where('barcode',$barcode)->where('transaction_type','sales')->where('unit_mrp',$request->unit_mrp)->where('v_id',$v_id)->where('store_id',$store_id)->delete();
			// }
			$itemvs = $this->getItemDetailsForPromo($promoData);
			if(isset($itemvs['status'])){
				if($itemvs['status'] == 'fail'){
					return response()->json($itemvs);
				}
			}
			if(empty($change_mrp) || $change_mrp == '0'){
				$change_mrp  = $itemvs['item']->MRP;
			}
			/*else{

				$carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->where('sku_code', $sku_code);

				if($item->vendorItem->has_batch == 1 && $batch_id != 0){
					$carts = $carts->where('batch_id',$batch_id);
				}
				if($item->vendorItem->has_serial == 1 && $serial_id != 0){
					$carts = $carts->where('serial_id',$serial_id);
				}

				$carts = $carts->where('unit_mrp',$request->unit_mrp)->first();

				$carts->unit_mrp = $change_mrp;
				$carts->save();

			}*/

			$carts = DB::table('cart')->where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->where('barcode', $barcode)->get();
			$check_product_in_cart_exists = $carts->where('sku_code', $sku_code);
			if($item->vendorItem->has_batch == 1 && $batch_id != 0){
				$check_product_in_cart_exists = $check_product_in_cart_exists->where('batch_id',$batch_id);
			}
			if($item->vendorItem->has_serial == 1 && $serial_id != 0){
				$check_product_in_cart_exists = $check_product_in_cart_exists->where('serial_id',$serial_id);
			}
			if(!empty($change_mrp) && $change_mrp > 0){
				$check_product_in_cart_exists = $check_product_in_cart_exists->where('unit_mrp',$change_mrp);
			}
	
			$check_product_in_cart_exists = $check_product_in_cart_exists->first();

			// dd($check_product_in_cart_exists);
			if (empty($check_product_in_cart_exists)) {
				if ($request->has('qty') && $request->qty > 0.0) {
					$qty = $request->qty;
				} else {
					$qty = 1;
				}
			} else {
				if ($item->uom->selling->type == 'WEIGHT') {
				  if ($request->has('qty') && $request->qty > 0.0) {
					$qty = $request->qty;
				  } else {
					$qty = $check_product_in_cart_exists->qty + 1;
				  }
				} else {
				  if ($request->has('qty') && $request->qty > 0.0) {
					$qty = $request->qty;
				  } else {
					$qty = $check_product_in_cart_exists->qty + 1;
				}
				// $qty = $check_product_in_cart_exists->qty + 1;
				}
			}

			// dd($qty);
			if($productMaxQty < $qty){
				return response()->json(['status' => 'fail', 'message' => 'Max qty for individual product is '.$productMaxQty ], 200);
			}

			if($trans_type == 'exchange'){

			}
 			// checking current stock status & stop negative stock billing
 			if(!in_array($request->trans_type, ['return','order'])) {
				$vSetting = new VendorSettingController;

				$param = ['v_id'=> $v_id,'store_id'=>$store_id,'user_id'=>$request->vu_id,'role_id'=>$role_id,'name'=>'stock'];
				$stock_setting = json_decode($vSetting->getSetting($param)->pluck('settings')->toArray()[0]);

				// $stock = $item->currentStock->where('store_id', $store_id)->sortByDesc('created_at')->first();
				$stockSetting = VendorSetting::where('v_id', $v_id)->where('name', 'stock')->first();
				$checkSetting = json_decode($stockSetting['settings']);

				if ($stock == null) {
					return response()->json(['status' => 'fail', 'message' => 'Stock is not available'], 404);
				} else {
					$currentStock = (float) $stock->total_qty;

				if ($stock_setting) {
					if ((float) $currentStock < (float)$qty) {
					 if ($stock_setting->negative_stock_billing->status === 0) {
						return response()->json(['status' => 'fail', 'message' => 'Negative stock billing is not allowed'], 200);
					 }
					}
				 }
				}
			}

			$promoC = new PromotionController;


			//(array) $push_data = ['v_id' => $v_id, 'trans_from' => $trans_from, 'barcode' => $barcodefrom, 'qty' => (string) $qty, 'scode' => $stores->mapping_store_id];

			//$offer_data = $promoC->final_check_promo_sitewise($push_data, 0);

			// $db_structure = DB::table('vendor')->select('db_structure')->where('id', $v_id)->first()->db_structure;

			//$item = DB::table($store_db_name.'.invitem')->select('GRPCODE', 'INVARTICLE_CODE','BARCODE', 'CCODE1', 'CCODE2', 'CCODE3', 'CCODE4', 'CCODE5', 'CCODE6', 'ICODE', 'GENERATED', 'MRP', 'CNAME1', 'CNAME2', 'INVHSNSACMAIN_CODE', 'STOCKINDATE', 'LISTED_MRP', 'DESC1', 'DESC2', 'DESC3', 'DESC4', 'DESC5', 'DESC5', 'DESC6')->where('BARCODE', $barcode)->first();
			//$start_time = microtime(TRUE);

			$promoData = ['item' => $item, 'v_id' => $v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'change_mrp'=>$change_mrp,'batch_id'=>$batch_id,'serial_id'=>$serial_id];
			if($request->has('override_flag')){

				$vSetting = new VendorSettingController;

				$param = ['v_id'=> $v_id,'store_id'=>$store_id,'user_id'=>$vu_id,'role_id'=>$role_id,'name'=>'cart'];
				$cart_setting = json_decode($vSetting->getSetting($param)->pluck('settings')->toArray()[0]);
				if(!$cart_setting->price_override->DEFAULT->status == 1){
					return response()->json(['status'=>'fail','message'=>'Price override is not enabled for this store'],200);
				}
				
				$promoData['override_flag'] = $request->override_flag;
			}
			$item = $this->getItemDetailsForPromo($promoData);

			//echo (' Time is milli sec: '.(microtime(true) - $start_time));exit;
			$mrp_arrs      	   = $item['price']['mrp_arrs'];
			$multiple_mrp_flag = $item['price']['multiple_mrp_flag'];
			$item 			   = $item['item'];
			$promo_cal 		   = false;

			$vendorS = new VendorSettingController;
            $sParams = ['v_id' => $v_id,'store_id'=>$store_id,'user_id'=>$vu_id,'role_id'=>$role_id,'trans_from' => $trans_from];
            $promotionS = $vendorS->getPromotionSetting($sParams);

            if($promotionS->status ==0 || $promotionS->options[0]->promo_apply_type->value == 'manual'){
			//if($v_id == 23  || $v_id == 24 || $v_id ==11 || $v_id ==76){

			}else{
				$promo_cal = true;
			}
			if($request->has('promo_cal')){
				$promo_cal = $request->promo_cal;
			}
			// if( $v_id ==76){
			// 	$promo_cal = false;
			// }
			//$promo_cal = false;

			$sign = 1;
			if($trans_type == 'exchange'){
				$promo_cal = false;
				$sign = -1;
			}


 			$params = ['v_id' => $v_id, 'trans_from' => $trans_from, 'barcode' => $barcodefrom, 'sku_code'=> $sku_code ,'batch_id'=>$batch_id,'serial_id'=>$serial_id, 'change_mrp'=>$change_mrp,'qty' =>  $qty, 'mapping_store_id' => $store_id, 'item' => $item, 'carts' => $carts, 'store_db_name' => $store_db_name, 'is_cart' => 0, 'is_update' => 0,
			//'db_structure' => $db_structure ,
			'promo_cal' => $promo_cal , 'trans_type' => $trans_type ];
			// $starttime = microtime(TRUE);
			// echo $qty;die;
		
			$offer_data = $promoC->index($params);

			// Get Only Product Details
			if($request->has('is_product_details') && $request->is_product_details) {
				$offer_data['product_name'] = $item->name;
			   	return response()->json(['status' => 'get_product_details', 'message' => 'Get Product Details', 'data' => $offer_data, 'product_image_link' => product_image_link(), 'store_name' => $store_name], 200);
			}
	
			// dd($offer_data);
			// echo (' Time is sec: '.(microtime(true) - $start_time));exit;
			$data = $offer_data;
			$itemDet = urldecode($data['item_det']);
			$itemDet = json_decode($itemDet);
			$itemDet = collect($itemDet)->forget(['store_id','for_date','opening_qty','out_qty','int_qty','v_id','created_at','updated_at','stop_billing','deleted_at','variant_combi','sku','qty','tax_group_id','is_active']);
			$data['item_det'] = urlencode(json_encode($itemDet));
			$data['pdata'] = [];
 			 
			if ($trans_from == 'ANDROID_VENDOR' || $trans_from == 'IOS_VENDOR' || $trans_from == 'CLOUD_TAB' || $trans_from == 'CLOUD_TAB_ANDROID' || $trans_from == 'CLOUD_TAB_WEB') {

			$request->merge(['barcode' => $barcodefrom,'sku_code' => $sku_code, 'ogbarcode' => $barcodefrom,'batch_id' => $batch_id ,'serial_id'=>$serial_id,'change_mrp'=>$change_mrp,'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'data' => $data, 'multiple_mrp_flag' => $multiple_mrp_flag, 'mrp_arrs' => $mrp_arrs, 'is_catalog' => '0', 'weight_flag' => $offer_data['weight_flag'] , 'target_offer' => $offer_data['target_offer'] , 'promo_cal' => $promo_cal , 'get_assortment_count' => $offer_data['get_assortment_count'], 'extra_charge' => $offer_data['extra_charge'] , 'net_amount' => $offer_data['net_amount'], 'charge_details' => $offer_data['charge_details']  ]);
			$cartC = new CartController;

			if ($offer_data['weight_flag'] == 1 && !$request->has('override_flag')) {
			  if (empty($check_product_in_cart_exists)) {
				return $cartC->add_to_cart($request);
			  } else {

				$data = (object) ['trans_type'=>$trans_type,'v_id' => $v_id, 'store_id' => $store_id, 'c_id' => $c_id,'sku_code' => $sku_code, 'barcode' => $barcodefrom,'batch_id' => $batch_id, 'serial_id'=>$serial_id,'change_mrp'=>$change_mrp,'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'data' => $data, 'trans_from' => $trans_from, 'vu_id' => $vu_id, 'weight_flag' => $offer_data['weight_flag'] , 'target_offer' => $offer_data['target_offer'] , 'promo_cal' => $promo_cal , 'get_assortment_count' => $offer_data['get_assortment_count'] , 'request' => $request , 'extra_charge' => $offer_data['extra_charge'] , 'net_amount' => $offer_data['net_amount'], 'charge_details' => $offer_data['charge_details'] ];
				if($request->has('cust_gstin') && $request->cust_gstin !=''){
		            $data->cust_gstin = $request->cust_gstin;
		        }
		        if($request->has('override_flag') && $request->get('override_flag')){
			        	$data->override_flag = $request->override_flag;
			        }
				return $cartC->update_to_cart($data);
			 }
			} else {
				// dd('boo');
				if (empty($check_product_in_cart_exists) && !$request->has('override_flag')) {
					return $cartC->add_to_cart($request);
				} else {
				//return $cartC->product_qty_update($request);
					$data = (object) ['trans_type'=>$trans_type,'v_id' => $v_id, 'store_id' => $store_id, 'c_id' => $c_id, 'barcode' =>  $barcodefrom,'sku_code' => $sku_code,'batch_id' => $batch_id ,'serial_id'=>$serial_id,'change_mrp'=>$change_mrp,'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'actual_unit_mrp' => $actual_unit_mrp, 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'data' => $data, 'trans_from' => $trans_from, 'vu_id' => $vu_id, 'weight_flag' => $offer_data['weight_flag'] , 'target_offer' => $offer_data['target_offer'] , 'promo_cal' => $promo_cal , 'get_assortment_count' => $offer_data['get_assortment_count'] , 'request' => $request , 'extra_charge' => $offer_data['extra_charge'] , 'net_amount' => $offer_data['net_amount'], 'charge_details' => $offer_data['charge_details'] ];
					if($request->has('cust_gstin') && $request->cust_gstin !=''){
			            $data->cust_gstin = $request->cust_gstin;
			        }

			        if($request->has('override_flag') && $request->get('override_flag')){
			        	$data->override_flag = $request->override_flag;
			        }
			        // dd($data);

					return $cartC->update_to_cart($data);
				}
			  }
			} else if ($trans_from == 'ANDROID_KIOSK' || $trans_from == 'IOS_KIOSK') {
			       $request->request->add([
			       	'qty' => $offer_data['qty'],
			       	'unit_mrp' 		=> $offer_data['unit_mrp'],
			       	'unit_rsp' 		=> $offer_data['unit_rsp'],
			       	'r_price' 		=> $offer_data['r_price'],
			       	's_price' 		=> $offer_data['s_price'],
			       	'discount' 		=> $offer_data['discount'],
			       	'pdata' 		=> $offer_data['pdata'],
			       	'get_data_of' 	=> 'CART_DETAILS',
			       	'ogbarcode'		=> $barcodefrom,
			       	'barcode'		=> $barcodefrom,
			       	'sku_code' => $sku_code,
			       	'data' 			=> $data,
			       	'multiple_mrp_flag' => $multiple_mrp_flag,
			       	'mrp_arrs'		=> $mrp_arrs,
			       	'is_catalog' 	=> '0',
			       	'get_assortment_count' => $offer_data['get_assortment_count'] 
			       ]);

			       $cartC = new CartController;
			       if ($qty == 1) {
			       	return $cartC->add_to_cart($request);
			       } else {
			       	$data = (object) ['v_id' => $v_id, 'store_id' => $store_id, 'c_id' => $c_id, 'barcode' => $barcodefrom, 'sku_code' => $sku_code, 'qty' => $offer_data['qty'], 'unit_mrp' => $offer_data['unit_mrp'], 'unit_rsp' => $offer_data['unit_rsp'], 'r_price' => $offer_data['r_price'], 's_price' => $offer_data['s_price'], 'discount' => $offer_data['discount'], 'pdata' => $offer_data['pdata'], 'data' => $data, 'trans_from' => $trans_from, 'vu_id' => $vu_id, 'get_data_of' => 'CART_DETAILS' , 'promo_cal' => $promo_cal , 'get_assortment_count' => $offer_data['get_assortment_count'] , 'extra_charge' => $offer_data['extra_charge'] , 'net_amount' => $offer_data['net_amount'], 'charge_details' => $offer_data['charge_details'] ];

				    if($request->has('cust_gstin') && $request->cust_gstin !=''){
			            $data->cust_gstin = $request->cust_gstin;
			        }

			       	return $cartC->update_to_cart($data);
			       }
			   } else {
			   	$offer_data['product_name'] = $item->name;
			   	return response()->json(['status' => 'get_product_details', 'message' => 'Get Product Details', 'data' => $offer_data, 'product_image_link' => product_image_link(), 'store_name' => $store_name], 200);
			   }
		
	}//End of product_details

	public function product_search_through_engine(Request $request){

		$v_id 		 = $request->v_id;
		$store_id 	 = $request->store_id;
		$search_term = trim($request->search_term);
		$product 	 = [];
		$timestamp   = date('Y-m-d');

		$paginateLimit = 10;
		$page        = (!empty($request->page))?$request->page:1;

		if($request->has('limit')){
			$paginateLimit = $request->limit;
		}
		JobdynamicConnection($v_id);

		$productList = [];
		if(!empty($search_term)){
			$var = function ($response) use(&$v_id, &$store_id){

				$price = \App\Model\Items\VendorSkuDetails::where('sku_code',$response->sku_code)->first();
				$priceArr  = ['v_id' => $v_id, 'store_id' => $store_id, 'item' => $price, 'unit_mrp' => ''];
		        $config = new \App\Http\Controllers\CloudPos\CartconfigController;
		        $getPrice = $config->getprice($priceArr);

		        $batches = [];
		        $serials = [];
		        // Check Batch List
				if($price->is_batch == 1) {
		          $grnData = GrnList::where([ 'barcode' => $price->barcode, 'v_id' => $v_id, 'store_id' => $store_id ])->orderBy('id','desc')->get();
		          foreach ($grnData as $gdata) {
		            foreach ($gdata->batches as $batch) {
		              if($batch->batch_no != '') {
		              	// dd($batch);
		                $validty = !empty($batch->valid_months) ? $batch->valid_months : 'N/A';
		                $batches[] = [ 'id' => $batch->id, 'code' => $batch->batch_no, 'mfg_date' => $batch->mfg_date, 'exp_date' => $batch->exp_date, 'validty' => $validty, 'type' => 'batch', 'mrp' => $batch->priceDetail->mrp ];
		              }
		            } 
		          }
		        }

		        // Check Serial List
		        if($price->is_serial == 1) {
			        $grnData = GrnList::where([ 'barcode' => $price->barcode, 'v_id' => $v_id, 'store_id' => $store_id ])->orderBy('id','desc')->get();
			        foreach ($grnData as $gdata) {
			          foreach ($gdata->serials as $serial) {
			            $serials[]  = [ 'id' => $serial->id, 'code' => $serial->serial_no, 'type' => 'serial', 'mrp' => $serial->priceDetail->mrp ];
			          } 
			        }
			    }
		    

				$data = [
					'name' => $response->name,
					'barcode' => $response->barcodes[0],
					'barcodes' => $response->barcodes,
					'weight' => $response->purchase_uom_type,
					'category' => $response->cat_name_1,
					'brand' => $response->brand_name,
					'variant' => $response->sku,
					'item_id' => $response->item_id,
					'variant_combi' => $response->sku,
					'is_batch' => $response->has_batch,
					'is_serial' => $response->has_serial,
					'mrp' => $getPrice['mrp'],
					'multiple_price' => $getPrice['mrp_arrs'],
					'batch' => $batches,
					'serial' => $serials
				];
				return (Object)$data;
			};

			$product = new VendorSku;
			$productList = $product->search()->select('sku_code','name','barcodes','purchase_uom_type','cat_name_1','brand_name','sku','item_id','has_batch','has_serial')
				->where('name','LIKE','%'.$search_term.'%')
				->where('barcode','LIKE','%'.$search_term.'%')
				->where('store_id', $store_id)
				->where('is_active', '1')
				// ->where('deleted_at', null)
				->mapResponse($var)->paginate($paginateLimit, $page);
		}

		$newProductList = [];
		if(is_array($productList['data']) && count($productList['data']) > 0) {
			

			$newProductList= ['data' => $productList['data'], 'per_page' => $paginateLimit, 'current_page' => $page, 'from' => '', 'to' => '' , 'total' => $productList['total'] ];
			$message = 'Get Product Search';

		}else{
			$message = 'No Data Found';
		}

		$productList = $newProductList;

		return ['productList' => $productList , 'message' => $message];

	}

	public function product_search(Request $request)
	{
		$productList = [];
		$productList = '';
		$productSearch = env('DEFAULT_ONLINE_SEARCH'); //SEARCH_ENGINE or  DATABASE
		if($productSearch == 'SEARCH_ENGINE'){
			$response = $this->product_search_through_engine($request);
			$productList = $response['productList'];
			$message = $response['message'];
		}else{

			$v_id 		 = $request->v_id;
			$store_id 	 = $request->store_id;
			$search_term = trim($request->search_term);
			$product 	 = [];
			$timestamp   = date('Y-m-d');
			$paginateLimit = 10;
			JobdynamicConnection($v_id);

			if($request->trans_from == 'CLOUD_TAB_WEB') {
				$paginateLimit = $request->limit;
			}

			if(!empty($search_term)){
				// 		$product   = Item:: where(['vendor_sku_details.v_id'=>$v_id,'stock_current_status.stop_billing'=>0])
				// 		->select('items.name as name','vendor_sku_details.barcode as barcode','uom.type as weight','item_prices.mrp as mrp','item_category.name as category','item_brand.name as brand','vendor_sku_details.variant_combi as variant','vendor_sku_details.item_id as item_id')
				// 		->join('vendor_items','items.id','vendor_items.item_id')
				// 		->where(function($query) use($search_term){
				// 			$query->where('items.name','LIKE','%'.$search_term.'%')
				// 			->orWhere('vendor_sku_details.barcode', 'LIKE', '%'.$search_term.'%');
				// 		})
				// 		->where('vendor_items.v_id', $v_id)
				// //->where('items.short_description','LIKE','%'.$search_term.'%')
				// 		->leftJoin('vendor_sku_details','vendor_sku_details.item_id','items.id')
				// 		->leftJoin('stock_current_status','stock_current_status.item_id','vendor_sku_details.item_id')
				// 		->leftJoin('uom_conversions','uom_conversions.id','vendor_items.uom_conversion_id')
				// 		->leftJoin('uom','uom.id','uom_conversions.sell_uom_id')
				// 		->leftJoin('vendor_item_price_mapping',function($query) use($v_id){
				// 			$query->on('vendor_item_price_mapping.item_id','vendor_sku_flat_table.item_id');
				// 			$query->on('vendor_item_price_mapping.variant_combi','vendor_sku_flat_table.variant_combi');
				// 		})
				// 		->leftJoin('price_book',function($query) use($store_id){
				// 			$query->on('price_book.id','vendor_item_price_mapping.price_book_id');
				// 			$query->where('price_book.status','1');
				// 		})
				// 		->leftJoin('item_prices','item_prices.id','vendor_item_price_mapping.item_price_id')
				// 		->groupBy('vendor_sku_flat_table.name')
				// 		->paginate(10);
				// dd($value);
				// DB::enableQueryLog();
				$productList = VendorSku::leftJoin('store_items',function($query){
	                                  $query->on('store_items.v_id','vendor_sku_flat_table.v_id');
	                                  $query->on('store_items.variant_sku','vendor_sku_flat_table.sku');
	                                  $query->on('store_items.item_id','vendor_sku_flat_table.item_id');
	                                })
				->join('stock_point_summary', 'stock_point_summary.sku_code', 'vendor_sku_flat_table.sku_code')
				->select('vendor_sku_flat_table.name', 'stock_point_summary.barcode', 'vendor_sku_flat_table.selling_uom_type as weight', 'vendor_sku_flat_table.cat_name_1 as category', 'vendor_sku_flat_table.brand_name as brand', 'vendor_sku_flat_table.variant_combi as variant', 'vendor_sku_flat_table.item_id', 'vendor_sku_flat_table.variant_combi','vendor_sku_flat_table.selling_uom_type as weight_flag')
				->addSelect(DB::raw('vendor_sku_flat_table.has_batch as is_batch, vendor_sku_flat_table.has_serial as is_serial'))
				// ->where('store_items.v_id', $request->v_id)
				// ->where('store_items.store_id', $request->store_id)
				->where([ 'store_items.v_id' => $request->v_id, 'store_items.store_id' => $storeid, 'vendor_sku_flat_table.is_active' => '1', 'vendor_sku_flat_table.deleted_at' => null, 'stock_point_summary.stop_billing' => '0', 'stock_point_summary.store_id' => $storeid ])
				->groupBy('vendor_sku_flat_table.sku')
				->where(function($query) use($search_term) {
					$query->where('vendor_sku_flat_table.name','LIKE','%'.$search_term.'%')->orWhere('stock_point_summary.barcode', 'LIKE', '%'.$search_term.'%');
				})->paginate($paginateLimit);
		}
			
			if(count($productList) > 0) {
				foreach ($productList as $price) {
					$batches = $serials = [];
					$price->name   = utf8_decode($price->name);
					$priceArr  = ['v_id' => $v_id, 'store_id' => $store_id, 'item' => $price, 'unit_mrp' => ''];
			        $config = new CartconfigController;
			        $getPrice = $config->getprice($priceArr);
			        // dd($price);
					// $getprice  = DB::table('vendor_item_price_mapping')
					// ->join('price_book','price_book.id','vendor_item_price_mapping.price_book_id')
					// ->join('item_prices','item_prices.id','vendor_item_price_mapping.item_price_id')
					// ->where('vendor_item_price_mapping.v_id',$v_id)
					// ->where('vendor_item_price_mapping.store_id',$store_id)
					// ->where('vendor_item_price_mapping.item_id',$price->item_id)
					// ->where('price_book.status','1')
					// ->whereDate('effective_date','<=',$timestamp)
					// ->whereDate('valid_to','>=',$timestamp)
					// ->whereNull('price_book.deleted_at')
					// ->orderBy('price_book.effective_date','desc')->first();
					// dd($getprice);
					// if(!empty($getprice)){
					$price->mrp =  	$getPrice['mrp'];
					$mrp_arrs  =  $getPrice['mrp_arrs']; 
					$price->unsetRelation('vprice');
					// } 

					$price->weight_flag = $price->weight_flag == 'WEIGHT' ? true : false;

					// Check Batch List
					if($price->is_batch == 1) {
			          $grnData = GrnList::where([ 'barcode' => $price->barcode, 'v_id' => $v_id, 'store_id' => $store_id ])->orderBy('id','desc')->get();
			          foreach ($grnData as $gdata) {
			            foreach ($gdata->batches as $batch) {
			              if($batch->batch_no != '') {
			              	// dd($batch);
			                $validty = !empty($batch->valid_months) ? $batch->valid_months : 'N/A';
			                $batches[] = [ 'id' => $batch->id, 'code' => $batch->batch_no, 'mfg_date' => $batch->mfg_date, 'exp_date' => $batch->exp_date, 'validty' => $validty, 'type' => 'batch', 'mrp' => $batch->priceDetail->mrp ];
			              }
			            } 
			          }
			        }

			        // Check Serial List
			        if($price->is_serial == 1) {
				        $grnData = GrnList::where([ 'barcode' => $price->barcode, 'v_id' => $v_id, 'store_id' => $store_id ])->orderBy('id','desc')->get();
				        foreach ($grnData as $gdata) {
				          foreach ($gdata->serials as $serial) {
				            $serials[]  = [ 'id' => $serial->id, 'code' => $serial->serial_no, 'type' => 'serial', 'mrp' => $serial->priceDetail->mrp ];
				          } 
				        }
				    }

			        $price->batch = $batches;
			        $price->serial = $serials;
			        $price->multiple_price = $mrp_arrs;
				}
				$message = 'Get Product Search';
			}else{
				$message = 'No Data Found';
			}
		}
		// dd(DB::getQueryLog());

		return response()->json(['status' => 'get_product_search', 'message' => $message, 'data' => $productList, 'product_image_link' => product_image_link()], 200);
	} //End function



	public function product_details_by_cart($value)
	{
		$v_id = $value->v_id;
		$trans_from = $value->trans_from;
		$store_id = $value->store_id;
		$barcode = $value->barcode;
		$c_id = $value->c_id;
		$scanFlag = $value->scan;
		$qty = $value->qty;
		$product_data = array();

		$stores = DB::table('stores')->select('name', 'mapping_store_id')->where('store_id', $store_id)->first();
		$store_name = $stores->name;
		$item = DB::table('vmart.invitem')->where('ICODE', $barcode)->first();
		if (!$item) {
			return response()->json(['status' => 'product_not_found', 'message' => 'Product Not Found'], 404);
		}

		(array) $push_data = ['v_id' => $v_id, 'trans_from' => $trans_from, 'barcode' => $value->barcode, 'qty' => $qty, 'scode' => $stores->mapping_store_id];

		$promoC = new PromotionController;
		$offer_data = $promoC->final_check_promo_sitewise($push_data, 1);
		// $data = $offer_data;

		return $offer_data;
	}

	public function product_details_by_qty(Request $request)
	{
		// dd($value);
		$v_id = $request->v_id;
		$trans_from = $request->trans_from;
		$store_id = $request->store_id;
		$barcode = $request->barcode;
		$c_id = $request->c_id;
		$scanFlag = $request->scan;
		$qty = $request->qty;
		$product_data = array();

		$stores = DB::table('stores')->select('name', 'mapping_store_id')->where('store_id', $store_id)->first();
		$store_name = $stores->name;
		// $item = DB::table('vmart.invitem')->where('ICODE', $barcode)->first();
		// if (!$item) {
		// 	return response()->json(['status' => 'product_not_found', 'message' => 'Product Not Found'], 404);
		// }

		(array) $push_data = ['v_id' => $v_id, 'trans_from' => $trans_from, 'barcode' => $value->barcode, 'qty' => $qty, 'scode' => $stores->mapping_store_id];

		$promoC = new PromotionController;
		$offer_data = $promoC->final_check_promo_sitewise($push_data, 0);
		// $data = $offer_data;
		// dd($offer_data);
		return $offer_data;
	}


	public function addOrEditProductInfo($request)
	{
       //DB::beginTransaction();
		try {
			if (isset($request->category) && gettype($request->category) == 'string') {
				$request->category = json_decode($request->category, true);
			}
			if (isset($request->product_attributes) && gettype($request->product_attributes) == 'string') {
				$request->product_attributes = json_decode($request->product_attributes, true);
			}
			if (isset($request->variant) && gettype($request->variant) == 'string') {
				$request->variant = json_decode($request->variant, true);
			}
			if (isset($request->variant_products) && gettype($request->variant_products) == 'string') {
				$request->variant_products = json_decode($request->variant_products, true);
			}

            // Attribute Validation
			foreach ($request->product_attributes as $index => $product_attribute) {
				$product_attribute = json_decode(json_encode($product_attribute), true);
				if (
					!@$product_attribute['attributes'] &&
					!@$product_attribute['value']
				) {
					continue;
				} else if (!$product_attribute['attributes'] || !$product_attribute['value']) {
					return response()->json([
						'message' => "Missing product attribute or value",
						'status' => 'error',
						'errors' => [
							'product_attributes' => [
								$index => [
									'Attribute information is required'
								]
							]
						]
					], 422);
				}
			}

            // Variant Validation
            //        foreach ($request->variant as $index => $variant) {
            //            print(json_encode($variant)); die;
            //        }


			$id = null;
			if (isset($request->id)) {
				$id = $request->id;
			}

            // Product Department

			if (gettype($request->department) == 'string') {
				$department_id = $this->productconfig->getDepartmentId(json_decode($request->department)->name);
			} else {
				$department_id = $this->productconfig->getDepartmentId($request->department->name);
			}

			if ($id) {
				$product = Item::find($id);
			} else {
				$product = Item::where('name', $request->name)
				->where('department_id', $department_id)
				->whereHas('vendor', function ($query) {
					$query->where('v_id', Auth::user()->v_id);
				})
				->first();
				if (!$product) {
					$product = new Item;
				}
			}

			$product->name = $request->name;
			$product->short_description = $request->short_desc;
			$product->long_description = $request->description;
			if (empty($request->auto_sku) || !$request->auto_sku) {
                //            $product->sku   = $request->custom_sku;
				$request->auto_sku = false;
				$product->sku = 'custom';
			} else {
				$product->sku = 'auto';
			}

            //            print(isset($request->is_tax_inclusive)?'EMP': 'NOT'); die;
            //            if(!empty($request->is_tax_inclusive)) {
            //            }
			$tax_type = !empty($request->is_tax_exclusive)? (($request->is_tax_exclusive)? 'EXC': 'INC'): 'INC';
			$product->tax_type = $tax_type;

			$vendorItem = VendorItem::where('v_id',$request->v_id)->where('item_id', $product->id)->first();
			$vendorItem->tax_type = $tax_type;
			$vendorItem->save();

			$product->mrp = !empty($request->mrp) ? $request->mrp : null;
			$product->rsp = !empty($request->rsp) ? $request->rsp : null;
			$product->hsn_code = !empty($request->hsn_code) ? json_decode($request->hsn_code)->hsn_code : null;
			$product->tax_group_id = !empty($request->tax_group_id) ? json_decode($request->tax_group_id)->id : null;
			$product->special_price = !empty($request->special_price) ? $request->special_price : null;
			$product->has_batch = !empty($request->has_batch) ? ($request->has_batch ? 1 : 0) : 0;
			$product->has_serial = !empty($request->has_serial) ? ($request->has_serial ? 1 : 0) : 0;
			$product->department_id = $department_id;
			$product->deleted = 0;

			if (gettype($request->brand) == 'string') {
				$product->brand_id = $this->productconfig->getBrandId(json_decode($request->brand)->name);
			} else {
				$product->brand_id = $this->productconfig->getBrandId($request->brand->name);
			}

			if (isset($request->new_uom)) {
				$uom = (gettype($request->department) == 'string') ? json_decode($request->uom) : $request->uom;
				$uom_conversion = json_decode($this->productconfig->createUomConversion($uom->purchase, $uom->selling, $uom->factor));
				if ($uom_conversion->status == 'error') {
					return response()->json($uom_conversion, 422);
				}
                //            dd($uom_conversion);
				$product->uom_conversion_id = $uom_conversion->id;
			} else {
				if (!isset($request->uom_conversion_id)) {
					return response()->json([
						'message' => "Unit of measurement required",
						'status' => 'error',
						'errors' => [
							'uom_conversion_id' => [
								'Unit conversion required'
							]
						]
					], 422);
				}
				$conversion_id = json_decode($request->uom_conversion_id);
				if (isset($conversion_id->id) && UomConversions::find($conversion_id->id)) {
					$product->uom_conversion_id = $conversion_id->id;
				} else {
					return response()->json([
						'message' => "Invalid or missing Unit of measurement",
						'status' => 'error',
						'errors' => [
							'uom_conversion_id' => [
								'Invalid Unit conversion'
							]
						]
					], 422);
				}
			}

			$product->save();

           // echo $product;die;

			$this->productconfig->variantAttributes($request, $product->id);
			$this->productconfig->vendorItemMapping($request, $product, $this->vendor_id());
			$this->productconfig->vendorCatgory($request, $product->id);



			$variantCombinationResult = json_decode($this->productconfig->variantCombination($request, $product->id, $request->auto_sku));

             //print_r($variantCombinationResult);
           //die;
			if ($variantCombinationResult->status == 'error') {
				return response()->json($variantCombinationResult, 422);
			}

			$this->productconfig->addproductattribute($request, $product->id);
			$this->productconfig->addVariantAttributeValueMatrixMapping($this->cartesian($request->variant), $product->id);
        // DB::commit();
			return $product;
		} catch (\Exception $e) {

			print_r( $e->getMessage());
            //DB::rollback();
			exit;
		}
	}


	public function checkOverrideLimit(Request $request)
	{
		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$c_id = $request->c_id;
		$vu_id = $request->vu_id;
		$unit_mrp = $request->unit_mrp;
		$barcode = $request->barcode;
		$trans_from = $request->trans_from;
		$override_price = $request->override_price;
				$vSetting = new VendorSettingController;
				$role_id = VendorRoleUserMapping::select('role_id')->where('user_id',$vu_id)->first()->role_id;

				$settingsArray = ['v_id'=> $v_id,'store_id'=>$store_id,'user_id'=>$vu_id,'role_id'=>$role_id,'name'=>'cart' , 'trans_from' => $trans_from];
				$priceOverRideSetting = json_decode($vSetting->getSetting($settingsArray)->pluck('settings')->toArray()[0]);
		
		$bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode','item_id')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $barcode)->first();
		$item = null;
		if($bar){
			/*$item  =  VendorSkuDetails::select('vendor_sku_details.is_priceoverride_active','vendor_sku_details.price_overide_variance','vendor_sku_details.item_id')
			->where(['vendor_sku_details.v_id' => $v_id , 'vendor_sku_details.id' => $bar->vendor_sku_detail_id])
			->first();*/
			$item  =  VendorItem::select('vendor_items.allow_price_override','vendor_items.price_override_variance','vendor_items.price_override_override_by_store_policy','vendor_items.item_id')
			->where(['vendor_items.v_id' => $v_id , 'vendor_items.item_id' => $bar->item_id])
			->first();
		}
		//if($item->allow_price_override == "1"){
			if(isset($priceOverRideSetting->price_override->DEFAULT->status) == 1){
			    if(isset($priceOverRideSetting->price_override->DEFAULT->options[0]->varince_limit->value)){

			    	//Handling Price Override condition for Exchange
			    	$exchange = $vSetting->getItemExchangeFunction($settingsArray);
			    	$exchangePriceOverride = $vSetting->getPriceOverrideExchange($settingsArray);
			    	if(!isset($exchange->status) && $exchange->status ==1 && $exchangePriceOverride->status == 1){
			    		//Allow Price Override
			    	}else{
			    		//Do not allow Price Ovveride for Exchange neet to change qty condition
				    	$cart = Cart::where('v_id',$v_id)->where('user_id',$c_id)->where('barcode', $barcode)->where('qty', '<',0)->first();
				    	if($cart){
				    		return response()->json(['status' => 'fail', 'message' => 'Unable to override price for this items', 'dev_message' => 'No override for exchange'], 200);
				    	}

			    	}
			    	// dd($priceOverRideSetting->price_override->DEFAULT->options[0]);
	
			    	//Applying Item level variant
			    	if($item->price_override_override_by_store_policy == '1'){ 

			    		if($item->allow_price_override == '1'){
				    		$variance = $item->price_override_variance;
				    	}else{
				    		return response()->json(['status' => 'fail', 'message' => 'Unable to override price for this items (By Product Policy)'], 200);
				    	}
			    	}else{

						$variance = $policyLimit = $priceOverRideSetting->price_override->DEFAULT->options[0]->varince_limit->value;
				     	//$variance = min($item->price_override_variance,$policyLimit); // Pick the lowest value
			    	}
			    	// dd($variance);

			     	$mrp = format_number($unit_mrp - ($unit_mrp*$variance/100));
			     	$maxMrp = format_number($unit_mrp + ($unit_mrp*$variance/100));
			     	if($override_price < $mrp){
			     		return response()->json(['status' => 'fail', 'message' => 'Amount is less than limit'], 200);
			     	}else if($override_price > $maxMrp){
			     		return response()->json(['status' => 'fail', 'message' => 'Amount is greater than limit'], 200);
			     	}else{
			     		return response()->json(['status' => 'success', 'message' => 'price overridden','amount'=>$override_price], 200);
			     	}
				}
				
			}
		// }else{
		// 	return response()->json(['status' => 'fail', 'message' => 'Price override is not active for this item'], 200);
		// }
	}

	public function checkProductInventory(Request $request)
	{
		$batch_id = $request->batch_id;
		if(empty($request->batch_id)) {
			$batch_id = 0;
		}
		$serial_id = $request->serial_id;
		if(empty($request->serial_id)) {
			$serial_id = 0;
		}
		$getStockPointId = StockPointSummary::select('stock_point_id', 'qty')->where(['v_id' => $request->v_id, 'store_id' => $request->store_id, 'barcode' => $request->barcode, 'batch_id' => $batch_id, 'serial_id' => $serial_id, 'active_status' => '1'])->get();

		$filterData = $getStockPointId->filter(function($item) use ($request) {
			
			$stockPointName = StockPoints::select('name')->where(['v_id' => $request->v_id, 'store_id' => $request->store_id, 'id' => $item->stock_point_id])->first();
			
			$item['stock_point_name'] = $stockPointName->name;

			if($item->qty <= 0) {
				$item['qty_is'] = 'deficit';
			} else if($item->qty > 0) {
				$item['qty_is'] = 'surplus';
			}

			return $item;
		});
		
		return response()->json(['status' => 'success', 'data' => $filterData, 'barcode' => $request->barcode], 200);
	}

	
}
