<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObject;

/**
 * Currency backed enum.
 * Only supported currencies are allowed — prevents typos and invalid codes.
 */
enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case INR = 'INR';

    public function getDecimalPlaces(): int
    {
        return match ($this) {
            self::USD, self::EUR, self::GBP => 2,
            self::INR => 2,
        };
    }

    public static function fromString(string $code): self
    {
        return self::from(strtoupper(trim($code)));
    }
}
