<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Http\Traits\V1\VendorFactoryTrait;
use App\Order;
use App\Cart;
use App\Address;
use App\PartnerOffer;
use App\PartnerOfferUsed;
use DB;
use App\Payment;
use Endroid\QrCode\QrCode;
use App\Wishlist;
use Auth;
use Razorpay\Api\Api;

class CartController extends Controller
{
    use VendorFactoryTrait;

    public function __construct()
	{
		$this->middleware('auth',   ['except' => ['order_receipt']]);
	}

	public function add_to_cart(Request $request)
    {
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    }

    public function bulk_add_to_cart(Request $request)
    {
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    }

    public function product_qty_update(Request $request)
    {
        return $this->callMethod($request, __CLASS__, __METHOD__ );
        
    }

    public function remove_product(Request $request)
    {
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    }

    public function cart_details(Request $request)
    {
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    }

    public function process_to_payment(Request $request)
    {
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    }

    public function payment_details(Request $request)
    {
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    }

    public function order_qr_code(Request $request)
    {
        $order_id = $request->order_id;
        $qrCode = new QrCode($order_id);  

        if( $request->has('go_to')){
            if($request->go_to == 'pos_checkout'){
                $order = DB::table('orders')->where('order_id', $order_id)->select('v_id','store_id','user_id','o_id')->first();
                $carts = DB::table('cart')->where('v_id',$order->v_id)->where('store_id', $order->store_id)->where('user_id', $order->user_id)->where('order_id', $order->o_id)->select('qty','barcode')->get();
                $items = '';
                foreach ($carts as $key => $value) {
                    $temp_qty = $value->qty;
                    while ( $temp_qty > 0) {
                        $items .= $value->barcode.PHP_EOL;
                        $temp_qty--;
                    }
                    
                }
                //$cart_items = $carts->pluck('qty' , 'barcode')->all();
                $cart_items = json_encode($items);
                $qrCode = new QrCode($cart_items);
            }
        }

        header('Content-Type: image/png');
        echo $qrCode->writeString();
    }

    public function order_pre_verify_guide(Request $request){

        return $this->callMethod($request, __CLASS__, __METHOD__ );

    }

    
    public function order_details(Request $request)
    {
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    }

    public function order_receipt($c_id,$v_id , $store_id, $order_id){

        if($v_id == 4){
           
            $cartC = new  Spar\CartController;
            $response = $cartC->order_receipt($c_id, $v_id , $store_id, $order_id);
           // return $response;
            echo $response;

        }else if($v_id == 26){
           
            $cartC = new  Zwing\CartController;
            $response = $cartC->order_receipt($c_id, $v_id , $store_id, $order_id);
           // return $response;
            echo $response;

        }else if($v_id == 28){
           
            $cartC = new  Dmart\CartController;
            $response = $cartC->order_receipt($c_id, $v_id , $store_id, $order_id);
           // return $response;
            echo $response;

        }else if($v_id == 30){
           
            $cartC = new  Hero\CartController;
            $response = $cartC->order_receipt($c_id, $v_id , $store_id, $order_id);
           // return $response;
            echo $response;

        }else if($v_id == 34){
           
            $cartC = new  Metro\CartController;
            $response = $cartC->order_receipt($c_id, $v_id , $store_id, $order_id);
           // return $response;
            echo $response;

        }
        
    }


    public function get_carry_bags(Request $request)
    {
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    }

    public function save_carry_bags(Request $request)
    {
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    }

    public function deliveryStatus(Request $request)
    {
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    }

    public function get_print_receipt(Request $request)
    {
        return $this->callMethod($request, __CLASS__, __METHOD__ );
    }

}