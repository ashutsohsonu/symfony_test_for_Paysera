<?php

declare(strict_types=1);

namespace App\Application\Account\Handler;

use App\Application\Account\Command\CreateAccountCommand;
use App\Domain\Account\Entity\Account;
use App\Domain\Account\Repository\AccountRepositoryInterface;
use App\Domain\Account\ValueObject\AccountId;
use App\Domain\Shared\ValueObject\Currency;
use App\Domain\Account\Exception\DuplicateAccountException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateAccountCommandHandler
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
    ) {}

    public function __invoke(CreateAccountCommand $command): string
    {
        $id      = AccountId::generate();
        $currency = Currency::fromString($command->currency);

        // Guard against duplicate accounts (same owner + currency)
        $existing = $this->accountRepository->findByOwnerNameAndCurrency(
            $command->ownerName,
            $currency
        );

        if ($existing !== null) {
            throw DuplicateAccountException::forOwnerAndCurrency(
                $command->ownerName,
                $command->currency
            );
        }

        $id = AccountId::generate();

        $account = Account::create(
            id: $id,
            ownerName: $command->ownerName,
            currency: $currency,
            initialBalanceMinorUnits: $command->initialBalanceMinorUnits,
        );

        $this->accountRepository->save($account);

        return $id->toString();
    }
}
