<?php

use Illuminate\Database\Seeder;
use App\InvoiceDetails;
use App\Invoice;
use App\Model\Stock\StockOut;
use App\Model\Stock\StockIn;
use App\Model\Stock\StockLogs;
use App\Model\Stock\StockPoints;
use App\Model\Stock\StockPointSummary;
use App\Http\Controllers\StockController;
use App\Http\Controllers\CloudPos\CartconfigController;
use App\Model\Items\VendorSku;
use App\Model\Items\VendorItem;
use App\Model\Items\VendorSkuDetailBarcode;


class updateStockFromInvoice extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
	echo $v_id     = 89;	
	JobdynamicConnection($v_id);
    $datebetween = ['2021-06-14','2021-06-24'];
    $invoices = InvoiceDetails::where('v_id',$v_id)->whereBetween('date',$datebetween)->orderby('date','ASC')->get();

	$cartconfig  = new CartconfigController;

    	foreach ($invoices as $invoice_item) {

			$inv = Invoice::find($invoice_item->t_order_id);

			$stock_point = StockPoints::where('v_id',$v_id)->where('store_id',$invoice_item->store_id)->where('is_sellable','1')->first()->id;
			$ref_stock_point = StockPoints::where('v_id',$v_id)->where('store_id',$invoice_item->store_id)->where('code','SALE')->first()->id;

			 $bar = VendorSkuDetailBarcode::select('vendor_sku_detail_id','barcode')->where('is_active', '1')->where('v_id', $v_id)->where('barcode', $invoice_item->barcode)->first();

			$Item = VendorSku::where(['vendor_sku_detail_id' => $bar->vendor_sku_detail_id, 'v_id' => $v_id])->first();

			if($Item){

    		if($invoice_item->transaction_type == 'sales'){

	    	$checkStockOut = StockOut::where('v_id',$v_id)->where('transaction_type','SALE')->where('transaction_scr_id',$invoice_item->t_order_id)->where('barcode',$invoice_item->barcode)->first();
	    	if(!$checkStockOut){
	    		echo 'No---'.$invoice_item->barcode;

	    		

				//if($Item){
	    		
	    		
	    			 $stock_type = 'OUT';

	    			/*echo 'sale';
					$params = array('v_id' => $invoice_item->v_id, 'store_id' => $invoice_item->store_id, 'barcode' => $invoice_item->barcode, 'qty' => $invoice_item->qty, 'invoice_id' => $inv->invoice_id,'transaction_scr_id'=>$inv->id, 'order_id' => $inv->ref_order_id,'transaction_type'=>'SALE','vu_id'=>$inv->vu_id,'trans_from'=>$inv->trans_from,'created_at'=>$invoice_item->created_at,'updated_at'=>$invoice_item->updated_at );
					$cartconfig->updateStockQty($params);*/

				$stockcont = new StockController;
				$stockData = [ 'variant_sku' => $Item->sku,'sku_code' => $invoice_item->sku_code, 'barcode'=> $invoice_item->barcode,'item_id' => $Item->item_id, 'store_id' => $invoice_item->store_id, 'stock_point_id' => $stock_point, 'qty' =>  $invoice_item->qty, 'ref_stock_point_id' =>$ref_stock_point, 'grn_id' => '', 'batch_id' => $invoice_item->batch_id, 'serial_id' => $invoice_item->serial_id, 'v_id' => $invoice_item->v_id,'vu_id'=>$invoice_item->vu_id,'stock_type' => $stock_type,'transaction_type' => 'SALE','transaction_scr_id'=>$inv->id,'date'=>$invoice_item->date,'created_at'=>$invoice_item->created_at,'updated_at'=>$invoice_item->updated_at];
				$stockOutData = $stockData;

				unset($stockOutData['type']);
				unset($stockOutData['stock_type']);
				unset($stockOutData['date']);

				$stockOutData['status'] = 'POST';
				if($stock_type == 'OUT'){
					$stockData['qty'] = -1*abs($stockData['qty']);
				}
				$stockData['transaction_scr_id'] = StockOut::create($stockOutData)->id;
				$stock         = StockLogs::create($stockData);

					$stockData['stock_point_id'] = $stockData['ref_stock_point_id'];
					$data                        = $stockData;
					/*$stockcont->stockPointSummary($stockData);*/

					$serial_id      = empty($data['serial_id'])?0:$data['serial_id'];
					$batch_id       = empty($data['batch_id'])?0:$data['batch_id'];
					$stockPointItem = StockPointSummary::where(['v_id'=>$data['v_id'],'store_id'=>$data['store_id'],'stock_point_id'=>$data['stock_point_id'],'item_id'=>$data['item_id'],'variant_sku'=>$data['variant_sku'],'batch_id'=>$batch_id,'serial_id'=>$serial_id])->first();
					if($stockPointItem){
						$stockPointItem->qty  += (float)$data['qty'];
						$stockPointItem->qty   = (string)$stockPointItem->qty;
						$stockPointItem->save(); 
					}else{
						$stockPointData = array('v_id'=>$data['v_id'],'store_id'=>$data['store_id'],'stock_point_id'=>$data['stock_point_id'],'item_id'=>$data['item_id'],'variant_sku'=>$data['variant_sku'], 'sku_code' => @$data['sku_code'] , 'barcode'=>$data['barcode'],'qty'=>(string)$data['qty'],
						'batch_id'=>$batch_id,'batch_code'=>@$data['batch_code'],'serial_id'=>@$serial_id,'serial_code'=>@$data['serial_code']);
						StockPointSummary::create($stockPointData);
					}

				 
					/*$stockRequest = new \Illuminate\Http\Request();
					$stockRequest->merge([
					'v_id'          => $invoice_item->v_id,
					'stockData'     => $data,
					'store_id'      => $invoice_item->store_id,
					'trans_from'    => $inv->trans_from,
					'vu_id'         => $inv->vu_id,
					'transaction_type' => 'SALE'
					]);

					$stockcont->stockOut($stockRequest);*/


	    		}
	    		
	    		//}

	    	}
	    	 if($invoice_item->transaction_type == 'return'){
	   			echo  $stock_type = 'IN';
				 $stockcont  = new StockController;
				 $ref_stock_point = 0;

				 $checkStockIn = StockIn::where('v_id',$v_id)->where('transaction_type','RETURN')->where('transaction_scr_id',$invoice_item->t_order_id)->where('barcode',$invoice_item->barcode)->first();

				 if(!$checkStockIn){
				 	$stockData = [ 'variant_sku' => @$Item->sku,'sku_code' => $invoice_item->sku_code, 'barcode'=> $invoice_item->barcode,'item_id' => $Item->item_id, 'store_id' => $invoice_item->store_id, 'stock_point_id' => $stock_point, 'qty' =>  $invoice_item->qty, 'ref_stock_point_id' =>$ref_stock_point, 'grn_id' => '', 'batch_id' => $invoice_item->batch_id, 'serial_id' => $invoice_item->serial_id, 'v_id' => $invoice_item->v_id,'vu_id'=>$invoice_item->vu_id,'stock_type' => $stock_type,'transaction_type' => 'RETURN','transaction_scr_id'=>$inv->id,'date'=>$invoice_item->date,'created_at'=>$invoice_item->created_at,'updated_at'=>$invoice_item->updated_at];


				  $stockInData = $stockData;

				unset($stockInData['type']);
				unset($stockInData['stock_type']);
				unset($stockInData['date']);

				$stockInData['status'] = 'POST';
				 
				$stockData['transaction_scr_id'] = StockIn::create($stockInData)->id;
				echo $stock         = StockLogs::create($stockData);


				$stockData['stock_point_id'] = $stockData['ref_stock_point_id'];
				$data                        = $stockData;
				/*$stockcont->stockPointSummary($stockData);*/

				$serial_id      = empty($data['serial_id'])?0:$data['serial_id'];
				$batch_id       = empty($data['batch_id'])?0:$data['batch_id'];
				$stockPointItem = StockPointSummary::where(['v_id'=>$data['v_id'],'store_id'=>$data['store_id'],'stock_point_id'=>$data['stock_point_id'],'item_id'=>$data['item_id'],'variant_sku'=>$data['variant_sku'],'batch_id'=>$batch_id,'serial_id'=>$serial_id])->first();
				if($stockPointItem){
				$stockPointItem->qty  += (float)$data['qty'];
				$stockPointItem->qty   = (string)$stockPointItem->qty;
				$stockPointItem->save(); 
				}else{
				$stockPointData = array('v_id'=>$data['v_id'],'store_id'=>$data['store_id'],'stock_point_id'=>$data['stock_point_id'],'item_id'=>$data['item_id'],'variant_sku'=>$data['variant_sku'], 'sku_code' => @$data['sku_code'] , 'barcode'=>$data['barcode'],'qty'=>(string)$data['qty'],
				'batch_id'=>$batch_id,'batch_code'=>@$data['batch_code'],'serial_id'=>@$serial_id,'serial_code'=>@$data['serial_code']);
				StockPointSummary::create($stockPointData);
				}
				 }
				 
	




	    			/*echo 'return';

					$params = array('v_id' => $invoice_item->v_id, 'store_id' => $invoice_item->store_id, 'barcode' => $invoice_item->barcode, 'qty' => $invoice_item->qty, 'invoice_id' => $inv->invoice_id,'transaction_scr_id'=>$inv->id, 'order_id' => $inv->ref_order_id,'transaction_type'=>'RETURN','vu_id'=>$inv->vu_id,'trans_from'=>$inv->trans_from );
					$cartconfig->updateStockQty($params);*/

	    		
	    	}
	     

	    	/*else{
				echo 'Yes---'.$invoice_item->barcode;
	    	}*/
    	}else{
    		echo '########';
    		echo $invoice_item->item_id;
    		echo '########'; 
    	}

    }
}

##### When barcode not insert in stock point summary #############################
##																				 #
##  UPDATE  stock_point_summary AS sps JOIN `vendor_sku_detail_barcodes` AS vsdb #
## ON sps.sku_code = vsdb.sku_code SET sps.barcode=vsdb.barcode;				 #
##################################################################################




}
