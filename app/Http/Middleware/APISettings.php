<?php

namespace App\Http\Middleware;

use DB;
use App\Vendor;
use Closure;
use Auth;
use App\VendorSetting;
use App\StoreSettings;
use App\DaySettlement;
// use Illuminate\Support\Facades\Artisan;

class APISettings
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {       
      // dd($request->getPathInfo());
        // Before Auth Settings

        // if (Auth::guest()) {
        //     dynamicConnection($request->mobile);
        // }  

        // After Auth Settings
      
        if (Auth::check()) {

            if ($request->has('vu_id') && $request->vu_id > 0) {
                $vendor = Vendor::find($request->vu_id);
                $checkLoyality = StoreSettings::where('name', 'loyalty')->where('store_id', $vendor->store_id)->where('status', '1')->first();
                if (!empty($checkLoyality)) {
                    $loyaltyType = json_decode($checkLoyality->settings);
                    $loyaltySettings = $checkLoyality->settings;
                    $request->request->add([ 'loyalty' => true, 'loyaltyType' => key($loyaltyType), 'loyaltySettings' => $loyaltySettings ]);
                }
            }
        }

        // During audit stop transaction 

        if($request->has('store_id') && $request->store_id != null ) {
            $storeId=$request->store_id;
            if(!empty($storeId)) {
                $arrayUrl = $request->segments();
                $filterUrl = ['product-details','add-to-cart','product-qty-update','cart-details','process-to-payment','save-payment','login-for-customer'];
                if(in_array(end($arrayUrl), $filterUrl)) {
                  $storeSettings = StoreSettings::where([ 'store_id' => $storeId, 'name' => '', 'status' => '1' ])->latest()->first();
                  if (!empty($storeSettings)) {
                    $auditSetting = json_decode($storeSettings->settings);
                    if($auditSetting->audit->is_reconciliation == 1) {
                      return response()->json(['status'=> 'stop_inventory_movement', 'message' => "Stock audit is in progress, Inventory movement has been paused temporarily."], 424); 
                    }
                  }
                }
            }
        } 

        // Day Settlement Stop Transaction

        if($request->has('store_id') && $request->store_id != null && $request->getPathInfo() != "/vendor/login" && $request->has('vu_id')) { 
          if(Auth::check()) {
            $userDetails = Vendor::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id, 'id' => $request->vu_id ])->first();
            $period = $userDetails->day_settlement_variance_period;
            $currentDate = date('Y-m-d');
            $daySettlement = DaySettlement::where([ 'v_id' => $request->v_id, 'store_id' => $request->store_id ])->latest()->first();
            if(empty($daySettlement)) {
              $lastDaySettlementtDate = $userDetails->storeData->store_active_date;
            } else {
              $lastDaySettlementtDate = $daySettlement->date;
            }
            $lastDaySettlementtDate = date('Y-m-d', strtotime($lastDaySettlementtDate));
            if(!empty($daySettlement) && ($currentDate == $lastDaySettlementtDate)) {
              return response()->json(['status' => 'fail', 'message' => 'No transactions allowed after the day settlement has been completed.'], 200);
            } else {
              if($period !== '') {
                $maxDay = (int)$period;
                $pendingDay = pendingDaySettlementDay($lastDaySettlementtDate);
                if($pendingDay > $maxDay) {
                  $pendingDay = $pendingDay - 1;
                  return response()->json(['status' => 'fail', 'message' => 'Action disabled as Day Settlement has been pending for over '.$pendingDay.' days.' ], 200);
                }
              }
            }
          }
        }
        
        return $next($request);
    }
}
