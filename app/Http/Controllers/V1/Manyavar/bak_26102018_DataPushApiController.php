<?php

namespace App\Http\Controllers\Vmart;

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

class DataPushApiController extends Controller
{

    public function __construct(){
        $this->ftp_server = "139.59.37.194";
        $this->date       = date('d-m-Y',strtotime('-1 day'));
    }

    public function inbound_api(Request $request)
    {
        $v_id = $request->v_id;
        $store_id = $request->store_id;

        $randomBarcode = ['G1170','G1169','G1168','G1167','G1166','G1165','G1164','G1163','G1162','G1161','G1160'];
        $randIndex = array_rand($randomBarcode);

        $current_date = ('Y-m-d');
        if($request->has('invoice_date')){
            $current_date = $request->invoice_date;
        }
        
        $orders = Order::where(['v_id' => $v_id, 'store_id' => $store_id, 'date' => $current_date , 'status' => 'success'])->get();


        $inbound_arr = [];
        $orderArr =[];

        foreach($orders as $order){

            $cartArr = [];

            $customer = User::where('c_id', $order->user_id)->first();
            $custAdd = Address::where('c_id', $order->user_id)->first();
            
            $orderArr['invoiceCreationDate'] = str_replace('','T',$order->created_at);
            $orderArr['updatedDate'] = str_replace('','T',$order->updated_at);
            $orderArr['invoiceNo'] = $order->order_id;
            $orderArr['channelCode'] = 'OMUNI';
            $orderArr['orderLocation'] = '19';
            $orderArr['stockPointName'] = null;
            $orderArr['remarks'] = '';
            $orderArr['roundOff'] = null;
            $orderArr['tradeType'] = 'Local';
            $orderArr['IntegrationRefNo']= null;
            $orderArr['IntegrationRef1'] =null;
            $orderArr['IntegrationRef2']= null;
            $orderArr['IntegrationRef3']= null;
            $orderArr['IntegrationRef4']= null;
            $orderArr['IntegrationUniqueId']= 'IntegrationUniqueId';
            $orderArr['customerMobileNo']= $customer->mobile;
            $orderArr['customerFirstName']= $customer->first_name;
            $orderArr['customerMiddleName']= null;
            $orderArr['customerLastName']= $customer->last_name;
            $orderArr['customerGender']= 'M';
            $orderArr['customerEmail']= $customer->email;
            $orderArr['customerAddress1']= (isset($custAdd))?$custAdd->address1:'';
            $orderArr['customerAddress2']= (isset($custAdd))?$custAdd->address2:'';
            $orderArr['customerAddress3']= '';
            $orderArr['customerCity']= (isset($custAdd))?$custAdd->city:'';
            $orderArr['customerDistrict']= '';
            $orderArr['customerState']= (isset($custAdd))?$custAdd->state:'';
            $orderArr['customerPincode']= (isset($custAdd))?$custAdd->pincode:'';
            $orderArr['customerGSTIN']= '';
            $orderArr['orderOriginStoreCode']= '';

            $carts = DB::table('vmart_cart')->select('item_id','qty','unit_mrp','discount','total','tdata')->where(['v_id' => $v_id, 'store_id' => $store_id, 'user_id' => $order->user_id, 'order_id' => $order->o_id , 'status' => 'success'])->get();
            
            foreach ($carts as $key => $cart) {

                $tax_data = json_decode($cart->tdata);

                $cartArr['itemCode']= $randomBarcode[$randIndex];
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
                $cartArr['hsnCode']= $tax_data->tax_details->HSN_SAC_CODE;
                $cartArr['taxableAmt']= $tax_data->wihout_tax_price;
                $cartArr['CGSTPercent']= $tax_data->apply_tax->CGST_RATE;
                $cartArr['SGSTPercent']= $tax_data->apply_tax->SGST_RATE;
                $cartArr['IGSTPercent']= $tax_data->apply_tax->IGST_RATE;
                $cartArr['CESSPercent']= $tax_data->apply_tax->CESS_RATE;
                $cartArr['CGSTAmt']= $tax_data->tax_amount / 2;
                $cartArr['SGSTAmt']= $tax_data->tax_amount / 2;
                $cartArr['IGSTAmt']= $tax_data->tax_amount;
                $cartArr['CESSAmt']= 0;
                $cartArr['IntegrationDetRef1']= '';
                $cartArr['IntegrationDetRef2']= '';
                $cartArr['IntegrationDetRef3']= '';
                $cartArr['IntegrationDetRef4']= '';


                $orderArr['InvoiceDetail'][] = $cartArr;
            }

            $inbound_arr[] = $orderArr;

        }

        $data_string = json_encode($inbound_arr);                                                                                                         
        $ch = curl_init('http://10.0.0.7/WebAPI/api/GDS/CreateInvoice');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                     
            'AuthKey: u8y+DKadFZ0/6LtUCDyUwLoY1fhdX6FfhFWJStOP0gW9F+0Qqx1T9YVqCGMJDZULPXi1mmJmJ1srXodQE8vJrw==',
            'Content-Type: application/json',                                         
            'Content-Length: ' . strlen($data_string)) 
        );                                                                                                                   
                                                                                                                             
        $result = curl_exec($ch);
        
        return response()->json(['status' => 'success', 'data' =>  $inbound_arr  ,'curl_result' => $result ],200);

        //return response()->json(['status' => 'success', 'data' =>  $inbound_arr ],200);
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
					'vendor_id' 		=> 3,
					'event_name'		=> 'Get Item Created',
					'event_short_name'	=> 'GIC',
					'file_name'			=> (string)$filename,
					'number_of_entry'	=> count($fp),
					'upload_date'		=> date('Y-m-d H:i:s'),
					'created_at'		=> date('Y-m-d H:i:s')
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
}
