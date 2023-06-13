<?php

namespace App\Http\Controllers\Erp\Ginesys;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiCallerController;
use App\Http\Traits\VendorFactoryTrait;
use App\Model\Stock\StockPoints;
use App\Model\Stock\StockPointHeader;
use App\Model\Stock\OpeningStock;
use App\Model\Stock\OpeningStockDetails;
use App\Model\Items\VendorItems;
use Log;
use DB;

class OpeningStockController extends Controller
{
    


  private $config = null;

    public function __construct()
    {
        //$this->config = new ConfigController;
    }


     public function OpeningStockPush($params)
     {
     	//dd($params);
        $id = null;
        $outBound = $params['outBound'];
        $v_id = $params['v_id'];
        $store_id = $params['store_id'];
        $client_id = $params['client_id'];
        $os_id = $params['os_id'];
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

				$OpeningStock=OpeningStock::where('v_id',$v_id)
                      ->where('store_id',$store_id)
                      ->where('id',$os_id)
                       ->first(); 
        //dd($OpeningStock); 
        if($OpeningStock){
             // $stockPoints=StockPoints::where('id',$OpeningStock->stock_point_id)->first(); 
            $StockpointhdId =  StockPoints::select('stock_point_header_id')->where('id',$OpeningStock->stock_point_id)->first();

            $stockPoints =  StockPointHeader::select('ref_stock_point_header_code')->where('id', $StockpointhdId->stock_point_header_id)->first();
             // /dd($stockPoints);
             if($stockPoints && $stockPoints->ref_stock_point_header_code!=null &&$stockPoints->ref_stock_point_header_code!=''){
             $stockpointId =$stockPoints->ref_stock_point_header_code;
             
             $CalculateRate = 1;
  
            }else{
                 $error_msg = 'Opening Stock Error: ' . $error_for . '-Stock Point code, Message: Unable to find  stock Point code';
                $outBound->error_before_call = $error_msg;
                $outBound->save();
                Log::error($error_msg);
                return ['error' => true, 'message' => $error_msg];
               exit();
               }
         }else{

             $error_msg = 'Opening Stock Error: ' . $error_for . '-Store data, Message: Unable to find  store data';
            $outBound->error_before_call = $error_msg;
            $outBound->save();
            Log::error($error_msg);
            return ['error' => true, 'message' => $error_msg];
            exit();
        }
       
          $date  = date('Y-m-d H:i:s',strtotime($store->store_active_date.'-1 day'));
          $docDate = str_replace(' ', 'T', $date);
          $request = [];
    		  $request['siteId']= (int)$store->store_reference_code;
    		  $request['openingDate']= $docDate;
    		  $request['stockpointId']=(int)$stockpointId;
          $request['CalculateRate']=(int)$CalculateRate;
          $openingStockDetails=OpeningStockDetails::leftjoin('vendor_items',function($join){
                                                          $join->on('vendor_items.item_id','=','opening_stocks_details.item_id')
                                                               ->on('vendor_items.v_id','=','opening_stocks_details.v_id');
                                                    })
                                                    ->where('opening_stocks_details.v_id',$v_id)
                                                    ->where('opening_stocks_details.store_id',$store_id)
                                                    ->where('opening_stocks_details.opening_stock_id',$os_id)
                                                   ->get();
                    foreach ($openingStockDetails as  $items) 
                    {            
                      //dd($items);
                      $supply_price= getExchangeRate($v_id,$source_currency,$target_currency,$items->supply_price);  
                      $request['openingStockItems'][]= [
                      'itemId'=> $items->ref_item_code,
                      'quantity'=>(float)$items->quantity,
                      'rate'=>(float)$supply_price['amount'],
                       ];                            
                                     # code...
                    }
          $outBound->api_request = json_encode($request);
          $outBound->save();

          $config = new ConfigController($v_id);                             
          $apiCaller = new ApiCallerController(['url' => $config->apiBaseUrl . '/OpeningStock', 
                                                'data' => $request, 
                                                'header' => ['Content-Type:application/json'], 
                                                'auth_type' => $config->authType, 
                                                'auth_token' => $config->authToken
                                              ]);
          # extract the body
          $response = $apiCaller->call();
          $outBound->api_response = isset($response['body'])?$response['body']:'';
          $outBound->response_status_code = $response['header_status'];
          $outBound->save();

          // Sync Status
          if(in_array($response['header_status'], [200, 201])) {
            $OpeningStock->sync_status = '1';
            $OpeningStock->save();
          } else {
            $OpeningStock->sync_status = '2';
            $OpeningStock->save();
          }
          
          return $config->handleApiResponse($response); 
      
     }




}
