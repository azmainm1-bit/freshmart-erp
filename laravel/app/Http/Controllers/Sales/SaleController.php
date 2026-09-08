<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreCustomerPaymentRequest;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Http\Requests\Sales\StoreSalesReturnRequest;
use App\Models\Sale;
use App\Support\Sales\CustomerPaymentService;
use App\Support\Sales\SaleService;
use App\Support\Sales\SalesReturnService;
use App\Support\TransactionResponse;
use Illuminate\Http\JsonResponse;

class SaleController extends Controller
{
    public function store(StoreSaleRequest $request): JsonResponse
    {
        return TransactionResponse::run($request, fn ($input) => ['sale' => SaleService::post($request->user(), $input)->refresh()->load('lines', 'payments')->toArray()]);
    }

    public function payment(StoreCustomerPaymentRequest $request, Sale $sale): JsonResponse
    {
        return TransactionResponse::run($request, fn ($input) => ['payment' => CustomerPaymentService::post($request->user(), $sale, $input)->toArray()]);
    }

    public function return(StoreSalesReturnRequest $request, Sale $sale): JsonResponse
    {
        return TransactionResponse::run($request, fn ($input) => ['return' => SalesReturnService::post($request->user(), $sale, $input)->toArray()]);
    }
}
