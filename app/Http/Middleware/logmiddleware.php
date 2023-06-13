<?php

namespace App\Http\Middleware;

use Closure;

class logmiddleware
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
        
         //dd("ok");
        // Pre-Middleware Action

        $response = $next($request);

        // Post-Middleware Action
        // 
       // echo ($request->getPathInfo().($request->getQueryString() ? ('?' . $request->getQueryString()) : ''));exit;

        //$url       = $request->fullUrl(); 
        //$url       = ($request->getPathInfo().($request->getQueryString() ? ('?' . $request->getQueryString()) : ''));
        $params = $request->all();
        //dd($params);
        $queryString = http_build_query($params);
        $url = $request->url().'?'.$queryString;
        //$para = $request->url().' \n '.str_replace('&', '\n', $queryString);
        $method    = $request->getMethod();
        $path      = $request->getPathInfo();
        $status    = $response->getStatusCode();

        /*Api Log Maintain*/
        /*echo "<pre>";
        $filePath    = "logfile/newfile.json";
        $str         = file_get_contents($filePath);
        $jsonData    = json_decode($str, true); 
        print_r($jsonData);die;*/

        $filename = date('Ymd').'_file.json';
        $filePath = "logfile/".$filename;
        if(file_exists($filePath)){
            $str         = file_get_contents($filePath);
            $jsonData    = json_decode($str, true); 
        }else{
            $logfile = fopen($filePath, "w") or die("Unable to open file!");
            $logarr  = $data = array('api_name'=>'Api Name','status'=>'Status','url'=>'Url','para' => 'Para','data'=>'Response Content','date'=>"Date");
             $txt     = "[".json_encode($logarr)."]";
            fwrite($logfile, $txt);
            fclose($logfile);

            $str         = file_get_contents($filePath);
            $jsonData    = json_decode($str, true);  
        }
             
        $arr = array();
        foreach($jsonData as $a){
            $arr[] = $a;
        }

        if(strpos($url , 'table-sync')!==false){
            $responseData = '';
        }else{
            $responseData = $response->getContent();
        }
        $logarr = array('api_name'=>$path,'status'=>$status,'url'=>$url, 
            //'para' => $para ,
             'data'=>$responseData,
             'date'=>date('Y-m-d H:m:s'));
        $arr[] = $logarr;

        $logfile = fopen($filePath, "w") or die("Unable to open file!");
        $txt     = json_encode($arr,true);
        fwrite($logfile, $txt);
        fclose($logfile);

        return $response;
    }
}
