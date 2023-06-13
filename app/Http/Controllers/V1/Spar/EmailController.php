<?php

namespace App\Http\Controllers\V1\Spar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;
use App\Order;
use App\User;
use App\Cart;
use App\Payment;
use App\Store;
use App\Mail\OrderCreated;
use App\Mail\OrderReturn;
use Illuminate\Support\Facades\Mail;
use PDF;

class EmailController extends Controller
{

	function get_email_triggers($params){

	    $store_id = $params['store_id'];
	    $v_id = $params['v_id'];
	    $email_trigger_code = $params['email_trigger_code'];

	    $email_triggers = DB::table('email_events as ee')
	            ->join('email_triggers as et', 'et.email_event_id','ee.id')
	            ->select('et.to_mail','et.cc','et.bcc')
	            ->where('ee.code', $email_trigger_code)->where('v_id', $v_id)->get();
	    $email = $email_triggers->where('store_id', $store_id)->first();
	    if($email){
	        $to = explode(',', $email->to_mail);
	        $cc = explode(',', $email->cc);
	        $bcc = explode(',', $email->bcc);
	    }else{
	        $to = explode(',', $email_triggers->first()->to_mail);
	        $cc = explode(',', $email_triggers->first()->cc);
	        $bcc = explode(',', $email_triggers->first()->bcc);
	    }

	    $to = array_filter($to);
	    $cc = array_filter($cc);
	    $bcc = array_filter($bcc);

	    return ['to' => $to , 'cc' => $cc , 'bcc' => $bcc ];
	}

	public function send_invoice(Request $request){

		$v_id = $request->v_id;
		$store_id = $request->store_id;
		$c_id = $request->c_id;
		$order_id = $request->order_id;

		$ord = Order::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('status','success')->first();

		$current_invoice_name = $ord->invoice_name;

		$carts = Cart::where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $ord->o_id)->where('user_id', $c_id)->where('transaction_type' , $ord->transaction_type)->get();


		$payments = Payment::select('method', 'amount')->where('store_id', $store_id)->where('v_id', $v_id)->where('order_id', $order_id)->where('user_id', $c_id)->get();

		//dd($payment);

		if(!$payments->isEmpty()){

			$payment_method = '';
			$count = 0;
			foreach($payments as $payment){
				$method_name = '';
				if($payment->method =='spar_credit'){
					$method_name = 'Store Credit';
				}else{
					$method_name = $payment->method;
				}
				$prefix = '';
				if($count > 0){
					$prefix = ', ';
				}
				$count++;
				$payment_method .=$prefix.(isset($method_name) )?$method_name:'';
			}
			

			$user = User::select('email')->where('c_id', $c_id)->first();
			$path =  storage_path();
		
			$complete_path = $path."/app/invoices/".$current_invoice_name;
			if($current_invoice_name && file_exists($complete_path)){
				
		    }else{
		    	
		    	$date = date('Y-m-d');
            	$time = date('h:i:s');

		    	$last_transaction_no = 0;
	            $store = Store::where('v_id',$v_id)->where('store_id', $store_id)->first();
	            $order = Order::where('v_id',$v_id)->where('store_id', $store_id)->where('status','success')->where('order_id','!=', $order_id)->orderBy('od_id','desc')->first();
	            $last_invoice_name = $order->invoice_name;
	            $last_transaction_no = $order->transaction_no;

		    	if($last_invoice_name){
	               $arr =  explode('_',$last_invoice_name);
	               $id = $arr[2] + 1;
	                $current_invoice_name = $date.$time.'_'.$store->mapping_store_id.'_'.$store_id.'_'.$id.'.pdf';
	            }else{
	                $current_invoice_name = $date.$time.'_'.$store->mapping_store_id.'_'.$store_id.'_1.pdf';
	            }

	            $ord->invoice_name = $current_invoice_name;
	            $ord->transaction_no = $last_transaction_no + 1;
	            $ord->status = 'success';
	            $ord->save();

		    	$cart_c = new CartController;

				$html = $cart_c->order_receipt($c_id , $v_id, $store_id, $order_id);
		        $pdf = PDF::loadHTML($html);

		        $complete_path = $path."/app/invoices/".$current_invoice_name;
	        	$pdf->setWarnings(false)->save($complete_path);
		    }
	        
	       
	       	
	        if($user->email != null && $user->email != ''){

	        	if($ord->transaction_type == 'sales'){

	        		$mail_res = $this->get_email_triggers(['v_id' => $v_id ,'store_id' => $store_id , 'email_trigger_code' => 'order_created']);
	                $to = $mail_res['to'];
	                $cc = $mail_res['cc'];
	                $bcc = $mail_res['bcc'];

	                //dd($cc);
	                $mailer = Mail::to($user->email); 
	                if(count($bcc)> 0){
	                    $mailer->bcc($bcc);
	                }
	                if(count($cc) > 0){
	                    $mailer->bcc($cc);
	                }
	            	$mailer->send(new OrderCreated($user,$ord,$carts,$payment_method,  $complete_path , $payments));

	        	}else if($ord->transaction_type == 'return'){

	        		$mail_res = $this->get_email_triggers(['v_id' => $v_id ,'store_id' => $store_id , 'email_trigger_code' => 'order_returned']);
	                $to = $mail_res['to'];
	                $cc = $mail_res['cc'];
	                $bcc = $mail_res['bcc'];

	                //dd($cc);
	                $mailer = Mail::to($user->email); 
	                if(count($bcc)> 0){
	                    $mailer->bcc($bcc);
	                }
	                if(count($cc) > 0){
	                    $mailer->bcc($cc);
	                }
	        		$mailer->send(new OrderReturn($user,$ord,$carts,$payment_method,  $complete_path , $payments));

	        	}
	        }
	    

	        return [ 'satus' => 'success' , 'message' => 'Email has been sent successfully' ];

	    }else{
	    	return [ 'satus' => 'fail' , 'message' => 'User is not completed the payment' ];
	    }
	}



}