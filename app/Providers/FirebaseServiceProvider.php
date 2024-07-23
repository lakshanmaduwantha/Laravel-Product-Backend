<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;

class FirebaseServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('firebase.auth', function ($app) {
            $factory = (new Factory)
                ->withServiceAccount(storage_path('firebase_credentials.json'));
            return $factory->createAuth();
        });
    }

    public function boot()
    {
        //
    }
}
