<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Organisation;
use App\Items\VendorSkuDetails;
use App\Model\Stock\StockCurrentStatus;
use App\Model\Stock\StockPointSummary;
use App\Store;
use App\Model\Store\StoreItems;

use Log;

class SchedulerController extends Controller
{
	public function makeOpeningStock()
	{
		Log::info('Open');
		echo $v_id     = 79;	
		JobdynamicConnection($v_id);
		$organisation = Organisation::select('id')->where('id', $v_id)->where('status', 1)->where('db_structure', '2')->get();
		
		foreach ($organisation as $vkey => $value) {
			Log::info('Organisation');
			$storeList = Store::select('store_id')->where('status', 1)->where('api_status', 1)->where('v_id', $value->id)->where('d_status', 0)->get();
			foreach ($storeList as $skey => $store) {
				Log::info('Store');
				
				//$productList = StoreItems::select('item_id','variant_sku','barcode')->where('deallocate_status', '0')->where('v_id', $value->id)->where('store_id', $store->store_id)->get();

				$productList = StockPointSummary::select('item_id','variant_sku','barcode')->where('v_id', $value->id)->where('store_id', $store->store_id)->get();
				foreach ($productList as $pkey =>  $product) {
					Log::info('Product');
					$product->v_id = $value->id;
					$product->store_id = $store->store_id;
					$this->createCurrentStockEntry($product);
					unset($productList[$pkey]);
				}
				unset($storeList[$skey]);
			}
			unset($organisation[$vkey]);
		}
	}

	public function createCurrentStockEntry($data)
	{
		//$date = date('Y-m-d');
		//$date  = date('2021-06-14');

		$dateArr = array('2021-06-14','2021-06-15','2021-06-16','2021-06-17','2021-06-18','2021-06-19','2021-06-20','2021-06-21','2021-06-22','2021-06-23','2021-06-24');

		foreach ($dateArr as $date) {
			 

		$arrayData = collect($data)->forget('barcode')->toArray();
		$checkEntryExists = StockCurrentStatus::whereDate('for_date', $date)->where($arrayData)->first();
		if(empty($checkEntryExists)) {
			$getPastStockStatus = StockCurrentStatus::where($arrayData)->orderBy('for_date', 'DESC')->first();
			if(!empty($getPastStockStatus)) {
                $openingStock = $getPastStockStatus->opening_qty + $getPastStockStatus->int_qty - $getPastStockStatus->out_qty;
                Log::info('Product Data'. json_encode($getPastStockStatus->item_id));
            } else {
                $openingStock = 0;
            }

            $skuD  =  VendorSkuDetails::select('sku_code')
            ->where('v_id', $arrayData['v_id'])
            ->where('item_id', $arrayData['item_id'])
            ->where('sku', $arrayData['variant_sku'])
            ->first();

            // Log::info('Product Data'. json_encode($data->barcode));
            StockCurrentStatus::create([
                'item_id' => $arrayData['item_id'],
                'variant_sku' => $arrayData['variant_sku'],
                'sku_code' => $skuD->sku_code,
                'barcode'  =>  $data->barcode,
                'store_id' => $arrayData['store_id'],
                'v_id' => $arrayData['v_id'],
                'for_date' => $date,
                'opening_qty' => $openingStock,
                'out_qty' => 0,
                'int_qty' => 0,
                'grn_qty' => 0,
                'adj_qty' => 0,
                'sale_qty' => 0,
                'return_qty' => 0,
                'transfer_out_qty' => 0
            ]);

			}
		}
	}
}
