<?php

declare(strict_types=1);

namespace App\Application\Transfer\Query;

/**
 * CQRS Query — fetch a transfer by ID.
 */
final class GetTransferQuery
{
    public function __construct(
        public readonly string $transferId,
    ) {}
}
