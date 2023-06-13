<?php

namespace App\Http\Controllers\Oauth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use DB;
use Payment;
use Validator;
use App\Order;
use App\Cart;
use App\Address;
use App\User;
use App\SyncReports;
use App\Jobs\ItemFetch;
use App\Invoice;
use App\InvoicePush;
use App\InvoiceDetails;
use App\Model\Oauth\OauthClient;
use App\Model\Client\ClientVendorMapping;


class OauthController extends Controller
{

    public function __construct(){
    
        
    }

    public function getToken(Request $request){

        $validator = Validator::make($request->all(), [
            'grant_type' => 'required'
              
        ]);

        if($validator->fails()){
            return response()->json(['status' => 'fail' , 'message' => 'Validation fail' , 'errors' => $validator->errors() ] , 422);
        }

        $this->validate($request,  [
            'grant_type' => 'required'
              
        ]);

        if($request->has('grant_type')){
           

            
            $grant_type = $request->grant_type;
            
            if($grant_type == 'password'){
                //validation
                $validator = Validator::make($request->all(),  [
                    'client_id' => 'required',
                    'username' => 'required',
                    'password' => 'required',
                ]);


                if($validator->fails()){
                    return response()->json(['status' => 'fail' , 'message' => 'Validation fails' , 'errors' => $validator->errors() ] , 422);
                }

                $client_id = $request->client_id ;
                $username = $request->username;
                $password = $request->password;

                $client = OauthClient::where('client_id',$client_id)
                                       ->first();
                $current_time  = $date = date('Y-m-d H:i:s');

                if($client){
                        if($client->id==1){

                       $clientvendor      =  ClientVendorMapping::where('client_id',$client->id)
                                                   ->where('username', $username)
                                                   ->first();
                            if($clientvendor){

                                if(Hash::check($password, $clientvendor->password)){

                                   if($current_time<$clientvendor->token_expair_at){
                                    
                                    $data = ['token' => $clientvendor->token ];
                                   }else{
                                    $expairtime = date('Y-m-d H:i:s', strtotime('30 minute'));
                                    $clientvendor->token = $token = str_random(70);
                                    $clientvendor->token_expair_at=$expairtime;
                                    $clientvendor->save();

                                    $data = ['token' => $token  ];
                                    }
                                    return response()->json( ['status' => 'success' , 'message' => 'Credentials Match' , 'data' => $data ], 200);
                                }else{

                                    return response()->json(['status' => 'fail' , 'message' => 'Incorrect Password'] , 401);
                                }
                            }else{
                            
                             return response()->json(['status' => 'fail' , 'message' => 'Client ID / Username Does not exists'] , 401);

                            }                           

                        }else{

                           $client = OauthClient::where('client_id',$client_id)
                                                   ->where('username', $username)
                                                   ->first();

                            if(Hash::check($password, $client->password)){

                               if($current_time<$client->token_expair_at){
                                
                                $data = ['token' => $client->token ];
                               }else{
                                $expairtime = date('Y-m-d H:i:s', strtotime('30 minute'));
                                $client->token = $token = str_random(70);
                                $client->token_expair_at=$expairtime;
                                $client->save();

                                $data = ['token' => $token  ];
                                }
                                return response()->json( ['status' => 'success' , 'message' => 'Credentials Match' , 'data' => $data ], 200);
                            }else{

                                return response()->json(['status' => 'fail' , 'message' => 'Incorrect Password'] , 401);
                            }
                        }    
                }else{
                    return response()->json(['status' => 'fail' , 'message' => 'Client ID / Username Does not exists'] , 401);
                }


            }

        }
    }

   


}