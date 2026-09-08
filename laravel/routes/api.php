<?php

use App\Http\Controllers\Inventory\GoodsReceiptController;
use App\Http\Controllers\Inventory\StockOperationController;
use App\Http\Controllers\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Purchasing\PurchaseReturnController;
use App\Http\Controllers\Purchasing\SupplierPaymentController;
use Illuminate\Support\Facades\Route;

// JSON endpoints for transactional operations that need precise
// idempotency-key/retry control from the frontend (checkout, receiving,
// returns, supplier payments, etc.) — see docs/ARCHITECTURE.md for why these
// are separate from the Inertia page routes above them. Required from
// routes/web.php (not registered as Laravel's stateless "api" group) so
// they run under the 'web' middleware group — session auth + CSRF, since
// this is a single session-authenticated SPA, not a token-auth API
// client. All require authentication; there is no public API surface.
Route::middleware(['auth'])->prefix('api')->name('api.')->group(function () {
    Route::post('goods-receipts', [GoodsReceiptController::class, 'store'])->name('goods-receipts.store');
    Route::post('purchase-orders', [PurchaseOrderController::class, 'store']);
    Route::post('purchase-orders/{order}/cancel', [PurchaseOrderController::class, 'cancel']);
    Route::post('goods-receipts/{receipt}/payments', [SupplierPaymentController::class, 'store']);
    Route::post('goods-receipts/{receipt}/returns', [PurchaseReturnController::class, 'store']);
    Route::post('stock-operations', [StockOperationController::class, 'store']);
});
