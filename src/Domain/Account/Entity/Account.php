<?php

declare(strict_types=1);

namespace App\Domain\Account\Entity;

use App\Domain\Account\ValueObject\AccountId;
use App\Domain\Shared\ValueObject\Currency;
use App\Domain\Shared\ValueObject\Money;

/**
 * Account aggregate root.
 *
 * All balance mutation happens here to enforce invariants:
 *  - Balance cannot go negative (overdraft protection)
 *  - Currency must match on all operations
 *  - Frozen/inactive accounts cannot transact
 */
final class Account
{
    private Money $balance;

    private function __construct(
        private readonly AccountId $id,
        private string $ownerName,
        private bool $active,
        Money $balance,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
        $this->balance = $balance;
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    public static function create(
        AccountId $id,
        string $ownerName,
        Currency $currency,
        int $initialBalanceMinorUnits = 0,
    ): self {
        if (trim($ownerName) === '') {
            throw new \DomainException('Owner name cannot be empty.');
        }

        $now = new \DateTimeImmutable();

        return new self(
            id: $id,
            ownerName: $ownerName,
            active: true,
            balance: Money::of($initialBalanceMinorUnits, $currency),
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /**
     * Reconstitution factory — used by the repository to hydrate from persistence.
     */
    public static function reconstitute(
        AccountId $id,
        string $ownerName,
        bool $active,
        Money $balance,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self($id, $ownerName, $active, $balance, $createdAt, $updatedAt);
    }

    // -------------------------------------------------------------------------
    // Domain behaviour
    // -------------------------------------------------------------------------

    /**
     * Debit the account by the given amount.
     *
     * @throws \DomainException when account is inactive or balance is insufficient
     */
    public function debit(Money $amount): void
    {
        $this->assertActive();
        $this->assertPositiveAmount($amount);

        // Money::subtract throws DomainException on insufficient funds
        $this->balance = $this->balance->subtract($amount);
        $this->touch();
    }

    /**
     * Credit the account by the given amount.
     *
     * @throws \DomainException when account is inactive
     */
    public function credit(Money $amount): void
    {
        $this->assertActive();
        $this->assertPositiveAmount($amount);

        $this->balance = $this->balance->add($amount);
        $this->touch();
    }

    public function freeze(): void
    {
        $this->active = false;
        $this->touch();
    }

    public function unfreeze(): void
    {
        $this->active = true;
        $this->touch();
    }

    // -------------------------------------------------------------------------
    // Guards
    // -------------------------------------------------------------------------

    private function assertActive(): void
    {
        if (!$this->active) {
            throw new \DomainException(
                sprintf('Account %s is frozen and cannot be used for transactions.', $this->id)
            );
        }
    }

    private function assertPositiveAmount(Money $amount): void
    {
        if ($amount->isZero()) {
            throw new \DomainException('Transfer amount must be greater than zero.');
        }
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getId(): AccountId
    {
        return $this->id;
    }

    public function getOwnerName(): string
    {
        return $this->ownerName;
    }

    public function getBalance(): Money
    {
        return $this->balance;
    }

    public function getCurrency(): Currency
    {
        return $this->balance->getCurrency();
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
