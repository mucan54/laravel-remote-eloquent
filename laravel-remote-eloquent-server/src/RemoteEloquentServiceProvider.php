<?php

namespace RemoteEloquent\Server;

use Illuminate\Support\ServiceProvider;
use RemoteEloquent\Server\Deserializers\ParameterDeserializer;
use RemoteEloquent\Server\Security\MethodValidator;
use RemoteEloquent\Server\Security\ModelValidator;

/**
 * Remote Eloquent Service Provider
 *
 * Registers the server package services, configuration, and routes.
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
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__.'/../config/remote-eloquent.php',
            'remote-eloquent'
        );

        // Register singletons
        $this->app->singleton(ModelValidator::class);
        $this->app->singleton(MethodValidator::class);
        $this->app->singleton(ParameterDeserializer::class);

        // Register QueryExecutor
        $this->app->singleton(QueryExecutor::class, function ($app) {
            return new QueryExecutor(
                $app->make(ModelValidator::class),
                $app->make(MethodValidator::class),
                $app->make(ParameterDeserializer::class)
            );
        });
    }

    /**
     * Bootstrap services
     *
     * @return void
     */
    public function boot(): void
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // Publish configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/remote-eloquent.php' => config_path('remote-eloquent.php'),
            ], 'remote-eloquent-config');
        }
    }
}
