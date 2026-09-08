<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StorePurchaseReturnRequest;
use App\Models\GoodsReceipt;
use App\Support\Purchasing\PurchaseReturnService;
use App\Support\TransactionResponse;
use Illuminate\Http\JsonResponse;

class PurchaseReturnController extends Controller
{
    public function store(StorePurchaseReturnRequest $request, GoodsReceipt $receipt): JsonResponse
    {
        return TransactionResponse::run($request, fn ($input) => ['return' => PurchaseReturnService::post($request->user(), $receipt, $input)->toArray()]);
    }
}
