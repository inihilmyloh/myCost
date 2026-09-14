<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ReceiptUploadController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('app');
});

// Setup & Diagnostic route
Route::get('/setup-check', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $count = \App\Models\Transaction::count();
        return response()->json([
            'status' => 'success',
            'message' => 'Laravel terhubung ke MySQL!',
            'connected' => true,
            'total_transactions' => $count
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Koneksi MySQL gagal: ' . $e->getMessage(),
            'connected' => false
        ], 500);
    }
});
