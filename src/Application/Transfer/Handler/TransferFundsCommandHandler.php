<?php

declare(strict_types=1);

namespace App\Application\Transfer\Handler;

use App\Application\Transfer\Command\TransferFundsCommand;
use App\Application\Transfer\Service\IdempotencyService;
use App\Domain\Account\Repository\AccountRepositoryInterface;
use App\Domain\Account\ValueObject\AccountId;
use App\Domain\Shared\ValueObject\Currency;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Transfer\Entity\Transfer;
use App\Domain\Transfer\Repository\TransferRepositoryInterface;
use App\Domain\Transfer\ValueObject\TransferId;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * CQRS Command Handler for fund transfers.
 *
 * Concurrency strategy:
 *  1. Idempotency check (Redis) — fast-path: return existing result for duplicate keys
 *  2. Database transaction with pessimistic write locks on both accounts
 *     Accounts are always locked in a deterministic order (lower UUID first) to
 *     prevent deadlocks under concurrent requests.
 *  3. Domain logic executes inside the transaction — debit source, credit destination
 *  4. Transfer record saved atomically with the balance changes
 */
#[AsMessageHandler]
final class TransferFundsCommandHandler
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly TransferRepositoryInterface $transferRepository,
        private readonly IdempotencyService $idempotencyService,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(TransferFundsCommand $command): string
    {
        // -----------------------------------------------------------------------
        // 1. Idempotency: if we've already processed this key, return cached result
        // -----------------------------------------------------------------------
        $cached = $this->idempotencyService->get($command->idempotencyKey);
        if ($cached !== null) {
            $this->logger->info('Transfer idempotent hit', [
                'idempotency_key' => $command->idempotencyKey,
                'transfer_id'     => $cached,
            ]);

            return $cached;
        }

        $transferId = TransferId::generate();
        $currency   = Currency::fromString($command->currency);
        $amount     = Money::of($command->amountMinorUnits, $currency);
        $sourceId   = AccountId::fromString($command->sourceAccountId);
        $destId     = AccountId::fromString($command->destinationAccountId);

        // -----------------------------------------------------------------------
        // 2. Create the Transfer ledger record (PENDING)
        // -----------------------------------------------------------------------
        $transfer = Transfer::initiate(
            id: $transferId,
            sourceAccountId: $sourceId,
            destinationAccountId: $destId,
            amount: $amount,
            idempotencyKey: $command->idempotencyKey,
        );

        $this->logger->info('Transfer initiated', [
            'transfer_id' => $transferId->toString(),
            'source'      => $command->sourceAccountId,
            'destination' => $command->destinationAccountId,
            'amount'      => $amount->__toString(),
        ]);

        // -----------------------------------------------------------------------
        // 3. Execute within a serializable transaction with pessimistic locks
        // -----------------------------------------------------------------------
        $this->connection->beginTransaction();

        try {
            // Lock accounts in deterministic order to prevent deadlocks
            [$firstId, $secondId] = $this->orderAccountIds($sourceId, $destId);

            $first  = $this->accountRepository->findByIdWithLock($firstId);
            $second = $this->accountRepository->findByIdWithLock($secondId);

            if ($first === null) {
                throw new \DomainException(sprintf('Account %s not found.', $firstId));
            }

            if ($second === null) {
                throw new \DomainException(sprintf('Account %s not found.', $secondId));
            }

            // Re-map in correct source/dest roles after locking
            $source      = $sourceId->equals($first->getId()) ? $first : $second;
            $destination = $destId->equals($first->getId()) ? $first : $second;

            // 4. Domain mutations (all invariant checks happen inside the aggregates)
            $source->debit($amount);
            $destination->credit($amount);

            // 5. Persist state changes atomically
            $this->accountRepository->save($source);
            $this->accountRepository->save($destination);

            $transfer->complete();
            $this->transferRepository->save($transfer);

            $this->connection->commit();

            // 6. Cache under idempotency key so duplicates return instantly
            $this->idempotencyService->set($command->idempotencyKey, $transferId->toString());

            $this->logger->info('Transfer completed', [
                'transfer_id'    => $transferId->toString(),
                'source_balance' => $source->getBalance()->__toString(),
                'dest_balance'   => $destination->getBalance()->__toString(),
            ]);

            return $transferId->toString();
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            $transfer->fail($e->getMessage());

            try {
                $this->transferRepository->save($transfer);
            } catch (\Throwable $saveEx) {
                $this->logger->error('Could not persist failed transfer', [
                    'transfer_id' => $transferId->toString(),
                    'error'       => $saveEx->getMessage(),
                ]);
            }

            $this->logger->error('Transfer failed', [
                'transfer_id' => $transferId->toString(),
                'error'       => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Returns account IDs sorted lexicographically so locks are always acquired
     * in the same order regardless of which account is source/dest.
     * This eliminates the classic deadlock pattern under concurrent transfers.
     *
     * @return array{0: AccountId, 1: AccountId}
     */
    private function orderAccountIds(AccountId $a, AccountId $b): array
    {
        if (strcmp($a->toString(), $b->toString()) <= 0) {
            return [$a, $b];
        }

        return [$b, $a];
    }
}
