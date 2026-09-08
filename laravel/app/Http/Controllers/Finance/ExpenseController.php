<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreExpenseRequest;
use App\Support\Finance\ExpenseService;
use App\Support\TransactionResponse;
use Illuminate\Http\JsonResponse;

class ExpenseController extends Controller
{
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        return TransactionResponse::run($request, fn ($input) => ['expense' => ExpenseService::post($request->user(), $input)->toArray()]);
    }
}
