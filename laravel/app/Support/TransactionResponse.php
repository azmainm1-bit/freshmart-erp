<?php

namespace App\Support;

use App\Http\Requests\TransactionRequest;
use App\Support\Idempotency\IdempotentOperation;
use Closure;
use Illuminate\Http\JsonResponse;

final class TransactionResponse
{
    public static function run(TransactionRequest $request, Closure $callback): JsonResponse
    {
        $payload = $request->validated();
        unset($payload['_key']);
        $result = IdempotentOperation::run($request->user(), $request->method().' /'.$request->path(), $request->header('Idempotency-Key'), $payload, fn () => ['status' => 201, 'body' => $callback($payload)]);

        return response()->json([...$result['body'], 'replayed' => $result['replayed']], $result['status']);
    }
}
