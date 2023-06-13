<?php

namespace App\Http\Controllers\V1\Star;

use App\Http\Controllers\Controller;
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
		$this->middleware('auth');
	}

    public function create(Request $request)
    {
        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;
        $product_id = $request->product_id;
        $barcode = $request->barcode;

        $store_db_name = get_store_db_name(['store_id' => $store_id]);
        $wishlist_count = Scan::where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('barcode', $barcode)->count();

        if(!empty($wishlist_count)) {
            return response()->json(['status' => 'product_exists', 'message' => 'Product Exists in wishlist'], 409);
        }
        
        $item_master = DB::table($store_db_name.'.item_master')->select('ITEM')->where('barcode', $barcode)->first();
        $price_master = DB::table($store_db_name.'.price_master')->select('ITEM_DESC')->where('ITEM', $item_master->ITEM)->first();

        $scan = new Scan;

        $scan->store_id = $store_id;
        $scan->v_id = $v_id;
        $scan->user_id = $c_id;
        $scan->item_id = $item_master->ITEM;
        $scan->product_name =  $price_master->ITEM_DESC;
        $scan->barcode = $barcode;
        $scan->date = date('Y-m-d');
        $scan->time = date('h:i:s');
        $scan->month = date('m');
        $scan->year = date('Y');

        $scan->save();

        return response()->json(['status' => 'add_to_scanlist', 'message' => 'Product Added in your Scan List'], 200);
    }

    public function details(Request $request)
    {
        $v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;
        $barcode = $request->barcode;
        $scan_id = $request->scan_id;

        $store_db_name = get_store_db_name(['store_id' => $store_id]);
        $scan_data = array();

         $scans = Scan::where('scan_id', $scan_id)->where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('barcode', $barcode)->get();

         //dd($scans);

        foreach ($scans as $key => $scan) {
            // $product = DB::table('zwv_inventory'.$v_id.$store_id)->where('barcode', $value->barcode)->first();
            $price_master = DB::table($store_db_name.'.price_master')->where('ITEM', $scan->item_id)->first();

            $product_data['p_id'] = $scan->item_id;
            $product_data['category'] = '';
            $product_data['brand_name'] = '';
            $product_data['sub_categroy'] = '';
            $product_data['whishlist'] = 'No';
            $product_data['p_name'] = $price_master->ITEM_DESC;
           // $product_data['offer'] = (count($offer_data['available_offer']) > 0)?'Yes':'No';
           // $product_data['offer_data'] = [ 'applied_offers' => $offer_data['applied_offer'] , 'available_offers' =>$offer_data['available_offer']  ];
            //$product_data['multiple_price_flag'] = $offer_data['multiple_price_flag'];
           // $product_data['multiple_mrp'] = $offer_data['multiple_mrp'];
            $product_data['r_price'] = format_number($price_master->MRP1);
            $product_data['s_price'] = format_number($price_master->CSP1);
            
            $product_data['varient'] = '';
            $product_data['images'] = '';
            $product_data['description'] = '';
            $product_data['deparment'] = '';
            $product_data['barcode'] = $scan->barcode;

            $scan_data[] = array(
                    'scan_id'           => $scan->scan_id,
                    'product_data'      => $product_data,
                    'store_id'          => $scan->store_id,
                    'v_id'              => $scan->v_id
            );
        }

        return response()->json(['status' => 'scan_details', 'message' => 'Your Scan Details', 'data' => $scan_data,'product_image_link' => product_image_link() ],200);
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
        //dd($request->all());
        $c_id = $request->c_id;
        $scans = Scan::where('user_id', $c_id);

        if($request->has('v_id')){
           // echo 'inside v_id';exit;
            //echo $v_id = $request->get('v_id');
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

        //echo $scans->toSql();exit;

        $scans = $scans->paginate(10);

        //dd($scans);


        $stores = DB::table('stores as s')
                    ->join('vendor_auth as v', 's.v_id' , 'v.id')
                    ->select('s.store_id', DB::raw(" CONCAT(s.name,' - ',s.location) as name") )
                    ->where('s.status','1')->where('v.status','1')->where('v.store_active','1')
                    ->get();
                    
        //$stores = Store::select('store_id', DB::raw(" CONCAT(name,' - ',location) as name") )->where('status', '1')->get();
        $vendorAuth = VendorAuth::select('id as v_id','vendor_name')->where('status','1')->where('store_active','1')->get();
        
        $data = array();

        foreach ($scans as $key => $value) {
            //echo '';exit;

            $sto = DB::table('stores')->select('location','store_db_name')->where('store_id', $value->store_id)->where('v_id', $value->v_id)->first();
            $store_db_name = $sto->store_db_name;
            $price_master = DB::table($store_db_name.'.price_master')->where('ITEM', $value->item_id)->first();

            $product_data['p_id'] = $value->item_id;
            $product_data['category'] = '';
            $product_data['brand_name'] = '';
            $product_data['sub_categroy'] = '';
            $product_data['whishlist'] = 'No';
            $product_data['p_name'] = $price_master->ITEM_DESC;
           // $product_data['offer'] = (count($offer_data['available_offer']) > 0)?'Yes':'No';
           // $product_data['offer_data'] = [ 'applied_offers' => $offer_data['applied_offer'] , 'available_offers' =>$offer_data['available_offer']  ];
            //$product_data['multiple_price_flag'] = $offer_data['multiple_price_flag'];
           // $product_data['multiple_mrp'] = $offer_data['multiple_mrp'];
            $product_data['r_price'] = format_number($price_master->MRP1);
            $product_data['s_price'] = format_number($price_master->CSP1);
            
            $product_data['varient'] = '';
            $product_data['images'] = '';
            $product_data['description'] = '';
            $product_data['deparment'] = '';
            $product_data['barcode'] = $value->barcode;

            
            
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

    }
}
