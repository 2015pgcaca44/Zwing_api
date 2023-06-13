<?php

namespace App\Http\Controllers;
use App\Http\Controllers\CloudPos\CartconfigController;
use App\Http\Controllers\Client\ClientMappingController;
use Illuminate\Http\Request;
// use App\Model\Client\ClientVendorMapping;
use App\Model\InboundApi;
use Auth;
use Validator;
use Log;
use App\Organisation;
use App\OrganisationDetails;

use App\Model\Items\Item;
use App\Model\Items\VendorItem;
use App\Model\Items\ItemDepartment;
use App\Model\Items\ItemBrand;
use App\Model\Items\VendorItemDepartment;
use App\Model\Items\VendorItemBrand;

use App\Model\Items\ItemAttributes;
use App\Model\Items\ItemAttributesValues;
use App\Model\Items\VendorItems;
use App\Model\Items\VendorItemAttributes;
use App\Model\Items\VendorItemAttributeValueMapping;

use App\Model\Items\ItemCategory;
use App\Model\Items\VendorItemCategoryIds;
use App\Model\Items\VendorItemCategoryMapping;

use App\Items\ItemPrices;
use App\Items\VendorItemPriceMapping;

/*Variants*/
use App\Model\Items\ItemVariantAttributes;
use App\Model\Items\ItemVariantAttributeValues;
use App\Model\Items\VendorItemVariantAttributes;
use App\Model\Items\VendorItemVariantAttributeValueMapping;
use App\Model\Items\VendorItemVariantAttributeValueMatrixMapping;
use App\Model\Items\VendorSkuDetails;
use App\Model\Items\VendorSkuDetailBarcode;
use App\Model\Items\VendorSku;

use App\Model\Tax\HsnCode; 
use App\Model\Item\Uom;
use App\Model\Item\VendorUom;
use App\Model\Item\UomConversions;
use App\Model\Stock\Batch;
use App\Model\Oauth\OauthClient;
use App\Jobs\ItemCreate;
use App\Model\Store\StoreItems;
use App\LastInwardPrice;
use App\LastInwardPriceHistory;
use DB;
use App\Model\Item\ItemList;
use App\ThirdPartyVendorSetting;

use App\Jobs\FlatProduct;

class ItemController extends Controller
{

	// public function __construct(Request $request)
	// {
	//    if($request->has('trans_from')){
	    
	//    }else{
	    
	//    	return  response()->json(['status' => 'Fail' , 'message' => 'Not Autherige for this device'],200);
	   	  
	//    }
	// }

	public function getItem(Request $request){

		$v_id     = $request->v_id;
		$store_id = $request->store_id;
		$barcode  = $request->barcode;
		$trans_from = $request->trans_from;

		$bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $barcode)->first();
		$item_master = null;
		if($bar){
			$where    = array('v_id'=>$v_id,'id'=> $bar->vendor_sku_detail_id);
			$item_master = VendorSkuDetails::where($where)->first();
			$item_master->barcode = $bar->barcode;
		}
		$data  = array();
		if($item_master){
			// $priceList   =  $item_master->vprice->where('v_id',$v_id)->where('variant_combi',$item_master->variant_combi);  
			$priceArr  = array('v_id'=>$v_id,'store_id'=>$store_id,'item'=>$item_master,'unit_mrp'=>'');
			$config     = new CartconfigController;
			$price   	= $config->getprice($priceArr);
			$mrp        = $price['unit_mrp'];

			$item_name  = $item_master->Item->name.' ('.$item_master->variant_combi.')';
			$data 		= array( 'barcode'   => $item_master->barcode,
										'item_name' => $item_name,
								      'sku'			=> $item_master->sku,
								      'has_serial'  => $item_master->vendorItem->has_serial,
								      'has_batch'   =>  $item_master->vendorItem->has_batch,
								      'unit_mrp'  => $mrp
								   	);
			 return response()->json(['status' => 'success' , 'item_detail' => $data],200);
		}else{
			return response()->json(['status' => 'fail' , 'message' => 'No Item Found'],200);
		}


	}

	public function create(Request $request){
        //dd($request->all());

		if ($request->isJson()) {
			try {

				$data = $request->json()->all();
		        //$data = collect($data);

		        /** @var \Illuminate\Contracts\Validation\Validator $validation */
		        $validator = Validator::make($data,[
		        		'organisation_code' => 'required',
		        		'item_data' => 'required|array'
		            ]
		        );

		        if($validator->fails()){
		        	$error_list = [];
		        	foreach($validator->messages()->get('*') as $key => $err){
		        		$error_list[] = [ 'error_for' => $key , 'messages' => $err ];  
		        	}

		        	return response()->json([ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' => $error_list ] , 422);
		        }

		        $client = oauthUser($request);
		        $client_id = $client->client_id;

		        //This code is added when we are using client
		        //$clientMapping = new ClientMappingController;
		        $vendor = Organisation::select('id','vendor_code')->where('ref_vendor_code', $data['organisation_code'])->first();
		       
		        if(!$vendor){

		        	$error_list =  [ 
		        		[ 'error_for' =>  'organisation_code' ,  'messages' => ['Unable to find This Organisation'] ] 
		        	]; 
		        	return response()->json([ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' =>  $error_list] , 422);
		        }

		        $v_id = $vendor->id;

		      
		        $vendorS = new VendorSettingController;
		        $sParams = ['v_id' => $v_id , 'trans_from' => 'DEFAULT' , 'store_id' => null , 'user_id' => null , 'role_id' => null  ];
		        $productSettings = $vendorS->getProductSetting($sParams);
		        $maxVariantAttribute = null;

		        // if(isset($productSettings->max_item_variant_attribute)) {
		        //     $maxVariantAttribute = $productSettings->max_item_variant_attribute;
		        // } else {
		        // 	$error_list =  [
		        // 		[
		        // 		'error_for' =>  'max_attribute' ,  
		        // 		'messages' => ['maximum item variant attribute value is not set in policy'] 
		        // 		] 
		        // 	]; 

		        //     return response()->json([
		        //         'message' => "Validation Fail",
		        //         'status' => 'fail',
		        //         'errors' => $error_list
		        //     ], 422);
		        // }


		        $ack_id = null;
		        $currrentDateInString = current_date_in_string();
		        $ack_id = $vendor->vendor_code.$client->client_code.$currrentDateInString;
		        $asyn = InboundApi::where('v_id', $v_id)->where('client_id', $client_id)->where('api_type','ASYNC' )->orderBy('_id','desc')->first();
		        $inc_id = '0001';
		    
		        if($asyn){
			        $last_ack_id = $asyn->ack_id;
			        $exists_date = substr($last_ack_id, 6, 3);
					if ($exists_date == $currrentDateInString) {
				        $inc_id = substr($last_ack_id, 9, 4);
			        	// dd($inc_id);
				        $inc_id++;
				        $inc_id = sprintf('%04d', $inc_id++);
				    }
		        }

		        $ack_id = $ack_id.$inc_id;

		        $asyn = new InboundApi;
		        $asyn->client_id = $client_id;
		        $asyn->v_id = $v_id;
		        $asyn->request = json_encode($data);
		        $asyn->job_class = 'ItemCreate';
		        $asyn->api_name = 'client/item/create';
		        $asyn->api_type = 'ASYNC';
		        $asyn->ack_id = $ack_id;
		        $asyn->status = 'PENDING'; // PENDING|FAIL|SUCCESS
		        $asyn->save();

		        //dd($data);
		        
		        // if(isset($data['testing'])){
		        // 	$this->processItemMasterCreationJob(['v_id' => $v_id, 'client_id' => $client_id, 'ack_id' => $ack_id ] );
		        // }else{

			        //Item Creation Job will handle the Item Create Job
			        dispatch(new ItemCreate(['v_id' => $v_id , 'client_id' => $client_id, 'ack_id' => $ack_id ]));
			        return response()->json([ 'status' => 'success' , 'message' => 'Request has been received'  , 'ack_id' => $ack_id  ] , 200);
			    // }
		      

			}catch( \Exception $e ) {
				Log::error($e);

		    	return response()->json([ 'status' => 'fail' , 'message' => 'Server Error'   ] , 500);
		    }

		}

	}

	public function checkItemCreationStatus(Request $request){

		if ($request->isJson()) {
			
			try {
				$data = $request->json()->all();
			        //$data = collect($data);

		        /** @var \Illuminate\Contracts\Validation\Validator $validation */
		        $validator = Validator::make($data,[
		        		'organisation_code' => 'required',
		        		'ack_id' => 'required'
		            ]
		        );

		        if($validator->fails()){
		        	$error_list = [];
		        	foreach($validator->messages()->get('*') as $key => $err){
		        		$error_list[] = [ 'error_for' => $key , 'messages' => $err ];  
		        	}

		        	return response()->json([ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' => $error_list ] , 422);
		        }

		        $client = oauthUser($request);
		        $client_id = $client->client_id;

		        //This code is added when we are using client
		        //$clientMapping = new ClientMappingController;
		        $vendor = Organisation::select('id','vendor_code')->where('ref_vendor_code', $data['organisation_code'])->first();
		        if(!$vendor){
		        	$error_list =  [ 
		        		[ 'error_for' =>  'organisation_code' ,  'messages' => ['Unable to find This Organisation'] ] 
		        	]; 
		        	return response()->json([ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' =>  $error_list] , 422);
		        }

		        $v_id = $vendor->id;
				$ack_id = $data['ack_id'];

				$asyn_status = null;
				$response = [];
				$response_status_code = null;

				$asyn = InboundApi::where('v_id', $v_id)->where('client_id', $client_id)->where('ack_id', $ack_id)->first();

				if($asyn){

					if($asyn->status == 'FAILED'){
						$res = json_decode($asyn->response, true);
						return response()->json( [ 
							'status' => 'success', 
							'message' => 'Api Processed Status', 
							'api_status' => $asyn->status, 
							'total_record' => $res['total_record'],
							'total_success' => $res['total_success'],
							'total_fail' => $res['total_fail'],
							'errors' => $res['errors'] 
						]);

					}else{

					}
					return response()->json( [ 
						'status' => 'success', 
						'message' => 'Api Processed Status', 
						'api_status' => $asyn->status,
						'total_record' => null,
						'total_success' => null,
						'total_fail' => null,
						'errors' => null 
						//, 'api_response' => $asyn->response 
					]);
					
				}else{

					$error_list =  [ 
		        		[ 'error_for' =>  'organisation_code' ,  'messages' => ['Unable to find The Api Request against this Ack id'] ] 
		        	]; 

					return response()->json([ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' =>  $error_list] , 422);
				}


			}catch( \Exception $e ) {
			 Log::error($e);

		    	return response()->json([ 'status' => 'fail' , 'message' => 'Server Error'   ] , 500);
		    }

		}
	}

	
		
	public function processItemMasterCreationJob(Request $request){

		//dd($params);

  //      if(array_key_exists("type",$params) && $params['type']=='excel'){
  //      	$v_id = $params['v_id'];
		// $client_id = 'VT2345RTZW87';
		// $ack_id = '';

  //      }else{
		// $v_id = $params['v_id'];
		// $client_id = $params['client_id'];
		// $ack_id = $params['ack_id'];

	 //   }
        //   $organisation = Organisation::find($request->v_id);
        //   //dd($organisation->db_type);
        // if($organisation->db_type == 'MULTITON'){

        //     $db_name = $organisation->db_name;
        //     if($db_name){
                
        //         if(config('database.default') == 'mysql') {
        //             $organisation = Organisation::find($request->v_id);
        //             //$vUser = DB::table($organisation->db_name.'.vender_users_auth')->where('vu_id', $request->vu_id)->first();
        //             //dynamicConnection($organisation->db_name);
        //         $connPrm    =array('host'=> $organisation->connection->host,'port'=>$organisation->connection->port,'username'=>$organisation->connection->username,'password' =>  $organisation->connection->password,'db_name'=>$organisation->db_name);
        //         dynamicConnectionNew($connPrm);
        //         }
        //     }
        // }

        $v_id = $request->v_id;
        $client_id= $request->client_id;
        $ack_id   =$request->ack_id;
        $type  = $request->type;
        JobdynamicConnection($v_id);  
		$asyn_status = null;
		$response = [];
		$response_status_code = null;
		$total_record = 0;
		$total_success = 0;
		$total_fail = 0;
		$product_ids = [];

		$error_for = 'v_id: '.$v_id.', client_id: '.$client_id.' ack_id: '.$ack_id;

		$client = OauthClient::where('client_id' , $client_id)->first();
        
		$asyn = InboundApi::where('v_id', $v_id)->where('client_id', $client_id)->where('ack_id', $ack_id)->first();

		$error_list = [];
		$error = [];
        
		$country_code=null;
		$orgAddress = OrganisationDetails::where('v_id',$v_id)->where('active',1)->first();
		if($orgAddress){
			$country_code = $orgAddress->countryDetail->sortname;
		}

		if($client_id==1){
			        $store_id = 0;
                    $currecy=getStoreAndClientCurrency($v_id,$store_id);
			        if($currecy['status']=='error'){
			            $error_msg = $extrarate['message'];
			            $outBound->error_before_call = $error_msg;
			            $outBound->save();
			            Log::error($error_msg);

			            return [ 'error' => true , 'message' => $error_msg ];

			        }else{

			         $source_currency = $currecy['client_currency'];
			         $target_currency = $currecy['store_currency']; 


			        }

			        $extrarate=getExchangeRate($v_id,$source_currency,$target_currency,1);
			        if($extrarate['status']=='error'){
			            $error_msg = $extrarate['message'];
			            $outBound->error_before_call = $error_msg;
			            $outBound->save();
			            Log::error($error_msg);

			            return [ 'error' => true , 'message' => $error_msg ];
			            exit;
			        }
		}

		try {
            
            if($type=='excel'){
            	$data = json_decode($request->item_data, true);
            }else{
				$data = json_decode($asyn->request, true);
            }
            //dd($data);
			$total_record = count($data['item_data']);
			$total_success = 0;
			$total_fail = 0;

			$asyn_status = 'SUCCESS';
	        $response = [ 'status' => 'success' , 'message' => 'Item Created Successfully'];
    		$response_status_code = 200;
    		$error_list = [];
			 //dd($data);

    		//Temporray Condition need to remove added for janavi
    		if($client->id == 2){
				$validator = Validator::make($data,[
						// 'organisation_code' => 'required',
						// 'item_data' => 'required|array',
						'item_data.*.item_code' => 'required',
						'item_data.*.name' => 'required',
						'item_data.*.selling_uom_type' => 'required|in:WEIGHT,PIECE',
						'item_data.*.purchase_uom_type' => 'required|in:WEIGHT,PIECE',
						'item_data.*.selling_uom_code' => 'required',
						'item_data.*.purchase_uom_code' => 'required',
						'item_data.*.sku_generation' => 'required|in:auto,custom,Auto,Custom',
						'item_data.*.uom_factor' => 'required',
						// 'item_data.*.hsn_code' => 'required',
						'item_data.*.tax_applicable_type' => 'in:INCLUSIVE,EXCLUSIVE',
						//'item_data.*.variants' => 'required|array',
						//'item_data.*.variants.*.barcode' => 'required|min:3',
						'item_data.*.variants.*.prices' => 'required|array',
						'item_data.*.variants.*.prices.*.unit_mrp' => 'required',
						'item_data.*.variants.*.variant_attributes.*.attribute_name' => 'required'
						// 'item_data.*.variants.*.variant_attributes.*.attribute_value' => 'required'
					]
				);
			}else{
	    		/** @var \Illuminate\Contracts\Validation\Validator $validation */
		        $validator = Validator::make($data,[
		        		// 'organisation_code' => 'required',
		        		// 'item_data' => 'required|array',
		        		'item_data.*.item_code' => 'required',
		        		'item_data.*.name' => 'required',
		        		'item_data.*.selling_uom_type' => 'required|in:WEIGHT,PIECE',
		        		'item_data.*.purchase_uom_type' => 'required|in:WEIGHT,PIECE',
		        		'item_data.*.selling_uom_code' => 'required',
		        		'item_data.*.purchase_uom_code' => 'required',
		        		'item_data.*.sku_generation' => 'required|in:auto,custom,Auto,Custom',
		        		'item_data.*.uom_factor' => 'required',
		        		// 'item_data.*.hsn_code' => 'required',
		        		'item_data.*.tax_applicable_type' => 'in:INCLUSIVE,EXCLUSIVE',
		        		'item_data.*.variants' => 'required|array',
		        		'item_data.*.variants.*.barcode' => 'required|min:3',
		        		'item_data.*.variants.*.prices' => 'required|array',
		        		'item_data.*.variants.*.prices.*.unit_mrp' => 'required',
		        		'item_data.*.variants.*.variant_attributes.*.attribute_name' => 'required'
		        		// 'item_data.*.variants.*.variant_attributes.*.attribute_value' => 'required'
		            ]
	        	);
	        }
            //dd($error_list);
	        if($validator->fails()){
	        	$total_fail = $total_record;
	        	foreach($validator->messages()->get('*') as $key => $err){
	   
	        		$error_list[] = $error =[ 'error_for' => $key , 'messages' => $err ];  
	        	}
	        	 //dd($error_list);
                //dd($error_list);
                //exit();
	        	// return response()->json([ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' => $error_list ] , 422);
	        }else{
               //dd($data);

               //exit();
				foreach($data['item_data'] as $itemIndex => $item){
					$error = [];
					#### Need to Map Item Code of Ginesyy to zwing ####
					$vendorItem = VendorItem::where('v_id' , $v_id)->where('ref_item_code', $item['item_code'])->first();
					// dd($vendorItem);
					if($vendorItem){
						$product = $vendorItem->item;
						$product->name = $item['name'];
						$product->short_description = (isset($item['short_desc']) && $item['short_desc']!='')?$item['short_desc']:$item['name'];
						$product->long_description = (isset($item['long_desc']) && $item['long_desc']!='' )?$item['long_desc']:$item['name'];
					}else{

						//This is Temporary Code for update item_code for janavi need to remove
						if($client->id == 2){

							if(!isset($item['variants']) ){
								$error = [ 'error_for' =>  'item_'.$itemIndex,  'id' => $item['item_code'], 'messages' => ['variants is required'] ] ; 
								$error_list[] = $error;
								continue;
							}
							if(count($item['variants']) <=0 ){
								$error = [ 'error_for' =>  'item_'.$itemIndex,  'id' => $item['item_code'], 'messages' => ['variants is Empty'] ] ; 
								$error_list[] = $error;
								continue;
							}
							/*
							$allBarcode = collect($item['variants'])->pluck('barcode')->all();
							$vendorDetails = VendorSkuDetails::select('item_id')->where('v_id',$v_id)->whereIn('barcode', $allBarcode)->get();
							//dd($vendorDetails);
							$itemIds = array_unique($vendorDetails->pluck('item_id')->all() );
							
							$itemCount = count( $itemIds);
							if($itemCount <=1 ){
								if($itemCount == 1){
									$vendorItem = VendorItem::where('v_id' , $v_id)->where('item_id', $itemIds[0])->first();
									//dd($vendorItem);
									$vendorItem->ref_item_code = $item['item_code'];
									$vendorItem->save();
								}
							
								
								
							}else{
								$error = [ 'error_for' =>  'item_'.$itemIndex,  'id' => $item['item_code'], 'messages' => ['Some barcode is map to differend item_id'] ] ; 
								$error_list[] = $error;
								continue;
							}*/
						}

						if(!$vendorItem){
							$vendorItem = new VendorItem;
							$vendorItem->ref_item_code = $item['item_code'];
							//$vendorItem->ref_item_code = $item['item_code'];
						}
						
						$product = Item::select('items.*')->join('vendor_items','vendor_items.item_id','items.id')->where('vendor_items.ref_item_code',$item['item_code'])->where('vendor_items.v_id',$v_id)->first();
						if(!$product){
							$product = new Item;
						}

						$product->name = $item['name'];
	           			$product->short_description = (isset($item['short_desc']) && $item['short_desc']!='')?$item['short_desc']:$item['name'];
	            		$product->long_description = (isset($item['long_desc']) && $item['long_desc']!='' )?$item['long_desc']:$item['name'];
					}

					$vendorItem->v_id = $v_id;		            
		            $vendorItem->sku = 'auto';
		            if (isset($item['sku_generation']) && ( $item['sku_generation']=='custom' || $item['sku_generation']=='Custom') ) {
		                //            $product->sku   = $request->custom_sku;
		                //$request->auto_sku = false;
		                $vendorItem->sku = 'custom';
		                
		            }

		            #### How to ginesys will tell us to calcluate tax INC EXclu ####
		            $vendorItem->tax_type = 'INC';
		            $product->tax_type = 'INC';
		            if($country_code == 'BD'){
		            	$product->tax_type = 'EXC';
		            	$vendorItem->tax_type = 'EXC';
		            }
		            	
		            if(isset($item['tax_applicable_type'])){
		            	if($item['tax_applicable_type'] == 'EXCLUSIVE'){
		            		$product->tax_type = 'EXC';
		            		$vendorItem->tax_type = 'EXC';
		            	}
		            }
	            
		            // $product->mrp = isset($item['unit_mrp']) ? $item['unit_mrp'] : null;
		            // $product->rsp = isset($item['unit_rsp']) ? $item['unit_rsp'] : null;
		            
		            if(isset($item['tax_code'])){
		            	$product->hsn_code = isset($item['tax_code']) ? $item['tax_code'] : null;
		            }else{
		            	$product->hsn_code = isset($item['hsn_code']) ? $item['hsn_code'] : null;
		            }
		            $product->tax_group_id =  null;
		            #### Need to Store Third price i.e special price ####
		            // $product->special_price = isset($item['unit_special_price']) ? $item['unit_special_price'] : null;
		            
		           	//$vendorItem->negative_inventory_override_by_store_policy = (isset($item['negative_inventory_override_by_store_policy']) && $item['negative_inventory_override_by_store_policy']=='YES' )?'1':'0';
		           	
		           	// $vendorItem->track_inventory = (isset($item['track_inventory']) && $item['track_inventory']=='YES' )?'1':null;
		           	$vendorItem->track_inventory_by = (isset($item['track_inventory_by'])  )?$item['track_inventory_by']:null;
		           	$vendorItem->negative_inventory = (isset($item['negative_inventory']) && $item['negative_inventory']=='YES' )?'1':null;
		            
		            $has_batch = 0;
		            $has_serial = 0;
		            $track_inventory_by = null;
		            if(isset($item['batch_tracking'])){
		            	if($item['batch_tracking'] == 'YES'){
		            		$has_batch = 1;
		            		$track_inventory_by = 'BATCH';
		            	}
		            }

		            if(isset($item['serial_tracking'])){
		            	if($item['serial_tracking'] == 'YES'){
		            		$has_serial = 1;
		            		$track_inventory_by = 'SERIAL';
		            	}
		            }

		            if($has_batch == 1 && $has_serial ==1){
		            	$error_list[] = [ 'error_for' =>  'item_'.$itemIndex,  'id' => $item['item_code'], 'messages' => ['Bothe Batch And Serial Tracking Cannot be yes'] ] ; 	
		            }


		            $vendorItem->track_inventory = ($has_batch || $has_serial)? '1': '0';
		            $vendorItem->track_inventory_by = $track_inventory_by;
		            $vendorItem->has_batch = $has_batch;
		            $vendorItem->has_serial = $has_serial;

		            if(isset($item['track_inventory'])){
		            	$vendorItem->track_inventory = ($item['track_inventory']=='YES' )?'1':'0';
		            }else{
		            	if($client_id == 1){
		            		$vendorItem->track_inventory = '1';
		            	}
		            }

		            $product->deleted = 0;

		            $vendorItem->allow_price_override = (isset($item['allow_price_override']) && $item['allow_price_override']=='YES' )?'1':null;
		            $vendorItem->price_override_variance = (isset($item['price_override_variance'])  )?$item['price_override_variance']:0;

		            $vendorItem->allow_manual_discount = (isset($item['allow_manual_discount']) && $item['allow_manual_discount']=='YES' )?'1':null;
		            $vendorItem->manual_discount_percent = (isset($item['manual_discount_percent'])  )?$item['manual_discount_percent']:null;
		            //$vendorItem->manual_discount_override_by_store_policy = (isset($item['manual_discount_override_by_store_policy']) && $item['manual_discount_override_by_store_policy']=='YES' )?'1':'0';
		            
		            //$product->department_id = null;
		            
		            #################################################
		            #### Creating and updating Department Start #####
		            
		            ## Need to remove this line ##

		            $vendorItem->department_id = 1;

		            if(isset($item['department'])){
		            	if(isset($item['department']['department_code']) ){
		            		$department = VendorItemDepartment::where('ref_department_code', $item['department']['department_code'])->where('v_id' , $v_id)->first();
		            		if($department){
		            			$vendorItem->department_id = $department->department->id;
		            		}else{

		            			$item_department_id = ItemDepartment::firstOrCreate([
		            				'name' => $item['department']['department_name']
		            			])->id;

		            			## custom department code generation pending
		            			$vendorItem->department_id  = $item_department_id;
		            			VendorItemDepartment::create(['v_id' => $v_id , 'department_id' => $item_department_id , 'department_code' => $item_department_id , 'ref_department_code' => $item['department']['department_code'] ]);
		            		}
		            	}
		            }

                    

		            #### Creating and updating Department End #####
		            ###############################################
		            

		            ############################################
		            #### Creating and updating Brand Start #####
		            
		            ## Need to remove this line ##
		            $vendorItem->brand_id = 2;

		            if(isset($item['brand'])){
		            	if(isset($item['brand']['brand_code']) ){
		            		$brand = VendorItemBrand::where('ref_brand_code', $item['brand']['brand_code'])->where('v_id' , $v_id)->first();
		            		if($brand){
		            			$vendorItem->brand_id = $brand->brand->id;
		            		}else{
		            			$item_brand_id = ItemBrand::firstOrCreate([
		            				'name' => $item['brand']['brand_name']
		            			])->id;

		            			## custom brand code generation pending ##
		            			$vendorItem->brand_id  = $item_brand_id;
		            			VendorItemBrand::create(['v_id' => $v_id , 'brand_id' => $item_brand_id , 'brand_code' => $item_brand_id , 'ref_brand_code' => $item['brand']['brand_code'] ]);
		            		}
		            	}
		            }
		            #### Creating and updating Brand End #####
		            ##########################################
		            
		            
		            
		            ############################################
		            #### Creating and updating Uom Start #####

		            ## Changing this based on the Erp ##
                   
                    ## new Barnd and  Department for ginesys only 

                    if($client->id == 1){
                    	
                        $cat  	         = $item['category']; 
                       	if(isset($cat['2'])){

                       		$department_name  = $cat['2']['category_name'];
							if(isset($department_name)){
							   $department = ItemDepartment::where('name',$department_name)->first();

							   if(!$department){
							     $department      = new ItemDepartment;
							     $department->name = $department_name;
							     $department->save(); 
							   }
							   
							   $vendorItem->department_id = $department->id;
							   
							}
                       	}else{
	                       	$error = [ 'error_for' =>  'item_'.$itemIndex.'_category',  'id' => $item['item_code'], 'messages' => ['Second level category is not exists, Required for Ginesys Integration'] ] ; 
				        	$error_list[] = $error;

                       	}
                      # for brand
                       //dd('hee');

                     $thirdPartyVendorSetting =ThirdPartyVendorSetting::where('name','Product')
                                                                 ->where('setting_for','Brand')
                                                                 ->where('v_id',$v_id)
                                                                 ->orderBy('id','DESC')
                                                                 ->first();
                        //dd($thirdPartyVendorSetting);                                         
                        if($thirdPartyVendorSetting){
                          $default_barnd_field   = $thirdPartyVendorSetting->setting_value;
                        }else{
                          $default_barnd_field   = 'CNAME1';
                      
                        } 
                        //dd($default_barnd_field);                                        
                    	# department
                       
                       $attributes       = $item['variants'][0]['variant_attributes'];
                       $brandName        = null;
                       foreach ($attributes as $key =>$attribute) {
                       	 if($attribute['attribute_name'] == $default_barnd_field){
                           $brandName = $attribute['attribute_value'];
                       	 }

                       }
                       
                       if($brandName!=null){
                               
                         $brand =ItemBrand::where('name',$brandName)->first();
                         if(!$brand){
                          
                          $brand       = new ItemBrand;
                          $brand->name = $brandName;
                          $brand->save();
                         }
                         $vendorItem->brand_id = $brand->id;
                       }

                    } 
                       

		            $selling_uom_code = $item['selling_uom_code'];
		            $purchase_uom_code = $item['purchase_uom_code'];
		            $selling_uom = null;
		            $purchase_uom = null;

		            if($client->id == 1){



			            $selling_uom_name =$item['selling_uom_code'];
			            $purchase_uom_name = $item['purchase_uom_code'];

			            // $sellingUom = VendorUom::where('ref_uom_code' , $selling_uom_code)->first();
			            // if($sellingUom){

			            // }
			            $selling_uom = Uom::where('code', $selling_uom_name)->first();
			            if(!$selling_uom){
			            	$selling_uom = Uom::Create(['name' => $selling_uom_name , 'code' => $selling_uom_code , 'type' => $item['selling_uom_type'] ]);
			            }

			            $purchase_uom = Uom::where('code', $purchase_uom_name)->first();
			            if(!$purchase_uom){
			            	$purchase_uom = Uom::Create(['name' => $purchase_uom_name , 'code' => $purchase_uom_code , 'type' => $item['purchase_uom_type'] ]);
			            }
			            
			            //dd($selling_uom);

			            

			        }else{

			        	$selling_uom = Uom::where('code', $selling_uom_code )->first();
			        	$purchase_uom = Uom::where('code', $purchase_uom_code )->first();
			        	//dd($selling_uom);
			        }


			        if(!$selling_uom){
		        		$error = [ 'error_for' =>  'item_'.$itemIndex.'_selling_uom_code',  'id' => $item['item_code'], 'messages' => ['Selling Uom not exists'] ] ; 
			        	$error_list[] = $error;
		        	}

		        	if(!$purchase_uom){
		        		$error = [ 'error_for' =>  'item_'.$itemIndex.'_purchase_uom_code',  'id' => $item['item_code'], 'messages' => ['Purchase Uom not exists'] ] ; 
			        	$error_list[] = $error;
		        	}

		        	if($selling_uom && $purchase_uom){
		        		$uomConversion = UomConversions::updateOrCreate(
		        			['v_id' => $v_id, 'purchase_uom_id' => $purchase_uom->id, 'sell_uom_id' =>  $selling_uom->id  ] ,
		        			['factor' => $item['uom_factor'] ]
		        		);
		        		$vendorItem->uom_conversion_id = $uomConversion->id;
		        	}

		            #### Creating and updating Uom End #####
		            ##########################################



		            if(count($error) >=1){
		            	// dd($error_list);
		            	$asyn_status = 'FAILED';
		            	$total_fail++;
		            	continue;
		            } 



		            $product->save();
		            $vendorItem->item_id = $product->id;
		            ### Item unique item code need to be generate based on vendor id
		            $vendorItem->item_code = (int)$v_id.''.$product->id;
		            $vendorItem->save();


		            ## Need to Remove this Code Start here ##
		            $product->brand_id = $vendorItem->brand_id;
		            $product->department_id = $vendorItem->department_id;
		            $product->uom_conversion_id = $vendorItem->uom_conversion_id;
		            $product->sku = $vendorItem->sku;
		            $product->has_serial = $vendorItem->has_serial;
		            $product->has_batch = $vendorItem->has_batch;
		            $product->save();
		            ## Need to Remove this Code End here ##
		            
		            
		            //For Running Flat Table id;
		            $product_ids[] = $vendorItem->item_id;

		            ########################################################
		            #### Creating and updating Item Attribute Start #####
		            // $this->productconfig->variantAttributes($request, $product->id);
		            if($client->id == 1){
                         //dd($item['variants']['variant_attributes'][0]);
		            	$attributes = $item['variants'][0]['variant_attributes'];
		            	//dd($attributes);
		            	$attributesColl = collect($attributes);

		            	// dd($attributes);
		            	foreach ($attributes as $key => $attribute) {
		            		$attribute_name = null;
		            		$attribute_value = null;
		            		$attribute_code = null;
		            		
		            		//This condition to capture all  CNAME1 to CNAME6
		            		if(strpos($attribute['attribute_name'], 'CNAME') !== false ){
							   if(isset($attribute['attribute_value']) && $attribute['attribute_value']!='' && $attribute['attribute_value']!= null){

							   	$number = str_replace('CNAME','', $attribute['attribute_name']);
							   	
								   	if($number > 0){
								   		//$value = 'CNAME'.$number;
								   		//$ColumnName = $attributesColl->where('attribute_name', $value)->first();

				            			//$attribute_value = $ColumnName['attribute_value'];
									   	$attribute_name = $attribute['attribute_name'];
				            			$attribute_value = $attribute['attribute_value'];
				            			if($attribute['attribute_name_code']!= null){
											$attribute_code =  $attribute['attribute_name_code'];
										}

								   	}

							   }
							}

							//This condition to capture all  DESC1 to DESC6
							if(strpos($attribute['attribute_name'], 'DESC') !== false){
							   
							    if(isset($attribute['attribute_value']) && $attribute['attribute_value']!='' && $attribute['attribute_value']!= null){

							   	$attribute_name = $attribute['attribute_name'];
		            			$attribute_value = $attribute['attribute_value'];
		            			if($attribute['attribute_name_code']!= null){
									$attribute_code =  $attribute['attribute_name_code'];
								}
							   	
							   }
							
							}


							//This condition to capture all  UDFSTRING1 to UDFSTRING6
							if(strpos($attribute['attribute_name'], 'UDFSTRING') !== false){
							   
							    if(isset($attribute['attribute_value']) && $attribute['attribute_value']!='' && $attribute['attribute_value']!= null){

							   	$attribute_name = $attribute['attribute_name'];
		            			$attribute_value = $attribute['attribute_value'];
		            			if($attribute['attribute_name_code']!= null){
									$attribute_code =  $attribute['attribute_name_code'];
								}
							   	
							   }
							
							}

							//This is common Code make it unique
							if($attribute_name && $attribute_value){
								$itemAttri = ItemAttributes::firstOrCreate(['name' => $attribute_name]);
								$itemAttrVal = ItemAttributesValues::firstOrCreate(['value' => $attribute_value , 'type' => 'text']);

								$vendorItemAttri = VendorItemAttributes::updateOrCreate(['v_id' => $v_id , 'item_attribute_id' => $itemAttri->id],
									['ref_attribute_code' => $attribute_code ]
								);

								//Creating Code
								if($vendorItemAttri->code =='' || $vendorItemAttri->code == null){
									$attribute_name =  'a_'.str_replace('/' ,'', str_replace(' ','_',str_replace('&','a', strtolower(trim($attribute_name)) )) );
									$w = true;
									$post_fix='';
									while($w){
										$attribute_name = $attribute_name.$post_fix;

										$code = VendorItemAttributes::where('v_id',$v_id)->where('code',$attribute_name)->first();
										
										if($code){
											$post_fix = '_'.rand(10, 99);
											$w=true;
										}else{
											$w=false;
										}
									}

									$vendorItemAttri->code = $attribute_name;
									$vendorItemAttri->save();
								}

								VendorItemAttributeValueMapping::updateOrCreate(
									['v_id' => $v_id , 'item_id' => $product->id , 'item_attribute_id' => $itemAttri->id ],
									['item_attribute_value_id' => $itemAttrVal->id ]
								);
							}


		            		
		            	}


		            }else{
		            	$item_attributes = isset($item['item_attributes'])?$item['item_attributes']:[];
		            	foreach ($item_attributes as $key => $attribute) {
		            		$attribute_name = null;
		            		$attribute_value = null;
		            		$attribute_code = null;

		            		if(isset($attribute['attribute_value']) && $attribute['attribute_value']!='' && $attribute['attribute_value']!= null){
							   	$attribute_name = $attribute['attribute_name'];
		            			$attribute_value = $attribute['attribute_value'];
		            			$attribute_code =  $attribute['attribute_name_code'];
							}

							//This is common Code make it unique
		            		if($attribute_name && $attribute_value){
								$itemAttri = ItemAttributes::firstOrCreate(['name' => $attribute_name]);
								$itemAttrVal = ItemAttributesValues::firstOrCreate(['value' => $attribute_name , 'type' => 'text']);

								// $vendorItemAttri = VendorItemAttributes::firstOrCreate(['v_id' => $v_id , 'item_attribute_id' => $itemAttri->id]);
								// VendorItemAttributeValueMapping::updateOrCreate(
								// 	['v_id' => $v_id , 'item_id' => $product->id , 'item_attribute_id' => $itemAttri->id ],
								// 	['item_attribute_value_id' => $itemAttrVal->id]
								// );

								$vendorItemAttri = VendorItemAttributes::updateOrCreate(['v_id' => $v_id , 'item_attribute_id' => $itemAttri->id],
									['ref_attribute_code' => $attribute_code ]
								);
								
								//Creating Code
								if($vendorItemAttri->code =='' || $vendorItemAttri->code == null){
									$attribute_name =  'a_'.str_replace('/' ,'', str_replace(' ','_',str_replace('&','a', strtolower(trim($attribute_name)) )) );
									$w = true;
									$post_fix='';
									while($w){
										$attribute_name = $attribute_name.$post_fix;
										$code = VendorItemAttributes::where('v_id',$v_id)->where('code',$attribute_name)->first();
										if($code){
											$post_fix = '_'.rand(10, 99);
											$w=true;
										}else{
											$w=false;
										}
									}

									$vendorItemAttri->code = $attribute_name;
									$vendorItemAttri->save();
								}

								VendorItemAttributeValueMapping::updateOrCreate(
									['v_id' => $v_id , 'item_id' => $product->id , 'item_attribute_id' => $itemAttri->id ],
									['item_attribute_value_id' => $itemAttrVal->id ]
								);
							}
		            	}
		            }

		            // dd('Product Save');


		            #### Creating and updating Item Attribute End #####
		            ########################################################


		            ########################################################
		            #### Creating and updating Category Start #####
		            // $this->productconfig->vendorCatgory($request, $product->id);
		            	### Zwing category code generation is pending ###
		            $category = $item['category'];
		            $catCount = 0;
		            if($client->id == 1){
		            	VendorItemCategoryMapping::where('v_id', $v_id)->where('item_id' , $product->id)->delete();
		            	foreach ($category as $catIndex => $cat) {

		            		if($cat['category_name']!= '' && $cat['category_code']!= ''){ 
			            		// $itemCategory = ItemCategory::firstOrCreate(['name' => $cat['category_name']]);
			            		// $itemCategoryId = VendorItemCategoryIds::firstOrCreate( ['v_id' => $v_id, 'ref_category_code' =>    ]);
								// $itemCategory =ItemCategory::where('name', $cat['category_name'])->first();
								// if(!$itemCategory){
								// 	$itemCategory = new ItemCategory;
								// 	$itemCategory->name =  $cat['category_name'];
								// 	$itemCategory->save(); 
								// }
			            		$itemCategoryId = null;
			            		$itemCategoryId = VendorItemCategoryIds::where('v_id', $v_id)->where('ref_category_code', $cat['category_code'])->first();
			            		$itemCategory = null;
			            		if(!$itemCategoryId){
			            			$itemCategoryId = VendorItemCategoryIds::create([
			            				'v_id' => $v_id ,
			            				'category_code' => $this->generatecategoryCode($v_id), //Need to implement this
			            				'ref_category_code' => $cat['category_code']
			            			]);

			            			$itemCategory = new ItemCategory;
									$itemCategory->name =  $cat['category_name'];
									$itemCategory->save();
			            		}else{
			            			$itemCategory =ItemCategory::where('id', $itemCategoryId->item_category_id)->first();
			            		}

			            		$itemCategoryId->item_category_id = $itemCategory->id;
			            		$itemCategoryId->parent_id = 0;

			            		if(isset($cat['parent_category_code']) && $cat['parent_category_code'] !=''){
			            			$parentCategoryId = VendorItemCategoryIds::where('ref_category_code', $cat['parent_category_code'])->first();
			            			if($parentCategoryId){
			            				$itemCategoryId->parent_id = $parentCategoryId->item_category_id;
			            			}else{
			            				$error_list[] = [ 'error_for' =>  'item_'.$itemIndex.'_category_'.$catIndex.'parent_category_code',  'id' => $item['item_code'], 'messages' => ['Parent Code is not given or it is no is sequence '] ] ; 	
			            			}
			            		}
			            		$itemCategoryId->save();
			            		
			            		VendorItemCategoryMapping::Create(['v_id' => $v_id, 'item_id' => $product->id, 'item_category_id' => $itemCategory->id ]);
			            		$catCount++;
			            	}
		            	}
		            }else{

		            	VendorItemCategoryMapping::where('v_id', $v_id)->where('item_id' , $product->id)->delete();
		            	foreach ($category as $catIndex => $cat) {
		            		if($cat['category_name']!= '' && $cat['category_code']!= ''){
			            		$itemCategory = ItemCategory::firstOrCreate(['name' => $cat['category_name']]);
			            		$itemCategoryId = VendorItemCategoryIds::where('v_id', $v_id)->where('ref_category_code', $cat['category_code'])->first();

			            		if(!$itemCategoryId){
			            			$itemCategoryId = VendorItemCategoryIds::create([
			            				'v_id' => $v_id ,
			            				'category_code' => $this->generatecategoryCode($v_id), //Need to implement this
			            				'ref_category_code' => $cat['category_code']
			            			]);
			            		}

			            		$itemCategoryId->item_category_id = $itemCategory->id;
			            		$itemCategoryId->parent_id = 0;

			            		if(isset($cat['parent_category_code']) && $cat['parent_category_code'] !=''){
			            			$parentCategoryId = VendorItemCategoryIds::where('category_code', $cat['parent_category_code'])->first();
			            			if($parentCategoryId){
			            				$itemCategoryId->parent_id = $parentCategoryId->item_category_id;
			            			}else{
			            				$error_list[] = [ 'error_for' =>  'item_'.$itemIndex.'_category_'.$catIndex.'parent_category_code',  'id' => $item['item_code'], 'messages' => ['Parent Code is not given or it is no is sequence '] ] ; 	
			            			}
			            		}
			            		$itemCategoryId->save();
			            		
			            		VendorItemCategoryMapping::Create(['v_id' => $v_id, 'item_id' => $product->id, 'item_category_id' => $itemCategory->id ]);
			            		$catCount++;
			            	}
		            	}

		            }

		            //Assigning Default Category
		            if($catCount == 0){
		            	// $itemCategory = ItemCategory::firstOrCreate(['name' => 'Default']);
	            		// $itemCategoryId = VendorItemCategoryIds::firstOrCreate( ['v_id' => $v_id, 'category_code' =>   'DEFAULT' ]);
	            		// $itemCategoryId->item_category_id = $itemCategory->id;
	            		// $itemCategoryId->parent_id = 0;
	            		$itemCategory = ItemCategory::firstOrCreate(['name' => 'Default']);
			            		$itemCategoryId = VendorItemCategoryIds::where('v_id', $v_id)->where('item_category_id', $itemCategory->id)->first();
			            		if(!$itemCategoryId){
			            			$itemCategoryId = VendorItemCategoryIds::create([
			            				'v_id' => $v_id ,
			            				'category_code' => $this->generatecategoryCode($v_id), //Need to implement this
			            				'ref_category_code' => 'DEFAULT'
			            			]);
			            		}

			            		$itemCategoryId->item_category_id = $itemCategory->id;
			            		$itemCategoryId->parent_id = 0;

	            		       $itemCategoryId->save();
	            		
	            		VendorItemCategoryMapping::Create(['v_id' => $v_id, 'item_id' => $product->id, 'item_category_id' => $itemCategory->id ]);
	           
		            }

		            #### Creating and updating Category End #####
		            ########################################################
		            


		            ########################################################
		            #### Creating and updating Variant Start #####
		            // $variantCombinationResult = json_decode($this->productconfig->variantCombination($request, $product->id, $request->auto_sku));
		            $hsn_code = null;
		            if(isset($item['tax_code']) ){
		            	$hsn_code = $item['tax_code'];
		            }else{
		            	$hsnCode = HsnCode::where('hsncode', $item['hsn_code'])->first();
			            if($hsnCode){
			            	$hsn_code = $item['hsn_code'];
			            }else{
			            	$hsnCode = HsnCode::where('hsncode', '0'.$item['hsn_code'])->first();
			            	if($hsnCode){
				            	$hsn_code = '0'.$item['hsn_code'];
				            }
			            }
		            }

		            if($country_code == 'BD'){
		            	$hsn_code = 'BN001';
		            }

		            foreach($item['variants'] as $vkey => $variant ){

		            	//This is Temporary Condition need to remove added for janavi
		            	if($client->id == 2){
							if(strlen($variant['barcode']) <= 2){
								$error = [ 'error_for' =>  'item_'.$itemIndex.'_variants_'.$vkey.'_barcode',  'id' => $item['item_code'], 'messages' => ['This Barcode '.$variant['barcode'].' size is less. minimum size is 3'] ] ; 
								$error_list[] = $error;
								continue;
							}
						}
						
						if (preg_match('/[\'^£$%&*()}{@#~?><>,|=_+¬]/', $variant['barcode'])){
							$error = [ 'error_for' =>  'item_'.$itemIndex.'_variants_'.$vkey.'_barcode',  'id' => $item['item_code'], 'messages' => [' Barcode '.$variant['barcode'].' Should not contain special character'] ] ; 
							$error_list[] = $error;
							continue;
						}

		            	//$variant = $item['variants'][0];
		            	$variant_combi = '';
		            	$sku = null;
		            	if($product->sku == 'auto'){
		            		$sku = $product->id.'-'.$variant_combi;
		            	}else{
		            		$sku = $variant['sku_code'];
		            	}

		            	$ref_sku_code = null;
		            	if(isset($variant['sku_code'])){
		            		$ref_sku_code = $variant['sku_code'];
		            	}

		            	//Default variant
		            	$defaultVariant = [[
		            		'attribute_name' => 'default',
		            		'attribute_name_code' => 'default',
		            		'attribute_value' => 'default',
		            		'attribute_value_code' => 'default'
		            	]];

		            	//Assinging default variant or the given one
		            	//dd(count($variant['variant_attributes']));
		            	if(count($variant['variant_attributes']) == 0 ||  $client->id == 1){
		            		$variant['variant_attributes'] = $defaultVariant;
		            	}elseif($variant['variant_attributes'] == null || count($variant['variant_attributes']) == 0){
		          
		            		$error = [ 'error_for' =>  'item_'.$itemIndex.'_variants_'.$vkey.'_variants_attributes',  'id' => $item['item_code'], 'messages' => ['Variant Attributes cannot be null or empty'] ] ; 
							$error_list[] = $error;
							continue;
		            	}
		            	// dd($variant['variant_attributes']);
		            	$matrix_mapping_arr = [];
		            	foreach($variant['variant_attributes'] as $attribute){
		            		$attributeName = $attribute['attribute_name'];
		            		$attributeValue = $attribute['attribute_value'];
		            		$itemAttribute =  ItemVariantAttributes::firstOrCreate(['name' => $attributeName ]);
		            		$itemAttributeValue = ItemVariantAttributeValues::firstOrCreate(['value' => $attributeValue]);
		            		
		            		$ref_attribute_code = $attributeName;
		            		if(isset($attribute['attribute_name_code']) && $attribute['attribute_name_code'] != ''){
		            			$ref_attribute_code = $attribute['attribute_name_code']; 
		            		}

		            		$vendorItemVarAttri = VendorItemVariantAttributes::updateOrCreate(
		            			['v_id' => $v_id,'item_variant_attribute_id' => $itemAttribute->id ],
		            			['ref_attribute_code' => $ref_attribute_code ]
		            		);

		            		//Creating Code
		            		if($vendorItemVarAttri->code =='' || $vendorItemVarAttri->code == null){
								$attributeName =  'va_'.str_replace('/' ,'', str_replace(' ','_',str_replace('&','a', strtolower(trim($attributeName)) )) );
								$w = true;
								$post_fix='';
								while($w){
									$attributeName = $attributeName.$post_fix;
									$code = VendorItemAttributes::where('v_id',$v_id)->where('code',$attributeName)->first();
									if($code){
										$post_fix = '_'.rand(10, 99);
										$w=true;
									}else{
										$w=false;
									}
								}
								$vendorItemVarAttri->code = $attributeName;
								$vendorItemVarAttri->save();
							}

		            		VendorItemVariantAttributeValueMapping::firstOrCreate(['v_id' => $v_id, 'item_id' => $product->id, 'item_variant_attribute_id' => $itemAttribute->id , 'item_variant_attribute_value_id' => $itemAttributeValue->id ]);

		            		$attributeValue = str_replace(' ','-', $attributeValue);
		            		$attributeValue = str_replace('&','', $attributeValue);
		            		if($variant_combi != ''){
								$variant_combi .= '-';
		            		}

		            		$variant_combi .= $attributeValue;
		            		$matrix_mapping_arr[] = [ 'item_variant_attribute_id' => $itemAttribute->id, 'item_variant_attribute_value_id' => $itemAttributeValue->id ];
		            	}
                        //dd($variant_combi);
		            	if($client->id == 1){
                        	$variant_combi = 'default';
		            	}
                         //dd($variant_combi);
		            	
		            	foreach($matrix_mapping_arr as $mapping){

			            	VendorItemVariantAttributeValueMatrixMapping::firstOrCreate(['v_id' => $v_id, 'item_id' => $product->id, 'variant_combi' => $variant_combi ,  'item_variant_attribute_id' => $mapping['item_variant_attribute_id'] , 'item_variant_attribute_value_id' => $mapping['item_variant_attribute_value_id'] ]);
		            	}

		            	$vendorSku = null;
		            	if($ref_sku_code){
		            		$vendorSku = VendorSkuDetails::where('v_id', $v_id)->where('item_id', $product->id)->where('ref_sku_code', $ref_sku_code)->first();
		            	}else{
		            		$vendorSku = VendorSkuDetails::where('v_id', $v_id)->where('item_id', $product->id)->where('variant_combi', $variant_combi)->first();
		            	}

		            	if($vendorSku){//Variant Exists

		            		//if($sku != null && strlen($sku)>1){

				           		//$sku = VendorSkuDetails::where('v_id', $v_id)->where('sku', $sku)->first();
			            		if($vendorSku){
			            			if($vendorSku->v_id == $v_id && $vendorSku->item_id == $product->id ){

			            			}else{

			            				$error = [ 'error_for' =>  'item_'.$itemIndex.'_variants_barcode',  'id' => $item['item_code'], 'messages' => ['Cannot update because '.$variant['sku_code'].' Sku is already assign to another Item variant'] ] ; 
				        				$error_list[] = $error;
			            			}
			            		}
			            		// else{

			            		// 	$vendorSku->sku = $variant['sku_code'];
					            // }

					            // $barcode = VendorSkuDetails::where('v_id', $v_id)->where('barcode', $variant['barcode'])->first();

					            $barcodes = [];
			            		//Having Mulitple Barcodes
			            		if(is_array($variant['barcode']) ){
			            			$barcodes = $variant['barcode'];
			            		//Having Single Barcode	
			            		}else{
			            			$barcodes[] = $variant['barcode'];
			            		}

			            		foreach($barcodes as $bar){
			            			$barcode = VendorSkuDetailBarcode::where('barcode', $bar)->where('v_id', $v_id)->first();

						            if($barcode){//Barcode already Exists

				            			if($barcode->v_id == $v_id && $barcode->sku->item_id == $product->id ){

				            			}else{

				            				$error = [ 'error_for' =>  'item_'.$itemIndex.'_variants_barcode',  'id' => $item['item_code'], 'messages' => ['Cannot update because '.$variant['barcode'].'Barcode is already assign to another Item Variant'] ] ; 
						        			$error_list[] = $error;
				            			}
				            		}else{

				            			// $vendorSku->barcode = $variant['barcode'];
							            VendorSkuDetailBarcode::Create([
							            	'v_id' => $v_id,
							            	'vendor_sku_detail_id' => $vendorSku->id ,
							            	'barcode' => $bar ,
							            	'sku_code' => $vendorSku->sku_code ,
							            	'item_id' => $vendorSku->item_id ,
							            	'is_active' => '1'
							            ]);
							            
						            }
						        }

				            	$vendorSku->hsn_code = $hsn_code;
					            $vendorSku->save();

							// }else{
							// 	$error = [ 'error_for' =>  'item_'.$itemIndex.'',  'id' => $item['item_code'], 'messages' => ['Sku is Required'] ] ; 
							// 	$error_list[] = $error;
							// }

		            	}else{// Variant Does not exist
		            		$barcode = null;
		            		$barcodes = [];
		            		//Having Mulitple Barcodes
		            		if(is_array($variant['barcode']) ){
		            			$barcode = VendorSkuDetailBarcode::whereIn('barcode', $variant['barcode'])->where('v_id', $v_id)->get();
		            			$barcodes = $variant['barcode'];
		            		//Having Single Barcode	
		            		}else{
		            			$barcodes[] = $variant['barcode'];
		            			$barcode = VendorSkuDetailBarcode::where('barcode', $variant['barcode'])->where('v_id', $v_id)->get();
		            		}


		            		if($barcode->isEmpty()){
		            			$loop = 0;
		            			$try = 5;
		            			while($loop < $try){

			            			try {
			            				$sku_code = generateSkuCode($v_id);
						            	$VendorSku = VendorSkuDetails::Create([
						            		'v_id' => $v_id,
						            		'item_id' => $product->id,
						            		'ref_sku_code' => $ref_sku_code,
						            		'sku_code' => $sku_code ,
						            		'variant_combi' => $variant_combi,
						            		'sku' => $sku,				        
						            		'hsn_code' => $hsn_code,
						            		'is_active' => '1'
						            	]);

						            	$loop = $try;

						            }catch(\Illuminate\Database\QueryException $ex){ 
										// dd($ex);
										if($loop == $try -1){
											throw new \Exception($ex->getMessage());
										}

										if($ex->errorInfo[1] == 1062) { 
											if (strpos($ex->errorInfo[2], 'vendor_sku_details_sku_code_unique') !== false) {
												//Regenerating sku Code 
												$loop++;
											}
										}
									}
								}

				            	
					            foreach ($barcodes as  $bar) {
						            VendorSkuDetailBarcode::Create([
						            	'v_id' => $v_id,
						            	'vendor_sku_detail_id' => $VendorSku->id ,
						            	'barcode' => $bar ,
						            	'sku_code' => $sku_code,
							            'item_id' => $product->id ,
						            	'is_active' => '1'
						            ]);
					            }

			            	}else{

			            		
			            		$error = [ 'error_for' =>  'item_'.$itemIndex.'_variants_'.$vkey.'_barcode',  'id' => $item['item_code'], 'variant_combi' => $variant_combi, 'messages' => 'Cannot Create Because Barcode'.json_encode($variant['barcode']).' is already assign to another sku: '.json_encode($barcode->pluck('sku_code')->all()).' item_id : '.json_encode($barcode->pluck('item_id')->all()) ]  ; 
			        			$error_list[] = $error;

			            	}

		            	}

		            	foreach($variant['prices'] as $price){
		            		
		            		if($price['unit_mrp'] > 0.0){
                               if($client_id==1){
		            			  $mrp =getExchangeRate($v_id,$source_currency,$target_currency,$price['unit_mrp']);
		            			  $rsp =getExchangeRate($v_id,$source_currency,$target_currency,$price['unit_rsp']);
		            			  
		            			  $unit_mrp    = $mrp['amount']; 
                                  $unit_rsp    = $rsp['amount']; 

		            		    }else{
                                  $unit_mrp    = $price['unit_mrp']; 
                                  $unit_rsp    = $price['unit_rsp']; 

		            		    }

			            		$itemPrice = ItemPrices::firstOrCreate([ 'mrp' =>$unit_mrp,'rsp' => $unit_rsp, 'special_price' =>$unit_rsp ]);
			            		VendorItemPriceMapping::firstOrCreate(['v_id' => $v_id, 'item_id' => $product->id, 'variant_combi' => $variant_combi , 'item_price_id' => $itemPrice->id ]);
			            		// Check
			                    //Batch::firstOrCreate(['v_id' => $v_id, 'item_id' => $product->id]);
			            	}

		            	}

		            	if($client->id == 1){
		            		break;
		            	}
		            }
		            // dd('product created');

		            #### Creating and updating Variant End #####
		            ########################################################

		            //print_r($variantCombinationResult);
		            //die;
		            // if ($variantCombinationResult->status == 'error') {
		            //     return response()->json($variantCombinationResult, 422);
		            // }

		            // $this->productconfig->addproductattribute($request, $product->id);
		            // $this->productconfig->addVariantAttributeValueMatrixMapping($this->cartesian($request->variant), $product->id);
		            if(count($error) >=1){
		            	// dd($error_list);
		            	$asyn_status = 'FAILED';
		            	$total_fail++;
		            
		            }

		            if(count($error) <= 0){
			        	$total_success++;
				    }
		            
		        }
		    }

		    $itemCat = new ItemCategoryController;
    		$itemCat->updateCategoryLevel($v_id);

		    #### Flat Table Jobs Start ####
            $product_ids = array_unique($product_ids);
		    // $dbName = DB::connection()->getDatabaseName();
		    $dbName = getDatabaseName($v_id);
		    
            if(count($product_ids) > 0 ){
	            if(count($product_ids) < 200){

	            	$productData = [ 'v_id' => $v_id, 'dbname' => $dbName, 'id' => implode(',', $product_ids) ];
				    dispatch(new FlatProduct($productData) );

	       			// foreach($product_ids as $id){
				    // 	$productData = [ 'v_id' => $v_id, 'dbname' => $dbName, 'id' => $id ];
				    //     dispatch(new FlatProduct($productData) );
				    // }
				    ### This code is commeted because getting timeout for multiple product ids
			        // $productData = [ 'v_id' => $v_id, 'dbname' => $dbName, 'product_id' => $product_ids ];
			        // dispatch(new FlatProduct($productData) );
	            }else{
	            	$productData = [ 'v_id' => $v_id, 'dbname' => $dbName ];
	            	dispatch(new FlatProduct($productData) );
	            }
		    }
            #### Flat Table Jobs Ends ####

    	}catch( \Exception $e){

    		#### Flat Table Jobs Start ####
            $product_ids = array_unique($product_ids);
		    // $dbName = DB::connection()->getDatabaseName();
		    $dbName = getDatabaseName($v_id);
            if(count($product_ids) > 0 ){
	            if(count($product_ids) < 200){
	            	foreach($product_ids as $id){
				    	$productData = [ 'v_id' => $v_id, 'dbname' => $dbName, 'id' => $id ];
				        dispatch(new FlatProduct($productData) );
				    }
				    ### This code is commeted because getting timeout for multiple product ids
			        // $productData = [ 'v_id' => $v_id, 'dbname' => $dbName, 'product_id' => $product_ids ];
			        // dispatch(new FlatProduct($productData) );
	            }else{
	            	$productData = [ 'v_id' => $v_id, 'dbname' => $dbName ];
	            	dispatch(new FlatProduct($productData) );
	            }
		    }
            #### Flat Table Jobs Ends ####
           
            //dd($e->getMessage());
    		$response = [
    			'status' => 'fail' ,
    			'message' => 'Server Error' ,
    			'total_record' => $total_record,
                'total_success' => $total_success,
                'total_fail' => $total_fail,
                'errors' => $e->getMessage()
    		];
    		// $e->getMessage().'\n'.$e->getTraceAsString()
    		$response_status_code = 500;

    		$asyn_status = 'FAILED';

    		$asyn->status = $asyn_status;
    		$asyn->response = json_encode($response);
    		$asyn->save();

    		Log::error('Item Creation Error: Error for- '.$error_for.' Message: unexcepted error' );
			Log::error($e);

    	}
		// dd($error_list);
	   	if($type=='excel'){
	   	    //dd(cont($error_list));
	     	if(count($error_list) > 0){

	    		$response = [
	                'status' => 'fail',
	                'message' => "Some Error has occured",
	                'errors' => $error_list
	            ];

	       	}
	       	//dd($response);
	      	return response()->json($response, 200);     

	   	}else{   		    
	    	if(count($error_list) > 0){
	    		$response = [
	                'status' => 'fail',
	                'message' => "Some Error has occured",
	                'total_record' => $total_record,
	                'total_success' => $total_success,
	                'total_fail' => $total_fail,
	                'errors' => $error_list
	            ];

	            $asyn_status = 'FAILED';
	            $response_status_code = 422;
		    }

	    	if($asyn_status){
	    		$asyn->status = $asyn_status;
	    	}

	    	// dd($asyn_status);
	    	// $asyn->response_status_code = $response_status_code;
	    	// $asyn->response = $response;
	    	// $asyn->sav

	    	InboundApi::where('v_id', $v_id)->where('client_id', $client_id)->where('ack_id', $ack_id)->update(['status' => $asyn_status,  'response_status_code' => $response_status_code , 'response' => json_encode($response)]);
	    }

	}
	

	public function itemImport(Request $request){

      $params=['v_id'=>$request->v_id,'item_data'=>$request->item_data,'type'=>'excel'];
       //dd($params);
      return $staus =$this->processItemMasterCreationJob($params);
     
      //dd($staus);

	}	

	public function item_exist_check(Request $request){

		//required parameater v_id,store_id,barcode
		$this->validate($request,[
                                 'v_id' => 'required',
                                 'barcode' => 'required',
                                 ]);

        try{

           $v_id      = $request->v_id; 
           //$store_id  = $request->store_id;
           $barcode  = $request->barcode;
           

           $item_details= ItemList::where('v_id',$v_id)->where('barcode',$barcode)->first();

			if (is_null($item_details)) {
			    return response()->json(['status' => 'fail', 'message' => 'Item not allocated'], 200);
			} else {
			    return response()->json(['status' => 'sucess','data'=>$item_details, 'message' => 'Item allocated'], 200);
			}  
			                 
            
        }catch( \Exception $e ) {
			 Log::error($e);

		    	return response()->json([ 'status' => 'fail' , 'message' => 'Server Error'   ] , 500);
		    }  

		
	} 

	//get last inward price 
	public function getLastInwardPrice(Request $request){


		//required parameater v_id,store_id,barcode
		$this->validate($request,[
                                 'v_id' => 'required',
                                 'barcode' => 'required',
                                 'store_id' => 'required',
                                 ]);
        try{

           $v_id      = $request->v_id; 
           $store_id  = $request->store_id;
           $barcode  = $request->barcode;
           $sourcetype  = empty($request->source_site_type)?'store':$request->source_site_type;
             
           $last_inward_price= LastInwardPrice::where('v_id',$v_id)
					                      ->where('barcode',$barcode)
					                      ->where('destination_site_id',$store_id)
					                      ->where('source_site_type',$sourcetype)
					                      ->orderBy('id','DESC')
					                      ->first();

			if (is_null($last_inward_price)) {
			    return response()->json(['status' => 'fail', 'message' => 'Item supply price not exist'], 200);
			} else {
			    return response()->json(['status' => 'sucess','data'=>$last_inward_price, 'message' => 'Item price available'], 200);
			}  
			                 
            
        }catch( \Exception $e ) {
			 Log::error($e);

		    	return response()->json([ 'status' => 'fail' , 'message' => 'Server Error'   ] , 500);
		    }  

		
	}

	// get last inward price 
	public function saveLastInwardPrice(Request $request)
	{
		// required parameater v_id,store_id,barcode

		$this->validate($request, [ 'v_id' => 'required', 'barcode' => 'required', 'source_site_id' => 'required', 'item_id' => 'required', 'supply_price' => 'required', 'source_transaction_id' => 'required', 'source_transaction_type' => 'required']);
		
        try {

            $v_id       		 	= $request->v_id; 
            $source_site_id   	 	= $request->source_site_id;
            $destination_site_id 	= empty($request->destination_site_id)?'':$request->destination_site_id;
            $destination_site_type 	= empty($request->destination_site_type)?'':$request->destination_site_type;
            $source_site_type    	= empty($request->source_site_type)?'store':$request->source_site_type;
            $item_id    		 	= $request->item_id;
            $barcode    		 	= $request->barcode;
            $supply_price        	= format_number($request->supply_price);
            $discount            	= empty($request->discount)?0.00:format_number($request->discount)					  ;
            $discount_details    	= empty($request->discount_details)?'':$request->discount_details;
            $tax            	 	= empty($request->tax)?0.00:format_number($request->tax);
            $tax_details    	 	= empty($request->tax_details)?'':$request->tax_details;
            $charge    			 	= empty($request->charge)?0.00:format_number($request->charge);
            $charge_details      	= empty($request->charge_details)?'':$request->charge_details;
            $source_transaction_id  = $request->source_transaction_id;
            $source_transaction_type= $request->source_transaction_type;

            $inwardData = [
	                        'v_id' 						=>	trim($v_id),
	                        'source_site_id' 			=>	trim($source_site_id),
	                        'destination_site_id' 		=>	trim($destination_site_id),
	                        'destination_site_type' 	=>	trim($destination_site_type),
	                        'source_site_type' 			=>	trim($source_site_type),
	                        'item_id' 					=>	trim($item_id),
	                        'barcode' 					=>	trim($barcode),
	                        'supply_price' 				=>	trim($supply_price),
	                        'discount' 					=>	trim($discount),
	                        'discount_details' 			=>	trim($discount_details),
	                        'tax' 						=>	trim($tax),
	                        'tax_details'				=>	trim($tax_details),
	                        'charge' 					=>	trim($charge),
	                        'charge_details'			=>	trim($charge_details),
	                        'source_transaction_id' 	=>	trim($source_transaction_id),
	                        'source_transaction_type' 	=>	trim($source_transaction_type)
                        ];

            $whereForPrice = ['v_id' => $v_id, 'source_site_id' => $source_site_id, 'barcode' => $barcode, 'source_site_type' => $source_site_type, 'supply_price' => $supply_price];

            $where = ['v_id' => $v_id, 'source_site_id' => $source_site_id, 'barcode' => $barcode, 'source_site_type' => $source_site_type];

            $checkSupplyPrice = LastInwardPrice::select('id','supply_price')->where($whereForPrice)->first();
            
            if(is_null($checkSupplyPrice)) {

            	$totalCount = LastInwardPrice::where($where)->count();
	            if($totalCount < 3) {
	            	$inwardDataId   = LastInwardPrice::create($inwardData);
	            	return response()->json(['status' => 'sucess', 'message' => 'Last inward price data save successfully'], 200);
	            } else {
	            	$lastInwardData = LastInwardPrice::where($where)->orderBy('id','ASC')->first();
	            	$inwardHistoryData = [
	            			'last_inward_price_id' 		=>	trim($lastInwardData->id),
	                        'v_id' 						=>	trim($lastInwardData->v_id),
	                        'source_site_id' 			=>	trim($lastInwardData->source_site_id),
	                        'destination_site_id' 		=>	trim($lastInwardData->destination_site_id),
	                        'source_site_type' 			=>	trim($lastInwardData->source_site_type),
	                        'item_id' 					=>	trim($lastInwardData->item_id),
	                        'barcode' 					=>	trim($lastInwardData->barcode),
	                        'supply_price' 				=>	trim($lastInwardData->supply_price),
	                        'discount' 					=>	trim($lastInwardData->discount),
	                        'discount_details' 			=>	trim($lastInwardData->discount_details),
	                        'tax' 						=>	trim($lastInwardData->tax),
	                        'tax_details'				=>	trim($lastInwardData->tax_details),
	                        'charge' 					=>	trim($lastInwardData->charge),
	                        'charge_details'			=>	trim($lastInwardData->charge_details),
	                        'source_transaction_id' 	=>	trim($lastInwardData->source_transaction_id),
	                        'source_transaction_type' 	=>	trim($lastInwardData->source_transaction_type)
                        ];
                    LastInwardPriceHistory::create($inwardHistoryData); 
                    LastInwardPrice::where('id',$lastInwardData->id)->delete();
                    $result   = LastInwardPrice::create($inwardData);
                    return response()->json(['status' => 'sucess', 'message' => 'Last inward price data save successfully with history'], 200);
	            }

            } else {
            	LastInwardPrice::where('id',$checkSupplyPrice->id)->update($inwardData);
            	return response()->json(['status' => 'sucess', 'message' => 'supply price update successfully'], 200);
            }             
            
        } catch( \Exception $e ) {
			Log::error($e);
		    return response()->json([ 'status' => 'fail' , 'message' => 'Server Error'   ] , 500);
		}
	}


	// category code 


	private function generatecategoryCode($v_id)
    {
           
            $code =  'C'.$this->incrementCode($v_id);
            
            if($this->categoryCodeExists($code,$v_id))
            {
              return  $this->generatecategoryCode($v_id);
            }
            return $code;

    }

    private function incrementCode($v_id)
    {

         $code = '001';

         $vendorCategory =VendorItemCategoryIds::where('v_id',$v_id)
                                             ->orderBy('id','DESC')
                                             ->first();                                                     
                                                                                                            
          if($vendorCategory)
          {  
              $n=strlen($code);
              $current_id = substr($vendorCategory->category_code,-$n); 
              $inc=++$current_id;
              if($inc<=999){
              $code =str_pad($inc,$n,"0",STR_PAD_LEFT);
            }else{
              $code=$this->randomchar();    
            }
          }
          return $code;
    }

    private function randomchar()
    {
          $length =3;
          $char = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz"), 0, $length);
          return $char; 
    }

     private function categoryCodeExists($code,$v_id)
     {
         return   VendorItemCategoryIds::where('category_code',$code)
                                    ->where('v_id',$v_id)
                                    ->exists();
     }

}