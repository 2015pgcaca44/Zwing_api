<?php

namespace App\Http\Middleware;

use App\Http\Controllers\VendorSettingController;
use App\SettlementSession;
use DB;
use App\Vendor;
use Closure;

class VendorMiddleware
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
        return $next($request);
    }
}
