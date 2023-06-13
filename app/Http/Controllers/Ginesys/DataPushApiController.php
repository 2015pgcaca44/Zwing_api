<?php

namespace App\Http\Controllers\Ginesys;

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
use Log;
use App\Jobs\Ginesys\Sync\Item;
use App\Jobs\Ginesys\Sync\Article;
use App\Jobs\Ginesys\Sync\Group;
use App\Jobs\Ginesys\Sync\Tax;
use App\Jobs\Ginesys\Sync\Promo;
use App\Jobs\Ginesys\Sync\Assortment;
use App\Jobs\Ginesys\Sync\AssortmentInclude;
use App\Jobs\Ginesys\Sync\AssortmentExclude;
use App\Jobs\Ginesys\Sync\PromoAssign;
use App\Jobs\Ginesys\Sync\Coupon;
use Carbon\Carbon;

class DataPushApiController extends Controller
{
    public function __construct()
    {
        $this->store_db_name = 'ginesys';
        $this->ftp_server   = '';
        $this->ftp_user     = '';
        $this->ftp_password = '';
        $this->date         = date('d-m-Y');
        $this->dateConvert  = date('Y-m-d',strtotime($this->date));
        $this->vendor_id    = 0;
        $this->ftp_name = '';
        $this->data = [];
    }

    public function inbound_api(Request $request)
    {
        
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
                    $invoiceArr['customerCity']= (isset($custAdd))?$custAdd->city:'mPos City';
                    $invoiceArr['customerDistrict']= '';
                    $invoiceArr['customerState']= $invoice->store->state;
                    $invoiceArr['customerPincode']= (isset($custAdd))?$custAdd->pincode:'mPos Pincode';
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

    public function get_data_from_order_details($values)
    {
        // dd($values);
        $carts = InvoiceDetails::select('item_id','qty','unit_mrp','discount','total','tdata','pdata','unit_csp','transaction_type','lpdiscount','coupon_discount','manual_discount')->where(['v_id' => $values->v_id, 'store_id' => $values->store_id, 'user_id' => $values->user_id, 't_order_id' => $values->id])->get();
        // dd($carts);
            $invoice = [];

            $cgst_amount = 0;
           $sgst_amount = 0;
           $taxable = 0;
           $cgstamt = 0;
           $sgstamt = 0;
           // dd($carts);
           // $qty='';
           // $netAmt='';
           // $itemPromoAmt='';
           // $rate = '';

            foreach ($carts as $key => $cart) {

                $tax_data = json_decode($cart->tdata);
                $p_data     = json_decode($cart->pdata,true);
                $p_data   = array_reverse($p_data);

                $pdata      = array();

                $pdata['Promo_code'] = '';
                $pdata['NO']         = '';
                $pdata['PROMO_NAME'] = '';
                $pdata['START_DATE'] = '';
                $pdata['END_DATE']   = '';
                $pdata['DISCOUNT_TYPE']   = '';

                foreach($p_data as $pvalue){
                    if($pvalue['item_id'] == $cart->item_id){
                        $pdata['Promo_code'] = (@$pvalue['promo_code'] == ''?'':$pvalue['promo_code']);
                        $pdata['NO']         = (@$pvalue['no'] == ''?'':$pvalue['no']);
                        $pdata['PROMO_NAME'] = (@$pvalue['message'] == ''?'':$pvalue['message']);  
                        $pdata['START_DATE'] =(@$pvalue['start_date'] == ''?'':date("Y-m-d H:m:i", strtotime($pvalue['start_date'])));    
                        $pdata['END_DATE']   = (@$pvalue['end_date'] == ''?'':date("Y-m-d H:m:i", strtotime($pvalue['end_date'])));  
                        $pdata['DISCOUNT_TYPE'] = (@$pvalue['promo_code'] == ''?'':'P');
                     }

                }

                if (empty($tax_data->tax)) {
                   $taxable = $cart->total;
               } else {
                   $taxable = $tax_data->taxable;
               }

               if (empty($cart->unit_mrp) || $cart->unit_mrp == 0.00 || $cart->unit_mrp == '0.00') {
                   $mrp = $cart->unit_csp;
               } else {
                   $mrp = $cart->unit_mrp;
               }

               if (empty($cart->unit_csp) || $cart->unit_csp == 0.00 || $cart->unit_csp == '0.00') {
                   $rate = $cart->unit_mrp;
               } else {
                   $rate = $cart->unit_csp;
               }

                if ($cart->transaction_type == 'sales') {
                   $qty = $cart->qty;
                   $mrp = $mrp;
                   $rate = $rate;
                   $taxable = $taxable;
                   $cgstamt = $tax_data->cgstamt;
                   $sgstamt = $tax_data->sgstamt;
                   $netAmt = $cart->total;
                   $itemPromoAmt = $cart->discount;
                   $discountAmt = $cart->total_discount;
               } elseif ($cart->transaction_type == 'return') {
                   $qty = -$cart->qty;
                   $mrp = $mrp;
                   $rate = $rate;
                   $taxable = -$taxable;
                   $cgstamt = -$tax_data->cgstamt;
                   $sgstamt = -$tax_data->sgstamt;
                   $netAmt = -$cart->total;
                   $itemPromoAmt = -$cart->discount;
                   $discountAmt = -$cart->total_discount;
               }

                $cartArr['itemCode']= strtoupper($cart->item_id);
                $cartArr['qty']= $qty;
                $cartArr['mrp']= format_number($mrp);
                $cartArr['Rate']= format_number($rate);
                $cartArr['remarks']= '';
                $cartArr['discountAmt']= format_number($discountAmt);
                $cartArr['extraChgAmt']= 0;
                $cartArr['shippingCharges']= 0;
                $cartArr['giftWrapCharges']= 0;
                $cartArr['CODCharges']= 0;
                $cartArr['netAmt']= format_number($netAmt);
                $cartArr['hsnCode']= $tax_data->hsn;
                $cartArr['taxableAmt']= format_number($taxable);
                $cartArr['CGSTPercent']= $tax_data->cgst;
                $cartArr['SGSTPercent']= $tax_data->sgst;
                $cartArr['IGSTPercent']= 0;
                $cartArr['CESSPercent']= $tax_data->cess;
                $cartArr['CGSTAmt']= format_number($cgstamt);
                $cartArr['SGSTAmt']= format_number($sgstamt);
                $cartArr['IGSTAmt']= 0;
                $cartArr['CESSAmt']= format_number($tax_data->cessamt);
                $cartArr['IntegrationDetRef1']= '';
                $cartArr['IntegrationDetRef2']= '';
                $cartArr['IntegrationDetRef3']= '';
                $cartArr['IntegrationDetRef4']= '';
                $cartArr['itemPromoAmt']= format_number($itemPromoAmt);
                $cartArr['itemPromoCode']= $pdata['Promo_code'];
                $cartArr['itemPromoNo']= $pdata['NO'];
                $cartArr['itemPromoName']= $pdata['PROMO_NAME'];
                $cartArr['itemPromoStartDate']= $pdata['START_DATE'];
                $cartArr['itemPromoEndDate']= $pdata['END_DATE'];
                $cartArr['memoDiscountAmt']= '';
                $cartArr['memoDiscountType']= '' ;
                $cartArr['memoPromoCode']= '';
                $cartArr['memoPromoNo']= '';
                $cartArr['memoPromoName']= '';
                $cartArr['memoPromoStartDate']= '';
                $cartArr['memoPromoEndDate']= '';
                $cartArr['memoPromoSlabFrom']= '';
                $cartArr['memoPromoSlabTo']= '';
                $cartArr['memoDiscountDesc']= '';
                $cartArr['memoCouponCode']= '';
                $cartArr['memoCouponOfferCode']= '';


                $invoice[] = $cartArr;
            }

            return $invoice;
    }

    public function dataSync()
    {   

         dispatch(new Item($this->data));die;

        // dd($cool);
        //dispatch(new ItemFetch);
        Log::info('['.$this->store_db_name.'] Init Data Sync Function Vendor ID : -'.$this->vendor_id);
        Log::info('['.$this->store_db_name.'] FTP Server : - '.$this->vendor_id.' ( '.$this->store_db_name.' ) ');

        dispatch(new Item($this->data));

        $article = (new Article($this->data))->delay(Carbon::now()->addMinutes(1));
        dispatch($article);

        // $group = (new Group($this->data))->delay(Carbon::now()->addMinutes(2));
        // dispatch($group);

        $tax = (new Tax($this->data))->delay(Carbon::now()->addMinutes(3));
        dispatch($tax);

        $promo = (new Promo($this->data))->delay(Carbon::now()->addMinutes(4));
        dispatch($promo);

        // $assortment = (new Assortment($this->data))->delay(Carbon::now()->addMinutes(5));
        // dispatch($assortment);

        // $assortmentInclude = (new AssortmentInclude($this->data))->delay(Carbon::now()->addMinutes(7));
        // dispatch($assortmentInclude);

        // $assortmentExclude = (new AssortmentExclude($this->data))->delay(Carbon::now()->addMinutes(10));
        // dispatch($assortmentExclude);

        $psitePromoAssign = (new PromoAssign($this->data))->delay(Carbon::now()->addMinutes(12));
        dispatch($psitePromoAssign);

        // $coupon = (new Coupon($this->data))->delay(Carbon::now()->addMinutes(14));
        // dispatch($coupon);


        Log::info('['.$this->store_db_name.'] End Data Sync Function Vendor ID : -'.$this->vendor_id);
        // Log::info('Data Sync New Invitem Start');
        // $this->InvItemNewSync();
        // Log::info('Data Sync New Invitem End');
        // Log::info('Data Sync Update Invitem Start');
        // $this->InvItemUpdateSync();
        // Log::info('Data Sync Update Invitem End');
        // $this->InvArticleNewSync();
        // // $this->InvArticleNewSync();
        // $this->InvArticleUpdateSync();
        // $this->InvGroupSync();

        // $this->InvHsnsadetNewSync();
        // $this->InvHsnsadetUpdateSync();
        // $this->InvHsnsacmainNewSync();
        // $this->InvHsnsacmainUpdateSync();
        // $this->InvHsnsaclabNewSync();
        // $this->PromoAssortmentSync();
        // $this->PromoAssortmentExcludeSync();
        // $this->PromoAssortmentIncludeSync();

        // $this->PromoBuyNewSync();
        // $this->PromoBuyUpdateSync();
        // $this->PromoMasterNewSync();
        // $this->PromoMasterUpdateSync();

        // $this->PromoSlabNewSync();
        // $this->PromoSlabUpdateSync();
        // $this->PsitePromoAssignNewSync();
        // // // $this->PsitePromoAssignUpdateSync();

        // $this->psiteCouponOffer();
        // $this->psiteCouponAssign();
        // $this->psiteCoupoAssort();

    }

    public function InvItemNewSync($ftpDetails)
    {
        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/item/new/'.$date.'/'; 
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/item/new/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);
        
        foreach ($filelist as $key => $value) {
              //$key." - ".$value;die;
              $filename = str_replace($path, "", $value);
              $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GIC')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();


            if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
                // echo $value;die;
                // $readfilename = 'ftp://vmart:vmart$1234@'.$ftp_server.'/'.$value;

                        $localfilename = $local_path;
                        $handle = fopen($readfilename, "r");  
                        $contents = fread($handle, filesize($readfilename));
                        //dd($contents);
                        $csv = array_map('str_getcsv', file($readfilename));
                        //print_r($csv);die;
                        fclose($handle);
              
                $fp = file($localfilename.$filename);
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get Item Created';
                $sync_reports->event_short_name = 'GIC';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
               //$custom = DB::connection()->getPdo()->exec('select * from zwing_demo.invitem');
               //dd($custom);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.invitem FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    }

    public function InvItemUpdateSync($ftpDetails)
    {
        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/item/update/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/item/update/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);

       

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GIM')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);

                $allkey = collect($csv);

                DB::table($ftpDetails->store_db_name.'.invitem')->whereIn('ICODE',$allkey->pluck('0'))->delete();
                // dd($allkey->pluck('0'));
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get Item Updated';
                $sync_reports->event_short_name = 'GIM';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get Item Updated',
                //     'event_short_name'  => 'GIM',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.invitem FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    }


    public function InvArticleNewSync($ftpDetails)
    {
        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/article/new/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/article/new/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);


        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GAC')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
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
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get Article Created';
                $sync_reports->event_short_name = 'GAC';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get Article Created',
                //     'event_short_name'  => 'GAC',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.invarticle FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    }

    public function InvArticleUpdateSync($ftpDetails)
    {

        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/article/update/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/article/update/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);


        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GAM')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);

                $allkey = collect($csv);

                DB::table($ftpDetails->store_db_name.'.invarticle')->whereIn('CODE',$allkey->pluck('0'))->delete();
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get Article Updated';
                $sync_reports->event_short_name = 'GAM';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get Article Updated',
                //     'event_short_name'  => 'GAM',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.invarticle FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function InvGroupSync($ftpDetails)
    {

        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/group/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/group/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);


        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GGC')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            // if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
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
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get Group Created';
                $sync_reports->event_short_name = 'GGC';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get Group Created',
                //     'event_short_name'  => 'GGC',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                DB::table($ftpDetails->store_db_name.'.invgrp')->truncate();
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.invgrp FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            // }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    }

    public function InvHsnsadetNewSync($ftpDetails)
    {

        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/hsndet/new/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/hsndet/new/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);


        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GHDC')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            // if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);

                $allkey = collect($csv);

                DB::table($ftpDetails->store_db_name.'.invhsnsacdet')->whereIn('CODE',$allkey->pluck('0'))->delete();
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get HSN Details Create';
                $sync_reports->event_short_name = 'GHDC';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get HSN Details Created',
                //     'event_short_name'  => 'GHDC',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.invhsnsacdet FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            // }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function InvHsnsadetUpdateSync($ftpDetails)
    {

        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/hsndet/update/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/hsndet/update/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);


        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GHDM')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            // if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);

                $allkey = collect($csv);

                DB::table($ftpDetails->store_db_name.'.invhsnsacdet')->whereIn('CODE',$allkey->pluck('0'))->delete();
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get HSN Details Updated';
                $sync_reports->event_short_name = 'GHDM';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get HSN Details Updated',
                //     'event_short_name'  => 'GHDM',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.invhsnsacdet FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            // }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function InvHsnsacmainNewSync($ftpDetails)
    {

        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/hsnmain/new/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/hsnmain/new/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);


        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GHMC')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            // if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);

                $allkey = collect($csv);
                DB::table($ftpDetails->store_db_name.'.invhsnsacmain')->whereIn('CODE',$allkey->pluck('0'))->delete();
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get HSN Main Created';
                $sync_reports->event_short_name = 'GHMC';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get HSN Main Created',
                //     'event_short_name'  => 'GHMC',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.invhsnsacmain FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            // }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function InvHsnsacmainUpdateSync($ftpDetails)
    {

        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/hsnmain/update/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/hsnmain/update/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);


        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GHMM')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            // if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);

                $allkey = collect($csv);

                DB::table($ftpDetails->store_db_name.'.invhsnsacmain')->whereIn('CODE',$allkey->pluck('0'))->delete();
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get HSN Main Updated';
                $sync_reports->event_short_name = 'GHMM';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get HSN Main Updated',
                //     'event_short_name'  => 'GHMM',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.invhsnsacmain FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            // }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function InvHsnsaclabNewSync($ftpDetails)
    {

        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/hsnslab/new/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/hsnslab/new/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GHSC')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            // if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);

                $allkey = collect($csv);
                DB::table($ftpDetails->store_db_name.'.invhsnsacslab')->whereIn('CODE',$allkey->pluck('0'))->delete();
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get HSN Slab Created';
                $sync_reports->event_short_name = 'GHSC';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get HSN Slab Created',
                //     'event_short_name'  => 'GHSC',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.invhsnsacslab FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            // }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function InvHsnsaclabUpdateSync($ftpDetails)
    {

        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/hsnslab/update/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/hsnslab/update/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
         $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GHSM')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            // if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);

                $allkey = collect($csv);
                DB::table($ftpDetails->store_db_name.'.invhsnsacslab')->whereIn('CODE',$allkey->pluck('0'))->delete();
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get HSN Slab Updated';
                $sync_reports->event_short_name = 'GHSM';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get HSN Slab Updated',
                //     'event_short_name'  => 'GHSM',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.invhsnsacslab FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            // }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function PromoAssortmentSync($ftpDetails)
    {

        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/PromoAssortment/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/PromoAssortment/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
         $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPAMC')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            // if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
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
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get Promo Assortment Created';
                $sync_reports->event_short_name = 'GPAMC';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get Promo Assortment Created',
                //     'event_short_name'  => 'GPAMC',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                DB::table($ftpDetails->store_db_name.'.promo_assortment_temp')->truncate();
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.promo_assortment_temp FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');

                echo $custom;

        
            // }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function PromoAssortmentExcludeSync($ftpDetails)
    {
 
        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/PromoAssortment/Exclude/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/PromoAssortment/Exclude/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPAMEC')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            // if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                // $handle = fopen($readfilename, "r");
                // $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                // $csv = array_map('str_getcsv', file($readfilename));
                //fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get Promo Assortment Exclude Created';
                $sync_reports->event_short_name = 'GPAMEC';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get Promo Assortment Exclude Created',
                //     'event_short_name'  => 'GPAMEC',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                DB::table($ftpDetails->store_db_name.'.promo_assortment_exclude_temp')->truncate();
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.promo_assortment_exclude_temp FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            // }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    

    }

    public function PromoAssortmentIncludeSync($ftpDetails)
    {

        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/PromoAssortment/Include/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/PromoAssortment/Include/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);
        // dd($filelist);
        DB::table($ftpDetails->store_db_name.'.promo_assortment_include_temp')->truncate();
        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPAMIC')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            // if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                // $handle = fopen($readfilename, "r");
                // $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                // $csv = array_map('str_getcsv', file($readfilename));
                // fclose($handle);
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                $syncReport = new SyncReports;
                $syncReport->vendor_id = $ftpDetails->vendor_id;
                $syncReport->event_name = 'Get Promo Assortment Include Created';
                $syncReport->event_short_name = 'GPAMIC';
                $syncReport->file_name = (string)$filename;
                $syncReport->number_of_entry = count($fp) - 1;
                $syncReport->upload_date = date('Y-m-d H:i:s');
                $syncReport->created_at = date('Y-m-d H:i:s');
                $syncReport->save();
                
                // DB::table($ftpDetails->store_db_name.'.promo_assortment_include')->truncate();
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.promo_assortment_include_temp FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            // }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function PromoBuyNewSync($ftpDetails)
    {

        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/PromoBuy/new/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/PromoBuy/new/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPBC')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            // if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);

                $allkey = collect($csv);
                // DB::table('zwing_demo.promo_buy')->whereIn('CODE',$allkey->pluck('0'))->delete();
                foreach ($allkey as $dkey => $delete) {
                    DB::table($ftpDetails->store_db_name.'.promo_buy')->where('PROMO_CODE', $delete[1])->where('ASSORTMENT_CODE', $delete[2])->delete();
                }
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get Promo Buy Created';
                $sync_reports->event_short_name = 'GPBC';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get Promo Buy Created',
                //     'event_short_name'  => 'GPBC',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.promo_buy FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            // }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function PromoBuyUpdateSync($ftpDetails)
    {

        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/PromoBuy/update/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/PromoBuy/update/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPBM')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            // if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);

                $allkey = collect($csv);
                // dd($allkey);
                // DB::table('zwing_demo.promo_buy')->whereIn('CODE',$allkey->pluck('0'))->delete();
                foreach ($allkey as $dkey => $delete) {
                    DB::table($ftpDetails->store_db_name.'.promo_buy')->where('PROMO_CODE', $delete[1])->where('ASSORTMENT_CODE', $delete[2])->delete();
                }
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get Promo Buy Updated';
                $sync_reports->event_short_name = 'GPBM';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get Promo Buy Updated',
                //     'event_short_name'  => 'GPBM',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.promo_buy FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            // }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function PromoMasterNewSync($ftpDetails)
    {

        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/PromoMaster/new/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/PromoMaster/new/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPMC')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            // if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);

                $allkey = collect($csv);
                DB::table($ftpDetails->store_db_name.'.promo_master')->whereIn('CODE',$allkey->pluck('0'))->delete();
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get Promo Master Created';
                $sync_reports->event_short_name = 'GPMC';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get Promo Master Created',
                //     'event_short_name'  => 'GPMC',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.promo_master FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            // }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function PromoMasterUpdateSync($ftpDetails)
    {

        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/PromoMaster/update/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/PromoMaster/update/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPMM')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            // if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);

                $allkey = collect($csv);
                DB::table($ftpDetails->store_db_name.'.promo_master')->whereIn('CODE',$allkey->pluck('0'))->delete();
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get Promo Master Updated';
                $sync_reports->event_short_name = 'GPMM';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get Promo Master Updated',
                //     'event_short_name'  => 'GPMM',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.promo_master FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            // }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function PromoSlabNewSync($ftpDetails)
    {

        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/promoslab/new/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/promoslab/new/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPSC')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            // if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);

                $allkey = collect($csv);
                // DB::table('zwing_demo.promo_slab')->whereIn('SLAB_CODE',$allkey->pluck('0'))->delete();
                foreach ($allkey as $dkey => $delete) {
                    DB::table($ftpDetails->store_db_name.'.promo_slab')->where('PROMO_CODE', $delete[1])->delete();
                }
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get Promo Slab Created';
                $sync_reports->event_short_name = 'GPSC';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get Promo Slab Created',
                //     'event_short_name'  => 'GPSC',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.promo_slab FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            // }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    
    }

    public function PromoSlabUpdateSync($ftpDetails)
    {

        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/promoslab/update/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/promoslab/update/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPSM')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            // if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);

                $allkey = collect($csv);
                // DB::table('zwing_demo.promo_slab')->whereIn('SLAB_CODE',$allkey->pluck('0'))->delete();

                foreach ($allkey as $dkey => $delete) {
                    DB::table($ftpDetails->store_db_name.'.promo_slab')->where('PROMO_CODE', $delete[1])->delete();
                }
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get Promo Slab Updated';
                $sync_reports->event_short_name = 'GPSM';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get Promo Slab Updated',
                //     'event_short_name'  => 'GPSM',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.promo_slab FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            // }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function PsitePromoAssignNewSync($ftpDetails)
    {

        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/PromoAssign/new/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/PromoAssign/new/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPAC')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            // if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                // $handle = fopen($readfilename, "r");
                // $contents = fread($handle, filesize($readfilename));
                // //dd($contents);
                // //print_r(fgetcsv($contents));
                // $csv = array_map('str_getcsv', file($readfilename));
                // fclose($handle);

                // $allkey = collect($csv);
                // DB::table('zwing_demo.psite_promo_assign')->whereIn('CODE',$allkey->pluck('0'))->delete();
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get Psite Promo Assign Created';
                $sync_reports->event_short_name = 'GPAC';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get Psite Promo Assign Created',
                //     'event_short_name'  => 'GPAC',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                DB::table($ftpDetails->store_db_name.'.psite_promo_assign')->truncate();
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.psite_promo_assign FIELDS TERMINATED BY "|" ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            // }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    }

    public function PsitePromoAssignUpdateSync()
    {

        $date       = $this->date;
        // connect and login to FTP server
        $ftp_server = $this->ftp_server;
        $path       = 'files/PromoAssign/update/'.$date.'/';
        $local_path = '/home/'.$this->ftp_name.'/ftp/files/PromoAssign/update/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $this->ftp_user,$this->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);

        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GPAM')->where('upload_date','like', $this->dateConvert.'%')->count();
            // if (empty($exists)) {
                $readfilename = 'ftp://'.$this->ftp_user.':'.$this->ftp_password.'@'.$ftp_server.'/'.$value;
                $localfilename = $local_path;
                $handle = fopen($readfilename, "r");
                $contents = fread($handle, filesize($readfilename));
                //dd($contents);
                //print_r(fgetcsv($contents));
                $csv = array_map('str_getcsv', file($readfilename));
                fclose($handle);

                $allkey = collect($csv);
                DB::table($this->store_db_name.'.psite_promo_assign')->whereIn('CODE',$allkey->pluck('0'))->delete();
                // dd($localfilename.$filename);
                // dd(fgetcsv($filename));

                $fp = file($localfilename.$filename);
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $this->vendor_id;
                $sync_reports->event_name = 'Get Psite Promo Assign Updated';
                $sync_reports->event_short_name = 'GPAM';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $this->vendor_id,
                //     'event_name'        => 'Get Psite Promo Assign Updated',
                //     'event_short_name'  => 'GPAM',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$this->store_db_name.'.psite_promo_assign FIELDS TERMINATED BY "|" ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            // }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    
    
    }

    public function psiteCouponOffer($ftpDetails)
    {

        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/coupon/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/coupon/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);


        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GCOC')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            // if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
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
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get Coupon Created';
                $sync_reports->event_short_name = 'GCOC';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get Group Created',
                //     'event_short_name'  => 'GGC',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                DB::table($ftpDetails->store_db_name.'.psite_couponoffer')->truncate();
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.psite_couponoffer FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            // }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    }

    public function psiteCouponAssign($ftpDetails)
    {
        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/coupon/assign/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/coupon/assign/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);


        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GCOAC')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            // if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
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
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get Coupon Assign Created';
                $sync_reports->event_short_name = 'GCOAC';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get Group Created',
                //     'event_short_name'  => 'GGC',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                DB::table($ftpDetails->store_db_name.'.psite_coupon_assign')->truncate();
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.psite_coupon_assign FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            // }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    }

    public function psiteCoupoAssort($ftpDetails)
    {
        $date       = $ftpDetails->date;
        // connect and login to FTP server
        $ftp_server = $ftpDetails->ftp_server;
        $path       = 'files/coupon/assort/'.$date.'/';
        $local_path = '/home/'.$ftpDetails->ftp_name.'/ftp/files/coupon/assort/'.$date.'/';
        $ftp_conn   = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");
        $login      = ftp_login($ftp_conn, $ftpDetails->ftp_user,$ftpDetails->ftp_password);
        $filelist   = ftp_nlist($ftp_conn, $path);


        foreach ($filelist as $key => $value) {
            //echo $key." - ".$value;
            $filename = str_replace($path, "", $value);
            $exists = SyncReports::where('file_name', $filename)->where('event_short_name', 'GCOAOC')->where('upload_date','like', $ftpDetails->dateConvert.'%')->count();
            // if (empty($exists)) {
                $readfilename = 'ftp://'.$ftpDetails->ftp_user.':'.$ftpDetails->ftp_password.'@'.$ftp_server.'/'.$value;
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
                $sync_reports = new SyncReports;
                $sync_reports->vendor_id = $ftpDetails->vendor_id;
                $sync_reports->event_name = 'Get Coupon Assort Created';
                $sync_reports->event_short_name = 'GCOAOC';
                $sync_reports->file_name = (string)$filename;
                $sync_reports->number_of_entry = count($fp) - 1;
                $sync_reports->upload_date = date('Y-m-d H:i:s');
                $sync_reports->created_at = date('Y-m-d H:i:s');
                $sync_reports->save();
                // SyncReports::create([
                //     'vendor_id'         => $ftpDetails->vendor_id,
                //     'event_name'        => 'Get Group Created',
                //     'event_short_name'  => 'GGC',
                //     'file_name'         => (string)$filename,
                //     'number_of_entry'   => count($fp) - 1,
                //     'upload_date'       => date('Y-m-d H:i:s'),
                //     'created_at'        => date('Y-m-d H:i:s')
                // ]);
                DB::table($ftpDetails->store_db_name.'.psite_coupon_assrt')->truncate();
                $custom = DB::connection()->getPdo()->exec('LOAD DATA LOCAL INFILE "'.$localfilename.$filename.'" INTO TABLE '.$ftpDetails->store_db_name.'.psite_coupon_assrt FIELDS TERMINATED BY "," ENCLOSED BY \'"\' ESCAPED BY \'\' IGNORE 1 LINES');
        
            // }
        }

        // then do something...

        // close connection
        ftp_close($ftp_conn); 
    }


}
