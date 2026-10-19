<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Middleware\EnsureUserIsApproved;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware(EnsureUserIsApproved::class)->group(function () {
        Route::post('/profile', [ProfileController::class, 'store']);
        Route::get('/program', [ProgramController::class, 'show']);
        Route::get('/program/day/{day}', [ProgramController::class, 'day']);
        Route::post('/subscription/pay', [SubscriptionController::class, 'pay']);
    });
});
