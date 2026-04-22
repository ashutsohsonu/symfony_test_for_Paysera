<?php

declare(strict_types=1);

namespace App\Domain\Account\Exception;

/**
 * Thrown when an account with the same owner name and currency already exists.
 */
final class DuplicateAccountException extends \DomainException
{
    public static function forOwnerAndCurrency(string $ownerName, string $currency): self
    {
        return new self(
            sprintf(
                'An account for owner "%s" with currency "%s" already exists.',
                $ownerName,
                $currency
            )
        );
    }
}
