<?php

namespace App\Http\Controllers\NurturingGreen;

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

class DataPushApiController extends Controller
{

    public function __construct(){
        $this->ftp_server = "139.59.37.194";
        $this->date       = date('d-m-Y');
    }

    public function inbound_api(Request $request)
    {
        // dd($request->all());
        $v_id = $request->v_id;
        // $store_id = $request->store_id;

        $current_date = date('Y-m-d');
        if($request->has('invoice_date')){
            $current_date = $request->invoice_date;
        }
        
        if ($request->has('store_id')) {
            $invoices = Invoice::where(['v_id' => $v_id, 'store_id' => $request->store_id, 'date' => $current_date])->skip(1)->take(1)->get();
        } else {
            $invoices = Invoice::where(['v_id' => $v_id, 'date' => $current_date])->skip(1)->take(1)->get();
        }
        

        // dd($orders);
        $inbound_arr = [];
        $invoiceArr =[];

        foreach($invoices as $invoice){

            // $unique

            $check_sync = InvoicePush::where('invoice_no', $invoice->order_id)->where('v_id', $invoice->v_id)->where('store_id', $invoice->store_id)->latest()->first();

            if (!empty($check_sync)) {
                
                if ($check_sync->status == 0 || $check_sync->status == '') {
                    $customer = User::where('c_id', $invoice->user_id)->first();
                    $custAdd = Address::where('c_id', $invoice->user_id)->first();
                    
                    $invoiceArr['GDSEntityId'] = 'ENT01';
                    $invoiceArr['invoiceCreationDate'] = str_replace('','T',$invoice->created_at);
                    $invoiceArr['updatedDate'] = str_replace('','T',$invoice->updated_at);
                    $invoiceArr['invoiceNo'] = $invoice->invoice_id;
                    $invoiceArr['channelCode'] = 'M11';
                    $invoiceArr['orderLocation'] = $invoice->store->mapping_store_id;
                    $invoiceArr['stockPointName'] = null;
                    $invoiceArr['remarks'] = '';
                    $invoiceArr['roundOff'] = null;
                    $invoiceArr['tradeType'] = 'Local';
                    $invoiceArr['IntegrationRefNo']= null;
                    $invoiceArr['IntegrationRef1'] =null;
                    $invoiceArr['IntegrationRef2']= null;
                    $invoiceArr['IntegrationRef3']= null;
                    $invoiceArr['IntegrationRef4']= null;
                    $invoiceArr['IntegrationUniqueId']= $invoice->id;
                    $invoiceArr['customerMobileNo']= $customer->mobile;
                    $invoiceArr['customerFirstName']= $customer->first_name;
                    $invoiceArr['customerMiddleName']= null;
                    $invoiceArr['customerLastName']= $customer->last_name;
                    $invoiceArr['customerGender']= 'M';
                    $invoiceArr['customerEmail']= $customer->email;
                    $invoiceArr['customerAddress1']= 'mPos Address';
                    $invoiceArr['customerAddress2']= (isset($custAdd))?$custAdd->address2:'';
                    $invoiceArr['customerAddress3']= '';
                    $invoiceArr['customerCity']= 'mPos City';
                    $invoiceArr['customerDistrict']= '';
                    $invoiceArr['customerState']= 'mPos State';
                    $invoiceArr['customerPincode']= $invoice->store->pincode;
                    $invoiceArr['customerGSTIN']= '';
                    $invoiceArr['orderOriginStoreCode']= '';
                    $invoiceArr['InvoiceDetail'] = $this->get_data_from_order_details($invoice);
                    $inbound_arr[] = $invoiceArr;
                }

            } else {
                    $customer = User::where('c_id', $invoice->user_id)->first();
                    $custAdd = Address::where('c_id', $invoice->user_id)->first();

                    $invoiceArr['GDSEntityId'] = 'ENT01';
                    $invoiceArr['invoiceCreationDate'] = str_replace('','T',$invoice->created_at);
                    $invoiceArr['updatedDate'] = str_replace('','T',$invoice->updated_at);
                    $invoiceArr['invoiceNo'] = $invoice->invoice_id;
                    $invoiceArr['channelCode'] = 'M11';
                    $invoiceArr['orderLocation'] = $invoice->store->mapping_store_id;
                    $invoiceArr['stockPointName'] = null;
                    $invoiceArr['remarks'] = '';
                    $invoiceArr['roundOff'] = null;
                    $invoiceArr['tradeType'] = 'Local';
                    $invoiceArr['IntegrationRefNo']= null;
                    $invoiceArr['IntegrationRef1'] =null;
                    $invoiceArr['IntegrationRef2']= null;
                    $invoiceArr['IntegrationRef3']= null;
                    $invoiceArr['IntegrationRef4']= null;
                    $invoiceArr['IntegrationUniqueId']= date('Ymd', strtotime($invoice->date)).$invoice->store->mapping_store_id;
                    $invoiceArr['customerMobileNo']= $customer->mobile;
                    $invoiceArr['customerFirstName']= $customer->first_name;
                    $invoiceArr['customerMiddleName']= null;
                    $invoiceArr['customerLastName']= $customer->last_name;
                    $invoiceArr['customerGender']= 'M';
                    $invoiceArr['customerEmail']= $customer->email;
                    $invoiceArr['customerAddress1']= (isset($custAdd))?$custAdd->address1:'mPos Address';
                    $invoiceArr['customerAddress2']= (isset($custAdd))?$custAdd->address2:'';
                    $invoiceArr['customerAddress3']= '';
                    $invoiceArr['customerCity']= (isset($custAdd))?$custAdd->city:'mPos City';
                    $invoiceArr['customerDistrict']= '';
                    $invoiceArr['customerState']= (isset($custAdd))?$custAdd->state:'mPos State';
                    $invoiceArr['customerPincode']= (isset($custAdd))?$custAdd->pincode:'mPos Pincode';
                    $invoiceArr['customerGSTIN']= '';
                    $invoiceArr['orderOriginStoreCode']= '';
                    $invoiceArr['InvoiceDetail'] = $this->get_data_from_order_details($invoice);
                    $inbound_arr[] = $invoiceArr;
            }

        }

        $data_string = json_encode($inbound_arr);

        // print_r($data_string);                                                                                                         
        // $ch = curl_init('http://10.0.0.7/WebAPI/api/GDS/CreateInvoice');
        // curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        // curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                     
        //     'AuthKey: u8y+DKadFZ0/6LtUCDyUwLoY1fhdX6FfhFWJStOP0gW9F+0Qqx1T9YVqCGMJDZULPXi1mmJmJ1srXodQE8vJrw==',
        //     'Content-Type: application/json',                                         
        //     'Content-Length: ' . strlen($data_string)) 
        // );                                                                                                                   
                                                                                                                             
        // $result = curl_exec($ch);
        
        // return response()->json(['status' => 'success', 'data' =>  $inbound_arr  ,'curl_result' => $result ],200);

        return response()->json(['status' => 'success', 'data' =>  $inbound_arr ],200);
    }

    public function get_data_from_order_details($values)
    {
        // dd($values);
        $carts = InvoiceDetails::select('item_id','qty','unit_mrp','discount','total','tdata')->where(['v_id' => $values->v_id, 'store_id' => $values->store_id, 'user_id' => $values->user_id, 't_order_id' => $values->id])->get();
        // dd($carts);
            $invoice = [];

            $cgst_amount = 0;
            $sgst_amount = 0;

            foreach ($carts as $key => $cart) {

                $tax_data = json_decode($cart->tdata);
                // $item = DB::table('vmart.invitem')->select('INVHSNSACMAIN_CODE')->where('ICODE', $cart->item_id)->first();
                // $taxs = $this->taxCal($cart->item_id, $cart->qty, $cart->total, $item->INVHSNSACMAIN_CODE);

                // dd($taxs);

                $cartArr['itemCode']= strtoupper($cart->item_id);
                $cartArr['qty']= $cart->qty;
                $cartArr['mrp']= $cart->unit_mrp;
                $cartArr['Rate']= $cart->unit_mrp;
                $cartArr['remarks']= '';
                $cartArr['discountAmt']= $cart->discount;
                $cartArr['extraChgAmt']= 0;
                $cartArr['shippingCharges']= 0;
                $cartArr['giftWrapCharges']= 0;
                $cartArr['CODCharges']= 0;
                $cartArr['netAmt']= $cart->total;
                $cartArr['hsnCode']= $tax_data->hsn;
                $cartArr['taxableAmt']= number_format($tax_data->taxable, 2);
                $cartArr['CGSTPercent']= $tax_data->cgst;
                $cartArr['SGSTPercent']= $tax_data->sgst;
                $cartArr['IGSTPercent']= 0;
                $cartArr['CESSPercent']= $tax_data->cess;
                $cartArr['CGSTAmt']= number_format($tax_data->cgstamt, 2);
                $cartArr['SGSTAmt']= number_format($tax_data->cgstamt, 2);
                $cartArr['IGSTAmt']= 0;
                $cartArr['CESSAmt']= number_format($tax_data->cessamt, 2);
                $cartArr['IntegrationDetRef1']= '';
                $cartArr['IntegrationDetRef2']= '';
                $cartArr['IntegrationDetRef3']= '';
                $cartArr['IntegrationDetRef4']= '';


                $invoice[] = $cartArr;
            }

            return $invoice;
    }   

    public function dataSync()
    {
        dispatch(new ItemFetch);
    }

    public function InvItemNewSync()
    {
        $date = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path = 'files/item/new/'.$date.'/';
        $local_path = '/home/vmart/files/item/new/'.$date.'/';
        $ftp_conn = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist = ftp_nlist($ftp_conn, $path);

        // $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "/var/www/filedump/vmart/files/item/invitem-1.csv" INTO TABLE ginesys.invitem FIELDS TERMINATED BY "|"');

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GIC')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get Item Created',
                    'event_short_name'  => 'GIC',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.invitem FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    }

    public function InvItemUpdateSync()
    {
        $date = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path = 'files/item/update/'.$date.'/';
        $local_path = '/home/vmart/files/item/update/'.$date.'/';
        $ftp_conn = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist = ftp_nlist($ftp_conn, $path);

        // $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "/var/www/filedump/vmart/files/item/invitem-1.csv" INTO TABLE ginesys.invitem FIELDS TERMINATED BY "|"');

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GIM')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get Item Updated',
                    'event_short_name'  => 'GIM',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.invitem FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    }


    public function InvArticleNewSync(){
        $date       = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path       = 'files/article/new/'.$date.'/';
        $local_path = '/home/vmart/files/article/new/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist   = ftp_nlist($ftp_conn, $path);


        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GAC')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get Article Created',
                    'event_short_name'  => 'GAC',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.invarticle FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    }

    public function InvArticleUpdateSync(){

        $date       = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path       = 'files/article/update/'.$date.'/';
        $local_path = '/home/vmart/files/article/update/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist   = ftp_nlist($ftp_conn, $path);


        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GAM')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get Article Updated',
                    'event_short_name'  => 'GAM',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.invarticle FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function InvGroupSync(){

        $date       = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path       = 'files/group/'.$date.'/';
        $local_path = '/home/vmart/files/group/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist   = ftp_nlist($ftp_conn, $path);


        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GGC')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get Group Created',
                    'event_short_name'  => 'GGC',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.invgrp FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    }

    public function InvHsnsadetNewSync(){

        $date       = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path       = 'files/hsndet/new/'.$date.'/';
        $local_path = '/home/vmart/files/hsndet/new/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist   = ftp_nlist($ftp_conn, $path);


        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GHDC')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get HSNDET Created',
                    'event_short_name'  => 'GHDC',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.invhsnsacdet FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function InvHsnsadetUpdateSync(){

        $date       = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path       = 'files/hsndet/update/'.$date.'/';
        $local_path = '/home/vmart/files/hsndet/update/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist   = ftp_nlist($ftp_conn, $path);


        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GHDM')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get HSNDET Updated',
                    'event_short_name'  => 'GHDM',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.invhsnsacdet FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function InvHsnsacmainNewSync(){

        $date       = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path       = 'files/hsnmain/new/'.$date.'/';
        $local_path = '/home/vmart/files/hsnmain/new/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist   = ftp_nlist($ftp_conn, $path);


        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GHMC')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get HSNMAIN Created',
                    'event_short_name'  => 'GHMC',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.invhsnsacmain FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function InvHsnsacmainUpdateSync(){

        $date       = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path       = 'files/hsnmain/update/'.$date.'/';
        $local_path = '/home/vmart/files/hsnmain/update/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist   = ftp_nlist($ftp_conn, $path);


        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GHMM')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get HSNMAIN Updated',
                    'event_short_name'  => 'GHMM',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.invhsnsacmain FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function InvHsnsaclabNewSync(){

        $date       = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path       = 'files/hsnslab/new/'.$date.'/';
        $local_path = '/home/vmart/files/hsnslab/new/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GHSC')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get HSNSLAB Created',
                    'event_short_name'  => 'GHSC',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.invhsnsacslab FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function InvHsnsaclabUpdateSync(){

        $date       = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path       = 'files/hsnslab/update/'.$date.'/';
        $local_path = '/home/vmart/files/hsnslab/update/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GHSM')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get HSNSLAB Updated',
                    'event_short_name'  => 'GHSM',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.invhsnsacslab FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function PromoAssortmentSync(){

        $date       = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path       = 'files/PromoAssortment/'.$date.'/';
        $local_path = '/home/vmart/files/PromoAssortment/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPAMC')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get PROMOASSORTMENT Created',
                    'event_short_name'  => 'GPAMC',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.promo_assortment FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function PromoAssortmentExcludeSync(){



        $date       = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path       = 'files/PromoAssortment/Exclude/'.$date.'/';
        $local_path = '/home/vmart/files/PromoAssortment/Exclude/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPAMEC')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                //fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get PROMOASSORTMENT EXCLUDE Created',
                    'event_short_name'  => 'GPAMEC',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.promo_assortment_exclude FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    

    }

    public function PromoAssortmentIncludeSync(){

        $date       = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path       = 'files/PromoAssortment/Include/'.$date.'/';
        $local_path = '/home/vmart/files/PromoAssortment/Include/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPAMIC')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                //fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get PROMOASSORTMENTINCLUDE Created',
                    'event_short_name'  => 'GPAMIC',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.promo_assortment_include FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function PromoBuyNewSync(){

        $date       = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path       = 'files/PromoBuy/new/'.$date.'/';
        $local_path = '/home/vmart/files/PromoBuy/new/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPBC')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get PROMObUY Created',
                    'event_short_name'  => 'GPBC',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.promo_buy FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function PromoBuyUpdateSync(){

        $date       = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path       = 'files/PromoBuy/update/'.$date.'/';
        $local_path = '/home/vmart/files/PromoBuy/update/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPBM')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get PROMObUY Updated',
                    'event_short_name'  => 'GPBM',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.promo_buy FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function PromoMasterNewSync(){

        $date       = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path       = 'files/PromoMaster/new/'.$date.'/';
        $local_path = '/home/vmart/files/PromoMaster/new/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPMC')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get PROMOMASTER Created',
                    'event_short_name'  => 'GPMC',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.promo_master FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function PromoMasterUpdateSync(){

        $date       = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path       = 'files/PromoMaster/update/'.$date.'/';
        $local_path = '/home/vmart/files/PromoMaster/update/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPMM')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get PROMOMASTER Updated',
                    'event_short_name'  => 'GPMM',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.promo_master FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function PromoSlabNewSync(){

        $date       = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path       = 'files/promoslab/new/'.$date.'/';
        $local_path = '/home/vmart/files/promoslab/new/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPSC')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get PROMOSLAB Created',
                    'event_short_name'  => 'GPSC',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.promo_slab FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    
    }

    public function PromoSlabUpdateSync(){

        $date       = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path       = 'files/promoslab/update/'.$date.'/';
        $local_path = '/home/vmart/files/promoslab/update/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPSM')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get PROMOSLAB Updated',
                    'event_short_name'  => 'GPSM',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.promo_slab FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function PsitePromoAssignNewSync(){

        $date       = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path       = 'files/PromoAssign/new/'.$date.'/';
        $local_path = '/home/vmart/files/PromoAssign/new/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPAC')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get PROMOASSIGN Created',
                    'event_short_name'  => 'GPAC',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.psite_promo_assign FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function PsitePromoAssignUpdateSync(){

        $date       = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path       = 'files/PromoAssign/update/'.$date.'/';
        $local_path = '/home/vmart/files/PromoAssign/update/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, 'vmart', 'v$mart1234');
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPAM')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://vmart:v$mart1234@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                SyncReports::create([
                    'vendor_id'         => 3,
                    'event_name'        => 'Get PROMOASSIGN Updated',
                    'event_short_name'  => 'GPAM',
                    'file_name'         => (string)$filename,
                    'number_of_entry'   => count($fp),
                    'upload_date'       => date('Y-m-d H:i:s'),
                    'created_at'        => date('Y-m-d H:i:s')
                ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE ginesys.psite_promo_assign FIELDS TERMINATED BY "," IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    
    }


}
