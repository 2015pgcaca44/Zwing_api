<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Http\Traits\V1\VendorFactoryTrait;
use Illuminate\Http\File;
use DB;
use Illuminate\Support\Facades\Crypt;
use App\Rating;
use App\PopUpCustomer;
use Auth;

class OfferController extends Controller
{
    use VendorFactoryTrait;

	public function __construct()
	{
		$this->middleware('auth');
	}
	
	
    public function popup_offer_viewed(Request $request){

        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $pop_up_id = $request->pop_up_id;

        $c_id = Auth::user()->c_id;

        $viewed = new PopUpCustomer;

        $viewed->v_id = $v_id;
        $viewed->store_id = $store_id;
        $viewed->pop_up_id = $pop_up_id;
        $viewed->c_id = $c_id;
        $viewed->viewed = '1';
        $viewed->save();

     return response()->json(['status' => 'success' , 'message' => 'Offer is viewed' ],200);

    }


    public function list(Request $request)
    {
        $offers[] = [ 'image'=> 'https://zwing.in/vendor/vendorstuff/store/offers/offers_1.png', 'offer_id' => 1 ];
        $offers[] = [ 'image'=> 'https://zwing.in/vendor/vendorstuff/store/offers/offers_1.png', 'offer_id' => 2 ];
        $offers[] = [ 'image'=> 'https://zwing.in/vendor/vendorstuff/store/offers/offers_1.png', 'offer_id' => 3 ];

        $data['slider'] = $offers;


        $data['offers'][] =  [ 'title' => 'Festive Special Offer' ,
                             'list' => [
                                            [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/cycling.png',
                                             'product_name' => 'Cycling',
                                             'offer' => 'Upto 30% off'
                                            ],
                                            [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/bag.png',
                                             'product_name' => 'Quechua',
                                             'offer' => 'Clearance Sale'
                                            ],
                                            [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/shoe.png',
                                             'product_name' => 'Running Shoes',
                                             'offer' => 'Upto 10% off'
                                            ]
                                        ]
                           ] ;

        $data['offers'][] =  [ 'title' => 'Top Deals' ,
                             'list' =>  [
                                            [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/softRing.png',
                                             'product_name' => 'Triboard Soft Ring',
                                             'offer' => 'Buy 1 Get 1'
                                            ],
                                            [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/bodybuilding.png',
                                             'product_name' => 'Dumbbell Set 20kg',
                                             'offer' => 'Flat 20% off'
                                            ],
                                             [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/jersey.jpg',
                                             'product_name' => 'Football Jerseys',
                                             'offer' => 'Now for RS 299'
                                            ]

                                        ]
                           ] ;
						   
						   
		
        $data['offers'][] =  [ 'title' => 'Festive Special Offer' ,
                             'list' => [
                                            [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/cycling.png',
                                             'product_name' => 'Cycling',
                                             'offer' => 'Upto 30% off'
                                            ],
                                            [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/bag.png',
                                             'product_name' => 'Quechua',
                                             'offer' => 'Clearance Sale'
                                            ],
                                            [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/shoe.png',
                                             'product_name' => 'Running Shoes',
                                             'offer' => 'Upto 10% off'
                                            ]
                                        ]
                           ] ;

        $data['offers'][] =  [ 'title' => 'Top Deals' ,
                             'list' =>  [
                                            [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/softRing.png',
                                             'product_name' => 'Triboard Soft Ring',
                                             'offer' => 'Buy 1 Get 1'
                                            ],
                                            [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/bodybuilding.png',
                                             'product_name' => 'Dumbbell Set 20kg',
                                             'offer' => 'Flat 20% off'
                                            ],
                                             [ 'image' => 'https://zwing.in/vendor/vendorstuff/store/offers/jersey.jpg',
                                             'product_name' => 'Football Jerseys',
                                             'offer' => 'Now for RS 299'
                                            ]

                                        ]
                           ] ;




        return response()->json(['status' => 'offer_list', 'message' => 'Offer List', 'data' => $data ],200);

    }


    public function get_offers(Request $request){
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    }


    public function apply_voucher(Request $request){
        
        return $this->callMethod($request, __CLASS__, __METHOD__ );

    }

    public function remove_voucher(Request $request){
        
        return $this->callMethod($request, __CLASS__, __METHOD__ );

    }
   

    
}
