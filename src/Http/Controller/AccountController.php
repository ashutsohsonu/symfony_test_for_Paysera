<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Application\Account\Command\CreateAccountCommand;
use App\Application\Account\Query\AccountView;
use App\Application\Account\Query\GetAccountQuery;
use App\Application\Transfer\Service\RateLimiterService;
use App\Domain\Account\Exception\DuplicateAccountException;
use App\Http\Request\CreateAccountRequest;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/accounts', name: 'api_accounts_')]
final class AccountController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly MessageBusInterface $queryBus,
        private readonly ValidatorInterface $validator,
        private readonly RateLimiterService $rateLimiter,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * POST /api/accounts
     * Create a new account.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->rateLimiter->isAllowed($request->getClientIp() ?? 'unknown')) {
            return $this->json([
                'status' => 'error',
                'message' => 'Rate limit exceeded. Please try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $dto = new CreateAccountRequest(
            ownerName: $data['owner_name'] ?? '',
            currency: $data['currency'] ?? '',
            initialBalance: (int) ($data['initial_balance'] ?? 0),
        );

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->validationErrorResponse($violations);
        }

        $envelope = $this->commandBus->dispatch(new CreateAccountCommand(
            ownerName: $dto->ownerName,
            currency: $dto->currency,
            initialBalanceMinorUnits: $dto->initialBalance,
        ));

        try {
            $accountId = $envelope->last(HandledStamp::class)->getResult();
        } catch (\Throwable $e) {
            $cause = $e->getPrevious() ?? $e;

            if ($cause instanceof DuplicateAccountException) {
                return $this->json([
                    'status' => 'error',
                    'message' => $cause->getMessage(),
                ], Response::HTTP_CONFLICT);
            }

            throw $e;
        }

        $this->logger->info('Account created via API', ['account_id' => $accountId]);

        return $this->json([
            'status' => 'success',
            'account_id' => $accountId,
            'message' => 'Account created successfully.',
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/accounts/{id}
     * Retrieve account details.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $envelope = $this->queryBus->dispatch(new GetAccountQuery($id));

        /** @var AccountView $view */
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
