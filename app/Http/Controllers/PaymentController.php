<?php

namespace App\Http\Controllers;

use App\Http\Controllers\VendorSettingController;
use Illuminate\Http\Request;
use DB;
use App\Payment;
use App\Vendor;
use App\SettlementSession;


class PaymentController extends Controller
{

    public function payment_summary(Request $request){

        $vu_id = $request->vu_id;
        $by = $request->by;

        //$current_date = date('Y-m-d');2018-08-05
        if($by == 'PAYMENT_METHOD'){

            $current_date = '2018-08-04';
            $payments = DB::table('payments as p')
                     ->select(DB::raw('sum(p.amount) as amount, p.method'))
                     ->join('orders as o', 'o.order_id' , 'p.order_id')
                     ->where('o.date', $current_date)
                     ->where('o.vu_id', $vu_id)
                     ->groupBy('p.method')
                     ->get();

            return response()->json(['status' => 'success', 'data' => $payments ], 200);

        }
        
    }
    
}
