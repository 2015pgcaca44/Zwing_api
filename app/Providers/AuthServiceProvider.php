<?php

namespace App\Providers;

use App\User;
use App\Vendor;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use DB;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Boot the authentication services for the application.
     *
     * @return void
     */
    public function boot()
    {
        // Here you may define how you wish users to be authenticated for your Lumen
        // application. The callback which receives the incoming request instance
        // should return either a User instance or null. You're free to obtain
        // the User instance via an API token or any other method necessary.
        $this->app['auth']->viaRequest('api', function ($request) {
            if ($request->input('api_token') && $request->input('c_id')) {
                return User::where('api_token', $request->input('api_token'))->where('c_id', $request->input('c_id'))->first();
            }
            if ($request->input('api_token') && $request->input('vu_id')) {
                return Vendor::where('api_token', $request->input('api_token'))->where('id', $request->input('vu_id'))->first();
            }
            if ($request->input('api_token')) {
                return $request->input('api_token');
            }
        });
    }
}
