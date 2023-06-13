<?php

namespace App\Http\Controllers\Skechers;

use App\Http\Controllers\Ginesys\CartController as Extended_Cart_Controller;
use Illuminate\Http\Request;
use App\Http\CustomClasses\PrintInvoice;

use App\Order;
use App\Payment;
use App\Store;
use App\User;
use App\VendorImage;
use App\Invoice;
use App\OrderDetails;
use App\OrderItemDetails;
use App\CartDetails;
use App\InvoiceDetails;
use App\InvoiceItemDetails;
use DB;
use Auth;

class CartController extends Extended_Cart_Controller {



	public function get_print_receipt(Request $request) 
	{


		// $v_id = $request->v_id;
		// $store_id = $request->store_id;
		// $c_id = $request->c_id;
		// $order_id = $request->order_id;
		if(!empty($request->request->all())){
			$data = $request->request->all();
			$v_id = $data['v_id'];
			$store_id = $data['store_id'];
			$c_id = $data['c_id'];
			$order_id = $data['order_id'];
		}else{
			$v_id = $request->v_id;
			$store_id = $request->store_id;
			$c_id = $request->c_id;
			$order_id = $request->order_id;
		}
		$product_data = [];
		$gst_list = [];
		$final_gst = [];
		$detatch_gst = [];
		$mop_list = [];
		$store_db_name = $this->store_db_name($store_id);
		$store = Store::find($store_id);

		$site_details = DB::table($store_db_name.'.admsite')->where('CODE', $store->mapping_store_id)->first();
		$order_details = Invoice::where('invoice_id', $order_id)->first();

		$customer = User::find($order_details->user_id);

		$cart_qty = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('qty');

        $total_amount = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
        // dd($total_amount);

        $cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->get();
		$count = 1;
		$gst_tax = 0;
		$totalDiscount = $order_details->discount + $order_details->lpdiscount + $order_details->manual_discount + $order_details->coupon_discount;
		$gst_listing = [];

		foreach ($cart_product as $key => $value) {

				$tdata = json_decode($value->tdata);
				$totalItemDiscount = $value->lpdiscount + $value->manual_discount + $value->coupon_discount + $value->discount;

				$gst_tax += $value->tax;
				array_push($product_data, [
					'row' => 1,
					'sr_no' => $count++,
					'name' => $value->item_name,
					'qty' => $value->qty,
					'rate' => round($value->unit_mrp),
					'total' => $value->total,
					'discount' => $totalItemDiscount
				]);

				if ($order_details->transaction_type == 'sales') {
					array_push($product_data, [
						'row' => 2,
						'barcode' => $value->barcode,
						'qty' => $value->qty,
						'hsn' => $tdata->hsn,
						'discount' => $totalItemDiscount,
						'rsp' => $value->unit_mrp,
						'tax_amt' => $value->tax,
						'tax_per' => $tdata->cgst + $tdata->sgst,
						'total' => $value->total,
					]);
				} else if ($order_details->transaction_type == 'return') {
					array_push($product_data, [
						'row' => 2,
						'rate' => round($value->unit_mrp),
						'qty' => -$value->qty,
						'discount' => $totalItemDiscount,
						'rsp' => $value->unit_mrp,
						'tax_amt' => format_number(-$value->tax),
						'tax_per' => $tdata->cgst + $tdata->sgst,
						'total' => format_number(-$value->total),
					]);
				}

				$gst_list[] = [
					'name' => $tdata->tax_name,
					'wihout_tax_price' => $tdata->taxable,
					'tax_amount' => $tdata->tax,
				];
		}

		// dd(array_unique($gst_list));

		$gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
		// dd($gst_list);
		$total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = 0;
		foreach ($gst_listing as $key => $value) {
			$buffer_total_gst = $buffer_taxable_amount = $buffer_total_taxable = $buffer_total_csgt = $buffer_total_sgst = 0;
			foreach ($gst_list as $val) {
				if ($val['name'] == $value) {
					if ($order_details->transaction_type == 'sales') {
						$buffer_total_gst += $val['tax_amount'];
						$buffer_taxable_amount += str_replace(",", '', $val['wihout_tax_price']);
						$total_gst += $val['tax_amount'];
						$taxable_amount += str_replace(",", '', $val['wihout_tax_price']);
						$final_gst[$value] = (object) [
							'name' => $value,
							'taxable' => $this->format_and_string($buffer_taxable_amount),
							'cgst' => number_format($buffer_total_gst / 2, 2),
							'sgst' => number_format($buffer_total_gst / 2, 2),
							'cess' => '0.00',
						];
						// $total_taxable += $taxable_amount;
						$total_csgt = $total_gst / 2;
						$total_sgst = $total_gst / 2;
					} elseif ($order_details->transaction_type == 'return') {
						$buffer_total_gst += $val['tax_amount'];
						$buffer_taxable_amount += str_replace(",", '', $val['wihout_tax_price']);
						$total_gst += $val['tax_amount'];
						$taxable_amount += str_replace(",", '', $val['wihout_tax_price']);
						$final_gst[$value] = (object) [
							'name' => $value,
							'taxable' => format_number(-$buffer_taxable_amount),
							'cgst' => format_number(-$buffer_total_gst / 2),
							'sgst' => format_number(-$buffer_total_gst / 2),
							'cess' => '0.00',
						];
						// $total_taxable += $taxable_amount;
						$total_csgt = -$total_gst / 2;
						$total_sgst = -$total_gst / 2;
					}
				}
			}
		}
		// dd($final_gst);

		foreach ($final_gst as $key => $value) {
			$detatch_gst[] = $value;
		}

		// dd($detatch_gst);

		$roundoff = explode(".", $total_amount);
		$roundoffamt = 0;
		// dd($roundoff);
		if (!isset($roundoff[1])) {
			$roundoff[1] = 0;
		}
		if ($roundoff[1] >= 50) {
			$roundoffamt = $order_details->total - $total_amount;
			$roundoffamt = -$roundoffamt;
		} else if ($roundoff[1] <= 49) {
			$roundoffamt = $total_amount - $order_details->total;
			$roundoffamt = -$roundoffamt;
		}
		// dd($roundoffamt);

		//Voucher Conditions started Here
		$store_credit = '';
		$voucher_no = '';
		$rounded = 0;
		$voucher_total = 0;
		$voucher_applied_list = [];
		$lapse_voucher_amount = 0;
		$bill_voucher_amount = 0;
		$cash_collected = 0;
		$cash_return = 0;
		if ($order_details->transaction_type == 'sales') {
			$invoice_title = '*** Invoice ***';
			$payments = Payment::where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('invoice_id', $order_id)->get();
			// dd($payments);
			if ($payments) {

				foreach ($payments as $payment) {
					if ($payment->method == 'cash') {
						$mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->cash_collected ];
					} else {
						$mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
					}
					
					$cash_collected += (float) $payment->cash_collected;
					$cash_return += (float) $payment->cash_return;
					if ($payment->method == 'vmart_credit') {
						$vouchers = DB::table('voucher_applied as va')
							->join('voucher as v', 'v.id', 'va.voucher_id')
							->select('v.voucher_no', 'v.amount' , 'va.applied_amount')
							->where('va.v_id', $v_id)->where('va.store_id', $store_id)
							->where('va.user_id', $c_id)->where('va.order_id', $order_details->o_id)->get();
						$voucher_total = 0;
						foreach ($vouchers as $voucher) {
							$voucher_total += $voucher->applied_amount;
							$voucher_applied_list[] = ['voucher_code' => $voucher->voucher_no, 'voucher_amount' => format_number($voucher->applied_amount)];
						}

						if ($voucher_total > $total_amount) {

							$lapse_voucher_amount = $voucher_total - $total_amount;
							$bill_voucher_amount = $total_amount;

						} else {

							$bill_voucher_amount = $voucher_total;
						}

					} else {
						$zwing_online = format_number($payment->amount);
					}
				}

			} else {
				return response()->json(['status' => 'fail', 'message' => 'Payment is not processed'], 200);
			}

		} elseif ($order_details->transaction_type == 'return')  {
			$invoice_title = '** Credit Note **';
			$voucher = DB::table('voucher')->where('ref_id', $order_details->ref_order_id)->where('user_id', $order_details->user_id)->first();
			if ($voucher) {

				$store_credit = format_number($rounded);
				$voucher_no = $voucher->voucher_no;

			}

		}

		if ($cash_collected > 0.00) {

		} else {
			$cash_collected = $total_amount;
			$cash_return = 0.00;
		}
		$bilLogo = '';
		$bill_logo_id = 5;
		$vendorImage = VendorImage::where('v_id', $v_id)->where('type', $bill_logo_id)->where('status', 1)->first();
		if ($vendorImage) {
			$bilLogo = env('ADMIN_URL') . $vendorImage->path;
		}

		

		if ($order_details->transaction_type == 'sales') {
			$cart_qty = $cart_qty;
			$total_amount = $total_amount;
			$due = $total_amount;
			if (empty($order_details->total) || $order_details->total == 0.00 || $order_details->total == '0.00') {
				$in_words = numberTowords(round($order_details->total));
			} else {
				$in_words = numberTowords(round($order_details->total)).' only';
			}
			$cash_collected = $cash_collected;
			$customer_paid = $cash_collected;
			$balance_refund = $cash_return;
			$total_sale = $total_amount;
			$saving_on_the_bill = format_number($totalDiscount);
			// if( isset($order_details->manual_discount)){
			// 	$saving_on_the_bill += $order_details->manual_discount;
			// }
			$net_sale = $order_details->total;
			$net_payable = $order_details->total;
			$taxable_amount = $taxable_amount;
		} elseif ($order_details->transaction_type == 'return') {
			$cart_qty = -$cart_qty;
			$total_amount = format_number(-$total_amount);
			$due = '0.00';
			$in_words = numberTowords(round($due)).' only';
			$cash_collected = format_number(-$cash_collected);
			$customer_paid = '0.00';
			$balance_refund = '0.00';
			$total_sale = '0.00';
			$saving_on_the_bill = '0.00';
			$net_sale = '0.00';
			$net_payable = '0.00';
			$taxable_amount = format_number(-$taxable_amount);
		}

		if ($order_details->tax > 0) {
		}else{
			$detatch_gst = [];
		}
		

        $manufacturer_name = 'basewin';
        if($request->has('manufacturer_name') ){
        	$manufacturer_name= $request->manufacturer_name;
        }

        $printInvioce = new PrintInvoice($manufacturer_name);
        // Start center
        $printInvioce->addLineCenter($site_details->NAME, 22, true);
        $printInvioce->addLineCenter('', 22, true);
        $printInvioce->addLine($site_details->ADDRESS.','.$site_details->CTNAME, 22);
        $printInvioce->addLine('PH: '.$store->contact_number, 22);
        $printInvioce->addLine('E-Mail: '.$store->email.'\n', 22);
        $printInvioce->addLine('GSTIN: '.$store->gst, 22);
		$printInvioce->addDivider('-', 22);
		$printInvioce->addLineCenter('Tax Invoice', 22, true);
		$printInvioce->addDivider('-', 22);

		$printInvioce->addLineLeft('Customer Name: '.$customer->first_name, 22);
        $printInvioce->addLine('Customer Mobile: '.(string) $order_details->user->mobile, 22);
        $printInvioce->addLineCenter('', 22, true);
        $printInvioce->addLine('GST Invoice No: '.$order_details->invoice_id, 22);
        $printInvioce->addLine('Date: '.date('d-M-Y', strtotime($order_details->created_at)).' '.date('H:i s', strtotime($order_details->created_at)), 22);
        $printInvioce->addDivider('-', 22);

        #Cashier Name
        //$printInvioce->addLine('Cashier: '.$order_details->vuser->first_name.' '.$order_details->vuser->last_name, 22);

      
        // Closes Left & Start center
        
        $printInvioce->tableStructure(['SI', 'Item Name', 'Qty','Rate','Disc','Amount'], [4,10,4,8,4,4], 22);
        $printInvioce->tableStructure(['Barcode', 'Hsn Code', ' DISC%', 'Incl Taxes'], [14, 8, 8, 4], 22);
        $printInvioce->addDivider('-', 20);
        for($i = 0; $i < count($product_data); $i++) {
            if($i % 2 == 0) {
                $printInvioce->tableStructure([
                    $product_data[$i]['sr_no'],
                    $product_data[$i]['name'],
                     ' '.$product_data[$i]['qty'],
                     $product_data[$i]['rate'],
                     $product_data[$i]['discount'],
                     $product_data[$i]['total']
                    ],
                    [4,10,4,8,4,4], 22);
            } else {
                $printInvioce->tableStructure([
                     $product_data[$i]['barcode'],
                    $product_data[$i]['hsn'],
                    '',
                     $product_data[$i]['tax_amt'],
                     ],
                    [14, 8, 8, 4], 22);
            }
        }
        $printInvioce->addDivider('-', 22);
        $printInvioce->tableStructure(['Total', $cart_qty, $total_amount], [14, 6, 14], 22);
        $printInvioce->addDivider('-', 22);

		$printInvioce->addLine('Rs: '.ucfirst(numberTowords(round($order_details->total))) , 22);
		// Closes Left & Start center
		$printInvioce->addDivider('-', 20);
		if(!empty($mop_list)) {
			foreach ($mop_list as $mop) {
			    $printInvioce->leftRightStructure($mop['mode'], $mop['amount'], 22);
			}
			$printInvioce->addDivider('-', 20);
		}

		// Closes center & Start right

        $printInvioce->addLineLeft('Customer Paid: '.format_number($customer_paid), 22,true);
        $printInvioce->addLineLeft('Balance Refund: '.format_number($balance_refund), 22,true);
        $printInvioce->addDivider('-', 20);

        // $printInvioce->leftRightStructure('GST', format_number('0.00'), 22);
        // $printInvioce->leftRightStructure('Round Off', format_number($roundoffamt), 22);
        // $printInvioce->leftRightStructure('Due:-', $due, 22);
          
		$printInvioce->leftRightStructure('Total MRP Value:', $total_sale, 22);
		$printInvioce->leftRightStructure('Total Discount:', format_number($totalDiscount), 22, true);
        $printInvioce->leftRightStructure('Round Off', $this->format_and_string($roundoffamt), 22);
        $printInvioce->leftRightStructure('Net Payable', $net_payable, 22);
        $printInvioce->addDivider('-', 22);
        $printInvioce->addLineLeft('GST Summary:', 22,true);
         $printInvioce->addDivider('-', 22);
        if(!empty($detatch_gst)) {
            $printInvioce->tableStructure(['Description', 'Taxable', ' CGST', 'SGST', 'IGST'], [11, 7, 6, 6, 4], 20, true);
            
            $printInvioce->addDivider('-', 22);
            foreach ($detatch_gst as $index => $gst) {
                $printInvioce->tableStructure([$gst->name,
                    $gst->taxable,
                    $gst->cgst,
                    $gst->sgst,
                    $gst->cess],
                    [12, 8, 5, 5, 4], 22);
            }
            $printInvioce->addDivider('-', 22);
            $printInvioce->tableStructure(['Totals',
                format_number($taxable_amount),
                format_number($total_csgt),
                format_number($total_sgst),
                '0.00'], [11, 7, 6, 6, 4], 22, true);
            $printInvioce->addDivider('-', 20);
            
         }
         $printInvioce->addLineLeft('Cashier: '.$order_details->vuser->first_name.' '.$order_details->vuser->last_name, 22);
 
        $printInvioce->addLineCenter('TERMS & CONDITIONS', 22, true);
 		$printInvioce->addLine('', 22, true);

 		$printInvioce->addLineLeft('PRICES.',20,true);
       	$printInvioce->addLineWithWrap('All prices are subject to change without prior notice, and all merchandise will be billed at the prices prevailing at the time of purchase of product in accordance to Skechers Policies.', 20);
       	
       	$printInvioce->addLine('LIMITATION OF LIABILITY.',20,true);
       	$printInvioce->addLineWithWrap('Skechers sole and exclusive obligation , and remedy under the warranty described in the preceding paragraph and Buyer’s exclusive remedy is limited to the repair or replacement against any defective product. In no event Skechers will be liable for any special,indirect,incidental or consequential damages, whether arising in contract, in tort, under warranty or otherwise, including but not limited to loss of anticipated profits and injury to persons or property.', 20);
       	
       	$printInvioce->addLineCenter('', 22, true);
       	$printInvioce->addLineCenterWrap('EXCHANGE POLICY AND LIMITED WARRANTY ', 22,true);
       	$printInvioce->addLineCenter('', 22, true);

       	$printInvioce->addLineWithWrap('Skechers warrants that its products, at the time of sale are free from defects in workmanship and materials. If buyer need to exchange any product, it can happen subject to following conditions :', 20);

       	$printInvioce->addLineWithWrap('. If the product is unused it can be exchanged within 15 days from  date of purchase with original  bill , product packing and barcode intact. For manufacturing defect product can be exchanged within 90 days from the date of purchase.', 20);

       	$printInvioce->addLineWithWrap('. For exchanged/replaced product , the date of purchase of the first sale will be considered while calculating the warranty period.', 20);

       	$printInvioce->addLineWithWrap('. Exchange will be offered for same purchase value or more . In case , if the replaced product value is more , and then buyer has to pay the differential . If it is less , then Skechers shall issue a credit note which is valid for 180 days from the date of issuance.', 20);
       	$printInvioce->addLineWithWrap('. The product has to be used for the purpose it has been designed and determined by Skechers & if inappropriately used product will not be considered for exchange.', 20);
       	$printInvioce->addLineWithWrap('. Skechers reserves the right to make any amendment to this policy.', 20);
       	$printInvioce->addLineWithWrap('. Product or Exchange related feedback/query  can be shared with store manager and/ or can be mailed at customercare.india@skechers.com  or get in touch with our customer care number :- 1800 200 8555.', 20);

        // Closes left
//        print($printInvioce->getFinalResult()); die;
        return response()->json(['status' => 'success', 'print_data' =>($printInvioce->getFinalResult())], 200);

		
	
	}


//  Using Ginesys Cart controller as parent controller

/**
 * function get_print_receipt
 * Function has been override from the Parent (Ginesys) for Separate print
**/

	 

}