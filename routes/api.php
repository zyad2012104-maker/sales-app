<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\SaleController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('products', ProductController::class);
Route::apiResource('sales', SaleController::class);
Route::post('sales/{id}/payment', [SaleController::class, 'addPayment']);
Route::get('daily-report', [SaleController::class, 'dailyReport']);
Route::post('sales/{id}/installment-payment', [SaleController::class, 'addInstallmentPayment']);