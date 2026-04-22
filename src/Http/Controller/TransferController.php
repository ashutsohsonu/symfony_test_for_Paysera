<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Application\Transfer\Command\TransferFundsCommand;
use App\Application\Transfer\Query\GetTransferQuery;
use App\Application\Transfer\Query\TransferView;
use App\Application\Transfer\Service\RateLimiterService;
use App\Http\Request\TransferRequest;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/transfers', name: 'api_transfers_')]
final class TransferController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly MessageBusInterface $queryBus,
        private readonly ValidatorInterface $validator,
        private readonly RateLimiterService $rateLimiter,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * POST /api/transfers
     *
     * Initiates a fund transfer between two accounts.
     *
     * Required headers:
     *   Content-Type: application/json
     *
     * Required body fields:
     *   source_account_id      string  UUID of the source account
     *   destination_account_id string  UUID of the destination account
     *   amount                 int     Amount in minor units (e.g. 1050 = $10.50)
     *   currency               string  ISO 4217 currency code (USD, EUR, GBP, INR)
     *   idempotency_key        string  Client-generated unique key (min 8 chars)
     *
     * Responses:
     *   201 Created            Transfer completed successfully
     *   200 OK                 Duplicate idempotent request — returns existing result
     *   422 Unprocessable      Validation or domain error
     *   429 Too Many Requests  Rate limit exceeded
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // Rate limit by IP address
        $clientIp = $request->getClientIp() ?? 'unknown';
        if (!$this->rateLimiter->isAllowed($clientIp)) {
            return $this->json([
                'status' => 'error',
                'message' => 'Rate limit exceeded. Please slow down.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $dto = new TransferRequest(
            sourceAccountId: $data['source_account_id'] ?? '',
            destinationAccountId: $data['destination_account_id'] ?? '',
            amount: isset($data['amount']) && is_int($data['amount']) ? $data['amount'] : 0,
            currency: $data['currency'] ?? '',
            idempotencyKey: $data['idempotency_key'] ?? '',
        );

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->validationErrorResponse($violations);
        }

        // Manual check for same account (legacy fix for missing expression-language component)
        if ($dto->sourceAccountId === $dto->destinationAccountId) {
            return $this->json([
                'status' => 'error',
                'message' => 'Validation Failed',
                'errors' => [
                    ['field' => 'destination_account_id', 'message' => 'Source and destination accounts must be different.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->logger->info('Transfer request received', [
            'source'          => $dto->sourceAccountId,
            'destination'     => $dto->destinationAccountId,
            'amount'          => $dto->amount,
            'currency'        => $dto->currency,
            'idempotency_key' => $dto->idempotencyKey,
            'client_ip'       => $clientIp,
        ]);

        try {
            $envelope = $this->commandBus->dispatch(new TransferFundsCommand(
                sourceAccountId: $dto->sourceAccountId,
                destinationAccountId: $dto->destinationAccountId,
                amountMinorUnits: $dto->amount,
                currency: $dto->currency,
                idempotencyKey: $dto->idempotencyKey,
            ));

            $transferId = $envelope->last(HandledStamp::class)->getResult();
        } catch (\Throwable $e) {
            $cause = $e->getPrevious() ?? $e;

            if ($cause instanceof \DomainException) {
                return $this->json([
                    'status' => 'error',
                    'message' => $cause->getMessage(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            throw $e;
        }

        return $this->json([
            'status' => 'success',
            'transfer_id' => $transferId,
            'message'     => 'Transfer completed successfully.',
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/transfers/{id}
     * Retrieve transfer details by ID.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $envelope = $this->queryBus->dispatch(new GetTransferQuery($id));

        /** @var TransferView $view */
        $view = $envelope->last(HandledStamp::class)->getResult();

        return $this->json(['data' => $view->toArray()]);
    }

    private function validationErrorResponse(iterable $violations): JsonResponse
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[] = [
                'field'   => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        return $this->json([
            'status' => 'error',
            'type'   => '/errors/validation',
            'message' => 'Validation Failed',
            'errors' => $errors,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
