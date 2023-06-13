<?php

namespace App\Http\Controllers\GiftVoucher;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use Auth;
use Event;
use App\Model\GiftVoucher\GiftVoucher;
use App\Model\GiftVoucher\GiftVoucherCategory;
use App\Model\GiftVoucher\GiftVoucherGroup;
use App\Model\GiftVoucher\GiftVoucherPacks;
use App\Model\GiftVoucher\GiftVoucherConfiguration;
use App\Model\GiftVoucher\GiftVoucherConfigPresetMapping;
use App\Model\GiftVoucher\GiftVoucherConfigPreset;
use App\Model\GiftVoucher\GiftVoucherAllocation;
use App\Model\GiftVoucher\GiftVoucherCartDetails;
use App\Store;
use Carbon\Carbon;
use App\Http\Controllers\Tax\TaxCalculationController;
use App\Model\Tax\TaxGroup;
use App\User;
use App\CustomerGroup;
use App\Country;
use App\CustomerGroupMapping;


class GiftVoucherCartController extends Controller
{
    public function __construct()
    {
       
    }
    
    //all cart action per
    public function cartUpdate(Request $request)
    {
        $this->validate($request, [
            'v_id'          => 'required',
            'store_id'      => 'required',
            'gv_group_id'   => 'required',
            'vu_id'         => 'required',
            'c_id'          => 'required',
            'action_type'   => 'required|in:add_update,remove_group,individual_remove,remove_cart,bulk_add,group_qty_update,sale_value_update',

        ]);
        $v_id         = $request->v_id;
        $store_id     = $request->store_id;
        $gv_group_id  = $request->gv_group_id;
        $user_id      = $request->c_id;
        $trans_from   = $request->trans_from;
        $vu_id        = $request->vu_id;
        $action_type  = trim($request->action_type);
        $gv_list      = json_decode($request->gv_list);
        $where_exist  = array();
        $action_value = ['add_update','remove_group','individual_remove','remove_cart','bulk_add'];
        $action_on    = '';
        $bulk_qty     = isset($request->bulk_qty)?$request->bulk_qty:0;
        $message      ='';
        
        

        if($action_type=='bulk_add'  || $action_type=='group_qty_update'){ 

            $group_details = GiftVoucherGroup::select('value_type')->where('v_id',$v_id)->where('gv_group_id',$gv_group_id)->first();
            if($group_details->value_type=='Custom'){
                if( !$request->has('gift_value') || !$request->has('sale_value') || empty($request->gift_value) || empty($request->sale_value) ){
                    return response()->json(['status' => 'fail' , 'message' => 'Gift or Sale Value is empty '],200);
                }else{
                    $sale_value=$request->sale_value;
                    $gift_value=$request->gift_value;
                }
            }
        }
        if($action_type=='sale_value_update'){  

            $group_details = GiftVoucherGroup::select('value_type')->where('v_id',$v_id)->where('gv_group_id',$gv_group_id)->first();
            if($group_details->value_type=='Custom'){
                
                if(!$request->has('gift_value') || empty($request->gift_value) ){
                    return response()->json(['status' => 'fail' , 'message' => 'Gift Value is empty '],200);
                }else{

                    $gift_value=$request->gift_value;
                    //$request = new \Illuminate\Http\Request();
                    $request = $request->merge([
                        'v_id' => $v_id,
                        'gv_group_id' => $gv_group_id,
                        'gift_value' => $gift_value,
                        'type'=>'array',
                    ]);
                    $res=$this->calculateGiftValue($request);
                    $gift_value=$res['gift_value'];
                    $sale_value=$res['sale_value'];
                }
            }

        }

        if($request->has('action_type') && $action_type=='add_update'){

            $params = array('v_id'=>$v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'gv_group_id'=>$gv_group_id,'user_id'=>$user_id,'gv_list'=>$gv_list,'action_type'=>$action_type);
            $res=$this->voucherAddUpdate($params);
            if($res['status']=='fail'){
                return response()->json(['status' => 'fail' , 'message' => $res['message']],200);   
            }
            $action_on=$action_type;
            
        }elseif($request->has('action_type') && $action_type=='individual_remove'){

            foreach ($gv_list as $key => $value) {
                $gv_id=$value->gv_id;
                $where_exist = array('gv_id'=>$gv_id,'v_id'=>$v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'gv_group_id'=>$gv_group_id,'customer_id'=>$user_id);
                //$where = array('v_id'=>$v_id,'store_id'=>$store_id,'gv_group_id'=>$gv_group_id,'gv_id'=>$gv_id);
                GiftVoucherCartDetails::where($where_exist)->delete();
                //GiftVoucherAllocation::where($where)->update(['is_received' => '0']);
            }
            $action_on=$action_type;

        }elseif($request->has('action_type') && $action_type=='remove_group'){

            $where_exist = array('v_id'=>$v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'gv_group_id'=>$gv_group_id,'customer_id'=>$user_id);
            //$where = array('v_id'=>$v_id,'store_id'=>$store_id,'gv_group_id'=>$gv_group_id);

            /*$gv_id_list = GiftVoucherCartDetails::where($where_exist)->get(['gv_id']);
            GiftVoucherAllocation::whereIn('gv_id', $gv_id_list)->update(['is_received' => '0']);*/

            GiftVoucherCartDetails::where($where_exist)->delete();
            //GiftVoucherAllocation::where($where)->update(['is_received' => '0']);
            $action_on=$action_type;
            
        }elseif($request->has('action_type') && $action_type=='remove_cart'){
            
            $where_exist = array('v_id'=>$v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'customer_id'=>$user_id);
            /*$where = array('v_id'=>$v_id,'store_id'=>$store_id);
            $gv_id_list = GiftVoucherCartDetails::where($where_exist)->get(['gv_id']);
            GiftVoucherAllocation::whereIn('gv_id', $gv_id_list)->update(['is_received' => '0']);*/
            GiftVoucherCartDetails::where($where_exist)->delete();
            //GiftVoucherAllocation::where($where)->update(['is_received' => '0']);
            $action_on=$action_type;
            
        }elseif($request->has('action_type') && $action_type=='bulk_add'){

            $params = array('v_id'=>$v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'gv_group_id'=>$gv_group_id,'user_id'=>$user_id,'bulk_qty'=>$bulk_qty,'action_type'=>$action_type);

            if($bulk_qty<0 || empty($bulk_qty)){
                return response()->json(['status' => 'fail' , 'message' => 'bulk qty should br greater then zero'],200);
            }
            if($group_details->value_type=='Custom'){

                $params['sale_value']=$sale_value;
                $params['gift_value']=$gift_value;
            }
            
            $res=$this->bulkVoucherAddToCart($params);
            if($res['status']=='fail'){
                return response()->json(['status' => 'fail' , 'message' => $res['message']],200);   
            }
            $action_on=$action_type;

        }elseif($request->has('action_type') && $action_type=='group_qty_update'){

            if($bulk_qty<0 || empty($bulk_qty)){
                return response()->json(['status' => 'fail' , 'message' => 'bulk qty should br greater then zero'],200);
            }
            
            $gift_value=format_number($request->gift_value);
            $sale_value=format_number($request->sale_value);
            $params = array('v_id'=>$v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'gv_group_id'=>$gv_group_id,'user_id'=>$user_id,'bulk_qty'=>$bulk_qty,'action_type'=>$action_type,'sale_value'=>$sale_value,'gift_value'=>$gift_value);
            
            $res=$this->updateCartByGroup($params);
            if($res['status']=='fail'){
                return response()->json(['status' => 'fail' , 'message' => $res['message']],200);   
            }
            $message=$res['message'];
            $action_on=$action_type;

        }elseif($request->has('action_type') && $action_type=='sale_value_update'){

            if(!$request->has('gv_id') || !$request->has('voucher_code')){
                return response()->json(['status' => 'fail' , 'message' => 'gv_id or voucher_code field is required'],200);  
            }
            $gv_id=$request->gv_id;
            $voucher_code=$request->voucher_code;
            $params = array('v_id'=>$v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'gv_group_id'=>$gv_group_id,'user_id'=>$user_id,'action_type'=>$action_type,'sale_value'=>$sale_value,'gift_value'=>$gift_value,'gv_id'=>$gv_id,'voucher_code'=>$voucher_code);

            $res=$this->updateSaleValueInCart($params);

            if($res['status']=='fail'){
                return response()->json(['status' => 'fail' , 'message' => $res['message']],200);   
            }
            $message=$res['message'];
            $action_on=$action_type;

        }else{

            return response()->json(['status' => 'fail' , 'message' => 'error in filter type'],200);
        }

        
        $responseData = [
                        'action_on'=>$action_on,
                        'message'=>$message,
                        ]; 
        return response()->json([ 'status' => 'success', 'message' => 'Gift voucher added','data' => $responseData ], 200);

    }

    //update sale value in cart for individual voucher
    public function updateSaleValueInCart($params){
        $v_id        = $params['v_id'];
        $vu_id       = $params['vu_id'];
        $store_id    = $params['store_id'];
        $gv_group_id = $params['gv_group_id'];
        $user_id     = $params['user_id'];
        $sale_value  = trim($params['sale_value']);
        $gift_value  = trim($params['gift_value']);
        $gv_id       = $params['gv_id'];
        $action_type = $params['action_type'];
        $voucher_code = $params['voucher_code'];
        
        $where_exist = array('v_id'=>$v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'gv_group_id'=>$gv_group_id,'customer_id'=>$user_id,'gv_id'=>$gv_id);
        //calculate tax
        $group_details =  $this->getGvGroupDetails($gv_group_id,$v_id);

        if($group_details->tax_api_cal ===true){
            
            $params = array('v_id'=>$v_id,'store_id'=>$store_id,'voucher_code'=>$voucher_code,'gv_group_id'=>$gv_group_id,'sale_value'=>$sale_value,'tax_type'=>$group_details->tax_type,'tax_group_id'=>$group_details->tax_group_id,'hsncode'=>$group_details->hsncode);
        
            $taxcal= new TaxCalculationController;
            $taxdata=$taxcal->taxCalculation($params);
            
            $subtotal=$taxdata['taxable'];
            $total=$taxdata['total'];
            $tax_amount=$taxdata['tax'];
            //dd($taxdata);
            $tax_details=json_encode($taxdata);
          //  dd($tax_details);

        }else{
            $params = array('v_id'=>$v_id,'store_id'=>$store_id,'voucher_code'=>$voucher_code,'gv_group_id'=>$gv_group_id,'sale_value'=>$sale_value,'tax_type'=>$group_details->tax_type,'tax_group_id'=>$group_details->tax_group_id,'hsncode'=>$group_details->hsncode);
                
            $taxcal= new TaxCalculationController;
            $taxdata=$taxcal->taxCalculation($params);
            $subtotal=$sale_value;
            $total=$sale_value;
            $tax_amount=0;
            $tax_details=json_encode($taxdata);

        }

        GiftVoucherCartDetails::where($where_exist)->update(['subtotal'=>(string)$subtotal,'total'=>(string)$total,'tax_amount'=>(string)$tax_amount,
            'tdata'=>$tax_details,'sale_value' => $sale_value,'gift_value'=>$gift_value]);
        return ['status' => 'success' , 'message' => 'Sale value update in the cart '];
        
    }
    //bulk voucher allocation
    public function bulkVoucherAddToCart($params){
        $v_id        = $params['v_id'];
        $vu_id       = $params['vu_id'];
        $store_id    = $params['store_id'];
        $gv_group_id = $params['gv_group_id'];
        $user_id     = $params['user_id'];
        $bulk_qty    = $params['bulk_qty'];
        
        $where_exist = array('v_id'=>$v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'customer_id'=>$user_id,'gv_group_id'=>$gv_group_id);
        
        $cart_list   =  GiftVoucherCartDetails::where($where_exist)->get(['gv_id']);
        
        $voucherList =  GiftVoucherAllocation::leftjoin('gift_voucher','gift_voucher.gv_id','gv_allocation.gv_id')
                                              ->leftjoin('gv_group','gv_group.gv_group_id','gv_allocation.gv_group_id')
                                              ->Where('gv_allocation.v_id',$v_id)
                                              ->Where('gift_voucher.is_blocked','0')
                                              ->Where('gv_allocation.gv_group_id',$gv_group_id)
                                              ->Where('gv_allocation.store_id',$store_id)
                                              //->Where('gv_allocation.is_received','0')
                                              ->whereNotIn('gv_allocation.gv_id',$cart_list)
                                              ->where('gift_voucher.status','Allocated');
        //$total_voucher_count =$voucherList->count();                                        
        $voucherList= $voucherList->select('gift_voucher.gv_id','gift_voucher.gv_code','gv_group.sale_value','gv_group.gift_value','gift_voucher.created_at','gv_group.gv_group_id','gift_voucher.voucher_sequence','gv_allocation.store_id')
                                 ->orderBy('gift_voucher.voucher_sequence')->take($bulk_qty)->get();

        $count = $voucherList->count();
        if($count!=$bulk_qty){

            return ['status' => 'fail' , 'message' => 'Entered quantity exceeds available voucher quantity'];
        }else{
            $params['gv_list']=$voucherList;

            return $this->addVoucherInCart($params);
            
        }
                                                

    }

    //cart update on the basis of group
    public function updateCartByGroup($params){

        $v_id        = $params['v_id'];
        $vu_id       = $params['vu_id'];
        $store_id    = $params['store_id'];
        $gv_group_id = $params['gv_group_id'];
        $user_id     = $params['user_id'];
        $bulk_qty    = $params['bulk_qty'];
        $sale_value  = $params['sale_value'];
        $gift_value  = $params['gift_value'];

        $where_exist = array('v_id'=>$v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'customer_id'=>$user_id,'gv_group_id'=>$gv_group_id);
        
        $cart_list=GiftVoucherCartDetails::where($where_exist)->get(['gv_id']);
        $cart_count = $cart_list->count();
        if($cart_count>$bulk_qty){

            $delete_voucher_count=$cart_count-$bulk_qty;
            $gv_id_list=$cart_list->take($delete_voucher_count)->pluck('gv_id');
            GiftVoucherCartDetails::where($where_exist)->whereIn('gv_id',$gv_id_list)->delete();
            
            //GiftVoucherAllocation::whereIn('gv_id', $gv_id_list)->update(['is_received'=> '0']);
            return ['status' => 'success' , 'message' => 'Group Cart update'];
        }elseif($cart_count==$bulk_qty){
            return ['status' => 'success' , 'message' => 'Quantity equals to group quantity '];
        }elseif($cart_count<$bulk_qty){

            $add_voucher_count=$bulk_qty-$cart_count;
            $voucherList =   GiftVoucherAllocation::leftjoin('gift_voucher','gift_voucher.gv_id','gv_allocation.gv_id')
                                                  ->leftjoin('gv_group','gv_group.gv_group_id','gv_allocation.gv_group_id')
                                                  ->Where('gv_allocation.v_id',$v_id)
                                                  ->Where('gift_voucher.is_blocked','0')
                                                  ->Where('gv_allocation.gv_group_id',$gv_group_id)
                                                  ->Where('gv_allocation.store_id',$store_id)
                                                  ->whereNotIn('gv_allocation.gv_id',$cart_list)
                                                  ->where('gift_voucher.status','Allocated');
            //$total_voucher_count =$voucherList->count();                                        
            $voucherList= $voucherList->select('gift_voucher.gv_id','gift_voucher.gv_code','gv_group.sale_value','gv_group.gift_value','gift_voucher.created_at','gv_group.gv_group_id','gift_voucher.voucher_sequence','gv_allocation.store_id')
                                    ->orderBy('gift_voucher.voucher_sequence')
                                    ->take($add_voucher_count)->get();
            $count = $voucherList->count();
            if($count!=$add_voucher_count){

                return ['status' => 'fail' , 'message' => 'Entered quantity exceeds available voucher quantity'];
            }else{

                $params['gv_list']=$voucherList;
                return $this->addVoucherInCart($params);
            }
        }else{
            return ['status' => 'fail' , 'message' => 'Somthing went wrong.'];
        }    
                                                

    }

    //Voucher add or update into cart
    public function voucherAddUpdate($params){

        
        $v_id        = $params['v_id'];
        $vu_id       = $params['vu_id'];
        $store_id    = $params['store_id'];
        $gv_group_id = $params['gv_group_id'];
        $user_id     = $params['user_id'];
        $gv_list     = $params['gv_list'];
        

        $where_exist = array('v_id'=>$v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'gv_group_id'=>$gv_group_id,'customer_id'=>$user_id);
        $voucher_exist= GiftVoucherCartDetails::where($where_exist)->exists();
        if($voucher_exist){
            
           //$gv_id_list = GiftVoucherCartDetails::where($where_exist)->get(['gv_id']);
           // GiftVoucherAllocation::whereIn('gv_id', $gv_id_list)->update(['is_received' => '0']);
            GiftVoucherCartDetails::where($where_exist)->delete();
        }
        return $this->addVoucherInCart($params);
        

    }

    //add voucher in cart
    public function addVoucherInCart($params){

        $v_id        = $params['v_id'];
        $vu_id       = $params['vu_id'];
        $store_id    = $params['store_id'];
        $gv_group_id = $params['gv_group_id'];
        $user_id     = $params['user_id'];
        $gv_list     = $params['gv_list'];
        $action_type = $params['action_type'];
        
        
        $group_value_type = GiftVoucherGroup::select('value_type')->where('v_id',$v_id)->where('gv_group_id',$gv_group_id)->first();

        foreach ($gv_list as $key => $value) {
                
                $sale_value=(float)$value->sale_value;
                $gift_value=(float)$value->gift_value;
                
                
                
                if($group_value_type->value_type=='Custom' && ($action_type=='bulk_add') ){

                    $sale_value  = $params['sale_value'];
                    $gift_value  = $params['gift_value'];
                }elseif($action_type=='group_qty_update'){
                    $sale_value  = $params['sale_value'];
                    $gift_value  = $params['gift_value'];
                }else{
                    if(empty($sale_value) || empty($gift_value) || $sale_value<0){

                        return ['status' => 'fail' , 'message' => 'Gift or Sale Value is empty '];
                    }
                }

                $sale_value=$this->formatValue((float)$sale_value);
                $gift_value=$this->formatValue((float)$gift_value);
                
                //calculate tax
                $group_details =  $this->getGvGroupDetails($gv_group_id,$v_id);
                //dd($group_details->tax_api_cal);
                if($group_details->tax_api_cal ===true){
                    
                    $params_tax = array('v_id'=>$v_id,'store_id'=>$store_id,'voucher_code'=>$value->gv_code,'gv_group_id'=>$gv_group_id,'sale_value'=>$sale_value,'tax_type'=>$group_details->tax_type,'tax_group_id'=>$group_details->tax_group_id,'hsncode'=>$group_details->hsncode);
                
                    $taxcal= new TaxCalculationController;
                    $taxdata=$taxcal->taxCalculation($params_tax);
                    //dd($taxdata);
                    if($taxdata['taxable']=='0'){
                        $subtotal=$taxdata['netamt'];
                        $total=$taxdata['netamt'];
                        $tax_amount=0;
                    }else{
                        $subtotal=$taxdata['taxable'];
                        $total=$taxdata['total'];
                        $tax_amount=$taxdata['tax'];
                    }
                    $tax_details=json_encode($taxdata);

                }else{
                    $params_tax = array('v_id'=>$v_id,'store_id'=>$store_id,'voucher_code'=>$value->gv_code,'gv_group_id'=>$gv_group_id,'sale_value'=>$sale_value,'tax_type'=>$group_details->tax_type,'tax_group_id'=>$group_details->tax_group_id,'hsncode'=>$group_details->hsncode);
                
                    $taxcal= new TaxCalculationController;
                    $taxdata=$taxcal->taxCalculation($params_tax);
                    $subtotal=$sale_value;
                    $total=$sale_value;
                    $tax_amount=0;
                    $tax_details=json_encode($taxdata);

                }
                
                $GiftVoucherCartDetails                   = new GiftVoucherCartDetails;
                $GiftVoucherCartDetails->v_id             = $v_id;
                $GiftVoucherCartDetails->store_id         = $store_id;
                $GiftVoucherCartDetails->gv_group_id      = $gv_group_id;
                $GiftVoucherCartDetails->vu_id            = $vu_id;
                $GiftVoucherCartDetails->customer_id      = $user_id;
                $GiftVoucherCartDetails->gv_id            = $value->gv_id;
                $GiftVoucherCartDetails->sale_value       = (string)$sale_value;
                $GiftVoucherCartDetails->gift_value       = (string)$gift_value;
                $GiftVoucherCartDetails->voucher_code     = $value->gv_code;
                $GiftVoucherCartDetails->voucher_sequence = $value->voucher_sequence;
                $GiftVoucherCartDetails->subtotal         = (string)$subtotal;
                $GiftVoucherCartDetails->total            = (string)$total;
                $GiftVoucherCartDetails->tdata            = $tax_details;
                $GiftVoucherCartDetails->tax_amount       = (string)$tax_amount;
              //$GiftVoucherCartDetails->mobile           = $value->mobile;
                $GiftVoucherCartDetails->save();
                //comment out code for is_received
                /*$where = array('v_id'=>$v_id,'store_id'=>$store_id,'gv_group_id'=>$gv_group_id,'gv_id'=>$value->gv_id);
                GiftVoucherAllocation::where($where)->update(['is_received' => '1']);*/
                
        }

    }
    //get cart details 
    public function getCartDetails(Request $request){

        $this->validate($request, [
            'v_id'          => 'required',
            'store_id'      => 'required',
            'vu_id'         => 'required',
            'c_id'          => 'required',

        ]);

        $v_id         = $request->v_id;
        $vu_id        = $request->vu_id;
        $store_id     = $request->store_id;
        $user_id      = $request->c_id;

        $where_get = array('gv_cart_details.v_id'=>$v_id,'gv_cart_details.vu_id'=>$vu_id,'gv_cart_details.store_id'=>$store_id,
                            'gv_cart_details.customer_id'=>$user_id);
        $cart_data= GiftVoucherCartDetails::leftjoin('gv_group','gv_group.gv_group_id','gv_cart_details.gv_group_id')
                                          ->where($where_get)
                                          ->select('gv_cart_details.voucher_code','gv_cart_details.voucher_sequence','gv_cart_details.gift_value','gv_cart_details.sale_value','gv_cart_details.mobile','gv_cart_details.gv_group_id','gv_cart_details.gv_id','gv_group.value_type','gv_cart_details.tax_amount','gv_cart_details.subtotal','gv_cart_details.total')
                                          ->get();
        $total_item = $cart_data->count();                                          
        $group_cart_data= GiftVoucherCartDetails::leftjoin('gv_group','gv_group.gv_group_id','gv_cart_details.gv_group_id')
                                                ->where($where_get)
                                                ->select('gv_group.gv_group_name','gv_group.gv_group_description','gv_cart_details.gv_group_id','gv_group.value_type','gv_cart_details.sale_value as sale_value_s','gv_cart_details.gift_value as gift_value_s',
                                                        DB::raw("count(gv_cart_details.gv_group_id) as quantity"),
                                                        DB::raw("sum(gv_cart_details.sale_value) as sale_value"))
                                                ->groupBy(['gv_cart_details.gv_group_id'])
                                                ->get();
        foreach ($group_cart_data as $key => $value) {
            
            
            $group_cart= GiftVoucherCartDetails::where('gv_group_id',$value->gv_group_id)->select('mobile','sale_value','gift_value')->first();
            
            if(!empty($group_cart->mobile)){
                $count_cart= GiftVoucherCartDetails::where('gv_group_id',$value->gv_group_id)->count();
                $count_mobile= GiftVoucherCartDetails::where('gv_group_id',$value->gv_group_id)->where('mobile',$group_cart->mobile)->count();
                if($count_cart==$count_mobile){
                    $group_cart_data[$key]['mobile']=$group_cart->mobile;
                }else{
                   $group_cart_data[$key]['mobile']=''; 
                }

            }
            /*$group_cart_single= GiftVoucherCartDetails::where('gv_group_id',$value->gv_group_id)
                                                ->where('gift_value',$value->gift_value)
                                                ->select('sale_value','gift_value')->first();
            dd($value); */                                   
            /*$group_cart_data[$key]['sale_value_s']=$value->s_value;
            $group_cart_data[$key]['gift_value_s']=$value->g_value;*/
        }
        
        $total_group = $group_cart_data->count();   
                                                     
        if(!empty($cart_data)){                                      
            $subtotal  = $cart_data->sum('subtotal');
            $total_tax  = $cart_data->sum('tax_amount');
            //$total_amount= $subtotal+$total_tax; 
            $total_amount= $cart_data->sum('total');
        }else{
            $subtotal=0;
            $total_tax=0;
            $total_amount=0; 
        }                                
        
        //$this->formatValue($total_tax)

       // dd($total_tax);
        $responseData = [
                        'total_item_in_cart'=>$total_item,
                        'total_group'=>$total_group,
                        'subtotal'=>(string)$subtotal,
                        'total_tax'=>(string)$total_tax, 
                        'total_amount'=>(string)$total_amount,
                        'cart_details'=>$cart_data,
                        'group_cart_details'=>$group_cart_data,
                         
                        ];
                        


        return response()->json([ 'status' => 'success', 'message' => 'Gift voucher cart details','data' => $responseData ], 200);
        
    }

    public function getGvGroupDetails($gv_group_id,$v_id){

            $group_details = GiftVoucherGroup::select('tax_type','hsncode','tax_group_id','tax_status')->where('v_id',$v_id)->where('gv_group_id',$gv_group_id)->first();
            $tax_api_cal=false;
            if($group_details->tax_status=='Not-Applicable' ){
                $responseData = (object)[
                                        'hsncode'=>$group_details->hsncode,
                                        'tax_group_id'=>$group_details->tax_group_id,
                                        'tax_status'=>$group_details->tax_status,
                                        'applicable_on'=>'',
                                        'tax_type' =>$group_details->tax_type,
                                        'tax_api_cal'=>$tax_api_cal
                                        ];
            }elseif(!empty($group_details->tax_group_id) && $group_details->tax_status=='Applicable'){
                $responseData = (object)[
                                        'hsncode'=>$group_details->hsncode,
                                        'tax_group_id'=>$group_details->tax_group_id,
                                        'tax_status'=>$group_details->tax_status,
                                        'applicable_on'=>'group',
                                        'tax_type' =>$group_details->tax_type,
                                        'tax_api_cal'=>true
                                        ];

            }elseif(!empty($group_details->hsncode) && $group_details->tax_status=='Applicable'){

                $group_data = DB::table('tax_hsn_cat')->select('cat_id')->where('store_id', $store_id)->where('v_id', $v_id)->where('hsncode', $hsncode)->where('deleted_by', '0')->first();
                $tax_group_id=0;
                if(isset($group_data)){
                
                    $exists = TaxGroup::where('v_id',$v_id)->where('id',$group_data->cat_id)->whereNull('deleted_at')->exists();
                    if($exists){

                        GiftVoucherGroup::where('v_id',$v_id)->where('gv_group_id',$gv_group_id)->update(['tax_group_id' => $group_data->cat_id]);
                      $tax_api_cal=true;
                      $tax_group_id=$group_data->cat_id;
                      
                    }
                }
                $responseData =(object) [
                                        'hsncode'=>$group_details->hsncode,
                                        'tax_group_id'=>$tax_group_id,
                                        'tax_status'=>$group_details->tax_status,
                                        'applicable_on'=>'hsncode',
                                        'tax_type' =>$group_details->tax_type,
                                        'tax_api_cal'=>$tax_api_cal
                                        ];

            }else{
                $responseData = (object)[
                                        'hsncode'=>$group_details->hsncode,
                                        'tax_group_id'=>$group_details->tax_group_id,
                                        'tax_status'=>'Not-Applicable',
                                        'applicable_on'=>'',
                                        'tax_type' =>$group_details->tax_type,
                                        'tax_api_cal'=>$tax_api_cal
                                        ];
            }

            return $responseData;

    }
    
 //tag customer
    public function addCustomerForGv(Request $request){

        $this->validate($request, [
            'v_id'            => 'required',
            'store_id'        => 'required',
            'vu_id'           => 'required',
            'customer_mobile' => 'required',
            'c_id'            => 'required',
            'action_type'     => 'required|in:tag_individual,tag_group,tag_cart,remove_individual,remove_group,remove_cart',

        ]);

        $customer_mobile = $request->customer_mobile;
        $vu_id = $request->vu_id;
        $v_id = $request->v_id;
        $store_id = $request->store_id;
        $user_id      = $request->c_id;
        $c_id = null;
        $mobile = '';
        $group_code = 'REGULAR'; //For Regular Customer
        $group_id = CustomerGroup::select('id')->where('code', $group_code)->first()->id;
        $fname = 'GV';
        $lname = 'Customer';
        $email = '';
        $gender = '';
        $gstin = '';
        $dob = null;

        $action_type=$request->action_type;
        if ($request->has('store_id')) {
            $country = Store::select('country')->where('v_id', $v_id)->where('store_id', $store_id)->first();
            $countryId=$country->country;
            $dialCode = Country::select('dial_code')->where('id', $countryId)->first();
            $customerPhoneCode=$dialCode->dial_code;
        }
        $customerPhoneCode=empty($customerPhoneCode)?'':$customerPhoneCode;

        if ($request->has('gv_group_id')) {
            $gv_group_id  = $request->gv_group_id;
        }
        if ($request->has('gv_id')) {
            $gv_id  = $request->gv_id;
        }

        $exists_user = User::select('c_id','mobile')->where('mobile', $customer_mobile)->where('v_id', $request->v_id)->first();
        
        if (!$exists_user) {

            $user = new User;
            $user->mobile    = $customer_mobile;
            $user->customer_phone_code   = $customerPhoneCode;
            $user->first_name = $fname;
            $user->v_id = $v_id;
            $user->last_name  = $lname;
            $user->email      = $email;
            $user->dob        = empty($dob)?$dob:date('Y-m-d',strtotime($dob));
            $user->gender     = $gender;
            $user->status     = 1;
            $user->anniversary_date  ='';
            $user->gstin  = $gstin;
            $user->api_token  =  str_random(50);
            $user->vendor_user_id = $vu_id;
            $user->save();

            $c_id = $user->c_id;
            $mobile =$user->mobile;
            $cgm = new CustomerGroupMapping;
            $cgm->c_id = $c_id;
            $cgm->group_id = $group_id;
            $cgm->save();

        }else{
            $c_id = $exists_user->c_id;
            $mobile = $exists_user->mobile;
        }
            
        if(empty($c_id) ||  empty($mobile) ){

            return response()->json(['status' => 'fail' , 'message' => 'Mobile number not tagging '],200);
        }

        if($action_type=='tag_individual' && !empty($gv_group_id) && !empty($gv_id) ){

            $where = array('gv_id'=>$gv_id,'v_id'=>$v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'gv_group_id'=>$gv_group_id,'customer_id'=>$user_id);
            GiftVoucherCartDetails::where($where)->update(['gift_customer_id' => $c_id,'mobile'=>$mobile] );

        }elseif($action_type=='tag_group' && !empty($gv_group_id) ){

            $where = array('v_id'=>$v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'gv_group_id'=>$gv_group_id,'customer_id'=>$user_id);
            GiftVoucherCartDetails::where($where)->update(['gift_customer_id' => $c_id,'mobile'=>$mobile] );

        }elseif($action_type=='tag_cart'){

            $where = array('v_id'=>$v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'customer_id'=>$user_id);
            GiftVoucherCartDetails::where($where)->update(['gift_customer_id' => $c_id,'mobile'=>$mobile] );

        }elseif($action_type=='remove_individual' && !empty($gv_group_id) && !empty($gv_id) ){

            $where = array('gv_id'=>$gv_id,'v_id'=>$v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'gv_group_id'=>$gv_group_id,'customer_id'=>$user_id);
            GiftVoucherCartDetails::where($where)->update(['gift_customer_id' => '','mobile'=>''] );

        }elseif($action_type=='remove_group' && !empty($gv_group_id) ){

            $where = array('v_id'=>$v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'gv_group_id'=>$gv_group_id,'customer_id'=>$user_id);
            GiftVoucherCartDetails::where($where)->update(['gift_customer_id' => '','mobile'=>''] );

        }elseif($action_type=='remove_cart'){

            $where = array('v_id'=>$v_id,'vu_id'=>$vu_id,'store_id'=>$store_id,'customer_id'=>$user_id);
            GiftVoucherCartDetails::where($where)->update(['gift_customer_id' => '','mobile'=>''] );

        }else{
            return response()->json(['status' => 'fail' , 'message' => 'error in filter type'],200);
        }
        $responseData = [
                        'action_on'=>$action_type,
                        ]; 
        return response()->json([ 'status' => 'success', 'message' => 'Mobile number tageed into cart','data' => $responseData ], 200);    


    }

    public function calculateGiftValue(Request $request){ 

        $this->validate($request, [
            'v_id'            => 'required',
            'gv_group_id'     => 'required',
            'gift_value'      => 'required',
           // 'action_type'     => 'required|in:cal_bulk,cal_individual',

        ]);

        $v_id        = $request->v_id;
        $gv_group_id = $request->gv_group_id;
        $gift_value  = (float)$request->gift_value;
        $action_type = $request->action_type;
        $group_details = GiftVoucherGroup::select('value_type','gift_value')->where('v_id',$v_id)->where('gv_group_id',$gv_group_id)->first();
        if($group_details->value_type=='Custom'){
            $gift_value =   $this->formatValue($gift_value);
            $percentage = (float)$group_details->gift_value;
            $percentage_val = ($percentage/100)* $gift_value;
            $percentage_val =   $this->formatValue($percentage_val);
            $sale_value = $gift_value+$percentage_val;
            $sale_value =   $this->formatValue($sale_value);
            
            $responseData = [
                        'sale_value'=>$sale_value,
                        'gift_value'=>$gift_value,
                        'percentage'=>$percentage,
                        'percentage_val'=>$percentage_val,
                        'action_type'=>$action_type,
                        ]; 

             if($request->has('type')  && $request->type=='array'){ 

                return $responseData ;

             }
                               
            return response()->json([ 'status' => 'success', 'message' => 'Gift value','data' => $responseData ], 200);
            
         }else{
            return response()->json(['status' => 'fail' , 'message' => 'Gift voucher group value type should be custom'],200);

         }
        


    }

    public function formatValue($value)
    {
        if (is_float($value) && $value != '0.00') {
            
            $tax = explode(".", $value);
            if (count($tax) == 1) {
                $strlen = 1;
            } else {
                $strlen = strlen($tax[1]);

            }
            if ($strlen == 2 || $strlen == 1) {
                return (float)$value;
            } else {
                $strlen = $strlen - 2;
                return (float)substr($value, 0, -$strlen);
            }
        } else {
            
            return $value;
        }
    }



}
