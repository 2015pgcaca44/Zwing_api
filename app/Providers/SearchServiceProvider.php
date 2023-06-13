<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;


class SearchServiceProvider extends ServiceProvider
{

    protected $defer = true;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return  void
     */
    public function register()
    {
        $this->app->bind(App\Http\Controllers\Search\SearchController::class, function ($app) {
            return new App\Http\Controllers\Search\SearchController();
        });
    }

}
