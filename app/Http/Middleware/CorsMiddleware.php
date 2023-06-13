<?php
namespace App\Http\Middleware;

use Closure;

class CorsMiddleware
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

        if(env('APP_ENV') == 'development' || env('APP_ENV') == 'demo' || env('APP_ENV') == 'staging' || env('APP_ENV') == 'test' ){


            $domains = ['https://dev.app.gozwing.com','https://test.app.gozwing.com','https://demo.app.gozwing.com','https://staging.app.gozwing.com','https://dev.api.gozwing.com','https://prod2.webpos.gozwing.com','http://localhost:8080','http://localhost:8081','https://offline.gozwing.com','http://localhost:5000','http://192.168.3.139:8080','http://192.168.3.124:8080','http://192.168.3.131:8080','http://192.168.3.131:8081','http://192.168.3.110','http://localhost','http://192.168.3.110:8080','http://192.168.1.40','http://192.168.1.40:84','http://192.168.3.122:8080','http://192.168.3.126:8080','http://192.168.3.120:8080','http://192.168.3.149:8080' ,'http://192.168.1.30:8080' ,'http://192.168.3.153:8080','http://192.168.1.21:8080','http://192.168.1.127','http://192.168.1.156','http://192.168.1.133:84','http://192.168.1.139:8080', 'http://localhost:8008','http://192.168.1.48','http://192.168.1.200','http://192.168.0.105','http://192.168.0.105:84','http://192.168.1.5:84'];

        }else{

            $domains = ['https://app.gozwing.com','https://dev.api.gozwing.com','https://prod2.webpos.gozwing.com','http://localhost:8080','https://offline.gozwing.com','http://localhost:5000','http://192.168.1.40:84/git-api/public','http://localhost:84','http://192.168.1.48','http://192.168.1.200','http://192.168.0.105:84','http://192.168.1.5:84'];

        }
        
        $headers = null;
        if(isset($request->server()['HTTP_ORIGIN'])) {
            $origin = $request->server()['HTTP_ORIGIN'];
              /*  Shweta 110620 : Commneted to work with local env   */
           // if(in_array($origin, $domains)) { 
                // header('Access-Control-Allow-Origin: '. $origin);
                // header('Access-Control-Allow-Methods: POST, GET');
                // header('Access-Control-Allow-Headers: Origin, Content-Type, Authorization');
                $headers = [
                    'Access-Control-Allow-Origin'      => $origin,
                    'Access-Control-Allow-Methods'     => 'POST, GET',
                    //'Access-Control-Allow-Methods'     => 'POST, GET, OPTIONS, PUT, DELETE',
                    'Access-Control-Allow-Credentials' => 'true',
                    'Access-Control-Max-Age'           => '86400',
                    'Access-Control-Allow-Headers'     => 'Content-Type, Authorization, X-Requested-With'
                ];
           // }

            if ($request->isMethod('OPTIONS'))
            {
                return response()->json('{"method":"OPTIONS"}', 200, $headers);
            }

            $response = $next($request);
            foreach($headers as $key => $value)
            {
                $response->header($key, $value);
            }

            return $response;
        } else {
            return $next($request);
        }
    }
}