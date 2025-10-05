<?php

use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransferController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/transfer', [TransferController::class, 'store']);

    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransferController::class, 'store']);
});
