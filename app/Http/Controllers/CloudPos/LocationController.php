<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Country;
use App\State;
use App\City;

class LocationController extends Controller
{
    public function getCountry(Request $request){

        if($request->has('country_id')){
            $country = Country::find($request->country_id);
        }else{
            $country = Country::all();
        }

    	return response()->json(['status'=> 'success' , 'data' => $country ], 200);

    }

    public function getState(Request $request){

    	if($request->has('country_id')){
            //$states = Country::find('country_id', $request->country_id)->states()->get();
    		$states = State::where('country_id', $request->country_id)->get();
    	}else if($request->has('state_id')){
            $states = State::find($request->state_id);
        }else{
    		$states = State::all();
    	}
    
    	return response()->json(['status'=> 'success' , 'data' => $states ], 200);

    }

    public function getCities(Request $request){

    	if($request->has('state_id')){
    		//$cities = State::find($request->state_id)->cities()->get();
            $cities = City::where('state_id', $request->state_id)->get();
    	}else{
    		$cities = City::all();
    	}
    	return response()->json(['status'=> 'success' , 'data' => $cities ], 200);

    }
}
