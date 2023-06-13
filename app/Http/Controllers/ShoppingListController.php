<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\ShoppingList;
use DB;

class ShoppingListController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    public function list(Request $request){

    	$v_id = $request->v_id;

        if($v_id == 16){

            $shopC = new  Spar\ShoppingListController;
            $response = $shopC->list($request);
            return $response;

        }else{

            $c_id = $request->c_id;
            $store_id = $request->store_id;
            $for_date = $request->for_date;

        	$shoppingList = DB::table('shopping_list as sl')
        					->join('zwv_inventory'.$v_id.$store_id.' as zi', 'sl.product_id' ,'zi.product_id')
        					->select('sl.id','sl.barcode','sl.qty','sl.amount','sl.for_date','sl.added_to_cart','zi.product_name','zi.varient','zi.image')
        					->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('for_date', $for_date)
        					->get();

            return response()->json(['status' => 'list', 'data' => $shoppingList ,'product_image_link' => product_image_link()],200);

        }

    }

    public function create(Request $request)
    {
    	//$response = $this->check_if_exists($request);

        $v_id = $request->v_id;

        if($v_id == 16){

            $shopC = new  Spar\ShoppingListController;
            $response = $shopC->create($request);
            return $response;

        }else{

        	$v_id = $request->v_id;
            $c_id = $request->c_id;
            $store_id = $request->store_id;
            $product_id = $request->product_id;
            $barcode = $request->barcode;
            $qty = $request->qty;
            $amount = $request->amount;
            $for_date = $request->for_date;

        	$check_product_exists = ShoppingList::where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('product_id', $product_id)->where('barcode', $barcode)->where('for_date', $for_date)->count();

        	//
        	if(!empty($check_product_exists)) {
            	return response()->json(['status' => 'product_already_exists', 'message' => 'Product Already Exists' ], 409);
            	
            }

    		$shoppingList = new ShoppingList;
        	$shoppingList->user_id = $c_id;
        	$shoppingList->v_id = $v_id;
        	$shoppingList->store_id = $store_id;
        	$shoppingList->product_id = $product_id;
        	$shoppingList->barcode = $barcode;
    		$shoppingList->qty = $qty;
        	$shoppingList->amount = $amount;
        	$shoppingList->for_date = $for_date;
        	//$shoppingList->added_to_cart = $added_to_cart;
    		$shoppingList->save();


            $shoppingList = DB::table('shopping_list as sl')
        					->join('zwv_inventory'.$v_id.$store_id.' as zi', 'sl.product_id' ,'zi.product_id')
        					->select('sl.id','sl.barcode','sl.qty','sl.amount','sl.for_date','sl.added_to_cart','zi.product_name','zi.varient','zi.image')
        					->where('sl.store_id', $store_id)->where('sl.v_id', $v_id)->where('sl.user_id', $c_id)->where('sl.for_date', $for_date)
    						->orderBy('sl.id','desc')
        					->first();

            return response()->json(['status' => 'create', 'message' => 'Product was successfully added to your Shopping List.', 'data' => $shoppingList, 'product_image_link' => product_image_link() ],200);
        }
    }


    public function update_list(Request $request){

        $v_id = $request->v_id;

        if($v_id == 16){

            $shopC = new  Spar\ShoppingListController;
            $response = $shopC->update_list($request);
            return $response;

        }else{

        	$barcode = $request->barcode; //$id = $request->id;
            $c_id = $request->c_id;
            $store_id = $request->store_id;
            $for_date = $request->for_date;

        	$shoppingList = ShoppingList::where('barcode', $barcode)
                         //   ->where('id', $id)
                        ->where('v_id', $v_id)->where('user_id', $c_id)->where('store_id', $store_id)->where('for_date', $for_date)->first();

        	if($request->has('added_to_cart')){
        		$shoppingList->added_to_cart =  $request->added_to_cart;
        	}

        	$shoppingList->save();

            //This code is used to fetch the shopping list again
            $shoppingList = DB::table('shopping_list as sl')
                            ->join('zwv_inventory'.$v_id.$store_id.' as zi', 'sl.product_id' ,'zi.product_id')
                            ->select('sl.id','sl.barcode','sl.qty','sl.amount','sl.for_date','sl.added_to_cart','zi.product_name','zi.varient','zi.image')
                            ->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('for_date', $for_date)
                            ->get();

           
            return response()->json(['status' => 'update_list', 'message' => 'shopping list Updated successfully', 'data' => $shoppingList ,'product_image_link' => product_image_link()], 200);
    	
        }


    }

    public function check_if_exists(Request $request){




    }

    public function product_qty_update(Request $request){

        $v_id = $request->v_id;
        if($v_id == 16){

            $shopC = new  Spar\ShoppingListController;
            $response = $shopC->product_qty_update($request);
            return $response;

        }else{

        	
            $c_id = $request->c_id;
            $store_id = $request->store_id;
            $product_id = $request->product_id;
            $barcode = $request->barcode;
            $qty = $request->qty;
            $amount = $request->amount;
            $for_date = $request->for_date;

        	$check_product_exists = ShoppingList::where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('product_id', $product_id)->where('barcode', $barcode)->where('for_date', $for_date)->first();

        	$check_product_exists->qty = $check_product_exists->qty + $qty;
            $check_product_exists->amount = $check_product_exists->amount + $amount;

            $check_product_exists->save();

            return response()->json(['status' => 'product_qty_update', 'message' => 'Product quantity successfully Updated'], 200);
        }
    }

    public function remove_product(Request $request)
    {
        $v_id = $request->v_id;
        if($v_id == 16){

            $shopC = new  Spar\ShoppingListController;
            $response = $shopC->remove_product($request);
            return $response;

        }else{

        	$barcode = $request->barcode; //$id = $request->id;
            
            $c_id = $request->c_id;
            $store_id = $request->store_id;
            $for_date = $request->for_date;

        	ShoppingList::where('barcode', $barcode)->where('store_id', $store_id)->where('v_id', $v_id)->where('user_id', $c_id)->where('for_date', $for_date)->delete();

        	return response()->json(['status' => 'remove_product', 'message' => 'Remove Product' ],200);
        }
    }
}
