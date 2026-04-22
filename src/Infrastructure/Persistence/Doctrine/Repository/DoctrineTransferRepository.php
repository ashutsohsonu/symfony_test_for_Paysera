<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Account\ValueObject\AccountId;
use App\Domain\Shared\ValueObject\Currency;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Transfer\Entity\Transfer;
use App\Domain\Transfer\Repository\TransferRepositoryInterface;
use App\Domain\Transfer\ValueObject\TransferId;
use App\Infrastructure\Persistence\Doctrine\Entity\TransferOrmEntity;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine adapter for TransferRepositoryInterface.
 */
final class DoctrineTransferRepository implements TransferRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function save(Transfer $transfer): void
    {
        $existing = $this->entityManager->find(
            TransferOrmEntity::class,
            $transfer->getId()
        );

        if ($existing === null) {
            $ormEntity = $this->toOrmEntity($transfer);
            $this->entityManager->persist($ormEntity);
        } else {
            $existing->setStatus($transfer->getStatus());
            $existing->setFailureReason($transfer->getFailureReason());
            $existing->setUpdatedAt($transfer->getUpdatedAt());
        }

        $this->entityManager->flush();
    }

    public function findById(TransferId $id): ?Transfer
    {
        $ormEntity = $this->entityManager->find(TransferOrmEntity::class, $id);

        return $ormEntity !== null ? $this->toDomainEntity($ormEntity) : null;
    }

    public function findByIdempotencyKey(string $key): ?Transfer
    {
        $ormEntity = $this->entityManager
            ->getRepository(TransferOrmEntity::class)
            ->findOneBy(['idempotencyKey' => $key]);

        return $ormEntity !== null ? $this->toDomainEntity($ormEntity) : null;
    }

    // -------------------------------------------------------------------------
    // Mapping helpers
    // -------------------------------------------------------------------------

    private function toOrmEntity(Transfer $transfer): TransferOrmEntity
    {
        return new TransferOrmEntity(
            id: $transfer->getId(),
            sourceAccountId: $transfer->getSourceAccountId(),
            destinationAccountId: $transfer->getDestinationAccountId(),
            amountMinorUnits: $transfer->getAmount()->getAmount(),
            currency: $transfer->getAmount()->getCurrency()->value,
            status: $transfer->getStatus(),
            failureReason: $transfer->getFailureReason(),
            idempotencyKey: $transfer->getIdempotencyKey(),
            createdAt: $transfer->getCreatedAt(),
            updatedAt: $transfer->getUpdatedAt(),
        );
    }

    private function toDomainEntity(TransferOrmEntity $orm): Transfer
    {
        return Transfer::reconstitute(
            id: $orm->getId(),
            sourceAccountId: $orm->getSourceAccountId(),
            destinationAccountId: $orm->getDestinationAccountId(),
            amount: Money::of(
                $orm->getAmountMinorUnits(),
                Currency::fromString($orm->getCurrency())
            ),
            status: $orm->getStatus(),
            failureReason: $orm->getFailureReason(),
            idempotencyKey: $orm->getIdempotencyKey(),
            createdAt: $orm->getCreatedAt(),
            updatedAt: $orm->getUpdatedAt(),
        );
    }
}
