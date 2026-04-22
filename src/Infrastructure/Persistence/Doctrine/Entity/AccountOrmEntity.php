<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use App\Domain\Transfer\Entity\TransferStatus;
use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine ORM entity for the `accounts` table.
 * This is intentionally separate from the domain Account aggregate.
 * The repository maps between these two representations.
 */
#[ORM\Entity]
#[ORM\Table(name: 'accounts')]
#[ORM\Index(columns: ['active'], name: 'idx_accounts_active')]
#[ORM\HasLifecycleCallbacks]
class AccountOrmEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'account_id')]
    private \App\Domain\Account\ValueObject\AccountId $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $ownerName;

    #[ORM\Column(type: 'boolean')]
    private bool $active;

    /**
     * Balance stored in minor units (cents).
     * Avoids all floating-point precision issues.
     */
    #[ORM\Column(type: 'money', options: ['unsigned' => true])]
    private int $balanceMinorUnits;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        \App\Domain\Account\ValueObject\AccountId $id,
        string $ownerName,
        bool $active,
        int $balanceMinorUnits,
        string $currency,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ) {
        $this->id                = $id;
        $this->ownerName         = $ownerName;
        $this->active            = $active;
        $this->balanceMinorUnits = $balanceMinorUnits;
        $this->currency          = $currency;
        $this->createdAt         = $createdAt;
        $this->updatedAt         = $updatedAt;
    }

    public function getId(): \App\Domain\Account\ValueObject\AccountId { return $this->id; }
    public function getOwnerName(): string { return $this->ownerName; }
    public function isActive(): bool { return $this->active; }
    public function getBalanceMinorUnits(): int { return $this->balanceMinorUnits; }
    public function getCurrency(): string { return $this->currency; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function setOwnerName(string $ownerName): void { $this->ownerName = $ownerName; }
    public function setActive(bool $active): void { $this->active = $active; }
    public function setBalanceMinorUnits(int $balanceMinorUnits): void { $this->balanceMinorUnits = $balanceMinorUnits; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void { $this->updatedAt = $updatedAt; }
}
