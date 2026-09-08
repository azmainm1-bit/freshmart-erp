<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Catalog\LocationController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\SupplierController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\GoodsReceiptController;
use App\Http\Controllers\Inventory\InventoryProductController;
use Illuminate\Support\Facades\Route;

// Internal staff tool, not a public product — no marketing page. Go
// straight to the dashboard (if signed in) or the login form (if not).
Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    });

    Route::prefix('catalog')->name('catalog.')->group(function () {
        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');

        Route::get('locations', [LocationController::class, 'index'])->name('locations.index');
        Route::post('locations', [LocationController::class, 'store'])->name('locations.store');

        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
    });

    Route::get('inventory/stock/{product}', [InventoryProductController::class, 'show'])->name('inventory.stock.show');
    Route::get('inventory/goods-receipts/create', [GoodsReceiptController::class, 'create'])->name('inventory.goods-receipts.create');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/api.php';

require __DIR__.'/erp.php';

require __DIR__.'/erp-pages.php';
