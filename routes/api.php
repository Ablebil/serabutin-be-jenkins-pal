<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Bids\BidActionController;
use App\Http\Controllers\Api\V1\Bids\BidController;
use App\Http\Controllers\Api\V1\Categories\CategoryController;
use App\Http\Controllers\Api\V1\Jobs\JobController;
use App\Http\Controllers\Api\V1\Reviews\ReviewController;
use App\Http\Controllers\Api\V1\Uploads\UploadController;
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
            Route::get('/me/reviews', [UserController::class, 'reviews']);
        });

        Route::middleware('auth.jwt.optional')->group(function (): void {
            Route::get('/{id}', [UserController::class, 'show']);
            Route::get('/{id}/jobs', [UserController::class, 'publicJobs']);
            Route::get('/{id}/assignments', [UserController::class, 'publicAssignments']);
            Route::get('/{id}/reviews', [UserController::class, 'publicReviews']);
        });
    });

    Route::prefix('categories')->group(function (): void {
        Route::get('/', [CategoryController::class, 'index']);
    });

    Route::prefix('jobs')->middleware('auth.jwt')->group(function (): void {
        Route::get('/', [JobController::class, 'index']);
        Route::post('/', [JobController::class, 'store'])->middleware('role:client');
        Route::prefix('{id}')->group(function (): void {
            Route::get('/', [JobController::class, 'show']);
            Route::patch('/', [JobController::class, 'update'])->middleware('role:client');
            Route::delete('/', [JobController::class, 'destroy'])->middleware('role:client');
            Route::patch('/status', [JobController::class, 'updateStatus'])->middleware('role:client');
            Route::get('/workers', [JobController::class, 'getWorkers']);
            Route::get('/bids', [BidController::class, 'index'])->middleware('role:client');
            Route::post('/bids', [BidController::class, 'store'])->middleware('role:worker');
            Route::post('/reviews', [ReviewController::class, 'store']);
        });
    });

    Route::prefix('bids')->middleware('auth.jwt')->group(function (): void {
        Route::delete('/{id}', [BidController::class, 'cancel'])->middleware('role:worker');
        Route::patch('/{id}/accept', [BidActionController::class, 'accept'])->middleware('role:client');
        Route::patch('/{id}/reject', [BidActionController::class, 'reject'])->middleware('role:client');
    });

    Route::prefix('uploads')->middleware('auth.jwt')->group(function (): void {
        Route::post('/', [UploadController::class, 'store']);
    });
});
