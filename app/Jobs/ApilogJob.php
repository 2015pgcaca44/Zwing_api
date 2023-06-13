<?php
/**
 * @author: Shweta T
 * @Discription : Save api log into mongodb
 * Date: 31/07/2020
 */

namespace App\Jobs;
use Illuminate\Http\Request;
use App\LogCollection;
use Closure;
use DB;
use Illuminate\Support\Facades\Artisan;
use App\Store;
use App\User;
use App\Vendor;

class ApilogJob extends Job
{
    /**
     * Create a new job instance.
     *
     * @return void
     */

    private $funArgs;
    public function __construct($funArgs)
    {
        $this->funArgs = $funArgs;
    }


    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(Request $request)
    {
        $params = $this->funArgs['params'] ;
        $api_url = $this->funArgs['url'] ;
        $getMethod = $this->funArgs['getMethod'] ;
        $getPathInfo = $this->funArgs['getPathInfo'] ;
        $getStatusCode = $this->funArgs['getStatusCode'] ;
        $getContent = $this->funArgs['getContent'] ;
             
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
            date_default_timezone_set('Asia/Kolkata');
            //print_r($params);
            $queryString = http_build_query($params);
            $url = $api_url . '?' . $queryString;
            $method = $getMethod;
            $path = $getPathInfo;
            $v_id = isset($params['v_id']) ? $params['v_id'] :'' ;
            $storeId = isset($params['store_id']) ? $params['store_id'] :'' ;
            if(empty($storeId))
            {
                $storeId = isset($params['storeId']) ? $params['storeId'] :'' ;
            }
            $client = isset($params['c_id']) ? $params['c_id'] :'' ;


            
            if(!empty($v_id))
            {
                if( $vendor = Vendor::where('v_id',$v_id)->first() )
                $v_id = '#'.$v_id." ".$vendor->first_name;
            }

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

            $status = $getStatusCode;
             if($path!='product-search'){

            if (strpos($url, 'table-sync') !== false || strpos($url, 'mongo') !== false) {
                $responseData = '';
            } else {
                $responseData = $getContent;
            }
            $logarr = array('api_name' => $path, 'status' => $status, 'url' => $url,
                //'para' => $para ,
                'data' => $responseData,
                'date' => date('Y-m-d H:m:s'));
    
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

            $id = $logCollection->id;
            $apilog_url = env("ADMIN_PATH").'/apilog/'.$id ;
            //echo "Apilog Saved at ".date('Y-m-d H:i:s')." - ". $apilog_url."<br>";
            if($status != 200)
            {
              switch ($status) {
                    case 100: $text = 'Continue'; break;
                    case 101: $text = 'Switching Protocols'; break;
                    //case 200: $text = 'OK'; break;
                    case 201: $text = 'Created'; break;
                    case 202: $text = 'Accepted'; break;
                    case 203: $text = 'Non-Authoritative Information'; break;
                    case 204: $text = 'No Content'; break;
                    case 205: $text = 'Reset Content'; break;
                    case 206: $text = 'Partial Content'; break;
                    case 300: $text = 'Multiple Choices'; break;
                    case 301: $text = 'Moved Permanently'; break;
                    case 302: $text = 'Moved Temporarily'; break;
                    case 303: $text = 'See Other'; break;
                    case 304: $text = 'Not Modified'; break;
                    case 305: $text = 'Use Proxy'; break;
                    case 400: $text = 'Bad Request'; break;
                    case 401: $text = 'Unauthorized'; break;
                    case 402: $text = 'Payment Required'; break;
                    case 403: $text = 'Forbidden'; break;
                    case 404: $text = 'Not Found'; break;
                    case 405: $text = 'Method Not Allowed'; break;
                    case 406: $text = 'Not Acceptable'; break;
                    case 407: $text = 'Proxy Authentication Required'; break;
                    case 408: $text = 'Request Time-out'; break;
                    case 409: $text = 'Conflict'; break;
                    case 410: $text = 'Gone'; break;
                    case 411: $text = 'Length Required'; break;
                    case 412: $text = 'Precondition Failed'; break;
                    case 413: $text = 'Request Entity Too Large'; break;
                    case 414: $text = 'Request-URI Too Large'; break;
                    case 415: $text = 'Unsupported Media Type'; break;
                    case 500: $text = 'Internal Server Error'; break;
                    case 501: $text = 'Not Implemented'; break;
                    case 502: $text = 'Bad Gateway'; break;
                    case 503: $text = 'Service Unavailable'; break;
                    case 504: $text = 'Gateway Time-out'; break;
                    case 505: $text = 'HTTP Version not supported'; break;
                    default:
                        exit('Unknown http status code "' . htmlentities($code) . '"');
                    break;
                }

                $msg = "Attention! Transaction error found on live server! :man-facepalming:";
                $webhookurl = env("LOG_SLACK_WEBHOOK_URL");
                $useragent = "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)";
                $apilog_link = '<a href="https://admin.gozwing.com/"> apilog </a>';
                $payload = 'payload={"channel": "#'. env("LOG_CHANNEL").'",
                                    "username": "webhookbot", 
                                    "icon_emoji": ":shweta:",
                    "blocks": [
                        {
                            "type": "section",
                            "text": {
                                "type": "mrkdwn",
                                "text": "'.$msg.'"
                            }
                        },
                        {
                            "type": "section",
                            "fields": [
                                {
                                    "type": "mrkdwn",
                                    "text": "*Response Status Code:*\n'.$status." - ".$text.'   "
                                },
                                {
                                    "type": "mrkdwn",
                                    "text": "*Environment:*\n'.$api_url.' "
                                },
                                {
                                    "type": "mrkdwn",
                                    "text": "*Vendor | Store | User :*\n'.$v_id.' | '.$storeId.' | '.$client.' "
                                },
                                {
                                    "type": "mrkdwn",
                                    "text": "*Api Log Link:*\n'.$apilog_url.'"
                                }
                               
                            ]
                        }
                    ]
                }';

            //echo $payload ;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_USERAGENT, $useragent); //set our user agent
            curl_setopt($ch, CURLOPT_POST, TRUE); //set how many paramaters to post
            curl_setopt($ch, CURLOPT_URL,$webhookurl); //set the url we want to use
            curl_setopt($ch, CURLOPT_POSTFIELDS,$payload); 
            curl_exec($ch); //execute and get the results
            curl_close($ch);
        }   


          } 
        }
    }

}
