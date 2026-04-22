<?php

declare(strict_types=1);

namespace App\Application\Account\Handler;

use App\Application\Account\Query\AccountView;
use App\Application\Account\Query\GetAccountQuery;
use App\Domain\Account\Repository\AccountRepositoryInterface;
use App\Domain\Account\ValueObject\AccountId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetAccountQueryHandler
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
    ) {}

    public function __invoke(GetAccountQuery $query): AccountView
    {
        $account = $this->accountRepository->findById(
            AccountId::fromString($query->accountId)
        );

        if ($account === null) {
            throw new \DomainException(
                sprintf('Account "%s" not found.', $query->accountId)
            );
        }

        return new AccountView(
            id: $account->getId()->toString(),
            ownerName: $account->getOwnerName(),
            currency: $account->getCurrency()->value,
            balance: $account->getBalance()->toFloat(),
            balanceMinorUnits: $account->getBalance()->getAmount(),
            active: $account->isActive(),
            createdAt: $account->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $account->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
