<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\User;
use App\CustomerLoginLog;
use App\Cart;
use App\Order;
use DB;

class CustomerController extends Controller
{

	public function __construct()
	{
		$this->middleware('auth');
	}

    public function profile(Request $request)
    {
    	$c_id = $request->c_id;
    	$mobile = $request->mobile;

    	$user = User::select('mobile','first_name','last_name','gender','dob','email','email_active')->where('c_id', $c_id)->where('mobile', $mobile)->first();

    	return response()->json(['status' => 'profile_data', 'message' => 'Profile Data', 'data' => $user],200);
    }
	
	
	public function log($param){

        $log  = new CustomerLoginLog;
        $log->latitude = $param['latitude'];
        $log->longitude = $param['longitude'];
        $log->user_id = $param['c_id'];

        $mapLoc = new MapLocationController;
        $response = $mapLoc->addressBylatLongArray($param['latitude'] , $param['longitude']);
        if(!empty($response) && $response['status'] !='fail'){
            $log->locality = $response['data']['locality'];
            $log->address = $response['data']['address'];
        }

        $log->save();

    }

}
