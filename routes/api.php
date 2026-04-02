<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\InquiryController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public tracking endpoints (no auth)
Route::post('/tracking/identify', [TrackingController::class, 'identify']);
Route::post('/tracking/track', [TrackingController::class, 'track']);

// Public inquiry submission
Route::post('/inquiries', [InquiryController::class, 'store']);
