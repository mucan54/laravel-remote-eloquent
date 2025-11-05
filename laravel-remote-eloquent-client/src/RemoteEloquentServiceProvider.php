<?php

namespace RemoteEloquent\Client;

use Illuminate\Support\ServiceProvider;

/**
 * Remote Eloquent Service Provider
 *
 * Registers the client package services and configuration.
 */
class RemoteEloquentServiceProvider extends ServiceProvider
{
    /**
     * Register services
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/remote-eloquent.php',
            'remote-eloquent'
        );
    }

    /**
     * Bootstrap services
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/remote-eloquent.php' => config_path('remote-eloquent.php'),
            ], 'remote-eloquent-config');
        }
    }
}
