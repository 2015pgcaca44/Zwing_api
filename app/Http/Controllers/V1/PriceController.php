<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Vendor;
use DB;
use Auth;
use App\Order;
use App\Cart;
use App\TempCart;
use App\User;
use App\Store;

class PriceController extends Controller
{

	public function override(Request $request){

		# When any promo is applied price override should not happen
		# What when bill buster is applied is this is promo or not, need to confirm
		
		$v_id  = $request->v_id;
		$store_id  = $request->store_id;
		$cart_id  = $request->cart_id;
		$override_unit_price  = $request->override_unit_price;
		$override_reason  = $request->override_reason;
		$cart_id  = $request->cart_id;

		$vendor = Auth::user();

		$cart = TempCart::where('cart_id', $cart_id)->first();
		if($cart->status != 'process'){
			return response()->json(['status' => 'fail', 'message' => 'Cannot override this items' ], 200); 
		}
		$carts = TempCart::where('user_id', $cart->user_id)->where('v_id', $cart->v_id)->where('store_id', $cart->store_id)->where('order_id', $cart->order_id)->orderBy('updated_at','desc')->get();

		$total     = $carts->sum('total');
        $cart_qty_total = $carts->sum('qty');
        $c_id = $cart->user_id;

		$res = DB::table('temp_cart_offers')->where('cart_id',$cart->cart_id)->first();
        $offer_data = json_decode($res->offers, true);


        $store_db_name = get_store_db_name(['store_id' => $store_id]);
        $promo_c = new Spar\PromotionController(['store_db_name' => $store_db_name ]);
        ######################################
        ##### --- BILL BUSTER  START --- #####

        //Bill Buster Calculation
        $ru_prdv_data_bill_buster = $promo_c->get_rule_id('', 'billbuster');
        $filter_data_bill_buster = $promo_c->filterPromotionID($ru_prdv_data_bill_buster);
        //dd($filter_data_bill_buster);
        $bill_buster_dis =[];
		$push_data_bill=[];
        foreach ($filter_data_bill_buster as $key => $value) {
            $spilt = explode("-", $key);

            if ($spilt[0] == 'Buy$NorMoreGetZ$offTiered') {
                // echo 'BuyNOrMoreOfXGetatUnitPriceTiered<br>';
                $push_data_bill[$spilt[0]][$spilt[1]] = $value;
            }elseif ($spilt[0] == 'Buy$NorMoreGetZ%offTiered') {
                $push_data_bill[$spilt[0]][$spilt[1]] = $value;
            }elseif ($spilt[0] == 'BuyRsNOrMoreGetPrintedItemFreeTiered') {
                $push_data_bill[$spilt[0]][$spilt[1]] = $value;
            }
        }
        //dd($push_data_bill);
        //echo $total;exit;
        $barcode = '1000000000000';
        foreach ($push_data_bill as $key => $value) {
            if ($key == 'Buy$NorMoreGetZ$offTiered') {
                $response = $promo_c->shop_bill_get_amount_tiered($total, $cart_qty_total, $value, $barcode, $store_id, $c_id);
                if(!empty($response)){
                    $bill_buster_dis[$response['discount']] = $response;
                }
            }elseif($key == 'Buy$NorMoreGetZ%offTiered'){
                $response = $promo_c->shop_bill_get_percentage_tiered($total, $cart_qty_total, $value, $barcode, $store_id, $c_id);
                if(!empty($response)){
                    $bill_buster_dis[$response['discount']] = $response;
                }
            }elseif($key == 'BuyRsNOrMoreGetPrintedItemFreeTiered'){
                /*$response = $this->shop_bill_get_printed_tiered($total_amount, $qty, $value, $barcode, $store, $user_id);
                if(!empty($response)){
                    $bill_buster_dis[$response['discount']] = $response;
                }*/
            }
        }
       
        ##### --- BILL BUSTER  END --- #####
        ####################################

        if(count($offer_data['applied_offer']) == 0 && empty($bill_buster_dis)){
        	

	        $offer_data['unit_mrp'] = $cart->unit_mrp;
	        $offer_data['unit_csp'] = $cart->unit_csp;
	        $offer_data['override_unit_price'] = $override_unit_price;
	        $offer_data['override_flag'] = '1';

	        $total_tax = 0;
	        foreach ($offer_data['pdata'] as $key => $value) {
	        	//dd($value);
	        	$offer_data['pdata'][$key]['csp'] = $cart->unit_csp;
	        	$offer_data['pdata'][$key]['override_unit_price'] = $override_unit_price;
	        	$offer_data['pdata'][$key]['override_flag'] = '1';
	        	$offer_data['pdata'][$key]['ex_price'] =  $value['ex_price'] = $value['qty'] * $override_unit_price;

	            $taxes =[];
	            $tax_rates =$value['tax'];
	            foreach ($tax_rates as $tkey => $tax_rate) {
	                $taxable_amount = round ( ($value['ex_price'] / $value['qty']) * $tax_rate['taxable_factor'] , 2 );
	                $tax = round( ( $taxable_amount / 100  ) * $tax_rate['tax_rate'] , 2 );
	                $taxable_amount = $taxable_amount * $value['qty'];
	                $tax = $tax * $value['qty'];
	                $taxes[] = [ 'tax_category' => $tax_rate['tax_category'] , 'tax_desc' => $tax_rate['tax_desc'] , 'tax_code' => $tax_rate['tax_code'] , 'tax_rate' => $tax_rate['tax_rate'] , 'taxable_factor' => $tax_rate['taxable_factor'] , 'taxable_amount' => $taxable_amount , 'tax' => $tax ];
	                $total_tax += $tax;
	            }
	            $offer_data['pdata'][$key]['tax'] = $taxes;
	            
	        }

			$total = $cart->qty * $override_unit_price;

			$cart->override_unit_price = $override_unit_price;
			$cart->override_flag = '1';
			$cart->override_reason = $override_reason;
			$cart->override_by = $vendor->vu_id;
			$cart->discount = $cart->subtotal - $total;
			$cart->tax = $total_tax;
			$cart->total = $total;

			$cart->save();

			DB::table('temp_cart_offers')->where('id',$res->id)->update(['offers' => json_encode($offer_data)]);

			//Cart details
			$data = json_decode(json_encode($offer_data) );
			foreach ($data as $key => $val) {
				if ($key == 'pdata') {
					DB::table('temp_cart_details')->where('cart_id',$cart->cart_id)->delete();
                    foreach ($val as $key => $value) {
                        $cart_details = DB::table('temp_cart_details')->insert([
                            'cart_id' => $cart->cart_id,
                            'qty' => $value->qty,
                            'mrp' => $value->mrp,
                            'price' => $value->total_price,
                            'discount' => $value->discount,
                            'ext_price' => $value->ex_price,
                            'tax' => '',
                            'message' => $value->message,
                            'ru_prdv' => $value->ru_prdv,
                            'type' => $value->type,
                            'type_id' => $value->type_id,
                            'promo_id' => $value->promo_id,
                            'is_promo' => $value->is_promo,
                            'taxes' => isset($value->tax)?json_encode($value->tax):''
                        ]);
                    }
                }
            }

			//Mail should trigger in price override
			try{

				$mail_res = get_email_triggers(['v_id' => $v_id ,'store_id' => $store_id , 'email_trigger_code' => 'price_override']);
				$to = $mail_res['to'];
				$cc = $mail_res['cc'];
				$bcc = $mail_res['bcc'];

	            $store = Store::where('store_id', $store_id)->select('mapping_store_id')->first();
            
            	$msg = 'Price override has been done in Store ID: '.$store->mapping_store_id. ' By : '.$vendor->first_name.' '.$vendor->last_name.' for Ittem :'.$cart->item_name;
				Mail::raw( $msg, function ($message) use($to,$cc,$bcc) {
				  $message = $message->to($to);
				  if(count($cc)> 0){
				  	//dd($cc);
				  	$message = $message->cc($cc);
				  }
				  if(count($bcc)> 0){
				  	$message = $message->bcc($bcc);
				  }
				  $message->subject('Price override');
				});

            }catch(Exception $e){
            	//echo $e->message();
            }

            

                    //dd($cc);
					
                    //Mail::to($user->email)->cc($cc)->bcc($bcc)->send(new OrderCreated($user,$ord,$carts,$payment_method,  $complete_path));
			
			// Tax calculation need to done
			
			
			// When any promo is applied  

			return response()->json(['status' => 'success', 'message' => 'Price override done successfully' ], 200);
		}else{
			return response()->json(['status' => 'fail', 'message' => 'Cannot override price because PROMO is applied on item' ], 200);
		}
	}
}