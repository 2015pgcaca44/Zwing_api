<?php

namespace App\Http\Controllers\Erp\Ginesys;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiCallerController;
use App\Http\Traits\VendorFactoryTrait;
use App\Model\Stock\StockPoints;
use App\Model\Stock\StockPointHeader;
use App\Model\Stock\Adjustment;
use App\Model\Stock\AdjustmentDetails;
use App\Model\Stock\StockAdjustment;
use DB;
use Log;

class StockAdjustmentController extends Controller
{
  
    private $config = null;

    public function __construct()
    {
        //$this->config = new ConfigController;
    }


     public function posMisPush($params)
     {
     	//dd($params);
        $id = null;
        $outBound = $params['outBound'];
        $v_id = $params['v_id'];
        $store_id = $params['store_id'];
        $client_id = $params['client_id'];
        $adj_id = $params['adj_id'];
        $client = $params['client'];
        $error_for = $params['error_for'];
        $store = $params['store'];
        $vendor = $params['vendor'];
        JobdynamicConnection($v_id);
        $currecy=getStoreAndClientCurrency($v_id,$store_id);
          if($currecy['status']=='error'){
              $error_msg = $currecy['message'];
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
        // $adjustment=Adjustment::join('stock_points','stock_points.id','=','adjustments.stock_point_id')
        //                             ->select('adjustments.id','adjustments.doc_no','adjustments.remark','stock_points.ref_stock_point_code','adjustments.created_at'
        //                                )
        //                             ->where('adjustments.v_id',$v_id)
				    //                 ->where('adjustments.store_id',$store_id)
				    //                 ->where('adjustments.id',$adj_id)
				    //                 ->first();
				                    
        // if(isset($adjustment) && $adjustment->ref_stock_point_code!=null){

        //      $docNo = $adjustment->doc_no;
        //      $stockpointId =$adjustment->ref_stock_point_code;
        //      $docDate  =  $adjustment->created_at;
        //      $remark  = $adjustment->remark;

        $adjustment=Adjustment::join('stock_points','stock_points.id','=','adjustments.stock_point_id')
                                    ->select('adjustments.id','adjustments.doc_no','adjustments.remark','stock_points.stock_point_header_id','adjustments.created_at'
                                       )
                                    ->where('adjustments.v_id',$v_id)
                            ->where('adjustments.store_id',$store_id)
                            ->where('adjustments.id',$adj_id)
                            ->first();

          $stockPoint =  StockPointHeader::select('ref_stock_point_header_code')->where('id', $adjustment->stock_point_header_id)->first();

        if(isset($adjustment) && $stockPoint->ref_stock_point_header_code!=null){

             $docNo = $adjustment->doc_no;
             $stockpointId =$stockPoint->ref_stock_point_header_code;
             $docDate  =  $adjustment->created_at;
             $remark  = $adjustment->remark;  

        }else{

             $error_msg = 'Stock Adjustments Error: ' . $error_for . '-Stock Point code, Message: Unable to find  stock Point code';
            $outBound->error_before_call = $error_msg;
            $outBound->save();
            Log::error($error_msg);
            return ['error' => true, 'message' => $error_msg];
            exit();
        }
        
        $request = [];

        // $request['storeId'] = (int)$store->store_reference_code; // Int
        // $request['billNo'] = (string)$docNo;
        // $request['billdate'] = $docDate;
        // $request['inStockpointId'] = (int)$inStockpointId;
        // $request['outStockpointId'] = (int)$outStockpointId;
        // $request['remarks'] = $remarks; // String


		  $request['siteId']= (int)$store->store_reference_code;
		  $request['docNo']= $docNo;//string,
		  $request['docDate']= $docDate;
		  $request['stockpointId']=(int)$stockpointId;
		  $request['remarks']=$remark; //string,
		  $request['refNo']= $id;//string,
		  $request['udfString1']= $id;//string,
		  $request['udfString2']= $id;//string,
		  $request['udfString3']=$id; //string,
		  $request['udfString4']= $id;//string,
		  $request['udfString5']= $id;//string,
		  $request['udfString6']= $id;//string,
		  $request['udfString7']= $id;//string,
		  $request['udfString8']= $id;//string,
		  $request['udfString9']= $id;//string,
		  $request['udfString10']= $id;//string,
		  $request['udfNum1']= $id;//0,
		  $request['udfNum2']= $id;//0,
		  $request['udfNum3']=$id; //0,
		  $request['udfNum4']=$id; //0,
		  $request['udfNum5']= $id;//0,
		  $request['udfDate1']= $id;//date,
		  $request['udfDate2']= $id;//date,
		  $request['udfDate3']= $id;//date,
		  $request['udfDate4']= $id;//date,
		  $request['udfDate5']= $id;//date,
          $adjustmentDetails=AdjustmentDetails::leftjoin('vendor_items',function($join){
                                                          $join->on('vendor_items.item_id','=','adjustment_details.item_id')
                                                               ->on('vendor_items.v_id','=','adjustment_details.v_id');
                                                    })
                                                ->where('adjustment_details.v_id',$v_id)
                                                ->where('adjustment_details.store_id',$store_id)
                                                ->where('adjustment_details.adj_id',$adj_id)
                                                ->get();
           //dd($stockAdjustmentDetails);
          foreach ($adjustmentDetails as  $items) 
          {
             $supply_price=getExchangeRate($v_id,$source_currency,$target_currency,$items->supply_price);        
             $request['miscItems'][]= [
              'itemId'=> (string)$items->ref_item_code,
              'qty'=> $items->stock_type == 'IN' ? -(float)$items->qty : abs((float)$items->qty),
              'rate'=>(float)$supply_price['amount']
               ];
                                # code...
             }                  
		 
        $outBound->api_request = json_encode($request);
        $outBound->save();
           //dd(json_encode($request));
        $config = new ConfigController($v_id); 
        $apiCaller = new ApiCallerController(['url' => $config->apiBaseUrl . '/POSMisc', 
                                              'data' => $request, 
                                              'header' => ['Content-Type:application/json'], 
                                              'auth_type' => $config->authType, 
                                              'auth_token' => $config->authToken
                                            ]);
        # extract the body
        $response = $apiCaller->call();
        $outBound->api_response = $response['body'];
        $outBound->response_status_code = $response['header_status'];
        $outBound->docNo = $docNo;
        $outBound->save();

        // Sync Status
        if(in_array($response['header_status'], [200, 201])) {
          $adjustment->sync_status = '1';
          $adjustment->save();
        } else {
          $adjustment->sync_status = '2';
          $adjustment->save();
        }

        return $config->handleApiResponse($response); 
      
     }

}
