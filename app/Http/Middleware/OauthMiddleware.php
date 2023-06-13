<?php

namespace App\Http\Middleware;

use Closure;
use App\Model\Oauth\OauthClient;
use App\Model\Client\ClientVendorMapping;

class OauthMiddleware
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
        $token = $request->bearerToken();
        
        $client =  OauthClient::where('token', $token)->first();

        if(!$client){
         $client   = ClientVendorMapping::where('token', $token)->first();

        }
        //dd( $client);
        $current_time  = $date = date('Y-m-d H:i:s');
        if(!$client || $token==''){
            return response()->json([ 'status' => 'fail', 'message' => 'Unauthorized'], 401);
        }
        
        if($client &&  $current_time>$client->token_expair_at){

            return response()->json([ 'status' => 'fail', 'message' => 'Unauthorized'], 401);

        }
        
        return $next($request);
    }

    /**
     * Get the bearer token from the request headers.
     *
     * @return string|null
    */
    public function bearerToken()
    {
       $header = $this->header('Authorization', '');
       
       if (Str::startsWith($header, 'Bearer ')) {
            return Str::substr($header, 7);
       }
    }
}
