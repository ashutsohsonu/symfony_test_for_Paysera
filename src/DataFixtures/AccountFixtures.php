<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Domain\Account\Entity\Account;
use App\Domain\Account\Repository\AccountRepositoryInterface;
use App\Domain\Account\ValueObject\AccountId;
use App\Domain\Shared\ValueObject\Currency;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Seed data for development and integration testing.
 * These IDs are stable so tests can reference them deterministically.
 */
final class AccountFixtures extends Fixture
{
    public const ALICE_ID = '019daf5a-9553-71cc-a1b3-92a4b6fd292b';
    public const BOB_ID   = '019db34f-ba2e-7eb3-a978-fa68f2b14309';
    public const BROKE_ID = '019daf5a-9553-71cc-a1b3-92a4b6fd292b';

    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Alice — $1,000.00 USD
        $alice = Account::create(
            AccountId::fromString(self::ALICE_ID),
            'Alice Smith',
            Currency::USD,
            100000  // $1,000.00 in minor units
        );
        $this->accountRepository->save($alice);
        $this->addReference('account_alice', $alice);

        // Bob — $500.00 USD
        $bob = Account::create(
            AccountId::fromString(self::BOB_ID),
            'Bob Jones',
            Currency::USD,
            50000   // $500.00
        );
        $this->accountRepository->save($bob);
        $this->addReference('account_bob', $bob);

        // Broke — $0.00 USD (for insufficient-funds tests)
        $broke = Account::create(
            AccountId::fromString(self::BROKE_ID),
            'Broke User',
            Currency::USD,
            0
        );
        $this->accountRepository->save($broke);
        $this->addReference('account_broke', $broke);
    }
}
