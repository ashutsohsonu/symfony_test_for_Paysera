<?php

declare(strict_types=1);

namespace App\Application\Transfer\Handler;

use App\Application\Transfer\Query\GetTransferQuery;
use App\Application\Transfer\Query\TransferView;
use App\Domain\Transfer\Repository\TransferRepositoryInterface;
use App\Domain\Transfer\ValueObject\TransferId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetTransferQueryHandler
{
    public function __construct(
        private readonly TransferRepositoryInterface $transferRepository,
    ) {}

    public function __invoke(GetTransferQuery $query): TransferView
    {
        $transfer = $this->transferRepository->findById(
            TransferId::fromString($query->transferId)
        );

        if ($transfer === null) {
            throw new \DomainException(
                sprintf('Transfer "%s" not found.', $query->transferId)
            );
        }

        return new TransferView(
            id: $transfer->getId()->toString(),
            sourceAccountId: $transfer->getSourceAccountId()->toString(),
            destinationAccountId: $transfer->getDestinationAccountId()->toString(),
            currency: $transfer->getAmount()->getCurrency()->value,
            amount: $transfer->getAmount()->toFloat(),
            amountMinorUnits: $transfer->getAmount()->getAmount(),
            status: $transfer->getStatus()->value,
            failureReason: $transfer->getFailureReason(),
            idempotencyKey: $transfer->getIdempotencyKey(),
            createdAt: $transfer->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $transfer->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
