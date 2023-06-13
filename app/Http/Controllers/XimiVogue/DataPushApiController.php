<?php

namespace App\Http\Controllers\XimiVogue;

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
use App\Invoice;
use App\InvoicePush;
use App\InvoiceDetails;
use App\Http\Controllers\Ginesys\DataPushApiController as Extended_DataPushApi_Controller;

class DataPushApiController extends Extended_DataPushApi_Controller
{

    public function __construct()
    {
        $this->store_db_name = 'ximivogue';
        $this->ftp_server   = '206.189.143.230';
        $this->ftp_user     = 'ximi';
        $this->ftp_password = 'Ximi@2019';
        $this->date         = date('d-m-Y');
        $this->dateConvert  = date('Y-m-d',strtotime($this->date));
        $this->vendor_id    = 23;
        $this->ftp_name = 'ximi';
    }


}