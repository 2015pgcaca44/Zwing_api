<?php

namespace App\Http\Controllers\V1\Crimsouneclub;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PDF;
use App\Store;
use App\Order;
use App\Cart;
use App\User;
use DB;
use Auth;
use App\VendorAuth;

class ProfileController extends Controller
{


    public function __construct()
    {
        $this->middleware('auth');
    }

    public function my_order(Request $request)
    {
        // dd($request->all());
        //$orders = Order::select('od_id','user_id','order_id','total','date','status','v_id','store_id','o_id')->where('status','!=','process')->where('transaction_type','sales');
        $orders = DB::table('orders as o')
                ->join('customer_auth as c', 'c.c_id','o.user_id')
                ->where('o.status','!=','process')->where('o.transaction_type','sales');
        
        
        if($request->has('vu_id')){
            
            $vu_id = $request->get('vu_id');
            $orders = $orders->where('o.vu_id', $vu_id);

        }else if($request->has('c_id')){
            
            $c_id = $request->get('c_id');
            $orders = $orders->where('o.user_id', $c_id);
        }
        
        if($request->has('v_id')){
            $v_id = $request->get('v_id');
            $orders = $orders->where('o.v_id', $v_id);
        }

        if($request->has('store_id')){
            $store_id = $request->get('store_id');
            $orders = $orders->where('o.store_id', $store_id);
        }

        $end_date ='';
        if($request->has('start_date')){
            $start_date = $request->get('start_date');
            $orders = $orders->whereDate('o.created_at', '>=', $start_date);//format yyyy-mm-dd
            $end_date = $start_date;
        }

        if($request->has('end_date') || $end_date!='' ){
            $end_date = ($request->has('end_date'))?$request->get('end_date'):$end_date;
            $orders = $orders->whereDate('o.created_at', '<=', $end_date);//format yyyy-mm-dd
        }

        if($request->has('sort')){
            $sort = $request->get('sort');
            $orders = $orders->orderBy('o.od_id', $end_date); // value of sort desc or asc
        }else{
            $orders = $orders->orderBy('o.od_id', 'desc');
        }

        if($request->has('search_term')){
            //$orders = $orders->orWhere('order_id', $vu_id);
            $search_term = $request->get('search_term');
            $orders = $orders->where(function ($query) use ($search_term) {
                        $query->where('o.order_id', 'like', '%'.$search_term.'%')
                              ->orWhere('c.first_name', 'like', '%'.$search_term.'%')
                              ->orWhere('c.mobile', 'like', '%'.$search_term.'%')
                        ;
                    });
        }

        $orders->select('c.first_name','c.last_name','c.mobile','o.od_id','o.user_id','o.order_id','o.total','o.date','o.status','o.v_id','o.store_id','o.o_id');
        
        $orders = $orders->paginate(10);

        // dd($orders);

        //$stores = Store::select('store_id', DB::raw(" CONCAT(name,' - ',location) as name") )->where('status', '1')->get();
        $stores = DB::table('stores as s')
                    ->join('vendor as v', 's.v_id' , 'v.id')
                    ->select('s.store_id', DB::raw(" CONCAT(s.name,' - ',s.location) as name") )
                    ->where('s.status','1')->where('v.status','1')
                    ->get();
       // $vendorAuth = VendorAuth::select('id as v_id','vendor_name')->where('status','1')->where('store_active','1')->get();
        $vendor = DB::table('vendor')->where('status','1')->select('id as v_id','name as vendor_name')->get();
        
        $data = array();

        foreach ($orders as $key => $value) {
            $carts = DB::table('cart')->where('v_id', $value->v_id)->where('store_id', $value->store_id)->where('user_id', $value->user_id)->where('order_id', $value->o_id)->get();
            //$items_of_pc = $carts->where('weight_flag','0')->sum('qty');
            //$items_of_w = $carts->where('weight_flag','1')->count();
            $items_of_pc = $carts->where('plu_barcode','=', '')->sum('qty');
            $items_of_w = $carts->where('plu_barcode','!=', '')->count();
            
            $items = $items_of_pc + $items_of_w;
            $logo = DB::table('stores')->select('store_logo','store_icon','location')->where('store_id', $value->store_id)->where('v_id', $value->v_id)->first();
            $data[] = array(
                'OD_ID' => $value->od_id,
                'Order_ID' => $value->order_id,
                'Amount' => $value->total,
                'Date' => date('d M', strtotime($value->date)),
                'Status' => $value->status,
                'V_ID' => $value->v_id,
                'Store_ID' => $value->store_id,
                'Location' => $logo->location,
                'Qty' => (string)$items,
                'Store_Icon' => $logo->store_icon,
                'Store_Logo' => $logo->store_logo,
                'Mobile'    => (string)$value->mobile
            );

        }



        return response()->json(['status' => 'my_order', 'data' => $data, 'logo_path' => store_logo_link(),'vendor_list' => $vendor, 'store_list' => $stores  ],200);
    }

    public function format_and_string($value)
    {
        return (string)sprintf('%0.2f', $value);
    }

}