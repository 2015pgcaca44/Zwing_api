<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\ProductReview;
use App\Cart;
use Auth;

class ProductReviewController extends Controller
{
    public function __construct()
	{
		$this->middleware('auth');
	}

	public function save_review(Request $request)
	{
		date_default_timezone_set("Asia/Kolkata");
		$store_id = $request->store_id;
		$v_id = $request->v_id;
		$c_id = $request->c_id;
		//$product_id = $request->product_id;
		$barcode = $request->barcode;
		$title = $request->title;
		$description = $request->description;

		$cart = Cart::where([ ['v_id','=',$v_id], ['store_id','=',$store_id],  ['user_id','=',$c_id], ['status','=','success'],
		  //['product_id','=',$product_id] ,  
		  ['barcode','=',$barcode]
			])->first();

		if($cart){

			$review = ProductReview::where([ ['v_id','=',$v_id], ['store_id','=',$store_id],  ['user_id','=',$c_id],
			  //['product_id','=',$product_id] ,  
			  ['barcode','=',$barcode]
				])->first();

			if($review){

				$review->title = $title;
				$review->description = $description;
				$review->save();
				
			}else{
				$review = new ProductReview;

				$review->v_id = $v_id;
				$review->store_id = $store_id;
				$review->user_id = $c_id;
				//$rating->product_id = $product_id;
				$review->barcode = $barcode;
				$review->title = $title;
				$review->description = $description;

				$review->save();
			}
			return response()->json(['status' => 'save_review', 'message' => 'Product Review Save Successfully' ],200);
		}else{
			return response()->json(['status' => 'fail', 'message' => 'You cannot give Review to this product' ],200);
		}
	}

	public function get_review(Request $request){

		$store_id = $request->store_id;
		$v_id = $request->v_id;
		$c_id = $request->c_id;
		//$product_id = $request->product_id;
		$barcode = $request->barcode;

		$vendorS = new VendorSettingController;
		$settings = $vendorS->getSetting($v_id , 'product');
		$settings = $settings->first()->settings;
		$settings = json_decode($settings);

		if($settings->review_rating->review->status == 'ON'){
				
			$display_type = $settings->review_rating->review->display_type;

			if($display_type == 'STORE_WISE'){

				$review =	ProductReview::select('title','description')->where([ ['v_id','=',$v_id], ['store_id','=',$store_id],  
				//['product_id','=',$product_id] ,
				['barcode','=',$barcode]
				])->get();

			}else{

				$review =	ProductReview::select('title','description')->where([ ['v_id','=',$v_id], ['barcode','=',$barcode] ])->get();
			}

			return ['status' => 'get_review', 'data' => $review ];
		}else{

			return ['status' => 'fail', 'message' => 'Unable to get the review' ];
		}
		
		

	}
}
