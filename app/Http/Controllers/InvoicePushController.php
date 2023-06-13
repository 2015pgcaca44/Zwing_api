<?php

namespace App\Http\Controllers;

use App\Http\Traits\VendorFactoryTrait;
use Illuminate\Http\Request;
use App\InvoicePush;
use App\Invoice;
use App\Store;

class InvoicePushController extends Controller
{
	use VendorFactoryTrait;

    public function __construct()
    {
        $this->date       = date('d-m-Y');
    }

    public function invoicePushStatus(Request $request)
    {
        return $this->callMethod($request, __CLASS__, __METHOD__);
    }   
}
