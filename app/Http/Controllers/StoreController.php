<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use Illuminate\Support\Facades\Crypt;
use App\Rating;
use App\PopUp;
use App\PopUpCustomer;
use App\Order;
use App\Cart;
use App\Store;
use Endroid\QrCode\QrCode;
use Auth;

class StoreController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function store_search_list(Request $request)
    {

        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $search_term = $request->search_term;
        $stores  = DB::table('stores')
            ->select(DB::raw('store_id, v_id, type, name, location,  ( 6371 * acos ( cos ( radians(' . $latitude . ') ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(' . $longitude . ') ) + sin ( radians(' . $latitude . ') ) * sin( radians( latitude ) ) ) ) AS customer_distance'))
            ->having('customer_distance', '<', 300)
            ->orderBy('customer_distance', 'asc')
            ->where('api_status', 1)
            ->where('name', 'like', '%' . $search_term . '%')
            ->limit(10)->get();



        return response()->json(['status' => 'success', 'data' => $stores], 200);
    }

    public function store_list(Request $request)
    {
        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $trans_from = 'ANDROID';
        if ($request->has('trans_from')) {
            $trans_from = $request->trans_from;
        }

        $stores  = DB::table('stores')
            ->select(DB::raw('store_id, v_id, type, name, address1, address2, latitude, longitude, opening_time, closing_time, description, tagline, store_logo,store_icon, store_list_bg,is_restaurant, location,  ( 6371 * acos ( cos ( radians(' . $latitude . ') ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(' . $longitude . ') ) + sin ( radians(' . $latitude . ') ) * sin( radians( latitude ) ) ) ) AS customer_distance'))
            ->having('customer_distance', '<', 300)
            ->orderBy('customer_distance', 'asc')
            ->where('api_status', 1)
            ->where('display_status', 'like', '%:' . $trans_from . ':%');
        if ($request->has('store_id')) {
            $stores  = $stores->where('store_id', $request->store_id);
        }

        if ($request->has('search_term')) {
            $stores  = $stores->where('name', 'like', '%' . $request->search_term . '%');
        }

        $stores  = $stores->limit(50)->get()->toArray();
        $data = array();
        $storeLanding = false;
        foreach ($stores as $key => $store) {
            if ($store->customer_distance <= 0.8) {
                $storeLanding = true;
            }
            $opening_time = date('h A', strtotime($store->opening_time));
            $closing_time = date('h A', strtotime($store->closing_time));
            date_default_timezone_set("Asia/Kolkata");
            $nowDate = date("Y-m-d h:i:sa");
            $start = date('H:i:s', strtotime($store->opening_time));
            $end   = date('H:i:s', strtotime($store->closing_time));
            $time = date("H:i:s");
            $flag = isWithInTime($start, $end, $time);
            $rating_exits = Rating::where('Store_ID', $store->store_id)->where('V_ID', $store->v_id)->where('User_ID', Auth::user()->c_id)->count();
            if (empty($rating_exits)) {
                $rating = 'No';
            } else {
                $rating = 'Yes';
            }

            $mgPath = store_logo_link() . $store->store_logo;
            $store_logo_flag = false;
            if (@getimagesize($mgPath)) {
                $store_logo_flag = true;
            }

            $vendorS = new VendorSettingController;
            $settings = $vendorS->getSetting($store->v_id, 'color');
            if ($settings) {

                $settings = $settings->first()->settings;
                $colorSettings = json_decode($settings);

                $colorSettings;
            } else {

                $colorSettings = json_decode('{"color_top":{"r":6,"g":80,"b":133,"hex":"#065085"},"color_bottom":{"r":28,"g":116,"b":180, "hex":"#1C74B4"}}');
            }

            $isNavigateStore = 'NO';
            $distance = 0.00;
            $vendorS = new VendorSettingController;
            $settings = $vendorS->getSetting($store->v_id, 'store');
            if ($settings) {
                $settings = $settings->first()->settings;
                $storeSettings = json_decode($settings, true);
                //echo '<pre>';print_r($storeSettings);exit;
                $radius = $storeSettings['enable_shopping_radius'];
                if ($radius['status'] == 1) {

                    if ($radius['apply_type'] == 'store_wise') {
                        //echo '<pre>';print_r($radius->store_wise->);exit;
                        if (isset($radius['store_wise'][$store->store_id])) {
                            $distance = $radius['store_wise'][$store->store_id]['radius'];
                        } else {
                            //$distance = $radius->default_radius;
                        }
                    } else if ($radius->apply_type == 'vendor_wise') {

                        $distance = $radius->default_radius;
                    }
                }
            }

            if ($distance > 0.00) {
                $isNavigateStore = 'YES';
            }

            $data[] = array(
                'isNavigateToStoreDetailFromServer' => $isNavigateStore,
                'store_id' => $store->store_id,
                'v_id' => $store->v_id,
                'type' => $store->type,
                'name' => $store->name,
                'address1' => $store->address1 . ' ' . $store->address2,
                'address2' => $store->address2,
                'latitude' => $store->latitude,
                'longitude' => $store->longitude,
                'opening_time' => $opening_time,
                'closing_time' => $closing_time,
                'description' => $store->description,
                'tagline' => $store->tagline,
                'store_logo_flag' => $store_logo_flag,
                'store_logo' => $store->store_logo,
                'store_icon' => $store->store_icon,
                'store_list_bg' => $store->store_list_bg,
                'location' => $store->location,
                'customer_distance' => $store->customer_distance,
                'store_status' => $flag,
                'user_rating' => $rating,
                'is_restaurant' => $store->is_restaurant,
                'rating' => store_rating($store->store_id, $store->v_id),
                'color' => $colorSettings
            );
        }

        $order = Order::select('order_id', 'v_id', 'store_id', 'date', 'time', 'total', 'verify_status', 'verify_status_guard')->where('user_id', $request->c_id)->where('status', 'success')->where('transaction_type', 'sales')->orderBy('od_id', 'desc')->first();

        if ($order) {

            $vendorS = new VendorSettingController;
            $settings = $vendorS->getSetting($order->v_id, 'color');
            if ($settings) {

                $settings = $settings->first()->settings;
                $colorSettings = json_decode($settings);

                $colorSettings;
            } else {

                $colorSettings = json_decode('{"color_top":{"r":6,"g":80,"b":133,"hex":"#065085"},"color_bottom":{"r":28,"g":116,"b":180, "hex":"#1C74B4"}}');
            }
        } else {

            $colorSettings = json_decode('{"color_top":{"r":6,"g":80,"b":133,"hex":"#065085"},"color_bottom":{"r":28,"g":116,"b":180, "hex":"#1C74B4"}}');
        }

        if ($order) {

            $verification_data = [
                'cashier_verify_status' => ($order->verify_status == '1') ? true : false,
                'guard_verify_status' => ($order->verify_status_guard == '1') ? true : false,
                'order_id' => $order->order_id,
                'amount' => $order->total,
                'v_id' => $order->v_id,
                'store_id' => $order->store_id,
                'date' => $order->date,
                'time' => $order->time,
                'color' => $colorSettings
            ];
        } else {

            $verification_data = [
                'cashier_verify_status' => true,
                'guard_verify_status' => true,
                'order_id' => '',
                'amount' => '',
                'v_id' => 0,
                'store_id' => 0,
                'date' => '',
                'time' => '',
                'color' => (object) []
            ];
        }


        return response()->json([
            'store_landing' => $storeLanding, 'status' => 'store_list', 'message' => 'Store List Data', 'data' => $data, 'store_logo_link' => store_logo_link(),
            'verification' => $verification_data
        ], 200);
    }

    public function store_details(Request $request)
    {
        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $trans_from = $request->trans_from;

        $store = DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();
        $data = array();
        $opening_time = date('h A', strtotime($store->opening_time));
        $closing_time = date('h A', strtotime($store->closing_time));
        date_default_timezone_set("Asia/Kolkata");
        $nowDate = date("Y-m-d h:i:sa");
        $start = date('H:i:s', strtotime($store->opening_time));
        $end   = date('H:i:s', strtotime($store->closing_time));
        $time = date("H:i:s");
        $flag = isWithInTime($start, $end, $time);
        $rating_exits = Rating::where('Store_ID', $store->store_id)->where('V_ID', $store->v_id)->where('User_ID', Auth::user()->c_id)->count();
        if (empty($rating_exits)) {
            $rating = 'No';
        } else {
            $rating = 'Yes';
        }
        $mgPath = store_logo_link() . $store->store_logo;
        $store_logo_flag = false;
        if (@getimagesize($mgPath)) {
            $store_logo_flag = true;
        }



        $vendorS = new VendorSettingController;
        $settings = $vendorS->getSetting($store->v_id, 'color');
        if ($settings) {

            $settings = $settings->first()->settings;
            $colorSettings = json_decode($settings);

            $colorSettings;
        } else {

            $colorSettings = json_decode('{"color_top":{"r":6,"g":80,"b":133,"hex":"#065085"},"color_bottom":{"r":28,"g":116,"b":180, "hex":"#1C74B4"}}');
        }


        $vendorS = new VendorSettingController;
        $settings = $vendorS->getSetting($store->v_id, 'others');
        if ($settings) {
            $settings = $settings->first()->settings;
            $otherSettings = json_decode($settings);

            $otherSettings;
        } else {

            $otherSettings = json_decode('{"offer_detail_flag":"YES","locate_product_flag":"YES"}');
        }

        Cart::where('store_id', '!=', $store_id)->where('status', 'process')->delete();

        //$vendorS = new VendorController;
        $request->request->add(['v_id' => $v_id, 'response_format' => 'ARRAY']);
        $sSetting =  $this->get_settings($request);

        $data['store_id'] = $store->store_id;
        $data['store_random'] = $store->store_random;
        $data['v_id'] = $store->v_id;
        $data['type'] = $store->type;
        $data['name'] = $store->name;
        $data['email'] = $store->email;
        $data['pincode'] = $store->pincode;
        $data['address1'] = $store->address1;
        $data['address2'] = $store->address2;
        $data['state'] = $store->state;
        $data['city'] = $store->city;
        $data['latitude'] = $store->latitude;
        $data['longitude'] = $store->longitude;
        $data['opening_time'] = $opening_time;
        $data['closing_time'] = $closing_time;
        $data['weekly_off'] = $store->weekly_off;
        $data['description'] = $store->description;
        $data['tagline'] = $store->tagline;
        $data['contact_person'] = $store->contact_person;
        $data['contact_number'] = $store->contact_number;
        $data['contact_designation'] = $store->contact_designation;
        $data['store_logo_flag'] = $store_logo_flag;
        $data['store_details_img'] = $store->store_details_img;
        $data['restaurant_bg'] = $store->restaurant_bg;
        $data['store_logo'] = $store->store_logo;
        $data['store_icon'] = $store->store_icon;
        $data['location'] = $store->location;
        $data['delivery'] = $store->delivery;
        $data['store_status'] = $flag;
        $data['day'] = date('D');
        $data['max_qty'] = (string) $store->max_qty;
        $data['user_rating'] = $rating;
        $data['rating'] = store_rating($store->store_id, $store->v_id);
        $data['color'] = $colorSettings;
        $data['offer_detail_flag'] = $otherSettings->offer_detail_flag;
        $data['locate_product_flag'] = $otherSettings->locate_product_flag;
        $data['settings'] = $sSetting['settings'];


        $popUp = PopUp::where('v_id', $v_id)->where('store_id', $store_id)->where('status', '1')->first();
        if ($popUp) {

            $popUpCustomer = PopUpCustomer::where('v_id', $v_id)->where('store_id', $store_id)->where('pop_up_id', $popUp->id)->where('c_id', Auth::user()->c_id)->first();

            if ($popUpCustomer) {
                $pop = ['flag' => 'NO'];
            } else {
                $pop = ['flag' => 'YES', 'offer_title' => $popUp->offer_title,  'offer_description' => $popUp->offer_description, 'pop_up_id' =>  $popUp->id, 'store_icon' =>  store_logo_link() . $store->store_icon, 'tc' => 'https://zwing.in/faq/'];
            }
            $data['pop_up'] =  $pop;
        } else {
            $data['pop_up'] =  ['flag' => 'NO'];;
        }

        if ($v_id == 3) {


            $data['store_navigation_img'] = 'vmart_store_nav_img.png';
            $data['store_scango_img'] = 'vmart_scango_bg_img.png';
            $data['offer_font_color'] = '#555555';

            $offers_grid[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/vmart_grid_1'];
            $offers_grid[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/vmart_grid_2'];


            $offers[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/vmart_grid_1.png', 'offer_id' => 1];
            $offers[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/vmart_grid_2.png', 'offer_id' => 2];
            $offers[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/offers_1.png', 'offer_id' => 3];
        } else if ($v_id == 16) {
            $data['store_navigation_img'] = 'spar_store_nav_img.png';
            $data['store_scango_img'] = 'spar_scango_bg_img.png';
            $data['offer_font_color'] = '#b2212d';

            $offers_grid[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/decath_grid_1'];
            $offers_grid[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/decath_grid_2'];


            $offers[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/spar_grid_1.png', 'offer_id' => 1];
            $offers[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/spar_grid_2.png', 'offer_id' => 2];
            $offers[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/offers_1.png', 'offer_id' => 3];
        } else if ($v_id == 17) {
            $data['store_navigation_img'] = 'spar_store_nav_img.png';
            $data['store_scango_img'] = 'spar_scango_bg_img.png';
            $data['offer_font_color'] = '#b2212d';

            $offers_grid[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/star_market_grid_1.png'];
            $offers_grid[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/star_market_grid_2.png'];


            $offers[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/star_market_grid_1.png', 'offer_id' => 1];
            $offers[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/star_market_grid_2.png', 'offer_id' => 2];
            $offers[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/offers_1.png', 'offer_id' => 3];
        } else if ($store->store_id == 20) {

            $data['store_navigation_img'] = 'decath_store_nav_img.png';
            $data['store_scango_img'] = 'decath_scango_bg_img.png';
            $data['offer_font_color'] = '#065086';

            $offers_grid[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/decath_20_grid_1.png'];
            $offers_grid[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/decath_20_grid_2.png'];


            $offers[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/decath_20_grid_1.png', 'offer_id' => 1];
            $offers[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/decath_20_grid_2.png', 'offer_id' => 2];
            $offers[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/decath_20_offer_1.png', 'offer_id' => 3];
        } else {


            $data['store_navigation_img'] = 'decath_store_nav_img.png';
            $data['store_scango_img'] = 'decath_scango_bg_img.png';
            $data['offer_font_color'] = '#065086';

            $offers_grid[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/decath_grid_1'];
            $offers_grid[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/decath_grid_2'];


            $offers[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/decath_grid_1.png', 'offer_id' => 1];
            $offers[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/decath_grid_2.png', 'offer_id' => 2];
            $offers[] = ['image' => 'http://zwing.in/vendor/vendorstuff/store/offers/decath_20_offer_1.png', 'offer_id' => 3];
        }

        $data['offers'] = $offers;
        $data['offer_grid'] = $offers_grid;

        $order = Order::where('v_id', $v_id)->where('store_id', $store_id)->where('user_id', $request->c_id)->where('status', 'success')->orderBy('od_id', 'desc')->first();
        $order_verify_status = true;
        $guard_verify_status = true;
        if ($order) {
            $order_verify_status = ($order->verify_status == 1) ? true : false;
            $guard_verify_status = ($order->verify_status_guard == 1) ? true : false;
        }

        return response()->json([
            'status' => 'store_details', 'message' => 'Store Profile Details', 'data' => $data, 'store_logo_link' => store_logo_link(),
            'store_verify_status' => $order_verify_status,
            'guard_verify_status' => $guard_verify_status
        ], 200);
    }

    public function get_settings(Request $request)
    {

        $v_id = $request->v_id;
        $trans_from = $request->trans_from;
        //$vu_id = $request->vu_id;

        $response_format = 'JSON';
        if ($request->has('response_format')) {
            $response_format =  $request->response_format;
        }

        $vendorS = new VendorSettingController;
        $colorSettings = $vendorS->getColorSetting(['v_id' => $v_id]);
        //$vendorApp = $vendorS->getVendorAppSetting(['v_id' => $v_id]);
        $toolbar = $vendorS->getToolbarSetting(['v_id' => $v_id]);

        $paymentTypeSettings = $vendorS->getPaymentTypeSetting(['v_id' => $v_id, 'trans_from' => $trans_from]);

        $feedback = $vendorS->getFeedbackSetting(['v_id' => $v_id, 'trans_from' => $trans_from]);
        $invoice = $vendorS->getInvoiceSetting(['v_id' => $v_id, 'trans_from' => $trans_from]);
        $print = $vendorS->getPrintSetting(['v_id' => $v_id, 'trans_from' => $trans_from]);
        $barcode = $vendorS->getBarcodeSetting(['v_id' => $v_id, 'trans_from' => $trans_from]);
        $optimize_flow = $vendorS->getOptimizeFlowSetting(['v_id' => $v_id, 'trans_from' => $trans_from]);

        $cart_avail_offer = $vendorS->getCartAvailSetting(['v_id' => $v_id, 'trans_from' => $trans_from]);

        $store =  DB::table('stores')->select(DB::raw('store_logo,store_icon,store_list_bg,location,is_restaurant,restaurant_bg'))->where('api_status', 1)->where('status', 1)->where('v_id', $v_id)->first();

        $mgPath = store_logo_link() . $store->store_logo;
        $store_logo_flag = false;
        if (@getimagesize($mgPath)) {
            $store_logo_flag = true;
        }

        //$vendorSett = new VendorSettlementController;
        if ($store->is_restaurant == 1) {
            $storearr = [
                'store_logo_flag' => $store_logo_flag,
                'restaurant' => $store->restaurant_bg,
                'store_logo' => $store->store_logo,
                'store_icon' => $store->store_icon,
                'store_list_bg' => $store->store_list_bg,
                'location' => $store->location,
                'store_logo_link' => store_logo_link()
            ];
        } else {
            $storearr = [
                'store_logo_flag' => $store_logo_flag,
                'store_logo' => $store->store_logo,
                'store_icon' => $store->store_icon,
                'store_list_bg' => $store->store_list_bg,
                'location' => $store->location,
                'store_logo_link' => store_logo_link()
            ];
        }

        $responseData = [
            'color' =>  $colorSettings,
            'payment_type' => $paymentTypeSettings,
            //'vendor_app_menu' => $vendorApp,
            'store' => $storearr,
            'toolbar' => $toolbar,
            'feedback' => $feedback,
            'print' => $print,
            'invoice' => $invoice,
            'barcode' => $barcode,
            //'opening_balance_status' => $vendorSett->opening_balance_flag($request),
            'optimize_flow' => $optimize_flow,
            'cart_avail_offer' => $cart_avail_offer

        ];

        if ($response_format == 'ARRAY') {

            return ['settings' => $responseData];
        } else {
            return response()->json(['settings' => $responseData, 'status' => 'success'], 200);
        }
    }

    public function store_qr_code(Request $request)
    {
        $store_id = $request->store_id;
        $v_id     = $request->v_id;
        $store  = Store::where('v_id', $v_id)->where('store_id', $store_id)->first();


        if (@$store->store_random == '') {
            $rand       = mt_rand(0, 99999999);
            // $storeU   = Store::find($store->store_id);
            $store->store_random = $rand;
            $store->save();
            $store_random = base64_encode($store->store_random);
        } else {
            $store_random = base64_encode($store->store_random);
        }

        $qrCode = new QrCode($store_random);
        header('Content-Type: image/png');
        echo $qrCode->writeString();
    }
}
