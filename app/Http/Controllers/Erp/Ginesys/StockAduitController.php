<?php

namespace App\Http\Controllers\Erp\Ginesys;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiCallerController;
use App\Model\OutboundApi;
use App\Model\Oauth\OauthClient;
use App\Model\Audit\AuditCountGroup;
use App\Model\Audit\AuditCountGroupDetail;
use App\Model\Audit\AuditPlan;
use App\Model\Audit\AuditPlanAllocation;
use App\Model\Audit\AuditPlanDetails;
use App\Model\Items\VendorSku;
use App\Model\Items\VendorSkuDetailBarcode;
use App\Http\Controllers\CloudPos\CartconfigController;
use App\Model\Items\VendorItems;
use App\Organisation;
use App\Store;
use Log;

class StockAduitController extends Controller
{
   
    private $config= null;
	
	public function __construct()
    {
    	//$this->config = new ConfigController;
    }

   public function StockAduitPush($params){
   	    $id = null;
    	$error_for = null;
    	$checkExits = OutboundApi::where([ 'for_transaction' => 'Stock Aduit Push', 'transaction_id' => $params['audit_plan_id'], 'client_id' => $params['client_id'], 'v_id' => (int)$params['v_id'], 'store_id' => (int)$params['store_id'] ])->first();
    	if(empty($checkExits)) {
    		$outBound = new OutboundApi;
    	} else {
    		$outBound = new OutboundApi;
    		$outBound->parent_id = $checkExits['_id'];
    	}
    	try {

	    	$v_id = $params['v_id'];
	    	$store_id = $params['store_id'];
	    	$audit_plan_id = $params['audit_plan_id'];
	    	$client_id = $params['client_id'];

	    	$error_for = 'v_id: '.$v_id. ' store_id: '.$store_id.' audit_plan_id: '.$audit_plan_id.' client_name: Ginesys';
            JobdynamicConnection($v_id);
            $currecy=getStoreAndClientCurrency($v_id,$store_id);
	          if($currecy['status']=='error'){
	              $error_msg = $extrarate['message'];
	              $outBound->error_before_call = $error_msg;
	              $outBound->save();
	              Log::error($error_msg);

	              return [ 'error' => true , 'message' => $error_msg ];
	              exit;

	          }else{

	           $source_currency = $currecy['store_currency'];
	           $target_currency = $currecy['client_currency']; 


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
			$client = OauthClient::where('id', $client_id)->first();

			$outBoundEntryExists = OutboundApi::where([ 'v_id' => $v_id, 'client_id' => $client_id, 'for_transaction' => 'Stock Aduit Push', 'transaction_id' => $audit_plan_id ])->first();
	    	if(!empty($outBoundEntryExists)) {
	    		$outBound->parent_id = $outBoundEntryExists->_id;
	    	}

			$outBound->v_id = (int)$v_id;
			$outBound->store_id = (int)$store_id;
	    	$outBound->client_id = $client_id;
	    	$outBound->client_name = $client->name;
	    	$outBound->for_transaction = 'Stock Aduit Push';
	    	$outBound->transaction_id = $audit_plan_id;
	    	$outBound->event_class = isset($params['event_class'] )?$params['event_class']:'';
	    	$outBound->job_class = isset($params['job_class'])?$params['job_class']:'';
	    	$outBound->save();

			$vendor = Organisation::select('ref_vendor_code')->where('id', $v_id)->first();
			if(!$vendor){

				$error_msg = 'Stock Aduit Error: '.$error_for.'- Vendor , Message: Unable to find Vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;

			}elseif($vendor->ref_vendor_code =='' || $vendor->ref_vendor_code ==null){

				$error_msg = 'Stock Aduit Error: '.$error_for.'- vendor , Message: Unable to find Mapping of vendor ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();

	    		return ['error' => true , 'message' => $error_msg ];
				exit;
			}
                          
	    	$store = Store::select('store_reference_code','state_id','gst')->where('v_id',$v_id)->where('store_id',$store_id)->first();

	    	if(!$store){
				$error_msg = 'Stock Aduit Error: '.$error_for.'- Store , Message: Unable to find stores ';
	    		Log::error( $error_msg );

	    		$outBound->error_before_call = $error_msg;
				$outBound->save();
	    		return ['error' => true , 'message' => $error_msg ];
			  exit;
			}elseif($store->store_reference_code =='' || $store->store_reference_code ==null){

				$error_msg = 'Stock Aduit Error: '.$error_for.'- Store , Message: Unable to find Mapping of store ';
	    		Log::error( $error_msg );
	    		$outBound->error_before_call = $error_msg;
				$outBound->save();
	    		return ['error' => true , 'message' => $error_msg ];
				exit;
			}

			$storeCode = (int)$store->store_reference_code;
			$auditPlan           = AuditPlan::where('id',$audit_plan_id)->first();         
            $auditPlanAllocation = AuditPlanAllocation::where('audit_plan_id',$auditPlan->id)
                                                        ->where('store_id',$store_id)
                                                       ->first();
            $remarks=null;
			$request = [];
			$request['referenceNo']    = $auditPlanAllocation->plan_allocation_code;
			$request['storeId']        = $storeCode;  //int
			$request['journalName']    = $auditPlan->name;
			$request['planName']       = $auditPlan->name;  
			$request['bookStockDate']  = $auditPlanAllocation->activated_date;  //string 2019-11-28T10:23:59.895Z
			$request['auditStartDate'] = $auditPlanAllocation->start_date;  //string 2019-11-28T10:23:59.895Z
			$request['auditEndDate']   = $auditPlanAllocation->due_date;  //string 2019-11-28T10:23:59.895Z
			$request['remarks']        = $remarks;  //string
			$request['postStock']      = (int)$auditPlan->is_reconciliation;  //string

            $auditGroup=AuditCountGroup::select('id')
			                             ->where('audit_plan_id',$auditPlanAllocation->audit_plan_id)
			                             ->where('audit_plan_allocation_code',$auditPlanAllocation->plan_allocation_code)
			                             ->where('store_id',$store_id)
			                             ->get();
            $auditGroupDetails=AuditCountGroupDetail::join('stock_points','stock_points.id','audit_count_group_details.stock_point_id')
                                             ->where('audit_count_group_details.audit_plan_id',$auditPlanAllocation->audit_plan_id)
                                             ->whereIN('audit_count_group_details.audit_count_group_id',$auditGroup)
                                             ->where('audit_count_group_details.store_id',$store_id)
                                             ->get();
            foreach ($auditGroupDetails as $key => $auditGroupDetail) {

	                $physicalQty        = (float)$auditGroupDetail->physical_qty;
	                $bookQty            = (float)$auditGroupDetail->system_qty;
	                $differenceQty      = $physicalQty- $bookQty;
	                $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $auditGroupDetail->barcode)->first();
	                if($bar){
		            	$item               = VendorSku::where('vendor_sku_detail_id',$bar->vendor_sku_detail_id)->first();
		            	$item->barcode = $bar->barcode;

	                }
		            $vendorItems        = VendorItems::where('v_id',$v_id)->where('item_id',$item->item_id)->first();
		            $cartconfig         = new CartconfigController;
		            $params             = ['v_id'=>$v_id,'store_id'=>$store_id,'item'=>$item,'unit_mrp'=>0];
		            $price              = $cartconfig->getprice($params);
		            $mrp                = !empty($price['s_price'])?$price['s_price']:$price['unit_mrp']; 
		            $rsp                = !empty($price['r_price'])?$price['r_price']:$price['unit_mrp'];
                    $exchangeMrp=getExchangeRate($v_id,$source_currency,$target_currency,$mrp);
                    $exchangeRsp=getExchangeRate($v_id,$source_currency,$target_currency,$rsp);

					$stockAuditItems[]  = [
							                  'stockpointId'=> (int) $auditGroupDetail->ref_stock_point_code,
										      'itemId'=> (string)$vendorItems->ref_item_code,
										      'division'=> $item->cat_name_1,
										      'section'=> $item->cat_name_2,
										      'department'=> $item->cat_name_3,
										      'bookQty'=> (float)$bookQty,
										      'physicalQty'=> (float)$physicalQty,
										      'differenceQty'=>(float)$differenceQty,
										      'rate'=> (float)$exchangeMrp['amount'],
										      'mrp'=> (float)$exchangeMrp['amount'],
										      'rsp'=> (float)$exchangeRsp['amount']
					                      ];         
           }
           $request['stockAuditItems']  = $stockAuditItems;                            

           		$outBound->api_request = json_encode($request);
          		$outBound->save();
				// dd(json_encode($request));
                $config = new ConfigController($v_id);
				$apiCaller = new  ApiCallerController([
					'url' => $config->apiBaseUrl.'/StockAuditJournal',
					'data'=> $request, 
					'header' => [ 'Content-Type:application/json'],
					'auth_type' => $config->authType,
					'auth_token' => $config->authToken,
				]);
				# extract the body
				$response = $apiCaller->call();
		        $outBound->api_response = $response['body'];
		        $outBound->response_status_code = $response['header_status'];
		        $outBound->doc_no = $auditPlanAllocation->plan_allocation_code;
		        $outBound->save();

		        // Sync Status
		        if(in_array($response['header_status'], [200, 201])) {
		        	$auditPlanAllocation->sync_status = '1';
		        	$auditPlanAllocation->save();
		        } else {
		        	$auditPlanAllocation->sync_status = '2';
		        	$auditPlanAllocation->save();
		        }
                return $config->handleApiResponse($response); 
				
		
		}catch (\Exception $e){

			$outBound->error_before_call = $e->getMessage().'\n'.$e->getTraceAsString();
			$outBound->save();

			$error_msg = 'Stock Aduit Push Error: Error for- '.$error_for.' Message: unexcepted error';
			Log::error( $error_msg);
			Log::error($e);

			return ['error' => true , 'message' => $error_msg ];
		
		}
    
    
   }  

}
