<?php

declare(strict_types=1);

namespace App\Domain\Account\Repository;

use App\Domain\Account\Entity\Account;
use App\Domain\Account\ValueObject\AccountId;
use App\Domain\Shared\ValueObject\Currency;

/**
 * Port (interface) for Account persistence — part of the Hexagonal / Ports-and-Adapters pattern.
 * Concrete implementations live in the Infrastructure layer.
 */
interface AccountRepositoryInterface
{
    /**
     * Persist a new or updated Account.
     */
    public function save(Account $account): void;

    /**
     * Find an Account by its ID.
     * Returns null when not found.
     */
    public function findById(AccountId $id): ?Account;

    /**
     * Find Account by ID and acquire a pessimistic write lock.
     * Must be called inside an active database transaction.
     *
     * @throws \RuntimeException when not inside a transaction
     */
    public function findByIdWithLock(AccountId $id): ?Account;

    /**
     * Find an Account by owner name and currency.
     * Used to prevent duplicate account creation.
     */
    public function findByOwnerNameAndCurrency(string $ownerName, Currency $currency): ?Account;
}
