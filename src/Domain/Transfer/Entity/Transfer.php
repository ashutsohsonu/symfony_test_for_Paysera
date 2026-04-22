<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Entity;

use App\Domain\Account\ValueObject\AccountId;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Transfer\ValueObject\TransferId;

/**
 * Transfer aggregate root.
 *
 * Represents an immutable ledger record of a completed fund movement.
 * Status transitions follow a strict state machine.
 */
final class Transfer
{
    private function __construct(
        private readonly TransferId $id,
        private readonly AccountId $sourceAccountId,
        private readonly AccountId $destinationAccountId,
        private readonly Money $amount,
        private TransferStatus $status,
        private ?string $failureReason,
        private readonly string $idempotencyKey,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {}

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    public static function initiate(
        TransferId $id,
        AccountId $sourceAccountId,
        AccountId $destinationAccountId,
        Money $amount,
        string $idempotencyKey,
    ): self {
        if ($sourceAccountId->equals($destinationAccountId)) {
            throw new \DomainException('Source and destination accounts must be different.');
        }

        if ($amount->isZero()) {
            throw new \DomainException('Transfer amount must be greater than zero.');
        }

        $now = new \DateTimeImmutable();

        return new self(
            id: $id,
            sourceAccountId: $sourceAccountId,
            destinationAccountId: $destinationAccountId,
            amount: $amount,
            status: TransferStatus::PENDING,
            failureReason: null,
            idempotencyKey: $idempotencyKey,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public static function reconstitute(
        TransferId $id,
        AccountId $sourceAccountId,
        AccountId $destinationAccountId,
        Money $amount,
        TransferStatus $status,
        ?string $failureReason,
        string $idempotencyKey,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            $id,
            $sourceAccountId,
            $destinationAccountId,
            $amount,
            $status,
            $failureReason,
            $idempotencyKey,
            $createdAt,
            $updatedAt,
        );
    }

    // -------------------------------------------------------------------------
    // State machine transitions
    // -------------------------------------------------------------------------

    public function complete(): void
    {
        $this->assertPending();
        $this->status = TransferStatus::COMPLETED;
        $this->touch();
    }

    public function fail(string $reason): void
    {
        $this->assertPending();
        $this->status = TransferStatus::FAILED;
        $this->failureReason = $reason;
        $this->touch();
    }

    // -------------------------------------------------------------------------
    // Guards
    // -------------------------------------------------------------------------

    private function assertPending(): void
    {
        if ($this->status !== TransferStatus::PENDING) {
            throw new \DomainException(
                sprintf(
                    'Cannot transition Transfer %s from status "%s".',
                    $this->id,
                    $this->status->value
                )
            );
        }
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getId(): TransferId
    {
        return $this->id;
    }

    public function getSourceAccountId(): AccountId
    {
        return $this->sourceAccountId;
    }

    public function getDestinationAccountId(): AccountId
    {
        return $this->destinationAccountId;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getStatus(): TransferStatus
    {
        return $this->status;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
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
