<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Http\File;
use DB;
use Illuminate\Support\Facades\Crypt;
use App\PartnerOffer;
use App\Order;
use App\PartnerOfferUsed;
use Auth;

class PartnerOfferController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }

    
    public function available_offer(Request $request){

        $c_id  = $request->c_id;

        $current_date =  date('Y-m-d');

        $offers =  PartnerOffer::where('start_at' ,'<=' , $current_date)
                    ->where('end_at' ,'>=' , $current_date)
                    ->where('status','1')
                    ->get();


        if($offers->isEmpty()){

            return response()->json(['status' => 'fail', 'message' => 'No offer available'],200);
        }else{

            $offersArry = [];
            foreach($offers as $offer){
                $offer_for_you_flag = false;
                if($offer->offer_for != ''){
                    $offer_for_you = explode(',', $offer->offer_for);
                    if(in_array($c_id, $offer_for_you)){
                        $offer_for_you_flag = true;
                    }
                }
				
				$offerUsed = PartnerOfferUsed::where('user_id',$c_id)->where('partner_offer_id',$offer->id)->first();
				$offerUsedFlag = false;
				if($offerUsed){
					$offerUsedFlag = true;
				}

                $off['offer_id'] = $offer->id;
                $off['partner_id'] = $offer->partner_id;
                $off['code'] = $offer->code;
                $off['name'] = $offer->name;
                $off['description'] = $offer->description;
                $off['type'] = $offer->type;
                $off['value'] = $offer->value;
                $off['offer_for_you'] = $offer_for_you_flag;
				$off['offer_used_flag'] = $offerUsedFlag;

                $offersArry[] = $off;
            }

            return response()->json(['status' => 'offer_list', 'message' => 'Offer List', 'data' => $offersArry ],200);
        }
    }


    public function apply(Request $request){
        
        $c_id  = $request->c_id;
        $order_id  = $request->order_id;
		$amount  = $request->amount;
        
        $current_date =  date('Y-m-d');
        $offers =  PartnerOffer::where('start_at' ,'<=' , $current_date)
                    ->where('end_at' ,'>=' , $current_date)
                    ->where('status','1');
        
        if($request->has('offer_id') || $request->has('code') ){
            if($request->has('offer_id')){
                $offer_id = $request->offer_id;

                $offers =  $offers->where('id' , $offer_id);
            }

            if($request->has('code')){
                $code = $request->code;
                $offers =  $offers->where('code' , $code);
            }
        }else{
            return response()->json(['status' => 'fail', 'message' => 'You have not choosen any offer to apply' ],200);
        }


        $offers = $offers->first();

        $offerMsg = '';
        $offeredAmount = 0;
        if($offers){


            $offerUsed = PartnerOfferUsed::where('user_id',$c_id)->where('partner_offer_id',$offers->id)->first();

            if($offerUsed){
                return response()->json(['status' => 'fail', 'message' => 'You have already used this offer' ],200);
            }else{

                /*$order = Order::where('o_id',$order_id)->where('user_id', $c_id)->first();
                $order->partner_offer_id = $offers->id;
                $order->save();*/


                if($offers->type == 'PRICE'){

                    $offerMsg = "Get Cash Back Upto $offers->value ";
                    $offeredAmount = $offers->value;
                }else if($offers->type == 'PERCENTAGE'){
                    $offerMsg = "Get Upto $offers->value % Discount max upto $offers->max";
                    
                    $offeredAmount = ($amount  * $offers->value) / 100;
                    if( $offers->max != 0  && $offeredAmount >= $offers->max){
                        $offeredAmount = $offers->max;
                    }
                    
                }

                $offerUsed = new PartnerOfferUsed;
                $offerUsed->user_id = $c_id;
                $offerUsed->order_id = $order_id;
                $offerUsed->partner_offer_id = $offers->id;

                $offerUsed->offered_amount = $offeredAmount ;
                $offerUsed->save();

                return response()->json(['status' => 'success', 'message' => $offerMsg, 'offer_used_id' => $offerUsed->id ],200);

            }




        }else{
            return response()->json(['status' => 'fail', 'message' => 'Unable to find any offers' ],200);

        }



    }


    public function remove(Request $request){

        $offer_used_id =  $request->offer_used_id ;
        //$order_id =  $request->order_id ;
        $c_id =  $request->c_id ;
        $offerUsed = PartnerOfferUsed::where('id' , $offer_used_id)->delete();

        /*$order = Order::where('o_id',$order_id)->where('user_id', $c_id)->first();
        $order->partner_offer_id = 0;
        $order->save();*/


        return response()->json(['status' => 'success', 'message' => 'Offers has been removed successfully' ],200);
    }


   

    
}
