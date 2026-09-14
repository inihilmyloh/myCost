<?php

use App\Http\Controllers\AuthController;
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

// Authentication Routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/auth/me', [AuthController::class, 'me']);
Route::post('/auth/logout', [AuthController::class, 'logout']);

Route::get('/stats', [StatsController::class, 'index']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::post('/upload', [ReceiptUploadController::class, 'upload']);

// Transactions CRUD & Bulk Sync
Route::get('/transactions', [TransactionController::class, 'index']);
Route::post('/transactions', [TransactionController::class, 'store']);
Route::put('/transactions/{id?}', [TransactionController::class, 'update']);
Route::delete('/transactions/{id?}', [TransactionController::class, 'destroy']);
