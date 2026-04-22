<?php

declare(strict_types=1);

namespace App\Domain\Transfer\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * Strongly-typed Transfer ID value object.
 */
final class TransferId
{
    private function __construct(private readonly string $value)
    {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid TransferId: "%s" is not a valid UUID.', $value)
            );
        }
    }

    public static function generate(): self
    {
        return new self(Uuid::v7()->toRfc4122());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
