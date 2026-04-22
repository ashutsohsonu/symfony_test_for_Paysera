<?php

declare(strict_types=1);

namespace App\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Validated request DTO for POST /api/accounts.
 */
final class CreateAccountRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'owner_name is required.')]
        #[Assert\Length(max: 255, maxMessage: 'owner_name cannot exceed 255 characters.')]
        public readonly string $ownerName,

        #[Assert\NotBlank(message: 'currency is required.')]
        #[Assert\Choice(choices: ['USD', 'EUR', 'GBP', 'INR'], message: 'currency must be one of: USD, EUR, GBP, INR.')]
        public readonly string $currency,

        #[Assert\PositiveOrZero(message: 'initial_balance must be zero or a positive integer.')]
        public readonly int $initialBalance = 0,
    ) {}
}
