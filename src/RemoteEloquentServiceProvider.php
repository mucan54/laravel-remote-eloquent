<?php

namespace RemoteEloquent;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * Remote Eloquent Service Provider
 *
 * Auto-registers based on mode (client or server).
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
        // Publish configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/remote-eloquent.php' => config_path('remote-eloquent.php'),
            ], 'remote-eloquent-config');
        }

        // Register routes only in server mode
        if (config('remote-eloquent.mode') === 'server') {
            $this->registerRoutes();
        }
    }

    /**
     * Register API routes
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        Route::prefix('api/remote-eloquent')
            ->middleware(['api'])
            ->group(function () {
                Route::post('/execute', [
                    \RemoteEloquent\Server\Http\Controllers\RemoteEloquentController::class,
                    'execute'
                ])->name('remote-eloquent.execute');

                Route::post('/batch', [
                    \RemoteEloquent\Server\Http\Controllers\RemoteEloquentController::class,
                    'batch'
                ])->name('remote-eloquent.batch');

                Route::post('/service', [
                    \RemoteEloquent\Server\Http\Controllers\RemoteEloquentController::class,
                    'service'
                ])->name('remote-eloquent.service');

                Route::get('/health', [
                    \RemoteEloquent\Server\Http\Controllers\RemoteEloquentController::class,
                    'health'
                ])->name('remote-eloquent.health');
            });
    }
}
