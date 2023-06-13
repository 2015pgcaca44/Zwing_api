<?php

namespace App\Http\Controllers\V1\Biba;

use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\VendorSettingController;
use Illuminate\Http\Request;
use App\ProductRating;
use App\Cart;
use Auth;

class ProductRatingController extends Controller
{
    public function __construct()
	{
		$this->middleware('auth');
	}

	public function save_rating(Request $request)
	{
		date_default_timezone_set("Asia/Kolkata");
		$store_id = $request->store_id;
		$v_id = $request->v_id;
		$c_id = $request->c_id;
		//$product_id = $request->product_id;
		$barcode = $request->barcode;
		$star = $request->star;

		$cart = Cart::where([ ['v_id','=',$v_id], ['store_id','=',$store_id],  ['user_id','=',$c_id], ['status','=','success'],
		  //['product_id','=',$product_id] ,  
		  ['barcode','=',$barcode]
			])->first();

		if($cart){

			$pRating = ProductRating::where([ ['v_id','=',$v_id], ['store_id','=',$store_id],  ['user_id','=',$c_id],
			  //['product_id','=',$product_id] ,  
			  ['barcode','=',$barcode]
				])->first();

			if($pRating){

				$pRating->star = $star;
				$pRating->save();
				
			}else{
				$rating = new ProductRating;

				$rating->v_id = $v_id;
				$rating->store_id = $store_id;
				$rating->user_id = $c_id;
				//$rating->product_id = $product_id;
				$rating->barcode = $barcode;
				$rating->star = $star;
				$rating->date = date('Y-m-d');
				$rating->month = date('M');
				$rating->year = date('Y');
				$rating->time = date('h:i:s');

				$rating->save();
			}
			return response()->json(['status' => 'save_rating', 'message' => 'Product Rating Save Successfully' ],200);
		}else{
			return response()->json(['status' => 'fail', 'message' => 'You Cannot Give rating to this product' ],200);
		}
	}

	public function get_rating(Request $request){

		$store_id = $request->store_id;
		$v_id = $request->v_id;
		$c_id = $request->c_id;
		//$product_id = $request->product_id;
		$barcode = $request->barcode;

		$vendorS = new VendorSettingController;
		$settings = $vendorS->getSetting($v_id , 'product');
		$settings = $settings->first()->settings;
		$settings = json_decode($settings);

		if($settings->review_rating->rating->status == 'ON'){
			if($settings == 'STORE_WISE'){

			$star =	ProductRating::where([ ['v_id','=',$v_id], ['store_id','=',$store_id],  
				//['product_id','=',$product_id] ,
				['barcode','=',$barcode]
				])->avg('star');

			$count =ProductRating::where([ ['v_id','=',$v_id], ['store_id','=',$store_id],  
				//['product_id','=',$product_id] ,
				['barcode','=',$barcode]
				])->count();
			}else{

				$star =	ProductRating::where([ ['v_id','=',$v_id], ['barcode','=',$barcode] ])->avg('star');
				$count = ProductRating::where([ ['v_id','=',$v_id], ['barcode','=',$barcode] ])->count();

			}

			$star = format_number($star);

			return ['status' => 'get_rating' , 'data' => ['star' => $star , 'count' => $count] ] ;
			
		}else{

			return ['status' => 'fail' , 'message' => 'Unable to get the rating'  ] ;
		}
		
		//return response()->json(['status' => 'get_rating', 'data' => ['rating' => $star] ],200);

	}
}
