<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Repository;

use App\Domain\Transfer\Entity\Transfer;
use App\Domain\Transfer\ValueObject\TransferId;

/**
 * Port for Transfer persistence.
 */
interface TransferRepositoryInterface
{
    public function save(Transfer $transfer): void;

    public function findById(TransferId $id): ?Transfer;

    public function findByIdempotencyKey(string $key): ?Transfer;
}
