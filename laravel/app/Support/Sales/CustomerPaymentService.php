<?php

namespace App\Support\Sales;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SalesPayment;
use App\Models\User;
use App\Support\AuditLog;
use App\Support\BusinessRules;
use App\Support\Decimal\Money;
use Illuminate\Support\Facades\DB;

class CustomerPaymentService
{
    public static function post(User $actor, Sale $sale, array $input): SalesPayment
    {
        return DB::transaction(function () use ($actor, $sale, $input) {
            $shift = empty($input['shift_id']) ? null : ShiftService::lockOpen($actor, $input['shift_id']);
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);
            if ($sale->customer_id) {
                Customer::lockForUpdate()->findOrFail($sale->customer_id);
            }
            $amount = Money::of((string) $input['amount']);
            $due = $sale->grand_total->minus($sale->returned_amount)->minus($sale->paid_amount)->plus($sale->refunded_amount);
            BusinessRules::require($amount->isPositive() && $amount->isLessThanOrEqualTo($due), 'amount', 'Payment must be positive and cannot exceed the amount due.');
            BusinessRules::require($input['method'] !== 'cash' || $shift !== null, 'shift_id', 'Cash collections require your open shift.');
            BusinessRules::require($input['method'] === 'cash' || ! empty($input['reference']), 'reference', 'Enter the payment transaction reference.');
            $payment = $sale->payments()->create(['shift_id' => $shift?->id, 'actor_id' => $actor->id, 'kind' => 'payment', 'amount' => $amount, 'method' => $input['method'], 'reference' => $input['reference'] ?? null, 'paid_at' => now()]);
            $sale->update(['paid_amount' => $sale->paid_amount->plus($amount)]);
            AuditLog::record($actor, 'CUSTOMER_PAYMENT_POSTED', $payment);

            return $payment;
        });
    }
}
