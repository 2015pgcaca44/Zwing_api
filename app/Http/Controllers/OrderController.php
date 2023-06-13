<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Address;
use App\Order;
use App\OrderDetails;
use App\OrderItemDetails;
use App\Cart;
use App\CartDetails;
use App\CartOffers;
use App\Payment;
use App\CustomerGroup;
use App\User;
use App\Invoice;
use DB;
use App\OrderDiscount;
use App\Model\Stock\StockPoints;
use App\Model\Stock\StockPointSummary;
use App\DepRfdTrans;
use App\Voucher;
use App\Http\Controllers\CloudPos\AccountsaleController;
use App\SettlementSession;
use App\CrDrSettlementLog;  
use App\Store;
use App\Http\Controllers\CloudPos\ProductController;
use App\Http\Controllers\CloudPos\CartController;
use App\Model\Grn\GrnList;
use App\CustomerGroupMapping;
use App\CashRegister;
use App\InvoiceDetails;
use App\Http\Controllers\CloudPos\CartconfigController;
use Auth;
use App\Http\Controllers\StockController;
use App\Vendor;
use App\CashPoint;
use App\Http\Controllers\CartController as MainCart;
use App\CashTransactionLog;
use App\Model\Items\VendorSku;
use App\Model\Payment\Mop;
use App\Events\SaleItemReport;
use Event;
use App\Events\DepositeRefund;
use App\Events\InvoiceCreated;

class OrderController extends Controller
{
	public function __construct()
	{
		//$this->middleware('auth');
		$this->cartconfig  = new CartconfigController;
	}

	public function getOrderResponse($params)
	{
		$v_id = $params['v_id'];
		$response = [];
		$summary = [];
		$order = null;
		$items_qty = 0;
		$amount_due = 0;
		$tax_total=0;
		$tatalItemLevelManualDiscount=0;

		$transaction_sub_type = '';
        if(isset($params['transaction_sub_type'])){
        	$transaction_sub_type  = $params['transaction_sub_type'];
        }

		if($params['trans_from'] == 'ANDROID_VENDOR' || $params['trans_from'] == 'CLOUD_TAB' || $params['trans_from'] == 'CLOUD_TAB_ANDROID' || $params['trans_from'] == 'CLOUD_TAB_WEB' || $params['trans_from'] == 'ANDROID_KIOSK' ) {
			
			if(isset($params['order'])){
				$order = $params['order'];
			}else if(isset($params['order_id'])){
				$order = Order::select('od_id')->where('v_id',$v_id)->where('order_id' , $params['order_id'])->first();
			}else{
				return $response;
			}

			$orderDetails = OrderDetails::where([ 't_order_id' => (string)$order->od_id, 'v_id' => $v_id, 'store_id' => $order->store_id ])->get();
             
			$total_payable = (float)$order->total;

			if($transaction_sub_type == 'lay_by'){
				$total_payable = (float) $order->lay_by_total;
			}else{
				$total_payable = (float)$order->total;
			}

			$items = [];
			$sub_total = 0;
			foreach ($orderDetails as $key => $od) {
				$product_details = json_decode($od->section_target_offers);
				$uom = '';
				if(isset($product_details->uom)){
					$uom = $product_details->uom;
				}
				$items[] = ['p_name' => utf8_encode($od->item_name)  , 'qty' => $od->qty , 'total' => format_number($od->total),'weight_flag'=>($od->weight_flag == 1)?true:false,'uom'=> $uom, 'barcode' => $od->barcode, 'unit_mrp' => format_number(@$od->unit_mrp), 'discount' => format_number(@$od->discount), 'extra_charge' => format_number(@$od->extra_charge), 'tax' => format_number(@$od->tax), 'subtotal' => format_number(@$od->subtotal) ];
				
				if($od->weight_flag == '1'){
	                $items_qty =  $items_qty + 1;
	            }else{

	                if($od->plu_barcode){
	                    $cart_plu_qty = $od->qty;
	                    $cart_plu_qty = explode('.',$cart_plu_qty);
	                    //dd($cart_plu_qty);
	                    if(count($cart_plu_qty) > 1 ){
	                        $items_qty =  $items_qty + 1;
	                    }else{
	                        $items_qty =  $items_qty + $od->qty;    
	                    }
	                }else{
	                    $items_qty =  $items_qty + $od->qty; 
	                }   
	            }
	            // itemwise manual discount 
                $itemLevelmanualDiscount=0;
	            if($od->item_level_manual_discount!=null){
	                $iLmd = json_decode($od->item_level_manual_discount);
	                $itemLevelmanualDiscount= $iLmd->discount;
	            }

				$tdata = json_decode($od->tdata);
				if(isset($tdata->tax_type) && $tdata->tax_type == 'EXC'){
					$sub_total += $od->subtotal;
				}else{
					//$sb = $od->subtotal-$od->tax;
					$sb = $od->subtotal;
					$sub_total += $sb;
					$tax_total += $od->tax;
					
				}
			    if($itemLevelmanualDiscount>0){
                  $tatalItemLevelManualDiscount  += $itemLevelmanualDiscount;
                }	 

			}
			$sub_total = $sub_total - $tax_total;
			$sb_total = $sub_total;//($order->subtotal-$order->tax);
			$summary[] = [ 'name' => 'sub_total' , 'display_text' => 'Sub Total' , 'display_name' => 'Sub Total' , 'value' => format_number($sb_total),'sign'=>'' ];
			if($order->discount > 0.0){
				$summary[] = [ 'name' => 'discount' , 'display_text' => 'Discount' ,'display_name' => 'Discount' , 'value' => format_number($order->discount),'sign'=>'-' ];
			}
			
			if($order->bill_buster_discount > 0.0){
				$summary[] = [ 'name' => 'bill_buster_discount' , 'display_text' => 'Bill Discount' ,'display_name' => 'Bill Discount' , 'value' => format_number($order->bill_buster_discount),'sign'=>'-'];
			}
			
			if ($order->status == 'process') { 
				$checkDiscountExists = OrderDiscount::where('v_id', $order->v_id)->where('store_id', $order->store_id)->where('order_id', $order->order_id)->get();
				$bill_level = 0.00;
				if ($checkDiscountExists->isNotEmpty()) { 
					foreach ($checkDiscountExists as $discount) {
						//dd($discount->name);
						if($discount->name=='Manual Discount'){
						  $bill_level +=$discount->amount; 	
                          $mdiscountType[] = ['name' => 'bill_level', 'value' => format_number($discount->amount)];    
						}else{
                        if($discount->amount >0){
						 $summary[] = [ 'name' => 'discount' , 'display_text' => $discount->name , 'display_name' => $discount->name , 'value' => format_number($discount->amount) , 'color_flag' => '1','sign'=>'-'];
					    }
					   }
					}
                   
					$total_payable -= (float)$checkDiscountExists->where('type', '!=' , 'MD')->sum('amount');
				}
				
				if($tatalItemLevelManualDiscount>0){
                  
                  $mdiscountType[] = ['name' => 'item_level', 'value' => format_number($tatalItemLevelManualDiscount)];
                 //$total_payable -= (float)$tatalItemLevelManualDiscount;
				}
    
				if($checkDiscountExists->isNotEmpty() || $tatalItemLevelManualDiscount>0){
					
                 $total = $bill_level+$tatalItemLevelManualDiscount;
                 $summary[] = [ 'name' => 'manual_discount' , 'display_text' => 'Manual Discount' , 'display_name' =>'Manual Discount' , 'value' => format_number($total) , 'color_flag' => '1','sign'=>'-','type' => $mdiscountType];
                 

				}
			} elseif ($order->status == 'success') { 
				if($tatalItemLevelManualDiscount>0){
				  $mdiscountType[] = ['name' => 'item_level', 'value' => format_number($tatalItemLevelManualDiscount)];	
				}
				if($order->manual_discount > 0.0) {
                 $mdiscountType[] = ['name' => 'bill_level', 'value' => format_number($order->manual_discount)];   
                }
				if($order->manual_discount > 0.0 || $tatalItemLevelManualDiscount>0){

					if($order->manual_discount>0.0){
                      $bill_level = $order->manual_discount;
					}else{
                     $bill_level = 0;
					}
					$totalmanualdiscount = (float)$tatalItemLevelManualDiscount+(float)$bill_level;

					$summary[] = [ 'name' => 'manual_discount' , 'display_text' => 'Manual Discount' , 'display_name' => 'Manual Discount' , 'value' => format_number($totalmanualdiscount),'sign'=>'-','type' => $mdiscountType];
				}
			}
			
			if (!empty($order->extra_charge)) {
	            $summary[] = [ 'name' => 'extra_charge' , 'display_text' => 'Extra Charge' ,'display_name' => 'Extra Charge', 'value' => abs($order->extra_charge),'sign' => '+' ];
	        }

			$summary[] = [ 'name' => 'tax' , 'display_text' => 'Taxes' , 'display_name' => 'Taxes' , 'value' => format_number($order->tax),'sign'=>'' ];
			// Round Off Calculation
	        if (!empty($order->round_off)) {
	            $summary[] = [ 'name' => 'roundoff' , 'display_text' => 'Round Off' ,'display_name' => 'Round Off', 'value' => abs($order->round_off),'sign' => $order->round_off < 0 ? '-' : '+' ];
	        }
			$summary[] = [ 'name' => 'total' , 'display_text' => 'Total' ,'display_name' => 'Total' , 'value' => format_number($order->total),'sign'=>'' ];
			// $summary[] = [ 'name' => 'carry_bags' , 'display_text' => 'Carry Bag' , 'value' => format_number('0') ];


			$payments = Payment::where('v_id', $order->v_id)->where('store_id', $order->store_id)->where('order_id', $order->order_id)->where('status','success')->get();


			foreach ($payments as $key => $payment) {
				if($payment->payment_gateway_type == 'CASH'){
					$summary[] = [ 'name' => 'cash' , 'display_text' => 'Cash' ,'display_name' => 'Cash' , 'value' =>  format_number($payment->amount) , 'color_flag' => '1' , 'mop_flag' => '1'];

				}else if($payment->payment_gateway_type == 'VOUCHER' || $payment->payment_gateway_type == ''){
					if($payment->method == 'credit_note_received') {
						$summary[] = [ 'name' => 'voucher_credit' , 'display_text' => 'Credit Note Redeemed' ,'display_name' => 'Credit Note Redeemed' , 'value' =>  format_number($payment->amount) , 'color_flag' => '1' , 'mop_flag' => '1' ];
					} 
					if($payment->method == 'credit_note_issued') {
						$summary[] = [ 'name' => 'voucher_debit' , 'display_text' => 'Credit Note Issued' ,'display_name' => 'Credit Note Issued' , 'value' =>  format_number($payment->amount) , 'color_flag' => '1' , 'mop_flag' => '1' ];
					} 
				}else if($payment->payment_gateway_type == 'RAZOR_PAY'){
					
					if($payment->method == 'wallet'){

						$summary[] = [ 'name' => 'online' , 'display_text' => 'Wallet' ,'display_name' => 'Wallet' , 'value' =>  format_number($payment->amount) , 'color_flag' => '1' , 'mop_flag' => '1'];
					}else if($payment->method == 'netbanking'){

						$summary[] = [ 'name' => 'netbanking' , 'display_text' => 'Net Banking' ,'display_name' => 'Net Banking' , 'value' => format_number($payment->amount) , 'color_flag' => '1' , 'mop_flag' => '1'];

					}else if($payment->method == 'card'){

						$summary[] = [ 'name' => 'card' , 'display_text' => 'Card' , 'display_name' => 'Card' , 'value' =>  format_number($payment->amount) , 'color_flag' => '1' , 'mop_flag' => '1'];
					}

				}else if($payment->payment_gateway_type == 'EZETAP' || $payment->payment_gateway_type == 'EZSWYPE'){

					$summary[] = [ 'name' => 'card' , 'display_text' => 'Card' , 'display_name' => 'Card' , 'value' =>  format_number($payment->amount) , 'color_flag' => '1' , 'mop_flag' => '1' ];
				} else {
					if(!empty($payment->mop_id) && !empty($payment->mop)) {
						$summary[] = [ 'name' => $payment->method , 'display_text' => ucwords($payment->mop->name) ,'display_name' => ucwords($payment->mop->name) , 'value' =>  format_number($payment->amount) , 'color_flag' => '1' , 'mop_flag' => '1' ];
					} else {
						$paymentName  = str_replace('_', ' ', $payment->method);
						$summary[] = [ 'name' => $payment->method , 'display_text' => ucwords($paymentName) ,'display_name' => ucwords($paymentName) , 'value' =>  format_number($payment->amount) , 'color_flag' => '1' , 'mop_flag' => '1' ];
					}
				}
				
			}

			$amount_paid = (float)$payments->sum('amount');
			$total_payable -= $amount_paid;


			if(in_array($order->transaction_sub_type, ['lay_by','order'])){
				$amount_due = (float)$order->total - $amount_paid;
				$summary[] = [ 'name' => 'amount_due' , 'display_text' => 'Amount Due' ,'display_name' => 'Amount Due' , 'value' => format_number($amount_due), 'color_flag' => '1'  ,'color_code' => '#FF0000'];
			}else{
				if($order->transaction_type == 'return'){
					$summary[] = [ 'name' => 'total_refund ' , 'display_text' => 'Total Refund' ,'display_name' => 'Total Refund' , 'value' => format_number($amount_paid), 'color_flag' => '1' ];
				}else{
				$summary[] = [ 'name' => 'total_payable' , 'display_text' => 'Total Payable' ,'display_name' => 'Total Payable' , 'value' => format_number($total_payable), 'color_flag' => '1' ];
				}
			}

			$summary = collect($summary);
			if($params['trans_from'] == 'CLOUD_TAB'){
				$summary = $summary->values();
			}else{

				$summary = $summary->whereNotIn('value', ['0.00'])->values();
			}


			$response['items'] = $items;
			$response['item_qty'] = (string)$items_qty;
			$response['summary'] = $summary;
			$response['total_payable'] = $total_payable;
			$response['amount_due'] = (string)$amount_due;
			$response['amount_paid'] = (string)$amount_paid;
			if($order->transaction_type == 'return'){
				$response['total_refund'] = (string)$order->total;
			}
			$response['order_total'] = (string)$order->total;
			
		}

		return $response;
	}

	public function recall(Request $request)
	{
        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $order_id = $request->order_id;
        $vu_id = $request->vu_id;
        $response = [];

        $order = Order::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->first();
         if($request->has('c_id') && $request->c_id!=null) {
         $c_id = $request->c_id;
         }else{
         $c_id = $order->user_id; 
         	
         }
        if($order){

            $order_id = Order::where('user_id', $order->user_id)->where('status', 'success')->count();
            $order_id = $order_id + 1;

            $orderDetails = OrderDetails::where('t_order_id', (string)$order->od_id)->get()->toArray();
        
            //Delete cart for user if already some item exists
            $cartDelete = Cart::where('user_id', $order->user_id)->get()->pluck('cart_id')->all();
            CartDetails::whereIn('cart_id', $cartDelete)->delete();
            CartOffers::whereIn('cart_id', $cartDelete)->delete();
            Cart::where('user_id', $order->user_id)->delete();

            foreach ($orderDetails as $value) {
                $orderDetail = array_except($value, ['t_order_id','created_at', 'updated_at']);
                //change status , order_id , vu-id
                $orderDetail['status'] = 'process';
                $orderDetail['order_id'] = $order_id;
                $orderDetail['vu_id'] = $vu_id;

                $cart = Cart::create($orderDetail);
                $OrderItemDetails = OrderItemDetails::where('porder_id' , $value['id'] )->get()->toArray();
                foreach ($OrderItemDetails as $key => $orderD) {
                    
                    $orderD = array_except($orderD, ['porder_id']);
                    $orderD = array_add($orderD, 'cart_id', $cart->cat_id);
                    CartDetails::create($orderD);
                }

				// $offerD = array('cart_id'=>$cart->cart_id,'item_id'=>$value['barcode'],'mrp'=>$value['unit_mrp'],'qty'=>$value['qty'],'offers'=>$value['pdata']);
				// CartOffers::create($offerD);
            }

           $whereMdiscount = array('v_id'=>$v_id,'store_id'=>$store_id,'type'=>'MD','order_id'=>$order->order_id);
            $mDiscount = OrderDiscount::where($whereMdiscount)->first();
            // dd($mDiscount);
           	if($mDiscount){
           		// dd('check');
            $request->merge([
            'manual_discount_factor'=> $mDiscount->factor,
            'manual_discount_basis' => $mDiscount->basis,
            'remove_discount'       => 0,
            'c_id' => $order->user_id
            // 'return'                => 0
           ]);
            // dd($request->all());

          $offerconfig = new \App\Http\Controllers\OfferController;
          $offerconfig->manualDiscount($request);
        }

            //Executing all Promotions
            $params = ['v_id' => $v_id , 'store_id' => $store_id , 'c_id' => $c_id];
			$cartC = new MainCart;
			$cartc = $cartC->getInstance($request , 'CartController');
			if(method_exists($cartc,'process_each_item_in_cart')){
				$cartc->process_each_item_in_cart($params);           
			}

            $exists_user = User::select('c_id','mobile','api_token','password','first_name','last_name','vendor_user_id','email','gender','anniversary_date','gstin')->where('c_id', $order->user_id)->first();

            $address = Address::where('c_id', $order->user_id)->first();

            $group_mapping_code = DB::table('customer_group_mappings')->where('c_id', $order->user_id)->first();
            if($group_mapping_code){
                $group_code = CustomerGroup::select('id','name','code')->where('id', $group_mapping_code->group_id)->first()->code;
            }else{
                $group_code = 'DUMMY';
            }
            
            if($address){

               $custC = new CustomerController;
               $address = $custC->getAddressArr($address);
           	}

           	$exists_user->customer_group_code = $group_code;

           	$summary = [];
            if($exists_user) {

            	$on_account_bal = DepRfdTrans::where(['v_id'=>$request->v_id,'src_store_id'=>$request->store_id,'user_id'=>$exists_user->c_id])->select(DB::raw("SUM(dep_rfd_trans.amount) as amount"))->where('status','Success')->first()->amount;

                $summary['total_spent'] = format_number($exists_user->invoices()->sum('total'));
                $summary['no_of_bills'] = $exists_user->invoices()->count();
                $summary['compleleted_sales'] = format_number($exists_user->invoices()->where('transaction_type', 'sales')->sum('total'));
                $summary['compleleted_sales_total'] = $exists_user->invoices()->where('transaction_type', 'sales')->count();
                $summary['no_of_returns'] = $exists_user->invoices()->where('transaction_type', 'return')->count();
                $summary['layby'] = format_number($exists_user->invoices()->where('transaction_type', 'layby')->sum('total'));
                $summary['layby_count'] = $exists_user->invoices()->where('transaction_type', 'layby')->count();
                $summary['loyalty'] = '0.00';
                $summary['store_credit_unused'] = format_number($exists_user->vouchers->where('status','unused')->sum('amount'));
                $summary['store_credit_used'] = format_number($exists_user->vouchers->where('status','used')->sum('amount'));
                $summary['total_store_credit'] = format_number($exists_user->vouchers->sum('amount'));
                $summary['on_account'] = $on_account_bal == null ? '0.00' : $on_account_bal;
                $exists_user->groups = $exists_user->groups->pluck('code');
                $exists_user->unsetRelation('groups')->unsetRelation('invoices')->unsetRelation('vouchers');
                //get all customer group and then get all setting from group on the basis of max value
	            $groupIdList = CustomerGroupMapping::select('group_id')->where('c_id',$exists_user->c_id)->get();
	            $groupIds = collect($groupIdList)->pluck('group_id');
	            $group_settings = [];
	            $maximum_limit_perbill = CustomerGroup::where('items_limit_perbill','1')->whereIn('id',$groupIds)->max('maximum_limit_perbill');
	            $maximum_limit_perday = CustomerGroup::where('items_limit_perday','1')->whereIn('id',$groupIds)->max('maximum_limit_perday');
	            $maximum_value_perbill = CustomerGroup::where('value_limit_perbill','1')->whereIn('id',$groupIds)->max('maximum_value_perbill');
	            $maximum_value_perday = CustomerGroup::where('value_limit_perday','1')->whereIn('id',$groupIds)->max('maximum_value_perday');
	            $allow_manual_discount = CustomerGroup::where('allow_manual_discount','1')->whereIn('id',$groupIds)->exists();
	            $allow_manual_discount_bill_level = CustomerGroup::where('allow_manual_discount_bill_level','1')->whereIn('id',$groupIds)->exists();
	            $perDayQty=$exists_user->invoices()->where('transaction_type', 'sales')->where('date',date('Y-m-d'))->sum('qty');
	            $perDayValue=$exists_user->invoices()->where('transaction_type', 'sales')->where('date',date('Y-m-d'))->sum('total');
	            $afterBillQty=($maximum_limit_perday-$perDayQty)>0?($maximum_limit_perday-$perDayQty):0;
	            $afterBillValue=($maximum_value_perday-$perDayValue)>0?($maximum_value_perday-$perDayValue):0;
	            $group_settings['items_limit_perbill']=$maximum_limit_perbill>0?true:false;
	            $group_settings['maximum_limit_perbill']=empty($maximum_limit_perbill)?0:$maximum_limit_perbill;
	            $group_settings['items_limit_perday']=$maximum_limit_perday>0?true:false;
	            $group_settings['maximum_limit_perday']=empty($maximum_limit_perday)?0:$afterBillQty;
	            $group_settings['actual_maximum_limit_perday']=empty($maximum_limit_perday)?0:$maximum_limit_perday;
	            $group_settings['value_limit_perbill']=$maximum_value_perbill>0?true:false;
	            $group_settings['maximum_value_perbill']=empty($maximum_value_perbill)?0:$maximum_value_perbill;
	            $group_settings['value_limit_perday']=$maximum_value_perday>0?true:false;
	            $group_settings['maximum_value_perday']=empty($maximum_value_perday)?0:$afterBillValue;
	            $group_settings['actual_maximum_value_perday']=empty($maximum_value_perday)?0:$maximum_value_perday;
	            $group_settings['allow_manual_discount_item_level']=empty($allow_manual_discount)?false:true;
	            $group_settings['allow_manual_discount_bill_level']=empty($allow_manual_discount_bill_level)?false:true;
            }
           	   
            $order->transaction_sub_type = 'un_hold';
            $order->save();
            $response = ['status' => 'success' , 'message' => 'Order Recall successfully', 'data' => $exists_user ,
            'customer_group_code' => $group_code,
            'address' => $address, 'summary' => $summary ,'group_settings'=>$group_settings];



        }else{
            $response = ['status' => 'fail' , 'message' => 'No Order Found' ];
        }

        return response()->json($response);
    

	}

	public function processLayBy(Request $request){
		$v_id = $request->v_id;
        $store_id = $request->store_id;
        $trans_from = $request->trans_from;
        $order_id = $request->order_id;
        $vu_id = $request->vu_id;

        $order = Order::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->first();

       $collection = collect($order);

       $customer = User::select('first_name','last_name','mobile','api_token','c_id')->where('c_id', $order->user_id)->first();
       $user_api_token=$customer->api_token;
       $group = DB::table('customer_group_mappings')->where('c_id', $order->user_id)->first();
       $group_code = 'REGULAR';
       if($group){
         $group_code = DB::table('customer_groups')->where('id', $group->group_id)->first()->code;
        }
       $collection->put('customer_name',$customer->first_name.' '.$customer->last_name);
       $collection->put('mobile',$customer->mobile);
       $collection->put('c_id',$customer->c_id);
       $collection->put('customer_group_code',$group_code);
       $collection->put('user_api_token',$user_api_token);
        if($order){

			$order_arr = $this->getOrderResponse(['order' => $order , 'v_id' => $v_id , 'trans_from' => $trans_from ]) ;
			$payment = Payment::where('order_id', $order_id)->first();

			$invoice = Invoice::where('ref_order_id', $order_id)->first();
			$print_url = '';
			if($invoice){

				$print_url  =  env('API_URL').'/order-receipt/'.$order->user_id.'/'.$v_id.'/'.$store_id.'/'.$invoice->invoice_id;
			}

        	$res =  ['status' => 'proceed_to_payment', 'message' => 'Proceed to Payment', 'data' => $collection , 'order_summary' => $order_arr ,'payment' => $payment , 'print_url' => $print_url ];

        	return response()->json($res, 200);
        }else{
        	return response()->json(['status' => 'fail' , 'message' => 'No Order Found']);
        }

	}

	public function orderInventoryCheck(Request $request)
	{
		$order = Order::where([ 'order_id' => $request->order_id, 'store_id' => $request->store_id, 'transaction_sub_type' => 'order' ])->first();

		// Check Stock available
		$unavailableProductList = $availableProductList = [];
		foreach ($order->list as $key => $value) {
			$checkStock = StockPointSummary::select('item_id','variant_sku','stock_point_id','batch_id','serial_id','sku_code', 'qty')->where([ 'v_id' => $value->v_id, 'store_id' => $value->store_id, 'barcode' => $value->barcode, 'sku_code' => $value->sku_code, 'stock_point_id' => $order->store->SellableStockPoint->id, 'serial_id' => $value->serial_id, 'batch_id' => $value->batch_id ])->first();
			if(empty($checkStock)) {
				$unavailableProductList[] = [ 'name' => $value->item_name, 'qty' => $value->qty, 'stock' => '' ];
			} else {
				if($checkStock->qty < 0) {
					$unavailableProductList[] = [ 'name' => $value->item_name, 'qty' => $value->qty, 'stock' => $checkStock->qty ];
				} else {
					$availableProductList[] = $checkStock->toArray();
				}
			}
		}
		
		return response()->json([ 'status' => 'success', 'available' => count($availableProductList), 'unavailable' => count($unavailableProductList) ]);
	}

	public function orderCreation(Request $request)
	{
		$vendorAuth = Vendor::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'id' => $request->vu_id ])->first();
		if($vendorAuth->order_inventory_blocking_level['setting'] == 'order_created') {
			$orderStockPointResponse = $this->movingStockToOrderStockPoint($request);
			$orderStockPointResponse = $orderStockPointResponse->getData();
			if($orderStockPointResponse->status == 'fail') {
				return response()->json((array)$orderStockPointResponse);
			}
		}
		$order = Order::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'user_id' => $request->c_id, 'order_id' => $request->order_id, 'status' => 'process' ])->first();
		$order->status = 'pending';
		if(!empty($request->due_date)) {
			$order->due_date = date('Y-m-d', strtotime($request->due_date));
		}
		$order->save();
		$order->mobile = $order->user->mobile;
		$order->customer_name = $order->user->first_name.' '.$order->user->last_name;
		$summary = $this->getOrderResponse([ 'order' => $order, 'v_id' => $request->v_id, 'trans_from' => $request->trans_from ]);
		$filterOrder = collect($order)->only(['od_id','order_id','qty','subtotal','tax','total','created_at','mobile','customer_name']);
		return response()->json([ 'status' => 'order_creation', 'data' => $filterOrder, 'order_summary' => $summary ]);
	}

	public function oredrList(Request $request)
	{
		$statusMeaningList = [ 'pending' => 'Pending', 'confirm' => 'Confirmed', 'picking' => 'Items Picked', 'picked' => 'Being Packed', 'packing' => 'Packed', 'shipped' => 'Shipped', 'success' => 'Picked by Customer', 'cancel' => 'Cancelled', 'fulfilled' => 'Order Fulfilled' ];
		$orderByfilter = [
			['column' => 'created_at', 'by' => 'desc'],
			['column' => 'created_at', 'by' => 'asc'],
			['column' => 'first_name', 'by' => 'asc'],
			['column' => 'first_name', 'by' => 'desc']
		];
		$whereCondition = [ 'orders.v_id' => $request->v_id, 'orders.store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'transaction_sub_type' => 'order' ];
		$getStatusList = Order::select('status')->distinct()->where($whereCondition)->where('status', '!=', 'process')->get()->pluck('status');
		$getMopList = Order::join('payments', function($query) {
						$query->on('orders.v_id', 'payments.v_id')->on('orders.store_id', 'payments.store_id')->on('orders.order_id','payments.order_id');
					})
					->select('payments.method')->distinct()
					->where([ 'orders.v_id' => $request->v_id, 'orders.store_id' => $request->store_id, 'orders.vu_id' => $request->vu_id, 'orders.transaction_sub_type' => 'order' ])
					->where('orders.status', '!=', 'process')
					->get()->pluck('method');
		$orderList = Order::leftJoin('customer_auth', 'orders.user_id', 'customer_auth.c_id')
						->leftJoin('payments', 'orders.order_id', 'payments.order_id')
						->select('orders.od_id','orders.order_id','orders.user_id','orders.created_at','orders.channel_id','orders.total','orders.status','orders.v_id','orders.store_id','orders.vu_id')
						->where($whereCondition)->where('orders.status', '!=', 'process')
						->groupBy('orders.order_id');
		if($request->has('start_date') && $request->has('end_date') && !empty($request->start_date) && !empty($request->end_date)) {
			$orderList = $orderList->whereBetween(DB::raw('DATE(orders.created_at)'), [date('Y-m-d', strtotime($request->start_date)),date('Y-m-d', strtotime($request->end_date))]);
		}
		if($request->has('sort') && !empty($request->sort)) {
			$orderList = $orderList->orderBy($orderByfilter[$request->sort]['column'], $orderByfilter[$request->sort]['by']);
		} else {
			$orderList = $orderList->orderBy($orderByfilter[0]['column'], $orderByfilter[0]['by']);
		}
		if($request->has('status') && !empty($request->status)) {
			$orderList = $orderList->whereIn('orders.status', json_decode($request->status));
		} 
		if($request->has('mop') && !empty($request->mop)) {
			$orderList = $orderList->whereIn('payments.method', json_decode($request->mop));
		} 
		$orderList = $orderList->paginate(50);
		$orderList->filter(function($item) use($statusMeaningList) {
			$item->p_status = $item->payment_status;
			$item->mop_list = $item->mop_name_list;
			$item->item_count = $item->details->count();
			$item->total = format_number($item->total);
			$item->source = $item->channel_name;
			$item->date = date('d M Y h:i A', strtotime($item->created_at));
			$item->status = $statusMeaningList[$item->status];
			$item->name = $item->user->first_name.' '.$item->user->last_name;
			unset($item->created_at);
			$item->unsetRelation('payments');
			$item->unsetRelation('details');
			$item->unsetRelation('user');
			return $item;
		});
		$filter['status'] = $getStatusList;
		$filter['mop'] = $getMopList;
		$filter['sort'] = [
			['name' => 'Newest First'],
			['name' => 'Oldest First'],
			['name' => 'Customer Name A-Z'],
			['name' => 'Customer Name Z-A']
		];
		return response()->json([ 'data' => $orderList, 'filter' => $filter ]);
	}

	public function orderDetails(Request $request)
	{
		// dd(Auth::user()->user_settings['order']);
		// DB::enableQueryLog();
		$productList = [];
		$statusMeaningList = [ 'pending' => 'Pending', 'confirm' => 'Confirmed', 'picking' => 'Items Picked', 'picked' => 'Being Packed', 'packing' => 'Packed', 'shipped' => 'Shipped', 'success' => 'Invoice', 'cancel' => 'Cancelled', 'fulfilled' => 'Order Fulfilled' ];
		$order = Order::where([ 'v_id' => $request->v_id, 'vu_id' => $request->vu_id, 'store_id' => $request->store_id, 'order_id' => $request->order_id ])->first();
		foreach ($order->list as $key => $product) {
			$product_details = json_decode($product->section_target_offers);
			$productList[] = (object)[ 'product_data' => [
										'return_flag' 			=> false,
										'return_qty'			=> 0,
										'carry_bag_flag'		=> false,
										'isProductReturn'		=> false,
										'p_id'					=> $product->barcode,
										'category'				=> ($product_details->category)?$product_details->category:'',
										'brand_name'			=> ($product_details->brand_name)?$product_details->brand_name:'',
										'sub_categroy'			=> ($product_details->sub_categroy)?$product_details->sub_categroy:'',
										'whishlist'				=> 'No',
										'weight_flag'			=> ($product->weight_flag == 1)?true:false,
										'p_name'				=> $product->item_name,
										'offer'					=> $product_details->offer,
										'offer_data'			=> $product_details->offer_data,
										'multiple_price_flag'	=> $product_details->multiple_price_flag,
										'multiple_mrp'			=> $product_details->multiple_mrp,
										'r_price'         		=> (string)$product->subtotal,
							            's_price'         		=> (string)$product->total,
							            'batch_id'         		=> (string)$product->batch_id,
							           	'serial_id'         	=> (string)$product->serial_id,
							           	'unit_mrp'        		=> $product_details->unit_mrp,
							           	'uom'             		=> $product_details->uom,
							            'discount'        		=> format_number($product->discount + $product->manual_discount + $product->lpdiscount + $product->coupon_discount + $product->bill_buster_discount),
							            'images'				=> "",
							            'barcode' 				=> $product->barcode
									], 'amount' => format_number($product->total), 'qty' => $product->qty, 'tax_amount' => format_number($product->tax), 'salesman_id' => $product->salesman_id, 'discount' => format_number($product->discount + $product->manual_discount + $product->lpdiscount + $product->coupon_discount + $product->bill_buster_discount) ];
		}
		// $paymentList = $order->payment_list->map(function($item) {
		// 	$item = str_replace("_", " ", $item);
		// 	return ucfirst($item);
		// });
		$summary = $this->getOrderResponse([ 'order' => $order, 'v_id' => $request->v_id, 'trans_from' => $request->trans_from ]);
		// dd(DB::getQueryLog());
		$billSummary = collect($summary['summary']);
		$totalBillSummary = $billSummary->map(function($item) {
			if($item['name'] == 'tax') {
				$item['name'] = 'tax_total';
			}
			return $item;
		});
		$pay_method = $billSummary->filter(function($item) { return array_key_exists('mop_flag', $item) && $item['mop_flag'] == '1'; })->values();
		// dd($pay_method);
		$amountDue = $billSummary->where('name', 'amount_due')->first();
		$invoiceId = '';
		$invoice = Invoice::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'ref_order_id' => $order->order_id ])->first();
		if(!empty($invoice)) {
			$invoiceId = $invoice->invoice_id;
		}


		$orderCustomerGroup = @$order->user->groups->pluck('name')->toArray();
		$orderCustomerGroup = implode(", ", $orderCustomerGroup);
		
		return response()->json([ 'status' => 'order_details', 'message' => 'Order Details Details', 'transaction_type' => $order->transaction_type, 'mobile' => $order->user->mobile, 'return_reasons' => [], 'payment_method' => $pay_method, 'data' => $productList, 'return_req_process' => [], 'return_req_approved' => [], 'product_image_link' => product_image_link().$request->v_id.'/', 'store_header_logo' => store_logo_link().'spar_logo_round.png', 'return_request_flag' => false, 'bags' => [], 'carry_bag_total' => 0, 'sub_total' => $order->subtotal, 'tax_total' => $order->tax, 'tax_details' => [], 'bill_buster_discount' => 0, 'discount' => 0, 'date' => date('d M Y h:i:s A', strtotime($order->created_at)), 'time' => '', 'order_id' => $order->order_id, 'total' => format_number($order->total), 'cart_qty_total' => $order->qty, 'saving' => 0, 'store_address' => "", 'store_timings' => "", 'delivered' => 'No', 'address' => "", 'user_api_token' => $order->user->api_token, 'bill_remark' => $order->remarks, 'customer_name' => $order->customer_name, 'c_id' => $order->user->c_id, 'bill_summary' => $totalBillSummary, 'transaction_sub_type' => $order->transaction_sub_type, 'cashier_name' => $order->cashier_name, 'store_name' => $order->store->name, 'customer_address' => @$order->user->address->address1 == ''? '-' : @$order->user->address->address1, 'customer_group' => $orderCustomerGroup, 'order_source' => $order->channel_name, 'remark' => $order->remark == ''? '-': $order->remark, 'amount_due' => empty(format_number($amountDue['value'])) || $order->status == 'cancel' ? '0.00' : format_number($amountDue['value']), 'order_status' => $statusMeaningList[$order->status], 'invoice_id' => $invoiceId, 'fulfillment_type' => 'Pickup', 'total_paid' => format_number($order->total_payment), 'due_date' => $order->due_date ]);
	}

	public function takePayment(Request $request)
	{
		$order = Order::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'order_id' => $request->order_id ])->first();
		// Check payment against already order created
		// 
		// $checkCartData = Cart::where('order_id', $order->o_id)->where('v_id', $request->v_id)->where('store_id', $request->store_id)->where('user_id', $request->c_id)->exists();
		// if(!$checkCartData) {
		// 	// Cash Entry
		// 	$this->cashTransactionEntry($request);
		// }
		if($request->has('payment_by') && $request->payment_by == 'i') {
			// Cash Entry
			$this->cashTransactionEntry($request);
			// When all due is done
			if($order->payment_status == 'Complete') {
				$this->generateInvoice($request);
			}

			$summary = $this->getOrderResponse([ 'order' => $order, 'v_id' => $request->v_id, 'trans_from' => $request->trans_from ]);
			return response()->json([ 'status' => 'payment_save', 'redirect_to_qr' => false, 'message' => 'Save Payment', 'data' => $order, 'order_summary' => $summary, 'transaction_type' => 'order-for-order' ], 200); 
		}
		DB::beginTransaction();
		try {
			DB::commit();
			
			$accountSaleCon = new AccountsaleController;
			
			// Deposit Creation
			$depositRequest = new \Illuminate\Http\Request();
			$depositRequest->merge([ 'v_id' => $request->v_id, 'src_store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'user_id' => $request->c_id, 'terminal_id' => $request->terminal_id, 'trans_type' => 'Credit', 'trans_sub_type' => 'Credit-Note', 'trans_src_ref' => $request->order_id, 'trans_src' => 'order', 'amount' => format_number($request->amount), 'status' => ucfirst($request->status), 'trans_from' => $request->trans_from, 'dep_rfd_trans' => '', 'remark' => '' ]);
			$getDepositData = $accountSaleCon->depositCreation($depositRequest);
			$getDepositData = $getDepositData->getData();

			if($getDepositData->status == 'fail') {
				return response()->json((array)$getDepositData);
			}

			// Voucher Creation & Deposit Unique Tagging
			$creditDebitNoteGenerationRequest = new \Illuminate\Http\Request();
			$creditDebitNoteGenerationRequest->merge([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'user_id' => $request->c_id, 'dep_ref_trans_ref' => $getDepositData->data->id, 'ref_id' => $request->order_id, 'type' => 'voucher_credit', 'amount' => format_number($request->amount), 'status' => 'used', 'effective_at' => date('Y-m-d H:i:s'), 'expired_at' => date('Y-m-d H:i:s') ]);
			$getCreditDebitNoteData = $accountSaleCon->debitCreditNoteGeneration($creditDebitNoteGenerationRequest);
			$getCreditDebitNoteData = $getCreditDebitNoteData->getData();

			if($getCreditDebitNoteData->status == 'fail') {
				return response()->json((array)$getCreditDebitNoteData);
			}

			// Payment Creation & Taggin Deposit
			// $depPaymentRequest = new \Illuminate\Http\Request();
			$settlementSession = SettlementSession::select('id')->where([ 'v_id' => $request->v_id , 'store_id' => $request->store_id , 'vu_id' => $request->vu_id , 'trans_from' => $request->trans_from ])->orderBy('opening_time','desc')->first();
			$session_id = 0;
			if($settlementSession) {
				$session_id = $settlementSession->id;
			}
			if(!$request->has('payment_id')) {
				return response()->json(["status" => 'fail' ,'message' => 'Payment record not found']);
			}
			$tagPaymentToDeposit = Payment::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'payment_id' => $request->payment_id, 'order_id' => $request->order_id ])->first();
			$tagPaymentToDeposit->pay_id = 'DEP-'.$getDepositData->data->id;
			$tagPaymentToDeposit->trans_type = 'Deposite';
			$tagPaymentToDeposit->order_id = $getDepositData->data->doc_no;
			$tagPaymentToDeposit->save();
			// $depPaymentRequest->merge([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'order_id' => $getDepositData->data->doc_no, 'invoice_id' => null, 'session_id' => $session_id, 'terminal_id' => $request->terminal_id, 'user_id' => $request->c_id, 'pay_id' => 'DEP-'.$getDepositData->data->id, 'amount' => format_number($request->amount), 'method' => $request->method, 'cash_collected' => 0, 'cash_return' => 0, 'error_description' => '', 'status' => $request->status, 'payment_type' => 'full', 'payment_gateway_type' => $request->payment_gateway_type, 'payment_gateway_device_type' => '', 'gateway_response' => '', 'ref_txn_id' => '', 'channel_id' => '1', 'trans_type' => 'Deposite' ]);
			// $getPaymentData = $this->paymentEntry($depPaymentRequest);
			// $getPaymentData = $getPaymentData->getData();

			// if($getPaymentData->status == 'fail') {
			// 	return response()->json((array)$getPaymentData);
			// }
			
			// Credit & Debit Note Log Creation
			$creditDebitLogRequest = new \Illuminate\Http\Request();

			$creditDebitLogRequest->merge([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'user_id' => $request->c_id, 'trans_src' => 'Deposite', 'trans_src_ref_id' => $getDepositData->data->id, 'order_id' => $order->o_id, 'applied_amount' => format_number($request->amount), 'voucher_id' => $getCreditDebitNoteData->data->id, 'status' => 'APPLIED' ]);
			$getCreditDebitLog = $accountSaleCon->debitCreditVoucherLog($creditDebitLogRequest);
			$getCreditDebitLog = $getCreditDebitLog->getData();

			if($getCreditDebitLog->status == 'fail') {
				return response()->json((array)$getCreditDebitLog);
			}

			// Redeem Deposit Creation
			$redeemDepositRequest = new \Illuminate\Http\Request();
			$redeemDepositRequest->merge([ 'v_id' => $request->v_id, 'src_store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'user_id' => $request->c_id, 'terminal_id' => $request->terminal_id, 'trans_type' => 'Credit', 'trans_sub_type' => 'Redeem-CN', 'trans_src_ref' => $request->order_id, 'trans_src' => 'order', 'amount' => -format_number($request->amount), 'status' => ucfirst($request->status), 'trans_from' => $request->trans_from, 'dep_rfd_trans' => '', 'remark' => '' ]);
			$getRedeemDepositData = $accountSaleCon->depositCreation($redeemDepositRequest);
			$getRedeemDepositData = $getRedeemDepositData->getData();

			if($getRedeemDepositData->status == 'fail') {
				return response()->json((array)$getRedeemDepositData);
			}

			// Credit Note Payment Entry & Tagging with order
			$orderPaymentRequest = new \Illuminate\Http\Request();
			$orderPaymentRequest->merge([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'order_id' => $request->order_id, 'invoice_id' => null, 'session_id' => $session_id, 'terminal_id' => $request->terminal_id, 'user_id' => $request->c_id, 'pay_id' => $getCreditDebitNoteData->data->voucher_no, 'amount' => format_number($request->amount), 'method' => 'credit_note_received', 'cash_collected' => 0, 'cash_return' => 0, 'error_description' => '', 'status' => $request->status, 'payment_type' => 'full', 'payment_gateway_type' => 'VOUCHER', 'payment_gateway_device_type' => '', 'gateway_response' => '', 'ref_txn_id' => '', 'channel_id' => '1', 'trans_type' => 'Invoice' ]);
			$getCreditNote = $this->paymentEntry($orderPaymentRequest);
			$getCreditNote = $getCreditNote->getData();

			if($getCreditNote->status == 'fail') {
				return response()->json((array)$getCreditNote);
			}

			$getCreditNote = collect($getCreditNote->data)->only(['payment_id','pay_id']);

			// Credit & Debit Note Redeem Log
			$creditDebitLogRequest = new \Illuminate\Http\Request();
			$creditDebitLogRequest->merge([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'user_id' => $request->c_id, 'trans_src' => 'Deposite', 'trans_src_ref_id' => $getRedeemDepositData->data->id, 'order_id' => $order->o_id, 'applied_amount' => -format_number($request->amount), 'voucher_id' => $getCreditDebitNoteData->data->id, 'status' => 'APPLIED' ]);
			$getCreditDebitLog = $accountSaleCon->debitCreditVoucherLog($creditDebitLogRequest);
			$getCreditDebitLog = $getCreditDebitLog->getData();

			if($getCreditDebitLog->status == 'fail') {
				return response()->json((array)$getCreditDebitLog);
			}
			//call event for push deposite data
			$db_structure = DB::table('vendor')->select('db_structure')->where('id', $request->v_id)->first()->db_structure;
			$clientIntregated=getIsIntegartionAttribute($request->v_id);
            if(isset($request->payment_id) && $clientIntregated){
                $zwingTagVId = '<ZWINGV>'.$request->v_id.'<EZWINGV>';
                $zwingTagStoreId = '<ZWINGSO>'.$request->store_id.'<EZWINGSO>';
                $zwingTagTranId = '<ZWINGTRAN>'.$request->payment_id.'<EZWINGTRAN>';
                event(new DepositeRefund([
                    'payment_id' => $request->payment_id,
                    'v_id' => $request->v_id,
                    'store_id' => $request->store_id,
                    'db_structure' => $db_structure,
                    'type'=>'SALES',
                    'zv_id' => $zwingTagVId,
                    'zs_id' => $zwingTagStoreId,
                    'zt_id' => $zwingTagTranId
                    ])
                );
            }
			// $order = Order::where('order_id', $request->order_id)->first();
			// if($order->payment_status == 'Incomplete') {
			// 	$order->status = 'pending';
			// 	$order->save();
			// }
			$summary = $this->getOrderResponse([ 'order' => $order, 'v_id' => $request->v_id, 'trans_from' => $request->trans_from ]);

			// Delete Cart Data
			$cartIdList = Cart::where('order_id', $order->o_id)->where('v_id', $request->v_id)->where('store_id', $request->store_id)->where('user_id', $request->c_id)->get(['cart_id']);
	        CartDetails::whereIn('cart_id', $cartIdList)->delete();
	        CartOffers::whereIn('cart_id', $cartIdList)->delete();
	        Cart::whereIn('cart_id', $cartIdList)->delete();

	        // Cash Entry
	        $this->cashTransactionEntry($request);

	  //       $order = Order::where('order_id', $request->order_id)->first();
			// if($order->payment_status == 'Incomplete') {
			// 	$order->status = 'pending';
			// 	$order->save();
			// }

			return response()->json([ 'status' => 'payment_save', 'redirect_to_qr' => false, 'message' => 'Save Payment', 'data' => $getCreditNote, 'order_summary' => $summary, 'transaction_type' => 'order-for-order' ], 200); 
		} catch (Exception $e) {
			DB::rollback();
		}
	}

	public function paymentEntry(Request $request)
	{
		$isKeyExists = true;
        $getColumns = new Payment;
        $paymnetsColumns = collect($getColumns->getTableColumns());
        $paymnetsColumns = $paymnetsColumns->filter(function($item) {
            return !in_array($item, ['payment_id','bank','wallet','vpa','deleted_at','created_at','updated_at','payment_invoice_id','date','time','month','year','channel_id','mop_id','payment_gateway_status']) ? $item : '';
        })->map(function($val) use(&$request, &$isKeyExists) {
            if(!$request->has($val)){
                $isKeyExists = false;
            }
            return $val;
        })
        ->values();
        
        if(!$isKeyExists) {
            return response()->json(["status" => 'fail' ,'message' => 'Requrired column not found on request (Payment).']);
        }

        // Payment Mapping Checker
		$exsitsMop = Mop::where([ 'code' => $request->method, 'status' => '1' ])->first();
		if(empty($exsitsMop)) {
			return response()->json([ 'status' => 'fail', 'message' => 'MOP not found' ]);
		}

        $createPayment = Payment::create([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'order_id' => $request->order_id, 'invoice_id' => $request->invoice_id, 'session_id' => $request->session_id, 'terminal_id' => $request->terminal_id, 'user_id' => $request->user_id, 'pay_id' => $request->pay_id, 'amount' => $request->amount, 'method' => $request->method, 'cash_collected' => $request->cash_collected, 'cash_return' => $request->cash_return, 'error_description' => $request->error_description, 'status' => $request->status, 'payment_type' => $request->payment_type, 'payment_gateway_type' => $request->payment_gateway_type, 'payment_gateway_device_type' => $request->payment_gateway_device_type, 'gateway_response' => $request->gateway_response, 'ref_txn_id' => $request->ref_txn_id, 'date' => date('Y-m-d'), 'time' => date('H:i:s'), 'month' => date('m'), 'year' => date('Y'), 'channel_id' => $request->channel_id, 'mop_id' => $exsitsMop->id ]);

        return response()->json(["status" => 'success' ,'message' => 'Payment created successfully.', 'data' => $createPayment ]);
	}

	public function cancelOrder(Request $request)
	{
		// return $request->all();
		$order = Order::where([ 'v_id' => $request->v_id, 'vu_id' => $request->vu_id, 'store_id' => $request->store_id, 'order_id' => $request->order_id ])->first();

		if($order->status == 'cancel') {
			return response()->json(["status" => 'fail' ,'message' => 'Order already cancelled. Please refresh your application.' ]);
		}

		if(format_number($order->total_payment) > 0) {
			$amount = $order->payments->sum('amount');
			$settlementSession = SettlementSession::select('id')->where([ 'v_id' => $request->v_id , 'store_id' => $request->store_id , 'vu_id' => $request->vu_id , 'trans_from' => $request->trans_from ])->orderBy('opening_time','desc')->first();
			$session_id = 0;
			if($settlementSession) {
				$session_id = $settlementSession->id;
			}
			if($request->refund_via == 'cash') {
				// Credit Note Payment Entry & Tagging with order
				$orderPaymentRequest = new \Illuminate\Http\Request();
				$orderPaymentRequest->merge([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'order_id' => $request->order_id, 'invoice_id' => null, 'session_id' => $session_id, 'terminal_id' => $request->terminal_id, 'user_id' => $order->user_id, 'pay_id' => '', 'amount' => -format_number($amount), 'method' => 'cash', 'cash_collected' => 0, 'cash_return' => 0, 'error_description' => '', 'status' => 'success', 'payment_type' => 'full', 'payment_gateway_type' => 'CASH', 'payment_gateway_device_type' => '', 'gateway_response' => '', 'ref_txn_id' => '', 'channel_id' => '1', 'trans_type' => 'Invoice' ]);
				$getPaymentResponse = $this->paymentEntry($orderPaymentRequest);
				$getPaymentResponse = $getPaymentResponse->getData();

				if($getPaymentResponse->status == 'fail') {
					return response()->json((array)$getPaymentResponse);
				}
				$cashRequest = new \Illuminate\Http\Request();
				$cashRequest->merge([ 'amount' => $amount, 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'terminal_id' => $request->terminal_id, 'method' => 'cash', 'order_id' => $request->order_id, 'vu_id' => $request->vu_id, 'is_refund' => true ]);
				$this->cashTransactionEntry($cashRequest);
			} else {

				$accountSaleCon = new AccountsaleController;
				$today_date = date('Y-m-d H:i:s');
		        $next_date =  date('Y-m-d H:i:s' ,strtotime('+30 days', strtotime($today_date)));
		
				// Deposit Creation
				$depositRequest = new \Illuminate\Http\Request();
				$depositRequest->merge([ 'v_id' => $request->v_id, 'src_store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'user_id' => $order->user_id, 'terminal_id' => $request->terminal_id, 'trans_type' => 'Credit', 'trans_sub_type' => 'Credit-Note', 'trans_src_ref' => $order->order_id, 'trans_src' => 'order', 'amount' => format_number(abs($amount)), 'status' => 'Success', 'trans_from' => $request->trans_from, 'dep_rfd_trans' => '', 'remark' => '' ]);
				$getDepositData = $accountSaleCon->depositCreation($depositRequest);
				$getDepositData = $getDepositData->getData();
				
				if($getDepositData->status == 'fail') {
					return response()->json((array)$getDepositData);
				}

				// Voucher Creation & Deposit Unique Tagging
				$creditDebitNoteGenerationRequest = new \Illuminate\Http\Request();
				$creditDebitNoteGenerationRequest->merge([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'user_id' => $order->user_id, 'dep_ref_trans_ref' => $getDepositData->data->id, 'ref_id' => $order->order_id, 'type' => 'voucher_credit', 'amount' => format_number(abs($amount)), 'status' => 'unused', 'effective_at' => date('Y-m-d H:i:s'), 'expired_at' => $next_date ]);
				$getCreditDebitNoteData = $accountSaleCon->debitCreditNoteGeneration($creditDebitNoteGenerationRequest);
				$getCreditDebitNoteData = $getCreditDebitNoteData->getData();

				if($getCreditDebitNoteData->status == 'fail') {
					return response()->json((array)$getCreditDebitNoteData);
				}

				// Credit & Debit Note Log Creation
				$creditDebitLogRequest = new \Illuminate\Http\Request();
				$creditDebitLogRequest->merge([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'user_id' => $order->user_id, 'trans_src' => 'Deposite', 'trans_src_ref_id' => $getDepositData->data->id, 'order_id' => $order->o_id, 'applied_amount' => format_number(abs($amount)), 'voucher_id' => $getCreditDebitNoteData->data->id, 'status' => 'APPLIED' ]);
				$getCreditDebitLog = $accountSaleCon->debitCreditVoucherLog($creditDebitLogRequest);
				$getCreditDebitLog = $getCreditDebitLog->getData();

				if($getCreditDebitLog->status == 'fail') {
					return response()->json((array)$getCreditDebitLog);
				}

				// Payment Creation & Taggin Deposit
				$depPaymentRequest = new \Illuminate\Http\Request();
				$depPaymentRequest->merge([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'order_id' => $order->order_id, 'invoice_id' => null, 'session_id' => $session_id, 'terminal_id' => $request->terminal_id, 'user_id' => $order->user_id, 'pay_id' => $getCreditDebitNoteData->data->voucher_no, 'amount' => -format_number(abs($amount)), 'method' => 'credit_note_issued', 'cash_collected' => 0, 'cash_return' => 0, 'error_description' => '', 'status' => 'success', 'payment_type' => 'full', 'payment_gateway_type' => 'VOUCHER', 'payment_gateway_device_type' => '', 'gateway_response' => '', 'ref_txn_id' => '', 'channel_id' => '1', 'trans_type' => 'Invoice' ]);
				$getCreditNote = $this->paymentEntry($depPaymentRequest);
				$getCreditNote = $getCreditNote->getData();

				if($getCreditNote->status == 'fail') {
					return response()->json((array)$getCreditNote);
				}

				// Send Credit Note
				// $today_date = date('Y-m-d H:i:s');
		         // $next_date =  date('Y-m-d H:i:s' ,strtotime('+30 days', strtotime($today_date)));
		         // $dates = explode(' ',$next_date);
				// $creditNoteRequest = new \Illuminate\Http\Request();
				// $voucherList = $order->payments->pluck('amount','pay_id')->toArray();
				// $creditNoteRequest->merge([ 'mobile' => $order->user->mobile, 'list' => $voucherList, 'date' => date('d-M-Y', strtotime($dates[0])) ]);
				// $this->sendSMS($creditNoteRequest);

				// Send Credit Note
				$voucherList = [];
				$today_date = date('Y-m-d H:i:s');
		        $next_date =  date('Y-m-d H:i:s' ,strtotime('+30 days', strtotime($today_date)));
		        $dates = explode(' ',$next_date);
				$creditNoteRequest = new \Illuminate\Http\Request();
				$voucherList[$getCreditDebitNoteData->data->voucher_no] = format_number(abs($amount));
				$creditNoteRequest->merge([ 'mobile' => $order->user->mobile, 'list' => $voucherList, 'date' => date('d-M-Y', strtotime($dates[0])) ]);
				$this->sendSMS($creditNoteRequest);
			}
		}

		$order->status = 'cancel';
		$order->remark = $request->remark;
		$order->save();
		
		return response()->json([ 'status' => 'success', 'message' => 'Order successfully cancelled' ]);
	}

	public function sendSMS(Request $request)
	{
		$username = "roxfortgroup@gmail.com";
        $hash = "bb7a4a8a91634f50df712fdf5c11ef6745ea735b85afc9443d23ec9ae3e8b711";
        $test = "0";
        $sender = "MZWING";
        $mobile = $request->mobile;

        foreach ($request->list as $key => $value) {
        	$numbers = "91".$mobile;
	        $message = "You have received a voucher of Rs ".format_number($value).". Your code is ".$key." Expire at ".$request->date.". one time use only";
	        $message = urlencode($message);
	        $data = "username=".$username."&hash=".$hash."&message=".$message."&sender=".$sender."&numbers=".$numbers."&test=".$test;
	        $ch = curl_init('http://api.textlocal.in/send/?');
	        curl_setopt($ch, CURLOPT_POST, true);
	        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	        $result = curl_exec($ch); 
	        curl_close($ch);
        }
	}

	public function orderConfirmDetails(Request $request)
	{
		// DB::enableQueryLog();
		$store =  Store::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id ])->first();
		$productList = [];
		$order = Order::where([ 'v_id' => $request->v_id, 'vu_id' => $request->vu_id, 'store_id' => $request->store_id, 'order_id' => $request->order_id ])->first();

		foreach ($order->list as $key => $value) {
			$productList[$key] = ['barcode' => $value->barcode, 'qty' => $value->qty, 'item_name' => $value->item_name, 'unit_mrp' => $value->unit_mrp, 'total_discount' => $value->total_discount, 'tax' => $value->tax, 'total' => $value->total, 'id' => $value->id, 'replaced' => '', 'is_discarded' => false, 'original_barcode' => $value->barcode, 'serial' => 0, 'batch' => 0 ];

			// $getStockList = StockPointSummary::select('qty','stock_point_id')->where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'barcode' => $value->item_id, 'batch_id' => $value->batch_id, 'serial_id' => $value->serial_id ])->get();

			$getStockList = StockPointSummary::select('qty','stock_point_id')->where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'barcode' => $value->item_id, 'stock_point_id' => $store->sellable_stock_point->id, 'batch_id' => $value->batch_id, 'serial_id' => $value->serial_id ])->get();

			if($getStockList->sum('qty') > 0) {
				$stockPointIdExists = $getStockList->where('stock_point_id', $store->sellable_stock_point->id)->first();
				if(empty($stockPointIdExists)) {
					$getAvailableStockPoint = $getStockList->where('stock_point_id', '!=' , $store->sellable_stock_point->id)->where('qty', '>', '0')->first();
					$productList[$key]['message'] = 'Stock available in '.$getAvailableStockPoint->point->name.'. Please transfer product to shelf';
					$productList[$key]['is_sellable'] = false;
					$productList[$key]['is_available'] = false;
					$productList[$key]['available_qty'] = 0;
					$productList[$key]['stock_point_name'] = $getAvailableStockPoint->point->name;
				} else {
					$productList[$key]['message'] = $stockPointIdExists->qty <= 0 ? 'Please add stock in shelf stock point' : '';
					$productList[$key]['is_sellable'] = true;
					$productList[$key]['is_available'] = $stockPointIdExists->qty <= 0 ? false : true;
					$productList[$key]['available_qty'] = $stockPointIdExists->qty;
					$productList[$key]['stock_point_name'] = $stockPointIdExists->point->name;
				}
			} else {
				$productList[$key]['message'] = 'Stock not available in any stock point.';
				$productList[$key]['is_available'] = false;
			}
		}

		$orderDetails = ['qty' => $order->qty, 'subtotal' => $order->subtotal, 'total' => $order->total, 'discount' => $order->discount, 'tax' => $order->tax ];

		return response()->json([ 'status' => 'success', 'data' => $productList, 'user_details' => [ 'comm_trans' => $order->comm_trans, 'cust_gstin' => $order->cust_gstin], 'order_details' =>  $orderDetails ], 200);
		// dd(DB::getQueryLog());
	}

	public function getProductDetails(Request $request)
	{
		DB::enableQueryLog();
		$batches = $serials = [];
		$productRequest = new \Illuminate\Http\Request();
		$stockQty = 0;
		$prodQty = $request->qty;
		if($request->type == 'add' && !$request->weight) {
			$prodQty = 1;
		}

		$productRequest->merge([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'barcode' => $request->barcode, 'trans_from' => $request->trans_from, 'vu_id' => $request->vu_id, 'qty' => $prodQty, 'is_product_details' => true ]);

		/* Update multiple mrp of a product */
		if($request->has('change_mrp') && !empty($request->change_mrp)) {
			$productRequest->merge([ 'change_mrp' => $request->change_mrp ]);
		}

		$productCon = new ProductController;
		$getProductDetails = $productCon->product_details($productRequest)->getData();
		// dd($getProductDetails);
		if($getProductDetails->status == 'product_not_found') {
			return response()->json([ 'status' => 'fail', 'data' => $getProductDetails->message ], 200);
		}
		if($getProductDetails->status == 'fail') {
			return response()->json([ 'status' => 'fail', 'data' => $getProductDetails->message ], 200);
		}
		$itemDet = json_decode(urldecode($getProductDetails->data->item_det));
		$itemDetails = VendorSku::where([ 'sku_code' => $itemDet->sku_code, 'sku' => $itemDet->sku, 'vendor_sku_detail_id' => $itemDet->vendor_sku_detail_id ])->first();
		if(empty($itemDetails)) {
			return response()->json([ 'status' => 'fail', 'data' => 'Product Not found' ], 200);
		}
		$storeDetails = Store::select('gst','store_id')->where('store_id', $request->store_id)->first();
		$itemStock = StockPointSummary::select('qty','stock_point_id')->where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'barcode' => $request->barcode, 'stock_point_id' => $storeDetails->sellable_stock_point->id, 'serial_id' => $getProductDetails->data->serial_id, 'batch_id' => $getProductDetails->data->batch_id ])->first();
		$vendorAuth = Vendor::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'id' => $request->vu_id ])->first();
		if(in_array($vendorAuth->order_inventory_blocking_level['setting'], ['order_confirmed', 'order_packed'])) {
			if($request->from_screen == 'pack') {
				if(empty($itemStock)) {
					return response()->json([ 'status' => 'fail', 'message' => 'Stock not available' ], 200);
				}
			} else {
				$stockQty = $itemStock->qty;
			}
			if($request->from_screen == 'confirm') {
				if(empty($itemStock)) {
					return response()->json([ 'status' => 'fail', 'message' => 'Stock not available' ], 200);
				}
			} else {
				$stockQty = $itemStock->qty;
			}
		} else {
			if(!empty($itemStock)) {
				$stockQty = $itemStock->qty;
			}
		}
		
		$taxParams = ['barcode' => $request->barcode, 'qty' => $request->qty, 's_price' => $getProductDetails->data->s_price, 'hsn_code' => $itemDet->hsn_code, 'store_id' => $request->store_id, 'v_id' => $request->v_id, 'from_gstin' => $storeDetails->gst , 'to_gstin' => $request->cust_gstin , 'invoice_type' => $request->comm_trans ];
		$cartCon = new CartController;
        $taxDetails = $cartCon->taxCal($taxParams);
        
        $isAvail = false;
        // return $stockQty;
        if($stockQty > 0) {
        	$isAvail = true;
        }

        $productSTotal = format_number($getProductDetails->data->s_price / $prodQty);
        $productSSubtotal = format_number($getProductDetails->data->unit_mrp / $prodQty);

		$productDetails = ['barcode' => $request->barcode, 'qty' => $prodQty, 'item_name' => $itemDetails->name, 'unit_mrp' => $getProductDetails->data->unit_mrp, 'total_discount' => $getProductDetails->data->discount, 'tax' => $taxDetails['tax'], 'total' => $getProductDetails->data->s_price, 'id' => 0, 'message' => '', 'is_sellable' => true, 'is_available' => $isAvail, 'available_qty' => $stockQty, 'stock_point_name' => @$itemStock->point->name, 'weight_flag' => $itemDetails->selling_uom_type == 'WEIGHT' ? true : false, 'stotal' => $productSTotal, 'ssubtotal' => $productSSubtotal ];

		// Check Batch List
		if($itemDetails->has_batch == 1) {
          $grnData = GrnList::where([ 'barcode' => $request->barcode, 'v_id' => $request->v_id, 'store_id' => $request->store_id ])->orderBy('id','desc')->get();
          foreach ($grnData as $gdata) {
            foreach ($gdata->batches as $batch) {
              if($batch->batch_no != '') {
                $validty = !empty($batch->valid_months) ? $batch->valid_months : 'N/A';
                $batchStock = StockPointSummary::select('qty','stock_point_id')->where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'barcode' => $request->barcode, 'stock_point_id' => $storeDetails->sellable_stock_point->id, 'batch_id' => $batch->id ])->first();
                $batches[] = [ 'id' => $batch->id, 'code' => $batch->batch_no, 'mfg_date' => emptyCheker($batch->mfg_date), 'exp_date' => emptyCheker($batch->exp_date), 'validty' => $validty, 'type' => 'batch', 'mrp' => $batch->priceDetail->mrp, 'stock' => empty($batchStock->qty) ? 0 : $batchStock->qty ];
              }
            } 
          }
        }

        // Check Serial List
        if($itemDetails->has_serial == 1) {
	        $grnData = GrnList::where([ 'barcode' => $request->barcode, 'v_id' => $request->v_id, 'store_id' => $request->store_id ])->orderBy('id','desc')->get();
	        foreach ($grnData as $gdata) {
	          foreach ($gdata->serials as $serial) {
	            $serials[]  = [ 'id' => $serial->id, 'code' => $serial->serial_no, 'type' => 'serial', 'mrp' => $serial->priceDetail->mrp ];
	          } 
	        }
	    }

	    $productDetails['batch'] = $batches;
		$productDetails['serial'] = $serials;
		$productDetails['has_batch'] = $itemDetails->has_batch == 1 ? true : false;
		$productDetails['has_serial'] = $itemDetails->has_serial == 1 ? true : false;

		if($productDetails['has_batch'] && count($productDetails['batch']) == 0) {
			return response()->json(['status' => 'fail', 'data' => 'This product cannot be added as no batch has been added. Please contact your store manager'], 200);
		}

		if($productDetails['has_serial'] && count($productDetails['serial']) == 0) {
			return response()->json(['status' => 'fail', 'data' => 'This product cannot be added as no serial has been added. Please contact your store manager'], 200);
		}

		// dd(DB::getQueryLog());

		return response()->json([ 'status' => 'success', 'data' => $productDetails ], 200);
	}

	public function confirmOrder(Request $request)
	{
		// return $request->all();
		$productList = json_decode($request->product_list);
		$productList = collect($productList);
		$order = Order::where([ 'v_id' => $request->v_id, 'vu_id' => $request->vu_id, 'store_id' => $request->store_id, 'order_id' => $request->order_id ])->first();

		// Review Checker
		if($request->has('is_review') && !$request->is_review) {
			$order->status = 'confirm';
			$order->save();
			return response()->json([ 'status' => 'success', 'message' => 'Order confirm successfully.' ], 200);
		}

		// Check Order total amount is less then of payment
		$productTotal = $productList->sum('total') + abs($order->round_off);
		if(!$request->has('via') && $order->total_payment > $productTotal) {
			$refundAmt = format_number($order->total_payment - $productTotal);
			return response()->json([ 'status' => 'info', 'message' => 'New order total is less than previous payments. Remaining amount will be refunded via', 'refund_amount' =>  $refundAmt, 'type' => 'lower' ]);
		}

		// Check Order total amount is grater then of payment
		if(!$request->has('via') && $order->total < $productTotal) {
			$refundAmt = format_number($productTotal - $order->total);
			return response()->json([ 'status' => 'info', 'message' => 'New order total is greater than the previous order.', 'refund_amount' =>  $refundAmt, 'type' => 'greater' ]);
		}

		// dd($productList);

		foreach ($order->list as $key => $value) {
			$isValidation = false;
			if(!in_array($value->barcode, $productList->pluck('barcode')->toArray())) {
				if(in_array($value->barcode, $productList->pluck('replaced')->toArray())) {
					$isValidation = true;
					OrderDetails::find($value->id)->delete();
				} else {
					OrderDetails::find($value->id)->delete();
				}
			} else {
				if(in_array($value->barcode, $productList->pluck('replaced')->toArray())) {
					$isValidation = true;
					OrderDetails::find($value->id)->delete();
				}
			}

			if($isValidation) {
				$product = $productList->where('replaced', $value->barcode)->first();
				$productRequest = new \Illuminate\Http\Request();
				$productRequest->merge([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'barcode' => $product->barcode, 'vu_id' => $request->vu_id, 'qty' => $product->qty, 'is_product_details' => true, 'serial_id' => $product->serial, 'batch_id' => $product->batch ]);
				$productCon = new ProductController;
				$getProductDetails = $productCon->product_details($productRequest)->getData()->data;
				// dd($getProductDetails);
				$itemDet = json_decode(urldecode($getProductDetails->item_det));
				$itemDetails = VendorSku::where([ 'sku_code' => $itemDet->sku_code, 'sku' => $itemDet->sku, 'vendor_sku_detail_id' => $itemDet->vendor_sku_detail_id ])->first();
				if(empty($itemDetails)) {
					return response()->json([ 'status' => 'fail', 'data' => 'Product Not found' ], 200);
				}
				$storeDetails = Store::select('gst','store_id')->where('store_id', $request->store_id)->first();
				$taxParams = ['barcode' => $product->barcode, 'qty' => $product->qty, 's_price' => $getProductDetails->s_price, 'hsn_code' => $itemDet->hsn_code, 'store_id' => $request->store_id, 'v_id' => $request->v_id, 'from_gstin' => $storeDetails->gst , 'to_gstin' => $request->cust_gstin , 'invoice_type' => $request->comm_trans ];
				$cartCon = new CartController;
		        $taxDetails = $cartCon->taxCal($taxParams);
				// Entry Order
				$orderDetails = OrderDetails::create([ 'transaction_type' => 'sales', 'store_id' => $request->store_id, 'v_id' => $request->v_id, 'order_id' => $value->order_id, 't_order_id' => $order->od_id, 'user_id' => $value->user_id, 'weight_flag' => (string)$getProductDetails->weight_flag, 'plu_barcode' => $value->plu_barcode, 'barcode' => $getProductDetails->barcode, 'item_name' => $itemDetails->name, 'item_id' =>  $getProductDetails->barcode, 'batch_id' => $getProductDetails->batch_id, 'serial_id' => $getProductDetails->serial_id, 'qty' => (string)$getProductDetails->qty, 'subtotal' => format_number($getProductDetails->r_price), 'unit_mrp' => format_number($getProductDetails->unit_mrp), 'unit_csp' => format_number($getProductDetails->unit_mrp), 'discount' => format_number($getProductDetails->discount), 'tax' => format_number($product->tax), 'total' => format_number($getProductDetails->s_price), 'status' => 'process', 'vu_id' => $request->vu_id, 'trans_from' => $request->trans_from, 'date' => date('Y-m-d'), 'time' => date('H:i:s'), 'month' => date('m'), 'year' => date('Y'), 'department_id' => $itemDet->department_id, 'group_id' => $itemDet->SECTION_CODE, 'division_id' => $itemDet->DIVISION_CODE, 'subclass_id' => $itemDet->ARTICLE_CODE, 'printclass_id' => isset($getProductDetails->get_assortment_count) ? $getProductDetails	->get_assortment_count : 0, 'section_target_offers' => json_encode($getProductDetails), 'pdata' => urldecode($getProductDetails->pdata), 'tdata' => json_encode($taxDetails), 'is_catalog' => 0, 'sku_code' => $itemDet->sku_code ]);
			}



		}
		
		// Re-calculate order values
		$newOrder = Order::where([ 'v_id' => $request->v_id, 'vu_id' => $request->vu_id, 'store_id' => $request->store_id, 'order_id' => $request->order_id ])->first();
		$rOffValue = 0;
		$newOrder->qty = $newOrder->list->sum('qty');
		$newOrder->subtotal = format_number($newOrder->list->sum('subtotal'));
		$newOrder->discount = format_number($newOrder->list->sum('discount'));
		$newOrder->tax = format_number($newOrder->list->sum('tax'));
		$prod_total = format_number($newOrder->list->sum('total'));
		if (!empty(getRoundValue($newOrder->product_total_amount))) {
			$rOffValue = getRoundValue($newOrder->product_total_amount);
			$newOrder->round_off = (string)$rOffValue;
		}
		$newOrder->total = format_number($newOrder->list->sum('total')) + format_number($rOffValue);
		$newOrder->status = 'confirm';
		$newOrder->save();

		// If order amount greater than payable amount by customer
		if($newOrder->total_payment > $newOrder->total) {
			$settlementSession = SettlementSession::select('id')->where([ 'v_id' => $request->v_id , 'store_id' => $request->store_id , 'vu_id' => $request->vu_id , 'trans_from' => $request->trans_from ])->orderBy('opening_time','desc')->first();
			$session_id = 0;
			if($settlementSession) {
				$session_id = $settlementSession->id;
			}
			if($request->has('via') && $request->via == 'cash') {
				// Credit Note Payment Entry & Tagging with order
				$orderPaymentRequest = new \Illuminate\Http\Request();
				$orderPaymentRequest->merge([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'order_id' => $request->order_id, 'invoice_id' => null, 'session_id' => $session_id, 'terminal_id' => $request->terminal_id, 'user_id' => $newOrder->user_id, 'pay_id' => '', 'amount' => format_number($newOrder->remaining_payment), 'method' => 'cash', 'cash_collected' => 0, 'cash_return' => 0, 'error_description' => '', 'status' => 'success', 'payment_type' => 'full', 'payment_gateway_type' => 'CASH', 'payment_gateway_device_type' => '', 'gateway_response' => '', 'ref_txn_id' => '', 'channel_id' => '1', 'trans_type' => 'Invoice' ]);
				$getPaymentResponse = $this->paymentEntry($orderPaymentRequest);
				$getPaymentResponse = $getPaymentResponse->getData();

				if($getPaymentResponse->status == 'fail') {
					return response()->json((array)$getPaymentResponse);
				}
				$cashRequest = new \Illuminate\Http\Request();
				$cashRequest->merge([ 'amount' => abs($newOrder->remaining_payment), 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'terminal_id' => $request->terminal_id, 'method' => 'cash', 'order_id' => $request->order_id, 'vu_id' => $request->vu_id, 'is_refund' => true ]);
				$this->cashTransactionEntry($cashRequest);
			} else {
				$accountSaleCon = new AccountsaleController;
				$today_date = date('Y-m-d H:i:s');
		        $next_date =  date('Y-m-d H:i:s' ,strtotime('+30 days', strtotime($today_date)));
		
				// Deposit Creation
				$depositRequest = new \Illuminate\Http\Request();
				$depositRequest->merge([ 'v_id' => $request->v_id, 'src_store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'user_id' => $newOrder->user_id, 'terminal_id' => $request->terminal_id, 'trans_type' => 'Credit', 'trans_sub_type' => 'Refund-CN', 'trans_src_ref' => $newOrder->order_id, 'trans_src' => 'order', 'amount' => format_number(abs($newOrder->remaining_payment)), 'status' => 'Success', 'trans_from' => $request->trans_from, 'dep_rfd_trans' => '', 'remark' => '' ]);
				$getDepositData = $accountSaleCon->depositCreation($depositRequest);
				$getDepositData = $getDepositData->getData();
				
				if($getDepositData->status == 'fail') {
					return response()->json((array)$getDepositData);
				}

				// Voucher Creation & Deposit Unique Tagging
				$creditDebitNoteGenerationRequest = new \Illuminate\Http\Request();
				$creditDebitNoteGenerationRequest->merge([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'user_id' => $newOrder->user_id, 'dep_ref_trans_ref' => $getDepositData->data->id, 'ref_id' => $newOrder->order_id, 'type' => 'voucher_credit', 'amount' => format_number(abs($newOrder->remaining_payment)), 'status' => 'unused', 'effective_at' => date('Y-m-d H:i:s'), 'expired_at' => $next_date ]);
				$getCreditDebitNoteData = $accountSaleCon->debitCreditNoteGeneration($creditDebitNoteGenerationRequest);
				$getCreditDebitNoteData = $getCreditDebitNoteData->getData();

				if($getCreditDebitNoteData->status == 'fail') {
					return response()->json((array)$getCreditDebitNoteData);
				}

				// Credit & Debit Note Log Creation
				$creditDebitLogRequest = new \Illuminate\Http\Request();
				$creditDebitLogRequest->merge([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'user_id' => $newOrder->user_id, 'trans_src' => 'Deposite', 'trans_src_ref_id' => $getDepositData->data->id, 'order_id' => $order->o_id, 'applied_amount' => format_number(abs($newOrder->remaining_payment)), 'voucher_id' => $getCreditDebitNoteData->data->id, 'status' => 'APPLIED' ]);
				$getCreditDebitLog = $accountSaleCon->debitCreditVoucherLog($creditDebitLogRequest);
				$getCreditDebitLog = $getCreditDebitLog->getData();

				if($getCreditDebitLog->status == 'fail') {
					return response()->json((array)$getCreditDebitLog);
				}

				// Payment Creation & Taggin Deposit
				$depPaymentRequest = new \Illuminate\Http\Request();
				$depPaymentRequest->merge([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'order_id' => $newOrder->order_id, 'invoice_id' => null, 'session_id' => $session_id, 'terminal_id' => $request->terminal_id, 'user_id' => $newOrder->user_id, 'pay_id' => $getCreditDebitNoteData->data->voucher_no, 'amount' => -format_number(abs($newOrder->remaining_payment)), 'method' => 'credit_note_issued', 'cash_collected' => 0, 'cash_return' => 0, 'error_description' => '', 'status' => 'success', 'payment_type' => 'full', 'payment_gateway_type' => 'VOUCHER', 'payment_gateway_device_type' => '', 'gateway_response' => '', 'ref_txn_id' => '', 'channel_id' => '1', 'trans_type' => 'Invoice' ]);
				$getCreditNote = $this->paymentEntry($depPaymentRequest);
				$getCreditNote = $getCreditNote->getData();

				if($getCreditNote->status == 'fail') {
					return response()->json((array)$getCreditNote);
				}


				// Send Credit Note
				$voucherList = [];
		        $dates = explode(' ',$next_date);
				$creditNoteRequest = new \Illuminate\Http\Request();
				$voucherList[$getCreditDebitNoteData->data->voucher_no] = format_number(abs($newOrder->remaining_payment));
				$creditNoteRequest->merge([ 'mobile' => $newOrder->user->mobile, 'list' => $voucherList, 'date' => date('d-M-Y', strtotime($dates[0])) ]);
				$this->sendSMS($creditNoteRequest);

				//call event for push deposite data
				$db_structure = DB::table('vendor')->select('db_structure')->where('id', $request->v_id)->first()->db_structure;
				$clientIntregated=getIsIntegartionAttribute($request->v_id);
	            if(isset($getCreditNote->data->payment_id) && $clientIntregated){
	                $zwingTagVId = '<ZWINGV>'.$request->v_id.'<EZWINGV>';
	                $zwingTagStoreId = '<ZWINGSO>'.$request->store_id.'<EZWINGSO>';
	                $zwingTagTranId = '<ZWINGTRAN>'.$getCreditNote->data->payment_id.'<EZWINGTRAN>';
	                event(new DepositeRefund([
	                    'payment_id' => $getCreditNote->data->payment_id,
	                    'v_id' => $request->v_id,
	                    'store_id' => $request->store_id,
	                    'db_structure' => $db_structure,
	                    'type'=>'SALES',
	                    'zv_id' => $zwingTagVId,
	                    'zs_id' => $zwingTagStoreId,
	                    'zt_id' => $zwingTagTranId
	                    ])
	                );
	            }
			}
		}

		if(!$request->has('from_pack')) {
			$vendorAuth = Vendor::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'id' => $request->vu_id ])->first();
			if($vendorAuth->order_inventory_blocking_level['setting'] == 'order_confirmed') {
				$orderStockPointResponse = $this->movingStockToOrderStockPoint($request);
				$orderStockPointResponse = $orderStockPointResponse->getData();
				if($orderStockPointResponse->status == 'fail') {
					$newOrder->status = 'pending';
					$newOrder->save();
					return response()->json((array)$orderStockPointResponse);
				}
			}
		}


		return response()->json([ 'status' => 'success', 'message' => 'Order confirm successfully.' ], 200);
	}

	public function paymentOrderDetails(Request $request)
	{
		$order = Order::where(['store_id' => $request->store_id, 'v_id' => $request->v_id, 'order_id' => $request->order_id])->first();
		$orderSummary = $this->getOrderResponse(['order' => $order , 'v_id' => $request->v_id , 'trans_from' => $request->trans_from ]);
		$customerInfo['data'] = [ 'mobile' => $order->user->mobile, 'first_name' => $order->user->first_name, 'last_name' => $order->user->last_name, 'api_token' => $order->user->api_token, 'c_id' => $order->user->c_id ];
		unset($order->user); 
		$order->is_payment = true;
		$order->payment_by = $request->payment_by;

		return response()->json([ 'status' => 'proceed_to_payment', 'message' => 'Proceed to Payment', 'data' => $order, 'order_summary' => $orderSummary, 'transaction_type' => 'order-for-order', 'customer' => $customerInfo ], 200);
	}

	public function getOrderPackerProductDetails(Request $request)
	{
		$productList = [];
		$order = Order::where(['store_id' => $request->store_id, 'v_id' => $request->v_id, 'order_id' => $request->order_id])->first();
		foreach ($order->list as $key => $value) {
			$itemDetails = VendorSku::where([ 'sku_code' => $value->sku_code, 'is_active' => '1', 'deleted_at' => null ])->first();
			$inv_type = "";
			$inventoryList = [];
			$selectedBatch = 0;
			if($itemDetails->has_batch == 1) {
				$inv_type = "batch";
				$grnData = GrnList::where([ 'barcode' => $value->barcode, 'v_id' => $request->v_id, 'store_id' => $request->store_id ])->orderBy('id','desc')->get();
		        	foreach ($grnData as $gdata) {
		            	foreach ($gdata->batches as $batch) {
		             		if($batch->batch_no != '') {
		             			$batchQty = 0;
		             			$batchStock = StockPointSummary::select('qty')->where([ 'barcode' => $value->barcode, 'sku_code' => $value->sku_code, 'batch_id' => $batch->id, 'stock_point_id' => $order->store->sellable_stock_point->id ])->first();
		             			if(!empty($batchStock)) {
		             				$batchQty = $batchStock->qty;
		             			}
		                		$validty = !empty($batch->valid_months)?$batch->valid_months.' Month' : 'N/A';
		                		$inventoryList[]  = ['id' => $batch->id, 'code' => $batch->batch_no, 'mfg_date' => $batch->mfg_date, 'exp_date' => $batch->exp_date, 'validty' => $validty, 'type' => 'batch', 'mrp' => $batch->priceDetail->mrp, 'stock' => $batchQty ];
		              		}
		            	} 
		          	}
			}
			if($itemDetails->has_serial == 1) {
				$inv_type = "serial";
				$grnSerialData = GrnList::where([ 'barcode' => $value->barcode, 'v_id' => $request->v_id, 'store_id' => $request->store_id ])->orderBy('id','desc')->get();
		        	foreach ($grnSerialData as $gdata) {
		            	foreach ($gdata->serials as $serial) {
		             		if($serial->serial_no != '') {
		            			$serialQty = 0;
		             			$stockQty = StockPointSummary::select('qty')->where([ 'barcode' => $value->barcode, 'sku_code' => $value->sku_code, 'serial_id' => $value->id, 'stock_point_id' => $order->store->sellable_stock_point->id ])->first();
		             			if(!empty($stockQty)) {
		             				$serialQty = $stockQty->qty;
		             			}
		                		$inventoryList[]  = [ 'id' => $serial->id, 'code' => $serial->serial_no, 'type' => 'serial' , 'mrp' => $serial->priceDetail->mrp, 'stock' => $serialQty ];
		              		}
		            	} 
		          	}
			}
			if(!empty($inventoryList)) {
				if($inv_type == 'batch') {
					$selectedBatch = collect($inventoryList)->where('id', $value->batch_id)->first();
					$selectedBatch = empty($selectedBatch) ? '' : $selectedBatch['id'];
				} else if($inv_type == 'serial') {
					$selectedBatch = collect($inventoryList)->where('id', $value->serial_id)->first();
					$selectedBatch = empty($selectedBatch) ? '' : $selectedBatch['id'];
				}
			}
			$singleTax = $singleTotal = $singleSubtoal = 0;
			if(!empty($value->tax)) {
				$singleTax = $value->tax / $value->qty;
			}
			$singleTotal = $value->total / $value->qty;
			$singleSubtoal = $value->subtotal / $value->qty;
			$available_qty = 0;
			$is_available = false;
			$getStockList = StockPointSummary::select('qty','stock_point_id')->where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'barcode' => $value->item_id, 'stock_point_id' => $order->store->sellable_stock_point->id, 'batch_id' => $value->batch_id, 'serial_id' => $value->serial_id ])->get();
			if($getStockList->sum('qty') > 0) {
				$available_qty = $getStockList->sum('qty');
				$is_available = true;
			}

			$priceArr  = array('v_id'=>$request->v_id,'store_id'=>$request->store_id,'item'=>$itemDetails,'unit_mrp'=>''); 
			$price = $this->cartconfig->getprice($priceArr);

			$productList[] = [ 'name' => $value->item_name, 'original_barcode' => $value->barcode, 'barcode' => $value->barcode, 'mrp' => $value->unit_mrp, 'subtotal' => $value->subtotal, 'tax' => $value->tax, 'total' => $value->total, 'batch_list' => $inventoryList, 'weight_flag' => $value->weight_flag == '0' ? false : true, 'packed_qty' => 0, 'qty' => (float)$value->qty, 'isPacked' => false, 'stax' => format_number($singleTax), 'stotal' => format_number($singleTotal), 'ssubtotal' => format_number($singleSubtoal), 'selected_batch' => $selectedBatch, 'id' => $value->id, 'is_discarded' => false, 'total_discount' => $value->total_discount, 'available_qty' => $available_qty, 'is_available' => $is_available, 'inv_type' => $inv_type, 'uom' => $itemDetails->selling_uom_name, 'category' => $itemDetails->cat_name_1, 'multiple_price' => $price['mrp_arrs'], 'multiple_price_flag' => $price['multiple_mrp_flag'] ];
		}

		return response()->json([ 'data' => $productList ], 200);
	}

	public function orderPack(Request $request)
	{
		$invoiceSeq = null;
		$session_id = 0;
		$productList = collect(json_decode($request->product_list));
		$totalQty = $productList->sum('qty');
		$totalPackedQty = $productList->sum('packed_qty');
		if($totalQty != $totalPackedQty) {
			return response()->json([ 'status' => 'fail', 'message' => 'Order total qty does not match from packed qty.' ], 200);
		}
		$vendorAuth = Vendor::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'id' => $request->vu_id ])->first();
		// dd($request->all());
		// Replace & Discard Product Checker
		if(in_array($vendorAuth->order_inventory_blocking_level['setting'], ['order_packed','invoice_generated'])) {
			$request->request->add([ 'from_pack' => true ]);
			$confirmResponse = $this->confirmOrder($request)->getData();
			if($confirmResponse->status == 'info') {
				return response()->json((array)$confirmResponse);
			}
		} else {
			// foreach ($productList as $key => $value) {
			// 	if(property_exists($value, 'batch')) {
			// 		OrderDetails::where([ 't_order_id' => $order->od_id, 'v_id' => $request->v_id, 'store_id' => $request->store_id ])->update([ 'batch_id' => $value->batch ]);
			// 	} else if(property_exists($value, 'serial')) { {
			// 		OrderDetails::where([ 't_order_id' => $order->od_id, 'v_id' => $request->v_id, 'store_id' => $request->store_id ])->update([ 'serial_id' => $value->serial ]);
			// 	}
			// }
		}

		$order = Order::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'order_id' => $request->order_id ])->first();
		// Change Status
		$order->status = 'packing';
		$order->save();

		
		if($vendorAuth->order_inventory_blocking_level['setting'] == 'order_packed') {
			$orderStockPointResponse = $this->movingStockToOrderStockPoint($request);
			$orderStockPointResponse = $orderStockPointResponse->getData();
			if($orderStockPointResponse->status == 'fail') {
				$order->status = 'confirm';
				$order->save();
				return response()->json((array)$orderStockPointResponse);
			}
		}

		return response()->json([ 'status' => 'success', 'message' => 'Order Packed successfully.' ], 200);
		$current_date = date('Y-m-d');
		$settlementSession = SettlementSession::select('id')->where(['v_id' => $request->v_id ,'store_id' => $request->store_id , 'vu_id' => $request->vu_id ,'trans_from' => $request->trans_from ])->orderBy('opening_time','desc')->first();
        if($settlementSession){
            $session_id = $settlementSession->id;
        }
		$order = Order::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'order_id' => $request->order_id ])->first();
		// Generate Invoice
		$createInvoice = collect($order)->only(['v_id','store_id','user_id','comm_trans','cust_gstin','cust_gstin_state_id','store_gstin','transaction_sub_type','store_short_code','terminal_id','qty','subtotal','discount','lpdiscount','manual_discount','coupon_discount','bill_buster_discount','tax','total','remark','store_gstin_state_id'])->toArray();
		$createInvoice['invoice_id'] = invoice_id_generate($request->store_id, $order->user_id, $request->trans_from, $invoiceSeq, $request->udidtoken);
		$createInvoice['custom_order_id'] = custom_invoice_id_generate(['store_id' => $request->store_id, 'user_id' => $order->user_id, 'trans_from' => $request->trans_from]);
		$createInvoice['ref_order_id'] = $request->order_id;
		$createInvoice['transaction_type'] = 'sales';
		$createInvoice['vu_id'] = $request->vu_id;
		$createInvoice['store_short_code'] = $order->store->short_code;
		$createInvoice['invoice_sequence'] = invoice_id_generate($request->store_id, $request->user_id, $request->trans_from, $invoiceSeq, $request->udidtoken, 'seq_id');
		$createInvoice['stock_point_id'] = $order->store->sellable_stock_point->id;
		$createInvoice['terminal_name'] = CashRegister::where('udidtoken', $request->udidtoken)->first()->name; 
		$createInvoice['terminal_id'] = $request->terminal_id;
		$createInvoice['tax_details'] = $order->tdata;
		$createInvoice['date'] = date('Y-m-d');
		$createInvoice['time'] = date('h:i:s');
		$createInvoice['month'] = date('m');
		$createInvoice['year'] = date('Y');
		$createInvoice['financial_year'] = getFinancialYear();
		$createInvoice['customer_name'] = $order->customer_name;
		$createInvoice['customer_number'] = $order->user->mobile;
		$createInvoice['customer_email'] = $order->user->email;
		$createInvoice['customer_address'] = @$order->user->address->address1;
		$createInvoice['trans_from'] = $request->trans_from;
		$createInvoice['session_id'] = $session_id;
		$createInvoice['customer_gender'] = @$order->user->gender;
		$createInvoice['customer_dob'] = @$order->user->dob;
		$createInvoice['customer_first_name'] = @$order->user->first_name;
		$createInvoice['customer_last_name'] = @$order->user->last_name;
		$createInvoice['customer_pincode'] = @$order->user->address->pincode;
		$invoiceId = Invoice::create($createInvoice);
		// Invoice Details Entry
		foreach ($productList as $key => $value) {
			$orderDetails = OrderDetails::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'id' => $value->id, 't_order_id' => $order->od_id ])->first();
			$createInvoiceDetails = collect($orderDetails)->forget(['id','t_order_id'])->toArray();
			$createInvoiceDetails['t_order_id'] = $invoiceId->id;
			InvoiceDetails::create($createInvoiceDetails);
		}
		// Stock Entry
		$productDataList = InvoiceDetails::select('barcode','qty')->where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 't_order_id' => $invoiceId->id ])->get()->toArray();
		foreach ($productDataList as $key => $value) {
			$params = ['v_id' => $request->v_id, 'store_id' => $request->store_id, 'barcode' => $value['barcode'], 'qty' => $value['qty'], 'invoice_id' => $invoiceId->invoice_id, 'order_id' => $invoiceId->ref_order_id, 'vu_id' => $request->vu_id, 'trans_from' => $request->trans_from, 'transaction_scr_id' => $invoiceId->id, 'transaction_type' => 'SALE'];
            $this->cartconfig->updateStockQty($params);
		}
		// Change Status
		$order->status = 'packing';
		$order->save();

		// Payment Entry
		Payment::where([ 'v_id' => $order->v_id, 'store_id' => $order->store_id, 'order_id' => $request->order_id, 'user_id' => $order->user_id ])->update([ 'invoice_id' => $invoiceId->invoice_id ]);
		return response()->json([ 'status' => 'success', 'data' => $invoiceId, 'message' => 'Order Packed successfully.' ], 200);
	}

	public function movingStockToOrderStockPoint(Request $request)
	{
		// Check Order Stock Point Created or Not
		$checkOrderStockPoint = StockPoints::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'name' => 'Reserve' ])->first();
		if(empty($checkOrderStockPoint)) {
			return response()->json([ 'status' => 'fail', 'message' => 'Order stock point not created in store.' ], 200);
		}
		// Check Order Stock Point is active or not
		if($checkOrderStockPoint->is_active == '0') {
			return response()->json([ 'status' => 'fail', 'message' => 'Order stock point not active. Please active to continue.' ], 200);
		}

		$order = Order::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'order_id' => $request->order_id ])->first();
		$store = Store::where([ 'store_id' => $request->store_id, 'v_id' => $request->v_id ])->first();

		// Check Stock available
		$unavailableProductList = $availableProductList = [];
		foreach ($order->list as $key => $value) {
			$checkStock = StockPointSummary::select('item_id','variant_sku','stock_point_id','batch_id','serial_id','sku_code', 'qty')->where([ 'v_id' => $value->v_id, 'store_id' => $value->store_id, 'barcode' => $value->barcode, 'sku_code' => $value->sku_code, 'stock_point_id' => $request->has('reverse') ? $store->OrderStockPoint->id : $store->SellableStockPoint->id, 'serial_id' => $value->serial_id, 'batch_id' => $value->batch_id ])->first();
			if(empty($checkStock)) {
				$unavailableProductList[] = [ 'name' => $value->item_name, 'qty' => $value->qty, 'stock' => '', 'barcode' => $value->barcode ];
			} else {
				if($checkStock->qty < 0) {
					$unavailableProductList[] = [ 'name' => $value->item_name, 'qty' => $value->qty, 'stock' => $checkStock->qty, 'batch_id' => $checkStock->batch_id  ];
				} else {
					$availableProductList[] = $checkStock->toArray();
				}
			}
			
		}

		if($request->has('is_stock') && $request->is_stock) {
			if(count($unavailableProductList) > 0) {
				return response()->json([ 'status' => 'fail', 'message' => 'Stock not available.', 'data' => $unavailableProductList ], 200);
			} else {
				return response()->json([ 'status' => 'success', 'message' => 'Stock available.' ], 200);
			}
		}

		if(count($unavailableProductList) > 0) {
			return response()->json([ 'status' => 'fail', 'message' => 'Stock not available.', 'data' => $unavailableProductList ], 200);
		}

		// DB::beginTransaction();

		// try {
			// Move stock self from OMS stock point
			foreach ($order->list as $key => $value) {
				$productDet = collect($availableProductList)->where('sku_code', $value->sku_code)->first();
				$postDataIn = $postDataOut = [ 'variant_sku' => $productDet['variant_sku'], 'sku_code' => $value->sku_code, 'barcode'=> $value->barcode, 'item_id' => $productDet['item_id'], 'store_id' => $value->store_id, 'stock_point_id' => $productDet['stock_point_id'], 'qty' => $value->qty, 'ref_stock_point_id' => 0, 'grn_id' => 0, 'batch_id' => $productDet['batch_id'], 'serial_id' => $productDet['serial_id'], 'v_id' => $request->v_id, 'vu_id' => $request->vu_id, 'transaction_scr_id' => $order->od_id, 'transaction_type' => 'SPT', 'status' => 'POST', 'stock_type' => 'OUT' ];
				if($request->has('reverse')) {
					$postDataIn['ref_stock_point_id'] = $productDet['stock_point_id'];
					$postDataIn['stock_point_id'] = $store->SellableStockPoint->id;
					$postDataIn['stock_type'] = 'IN';
				} else {
					$postDataIn['ref_stock_point_id'] = $productDet['stock_point_id'];
					$postDataIn['stock_point_id'] = $store->OrderStockPoint->id;
					$postDataIn['stock_type'] = 'IN';
				}
				$stockCon = new StockController;
				// Stock Out
				$stockOutRequest = new \Illuminate\Http\Request();
                $stockOutRequest->merge([
                    'v_id'          => $request->v_id,
                    'stockData'     => $postDataOut,
                    'store_id'      => $request->store_id,
                    'trans_from'    => $request->trans_from,
                    'vu_id'         => $request->vu_id
                ]);
				$stockCon->stockOut($stockOutRequest);
				// Stock In
				$stockInRequest = new \Illuminate\Http\Request();
                $stockInRequest->merge([
                    'v_id'          => $request->v_id,
                    'stockData'     => $postDataIn,
                    'store_id'      => $request->store_id,
                    'trans_from'    => $request->trans_from,
                    'vu_id'         => $request->vu_id
                ]);
				$stockCon->stockIn($stockInRequest);
			}

			// DB::commit();

			return response()->json([ 'status' => 'success', 'message' => 'Order Stock posting successfully' ], 200);

		// } catch (Exception $e) {
		// 	DB::rollback();
  //           exit;
		// }
	}

	public function generateInvoice(Request $request)
	{
		$invoiceSeq = null;
		$session_id = 0;

		// Check Invoice generated
		$invoice = Invoice::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'ref_order_id' => $request->order_id ])->first();
		if(!empty($invoice)) {
			return response()->json([ 'status' => 'fail', 'message' => 'Invoice already generated for this order.' ], 200);
		}

		$vendorAuth = Vendor::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'id' => $request->vu_id ])->first();
		if(in_array($vendorAuth->order_inventory_blocking_level['setting'], ['invoice_generated'])) {
			$request->request->add([ 'is_stock' => true ]);
			$inventoryChecker = $this->movingStockToOrderStockPoint($request)->getData();
			if($inventoryChecker->status == 'fail') {
				return response()->json((array)$inventoryChecker);
			}
		}
		// return;
		$order = Order::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'order_id' => $request->order_id, 'status' => 'packing' ])->first();

		// Check payment against order
		if(in_array($order->payment_status, ['Partial', 'Incomplete'])) {
			return response()->json([ 'status' => 'action', 'message' => 'Collect Pending Payment' ], 200);
		}

		$current_date = date('Y-m-d');
		$settlementSession = SettlementSession::select('id')->where(['v_id' => $request->v_id ,'store_id' => $request->store_id , 'vu_id' => $request->vu_id ,'trans_from' => $request->trans_from ])->orderBy('opening_time','desc')->first();
        if($settlementSession){
            $session_id = $settlementSession->id;
        }

		// Generate Invoice
		$createInvoice = collect($order)->only(['v_id','store_id','user_id','comm_trans','cust_gstin','cust_gstin_state_id','store_gstin','transaction_sub_type','store_short_code','terminal_id','qty','subtotal','discount','lpdiscount','manual_discount','coupon_discount','bill_buster_discount','tax','total','remark','store_gstin_state_id', 'round_off'])->toArray();
		$createInvoice['invoice_id'] = invoice_id_generate($request->store_id, $order->user_id, $request->trans_from, $invoiceSeq, $request->udidtoken);
		$createInvoice['custom_order_id'] = custom_invoice_id_generate(['store_id' => $request->store_id, 'user_id' => $order->user_id, 'trans_from' => $request->trans_from]);
		$createInvoice['ref_order_id'] = $request->order_id;
		$createInvoice['transaction_type'] = 'sales';
		$createInvoice['vu_id'] = $request->vu_id;
		$createInvoice['store_short_code'] = $order->store->short_code;
		$createInvoice['invoice_sequence'] = invoice_id_generate($request->store_id, $request->user_id, $request->trans_from, $invoiceSeq, $request->udidtoken, 'seq_id');
		$createInvoice['stock_point_id'] = $order->store->sellable_stock_point->id;
		$createInvoice['terminal_name'] = CashRegister::where('udidtoken', $request->udidtoken)->first()->name; 
		$createInvoice['terminal_id'] = $request->terminal_id;
		$createInvoice['tax_details'] = $order->tdata;
		$createInvoice['date'] = date('Y-m-d');
		$createInvoice['time'] = date('h:i:s');
		$createInvoice['month'] = date('m');
		$createInvoice['year'] = date('Y');
		$createInvoice['financial_year'] = getFinancialYear();
		$createInvoice['customer_name'] = $order->customer_name;
		$createInvoice['customer_number'] = $order->user->mobile;
		$createInvoice['customer_email'] = $order->user->email;
		$createInvoice['customer_address'] = @$order->user->address->address1;
		$createInvoice['trans_from'] = $request->trans_from;
		$createInvoice['session_id'] = $session_id;
		$createInvoice['customer_gender'] = @$order->user->gender;
		$createInvoice['customer_dob'] = @$order->user->dob;
		$createInvoice['customer_first_name'] = @$order->user->first_name;
		$createInvoice['customer_last_name'] = @$order->user->last_name;
		$createInvoice['customer_pincode'] = @$order->user->address->pincode;
		$createInvoice['customer_phone_code'] = @$order->user->customer_phone_code;
		

		// dd($createInvoice);
		$invoiceId = Invoice::create($createInvoice);
		// Invoice Details Entry
		foreach ($order->list as $key => $value) {
			$orderDetails = OrderDetails::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'id' => $value->id, 't_order_id' => $order->od_id ])->first();
			$createInvoiceDetails = collect($orderDetails)->forget(['id','t_order_id'])->toArray();
			$createInvoiceDetails['t_order_id'] = $invoiceId->id;
			InvoiceDetails::create($createInvoiceDetails);
		}

		// Check Inventory Block Setting
		// $vendorAuth = Vendor::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'id' => $request->vu_id ])->first();
		// if(in_array($vendorAuth->order_inventory_blocking_level['setting'], ['order_created', 'order_confirmed', 'order_packed'])) {
		// 	// $request->request->add([ 'reverse' => true ]);
		// 	$orderStockPointResponse = $this->movingStockToOrderStockPoint($request);
		// 	$orderStockPointResponse = $orderStockPointResponse->getData();
		// 	if($orderStockPointResponse->status == 'fail') {
		// 		return response()->json((array)$orderStockPointResponse);
		// 	}
		// }

		// Stock Entry
		$productDataList = InvoiceDetails::select('barcode','qty')->where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 't_order_id' => $invoiceId->id ])->get()->toArray();
		foreach ($productDataList as $key => $value) {
			$params = ['v_id' => $request->v_id, 'store_id' => $request->store_id, 'barcode' => $value['barcode'], 'qty' => $value['qty'], 'invoice_id' => $invoiceId->invoice_id, 'order_id' => $invoiceId->ref_order_id, 'vu_id' => $request->vu_id, 'trans_from' => $request->trans_from, 'transaction_scr_id' => $invoiceId->id, 'transaction_type' => 'SALE'];
			// $vendorAuth = Vendor::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'id' => $request->vu_id ])->first();
			if(in_array($vendorAuth->order_inventory_blocking_level['setting'], ['order_created', 'order_confirmed', 'order_packed'])) {
				$params['stock_point_id'] = $order->store->OrderStockPoint->id;
			}
            $this->cartconfig->updateStockQty($params);
		}
		// Change Status
		$order->status = 'success';
		$order->verify_status = '1';
		$order->verify_status_guard = '1';
		$order->save();

		// Payment Entry
		Payment::where([ 'v_id' => $order->v_id, 'store_id' => $order->store_id, 'order_id' => $request->order_id, 'user_id' => $order->user_id ])->update([ 'invoice_id' => $invoiceId->invoice_id ]);

		event(new SaleItemReport($invoiceId->invoice_id));

		$db_structure = DB::table('vendor')->select('db_structure')->where('id', $request->v_id)->first()->db_structure;
		 if(!empty($invoiceId) && isset($invoiceId) ) {
 	        	$zwingTagVId = '<ZWINGV>'.$request->v_id.'<EZWINGV>';
 				$zwingTagStoreId = '<ZWINGSO>'.$request->store_id.'<EZWINGSO>';
 				$zwingTagTranId = '<ZWINGTRAN>'.$invoiceId->id.'<EZWINGTRAN>';
 				$jobType = 'SALES';
 				if($invoiceId->transaction_type == 'return'){
 					$jobType = 'RETURN';
 				}
 	        	event(new InvoiceCreated([ 'invoice_id' => $invoiceId->id, 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'db_structure' => $db_structure, 'type'=> $jobType, 'zv_id' => $zwingTagVId, 'zs_id' => $zwingTagStoreId, 'zt_id' => $zwingTagTranId ] )
 		        );
		}

		return response()->json([ 'status' => 'success', 'data' => $invoiceId, 'message' => 'Invocie generated successfully.' ], 200);
	}

	public function cashTransactionEntry(Request $request)
	{
		$vendorAuth = Vendor::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'id' => $request->vu_id ])->first();
		if($vendorAuth->cash_management['status'] && $request->method == 'cash') {
			$amount = $request->amount;
			$order = Order::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'order_id' => $request->order_id ])->first();
			$transaction_type = $order->transaction_type == 'return' ? 'RETURN' : 'SALES';
			$transaction_behaviour = $order->transaction_type == 'return' ? 'OUT' : 'IN';
			if($request->has('is_refund') && $request->is_refund) {
				$transaction_type = 'REFUND';
				$transaction_behaviour = 'OUT';
			}
			if($transaction_behaviour == 'OUT') {
				$amount = -$amount;
			}
			$currentTerminalCashPoint = CashPoint::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'ref_id' => $request->terminal_id ])->first();
			$settlement_date = date('Y-m-d');
			$settlementSession = SettlementSession::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id, 'cash_register_id' => $request->terminal_id ])->orderBy('id','desc')->first();  
			$createCashTransactionLog = [ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'session_id' => $settlementSession->id, 'logged_session_user_id' => $request->vu_id, 'cash_point_id' => $currentTerminalCashPoint->id, 'cash_point_name' => $currentTerminalCashPoint->cash_point_name, 'transaction_type' => $transaction_type, 'transaction_behaviour' => $transaction_behaviour, 'amount' => $amount, 'transaction_ref_id' => $request->order_id, 'cash_register_id' => $request->terminal_id, 'status' => 'APPROVED', 'approved_by' => $request->vu_id, 'remark' => 'POS Order - '.$transaction_type, 'date' => date('Y-m-d'), 'time' => date('h:i:s') ];
			CashTransactionLog::create($createCashTransactionLog);
			$mainCart = new MainCart;
			$mainCart->cashPointSummaryUpdate($currentTerminalCashPoint->id, $currentTerminalCashPoint->cash_point_name, $request->store_id, $request->v_id, $settlementSession->id);
		}
	}

	public function markAsFulfilled(Request $request) 
	{
		$updateOrder = Order::where([ 'order_id' => $request->order_id, 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'vu_id' => $request->vu_id ])->update([ 'status' => 'fulfilled' ]);

		if($updateOrder) {
			return response()->json(['status' => 'success', 'message' => 'Order fulfilled successfully'], 200);
		} else {
			return response()->json(['status' => 'fail', 'message' => 'Error in updating order status'], 200);
		}
	}

	public function getProductDetailsByInventory(Request $request)
	{
		DB::enableQueryLog();
		$batches = $serials = [];
		$productRequest = new \Illuminate\Http\Request();

		$prodQty = $request->qty;
		if($request->type == 'add' && !$request->weight) {
			$prodQty = 1;
		}

		$productRequest->merge([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'barcode' => $request->barcode, 'trans_from' => $request->trans_from, 'vu_id' => $request->vu_id, 'qty' => $prodQty, 'is_product_details' => true, 'batch_id' => $request->batch_id, 'serial_id' => $request->serial_id ]);
		$productCon = new ProductController;
		$getProductDetails = $productCon->product_details($productRequest)->getData();
		// dd($getProductDetails);
		if($getProductDetails->status == 'product_not_found') {
			return response()->json([ 'status' => 'fail', 'data' => $getProductDetails->message ], 200);
		}
		if($getProductDetails->status == 'fail') {
			return response()->json([ 'status' => 'fail', 'data' => $getProductDetails->message ], 200);
		}
		$itemDet = json_decode(urldecode($getProductDetails->data->item_det));
		$itemDetails = VendorSku::where([ 'sku_code' => $itemDet->sku_code, 'sku' => $itemDet->sku, 'vendor_sku_detail_id' => $itemDet->vendor_sku_detail_id ])->first();
		if(empty($itemDetails)) {
			return response()->json([ 'status' => 'fail', 'data' => 'Product Not found' ], 200);
		}

		$storeDetails = Store::select('gst','store_id')->where('store_id', $request->store_id)->first();
		$itemStock = StockPointSummary::select('qty','stock_point_id')->where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'barcode' => $request->barcode, 'stock_point_id' => $storeDetails->sellable_stock_point->id, 'serial_id' => $getProductDetails->data->serial_id, 'batch_id' => $getProductDetails->data->batch_id ])->first();
		$taxParams = ['barcode' => $request->barcode, 'qty' => $request->qty, 's_price' => $getProductDetails->data->s_price, 'hsn_code' => $itemDet->hsn_code, 'store_id' => $request->store_id, 'v_id' => $request->v_id, 'from_gstin' => $storeDetails->gst , 'to_gstin' => $request->cust_gstin , 'invoice_type' => $request->comm_trans ];
		$cartCon = new CartController;
        $taxDetails = $cartCon->taxCal($taxParams);
        	
        if(empty($itemStock)) {
        	return response()->json([ 'status' => 'fail', 'message' => $request->barcode.' Stock not available' ], 200);
        }

        $isAvail = false;
        if($itemStock->qty > 0) $isAvail = true;

		$productDetails = ['barcode' => $request->barcode, 'qty' => $prodQty, 'item_name' => $itemDetails->name, 'unit_mrp' => $getProductDetails->data->unit_mrp, 'total_discount' => $getProductDetails->data->discount, 'tax' => $taxDetails['tax'], 'total' => $getProductDetails->data->s_price, 'id' => 0, 'message' => '', 'is_sellable' => true, 'is_available' => $isAvail, 'available_qty' => $itemStock->qty, 'stock_point_name' => $itemStock->point->name, 'weight_flag' => $itemDetails->selling_uom_type == 'WEIGHT' ? true : false, 'serial_id' => $getProductDetails->data->serial_id, 'batch_id' => $getProductDetails->data->batch_id ];

		// Check Batch List
		if($itemDetails->has_batch == 1) {
          $grnData = GrnList::where([ 'barcode' => $request->barcode, 'v_id' => $request->v_id, 'store_id' => $request->store_id ])->orderBy('id','desc')->get();
          foreach ($grnData as $gdata) {
            foreach ($gdata->batches as $batch) {
              if($batch->batch_no != '') {
                $validty = !empty($batch->valid_months) ? $batch->valid_months : 'N/A';
                $batchStock = StockPointSummary::select('qty','stock_point_id')->where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'barcode' => $request->barcode, 'stock_point_id' => $storeDetails->sellable_stock_point->id, 'batch_id' => $batch->id ])->first();
                $batches[] = [ 'id' => $batch->id, 'code' => $batch->batch_no, 'mfg_date' => emptyCheker($batch->mfg_date), 'exp_date' => emptyCheker($batch->exp_date), 'validty' => $validty, 'type' => 'batch', 'mrp' => $batch->priceDetail->mrp, 'stock' => empty($batchStock->qty) ? 0 : $batchStock->qty ];
              }
            } 
          }
        }

        // Check Serial List
        if($itemDetails->has_serial == 1) {
	        $grnData = GrnList::where([ 'barcode' => $request->barcode, 'v_id' => $request->v_id, 'store_id' => $request->store_id ])->orderBy('id','desc')->get();
	        foreach ($grnData as $gdata) {
	          foreach ($gdata->serials as $serial) {
	            $serials[]  = [ 'id' => $serial->id, 'code' => $serial->serial_no, 'type' => 'serial', 'mrp' => $serial->priceDetail->mrp ];
	          } 
	        }
	    }

	    $productDetails['batch'] = $batches;
	    $productDetails['serial'] = $serials;

		return response()->json([ 'status' => 'success', 'data' => $productDetails ], 200);
	}

}
