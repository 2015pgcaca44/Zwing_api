<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Rating;
use Auth;

class RatingController extends Controller
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
		$star = $request->star;

		$rating = Rating::where(['V_ID' => $v_id , 'Store_ID' => $store_id, 'User_ID' => Auth::user()->c_id ])->first();

		if($rating){

			$rating->star = $star;
			$rating->save();

		}else{

			$rating = new Rating;

			$rating->V_ID = $v_id;
			$rating->Store_ID = $store_id;
			$rating->User_ID = Auth::user()->c_id;
			$rating->Star = $star;
			$rating->Date = date('Y-m-d');
			$rating->Month = date('M');
			$rating->Year = date('Y');
			$rating->Time = date('h:i:s');

			$rating->save();


		}

		return response()->json(['status' => 'save_store_rating', 'message' => 'Store Rating Save Successfully' ],200);
	}
}
