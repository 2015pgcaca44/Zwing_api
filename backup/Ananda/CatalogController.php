<?php

namespace App\Http\Controllers\Ananda;

use App\Http\Controllers\Controller;
use App\Http\Controllers\V1\VendorSettingController;
use Illuminate\Http\Request;
use DB;

use App\Cart;
use App\Order;

use App\Vendor\VendorRoleUserMapping;
use App\Organisation;

class CatalogController extends Controller
{

    public function __construct()
	{
		//$this->middleware('auth');
	}

	public function getCatalog(Request $request){

		$v_id = $request->v_id;
        $store_id = $request->store_id; 
        $c_id = $request->c_id;

        $stores =  DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();
        $store_db_name = $stores->store_db_name;

		/*$item_master = DB::table($store_db_name.'.item_master as im')
						->join($store_db_name.'.price_master as pm', 'pm.ITEM' ,'im.EAN')
						->join($store_db_name.'.price_master_details as pmd', 'pmd.ITEM', 'pm.ITEM')
						->select('pmd.ITEM','pmd.ITEM_DESC','pmd.IMAGE','pm.MRP1','pmd.CATEGORY')
						->where('pmd.IS_CATALOG','1')->get();*/


        $item_master = DB::table($store_db_name.'.invitem as im')
                        ->join($store_db_name.'.invgrp as grp','grp.GRPCODE','im.GRPCODE')
                        ->join($store_db_name.'.invstock_onhand as hnd','hnd.ICODE','im.ICODE')
                        ->select('im.ICODE as ITEM', DB::raw('CONCAT(im.CNAME1, " ", im.CNAME3) as ITEM_DESC'),'im.MRP as MRP1','grp.GRPNAME as CATEGORY')
                        ->where('hnd.ADMSITE_CODE',$stores->mapping_store_id)
                        ->where('hnd.QTY','<>',0)
                        ->where('im.MRP','<>',0.000)
                        ->where('im.EXT', 'N')
                        ->groupBy('im.ICODE')
                         ->orderBy('grp.LEV','ASC')
                         ->orderByRaw("CASE WHEN  grp.LEV2GRPNAME = 'MILK' THEN 0 ELSE 1 END ASC")
                         ->orderByRaw("CASE WHEN  grp.LEV2GRPNAME = 'DAHI' THEN 0 ELSE 1 END ASC")
                         ->orderBy('grp.LEV2GRPNAME','ASC')
                         ->orderBy('hnd.QTY','DESC')
                         //->orderByRaw( FIELD(priority, 'grp.LEV2GRPNAME', 'grp.LEV', 'hnd.QTY'))
                        ->get();

       
		$carts = Cart::where('user_id', $c_id)->where('v_id', $v_id)->where('store_id', $store_id)->where('status','process')->orderBy('updated_at','desc')->get();

		$total = $carts->sum('total');
		$cart_qty_total = $carts->sum('qty');
		$cartItems = $carts->pluck('qty','item_id')->all();;

		$items = $item_master->pluck('');
		$data = [];
		foreach($item_master as $product){
			
			$qty = 0;
			$cart_id = '';
			if(isset($cartItems[$product->ITEM])){
				$qty = $cartItems[$product->ITEM];
				$cart = $carts->where('item_id', $product->ITEM)->first();
				$cart_id = $cart->cart_id;
			}
			$data[$product->CATEGORY][] = [ 'item_name' => $product->ITEM_DESC ,
						

                        'images' => 'ananda_list.png',
						

                        'unit_mrp' => $product->MRP1,
						'category' => 'Ananda',
						'qty' => (string)$qty,
						'barcode' => $product->ITEM,
						'cart_id' => $cart_id
					 ];
		}

		$response = [];
		foreach($data as $key =>  $d){
			$response[] = [ 'title' => $key , 'data' => $d ]; 
		}

        $roundoff_total = round($total);

		return response()->json(['status' => 'success' , 'catalog_data' => $response , 'product_image_link' => product_image_link().$v_id.'/', 'total' => (string)format_number($total) , 'cart_qty_total' => (string)$cart_qty_total ],200);

	}

	public function saveCatalog(Request $request)
    {
    	
        //echo 'inside this';exit;
        $v_id = $request->v_id;
        $store_id = $request->store_id; 
        $c_id = $request->c_id; 
        //$order_id = $request->order_id; 
        $catalogs = $request->catalogs; 
	    $catalogs = json_decode($catalogs, true);
        // dd($catalogs);
        $stores =  DB::table('stores')->where('v_id', $v_id)->where('store_id', $store_id)->first();
        $store_db_name = $stores->store_db_name;

        //$store_db_name = get_store_db_name(['store_id' => $store_id]);


        $order_id = Order::where('user_id', $c_id)->where('status', 'success')->count();
        $order_id = $order_id + 1;

        $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

        //dd($carts);

        $cart_barcode = $carts->pluck('item_id')->all(); 
        $cartC = new CartController;
        $status = '2';
        foreach ($catalogs as $key => $value) {
            $exists         = $carts->where('barcode', $value[0])->first();
            // dd($exists);

            $price_master   = DB::table($store_db_name.'.invitem')->where('ICODE', $value[0])->first();

            (array) $push_data = ['v_id' => $v_id, 'trans_from' => $request->trans_from, 'barcode' => $price_master->ICODE, 'qty' => $value[1], 'scode' =>$stores->mapping_store_id];

            $promoC = new PromotionController;
            $offer_data = $promoC->final_check_promo_sitewise($push_data, 0);
            $data = $offer_data;
            // dd($data);

            if($exists) {
                if($value[1] < 1 ){
                    $request->request->add(['cart_id' => $exists->cart_id]);
                    $cartC->remove_product($request);
                }else{
                    unset($cart_barcode[ array_search($value[0], $cart_barcode )]);
                    $request->request->add(['barcode' => $value[0] , 'qty' =>$value[1] , 'unit_mrp' => $price_master->MRP  , 'r_price' => $price_master->MRP * $value[1] , 's_price' => $price_master->MRP * $value[1] , 'discount' => 0 , 'pdata' => '','data'=>$data ]);
                    $cartC->product_qty_update($request);
                }

                $status = '1';
            } else {

                if($value[1] > 0 ){
        
                    $request->request->add(['barcode' => $value[0] , 'qty' =>$value[1] , 'unit_mrp' => $price_master->MRP  , 'r_price' => $price_master->MRP * $value[1] , 's_price' => $price_master->MRP * $value[1] , 'discount' => 0 , 'pdata' => '','data'=>$data, 'ogbarcode' => $value[0] ]);
                    //dd($request);

                    $cartC->add_to_cart($request);
                }

                $status = '2';
            }
        }
        
        //dd($cart_barcode);
        foreach($cart_barcode as $bar){
            $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

            $remove_cart = $carts->where('item_id', $bar)->first();
            //dd($remove_cart);
            if($remove_cart){
                $request->request->add(['cart_id' => $remove_cart->cart_id]);
                $cartC->remove_product($request);
            }
        }

        $carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->where('status', 'process')->get();

        $total = $carts->sum('total');

        if($status == 1) {
            $response = ['status' => 'save_catalogs', 'message' => 'Catalogs Added' , 'total' => format_number($total) ];
        } else {
            $response = ['status' => 'save_catalogs', 'message' => 'Catalogs  Updated' ,'total' => format_number($total) ] ;
        }

        $tender_accept = 1;
        $pay_by_cash_landing = false;

        $user_id = $request->vu_id;
        $udidtoken=$request->udidtoken;
        $organisation = Organisation::where('id',$v_id)->first();
        $role = VendorRoleUserMapping::select('role_id')->where('user_id', $user_id)->first();
        $role_id = $role->role_id;
        $trans_from = $request->trans_from;
        $sParams = ['v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $user_id, 'role_id' => $role_id, 'trans_from' => $trans_from,'udidtoken'=>$udidtoken];

        // $sParams = ['v_id' => $v_id, 'trans_from' => $trans_from];
        $vendorS = new VendorSettingController;
        $paymentType = $vendorS->getPaymentTypeSetting($sParams);
        $vendorApp = $vendorS->getVendorAppSetting($sParams);

        if($vendorApp->landing_payment_option == 'pay_by_cash'){
            $pay_by_cash_landing = true;

        }

        if($pay_by_cash_landing){
            foreach ($paymentType as $key => $type) {
                if($type->name == 'cash'){
                    if($type->tender_accept == 0){
                        $tender_accept = 0;
                    }
                }
            }
            
        }

        if($pay_by_cash_landing && $tender_accept == 0){

            $request->request->add(['subtotal' => $total , 'discount' => 0, 'total' => $total , 'pay_by_voucher' => 0, 'payment_gateway_type' => 'CASH' , 'tax_total' => 0 , 'employee_id' => 0, 'employee_discount' => 0 ]);

            $cartC = new CartController;
            $response = $cartC->process_to_payment($request);
            $response = json_decode($response);
            if($response->status == 'proceed_to_payment'){

                $request->request->add(
                    [ 'amount' => $total ,
                    't_order_id' => '',
                    'order_id' => $response->data->order_id,
                    'pay_id' => '',
                    'method' => 'cash',
                    'invoice_id' => '',
                    'bank' => '',
                    'wallet' => '',
                    'vpa' => '',
                    'error_description' => 'success',
                    'status' => 'success',
                    'address_id' => 'success',
                    'cash_collected' => $total,
                    'cash_return' => '0',
                     ]);
                return $cartC->payment_details($request);

            }

            

        }else{
            return response()->json( $response, 200);
        }
        

        //print_r($
        // $carry_bags = DB::table('vendor_carry_bags')->select('BAG_ID','Name','Price')->where('V_ID', $v_id)->where('Store_ID', $store_id)->get();
        // return response()->json(['status' => 'get_carry_bags_by_store', 'data' => print_r(expression)$bags ],200);
    }

}