<?php

namespace App\Support\Decimal;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Shared base for Money/Quantity/Cost — see docs/ARCHITECTURE.md
 *
 * Never wraps a PHP float. Always rounds explicitly, only at construction
 * (never mid-calculation — intermediate results keep full precision until
 * the next value object is built from them).
 */
abstract class AbstractDecimal implements JsonSerializable, Stringable
{
    protected BigDecimal $value;

    /** Decimal places this type is rounded/displayed to. */
    abstract protected static function scale(): int;

    final public function __construct(BigDecimal|string|int|float $value)
    {
        if (is_float($value)) {
            throw new InvalidArgumentException(
                static::class.' must not be constructed from a PHP float (binary floating point) — pass a string.'
            );
        }

        $decimal = $value instanceof BigDecimal ? $value : BigDecimal::of($value);
        $this->value = $decimal->toScale(static::scale(), RoundingMode::HalfUp);
    }

    public static function of(BigDecimal|string|int|float|self $value): static
    {
        if ($value instanceof self) {
            return new static($value->value);
        }

        return new static($value);
    }

    public static function zero(): static
    {
        return new static('0');
    }

    public function plus(self|BigDecimal|string|int $other): static
    {
        return new static($this->value->plus(self::extract($other)));
    }

    public function minus(self|BigDecimal|string|int $other): static
    {
        return new static($this->value->minus(self::extract($other)));
    }

    /** Multiply by a plain scalar factor (e.g. a quantity or a rate) — result keeps this type's scale. */
    public function multipliedBy(BigDecimal|string|int $factor): static
    {
        return new static($this->value->multipliedBy($factor));
    }

    public function dividedBy(BigDecimal|string|int $divisor, ?int $scale = null): static
    {
        return new static($this->value->dividedBy($divisor, $scale ?? static::scale() + 4, RoundingMode::HalfUp));
    }

    public function negated(): static
    {
        return new static($this->value->negated());
    }

    public function abs(): static
    {
        return new static($this->value->abs());
    }

    public function isNegative(): bool
    {
        return $this->value->isNegative();
    }

    public function isPositive(): bool
    {
        return $this->value->isPositive();
    }

    public function isZero(): bool
    {
        return $this->value->isZero();
    }

    public function isGreaterThan(self|BigDecimal|string|int $other): bool
    {
        return $this->value->isGreaterThan(self::extract($other));
    }

    public function isGreaterThanOrEqualTo(self|BigDecimal|string|int $other): bool
    {
        return $this->value->isGreaterThanOrEqualTo(self::extract($other));
    }

    public function isLessThan(self|BigDecimal|string|int $other): bool
    {
        return $this->value->isLessThan(self::extract($other));
    }

    public function isLessThanOrEqualTo(self|BigDecimal|string|int $other): bool
    {
        return $this->value->isLessThanOrEqualTo(self::extract($other));
    }

    public function equals(self|BigDecimal|string|int $other): bool
    {
        return $this->value->isEqualTo(self::extract($other));
    }

    public function toBigDecimal(): BigDecimal
    {
        return $this->value;
    }

    /** Fixed-scale string, e.g. "540.00" — never strips trailing zeros. */
    public function toString(): string
    {
        return $this->value->toScale(static::scale(), RoundingMode::HalfUp)->__toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function jsonSerialize(): string
    {
        return $this->toString();
    }

    private static function extract(self|BigDecimal|string|int $value): BigDecimal|string|int
    {
        return $value instanceof self ? $value->value : $value;
    }
}
