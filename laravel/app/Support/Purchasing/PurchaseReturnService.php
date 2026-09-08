<?php

namespace App\Support\Purchasing;

use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Amounts;
use App\Support\AuditLog;
use App\Support\BusinessRules;
use App\Support\Decimal\Money;
use App\Support\DocumentNumber;
use App\Support\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;

class PurchaseReturnService
{
    public static function post(User $actor, GoodsReceipt $receipt, array $input): PurchaseReturn
    {
        return DB::transaction(function () use ($actor, $receipt, $input) {
            $receipt = GoodsReceipt::lockForUpdate()->findOrFail($receipt->id);
            $lines = $receipt->lines()->with('product')->get()->keyBy('id');
            Product::whereIn('id', $lines->pluck('product_id'))->orderBy('id')->lockForUpdate()->get();
            BusinessRules::require(count($input['lines']) > 0, 'lines', 'Select at least one received line.');
            $return = PurchaseReturn::create(['number' => DocumentNumber::next('PR'), 'goods_receipt_id' => $receipt->id, 'actor_id' => $actor->id, 'total_amount' => Money::zero(), 'reason' => $input['reason'], 'posted_at' => now()]);
            $total = Money::zero();
            foreach ($input['lines'] as $i => $inputLine) {
                $line = $lines->get($inputLine['goods_receipt_line_id']);
                BusinessRules::require($line !== null, "lines.{$i}.goods_receipt_line_id", 'Choose a line from this receipt.');
                $quantity = BusinessRules::quantity($line->product, (string) $inputLine['quantity'], "lines.{$i}.quantity");
                $cumulative = $line->returned_quantity->plus($quantity);
                BusinessRules::require($cumulative->isLessThanOrEqualTo($line->quantity_received), "lines.{$i}.quantity", 'Return exceeds the received quantity remaining.');
                // Round cumulative entitlements so repeated partial returns sum exactly.
                $amount = Amounts::proportion($line->line_total, $cumulative, $line->quantity_received)
                    ->minus(Amounts::proportion($line->line_total, $line->returned_quantity, $line->quantity_received));
                StockLedger::postMovement($line->product_id, $receipt->location_id, $line->batch_id, $quantity->negated(), null, StockMovement::PURCHASE_RETURN, 'PurchaseReturn', $return->id, $actor, note: $input['reason']);
                $return->lines()->create(['goods_receipt_line_id' => $line->id, 'quantity' => $quantity, 'amount' => $amount]);
                $line->update(['returned_quantity' => $cumulative]);
                $total = $total->plus($amount);
            }
            $return->update(['total_amount' => $total]);
            $receipt->update(['returned_amount' => $receipt->returned_amount->plus($total)]);
            AuditLog::record($actor, 'PURCHASE_RETURN_POSTED', $return);

            return $return->load('lines');
        });
    }
}
