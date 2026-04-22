<?php

declare(strict_types=1);

namespace App\Domain\Transfer\Entity;

enum TransferStatus: string
{
    case PENDING   = 'pending';
    case COMPLETED = 'completed';
    case FAILED    = 'failed';
}
