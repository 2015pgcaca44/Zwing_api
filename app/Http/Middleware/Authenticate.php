<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;
use App\VendorSetting;
use DB;
use App\Vendor;
use App\CashRegister;
use App\DaySettlement;
use App\StoreSettings;

class Authenticate
{
  /**
   * The authentication guard factory instance.
   *
   * @var \Illuminate\Contracts\Auth\Factory
   */
  protected $auth;

  /**
   * Create a new middleware instance.
   *
   * @param  \Illuminate\Contracts\Auth\Factory  $auth
   * @return void
   */
  public function __construct(Auth $auth)
  {
    $this->auth = $auth;
  }

  /**
   * Handle an incoming request.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure  $next
   * @param  string|null  $guard
   * @return mixed
   */
  public function handle($request, Closure $next, $guard = null)
  {
    // Check dynamic database
     //dd($request->all());
    // if(config('database.default') == 'mysql') {
    //     $vUser = Vendor::find($request->vu_id);
    //     dynamicConnection($vUser->mobile);
    // }
    // if($request->v_id==1){
    // if ($request->input('udidtoken') == null) {
    //     return response()->json([ 'status' => 'licence_not_valid', 'message' => 'Terminal has not registered'], 420);
    // }else{
  //dd("ok");
    if ($request->input('trans_from') == 'ANDROID_VENDOR' || $request->input('trans_from') == 'CLOUD_TAB' || $request->input('trans_from') == 'CLOUD_TAB_WEB') {
      if(env('APP_ENV') == "development"){
      if ($request->udidtoken != 'GT6A7lWWdkjCilh9jKtm7Yc9') {
        //dd("ok");
        if ($request->input('udidtoken') == null) {
          return response()->json(['status' => 'licence_not_valid', 'message' => 'This terminal has not been registered.'], 420);
        } else {
          $vendor = Vendor::where('id', $request->vu_id)->where('v_id', $request->v_id)->first();
          //dd(encrypt_decryp('decrypt',$request->udidtoken));
           //DB::enableQueryLog();
          $licence = CashRegister::select('udid', 'udidtoken', 'exp_date')
            ->join('subscriptions', 'cash_registers.id', 'subscriptions.cr_id')
            ->where('cash_registers.v_id', $request->v_id)
            ->where('cash_registers.store_id', $vendor->store_id)
            ->where('cash_registers.udidtoken', $request->udidtoken)
            ->where('cash_registers.is_deleted', '!=', '1')
            ->orderBy('subscriptions.created_at', 'desc')
            ->first();
          //$data = DB::getQueryLog();
          //dd($licence);
          if (empty($licence)) {

            return response()->json(['status' => 'licence_not_valid', 'message' => 'This terminal has not been registered.'], 420);
          }

          $currentdate = date('Y-m-d');

          // if (strtotime($currentdate) > strtotime($licence->exp_date)) {

          //   return response()->json(['status' => 'licence_expired', 'message' => 'Your license has expired. Please contact ZWING support for renewal.'], 420);
          // }
        }
      }
    }else{
      if ($request->input('udidtoken') == null) {
          return response()->json(['status' => 'licence_not_valid', 'message' => 'This terminal has not been registered.'], 420);
        } else {
          $vendor = Vendor::where('id', $request->vu_id)->where('v_id', $request->v_id)->first();

          //dd(encrypt_decryp('decrypt',$request->udidtoken));
           //DB::enableQueryLog();
          $licence = CashRegister::select('udid', 'udidtoken', 'exp_date')
            ->join('subscriptions', 'cash_registers.id', 'subscriptions.cr_id')
            ->where('cash_registers.v_id', $request->v_id)
            ->where('cash_registers.store_id', $vendor->store_id)
            ->where('cash_registers.udidtoken', $request->udidtoken)
            ->where('cash_registers.is_deleted', '!=', '1')
            ->orderBy('subscriptions.created_at', 'desc')
            ->first();
          //$data = DB::getQueryLog();
         //dd($data);
          if (empty($licence)) {

            return response()->json(['status' => 'licence_not_valid', 'message' => 'This terminal has not been registered.'], 420);
          }

          $currentdate = date('Y-m-d');

          // if (strtotime($currentdate) > strtotime($licence->exp_date)) {

          //   return response()->json(['status' => 'licence_expired', 'message' => 'Your license has expired. Please contact ZWING support for renewal.'], 420);
          // }
        }
    }
    }
    // }
   if ($request->input('trans_from') == 'ANDROID_VENDOR' || $request->input('trans_from') == 'CLOUD_TAB' || $request->input('trans_from') == 'CLOUD_TAB_WEB') {
      $vendor = Vendor::where('id', $request->vu_id)->where('v_id', $request->v_id)->first();
          $currentdate = date('Y-m-d');
          $todayDaySettlement=DaySettlement::where('store_id',$vendor->store_id)
                                            ->where('date',$currentdate)
                                            ->first();
         if($todayDaySettlement){
             return response()->json(['status' => 'day_settlement_done', 'message' => "Today's day settlement has been already done.You can't start billing today"], 419);
          }
    }

    if ($this->auth->guard($guard)->guest()) {
      return response()->json(['status' => 'redirect_to_store_list', 'message' => 'Unauthorized' . DB::connection()->getDatabaseName()], 401);
    } elseif ($this->auth->guard($guard)->check()) {
      $arrayUrl = $request->segments();
      $filterUrl = ['product-details','add-to-cart','product-qty-update','cart-details','process-to-payment','save-payment','login-for-customer'];
      if(in_array(end($arrayUrl), $filterUrl)) {
        $storeSettings = StoreSettings::where([ 'store_id' => $request->store_id, 'name' => 'audit', 'status' => '1' ])->latest()->first();
        if (!empty($storeSettings)) {
          $auditSetting = json_decode($storeSettings->settings);
          if($auditSetting->is_reconciliation == 1) {
            return response()->json([ 'status' => 'stop_inventory_movement', 'message' => "Stock audit is processed, Inventory movement has been stopped temporarily."], 420);
          }
        }
      }
      // API Settings Checker
      // $settings = VendorSetting::where('v_id', $request->v_id)->where('name', 'loyalty')->where('status', 1)->first();
      // if (!empty($settings)) {
      //     $request->request->add([ 'loyalty' => true ]);   
      // }
      // dd($request->all());
    }
    return $next($request);
  }
}
