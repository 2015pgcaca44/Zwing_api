<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\FeedbackQuestions;
use App\CustomerFeedback;

class CustomerFeedbackController extends Controller
{
	public function __construct()
	{
		$this->middleware('auth');
	}

	public function getFeedbackQuestions(Request $request)
	{
		$c_id = $request->c_id;
		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$data = [];

		$lists = FeedbackQuestions::where('store_id', $store_id)->where('vendor_id', $v_id)->get();

		foreach ($lists as $key => $value) {
			$data[] = [
				'name' => $value->question,
				'option_style_type' => $value->option_style_type,
				'options' => json_decode($value->options)
			];
		}

		return response()->json(['status' => 'get_feedback_questions', 'data' => $data ,  'default_image_path' => image_path().'feedback/'], 200);
	}

	public function submitAnswer(Request $request)
	{
		$c_id = $request->c_id;
		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$order_id = $request->order_id;
		$answer = $request->answer;
		//dd($answer);
		$quesAns = json_decode($answer, true);
		//dd($quesAns);
		foreach($quesAns as $val){
			$custFeedback =  new CustomerFeedback;
			$custFeedback->vendor_id = $v_id;
			$custFeedback->store_id = $store_id;
			$custFeedback->user_id = $c_id;
			$custFeedback->order_id = $order_id;
			$custFeedback->question_id = $val[0];
			$custFeedback->answer = $val[1];
			$custFeedback->save();
			
		}

		return response()->json(['status' => 'submit_feedback' ], 200);
	}

}
