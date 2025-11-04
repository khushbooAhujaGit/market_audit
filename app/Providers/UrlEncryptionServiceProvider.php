<?php

namespace App\Providers;

use App\Extensions\EncryptedUrlGenerator;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator as LaravelUrlGenerator;
use Illuminate\Support\ServiceProvider;

class UrlEncryptionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->extend(LaravelUrlGenerator::class, function ($url, $app) {
            $routes = $app->make(Router::class)->getRoutes();
            return new EncryptedUrlGenerator($routes, $url->getRequest());
        });
    }
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
