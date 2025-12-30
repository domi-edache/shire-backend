<?php

use App\Http\Controllers\Api\AuthController; // Added
use App\Http\Controllers\Api\OnboardingController; // Added
use App\Http\Controllers\Api\RunController;
use App\Http\Controllers\Api\RunItemController;
use App\Http\Controllers\Api\CommitmentController;
use App\Http\Controllers\Api\RunStatusController;
use App\Http\Controllers\Api\RunActivityController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\SocialController;
use App\Http\Controllers\Api\HandshakeController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// --- PUBLIC ROUTES (No Token Needed) ---
Route::post('/auth/social', [AuthController::class, 'socialLogin']);
Route::get('/auth/check-handle', [AuthController::class, 'checkHandle']);

// Guest-accessible haul view (returns sanitized data for non-authenticated users)
Route::get('/hauls/{run}', [RunController::class, 'show']);
Route::get('/runs/{run}', [RunController::class, 'show']);

// Public profile (no auth required)
Route::get('/users/{user}/public', [\App\Http\Controllers\Api\PublicProfileController::class, 'show']);

// --- PROTECTED ROUTES (Token Required) ---
Route::middleware('auth:sanctum')->group(function () {

    // User Profile
    Route::get('/me', [UserController::class, 'me']);
    Route::put('/me', [UserController::class, 'update']);
    Route::patch('/me', [UserController::class, 'update']);
    Route::post('/me/settings', [UserController::class, 'updateSettings']);
    Route::get('/me/hauls', [RunController::class, 'myHauls']);
    Route::get('/me/activities', [UserController::class, 'activities']);

    // Onboarding
    Route::post('/onboarding', [OnboardingController::class, 'store']);

    // Runs
    Route::get('/runs', [RunController::class, 'index']);
    Route::post('/runs', [RunController::class, 'store']);

    Route::get('/hauls', [RunController::class, 'index']);
    Route::post('/hauls', [RunController::class, 'store']);
    Route::delete('/hauls/{run}', [RunController::class, 'destroy']);
    Route::get('/runs/{run}/activities', [RunActivityController::class, 'index']);
    Route::post('/runs/{run}/status', [RunStatusController::class, 'update']);

    // Run Items
    Route::post('/runs/{run}/items', [RunItemController::class, 'store']);

    // Commitments
    Route::post('/items/{item}/commit', [CommitmentController::class, 'store']);
    Route::delete('/commitments/{commitment}', [CommitmentController::class, 'destroy']);


    // Chat
    Route::get('/runs/{run}/chat', [ChatController::class, 'index']);
    Route::post('/runs/{run}/chat', [ChatController::class, 'store']);

    // Social Following
    Route::post('/users/{user}/follow', [SocialController::class, 'toggleFollow']);

    // Payment Handshake
    Route::post('/commitments/{commitment}/pay', [HandshakeController::class, 'markPaid']);
    Route::post('/commitments/{commitment}/confirm', [HandshakeController::class, 'confirmPayment']);
});