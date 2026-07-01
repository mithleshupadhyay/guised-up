<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\InteractionController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'status' => 'ok',
    'auth' => 'Use POST /api/auth/login to create a Sanctum Bearer token.',
    'endpoints' => [
        'POST /api/auth/login',
        'GET /api/feed',
        'GET /api/search?q=funny%20travel%20stories',
        'GET /api/metrics',
        'POST /api/posts',
        'POST /api/interactions',
    ],
]));
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:auth-login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::middleware('throttle:api-read')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/feed', [FeedController::class, 'index']);
        Route::get('/search', [SearchController::class, 'index']);
        Route::get('/metrics', MetricsController::class);
    });

    Route::middleware('throttle:api-write')->group(function (): void {
        Route::post('/posts', [PostController::class, 'store']);
        Route::post('/interactions', [InteractionController::class, 'store']);
    });
});
