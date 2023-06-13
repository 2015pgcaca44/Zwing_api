<?php

namespace App\Http\Middleware;

use Closure;

class VendorAuthenticate
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
      
        if ($request->input('api_token') == null) {
            return response()->json([ 'status' => 'redirect_to_home', 'message' => 'Unauthorized'], 401);
        }
        return $next($request);
    }
}
