<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StoreSupplierPaymentRequest;
use App\Models\GoodsReceipt;
use App\Support\Purchasing\SupplierPaymentService;
use App\Support\TransactionResponse;
use Illuminate\Http\JsonResponse;

class SupplierPaymentController extends Controller
{
    public function store(StoreSupplierPaymentRequest $request, GoodsReceipt $receipt): JsonResponse
    {
        return TransactionResponse::run($request, fn ($input) => ['payment' => SupplierPaymentService::post($request->user(), $receipt, $input)->toArray()]);
    }
}
