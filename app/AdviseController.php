<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Model\Grn\Advise;
use App\Model\Grn\AdviseList;
use App\Model\Grn\Grn;
use App\Model\Grn\GrnList;
use App\Store;
use App\Organisation;
use Validator;
use App\Supplier;
use App\Model\InboundApi;
use Log;
use DB;
//use Auth;

class AdviseController extends Controller
{
	public function __construct()
	{
		//$this->middleware('auth');
		 $this->middleware('auth', ['except' => ['adviceCreate']]);
	}

	public function getAdvices(Request $request){
		$store_id_get = $request->store_id;
		$destination_short_code = Store::select('short_code')->where('store_id',$store_id_get)->first()->short_code;
		$where    = array('v_id'=> $request->v_id);
		$sortBy = 'status';
		$partial = '';
		// filter 
		if($request->has('filter')) {
			$filterData = json_decode($request->filter);
			// dd($filterData);
			foreach ($filterData as  $value) {
				if($value->key == 'created_at') {
					if(!empty($value->start_date)) {
						$where[] = [ 'advice.created_at', '>=', $value->start_date ];
						$where[] = [ 'advice.created_at', '<=', $value->end_date ];
					}
				} else {
					if(!empty($value->value)) {
						if($value->key == 'sort') {
							$sortBy = $value->value;
						} else {
							if($value->value == 'PARTIAL'){
								$partial = 'PARTIAL';
							}
							if($value->value != 'All'){
								$where[] = [ 'advice.'.$value->key, $value->value ];
							}
						}
					}
				}
			}
		}

		 // dd($where);
		
		if($sortBy == 'supplier_name'){
			$sortBy = 'suppliers.name';
			$data  = Advise::join('suppliers','suppliers.id','advice.supplier_id')->where($where)->where(function($query) use($store_id_get){
				$query->where('store_id',$store_id_get);
				$query->orWhereNull('store_id');
				$query->orwhere('store_id',0);
			})->orderByRaw("CASE WHEN  status = 'PENDING' THEN 0 ELSE 1 END ASC")->orderBy($sortBy)->get();
		}else{
 			$data  = Advise::where($where)->Where(function($query) use($store_id_get){
				$query->where('store_id',$store_id_get);
				$query->orWhereNull('store_id');
				$query->orwhere('store_id',0);
			})
			->orderByRaw("CASE WHEN  status = 'PENDING' THEN 0 ELSE 1 END ASC")
			->orderByRaw("CASE WHEN  status = 'PARTIAL' THEN 0 ELSE 1 END ASC")
			->orderBy($sortBy)->latest()->get();
 		}

		
		// dd($data);
		$responseData = [];
		$filter = [];
		$supplierList = [];
		foreach ($data as $key => $value) {
			$responseData[] = (object)[ 
				'id'			=> $value->id,
				'advice_no'		=> $value->advice_no,
				'created_at' 	=> date('d-m-Y', strtotime($value->created_at)),
				'date' 			=> date('d F Y', strtotime($value->created_at)),
				'supplier_name'	=> isValueExists($value->supplier, 'name', 'str'),
				'origin_from'	=> $value->origin_from,
				'status'		=> $value->current_status,
				'grn'			=> (object)$value->grnlist->pluck('grn_no')->toArray()
			];
		}

		if($request->has('filter')) {
			if($partial == 'PARTIAL'){
				$responseData = collect($responseData)->sortByDesc('status');
				$responseData = $responseData->filter(function ($item, $key) {
						if($item->status == 'PARTIAL'){
							return $item;
						}else{
							
						}
				});


				$responseData =  $responseData->values()->all();
				$responseData = (array)$responseData;
			}
		}else{
			//->sortByDesc(['created_at','status'])
			$responseData = collect($responseData);
			$responseData =  $responseData->values()->all();
			$responseData = (array)$responseData;
		}

		 //print_r($responseData);die;


		// dd(array_unique($supplierList));
		$getFilterData = Advise::select('status','supplier_id')->where('v_id', $request->v_id)->get();
		// $supplierList = collect($supplierList)->unique()->values();
		foreach ($getFilterData as $key => $supp) {
			$supplierList[] = [ 'id' => isValueExists($supp->supplier, 'id', 'num'), 'name' => isValueExists($supp->supplier, 'name', 'str')];
		}
		 //dd($getFilterData);

		 
		
		$filter['status']   = $getFilterData->unique('status')->pluck('status');
		$filter['status'][] = 'All';
		 
	 
		
		$filter['supplier']  = collect($supplierList)->unique()->values();
		$filter['supplier'][]  = array('id'=>0,'name'=>'All');

		$filter['sort'] = [
			['key' => 'created_at', 'name' => 'Sort By Date'],
			['key' => 'supplier_name', 'name' => 'Sort By Supplier'],
			['key' => 'origin_from', 'name' => 'Sort By Origin']
		];
		$filter['filter_format'] = [
			[ 'key' => 'created_at', 'start_date' => '', 'end_date' => '' ],
			[ 'key'	=> 'supplier_id', 'value' => '' ],
			[ 'key'	=> 'status', 'value' => '' ],
			[ 'key'	=> 'sort', 'value' => '' ]
		];

		return response()->json(['status' => 'success', 'data' => $responseData, 'filter' => $filter ], 200);
	}

	public function adviceDetails(Request $request)
	{
		// $id = $request->id; // Advice_id
		// $v_id = $request->v_id; // Advice_id
		$advice = Advise::where('v_id', $request->v_id)->where('id', $request->id)->first();
		foreach ($advice->grnlist as $key => $value) {  
			$grnData[] = (object)[ 'grn_no' => $value->grn_no, 'date' => date('d F Y', strtotime($value->created_at)), 'received_qty' => $value->qty, 'damage_qty' => $value->damage_qty, 'lost_qty' => 0, 'remarks' => $value->remarks, 'id' => $value->id, 'short_qty' => $value->short_qty, 'excess_qty' => $value->excess_qty ];
		}
		$data = [ 'advice_no' => $advice->advice_no, 'date' => date('d F Y'), 'supplier' => $advice->supplier->name, 'origin_from' => $advice->origin_from, 'status' => $advice->current_status, 'grn_list' => $grnData ];
		return response()->json(['status' => 'success', 'data' => $data ], 200);

	}

	public function adviceList(Request $request){
		$advice_id 	= $request->advice_id; // Advice_id
		$v_id       = $request->v_id;
		$isConditon = false;
		$grnlistC   = [];

		if($request->has('trans_from')) {
			if($request->trans_from == 'CLOUD_TAB_WEB' ||  $request->trans_from == 'ANDROID_VENDOR') {
				$isConditon = true;
			} 
		} else {
			$isConditon = false;
		}

		if($isConditon) {
			// if($request->trans_from == 'CLOUD_TAB_WEB') {
			$where    	= array('id'=>$advice_id,'v_id'=>$request->v_id);
			$advice 	= Advise::where($where)->first();
			$data 		= $advice->advice_product_details;

			$adviceDetails = [ 'advice_no' => $advice->advice_no, 'date' => date('d F Y'), 'supplier' => @$advice->supplier->name, 'origin_from' => @$advice->origin_from, 'status' => $advice->current_status ];
			return response()->json(['status' => 'success', 'data' => $data, 'advice_details' => $adviceDetails ], 200);
			// }
		} else {
			$where    	= array('advice_id'=>$advice_id,'v_id'=>$request->v_id);
			$data 		= AdviseList::where($where)->orderBy('id','asc')->get();
			
			$grn        = Grn::where($where)->first();
			if($grn){
				$grndata    = GrnList::where('grn_id',$grn->id)->get();
			}else{
				$grndata  = array();
			}
			$newData    = [];
			
			foreach ($data as $key => $d) {
				$batch    = [];
				$serial   = [];
				//echo $d->item_no;die;
				$d->order_qty    = $d->qty;
				$d->received_qty ='';
				unset($d->qty);
				$where    	= array('advice_id'=>$advice_id,'item_no'=> $d->item_no);
				// $item_check	=  AdviseListrequest::where($where)->with(['Items'=>function($query){
				// 	$query->where('v_id',$->v_id);
				// }])->first();

				$item_check	=  AdviseList::where($where)->with(['Items'=> function($query) use($v_id) {
	                                    $query->where('v_id',$v_id);
	                            }])->first();

				$is_batch   = 0;
				$is_serial  = 0;
				$status     = '';
				if(!empty($item_check->Items) ){
					$is_batch = $item_check->Items->Item->has_batch;
					$is_serial= $item_check->Items->Item->has_serial;
					if($item_check->Items->Item->has_batch == 1){
						$status = "Batch";
					}if($item_check->Items->Item->has_serial == 1){
						$status = "Serial";
					}if($item_check->Items->Item->has_batch == 1 && $item_check->Items->Item->has_serial == 1){
						$status = "Batch/Serial";
					}	
				}
				if (strpos($status, 'Batch') !== false) {
					$batch = array('batch_no'=>'','mfg_date'=>'','exp_date'=>'','valid_months'=>'','qty'=>''); 
				}

				if (strpos($status, 'Serial') !== false) {
					$serial[] 	   = array('serial_no'=>''); 
				}
				
				$batch = (object)$batch;
				 

				$da = $d->toArray();
				$da['request_qty'] = $da['order_qty'];
				$newData[] = array_merge(['is_ok'=>false,'is_difference'=>false,'is_batch'=> $is_batch,'is_serial'=>$is_serial,'status'=>$status,'damage_qty' => '0' , 'lost_qty' => '0' , 'remarks' => '','batch'=>$batch,'serial'=>$serial,'barcode'=>$da['item_no'] ] ,$da);
	 			$grnlistC = [];
				if(count($grndata)>0)
				{
					//$grnlistC =$grndata;

					$grnlistC = $grndata->each(function($item, $key){
						$item->received_qty = $item->qty;
						$item->barcode      = $item->item_no;
						return $item;
					});
				}

			}

			return response()->json(['status' => 'success', 'data' => $newData ,'grnlist'=> (object)$grnlistC ], 200);

		}		
	}

	public function customValidation(){

	}

	public function adviceCreate(Request $request){

		if ($request->isJson()) {
			try {
		        $data = $request->json()->all();
		        //$data = collect($data);

		        /** @var \Illuminate\Contracts\Validation\Validator $validation */
		        $validator = Validator::make($data,[
		                'advice_no' => 'required',
		                'dest_site_code' => 'required',
		                'type' => 'required|in:PO,SST,STO,GRT',
		                'item_list' => 'array',
		                'advice_created_date' => 'date_format:Y-m-d',
		                'against_created_date' => 'date_format:Y-m-d',
		                'item_list.*.item_barcode' => 'required',
		                'item_list.*.qty' => 'required',
		            ]
		        );


		        $client = oauthUser($request);
		        $client_id = $client->client_id;
		        //This code is added when we are using client

		        // if client Id 1 then apply this 

		        
		 
		        $vendor = Organisation::select('id')->where('ref_vendor_code', $data['organisation_code'])->first();
		        if(!$vendor){

		        	$error_list =  [ 
		        		[ 'error_for' =>  'organisation_code' ,  'messages' => ['Unable to find This Organisation'] ] 
		        	]; 
		        	return response()->json([ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' =>  $error_list] , 422);
		        }

		        $v_id = $vendor->id;
		        $checkAdviceEntry = InboundApi::where([ 'v_id' => (int)$v_id, 'doc_no' => $data['advice_no'], 'api_name' => 'client/advice/create' ])->first();
		        if(empty($checkAdviceEntry)) {
		        	$asyn = new InboundApi;
			        $asyn->client_id = $client_id;
			        $asyn->v_id = $v_id;
			        $asyn->request = json_encode($data);
			        $asyn->job_class = '';
			        $asyn->api_name = 'client/advice/create';
			        $asyn->api_type = 'SYNC';
			        $asyn->ack_id = '';
			        $asyn->doc_no = $data['advice_no'];
			        $asyn->status = 'PENDING'; // PENDING|FAIL|SUCCESS
			        $asyn->save();
		        } else {
		        	$asyn = $checkAdviceEntry;
		        }

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
		        
		        $store = Store::select('store_id','short_code')->where('v_id', $v_id)->where('mapping_store_id', $data['dest_site_code'])->first();
		        if(!$store){

		        	$error_list =  [ 
		        		[ 'error_for' =>  'dest_site_code' , 'messages' => ['Unable to find This Store'] ]
		        	]; 

		        	$response = [ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' => $error_list  ];
		        	$asyn->status = 'FAIL';
		        	$asyn->response = json_encode($response);
		        	$asyn->save();

		        	return response()->json( $response, 422);
		        }

		        $store_id = $store->store_id;

		        if($client_id==1){
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

		        $no_of_packets = 0;

		        $client_advice_no = $data['advice_no'];

		        $client_advice_id = null;
		        if(isset($data['advice_id']) ){
		        	$client_advice_id = $data['advice_id'];
		        }
		        
		        $destination_store_code = $store->short_code;
		        $source_store_code = isset($data['src_site_code'])?$data['src_site_code']:null;


		        $type = $data['type'];
		        $no_of_packets = (isset($data['no_of_packets']))? $data['no_of_packets'] : null;
		        $origin_from = (isset($data['src_site_name']) )?$data['src_site_name']:'';
		        $advice_created_date = (isset($data['advice_created_date']) && $data['advice_created_date']!='')? $data['advice_created_date'] : null;
		        $against_id = (isset($data['src_doc_id']) && $data['src_doc_id']!='')?$data['src_doc_id']:null;
		        $against_created_date = (isset($data['src_doc_created_date']) && $data['src_doc_created_date']!='')?$data['src_doc_created_date']:null;
		        $item_list = $data['item_list'];

		        $advice_qty = 0;
		        $advice_subtotal = 0;
		        $advice_discount = 0;
		        $advice_tax = 0;
		        $advice_total = 0;
		        $advice_status= '';
		        $supplier_id = Null;

		        $tax_details=null;
		        $cgst_per=0;
		        $sgst_per=0;
		        $netamt=0;
		        $headerTax=[];
		        $arrayData = [];
		        $cl=[];
		        

		        $newItemList = [];
		        $headerLevelTax = [];
		        foreach ($item_list as $key => $list) {

			        $advice_qty += $list['qty'];
			        $ref_advice_detail_id = isset($list['advice_detail_no'])?$list['advice_detail_no']:'';
			        $unit_mrp = ( isset($list['unit_mrp']) && $list['unit_mrp']!='' && $list['unit_mrp'] >= 0.000)?$list['unit_mrp']:'0.0';
			        $supply_price =  (isset($list['supply_price']) && $list['supply_price']!='' && $list['supply_price'] >= 0.000)?$list['supply_price']:'0.0';
			        $subtotal = (isset($list['subtotal']) && $list['subtotal']!='' && $list['subtotal'] >= 0.000)?$list['subtotal']:'0.0';
			        $discount =  (isset($list['discount']) && $list['discount']!='' && $list['discount'] >= 0.000)?$list['discount']:'0.0';
			        $tax =  (isset($list['tax_amount']) && $list['tax_amount']!='' && $list['tax_amount'] >= 0.000)?$list['tax_amount']:'0.0';
			        $total =  (isset($list['net_amount']) && $list['net_amount']!='' && $list['net_amount'] >= 0.000)?$list['net_amount']:'0.0';

			        $item_description =  (isset($list['item_description']))?$list['item_description']:'';
			        $packet_id =  (isset($list['packet_id']))?$list['packet_id']:'';
			        $packet_code =  (isset($list['packet_code']))?$list['packet_code']:'';
			        $tax_details = (isset($list['tax_details']))?json_encode($list['tax_details']):'';
			       //  foreach ($list['tax_details'] as $key => $tax_detail) {
			       // 		//$gst = $tax_detail['cgst_rate'] + $tax_detail['sgst_rate'];
			     		// // $netamt = $tax_detail['netamt'];
			     		// // $headerLevelTax[] = [
			     		// // 	'GST '.$gst => $netamt
			     		// // ];
			       //  	$tax_details = json_encode($tax_detail);
			       //  }
                    if($client_id ==1){
			         $exchangeUnitMrp      = getExchangeRate($v_id,$source_currency,$target_currency,$unit_mrp);
			         $exchangeSupplyPrice  = getExchangeRate($v_id,$source_currency,$target_currency,$supply_price);
			         $exchangeSubtotal     = getExchangeRate($v_id,$source_currency,$target_currency,$subtotal);
			         $exchangeDiscount     = getExchangeRate($v_id,$source_currency,$target_currency,$discount);
			         $exchangeTax          = getExchangeRate($v_id,$source_currency,$target_currency, $tax);
			         $exchangeTotal        = getExchangeRate($v_id,$source_currency,$target_currency,$total);
			         
                     $unit_mrp             = $exchangeUnitMrp['amount'];
                     $supply_price         = $exchangeSupplyPrice['amount'];
                     $subtotal             = $exchangeSubtotal['amount'];
                     $discount             = $exchangeDiscount['amount'];
                     $tax                  = $exchangeTax['amount'];
                     $total                = $exchangeTotal['amount'];


			       	}
			       	$newItemList[] = [
			       		'ref_advice_detail_id' => $ref_advice_detail_id,
			       		'qty' => $list['qty'],
			        	'item_no' =>  $list['item_barcode'],
			       		'unit_mrp' =>  $unit_mrp,
			        	'supply_price' => $supply_price,
			        	'item_description' =>  $item_description,
			        	'subtotal' => $subtotal,
			        	'discount' => $discount,
			        	'tax' =>  $tax,
			        	'tax_detail' => $tax_details,
			        	'total' =>  $total,
			        	'packet_id'=>$packet_id,
			        	'packet_code' => $packet_code
			       	];
			        $advice_subtotal += (float)$unit_mrp;
			        $advice_discount += (float)$discount;
			        $advice_tax += (float)$tax;
			        $advice_total += (float)$total;
		        	
		        }

		        // $set = collect($collect);
		        // foreach ($set->groupBy('sgst_rate','cgst_rate') as $key => $sgst) {
		        // 	$netamt = $sgst->sum('netamt');
		        // 	// $gst = $sgst->sum('sgst_rate')+$sgst->sum('cgst_rate');
		        // 	// // dd($gst);
		        // 	// $headerLevelTax[] = [
		        // 	// 	'GST '.$gst => $netamt
		        // 	// ];
		        // }
		        
		        

		        //Checking duplicate advice no is exists or not
		        if(Advise::where('client_advice_no', $client_advice_no)->where('v_id', $v_id)->first()){

		        	$error_list =  [ 
		        		['error_for' =>  'client_advice_no' , 'messages' =>  [ 'This advice number already exists']] 
		        	]; 

		        	$response = [ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' => $error_list  ];
		        	$asyn->status = 'FAIL';
		        	$asyn->response = json_encode($response);
		        	$asyn->save();

		        	return response()->json( $response , 422);
		        }

		        if(isset($data['supplier_name'])){
		        	$supplier = Supplier::where('name', $data['supplier_name'])->first();
		        	if(!$supplier){
		        		$supplier  = Supplier::create(['v_id' => $v_id, 'name' => $data['supplier_name']]);
		        	}

		        	$supplier_id = $supplier->id;
		        }
		        $advice = Advise::create([
		        	'v_id' => $v_id,
		        	'store_id' => $store_id,
		        	'advice_no' => advice_no_generate($v_id, 'THIRD_PARTY_APP'),
		        	'client_advice_no' => $client_advice_no,
		        	'client_advice_id' => $client_advice_id,
		        	'no_of_packets' => $no_of_packets,
		        	'destination_short_code' => $destination_store_code,
		        	'source_store_code' => $source_store_code,
		        	'creation_mode' => 'api',
		        	'type' => $type,
		        	'origin_from' => $origin_from,
		        	'advice_created_date' => $advice_created_date,
		        	'against_id' => $against_id,
		        	'against_created_date' => $against_created_date,

		        	'status' => 'PENDING',
		        	//'advice_type' => '',
		        	'supplier_id' => $supplier_id,
		        	'qty' => (string)$advice_qty,
		        	'subtotal' => (string)$advice_subtotal,
		        	'discount' => (string)$advice_discount,
		        	'tax' => (string)$advice_tax,
		        	'tax_details' => json_encode($headerLevelTax),
		        	'total' => (string)$advice_total,
		        ]);

		        $item_list = $data['item_list'];


		        foreach($newItemList as $val){
			        AdviseList::create([
			        	'v_id' => $v_id,
			        	'store_id' => $store_id,
			        	'advice_id' => $advice->id,
			        	'ref_advice_detail_id' => $val['ref_advice_detail_id'],
			        	'ref_advice_id' => $client_advice_no,
			        	'item_no' =>  $val['item_no'],
			        	'qty' =>  (string)$val['qty'],
			        	'unit_mrp' =>  (string)$val['unit_mrp'],
			        	'cost_price' => $val['supply_price'],
			        	'item_desc' =>  $val['item_description'],
			        	'subtotal' => (string)$val['subtotal'],
			        	'discount' =>  (string)$val['discount'],
			        	'tax' =>  (string)$val['tax'],
			        	'tax_details' => $val['tax_detail'],
			        	'total' =>  (string)$val['total'],
			        	'packet_id' => $val['packet_id'],
			        	'packet_code' => $val['packet_code']
			        ]);

		        }


		        $response = [ 'status' => 'success' , 'message' => 'Advice Created Successfully'   ];
		        
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
}
