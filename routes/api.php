<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\InquiryController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public tracking endpoints (rate-limited)
Route::middleware('throttle:120,1')->group(function () {
    Route::post('/tracking/identify', [TrackingController::class, 'identify']);
    Route::post('/tracking/track', [TrackingController::class, 'track']);
});

// Public inquiry submission (stricter rate-limit)
Route::post('/inquiries', [InquiryController::class, 'store'])->middleware('throttle:10,1');

// AI endpoint (authenticated, rate-limited)
Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    Route::post('/ai/generate', [\App\Http\Controllers\AiController::class, 'generate']);
});
