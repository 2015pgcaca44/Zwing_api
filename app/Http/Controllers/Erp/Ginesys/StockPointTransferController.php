<?php
namespace App\Http\Controllers\Erp\Ginesys;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ApiCallerController;
use App\Http\Traits\VendorFactoryTrait;
use App\Model\Stock\StockPoints;
use App\StockPointTransfer;
use App\StockPointTransferDetail;
use Log;
use DB;

class StockPointTransferController extends Controller
{

    private $config = null;

    public function __construct()
    {
        //$this->config = new ConfigController;
    }

    public function itemStockTransfer($params)
    {

        $id = null;
        $outBound = $params['outBound'];
        $v_id = $params['v_id'];
        $store_id = $params['store_id'];
        $client_id = $params['client_id'];
        $spt_id = $params['spt_id'];
        $client = $params['client'];
        $error_for = $params['error_for'];
        $store = $params['store'];
        $vendor = $params['vendor'];
        JobdynamicConnection($v_id);  
        $stockPointTransfer = StockPointTransfer::Join('stock_points', 'stock_point_transfers.origin_stockpoint_id', 'stock_points.id')
        ->Join('stock_points as sd', 'stock_point_transfers.destination_stockpoint_id', 'sd.id')
            ->join('stock_point_header', 'stock_point_header.id', 'stock_points.stock_point_header_id')
            ->join('stock_point_header as sph', 'sph.id', 'sd.stock_point_header_id')
            ->select('stock_point_transfers.id', 'stock_point_header.ref_stock_point_header_code as origin_stock_point_code', 'sph.ref_stock_point_header_code as destianton_stock_point_code', 'stock_point_transfers.doc_no', 'stock_point_transfers.remarks', 'stock_point_transfers.created_at')
            ->where('stock_point_transfers.v_id', $v_id)->where('stock_point_transfers.store_id', $store_id)->where('stock_point_transfers.id', $spt_id)->first();

        if ($stockPointTransfer->origin_stock_point_code == null || $stockPointTransfer->destianton_stock_point_code == null || $stockPointTransfer->doc_no == null)
        {
            $error_msg = 'Stock Point Transfer Error: ' . $error_for . '-Stock Point code, Message: Unable to find  stock Point code';
            $outBound->error_before_call = $error_msg;
            $outBound->save();
            Log::error($error_msg);

            return ['error' => true, 'message' => $error_msg];
            exit();

        }
        else
        {
            // $inStockpointId = $stockPointTransfer->origin_stock_point_code;
            // $outStockpointId = $stockPointTransfer->destianton_stock_point_code;
            $inStockpointId = $stockPointTransfer->destianton_stock_point_code;
            $outStockpointId = $stockPointTransfer->origin_stock_point_code;
            $docNo = $stockPointTransfer->doc_no;
            $docDate = $stockPointTransfer->created_at;
            // $remarks = $stockPointTransfer->remarks;
            $remarks = "test";

        }
        $request = [];

        $request['storeId'] = (int)$store->store_reference_code; // Int
        $request['docNo'] = (string)$docNo;
        $request['docdate'] = $docDate;
        $request['inStockpointId'] = (int)$inStockpointId;
        $request['outStockpointId'] = (int)$outStockpointId;
        $request['remarks'] = $remarks; // String

        $stockPointTransferDetails = StockPointTransferDetail::leftjoin('vendor_items',function($join){
                                                               $join->on('vendor_items.item_id','=','stock_point_transfer_details.item_id')
                                                               ->on('vendor_items.v_id','=','stock_point_transfer_details.v_id');
                                                              })
                                                             ->where('stock_point_transfer_details.store_id', $store_id)
                                                             ->where('stock_point_transfer_details.v_id', $v_id)
                                                             ->where('stock_point_transfer_details.stock_point_transfer_id', $spt_id)
                                                             ->get();
        foreach ($stockPointTransferDetails as $stockPointTransferDetail)
        {
            $request['stockPointTransferItem'][] = ['itemId' =>(string)$stockPointTransferDetail->ref_item_code, 
                                                     'qty' => (float) $stockPointTransferDetail->qty, 
                                                   ];
        }

        $outBound->api_request = json_encode($request);
        $outBound->save();

        $config = new ConfigController($v_id);
        $apiCaller = new ApiCallerController(['url' => $config->apiBaseUrl . '/StockPointTransfer', 
                                              'data' => $request, 
                                              'header' => ['Content-Type:application/json'], 
                                              'auth_type' => $config->authType, 
                                              'auth_token' => $config->authToken
                                            ]);
        # extract the body
        $response = $apiCaller->call();
        $outBound->api_response = $response['body'];
        $outBound->response_status_code = $response['header_status'];
        $outBound->doc_no = $docNo;
        $outBound->save();

        // Sync Status
        if(in_array($response['header_status'], [200, 201])) {
            $stockPointTransfer->sync_status = '1';
            $stockPointTransfer->save();
        } else {
            $stockPointTransfer->sync_status = '2';
            $stockPointTransfer->save();
        }

        return $config->handleApiResponse($response);

        // if(json_encode($response)){
        // }
        // dd(json_decode($response));
        //return $response;
        
    }

}

