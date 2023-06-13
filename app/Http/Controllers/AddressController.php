<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Address;

class AddressController extends Controller
{
	public function __construct()
	{
		$this->middleware('auth');
	}

	public function add(Request $request)
	{
		$c_id = $request->c_id;
		$name = $request->name;
		$address_nickname = $request->address_nickname;
		$mobile = $request->mobile;
		$pincode = $request->pincode;
		$address1 = $request->address1;
		$address2 = $request->address2;
		$landmark = $request->landmark;
		$city = $request->city;
		$state = $request->state;

		$address = new Address;

		$address->c_id = $c_id;
		$address->name = $name;
		$address->address_nickname = $address_nickname;
		$address->mobile = $mobile;
		$address->pincode = $pincode;
		$address->address1 = $address1;
		$address->address2 = $address2;
		$address->landmark = $landmark;
		$address->city = $city;
		$address->state = $state;

		$address->save();

		return response()->json(['status' => 'add_address', 'message' => 'Address Added Successfully'], 200);
	}

	public function check(Request $request)
	{
		$c_id = $request->c_id;
		$address_nickname =  $request->address_nickname;

		$exists = Address::where('address_nickname', $address_nickname)->where('c_id', $c_id)->count();

		if(!empty($exists)) {
			return response()->json(['status' => 'address_nickname_already_exists', 'message' => 'Address Nickname Already Exists' ], 409);
		} else {
			return response()->json(['status' => 'new_address_nickname', 'message' => 'Address Nickname Available' ], 200);
		}
	}

	public function lists(Request $request)
	{
		$c_id = $request->c_id;

		$address = Address::where('c_id', $c_id)->where('deleted_status', 0)->orderBy('is_primary','desc')->get();

		return response()->json(['status' => 'address_list', 'message' => 'Your Address List', 'data' => $address ], 200);
	}

	public function update(Request $request)
	{
		$c_id = $request->c_id;
		$name = $request->name;
		$address_nickname = $request->address_nickname;
		$mobile = $request->mobile;
		$pincode = $request->pincode;
		$address1 = $request->address1;
		$address2 = $request->address2;
		$landmark = $request->landmark;
		$city = $request->city;
		$state = $request->state;
		$aid = $request->aid;

		$address = Address::find($aid);

		$address->c_id = $c_id;
		$address->name = $name;
		$address->address_nickname = $address_nickname;
		$address->mobile = $mobile;
		$address->pincode = $pincode;
		$address->address1 = $address1;
		$address->address2 = $address2;
		$address->landmark = $landmark;
		$address->city = $city;
		$address->state = $state;

		$address->save();

		return response()->json(['status' => 'update_address', 'message' => 'Address Updated Successfully'], 200);
	}

	public function delete(Request $request)
	{
		$c_id = $request->c_id;
		$aid = $request->aid;
		$address = Address::find($aid);
		if($address->is_primary == '1'){
			
			return response()->json(['status' => 'fail', 'message' => 'You cannot Delete Primary Address'], 200);	
		}else{
			
			$address = $address->update(['deleted_status' => 1]);
			return response()->json(['status' => 'delete_address', 'message' => 'Address Deleted Successfully'], 200);
		}
		
	}

	public function setPrimary(Request $request)
	{
		$c_id = $request->c_id;
		$aid = $request->aid;

		Address::where('c_id', $c_id)->update([ 'is_primary' => "0" ]);
		$address = Address::find($aid);
		$address->is_primary = "1";
		$address->save();
		// $all = Address::all();

		return response()->json(['status' => 'primary_address_set', 'message' => 'Primary Address Successfully Set' ], 200);
	}

}
