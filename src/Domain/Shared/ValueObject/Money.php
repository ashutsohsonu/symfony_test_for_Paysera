<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObject;

/**
 * Immutable Money value object.
 * Stores amount in minor units (cents) to avoid floating-point errors.
 */
final class Money
{
    private function __construct(
        private readonly int $amount,
        private readonly Currency $currency,
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException(
                sprintf('Money amount cannot be negative, got: %d', $amount)
            );
        }
    }

    public static function of(int $amount, Currency $currency): self
    {
        return new self($amount, $currency);
    }

    public static function ofFloat(float $amount, Currency $currency): self
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Money amount cannot be negative.');
        }
        // Convert to minor units (e.g. 10.50 USD → 1050 cents)
        $minor = (int) round($amount * (10 ** $currency->getDecimalPlaces()));

        return new self($minor, $currency);
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function toFloat(): float
    {
        return $this->amount / (10 ** $this->currency->getDecimalPlaces());
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        $result = $this->amount - $other->amount;

        if ($result < 0) {
            throw new \DomainException(
                sprintf(
                    'Insufficient funds: cannot subtract %d from %d %s',
                    $other->amount,
                    $this->amount,
                    $this->currency->value
                )
            );
        }

        return new self($result, $this->currency);
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function isGreaterThanOrEqual(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount >= $other->amount;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency === $other->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \DomainException(
                sprintf(
                    'Currency mismatch: %s vs %s',
                    $this->currency->value,
                    $other->currency->value
                )
            );
        }
    }

    public function __toString(): string
    {
        return sprintf('%s %s', number_format($this->toFloat(), $this->currency->getDecimalPlaces()), $this->currency->value);
    }
}
