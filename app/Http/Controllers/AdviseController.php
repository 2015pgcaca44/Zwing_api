<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Model\Grn\Advise;
use App\Model\Grn\AdviseList;
use App\Model\Grn\Grn;
use App\Model\Grn\GrnList;
use App\Model\Item\ItemList;
use App\Model\Items\Item;
use App\Model\Items\VendorItem;
use App\Model\Items\VendorSku;
use App\Model\Items\VendorSkuDetails;
use App\Model\Items\VendorSkuDetailBarcode;
use App\Model\Stock\Batch;
use App\Model\Stock\Serial;
use App\Store;
use App\Organisation;
use Validator;
use App\Model\Supplier\Supplier;
use App\Model\InboundApi;
use Log;
use DB;
use App\Http\Controllers\GrnController;
//use Auth;

class AdviseController extends Controller
{
	public function __construct()
	{
		//$this->middleware('auth');
		 $this->middleware('auth', ['except' => ['adviceCreate','createAdviceNew']]);
		 //JobdynamicConnection(127);
	}

	public function getAdvices(Request $request){
		$store_id_get = $request->store_id;
		$destination_short_code = Store::select('short_code')->where('store_id',$store_id_get)->first()->short_code;
		$where    = array('v_id'=> $request->v_id,'store_id'=>$request->store_id);
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
			$sortBy = 'supplier.legal_name';
			$data  = Advise::join('suppliers','supplier.id','advice.supplier_id')->where('status','<>','COMPLETE')->where($where)->whereIn('type', ['GRN','PO'])->where(function($query) use($store_id_get){
				$query->where('store_id',$store_id_get);
				$query->orWhereNull('store_id');
				$query->orwhere('store_id',0);
			})->orderByRaw("CASE WHEN  status = 'PENDING' THEN 0 ELSE 1 END ASC")->orderBy($sortBy)->get();
		}else{
 			$data  = Advise::where($where)->where('status','<>','COMPLETE')->whereIn('type', ['GRN','PO'])->Where(function($query) use($store_id_get){
				$query->where('store_id',$store_id_get);
				$query->orWhereNull('store_id');
				$query->orwhere('store_id',0);
			})
			->orderByRaw("CASE WHEN  status = 'PENDING' THEN 0 ELSE 1 END ASC")
			->orderByRaw("CASE WHEN  status = 'PARTIAL' THEN 0 ELSE 1 END ASC")
			->orderBy($sortBy)->latest()->get();
 		}

		
		$responseData = [];
		$filter = [];
		$supplierList = [];
		foreach ($data as $key => $value) {
			$responseData[] = (object)[ 
				'id'			=> $value->id,
				'advice_no'		=> $value->advice_no,
				'created_at' 	=> date('d-m-Y', strtotime($value->created_at)),
				'date' 			=> date('d F Y', strtotime($value->created_at)),
				'supplier_name'	=> isValueExists($value->supplier, 'legal_name', 'str'),
				'origin_from'	=> $value->origin_from,
				'status'		=> $value->status,
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
			$supplierList[] = [ 'id' => isValueExists($supp->supplier, 'id', 'num'), 'name' => isValueExists($supp->supplier, 'legal_name', 'str')];
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
			$adviceNo = Advise::find($value->advice_id)->advice_no;
			$grnData[] = (object)['advice_no' => $adviceNo, 'grn_from'=>$value->grn_from,'grn_no' => $value->grn_no, 'date' => date('d F Y', strtotime($value->created_at)), 'received_qty' => $value->qty, 'damage_qty' => $value->damage_qty, 'lost_qty' => 0, 'remarks' => $value->remarks, 'id' => $value->id, 'short_qty' => $value->short_qty, 'excess_qty' => $value->excess_qty ];
		}
		$data = [ 'advice_no' => $advice->advice_no, 'date' => date('d F Y'), 'supplier' => @$advice->supplier->name, 'origin_from' => $advice->origin_from, 'status' => $advice->current_status, 'grn_list' => $grnData ];
		return response()->json(['status' => 'success', 'data' => $data ], 200);

	}

	public function adviceList(Request $request){
		$advice_id 	= $request->advice_id; // Advice_id
		$v_id       = $request->v_id;
		$isConditon = false;
		$grnlistC   = [];

		if($request->has('trans_from')) {
			if($request->trans_from == 'CLOUD_TAB_WEB') {
				$isConditon = true;
			} 
		} else {
			$isConditon = false;
		}
		if($isConditon) {


			$where    = array(
                'advice.v_id' => $v_id,
                'advice.id' => $advice_id,
            );
            $advice = Advise::join('stores', 'stores.store_id','advice.store_id')->where($where)->first();
            $adviceDetails = [ 'advice_no' => $advice->advice_no, 'qty' => $advice->qty, 'total' => format_number($advice->total), 'client_advice_no' => $advice->client_advice_no, 'origin_from' => $advice->origin_from, 'created_at' => date('d-m-Y', strtotime($advice->created_at)), 'subtotal' => format_number($advice->subtotal), 'discount' => format_number($advice->discount), 'tax' => format_number($advice->tax), 'charge' => format_number($advice->charge), 'store_name' => $advice->store->name, 'address1' => $advice->store->address, 'gst' => $advice->store->gst, 'supplier' => @$advice->supplier->name, 'product_count' => $advice->advicelist->count(), 'charge' => format_number($advice->charge), 'no_of_packets' => $advice->no_of_packets ];
			$data 		= $advice->advice_product_details;
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
	                                    $query->where('vendor_sku_detail_barcodes.v_id',$v_id);
	                            }])->first();

				$is_batch   = 0;
				$is_serial  = 0;
				$status     = '';
				if(!empty($item_check->Items) ){
					$is_batch = $item_check->Items->vendorItem->has_batch;
					$is_serial= $item_check->Items->vendorItem->has_serial;
					if($item_check->Items->vendorItem->has_batch == 1){
						$status = "Batch";
					}if($item_check->Items->vendorItem->has_serial == 1){
						$status = "Serial";
					}if($item_check->Items->vendorItem->has_batch == 1 && $item_check->Items->vendorItem->has_serial == 1){
						$status = "Batch/Serial";
					}	
				}
				if (strpos($status, 'Batch') !== false) {
					$batch = array('batch_no'=>'','mfg_date'=>'','exp_date'=>'','valid_months'=>'','qty'=>''); 
				}

        if($request->has('type') && $request->type == 'grt'){


            $param['id'] = $id;
            $param['from_stock_point']  = $request->from_stock_point;
            return $this->getGrtAdviceList($param);
        }
        $v_id = $request->v_id;
        $id = $request->advice_id;
        $isPacket = false;
        $advice = Advise::find($id);
        //return AdviseList::where($where)->with(['Items','Batch'])->get();
        // return AdviseList::where($where)->with(['Items' => function ($query) {
        //     $query->where('v_id', Auth::user()->v_id);
        // }])->get();
        $adviceData = $packetList = [];
        $skuBarcode = VendorSku::select('vendor_sku_flat_table.v_id','vendor_sku_flat_table.has_serial as serial','vendor_sku_flat_table.has_batch as batch', 'vendor_sku_detail_barcodes.barcode' )
        ->join('vendor_sku_detail_barcodes','vendor_sku_detail_barcodes.vendor_sku_detail_id','=','vendor_sku_flat_table.vendor_sku_detail_id');

        $adviseList = AdviseList::leftJoin('grn_list', function($query) {
                                    $query->on('advice_list.id', 'grn_list.advice_list_id');
                                    // $query->on('advice_list.item_no', '!=', 'grn_list.barcode');
                                })
                                // ->leftJoin('v_item_list', function($query) {
                                //     $query->on('v_item_list.v_id', 'advice_list.v_id');
                                //     $query->on('v_item_list.barcode', 'advice_list.item_no');
                                // })
                                ->leftJoinSub($skuBarcode, 'sku_barcode', function($query){
                                	$query->on('sku_barcode.v_id', 'advice_list.v_id');
                                    $query->on('sku_barcode.barcode', 'advice_list.item_no');
                                })
                                ->where('advice_list.v_id', $v_id)
                                ->where('advice_list.advice_id', $id)
                                //->whereNull('grn_list.id')
                                ->select('grn_list.id as grn_list_id', 'advice_list.item_no','advice_list.item_desc','advice_list.unit_mrp','advice_list.cost_price','advice_list.subtotal','advice_list.discount','advice_list.total','advice_list.tax','advice_list.qty','sku_barcode.batch','sku_barcode.serial','advice_list.id','advice_list.packet_code','advice_list.advice_id');
        // Check Packet Exists
        if(count($advice->grnlist) > 0) {
            if(!empty($advice->no_of_packets)) {
                $uniquePacketCode = GrnList::select('packet_code')->distinct()->whereIn('grn_id', $advice->grnlist->pluck('id'))->get();
                if(!$uniquePacketCode->isEmpty()) {
                    $adviseList = $adviseList->whereNotIn('advice_list.packet_code', $uniquePacketCode);
                }
            } 
        }

		$adviseList = $adviseList
        ->groupBy('advice_list.item_no')// This condition is added because in Mpos showing duplicate item for advise list
        ->get();
        // dd($adviseList->first());
        foreach ($adviseList as $key => $value) {
        	
            $is_batch = $is_serial = $received_qty = $damage_qty = $lost_qty = 0;
            // $received_qty = $value->qty;
            $status     = '';
            	
            	// if(!empty($value->Items)) {
                // $itemInfo = $value->Items->where('v_id', $v_id)->where('barcode', $value->item_no)->first();
                $is_batch = $value->Items->Item->has_batch;
                $is_serial= $value->Items->Item->has_serial;
                if($value->Items->Item->batch == 1) {
                    $status = "Batch";
                }
                if($value->Items->Item->serial == 1) {
                    $status = "Serial";
                }
                if($value->Items->Item->batch == 1 && $value->Items->Item->serial == 1) {
                    $status = "Batch/Serial";
                }

                $supply_price = $value->unit_mrp;
                if($value->cost_price > 0.00) {
                  $supply_price = $value->cost_price;
                }

                if(empty($value->packet_code)) {
                    $value->packet_code = '';
                }

                $packetList[$value->packet_code] = [ 'code' => $value->packet_code, 'ordered' => 0, 'received' => 0, 'damaged' => 0, 'short' => 0, 'excess' => 0, 'subtotal' => 0, 'discount' => 0, 'taxes' => 0, 'charges' => 0, 'total' => 0 ];
                if(!empty($value->packet_code)) {
                    $isPacket = true;
                }

                $adviceData[] = [
                  'id'                  => $value->id,
                  'code'                => $value->packet_code,
                  'barcode'             => $value->item_no,
                  'item_desc'           => $value->item_desc,
                  'is_batch'            => $is_batch,
                  'is_serial'           => $is_serial,
                  'order_qty'           => $value->qty,
                  'received_qty'        => $received_qty,
                  'damage_qty'          => $damage_qty,
                  'short_ex'            => $lost_qty,
                  'remarks'             => '',
                  'charges'             => 0,
                  'supply_price'        => format_number($supply_price),
                  'subtotal'            => 0,
                  'discount'            => 0,
                  'tax'                 => 0,
                  'total'               => 0,
                  'advice_id'           => $value->advice_id,
                  'order'               => 0,
                  'error'               => '',
                  'uom'                 => $value->Items->Item->uom->selling->type
                ];

            // }
        
        }
        $store = Store::find($advice->store_id);
        // $adviceData = $adviceData->take(100)->map(function($item, $key) {
        //     $item['sr_no'] = $key + 1;
        //     $item['visible'] = true;
        //     return $item;
        // })->groupBy('packet_code');
        return response()->json(['status'=>'success','product_list' => $adviceData, 'store_name' => $store->name, 'packet' => $packetList, 'is_packet' => $isPacket, 'store_id' => $store->store_id ], 200);
    
	}
}
}



	public function customValidation(){

	}

	public function adviceCreate(Request $request){

		if ($request->isJson()) {
			try {
		        $data = $request->json()->all();

		        $client = oauthUser($request);
		        $client_id = $client->client_id;
		        $clients   = $client->id;

		        //$data = collect($data);
		        ######################
		        # Dynamic connection is not added may be we need to add
		        #######################
		        //$data = collect($data);
		        $messages = [
		        	// 'required' => 'The :attribute field is required.',
		        	'item_list.*.item_barcode.exists' => 'Item Barcode does not Exists'
		        ];

		        $custom_valiation = [];

		        $validation = [
	                'advice_no' => 'required',
	                'dest_site_code' => 'required',
	                'src_site_code' => 'required',
	                'type' => 'required|in:PO,SST,STO,GRT,GRN,STR',
	                'item_list' => 'array',
	                'advice_created_date' => 'date_format:Y-m-d',
	                'against_created_date' => 'date_format:Y-m-d',
	                'item_list.*.qty' => 'required',
	            ];

		        if($clients==1){
		        	//For Ginesys ref_item_code is sku code
		        	$custom_valiation = ['item_list.*.item_barcode' => 'required|exists:vendor_items,ref_item_code,deleted,NULL',
			        	];
		        }else{
			        $custom_valiation = ['item_list.*.item_barcode' => 'required|exists:vendor_sku_detail_barcodes,barcode,deleted_at,NULL',
			        	];
		        }
		        $validation = array_merge($validation, $custom_valiation);

		        /** @var \Illuminate\Contracts\Validation\Validator $validation **/
		        $validator = Validator::make($data,$validation,$messages);

		        //This code is added when we are using client
		 
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
		        $asyn->api_name = 'client/advice/create';
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

		       	
		       	JobdynamicConnection($v_id);
		        
		        $store_id = null;
		        $store = null;
		        if($request->type=='GRT' || $request->type=='STR'){
                  $destination_site_code= $request->src_site_code;
                  $source_site_code=  $request->dest_site_code;

                  $store = Store::select('store_id','short_code')->where('v_id', $v_id)->where('store_reference_code', $src_site_code)->first();
                  $store_id = $store->store_id;

                  $dest_store = Store::select('store_id','short_code')->where('v_id', $v_id)->where('store_reference_code', $destination_site_code)->first();

                  $destination_store_code = $dest_store->short_code;

		        }else{
                  $destination_site_code= $request->dest_site_code;
                  $source_site_code=  $request->src_site_code;

                  $store = Store::select('store_id','short_code')->where('v_id', $v_id)->where('store_reference_code', $destination_site_code)->first();
                  $store_id = $store->store_id;

                  $destination_store_code = $store->short_code;
		        }
                 


		        if(!$store){
                    if($request->type=='GRT'){
                    	$error_list =  [ 
		        		   [ 'error_for' =>  'source_site_code' , 'messages' => ['Unable to find This source_site_code'] ]
		            	]; 

                    }else{
			        	$error_list =  [ 
			        		[ 'error_for' =>  'dest_site_code' , 'messages' => ['Unable to find This dest_site_code'] ]
			        	]; 
		           }

		        	$response = [ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' => $error_list  ];
		        	$asyn->status = 'FAIL';
		        	$asyn->response = json_encode($response);
		        	$asyn->save();

		        	return response()->json( $response, 422);
		        }



		        $supplier = Supplier::select('id','reference_code')->where('v_id', $v_id)->where('reference_code', $source_site_code)->first();
		        if(!$supplier){
		        	//dd($client_id);
                    if($clients==1){
	                    if($request->type=='GRT'){
	                    	$error_list =  [ 
			        		   [ 'error_for' =>  'dest_site_code' , 'messages' => ['Unable to find This dest_site_code'] ]
			            	]; 

	                    }else{
			        	$error_list =  [ 
			        		[ 'error_for' =>  'source_site_code' , 'messages' => ['Unable to find This source_site_code'] ]
			        	]; 
			           }
			         $response = [ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' => $error_list  ]; 
		        	 $asyn->status = 'FAIL';
		        	 $asyn->response = json_encode($response);
		        	 $asyn->save();

		        	 return response()->json( $response, 422);   
	                }else{
	                	//dd('hh');
	                   $default = 'Solasta_'.$source_site_code;
	                   $code    = 'SUP0'.$source_site_code;
	                   //dd($code);
	                   $supplier   = new Supplier;
	                   $supplier->supplier_code    =  $code;
	                   $supplier->reference_code   = $source_site_code;
	                   $supplier->trade_name       = $default;
	                   $supplier->legal_name       = $default;
	                   $supplier->save();
                      //dd($supplier);
	                }
		        }


		        $supplier_id =$supplier->id;

		        $no_of_packets = 0;

		        $client_advice_no = $data['advice_no'];

		        $client_advice_id = null;
		        if(isset($data['advice_id']) ){
		        	$client_advice_id = $data['advice_id'];
		        }else{
		        	//Need to change this to advice_id from src_doc_id for ginesys in future
			        if($client->id == 1){
			        	if(isset($data['src_doc_id'])){
			        		$client_advice_id = $data['src_doc_id'];
			        	}
			        }
		        }


		        /*if($client->id == 1 && ($client_advice_id == null || $client_advice_id =='') ){
		        	$error_list =  [ 
		        		[ 'error_for' =>  'advice_id' , 'messages' => ['Advice Id is required'] ]
		        	]; 

		        	$response = [ 'status' => 'fail' , 'message' => 'Validation fail' , 'errors' => $error_list  ];
		        	$asyn->status = 'FAIL';
		        	$asyn->response = json_encode($response);
		        	$asyn->save();

		        	return response()->json( $response, 422);
		        }*/
		        
		        
		        $source_store_code = isset($data['src_site_code'])?$data['src_site_code']:null;


		        $type = $request->type;
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
		       // $supplier_id = Null;

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
			        $ref_sku_code =  (isset($list['sku_code']))?$list['sku_code']:null;
			        $sku_code = null;
			       //  foreach ($list['tax_details'] as $key => $tax_detail) {
			       // 		//$gst = $tax_detail['cgst_rate'] + $tax_detail['sgst_rate'];
			     		// // $netamt = $tax_detail['netamt'];
			     		// // $headerLevelTax[] = [
			     		// // 	'GST '.$gst => $netamt
			     		// // ];
			       //  	$tax_details = json_encode($tax_detail);
			       //  }
			       	
			       	$newItemList[] = [
			       		'ref_advice_detail_id' => $ref_advice_detail_id,
			       		'ref_sku_code' => $ref_sku_code,
			       		'sku_code' => $sku_code,
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

		        // if(isset($data['supplier_name'])){
		        // 	$supplier = Supplier::where('name', $data['supplier_name'])->first();
		        // 	if(!$supplier){
		        // 		$supplier  = Supplier::create(['v_id' => $v_id, 'name' => $data['supplier_name']]);
		        // 	}

		        // 	$supplier_id = $supplier->id;
		        // }
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
		        	//dd($val['item_description']);
					
					$ref_sku_code = $val['ref_sku_code'];
					$sku_code = $val['sku_code'];

					//For Ginesys Client
					if($clients==1){
						$ref_sku_code = $val['item_no']; //Ginesys sending sku_code which is same as item Code
						$vendorItem = VendorItem::where('ref_item_code', $ref_sku_code)->where('v_id', $v_id)->first();
						$item =Item::select('name')->where('id', $vendorItem->item_id)->first(); 
						$sku = VendorSkuDetails::select('id','sku_code','item_id')->where('item_id', $vendorItem->item_id)->first();
						$sku_code = $sku->sku_code;
						$bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('vendor_sku_detail_id',$sku->id)->first();
						$item_description = $item->name;
						$item_no = $bar->barcode;
					}else{
						$item_no = $val['item_no'];
						$item_description = $val['item_description'];

						if($ref_sku_code){
	                        $sku = VendorSkuDetails::select('sku_code')->where('v_id', $v_id)->where('ref_sku_code', $ref_sku_code)->first();
	                        $sku_code = $sku->sku_code;


				       	}else{
				       		$bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $list['item_barcode'] )->first();
	                        if($bar){
	                        	$sku = VendorSkuDetails::select('sku_code')->where('id', $bar->vendor_sku_detail_id)->first();
	                            $sku_code = $sku->sku_code;
	                        }
				       	}  
					}  

			        AdviseList::create([
			        	'v_id' => $v_id,
			        	'store_id' => $store_id,
			        	'advice_id' => $advice->id,
			        	'ref_advice_detail_id' => $val['ref_advice_detail_id'],
			        	'ref_sku_code' => $ref_sku_code,
			        	'sku_code' => $sku_code,
			        	'ref_advice_id' => $client_advice_no,
			        	'item_no' => $item_no,
			        	'ref_item_id'=>$val['item_no'],
			        	'qty' =>  (string)$val['qty'],
			        	'unit_mrp' =>  (string)$val['unit_mrp'],
			        	'cost_price' => (string)$val['supply_price'],
			        	'item_desc' => $item_description,
			        	'subtotal' => (string)$val['subtotal'],
			        	'discount' =>  (string)$val['discount'],
			        	'tax' => (string) $val['tax'],
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

	//new advice list  create function for inventory :Prashant
	public function createAdviceNew(Request $request)
	{
		if($request->mode == 'multiple') {
			return $this->multipleAdviceEntry($request);
		}
        $adviceData = json_decode($request->advice);
        if($request->has('advice_id') && empty($request->advice_id)){
            return $this->entryInAdvice($request);
        } else if(!empty($request->advice_id)) {

            try {
                
                $advice_list = json_decode($request->advice_list);



                $advice_list_count = $request->advice_list_count;
                $getStoreDet = Store::where('short_code', $adviceData->destination_short_code)->first();
                $advice_list_where = [ 'v_id' => $request->v_id, 'store_id' => $getStoreDet->store_id, 'advice_id' => $request->advice_id ];

                foreach ($advice_list as $key => $adviceItem) {
                	//Added by Chandramani Tagging Sku code 
                	$sku_code = null;
                	$batch_id = $batch_no = $serial_id = $serial_no = 0;
                	$bar = VendorSkuDetailBarcode::select('sku_code','vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $request->v_id)->where('barcode', trim($adviceItem->item_barcode) )->first();

                	if(!isset($adviceItem->sku_code) ){
		                if($bar) {		         
		                	$sku_code = $bar->sku_code;
		                }
                	} else {
                		$sku_code = $adviceItem->sku_code;
                	}
                	//DB::enableQueryLog();
                    //$bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id')->where('v_id', $request->v_id)->where('barcode', trim($adviceItem->item_barcode))->first();

                    if($bar) {
						$itemCheck = VendorSku::where([ 'vendor_sku_detail_id' => $bar->vendor_sku_detail_id, 'v_id' => $request->v_id, 'deleted_at' => null ])->first();
                    }
                    //dd(DB::getQueryLog());
                    //return $itemCheck;
                    if(!empty($itemCheck) && $itemCheck->has_batch == '1' && !empty($adviceItem->batch_no)) {
                    	$batchDetail = Batch::where([ 'v_id' => $request->v_id, 'batch_no' => trim($adviceItem->batch_no) ])->first();
                    	if(!empty($batchDetail)) {
                    		$batch_id = $batchDetail->id;
                    		$batch_no = $batchDetail->batch_code;
                    	} else {
                    		$mrp = trim($adviceItem->unit_mrp);
                    		$priceParam = [ 'mrp' => $mrp, 'rsp' => $mrp, 'special_price' => $mrp ];
                    		$grnCon = new GrnController;
                        	$priceId = $grnCon->priceAdd($priceParam);
                    		$newBatch = Batch::create([ 'v_id' => $request->v_id, 'batch_no' => trim($adviceItem->batch_no), 'mfg_date' => date('Y-m-d', strtotime(trim($adviceItem->mfg_date))), 'exp_date' => date('Y-m-d', strtotime(trim($adviceItem->exp_date))), 'valid_months' => trim($adviceItem->validity_values), 'item_price_id' => $priceId, 'batch_code' => generateBatchCode($request->v_id), 'validity_unit' => trim($adviceItem->validity_unit) ]);
                    		$batch_id = $newBatch->id;
                    		$batch_no = $newBatch->batch_code;
                    	}
                    }

                    if(!empty($itemCheck) && $itemCheck->has_serial == '1' && !empty($adviceItem->serial_no)) {
                    	$serialDetail = Serial::where([ 'v_id' => $request->v_id, 'serial_no' => trim($adviceItem->serial_no) ])->first();
                    	if(!empty($serialDetail)) {
                    		$serial_id = $serialDetail->id;
                    		$serial_no = $serialDetail->serial_code;
                    	} else {
                    		$mrp = trim($adviceItem->unit_mrp);
                    		$priceParam = [ 'mrp' => $mrp, 'rsp' => $mrp, 'special_price' => $mrp ];
                    		$grnCon = new GrnController;
                        	$priceId = $grnCon->priceAdd($priceParam);
                    		$newSerial = Serial::create([ 'v_id' => $request->v_id, 'serial_no' => trim($adviceItem->serial_no), 'manufacturing_date' => date('Y-m-d', strtotime(trim($adviceItem->mfg_date))), 'sku_code' => $sku_code, 'is_warranty' => trim($adviceItem->has_warranty) == 'Y' ? '1' : '0', 'item_price_id' => $priceId, 'warranty_period' => trim($adviceItem->warranty_period), 'udf1' => trim($adviceItem->serial_udf1), 'udf2' => trim($adviceItem->serial_udf2), 'udf3' => trim($adviceItem->serial_udf3), 'serial_code' => generateSerialCode($request->v_id), 'validity_unit' => trim($adviceItem->warranty_period_unit) ]);
                    		$serial_id = $newSerial->id;
                    		$serial_no = $newSerial->serial_code;
                    	}
                    }
                    
                    AdviseList::create([
                		'v_id' 			=> $request->v_id,
                		'store_id' 		=> $getStoreDet->store_id,
                		'advice_id' 	=> $request->advice_id,
                		'sku_code' 		=> $sku_code,
                		'item_no' 		=> trim($adviceItem->item_barcode),
                		'batch_id' 		=> $batch_id,
                		'batch_code'	=> $batch_no,
                		'serial_id'	    => $serial_id,
                		'serial_code'	=> $serial_no,
                		// 'packet_id' 	=> trim($adviceItem->packet_id),
                		'packet_code' 	=> trim($adviceItem->packet_code),
                		'qty'	 		=> trim($adviceItem->qty),
                		'unit_mrp' 		=> trim($adviceItem->unit_mrp),
                		'cost_price'	=> trim($adviceItem->supply_price),
                		'supply_price'	=> trim($adviceItem->supply_price),
                		'item_desc' 	=> trim($adviceItem->item_description),
                		'subtotal' 		=> trim($adviceItem->subtotal),
                		'discount' 		=> trim($adviceItem->discount),
                		// 'tax' 			=> trim($adviceItem->tax),
                		'charge' 		=> trim($adviceItem->charge),
                		'total' 		=> trim($adviceItem->total)
                    ]);

                    //chek how many list are remaing if done then break  prashant code
	                // $current_advicelist_count = AdviseList::where($advice_list_where)->count();
	                // if($advice_list_count==$current_advicelist_count){
	                //     break;
	                // }
                }
                
                $tempCount = AdviseList::where($advice_list_where)->count();
		        if($advice_list_count == $tempCount) {
		        	$adviceQty = AdviseList::where($advice_list_where)->sum('qty');
	                $adviceSubtotal = AdviseList::where($advice_list_where)->sum('subtotal');
	                $adviceDiscount = AdviseList::where($advice_list_where)->sum('discount');
	                $adviceTax = AdviseList::where($advice_list_where)->sum('tax');
	                $adviceCharges = AdviseList::where($advice_list_where)->sum('charge');
	                $adviceTotal = AdviseList::where($advice_list_where)->sum('total');
	                Advise::where('id', $request->advice_id)->update([ 'qty' => $adviceQty, 'subtotal' => format_number($adviceSubtotal), 'discount' => format_number($adviceDiscount), 'tax' => format_number($adviceTax), 'charge' => format_number($adviceCharges), 'total' => format_number($adviceTotal), 'status' => 'PENDING' ]);
		        	return response()->json([ 'status' => 'success', 'message' => 'Advice created sucessfully!' , 'id' => $request->advice_id ]);
		        } else {
		        	$remaining_list = $advice_list_count - $tempCount;
		           	return response()->json(["status" => 'continue' , 'remaining' => $remaining_list]);
		       	}

            }catch (Exception $e) {
                DB::rollBack();
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Some error has occurred Plz try again'
                ]);
            }	
				
				
        } else {
           return response()->json(["status" => 'fail' ,'message' => 'Invalid request' ]);
        }
        
	}

	//create new advice for inventory module:prashant
	public function entryInAdvice(Request $request)
	{
		$adviceData = json_decode($request->advice);
		if(isValueChecker($adviceData->ref_advice_no) && isValueChecker($adviceData->destination_short_code) && isValueChecker($adviceData->supplier_code)) {
                          
            $supplier_tag = 0;
            // Check Supplier                        
                    
            $checkSuppler = Supplier::where([ 'supplier_code' => $adviceData->supplier_code , 'v_id' => $request->v_id ])->first();
            // if(!empty($checkSuppler)) {
                $supplier_tag = $checkSuppler->id;
            // } else {
            //     $supplier = Supplier::create([
            //         'v_id'      => $request->v_id,
            //         'legal_name'      => $adviceData->supplier_name,
            //         'status'    => '1'
            //     ]);
            //     $supplier_tag = $supplier->id;
            // }

            $getStoreDet = Store::where('short_code', $adviceData->destination_short_code)->first();

            $advice = Advise::create([
                'v_id'     				 	=> $request->v_id,
                'store_id'					=> $getStoreDet->store_id,
                'destination_short_code'  	=> trim($adviceData->destination_short_code),
                'vu_id'     				=> $request->vu_id,
                'advice_no' 				=> advice_no_generate($request->v_id),
                'client_advice_no' 			=> trim($adviceData->ref_advice_no),
                'no_of_packets' 			=> trim($adviceData->no_of_packets),
                'type'      				=> trim($adviceData->type),
                'status'    				=> trim('DRAFT'),
                'supplier_id' 				=> trim($supplier_tag),
                'advice_type' 				=> trim('NORMAL'),
                'creation_mode' 			=> trim('manual'),
                'origin_from'   			=> '',
                'advice_created_date' 		=> date('Y-m-d'),                            
                'against_created_date' 		=> date('Y-m-d'),
                'against_id' 				=> 0,
            	]);

            return response()->json([ "status" => 'advice_entry', 'advice_id' => $advice->id  ]);

		} else {
            return response()->json([
                'message' => "client_advice_no,destination_short_code,origin_from,supplier_name are null or zero Please check !",
                'status' => 'fail'
            ]);
        }
	}		

	public function multipleAdviceEntry(Request $request)
	{
        
        if($request->has('bulk_advice') && !empty($request->bulk_advice)) {
        	$adviceData = json_decode($request->bulk_advice);
        	$isValidation = true;

        	foreach ($adviceData as $key => $value) {
        		if(isValueChecker($value->ref_advice_no) && isValueChecker($value->destination_store_code) && isValueChecker($value->supplier_code)) {
                          
		            $supplier_tag = 0;
		            // Check Supplier                        
		                    
		            $checkSuppler = Supplier::where([ 'supplier_code' => $value->supplier_code , 'v_id' => $request->v_id ])->first();
		            // if(!empty($checkSuppler)) {
		                $supplier_tag = $checkSuppler->id;
		            // } else {
		            //     $supplier = Supplier::create([
		            //         'v_id'      => $request->v_id,
		            //         'legal_name'      => $value->supplier_name,
		            //         'status'    => '1'
		            //     ]);
		            //     $supplier_tag = $supplier->id;
		            // }

		            $getStoreDet = Store::where('short_code', $value->destination_store_code)->first();

		            $advice = Advise::create([
		                'v_id'     				 	=> $request->v_id,
		                'store_id'					=> $getStoreDet->store_id,
		                'destination_short_code'  	=> trim($value->destination_store_code),
		                'vu_id'     				=> $request->vu_id,
		                'advice_no' 				=> advice_no_generate($request->v_id),
		                'client_advice_no' 			=> trim($value->ref_advice_no),
		                'type'      				=> trim($value->type),
		                'no_of_packets' 			=> 0,
		                'status'    				=> trim('DRAFT'),
		                'supplier_id' 				=> trim($supplier_tag),
		                'advice_type' 				=> trim('NORMAL'),
		                'creation_mode' 			=> trim('manual'),
		                'origin_from'   			=> '',
		                'advice_created_date' 		=> date('Y-m-d'),                            
		                'against_created_date' 		=> date('Y-m-d'),
		                'against_id' 				=> 0,
		                'source_store_code'			=> 'MPL'
		            	]);
				} else {
					$isValidation = false;
					break;
				}
        	}

        	if($isValidation) {
        		return response()->json([ "status" => 'advice_entry', 'advice_id' => 0  ]);
        	} else {
        		return response()->json([
	                'message' => "Unable to Save  Advice Data Missing! ",
	                'status' => 'fail'
	            ]);
        	}

        } else if(empty($request->advice_id) && empty($request->bulk_advice)) {

            try {
                
                $advice_list = json_decode($request->advice_list);
                $advice_list_count = $request->advice_list_count;
                $advice_list_where = [ 'v_id' => $request->v_id, 'ref_advice_detail_id' => 'MPL', 'advice_id' => 0, 'ref_item_id' => $request->unique_code ];
                foreach ($advice_list as $key => $adviceItem) {
                	$batch_id = $batch_no = $serial_id = $serial_no = 0;
                	$sku_code = null;
                	$bar = VendorSkuDetailBarcode::select('sku_code','vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $request->v_id)->where('barcode', trim($adviceItem->item_barcode))->first();
                    if($bar){
                        $sku_code = $bar->sku_code;

                    }

                    if($bar) {
						$itemCheck = VendorSku::where([ 'vendor_sku_detail_id' => $bar->vendor_sku_detail_id, 'v_id' => $request->v_id, 'deleted_at' => null ])->first();
                    }

                    if(!empty($itemCheck) && $itemCheck->has_batch == '1') {
                    	$batchDetail = Batch::where([ 'v_id' => $request->v_id, 'batch_no' => trim($adviceItem->batch_no) ])->first();
                    	if(!empty($batchDetail)) {
                    		$batch_id = $batchDetail->id;
                    		$batch_no = $batchDetail->batch_code;
                    	} else {
                    		$mrp = trim($adviceItem->unit_mrp);
                    		$priceParam = [ 'mrp' => $mrp, 'rsp' => $mrp, 'special_price' => $mrp ];
                    		$grnCon = new GrnController;
                        	$priceId = $grnCon->priceAdd($priceParam);
                    		$newBatch = Batch::create([ 'v_id' => $request->v_id, 'batch_no' => trim($adviceItem->batch_no), 'mfg_date' => date('Y-m-d', strtotime(trim($adviceItem->mfg_date))), 'exp_date' => date('Y-m-d', strtotime(trim($adviceItem->exp_date))), 'valid_months' => trim($adviceItem->validity_values), 'item_price_id' => $priceId, 'batch_code' => generateBatchCode($request->v_id) ]);
                    		$batch_id = $newBatch->id;
                    		$batch_no = $newBatch->batch_code;
                    	}
                    }

                    if(!empty($itemCheck) && $itemCheck->has_serial == '1') {
                    	$serialDetail = Serial::where([ 'v_id' => $request->v_id, 'serial_no' => trim($adviceItem->serial_no) ])->first();
                    	if(!empty($serialDetail)) {
                    		$serial_id = $serialDetail->id;
                    		$serial_no = $serialDetail->serial_code;
                    	} else {
                    		$mrp = trim($adviceItem->unit_mrp);
                    		$priceParam = [ 'mrp' => $mrp, 'rsp' => $mrp, 'special_price' => $mrp ];
                    		$grnCon = new GrnController;
                        	$priceId = $grnCon->priceAdd($priceParam);
                    		$newSerial = Serial::create([ 'v_id' => $request->v_id, 'serial_no' => trim($adviceItem->serial_no), 'manufacturing_date' => date('Y-m-d', strtotime(trim($adviceItem->mfg_date))), 'sku_code' => $sku_code, 'is_warranty' => trim($adviceItem->has_warranty) == 'Y' ? '1' : '0', 'item_price_id' => $priceId, 'warranty_period' => trim($adviceItem->warranty_period), 'udf1' => trim($adviceItem->serial_udf1), 'udf2' => trim($adviceItem->serial_udf2), 'udf3' => trim($adviceItem->serial_udf3), 'serial_code' => generateSerialCode($request->v_id) ]);
                    		$serial_id = $newSerial->id;
                    		$serial_no = $newSerial->serial_code;
                    	}
                    }
                    
                    AdviseList::create([
                		'v_id' 					=> $request->v_id,
                		'store_id' 				=> $adviceItem->store_id,
                		'advice_id' 			=> 0,
                		'sku_code'	 			=> $sku_code,
                		'item_no' 				=> trim($adviceItem->item_barcode),
                		'batch_id' 				=> $batch_id,
                		'batch_code'			=> $batch_no,
                		'serial_id'	    		=> $serial_id,
                		'serial_code'			=> $serial_no,
                		// 'packet_id' 			=> trim($adviceItem->packet_id),
                		'packet_code' 			=> trim($adviceItem->packet_code),
                		'qty'	 				=> trim($adviceItem->qty),
                		'unit_mrp' 				=> trim($adviceItem->unit_mrp),
                		'cost_price'			=> trim($adviceItem->supply_price),
                		'supply_price'			=> trim($adviceItem->supply_price),
                		'item_desc' 			=> trim($adviceItem->item_description),
                		'subtotal' 				=> trim($adviceItem->subtotal),
                		'discount' 				=> $adviceItem->discount,
                		// 'tax' 					=> trim($adviceItem->tax),
                		'charge' 				=> trim($adviceItem->charge),
                		'total' 				=> trim($adviceItem->total),
                		'ref_advice_detail_id'	=> 'MPL',
                		'ref_item_id' 			=> $request->unique_code
                    ]);

                }
                
                $tempCount = AdviseList::where($advice_list_where)->count();
		        if($advice_list_count == $tempCount) {
		        	$getAdvicesList = AdviseList::select('store_id')->where($advice_list_where)->groupBy('store_id')->get();
		        	foreach ($getAdvicesList as $key => $value) {
		        		$adviceQty = AdviseList::where($advice_list_where)->where('store_id', $value->store_id)->sum('qty');
		                $adviceSubtotal = AdviseList::where($advice_list_where)->where('store_id', $value->store_id)->sum('subtotal');
		                $adviceDiscount = AdviseList::where($advice_list_where)->where('store_id', $value->store_id)->sum('discount');
		                $adviceTax = AdviseList::where($advice_list_where)->where('store_id', $value->store_id)->sum('tax');
		                $adviceCharges = AdviseList::where($advice_list_where)->where('store_id', $value->store_id)->sum('charge');
		                $adviceTotal = AdviseList::where($advice_list_where)->where('store_id', $value->store_id)->sum('total');
		                $adviseDetails = Advise::where('v_id', $request->v_id)->where('source_store_code', 'MPL')->where('store_id', $value->store_id)->where('status', 'DRAFT')->where('advice_type', 'NORMAL')->where('creation_mode', 'manual')->first();
		                $adviseDetails->qty = $adviceQty;
		                $adviseDetails->subtotal = format_number($adviceSubtotal);
		                $adviseDetails->discount = format_number($adviceDiscount);
		                $adviseDetails->tax = format_number($adviceTax);
		                $adviseDetails->charge = format_number($adviceCharges);
		                $adviseDetails->total = format_number($adviceTotal);
		                $adviseDetails->status = 'PENDING';
		                $adviseDetails->source_store_code = null;
		                $adviseDetails->save();
		                AdviseList::where($advice_list_where)->where('store_id', $value->store_id)->update([ 'ref_advice_detail_id' => null, 'ref_item_id' => null, 'advice_id' => $adviseDetails->id ]);
		        	}
		        	return response()->json([ 'status' => 'success', 'message' => 'Advice save sucessfully!' ]);
		        } else {
		        	$remaining_list = $advice_list_count - $tempCount;
		           	return response()->json(["status" => 'continue' , 'remaining' => $remaining_list]);
		       	}

            }catch (Exception $e) {
                DB::rollBack();
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Some error has occurred Plz try again'
                ]);
            }	
				
				
        } else {
           return response()->json(["status" => 'fail' ,'message' => 'Invalid request' ]);
        }
        
	}
}
