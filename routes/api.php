<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register']);
        Route::get('/verify', [AuthController::class, 'verify']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth.jwt');
    });

    Route::prefix('users')->group(function (): void {
        Route::middleware('auth.jwt')->group(function (): void {
            Route::get('/me', [UserController::class, 'me']);
            Route::patch('/me', [UserController::class, 'update']);
            Route::get('/me/jobs', [UserController::class, 'postedJobs'])->middleware('role:client');
            Route::get('/me/bids', [UserController::class, 'bidHistory'])->middleware('role:worker');
            Route::get('/me/assignments', [UserController::class, 'assignments'])->middleware('role:worker');
        });

        Route::get('/{id}', [UserController::class, 'show'])->middleware('auth.jwt.optional');
    });
});