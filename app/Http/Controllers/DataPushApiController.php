<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;
use Payment;
use App\Order;
use App\Cart;
use App\Address;
use App\User;
use App\SyncReports;
use App\Jobs\ItemFetch;
use App\Http\Traits\VendorFactoryTrait;

class DataPushApiController extends Controller
{
    use VendorFactoryTrait;

    public function __construct()
    {
        $this->date       = date('d-m-Y');
    }

    public function inbound_api(Request $request)
    {
        return $this->callMethod($request, __CLASS__, __METHOD__);
    } 

    public function dataSync(Request $request)
    {
        return $this->callMethod($request, __CLASS__, __METHOD__);
    }   

}
