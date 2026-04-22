<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Account\Entity\Account;
use App\Domain\Account\Repository\AccountRepositoryInterface;
use App\Domain\Account\ValueObject\AccountId;
use App\Domain\Shared\ValueObject\Currency;
use App\Domain\Shared\ValueObject\Money;
use App\Infrastructure\Persistence\Doctrine\Entity\AccountOrmEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\LockMode;

/**
 * Doctrine adapter for AccountRepositoryInterface.
 *
 * Implements the anti-corruption layer between:
 *  - Domain: Account aggregate (pure PHP, no ORM annotations)
 *  - Persistence: AccountOrmEntity (Doctrine-aware, flat structure)
 */
final class DoctrineAccountRepository implements AccountRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function save(Account $account): void
    {
        $existing = $this->entityManager->find(
            AccountOrmEntity::class,
            $account->getId()
        );

        if ($existing === null) {
            $ormEntity = $this->toOrmEntity($account);
            $this->entityManager->persist($ormEntity);
        } else {
            // Update only mutable fields — ID and createdAt are immutable
            $existing->setOwnerName($account->getOwnerName());
            $existing->setActive($account->isActive());
            $existing->setBalanceMinorUnits($account->getBalance()->getAmount());
            $existing->setUpdatedAt($account->getUpdatedAt());
        }

        $this->entityManager->flush();
    }

    public function findById(AccountId $id): ?Account
    {
        $ormEntity = $this->entityManager->find(AccountOrmEntity::class, $id);

        return $ormEntity !== null ? $this->toDomainEntity($ormEntity) : null;
    }

    public function findByIdWithLock(AccountId $id): ?Account
    {
        /** @var AccountOrmEntity|null $ormEntity */
        $ormEntity = $this->entityManager->find(
            AccountOrmEntity::class,
            $id,
            LockMode::PESSIMISTIC_WRITE
        );

        return $ormEntity !== null ? $this->toDomainEntity($ormEntity) : null;
    }

    public function findByOwnerNameAndCurrency(string $ownerName, Currency $currency): ?Account
    {
        /** @var AccountOrmEntity|null $ormEntity */
        $ormEntity = $this->entityManager
            ->createQueryBuilder()
            ->select('a')
            ->from(AccountOrmEntity::class, 'a')
            ->where('a.ownerName = :ownerName')
            ->andWhere('a.currency = :currency')
            ->setParameter('ownerName', $ownerName)
            ->setParameter('currency', $currency->value)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $ormEntity !== null ? $this->toDomainEntity($ormEntity) : null;
    }

    // -------------------------------------------------------------------------
    // Mapping helpers (Anti-Corruption Layer)
    // -------------------------------------------------------------------------

    private function toOrmEntity(Account $account): AccountOrmEntity
    {
        return new AccountOrmEntity(
            id: $account->getId(),
            ownerName: $account->getOwnerName(),
            active: $account->isActive(),
            balanceMinorUnits: $account->getBalance()->getAmount(),
            currency: $account->getCurrency()->value,
            createdAt: $account->getCreatedAt(),
            updatedAt: $account->getUpdatedAt(),
        );
    }

    private function toDomainEntity(AccountOrmEntity $orm): Account
    {
        return Account::reconstitute(
            id: $orm->getId(),
            ownerName: $orm->getOwnerName(),
            active: $orm->isActive(),
            balance: Money::of(
                $orm->getBalanceMinorUnits(),
                Currency::fromString($orm->getCurrency())
            ),
            createdAt: $orm->getCreatedAt(),
            updatedAt: $orm->getUpdatedAt(),
        );
    }
}
