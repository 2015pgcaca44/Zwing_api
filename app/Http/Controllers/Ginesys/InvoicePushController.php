<?php

namespace App\Http\Controllers\Ginesys;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Store;
use App\Invoice;
use App\InvoicePush;

use App\Http\Controllers\ApiCallerController;

class InvoicePushController extends Controller
{
    public function invoicePushStatus(Request $request)
	{
		// dd($request->all());
		$data = urldecode($request->data);
		$data = json_decode($data);
		// echo $data->v_id;
		// dd('Cool');
		$store = Store::where('mapping_store_id', $data->store_id)->where('v_id', $data->v_id)->first();
		// echo $store->store_id;
		// dd('Cool');
		$check_invoice_exists = Invoice::where('v_id', $data->v_id)->where('store_id', $store->store_id)->where('invoice_id', $data->invoice_no)->count();
		// echo $check_invoice_exists;
		// dd('Cool');
		if (!empty($check_invoice_exists)) {
			InvoicePush::create([ 
				'v_id'			=> $data->v_id,
				'store_id'		=> $store->store_id,
				'invoice_no'	=> $data->invoice_no,
				'response'		=> $data->response,
				'status'		=> $data->status
			]);
		}
	}
}
