<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Account\Entity;

use App\Domain\Account\Entity\Account;
use App\Domain\Account\ValueObject\AccountId;
use App\Domain\Shared\ValueObject\Currency;
use App\Domain\Shared\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class AccountTest extends TestCase
{
    private Account $account;

    protected function setUp(): void
    {
        $this->account = Account::create(
            AccountId::generate(),
            'Alice Smith',
            Currency::USD,
            100000  // $1,000.00
        );
    }

    public function testCreateSetsCorrectInitialState(): void
    {
        self::assertSame('Alice Smith', $this->account->getOwnerName());
        self::assertSame(Currency::USD, $this->account->getCurrency());
        self::assertSame(100000, $this->account->getBalance()->getAmount());
        self::assertTrue($this->account->isActive());
    }

    public function testDebitReducesBalance(): void
    {
        $this->account->debit(Money::of(25000, Currency::USD));

        self::assertSame(75000, $this->account->getBalance()->getAmount());
    }

    public function testCreditIncreasesBalance(): void
    {
        $this->account->credit(Money::of(10000, Currency::USD));

        self::assertSame(110000, $this->account->getBalance()->getAmount());
    }

    public function testDebitFailsWhenInsufficientFunds(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Insufficient funds/');

        $this->account->debit(Money::of(200000, Currency::USD)); // More than $1,000
    }

    public function testDebitFailsWhenAccountFrozen(): void
    {
        $this->account->freeze();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/frozen/');

        $this->account->debit(Money::of(100, Currency::USD));
    }

    public function testCreditFailsWhenAccountFrozen(): void
    {
        $this->account->freeze();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/frozen/');

        $this->account->credit(Money::of(100, Currency::USD));
    }

    public function testFreezeAndUnfreeze(): void
    {
        $this->account->freeze();
        self::assertFalse($this->account->isActive());

        $this->account->unfreeze();
        self::assertTrue($this->account->isActive());

        // Should work again after unfreeze
        $this->account->debit(Money::of(100, Currency::USD));
        self::assertSame(99900, $this->account->getBalance()->getAmount());
    }

    public function testDebitZeroAmountThrows(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/greater than zero/');

        $this->account->debit(Money::of(0, Currency::USD));
    }

    public function testCreateWithEmptyOwnerNameThrows(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Owner name/');

        Account::create(AccountId::generate(), '  ', Currency::USD);
    }

    public function testUpdatedAtChangesAfterMutation(): void
    {
        $before = $this->account->getUpdatedAt();

        // Small sleep to ensure timestamp differs
        usleep(1000);
        $this->account->debit(Money::of(100, Currency::USD));

        self::assertGreaterThanOrEqual($before, $this->account->getUpdatedAt());
    }
}
