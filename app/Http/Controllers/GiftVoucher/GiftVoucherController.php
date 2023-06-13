<?php

namespace App\Http\Controllers\GiftVoucher;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use Auth;
use Event;
use App\Model\GiftVoucher\GiftVoucher;
use App\Model\GiftVoucher\GiftVoucherGroup;
use App\Model\GiftVoucher\GiftVoucherPacks;
use App\Model\GiftVoucher\GiftVoucherConfiguration;
use App\Model\GiftVoucher\GiftVoucherConfigPresetMapping;
use App\Model\GiftVoucher\GiftVoucherConfigPreset;
use App\Model\GiftVoucher\GiftVoucherAllocation;
use App\Model\GiftVoucher\GiftVoucherCartDetails;
use App\Store;
use Carbon\Carbon;



class GiftVoucherController extends Controller
{
    public function __construct()
    {
       
    }
    //get all group information 
    public function getGvGroupList(Request $request)
    {
        $this->validate($request, [
            'v_id'                  => 'required',
            'store_id'            => 'required',
        ]);
        $v_id      = trim($request->v_id);
        $store_id  = trim($request->store_id);
        $filterBy=[];
        $filterBy=json_decode($request->filter_by);
        $filterArray=['Physical-Voucher','E-Voucher','Fixed','Custom'];
        $gv_type='';
        $value_type='';
        if(!empty($filterBy) ){
            foreach ($filterBy as $key => $value) {
                $filterExist=in_array($value, $filterArray);
                if($filterExist && ($value=='Physical-Voucher' || $value=='E-Voucher')){
                    $gv_type=$value;
                }elseif($filterExist && ($value=='Fixed' || $value=='Custom')){
                    $value_type=$value;
                }
            }
        }    
        $currentDate=Carbon::now()->format('Y-m-d H:i:s');
        //DB::enableQueryLog();
        $groupList = GiftVoucherAllocation::leftjoin('gv_group','gv_group.gv_group_id','gv_allocation.gv_group_id')
                                            ->leftjoin('gift_voucher','gift_voucher.gv_id','gv_allocation.gv_id')
                                            ->where('gv_allocation.v_id', $v_id)
                                            ->Where('gv_allocation.store_id',$store_id)
                                            ->Where('gv_group.status','Active')
                                            ->where('gift_voucher.status','Allocated')
                                            ->Where('gift_voucher.is_blocked','0');
                                            /*->where(function($q) use($currentDate)
                                            {
                                                 $q->where('gv_group.valid_upto','>=',$currentDate)
                                                   //->where('gv_group.effective_from','<=',$currentDate)
                                                   ->orwhereNull('gv_group.valid_upto');
                                             });*/
        if($request->has('filter_by') && (!empty($gv_type) || !empty($value_type) ) ) {
            
            $groupList= $groupList->where(function($q) use($gv_type,$value_type)
                                            {
                                                 $q->where('gv_group.gv_type',$gv_type)
                                                   ->orwhere('gv_group.value_type',$value_type);
                                             });
        }
        $groupList= $groupList->select('gv_group.gv_group_id','gv_group.gv_group_name','gv_group.value_type',
                                        'gv_group.gv_group_description','gv_group.gift_value',DB::raw("count(gv_allocation.gv_id) as available_voucher"))
                               ->groupBy('gv_allocation.gv_group_id') 
                               ->get();
    //dd(DB::getQueryLog());
        $count = $groupList->count();  
        $responseData = [
                         'groupList' => $groupList,
                         'groupCount' => $count,
                        ];                                  
        return response()->json([ 'status' => 'success', 'message' => 'GV Group list','data'=>$responseData ], 200);

    }
    //get voucher list on the basis of group id
    public function getVoucherList(Request $request)
    {
        $this->validate($request, [
            'v_id'                => 'required',
            'store_id'            => 'required',
            'gv_group_id'         => 'required',
            //'from'         => 'required',
            //'to'         => 'required',
            'per_page'            => 'required',
        ]);
        $v_id        = $request->v_id;
        $store_id    = $request->store_id;
        $gv_group_id = $request->gv_group_id;
        $customer_id = $request->c_id;

        //$fromData    = $request->from;
       // $toData      = $request->to;
        //$take        = $toData - $fromData;
        $groupList   = [];
        $per_page        = $request->per_page;
        //cart data
        $voucherList =   GiftVoucherAllocation::leftjoin('gift_voucher','gift_voucher.gv_id','gv_allocation.gv_id')
                                              ->leftjoin('gv_group','gv_group.gv_group_id','gv_allocation.gv_group_id')
                                              ->leftjoin('gv_cart_details','gv_cart_details.gv_id','gv_allocation.gv_id')
                                              ->Where('gv_allocation.v_id',$v_id)
                                              ->Where('gift_voucher.is_blocked','0')
                                              ->Where('gv_allocation.gv_group_id',$gv_group_id)
                                              ->Where('gv_allocation.store_id',$store_id)
                                              ->where(function ($query) use ($customer_id) {
                                                $query->where('gv_cart_details.customer_id', '=', $customer_id)
                                                ->orWhereNull('gv_cart_details.customer_id');
                                                })
                                              ->where('gift_voucher.status','Allocated');
        $total_voucher_count =$voucherList->count();                                        
        $voucherList= $voucherList->select('gv_cart_details.gv_cart_id','gv_cart_details.customer_id','gift_voucher.gv_id','gift_voucher.gv_code','gv_group.value_type','gift_voucher.sales_value as sale_value','gift_voucher.gift_value','gift_voucher.created_at','gv_group.gv_group_id','gift_voucher.voucher_sequence','gv_allocation.store_id',
            DB::raw("CASE WHEN gv_cart_details.gv_cart_id IS NOT NULL THEN '1' ELSE '0' END as is_received"),
            DB::raw("CASE WHEN gv_cart_details.gv_cart_id IS NOT NULL THEN 'TRUE' ELSE 'FALSE' END as is_received_boolean"))
                                    ->orderBy('gift_voucher.voucher_sequence')
                                    ->paginate($per_page);
                                        
        $count = $voucherList->count();
        $responseData = [
                         'total_voucher_count'=>$total_voucher_count,
                         'voucher_count' => $count,
                         'voucherList' => $voucherList,
                         
                        ]; 
        return response()->json([ 'status' => 'success', 'message' => 'Gift voucher list','data' => $responseData ], 200);

    }

    //get indivusal group details and preset details
    public function getGroupDetails(Request $request)
    {
        $this->validate($request, [
            'v_id'                => 'required',
            'gv_group_id'         => 'required',
        ]);
        $v_id           = $request->v_id;
        $gv_group_id    = $request->gv_group_id;
        $groupInfo=[]; 
        $presetDetails=[];
       // $filterPreset=[];
        $groupInfo=   GiftVoucherGroup::leftjoin('gv_category','gv_category.gv_cat_id','gv_group.category_id')
                                        ->Where('gv_group.v_id',$v_id)
                                        ->Where('gv_group.gv_group_id',$gv_group_id)
                                        ->select('gv_group.gv_group_id','gv_group.gv_group_name','gv_group.gv_group_description','gv_group.gv_type','gv_category.gv_cat_name','gv_group.is_with_pack','gv_group.value_type','gv_group.sale_value','gv_group.gift_value','gv_group.config_preset_id','gv_group.validity')
                                        ->first();
        if(!empty($groupInfo)){
            $presetId=$groupInfo->config_preset_id;
            $presetDetails=   GiftVoucherConfigPresetMapping::leftjoin('gv_config_master','gv_config_master.config_id',
                                                                        'gv_config_preset_mapping.config_id')
                                                            ->Where('gv_config_preset_mapping.config_preset_id',$presetId)
                                                            ->Where('gv_config_preset_mapping.v_id',$v_id)
                                                            ->select('gv_config_master.config_name','gv_config_master.config_code','gv_config_preset_mapping.config_value')
                                                            ->orderBy('gv_config_master.config_id')
                                                            ->get();
            $filterPreset = collect($presetDetails)->filter(function($item) {
                        
                                if($item->config_code=='one_time' ){
                                    if($item->config_value=='Yes'){
                                        $item->config_name='One-time use only';
                                    }else{
                                        $item->config_name='Non one-time use';
                                    }
                                   
                                    return $item;
                                }
                                if($item->config_code=='allow_partial_redemption' ){
                                    if($item->config_value=='Yes'){
                                        $item->config_name='Partial Redemption';
                                    }else{
                                        $item->config_name='No Partial Redemption';
                                    }
                                    
                                    return $item;
                                }
                                if($item->config_code=='allow_return_gv' ){
                                    if($item->config_value=='Yes'){
                                        $item->config_name='Returnable';
                                    }else{
                                        $item->config_name='Non Returnable';
                                    }
                                    
                                    return $item;
                                }
                                if($item->config_code=='recharge_limit'){
                                    if((int)$item->config_value>0){
                                        $item->config_name='Rechargeable';
                                        $item->limit=$item->config_value;
                                        $item->config_value='Yes';
                                        $item->limit_text='upto';
                                    }else{
                                        $item->config_name='Non Rechargeable';
                                        $item->config_value='No';
                                        $item->limit=0;
                                        $item->limit_text='upto';
                                    }
                                   
                                    return $item;
                                }
                                if($item->config_code=='refund_limit'){
                                    if((int)$item->config_value>0){
                                        $item->config_name='Refundable';
                                        $item->limit=$item->config_value;
                                        $item->config_value='Yes';
                                        $item->limit_text='upto';
                                    }else{
                                        $item->config_name='Non Refundable';
                                        $item->config_value='No';
                                        $item->limit=0;
                                        $item->limit_text='upto';
                                    }
                                    return $item;
                                }
                                

            })->values(); 
              
        }   
        $filterPreset[]=(object)['config_name'=>'No Authentication','config_value'=>'No','config_code'=>'authentication']; 
        $responseData = [
                         'groupInfo' => $groupInfo,
                         'presetDetails' => $filterPreset,
                        ];
        return response()->json([ 'status' => 'success', 'message' => 'Gift voucher information','data'=>$responseData ], 200);

    }

    public function getRangeList(Request $request)
    {

            $this->validate($request, [
                'v_id'                => 'required',
                'gv_group_id'         => 'required',
                'store_id'         => 'required',
            ]);
            $v_id           = $request->v_id;
            $gv_group_id    = $request->gv_group_id;
            $store_id    = $request->store_id;
            $voucherList=   GiftVoucherAllocation::leftjoin('gift_voucher','gift_voucher.gv_id','gv_allocation.gv_id')
                                                ->leftjoin('gv_cart_details','gv_cart_details.gv_id','gv_allocation.gv_id')
                                                ->Where('gv_allocation.v_id',$v_id)
                                                ->Where('gift_voucher.is_blocked','0')
                                                ->Where('gv_allocation.gv_group_id',$gv_group_id)
                                                ->Where('gv_allocation.store_id',$store_id)
                                                ->select('gv_allocation.gv_id','gift_voucher.voucher_sequence','gift_voucher.status',DB::raw("CASE WHEN gv_cart_details.gv_cart_id IS NOT NULL THEN '1' ELSE '0' END as is_received"))
                                                ->orderBy('gift_voucher.voucher_sequence')
                                                ->get();
            //dd($voucherList);
            $range_array=[];
            $counter = 0; 
            foreach ($voucherList as $key => $value) {
                    
                    if(!empty($start_r) && ($value->status=="Sold" || $value->is_received=="1") ){
                        
                        $end_r=$voucherList[$key-1]['voucher_sequence'];
                        $total_voucher=($end_r-$start_r)+1;
                        //$range_array[]=array('s'=>$start_r,'e'=>$end_r);
                        $range_array[]=array('s'=>$start_r,'e'=>$end_r,'t'=>$total_voucher);
                        $start_r='';
                        $total_voucher='';
                    }

                   if($value->status=="Allocated" &&  $value->is_received=="0" && empty($start_r) ){ 
                        $start_r=$value->voucher_sequence;
                        //$range_array[]=array('s'=>$start_r,'e'=>'');
                        $range_array[]=array('s'=>$start_r,'e'=>'','t'=>'');

                   } 
                   if( $counter == count( $voucherList ) - 1 && count( $range_array )!=0) { 
                        $last_range_arr=count($range_array)-1;
                        if(empty($range_array[$last_range_arr]['e'])){
                            $total_voucher_last=($voucherList[$counter]['voucher_sequence']-$range_array[$last_range_arr]['s'])+1;

                            $range_array[$last_range_arr]['e']=$voucherList[$counter]['voucher_sequence'];
                            $range_array[$last_range_arr]['t']=$total_voucher_last;
                        }
                    } 
                    $counter = $counter+1; 

            }  
            /*foreach ($range_array as $key => $value) {
                 
                if(empty($value['e'])){
                    unset($range_array[$key]);
                }
                 
            }*/
            $range_array = collect($range_array)->filter(function($item,$key) {

                if(!empty($item['e'])){
                    return $item;
                }

            })->values();
            if(count($range_array) ==0){
                $range_array[]=array('s'=>0,'e'=>0,'t'=>0);
            }
            return response()->json([ 'status' => 'success','message' => 'Gift voucher range list','data'=>$range_array ], 200);                                                

    }
 

}
