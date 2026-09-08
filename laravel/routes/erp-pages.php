<?php

use App\Http\Controllers\Catalog\CustomerController;
use App\Http\Controllers\Catalog\LookupController;
use App\Http\Controllers\ErpPageController;
use App\Http\Controllers\Finance\ExpenseCategoryController;
use App\Http\Controllers\Inventory\InventoryProductController;
use App\Http\Controllers\ReportsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('pos', [ErpPageController::class, 'pos'])->name('pos');
    Route::get('sales', [ErpPageController::class, 'sales'])->name('sales.index');
    Route::get('sales/{sale}', [ErpPageController::class, 'sale'])->name('sales.show');
    Route::get('returns', [ErpPageController::class, 'returns'])->name('returns.index');
    Route::get('purchasing', [ErpPageController::class, 'purchasing'])->name('purchasing.index');
    Route::get('purchasing/create', [ErpPageController::class, 'purchaseCreate'])->name('purchasing.create');
    Route::get('purchasing/receipts/{receipt}', [ErpPageController::class, 'receipt'])->name('purchasing.show');
    Route::get('inventory', [ErpPageController::class, 'inventory'])->name('inventory.index');
    Route::get('inventory/operations', [ErpPageController::class, 'stockOperations'])->name('inventory.operations');
    Route::post('expense-categories', [ExpenseCategoryController::class, 'store']);
    Route::get('expenses', [ErpPageController::class, 'expenses'])->name('expenses.index');
    Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportsController::class, 'export'])->name('reports.export');
    Route::get('catalog/categories', [ErpPageController::class, 'categories'])->name('categories.index');
    Route::post('catalog/categories/rename', [ErpPageController::class, 'renameCategory']);
    Route::get('admin/audit', [ErpPageController::class, 'audit'])->name('audit.index');
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::get('api/stock-options/{product}', [InventoryProductController::class, 'options']);
    Route::get('api/products', [LookupController::class, 'products']);
    Route::get('api/customers', [LookupController::class, 'customers']);
});
