<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Api\BundleController;
use App\Http\Controllers\PaymentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// === PUBLIC ROUTES (TANPA LOGIN) ===
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');

// Routes publik untuk semua user
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']); 

//  ROUTE PUBLIK UNTUK STRUK 
Route::get('/public/transaction', [TransactionController::class, 'getByOrderId']);

// MIDTRANS ROUTES - public!
Route::post('/payment/create', [PaymentController::class, 'create']);
Route::post('/payment/notification', [PaymentController::class, 'notification']);

// === PROTECTED ROUTES  ===
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // Order hanya untuk user yang sudah login
    Route::post('/orders', [TransactionController::class, 'store']);
    
    // Categories (CRUD penuh untuk owner/cashier)
    Route::apiResource('categories', CategoryController::class);
    
    // Products (CRUD penuh untuk owner/cashier)
    Route::apiResource('products', ProductController::class);
    Route::get('/products/low-stock', [ProductController::class, 'lowStock']);
    Route::post('/products/bulk', [ProductController::class, 'bulkStore']); 
    
    // Bundles
    Route::apiResource('bundles', BundleController::class);
    
    // Transactions (owner/cashier)
    Route::apiResource('transactions', TransactionController::class);
    Route::post('/transactions/{id}/confirm', [TransactionController::class, 'confirmOrder']); 
    
    // Reports
    Route::get('/reports/sales', [ReportController::class, 'salesReport']);
    Route::get('/reports/stock', [ReportController::class, 'stockReport']);
    Route::get('/reports/revenue-by-category', [ReportController::class, 'revenueByCategory']);
});