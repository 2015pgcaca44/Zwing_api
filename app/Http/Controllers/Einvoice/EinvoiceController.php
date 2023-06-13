<?php

namespace App\Http\Controllers\Einvoice;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\VendorSetting;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use App\User;
 use Auth;
 use App\Invoice;
use App\LoyaltyBill;
use App\InvoiceDetails;
use App\Store;
use App\EinvoiceDetails;
use App\State;
use App\Http\Controllers\ApiCallerController;
use Endroid\QrCode\QrCode;

class EinvoiceController extends Controller
{

#emgorg

	public function __construct($params){
		
		$client = new Client();
		$storeSettings = json_decode($params['settings']);
		$res = $client->request('POST',$storeSettings->texilla->TOKEN_HOST_ADDRESS,[
		'form_params' => [
		    'client_id' =>  $storeSettings->texilla->CLIENT_ID, 
		    'client_secret' => $storeSettings->texilla->CLIENT_SECRET,
		    'grant_type' => $storeSettings->texilla->GRANT_TYPE
		]]);

		$statusCode =  $res->getStatusCode();
		if($statusCode == 200){
			$accessToken       = json_decode($res->getBody(), true);
			$accessToken       = $accessToken['access_token'];
			$this->accessToken = 'Bearer '.$accessToken;
			$this->apiUrl      = $storeSettings->texilla->API_URL;

		}else{
			return response()->json([ 'message' => $res->getBody() ]);
		}
		 //print_r($accessToken);	
	}

	public function second($params){
		$client = new Client();
		$storeSettings = json_decode($params['settings']);
		$res = $client->request('POST',$storeSettings->texilla->TOKEN_HOST_ADDRESS,[
		'form_params' => [
		    'client_id' =>  $storeSettings->texilla->CLIENT_ID, 
		    'client_secret' => $storeSettings->texilla->CLIENT_SECRET,
		    'grant_type' => $storeSettings->texilla->GRANT_TYPE
		]]);

		$statusCode =  $res->getStatusCode();
		if($statusCode == 200){
			$accessToken       = json_decode($res->getBody(), true);
			$accessToken       = $accessToken['access_token'];
			$this->accessToken = $accessToken;
			$this->apiUrl      = $storeSettings->texilla->API_URL;

		}else{
			return response()->json([ 'message' => $res->getBody() ]);
		}

		 //print_r($accessToken);
	}

	public function checkApi($params)
	{
		$post['client_id'] 		= 'abhi1';
		$post['client_secret']  = 'abhi';
		$post['grant_type']     = 'client_credentials';
		$curl  = curl_init();
		$query = http_build_query($post);
	
		curl_setopt($curl, CURLOPT_URL,'https://api.taxilla.com/oauth2/v1/token'); 
		// curl_setopt($curl, CURLOPT_PROXY, "10.0.0.254");
		// curl_setopt($curl, CURLOPT_PROXYPORT, "3128");
		curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curl, CURLOPT_POSTFIELDS, "client_id=emgorg&client_secret=emgorg&grant_type=client_credentials");
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
		curl_setopt($curl, CURLOPT_POSTREDIR, 3);

		$result = curl_exec($curl);
		if (curl_error($curl)) {
			$error_msg = curl_error($curl);
			print_r($error_msg);
		}

		print_r($result);
		die;

		$client = new Client();
		$response = $client->request('POST','https://api.taxilla.com/oauth2/v1/token',['']);
		print_r($response);die;
	}


	public function checkNewOne($params){

		/*$file='Zwing-Sales-Detail-Report-'.date('d-M-Y-h-i-s-A').'.xls';
		header("Content-type: application/vnd.ms-excel");
		header("Content-Disposition: attachment; filename=$file");
		echo "asdf";
		exit();

		$post['transaction_id'] 	= 'EMGA30112020B31';
		$post['supply_type']  		= 'Outward';
		$post['transaction_type']   = 'INV';
		$post['return_year']     	= '2020-21';
		$post['return_month']     	= '11';

		die;*/


		$invoice_id  = $params['invoice_id'];
		$return_year = $params['return_year'];
		$return_month= $params['return_month'];
		$post['transaction_id'] 	= $invoice_id;
		$post['supply_type']  		= 'Outward';
		$post['transaction_type']   = 'INV';
		$post['return_year']     	= $return_year;
		$post['return_month']     	= $return_month;
		$curl  = curl_init();
		$query = http_build_query($post);


		curl_setopt($curl, CURLOPT_URL,'https://api.taxilla.com/process/v1/einvoiceewbv1/reports/eInvoice?'.$query); 
		// curl_setopt($curl, CURLOPT_PROXY, "10.0.0.254");
		// curl_setopt($curl, CURLOPT_PROXYPORT, "3128");
		curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Authorization:'.$this->accessToken,'app-id:envoice'));

		$result = curl_exec($curl);
		$data = json_decode($result);
		/*$downloadPath = base_path('public')."/einvoice/".$post['transaction_id'].".pdf";
		$file = fopen($downloadPath, "w+");
		fputs($file, $result);
		fclose($file);*/

//print_r($result);
		 
		$filename = 'Report.pdf';
        header('Content-type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');
        echo $result;
       

		//print_r($result);
		//dd($result);
		if (curl_error($curl)) {
			$error_msg = curl_error($curl);
			print_r($error_msg);
		}

		die;
	
	}

  /*Work start */
	public function generateInvoiceToJson($invoiceId){

    //JobdynamicConnection(19);
		$invoice    =  Invoice::where('invoice_id',$invoiceId)->first();
		$SellerDtls =  Store::find($invoice->store_id);
		$BuyerDtls  =  User::leftjoin('addresses','addresses.c_id','customer_auth.c_id')->where('customer_auth.c_id',$invoice->user_id)->select('customer_auth.*','addresses.address1','addresses.address2','addresses.landmark','addresses.pincode','addresses.state_id')->first();
    //dd( $BuyerDtls->state);die;
   
		$data = [];
		if(!empty($invoice)){
			$date   = date('d/m/Y',strtotime($invoice->date));
			$data['Version']  = '1.1';
			$data['TranDtls'] = array('TaxSch'=>'GST','SupTyp'=>'B2B','RegRev'=>'N','EcmGstin'=>null,'IgstOnIntra'=>'N');
			$data['DocDtls']  = array('Typ'=>'INV','No'=>$invoice->invoice_id,'Dt'=>$date);

			//$data['SellerDtls'] = array('Gstin'=>$SellerDtls->gst,'LglNm'=>$SellerDtls->name,'TrdNm'=>null,'Addr1'=>$SellerDtls->address1,'Addr2'=>$SellerDtls->address2,'Loc'=>$SellerDtls->location,'Pin'=>$SellerDtls->pin,'Stcd'=>null,'Ph'=>$SellerDtls->contact_number,'Em'=>$SellerDtls->email);

      $data['SellerDtls'] = array('Gstin'=>'01AMBPG7773M002','LglNm'=>'ABC company pvt ltd','TrdNm'=>null,'Addr1'=>'5th  block,  kuvempu layout','Addr2'=>null,'Loc'=>'Jammu and Kashmir','Pin'=>'181102','Stcd'=>'JK','Ph'=>null,'Em'=>null);

			$data['BuyerDtls'] = array('Gstin'=> @$BuyerDtls->gstin,'LglNm'=>$BuyerDtls->first_name.$BuyerDtls->last_name,'TrdNm'=>null,'Pos'=>$this->getStateDetails($BuyerDtls->state_id)->tin,'Addr1'=>$BuyerDtls->address1,'Addr2'=>$BuyerDtls->address2,'Loc'=>$BuyerDtls->landmark,'Pin'=>$BuyerDtls->pincode,'Stcd'=>trim($this->getStateDetails($BuyerDtls->state_id)->code),'Ph'=>$BuyerDtls->mobile,'Em'=>$BuyerDtls->email);

			$data['DispDtls'] = array('Nm'=>null,'Addr1'=>null,'Addr2'=>null,'Loc'=>null,'Pin'=>null,'Stcd'=>null);

			$data['ShipDtls'] = array('Gstin'=>null,'LglNm'=>null,'TrdNm'=>null,'Addr1'=>null,'Addr2'=>null,'Loc'=>null,'Pin'=>null,'Stcd'=>null);

			$data['ItemList'] = $this->getInvoiceDetail($invoice);

			$data['ValDtls']  =  array('AssVal'=>null,'CgstVal'=>null,'SgstVal'=>null,'IgstVal'=>null,'CesVal'=>null,'StCesVal'=>null,'Discount'=>null,'OthChrg'=>null,'RndOffAmt'=>null,'TotInvVal'=>null,'TotInvValFc'=>null);
			$data['PayDtls']  =  array('Nm'=>null,'AccDet'=>null,'Mode'=>null,'FinInsBr'=>null,'PayTerm'=>null,'PayInstr'=>null,'CrTrn'=>null,'DirDr'=>null,'CrDay'=>null,'PaidAmt'=>null,'PaymtDue'=>null);

			$data['RefDtls']  =  array('InvRm'=>null,'DocPerdDtls'=>array('InvStDt'=>$date,'InvEndDt'=>$date),
								'PrecDocDtls'=>[array('InvNo'=>$invoice->id,'InvDt'=>$date,'OthRefNo'=>null)],
								'ContrDtls'=>[array('RecAdvRefr'=>null,'RecAdvDt'=>$date,'TendRefr'=>null,'ContrRefr'=>null,'ExtRefr'=>null,'ProjRefr'=>null,'PORefr'=>null,'PORefDt'=>$date)]);


			$data['AddlDocDtls']  =  [array('Url'=>null,'Docs'=>null,'Info'=>null)];
			$data['ExpDtls']  =  array('ShipBNo'=>null,'ShipBDt'=>$date,'Port'=>null,'RefClm'=>null,'ForCur'=>null,'CntCode'=>null,'ExpDuty'=>null);
			$data['EwbDtls']  =  array('TransId'=>null,'TransName'=>null,'TransMode'=>null,'Distance'=>null,'TransDocDt'=>$date,'TransDocNo'=>null,'VehNo'=>null,'VehType'=>null);

      return json_encode($data,true);

		}

	}

	private function getInvoiceDetail($values){

        // dd($values);
        $carts = InvoiceDetails::select('item_id','item_name','qty','unit_mrp','discount','total','tdata','pdata','unit_csp','transaction_type','lpdiscount','coupon_discount','manual_discount')->where(['v_id' => $values->v_id, 'store_id' => $values->store_id, 'user_id' => $values->user_id, 't_order_id' => $values->id])->get();
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
            $srno = 1;
            foreach ($carts as $key => $cart) {

                $tax_data = json_decode($cart->tdata);
              
                $date     = date('d/m/Y',strtotime($cart->date));

                $pdata      = array();

                $pdata['Promo_code'] = '';
                $pdata['NO']         = '';
                $pdata['PROMO_NAME'] = '';
                $pdata['START_DATE'] = '';
                $pdata['END_DATE']   = '';
                $pdata['DISCOUNT_TYPE']   = '';

 
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
                   $qty 	= $cart->qty;
                   $mrp 	= $mrp;
                   $rate 	= $rate;
                   $taxable = $taxable;
                   $cgstamt = $tax_data->cgstamt;
                   $sgstamt = $tax_data->sgstamt;
                   $netAmt 	= $cart->total;
                   $itemPromoAmt = $cart->discount;
                   $discountAmt  = $cart->total_discount;
                   $gst        = $tax_data->cgst+$tax_data->sgst+$tax_data->igst;
                   $igstamt    = $tax_data->igstamt;
                   $cessAmt    = $tax_data->cessamt;
 
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
                   $gst        = $tax_data->cgst+$tax_data->sgst+$tax_data->igst;
                   $igstamt       = -$tax_data->igstamt;
                   $cessAmt    = -$tax_data->cessamt;
                    

               }

                $cartArr['SlNo']= (string)$srno;
                $cartArr['PrdDesc']= $cart->item_name;
                $cartArr['IsServc']= 'N';
                $cartArr['HsnCd']= $tax_data->hsn;
                $cartArr['Barcde']= $cart->item_id;
                $cartArr['Qty']= $qty;
                $cartArr['FreeQty']= '0';
                $cartArr['Unit']= 'BAG';
                $cartArr['UnitPrice']= $cart->unit_mrp;
                $cartArr['TotAmt']= format_number($netAmt);
                $cartArr['Discount']= $discountAmt;
                $cartArr['PreTaxVal']= null;
                $cartArr['AssAmt']= format_number($taxable);
                $cartArr['GstRt']=  $gst;
                $cartArr['IgstAmt']=  $igstamt;
                $cartArr['CgstAmt']=  $cgstamt;
                $cartArr['SgstAmt']=  $sgstamt;
                $cartArr['CesRt']=  $tax_data->cess;
                $cartArr['CesAmt']=  $cessAmt;
                $cartArr['CesNonAdvlAmt']=  null;
                $cartArr['StateCesRt']=  null;
                $cartArr['StateCesAmt']=  null;
                $cartArr['StateCesNonAdvlAmt']=  null;
                $cartArr['OthChrg']=  null;
                $cartArr['TotItemVal']=  0;
                $cartArr['OrdLineRef']=  null;
                $cartArr['OrgCntry']=  null;
                $cartArr['PrdSlNo']=  null;
                $cartArr['BchDtls']=  array('Nm' => '','Expdt'=>$date,'wrDt'=>$date );
                $cartArr['AttribDtls']=  [array('Nm' => '','Val'=>'' )];

                $srno++;

                $invoice[] = $cartArr;
            }

            return $invoice;
    
    }//End of getInvoiceDetail


	public function generateEinvoice($params){

    $invoice_id = $params['invoice_id'];
    $invoiceData = $this->generateInvoiceToJson($invoice_id);
    $stParam = $params;
    $post['transformationName']    = 'IRP_JSON_Upload';
    $post['autoExecuteRules']  = true;
    //$post['json']     = $invoiceData;
    $curl  = curl_init();
    $query = http_build_query($post);
  
    curl_setopt($curl, CURLOPT_URL,$this->apiUrl.'einvoiceewbv1?'.$query); 
    // curl_setopt($curl, CURLOPT_PROXY, "10.0.0.254");
    // curl_setopt($curl, CURLOPT_PROXYPORT, "3128");
    curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($curl, CURLOPT_POSTFIELDS, $invoiceData);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [ 'Content-Type:application/json','Authorization:'.$this->accessToken,'app-id:envoice']);
    curl_setopt($curl, CURLOPT_POSTREDIR, 3);

    $result = curl_exec($curl);
    $data = json_decode($result,true);
    if (curl_error($curl)) {
      $error_msg = curl_error($curl);
      //print_r($error_msg);
      return $error_msg;
    }

    //print_r($data['response']['requestId']);die;

    if(!empty($data['response']['requestId'])){
      $request_id = $data['response']['requestId'];
      $msg = 'Irn genrated but ack status pending';
      $pm = array('v_id'=>$params['v_id'],'invoice_id'=>$invoice_id,'request_id'=>$request_id,'status'=>'Pending','response'=>$msg);
      $this->createOrUpdateEinvoiceDetails($pm);
      return response()->json(['status'   => 'success', 'message' => $msg],200);  
    }else{
    return response()->json(['status'   => 'error', 'message' => 'Please Try again'],422);  
    }
     
    //return $this->IrnStatus($stParam);


		//echo $this->accessToken;
		$client = new Client();
    $invoice_id = $params['invoice_id'];
    $stParam = $params;

    $invoiceData = $this->generateInvoiceToJson($invoice_id);

    //echo $invoiceData;die;
 
		$params['data'] = [
			'headers' => [ 
				'Content-Type' => 'application/json',
		    	'Authorization'	=> $this->accessToken,
		    	'app-id'        => 'envoice'
		    ],
			 'form_params'=> ['json'	=> $invoiceData],
		    'query'	=> [
		    	'transformationName' => 'IRP_JSON_Upload',
		    	'autoExecuteRules'	 => true,
          'json' => $invoiceData

		    ]
		];

		$res = $client->request($params['method'], $this->apiUrl.'einvoiceewbv1', $params['data']);
		$statusCode =  $res->getStatusCode();
		if ($statusCode == 200 || $statusCode == 202) {
			$result       = json_decode($res->getBody(), true);
      //return $result;
		//print_r($result['response']['requestId']);
      $request_id = $result['response']['requestId'];
      $pm = array('v_id'=>$params['v_id'],'invoice_id'=>$invoice_id,'request_id'=>$request_id,'status'=>'Pending','response'=>'Irn genrated but ack status pending ');
      $this->createOrUpdateEinvoiceDetails($pm);
      //Check status and update ack no  in db
      //return $this->IrnStatus($stParam);
      return $result;
		}else{
		 return response()->json([ 'message' => $res->getBody() ]);
		}

	}//End of generateEinvoice


  public function IrnStatus($params)
  {

    //echo $this->accessToken;

    $invoice_id  = $params['invoice_id'];
    $return_year = $params['return_year'];
    $return_month= $params['return_month'];

    $post['transaction_id']   = $invoice_id;
    $post['supply_type']      = 'Outward';
    $post['transaction_type'] = 'INV';
    $post['return_year']      = $return_year;
    $post['return_month']     = $return_month;
    $curl  = curl_init();
    $query = http_build_query($post);
    curl_setopt($curl, CURLOPT_URL,'https://api.taxilla.com/process/v1/einvoiceewbv1/reports/IRN-Response?'.$query); 
    // curl_setopt($curl, CURLOPT_PROXY, "10.0.0.254");
    // curl_setopt($curl, CURLOPT_PROXYPORT, "3128");
    curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Authorization: '.$this->accessToken,'app-id:envoice'));
    $result = curl_exec($curl);
       
    if (curl_error($curl)) {
      $error_msg = curl_error($curl);
      //print_r($error_msg);
      return response()->json(['status'   => 'error', 'message' => 'Please try again'],401); 

    }else{
      $data = json_decode($result,true);
       //print_r($data['result']);
       $pm = array('v_id'=>$params['v_id'],'invoice_id'=>$invoice_id);
       if(!empty($data['status']) && $data['status'] != 'ACT'){
          $pm['status']       = 'Error';
          $pm['response']     = $data['msg'];
          $pm['response_json']  = $result;
          $status    = 'error';
          $msg       = $data['msg'];
          $taxillaErrorCode = $data['taxillaErrorCode'];
       }else{
       	  $path      =    base_path('public')."/einvoice/qrcode/$invoice_id.png";
          $pm['response']       = $data['result']['Status'];
          $pm['ack_no']         = $data['result']['AckNo'];
          $pm['ack_date']       = $data['result']['AckDt'];
          $pm['irn']            = $data['result']['Irn'];
          $pm['signed_invoice'] = $data['result']['SignedInvoice'];
          $pm['signed_qr_code'] = $data['result']['SignedQRCode'];
          $pm['status']         = 'Success';
          $pm['ewd_no']         = @$data['result']['EwbNo'];
          $pm['ewd_date']       = @$data['result']['EwbDt'];
          $pm['ewd_valid_till'] = @$data['result']['EwbValidTill']; 
          $pm['qrcode_image_path']= @$path; 

          $status    = 'success';
          $msg       = 'IRN Generated Successfully.';

          //$prQr =array('content' => $data['result']['SignedInvoice'] ,'invoice_id'=>$invoice_id,'path'=>$path);
          //$this->generateQRCode($prQr);

       }
      $this->createOrUpdateEinvoiceDetails($pm);
      if($status == 'success'){
        return response()->json(['status'   => $status, 'message' => $msg],200); 
      }else{
         return response()->json(['status'   => $status, 'message' => $msg,'taxillaErrorCode'=>$taxillaErrorCode],422); 
      }
    }


     // $pm = array('v_id'=>$params['v_id'],'invoice_id'=>$invoice_id,'status'=>'Pending','response'=>'Irn genrated but ack status pending ',);
     //  $this->createOrUpdateEinvoiceDetails($pm);


    /*die;

    $client = new Client();
    $invoice_id  = $params['invoice_id'];
    $return_year = $params['return_year'];
    $return_month= $params['return_month'];

    $params['data'] = [
      'headers' => [ 
        'Content-Type' => 'application/json',
          'Authorization' => $this->accessToken,
          'app-id'        => 'envoice'
        ],
        'query' => [
          'transaction_id'   =>  $invoice_id,
          'supply_type'    =>   'Outward',
          'transaction_type' =>  'INV',
          'return_year'      =>  $return_year,
          'return_month'     =>  $return_month
        ]        
    ];
    $res = $client->request('GET', $this->apiUrl.'einvoiceewbv1/reports/IRN-Response', $params['data']);
    $statusCode =  $res->getStatusCode();
    if ($statusCode == 200 || $statusCode == 202) {
      $result       = json_decode($res->getBody(), true);
      return $result;
    //print_r($result);
    }else{
     return response()->json([ 'message' => $res->getBody() ]);
    }*/
  }



	public function downloadEinvoice($params){
		$client = new Client();
		$params['data'] = [
			'headers' => [ 
				'Content-Type' => 'application/json',
		    	'Authorization'	=> $this->accessToken,
		    	'app-id'        => 'envoice'
		    ],
		    'query'	=> [
		    	'transaction_id'   =>  'EMGA30112020B31',
		    	'supply_type'	   =>   'Outward',
		    	'transaction_type' =>  'INV',
		    	'return_year'      =>  '2020-21',
		    	'return_month'     =>  '11'
		    ],
		];

		$res = $client->request($params['method'], $this->apiUrl.'einvoiceewbv1/reports/eInvoice', $params['data']);
		$statusCode =  $res->getStatusCode();
		if ($statusCode == 500 || $statusCode == 202) {
			$result       = json_decode($res->getBody(), true);

		print_r($result);
		}else{
		 return response()->json([ 'message' => $res->getBody() ]);
		}

	}//End of downloadEinvoice


  public function createOrUpdateEinvoiceDetails($params){
    $invoice_id = $params['invoice_id'];
    $store_id   = isset($params['store_id'])?$params['store_id']:'0';
    

    $recordExist = EinvoiceDetails::where('invoice_id',$invoice_id)->where('status','Pending')->first();
    if($recordExist){
      $recordExist->response = @$params['response'];
      $recordExist->store_id = @$store_id;
      $recordExist->response_json = @$params['response_json'];
      $recordExist->ack_no   = @$params['ack_no'];
      $recordExist->ack_date = @$params['ack_date'];
      $recordExist->irn      = @$params['irn'];
      $recordExist->signed_invoice = @$params['signed_invoice'];
      $recordExist->signed_qr_code = @$params['signed_qr_code'];
      $recordExist->status    = @$params['status'];
      $recordExist->ewd_no = @$params['ewd_no'];
      $recordExist->ewd_date = @$params['ewd_date'];
      $recordExist->ewd_valid_till = @$params['ewd_valid_till'];
      $recordExist->qrcode_image_path = @$params['qrcode_image_path'];
      $recordExist->save();
    }else{
      $recordExist  = new EinvoiceDetails();
      $recordExist->v_id = @$params['v_id'];
      $recordExist->store_id = @$store_id;
      $recordExist->invoice_id = @$params['invoice_id'];
      $recordExist->request_id = @$params['request_id'];
      $recordExist->response = @$params['response'];
      $recordExist->response_json = @$params['response_json'];
      $recordExist->ack_no   = @$params['ack_no'];
      $recordExist->ack_date = @$params['ack_date'];
      $recordExist->irn      = @$params['irn'];
      $recordExist->signed_invoice = @$params['signed_invoice'];
      $recordExist->signed_qr_code = @$params['signed_qr_code'];
      $recordExist->status    = @$params['status'];
      $recordExist->ewd_no = @$params['ewd_no'];
      $recordExist->ewd_date = @$params['ewd_date'];
      $recordExist->ewd_valid_till = @$params['ewd_valid_till'];
      $recordExist->qrcode_image_path = @$params['qrcode_image_path'];
      $recordExist->save();
    }

  }//End of createOrUpdateEinvoiceDetails


  

  /*

          '{
  "Version": "1.1",
  "TranDtls": {
    "TaxSch": "GST",
    "SupTyp": "B2B",
    "RegRev": "N",
    "EcmGstin": null,
    "IgstOnIntra": "N"
  },
  "DocDtls": {
    "Typ": "INV",
    "No": "ZMGA30112020B11",
    "Dt": "30/11/2020"
  },
  "SellerDtls": {
    "Gstin": "01AMBPG7773M002",
    "LglNm": "ABC company pvt ltd",
    "TrdNm": null,
    "Addr1": "5th block, kuvempu layout",
    "Addr2": null,
    "Loc": "Jammu and Kashmir",
    "Pin": "181102",
    "Stcd": "JK",
    "Ph": null,
    "Em": null
  },
  "BuyerDtls": {
    "Gstin": "29AAACC9061C1ZT",
    "LglNm": "XYZ company pvt ltd",
    "TrdNm": null,
    "Pos": "29",
    "Addr1": "7th block, kuvempu layout",
    "Addr2": null,
    "Loc": "GANDHINAGAR",
    "Pin": "560025",
    "Stcd": "KA",
    "Ph": null,
    "Em": "rkdasari@taxilla.com"
  },
  "DispDtls": {
    "Nm": "ABC company pvt ltd",
    "Addr1": "7th block, kuvempu layout",
    "Addr2": null,
    "Loc": "Banagalore",
    "Pin": "560043",
    "Stcd": "29"
  },
  "ShipDtls": {
    "Gstin": null,
    "LglNm": "CBE company pvt ltd",
    "TrdNm": null,
    "Addr1": "7th block, kuvempu layout",
    "Addr2": null,
    "Loc": "Banagalore",
    "Pin": 560025,
    "Stcd": "29"
  },
  "ItemList": [
    {
      "SlNo": "1",
      "PrdDesc": "Medicaments",
      "IsServc": "N",
      "HsnCd": "30049099",
      "Barcde": null,
      "Qty": 145.356,
      "FreeQty": 1.111,
      "Unit": "BAG",
      "UnitPrice": 10.613,
      "TotAmt": 1542.66,
      "Discount": 0,
      "PreTaxVal": null,
      "AssAmt": 1542.66,
      "GstRt": 12,
      "IgstAmt": 185.11,
      "CgstAmt": null,
      "SgstAmt": null,
      "CesRt": null,
      "CesAmt": null,
      "CesNonAdvlAmt": null,
      "StateCesRt": null,
      "StateCesAmt": null,
      "StateCesNonAdvlAmt": null,
      "OthChrg": null,
      "TotItemVal": 0,
      "OrdLineRef": null,
      "OrgCntry": null,
      "PrdSlNo": null,
      "BchDtls": {
        "Nm": "123456",
        "Expdt": "09/10/2020",
        "wrDt": "09/11/2020"
      },
      "AttribDtls": [
        {
          "Nm": "Rice",
          "Val": "10000"
        }
      ]
    },
    {
      "SlNo": "2",
      "PrdDesc": "Medicaments",
      "IsServc": "N",
      "HsnCd": "30049099",
      "Barcde": null,
      "Qty": 145,
      "FreeQty": null,
      "Unit": "BAG",
      "UnitPrice": 10.6,
      "TotAmt": 1545,
      "Discount": 0,
      "PreTaxVal": null,
      "AssAmt": 1545,
      "GstRt": 12,
      "IgstAmt": 186,
      "CgstAmt": null,
      "SgstAmt": null,
      "CesRt": null,
      "CesAmt": null,
      "CesNonAdvlAmt": null,
      "StateCesRt": null,
      "StateCesAmt": null,
      "StateCesNonAdvlAmt": null,
      "OthChrg": null,
      "TotItemVal": 0,
      "OrdLineRef": null,
      "OrgCntry": null,
      "PrdSlNo": null,
      "BchDtls": {
        "Nm": "123456",
        "Expdt": "01/08/2020",
        "wrDt": "01/09/2020"
      },
      "AttribDtls": [
        {
          "Nm": "Rice",
          "Val": "10000"
        }
      ]
    }
  ],
  "ValDtls": {
    "AssVal": null,
    "CgstVal": null,
    "SgstVal": null,
    "IgstVal": null,
    "CesVal": null,
    "StCesVal": null,
    "Discount": null,
    "OthChrg": null,
    "RndOffAmt": null,
    "TotInvVal": null,
    "TotInvValFc": null
  },
  "PayDtls": {
    "Nm": null,
    "AccDet": null,
    "Mode": null,
    "FinInsBr": null,
    "PayTerm": null,
    "PayInstr": null,
    "CrTrn": null,
    "DirDr": null,
    "CrDay": null,
    "PaidAmt": null,
    "PaymtDue": null
  },
  "RefDtls": {
    "InvRm": null,
    "DocPerdDtls": {
      "InvStDt": "01/08/2020",
      "InvEndDt": "26/08/2020"
    },
    "PrecDocDtls": [
      {
        "InvNo": "2323",
        "InvDt": "26/08/2020",
        "OthRefNo": null
      }
    ],
    "ContrDtls": [
      {
        "RecAdvRefr": null,
        "RecAdvDt": "26/08/2020",
        "TendRefr": null,
        "ContrRefr": null,
        "ExtRefr": null,
        "ProjRefr": null,
        "PORefr": null,
        "PORefDt": "26/08/2020"
      }
    ]
  },
  "AddlDocDtls": [
    {
      "Url": null,
      "Docs": null,
      "Info": null
    }
  ],
  "ExpDtls": {
    "ShipBNo": null,
    "ShipBDt": "26/08/2020",
    "Port": null,
    "RefClm": null,
    "ForCur": null,
    "CntCode": null,
    "ExpDuty": null
  },
  "EwbDtls": {
    "TransId": "05AAACG2140A1ZL",
    "TransName": "adaequare",
    "TransMode": "1",
    "Distance": 0,
    "TransDocDt": "30/11/2020",
    "TransDocNo": "TRAN/DOC/11",
    "VehNo": "KA12ER1234",
    "VehType": "R"
  }
}'
        
  */


public function generateQRCode_bak($params){

	if(!empty($params['content'])){
		$content    = $params['content'];
		$invoice_id = $params['invoice_id'];
		$path       = $params['path'];
		$qrCode  = new QrCode($content);
		//header('Content-Type: image/png');
		$qrCode->writeString();
		$qrCode->writeFile('qrCode.png');
		$dataUri = $qrCode->writeDataUri();
		 
	}else{
		 
	}
	 

       

}


  public function generateQRCode($params){
    //Using google api
    $width = $height = 229;
    $url   = $params['content'];
    //$width = $height = 100;
    //$url   = urlencode("http://create.stephan-brumme.com");
    $error = "H"; // handle up to 30% data loss, or "L" (7%), "M" (15%), "Q" (25%)
    echo "<img src=\"http://chart.googleapis.com/chart?".
    "chs={$width}x{$height}&cht=qr&chld=$error&chl=$url\" />";
  }//End of generateQRCode


  public function getStateDetails($stateid){

    $state  = State::find($stateid);
    if(empty($state)){
      $state['name'] = 'Other Territory';
      $state['code'] = 'OT';
      $state['tin']  = 97;

      $state = (object)$state;
    } 
    return $state;

  }//End of getStateDetails

}
?>