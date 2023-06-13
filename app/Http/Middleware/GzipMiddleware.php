<?php

namespace App\Http\Middleware;

use Closure;

class GzipMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        $content = $response->content();
        $data = gzencode($content, 9); 

        if($response->status() == 200){
            return response($data)->withHeaders([
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods'=> 'GET',            
                'Content-type' => 'application/json; charset=utf-8',
                'Content-Length'=> strlen($data),
                'Content-Encoding' => 'gzip'
            ]);
        } else{
            return $response;
        }
    }
}