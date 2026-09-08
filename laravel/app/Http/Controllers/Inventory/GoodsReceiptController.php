<?php

namespace App\Http\Controllers\Inventory;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreGoodsReceiptRequest;
use App\Models\Location;
use App\Models\Supplier;
use App\Support\Idempotency\IdempotentOperation;
use App\Support\Inventory\GoodsReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GoodsReceiptController extends Controller
{
    public function create(): Response
    {
        Gate::authorize('inventory.receive');

        return Inertia::render('purchasing/create', [
            'suppliers' => Supplier::where('active', true)->orderBy('name')->get(['id', 'name']),
            'locations' => Location::orderBy('name')->get(['id', 'name', 'type']),
            'order' => null,
        ]);
    }

    public function store(StoreGoodsReceiptRequest $request): JsonResponse
    {
        $key = $request->header('Idempotency-Key');
        if (! $key || strlen($key) > 128) {
            throw new BusinessRuleException(400, 'IDEMPOTENCY_KEY_REQUIRED', 'Idempotency-Key header is required');
        }

        $data = $request->validated();

        $result = IdempotentOperation::run(
            $request->user(),
            'POST /api/goods-receipts',
            $key,
            $data,
            function () use ($data, $request) {
                $goodsReceipt = GoodsReceiptService::post($request->user(), $data);

                return ['status' => 201, 'body' => ['goods_receipt' => $goodsReceipt->toArray()]];
            }
        );

        return response()->json([...$result['body'], 'replayed' => $result['replayed']], $result['status']);
    }
}
