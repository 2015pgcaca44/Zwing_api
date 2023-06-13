<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Model\CustomerGST;
use App\User;
use App\State;

class TaxController extends Controller
{
    public function __construct()
    {
     	$this->middleware('auth');	
    }

    public function create(Request $request)
    {
    	$this->validate($request, [
    		'v_id'			=> 'required',
    		'mobile'		=> 'required',
    		'legal_name'	=> 'required',
    		'state_id'		=> 'required',
    		'vu_id'			=> 'required',
    		'gstin'			=> 'required'
    	]);

    	$c_id = User::select('c_id')
    			->where('mobile', $request->mobile)
    			->where('v_id', $request->v_id)
    			->first();

    	$gstin = CustomerGST::where('c_id', $c_id->c_id)
    			->where('v_id', $request->v_id)
    			->where('gstin', $request->gstin)
    			->where('deleted_at', '0')
    			->first();

    	if($gstin) {
    		return response()->json(['status' => 'fail', 'message' => $request->gstin.' '.'GSTIN already exists for this customer.'], 200);
    	}

    	$state = State::select('id')->where('name', $request->state_id)->first();

    	$gstInfo = CustomerGST::create(
    	[
    		'v_id' 		 => $request->v_id,
    		'c_id' 	 	 => $c_id->c_id,
    		'legal_name' => $request->legal_name,
    		'state_id' 	 => $state->id,
    		'created_by' => $request->vu_id,
    		'gstin' 	 => $request->gstin
    	]);

    	return response()->json(['status' => 'success','data'=>$gstInfo, 'message' => 'GST details added successfully.'], 200);
    }

    public function list(Request $request)
    {
        $c_id = User::select('c_id')
                ->where('mobile', $request->mobile)
                ->where('v_id', $request->v_id)
                ->first();
        $list = [];
        if(!empty($c_id)) {
            $list = CustomerGST::select('state_id', 'legal_name', 'gstin', 'id')
                ->where('v_id', $request->v_id)
                ->where('c_id', $c_id->c_id)
                ->where('deleted_at', '0')
                ->orderBy('id', 'DESC')
                ->get();

            foreach ($list as $gst => $item) {
                $state = State::select('name')->where('id', $item->state_id)->first();
                $item->state_name = $state->name;
                //unset($item->state_id);
            }
        }

        return response()->json([ 'status' => 'success', 'list' => $list ], 200);
    }

    public function update(Request $request)
    {
    	$this->validate($request, [
    		'v_id'			=> 'required',
    		'mobile'		=> 'required',
    		'legal_name'	=> 'required',
    		'state_id'		=> 'required',
    		'vu_id'			=> 'required',
    		'gstin'			=> 'required'
    	]);

    	$c_id = User::select('c_id')
    			->where('mobile', $request->mobile)
    			->where('v_id', $request->v_id)
    			->first();

    	$state = State::select('id')->where('name', $request->state_id)->first();

    	$update = CustomerGST::where('id', $request->gstin)
    			  ->update(['legal_name' => $request->legal_name, 'state_id' => $state->id]);
        
        
        
    	if($update) {
            $gstInfo = CustomerGST::select('id','legal_name','gstin','state_id')->where('id', $request->gstin)->first();                  
    		return response()->json(['status' => 'success','data'=>$gstInfo, 'message' => 'GST details updated successfully.'], 200);
    	} else {
    		return response()->json(['status' => 'fail', 'message' => 'GSTIN not found.'], 200);
    	}

    }

    public function remove(Request $request)
    {
    	$this->validate($request, [
    		'v_id'			=> 'required',
    		'mobile'		=> 'required',
    		'vu_id'			=> 'required',
    		'gstin'			=> 'required'
    	]);

    	$c_id = User::select('c_id')
    			->where('mobile', $request->mobile)
    			->where('v_id', $request->v_id)
    			->first();

    	$remove = CustomerGST::where('id', $request->gstin)->update(['deleted_at' => '1']);
    	
    	if($remove) {
    		return response()->json(['status' => 'success', 'message' => 'GSTIN deleted successfully.'], 200);
    	} else {
    		return response()->json(['status' => 'fail', 'message' => 'GSTIN not found.'], 200);
    	}
    }
}
