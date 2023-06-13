<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;
use App\Http\Controllers\Spar\DatabaseOfferController;
use App\Http\Controllers\DataPushApiController;
use DB;
use App\VendorDataSync;
use App\Console\Commands\WebSocketServer;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */

    protected $commands = [
      Commands\WebSocketServer::class,
      Commands\PushAPI::class,
      Commands\SendEmails::class,
      //\Laravelista\LumenVendorPublish\VendorPublishCommand::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {



        //Log::info('Start Cool');
        $schedule->call(function () {
          $scheduler = new SchedulerController;
          $scheduler->makeOpeningStock();
        })->dailyAt('14:20');

       $schedule->command('push:invoice')->everyThirtyMinutes()
            ->onSuccess(function (Stringable $output) {
              // The task succeeded...
            })
            ->onFailure(function (Stringable $output) {
            // The task failed...
            }) 
      ->appendOutputTo(storage_path('logs/examplecommand.log'));

     /* //Deleting all Throttle file 
      $schedule->call(function () {
        $path = storage_path('app/throttle');
        $files = glob($path.'/*'); // get all file names
        foreach($files as $file){ // iterate files
          if(is_file($file)){
            unlink($file); // delete file
          }
        }
      })->dailyAt('23:55');

      $schedule->command('push:invoice')->withoutOverlapping()->appendOutputTo(storage_path('logs/examplecommand.log'));*/

      // DB::table('wishlists')->truncate();
      
      // $sync = VendorDataSync::where('status', '1')->get();

      // foreach ($sync as $key => $value) {
      //   $schedule->call(function () use ($value) {
      //     $request = new \Illuminate\Http\Request();
      //     $request->merge([
      //       'v_id' => $value->v_id
      //     ]);
      //     $datapush = new DataPushApiController;
      //     $datapush->dataSync($request);
      //     // VendorDataSync::find(1)->update([ 'status' => '0' ]);
      //   })->cron('*/'.$value->duration.' * * * * *');
      // }

      // $schedule->command('queue:work --tries=3')->everyMinute();
      // $schedule->call(function () {
        // echo 'Cool';
      //      DB::table('wishlists')->truncate();
      //     // $datapush = new DataPushApiController;
      //     // $datapush->dataSync();
        // })->everyMinute();
       
        /*
          
           $schedule->call('App\Http\Controllers\Vmart\DataPushApiController@dataSync')->everyThirtyMinutes();
        $schedule->command('queue:work')->everyThirtyMinutes();
        $schedule->call(function () {
			$date = date('Y-m-d');
			$page = 1;
            //DB::table('spar_uat.cron_log')->insert( ['message' => 'Logging is done at '.date('d-m-Y H:i:s')] );
			//DB::table('spar_uat.cron_log')->insert( ['message' => 'Logging is done at '.date('d-m-Y H:i:s') , 'log_date' =>$date , 'page' => $page ] ); 
			$cron_log = DB::table('spar_uat.cron_log')->where('log_date',$date)->first();
            if($cron_log){
               $page += $cron_log->page;
                DB::table('spar_uat.cron_log')->where('log_date',$date )->update( ['message' => 'Logging is done at '.date('d-m-Y H:i:s'), 'page' => $page] );
            }else{
               DB::table('spar_uat.cron_log')->insert( ['message' => 'Logging is done at '.date('d-m-Y H:i:s') , 'log_date' =>$date , 'page' => $page ] ); 
            }
        })->everyMinute();*/
        /*

        $schedule->call(function () {
            
            $date = date('Y-m-d');
            $page = 1;
            $cron_log = DB::table('spar_uat.cron_log')->where('log_date',$date)->first();
            if($cron_log){
               $page += $cron_log->page;
                DB::table('spar_uat.cron_log')->where('log_date',$date )->update( ['message' => 'Logging is done at '.date('d-m-Y H:i:s'), 'page' => $page] );
            }else{
               DB::table('spar_uat.cron_log')->insert( ['message' => 'Logging is done at '.date('d-m-Y H:i:s') , 'log_date' =>$date , 'page' => $page ] ); 
            }
            

            $request = new \Illuminate\Http\Request();
            $request->replace(['page' => $page]);

            $databaseOfferC = new DatabaseOfferController;
            $databaseOfferC->create_offers($request);

         })->everyMinute()->timezone('Asia/Calcutta')->between('01:00', '04:00'); 
		 
  		$schedule->call(function () {

  			//DB::table('cron_log')->insert(['name' =>'rt_log' , 'response' => 'start'  ]);
              $date = date('Y-m-d');
              $v_id = '4';
              $store_id = '5';
              $request = new \Illuminate\Http\Request();
              $request->replace(['v_id' => $v_id, 'store_id' => $store_id , 'date' => $date]);

              $cart_c = new \App\Http\Controllers\Spar\CartController;
              $response = $cart_c->rt_log($request);

              DB::table('cron_log')->insert(['v_id' => $v_id, 'store_id'=> $store_id, 'name' =>'rt_log' , 'response' => json_encode($response)  ]);

      })->dailyAt('22:55');
      //})->twiceDaily('17:21', '17:23');
  		
  		$schedule->call(function () {
  			
  			$carts = DB::table('temp_cart')->where('status','process')->get();
  			
  			foreach($carts as $cart){
  				
  				DB::table('temp_cart')->where('cart_id' , $cart->cart_id)->delete();
          DB::table('temp_cart_details')->where('cart_id' , $cart->cart_id)->delete();
          DB::table('temp_cart_offers')->where('cart_id' , $cart->cart_id)->delete();
  				
  				DB::table('orders')->where('user_id' , $cart->user_id)->where('v_id', $cart->v_id)->where('store_id', $cart->store_id)->where('o_id', $cart->order_id)->delete();
  			}
  			DB::table('cron_log')->insert(['name' =>'cleaning' , 'response' => ''  ]);
  		})->dailyAt('23:00');
      */


    }
}
