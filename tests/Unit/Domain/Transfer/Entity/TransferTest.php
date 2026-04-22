<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Transfer\Entity;

use App\Domain\Account\ValueObject\AccountId;
use App\Domain\Shared\ValueObject\Currency;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Transfer\Entity\Transfer;
use App\Domain\Transfer\Entity\TransferStatus;
use App\Domain\Transfer\ValueObject\TransferId;
use PHPUnit\Framework\TestCase;

final class TransferTest extends TestCase
{
    private AccountId $sourceId;
    private AccountId $destId;
    private Money $amount;

    protected function setUp(): void
    {
        $this->sourceId = AccountId::generate();
        $this->destId   = AccountId::generate();
        $this->amount   = Money::of(5000, Currency::USD);
    }

    public function testInitiateCreatesTransferInPendingState(): void
    {
        $transfer = Transfer::initiate(
            TransferId::generate(),
            $this->sourceId,
            $this->destId,
            $this->amount,
            'idem-key-001'
        );

        self::assertSame(TransferStatus::PENDING, $transfer->getStatus());
        self::assertNull($transfer->getFailureReason());
        self::assertSame('idem-key-001', $transfer->getIdempotencyKey());
    }

    public function testCompleteTransitionsToCompleted(): void
    {
        $transfer = $this->makeTransfer();
        $transfer->complete();

        self::assertSame(TransferStatus::COMPLETED, $transfer->getStatus());
    }

    public function testFailTransitionsToFailed(): void
    {
        $transfer = $this->makeTransfer();
        $transfer->fail('Insufficient funds');

        self::assertSame(TransferStatus::FAILED, $transfer->getStatus());
        self::assertSame('Insufficient funds', $transfer->getFailureReason());
    }

    public function testCannotCompleteAlreadyCompletedTransfer(): void
    {
        $this->expectException(\DomainException::class);

        $transfer = $this->makeTransfer();
        $transfer->complete();
        $transfer->complete(); // Second call must throw
    }

    public function testCannotFailAlreadyCompletedTransfer(): void
    {
        $this->expectException(\DomainException::class);

        $transfer = $this->makeTransfer();
        $transfer->complete();
        $transfer->fail('Too late');
    }

    public function testSameSourceAndDestinationThrows(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/different/');

        Transfer::initiate(
            TransferId::generate(),
            $this->sourceId,
            $this->sourceId, // Same ID!
            $this->amount,
            'idem-key-002'
        );
    }

    public function testZeroAmountTransferThrows(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/greater than zero/');

        Transfer::initiate(
            TransferId::generate(),
            $this->sourceId,
            $this->destId,
            Money::of(0, Currency::USD),
            'idem-key-003'
        );
    }

    private function makeTransfer(): Transfer
    {
        return Transfer::initiate(
            TransferId::generate(),
            $this->sourceId,
            $this->destId,
            $this->amount,
            'idem-key-' . uniqid()
        );
    }
}
