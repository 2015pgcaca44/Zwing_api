<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Response;
use Storage;
use Log;

class Throttle
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1)
    {
        $key = $this->resolveRequestSignature($request);

        try{
            $filename = storage_path('app/throttle').'/'.$key.'.txt';

            if(file_exists($filename) ){
                $myfile = fopen($filename, "r"); //Read and write mode
                $content = fread( $myfile, filesize($filename) );
                $arr = explode(':',$content);
                $counter = $arr[0];
                $previousTime = $arr[1];
                $remaining = round( abs(time() - $previousTime ) , 2 );

                fclose($myfile);

                if($remaining >= ($decayMinutes * 60)){// If Expire

                    $myfile = fopen($filename, "w");
                    fwrite($myfile,'0:'.time());
                    fclose($myfile);

                }else{// Time is not Expire

                    if((int)$counter >= $maxAttempts ){
                        
                        return response()->json([ 'status' => 'fail', 'message' => 'Too Many Attempts'], 429);
                    }

                    $myfile = fopen($filename, "w");

                    $counter = (int)$counter + 1;
                    fwrite($myfile,$counter.':'.$previousTime);
                    fclose($myfile);
                }

            }else{
                $myfile = fopen($filename, "w");
                fwrite($myfile,'0:'.time());
                fclose($myfile);

            }

        }catch(\Exception $e){
            Log::error($e);
        }
        // dd($key);
        
        //Deleting all file in throttle folder if any request is hit at 23:55 which include throttle middleware
        $deldate = date('H:i');
        if($deldate == '23:55'){
            $path = storage_path('app/throttle');
            $files = glob($path.'/*'); // get all file names
            foreach($files as $file){ // iterate files
              if(is_file($file)){
                unlink($file); // delete file
              }
            }
        }

        return $next($request);

    }

    /**
     * Resolve request signature.
     *
     * @param  \Illuminate\Http\Request $request
     *
     * @return string
     */
    protected function resolveRequestSignature($request)
    {
        return sha1(
            $request->method() .
            '|' . $request->server('SERVER_NAME') .
            '|' . $request->path() .
            '|' . $request->ip()
        );
    }

    /**
     * Get the bearer token from the request headers.
     *
     * @return string|null
    */
    public function tooManyAttempts($key, $maxAttempts, $decayMinutes){
        if(Cache::store('file')->get($key) >= $maxAttempts){
            return true;
        }else{
            return false;
        }
       
    }

    public function hit($key, $decayMinutes){
        
        if(Cache::has($key)){
            
            Cache::increment($key);
        }
        Cache::add($key,0,$decayMinutes * 60);

        // dd(Cache::store('file')->get($key));
    }

    public function attempts($key){
        return Cache::get($key);
    }


    /**
     * Add the limit header information to the given response.
     *
     * @param  \Illuminate\Http\Response $response
     * @param  int                       $maxAttempts
     * @param  int                       $remainingAttempts
     * @param  int|null                  $retryAfter
     *
     * @return \Illuminate\Http\Response
     */
    protected function addHeaders(Response $response, $maxAttempts, $remainingAttempts, $retryAfter = null)
    {
        $headers = [
            'X-RateLimit-Limit'     => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ];

        if (!is_null($retryAfter)) {
            $headers['Retry-After'] = $retryAfter;
        }

        $response->headers->add($headers);
        $response->headers->remove('Cache-Control');

        return $response;
    }

    /**
     * Calculate the number of remaining attempts.
     *
     * @param  string $key
     * @param  int    $maxAttempts
     *
     * @return int
     */
    protected function calculateRemainingAttempts($key, $maxAttempts)
    {
        return $maxAttempts - $this->attempts($key) + 1;
    }

}
