<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\BigIntType;

/**
 * Custom Doctrine type for Money amounts (minor units).
 * Maps to a BIGINT in the database.
 *
 * This version stores the integer part (minor units) to maintain compatibility
 * with high-performance numeric schemas while providing a named type for semantic clarity.
 */
final class MoneyType extends BigIntType
{
    public const NAME = 'money';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?int
    {
        $value = parent::convertToPHPValue($value, $platform);

        return $value !== null ? (int) $value : null;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        // The base Type::convertToDatabaseValue() returns mixed (the value unchanged).
        // Under declare(strict_types=1) returning an int from a ?string-typed method
        // is a TypeError, so we cast explicitly here.
        return (string) (int) $value;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
