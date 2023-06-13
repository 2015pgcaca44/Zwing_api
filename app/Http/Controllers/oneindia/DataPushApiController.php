<?php

namespace App\Http\Controllers\oneindia;

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
    	$this->store_db_name = 'one_india';
        $this->ftp_server   = '139.59.37.194';
        $this->ftp_user     = 'nyssa';
        $this->ftp_password = 'nyssa@ftp';
        $this->date         = date('d-m-Y');
        $this->dateConvert  = date('Y-m-d',strtotime($this->date));
        $this->vendor_id    = 12;
        $this->ftp_name 	= 'pvmart';
        $this->data = (object)[
                'store_db_name'     => $this->store_db_name,
                'ftp_server'        => $this->ftp_server,
                'ftp_user'          => $this->ftp_user,
                'ftp_password'      => $this->ftp_password,
                'date'              => $this->date,
                'dateConvert'       => $this->dateConvert,
                'vendor_id'         => $this->vendor_id,
                'ftp_name'          => $this->ftp_name,
                'data'              => ''   
            ];
    }


}