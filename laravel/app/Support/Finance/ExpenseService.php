<?php

namespace App\Support\Finance;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Support\AuditLog;
use App\Support\BusinessRules;
use App\Support\Decimal\Money;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public static function post(User $actor, array $input): Expense
    {
        return DB::transaction(function () use ($actor, $input) {
            ExpenseCategory::findOrFail($input['expense_category_id']);
            $amount = Money::of((string) $input['amount']);
            BusinessRules::require($amount->isPositive(), 'amount', 'Expense amount must be positive.');
            BusinessRules::require($input['method'] === 'cash' || ! empty($input['reference']), 'reference', 'Enter the payment transaction reference.');
            $expense = Expense::create([
                'number' => DocumentNumber::next('EXP'), 'expense_category_id' => $input['expense_category_id'],
                'location_id' => $input['location_id'] ?? null, 'actor_id' => $actor->id, 'amount' => $amount,
                'method' => $input['method'], 'reference' => $input['reference'] ?? null, 'paid_from' => $input['paid_from'],
                'expense_date' => $input['expense_date'], 'description' => $input['description'],
            ]);
            AuditLog::record($actor, 'EXPENSE_POSTED', $expense);

            return $expense;
        });
    }
}
