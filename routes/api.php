<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ReceiptUploadController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json(['status' => 'success', 'message' => 'myCost Laravel API is running smoothly']);
});

Route::get('/stats', [StatsController::class, 'index']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::post('/upload', [ReceiptUploadController::class, 'upload']);

// Transactions CRUD & Bulk Sync
Route::get('/transactions', [TransactionController::class, 'index']);
Route::post('/transactions', [TransactionController::class, 'store']);
Route::put('/transactions/{id?}', [TransactionController::class, 'update']);
Route::delete('/transactions/{id?}', [TransactionController::class, 'destroy']);
