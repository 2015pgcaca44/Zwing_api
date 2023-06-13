<?php

namespace App\Http\Controllers\Bazaarkolkata;

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
    	$this->store_db_name = 'ginesys';
        $this->ftp_server   = '139.59.37.194';
        $this->ftp_user     = 'bazaar_kolkata';
        $this->ftp_password = 'bazaar_kolkata@ftp';
        $this->date         = date('d-m-Y');
        $this->dateConvert  = date('Y-m-d',strtotime($this->date));
        $this->vendor_id    = 13;
        $this->ftp_name 	= 'bazaar_kolkata';
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


   public function inbound_api(Request $request)
    {
        die;
         
        $v_id = $request->v_id;
        // $store_id = $request->store_id;

        $current_date = date('Y-m-d');
        if($request->has('invoice_date')){
            $current_date = $request->invoice_date;
        }
        
        if ($request->has('store_id')) {
            $invoices = Invoice::where(['v_id' => $v_id, 'store_id' => $request->store_id, 'date' => $current_date])->get();
        } else {
            $invoices = Invoice::where(['v_id' => $v_id, 'date' => $current_date])->orderBy('id','desc')->get();
        }
        
        // dd($invoices);
        $inbound_arr = [];
        $invoiceArr =[];

        foreach($invoices as $invoice){

            // $unique

            $check_sync = InvoicePush::where('invoice_no', $invoice->invoice_id)->where('v_id', $invoice->v_id)->where('store_id', $invoice->store_id)->latest()->first();

            if (!empty($check_sync)) {
                
                if ($check_sync->status == 0 || $check_sync->status == '') {
                    $customer = User::where('c_id', $invoice->user_id)->first();
                    $custAdd = Address::where('c_id', $invoice->user_id)->first();
                    
                    $invoiceArr['GDSEntityId'] = 'ZW001';
                    $invoiceArr['invoiceCreationDate'] = str_replace('','T',$invoice->created_at);
                    $invoiceArr['updatedDate'] = str_replace('','T',$invoice->updated_at);
                    $invoiceArr['invoiceNo'] = $invoice->invoice_id;
                    $invoiceArr['channelCode'] = 'QB001';
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
                    $invoiceArr['IntegrationUniqueId']= date('Ymd', strtotime($invoice->date)).$invoice->id.$invoice->store->mapping_store_id;
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
                    $invoiceArr['customerState']= $invoice->store->state;
                    $invoiceArr['customerPincode']= $invoice->store->pincode;
                    $invoiceArr['customerGSTIN']= '';
                    $invoiceArr['orderOriginStoreCode']= '';
                    $invoiceArr['InvoiceDetail'] = $this->get_data_from_order_details($invoice);
                    $inbound_arr[] = $invoiceArr;
                }

            } else {
                    $customer = User::where('c_id', $invoice->user_id)->first();
                    $custAdd = Address::where('c_id', $invoice->user_id)->first();

                    $invoiceArr['GDSEntityId'] = 'ZW001';
                    $invoiceArr['invoiceCreationDate'] = str_replace('','T',$invoice->created_at);
                    $invoiceArr['updatedDate'] = str_replace('','T',$invoice->updated_at);
                    $invoiceArr['invoiceNo'] = $invoice->invoice_id;
                    $invoiceArr['channelCode'] = 'QB001';
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
                    $invoiceArr['IntegrationUniqueId']= date('Ymd', strtotime($invoice->date)).$invoice->id.$invoice->store->mapping_store_id;
                    $invoiceArr['customerMobileNo']= $customer->mobile;
                    $invoiceArr['customerFirstName']= $customer->first_name;
                    $invoiceArr['customerMiddleName']= null;
                    $invoiceArr['customerLastName']= $customer->last_name;
                    $invoiceArr['customerGender']= 'M';
                    $invoiceArr['customerEmail']= $customer->email;
                    $invoiceArr['customerAddress1']= (isset($custAdd))?$custAdd->address1:'mPos Address';
                    $invoiceArr['customerAddress2']= (isset($custAdd))?$custAdd->address2:'';
                    $invoiceArr['customerAddress3']= '';
                    $invoiceArr['customerCity']= (isset($custAdd->city))?$custAdd->city:'mPos City';
                    $invoiceArr['customerDistrict']= '';
                    $invoiceArr['customerState']= $invoice->store->state;
                    $invoiceArr['customerPincode']= (isset($custAdd->pincode))?$custAdd->pincode == ''?'mPos Pincode':$custAdd->pincode:'mPos Pincode';
                    $invoiceArr['customerGSTIN']= '';
                    $invoiceArr['orderOriginStoreCode']= '';
                    $invoiceArr['InvoiceDetail'] = $this->get_data_from_order_details($invoice);
                    $inbound_arr[] = $invoiceArr;
            }

        }

        $data_string = json_encode($inbound_arr);

        //print_r($data_string);                                                                                                         
        /*$ch = curl_init('http://10.0.0.7/WebAPI/api/GDS/CreateInvoice');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                     
            'AuthKey: XCdkZT/ZhQmHfViM116dXzKUv+KdnSh0U3CPfAWAEIhAtPHrp2BZUyng/tLY8ozgwlvr/YC4iL/+7xHOSQkEBw==',
            'Content-Type: application/json',                                         
            'Content-Length: ' . strlen($data_string)) 
        );                                                                                                                   
                                                                                                                             
        $result = curl_exec($ch);
        if (curl_error($ch)) {
         $error_msg = curl_error($ch);
        }
        print_r($error_msg);
        echo $result;die;
        
        return response()->json(['status' => 'success', 'data' =>  $inbound_arr  ,'curl_result' => $result ],200);*/

        return response()->json(['status' => 'success', 'data' =>  $inbound_arr ],200);
    
    }


}