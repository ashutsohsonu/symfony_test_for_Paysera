<?php

declare(strict_types=1);

namespace App\Application\Transfer\Command;

/**
 * CQRS Command — carries the intent to transfer funds.
 * Immutable DTO: no logic, only data.
 */
final class TransferFundsCommand
{
    public function __construct(
        public readonly string $sourceAccountId,
        public readonly string $destinationAccountId,
        public readonly int $amountMinorUnits,
        public readonly string $currency,
        public readonly string $idempotencyKey,
    ) {}
}
