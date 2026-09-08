<?php

namespace App\Support\Purchasing;

use App\Models\GoodsReceipt;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\AuditLog;
use App\Support\BusinessRules;
use App\Support\Decimal\Money;
use Illuminate\Support\Facades\DB;

class SupplierPaymentService
{
    public static function post(User $actor, GoodsReceipt $receipt, array $input): SupplierPayment
    {
        return DB::transaction(function () use ($actor, $receipt, $input) {
            $receipt = GoodsReceipt::lockForUpdate()->findOrFail($receipt->id);
            $amount = Money::of((string) $input['amount']);
            $due = $receipt->total_amount->minus($receipt->returned_amount)->minus($receipt->paid_amount);
            BusinessRules::require($amount->isPositive() && $amount->isLessThanOrEqualTo($due), 'amount', 'Payment must be positive and cannot exceed the outstanding invoice balance.');
            BusinessRules::require($input['method'] === 'cash' || ! empty($input['reference']), 'reference', 'Enter the bank or payment transaction reference.');
            $payment = SupplierPayment::create(['goods_receipt_id' => $receipt->id, 'actor_id' => $actor->id, 'amount' => $amount, 'method' => $input['method'], 'reference' => $input['reference'] ?? null, 'paid_at' => now()]);
            $receipt->update(['paid_amount' => $receipt->paid_amount->plus($amount)]);
            AuditLog::record($actor, 'SUPPLIER_PAYMENT_POSTED', $payment);

            return $payment;
        });
    }
}
