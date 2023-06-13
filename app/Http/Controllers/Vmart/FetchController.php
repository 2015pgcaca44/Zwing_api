<?php

namespace App\Http\Controllers\Vmart;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;


class FetchController extends Controller
{
     
	public function apicall() 
	{
 		ini_set('max_execution_time', 300);  

		/*$response = $this->inboundCheckInvoiceStatus('759254375');
 			dd($response['status']->requestStatusList);
 			echo 'good';
 			die;*/

  		$curl = curl_init();
  		$post['v_id'] = 1;
  		// $post['store_id'] = 2;
  		//echo $post['invoice_date'] = '2021-02-09';  #24 pen  #12/11 problem again push
  		echo $post['invoice_date'] = date('Y-m-d');  #24 pen  #12/11 problem again push



  		$query = http_build_query($post);

		curl_setopt($curl, CURLOPT_URL,'https://api.gozwing.com/inbound-api?'.$query); 
		// curl_setopt($curl, CURLOPT_PROXY, "10.0.0.254");
		// curl_setopt($curl, CURLOPT_PROXYPORT, "3128");
		curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
		curl_setopt($curl, CURLOPT_POSTREDIR, 3);

		$result = curl_exec($curl);
		//dd($result);
		if (curl_error($curl)) {
			$error_msg = curl_error($curl);
			print_r($error_msg);
		}

		if(!$result){die("Connection Failure");}
		// curl_close($curl);
		
		$data = json_decode($result);
		// dd($data);
		$totalData = count($data->data);
		$errorData   = 0;
		$successData = 0;
		foreach ($data->data as $key => $value) 
		{
			// dd($value);
			$push_statustype = $this->inboundAPI($value);

			//echo $value->invoiceNo;
			//print_r($push_statustype);
			 //dd($push_statustype);
			if(array_key_exists('error', $push_statustype)) {
				$data = [ 'v_id' => 1, 'store_id' => $value->orderLocation, 'invoice_no' => $value->invoiceNo, 'response' => $push_statustype['error'], 'status' => '0' ];
				$this->invoiceFlag($data);
				$errorData++;
			} elseif (array_key_exists('ackid', $push_statustype)) {
				$final_check = $this->inboundCheckInvoiceStatus($push_statustype['ackid']);

				//echo print_r($final_check['error']);die;

				if (array_key_exists('error', $final_check)) {
					$data = [ 'v_id' => 1, 'store_id' => $value->orderLocation, 'invoice_no' => $value->invoiceNo, 'response' => $final_check['error'], 'status' => '0' ];

					$this->invoiceFlag($data);
					$errorData++;
				} elseif (array_key_exists('status', $final_check)) {
					$data = [ 'v_id' => 1, 'store_id' => $value->orderLocation, 'invoice_no' => $value->invoiceNo, 'response' => $final_check['status'], 'status' => '1' ];
					$this->invoiceFlag($data);
					$successData++;
				}else{
					$data = [ 'v_id' => 1, 'store_id' => $value->orderLocation, 'invoice_no' => $value->invoiceNo, 'response' => $final_check['status'], 'status' => '0' ];
					$this->invoiceFlag($data);
					$errorData++;
				}
			}else{
				$data = [ 'v_id' => 1, 'store_id' => $value->orderLocation, 'invoice_no' => $value->invoiceNo, 'response' => $push_statustype, 'status' => '0' ];
				$this->invoiceFlag($data);
				$errorData++;
			}
		}
		echo 'Total Record = '.$totalData;
		echo '----------------------------';
		echo 'Total Success = '.$successData;
		echo '------------------------------';
		echo 'Total Error = '.$errorData;

	}

	public function inboundAPI($value)
	{
		#old - http://182.76.185.60/WebAPI/api/GDS/CreateInvoice
		#new - http://182.76.185.60/WebApi/api/GDS/CreateInvoice?version=2
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => "http://182.76.185.60/WebAPI/api/GDS/CreateInvoice?version=2",
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 500,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS => '['.json_encode($value).']',
		  CURLOPT_HTTPHEADER => array(
		    "AuthKey: XCdkZT/ZhQmHfViM116dXzKUv+KdnSh0U3CPfAWAEIhAtPHrp2BZUyng/tLY8ozgwlvr/YC4iL/+7xHOSQkEBw==",
		    "Content-Type: application/json",
		    "cache-control: no-cache"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		//echo 'hello';
		//print_r($err);die;
		curl_close($curl);

		// if ($err) {
		//   // echo "cURL Error #:" . $err;
		// 	return '';
		// } else {
		 //print_r($response);
		  $response = json_decode($response,false, 512, JSON_BIGINT_AS_STRING);

		 

		  if ($response->error == null) {
		  	return [ 'ackid' => $response->result->ackid ];
		  } else {
		  	return [ 'error' => $response->error ];
		  }
		// }
	}




	public function inboundCheckInvoiceStatus($value)
	{
		//."&version=2"
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => "http://182.76.185.60/WebAPI/api/GDS/CheckStatus?ackid=".$value,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 500,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "GET",
		  // CURLOPT_POSTFIELDS => '['.json_encode($value).']',
		  CURLOPT_HTTPHEADER => array(
		    "AuthKey: XCdkZT/ZhQmHfViM116dXzKUv+KdnSh0U3CPfAWAEIhAtPHrp2BZUyng/tLY8ozgwlvr/YC4iL/+7xHOSQkEBw==",
		    "Content-Type: application/json",
		    "cache-control: no-cache"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);


		//dd(json_decode($response));

		// if ($err) {
		//   echo "cURL Error #:" . $err;
		// } else {
		//   echo $response.'<br/>';
		// }
		
		$response = json_decode($response);

		
		//dd($response->result);
		if( stripos($response->result, 'Failure') || $response->error != null){
		//if( $response->result == 'Failure' || $response->error != null){
			return [ 'error' => $response->result ];
		}else{
			return [ 'status' => $response->result ];
		}
		 
		// dd($response);
	}

	public function invoiceFlag($data)
	{
		ini_set('max_execution_time', 300);  

  		$curl = curl_init();
  		$post['v_id'] = $data['v_id'];
  		$post['store_id'] = $data['store_id'];
  		$post['invoice_no'] = $data['invoice_no'];
  		$post['response'] = $data['response'];
  		$post['status'] = $data['status'];

  		$query['data'] = urlencode(json_encode($post));
  		$query['v_id'] = 1; 

  		// dd($query);

		curl_setopt($curl, CURLOPT_URL,'https://api.gozwing.com/invoice-push-status'); 
		// curl_setopt($curl, CURLOPT_PROXY, "10.0.0.254");
		// curl_setopt($curl, CURLOPT_PROXYPORT, "3128");
		curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
		curl_setopt($curl, CURLOPT_POSTREDIR, 3);

		$result = curl_exec($curl);

		// dd($result);

		if (curl_error($curl)) {
			$error_msg = curl_error($curl);
			print_r($error_msg);
		}

		// if(!$result){die("Connection Failure");}
	}
}
