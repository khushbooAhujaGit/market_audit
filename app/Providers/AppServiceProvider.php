<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Extensions\EncryptedUrlGenerator;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator as LaravelUrlGenerator;
use Illuminate\Support\Facades\Crypt;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
//        config([
//            'database.connections.mysql.database' => Crypt::decrypt(env('DB_DATABASE')),
//            'database.connections.mysql.username' => Crypt::decrypt(env('DB_USERNAME')),
//            // 'app.key' => Crypt::decrypt(env('APP_KEY')),
//        ]);

        Schema::defaultStringLength(191);
        // Automatically apply the HasEncryptedId trait to all models
        $this->app->extend(LaravelUrlGenerator::class, function ($url, $app) {
            $routes = $app->make(Router::class)->getRoutes();
            return new EncryptedUrlGenerator($routes, $url->getRequest());
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        Paginator::useBootstrapFive();
        Paginator::useBootstrapFour();
    }
}
