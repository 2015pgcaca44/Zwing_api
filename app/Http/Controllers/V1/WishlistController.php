<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Wishlist;
use DB;
use Auth;

class WishlistController extends Controller
{
    public function __construct()
	{
		$this->middleware('auth');
	}

	public function add_to_wishlist(Request $request)
	{
		$v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;
        $product_id = $request->product_id;
        $barcode = $request->barcode;

        $wishlist_count = Wishlist::where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('product_id', $product_id)->where('barcode', $barcode)->count();

        if(!empty($wishlist_count)) {
        	return response()->json(['status' => 'product_exists', 'message' => 'Product Exists in wishlist'], 409);
        }

        $wishlist = new Wishlist;

        $wishlist->store_id = $store_id;
        $wishlist->v_id = $v_id;
        $wishlist->user_id = $c_id;
        $wishlist->product_id = $product_id;
        $wishlist->barcode = $barcode;
        $wishlist->date = date('Y-m-d');
        $wishlist->time = date('h:i:s');
        $wishlist->month = date('m');
        $wishlist->year = date('Y');

        $wishlist->save();

        return response()->json(['status' => 'add_to_wishlist', 'message' => 'Product Added in your wishlist'], 200);
	}

	public function wishlist_details(Request $request)
	{
		$v_id = $request->v_id;
        $c_id = $request->c_id;
        $store_id = $request->store_id;

        $wishlist_data = array();

        $wishlists = Wishlist::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->get();

        foreach ($wishlists as $key => $wishlist) {
            // $product = DB::table('zwv_inventory'.$v_id.$store_id)->where('barcode', $value->barcode)->first();
            $api_link_column = DB::table('api_link')->select('API_Column','V_API_Column')->where('Table', 'zwv_inventory'.$v_id.$store_id)->where('cart_view', 1)->get();
            foreach ($api_link_column as $key => $value) {
                $api_column_id = $value->API_Column;
                $api_columns = DB::table('api_columns')->select('api_id','Name')->where('api_id', $api_column_id)->first();
                $v_column = get_vendor_column_name($value->V_API_Column,$v_id,$store_id);
                $product_details = DB::table('zwv_inventory'.$v_id.$store_id)->select($v_column)->where('barcode', $wishlist->barcode)->first();
                $product_data[get_api_column_name($value->API_Column)] = $product_details->$v_column;
            }
            $wishlist_data[] = array(
                    'wishlist_id'       => $wishlist->wishlist_id,
                    'product_data'      => $product_data,
                    'store_id'          => $wishlist->store_id,
                    'v_id'  	        => $wishlist->v_id
            );
        }

        return response()->json(['status' => 'cart_details', 'message' => 'Your Cart Details', 'data' => $wishlist_data,'product_image_link' => product_image_link() ],200);
	}

	public function remove_product_from_wishlist(Request $request)
	{
		$c_id = $request->c_id;
    	$store_id = $request->store_id;
    	$v_id = $request->v_id;
    	$wishlist_id = $request->wishlist_id;
    	$product_id = $request->product_id;

    	Wishlist::where('wishlist_id', $wishlist_id)->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('product_id', $product_id)->delete();

    	return response()->json(['status' => 'remove_product_from_wishlist', 'message' => 'Product Remove successfully from wishlist' ],200);
	}

    public function wishlist(Request $request)
    {
        $wishlists = Wishlist::where('user_id', Auth::user()->c_id)->get();
        $data = array();

        foreach ($wishlists as $key => $value) {
            $product_data = DB::table('zwv_inventory'.$value->v_id.$value->store_id)->where('barcode', $value->barcode)->first();
            $data[] = array(
                'wishlist_id'       => $value->wishlist_id,
                'product_data'      => $product_data,
                'store_id'          => $value->store_id,
                'v_id'              => $value->v_id
            );
        }

        return response()->json(['status' => 'wishlist', 'data' => $data, 'product_image_link' => product_image_link() ],200);
    }

}