<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use App\CustomerLoginLog;
use App\Cart;
use App\Order;
use App\Address;
use App\City;
use App\State;
use App\Country;
use App\Invoice;
use DB;
use App\Http\Controllers\LoyaltyController;
use App\CustomerGroup;
use App\CustomerGroupMapping;
use App\DepRfdTrans;
use App\VendorAuth;
use App\Store;
use App\CrDrSettlementLog;

class CustomerController extends Controller
{

    public function __construct()
    {
        // $this->middleware('auth' , ['except' => ['getCustomerGroupList'] ]);
        $this->middleware('auth'); 
    }

    public function profile(Request $request)
    {
         //dd($request->all());
        $mobile = $request->mobile;
        $v_id   = $request->v_id;
        $vu_id   = $request->vu_id;
        $storeData = VendorAuth::select('store_id')->where('id', $vu_id)->where('v_id', $v_id)->first();
        $store_id=$storeData->store_id;
        if ($request->has('vu_id')) {
            $country = Store::select('country')->where('v_id', $v_id)->where('store_id', $store_id)->first();
            $countryId=$country->country;
            $dialCode = Country::select('dial_code')->where('id', $countryId)->first();
            $customerPhoneCode=$dialCode->dial_code;
        }
        $customerPhoneCode=empty($customerPhoneCode)?'':$customerPhoneCode;
        $address = null;
        $address_arr = null;
        if($request->has('c_id') && $request->c_id!=null) {  
            $c_id = $request->c_id;
            $user = User::select('c_id','mobile','first_name','last_name','gender','dob','email','email_active','anniversary_date','gstin','v_id')->where('c_id', $c_id)->where('mobile', $mobile)->where('v_id',$v_id)->first();
            if($user){
                $address = Address::select('address_nickname','address1','address2','city_id','state_id','country_id','pincode')->where('c_id', $user->c_id)->where('deleted_status','0')->first();

                if($address){
                    $address_arr = $this->getAddressArr($address);

                }
            // $accountDetails=$this->getAccountDetails($user);
            }
        } else {
            $user = User::select('c_id','mobile','first_name','last_name','gender','dob','email','email_active','anniversary_date','gstin','v_id')->where('mobile', $mobile)->where('v_id', $request->v_id)->first();
            if($user){
                $address = Address::select('address_nickname','address1','address2','landmark','city_id','state_id','country_id','pincode')->where('c_id', $user->c_id)->where('deleted_status','0')->first();
                if($address){
                    
                    $address_arr = $this->getAddressArr($address);
                }
                // $accountDetails=$this->getAccountDetails($user);
            } else {
                $user = new User;
                $user->mobile    = $request->mobile;
                $user->v_id    = $request->v_id;
                if($request->trans_from == 'CLOUD_TAB_WEB') {
                    $user->first_name = 'wPos';
                } else if($request->trans_from == 'ANDROID_VENDOR') {
                    $user->first_name = 'mPos';
                }
                $user->last_name = 'Customer';
                $user->customer_phone_code = $customerPhoneCode;
                $user->save();
                // $user = User::create(
                // [
                //     'mobile', $request->mobile,
                //     'v_id', $request->v_id
                // ]);
                // if ($request->has('loyalty')) {
                //     $loyaltyPrams = [ 'type' => 'easeMyRetail', 'event' => 'getCustomerInfo', 'mobile' => $mobile, 'vu_id' => $request->vu_id ];
                //     $loyaltyCon = new LoyaltyController;
                //     $loyaltyResponse = $loyaltyCon->index($loyaltyPrams);
                //     if($loyaltyResponse->response['event'] == 'not_create') {

                //     } elseif($loyaltyResponse->response['event'] == 'create_account') {
                //         // dd($loyaltyResponse);
                //     }
                // }
            }
        }

        $summary=[];

        // $customer = User::where('c_id',$user->c_id)->where('v_id',$request->v_id)->first();
        // $customer_bal = 0;
        // if($customer){
        //     foreach ($customer->groups as $custerGroup) {
        //         if($custerGroup->allow_credit == '1'){
        //             $allow_credit   = $custerGroup->allow_credit;
        //             $maxCreditLimit = $custerGroup->maximum_credit_limit;
        //         }
        //        $group_name =  $custerGroup->name;
        //     }
        //     if($allow_credit == '1'){
        //         /*'src_store_id'=>$store_id*/
        //     $previousDebitAmount = DepRfdTrans::where(['v_id'=>$request->v_id,'user_id'=>$user->c_id])->select(DB::raw("SUM(dep_rfd_trans.amount) as amount"))->where('status','Success')->first();
        //     $customer_bal    = $previousDebitAmount->amount;
        //     $maxCreditLimit  = $maxCreditLimit+$previousDebitAmount->amount;
        //     }
        // }
        if($user){
            $maxCreditLimit=0;
            $customerGroups = [];
            // dd($user->groups);
            foreach ($user->groups as $group => $code) {
                $maxCreditLimit = $code->maximum_credit_limit;
                array_push($customerGroups, $code->code);
            }
            /*'src_store_id'=>$request->store_id*/
            $on_account_bal = DepRfdTrans::where(['v_id'=>$request->v_id,'user_id'=>$user->c_id])->select(DB::raw("SUM(dep_rfd_trans.amount) as amount"))->where('status','Success')->first()->amount;


            ### Note:
            ### Amount_due  = total_non_setteled_debit_note - total_non_setteled_credit_note
            ### Max_limit   = total_limit-total_non_setteled_debit_note
            ### total_spent = values(total amount) of all invoices
            ### no_of_bills = Number of all invoices till date
            ### on_account  = Values of debit note in all the invoices

            
            $total_non_setteled_debit_note = DepRfdTrans::where(['v_id'=>$request->v_id,'user_id'=>$user->c_id])->select(DB::raw("SUM(dep_rfd_trans.amount) as amount"))
                ->where('status','Success')
                ->whereIn('dep_rfd_trans.trans_sub_type',['Debit-Note','Deposit-DN'])
                ->first()->amount;
 
            // $total_non_setteled_credit_note  = DepRfdTrans::join('cr_dr_voucher','cr_dr_voucher.dep_ref_trans_ref','dep_rfd_trans.id')
            //     ->join('cr_dr_settlement_log','cr_dr_settlement_log.voucher_id','cr_dr_voucher.id')
            //     ->where('dep_rfd_trans.v_id',$request->v_id)
            //     ->where('dep_rfd_trans.user_id',$user->c_id)
            //     ->whereIn('dep_rfd_trans.trans_sub_type',['Credit-Note'])
            //     ->where('cr_dr_settlement_log.status','APPLIED')
            //     ->select(DB::raw("SUM(cr_dr_settlement_log.applied_amount) as amount"))
            //     ->first()->amount;


            // $amount_due = $total_non_setteled_credit_note+$total_non_setteled_debit_note;


            
       
        /*
          $total_non_setteled_debit_note = DepRfdTrans::where(['dep_rfd_trans.v_id'=>$request->v_id,'dep_ref_trans_ref.user_id'=>$user->c_id])
                ->join('cr_dr_voucher','cr_dr_voucher.dep_ref_trans_ref','dep_rfd_trans.id')
                ->select(DB::raw("SUM(dep_rfd_trans.amount) as amount"))
                ->whereIn('cr_dr_voucher.status',['Partially settled','Completed'])
                ->whereIn('dep_rfd_trans.trans_sub_type',['Debit-Note'])
                ->first()->amount;
 
            $total_non_setteled_credit_note  = DepRfdTrans::join('cr_dr_voucher','cr_dr_voucher.dep_ref_trans_ref','dep_rfd_trans.id')
                ->join('cr_dr_settlement_log','cr_dr_settlement_log.voucher_id','cr_dr_voucher.id')
                ->whereIn('cr_dr_voucher.status',['Partially settled','Completed'])
                ->where('dep_rfd_trans.v_id',$request->v_id)
                ->where('dep_rfd_trans.user_id',$user->c_id)
                ->whereIn('dep_rfd_trans.trans_sub_type',['Credit-Note'])
                ->where('cr_dr_settlement_log.status','APPLIED')
                ->select(DB::raw("SUM(cr_dr_settlement_log.applied_amount) as amount"))
                ->first()->amount;

            $amount_due = $total_non_setteled_credit_note+$total_non_setteled_debit_note;
            */
            $amount_due = CrDrSettlementLog::where('v_id', $request->v_id)->where('user_id', $user->c_id)->where('status', 'APPLIED')->sum('applied_amount');





            // $maxCreditLimit = $maxCreditLimit+$on_account_bal;

            // $previousDebitAmount = DepRfdTrans::where(['v_id'=>$request->v_id,'user_id'=>$user->c_id])->select(DB::raw("SUM(dep_rfd_trans.amount) as amount"))->where('status','Success')->first();
            // $customer_bal    = $previousDebitAmount->amount;
            // $maxCreditLimit  = $maxCreditLimit+$previousDebitAmount->amount;
            // $maxCreditLimit  = $maxCreditLimit;

            $user->groups = $customerGroups;
            $summary['amount_due'] = $amount_due;
            $summary['total_spent'] = format_number($user->invoices()->sum('total'));
            $summary['no_of_bills'] = $user->invoices()->count();
            $summary['compleleted_sales'] = format_number($user->invoices()->where('transaction_type', 'sales')->sum('total'));
            $summary['compleleted_sales_total'] = $user->invoices()->where('transaction_type', 'sales')->count();
            $summary['no_of_returns'] = $user->invoices()->where('transaction_type', 'return')->count();
            $summary['total_returns'] = format_number($user->invoices()->where('transaction_type', 'return')->sum('total'));
            $summary['layby'] = format_number($user->invoices()->where('transaction_type', 'layby')->sum('total'));
            $summary['layby_count'] = $user->invoices()->where('transaction_type', 'layby')->count();
            $summary['loyalty'] = '0.00';
            $summary['store_credit_unused'] = format_number($user->vouchers->whereIn('status',array('unused','Pending'))->sum('amount'));
            $summary['store_credit_used'] = format_number($user->vouchers->where('status','used')->sum('amount'));
            $summary['total_store_credit'] = format_number($user->vouchers->whereIn('status',array('unused','Pending'))->sum('amount')) + format_number($user->vouchers->where('status','used')->sum('amount'));
            //$summary['on_account'] = $on_account_bal == null ? '0.00' : $on_account_bal;
            $summary['on_account'] = abs($total_non_setteled_debit_note);


            // $summary['on_account'] = format_number($user->invoices()->sum('total'));

            //echo abs($total_non_setteled_debit_note);die;
        $summary['max_limit'] = format_number($maxCreditLimit + $total_non_setteled_debit_note);
        
        $summary['no_of_on_account'] = DepRfdTrans::where(['v_id'=>$request->v_id,'user_id'=>$user->c_id])->select(DB::raw("SUM(dep_rfd_trans.amount) as amount"))
            ->whereIn('dep_rfd_trans.trans_sub_type',['Debit-Note'])
            ->where('status','Success')->count();

            $user->unsetRelation('groups')->unsetRelation('invoices')->unsetRelation('vouchers');
        }
        $userPhoneCode = User::select('customer_phone_code')->where('c_id', $user->c_id)->first();
        if(empty($userPhoneCode->customer_phone_code)){
                User::find($user->c_id)->update(['customer_phone_code' => $customerPhoneCode ]); 
        }
        return response()->json(['status' => 'profile_data', 'message' => 'Profile Data', 'data' => $user, 'address' => $address_arr, 'summary' => $summary,'customerPhoneCode'=>$customerPhoneCode], 200);
    }
    
    
    public function log($param){

        $log  = new CustomerLoginLog;
        $log->latitude = $param['latitude'];
        $log->longitude = $param['longitude'];
        $log->user_id = $param['c_id'];

        $mapLoc = new MapLocationController;
        $response = $mapLoc->addressBylatLongArray($param['latitude'] , $param['longitude']);
        if(!empty($response) && $response['status'] !='fail'){
            $log->locality = $response['data']['locality'];
            $log->address = $response['data']['address'];
        }

        $log->save();

    }

    public function getAddressArr($address){
        $city_name = '';
        $state_name = '';
        $country_name = '';
        $city = City::select('name')->where('id',$address->city_id)->first();
        if($city){
           $city_name = $city->name; 
        }
        $state = State::select('name')->where('id',$address->state_id)->first();
        if($state){
           $state_name = $state->name; 
        }
        $country = Country::select('name')->where('id',$address->country_id)->first();
        if($country){
           $country_name = $country->name; 
        }

        
        $address_arr = [
            'address_nickname' => $address->address_nickname ,
            'address1' => $address->address1 ,
            'address2' => $address->address2 ,
            'landmark' => $address->landmark,
            'pincode' => $address->pincode ,
            'city_id' => $address->city_id ,
            'state_id' => $address->state_id ,
            'country_id' => $address->country_id ,
            'state_name' => $state_name ,
            'city_name' => $city_name ,
            'country_name' => $country_name 
        ];

        return $address_arr;

    }

  public function getAccountDetails($user){
    
    $total_sales   = Invoice::select(DB::raw('count(user_id) as total_bill,COALESCE(sum(total),0) as total_amount,transaction_type'))->where('v_id',$user->v_id)
                          ->where('user_id',$user->c_id)
                          ->groupBy('user_id')
                          ->get();
    $compleleted_sales   =  $total_sales->where('transaction_type','sales');
    $return_sales        =   $total_sales->where('transaction_type','return');
   
                       
    $data = [];            
      foreach($total_sales as $total_sale){
      
        $data = array('total_spent_till_date'=>$total_sale->total_amount,
                        'total_bill_till_date'=>$total_sale->total_bill);      
      }
     //$data = [];
    //   foreach($compleleted_sales as $compleleted_sale){
      
    //     $data = array('total_amount'=>$compleleted->total_amount,
    //                   'total_bill'=>$compleleted->total_bill);      
    //   }
      

      return $data;                    
  }

  public function getCustomerList(Request $request) 
  {
    $v_id = $request->v_id;
    $trans_from = $request->trans_from;
    $customerList = User::with([ 'groups' => function($q) {
                        $q->where('code', '!=', 'DUMMY');
                    }])
                    ->select('mobile', 'first_name', 'last_name', 'c_id', 'v_id')
                    ->where('v_id', $v_id)
                    ->where('first_name', '!=', 'Dummy');
    if($request->has('search') && $request->search != '') {
        $search = $request->search;
        $customerList = $customerList->where(function ($customerList) use ($search){
            $customerList->orWhere('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%')->orWhere('mobile', 'like', '%'.$search.'%');    
        });
    } else {
        $customerList = $customerList->latest();
    }
    $customerList = $customerList->paginate(15);
    $filterData = $customerList->getCollection();
    $filterData = $filterData->filter(function ($item) use ($trans_from) {
                    if($item->first_name == '' && $item->last_name == '') {
                        if($trans_from == 'CLOUD_TAB_WEB') {
                            $item->first_name = 'wPos';
                            $item->last_name = 'Customer';
                        } else if($trans_from == 'ANDROID_VENDOR') {
                            $item->first_name = 'mPos';
                            $item->last_name = 'Customer';
                        }
                    }
                    if(!empty($item->address)) {
                        if($item->address->city_id != null) {
                            $city = City::select('name')->where('id', $item->address->city_id)->first();
                            if($city){
                                $item->location = $city->name;
                            }else {
                                $item->location = '-';
                            }
                        }
                        if($item->address->state_id != null) {
                            $state = State::select('name')->where('id', $item->address->state_id)->first();
                            if($state){
                                $item->location = $item->location.', '.$state->name;
                            }else{
                                $item->location = $item->location.', ';
                            }
                        }
                        if($item->address->city_id == null && $item->address->state_id == null) {
                            $item->location = '-';
                        }
                        
                    } else {
                        $item->location = '-';
                    }
                    $item->spent = format_number($item->invoices()->sum('total'));
                    $item->bills = $item->invoices()->count();
                    $item->loyalty = '0.00';
                    $item->account = '0.00';
                    $item->store_credit_amount = format_number($item->vouchers->where('status','unused')->sum('amount'));
                    $item->groups = $item->groups->pluck('code');
                    $item->unsetRelation('address')->unsetRelation('vouchers')->unsetRelation('groups');
                    unset($item->v_id);
                    // unset($item->c_id);
                    return $item;
                  });
    $customerList->setCollection($filterData);

    return response()->json([ 'status' => 'success', 'message' => 'Customer list', 'listing' => $customerList ], 200);
  }

public function getCustomerGroupList(Request $request) {
    
    $v_id = $request->v_id;
    $c_id = $request->user_id;
    if($request->has('v_id') && $request->has('user_id')) {
        $group_list = CustomerGroup::select('customer_groups.id','customer_groups.name')->where('customer_groups.v_id',$request->v_id)->WhereNull('customer_groups.deleted_at')->where('customer_groups.allowed_tagging','1')->get();
            foreach ($group_list as $key => $val) {
                
                $exists_customer = CustomerGroupMapping::where('c_id',$c_id)->where('group_id',$val->id)->exists();
                $val->c_id=$c_id;
                if(empty($exists_customer)){
                    
                    $val->selected=false;
                }else{
                    $val->selected=true;
                }
            
            }
        return response()->json([ 'status' => 'success', 'message' => 'Customer group list', 'customer_group_list' => $group_list ], 200);
    }else{

        return response()->json([ 'status' => 'fail', 'message' => 'Customer id required'], 200);   
    }
  }

  public function updateCustomerGroupList(Request $request) {
    
    $v_id = $request->v_id;
    $c_id = $request->user_id;
    if($request->has('customer_group_list') && $request->has('v_id') && $request->has('user_id')) {

        $groupList = json_decode($request->customer_group_list);
        CustomerGroupMapping::where('c_id', $c_id)->whereNotIn('group_id', [1,2])->delete();
        foreach ($groupList as $key => $value) {
            if(!empty($value->selected) && $value->selected==true){
                $CustomerGroupMapping = new CustomerGroupMapping;
                $CustomerGroupMapping->c_id = $value->c_id;
                $CustomerGroupMapping->group_id = $value->id;
                $CustomerGroupMapping->save();
            }
        }
        return response()->json([ 'status' => 'success', 'message' => 'Customer tag sucessfully into groups'], 200); 
    }else{

        return response()->json([ 'status' => 'fail', 'message' => 'Customer user_id,customer_group_list required'], 200);   
    } 
  }

}
