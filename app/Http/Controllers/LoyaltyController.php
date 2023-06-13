<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Loyality\EaseMyRetailController;
use App\User;

class LoyaltyController extends Controller
{
	public function __construct()
	{
		$this->middleware('auth');
	}

	public function index($params)
	{
		$funcName = (string)$params['type'];
		return $this->$funcName($params);
	}

	public function easeMyRetail($params)
	{
		$easeMyRetail = new EaseMyRetailController($params);
		return $easeMyRetail;
	}

	public function getPoints(Request $request)
	{
		$url = "http://haagendazsPWW.erlpaas.com/RedeemPoints.aspx?RequestCode=b96901cc-c043-4d45-b288-16aaa57e32b9";
		$content = file_get_contents($url);
		$doc = new DOMDocument();
		$doc->loadHTML($page);
		dd($content);
		if($request->has('loyalty')) {
			$userInfo = User::find($request->c_id);
			$loyaltyPrams = [ 'type' => $request->loyaltyType, 'event' => 'getUrl', 'mobile' => $userInfo->mobile, 'vu_id' => $request->vu_id, 'settings' => $request->loyaltySettings, 'zw_event' => 'generateUrl', 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'user_id' => $request->c_id, 'order_id' => $request->order_id, 'billAmount' => $request->bill_amount ];
			$loyaltyCon = new LoyaltyController;
			$loyaltyUrl = $loyaltyCon->index($loyaltyPrams);
		}
	}
}
