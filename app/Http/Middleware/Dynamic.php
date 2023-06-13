<?php

namespace App\Http\Middleware;

use Closure;
use App\Vendor;
use Auth;
use App\Organisation;
use App\VendorDetails;

use Illuminate\Support\Facades\Artisan;

use DB;

class Dynamic
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Pre-Middleware Action
        // dd($request->all());
        // if($request->has('mobile')) {
        //     dynamicConnection($request->mobile);
        // } else {

            if ($request->has('v_id') || $request->has('organisation_code') ) {
                 $timezone = [];
                if($request->has('organisation_code')){
                    $organisation = Organisation::where('ref_vendor_code',$request->organisation_code)->first();
                }else{
          
                $organisation = Organisation::find($request->get('v_id'));
                $timezone = getTimeZone($request->get('v_id'),$request->get('store_id'));
                }
                
                /*** Shweta T * Date: 22/06/2020 */
                if(!$organisation)
                    return response()->json(['status' => 'fail', 'message' => 'Unable to find Organisation'], 200);
			
                if($organisation->db_type == 'MULTITON'){
                    $db_name = $organisation->db_name;
                    if($db_name){
                        if(config('database.default') == 'mysql') {
                            //$organisation = Organisation::find($request->v_id);
                            //$vUser = DB::table($organisation->db_name.'.vender_users_auth')->where('vu_id', $request->vu_id)->first();
                            //dynamicConnection($organisation->db_name);
                        $connPrm    =array('host'=> $organisation->connection->host,'port'=>$organisation->connection->port,'username'=>$organisation->connection->username,'password' =>  $organisation->connection->password,'db_name'=>$db_name);
                        dynamicConnectionNew($connPrm);
                        }
                    }
                }else{
                    if(count($timezone)>0){
                        //date_default_timezone_set($timezone['timezone']);
                        // date_default_timezone_set(config('app.timezone', 'Asia/Dhaka'));

                        //echo date('Y:m:d h:i:s');
                        //config(['database.connections.mysql.timezone'=>"$timezone['utf_time']"]);
                        //Artisan::call('cache:clear');
                        //DB::reconnect();
                    }
                }

                if(count($timezone)>0){
                    date_default_timezone_set($timezone['timezone']);
                }else{
                    date_default_timezone_set('Asia/Kolkata');
                }


            
                   
            } 
            // elseif ($request->has('vu_id')) {
            //     if(config('database.default') == 'mysql') {
            //         $vUser = Vendor::find($request->vu_id);
            //         dynamicConnection($vUser->mobile);
            //     }
            // }
        // }

        $response = $next($request);

        // Post-Middleware Action



        return $response;
    }
}
