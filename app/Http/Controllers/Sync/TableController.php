<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Address;
use DB;

class TableController extends Controller
{
	public function __construct()
	{
		$this->middleware('auth');
	}

	public function all(Request $request){


		$tableName = $request->table_name;
		$v_id = $request->v_id;
		$store_id = $request->store_id;

		try{

			$table = DB::table($tableName)->select('*')->where('store_id', $store_id)->where('vendor_id', $v_id)->get();

			return response()->json(['status' => 'success', 'data' => $table ]);
		}catch(Eception $e){

			return response()->json(['status' => 'fail', 'message' => 'There is a  error in fetching data']); 
		}

	}

}