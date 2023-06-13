<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\MapLocation;
use Auth;

class MapLocationController extends Controller
{
    public function __construct()
	{
		$this->middleware('auth');
	}

	public function store(Request $request)
	{
		/*$mapLocation = new MapLocation;
		$maplocation->latitude = $lat;
		$maplocation->longitude = $long;
		$maplocation->address = $respones->results[1]->formatted_address;
		$maplocation->google_response = $jsonResponse;
		$mapLocation->save();

		return response()->json(['status' => 'save_store_rating', 'message' => 'Store Rating Save Successfully' ],200);*/
	}

	public function addressBylatLong(Request $request)
	{
		$latitude = $request->latitude;
		$longitude = $request->longitude;

		$address = MapLocation::select('address','locality')->where('latitude' , $latitude)->where('longitude' , $longitude)->first();


		if(empty($address) ){
			$response =  $this->getAddressFromApi($latitude , $longitude);
			$address['address'] =  $response['address'];
			$address['locality'] =  $response['locality'];
		}

		return response()->json(['status' => 'addressBylatLong', 'data' =>$address  ],200);
	}

	public function addressBylatLongArray($lat , $long){

		$latitude = $lat;
		$longitude = $long;

		$address = MapLocation::select('address','locality')->where('latitude' , $latitude)->where('longitude' , $longitude)->first();



		if(empty($address) ){
			$response =  $this->getAddressFromApi($latitude , $longitude);
			$data['address'] =  $response['address'];
			$data['locality'] =  $response['locality'];
		}else{

			$data['address'] =  $address->address;
			$data['locality'] =  $address->locality;
		}

		return ['status' => 'addressBylatLong', 'data' =>$data  ];
	}

	public function getAddressFromApi($lat, $long)
	{
		$url = 'https://maps.googleapis.com/maps/api/geocode/json?latlng='.$lat.','.$long.'&sensor=true&key=AIzaSyDP7fe-gxIoFP8XIqYYKpHjRP5Whk6ELYg';

		$jsonResponse = file_get_contents($url);
		$response = json_decode($jsonResponse);

		/*echo '<pre>';
		print_r($response);exit;*/


		if($response->status == 'OK'){
			$locality = '';

			foreach( $response->results[1]->address_components as $address )
			{

				if(in_array('locality' ,$address->types))
				{
					$locality = $address->long_name;
					break;
				}
			}

			$mapLocation = new MapLocation;
			$mapLocation->latitude = $lat;
			$mapLocation->longitude = $long;
			$mapLocation->address = $response->results[1]->formatted_address;
			$mapLocation->locality = $locality;
			$mapLocation->google_response = json_encode($response);
			$mapLocation->save();

			$response = ['address' => $response->results[1]->formatted_address , 'locality' => $locality ];

		}else{
			$response = ['address' => 'Null' , 'locality' => 'Null' ];

		}

		return $response;
		

	}
}
