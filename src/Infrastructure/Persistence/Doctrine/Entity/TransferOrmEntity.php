<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use App\Domain\Transfer\Entity\TransferStatus;
use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine ORM entity for the `transfers` table.
 */
#[ORM\Entity]
#[ORM\Table(name: 'transfers')]
#[ORM\Index(columns: ['idempotency_key'], name: 'idx_transfers_idempotency_key')]
#[ORM\Index(columns: ['source_account_id'], name: 'idx_transfers_source')]
#[ORM\Index(columns: ['destination_account_id'], name: 'idx_transfers_destination')]
#[ORM\Index(columns: ['status'], name: 'idx_transfers_status')]
class TransferOrmEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'transfer_id')]
    private \App\Domain\Transfer\ValueObject\TransferId $id;

    #[ORM\Column(type: 'account_id')]
    private \App\Domain\Account\ValueObject\AccountId $sourceAccountId;

    #[ORM\Column(type: 'account_id')]
    private \App\Domain\Account\ValueObject\AccountId $destinationAccountId;

    #[ORM\Column(type: 'money', options: ['unsigned' => true])]
    private int $amountMinorUnits;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    #[ORM\Column(type: 'string', length: 20, enumType: TransferStatus::class)]
    private TransferStatus $status;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $failureReason;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $idempotencyKey;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        \App\Domain\Transfer\ValueObject\TransferId $id,
        \App\Domain\Account\ValueObject\AccountId $sourceAccountId,
        \App\Domain\Account\ValueObject\AccountId $destinationAccountId,
        int $amountMinorUnits,
        string $currency,
        TransferStatus $status,
        ?string $failureReason,
        string $idempotencyKey,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ) {
        $this->id                   = $id;
        $this->sourceAccountId      = $sourceAccountId;
        $this->destinationAccountId = $destinationAccountId;
        $this->amountMinorUnits     = $amountMinorUnits;
        $this->currency             = $currency;
        $this->status               = $status;
        $this->failureReason        = $failureReason;
        $this->idempotencyKey       = $idempotencyKey;
        $this->createdAt            = $createdAt;
        $this->updatedAt            = $updatedAt;
    }

    public function getId(): \App\Domain\Transfer\ValueObject\TransferId { return $this->id; }
    public function getSourceAccountId(): \App\Domain\Account\ValueObject\AccountId { return $this->sourceAccountId; }
    public function getDestinationAccountId(): \App\Domain\Account\ValueObject\AccountId { return $this->destinationAccountId; }
    public function getAmountMinorUnits(): int { return $this->amountMinorUnits; }
    public function getCurrency(): string { return $this->currency; }
    public function getStatus(): TransferStatus { return $this->status; }
    public function getFailureReason(): ?string { return $this->failureReason; }
    public function getIdempotencyKey(): string { return $this->idempotencyKey; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function setStatus(TransferStatus $status): void { $this->status = $status; }
    public function setFailureReason(?string $reason): void { $this->failureReason = $reason; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void { $this->updatedAt = $updatedAt; }
}
