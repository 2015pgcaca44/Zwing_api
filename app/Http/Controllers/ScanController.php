<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Scan;
use App\Store;
use App\VendorAuth;
use DB;
use Auth;

class ScanController extends Controller
{
    public function __construct()
    {
        //$this->middleware('auth');
    }


    public function create(Request $request)
    {
        $v_id = $request->v_id;

        if($v_id == 16){
           
            $scanC = new  Spar\ScanController;
            $response = $scanC->create($request);
            return $response;

        }else{

    
            $c_id = $request->c_id;
            $store_id = $request->store_id;
            $product_id = $request->product_id;
            $barcode = $request->barcode;

            $wishlist_count = Scan::where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('product_id', $product_id)->where('barcode', $barcode)->count();

            if(!empty($wishlist_count)) {
                return response()->json(['status' => 'product_exists', 'message' => 'Product Exists in wishlist'], 409);
            }
            
            $product_data = DB::table('zwv_inventory'.$v_id.$store_id)->select('product_name')->where('barcode', $barcode)->where('product_id',$product_id)->first();

            $scan = new Scan;

            $scan->store_id = $store_id;
            $scan->v_id = $v_id;
            $scan->user_id = $c_id;
            $scan->product_id = $product_id;
            $scan->product_name =  $product_data->product_name;
            $scan->barcode = $barcode;
            $scan->date = date('Y-m-d');
            $scan->time = date('h:i:s');
            $scan->month = date('m');
            $scan->year = date('Y');

            $scan->save();

            return response()->json(['status' => 'add_to_scanlist', 'message' => 'Product Added in your Scan List'], 200);

        }
    }

    public function details(Request $request)
    {
        $v_id = $request->v_id;

        if($v_id == 16){
           
            $scanC = new  Spar\ScanController;
            $response = $scanC->details($request);
            return $response;

        }else{

            $c_id = $request->c_id;
            $store_id = $request->store_id;
            $barcode = $request->barcode;
            $scan_id = $request->scan_id;

            $scan_data = array();

             $scans = Scan::where('scan_id', $scan_id)->where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('barcode', $barcode)->get();

            foreach ($scans as $key => $scan) {
                // $product = DB::table('zwv_inventory'.$v_id.$store_id)->where('barcode', $value->barcode)->first();
                $api_link_column = DB::table('api_link')->select('API_Column','V_API_Column')->where('Table', 'zwv_inventory'.$v_id.$store_id)->where('cart_view', 1)->get();
                foreach ($api_link_column as $key => $value) {
                    $api_column_id = $value->API_Column;
                    $api_columns = DB::table('api_columns')->select('api_id','Name')->where('api_id', $api_column_id)->first();
                    $v_column = get_vendor_column_name($value->V_API_Column,$v_id,$store_id);
                    $product_details = DB::table('zwv_inventory'.$v_id.$store_id)->select($v_column)->where('barcode', $scan->barcode)->first();
                    $product_data[get_api_column_name($value->API_Column)] = $product_details->$v_column;
                }
                $scan_data[] = array(
                        'scan_id'           => $scan->scan_id,
                        'product_data'      => $product_data,
                        'store_id'          => $scan->store_id,
                        'v_id'              => $scan->v_id
                );
            }

            return response()->json(['status' => 'scan_details', 'message' => 'Your Scan Details', 'data' => $scan_data,'product_image_link' => product_image_link() ],200);
        }
    }

/*
    public function remove(Request $request)
    {
        $c_id = $request->c_id;
        $store_id = $request->store_id;
        $v_id = $request->v_id;
        $id = $request->id;
        $product_id = $request->product_id;

        Wishlist::where('id', $id)->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('product_id', $product_id)->delete();

        return response()->json(['status' => 'remove_product_from_wishlist', 'message' => 'Product Remove successfully from wishlist' ],200);
    }
*/

    public function list(Request $request)
    {


        $scanC = new  Spar\ScanController;
        $response = $scanC->list($request);
        return $response;

       /* $v_id = $request->v_id;

        if($v_id == 16){
           
           

        }else{

            $c_id = $request->c_id;
            $scans = Scan::where('user_id', $c_id);

            if($request->has('v_id')){
                $v_id = $request->get('v_id');
                $scans = $scans->where('v_id', $v_id);
            }

            if($request->has('store_id')){
                $store_id = $request->get('store_id');
                $scans = $scans->where('store_id', $store_id);
            }

            if($request->has('start_date')){
                $start_date = $request->get('start_date');
                $scans = $scans->whereDate('created_at', '>=', $start_date);//format yyyy-mm-dd
            }

            if($request->has('end_date')){
                $end_date = $request->get('end_date');
                $scans = $scans->whereDate('created_at', '<=', $end_date);//format yyyy-mm-dd
            }

            if($request->has('search_term')){
                $search_term = $request->get('search_term');
                $scans = $scans->where('product_name', 'LIKE', '%'.$search_term.'%');
               // $scans = $scans->orWhere('order_id', 'LIKE', '%'.$search_term.'%')
                                 //->orWhere('status', 'LIKE', '%'.$search_term.'%');
            }


            if($request->has('sort')){
                $sort = $request->get('sort');
                $scans = $scans->orderBy('scan_id', $sort); // value of sort desc or asc
            }else{
                $scans = $scans->orderBy('scan_id', 'desc');
            }



            $scans = $scans->paginate(10);


            $stores = DB::table('stores as s')
                        ->join('vendor_auth as v', 's.v_id' , 'v.id')
                        ->select('s.store_id', DB::raw(" CONCAT(s.name,' - ',s.location) as name") )
                        ->where('s.status','1')->where('v.status','1')->where('v.store_active','1')
                        ->get();
                        
            //$stores = Store::select('store_id', DB::raw(" CONCAT(name,' - ',location) as name") )->where('status', '1')->get();
            $vendorAuth = VendorAuth::select('id as v_id','vendor_name')->where('status','1')->where('store_active','1')->get();
            
            $data = array();

            foreach ($scans as $key => $value) {
                $product_data = DB::table('zwv_inventory'.$value->v_id.$value->store_id)->where('barcode', $value->barcode)->first();
                $sto = DB::table('stores')->select('location')->where('store_id', $value->store_id)->where('v_id', $value->v_id)->first();
                $data[] = array(
                    'scan_id'       => $value->scan_id,
                    'scan_date'     => $value->date,
                    'location'      => $sto->location,
                    'product_data'      => $product_data,
                    'store_id'          => $value->store_id,
                    'v_id'              => $value->v_id
                );
            }

             return response()->json(['status' => 'list',  'data' => $data,'product_image_link' => product_image_link(), 'vendor_list' => $vendorAuth, 'store_list' => $stores  ],200);
        }*/

    }
}
