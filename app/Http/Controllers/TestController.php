<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use DB;
use App\Cart;
use App\TempCart;
use App\VendorSetting;
use App\Invoice;
use App\Order;
use App\Payment;
use App\Http\Controllers\Ginesys\CartController;
use App\Http\Controllers\CloudPos\CartController as CloudCart;
use App\OrderDetails;
use App\Http\Controllers\Loyality\EaseMyRetailController;
use App\Http\Controllers\OrderController;
use Carbon\Carbon;
use App\Model\Stock\StockLogs;
use App\Model\Stock\StockCurrentStatus;
use App\Model\Stock\StockTransactions;
use App\Model\Stock\StockIn;
use App\Model\Stock\StockOut;
use App\Model\Stock\StockIntransit;
use App\Model\Stock\StockAdjustment;
use App\InvoiceDetails;
 use App\Model\Items\VendorSkuDetails;    
 use App\Model\Items\VendorSku;
use App\Model\Grn\Grn;
use App\Model\Grn\GrnList;
use App\Model\Grn\GrnBatch;
use App\Model\Grn\GrnSerial;
use App\Model\Grn\AdviseList;
use Event;
use App\Events\InvoiceCreated;
use App\Events\PacketCreated;
use App\Events\GrtCreated;
use App\Events\GrnCreated;
use App\Events\StockTransfer;
use App\Events\DaySettlementCreated;
use App\Events\CashPointTransfer;
use App\Events\StockAdjust;
use App\Events\DepositeRefund;
use App\Jobs\FlatProduct;
use App\Mail\IntegrationSyncStatus;
use Illuminate\Support\Facades\Mail;
use App\FailedSyncReports;
use App\Model\OutboundApi;
use App\StockPointTransfer;
use App\Packet;
use App\GrtHeader;
use App\Model\Stock\Adjustment;
use App\CashTransaction;
use App\StoreExpense;

class TestController extends Controller
{
	public function __construct()
	{ 
		// $this->todb = DB::connection('mysql');
	}

	public function testJob(){

		
		// $productData = [ 'v_id' => 127, 'dbname' => 'ZW_MN_127_pentagon', 'product_id' => [123] ];
		// dispatch(new FlatProduct($productData) );
		

		// $invoice = new \App\Http\Controllers\Erp\InvoiceController;

		//$invoice->depositeRefund(['v_id' =>148 , 'store_id' => 228 , 'payment_id' =>15826, 'client_id' => '1','type' => 'SALE' ]);
		
		//$invoice->InvoicePush(['v_id' =>148 , 'store_id' => 228 , 'invoice_id' =>14544, 'client_id' => '1','type' => 'SALE' ]);
		
						
        /*event(new DepositeRefund([
            'payment_id' => '15853',
            'v_id' => '148',
            'store_id' => '228',
            'db_structure' => '2',
            'type'=>'SALES',
            'zv_id' => '<ZWINGV>148<EZWINGV>', 
		 	'zs_id' => '<ZWINGSO>228<EZWINGSO>', 
		 	'zt_id' => '<ZWINGTRAN>15848<EZWINGTRAN>'
            ] 
            )
        );*/
		// $adj = new  \App\Http\Controllers\Erp\StockAdjustmentController;
		// $response = $adj->posMisPush(['v_id' => 108 , 'store_id' => 231 ,'adj_id'=>'125','client_id' => '1']);
		
		// event(new StockAdjust([
		// 	'v_id' => 108, 
		// 	'store_id' => 231, 
		// 	'adj_id' => 124, 
		// 	'zv_id' => '<ZWINGV>108<EZWINGV>', 
		// 	'zs_id' => '<ZWINGSO>231<EZWINGSO>', 
		// 	'zt_id' => '<ZWINGTRAN>124<EZWINGTRAN>'
		//     ])
		// );  

		// $request = new \Illuminate\Http\Request(['v_id' => 84 , 'client_id' => 'VT2345RTZW87'  , 'ack_id' => '286001L4Q0007' ]);
		// $itemC = new  \App\Http\Controllers\ItemController;
		// $response = $itemC->processItemMasterCreationJob($request);
		// dd($response);

		// event(new DaySettlementCreated([
		// 	'v_id'=> 76, 
		// 	'store_id' => 465, 
		// 	'ds_id' => 75, 
		// 	'zv_id' => '<ZWINGV>76<EZWINGV>', 
		// 	'zs_id' => '<ZWINGSO>465<EZWINGSO>', 
		// 	'zt_id' => '<ZWINGTRAN>75<EZWINGTRAN>'
		// 	])
		// );
		
		// event(new CreateOpeningStock([
		// 	'v_id' => 84, 
		// 	'store_id' => 478, 
		// 	'os_id' => 7, 
		// 	'zv_id' => '<ZWINGV>84<EZWINGV>', 
		// 	'zs_id' => '<ZWINGSO>478<EZWINGSO>', 
		// 	'zt_id' => '<ZWINGTRAN>7<EZWINGTRAN>'
		//     ]

		// ));
		// dd('Opening stocck  created');

		// dd('day settlement push');


        // $packet = new  \App\Http\Controllers\Erp\PacketController;
        // $response = $packet->packetPush([
        // 	'v_id' => 148, 
        // 	'store_id' =>  229, 
        // 	'packet_id' => 124,
        // 	'client_id' => 1,

        // ]);

        // dd($response);
		// event( new PacketCreated([
		// 	'v_id' => 51,
		// 	'store_id' => 436, 
		// 	'packet_id' => 1
		// 	]) 
		// );

		// event(new GrtCreated([
		// 	'v_id' => 76, 
		// 	'store_id' => 464, 
		// 	'grt_id' => 8877,
		// 	'zv_id' => '<ZWINGV>'.'76'.'<EZWINGV>', 
		// 	'zs_id' => '<ZWINGSO>'.'464'.'<EZWINGSO>', 
		// 	'zt_id' => '<ZWINGTRAN>'.'8877'.'<EZWINGTRAN>'
		// 	])
		// ); 
		// dd('Grt job created');
		
		
		// event(new StockTransfer([
		// 	'v_id' => 108,
		// 	'store_id' => 230 , 
		// 	'spt_id' => 467, 
		// 	'zv_id' => '<ZWINGV>'.'108'.'<EZWINGV>', 
		// 	'zs_id' => '<ZWINGSO>'.'230'.'<EZWINGSO>', 
		// 	'zt_id' => '<ZWINGTRAN>'.'467'.'<EZWINGTRAN>'
		// 	])
		// );
		// die('stock transfer created');
		

		// event(new 
		// 	CashPointTransfer([
		// 		'v_id' => 108, 
		// 		'store_id' => 230, 
		// 		'cash_transaction_id' => 42,
		// 		'transfer_type' =>'2', 
		// 		'zv_id' => '<ZWINGV>'.'108'.'<EZWINGV>', 
		// 		'zs_id' => '<ZWINGSO>'.'230'.'<EZWINGSO>', 
		// 		'zt_id' => '<ZWINGTRAN>'.'42'.'<EZWINGTRAN>'
		// 	])
		// );
		// die('Cahs point Transfer');
		
		// $grnC = new \App\Http\Controllers\Erp\GrnController;
		// $response = $grnC->grnPush([
		// 	'v_id' => 75,
		// 	'store_id' => 460,
		// 	'grn_id' => 25,
		// 	'client_id' => 1,
		// 	'advice_id' => 8822,
		// 	'zv_id' => '<ZWINGV>'.'75'.'<EZWINGV>', 
		// 	'zs_id' => '<ZWINGSO>'.'460'.'<EZWINGSO>', 
		// 	'zt_id' => '<ZWINGTRAN>'.'25'.'<EZWINGTRAN>'
		// ]);

		// dd($response);

		// event(new GrnCreated([
		//           'v_id' => 75,
		//           'store_id' => 460,
		//           'grn_id' => 20,
		//           'advice_id' =>   8819,
		//           'zv_id' => '<ZWINGV>'.'75'.'<EZWINGV>', 
		// 		'zs_id' => '<ZWINGSO>'.'460'.'<EZWINGSO>', 
		// 		'zt_id' => '<ZWINGTRAN>'.'20'.'<EZWINGTRAN>'
		//           ] 
		//       ));
		// dd('Grn Created ');

		// $invoice = new  \App\Http\Controllers\Erp\InvoiceController;
		// $response = $invoice->InvoicePush([
		// 	'v_id' =>75 ,
		// 	'store_id' => 460,
		// 	'invoice_id' =>6162,
		// 	'client_id' => '1',
		// 	'type' => 'RETURN',
		// 	'zv_id' => '<ZWINGV>'.'75'.'<EZWINGV>', 
		// 	'zs_id' => '<ZWINGSO>'.'460'.'<EZWINGSO>', 
		// 	'zt_id' => '<ZWINGTRAN>'.'6162'.'<EZWINGTRAN>'
		//     ]
		// );
		// dd($response);
		// dd('invoice push');
		// event(new InvoiceCreated([
		// 	   'invoice_id' => 6162,
		// 	   'v_id' => 75,
		// 	   'store_id' => 460,
		// 	   'db_structure' =>2,
		// 	   'type'=>'SALE',
		// 	   	'zv_id' => '<ZWINGV>'.'76'.'<EZWINGV>', 
		// 		'zs_id' => '<ZWINGSO>'.'460'.'<EZWINGSO>', 
		// 		'zt_id' => '<ZWINGTRAN>'.'6162'.'<EZWINGTRAN>'
		//         ])
		//     );
		// dd('Invoice job created for 75');
	
		// $data =DB::select('SELECT id, v_id, store_id FROM ZW_MN_75_eurus_so.invoices where transaction_type ="return"');
		// // dd($data);

		// foreach($data as $dat){

		// 	event(new InvoiceCreated([
		// 		   'invoice_id' => $dat->id,
		// 		   'v_id' => $dat->v_id,
		// 		   'store_id' => $dat->store_id,
		// 		   'db_structure' =>2,
		// 		   'type'=>'RETURN',
		// 		   	'zv_id' => '<ZWINGV>'.$dat->v_id.'<EZWINGV>', 
		// 			'zs_id' => '<ZWINGSO>'.$dat->store_id.'<EZWINGSO>', 
		// 			'zt_id' => '<ZWINGTRAN>'.$dat->id.'<EZWINGTRAN>'
		// 	         ])
		// 	    );
		// 	sleep(2);

		// 	echo $dat->id ; 
		// 	echo "\n"; 
		// }

		// event(new InvoiceCreated([
		// 		   'invoice_id' => 7199,
		// 		   'v_id' => 75,
		// 		   'store_id' => 460,
		// 		   'db_structure' =>2,
		// 		   'type'=>'RETURN',
		// 		   	'zv_id' => '<ZWINGV>'.'75'.'<EZWINGV>', 
		// 			'zs_id' => '<ZWINGSO>'.'460'.'<EZWINGSO>', 
		// 			'zt_id' => '<ZWINGTRAN>'.'7199'.'<EZWINGTRAN>'
		// 	         ])
		// 	    );
		// dd('Invoice job created for 75');
		
	}
	public function testoutbondapi(){

		$invoice = new \App\Http\Controllers\Erp\InvoiceController;

		$invoice->depositeRefund(['v_id' =>148 , 'store_id' => 228 , 'payment_id' =>15826, 'client_id' => '1','type' => 'SALE' ]);
		
		
						
        event(new DepositeRefund([
            'payment_id' => '15848',
            'v_id' => '148',
            'store_id' => '228',
            'db_structure' => '2',
            'type'=>'SALES',
            'zv_id' => '<ZWINGV>148<EZWINGV>', 
		 	'zs_id' => '<ZWINGSO>228<EZWINGSO>', 
		 	'zt_id' => '<ZWINGTRAN>15848<EZWINGTRAN>'
            ] 
            )
        );

	}
	public function testSms(){
		
		// $smsC = new \App\Http\Controllers\SmsController;
		// $response =  $smsC->send_voucher(['mobile' => '9930387351' , 'voucher_amount' => 99.00, 'voucher_no' => 'RTG67' , 'expiry_date' => '2021-03-03' , 'v_id' => 127 , 'store_id' =>  230 , 'store_name' => 'Test store' ]);
		// dd($response);
	}

	public function stockOut($data)
	{
	    $stockData = $data;
	    StockOut::create($stockData);
	    $this->stockLog($stockData, 'OUT');
	}
	public function stockLog($data, $type)
	{
	     if($type == 'OUT'){
	        $data['qty'] = -1*abs($data['qty']);
	    }
	    $data['date'] = date('Y-m-d');
	    $data['stock_type']= $type;
	  
	    $stock = StockLogs::create($data);
	    //StockLogs::find($stock->id)->update([ 'stock_type' => $type ]);
	    
	    if($data['transaction_type'] != 'SPT'){
	     $this->updateCurrentStock($data);
	    }
	}

	public function updateCurrentStock($data) 
    {
        $vendor_id  = $data['v_id'];
        $store_id   = $data['store_id'];
        $variant_sku= $data['variant_sku'];
        $barcode  = $data['barcode'];
        $item_id   = $data['item_id'];
        $today     = date('Y-m-d');
        $quantity  = abs($data['qty']);
        $int_qty   = 0;
        $out_qty   = 0;
        $grn_qty   = 0;
        $adj_qty   = 0;
        $sale_qty  = 0;
        $return_qty = 0;
        $transfer_out_qty = 0;
        $transaction_type = $data['transaction_type'];
        $stock_type  = isset($data['stock_type'])?$data['stock_type']:$data['type'];

        if($transaction_type == 'GRN'){
        $grn_qty =  $quantity ;
        }
        if($transaction_type == 'ADJ'){
         $adj_qty = $quantity;
        }
        if($transaction_type == 'SALE' || $transaction_type == 'sales'){
        $sale_qty =  $quantity ;
        }
        if($transaction_type == 'RETURN'){
         $return_qty =  $quantity;
        }
        
        $todayStatus = StockCurrentStatus::select('id', 'out_qty','int_qty','grn_qty','adj_qty','sale_qty','return_qty')
            ->where('item_id', $item_id)
            ->where('variant_sku', $variant_sku)
            ->where('store_id', $store_id)
            ->where('v_id', $vendor_id)
            ->where('for_date', $today)    //Carbon::today()->toDateString()
            ->first();

            // if($stock_type == 'OUT'){

            //     echo $todayStatus->out_qty.'---';
            // echo $todayStatus->out_qty +$quantity; 
            //  die;     
            // }

        if($todayStatus) {

            if($stock_type == 'OUT'){
                $todayStatus->out_qty = $todayStatus->out_qty +$quantity;              
            }
            if($stock_type == 'IN'){
                $todayStatus->int_qty =$todayStatus->int_qty +$quantity;         
            }
            if($transaction_type == 'GRN'){
             $todayStatus->grn_qty = $todayStatus->grn_qty+$quantity ;
            }
            if($transaction_type == 'ADJ'){
                $todayStatus->adj_qty = $todayStatus->adj_qty+$quantity;
            }
            if($transaction_type == 'sales' || $transaction_type == 'SALE'){
                
                $todayStatus->sale_qty = $todayStatus->sale_qty+$quantity;
            }
            if($transaction_type == 'RETURN'){
                $todayStatus->return_qty = $todayStatus->return_qty+$quantity;
            }
            $todayStatus->barcode = $barcode;
            $todayStatus->save();
        
        } else {
            if($stock_type == 'OUT'){
             $out_qty =$quantity;
            }
            if($stock_type == 'IN' ){
             $int_qty  =$quantity;
            }
            $stockPastStatus = StockCurrentStatus::select('opening_qty', 'out_qty', 'int_qty')
                ->where('item_id', $item_id)
                ->where('variant_sku', $variant_sku)
                ->where('store_id', $store_id)
                ->where('v_id', $vendor_id)
                ->orderBy('for_date', 'DESC')
                ->first();

            if($stockPastStatus) {
                $openingStock = $stockPastStatus->opening_qty + $stockPastStatus->int_qty - $stockPastStatus->out_qty;
            } else {
                $openingStock = 0;
            }

            StockCurrentStatus::create([
                'item_id' => $item_id,
                'variant_sku' => $variant_sku,
                'barcode'  =>  $barcode,
                'store_id' => $store_id,
                'v_id' => $vendor_id,
                'for_date' => $today,
                'opening_qty' => $openingStock,
                'out_qty' => $out_qty,
                'int_qty' => $int_qty,
                'grn_qty' => $grn_qty,
                'adj_qty' => $adj_qty,
                'sale_qty' => $sale_qty,
                'return_qty' =>$return_qty,
                'transfer_out_qty' => $transfer_out_qty
             ]);
        }
    }//End of function



	/*public function stockLog($data, $type)
    {
        // if($type == 'OUT'){
        //      $data['qty'] = -1*abs($data['qty']);
        // }
        $stock = StockLogs::create($data);
        StockLogs::find($stock->id)->update([ 'stock_type' => $type ]);
       
    }*/

    public function updateCompleteStockCurrentStatus(){
    	##################################################
    	## Update Stock Current Status for a vendor   ####
    	##################################################

    	$v_id  = 47;

    }

	public function verify_order_for_test(Request $request){
		
		/*manauly update stock*/

 
		$param = array('v_id'=>18,'store_id'=>'158','stock_point_id'=>'264','qty'=>'‭483.52','stock_type'=>'IN','sku'=>'102112001','remarks'=>'Issue for Invoice no- Z7018002JCR00098: qty=484-.484=‭483.52');
		$this->stockItemAdjustmentManualy($param);
		die;
		$v_id 	  = 20;
		$store_id = 160; 
		$damage_stock_point_id = 278;
		$stocklog  = StockLogs::select('date','variant_sku',DB::Raw('sum(qty) as qty'))->where('v_id',$v_id)->where('store_id',$store_id)->where('transaction_type','SPT')->where('stock_point_id',$damage_stock_point_id)->groupBy('date','variant_sku')->get();
		foreach ($stocklog as $itemlog) {
			//echo $itemlog->variant_sku.' -- '.abs($itemlog->qty).' -- '.$itemlog->date;
			//echo '<br>';
			echo StockCurrentStatus::where('variant_sku',$itemlog->variant_sku)->where('for_date',$itemlog->date)->where('v_id',$v_id)->where('store_id',$store_id)->update(['damage_qty'=>abs($itemlog->qty)]);
		}

		echo 'Good';
		die;
		/*SST stock log update from advice using stockIntransit*/
		
		$stockIntransit  = StockIntransit::where('v_id',$v_id)->get();
		foreach ($stockIntransit as $item) {

			$advice = AdviseList::where('v_id',$v_id)->where('advice_id',$item->advice_id)->get();
			foreach ($advice as $aditem) {
					 
				$itemExist = StockLogs::where('v_id',$v_id)->where('stock_intransit_id',$item->id)->where('stock_type','OUT')->where('barcode',$aditem->item_no)->first();
				if(!$itemExist){
					echo $aditem->item_no;
					$vendorSku = VendorSkuDetails::where('v_id',$v_id)->where('barcode',$aditem->item_no)->first();
					$batch     = StockLogs::where('v_id',$v_id)->where('variant_sku',$vendorSku->sku)->orderBy('id','desc')->first();
					if($vendorSku){
					/*Stock Out Start*/
					$stockOutData = [
						'variant_sku' => $vendorSku->sku,
						'barcode' => $vendorSku->barcode,
						'item_id' => $vendorSku->item_id,
						'store_id' => $item->source_store_id,
						'stock_point_id' => '274',
						'qty' => $aditem->qty,
						'ref_stock_point_id' => '276',
						'batch_id' => $batch->batch_id,
						'serial_id' => isset($serialNumberId)?$serialNumberId:NULL,
						'v_id' => $v_id,
						'vu_id' => '292',
						'stock_intransit_id' => $item->id,
						'transaction_scr_id' => $item->id,
						'transaction_type' => 'SST'
					];
					$this->stockOut($stockOutData);
					/*Stock Out End*/
					}


				}


			}

		}



		echo 'hello';die;
		$v_id = 17;
		  $store_id = 137;
		 
		$returnLogs =  $this->updateStockLogs($v_id,$store_id);
		if($returnLogs){
			echo $this->updateStockCurrentStatus($v_id,$store_id);
		}
		die;


		$v_id = 17;

		$vendorSku = VendorSkuDetails::where('v_id',$v_id)->groupBy('item_id')->get();
		$data = array('variant_sku'=>$vendorSku->sku,'barcode'=>$vendorSku->barcode,'item_id'=>$vendorSku->item_id,'');

	 




		die;



		$v_id   =  62;
		$vendorSku = VendorSkuDetails::where('v_id',$v_id)->groupBy('item_id')->get();
		foreach($vendorSku as $sku){
			$stockLog  = StockCurrentStatus::where('v_id',$v_id)->where('item_id',$sku->item_id)->update(['variant_sku'=>$sku->sku]);
			 
		}

		echo 'sku done';

		die;



		$v_id 			= 62;
		$InvoiceDetails = InvoiceDetails::where('v_id',$v_id)->where('date','>','2019-12-07')->get();

		foreach($InvoiceDetails as $detail){

			DB::beginTransaction();
            try {
			$invoice   = Invoice::find($detail->t_order_id);
			$vendorSku = VendorSkuDetails::where('v_id',$v_id)->where('barcode',$detail->barcode)->first();
			if($vendorSku && $invoice){ 
				//Stock out
				$data =  array('v_id'=>$v_id ,'variant_sku'=>$vendorSku->sku,'item_id'=>$vendorSku->item_id,'store_id'=>$detail->store_id,'stock_point_id'=>'396','qty'=>$detail->qty,'ref_stock_point_id'=>'0','grn_id'=>'0','batch_id'=>'0','created_at'=>$detail->created_at);
				StockOut::create($data);
				$data['stock_type'] ='OUT';

				//Stock log
				StockLogs::create($data);
				
				//Stocck transaction
				$data['transaction_type'] = 'sales';
				$data['invoice_no']  = $invoice->invoice_id;
				unset($data['ref_stock_point_id']);
				StockTransactions::create($data);
 
				//Stock current
				$stockCurrentStatus = StockCurrentStatus::where('v_id',$v_id)->where('item_id',$vendorSku->item_id)->where('variant_sku',$vendorSku->sku)->orderBy('for_date','desc')->first();
				$stockCurrentStatus->out_qty += $detail->qty;
				$stockCurrentStatus->save();
				echo 'done';
			}
			DB::commit();
            } catch (Exception $e) {
                DB::rollback();
                exit;
            }

		}
		echo 'die-- ok';
		die;
		$v_id   =  62;
		$vendorSku = VendorSkuDetails::where('v_id',$v_id)->groupBy('barcode')->get();
		foreach($vendorSku as $sku){
			$stockLog  = StockLogs::where('v_id',$v_id)->where('item_id',$sku->item_id)->update(['barcode'=>$sku->barcode]);
			 
		}

		echo 'barcode done';

		die;
		$v_id   =  62;
		$stockLog = StockLogs::where('v_id',$v_id)->groupBy('barcode')->get();
		foreach($stockLog as $log){
			$vendorSku  = VendorSkuDetails::where('v_id',$v_id)->where('item_id',$log->item_id)->first();
			if($vendorSku){
				$vendorSku->sku = $log->variant_sku;
				$vendorSku->save();
			}
		}
		echo 'done';
		die;
		#############################################
		## Use for prmanent delete grn with stock ###
		#############################################
		$v_id   =  62;
		$grnId  =  array('GRN762JCB00005');
		$grns    =  Grn::whereIn('grn_no',$grnId)->where('v_id',$v_id)->get();
		foreach($grns as $grn){
			$grnList = GrnList::where('grn_id',$grn->id)->get();
			foreach($grnList as $item){
				$grnBatch   = GrnBatch::where('grnlist_id',$item->id)->delete();
				$grnSerial  = GrnSerial::where('grnlist_id',$item->id)->delete();
			}
			$grnList   = GrnList::where('grn_id',$grn->id)->delete();
			$stockLog  = StockLogs::where('grn_id',$grn->id)->where('v_id',$v_id)->get();
			if($stockLog){
				foreach ($stockLog as $value) {
					$stockCurrentStatus =  StockCurrentStatus::where('v_id',$v_id)->where('variant_sku',$value->variant_sku)->orderBy('for_date','desc')->first();
					$qty = $stockCurrentStatus->int_qty - $value->qty;
					$stockCurrentStatus->int_qty = $qty;
					$stockCurrentStatus->save();
					$stockIn   = StockIn::where('grn_id',$grn->id)->where('v_id',$v_id)->where('variant_sku',$value->variant_sku)->delete();
					$stockIn   = StockLogs::where('grn_id',$grn->id)->where('variant_sku',$value->variant_sku)->where('v_id',$v_id)->delete();
				}				
			}
			Grn::where('id',$grn->id)->where('v_id',$v_id)->delete();
		}
		echo 'Done';

		#################################################
		## Use for prmanent delete grn with stock End ###
		#################################################

		die;

		$v_id    = 19;
		$grnId   = array(256);
		$grnList = GrnList::where('v_id',$v_id)->whereIn('grn_id',$grnId)->get();
		$trans_from = 'WEB';
		$vu_id    = '0';
		 // ///dd($grnList);
		foreach ($grnList as $value) {
			$store_id = $value->store_id;
			$grnBatch = GrnBatch::where('grnlist_id', $value->id)->first();

			$grnStockPoint = StockPoints::where('v_id', $v_id)->where('store_id', $store_id)->where('is_default', '1')->first();

			$type = 'IN';
			 $is_batch = $grnBatch->batch_id;
			$data = [ 'variant_sku' => $value->Items->sku, 'item_id' => $value->Items->item_id, 'store_id' => $value->store_id, 'stock_point_id' => $grnStockPoint->id,'request_qty' => $value->request_qty, 'qty' =>  $value->qty, 'ref_stock_point_id' => 0, 'grn_id' => $value->grn_id, 'batch_id' =>$is_batch, 'serial_id' => '0', 'v_id' => $v_id, 'lost_qty' => $value->lost_qty, 'damage_qty' => $value->damage_qty, 'type' => $type,'transaction_type' => 'GRN' ];
			$stockRequest = new \Illuminate\Http\Request();
			$stockRequest->merge([
			    'v_id'          => $v_id,
			    'stockData'     => $data,
			    'store_id'      => $store_id,
			    'trans_from'    => $trans_from,
			    'vu_id'         => $vu_id
			]);
			$this->stockIn($stockRequest);

		}


		die;

        $v_id       = 64;
        $store_id   = 109;
        $barcodes    = array('NC01HTINDIGNAPKSh207','NC01HTINDIGNAPKSh307','NC01HTINDIGNAPKSh607','NC01HTSHADBLUTCSh206','NC01HTSHADBLUTCSh506');
        foreach($barcodes as $barcode){

            $invoice = InvoiceDetails::select('t_order_id','created_at',DB::Raw('sum(qty) as qty'))->where(['v_id'=>$v_id,'store_id'=>$store_id,'barcode'=>$barcode])->groupBy('t_order_id')->get();
            //dd($invoice);
            if(count($invoice) > 0){
            foreach($invoice as $inv){
            $invoice_id = Invoice::select('ref_order_id','invoice_id')->find($inv->t_order_id);
            if($inv->qty){
                $sku = VendorSkuDetails::select('item_id','sku')->where(['v_id'=>$v_id,'barcode'=>$barcode])->first();

                //1. Stock Out

                $stockOut = StockOut::where(['v_id'=>$v_id,'store_id'=>$store_id,'variant_sku'=>$sku->sku])->where(DB::raw("DATE(stock_out.created_at)"), $inv->created_at)->first();

                if(!$stockOut){
                    $shelftockPoint = StockPoints::where(['v_id'=>$v_id,'store_id'=>$store_id])->where('name','like','%shelf%')->first();
                    $salestockPoint = StockPoints::where(['v_id'=>$v_id,'store_id'=>$store_id])->where('name','like','%SALE%')->first();
                    $stockOutData = [
                                    'variant_sku' => $sku->sku,
                                    'item_id' => $sku->item_id,
                                    'store_id' => $store_id,
                                    'stock_point_id' => $salestockPoint->id,
                                    'qty' => $inv->qty,
                                    'ref_stock_point_id' => $shelftockPoint->id,
                                    'batch_id' => '',
                                    'serial_id' => '',
                                    'v_id' => $v_id,
                                  ];
                    $dataStockOut        = StockOut::create($stockOutData);
                    $stockCurrentStatus  = StockCurrentStatus::where(['v_id'=>$v_id,'store_id'=>$store_id,'variant_sku'=>$sku->sku])->orderBy('for_date','desc')->first();
                    $out_qty = $stockCurrentStatus->out_qty +$inv->qty;

                    $stockCurrentStatus->out_qty = $out_qty;
                    $stockCurrentStatus->save();


                $stocktransdata     = array(
                    'variant_sku' => $sku->sku,
                    'item_id'    => $sku->item_id,
                    'store_id'   => $store_id,
                    'stock_type' =>  'OUT',
                    'stock_point_id' => $salestockPoint->id,
                    'qty'        => $inv->qty,
                    'v_id'      =>  $v_id,
                    'order_id'  =>  $invoice_id->ref_order_id,
                    'invoice_no' =>  $invoice_id->invoice_id,
                    'transaction_type' => 'SALE'

                );
                StockTransactions::create($stocktransdata);


                $stockdata  = array(
                    'variant_sku' => $sku->sku,
                    'item_id'    => $sku->item_id,
                    'store_id'   => $store_id,
                    'stock_type' => 'OUT',
                    'stock_point_id' => $salestockPoint->id,
                    'qty'        => $inv->qty,
                    'ref_stock_point_id' => $shelftockPoint->id,
                    'v_id'      =>  $v_id,
                    //'transaction_type'      => 'sale'
                );
                StockLogs::create($stockdata);

                }
              }
            }
          }
        }
    	die;
	
		DB::table('orders')->where('order_id', $request->order_id)->update(['verify_status' => $request->verify_status,  'verify_status_guard' => $request->verify_status_guard ]);
		return response()->json(['status' => 'success', 'message' => 'Data updated successfully'],200);
	}

	  /* Stock Create and update function*/
	public function updateStockLogs($id,$store_id){
		$v_id     = $id;
		$store_id = $store_id;

		$deleteStockLogs = $this->todb->table('stock_logs')->where(['v_id'=>$v_id,'store_id'=>$store_id])->delete();
		$deleteCurrent   = $this->todb->table('stock_current_status')->where(['v_id'=>$v_id,'store_id'=>$store_id])->delete();
		$deleteCurrent   = $this->todb->table('stock_transactions')->where(['v_id'=>$v_id,'store_id'=>$store_id])->delete();

		$dateList = $this->generateDateRange(Carbon::parse('2019-11-01'), Carbon::parse('2019-12-16'));
		$saleStockPointId = $this->todb->table('stock_points')->where(['v_id'=>$v_id,'store_id'=>$store_id,'name'=>'SALE'])->first()->id;
		$grnStockPointId = $this->todb->table('stock_points')->where(['v_id'=>$v_id,'store_id'=>$store_id,'is_default'=>'1'])->first()->id;
		$refSaleStockPointId = $this->todb->table('stock_points')->where(['v_id'=>$v_id,'store_id'=>$store_id,'is_default'=>'1'])->first()->id;

		if(count($dateList) > 0) {
		  foreach ($dateList as $key => $value) {

			$invoice_details  =  $this->todb->table('invoice_details')->where('v_id',$v_id)->where('store_id',$store_id)->where('transaction_type','sales')->where('date',$value)->get();
			$data = array('v_id'=>$v_id,'store_id'=>$store_id,'vu_id'=>0,'stock_intransit_id'=>'0');
			if($invoice_details){
				foreach ($invoice_details as $invoice) {
					$sku =  $this->todb->table('vendor_sku_details')->where('v_id',$v_id)->where('barcode',$invoice->barcode)->first();
					$data['variant_sku']    = $sku->sku;
					$data['barcode']        = $invoice->barcode;
					$data['item_id']        = $sku->item_id;
					$data['stock_type']     = 'OUT';
					$data['stock_point_id'] = $refSaleStockPointId;
					$data['qty']            = -1*$invoice->qty;
					$data['ref_stock_point_id'] = $saleStockPointId;
					$data['grn_id']            = '0';
					$data['batch_id']          = '0';
					$data['serial_id']         = '0';
					$data['date']         	   = $invoice->date;
					$data['transaction_type']  = 'SALE';
					$data['transaction_scr_id']= $invoice->t_order_id;
					$data['vu_id']            = $invoice->vu_id;
					$data['created_at']		  = $invoice->created_at;
					$data['created_at']		  = $invoice->updated_at;
					$this->stockLog($data);

					$transactionData = $data;
					$transactionData['transaction_type'] ='sales';
					$transactionData['qty'] = abs($data['qty']);
					unset($transactionData['transaction_scr_id']);
					unset($transactionData['ref_stock_point_id']);
					unset($transactionData['batch_id']);
					StockTransactions::create($transactionData);



				}
			}//End of invoice detail

			$grn_details  =  $this->todb->table('grn_list')->join('grn_batch','grn_batch.grnlist_id','grn_list.id')->where('v_id',$v_id)->where('store_id',$store_id)->whereDate('created_at',$value)->get();
			if($grn_details){
				foreach ($grn_details as $grn) {
				 	$sku =  $this->todb->table('vendor_sku_details')->where('v_id',$v_id)->where('barcode',$grn->item_no)->first();
				 	$data['variant_sku']    = $sku->sku;
					$data['barcode']        = $grn->item_no;
					$data['item_id']        = $sku->item_id;
					$data['stock_type']     = 'IN';
					$data['stock_point_id'] = $grnStockPointId;
					$data['qty']            =  $grn->qty;
					$data['ref_stock_point_id'] = '0';
					$data['grn_id']            = $grn->grn_id;
					$data['batch_id']          = $grn->batch_id;
					$data['serial_id']         = '0';
					$data['date']         	   = date('Y-m-d',strtotime($grn->created_at));
					$data['transaction_type']  = 'GRN';
					$data['transaction_scr_id']= $grn->grn_id;
					$data['created_at']		  = $grn->created_at;
					$data['created_at']		  = $grn->updated_at;
					$this->stockLog($data);

				}
			}

			$return_invoice_details  =  $this->todb->table('invoice_details')->where('v_id',$v_id)->where('store_id',$store_id)->where('transaction_type','return')->where('date',$value)->get();
			if($return_invoice_details){
				foreach ($return_invoice_details as $invoice) {
					$sku =  $this->todb->table('vendor_sku_details')->where('v_id',$v_id)->where('barcode',$invoice->barcode)->first();
					$data['variant_sku']    = $sku->sku;
					$data['barcode']        = $invoice->barcode;
					$data['item_id']        = $sku->item_id;
					$data['stock_type']     = 'IN';
					$data['stock_point_id'] = $refSaleStockPointId;
					$data['qty']            =  $invoice->qty;
					$data['ref_stock_point_id'] = '0';
					$data['grn_id']            = '0';
					$data['batch_id']          = '0';
					$data['serial_id']         = '0';
					$data['date']         	   = $invoice->date;
					$data['transaction_type']  = 'RETURN';
					$data['transaction_scr_id']= $invoice->t_order_id;
					$data['created_at']		  = $invoice->created_at;
					$data['created_at']		  = $invoice->updated_at;
					$this->stockLog($data);
					$transactionData['transaction_type'] ='return';
					unset($transactionData['transaction_scr_id']);
					unset($transactionData['ref_stock_point_id']);
					unset($transactionData['batch_id']);
					StockTransactions::create($transactionData);
				}
			}//End of invoice detail
		}
		}
		return response()->json([ 'message' => 'Stock logs Update Successfully' ], 200);
	}//End of function

	public function stockItemAdjustmentManualy($param){

		/*Manually adjust any product when some time any mismatch qty*/

		$v_id      = $param['v_id'];
		$store_id  = $param['store_id'];
		$stock_point_id = $param['stock_point_id'];
		$stock_type = $param['stock_type'];
		$qty        = $param['qty'];
		$sku        = $param['sku'];

		
		$remarks    = 'Manual update stock adjustment.('.$param['remarks'].')';
		$skuDetail  = VendorSkuDetails::where(['v_id'=>$v_id ,'sku'=>$sku])->first();
		if($skuDetail){
			DB::beginTransaction();
            try {
			$batch     = StockLogs::where('v_id',$v_id)->where('variant_sku',$skuDetail->sku)->orderBy('id','desc')->first();

			//	'barcode' 	  => $skuDetail->barcode,


			$stockAdjData = [
				'variant_sku' => $skuDetail->sku,
				'item_id' 	  => $skuDetail->item_id,
				'store_id' 	  => $store_id,
				'stock_type'  => $stock_type,
				'stock_point_id' => $stock_point_id,
				'qty' => $qty,
				'ref_stock_point_id' => 0,
				'batch_id' => @$batch->batch_id,
				'serial_id' => isset($serialNumberId)?$serialNumberId:NULL,
				'v_id' => $v_id,
				'via_from' =>'STOCK_AUDIT',
				'vu_id' => 0,
				'remarks' => $remarks
 				 
			];
			print_r($stockAdjData);die;
			$stAdj = StockAdjustment::insertGetId($stockAdjData);
			if($stock_type == 'OUT'){
				$stockAdjData['qty'] = -1*abs($qty);
			}
			$stockAdjData['barcode']    = $skuDetail->barcode;
			$stockAdjData['transaction_type'] = 'ADJ' ;
			$stockAdjData['transaction_scr_id'] = $stAdj;
			$stockAdjData['ADJ'] = $stock_type;

			$this->stockLog($stockAdjData,'IN');
			echo 'done';
			DB::commit();
            } catch (Exception $e) {
                DB::rollback();
                exit;
            }
			//$this->stockOut($stockOutData);
		}
		 
	}

	public function updateStockCurrentStatus($id,$store_id)
	{
		// ini_set('memory_limit','8095M');
		$store_id 	 = $store_id;
		$allBarcodes = $this->todb->table('grn_list as gn')
		->select('vi.sku','vi.barcode')
		->join('v_item_list as vi', function($join) {
			$join->on('vi.barcode', '=', 'gn.item_no');
			$join->on('vi.v_id', '=', 'gn.v_id');
		})
		->where('gn.v_id', $id)
		->groupBy('gn.item_no')
		->havingRaw('sum(gn.qty) > 0')
		// ->whereRaw("date(created_at) between '2019-09-01' AND '2019-09-15'")
		//->where('vi.barcode', '8908000911048')
		// ->skip(60)
		// ->take(150)
		// ->where('id', 7542)
		->get();
		 //dd($allBarcodes);


		$dateList = $this->generateDateRange(Carbon::parse('2019-11-01'), Carbon::parse('2019-12-16'));
		
				// $date = date('Y-m-d', strtotime($value->created_at));
				// dd($previousDate);
				// Previous Status				
				foreach ($allBarcodes as $barcode) {
					$previousDate =  0;
					$updateDate = [ 'grn_qty' => 0, 'sale_qty' => 0, 'opening_qty' => 0, 'int_qty' => 0, 'out_qty'=> 0 ];
					$previousOpeningQty = 0;
					if(count($dateList) > 0) {
					foreach ($dateList as $key => $value) {

					$getCurrentStatus = $this->todb->table('stock_current_status')->where('v_id', $id)->where('store_id', $store_id)->where('for_date', $value)->where('variant_sku', $barcode->sku)->first();
					
					if(!empty($getCurrentStatus)) {
						$grnCount = $this->todb->table('grn_list')->where('v_id', $id)->where('store_id', $store_id)->where('item_no', $barcode->barcode);
						$saleCount = $this->todb->table('invoice_details')->where('v_id', $id)->where('store_id', $store_id)->where('item_id', $barcode->barcode);
						$currentGrnCount = $this->todb->table('grn_list')->where('v_id', $id)->where('store_id', $store_id)->where('item_no', $barcode->barcode);
						$currentSaleCount = $this->todb->table('invoice_details')->where('v_id', $id)->where('store_id', $store_id)->where('item_id', $barcode->barcode);
						$getPrevoiusStatus = $this->todb->table('stock_current_status')->where('v_id', $id)->where('store_id', $store_id)->where('for_date', $previousDate)->where('variant_sku', $barcode->sku)->first();
						if(!empty($getPrevoiusStatus)) {
							$previousOpeningQty = $getPrevoiusStatus->opening_qty;
						}

						$updateDate['opening_qty'] = $previousOpeningQty + $grnCount->whereDate('created_at', $previousDate)->sum('qty') - $saleCount->whereDate('created_at', $previousDate)->sum('qty');

						$updateDate['sale_qty'] = $updateDate['out_qty'] = $currentSaleCount->whereDate('created_at', $value)->sum('qty');
						$updateDate['grn_qty'] = $updateDate['int_qty'] = $currentGrnCount->whereDate('created_at', $value)->sum('qty');
						// echo $saleCount->whereDate('created_at', $value)->sum('qty');
						echo $barcode->sku.'   - '.$previousDate.'   -   ';
						print_r($updateDate);
						echo ' ----' ;
						echo $previousDate = $value;
						echo $this->todb->table('stock_current_status')->where('id', $getCurrentStatus->id)->update($updateDate);
						
					}


					}

   				  }
	
				}

			

		return response()->json([ 'message' => 'Stock Current Status Update Successfully' ], 200);
	}
	/* Stock Create and update function end*/

	public function get_vendor_settings(Request $request){
		$v_id = $request->v_id;
		$name = $request->setting_name;

		$settings = VendorSetting::select('name','settings')->where('v_id',$v_id)->where('name',$name)->first();
		$settings = json_decode($settings->settings);

		return response()->json(['status' => 'success' , 'data' => $settings],200);


	}

	public function update_vendor_settings(Request $request)
	{
		$v_id = $request->v_id;
		$name = $request->setting_name;
		$settings_data = json_decode($request->settings);

		//dd(json_decode($request->settings));

		$settings = VendorSetting::where('v_id',$v_id)->where('name',$name)->first();
		$settings->settings = json_encode($settings_data);
		$settings->save();


		return response()->json(['status' => 'success' , 'data' => "Data updated successfully"], 200);
	}

	public function apportion()
	{
		$paymentLists = Payment::where('order_id', 'O2001002J4K00003')->where('payment_gateway_type', 'LOYALTY')->where('status', 'success')->get();
		$order = Order::where('order_id', 'O2001002J4K00003')->first();
		$orderData = [];
		$totalTax = $totalAmount = 0;

		if (!$paymentLists->isEmpty()) {

			$totalLPDiscount = $paymentLists->sum('amount');
			$discountPer = getPercentageOfDiscount($order->total, $totalLPDiscount);
			
			// Apply discount to each item

			foreach ($order->details as $item) {

				$total = $tax = $lpdiscount = 0;
				$lpdiscount = round($item->total * $discountPer / 100, 2);
				$total = $item->total - $lpdiscount;
				$tax_code = json_decode($item->section_target_offers);
				$tax_code = json_decode(urldecode($tax_code->item_det));
				// dd(json_decode($tax_code)->INVHSNSACMAIN_CODE);
				$orderData[] = [ 'id' => $item->id, 'barcode' => $item->item_id, 'total' => $total, 'lpdiscount' => $lpdiscount, 'qty' => $item->qty, 'tax_code' => $tax_code->INVHSNSACMAIN_CODE, 'store_id' => $item->store_id ];

			}

			// Calculate all item LP Discount & match to Bill level LP Discount

			$orderData = collect($orderData);
			$totalItemLPdiscount = $orderData->sum('lpdiscount');
			if ($totalLPDiscount == $totalItemLPdiscount) {
				// echo 'Cool : -'.$totalItemLPdiscount;
			} elseif ($totalItemLPdiscount > $totalLPDiscount) {
				$highestLPDAmt = $orderData->sortByDesc('lpdiscount')->first();
				$diffAmt = round($totalItemLPdiscount - $totalLPDiscount, 2);
				$orderData = $orderData->map(function ($item, $key) use ($highestLPDAmt, $diffAmt) {
					if ($item['id'] == $highestLPDAmt['id']) {
						$item['lpdiscount'] = $item['lpdiscount'] - $diffAmt;
						$item['total'] = $item['total'] + $diffAmt;
					} 
					return $item;
				});
				// echo 'Grater Then : -'.$orderData->sum('lpdiscount');
			} elseif ($totalItemLPdiscount < $totalLPDiscount) {
				$lowestLPDAmt = $orderData->sortBy('lpdiscount')->first();
				$diffAmt = round($totalLPDiscount - $totalItemLPdiscount, 2);
				$orderData = $orderData->map(function ($item, $key) use ($lowestLPDAmt, $diffAmt) {
					if ($item['id'] == $lowestLPDAmt['id']) {
						$item['lpdiscount'] = $item['lpdiscount'] + $diffAmt;
						$item['total'] = $item['total'] - $diffAmt;
					} 
					return $item;
				});
				// echo 'Less Then : -'.$orderData->sum('lpdiscount');
			}

			// dd($orderData);

			// Re-calculate Tax of all items

			foreach ($orderData as $taxData) {
				
				$CartController = new CartController();
				$itemTaxData = $CartController->taxCal([
					'barcode' 	=> $taxData['barcode'],
					'qty'		=> $taxData['qty'],
					's_price'	=> $taxData['total'],
					'tax_code'	=> $taxData['tax_code'],
					'store_id'	=> $taxData['store_id']
				]);

				$totalAmount += $taxData['total'];
				$totalTax += $itemTaxData['tax'];

				OrderDetails::find($taxData['id'])->update([
					'lpdiscount'	=> $taxData['lpdiscount'],
					'tax'			=> format_number($itemTaxData['tax'], 2),
					'total'			=> $taxData['total'],
					'tdata'			=> json_encode($itemTaxData)
				]);

			}

			// dd($totalTax);

			// Update Order Data

			Order::where('order_id', $order->order_id)->update([
				'lpdiscount'	=> $totalLPDiscount,
				'tax'			=> format_number($totalTax, 2),
				'total'			=> $totalAmount
			]);
		}
	}

	public function billResponse()
	{
		$emr = new EaseMyRetailController;
		$response = $emr->createBillPushResponse('Z2001002J5300002');
		return $response;
	}

	public function couponResposne()
	{
		echo '<!DOCTYPE html><html><body><h2>Cool</h2><input type="hidden" id="redemptionResponse" value="{"referenceno":"O2001002J4U00001","basis":"1","factor":"500","min_purchase_value":"1000","max_redeem_value":"500","allow_point_accrual":"0","mobileno":"9967746674","allow_point_redemption":"0","couponcode":"ZWBPVJOUGR","status":"Success","OFFERCODE":"ZWING","Min_Item_Quantity":"0","Max_item_quantity":"0"}" /></body></html>';
	}

	public function orderSummary()
	{
		$orders = Order::where('order_id', 'O7011001J9O00045')->first();
		$order = new OrderController;
		$response = $order->getOrderResponse(['order' => $orders , 'v_id' => 11 , 'trans_from' => 'ANDROID_VENDOR' ]);
		return $response;
	}

	private function generateDateRange(Carbon $start_date, Carbon $end_date)
	{
	    $dates = [];

	    for($date = $start_date->copy(); $date->lte($end_date); $date->addDay()) {
	        $dates[] = $date->format('Y-m-d');
	    }

	    return $dates;
	}

	public function taxCalculation(Request $request)
	{
		if(!$request->has('sku_code') || empty($request->sku_code)) {
			return response()->json([ 'status' => 'fail', 'message' => 'SKU code required.' ]);
		}

		if(!$request->has('v_id') || empty($request->v_id)) {
			return response()->json([ 'status' => 'fail', 'message' => 'Vendor ID code required.' ]);
		}
		$v_id = 92;
		JobdynamicConnection($v_id);

		$itemDetails = VendorSku::select('vendor_sku_detail_id','item_id','sku_code','name','hsn_code','has_batch','variant_combi','tax_type')->where([ 'sku_code' => $request->sku_code, 'v_id' => $v_id, 'deleted_at' => null ])->first();

		$params = [ 'barcode' => $itemDetails->bar[0], 'sku_code' => $request->sku_code, 'qty' => 1, 's_price' => 135, 'hsn_code' => $itemDetails->hsn_code, 'store_id' => 247,'v_id' => $v_id, 'from_gstin' => '22AABCU9603R1ZX' , 'to_gstin' => null , 'invoice_type' => 'B2C' ];
		$cloudCart = new CloudCart;
		$taxDetails = $cloudCart->taxCal($params);
		// dd($taxDetails);
	}

	public function emailTemplate(Request $request)
	{
		// dd(app('queue'));
		// dispatch(new \App\Jobs\SendEmailJob());
		// Mail::to('shubham.m@gsl.in')->send(new IntegrationSyncStatus(108));
  //       if( count(Mail::failures()) > 0 ) {
  //       	 echo "There was one or more failures. They were: <br />";
  //       }
		return (new IntegrationSyncStatus(108))->render();
		// dd(app('queue.connection'));
		// $email = new IntegrationSyncStatus(108);        
        // app('mailer')->to('shubhammaurya021@gmail.com')->send($email);
	}

	public function postJob(Request $request)
	{
		$v_id = 108;
		JobdynamicConnection($v_id);
		// echo 'Cool';
		// event(new GrnCreated([ 'v_id' => 108, 'store_id' => 230, 'grn_id' => 1155, 'advice_id' => 1376, 'zv_id' => '<ZWINGV>'.'108'.'<EZWINGV>',  'zs_id' => '<ZWINGSO>'.'230'.'<EZWINGSO>',  'zt_id' => '<ZWINGTRAN>'.'1155'.'<EZWINGTRAN>' ]));

		// Invoice List
		// $invoiceList = Invoice::where([ 'v_id' => $v_id ])->whereIn('sync_status', ['0','2'])->get();
		// foreach ($invoiceList->chunk(50) as $key => $chunkInvoiceList) {
		// 	foreach ($chunkInvoiceList as $invoice) {
		// 		if($invoice->transaction_type == 'return') {
		// 			event(new InvoiceCreated([ 'invoice_id' => $invoice->id, 'v_id' => $invoice->v_id, 'store_id' => $invoice->store_id, 'db_structure' => 2, 'type' => 'RETURN', 'zv_id' => '<ZWINGV>'.$invoice->v_id.'<EZWINGV>', 'zs_id' => '<ZWINGSO>'.$invoice->store_id.'<EZWINGSO>', 'zt_id' => '<ZWINGTRAN>'.$invoice->id.'<EZWINGTRAN>'
		// 	        ]));
		// 		} else if($invoice->transaction_type == 'sales') {
		// 			event(new InvoiceCreated([ 'invoice_id' => $invoice->id, 'v_id' => $invoice->v_id, 'store_id' => $invoice->store_id, 'db_structure' => 2, 'type' => 'SALE', 'zv_id' => '<ZWINGV>'.$invoice->v_id.'<EZWINGV>', 'zs_id' => '<ZWINGSO>'.$invoice->store_id.'<EZWINGSO>', 'zt_id' => '<ZWINGTRAN>'.$invoice->id.'<EZWINGTRAN>'
		// 	        ]));
		// 		}
		// 	}
		// }

		// Stock Point Transfer
		// $stockPointTransferList = StockPointTransfer::where([ 'v_id' => $v_id ])->whereIn('sync_status', ['0','2'])->get();
		// foreach ($stockPointTransferList->chunk(50) as $key => $chunkstockPointTransferList) {
		// 	foreach ($chunkstockPointTransferList as $stockPoint) {
		// 		event(new StockTransfer([ 'v_id' => $stockPoint->v_id, 'store_id' => $stockPoint->store_id, 'spt_id' => $stockPoint->id, 'zv_id' => '<ZWINGV>'.$stockPoint->v_id.'<EZWINGV>', 'zs_id' => '<ZWINGSO>'.$stockPoint->store_id.'<EZWINGSO>', 'zt_id' => '<ZWINGTRAN>'.$stockPoint->id.'<EZWINGTRAN>']));
		// 	}
		// }

		// Packet List
		// $packetList = Packet::where([ 'v_id' => $v_id ])->whereIn('sync_status', ['0','2'])->get();
		// foreach ($packetList->chunk(50) as $key => $chunkPacketList) {
		// 	foreach ($chunkPacketList as $packet) {
		// 		event(new PacketCreated([ 'v_id' => $packet->v_id, 'store_id' => $packet->store_id, 'packet_id' => $packet->id, 'zv_id' => '<ZWINGV>'.$packet->v_id.'<EZWINGV>', 'zs_id' => '<ZWINGSO>'.$packet->store_id.'<EZWINGSO>', 'zt_id' => '<ZWINGTRAN>'.$packet->id.'<EZWINGTRAN>']));
		// 	}
		// }

		// Grn List
		// $grnList = Grn::where([ 'v_id' => $v_id ])->whereIn('sync_status', ['0','2'])->get();
		// foreach ($grnList->chunk(50) as $key => $chunkGrnList) {
		// 	foreach ($chunkGrnList as $grn) {
		// 		event(new GrnCreated([ 'v_id' => $grn->v_id, 'store_id' => $grn->store_id, 'grn_id' => $grn->id, 'advice_id' => $grn->advice_id, 'zv_id' => '<ZWINGV>'.$grn->v_id.'<EZWINGV>', 'zs_id' => '<ZWINGSO>'.$grn->store_id.'<EZWINGSO>', 'zt_id' => '<ZWINGTRAN>'.$grn->id.'<EZWINGTRAN>' ]));
		// 	}
		// }

		// Grt List
		// $grtList = GrtHeader::where([ 'v_id' => $v_id ])->whereIn('sync_status', ['0','2'])->get();
		// foreach ($grtList->chunk(50) as $key => $chunkGrtList) {
		// 	foreach ($chunkGrtList as $grt) {
		// 		event(new GrtCreated([ 'v_id' => $grt->v_id, 'store_id' => $grt->src_store_id, 'grt_id' => $grt->id, 'zv_id' => '<ZWINGV>'.$grt->v_id.'<EZWINGV>', 'zs_id' => '<ZWINGSO>'.$grt->src_store_id.'<EZWINGSO>', 'zt_id' => '<ZWINGTRAN>'.$grt->id.'<EZWINGTRAN>' ]));
		// 	}
		// }

		// Adjustment List
		// $adjList = Adjustment::where([ 'v_id' => $v_id ])->whereIn('sync_status', ['0','2'])->get();
		// foreach ($adjList->chunk(50) as $key => $chunkAdjList) {
		// 	foreach ($chunkAdjList as $adj) {
		// 		event(new StockAdjust([ 'v_id' => $adj->v_id, 'store_id' => $adj->store_id, 'adj_id' => $adj->id, 'zv_id' => '<ZWINGV>'.$adj->v_id.'<EZWINGV>', 'zs_id' => '<ZWINGSO>'.$adj->store_id.'<EZWINGSO>', 'zt_id' => '<ZWINGTRAN>'.$adj->id.'<EZWINGTRAN>' ]));
		// 	}
		// }

		// Cash transcation
		// $cashTransactionList = CashTransaction::where([ 'v_id' => $v_id ])->whereIn('sync_status', ['0','2'])->get();
		// foreach ($cashTransactionList->chunk(50) as $key => $chunkcashTransactionList) {
		// 	foreach ($chunkcashTransactionList as $cash) {
		// 		event(new CashPointTransfer([ 'v_id' => $cash->v_id, 'store_id' => $cash->store_id, 'cash_transaction_id' => $cash->id, 'transfer_type' => '1', 'zv_id' => '<ZWINGV>'.$cash->v_id.'<EZWINGV>', 'zs_id' => '<ZWINGSO>'.$cash->store_id.'<EZWINGSO>', 'zt_id' => '<ZWINGTRAN>'.$cash->id.'<EZWINGTRAN>', 'event_type' => '<ZWINGTYPE>1<EZWINGTYPE>' ]));
		// 	}
		// }

		// $storeExpenseList = StoreExpense::where([ 'v_id' => $v_id ])->whereIn('sync_status', ['0','2'])->get();
		// foreach ($storeExpenseList->chunk(50) as $key => $chunkStoreExpenseList) {
		// 	foreach ($chunkStoreExpenseList as $store) {
		// 		event(new CashPointTransfer([ 'v_id' => $store->v_id, 'store_id' => $store->store_id, 'cash_transaction_id' => $store->id, 'transfer_type' => '2', 'zv_id' => '<ZWINGV>'.$store->v_id.'<EZWINGV>', 'zs_id' => '<ZWINGSO>'.$store->store_id.'<EZWINGSO>', 'zt_id' => '<ZWINGTRAN>'.$store->id.'<EZWINGTRAN>', 'event_type' => '<ZWINGTYPE>2<EZWINGTYPE>' ]));
		// 	}
		// }

	}

}