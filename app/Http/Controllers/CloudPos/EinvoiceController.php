<?php

namespace App\Http\Controllers\CloudPos;

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
use DB;
 

class EinvoiceController extends Controller
{
	public function __construct()
    {
        $this->middleware('auth' , ['except' => ['callEinvoice','downloadEinvoice'] ]);
        $this->cartconfig  = new CartconfigController;     
    }


    public function callEinvoice(Request $request){
    	$v_id        = $request->v_id;
    	$store       = $request->store;
    	$invoice_id  = $request->invoice_id;
    	$setting     =  DB::table('store_settings')->where('name','Einvoice')->first();
        $invoice     =  Invoice::where('invoice_id',$invoice_id)->first();
        $BuyerDtls  =  User::leftjoin('addresses','addresses.c_id','customer_auth.c_id')->where('customer_auth.c_id',$invoice->user_id)->select('customer_auth.*','addresses.address1','addresses.address2','addresses.landmark','addresses.pincode','addresses.state_id')->first();

        /*Validation Start*/
        $checkError = 0;
        if($invoice->comm_trans != 'B2B'){
        	$checkError++;
        	$Errmessage = 'Einvoice generate only for B2B bill';
        }else if(empty($BuyerDtls->gstin) || empty($BuyerDtls->state_id) || empty($BuyerDtls->address1) || empty($BuyerDtls->landmark) || empty($BuyerDtls->pincode)){
        	$checkError++;
        	$Errmessage = 'Please fill all buyer details: Gstin, State, Address, Landmark, Pincode';
        }
        if($checkError > 0){
        	return response()->json(['status'   => 'error', 'message' => $Errmessage],422); 
        }
        /*Validation End*/



        $yearConvert =  date('Y',strtotime($invoice->date));   
        $financeYear =  date('y',strtotime("+12 months $invoice->date")); 
        $monthConvert = date('m',strtotime($invoice->date));   
        //"$yearConvert-$financeYear"
        $params    = array('v_id'=>$v_id,'settings'=> $setting->settings,'method'=>'POST','invoice_id'=>$invoice->invoice_id,'return_year'=>"2020-21",'return_month'=>$monthConvert);
        //print_r($params);die;
        $eInvoice  = new \App\Http\Controllers\Einvoice\EinvoiceController($params);
        $resultGenerateInvoce    = $eInvoice->generateEinvoice($params);
        //$resultGenerateInvoce->getData() 
        if($resultGenerateInvoce->status() == 200){
        	sleep(10);
        	$resultStatus    = $eInvoice->IrnStatus($params);
        	if($resultStatus->status() == 200){

        		$msg = $resultStatus->getData()->message;
        		 return response()->json(['status'   => 'success', 'message' => $msg],200); 
        	}else{
        		return response()->json(['status'   => 'error', 'message' => 'please try again '],422); 
        	}
        }else{
        	return response()->json(['status'   => 'error', 'message' => $resultGenerateInvoce->getData()->message],422); 
        }
        //
        //return $result;
    }//End of callEinvoice


    public function downloadEinvoice(Request $request){
        $v_id        = $request->v_id;
        $store       = $request->store;
        $invoice_id  = $request->invoice_id;
        $setting     =  DB::table('store_settings')->where('name','Einvoice')->first();
        $invoice     =  Invoice::where('invoice_id',$invoice_id)->first();
        $monthConvert = date('m',strtotime($invoice->date));   
        if($invoice){
            $recordExist = EinvoiceDetails::where('invoice_id',$invoice_id)->where('status','Success')->first();
            if($recordExist){
              $params    = array('v_id'=>$v_id,'settings'=> $setting->settings,'method'=>'POST','invoice_id'=>$invoice->invoice_id,'return_year'=>"2020-21",'return_month'=>$monthConvert);

              $eInvoice  = new \App\Http\Controllers\Einvoice\EinvoiceController($params);
              $resultGenerateInvoce    = $eInvoice->checkNewOne($params);

            }else{
                return response()->json(['status'   => 'error', 'message' => 'Einvoice not generate.'],422);     
            }
        }else{
            return response()->json(['status'   => 'error', 'message' => 'No invoice found.'],422); 
        }

    }//End of downloadEinvoice
}

?>