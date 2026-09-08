<?php

namespace App\Support\Sales;

use App\Models\CashMovement;
use App\Models\Location;
use App\Models\Shift;
use App\Models\User;
use App\Support\AuditLog;
use App\Support\BusinessRules;
use App\Support\Decimal\Money;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    public static function open(User $actor, array $input): Shift
    {
        return DB::transaction(function () use ($actor, $input) {
            // Serialize open requests, including two users claiming one counter.
            DB::select('select pg_advisory_xact_lock(82471002)');
            $location = Location::findOrFail($input['location_id']);
            BusinessRules::require($location->type === 'sales_floor', 'location_id', 'Open a till at a sales-floor location.');
            BusinessRules::require(! Shift::where('cashier_id', $actor->id)->where('status', 'open')->exists(), 'counter', 'Close your current shift before opening another.');
            BusinessRules::require(! Shift::where('location_id', $location->id)->where('counter', trim($input['counter']))->where('status', 'open')->exists(), 'counter', 'This counter already has an open shift.');
            $opening = Money::of((string) $input['opening_cash']);
            BusinessRules::require(! $opening->isNegative(), 'opening_cash', 'Opening cash cannot be negative.');
            $shift = Shift::create(['cashier_id' => $actor->id, 'location_id' => $location->id, 'counter' => trim($input['counter']), 'opening_cash' => $opening, 'status' => 'open', 'opened_at' => now()]);
            AuditLog::record($actor, 'SHIFT_OPENED', $shift);

            return $shift;
        });
    }

    public static function lockOpen(User $actor, string $id): Shift
    {
        $shift = Shift::lockForUpdate()->findOrFail($id);
        abort_unless($shift->cashier_id === $actor->id, 403, 'This till belongs to another cashier.');
        BusinessRules::require($shift->status === 'open', 'shift_id', 'This shift is closed. Open a shift before posting a transaction.');

        return $shift;
    }

    public static function expectedCash(Shift $shift): Money
    {
        $payments = DB::table('sales_payments')->where('shift_id', $shift->id)->where('method', 'cash')->selectRaw("coalesce(sum(case when kind = 'payment' then amount else -amount end), 0)::text as total")->first();
        $movements = DB::table('cash_movements')->where('shift_id', $shift->id)->selectRaw("coalesce(sum(case when type = 'in' then amount else -amount end), 0)::text as total")->first();

        return $shift->opening_cash->plus((string) $payments->total)->plus((string) $movements->total);
    }

    public static function close(User $actor, Shift $shift, array $input): Shift
    {
        return DB::transaction(function () use ($actor, $shift, $input) {
            $shift = self::lockOpen($actor, $shift->id);
            $expected = self::expectedCash($shift);
            $counted = Money::of((string) $input['counted_cash']);
            BusinessRules::require(! $counted->isNegative(), 'counted_cash', 'Counted cash cannot be negative.');
            $variance = $counted->minus($expected);
            BusinessRules::require($variance->isZero() || ! empty($input['closing_note']), 'closing_note', 'Explain the cash variance before closing.');
            $shift->update(['status' => 'closed', 'expected_cash' => $expected, 'counted_cash' => $counted, 'variance' => $variance, 'closing_note' => $input['closing_note'] ?? null, 'closed_at' => now()]);
            AuditLog::record($actor, 'SHIFT_CLOSED', $shift, ['variance' => (string) $variance]);

            return $shift;
        });
    }

    public static function cashMovement(User $actor, Shift $shift, array $input): CashMovement
    {
        return DB::transaction(function () use ($actor, $shift, $input) {
            $shift = self::lockOpen($actor, $shift->id);
            $amount = Money::of((string) $input['amount']);
            BusinessRules::require($amount->isPositive(), 'amount', 'Enter a positive amount.');
            BusinessRules::require($input['type'] !== 'out' || $amount->isLessThanOrEqualTo(self::expectedCash($shift)), 'amount', 'Cash out exceeds expected drawer cash.');
            $movement = CashMovement::create(['shift_id' => $shift->id, 'actor_id' => $actor->id, 'type' => $input['type'], 'amount' => $amount, 'reason' => $input['reason'], 'posted_at' => now()]);
            AuditLog::record($actor, 'TILL_CASH_MOVEMENT', $movement);

            return $movement;
        });
    }
}
