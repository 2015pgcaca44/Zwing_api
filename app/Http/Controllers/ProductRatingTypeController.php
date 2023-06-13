<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\ProductRatingType;
use Auth;

class ProductRatingTypeController extends Controller
{
    public function __construct()
	{
		$this->middleware('auth');
	}

	public function save(Request $request)
	{
		
		$v_id = $request->v_id;
		$type = $request->type; // STORE_WISE | VENDOR_WISE
		$c_id = $request->c_id;

		$ratingType = ProductRatingType::where('v_id',$v_id)->first();

		if($ratingType){

			$ratingType->type = $type;
			$ratingType->user_id = $c_id;
			$ratingType->save();

		}else{

			$ratingType = new ProductRatingType;

			$ratingType->v_id = $v_id;
			$ratingType->user_id = $c_id;
			$ratingType->type = $type;

			$ratingType->save();


		}

	

		return response()->json(['status' => 'save', 'message' => 'Product Rating  Type Save Successfully' ],200);
	}

	public function getType(Request $request){

		$v_id = $request->v_id;

		$ratingType = ProductRatingType::select('type')->where('v_id',$v_id)->first();

		return $ratingType->type;

		//return response()->json(['status' => 'sucess', 'data' => $ratingType ]);

	}

}
