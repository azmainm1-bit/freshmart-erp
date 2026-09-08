<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CloseShiftRequest;
use App\Http\Requests\Sales\OpenShiftRequest;
use App\Http\Requests\Sales\StoreCashMovementRequest;
use App\Models\Shift;
use App\Support\Sales\ShiftService;
use App\Support\TransactionResponse;
use Illuminate\Http\JsonResponse;

class ShiftController extends Controller
{
    public function open(OpenShiftRequest $request): JsonResponse
    {
        return TransactionResponse::run($request, fn ($input) => ['shift' => ShiftService::open($request->user(), $input)->toArray()]);
    }

    public function close(CloseShiftRequest $request, Shift $shift): JsonResponse
    {
        return TransactionResponse::run($request, fn ($input) => ['shift' => ShiftService::close($request->user(), $shift, $input)->toArray()]);
    }

    public function cash(StoreCashMovementRequest $request, Shift $shift): JsonResponse
    {
        return TransactionResponse::run($request, fn ($input) => ['movement' => ShiftService::cashMovement($request->user(), $shift, $input)->toArray()]);
    }
}
