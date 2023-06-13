<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\VendorAuth;
use App\Store;
use App\Order;
use App\Cart;
use App\User;
use App\Payment;
use App\B2bOrder;
use App\B2bOrderExtra;
use App\Agents;
use DB;
use Auth;

class ProfileController extends Controller
{
	public function __construct()
	{
		$this->middleware('auth');
	}

	public function my_order(Request $request)
	{
		// dd(DB::connection()->getDatabaseName());

		// dd(DB::connection('mysql'));
		// if(Auth::user()->vendor_id == 1){
		//          // dd('Cool');
		//           $profile = new  Vmart\ProfileController;
		//           $response = $profile->my_order($request);
		//           return $response;

		//       } else {
		$paymentMod   = array();
		$existPayment =  Payment::select('method')->where('invoice_id', '!=', '')->where('method', '!=', '')->where('v_id', $request->v_id)->orderBy('method', 'ASC')->groupBy('method')->get();

		if ($existPayment) {
			foreach ($existPayment as $item) {
				$method        = str_replace('_', ' ', $item->method);
				$paymentMod[]  = array('name' => strtolower($item->method), 'code' => strtoupper($method));
			}
		}


		if ($request->has('transaction_sub_type') && $request->transaction_sub_type == 'hold') {
			$orders = DB::table('orders as o')
				->join('customer_auth as c', 'c.c_id', 'o.user_id')
				->where('deleted_at', null);
		} else if($request->has('trans_type') && $request->trans_type == 'success') {
			$orders = DB::table('orders as o')
				->join('customer_auth as c', 'c.c_id', 'o.user_id')
				->Join('payments as p', 'p.order_id', 'o.order_id')
				->leftJoin('invoices as inv', 'inv.ref_order_id', 'o.order_id')
				->whereNotNull('inv.invoice_id')
				->where('o.deleted_at', null)
				->where('o.status', 'success')
				->groupBy('o.order_id');
		} else {
			$orders = DB::table('orders as o')
				->join('customer_auth as c', 'c.c_id', 'o.user_id')
				->Join('payments as p', 'p.order_id', 'o.order_id')
				->leftJoin('invoices as inv', 'inv.ref_order_id', 'o.order_id')
				->where('o.deleted_at', null)
				->groupBy('o.order_id');
		}

		if ($request->has('transaction_sub_type') && !empty($request->transaction_sub_type)) {
			$orders = $orders->where('o.transaction_sub_type', $request->transaction_sub_type);
		} else {
			//$orders = $orders->where('o.status','!=','process');
		}

		if ($request->has('transaction_type') && $request->get('transaction_type')) {
			$transaction_type = $request->get('transaction_type');
			$orders = $orders->where('o.transaction_type', $transaction_type);
		}

		if ($request->has('cust_order_all') && $request->cust_order_all == 1) {

			$c_id = $request->get('c_id');
			$orders = $orders->where('o.user_id', $c_id);
		} else {


			if ($request->has('vu_id') && $request->get('vu_id')) {

				$vu_id = $request->get('vu_id');
				if(!$request->has('search_term')) {
					$orders = $orders->where('o.vu_id', $vu_id);
				}else if($request->search_term==''){//if key exist and search term empty
					$orders = $orders->where('o.vu_id', $vu_id);
				}
			}
			if ($request->has('c_id') && $request->get('c_id')) {

				if ($request->has('transaction_sub_type') && $request->transaction_sub_type == 'hold') {
					// for Hold we get all Customer data

				} else {

					$c_id = $request->get('c_id');
					$orders = $orders->where('o.user_id', $c_id);
				}
			}
		}
		if ($request->has('v_id') && $request->get('v_id')) {
			$v_id = $request->get('v_id');
			$orders = $orders->where('o.v_id', $v_id);
		}
		if ($request->has('store_id') && $request->get('store_id')) {
			$store_id = $request->get('store_id');
			$orders = $orders->where('o.store_id', $store_id);
		}

		// Filter for today,yesterday and custom date
		// if($request->has('date_filter') && $request->get('date_filter')){
		// 	$arrayCheck = array('today','yesterday','last_week');

		// 	if(in_array($request->get('date_filter'), $arrayCheck)){
		// 		if($request->get('date_filter') == 'today' ){
		// 		$orders = $orders->whereDate('o.created_at', '=', date('Y-m-d'));
		// 	}

		// 	if($request->get('date_filter') == 'yesterday' ){
		// 		$orders = $orders->whereDate('o.created_at', '=', date('Y-m-d',strtotime("-1 days")));
		// 	}

		// 	if($request->get('date_filter') == 'last_week' ){
		// 		$orders = $orders->whereDate('o.created_at', '>=', date('Y-m-d',strtotime("Today - 6 Day")));
		// 	}

		// 	}else{
		// 		$expl = explode('/', $request->get('date_filter'));

		// 		if($expl[1] == 'specific_month' ){
		// 			$days = cal_days_in_month(CAL_GREGORIAN, $expl[0], 2019);
		// 			$fDate = date('Y-m-d', mktime(0, 0, 0, $expl[0], 1, $expl[2]));
		// 			$lDate = date('Y-m-d', mktime(0, 0, 0, $expl[0], $days, $expl[2]));
		// 			$orders = $orders->whereDate('o.created_at', '>=', $fDate)->whereDate('o.created_at', '<=', $lDate);
		// 		}

		// 		if($expl[1] == 'specific_year' ){
		// 			$days = cal_days_in_month(CAL_GREGORIAN, 12, 2019);
		// 			$fDate = date('Y-m-d', mktime(0, 0, 0, 1, 1, $expl[0]));
		// 			$lDate = date('Y-m-d', mktime(0, 0, 0, 12, $days, $expl[0]));
		// 			$orders = $orders->whereDate('o.created_at', '>=', $fDate)->whereDate('o.created_at', '<=', $lDate);
		// 		}

		// 		if($expl[1] == 'specific_day' ){
		// 			$orders = $orders->whereDate('o.created_at', '=', $expl[0]);
		// 		}

		// 		if($expl[1] == 'date_range' ){
		// 			$fDate = $expl[0];
		// 			$lDate = $expl[2];
		// 			$orders = $orders->whereDate('o.created_at', '>=', $fDate)->whereDate('o.created_at', '<=', $lDate);
		// 		}
		// 	}

		// }


		$end_date = '';
		if ($request->has('start_date') && $request->get('start_date')) {
			$start_date = $request->get('start_date');
			$orders = $orders->whereDate('o.created_at', '>=', $start_date); //format yyyy-mm-dd
			$end_date = $start_date;
		}

		if ($request->has('end_date') && $request->get('end_date')) {
			$end_date = ($request->has('end_date')) ? $request->get('end_date') : $end_date;
			$orders = $orders->whereDate('o.created_at', '<=', $end_date); //format yyyy-mm-dd
		}
		if ($request->has('transaction_sub_type') && $request->transaction_sub_type == 'hold') {

			if ($request->has('sort') && $request->get('sort')) {
				$sort = $request->get('sort');
				if ($sort == 'new') {
					$orders = $orders->orderBy('o.od_id', 'DESC');
				} elseif ($sort == 'old') {
					$orders = $orders->orderBy('o.od_id', 'ASC');
				} elseif ($sort == 'customer_asc') {
					$orders = $orders->orderBy('c.first_name', 'ASC');
				} elseif ($sort == 'customer_desc') {
					$orders = $orders->orderBy('c.first_name', 'DESC');
				}
			} else {
				$orders = $orders->orderBy('o.od_id', 'desc');
			}

			if ($request->has('search_term') && $request->get('search_term')) {
				//$orders = $orders->orWhere('order_id', $vu_id);
				$search_term = $request->get('search_term');
				$orders = $orders->where(function ($query) use ($search_term) {
					$query->where('o.order_id', 'like', '%' . $search_term . '%')
						->orWhere('c.first_name', 'like', '%' . $search_term . '%')
						->orWhere('c.mobile', 'like', '%' . $search_term . '%');
				});
			}

			$orders->select('c.first_name', 'c.last_name', 'c.mobile', 'o.od_id as id', 'o.transaction_type', 'o.transaction_sub_type', 'o.user_id', 'o.order_id', 'o.total', 'o.date', 'o.created_at', 'o.v_id', 'o.store_id');
		} else {

			if ($request->has('sort') && $request->get('sort')) {
				$sort = $request->get('sort');
				if($sort == "dsc"){
					$sort = 'desc';
				}
				if ($sort == 'new') {
					$orders = $orders->orderBy('o.od_id', 'DESC');
				} elseif ($sort == 'old') {
					$orders = $orders->orderBy('o.od_id', 'ASC');
				} elseif ($sort == 'customer_asc') {
					$orders = $orders->orderBy('c.first_name', 'ASC');
				} elseif ($sort == 'customer_desc') {
					$orders = $orders->orderBy('c.first_name', 'DESC');
				} else {
					$orders = $orders->orderBy('o.od_id', $sort);
				}
			} else {
				// $orders = $orders->orderBy('o.od_id', 'desc');
				$orders = $orders->orderBy('o.created_at', 'desc')->orderBy('o.od_id', 'desc');
			}

			if ($request->has('payment_method') && $request->get('payment_method')) {
				$orders = $orders->where('p.method', $request->get('payment_method'));
			}

			if ($request->has('search_term') && $request->get('search_term')) {
				//$orders = $orders->orWhere('order_id', $vu_id);
				$search_term = $request->get('search_term');
				$orders = $orders->where(function ($query) use ($search_term) {
					$query->where('inv.invoice_id', 'like', '%' . $search_term . '%')
						->orWhere('c.first_name', 'like', '%' . $search_term . '%')
						->orWhere('c.mobile', 'like', '%' . $search_term . '%');
				});
			}

			$orders->select('c.first_name', 'c.last_name', 'c.mobile', 'inv.id', 'o.od_id', 'o.transaction_type', 'o.transaction_sub_type', 'o.user_id', 'inv.invoice_id', 'o.order_id', 'o.total', 'o.date', 'o.created_at', 'o.v_id', 'o.store_id', 'o.status');
		}

		// dd($orders->dd());
		$orders = $orders->paginate(10);
		$paginateData['last_page'] = $orders->lastPage(); 
		$paginateData['total_records'] = $orders->total(); 
		$paginateData['per_page'] = $orders->perPage(); 
		//$stores = Store::select('store_id', DB::raw(" CONCAT(name,' - ',location) as name") )->where('status', '1')->get();
		// $stores = DB::table('stores as s')
		// 			->join('vendor as v', 's.v_id' , 'v.id')
		// 			->select('s.store_id', DB::raw(" CONCAT(s.name,' - ',s.location) as name") )
		// 			->where('s.status','1')->where('v.status','1')
		// 			->get();
		//$vendorAuth = VendorAuth::select('id as v_id','vendor_name')->where('status','1')->where('store_active','1')->get();
		// $vendor = DB::table('vendor')->where('status','1')->select('id as v_id','name as vendor_name')->get();


		$data = array();
		foreach ($orders as $key => $value) {
			$payment_mod = '';
			if ($request->has('transaction_sub_type') && $request->transaction_sub_type == 'hold') {

				$carts = DB::table('order_details')->where('v_id', $value->v_id)->where('store_id', $value->store_id)->where('user_id', $value->user_id)->where('t_order_id', $value->id)->get();
				$payment = DB::table('payments')->where('order_id', $value->order_id)->get();
			} else {

				$carts = DB::table('invoice_details')->where('v_id', $value->v_id)->where('store_id', $value->store_id)->where('user_id', $value->user_id)->where('t_order_id', $value->id)->get();

				$payment = DB::table('payments')->where('status', '!=', 'pending')->where('invoice_id', $value->invoice_id)->get();
			}
			if ($value->transaction_sub_type == 'lay_by') {
				$carts = DB::table('order_details')->where('v_id', $value->v_id)->where('store_id', $value->store_id)->where('user_id', $value->user_id)->where('t_order_id', $value->od_id)->get();
				$payment = DB::table('payments')->where('status', '!=', 'pending')->where('order_id', $value->order_id)->get();
			}

			if (!$payment->isEmpty()) {
				$payment_mod = $payment->pluck('method')->all();
				$payment_mod = array_map('ucfirst', $payment_mod);
				$payment_mod = implode(',', $payment_mod);
			}

			//$items_of_pc = $carts->where('weight_flag','0')->sum('qty');
			//$items_of_w = $carts->where('weight_flag','1')->count();
			//$items_of_pc = $carts->where('plu_barcode','=', '')->sum('qty');
			//$items_of_w = $carts->where('plu_barcode','!=', '')->count();
			// Condition for spar store only for order id
			if ($value->v_id == 4) {
				$invoice_id = $value->order_id;
			} else {
				if ($value->transaction_sub_type == 'hold' || $value->transaction_sub_type == 'un_hold' || $value->transaction_sub_type == 'lay_by') {
					$invoice_id = $value->order_id;
				} else {

					$invoice_id = $value->invoice_id; //get_invoice_no($value->invoice_id);
				}
			}
			//$items = $items_of_pc + $items_of_w;

			$cart_qty_total = 0;
			foreach ($carts as $cart) {
				if ($cart->weight_flag) {
					$cart_qty_total =  $cart_qty_total + 1;
				} else {

					if ($cart->plu_barcode) {
						$cart_plu_qty = $cart->qty;
						$cart_plu_qty = explode('.', $cart_plu_qty);
						//dd($cart_plu_qty);
						if (count($cart_plu_qty) > 1) {
							$cart_qty_total =  $cart_qty_total + 1;
						} else {
							$cart_qty_total =  $cart_qty_total + $cart->qty;
						}
					} else {
						$cart_qty_total =  $cart_qty_total + $cart->qty;
					}
				}
			}

			$logo = DB::table('stores')->select('name', 'store_logo', 'store_icon', 'location')->where('store_id', $value->store_id)->where('v_id', $value->v_id)->first();

			if(!empty($invoice_id) && $invoice_id !=''){
			$data[] = array(
				'OD_ID' => $value->id,
				'Order_ID' => $invoice_id,
				'Amount' => round($value->total,3),
				'Date' => date('d M Y', strtotime($value->date)),
				'Status' => 'Success', //$value->status,
				'Transaction_Sub_Type' => $value->transaction_sub_type,
				'V_ID' => $value->v_id,
				'Store_ID' => $value->store_id,
				'Store_Name' =>  $logo->name,
				'Customer_Name' => $value->first_name . ' ' . $value->last_name,
				'Cloud_Date' => date('d M Y H:i A', strtotime($value->created_at)),
				'Location' => $logo->location,
				'Qty' => (string) $cart_qty_total,
				'Store_Icon' => $logo->store_icon,
				'Store_Logo' => $logo->store_logo,
				'Mobile'	=> (string) $value->mobile,
				'Transaction_Type' => $value->transaction_type,
				'MOP' => $payment_mod
			);
			}
		}
		// }
		//$status_filter=array('sales'=>'Completed Sale','hold'=>'Hold','lay_by'=>'Lay by','return'=>'Return');
		//$sort_type  = array('new'=>'Newest First','old'=>'Oldest First','customer_asc'=>'Customer Name A-Z','customer_desc'=>'Customer Name Z-A');
		$status_filter = [
			['key' => 'sales', 'name' => 'Completed Sale'],
			['key' => 'hold', 'name' => 'Hold'],
			['key' => 'lay_by', 'name' => 'Lay by'],
			['key' => 'return', 'name' => 'Return']
		];

		$sort_type = [
			['key' => 'new', 'name' => 'Newest First'],
			['key' => 'old', 'name' => 'Oldest First'],
			['key' => 'customer_asc', 'name' => 'Customer Name A-Z'],
			['key' => 'customer_desc', 'name' => 'Customer Name Z-A']
		];
		return response()->json(['status' => 'my_order', 'data' => $data, 'logo_path' => store_logo_link(), 'vendor_list' => [], 'store_list' => [], 'payment_mod' => $paymentMod, 'status_filter' => $status_filter, 'sort_type' => $sort_type, 'pagination' => @$paginateData], 200);
	}

	public function b2b_my_order(Request $request)
	{

		$v_id = $request->v_id;
		$store_id = $request->store_id;
		//$c_id = $request->agent_id;
		$trans_from = $request->trans_from;
		$vu_id            = $request->vu_id;

		$orders = B2bOrder::join('b2b_order_extra', 'b2b_orders.order_id', '=', 'b2b_order_extra.order_id')
			->join('agents', 'b2b_orders.user_id', '=', 'agents.id')
			->where('b2b_orders.vu_id', $vu_id)->orderBy('b2b_orders.od_id', 'DESC')
			// ->where('b2b_orders.vu_id',$vu_id)
			->get();
		//dd($orders);
		$data = [];
		foreach ($orders as $key => $value) {


			$data[] = array(
				'order_id' => $value->order_id,
				'agent_id' => $value->id,
				'agent_name' => $value->agent_name,
				'site_name' => $value->destination_site,
				'date' => date('d M Y', strtotime($value->date))
			);
		}

		return response()->json(['status' => 'my_order', 'data' => $data, 'logo_path' => store_logo_link()], 200);
	}
}
