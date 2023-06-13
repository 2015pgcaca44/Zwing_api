<?php

namespace App\Http\Controllers\NurturingGreen;

use App\Http\Controllers\Ginesys\CartController as Extended_Cart_Controller;
use Illuminate\Http\Request;
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

//  Using Ginesys Cart controller as parent controller

/**
 * function get_print_receipt
 * Function has been override from the Parent (Ginesys) for Separate print
**/

	public function get_print_receipt(Request $request) 
	{

        $v_id = $request->v_id;
		$store_id = $request->store_id;
		$c_id = $request->c_id;
		$order_id = $request->order_id;
		$product_data = [];
		$gst_list = [];
		$final_gst = [];
		$detatch_gst = [];


        $store = Store::find($store_id);
       // dd($store_id);
		$site_details = DB::table($store->store_db_name.'.admsite')->where('CODE', $store->mapping_store_id)->first();
		
		//dd($site_details);
		$order_details = Invoice::where('ref_order_id', $order_id)->first();

		$cart_qty = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('qty');

        $total_amount = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->sum('total');
        // dd($total_amount);

        $cart_product = InvoiceDetails::where('t_order_id', $order_details->id)->where('v_id', $order_details->v_id)->where('store_id', $order_details->store_id)->where('user_id', $order_details->user_id)->get();
		$count = 1;
		$gst_tax = 0;
		$gst_listing = [];

		foreach ($cart_product as $key => $value) {

			$tdata = json_decode($value->tdata);
			// dd($tdata);
			if (is_array($tdata) || array_key_exists('hsn', $tdata)) {

				if(!empty($value->unit_mrp) && $value->unit_mrp != '0.00'):
				  $rate  =$value->unit_mrp;
				else:  
				   $rate  =$value->unit_csp;
				endif;

				$gst_tax += $value->tax;
				array_push($product_data, [
					'row' => 1,
					'sr_no' => $count++,
					'qty' => $value->qty,
					'rate' => round($rate),
					'discount' => $value->discount,
					'total' => $value->total,
					
				]);

				array_push($product_data, [
					'row' => 2,
					'name' => $value->item_name,	
					'hsn' => $tdata->hsn,				
					'tax_amt' => $value->tax,
					'tax_per' => $tdata->tax_name,
					'total' => $value->total,
				]);
				if(strpos($tdata->tax_name,'GST') !== false){
					$gst_list[] = [
						'name' => $tdata->tax_name,
						'wihout_tax_price' => $tdata->taxable,
						'tax_amount' => $tdata->tax,
					];
				}
			} else {

				if(!empty($value->unit_mrp) && $value->unit_mrp != '0.00'):
				  $rate  =$value->unit_mrp;
				else:  
				   $rate  =$value->unit_csp;
				endif;
				
				// $gst_tax += $value->tax;
				array_push($product_data, [
					'row' => 1,
					'sr_no' => $count++,
					'qty' => $value->qty,
					'rate' => round($rate),
					'discount' => $value->discount,
					'total' => $value->total,
					
				]);

				array_push($product_data, [
					'row' => 2,
					'name' => $value->item_name,
					'hsn' => '',
					'rsp' => $value->unit_mrp,
					'tax_amt' => $value->tax,
					'tax_per' => '',
					 
				]);

			}
		
		}

		// dd(array_unique($gst_list));

		$gst_listing = array_unique(array_column($gst_list, 'name'), SORT_REGULAR);
		// dd($gst_list);
		$total_gst = $taxable_amount = $total_taxable = $total_csgt = $total_sgst = 0;
		foreach ($gst_listing as $key => $value) {
			$buffer_total_gst = $buffer_taxable_amount = $buffer_total_taxable = $buffer_total_csgt = $buffer_total_sgst = 0;
			foreach ($gst_list as $val) {
				if ($val['name'] == $value) {
					$buffer_total_gst += $val['tax_amount'];
					$buffer_taxable_amount += str_replace(",", '', $val['wihout_tax_price']);
					$total_gst += $val['tax_amount'];
					$taxable_amount += str_replace(",", '', $val['wihout_tax_price']);
					$final_gst[$value] = (object) [
						'name' => $string = str_replace(' ', '', $value),
						'taxable' => $this->format_and_string($buffer_taxable_amount),
						'cgst' => $this->format_and_string($buffer_total_gst / 2),
						'sgst' => $this->format_and_string($buffer_total_gst / 2),
						'cess' => '0.00',
					];
					// $total_taxable += $taxable_amount;
					$total_csgt = $total_gst / 2;
					$total_sgst = $total_gst / 2;
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
		} else if ($roundoff[1] <= 49) {
			$roundoffamt = $total_amount - $order_details->total;
		}
		// dd($roundoffamt);

		//Voucher Conditions started Here
		$store_credit = '';
		$voucher_no = '';
		$voucher_total = 0;
		$voucher_applied_list = [];
		$lapse_voucher_amount = 0;
		$bill_voucher_amount = 0;
		$cash_collected = 0;
		$cash_return = 0;
		if ($order_details->transaction_type == 'sales') {
			$payments = Payment::where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $c_id)->where('order_id', $order_id)->get();
			if ($payments) {

				foreach ($payments as $payment) {
					$cash_collected += (float) $payment->cash_collected;
					$cash_return += (float) $payment->cash_return;
					if ($payment->method == 'vmart_credit') {
						$vouchers = DB::table('voucher_applied as va')
							->join('voucher as v', 'v.id', 'va.voucher_id')
							->select('v.voucher_no', 'v.amount')
							->where('va.v_id', $v_id)->where('va.store_id', $store_id)
							->where('va.user_id', $c_id)->where('va.order_id', $order_details->o_id)->get();
						$voucher_total = 0;
						foreach ($vouchers as $voucher) {
							$voucher_total += $voucher->amount;
							$voucher_applied_list[] = ['voucher_code' => $voucher->voucher_no, 'voucher_amount' => format_number($voucher->amount)];
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

		} else {
			$voucher = DB::table('voucher')->where('ref_id', $order_details->ref_order_id)->where('user_id', $order_details->user_id)->first();
			if ($voucher) {

				$store_credit = format_number($voucher->amount);
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

		$data = [
			'header' => $store->name,
			'address' => $site_details->ADDRESS,
			'contact' => $store->helpline,
 			'email' => $store->email,
 			'gstin' => $store->gst,
			'cin' => 'L51909DL2002PLC163727',
			'gst_doc_no' => $order_details->custom_order_id,
			'memo_no' => $order_details->invoice_id,
			'time' => date('h:i A', strtotime($order_details->created_at)),
			'date' => date('d-M-Y', strtotime($order_details->created_at)),
			'cashier' => $order_details->vuser->first_name . ' ' . $order_details->vuser->last_name,
			'customer_name' => 'NA',
			// 'mobile' => (string) $order_details->user->mobile,
			'mobile'	=>$store->helpline,
			'product_data' => $product_data,
			'total_qty' => $cart_qty,
			'total_amount' => $total_amount,
			'voucher_total ' => $voucher_total,
			'voucher_applied_list ' => $voucher_applied_list,
			'lapse_voucher_amount ' => $lapse_voucher_amount,
			'bill_voucher_amount ' => $bill_voucher_amount,
			'gst' => $this->format_and_string('0.00'),
			'round_off' => $this->format_and_string($roundoffamt),
			'due' => $total_amount,
			'in_words' => ucfirst(numberTowords(round($order_details->total))),
			'payment_type' => ucfirst($order_details->payment->method),
			'payment_type_amount' => $total_amount,
			'customer_paid' => format_number($cash_collected),
			'balance_refund' => format_number($cash_return),
			'total_sale' => $order_details->total,
			'total_return' => '0.00',
			'saving_on_the_bill' => $order_details->discount,
			'net_sale' => $order_details->total,
			'round_off_2' => $this->format_and_string($roundoffamt),
			'net_payable' => $order_details->total,
			't_and_s_1' => '1. All Items inclusive of GST \nExcept Discounted Item.',
			't_and_s_2' => '2. Extra GST Will be Charged on\n Discounted Item.',
			't_and_s_3' => '3. No exchange on discounted and\n offer items.',
			't_and_s_4' => '4. No Refund.',
			't_and_s_5' => '5. We recommended dry clean for\n all fancy items.',
			't_and_s_6' => '6. No guarantee for colors and all hand work item.',
			'total_savings' => $order_details->discount,
			'gst_list' => $detatch_gst,
			'total_gst' => ['taxable' => $this->format_and_string($taxable_amount), 'cgst' => $this->format_and_string($total_csgt), 'sgst' => $this->format_and_string($total_sgst), 'cess' => '0.00'],
			'gate_pass_no' => '',
			'bill_logo' => $bilLogo,
		];

		return response()->json(['status' => 'success', 'data' => $data], 200);
	

	}

}