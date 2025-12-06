<?php

use App\Http\Controllers\Api\RunController;
use App\Http\Controllers\Api\RunItemController;
use App\Http\Controllers\Api\CommitmentController;
use App\Http\Controllers\Api\RunStatusController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\SocialController;
use App\Http\Controllers\Api\HandshakeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Runs
    Route::get('/runs', [RunController::class, 'index']);
    Route::post('/runs', [RunController::class, 'store']);

    // Run Items
    Route::post('/runs/{run}/items', [RunItemController::class, 'store']);

    // Commitments
    Route::post('/items/{item}/commit', [CommitmentController::class, 'store']);

    // Status Updates
    Route::post('/runs/{run}/status', [RunStatusController::class, 'update']);

    // Chat
    Route::get('/runs/{run}/chat', [ChatController::class, 'index']);
    Route::post('/runs/{run}/chat', [ChatController::class, 'store']);

    // Social Following
    Route::post('/users/{user}/follow', [SocialController::class, 'toggleFollow']);

    // Payment Handshake
    Route::post('/commitments/{commitment}/pay', [HandshakeController::class, 'markPaid']);
    Route::post('/commitments/{commitment}/confirm', [HandshakeController::class, 'confirmPayment']);
});
