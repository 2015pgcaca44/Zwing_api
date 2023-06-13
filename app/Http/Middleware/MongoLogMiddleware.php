<?php
/**
 * Created by PhpStorm.
 * User: sudhanshuigi
 * Date: 15/11/18
 * Time: 11:45 AM
 */

namespace App\Http\Middleware;

use App\LogCollection;
use Closure;
use DB;
use Illuminate\Support\Facades\Artisan;
use App\Store;
use App\User;
use App\Jobs\ApilogJob;

/*use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
*/

class MongoLogMiddleware {
	public function handle($request, Closure $next) {


		
		
		 //dd($request->getPathInfo());
		$response = $next($request);
		//dd(env('DB_CONNECTION'));
		if(env('APP_ENV') == 'development' || env('APP_ENV') == 'demo' || env('APP_ENV') == 'staging' || env('APP_ENV') == 'test' || env('APP_ENV')=='local' ){
   
			if (strpos($request->getPathInfo(), 'v1') !== false) {
				DB::disconnect();
				config(['database.default' => 'mysql']);
				Artisan::call('cache:clear');
				DB::reconnect();
			} else {
				DB::disconnect();
				config(['database.default' => 'mysql1']);
				Artisan::call('cache:clear');
				DB::reconnect();
			}
			// Pre-Middleware Action
			date_default_timezone_set('Asia/Kolkata');

			// Post-Middleware Action

			$params = $request->all();
			$queryString = http_build_query($params);
			$url = $request->url() . '?' . $queryString;
			$method = $request->getMethod();
			$path = $request->getPathInfo();
		   //dd($path);
			
			$status = $response->getStatusCode();
             if($path!='product-search'){

			if (strpos($url, 'table-sync') !== false || strpos($url, 'mongo') !== false) {
				$responseData = '';
			} else {
				$responseData = $response->getContent();
			}
			$logarr = array('api_name' => $path, 'status' => $status, 'url' => $url,
				//'para' => $para ,
				'data' => $responseData,
				'date' => date('Y-m-d H:m:s'));
       //dd($logarr); 
			$logCollection = new LogCollection;
			$logCollection->api_name = $path;
			$logCollection->status = $status;
			$logCollection->url = $url;
			$logCollection->data = $responseData;
			$logCollection->date = date('Y-m-d H:i:s');
			$logCollection->save();

			// echo LogCollection::all();
			//        LogCollection::create($logarr);
			// dd($response);
		  }	

		}
		return $response;
		
	
	
		
		/*
		//shw start 310720 
		$response = $next($request);
       $params['params'] = $request->all(); 
        $params['url'] = $request->url();
        $params['getMethod'] = $request->getMethod();
        $params['getPathInfo'] = $request->getPathInfo();
        $params['getStatusCode'] = $response->getStatusCode();
        $params['getContent'] = $response->getContent();

		dispatch(new ApilogJob($params)); 

		//shw end 310720  - All below code done via ApilogJob
		//dd(env('DB_CONNECTION'));
		if(env('APP_ENV') == 'development' || env('APP_ENV') == 'demo' || env('APP_ENV') == 'staging' || env('APP_ENV') == 'test' || env('APP_ENV')=='local' ){
   
			if (strpos($request->getPathInfo(), 'v1') !== false) {
				DB::disconnect();
				config(['database.default' => 'mysql']);
				Artisan::call('cache:clear');
				DB::reconnect();
			} else {
				DB::disconnect();
				config(['database.default' => 'mysql']);
				Artisan::call('cache:clear');
				DB::reconnect();
			}
			// Pre-Middleware Action
			date_default_timezone_set('Asia/Kolkata');

			// Post-Middleware Action

			$params = $request->all();
			$queryString = http_build_query($params);
			$url = $request->url() . '?' . $queryString;
			$method = $request->getMethod();
			$path = $request->getPathInfo();
			$v_id = isset($params['v_id']) ? $params['v_id'] :'' ;
			$storeId = isset($params['storeId']) ? $params['storeId'] :'' ;
			$client = isset($params['c_id']) ? $params['c_id'] :'' ;
			if(!empty($storeId))
			{
				if( $store = Store::where('store_id',$storeId)->first() )
				$storeId = '#'.$storeId." ".$store->name;
			}

			if(!empty($client))
			{  
				if( $user = User::find($client) )
				$client = '#'.$client." ".$user->first_name;
			}

			$status = $response->getStatusCode();
             if($path!='product-search'){

			if (strpos($url, 'table-sync') !== false || strpos($url, 'mongo') !== false) {
				$responseData = '';
			} else {
				$responseData = $response->getContent();
			}
			$logarr = array('api_name' => $path, 'status' => $status, 'url' => $url,
				//'para' => $para ,
				'data' => $responseData,
				'date' => date('Y-m-d H:m:s'));
       			//dd($logarr); 
			$logCollection = new LogCollection;
			$logCollection->api_name = $path;
			$logCollection->status = $status;
			$logCollection->url = $url;
			$logCollection->data = $responseData;
			$logCollection->v_id = $v_id;
			$logCollection->store_id = $storeId;
			$logCollection->client = $client;
			$logCollection->date = date('Y-m-d H:i:s');
			$logCollection->save();

			// echo LogCollection::all();
			//        LogCollection::create($logarr);
			// dd($response);
		  }	

		}
		return $next($request); */

	}
}