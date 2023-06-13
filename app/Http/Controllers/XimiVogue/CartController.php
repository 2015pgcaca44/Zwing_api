<?php

namespace App\Http\Controllers\XimiVogue;

use App\Http\Controllers\Ginesys\CartController as Extended_Cart_Controller;
use Illuminate\Http\Request;
use App\Invoice;
use App\InvoiceDetails;
use App\User;
use App\Store;
use DB;
use Auth;
use App\Payment;
use App\VendorImage;
use App\Http\CustomClasses\PrintInvoice;
use App\Organisation;

class CartController extends Extended_Cart_Controller {

//  Using Ginesys's Cart controller as parent controller
	public function get_print_receipt(Request $request) 
	{
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
		$gst_listing = [];

		foreach ($cart_product as $key => $value) {

				$tdata = json_decode($value->tdata);

				$gst_tax += $value->tax;
				array_push($product_data, [
					'row' => 1,
					'sr_no' => $count++,
					'name' => $value->item_name,
					'rate' => round($value->unit_mrp),
					'qty' => $value->qty,
					'discount' => $value->discount,
					'total' => $value->total,
					'hsn' => isValueExists($tdata, 'hsn'),
				]);

				if ($order_details->transaction_type == 'sales') {
					array_push($product_data, [
						'row' => 2,
						'barcode' => $value->barcode,
						'hsn' => isValueExists($tdata, 'hsn'),
						'rate' => round($value->unit_mrp),
						'qty' => $value->qty,
						'discount' => $value->discount,
						'rsp' => $value->unit_mrp,
						'tax_amt' => $value->tax,
						'tax_per' => isValueExists($tdata,'cgst', 'num') + isValueExists($tdata, 'sgst','num'),
						'total' => $value->total,
						'gst_rate' => isValueExists($tdata,'igst'),
					]);
				} else if ($order_details->transaction_type == 'return') {
					array_push($product_data, [
						'row' => 2,
						'barcode' => $value->barcode,
						'hsn' => isValueExists($tdata, 'hsn'),
						'rate' => round($value->unit_mrp),
						'qty' => -$value->qty,
						'discount' => $value->discount,
						'rsp' => $value->unit_mrp,
						'tax_amt' => format_number(-$value->tax),
						'tax_per' => $tdata->cgst + $tdata->sgst,
						'total' => format_number(-$value->total),
						'gst_rate' => $tdata->igst,
					]);
				}

				$gst_list[] = [
					'name' => isValueExists($tdata, 'tax_name'),
					'wihout_tax_price' => isValueExists($tdata, 'taxable', 'num'),
					'tax_amount' => isValueExists($tdata, 'tax','num'),
					'igst' => isValueExists($tdata, 'igst','num'),
					'cess' => isValueExists($tdata, 'cess', 'num'),
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
							//'igst' => number_format($buffer_total_gst / 2, 2),
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
						$mop_list[] = [ 'mode' => $payment->method, 'amount' => $payment->amount ];
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
			$saving_on_the_bill = $order_details->discount;
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

		// $mop_list[] = [ 'mode' => 'Credit Card', 'amount' => '1000' ];

		if($order_details->tax > 0){
			
		}else{
			$detatch_gst = [];
		}
		/*
		$data = [
			'header' => $site_details->NAME,
			'address' => $site_details->ADDRESS.','.$site_details->CTNAME.','.$site_details->PIN,
			'contact' => $store->contact_number,
			'email' => $store->email,
			'title'	=> $invoice_title,
			'gstin' => $store->gst,
			'cin' => 'L51909DL2002PLC163727',
			'gst_doc_no' => $order_details->custom_order_id,
			'memo_no' => $order_details->invoice_id,
			'time' => date('h:i A', strtotime($order_details->created_at)),
			'date' => date('d-M-Y', strtotime($order_details->created_at)),
			'cashier' => $order_details->vuser->first_name . ' ' . $order_details->vuser->last_name,
			'customer_name' => $customer->first_name,
			'mobile' => (string) $order_details->user->mobile,
			'product_data' => $product_data,
			'total_qty' => $cart_qty,
			'total_amount' => $total_amount,
			'voucher_total ' => format_number( $voucher_total ),
			'voucher_applied_list ' => $voucher_applied_list,
			'lapse_voucher_amount ' => $lapse_voucher_amount,
			'bill_voucher_amount ' => $bill_voucher_amount,
			'gst' => $this->format_and_string('0.00'),
			'round_off' => $this->format_and_string($roundoffamt),
			'due' => $due,
			'in_words' => $in_words,
			'mop_list' => $mop_list,
			'payment_type' => ucfirst($order_details->payment->method),
			'payment_type_amount' => format_number($cash_collected),
			'customer_paid' => format_number($customer_paid),
			'balance_refund' => format_number($balance_refund),
			'total_sale' => $total_sale,
			'total_return' => '0.00',
			'saving_on_the_bill' => $saving_on_the_bill,
			'net_sale' => $net_sale,
			'round_off_2' => $this->format_and_string($roundoffamt),
			'net_payable' => $net_payable,
			't_and_s_1' => '1. All Items inclusive of GST \nExcept Discounted Item.',
			't_and_s_2' => '2. Extra GST Will be Charged on\n Discounted Item.',
			't_and_s_3' => '3. No exchange on discounted and\n offer items.',
			't_and_s_4' => '4. No Refund.',
			't_and_s_5' => '5. We recommended dry clean for\n all fancy items.',
			't_and_s_6' => '6. No guarantee for colors and all hand work item.',
			'total_savings' => $order_details->discount,
			'round_off_3' => $this->format_and_string($roundoffamt),
			'gst_list' => $detatch_gst,
			'total_gst' => ['taxable' => $this->format_and_string($taxable_amount), 'cgst' => $this->format_and_string($total_csgt), 'sgst' => $this->format_and_string($total_sgst), 'cess' => '0.00'],
			'gate_pass_no' => '',
			'bill_logo' => $bilLogo,
		];*/

		//return response()->json(['status' => 'success', 'data' => $data], 200);

		$terms_conditions =  array(
			'1.No exchange or No Return Allowed.',
			'2.All Items sold are final sale.',
			'3.10 Days exchange is available only \n on Electronics and Slippers (Only size issue \nand Bill is Mandatory in That.',
			'4.Any Item Broken by Customer will be treated as final .',
			'5.Before Buying, Customer are \nrequested to check theitem, After billing any \nphysically damaged product is no\acceptable.',
		);

		$manufacturer_name = 'basewin';
        if($request->has('manufacturer_name') ){
        	$manufacturer_name= $request->manufacturer_name;
        }

        $printInvioce = new PrintInvoice($manufacturer_name);
        // Start center
        $printInvioce->addDivider('_', 20);
        
        $printInvioce->addLineCenter($site_details->NAME, 24, true);
        $printInvioce->addLine(' ', 22);
        $printInvioce->addLine('Helpline No: '.$store->contact_number, 22);
        $printInvioce->addLine('E-mail: '.$store->email, 22);
        if($store->gst){
        	$printInvioce->addLine('GSTIN: '.$store->gst, 22);
   		}

        $printInvioce->addLine('Tax Invoice(Customer Copy)', 24, true);
        $printInvioce->addDivider('_', 20);
        $printInvioce->addLineLeft('Invoice No:'.$order_details->invoice_id, 24);
        $printInvioce->addLineLeft('Date: '.date( 'd-m-y', strtotime($order_details->created_at) ).' \ncashier: '.$order_details->vuser->first_name . ' ' . $order_details->vuser->last_name , 22);
        $printInvioce->addDivider('_', 20);
        $printInvioce->tableStructure(['SI', 'Product', 'Rate','Qty','Disc', 'Amt'], [3, 8, 6, 4, 5,8], 22);
        $printInvioce->tableStructure(['Barcode', 'Gst%', 'HSN Code', 'Unknown'], [13, 5, 10, 6], 22);
        $printInvioce->addDivider('_', 20);
        for($i = 0; $i < count($product_data); $i++) {
            if($i % 2 == 0) {
                $printInvioce->tableStructure([
                    $product_data[$i]['sr_no'],
                    $product_data[$i]['name'],
                    $product_data[$i]['rate'],
                    $product_data[$i]['qty'],
                    $product_data[$i]['discount'],
                    $product_data[$i]['total']],
                    [3, 8, 6, 4, 5,8], 22);
            } else {
                $printInvioce->tableStructure([
                    ' '.$product_data[$i]['barcode'],
                    $product_data[$i]['gst_rate'],
                    $product_data[$i]['hsn'],
                    '' ],
                    [14, 4, 9, 6], 22);
            }
        }
        $printInvioce->addDivider('_', 20);
        $printInvioce->tableStructure(['Total', $cart_qty, $total_amount], [8, 4, 22], 22);
        $printInvioce->numToWords($order_details->total, 22);
        $printInvioce->addDivider('_', 20);
        
    
        if(!empty($mop_list)) {
            foreach ($mop_list as $mop) {
                $printInvioce->leftRightStructure($mop['mode'], $mop['amount'], 22);
            }
            $printInvioce->addDivider('_', 20);
        }

        if($customer_paid > 0){
	        $printInvioce->addLineLeft('Customer Paid: '.format_number($customer_paid), 22, true);
	        $printInvioce->addLine('Balance Refund: '.format_number($balance_refund), 22, true);
        }

        $printInvioce->addDivider('_', 20);
        $printInvioce->leftRightStructure('Total Sale', $total_sale, 22);
        $printInvioce->leftRightStructure('Net Payable', $net_sale, 22);
        //GST Summary Start
        $printInvioce->addDivider('_', 20);
        if(!empty($detatch_gst)) {
        	$printInvioce->addLineLeft('GST Summary', 24);
            $printInvioce->tableStructure(['Desc', 'Taxable', 'CGST', 'SGST', 'IGST' , 'Amt'], [9, 9, 5, 5, 5, 4], 20, true);
         	
            foreach ($detatch_gst as $index => $gst) {
                $printInvioce->tableStructure([$gst->name,
                    $gst->taxable,
                    $gst->cgst,
                    $gst->sgst,
                    '',
                    $gst->cess],
                    [9, 9, 5, 5, 5, 4], 20);

            
            }

            $printInvioce->addDivider('_', 20);
            $printInvioce->tableStructure(['Total',
                format_number($taxable_amount),
                format_number($total_csgt),
                format_number($total_sgst),
                format_number(0)
                ], [6, 10, 7, 7, 4], 22, true);
            $printInvioce->addDivider('_', 20);
            
        }

        $printInvioce->addLineLeft('Terms and Conditions', 22, true);
        foreach ($terms_conditions as $term) {
            $printInvioce->addLine($term, 20);
        }
        $printInvioce->addLine('', 20);
        $printInvioce->addLineCenter('ALL SUBJECT HANDEL TO JAIPUR JURSIDICTION ONLY .', 20);

        $printInvioce->addLine('Thank You For Shopping With Us!', 22);
        
        return response()->json(['status' => 'success', 'print_data' =>($printInvioce->getFinalResult())], 200);
	}
}