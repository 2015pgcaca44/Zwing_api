<?php

require_once __DIR__.'/../vendor/autoload.php';

(new Laravel\Lumen\Bootstrap\LoadEnvironmentVariables(
    dirname(__DIR__)
))->bootstrap();

// try {
//     (new Dotenv\Dotenv(__DIR__.'/../'))->load();
// } catch (Dotenv\Exception\InvalidPathException $e) {
//     //
// }

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| Here we will load the environment and create the application instance
| that serves as the central piece of this framework. We'll use this
| application as an "IoC" container and router for this framework.
|
*/

$app = new Laravel\Lumen\Application(
    realpath(__DIR__.'/../')
);

$app->register(Jenssegers\Mongodb\MongodbServiceProvider::class);
$app->register(\Illuminate\Redis\RedisServiceProvider::class);
// $app->register(Jenssegers\Mongodb\MongodbQueueServiceProvider::class);

$app->withFacades();

$app->withEloquent();

/*
|--------------------------------------------------------------------------
| Register Container Bindings
|--------------------------------------------------------------------------
|
| Now we will register a few bindings in the service container. We will
| register the exception handler and the console kernel. You may add
| your own bindings here if you like or you can make another file.
|
*/

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);


/*
|--------------------------------------------------------------------------
| Register Middleware
|--------------------------------------------------------------------------
|
| Next, we will register the middleware with the application. These can
| be global middleware that run before and after each request into a
| route or middleware that'll be assigned to some specific routes.
|
*/

// $app->middleware([
//    App\Http\Middleware\VendorAuthenticate::class
// ]);

$app->routeMiddleware([
    'auth' => App\Http\Middleware\Authenticate::class,
    //'v_auth' => App\Http\Middleware\VendorAuthenticate::class,
    'vendor_m' => App\Http\Middleware\VendorMiddleware::class,
    'settings' => App\Http\Middleware\APISettings::class,
    'oauth' => App\Http\Middleware\OauthMiddleware::class,
    'throttle' => App\Http\Middleware\Throttle::class,
    'gzip' => App\Http\Middleware\GzipMiddleware::class,
    // 'throttle' => App\Http\Middleware\RateLimits::class

]);


// $app->middleware([
//    App\Http\Middleware\logmiddleware::class
// ]);

$app->middleware([
   App\Http\Middleware\MongoLogMiddleware::class,
   App\Http\Middleware\CorsMiddleware::class,
   App\Http\Middleware\Dynamic::class
]);


/*
|--------------------------------------------------------------------------
| Register Service Providers
|--------------------------------------------------------------------------
|
| Here we will register all of the application's service providers which
| are used to bind services into the container. Service providers are
| totally optional, so you are not required to uncomment this line.
|
*/
$app->register(Flipbox\LumenGenerator\LumenGeneratorServiceProvider::class);
$app->register(App\Providers\AppServiceProvider::class);
$app->register(App\Providers\AuthServiceProvider::class);
$app->register(App\Providers\EventServiceProvider::class);
// $app->register(App\Providers\SolariumServiceProvider::class);
$app->register(App\Providers\SearchServiceProvider::class);

$app->register(Illuminate\Mail\MailServiceProvider::class);
$app->register(\Barryvdh\DomPDF\ServiceProvider::class);
// $app->alias('mailer', \Illuminate\Contracts\Mail\Mailer::class);
// $app->register(\Illuminate\Redis\RedisServiceProvider::class);
//$app->register(Aws\Laravel\AwsServiceProvider::class); //Shw 24/07/20 


//class_alias(Barryvdh\DomPDF\Facade::class, 'PDF');
/*
|--------------------------------------------------------------------------
| Load The Application Routes
|--------------------------------------------------------------------------
|
| Next we will include the routes file so that they can all be added to
| the application. This will provide all of the URLs the application
| can respond to, as well as the controllers that may handle them.
|
*/

$app->configure('database');
$app->configure('cache');
$app->configure('services');
$app->configure('mail');
$app->configure('dompdf');
$app->configure('search');
$app->make('queue');  
// $app->configure('queue');
// $app->configure('solarium');
$app->alias('mailer', Illuminate\Mail\Mailer::class);
$app->alias('mailer', Illuminate\Contracts\Mail\Mailer::class);
$app->alias('mailer', Illuminate\Contracts\Mail\MailQueue::class);

$app->router->group([
    'namespace' => 'App\Http\Controllers',
], function ($app) {
    require __DIR__.'/../routes/web.php';
});

// $app->group(['namespace' => 'App\Http\Controllers'], function ($app) {
//     require __DIR__.'/../routes/web.php';
// });

return $app;
