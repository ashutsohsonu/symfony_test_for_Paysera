<?php

declare(strict_types=1);

namespace App\Application\Transfer\Query;

/**
 * Read model returned by GetTransferQueryHandler.
 */
final class TransferView
{
    public function __construct(
        public readonly string $id,
        public readonly string $sourceAccountId,
        public readonly string $destinationAccountId,
        public readonly string $currency,
        public readonly float $amount,
        public readonly int $amountMinorUnits,
        public readonly string $status,
        public readonly ?string $failureReason,
        public readonly string $idempotencyKey,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'id'                    => $this->id,
            'source_account_id'     => $this->sourceAccountId,
            'destination_account_id'=> $this->destinationAccountId,
            'currency'              => $this->currency,
            'amount'                => $this->amount,
            'amount_minor_units'    => $this->amountMinorUnits,
            'status'                => $this->status,
            'failure_reason'        => $this->failureReason,
            'idempotency_key'       => $this->idempotencyKey,
            'created_at'            => $this->createdAt,
            'updated_at'            => $this->updatedAt,
        ];
    }
}
