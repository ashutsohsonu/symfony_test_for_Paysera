<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Shared\ValueObject;

use App\Domain\Shared\ValueObject\Currency;
use App\Domain\Shared\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function testOfCreatesMoneyInMinorUnits(): void
    {
        $money = Money::of(1050, Currency::USD);

        self::assertSame(1050, $money->getAmount());
        self::assertSame(Currency::USD, $money->getCurrency());
        self::assertEqualsWithDelta(10.50, $money->toFloat(), 0.001);
    }

    public function testOfFloatConvertsToMinorUnits(): void
    {
        $money = Money::ofFloat(10.50, Currency::USD);

        self::assertSame(1050, $money->getAmount());
    }

    public function testOfFloatRoundsCorrectly(): void
    {
        $money = Money::ofFloat(10.999, Currency::USD);

        // Should round to 1100 cents
        self::assertSame(1100, $money->getAmount());
    }

    public function testNegativeAmountThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot be negative/');

        Money::of(-1, Currency::USD);
    }

    // -------------------------------------------------------------------------
    // Arithmetic
    // -------------------------------------------------------------------------

    public function testAdd(): void
    {
        $a = Money::of(500, Currency::USD);
        $b = Money::of(300, Currency::USD);

        self::assertSame(800, $a->add($b)->getAmount());
    }

    public function testSubtract(): void
    {
        $a = Money::of(500, Currency::USD);
        $b = Money::of(300, Currency::USD);

        self::assertSame(200, $a->subtract($b)->getAmount());
    }

    public function testSubtractInsufficientFundsThrows(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Insufficient funds/');

        Money::of(100, Currency::USD)->subtract(Money::of(200, Currency::USD));
    }

    public function testCurrencyMismatchOnAddThrows(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Currency mismatch/');

        Money::of(100, Currency::USD)->add(Money::of(100, Currency::EUR));
    }

    public function testCurrencyMismatchOnSubtractThrows(): void
    {
        $this->expectException(\DomainException::class);

        Money::of(500, Currency::USD)->subtract(Money::of(100, Currency::EUR));
    }

    // -------------------------------------------------------------------------
    // Comparison
    // -------------------------------------------------------------------------

    public function testIsGreaterThan(): void
    {
        self::assertTrue(Money::of(200, Currency::USD)->isGreaterThan(Money::of(100, Currency::USD)));
        self::assertFalse(Money::of(100, Currency::USD)->isGreaterThan(Money::of(200, Currency::USD)));
    }

    public function testIsZero(): void
    {
        self::assertTrue(Money::of(0, Currency::USD)->isZero());
        self::assertFalse(Money::of(1, Currency::USD)->isZero());
    }

    public function testEquals(): void
    {
        $a = Money::of(100, Currency::USD);
        $b = Money::of(100, Currency::USD);
        $c = Money::of(200, Currency::USD);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    public function testToString(): void
    {
        $money = Money::of(1050, Currency::USD);

        self::assertSame('10.50 USD', (string) $money);
    }
}
