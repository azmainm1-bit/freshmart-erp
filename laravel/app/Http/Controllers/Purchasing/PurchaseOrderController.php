<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StorePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Support\Purchasing\PurchaseOrderService;
use App\Support\TransactionResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PurchaseOrderController extends Controller
{
    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        return TransactionResponse::run($request, fn ($input) => ['order' => PurchaseOrderService::create($request->user(), $input)->toArray()]);
    }

    public function cancel(Request $request, PurchaseOrder $order): JsonResponse
    {
        Gate::authorize('purchasing.manage');

        return response()->json(['order' => PurchaseOrderService::cancel($request->user(), $order)]);
    }
}
