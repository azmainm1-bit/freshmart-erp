<?php

namespace Tests\Unit\Support;

use App\Support\Decimal\Cost;
use App\Support\Decimal\Money;
use App\Support\Decimal\Quantity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DecimalTest extends TestCase
{
    public function test_money_always_serializes_with_two_decimal_places(): void
    {
        // This is the direct fix for the legacy Node app's known display
        // gap (decimal.js stripping trailing zeros) — docs/ARCHITECTURE.md
        $this->assertSame('540.00', Money::of('540')->toString());
        $this->assertSame('0.00', Money::of('0')->toString());
        $this->assertSame('19.50', Money::of('19.5')->toString());
    }

    public function test_quantity_always_serializes_with_three_decimal_places(): void
    {
        $this->assertSame('0.750', Quantity::of('0.75')->toString());
        $this->assertSame('10.000', Quantity::of('10')->toString());
    }

    public function test_cost_serializes_with_six_decimal_places(): void
    {
        $this->assertSame('12.500000', Cost::of('12.5')->toString());
    }

    public function test_rounds_half_up_at_construction(): void
    {
        $this->assertSame('10.13', Money::of('10.125')->toString());
        $this->assertSame('10.12', Money::of('10.124')->toString());
    }

    public function test_constructing_from_a_php_float_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::of(19.99);
    }

    public function test_arithmetic_is_exact_not_binary_float(): void
    {
        // 0.1 + 0.2 famously != 0.3 in binary float; must be exact here.
        $sum = Money::of('0.10')->plus(Money::of('0.20'));
        $this->assertSame('0.30', $sum->toString());
        $this->assertTrue($sum->equals(Money::of('0.30')));
    }

    public function test_multiplication_and_json_serialization(): void
    {
        $lineTotal = Money::of('180.00')->multipliedBy('3');
        $this->assertSame('540.00', $lineTotal->toString());
        $this->assertSame('"540.00"', json_encode($lineTotal));
    }

    public function test_proportional_refund_matches_the_returns_business_rule(): void
    {
        // docs/ARCHITECTURE.md worked example: Tk 100 item at 20% off (Tk 80),
        // half-returned refunds Tk 40, not Tk 50.
        $lineTotal = Money::of('80.00');
        $refund = $lineTotal->multipliedBy(Quantity::of('1')->toBigDecimal())
            ->dividedBy(Quantity::of('2')->toBigDecimal(), 2);
        $this->assertSame('40.00', $refund->toString());
    }

    public function test_quantity_integer_check_for_non_weighted_products(): void
    {
        $this->assertTrue(Quantity::of('3')->isInteger());
        $this->assertFalse(Quantity::of('0.750')->isInteger());
    }

    public function test_comparisons(): void
    {
        $this->assertTrue(Money::of('10.00')->isGreaterThan(Money::of('9.99')));
        $this->assertTrue(Money::of('-1.00')->isNegative());
        $this->assertTrue(Money::zero()->isZero());
    }
}
