<?php

declare(strict_types=1);

namespace App\Application\Account\Query;

/**
 * Read model returned by GetAccountQueryHandler.
 * Decoupled from the domain entity — safe to expose over HTTP.
 */
final class AccountView
{
    public function __construct(
        public readonly string $id,
        public readonly string $ownerName,
        public readonly string $currency,
        public readonly float $balance,
        public readonly int $balanceMinorUnits,
        public readonly bool $active,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'owner_name'          => $this->ownerName,
            'currency'            => $this->currency,
            'balance'             => $this->balance,
            'balance_minor_units' => $this->balanceMinorUnits,
            'active'              => $this->active,
            'created_at'          => $this->createdAt,
            'updated_at'          => $this->updatedAt,
        ];
    }
}
