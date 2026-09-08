<?php

use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Sales\ShiftController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('api')->group(function () {
    Route::post('shifts', [ShiftController::class, 'open']);
    Route::post('shifts/{shift}/close', [ShiftController::class, 'close']);
    Route::post('shifts/{shift}/cash', [ShiftController::class, 'cash']);
    Route::post('sales', [SaleController::class, 'store']);
    Route::post('sales/{sale}/payments', [SaleController::class, 'payment']);
    Route::post('sales/{sale}/returns', [SaleController::class, 'return']);
    Route::post('expenses', [ExpenseController::class, 'store']);
});
