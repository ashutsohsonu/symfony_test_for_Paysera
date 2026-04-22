<?php

declare(strict_types=1);

namespace App\Application\Account\Command;

/**
 * CQRS Command — creates a new account.
 */
final class CreateAccountCommand
{
    public function __construct(
        public readonly string $ownerName,
        public readonly string $currency,
        public readonly int $initialBalanceMinorUnits = 0,
    ) {}
}
