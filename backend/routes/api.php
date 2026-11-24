<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

// Préfixe /api/v1 défini dans bootstrap/app.php.

Route::post('/auth/otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:otp-request');
Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp-verify');

Route::middleware(['auth:sanctum', 'professional.active'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/me', [MeController::class, 'show']);
    Route::patch('/me', [MeController::class, 'update']);

    Route::apiResource('services', ServiceController::class);

    Route::get('/availability', [AvailabilityController::class, 'show']);
    Route::put('/availability', [AvailabilityController::class, 'replaceRules']);
    Route::post('/availability/exceptions', [AvailabilityController::class, 'storeException']);
    Route::delete('/availability/exceptions/{id}', [AvailabilityController::class, 'destroyException']);
});
