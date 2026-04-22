<?php

declare(strict_types=1);

namespace App\Application\Account\Query;

/**
 * CQRS Query — fetch a single account by ID.
 */
final class GetAccountQuery
{
    public function __construct(
        public readonly string $accountId,
    ) {}
}
