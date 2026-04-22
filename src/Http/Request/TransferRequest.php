<?php

declare(strict_types=1);

namespace App\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Validated request DTO for POST /api/transfers.
 */
final class TransferRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'source_account_id is required.')]
        #[Assert\Uuid(message: 'source_account_id must be a valid UUID.')]
        public readonly string $sourceAccountId,

        #[Assert\NotBlank(message: 'destination_account_id is required.')]
        #[Assert\Uuid(message: 'destination_account_id must be a valid UUID.')]
        public readonly string $destinationAccountId,

        #[Assert\NotBlank(message: 'amount is required.')]
        #[Assert\Positive(message: 'amount must be a positive integer (minor units).')]
        #[Assert\Type(type: 'integer', message: 'amount must be an integer in minor units (e.g. 1050 = $10.50).')]
        public readonly int $amount,

        #[Assert\NotBlank(message: 'currency is required.')]
        #[Assert\Choice(choices: ['USD', 'EUR', 'GBP', 'INR'], message: 'currency must be one of: USD, EUR, GBP, INR.')]
        public readonly string $currency,

        #[Assert\NotBlank(message: 'idempotency_key is required.')]
        #[Assert\Length(min: 8, max: 255, minMessage: 'idempotency_key must be at least 8 characters.')]
        public readonly string $idempotencyKey,
    ) {}
}
