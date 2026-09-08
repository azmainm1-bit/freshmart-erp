<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockOperationRequest;
use App\Support\Inventory\InventoryOperationService;
use App\Support\TransactionResponse;
use Illuminate\Http\JsonResponse;

class StockOperationController extends Controller
{
    public function store(StoreStockOperationRequest $request): JsonResponse
    {
        return TransactionResponse::run($request, fn ($input) => ['operation' => InventoryOperationService::post($request->user(), $input)->toArray()]);
    }
}
