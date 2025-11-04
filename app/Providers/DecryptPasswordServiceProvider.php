<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Crypt;

class DecryptPasswordServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Only decrypt if the DB_PASSWORD is present in the environment
        if (env('DB_PASSWORD')) {
            $decryptedPassword = Crypt::decrypt(env('DB_PASSWORD'));
            config(['database.connections.mysql.password' => $decryptedPassword]);
        }
    }
}
