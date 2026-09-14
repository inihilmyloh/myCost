<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PiggyBankController;
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

// Financial Stats & Categories
Route::get('/stats', [StatsController::class, 'index']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::post('/upload', [ReceiptUploadController::class, 'upload']);

// Accounts / Wallets (Firefly III)
Route::get('/accounts', [AccountController::class, 'index']);
Route::post('/accounts', [AccountController::class, 'store']);
Route::put('/accounts/{id?}', [AccountController::class, 'update']);
Route::delete('/accounts/{id?}', [AccountController::class, 'destroy']);

// Budgets (Firefly III)
Route::get('/budgets', [BudgetController::class, 'index']);
Route::post('/budgets', [BudgetController::class, 'store']);
Route::delete('/budgets/{id?}', [BudgetController::class, 'destroy']);

// Piggy Banks / Savings Goals (Firefly III)
Route::get('/piggy-banks', [PiggyBankController::class, 'index']);
Route::post('/piggy-banks', [PiggyBankController::class, 'store']);
Route::post('/piggy-banks/{id}/adjust', [PiggyBankController::class, 'adjustMoney']);
Route::delete('/piggy-banks/{id?}', [PiggyBankController::class, 'destroy']);

// Transactions CRUD & Transfer
Route::get('/transactions', [TransactionController::class, 'index']);
Route::post('/transactions', [TransactionController::class, 'store']);
Route::put('/transactions/{id?}', [TransactionController::class, 'update']);
Route::delete('/transactions/{id?}', [TransactionController::class, 'destroy']);
