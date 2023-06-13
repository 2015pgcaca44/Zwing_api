<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\Http\Controllers\WebSocketController;

class WebSocketServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'websocket:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $app = new \Ratchet\Http\HttpServer(
            new \Ratchet\WebSocket\WsServer(
                new WebSocketController()
            )
        );

        if(in_array(env('APP_ENV'), ['test', 'development', 'demo', 'staging', 'production'])) {
            $loop = \React\EventLoop\Factory::create();

            $secure_websockets = new \React\Socket\Server('0.0.0.0:8090', $loop);

            $public_cert = '/etc/letsencrypt/live/api.gozwing.com/cert.pem';
            $private_pk = '/etc/letsencrypt/live/api.gozwing.com/privkey.pem';
            if(env('APP_ENV') == 'test'){
                $public_cert = '/etc/letsencrypt/live/test.api.gozwing.com/cert.pem';
                $private_pk = '/etc/letsencrypt/live/test.api.gozwing.com/privkey.pem';
            }
            if(env('APP_ENV') == 'development'){
                $public_cert = '/etc/letsencrypt/live/dev.api.gozwing.com/cert.pem';
                $private_pk = '/etc/letsencrypt/live/dev.api.gozwing.com/privkey.pem';
            }
            if(env('APP_ENV') == 'demo'){
                $public_cert = '/etc/letsencrypt/live/demo.api.gozwing.com/cert.pem';
                $private_pk = '/etc/letsencrypt/live/demo.api.gozwing.com/privkey.pem';
            }
            if(env('APP_ENV') == 'staging'){
                $public_cert = '/etc/letsencrypt/live/staging.api.gozwing.com/cert.pem';
                $private_pk = '/etc/letsencrypt/live/staging.api.gozwing.com/privkey.pem';
            }
            if(env('APP_ENV') == 'production'){
                $public_cert = '/etc/letsencrypt/live/api.gozwing.com/cert.pem';
                $private_pk = '/etc/letsencrypt/live/api.gozwing.com/privkey.pem';
            }

            $secure_websockets = new \React\Socket\SecureServer($secure_websockets, $loop, [
                'local_cert' => $public_cert, //public
                'local_pk' => $private_pk, //private
                'allow_self_signed' => true, 
                'verify_peer' => false
            ]);


            $secure_websockets_server = new \Ratchet\Server\IoServer($app, $secure_websockets, $loop);
            $secure_websockets_server->run();
        } else {
            $server = IoServer::factory(
            new HttpServer(
                new WsServer(
                    new WebSocketController()
                            )
                        ),
                       8090 
                    );
            $server->run();
        }

        // $webSock = new React\Socket\SecureServer(
        //     $webSock,
        //     $loop,
        //     array(
        //       'local_cert' => '/home/domain/ssl/certs/abc.crt',
        //       'local_pk' => '/home/domain/ssl/keys/abc.key',
        //       'verify_peer' => false,
        //       'verify_peer_name' => false
        //     )
        // );
    }
}
