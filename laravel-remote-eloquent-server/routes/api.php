<?php

use RemoteEloquent\Server\Http\Controllers\QueryExecutorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Remote Eloquent API Routes
|--------------------------------------------------------------------------
|
| These routes handle remote query execution from client applications.
|
*/

Route::prefix('api/remote-eloquent')
    ->middleware(['api', \RemoteEloquent\Server\Http\Middleware\RemoteEloquentMiddleware::class])
    ->group(function () {
        // Main query execution endpoint
        Route::post('/execute', [QueryExecutorController::class, 'execute'])
            ->name('remote-eloquent.execute');

        // Health check endpoint
        Route::get('/health', [QueryExecutorController::class, 'health'])
            ->name('remote-eloquent.health');
    });
