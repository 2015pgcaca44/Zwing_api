<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Model\Item\ItemList;
use App\Model\Items\Item;
use App\Model\Items\VendorItem;
use App\Model\Items\VendorSku;
use App\Model\Items\VendorSkuDetails;
use App\Model\Items\VendorSkuDetailBarcode;
use App\Model\Stock\Batch;
use App\Model\Stock\Serial;
use App\Model\Stock\StockPointSummary;
use App\Store;
use App\Organisation;
use Validator;
use App\Model\Supplier\Supplier;
use App\Model\Supplier\SupplierAddress;
use App\Model\InboundApi;
use Log;
use DB;
use App\GrtHeader;
use App\GrtDetail;
use App\LastInwardPrice;
use App\Http\Controllers\StockController;
use App\Http\Controllers\VendorSettingController;
use App\VendorImage;
use App\Model\Stock\StockTransferOrder;
use App\Model\Stock\StockTransferOrderDetails;
use App\Model\Grn\Grn;
use App\Model\Grn\GrnList;

class GrtController extends Controller
{
    public function __construct()
	{
		//$this->middleware('auth');
		 $this->middleware('auth', ['except' => ['create','grn_print_recipt','get_print_recipt','sst_print_reciept','grt-receipt']]);
		 //JobdynamicConnection(127);
		 
	}



    public function create(Request $request){

    	
    	if (!$request->isJson()) {

	    	try{	

		    	$data = $request->json()->all();
		    	$client = oauthUser($request);
		    	$client_id = $client->client_id;
			    $clients   = $client->id;
		    	$messages = [
		        	// 'required' => 'The :attribute field is required.',
		        	'item_list.*.item_barcode.exists' => 'Item Barcode does not Exists'
		        ];
		        $custom_valiation = [];

		        $validation = [
	                'organisation_code' => 'required',
	                'grt_doc_no' => 'required',
	                'src_site_code' => 'required',
	                'created_date' => 'date_format:Y-m-d',
	                'stock_point_id'=>'required',
	                'supplier_code'=>'required',
	                'supplier_legal_name'=>'required',
	                'supplier_trade_name'=>'required',
	                'item_list' => 'array',
	                'item_list.*.grt_detail_no' => 'required',
	                'item_list.*.item_sku_code' => 'required',
	                'item_list.*.item_barcode' => 'required',
	                //'item_list.*.is_batch' => 'required|in:Yes,true,No,false',
	                'item_list.*.supply_price' => 'numeric',
	                'item_list.*.is_batch' => 'required|boolean',
	                'item_list.*.is_serial' => 'required|boolean',
	                'item_list.*.return_qty' => 'required',
	                'item_list.*.tax_rate' => 'required_with:item_list.*.tax_amount',
	                'item_list.*.tax_rate_desc' => 'required_with:item_list.*.tax_amount',
	                'item_list.*.tax_code' => 'required_with:item_list.*.tax_amount',
	                'item_list.*.batch_details.*.batch_no' => 'required_if:item_list.*.is_batch,1,true',
	                'item_list.*.batch_details.*.return_qty' => 'required_if:item_list.*.is_batch,1,true',
	                'item_list.*.serial_list.*.serial_no' => 'required_if:item_list.*.is_serial,1,true',
	            ];
		    	

		        if($clients==1){
		        	//For Ginesys ref_item_code is sku code
		        	$custom_valiation = ['item_list.*.item_sku_code' => 'required|exists:vendor_items,ref_item_code,deleted,NULL',
			        	];
		        }else{
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
		        $asyn->api_name = 'client/ad-hoc-grt/create';
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

		        $source_site_code=  $data['src_site_code'];
		        $store = Store::select('store_id','short_code')->where('v_id', $v_id)->where('store_reference_code', $source_site_code)->first();
		        
		        if(!$store){
                    
                    $error_list =  [ 
		        		   [ 'error_for' =>  'src_site_code' , 'messages' => ['Unable to find This source_site_code'] ]
		            ]; 

		        	$response = [ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' => $error_list  ];
		        	$asyn->status = 'FAIL';
		        	$asyn->response = json_encode($response);
		        	$asyn->save();

		        	return response()->json( $response, 422);
		        }
		        
		        $reference_code=$data['supplier_code'];
		        $supplier = Supplier::select('id','reference_code','trade_name','legal_name')->where('v_id', $v_id)->where('reference_code', $reference_code)->first();
		        //dd($supplier);
		        $legal_name=$data['supplier_legal_name'];
                $trade_name=$data['supplier_trade_name'];
                

		        if(!$supplier){

	               $code = supplierCodeGenerator($v_id);
                   //dd($code);
                   $supplier   = new Supplier;
                   $supplier->supplier_code    = $reference_code;
                   $supplier->code             = $code;
                   $supplier->reference_code   = $reference_code;
                   $supplier->trade_name       = $trade_name;
                   $supplier->legal_name       = $legal_name;
                   $supplier->v_id             = $v_id;
                   $supplier->save();

                   $states =DB::connection('mysql')->table('states')->where('code', $data['supplier_state_code'])->first();
                   $state_id='';
                   if($states){
                   		$state_id=$states->id;
                   }
                   $address = new  SupplierAddress;
	    		   $address->supplier_id = $supplier->id;
	    		   $address->address_line_1 = $data['supplier_address'];
	    		   $address->city_id = $data['supplier_city'];
	    			
	    		   $address->pincode = $data['supplier_pincode'];
	    		   $address->state_id = $state_id;
	    		   $address->gstin = $data['supplier_gstin'];
	    		   $address->save();
                }else{
                   $supplier->trade_name       = $trade_name;
                   $supplier->legal_name       = $legal_name;
                   $supplier->save();
                }

                $store_id = $store->store_id;
		        $supplier_id =$supplier->id;
		        $stock_point_id=$data['stock_point_id'];
		        $ref_doc_no=$data['grt_doc_no'];
		        //dd($supplier_id);
		        //calculate total tax,amount,discount etc 
		        $newItemList = [];
		        $return_qty = 0;
		        $grt_supply_price = 0;
		        $grt_subtotal = 0;
		        $grt_discount = 0;
		        $grt_tax = 0;
		        $grt_total= 0;
		        $grt_charge=0;
		        $item_list = $data['item_list'];

		        
		        foreach ($item_list as $key => $list) {

			        $return_qty += $list['return_qty'];
			        
			        $discount =  (isset($list['discount']) && $list['discount']!='' && $list['discount'] >= 0.000)?$list['discount']:'0.0';
			        $misc_charge_value =  (isset($list['misc_charge_value']) && $list['misc_charge_value']!='' && $list['misc_charge_value'] >= 0.000)?$list['misc_charge_value']:'0.0';

			        $supply_price =  (isset($list['supply_price']) && $list['supply_price']!='' && $list['supply_price'] >= 0.000)?$list['supply_price']:'0.0';
			        
			        $subtotal=( ($supply_price*$list['return_qty'])-$discount+$misc_charge_value);
			        $taxable_amount=$subtotal;
			        $charge=$misc_charge_value;
			        
			        $tax =  (isset($list['tax_amount']) && $list['tax_amount']!='' && $list['tax_amount'] >= 0.000)?$list['tax_amount']:'0.0';
			        $netamt=$tax+$taxable_amount;
			        $total = $netamt;
			        
			       	if(isset($list['tax_amount']) && !empty($list['tax_amount']) ) {
			       		$tax_details=(object)['barcode'=>$list['item_barcode'],'cgst'=>$list['cgst_rate'],'sgst'=>$list['sgst_rate'],'igst'=>$list['igst_rate'],'cess'=>$list['cess_rate'],'cgstamt'=>$list['cgst_amount'],'sgstamt'=>$list['sgst_amount'],'igstamt'=>$list['igst_amount'],'cessamt'=>$list['cess_amount'],'netamt'=>$netamt,'taxable'=>$taxable_amount,'tax'=>$list['tax_rate'],'total'=>$netamt,'tax_name'=>$list['tax_rate_desc'],'tax_type'=>''];
			       	}else{
			        $tax_details=(object)['barcode'=>$list['item_barcode'],'cgst'=>'','sgst'=>'','igst'=>'','cess'=>'','cgstamt'=>'','sgstamt'=>'','igstamt'=>'','cessamt'=>'','netamt'=>'','taxable'=>'','tax'=>'','total'=>'','tax_name'=>'','tax_type'=>''];
			        }
			        $tax_details = json_encode($tax_details);

		        	if($clients==1){
						$ref_item_code = $list['item_sku_code']; //Ginesys sending sku_code which is same as item Code
						$vendorItem = VendorItem::where('ref_item_code', $ref_item_code)->where('v_id', $v_id)->first();
						$item =Item::select('name')->where('id', $vendorItem->item_id)->first(); 
						$skuData = VendorSkuDetails::select('id','sku_code','item_id','sku')->where('item_id', $vendorItem->item_id)->first();
						$sku_code = $skuData->sku_code;
						$sku = $skuData->sku;
						$item_id = $skuData->item_id;
						$item_name = $item->name;
					}else{

						$bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $list['item_barcode'] )->first();
	                        if($bar){
	                        	$skuData = VendorSkuDetails::select('sku_code','item_id','sku')->where('id', $bar->vendor_sku_detail_id)->first();
	                            $sku_code = $skuData->sku_code;
								$sku = $skuData->sku;
								$item_id = $skuData->item_id;
								$item =Item::select('name')->where('id', $skuData->item_id)->first(); 
								$item_name = $item->name;
	                        }
					}
					if($list['is_batch'] && $list['is_serial']){
						$error_list =[ 
		        		  			 	[ 'error_for' =>  'item_list.'.$key.'.is_batch.'.$key.'.is_serial' , 'messages' => ['Batch or Serial both are not true for the same item'] ]
		            				]; 

    					$response = [ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' => $error_list  ];
    					$asyn->status = 'FAIL';
			        	$asyn->response = json_encode($response);
			        	$asyn->save();
    					return response()->json( $response, 422);
					}
					//check for batch 
					$batch_return_qty=0;
					if($list['is_batch'] && count($list['batch_details']) >0 && empty($list['is_serial']) ){

						$is_batch=true;
						
						foreach ($list['batch_details'] as $key => $batch_val) {
							$batch_exist=Batch::where('batch_no',$batch_val['batch_no'])->first();
							if($batch_exist){
								$batch_return_qty+=$batch_val['return_qty'];
								$parmas=['v_id'=>$v_id,'vu_id'=>'','barcode'=>$list['item_barcode'],'sku'=>$sku,'store_id'=>$store_id,'stock_point_id'=>$stock_point_id,'batch_id'=>$batch_exist->id,'return_qty'=>$batch_val['return_qty']];
		        				$batch_data_list=$this->getItemAvailableBatches($parmas);
		        				
		        				if(count($batch_data_list)==0){

		        					$error_list =  [ 
		        		  			 [ 'error_for' =>  'item_list.'.$key.'.batch_details.'.$key.'.batch_no' , 'messages' => ['Unable to find data for this  batch_no '] ]
		            				]; 

		        					$response = [ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' => $error_list  ];
		        					$asyn->status = 'FAIL';
						        	$asyn->response = json_encode($response);
						        	$asyn->save();
		        					return response()->json( $response, 422);

		        				}else{
		        					$batch_data_t[]=$batch_data_list;
		        				}
							}else{
								$error_list =  [ 
		        		  			 [ 'error_for' =>  'item_list.'.$key.'.batch_details.'.$key.'.batch_no' , 'messages' => [' This batch_no not exist'] ]
		            			]; 

		        				$response = [ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' => $error_list  ];
		        				$asyn->status = 'FAIL';
					        	$asyn->response = json_encode($response);
					        	$asyn->save();
		        				return response()->json( $response, 422);
							}
							
						}
						    $singleArray=[];
							foreach ($batch_data_t as $key => $childArray) {
								
								foreach ($childArray as $key => $value1) {
									$singleArray[]=$value1;
								}
							}
							$batch_data=$singleArray;
							/*echo array_sum(array_column($batch_data, 'qty'));
							echo $list['return_qty'];
							dd($batch_return_qty);
							dd($batch_data);*/
							if($list['return_qty']!=$batch_return_qty){
								$error_list =  [ 
		        		  			 [ 'error_for' =>  'item_list.'.$key.'.batch_details.'.$key.'.return_qty' , 'messages' => [' batch_details return_qty not equal to the item_list return qty'] ]
		            			]; 

		        				$response = [ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' => $error_list  ];
		        				$asyn->status = 'FAIL';
					        	$asyn->response = json_encode($response);
					        	$asyn->save();
		        				return response()->json( $response, 422);
							}

					}else{
						$is_batch=false;
						$batch_data=[];
					}

					
					
					//check for serial 
					if($list['is_serial'] && count($list['serial_list']) >0 && empty($list['is_batch'])){
						$is_serial=true;
						foreach ($list['serial_list'] as $key => $serial_val) {
							$serial_exist=Serial::where('serial_no',$serial_val['serial_no'])->first();
							if($serial_exist){

								$parmas=['v_id'=>$v_id,'vu_id'=>'','barcode'=>$list['item_barcode'],'sku'=>$sku,'store_id'=>$store_id,'stock_point_id'=>$stock_point_id,'serial_id'=>$serial_exist->id];
		        				$serial_data_list=$this->getItemAvailableSerial($parmas);
		        				if(count($serial_data_list)==0){
		        					$error_list =  [ 
		        		  			 [ 'error_for' =>  'item_list.'.$key.'.serial_list.'.$key.'.serial_no' , 'messages' => ['Unable to find data for this  serial_no '] ]
		            				]; 

		        					$response = [ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' => $error_list  ];
		        					$asyn->status = 'FAIL';
						        	$asyn->response = json_encode($response);
						        	$asyn->save();
		        					return response()->json( $response, 422);
		        				}else{
		        					$serial_data_t[]=$serial_data_list;
		        				}
							}else{
								$error_list =  [ 
		        		  			 [ 'error_for' =>  'item_list.'.$key.'.serial_list.'.$key.'.serial_no' , 'messages' => [' This serial_no not exist'] ]
		            			]; 

		        				$response = [ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' => $error_list  ];
		        				$asyn->status = 'FAIL';
					        	$asyn->response = json_encode($response);
					        	$asyn->save();
		        				return response()->json( $response, 422);
							}
						}

						$singleArray=[];
						foreach ($serial_data_t as $key => $childArray) {
							
							foreach ($childArray as $key => $value1) {
								$singleArray[]=$value1;
							}
						}
						$serial_data=$singleArray;

					}else{
						$is_serial=false;
						$serial_data=[];
					}
		        	
					
			       	$newItemList[] = [
			       		'item_sku_code' => $list['item_sku_code'],
			       		'qty' => $list['return_qty'],
			        	'barcode' =>  $list['item_barcode'],
			        	'supply_price' => $supply_price,
			        	'subtotal' => $subtotal,
			        	'charge' => $charge,
			        	'discount' => $discount,
			        	'tax' =>  $tax,
			        	'tax_details' => $tax_details,
			        	'total' =>  $total,
			        	'ref_grt_detail_no' => $list['grt_detail_no'],
			        	'is_batch'=> $is_batch,
			        	'batch_data'=> $batch_data,
			        	'is_serial'=> $is_serial,
			        	'serial_data'=> $serial_data,
			        	'item_name'  => $item_name,
	                    'sku'        => $sku,
	                    'sku_code'   => $sku_code,
	                    'item_id'    => $item_id,
			       	];

			        $grt_subtotal += (float)$subtotal;
			        $grt_discount += (float)$discount;
			        $grt_tax += (float)$tax;
			        $grt_total += (float)$total;
			        $grt_charge += (float)$charge;
		        	
		        }
		          
		        $grt_no_sequence        =  $this->docIncrementNo($store_id);
            	$doc_no                 =  $this->generateDocNo($store_id);   

            	$grt=  new GrtHeader;
		        $grt->v_id               =  $v_id;
	            $grt->src_store_id       =  $store_id;
	            $grt->supplier_id        =  $supplier_id;
	            $grt->stock_point_id     =  $stock_point_id;
	            $grt->grt_no             =  $doc_no;
	            $grt->ref_grt_no         =  $ref_doc_no;
	            $grt->grt_no_sequence    =  $grt_no_sequence;
	            $grt->sent_qty           =  $return_qty;
	            $grt->transfer_type      =  '1';
	            $grt->creation_mode      =  'Ad-hoc'; 
	            $grt->subtotal           =  (string)$grt_subtotal;
	            $grt->discount_amount    =  (string)$grt_discount;
	            $grt->tax_amount         =  (string)$grt_tax;
	            $grt->charge_amount      =  (string)$grt_charge;
	            $grt->total              =  (string)$grt_total;
	            $grt->remark             =  '';
	            $grt->status             =  'POST';
	            $grt->trans_src_doc_id   =  $data['src_doc_id'];
	            $grt->save();

		        foreach($newItemList as $val){

		        	$grtDetail = [
                    'item_name'             => $val['item_name'],
                    'sku'                   => $val['sku'],
                    'sku_code'              => $val['sku_code'],
                    'barcode'               => $val['barcode'],
                    'item_id'               => $val['item_id'],
                    'store_id'              => $store_id,
                    'stock_point_id'        => $stock_point_id,
                    'qty'                   => $val['qty'],
                    'v_id'                  => $v_id,     
                    'grt_id'                => $grt->id,
                    'supply_price'          => (string)$val['supply_price'],
                    'subtotal'              => (string)$val['subtotal'],
                    'tax'                   => (string)$val['tax'],
                    'tax_details'           => $val['tax_details'],
                    'discount'              => (string)$val['discount'],
                    'discount_details'      => '',
                    'charge'                => (string)$val['charge'],
                    'charge_details'        => '',
                    'total'                 => (string)$val['total'],
                    'ref_grt_detail_no'     => $val['ref_grt_detail_no'],
                	];
                	$grtOutPost = [ 'variant_sku' => $val['sku'], 'sku_code' => $val['sku_code'], 'barcode' => $val['barcode'], 'item_id' => $val['item_id'], 'store_id' => $store_id, 'stock_point_id' => $stock_point_id, 'ref_stock_point_id' =>0, 'qty' => $val['qty'], 'v_id' => $v_id,'vu_id' => '', 'transaction_scr_id' => $grt->id, 'status' => 'POST', 'transaction_type' =>'GRT','stock_type' => 'OUT' ];

                	$StockController= new StockController;

                	if($val['is_batch'] && count($val['batch_data']) > 0){
                		
                		foreach ($val['batch_data'] as $key => $value) {
                			$grtDetail['batch_id']  = $value['id'];
                    		$grtDetail['batch_code']= $value['code'];
                    		$grtDetail['qty']       = $value['qty'];
                    		//dd($grtDetail);
                    		GrtDetail::create($grtDetail);

                    		$grtOutPost['batch_id']= $value['id'];
                    		$grtOutPost['batch_code']= $value['code'];
                    		$request = new \Illuminate\Http\Request();
                    		$request->merge([
		                    'v_id'     => $v_id,
		                    'store_id'  => $store_id,
		                    'trans_from' => 'VENDOR_PANEL',
		                    'c_id'   =>  '',
		                    'api_token'=>'',
		                    'vu_id'=>'',
		                    'stockData'=>$grtOutPost,
	                    	]);
                    		$StockController->stockOut($request);
                		}
                		
                	}
                	if($val['is_serial'] && count($val['serial_data']) > 0){
                		
                		foreach ($val['serial_data'] as $key => $value) {
                			$grtDetail['serial_id']  = $value['id'];
                    		$grtDetail['serial_code']= $value['code'];
                    		$grtDetail['qty']       = 1;
                    		GrtDetail::create($grtDetail);

                    		$grtOutPost['serial_id']= $value['id'];
                    		$grtOutPost['serial_code']= $value['code'];
                    		$request = new \Illuminate\Http\Request();
                    		$request->merge([
		                    'v_id'     => $v_id,
		                    'store_id'  => $store_id,
		                    'trans_from' => 'VENDOR_PANEL',
		                    'c_id'   =>  '',
		                    'api_token'=>'',
		                    'vu_id'=>'',
		                    'stockData'=>$grtOutPost,
	                    	]);
                    		$StockController->stockOut($request);
                		}
                		
                	}
                	if(empty($val['is_serial']) && empty($val['is_batch']) ){

                		GrtDetail::create($grtDetail);
                		$request = new \Illuminate\Http\Request();
                		$request->merge([
	                    'v_id'     => $v_id,
	                    'store_id'  => $store_id,
	                    'trans_from' => 'VENDOR_PANEL',
	                    'c_id'   =>  '',
	                    'api_token'=>'',
	                    'vu_id'=>'',
	                    'stockData'=>$grtOutPost,
                    	]);
                		$StockController->stockOut($request);

                	}


		        }
		        $response = [ 'status' => 'success' , 'message' => 'Ad-hoc Grt Created Successfully'   ];
		        
	        	$asyn->status = 'SUCCESS';
	        	$asyn->response = json_encode($response);
	        	$asyn->save();

		        return response()->json( $response , 200);

	    	}catch( \Exception $e ) {
		    	Log::error($e);
		    	return response()->json([ 'status' => 'fail' , 'message' => 'Server Error'   ] , 500);
		    }

    	}

    }

    private function generateDocNo($store_id) 
    {
         $store = Store::select('short_code')      
                                ->where('store_id',$store_id)
                                ->first();
        $c_date =date('dmy');
              
         $number =  'GRT'.$store->short_code.$c_date.$this->docIncrementNo($store_id);

         return $number;
    }


    private function docIncrementNo($store_id){
       	$inc_no = '0001';
     	$currentdate = date('Y-m-d');
     	$lastTranscation  = GrtHeader::where('src_store_id',$store_id)
                                         ->orderBy('id','DESC')
                                         ->first();                             
	    if(!empty($lastTranscation) && $lastTranscation->created_at->format('Y-m-d')==$currentdate)
	    {
	          $n  = strlen($inc_no);
	          $current_id = substr($lastTranscation->grt_no,-$n);
	          $inc=++$current_id;
	          $inc_no =str_pad($inc,$n,"0",STR_PAD_LEFT);
	    }else{
	     $inc_no = '0001';
	    }
     	return $inc_no;
    }

    //check item for batch
    public function getItemAvailableBatches($parmas){

		$v_id 	  =  $parmas['v_id'];
		$vu_id 	  =  $parmas['vu_id'];
		$barcode  =  $parmas['barcode'];
		$sku      =  !empty($parmas['sku'])?$parmas['sku']:'';
		$store_id =  $parmas['store_id'];
		$batch_id =  $parmas['batch_id'];
		$return_qty =  $parmas['return_qty'];
		$settings_for   = 'grt';
		$negativeInventoryAllow = false;
		$stock_point_id = !empty($parmas['stock_point_id'])?$parmas['stock_point_id']:'0';
		$negativeInventoryAllow = getSettingsForInventory($store_id,$vu_id,$v_id,$settings_for);

		$batchDetails = [];

		$batchData = StockPointSummary::join('batch','batch.id','stock_point_summary.batch_id')->where(['stock_point_summary.barcode'=>$barcode,'stock_point_summary.v_id'=>$v_id,'stock_point_summary.store_id'=>$store_id])->where('batch_id',$batch_id);

		if(!empty($sku)){

			$batchData  = $batchData->where('variant_sku', $sku);
		}
		if($stock_point_id != '0'){

		  $batchData  = $batchData->where('stock_point_id', $stock_point_id);

		}
		if($negativeInventoryAllow == 'not_allowed'){
		 $batchData  = $batchData->where('qty','>','0');
		}
	  	$batchData = $batchData->orderBy('batch.id','desc')->get();

      foreach($batchData as $batch){
        //echo $batch->priceDetail->mrp;
        if(!empty($batch->valid_months)){
          $validty      = $batch->valid_months;
          $validtyDm    =  explode(' ', $validty);
          $validty_no   = $validtyDm[0];
          $validty_type = $validtyDm[1];
        }else{
           $validty      = 'N/A';
           $validty_no   = '';
           $validty_type = '';
        }
        //$validty        = !empty($batch->valid_months)?$batch->valid_months:'N/A';
        
        $batchDetails[] = array('id'=>$batch->id,'batch_no'=>$batch->batch_no,'code'=>$batch->batch_code,'mfg_date'=>$batch->mfg_date,'exp_date'=>$batch->exp_date,'validty'=>$validty,'validty_no'=>$validty_no,'validty_type'=>$validty_type,'type'=>'batch','mrp'=>$batch->batch->priceDetail->mrp,'qty'=>$return_qty);

      } 
	  return $batchDetails;
	}

	public function getItemAvailableSerial($parmas){

		$v_id 	  =  $parmas['v_id'];
		$vu_id 	  =  $parmas['vu_id'];
		$barcode  =  $parmas['barcode'];
		$sku      =  !empty($parmas['sku'])?$parmas['sku']:'';
		$store_id =  $parmas['store_id'];
		$serial_id =  $parmas['serial_id'];
		$settings_for   = !empty($parmas['type'])?$parmas['type']:'grt';
		$negativeInventoryAllow = false;
		$stock_point_id = !empty($parmas['stock_point_id'])?$parmas['stock_point_id']:'0';
		//$negativeInventoryAllow = getSettingsForInventory($store_id,$vu_id,$v_id,$settings_for);
		$serials   = [];
		$serialData = StockPointSummary::where(['barcode'=>$barcode,'v_id'=>$v_id,'store_id'=>$store_id])->where('serial_id',$serial_id)->where('qty','>','0');
		if(!empty($sku)){
			$serialData  = $serialData->where('variant_sku', $sku);
		}
		if($stock_point_id != '0'){
		  $serialData  = $serialData->where('stock_point_id', $stock_point_id);
		}
	  	$serialData = $serialData->orderBy('id','desc')->get();
//dd($serialData);
		foreach ($serialData as $gdata) {
		//print_r($gdata->serialNumbers);die;
			if(!empty($gdata->serials)){
				foreach ($gdata->serials as $serialD) {
					$serials[]  = array('id'=>$serialD->id,'serial_no'=>$serialD->serial_no,'code'=>$serialD->serial_code,'type'=>'serial','mrp'=>$serialD->price->mrp);
				} 
			}
		}
		return $serials;

	}

		public function get_print_recipt($c_id,$v_id , $store_id, $invoice_id){


		JobdynamicConnection($v_id);
		$invoice_id  =  $invoice_id;
		$grtHeader   = GrtHeader::where('grt_no',$invoice_id)->where('v_id',$v_id)->first();
		if(!empty($grtHeader)){

            $product_data= [];
            $gst_list    = [];
            $final_gst   = [];
            $detatch_gst = [];
            $rounded     = 0;
            $taxableAmount = 0;
            $data    = '';

            $invoice_title = 'Good Return Invoice';
            $style = "<style>hr{background-color: #000;} .bold{font-weight: bold;} body{font-family: glacial_indifferenceregular; font-size: 14px;} .head-p.invoice{font-size: 24px; padding-top: 0px !important;} 
            .mapcha table thead tr th{border-left: none; padding: 10px 10px;} .head-p{ padding-top: 15px; padding-bottom: 15px;} .mapcha table tbody tr td pre{min-height: 20px; font-size: 14px !important; font-family: glacial_indifferenceregular;} .pr-3{padding-right:10px !important;} .terms-spacing{padding-bottom:
                10px;} .spacing{padding: 2px 0px;} *{padding:0;margin:0;box-sizing:border-box;-webkit-border-vertical-spacing:0;-webkit-border-horizontal-spacing:0;font-size:11px}.print_receipt_invoice thead tr th{border-right:1px #000 solid; white-space: nowrap; color: #000; border-bottom:1px #000 solid;border-top:1px #000 solid;padding: 5px;}.print_receipt_invoice thead tr:last-child{border-right:none}.print_receipt_invoice tbody tr td{border-right:1px #000 solid; padding: 0px;}.print_receipt_invoice tbody tr td pre{border-bottom: 1px #000 solid; min-height:20px;text-align:left;white-space:normal;word-wrap:break-word; font-size: 11px;overflow:hidden;line-height: 19px; padding: 0px 5px;}.print_receipt_invoice tbody tr td:last-child{border-right:none}.print_receipt_top-head tr td{padding:2px}.print_invoice_terms td table{text-align: left;}.print_invoice_last td table td{text-align: left;}.print_store_sign td:nth-child(2){text-align: right;}.print_invoice_last td table:last-child{margin-top: 40px;}.print_invoice_table_start table tbody tr td{font-size:13px;}.print_invoice_terms td{ border-left: none;}.mapcha table thead tr th:last-child{border-right: none;}</style>";


            $printArray  = array();
            $store       = Store::find($store_id);

            /*$einvoice = EinvoiceDetails::where('invoice_id',$order_details->invoice_id)->where('status','Success')->first();
            $qrImage = '';
            if($einvoice && !empty($einvoice->signed_qr_code)){
              
               $qrImage      = $this->generateQRCode(['content'=>$einvoice->signed_qr_code]);
                //$qrImage      = $einvoice->qrcode_image_path;
            }*/

	 

			$count_cart_product  = GrtDetail::where('grt_id', $grtHeader->id)->where('v_id', $grtHeader->v_id)->where('store_id', $grtHeader->src_store_id)->count();

			$startitem   = 0;
			$getItem     = 30;
			$countitem   = $count_cart_product;
			$totalpage   = ceil($count_cart_product/$getItem);
			$sr          = 1;

            
             // dd($grtHeader);
			$customer_address = '';
			if(isset($grtHeader->supplier->address->address_line_1)){
			 $customer_address .= $grtHeader->supplier->address->address_line_1;
			}
			if(isset($grtHeader->supplier->address->address_line_2)){
			 $customer_address .= $grtHeader->supplier->address->address_line_2;
			}

            $count       = 1;
            $gst_tax     = 0;
            $gst_listing = [];
            $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
            $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
            $cgst = $sgst = $cess = 0 ;
            // dd($final_gst);
 
            $bilLogo      = '';
            $bill_logo_id = 5;
            $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
            if($vendorImage)
            {
                $bilLogo = env('ADMIN_URL').$vendorImage->path;
            } 
             
            $cash_collected = 0;  
            $cash_return    = 0;
            $net_payable    = $grtHeader->total;

            $total_discount = (float)$grtHeader->discount_amount;
            
            $terms_conditions =  array('THANK YOU!');
               // dd($order_details);
            ########################
            ####### Print Start ####
            ########################

//           $data . = '<htm><body>';
            // dd($grtHeader);
         $data  .= '<table width="90%" style="margin-bottom: auto; margin-left: auto; margin-right: auto;">';
          $data  .= '<tr><td width="100%"><table width="100%" style="text-align: center; font-size: 12px; position: fixed; left: 50%; transform: translateX(-50%); top: 0px; background-color: #fff; paddig: 10px;"><tr><td>Tax Invoice</td></tr></table></td></tr>';
          $data  .= '<tr><td width="100%"><hr></td></tr>';
            $data  .= '<tr><td width="100%" style="padding: 40px 0 120px 0;">
                            <table width="100%"><tr style="vertical-align: top;"><td class="head-p" width="35%"><img src="'.$bilLogo.'" alt="" height="80px" style="margin-bottom: 8px;"><br><span style="font-size: 11px;">Date: '.date('d-m-Y', strtotime($grtHeader->created_at)).'</span></td>
                            <td width="65%">
                            <table width="100%" align="left" style="color: #000;" >';
            $data  .=  '<tr><td class="spacing "><b style="font-size: 12p;">GOA</b></td></tr>';
            $data  .=  '<tr><td  style="font-size: 11px;">'.$store->name.'</td></tr>';
            // if($store->address2){
            $data  .=  '<tr><td style="font-size: 11px;">'.$store->address1.'</td></tr>';
            if($store->address2){
                $data  .=  '<tr><td style="font-size: 11px;">'.$store->address2.'</td></tr>';
            }
            $data  .=  '<tr><td style="font-size: 11px;">'.$store->location.'-'.$store->pincode.'</td></tr>';
            $data  .=  '<tr><td style="font-size: 11px;">PH. No- '.$store->contact_number.'</td></tr>';
            $data  .=  '<tr><td style="font-size: 11px;">GSTIN: '.$store->gst.'</td></tr>';
             $data  .=  '<tr><td  style="font-size: 11px;"></td></tr>';
            $data  .=  '</table></td>
                        </tr></table>';
            $data .= '<table width="100%" style="background-color: #e0e0e0; text-align: center; border: 1px #000 solid; padding: 4px;">
                    <tr>
                        <td style="font-size: 11px;">No. : '.$grtHeader->grt_no.'</td>
                    </tr>
                </table>';  
            $data .='<table width="100%" style="padding: 8px 0;">
                    <tr>
                        <td style="width: 75%; border-right: 1px #000 solid;">
                            <table>
                                <tr>
                                    <td><b style="font-size: 11px;">Delivery Location:</b></td>
                                </tr>
                                <tr>
                                    <td><b style="font-size: 11px;">'.@$grtHeader->supplier->legal_name.'</b></td>
                                </tr>
                                <tr>
                                    <td style="font-size: 11px;">'.$customer_address.'</td>
                                </tr>
                                <tr>
                                    <td style="font-size: 11px;">Ph-022-42950768</td>
                                </tr>
                                <tr>
                                    <td style="font-size: 11px;">GSTIN No. : '.@$grtHeader->supplier->address->gstin.'</td>
                                </tr>
                            </table>
                        </td>
                        <td style="width: 25%;"></td>
                    </tr>
                </table>';

                for($i=0;$i < $totalpage ; $i++) {
                 $cart_product = GrtDetail::select('grt_details.item_name','grt_details.sku_code','grt_details.barcode','grt_details.tax_details','grt_details.v_id','grt_details.supply_price','grt_details.subtotal','grt_details.discount','grt_details.available_qty','grt_details.charge','grt_details.total','grt_details.qty','vendor_sku_flat_table.hsn_code')->leftjoin('vendor_sku_flat_table','vendor_sku_flat_table.item_id','grt_details.item_id')->where('grt_details.grt_id', $grtHeader->id)->where('grt_details.v_id', $grtHeader->v_id)->where('grt_details.store_id', $grtHeader->src_store_id)->groupBy('vendor_sku_flat_table.item_id')->skip($startitem)->take(30)->get();

             // $cart_product = GrtDetail::where('grt_id', $grtHeader->id)->where('v_id', $grtHeader->v_id)->where('store_id', $grtHeader->src_store_id)->take(8)->get();
             // dd($cart_product);
            $startitem  = $startitem+$getItem;
            $startitem  = $startitem;
            $itempagebreakcount = $countitem - 30;
            if($itempagebreakcount >= 30){
            	$data  .= '<table height="100%" cellspacing="0" width="100%" class="print_receipt_invoice" bgcolor="#fff" style="width: 100%; color: #000; border: 1px #000 solid; page-break-after: always;">';
            }elseif ($itempagebreakcount >= 0) {
            	$data  .= '<table height="100%" cellspacing="0" width="100%" class="print_receipt_invoice" bgcolor="#fff" style="width: 100%; color: #000; border: 1px #000 solid; page-break-after: always;">';
            }elseif ($itempagebreakcount <= 40) {
            	$data  .= '<table height="100%" cellspacing="0" width="100%" class="print_receipt_invoice" bgcolor="#fff" style="width: 100%; color: #000; border: 1px #000 solid; page-break-after: avoid;">';
            }

            $data  .= '<thead style="background-color: #e0e0e0;"><tr align="left">
                        <th width="2%" align="center" style="font-size: 11px; border-right:1px #000 solid; padding:4px; border-bottom:1px #000 solid">SNo.</th>
                        <th width="5%" align="left" style="font-size: 11px; border-bottom:1px #000 solid; padding:4px; border-right:1px #000 solid">Item #</th>
                        <th width="6%" align="left" style="font-size: 11px; border-bottom:1px #000 solid; padding:4px; border-right:1px #000 solid">HSN</th>
                        <th width="6%" align="left" style="font-size: 11px; white-space: nowrap; border-bottom:1px #000 solid; padding:4px; border-right:1px #000 solid">Product Des.</th>
                        <th width="4%" align="left" style="font-size: 11px; border-bottom:1px #000 solid; padding:4px;border-right:1px #000 solid">Color</th>
                        <th width="4%" align="center" style="font-size: 11px; border-bottom:1px #000 solid; padding:4px;border-right:1px #000 solid">Size</th>
                        <th width="4%" align="right" style="font-size: 11px; border-bottom:1px #000 solid; padding:4px;border-right:1px #000 solid">MRP</th>
                        <th width="4%" align="right" style="font-size: 11px; border-bottom:1px #000 solid; padding:4px;border-right:1px #000 solid">Qty.</th>
                        <th width="4%" align="right" style="font-size: 11px; border-bottom:1px #000 solid; padding:4px;border-right:1px #000 solid">Rate</th>
                        <th width="5%" align="right" style="font-size: 11px; border-bottom:1px #000 solid; padding:4px; white-space: nowrap">Total Amount</th></tr></thead><tbody>';
           
            $barcode = '';
            $hsn ='';
            $item_name ='';
            $qty  = '';
            $unit = '';
            $mrp  = '';
            $disc = '';
            $taxp = '';
            $taxb = '';

            $taxable_amount = 0;
            $total_csgt     = 0;
            $total_sgst     = 0;
            $total_cess     = 0;
            $total_igst     = 0;
            $total_qty      = 0;
            $total_discount  = 0;
            $total_amount   = 0;
            $total_inc_tax   = 0;
            $tax_amount    = 0;
            $total_taxable_amt = 0;
            $total_tax_amount  = 0;
            $srp            = '';
            $barcode        = '';
            $disc           = '';
            $hsn            = '';
            $item_name      = '';
            $qty            = '';
            $unit           = '';
            $mrp            = '';
            $TotalMrp           = '';
            $TotalAmt           = '';
            $tax_cgst           = '';
            $tax_sgst           = '';
            $color          = '';
            $size           = '';
            $tax_name       = '';
            $tax_igst       = '';  
            $taxable        = '';   
            $tax_cess       = '';  
            $taxamt         = '';
            $taxcgst        = '';
            $taxsgst        = '';
            $taxigst        = '';
            $taxcess        = '';
			$cgstper        = '';
			$sgstper        = '';
			$igstper        = '';
            $taxcessper        = '';
            $totalMrp       = 0;
            foreach ($cart_product as $key => $value) {
            	 
            	//$total_amount  = 0;
 
				$itemD  = VendorSku::where('sku_code',$value->sku_code)->first();
 
                // dd($value);
                $remark = isset($value->remark)?' -'.$value->remark:'';
                if(!empty($value->tax_details)){
					$tdata    = json_decode($value->tax_details);
					// $hsn_data    = $value->hsn_code;
					//print_r($tdata);die;
					}else{
					$params  = array('barcode' => $value->barcode, 'qty' => $value->qty, 's_price' => $value->total, 'hsn_code' => $itemD->hsn_code, 'store_id' => $value->src_store_id, 'v_id' => $value->v_id);
					//dd($params);
					$cartConfig  = new CloudPos\CartController;
					$tax_details = $cartConfig->taxCal($params);
					$tdata       = json_decode(json_encode($tax_details));
					// $hsn_data    = $value->hsn_code;
					}

                // dd($tdata->hsn_code);

				$cgstR   = isset($tdata->cgst)?$tdata->cgst:$tdata->cgst_rate;	
				$sgstR   = isset($tdata->sgst)?$tdata->sgst:$tdata->sgst_rate;
				$igstR   = isset($tdata->igst)?$tdata->igst:$tdata->igst_rate;
				$cesR    = isset($tdata->cess)?$tdata->cess:$tdata->cess_rate;
				$cgstAmt = isset($tdata->cgstamt)?$tdata->cgstamt:$tdata->cgst_amt;
				$sgstAmt = isset($tdata->sgstamt)?$tdata->sgstamt:$tdata->sgst_amt;
				$igstAmt = isset($tdata->igstamt)?$tdata->igstamt:$tdata->igst_amt;
				$cessAmt = isset($tdata->cessamt)?$tdata->cessamt:$tdata->cess_amt;
				$tdata->hsn = isset($tdata->hsn)?$tdata->hsn:'';

 
                $discount = $value->discount;
                $taxper         = $cgstR + $sgstR;
                $taxable_amount += $value->total;
                $total_csgt     += $cgstAmt;
                $total_sgst     += $sgstAmt;
                $total_cess     += $cessAmt;
                $total_discount += $discount;
                $totalmrp = $value->supply_price * $value->qty;
                // dd($value);
                $totalamt = $totalmrp;
                /*if($tdata->tax_type == 'INC'){
                    $totalamt = $totalmrp;
                }else{
                    $excgst = ($totalmrp - $discount) * $taxper/100;
                    $totalamt = $totalmrp + $excgst;
                }*/
                $total_amount += $value->total;
                $cgst = $cgstR;
                $sgst = $sgstR;
                // dd($total_amount);
                if($itemD->purchase_uom_type == 'PIECE'){
                	$total_qty  += $value->qty;
                }else{
                	$total_qty  +=1 ;
                }
                
                $product_variant = explode("-", $value->variant_combi);

                $totaltaxamt = $cgstAmt + $sgstAmt + $igstAmt + $cessAmt;
                $tax_amnt = $totalamt - ($totalamt - $totaltaxamt);
                $total_taxable_amt += $totalamt - $tax_amnt;
                    $total_tax_amount  += $tax_amnt;

                $gst_list[] = [
                    'name'              => $value->hsn_code,
                    'wihout_tax_price'  => $tdata->taxable,
                    'taxAmount'        =>  $tax_amnt,
                    'cgst'              => $cgstAmt,
                    'sgst'              => $sgstAmt,
                    'cess'              => $cessAmt,
                    'igst'              => $igstAmt,
                    'cessper'           => $cesR,
                    'cgstper'           => $cgstR,
                    'sgstper'           => $sgstR,
                    'igstper'           => $igstR,
                ];


                $itemName = substr($value->item_name, 0, 20);
                // $total_inc_tax = $total_amount + $cgst + $sgst; 
                $srp       .= '<pre style="text-align: center; padding: 4px; margin: 0px; font-size: 11px;">'.$sr.'</pre>';
                $barcode   .= '<pre style="text-align: left; padding: 4px; margin: 0px; font-size: 11px;">'.$value->barcode.'</pre>';
                $hsn       .= '<pre style="text-align: left;white-space: nowrap; padding: 4px; margin: 0px; font-size: 11px;">'.$value->hsn_code.'</pre>';
                $item_name .= '<pre style="text-align: left;white-space: nowrap; padding: 4px; margin: 0px; font-size: 11px;">'.$itemName.'</pre>';
                $qty       .= '<pre style="text-align: right; padding: 4px; margin: 0px; font-size: 11px;">'.$value->qty.'</pre>';
                $tempVarientColor = isset($value->va_color) ? $value->va_color : 'N/A';
                $color     .= '<pre style="text-align: left; padding: 4px; margin: 0px; font-size: 11px;">'.$tempVarientColor.'</pre>';
                $tempVarientSize = isset($value->va_size) ? $value->va_size : 'N/A';
                $size     .= '<pre style="text-align: center; padding: 4px; margin: 0px; font-size: 11px;">'.$tempVarientSize.'</pre>';
                $disc      .= '<pre style="text-align: center; padding: 4px; margin: 0px; font-size: 11px;">'.number_format($discount,2).'</pre>';
                $tax_cgst      .= '<pre style="text-align: center; padding: 4px; margin: 0px; font-size: 11px;">'.number_format($cgst,2).'</pre>';
                $tax_sgst      .= '<pre style="text-align: center; padding: 4px; margin: 0px; font-size: 11px;">'.number_format($sgst,2).'</pre>';
                $mrp       .= '<pre style="text-align: right; padding: 4px; margin: 0px; font-size: 11px;">'.number_format($value->supply_price,2).'</pre>';
                $TotalMrp      .= '<pre style="text-align: right; padding: 4px; margin: 0px; font-size: 11px;">'.number_format($totalmrp,2).'</pre>';
                $TotalAmt      .= '<pre style="text-align: right; padding: 4px; margin: 0px; font-size: 11px;">'.number_format($value->total,2).'</pre>';
                $taxb      .= '<pre style="text-align: center; padding: 4px; margin: 0px; font-size: 11px;">'.$tdata->taxable.'</pre>';
                $sr++;
            }
            // print_r($total_amount);
            // dd($barcode);
            // dd($tdata->cgstamt);
            $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
            $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
            $cgst = $sgst = $cess = $igst = $cessper = 0 ;
            foreach ($gst_listing as $key => $value) {
                $tax_ab = [];
                $tax_cg = [];
                $tax_sg = [];
                $tax_ig = [];
                $tax_ces = [];
                $tax_cesper = [];
                $tax_amt = [];
                foreach ($gst_list as $val) {

                    if ($val['name'] == $value) {
                        $total_gst      += str_replace(",", '', $val['taxAmount']);
                        $taxable_amount += str_replace(",", '', $val['wihout_tax_price']);
                        $tax_ab[]       =  str_replace(",", '', $val['wihout_tax_price']);
                        $tax_amt[]      =  str_replace(",", '', $val['taxAmount']);
                        $tax_cg[]       =  str_replace(",", '', $val['cgst']);
                        $tax_sg[]       =  str_replace(",", '', $val['sgst']);
                        $tax_ig[]       =  str_replace(",", '', $val['igst']);
                        $tax_ces[]      =  str_replace(",", '', $val['cess']);
                        $tax_cesper   =  str_replace(",", '', $val['cessper']);
                        $tax_cgstper   =  str_replace(",", '', $val['cgstper']);
                        $tax_sgstper   =  str_replace(",", '', $val['sgstper']);
                        $tax_igstper   =  str_replace(",", '', $val['igstper']);
                        $cgst           += str_replace(",", '', $val['cgst']);
                        $sgst           += str_replace(",", '', $val['sgst']);
                        $cess           += str_replace(",", '', $val['cess']);
                        $cessper        += str_replace(",", '', $val['cessper']);
             
                        $igst           += str_replace(",", '', @$val['igst']);

                        $final_gst[$value] = (object)[
                        'name'      => $value,
                        'taxable'   => array_sum($tax_ab),//$this->format_and_string($taxable_amount),
                        'tax_amt'   => array_sum($tax_amt),//$this->format_and_string($taxable_amount),
                        'cgst'      => round(array_sum($tax_cg),2),
                        'sgst'      => round(array_sum($tax_sg),2),
                        'igst'      => round(array_sum($tax_ig),2),
                        'cess'      => round(array_sum($tax_ces),2),
                        'cessper'   => $tax_cesper,
                        'cgstper'   => $tax_cgstper,
                        'sgstper'   => $tax_sgstper,
                        'igstper'   => $tax_igstper
                    ];
                }
            }
        }


        $total_csgt = round($cgst,2);
        $total_sgst = round($sgst,2);
        $total_cess = round($cess,2);
        $total_igst = round($igst,2);

        foreach ($final_gst as $key => $value) {
            $tax_details  = json_decode(json_encode($value),true);
            $taxable     .= '<p style="margin: 0px; padding: 4px;">'.$tax_details['taxable'].'</p>';
            $taxamt      .= '<p style="margin: 0px; padding: 4px;">'.$tax_details['tax_amt'].'</p>';
            $tax_name    .= '<p style="margin: 0px; padding: 4px;">'.$tax_details['name'].'</p>';
            $taxcgst     .= '<p style="margin: 0px; padding: 4px;">'.$tax_details['cgst'].'</p>';
            $taxsgst     .= '<p style="margin: 0px; padding: 4px;">'.$tax_details['sgst'].'</p>';
            $taxigst     .= '<p style="margin: 0px; padding: 4px;">'.$tax_details['igst'].'</p>';
            $taxcess     .= '<p style="margin: 0px; padding: 4px;">'.$tax_details['cess'].'</p>';
            $taxcessper  .= '<p style="margin: 0px; padding: 4px;">'.$tax_details['cessper'].'</p>';
            $cgstper     .= '<p style="margin: 0px; padding: 4px;">'.$tax_details['cgstper'].'</p>';
            $sgstper     .= '<p style="margin: 0px; padding: 4px;">'.$tax_details['sgstper'].'</p>';
            $igstper     .= '<p style="margin: 0px; padding: 4px;">'.$tax_details['igstper'].'</p>';
        }
        // dd($taxable);
        //echo 'heloo';die;
        $data   .= '<tr align="left">';

                $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid; padding: 0px;">'.$srp.'</td>';
                $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid; padding: 0px;">'.$barcode.'</td>';
                $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid; padding: 0px;">'.$hsn.'</td>';
                $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid; padding: 0px;">'.$item_name.'</td>';
                $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid; padding: 0px;">'.$color.'</td>';
                $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid; padding: 0px;">'.$size.'</td>';
                $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid; padding: 0px;">'.$mrp.'</td>';
                $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid; padding: 0px;">'.$qty.'</td>';
                $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid; padding: 0px;">'.$TotalMrp.'</td>';
                $data   .= '<td valign="top" class="mapcha" style="border-bottom: 1px #000 solid; padding: 0px;">'.$TotalAmt.'</td></tr>';

		$total_csgt = round($total_csgt,2);
		$total_sgst = round($total_sgst,2);
		$total_cess = round($total_cess,2);
		
        
        if($totalpage-1 == $i){

        
                $total_csgt       = 0;
                $total_sgst       = 0;
                $total_cess       = 0;
                $total_mrp        = 0;
                $total_igst       = 0;
                //$total_qty        = 0;
                $total_amount  = 0;
                $tax_amount    = 0;
                $total_discount   = 0;
                $grossQty   = 0;
                $grossTotal   = 0;
                // $total_taxable_amt = 0;
                // $total_tax_amount  = 0;
                $invoiceData  = GrtDetail::where('grt_id', $grtHeader->id)->where('v_id', $grtHeader->v_id)->where('store_id', $grtHeader->src_store_id)->get();
                foreach($invoiceData as $invdata){
                    
					if(!empty($invdata->tax_details)){
					$Ntdata    = json_decode($invdata->tax_details);
					}else{
					$params  = array('barcode' => $invdata->barcode, 'qty' => $invdata->qty, 's_price' => $invdata->total, 'hsn_code' => null, 'store_id' => $invdata->src_store_id, 'v_id' => $invdata->v_id);
					//dd($params);
					$cartConfig  = new CloudPos\CartController;
					$tax_details = $cartConfig->taxCal($params);
					$Ntdata       = json_decode(json_encode($tax_details));

					}
                    $itemD  = VendorSku::where('sku_code',$invdata->sku_code)->first();
                     
                    $discount = $invdata->discount;
                    $taxper   = @$Ntdata->cgst + @$Ntdata->sgst;
                    $taxable_amount += $Ntdata->taxable;
                    $taxableAmount += $Ntdata->taxable;
                    $total_csgt  += isset($Ntdata->cgstamt)?$Ntdata->cgstamt:$Ntdata->cgst_amt;
                    $total_sgst  += isset($Ntdata->sgstamt)?$Ntdata->sgstamt:$Ntdata->sgst_amt;
                    $total_igst  += isset($Ntdata->igstamt)?$Ntdata->igstamt:$Ntdata->igst_amt;
                    $total_cess  += isset($Ntdata->cessamt)?$Ntdata->cessamt:$Ntdata->cess_amt;
                    $total_discount += $discount;
                    $totalmrp = $invdata->supply_price * $invdata->qty;
                // print_r($totalmrp);
                    if(@$Ntdata->tax_type == 'INC'){
                        $totalamt = $totalmrp;
                    }else{
                        $excgst = ($totalmrp - $discount) * $taxper/100;
                        $totalamt = $totalmrp + $excgst;
                    }
                // if($Ntdata->tax_type == 'INC'){
                //     $total_inc_tax = $total_amount -($Ntdata->cgstamt + $Ntdata->sgstamt); 
                // }
                    $total_amount += $totalamt;
                    // $cgst = $tdata->cgst;
                    // $sgst = $tdata->sgst;
                // $tax_cgst = $Ntdata->cgstamt;
                // $tax_sgst = $Ntdata->sgstamt;
                // $tax_igst = $Ntdata->igstamt;
                   // $total_qty  += $invdata->qty;
                    $cess_percentage = @$Ntdata->cess;
                    $tax_cess = @$Ntdata->cessamt;
                    $taxname = @$Ntdata->tax_name;
                // print_r($invdata->unit_mrp);
                    $totaltaxamount = $total_csgt + $total_sgst + $total_igst + $total_cess;  
                    $total_inc_tax = $total_amount - $totaltaxamount; 
                    $tax_amount = $total_amount - $total_inc_tax;
                    if($itemD->purchase_uom_type == 'PIECE'){
						$grossQty  += $invdata->qty;
						}else{
						$grossQty  +=1 ;
						}
                    $grossTotal  += $invdata->total;


                    // $total_taxable_amt += $total_inc_tax;
                    // $total_tax_amount  += $tax_amount;
                }
            $data   .= '</tbody>
            <tfoot style="background-color: #e0e0e0;"><tr><td colspan="7" style="padding: 5px;"><b style="font-size: 11px;">Total</b></td><td colspan="1" style="padding: 5px; text-align: center;"><b style="font-size: 11px;">'.$grossQty.'</b></td><td colspan="2" style="padding: 5px; text-align: right;"><b style="font-size: 11px;">'.$grtHeader->total.'</b></td></tr></tfoot></table>';


            $data .= '<table width="100%" cellspacing="0"><tr style="vertical-align: top;">
                    <td width="60%" style="padding-top: 4px !important; padding: 0; height: auto;">
                    <table width="100%">
                    <tr>
                    <td style="font-size: 11px;">Remarks</td>';
                    $data .= '<td style="text-transform: capitalize; text-align:left; font-size: 11px;">As instructed by Mr. Dheeraj BhatiaO1 box</td>';
                    $data .= '</tr>
                    </table>
                    </td>
                    <td width="10%"></td>
                    <td width="30%" style="padding: 0px; ">
                        <table align="rights" cellspacing="0" width="100%"><tr><td align="left" width="50%"  style="font-size: 11px; padding: 4px 0px;">Total MRP Value</td><td align="right"  width="30%" style="border: 1px #000 solid; border-top: none; border-bottom: none; padding: 4px; font-size: 11px;">'.$grtHeader->subtotal.'</td></tr>
                        <tr><td align="left"  width="50%" style="font-size: 11px; padding: 4px 0px;">Total Qty. Transfer</td><td align="right" style="border: 1px #000 solid; border-bottom: none;  padding: 4px;font-size: 11px;" width="30%">'.$grossQty.'</td></tr>
                        <tr><td align="left"  width="50%" style="font-size: 11px; padding: 4px 0px;">Total Discount Value</td><td align="right" style="border: 1px #000 solid; border-bottom: none; padding: 4px;font-size: 11px;" width="30%">'.$total_discount.'</td></tr>
                        <tr><td align="left"  width="50%" style="font-size: 11px; padding: 4px 0px;">Total Transfer Value</td><td align="right" style="border: 1px #000 solid; padding: 4px;font-size: 11px; border-bottom: none;" width="30%">'.$grtHeader->total.'</td></tr>                 
                        </table>
                    </td>
            </tr></table>';



                     $data .= '
            <table width="100%" style="border: 1px #000 solid; background-color: #e0e0e0; padding: 4px;"  cellspacing="0">
            <tr>
                <td style="font-size: 11px;">GST SUMMARY</td>
            </tr>
                </table>
            ';
            $data .= '
            <table width="100%" style="border: 1px #000 solid; border-top: none; border-bottom: none;" class="print_receipt_invoice"  cellspacing="0">
            <thead>
            <tr style="background-color: #e0e0e0;">
                <th style="text-align: left; font-size: 13px;border-left: none; padding: 4px;">HSN Code</th>
                <th style="text-align: right; font-size: 13px; border-right:1px #000 solid; padding: 4px">Taxable Amt.</th>
                <th colspan="2" style="text-align: right; font-size: 13px; border-right:1px #000 solid; padding: 4px">Integrated GST</th>
                <th colspan="2" style="text-align: right; font-size: 13px; border-right:1px #000 solid; padding: 4px">Central GST</th>
                <th colspan="2" style="text-align: right; font-size: 13px; border-right:1px #000 solid; padding: 4px">State GST</th>
                <th colspan="2" style="text-align: right; font-size: 13px; padding: 4px">Cess</th>
            </tr>
            <tr style="background-color: #e0e0e0;">
                <th style="border-left: none; border-bottom:1px #000 solid; font-size: 11px;"></th>
                <th style="border-bottom:1px #000 solid; font-size: 11px; border-right:1px #000 solid;"></th>
                <th style="text-align: center;border-bottom:1px #000 solid; font-size: 11px; border-top:1px #000 solid; padding: 4px;">Rate</th>
                <th style="text-align: right; border-top:1px #000 solid; padding: 4px; border-bottom:1px #000 solid; font-size: 11px;border-right:1px #000 solid;">Amount</th>
                <th style="text-align: center; border-bottom:1px #000 solid; font-size: 11px;border-top:1px #000 solid; padding: 4px;">Rate</th>
                <th style="text-align: right; border-top:1px #000 solid; padding: 4px; border-bottom:1px #000 solid; font-size: 11px;border-right:1px #000 solid;">Amount</th>
                <th style="text-align: center;border-bottom:1px #000 solid; font-size: 11px; border-top:1px #000 solid; padding: 4px;">Rate</th>
                <th style="text-align: right;border-bottom:1px #000 solid; font-size: 11px; border-top:1px #000 solid; padding: 4px; border-right:1px #000 solid;">Amount</th>
                <th style="text-align: center;border-bottom:1px #000 solid; font-size: 11px; border-top:1px #000 solid; padding: 4px;">Rate</th>
                <th style="text-align: right;border-bottom:1px #000 solid; font-size: 11px; border-top:1px #000 solid; padding: 4px;">Amount</th>
    </tr>
            </thead>
            <tbody>';


			$data .= '<tr>
                    <td style="text-align: left;border-right: 1px #000 solid; font-size: 11px;">'.$tax_name.'</td>
                    <td style="text-align: right;border-right: 1px #000 solid; font-size: 11px;">'.$taxableAmount.'</td>

                    <td style="text-align: center; font-size: 11px;">'.$igstper.'</td>
                    <td style="text-align: right;border-right: 1px #000 solid; font-size: 11px;">'.$total_igst.'</td>

                    <td style="text-align: center; font-size: 11px;">'.$cgstper.'</td>
                    <td style="text-align: right;border-right: 1px #000 solid; font-size: 11px; font-size: 11px;">'.$taxcgst.'</td>


                    <td style="text-align: center;">'.$sgstper.'</td>
                    <td style="text-align: right;border-right: 1px #000 solid; font-size: 11px;">'.$taxsgst.'</td>

                    <td style="text-align: center; font-size: 11px;">'.$taxcess.'</td>
                    <td style="text-align: right; font-size: 11px;">'.$taxcessper.'</td>
                </tr>';



           $data .= '</tbody>
            <tfoot>
                <tr style="background-color: #e0e0e0;">
                    <td style="padding: 4px; border: 1px #000 solid; border-right: none; border-left: none;  font-size: 11px; text-align: left;"><b>Total</b></td>
                    <td style="padding: 4px; border: 1px #000 solid; border-left: none; text-align: right; font-size: 11px;">'.$taxableAmount.'</td>
                    <td style="padding: 4px; border: 1px #000 solid; border-right: none; border-left: none; font-size: 11px; text-align: right;">'.$igstper.'</td>
                    <td style="padding: 4px; border: 1px #000 solid; border-left: none; text-align: right; font-size: 11px;">'.$total_igst.'</td>

                    <td style="padding: 4px; border: 1px #000 solid; border-right: none; border-left: none; font-size: 11px; text-align: right;">'.$cgstper.'</td>
                    <td style="padding: 4px; border: 1px #000 solid; border-left: none; text-align: right; font-size: 11px;">'.$total_csgt.'</td>
                    <td style="padding: 4px; border: 1px #000 solid; border-right: none; border-left: none; font-size: 11px; text-align: right;">'.$sgstper.'</td>
                    <td style="padding: 4px; border: 1px #000 solid; border-left: none; text-align: right; font-size: 11px;">'.$total_sgst.'</td>
                    <td style="padding: 4px; border: 1px #000 solid; border-right: none;border-left: none; font-size: 11px; text-align: right;">'.$cessper.'</td>
                    <td style="padding: 4px; border: 1px #000 solid; border-right: none; border-left: none; font-size: 11px; text-align: right;">'.$total_cess.'</td>
                </tr>
            </tfoot>
                </table>';

       
   
           
           $data .= '<table width="100%" cellspacing="0" style="padding-top: 4px;"><tr><td style="width:50%; vertical-align: top; font-size: 11px;">Prepared by:Akassh Mattikop</td><td style="width:50%; text-align: center;"><table width="100%" cellspacing="0"><tr><td style="font-size: 11px; vertical-align: top;">For BIBA APPARELS PVT. LTD</td></tr><tr><td style="padding-top: 20px; font-size: 11px;">Authorised Signatory</td></tr></table></td></tr></table>';
        }
        $data    .= '<table width="80%" style="margin: 0 auto; position: fixed; bottom: 10px;><tr class="print_invoice_last">
        <td class="bold" style="text-align: center; padding-bottom: 8px; font-size: 9px;">Corporate Address: 13th Floor, Capital Cyber Scape,Sector-59, Golf Course Extension Road,Gurugram,Haryana-122102,INDIA <br>
Registered Address: 13th Floor, Capital Cyber Scape,Sector-59, Golf Course Extension Road,Gurugram,Haryana-122102,INDIA <br>
CIN : U74110HR2002PTC083029 | Phone :0124-5047000 | Email : info@bibaindia.com | Website:- www.biba.in
        </td></tr><tr><td class="bold" style="text-align: center; font-size: 9px;">This invoice covered under Policy No - OG-21-1113-1018-00000034 of Bajaj Allianz - General Insurance Company Ltd</td></tr></table>';
             
            if($totalpage > 1){
                $data .= '<br><hr>';
            }
             
        }

         $data .= '</body></html>';

       echo  $data;die;

        $return = array('status'=>'success','style'=>$style,'html'=>$data) ;
        return $return;
    

		}
	}//End of get_print_recipt

  

//use App\Model\Stock\StockTransferOrder;
//use App\Model\Stock\StockTransferOrderDetails;

	public function sst_print_reciept($c_id,$v_id , $store_id, $invoice_id){



		JobdynamicConnection($v_id);
		$invoice_id  =  $invoice_id;
		$sstHeader   = StockTransferOrder::where('sto_no',$invoice_id)->where('v_id',$v_id)->first();
		if(!empty($sstHeader)){

            $product_data= [];
            $gst_list    = [];
            $final_gst   = [];
            $detatch_gst = [];
            $rounded     = 0;
            $data    = '';

            $invoice_title = 'Good Return Invoice';
            $style = "<style>hr{background-color: #000;} .bold{font-weight: bold;} body{font-family: glacial_indifferenceregular; font-size: 14px;} .head-p.invoice{font-size: 24px; padding-top: 0px !important;} 
            .mapcha table thead tr th{border-left: none; padding: 10px 10px;} .head-p{ padding-top: 15px; padding-bottom: 15px;} .mapcha table tbody tr td pre{min-height: 20px; font-size: 14px !important; font-family: glacial_indifferenceregular;} .pr-3{padding-right:10px !important;} .terms-spacing{padding-bottom:
                10px;} .spacing{padding: 2px 0px;} *{padding:0;margin:0;box-sizing:border-box;-webkit-border-vertical-spacing:0;-webkit-border-horizontal-spacing:0;font-size:14px}.print_receipt_invoice thead tr th{border-right:1px #000 solid; white-space: nowrap; color: #000; border-bottom:1px #000 solid;border-top:1px #000 solid;padding: 5px;}.print_receipt_invoice thead tr:last-child{border-right:none}.print_receipt_invoice tbody tr td{border-right:1px #000 solid; padding: 0px;}.print_receipt_invoice tbody tr td pre{border-bottom: 1px #000 solid; min-height:20px;text-align:left;white-space:normal;word-wrap:break-word; font-size: 11px;overflow:hidden;line-height: 19px; padding: 0px 5px;}.print_receipt_invoice tbody tr td:last-child{border-right:none}.print_receipt_top-head tr td{padding:2px}.print_invoice_terms td table{text-align: left;}.print_invoice_last td table td{text-align: left;}.print_store_sign td:nth-child(2){text-align: right;}.print_invoice_last td table:last-child{margin-top: 40px;}.print_invoice_table_start table tbody tr td{font-size:13px;}.print_invoice_terms td{ border-left: none;}.mapcha table thead tr th:last-child{border-right: none;}</style>";



            $printArray  = array();
            $store       = Store::find($store_id);

            /*$einvoice = EinvoiceDetails::where('invoice_id',$order_details->invoice_id)->where('status','Success')->first();
            $qrImage = '';
            if($einvoice && !empty($einvoice->signed_qr_code)){
              
               $qrImage      = $this->generateQRCode(['content'=>$einvoice->signed_qr_code]);
                //$qrImage      = $einvoice->qrcode_image_path;
            }*/

	 

       $count_cart_product  = StockTransferOrderDetails::where('sto_trf_ord_id', $sstHeader->id)->where('v_id', $sstHeader->v_id)->where('store_id', $sstHeader->src_store_id)->count();

            $startitem   = 0;
            $getItem     = 8;
            $countitem   = $count_cart_product;
           $totalpage   = ceil($count_cart_product/$getItem);
            $sr          = 1;

            for($i=0;$i < $totalpage ; $i++) {
                
                //'vendor_sku_flat_table.va_color','vendor_sku_flat_table.va_size'
             $cart_product = StockTransferOrderDetails::select('stock_transfer_order_details.item_name','stock_transfer_order_details.sku_code','stock_transfer_order_details.barcode','stock_transfer_order_details.v_id','stock_transfer_order_details.supply_price','stock_transfer_order_details.subtotal','stock_transfer_order_details.discount','stock_transfer_order_details.tax','stock_transfer_order_details.tax_detail','stock_transfer_order_details.charge','stock_transfer_order_details.total','stock_transfer_order_details.qty','stock_transfer_order_details.created_at','vendor_sku_flat_table.hsn_code')->leftjoin('vendor_sku_flat_table','vendor_sku_flat_table.item_id','stock_transfer_order_details.item_id')->where('stock_transfer_order_details.sto_trf_ord_id', $sstHeader->id)->where('stock_transfer_order_details.v_id', $sstHeader->v_id)->where('stock_transfer_order_details.store_id', $sstHeader->src_store_id)->skip($startitem)->take(8)->get();

             // $cart_product = StockTransferOrderDetails::where('sto_trf_ord_id', $sstHeader->id)->where('v_id', $sstHeader->v_id)->where('store_id', $sstHeader->src_store_id)->take(8)->get();
// dd($cart_product);
            $startitem  = $startitem+$getItem;
            $startitem  = $startitem;
             
			$customer_address = '';
			if(isset($sstHeader->supplier->address->address_line_1)){
			 $customer_address .= $sstHeader->supplier->address->address_line_1;
			}
			if(isset($sstHeader->supplier->address->address_line_2)){
			 $customer_address .= $sstHeader->supplier->address->address_line_2;
			}

            $count       = 1;
            $gst_tax     = 0;
            $gst_listing = [];
            $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
            $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
            $cgst = $sgst = $cess = 0 ;
            // dd($final_gst);
 
            $bilLogo      = '';
            $bill_logo_id = 5;
            $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
            if($vendorImage)
            {
                $bilLogo = env('ADMIN_URL').$vendorImage->path;
            } 
            
            $cash_collected = 0;  
            $cash_return    = 0;
            $net_payable    = $sstHeader->total;

            $total_discount = (float)$sstHeader->discount_amount;
            
            $terms_conditions =  array('THANK YOU!');
               // dd($order_details);
            ########################
            ####### Print Start ####
            ########################

//           $data . = '<htm><body>';
            // dd($sstHeader);
         $data  .= '<table width="90%" style=" margin-top: 20px; margin-bottom: 0px; margin-left: auto; margin-right: auto;">';
         $data  .= '<table width="90%" style=" margin-top: 20px; margin-bottom: 0px; margin-left: auto; margin-right: auto;">';
         $data  .= '<tr><td width="100%" style="text-align: center; font-size: 21px; ">Tax Invoice</td></tr>';
         $data  .= '<tr><td width="100%"><hr></td></tr>';
         $data  .= '<tr><td width="100%">
                            <table width="100%"><tr style="vertical-align: top;"><td class="head-p" width="35%"><img src="'.$bilLogo.'" alt="" height="80px" style="margin-bottom: 15px;"><br><span style="border-top: 1px #000 solid; border-bottom: 1px #000 solid; padding: 3px; background-color: #e0e0e0;">Date: '.date('d-m-Y', strtotime($sstHeader->created_at)).'</span></td>
                            <td width="65%">
                            <table width="100%" align="left" style="color: #000;" >';
            $data  .=  '<tr><td class="spacing "><b>GOA</b></td></tr>';
            $data  .=  '<tr><td class="spacing ">'.$store->name.'</td></tr>';
            // if($store->address2){
            $data  .=  '<tr><td class="spacing ">'.$store->address1.'</td></tr>';
            if($store->address2){
                $data  .=  '<tr><td class="spacing ">'.$store->address2.'</td></tr>';
            }
            $data  .=  '<tr><td class="spacing ">'.$store->location.'-'.$store->pincode.'</td></tr>';
            $data  .=  '<tr><td class="spacing ">PH. No- '.$store->contact_number.'</td></tr>';
            $data  .=  '<tr><td class="spacing ">GSTIN: '.$store->gst.'</td></tr>';
            $data  .=  '<tr><td class="spacing bold"></td></tr>';
            $data  .=  '</table></td>
                        </tr></table>';
            $data .= '<table width="100%" style="background-color: #e0e0e0; text-align: center; border: 1px #000 solid; border-left: none; border-right: none;">
                    <tr>
                        <td>No. : '.$sstHeader->sto_no.'</td>
                    </tr>
                </table>';  
             $data .='<table width="100%" style="padding-bottom: 10px; line-height: 1.4;">
                    <tr>
                        <td style="width: 60%; border-right: 1px #000 solid;">
                            <table>
                                <tr>
                                    <td><b>Delivery Location:</b></td>
                                </tr>
                                <tr>
                                    <td><b>'.@$sstHeader->destination->name.'</b></td>
                                </tr>
                                <tr>
                                    <td>'.@$sstHeader->destination->address1.$sstHeader->destination->address2.'</td>
                                </tr>
                                 <tr>
                                    <td>'.@$sstHeader->destination->location.'</td>
                                </tr>
                                <tr>
                                    <td>'.@$sstHeader->destination->contact_number.'</td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom: 20px;">GSTIN No. : '.@$sstHeader->destination->gst.'</td>
                                </tr>
                                <tr>
                                    <td><b>Transporter Name:</b></td>
                                </tr>
                                <tr>
                                    <td><b>G.R.NO:</b></td>
                                </tr>
                                <tr>
                                    <td><b>Way Bill:</b></td>
                                </tr>
                                <tr>
                                    <td><b>CARRIER</b></td>
                                </tr>
                            </table>
                        </td>
                        <td style="width: 40%;"></td>
                    </tr>
                </table>';
                $data .= '<hr>';     
                $data .='<table width="100%">
                    <tr>
                        <td>e-Invoice Details :</td>
                    </tr>
                    <tr>
                        <td style="width: 60%">
                            <table>
                                <tr>
                                    <td>IRN :</td>
                                    <td>e83af9d7b653b4c4b3eac0521041e14560857dfac1f2078c80bb8ea1ad3d
                                    a6cf</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </table>
                        </td>
                        <td  style="width: 10%"></td>
                        <td style="width: 30%">
                            <table>
                                <tr>
                                    <td>Ack. No. :</td>
                                    <td>112010028606896</td>
                                </tr>
                                <tr>
                                    <td>Ack. Date Time :</td>
                                    <td>10-10-2020 06:41:00 PM</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>';   
            $data  .= '<table width="100%"><tr><td><div  style="overflow: hidden; border-bottom: 1px #000 solid; "  width="100%" ><table height="100%" width="100%" class="print_receipt_invoice" bgcolor="#fff" style="width: 100%; color: #000;border: 1px #000 solid;border-bottom: none;" cellspacing="0">';
            $data  .= '<thead style="background-color: #e0e0e0;"><tr align="left">
                        <th width="2%" align="center" class="bold" style="border-right:1px #000 solid; border-bottom:1px #000 solid">SNo.</th>
                        <th width="5%" align="left" class="bold" style="border-bottom:1px #000 solid;border-right:1px #000 solid">Item #</th>
                        <th width="6%" align="left" class="bold" style="border-bottom:1px #000 solid;border-right:1px #000 solid">HSN</th>
                        <th width="6%" align="left" class="bold" style="white-space: nowrap;border-bottom:1px #000 solid;border-right:1px #000 solid">Product Des.</th>
                        <th width="4%" align="left" class="bold" style="border-bottom:1px #000 solid;border-right:1px #000 solid">Color</th>
                        <th width="4%" align="center" class="bold" style="border-bottom:1px #000 solid;border-right:1px #000 solid">Size</th>
                        <th width="4%" align="right" class="bold" style="border-bottom:1px #000 solid;border-right:1px #000 solid">MRP</th>
                        <th width="4%" align="right" class="bold" style="border-bottom:1px #000 solid;border-right:1px #000 solid">Qty.</th>
                        <th width="4%" align="right" class="bold" style="border-bottom:1px #000 solid;border-right:1px #000 solid">Rate</th>
                        <th width="5%" align="right" class="bold" style="border-bottom:1px #000 solid;">Total Amount</th></tr></thead><tbody>';

           
            $barcode = '';
            $hsn ='';
            $item_name ='';
            $qty  = '';
            $unit = '';
            $mrp  = '';
            $disc = '';
            $taxp = '';
            $taxb = '';

            $taxable_amount = 0;
            $total_csgt     = 0;
            $total_sgst     = 0;
            $total_cess     = 0;
            $total_igst     = 0;
            $total_qty      = 0;
            $total_discount  = 0;
            $total_amount   = 0;
            $total_inc_tax   = 0;
            $tax_amount    = 0;
            $total_taxable_amt = 0;
            $total_tax_amount  = 0;
            $srp            = '';
            $barcode        = '';
            $disc           = '';
            $hsn            = '';
            $item_name      = '';
            $qty            = '';
            $unit           = '';
            $mrp            = '';
            $TotalMrp           = '';
            $TotalAmt           = '';
            $tax_cgst           = '';
            $tax_sgst           = '';
            $color          = '';
            $size           = '';
            $tax_name       = '';
            $tax_igst       = '';  
            $taxable        = '';   
            $tax_cess       = '';  
            $taxamt         = '';
            $taxcgst        = '';
            $taxsgst        = '';
            $taxigst        = '';
            $taxcess        = '';
            $taxcessper     = '';
			$cgstper     	= '';
			$sgstper     	= '';
			$igstper     	= '';
            foreach ($cart_product as $key => $value) {
                // dd($value);
                $remark = isset($value->remark)?' -'.$value->remark:'';
                $itemD  = VendorSku::where('sku_code',$value->sku_code)->first();


 				 
				/*$params  = array('barcode' => $value->barcode, 'qty' => $value->qty, 's_price' => $value->total, 'hsn_code' => $value->hsn_code, 'store_id' => $value->src_store_id, 'v_id' => $value->v_id);
				//dd($params);
				$cartConfig  = new CloudPos\CartController;
				$tax_detailss = $cartConfig->taxCal($params);
				$tax_details2       = json_decode(json_encode($tax_details));*/

				//print_r($value->tax_detail);

                if(!empty($value->tax_detail)){
					$tdata    = json_decode($value->tax_detail);

					//print_r($tdata);die;
					}else{
					$params  = array('barcode' => $value->barcode, 'qty' => $value->qty, 's_price' => $value->subtotal, 'hsn_code' =>  $value->hsn_code, 'store_id' => $value->src_store_id, 'v_id' => $value->v_id);
					//dd($params);
					$cartConfig  = new CloudPos\CartController;
					$tax_details = $cartConfig->taxCal($params);
					$tdata       = json_decode(json_encode($tax_details));
					}

               // dd($tdata);
                
				$cgstR   = isset($tdata->cgst)?$tdata->cgst:$tdata->cgst_rate;	
				$sgstR   = isset($tdata->sgst)?$tdata->sgst:$tdata->sgst_rate;
				$cesR   = isset($tdata->cess)?$tdata->cess:$tdata->cess_rate;
				$igstR   = isset($tdata->igst)?$tdata->igst:$tdata->igst_rate;
				$cgstAmt = isset($tdata->cgstamt)?$tdata->cgstamt:$tdata->cgst_amt;
				$sgstAmt = isset($tdata->sgstamt)?$tdata->sgstamt:$tdata->sgst_amt;
				$igstAmt = isset($tdata->igstamt)?$tdata->igstamt:$tdata->igst_amt;
				$cessAmt = isset($tdata->cessamt)?$tdata->cessamt:$tdata->cess_amt;
				$tdata->hsn = isset($tdata->hsn)?$tdata->hsn:'';

 
                $discount = $value->discount;
                $taxper         = $cgstR + $sgstR;
                $taxable_amount += $value->total;
                $total_csgt     += $cgstAmt;
                $total_sgst     += $sgstAmt;
                $total_cess     += $cessAmt;
                $total_discount += $discount;
                $totalmrp = $value->supply_price * $value->qty;
                // dd($value);
                $totalamt = $totalmrp;
                /*if($tdata->tax_type == 'INC'){
                    $totalamt = $totalmrp;
                }else{
                    $excgst = ($totalmrp - $discount) * $taxper/100;
                    $totalamt = $totalmrp + $excgst;
                }*/
                $total_amount += $totalamt;
                $cgst = $cgstR;
                $sgst = $sgstR;
                // dd($total_amount);
                 if($itemD->purchase_uom_type == 'PIECE'){
                	$total_qty  += $value->qty;
                }else{
                	$total_qty  +=1 ;
                }
                
                $product_variant = explode("-", $value->variant_combi);
                $totaltaxamt = $cgstAmt + $sgstAmt + $igstAmt + $cessAmt;
                $tax_amnt = $totalamt - ($totalamt - $totaltaxamt);
                $total_taxable_amt += $totalamt - $tax_amnt;
                    $total_tax_amount  += $tax_amnt;
                $gst_list[] = [
                    'name'              => $value->hsn_code,
                    'wihout_tax_price'  => $tdata->taxable,
                    'taxAmount'        =>  $tax_amnt,
                    'cgst'              => $cgstAmt,
                    'sgst'              => $sgstAmt,
                    'cess'              => $cessAmt,
                    'igst'              => $igstAmt,
                    'cessper'           => $cesR,
                    'cgstper'           => $cgstR,
                    'sgstper'           => $sgstR,
                    'igstper'           => $igstR,
                ];
                $itemName = substr($value->item_name, 0, 20);
                // $total_inc_tax = $total_amount + $cgst + $sgst; 
                $srp       .= '<pre style="text-align: center;">'.$sr.'</pre>';
                $barcode   .= '<pre style="text-align: left;">'.$value->barcode.'</pre>';
                $hsn       .= '<pre style="text-align: left;white-space: nowrap;">'.$value->hsn_code.'</pre>';
                $item_name .= '<pre style="text-align: left;white-space: nowrap;">'.$itemName.'</pre>';
                $qty       .= '<pre style="text-align: right;">'.$value->qty.'</pre>';
                $tempVarientColor = isset($value->va_color) ? $value->va_color : 'N/A';
                $color     .= '<pre style="text-align: left;">'.$tempVarientColor.'</pre>';
                $tempVarientSize = isset($value->va_size) ? $value->va_size : 'N/A';
                $size     .= '<pre style="text-align: center;">'.$tempVarientSize.'</pre>';
                $disc      .= '<pre style="text-align: center;">'.number_format($discount,2).'</pre>';
                $tax_cgst      .= '<pre style="text-align: center;">'.number_format($cgst,2).'</pre>';
                $tax_sgst      .= '<pre style="text-align: center;">'.number_format($sgst,2).'</pre>';
                $mrp       .= '<pre style="text-align: right;">'.number_format($value->supply_price,2).'</pre>';
                $TotalMrp      .= '<pre style="text-align: right;">'.number_format($totalmrp,2).'</pre>';
                $TotalAmt      .= '<pre style="text-align: right;">'.number_format($value->total,2).'</pre>';
                $taxb      .= '<pre style="text-align: center;">'.$tdata->taxable.'</pre>';
                $sr++;
            }
            // print_r($total_amount);
            // dd($barcode);
            // dd($tdata->cgstamt);
            $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
            $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
            $cgst = $sgst = $cess = $igst = $cessper = 0 ;
            foreach ($gst_listing as $key => $value) {
                $tax_ab = [];
                $tax_cg = [];
                $tax_sg = [];
                $tax_ig = [];
                $tax_ces = [];
                $tax_cesper = [];
                $tax_amt = [];
                foreach ($gst_list as $val) {

                    if ($val['name'] == $value) {
                        $total_gst      += str_replace(",", '', $val['taxAmount']);
                        $taxable_amount += str_replace(",", '', $val['wihout_tax_price']);
                        $tax_ab[]       =  str_replace(",", '', $val['wihout_tax_price']);
                        $tax_amt[]      =  str_replace(",", '', $val['taxAmount']);
                        $tax_cg[]       =  str_replace(",", '', $val['cgst']);
                        $tax_sg[]       =  str_replace(",", '', $val['sgst']);
                        $tax_ig[]       =  str_replace(",", '', $val['igst']);
                        $tax_ces[]      =  str_replace(",", '', $val['cess']);
                        $tax_cesper   =  str_replace(",", '', $val['cessper']);
                        $tax_cgstper  =  str_replace(",", '', $val['cgstper']);
                        $tax_sgstper  =  str_replace(",", '', $val['sgstper']);
                        $tax_igstper  =  str_replace(",", '', $val['igstper']);
                        $cgst           += str_replace(",", '', $val['cgst']);
                        $sgst           += str_replace(",", '', $val['sgst']);
                        $cess           += str_replace(",", '', $val['cess']);
                        $cessper        += str_replace(",", '', $val['cessper']);
                        $igst           += str_replace(",", '', @$val['igst']);

                        $final_gst[$value] = (object)[
                        'name'      => $value,
                        'taxable'   => array_sum($tax_ab),//$this->format_and_string($taxable_amount),
                        'tax_amt'   => array_sum($tax_amt),//$this->format_and_string($taxable_amount),
                        'cgst'      => round(array_sum($tax_cg),2),
                        'sgst'      => round(array_sum($tax_sg),2),
                        'igst'      => round(array_sum($tax_ig),2),
                        'cess'      => round(array_sum($tax_ces),2),
                        'cessper'   => $tax_cesper,
                        'cgstper'   => $tax_cgstper,
                        'sgstper'   => $tax_sgstper,
                        'igstper'   => $tax_igstper
                    ];
                }
            
                }
        }
        $total_csgt = round($cgst,2);
        $total_sgst = round($sgst,2);
        $total_cess = round($cess,2);
        $total_igst = round($igst,2);

        foreach ($final_gst as $key => $value) {
            $tax_details  = json_decode(json_encode($value),true);
            $taxable     .= '<p>'.$tax_details['taxable'].'</p>';
            $taxamt      .= '<p>'.$tax_details['tax_amt'].'</p>';
            $tax_name    .= '<p>'.$tax_details['name'].'</p>';
            $taxcgst     .= '<p>'.$tax_details['cgst'].'</p>';
            $taxsgst     .= '<p>'.$tax_details['sgst'].'</p>';
            $taxigst     .= '<p>'.$tax_details['igst'].'</p>';
            $taxcess     .= '<p>'.$tax_details['cess'].'</p>';
            $taxcessper  .= '<p>'.$tax_details['cessper'].'</p>';
            $cgstper     .= '<p>'.$tax_details['cgstper'].'</p>';
            $sgstper     .= '<p>'.$tax_details['sgstper'].'</p>';
            $igstper     .= '<p>'.$tax_details['igstper'].'</p>';
        }

        // dd($taxable);
        $data   .= '<tr align="left">';
        $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid;">'.$srp.'</td>';
        $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid;">'.$barcode.'</td>';
        $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid;">'.$hsn.'</td>';
        $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid;">'.$item_name.'</td>';
        $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid;">'.$color.'</td>';
        $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid;">'.$size.'</td>';
        $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid;">'.$mrp.'</td>';
        $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid;">'.$qty.'</td>';
        $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid;">'.$TotalMrp.'</td>';
        $data   .= '<td valign="top" class="mapcha" style="border-bottom: 1px #000 solid;">'.$TotalAmt.'</td></tr>';

		$total_csgt = round($total_csgt,2);
		$total_sgst = round($total_sgst,2);
		$total_cess = round($total_cess,2);

       
        if($totalpage-1 == $i){
                $total_csgt       = 0;
                $total_sgst       = 0;
                $total_cess       = 0;
                $total_mrp        = 0;
                $total_igst       = 0;
               // $total_qty        = 0;
                $total_amount  = 0;
                $tax_amount    = 0;
                $total_discount   = 0;
                $taxableAmount   = 0;
                 $grossQty   = 0;
                $grossTotal   = 0;
                // $total_taxable_amt = 0;
                // $total_tax_amount  = 0;
                $invoiceData  = StockTransferOrderDetails::where('sto_trf_ord_id', $sstHeader->id)->where('v_id', $sstHeader->v_id)->where('store_id', $sstHeader->src_store_id)->get();
                
                foreach($invoiceData as $invdata){

                	$itemDetail  = VendorSkuDetails::where('sku_code',$invdata->sku_code)->first();
                	$itemD  = VendorSku::where('sku_code',$invdata->sku_code)->first();
                    
					if(!empty($invdata->tax_detail)){
					$Ntdata    = json_decode($invdata->tax_detail);
					}else{
					$params  = array('barcode' => $invdata->barcode, 'qty' => $invdata->qty, 's_price' => $invdata->subtotal, 'hsn_code' => $itemDetail->hsn_code, 'store_id' => $invdata->src_store_id, 'v_id' => $invdata->v_id);
					//dd($params);
					$cartConfig  = new CloudPos\CartController;
					$tax_details = $cartConfig->taxCal($params);
					$Ntdata       = json_decode(json_encode($tax_details));
					}
                     //dd($Ntdata);
                     
                    $discount = $invdata->discount;
                    $taxper   = @$Ntdata->cgst + @$Ntdata->sgst;
                    $taxable_amount += $Ntdata->taxable;
                    $taxableAmount += $Ntdata->taxable;
                    $total_csgt  += $Ntdata->cgstamt;
                    $total_sgst  += $Ntdata->sgstamt;
                    $total_igst  += $Ntdata->igstamt;
                    $total_cess  += $Ntdata->cessamt;
                    $total_discount += $discount;
                    $totalmrp = $invdata->supply_price * $invdata->qty;
                // print_r($totalmrp);
                    if($Ntdata->tax_type == 'INC'){
                        $totalamt = $totalmrp;
                    }else{
                        $excgst = ($totalmrp - $discount) * $taxper/100;
                        $totalamt = $totalmrp + $excgst;
                    }
                // if($Ntdata->tax_type == 'INC'){
                //     $total_inc_tax = $total_amount -($Ntdata->cgstamt + $Ntdata->sgstamt); 
                // }
                    $total_amount += $totalamt;
                    // $cgst = $tdata->cgst;
                    // $sgst = $tdata->sgst;
                // $tax_cgst = $Ntdata->cgstamt;
                // $tax_sgst = $Ntdata->sgstamt;
                // $tax_igst = $Ntdata->igstamt;
                   // $total_qty  += $invdata->qty;
                    $cess_percentage = $Ntdata->cess;
                    $tax_cess = $Ntdata->cessamt;
                    $taxname = $Ntdata->tax_name;
                // print_r($invdata->unit_mrp);
                    $totaltaxamount = $total_csgt + $total_sgst + $total_igst + $total_cess;  
                    $total_inc_tax = $total_amount - $totaltaxamount; 
                    $tax_amount = $total_amount - $total_inc_tax;
                    // $total_taxable_amt += $total_inc_tax;
                    // $total_tax_amount  += $tax_amount;

						if($itemD->purchase_uom_type == 'PIECE'){
						$grossQty  += $invdata->qty;
						}else{
						$grossQty  +=1 ;
						}
                    //$grossQty    += $invdata->qty;
                    $grossTotal  += $invdata->total;
                }
              $data   .= '</tbody>
            <tfoot style="background-color: #e0e0e0;"><tr><td colspan="7" style="padding: 5px;"><b>Total</b></td><td colspan="1" style="padding: 5px; text-align: center;"><b>'.$grossQty.'</b></td><td colspan="2" style="padding: 5px; text-align: right;"><b>'.$sstHeader->total.'</b></td></tr></tfoot></table></td></tr></div></td></tr></table>';
            $data .= '<table width="100%" style="position: relative;"><tr style="vertical-align: top;">
                    <td width="35%" style="padding-top: 10px; height: 117px;">
                    <table width="100%">
                    <tr>
                    <td>Remarks</td>';
                    $data .= '<td style="text-transform: capitalize; text-align:right;">As instructed by Mr. Dheeraj BhatiaO1 box</td>';
                    $data .= '</tr>
                    <tr style="position: absolute;bottom: 10px; border: 1px #000 solid; border-left: none;  border-right: none;">
                    <td>GOA-GT1020-00010</td>';
                    $data .= '</tr>
                </table>
                    </td>
                    <td width="35%"></td>
                    <td width="30%" style="padding-top: 10px;">
                        <table align="rights" width="100%"><tr><td align="left" width="50%" class="terms-spacing bold">Total MRP Value</td><td align="right"  width="30%" style="border: 1px #000 solid; border-bottom: none;" class="terms-spacing pr-3 bold">'.$sstHeader->subtotal.'</td></tr>
                        <tr><td align="left" class="terms-spacing bold"  width="50%">Total Qty. Transfer</td><td align="right" style="border: 1px #000 solid; border-bottom: none;" class="terms-spacing bold pr-3"  width="30%">'.$grossQty.'</td></tr>
                        <tr><td align="left" class="terms-spacing bold"  width="50%">Total Discount Value</td><td align="right" style="border: 1px #000 solid; border-bottom: none;" class="terms-spacing bold pr-3"  width="30%">'.$total_discount.'</td></tr>
                        <tr><td align="left" class="terms-spacing bold"  width="50%">Total Transfer Value</td><td align="right" style="border: 1px #000 solid;" class="terms-spacing bold pr-3"  width="30%">'.$sstHeader->total.'</td></tr>                 
                        </table>
                    </td>
            </tr></table>';
              $data .= '
            <table width="100%" style="border: 1px #000 solid; border-left: none; border-right: none;"  cellspacing="0">
            <tr>
                <td style="padding: 5px; background-color: #e0e0e0;">GST SUMMARY</td>
            </tr>
                </table>
            ';
            $data .= '
            <table width="100%" style="border-left: none; border-right: none; border-bottom: none;" class="print_receipt_invoice"  cellspacing="0">
            <thead>
            <tr style="background-color: #e0e0e0;">
                <th style="text-align: left; font-size: 13px;border-left: none;">HSN Code</th>
                <th style="text-align: right; font-size: 13px; border-right:1px #000 solid;">Taxable Amt.</th>
                <th colspan="2" style="text-align: right; font-size: 13px; border-right:1px #000 solid;">Integrated GST</th>
                <th colspan="2" style="text-align: right; font-size: 13px; border-right:1px #000 solid;">Central GST</th>
                <th colspan="2" style="text-align: right; font-size: 13px; border-right:1px #000 solid;">State GST</th>
                <th colspan="2" style="text-align: right; font-size: 13px;">Cess</th>
            </tr>
            <tr style="background-color: #e0e0e0;">
                <th style="border-left: none; border-bottom:1px #000 solid;"></th>
                <th style="border-bottom:1px #000 solid; border-right:1px #000 solid;"></th>
                <th style="text-align: right;border-bottom:1px #000 solid; border-top:1px #000 solid;">Rate</th>
                <th style="text-align: right; border-top:1px #000 solid; border-bottom:1px #000 solid;border-right:1px #000 solid;">Amount</th>
                <th style="text-align: right; border-bottom:1px #000 solid;border-top:1px #000 solid;">Rate</th>
                <th style="text-align: right; border-top:1px #000 solid; border-bottom:1px #000 solid;border-right:1px #000 solid;">Amount</th>
                <th style="text-align: right;border-bottom:1px #000 solid; border-top:1px #000 solid;">Rate</th>
                <th style="text-align: right;border-bottom:1px #000 solid; border-top:1px #000 solid; border-right:1px #000 solid;">Amount</th>
                <th style="text-align: right;border-bottom:1px #000 solid; border-top:1px #000 solid;">Rate</th>
                <th style="text-align: right;border-bottom:1px #000 solid; border-top:1px #000 solid;">Amount</th>
    </tr>
            </thead>
            <tbody>';
                $data .= '<tr>
                     <td style="padding: 10px; text-align: left;border-right: 1px #000 solid;">'.$tax_name.'</td>
                    <td style="padding: 10px; text-align: right;border-right: 1px #000 solid;">'.$taxableAmount.'</td>

                    <td style="padding: 10px; text-align: right;">'.$igstper.'</td>
                    <td style="padding: 10px; text-align: right;border-right: 1px #000 solid;">'.$total_igst.'</td>

                    <td style="padding: 10px; text-align: right;">'.$cgstper.'</td>
                    <td style="padding: 10px; text-align: right;border-right: 1px #000 solid;">'.$taxcgst.'</td>


                    <td style="padding: 10px; text-align: right;">'.$sgstper.'</td>
                    <td style="padding: 10px; text-align: right;border-right: 1px #000 solid;">'.$taxsgst.'</td>

                    <td style="padding: 10px; text-align: right;">'.$taxcess.'</td>
                    <td style="padding: 10px; text-align: right;">'.$taxcessper.'</td>
                </tr>
            </tbody>



            


            
            <tfoot>
                <tr style="background-color: #e0e0e0;">
                    <td style="padding: 10px; border: 1px #000 solid; border-right: none; border-left: none; text-align: left;"><b>Total</b></td>
                    <td style="padding: 10px; border: 1px #000 solid; border-left: none; text-align: right;">'.$taxableAmount.'</td>


                    <td style="padding: 10px; border: 1px #000 solid; border-right: none; border-left: none; text-align: right;">'.$igstper.'</td>
                    <td style="padding: 10px; border: 1px #000 solid; border-left: none; text-align: right;">'.$total_igst.'</td>

                    <td style="padding: 10px; border: 1px #000 solid; border-right: none; border-left: none; text-align: right;">'.$cgstper.'</td>
                    <td style="padding: 10px; border: 1px #000 solid; border-left: none; text-align: right;">'.$total_csgt.'</td>
                    <td style="padding: 10px; border: 1px #000 solid; border-right: none; border-left: none; text-align: right;">'.$sgstper.'</td>
                    <td style="padding: 10px; border: 1px #000 solid; border-left: none; text-align: right;">'.$total_sgst.'</td>
                    <td style="padding: 10px; border: 1px #000 solid; border-right: none;border-left: none; text-align: right;">'.$cessper.'</td>
                    <td style="padding: 10px; border: 1px #000 solid; border-right: none; border-left: none; text-align: right;">'.$total_cess.'</td>
                </tr>
            </tfoot>
                </table>
            ';
       }
          
           
           $data .= '<table width="100%"><tr><td style="width:50%"></td><td style="width:50%; text-align: center; line-height: 30px;">For BIBA APPARELS PVT. LTD<br><span>Authorised Signatory</span></td></tr><tr><td style="width:50%">Prepared by:
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Akassh Mattikop</td><td style="width:50%;"></td></tr></table>';
            $data    .= '<table width="60%" style="
    margin: 0 auto;
    padding: 10px;"><tr class="print_invoice_last">
            <td class="bold" style="text-align: center; padding-bottom: 12px; font-size: 12px;">Corporate Address: 13th Floor, Capital Cyber Scape,Sector-59, Golf Course Extension Road,Gurugram,Haryana-122102,INDIA
Registered Address: 13th Floor, Capital Cyber Scape,Sector-59, Golf Course Extension Road,Gurugram,Haryana-122102,INDIA
CIN : U74110HR2002PTC083029 | Phone :0124-5047000 | Email : info@bibaindia.com | Website:- www.biba.in
            </td></tr><tr><td class="bold" style="text-align: center; font-size: 12px;">This invoice covered under Policy No - OG-21-1113-1018-00000034 of Bajaj Allianz - General Insurance Company Ltd</td></tr></table>';
             
            if($totalpage > 1){
                $data .= '<br><hr>';
            }
             
        }

         $data .= '</body></html>';

       echo  $data;die;

        $return = array('status'=>'success','style'=>$style,'html'=>$data) ;
        return $return;
    

		}
	
	}//End of sst_print_reciept

	public function grn_print_recipt($c_id,$v_id , $store_id, $invoice_id){

		JobdynamicConnection($v_id);
		$invoice_id  =  $invoice_id;

		$grnHeader   = Grn::where('grn_no',$invoice_id)->where('v_id',$v_id)->first();
		if(!empty($grnHeader)){

            $product_data= [];
            $gst_list    = [];
            $final_gst   = [];
            $detatch_gst = [];
            $rounded     = 0;
            $taxableAmount = 0;
            $data    = '';

            $invoice_title = 'Good Return Invoice';
            $style = "<style>hr{background-color: #000;} .bold{font-weight: bold;} body{font-family: glacial_indifferenceregular; font-size: 14px;} .head-p.invoice{font-size: 24px; padding-top: 0px !important;} 
            .mapcha table thead tr th{border-left: none; padding: 10px 10px;} .head-p{ padding-top: 15px; padding-bottom: 15px;} .mapcha table tbody tr td pre{min-height: 20px; font-size: 14px !important; font-family: glacial_indifferenceregular;} .pr-3{padding-right:10px !important;} .terms-spacing{padding-bottom:
                10px;} .spacing{padding: 2px 0px;} *{padding:0;margin:0;box-sizing:border-box;-webkit-border-vertical-spacing:0;-webkit-border-horizontal-spacing:0;font-size:14px}.print_receipt_invoice thead tr th{border-right:1px #000 solid; white-space: nowrap; color: #000; border-bottom:1px #000 solid;border-top:1px #000 solid;padding: 5px;}.print_receipt_invoice thead tr:last-child{border-right:none}.print_receipt_invoice tbody tr td{border-right:1px #000 solid; padding: 0px;}.print_receipt_invoice tbody tr td pre{border-bottom: 1px #000 solid; min-height:20px;text-align:left;white-space:normal;word-wrap:break-word; font-size: 11px;overflow:hidden;line-height: 19px; padding: 0px 5px;}.print_receipt_invoice tbody tr td:last-child{border-right:none}.print_receipt_top-head tr td{padding:2px}.print_invoice_terms td table{text-align: left;}.print_invoice_last td table td{text-align: left;}.print_store_sign td:nth-child(2){text-align: right;}.print_invoice_last td table:last-child{margin-top: 40px;}.print_invoice_table_start table tbody tr td{font-size:13px;}.print_invoice_terms td{ border-left: none;}.mapcha table thead tr th:last-child{border-right: none;}</style>";

            $printArray  = array();
            $store       = Store::find($store_id);

           $count_cart_product  = GrnList::where('grn_id', $grnHeader->id)->where('v_id', $grnHeader->v_id)->where('store_id', $grnHeader->store_id)->count();

	       $startitem   = 0;
	       $getItem     = 8;
	       $countitem   = $count_cart_product;
           $totalpage   = ceil($count_cart_product/$getItem);
            $sr          = 1;

            for($i=0;$i < $totalpage ; $i++) {

            	//vendor_sku_flat_table.va_color','vendor_sku_flat_table.va_size
            	$cart_product = GrnList::select('grn_list.v_id','grn_list.store_id','grn_list.sku_code','grn_list.name','grn_list.request_qty','grn_list.qty','grn_list.short_qty','grn_list.excess_qty','grn_list.unit_mrp','grn_list.cost_price','grn_list.subtotal','grn_list.discount','grn_list.tax','grn_list.tax_details','grn_list.total','grn_list.damage_qty','grn_list.charges','vendor_sku_flat_table.hsn_code','vendor_sku_detail_barcodes.barcode')->leftjoin('vendor_sku_flat_table','vendor_sku_flat_table.sku_code','grn_list.sku_code')->leftjoin('vendor_sku_detail_barcodes','vendor_sku_detail_barcodes.item_id','vendor_sku_flat_table.item_id')->where('grn_list.grn_id', $grnHeader->id)->where('grn_list.v_id', $grnHeader->v_id)->where('grn_list.store_id', $grnHeader->store_id)->skip($startitem)->take(8)->get();

					$startitem  = $startitem+$getItem;
					$startitem  = $startitem;

                // $cart_product = GrnList::where('grn_id', $grnHeader->id)->where('v_id', $grnHeader->v_id)->where('store_id', $grnHeader->store_id)->take(8)->get();
                // dd($cart_product);
	            $customer_address = '';
				if(isset($grtHeader->supplier->address->address_line_1)){
				 $customer_address .= $grtHeader->supplier->address->address_line_1;
				}
				if(isset($grtHeader->supplier->address->address_line_2)){
				 $customer_address .= $grtHeader->supplier->address->address_line_2;
				}

				$bilLogo      = '';
	            $bill_logo_id = 5;
	            $vendorImage  = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status',1)->first();
	            if($vendorImage)
	            {
	                $bilLogo = env('ADMIN_URL').$vendorImage->path;
	            } 
	            
	            $cash_collected   = 0;  
	            $cash_return      = 0;
	            $net_payable      = $grnHeader->total;
	            $total_discount   = (float)$grnHeader->discount;
	            $terms_conditions =  array('THANK YOU!');
 				$data .= '<html><body>';
	            $data  .= '<table width="90%" style=" margin-top: 20px; margin-bottom: 0px; margin-left: auto; margin-right: auto;">';
          		$data  .= '<tr><td width="100%" style="text-align: center; font-size: 21px; ">Tax Invoice</td></tr>';
          		$data  .= '<tr><td width="100%"><hr></td></tr>';
            	$data  .= '<tr><td width="100%">
                            <table width="100%"><tr style="vertical-align: top;"><td class="head-p" width="35%"><img src="'.$bilLogo.'" alt="" height="80px" style="margin-bottom: 15px;"><br><span style="border-top: 1px #000 solid; border-bottom: 1px #000 solid; padding: 3px; background-color: #e0e0e0;">Date: '.date('d-m-Y', strtotime($grnHeader->created_at)).'</span></td>
                            <td width="65%">
                            <table width="100%" align="left" style="color: #000;" >';
            	$data  .=  '<tr><td class="spacing "><b>GOA</b></td></tr>';
            	$data  .=  '<tr><td class="spacing ">'.$store->name.'</td></tr>';
            	// if($store->address2){
            	$data  .=  '<tr><td class="spacing ">'.$store->address1.'</td></tr>';
            	if($store->address2){
                	$data  .=  '<tr><td class="spacing ">'.$store->address2.'</td></tr>';
            	}
            	$data  .=  '<tr><td class="spacing ">'.$store->location.'-'.$store->pincode.'</td></tr>';
            	$data  .=  '<tr><td class="spacing ">PH. No- '.$store->contact_number.'</td></tr>';
            	$data  .=  '<tr><td class="spacing ">GSTIN: '.$store->gst.'</td></tr>';
             	$data  .=  '<tr><td class="spacing bold"></td></tr>';
            	$data  .=  '</table></td>
                        </tr></table>';
            	$data .= '<table width="100%" style="background-color: #e0e0e0; text-align: center; border: 1px #000 solid; border-left: none; border-right: none;">
                    <tr>
                        <td>No. : '.$grnHeader->grn_no.'</td>
                    </tr>
                </table>';  
            	$data .='<table width="100%" style="padding-bottom: 10px; line-height: 1.4;">
                    <tr>
                        <td style="width: 60%; border-right: 1px #000 solid;">
                            <table>
                                <tr>
                                    <td><b>Delivery Location:</b></td>
                                </tr>
                                <tr>
                                    <td><b>'.@$grnHeader->supplier->legal_name.'</b></td>
                                </tr>
                                <tr>
                                    <td>'.$customer_address.'</td>
                                </tr>
                                <tr>
                                    <td>Ph-022-42950768</td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom: 20px;">GSTIN No. : '.@$grnHeader->supplier->address->gstin.'</td>
                                </tr>
                                <tr>
                                    <td><b>Transporter Name:</b></td>
                                </tr>
                                <tr>
                                    <td><b>G.R.NO:</b></td>
                                </tr>
                                <tr>
                                    <td><b>Way Bill:</b></td>
                                </tr>
                                <tr>
                                    <td><b>CARRIER</b></td>
                                </tr>
                            </table>
                        </td>
                        <td style="width: 40%;"></td>
                    </tr>
                </table>';
                $data .= '<hr>';     
                $data .='<table width="100%">
                    <tr>
                        <td>e-Invoice Details :</td>
                    </tr>
                    <tr>
                        <td style="width: 60%">
                            <table>
                                <tr>
                                    <td>IRN :</td>
                                    <td>e83af9d7b653b4c4b3eac0521041e14560857dfac1f2078c80bb8ea1ad3d
                                    a6cf</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </table>
                        </td>
                        <td  style="width: 10%"></td>
                        <td style="width: 30%">
                            <table>
                                <tr>
                                    <td>Ack. No. :</td>
                                    <td>112010028606896</td>
                                </tr>
                                <tr>
                                    <td>Ack. Date Time :</td>
                                    <td>10-10-2020 06:41:00 PM</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>';   
            	$data  .= '<table width="100%"><tr><td><div  style="overflow: hidden; border-bottom: 1px #000 solid; "  width="100%" ><table height="100%" width="100%" class="print_receipt_invoice" bgcolor="#fff" style="width: 100%; color: #000;border: 1px #000 solid;border-bottom: none;" cellspacing="0">';
            	$data  .= '<thead style="background-color: #e0e0e0;"><tr align="left">
                        <th width="2%" align="center" class="bold" style="border-right:1px #000 solid; border-bottom:1px #000 solid">SNo.</th>
                        <th width="5%" align="left" class="bold" style="border-bottom:1px #000 solid;border-right:1px #000 solid">Item #</th>
                        <th width="6%" align="left" class="bold" style="border-bottom:1px #000 solid;border-right:1px #000 solid">HSN</th>
                        <th width="6%" align="left" class="bold" style="white-space: nowrap;border-bottom:1px #000 solid;border-right:1px #000 solid">Product Des.</th>
                        <th width="4%" align="left" class="bold" style="border-bottom:1px #000 solid;border-right:1px #000 solid">Color</th>
                        <th width="4%" align="center" class="bold" style="border-bottom:1px #000 solid;border-right:1px #000 solid">Size</th>
                        <th width="4%" align="right" class="bold" style="border-bottom:1px #000 solid;border-right:1px #000 solid">MRP</th>
                        <th width="4%" align="right" class="bold" style="border-bottom:1px #000 solid;border-right:1px #000 solid">Qty.</th>
                        <th width="4%" align="right" class="bold" style="border-bottom:1px #000 solid;border-right:1px #000 solid">Rate</th>
                        <th width="5%" align="right" class="bold" style="border-bottom:1px #000 solid;">Total Amount</th></tr></thead><tbody>';

                        $barcode = '';
			            $hsn ='';
			            $item_name ='';
			            $qty  = '';
			            $unit = '';
			            $mrp  = '';
			            $disc = '';
			            $taxp = '';
			            $taxb = '';

			            $taxable_amount = 0;
			            $total_csgt     = 0;
			            $total_sgst     = 0;
			            $total_cess     = 0;
			            $total_igst     = 0;
			            $total_qty      = 0;
			            $total_discount  = 0;
			            $total_amount   = 0;
			            $total_inc_tax   = 0;
			            $tax_amount    = 0;
			            $total_taxable_amt = 0;
			            $total_tax_amount  = 0;
			            $srp            = '';
			            $barcode        = '';
			            $disc           = '';
			            $hsn            = '';
			            $item_name      = '';
			            $qty            = '';
			            $unit           = '';
			            $mrp            = '';
			            $TotalMrp           = '';
			            $TotalAmt           = '';
			            $tax_cgst           = '';
			            $tax_sgst           = '';
			            $color          = '';
			            $size           = '';
			            $tax_name       = '';
			            $tax_igst       = '';  
			            $taxable        = '';   
			            $tax_cess       = '';  
			            $taxamt         = '';
			            $taxcgst        = '';
			            $taxsgst        = '';
			            $taxigst        = '';
			            $taxcess        = '';
						$cgstper        = '';
						$sgstper        = '';
						$igstper        = '';
			            $taxcessper        = '';
			            $totalMrp       = 0;
            			foreach ($cart_product as $key => $value) {
            				// dd($value);
							$itemD  = VendorSku::where('sku_code',$value->sku_code)->first();
							// dd($itemD);
                			$remark = isset($value->remarks)?$value->remarks:'';
                			if(!empty($value->tax_details)){
								$tdata    = json_decode($value->tax_details);
							}else{
								$params  = array('barcode' => $value->barcode, 'qty' => $value->qty, 's_price' => $value->total, 'hsn_code' => $itemD->hsn_code, 'store_id' => $value->src_store_id, 'v_id' => $value->v_id);
								//dd($params);
								$cartConfig  = new CloudPos\CartController;
								$tax_details = $cartConfig->taxCal($params);
								$tdata       = json_decode(json_encode($tax_details));
								// $hsn_data    = $value->hsn_code;
							}
			                // dd($tdata->hsn_code);
							$cgstR   = isset($tdata->cgst)?$tdata->cgst:$tdata->cgst_rate;	
							$sgstR   = isset($tdata->sgst)?$tdata->sgst:$tdata->sgst_rate;
							$igstR   = isset($tdata->igst)?$tdata->igst:$tdata->igst_rate;
							$cesR   = isset($tdata->cess)?$tdata->cess:$tdata->cess_rate;
							$cgstAmt = isset($tdata->cgstamt)?$tdata->cgstamt:$tdata->cgst_amt;
							$sgstAmt = isset($tdata->sgstamt)?$tdata->sgstamt:$tdata->sgst_amt;
							$igstAmt = isset($tdata->igstamt)?$tdata->igstamt:$tdata->igst_amt;
							$cessAmt = isset($tdata->cessamt)?$tdata->cessamt:$tdata->cess_amt;
							$tdata->hsn = isset($tdata->hsn)?$tdata->hsn:'';

			                $discount = $value->discount;
			                $taxper         = $cgstR + $sgstR;
			                $taxable_amount += $value->total;
			                $total_csgt     += $cgstAmt;
			                $total_sgst     += $sgstAmt;
			                $total_cess     += $cessAmt;
			                $total_discount += $discount;
			                $totalmrp = $value->cost_price * $value->qty;
			                // dd($value);
			                $totalamt = $totalmrp;
			                $total_amount += $totalamt;
			                $cgst = $cgstR;
			                $sgst = $sgstR;
			                // dd($total_amount);
			                if($itemD->purchase_uom_type == 'PIECE'){
			                	$total_qty  += $value->qty;
			                }else{
			                	$total_qty  +=1 ;
			                }
			                
			                $product_variant = explode("-", $value->variant_combi);

			                $totaltaxamt = $cgstAmt + $sgstAmt + $igstAmt + $cessAmt;
			                $tax_amnt = $totalamt - ($totalamt - $totaltaxamt);
			                $total_taxable_amt += $totalamt - $tax_amnt;
			                    $total_tax_amount  += $tax_amnt;
			                $gst_list[] = [
			                    'name'              => $value->hsn_code,
			                    'wihout_tax_price'  => $tdata->taxable,
			                    'taxAmount'        =>  $tax_amnt,
			                    'cgst'              => $cgstAmt,
			                    'sgst'              => $sgstAmt,
			                    'cess'              => $cessAmt,
			                    'igst'              => $igstAmt,
			                    'cessper'           => $cesR,
			                    'cgstper'           => $cgstR,
			                    'sgstper'           => $sgstR,
			                    'igstper'           => $igstR,
			                ];

			                $itemName = substr($value->name, 0, 25);
			                // $total_inc_tax = $total_amount + $cgst + $sgst; 
			                $srp       .= '<pre style="text-align: center;">'.$sr.'</pre>';
			                $barcode   .= '<pre style="text-align: left;">'.$value->barcode.'</pre>';
			                $hsn       .= '<pre style="text-align: left;white-space: nowrap;">'.$value->hsn_code.'</pre>';
			                $item_name .= '<pre style="text-align: left;white-space: nowrap;">'.$itemName.'</pre>';
			                $qty       .= '<pre style="text-align: right;">'.$value->qty.'</pre>';
			                $tempVarientColor = isset($value->va_color) ? $value->va_color : 'N/A';
			                $color     .= '<pre style="text-align: left;">'.$tempVarientColor.'</pre>';
			                $tempVarientSize = isset($value->va_size) ? $value->va_size : 'N/A';
			                $size     .= '<pre style="text-align: center;">'.$tempVarientSize.'</pre>';
			                $disc      .= '<pre style="text-align: center;">'.number_format($discount,2).'</pre>';
			                $tax_cgst      .= '<pre style="text-align: center;">'.number_format($cgst,2).'</pre>';
			                $tax_sgst      .= '<pre style="text-align: center;">'.number_format($sgst,2).'</pre>';
			                $mrp       .= '<pre style="text-align: right;">'.number_format($value->cost_price,2).'</pre>';
			                $TotalMrp      .= '<pre style="text-align: right;">'.number_format($totalmrp,2).'</pre>';
			                $TotalAmt      .= '<pre style="text-align: right;">'.number_format($value->total,2).'</pre>';
			                $taxb      .= '<pre style="text-align: center;">'.$tdata->taxable.'</pre>';
			                $totalMrp += $value->

			                $sr++;
            			}
			            $gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
			            $total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = $total_cess = 0 ;
			            $cgst = $sgst = $cess = $igst = $cessper = 0 ;
			            foreach ($gst_listing as $key => $value) {
			                $tax_ab = [];
			                $tax_cg = [];
			                $tax_sg = [];
			                $tax_ig = [];
			                $tax_ces = [];
			                $tax_cesper = [];
			                $tax_amt = [];
			                foreach ($gst_list as $val) {

			                    if ($val['name'] == $value) {
			                        $total_gst      += str_replace(",", '', $val['taxAmount']);
			                        $taxable_amount += str_replace(",", '', $val['wihout_tax_price']);
			                        $tax_ab[]       =  str_replace(",", '', $val['wihout_tax_price']);
			                        $tax_amt[]      =  str_replace(",", '', $val['taxAmount']);
			                        $tax_cg[]       =  str_replace(",", '', $val['cgst']);
			                        $tax_sg[]       =  str_replace(",", '', $val['sgst']);
			                        $tax_ig[]       =  str_replace(",", '', $val['igst']);
			                        $tax_ces[]      =  str_replace(",", '', $val['cess']);
									$tax_cesper     =  str_replace(",", '', $val['cessper']);
									$tax_cgstper    =  str_replace(",", '', $val['cgstper']);
									$tax_sgstper    =  str_replace(",", '', $val['sgstper']);
									$tax_igstper    =  str_replace(",", '', $val['igstper']);
			                        $cgst           += str_replace(",", '', $val['cgst']);
			                        $sgst           += str_replace(",", '', $val['sgst']);
			                        $cess           += str_replace(",", '', $val['cess']);
			                        $cessper        += str_replace(",", '', $val['cessper']);
			             
			                        $igst           += str_replace(",", '', @$val['igst']);

			                        $final_gst[$value] = (object)[
			                        'name'      => $value,
			                        'taxable'   => array_sum($tax_ab),//$this->format_and_string($taxable_amount),
			                        'tax_amt'   => array_sum($tax_amt),//$this->format_and_string($taxable_amount),
			                        'cgst'      => round(array_sum($tax_cg),2),
			                        'sgst'      => round(array_sum($tax_sg),2),
			                        'igst'      => round(array_sum($tax_ig),2),
			                        'cess'      => round(array_sum($tax_ces),2),
									'cessper'   => $tax_cesper,
									'cgstper'   => $tax_cgstper,
									'sgstper'   => $tax_sgstper,
									'igstper'   => $tax_igstper
			                    	];
			                	}
			            	}
			        	}
				        $total_csgt = round($cgst,2);
				        $total_sgst = round($sgst,2);
				        $total_cess = round($cess,2);
				        $total_igst = round($igst,2);

				        foreach ($final_gst as $key => $value) {
				            $tax_details  = json_decode(json_encode($value),true);
				            $taxable     .= '<p>'.$tax_details['taxable'].'</p>';
				            $taxamt      .= '<p>'.$tax_details['tax_amt'].'</p>';
				            $tax_name    .= '<p>'.$tax_details['name'].'</p>';
				            $taxcgst     .= '<p>'.$tax_details['cgst'].'</p>';
				            $taxsgst     .= '<p>'.$tax_details['sgst'].'</p>';
				            $taxigst     .= '<p>'.$tax_details['igst'].'</p>';
				            $taxcess     .= '<p>'.$tax_details['cess'].'</p>';
				            $taxcessper  .= '<p>'.$tax_details['cessper'].'</p>';
				            $cgstper     .= '<p>'.$tax_details['cgstper'].'</p>';
				            $sgstper     .= '<p>'.$tax_details['sgstper'].'</p>';
				            $igstper     .= '<p>'.$tax_details['igstper'].'</p>';
				        }
		        		$data   .= '<tr align="left">';

		                $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid;">'.$srp.'</td>';
		                $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid;">'.$barcode.'</td>';
		                $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid;">'.$hsn.'</td>';
		                $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid;">'.$item_name.'</td>';
		                $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid;">'.$color.'</td>';
		                $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid;">'.$size.'</td>';
		                $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid;">'.$mrp.'</td>';
		                $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid;">'.$qty.'</td>';
		                $data   .= '<td valign="top" class="mapcha" style="border-right: 1px #000 solid;border-bottom: 1px #000 solid;">'.$TotalMrp.'</td>';
		                $data   .= '<td valign="top" class="mapcha" style="border-bottom: 1px #000 solid;">'.$TotalAmt.'</td></tr>';

						$total_csgt = round($total_csgt,2);
						$total_sgst = round($total_sgst,2);
						$total_cess = round($total_cess,2);
				       

			         	if($totalpage-1 == $i){
			                $total_csgt       = 0;
			                $total_sgst       = 0;
			                $total_cess       = 0;
			                $total_mrp        = 0;
			                $total_igst       = 0;
			                $total_qty        = 0;
			                 $grossQty   = 0;
			                 $grossTotal  = 0;
			                $total_amount  = 0;
			                $tax_amount    = 0;
			                $total_discount   = 0;
			                // $total_taxable_amt = 0;
			                // $total_tax_amount  = 0;
			                $invoiceData  = GrnList::where('grn_id', $grnHeader->id)->where('v_id', $grnHeader->v_id)->where('store_id', $grnHeader->store_id)->get();
			                foreach($invoiceData as $invdata){

			                	$itemD  = VendorSku::where('sku_code',$invdata->sku_code)->first();
			                    
								if(!empty($invdata->tax_details)){
								$Ntdata    = json_decode($invdata->tax_details);
								}else{
								$params  = array('barcode' => $invdata->barcode, 'qty' => $invdata->qty, 's_price' => $invdata->total, 'hsn_code' => null, 'store_id' => $invdata->src_store_id, 'v_id' => $invdata->v_id);
								//dd($params);
								$cartConfig  = new CloudPos\CartController;
								$tax_details = $cartConfig->taxCal($params);
								$Ntdata       = json_decode(json_encode($tax_details));

								}
			                   
			                     
			                    $discount = $invdata->discount;
			                    $taxper   = @$Ntdata->cgst + @$Ntdata->sgst;
			                    $taxable_amount += $Ntdata->taxable;
			                    $taxableAmount += $Ntdata->taxable;
			                    $total_csgt  += isset($Ntdata->cgstamt)?$Ntdata->cgstamt:$Ntdata->cgst_amt;
			                    $total_sgst  += isset($Ntdata->sgstamt)?$Ntdata->sgstamt:$Ntdata->sgst_amt;
			                    $total_igst  += isset($Ntdata->igstamt)?$Ntdata->igstamt:$Ntdata->igst_amt;
			                    $total_cess  += isset($Ntdata->cessamt)?$Ntdata->cessamt:$Ntdata->cess_amt;
			                    $total_discount += $discount;
			                    $totalmrp = $invdata->supply_price * $invdata->qty;
			                // print_r($totalmrp);
			                    if(@$Ntdata->tax_type == 'INC'){
			                        $totalamt = $totalmrp;
			                    }else{
			                        $excgst = ($totalmrp - $discount) * $taxper/100;
			                        $totalamt = $totalmrp + $excgst;
			                    }
			                // if($Ntdata->tax_type == 'INC'){
			                //     $total_inc_tax = $total_amount -($Ntdata->cgstamt + $Ntdata->sgstamt); 
			                // }
			                    $total_amount += $totalamt;
			                    // $cgst = $tdata->cgst;
			                    // $sgst = $tdata->sgst;
			                // $tax_cgst = $Ntdata->cgstamt;
			                // $tax_sgst = $Ntdata->sgstamt;
			                // $tax_igst = $Ntdata->igstamt;
			                    $total_qty  += $invdata->qty;
			                    $cess_percentage = @$Ntdata->cess;
			                    $tax_cess = @$Ntdata->cessamt;
			                    $taxname = @$Ntdata->tax_name;
			                // print_r($invdata->unit_mrp);
			                    $totaltaxamount = $total_csgt + $total_sgst + $total_igst + $total_cess;  
			                    $total_inc_tax = $total_amount - $totaltaxamount; 
			                    $tax_amount = $total_amount - $total_inc_tax;

									if($itemD->purchase_uom_type == 'PIECE'){
									$grossQty  += $invdata->qty;
									}else{
									$grossQty  +=1 ;
									}
									$grossTotal  += $invdata->total;
			                    // $total_taxable_amt += $total_inc_tax;
			                    // $total_tax_amount  += $tax_amount;
			                }

			                 $data   .= '</tbody>
				            <tfoot style="background-color: #e0e0e0;"><tr><td colspan="7" style="padding: 5px;"><b>Total</b></td><td colspan="1" style="padding: 5px; text-align: center;"><b>'.$total_qty.'</b></td><td colspan="2" style="padding: 5px; text-align: right;"><b>'.$grnHeader->total.'</b></td></tr></tfoot></table></td></tr></div></td></tr></table>';

                			$data .= '<table width="100%" style="position: relative;"><tr style="vertical-align: top;">
                    			<td width="35%" style="padding-top: 10px; height: 117px;">
                    			<table width="100%">
		                    	<tr>
		                    	<td>Remarks</td>';
		                    $data .= '<td style="text-transform: capitalize; text-align:right;">'.$remark.'</td>';
		                    $data .= '</tr>
		                    	<tr style="position: absolute;bottom: 10px; border: 1px #000 solid; border-left: none;  border-right: none;">
		                    	<td>GOA-GT1020-00010</td>';
		                    $data .= '</tr>
                				</table>
                    			</td>
			                    <td width="35%"></td>
			                    <td width="30%" style="padding-top: 10px;">
			                        <table align="rights" width="100%"><tr><td align="left" width="50%" class="terms-spacing bold">Total MRP Value</td><td align="right"  width="30%" style="border: 1px #000 solid; border-bottom: none;" class="terms-spacing pr-3 bold">'.$grnHeader->subtotal.'</td></tr>
			                        <tr><td align="left" class="terms-spacing bold"  width="50%">Total Qty. Transfer</td><td align="right" style="border: 1px #000 solid; border-bottom: none;" class="terms-spacing bold pr-3"  width="30%">'.$total_qty.'</td></tr>
			                        <tr><td align="left" class="terms-spacing bold"  width="50%">Total Discount Value</td><td align="right" style="border: 1px #000 solid; border-bottom: none;" class="terms-spacing bold pr-3"  width="30%">'.$total_discount.'</td></tr>
			                        <tr><td align="left" class="terms-spacing bold"  width="50%">Total Transfer Value</td><td align="right" style="border: 1px #000 solid;" class="terms-spacing bold pr-3"  width="30%">'.$grnHeader->total.'</td></tr>                 
			                        </table>
			                    </td>
			            		</tr></table>';


			            		$data .= '
			            <table width="100%" style="border: 1px #000 solid; border-left: none; border-right: none;"  cellspacing="0">
			            <tr>
			                <td style="padding: 5px; background-color: #e0e0e0;">GST SUMMARY</td>
			            </tr>
			                </table>
			            ';
			            $data .= '
			            <table width="100%" style="border-left: none; border-right: none; border-bottom: none;" class="print_receipt_invoice"  cellspacing="0">
			            <thead>
				            <tr style="background-color: #e0e0e0;">
				                <th style="text-align: left; font-size: 13px;border-left: none;">HSN Code</th>
				                <th style="text-align: right; font-size: 13px; border-right:1px #000 solid;">Taxable Amt.</th>
				                <th colspan="2" style="text-align: right; font-size: 13px; border-right:1px #000 solid;">Integrated GST</th>
				                <th colspan="2" style="text-align: right; font-size: 13px; border-right:1px #000 solid;">Central GST</th>
				                <th colspan="2" style="text-align: right; font-size: 13px; border-right:1px #000 solid;">State GST</th>
				                <th colspan="2" style="text-align: right; font-size: 13px;">Cess</th>
				            </tr>
				            <tr style="background-color: #e0e0e0;">
				                <th style="border-left: none; border-bottom:1px #000 solid;"></th>
				                <th style="border-bottom:1px #000 solid; border-right:1px #000 solid;"></th>
				                <th style="text-align: right;border-bottom:1px #000 solid; border-top:1px #000 solid;">Rate</th>
				                <th style="text-align: right; border-top:1px #000 solid; border-bottom:1px #000 solid;border-right:1px #000 solid;">Amount</th>
				                <th style="text-align: right; border-bottom:1px #000 solid;border-top:1px #000 solid;">Rate</th>
				                <th style="text-align: right; border-top:1px #000 solid; border-bottom:1px #000 solid;border-right:1px #000 solid;">Amount</th>
				                <th style="text-align: right;border-bottom:1px #000 solid; border-top:1px #000 solid;">Rate</th>
				                <th style="text-align: right;border-bottom:1px #000 solid; border-top:1px #000 solid; border-right:1px #000 solid;">Amount</th>
				                <th style="text-align: right;border-bottom:1px #000 solid; border-top:1px #000 solid;">Rate</th>
				                <th style="text-align: right;border-bottom:1px #000 solid; border-top:1px #000 solid;">Amount</th>
					    		</tr>
				            </thead>
				            <tbody>';
				                $data .= '<tr>
				                    <td style="padding: 10px; text-align: left;border-right: 1px #000 solid;">'.$tax_name.'</td>
				                    <td style="padding: 10px; text-align: right;border-right: 1px #000 solid;">'.$taxable.'</td>

				                    <td style="padding: 10px; text-align: right;">'.$igstper.'</td>
				                    <td style="padding: 10px; text-align: right;border-right: 1px #000 solid;">'.$taxigst.'</td>

				                    <td style="padding: 10px; text-align: right;">'.$cgstper.'</td>
				                    <td style="padding: 10px; text-align: right;border-right: 1px #000 solid;">'.$taxcgst.'</td>


				                    <td style="padding: 10px; text-align: right;">'.$sgstper.'</td>
				                    <td style="padding: 10px; text-align: right;border-right: 1px #000 solid;">'.$taxsgst.'</td>

				                    <td style="padding: 10px; text-align: right;">'.$taxcess.'</td>
				                    <td style="padding: 10px; text-align: right;">'.$taxcessper.'</td>
				                </tr>
				            </tbody>
				            <tfoot>
				                <tr style="background-color: #e0e0e0;">
				                    <td style="padding: 10px; border: 1px #000 solid; border-right: none; border-left: none; text-align: left;"><b>Total</b></td>
				                    <td style="padding: 10px; border: 1px #000 solid; border-left: none; text-align: right;">'.$taxableAmount.'</td>


				                    <td style="padding: 10px; border: 1px #000 solid; border-right: none; border-left: none; text-align: right;">'.$igstper.'</td>
				                    <td style="padding: 10px; border: 1px #000 solid; border-left: none; text-align: right;">'.$total_igst.'</td>

				                    <td style="padding: 10px; border: 1px #000 solid; border-right: none; border-left: none; text-align: right;">'.$cgstper.'</td>
				                    <td style="padding: 10px; border: 1px #000 solid; border-left: none; text-align: right;">'.$total_csgt.'</td>
				                    <td style="padding: 10px; border: 1px #000 solid; border-right: none; border-left: none; text-align: right;">'.$sgstper.'</td>
				                    <td style="padding: 10px; border: 1px #000 solid; border-left: none; text-align: right;">'.$total_sgst.'</td>
				                    <td style="padding: 10px; border: 1px #000 solid; border-right: none;border-left: none; text-align: right;">'.$cessper.'</td>
				                    <td style="padding: 10px; border: 1px #000 solid; border-right: none; border-left: none; text-align: right;">'.$total_cess.'</td>
				                </tr>
				            </tfoot>
				        </table>';
		            	}
			            
           				$data .= '<table width="100%"><tr><td style="width:50%"></td><td style="width:50%; text-align: center; line-height: 30px;">For BIBA APPARELS PVT. LTD<br><span>Authorised Signatory</span></td></tr><tr><td style="width:50%">Prepared by:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'.@$grnHeader->user->first_name.' '.@$grnHeader->user->last_name.'</td><td style="width:50%;"></td></tr></table>';
            			$data    .= '<table width="60%" style="margin: 0 auto;padding: 10px;"><tr class="print_invoice_last"><td class="bold" style="text-align: center; padding-bottom: 12px; font-size: 12px;">Corporate Address: 13th Floor, Capital Cyber Scape,Sector-59, Golf Course Extension Road,Gurugram,Haryana-122102,INDIA Registered Address: 13th Floor, Capital Cyber Scape,Sector-59, Golf Course Extension Road,Gurugram,Haryana-122102,INDIA CIN : U74110HR2002PTC083029 | Phone :0124-5047000 | Email : info@bibaindia.com | Website:- www.biba.in
            				</td></tr><tr><td class="bold" style="text-align: center; font-size: 12px;">This invoice covered under Policy No - OG-21-1113-1018-00000034 of Bajaj Allianz - General Insurance Company Ltd</td></tr></table>';
             
            			if($totalpage > 1){
                			$data .= '<br><hr>';
            			}
			}
			$data .= '</body></html>';
			echo  $data;die;
			$return = array('status'=>'success','style'=>$style,'html'=>$data) ;
			return $return;
       	}
	
	}




}
