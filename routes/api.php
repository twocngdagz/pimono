<?php

use App\Http\Controllers\AuthTokenController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransferController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Issue personal access token (Sanctum) using email/password
Route::post('/token', [AuthTokenController::class, 'issue']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransferController::class, 'store'])->middleware('throttle:60,1');
});
